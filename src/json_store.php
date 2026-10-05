<?php
declare(strict_types=1);

/**
 * JSON store helpers for Folio.
 * Provides simple load/save with atomic replace and error handling.
 */
function json_load(string $path, array $default = []): array {
    if (!is_file($path)) {
        return $default;
    }
    $content = @file_get_contents($path);
    if ($content === false) {
        return $default;
    }
    $data = json_decode($content, true);
    return is_array($data) ? $data : $default;
}

function json_save(string $path, array $data, int $mode = 0600, bool $backup = false): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    // Re-use atomic_replace_file from the main script.
    return atomic_replace_file($path, $json, $mode, $backup);
}
?>
