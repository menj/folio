<?php
/**
 * Folio — password hash generator.
 *
 * Upload this file beside index.php, open it in a browser, type the password
 * you want, and copy the hash it produces into config.php.
 *
 * DELETE THIS FILE once you have your hash. It does not change any setting
 * and stores nothing, but there is no reason to leave it on a live site.
 */

declare(strict_types=1);

$hash = '';
$password = (string) ($_POST['password'] ?? '');
$too_long = strlen($password) > 200;

if ($password !== '' && !$too_long) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="folio">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Password hash generator</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <h1>Folio</h1>
    <span class="running-head">Password hash</span>
</header>
<main class="detail">
    <h2 class="detail-title">Generate a password hash</h2>
    <p class="detail-desc">
        Type the password you want to use. The hash below goes into
        <code>config.php</code>. Your password itself is never stored anywhere.
    </p>

    <form method="post" class="hash-form">
        <input type="text" name="password" autocomplete="off" autocapitalize="off"
               spellcheck="false" placeholder="Password to hash"
               value="<?= h($password) ?>" required>
        <button type="submit" class="btn">Generate hash</button>
    </form>

    <?php if ($too_long): ?>
        <p class="detail-desc">That password is longer than 200 characters. Shorten it.</p>
    <?php elseif ($hash !== ''): ?>
        <p class="detail-facts">Copy this whole line into config.php</p>
        <pre class="hash-out"><code>define('ADMIN_PASSWORD_HASH', '<?= h($hash) ?>');</code></pre>
        <p class="detail-desc">
            Replace the matching line in <code>config.php</code>, save it, and
            log in with the password you just typed.
        </p>
    <?php endif; ?>

    <p class="detail-facts" style="border-top:1px solid var(--rule);padding-top:0.9rem">
        Delete this file when you are finished
    </p>
</main>
</body>
</html>
