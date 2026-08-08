# Folio

**Folio** is a single-script PHP reading library for a web folder. It lists files uploaded over FTP, serves crawlable detail pages for each file, previews and prints PDFs and images, and lets an authenticated admin assign titles, descriptions, categories, and tags. No database is required.

| | |
| --- | --- |
| Author | MENJ &nbsp;·&nbsp; <https://menj.blog> |
| Repository | <https://github.com/menj/folio> |
| Licence | GPL-3.0-or-later |

## Features

* Directory listing with subfolder navigation and breadcrumbs
* Inline preview and printing for PDF, image, and Markdown files
* Fast thumbnails when the Imagick or GD extension is available: listings and
  hover cards send small cached copies instead of full-size scans, and TIFF,
  HEIC, and AVIF files get a viewable preview. Originals are never modified,
  and with no image extension Folio serves them as it always has
* A page-flip reader for PDFs, rendering real pages with Mozilla's pdf.js from
  your own domain; keyboard, click-zone, and page-number navigation, and it
  respects `prefers-reduced-motion`
* Markdown (.md) files rendered to formatted HTML by Parsedown, in safe mode
* Hover preview cards on the listing: image thumbnails, first-page PDF previews,
  and titled tiles for other formats; on desktop and hover-capable pointers only
* Responsive layout: the listing collapses from a table into stacked cards on
  narrow screens, so rows never scroll sideways
* Editable title and short description per file; the raw filename and extension stay out of sight
* One category and up to ten tags per file
* Browsable category archive pages with their own indexable URLs, gathering
  documents from every folder; tags filter the current view by chip
* Client-side search across titles, descriptions, categories, tags, and
  filenames; appears once a folder holds three or more files and composes with
  the chip filters
* `EXCLUDE_PATTERNS` in `config.php` to hide specific files or folders from
  every public surface, including direct URL access
* Optional **Standalone pages** — About, FAQ, and three custom slots — edited in
  the admin, stored privately, with `AboutPage`, `FAQPage` (with parsed Question
  and Answer entities), and `WebPage` structured data
* Direct hotlinks to every file, copied to the clipboard in one click
* SEO layer: per-file detail pages with canonical URLs, Open Graph and Twitter
  Card tags, an XML sitemap with image extensions, a generated llms.txt map for
  AI crawlers, and optional clean URLs
* Crawler controls in the admin: indexability switch, sitemap and llms.txt
  toggles, robots.txt generator, sitemap preview, Bing ping, IndexNow key
  generation and one-click URL submission, and a clean-URL preflight that
  verifies mod_rewrite before letting the setting be enabled
* Focused schema.org JSON-LD: WebSite, optional publisher, breadcrumbs,
  CollectionPage and ItemList on archives, with detailed typed nodes on file pages
* Four colour schemes (Folio, Ledger, Garden, Night), remembered per visitor
* Admin managed in the browser: Settings, Accounts with multiple users and
  in-app password change, a documentation viewer, and a diagnostics page
* Hardened by default: CSRF tokens, namespaced login throttling, strict
  Content-Security-Policy, lazy hardened sessions, session revocation, symlink
  containment, atomic metadata storage, and active-file download controls
* Per-file PDF access control (`pdf_access`: public / viewer / hidden),
  enforced by a signed-URL endpoint once a routing preflight on the Crawlers
  screen confirms it's actually in effect (Apache only)
* Optional `transcript`, `document_type`, and `language` metadata fields, with
  the transcript rendered server-side so restricted documents stay fully
  readable and indexable even when the original PDF is not
* Dublin Core Terms alongside the existing Schema.org structured data
* Automatic blurred first-page previews for hidden PDFs,
  with a manual placeholder-image fallback where that isn't available

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
| PDF | `.pdf` | Embedded viewer, plus a page-flip reader | Yes | Yes | Yes |
| Camera and scanner | `.tif` `.tiff` `.heic` `.heif` `.avif` | Converted preview (needs Imagick) | Yes | Yes | Yes |
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

* PHP 8.4 or newer with JSON, password, random and `mbstring` support
* Apache or LiteSpeed using the supplied `.htaccess` with `mod_mime` and
  `mod_headers`; `mod_rewrite` is needed only for optional clean URLs
* Read permission for PHP on `uploads/` and write permission on `data/`

## File structure

```
index.php              Application (all server logic)
install.php            One-page guided installer; delete after use
readme.md, changelog.md, security.md   At the root, where conventions expect them
docs/                  Installation, upgrade, and readme.txt guides
config-sample.php      Settings template; copy to config.php
data/                  Private accounts, settings, metadata, and install token
.gitignore             Keeps configuration, runtime data, and uploads untracked
assets/css/style.css   Stylesheet and colour schemes
assets/css/flipbook.css  Styles for the PDF flip-view reader only
assets/js/app.js       Listing behaviour: preview, print, editing, filtering
assets/js/view.js      Detail page behaviour: printing
assets/js/admin.js     Admin-only: delete/remove confirmations, rewrite preflight
assets/js/flipbook.js  PDF flip-view reader; loaded only on that screen
assets/img/            favicon.svg, favicon.ico, apple-touch-icon.png
lib/parsedown/         Parsedown 1.8.0 (MIT), renders Markdown files
lib/pdfjs/             PDF.js (Apache 2.0), powers both PDF readers
lib/pdfjs/             Mozilla pdf.js 5.4.149 (Apache-2.0), with its licences
changelog.md           Version history
.htaccess              Apache rules, active as shipped
tests/                 Isolated integration smoke test
robots.txt             Neutral crawler template; upload to the DOMAIN ROOT
license.txt            GNU General Public License v3
uploads/               Your published files; keep this directory present
uploads/.htaccess      Hardening for the publicly served uploads folder
```

## Installation

See `docs/install.md` for the complete procedure. In brief:

1. Unpack the zip and upload the contents of `folio/`.
2. Make `uploads/` and `data/` writable by the web server.
3. The shipped `.htaccess` works as-is on Apache or LiteSpeed.
4. Open `install.php`. It creates `data/install-token.php`.
5. Read that one-time token over FTP, enter it in the installer, and provide the
   exact canonical `SITE_URL` for the Folio folder.
6. Complete the account and site fields, then delete `install.php`.
7. Log in, run the admin-only diagnostics, and publish a customised `robots.txt`.

Clean URLs can be enabled later; query-string URLs remain fully supported.

## Usage

### Managing files

All uploads, renames, and deletions happen over FTP. The application never writes to your files; it only reads the folder — with one narrow exception: a hidden probe file, `uploads/.folio-pdf-probe.pdf`, that the PDF access control preflight (below) creates to test itself. It's never shown anywhere and safe to delete; Folio recreates it if needed.

### PDF access control

Each PDF can be set to **Public** (default, unchanged from previous versions), **Viewer**, or **Hidden** in its inline editor:

* **Viewer** embeds the PDF through a short-lived signed link and removes the direct-link and flip-view download buttons. The file isn't reachable at its plain `uploads/` URL.
* **Hidden** serves no PDF bytes by any path — preview, flip view, print, or direct link. The detail page shows a notice and, where available, a redacted preview instead of the file.

Neither is enforced until you:

1. Set `FOLIO_URL_SIGNING_KEY` in `config.php` (generate it the same way as `FOLIO_AUTH_PEPPER` — never reuse that value here).
2. Confirm the preflight on the **Crawlers** screen, which proves that requests to a real PDF under `uploads/` actually reach Folio instead of being served directly by the webserver.

Until both are done, every PDF behaves as Public regardless of what's set on it — Diagnostics and each file's editor say so plainly. This is deliberate: a restriction that silently doesn't work would be worse than no restriction at all. **This feature requires Apache or LiteSpeed** (see Requirements above); the routing preflight fails safe on any server that can't confirm the rewrite, so an unsupported server never falsely presents a restriction.

Restricting a PDF never affects its record page's own indexability: the sitemap, the page's `robots` meta tag, and `llms.txt` reference the record page exactly as they would for any other file. Add a `transcript` in the editor and it renders directly in the page's HTML, so the content stays fully readable — by people and by search/AI crawlers — even when the original file is not.

For **Hidden** PDFs specifically, Folio can generate a blurred first-page preview automatically if the server can render PDF pages (check Diagnostics). Where that isn't available, set `placeholder_image` in the editor to the relative path of any image already in `uploads/` to use as a manual stand-in instead.

### Titles, descriptions, categories, tags

1. Click **Admin** in the top bar and log in.
2. Click **Edit** on any row. Fill in the title, description, category, and comma-separated tags, then save.
3. Metadata is stored atomically in `data/metadata.json`, keyed by relative path, with a last-known-good backup at `data/metadata.json.bak`. Existing `uploads/.sfm-meta.json` data is read for migration. Renaming a file over FTP still changes its key, so re-enter the metadata after a rename.

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

The **Link** button copies the safe delivery URL for the file. PDFs, raster
images, text, and Markdown use their real location in `uploads/`, for example
`https://example.com/documents/uploads/paper.pdf`, and are served directly by
the web server. SVG uses Folio's controlled delivery endpoint, while unsupported or active
formats use the same endpoint as forced downloads; none can become uncontrolled
same-origin executable content.
Legacy `?action=raw` links redirect to the appropriate current URL.

The supplied Apache/LiteSpeed rules reject symlinks and hidden files,
block executable formats, sandbox SVG, force unknown or active formats to
download, and mark raw documents `noindex` so their Folio detail pages remain
the search target. Keep those rules in force. The PHP endpoint remains a safe
fallback for links generated by Folio when direct inline delivery is unsuitable.

### URL slugs

A page slug includes the normalised filename and extension:
`Acts 17 Reconsidered.pdf` becomes `acts-17-reconsidered-pdf`. If two filenames
normalise to the same value, Folio appends a short stable hash, so every file
remains reachable. Old extensionless or extension-bearing URLs redirect to the
new canonical URL when they identify exactly one file.

Slugs derive from filenames rather than editable titles. Renaming a file over
FTP therefore changes its URL.

### SEO

Upload `robots.txt` to your domain root, for example `https://example.com/robots.txt`,
after editing the `Sitemap:` line to match your domain and folder. A robots.txt
placed inside a subfolder is ignored. If a robots.txt already exists at the root,
merge the `Sitemap:` line into it rather than replacing the file. The supplied
version opens the whole site to search engines and to AI crawlers such as GPTBot,
ClaudeBot, PerplexityBot, and CCBot.

Submit `sitemap.xml` in Google Search Console. Detail pages carry the ranking signals, and their quality depends on the titles and descriptions you write. Untitled files fall back to thin generated metadata.


## Regression tests

Run `./tests/smoke.sh` from the Folio root. The isolated test installation checks
routing, canonical URL trust, symlink containment, active-file delivery,
authentication, metadata integrity, session revocation, and sitemap caching.

## Structured data

Listing and category pages emit a focused graph containing `WebSite`, an
optional publisher, `BreadcrumbList`, `CollectionPage`, and a lightweight
`ItemList`. File pages add `ItemPage` and the file itself as `ImageObject`,
`DigitalDocument`, `Article`, or `TextDigitalDocument`.

Filesystem modification time is used only as `dateModified`; Folio does not
pretend it is the publication or upload date. Publisher nodes are omitted when
no publisher name is configured. Set identity values in `config.php` or the
Settings screen and validate the result with a schema validator.

File-page JSON-LD also mirrors Dublin Core Terms (`dcterms:title`,
`dcterms:type`, `dcterms:subject`, `dcterms:date`, `dcterms:format`,
`dcterms:language`, `dcterms:modified`) alongside the Schema.org fields,
additive to the same graph rather than a separate block.

## What Folio is not

Folio is a **public** library. Being clear about this up front saves
disappointment later, because several reasonable-sounding expectations are
outside what it does:

* **Every document is public unless excluded.** Anything in `uploads/` is
  served to anyone who asks, and appears in the listing, the sitemap, and
  structured data. `EXCLUDE_PATTERNS` in `config.php` hides files and folders
  from every public surface, but that is a publishing decision, not an access
  control: an excluded file is simply treated as absent.
* **There is no per-document permission model.** Folio cannot show one
  document to one visitor and hide it from another. If you need that, you need
  an access-controlled repository, not Folio.
* **Every account has full administrative authority.** Accounts exist so that
  several people can administer the library with their own passwords and so
  that one person's access can be revoked. There are no roles, no read-only
  administrators, and no per-folder delegation.
* **Anyone with FTP access controls the library.** FTP is the intended way to
  add and remove documents, so the FTP account is the real trust boundary.
* **Metadata is intentionally shallow.** A title, a description, one category,
  and up to ten tags. There are no custom fields, relationships, or workflow
  states, and no versioning of documents or their metadata.
* **It is not a digital-asset manager.** Folio never modifies the original
  file. It generates small, disposable derivative copies for faster viewing
  (thumbnails, format conversions, blurred previews) — all deleted and
  regenerated freely — but it does not watermark, edit, or version the
  original in any way, and the original is always what direct links and
  downloads give you.

## The admin at a glance

| Screen | Where | Purpose |
| --- | --- | --- |
| Library | `index.php` | Edit titles, descriptions, categories, tags |
| Settings | `?action=settings` | Site name, description, publisher, language, Admin-link visibility |
| Analytics | `?action=analytics` | Matomo and GA4 configuration |
| Crawlers | `?action=crawlers` | Sitemap, llms.txt, indexability, robots.txt, sitemap preview, Bing ping, IndexNow, clean-URL preflight |
| Accounts | `?action=users` | Change your password, add, reset, delete accounts |
| Docs | `?action=docs` | Read the Readme, Upgrading guide, and Changelog |
| Pages | `?action=pages` | Optional standalone pages (About, FAQ, three custom slots) |
| Log in | `?action=login` | Direct sign-in page, works with the Admin link hidden |
| Diagnostics | `?action=diagnostics` | Environment, addressing, and configuration health |

## Settings screen

Log in and click **Settings** in the top bar, or open
`index.php?action=settings`. From there you can rename the site and edit the
description, publisher identity, language, and the Admin-link visibility. The
change applies immediately across page titles, the header, the `lang`
attribute, and the structured data.

Saved settings are written to `data/settings.php` and take precedence over
`config.php`, which remains the fallback and still holds the settings that can
take the site down if misconfigured: `SITE_URL`, `PRETTY_URLS`, `TRUST_PROXY_HEADERS`, and `UPLOADS_DIRNAME` stay
file-only on purpose.

## Crawler controls

Log in and click **Crawlers**, or open `index.php?action=crawlers`. From there:

* **Indexability.** One switch marks every public page `noindex, nofollow`,
  for a library that is not yet ready to be found. On by default.
* **XML sitemap.** Toggle whether the sitemap is served; disabled, it returns
  404 and the head link disappears.
* **llms.txt.** Folio generates a curated Markdown map of the library for AI
  crawlers at `/llms.txt` (or `?action=llms`), built live from your titles,
  descriptions, and categories, with an optional introduction paragraph you
  write on this screen. Toggle it off to return 404. It also returns 404 while the whole site is non-indexable.
* **robots.txt generator.** The screen shows a robots.txt reflecting the
  settings above, ready to copy into the file at your domain root. Folio never
  writes outside its own folder, so that final step stays manual by design.
* **Clean URLs** with a real preflight check: click **Test rewrite**, and Folio
  probes a fake pretty URL through your `.htaccess`. Only if that probe
  succeeds is the Enable button revealed. It cannot silently take the site
  down.
* **Sitemap preview** shows the URL count and the first few entries the
  sitemap will contain.
* **Notify search engines**: Folio offers no sitemap "ping" button, because
  the anonymous ping endpoints no longer exist. Microsoft retired Bing's in
  May 2022 (it answers `410 Gone`) and Google retired its own in 2023. A
  button would report success while doing nothing. Use the `robots.txt`
  sitemap reference, Bing Webmaster Tools or Google Search Console for manual
  submission, or IndexNow below.
* **Change dates that mean something**: each sitemap entry reports the later
  of the file's modification time and the last time its metadata changed, so
  retitling a document or rewriting its description tells search engines that
  page changed. Editing one document does not disturb any other entry. Folder
  and category entries take the newest date among their contents.
* **Sitemap partitioning**: a sitemap may contain at most 50,000 URLs. Larger
  libraries are served as a sitemap index at `sitemap.xml` pointing to
  `sitemap-1.xml`, `sitemap-2.xml`, and so on. Smaller libraries are served as
  a single file exactly as before, so most sites see no change.
* **IndexNow**: generate a key, host it at the site root as `{key}.txt`
  automatically, and submit every URL in the library in one click. Compatible
  engines are Bing, Yandex, Naver, and others. The protocol accepts at most
  10,000 URLs per request, so larger libraries are submitted in batches
  automatically; each batch is reported on separately, and a partial failure
  is never described as a complete success. Folio submits the whole library
  rather than only what changed.

## Image thumbnails

If your host has the **Imagick** or **GD** PHP extension, Folio generates small
cached copies of images and serves those in listings, hover cards, and detail
pages. On a library of scanned documents this is the single largest speed
difference available: hovering a row previously downloaded the entire original.

Imagick additionally reads **TIFF, HEIC, HEIF, and AVIF** — formats no browser
displays — so scans and phone photos get a viewable preview instead of
appearing as an unknown download. The original is untouched; the direct link
and download always give you the real file.

Check which engine you have at `index.php?action=diagnostics`. If neither
extension is present, nothing breaks: Folio serves originals exactly as it did
before, and the Diagnostics row tells you what to ask your host to enable.

Derivatives are stored in `data/thumbs/`. The folder is disposable — delete it
whenever you like and it rebuilds on demand. Replacing a file over FTP
invalidates its derivatives automatically, so you never need to clear a cache
by hand.

Two behaviours are deliberate. Generated images have their metadata stripped,
so EXIF GPS coordinates from a phone photo are not republished in a public
thumbnail. And server-rendered PDF thumbnails are **off** by default; the in-browser
reader already previews PDFs. Set `PDF_SERVER_PREVIEW` to true in
`config.php` if you want them.

### Turning on PDF access control

By default every PDF behaves as **Public**, whatever you set on it. Two things
must be in place before Folio will enforce a restriction, because enforcement
depends on your web server actually routing PDF requests through Folio. If it
enforced without checking, it would claim a document was protected while
anyone could still download the file directly — worse than not having the
feature at all.

**1. Add a signing key.** Open `?action=crawlers`. When no key is set, Folio
shows a freshly generated one, ready to copy:

```php
define('FOLIO_URL_SIGNING_KEY', '…64 hex characters…');
```

Copy the whole line into `config.php` and reload. A new key is offered on
every visit until the setting is in place, so use the one in front of you.

Folio does not write it into `config.php` for you deliberately. That file
stays one the application cannot modify, which is what stops any future flaw
from rewriting Folio's own configuration.

Prefer to generate it yourself? Any of these work:

```sh
php -r 'echo bin2hex(random_bytes(32)), "\n";'
openssl rand -hex 32
head -c 32 /dev/urandom | xxd -p -c 64
```

Use a **different** value from `FOLIO_AUTH_PEPPER`. They protect different
things, and reusing one weakens both. Changing the signing key later
invalidates any signed link already shared.

**2. Confirm the preflight**, on the same screen. Folio requests a PDF and
checks that the request reached it rather than being served straight off disk
by the web server. Only when that passes does the Confirm button appear.

This needs Apache or LiteSpeed with `mod_rewrite` and the rule that ships in
`.htaccess`:

```apache
RewriteRule ^uploads/(.+\.pdf)$ index.php?action=raw&serve=1&file=$1 [L,QSA,NC]
```

If you renamed the uploads folder, change `uploads` in that rule to match, or
restrictions silently will not apply. The preflight reports this.

**3. Set access per document** in its Edit panel: Public, Viewer (links are
signed and expire), or Hidden.

Diagnostics tells you where you are at every stage, and while a key is set but
the preflight is unconfirmed it names the specific files that are marked
restricted but still behaving as public.

## Server utilities and OCR

If the server already has certain command-line tools — often because another
application on the same host needs them — Folio finds them and puts them to
use. Nothing is required: with none installed, everything works as before.
`index.php?action=diagnostics` lists what was found, where, and what each one
enables.

| Tool | What Folio does with it |
| --- | --- |
| `ocrmypdf` + `tesseract` | Make scanned documents searchable |
| `pdftotext` | Pull text out of PDFs, cached for reuse |
| `pdfinfo` | Page counts and PDF facts |
| `pdftocairo` or `pdftoppm` | Render PDF page previews |
| `pngquant` | Smaller PNG derivatives |
| `unpaper` | Straighten crooked scans before OCR |

### OCR

A scanned document is a picture of text: nothing can search it, and no
crawler can read it. Where OCRmyPDF and Tesseract are installed, Folio can
produce a searchable copy.

**Your file is never modified.** Folio reads it and writes a separate copy
under `data/ocr/`; that copy is what text is read from. Delete `data/ocr/` at
any time and nothing is lost but the work.

A PDF that already contains text is reported as already searchable rather
than being reprocessed, and pages inside a mixed document that already carry
text are left untouched.

OCR is slow — seconds to minutes for a long document — so it runs from the
admin when you ask for it, one document at a time, never during a visitor's
page load.

Languages come from `OCR_LANGUAGES`, which defaults to English, Malay
(`msa`), and Arabic (`ara`). Only datasets actually installed are used;
Diagnostics names any that are missing so you know which Tesseract language
pack to ask your host for.

### What happens when a tool is missing

Every one of these is optional. Nothing here is required for Folio to run, and
nothing breaks when a tool is absent — a feature is simply unavailable.

Folio looks in the usual system directories and, because a shared host will
not let you write to them, in this account's own home as well: `~/.local/bin`,
`~/bin`, and any virtual environment such as `~/ocrmypdf-venv/bin`. A tool
your host installed for your account only is found without configuration.
Diagnostics lists everything found and where it looked.

| Missing | Effect |
| --- | --- |
| `ocrmypdf` | OCR still works, using Tesseract and Poppler instead |
| `tesseract` | No OCR. Everything else is unaffected |
| `pdftotext` | No text extraction from PDFs |
| `pdftocairo` and `pdftoppm` | No PDF previews; the original file is served |
| `qpdf` | Single-page documents still OCR; multi-page ones explain why not |
| `pngquant` | Rendered PDF pages are simply larger |
| everything | Folio behaves exactly as it did before this feature existed |


## Document URLs

A document's public address is **permanent and independent of its file**.
Renaming `award_1997.pdf` to `scrabble_award.pdf` over FTP, or moving it to a
different folder, does not change its URL.

That separation is deliberate:

> **FTP owns the files. Folio owns the addresses.**

Folio never uploads, renames, moves, replaces, or deletes anything, and never
creates folders. It only reads your library and maintains its own catalogue.

### Editing a URL slug

Open a document's **Edit** panel in the listing. The **URL slug** field sets
the permanent address. Enter lowercase letters, numbers, and hyphens.

Changing it leaves a **permanent 301 redirect** from the old address, so
existing links and search-engine results keep working. Change it again and
both previous addresses redirect **directly** to the newest one — Folio never
builds a chain of redirects.

A slug is refused if it is empty, is a reserved Folio route such as `admin` or
`sitemap`, is already used by another document, or is a previous address of
another document. Pasting a full web address is refused rather than silently
converted, since that is almost always a mistake.

### After renaming or moving files over FTP

Folio finds the file again by its contents.

1. Rename or move the file however you like over FTP.
2. Open `index.php?action=diagnostics`. The **catalogue** row lists any
   document whose file has gone missing.
3. Run reconciliation. Folio matches each record to a file with identical
   contents and updates only the stored path.

The document keeps its URL, title, description, transcript, category, tags,
and access settings. Nothing on disk is touched.

Open **Catalogue** in the admin bar to do this. It lists what has come adrift
and offers both repairs.

### When a file cannot be matched

Content matching only works while the bytes are unchanged. If a file was
renamed **and** edited, its fingerprint no longer matches and Folio will not
guess — attaching a document's history to the wrong file is worse than asking
you to choose. The same applies when several files share identical contents.

Those cases appear in Diagnostics as a document whose file is missing
alongside a file that is not yet catalogued, and you can relink them by hand.
Relinking changes only which file the record points at.

### Folders

Folders still organise browsing, and renaming one over FTP still changes the
folder's browsing URL. They do **not** determine document addresses: a
document's slug is a flat name, independent of where the file lives.

## PHP extensions

Only **mbstring** is genuinely required, and only for rendering Markdown.
Everything else Folio can use has a fallback, so a missing extension costs a
capability rather than breaking the site. `?action=diagnostics` reports each
one and says what is lost when it is absent.

| Extension | What Folio does with it | Without it |
| --- | --- | --- |
| **mbstring** | Correct handling of multi-byte text | Markdown rendering is disabled |
| **Imagick** | Thumbnails; converts TIFF, HEIC and AVIF for viewing; blurred previews | Falls back to GD |
| **GD** | Thumbnails for PNG, JPEG, GIF and WebP | No thumbnails; originals are served directly |
| **fileinfo** | Detects file types from content, not just the extension | Falls back to the filename extension |
| **iconv** | Transliterates accents when building URL slugs | Accents are stripped instead of converted |
| **OPcache** | Avoids recompiling Folio on every request | Works, but noticeably slower |

Folio does **not** use cURL, intl, ZIP, XML, EXIF, or any database extension.
IndexNow uses PHP's own stream functions, sitemaps are built as strings, and
there is no database. If your host offers a minimal PHP build, none of those
are worth asking for.

The two that most affect a scanned-document library are **Imagick** — without
it, hovering a row downloads the full-size original instead of a thumbnail —
and **OPcache**, because Folio is one large file.

## Making scanned documents searchable (OCR)

A scanned document is a picture of text. Nobody can search it, no crawler can
read it, and copying a line out of it is impossible. OCR reads the picture and
attaches a text layer, so the document becomes searchable while looking
exactly the same.

Folio does this with **OCRmyPDF** and **Tesseract** when your server has them.
Neither is required: without them everything else works and the OCR button
simply does not appear.

### Step 1 — Check what your server has

Sign in and open `index.php?action=diagnostics`. Two rows matter:

* **external utilities** — lists every tool found and its full path.
* **OCR** — says whether OCR is ready, and in which languages.

If OCR is ready, skip to step 3.

### Step 2 — Ask your host to install what is missing

On a cPanel or AlmaLinux server, this is usually:

```
dnf install tesseract tesseract-langpack-eng tesseract-langpack-msa \
            tesseract-langpack-ara poppler-utils qpdf
```

OCRmyPDF is a Python program and is normally installed into a virtual
environment belonging to your account, because a shared host will not let you
write to `/usr/bin`:

```
python3 -m venv ~/ocrmypdf-venv
~/ocrmypdf-venv/bin/pip install ocrmypdf
```

**Folio finds that automatically.** It searches your account's home directory
as well as the system paths, including `~/.local/bin`, `~/bin`,
`~/ocrmypdf-venv/bin`, and cPanel's `~/virtualenv/<app>/<version>/bin`. If a
tool lives somewhere unusual, name it directly in `config.php`:

```php
define('TOOL_PATHS', [
    'ocrmypdf' => '/home/youruser/ocrmypdf-venv/bin/ocrmypdf',
]);
```

Nothing beyond the packages above is required.

Language packs are separate from Tesseract itself and are often missing even
when Tesseract is installed. Malay is `msa` and Arabic is `ara`. Diagnostics
names any that are absent, so you know exactly what to ask for.

### Step 3 — Choose your languages

By default Folio asks for English, Malay, and Arabic. Only the ones actually
installed are used. To change the list, in `config.php`:

```php
define('OCR_LANGUAGES', ['eng', 'msa', 'ara']);
```

Order matters a little: put the most likely language first.

### Step 4 — Run it

In the listing, each PDF row shows an **OCR** button when you are signed in
and OCR is available.

Click it. The button counts up while it works — expect anything from a few
seconds for a one-page certificate to several minutes for a long document.
When it finishes it reports how much text was found and stays marked, so you
can see at a glance which documents you have already done.

If a PDF already contains text, Folio says **Already searchable** immediately
rather than spending minutes reaching the same conclusion.

### What OCR does and does not touch

**Your original file is never modified.** Folio reads it and writes a separate
searchable copy into `data/ocr/`. That copy is what text is read from.

You can delete `data/ocr/` at any time. Nothing is lost except the work, and
you can run OCR again. Replacing a file over FTP invalidates its OCR copy
automatically, because the cache is keyed to the file's size and modification
time.

### When OCR does not work well

* **A poor scan gives poor text.** Faint, skewed, or low-resolution originals
  are hard to read. Installing `unpaper` lets Folio straighten and clean pages
  before reading them, which helps noticeably with older photocopies.
* **Handwriting is not recognised.** Tesseract reads printed text. A
  handwritten certificate will produce little or nothing.
* **The wrong language gives nonsense.** A Malay document read as English
  produces plausible-looking rubbish. Check the OCR row in Diagnostics lists
  the language you need.
* **Long documents may hit a time limit.** `OCR_TIMEOUT` defaults to 600
  seconds. If a document is cut short, raise it in `config.php`.

Where OCR cannot help, the **transcript** field in the metadata editor lets you
type or paste the text yourself. That is often the better answer for a short
handwritten document.

## Your own site icon

Make a folder called `branding/` beside `index.php` and put your icon in it:

```
branding/favicon.svg            preferred
branding/favicon.png            or this
branding/favicon.ico            for older browsers
branding/apple-touch-icon.png   for iOS home screens
```

Folio picks these up automatically — there is nothing to configure. They are
served both through the `<link>` tags and at the root paths browsers request
on their own, such as `/favicon.ico`, which is what tabs and bookmarks use.

Use `branding/` rather than replacing the file inside `assets/`. `assets/` is
release-owned, so an upgrade would overwrite whatever you put there and your
icon would silently revert. `branding/` is never touched by an upgrade.

To point somewhere else instead, set `SITE_ICON` in `config.php` to a path
inside the installation or a full URL. Diagnostics reports which icon is in
use.

If a new icon does not appear immediately, it is almost always the browser
cache — icons are cached far more aggressively than pages. Check in a private
window before concluding it has not worked.

## Getting documents found

Folio publishes two sitemaps:

| Sitemap | Lists |
| --- | --- |
| `/sitemap.xml` | Record pages, categories, and standalone pages |
| `/sitemap-pdf.xml` | The PDF files themselves |

The second matters for a document library. Search engines index PDFs as pages
in their own right, so a scanned certificate can be found directly rather than
only through the page describing it. Both are announced in `robots.txt`.

Documents are served `index, follow`, so crawlers may index them and follow
the links inside them.

**Every PDF in the library is listed**, whatever its access setting. The
access setting governs delivery, not discovery.

Files matching `EXCLUDE_PATTERNS` are the one exception, and not really an
exception at all: those are not part of the library. They return 404 on every
route, so a sitemap entry would point at nothing.

## Sorting and filtering the listing

Click **Name**, **Size** or **Date** to sort; click again to reverse. An arrow
marks the active column, and the headers work by keyboard.

Sorting uses the real values, not what is shown, so sizes order by bytes and
dates order chronologically whatever form they were entered in. A document
without its own date sorts after those that have one.

Search is the magnifier in the header, or press `/` from anywhere on the
page. Escape closes it and keeps the results.

Sorting and filtering are separate. The category chips and the search box
choose which documents appear; sorting only changes their order, so the two
compose. Your chosen order is remembered while you browse.

## Making a PDF smaller

Scanners often write PDFs that store their page images with little or no
compression, so a single certificate can arrive as tens of megabytes.

Each PDF row has a **Compress** button. It prepares a smaller copy with qpdf,
losslessly — the structure is rewritten and the streams recompressed, and the
images are left alone, so the result is the same document to any reader. On an
unoptimised scan this can be dramatic: 42 MB down to 57 KB in testing.

**Your file is not replaced.** The copy downloads to you; put it in place over
FTP if you want it, exactly as you would any other change to the library.
Because the path stays the same, the document keeps its URL and metadata.

A copy is only offered when it saves at least 3% and still opens correctly.
An already-efficient document says so rather than being duplicated for
nothing.

## Standalone pages

Folio can optionally publish a few informational pages alongside the library:
an **About** slot, a **FAQ** slot, and three general **custom** slots. All are
disabled on a fresh install, so the site stays a pure library until you fill
one in.

Log in and click **Pages**, or open `index.php?action=pages`. Each slot has an
enabled toggle, a title, an optional shorter menu label, and a Markdown body.
Content is written in Markdown; raw HTML in the body is escaped for safety.

Enabled pages appear in the header nav for public visitors and in the XML
sitemap when the site is indexable. URLs are `/about/`, `/faq/`, and
`/p/<slug>/` under clean URLs, or `?page=<slot>` otherwise. Disabled or unknown
slugs return a real 404.

Each page emits the correct schema.org type: `AboutPage` for About, `FAQPage`
for FAQ (with `Question` and `Answer` entities parsed from `##` headings), and
`WebPage` for the custom slots. Pages are stored privately in `data/pages.json`
through the same atomic transaction as settings and metadata.

### Page addresses

Each page has a **URL slug** field. It sets the page's address directly:
`/bibliography/`, not `/p/page1/`. Leave it empty and the page uses its slot
name.

Pages and documents share one address space, so a slug is refused if another
page, a document, or a Folio route already uses it. About and FAQ default to
`/about/` and `/faq/`.

Changing a slug leaves the old address redirecting permanently, and the
original `/p/slot/` form keeps working too, so nothing you have already linked
to breaks.

## Hiding the admin link

`SHOW_ADMIN_LINK` controls whether the **Admin** link appears in
the header for logged-out visitors.

```php
define('SHOW_ADMIN_LINK', false);
```

It is a checkbox on the Settings screen, or a constant in `config.php`. With it
off, the header shows nothing and the library reads as a public archive.
You can still sign in at any time through the direct login page:

```
index.php?action=login
```

That page exists whether the link is shown or not, so hiding it cannot lock you
out. Bookmark it before switching the link off.

Treat this as tidiness rather than security. It keeps the login out of sight of
casual visitors and of scanners that follow visible links, but anyone who knows
the URL can still reach the form. The real protections are the password, the
lockout after eight failed attempts, and HTTPS.

## Accounts

Log in and click **Accounts** in the top bar, or open `index.php?action=users`.
From there you can change your own password, add accounts, reset another
account holder's password, and delete accounts.

Until the first change is made there, the single account in `config.php` is
used. The first change writes all accounts to `data/users.php`, after which
`config.php` credentials are no longer consulted. That file is a PHP file that
returns an array, so even if the web server were misconfigured and served it
directly, it would execute and output nothing. The shipped `data/.htaccess`
denies access to the folder outright.

Rules the screen enforces: passwords are at least 10 characters, usernames are
3 to 32 characters of letters, digits, dot, dash, or underscore, changing your
own password requires your current one, you cannot delete the account you are
signed in with, and the last remaining account cannot be deleted. Every account
has the same full access; there are no roles. Password resets and account
deletions revoke previously issued sessions through an account version check.

For this to work, the web server needs write access to `data/`. The Accounts
screen says so plainly if it does not.

## Setting the first password by hand

Before any account exists, credentials come from `config.php` and you need a
bcrypt hash. Any of these produces one:

**With shell or cPanel Terminal.**

```
php -r "echo password_hash('new-password', PASSWORD_DEFAULT), PHP_EOL;"
```

**With a temporary file.** Create `hash.php` containing
`<?php echo password_hash('new-password', PASSWORD_DEFAULT);`, load it in a
browser, copy the output, then delete it.

A valid hash begins with `$2y$` and is exactly 60 characters. Paste the whole
thing, quotes included, over the existing `ADMIN_PASSWORD_HASH` line.

Once accounts are managed through the Accounts screen, changing or resetting a
password revokes older sessions automatically. For a hand-edited fallback
account in `config.php`, changing the cookie name forces a fresh login.

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

See `docs/upgrading.md` for the full procedure, also readable from the admin under
**Docs**. In brief:

**To upgrade:** back up `uploads/`, `data/`, `config.php`, and `.htaccess`.
Overwrite `index.php`, `assets/`, `lib/`, and the documentation files with the
new release. Leave your `config.php`, `data/`, `uploads/`, and `.htaccess`
alone. Run the diagnostics, hard-refresh, and verify.

**To remove:** download `uploads/` first, then delete the installation folder.
Folio writes nothing outside it: no database, no configuration elsewhere on the
server. Remove the `Sitemap:` line from your root `robots.txt` afterwards.

## Site secrets

Two optional constants add defence-in-depth. Generate random values locally on
the server, for example with `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`,
and place them in `config.php`.

- **`FOLIO_AUTH_PEPPER`** is HMAC-mixed into every stored password hash. If
  `data/users.php` ever leaks in isolation — through a backup exposure, for
  example — the hashes remain uncrackable without the pepper sitting in
  `config.php`. **Never change this value once accounts exist**: it invalidates
  every hash and every account must reset their password. Existing hashes
  without a pepper migrate transparently on next successful login.

- **`FOLIO_COOKIE_NAME`** replaces PHP's default `PHPSESSID` cookie name with a
  site-specific one, so sessions cannot collide with other PHP applications on
  the same domain. Safe to change at any time; the worst it does is log
  everyone out.

Both are optional. Without them, Folio uses bcrypt as before and the default
session cookie. Adding them later is safe: peppering migrates hashes lazily,
so no reset is required as long as the pepper never changes afterwards.

## Where Folio is going

[`docs/upgrading.md`](docs/upgrading.md) carries a roadmap: what is planned
next, what is being considered, and — deliberately — what has been declined
and why. It also states the principles that will not change, chief among them
that Folio never modifies your files and will never gain upload, rename, move,
or delete controls.

Read it before planning around a feature. Something listed as declined is not
waiting for a good enough reason.

## Security and hardening

[`security.md`](security.md) records what Folio actually enforces — output
encoding, path containment, file delivery, authentication and its limits,
request-forgery coverage, response headers, and canonical addressing — along
with the deployment steps Folio cannot enforce itself, such as HTTPS, file
permissions, keeping `data/` unreadable from the web, and removing
`install.php` after setup. It also states the known weaknesses honestly, and
explains what counts as a vulnerability and how to report one privately.

## Testing

[`tests/readme.md`](tests/readme.md) documents the regression suite: how to
run it, what each group proves, the security payloads it replays, and — just
as importantly — what it does **not** cover, so a passing run is not
over-read.

## Theme architecture

Themes are a set of seven CSS custom properties on the `html` element. There is
no build step, no preprocessor, and no per-theme stylesheet: everything in
`assets/css/style.css` refers to these tokens, so defining them for a new
`data-theme` value restyles the entire application.

```css
html[data-theme="folio"] {
    --paper:   #f3f2ee;   /* page background, the surface behind everything */
    --leaf:    #fbfaf8;   /* raised surfaces: the document itself, cards */
    --ink:     #26262a;   /* body text */
    --quiet:   #75727a;   /* apparatus: metadata, captions, labels */
    --accent:  #6e1d1d;   /* links, active states, primary buttons */
    --rule:    #ddd9d2;   /* hairlines, borders, dividers */
    --gutter:  rgba(38, 38, 42, 0.09);  /* the seam between the two leaves */
}
```

Four themes ship: `folio` (oxblood on bone), `ledger` (indigo on cool grey),
`garden` (green on warm white), and `night` (dark). The selector renders as the
row of colour dots in the header; the choice is stored client-side and applied
to the `data-theme` attribute.

### Adding a theme

Append a block to `assets/css/style.css` defining all seven tokens, then add a
swatch to the theme selector. Nothing else needs to change. Two constraints
worth respecting:

- `--accent` is used for both text on `--leaf` and as a button background with
  `--leaf` as its text colour, so it needs contrast in both directions.
- `--quiet` must stay legible against `--paper`; it carries all metadata.

### Layout

The listing is a two-leaf codex: documents on the left, preview on the right,
divided by `--gutter`. Below 900 pixels the preview pane collapses and the
hover card is disabled outright, since hover has no meaning on touch.

Typography is a Garamond stack for text and a sans stack for apparatus —
metadata, labels, buttons, chips. That split is the main thing keeping the
interface from reading as a generic admin panel, so new UI should follow it:
anything the reader reads is serif, anything the interface says about itself is
sans.

### Scoped stylesheets

`assets/css/flipbook.css` loads only on the flip reader. Keep it that way: the
reader is the one screen with a relaxed Content-Security-Policy, and confining
both its CSS and its policy to that route keeps the rest of the site strict.

## Third-party components

Markdown rendering uses Parsedown 1.8.0 by Emanuil Rusev, distributed under
the MIT licence. The library lives in `lib/` with its licence text. It runs in
safe mode, so raw HTML inside a Markdown file is escaped rather than executed.

The PDF flip-view reader uses Mozilla's pdf.js 5.4.149, distributed under the
Apache 2.0 licence, vendored in `lib/pdfjs/` with its licence text and the
licences of its bundled WebAssembly decoders. It is served from your own
domain: nothing is fetched from a CDN and no document is ever sent to a
third-party viewer. It loads only when someone opens the flip reader, so
visitors who never use it never download it.

Two deliberate limitations are worth knowing:

* The character maps and standard font files that pdf.js can optionally use
  are **not** bundled, to keep the package small. Almost every PDF embeds its
  own fonts and is unaffected. A PDF that relies on external CJK character
  maps may show substituted glyphs in the flip reader; the Download button
  always gives the untouched original.
* The reader shows one page at a time rather than a two-page spread.

## Licence

Folio is free software under the **GNU General Public License, version 3 or
later**. You may use, study, modify, and redistribute it; derivative works
must carry the same licence. The full text is in `license.txt`.

    Copyright (C) 2026 Mohd Elfie Nieshaem Juferi
    SPDX-License-Identifier: GPL-3.0-or-later

Bundled components keep their own, GPL-3-compatible licences: Parsedown (MIT),
Mozilla pdf.js (Apache-2.0), and the OpenJPEG and QCMS WebAssembly decoders.

Apache-2.0 is compatible with GPL version 3 but **not** with version 2, which
is why Folio is version 3 or later rather than version 2.

## Version

1.13.1. Single-file application with separated CSS and JS assets.
