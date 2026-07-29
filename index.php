<?php
/**
 * Folio
 * Lists files in a web folder, previews and prints PDFs and images.
 *
 * Drop this folder into your web root. Set BASE_DIR below to the folder
 * you want to browse. Defaults to an "uploads" directory beside this script.
 */

declare(strict_types=1);

/**
 * Settings live in config.php, which is not tracked in version control.
 * Copy config-sample.php to config.php and edit it. Anything not set there
 * falls back to the defaults below.
 */
if (is_file(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
}

defined('UPLOADS_DIRNAME')      || define('UPLOADS_DIRNAME', 'uploads');
defined('ADMIN_USERNAME')       || define('ADMIN_USERNAME', 'admin');
defined('ADMIN_PASSWORD_HASH')  || define('ADMIN_PASSWORD_HASH', 'CHANGE_ME');
defined('SITE_NAME')            || define('SITE_NAME', 'Folio');
defined('SITE_DESCRIPTION')     || define('SITE_DESCRIPTION', 'A reading library of documents, papers, and images.');
defined('PUBLISHER_TYPE')       || define('PUBLISHER_TYPE', 'Person');
defined('PUBLISHER_NAME')       || define('PUBLISHER_NAME', '');
defined('PUBLISHER_URL')        || define('PUBLISHER_URL', '');
defined('SITE_LANGUAGE')        || define('SITE_LANGUAGE', 'en');
defined('PRETTY_URLS')          || define('PRETTY_URLS', false);

define('BASE_DIR', realpath(__DIR__ . '/' . UPLOADS_DIRNAME) ?: __DIR__ . '/' . UPLOADS_DIRNAME);

ini_set('display_errors', '0');

$secure_cookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure_cookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (empty($_SESSION['sfm_csrf'])) {
    $_SESSION['sfm_csrf'] = bin2hex(random_bytes(32));
}

function csrf_token(): string
{
    return (string) $_SESSION['sfm_csrf'];
}

function csrf_valid(): bool
{
    return hash_equals((string) $_SESSION['sfm_csrf'], (string) ($_POST['csrf'] ?? ''));
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'");
}

function is_admin(): bool
{
    return !empty($_SESSION['sfm_admin']);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
// SCRIPT_NAME always points at index.php itself, even under rewrites.
$script = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/';
define('BASE_URL', $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $script);

/** Stable URL slug for a file, derived from its name without the extension. */
function file_slug(string $name): string
{
    return slugify(pathinfo($name, PATHINFO_FILENAME));
}

/** Slug path for a file: its folder plus its extensionless slug. */
function slug_path(string $rel): string
{
    $dir  = dirname($rel);
    $slug = file_slug(basename($rel));
    return ($dir === '.' || $dir === '') ? $slug : $dir . '/' . $slug;
}

function url_view(string $rel): string
{
    // e.g. https://menj.blog/documents/islamic-dilemma-refuted/ (no extension)
    $path = slug_path($rel);
    return PRETTY_URLS
        ? rtrim(BASE_URL, '/') . '/' . str_replace('%2F', '/', rawurlencode($path)) . '/'
        : BASE_URL . '?view=' . rawurlencode($path);
}

/**
 * Resolve a requested view path back to a real file.
 * Returns [absolute path, relative path, is_legacy] or null.
 * A legacy request is one that still carries the file extension; those are
 * redirected to the slug URL so previously indexed links keep their value.
 */
function resolve_view(string $req): ?array
{
    $req = trim(str_replace('\\', '/', $req), '/');
    if ($req === '') {
        return null;
    }

    // Legacy form: the path names the file itself, extension included.
    $direct = resolve_path($req);
    if ($direct !== null && is_file($direct)) {
        $rel = str_replace(DIRECTORY_SEPARATOR, '/', trim(substr($direct, strlen((string) realpath(BASE_DIR))), '/\\'));
        return [$direct, $rel, true];
    }

    $dir_rel = dirname($req) === '.' ? '' : dirname($req);
    $slug    = basename($req);
    $abs_dir = resolve_path($dir_rel);
    if ($abs_dir === null || !is_dir($abs_dir)) {
        return null;
    }
    foreach (scandir($abs_dir) as $entry) {
        if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
            continue;
        }
        $abs_e = $abs_dir . DIRECTORY_SEPARATOR . $entry;
        if (is_file($abs_e) && file_slug($entry) === $slug) {
            $rel = ltrim($dir_rel . '/' . $entry, '/');
            return [$abs_e, $rel, false];
        }
    }
    return null;
}

/**
 * The file's own URL: the uploads folder, served directly by the web server.
 * e.g. https://menj.blog/documents/uploads/islamic-dilemma-refuted.pdf
 */
function url_raw(string $rel): string
{
    return rtrim(BASE_URL, '/') . '/' . UPLOADS_DIRNAME . '/'
        . str_replace('%2F', '/', rawurlencode($rel));
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

/** URL-safe slug for a category name. */
function slugify(string $s): string
{
    $s = strtolower(trim($s));
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($t !== false) {
            $s = $t;
        }
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string) $s, '-');
}

function url_category(string $cat): string
{
    $slug = slugify($cat);
    return PRETTY_URLS
        ? rtrim(BASE_URL, '/') . '/category/' . rawurlencode($slug) . '/'
        : BASE_URL . '?cat=' . rawurlencode($slug);
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
    if (preg_match('#^raw/(.+)$#', $route, $m)) {
        $_GET['action'] = 'raw'; // legacy path, redirected below
        $_GET['file'] = rawurldecode($m[1]);
    } elseif (preg_match('#^category/([^/]+)/?$#', $route, $m)) {
        $_GET['cat'] = rawurldecode($m[1]);
    } elseif ($route !== '') {
        $_GET['view'] = rawurldecode($route);
    }
}

if (!is_dir(BASE_DIR)) {
    mkdir(BASE_DIR, 0755, true);
}

/**
 * Resolve a user-supplied relative path safely inside BASE_DIR.
 * Returns the absolute path, or null if it escapes the base.
 */
function resolve_path(string $rel): ?string
{
    $rel  = trim($rel, "/\\");
    $abs  = realpath(BASE_DIR . DIRECTORY_SEPARATOR . $rel);
    $base = realpath(BASE_DIR);
    if ($abs === false || $base === false) {
        return null;
    }
    if ($abs !== $base && strpos($abs, $base . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $abs;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
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

define('META_FILE', BASE_DIR . DIRECTORY_SEPARATOR . '.sfm-meta.json');

/** Load the title/description metadata map, keyed by relative path. */
function meta_load(): array
{
    if (!is_file(META_FILE)) {
        return [];
    }
    $data = json_decode((string) file_get_contents(META_FILE), true);
    return is_array($data) ? $data : [];
}

function meta_save(array $meta): bool
{
    return file_put_contents(
        META_FILE,
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    ) !== false;
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
    'txt'  => 'text/plain',
    'md'   => 'text/markdown',
];

function render_markdown(string $abs): string
{
    require_once __DIR__ . '/lib/Parsedown.php';
    $pd = new Parsedown();
    $pd->setSafeMode(true); // escapes raw HTML and neutralises unsafe links
    return $pd->text((string) file_get_contents($abs));
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
    return [
        '@type' => 'WebSite',
        '@id' => BASE_URL . '#website',
        'name' => SITE_NAME,
        'description' => SITE_DESCRIPTION,
        'url' => BASE_URL,
        'inLanguage' => SITE_LANGUAGE,
        'publisher' => ['@id' => BASE_URL . '#publisher'],
    ];
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
    $type  = schema_type($ext);
    $view  = url_view($rel);
    $raw   = url_raw($rel);
    $mtime = (int) filemtime($abs);
    $bytes = (int) filesize($abs);

    $node = [
        '@type' => $type,
        '@id' => $view . '#file',
        'name' => $title,
        'url' => $view,
        'contentUrl' => $raw,
        'encodingFormat' => $mime_map[$ext] ?? 'application/octet-stream',
        'fileFormat' => $mime_map[$ext] ?? 'application/octet-stream',
        'contentSize' => human_size($bytes),
        'dateModified' => date('c', $mtime),
        'datePublished' => date('c', $mtime),
        'uploadDate' => date('c', $mtime),
        'inLanguage' => SITE_LANGUAGE,
        'isPartOf' => ['@id' => BASE_URL . '#website'],
        'publisher' => ['@id' => BASE_URL . '#publisher'],
    ];
    if (($m['desc'] ?? '') !== '') {
        $node['description'] = $m['desc'];
    }
    if (!empty($m['category'])) {
        $node['genre'] = $m['category'];
    }
    if (!empty($m['tags'])) {
        $node['keywords'] = implode(', ', $m['tags']);
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
        $node['author'] = ['@id' => BASE_URL . '#publisher'];
        $node['headline'] = $title;
    }
    if ($full) {
        $node['mainEntityOfPage'] = ['@id' => $view . '#page'];
        $node['associatedMedia'] = [
            '@type' => 'DataDownload',
            'contentUrl' => $raw,
            'encodingFormat' => $mime_map[$ext] ?? 'application/octet-stream',
            'contentSize' => human_size($bytes),
        ];
        $node['potentialAction'] = [
            '@type' => 'DownloadAction',
            'target' => $raw,
        ];
    }
    return $node;
}

function schema_emit(array $graph): string
{
    return json_encode(
        ['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}

/**
 * Every file in the library, at any depth, with its metadata attached.
 * Used by category pages, the category register, and the sitemap.
 */
function index_all_files(array $mime_map): array
{
    $meta = meta_load();
    $out  = [];
    $walk = function (string $abs, string $rel) use (&$walk, &$out, $meta, $mime_map): void {
        foreach (scandir($abs) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                continue;
            }
            $abs_e = $abs . DIRECTORY_SEPARATOR . $entry;
            $rel_e = ltrim($rel . '/' . $entry, '/');
            if (is_dir($abs_e)) {
                $walk($abs_e, $rel_e);
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            $m   = $meta[$rel_e] ?? [];
            $out[$rel_e] = [
                'name' => $entry,
                'rel' => $rel_e,
                'abs' => $abs_e,
                'ext' => $ext,
                'dir' => $rel,
                'size' => human_size((int) filesize($abs_e)),
                'mtime' => (int) filemtime($abs_e),
                'title' => $m['title'] ?? '',
                'desc' => $m['desc'] ?? '',
                'category' => $m['category'] ?? '',
                'tags' => $m['tags'] ?? [],
                'previewable' => isset($mime_map[$ext]) && $ext !== 'txt',
                'kind' => $ext === 'pdf' ? 'pdf' : ($ext === 'md' ? 'md'
                    : (isset($mime_map[$ext]) && $ext !== 'txt' ? 'image' : 'other')),
                'view' => url_view($rel_e),
                'hotlink' => url_raw($rel_e),
            ];
        }
    };
    $walk((string) realpath(BASE_DIR), '');
    ksort($out);
    return $out;
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
        'readme' => ['file' => 'README.md', 'label' => 'Readme'],
        'upgrading' => ['file' => 'UPGRADING.md', 'label' => 'Upgrading'],
        'changelog' => ['file' => 'CHANGELOG.md', 'label' => 'Changelog'],
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
<html lang="en" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($docs[$key]['label']) ?> &ndash; <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e(BASE_URL) ?>assets/img/favicon.svg" type="image/svg+xml">
<link rel="alternate icon" href="<?= e(BASE_URL) ?>assets/img/favicon.ico">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/style.css">
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
            <?php foreach ($docs as $k => $d): ?>
                <a class="chip chip-cat<?= $k === $key ? ' chip-active' : '' ?>" href="<?= e(BASE_URL) ?>?action=docs&amp;doc=<?= e($k) ?>"><?= e($d['label']) ?></a>
            <?php endforeach; ?>
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
/* Self-test: is the rewrite working?                                  */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'selftest') {
    header('Content-Type: text/plain; charset=UTF-8');
    $route = (string) ($_SERVER['SFM_ROUTE'] ?? $_SERVER['REDIRECT_SFM_ROUTE'] ?? '');
    $probe = rtrim(BASE_URL, "/") . "/__folio_probe__/";
    echo "Folio self-test\n";
    echo str_repeat('-', 40) . "\n";
    echo 'Base URL         : ' . BASE_URL . "\n";
    echo 'PRETTY_URLS      : ' . (PRETTY_URLS ? 'true' : 'false') . "\n";
    echo 'mod_rewrite      : ' . (function_exists('apache_get_modules')
        ? (in_array('mod_rewrite', apache_get_modules(), true) ? 'loaded' : 'NOT loaded')
        : 'unknown (not running under Apache mod_php)') . "\n";
    echo '.htaccess present: ' . (is_file(__DIR__ . '/.htaccess') ? 'yes' : 'NO — rename htaccess.txt to .htaccess') . "\n";
    echo 'Route received   : ' . ($route === '' ? '(none — this request was not rewritten)' : $route) . "\n";
    echo 'Script directory  : ' . __DIR__ . "\n";
    echo "\nTo test the rewrite, open this URL:\n  " . $probe . "\n";
    echo "  Expect: a 404 page produced by Folio saying the file was not found.\n";
    echo "  If your server returns its own 404 page instead, the rewrite is not\n";
    echo "  active and PRETTY_URLS must stay false.\n";
    echo "\nUploads directory: " . BASE_DIR . "\n";
    echo 'Writable         : ' . (is_writable(BASE_DIR) ? 'yes' : 'NO — titles cannot be saved') . "\n";
    echo 'PHP version      : ' . PHP_VERSION . "\n";
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
    header('Content-Type: text/html; charset=UTF-8');
    send_security_headers();
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex">'
       . '<link rel="icon" href="' . e(BASE_URL) . 'assets/img/favicon.svg" type="image/svg+xml">'
       . '<link rel="alternate icon" href="' . e(BASE_URL) . 'assets/img/favicon.ico">'
       . '<link rel="stylesheet" href="' . e(BASE_URL) . 'assets/css/style.css">'
       . '</head><body class="md-body"><div class="md-content">'
       . render_markdown($abs)
       . '</div></body></html>';
    exit;
}

/* ------------------------------------------------------------------ */
/* Legacy file endpoint: redirect to the file's real location          */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'raw') {
    $abs = resolve_path((string) ($_GET['file'] ?? ''));
    if ($abs === null || !is_file($abs)) {
        http_response_code(404);
        exit('Not found');
    }
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', trim(substr($abs, strlen((string) realpath(BASE_DIR))), '/\\'));
    header('Location: ' . url_raw($rel), true, 301);
    exit;
}

/* ------------------------------------------------------------------ */
/* XML sitemap: directories, file pages, and category archives         */
/* ------------------------------------------------------------------ */
if (isset($_GET['action']) && $_GET['action'] === 'sitemap') {
    header('Content-Type: application/xml; charset=UTF-8');
    $meta = meta_load();
    $out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
          . ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

    $walk = function (string $abs, string $rel) use (&$walk, &$out, $meta, $mime_map): void {
        $latest = 0;
        foreach (scandir($abs) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                continue;
            }
            $abs_e = $abs . DIRECTORY_SEPARATOR . $entry;
            $rel_e = ltrim($rel . '/' . $entry, '/');
            if (is_dir($abs_e)) {
                $walk($abs_e, $rel_e);
                continue;
            }
            $mtime  = (int) filemtime($abs_e);
            $latest = max($latest, $mtime);
            $ext    = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!isset($mime_map[$ext])) {
                continue; // only known types get pages
            }
            $out .= "  <url>\n";
            $out .= '    <loc>' . htmlspecialchars(url_view($rel_e), ENT_XML1) . "</loc>\n";
            $out .= '    <lastmod>' . date('c', $mtime) . "</lastmod>\n";
            if ($ext !== 'pdf' && $ext !== 'txt' && $ext !== 'md') {
                $out .= "    <image:image>\n";
                $out .= '      <image:loc>' . htmlspecialchars(url_raw($rel_e), ENT_XML1) . "</image:loc>\n";
                $title = $meta[$rel_e]['title'] ?? '';
                if ($title !== '') {
                    $out .= '      <image:title>' . htmlspecialchars($title, ENT_XML1) . "</image:title>\n";
                }
                $out .= "    </image:image>\n";
            }
            $out .= "  </url>\n";
        }
        $out .= "  <url>\n";
        $out .= '    <loc>' . htmlspecialchars(url_dir($rel), ENT_XML1) . "</loc>\n";
        if ($latest > 0) {
            $out .= '    <lastmod>' . date('c', $latest) . "</lastmod>\n";
        }
        $out .= "  </url>\n";
    };
    $walk((string) realpath(BASE_DIR), '');

    foreach (category_register(index_all_files($mime_map)) as $cat_name => $count) {
        $out .= "  <url>\n";
        $out .= '    <loc>' . htmlspecialchars(url_category($cat_name), ENT_XML1) . "</loc>\n";
        $out .= "  </url>\n";
    }

    $out .= '</urlset>';
    exit($out);
}

/* ------------------------------------------------------------------ */
/* Admin login / logout                                                */
/* ------------------------------------------------------------------ */
function throttle_file(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return sys_get_temp_dir() . '/folio_login_' . hash('sha256', $ip);
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

function throttle_hit(): void
{
    $f = throttle_file();
    $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : [];
    $d = is_array($d) ? $d : [];
    $d['count'] = ($d['count'] ?? 0) + 1;
    $d['ts'] = time();
    @file_put_contents($f, json_encode($d), LOCK_EX);
}

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!csrf_valid()) {
        $login_error = 'Session expired, please try again.';
    } elseif (!throttle_check()) {
        $login_error = 'Too many failed attempts. Try again in 15 minutes.';
    } else {
        $user = (string) ($_POST['username'] ?? '');
        $pw   = (string) ($_POST['password'] ?? '');
        if (ADMIN_PASSWORD_HASH !== 'CHANGE_ME'
            && hash_equals(ADMIN_USERNAME, $user)
            && password_verify($pw, ADMIN_PASSWORD_HASH)) {
            @unlink(throttle_file());
            session_regenerate_id(true);
            $_SESSION['sfm_admin'] = true;
            $_SESSION['sfm_csrf'] = bin2hex(random_bytes(32));
            header('Location: ' . strtok((string) $_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        throttle_hit();
        usleep(random_int(200000, 500000)); // blunt timing probes
        $login_error = ADMIN_PASSWORD_HASH === 'CHANGE_ME'
            ? 'No admin password is configured. Set ADMIN_PASSWORD_HASH in index.php.'
            : 'Wrong username or password.';
    }
}
if (($_GET['action'] ?? '') === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . strtok((string) $_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* ------------------------------------------------------------------ */
/* Save title / description (admin only)                               */
/* ------------------------------------------------------------------ */
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
    if (function_exists('mb_substr')) {
        $title = mb_substr($title, 0, 200);
        $desc  = mb_substr($desc, 0, 500);
        $cat   = mb_substr($cat, 0, 50);
    } else {
        $title = substr($title, 0, 200);
        $desc  = substr($desc, 0, 500);
        $cat   = substr($cat, 0, 50);
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

    $meta = meta_load();
    if ($title === '' && $desc === '' && $cat === '' && !$tags) {
        unset($meta[$rel]);
    } else {
        $meta[$rel] = ['title' => $title, 'desc' => $desc, 'category' => $cat, 'tags' => $tags];
    }
    if (!meta_save($meta)) {
        http_response_code(500);
        exit(json_encode(['ok' => false, 'error' => 'Could not write metadata file']));
    }
    exit(json_encode(['ok' => true, 'title' => $title, 'desc' => $desc, 'category' => $cat, 'tags' => $tags]));
}

/* ------------------------------------------------------------------ */
/* Category archive page                                               */
/* ------------------------------------------------------------------ */
if (isset($_GET['cat'])) {
    $slug = slugify((string) $_GET['cat']);
    $all  = index_all_files($mime_map);
    $reg  = category_register($all);

    $cat_name = '';
    foreach (array_keys($reg) as $name) {
        if (slugify($name) === $slug) {
            $cat_name = $name;
            break;
        }
    }
    if ($cat_name === '') {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        send_security_headers();
        echo '<!DOCTYPE html><html lang="en" data-theme="folio"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>Category not found &ndash; ' . e(SITE_NAME) . '</title>'
           . '<meta name="robots" content="noindex">'
           . '<link rel="icon" href="' . e(BASE_URL) . 'assets/img/favicon.svg" type="image/svg+xml">'
           . '<link rel="alternate icon" href="' . e(BASE_URL) . 'assets/img/favicon.ico">'
           . '<link rel="stylesheet" href="' . e(BASE_URL) . 'assets/css/style.css"></head><body>'
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
            'url' => $f['view'],
            'item' => schema_file($f['rel'], $f['abs'], meta_load(), $mime_map),
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
    ?>
<!DOCTYPE html>
<html lang="<?= e(SITE_LANGUAGE) ?>" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($cat_name) ?> &ndash; <?= e(SITE_NAME) ?></title>
<meta name="description" content="<?= e($cat_desc) ?>">
<link rel="canonical" href="<?= e($cat_url) ?>">
<meta name="robots" content="index, follow">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($cat_name) ?>">
<meta property="og:description" content="<?= e($cat_desc) ?>">
<meta property="og:url" content="<?= e($cat_url) ?>">
<meta name="twitter:card" content="summary">
<script type="application/ld+json"><?= schema_emit($cat_ld) ?></script>
<link rel="icon" href="<?= e(BASE_URL) ?>assets/img/favicon.svg" type="image/svg+xml">
<link rel="alternate icon" href="<?= e(BASE_URL) ?>assets/img/favicon.ico" sizes="16x16 32x32 48x48">
<link rel="apple-touch-icon" href="<?= e(BASE_URL) ?>assets/img/apple-touch-icon.png">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/style.css">
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
            <?php foreach (array_keys($reg) as $name): ?>
                <a class="chip chip-cat<?= $name === $cat_name ? ' chip-active' : '' ?>" href="<?= e(url_category($name)) ?>"><?= e($name) ?> <span class="chip-count"><?= (int) $reg[$name] ?></span></a>
            <?php endforeach; ?>
        </div>
        <h2 class="archive-title"><?= e($cat_name) ?></h2>
        <p class="archive-note"><?= count($cat_files) ?> document<?= count($cat_files) === 1 ? '' : 's' ?><?php if ($latest > 0): ?>, most recent <?= e(date('j F Y', $latest)) ?><?php endif; ?></p>
        <table>
            <thead><tr><th>Name</th><th>Folder</th><th>Size</th><th>Modified</th></tr></thead>
            <tbody>
            <?php foreach ($cat_files as $f): ?>
                <?php $label = $f['title'] !== '' ? $f['title'] : pathinfo($f['name'], PATHINFO_FILENAME); ?>
                <tr class="row-file">
                    <td>
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
    $found = resolve_view((string) $_GET['view']);
    if ($found === null) {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        send_security_headers();
        exit('<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Not found &ndash; ' . e(SITE_NAME) . '</title>'
            . '<meta name="robots" content="noindex">'
            . '<link rel="icon" href="' . e(BASE_URL) . 'assets/img/favicon.svg" type="image/svg+xml">'
            . '<link rel="alternate icon" href="' . e(BASE_URL) . 'assets/img/favicon.ico">'
            . '<link rel="stylesheet" href="' . e(BASE_URL) . 'assets/css/style.css"></head><body>'
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
    $mtime = (int) filemtime($abs);
    $size  = human_size((int) filesize($abs));
    $raw   = url_raw($rel);
    $view  = url_view($rel);
    $meta_desc = $desc !== ''
        ? $desc
        : trim($title . ($cat !== '' ? ' — ' . $cat : '') . ' (' . strtoupper($ext) . ', ' . $size . ')');

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
    ?>
<!DOCTYPE html>
<html lang="en" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> &ndash; <?= e(SITE_NAME) ?></title>
<meta name="description" content="<?= e($meta_desc) ?>">
<link rel="canonical" href="<?= e($view) ?>">
<meta name="robots" content="index, follow">
<meta property="og:type" content="<?= $kind === 'image' ? 'website' : 'article' ?>">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($title) ?>">
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
<link rel="icon" href="<?= e(BASE_URL) ?>assets/img/favicon.svg" type="image/svg+xml">
<link rel="alternate icon" href="<?= e(BASE_URL) ?>assets/img/favicon.ico" sizes="16x16 32x32 48x48">
<link rel="apple-touch-icon" href="<?= e(BASE_URL) ?>assets/img/apple-touch-icon.png">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/style.css">
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
        <figure class="detail-media">
            <?php if ($kind === 'image'): ?>
                <img src="<?= e($raw) ?>" alt="<?= e($meta_desc) ?>">
            <?php elseif ($kind === 'pdf'): ?>
                <iframe src="<?= e($raw) ?>" title="<?= e($title) ?>"></iframe>
            <?php elseif ($kind === 'md'): ?>
                <div class="md-content"><?= render_markdown($abs) ?></div>
            <?php else: ?>
                <p><a class="btn" href="<?= e($raw) ?>">Download file</a></p>
            <?php endif; ?>
        </figure>
        <p class="detail-facts">
            <?= e(strtoupper($ext)) ?> &middot; <?= e($size) ?> &middot; Updated <?= e(date('j F Y', $mtime)) ?>
        </p>
        <p class="detail-actions">
            <?php if ($kind !== 'other'): ?><button id="btn-print" class="btn">Print</button><?php endif; ?>
            <a class="btn btn-ghost" href="<?= e($raw) ?>">Direct link</a>
        </p>
    </article>
</main>
<iframe id="print-frame" class="print-frame" title="print helper" data-kind="<?= e($kind) ?>" data-url="<?= e($raw) ?>"></iframe>
<script src="<?= e(BASE_URL) ?>assets/js/view.js"></script>
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
    $abs_dir = realpath(BASE_DIR);
    $rel_dir = '';
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
    $abs_entry = $abs_dir . DIRECTORY_SEPARATOR . $entry;
    $rel_entry = ltrim($rel_dir . '/' . $entry, '/');
    if (is_dir($abs_entry)) {
        $dirs[] = ['name' => $entry, 'rel' => $rel_entry];
    } else {
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        $files[] = [
            'name' => $entry,
            'rel'  => $rel_entry,
            'ext'  => $ext,
            'size' => human_size((int) filesize($abs_entry)),
            'mtime' => date('Y-m-d H:i', (int) filemtime($abs_entry)),
            'previewable' => isset($mime_map[$ext]) && $ext !== 'txt',
            'kind' => $ext === 'pdf' ? 'pdf' : ($ext === 'md' ? 'md' : (isset($mime_map[$ext]) && $ext !== 'txt' ? 'image' : 'other')),
            'title' => $meta[$rel_entry]['title'] ?? '',
            'desc'  => $meta[$rel_entry]['desc'] ?? '',
            'category' => $meta[$rel_entry]['category'] ?? '',
            'tags'  => $meta[$rel_entry]['tags'] ?? [],
            'hotlink' => url_raw($rel_entry),
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

/* Structured data for this listing: the collection, its breadcrumb trail,
   and every file it contains as a fully described list item. */
$list_items = [];
$pos = 0;
foreach ($files as $f) {
    $abs_f = $abs_dir . DIRECTORY_SEPARATOR . $f['name'];
    $list_items[] = [
        '@type' => 'ListItem',
        'position' => ++$pos,
        'url' => $f['view'],
        'item' => schema_file($f['rel'], $abs_f, $meta, $mime_map),
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
<html lang="en" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php $page_title = $rel_dir === '' ? SITE_NAME : basename($rel_dir) . ' – ' . SITE_NAME; ?>
<title><?= e($page_title) ?></title>
<meta name="description" content="<?= e(($rel_dir === '' ? 'Browse' : basename($rel_dir) . ': browse') . ' documents and images — ' . SITE_NAME) ?>">
<link rel="canonical" href="<?= e(url_dir($rel_dir)) ?>">
<meta name="robots" content="index, follow">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($page_title) ?>">
<meta property="og:url" content="<?= e(url_dir($rel_dir)) ?>">
<meta name="twitter:card" content="summary">
<script type="application/ld+json"><?= schema_emit($listing_ld) ?></script>
<link rel="sitemap" type="application/xml" href="<?= e(PRETTY_URLS ? BASE_URL . 'sitemap.xml' : BASE_URL . '?action=sitemap') ?>">
<link rel="icon" href="<?= e(BASE_URL) ?>assets/img/favicon.svg" type="image/svg+xml">
<link rel="alternate icon" href="<?= e(BASE_URL) ?>assets/img/favicon.ico" sizes="16x16 32x32 48x48">
<link rel="apple-touch-icon" href="<?= e(BASE_URL) ?>assets/img/apple-touch-icon.png">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/style.css">
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
    <div class="theme-picker" role="group" aria-label="Colour scheme">
        <button data-set-theme="folio" title="Folio"></button>
        <button data-set-theme="ledger" title="Ledger"></button>
        <button data-set-theme="garden" title="Garden"></button>
        <button data-set-theme="night" title="Night"></button>
    </div>
    <?php if (is_admin()): ?>
        <a class="admin-link" href="<?= e(BASE_URL) ?>?action=docs">Docs</a>
        <a class="admin-link" href="?action=logout">Log out</a>
    <?php else: ?>
        <details class="login-box" <?= $login_error !== '' ? 'open' : '' ?>>
            <summary class="admin-link">Admin</summary>
            <form method="post" class="login-form">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="text" name="username" placeholder="Username" autocomplete="username" required>
                <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
                <button type="submit" class="btn-small">Log in</button>
                <?php if ($login_error !== ''): ?>
                    <span class="login-error"><?= e($login_error) ?></span>
                <?php endif; ?>
            </form>
        </details>
    <?php endif; ?>
</header>

<main class="layout">
    <section class="listing">
        <?php if ($cat_register): ?>
        <div class="filter-bar" id="filter-bar">
            <?php foreach ($cat_register as $c => $n): ?>
                <a class="chip chip-cat" href="<?= e(url_category($c)) ?>"><?= e($c) ?> <span class="chip-count"><?= (int) $n ?></span></a>
            <?php endforeach; ?>
            <button class="chip chip-clear" id="filter-clear" hidden>&times; Clear filter</button>
        </div>
        <?php endif; ?>
        <table>
            <thead>
                <tr><th>Name</th><th>Size</th><th>Modified</th><th></th></tr>
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
                <tr class="row-file" data-file="<?= e($f['rel']) ?>" data-category="<?= e($f['category']) ?>" data-tags="<?= e(implode(',', $f['tags'])) ?>">
                    <td>
                        <div class="file-meta">
                            <a class="file-title" href="<?= e($f['view']) ?>"><?= e($label) ?></a>
                            <?php if ($f['desc'] !== ''): ?>
                                <span class="file-desc"><?= e($f['desc']) ?></span>
                            <?php endif; ?>
                            <?php if ($f['category'] !== '' || $f['tags']): ?>
                                <span class="file-chips">
                                    <?php if ($f['category'] !== ''): ?>
                                        <a class="chip chip-cat chip-mini" href="<?= e(url_category($f['category'])) ?>"><?= e($f['category']) ?></a>
                                    <?php endif; ?>
                                    <?php foreach ($f['tags'] as $t): ?>
                                        <button class="chip chip-tag chip-mini" data-filter-tag="<?= e($t) ?>">#<?= e($t) ?></button>
                                    <?php endforeach; ?>
                                </span>
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
                            <div class="meta-form-actions">
                                <button type="submit" class="btn-small">Save</button>
                                <button type="button" class="btn-small btn-ghost meta-cancel">Cancel</button>
                                <span class="meta-filename" title="Actual file"><?= e($f['name']) ?></span>
                            </div>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td><?= e($f['size']) ?></td>
                    <td><?= e($f['mtime']) ?></td>
                    <td class="row-actions">
                        <?php if ($f['previewable']): ?>
                            <button class="btn-small file-link" data-file="<?= e($f['rel']) ?>" data-kind="<?= e($f['kind']) ?>">Preview</button>
                        <?php endif; ?>
                        <?php if (is_admin()): ?>
                            <button class="btn-small btn-ghost meta-edit" title="Edit title and description">Edit</button>
                        <?php endif; ?>
                        <button class="btn-small btn-ghost copy-link" data-hotlink="<?= e($f['hotlink']) ?>" title="Copy direct link">Link</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$dirs && !$files): ?>
                <tr><td colspan="4" class="empty">This folder is empty.</td></tr>
            <?php endif; ?>
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
</main>

<iframe id="print-frame" class="print-frame" title="print helper"></iframe>
<?php if (is_admin()): ?>
<datalist id="category-list">
    <?php foreach ($all_categories as $c): ?>
        <option value="<?= e($c) ?>"></option>
    <?php endforeach; ?>
</datalist>
<?php endif; ?>
<script src="<?= e(BASE_URL) ?>assets/js/app.js"></script>
</body>
</html>
