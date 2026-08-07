<?php
/**
 * Folio — a self-hosted document library.
 *
 * Turns a web folder into a public, catalogued, searchable collection.
 * Drop this folder into your web root. Set BASE_DIR below to the folder
 * you want to browse. Defaults to an "uploads" directory beside this script.
 *
 * Copyright (C) 2026 Mohd Elfie Nieshaem Juferi
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version. It is distributed WITHOUT ANY WARRANTY; without even the
 * implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See license.txt, or <https://www.gnu.org/licenses/>.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Settings resolve in this order:
 *   1. data/settings.php   — written by the Settings screen in the admin
 *   2. config.php          — hand-edited file settings
 *   3. the defaults below
 */
define('SETTINGS_FILE', __DIR__ . '/data/settings.php');

/** Write an entire string even when the stream accepts only a partial chunk. */
function write_all_stream($handle, string $contents): bool
{
    $length = strlen($contents);
    $written = 0;
    while ($written < $length) {
        $chunk = @fwrite($handle, substr($contents, $written));
        if (!is_int($chunk) || $chunk < 1) {
            return false;
        }
        $written += $chunk;
    }
    return true;
}

/** Atomically replace a private application file in its existing directory. */
function atomic_replace_file(string $path, string $contents, int $mode = 0600, bool $backup = false): bool
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return false;
    }
    try {
        $tmp = $dir . '/.' . basename($path) . '.' . bin2hex(random_bytes(8)) . '.tmp';
    } catch (Throwable $e) {
        return false;
    }
    $fh = @fopen($tmp, 'xb');
    if ($fh === false) {
        return false;
    }
    $ok = @flock($fh, LOCK_EX)
        && write_all_stream($fh, $contents)
        && @fflush($fh);
    @flock($fh, LOCK_UN);
    @fclose($fh);
    if (!$ok) {
        @unlink($tmp);
        return false;
    }
    @chmod($tmp, $mode);
    if ($backup && is_file($path)) {
        $previous = @file_get_contents($path);
        if (!is_string($previous) || !atomic_replace_file($path . '.bak', $previous, $mode, false)) {
            @unlink($tmp);
            return false;
        }
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    @chmod($path, $mode);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
    return true;
}

/** Merge updates into the saved settings file. Returns success. */
function settings_store(array $updates): bool
{
    $current = [];
    if (is_file(SETTINGS_FILE)) {
        $loaded = @include SETTINGS_FILE;
        if (is_array($loaded)) {
            $current = $loaded;
        }
    }
    $merged = array_merge($current, $updates);
    $out = "<?php\n/* Folio settings. Generated file — edit through the admin. */\nreturn "
         . var_export($merged, true) . ";\n";
    return atomic_replace_file(SETTINGS_FILE, $out);
}

if (is_file(SETTINGS_FILE)) {
    $folio_saved = @include SETTINGS_FILE;
    if (is_array($folio_saved)) {
        foreach ($folio_saved as $folio_key => $folio_value) {
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', (string) $folio_key)) {
                define((string) $folio_key, $folio_value);
            }
        }
    }
    unset($folio_saved, $folio_key, $folio_value);
}

if (is_file(__DIR__ . '/config.php')) {
    @require __DIR__ . '/config.php';
}

defined('UPLOADS_DIRNAME')      || define('UPLOADS_DIRNAME', 'uploads');
defined('ADMIN_USERNAME')       || define('ADMIN_USERNAME', 'admin');
defined('ADMIN_PASSWORD_HASH')  || define('ADMIN_PASSWORD_HASH', 'CHANGE_ME');
defined('SITE_NAME')            || define('SITE_NAME', 'Folio');
define('FOLIO_VERSION', '1.19.0');

defined('SITE_URL')             || define('SITE_URL', '');
defined('SITE_DESCRIPTION')     || define('SITE_DESCRIPTION', 'A reading library of documents, papers, and images.');
defined('PUBLISHER_TYPE')       || define('PUBLISHER_TYPE', 'Person');
defined('PUBLISHER_NAME')       || define('PUBLISHER_NAME', '');
defined('PUBLISHER_URL')        || define('PUBLISHER_URL', '');
defined('SITE_LANGUAGE')        || define('SITE_LANGUAGE', 'en');
/**
 * Clean URLs.
 *
 * When config.php does not pin this, Folio decides for itself: the shipped
 * .htaccess sets FOLIO_REWRITE from inside its <IfModule mod_rewrite.c>
 * block, so the variable is present only when that file is installed AND the
 * module is loaded. Anything else — an older .htaccess kept through an
 * upgrade, or a host without mod_rewrite — falls back to query-string URLs,
 * which always work.
 */
if (!defined('PRETTY_URLS')) {
    define('PRETTY_URLS', !empty($_SERVER['FOLIO_REWRITE'])
        || !empty($_SERVER['REDIRECT_FOLIO_REWRITE']));
}
defined('SHOW_ADMIN_LINK')      || define('SHOW_ADMIN_LINK', true);
/**
 * Whether the admin has confirmed (via the Crawlers screen preflight) that
 * requests to uploads/*.pdf actually reach ?action=raw on this server.
 * Never set this by hand: it is only meaningful after a real, successful
 * probe, and a false value here silently and safely downgrades every
 * "viewer"/"hidden" pdf_access to public rather than pretending to protect
 * anything. See pdf_access_enforced().
 */
defined('PDF_GATE_CONFIRMED')   || define('PDF_GATE_CONFIRMED', false);
defined('SITEMAP_ENABLED')      || define('SITEMAP_ENABLED', true);
defined('LLMS_ENABLED')         || define('LLMS_ENABLED', true);
defined('LLMS_INTRO')           || define('LLMS_INTRO', '');
defined('SITE_INDEXABLE')       || define('SITE_INDEXABLE', true);
defined('INDEXNOW_KEY')         || define('INDEXNOW_KEY', '');

/* Analytics. Both providers are external and off by default; Folio itself
   records no visits, no IP addresses, and no geolocation. */
defined('MATOMO_URL')           || define('MATOMO_URL', '');
defined('MATOMO_SITE_ID')       || define('MATOMO_SITE_ID', '');
defined('MATOMO_HONOR_DNT')     || define('MATOMO_HONOR_DNT', true);
defined('MATOMO_COOKIELESS')    || define('MATOMO_COOKIELESS', false);
defined('GA4_MEASUREMENT_ID')   || define('GA4_MEASUREMENT_ID', '');
defined('GA4_ANONYMIZE_IP')     || define('GA4_ANONYMIZE_IP', true);
defined('ANALYTICS_ADMIN')      || define('ANALYTICS_ADMIN', false);

/* IndexNow rejects requests carrying more than 10,000 URLs. */
define('INDEXNOW_MAX_URLS_PER_REQUEST', 10000);

/* The sitemap protocol allows at most 50,000 URLs per file. */
define('SITEMAP_MAX_URLS', 50000);

/**
 * Files and folders that must not appear in the listing, the sitemap, category
 * archives, or any other public surface. Values are shell-style globs matched
 * against both the entry name and the relative path, for example:
 *   ['_drafts/*', '*.tmp', 'working-notes.md', '_private']
 * Dotfiles like .htaccess and .DS_Store are already skipped and do not need to
 * be listed here.
 */
defined('EXCLUDE_PATTERNS')     || define('EXCLUDE_PATTERNS', []);

/* ---------------------------------------------------------------- */
/* Derivative images                                                 */
/*                                                                   */
/* Folio never touches an uploaded file. Derivatives are written to  */
/* data/thumbs/ and can be deleted at any time; they rebuild on the  */
/* next request. With no image engine installed every setting here   */
/* is inert and the original file is served exactly as before.       */
/* ---------------------------------------------------------------- */

/** Generate cached thumbnails for listings, hover cards, and detail pages. */
defined('THUMBNAILS_ENABLED')   || define('THUMBNAILS_ENABLED', true);

/** Widths Folio will produce. A request for any other width is refused, so a
 *  visitor cannot fill the disk by asking for thousands of sizes. */
defined('THUMB_WIDTHS')         || define('THUMB_WIDTHS', [320, 640, 1280]);

/** JPEG/WebP quality for derivatives. 82 is visually clean and compact. */
defined('THUMB_QUALITY')        || define('THUMB_QUALITY', 82);

/** Refuse to decode images beyond this many pixels. A small file can declare
 *  enormous dimensions and exhaust memory when decoded ("decompression bomb"),
 *  so dimensions are read before any pixels are. */
defined('IMAGE_MAX_PIXELS')     || define('IMAGE_MAX_PIXELS', 80000000);

/** Memory ceiling and wall-clock limit for a single conversion. */
defined('IMAGE_MEMORY_LIMIT')   || define('IMAGE_MEMORY_LIMIT', 256);
defined('IMAGE_TIME_LIMIT')     || define('IMAGE_TIME_LIMIT', 20);

/** Render page one of a PDF server-side. Requires Imagick with a working
 *  PDF delegate. Off by default: that delegate has a poor security
 *  history, and the client-side reader already covers this without it. */
defined('PDF_SERVER_PREVIEW')   || define('PDF_SERVER_PREVIEW', false);

/** Formats browsers cannot display, which are converted to a viewable
 *  derivative when an engine is available. The original is always what the
 *  download and direct links give you. */
defined('CONVERT_FORMATS')      || define('CONVERT_FORMATS', ['tif', 'tiff', 'heic', 'heif', 'avif']);

define('THUMB_DIR', __DIR__ . '/data/thumbs');

/* ---------------------------------------------------------------- */
/* External utilities                                                */
/*                                                                   */
/* Folio works with none of these installed. When a host provides    */
/* them — typically because another application on the same server   */
/* needs them — Folio detects them and uses them for work PHP cannot */
/* do well: OCR, text extraction, and safer PDF rasterising.         */
/*                                                                   */
/* Nothing here is ever required. Every feature that depends on a    */
/* utility checks for it first and falls back to previous behaviour. */
/* ---------------------------------------------------------------- */

/** Look for utilities in these directories only. $PATH is not consulted:
 *  a fixed list cannot be redirected by a modified environment. */
defined('TOOL_SEARCH_PATHS') || define('TOOL_SEARCH_PATHS', [
    '/usr/local/bin', '/usr/bin', '/bin', '/opt/bin',
    '/usr/local/sbin', '/usr/sbin', '/opt/cpanel/composer/bin',
]);

/** Override any detected path explicitly, for hosts that keep tools
 *  elsewhere: ['ocrmypdf' => '/home/d2076/.local/bin/ocrmypdf'] */
defined('TOOL_PATHS')       || define('TOOL_PATHS', []);

/** Master switch. false disables every external utility regardless of
 *  what is installed. */
defined('TOOLS_ENABLED')    || define('TOOLS_ENABLED', true);

/** Seconds any single utility may run before it is killed. OCR is slow,
 *  so it has its own, larger budget. */
defined('TOOL_TIMEOUT')     || define('TOOL_TIMEOUT', 20);
defined('OCR_TIMEOUT')      || define('OCR_TIMEOUT', 600);

/** Languages passed to OCR, most likely first. Only those actually
 *  installed are used; the rest are dropped. */
defined('OCR_LANGUAGES')    || define('OCR_LANGUAGES', ['eng', 'msa', 'ara']);

/**
 * Allow ImageMagick to render PDFs directly.
 *
 * Off by default and deliberately so. Its PDF delegate has a long history of
 * serious vulnerabilities, many hosts disable it in ImageMagick's policy.xml,
 * and plenty of servers do not install it at all. Folio does not need it:
 * Poppler renders PDF pages directly, and OCR runs without it.
 *
 * Leave this false unless you have a specific reason and trust every
 * document in the library.
 */
defined('PDF_ALLOW_GHOSTSCRIPT') || define('PDF_ALLOW_GHOSTSCRIPT', false);

/**
 * Your own site icon.
 *
 * Leave empty and Folio looks for one in branding/ before falling back to the
 * shipped default. branding/ is deliberately not part of the release, so an
 * upgrade cannot overwrite what you put there — replacing the file inside
 * assets/ would be undone by the next update.
 */
defined('SITE_ICON')        || define('SITE_ICON', '');

/** Where OCR results and extracted text are cached. Never inside uploads/. */
define('TEXT_DIR', __DIR__ . '/data/text');
define('OCR_DIR',  __DIR__ . '/data/ocr');
define('COMPRESS_DIR', __DIR__ . '/data/compressed');

/**
 * Site secrets. Set these in config.php. They are optional; without them,
 * Folio still works but you lose two protections:
 *   1. Password hash peppering: if data/users.php leaks in isolation, the
 *      hashes remain uncrackable without the pepper.
 *   2. Session cookie namespacing: prevents session collisions with other PHP
 *      applications on the same domain.
 *
 * Generate values locally with random_bytes() and store them in config.php. NEVER rotate FOLIO_AUTH_PEPPER once accounts exist without a
 * password reset for every account: it invalidates every existing hash.
 */
defined('FOLIO_AUTH_PEPPER')    || define('FOLIO_AUTH_PEPPER', '');
defined('FOLIO_COOKIE_NAME')    || define('FOLIO_COOKIE_NAME', 'FOLIOSESSID');
defined('TRUST_PROXY_HEADERS')  || define('TRUST_PROXY_HEADERS', false);

/**
 * Signs the short-lived URLs used to display "viewer" pdf_access PDFs.
 * Deliberately a separate secret from FOLIO_AUTH_PEPPER: one signs URLs, the
 * other peppers password hashes, and rotating one must never affect the
 * other. Left empty, "viewer" and "hidden" pdf_access are not enforced —
 * see pdf_access_enforceable() — rather than signing with a blank, guessable
 * key that would only look like protection.
 */
defined('FOLIO_URL_SIGNING_KEY') || define('FOLIO_URL_SIGNING_KEY', '');

define('BASE_DIR', realpath(__DIR__ . '/' . UPLOADS_DIRNAME) ?: __DIR__ . '/' . UPLOADS_DIRNAME);

ini_set('display_errors', '0');

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (TRUST_PROXY_HEADERS) {
        $proto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        return $proto === 'https';
    }
    return false;
}

function ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(FOLIO_COOKIE_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (empty($_SESSION['sfm_csrf'])) {
        $_SESSION['sfm_csrf'] = bin2hex(random_bytes(32));
    }
}

function request_needs_session(): bool
{
    if (isset($_COOKIE[FOLIO_COOKIE_NAME])) {
        return true;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        return true;
    }
    $action = (string) ($_GET['action'] ?? '');
    return in_array($action, ['login', 'logout', 'settings', 'crawlers', 'users', 'docs', 'diagnostics', 'pages', 'analytics'], true);
}

if (request_needs_session()) {
    ensure_session_started();
}

function csrf_token(): string
{
    ensure_session_started();
    return (string) $_SESSION['sfm_csrf'];
}

function csrf_valid(): bool
{
    ensure_session_started();
    return hash_equals((string) ($_SESSION['sfm_csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''));
}

/**
 * Stateless CSRF token for the header login form.
 *
 * The login form is rendered on every anonymous page view. Using the
 * session-backed token would start a session for every visitor and defeat
 * public caching, so this token is signed rather than stored: it carries its
 * own timestamp and an HMAC, and is verified without touching the session.
 *
 * The signing key never leaves the server. FOLIO_AUTH_PEPPER is used when
 * configured; otherwise the stored password hash serves, since it is secret,
 * always present, and changes whenever credentials do.
 */
function login_token_key(): string
{
    $key = (string) FOLIO_AUTH_PEPPER;
    if ($key === '') {
        $users = users_load();
        foreach ($users as $u) {
            $key .= (string) ($u['hash'] ?? '');
        }
        $key .= (string) ADMIN_PASSWORD_HASH;
    }
    return $key !== '' ? $key : 'folio-unconfigured';
}

function login_token(): string
{
    $ts = (string) time();
    return $ts . '.' . hash_hmac('sha256', $ts, login_token_key());
}

function login_token_valid(string $token, int $max_age = 7200): bool
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0])) {
        return false;
    }
    $ts = (int) $parts[0];
    if ($ts > time() + 300 || time() - $ts > $max_age) {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $parts[0], login_token_key()), $parts[1]);
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    /* Analytics providers are third-party origins, so the policy is widened
       to exactly the hosts a configured provider needs and nothing else.
       With no analytics configured these are empty and the policy is
       byte-identical to a build without the feature. */
    $extra      = analytics_csp_sources();
    $script_src = "'self'" . ($extra['script'] !== '' ? ' ' . $extra['script'] : '');
    $img_src    = "'self' data:" . ($extra['img'] !== '' ? ' ' . $extra['img'] : '');
    $connect    = "'self'" . ($extra['connect'] !== '' ? ' ' . $extra['connect'] : '');

    /* The inlined stylesheet is allowed by the hash of its exact bytes, so no
       other inline style can run. 'unsafe-inline' would allow every one, and a
       nonce would change per request and make the page uncacheable. */
    $style_src = "'self'" . inline_css_hash();

    header("Content-Security-Policy: default-src 'self'; img-src $img_src; style-src $style_src; script-src $script_src; connect-src $connect; frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none'");
}

/**
 * Analytics runs for anonymous visitors only unless ANALYTICS_ADMIN is on,
 * so your own browsing does not distort the reports.
 */
function analytics_active(): bool
{
    if (!ANALYTICS_ADMIN && is_admin()) {
        return false;
    }
    return (MATOMO_URL !== '' && MATOMO_SITE_ID !== '') || GA4_MEASUREMENT_ID !== '';
}

/** The extra CSP origins the configured providers require. */
function analytics_csp_sources(): array
{
    $out = ['script' => '', 'img' => '', 'connect' => ''];
    if (!analytics_active()) {
        return $out;
    }
    $script = $img = $connect = [];

    if (MATOMO_URL !== '' && MATOMO_SITE_ID !== '') {
        $scheme = (string) parse_url(MATOMO_URL, PHP_URL_SCHEME);
        $host   = (string) parse_url(MATOMO_URL, PHP_URL_HOST);
        $port   = parse_url(MATOMO_URL, PHP_URL_PORT);
        if ($scheme !== '' && $host !== '') {
            // A CSP source without the port only matches the default port for
            // the scheme, so a self-hosted Matomo on, say, :8080 would have
            // its script refused. Carry the port through when one is given.
            $origin = $scheme . '://' . $host . ($port ? ':' . (int) $port : '');
            $script[] = $origin;
            $img[] = $origin;
            $connect[] = $origin;
        }
    }
    if (GA4_MEASUREMENT_ID !== '') {
        $script[]  = 'https://www.googletagmanager.com';
        $img[]     = 'https://www.google-analytics.com';
        $img[]     = 'https://*.google-analytics.com';
        $img[]     = 'https://*.analytics.google.com';
        $connect[] = 'https://www.google-analytics.com';
        $connect[] = 'https://*.google-analytics.com';
        $connect[] = 'https://*.analytics.google.com';
    }
    /* The trackers need a small inline bootstrap, and 'unsafe-inline' would
       switch off inline-script protection for the whole site to allow it.
       A sha256 hash of each exact block allows precisely those blocks and
       nothing else, so the policy stays as strict as it was before analytics
       existed. The hashes are computed from the same strings that are
       emitted, so they cannot fall out of step. */
    foreach (analytics_inline_blocks() as $block) {
        $script[] = "'sha256-" . base64_encode(hash('sha256', $block, true)) . "'";
    }

    $out['script']  = implode(' ', array_unique($script));
    $out['img']     = implode(' ', array_unique($img));
    $out['connect'] = implode(' ', array_unique($connect));
    return $out;
}

/**
 * Tracker tags for the public footer. Values come from constants validated on
 * save, and the GA4 identifier is filtered again here before it reaches the
 * page.
 */
/**
 * The inline script bodies the configured providers need, without their
 * <script> wrappers.
 *
 * This is the single source of truth for that code: analytics_scripts()
 * wraps these for output and analytics_csp_sources() hashes these same
 * strings for the policy. Deriving both from one place is what keeps the
 * sha256 hashes from drifting away from what actually executes — a drift
 * that would silently block the tracker.
 */
function analytics_inline_blocks(): array
{
    if (!analytics_active()) {
        return [];
    }
    $blocks = [];

    if (MATOMO_URL !== '' && MATOMO_SITE_ID !== '') {
        $base = json_encode(rtrim(MATOMO_URL, '/') . '/');
        $site = (int) MATOMO_SITE_ID;
        $dnt  = MATOMO_HONOR_DNT ? "_paq.push(['setDoNotTrack', true]);\n" : '';
        $ck   = MATOMO_COOKIELESS ? "_paq.push(['disableCookies']);\n" : '';
        $blocks[] = "\n"
              . "var _paq = window._paq = window._paq || [];\n"
              . $dnt . $ck
              . "_paq.push(['trackPageView']);\n"
              . "_paq.push(['enableLinkTracking']);\n"
              . "(function () {\n"
              . "  var u = " . $base . ";\n"
              . "  _paq.push(['setTrackerUrl', u + 'matomo.php']);\n"
              . "  _paq.push(['setSiteId', '" . $site . "']);\n"
              . "  var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];\n"
              . "  g.async = true; g.src = u + 'matomo.js'; s.parentNode.insertBefore(g, s);\n"
              . "})();\n";
    }

    if (GA4_MEASUREMENT_ID !== '') {
        $id   = (string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) GA4_MEASUREMENT_ID);
        $anon = GA4_ANONYMIZE_IP ? ", { 'anonymize_ip': true }" : '';
        $blocks[] = "\n"
              . "window.dataLayer = window.dataLayer || [];\n"
              . "function gtag(){dataLayer.push(arguments);}\n"
              . "gtag('js', new Date());\n"
              . "gtag('config', '" . $id . "'" . $anon . ");\n";
    }

    return $blocks;
}

/**
 * Tracker tags for the public footer. Values come from constants validated on
 * save, and the GA4 identifier is filtered again here before it reaches the
 * page.
 */
function analytics_scripts(): string
{
    if (!analytics_active()) {
        return '';
    }
    $out = '';

    if (GA4_MEASUREMENT_ID !== '') {
        $id = (string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) GA4_MEASUREMENT_ID);
        $out .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . e($id) . "\"></script>\n";
    }

    foreach (analytics_inline_blocks() as $block) {
        $out .= '<script>' . $block . "</script>\n";
    }

    return $out;
}

function send_public_cache_headers(int $seconds = 300): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        header('Cache-Control: public, max-age=' . $seconds);
    }
}

function clear_auth_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    unset($_SESSION['sfm_admin'], $_SESSION['sfm_user'], $_SESSION['sfm_auth_version']);
}

function is_admin(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['sfm_admin'])) {
        return false;
    }
    $username = (string) ($_SESSION['sfm_user'] ?? '');
    $users = users_load();
    if ($username === '' || !isset($users[$username])) {
        clear_auth_session();
        return false;
    }
    $stored_version = max(1, (int) ($users[$username]['auth_version'] ?? 1));
    if ((int) ($_SESSION['sfm_auth_version'] ?? 0) !== $stored_version) {
        clear_auth_session();
        return false;
    }
    return true;
}

/* ------------------------------------------------------------------ */
/* User accounts                                                       */
/* ------------------------------------------------------------------ */

define('USERS_FILE', __DIR__ . '/data/users.php');

/**
 * All accounts, as username => ['hash' => ..., 'created' => ...].
 * Falls back to the single account in config.php until accounts are added,
 * so an installation works before anyone visits the users screen.
 */
function users_load(): array
{
    $users = [];
    if (is_file(USERS_FILE)) {
        $data = @include USERS_FILE;
        if (is_array($data) && $data !== []) {
            $users = $data;
        }
    }
    if ($users === [] && ADMIN_PASSWORD_HASH !== 'CHANGE_ME') {
        $users = [ADMIN_USERNAME => [
            'hash' => ADMIN_PASSWORD_HASH,
            'created' => 0,
            'auth_version' => 1,
        ]];
    }
    foreach ($users as $name => $record) {
        if (!is_array($record) || empty($record['hash'])) {
            unset($users[$name]);
            continue;
        }
        $users[$name]['auth_version'] = max(1, (int) ($record['auth_version'] ?? 1));
    }
    return $users;
}

function users_save(array $users): bool
{
    $out = "<?php
/* Folio accounts. Generated file — edit through the admin. */
return "
         . var_export($users, true) . ";
";
    return atomic_replace_file(USERS_FILE, $out);
}

/** True when accounts are managed in the store rather than config.php. */
function users_in_store(): bool
{
    return is_file(USERS_FILE);
}

/**
 * Apply the pepper via HMAC. This is length-safe (bcrypt has a 72-byte input
 * limit, and long passwords with a raw prefix could silently truncate).
 * With no pepper configured, this returns the password unchanged, so existing
 * hashes keep working.
 */
function pepper(string $password): string
{
    if (FOLIO_AUTH_PEPPER === '') {
        return $password;
    }
    return hash_hmac('sha256', $password, FOLIO_AUTH_PEPPER);
}

function user_verify(string $username, string $password): bool
{
    $users = users_load();
    if (!isset($users[$username])) {
        // Compare anyway so a missing user costs the same time as a wrong one.
        password_verify($password, '$2y$10$usesomesillystringfoeidfsaywcoiswiZajHXPWLsA9zJRabHOo9UDy');
        return false;
    }
    $stored = (string) $users[$username]['hash'];

    // Try the current (peppered) form first.
    if (password_verify(pepper($password), $stored)) {
        // If the hash was made without pepper and pepper is now set, upgrade it.
        if (FOLIO_AUTH_PEPPER !== '' && empty($users[$username]['peppered'])) {
            $users[$username]['hash'] = password_hash(pepper($password), PASSWORD_DEFAULT);
            $users[$username]['peppered'] = true;
            @users_save($users);
        }
        return true;
    }

    // Fall back to the unpeppered form for pre-existing hashes, then migrate.
    if (FOLIO_AUTH_PEPPER !== '' && password_verify($password, $stored)) {
        $users[$username]['hash'] = password_hash(pepper($password), PASSWORD_DEFAULT);
        $users[$username]['peppered'] = true;
        @users_save($users);
        return true;
    }
    return false;
}

function valid_username(string $u): bool
{
    return (bool) preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $u);
}

/** Canonical public URL. It must be configured; request Host headers are never trusted. */
$site_url = trim((string) SITE_URL);
if ($site_url === '' || !filter_var($site_url, FILTER_VALIDATE_URL)
    || !in_array(strtolower((string) parse_url($site_url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
    $script_path = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/';

    // No canonical URL configured. Derive one from the request so the site
    // works out of the box, but accept only a plausible hostname: anything
    // else is a malformed or hostile Host header and falls through.
    $req_host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (TRUST_PROXY_HEADERS && !empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $req_host = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
    }
    if (preg_match('/^[A-Za-z0-9._-]+(:[0-9]{1,5})?$/', $req_host)) {
        $site_url = (request_is_https() ? 'https://' : 'http://') . $req_host . $script_path;
    } else {
        $site_url = 'http://localhost' . $script_path;
    }
}
define('BASE_URL', rtrim($site_url, '/') . '/');

/** Base slug for a file, without its extension. Canonical whenever it's unique
 *  within the directory. */
function file_slug(string $name): string
{
    $slug = slugify(pathinfo($name, PATHINFO_FILENAME));
    return $slug !== '' ? $slug : 'file';
}

/** Extension-qualified slug. Used only to disambiguate two files that share a
 *  base slug (e.g. report.pdf and report.docx), and to recognise old links
 *  that carried the extension so they can be redirected to the clean slug. */
function file_slug_with_ext(string $name): string
{
    $ext = slugify(strtolower(pathinfo($name, PATHINFO_EXTENSION)));
    $ext = $ext !== '' ? $ext : 'bin';
    return file_slug($name) . '-' . $ext;
}

/** Collision-safe slug map for one physical directory. */
function directory_slug_map(string $dir_rel): array
{
    static $cache = [];
    if (isset($cache[$dir_rel])) {
        return $cache[$dir_rel];
    }
    $dir = resolve_path($dir_rel);
    if ($dir === null || !is_dir($dir)) {
        return $cache[$dir_rel] = [];
    }
    $groups = [];
    foreach ((array) scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '' || $entry[0] === '.') {
            continue;
        }
        $real = safe_entry_realpath($dir . DIRECTORY_SEPARATOR . $entry);
        if ($real === null || !is_file($real)) {
            continue;
        }
        $groups[file_slug($entry)][] = $entry;
    }
    $map = [];
    foreach ($groups as $base => $entries) {
        foreach ($entries as $entry) {
            // Unique base slug in this folder: use it bare, e.g. "report".
            // Shared base slug (different file types with the same name, or a
            // slugify collision): disambiguate with the extension and a hash.
            $map[$entry] = count($entries) === 1
                ? $base
                : file_slug_with_ext($entry) . '-' . substr(hash('sha256', $entry), 0, 8);
        }
    }
    return $cache[$dir_rel] = $map;
}

function slug_path(string $rel): string
{
    $dir = dirname($rel);
    $dir = ($dir === '.' || $dir === '') ? '' : str_replace('\\', '/', $dir);
    $map = directory_slug_map($dir);
    $name = basename($rel);
    $slug = $map[$name] ?? file_slug_with_ext($name) . '-' . substr(hash('sha256', $name), 0, 8);
    return $dir === '' ? $slug : $dir . '/' . $slug;
}

/**
 * The public URL for a document.
 *
 * Prefers the saved canonical slug, which is permanent and independent of the
 * file's name and folder. Every link, canonical tag, sitemap entry, and
 * structured-data identifier goes through here, so routing them all to the
 * saved slug is a single change rather than six call sites that could drift
 * apart.
 *
 * The path-derived slug remains only as a fallback for a file that has no
 * document record yet — a file just dropped in over FTP and not yet indexed.
 */
function url_view(string $rel): string
{
    $rec = document_for_path($rel);
    if ($rec !== null && (string) ($rec['slug'] ?? '') !== '') {
        return document_url($rec);
    }
    $path = slug_path($rel);
    return PRETTY_URLS
        ? rtrim(BASE_URL, '/') . '/' . str_replace('%2F', '/', rawurlencode($path)) . '/'
        : BASE_URL . '?view=' . rawurlencode($path);
}

/**
 * Resolve a requested view path back to a real file.
 * Returns [absolute path, relative path, is_legacy] or null.
 */
function resolve_view(string $req): ?array
{
    $req = trim(str_replace('\\', '/', $req), '/');
    if ($req === '') {
        return null;
    }

    $direct = resolve_path($req);
    if ($direct !== null && is_file($direct)) {
        $rel = relative_from_base($direct);
        if (is_excluded(basename($rel), $rel)) {
            return null;
        }
        return [$direct, $rel, true];
    }

    $dir_rel = dirname($req) === '.' ? '' : dirname($req);
    $slug = basename($req);
    $abs_dir = resolve_path($dir_rel);
    if ($abs_dir === null || !is_dir($abs_dir)) {
        return null;
    }
    $map = directory_slug_map($dir_rel);
    $legacy_matches = [];
    foreach ($map as $entry => $entry_slug) {
        $abs_e = safe_entry_realpath($abs_dir . DIRECTORY_SEPARATOR . $entry);
        if ($abs_e === null || !is_file($abs_e)) {
            continue;
        }
        $rel = ltrim($dir_rel . '/' . $entry, '/');
        if (is_excluded($entry, $rel)) {
            continue;
        }
        if ($entry_slug === $slug) {
            return [$abs_e, $rel, false];
        }
        // Old link carrying the file extension (the previous canonical form):
        // redirect forward to whatever this file's clean slug is now.
        if (file_slug_with_ext($entry) === $slug) {
            $legacy_matches[] = [$abs_e, $rel, true];
        }
    }
    return count($legacy_matches) === 1 ? $legacy_matches[0] : null;
}

function url_raw(string $rel): string
{
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    $direct_safe = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'txt', 'md',
                    'tif', 'tiff', 'heic', 'heif', 'avif'];
    if (!in_array($ext, $direct_safe, true)) {
        return BASE_URL . '?action=raw&serve=1&file=' . rawurlencode($rel);
    }
    return rtrim(BASE_URL, '/') . '/' . rawurlencode(UPLOADS_DIRNAME) . '/'
        . str_replace('%2F', '/', rawurlencode($rel));
}

/**
 * Reserved dotfile used only to test whether the PDF access-control rewrite
 * is actually reaching PHP for a real file (not just a nonexistent path).
 * Dotfiles are already skipped in every directory listing and the sitemap,
 * so this never appears anywhere public. See pdf_gate_ensure_probe_file()
 * and the ?action=raw admin-only probe branch.
 *
 * It must be a REAL file, not a made-up name: index.php already has a
 * pre-existing fallback that maps any nonexistent uploads/... path to
 * ?action=raw when the generic "not a real file or directory" rewrite rule
 * fires and PRETTY_URLS is on. That fallback exists for a different reason
 * (servers whose rewrite doesn't short-circuit real files) but would make a
 * nonexistent probe path succeed even when the specific PDF-gate rule below
 * is completely absent — a false positive. A real file sidesteps this: that
 * fallback only ever triggers for paths that do NOT exist on disk.
 */
define('FOLIO_PDF_PROBE_NAME', '.folio-pdf-probe.pdf');

/** Absolute path to the PDF-gate preflight probe file. */
function pdf_gate_probe_path(): string
{
    return rtrim(BASE_DIR, '/\\') . DIRECTORY_SEPARATOR . FOLIO_PDF_PROBE_NAME;
}

/** Create the tiny real probe file if it doesn't already exist. */
function pdf_gate_ensure_probe_file(): bool
{
    $path = pdf_gate_probe_path();
    if (is_file($path)) {
        return true;
    }
    if (!is_dir(BASE_DIR) && !@mkdir(BASE_DIR, 0750, true)) {
        return false;
    }
    return @file_put_contents(
        $path,
        "%PDF-1.4\n% Folio PDF-gate preflight probe file. Safe to delete; Folio recreates it as needed.\n"
    ) !== false;
}

/** Normalise a stored pdf_access value, defaulting unknown/missing to public. */
function pdf_access_of(array $m): string
{
    $v = (string) ($m['pdf_access'] ?? 'public');
    return in_array($v, ['public', 'viewer', 'hidden'], true) ? $v : 'public';
}

/**
 * Whether "viewer"/"hidden" pdf_access can actually be enforced right now.
 *
 * Two independent things must both be true, or every PDF behaves as public
 * regardless of its stored pdf_access — a missing precondition must never
 * look like a working restriction:
 *   1. A signing key is configured, so signed URLs are not forgeable.
 *   2. The admin has run and confirmed the PDF-routing preflight (Crawlers
 *      screen), proving requests to uploads/*.pdf actually reach ?action=raw
 *      on this server rather than being served directly by the webserver.
 */
function pdf_access_enforced(): bool
{
    return FOLIO_URL_SIGNING_KEY !== '' && !empty(PDF_GATE_CONFIRMED);
}

/** HMAC for a short-lived signed PDF URL. Never exposed except inside the token itself. */
function pdf_sign(string $rel, int $expires): string
{
    return hash_hmac('sha256', $rel . '|' . $expires, FOLIO_URL_SIGNING_KEY);
}

/** A signed, time-limited URL through ?action=raw for a "viewer" PDF. */
function pdf_signed_url(string $rel, int $ttl = 900): string
{
    $expires = time() + $ttl;
    $token = pdf_sign($rel, $expires);
    return BASE_URL . '?action=raw&serve=1&file=' . rawurlencode($rel)
        . '&expires=' . $expires . '&token=' . $token;
}

/** Verify a signed PDF URL's expiry and signature. Timing-safe. */
function pdf_signed_url_valid(string $rel, int $expires, string $token): bool
{
    if ($expires < time()) {
        return false;
    }
    return hash_equals(pdf_sign($rel, $expires), $token);
}

/**
 * The URL to use for a file's actual bytes, aware of pdf_access.
 *
 * Returns '' for a PDF whose access is "hidden" and is currently enforced —
 * callers must treat an empty string as "do not render any link or embed
 * for this file," not fall back to a raw filename guess.
 *
 * Every other case, including any PDF when enforcement is not confirmed,
 * behaves exactly as url_raw() always has.
 */
function url_raw_effective(string $rel, array $m): string
{
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if ($ext !== 'pdf' || !pdf_access_enforced()) {
        return url_raw($rel);
    }
    $access = pdf_access_of($m);
    if ($access === 'hidden') {
        return '';
    }
    if ($access === 'viewer') {
        return pdf_signed_url($rel);
    }
    return url_raw($rel);
}

/**
 * Whether the full set of "public" affordances — direct link, flip-view
 * download, print — should be offered for this file. False for any PDF
 * currently restricted to "viewer" or "hidden"; true for everything else,
 * including PDFs when enforcement isn't confirmed (see pdf_access_enforced).
 */
function pdf_full_access(string $rel, array $m): bool
{
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if ($ext !== 'pdf' || !pdf_access_enforced()) {
        return true;
    }
    return pdf_access_of($m) === 'public';
}

/** The sitemap listing the PDF files themselves. */
function url_sitemap_pdf(): string
{
    return PRETTY_URLS
        ? rtrim(BASE_URL, '/') . '/sitemap-pdf.xml'
        : BASE_URL . '?action=sitemap_pdf';
}

/** URL of one part of a partitioned sitemap. */
function url_sitemap_part(int $n): string
{
    return PRETTY_URLS
        ? rtrim(BASE_URL, '/') . '/sitemap-' . $n . '.xml'
        : BASE_URL . '?action=sitemap&part=' . $n;
}

/** The flip-view reader URL for a PDF. Query-string only; not part of the pretty-URL map. */
function url_flipbook(string $rel): string
{
    return BASE_URL . '?action=flipbook&file=' . rawurlencode($rel);
}

function url_render(string $rel): string
{
    return BASE_URL . '?action=render&file=' . rawurlencode($rel);
}

function url_dir(string $rel): string
{
    if ($rel === '') {
        return BASE_URL;
    }
    return PRETTY_URLS
        ? rtrim(BASE_URL, '/') . '/' . str_replace('%2F', '/', rawurlencode($rel)) . '/'
        : BASE_URL . '?dir=' . rawurlencode($rel);
}

/** URL-safe slug. */
function slugify(string $s): string
{
    $original = trim($s);
    $s = strtolower($original);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($t !== false) {
            $s = $t;
        }
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string) $s, '-');
    return $s !== '' ? $s : 'item-' . substr(hash('sha256', $original), 0, 8);
}

/** The pre-1.16.1 form: always suffixed, whether or not anything clashed. */
function category_slug_legacy(string $cat): string
{
    return slugify($cat) . '-' . substr(hash('sha256', $cat), 0, 8);
}

/**
 * Slug for every category in the library, disambiguated only where needed.
 *
 * Two category names can slugify to the same string — "Q&A" and "Q A", or
 * "Café" and "Cafe" — and both would then claim one URL. Earlier releases
 * avoided that by suffixing a hash of the name to every category, so a
 * library that had no clash at all still carried "tracts-2a4f72ad" in its
 * addresses. The suffix is now added only to the names that actually collide,
 * and only to them: an unambiguous category is simply "tracts".
 */
function category_slug_map(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }
    global $mime_map;
    $names = array_keys(category_register(index_all_files($mime_map ?? [])));

    $by_plain = [];
    foreach ($names as $name) {
        $by_plain[slugify((string) $name)][] = (string) $name;
    }

    $map = [];
    foreach ($by_plain as $plain => $group) {
        if (count($group) === 1) {
            $map[$group[0]] = $plain;
            continue;
        }
        // A genuine clash: every name in this group keeps a suffix, so no one
        // of them silently wins the plain address.
        foreach ($group as $name) {
            $map[$name] = category_slug_legacy($name);
        }
    }
    return $map;
}

function category_slug(string $cat): string
{
    $map = category_slug_map();
    if (isset($map[$cat])) {
        return $map[$cat];
    }
    // Not in the register: nothing to collide with, so the plain form is safe.
    return slugify($cat);
}

function url_category(string $cat): string
{
    $slug = category_slug($cat);
    return PRETTY_URLS
        ? rtrim(BASE_URL, '/') . '/category/' . rawurlencode($slug) . '/'
        : BASE_URL . '?cat=' . rawurlencode($slug);
}

/* ------------------------------------------------------------------ */
/* Root icon requests                                                   */
/*                                                                      */
/* Browsers ask for /favicon.ico and /apple-touch-icon.png directly,    */
/* whatever the <link> tags say — bookmarks, history and tab restore    */
/* all use the root path. With the catch-all rewrite in place those     */
/* requests reach index.php and would otherwise 404, so a correctly     */
/* configured icon still appeared broken. Serve the real one instead.   */
/* ------------------------------------------------------------------ */
if (isset($_SERVER['REQUEST_URI'])) {
    $icon_path = strtolower(basename((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH)));
    $icon_map = [
        'favicon.ico'          => ['branding/favicon.ico', 'assets/img/favicon.ico'],
        'favicon.svg'          => ['branding/favicon.svg', 'assets/img/favicon.svg'],
        'favicon.png'          => ['branding/favicon.png'],
        'apple-touch-icon.png' => ['branding/apple-touch-icon.png', 'assets/img/apple-touch-icon.png'],
        'apple-touch-icon-precomposed.png' => ['branding/apple-touch-icon.png', 'assets/img/apple-touch-icon.png'],
    ];
    // Runs before URL mapping, so the catch-all rewrite has not yet turned
    // this path into a document lookup. Only a bare request qualifies.
    if (isset($icon_map[$icon_path]) && $_SERVER['REQUEST_METHOD'] === 'GET'
        && ($_SERVER['QUERY_STRING'] ?? '') === '') {
        $served = null;
        foreach ($icon_map[$icon_path] as $candidate) {
            if (is_file(__DIR__ . '/' . $candidate)) {
                $served = __DIR__ . '/' . $candidate;
                break;
            }
        }
        // A configured SITE_ICON wins for the .ico request only when it is
        // itself an icon; a PNG or SVG is not a valid .ico reply.
        if ($served !== null) {
            $ext  = strtolower(pathinfo($served, PATHINFO_EXTENSION));
            $type = ['ico' => 'image/x-icon', 'svg' => 'image/svg+xml', 'png' => 'image/png'][$ext] ?? 'application/octet-stream';
            header('Content-Type: ' . $type);
            header('Content-Length: ' . (string) filesize($served));
            header('Cache-Control: public, max-age=604800');
            header('X-Content-Type-Options: nosniff');
            readfile($served);
            exit;
        }
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Not found');
    }
}

/* Map pretty URLs back onto query parameters. */
if (PRETTY_URLS) {
    $route = (string) ($_SERVER['SFM_ROUTE'] ?? $_SERVER['REDIRECT_SFM_ROUTE'] ?? '');
    if ($route === '') {
        // Some servers do not pass the rewrite environment variable through.
        // Derive the route from the request path relative to this folder.
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $dir  = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        if ($dir !== '' && strpos($path, $dir) === 0) {
            $path = substr($path, strlen($dir));
        }
        $path = ltrim(rawurldecode($path), '/');
        if ($path !== '' && $path !== 'index.php') {
            $route = $path;
        }
    }
    $route = trim($route, '/');
    if (preg_match('#^([a-f0-9]{8,128})\.txt$#i', $route, $m)) {
        $_GET['indexnow_key'] = $m[1];
    } elseif ($route === 'llms.txt') {
        $_GET['action'] = 'llms';
    } elseif ($route === 'sitemap.xml') {
        $_GET['action'] = 'sitemap';
    } elseif ($route === 'sitemap-pdf.xml') {
        $_GET['action'] = 'sitemap_pdf';
    } elseif (preg_match('#^sitemap-([0-9]+)\.xml$#', $route, $m)) {
        $_GET['action'] = 'sitemap';
        $_GET['part'] = $m[1];
    } elseif (preg_match('#^raw/(.+)$#', $route, $m)) {
        $_GET['action'] = 'raw'; // legacy path, redirected below
        $_GET['file'] = rawurldecode($m[1]);
    } elseif (preg_match('#^' . preg_quote(UPLOADS_DIRNAME, '#') . '/(.+)$#', $route, $m)) {
        // A real file would normally be served before reaching us; if the
        // rewrite sent it here anyway, stream it rather than redirect, since
        // redirecting to the same path would loop.
        $_GET['action'] = 'raw';
        $_GET['serve'] = '1';
        $_GET['file'] = rawurldecode($m[1]);
    } elseif (preg_match('#^category/([^/]+)/?$#', $route, $m)) {
        $_GET['cat'] = rawurldecode($m[1]);
    } elseif ($route === 'about' || $route === 'faq') {
        $_GET['page'] = $route;
    } elseif (preg_match('#^p/([a-z0-9-]+)/?$#', $route, $m)) {
        // The old prefixed form. Still routed so existing links survive; the
        // handler redirects it to the page's current address.
        $_GET['page'] = rawurldecode($m[1]);
    } elseif ($route !== '') {
        // A bare slug could be a page or a document. Which one cannot be
        // decided here: URL mapping runs before the pages file's location is
        // even defined. Record the candidate and let the view handler ask.
        $_GET['view'] = rawurldecode($route);
        $GLOBALS['folio_route_candidate'] = rawurldecode($route);
    }
}

if (!is_dir(BASE_DIR)) {
    mkdir(BASE_DIR, 0755, true);
}

/** Resolve a path only when every component is real, inside BASE_DIR, and not a symlink. */
function resolve_path(string $rel): ?string
{
    $base = realpath(BASE_DIR);
    if ($base === false) {
        return null;
    }
    $rel = trim(str_replace('\\', '/', $rel), '/');
    if ($rel === '') {
        return $base;
    }
    $current = $base;
    foreach (explode('/', $rel) as $part) {
        if ($part === '' || $part === '.' || $part === '..') {
            return null;
        }
        $current .= DIRECTORY_SEPARATOR . $part;
        if (is_link($current)) {
            return null;
        }
    }
    $abs = realpath($current);
    if ($abs === false || ($abs !== $base && strpos($abs, $base . DIRECTORY_SEPARATOR) !== 0)) {
        return null;
    }
    return $abs;
}

function safe_entry_realpath(string $path): ?string
{
    if (is_link($path)) {
        return null;
    }
    $real = realpath($path);
    $base = realpath(BASE_DIR);
    if ($real === false || $base === false
        || ($real !== $base && strpos($real, $base . DIRECTORY_SEPARATOR) !== 0)) {
        return null;
    }
    return $real;
}

function relative_from_base(string $abs): string
{
    $base = (string) realpath(BASE_DIR);
    return str_replace(DIRECTORY_SEPARATOR, '/', trim(substr($abs, strlen($base)), '/\\'));
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Clip a string to a maximum length, using mbstring when available. */
function str_clip(string $s, int $max): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
}

function human_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = (float) $bytes;
    while ($size >= 1024 && $i < 3) {
        $size /= 1024;
        $i++;
    }
    return ($i === 0 ? (string) $bytes : number_format($size, 1)) . ' ' . $units[$i];
}

/**
 * Is this entry excluded from public listings, sitemap, and categories?
 * Patterns are shell-style globs matched against both the entry name and the
 * relative path from uploads/, so 'draft-*' matches at any depth and
 * '_drafts/*' matches only entries inside that folder.
 */
function is_excluded(string $name, string $rel): bool
{
    $rel = trim(str_replace('\\', '/', $rel), '/');

    foreach ((array) EXCLUDE_PATTERNS as $pattern) {
        $pattern = trim((string) $pattern);
        if ($pattern === '') {
            continue;
        }
        if (fnmatch($pattern, $name) || fnmatch($pattern, $rel)) {
            return true;
        }

        // A pattern written to hide a folder's contents hides the folder too.
        // Someone writing '_drafts/*' means the folder is not for the public;
        // listing an empty '_drafts' row still discloses that it exists, and
        // lets it into breadcrumbs and structured data.
        $bare = rtrim($pattern, '*');
        $bare = rtrim($bare, '/');
        if ($bare !== '' && $bare !== $pattern
            && (fnmatch($bare, $rel) || fnmatch($bare, $name))) {
            return true;
        }

        // Anything beneath an excluded folder is excluded, however deep, so
        // 'staff' hides 'staff/2024/report.pdf' without needing a glob.
        if ($rel !== '' && (
            fnmatch($pattern . '/*', $rel) || fnmatch(rtrim($pattern, '/') . '/**', $rel)
        )) {
            return true;
        }
        if ($bare !== '' && $rel !== '' && strpos($rel . '/', $bare . '/') === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Controlled vocabulary for the document_type metadata field. Deliberately
 * separate from the free-form category field: document_type describes what
 * an item physically/editorially is, category describes its subject area.
 * Keys are what gets stored; values are the admin-facing labels.
 */
function document_types(): array
{
    return [
        'card'        => 'Card',
        'certificate' => 'Certificate',
        'letter'      => 'Letter',
        'magazine'    => 'Magazine',
        'article'     => 'Article',
        'tract'       => 'Tract',
        'transcript'  => 'Transcript',
        'report'      => 'Report',
        'form'        => 'Form',
        'identity'    => 'Identity Document',
        'academic'    => 'Academic Record',
        'award'       => 'Award',
        'booklet'     => 'Booklet',
        'other'       => 'Other',
    ];
}

/** Conservative Schema.org type per document_type. Not every archival label needs its own class. */
function document_type_schema_type(string $document_type): string
{
    $map = [
        'article'  => 'Article',
        'magazine' => 'Periodical',
        'report'   => 'Report',
        'letter'   => 'Message',
    ];
    return $map[$document_type] ?? 'DigitalDocument';
}

/* ------------------------------------------------------------------ */
/* External utilities: detection and execution                          */
/* ------------------------------------------------------------------ */

/**
 * Absolute path to an external utility, or null when it is not installed.
 *
 * Only the fixed directory list is searched, and the result is cached for
 * the request. $PATH is deliberately ignored: it is inherited from whatever
 * spawned PHP, and on shared hosting that is not something Folio should
 * trust to decide which binary runs.
 */
function tool_path(string $name): ?string
{
    static $cache = [];
    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }
    if (!TOOLS_ENABLED) {
        return $cache[$name] = null;
    }
    // Names are internal constants, never user input, but validate anyway so
    // that a future caller cannot turn this into a path traversal.
    if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $name)) {
        return $cache[$name] = null;
    }

    $override = (array) TOOL_PATHS;
    if (isset($override[$name])) {
        $p = (string) $override[$name];
        return $cache[$name] = (is_file($p) && is_executable($p)) ? $p : null;
    }

    foreach (tool_search_dirs() as $dir) {
        $p = rtrim($dir, '/') . '/' . $name;
        if (is_file($p) && is_executable($p)) {
            return $cache[$name] = $p;
        }
    }
    return $cache[$name] = null;
}

/**
 * Directories to search, in order.
 *
 * The configured system paths come first, then a few well-known locations
 * inside this account's own home. On shared hosting a user cannot write to
 * /usr/bin, so tools installed for one account normally land in a virtual
 * environment or ~/.local — which is exactly where a cPanel user is told to
 * put OCRmyPDF. Searching there is what makes detection automatic rather
 * than something you have to configure.
 *
 * These are inside the same account that already owns Folio's own code, so
 * they are no more privileged than index.php itself.
 */
function tool_search_dirs(): array
{
    static $dirs = null;
    if (is_array($dirs)) {
        return $dirs;
    }
    $out = array_map('strval', (array) TOOL_SEARCH_PATHS);

    foreach (tool_account_homes() as $home) {
        foreach (['/.local/bin', '/bin', '/ocrmypdf-venv/bin', '/venv/bin', '/.venv/bin'] as $sub) {
            $out[] = $home . $sub;
        }
        // cPanel's "Setup Python App" creates /home/<user>/virtualenv/<app>/<ver>/bin
        foreach ((array) @glob($home . '/virtualenv/*/*/bin', GLOB_ONLYDIR) as $g) {
            $out[] = $g;
        }
        // Any other *-venv or *-env directory the account owns.
        foreach ((array) @glob($home . '/*{-venv,-env,_venv}/bin', GLOB_ONLYDIR | GLOB_BRACE) as $g) {
            $out[] = $g;
        }
    }
    return $dirs = array_values(array_unique($out));
}

/**
 * Home directories to consider for account-local tools.
 *
 * Both the process owner's home and the directory Folio itself sits under
 * are considered, because they are not always the same: a cPanel account
 * runs PHP as its own user, but a site can also be served from a location
 * that does not match the process owner. Trying both is more reliable than
 * picking one and being wrong silently.
 */
function tool_account_homes(): array
{
    static $homes = null;
    if (is_array($homes)) {
        return $homes;
    }
    $out = [];

    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        $pw = @posix_getpwuid(@posix_geteuid());
        if (is_array($pw) && !empty($pw['dir']) && is_dir($pw['dir'])) {
            $out[] = rtrim($pw['dir'], '/');
        }
    }

    // Walk up from Folio's own location: /home/<user>/public_html/... is the
    // usual cPanel shape, so the account home is a few levels above.
    $path = __DIR__;
    for ($i = 0; $i < 8; $i++) {
        $parent = dirname($path);
        if ($parent === $path || $parent === '/' || $parent === '.') {
            break;
        }
        if (preg_match('#^(/home\d*|/usr/home|/var/www)/[^/]+$#', $parent) && is_dir($parent)) {
            $out[] = $parent;
            break;
        }
        $path = $parent;
    }

    return $homes = array_values(array_unique($out));
}

/** Is this utility available? */
function tool_have(string $name): bool
{
    return tool_path($name) !== null;
}

/**
 * Run a utility and capture its output.
 *
 * proc_open() is given an argument array, so no shell is spawned and shell
 * metacharacters in a filename are inert — they arrive at the program as
 * ordinary characters. This is the whole reason Folio can accept
 * FTP-supplied filenames and still call external programs safely.
 *
 * Returns ['code' => int, 'out' => string, 'err' => string]. A code of -1
 * means the program could not be started or timed out.
 */
function tool_run(string $name, array $args, ?int $timeout = null, ?string $stdin = null): array
{
    $bin = tool_path($name);
    if ($bin === null) {
        return ['code' => -1, 'out' => '', 'err' => $name . ' is not installed'];
    }
    $timeout = $timeout ?? TOOL_TIMEOUT;

    $cmd  = array_merge([$bin], array_map('strval', $args));
    $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $env  = ['LC_ALL' => 'C', 'LANG' => 'C', 'HOME' => sys_get_temp_dir(), 'PATH' => '/usr/local/bin:/usr/bin:/bin'];

    $proc = @proc_open($cmd, $desc, $pipes, sys_get_temp_dir(), $env, ['bypass_shell' => true]);
    if (!is_resource($proc)) {
        return ['code' => -1, 'out' => '', 'err' => 'could not start ' . $name];
    }

    if ($stdin !== null) {
        @fwrite($pipes[0], $stdin);
    }
    @fclose($pipes[0]);
    @stream_set_blocking($pipes[1], false);
    @stream_set_blocking($pipes[2], false);

    $out = '';
    $err = '';
    $cap = 8 * 1024 * 1024;   // a runaway program must not exhaust memory
    $deadline = microtime(true) + $timeout;
    $timedout = false;

    while (true) {
        $out .= (string) @stream_get_contents($pipes[1]);
        $err .= (string) @stream_get_contents($pipes[2]);
        if (strlen($out) > $cap || strlen($err) > $cap) {
            @proc_terminate($proc, 9);
            $err .= ' (output limit exceeded)';
            break;
        }
        $status = @proc_get_status($proc);
        if (!$status || !$status['running']) {
            break;
        }
        if (microtime(true) > $deadline) {
            @proc_terminate($proc, 9);
            $timedout = true;
            break;
        }
        usleep(20000);
    }

    $out .= (string) @stream_get_contents($pipes[1]);
    $err .= (string) @stream_get_contents($pipes[2]);
    foreach ($pipes as $p) {
        if (is_resource($p)) {
            @fclose($p);
        }
    }
    $code = @proc_close($proc);
    if ($timedout) {
        return ['code' => -1, 'out' => $out, 'err' => trim($err . ' (timed out after ' . $timeout . 's)')];
    }
    return ['code' => (int) $code, 'out' => $out, 'err' => $err];
}

/** First line of a utility's version output, for Diagnostics. */
function tool_version(string $name): ?string
{
    if (!tool_have($name)) {
        return null;
    }
    $flags = [
        'exiftool' => ['-ver'],
        'pdftotext' => ['-v'], 'pdfinfo' => ['-v'], 'pdftoppm' => ['-v'], 'pdftocairo' => ['-v'],
    ];
    $r = tool_run($name, $flags[$name] ?? ['--version'], 10);
    $text = trim($r['out']) !== '' ? $r['out'] : $r['err'];
    $line = trim((string) strtok($text, "\n"));
    return $line === '' ? 'installed' : str_clip($line, 90);
}

/**
 * Numeric major version of the installed qpdf, or 0 when it cannot be read.
 *
 * qpdf gained --recompress-flate and --compression-level in 10.0. Passing
 * either to an older build makes it exit immediately with "unknown option",
 * so the flags are chosen from what is actually installed rather than
 * assumed. Shared hosts commonly ship 8.x or 9.x.
 */
function qpdf_major(): int
{
    static $major = null;
    if ($major !== null) {
        return $major;
    }
    $v = (string) tool_version('qpdf');
    // "qpdf version 11.9.0" and bare "9.1.1" both appear in the wild.
    if (preg_match('/(\d+)\.\d+/', $v, $m)) {
        return $major = (int) $m[1];
    }
    return $major = 0;
}

/** Tesseract language datasets actually installed. */
function ocr_languages_available(): array
{
    static $langs = null;
    if (is_array($langs)) {
        return $langs;
    }
    if (!tool_have('tesseract')) {
        return $langs = [];
    }
    $r = tool_run('tesseract', ['--list-langs'], 15);
    $out = [];
    foreach (preg_split('/\R/', $r['out'] . "\n" . $r['err']) as $line) {
        $line = trim($line);
        if ($line !== '' && preg_match('/^[a-z]{3}(_[A-Za-z]+)?$/', $line)) {
            $out[] = $line;
        }
    }
    return $langs = array_values(array_unique($out));
}

/** The configured OCR languages that are actually installed, as 'eng+msa'. */
function ocr_language_string(): string
{
    $have = ocr_languages_available();
    $want = array_values(array_filter((array) OCR_LANGUAGES, static function ($l) use ($have) {
        return in_array((string) $l, $have, true);
    }));
    return implode('+', $want ?: (in_array('eng', $have, true) ? ['eng'] : []));
}

/* ------------------------------------------------------------------ */
/* Text extraction and OCR                                              */
/* ------------------------------------------------------------------ */

/**
 * Cache path for a derived artefact belonging to one file.
 *
 * Keyed on modification time and size, so replacing a document over FTP
 * invalidates everything derived from it without anything having to notice.
 */
function derived_path(string $dir, string $rel, string $abs, string $ext): string
{
    $key = hash('sha256', implode('|', [$rel, (string) @filemtime($abs), (string) @filesize($abs)]));
    return rtrim($dir, '/') . '/' . substr($key, 0, 2) . '/' . $key . '.' . $ext;
}

function derived_write(string $path, string $contents): bool
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }
    $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (@file_put_contents($tmp, $contents) === false) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    @chmod($path, 0644);
    return true;
}

/**
 * Structural facts about a PDF, from pdfinfo. Null when unavailable.
 * Returns ['pages' => int, 'encrypted' => bool, 'title' => string,
 *          'width' => float, 'height' => float].
 */
function pdf_info(string $abs): ?array
{
    if (!tool_have('pdfinfo') || !is_file($abs)) {
        return null;
    }
    $r = tool_run('pdfinfo', [$abs], 15);
    if ($r['code'] !== 0) {
        return null;
    }
    $out = ['pages' => 0, 'encrypted' => false, 'title' => '', 'width' => 0.0, 'height' => 0.0];
    foreach (preg_split('/\R/', $r['out']) as $line) {
        if (!str_contains($line, ':')) {
            continue;
        }
        [$k, $v] = explode(':', $line, 2);
        $k = strtolower(trim($k));
        $v = trim($v);
        if ($k === 'pages')     { $out['pages'] = (int) $v; }
        if ($k === 'encrypted') { $out['encrypted'] = stripos($v, 'yes') === 0; }
        if ($k === 'title')     { $out['title'] = str_clip($v, 200); }
        if ($k === 'page size' && preg_match('/([0-9.]+) x ([0-9.]+)/', $v, $m)) {
            $out['width']  = (float) $m[1];
            $out['height'] = (float) $m[2];
        }
    }
    return $out;
}

/**
 * Text already embedded in a PDF, via pdftotext. Cached.
 *
 * This is the cheap case: a born-digital PDF already carries its text and
 * needs no OCR at all. Only when this comes back empty is a scan implied.
 */
function pdf_extract_text(string $rel, string $abs, bool $use_cache = true): ?string
{
    if (!tool_have('pdftotext') || !is_file($abs)) {
        return null;
    }
    $cache = derived_path(TEXT_DIR, $rel, $abs, 'txt');
    if ($use_cache && is_file($cache)) {
        return (string) @file_get_contents($cache);
    }
    // -q quiet, -enc UTF-8, '-' writes to stdout so no temp file is needed.
    $r = tool_run('pdftotext', ['-q', '-enc', 'UTF-8', $abs, '-'], 60);
    if ($r['code'] !== 0) {
        return null;
    }
    $text = trim($r['out']);
    derived_write($cache, $text);
    return $text;
}

/** Does this PDF already contain a usable text layer? */
function pdf_has_text(string $rel, string $abs): bool
{
    $t = pdf_extract_text($rel, $abs);
    // A scanned page often yields a few stray characters from artefacts, so
    // a small threshold separates "has text" from "noise".
    return $t !== null && strlen(preg_replace('/\s+/', '', $t)) >= 32;
}

/** Where the OCR'd copy of a document lives, if one has been made. */
function ocr_output_path(string $rel, string $abs): string
{
    return derived_path(OCR_DIR, $rel, $abs, 'pdf');
}

/**
 * Which OCR route this server can take.
 *
 *   'ocrmypdf' — OCRmyPDF drives the whole job. Best results: it deskews,
 *                keeps existing text layers, and optimises output.
 *   'tesseract'— Poppler renders each page, Tesseract OCRs it into a
 *                single-page searchable PDF, and qpdf joins them. Needs no
 *                Python.
 *   ''         — neither is possible.
 *
 * Neither route needs a PDF delegate. OCRmyPDF skips PDF/A output when it
 * is missing and says so; the Tesseract route never wanted it.
 */
function ocr_method(): string
{
    if (ocr_language_string() === '' || !tool_have('tesseract')) {
        return '';
    }
    if (tool_have('ocrmypdf')) {
        return 'ocrmypdf';
    }
    // qpdf is only needed to join pages, so without it single-page documents
    // can still be processed. Many scans are a single page.
    if (tool_have('pdftocairo') || tool_have('pdftoppm')) {
        return 'tesseract';
    }
    return '';
}

function ocr_available(): bool
{
    return ocr_method() !== '';
}

/**
 * Produce a searchable copy of a scanned PDF.
 *
 * The original is never touched: OCRmyPDF reads it and writes a new file
 * under data/ocr/. That copy is what gets its text extracted and indexed.
 *
 * This is slow — tens of seconds to minutes for a long document — so it is
 * never triggered by a visitor's page load. It runs from the admin screen or
 * the command line, one document at a time.
 */
function ocr_run(string $rel, string $abs, string &$message = ''): bool
{
    $method = ocr_method();
    if ($method === '') {
        $message = 'OCR is not available on this server. See Diagnostics.';
        return false;
    }
    if (!is_file($abs) || strtolower(pathinfo($abs, PATHINFO_EXTENSION)) !== 'pdf') {
        $message = 'Not a PDF.';
        return false;
    }
    $out = ocr_output_path($rel, $abs);
    if (is_file($out)) {
        $message = 'Already processed.';
        return true;
    }
    $dir = dirname($out);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        $message = 'Could not create the OCR cache directory.';
        return false;
    }

    $tmp = $out . '.' . bin2hex(random_bytes(6)) . '.tmp.pdf';
    $ok  = $method === 'ocrmypdf'
        ? ocr_run_ocrmypdf($abs, $tmp, $message)
        : ocr_run_tesseract($abs, $tmp, $message);

    if (!$ok || !is_file($tmp)) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $out)) {
        @unlink($tmp);
        $message = 'Could not store the OCR result.';
        return false;
    }
    @chmod($out, 0644);
    $message = 'Searchable copy created.';
    return true;
}

/** OCR through OCRmyPDF. */
function ocr_run_ocrmypdf(string $abs, string $tmp, string &$message): bool
{
    $args = [
        '--language', ocr_language_string(),
        // Leave pages that already carry text alone rather than rasterising.
        '--skip-text',
        // Plain PDF, not PDF/A. PDF/A output is the one part of OCRmyPDF that
        // needs a delegate, and asking for it where none exists costs a
        // failed attempt and a warning for no benefit.
        '--output-type', 'pdf',
        '--optimize', tool_have('pngquant') ? '2' : '1',
        '--quiet',
    ];
    if (tool_have('unpaper')) {
        $args[] = '--clean';
    }
    $args[] = $abs;
    $args[] = $tmp;

    $r = tool_run('ocrmypdf', $args, OCR_TIMEOUT);
    if ($r['code'] !== 0) {
        $first = trim((string) strtok(trim($r['err']) ?: trim($r['out']), "\n"));
        $message = 'OCR failed' . ($first !== '' ? ': ' . str_clip($first, 160) : '.');
        return false;
    }
    return true;
}

/**
 * OCR without OCRmyPDF: render each page with Poppler, let Tesseract write a
 * searchable single-page PDF, then join them with qpdf.
 *
 * Slower and less refined than OCRmyPDF, but it needs no Python and no
 * a PDF delegate, so it works on a plain LAMP host with three packages.
 */
function ocr_run_tesseract(string $abs, string $tmp, string &$message): bool
{
    $raster = tool_have('pdftocairo') ? 'pdftocairo' : 'pdftoppm';
    $info   = pdf_info($abs);
    $pages  = $info['pages'] ?? 0;
    if ($pages < 1) {
        $message = 'Could not read the page count; is pdfinfo installed?';
        return false;
    }
    if (!empty($info['encrypted'])) {
        $message = 'This PDF is encrypted and cannot be processed.';
        return false;
    }
    // A ceiling keeps one enormous document from monopolising the server.
    $limit = min($pages, 200);

    $work = sys_get_temp_dir() . '/folio-ocr-' . bin2hex(random_bytes(8));
    if (!@mkdir($work, 0700)) {
        $message = 'Could not create a working directory.';
        return false;
    }
    $made = [];
    $lang = ocr_language_string();
    $deadline = time() + OCR_TIMEOUT;

    for ($p = 1; $p <= $limit; $p++) {
        if (time() > $deadline) {
            $message = 'OCR timed out after ' . count($made) . ' of ' . $limit . ' pages.';
            ocr_cleanup_dir($work);
            return false;
        }
        $stem = $work . '/p' . $p;
        // 300 dpi is the usual floor for reliable OCR accuracy.
        $r = tool_run($raster, ['-png', '-r', '300', '-f', (string) $p, '-l', (string) $p, $abs, $stem], 120);
        $png = null;
        foreach ([$stem . '-' . $p . '.png', $stem . '-' . str_pad((string) $p, 2, '0', STR_PAD_LEFT) . '.png',
                  $stem . '-' . str_pad((string) $p, 3, '0', STR_PAD_LEFT) . '.png', $stem . '.png'] as $c) {
            if (is_file($c)) { $png = $c; break; }
        }
        if ($png === null) {
            continue;   // an unrenderable page is skipped, not fatal
        }
        // Tesseract appends .pdf to the output stem itself.
        $outstem = $work . '/ocr' . $p;
        $t = tool_run('tesseract', [$png, $outstem, '-l', $lang, 'pdf'], 180);
        @unlink($png);
        if ($t['code'] === 0 && is_file($outstem . '.pdf')) {
            $made[] = $outstem . '.pdf';
        }
    }

    if (!$made) {
        $message = 'No page could be processed.';
        ocr_cleanup_dir($work);
        return false;
    }

    if (count($made) === 1) {
        $ok = @copy($made[0], $tmp);
    } elseif (!tool_have('qpdf')) {
        // Pages were produced but there is nothing to join them with. Saying
        // so is better than silently storing only the first page.
        $message = 'This document has ' . count($made) . ' pages and qpdf is not '
                 . 'installed to join them. Ask your host for qpdf, or use a '
                 . 'single-page document.';
        ocr_cleanup_dir($work);
        return false;
    } else {
        $args = ['--empty', '--pages'];
        foreach ($made as $f) { $args[] = $f; }
        $args[] = '--';
        $args[] = $tmp;
        $q = tool_run('qpdf', $args, 120);
        $ok = $q['code'] === 0 && is_file($tmp);
        if (!$ok) {
            $message = 'Could not join the OCR pages: '
                . str_clip(trim((string) strtok(trim($q['err']), "\n")), 140);
        }
    }
    ocr_cleanup_dir($work);
    if (!$ok) {
        $message = $message ?: 'Could not assemble the searchable copy.';
        return false;
    }
    if ($limit < $pages) {
        $message = 'Processed the first ' . $limit . ' of ' . $pages . ' pages.';
    }
    return true;
}

function ocr_cleanup_dir(string $dir): void
{
    foreach ((array) @glob($dir . '/*') as $f) {
        @unlink($f);
    }
    @rmdir($dir);
}

/**
 * The best text available for a document: its own text layer, or the text
 * from an OCR'd copy if one exists. Null when there is none.
 */
function document_text(string $rel, string $abs): ?string
{
    if (strtolower(pathinfo($abs, PATHINFO_EXTENSION)) !== 'pdf') {
        return null;
    }
    $own = pdf_extract_text($rel, $abs);
    if ($own !== null && strlen(preg_replace('/\s+/', '', $own)) >= 32) {
        return $own;
    }
    $ocr = ocr_output_path($rel, $abs);
    if (is_file($ocr)) {
        return pdf_extract_text($rel . '#ocr', $ocr);
    }
    return $own;
}

/* ------------------------------------------------------------------ */
/* Derivative images                                                    */
/* ------------------------------------------------------------------ */

/**
 * Which image engine is available, if any.
 *
 * Imagick is preferred because it reads TIFF, HEIC, and PDF, which GD cannot.
 * GD covers the common web formats and is present on almost every host. With
 * neither, every function below returns null and callers fall back to serving
 * the original file, exactly as Folio behaved before derivatives existed.
 */
function image_engine(): string
{
    static $engine = null;
    if ($engine !== null) {
        return $engine;
    }
    if (extension_loaded('imagick') && class_exists('Imagick')) {
        return $engine = 'imagick';
    }
    if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
        return $engine = 'gd';
    }
    return $engine = 'none';
}

/** Formats the active engine can read. */
function image_readable_formats(): array
{
    static $formats = null;
    if (is_array($formats)) {
        return $formats;
    }
    $engine = image_engine();
    if ($engine === 'imagick') {
        $have = array_map('strtolower', (array) Imagick::queryFormats());
        $map  = ['jpg' => 'jpeg', 'tif' => 'tiff', 'heif' => 'heic'];
        $out  = [];
        foreach (['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'heic', 'heif', 'avif'] as $ext) {
            if (in_array($map[$ext] ?? $ext, $have, true)) {
                $out[] = $ext;
            }
        }
        return $formats = $out;
    }
    if ($engine === 'gd') {
        $out = ['png', 'jpg', 'jpeg'];
        if (function_exists('imagecreatefromgif'))  { $out[] = 'gif'; }
        if (function_exists('imagecreatefromwebp')) { $out[] = 'webp'; }
        if (function_exists('imagecreatefrombmp'))  { $out[] = 'bmp'; }
        return $formats = $out;
    }
    return $formats = [];
}

/** Can a derivative be produced for this file at all? */
function image_can_derive(string $rel): bool
{
    if (!THUMBNAILS_ENABLED || image_engine() === 'none') {
        return false;
    }
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        // Poppler renders PDFs directly, so where it is present
        // the security argument for keeping previews off does not apply and
        // they work regardless of PDF_SERVER_PREVIEW. Falling back to
        // Imagick's delegate still requires that setting to be turned on
        // deliberately.
        if (tool_have('pdftocairo') || tool_have('pdftoppm')) {
            return image_engine() !== 'none';
        }
        return PDF_SERVER_PREVIEW && image_engine() === 'imagick';
    }
    // SVG is already a web format and is deliberately never rasterised: it is
    // served as-is under the sandboxing policy that applies to active formats.
    if ($ext === 'svg') {
        return false;
    }
    return in_array($ext, image_readable_formats(), true);
}

/** True when the browser cannot display this format natively. */
function image_needs_conversion(string $rel): bool
{
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    return in_array($ext, (array) CONVERT_FORMATS, true);
}

/**
 * Cache path for one derivative.
 *
 * The key includes the file's modification time and size, so replacing a file
 * over FTP invalidates its derivatives automatically without anything having
 * to notice the change or clean up after it.
 */
function thumb_cache_path(string $rel, string $abs, int $width): string
{
    $key = hash('sha256', implode('|', [
        $rel,
        (string) @filemtime($abs),
        (string) @filesize($abs),
        (string) $width,
        (string) THUMB_QUALITY,
    ]));
    return THUMB_DIR . '/' . substr($key, 0, 2) . '/' . $key . '.webp';
}

/** Apply the guards that keep one conversion from taking down the host. */
function image_apply_limits(Imagick $im): void
{
    $im->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, IMAGE_MEMORY_LIMIT * 1024 * 1024);
    $im->setResourceLimit(Imagick::RESOURCETYPE_MAP,    IMAGE_MEMORY_LIMIT * 1024 * 1024);
    $im->setResourceLimit(Imagick::RESOURCETYPE_TIME,   IMAGE_TIME_LIMIT);
    $im->setResourceLimit(Imagick::RESOURCETYPE_THREAD, 1);
}

/**
 * Produce a derivative and return its cache path, or null if that is not
 * possible. Never throws: a failure here must degrade to the original file
 * rather than break the page it appears on.
 */
function thumb_build(string $rel, string $abs, int $width): ?string
{
    if (!in_array($width, (array) THUMB_WIDTHS, true) || !image_can_derive($rel)) {
        return null;
    }
    $cache = thumb_cache_path($rel, $abs, $width);
    if (is_file($cache)) {
        return $cache;
    }
    $dir = dirname($cache);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }
    if (!is_writable($dir)) {
        return null;
    }

    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    $tmp = $cache . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $ok  = image_engine() === 'imagick'
        ? thumb_build_imagick($abs, $ext, $width, $tmp)
        : thumb_build_gd($abs, $ext, $width, $tmp);

    if (!$ok || !is_file($tmp)) {
        @unlink($tmp);
        return null;
    }
    if (!@rename($tmp, $cache)) {
        @unlink($tmp);
        return null;
    }
    @chmod($cache, 0644);
    thumb_optimise($cache);
    return $cache;
}

/**
 * Rasterise page one of a PDF with Poppler.
 *
 * Preferred over Imagick for PDFs. Imagick delegates PDF work out of process,
 * which has a long history of serious vulnerabilities and which many hosts
 * disable in policy.xml for exactly that reason. Poppler renders PDFs
 * directly, is the library Firefox and most Linux viewers rely on, and needs
 * no delegate. Where it is installed, Folio uses it and never invokes
 * that delegate at all.
 *
 * Returns the path to a temporary PNG, or null.
 */
function pdf_rasterise_page(string $abs, int $width, ?string &$error = null): ?string
{
    $tool = tool_have('pdftocairo') ? 'pdftocairo' : (tool_have('pdftoppm') ? 'pdftoppm' : null);
    if ($tool === null) {
        $error = 'no Poppler rasteriser';
        return null;
    }
    $stem = sys_get_temp_dir() . '/folio-pdf-' . bin2hex(random_bytes(8));
    // Rendering can fail for reasons only the server can see: an encrypted or
    // damaged document, or one large enough to exhaust the time budget. The
    // visitor gets a placeholder either way, so the reason is logged rather
    // than shown — otherwise a library where only some previews work gives
    // nothing to diagnose.

    // -f/-l 1 limits work to the first page: a 400-page scan costs the same
    // as a one-page one.
    $args = ['-png', '-f', '1', '-l', '1', '-scale-to-x', (string) $width, '-scale-to-y', '-1', $abs, $stem];
    $r = tool_run($tool, $args, 60);

    foreach ([$stem . '-1.png', $stem . '-01.png', $stem . '-001.png', $stem . '.png'] as $candidate) {
        if (is_file($candidate)) {
            // Shrink the intermediate before it is decoded and re-encoded.
            // A 300 dpi page render is large, and this is the one point in
            // the pipeline where a real PNG exists for pngquant to work on.
            thumb_optimise($candidate);
            return $candidate;
        }
    }
    $error = trim((string) strtok(trim($r['err']) ?: 'no output', "\n"));
    error_log('Folio: could not render page 1 of ' . $abs . ' — ' . $error);
    return null;
}

/**
 * Shrink a finished derivative in place with pngquant, when it is installed.
 *
 * Best-effort and non-destructive: the derivative is only replaced if the
 * optimiser succeeded and actually produced something smaller. A failure
 * leaves the perfectly usable original derivative alone.
 */
function thumb_optimise(string $path): void
{
    if (!tool_have('pngquant') || !is_file($path)) {
        return;
    }
    // Derivatives are WebP, which pngquant cannot read, so it is given the
    // PNG that Poppler produced on the way to the derivative instead. Guarding
    // on the extension alone made this function unreachable: every derivative
    // Folio writes is WebP, so the PNG branch never ran.
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'png') {
        return;
    }
    $tmp = $path . '.opt.png';
    $r = tool_run('pngquant', ['--quality=65-90', '--strip', '--force', '--output', $tmp, '--', $path], 30);
    if ($r['code'] === 0 && is_file($tmp) && filesize($tmp) > 0 && filesize($tmp) < filesize($path)) {
        @rename($tmp, $path);
        @chmod($path, 0644);
    } else {
        @unlink($tmp);
    }
}

function thumb_build_imagick(string $abs, string $ext, int $width, string $out): bool
{
    try {
        $im = new Imagick();
        image_apply_limits($im);

        if ($ext === 'pdf') {
            // Poppler first: it renders PDFs directly, which is
            // both safer and works on hosts whose ImageMagick policy forbids
            // PDF. Imagick's own delegate is the fallback.
            $png = pdf_rasterise_page($abs, max($width, 1000));
            if ($png !== null) {
                $im->readImage($png);
                @unlink($png);
            } elseif (PDF_ALLOW_GHOSTSCRIPT) {
                // Only reached when Poppler is absent and the delegate has
                // been allowed on purpose.
                $im->setResolution(150, 150);
                $im->readImage($abs . '[0]');
            } else {
                return false;
            }
            $im->setImageBackgroundColor('white');
            $im = $im->flattenImages();
        } else {
            // Read dimensions before pixels: a small file can declare huge
            // dimensions, and decoding it would exhaust memory.
            $probe = new Imagick();
            image_apply_limits($probe);
            $probe->pingImage($abs);
            $pixels = $probe->getImageWidth() * $probe->getImageHeight();
            $probe->clear();
            if ($pixels > IMAGE_MAX_PIXELS) {
                return false;
            }
            $im->readImage($abs);
            $im = $im->coalesceImages();   // animated source: take frame one
            $im->setIteratorIndex(0);
        }

        $im->setImageOrientation($im->getImageOrientation() ?: Imagick::ORIENTATION_TOPLEFT);
        if (method_exists($im, 'autoOrient')) {
            $im->autoOrient();
        }
        if ($im->getImageWidth() > $width) {
            $im->resizeImage($width, 0, Imagick::FILTER_LANCZOS, 1);
        }
        // Metadata can carry GPS coordinates and camera serials. A public
        // thumbnail should not republish them.
        $im->stripImage();
        $im->setImageFormat('webp');
        $im->setImageCompressionQuality((int) THUMB_QUALITY);
        $written = $im->writeImage($out);
        $im->clear();
        return (bool) $written;
    } catch (Throwable $e) {
        return false;
    }
}

function thumb_build_gd(string $abs, string $ext, int $width, string $out): bool
{
    try {
        $info = @getimagesize($abs);
        if (!is_array($info)) {
            return false;
        }
        if (((int) $info[0] * (int) $info[1]) > IMAGE_MAX_PIXELS) {
            return false;
        }
        $src = match ($ext) {
            'png'         => @imagecreatefrompng($abs),
            'jpg', 'jpeg' => @imagecreatefromjpeg($abs),
            'gif'         => function_exists('imagecreatefromgif')  ? @imagecreatefromgif($abs)  : false,
            'webp'        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($abs) : false,
            'bmp'         => function_exists('imagecreatefrombmp')  ? @imagecreatefrombmp($abs)  : false,
            default       => false,
        };
        if (!$src) {
            return false;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $tw = min($width, $sw);
        $th = (int) max(1, round($sh * ($tw / $sw)));
        $dst = imagecreatetruecolor($tw, $th);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $sw, $sh);
        $ok = function_exists('imagewebp')
            ? @imagewebp($dst, $out, (int) THUMB_QUALITY)
            : @imagejpeg($dst, $out, (int) THUMB_QUALITY);
        imagedestroy($src);
        imagedestroy($dst);
        return (bool) $ok;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Whether a derivative may be published for this file.
 *
 * Access gating and derivative generation are separate features that meet
 * here. A PDF restricted to "viewer" or "hidden" must not have page one
 * readable through the thumbnail route, which carries no signature: that
 * would be a second, unguarded door to the content the gate exists to
 * protect. The blurred preview in pdf_blur_generate() is the only derivative
 * a restricted PDF gets.
 */
function thumb_permitted(string $rel, array $m = []): bool
{
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if ($ext === 'pdf' && pdf_access_enforced()) {
        if ($m === []) {
            $meta = meta_load();
            $m = $meta[$rel] ?? [];
        }
        if (pdf_access_of($m) !== 'public') {
            return false;
        }
    }
    return true;
}

/**
 * The URL to show for a file, preferring a derivative when one is possible.
 * Falls back to the original, so callers never need to branch.
 */
function url_thumb(string $rel, int $width = 320, array $m = []): string
{
    if (!image_can_derive($rel) || !thumb_permitted($rel, $m)) {
        return url_raw($rel);
    }
    return BASE_URL . '?action=thumb&w=' . (int) $width . '&file=' . rawurlencode($rel);
}

define('META_FILE', __DIR__ . '/data/metadata.json');
define('META_LOCK_FILE', __DIR__ . '/data/metadata.lock');
define('LEGACY_META_FILE', BASE_DIR . DIRECTORY_SEPARATOR . '.sfm-meta.json');

$folio_meta_cache = null;

function meta_decode_file(string $path, bool &$valid): array
{
    if (is_link($path)) {
        $valid = false;
        return [];
    }
    if (!is_file($path)) {
        $valid = true;
        return [];
    }
    $raw = @file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $valid = is_array($data);
    return $valid ? $data : [];
}

/** Load metadata once per request. Invalid JSON is never silently overwritten. */
/* ------------------------------------------------------------------ */
/* Document identity                                                    */
/*                                                                      */
/* A file path is not an identity. Renaming a file over FTP, or moving  */
/* it to another folder, changes the path but not the document — the    */
/* title, transcript, and above all the public URL must survive both.   */
/* Records are therefore keyed by a permanent document_id, and the path */
/* is stored as one mutable property of the record.                     */
/*                                                                      */
/* The store keeps the legacy shape readable: a top-level 'documents'   */
/* key marks the new format, and anything else is treated as the old    */
/* path-keyed map and migrated on first write.                          */
/* ------------------------------------------------------------------ */

/** A permanent identifier, derived from nothing about the file. */
function document_new_id(): string
{
    return 'doc_' . bin2hex(random_bytes(8));
}

/** Is this metadata array already in the document-keyed format? */
function meta_is_migrated(array $data): bool
{
    return isset($data['documents']) && is_array($data['documents']);
}

/**
 * Slugs Folio reserves for its own routes. A document may not take one, or it
 * would shadow a part of the application.
 */
function reserved_slugs(): array
{
    return [
        'admin', 'login', 'logout', 'settings', 'users', 'accounts', 'crawlers',
        'diagnostics', 'pages', 'page', 'about', 'faq', 'category', 'categories',
        'sitemap', 'sitemap.xml', 'llms', 'llms.txt', 'robots', 'robots.txt',
        'raw', 'render', 'thumb', 'flipbook', 'ocr', 'meta', 'view', 'search',
        'feed', 'rss', 'atom', 'assets', 'lib', 'data', 'docs', 'uploads',
        'install', 'index', 'api', 'relink', 'reconcile',
    ];
}

/**
 * Normalise an administrator-entered slug.
 * Returns '' when nothing usable remains.
 */
function slug_normalise(string $raw): string
{
    $s = trim($raw);
    // A full URL is never a slug. Rejecting it here stops an external address
    // from ever reaching a Location header.
    $s = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $s);
    $s = str_replace('\\', '/', $s);
    $s = str_replace('/', '-', $s);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if (is_string($t) && $t !== '') {
            $s = $t;
        }
    }
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = preg_replace('/-+/', '-', (string) $s);
    return trim((string) $s, '-');
}

/**
 * Why a slug cannot be used, or '' when it is acceptable.
 * $self is the document_id allowed to already own it.
 */
function slug_rejection_reason(string $slug, array $data, string $self = ''): string
{
    if ($slug === '') {
        return 'A URL slug is required. Use lowercase letters, numbers, and hyphens.';
    }
    if (strlen($slug) > 160) {
        return 'That slug is too long. Keep it under 160 characters.';
    }
    if (in_array($slug, reserved_slugs(), true)) {
        return 'That slug is reserved for a Folio route. Choose another.';
    }
    if (preg_match('/^doc_[0-9a-f]{16}$/', $slug)) {
        return 'That slug looks like an internal identifier. Choose another.';
    }
    // Pages live in the same flat namespace, so a document may not take a
    // slug a page already answers on, or one page would become unreachable.
    if (function_exists('page_slots')) {
        foreach (page_slots() as $pslot => $_pm) {
            // $self names the page slot asking, if any, so a page is allowed
            // to keep the slug it already has.
            if ($pslot === $self) {
                continue;
            }
            if ($pslot === $slug) {
                return 'That slug is the name of a standalone page slot. Choose another.';
            }
            if (function_exists('page_slug') && page_slug($pslot) === $slug) {
                return 'A standalone page already uses that slug. Choose another.';
            }
        }
    }
    $docs = $data['documents'] ?? [];
    foreach ($docs as $id => $rec) {
        if ($id === $self) {
            continue;
        }
        if (($rec['slug'] ?? '') === $slug) {
            return 'Another document already uses that slug: '
                 . str_clip((string) ($rec['title'] ?: ($rec['file_path'] ?? $id)), 80);
        }
        foreach ((array) ($rec['aliases'] ?? []) as $a) {
            if ($a === $slug) {
                return 'That slug is a previous URL of another document: '
                     . str_clip((string) ($rec['title'] ?: ($rec['file_path'] ?? $id)), 80);
            }
        }
    }
    return '';
}

/** Make a slug unique within $data by appending a short suffix if needed. */
function slug_make_unique(string $base, array $data, string $self = ''): string
{
    $base = $base !== '' ? $base : 'document';
    if (slug_rejection_reason($base, $data, $self) === '') {
        return $base;
    }
    for ($i = 2; $i <= 50; $i++) {
        $try = $base . '-' . $i;
        if (slug_rejection_reason($try, $data, $self) === '') {
            return $try;
        }
    }
    return $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
}

/**
 * Convert a legacy path-keyed metadata map into the document-keyed format.
 *
 * Pure: it takes the old array and returns the new one, so it can be tested
 * and validated before anything is written. It never touches the filesystem
 * except to read the current slug map, which is what lets an existing public
 * URL be carried over unchanged — the single most important property here,
 * because a migration that silently changes every URL would undo years of
 * indexing and inbound links.
 *
 * Idempotent: an already-migrated array is returned untouched, so running it
 * repeatedly cannot mint new identifiers or duplicate aliases.
 */
function meta_migrate(array $old, array &$warnings = []): array
{
    if (meta_is_migrated($old)) {
        return $old;
    }
    $new = ['version' => 2, 'documents' => []];
    if (!$old) {
        return $new;
    }

    // Sorting keeps identifier assignment and conflict resolution
    // deterministic, so a repeated migration of the same input behaves the
    // same way.
    $paths = array_keys($old);
    sort($paths, SORT_STRING);

    foreach ($paths as $path) {
        $rec = $old[$path];
        if (!is_array($rec)) {
            $warnings[] = 'Skipped a malformed record for ' . str_clip((string) $path, 80) . '.';
            continue;
        }
        $path = (string) $path;

        // Carry over the URL this document already answers on. slug_path()
        // may include a folder prefix; only the final segment becomes the
        // slug, because the new URLs are flat.
        $existing = '';
        if (function_exists('slug_path')) {
            $existing = (string) @slug_path($path);
        }
        $existing = $existing !== '' ? basename($existing) : '';
        $candidate = slug_normalise($existing !== '' ? $existing : pathinfo($path, PATHINFO_FILENAME));

        $reason = slug_rejection_reason($candidate, $new);
        if ($reason !== '') {
            $unique = slug_make_unique($candidate, $new);
            $warnings[] = 'The URL for ' . str_clip($path, 80) . ' became "' . $unique
                        . '" because "' . $candidate . '" was not available.';
            $candidate = $unique;
        }

        $id = document_new_id();
        while (isset($new['documents'][$id])) {
            $id = document_new_id();
        }

        // Every existing field is carried across untouched. Only the
        // identity fields are added.
        $doc = $rec;
        unset($doc['document_id'], $doc['file_path'], $doc['slug'], $doc['aliases']);
        $new['documents'][$id] = array_merge($doc, [
            'document_id' => $id,
            'file_path'   => $path,
            'slug'        => $candidate,
            'aliases'     => [],
            'fingerprint' => (string) ($rec['fingerprint'] ?? ''),
            'file_size'   => (int) ($rec['file_size'] ?? 0),
            'file_mtime'  => (int) ($rec['file_mtime'] ?? 0),
        ]);
    }
    return $new;
}

/**
 * Does this document store hold together?
 *
 * Checked before any write. A store where two documents claim one slug would
 * make routing ambiguous, and an invalid store must never be allowed to
 * replace a valid one.
 */
function meta_validate(array $data, string &$error = ''): bool
{
    if (!meta_is_migrated($data)) {
        $error = 'The metadata is not in the document format.';
        return false;
    }
    $seen = [];
    foreach ($data['documents'] as $id => $rec) {
        if (!is_array($rec)) {
            $error = 'Record ' . str_clip((string) $id, 40) . ' is not an array.';
            return false;
        }
        if (($rec['document_id'] ?? '') !== $id) {
            $error = 'Record ' . str_clip((string) $id, 40) . ' has a mismatched identifier.';
            return false;
        }
        $slug = (string) ($rec['slug'] ?? '');
        if ($slug === '') {
            $error = 'Record ' . str_clip((string) $id, 40) . ' has no slug.';
            return false;
        }
        foreach (array_merge([$slug], array_map('strval', (array) ($rec['aliases'] ?? []))) as $s) {
            if (isset($seen[$s])) {
                $error = 'The slug "' . str_clip($s, 60) . '" is claimed by two documents.';
                return false;
            }
            $seen[$s] = $id;
        }
        if (in_array($slug, (array) ($rec['aliases'] ?? []), true)) {
            $error = 'Record ' . str_clip((string) $id, 40) . ' lists its own slug as an alias.';
            return false;
        }
    }
    return true;
}

/**
 * Lookup indexes, built once per request.
 * Routing must not walk every record for each incoming URL.
 */
function meta_indexes(?array $data = null): array
{
    static $cache = null;
    if ($data === null && is_array($cache) && $cache !== []) {
        return $cache;
    }
    $data = $data ?? meta_documents();
    $idx = ['slug' => [], 'alias' => [], 'path' => []];
    foreach (($data['documents'] ?? []) as $id => $rec) {
        $slug = (string) ($rec['slug'] ?? '');
        if ($slug !== '') {
            $idx['slug'][$slug] = $id;
        }
        foreach ((array) ($rec['aliases'] ?? []) as $a) {
            $a = (string) $a;
            // A slug that is now canonical somewhere must never be treated as
            // an alias, or a request would redirect away from a live page.
            if ($a !== '' && !isset($idx['slug'][$a])) {
                $idx['alias'][$a] = $id;
            }
        }
        $p = (string) ($rec['file_path'] ?? '');
        if ($p !== '') {
            $idx['path'][$p] = $id;
        }
    }
    // Resolve the ordering hazard: an alias recorded before its canonical
    // owner was seen. Canonical always wins.
    foreach (array_keys($idx['slug']) as $s) {
        unset($idx['alias'][$s]);
    }
    if ($data === null) {
        $cache = $idx;
    }
    return $idx;
}

/** The document record for a relative file path, or null. */
/**
 * The canonical public URL for a document record.
 *
 * Built from the saved slug alone. It deliberately ignores the file's name
 * and folder: those are FTP's business and change without the document
 * changing, whereas this address is permanent.
 */
/**
 * Write one document's fields into whichever store shape is on disk.
 *
 * The metadata file may still be the legacy path-keyed map, or already the
 * document-keyed one. Writing a path key into a migrated store would corrupt
 * it, so every write goes through here rather than assigning into the array
 * directly.
 *
 * $fields are descriptive values only. Identity — document_id, slug,
 * aliases, file_path — is managed separately and never overwritten here.
 */
function meta_put_record(array $store, string $rel, ?array $fields): array
{
    // Any write moves a legacy store into the document format. Doing it here
    // rather than leaving two shapes in play means identity fields — the
    // fingerprint especially — are recorded from the first save. Without a
    // fingerprint stored now, a later rename over FTP has nothing to match
    // the file back to its document. meta_update() keeps the usual backup.
    if (!meta_is_migrated($store)) {
        $store = meta_migrate($store);
    }

    $id = null;
    foreach ($store['documents'] as $did => $rec) {
        if ((string) ($rec['file_path'] ?? '') === $rel) {
            $id = $did;
            break;
        }
    }

    if ($fields === null) {
        // Clearing the descriptive fields must not destroy the document's
        // identity: its URL and aliases have to survive, or every link to it
        // would break the moment someone emptied the title box.
        if ($id !== null) {
            $keep = $store['documents'][$id];
            foreach (array_keys($keep) as $k) {
                if (!in_array($k, ['document_id', 'file_path', 'slug', 'aliases',
                                   'fingerprint', 'file_size', 'file_mtime'], true)) {
                    unset($keep[$k]);
                }
            }
            $store['documents'][$id] = $keep;
        }
        return $store;
    }

    if ($id === null) {
        $id = document_new_id();
        while (isset($store['documents'][$id])) {
            $id = document_new_id();
        }
        // Use the address Folio already serves this file on, so cataloguing
        // an existing document for the first time does not change its URL.
        // slug_path() may carry a folder prefix; only the last segment
        // becomes the slug, since document slugs are a flat namespace.
        $existing_slug = basename((string) slug_path($rel));
        $base = slug_normalise($existing_slug !== '' ? $existing_slug : pathinfo($rel, PATHINFO_FILENAME));
        $store['documents'][$id] = [
            'document_id' => $id,
            'file_path'   => $rel,
            'slug'        => slug_make_unique($base, $store),
            'aliases'     => [],
            'fingerprint' => '',
            'file_size'   => 0,
            'file_mtime'  => 0,
        ];
    }
    $identity = array_intersect_key($store['documents'][$id], array_flip([
        'document_id', 'file_path', 'slug', 'aliases', 'fingerprint', 'file_size', 'file_mtime',
        'captured_at',
    ]));

    // Record the file's fingerprint while we are already writing. Without one
    // stored now, a later rename over FTP leaves nothing to match the file
    // back to this document. Hashing here is safe: this runs on an
    // administrator's save, not on a page view.
    $abs = resolve_path($rel);
    if ($abs !== null && is_file($abs) && !fingerprint_is_current($identity, $abs)) {
        $identity['fingerprint'] = file_fingerprint($abs);
        $identity['file_size']   = (int) @filesize($abs);
        $identity['file_mtime']  = (int) @filemtime($abs);
        // A document that states its own creation date is usually telling the
        // truth better than the filesystem, whose time changes every time the
        // file is copied or re-uploaded. Recorded when available; ignored
        // entirely when exiftool is not installed.
        $embedded = file_embedded_date($abs);
        if ($embedded !== null) {
            $identity['captured_at'] = $embedded;
        }
    }

    $store['documents'][$id] = array_merge($fields, $identity);
    return $store;
}

/**
 * Change a document's canonical slug, keeping the old one as an alias.
 *
 * Aliases are flattened, never chained: they all name the record, and the
 * record names the current slug, so A → B → C leaves both A and B pointing
 * straight at C. Reverting to an earlier slug removes it from the alias list
 * so a URL is never simultaneously canonical and redirecting.
 */
function document_set_slug(string $rel, string $raw, string &$error = ''): bool
{
    // A pasted URL is refused rather than mangled into a slug. Normalising it
    // would be safe — the result carries no protocol or slashes and could not
    // become an external redirect — but silently turning
    // "https://example.com/x" into "https-example-com-x" hides a mistake
    // instead of reporting it.
    if (preg_match('#^\s*[a-z][a-z0-9+.-]*://#i', $raw) || preg_match('#^\s*//#', $raw)) {
        $error = 'Enter a slug, not a full web address. Use only lowercase letters, numbers, and hyphens.';
        return false;
    }
    $slug = slug_normalise($raw);
    $result = meta_update(static function (array $store) use ($rel, $slug, &$error) {
        // Any refusal must leave the store exactly as it was. meta_update
        // writes whatever the mutator returns, so an error path returns the
        // original rather than a partly-changed copy.
        $original = $store;
        if (!meta_is_migrated($store)) {
            $store = meta_migrate($store);
        }
        $id = null;
        foreach ($store['documents'] as $did => $rec) {
            if ((string) ($rec['file_path'] ?? '') === $rel) {
                $id = $did;
                break;
            }
        }
        if ($id === null) {
            $error = 'That document is not in the catalogue yet.';
            return $original;
        }
        $reason = slug_rejection_reason($slug, $store, $id);
        if ($reason !== '') {
            $error = $reason;
            return $original;
        }
        $old = (string) ($store['documents'][$id]['slug'] ?? '');
        if ($old === $slug) {
            return $store;   // unchanged: no alias is recorded
        }
        $aliases = array_values(array_unique(array_filter(
            array_map('strval', (array) ($store['documents'][$id]['aliases'] ?? []))
        )));
        if ($old !== '') {
            $aliases[] = $old;
        }
        // The new slug must not also be listed as one of its own aliases.
        $aliases = array_values(array_filter($aliases, static function ($a) use ($slug) {
            return $a !== $slug;
        }));
        $store['documents'][$id]['slug']    = $slug;
        $store['documents'][$id]['aliases'] = array_values(array_unique($aliases));

        $check = '';
        if (!meta_validate($store, $check)) {
            $error = 'That change would make the catalogue inconsistent: ' . $check;
            return $original;
        }
        return $store;
    });
    if ($result === false) {
        $error = $error ?: 'The catalogue could not be written.';
        return false;
    }
    return $error === '';
}

/* ------------------------------------------------------------------ */
/* FTP rename and move reconciliation                                   */
/*                                                                      */
/* FTP owns the files; Folio owns the catalogue. When a file is renamed */
/* or moved outside the application, the record's saved path stops      */
/* resolving. Matching the bytes lets the record follow the file, so    */
/* the title, transcript, and above all the public URL survive.         */
/*                                                                      */
/* Hashing is deliberate work, never done during ordinary browsing: a   */
/* library of large scans would make every page load read every byte on */
/* disk. It happens when a record is first written, when a saved path   */
/* has disappeared, or when an administrator asks for it.               */
/* ------------------------------------------------------------------ */

/**
 * Metadata a document carries about itself, via exiftool.
 *
 * Useful mainly for the date: a scan's own creation date is usually closer to
 * the truth than the file's modification time, which changes whenever the
 * file is copied or re-uploaded over FTP. Returns [] when exiftool is absent
 * or the file says nothing — always optional, never required.
 */
function file_embedded_metadata(string $abs): array
{
    if (!tool_have('exiftool') || !is_file($abs)) {
        return [];
    }
    // -json for a parseable answer, -n for raw values, and an explicit tag
    // list so a hostile file cannot make the output unbounded.
    $r = tool_run('exiftool', [
        '-json', '-n', '-s',
        '-CreateDate', '-DateTimeOriginal', '-ModifyDate',
        '-Title', '-Author', '-Producer', '-PageCount', '-ImageSize',
        $abs,
    ], 20);
    if ($r['code'] !== 0) {
        return [];
    }
    $data = json_decode(trim($r['out']), true);
    if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
        return [];
    }
    $out = [];
    foreach ($data[0] as $k => $v) {
        if ($k === 'SourceFile' || !is_scalar($v)) {
            continue;
        }
        $out[$k] = str_clip((string) $v, 200);
    }
    return $out;
}

/**
 * The date a document was created, as a timestamp, or null.
 * Preferred over the filesystem time when a document states its own.
 */
function file_embedded_date(string $abs): ?int
{
    $meta = file_embedded_metadata($abs);
    foreach (['DateTimeOriginal', 'CreateDate', 'ModifyDate'] as $field) {
        $raw = trim((string) ($meta[$field] ?? ''));
        if ($raw === '') {
            continue;
        }
        // exiftool writes "2019:03:14 10:22:01"; the colons in the date part
        // are not something strtotime understands.
        $norm = preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', $raw);
        $ts = strtotime((string) $norm);
        if ($ts !== false && $ts > 0 && $ts <= time() + 86400) {
            return $ts;
        }
    }
    return null;
}

/**
 * Interpret the date a document carries.
 *
 * Historical documents state their date in whatever form they please: a bare
 * year, a month and year, a full date, sometimes an approximation. Forcing a
 * date picker on them would either lose that nuance or refuse the document,
 * so the field is free text and this reads what it can.
 *
 * Returns ['display' => as entered, 'iso' => ISO 8601 or '',
 *          'year' => int|0, 'precision' => 'day'|'month'|'year'|''].
 */
function document_date_parse(string $raw): array
{
    $raw = trim($raw);
    $out = ['display' => $raw, 'iso' => '', 'year' => 0, 'precision' => ''];
    if ($raw === '') {
        return $out;
    }

    // Malay month names appear throughout a Malaysian collection, and a
    // document dated "Oktober 1998" should sort with October, not nowhere.
    $months = [
        'jan' => 1, 'feb' => 2, 'mac' => 3, 'mar' => 3, 'apr' => 4,
        'mei' => 5, 'may' => 5, 'jun' => 6, 'jul' => 7, 'ogo' => 8, 'aug' => 8,
        'sep' => 9, 'okt' => 10, 'oct' => 10, 'nov' => 11, 'dis' => 12, 'dec' => 12,
    ];

    // Full ISO or slash/dot date.
    if (preg_match('/(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $raw, $m)) {
        $y = (int) $m[1]; $mo = (int) $m[2]; $d = (int) $m[3];
        if ($mo >= 1 && $mo <= 12 && $d >= 1 && $d <= 31) {
            return ['display' => $raw, 'iso' => sprintf('%04d-%02d-%02d', $y, $mo, $d),
                    'year' => $y, 'precision' => 'day'];
        }
    }
    // Day-first numeric: "30/11/1991". Day-first is the convention in
    // Malaysia and most of the world; a value above 12 in the first position
    // settles it outright, and below that the convention decides.
    if (preg_match('/(\\d{1,2})[-\\/.](\\d{1,2})[-\\/.](\\d{4})/', $raw, $m)) {
        $d = (int) $m[1]; $mo = (int) $m[2]; $y = (int) $m[3];
        if ($d >= 1 && $d <= 31 && $mo >= 1 && $mo <= 12) {
            return ['display' => $raw, 'iso' => sprintf('%04d-%02d-%02d', $y, $mo, $d),
                    'year' => $y, 'precision' => 'day'];
        }
    }
    // Day month year, in words: "30 Oktober 1998".
    if (preg_match('/(\d{1,2})\s+([a-zA-Z]{3,})\s+(\d{4})/', $raw, $m)) {
        $key = strtolower(substr($m[2], 0, 3));
        if (isset($months[$key])) {
            return ['display' => $raw,
                    'iso' => sprintf('%04d-%02d-%02d', (int) $m[3], $months[$key], (int) $m[1]),
                    'year' => (int) $m[3], 'precision' => 'day'];
        }
    }
    // Month and year: "Oktober 1998".
    if (preg_match('/([a-zA-Z]{3,})\s+(\d{4})/', $raw, $m)) {
        $key = strtolower(substr($m[1], 0, 3));
        if (isset($months[$key])) {
            return ['display' => $raw, 'iso' => sprintf('%04d-%02d', (int) $m[2], $months[$key]),
                    'year' => (int) $m[2], 'precision' => 'month'];
        }
    }
    // A year on its own, including "c. 1985" and "1991/92". Anything from the
    // first photographs to a little ahead of now.
    if (preg_match('/(1[89]\d{2}|20\d{2}|21\d{2})/', $raw, $m)) {
        $y = (int) $m[1];
        if ($y >= 1800 && $y <= (int) date('Y') + 5) {
            return ['display' => $raw, 'iso' => (string) $y, 'year' => $y, 'precision' => 'year'];
        }
    }
    return $out;
}

/**
 * Make a smaller copy of a PDF, losslessly.
 *
 * Scanners often write PDFs that store their page images with little or no
 * compression, and those files can be enormous for what they contain — a
 * single certificate arriving as tens of megabytes. qpdf rewrites the
 * structure and recompresses the streams without touching the images
 * themselves, so the result is byte-for-byte the same document to a reader
 * while being a fraction of the size.
 *
 * Lossless is the only mode offered. This is an archive: a scan of a birth
 * certificate should not be quietly degraded to save bandwidth.
 *
 * The original is never touched. The copy is written under data/, and it is
 * for the administrator to decide whether to put it in place over FTP.
 *
 * Returns the path to the copy, or null when nothing worth keeping was
 * produced. $report receives 'before', 'after', 'saved_pct' and 'message'.
 */
function pdf_compress(string $rel, string $abs, array &$report = []): ?string
{
    $report = ['before' => 0, 'after' => 0, 'saved_pct' => 0, 'message' => ''];

    if (!tool_have('qpdf')) {
        $report['message'] = 'qpdf is not installed on this server.';
        return null;
    }
    if (!is_file($abs) || strtolower(pathinfo($abs, PATHINFO_EXTENSION)) !== 'pdf') {
        $report['message'] = 'Not a PDF.';
        return null;
    }

    $before = (int) @filesize($abs);
    $report['before'] = $before;
    if ($before <= 0) {
        $report['message'] = 'The file is empty.';
        return null;
    }

    $out = derived_path(COMPRESS_DIR, $rel, $abs, 'pdf');
    if (is_file($out)) {
        $report['after'] = (int) @filesize($out);
        $report['saved_pct'] = (int) round(100 - ($report['after'] / $before * 100));
        $report['message'] = 'Already prepared.';
        return $out;
    }
    $dir = dirname($out);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        $report['message'] = 'Could not create the cache directory.';
        return null;
    }

    $tmp = $out . '.' . bin2hex(random_bytes(6)) . '.tmp.pdf';

    /* Understood by every qpdf still in circulation. */
    $qpdf_args = [
        '--object-streams=generate',
        '--compress-streams=y',
        '--stream-data=compress',
        // Linearised output starts rendering before the whole file arrives,
        // which matters for a large scan opened in a browser.
        '--linearize',
    ];
    /* Added in qpdf 10.0. Older builds abort on an unknown option rather
       than ignoring it, so they are offered only where they exist. Their
       absence costs a few percent of the saving, not the feature. */
    if (qpdf_major() >= 10) {
        $qpdf_args[] = '--recompress-flate';
        $qpdf_args[] = '--compression-level=9';
    }
    $qpdf_args[] = $abs;
    $qpdf_args[] = $tmp;

    $r = tool_run('qpdf', $qpdf_args, 300);

    // qpdf exits 3 on warnings while still writing a usable file, so the
    // output is judged on whether it exists and reads back, not on the code.
    if (!is_file($tmp) || (int) @filesize($tmp) <= 0) {
        @unlink($tmp);
        $first = trim((string) strtok(trim($r['err']) ?: 'no output', "\n"));
        /* An unknown option means qpdf is older than the flags being sent.
           Say so, with the version, rather than passing the raw tool error to
           someone who has no reason to know qpdf's release history. */
        if (stripos($first, 'unknown option') !== false) {
            $first = 'this server has qpdf ' . (qpdf_major() ?: 'of an unknown version')
                   . ', which does not support an option Folio used. Please report this,'
                   . ' quoting: ' . str_clip($first, 90);
        }
        $report['message'] = 'Could not compress: ' . str_clip($first, 220);
        return null;
    }

    $after = (int) @filesize($tmp);

    // Verify before offering it. A smaller file that will not open is worse
    // than no file at all.
    $check = tool_have('pdfinfo') ? tool_run('pdfinfo', [$tmp], 30) : ['code' => 0];
    if ($check['code'] !== 0) {
        @unlink($tmp);
        $report['message'] = 'The compressed copy did not verify, so it was discarded.';
        return null;
    }

    $saved = (int) round(100 - ($after / $before * 100));
    if ($after >= $before || $saved < 3) {
        // Already efficiently stored. Keeping a copy that saves nothing only
        // costs disk and invites confusion about which file is authoritative.
        @unlink($tmp);
        $report['after'] = $before;
        $report['saved_pct'] = 0;
        $report['message'] = 'Already well compressed — nothing worth saving.';
        return null;
    }

    if (!@rename($tmp, $out)) {
        @unlink($tmp);
        $report['message'] = 'Could not store the compressed copy.';
        return null;
    }
    @chmod($out, 0644);

    $report['after'] = $after;
    $report['saved_pct'] = $saved;
    $report['message'] = human_size($before) . ' to ' . human_size($after)
                       . ' — ' . $saved . '% smaller.';
    return $out;
}

/** SHA-256 of a file, or '' if it cannot be read. */
function file_fingerprint(string $abs): string
{
    if (!is_file($abs) || !is_readable($abs)) {
        return '';
    }
    $h = @hash_file('sha256', $abs);
    return is_string($h) ? $h : '';
}

/**
 * Is a record's stored fingerprint still trustworthy?
 * Size and modification time are cheap to check and change whenever the
 * bytes do, so an unchanged pair means the stored hash can be reused.
 */
function fingerprint_is_current(array $rec, string $abs): bool
{
    if (($rec['fingerprint'] ?? '') === '') {
        return false;
    }
    return (int) ($rec['file_size'] ?? -1) === (int) @filesize($abs)
        && (int) ($rec['file_mtime'] ?? -1) === (int) @filemtime($abs);
}

/**
 * Survey the catalogue against the filesystem.
 *
 * Returns:
 *   orphans      — records whose saved file_path no longer exists
 *   unassociated — files on disk that no record claims
 *   ok           — records whose file is present
 */
function reconcile_survey(): array
{
    $data = meta_documents();
    $docs = $data['documents'] ?? [];

    $orphans = [];
    $ok = [];
    $claimed = [];
    foreach ($docs as $id => $rec) {
        $path = (string) ($rec['file_path'] ?? '');
        $abs  = $path !== '' ? resolve_path($path) : null;
        if ($abs !== null && is_file($abs)) {
            $ok[$id] = $rec;
            $claimed[$path] = $id;
        } else {
            $orphans[$id] = $rec;
        }
    }

    // Walk the library once. index_all_files() already applies exclusion and
    // path-containment rules, so nothing hidden can appear here.
    global $mime_map;
    $unassociated = [];
    foreach (index_all_files($mime_map ?? []) as $f) {
        $rel = (string) ($f['rel'] ?? '');
        if ($rel !== '' && !isset($claimed[$rel])) {
            $unassociated[$rel] = $f;
        }
    }

    return ['orphans' => $orphans, 'unassociated' => $unassociated, 'ok' => $ok];
}

/**
 * Match orphaned records to unassociated files by content.
 *
 * A match is only accepted when exactly one file has the record's
 * fingerprint and exactly one record wants that file. Anything ambiguous is
 * left alone and reported: silently attaching a document's history to the
 * wrong file is far worse than asking someone to choose.
 *
 * $apply false surveys without writing, which is what the diagnostics screen
 * shows before an administrator commits.
 */
function reconcile_run(bool $apply, array &$report = []): bool
{
    $survey = reconcile_survey();
    $report = ['matched' => [], 'ambiguous' => [], 'orphans' => [], 'unassociated' => []];

    if (!$survey['orphans']) {
        $report['unassociated'] = array_keys($survey['unassociated']);
        return true;
    }

    // Hash the candidates once. This is the expensive step, and it only runs
    // because something is already known to be missing.
    $by_hash = [];
    foreach ($survey['unassociated'] as $rel => $f) {
        $abs = resolve_path($rel);
        if ($abs === null) {
            continue;
        }
        $h = file_fingerprint($abs);
        if ($h !== '') {
            $by_hash[$h][] = $rel;
        }
    }

    $plan = [];
    $wanted = [];
    foreach ($survey['orphans'] as $id => $rec) {
        $h = (string) ($rec['fingerprint'] ?? '');
        if ($h === '' || !isset($by_hash[$h])) {
            $report['orphans'][] = $id;
            continue;
        }
        if (count($by_hash[$h]) !== 1) {
            // Several files share these bytes; which one this document is
            // cannot be decided from content alone.
            $report['ambiguous'][] = ['document_id' => $id, 'candidates' => $by_hash[$h]];
            continue;
        }
        $rel = $by_hash[$h][0];
        $wanted[$rel][] = $id;
        $plan[$id] = $rel;
    }

    // Two records claiming the same file is equally ambiguous.
    foreach ($wanted as $rel => $ids) {
        if (count($ids) > 1) {
            foreach ($ids as $id) {
                unset($plan[$id]);
                $report['ambiguous'][] = ['document_id' => $id, 'candidates' => [$rel]];
            }
        }
    }

    foreach ($plan as $id => $rel) {
        $report['matched'][] = ['document_id' => $id, 'file_path' => $rel];
    }
    foreach (array_keys($survey['unassociated']) as $rel) {
        if (!isset($wanted[$rel])) {
            $report['unassociated'][] = $rel;
        }
    }

    if (!$apply || !$plan) {
        return true;
    }

    $result = meta_update(static function (array $store) use ($plan) {
        if (!meta_is_migrated($store)) {
            $store = meta_migrate($store);
        }
        foreach ($plan as $id => $rel) {
            if (!isset($store['documents'][$id])) {
                continue;
            }
            $abs = resolve_path($rel);
            // Only the file association changes. The identity, the slug, the
            // aliases and every descriptive field stay exactly as they were.
            $store['documents'][$id]['file_path']  = $rel;
            $store['documents'][$id]['file_size']  = $abs ? (int) @filesize($abs) : 0;
            $store['documents'][$id]['file_mtime'] = $abs ? (int) @filemtime($abs) : 0;
        }
        $check = '';
        return meta_validate($store, $check) ? $store : $store;
    });
    return $result !== false;
}

/**
 * Attach an existing record to a specific file, chosen by an administrator.
 *
 * For the cases content matching cannot settle: a file that was renamed and
 * also edited, so its bytes no longer match anything Folio remembers.
 * Physical files are never touched.
 */
function document_relink(string $document_id, string $rel, string &$error = ''): bool
{
    $abs = resolve_path($rel);
    if ($abs === null || !is_file($abs)) {
        $error = 'That file was not found in the library.';
        return false;
    }
    if (is_excluded(basename($rel), $rel)) {
        $error = 'That file is excluded from the library.';
        return false;
    }
    $result = meta_update(static function (array $store) use ($document_id, $rel, $abs, &$error) {
        $original = $store;
        if (!meta_is_migrated($store)) {
            $store = meta_migrate($store);
        }
        if (!isset($store['documents'][$document_id])) {
            $error = 'That document is no longer in the catalogue.';
            return $original;
        }
        foreach ($store['documents'] as $id => $rec) {
            if ($id !== $document_id && (string) ($rec['file_path'] ?? '') === $rel) {
                $error = 'That file already belongs to another document: '
                       . str_clip((string) ($rec['title'] ?: $id), 80);
                return $original;
            }
        }
        $store['documents'][$document_id]['file_path']   = $rel;
        $store['documents'][$document_id]['fingerprint'] = file_fingerprint($abs);
        $store['documents'][$document_id]['file_size']   = (int) @filesize($abs);
        $store['documents'][$document_id]['file_mtime']  = (int) @filemtime($abs);

        $check = '';
        if (!meta_validate($store, $check)) {
            $error = 'That change would make the catalogue inconsistent: ' . $check;
            return $original;
        }
        return $store;
    });
    if ($result === false) {
        $error = $error ?: 'The catalogue could not be written.';
        return false;
    }
    return $error === '';
}

/**
 * The <link> tags for the site icon.
 *
 * Resolution order: an explicit SITE_ICON, then anything in branding/, then
 * the icon that ships with Folio. Emitted from one place so a new page type
 * cannot quietly keep pointing at the default.
 */
/**
 * A versioned URL for a release-owned asset.
 *
 * The stylesheet and scripts are told to cache for a year and never
 * revalidate, which is right for files that only change on upgrade — but only
 * if the URL changes with them. Without this, an upgrade ships new markup to
 * a browser still holding the previous stylesheet, and the page renders with
 * rules that no longer match: sort buttons drawn as plain boxes, tags still
 * carrying borders the new CSS removed.
 */
/**
 * URL for a release-owned asset, carrying the version so a year-long cache is
 * bypassed exactly when the file changes.
 *
 * A minified twin is preferred when one exists and is not older than the
 * source. The mtime comparison is what makes editing safe: change
 * `style.css` to adjust a theme and it immediately outranks the stale
 * `style.min.css`, with nothing to rebuild and no setting to remember.
 * Deleting the `.min.` files reverts to readable sources permanently.
 */
define('ASSET_MANIFEST_FILE', __DIR__ . '/assets/manifest.json');

/**
 * URL for a release-owned asset, carrying the version so a year-long cache is
 * bypassed exactly when the file changes.
 *
 * A minified twin is used when one exists and was built from the source
 * currently on disk. That last part is decided by comparing the source's byte
 * length with the length recorded when it was minified, not by comparing
 * modification times: over FTP, mtimes are set by upload order and by
 * whatever the client feels like doing, so a perfectly good minified file
 * could look stale and be ignored for good. A byte length survives upload
 * unchanged and still changes the moment the file is edited.
 *
 * Editing a stylesheet therefore still works with nothing to rebuild: the
 * length stops matching, and the readable source is served again.
 */
function asset_manifest(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }
    $map = [];
    if (!is_link(ASSET_MANIFEST_FILE) && is_file(ASSET_MANIFEST_FILE)) {
        $raw  = @file_get_contents(ASSET_MANIFEST_FILE);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($data)) {
            $map = $data;
        }
    }
    return $map;
}

/**
 * The stylesheet tag: inlined where that is faster, linked otherwise.
 * Every page uses this, so the two paths cannot diverge screen by screen.
 */
function stylesheet_tag(): string
{
    $css = inline_css();
    if ($css !== null) {
        return '<style>' . $css . '</style>';
    }
    return '<link rel="stylesheet" href="' . e(asset_url('assets/css/style.css')) . '">';
}

/**
 * The stylesheet, inlined when that is the faster choice.
 *
 * A linked stylesheet blocks the first paint for a whole extra round trip:
 * the browser must parse the document, request the file, and wait. On a slow
 * mobile connection that measured around 270ms of nothing on screen. Folio's
 * stylesheet compresses to a few kilobytes, so putting it in the document
 * removes the wait entirely and the page paints on the first response.
 *
 * Returns the CSS when it should be inlined, or null to link it as before:
 * when there is no manifest, when the minified file is missing, or when it
 * has grown past the point where inlining is worth repeating on every page.
 */
function inline_css(): ?string
{
    static $css = false;
    if ($css !== false) {
        return $css;
    }
    $entry = asset_manifest()['assets/css/style.css'] ?? null;
    if (!is_array($entry) || ($entry['sha256'] ?? '') === '') {
        return $css = null;
    }
    // Beyond this, the bytes repeated on every page outweigh the saved trip.
    if ((int) ($entry['min_size'] ?? 0) > 60000) {
        return $css = null;
    }
    $abs = __DIR__ . '/' . (string) $entry['min'];
    $src = __DIR__ . '/assets/css/style.css';
    if (is_link($abs) || !is_file($abs) || !is_file($src)
        || (int) ($entry['size'] ?? -1) !== filesize($src)) {
        // Source edited since the twin was built: link the readable file.
        return $css = null;
    }
    $body = @file_get_contents($abs);
    return $css = is_string($body) ? $body : null;
}

/** The CSP source expression for the inlined stylesheet, if there is one. */
function inline_css_hash(): string
{
    if (inline_css() === null) {
        return '';
    }
    $entry = asset_manifest()['assets/css/style.css'] ?? [];
    $hash  = (string) ($entry['sha256'] ?? '');
    return $hash === '' ? '' : " 'sha256-" . $hash . "'";
}

function asset_url(string $path): string
{
    $rel = ltrim($path, '/');
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));

    if ($ext === 'css' || $ext === 'js') {
        $entry = asset_manifest()[$rel] ?? null;
        if (is_array($entry)) {
            $min = (string) ($entry['min'] ?? '');
            $src_abs = __DIR__ . '/' . $rel;
            $min_abs = __DIR__ . '/' . $min;
            if ($min !== '' && is_file($min_abs) && is_file($src_abs)
                && (int) ($entry['size'] ?? -1) === filesize($src_abs)) {
                $rel = $min;
            }
        }
    }
    return BASE_URL . $rel . '?v=' . rawurlencode(FOLIO_VERSION);
}

function site_icon_tags(): string
{
    static $html = null;
    if ($html !== null) {
        return $html;
    }
    $base = e(BASE_URL);

    $configured = trim((string) SITE_ICON);
    if ($configured !== '') {
        // An absolute URL is used as-is; anything else is treated as a path
        // inside the installation.
        $href = preg_match('#^https?://#i', $configured)
            ? $configured
            : rtrim(BASE_URL, '/') . '/' . ltrim($configured, '/');
        $ext  = strtolower(pathinfo(parse_url($href, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        $type = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'ico' => 'image/x-icon',
                 'gif' => 'image/gif', 'webp' => 'image/webp'][$ext] ?? '';
        return $html = '<link rel="icon" href="' . e($href) . '"'
            . ($type !== '' ? ' type="' . e($type) . '"' : '') . '>' . "\n";
    }

    // An icon dropped into branding/ is picked up without configuration and
    // survives upgrades, because branding/ is not a release-owned folder.
    $out = '';
    foreach (['favicon.svg' => 'image/svg+xml', 'favicon.png' => 'image/png'] as $file => $type) {
        if (is_file(__DIR__ . '/branding/' . $file)) {
            $out .= '<link rel="icon" href="' . $base . 'branding/' . $file . '" type="' . $type . '">' . "\n";
            break;
        }
    }
    if (is_file(__DIR__ . '/branding/favicon.ico')) {
        $out .= '<link rel="' . ($out === '' ? 'icon' : 'alternate icon') . '" href="' . $base . 'branding/favicon.ico">' . "\n";
    }
    if (is_file(__DIR__ . '/branding/apple-touch-icon.png')) {
        $out .= '<link rel="apple-touch-icon" href="' . $base . 'branding/apple-touch-icon.png">' . "\n";
    }
    if ($out !== '') {
        return $html = $out;
    }

    return $html = '<link rel="icon" href="' . $base . 'assets/img/favicon.svg" type="image/svg+xml">' . "\n"
        . '<link rel="alternate icon" href="' . $base . 'assets/img/favicon.ico">' . "\n";
}

function document_url(array $rec): string
{
    $slug = (string) ($rec['slug'] ?? '');
    if ($slug === '') {
        return BASE_URL;
    }
    return PRETTY_URLS
        ? rtrim(BASE_URL, '/') . '/' . rawurlencode($slug) . '/'
        : BASE_URL . '?view=' . rawurlencode($slug);
}

/**
 * Resolve a requested slug.
 *
 * Returns ['doc' => record, 'redirect' => bool]. A canonical slug renders;
 * an alias reports a redirect to the current canonical URL. Aliases always
 * point at the record's present slug rather than at whatever slug was
 * canonical when the alias was created, so a document renamed A → B → C
 * sends both A and B straight to C with no intermediate hop.
 */
function document_resolve_slug(string $slug): ?array
{
    $slug = trim($slug, '/');
    if ($slug === '' || str_contains($slug, '/')) {
        return null;   // flat namespace: a slug never contains a separator
    }
    $data = meta_documents();
    $idx  = meta_indexes($data);

    if (isset($idx['slug'][$slug])) {
        $rec = $data['documents'][$idx['slug'][$slug]] ?? null;
        return $rec ? ['doc' => $rec, 'redirect' => false] : null;
    }
    if (isset($idx['alias'][$slug])) {
        $rec = $data['documents'][$idx['alias'][$slug]] ?? null;
        // Guard against a record whose slug somehow equals the alias, which
        // would redirect a URL to itself forever.
        if ($rec && (string) ($rec['slug'] ?? '') !== $slug) {
            return ['doc' => $rec, 'redirect' => true];
        }
    }
    return null;
}

function document_for_path(string $rel): ?array
{
    $data = meta_documents();
    $idx  = meta_indexes($data);
    $id   = $idx['path'][$rel] ?? null;
    return $id !== null ? ($data['documents'][$id] ?? null) : null;
}

function meta_load(): array
{
    global $folio_meta_cache;
    if (is_array($folio_meta_cache)) {
        return $folio_meta_cache;
    }
    $source = is_file(META_FILE) ? META_FILE : LEGACY_META_FILE;
    $valid = true;
    $data = meta_decode_file($source, $valid);
    if (!$valid) {
        error_log('Folio metadata is invalid JSON: ' . $source);
        return $folio_meta_cache = [];
    }
    // Present a path-keyed view regardless of which shape is on disk. Existing
    // readers throughout the application look up metadata by relative path,
    // and a migrated store must not silently stop answering them — a
    // pdf_access lookup that quietly returns nothing would make a restricted
    // document public.
    if (meta_is_migrated($data)) {
        $flat = [];
        foreach ($data['documents'] as $rec) {
            $path = (string) ($rec['file_path'] ?? '');
            if ($path !== '') {
                $flat[$path] = $rec;
            }
        }
        $data = $flat;
    }
    return $folio_meta_cache = $data;
}

/**
 * The document store, in the identity-keyed format, whatever is on disk.
 *
 * Legacy path-keyed files are migrated in memory on read so the rest of the
 * application sees one shape. Persisting that form is a separate, deliberate
 * step (meta_migrate_now) which takes a backup first: a page view must never
 * rewrite the metadata file as a side effect.
 */
/** Forget the cached document view, after a write. */
function meta_documents_reset(): void
{
    meta_documents(true);
}

function meta_documents(bool $reset = false): array
{
    static $cache = null;
    if ($reset) {
        $cache = null;
        return [];
    }
    if (is_array($cache)) {
        return $cache;
    }
    $source = is_file(META_FILE) ? META_FILE : LEGACY_META_FILE;
    $valid = true;
    $raw = meta_decode_file($source, $valid);
    if (!$valid) {
        return $cache = ['version' => 2, 'documents' => []];
    }
    return $cache = meta_is_migrated($raw) ? $raw : meta_migrate($raw);
}

/**
 * Persist the migration, once, with a backup.
 *
 * Separate from meta_load() on purpose: a page view should never rewrite the
 * metadata file as a side effect. This runs from the admin, and refuses to
 * replace a valid store with one that does not validate.
 */
function meta_migrate_now(string &$message = '', array &$warnings = []): bool
{
    $valid = true;
    $current = meta_decode_file(is_file(META_FILE) ? META_FILE : LEGACY_META_FILE, $valid);
    if (!$valid) {
        $message = 'The metadata file could not be parsed. It has not been changed.';
        return false;
    }
    if (meta_is_migrated($current)) {
        $message = 'Already migrated.';
        return true;
    }
    $migrated = meta_migrate($current, $warnings);
    $error = '';
    if (!meta_validate($migrated, $error)) {
        $message = 'Migration produced an inconsistent store, so nothing was changed: ' . $error;
        return false;
    }
    // Keep a dated copy of the pre-migration file alongside the usual .bak.
    if (is_file(META_FILE)) {
        @copy(META_FILE, META_FILE . '.pre-1.6.' . date('Ymd-His') . '.bak');
    }
    $result = meta_update(static function (array $existing) use ($migrated) {
        return meta_is_migrated($existing) ? $existing : $migrated;
    });
    if ($result === false) {
        $message = 'The metadata file could not be written. Nothing was changed.';
        return false;
    }
    $message = 'Migrated ' . count($migrated['documents']) . ' document(s).';
    return true;
}

/** Perform a locked read-update-write transaction. Returns the new map or false. */
function meta_update(callable $mutator)
{
    global $folio_meta_cache;
    $dir = dirname(META_FILE);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return false;
    }
    if (is_link(META_LOCK_FILE)) {
        return false;
    }
    $lock = @fopen(META_LOCK_FILE, 'c+b');
    if ($lock === false || !@flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            @fclose($lock);
        }
        return false;
    }
    $source = is_file(META_FILE) ? META_FILE : LEGACY_META_FILE;
    $valid = true;
    $current = meta_decode_file($source, $valid);
    if (!$valid) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
        return false;
    }
    $next = $mutator($current);
    if (!is_array($next)) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
        return false;
    }
    $json = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ok = is_string($json) && atomic_replace_file(META_FILE, $json . "\n", 0600, true);
    if ($ok) {
        // Drop the cached views rather than assigning the raw store: readers
        // expect the path-keyed shape, and meta_documents() keeps its own
        // copy. Both are rebuilt on next use from what was actually written.
        $folio_meta_cache = null;
        meta_documents_reset();
    }
    @flock($lock, LOCK_UN);
    @fclose($lock);
    return $ok ? $next : false;
}

/* ------------------------------------------------------------------ */
/* Standalone pages (About, FAQ, and a few custom slots)               */
/* ------------------------------------------------------------------ */

define('PAGES_FILE', __DIR__ . '/data/pages.json');

/**
 * The fixed set of page slots. Two named defaults plus three free slots.
 * Slots are fixed by design: Folio stays a document library, not a page
 * builder, so there is no arbitrary page creation and no growing surface.
 *
 * 'type' selects the schema.org page type emitted for that slot.
 */
/**
 * The two pages Folio understands the meaning of.
 *
 * About and FAQ are built in because their schema.org types say something a
 * generic page cannot: an FAQ page carries its questions as structured data,
 * and search engines treat an About page as identity information. Neither can
 * be deleted, and both keep their historic addresses.
 */
function page_slots_builtin(): array
{
    return [
        'about' => ['type' => 'AboutPage', 'default_title' => 'About', 'builtin' => true],
        'faq'   => ['type' => 'FAQPage',   'default_title' => 'FAQ',   'builtin' => true],
    ];
}

/** A page slot identifier: the two built-ins, or a generated custom one. */
function page_slot_valid(string $slot): bool
{
    return isset(page_slots_builtin()[$slot])
        || (bool) preg_match('/^(page[0-9]+|p-[a-f0-9]{8})$/', $slot);
}

/**
 * Every page slot that exists, built-in first and custom ones after.
 *
 * The list used to be a fixed five. It is now read from the stored file, so a
 * library can have as many pages as it wants. Nothing else changed shape:
 * URLs, menus, the sitemap, and structured data are all still keyed by slot,
 * and an installation carrying the old page1..page3 records keeps them,
 * addresses included, because those keys are simply found in the file.
 */
function page_slots(): array
{
    static $slots = null;
    if (is_array($slots)) {
        return $slots;
    }
    $slots = page_slots_builtin();

    $stored = [];
    if (!is_link(PAGES_FILE) && is_file(PAGES_FILE)) {
        $raw  = @file_get_contents(PAGES_FILE);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($data)) {
            $stored = $data;
        }
    }
    foreach (array_keys($stored) as $slot) {
        $slot = (string) $slot;
        if (isset($slots[$slot]) || !page_slot_valid($slot)) {
            continue;
        }
        $slots[$slot] = [
            'type' => 'WebPage',
            'default_title' => (string) ($stored[$slot]['title'] ?? '') !== ''
                ? (string) $stored[$slot]['title']
                : 'Page',
            'builtin' => false,
        ];
    }
    return $slots;
}

/** An unused slot identifier for a newly added page. */
function page_slot_new(): string
{
    $existing = page_slots();
    do {
        $slot = 'p-' . bin2hex(random_bytes(4));
    } while (isset($existing[$slot]));
    return $slot;
}

/**
 * Stored page content, as slot => ['enabled','title','menu','body'].
 * Unknown or malformed data yields an empty map, so a missing or damaged
 * file simply means no pages rather than a broken site.
 */
function pages_load(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    if (is_link(PAGES_FILE) || !is_file(PAGES_FILE)) {
        return $cache = [];
    }
    $raw = @file_get_contents(PAGES_FILE);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return $cache = [];
    }
    $out = [];
    foreach (page_slots() as $slot => $_meta) {
        $rec = is_array($data[$slot] ?? null) ? $data[$slot] : [];
        $out[$slot] = [
            'enabled' => !empty($rec['enabled']),
            'title'   => (string) ($rec['title'] ?? ''),
            'menu'    => (string) ($rec['menu'] ?? ''),
            'slug'    => (string) ($rec['slug'] ?? ''),
            'body'    => (string) ($rec['body'] ?? ''),
            'seo_title' => (string) ($rec['seo_title'] ?? ''),
            'seo_desc'  => (string) ($rec['seo_desc'] ?? ''),
            'updated_at' => (int) ($rec['updated_at'] ?? 0),
        ];
    }
    return $cache = $out;
}

/** Persist the whole page map through the shared atomic transaction. */
function pages_save(array $pages, string &$error = ''): bool
{
    $previous = pages_load();
    $clean = [];
    $taken = [];
    /* The caller decides which slots survive: a slot absent from $pages is a
       page that was deleted. Built-ins are always kept so About and FAQ
       cannot be removed by a malformed or truncated form post. */
    $slots = page_slots_builtin();
    foreach (array_keys($pages) as $slot) {
        $slot = (string) $slot;
        if (!isset($slots[$slot]) && page_slot_valid($slot)) {
            $slots[$slot] = ['type' => 'WebPage', 'default_title' => 'Page', 'builtin' => false];
        }
    }
    foreach ($slots as $slot => $_meta) {
        $rec = is_array($pages[$slot] ?? null) ? $pages[$slot] : [];

        /* Pages and documents share one flat namespace, so a page slug is
           checked against documents, reserved routes, and the other pages.
           A rejected slug leaves the whole save untouched rather than
           silently keeping the old address while reporting success. */
        $wanted = slug_normalise((string) ($rec['slug'] ?? ''));
        if ($wanted !== '') {
            if ($wanted !== $slot) {
                $reason = slug_rejection_reason($wanted, meta_documents(), $slot);
                if ($reason !== '') {
                    $error = 'The URL slug for "' . $slot . '" was refused. ' . $reason;
                    return false;
                }
            }
            // A file that has never been given metadata has no stored slug,
            // but it still answers on its path-derived address. A page taking
            // that name would leave the document unreachable, so ask the
            // resolver directly rather than only consulting the catalogue.
            $already_mine = ($previous[$slot]['slug'] ?? '') === $wanted;
            if (!$already_mine && function_exists('resolve_view') && resolve_view($wanted) !== null) {
                $error = 'The URL slug "' . $wanted . '" already belongs to a document.';
                return false;
            }
            if (isset($taken[$wanted])) {
                $error = 'The URL slug "' . $wanted . '" is used by two pages at once.';
                return false;
            }
            foreach ($slots as $other => $__) {
                if ($other !== $slot && $other === $wanted) {
                    $error = 'The URL slug "' . $wanted . '" is the name of another page slot.';
                    return false;
                }
            }
            $taken[$wanted] = $slot;
        }

        $next = [
            'enabled' => !empty($rec['enabled']),
            'title'   => str_clip(trim((string) ($rec['title'] ?? '')), 120),
            'menu'    => str_clip(trim((string) ($rec['menu'] ?? '')), 40),
            'slug'    => $wanted,
            'body'    => (string) ($rec['body'] ?? ''),
            'seo_title' => str_clip(trim((string) ($rec['seo_title'] ?? '')), 60),
            'seo_desc'  => str_clip(trim((string) ($rec['seo_desc'] ?? '')), 150),
        ];
        // Only stamp slots that actually changed, so saving the form does not
        // report every page as freshly modified to search engines.
        $old = $previous[$slot] ?? [];
        $changed = ($old['enabled'] ?? null) !== $next['enabled']
            || ($old['title'] ?? '') !== $next['title']
            || ($old['menu'] ?? '') !== $next['menu']
            || ($old['slug'] ?? '') !== $next['slug']
            || ($old['body'] ?? '') !== $next['body']
            || ($old['seo_title'] ?? '') !== $next['seo_title']
            || ($old['seo_desc'] ?? '') !== $next['seo_desc'];
        $next['updated_at'] = $changed ? time() : (int) ($old['updated_at'] ?? 0);
        $clean[$slot] = $next;
    }
    $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return false;
    }
    return atomic_replace_file(PAGES_FILE, $json . "\n", 0600, true);
}

/** A page's public URL. */
/**
 * The saved slug for a page slot, or the slot's own name.
 *
 * Pages share the flat slug namespace with documents rather than hiding
 * behind a /p/ prefix: an "About" page and a certificate are both just pages
 * of the site, and there is no reason one should have a tidier address than
 * the other. Collisions are prevented by checking both when a slug is saved.
 */
function page_slug(string $slot, array $rec = []): string
{
    if ($rec === []) {
        $pages = pages_load();
        $rec = $pages[$slot] ?? [];
    }
    $saved = slug_normalise((string) ($rec['slug'] ?? ''));
    if ($saved !== '') {
        return $saved;
    }
    /* A page added from the admin has a generated slot id like "p-3f9a1c22".
       That is an internal key, not something to put in an address bar, so a
       slug is derived from the title instead and the id never surfaces. */
    if (strpos($slot, 'p-') === 0) {
        $from_title = slug_normalise(slugify((string) ($rec['title'] ?? '')));
        if ($from_title !== '') {
            return $from_title;
        }
    }
    // Nothing saved: About and FAQ keep their historic addresses, and the
    // numbered slots fall back to their slot name so an unconfigured page
    // still resolves.
    return $slot;
}

function url_page(string $slot): string
{
    $slots = page_slots();
    if (!isset($slots[$slot])) {
        return BASE_URL;
    }
    $slug = page_slug($slot);
    return PRETTY_URLS
        ? rtrim(BASE_URL, '/') . '/' . rawurlencode($slug) . '/'
        : BASE_URL . '?page=' . rawurlencode($slug);
}

/** Which slot answers to this slug, or null. Accepts the slot name too. */
function page_slot_for_slug(string $slug): ?string
{
    $slug = trim($slug, '/');
    if ($slug === '') {
        return null;
    }
    $pages = pages_load();
    foreach (page_slots() as $slot => $_meta) {
        if (page_slug($slot, $pages[$slot] ?? []) === $slug) {
            return $slot;
        }
    }
    // The slot name always resolves, so an existing /p/page1/ or ?page=page1
    // link keeps working after a slug is chosen.
    return isset(page_slots()[$slug]) ? $slug : null;
}

/** The display title for a slot, falling back to its default. */
function page_title(string $slot, array $rec): string
{
    $slots = page_slots();
    $title = trim((string) ($rec['title'] ?? ''));
    return $title !== '' ? $title : ($slots[$slot]['default_title'] ?? ucfirst($slot));
}

/** The menu label for a slot: explicit menu, else title, else default. */
function page_menu_label(string $slot, array $rec): string
{
    $menu = trim((string) ($rec['menu'] ?? ''));
    return $menu !== '' ? $menu : page_title($slot, $rec);
}

/** Enabled pages that have a body, in slot order, for the public nav. */
function pages_menu(): array
{
    $out = [];
    foreach (pages_load() as $slot => $rec) {
        if ($rec['enabled'] && trim($rec['body']) !== '') {
            $out[$slot] = $rec;
        }
    }
    return $out;
}

/**
 * The shared footer for public pages.
 *
 * Kept in one function so the four public screens cannot drift apart, and so
 * that a new page type gets the footer by calling one thing. Admin screens
 * deliberately do not use it: they are tools, not published pages, and their
 * crumb navigation already carries everything they need.
 */
function render_footer(): void
{
    $pages     = pages_menu();
    $publisher = trim((string) PUBLISHER_NAME);
    $pub_url   = trim((string) PUBLISHER_URL);
    $year      = date('Y');
    ?>
<footer class="site-footer">
    <div class="site-footer-inner">
        <p class="footer-identity">
            <span class="footer-site"><?= e(SITE_NAME) ?></span>
            <?php if ($publisher !== ''): ?>
                <span class="footer-sep">&middot;</span>
                <?php if ($pub_url !== ''): ?>
                    <a href="<?= e($pub_url) ?>" rel="noopener"><?= e($publisher) ?></a>
                <?php else: ?>
                    <?= e($publisher) ?>
                <?php endif; ?>
            <?php endif; ?>
            <span class="footer-sep">&middot;</span>
            <span class="footer-year"><?= e($year) ?></span>
        </p>
        <nav class="footer-nav" aria-label="Site">
            <a href="<?= e(BASE_URL) ?>">Library</a>
            <?php foreach ($pages as $slot => $rec): ?>
                <a href="<?= e(url_page($slot)) ?>"><?= e(page_menu_label($slot, $rec)) ?></a>
            <?php endforeach; ?>
            <?php if (SITEMAP_ENABLED && SITE_INDEXABLE): ?>
                <a href="<?= e(PRETTY_URLS ? rtrim(BASE_URL, '/') . '/sitemap.xml' : BASE_URL . '?action=sitemap') ?>">Sitemap</a>
            <?php endif; ?>
            <?php if (!is_admin() && SHOW_ADMIN_LINK): ?>
                <a href="<?= e(BASE_URL) ?>?action=login">Admin</a>
            <?php endif; ?>
        </nav>
    </div>
</footer>
<?= analytics_scripts() ?>
    <?php
}

/**
 * Supported file formats.
 *
 * Types listed here are served inline with a correct MIME type, receive a
 * detail page, and are included in the XML sitemap. Anything not listed is
 * still shown in the listing and can be downloaded, but is served as a binary
 * attachment and is left out of the sitemap.
 *
 * To support another format, add its extension and MIME type here. Formats
 * the browser cannot display will fall back to a download link.
 */
$mime_map = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
    'bmp'  => 'image/bmp',
    // Camera and scanner formats. Browsers cannot display these, so a
    // converted preview is generated when an image engine is available and
    // the original is offered for download either way.
    'tif'  => 'image/tiff',
    'tiff' => 'image/tiff',
    'heic' => 'image/heic',
    'heif' => 'image/heif',
    'avif' => 'image/avif',
    'txt'  => 'text/plain',
    'md'   => 'text/markdown',
];

/**
 * The file's real, content-sniffed MIME type, for metadata only
 * (encodingFormat / dcterms:format). Deliberately not used for routing,
 * previews, or sitemap inclusion — $mime_map above stays authoritative
 * there, so a mislabeled extension can't silently change what Folio decides
 * to serve inline versus as a download. Falls back to the extension-based
 * guess wherever Fileinfo is unavailable.
 */
function detected_mime_type(string $abs, string $ext, array $mime_map): string
{
    $fallback = $mime_map[$ext] ?? 'application/octet-stream';
    if (!extension_loaded('fileinfo') || !class_exists('finfo')) {
        return $fallback;
    }
    $finfo = @new finfo(FILEINFO_MIME_TYPE);
    $detected = $finfo !== false ? @$finfo->file($abs) : false;
    return is_string($detected) && $detected !== '' ? $detected : $fallback;
}

/** Where a hidden PDF's auto-generated blurred first-page preview is cached. */
function pdf_blur_cache_path(string $rel): string
{
    return __DIR__ . '/data/previews/' . hash('sha256', $rel) . '.jpg';
}

/** Whether Imagick can read PDFs on this server. */
function pdf_blur_available(): bool
{
    if (!extension_loaded('imagick') || !class_exists('Imagick')) {
        return false;
    }
    try {
        return in_array('PDF', (new Imagick())->queryFormats('PDF'), true);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Render page one of a PDF to a small, heavily blurred JPEG and cache it.
 *
 * Deliberately rendered at low resolution and blurred AFTER a further
 * downscale, not just a blur filter over a full-resolution page — a light
 * blur over sharp source pixels can be partially reversed with a
 * sharpening/deconvolution pass; throwing away the resolution first is a
 * more genuine loss of the underlying content. Returns false on any
 * failure; callers fall back to the manual placeholder_image or the plain
 * text notice.
 */
function pdf_blur_generate(string $abs_pdf, string $rel): bool
{
    if (!pdf_blur_available()) {
        return false;
    }
    $cache = pdf_blur_cache_path($rel);
    if (is_file($cache) && filemtime($cache) >= filemtime($abs_pdf)) {
        return true;
    }
    try {
        $img = new Imagick();
        image_apply_limits($img);
        // Poppler first: it renders PDFs directly and needs no delegate,
        // which this server may not have and which Folio does not require.
        $png = pdf_rasterise_page($abs_pdf, 400);
        if ($png !== null) {
            $img->readImage($png);
            @unlink($png);
        } elseif (PDF_ALLOW_GHOSTSCRIPT) {
            $img->setResolution(36, 36);
            $img->readImage($abs_pdf . '[0]');
        } else {
            return false;
        }
        $img->setImageFormat('jpeg');
        $img->flattenImages();
        // Downscale hard first, then blur what's left, then upscale back to
        // a viewable size — the detail lost in the downscale cannot be
        // recovered by anything applied afterwards.
        $w = max(1, (int) $img->getImageWidth());
        $img->scaleImage(max(1, (int) ($w / 8)), 0);
        $img->blurImage(6, 3);
        $img->scaleImage(min(600, $w), 0);
        $img->setImageCompressionQuality(70);
        if (!is_dir(dirname($cache)) && !@mkdir(dirname($cache), 0750, true)) {
            return false;
        }
        $ok = $img->writeImage($cache);
        $img->clear();
        return (bool) $ok;
    } catch (Throwable $e) {
        return false;
    }
}

function render_markdown(string $abs): string
{
    return render_markdown_text((string) file_get_contents($abs));
}

/** Render a Markdown string to safe HTML (raw HTML escaped, unsafe links neutralised). */
function render_markdown_text(string $text): string
{
    require_once __DIR__ . '/lib/parsedown/Parsedown.php';
    $pd = new Parsedown();
    $pd->setSafeMode(true);
    return $pd->text($text);
}

/**
 * Parse an FAQ Markdown body into schema.org Question/Answer entities.
 * Each `##` (or `###`) heading is a question; the text beneath it, up to the
 * next heading, is the answer. Returns [] when no headings are present.
 */
function faq_parse(string $body): array
{
    $lines = preg_split('/\r\n|\r|\n/', $body);
    $questions = [];
    $current = null;
    $answer = [];
    $flush = function () use (&$questions, &$current, &$answer): void {
        if ($current !== null) {
            $text = trim(implode("\n", $answer));
            if ($text !== '') {
                $questions[] = [
                    '@type' => 'Question',
                    'name' => $current,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => trim(strip_tags(render_markdown_text($text))),
                    ],
                ];
            }
        }
    };
    foreach ($lines as $line) {
        if (preg_match('/^\s{0,3}#{2,3}\s+(.+?)\s*#*\s*$/', $line, $m)) {
            $flush();
            $current = trim($m[1]);
            $answer = [];
        } elseif ($current !== null) {
            $answer[] = $line;
        }
    }
    $flush();
    return $questions;
}

/* ------------------------------------------------------------------ */
/* Structured data (schema.org JSON-LD)                                */
/* ------------------------------------------------------------------ */

/** Map a file extension onto the most specific schema.org type available. */
function schema_type(string $ext): string
{
    switch ($ext) {
        case 'pdf':
            return 'DigitalDocument';
        case 'md':
            return 'Article';
        case 'txt':
            return 'TextDigitalDocument';
        case 'png':
        case 'jpg':
        case 'jpeg':
        case 'gif':
        case 'webp':
        case 'svg':
        case 'bmp':
            return 'ImageObject';
        default:
            return 'MediaObject';
    }
}

function schema_publisher(): array
{
    if (trim((string) PUBLISHER_NAME) === '') {
        return [];
    }
    $node = [
        '@type' => PUBLISHER_TYPE,
        '@id' => BASE_URL . '#publisher',
        'name' => PUBLISHER_NAME,
    ];
    if (PUBLISHER_URL !== '') {
        $node['url'] = PUBLISHER_URL;
    }
    return $node;
}

function schema_website(): array
{
    $node = [
        '@type' => 'WebSite',
        '@id' => BASE_URL . '#website',
        'name' => SITE_NAME,
        'description' => SITE_DESCRIPTION,
        'url' => BASE_URL,
        'inLanguage' => SITE_LANGUAGE,
    ];
    if (trim((string) PUBLISHER_NAME) !== '') {
        $node['publisher'] = ['@id' => BASE_URL . '#publisher'];
    }
    return $node;
}

/** Breadcrumb trail from the library root down to $rel. */
function schema_breadcrumbs(string $rel, string $leaf_name, string $leaf_url): array
{
    $items = [[
        '@type' => 'ListItem',
        'position' => 1,
        'name' => SITE_NAME,
        'item' => BASE_URL,
    ]];
    $acc = '';
    $pos = 1;
    foreach (array_filter(explode('/', $rel)) as $part) {
        $acc = ltrim($acc . '/' . $part, '/');
        $items[] = [
            '@type' => 'ListItem',
            'position' => ++$pos,
            'name' => $part,
            'item' => url_dir($acc),
        ];
    }
    if ($leaf_name !== '') {
        $items[] = [
            '@type' => 'ListItem',
            'position' => ++$pos,
            'name' => $leaf_name,
            'item' => $leaf_url,
        ];
    }
    return [
        '@type' => 'BreadcrumbList',
        '@id' => $leaf_url . '#breadcrumb',
        'itemListElement' => $items,
    ];
}

/**
 * A single file as a fully described schema.org node.
 * $full adds properties only worth emitting on the file's own page.
 */
function schema_file(string $rel, string $abs, array $meta, array $mime_map, bool $full = false): array
{
    $ext   = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $m     = $meta[$rel] ?? [];
    $title = ($m['title'] ?? '') !== '' ? $m['title'] : pathinfo($rel, PATHINFO_FILENAME);
    $document_type = (string) ($m['document_type'] ?? '');
    $type  = $document_type !== '' ? document_type_schema_type($document_type) : schema_type($ext);
    $view  = url_view($rel);
    $raw   = url_raw_effective($rel, $m);
    // A restricted PDF's URL is never permanent metadata: viewer's signed
    // link expires, and hidden has none at all. contentUrl/associatedMedia/
    // potentialAction/DownloadAction only ever describe a public file.
    $show_pdf_url = pdf_full_access($rel, $m);
    $mtime = (int) filemtime($abs);
    $bytes = (int) filesize($abs);
    $language = (string) ($m['language'] ?? '');
    $transcript = trim((string) ($m['transcript'] ?? ''));
    $detected_mime = detected_mime_type($abs, $ext, $mime_map);

    $doc_date_meta = document_date_parse((string) ($m['doc_date'] ?? ''));

    $node = [
        '@type' => $type,
        '@id' => $view . '#file',
        'name' => $title,
        'url' => $view,
        'encodingFormat' => $detected_mime,
        'fileFormat' => $detected_mime,
        'contentSize' => human_size($bytes),
        'dateModified' => date('c', $mtime),

        'inLanguage' => $language !== '' ? $language : SITE_LANGUAGE,
        'isPartOf' => ['@id' => BASE_URL . '#website'],

        'dcterms:title' => $title,
        'dcterms:identifier' => $view,
        'dcterms:format' => $detected_mime,
        'dcterms:modified' => date('c', $mtime),
    ];

    /* The document's own date. For an archive this is the fact that matters:
       dateModified only records when the file was last touched, and
       re-uploading a 1979 certificate does not make it a 2026 document.
       Emitted only when there is one, so an undated record stays silent
       rather than publishing an empty claim. */
    if ($doc_date_meta['iso'] !== '') {
        $node['dateCreated']      = $doc_date_meta['iso'];
        $node['datePublished']    = $doc_date_meta['iso'];
        $node['temporalCoverage'] = $doc_date_meta['iso'];
        $node['dcterms:date']     = $doc_date_meta['display'];
    }
    if ($show_pdf_url) {
        $node['contentUrl'] = $raw;
    }
    if (trim((string) PUBLISHER_NAME) !== '') {
        $node['publisher'] = ['@id' => BASE_URL . '#publisher'];
    }
    if (($m['desc'] ?? '') !== '') {
        $node['description'] = $m['desc'];
        $node['dcterms:description'] = $m['desc'];
    }
    if (!empty($m['category']) || !empty($m['tags'])) {
        $subjects = [];
        if (!empty($m['category'])) {
            $node['genre'] = $m['category'];
            $subjects[] = $m['category'];
        }
        if (!empty($m['tags'])) {
            $node['keywords'] = implode(', ', $m['tags']);
            foreach ($m['tags'] as $tag) {
                $subjects[] = $tag;
            }
        }
        $node['dcterms:subject'] = array_values(array_unique($subjects));
    }
    if ($document_type !== '') {
        $node['genre'] = document_types()[$document_type] ?? $document_type;
        $node['dcterms:type'] = document_types()[$document_type] ?? $document_type;
    }
    if ($language !== '') {
        $node['dcterms:language'] = $language;
    }
    if ($transcript !== '') {
        // Never the full transcript text here — that would duplicate large
        // blocks of content already present in the server-rendered HTML.
        // The visible page is what search engines and AI crawlers read.
        $node['additionalProperty'][] = [
            '@type' => 'PropertyValue',
            'name' => 'Transcription',
            'value' => 'Available on the document page',
        ];
    }
    if ($type === 'ImageObject') {
        $node['thumbnailUrl'] = $raw;
        $node['representativeOfPage'] = true;
        $dim = @getimagesize($abs);
        if (is_array($dim)) {
            $node['width']  = ['@type' => 'QuantitativeValue', 'value' => $dim[0], 'unitCode' => 'E37'];
            $node['height'] = ['@type' => 'QuantitativeValue', 'value' => $dim[1], 'unitCode' => 'E37'];
        }
    }
    if ($type === 'Article') {
        if (trim((string) PUBLISHER_NAME) !== '') {
            $node['author'] = ['@id' => BASE_URL . '#publisher'];
        }
        $node['headline'] = $title;
    }
    if ($full) {
        $node['mainEntityOfPage'] = ['@id' => $view . '#page'];
        if ($show_pdf_url) {
            $node['associatedMedia'] = [
                '@type' => 'DataDownload',
                'contentUrl' => $raw,
                'encodingFormat' => $detected_mime,
                'contentSize' => human_size($bytes),
            ];
            $node['potentialAction'] = [
                '@type' => 'DownloadAction',
                'target' => $raw,
            ];
        }
    }
    return $node;
}

/**
 * Encode a schema.org graph for embedding inside a <script> element.
 *
 * JSON-LD sits in an HTML context, so JSON validity alone is not enough: a
 * metadata value containing a closing script tag would end the element and
 * everything after it would be parsed as markup. The HEX flags encode <, >,
 * &, ' and " as \uXXXX escapes, which JSON parsers read back identically
 * while leaving nothing the HTML parser can act on. JSON_UNESCAPED_SLASHES
 * is deliberately absent for the same reason.
 */
function schema_emit(array $graph): string
{
    $graph = array_values(array_filter($graph, static function ($node): bool {
        return is_array($node) && $node !== [];
    }));
    $json = json_encode(
        [
            '@context' => [
                '@vocab' => 'https://schema.org/',
                'dcterms' => 'http://purl.org/dc/terms/',
            ],
            '@graph' => $graph,
        ],
        JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
    );
    if (!is_string($json)) {
        // Malformed input (invalid UTF-8, recursion) must not emit a broken
        // element. An empty graph is valid and inert.
        return '{"@context":"https://schema.org","@graph":[]}';
    }
    return $json;
}

/**
 * Every file in the library, at any depth, with its metadata attached.
 * Used by category pages, the category register, and the sitemap.
 */
function index_all_files(array $mime_map): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $meta = meta_load();
    $out = [];
    $visited = [];
    $walk = function (string $abs, string $rel) use (&$walk, &$out, &$visited, $meta, $mime_map): void {
        $real_dir = safe_entry_realpath($abs);
        if ($real_dir === null || !is_dir($real_dir) || isset($visited[$real_dir])) {
            return;
        }
        $visited[$real_dir] = true;
        foreach ((array) scandir($real_dir) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '' || $entry[0] === '.') {
                continue;
            }
            $rel_e = ltrim($rel . '/' . $entry, '/');
            if (is_excluded($entry, $rel_e)) {
                continue;
            }
            $abs_e = safe_entry_realpath($real_dir . DIRECTORY_SEPARATOR . $entry);
            if ($abs_e === null) {
                continue;
            }
            if (is_dir($abs_e)) {
                $walk($abs_e, $rel_e);
                continue;
            }
            if (!is_file($abs_e)) {
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            $m = $meta[$rel_e] ?? [];
            $pdf_access = pdf_access_of($m);
            // A "hidden" PDF has nothing to hotlink to or preview by any path.
            $previewable = isset($mime_map[$ext]) && $ext !== 'txt'
                && !($ext === 'pdf' && $pdf_access === 'hidden' && pdf_access_enforced());
            $out[$rel_e] = [
                'name' => $entry,
                'rel' => $rel_e,
                'abs' => $abs_e,
                'ext' => $ext,
                'dir' => $rel,
                'size' => human_size((int) filesize($abs_e)),
                'mtime' => (int) filemtime($abs_e),
                // The page changes when either the file or its metadata does.
                // Records written before updated_at existed fall back to the
                // file time, so legacy metadata keeps working untouched.
                'lastmod' => max((int) filemtime($abs_e), (int) ($m['updated_at'] ?? 0)),
                'title' => $m['title'] ?? '',
                'desc' => $m['desc'] ?? '',
                'category' => $m['category'] ?? '',
                'tags' => $m['tags'] ?? [],
                'document_type' => $m['document_type'] ?? '',

                'doc_date' => $m['doc_date'] ?? '',
                'seo_title' => $m['seo_title'] ?? '',
                'seo_desc' => $m['seo_desc'] ?? '',
                'language' => $m['language'] ?? '',
                'pdf_access' => $pdf_access,
                // Deliberately not the transcript text itself here — this
                // index is loaded in bulk for the sitemap and category
                // pages. Just enough to know whether one exists.
                'has_transcript' => trim((string) ($m['transcript'] ?? '')) !== '',
                'previewable' => $previewable,
                'kind' => $ext === 'pdf' ? 'pdf' : ($ext === 'md' ? 'md'
                    : (isset($mime_map[$ext]) && $ext !== 'txt' ? 'image' : 'other')),
                'view' => url_view($rel_e),
                'hotlink' => $previewable ? url_raw_effective($rel_e, $m) : '',
                'render' => url_render($rel_e),
            ];
        }
    };
    $base = realpath(BASE_DIR);
    if ($base !== false) {
        $walk($base, '');
    }
    ksort($out);
    return $cache = $out;
}

/** Category name => file count, across the whole library. */
function category_register(array $all): array
{
    $reg = [];
    foreach ($all as $f) {
        if ($f['category'] !== '') {
            $reg[$f['category']] = ($reg[$f['category']] ?? 0) + 1;
        }
    }
    ksort($reg, SORT_NATURAL | SORT_FLAG_CASE);
    return $reg;
}

/* ------------------------------------------------------------------ */
/* Rewrite preflight probe (admin only).                                */
/*                                                                      */
/* Fetched by the Crawlers screen before enabling PRETTY_URLS. If the   */
/* server rewrote /__probe__/ through index.php, SFM_ROUTE will contain */
/* '__probe__' and we return JSON that reveals it. Anonymous requests   */
/* get redirected to the login page — no recon for the internet.         */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'rewrite_probe') {
    if (!is_admin()) {
        header('Location: ' . BASE_URL . '?action=login');
        exit;
    }
    header('Content-Type: application/json');
    exit(json_encode([
        'ok' => true,
        'route' => (string) ($_SERVER['SFM_ROUTE'] ?? $_SERVER['REDIRECT_SFM_ROUTE'] ?? ''),
    ]));
}

/* ------------------------------------------------------------------ */
/* IndexNow key file.                                                   */
/*                                                                      */
/* Served publicly at /{key}.txt containing exactly the key, so         */
/* IndexNow-compatible engines (Bing, Yandex, Naver, and others) can    */
/* verify ownership. Timing-safe comparison; unknown keys 404.           */
/* ------------------------------------------------------------------ */
if (isset($_GET['indexnow_key'])) {
    $requested = (string) $_GET['indexnow_key'];
    if (INDEXNOW_KEY !== '' && hash_equals(INDEXNOW_KEY, $requested)) {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
        exit(INDEXNOW_KEY);
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Not found');
}

/**
 * Every public address worth telling a search engine about.
 *
 * This is the union of both sitemaps. Earlier releases submitted only the
 * library root and the document pages, so a new category, a new standalone
 * page, a new folder, and the PDF files themselves were all announced by the
 * sitemaps but never pushed — the one mechanism that delivers immediately was
 * the one missing most of the site.
 *
 * Built from the same sources the sitemaps use, in the same order, so the
 * three cannot drift apart.
 */
function indexnow_url_list(array $mime_map): array
{
    $all  = index_all_files($mime_map);
    $urls = [BASE_URL];

    // Document pages, and the folders they sit in.
    $dirs = [];
    foreach ($all as $f) {
        $urls[] = $f['view'];
        $dir = (string) $f['dir'];
        while ($dir !== '') {
            $dirs[$dir] = true;
            $dir = dirname($dir);
            $dir = $dir === '.' ? '' : str_replace('\\', '/', $dir);
        }
    }
    foreach (array_keys($dirs) as $dir) {
        $urls[] = url_dir((string) $dir);
    }

    // Category archives.
    foreach (array_keys(category_register($all)) as $cat_name) {
        $urls[] = url_category((string) $cat_name);
    }

    // Standalone pages.
    foreach (pages_menu() as $mslot => $_mrec) {
        $urls[] = url_page((string) $mslot);
    }

    // The PDF files themselves, matching the document sitemap's rule.
    foreach ($all as $f) {
        if (strtolower((string) $f['ext']) !== 'pdf') {
            continue;
        }
        $rel = (string) $f['rel'];
        $abs = resolve_path($rel);
        if ($abs !== null && is_file($abs)) {
            $urls[] = url_raw($rel);
        }
    }

    return array_values(array_unique($urls));
}

/* ------------------------------------------------------------------ */
/* Crawlers (admin only): sitemap, llms.txt, indexability, robots      */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'crawlers') {
    if (!is_admin()) {
        http_response_code(403);
        header('Location: ' . BASE_URL);
        exit;
    }
    $notice = '';
    $error  = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_valid()) {
            $error = 'Security token expired. Reload the page and try again.';
        } else {
            $op = (string) ($_POST['op'] ?? 'save');

            if ($op === 'indexnow_generate') {
                $key = bin2hex(random_bytes(16));
                if (settings_store(['INDEXNOW_KEY' => $key])) {
                    header('Location: ' . BASE_URL . '?action=crawlers&saved=1&indexnow=generated');
                    exit;
                }
                $error = 'Could not save the IndexNow key. Check that data/ is writable.';

            } elseif ($op === 'indexnow_clear') {
                if (settings_store(['INDEXNOW_KEY' => ''])) {
                    header('Location: ' . BASE_URL . '?action=crawlers&saved=1&indexnow=cleared');
                    exit;
                }
                $error = 'Could not clear the IndexNow key.';

            } elseif ($op === 'indexnow_submit') {
                if (INDEXNOW_KEY === '') {
                    $error = 'Generate an IndexNow key first.';
                } elseif (!SITE_INDEXABLE) {
                    $error = 'The site is marked non-indexable, so URLs are not being submitted.';
                } else {
                    $host = (string) parse_url(BASE_URL, PHP_URL_HOST);
                    $urls = indexnow_url_list($mime_map);

                    /* IndexNow accepts at most 10,000 URLs per request, so a
                       large library must be split. Each batch is reported on
                       independently: one failure must not be described as a
                       whole-library success, or the reverse. */
                    $batches   = array_chunk($urls, INDEXNOW_MAX_URLS_PER_REQUEST);
                    $sent      = 0;
                    $failed    = 0;
                    $unreached = 0;
                    $last_code = 0;
                    foreach ($batches as $batch) {
                        $payload = json_encode([
                            'host' => $host,
                            'key' => INDEXNOW_KEY,
                            'keyLocation' => rtrim(BASE_URL, '/') . '/' . INDEXNOW_KEY . '.txt',
                            'urlList' => $batch,
                        ]);
                        $ctx = stream_context_create(['http' => [
                            'method' => 'POST',
                            'header' => "Content-Type: application/json\r\n",
                            'content' => $payload,
                            'timeout' => 10,
                            'ignore_errors' => true,
                        ]]);
                        $http_response_header = [];
                        $result = @file_get_contents('https://api.indexnow.org/indexnow', false, $ctx);
                        $code = 0;
                        if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
                            $code = (int) $m[1];
                        }
                        if ($result === false && $code === 0) {
                            $unreached++;
                        } elseif ($code >= 200 && $code < 300) {
                            $sent += count($batch);
                        } else {
                            $failed++;
                            $last_code = $code;
                        }
                    }

                    $batch_count = count($batches);
                    $suffix = $batch_count > 1 ? ' across ' . $batch_count . ' batches' : '';
                    if ($unreached === $batch_count) {
                        $error = 'Could not reach IndexNow. Your host may block outbound HTTPS.';
                    } elseif ($failed === 0 && $unreached === 0) {
                        $notice = 'Submitted ' . $sent . ' URLs to IndexNow' . $suffix . '.';
                    } else {
                        $error = 'Submitted ' . $sent . ' of ' . count($urls) . ' URLs' . $suffix . '. '
                            . ($failed > 0 ? $failed . ' batch(es) were rejected (last HTTP ' . $last_code . '). ' : '')
                            . ($unreached > 0 ? $unreached . ' batch(es) could not reach IndexNow. ' : '')
                            . 'Confirm the key file at the link above returns your key as plain text, then try again.';
                    }
                }

            } elseif ($op === 'toggle_pretty_urls') {
                $probe = (string) ($_POST['probe_result'] ?? '');
                if ($probe !== 'ok') {
                    $error = 'The rewrite preflight did not succeed. Clean URLs will not be enabled.';
                } elseif (settings_store(['PRETTY_URLS' => true])) {
                    header('Location: ' . BASE_URL . '?action=crawlers&saved=1');
                    exit;
                } else {
                    $error = 'Could not save. Check that data/ is writable.';
                }

            } elseif ($op === 'disable_pretty_urls') {
                if (settings_store(['PRETTY_URLS' => false])) {
                    header('Location: ' . BASE_URL . '?action=crawlers&saved=1');
                    exit;
                }
                $error = 'Could not save. Check that data/ is writable.';

            } elseif ($op === 'confirm_pdf_gate') {
                if (FOLIO_URL_SIGNING_KEY === '') {
                    $error = 'Set FOLIO_URL_SIGNING_KEY in config.php before confirming this.';
                } elseif (!pdf_gate_ensure_probe_file()) {
                    $error = 'Could not create the probe file. Check that ' . e(UPLOADS_DIRNAME) . '/ is writable.';
                } else {
                    // Prefer verifying independently, the same way the
                    // IndexNow submission above makes its own outbound
                    // request, rather than only trusting what the browser
                    // reports — this flag decides whether real access
                    // control is enforced, so it is worth the extra request.
                    $probe_url = rtrim(BASE_URL, '/') . '/' . rawurlencode(UPLOADS_DIRNAME) . '/' . FOLIO_PDF_PROBE_NAME;
                    ensure_session_started();
                    $ctx = stream_context_create(['http' => [
                        'method' => 'GET',
                        'header' => 'Cookie: ' . FOLIO_COOKIE_NAME . '=' . rawurlencode((string) session_id()) . "\r\n",
                        'timeout' => 8,
                        'ignore_errors' => true,
                    ]]);
                    $result = @file_get_contents($probe_url, false, $ctx);
                    $decoded = $result !== false ? json_decode($result, true) : null;
                    $server_verified = is_array($decoded) && !empty($decoded['ok']) && ($decoded['gate'] ?? '') === 'pdf';
                    $client_probe_ok = (string) ($_POST['probe_result'] ?? '') === 'ok';

                    if ($server_verified && settings_store(['PDF_GATE_CONFIRMED' => true])) {
                        header('Location: ' . BASE_URL . '?action=crawlers&saved=1&pdfgate=confirmed');
                        exit;
                    } elseif ($result === false && $client_probe_ok && settings_store(['PDF_GATE_CONFIRMED' => true])) {
                        // Could not reach the server's own public URL — some
                        // hosts block outbound HTTP entirely, the same
                        // failure mode IndexNow submission already handles
                        // above. Fall back to the browser's own successful
                        // probe rather than blocking the feature outright.
                        header('Location: ' . BASE_URL . '?action=crawlers&saved=1&pdfgate=confirmed_unverified');
                        exit;
                    } else {
                        $error = 'Could not confirm that PDF requests reach the raw action. '
                            . 'Check that the PDF rewrite rule in .htaccess is present and mod_rewrite is active.';
                    }
                }

            } elseif ($op === 'disable_pdf_gate') {
                if (settings_store(['PDF_GATE_CONFIRMED' => false])) {
                    header('Location: ' . BASE_URL . '?action=crawlers&saved=1');
                    exit;
                }
                $error = 'Could not save. Check that data/ is writable.';

            } else {
                $intro = trim((string) ($_POST['llms_intro'] ?? ''));
                if (strlen($intro) > 1000) {
                    $error = 'The llms.txt introduction must be at most 1000 characters.';
                } elseif (settings_store([
                    'SITEMAP_ENABLED' => !empty($_POST['sitemap_enabled']),
                    'LLMS_ENABLED' => !empty($_POST['llms_enabled']),
                    'LLMS_INTRO' => $intro,
                    'SITE_INDEXABLE' => !empty($_POST['site_indexable']),
                ])) {
                    header('Location: ' . BASE_URL . '?action=crawlers&saved=1');
                    exit;
                } else {
                    $error = 'Could not write to data/. Check that the folder is writable.';
                }
            }
        }
    }
    if (isset($_GET['saved'])) {
        $notice = 'Saved. Changes apply immediately.';
        if (($_GET['indexnow'] ?? '') === 'generated') {
            $notice = 'IndexNow key generated. Verify by opening the key file link below in a browser.';
        } elseif (($_GET['indexnow'] ?? '') === 'cleared') {
            $notice = 'IndexNow key removed.';
        } elseif (($_GET['pdfgate'] ?? '') === 'confirmed') {
            $notice = 'Confirmed: PDF requests reach the raw action on this server. "Viewer" and "hidden" pdf_access are now enforced.';
        } elseif (($_GET['pdfgate'] ?? '') === 'confirmed_unverified') {
            $notice = 'Enabled based on your browser\'s successful test — this server could not reach its own public URL to verify independently (outbound HTTP may be blocked here). "Viewer" and "hidden" pdf_access are now enforced.';
        }
    }

    $sitemap_url = PRETTY_URLS ? rtrim(BASE_URL, '/') . '/sitemap.xml' : BASE_URL . '?action=sitemap';
    $llms_url    = PRETTY_URLS ? rtrim(BASE_URL, '/') . '/llms.txt' : BASE_URL . '?action=llms';

    /* Sitemap preview: URL count and a small sample. */
    $all_indexed    = index_all_files($mime_map);
    $sitemap_count  = count($all_indexed) + 1; // +1 for the library root
    $sitemap_sample = array_slice(array_map(function ($f) { return $f['view']; }, $all_indexed), 0, 8);

    /* The document sitemap lists the files themselves rather than the pages
       describing them. Counted with the same rule that endpoint applies, so
       the number shown here cannot drift from what is actually served. */
    $pdf_sitemap_url   = url_sitemap_pdf();
    $pdf_sitemap_count = 0;
    foreach ($all_indexed as $f) {
        if (strtolower((string) $f['ext']) !== 'pdf') {
            continue;
        }
        $pdf_abs = resolve_path((string) $f['rel']);
        if ($pdf_abs !== null && is_file($pdf_abs)) {
            $pdf_sitemap_count++;
        }
    }

    /* IndexNow key file URL, if a key exists. */
    $indexnow_key_url = INDEXNOW_KEY !== ''
        ? (PRETTY_URLS ? rtrim(BASE_URL, '/') . '/' . INDEXNOW_KEY . '.txt'
                       : BASE_URL . '?indexnow_key=' . INDEXNOW_KEY)
        : '';

    /* Rewrite preflight target: /__probe__/?action=rewrite_probe.
       If mod_rewrite routes /__probe__/ through index.php, SFM_ROUTE arrives
       and the JSON handler returns 'ok'. Otherwise the fetch fails. */
    $probe_url = rtrim(BASE_URL, '/') . '/__probe__/';

    $robots  = "User-agent: *\n";
    $robots .= SITE_INDEXABLE ? "Allow: /\n" : "Disallow: " . parse_url(BASE_URL, PHP_URL_PATH) . "\n";
    $robots .= "\n";
    if (SITEMAP_ENABLED && SITE_INDEXABLE) {
        $robots .= 'Sitemap: ' . $sitemap_url . "\n";
        // Announced separately so crawlers find the documents themselves, not
        // only the pages describing them.
        $robots .= 'Sitemap: ' . url_sitemap_pdf() . "\n";
    }

    $writable = is_dir(dirname(SETTINGS_FILE)) ? is_writable(dirname(SETTINGS_FILE)) : is_writable(__DIR__);
    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Crawlers &ndash; <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
</head>
<body>
<header class="topbar">
    <h1><a class="site-home" href="<?= e(BASE_URL) ?>"><?= e(SITE_NAME) ?></a></h1>
    <span class="running-head">Crawlers</span>
    <nav class="crumbs">
        <a href="<?= e(BASE_URL) ?>?action=settings">Settings</a>
        <span class="sep">/</span>
        <a href="<?= e(BASE_URL) ?>?action=diagnostics">Diagnostics</a>
        <span class="sep">/</span>
        <a href="<?= e(BASE_URL) ?>">Back to the library</a>
    </nav>
</header>
<main class="detail">
    <?php if ($notice !== ''): ?><p class="msg msg-ok"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="msg msg-bad"><?= e($error) ?></p><?php endif; ?>
    <?php if (!$writable): ?>
        <p class="msg msg-bad">The <code>data/</code> folder is not writable, so these settings cannot be saved.</p>
    <?php endif; ?>

    <h2 class="detail-title">Crawler controls</h2>
    <form method="post" class="stack-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label class="check-row">
            <input type="checkbox" name="site_indexable" value="1" <?= SITE_INDEXABLE ? 'checked' : '' ?>>
            Allow search engines to index the library
        </label>
        <p class="field-note">Off marks every public page <code>noindex, nofollow</code>. Use while preparing a library that is not ready to be found.</p>

        <label class="check-row">
            <input type="checkbox" name="sitemap_enabled" value="1" <?= SITEMAP_ENABLED ? 'checked' : '' ?>>
            Serve the XML sitemap
        </label>
        <p class="field-note">Currently at <a href="<?= e($sitemap_url) ?>"><?= e($sitemap_url) ?></a><?= SITEMAP_ENABLED ? '' : ' (disabled, returns 404)' ?>.</p>

        <label class="check-row">
            <input type="checkbox" name="llms_enabled" value="1" <?= LLMS_ENABLED ? 'checked' : '' ?>>
            Serve llms.txt for AI crawlers
        </label>
        <p class="field-note">A curated Markdown map of the library, built from your titles, descriptions, and categories. Currently at <a href="<?= e($llms_url) ?>"><?= e($llms_url) ?></a><?= LLMS_ENABLED ? '' : ' (disabled, returns 404)' ?>.</p>

        <label for="c-intro">llms.txt introduction (optional)</label>
        <textarea id="c-intro" name="llms_intro" rows="3" maxlength="1000" placeholder="A paragraph of context for AI systems reading the library."><?= e(LLMS_INTRO) ?></textarea>

        <div><button type="submit" class="btn">Save</button></div>
    </form>

    <h2 class="detail-title">Clean URLs</h2>
    <p class="detail-desc">
        Clean URLs turn <code>?view=paper</code> into <code>/paper/</code>. They require the rewrite block in <code>.htaccess</code> to be uncommented and <code>mod_rewrite</code> to be active. Folio will not enable them until it can verify both.
    </p>
    <?php if (PRETTY_URLS): ?>
        <p class="field-note">Clean URLs are <strong>enabled</strong>.</p>
        <form method="post" class="stack-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="disable_pretty_urls">
            <div><button type="submit" class="btn btn-ghost">Disable clean URLs</button></div>
        </form>
    <?php else: ?>
        <p class="field-note">Before enabling, uncomment the rewrite block at the bottom of your <code>.htaccess</code>. Then click <strong>Test rewrite</strong>.</p>
        <div id="rewrite-preflight" data-probe="<?= e($probe_url) ?>?action=rewrite_probe">
            <button type="button" class="btn" id="rewrite-test-btn">Test rewrite</button>
            <p class="field-note rewrite-result" id="rewrite-result"></p>
            <form method="post" class="stack-form rewrite-enable-form" id="pretty-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="op" value="toggle_pretty_urls">
                <input type="hidden" name="probe_result" value="">
                <div><button type="submit" class="btn">Enable clean URLs</button></div>
            </form>
        </div>
    <?php endif; ?>

    <h2 class="detail-title">PDF access control</h2>
    <p class="detail-desc">
        Documents with <code>pdf_access</code> set to <strong>Viewer</strong> or <strong>Hidden</strong> in the inline
        editor are only actually restricted once this preflight is confirmed — it proves that requests to a real PDF
        under <code>uploads/</code> reach Folio's <code>raw</code> action instead of being served directly by the
        webserver. Until then, every PDF behaves as <strong>Public</strong> regardless of what is set on it, and a
        note on each file's editor says so.
    </p>
    <?php if (FOLIO_URL_SIGNING_KEY === ''): ?>
        <p class="msg msg-bad">
            <code>FOLIO_URL_SIGNING_KEY</code> is not set in <code>config.php</code>, so PDF access
            control cannot be enforced yet.
        </p>
        <p class="field-note">
            Here is a freshly generated key. Copy the whole line into <code>config.php</code>, then
            reload this page. A new one is offered on every visit until the setting is in place, so
            take this one and use it.
        </p>
        <pre class="key-offer"><code>define('FOLIO_URL_SIGNING_KEY', '<?= e(bin2hex(random_bytes(32))) ?>');</code></pre>
        <p class="field-note">
            Folio does not write this into <code>config.php</code> for you on purpose: that file
            stays one the application cannot modify, which is what stops a future flaw from
            rewriting Folio's own configuration. Use a different value from
            <code>FOLIO_AUTH_PEPPER</code> &mdash; they protect different things, and reusing one
            weakens both. Changing this key later invalidates any signed link already shared.
        </p>
    <?php elseif (PDF_GATE_CONFIRMED): ?>
        <p class="field-note">PDF access control is <strong>confirmed and enforced</strong>.</p>
        <form method="post" class="stack-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="disable_pdf_gate">
            <div><button type="submit" class="btn btn-ghost">Disable enforcement</button></div>
        </form>
        <p class="field-note">Disabling makes every PDF behave as Public again, immediately, without touching any file's stored <code>pdf_access</code> value.</p>
    <?php else: ?>
        <p class="field-note">Click <strong>Test PDF routing</strong> to check, then confirm.</p>
        <div id="pdf-gate-preflight" data-probe="<?= e(rtrim(BASE_URL, '/')) ?>/<?= e(rawurlencode(UPLOADS_DIRNAME)) ?>/<?= e(FOLIO_PDF_PROBE_NAME) ?>">
            <button type="button" class="btn" id="pdf-gate-test-btn">Test PDF routing</button>
            <p class="field-note rewrite-result pdf-gate-result" id="pdf-gate-result"></p>
            <form method="post" class="stack-form rewrite-enable-form pdf-gate-enable-form" id="pdf-gate-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="op" value="confirm_pdf_gate">
                <input type="hidden" name="probe_result" value="">
                <div><button type="submit" class="btn">Confirm and enforce</button></div>
            </form>
        </div>
    <?php endif; ?>

    <h2 class="detail-title">Sitemap preview</h2>
    <p class="detail-desc">Folio publishes two sitemaps, and the <code>robots.txt</code> below announces both.</p>
    <ul class="sitemap-list">
        <li>
            <a href="<?= e($sitemap_url) ?>">Page sitemap</a>
            &mdash; <strong><?= (int) $sitemap_count ?></strong> URL<?= $sitemap_count === 1 ? '' : 's' ?>:
            the library, its folders, its categories, and a page for every document.
        </li>
        <li>
            <a href="<?= e($pdf_sitemap_url) ?>">Document sitemap</a>
            &mdash; <strong><?= (int) $pdf_sitemap_count ?></strong> PDF<?= $pdf_sitemap_count === 1 ? '' : 's' ?>:
            the files themselves, so a search engine indexes what is inside them rather than
            only the pages describing them.
        </li>
    </ul>
    <?php if ($sitemap_sample): ?>
        <p class="detail-desc">First few entries:</p>
        <pre class="hash-out"><code><?php foreach ($sitemap_sample as $u) { echo e($u) . "\n"; } ?></code></pre>
    <?php endif; ?>

    <h2 class="detail-title">Notify search engines</h2>
    <p class="detail-desc">
        Anonymous sitemap &ldquo;ping&rdquo; endpoints no longer exist. Microsoft retired Bing's in
        May 2022 (it now answers <code>410 Gone</code>) and Google retired its own in 2023. Folio
        therefore offers no ping button: it would report success while doing nothing.
    </p>
    <p class="detail-desc">
        The methods that do work are the sitemap reference in <code>robots.txt</code> below, which every
        crawler reads; <a href="https://www.bing.com/webmasters/sitemaps" rel="noopener noreferrer" target="_blank">Bing
        Webmaster Tools</a> and Google Search Console for one-off manual submission; and IndexNow below
        for immediate push notification.
    </p>

    <h2 class="detail-title">IndexNow</h2>
    <p class="field-note">
        Everything in both sitemaps is submitted: the library, its folders, its categories, every
        document page, every standalone page, and the PDF files themselves.
    </p>
    <p class="detail-desc">
        IndexNow is a shared protocol (Bing, Yandex, Naver, and others) that pushes URLs to search engines
        instantly instead of waiting for a crawl. Ownership is proven by hosting a text file at your domain
        that contains your key.
    </p>
    <?php if (INDEXNOW_KEY === ''): ?>
        <form method="post" class="stack-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="indexnow_generate">
            <div><button type="submit" class="btn">Generate IndexNow key</button></div>
        </form>
    <?php else: ?>
        <p class="detail-desc">Key file: <a href="<?= e($indexnow_key_url) ?>" target="_blank" rel="noopener"><?= e($indexnow_key_url) ?></a></p>
        <p class="field-note">Open that link and confirm it returns your key as plain text before submitting URLs.</p>
        <div class="ping-row">
            <form method="post" class="inline-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="op" value="indexnow_submit">
                <button type="submit" class="btn"<?= SITE_INDEXABLE ? '' : ' disabled' ?>>Submit <?= (int) count(indexnow_url_list($mime_map)) ?> URLs to IndexNow</button>
            </form>
            <form method="post" class="inline-form indexnow-clear-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="op" value="indexnow_clear">
                <button type="submit" class="btn btn-ghost">Remove key</button>
            </form>
        </div>
        <?php if (!SITE_INDEXABLE): ?>
            <p class="field-note">The site is currently marked non-indexable, so URLs cannot be submitted.</p>
        <?php endif; ?>
    <?php endif; ?>

    <h2 class="detail-title">robots.txt for your domain root</h2>
    <p class="detail-desc">Folio cannot write outside its own folder, so robots.txt stays a manual step: copy this into the file at your domain root (for example <code>https://example.com/robots.txt</code>), merging with whatever is already there. It reflects the settings above.</p>
    <pre class="hash-out"><code><?= e($robots) ?></code></pre>
    <p class="detail-facts">The shipped <code>robots.txt</code> in the package also lists AI crawlers individually if you prefer the explicit form.</p>
</main>
<script src="<?= e(asset_url('assets/js/admin.js')) ?>" defer></script>
</body>
</html>
    <?php
    exit;
}

/* ------------------------------------------------------------------ */
/* Settings (admin only)                                               */
/* ------------------------------------------------------------------ */
/* ------------------------------------------------------------------ */
/* Analytics (admin only)                                              */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'analytics') {
    if (!is_admin()) {
        http_response_code(403);
        header('Location: ' . BASE_URL);
        exit;
    }
    $notice = '';
    $error  = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_valid()) {
            $error = 'Security token expired. Reload the page and try again.';
        } else {
            $mu = trim((string) ($_POST['matomo_url'] ?? ''));
            $ms = trim((string) ($_POST['matomo_site_id'] ?? ''));
            $ga = trim((string) ($_POST['ga4_measurement_id'] ?? ''));

            if ($mu !== '' && !preg_match('#^https?://[^\s]+$#', $mu)) {
                $error = 'The Matomo URL must be a full https:// or http:// address.';
            } elseif ($mu !== '' && $ms === '') {
                $error = 'A Matomo URL needs a site ID as well.';
            } elseif ($ms !== '' && !ctype_digit($ms)) {
                $error = 'The Matomo site ID must be a whole number.';
            } elseif ($ga !== '' && !preg_match('/^G-[A-Z0-9]{4,20}$/', $ga)) {
                $error = 'The GA4 measurement ID should look like G-XXXXXXXXXX.';
            } elseif (settings_store([
                'MATOMO_URL'         => $mu,
                'MATOMO_SITE_ID'     => $ms,
                'MATOMO_HONOR_DNT'   => !empty($_POST['matomo_honor_dnt']),
                'MATOMO_COOKIELESS'  => !empty($_POST['matomo_cookieless']),
                'GA4_MEASUREMENT_ID' => $ga,
                'GA4_ANONYMIZE_IP'   => !empty($_POST['ga4_anonymize_ip']),
                'ANALYTICS_ADMIN'    => !empty($_POST['analytics_admin']),
            ])) {
                header('Location: ' . BASE_URL . '?action=analytics&saved=1');
                exit;
            } else {
                $error = 'Could not write to data/. Check that the folder is writable.';
            }
        }
    }
    if (isset($_GET['saved'])) {
        $notice = 'Analytics settings saved.';
    }

    $writable = is_dir(dirname(SETTINGS_FILE)) ? is_writable(dirname(SETTINGS_FILE)) : is_writable(__DIR__);
    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Analytics &ndash; <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
</head>
<body>
<header class="topbar">
    <h1><a class="site-home" href="<?= e(BASE_URL) ?>"><?= e(SITE_NAME) ?></a></h1>
    <span class="running-head">Analytics</span>
    <nav class="crumbs">
        <a href="<?= e(BASE_URL) ?>?action=settings">Settings</a>
        <span class="sep">/</span>
        <a href="<?= e(BASE_URL) ?>">Back to the library</a>
    </nav>
</header>
<main class="detail">
    <?php if ($notice !== ''): ?><p class="msg msg-ok"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="msg msg-bad"><?= e($error) ?></p><?php endif; ?>
    <?php if (!$writable): ?>
        <p class="msg msg-bad">The <code>data/</code> folder is not writable, so these settings cannot be saved.</p>
    <?php endif; ?>

    <p class="detail-desc">Folio stores no visit data of its own: no IP addresses, no geolocation, no logs. Both providers below are external, and you read the reports in their dashboards. Leave both blank to run with no analytics at all.</p>

    <form method="post" class="stack-form settings-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <h2 class="detail-title">Matomo</h2>
        <p class="detail-desc">Self-hosted, so visit data never leaves your own infrastructure.</p>

        <label for="a-mu">Matomo URL</label>
        <input type="text" id="a-mu" name="matomo_url" maxlength="200" placeholder="https://analytics.example.com/" value="<?= e((string) MATOMO_URL) ?>">
        <p class="field-note">The base address of your Matomo installation, with a trailing slash.</p>

        <label for="a-ms">Site ID</label>
        <input type="text" id="a-ms" name="matomo_site_id" maxlength="10" placeholder="1" value="<?= e((string) MATOMO_SITE_ID) ?>">

        <label class="check-row">
            <input type="checkbox" name="matomo_honor_dnt" value="1" <?= MATOMO_HONOR_DNT ? 'checked' : '' ?>>
            Honour Do Not Track
        </label>
        <label class="check-row">
            <input type="checkbox" name="matomo_cookieless" value="1" <?= MATOMO_COOKIELESS ? 'checked' : '' ?>>
            Cookieless tracking
        </label>
        <p class="field-note">Cookieless mode removes the consent-banner requirement in most jurisdictions, at some cost to returning-visitor accuracy.</p>

        <h2 class="detail-title">Google Analytics 4</h2>
        <p class="detail-desc">Convenient if you already use it, though it sends visit data to Google and generally obliges you to show a cookie-consent banner.</p>

        <label for="a-ga">Measurement ID</label>
        <input type="text" id="a-ga" name="ga4_measurement_id" maxlength="20" placeholder="G-XXXXXXXXXX" value="<?= e((string) GA4_MEASUREMENT_ID) ?>">

        <label class="check-row">
            <input type="checkbox" name="ga4_anonymize_ip" value="1" <?= GA4_ANONYMIZE_IP ? 'checked' : '' ?>>
            Anonymise IP addresses
        </label>

        <h2 class="detail-title">Scope</h2>
        <label class="check-row">
            <input type="checkbox" name="analytics_admin" value="1" <?= ANALYTICS_ADMIN ? 'checked' : '' ?>>
            Track admin sessions too
        </label>
        <p class="field-note">Off by default, so your own visits do not distort the reports.</p>

        <div><button type="submit" class="btn">Save analytics settings</button></div>
    </form>

    <p class="detail-facts">Trackers load from third-party origins. Folio widens its Content-Security-Policy to exactly the hosts a configured provider needs, and to nothing else.</p>
</main>
</body>
</html>
    <?php
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'settings') {
    if (!is_admin()) {
        http_response_code(403);
        header('Location: ' . BASE_URL);
        exit;
    }
    $notice = '';
    $error  = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_valid()) {
            $error = 'Security token expired. Reload the page and try again.';
        } else {
            $name = trim((string) ($_POST['site_name'] ?? ''));
            $desc = trim((string) ($_POST['site_description'] ?? ''));
            $ptype = (string) ($_POST['publisher_type'] ?? 'Person');
            $pname = trim((string) ($_POST['publisher_name'] ?? ''));
            $purl  = trim((string) ($_POST['publisher_url'] ?? ''));
            $lang  = trim((string) ($_POST['site_language'] ?? 'en'));

            if ($name === '' || strlen($name) > 100) {
                $error = 'The site name is required and must be at most 100 characters.';
            } elseif (strlen($desc) > 300) {
                $error = 'The description must be at most 300 characters.';
            } elseif (!in_array($ptype, ['Person', 'Organization'], true)) {
                $error = 'The publisher type must be Person or Organization.';
            } elseif ($purl !== '' && !preg_match('#^https?://#', $purl)) {
                $error = 'The publisher URL must start with http:// or https://.';
            } elseif (!preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})*$/', $lang)) {
                $error = 'The language must be a BCP 47 tag such as en or ms.';
            } else {
                $settings = [
                    'SITE_NAME' => $name,
                    'SITE_DESCRIPTION' => $desc,
                    'PUBLISHER_TYPE' => $ptype,
                    'PUBLISHER_NAME' => $pname,
                    'PUBLISHER_URL' => $purl,
                    'SITE_LANGUAGE' => $lang,
                    'SHOW_ADMIN_LINK' => !empty($_POST['show_admin_link']),
                ];
                if (settings_store($settings)) {
                    header('Location: ' . BASE_URL . '?action=settings&saved=1');
                    exit;
                }
                $error = 'Could not write to data/. Check that the folder is writable.';
            }
        }
    }
    if (isset($_GET['saved'])) {
        $notice = 'Settings saved.';
    }

    $cur = [
        'site_name' => SITE_NAME,
        'site_description' => SITE_DESCRIPTION,
        'publisher_type' => PUBLISHER_TYPE,
        'publisher_name' => PUBLISHER_NAME,
        'publisher_url' => PUBLISHER_URL,
        'site_language' => SITE_LANGUAGE,
        'show_admin_link' => SHOW_ADMIN_LINK,
    ];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') {
        foreach (['site_name', 'site_description', 'publisher_type', 'publisher_name', 'publisher_url', 'site_language'] as $k) {
            if (isset($_POST[$k])) {
                $cur[$k] = (string) $_POST[$k];
            }
        }
        $cur['show_admin_link'] = !empty($_POST['show_admin_link']);
    }
    $writable = is_dir(dirname(SETTINGS_FILE)) ? is_writable(dirname(SETTINGS_FILE)) : is_writable(__DIR__);

    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings &ndash; <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
</head>
<body>
<header class="topbar">
    <h1><a class="site-home" href="<?= e(BASE_URL) ?>"><?= e(SITE_NAME) ?></a></h1>
    <span class="running-head">Settings</span>
    <nav class="crumbs">
        <a href="<?= e(BASE_URL) ?>?action=users">Accounts</a>
        <span class="sep">/</span>
        <a href="<?= e(BASE_URL) ?>?action=docs">Docs</a>
        <span class="sep">/</span>
        <a href="<?= e(BASE_URL) ?>?action=diagnostics">Diagnostics</a>
        <span class="sep">/</span>
        <a href="<?= e(BASE_URL) ?>">Back to the library</a>
    </nav>
</header>
<main class="detail">
    <?php if ($notice !== ''): ?><p class="msg msg-ok"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="msg msg-bad"><?= e($error) ?></p><?php endif; ?>
    <?php if (!$writable): ?>
        <p class="msg msg-bad">The <code>data/</code> folder is not writable, so settings cannot be saved. Give the web server write access to it.</p>
    <?php endif; ?>

    <h2 class="detail-title">Site settings</h2>
    <p class="detail-desc">Saved settings override <code>config.php</code>. They apply immediately across the library, page titles, and structured data.</p>

    <form method="post" class="stack-form settings-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label for="s-name">Site name</label>
        <input type="text" id="s-name" name="site_name" maxlength="100" value="<?= e((string) $cur['site_name']) ?>" required>

        <label for="s-desc">Description</label>
        <input type="text" id="s-desc" name="site_description" maxlength="300" value="<?= e((string) $cur['site_description']) ?>">

        <label for="s-ptype">Publisher type</label>
        <select id="s-ptype" name="publisher_type">
            <option value="Person" <?= $cur['publisher_type'] === 'Person' ? 'selected' : '' ?>>Person</option>
            <option value="Organization" <?= $cur['publisher_type'] === 'Organization' ? 'selected' : '' ?>>Organization</option>
        </select>

        <label for="s-pname">Publisher name</label>
        <input type="text" id="s-pname" name="publisher_name" maxlength="100" value="<?= e((string) $cur['publisher_name']) ?>">

        <label for="s-purl">Publisher URL</label>
        <input type="text" id="s-purl" name="publisher_url" maxlength="200" placeholder="https://…" value="<?= e((string) $cur['publisher_url']) ?>">

        <label for="s-lang">Language (BCP 47, e.g. en or ms)</label>
        <input type="text" id="s-lang" name="site_language" maxlength="20" value="<?= e((string) $cur['site_language']) ?>">

        <label class="check-row">
            <input type="checkbox" name="show_admin_link" value="1" <?= $cur['show_admin_link'] ? 'checked' : '' ?>>
            Show the Admin link to logged-out visitors
        </label>

        <div><button type="submit" class="btn">Save settings</button></div>
    </form>

    <p class="detail-facts">Clean URLs and the uploads folder name stay in <code>config.php</code>, since changing them can take the site down and should be a deliberate file edit.</p>
</main>
</body>
</html>
    <?php
    exit;
}

/* ------------------------------------------------------------------ */
/* Accounts (admin only)                                               */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'users') {
    if (!is_admin()) {
        http_response_code(403);
        header('Location: ' . BASE_URL);
        exit;
    }
    $me = (string) ($_SESSION['sfm_user'] ?? ADMIN_USERNAME);
    $notice = '';
    $error  = '';
    $users  = users_load();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_valid()) {
            $error = 'Security token expired. Reload the page and try again.';
        } else {
            $op = (string) ($_POST['op'] ?? '');

            if ($op === 'password') {
                $current = (string) ($_POST['current'] ?? '');
                $new     = (string) ($_POST['new'] ?? '');
                $confirm = (string) ($_POST['confirm'] ?? '');
                if (!user_verify($me, $current)) {
                    $error = 'Your current password is not correct.';
                } elseif (strlen($new) < 10) {
                    $error = 'The new password must be at least 10 characters.';
                } elseif ($new !== $confirm) {
                    $error = 'The two new passwords do not match.';
                } else {
                    $users[$me]['hash'] = password_hash(pepper($new), PASSWORD_DEFAULT);
                    $users[$me]['peppered'] = FOLIO_AUTH_PEPPER !== '';
                    $users[$me]['auth_version'] = max(1, (int) ($users[$me]['auth_version'] ?? 1)) + 1;
                    if (users_save($users)) {
                        session_regenerate_id(true);
                        $_SESSION['sfm_auth_version'] = $users[$me]['auth_version'];
                        $notice = 'Your password has been changed.';
                    } else {
                        $error = 'Could not write to data/. Check that the folder is writable.';
                    }
                }

            } elseif ($op === 'add') {
                $name = trim((string) ($_POST['username'] ?? ''));
                $pw   = (string) ($_POST['password'] ?? '');
                if (!valid_username($name)) {
                    $error = 'Usernames may use letters, numbers, dot, dash, and underscore, 3 to 32 characters.';
                } elseif (isset($users[$name])) {
                    $error = 'That username already exists.';
                } elseif (strlen($pw) < 10) {
                    $error = 'The password must be at least 10 characters.';
                } else {
                    $users[$name] = ['hash' => password_hash(pepper($pw), PASSWORD_DEFAULT), 'created' => time(), 'peppered' => FOLIO_AUTH_PEPPER !== '', 'auth_version' => 1];
                    if (users_save($users)) {
                        $notice = 'Account ' . $name . ' created.';
                    } else {
                        $error = 'Could not write to data/. Check that the folder is writable.';
                    }
                }

            } elseif ($op === 'reset') {
                $name = (string) ($_POST['username'] ?? '');
                $pw   = (string) ($_POST['password'] ?? '');
                if (!isset($users[$name])) {
                    $error = 'No such account.';
                } elseif (strlen($pw) < 10) {
                    $error = 'The password must be at least 10 characters.';
                } else {
                    $users[$name]['hash'] = password_hash(pepper($pw), PASSWORD_DEFAULT);
                    $users[$name]['peppered'] = FOLIO_AUTH_PEPPER !== '';
                    $users[$name]['auth_version'] = max(1, (int) ($users[$name]['auth_version'] ?? 1)) + 1;
                    if (users_save($users)) {
                        $notice = 'Password reset for ' . $name . '.';
                    } else {
                        $error = 'Could not write to data/. Check that the folder is writable.';
                    }
                }

            } elseif ($op === 'delete') {
                $name = (string) ($_POST['username'] ?? '');
                if ($name === $me) {
                    $error = 'You cannot delete the account you are signed in with.';
                } elseif (!isset($users[$name])) {
                    $error = 'No such account.';
                } elseif (count($users) <= 1) {
                    $error = 'This is the only account. Add another before deleting it.';
                } else {
                    unset($users[$name]);
                    if (users_save($users)) {
                        $notice = 'Account ' . $name . ' deleted.';
                    } else {
                        $error = 'Could not write to data/. Check that the folder is writable.';
                    }
                }
            }
        }
        $users = users_load();
    }

    $writable = is_dir(dirname(USERS_FILE))
        ? is_writable(dirname(USERS_FILE))
        : is_writable(__DIR__);

    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Accounts &ndash; <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
</head>
<body>
<header class="topbar">
    <h1><a class="site-home" href="<?= e(BASE_URL) ?>"><?= e(SITE_NAME) ?></a></h1>
    <span class="running-head">Accounts</span>
    <nav class="crumbs">
        <a href="<?= e(BASE_URL) ?>?action=docs">Docs</a>
        <span class="sep">/</span>
        <a href="<?= e(BASE_URL) ?>?action=diagnostics">Diagnostics</a>
        <span class="sep">/</span>
        <a href="<?= e(BASE_URL) ?>">Back to the library</a>
    </nav>
</header>
<main class="detail">
    <?php if ($notice !== ''): ?><p class="msg msg-ok"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="msg msg-bad"><?= e($error) ?></p><?php endif; ?>
    <?php if (!$writable): ?>
        <p class="msg msg-bad">The <code>data/</code> folder is not writable, so account changes cannot be saved. Give the web server write access to it.</p>
    <?php endif; ?>
    <?php if (!users_in_store()): ?>
        <p class="msg">Accounts are still being read from <code>config.php</code>. The first change made here writes them to <code>data/users.php</code>, and <code>config.php</code> stops being consulted.</p>
    <?php endif; ?>

    <h2 class="detail-title">Your password</h2>
    <p class="detail-desc">Signed in as <strong><?= e($me) ?></strong>.</p>
    <form method="post" class="stack-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="password">
        <input type="password" name="current" placeholder="Current password" autocomplete="current-password" required>
        <input type="password" name="new" placeholder="New password, at least 10 characters" autocomplete="new-password" required>
        <input type="password" name="confirm" placeholder="Repeat the new password" autocomplete="new-password" required>
        <div><button type="submit" class="btn">Change password</button></div>
    </form>

    <h2 class="detail-title">Accounts</h2>
    <table class="accounts">
        <thead><tr><th>Username</th><th>Added</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $name => $u): ?>
            <tr>
                <td><?= e((string) $name) ?><?= $name === $me ? ' <span class="chip chip-mini">you</span>' : '' ?></td>
                <td><?= empty($u['created']) ? 'from config.php' : e(date('j M Y', (int) $u['created'])) ?></td>
                <td class="row-actions">
                    <?php if ($name !== $me && count($users) > 1): ?>
                    <form method="post" class="inline-form account-delete-form" data-username="<?= e((string) $name) ?>">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="op" value="delete">
                        <input type="hidden" name="username" value="<?= e((string) $name) ?>">
                        <button type="submit" class="btn-small btn-ghost">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2 class="detail-title">Add an account</h2>
    <form method="post" class="stack-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="add">
        <input type="text" name="username" placeholder="Username" autocomplete="off" required>
        <input type="password" name="password" placeholder="Password, at least 10 characters" autocomplete="new-password" required>
        <div><button type="submit" class="btn">Add account</button></div>
    </form>

    <h2 class="detail-title">Reset someone's password</h2>
    <p class="detail-desc">Use this when another account holder is locked out. Every account has full access to the library.</p>
    <form method="post" class="stack-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="reset">
        <select name="username" required>
            <?php foreach (array_keys($users) as $name): ?>
                <option value="<?= e((string) $name) ?>"><?= e((string) $name) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="password" name="password" placeholder="New password, at least 10 characters" autocomplete="new-password" required>
        <div><button type="submit" class="btn">Reset password</button></div>
    </form>
</main>
<script src="<?= e(asset_url('assets/js/admin.js')) ?>" defer></script>
</body>
</html>
    <?php
    exit;
}

/* ------------------------------------------------------------------ */
/* Documentation viewer (admin only)                                   */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'docs') {
    if (!is_admin()) {
        http_response_code(403);
        header('Location: ' . BASE_URL);
        exit;
    }
    // Whitelist: no user-supplied paths ever reach the filesystem.
    $docs = [
        'readme' => ['file' => 'readme.md', 'label' => 'Readme'],
        'install' => ['file' => 'docs/install.md', 'label' => 'Install'],
        'upgrading' => ['file' => 'docs/upgrading.md', 'label' => 'Upgrading'],
        'changelog' => ['file' => 'changelog.md', 'label' => 'Changelog'],
        'ssot' => ['file' => 'docs/ssot.md', 'label' => 'Reference'],
        'plugin-readme' => ['file' => 'readme.txt', 'label' => 'readme.txt'],
    ];
    $key = (string) ($_GET['doc'] ?? 'readme');
    if (!isset($docs[$key])) {
        $key = 'readme';
    }
    $path = __DIR__ . '/' . $docs[$key]['file'];
    $body = is_file($path)
        ? ($docs[$key]['file'] === 'readme.txt'
            ? '<pre>' . e((string) file_get_contents($path)) . '</pre>'
            : render_markdown($path))
        : '<p>That file is missing from the installation.</p>';

    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($docs[$key]['label']) ?> &ndash; <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
</head>
<body>
<header class="topbar">
    <h1><a class="site-home" href="<?= e(BASE_URL) ?>"><?= e(SITE_NAME) ?></a></h1>
    <span class="running-head">Documentation</span>
    <nav class="crumbs">
        <a href="<?= e(BASE_URL) ?>">Back to the library</a>
    </nav>
</header>
<main class="layout">
    <section class="listing">
        <div class="filter-bar">
            <div class="filter-chips">
                <?php foreach ($docs as $k => $d): ?>
                    <a class="chip chip-cat<?= $k === $key ? ' chip-active' : '' ?>" href="<?= e(BASE_URL) ?>?action=docs&amp;doc=<?= e($k) ?>"><?= e($d['label']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="md-content"><?= $body ?></div>
    </section>
</main>
</body>
</html>
    <?php
    exit;
}

/* ------------------------------------------------------------------ */
/* Pages editor (admin only)                                           */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'pages') {
    if (!is_admin()) {
        http_response_code(403);
        header('Location: ' . BASE_URL);
        exit;
    }
    $notice = '';
    $error  = '';
    $writable = is_dir(__DIR__ . '/data') && is_writable(__DIR__ . '/data');
    $slots = page_slots();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_valid()) {
            $error = 'Security token expired. Reload the page and try again.';
        } else {
            $next = [];
            foreach ($slots as $slot => $_meta) {
                /* A page marked for removal is simply left out of the set
                   handed to pages_save(), which writes what it is given.
                   Built-ins cannot be removed. */
                if (empty($_meta['builtin']) && !empty($_POST['delete'][$slot])) {
                    continue;
                }
                $next[$slot] = [
                    'enabled' => !empty($_POST['enabled'][$slot]),
                    'title'   => (string) ($_POST['title'][$slot] ?? ''),
                    'menu'    => (string) ($_POST['menu'][$slot] ?? ''),
                    'slug'    => (string) ($_POST['slug'][$slot] ?? ''),
                    'body'    => (string) ($_POST['body'][$slot] ?? ''),
                    'seo_title' => (string) ($_POST['seo_title'][$slot] ?? ''),
                    'seo_desc'  => (string) ($_POST['seo_desc'][$slot] ?? ''),
                ];
            }
            /* Adding a page creates an empty slot and returns to the form, so
               the new fields arrive filled in by the browser rather than by
               JavaScript building a row. */
            if (($_POST['op'] ?? '') === 'add') {
                $next[page_slot_new()] = [
                    'enabled' => false, 'title' => '', 'menu' => '', 'slug' => '', 'body' => '',
                    'seo_title' => '', 'seo_desc' => '',
                ];
            }
            $slug_error = '';
            if (pages_save($next, $slug_error)) {
                header('Location: ' . BASE_URL . '?action=pages&saved=1');
                exit;
            }
            $error = $slug_error !== ''
                ? $slug_error
                : 'Could not write to data/. Check that the folder is writable.';
        }
    }
    if (isset($_GET['saved'])) {
        $notice = 'Saved. Enabled pages appear in the header immediately.';
    }

    $pages = pages_load();

    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pages &ndash; <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
</head>
<body>
<header class="topbar">
    <h1><a class="site-home" href="<?= e(BASE_URL) ?>"><?= e(SITE_NAME) ?></a></h1>
    <span class="running-head">Pages</span>
    <nav class="crumbs">
        <a href="<?= e(BASE_URL) ?>?action=settings">Settings</a>
        <span class="sep">/</span>
        <a href="<?= e(BASE_URL) ?>?action=diagnostics">Diagnostics</a>
        <span class="sep">/</span>
        <a href="<?= e(BASE_URL) ?>">Back to the library</a>
    </nav>
</header>
<main class="detail">
    <?php if ($notice !== ''): ?><p class="msg msg-ok"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="msg msg-bad"><?= e($error) ?></p><?php endif; ?>
    <?php if (!$writable): ?>
        <p class="msg msg-bad">The <code>data/</code> folder is not writable, so pages cannot be saved.</p>
    <?php endif; ?>

    <h2 class="detail-title">Standalone pages</h2>
    <p class="detail-desc">
        Optional informational pages shown in the header beside the library. Write in Markdown;
        raw HTML is escaped for safety. A page appears publicly only when it is enabled and has
        content. About and FAQ carry matching structured data; the numbered slots are general pages.
    </p>

    <form method="post" class="pages-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <?php foreach ($slots as $slot => $meta): ?>
            <?php $rec = $pages[$slot] ?? ['enabled' => false, 'title' => '', 'menu' => '', 'body' => '', 'seo_title' => '', 'seo_desc' => '']; ?>
            <fieldset class="page-slot">
                <legend>
                    <?= e($rec['title'] !== '' ? $rec['title'] : $meta['default_title']) ?>
                    <span class="page-url"><?= e(str_replace(BASE_URL, '/', url_page($slot))) ?></span>
                    <?php if (empty($meta['builtin'])): ?>
                        <label class="page-delete">
                            <input type="checkbox" name="delete[<?= e($slot) ?>]" value="1">
                            Delete on save
                        </label>
                    <?php endif; ?>
                </legend>
                <label class="check-row">
                    <input type="checkbox" name="enabled[<?= e($slot) ?>]" value="1" <?= $rec['enabled'] ? 'checked' : '' ?>>
                    Show this page
                </label>
                <label class="page-field">
                    <span>Title</span>
                    <input type="text" name="title[<?= e($slot) ?>]" maxlength="120" value="<?= e($rec['title']) ?>" placeholder="<?= e($meta['default_title']) ?>">
                </label>
                <label class="page-field">
                    <span>Menu label <em>(optional)</em></span>
                    <input type="text" name="menu[<?= e($slot) ?>]" maxlength="40" value="<?= e($rec['menu']) ?>" placeholder="Defaults to the title">
                </label>
                <label class="page-field">
                    <span>URL slug</span>
                    <input type="text" name="slug[<?= e($slot) ?>]" maxlength="160"
                           value="<?= e((string) ($rec['slug'] ?? '')) ?>"
                           placeholder="<?= e($slot) ?>">
                    <span class="field-note">
                        The page's address: <code><?= e(rtrim(BASE_URL, '/')) ?>/<?= e(page_slug($slot, $rec)) ?>/</code>.
                        Leave empty to use <code><?= e($slot) ?></code>. Must not clash with another
                        page, a document, or a Folio route.
                    </span>
                </label>
                <label class="page-field">
                    <span>Content <em>(Markdown<?= $slot === 'faq' ? '; use ## for each question' : '' ?>)</em></span>
                    <textarea name="body[<?= e($slot) ?>]" rows="8" spellcheck="true"><?= e($rec['body']) ?></textarea>
                </label>
                <label>
                    <span>Search title</span>
                    <input type="text" name="seo_title[<?= e($slot) ?>]" maxlength="60"
                           placeholder="Defaults to the title above"
                           value="<?= e($rec['seo_title'] ?? '') ?>">
                    <span class="field-note">Up to 60 characters. Used verbatim as the page title, with no site name appended.</span>
                </label>
                <label>
                    <span>Search description</span>
                    <textarea name="seo_desc[<?= e($slot) ?>]" maxlength="150" rows="2"
                              placeholder="Defaults to the opening of the page"><?= e($rec['seo_desc'] ?? '') ?></textarea>
                    <span class="field-note">Up to 150 characters. The snippet under the link in a search result, and what social cards show.</span>
                </label>
            </fieldset>
        <?php endforeach; ?>
        <div class="pages-actions">
            <button type="submit" class="btn"<?= $writable ? '' : ' disabled' ?>>Save pages</button>
            <button type="submit" name="op" value="add" class="btn btn-ghost"<?= $writable ? '' : ' disabled' ?>>Add a page</button>
        </div>
        <p class="field-note">
            Adding a page saves the form first, so anything typed above is kept. There is no limit
            on how many pages a library can have. Deleting one removes its content and its address;
            About and FAQ cannot be deleted because their structured data types are built in.
        </p>
    </form>
</main>
</body>
</html>
    <?php
    exit;
}

/* ------------------------------------------------------------------ */
/* Public page display                                                 */
/* ------------------------------------------------------------------ */
/* A bare clean URL could name a standalone page or a document. Which one
   cannot be decided during URL mapping, because that runs before the pages
   file's location is defined. It is settled here instead, before either
   handler, so pages and documents can share one flat namespace. */
if (isset($GLOBALS['folio_route_candidate'], $_GET['view'])
    && $GLOBALS['folio_route_candidate'] === $_GET['view']) {
    $candidate_slot = page_slot_for_slug((string) $_GET['view']);
    if ($candidate_slot !== null) {
        $candidate_rec = pages_load()[$candidate_slot] ?? null;
        if ($candidate_rec !== null && !empty($candidate_rec['enabled'])
            && trim((string) $candidate_rec['body']) !== '') {
            $_GET['page'] = (string) $_GET['view'];
            unset($_GET['view']);
        }
    }
}

if (isset($_GET['page'])) {
    $requested = (string) $_GET['page'];
    $slots = page_slots();
    $pages = pages_load();

    // A page is addressed by its slug. Its slot name still resolves, so any
    // link made before the slug was chosen keeps working — it just redirects
    // to the current address rather than serving a second copy of the page.
    $slot = page_slot_for_slug($requested);
    if ($slot !== null) {
        $canonical = page_slug($slot, $pages[$slot] ?? []);
        if ($requested !== $canonical) {
            $rec_check = $pages[$slot] ?? null;
            if ($rec_check !== null && !empty($rec_check['enabled']) && trim((string) $rec_check['body']) !== '') {
                header('Location: ' . url_page($slot), true, 301);
                exit;
            }
        }
    } else {
        $slot = $requested;
    }
    $rec = $pages[$slot] ?? null;

    if (!isset($slots[$slot]) || $rec === null || !$rec['enabled'] || trim($rec['body']) === '') {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        send_security_headers();
        echo '<!DOCTYPE html><html lang="' . e(SITE_LANGUAGE) . '"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<meta name="robots" content="noindex">'
           . '<title>Not found &ndash; ' . e(SITE_NAME) . '</title>'
           . '<link rel="stylesheet" href="' . e(asset_url('assets/css/style.css')) . '"></head>'
           . '<body><main class="detail"><h1 class="detail-title">Page not found</h1>'
           . '<p class="detail-desc">There is no page here.</p>'
           . '<p class="detail-actions"><a class="btn" href="' . e(BASE_URL) . '">Back to the library</a></p>'
           . '</main></body></html>';
        exit;
    }

    if (!function_exists('mb_strlen')) {
        http_response_code(500);
        exit('The mbstring PHP extension is required for Markdown rendering.');
    }

    $title    = page_title($slot, $rec);
    $page_url = url_page($slot);
    $body_html = render_markdown_text($rec['body']);
    $indexable = SITE_INDEXABLE;
    $menu = pages_menu();

    /* Search-result title and description. A standalone page previously had
       no meta description at all, so search engines invented one from the
       body. Absent a written description, the opening of the page is used:
       still derived, but from the top of the page rather than wherever a
       crawler decided to start. */
    $seo_title = trim((string) ($rec['seo_title'] ?? ''));
    $seo_desc  = trim((string) ($rec['seo_desc'] ?? ''));
    $head_title = $seo_title !== '' ? $seo_title : $title . ' – ' . SITE_NAME;
    if ($seo_desc !== '') {
        $page_desc = $seo_desc;
    } else {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($body_html)) ?? '');
        $page_desc = $plain !== '' ? str_clip($plain, 150) : SITE_DESCRIPTION;
    }

    /* Structured data for the page. */
    $page_type = $slots[$slot]['type'];
    $page_node = [
        '@type' => $page_type,
        '@id' => $page_url . '#page',
        'name' => $title,
        'url' => $page_url,
        'inLanguage' => SITE_LANGUAGE,
        'isPartOf' => ['@id' => BASE_URL . '#website'],
    ];
    if ($page_type === 'FAQPage') {
        $faq = faq_parse($rec['body']);
        if ($faq) {
            $page_node['mainEntity'] = $faq;
        }
    }

    /* Standalone pages carry a breadcrumb like every other public page, so a
       search result shows the library path rather than a bare URL. */
    $page_crumb = [
        '@type' => 'BreadcrumbList',
        '@id' => $page_url . '#breadcrumb',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => SITE_NAME, 'item' => BASE_URL],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $page_url],
        ],
    ];
    $page_node['breadcrumb'] = ['@id' => $page_url . '#breadcrumb'];

    $page_ld = [schema_website(), schema_publisher(), $page_crumb, $page_node];

    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    if ($indexable) {
        send_public_cache_headers(300);
    }
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($head_title) ?></title>
<meta name="description" content="<?= e($page_desc) ?>">
<?php if (!$indexable): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<link rel="canonical" href="<?= e($page_url) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($seo_title !== '' ? $seo_title : $title) ?>">
<meta property="og:description" content="<?= e($page_desc) ?>">
<meta property="og:url" content="<?= e($page_url) ?>">
<meta name="twitter:card" content="summary">
<script type="application/ld+json"><?= schema_emit($page_ld) ?></script>
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
</head>
<body>
<header class="topbar">
    <h1><a class="site-home" href="<?= e(BASE_URL) ?>"><?= e(SITE_NAME) ?></a></h1>
    <nav class="crumbs">
        <a href="<?= e(BASE_URL) ?>">Home</a>
        <?php foreach ($menu as $mslot => $mrec): ?>
            <span class="sep">/</span>
            <a href="<?= e(url_page($mslot)) ?>"<?= $mslot === $slot ? ' aria-current="page"' : '' ?>><?= e(page_menu_label($mslot, $mrec)) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="theme-picker" role="group" aria-label="Colour scheme">
        <button data-set-theme="folio" title="Folio"></button>
        <button data-set-theme="ledger" title="Ledger"></button>
        <button data-set-theme="garden" title="Garden"></button>
        <button data-set-theme="night" title="Night"></button>
    </div>
</header>
<main class="detail">
    <h1 class="detail-title page-title"><?= e($title) ?></h1>
    <div class="md-content"><?= $body_html ?></div>
</main>
<?php render_footer(); ?>
<script src="<?= e(asset_url('assets/js/app.js')) ?>" defer></script>
<?php if (is_admin()): ?><script src="<?= e(asset_url('assets/js/admin.js')) ?>" defer></script><?php endif; ?>
</body>
</html>
    <?php
    exit;
}

/* ------------------------------------------------------------------ */
/* Diagnostics: environment, addressing, and configuration health     */
/* ------------------------------------------------------------------ */
/* ------------------------------------------------------------------ */
/* Catalogue: reconnect records to files                                */
/*                                                                      */
/* Shows what has come adrift and offers the two safe repairs: match by */
/* content, or attach a record to a file by hand. Neither touches a     */
/* physical file — FTP owns those.                                      */
/* ------------------------------------------------------------------ */
/* ------------------------------------------------------------------ */
/* Catalogue: reconnect records to files                                */
/*                                                                      */
/* Shows what has come adrift and offers the two safe repairs: match by */
/* content, or attach a record to a file by hand. Neither touches a     */
/* physical file — FTP owns those.                                      */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'catalogue') {
    if (!is_admin()) {
        header('Location: ' . BASE_URL . '?action=login');
        exit;
    }
    $notice = '';
    $error  = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_valid()) {
            $error = 'Invalid security token — reload the page and try again.';
        } elseif (($_POST['op'] ?? '') === 'reconcile') {
            @set_time_limit(300);
            $rep = [];
            if (reconcile_run(true, $rep)) {
                $n = count($rep['matched'] ?? []);
                $notice = $n > 0
                    ? $n . ' document(s) reconnected to their files. Nothing on disk was changed.'
                    : 'Nothing could be matched by content. Anything left needs relinking by hand.';
            } else {
                $error = 'The catalogue could not be written.';
            }
        } elseif (($_POST['op'] ?? '') === 'relink') {
            $rl_error = '';
            if (document_relink((string) ($_POST['document_id'] ?? ''),
                                (string) ($_POST['file'] ?? ''), $rl_error)) {
                $notice = 'Reconnected. Only the file association changed — the URL, title and '
                        . 'everything else are as they were.';
            } else {
                $error = $rl_error !== '' ? $rl_error : 'That document could not be relinked.';
            }
        }
    }

    $survey  = reconcile_survey();
    $preview = [];
    if ($survey['orphans']) {
        reconcile_run(false, $preview);
    }
    $auto = count($preview['matched'] ?? []);

    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Catalogue &ndash; <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
</head>
<body>
<header class="topbar">
    <h1><a class="site-home" href="<?= e(BASE_URL) ?>"><?= e(SITE_NAME) ?></a></h1>
    <span class="running-head">Catalogue</span>
    <nav class="crumbs">
        <a href="<?= e(BASE_URL) ?>?action=diagnostics">Diagnostics</a>
        <span class="sep">/</span>
        <a href="<?= e(BASE_URL) ?>">Back to the library</a>
    </nav>
</header>
<main class="detail">
    <div class="md-content">
        <h1>Catalogue</h1>

        <?php if ($notice !== ''): ?><p class="msg msg-ok"><?= e($notice) ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="msg msg-bad"><?= e($error) ?></p><?php endif; ?>

        <?php if (!$survey['orphans'] && !$survey['unassociated']): ?>
            <p>Every document is matched to a file, and every file is catalogued. There is
               nothing to repair.</p>
        <?php endif; ?>

        <?php if ($survey['orphans']): ?>
            <h2>Documents whose file is missing</h2>
            <p>These records still hold their title, URL, description and everything else. Their
               file has been renamed, moved, or removed since it was catalogued.</p>

            <?php if ($auto > 0): ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="op" value="reconcile">
                    <p><strong><?= (int) $auto ?></strong> of them can be matched by comparing file
                       contents. A match is only accepted when exactly one file has the right
                       contents and exactly one record wants it.</p>
                    <button class="btn" type="submit">Reconnect <?= (int) $auto ?> document<?= $auto === 1 ? '' : 's' ?></button>
                </form>
            <?php else: ?>
                <p>None can be matched automatically: their contents no longer match any file in
                   the library, which usually means a document was edited as well as renamed.
                   Relink those by hand below.</p>
            <?php endif; ?>

            <table class="diag-table">
                <?php foreach ($survey['orphans'] as $oid => $orec): ?>
                    <tr>
                        <td>
                            <strong><?= e($orec['title'] !== '' ? $orec['title'] : $orec['slug']) ?></strong><br>
                            <span class="field-note">was <code><?= e($orec['file_path']) ?></code></span>
                        </td>
                        <td>
                            <?php if ($survey['unassociated']): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="op" value="relink">
                                    <input type="hidden" name="document_id" value="<?= e($oid) ?>">
                                    <select name="file" required>
                                        <option value="">Attach to a file&hellip;</option>
                                        <?php foreach ($survey['unassociated'] as $urel => $_u): ?>
                                            <option value="<?= e($urel) ?>"><?= e($urel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn-small" type="submit">Relink</button>
                                </form>
                            <?php else: ?>
                                <span class="field-note">No uncatalogued file to attach it to.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if ($survey['unassociated']): ?>
            <h2>Files not yet catalogued</h2>
            <p>These are in the library but have no record. Give one a title from the listing to
               add it, or attach it to a document above.</p>
            <ul>
                <?php foreach ($survey['unassociated'] as $urel => $_u): ?>
                    <li><code><?= e($urel) ?></code></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p class="field-note">Folio never renames, moves, replaces or deletes a file. Everything
           on this page changes only which file a record points at.</p>
    </div>
</main>
<?php render_footer(); ?>
</body>
</html>
<?php
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'diagnostics') {
    if (!is_admin()) {
        header('Location: ' . BASE_URL . '?action=login');
        exit;
    }

    $route = (string) ($_SERVER['SFM_ROUTE'] ?? $_SERVER['REDIRECT_SFM_ROUTE'] ?? '');
    $probe = rtrim(BASE_URL, '/') . '/__folio_probe__/';

    /* Each check: label, status (ok|warn|bad), and a short note. */
    $env_checks = [];

    $php_ok = version_compare(PHP_VERSION, '8.4.0', '>=');
    $env_checks[] = [
        'label'  => 'PHP version',
        'status' => $php_ok ? 'ok' : 'bad',
        'note'   => $php_ok ? PHP_VERSION : PHP_VERSION . ' — Folio requires PHP 8.4 or newer',
    ];
    $env_checks[] = [
        'label'  => 'mbstring extension',
        'status' => function_exists('mb_strlen') ? 'ok' : 'bad',
        'note'   => function_exists('mb_strlen') ? 'Available' : 'Missing — Markdown rendering is disabled',
    ];
    foreach (['json' => 'json_encode', 'password hashing' => 'password_hash', 'randomness' => 'random_bytes'] as $need => $fn) {
        $env_checks[] = [
            'label'  => $need . ' support',
            'status' => function_exists($fn) ? 'ok' : 'bad',
            'note'   => function_exists($fn) ? 'Available' : 'Missing — required PHP function ' . $fn . '() not found',
        ];
    }
    /* PHP extensions. Only mbstring is genuinely required; the rest each
       have a documented fallback, so a missing one is a note about reduced
       capability rather than a fault. Reporting them all means you can see
       what your host gives you without reading phpinfo(). */
    $engine = image_engine();
    $env_checks[] = [
        'label'  => 'image engine',
        'brief'  => $engine === 'imagick' ? 'Imagick' : ($engine === 'gd' ? 'GD' : ''),
        'status' => $engine === 'none' ? 'warn' : 'ok',
        'note'   => $engine === 'imagick'
            ? 'Imagick — thumbnails, and TIFF, HEIC and AVIF converted for viewing.'
            : ($engine === 'gd'
                ? 'GD only. Thumbnails work for PNG, JPEG, GIF and WebP. TIFF, HEIC and '
                  . 'AVIF cannot be converted; those files are served as-is. Ask your host '
                  . 'for php-imagick to cover them.'
                : 'Neither Imagick nor GD. Thumbnails are disabled and original files are '
                  . 'served directly, which is slow on large scans. Ask your host for '
                  . 'php-imagick.'),
    ];
    $has_finfo = extension_loaded('fileinfo') && class_exists('finfo');
    $env_checks[] = [
        'label'  => 'fileinfo extension',
        'brief'  => 'Available',
        'status' => $has_finfo ? 'ok' : 'warn',
        'note'   => $has_finfo
            ? 'Available — file types are detected from content, not just the extension.'
            : 'Missing. File types fall back to the filename extension, so a mislabelled '
              . 'file is served with the wrong type. Nothing breaks.',
    ];
    $env_checks[] = [
        'label'  => 'iconv extension',
        'brief'  => 'Available',
        'status' => function_exists('iconv') ? 'ok' : 'warn',
        'note'   => function_exists('iconv')
            ? 'Available — accented characters are transliterated when building URL slugs.'
            : 'Missing. Accented characters are stripped from slugs rather than converted, '
              . 'so "Ærø" becomes "r" instead of "aero". Existing URLs are unaffected.',
    ];
    $opcache_on = function_exists('opcache_get_status') && ini_get('opcache.enable');
    $env_checks[] = [
        'label'  => 'OPcache',
        'brief'  => 'Enabled',
        'status' => $opcache_on ? 'ok' : 'warn',
        'note'   => $opcache_on
            ? 'Enabled — Folio is one large file, so this saves recompiling it on every request.'
            : 'Not enabled. Folio works without it, but as a single large file it benefits '
              . 'noticeably. Worth asking your host to turn on.',
    ];

    $uploads_ok = is_dir(BASE_DIR) && is_readable(BASE_DIR);
    $env_checks[] = [
        'label'  => 'uploads/ readable',
        'status' => $uploads_ok ? 'ok' : 'bad',
        'note'   => $uploads_ok ? relative_from_base(BASE_DIR) === '' ? UPLOADS_DIRNAME . '/' : (string) BASE_DIR : 'Not readable — the library cannot list files',
    ];
    $data_ok = is_dir(__DIR__ . '/data') && is_writable(__DIR__ . '/data');
    $env_checks[] = [
        'label'  => 'data/ writable',
        'status' => $data_ok ? 'ok' : 'bad',
        'note'   => $data_ok ? 'Writable' : 'Not writable — settings, accounts, and metadata cannot be saved',
    ];

    /* Addressing / rewrite. */
    $url_checks = [];
    if (function_exists('apache_get_modules')) {
        $rewrite = in_array('mod_rewrite', apache_get_modules(), true);
        $url_checks[] = [
            'label'  => 'mod_rewrite',
            'brief'  => 'Not readable under PHP-FPM or CGI, which is normal',
            'status' => $rewrite ? 'ok' : (PRETTY_URLS ? 'bad' : 'warn'),
            'note'   => $rewrite ? 'Loaded' : 'Not loaded' . (PRETTY_URLS ? ' — clean URLs will 404' : ' — only needed for clean URLs'),
        ];
    } else {
        // Not readable outside mod_php. That is the normal case on modern
        // hosting, so it is reported as information rather than a warning:
        // an amber flag on a site whose clean URLs work is just noise.
        $url_checks[] = [
            'label'  => 'mod_rewrite',
            'brief'  => 'Not readable under PHP-FPM or CGI, which is normal',
            'status' => 'ok',
            'note'   => 'Cannot be read from PHP-FPM or CGI, which is normal. It is not a problem:'
                      . ' if your document pages load with clean URLs, mod_rewrite is active.',
        ];
    }
    $htaccess = is_file(__DIR__ . '/.htaccess');
    $url_checks[] = [
        'label'  => '.htaccess present',
        'status' => $htaccess ? 'ok' : 'warn',
        'note'   => $htaccess ? 'Installed' : 'Not found — the release ships .htaccess at the root. Many FTP clients skip dotfiles by default; enable hidden files and upload it again.',
    ];
    $url_checks[] = [
        'label'  => 'URL mode',
        'status' => 'ok',
        'note'   => PRETTY_URLS ? 'Clean URLs (PRETTY_URLS on)' : 'Query-string URLs (PRETTY_URLS off)',
    ];
    /* This screen is always reached at ?action=diagnostics, a query-string
       admin URL that is never rewritten by design. Reporting "not rewritten"
       here therefore says nothing about whether rewriting works, and reads as
       a fault on a site whose clean URLs are working perfectly. What can be
       checked from here is that the rules are present in .htaccess. */
    $ht_rules = $htaccess ? (string) @file_get_contents(__DIR__ . '/.htaccess') : '';
    $has_router = $ht_rules !== '' && strpos($ht_rules, 'RewriteRule ^(.+)$ index.php') !== false;
    $has_pdf    = $ht_rules !== '' && strpos($ht_rules, 'uploads/(.+\.pdf)') !== false;
    if (PRETTY_URLS) {
        $url_checks[] = [
            'label'  => 'Rewrite rules',
        'brief'  => 'Present, including the PDF access rule',
            'status' => $has_router ? 'ok' : 'warn',
            'note'   => $has_router
                ? 'Present in .htaccess'
                  . ($has_pdf ? ', including the PDF access rule.' : '. The PDF access rule is missing or commented out.')
                  . ' Clean URLs are on, so if your document pages load, rewriting is working —'
                  . ' this admin page is always a query-string URL and is never rewritten, so it'
                  . ' cannot demonstrate that itself.'
                : 'The catch-all rewrite rule was not found in .htaccess, but clean URLs are'
                  . ' turned on. If document pages 404, either uncomment the rules or set'
                  . ' PRETTY_URLS to false.',
        ];
    } else {
        $url_checks[] = [
            'label'  => 'Rewrite rules',
            'status' => 'ok',
            'note'   => 'Not needed — query-string URLs are in use.',
        ];
    }

    /* Configuration health. */
    $cfg_checks = [];

    $site_url_set = defined('SITE_URL') && trim((string) SITE_URL) !== ''
        && filter_var((string) SITE_URL, FILTER_VALIDATE_URL)
        && in_array(strtolower((string) parse_url((string) SITE_URL, PHP_URL_SCHEME)), ['http', 'https'], true);
    $cfg_checks[] = [
        'label'  => 'SITE_URL configured',
        'status' => $site_url_set ? 'ok' : 'bad',
        'note'   => $site_url_set ? BASE_URL : 'Not set — canonical URLs fall back to http://localhost. Set SITE_URL in config.php.',
    ];
    $https = request_is_https();
    $cfg_checks[] = [
        'label'  => 'HTTPS',
        'status' => $https ? 'ok' : 'warn',
        'note'   => $https ? 'Request served over HTTPS' : 'Not detected — login and session cookies need HTTPS in production'
            . (TRUST_PROXY_HEADERS ? '' : '. If behind a proxy, set TRUST_PROXY_HEADERS.'),
    ];
    $installer_here = is_file(__DIR__ . '/install.php');
    $cfg_checks[] = [
        'label'  => 'install.php removed',
        'status' => $installer_here ? 'bad' : 'ok',
        'note'   => $installer_here ? 'Still present — delete install.php now that setup is complete' : 'Deleted',
    ];
    $token_here = is_file(__DIR__ . '/data/install-token.php');
    $cfg_checks[] = [
        'label'  => 'installer token removed',
        'status' => $token_here ? 'warn' : 'ok',
        'note'   => $token_here ? 'data/install-token.php still present — delete it' : 'Deleted',
    ];
    $pepper_set = defined('FOLIO_AUTH_PEPPER') && FOLIO_AUTH_PEPPER !== '';
    $cfg_checks[] = [
        'label'  => 'password pepper',
        'brief'  => 'Set',
        'status' => $pepper_set ? 'ok' : 'warn',
        'note'   => $pepper_set ? 'Set — hashes are peppered' : 'Not set — optional, but recommended (FOLIO_AUTH_PEPPER in config.php)',
    ];
    $publisher_named = defined('PUBLISHER_NAME') && trim((string) PUBLISHER_NAME) !== '';
    $cfg_checks[] = [
        'label'  => 'publisher identity',
        'status' => $publisher_named ? 'ok' : 'warn',
        'note'   => $publisher_named ? PUBLISHER_NAME . ' (' . PUBLISHER_TYPE . ')' : 'Empty — structured data omits the publisher node until PUBLISHER_NAME is set',
    ];
    $cfg_checks[] = [
        'label'  => 'indexability',
        'brief'  => SITE_INDEXABLE ? 'Public' : 'Not indexed',
        'status' => SITE_INDEXABLE ? 'ok' : 'warn',
        'note'   => SITE_INDEXABLE ? 'Public — search and AI crawlers may index the library' : 'Non-indexable — pages emit noindex; sitemap and llms.txt return 404',
    ];
    $accounts_stored = users_in_store();
    $cfg_checks[] = [
        'label'  => 'account store',
        'status' => 'ok',
        'note'   => $accounts_stored ? 'data/users.php (' . count(users_load()) . ' account(s))' : 'Using config.php credentials until the first account change',
    ];
    $enabled_pages = pages_menu();
    $cfg_checks[] = [
        'label'  => 'standalone pages',
        'status' => 'ok',
        'note'   => $enabled_pages
            ? count($enabled_pages) . ' enabled: ' . implode(', ', array_map(function ($slot, $rec) { return page_menu_label($slot, $rec); }, array_keys($enabled_pages), $enabled_pages))
            : 'None enabled. Add optional pages at ?action=pages',
    ];
    $indexnow_active = defined('INDEXNOW_KEY') && INDEXNOW_KEY !== '';
    $cfg_checks[] = [
        'label'  => 'IndexNow',
        'status' => 'ok',
        'note'   => $indexnow_active
            ? 'Key active. Key file: /' . INDEXNOW_KEY . '.txt'
            : 'No key set. Generate one at ?action=crawlers if you want to notify IndexNow engines.',
    ];
    $exclude_patterns = (array) EXCLUDE_PATTERNS;
    $cfg_checks[] = [
        'label'  => 'excluded patterns',
        'brief'  => 'None',
        'status' => 'ok',
        'note'   => $exclude_patterns
            ? count($exclude_patterns) . ' pattern(s): ' . implode(', ', array_map('strval', $exclude_patterns))
            : 'None. Add globs to EXCLUDE_PATTERNS in config.php to hide files or folders.',
    ];

    $cfg_checks[] = [
        'label'  => 'Folio version',
        'status' => 'ok',
        'note'   => FOLIO_VERSION,
    ];

    $rewrite_env = !empty($_SERVER['FOLIO_REWRITE']) || !empty($_SERVER['REDIRECT_FOLIO_REWRITE']);
    $cfg_checks[] = [
        'label'  => 'Clean URLs',
        'brief'  => 'Active',
        'status' => PRETTY_URLS ? 'ok' : 'warn',
        'note'   => PRETTY_URLS
            ? 'Active. mod_rewrite is working and the shipped .htaccess is installed.'
            : ($rewrite_env
                ? 'Disabled in config.php, though the server supports it. Remove the PRETTY_URLS line to enable.'
                : 'Query-string URLs in use. Upload the .htaccess from this release and confirm your host allows overrides and loads mod_rewrite.'),
    ];

    /* External utilities. Folio needs none of them, so a missing tool is a
       note rather than a fault; only a partly-working OCR chain warns. */
    $tool_rows = [
        'ocrmypdf'   => 'searchable copies of scanned PDFs',
        'tesseract'  => 'the OCR engine itself',
        'pdftotext'  => 'text extraction and search indexing',
        'pdfinfo'    => 'page counts and PDF facts',
        'pdftocairo' => 'rendering PDF pages for previews',
        'pdftoppm'   => 'PDF page previews (fallback)',
        'qpdf'       => 'PDF structure checks',
        'pngquant'   => 'shrinking rendered PDF pages before they become thumbnails',
        'exiftool'   => 'reading a document\'s own creation date',
        'unpaper'    => 'cleaning up crooked scans before OCR',
    ];
    $found = [];
    $absent = [];
    foreach ($tool_rows as $bin => $why) {
        $p = tool_path($bin);
        if ($p !== null) {
            $found[] = $bin . ' (' . $p . ')';
        } else {
            $absent[] = $bin;
        }
    }
    $homes = tool_account_homes();
    if (!TOOLS_ENABLED) {
        $tools_status = 'ok';
        $tools_note   = 'Turned off by TOOLS_ENABLED in config.php. Folio behaves as though none were installed.';
        $tools_brief  = 'Turned off in config.php';
    } elseif (!$found) {
        $tools_status = 'ok';
        $tools_brief  = 'None found';
        $tools_note   = 'None found. Everything works without them; OCR, text search over scans, and server-rendered PDF previews are simply unavailable.'
            . ($homes ? ' Searched the system paths plus ' . implode(', ', $homes)
                . ' (including .local/bin and any virtual environment). Name a tool in TOOL_PATHS if it lives elsewhere.' : '');
    } else {
        $tools_status = 'ok';
        $tools_brief  = count($found) . ' found' . ($absent ? ', ' . count($absent) . ' missing (' . implode(', ', $absent) . ')' : '');
        $tools_note   = count($found) . ' found: ' . implode(', ', $found)
            . ($absent ? '. Not installed: ' . implode(', ', $absent) . '.' : '.')
            . ($absent && $homes
                ? ' Searched the system paths plus this account\'s home ('
                  . implode(', ', $homes) . '), including .local/bin and any '
                  . 'virtual environment such as ocrmypdf-venv/bin. If a tool '
                  . 'lives elsewhere, name it in TOOL_PATHS.'
                : '');
    }
    $icon_src = trim((string) SITE_ICON) !== ''
        ? 'SITE_ICON in config.php'
        : (is_file(__DIR__ . '/branding/favicon.svg')
            || is_file(__DIR__ . '/branding/favicon.png')
            || is_file(__DIR__ . '/branding/favicon.ico')
            ? 'branding/ folder' : '');
    $cfg_checks[] = [
        'label'  => 'site icon',
        'brief'  => $icon_src !== '' ? 'Your own icon, from the ' . $icon_src : 'Folio default',
        'status' => 'ok',
        'note'   => $icon_src !== ''
            ? 'Your own icon, from the ' . $icon_src . '.'
            : 'Folio default. To use your own, put favicon.svg (or .png/.ico, and '
              . 'apple-touch-icon.png) in a branding/ folder at the root — it is picked up '
              . 'automatically and survives upgrades. Replacing the file inside assets/ '
              . 'would be overwritten by the next update.',
    ];

    $cfg_checks[] = [
        'label'  => 'external utilities',
        'brief'  => $tools_brief,
        'status' => $tools_status,
        'note'   => $tools_note,
    ];

    /* Catalogue health. This is a survey only — it never writes, and never
       hashes a file unless something is already known to be missing, so
       opening Diagnostics on a large library stays cheap. */
    $survey = reconcile_survey();
    $n_orphan = count($survey['orphans']);
    $n_new    = count($survey['unassociated']);
    if ($n_orphan === 0 && $n_new === 0) {
        $cat_status = 'ok';
        $cat_note   = count($survey['ok']) . ' document(s), every one matched to a file on disk.';
    } else {
        $cat_status = $n_orphan > 0 ? 'warn' : 'ok';
        $parts = [];

        /* Run the match preview once and use it for both messages below. It
           was previously computed after the catalogue note, which meant that
           note promised content matching would reconnect these records even
           when the preview had already established that it could not. */
        $recon_preview = [];
        $m = $a = 0;
        if ($n_orphan > 0) {
            reconcile_run(false, $recon_preview);
            $m = count($recon_preview['matched'] ?? []);
            $a = count($recon_preview['ambiguous'] ?? []);
        }

        if ($n_orphan > 0) {
            $names = [];
            foreach (array_slice($survey['orphans'], 0, 5, true) as $rec) {
                $names[] = ($rec['title'] ?: $rec['slug']) . ' (was ' . $rec['file_path'] . ')';
            }
            $parts[] = $n_orphan . ' document(s) whose file is missing: ' . implode('; ', $names)
                     . ($n_orphan > 5 ? ' and others' : '')
                     . '. ' . ($m > 0
                        ? 'Open Catalogue (?action=catalogue): ' . $m . ' can be matched by content, '
                          . 'which reconnects them and keeps their existing URLs.'
                        : 'None can be matched by content, so open Catalogue (?action=catalogue) to '
                          . 'relink them by hand or remove them.');
        }
        if ($n_new > 0) {
            $parts[] = $n_new . ' file(s) not yet catalogued: '
                     . implode(', ', array_slice(array_keys($survey['unassociated']), 0, 5))
                     . ($n_new > 5 ? ' and others' : '')
                     . '. Give one a title to add it.';
        }
        $cat_note = implode(' ', $parts);
    }
    $cfg_checks[] = ['label' => 'catalogue', 'status' => $cat_status, 'note' => $cat_note];

    if ($n_orphan > 0) {
        $recon = [];
        if ($m > 0) {
            $recon[] = $m . ' document(s) can be reconnected automatically by matching file contents.';
        }
        if ($a > 0) {
            $recon[] = $a . ' case(s) are ambiguous and will be left alone: several files share the '
                     . 'same contents, or several records want the same file. Relink those by hand.';
        }
        if ($m === 0 && $a === 0) {
            /* Orphans exist but no content match was found, so the files were
               deleted rather than moved, or were replaced by different
               content. Nothing can be applied; saying "open Catalogue to
               apply it" here would name an action that does not exist. */
            $recon[] = 'No document can be matched by content, so these files were deleted rather '
                     . 'than renamed, or were replaced with different content. Open Catalogue '
                     . '(?action=catalogue) to relink them to a file by hand, or to remove records '
                     . 'you no longer want.';
        } else {
            $recon[] = 'Open Catalogue (?action=catalogue) to review and apply.';
        }
        $recon[] = 'Folio never renames, moves, or deletes anything on disk; only its own catalogue changes.';

        $cfg_checks[] = [
            'label'  => 'reconciliation',
            'status' => $a > 0 ? 'warn' : 'ok',
            'note'   => implode(' ', $recon),
        ];
    }

    if (tool_have('ocrmypdf') || tool_have('tesseract')) {
        $langs   = ocr_languages_available();
        $usable  = ocr_language_string();
        $missing = array_values(array_diff((array) OCR_LANGUAGES, $langs));
        if (ocr_available()) {
            $ocr_status = $missing ? 'warn' : 'ok';
            $ocr_note   = 'Ready. Will OCR in: ' . $usable . '.'
                . ($missing
                    ? ' Requested but not installed: ' . implode(', ', $missing)
                      . ' — install the matching tesseract language packs to use them.'
                    : '')
                . ' Installed datasets: ' . (implode(', ', $langs) ?: 'none') . '.';
        } else {
            $ocr_status = 'warn';
            $ocr_note   = 'Incomplete: '
                . (tool_have('ocrmypdf') ? '' : 'ocrmypdf missing. ')
                . (tool_have('tesseract') ? '' : 'tesseract missing. ')
                . ($usable === '' ? 'No requested language dataset is installed. ' : '')
                . 'OCR is unavailable until this is resolved; nothing else is affected.';
        }
        $cfg_checks[] = [
            'label'  => 'OCR',
            'brief'  => 'Ready — ' . $usable,
            'status' => $ocr_status,
            'note'   => $ocr_note,
        ];
    }

    if (tool_have('pdftocairo') || tool_have('pdftoppm')) {
        $cfg_checks[] = [
            'label'  => 'PDF rasteriser',
        'brief'  => 'Poppler present',
            'status' => 'ok',
            'note'   => 'Poppler is present, so PDF pages can be rendered for previews and thumbnails.',
        ];
    }

    $pdfjs_core   = __DIR__ . '/lib/pdfjs/pdf.min.mjs';
    $pdfjs_worker = __DIR__ . '/lib/pdfjs/pdf.worker.min.mjs';
    $pdfjs_ok     = is_file($pdfjs_core) && is_file($pdfjs_worker);
    $parsedown_ver = is_file(__DIR__ . '/lib/parsedown/VERSION')
        ? trim((string) @file_get_contents(__DIR__ . '/lib/parsedown/VERSION'))
        : 'unknown';
    $pdfjs_ver    = is_file(__DIR__ . '/lib/pdfjs/VERSION')
        ? trim((string) @file_get_contents(__DIR__ . '/lib/pdfjs/VERSION'))
        : 'unknown';
    $cfg_checks[] = [
        'label'  => 'PDF flip reader',
        'brief'  => 'pdf.js ' . $pdfjs_ver,
        'status' => $pdfjs_ok ? 'ok' : 'bad',
        'note'   => $pdfjs_ok
            ? 'pdf.js ' . $pdfjs_ver . ' present in lib/pdfjs/. If the reader does not start, confirm your server sends .mjs as JavaScript and .wasm as application/wasm.'
            : 'Missing files in lib/pdfjs/. Re-upload the lib/ folder from the release package.',
    ];

    $parsedown_ok = is_file(__DIR__ . '/lib/parsedown/Parsedown.php');
    $cfg_checks[] = [
        'label'  => 'Markdown rendering',
        'brief'  => 'Parsedown ' . $parsedown_ver,
        'status' => $parsedown_ok ? 'ok' : 'bad',
        'note'   => $parsedown_ok
            ? 'Parsedown ' . $parsedown_ver . ' present in lib/parsedown/.'
            : 'Missing lib/parsedown/Parsedown.php. Markdown files will not render. Re-upload the lib/ folder from the release package.',
    ];

    $pdf_signing_key_set = FOLIO_URL_SIGNING_KEY !== '';
    $cfg_checks[] = [
        'label'  => 'PDF URL signing key',
        'brief'  => 'Set',
        'status' => $pdf_signing_key_set ? 'ok' : 'warn',
        'note'   => $pdf_signing_key_set
            ? 'Set — "viewer" pdf_access links can be signed'
            : 'Not set — "viewer" and "hidden" pdf_access cannot be enforced until FOLIO_URL_SIGNING_KEY is set in config.php',
    ];

    // Files with a non-public pdf_access setting that is not currently
    // enforced — either the signing key is missing, or the routing
    // preflight on the Crawlers screen has not been confirmed. These files
    // are being served as public right now regardless of what is set on
    // them, which is the safe default but worth surfacing clearly here
    // rather than only as a small note in each file's editor.
    $pdf_restricted_unenforced = [];
    if (!pdf_access_enforced()) {
        foreach (meta_load() as $meta_rel => $meta_row) {
            if (pdf_access_of((array) $meta_row) !== 'public') {
                $pdf_restricted_unenforced[] = $meta_rel;
            }
        }
    }
    $cfg_checks[] = [
        'label'  => 'PDF access control',
        'status' => !$pdf_signing_key_set ? 'warn' : (PDF_GATE_CONFIRMED ? 'ok' : 'warn'),
        'note'   => !$pdf_signing_key_set
            ? 'Cannot be enforced yet — set FOLIO_URL_SIGNING_KEY first, then confirm the preflight at ?action=crawlers'
            : (PDF_GATE_CONFIRMED
                ? 'Confirmed and enforced.'
                : 'Not yet confirmed at ?action=crawlers'
                    . ($pdf_restricted_unenforced
                        ? ' — ' . count($pdf_restricted_unenforced) . ' file(s) are set to viewer/hidden but currently behave as public: '
                            . implode(', ', array_slice($pdf_restricted_unenforced, 0, 5))
                            . (count($pdf_restricted_unenforced) > 5 ? ', …' : '')
                        : '')),
    ];

    $imagick_ok = extension_loaded('imagick') && class_exists('Imagick');
    $ghostscript_ok = false;
    if ($imagick_ok) {
        try {
            $ghostscript_ok = in_array('PDF', (new Imagick())->queryFormats('PDF'), true);
        } catch (Throwable $e) {
            $ghostscript_ok = false;
        }
    }
    $cfg_checks[] = [
        'label'  => 'Blurred preview for hidden PDFs',
        'brief'  => 'Available',
        'status' => $imagick_ok && $ghostscript_ok ? 'ok' : 'warn',
        'note'   => $imagick_ok && $ghostscript_ok
            ? 'Available — automatic blurred first-page previews can be generated.'
            : ($imagick_ok
                ? 'Imagick is installed but cannot read PDFs on this server. Upload a manual placeholder image per file instead.'
                : 'Imagick is not installed. Upload a manual placeholder image per file instead of an automatic blurred preview.'),
    ];

    $groups = [
        ['Environment', $env_checks],
        ['Addressing and rewrite', $url_checks],
        ['Configuration health', $cfg_checks],
    ];
    $bad_count  = 0;
    $warn_count = 0;
    foreach ($groups as [$g_label, $g_checks]) {
        foreach ($g_checks as $c) {
            if ($c['status'] === 'bad') {
                $bad_count++;
            } elseif ($c['status'] === 'warn') {
                $warn_count++;
            }
        }
    }

    $badge = function (string $status): string {
        switch ($status) {
            case 'ok':
                return '<span class="chip chip-mini diag-ok">OK</span>';
            case 'warn':
                return '<span class="chip chip-mini diag-warn">CHECK</span>';
            default:
                return '<span class="chip chip-mini diag-bad">FAIL</span>';
        }
    };

    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnostics &ndash; <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
</head>
<body>
<header class="topbar">
    <h1><a class="site-home" href="<?= e(BASE_URL) ?>"><?= e(SITE_NAME) ?></a></h1>
    <span class="running-head">Diagnostics</span>
    <nav class="crumbs">
        <a href="<?= e(BASE_URL) ?>?action=settings">Settings</a>
        <span class="sep">/</span>
        <a href="<?= e(BASE_URL) ?>">Back to the library</a>
    </nav>
</header>
<main class="detail">
    <?php if ($bad_count > 0): ?>
        <p class="msg msg-bad"><?= (int) $bad_count ?> item<?= $bad_count === 1 ? '' : 's' ?> need attention before the site is healthy.</p>
    <?php elseif ($warn_count > 0): ?>
        <p class="msg msg-ok">No blocking problems. <?= (int) $warn_count ?> item<?= $warn_count === 1 ? '' : 's' ?> worth reviewing.</p>
    <?php else: ?>
        <p class="msg msg-ok">All checks passed.</p>
    <?php endif; ?>

    <?php
    /* Anything not OK, gathered first: the reason to open this page is almost
       always to find what is wrong, not to read thirty-five passing rows. */
    $attention = [];
    foreach ($groups as [$g_label, $g_checks]) {
        foreach ($g_checks as $c) {
            if ($c['status'] !== 'ok') {
                $c['group'] = $g_label;
                $attention[] = $c;
            }
        }
    }
    $tabs = [];
    if ($attention) {
        $tabs[] = ['Needs attention', $attention, count($attention)];
    }
    foreach ($groups as [$g_label, $g_checks]) {
        $tabs[] = [$g_label, $g_checks, count($g_checks)];
    }

    /* A passing check states the finding and stops. The guidance about how to
       change or fix a thing is only useful when something is wrong, so it is
       carried in 'note' and shown then; 'brief' is the resting state. */
    $note_of = function (array $c): string {
        return ($c['status'] === 'ok' && isset($c['brief']) && $c['brief'] !== '')
            ? (string) $c['brief']
            : (string) $c['note'];
    };
    ?>

    <div class="diag-tabs" id="diag-tabs">
        <div class="diag-tablist" role="tablist">
            <?php foreach ($tabs as $i => [$t_label, $t_checks, $t_count]): ?>
                <button type="button" role="tab"
                        class="diag-tab<?= $i === 0 ? ' is-active' : '' ?><?= $t_label === 'Needs attention' ? ' diag-tab-alert' : '' ?>"
                        data-diag-tab="<?= (int) $i ?>"
                        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
                    <?= e($t_label) ?> <span class="diag-tab-count"><?= (int) $t_count ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($tabs as $i => [$t_label, $t_checks, $t_count]): ?>
            <section class="diag-panel<?= $i === 0 ? ' is-active' : '' ?>" data-diag-panel="<?= (int) $i ?>" role="tabpanel">
                <h2 class="detail-title diag-panel-heading"><?= e($t_label) ?></h2>
                <table class="accounts diag-table">
                    <?php foreach ($t_checks as $c): ?>
                        <tr class="diag-row diag-row-<?= e((string) $c['status']) ?>">
                            <td>
                                <?= e((string) $c['label']) ?>
                                <?php if (isset($c['group'])): ?><span class="diag-row-group"><?= e((string) $c['group']) ?></span><?php endif; ?>
                            </td>
                            <td><?= $badge((string) $c['status']) ?></td>
                            <td class="detail-facts"><?= e($note_of($c)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </section>
        <?php endforeach; ?>
    </div>

    <h2 class="detail-title">Clean-URL rewrite probe</h2>
    <p class="detail-desc">
        Open <a href="<?= e($probe) ?>"><code><?= e($probe) ?></code></a> in a new tab.
        Expect a Folio "file not found" 404 page. If your server shows its own 404 instead,
        the rewrite is not active and <code>PRETTY_URLS</code> must stay off.
    </p>

    <h2 class="detail-title">Full regression smoke test</h2>
    <p class="detail-desc">
        A shell script under <code>tests/smoke.sh</code> exercises the security and integrity
        behaviours end to end (symlink containment, forced downloads, atomic metadata, session
        revocation, host-header validation, and more). Run it from a shell on a host with
        command-line PHP and <code>curl</code>:
    </p>
    <pre class="diag-cmd">cd <?= e(basename(__DIR__)) ?>
bash tests/smoke.sh</pre>
    <p class="detail-desc">
        It starts a throwaway PHP server on a copy of the installation, so it never touches your
        real <code>config.php</code>, <code>data/</code>, or <code>uploads/</code>. A passing run
        ends with <em>All Folio smoke tests passed.</em> See <code>tests/readme.md</code> for details.
    </p>

    <p class="detail-actions">
        <a class="btn" href="<?= e(BASE_URL) ?>?action=diagnostics">Re-run checks</a>
        <a class="btn btn-ghost" href="<?= e(BASE_URL) ?>">Back to the library</a>
    </p>
</main>
</body>
</html>
    <?php
    exit;
}

/* ------------------------------------------------------------------ */
/* Rendered markdown fragment (loaded in preview iframes)              */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'render') {
    $abs = resolve_path((string) ($_GET['file'] ?? ''));
    if ($abs === null || !is_file($abs) || strtolower(pathinfo($abs, PATHINFO_EXTENSION)) !== 'md') {
        http_response_code(404);
        exit('Not found');
    }
    $rel_render = str_replace(DIRECTORY_SEPARATOR, '/', trim(substr($abs, strlen((string) realpath(BASE_DIR))), '/\\'));
    if (is_excluded(basename($rel_render), $rel_render)) {
        http_response_code(404);
        exit('Not found');
    }
    if (!function_exists('mb_strlen')) {
        http_response_code(500);
        exit('The mbstring PHP extension is required for Markdown rendering.');
    }
    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    send_public_cache_headers(300);
    echo '<!DOCTYPE html><html lang="' . e(SITE_LANGUAGE) . '"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex">'
       . site_icon_tags()
              . '<link rel="stylesheet" href="' . e(BASE_URL) . 'assets/css/style.css?v=' . rawurlencode(FOLIO_VERSION) . '">'
       . '</head><body class="md-body"><div class="md-content">'
       . render_markdown($abs)
       . '</div></body></html>';
    exit;
}

/* ------------------------------------------------------------------ */
/* PDF flip-view reader                                                */
/*                                                                      */
/* A dedicated page rather than an in-listing overlay, so the relaxed  */
/* CSP that WebAssembly rendering needs (wasm-unsafe-eval, worker-src) */
/* is scoped to this one screen and never loosens the main listing.    */
/* The PDF itself is fetched client-side from its normal raw URL —     */
/* no new file-reading code path, so path safety and excludes are      */
/* enforced exactly the same way they already are for that URL.        */
/* ------------------------------------------------------------------ */
/* ------------------------------------------------------------------ */
/* Derivative image delivery                                            */
/*                                                                      */
/* Same containment as every other delivery route: the path is resolved */
/* inside uploads/, excluded files are absent, and the width must be    */
/* one Folio actually offers so the cache cannot be filled on demand.   */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'thumb') {
    $abs = resolve_path((string) ($_GET['file'] ?? ''));
    if ($abs === null || !is_file($abs)) {
        http_response_code(404);
        exit('Not found');
    }
    $rel_thumb = str_replace(DIRECTORY_SEPARATOR, '/', trim(substr($abs, strlen((string) realpath(BASE_DIR))), '/\\'));
    if (is_excluded(basename($rel_thumb), $rel_thumb)) {
        http_response_code(404);
        exit('Not found');
    }

    // The URL helper avoids linking these, but anyone can type the URL, so
    // the gate is enforced here too. A restricted PDF has no thumbnail.
    if (!thumb_permitted($rel_thumb)) {
        http_response_code(404);
        exit('Not found');
    }

    $raw_w = (string) ($_GET['w'] ?? '');
    if (!preg_match('/^[0-9]+$/', $raw_w) || !in_array((int) $raw_w, (array) THUMB_WIDTHS, true)) {
        http_response_code(404);
        exit('Not found');
    }
    $width = (int) $raw_w;

    $cache = thumb_build($rel_thumb, $abs, $width);
    if ($cache === null) {
        // No engine, unsupported format, or a conversion that failed.
        //
        // Redirecting to the original is only right when the original is
        // itself something an <img> can display. For a PDF it is not: the
        // browser receives a PDF where it expected an image and draws a
        // broken-image icon — the very thing this fallback exists to avoid.
        // A 404 lets the caller fall back to its own placeholder instead.
        $thumb_ext = strtolower(pathinfo($rel_thumb, PATHINFO_EXTENSION));
        if (in_array($thumb_ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'], true)) {
            header('Location: ' . url_raw($rel_thumb), true, 302);
            exit;
        }
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('No preview available');
    }

    $etag = '"' . substr(basename($cache), 0, 32) . '"';
    header('Content-Type: image/webp');
    header('Content-Length: ' . (string) filesize($cache));
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }
    readfile($cache);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'flipbook') {
    $abs = resolve_path((string) ($_GET['file'] ?? ''));
    if ($abs === null || !is_file($abs) || strtolower(pathinfo($abs, PATHINFO_EXTENSION)) !== 'pdf') {
        http_response_code(404);
        exit('Not found');
    }
    $rel_flip = str_replace(DIRECTORY_SEPARATOR, '/', trim(substr($abs, strlen((string) realpath(BASE_DIR))), '/\\'));
    if (is_excluded(basename($rel_flip), $rel_flip)) {
        http_response_code(404);
        exit('Not found');
    }

    $meta       = meta_load();
    $m_flip     = $meta[$rel_flip] ?? [];
    if (pdf_access_of($m_flip) === 'hidden' && pdf_access_enforced()) {
        http_response_code(404);
        exit('Not found');
    }
    $flip_title  = trim((string) ($m_flip['title'] ?? '')) ?: pathinfo($abs, PATHINFO_FILENAME);
    $flip_url    = url_raw_effective($rel_flip, $m_flip);
    $flip_full   = pdf_full_access($rel_flip, $m_flip);
    $back_url   = url_view($rel_flip);

    header('Content-Type: text/html; charset=UTF-8');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'" . inline_css_hash() . "; script-src 'self' 'wasm-unsafe-eval'; worker-src 'self'; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($flip_title) ?> &ndash; <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/flipbook.css">
</head>
<body class="flip-body">
<header class="flip-topbar">
    <a class="flip-back" href="<?= e($back_url) ?>">&larr; <?= e($flip_title) ?></a>
    <div class="flip-controls">
        <button type="button" class="btn-small" id="flip-prev" aria-label="Previous page">&larr;</button>
        <span class="flip-pageno"><input type="text" inputmode="numeric" id="flip-page-input" class="flip-page-input" aria-label="Page number"> / <span id="flip-page-total">&hellip;</span></span>
        <button type="button" class="btn-small" id="flip-next" aria-label="Next page">&rarr;</button>
    </div>
    <?php if ($flip_full): ?>
    <a class="flip-download" href="<?= e($flip_url) ?>" download>Download</a>
    <?php endif; ?>
</header>
<main class="flip-stage" id="flip-stage" data-pdf-url="<?= e($flip_url) ?>" data-pdfjs-base="<?= e(BASE_URL) ?>lib/pdfjs/">
    <p class="flip-status" id="flip-status">Loading&hellip;</p>
</main>
<script type="module" src="<?= e(asset_url('assets/js/flipbook.js')) ?>"></script>
</body>
</html>
    <?php
    exit;
}

/* ------------------------------------------------------------------ */
/* Legacy file endpoint: redirect to the file's real location          */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'raw') {
    // PDF-routing self-check probe. Lets the Crawlers screen confirm that
    // requests to a real uploads/*.pdf file actually reach this action on
    // this server before pdf_access is treated as enforced. Admin-only, so
    // it gives an anonymous scanner no information.
    if (($_GET['file'] ?? '') === FOLIO_PDF_PROBE_NAME) {
        if (!is_admin()) {
            http_response_code(404);
            exit('Not found');
        }
        header('Content-Type: application/json');
        exit(json_encode(['ok' => true, 'gate' => 'pdf']));
    }

    $abs = resolve_path((string) ($_GET['file'] ?? ''));
    if ($abs === null || !is_file($abs)) {
        http_response_code(404);
        exit('Not found');
    }
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', trim(substr($abs, strlen((string) realpath(BASE_DIR))), '/\\'));
    if (is_excluded(basename($rel), $rel)) {
        http_response_code(404);
        exit('Not found');
    }

    // pdf_access gate. This is the single enforcement point: every other
    // path to a PDF's bytes (preview, flip view, print, direct link) is
    // built to route through here rather than duplicate this check.
    if (strtolower(pathinfo($abs, PATHINFO_EXTENSION)) === 'pdf' && pdf_access_enforced()) {
        $access = pdf_access_of(meta_load()[$rel] ?? []);
        if ($access === 'hidden') {
            http_response_code(404);
            exit('Not found');
        }
        if ($access === 'viewer') {
            $expires = (int) ($_GET['expires'] ?? 0);
            $token   = (string) ($_GET['token'] ?? '');
            if ($token === '' || !pdf_signed_url_valid($rel, $expires, $token)) {
                http_response_code(404);
                exit('Not found');
            }
        }
    }

    if (($_GET['serve'] ?? '') === '1') {
        // Fallback delivery for servers whose rewrite does not short-circuit
        // real files. Redirecting here would loop, so stream the bytes.
        $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = $mime_map[$ext] ?? 'application/octet-stream';
        $active_downloads = ['html', 'htm', 'xhtml', 'xml', 'mht', 'mhtml'];
        $force_download = !isset($mime_map[$ext]) || in_array($ext, $active_downloads, true);
        header('Content-Type: ' . ($force_download ? 'application/octet-stream' : $mime));
        header('Content-Length: ' . (string) filesize($abs));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=86400');
        if ($force_download) {
            header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', basename($abs)) . '"');
            header("Content-Security-Policy: default-src 'none'; sandbox");
            header('X-Robots-Tag: noindex, nofollow');
        } elseif (in_array($ext, ['pdf', 'txt', 'md'], true)) {
            // Stated explicitly rather than left to the default. Documents are
            // the content of the library: a scanned certificate a search engine
            // cannot see is a document nobody will find, and following the
            // links inside a PDF is how a crawler discovers the rest.
            header('X-Robots-Tag: index, follow');
        }
        if ($ext === 'svg') {
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
        }
        readfile($abs);
        exit;
    }
    header('Location: ' . url_raw_effective($rel, meta_load()[$rel] ?? []), true, 301);
    exit;
}

/* ------------------------------------------------------------------ */
/* Blurred first-page preview for "hidden" PDFs.                        */
/* Never the original file: this streams only the derived, heavily      */
/* downsampled and blurred JPEG, generated by pdf_blur_generate(). Safe  */
/* to serve publicly since no reconstructable content survives the blur.*/
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'pdf_preview') {
    $abs = resolve_path((string) ($_GET['file'] ?? ''));
    if ($abs === null || !is_file($abs) || strtolower(pathinfo($abs, PATHINFO_EXTENSION)) !== 'pdf') {
        http_response_code(404);
        exit('Not found');
    }
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', trim(substr($abs, strlen((string) realpath(BASE_DIR))), '/\\'));
    if (is_excluded(basename($rel), $rel) || pdf_access_of(meta_load()[$rel] ?? []) !== 'hidden') {
        http_response_code(404);
        exit('Not found');
    }
    if (!pdf_blur_generate($abs, $rel)) {
        http_response_code(404);
        exit('Not found');
    }
    $cache = pdf_blur_cache_path($rel);
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . (string) filesize($cache));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=86400');
    readfile($cache);
    exit;
}

/* ------------------------------------------------------------------ */
/* XML sitemap: directories, file pages, and category archives         */
/* ------------------------------------------------------------------ */
/* ------------------------------------------------------------------ */
/* PDF sitemap                                                          */
/*                                                                      */
/* A sitemap of the document files themselves, separate from the one    */
/* listing record pages. Google indexes PDFs as documents in their own  */
/* right, and a scanned certificate that is never crawled is a document */
/* nobody finds. Every PDF in the library is listed.                    */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'sitemap_pdf') {
    if (!SITEMAP_ENABLED || !SITE_INDEXABLE) {
        http_response_code(404);
        exit('Not found');
    }

    $all = index_all_files($mime_map);
    $entries = [];
    foreach ($all as $f) {
        if (strtolower((string) $f['ext']) !== 'pdf') {
            continue;
        }
        $rel = (string) $f['rel'];
        // Every PDF in the library is listed. Files matching EXCLUDE_PATTERNS
        // never reach this loop at all: index_all_files() has already dropped
        // them, and they return 404 on every route, so listing them would only
        // send crawlers to a dead address.
        $abs = resolve_path($rel);
        if ($abs === null || !is_file($abs)) {
            continue;
        }
        $entries[] = [
            'loc'     => url_raw($rel),
            'lastmod' => (int) $f['lastmod'],
            'page'    => url_view($rel),
            'title'   => (string) $f['title'],
        ];
    }

    header('Content-Type: application/xml; charset=UTF-8');
    send_public_cache_headers(900);
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($entries as $e) {
        echo "  <url>\n";
        echo '    <loc>' . htmlspecialchars($e['loc'], ENT_XML1) . "</loc>\n";
        if ($e['lastmod'] > 0) {
            echo '    <lastmod>' . date('c', $e['lastmod']) . "</lastmod>\n";
        }
        echo "  </url>\n";
    }
    echo '</urlset>';
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'sitemap') {
    if (!SITEMAP_ENABLED || !SITE_INDEXABLE) {
        http_response_code(404);
        exit('Not found');
    }

    $all = index_all_files($mime_map);

    /* Build the URL set as individual <url> blocks so it can be counted and
       partitioned. The sitemap protocol allows at most 50,000 URLs and 50 MB
       per file; beyond that a sitemap index pointing at parts is required. */
    $entries = [];

    $dir_latest = ['' => 0];
    foreach ($all as $f) {
        $dir = (string) $f['dir'];
        while (true) {
            $dir_latest[$dir] = max((int) ($dir_latest[$dir] ?? 0), (int) $f['lastmod']);
            if ($dir === '') {
                break;
            }
            $dir = dirname($dir);
            $dir = $dir === '.' ? '' : str_replace('\\', '/', $dir);
        }
        if (!isset($mime_map[$f['ext']])) {
            continue;
        }
        $e  = "  <url>\n";
        $e .= '    <loc>' . htmlspecialchars($f['view'], ENT_XML1) . "</loc>\n";
        $e .= '    <lastmod>' . date('c', (int) $f['lastmod']) . "</lastmod>\n";
        if (!in_array($f['ext'], ['pdf', 'txt', 'md'], true)) {
            $e .= "    <image:image>\n";
            $e .= '      <image:loc>' . htmlspecialchars($f['hotlink'], ENT_XML1) . "</image:loc>\n";
            if ($f['title'] !== '') {
                $e .= '      <image:title>' . htmlspecialchars($f['title'], ENT_XML1) . "</image:title>\n";
            }
            $e .= "    </image:image>\n";
        }
        $e .= "  </url>\n";
        $entries[] = $e;
    }

    ksort($dir_latest);
    foreach ($dir_latest as $dir => $latest) {
        $e  = "  <url>\n";
        $e .= '    <loc>' . htmlspecialchars(url_dir((string) $dir), ENT_XML1) . "</loc>\n";
        if ($latest > 0) {
            $e .= '    <lastmod>' . date('c', $latest) . "</lastmod>\n";
        }
        $e .= "  </url>\n";
        $entries[] = $e;
    }

    $cat_latest = [];
    foreach ($all as $f) {
        $c = (string) $f['category'];
        if ($c !== '') {
            $cat_latest[$c] = max((int) ($cat_latest[$c] ?? 0), (int) $f['lastmod']);
        }
    }
    foreach (category_register($all) as $cat_name => $count) {
        $e  = "  <url>\n";
        $e .= '    <loc>' . htmlspecialchars(url_category($cat_name), ENT_XML1) . "</loc>\n";
        if (!empty($cat_latest[$cat_name])) {
            $e .= '    <lastmod>' . date('c', (int) $cat_latest[$cat_name]) . "</lastmod>\n";
        }
        $e .= "  </url>\n";
        $entries[] = $e;
    }

    foreach (pages_menu() as $mslot => $mrec) {
        $e  = "  <url>\n";
        $e .= '    <loc>' . htmlspecialchars(url_page($mslot), ENT_XML1) . "</loc>\n";
        if (!empty($mrec['updated_at'])) {
            $e .= '    <lastmod>' . date('c', (int) $mrec['updated_at']) . "</lastmod>\n";
        }
        $e .= "  </url>\n";
        $entries[] = $e;
    }

    $total = count($entries);
    $parts = (int) max(1, (int) ceil($total / SITEMAP_MAX_URLS));

    // Distinguish "no part requested" (serve the index or the whole sitemap)
    // from "a part was requested but is not a valid number", which is a
    // malformed URL and must not silently return something else.
    $requested_part = 0;
    if (isset($_GET['part'])) {
        $raw_part = (string) $_GET['part'];
        if (preg_match('/^[0-9]+$/', $raw_part)) {
            $requested_part = (int) $raw_part;
            if ($requested_part === 0) {
                $requested_part = -1; // part=0 is not a real part
            }
        } else {
            $requested_part = -1;
        }
    }

    header('Content-Type: application/xml; charset=UTF-8');
    send_public_cache_headers(900);

    // A single-file sitemap keeps the original shape, so small sites are
    // completely unaffected by partitioning.
    if ($parts === 1 && $requested_part === 0) {
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
           . ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach ($entries as $e) {
            echo $e;
        }
        echo '</urlset>';
        exit;
    }

    if ($requested_part === 0) {
        // Index listing every part.
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        for ($i = 1; $i <= $parts; $i++) {
            $slice = array_slice($entries, ($i - 1) * SITEMAP_MAX_URLS, SITEMAP_MAX_URLS);
            $newest = 0;
            foreach ($slice as $e) {
                if (preg_match('#<lastmod>([^<]+)</lastmod>#', $e, $m)) {
                    $newest = max($newest, (int) strtotime($m[1]));
                }
            }
            echo "  <sitemap>\n";
            echo '    <loc>' . htmlspecialchars(url_sitemap_part($i), ENT_XML1) . "</loc>\n";
            if ($newest > 0) {
                echo '    <lastmod>' . date('c', $newest) . "</lastmod>\n";
            }
            echo "  </sitemap>\n";
        }
        echo '</sitemapindex>';
        exit;
    }

    if ($requested_part < 1 || $requested_part > $parts) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Not found');
    }

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
       . ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
    foreach (array_slice($entries, ($requested_part - 1) * SITEMAP_MAX_URLS, SITEMAP_MAX_URLS) as $e) {
        echo $e;
    }
    echo '</urlset>';
    exit;
}

/* ------------------------------------------------------------------ */
/* Admin login / logout                                                */
/* ------------------------------------------------------------------ */
function throttle_file(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return sys_get_temp_dir() . '/folio_login_' . hash('sha256', BASE_URL . '|' . FOLIO_COOKIE_NAME . '|' . $ip);
}

function throttle_check(): bool
{
    $f = throttle_file();
    if (!is_file($f)) {
        return true;
    }
    $d = json_decode((string) file_get_contents($f), true);
    if (!is_array($d)) {
        return true;
    }
    if (($d['count'] ?? 0) >= 8 && time() - ($d['ts'] ?? 0) < 900) {
        return false;
    }
    if (time() - ($d['ts'] ?? 0) >= 900) {
        @unlink($f);
    }
    return true;
}

/**
 * Record a failed login attempt.
 *
 * The whole read-modify-write runs while an exclusive lock is held. Reading
 * the counter before locking would let two parallel attempts load the same
 * value and each write back the same increment, so a burst of concurrent
 * guesses would cost far fewer than one attempt each.
 *
 * If the counter cannot be locked or written we fail closed by treating the
 * attempt as if it had counted: throttle_check() sees a missing or stale file
 * as "allowed", so silently losing writes here would disable the limit.
 */
function throttle_hit(): void
{
    $f = throttle_file();
    $fh = @fopen($f, 'c+');
    if ($fh === false) {
        return;
    }
    if (!@flock($fh, LOCK_EX)) {
        @fclose($fh);
        return;
    }
    @chmod($f, 0600);

    // filesize() consults PHP's stat cache and can report a stale size for a
    // file another process just wrote, which would silently truncate the read
    // and lose counts even though the lock was held correctly.
    @rewind($fh);
    $raw = (string) @stream_get_contents($fh);
    $d   = json_decode($raw, true);
    $d   = is_array($d) ? $d : [];

    // A window that has already expired starts a fresh count rather than
    // accumulating attempts from hours ago.
    if (time() - (int) ($d['ts'] ?? 0) >= 900) {
        $d = [];
    }
    $d['count'] = (int) ($d['count'] ?? 0) + 1;
    $d['ts']    = time();

    @rewind($fh);
    @ftruncate($fh, 0);
    @fwrite($fh, (string) json_encode($d));
    @fflush($fh);
    @flock($fh, LOCK_UN);
    @fclose($fh);
}

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    // Accept either the session token (standalone login page, where a session
    // already exists) or the stateless signed token used by the header form.
    $submitted = (string) ($_POST['csrf'] ?? '');
    $token_ok = login_token_valid($submitted)
        || (session_status() === PHP_SESSION_ACTIVE && csrf_valid());
    if (!$token_ok) {
        $login_error = 'Session expired, please try again.';
    } elseif (!throttle_check()) {
        $login_error = 'Too many failed attempts. Try again in 15 minutes.';
    } else {
        $user = (string) ($_POST['username'] ?? '');
        $pw   = (string) ($_POST['password'] ?? '');
        if (user_verify($user, $pw)) {
            @unlink(throttle_file());
            session_regenerate_id(true);
            $verified_users = users_load();
            $_SESSION['sfm_admin'] = true;
            $_SESSION['sfm_user'] = $user;
            $_SESSION['sfm_auth_version'] = max(1, (int) ($verified_users[$user]['auth_version'] ?? 1));
            $_SESSION['sfm_csrf'] = bin2hex(random_bytes(32));
            header('Location: ' . strtok((string) $_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        throttle_hit();
        usleep(random_int(200000, 500000)); // blunt timing probes
        $login_error = users_load() === []
            ? 'No account is configured. Set ADMIN_PASSWORD_HASH in config.php.'
            : 'Wrong username or password.';
    }
}
if (($_GET['action'] ?? '') === 'logout' || ($_POST['action'] ?? '') === 'logout') {
    // Logging out changes server state, so it must not be reachable by a
    // cross-origin GET. Without this a third-party page could end an
    // administrator's session with an <img> tag.
    ensure_session_started();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: text/html; charset=UTF-8');
        send_security_headers();
        echo '<!DOCTYPE html><html lang="' . e(SITE_LANGUAGE) . '"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<meta name="robots" content="noindex">'
           . '<title>Log out &ndash; ' . e(SITE_NAME) . '</title>'
           . '<link rel="stylesheet" href="' . e(asset_url('assets/css/style.css')) . '"></head>'
           . '<body><main class="detail"><h1 class="detail-title">Confirm sign out</h1>'
           . '<p class="detail-desc">Signing out needs to be confirmed here rather than through a '
           . 'plain link, so that another site cannot end your session for you.</p>'
           . '<form method="post" action="' . e(BASE_URL) . '">'
           . '<input type="hidden" name="action" value="logout">'
           . '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
           . '<p class="detail-actions"><button type="submit" class="btn">Log out</button> '
           . '<a class="btn btn-ghost" href="' . e(BASE_URL) . '">Stay signed in</a></p>'
           . '</form></main></body></html>';
        exit;
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    header('Location: ' . BASE_URL);
    exit;
}

/* ------------------------------------------------------------------ */
/* llms.txt: a curated map of the library for AI crawlers              */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'llms') {
    if (!LLMS_ENABLED || !SITE_INDEXABLE) {
        http_response_code(404);
        exit('Not found');
    }
    header('Content-Type: text/plain; charset=UTF-8');
    send_public_cache_headers(900);
    $all = index_all_files($mime_map);
    $out = '# ' . SITE_NAME . "\n\n> " . SITE_DESCRIPTION . "\n\n";
    if (LLMS_INTRO !== '') {
        $out .= LLMS_INTRO . "\n\n";
    }
    if (PUBLISHER_NAME !== '') {
        $out .= 'Published by ' . PUBLISHER_NAME
              . (PUBLISHER_URL !== '' ? ' (' . PUBLISHER_URL . ')' : '') . ".\n\n";
    }
    $by_cat = [];
    foreach ($all as $f) {
        $key = $f['category'] !== '' ? $f['category'] : "\x7fOther documents";
        $by_cat[$key][] = $f;
    }
    ksort($by_cat, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($by_cat as $cat => $files_in_cat) {
        $label = $cat === "\x7fOther documents" ? 'Other documents' : $cat;
        $out .= '## ' . $label . "\n\n";
        foreach ($files_in_cat as $f) {
            $title = $f['title'] !== '' ? $f['title'] : pathinfo($f['name'], PATHINFO_FILENAME);
            $out .= '- [' . str_replace(['[', ']'], '', $title) . '](' . $f['view'] . ')';
            if ($f['desc'] !== '') {
                $out .= ': ' . str_replace("\n", ' ', $f['desc']);
            }
            if ($f['pdf_access'] !== 'public' && pdf_access_enforced() && $f['has_transcript']) {
                $out .= ' (Original file access-restricted; full transcription available on the page.)';
            }
            $out .= "\n";
        }
        $out .= "\n";
    }
    $out .= "## Machine-readable\n\n- [Sitemap]("
          . (PRETTY_URLS ? rtrim(BASE_URL, '/') . '/sitemap.xml' : BASE_URL . '?action=sitemap')
          . ")\n";
    exit($out);
}

/* ------------------------------------------------------------------ */
/* Standalone login page                                               */
/* Always reachable at index.php?action=login, so hiding the Admin      */
/* link in the header never locks anybody out.                          */
/* ------------------------------------------------------------------ */
if (($_GET['action'] ?? '') === 'login'
    || (($_POST['from'] ?? '') === 'login' && $login_error !== '')) {
    if (is_admin()) {
        header('Location: ' . BASE_URL);
        exit;
    }
    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in &ndash; <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
</head>
<body>
<header class="topbar">
    <h1><a class="site-home" href="<?= e(BASE_URL) ?>"><?= e(SITE_NAME) ?></a></h1>
    <span class="running-head">Sign in</span>
</header>
<main class="detail">
    <h2 class="detail-title">Log in</h2>
    <?php if ($login_error !== ''): ?>
        <p class="msg msg-bad"><?= e($login_error) ?></p>
    <?php endif; ?>
    <form method="post" class="stack-form">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="from" value="login">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="text" name="username" placeholder="Username" autocomplete="username" required autofocus>
        <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
        <div><button type="submit" class="btn">Log in</button></div>
    </form>
    <p class="detail-facts"><a href="<?= e(BASE_URL) ?>">Back to the library</a></p>
</main>
</body>
</html>
    <?php
    exit;
}

/* ------------------------------------------------------------------ */
/* Save title / description (admin only)                               */
/* ------------------------------------------------------------------ */
/* ------------------------------------------------------------------ */
/* OCR one document                                                     */
/*                                                                      */
/* Admin-only and deliberately one file per request: OCR takes seconds  */
/* to minutes, so it must never sit in a visitor's page load. The       */
/* original is read, never written; the searchable copy goes to         */
/* data/ocr/ and is what later text extraction uses.                    */
/* ------------------------------------------------------------------ */
/* ------------------------------------------------------------------ */
/* Reconciliation and relinking                                         */
/*                                                                      */
/* Both are administrator actions and neither touches a physical file.  */
/* Folio reads the library and rewrites its own catalogue; renaming,    */
/* moving, and deleting remain FTP's job alone.                         */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array(($_POST['action'] ?? ''), ['reconcile', 'relink'], true)) {
    header('Content-Type: application/json');
    if (!is_admin()) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'error' => 'Not authorised']));
    }
    if (!csrf_valid()) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'error' => 'Invalid security token — reload the page']));
    }

    if ($_POST['action'] === 'reconcile') {
        @set_time_limit(300);
        $apply  = ($_POST['apply'] ?? '') === '1';
        $report = [];
        $ok = reconcile_run($apply, $report);
        exit(json_encode([
            'ok'      => $ok,
            'applied' => $apply,
            'matched' => $report['matched'] ?? [],
            'ambiguous' => $report['ambiguous'] ?? [],
            'orphans' => $report['orphans'] ?? [],
            'unassociated' => $report['unassociated'] ?? [],
            'message' => $ok
                ? ($apply
                    ? count($report['matched'] ?? []) . ' document(s) reconnected to their files.'
                    : count($report['matched'] ?? []) . ' match(es) found. Nothing has been changed yet.')
                : 'The catalogue could not be written.',
        ]));
    }

    $error = '';
    $ok = document_relink(
        (string) ($_POST['document_id'] ?? ''),
        (string) ($_POST['file'] ?? ''),
        $error
    );
    exit(json_encode([
        'ok'      => $ok,
        'error'   => $ok ? null : $error,
        'message' => $ok ? 'The document now points at that file. Nothing on disk was changed.' : null,
    ]));
}

/* ------------------------------------------------------------------ */
/* Compress a PDF                                                       */
/*                                                                      */
/* Produces a smaller copy and reports the saving. It does not replace  */
/* anything: the original stays exactly as uploaded, and putting the    */
/* smaller file in its place is done over FTP, deliberately.            */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'compress') {
    header('Content-Type: application/json');
    if (!is_admin()) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'error' => 'Not authorised']));
    }
    if (!csrf_valid()) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'error' => 'Invalid security token — reload the page']));
    }
    $rel_c = (string) ($_POST['file'] ?? '');
    $abs_c = resolve_path($rel_c);
    if ($abs_c === null || !is_file($abs_c)) {
        http_response_code(404);
        exit(json_encode(['ok' => false, 'error' => 'File not found']));
    }
    $rel_c = str_replace(DIRECTORY_SEPARATOR, '/', trim(substr($abs_c, strlen((string) realpath(BASE_DIR))), '/\\'));
    if (is_excluded(basename($rel_c), $rel_c)) {
        http_response_code(404);
        exit(json_encode(['ok' => false, 'error' => 'File not found']));
    }

    @set_time_limit(360);
    $report = [];
    $made = pdf_compress($rel_c, $abs_c, $report);
    exit(json_encode([
        'ok'        => $made !== null,
        'error'     => $made === null ? $report['message'] : null,
        'message'   => $made !== null ? $report['message'] : null,
        'saved_pct' => $report['saved_pct'],
        'download'  => $made !== null
            ? BASE_URL . '?action=compressed&file=' . rawurlencode($rel_c)
            : null,
    ]));
}

/* Deliver a prepared compressed copy, for the administrator to download and
   put in place over FTP. Admin-only: it is not a second public address for
   the document. */
if (isset($_GET['action']) && $_GET['action'] === 'compressed') {
    if (!is_admin()) {
        http_response_code(403);
        exit('Not authorised');
    }
    $rel_d = (string) ($_GET['file'] ?? '');
    $abs_d = resolve_path($rel_d);
    if ($abs_d === null || !is_file($abs_d)) {
        http_response_code(404);
        exit('Not found');
    }
    $rel_d = str_replace(DIRECTORY_SEPARATOR, '/', trim(substr($abs_d, strlen((string) realpath(BASE_DIR))), '/\\'));
    $copy = derived_path(COMPRESS_DIR, $rel_d, $abs_d, 'pdf');
    if (!is_file($copy)) {
        http_response_code(404);
        exit('No compressed copy has been prepared for this document.');
    }
    header('Content-Type: application/pdf');
    header('Content-Length: ' . (string) filesize($copy));
    header('Content-Disposition: attachment; filename="' . basename($rel_d) . '"');
    header('X-Robots-Tag: noindex, nofollow');
    readfile($copy);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ocr') {
    header('Content-Type: application/json');
    if (!is_admin()) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'error' => 'Not authorised']));
    }
    if (!csrf_valid()) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'error' => 'Invalid security token — reload the page']));
    }
    $rel = (string) ($_POST['file'] ?? '');
    $abs = resolve_path($rel);
    if ($abs === null || !is_file($abs)) {
        http_response_code(404);
        exit(json_encode(['ok' => false, 'error' => 'File not found']));
    }
    $rel_ocr = str_replace(DIRECTORY_SEPARATOR, '/', trim(substr($abs, strlen((string) realpath(BASE_DIR))), '/\\'));
    if (is_excluded(basename($rel_ocr), $rel_ocr)) {
        http_response_code(404);
        exit(json_encode(['ok' => false, 'error' => 'File not found']));
    }
    if (!ocr_available()) {
        exit(json_encode(['ok' => false, 'error' => 'OCR is not available on this server. See Diagnostics.']));
    }

    // A document that already carries text needs no OCR, and saying so is
    // more useful than spending two minutes to reach the same place.
    if (pdf_has_text($rel_ocr, $abs)) {
        exit(json_encode([
            'ok'      => true,
            'skipped' => true,
            'message' => 'This PDF already contains text, so it is searchable as it is.',
        ]));
    }

    @set_time_limit(OCR_TIMEOUT + 60);
    $message = '';
    $ok = ocr_run($rel_ocr, $abs, $message);
    if (!$ok) {
        exit(json_encode(['ok' => false, 'error' => $message]));
    }
    $text  = document_text($rel_ocr, $abs);
    $chars = strlen(preg_replace('/\s+/', '', (string) $text));
    exit(json_encode([
        'ok'        => true,
        'message'   => $message,
        'languages' => ocr_language_string(),
        'chars'     => $chars,
        'preview'   => str_clip(trim((string) $text), 300),
    ]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'meta') {
    header('Content-Type: application/json');
    if (!is_admin()) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'error' => 'Not authorised']));
    }
    if (!csrf_valid()) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'error' => 'Invalid security token — reload the page']));
    }
    $rel = (string) ($_POST['file'] ?? '');
    $abs = resolve_path($rel);
    if ($abs === null || !is_file($abs)) {
        http_response_code(404);
        exit(json_encode(['ok' => false, 'error' => 'File not found']));
    }
    $rel   = str_replace(DIRECTORY_SEPARATOR, '/', trim(substr($abs, strlen((string) realpath(BASE_DIR))), '/\\'));
    $title = trim((string) ($_POST['title'] ?? ''));
    $desc  = trim((string) ($_POST['desc'] ?? ''));
    $cat   = trim((string) ($_POST['category'] ?? ''));
    $tags_raw = (string) ($_POST['tags'] ?? '');
    $transcript = trim((string) ($_POST['transcript'] ?? ''));
    $language   = trim((string) ($_POST['language'] ?? ''));
    $doc_date   = str_clip(trim((string) ($_POST['doc_date'] ?? '')), 60);
    /* Search-result title and description, kept apart from the ones shown on
       the page. A title that reads well above a document is often not the one
       that reads well in a result list, and a description written for a
       reader is often longer than a result will show. The limits are the
       lengths Google displays before truncating. */
    $seo_title = str_clip(trim((string) ($_POST['seo_title'] ?? '')), 60);
    $seo_desc  = str_clip(trim((string) ($_POST['seo_desc'] ?? '')), 150);
    if (function_exists('mb_substr')) {
        $title      = mb_substr($title, 0, 200);
        $desc       = mb_substr($desc, 0, 500);
        $cat        = mb_substr($cat, 0, 50);
        $transcript = mb_substr($transcript, 0, 100000);
        $language   = mb_substr($language, 0, 35);
    } else {
        $title      = substr($title, 0, 200);
        $desc       = substr($desc, 0, 500);
        $cat        = substr($cat, 0, 50);
        $transcript = substr($transcript, 0, 100000);
        $language   = substr($language, 0, 35);
    }
    $tags = [];
    foreach (explode(',', $tags_raw) as $t) {
        $t = trim($t);
        if ($t !== '' && !in_array($t, $tags, true)) {
            $tags[] = function_exists('mb_substr') ? mb_substr($t, 0, 50) : substr($t, 0, 50);
        }
        if (count($tags) >= 10) {
            break;
        }
    }

    $document_type = strtolower(trim((string) ($_POST['document_type'] ?? '')));
    if (!array_key_exists($document_type, document_types())) {
        $document_type = '';
    }

    $pdf_access = (string) ($_POST['pdf_access'] ?? 'public');
    if (!in_array($pdf_access, ['public', 'viewer', 'hidden'], true)) {
        $pdf_access = 'public';
    }

    // Manual fallback preview for "hidden" PDFs when automatic blurring
    // is not available on this host: a redacted or
    // placeholder image the admin has already placed in uploads/, the same
    // way every other file in the library gets there.
    $placeholder_image = trim((string) ($_POST['placeholder_image'] ?? ''));
    if ($placeholder_image !== '') {
        $ph_abs = resolve_path($placeholder_image);
        $ph_ext = $ph_abs !== null ? strtolower(pathinfo($ph_abs, PATHINFO_EXTENSION)) : '';
        $ph_valid = $ph_abs !== null && is_file($ph_abs)
            && in_array($ph_ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'], true);
        if (!$ph_valid) {
            http_response_code(422);
            exit(json_encode(['ok' => false, 'error' => 'Placeholder image not found — it must be an existing image already in ' . UPLOADS_DIRNAME . '/']));
        }
    }

    $updated = meta_update(static function (array $meta) use (
        $rel, $title, $desc, $cat, $tags, $document_type, $doc_date, $transcript, $pdf_access, $language, $placeholder_image,
        $seo_title, $seo_desc
    ): array {
        // Every field is checked here: a record is only cleared when the
        // administrator has genuinely emptied all of them. Omitting one would
        // both discard it on save and stop an otherwise-empty record being
        // cleared.
        if ($title === '' && $desc === '' && $cat === '' && !$tags
            && $document_type === '' && $doc_date === '' && $transcript === ''
            && $pdf_access === 'public' && $language === ''
            && $placeholder_image === '' && $seo_title === '' && $seo_desc === ''
        ) {
            $meta = meta_put_record($meta, $rel, null);
        } else {
            // updated_at lets the sitemap report a page as changed when its
            // description changed but the file on disk did not.
            $meta = meta_put_record($meta, $rel, [
                'title' => $title,
                'desc' => $desc,
                'category' => $cat,
                'tags' => $tags,
                'document_type' => $document_type,
                'doc_date' => $doc_date,
                'seo_title' => $seo_title,
                'seo_desc' => $seo_desc,
                'transcript' => $transcript,
                'pdf_access' => $pdf_access,
                'language' => $language,
                'placeholder_image' => $placeholder_image,
                'updated_at' => time(),
            ]);
        }
        return $meta;
    });
    if ($updated === false) {
        http_response_code(500);
        exit(json_encode(['ok' => false, 'error' => 'Metadata is invalid or could not be written safely']));
    }

    // The slug is identity rather than description, so it is applied on its
    // own and can be refused without disturbing the fields just saved.
    $slug_error = '';
    $slug_now = '';
    if (array_key_exists('slug', $_POST)) {
        $wanted = trim((string) $_POST['slug']);
        if ($wanted !== '') {
            document_set_slug($rel, $wanted, $slug_error);
        }
    }
    $rec_now = document_for_path($rel);
    $slug_now = (string) ($rec_now['slug'] ?? '');

    exit(json_encode([
        'ok' => $slug_error === '',
        'error' => $slug_error ?: null,
        'slug' => $slug_now,
        'url' => $rec_now ? document_url($rec_now) : null,
        'title' => $title,
        'desc' => $desc,
        'category' => $cat,
        'category_url' => $cat !== '' ? url_category($cat) : '',
        'tags' => $tags,
        'document_type' => $document_type,
        'pdf_access' => $pdf_access,
    ]));
}

/* ------------------------------------------------------------------ */
/* Category archive page                                               */
/* ------------------------------------------------------------------ */
if (isset($_GET['cat'])) {
    $slug = slugify((string) $_GET['cat']);
    $all  = index_all_files($mime_map);
    $reg  = category_register($all);

    $cat_name = '';
    $legacy_matches = [];
    foreach (array_keys($reg) as $name) {
        if (category_slug($name) === $slug) {
            $cat_name = $name;
            break;
        }
        /* Addresses issued before 1.16.1 carried a hash suffix on every
           category. They are indexed and sit in existing sitemaps, so they
           redirect to the current address rather than 404. The bare slugified
           name is matched too, for links predating the suffix entirely. */
        if (category_slug_legacy($name) === $slug || slugify($name) === $slug) {
            $legacy_matches[] = $name;
        }
    }
    if ($cat_name === '' && count(array_unique($legacy_matches)) === 1) {
        header('Location: ' . url_category($legacy_matches[0]), true, 301);
        exit;
    }
    if ($cat_name === '') {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        send_security_headers();
        echo '<!DOCTYPE html><html lang="' . e(SITE_LANGUAGE) . '" data-theme="folio"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>Category not found &ndash; ' . e(SITE_NAME) . '</title>'
           . '<meta name="robots" content="noindex">'
           . site_icon_tags()
                      . '<link rel="stylesheet" href="' . e(asset_url('assets/css/style.css')) . '"></head><body>'
           . '<header class="topbar"><h1><a class="site-home" href="' . e(BASE_URL) . '">' . e(SITE_NAME) . '</a></h1></header>'
           . '<main class="detail"><h2 class="detail-title">No such category</h2>'
           . '<p class="detail-desc">That category does not exist in this library.</p>'
           . '<p class="detail-actions"><a class="btn" href="' . e(BASE_URL) . '">Back to the library</a></p>'
           . '</main></body></html>';
        exit;
    }

    $cat_files = array_values(array_filter($all, function (array $f) use ($cat_name): bool {
        return $f['category'] === $cat_name;
    }));
    $cat_url = url_category($cat_name);
    $cat_desc = 'Documents and images categorised as ' . $cat_name . ' in ' . SITE_NAME . '.';
    $latest = 0;
    foreach ($cat_files as $f) {
        $latest = max($latest, $f['mtime']);
    }

    $items = [];
    $pos = 0;
    foreach ($cat_files as $f) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => ++$pos,
            'name' => $f['title'] !== '' ? $f['title'] : pathinfo($f['name'], PATHINFO_FILENAME),
            'url' => $f['view'],
        ];
    }
    $cat_ld = [
        schema_website(),
        schema_publisher(),
        [
            '@type' => 'BreadcrumbList',
            '@id' => $cat_url . '#breadcrumb',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => SITE_NAME, 'item' => BASE_URL],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Categories', 'item' => BASE_URL],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $cat_name, 'item' => $cat_url],
            ],
        ],
        [
            '@type' => 'CollectionPage',
            '@id' => $cat_url . '#page',
            'name' => $cat_name,
            'description' => $cat_desc,
            'url' => $cat_url,
            'inLanguage' => SITE_LANGUAGE,
            'isPartOf' => ['@id' => BASE_URL . '#website'],
            'breadcrumb' => ['@id' => $cat_url . '#breadcrumb'],
            'about' => ['@type' => 'Thing', 'name' => $cat_name],
            'mainEntity' => ['@id' => $cat_url . '#list'],
        ],
        [
            '@type' => 'ItemList',
            '@id' => $cat_url . '#list',
            'name' => $cat_name,
            'numberOfItems' => count($items),
            'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
            'itemListElement' => $items,
        ],
    ];

    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    send_public_cache_headers(300);
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($cat_name) ?> &ndash; <?= e(SITE_NAME) ?></title>
<meta name="description" content="<?= e($cat_desc) ?>">
<link rel="canonical" href="<?= e($cat_url) ?>">
<meta name="robots" content="<?= SITE_INDEXABLE ? 'index, follow' : 'noindex, nofollow' ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($cat_name) ?>">
<meta property="og:description" content="<?= e($cat_desc) ?>">
<meta property="og:url" content="<?= e($cat_url) ?>">
<meta name="twitter:card" content="summary">
<script type="application/ld+json"><?= schema_emit($cat_ld) ?></script>
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
</head>
<body>
<header class="topbar">
    <h1><a class="site-home" href="<?= e(BASE_URL) ?>"><?= e(SITE_NAME) ?></a></h1>
    <span class="running-head">Category</span>
    <nav class="crumbs">
        <a href="<?= e(BASE_URL) ?>">Home</a>
        <span class="sep">/</span><span><?= e($cat_name) ?></span>
    </nav>
</header>
<main class="layout">
    <section class="listing">
        <div class="filter-bar">
            <div class="filter-chips">
                <?php foreach (array_keys($reg) as $name): ?>
                    <a class="chip chip-cat<?= $name === $cat_name ? ' chip-active' : '' ?>" href="<?= e(url_category($name)) ?>"><?= e($name) ?> <span class="chip-count"><?= (int) $reg[$name] ?></span></a>
                <?php endforeach; ?>
            </div>
        </div>
        <h2 class="archive-title"><?= e($cat_name) ?></h2>
        <p class="archive-note"><?= count($cat_files) ?> document<?= count($cat_files) === 1 ? '' : 's' ?><?php if ($latest > 0): ?>, most recent <?= e(date('j F Y', $latest)) ?><?php endif; ?></p>
        <table>
            <thead><tr><th>Name</th><th>Folder</th><th>Size</th><th>Modified</th></tr></thead>
            <tbody>
            <?php foreach ($cat_files as $f): ?>
                <?php $label = $f['title'] !== '' ? $f['title'] : pathinfo($f['name'], PATHINFO_FILENAME); ?>
                <tr class="row-file">
                    <td data-sort-name="<?= e(mb_strtolower($label !== '' ? $label : $f['name'])) ?>">
                        <div class="file-meta">
                            <a class="file-title" href="<?= e($f['view']) ?>"><?= e($label) ?></a>
                            <?php if ($f['desc'] !== ''): ?><span class="file-desc"><?= e($f['desc']) ?></span><?php endif; ?>
                            <?php if ($f['tags']): ?>
                                <span class="file-chips">
                                    <?php foreach ($f['tags'] as $t): ?><span class="chip chip-tag chip-mini">#<?= e($t) ?></span><?php endforeach; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><a href="<?= e(url_dir($f['dir'])) ?>"><?= e($f['dir'] === '' ? SITE_NAME : $f['dir']) ?></a></td>
                    <td><?= e($f['size']) ?></td>
                    <td><?= e(date('Y-m-d', $f['mtime'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
<?php render_footer(); ?>
</body>
</html>
    <?php
    exit;
}

/* ------------------------------------------------------------------ */
/* Per-file detail page (indexable)                                    */
/* ------------------------------------------------------------------ */
if (isset($_GET['view'])) {
    $abs = resolve_path((string) $_GET['view']);
    if ($abs !== null && is_dir($abs)) {
        // Pretty URL pointed at a folder: show the listing instead.
        $_GET['dir'] = (string) $_GET['view'];
        unset($_GET['view']);
    }
}
if (isset($_GET['view'])) {
    $requested = (string) $_GET['view'];

    /* Canonical slugs and their aliases are resolved first, so a document's
       permanent address always wins over anything derived from the current
       filename. Only if neither matches do we fall back to the older
       path-derived slugs, which then redirect forward. */
    $doc_hit = document_resolve_slug($requested);
    if ($doc_hit !== null) {
        if ($doc_hit['redirect']) {
            header('Location: ' . document_url($doc_hit['doc']), true, 301);
            exit;
        }
        $doc_path = (string) ($doc_hit['doc']['file_path'] ?? '');
        $doc_abs  = $doc_path !== '' ? resolve_path($doc_path) : null;
        if ($doc_abs !== null && is_file($doc_abs)
            && !is_excluded(basename($doc_path), $doc_path)) {
            $found = [$doc_abs, $doc_path, false];
        } else {
            // The record exists but its file does not. Fall through to the
            // ordinary not-found page rather than disclosing the stored path.
            $found = null;
        }
    } else {
        $found = resolve_view($requested);
        // An older path-derived URL that still resolves is sent forward to
        // the document's canonical address.
        if ($found !== null) {
            $legacy_doc = document_for_path($found[1]);
            if ($legacy_doc !== null) {
                $canonical = document_url($legacy_doc);
                $current   = PRETTY_URLS
                    ? rtrim(BASE_URL, '/') . '/' . str_replace('%2F', '/', rawurlencode(trim($requested, '/'))) . '/'
                    : BASE_URL . '?view=' . rawurlencode(trim($requested, '/'));
                if ($canonical !== $current) {
                    header('Location: ' . $canonical, true, 301);
                    exit;
                }
            }
        }
    }
    if ($found === null) {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        send_security_headers();
        exit('<!DOCTYPE html><html lang="' . e(SITE_LANGUAGE) . '"><head><meta charset="utf-8">'
            . '<title>Not found &ndash; ' . e(SITE_NAME) . '</title>'
            . '<meta name="robots" content="noindex">'
            . site_icon_tags()
                        . '<link rel="stylesheet" href="' . e(asset_url('assets/css/style.css')) . '"></head><body>'
            . '<main class="detail"><h2 class="detail-title">Not found</h2>'
            . '<p class="detail-desc">No such document in this library.</p>'
            . '<p class="detail-actions"><a class="btn" href="' . e(BASE_URL) . '">Back to the library</a></p>'
            . '</main></body></html>');
    }
    [$abs, $rel, $legacy] = $found;
    if ($legacy) {
        // Old URL carrying the file extension: send it to the slug URL.
        header('Location: ' . url_view($rel), true, 301);
        exit;
    }
    $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $kind = $ext === 'pdf' ? 'pdf' : ($ext === 'md' ? 'md' : (isset($mime_map[$ext]) && $ext !== 'txt' ? 'image' : 'other'));
    $meta = meta_load();
    $m     = $meta[$rel] ?? [];
    $title = ($m['title'] ?? '') !== '' ? $m['title'] : pathinfo($rel, PATHINFO_FILENAME);
    $desc  = $m['desc'] ?? '';
    $cat   = $m['category'] ?? '';
    $tags  = $m['tags'] ?? [];
    $document_type = (string) ($m['document_type'] ?? '');
    $document_type_label = document_types()[$document_type] ?? '';
    $transcript = trim((string) ($m['transcript'] ?? ''));
    $mtime = (int) filemtime($abs);
    $size  = human_size((int) filesize($abs));
    $raw   = url_raw_effective($rel, $m);
    $pdf_full_access  = pdf_full_access($rel, $m);
    $pdf_is_hidden    = $kind === 'pdf' && pdf_access_of($m) === 'hidden' && pdf_access_enforced();
    $hidden_preview_url = '';
    if ($pdf_is_hidden) {
        $placeholder_rel = trim((string) ($m['placeholder_image'] ?? ''));
        $placeholder_abs = $placeholder_rel !== '' ? resolve_path($placeholder_rel) : null;
        if ($placeholder_abs !== null && is_file($placeholder_abs)) {
            $hidden_preview_url = url_raw($placeholder_rel);
        } elseif (pdf_blur_available()) {
            $hidden_preview_url = BASE_URL . '?action=pdf_preview&file=' . rawurlencode($rel);
        }
    }
    $view  = url_view($rel);
    /* Search-result title and description. When set they are used verbatim
       and the site name is not appended, because the field exists precisely
       so the whole tag can be controlled. Empty falls back to what the page
       itself shows, which is what every earlier release did. */
    $seo_title = trim((string) ($m['seo_title'] ?? ''));
    $seo_desc  = trim((string) ($m['seo_desc'] ?? ''));

    $head_title = $seo_title !== '' ? $seo_title : $title . ' – ' . SITE_NAME;
    $meta_desc  = $seo_desc !== ''
        ? $seo_desc
        : ($desc !== ''
            ? $desc
            : trim($title . ($cat !== '' ? ' — ' . $cat : '') . ' (' . strtoupper($ext) . ', ' . $size . ')'));

    $file_node = schema_file($rel, $abs, $meta, $mime_map, true);
    $page_node = [
        '@type' => 'ItemPage',
        '@id' => $view . '#page',
        'name' => $title,
        'description' => $meta_desc,
        'url' => $view,
        'inLanguage' => SITE_LANGUAGE,
        'isPartOf' => ['@id' => BASE_URL . '#website'],
        'breadcrumb' => ['@id' => $view . '#breadcrumb'],
        'dateModified' => date('c', $mtime),
        'mainEntity' => ['@id' => $view . '#file'],
    ];
    if ($kind === 'image') {
        $page_node['primaryImageOfPage'] = ['@id' => $view . '#file'];
    }
    $ld = [
        schema_website(),
        schema_publisher(),
        schema_breadcrumbs(dirname($rel) === '.' ? '' : dirname($rel), $title, $view),
        $page_node,
        $file_node,
    ];
    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    send_public_cache_headers(300);
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($head_title) ?></title>
<meta name="description" content="<?= e($meta_desc) ?>">
<link rel="canonical" href="<?= e($view) ?>">
<meta name="robots" content="<?= SITE_INDEXABLE ? 'index, follow' : 'noindex, nofollow' ?>">
<meta property="og:type" content="<?= $kind === 'image' ? 'website' : 'article' ?>">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($seo_title !== '' ? $seo_title : $title) ?>">
<meta property="og:description" content="<?= e($meta_desc) ?>">
<meta property="og:url" content="<?= e($view) ?>">
<?php if ($kind === 'image'): ?>
<meta property="og:image" content="<?= e($raw) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?= e($raw) ?>">
<?php else: ?>
<meta name="twitter:card" content="summary">
<?php endif; ?>
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($meta_desc) ?>">
<script type="application/ld+json"><?= schema_emit($ld) ?></script>
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
</head>
<body>
<header class="topbar">
    <h1><a class="site-home" href="<?= e(url_dir('')) ?>"><?= e(SITE_NAME) ?></a></h1>
    <nav class="crumbs">
        <a href="<?= e(url_dir('')) ?>">Home</a>
        <?php
        $acc = '';
        foreach (array_filter(explode('/', dirname($rel) === '.' ? '' : dirname($rel))) as $part) {
            $acc = ltrim($acc . '/' . $part, '/');
            echo '<span class="sep">/</span><a href="' . e(url_dir($acc)) . '">' . e($part) . '</a>';
        }
        ?>
        <span class="sep">/</span><span><?= e($title) ?></span>
    </nav>
</header>
<main class="detail">
    <article>
        <h2 class="detail-title"><?= e($title) ?></h2>
        <?php if ($desc !== ''): ?><p class="detail-desc"><?= e($desc) ?></p><?php endif; ?>
        <?php if ($cat !== '' || $tags): ?>
        <p class="detail-chips">
            <?php if ($cat !== ''): ?><a class="chip chip-cat" href="<?= e(url_category($cat)) ?>"><?= e($cat) ?></a><?php endif; ?>
            <?php foreach ($tags as $t): ?><span class="chip chip-tag">#<?= e($t) ?></span><?php endforeach; ?>
        </p>
        <?php endif; ?>
        <?php
        /* One line of facts, above the document rather than stranded between
           it and the buttons. The document's own date is shown when it has
           one: for an archive the file's modification time says when the scan
           was made, which is rarely what the reader wants to know. */
        $doc_date  = document_date_parse((string) ($m['doc_date'] ?? ''));
        $facts     = [];
        if ($document_type_label !== '') { $facts[] = e($document_type_label); }
        $facts[]   = e(strtoupper($ext));
        $facts[]   = e($size);
        $facts[]   = $doc_date['display'] !== ''
            ? e($doc_date['display'])
            : 'Updated ' . e(date('j F Y', $mtime));
        ?>
        <p class="detail-facts detail-facts-lead"><?= implode(' <span class="sep">&middot;</span> ', $facts) ?></p>
        <figure class="detail-media">
            <?php if ($pdf_is_hidden): ?>
                <?php if ($hidden_preview_url !== ''): ?>
                    <img class="document-restricted-preview" src="<?= e($hidden_preview_url) ?>" alt="Redacted preview of <?= e($title) ?>">
                <?php endif; ?>
                <p class="document-restricted">
                    The original scan is not publicly available. A verified transcription is provided below.
                </p>
            <?php elseif ($kind === 'image'): ?>
                <img src="<?= e(url_thumb($rel, 1280, $m)) ?>" alt="<?= e($meta_desc) ?>" loading="lazy">
                <?php if (image_needs_conversion($rel) && image_can_derive($rel)): ?>
                    <figcaption class="detail-note">
                        Shown as a converted preview because browsers cannot display
                        <?= e(strtoupper($ext)) ?> files. The direct link gives the untouched original.
                    </figcaption>
                <?php endif; ?>
            <?php elseif ($kind === 'pdf'): ?>
                <div class="pdf-preview" data-pdf-url="<?= e($raw) ?>" data-pdfjs-base="<?= e(BASE_URL) ?>lib/pdfjs/" data-flip-url="<?= e(url_flipbook($rel)) ?>" data-pdf-title="<?= e($title) ?>">
                    <div class="pdf-preview-canvas-wrap">
                        <canvas class="pdf-preview-canvas" aria-label="First page of <?= e($title) ?>"></canvas>
                        <p class="pdf-preview-status">Loading preview&hellip;</p>
                    </div>
                </div>
            <?php elseif ($kind === 'md'): ?>
                <div class="md-content"><?= render_markdown($abs) ?></div>
            <?php else: ?>
                <p><a class="btn" href="<?= e($raw) ?>">Download file</a></p>
            <?php endif; ?>
        </figure>
        <p class="detail-actions">
            <?php if ($kind === 'pdf' && !$pdf_is_hidden): ?><a class="btn" href="<?= e(url_flipbook($rel)) ?>">Flip view</a><?php endif; ?>
            <?php if ($kind !== 'other' && !$pdf_is_hidden): ?><button id="btn-print" class="btn btn-ghost">Print</button><?php endif; ?>
            <?php if ($pdf_full_access): ?><a class="btn btn-ghost" href="<?= e($raw) ?>">Direct link</a><?php endif; ?>
        </p>
        <?php if ($transcript !== ''): ?>
        <section class="document-transcript">
            <h3>Document transcription</h3>
            <div class="transcript-content"><?= nl2br(e($transcript)) ?></div>
        </section>
        <?php endif; ?>
    </article>
</main>
<?php render_footer(); ?>
<?php if (!$pdf_is_hidden): ?>
<iframe id="print-frame" class="print-frame" title="print helper" data-kind="<?= e($kind) ?>" data-url="<?= e($raw) ?>"></iframe>
<?php endif; ?>
<script src="<?= e(asset_url('assets/js/view.js')) ?>" defer></script>
</body>
</html>
    <?php
    exit;
}

/* ------------------------------------------------------------------ */
/* Directory listing                                                   */
/* ------------------------------------------------------------------ */
$rel_dir = (string) ($_GET['dir'] ?? '');
$abs_dir = resolve_path($rel_dir);
if ($abs_dir === null || !is_dir($abs_dir)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    exit('<!DOCTYPE html><html lang="' . e(SITE_LANGUAGE) . '"><head><meta charset="utf-8">'
        . '<meta name="robots" content="noindex"><title>Folder not found &ndash; ' . e(SITE_NAME) . '</title>'
        . '<link rel="stylesheet" href="' . e(asset_url('assets/css/style.css')) . '"></head><body>'
        . '<main class="detail"><h2 class="detail-title">Folder not found</h2>'
        . '<p class="detail-actions"><a class="btn" href="' . e(BASE_URL) . '">Back to the library</a></p>'
        . '</main></body></html>');
}
$rel_dir = trim(str_replace(realpath(BASE_DIR), '', $abs_dir), DIRECTORY_SEPARATOR);
$rel_dir = str_replace(DIRECTORY_SEPARATOR, '/', $rel_dir);

$meta = meta_load();

$dirs  = [];
$files = [];
foreach (scandir($abs_dir) as $entry) {
    if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
        continue;
    }
    $rel_entry = ltrim($rel_dir . '/' . $entry, '/');
    if (is_excluded($entry, $rel_entry)) {
        continue;
    }
    $abs_entry = safe_entry_realpath($abs_dir . DIRECTORY_SEPARATOR . $entry);
    if ($abs_entry === null) {
        continue;
    }
    if (is_dir($abs_entry)) {
        $dirs[] = ['name' => $entry, 'rel' => $rel_entry];
    } elseif (is_file($abs_entry)) {
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        $m = $meta[$rel_entry] ?? [];
        $pdf_access  = pdf_access_of($m);
        $full_access = pdf_full_access($rel_entry, $m);
        // A "hidden" PDF offers no preview affordance of any kind: the hover
        // card, the Preview button, and the listing hotlink must all have
        // nothing real to point at.
        $previewable = isset($mime_map[$ext]) && $ext !== 'txt'
            && !($ext === 'pdf' && $pdf_access === 'hidden' && pdf_access_enforced());
        $files[] = [
            'name' => $entry,
            'rel'  => $rel_entry,
            'ext'  => $ext,
            'size' => human_size((int) filesize($abs_entry)),
            // Raw values for sorting. "1.5 MB" and "9 B" do not compare as
            // strings, and a displayed date like "Oktober 1998" cannot be
            // ordered without its machine form.
            'bytes' => (int) filesize($abs_entry),
            'mtime' => date('Y-m-d H:i', (int) filemtime($abs_entry)),
            'mtime_ts' => (int) filemtime($abs_entry),
            'previewable' => $previewable,
            'kind' => $ext === 'pdf' ? 'pdf' : ($ext === 'md' ? 'md' : (isset($mime_map[$ext]) && $ext !== 'txt' ? 'image' : 'other')),
            'title' => $m['title'] ?? '',
            'desc'  => $m['desc'] ?? '',
            'slug'  => (string) ((document_for_path($rel_entry ?? '') ?? [])['slug'] ?? ''),
            'category' => $m['category'] ?? '',
            'tags'  => $m['tags'] ?? [],
            'document_type' => $m['document_type'] ?? '',

            'doc_date' => $m['doc_date'] ?? '',
            'seo_title' => $m['seo_title'] ?? '',
            'seo_desc' => $m['seo_desc'] ?? '',
            'transcript' => $m['transcript'] ?? '',
            'pdf_access' => $pdf_access,
            'language' => $m['language'] ?? '',
            'placeholder_image' => $m['placeholder_image'] ?? '',
            'full_access' => $full_access,
            'hotlink' => $previewable ? url_raw_effective($rel_entry, $m) : '',
            'render'  => url_render($rel_entry),
            'view'    => url_view($rel_entry),
        ];
    }
}

$crumbs = [];
$cat_register = category_register(index_all_files($mime_map));
$all_categories = [];
foreach ($files as $f) {
    if ($f['category'] !== '' && !in_array($f['category'], $all_categories, true)) {
        $all_categories[] = $f['category'];
    }
}
sort($all_categories);
$acc = '';
foreach (array_filter(explode('/', $rel_dir)) as $part) {
    $acc = ltrim($acc . '/' . $part, '/');
    $crumbs[] = ['name' => $part, 'rel' => $acc];
}

send_security_headers();
send_public_cache_headers(300);

$installer_present = is_admin() && is_file(__DIR__ . '/install.php');

/* Structured data for this listing: the collection, its breadcrumb trail,
   and every file it contains as a fully described list item. */
$list_items = [];
$pos = 0;
foreach ($files as $f) {
    $list_items[] = [
        '@type' => 'ListItem',
        'position' => ++$pos,
        'name' => $f['title'] !== '' ? $f['title'] : pathinfo($f['name'], PATHINFO_FILENAME),
        'url' => $f['view'],
    ];
}
$collection_url = url_dir($rel_dir);
$collection_name = $rel_dir === '' ? SITE_NAME : basename($rel_dir);
$collection_node = [
    '@type' => 'CollectionPage',
    '@id' => $collection_url . '#page',
    'name' => $collection_name,
    'description' => $rel_dir === ''
        ? SITE_DESCRIPTION
        : 'Documents and images in ' . $collection_name . '.',
    'url' => $collection_url,
    'inLanguage' => SITE_LANGUAGE,
    'isPartOf' => ['@id' => BASE_URL . '#website'],
    'breadcrumb' => ['@id' => $collection_url . '#breadcrumb'],
    'mainEntity' => ['@id' => $collection_url . '#list'],
];
if ($dirs) {
    $collection_node['hasPart'] = array_map(function (array $d): array {
        return [
            '@type' => 'CollectionPage',
            '@id' => url_dir($d['rel']) . '#page',
            'name' => $d['name'],
            'url' => url_dir($d['rel']),
        ];
    }, $dirs);
}
$listing_ld = [
    schema_website(),
    schema_publisher(),
    schema_breadcrumbs($rel_dir, '', $collection_url),
    $collection_node,
    [
        '@type' => 'ItemList',
        '@id' => $collection_url . '#list',
        'name' => $collection_name,
        'numberOfItems' => count($list_items),
        'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
        'itemListElement' => $list_items,
    ],
];
?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php $page_title = $rel_dir === '' ? SITE_NAME : basename($rel_dir) . ' – ' . SITE_NAME; ?>
<title><?= e($page_title) ?></title>
<meta name="description" content="<?= e($rel_dir === '' ? SITE_DESCRIPTION : basename($rel_dir) . ': documents and images in ' . SITE_NAME) ?>">
<link rel="canonical" href="<?= e(url_dir($rel_dir)) ?>">
<meta name="robots" content="<?= SITE_INDEXABLE ? 'index, follow' : 'noindex, nofollow' ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($page_title) ?>">
<meta property="og:url" content="<?= e(url_dir($rel_dir)) ?>">
<meta name="twitter:card" content="summary">
<script type="application/ld+json"><?= schema_emit($listing_ld) ?></script>
<?php if (SITEMAP_ENABLED): ?>
<link rel="sitemap" type="application/xml" href="<?= e(PRETTY_URLS ? BASE_URL . 'sitemap.xml' : BASE_URL . '?action=sitemap') ?>">
<?php endif; ?>
<?= site_icon_tags() ?><?= stylesheet_tag() ?>
</head>
<body>
<header class="topbar">
    <h1><?= e(SITE_NAME) ?></h1>
    <span class="running-head"><?= e($rel_dir === '' ? 'Collection' : $rel_dir) ?></span>
    <nav class="crumbs">
        <a href="<?= e(url_dir('')) ?>">Home</a>
        <?php foreach ($crumbs as $c): ?>
            <span class="sep">/</span>
            <a href="<?= e(url_dir($c['rel'])) ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php $folio_pages_menu = pages_menu(); if ($folio_pages_menu): ?>
    <nav class="page-nav" aria-label="Pages">
        <?php foreach ($folio_pages_menu as $mslot => $mrec): ?>
            <a href="<?= e(url_page($mslot)) ?>"><?= e(page_menu_label($mslot, $mrec)) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <?php if (count($files) >= 3): ?>
    <button type="button" class="nav-search-open" id="nav-search-open"
            aria-label="Search this folder" aria-expanded="false" aria-controls="search-overlay">
        <span class="nav-search-glyph" aria-hidden="true"></span>
    </button>
    <?php endif; ?>
    <div class="theme-picker" role="group" aria-label="Colour scheme">
        <button data-set-theme="folio" title="Folio"></button>
        <button data-set-theme="ledger" title="Ledger"></button>
        <button data-set-theme="garden" title="Garden"></button>
        <button data-set-theme="night" title="Night"></button>
    </div>
    <?php if (is_admin()): ?>
        <a class="admin-link" href="<?= e(BASE_URL) ?>?action=settings">Settings</a>
        <a class="admin-link" href="<?= e(BASE_URL) ?>?action=crawlers">Crawlers</a>
        <a class="admin-link" href="<?= e(BASE_URL) ?>?action=analytics">Analytics</a>
        <a class="admin-link" href="<?= e(BASE_URL) ?>?action=users">Accounts</a>
        <a class="admin-link" href="<?= e(BASE_URL) ?>?action=catalogue">Catalogue</a>
            <a class="admin-link" href="<?= e(BASE_URL) ?>?action=docs">Docs</a>
        <a class="admin-link" href="<?= e(BASE_URL) ?>?action=pages">Pages</a>
        <a class="admin-link" href="<?= e(BASE_URL) ?>?action=diagnostics">Diagnostics</a>
        <form method="post" action="<?= e(BASE_URL) ?>" class="logout-form">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="admin-link logout-button">Log out</button>
        </form>
    <?php else: ?>
        <?php if (SHOW_ADMIN_LINK): ?>
        <details class="login-box"<?= $login_error !== '' ? ' open' : '' ?>>
            <summary class="admin-link">Admin</summary>
            <form method="post" action="<?= e(BASE_URL) ?>" class="login-form">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf" value="<?= e(login_token()) ?>">
                <?php if ($login_error !== ''): ?>
                    <p class="login-error"><?= e($login_error) ?></p>
                <?php endif; ?>
                <input type="text" name="username" placeholder="Username" autocomplete="username" required>
                <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
                <button type="submit" class="btn">Log in</button>
            </form>
        </details>
        <?php endif; ?>
    <?php endif; ?>
</header>

<main class="layout">
    <section class="listing">
        <?php if ($installer_present): ?>
        <p class="msg msg-bad install-warn"><strong>Security notice.</strong> <code>install.php</code> is still present in the Folio folder. It is inert while <code>config.php</code> exists, but delete it now over FTP to keep the installation tidy.</p>
        <?php endif; ?>
        <?php if ($cat_register || count($files) >= 3): ?>
        <div class="filter-bar" id="filter-bar">
            <?php if ($cat_register): ?>
            <div class="filter-chips">
                <?php foreach ($cat_register as $c => $n): ?>
                    <a class="chip chip-cat" data-filter-cat="<?= e($c) ?>" href="<?= e(url_category($c)) ?>"><?= e($c) ?> <span class="chip-count"><?= (int) $n ?></span></a>
                <?php endforeach; ?>
                <button class="chip chip-clear" id="filter-clear" hidden>&times; Clear filter</button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <table>
            <thead>
                <tr>
                    <th aria-sort="none" data-sort-key="name"><button type="button" class="col-sort">Name<span class="sort-mark" aria-hidden="true"></span></button></th>
                    <th aria-sort="none" data-sort-key="size"><button type="button" class="col-sort">Size<span class="sort-mark" aria-hidden="true"></span></button></th>
                    <th aria-sort="none" data-sort-key="date"><button type="button" class="col-sort">Date<span class="sort-mark" aria-hidden="true"></span></button></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rel_dir !== ''): ?>
                <tr class="row-dir">
                    <td colspan="4"><a href="<?= e(url_dir(dirname($rel_dir) === '.' ? '' : dirname($rel_dir))) ?>">&#8617; Up one level</a></td>
                </tr>
            <?php endif; ?>
            <?php foreach ($dirs as $d): ?>
                <tr class="row-dir">
                    <td colspan="4"><a href="<?= e(url_dir($d['rel'])) ?>">&#128193; <?= e($d['name']) ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ($files as $f): ?>
                <?php $label = $f['title'] !== '' ? $f['title'] : pathinfo($f['name'], PATHINFO_FILENAME); ?>
                <tr class="row-file" data-file="<?= e($f['rel']) ?>" data-category="<?= e($f['category']) ?>" data-tags="<?= e(implode(',', $f['tags'])) ?>" data-hover-kind="<?= e($f['kind']) ?>" data-hover-url="<?= e($f['kind'] === 'image'
                        ? url_thumb($f['rel'], 320)
                        : ($f['kind'] === 'pdf'
                            ? (image_can_derive($f['rel']) ? url_thumb($f['rel'], 320) : $f['hotlink'])
                            : '')) ?>" data-hover-thumb="<?= ($f['kind'] === 'pdf' && image_can_derive($f['rel'])) ? '1' : '' ?>" data-hover-title="<?= e($label) ?>" data-hover-meta="<?= e($f['size'] . ' &middot; ' . $f['mtime']) ?>">
                    <td data-sort-name="<?= e(function_exists('mb_strtolower') ? mb_strtolower($label) : strtolower($label)) ?>">
                        <div class="file-meta">
                            <a class="file-title" href="<?= e($f['view']) ?>"><?= e($label) ?></a>
                            <?php if ($f['desc'] !== ''): ?>
                                <span class="file-desc"><?= e($f['desc']) ?></span>
                            <?php endif; ?>
                            <?php if ($f['category'] !== '' || $f['tags']): ?>
                                <?php if ($f['category'] !== ''): ?>
                                <span class="file-cats">
                                        <a class="chip chip-cat chip-mini" data-filter-cat="<?= e($f['category']) ?>" href="<?= e(url_category($f['category'])) ?>"><?= e($f['category']) ?></a>
                                </span>
                                <?php endif; ?>
                                <?php if ($f['tags']): ?>
                                <span class="file-tags">
                                    <?php foreach ($f['tags'] as $t): ?>
                                        <button class="chip chip-tag chip-mini" data-filter-tag="<?= e($t) ?>">#<?= e($t) ?></button>
                                    <?php endforeach; ?>
                                </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (is_admin()): ?>
                        <form class="meta-form" method="post" hidden>
                            <input type="hidden" name="action" value="meta">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="file" value="<?= e($f['rel']) ?>">
                            <input type="text" name="title" maxlength="200" placeholder="Title" value="<?= e($f['title']) ?>">
                            <input type="text" name="desc" maxlength="500" placeholder="Short description" value="<?= e($f['desc']) ?>">
                            <input type="text" name="category" maxlength="50" placeholder="Category" value="<?= e($f['category']) ?>" list="category-list">
                            <input type="text" name="tags" placeholder="Tags, comma-separated" value="<?= e(implode(', ', $f['tags'])) ?>">
                            <label class="meta-form-label">
                                Document type
                                <select name="document_type">
                                    <option value="">Select type</option>
                                    <?php foreach (document_types() as $dt_value => $dt_label): ?>
                                        <option value="<?= e($dt_value) ?>" <?= $f['document_type'] === $dt_value ? 'selected' : '' ?>><?= e($dt_label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <input type="text" name="language" maxlength="35" placeholder="Language (e.g. en, ms, ar)" value="<?= e($f['language']) ?>">
                            <label class="meta-form-label">
                                Date of the document
                                <input type="text" name="doc_date" maxlength="60"
                                       placeholder="1996, Oktober 1998, 30/11/1991"
                                       value="<?= e($f['doc_date']) ?>">
                                <span class="field-note">
                                    When the document itself is from — not when the file was
                                    uploaded. A year alone is fine, and so is an approximation.
                                </span>
                            </label>
                            <label class="meta-form-label">
                                Search title
                                <input type="text" name="seo_title" maxlength="60"
                                       placeholder="Defaults to the title above"
                                       value="<?= e($f['seo_title']) ?>">
                                <span class="field-note">
                                    Up to 60 characters, which is roughly what a search result shows
                                    before truncating. Set it and it becomes the whole page title, with
                                    no site name appended; leave it empty to keep the current behaviour.
                                </span>
                            </label>
                            <label class="meta-form-label">
                                Search description
                                <textarea name="seo_desc" maxlength="150" rows="2"
                                          placeholder="Defaults to the description above"><?= e($f['seo_desc']) ?></textarea>
                                <span class="field-note">
                                    Up to 150 characters. This is the snippet under the link in a
                                    search result, and it is what social cards show too.
                                </span>
                            </label>
                            <label class="meta-form-label">
                                URL slug
                                <input type="text" name="slug" maxlength="160" placeholder="url-slug" value="<?= e($f['slug']) ?>">
                                <span class="field-note">
                                    The permanent public address for this document. It does not change
                                    when the file is renamed or moved over FTP. Changing it here leaves
                                    a permanent redirect from the old address.
                                </span>
                            </label>
                            <?php if ($f['kind'] === 'pdf'): ?>
                            <label class="meta-form-label">
                                PDF access
                                <select name="pdf_access">
                                    <option value="public" <?= $f['pdf_access'] === 'public' ? 'selected' : '' ?>>Public — direct link and download allowed</option>
                                    <option value="viewer" <?= $f['pdf_access'] === 'viewer' ? 'selected' : '' ?>>Viewer only — no direct link or download</option>
                                    <option value="hidden" <?= $f['pdf_access'] === 'hidden' ? 'selected' : '' ?>>Hidden — no preview, only the transcription</option>
                                </select>
                            </label>
                            <?php if (!pdf_access_enforced() && $f['pdf_access'] !== 'public'): ?>
                                <p class="field-note">Not enforced yet on this server — behaves as Public until the PDF access preflight is confirmed on the Crawlers screen.</p>
                            <?php endif; ?>
                            <input type="text" name="placeholder_image" maxlength="255" placeholder="Placeholder image path for Hidden (e.g. redactions/cert-blur.jpg)" value="<?= e($f['placeholder_image']) ?>">
                            <?php endif; ?>
                            <textarea name="transcript" maxlength="100000" placeholder="Document transcription (corrected OCR or manual transcript)" rows="4"><?= e($f['transcript']) ?></textarea>
                            <div class="meta-form-actions">
                                <button type="submit" class="btn-small">Save</button>
                                <button type="button" class="btn-small btn-ghost meta-cancel">Cancel</button>
                                <span class="meta-filename" title="Actual file"><?= e($f['name']) ?></span>
                            </div>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td class="col-size" data-label="Size" data-sort-size="<?= (int) $f['bytes'] ?>"><?= e($f['size']) ?></td>
                    <?php $fd = document_date_parse((string) ($f['doc_date'] ?? '')); ?>
                    <td class="col-modified" data-label="Date" data-sort-date="<?= e($fd['iso'] !== '' ? str_pad($fd['iso'], 10, '-01') : date('Y-m-d', (int) $f['mtime_ts'])) ?>" data-sort-dated="<?= $fd['iso'] !== '' ? '1' : '0' ?>"><?php if ($fd['display'] !== ''): ?><?php if ($fd['iso'] !== ''): ?><time datetime="<?= e($fd['iso']) ?>"><?= e($fd['display']) ?></time><?php else: ?><?= e($fd['display']) ?><?php endif; ?><?php else: ?><span class="file-date-fallback" title="No document date recorded; this is when the file was last changed."><?= e($f['mtime']) ?></span><?php endif; ?></td>
                    <td class="row-actions">
                        <?php if ($f['previewable']): ?>
                            <button class="btn-small file-link" data-file="<?= e($f['rel']) ?>" data-kind="<?= e($f['kind']) ?>" data-raw-url="<?= e($f['hotlink']) ?>" data-render-url="<?= e($f['render']) ?>">Preview</button>
                        <?php endif; ?>
                        <?php if ($f['kind'] === 'pdf' && !($f['pdf_access'] === 'hidden' && pdf_access_enforced())): ?>
                            <a class="btn-small btn-ghost" href="<?= e(url_flipbook($f['rel'])) ?>">Flip view</a>
                        <?php endif; ?>
                        <?php if (is_admin()): ?>
                            <button class="btn-small btn-ghost meta-edit" title="Edit title and description">Edit</button>
                        <?php endif; ?>
                        <?php if (is_admin() && $f['kind'] === 'pdf' && ocr_available()): ?>
                            <button class="btn-small btn-ghost ocr-run"
                                    data-file="<?= e($f['rel']) ?>"
                                    title="Make this scanned PDF searchable. Takes a while; the original is not changed.">OCR</button>
                        <?php endif; ?>
                        <?php if (is_admin() && $f['kind'] === 'pdf' && tool_have('qpdf')): ?>
                            <button class="btn-small btn-ghost pdf-compress"
                                    data-file="<?= e($f['rel']) ?>"
                                    title="Make a smaller copy of this PDF without losing quality. Your file is not changed; you download the copy and replace it over FTP if you want it.">Compress</button>
                        <?php endif; ?>
                        <?php if ($f['hotlink'] !== ''): ?>
                            <button class="btn-small btn-ghost copy-link" data-hotlink="<?= e($f['hotlink']) ?>" title="Copy direct link">Link</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$dirs && !$files): ?>
                <tr><td colspan="4" class="empty">This folder is empty.</td></tr>
            <?php endif; ?>
            <tr class="row-search-empty" id="search-empty" hidden>
                <td colspan="4" class="empty">No files match that search.</td>
            </tr>
            </tbody>
        </table>
    </section>

    <aside class="preview" id="preview-pane" hidden>
        <div class="preview-head">
            <span id="preview-name"></span>
            <div class="preview-actions">
                <button id="btn-print" class="btn">Print</button>
                <button id="btn-close" class="btn btn-ghost">Close</button>
            </div>
        </div>
        <div class="preview-body" id="preview-body"></div>
    </aside>

    <aside class="hover-card" id="hover-card" aria-hidden="true" data-pdfjs-base="<?= e(BASE_URL) ?>lib/pdfjs/">
        <div class="hover-card-inner">
            <div class="hover-card-media" id="hover-card-media"></div>
            <div class="hover-card-caption">
                <span class="hover-card-title" id="hover-card-title"></span>
                <span class="hover-card-meta" id="hover-card-meta"></span>
            </div>
        </div>
    </aside>
</main>
<?php if (count($files) >= 3): ?>
<div class="search-overlay" id="search-overlay" hidden>
    <div class="search-overlay-panel" role="dialog" aria-modal="true" aria-label="Search this folder">
        <label class="search-overlay-label" for="listing-search">
            <span class="nav-search-glyph" aria-hidden="true"></span>
            <!-- Deliberately type="text", not type="search": a search input clears
                 itself on Escape at a level preventDefault cannot reach, which
                 wiped the filter when the reader only meant to dismiss the panel. -->
            <input type="text" inputmode="search" id="listing-search" class="listing-search"
                   placeholder="Search this folder&hellip;" autocomplete="off" spellcheck="false">
        </label>
        <button type="button" class="search-overlay-close" id="search-overlay-close" aria-label="Close search">&times;</button>
        <p class="search-overlay-hint">Type to filter. <kbd>Esc</kbd> closes.</p>
    </div>
</div>
<?php endif; ?>

<?php render_footer(); ?>

<iframe id="print-frame" class="print-frame" title="print helper"></iframe>
<?php if (is_admin()): ?>
<datalist id="category-list">
    <?php foreach ($all_categories as $c): ?>
        <option value="<?= e($c) ?>"></option>
    <?php endforeach; ?>
</datalist>
<?php endif; ?>
<script src="<?= e(asset_url('assets/js/app.js')) ?>" defer></script>
<?php if (is_admin()): ?><script src="<?= e(asset_url('assets/js/admin.js')) ?>" defer></script><?php endif; ?>
</body>
</html>
