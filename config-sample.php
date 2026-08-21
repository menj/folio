<?php
/* Folio. Copyright (C) 2026 Mohd Elfie Nieshaem Juferi.
   SPDX-License-Identifier: GPL-3.0-or-later. See license.txt. */
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

/**
 * Optional, used only by the downloadable vCard (vcard.vcf), never by
 * identity.json. Each renders in the card only when set; leave any of them
 * as '' to omit that field entirely rather than emit an empty one.
 */
define('PUBLISHER_NICKNAME', '');
define('PUBLISHER_EMAIL', '');
define('PUBLISHER_PHONE', '');
define('PUBLISHER_COUNTRY', '');

/** Library language as a BCP 47 tag, for example en or ms. */
define('SITE_LANGUAGE', 'en');

/* ---------------------------------------------------------------- */
/* Site secrets (optional but recommended)                           */
/* ---------------------------------------------------------------- */

/**
 * Generate these once with PHP on the server:
 *
 *   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
 *
 * FOLIO_AUTH_PEPPER is mixed into every stored password hash. If
 * data/users.php leaks in isolation but this file does not, the hashes
 * remain uncrackable without the pepper. NEVER change this value once
 * accounts exist: it locks every account out and every user must reset
 * their password. Existing hashes without a pepper migrate automatically
 * the next time each user logs in.
 *
 * FOLIO_COOKIE_NAME prevents session collisions with other PHP applications
 * on the same domain. Safe to change at any time; the worst it does is log
 * everyone out.
 */
define('FOLIO_AUTH_PEPPER', '');
define('FOLIO_COOKIE_NAME', 'FOLIOSESSID');

/**
 * FOLIO_URL_SIGNING_KEY signs the short-lived URLs used to display PDFs
 * whose pdf_access is set to "viewer" in the admin. Generate it the same
 * way as FOLIO_AUTH_PEPPER above, but NEVER reuse that value here: they
 * protect different things, and rotating one must never lock out or
 * invalidate the other. Safe to rotate at any time — the worst it does is
 * invalidate PDF preview links that are already open in a browser tab.
 *
 * Leaving this empty disables real enforcement of "viewer" and "hidden"
 * pdf_access: affected files fall back to public behaviour, and Diagnostics
 * explains why, rather than presenting a restriction that a blank key would
 * make trivially forgeable.
 */
/**
 * Generate this rather than inventing one. The Crawlers screen shows a
 * ready-to-paste line when the key is unset, or:
 *
 *   php -r 'echo bin2hex(random_bytes(32)), "\n";'
 *   openssl rand -hex 32
 *
 * Must differ from FOLIO_AUTH_PEPPER: they protect different things.
 * Changing it invalidates every signed link already shared.
 */
define('FOLIO_URL_SIGNING_KEY', '');

/* ---------------------------------------------------------------- */
/* URLs                                                              */
/* ---------------------------------------------------------------- */

/**
 * Full canonical URL of this Folio folder, including its trailing slash.
 *
 * Optional. Left unset, Folio derives the address from the request and
 * accepts only a well-formed hostname. Setting it pins the canonical URL
 * used in sitemaps, structured data, and redirects, which is worth doing
 * on a production site. The installer fills this in for you.
 */
// define('SITE_URL', 'https://example.com/documents/');

/** The folder your files are uploaded to, relative to index.php. */
define('UPLOADS_DIRNAME', 'uploads');

/**
 * Clean URLs.
 *
 * Leave this alone. Folio detects whether rewriting works: the shipped
 * .htaccess sets a marker from inside its <IfModule mod_rewrite.c> block,
 * so clean URLs switch on by themselves on Apache and LiteSpeed, and fall
 * back to query-string URLs on any host that cannot rewrite. Both forms
 * are fully indexable.
 *
 * Uncomment only to force one mode regardless of what the server supports.
 */
// define('PRETTY_URLS', true);

/** Set true only behind a trusted reverse proxy that controls X-Forwarded-Proto. */
define('TRUST_PROXY_HEADERS', false);

/* ---------------------------------------------------------------- */
/* Admin visibility                                                  */
/* ---------------------------------------------------------------- */

/**
 * Whether the Admin link appears in the header for logged-out visitors.
 *
 * true  — the Admin link is visible, as usual.
 * false — the header shows nothing, and the library looks read-only.
 *
 * Either way you can always sign in directly at:
 *   index.php?action=login
 *
 * Hiding the link keeps the login out of sight of casual visitors and
 * automated scanners that follow visible links. It is not a security
 * control on its own: the real protection is the password, the login
 * throttle, and HTTPS. Bookmark the login URL before switching this off.
 */
define('SHOW_ADMIN_LINK', true);

/* ---------------------------------------------------------------- */
/* Hidden files and folders                                          */
/* ---------------------------------------------------------------- */

/**
 * Shell-style globs matched against both the entry name and the relative
 * path from uploads/. Anything matching is omitted from the listing, the
 * sitemap, category archives, IndexNow submissions, and detail-page URLs
 * (they return a real 404).
 *
 * Dotfiles like .htaccess and .DS_Store are already skipped without needing
 * to be listed here.
 *
 * Examples:
 *   define('EXCLUDE_PATTERNS', ['_drafts/*', '*.tmp', 'working-notes.md']);
 *   define('EXCLUDE_PATTERNS', ['_private', 'staff/*']);
 *
 * Excluded entries stay in uploads/ untouched — Folio simply pretends they
 * aren't there. Move or rename them to publish.
 */
define('EXCLUDE_PATTERNS', []);

/* ---------------------------------------------------------------- */
/* Derivative images                                                 */
/* ---------------------------------------------------------------- */

/**
 * Folio can generate small cached copies of images for the listing, hover
 * cards, and detail pages, so a hover over a 12 MB scan no longer downloads
 * 12 MB. It can also convert formats browsers cannot display, such as TIFF
 * and HEIC, into a viewable preview.
 *
 * This needs the Imagick or GD PHP extension. With neither, everything here
 * is inert: Folio serves the original file exactly as it always has. Check
 * which engine you have at index.php?action=diagnostics.
 *
 * Originals are never touched. Derivatives live in data/thumbs/ and can be
 * deleted at any time; they are rebuilt on demand, and replacing a file over
 * FTP invalidates its derivatives automatically.
 */
// define('THUMBNAILS_ENABLED', true);

/** Widths Folio will produce. Any other width is refused, so the cache cannot
 *  be filled by requesting thousands of sizes. */
// define('THUMB_WIDTHS', [320, 640, 1280]);

/** Quality for generated WebP derivatives. */
// define('THUMB_QUALITY', 82);

/** Refuse to decode images beyond this many pixels. A small file can declare
 *  enormous dimensions and exhaust memory when decoded. Dimensions are read
 *  before any pixels, so an oversized image costs nothing to reject. */
// define('IMAGE_MAX_PIXELS', 80000000);

/** Ceilings for a single conversion: megabytes of memory, and seconds. */
// define('IMAGE_MEMORY_LIMIT', 256);
// define('IMAGE_TIME_LIMIT', 20);

/**
 * Render page one of a PDF on the server. Requires Imagick with a working
 * PDF delegate.
 *
 * Off by default, deliberately. The PDF delegate has a poor security record,
 * and Folio's in-browser reader already previews PDFs without it. Turn this
 * on only if you want PDF thumbnails in listings and trust the documents.
 */
// define('PDF_SERVER_PREVIEW', false);

/** Formats a browser cannot display, converted to a viewable preview when an
 *  engine is available. The original is always what downloads. */
// define('CONVERT_FORMATS', ['tif', 'tiff', 'heic', 'heif', 'avif']);

/* ---------------------------------------------------------------- */
/* External utilities                                                */
/* ---------------------------------------------------------------- */

/**
 * Folio detects command-line utilities on the server and uses them when
 * they are there. Nothing here is required: with none installed, Folio
 * behaves exactly as it did before, and Diagnostics tells you which were
 * found and what each one enables.
 *
 * What Folio will use if present:
 *
 *   ocrmypdf + tesseract   make scanned PDFs searchable
 *   pdftotext              pull text out of PDFs for indexing
 *   pdfinfo                page counts and PDF facts
 *   pdftocairo / pdftoppm  render PDF pages for previews
 *   pngquant               smaller PNG derivatives
 *   unpaper                straighten crooked scans before OCR
 *
 * Utilities are run through proc_open() with an argument array, so no
 * shell is involved and a filename containing shell metacharacters is
 * inert. Only the directories in TOOL_SEARCH_PATHS are searched; $PATH
 * is deliberately ignored.
 */
// define('TOOLS_ENABLED', true);

/**
 * Where to look for system-wide tools. $PATH is not consulted.
 *
 * Folio also searches this account's own home automatically, so a tool
 * installed for one cPanel user rather than server-wide is still found.
 * That covers:
 *
 *   ~/.local/bin              pip install --user
 *   ~/bin
 *   ~/ocrmypdf-venv/bin       the usual name for an OCRmyPDF virtualenv
 *   ~/venv/bin, ~/.venv/bin
 *   ~/virtualenv/<app>/<ver>/bin    cPanel's "Setup Python App"
 *   ~/anything-venv/bin       any *-venv, *-env or *_venv directory
 *
 * You should not need to change this list.
 */
// define('TOOL_SEARCH_PATHS', ['/usr/local/bin', '/usr/bin', '/bin', '/opt/bin']);

/**
 * Name a binary outright if it lives somewhere the automatic search does not
 * cover. Rarely needed: a virtualenv in the account home is found already.
 *
 * define('TOOL_PATHS', [
 *     'ocrmypdf' => '/home/youruser/ocrmypdf-venv/bin/ocrmypdf',
 * ]);
 *
 * Check ?action=diagnostics first — it lists every tool found, its absolute
 * path, and the directories that were searched.
 */
// define('TOOL_PATHS', []);

/** Seconds a utility may run before it is killed. OCR gets its own budget
 *  because it is genuinely slow on long documents. */
// define('TOOL_TIMEOUT', 20);
// define('OCR_TIMEOUT', 600);

/**
 * Languages to OCR in, most likely first. Only datasets actually installed
 * are used, so listing one you do not have is harmless — Diagnostics will
 * name it so you can ask your host to add the tesseract language pack.
 *
 * Malay is 'msa' and Arabic is 'ara'.
 */
// define('OCR_LANGUAGES', ['eng', 'msa', 'ara']);

/**
 * Let ImageMagick render PDFs directly.
 *
 * Off by default. Folio renders PDF pages with Poppler instead, which is
 * available on most hosts and needs no extra configuration. With this false
 * and no Poppler installed, PDF previews are simply not generated and the
 * original file is served.
 */
// define('PDF_ALLOW_GHOSTSCRIPT', false);

/* ---------------------------------------------------------------- */
/* Site icon                                                         */
/* ---------------------------------------------------------------- */

/**
 * Your own favicon.
 *
 * The simplest way needs no setting at all: make a folder called branding/
 * beside index.php and put your icon in it.
 *
 *   branding/favicon.svg          preferred
 *   branding/favicon.png          or this
 *   branding/favicon.ico          for older browsers
 *   branding/apple-touch-icon.png for iOS home screens
 *
 * Folio finds them automatically. Use branding/ rather than replacing the
 * file inside assets/, because assets/ is release-owned and an upgrade would
 * overwrite whatever you put there.
 *
 * Set this only to point somewhere else — a path inside the installation, or
 * a full URL.
 */
// define('SITE_ICON', '');

/* ---------------------------------------------------------------- */
/* Analytics                                                          */
/* ---------------------------------------------------------------- */

/**
 * Configure these from the Analytics screen in the admin rather than here;
 * values saved there override this file. They are listed for reference and
 * for seeding an installation before first login.
 *
 * Folio stores no visit data itself. Both providers are external.
 */
// define('MATOMO_URL', 'https://analytics.example.com/');
// define('MATOMO_SITE_ID', '1');
// define('MATOMO_HONOR_DNT', true);
// define('MATOMO_COOKIELESS', false);
// define('GA4_MEASUREMENT_ID', 'G-XXXXXXXXXX');
// define('GA4_ANONYMIZE_IP', true);
// define('ANALYTICS_ADMIN', false);

/* ---------------------------------------------------------------- */
/* Settings the admin screens manage for you                         */
/* ---------------------------------------------------------------- */

/**
 * The values below are written to data/settings.php by Folio's own admin
 * screens. They are listed here so you know they exist and where they come
 * from, not so you can set them by hand.
 *
 * If you do define one here, your value wins and the admin screen can no
 * longer change it — settings resolve in the order: data/settings.php first,
 * then config.php, then Folio's default. That is occasionally useful for
 * pinning something on a staging copy, but for ordinary use the admin screens
 * are the right place.
 *
 *   SITE_INDEXABLE      Crawlers screen. Whether search engines may index
 *                       the library at all. Off means noindex everywhere and
 *                       a 404 for the sitemap and llms.txt.
 *   SITEMAP_ENABLED     Crawlers screen. Whether /sitemap.xml is served.
 *   LLMS_ENABLED        Crawlers screen. Whether /llms.txt is served, the
 *                       plain-text map some AI crawlers read.
 *   LLMS_INTRO          Crawlers screen. A short paragraph at the top of
 *                       llms.txt describing the collection.
 *   INDEXNOW_KEY        Crawlers screen. Generated there; also hosted at
 *                       /{key}.txt so IndexNow can verify ownership. Treat it
 *                       as a secret and keep it out of version control.
 *   PDF_GATE_CONFIRMED  Settings screen, after the preflight passes. Records
 *                       that Folio has confirmed PDF requests actually reach
 *                       it, which is the precondition for enforcing
 *                       pdf_access. Never set this by hand: doing so claims a
 *                       restriction is working when it may not be.
 */
