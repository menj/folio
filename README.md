# Folio

**Folio** is a single-script PHP reading library for a web folder. It lists files uploaded over FTP, serves crawlable detail pages for each file, previews and prints PDFs and images, and lets an authenticated admin assign titles, descriptions, categories, and tags. No database is required.

## Features

* Directory listing with subfolder navigation and breadcrumbs
* Inline preview and printing for PDF, image, and Markdown files
* Markdown (.md) files rendered to formatted HTML by Parsedown, in safe mode
* Editable title and short description per file; the raw filename and extension stay out of sight
* One category and up to ten tags per file
* Browsable category archive pages with their own indexable URLs, gathering
  documents from every folder; tags filter the current view by chip
* Direct hotlinks to every file, copied to the clipboard in one click
* SEO layer: per-file detail pages with canonical URLs, Open Graph and Twitter
  Card tags, an XML sitemap with image extensions, and optional clean URLs
* Full schema.org JSON-LD graph on every page: WebSite, publisher, BreadcrumbList,
  CollectionPage, ItemList, and a typed node for each individual file
* Four colour schemes (Folio, Ledger, Garden, Night), remembered per visitor
* Hardened by default: CSRF tokens, login throttling, strict Content-Security-Policy, hardened session cookies, path traversal guards, and SVG script neutralisation

## Design

The interface is set as an open codex: the listing and the preview sit as two
leaves divided by a gutter. Titles are set in a Garamond stack, while the
apparatus — sizes, dates, labels, controls — is set in a sans face, after the
oxblood-and-graphite house monograph style. Hairline rules carry the structure.
Four colour schemes ship: Folio (oxblood on bone), Ledger (indigo on cool grey),
Garden (green), and Night (a dark scheme for evening reading).

## Supported file formats

| Format | Extensions | Preview | Print | Detail page | In sitemap |
| --- | --- | --- | --- | --- | --- |
| PDF | `.pdf` | Embedded viewer | Yes | Yes | Yes |
| Images | `.png` `.jpg` `.jpeg` `.gif` `.webp` `.bmp` | Inline image | Yes | Yes | Yes, with image extensions |
| SVG | `.svg` | Inline image | Yes | Yes | Yes, with image extensions |
| Markdown | `.md` | Rendered to HTML | Yes | Yes, rendered into the page | Yes |
| Plain text | `.txt` | No | No | Yes, with a download link | Yes |
| Anything else | any | No | No | Yes, with a download link | No |

Files of any other type are listed, titled, tagged, and downloadable; they are
simply served as attachments rather than displayed. SVG files are served under a
sandboxing Content-Security-Policy, so any script embedded in an SVG cannot run.
Markdown is rendered in Parsedown safe mode, so raw HTML inside a Markdown file
is escaped rather than executed.

To add a format, add its extension and MIME type to the `$mime_map` array near
the top of `index.php`. Formats a browser cannot display will fall back to a
download link.

## Requirements

* PHP 7.4 or newer (developed and tested on PHP 8.3)
* Apache with mod_rewrite for clean URLs (optional; the query-string URLs work everywhere)
* Write permission for the web server user on the `uploads/` directory

## File structure

```
index.php              Application (all server logic)
config-sample.php      Settings template; copy to config.php
hash-tool.php          Password hash generator; delete after use
.gitignore             Keeps config.php and uploads out of version control
assets/css/style.css   Stylesheet and colour schemes
assets/js/app.js       Listing behaviour: preview, print, editing, filtering
assets/js/view.js      Detail page behaviour: printing
assets/img/            favicon.svg, favicon.ico, apple-touch-icon.png
lib/Parsedown.php      Parsedown 1.8.0 (MIT), renders Markdown files
lib/Parsedown-LICENSE.txt
CHANGELOG.md           Version history
UPGRADING.md           Upgrade, migration, and removal instructions
htaccess.txt           Apache rules; rename to .htaccess
uploads-htaccess.txt   Copy of uploads/.htaccess, in case it is lost in transfer
robots.txt             Crawler permissions; upload to the DOMAIN ROOT
uploads/               Your files; created automatically on first run
uploads/.htaccess      Hardening for the publicly served uploads folder
```

## Installation

1. **Unzip the package.** It expands to a `folio/` folder. Upload the *contents*
   of that folder, not the folder itself, into the web folder you want to use,
   for example `/documents/`. You should end up with `index.php` sitting
   directly in that folder.

2. **Create your settings file.** Copy `config-sample.php` to `config.php`.
   All settings live there, and `config.php` is excluded from version control.

3. **Set your credentials** in `config.php`. `ADMIN_USERNAME` is the login name.
   `ADMIN_PASSWORD_HASH` takes a bcrypt hash of your password, never the
   password itself:

   The easiest way to produce one is the included tool: open
   `hash-tool.php` in a browser, type your password, and copy the line it
   gives you into `config.php`. **Delete `hash-tool.php` afterwards.**

   With shell access you can instead run:

   ```
   php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
   ```

   Until the hash is set, the admin login is disabled and the library is
   read-only.

4. **Set your identity values** in `config.php`: `SITE_NAME`,
   `SITE_DESCRIPTION`, `PUBLISHER_NAME`, and `SITE_LANGUAGE`. These appear in
   page titles and structured data.

5. **Check permissions.** The web server user needs write access to `uploads/`
   so titles can be saved. The folder is created automatically on first run.

6. **Install the Apache rules.** Rename `htaccess.txt` to `.htaccess`. As
   shipped it adds security hardening only and changes no URLs, so it is safe
   in any folder. Confirm `uploads/.htaccess` also arrived; FTP clients often
   skip dot-files, and a plain-text copy is included as `uploads-htaccess.txt`.

7. **Upload your documents** into `uploads/` over FTP. Subfolders become
   browsable sections.

8. **Verify** at `index.php?action=selftest`, which reports the base URL, URL
   mode, `.htaccess` status, and whether `uploads/` is writable.

9. **Publish the crawler rules.** Edit the `Sitemap:` line in `robots.txt` to
   match your address, then upload it to your **domain root**, not this folder.
   Merge it into an existing root `robots.txt` if you have one.

10. **Log in** through the Admin link and start titling documents.

### Clean URLs (optional)

Clean URLs change every address on the site, so leave them off until the rest
works.

1. Rename `htaccess.txt` to `.htaccess` if you have not already.
2. Uncomment the clean-URL block at the bottom of that file by deleting the
   leading `# ` from each line. The rules need no `RewriteBase`; they resolve
   relative to the folder the file sits in.
3. Only now set `PRETTY_URLS` to `true` in `index.php`. It ships as `false`
   because clean URLs return 404 on every page if the rewrite is not active.
4. Verify with `index.php?action=selftest`, which reports whether `.htaccess`
   is present, whether mod_rewrite is loaded, and whether the request reached
   the application through a rewrite.

The resulting URL scheme:

| URL | Serves |
| --- | --- |
| `/documents/` | Root listing |
| `/documents/papers/` | Folder listing |
| `/documents/paper-title/` | File detail page, no extension in the slug |
| `/documents/uploads/paper-title.pdf` | The file itself |
| `/documents/category/radd-papers/` | Category archive |
| `/documents/sitemap.xml` | XML sitemap |

If pages return 404 after switching on, the rewrite is not working: your host
may disallow `.htaccess` overrides (`AllowOverride`), may not have mod_rewrite
enabled, or `RewriteBase` may not match the folder. Set `PRETTY_URLS` back to
`false` and everything works again immediately. Query-string URLs are fully
indexable, carry the same canonical tags and structured data, and are a
perfectly good permanent setting.

## Troubleshooting

**Clicking a file name gives a 404.** `PRETTY_URLS` is `true` but the rewrite is
not active. Set it to `false`, or install `.htaccess` correctly. Run
`index.php?action=selftest` to see which.

**Titles will not save.** The web server user cannot write to `uploads/`. The
self-test reports this.

**Reading the documentation from the admin.** Log in and click **Docs** in the
top bar, or open `index.php?action=docs`. It renders `README.md` and
`CHANGELOG.md` through Parsedown and shows `readme.txt` as plain text, with a
tab for each. The page is admin-only and marked `noindex, nofollow`; visitors
are redirected to the library. Only those three filenames are readable, so no
user-supplied path ever reaches the filesystem.

**The self-test page** is at `index.php?action=selftest` and reports base URL,
URL mode, mod_rewrite status, `.htaccess` presence, whether the current request
was rewritten, the uploads path, and whether it is writable.

## Usage

### Managing files

All uploads, renames, and deletions happen over FTP. The application never writes to your files; it only reads the folder.

### Titles, descriptions, categories, tags

1. Click **Admin** in the top bar and log in.
2. Click **Edit** on any row. Fill in the title, description, category, and comma-separated tags, then save.
3. Metadata is stored in `uploads/.sfm-meta.json`, keyed by relative path. Keep this file when moving the installation. Renaming a file over FTP orphans its entry, so re-enter the metadata after a rename.

The category field suggests categories already in use, which keeps the taxonomy
consistent. Categories are a real taxonomy: each one has its own archive page at
`/category/<slug>/`, gathering every document in that category from across all
folders, with its own title, meta description, canonical URL, breadcrumb, and
`CollectionPage` structured data. Category archives appear in the XML sitemap and
are linked from the chip bar on every listing, from each row, and from each file
page, which gives search engines a clean internal link graph.

Tags work differently on purpose: they appear only on the rows that carry them,
not in the bar at the top, and clicking one filters the current view in the
browser without loading a page. Reserve categories for the few durable
divisions of the library, and tags for everything finer-grained.

A folder literally named `category` would collide with the archive route. Rename
it if you have one.

### Hotlinks

The **Link** button on each row copies the file's own URL, which is simply its
location in the uploads folder, for example
`https://menj.blog/documents/uploads/islamic-dilemma-refuted.pdf`. Files are
served straight from disk by the web server rather than proxied through PHP, so
hotlinks are as fast as any other static asset and work in `<img>` tags, embeds,
and posts. Older `?action=raw` links are 301-redirected to the same place.

Because the web server serves that folder directly, the shipped
`uploads/.htaccess` disables script execution there, blocks the metadata store,
and sandboxes SVG files so any script inside one cannot run. Keep that file.

### URL slugs

A file's page URL is its filename with the extension stripped and the result
slugified: `Acts 17 Reconsidered.pdf` becomes `acts-17-reconsidered`. The
extension survives only on the asset URL under `/raw/`, where it belongs.
Slugs derive from the filename rather than the title, so they stay stable when
you edit a title, and renaming the file over FTP is what changes the URL.

Requests that still carry the extension are answered with a 301 redirect to the
slug URL, so anything already indexed or linked keeps its value.

Two files whose names differ only by extension, such as `plate.png` and
`plate.pdf` in the same folder, produce the same slug; only one will be
reachable. Rename one of them.

### SEO

Upload `robots.txt` to your domain root, for example `https://menj.blog/robots.txt`,
after editing the `Sitemap:` line to match your domain and folder. A robots.txt
placed inside a subfolder is ignored. If a robots.txt already exists at the root,
merge the `Sitemap:` line into it rather than replacing the file. The supplied
version opens the whole site to search engines and to AI crawlers such as GPTBot,
ClaudeBot, PerplexityBot, and CCBot.

Submit `sitemap.xml` in Google Search Console. Detail pages carry the ranking signals, and their quality depends on the titles and descriptions you write. Untitled files fall back to thin generated metadata.

## Security notes

* Serve the site over HTTPS. The login form and session cookie depend on it.
* Login locks out an IP for 15 minutes after eight failed attempts.
* The metadata store is denied direct access by the `.htaccess`. On non-Apache hosts, confirm your server does not serve dotfiles.
* To rotate the password, generate a new hash and replace `ADMIN_PASSWORD_HASH`. Active sessions survive until they expire; restart PHP or change `session.save_path` contents to force a global logout.

## Structured data

Every page emits a linked schema.org `@graph` rather than an isolated node.

Listing pages carry `WebSite`, the publisher (`Person` or `Organization`),
`BreadcrumbList`, `CollectionPage` with `hasPart` for subfolders, and an
`ItemList` in which every file appears as a fully described `ListItem`.

File pages carry `WebSite`, the publisher, `BreadcrumbList`, `ItemPage`, and the
file itself typed as `ImageObject`, `DigitalDocument`, `Article`, or
`TextDigitalDocument` according to its format. File nodes carry name,
description, `contentUrl`, `encodingFormat`, `fileFormat`, `contentSize`,
`dateModified`, `datePublished`, `uploadDate`, `inLanguage`, `genre` from the
category, `keywords` from the tags, `isPartOf`, `publisher`, an
`associatedMedia` DataDownload, and a `DownloadAction`. Images additionally
carry `thumbnailUrl` and their real pixel `width` and `height`, and become the
`primaryImageOfPage`.

Set `PUBLISHER_TYPE`, `PUBLISHER_NAME`, `PUBLISHER_URL`, `SITE_DESCRIPTION`, and
`SITE_LANGUAGE` in `index.php` so the graph names you rather than a placeholder.
Validate output with the Google Rich Results Test or the Schema Markup Validator.

## Changing the admin password

You need a bcrypt hash of the new password. Any of these produces one:

**With the included tool.** Upload `hash-tool.php` beside `index.php`, open it
in a browser, type the password, and copy the `define(...)` line it prints into
`config.php`. Delete the file when finished.

**With shell or cPanel Terminal.**

```
php -r "echo password_hash('new-password', PASSWORD_DEFAULT), PHP_EOL;"
```

**With a temporary file.** Create `hash.php` containing
`<?php echo password_hash('new-password', PASSWORD_DEFAULT);`, load it in a
browser, copy the output, then delete it.

A valid hash begins with `$2y$` and is exactly 60 characters. Paste the whole
thing, quotes included, over the existing `ADMIN_PASSWORD_HASH` line.

Sessions already open stay valid until they expire. To force a global logout,
restart PHP or clear the session store.

## Publishing to a public repository

Credentials are kept out of the code so the repository can be public:

* `config.php` holds the username and password hash and is listed in
  `.gitignore`. Only `config-sample.php`, which contains placeholders, is
  committed.
* `uploads/` is ignored except for its `.htaccess`, so documents are not
  published with the code.
* `.htaccess` itself is ignored, since it is installation-specific.

There is no default password. With no `config.php` present, `ADMIN_PASSWORD_HASH`
falls back to `CHANGE_ME`, which disables the login entirely rather than
accepting anything. A fresh clone is therefore read-only until someone supplies
their own hash.

If a hash was ever committed, remember that removing it in a later commit does
not remove it from the repository history. Rotate the password.

## Upgrading and removing

See `UPGRADING.md`, which is also readable from the admin under **Docs**. In
short: back up `uploads/` and `config.php`, overwrite `index.php`, `assets/`,
and `lib/`, leave your settings and documents alone, then hard-refresh and run
the self-test.

To remove Folio, download `uploads/` first, then delete the installation folder.
Folio writes nothing outside it: no database, no configuration elsewhere on the
server. Remember to remove the `Sitemap:` line from your root `robots.txt`.

## Third-party components

Markdown rendering uses Parsedown 1.8.0 by Emanuil Rusev, distributed under
the MIT licence. The library lives in `lib/` with its licence text. It runs in
safe mode, so raw HTML inside a Markdown file is escaped rather than executed.

## Version

1.0.0. Single-file application with separated CSS and JS assets.
