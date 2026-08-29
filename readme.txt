=== Folio ===
Contributors: menj
Author: MENJ
Author URI: https://menj.blog
Project URI: https://github.com/menj/folio
Requires PHP: 8.4
Requires at least: PHP 8.4
Tested up to: PHP 8.4
Stable tag: 1.60.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Folio turns a web folder into a small public document library with crawlable
file pages, previews, metadata, categories, accounts, sitemap, and llms.txt.
Files remain managed over FTP and no database is required. Optional standalone
pages (About, FAQ, Contact, and your own) sit alongside the library, with a
secure contact form on the Contact page and a Redirect Manager that keeps old
addresses working. Hover preview cards give each row a real thumbnail on
desktop — a short moving clip for video, where ffmpeg is available — and the
layout holds up cleanly at desktop, tablet, and mobile widths.

== Requirements ==

* PHP 8.4 or newer
* JSON, password and random support; mbstring optional (Markdown pages need it)
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

Each file's detail page offers a share menu. Copy link is always available;
X, Reddit, WhatsApp, and email links appear only when that page is public
and indexable, so a restricted or hidden file is never offered a public
share invitation.

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

= 1.60.0 =

Completes Google structured data coverage against the full official list.
Adds ProfilePage for the About page, Speakable for TTS eligibility on
articles and FAQ, Dataset for Google Dataset Search, paywalled content
markup for restricted documents, full image creator/copyright fields for
Google Images, Video rich-result fields (uploadDate required, thumbnailUrl,
duration, Movie subtype), Organisation contact and logo fields, and Event
schema for event documents. New SITE_LICENSE_URL constant for image licence
badges. 8 new tests; suite at 87.

= 1.59.0 =

Adds image redaction. Draw boxes over anything that should not be published
and visitors get a copy with the boxes burned into the pixels — the original
is withheld, and what is underneath cannot be recovered by saving the image or
reading its metadata. Thumbnails use the redacted copy too. If your server
cannot build the redacted copy, the image is withheld entirely rather than
risk publishing the original. Also fixes the video redaction editor always
showing zero regions.

= 1.58.1 =

Documentation only: the roadmap's phase numbers no longer shift when a phase
completes, so a phase number means the same thing permanently. Completed work
stays listed and struck through rather than disappearing.

= 1.58.0 =

Adds access control for images: Public, Restricted and Hidden, the same three
settings PDFs and video already have. Restricted images are delivered through
a short-lived signed link and hidden ones are not served at all. Turn it on
under Crawlers, after the preflight confirms your server routes images through
Folio. Also fixes the 1.55.0 scanner hardening accidentally blocking Folio's
own preflight test files, which stopped the PDF and video preflights working.

= 1.57.0 =

Completes the roadmap's first phase. Adds a redirect tester that shows what an
old address actually does before you rely on it, backup and restore for your
redirect rules, a read-only view of the old document addresses Folio already
redirects after a rename, and captions for audio and video — put a .vtt file
next to a media file over FTP and a captions track appears, with nothing to
configure.

= 1.56.0 =

Adds a Download a copy button to the Catalogue screen, saving everything you
have typed — titles, descriptions, categories, tags, dates and access settings
— as a single file. It is the only part of Folio that cannot be rebuilt from
your files, and previously could only be backed up over FTP. Restore it by
putting the same file back; nothing needs converting.

= 1.55.1 =

Documentation only: refreshes the index.php size figure in the roadmap's
Known issues, which had gone stale.

= 1.55.0 =

Hardens the site against automated vulnerability scans. Blocks dotfiles like
.env and .git/config, editor leftovers like index.php.bak (which would serve
your source code as plain text), version-control folders, and directory
listing. Stops PHP announcing its exact version to scanners. Tested against a
real Apache; certificate renewal is unaffected.

= 1.54.1 =

Stops automated vulnerability scans from loading Folio and filling the 404
Monitor. Requests for .php files and other applications' admin pages are now
refused by the webserver before PHP starts, and are no longer recorded. Adds a
button to remove scan entries already logged, keeping genuine broken links.

= 1.54.0 =

Adds the publisher identity fields to Settings — occupation, biography, other
names, nationality, education, affiliations and a related site. These feed
identity.json and llms.txt and previously required hand-editing config.php.
Also a full audit of every admin screen and setting, and documentation
corrections where settings were described as config-only.

= 1.53.2 =

Fixes the Search title and Search description fields on the Pages screen,
which ran together as inline text instead of stacking like every other field,
and stops field help text being rendered in shouty uppercase.

= 1.53.1 =

Adds the missing publisher email field to Settings — the contact form told you
to set it there, but there was no field. Also fixes a button that turned dark
and hard to read when hovered.

= 1.53.0 =

robots.txt is rewritten: it now explains itself, names the known AI crawlers,
and actually enforces your "no training data" setting by refusing each
training crawler by name. Crawlers that fetch a page live to answer someone's
question, or index for AI search, stay allowed. Adds an optional crawl-delay
setting for small hosts.

= 1.52.2 =

Security fix: a restricted or hidden video's hover preview and thumbnail could
be fetched by anyone who guessed the address. Both now check the video's access
setting. Also adds a Stored caches section to Diagnostics showing what each
cache holds and how much space it uses, with a button to clear any of them —
previously the only way to remove stale previews was over FTP.

= 1.52.1 =

Fixes the contact form shipping without its styles, which left the hidden
anti-spam field visible to real visitors. Also corrects several outdated
descriptions in the documentation and adds proper guides for the contact form
and the Redirect Manager.

= 1.52.0 =

Adds a public Contact page with a working contact form. Enable Contact under
Pages and write an introduction; the form appears beneath it. Messages are
emailed to your publisher address, which visitors never see. Optional
attachments are forwarded with the email and deleted immediately, never
entering your library. Includes honeypot, timing and rate-limit spam
protection, and a test-email button so you can confirm delivery works before
relying on it. Nothing visitors send is stored on the site.

= 1.51.0 =

Adds a Redirect Manager and 404 Monitor at Admin > Redirects. Folio already
keeps URLs alive through renames and moves on its own; these are explicit
301/302 rules for the cases no automatic mechanism can infer, such as a
folder restructured over FTP. A rule is only ever consulted after every
existing mechanism has declined, so it can never shadow a URL that still
works. The 404 Monitor records unresolved addresses (counts only, no visitor
information) and turns one into a rule in a step. Entirely additive: nothing
changes until you create a rule.

= 1.50.26 =

Re-audits docs/upgrading.md's phased roadmap against the current codebase,
item by item across all six phases: everything listed is confirmed still
genuinely absent from the code. Nothing needed reordering. Refreshes the
one figure that had drifted (index.php's line/function count under Known
issues).

= 1.50.25 =

llms.txt now includes the required # Contact section (built from your
configured publisher email, phone, and/or URL) and a Lang: header, per
the llms.txt Specification v1.7.0. The specification attribution moved
from an inline link near the top to the closing footer the spec's own
example shows. A new Diagnostics row flags a missing Contact section as
informational, not an error.

= 1.50.24 =

robots.txt is now generated live, the same way sitemap.xml and llms.txt
already are, replacing the static file and the old copy-into-your-domain-
root manual step. If Folio is installed at your domain root this needs
nothing further; a subfolder install needs one rewrite rule at the domain
root's own config instead of an entire file kept in sync by hand. Never
returns 404, unlike other discovery endpoints, since it is what announces
non-indexability in the first place.

= 1.50.23 =

Fixes the video sitemap's title/description using raw CDATA instead of
proper XML escaping — a title containing "]]>" would have produced
malformed XML. Adds smoke test coverage for the video sitemap and a
security.md paragraph documenting the 1.50.21 X-Robots-Tag fix, neither of
which had any before now.

= 1.50.22 =

Added a video sitemap (sitemap-video.xml): title, description, thumbnail,
and content_loc for every public video, following the video: extension
tags. Announced in robots.txt and llms.txt, and shown on the Crawlers
screen alongside the page, document, and category sitemaps. Empty while
the video-routing guard is on, since a signed content_loc could expire
before the sitemap is next crawled.

= 1.50.21 =

A restricted (non-indexable) library no longer leaves its PDFs telling
Google to index them anyway. The X-Robots-Tag on raw PDF/txt/md responses
now follows SITE_INDEXABLE, the same as every HTML page's robots meta tag
already does.

= 1.50.20 =

llms.txt now carries a Specification line, and identity.json a
_specification object, both pointing at the AI Visibility convention
(https://www.ai-visibility.org.uk/) that each document also follows.

= 1.50.19 =

The desktop Playlist's stage-fill background (added in 1.50.11) is now a
genuine second, playing video of the same clip rather than a static
poster frame — it moves the way the foreground does. Muted, looped, and
deferred until playback actually starts so it costs no extra bandwidth
while paused. Scope is unchanged: only the desktop two-pane Playlist
stage, since the single-file page never has unused stage space to fill.

= 1.50.18 =

Diagnostics no longer flags "Video access control" as Needs attention just
for using the deliberate, documented default (page-level, not webserver-
enforced) model — a new blue "NOTE" tier replaces the amber "CHECK" for
this and any future row describing a chosen trade-off rather than a
problem. A genuinely separate case — enforcement having been confirmed
once and then silently stopped — still correctly warns.

= 1.50.17 =

The Diagnostics "Blurred preview for restricted video" check now runs a
real test against an actual restricted/hidden video when one exists,
showing the exact reason for a failure right on the page instead of only
ffmpeg/Imagick's presence. Also adds a check for whether data/previews/ is
writable, a separate common failure the old check couldn't see. Corrects a
test-count slip from the previous release (32 test groups, not 31).

= 1.50.16 =

Adds diagnostic logging for restricted-video blur previews: every failure
point (missing ffmpeg/Imagick, unreadable source, unwritable cache
directory, an Imagick exception) now logs a specific reason to the PHP
error log, controlled by a new VIDEO_BLUR_DIAGNOSTICS setting. The public
response is unchanged, still a plain 404. Video preview helpers also moved
into their own file, lib/video.php.

= 1.50.15 =

Fills in tests/readme.md's coverage table, which was missing 18 of the
suite's 32 test groups (a pre-existing gap), and adds a short section
documenting the two namespaced HMAC signing payloads (PDF vs video) side
by side.

= 1.50.14 =

Codebase cleanup: removes five dead functions with no callers
(meta_migrate_now, audio/video/media_playlist_for, video_redact_is_on),
fixes stray/duplicated documentation in style.css and index.php,
regenerates assets/manifest.json (it had drifted from the real style.css
and media.js, so the minified builds were silently going unused), and
corrects two tests/smoke.sh assertions that contradicted Folio's actual,
documented "hidden" access policy. Full suite now passes 31/31.

= 1.50.13 =

Fixes the file listing's action buttons (Preview, Edit, Link) sitting
pinned to the far-right page edge with a wide empty gap before them, and
wrapping even when there was clearly room. The listing table now uses
table-layout: fixed with a controlled width for the actions column, so
buttons stay anchored to their row; the name column absorbs the rest.
Wrapping is preserved for rows with several actions.

= 1.50.12 =

Fixes a CSP violation logged on every admin listing page load: a label in
the file-edit form used an inline style="" attribute that the strict CSP
silently dropped. Moved to a stylesheet class. No visual change.

= 1.50.11 =

Fixes a portrait video in the desktop Playlist rendering inside a stage
stretched much wider than the video itself, leaving bare black space on
either side. The video was never distorted; a blurred, scaled copy of its
own poster now fills the stage behind it, while the real video stays
centred and untouched at its correct shape.

= 1.50.10 =

Fixes the restricted-video notice rendering flat with no blur or texture:
the minified stylesheet actually served was stale and missing several of
the rules `style.css` already had. Also drops the Share button entirely
from restricted-video and hidden-PDF pages, rather than showing a
copy-link-only version of it.

= 1.50.9 =

Fixes gated video stalling partway through playback and never recovering.
The function every gated video streams through never raised PHP's
execution-time limit, so a large file or slow connection could outlast a
shared host's default 30-second limit and get killed mid-transfer. Public
video was never affected, since it bypasses PHP entirely.

= 1.50.8 =

menj.blog's icon is now a threshold-traced portrait silhouette from the
site owner's own photo, replacing the 1.50.7 "M" monogram, still in the
same flat currentColor treatment as the rest of the set.

= 1.50.7 =

menj.blog's own footer/identity.json/vcard.vcf icon is now a flat "M"
monogram instead of a personal photo, matching the currentColor treatment
every other logo-less entry already uses.

= 1.50.6 =

Fixes Academia, Tumblr, and Substack profiles in SITE_SAMEAS falling back to
the generic link icon instead of their own, because all three put a profile
at a personal subdomain rather than the bare domain and the matcher only
checked for an exact host match.

= 1.50.5 =

Fixes a fatal parse error introduced in 1.50.4 that could take a site down
entirely. Every .php file in the release now lints clean before packaging.

= 1.50.4 =

Restricted and hidden video now shows a sealed-archive notice — a keyhole
icon, a two-tier label, and an automatically generated blurred frame preview
where the server has ffmpeg and Imagick — instead of a plain one-line notice.
Mirrors the existing hidden-PDF blurred preview: the frame is downscaled hard
before it's blurred, so the result is safe to serve publicly. Reuses the
existing placeholder_image field as a manual fallback rather than adding a
new one. Adds a thin-line UI icon set and an outlined status badge next to
the title of any restricted or private item. Fixes an undefined CSS variable
that had been silently breaking the restricted-video notice's text colour.

= 1.50.3 =

Fixes the Share button dropping onto its own line below Flip view, Print,
and Direct link, caused by invalid HTML (a block element nested inside a
paragraph) rather than the flexbox rules, which were already correct.

= 1.50.2 =

Gravatar, Google Play, Google Scholar, and Acronym Finder are now recognised
by SITE_SAMEAS and get their own icon in the footer, identity.json, and
vcard.vcf, rather than the generic link glyph.

= 1.50.1 =

Fixes IndexNow URL submission not applying the same visibility gates as the
sitemap, so a hidden-tier file could be pushed to search engines even though
it's deliberately excluded from discovery elsewhere.

= 1.50.0 =

Verified social profiles (SITE_SAMEAS) now render as recolourable inline-SVG
icons in the site footer, sourced from the same shared map that already
powers identity.json and vcard.vcf.

Older entries are not mirrored here in full; see changelog.md in the release
for the complete history back to 1.0.0.

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
