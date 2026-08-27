<?php
/**
 * Folio video preview helpers.
 *
 * Restricted-video derivatives are generated from a representative frame,
 * aggressively reduced, blurred with Imagick, and cached outside the public
 * source-video path. Vendor libraries are intentionally not modified.
 *
 * Copyright (C) 2026 Mohd Elfie Nieshaem Juferi. This program is free
 * software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any
 * later version. See license.txt, or <https://www.gnu.org/licenses/>.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/** Where a restricted video's auto-generated blurred frame preview is
 *  cached. Prefixed so its hash space never collides with a PDF's, even
 *  though a shared $rel would be astronomically unlikely on its own. */
function video_blur_cache_path(string $rel): string
{
    return dirname(__DIR__) . '/data/previews/' . hash('sha256', 'video:' . $rel) . '.jpg';
}

/**
 * Diagnostic logging for restricted-video blur previews.  Logging is deliberately
 * server-side only: the public response remains a generic 404 on failure so
 * internal paths, binaries, and exception details are never disclosed.
 *
 * Set VIDEO_BLUR_DIAGNOSTICS to false in config.php to silence these entries.
 */
if (!defined('VIDEO_BLUR_DIAGNOSTICS')) {
    define('VIDEO_BLUR_DIAGNOSTICS', true);
}

function video_blur_log(string $stage, string $message, array $context = []): void
{
    if (!VIDEO_BLUR_DIAGNOSTICS) {
        return;
    }
    static $seen = [];
    $key = $stage . '|' . $message;
    // Prevent a broken preview from filling the PHP error log on every page load.
    if (isset($seen[$key])) {
        return;
    }
    $seen[$key] = true;
    $parts = ['stage=' . $stage, $message];
    foreach ($context as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        $v = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_SLASHES);
        $parts[] = $k . '=' . $v;
    }
    error_log('Folio video-blur: ' . implode(' | ', $parts));
}

/** Whether a blurred video preview can be generated on this server: needs
 *  both ffmpeg, to pull a representative frame, and Imagick, to downscale
 *  and blur it — the same two-step loss pdf_blur_generate relies on. */
function video_blur_available(): bool
{
    $ffmpeg = tool_path('ffmpeg');
    $imagick_ext = extension_loaded('imagick');
    $imagick_class = class_exists('Imagick');
    if ($ffmpeg === null) {
        video_blur_log('availability', 'ffmpeg was not found by Folio', [
            'PATH' => getenv('PATH') ?: '(empty)',
        ]);
    }
    if (!$imagick_ext) {
        video_blur_log('availability', 'PHP Imagick extension is not loaded', [
            'php_version' => PHP_VERSION,
        ]);
    } elseif (!$imagick_class) {
        video_blur_log('availability', 'Imagick extension is loaded but Imagick class is unavailable');
    }
    return $ffmpeg !== null && $imagick_ext && $imagick_class;
}

/**
 * Extract one frame from a restricted video and reduce it to a small,
 * heavily blurred JPEG, cached exactly like pdf_blur_generate's page-one
 * preview and for the same reason: downscaling hard before blurring is a
 * genuine loss of the underlying content, not a filter a sharpening pass
 * could partially undo. Returns false on any failure; callers fall back
 * to the manual placeholder_image or the plain archival texture.
 *
 * $error, when passed, receives a short human-readable reason for a false
 * return — the same detail video_blur_log() already sends to the PHP error
 * log, just also handed back directly so a caller like Diagnostics can show
 * it on the page itself rather than sending an admin to dig through server
 * logs for what video_blur_log() already knows.
 */
function video_blur_generate(string $abs, string $rel, ?string &$error = null): bool
{
    $error = null;
    if (!video_blur_available()) {
        $error = 'ffmpeg or the Imagick extension is unavailable — see the checks above.';
        video_blur_log('generate', 'preview prerequisites are unavailable', [
            'file' => $rel,
        ]);
        return false;
    }
    if (!is_readable($abs)) {
        $error = 'The source video is not readable by PHP (check file ownership/permissions).';
        video_blur_log('generate', 'source video is not readable', [
            'file' => $rel,
            'path' => $abs,
            'perms' => @substr(sprintf('%o', @fileperms($abs)), -4),
        ]);
        return false;
    }
    $cache = video_blur_cache_path($rel);
    $source_mtime = @filemtime($abs);
    if (is_file($cache) && $source_mtime !== false && @filemtime($cache) >= $source_mtime && @filesize($cache) > 0) {
        return true;
    }
    if (is_file($cache)) {
        video_blur_log('cache', 'cached preview exists but is stale or empty', [
            'file' => $rel,
            'cache' => $cache,
            'cache_size' => @filesize($cache),
        ]);
    }

    $frame_error = null;
    $frame = video_rasterise_frame($abs, 400, [], $frame_error);
    if ($frame === null) {
        $error = 'ffmpeg could not extract a frame: ' . ($frame_error ?: 'unknown ffmpeg error') . '.';
        video_blur_log('ffmpeg', 'frame extraction failed', [
            'file' => $rel,
            'error' => $frame_error ?: 'unknown ffmpeg error',
            'ffmpeg' => tool_path('ffmpeg') ?: 'not found',
        ]);
        return false;
    }
    video_blur_log('ffmpeg', 'frame extracted successfully', [
        'file' => $rel,
        'frame' => $frame,
        'frame_size' => @filesize($frame),
    ]);

    try {
        $img = new Imagick();
        image_apply_limits($img);
        $img->readImage($frame);
        video_blur_log('imagick', 'frame read successfully', [
            'file' => $rel,
            'width' => $img->getImageWidth(),
            'height' => $img->getImageHeight(),
            'format' => $img->getImageFormat(),
            'imagick_version' => defined('Imagick::IMAGICK_VERSION') ? Imagick::IMAGICK_VERSION : 'unknown',
        ]);
        $img->setIteratorIndex(0);
        $img->setImageFormat('jpeg');
        $img->flattenImages();
        $w = max(1, (int) $img->getImageWidth());
        $img->scaleImage(max(1, (int) ($w / 8)), 0);
        $img->blurImage(6, 3);
        $img->scaleImage(min(600, $w), 0);
        $img->setImageCompressionQuality(70);
        if (!is_dir(dirname($cache)) && !@mkdir(dirname($cache), 0750, true) && !is_dir(dirname($cache))) {
            $error = 'The preview cache directory (' . dirname($cache) . ') could not be created.';
            video_blur_log('cache', 'could not create preview cache directory', [
                'file' => $rel,
                'directory' => dirname($cache),
                'parent_writable' => @is_writable(dirname(dirname($cache))) ? 'yes' : 'no',
            ]);
            $img->clear();
            return false;
        }
        if (!is_writable(dirname($cache))) {
            $error = 'The preview cache directory (' . dirname($cache) . ') is not writable by PHP.';
            video_blur_log('cache', 'preview cache directory is not writable', [
                'file' => $rel,
                'directory' => dirname($cache),
                'perms' => @substr(sprintf('%o', @fileperms(dirname($cache))), -4),
            ]);
            $img->clear();
            return false;
        }
        $ok = $img->writeImage($cache);
        $write_error = $img->getImageFilename();
        $img->clear();
        if (!$ok || !is_file($cache) || @filesize($cache) <= 0) {
            $error = 'Imagick could not write the blurred JPEG to the cache.';
            video_blur_log('imagick', 'blurred JPEG write failed', [
                'file' => $rel,
                'cache' => $cache,
                'write_return' => $ok ? 'true' : 'false',
                'cache_exists' => is_file($cache) ? 'yes' : 'no',
                'cache_size' => @filesize($cache),
                'filename' => $write_error,
            ]);
            return false;
        }
        video_blur_log('complete', 'blurred video preview generated', [
            'file' => $rel,
            'cache' => $cache,
            'cache_size' => @filesize($cache),
        ]);
        return true;
    } catch (Throwable $e) {
        $error = 'Imagick raised ' . get_class($e) . ': ' . $e->getMessage();
        video_blur_log('imagick', 'exception while generating blurred preview', [
            'file' => $rel,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);
        return false;
    } finally {
        @unlink($frame);
    }
}
