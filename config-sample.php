<?php
/**
 * Folio settings.
 *
 * SETUP
 *   1. Copy this file to config.php in the same folder.
 *   2. Edit the values below.
 *   3. Never commit config.php to version control. The shipped .gitignore
 *      already excludes it.
 *
 * Any setting left out here falls back to a safe default in index.php.
 */

declare(strict_types=1);

/* ---------------------------------------------------------------- */
/* Admin credentials                                                 */
/* ---------------------------------------------------------------- */

define('ADMIN_USERNAME', 'admin');

/**
 * The bcrypt hash of your password, never the password itself.
 * Generate one on your server over SSH:
 *
 *   php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
 *
 * Or, if you have no shell access, put this in a temporary file called
 * hash.php in this folder, load it in a browser, copy the result, then
 * DELETE the file:
 *
 *   <?php echo password_hash('your-password', PASSWORD_DEFAULT);
 *
 * Paste the result below. It starts with $2y$ and is 60 characters long.
 * Leaving it as CHANGE_ME disables the admin login entirely.
 */
define('ADMIN_PASSWORD_HASH', 'CHANGE_ME');

/* ---------------------------------------------------------------- */
/* Identity                                                          */
/* ---------------------------------------------------------------- */

/** Appears in page titles, the running head, and structured data. */
define('SITE_NAME', 'Folio');

/** Used for meta descriptions and the WebSite description. */
define('SITE_DESCRIPTION', 'A reading library of documents, papers, and images.');

/** Publisher for structured data. Type is Person or Organization. */
define('PUBLISHER_TYPE', 'Person');
define('PUBLISHER_NAME', '');
define('PUBLISHER_URL', '');

/** Library language as a BCP 47 tag, for example en or ms. */
define('SITE_LANGUAGE', 'en');

/* ---------------------------------------------------------------- */
/* URLs                                                              */
/* ---------------------------------------------------------------- */

/** The folder your files are uploaded to, relative to index.php. */
define('UPLOADS_DIRNAME', 'uploads');

/**
 * Clean URLs. Leave false until the rewrite rules in .htaccess are
 * uncommented and working, or every page will return 404.
 * Check with index.php?action=selftest before switching this on.
 */
define('PRETTY_URLS', false);
