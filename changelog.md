# Changelog

All notable changes to Folio are recorded here. Versions follow semantic
versioning: major for breaking changes, minor for features, patch for fixes.

## 1.8.3 — 4 August 2026

### Fixed

- **`uploads/.htaccess` could return 403 for every file in the folder.** It set
  `Options -FollowSymLinks`. On cPanel and similar hosts the path to
  `public_html` is frequently a symlink itself, and disabling symlink following
  makes Apache refuse the entire directory — documents included. It is now
  `+SymLinksIfOwnerMatch`, which keeps the protection without the risk. Folio's
  own path resolution rejects symlinks regardless, so nothing is lost.

- **PDFs were being told not to appear in search results.** Both
  `uploads/.htaccess` and the raw file endpoint sent
  `X-Robots-Tag: noindex, follow` for `.pdf`, `.txt` and `.md`. For a public
  document library that is backwards: the documents are the content, and a
  scanned certificate a search engine cannot see is a document nobody will
  find. Documents are now left indexable. The detail page still carries the
  canonical URL, so the record and the file do not compete.

Formats a browser would execute — HTML, XHTML, XML, MHTML and anything
unrecognised — are unaffected and still forced to download with `noindex`.

## 1.8.2 — 4 August 2026

### Fixed

- **`uploads/` did not appear when the project was pushed to GitHub.** Two
  causes. The `.gitignore` carried a bare `.htaccess` rule, which matches at
  every level, and it came *after* the `!uploads/.htaccess` exception — so the
  later rule won and every file in `uploads/` was ignored. With nothing
  tracked, Git dropped the directory, since it cannot store an empty one. The
  rule is now `/.htaccess`, scoped to the installed copy at the root.

  Separately, `uploads/` and `data/` contained only dotfiles, and GitHub's
  web uploader silently skips those — so even a correct `.gitignore` would
  have produced empty folders when dragging the release in. Both now carry a
  visible `readme.txt` explaining what belongs there.

- **`data/` ignored a list of known files rather than the folder.** Any
  runtime file added by a future version would have been committed to a public
  repository. It now ignores the folder and names the few files that belong in
  the repository, so it fails closed.

Verified with `git check-ignore`: `uploads/` and `data/` are both present in a
commit, while `config.php`, the installed `.htaccess`, accounts, settings, the
catalogue, generated caches, and uploaded documents are all still excluded.

## 1.8.1 — 4 August 2026

### Changed

- **Stopped labouring the Ghostscript point.** Folio does not use Ghostscript,
  and the documentation said so 77 times across seven files — in the readmes,
  the security notes, the architecture reference, the upgrade guide, the
  sample configuration, and on the Diagnostics screen. Repeating it made an
  irrelevance look like a caveat. The user-facing text now simply says PDF
  pages are rendered with Poppler.

  What remains is only the `PDF_ALLOW_GHOSTSCRIPT` setting name, which cannot
  be renamed without breaking existing configurations. Behaviour is unchanged.

## 1.8.0 — 4 August 2026

Folio is free software.

### Changed

- **Folio is now licensed under the GNU General Public License, version 3 or
  later.** It was previously marked proprietary, which was wrong: Folio is
  open source. You may use, study, modify, and redistribute it; derivative
  works carry the same licence.

      Copyright (C) 2026 Mohd Elfie Nieshaem Juferi
      SPDX-License-Identifier: GPL-3.0-or-later

  `license.txt` now carries the full, verbatim GPL version 3 text, preceded by
  the standard notice and a list of the bundled components.

- **Version 3 rather than version 2 is a constraint, not a preference.** The
  bundled Mozilla pdf.js is Apache-2.0, which is compatible with GPL version 3
  and incompatible with version 2. Licensing Folio as GPL-2.0-only would make
  the release undistributable without first removing pdf.js.

- Every source file — PHP, JavaScript, and CSS — carries an
  `SPDX-License-Identifier` line, so the licence is discoverable from any one
  file rather than only from the release as a whole.

- `docs/ssot.md` gained a Licensing section recording each bundled
  component's licence and where its notice lives, and an invariant: a
  dependency that is GPL-2-only, proprietary, or non-commercial cannot be
  bundled.

### Unchanged

- The bundled components keep their own licences and are unaffected:
  Parsedown (MIT), Mozilla pdf.js (Apache-2.0), and the OpenJPEG and QCMS
  WebAssembly decoders.

## 1.7.2 — 4 August 2026

### Fixed

- **A custom site icon still did not appear.** 1.6.1 added the `branding/`
  folder and made every page emit the right `<link>` tags, and that part
  worked — but browsers also request `/favicon.ico` and
  `/apple-touch-icon.png` directly, whatever the tags say. Tabs, bookmarks,
  history and session restore all use the root path. With the catch-all
  rewrite in place those requests reached `index.php`, which tried to resolve
  them as document slugs and returned an HTML 404, so the browser fell back to
  a blank icon and the custom one never showed.

  Those paths are now answered with the real icon, preferring `branding/` and
  falling back to the one Folio ships. Verified for `favicon.ico`,
  `favicon.svg`, and `apple-touch-icon.png`, with and without a `branding/`
  folder present.

- **`branding/` is now part of the release**, with a short readme inside it.
  Previously the folder had to be created by hand after reading the
  documentation, which is a poor way to discover a feature.

### Added

- A regression test asserting the root icon paths return an actual image
  rather than an HTML page. It fails if the handler is removed.

### Note

Icons are cached hard by browsers. If the old one persists after upgrading,
try a private window before assuming it has not worked.

## 1.7.1 — 4 August 2026

Two Diagnostics faults, both of which made a healthy installation look broken.

### Fixed

- **"Rewrite route received" always reported a failure.** It inspected the
  current request, but Diagnostics is only ever reached at
  `?action=diagnostics` — a query-string admin URL that is never rewritten by
  design. The check could therefore never pass, and reported "this request was
  not rewritten" on sites whose clean URLs were working perfectly. It is
  replaced by a **Rewrite rules** check that reads `.htaccess` for the rules
  that actually matter, including the PDF access rule, and says plainly that
  an admin page cannot demonstrate rewriting itself.
- **`mod_rewrite` was flagged amber on every modern host.** It can only be
  read under Apache mod_php, so PHP-FPM and CGI — which is most hosting — got
  a warning for a condition that is normal and harmless. It now reports as
  information, with the note explaining that working clean URLs are the real
  proof.
- **Status chips and notes were misaligned between groups, and long labels
  collided with them.** Each group is a separate table, and with automatic
  layout every one computed its own column widths. The tables now share a
  fixed layout, so all 38 rows line up in a single column, and a long label
  wraps instead of running into the chip beside it.

## 1.7.0 — 4 August 2026

Standalone pages now work the way documents do.

### Changed

- **Pages have editable URL slugs.** A page's address was fixed to its
  internal slot name behind a `/p/` prefix, so the three general-purpose pages
  were stuck at `/p/page1/`, `/p/page2/` and `/p/page3/` — names that mean
  nothing to a reader. Each page now has a **URL slug** field, and its address
  is whatever you set: `/bibliography/`, not `/p/page1/`.
- **Pages and documents share one flat namespace.** There is no reason a
  certificate should get a tidier address than an About page. A slug is
  checked against documents, other pages, and Folio's own routes before it is
  accepted, in both directions: a page cannot take a document's address and a
  document cannot take a page's. An uncatalogued file is checked too, since it
  still answers on its filename-derived address.
- **Old addresses keep working.** `/p/page1/` and `/page1/` both redirect
  permanently to the page's current address, so existing links and bookmarks
  survive. About and FAQ keep `/about/` and `/faq/` unless you change them.

This was an inconsistency left by 1.6.0, which gave documents editable,
collision-checked slugs and did not extend the same treatment to pages.

## 1.6.6 — 4 August 2026

### Fixed

- **Nothing told you how to produce `FOLIO_URL_SIGNING_KEY`.** Without it, PDF
  access control cannot be enforced, and the Crawlers screen said only
  "generate one and add it there" — while the IndexNow key beside it had a
  one-click generator. The screen now shows a freshly generated 256-bit key as
  a complete, copyable `define(...)` line, with a new one offered on every
  visit until the setting is in place. `config-sample.php` gained the
  equivalent shell commands.

  Folio does not write the key into `config.php` itself, deliberately: that
  file stays one the application cannot modify, which is what stops a future
  flaw from rewriting Folio's own configuration.

### Added

- A step-by-step **"Turning on PDF access control"** section in the readme
  covering the signing key, the preflight, the `.htaccess` rule, and the
  warning that renaming the uploads folder silently disables enforcement
  unless the rule is updated to match.

## 1.6.5 — 4 August 2026

Documentation completeness, and a roadmap.

### Added

- **A roadmap in `docs/upgrading.md`.** It records the principles that will
  not change, what is planned next, what is under consideration, and — just as
  usefully — what has been considered and declined, with reasons. The two
  questions "what changes if I upgrade" and "what is going to change later"
  belong in the same file.
- A note in the readme and readme.txt pointing at it, so that something listed
  as declined is not mistaken for something merely not done yet.

### Fixed

- **Six configuration constants were documented nowhere.** `SITE_INDEXABLE`,
  `SITEMAP_ENABLED`, `LLMS_ENABLED`, `LLMS_INTRO`, `INDEXNOW_KEY`, and
  `PDF_GATE_CONFIRMED` are written by the admin screens, so nothing was
  broken, but a reader of `config-sample.php` had no way to learn they exist
  or that defining one by hand takes it away from the admin screen. They are
  now listed with that explanation, and with a warning against setting
  `PDF_GATE_CONFIRMED` manually — doing so claims a restriction is enforced
  when it may not be. All 46 constants are now documented.
- **Four endpoints were missing from the reference.** `?action=thumb`,
  `?action=ocr`, `?action=reconcile`, and `?action=relink` were added in
  1.5.0 and 1.6.0 but never reached the endpoint table in `docs/ssot.md`.
  `?action=meta` and `?action=logout` were absent too.
- **The documentation-set table said "these five" while listing six**, and
  omitted `security.md` and `tests/readme.md` altogether. It now lists all
  eight and states each one's scope.

## 1.6.4 — 4 August 2026

Diagnostics now reports every PHP extension Folio can use.

### Fixed

- **The image engine row had disappeared.** It was added in 1.5.0 and lost in
  the 1.6.0 merge, because the branch it was merged onto predated it. Whether
  Imagick or GD was available — the difference between working thumbnails and
  none — was not reported anywhere.

### Added

- Diagnostics rows for **image engine**, **fileinfo**, **iconv**, and
  **OPcache**, alongside the existing PHP version, mbstring, JSON, password
  hashing and randomness checks. Each says what is actually lost when the
  extension is missing rather than only naming it: GD alone cannot convert
  TIFF, HEIC or AVIF; without fileinfo a mislabelled file is served with the
  wrong type; without iconv accented characters are stripped from slugs
  instead of transliterated.
- OPcache reports as a check rather than OK when it is off, since a
  single-file application benefits from it noticeably.

### Unchanged

- Only mbstring is genuinely required, and only for Markdown. Every other
  extension has a fallback: verified by serving the listing, a document page
  and the sitemap with Imagick, GD, fileinfo, iconv and mbstring all absent —
  all returned 200.

## 1.6.3 — 4 August 2026

Two utilities were detected and advertised but never actually used.

### Fixed

- **`pngquant` was dead code.** `thumb_optimise()` returned immediately unless
  the file ended in `.png`, but every derivative Folio writes is WebP, so the
  condition could never be true. It now runs on the PNG that Poppler produces
  when rendering a PDF page — the one point in the pipeline where a real PNG
  exists — shrinking it before it is decoded and re-encoded. Measured at 26%
  smaller on a 1400x900 render.
- **`exiftool` was never called at all.** It appeared only in the version
  table and a Diagnostics label. It now reads a document's own creation date,
  which is usually closer to the truth than the filesystem time — that changes
  every time a file is copied or re-uploaded over FTP. The date is stored as
  `captured_at` when a document states one.
- Diagnostics described both in terms of what they were supposed to do rather
  than what they did. The labels now say what actually happens.

### Added

- A regression test asserting that **every utility Diagnostics advertises is
  invoked somewhere**. Detection without a call site is worse than not
  supporting a tool: the interface reports a capability the application does
  not have. Verified to fail when either utility is returned to its previous
  state.

## 1.6.2 — 4 August 2026

OCR is now usable from the interface. It was implemented and tested in 1.5.0
but there was no way to start it.

### Fixed

- **`admin.js` was never loaded on the listing page**, so every admin-only row
  behaviour defined there was unreachable. It is now loaded for signed-in
  administrators alongside `app.js`.

### Added

- **An OCR button on PDF rows**, shown to signed-in administrators when OCR is
  available. It counts up while working — a control that sits silent for two
  minutes reads as broken — reports how much text was found, and stays marked
  so you can see which documents are done. A PDF that already contains text is
  reported as already searchable rather than reprocessed.
- **A step-by-step OCR guide** in the readme: how to check what the server has,
  what to ask a host to install, where Folio looks for a per-account virtual
  environment, how to choose languages, and what to do when OCR reads a
  document badly.

## 1.6.1 — 4 August 2026

Interface fixes, and a site icon you can actually change.

### Fixed

- **Every metadata edit form was permanently open.** `.meta-form` set
  `display: flex` in a class rule, which silently overrides the `hidden`
  attribute — `hidden` is only a weak user-agent style. The listing rendered
  every row fully expanded, with truncated inputs squeezed into the narrow
  first column. A `[hidden] { display: none !important }` guard now covers
  every element that toggles this way, six of which were affected.
- **The listing was starved of width.** The hover-preview column reserved a
  proportional share whether or not it was showing anything, leaving the table
  44% of the page: long titles wrapped over four lines and tags stacked one
  per row. The reservation is now capped, giving the listing the majority —
  at 1280px the table went from 564px to 895px, and the name column from
  145px to 423px.
- **Rows with an open form put the size, date and buttons in the vertical
  middle** of a tall empty row. Table cells are top-aligned so they stay level
  with the title.

### Added

- **A site icon you can change.** Put `favicon.svg`, `.png`, `.ico`, or
  `apple-touch-icon.png` in a `branding/` folder at the root and Folio uses
  them, with no configuration. `branding/` is not release-owned, so an upgrade
  cannot overwrite your icon — replacing the file inside `assets/` would be
  undone by the next update, which is why it never appeared to work. The
  optional `SITE_ICON` setting points somewhere else. Icon markup is now
  emitted from one place rather than repeated across sixteen page templates.
- Diagnostics reports which icon is in use and where it came from.

## 1.6.0 — 4 August 2026

Document URLs are now permanent. They no longer follow the filename or the
folder, so renaming or reorganising the library over FTP does not break links.

### Added

- **Permanent document identity.** Every document gets an internal
  `document_id` that survives renaming, moving, and slug changes. Metadata is
  keyed by that identifier; the file path is one mutable property of the
  record rather than the identity itself.
- **Administrator-controlled URL slugs.** A **URL slug** field in the metadata
  editor sets the permanent public address. It is independent of the physical
  filename and folder.
- **Automatic 301 redirects.** Changing a slug keeps the previous one as an
  alias that redirects permanently. Aliases are flattened, never chained: a
  document renamed A → B → C sends both A and B **directly** to C in one hop.
  Reverting to an earlier slug removes it from the alias list, so no URL is
  ever both canonical and redirecting.
- **FTP rename and move reconciliation.** When a saved path stops resolving,
  Folio can match the record to a file by SHA-256 content hash and update only
  the path — keeping the URL, title, transcript, and every other field. A
  match is accepted only when exactly one file has those contents and exactly
  one record wants it.
- **Manual relinking** for the cases content cannot settle, such as a file
  that was renamed *and* edited. The administrator chooses the file; only the
  path and fingerprint change.
- **Catalogue diagnostics** listing documents whose file is missing, files not
  yet catalogued, and how many can be reconnected automatically.

### Changed

- Canonical URLs, sitemap entries, structured-data identifiers, `og:url`, and
  every internal link now use the saved slug. Aliases never appear in any of
  them; they only ever redirect.
- Older path-derived URLs continue to work and redirect to the current
  canonical address, so existing links and search-engine results survive.
- Legacy path-keyed metadata is migrated on first write, preserving every
  field **and the URL each document already answered on** — a migration that
  silently changed every address would undo years of indexing. The pre-
  migration file is kept as a dated backup.

### Security

- Slugs are validated against reserved routes and against every other
  document's slug and aliases. A pasted web address is refused outright rather
  than normalised, so a mistake is reported instead of hidden.
- Redirect destinations are built only from saved server-side metadata. No
  request input reaches a `Location` header, and a slug can never carry an
  external destination.
- A canonical slug always wins over a stale alias, so a live page can never
  redirect away from itself. Two independent guards enforce this.
- Reconciliation and relinking require an administrator session and a CSRF
  token, reject paths outside `uploads/`, and honour `EXCLUDE_PATTERNS`.
- **Folio still performs no physical file operation.** It does not upload,
  rename, move, replace, or delete anything, and creates no folders. The
  regression suite fails the build if such a call is introduced.

## 1.5.0 — 3 August 2026

Folio now notices the command-line utilities a server already provides and
uses them. Nothing here is required: with none installed, behaviour is
unchanged.

### Added

- **Server utilities are detected automatically.** `ocrmypdf`, `tesseract`,
  `pdftotext`, `pdfinfo`, `pdftocairo`, `pdftoppm`, `qpdf`, `pngquant`,
  `exiftool`, and `unpaper` are looked for on each install and used where
  they help. Diagnostics lists which were found, their absolute paths, and
  what each one enables, so a missing tool is a plain statement rather than
  a mystery.
- **OCR for scanned documents.** A scanned PDF carries no text, so it cannot
  be searched or indexed. Where OCRmyPDF and Tesseract are present, an admin
  can produce a searchable copy from the file's own page. The original is
  read and never written; the searchable copy is cached under `data/ocr/`.
  Pages that already contain text are left alone, and a PDF that already has
  a text layer is reported as such instead of being needlessly reprocessed.
- **Text extraction and caching.** `pdftotext` output is cached under
  `data/text/`, keyed to the source file's modification time and size, so
  replacing a document over FTP invalidates it automatically.
- **PDF previews without Ghostscript.** Where Poppler is installed, PDF pages
  are rendered with `pdftocairo` or `pdftoppm` instead of ImageMagick's
  Ghostscript delegate. This is both safer and works on hosts whose
  ImageMagick `policy.xml` forbids PDF — a common hardening measure that
  previously disabled PDF previews entirely.
- **`OCR_LANGUAGES`** selects OCR languages, defaulting to English, Malay,
  and Arabic. Only datasets actually installed are used; the rest are named
  in Diagnostics so you know what to ask your host for.
- **`pngquant`** shrinks PNG derivatives when available.

- **A second OCR route that needs neither OCRmyPDF nor Ghostscript.** Where
  OCRmyPDF is absent, Poppler renders each page, Tesseract writes a searchable
  single-page PDF, and qpdf joins them. `qpdf` is needed only for multi-page
  documents, so a single-page scan works without it. OCRmyPDF, when present,
  is invoked with `--output-type pdf` so it never attempts the PDF/A
  conversion that would want Ghostscript.
- **`PDF_ALLOW_GHOSTSCRIPT`**, default false. Folio does not use Ghostscript
  and does not need it: PDF pages render through Poppler, and both OCR routes
  work without it. ImageMagick's PDF delegate is reached only if this is
  explicitly enabled. With it off and no Poppler installed, PDF previews are
  not generated and the original file is served — a missing feature, not an
  error.
- **Account-local tools are found without configuration.** A shared host will
  not let a user write to `/usr/bin`, so tools installed for one account go
  into a virtual environment or `~/.local`. Folio now searches those too:
  `~/.local/bin`, `~/bin`, `~/ocrmypdf-venv/bin`, `~/venv/bin`, cPanel's
  `~/virtualenv/<app>/<version>/bin`, and any `*-venv` directory. The account
  home is derived both from the process owner and from Folio's own location,
  because those are not always the same. Diagnostics reports which
  directories were searched when a tool is missing.
- Every utility has a defined fallback, recorded as a table in
  `docs/ssot.md` and in the readme. A server with none of them installed runs
  Folio exactly as it ran before this release.

### Security

- Utilities are run through `proc_open()` with an argument array and
  `bypass_shell`, so no shell is spawned and a filename containing shell
  metacharacters arrives at the program as ordinary characters. This is what
  makes it safe for Folio, whose filenames come from FTP, to call external
  programs at all. The regression suite fails the build if `shell_exec`,
  `exec`, `system`, `passthru`, or backticks appear in executable code, or if
  `proc_open` is used without `bypass_shell`.
- Only the directories in `TOOL_SEARCH_PATHS` are searched. `$PATH` is
  inherited from whatever started PHP and is not something to trust when
  deciding which binary runs. A utility that is not found resolves to `null`
  rather than a bare name, so a failed lookup can never become a
  `$PATH`-resolved execution.
- Every invocation has a timeout and an output cap, so a malformed document
  cannot hang a request or exhaust memory.
- The OCR endpoint is admin-only, CSRF-protected, and refuses paths outside
  `uploads/` and anything matching `EXCLUDE_PATTERNS`, like every other
  action.
- The regression suite fails the build if `PDF_ALLOW_GHOSTSCRIPT` stops
  defaulting to false, if an ImageMagick PDF read appears without that guard,
  or if any utility becomes mandatory.

## 1.4.2 — 3 August 2026

Documentation accuracy. No functional change.

### Fixed

- **Install and diagnostic messages referred to a file that no longer
  ships.** `.htaccess` has been active in the package since 1.2.0, but the
  installer, the Diagnostics screen, and `readme.txt` still told you to
  "rename `htaccess.txt` to `.htaccess`". Worse, that instruction pointed at
  the wrong problem: when `.htaccess` is genuinely missing it is almost always
  because the FTP client skipped the dotfile, not because anything needs
  renaming. All three now say so.
- **Four fixes shipped in 1.4.0 were missing from its changelog** — the
  analytics bootstrap being blocked by the Content-Security-Policy, the Matomo
  port being dropped from the policy origin, resource ceilings on blurred PDF
  previews, and restricted PDFs being excluded from the thumbnail route. The
  code was correct; only the record was incomplete. They are documented under
  1.4.0 where they belong.

### Changed

- The version-sync table in `docs/ssot.md` now lists all six locations that
  carry the version, with the exact string in each, plus a shell snippet that
  reports any that are stale. It previously listed three, omitting `readme.md`
  and `security.md` — the two that had actually drifted in the past.
- `docs/upgrading.md` separates the 1.3.0 and 1.4.0 steps. The three PDF
  access control steps belonged to 1.3.0 but sat under the 1.4.0 heading, so
  anyone already on 1.3.0 was walked through work they had done.

## 1.4.1 — 3 August 2026

### Removed

- **`nginx.conf.example`.** Nginx was never actively maintained as a
  deployment target; keeping a config file for it implied a level of
  support that wasn't real. Folio already fails safe on any server that
  can't confirm its rewrite is active — see "PDF access control" — so
  dropping this file changes documentation, not behaviour: an Nginx
  install already fell back to query-string URLs and unenforced
  `pdf_access` before this, and still does. Requirements, install steps,
  and the PDF access control docs across `readme.md`, `readme.txt`,
  `security.md`, `docs/install.md`, and `docs/ssot.md` now state Apache or
  LiteSpeed is required, rather than carving Nginx out as a special case
  only for the PDF feature.

## 1.4.0 — 3 August 2026

### Added

- **Cached derivative images.** When the Imagick or GD extension is present,
  Folio generates small WebP copies for listings, hover cards, and detail
  pages instead of sending the full-size original. A hover over a 12 MB scan
  previously downloaded 12 MB. Derivatives live in `data/thumbs/`, are keyed by
  the source file's modification time and size so replacing a file over FTP
  invalidates them automatically, and can be deleted at any time.
- **Formats browsers cannot display are converted for viewing.** TIFF, HEIC,
  HEIF, and AVIF now appear as images with a converted preview rather than as
  unknown downloads. The original is untouched and is what the direct link and
  download give you.
- **Optional server-side PDF previews** at `PDF_SERVER_PREVIEW`, rendering page
  one through Imagick. Off by default: the Ghostscript delegate has a poor
  security record and the in-browser reader already previews PDFs without it.
- Diagnostics reports which image engine is active, which formats it can read,
  whether the cache directory is writable, and the state of PDF preview
  support.

### Fixed

- **Analytics was collecting nothing.** Matomo and GA4 both need a short
  inline bootstrap, and the Content-Security-Policy admits no inline script.
  The external tracker file loaded, the inline block was refused, and `_paq`
  or `dataLayer` stayed empty — a silent failure that looks exactly like a
  working installation. Each bootstrap is now admitted by its own `sha256`
  hash, derived from the same string that is emitted, so the policy stays as
  strict as it was and the two cannot drift apart.
- **A Matomo URL with a non-default port was refused.** The policy origin was
  built without the port, so a self-hosted Matomo on, say, `:8443` had its
  script blocked. The port is now carried through.
- **An excluded folder was still visible.** A pattern such as `_drafts/*`
  hid the folder's contents but not the folder itself, so an empty row, a
  working link, and a `CollectionPage` entry in the structured data all
  disclosed that it existed. Patterns now hide the folder they describe, and
  everything beneath an excluded folder is excluded however deeply nested,
  without needing a glob. `_draftsman.pdf` is still shown when `_drafts/*` is
  excluded: the match is on path segments, not string prefixes.
- **`uploads/.htaccess` was missing from the package** although `readme.md`,
  `security.md`, `docs/install.md`, and `docs/ssot.md` all describe it as
  shipped. It's the second layer that forces active formats to download and
  stops anything in the uploads folder from executing; without it that
  protection rested on PHP alone. Restored, along with `.gitignore`.

### Security

- Rasterising a PDF for a blurred preview now runs under the same memory,
  time, and thread ceilings as every other conversion. Without them a large or
  crafted document could exhaust the host through Ghostscript.
- A PDF restricted to `viewer` or `hidden` has no thumbnail. Access gating and
  derivative generation are separate features that meet at the thumbnail
  route, which carries no signature; without this it would be an unguarded
  second door to exactly what the gate protects. Enforced in both the URL
  helper and the endpoint, since anyone can type a URL.
- Derivative generation reads image dimensions before any pixels and refuses
  anything beyond `IMAGE_MAX_PIXELS`, so a small file declaring enormous
  dimensions cannot exhaust memory. Memory, time, and thread ceilings apply to
  every conversion.
- The derivative route accepts only the widths Folio offers, so the cache
  cannot be filled by requesting arbitrary sizes, and it enforces the same path
  containment and exclusion rules as every other delivery route.
- Generated derivatives are stripped of metadata, so EXIF GPS coordinates and
  camera serial numbers are not republished in public thumbnails.

## 1.3.0 — 3 August 2026

### Added

- **Per-file PDF access control** (`pdf_access`: `public` / `viewer` /
  `hidden`), enforced through the existing `?action=raw` endpoint as the sole
  gatekeeper for PDF bytes. `public` is unchanged. `viewer` embeds the PDF
  through a short-lived HMAC-signed URL and drops the direct-link and
  flip-view download affordances. `hidden` serves no PDF bytes by any path
  (preview, flip view, print, or direct link), and shows a redacted or
  automatically blurred first-page image instead where available.
- `FOLIO_URL_SIGNING_KEY`, a config constant dedicated to signing these
  short-lived URLs — deliberately separate from `FOLIO_AUTH_PEPPER`, so
  rotating one never affects the other.
- A PDF-routing preflight on the Crawlers screen (`PDF_GATE_CONFIRMED`) that
  verifies requests to a real file reach `?action=raw` before `viewer`/
  `hidden` are treated as enforced. Until confirmed — on any server, not
  only Nginx — every PDF behaves as `public` regardless of its stored
  setting, with a visible warning on Diagnostics and in each file's editor,
  rather than presenting a false sense of restriction.
- Two new optional metadata fields: `transcript` (manual or corrected-OCR
  text, rendered server-side in the detail page so it's crawlable and
  AI-readable regardless of `pdf_access`) and `document_type` (a controlled
  vocabulary — certificate, letter, card, article, and so on — distinct from
  the existing free-form `category`).
- An optional `language` metadata field.
- Dublin Core Terms mirrored alongside the existing Schema.org JSON-LD on
  file pages (`dcterms:title`, `dcterms:type`, `dcterms:subject`,
  `dcterms:date`, `dcterms:format`, `dcterms:language`, `dcterms:modified`,
  conditional `dcterms:hasFormat`), additive to the current graph.
- Automatic blurred first-page previews for `hidden` PDFs where Imagick with
  a Ghostscript delegate is available on the host, detected the same way
  Diagnostics already detects pdf.js. An optional `placeholder_image` field
  covers hosts without Imagick: point it at any image already in `uploads/`.
- `llms.txt` entries for `viewer`/`hidden` files note that a full
  transcription is available on the record page, so AI crawlers aren't
  spending a request on a PDF they can't reach.
- `detected_mime_type()`, an `finfo`-based content-sniffed MIME type used
  only for `encodingFormat`/`dcterms:format`. The existing extension-based
  map stays authoritative for routing, previews, and sitemap inclusion.

### Fixed

- **The analytics tracker was blocked by the site's own security policy and
  collected nothing.** Matomo/GA4's inline bootstrap needs a `<script>`
  block the Content-Security-Policy didn't allow, since permitting it with
  `'unsafe-inline'` would have switched off inline-script protection
  site-wide. Each exact inline block is now allowed individually by its
  sha256 hash (`analytics_csp_sources()`), computed from the same strings
  that are emitted, so the two cannot drift apart. A self-hosted Matomo on a
  non-default port is also now carried through correctly in the CSP
  origin, which previously matched only the scheme's default port.

### Explicitly out of scope

- **No Nginx support for this feature.** `pdf_access` enforcement depends on
  a rewrite rule forcing PDF requests through PHP; only the Apache path
  (`.htaccess`) ships one. Nginx installs are unaffected in the sense that
  they are never silently insecure — the routing preflight above ensures
  unenforceable settings fall back to `public` rather than pretending to
  restrict anything. This is a deliberate, final decision, not an oversight.

### Unchanged

- Sitemap `<loc>` entries, the file page's `robots` meta tag, and `llms.txt`
  continue to reference only the record page, never the raw file, regardless
  of `pdf_access`. This already held true before this feature and nothing
  above changes it.

## 1.2.0 — 1 August 2026

### Added

- **Analytics screen** at `?action=analytics`, supporting Matomo and Google
  Analytics 4. Folio itself records nothing: no visit log, no IP addresses, no
  geolocation. Both providers are external and you read the reports in their
  own dashboards. Leaving both blank runs the site with no analytics at all.
- Matomo options for honouring Do Not Track (on by default) and cookieless
  tracking; GA4 option for IP anonymisation (on by default).
- **Admin sessions are excluded by default**, so your own browsing does not
  distort the figures. A checkbox includes them for anyone who wants it.
- The Content-Security-Policy widens to exactly the origins a configured
  provider needs, and to nothing else. With no analytics configured the policy
  is identical to a build without the feature.

### Documentation

- `docs/upgrading.md` rewritten for this release with an explicit **delete
  list**. 1.2.0 renames fifteen files, and uploading the new ones without
  removing the old leaves both on any case-sensitive server: `README.md`
  beside `readme.md`, a stale `lib/Parsedown.php` that is never loaded again.
  The guide previously said `lib/` and `.htaccess` were unchanged, which is no
  longer true.
- Duplicate and stale sections in the upgrade guide consolidated: two separate
  rolling-back sections, and a generic file list that contradicted the
  version-specific one.

- **Each vendored library now has its own folder.** Parsedown sat loose in
  `lib/` while PDF.js had `lib/pdfjs/`; it now lives in `lib/parsedown/` with
  its licence and a `VERSION` file, matching the structure PDF.js already used.
  Diagnostics reports the version of both, and says plainly when either is
  missing.
- **Every licence file is now `license.txt`**, at the root and inside each
  `lib/` subfolder. Where a folder carries several, they are
  `license-<component>.txt`. Previously the package shipped three conventions
  at once: `LICENSE`, `LICENSE.txt`, and `LICENSE_COMPONENT`. Only filenames
  changed; no licence text was altered.
- **Filenames are lowercase throughout**, including `readme.md`,
  `changelog.md`, `security.md`, `license.txt`, and everything in `docs/`.
  Vendored files under `lib/` keep their upstream names.
- The duplicated security summary in `readme.md` is gone. `security.md` is now
  the single place describing what Folio enforces, which readers reach through
  the pointer that remains in `readme.md`.

- **`docs/ssot.md`**, a single source of truth: version, complete file
  inventory split into release-owned and installation-owned, every setting
  with its default and where it can be edited, every endpoint, and the
  invariants a change must not break.
- **Theme architecture** documented in `readme.md`: the seven CSS custom
  properties a theme defines, how to add one, the two-leaf layout, the
  serif-for-reading and sans-for-apparatus rule, and why the flip reader keeps
  a separate stylesheet.
- `readme.txt` moved from `docs/` to the root, so the plain-text entry point
  sits beside `readme.md` where people look for it.
- The admin documentation viewer gains a **Reference** tab for `ssot.md`.

### Fixed

- The `.htaccess` and `nginx.conf.example` rules that stop documentation being
  served over HTTP matched uppercase filenames only, so the lowercase rename
  would have left `readme.md`, `changelog.md`, and `license.txt` publicly
  fetchable. Both rules are now case-insensitive and cover `security.md`,
  `install.md`, `upgrading.md`, and `ssot.md` as well. The Nginx rule also
  still referenced the `-htaccess.txt` templates removed in 1.1.0.

- **Category chips did nothing useful on the listing.** They carried no filter
  hook, so clicking one navigated away to the archive page while tag chips
  beside them filtered in place. They now filter the current folder like tags
  do, while remaining real links to their archive pages for crawlers and for
  anyone who opens them in a new tab.
- **The hover preview embedded the library inside itself.** The card framed the
  PDF and appended a fragment to the URL, so an empty URL resolved against the
  current page and rendered the whole site in miniature. PDFs are now drawn as
  a real first-page thumbnail, which also avoids downloading an entire document
  on hover and works in browsers with no PDF plugin.
- The side preview pane had the same latent flaw and now reports that a file
  has no preview instead of framing the library.
- **The PDF preview could hang forever** on "Loading first page". A fifteen
  second watchdog now reports the failure and points at the buttons below.
- **Two buttons linked to the same file.** The PDF page offered both "Open the
  PDF" and "Direct link"; the duplicate is gone and the action row is now
  consistent across every file type.
- Settings whose names contain digits, such as `GA4_MEASUREMENT_ID`, were
  silently discarded when loaded from `data/settings.php`. The name filter
  accepted only letters and underscores.

## 1.1.0 — 1 August 2026

### Action required when upgrading

- **Upload the new `.htaccess`.** It now ships as a real dot-file with clean
  URLs already active, replacing the old `htaccess.txt` you had to rename by
  hand. If you customised yours, merge rather than overwrite.
- `htaccess.txt`, `uploads-htaccess.txt`, and `data-htaccess.txt` are gone from
  the package. Delete them from your installation.

Nothing else changes on upgrade. Your `config.php`, `data/`, and `uploads/` are
untouched, and an installation that keeps an older `.htaccess` keeps working on
query-string URLs rather than breaking.

### Fixed

- **The inline PDF viewer is back on the document page.** 1.0.1 replaced the
  embedded viewer with a static first-page image because Chrome on Android and
  older Safari on iOS cannot display a framed PDF, which also removed inline
  reading on every desktop browser that can. Folio now asks the browser through
  `navigator.pdfViewerEnabled`: where a built-in viewer exists the PDF is
  embedded and is scrollable, searchable, and printable in place; where it does
  not, the first-page render is used exactly as before. Flip view is a
  secondary action alongside the direct link rather than the primary one.
- **Every link pointed at `http://localhost` unless `SITE_URL` was set in
  `config.php`.** This broke navigation, the PDF reader, the flip-view reader,
  and the admin simultaneously on any installation that skipped that setting.
  The canonical URL is now derived from the request when unconfigured, with the
  Host header validated against a strict hostname pattern so header injection
  is still rejected. Setting `SITE_URL` explicitly remains recommended and
  continues to take precedence.

### Changed

- **Clean URLs need no configuration at all.** The installer no longer writes
  `PRETTY_URLS` into `config.php`, and `config-sample.php` ships it commented
  out, so detection governs on a fresh install and the site comes up on clean
  URLs without anyone editing a file. Setting the constant still forces a mode
  for hosts that need it, such as Nginx.
- **`SITE_URL` is optional rather than required.** The installer still fills it
  in automatically, and pinning it remains recommended for production, but an
  installation without it now derives the address from the request instead of
  breaking.
- **Apache configuration ships as `.htaccess`, ready to use.** No renaming, and
  clean URLs are enabled out of the box rather than requiring a manual edit.
- **Clean URLs are now detected rather than assumed.** The shipped `.htaccess`
  sets `FOLIO_REWRITE` from inside its `<IfModule mod_rewrite.c>` block, so the
  signal is present only when that file is installed and the module is loaded.
  Folio emits clean URLs when it sees it and query-string URLs otherwise, so a
  host without mod_rewrite, or an upgrade that keeps an older `.htaccess`,
  degrades quietly instead of serving links that 404. Pinning `PRETTY_URLS` in
  `config.php` overrides the detection either way.
- **The admin login is a dropdown in the header again**, replacing the separate
  login page. The page remains available at `?action=login` for installations
  that hide the Admin link.
- The header login form is authenticated with a stateless signed token rather
  than a session-backed one, so anonymous visitors still receive no session
  cookie and public pages stay cacheable.

### Added

- `FOLIO_VERSION` constant as the single source of truth for the release.
- Diagnostics reports the Folio version and whether clean URLs are active,
  naming the reason when they are not.

## 1.0.1 — 31 July 2026

### Security

- **Fixed HTML injection through JSON-LD structured data.** Document titles,
  descriptions, categories, and tags are user input and were written into a
  `<script type="application/ld+json">` element with `JSON_UNESCAPED_SLASHES`,
  so a value containing a closing script tag could end the element and inject
  markup into the page. JSON-LD is now encoded with `JSON_HEX_TAG`,
  `JSON_HEX_AMP`, `JSON_HEX_APOS`, and `JSON_HEX_QUOT`, and an encoding failure
  emits an empty graph rather than a broken element. Covered by a regression
  test that fails against the previous behaviour.
- **Logging out now requires a POST with a valid CSRF token.** Previously a
  plain `GET` ended the session, so any third-party page could sign an
  administrator out. Opening the old URL now returns `405` with a confirmation
  form instead of acting.
- **Login throttling is safe under concurrent requests.** The counter was read
  before the exclusive lock was taken, so parallel attempts overwrote each
  other's increments and a burst of guesses cost far fewer than one attempt
  each. The whole read-modify-write now happens while the lock is held. The
  read also no longer uses `filesize()`, whose stat cache could return a stale
  size and silently truncate the counter.
- **The installer emits the same hardened headers as the application**: a
  strict Content-Security-Policy with no `unsafe-inline`, `frame-ancestors
  'none'`, `nosniff`, a referrer policy, and `no-store` caching, since its
  pages display one-time tokens and generated secrets. Its two inline style
  attributes were replaced with classes so the policy needs no exceptions.

### Changed

- **Removed the Bing sitemap ping.** Microsoft retired anonymous sitemap
  submission in May 2022 and the endpoint answers `410 Gone`; Google retired
  its own in 2023. The button reported success while doing nothing. The
  Crawlers screen now explains the methods that do work: the sitemap reference
  in `robots.txt`, Bing Webmaster Tools and Google Search Console for manual
  submission, and IndexNow for immediate notification.
- **Site footer on every public page.** The listing, file detail, category
  archive, and standalone pages now carry a shared footer with the site name,
  optional publisher and publisher link, the year, and navigation to Library,
  each enabled standalone page, Sitemap, and Admin. Kept out of admin screens
  and the flip reader on purpose. Shared viewport-height variables ensure the
  footer sits on-screen rather than being pushed below the fold by a
  viewport-tall listing.
- **PDF preview now renders on mobile.** Mobile browsers refuse to render
  PDFs inline via `<iframe>` and show a "content blocked" placeholder, so the
  file detail page could not preview a PDF on Chrome for Android or Safari on
  iOS. The preview is now the first page rendered to `<canvas>` with the
  already-vendored pdf.js, which works on every browser. Tapping the preview
  enters the flip reader; a broken or password-protected PDF shows a specific
  message instead of a blank space, with the read and download buttons still
  available. Print of a PDF now opens it in a new tab so the browser's own
  viewer can handle printing.

- **Sitemaps are partitioned.** The protocol allows at most 50,000 URLs per
  file. Beyond that, `sitemap.xml` becomes a sitemap index and the URLs are
  served in numbered parts at `/sitemap-1.xml`, `/sitemap-2.xml`, and so on
  (`?action=sitemap&part=N` without clean URLs). Libraries below the limit are
  served exactly as before, as a single `urlset`. Invalid or out-of-range part
  numbers return 404 rather than silently serving something else.
- **Sitemap `lastmod` reflects metadata changes.** Dates previously came only
  from the file's modification time, so retitling a document or changing its
  description never told search engines the page had changed. Metadata records
  now carry an `updated_at` timestamp and each page reports the later of the
  two. Editing one document does not change any other entry. Records written
  before this release have no timestamp and fall back to the file date, so
  existing metadata keeps working untouched. Folder and category entries take
  the newest date among their contents, and standalone pages are stamped only
  when their content actually changes.
- **IndexNow submissions are batched.** The protocol rejects requests carrying
  more than 10,000 URLs; the whole library was previously sent in one request.
  URLs are now split into batches of at most 10,000, each reported on
  independently so a partial failure is never described as a whole-library
  success.

## 1.0.0 — 30 July 2026

First release.

### Library

- Directory listing with subfolder navigation and breadcrumbs.
- Inline preview and printing for PDF, image, and Markdown files.
- Plain text and all other formats listed with a download link.
- Files managed entirely over FTP; the application never writes to them.

### Cataloguing

- Editable title and short description for every file, stored in
  `data/metadata.json` through a locked atomic transaction with a
  last-known-good backup; a legacy `uploads/.sfm-meta.json` is read once for
  migration.
- Raw filenames and extensions hidden from visitors.
- One category and up to ten tags per file.
- Category archive pages at `/category/<slug>/`, gathering documents from every
  folder, with their own indexable URLs and collision-safe slugs.
- Tag chips filter the current view in the browser.
- Client-side listing search: when a folder has three or more files, a search
  field appears in the header and filters rows in real time across titles,
  descriptions, categories, tags, and filenames. Composes with the tag and
  category chips. Esc clears the field.
- `EXCLUDE_PATTERNS` in `config.php`: shell-style globs that hide matching
  files and folders from the listing, sitemap, category archives, IndexNow
  submissions, and detail-page URLs. Excluded entries return a real 404 if
  requested directly and are reported on the Diagnostics screen.

### Addressing

- Page URLs are the filename slugified, including the extension so that files
  differing only by type never collide; a short hash is appended only when
  normalised names still collide.
- Files are served from their real location in `uploads/`, directly by the web
  server rather than proxied through PHP.
- Requests carrying an unambiguous legacy slug or the former `?action=raw`
  endpoint are 301-redirected; ambiguous ones return 404.
- Optional clean URLs through mod_rewrite; query-string URLs by default.

### Discovery

- llms.txt endpoint: a curated Markdown map of the library for AI crawlers,
  generated from titles, descriptions, and categories, with an optional
  introduction.
- Crawlers screen in the admin: indexability switch, sitemap and llms.txt
  toggles, and a robots.txt generator reflecting the current settings. When the
  site is marked non-indexable, the sitemap and llms.txt return 404 and public
  pages emit `noindex, nofollow`.
- Canonical URLs, Open Graph, and Twitter Card tags on every page, built from a
  configured `SITE_URL` rather than the request Host header.
- schema.org JSON-LD graph: `WebSite`, publisher, `BreadcrumbList`,
  `CollectionPage`, a lightweight `ItemList` on listings, and a typed node for
  each file on its own page. Empty publisher nodes and unknown publication dates
  are omitted rather than fabricated.
- XML sitemap covering folders, file pages, and category archives, with Google
  image extensions.
- Sitemap preview in the Crawlers screen: total URL count and a sample of the
  entries that will be included.
- One-click Bing ping to refetch the sitemap. Google's ping endpoint was
  deprecated in mid-2023 (submissions now return 404), so it is deliberately
  not offered; use Search Console or IndexNow instead.
- IndexNow support: generate a key, host it automatically at `/{key}.txt` on
  the site root, and submit every URL in the library in one click. Compatible
  engines include Bing, Yandex, and Naver; Google does not participate.
- Clean-URL preflight in the Crawlers screen: the **Test rewrite** button
  probes a fake pretty URL through your `.htaccess`. The **Enable clean URLs**
  button only appears if the probe succeeds, so the setting cannot silently
  take the site down.
- `robots.txt` opening the library to search engines and AI crawlers.

### Standalone pages

- Optional informational pages alongside the library: an About slot, a FAQ slot,
  and three named custom slots. All disabled on a fresh install, so Folio stays
  a pure library until you fill one in.
- Admin editor at `index.php?action=pages`, linked in the header. Each slot has
  an enabled toggle, a title, an optional menu label, and a Markdown body.
- Stored privately in `data/pages.json` through the same atomic transaction
  (lock, temp, rename, backup) as settings and metadata; never in `uploads/`.
- Rendered through Parsedown in safe mode: raw HTML in a page body is escaped,
  so a stored-content XSS route stays closed.
- Clean URLs `/about/`, `/faq/`, and `/p/<slug>/` when `PRETTY_URLS` is on;
  `?page=<slot>` otherwise. Disabled or unknown slugs return a real 404.
- Enabled pages appear in the header nav for public visitors and are added to
  the XML sitemap when the site is indexable.
- Correct `schema.org` per slot: `AboutPage` for About, `FAQPage` for FAQ with
  `Question` and `Answer` entities parsed from `##` headings, `WebPage` for
  custom slots. Canonical URL, Open Graph, and Twitter Card tags on every page.

### Interface

- Codex-inspired layout: listing and preview as two leaves divided by a gutter.
- Garamond stack for text, sans for the apparatus, hairline rules throughout.
- Responsive layout: on narrow screens the listing collapses from a table into
  stacked cards with labelled size and date and wrapping actions, so rows never
  scroll sideways; the preview drops below the listing.
- Hover preview cards: on wide, hover-capable screens, pointing at a file in the
  listing shows a preview of it in the right column — the image itself for
  pictures, a first-page view for PDFs, and a titled tile for other formats.
  Keyboard focus previews too; touch and narrow screens are unaffected.
- PDF flip-view reader at `index.php?action=flipbook`, reached from a **Flip
  view** button on PDF rows. Renders real PDF pages to canvas with Mozilla's
  pdf.js and turns them with a page-flip transition. Navigation by buttons,
  arrow keys, Home and End, a direct page-number field, or by clicking the
  left and right thirds of the page. Escape returns to the file's detail page.
  Pages are prefetched around the current one and cached with a bounded budget.
  Under `prefers-reduced-motion` the animation is skipped entirely and pages
  simply replace one another.
- The flip reader is a separate screen on purpose: WebAssembly rendering needs
  `wasm-unsafe-eval` and `worker-src` in the Content-Security-Policy, and
  scoping it to this one page keeps the listing and every other screen on the
  stricter site-wide policy.
- Four colour schemes: Folio, Ledger, Garden, Night.
- Site icon in SVG, ICO, and Apple touch formats.

### Security

- Symbolic links, and any path resolving outside `uploads/`, are rejected on
  every endpoint: listing, recursive indexing, detail resolution, sitemap, and
  raw delivery. Recursive indexing tracks visited directories to avoid loops.
- Unknown and active formats (HTML, XML, MHTML, and anything outside the inline
  allowlist) are forced through attachment delivery with a sandboxing
  Content-Security-Policy, both by PHP and by the shipped Apache/LiteSpeed and
  Nginx rules.
- Sessions start only for login, authenticated routes, POST requests, or
  visitors who already hold a Folio session cookie, so anonymous public pages
  stay cacheable.
- Per-account authentication versions mean password changes, resets, and
  account deletion immediately revoke other active sessions.
- Optional `FOLIO_AUTH_PEPPER` in `config.php` HMACs every password hash,
  protecting `data/users.php` in the event of an isolated leak. Existing hashes
  migrate on next successful login.
- Optional `FOLIO_COOKIE_NAME` namespaces the session cookie against other
  applications on the same domain.
- Admin login with a bcrypt password hash and a constant-time comparison.
- CSRF tokens on every POST, hardened session cookies, and login throttling
  after eight failed attempts.
- Content-Security-Policy, `X-Frame-Options`, `Referrer-Policy`, and `nosniff`
  on every page.
- SVG files sandboxed so embedded scripts cannot run; script execution disabled
  inside `uploads/`.
- Markdown rendered by Parsedown in safe mode.

### Accounts

- Accounts screen at `index.php?action=users` for changing your own password,
  adding accounts, resetting passwords, and deleting accounts.
- Accounts stored in `data/users.php`, a PHP file that returns an array and is
  never served; the folder is denied by its own `.htaccess`.
- Credentials fall back to `config.php` until the first account change.
- `SHOW_ADMIN_LINK` in `config.php` hides the Admin link from the header;
  the direct login page at `index.php?action=login` remains available.

### Settings

- Settings screen at `index.php?action=settings` for the site name,
  description, publisher identity, language, and Admin-link visibility.
- Saved to `data/settings.php`, which overrides `config.php`; the settings
  that can take the site down stay file-only.

### Installation

- `install.php` guided installer, protected by a one-time private token read
  over FTP or supplied through the `FOLIO_INSTALL_TOKEN` environment variable.
  A single form covers admin credentials, site identity, and site secrets. It
  runs environment checks, creates `config.php` exclusively with restrictive
  permissions, refuses to run again while `config.php` exists, and shows a
  persistent red banner in the admin if not deleted afterwards.

### Operations

- Diagnostics screen at `index.php?action=diagnostics` (admin only), linked in
  the admin header, reporting environment (PHP version and the 8.4 minimum,
  `mbstring`, required extensions, `uploads/` and `data/` permissions),
  addressing (URL mode, mod_rewrite, `.htaccess`, rewrite probe), and
  configuration health (`SITE_URL`, HTTPS, installer and token removal, password
  pepper, publisher identity, indexability, account store, standalone pages,
  IndexNow key), plus guidance for
  running the `tests/smoke.sh` regression suite.
- Documentation viewer for the admin at `index.php?action=docs`.
- An isolated integration smoke test under `tests/` exercising the security and
  integrity behaviours end to end.
