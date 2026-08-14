# Folio — single source of truth

The canonical reference for what Folio is made of. Where any other document
disagrees with this one, this one is correct and the other is a bug.

Version 1.25.0. Update this file in the same commit as any change it describes.

## Project

| | |
| --- | --- |
| Name | Folio |
| Author | MENJ, <https://menj.blog> |
| Repository | <https://github.com/menj/folio> |
| Licence | GNU General Public License v3.0 or later |

`FOLIO_AUTHOR`, `FOLIO_AUTHOR_URI` and `FOLIO_REPO_URI` in `index.php` hold
these values, so nothing else needs to repeat them.

## Version

`FOLIO_VERSION` in `index.php` is authoritative. Five other places repeat it
and must be updated in the same commit. They are listed in full, with the
exact string to look for, because a missed one is invisible until someone
reports the wrong version — `readme.md` sat at 1.0.1 through several releases
for precisely this reason.

| Location | Exact string |
| --- | --- |
| `index.php` | `define('FOLIO_VERSION', '1.25.0');` |
| `changelog.md` | `## 1.25.0 — 13 August 2026` |
| `readme.txt` | `Stable tag: 1.25.0` |
| `readme.md` | `1.25.0.` under `## Version` |
| `security.md` | `The current supported release is **1.25.0**.` |
| `docs/ssot.md` | this section |

To check them all at once from the release root:

```sh
V=$(sed -n "s/.*FOLIO_VERSION', '\([^']*\)'.*/\1/p" index.php)
for f in changelog.md readme.txt readme.md security.md docs/ssot.md; do
    grep -qF "$V" "$f" || echo "STALE: $f"
done
```

Silence means every location matches.

Semantic versioning. Major for breaking changes, minor for features, patch for
fixes. A change to a shipped default counts as minor, not patch.

The running version is shown to a logged-in admin only: the public footer
carries a *Powered by Folio* colophon linking `FOLIO_REPO_URI`, and appends
`v<FOLIO_VERSION>` to it exclusively when `is_admin()`. An anonymous visitor
is never told which version is deployed. The footer's identity line is the
site name and year only; `PUBLISHER_NAME` / `PUBLISHER_URL` are not shown
there (they would duplicate the title on a single-owner archive) but continue
to populate the schema.org publisher node.

## File inventory

Files owned by the release. An upgrade overwrites all of them.

```
index.php                 application, all server logic
install.php               first-run installer, delete after use
config-sample.php         settings template
.gitignore                excludes config.php, data/*, uploads/*
.htaccess                 Apache rules, active as shipped
robots.txt                to be copied to the domain root
readme.md                 technical documentation
changelog.md              version history
security.md               vulnerability disclosure policy
license.txt               GNU GPL v3 + bundled-component notices
readme.txt                general-audience documentation
docs/ssot.md              this file
docs/install.md           installation guide
docs/upgrading.md         upgrade, migration, removal
docs/.htaccess            denies web access to docs/
assets/css/style.css      stylesheet, themes, all layout
assets/css/*.min.css      minified twins, built by tools/minify.js
assets/manifest.json      records which source each minified twin was built from
assets/js/*.min.js        minified twins, built by tools/minify.js
tools/minify.js           builds the minified twins; maintainers only
assets/css/flipbook.css   flip reader only
assets/js/app.js          listing behaviour
assets/js/view.js         detail page behaviour
assets/js/media.js        themed audio and video transport
assets/js/admin.js        admin screens
assets/js/flipbook.js     flip reader
assets/img/               favicon.svg, favicon.ico, apple-touch-icon.png
lib/parsedown/            Parsedown 1.8.0, MIT
lib/pdfjs/                PDF.js, Apache 2.0

tests/smoke.sh            regression suite
tests/readme.md           how to run it
tests/asset-version-check.php   asserts every asset is linked with ?v=
tests/wired-check.php     asserts every advertised utility is actually called
uploads/.htaccess         hardening for the served uploads folder
uploads/readme.txt        keeps the folder present in git and on GitHub
data/readme.txt           keeps the folder present in git and on GitHub
data/thumbs/              generated image derivatives; safe to delete
data/compressed/          prepared smaller copies of PDFs; safe to delete
data/.htaccess            denies web access to data/
```

Filenames are lowercase throughout, with no exceptions. Every `lib/`
subfolder carries the library itself, a `license.txt`, and a `VERSION` file
that Diagnostics reads. Licence files are named `license.txt` everywhere;
where a folder contains several, they are `license-<component>.txt`.

Renaming a vendored licence file is permitted: MIT and Apache 2.0 require the
licence text to be distributed with the work, not that it carry a particular
filename. The text itself is never altered.

Files owned by the installation. **Never** shipped, never overwritten:

```
config.php                credentials, secrets, settings
data/users.php            accounts
data/settings.php         settings saved from the admin
data/metadata.json        titles, descriptions, categories, tags, document_type,
                          transcript, pdf_access, language, placeholder_image
data/metadata.lock        write lock
data/folder-descriptions.json  folder descriptions, keyed by folder path
data/pages.json           standalone page content
data/aspect.json          cached PDF page shapes; safe to delete
data/previews/            generated, cached blurred previews for hidden PDFs
uploads/                  documents
uploads/.folio-pdf-probe.pdf  generated dummy file for the PDF-routing preflight
uploads/.sfm-meta.json    legacy metadata, read once for migration
```

## Requirements

- PHP 8.4 or newer, with JSON, password, random, and mbstring
- Apache or LiteSpeed
- Write access to `data/`; read access to `uploads/`
- No database

## Settings

All settings are constants. Precedence, highest first:

1. `data/settings.php` — written by the admin screens
2. `config.php` — hand-edited or written by the installer
3. defaults in `index.php`

Constant names loaded from `data/settings.php` must match `/^[A-Z][A-Z0-9_]*$/`.
Names containing digits are valid; `GA4_MEASUREMENT_ID` depends on this.

### Identity

| Constant | Default | Editable in admin |
| --- | --- | --- |
| `SITE_NAME` | `Folio` | Settings |
| `SITE_DESCRIPTION` | generic sentence | Settings |
| `SITE_LANGUAGE` | `en` | Settings |
| `PUBLISHER_TYPE` | `Person` | Settings |
| `PUBLISHER_NAME` | empty | Settings |
| `PUBLISHER_URL` | empty | Settings |
| `SHOW_ADMIN_LINK` | `true` | Settings |
| `AUDIO_PLAYLIST` | `true` | Settings |

### Addressing

| Constant | Default | Editable in admin |
| --- | --- | --- |
| `SITE_URL` | derived from request | no |
| `PRETTY_URLS` | auto-detected | Crawlers |
| `UPLOADS_DIRNAME` | `uploads` | no |
| `TRUST_PROXY_HEADERS` | `false` | no |
| `EXCLUDE_PATTERNS` | empty | no |

`PRETTY_URLS` is detected from `FOLIO_REWRITE`, which the shipped `.htaccess`
sets from inside its `<IfModule mod_rewrite.c>` block. Defining the constant
in `config.php` overrides detection in both directions.

`SITE_URL` unset means the address is derived from the request, accepting only
a host matching `/^[A-Za-z0-9._-]+(:[0-9]{1,5})?$/`.

### Discovery

| Constant | Default | Editable in admin |
| --- | --- | --- |
| `SITE_INDEXABLE` | `true` | Crawlers |
| `SITEMAP_ENABLED` | `true` | Crawlers |
| `LLMS_ENABLED` | `true` | Crawlers |
| `LLMS_INTRO` | empty | Crawlers |
| `INDEXNOW_KEY` | empty | Crawlers |

### Analytics

| Constant | Default | Editable in admin |
| --- | --- | --- |
| `MATOMO_URL` | empty | Analytics |
| `MATOMO_SITE_ID` | empty | Analytics |
| `MATOMO_HONOR_DNT` | `true` | Analytics |
| `MATOMO_COOKIELESS` | `false` | Analytics |
| `GA4_MEASUREMENT_ID` | empty | Analytics |
| `GA4_ANONYMIZE_IP` | `true` | Analytics |
| `ANALYTICS_ADMIN` | `false` | Analytics |

Folio stores no visit data itself. With both providers unset, the
Content-Security-Policy is identical to a build without the feature.

### Security

| Constant | Default | Editable in admin |
| --- | --- | --- |
| `ADMIN_USERNAME` | `admin` | Accounts |
| `ADMIN_PASSWORD_HASH` | `CHANGE_ME` | Accounts |
| `FOLIO_AUTH_PEPPER` | empty | no |
| `FOLIO_COOKIE_NAME` | `FOLIOSESSID` | no |
| `FOLIO_URL_SIGNING_KEY` | empty | no — signs "viewer" pdf_access URLs, deliberately separate from `FOLIO_AUTH_PEPPER` |
| `PDF_GATE_CONFIRMED` | `false` | Crawlers, via the PDF-routing preflight — never set by hand |

`ADMIN_PASSWORD_HASH` left at `CHANGE_ME` disables login rather than accepting
anything. **`FOLIO_AUTH_PEPPER` must never change once accounts exist**: it is
mixed into every stored hash, and changing it locks every account out.

## Endpoints

Public:

| Path | Purpose |
| --- | --- |
| `/` or `?dir=` | folder listing |
| `?view=` | document detail page |
| `?cat=` | category archive |
| `?page=` | standalone page, any number of them |
| `?action=render` | Markdown to HTML, `.md` only |
| `?action=raw` | streams a file's bytes (`serve=1`) or 301s to the direct file URL; the sole enforcement point for `pdf_access` on PDFs — see § PDF access control |
| `?action=pdf_preview` | blurred first-page JPEG for a `hidden` PDF, generated on demand and cached; never the original file |
| `?action=flipbook` | flip reader, PDF only; refuses `hidden` PDFs outright |
| `?action=sitemap_pdf` | the document files themselves |
| `?action=sitemap` | XML sitemap, or index beyond 50,000 URLs |
| `?action=llms` | llms.txt for AI crawlers |
| `?indexnow_key=` | IndexNow ownership file |
| `?action=rewrite_probe` | JSON, reports whether rewriting reached PHP |

Admin, all requiring a session:

| Path | Purpose |
| --- | --- |
| `?action=login` / `logout` | authentication |
| `?action=settings` | identity |
| `?action=crawlers` | sitemap, llms.txt, indexability, IndexNow, clean URLs |
| `?action=analytics` | Matomo and GA4 |
| `?action=users` | accounts |
| `?action=pages` | standalone pages |
| `?action=docs` | documentation viewer |
| `?action=diagnostics` | environment report |
| `?action=catalogue` | admin: reconnect records to files |
| `?action=compress` | POST, admin: prepare a smaller copy of a PDF |
| `?action=compressed` | admin: download that copy |
| `?action=thumb` | cached image derivative; only the offered widths |
| `?action=ocr` | POST, admin: make one scanned PDF searchable |
| `?action=reconcile` | POST, admin: match records to renamed or moved files |
| `?action=relink` | POST, admin: attach one record to one file by hand |
| `?action=meta` | POST, admin: save a document's metadata and slug |
| `?action=logout` | POST, admin: end the session |

Under clean URLs these become `/slug/`, `/category/slug/`, `/sitemap.xml`,
`/llms.txt`, and `/{key}.txt`. Admin paths keep their query-string form.

## Derivative images

Folio generates cached WebP copies of images so a listing does not send
full-size originals, and so formats browsers cannot display still have a
preview.

| Setting | Default | Meaning |
| --- | --- | --- |
| `THUMBNAILS_ENABLED` | `true` | Master switch |
| `THUMB_WIDTHS` | `[320, 640, 1280]` | The only widths that will be produced |
| `THUMB_QUALITY` | `82` | WebP quality |
| `IMAGE_MAX_PIXELS` | `80000000` | Decode ceiling |
| `IMAGE_MEMORY_LIMIT` | `256` | Megabytes per conversion |
| `IMAGE_TIME_LIMIT` | `20` | Seconds per conversion |
| `PDF_SERVER_PREVIEW` | `false` | Rasterise PDF page one |
| `CONVERT_FORMATS` | TIFF, HEIC, HEIF, AVIF | Formats needing conversion |

`image_engine()` returns `imagick`, `gd`, or `none`. Imagick is preferred
because it reads TIFF, HEIC, and PDF; GD covers the common web formats and is
almost always present. With neither, `image_can_derive()` is false everywhere,
`url_thumb()` returns the original URL, and the feature is invisible.

Cache keys hash the relative path, modification time, size, width, and
quality, so replacing a file over FTP invalidates its derivatives without
anything having to detect the change. `data/thumbs/` is disposable: deleting it
frees space and costs one regeneration.

Three limits matter and are deliberate. Dimensions are read with `pingImage()`
before any pixels are decoded, so a small file declaring enormous dimensions is
rejected at no cost. Only the widths in `THUMB_WIDTHS` are honoured, so the
cache cannot be filled on demand. Derivatives are stripped of metadata, because
a public thumbnail should not republish EXIF GPS coordinates.

`PDF_SERVER_PREVIEW` is off by default. Rasterising PDFs means invoking
ImageMagick's PDF delegate, which has a poor security record, and the
client-side reader already previews PDFs without it.

## External utilities

Folio detects command-line utilities and uses them where they help. Every one
is optional. This table is the contract: it says what is lost when a tool is
absent, and nothing in it may become a hard requirement.

| Utility | Used for | Without it |
| --- | --- | --- |
| `ocrmypdf` | OCR, preferred route | Falls back to the Tesseract route |
| `tesseract` | The OCR engine | No OCR; everything else unaffected |
| `pdftotext` | Text extraction, indexing | No extracted text; no text search over PDFs |
| `pdfinfo` | Page counts, encryption check | Page counts unknown; Tesseract OCR route unavailable |
| `pdftocairo` | PDF page rendering | Falls back to `pdftoppm` |
| `pdftoppm` | PDF page rendering | With neither, no PDF previews |
| `qpdf` | Joining OCR'd pages | Single-page documents still OCR; multi-page reports why not |
| `pngquant` | Shrinking rendered PDF pages | Renders are simply larger |
| `exiftool` | Reading a document's own creation date | The filesystem date is used |
| `unpaper` | Deskewing before OCR | OCR runs without cleanup |

### Where utilities are looked for

In order: the directories in `TOOL_SEARCH_PATHS`, then account-local
locations derived from Folio's own position and the process owner. `$PATH` is
never consulted.

Account-local matters on shared hosting: a cPanel user cannot write to
`/usr/bin`, so tools installed for one account land in a virtual environment
or `~/.local`. Those directories belong to the same account that owns
`index.php`, so searching them grants no privilege Folio did not already have.

```
~/.local/bin
~/bin
~/ocrmypdf-venv/bin
~/venv/bin, ~/.venv/bin
~/virtualenv/<app>/<version>/bin     cPanel "Setup Python App"
~/*-venv/bin, ~/*-env/bin, ~/*_venv/bin
```

`TOOL_PATHS` overrides everything for a named binary.

The account home is derived two ways and both are tried: `posix_getpwuid()`
on the effective user, and walking up from `__DIR__` to a `/home/<user>`
shaped parent. They are not always the same, and relying on one silently
fails when it is the wrong one.

### OCR routes

`ocr_method()` returns whichever is possible, preferring the first:

1. **`ocrmypdf`** — OCRmyPDF drives the job. Best results: it keeps existing
   text layers, deskews, and optimises. `--output-type pdf` is passed so it
   never attempts PDF/A conversion.
2. **`tesseract`** — Poppler renders each page, Tesseract writes a searchable
   single-page PDF, qpdf joins them. No Python needed. `qpdf` is
   needed only for multi-page documents.
3. **none** — OCR is unavailable and says so.

### PDF rendering

PDF pages are rendered with Poppler. ImageMagick's own PDF support is reached
only when `PDF_ALLOW_GHOSTSCRIPT` is true, which it is not by default. With
both unavailable, previews are not generated and the original is served.


## Document identity and URLs

A file path is not an identity. Renaming a file over FTP, or moving it to
another folder, changes the path but not the document — its title, transcript,
and above all its public address have to survive both.

### Record shape

```
{
  "version": 2,
  "documents": {
    "doc_8f4a73c2...": {
      "document_id": "doc_8f4a73c2...",
      "file_path":   "certificates/award_1997.pdf",
      "slug":        "pertandingan-scrabble-1997",
      "aliases":     ["award-1997"],
      "fingerprint": "<sha256>",
      "file_size":   123456,
      "file_mtime":  1785770400,
      "title": "...", "transcript": "...", ...
    }
  }
}
```

`document_id` is random, never derived from the path, title, or slug, and
never changes. `file_path` is mutable. Descriptive fields are unchanged from
earlier releases.

### Two shapes, one bridge

The metadata file may still be the legacy path-keyed map. Rather than a
flag-day switch, both shapes are served:

- `meta_load()` always returns the **path-keyed view**, whatever is on disk.
  Every existing reader looks up metadata by relative path, and a migrated
  store must not silently stop answering them. This is not cosmetic: during
  development, a version that skipped this made `pdf_access` lookups return
  nothing and **a hidden PDF became publicly reachable**.
- `meta_documents()` returns the **identity-keyed store**, migrating in memory
  when needed.
- `meta_put_record()` writes through whichever shape is on disk, and migrates
  a legacy store on the way. Writing a bare path key into a migrated store
  would corrupt it, so no code assigns into the metadata array directly.

Both caches are dropped after every write. Assigning the written array
straight into the cache would hand readers the wrong shape.

### Migration

`meta_migrate()` is pure — it takes the old array and returns the new one,
touching no files — so the result can be validated before anything is written.
It is idempotent: an already-migrated array is returned untouched, so it can
never mint new identifiers or duplicate aliases.

It preserves the URL each document already answered on. A migration that
silently changed every address would undo years of indexing and inbound links.
Where an existing address cannot be kept, resolution is deterministic and a
warning is recorded rather than the collision being resolved silently.

### Slugs and aliases

Slugs are a flat namespace: a slug never contains a separator, and folders do
not appear in document URLs. Folder browsing is unaffected — it just does not
determine a document's permanent address.

Aliases are flattened, never chained. Each alias names the record, and the
record names its current slug, so A → B → C sends both A and B directly to C.
A canonical slug always beats a stale alias in the index, or a live page could
redirect away from itself; this is enforced twice, independently.

### Reconciliation

Hashing is deliberate work and never happens during ordinary browsing: a
library of large scans would otherwise read every byte on disk for every page
view. A fingerprint is written when a record is saved, and read when a saved
path has disappeared or an administrator asks.

A match requires exactly one file with the record's contents **and** exactly
one record wanting that file. Anything else is reported for manual relinking.
Attaching a document's history to the wrong file is far worse than asking
someone to choose.

## Licensing

Folio is **GPL-3.0-or-later**. Copyright (C) 2026 Mohd Elfie Nieshaem Juferi.

Version 3 rather than version 2 is a constraint, not a preference: the
bundled Mozilla pdf.js is Apache-2.0, which is compatible with GPL version 3
and **incompatible with version 2**. Relicensing Folio to GPL-2.0 would make
the release undistributable without removing pdf.js first.

| Component | Licence | Notice |
| --- | --- | --- |
| Folio | GPL-3.0-or-later | `license.txt` |
| Parsedown 1.8.0 | MIT | `lib/parsedown/license.txt` |
| Mozilla pdf.js 5.4.149 | Apache-2.0 | `lib/pdfjs/license.txt` |
| OpenJPEG, QCMS (WASM) | own permissive | `lib/pdfjs/wasm/` |

Every source file carries an `SPDX-License-Identifier: GPL-3.0-or-later`
line, so the licence is discoverable from any single file rather than only
from the release as a whole.

Before adding a dependency, check it against GPL-3. Anything GPL-2-only,
proprietary, or non-commercial cannot be bundled.

## Invariants

Rules that must hold. A change breaking any of these is a defect.

1. Folio writes nothing outside its own folder.
2. `uploads/` is read-only to the application, with one narrow, deliberate
   exception: `pdf_gate_ensure_probe_file()` writes a single hidden dotfile,
   `uploads/.folio-pdf-probe.pdf`, so the PDF-routing preflight can test a
   real file rather than a nonexistent path — a nonexistent path can give a
   false positive through a pre-existing, unrelated fallback (see § PDF
   access control). Every other document arrives over FTP; Folio never
   writes, moves, or deletes anything else there.
3. Anonymous requests create no session and no cookie, so pages stay cacheable.
4. Every state-changing request carries a CSRF token. The header login form
   uses a stateless signed token so it does not start a session.
5. Path resolution is pinned inside `BASE_DIR`, rejecting symlinks and any
   component that escapes.
6. No `data/*.php` file is ever served: each returns an array and executes to
   nothing, and `data/.htaccess` denies access.
7. Excluded files are absent from listings, sitemaps, categories, IndexNow, and
   detail pages, and return a genuine 404.
8. Both URL modes are fully indexable. Neither is a degraded experience.
9. Upgrades never touch installation-owned files.
10. An exclusion pattern hides the folder it describes and everything beneath
    it, on every surface: listing, breadcrumbs, structured data, sitemap,
    categories, IndexNow, and all delivery routes.
11. No page uses `'unsafe-inline'`. Folio's own scripts are external files;
    the analytics bootstraps are inline by necessity and are admitted
    individually by `sha256` hash. The hashed string and the emitted string
    both come from `analytics_inline_blocks()`, so they cannot drift apart.
    A CSP origin built from a configured URL keeps its port.
12. Derivatives are written only under `data/`, never into `uploads/`. A
    missing image engine, an unreadable format, or a failed conversion falls
    back to serving the original; it never errors or shows a broken image.
13. A restricted PDF has no thumbnail. Access gating and derivative generation
    meet at the thumbnail route, which carries no signature, so it must not
    become a second unguarded door to gated content.
14. External programs are started only through `proc_open()` with an argument
    array and `bypass_shell`. No shell-invoking construct appears anywhere in
    executable code, so a filename can never be interpreted as a command.
15. A missing utility is never an error. Every feature that uses one checks
    first and falls back to the behaviour Folio had without it.
16. Ghostscript is never invoked unless `PDF_ALLOW_GHOSTSCRIPT` is explicitly
    true. It defaults to false and Folio's features do not depend on it.
17. Every external utility is optional, and the fallback for each is recorded
    in the External utilities table. A tool becoming mandatory is a defect.
18. A document's canonical URL is its saved slug alone. It never depends on
    the physical filename or the folder the file sits in.
19. Folio performs no physical file operation. It never uploads, renames,
    moves, replaces, or deletes a file, and never creates or removes a folder.
    FTP owns the files; Folio owns the catalogue.
20. An ambiguous reconciliation is never resolved automatically.
21. Every bundled dependency must be licence-compatible with GPL-3. A
    GPL-2-only, proprietary, or non-commercial component cannot ship.
22. Release-owned assets are linked with the version in the URL. They are
    cached for a year without revalidation, so an unversioned link means an
    upgraded site is rendered against the previous stylesheet.
23. Canonical tags, `og:url`, structured-data URLs and `@id`s, sitemap `<loc>`
    values, and the IndexNow payload are always absolute. In-page navigation
    inside a listing — the row title, folder, category, and flip-view links —
    may be root-relative to save page weight, produced by `root_relative()` at
    the point of emission only. The shared field these links derive from
    (`$f['view']` / `url_view()`) stays absolute at source, because the
    canonical, structured-data, and sitemap surfaces read the same field. A
    link surface that must be absolute becoming root-relative is a defect.

## Documentation set

These eight must always exist. Each has a distinct audience; none is a
restatement of another. If two files would say the same thing, one of them is
wrong.

| File | Audience and scope |
| --- | --- |
| `readme.txt` | general: what it is, how to use it, plain text, FAQ |
| `readme.md` | operators and developers: features, architecture, hosting |
| `docs/install.md` | first-time installation only |
| `docs/upgrading.md` | upgrading, migrating, the **roadmap**, removing |
| `changelog.md` | version history; what observably changed |
| `security.md` | threat model, controls, deployment, reporting |
| `tests/readme.md` | how to run the suite, what it covers and does not |
| `docs/ssot.md` | this reference: architecture, schemas, invariants |

`docs/upgrading.md` carries the roadmap because the two questions are the
same one asked at different times: what will change if I upgrade, and what is
going to change later.

`readme.md` and `changelog.md` stay at the root because GitHub renders the
first on the repository landing page and release tooling expects the second
there. `readme.txt` stays at the root as the plain-text entry point.

`docs/` is deliberately outside `assets/`. `assets/` is served to browsers;
`docs/` is denied by its own `.htaccess` and read through the admin viewer.

## PDF access control

Per-file `pdf_access` (`public` / `viewer` / `hidden`) restricts a PDF's raw
bytes without ever affecting the record page's own indexability — see
"Indexability is unaffected" below, which is a hard invariant, not a goal.

### Metadata fields

On top of the existing `title`, `desc`, `category`, `tags`:

| Field | Values | Notes |
| --- | --- | --- |
| `pdf_access` | `public` (default) \| `viewer` \| `hidden` | validated server-side, unknown values fall back to `public` |
| `document_type` | controlled list (certificate, letter, card, article, magazine, tract, report, transcript, form, identity, academic, award, booklet, other) | distinct from the existing free-form `category`; also feeds a conservative Schema.org type override |
| `transcript` | plain text, ~100,000 char cap | rendered server-side in the detail page HTML, never JS-injected — this is what keeps the content crawlable and AI-readable when the PDF itself is restricted |
| `language` | e.g. `en`, `ms`, `ar` | optional, maps to `dcterms:language` / `inLanguage` |
| `placeholder_image` | relative path to an existing image already in `uploads/` | manual fallback preview for `hidden` PDFs when Imagick/Ghostscript isn't available; validated to resolve to a real image file |

### `?action=raw` is the sole enforcement point

`?action=raw` (see Endpoints above) gains two optional query params,
`expires` and `token`, checked only when the requested file's `pdf_access`
is `viewer`. Signature is
`hash_hmac('sha256', $rel . '|' . $expires, FOLIO_URL_SIGNING_KEY)`,
compared with `hash_equals`. Missing, expired, or invalid → 404. Files with
`pdf_access` of `hidden` are refused unconditionally, regardless of any
params presented. The detail-page preview, the flip-view reader (which
refuses `hidden` PDFs outright), the "Direct link" button, the flip-view
download link, and the print iframe all route through this one check
(`url_raw_effective()` / `pdf_full_access()`) rather than each implementing
their own. `public` behaviour is unchanged.

### `PDF_GATE_CONFIRMED`: a preflight, not an assumption

`pdf_access` restriction is only real if PDF requests actually reach PHP.
The Crawlers screen has a preflight that requests a real, on-disk dummy
file (`uploads/.folio-pdf-probe.pdf`, dotfile, already excluded from every
listing and the sitemap the same way `.htaccess` and `.DS_Store` are) and
confirms the response came from `?action=raw`'s admin-only probe branch,
not a static file served directly by the webserver. A **real** file matters
here, not a made-up path: index.php already has a fallback that maps any
*nonexistent* `uploads/...` path to `?action=raw` when the generic
not-a-real-file rewrite fires and `PRETTY_URLS` is on — a nonexistent probe
path would give a false positive through that fallback even when the
PDF-specific rewrite rule is completely absent. Confirming sets
`PDF_GATE_CONFIRMED` (self-verified server-side via an outbound request the
same way IndexNow submission already makes one, with a client-probe
fallback for hosts that block outbound HTTP). Until confirmed — on any
unsupported or misconfigured server — every PDF behaves as `public`
regardless of its stored setting, with a visible warning on Diagnostics and
inline in each restricted file's editor, rather than presenting a false
sense of restriction.

**This feature requires Apache or LiteSpeed**, the only servers Folio
supports at all (see Requirements): only the Apache/LiteSpeed path
(`.htaccess`) gets the PDF-routing rewrite rule (hardcoded to the default
`uploads` folder name, since `.htaccess` cannot read `UPLOADS_DIRNAME` from
`config.php` — rename the uploads folder and this line needs a matching
manual edit). Because Folio has no other supported server target, there is
no separate "unsupported server" case to design around here beyond the
preflight already covering it: any server that can't confirm the rewrite
simply falls back to `public` for every file, with the admin warning
explaining why, rather than silently providing no real protection.

### Indexability is unaffected — a hard invariant, not a goal

Regardless of `pdf_access`, for every mode:

- The record page's `robots` meta tag is driven only by the existing
  `SITE_INDEXABLE`, never by `pdf_access`.
- The sitemap's `<loc>` for a file is always the record page URL, never the
  raw file — true before this feature, and nothing about it changes that.
- `X-Robots-Tag: noindex` is added only to the raw PDF response itself (for
  `viewer`), never to the record page response.
- The transcript, when present, is server-rendered into the record page's
  initial HTML for every `pdf_access` value, including `hidden`.
- `llms.txt` notes that a full transcription is available for restricted
  files, so AI crawlers don't spend a request on a PDF they can't reach.

In other words: the PDF binary can be restricted, but the record page
describing it — title, description, transcript, Dublin Core and Schema.org
metadata — stays exactly as crawlable and indexable as any other file,
public or not.

### Blurred previews for `hidden`

Where the server can render PDF pages
(`pdf_blur_available()`), page one is rasterized server-side at low
resolution, downscaled hard, blurred, then scaled back up
(`pdf_blur_generate()`) — deliberately more destructive than a blur filter
over a full-resolution render, which can be partially reversed with a
sharpening/deconvolution pass. The result is cached in `data/previews/` and
served through `?action=pdf_preview`, never the original file. Where
PDF rendering isn't available, or where set regardless, the
`placeholder_image` field takes priority: an existing image already in
`uploads/` the admin points at directly. Availability is detected and
reported on the Diagnostics screen the same way pdf.js availability already
is.

### Dublin Core Terms

Schema.org JSON-LD's `@context` gains the `dcterms` namespace alongside
`@vocab`, and file nodes gain `dcterms:title`, `dcterms:identifier`,
`dcterms:format`, `dcterms:modified`, and conditionally
`dcterms:description`, `dcterms:subject`, `dcterms:type`,
`dcterms:language`. `dcterms:format`/`encodingFormat` use
`detected_mime_type()` (`finfo`, content-sniffed) rather than the
extension-based `$mime_map` — deliberately for metadata only, so a
mislabeled extension can't change what Folio decides to serve inline versus
as a download; routing and previews keep using `$mime_map`. `contentUrl`,
`associatedMedia`, and `potentialAction`/`DownloadAction` are omitted
entirely for any PDF whose `pdf_access` is not `public` and is enforced — a
temporary signed URL, or no URL at all, must never be published as
permanent metadata.

## Testing

`tests/smoke.sh` provisions a temporary installation and asserts twenty-nine
behaviours, covering caching, host validation, symlink containment, slug
collisions, file delivery, metadata writes, JSON-LD escaping, session
revocation, CSRF on logout, installer headers, sitemap generation, and PDF
access control (hidden/viewer enforcement, signed-URL validation, and
indexability being unaffected). `PDF_GATE_CONFIRMED` is pre-seeded directly
in the test's `data/settings.php` — the interactive preflight itself depends
on real Apache rewrite behaviour that `php -S` (used by this harness) has no
equivalent for, so it isn't exercised end-to-end here.

It must pass before any release. A security fix should arrive with a test that
fails against the unfixed code.
