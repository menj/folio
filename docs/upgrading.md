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

## How to upgrade — any version to the latest

This is the standard procedure, and it is the same every release. The
per-version sections below add only what a specific release needs on top of
it; if a version is not listed there, this is the whole of it. To cross
several releases at once, read each per-version section between your build and
the latest first — they are ordered newest at the top — then follow these
steps once.

### Step 1 — Upload the release, except your own files

Upload every file and folder from the package **except** the three that belong
to your installation:

```
config.php
data/
uploads/
```

Everything else is release-owned and is meant to be overwritten: `index.php`,
`install.php`, `.htaccess`, `robots.txt`, all of `assets/`, `lib/`, `docs/`,
`tests/`, and the root documentation files.

**Upload `.htaccess`; it is not optional.** Several features live in its rules
— PDF access control, the PDF sitemap, asset cache lifetimes, and the
media byte-range delivery that makes audio and video seek. A site missing the
current `.htaccess` looks fine and quietly loses these. If you hand-edited it
(for example, renaming the uploads folder), re-apply your edit to the new file
rather than keeping the old one.

**Keep `branding/`.** Your site icon lives there, untouched by upgrades. The
`uploads/readme.txt` and `data/readme.txt` placeholders in the package are
harmless if they land; they only keep those folders present in git.

### Step 2 — Reload once with the cache cleared

Press Ctrl-Shift-R, or open the site in a private window. Assets are linked
with the version number since 1.13.1, so a stale stylesheet corrects itself,
but this one reload clears anything left from an older build.

### Step 3 — Verify

1. Open **Diagnostics**. It should report the new version and no failures.
2. Open a document page and hover a row in the listing — previews render.
3. Open `/sitemap.xml` and `/sitemap-pdf.xml` — both list your documents.
4. Sort the listing by a column header — the arrows draw correctly, not as
   plain boxes (if they do, repeat step 2; it is the browser cache).

Nothing in the standard procedure migrates or rewrites stored data. Your
documents, titles, categories, tags, accounts, and settings carry across as
they are. Where a specific release does migrate something — such as the
metadata store's move to `document_id` keys — its own section below says so.

## Upgrading to 1.28.0

Upload the release as usual, excluding `config.php`, `data/`, and `uploads/`,
then copy the single file `uploads/.htaccess` from the release over your
existing one: it gained the managed `# BEGIN/# END Folio video access control`
block that the new feature fills in. Leave those two marker lines in place. If
you do not copy it, video access control simply cannot be switched on; nothing
else changes.

Video access control is opt-in and off by default, so existing libraries are
unaffected until you enable it under Crawlers. It reuses `FOLIO_URL_SIGNING_KEY`
(the same key PDF access uses), and it fails closed: while it is on but the
preflight has not confirmed your server refuses direct video, non-public video
is hidden from the public rather than served. `uploads/` must be writable by the
web server for Folio to manage the block, which is already the case on any
install that accepts uploads.

## Upgrading to 1.27.0

Upload the release as usual, excluding `config.php`, `data/`, and `uploads/`.
There is no required manual step.

New in this release: a JSON Feed at `/feed.json`. It works through the existing
catch-all, so it needs no configuration. The root `.htaccess` gained one
optional line, an explicit rewrite for `feed.json`, grouped with the sitemap
rules; replacing the root `.htaccess` is optional because the catch-all already
routes that address. This release also finishes announcing the category sitemap
in the static `robots.txt`, so if you keep a copy of it at your domain root,
copy the new `Sitemap:` line across.

## Upgrading to 1.26.2

Upload the release as usual, excluding `config.php`, `data/`, and `uploads/`,
then copy the single file `uploads/.htaccess` from the release over your
existing one. That file gained cache and byte-range headers for audio and
video, which let the browser stream media and stop it from re-downloading on a
replay or a seek. Without the new `uploads/.htaccess` playback still works, but
it will feel slower. Do not touch anything else in `uploads/`.

If media still buffers slowly after this, check that your server answers a
byte-range request with `206 Partial Content` rather than `200`. On Apache the
supplied rules handle it. On nginx or another server, range support for static
files has to be enabled in that server's own configuration; Folio cannot set it
from `.htaccess` there.

## Upgrading to 1.26.0

Upload the release as usual, excluding `config.php`, `data/`, and `uploads/`.
There is no required manual step.

Three things are new. Folders can carry a description, stored in a file the
application writes for you, `data/folder-descriptions.json`. The category
archive pages now have their own sitemap at `sitemap-categories.xml`, announced
in the `robots.txt` shown on the Crawlers screen. And the audio playlist has
moved off the document pages onto a dedicated page, reached by a "Play all"
button on any folder holding two or more audio files; a document page now plays
only its own file. The `AUDIO_PLAYLIST` setting still turns the playlist on or
off.

The root `.htaccess` gained one line, an explicit rewrite for
`sitemap-categories.xml`. Replacing the root `.htaccess` is optional, because
the existing catch-all already routes that address to the application. Clear
your browser cache once so the new stylesheet loads.

## Upgrading to 1.25.0

Upload the release as usual, excluding `config.php`, `data/`, and `uploads/`.
Clear your browser cache once so the new stylesheet loads. Nothing else to do.

The folder listing now sends fewer bytes — its in-page links are root-relative
and the template's indentation whitespace is collapsed — but every page looks
and behaves exactly as before, and canonical URLs, structured data, sitemap
entries, and IndexNow remain fully absolute. The in-page media player's seek
bar and playlist rows are larger on touch screens; on a desktop nothing moves.

### Removed from Known issues

The roadmap listed the repeated `BASE_URL` on every listing row, and the
indentation whitespace between cells, as outstanding page-weight issues. Both
are done, and the entries have been removed.

## Upgrading across 1.17.0 to 1.24.0

This whole span shipped in a few days and nothing in it migrates or rewrites
stored data, so the standard procedure above is all it takes. Two changes are
worth knowing before you upgrade, because both are visible on the first visit.

- **Audio and video now play in the page (1.24.0).** Files that were
  preview-and-download only — `.mp3`, `.m4a`, `.aac`, `.wav`, `.flac`, `.ogg`,
  `.oga`, `.opus`, `.weba`, `.mp4`, `.m4v`, `.webm`, `.ogv`, `.mov` — open in a
  themed player on the document page and in the listing preview. The current
  `.htaccess` puts these extensions on the direct-serve path so the browser can
  request byte ranges and seek, which is the one reason uploading the new
  `.htaccess` matters here specifically. A reader without JavaScript still gets
  the browser's own player. An optional **audio playlist**, off by default, is
  turned on from Settings (`AUDIO_PLAYLIST`) and plays a folder's audio as a
  queue.

- **The system dark-mode preference is honoured on a first visit (1.23.0).** A
  reader whose operating system asks for dark now opens on the Night theme when
  they have made no choice of their own. Any saved choice still wins, and the
  theme picker is unchanged. This is a shipped-default change, not a migration:
  nothing stored is altered.

Everything else in the range is additive or cosmetic and needs nothing from
you: large folders paginate above `PER_PAGE` documents with server-side sorting
(1.21.0), the interface gained a consistent radius scale, soft shadows, and
touch feedback (1.20.0), a skip-to-content link and an accessible search-field
name (1.23.0), and mobile touch targets were enlarged (1.22.0). Full detail is
in `changelog.md`.

## Upgrading to 1.16.1

Upload the release as usual, excluding `config.php`, `data/`, and `uploads/`.

**Your category URLs change.** They lose the hash suffix that every category
carried: `/category/tracts-2a4f72ad/` becomes `/category/tracts/`. Only
categories whose names collide when slugified keep a suffix.

Nothing breaks. The old addresses 301-redirect to the new ones, so indexed
links, bookmarks, and any sitemap already submitted continue to work. Search
engines will pick up the new addresses on their next crawl; the sitemap
carries them immediately.

If you would rather they did not change, there is no setting for it: the old
form is available only as a redirect target.

## Upgrading to 1.14.0

Upload the new release except `config.php`, `data/`, and `uploads/`, as usual.
Two things are new in the package:

```
assets/css/*.min.css     minified stylesheets
assets/js/*.min.js       minified scripts
tools/minify.js          the script that builds them; maintainers only
```

Folio serves the minified files automatically. There is nothing to enable.

**If you have edited `assets/css/style.css`** to change a colour or a rule,
your edit still wins: Folio compares modification times and ignores a
minified file that is older than its source. You do not need to rebuild
anything, and you can delete the `.min.` files entirely if you would rather
work with readable sources permanently.

Reload once with Ctrl-Shift-R afterwards. The asset URLs carry the version,
so this should not be necessary, but it costs nothing.

### Removed from Known issues

The roadmap listed minification as outstanding. It is done, and the entry has
been removed.

## Upgrading to 1.13.1

Everything from 1.7.0 to 1.13.1 shipped over two days, so this covers the whole
span at once. If you are further back than 1.7.0, work through the older
sections below first, then return here.

Nothing in this range migrates or rewrites stored data. Your documents,
titles, categories, tags, accounts, and settings carry over as they are.

### Step 1 — Upload the whole release except your own files

Upload every file and folder from the package except these three:

```
config.php
data/
uploads/
```

Two of the files people skip are the ones that matter most here.

**`.htaccess` is mandatory, not optional.** Between 1.9.0 and 1.11.x it gained
rules that features now depend on. Without the new file, restricted PDFs are
served straight off disk with no access check, `/sitemap-pdf.xml` returns
nothing, and compression and cache lifetimes never apply. The site will look
fine, which is what makes it worth stating plainly.

**`uploads/.htaccess` fixes a fault that could return 403 for every document
in the folder** (1.8.3). If your library has been returning permission errors
on files that plainly exist, this is why.

### Step 2 — Upload the new folders

```
branding/          your own site icon lives here, safe from upgrades
```

`branding/` became part of the release in 1.7.2. Put `favicon.svg`,
`favicon.ico`, or `apple-touch-icon.png` in it and Folio finds them with no
configuration. Icons placed inside `assets/` are overwritten on every upgrade,
which is the problem the folder exists to solve.

You will also see `uploads/readme.txt` and `data/readme.txt` in the package.
They exist only so the folders survive a `git clone`, and are harmless either
way.

### Step 3 — Reload once with the cache cleared

Press Ctrl-Shift-R, or open the site in a private window.

Releases up to 1.13.0 told browsers to cache the stylesheet and scripts for a
year without changing the URL when those files changed. A browser that had
visited before would keep the old stylesheet and apply it to new markup: sort
buttons drawn as plain boxes, the header unstyled. From 1.13.1 assets are
linked with a version, so this corrects itself and will not recur. This one
reload clears the last of it.

### Step 4 — Verify

1. Open **Diagnostics**. It should report 1.13.1 and no failures.
2. Open a PDF document page, and hover a PDF row in the listing. Both should
   show a rendered page.
3. Open `/sitemap-pdf.xml`. It should list your documents.
4. Sort the listing by Name, then Size. The columns should be clickable and
   the arrows should render properly rather than as plain boxes.
5. If anything still looks unstyled, repeat step 3 — it is the browser cache,
   not the upgrade.

### Worth knowing

- **The licence changed in 1.8.0** to the GNU General Public License v3 or
  later. If you have redistributed Folio or built on it, read `license.txt`.
- **A Catalogue screen** arrived in 1.13.0 at `?action=catalogue`. If any
  records have drifted from their files, it will show them and offer to
  reconnect records to files by comparing contents. It never touches the files
  themselves, and only accepts a match when it is unambiguous. Worth opening
  once after upgrading a library that has been reorganised over FTP.
- **Documents have their own date field** from 1.10.0, separate from the
  file's modification time. Existing documents keep showing the file date
  until you set one, so nothing changes on its own.

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
short-lived URLs for PDFs set to "restricted" access. It is optional — leaving
it unset simply means the PDF access control feature stays inert, every PDF
continuing to behave exactly as it did in 1.2.0 — but add it to
`config.php` if you plan to use "restricted" or "hidden" `pdf_access`:

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

If you intend to set any PDF to "restricted" or "hidden," visit the Crawlers
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
   `data/ocr/`, `data/previews/`, `data/compressed/`, and `data/aspect.json`
   are all disposable and rebuild on demand.

### Known issues

Measured on this release rather than assumed. These are defects or gaps with a
known cause, listed ahead of new work because they affect libraries that exist
today.

- **In-page search covers the page, not the folder, once a folder is
  paginated.** The field says so — it reads *Search this page* rather than
  *Search this folder* — but a reader with two thousand documents wants to
  search all of them. A folder-scoped server search would fix it, and is the
  smaller half of the global search listed under Medium term.

- **`index.php` is past the size the single-file design serves well.** At
  9,311 lines and 170 functions it has grown by roughly 350 lines since this
  was first noted, and the admin screens are the bulk of it. Moving them into
  `admin/` includes would roughly halve the main file while keeping the
  copy-a-folder deployment intact. This is maintainability, not behaviour:
  nothing a reader sees would change. The figures here are re-measured each
  time the list is reviewed, because a number that quietly goes stale is worse
  than no number.

- **The catalogue cannot be exported from the admin.** `data/metadata.json`
  holds every title, description, category, tag, and date entered by hand, and
  is the one asset that cannot be regenerated from the files. Backing it up
  currently requires FTP. A download button on the Catalogue screen would be
  small and would protect the thing most worth protecting.

### The archive: a phased plan

menj.bio is a documentary biographical archive: its records are primary
documents, and the work here strengthens the archive as an archive rather than
turning it into a personal site. The features are general, so any Folio library
used as an archive gains from them.

Two foundations already exist and the plan builds on them. `doc_date` is free
text that `document_date_parse()` reads as a bare year, "Oktober 1998",
"c. 1985", day-first numeric, and Malay month names, returning an ISO value, a
year, and a precision. And every record carries a stable `document_id` apart
from its slug, so a link or a timeline entry survives a rename. Every phase
obeys the principles above: files on disk are never touched, no database
appears, adding a field stays backward compatible, and new links reference
`document_id` and render through the current slug.

The order runs foundations first. The timeline is the centrepiece and the most
dependent piece, since it is only as good as its date coverage and an event
holding several documents is the related-records feature seen from another
angle. So dates and description come before relationships, and the timeline
comes last.

- **Phase A, dates.** Keep the date a document was created apart from the date
  it was digitised and the date it entered the archive. Relabels `doc_date` as
  the document date, keeping the stored key so no record churns; adds
  `digitised_date` and `added_at`; and makes the default listing sort the
  document date. See decision 1 for undated records.

- **Phase B, standardised metadata.** Describe every record the same way. Adds
  issuing organisation, place, provenance, archive identifier, and a Collection
  axis beside category, the last with its own archive page reusing the
  category-archive pattern. The new fields map onto the schema.org and Dublin
  Core graph Folio already emits: issuing organisation to `sourceOrganization`,
  place to `contentLocation`, provenance to `dcterms:provenance`, archive
  identifier to `identifier`, and Collection to `isPartOf`.

- **Phase C, related records.** Connect documents that belong to the same
  event, qualification, or publication. Each link is stored once against a
  `document_id` with its inverse computed at read time, so the two sides cannot
  drift, and a disposable index under `data/` caches the reverse lookup only if
  a library grows large enough to need it. The connection type is separate from
  what a document is, which stays in the document type field. The types and
  their inverses are `part_of` and `has_part`, `supersedes` and
  `superseded_by`, `references` and `referenced_by`, and a symmetric
  `related_to`. See decision 3.

- **Phase D, timeline.** Present the records as a life history. A timeline view
  buckets records by the year of their document date, links each entry to its
  record, and folds an event's related documents in beneath the principal one.
  An event is modelled with the Phase C relationships, so no separate event
  type is introduced. Named periods layer on top of the year buckets later, and
  undated records are listed together at the end so nothing disappears. This is
  the Chronological browsing goal, now given a shape.

Four decisions gate the work, each with a proposed answer, settled before the
phase it gates.

1. **Default sort.** Moving the default from name to document date changes a
   shipped default, which is a minor. Proposed: dated records in date order,
   then undated records after them by name.
2. **`added_at` cannot be backfilled.** Existing records were never stamped.
   Proposed: leave older records blank rather than inventing a date. The
   alternative is to read the file modification time as the added date and
   label it a guess.
3. **Relationship type versus document type.** The source examples mix a
   connection (`parent_record`, `supporting_document`) with what a document is
   (certificate, transcript, results slip). Proposed: the small type set above,
   with the role kept in the document type field.
4. **Where the richer form lives.** The full field set is too tall for the
   inline row editor. Proposed: a dedicated per-document edit screen grouped
   into identity, dates, provenance, and relationships.

### Redaction: a phased plan

**Status (1.30.0): PDFs shipped; images still planned.** PDF redaction now
exists and follows the model below exactly — fractional rectangles marked in a
dashboard editor, an image-only rendered copy served to the public with the
boxes burned in, the original gated, and fail-closed behaviour when the render
engine is missing. In terms of the phases below, that is R2 (render and cache),
R4 (the marking tool), and the PDF portion of R3 (every PDF serve path —
detail, preview, flip reader, hover, thumbnails, structured-data image — routes
through the derivative). What remains is the **image** side: R1 (extending the
access gate to image files) and redaction of images rather than PDFs. The
design record below is kept because the image work still follows it; read the
PDF feature as the first, proven instance of it. The settled decision from the
open questions: redaction is its own flag beside `pdf_access`, not a fourth
state of it, and a document can be partially public — the redacted copy public
while the original is gated, which is the passport case the feature exists for.

An archive of real documents needs to publish a passport or an identity card
while hiding the number, the address, and the photograph. This is planned, and
there is exactly one honest way to build it. The obvious way is a fake, so the
constraint is written down first.

**Client-side boxes are not redaction.** Drawing black rectangles over the
image with CSS or canvas leaves the original bytes one right-click, one
network-tab look, or one devtools deletion away. Folio serves image bytes
directly from the web server, so anything painted in the browser hides nothing.
Folio will not ship that and call it redaction.

**The real model is gate the original, publish a rendered copy.** The censored
regions are burned into the pixels on the server, and the original stops being
publicly reachable. Everything below follows from that.

- The admin marks rectangles on the document from the dashboard. They are
  stored as fractions of the page, so they hold at any resolution and survive
  a re-render.
- Folio renders a redacted copy: rasterise the image or the PDF page, paint
  solid opaque boxes over the marked regions, strip embedded metadata, and
  cache the result under `data/` as a disposable derivative.
- Every public path serves only the redacted copy: the detail view, the
  preview pane, the hover and listing thumbnails, the sitemap image, the
  structured-data image, and the direct link. The original becomes
  access-controlled the way a hidden PDF already is, so it is never served
  whole to the public.

That last point is the whole feature. Redaction is only as strong as the
weakest route that serves original bytes, so this is really "extend the
`pdf_access` gate to images and route every serve path through the rendered
copy," with a marking tool on top. It is the concrete form of the "per-document
access beyond PDFs" item below.

**Two rules that cannot bend.**

- **It fails closed.** Redaction depends on an image engine (Imagick or GD,
  both already used for thumbnails) and, for PDFs, on Poppler. When the engine
  is missing or a render fails, the safe behaviour is to refuse to show the
  document, never to fall back to the unredacted original.
- **A redacted PDF is shown as page images, not the embedded file.** Painting
  over a PDF page leaves the text underneath extractable, which is the exact
  way "redacted" government files have leaked. So a redacted PDF is served as
  rendered page images, the embedded viewer and flip reader are off for it, and
  the original PDF stays gated. Selectable text is lost on redacted documents.
  That is the price of the boxes being real.

The phases build the safe core before the convenience.

- **Phase R1, gate images.** Extend the `pdf_access` states (public, restricted,
  hidden) to image files, so an original can be marked non-public and served
  only through the signed, access-controlled path. No redaction yet; this is
  the gate the rest stands on, and it fails closed when signing is not
  configured, exactly as PDF gating already does.

- **Phase R2, render and cache.** Given a file and a set of rectangles,
  produce the redacted derivative: rasterise, paint opaque boxes, strip
  metadata, cache under `data/`. Invalidate the cache when the rectangles or
  the source change. Refuse when the engine is absent.

- **Phase R3, route every serve path.** Point the detail view, preview,
  thumbnails, hover, sitemap image, and structured-data image at the
  derivative for a redacted record, and gate the original behind the R1
  access control. Audit each path so none serves the source.

- **Phase R4, the marking tool.** A dashboard editor to draw, move, and delete
  rectangles on the document, stored as page fractions. This comes last on
  purpose: the serving and gating must be proven safe before a tool makes it
  easy to rely on them.

Decisions to settle before R1: whether redaction reuses the `pdf_access`
field and its three states directly or gets its own flag beside it; and
whether a document can be partially public, meaning the redacted copy is
public while the original is gated, which is the passport case and the reason
the feature exists.

### Near term

Work that is scoped and would not change existing behaviour.

- **Batch OCR.** Today OCR runs one document at a time from the listing. A
  queue that works through everything unprocessed, resumable and bounded, is
  the obvious next step for a large scanned archive.
- **Bulk metadata editing.** Applying a category or tag across a selection,
  rather than one row at a time.
- **A slug history view.** Previous addresses are stored and redirect
  correctly, but there is no screen showing them or allowing one to be
  retired deliberately.
- **Captions and a transcript for audio and video.** The in-page player
  (1.24.0) plays media but shows no text alongside it, while the archive
  already keeps a transcript field for documents. A `<track>` for caption
  files placed beside a media file, and the existing transcript rendered under
  the player, would make spoken records as crawlable and accessible as the
  scanned ones — the same transcript-first posture the PDF access model takes,
  applied to media. Additive, no new dependency.

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

### Under consideration

Ideas that are plausible but not yet decided. Listed so the thinking is
visible, not because they will happen.

- **Multiple languages in the interface.** The building blocks exist —
  documents already carry a language — but translating the interface is a
  commitment that outlives enthusiasm for it.
- **A full export command.** Producing a single archive of files plus
  catalogue. The catalogue half of this is listed under Known issues as worth
  doing on its own; bundling the documents alongside it is largely achievable
  with FTP already, so the remaining value is convenience.
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

