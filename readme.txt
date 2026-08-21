=== Folio ===
Contributors: menj
Author: MENJ
Author URI: https://menj.blog
Project URI: https://github.com/menj/folio
Requires PHP: 8.4
Requires at least: PHP 8.4
Tested up to: PHP 8.4
Stable tag: 1.41.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Folio turns a web folder into a small public document library with crawlable
file pages, previews, metadata, categories, accounts, sitemap, and llms.txt.
Files remain managed over FTP and no database is required. Optional standalone
pages (About, FAQ, custom) sit alongside the library. Hover preview cards give
each row a real thumbnail on desktop, and the layout collapses cleanly on
mobile.

== Requirements ==

* PHP 8.4 or newer
* JSON, password, random and mbstring support
* PHP read access to uploads/ and write access to data/
* Apache/LiteSpeed using the supplied .htaccess with mod_mime and mod_headers;
  mod_rewrite is optional

== Installation ==

1. Upload the contents of the folio/ folder.
2. Make uploads/ readable by PHP and data/ writable by the web-server account.
3. Confirm .htaccess uploaded. It ships ready to use, but many FTP
   clients hide dotfiles by default.
4. Open install.php. It creates data/install-token.php.
5. Read the one-time token over FTP and enter it in the installer.
6. Enter the exact canonical SITE_URL, account, and site details.
7. Delete install.php after installation.
8. Log in at ?action=login and run the admin-only diagnostics.

== Metadata and files ==

Upload, rename, and remove documents over FTP. Folio never edits the document
contents. Titles, descriptions, categories, and tags are stored atomically in
data/metadata.json, with data/metadata.json.bak as the last-known-good backup.
Older uploads/.sfm-meta.json data is read for migration.

Supported preview formats are PDF, PNG, JPEG, GIF, WebP, BMP, SVG, and Markdown.
Audio and video (MP3, M4A, AAC, WAV, FLAC, OGG, Opus, MP4, M4V, WebM, OGV, MOV)
play in the page through a themed transport, with the web server delivering the
bytes directly so seeking works. Plain text and unknown files receive detail
pages with download links. Unknown or active formats are forced to download by
the supplied server rules and by the PHP fallback endpoint. Symlinks are rejected.

== URLs and SEO ==

File page slugs include the extension, for example paper.pdf becomes paper-pdf.
A short hash is added only when normalised names still collide. Unambiguous old
extensionless or extension-bearing URLs redirect to the current canonical URL.

SITE_URL is authoritative for canonical, Open Graph, sitemap, and structured
data URLs. Incoming Host headers are never trusted. Raw PDF, text, and Markdown
responses are marked noindex by the supplied server rules so their Folio detail
pages remain the search target.

Listing and category pages emit focused WebSite, breadcrumb, CollectionPage,
and ItemList schema. Detailed typed file schema appears on the file page.
Publisher schema is omitted when no publisher name is configured.

== Sessions and accounts ==

Public requests do not start PHP sessions. Sessions begin only for login,
authenticated routes, POST requests, or visitors who already carry the Folio
session cookie. Password changes and resets increment an authentication version;
deleted or reset accounts lose old sessions on their next request.

Accounts are managed at ?action=users and stored privately in data/users.php.
Every account has full access; Folio has no role hierarchy.

== Security ==

The installer requires a one-time private token and creates config.php
exclusively with restrictive permissions. Metadata updates use a locked,
atomic transaction and reject malformed JSON. Upload scanning rejects symlinks
and paths outside uploads/. The Apache/LiteSpeed rules block executable
formats, sandbox SVG, force active files to download, and disable indexing.

Set TRUST_PROXY_HEADERS to true only behind a trusted reverse proxy that
overwrites X-Forwarded-Proto. Always use HTTPS in production.

== Frequently Asked Questions ==

= Where do I set the canonical URL? =

Set SITE_URL in config.php to the complete public URL of the Folio folder,
including the trailing slash.

= How do I generate a password hash manually? =

Run:
php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"

The guided installer performs this automatically.

= How do categories differ from tags? =

Categories have crawlable archive pages spanning all folders. Tags filter the
current listing in the browser and do not have archive pages.

= Does it work on Nginx? =

Query-string URLs will work, since Folio falls back to them automatically
whenever it can't confirm rewriting is active. Nginx is not a supported or
documented deployment target, though: Folio ships and maintains only an
Apache/LiteSpeed .htaccess, and features that depend on server-level rewrite
rules (clean URLs, per-file PDF access control) will not be enforced. Folio
detects this itself and fails safe rather than presenting a false sense of
protection.

= Is there a page-turning reader for PDFs? =

Yes. PDF rows have a "Flip view" button that opens a reader rendering real
pages with Mozilla's pdf.js, served from your own domain. Navigate with the
arrow keys, Home and End, the page-number field, the buttons, or by clicking
the left and right thirds of the page. Escape returns to the file. The
animation is skipped for visitors whose system asks for reduced motion.

= My library is very large. Does the sitemap still work? =

Yes. A sitemap may contain at most 50,000 URLs, so beyond that Folio serves
sitemap.xml as a sitemap index pointing at sitemap-1.xml, sitemap-2.xml, and
so on. Smaller libraries are served as a single file exactly as before.

= Does the sitemap notice when I only change a title? =

Yes. Each page reports the later of the file's modification time and the last
time its metadata changed, so retitling a document tells search engines that
page changed. Editing one document does not change any other entry.

= What happens when the site is non-indexable? =

Public pages emit noindex, nofollow. Sitemap and llms.txt return 404 until the
site is made indexable again.

= Can I add pages like About and FAQ? =

Yes, at ?action=pages. Two named slots (About, FAQ) and three custom slots are
available. All are off by default; enable and fill in Markdown to publish. FAQ
pages emit FAQPage structured data with Question and Answer entities parsed
from ## headings, useful for AI search visibility.

= How do I notify search engines when the library changes? =

The Crawlers screen has one-click Bing ping and IndexNow support (Bing, Yandex,
Naver, and others). Google no longer accepts sitemap pings; use Search Console
for Google. IndexNow requires generating a key from the same screen; Folio
hosts the verification file at /{key}.txt automatically.

= Does Folio use server tools like OCR? =

Yes, when they are installed. Folio looks for ocrmypdf, tesseract, pdftotext,
pdfinfo, pdftocairo, pdftoppm, pngquant and unpaper, and uses whichever are
present. None are required. Check ?action=diagnostics to see what was found,
where each one lives, and what it enables.

The main gain is OCR. A scanned document is a picture of text and cannot be
searched by anyone. With OCRmyPDF and Tesseract installed, Folio can make a
searchable copy. Your original file is never modified: the copy is kept in
data/ocr/ and can be deleted at any time.

PDF page previews are rendered with Poppler where it is installed.

= What happens to a document URL if I rename the file over FTP? =

Nothing. A document address is stored, not derived from the filename, so
renaming or moving a file does not change its URL.

After renaming or moving files, open ?action=diagnostics and run
reconciliation. Folio matches each record to its file by content and updates
only the stored path. The URL, title, transcript and everything else stay as
they were, and nothing on disk is touched.

If a file was renamed AND edited, its contents no longer match and Folio will
not guess. It shows the document as missing its file alongside the new
uncatalogued file, and you can relink them by hand. A record you no longer want
can be removed with the Forget button on its row; only records whose file is
missing can be forgotten, and no file on disk is touched.

= Can I change a document URL? =

Yes. Edit the URL slug field in the metadata editor. The old address then
redirects permanently to the new one. Change it again and every previous
address redirects straight to the newest, never through a chain.

= Does Folio manage my files? =

No, and deliberately. FTP owns the files; Folio owns the catalogue and the
public addresses. Folio never uploads, renames, moves, replaces or deletes
anything, and never creates folders.

= What is planned for future versions? =

docs/upgrading.md carries a roadmap: what is planned next, what is under
consideration, and what has been declined and why. It also lists the
principles that will not change.

The most important of those: Folio never modifies your files. It will not gain
upload, rename, move, delete or folder controls in any version. FTP owns the
files; Folio owns the catalogue and the public addresses.

== Changelog ==

Every tool is optional. Without ocrmypdf, OCR still runs using Tesseract and
Poppler. Without Tesseract there is no OCR but nothing else changes. Without
any of them Folio behaves exactly as it did before the feature existed.

PDF pages are rendered with Poppler where it is installed.

= 1.6.0 =

Document URLs are now permanent and independent of filenames and folders.
Adds an editable URL slug per document, automatic 301 redirects from previous
addresses, permanent internal document identifiers, and reconciliation that
matches records back to files by content after an FTP rename or move. Existing
metadata and existing URLs are preserved. Folio still performs no physical
file operation.

= 1.5.0 =

Folio now detects command-line utilities on the server and uses them when
present: OCR for scanned documents via OCRmyPDF and Tesseract, text
extraction via pdftotext, PDF page previews via Poppler,
and smaller PNGs via pngquant. Nothing is required and nothing changes if
none are installed. Originals are never modified; results are cached under
data/ and can be deleted freely.

= 1.4.2 =

Documentation accuracy. The installer, Diagnostics and this file told you to
rename htaccess.txt, a file that stopped shipping in 1.2.0 — .htaccess is
active as delivered, and when it is missing the usual cause is an FTP client
hiding dotfiles. Four fixes shipped in 1.4.0 were also missing from its
changelog and are now recorded.

= 1.4.1 =

Removes nginx.conf.example. Nginx was never actively maintained as a
deployment target and keeping the file implied a level of support that
wasn't real; Folio already fails safe on any server it can't confirm its
rewrite is active on, so this changes documentation, not behaviour.
Requirements, install steps, and the PDF access control docs now state
plainly that Apache or LiteSpeed is required, rather than carving Nginx
out as a special case.

= 1.4.0 =

Adds cached WebP derivative images (Imagick or GD) for listings, hover
cards, and detail pages, keyed to the source file's modification time and
size so replacing a file over FTP invalidates the cache automatically.
Adds viewable conversions for TIFF, HEIC, HEIF, and AVIF; the original file
remains what direct links and downloads give you. Adds PDF_SERVER_PREVIEW
(off by default) for optional server-side first-page PDF rendering. Fixes
an issue where an excluded folder's contents were hidden but the folder
itself, an empty listing row, and a CollectionPage entry in structured data
were not; exclusion now matches on path segments so nested content is
excluded however deeply nested. Restores uploads/.htaccess and .gitignore,
which were documented as shipped but missing from the 1.3.0 package.
Nginx is not a supported deployment target; nginx.conf.example has been
removed rather than kept as an unmaintained, partially-accurate reference —
see the FAQ above.

= 1.3.0 =

Adds per-file PDF access control (public/viewer/hidden) enforced through
a signed-URL raw endpoint, with a preflight confirmation step on the
Crawlers screen before anything is actually restricted. Adds document_type,
transcript, and language metadata fields, with the transcript rendered
server-side so restricted documents stay fully readable and indexable by
search engines and AI crawlers even when the original PDF is not. Adds
Dublin Core Terms alongside the existing Schema.org structured data, and
automatic blurred first-page previews for hidden PDFs where Imagick with a
server can render PDF pages (falls back to a manual placeholder image
otherwise). PDF access control requires Apache or LiteSpeed. Fixes the
analytics tracker being blocked by the security policy and collecting
nothing.

= 1.2.0 =

Adds an Analytics screen supporting Matomo and Google Analytics 4;
Folio itself stores no visit data, IP addresses, or geolocation, and
admin sessions are excluded by default. Fixes category chips not
filtering the listing, the hover preview rendering the library inside
itself, a PDF preview that could hang indefinitely, and a duplicate
download button on PDF pages.

= 1.1.0 =

Fixes a bug where every link pointed at localhost unless SITE_URL was
set, which broke navigation, both PDF readers, and the admin. Apache
config now ships as a real .htaccess with clean URLs active, and Folio
detects whether mod_rewrite actually works instead of assuming. Admin
login is a header dropdown again. Upgraders: upload the new .htaccess
and delete any old htaccess.txt files left from releases before 1.2.0.

= 1.0.1 =

Security: fixed HTML injection through JSON-LD structured data; logout now
requires POST with a CSRF token; login throttling is safe under concurrent
requests; the installer emits hardened security headers.
Changed: removed the retired Bing sitemap ping; IndexNow submissions are now
batched to respect the 10,000-URL limit.

= 1.0.0 =
* Initial release.
