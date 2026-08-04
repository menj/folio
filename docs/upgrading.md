# Upgrading and removing Folio

## Never touch these

An upgrade replaces program code only. These belong to you and are never part
of a release:

```
config.php              your credentials, secrets, and settings
data/                   accounts, saved settings, metadata, page content
uploads/                your documents
uploads/.sfm-meta.json  every title, description, category, and tag
```

Nothing in the instructions below writes to any of them. If a step ever tells
you to delete `data/` or `uploads/`, stop: that is wrong.

## Before upgrading

Back up these installation-specific items:

- `config.php`
- `data/`, including accounts, settings, `metadata.json`, and its backup
- `uploads/`
- the installed root `.htaccess`

A folder that previously held metadata in `uploads/.sfm-meta.json` is read
once for migration; Folio writes all future changes to `data/metadata.json`.
Keep the legacy file until the migrated metadata has been verified.

## Upgrading to 1.6.0

Document URLs become permanent in this release: they no longer follow the
filename or folder. Upload the changed files as usual.

### Step 1 — Nothing to do before upgrading

Your existing metadata is migrated automatically the first time Folio writes
to it. Every field is preserved, and **each document keeps the URL it already
answered on** — the migration exists precisely so that upgrading does not
change a single public address. The pre-migration file is kept as a dated
`.pre-1.6.*.bak` alongside the usual backup.

Older path-derived URLs keep working afterwards and redirect to the document's
canonical address, so inbound links and search results survive.

### Step 2 — Check the catalogue

Open `index.php?action=diagnostics`. The new **catalogue** row reports how
many documents are matched to a file, any whose file is missing, and any files
not yet catalogued.

### Step 3 — Optional: choose better URLs

Each document now has a **URL slug** field in its Edit panel. Changing it
leaves a permanent 301 redirect from the old address. Previous addresses
always redirect straight to the newest one, never through a chain.

### After an FTP rename or move

This is the point of the release. Rename or move files freely over FTP, then
run reconciliation from Diagnostics: Folio matches each record to its file by
content and updates only the stored path. URLs and metadata are untouched.

Content matching needs the bytes to be unchanged. A file that was renamed
**and** edited cannot be matched, and Folio will not guess — it reports the
document and the file separately so you can relink them by hand.

### What has not changed

Folio still performs no physical file operation. It does not upload, rename,
move, replace, or delete anything, and creates no folders. FTP remains the
only way files are managed.

## Upgrading to 1.5.0

Upload the changed files. No data format changed and no setting is required:
if your server has none of the utilities Folio can now use, it behaves exactly
as 1.4.2 did.

### Step 1 — See what your server already has

Open `index.php?action=diagnostics` after upgrading. The **external
utilities** row lists every tool found and its absolute path, and the **OCR**
row says whether OCR is usable and in which languages. Nothing needs
installing for Folio to work; this only tells you what is available.

### Step 2 — Only if you want OCR

OCR needs `ocrmypdf` and `tesseract`, plus a language dataset for each
language you want to read. Malay is `msa` and Arabic is `ara`; these are
separate packages from Tesseract itself and are frequently absent even when
Tesseract is installed. Diagnostics names any that are missing.

A tool installed for your account rather than server-wide is found
automatically: Folio searches `~/.local/bin`, `~/bin`, and any virtual
environment such as `~/ocrmypdf-venv/bin`, which is where a cPanel user is
normally told to install OCRmyPDF. Nothing needs configuring for that.

If a tool lives somewhere else entirely, point at it directly:

```php
define('TOOL_PATHS', ['ocrmypdf' => '/home/youruser/.local/bin/ocrmypdf']);
```

To keep everything off regardless of what is installed:

```php
define('TOOLS_ENABLED', false);
```

### Step 3 — Nothing to undo

Two new cache directories appear under `data/` the first time they are used:
`data/text/` for extracted text and `data/ocr/` for searchable copies. Both
are disposable. Deleting them frees space and costs only the work of
regenerating. Your uploaded files are never modified.

## Upgrading to 1.4.2

Documentation only — no code behaviour changed. Upload the changed files if
you keep the docs alongside the install; otherwise nothing is required.

If Diagnostics ever told you to rename `htaccess.txt`, ignore that: the file
stopped shipping in 1.2.0 and `.htaccess` is active as delivered. If
`.htaccess` is genuinely missing on your server, the usual cause is an FTP
client hiding dotfiles rather than anything needing renaming.

## Upgrading to 1.4.1

Documentation, packaging, and a few comments/diagnostic text in `index.php`
— no functional behaviour changed. If you previously uploaded
`nginx.conf.example`, it's no longer part of the release and can be
deleted — nothing reads it. No other action is required.

## Upgrading to 1.4.0

Coming from 1.3.0, this release is additive: no data format changed and no new
setting is required. Upload the changed files over the existing installation;
nothing needs deleting.

**Coming from 1.2.0 or earlier?** Do the 1.3.0 steps further down first — this
section assumes they are done.

```
index.php
.htaccess
config-sample.php
assets/css/style.css
assets/js/app.js
assets/js/admin.js
readme.md
readme.txt
changelog.md
docs/ssot.md
docs/upgrading.md
security.md
tests/smoke.sh
```

### Step 1 — Copy the restored hardening file

One file missing from the 1.2.0 package is back: `uploads/.htaccess`. Copy it
into your `uploads/` folder. It stops anything in that folder executing,
forces active formats to download, denies the legacy `.sfm-meta.json`, and
disables symlink following at the server. Folio enforces the same delivery
rules in PHP, but running with both is the intent.

### Step 2 — Nothing to do for image thumbnails

Thumbnails switch on by themselves if your host has the Imagick or GD
extension, and nothing changes if it has neither. Check
`index.php?action=diagnostics` to see which engine was found. The first visit
to a page generates its derivatives, so an initial load can be slightly
slower; afterwards they are cached in `data/thumbs/`, which is disposable.

### After upgrading: behaviour changes to expect

If you exclude folders with `EXCLUDE_PATTERNS`, check them. A pattern such as
`_drafts/*` previously hid a folder's contents but left the folder itself
listed and linked. It now hides the folder as well, so a folder you thought
was already hidden may disappear from the listing. That is the corrected
behaviour.

If you have analytics configured, it starts working. The inline tracker
bootstrap was being blocked by the Content-Security-Policy, so Matomo and GA4
were loading their external script and then collecting nothing. A self-hosted
Matomo on a non-standard port also needed that port carried into the policy,
which it now is. No action is required.

If you were relying on `nginx.conf.example` or running Folio behind Nginx,
note that it's no longer shipped or supported. Query-string URLs and
public-only PDF behaviour still work there, since Folio's own preflight
checks fail safe on any server it can't confirm — but there is no
maintained Nginx configuration going forward.

## Upgrading to 1.3.0

Only needed if you are coming from 1.2.0 or earlier. These three steps set up
PDF access control; skip them if you are already on 1.3.0 or later.

### Step 1 — Add the new config constant

This release adds one new setting: `FOLIO_URL_SIGNING_KEY`, which signs
short-lived URLs for PDFs set to "viewer" access. It is optional — leaving
it unset simply means the PDF access control feature stays inert, every PDF
continuing to behave exactly as it did in 1.2.0 — but add it to
`config.php` if you plan to use "viewer" or "hidden" `pdf_access`:

```php
define('FOLIO_URL_SIGNING_KEY', '');
```

Generate a value the same way as `FOLIO_AUTH_PEPPER` (see `config-sample.php`
for the exact command), but **never reuse that value here** — the two
secrets protect different things and must be independent.

### Step 2 — Upload the new .htaccess

The updated `.htaccess` adds one rule routing PDF requests under `uploads/`
through Folio, which the new PDF access control feature depends on. This is
additive; it does not change how any existing file is served. **Apache and
LiteSpeed only** — Nginx is not a supported deployment target.

### Step 3 — Confirm the PDF access preflight (only if you plan to use it)

If you intend to set any PDF to "viewer" or "hidden," visit the Crawlers
screen and run the PDF routing preflight before relying on it. Until
confirmed, every PDF continues to behave as Public regardless of what's set
on it — Diagnostics and each file's editor say so.

## Upgrading to 1.2.0

This release renames files. Uploading the new ones is not enough on its own:
on a case-sensitive server, `README.md` and `readme.md` are two different
files, and leaving both means the old copy sits there forever going stale.
The delete step below is not optional.

### Step 1 — Upload the new release

Upload every file except `config.php`, `data/`, and `uploads/`. Overwriting
the whole folder minus those three is the simplest correct approach.

Files that changed or are new in this release:

```
index.php
.htaccess
nginx.conf.example
config-sample.php
assets/css/style.css
assets/js/app.js
assets/js/view.js
lib/parsedown/Parsedown.php
lib/parsedown/license.txt
lib/parsedown/VERSION
lib/pdfjs/license.txt
lib/pdfjs/wasm/license-openjpeg.txt
lib/pdfjs/wasm/license-pdfjs-openjpeg.txt
lib/pdfjs/wasm/license-pdfjs-qcms.txt
lib/pdfjs/wasm/license-qcms.txt
readme.md
readme.txt
changelog.md
security.md
license.txt
docs/install.md
docs/upgrading.md
docs/ssot.md
tests/readme.md
```

### Step 2 — Delete the old files

These fifteen were renamed or moved. Delete them from the server:

```
README.md
CHANGELOG.md
SECURITY.md
LICENSE.txt
docs/INSTALL.md
docs/UPGRADING.md
docs/readme.txt
tests/README.md
lib/Parsedown.php
lib/Parsedown-LICENSE.txt
lib/pdfjs/LICENSE
lib/pdfjs/wasm/LICENSE_OPENJPEG
lib/pdfjs/wasm/LICENSE_PDFJS_OPENJPEG
lib/pdfjs/wasm/LICENSE_PDFJS_QCMS
lib/pdfjs/wasm/LICENSE_QCMS
```

`lib/Parsedown.php` matters most: leaving it behind is harmless but confusing,
since the application now loads `lib/parsedown/Parsedown.php` and the stale
copy will never be updated again.

If your FTP client hides dot-files, turn that off first: `.htaccess` is in the
upload list and it changed in this release.

### Step 3 — Nothing to change in config.php

No settings were added, removed, or renamed. Your `config.php`, accounts,
settings, metadata, pages, and documents all carry over untouched.

### Step 4 — Verify

1. Hard-refresh with Ctrl+F5 so the new stylesheet and scripts load.
2. Log in and open **Diagnostics**. It should report version 1.2.0, and both
   library rows should read *Parsedown 1.8.0 present in lib/parsedown/* and
   *pdf.js present in lib/pdfjs/*.
3. Open a Markdown document. If it renders as raw text, `lib/parsedown/` did
   not upload correctly.
4. Open a PDF page and hover a PDF row in the listing; both should show a
   rendered page rather than an empty box.
5. Click a category chip in the listing: it should filter in place rather than
   navigating away.

### What changed and why it matters on upgrade

- **All filenames are lowercase**, which is why step 2 exists.
- **Every licence file is `license.txt`**, at the root and in each `lib/`
  subfolder.
- **Parsedown moved into `lib/parsedown/`** so both vendored libraries have the
  same shape.
- **The `.htaccess` and Nginx documentation-blocking rules are now
  case-insensitive.** Without the new `.htaccess`, the lowercase documentation
  files would be fetchable over HTTP. This is the reason `.htaccess` is in the
  upload list.

### Additionally, if you are coming from 1.0.1

Steps 1 and 2 above still apply in full. Three more things are needed, because
1.1.0 changed how the server configuration works.

Delete these template files, which stopped shipping in 1.1.0:

```
htaccess.txt
uploads-htaccess.txt
data-htaccess.txt
```

**Open `config.php` and delete this line if it is present:**

```php
define('PRETTY_URLS', false);
```

The 1.0.1 installer wrote that into every configuration. Left in place it pins
clean URLs off and overrides the automatic detection added in 1.1.0, so your
addresses stay in `?view=…` form even though the server supports better.
Deleting the line is all that is needed; Folio works out the rest.

`SITE_URL` can stay. It is no longer required, but pinning the canonical
address is still the better choice on a live site.

Confirm `install.php` uploaded as well: it changed in 1.1.0 and again in this
release. If you already deleted it after installing, leave it deleted.

### Rolling back

Keep a copy of the previous folder before you start, excluding `config.php`,
`data/`, and `uploads/`. Restoring it returns you to where you were.

No release in the 1.x line changes the format of anything under `data/` or
`uploads/`, so your documents, titles, categories, accounts, and settings are
never at risk from an upgrade or a rollback.

## Verifying any upgrade

Check the upgrade landed correctly:

1. Open `index.php?action=diagnostics` and confirm every row reports OK. It
   checks the PHP version, required extensions, directory permissions, URL
   mode, and whether the installer has been removed.
2. Load the public listing as an anonymous visitor and confirm documents,
   titles, and categories appear as before.
3. Open `index.php?action=sitemap` and confirm it is well-formed. On a library
   above 50,000 URLs, confirm it is a `<sitemapindex>` and that the first part
   loads.
4. Sign in, edit one document's title, and confirm the change is saved.
5. Sign out using the header link and confirm you are signed out.

If you maintain a copy of the source, run `bash tests/smoke.sh`. It exits
non-zero on any failure and covers path containment, file delivery, atomic
metadata storage, session revocation, structured-data encoding, logout
protection, installer headers, and sitemap behaviour.

## Roadmap

This section is the plan for where Folio goes next, and — just as usefully —
where it deliberately does not go. It exists so that an upgrade is never a
surprise: if something here would change how your library behaves, it is said
plainly before it ships.

Nothing below is a promise of a date. Items move when they are ready and
tested, not when a version number is due.

### Principles that will not change

These are the constraints every future item is judged against. If a feature
cannot be built without breaking one of them, the feature does not get built.

1. **Your files are never modified.** Folio reads the library and writes only
   its own catalogue and its own caches. It will never gain upload, rename,
   move, delete, or folder-management controls. FTP owns the files.
2. **No database.** Metadata stays in a flat file with atomic writes and a
   last-known-good backup.
3. **It stays one application, deployable by copying a folder.** No build
   step, no Composer requirement, no framework.
4. **Every added dependency is optional.** A missing utility or PHP extension
   costs a capability, never the site.
5. **Public URLs are permanent.** Once a document has an address, Folio's job
   is to keep it working — through renames, moves, and slug changes.
6. **Delete a cache and nothing is lost.** `data/thumbs/`, `data/text/`,
   `data/ocr/`, and `data/previews/` are all disposable.

### Near term

Work that is scoped and would not change existing behaviour.

- **Reconcile and relink buttons in the admin.** The endpoints exist, are
  CSRF-protected, and are tested, but Diagnostics currently only reports what
  it would do — running it needs a POST. This is the largest gap between what
  Folio can do and what you can reach.
- **Batch OCR.** Today OCR runs one document at a time from the listing. A
  queue that works through everything unprocessed, resumable and bounded, is
  the obvious next step for a large scanned archive.
- **Bulk metadata editing.** Applying a category or tag across a selection,
  rather than one row at a time.
- **A slug history view.** Previous addresses are stored and redirect
  correctly, but there is no screen showing them or allowing one to be
  retired deliberately.

### Medium term

Larger pieces that need design work before they are safe to start.

- **Global search.** Current search filters the folder you are looking at, in
  the browser. With OCR text now cached in `data/text/`, a real search across
  every document's contents becomes possible. It needs an index that stays
  cheap to build and cannot leak excluded or access-restricted documents.
- **Per-document access beyond PDFs.** `pdf_access` covers PDFs. The same
  gating could reasonably extend to images and other formats, using the
  signing mechanism that already exists.
- **A read-only account role.** Every account currently has full authority.
  A role that can edit metadata but not manage accounts or settings would
  suit a library with more than one cataloguer.
- **Structured dates.** Documents carry a description like "Dewan Siswa,
  Oktober 1998". A real date field would allow chronological browsing and
  better structured data, but needs a migration and a way to express
  uncertain or partial dates, which historical documents very often have.

### Under consideration

Ideas that are plausible but not yet decided. Listed so the thinking is
visible, not because they will happen.

- **Multiple languages in the interface.** The building blocks exist —
  documents already carry a language — but translating the interface is a
  commitment that outlives enthusiasm for it.
- **An export or backup command.** Producing a single archive of files plus
  catalogue. Much of this is already achievable with FTP and a copy of
  `data/`, so the value is convenience rather than capability.
- **Handwriting recognition.** Tesseract reads printed text. Handwritten
  documents currently need the transcript field filled in by hand. The
  models that do this well are not things a shared host will run.
- **IIIF or OAI-PMH.** Standard interfaces for institutional repositories.
  Worth doing only if Folio is actually being harvested by one.

### Explicitly not planned

Saying no is part of a roadmap. These have been considered and declined.

- **A web-based file manager.** Uploading, renaming, moving, and deleting
  through the browser. This is the single most requested thing a document
  application can do and Folio will not do it: the whole design rests on the
  application never being able to damage the library.
- **A database backend.** It would solve problems Folio does not have and
  create ones it currently avoids, starting with backup and portability.
- **Comments, accounts for visitors, or any social feature.** Folio publishes
  a collection; it is not a platform.
- **A plugin system.** It would make every future change a compatibility
  problem, for a single-file application maintained by one person.
- **Themes as installable packages.** The colour schemes and the stylesheet
  are editable directly, which covers the real need without the machinery.

### How to read a version number

Folio follows semantic versioning, with one clarification: **a change to a
shipped default counts as minor, not patch**, because it changes behaviour on
an existing installation.

- **Patch** (1.6.3 → 1.6.4) — fixes and documentation. Upload and go.
- **Minor** (1.5.0 → 1.6.0) — new capability, existing behaviour preserved.
  Read the changelog; there may be an optional step.
- **Major** — a breaking change. There has not been one, and the principles
  above are intended to make one unnecessary.

Every release records what changed in `changelog.md`, and anything needing
action from you appears in this file under its own heading.

## Removing Folio

Download `uploads/`, `data/`, and `config.php` if they may be needed again.
Delete the Folio folder and remove its rewrite/server block. Remove or update
the Folio `Sitemap:` line in the domain-root `robots.txt`.

Folio uses no database. PHP itself may keep session files in the server's
configured session directory until normal expiry.

## Appendix: upgrading to 1.0.1

Nothing needs to be migrated: no data format changed and no configuration key
was added or removed. Overwrite the release-owned files as described below and
the upgrade is complete. Two behavioural changes are worth knowing about
before you do.

**Logging out is now a POST.** A plain `GET ?action=logout` no longer ends the
session; it returns `405` with a confirmation form instead. This closes a
request-forgery hole where any third-party page could sign an administrator
out. The header link continues to work normally. If you bookmarked the logout
URL, linked to it, or scripted it, update it to submit a POST carrying a valid
CSRF token.

**The Bing sitemap ping button is gone.** Microsoft retired anonymous sitemap
submission in May 2022 and the endpoint answers `410 Gone`, so the button
reported success while doing nothing. Nothing replaces it because nothing
needs to: the sitemap reference in `robots.txt` is what crawlers actually
read. Use Bing Webmaster Tools or Google Search Console for a manual
submission, or IndexNow to push changes immediately.

Metadata records gain an `updated_at` timestamp the first time each document
is edited after upgrading. Nothing is rewritten automatically and no migration
step is required: records without the field fall back to the file's
modification date, exactly as before.

If your library holds more than 50,000 URLs, `sitemap.xml` now returns a
sitemap index rather than a URL set, and the URLs are served from
`sitemap-1.xml` onwards. On Apache, take the new
`RewriteRule ^sitemap-([0-9]+)\.xml$` line from the shipped `.htaccess` into
your live one; without it the numbered parts will not resolve under clean
URLs.
Libraries below the limit are served exactly as before.

