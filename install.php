<?php
/**
 * Folio installer.
 *
 * Runs only while config.php does not exist. On successful install it writes
 * config.php with your username, password hash, site identity, and site
 * secrets, then refuses to run again.
 *
 * DELETE THIS FILE after install. It cannot delete itself from a live web
 * server because that would require write access to code, which is a worse
 * security posture. The installer refuses to run once config.php exists, so
 * a forgotten install.php is inert, but there is no reason to leave any
 * setup file on a live site.
 */

declare(strict_types=1);

ini_set('display_errors', '0');

const FOLIO_CONFIG_FILE = __DIR__ . '/config.php';
const FOLIO_UPLOADS_DIR = __DIR__ . '/uploads';
const FOLIO_DATA_DIR    = __DIR__ . '/data';
const FOLIO_INSTALL_TOKEN_FILE = FOLIO_DATA_DIR . '/install-token.php';

if (is_file(FOLIO_CONFIG_FILE)) {
    http_response_code(403);
    installer_layout('Already installed', function (): void { ?>
        <h2 class="detail-title">Folio is already installed</h2>
        <p class="detail-desc">
            <code>config.php</code> already exists in this folder. To reinstall,
            delete it first — but doing so will detach the login from the
            accounts stored in <code>data/users.php</code>.
        </p>
        <p class="detail-desc"><strong>Delete <code>install.php</code> from the server now.</strong></p>
        <p class="detail-actions"><a class="btn" href="./">Open the library</a></p>
    <?php });
    exit;
}

function random_secret(int $length = 64): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
              . '!@#$%^&*()-_=+[]{}|:,.<>?';
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function write_all($handle, string $contents): bool
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

function install_token(): string
{
    $env = getenv('FOLIO_INSTALL_TOKEN');
    if (is_string($env) && strlen($env) >= 20) {
        return $env;
    }
    if (!function_exists('random_bytes')) {
        return '';
    }
    if (!is_dir(FOLIO_DATA_DIR) && !@mkdir(FOLIO_DATA_DIR, 0750, true)) {
        return '';
    }
    if (is_link(FOLIO_INSTALL_TOKEN_FILE)) {
        return '';
    }
    if (is_file(FOLIO_INSTALL_TOKEN_FILE)) {
        $stored = @include FOLIO_INSTALL_TOKEN_FILE;
        return is_string($stored) ? $stored : '';
    }
    $token = bin2hex(random_bytes(24));
    $body = "<?php\n/* One-time Folio installation token. Delete after installation. */\nreturn "
          . var_export($token, true) . ";\n";
    $fh = @fopen(FOLIO_INSTALL_TOKEN_FILE, 'xb');
    if ($fh === false) {
        return '';
    }
    $ok = write_all($fh, $body) && @fflush($fh);
    @fclose($fh);
    if (!$ok) {
        @unlink(FOLIO_INSTALL_TOKEN_FILE);
        return '';
    }
    @chmod(FOLIO_INSTALL_TOKEN_FILE, 0600);
    return $token;
}

function write_config_exclusive(string $contents): bool
{
    if (is_file(FOLIO_CONFIG_FILE)) {
        return false;
    }
    $tmp = __DIR__ . '/.config.' . bin2hex(random_bytes(8)) . '.tmp';
    $fh = @fopen($tmp, 'xb');
    if ($fh === false) {
        return false;
    }
    $ok = write_all($fh, $contents) && @fflush($fh);
    @fclose($fh);
    if (!$ok) {
        @unlink($tmp);
        return false;
    }
    @chmod($tmp, 0600);

    // A hard link publishes the fully written file atomically and refuses to
    // replace an existing config.php. Fall back to exclusive creation where
    // hard links are unavailable.
    if (@link($tmp, FOLIO_CONFIG_FILE)) {
        @unlink($tmp);
        @chmod(FOLIO_CONFIG_FILE, 0600);
        return true;
    }
    $target = @fopen(FOLIO_CONFIG_FILE, 'xb');
    if ($target === false) {
        @unlink($tmp);
        return false;
    }
    $contents_ok = write_all($target, $contents) && @fflush($target);
    @fclose($target);
    @unlink($tmp);
    if (!$contents_ok) {
        @unlink(FOLIO_CONFIG_FILE);
        return false;
    }
    @chmod(FOLIO_CONFIG_FILE, 0600);
    return true;
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function installer_layout(string $title, callable $body): void
{
    // The installer handles credentials and writes config.php, so it gets the
    // same protections as the application rather than fewer. No inline script
    // or style is used, so the policy can stay strict.
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
        header(
            "Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
            . "style-src 'self'; script-src 'self'; frame-ancestors 'none'; "
            . "form-action 'self'; base-uri 'self'; object-src 'none'"
        );
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('X-Frame-Options: DENY');
        // Installer pages show one-time tokens and generated secrets.
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
    }
    ?>
<!DOCTYPE html>
<html lang="en" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> &ndash; Folio installer</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<link rel="alternate icon" href="assets/img/favicon.ico">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <h1>Folio</h1>
    <span class="running-head">Installer</span>
</header>
<main class="detail">
    <?php $body(); ?>
</main>
</body>
</html>
    <?php
}

/* ------------------------------------------------------------------ */
/* Environment checks                                                  */
/* ------------------------------------------------------------------ */

function env_checks(): array
{
    $out = [];
    $out[] = [
        'label' => 'PHP 8.4 or newer',
        'ok'    => version_compare(PHP_VERSION, '8.4.0', '>='),
        'note'  => 'Detected ' . PHP_VERSION,
    ];
    $out[] = [
        'label' => 'password_hash() available',
        'ok'    => function_exists('password_hash'),
        'note'  => '',
    ];
    $out[] = [
        'label' => 'random_bytes() available',
        'ok'    => function_exists('random_bytes'),
        'note'  => '',
    ];
    $out[] = [
        'label' => 'JSON extension',
        'ok'    => function_exists('json_encode'),
        'note'  => '',
    ];
    $out[] = [
        'label' => 'mbstring extension',
        'ok'    => function_exists('mb_strlen'),
        'note'  => 'Required by Markdown rendering',
    ];
    $out[] = [
        'label' => 'uploads/ exists and is readable',
        'ok'    => is_dir(FOLIO_UPLOADS_DIR) && is_readable(FOLIO_UPLOADS_DIR),
        'note'  => is_dir(FOLIO_UPLOADS_DIR)
            ? (is_readable(FOLIO_UPLOADS_DIR) ? 'readable' : 'NOT readable by PHP')
            : 'missing — restore the uploads/ folder from the package',
    ];
    $out[] = [
        'label' => 'data/ exists and is writable',
        'ok'    => is_dir(FOLIO_DATA_DIR) && is_writable(FOLIO_DATA_DIR),
        'note'  => is_dir(FOLIO_DATA_DIR)
            ? (is_writable(FOLIO_DATA_DIR) ? 'writable' : 'NOT writable — set to 755 or 775')
            : 'missing — will be created on demand',
    ];
    $out[] = [
        'label' => 'This folder is writable',
        'ok'    => is_writable(__DIR__),
        'note'  => is_writable(__DIR__) ? '' : 'config.php cannot be written',
    ];
    return $out;
}

$checks = env_checks();
$install_token = install_token();
$critical_ok = true;
foreach ($checks as $c) {
    if (!$c['ok']) {
        $critical_ok = false;
    }
}
if ($install_token === '') {
    $critical_ok = false;
}

/* ------------------------------------------------------------------ */
/* Submission                                                          */
/* ------------------------------------------------------------------ */

$errors = [];
$values = [
    'username'         => '',
    'site_url'         => '',
    'site_name'        => 'Folio',
    'site_description' => 'A reading library of documents, papers, and images.',
    'publisher_type'   => 'Person',
    'publisher_name'   => '',
    'publisher_url'    => '',
    'site_language'    => 'en',
    'add_secrets'      => '1',
];
foreach ($values as $k => $default) {
    if (isset($_POST[$k])) {
        $values[$k] = (string) $_POST[$k];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $critical_ok) {
    $u  = trim((string) ($_POST['username'] ?? ''));
    $p1 = (string) ($_POST['password'] ?? '');
    $p2 = (string) ($_POST['password_confirm'] ?? '');
    $token_input = trim((string) ($_POST['install_token'] ?? ''));
    $site_url = trim((string) ($_POST['site_url'] ?? ''));
    $sn = trim((string) ($_POST['site_name'] ?? ''));
    $sd = trim((string) ($_POST['site_description'] ?? ''));
    $pt = (string) ($_POST['publisher_type'] ?? 'Person');
    $pn = trim((string) ($_POST['publisher_name'] ?? ''));
    $pu = trim((string) ($_POST['publisher_url'] ?? ''));
    $sl = trim((string) ($_POST['site_language'] ?? 'en'));
    $secrets = !empty($_POST['add_secrets']);

    if ($install_token === '' || !hash_equals($install_token, $token_input)) {
        $errors[] = 'The one-time installation token is incorrect.';
    }
    if (!filter_var($site_url, FILTER_VALIDATE_URL)
        || !in_array(strtolower((string) parse_url($site_url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        $errors[] = 'Site URL must be the full public http:// or https:// URL of this Folio folder.';
    } else {
        $site_url = rtrim($site_url, '/') . '/';
    }
    if (!preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $u)) {
        $errors[] = 'Username must be 3–32 characters: letters, digits, dot, dash, underscore.';
    }
    if (strlen($p1) < 10) {
        $errors[] = 'Password must be at least 10 characters.';
    }
    if ($p1 !== $p2) {
        $errors[] = 'The two passwords do not match.';
    }
    if ($sn === '' || strlen($sn) > 100) {
        $errors[] = 'Site name is required, at most 100 characters.';
    }
    if (strlen($sd) > 300) {
        $errors[] = 'Description must be at most 300 characters.';
    }
    if (!in_array($pt, ['Person', 'Organization'], true)) {
        $errors[] = 'Publisher type must be Person or Organization.';
    }
    if ($pu !== '' && !preg_match('#^https?://#', $pu)) {
        $errors[] = 'Publisher URL must start with http:// or https://.';
    }
    if (!preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})*$/', $sl)) {
        $errors[] = 'Language must be a BCP 47 tag such as en or ms.';
    }

    if (!$errors) {
        $pepper = $secrets ? random_secret(64) : '';
        $cookie = $secrets ? 'FOLIO_' . strtoupper(bin2hex(random_bytes(4))) : 'FOLIOSESSID';
        $hash   = password_hash(
            $secrets ? hash_hmac('sha256', $p1, $pepper) : $p1,
            PASSWORD_DEFAULT
        );

        $lines = [
            '<?php',
            '/**',
            ' * Folio configuration.',
            ' * Generated by install.php on ' . gmdate('Y-m-d H:i:s') . ' UTC.',
            ' */',
            '',
            "declare(strict_types=1);",
            '',
            "define('ADMIN_USERNAME', " . var_export($u, true) . ');',
            "define('ADMIN_PASSWORD_HASH', " . var_export($hash, true) . ');',
            '',
            "define('SITE_URL', " . var_export($site_url, true) . ');',
            "define('SITE_NAME', " . var_export($sn, true) . ');',
            "define('SITE_DESCRIPTION', " . var_export($sd, true) . ');',
            "define('PUBLISHER_TYPE', " . var_export($pt, true) . ');',
            "define('PUBLISHER_NAME', " . var_export($pn, true) . ');',
            "define('PUBLISHER_URL', " . var_export($pu, true) . ');',
            "define('SITE_LANGUAGE', " . var_export($sl, true) . ');',
            '',
            '/* Clean URLs are detected automatically: the shipped .htaccess',
            ' * advertises a working mod_rewrite, and Folio falls back to',
            ' * query-string URLs when that signal is absent. Uncomment the',
            ' * line below only to force one mode regardless of the server. */',
            "// define('PRETTY_URLS', true);",
            '',
            "define('TRUST_PROXY_HEADERS', false);",
            '',
        ];
        if ($secrets) {
            $lines[] = '/* Site secrets. NEVER change FOLIO_AUTH_PEPPER once accounts exist. */';
            $lines[] = "define('FOLIO_AUTH_PEPPER', " . var_export($pepper, true) . ');';
            $lines[] = "define('FOLIO_COOKIE_NAME', " . var_export($cookie, true) . ');';
            $lines[] = '';
        }
        $body = implode("\n", $lines);

        $ok = write_config_exclusive($body);
        if ($ok) {
            if (is_file(FOLIO_INSTALL_TOKEN_FILE)) {
                @unlink(FOLIO_INSTALL_TOKEN_FILE);
            }
            installer_layout('Installed', function () use ($sn): void { ?>
                <p class="msg msg-ok">Folio is installed. <?= h($sn) ?> is ready.</p>

                <h2 class="detail-title">One last step</h2>
                <p class="detail-desc"><strong>Delete <code>install.php</code> from the server now.</strong> The installer refuses to run again while <code>config.php</code> exists, but a leftover setup file is untidy.</p>

                <h2 class="detail-title">Next</h2>
                <ol class="install-next">
                    <li>Confirm <code>.htaccess</code> reached the server. It ships with the release, but many FTP clients hide dotfiles &mdash; turn on hidden files if it is missing.</li>
                    <li>Upload documents into <code>uploads/</code> over FTP.</li>
                    <li>Log in and add titles, descriptions, categories, and tags.</li>
                    <li>Edit <code>robots.txt</code> and upload it to your domain root.</li>
                </ol>

                <p class="detail-actions">
                    <a class="btn" href="./?action=login">Log in</a>
                    <a class="btn btn-ghost" href="./">Open the library</a>
                </p>
            <?php });
            exit;
        }
        $errors[] = 'Could not write config.php. Check that this folder is writable by the web server.';
    }
}

/* ------------------------------------------------------------------ */
/* Form                                                                */
/* ------------------------------------------------------------------ */

installer_layout('Install', function () use ($checks, $critical_ok, $errors, $values): void { ?>
    <h2 class="detail-title">Install Folio</h2>
    <p class="detail-desc">This installer is locked by a one-time token. Read <code>data/install-token.php</code> over FTP or set the <code>FOLIO_INSTALL_TOKEN</code> environment variable, then enter the token below.</p>

    <h2 class="detail-title">Environment</h2>
    <table class="accounts">
        <?php foreach ($checks as $c): ?>
            <tr>
                <td><?= h($c['label']) ?></td>
                <td><?= $c['ok'] ? '<span class="chip chip-mini diag-ok">OK</span>' : '<span class="chip chip-mini diag-bad">FAIL</span>' ?></td>
                <td class="detail-facts"><?= h((string) $c['note']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <?php if (!$critical_ok): ?>
        <p class="msg msg-bad">Fix the failed checks above before proceeding. If the token file could not be created, make <code>data/</code> writable and reload.</p>
    <?php else: ?>

    <?php foreach ($errors as $err): ?>
        <p class="msg msg-bad"><?= h($err) ?></p>
    <?php endforeach; ?>

    <form method="post" class="stack-form settings-form form-narrow">
        <h2 class="detail-title">Installation lock</h2>
        <label for="i-token">One-time installation token</label>
        <input type="password" id="i-token" name="install_token" required autocomplete="off">

        <h2 class="detail-title">Canonical URL</h2>
        <label for="i-url">Full public URL of this Folio folder</label>
        <input type="url" id="i-url" name="site_url" placeholder="https://example.com/documents/" value="<?= h($values['site_url']) ?>" required>
        <p class="field-note">Canonical links never trust the request Host header. Include the folder path and trailing slash.</p>

        <h2 class="detail-title">Your account</h2>
        <label for="i-user">Username</label>
        <input type="text" id="i-user" name="username" value="<?= h($values['username']) ?>" required autocomplete="username">

        <label for="i-p1">Password (at least 10 characters)</label>
        <input type="password" id="i-p1" name="password" required autocomplete="new-password">

        <label for="i-p2">Repeat password</label>
        <input type="password" id="i-p2" name="password_confirm" required autocomplete="new-password">

        <h2 class="detail-title">Site identity</h2>
        <label for="i-sn">Site name</label>
        <input type="text" id="i-sn" name="site_name" maxlength="100" value="<?= h($values['site_name']) ?>" required>

        <label for="i-sd">Description</label>
        <input type="text" id="i-sd" name="site_description" maxlength="300" value="<?= h($values['site_description']) ?>">

        <label for="i-pt">Publisher type</label>
        <select id="i-pt" name="publisher_type">
            <option value="Person" <?= $values['publisher_type'] === 'Person' ? 'selected' : '' ?>>Person</option>
            <option value="Organization" <?= $values['publisher_type'] === 'Organization' ? 'selected' : '' ?>>Organization</option>
        </select>

        <label for="i-pn">Publisher name</label>
        <input type="text" id="i-pn" name="publisher_name" maxlength="100" value="<?= h($values['publisher_name']) ?>">

        <label for="i-pu">Publisher URL</label>
        <input type="text" id="i-pu" name="publisher_url" maxlength="200" placeholder="https://…" value="<?= h($values['publisher_url']) ?>">

        <label for="i-sl">Language (BCP 47, e.g. en or ms)</label>
        <input type="text" id="i-sl" name="site_language" maxlength="20" value="<?= h($values['site_language']) ?>">

        <h2 class="detail-title">Security</h2>
        <label class="check-row">
            <input type="checkbox" name="add_secrets" value="1" <?= $values['add_secrets'] === '1' ? 'checked' : '' ?>>
            Generate site secrets (recommended)
        </label>
        <p class="field-note">
            Adds a per-installation pepper to every password hash and a unique
            session cookie name. Once accounts exist, the pepper must never
            change. Turning this off can be done in <code>config.php</code>
            later without effect on existing accounts.
        </p>

        <div><button type="submit" class="btn">Install Folio</button></div>
    </form>
    <?php endif; ?>
<?php });
