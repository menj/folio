=== Folio ===
Contributors: menj
Tags: file manager, pdf, images, markdown, seo, document library
Requires PHP: 7.4
Tested up to: PHP 8.3
Stable tag: 1.0.0
License: Proprietary

A single-script PHP file library. Lists FTP-uploaded files, previews and
prints PDFs and images, and serves an SEO-ready detail page for every file.

== Description ==

Folio turns a web folder into a public document library with
no database. Files are managed entirely over FTP. An authenticated admin
assigns a title, a short description, one category, and up to ten tags per
file. Raw filenames and extensions stay hidden from visitors.

Major features:

* Directory listing with subfolder navigation and breadcrumbs
* Inline preview and printing for PDF, image, and Markdown files
* Markdown rendered to HTML by Parsedown in safe mode
* Full schema.org JSON-LD graph: WebSite, publisher, BreadcrumbList,
  CollectionPage, ItemList, and a typed node for every file
* Per-file titles, descriptions, categories, and tags
* Browsable category archive pages at /category/<slug>/ with their own
  SEO URLs, gathering documents from every folder
* Client-side filtering by tag chip
* One-click hotlink copying with stable, filename-bearing URLs
* SEO layer: crawlable detail pages, canonical URLs, Open Graph and
  Twitter Card tags, JSON-LD structured data, XML sitemap with image
  extensions, optional clean URLs via mod_rewrite
* Four colour schemes (Folio, Ledger, Garden, Night) remembered per visitor
* Security hardening: CSRF tokens, login throttling, strict CSP,
  hardened session cookies, path traversal guards, SVG script
  neutralisation

== Installation ==

1. Unzip the package. It expands to a folio/ folder; upload its
   contents into a web folder, for example /documents/.
2. Copy config-sample.php to config.php. All settings live there.
3. In config.php set ADMIN_USERNAME, and set ADMIN_PASSWORD_HASH to a
   bcrypt hash of your password:
   php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
   Until it is set, the admin login is disabled.
4. Set SITE_NAME, SITE_DESCRIPTION, PUBLISHER_TYPE, PUBLISHER_NAME,
   PUBLISHER_URL, and SITE_LANGUAGE in config.php.
5. Rename htaccess.txt to .htaccess. As shipped it only adds security
   hardening and changes no URLs, so it is safe in any folder.
   For clean URLs, uncomment the block at the bottom of that file and
   set PRETTY_URLS to true in index.php. Check the result at
   index.php?action=selftest, and comment the block out again if
   pages start returning 404.
6. Edit the Sitemap line in robots.txt, then upload robots.txt to the
   DOMAIN ROOT (not this folder). Merge it into an existing root
   robots.txt if you have one.
7. Upload files into the uploads/ folder over FTP.
8. Serve the site over HTTPS.

== Supported file formats ==

Displayed inline, with a detail page and a sitemap entry:

* PDF (.pdf) in an embedded viewer, printable
* Images (.png, .jpg, .jpeg, .gif, .webp, .bmp, .svg), printable
* Markdown (.md), rendered to HTML by Parsedown, printable
* Plain text (.txt), listed with a download link

Every other file type is listed, can be titled, described, categorised,
and tagged, and is downloadable. Such files are served as attachments
rather than displayed, and are left out of the sitemap.

To support another format, add its extension and MIME type to the
$mime_map array near the top of index.php.

== Upgrading ==

1. Back up uploads/ and config.php. The metadata file
   uploads/.sfm-meta.json holds every title, description, category,
   and tag you have written.
2. Overwrite index.php, assets/, lib/, and the documentation files.
3. Do NOT overwrite config.php, uploads/, or .htaccess.
4. Check config-sample.php for settings the release has added.
5. Run index.php?action=selftest.
6. Hard-refresh the browser (Ctrl+F5) so cached assets are replaced.

Rolling back means restoring the previous index.php and assets/.
Documents and metadata are never touched by an upgrade.

== Removing Folio ==

1. Download uploads/ first. Those documents exist nowhere else.
2. Keep uploads/.sfm-meta.json if you may reinstall; it restores the
   whole catalogue.
3. Delete the installation folder.
4. Remove the Sitemap: line pointing at Folio from the robots.txt at
   your domain root.
5. If the library was indexed, serve 410 Gone or redirect the old URLs.

Folio writes nothing outside its own folder: no database, no
configuration elsewhere on the server.

== Frequently Asked Questions ==

= Where are titles and tags stored? =

In uploads/.sfm-meta.json, keyed by relative path. Keep this file when
moving the installation. Renaming a file over FTP orphans its entry.

= How do I upload files? =

Over FTP only. The application reads the uploads/ folder and never
modifies your files.

= How do I generate the password hash? =

Three ways. Easiest: upload hash-tool.php beside index.php, open it
in a browser, type your password, and copy the define(...) line it
prints into config.php. Delete hash-tool.php afterwards.

With shell or cPanel Terminal:
php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"

Or create a temporary file hash.php containing
<?php echo password_hash('your-password', PASSWORD_DEFAULT);
load it in a browser, copy the output, then delete the file.

A valid hash starts with $2y$ and is 60 characters long.

= How do I change the admin password? =

Generate a new hash by any method above and replace the
ADMIN_PASSWORD_HASH line in config.php.

= What is the default password? =

There is none. Without config.php the hash falls back to CHANGE_ME,
which disables the login rather than accepting any password. Supply
your own hash to enable the admin.

= Is it safe to publish this in a public repository? =

Yes. config.php holds the credentials and is listed in .gitignore,
as is uploads/. Only config-sample.php with placeholders is committed.
If a real hash was ever committed, rotate the password: deleting it in
a later commit does not remove it from the repository history.

= What happens after failed logins? =

Eight failed attempts from one IP address lock that address out for
15 minutes.

= Why is robots.txt not in the application folder? =

Crawlers only read robots.txt at the domain root. A copy inside a
subfolder has no effect. Upload it to the root and edit the Sitemap
line to point at this installation.

= What is the URL of a file itself? =

Its real location in the uploads folder, e.g.
/documents/uploads/paper-title.pdf. Files are served directly by the
web server, not proxied through PHP. The uploads/.htaccess that ships
with Folio disables script execution there and sandboxes SVG files;
keep it. Older ?action=raw links are 301-redirected.

= Do page URLs include the file extension? =

No. A file's page URL is its filename minus the extension, slugified:
Acts 17 Reconsidered.pdf becomes /acts-17-reconsidered/. The extension
appears only on the raw asset URL. Old URLs containing the extension
are 301-redirected to the slug URL. Two files in one folder whose
names differ only by extension will collide; rename one.

= Can I read the documentation inside Folio? =

Yes. Log in and click Docs in the top bar, or open
index.php?action=docs. It renders README.md and CHANGELOG.md and shows
readme.txt as plain text. The page is admin-only and noindex.

= Clicking a file name returns 404. Why? =

PRETTY_URLS is true but the rewrite rules are not active. Set
PRETTY_URLS to false in index.php and links work again at once, or
install .htaccess correctly. Run index.php?action=selftest to see
which applies.

= Does it work on nginx? =

Yes, with PRETTY_URLS set to false. Clean URLs require Apache
mod_rewrite or equivalent nginx rewrite rules of your own.

= What file formats are supported? =

PDF, images (PNG, JPEG, GIF, WebP, BMP, SVG), Markdown, and plain text
are handled natively. Any other file type is still listed and
downloadable. See the Supported file formats section above.

= Can it display Markdown files? =

Yes. Files ending in .md are rendered to formatted HTML by Parsedown in
safe mode, both in the preview pane and on the file detail page.

= How do categories differ from tags? =

Each category has its own archive page with an indexable URL, gathering
every document in that category across all folders. Tags filter the
current view in the browser and have no pages of their own. Use
categories for the few durable divisions of the library.

= Can a folder be named "category"? =

No. That name collides with the category archive route. Rename it.

== Changelog ==

= 1.0.0 =
* Initial release: listing, preview, print, hotlinks, titles and
  descriptions, categories and tags with filtering, admin login,
  SEO detail pages, XML sitemap, clean URLs, security hardening,
  Markdown rendering via Parsedown 1.8.0 (MIT), category archive pages.
