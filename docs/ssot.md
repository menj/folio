# Folio — single source of truth

The canonical reference for what Folio is made of. Where any other document
disagrees with this one, this one is correct and the other is a bug.

Version 1.69.2. Update this file in the same commit as any change it describes.

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
| `index.php` | `define('FOLIO_VERSION', '1.69.2');` |
| `changelog.md` | `## 1.69.2 — 2 September 2026` |
| `readme.txt` | `Stable tag: 1.69.2` |
| `readme.md` | `1.69.2.` under `## Version` |
| `security.md` | `The current supported release is **1.69.2**.` |
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
assets/img/social/        single-colour profile icons, recoloured by the active theme
assets/js/*.min.js        minified twins, built by tools/minify.js
tools/minify.js           builds the minified twins; maintainers only
assets/css/flipbook.css   flip reader only
assets/js/app.js          listing behaviour
assets/js/view.js         detail page behaviour
assets/js/media.js        themed audio and video transport
assets/js/library-view.js renders sitemap.html from library.yaml in the browser
assets/js/admin.js        admin screens
assets/js/flipbook.js     flip reader
assets/img/               favicon.svg, favicon.ico, apple-touch-icon.png
lib/parsedown/            Parsedown 1.8.0, MIT
lib/pdfjs/                PDF.js, Apache 2.0
lib/js-yaml/              js-yaml 5.3.0, MIT — renders sitemap.html in the browser
lib/vendor/               Google API client and dependencies, MIT/Apache 2.0 — Google Indexing API
lib/video.php             restricted/hidden video blur-preview helpers, Folio's own code — not
                          third-party, kept out of index.php the same way the lib/ vendor
                          folders already are
lib/redirects.php         Redirect Manager and 404 Monitor — storage, path normalisation,
                          destination validation, loop and chain detection, resolver.
                          Folio's own code, kept out of index.php for the same reason
lib/contact.php           public contact form — validation, anti-spam, rate limiting,
                          attachment checks, and mail delivery. Folio's own code, kept
                          out of index.php for the same reason

tests/smoke.sh            regression suite
tests/asset-version-check.php   asserts every asset is linked with ?v=
tests/wired-check.php     asserts every advertised utility is actually called
uploads/.htaccess         hardening for the served uploads folder
uploads/readme.txt        keeps the folder present in git and on GitHub
data/readme.txt           keeps the folder present in git and on GitHub
data/thumbs/              generated image derivatives; safe to delete
data/video-previews/      generated moving preview clips for video; safe to delete
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
                          entity_relation, entity_org, entity_work, transcript, pdf_access,
                          language, placeholder_image
data/metadata.lock        write lock
data/entities.json        reusable Organization and Book entities; absent until the
                          first one is saved on the Entities screen
data/folder-descriptions.json  folder descriptions, keyed by folder path
data/pages.json           standalone page content
data/redirects.json       explicit 301/302 rules; absent until the first one is saved
data/redirects.lock       write lock
data/notfound.json        404 Monitor counts — path, hits, first and last seen. No IP
                          address or user agent. Capped and self-trimming; safe to delete
data/notfound.lock        write lock
data/contact-rate.json    contact form rate limiting — salted hashes of truncated IP
                          networks with timestamps, never a raw address. Entries
                          expire after an hour and prune themselves; safe to delete
data/contact-rate.json.lock  write lock
data/crawler-log.jsonl    AI crawler tracker hits — one JSON line per hit
                          ({t,b,r}: timestamp, bot, route). No raw
                          user-agent, no ordinary visitor. Pruned
                          opportunistically past CRAWLER_LOG_RETENTION;
                          safe to delete
data/aspect.json          cached PDF page shapes; safe to delete
data/previews/            generated, cached blurred previews for hidden PDFs and
                          restricted/hidden video (distinct hash namespaces, one folder)
data/.obscure-key         key for obscuring a gated video's path in hover-preview
                          URLs; regenerating invalidates only URLs already loaded
                          in an open page, nothing stored
uploads/                  documents
uploads/.folio-pdf-probe.pdf  generated dummy file for the PDF-routing preflight
uploads/.folio-video-probe.mp4  generated dummy file for the video-routing preflight
uploads/.sfm-meta.json    legacy metadata, read once for migration
```

## Requirements

- PHP 8.4 or newer, with JSON, password, and random — mbstring is optional
  (Markdown rendering only; every other feature, breadcrumb capitalisation
  included, degrades gracefully without it rather than failing)
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
| `PUBLISHER_CANONICAL_ID` | empty | Settings |
| `PUBLISHER_IMAGE` | empty | Settings |
| `PUBLISHER_CONTACT_TYPE` | `customer support` | Settings |
| `PUBLISHER_CONTACT_LANGUAGES` | empty | Settings |
| `SHOW_ADMIN_LINK` | `true` | Settings |
| `AUDIO_PLAYLIST` | `true` | Settings |
| `PUBLISHER_NICKNAME` | empty | no — vCard only, never identity.json |
| `PUBLISHER_EMAIL` | empty | Settings — the contact form's recipient, and published in vcard.vcf and llms.txt's Contact section |
| `PUBLISHER_PHONE` | empty | no — vCard only, never identity.json |
| `PUBLISHER_COUNTRY` | empty | no — vCard only, never identity.json |
| `PUBLISHER_BIO` | empty | Settings — identity.json's `Person.description`, replacing the library's own description as the fallback |
| `PUBLISHER_OCCUPATION` | empty | Settings — identity.json's `Person.jobTitle` |
| `PUBLISHER_ALT_NAMES` | empty | Settings — identity.json's `Person.alternateName` |
| `PUBLISHER_NATIONALITY` | empty | Settings — Person `nationality`; comma list, so dual citizenship publishes both |
| `PUBLISHER_ALUMNI_OF` | empty | Settings — identity.json's `Person.alumniOf` |
| `PUBLISHER_AFFILIATION` | empty | Settings — identity.json's `Person.affiliation` |
| `PUBLISHER_RELATED_SITE_URL` | empty | Settings — a second site about the same person, as a named `additionalProperty` |
| `PUBLISHER_RELATED_SITE_LABEL` | empty | Settings — label for the above, defaulting to "Related site" |
| `PUBLISHER_HONORIFIC_PREFIX` | empty | Settings — Person `honorificPrefix`; vCard N prefix |
| `PUBLISHER_GIVEN_NAME` | empty | Settings — Person `givenName`; vCard N |
| `PUBLISHER_FAMILY_NAME` | empty | Settings — Person `familyName`; vCard N |
| `PUBLISHER_BIRTH_DATE` | empty | Settings — Person `birthDate` (ISO, year precision allowed); vCard BDAY |
| `PUBLISHER_BIRTH_PLACE` | empty | Settings — Person `birthPlace` |
| `PUBLISHER_GENDER` | empty | Settings — Person `gender` |
| `PUBLISHER_PRONOUNS` | empty | Settings — Person `pronouns` |
| `PUBLISHER_KNOWS_LANGUAGE` | empty | Settings — Person `knowsLanguage` (comma list); vCard LANG |
| `PUBLISHER_WORKS_FOR` | empty | Settings — Person `worksFor` |
| `PUBLISHER_AWARDS` | empty | Settings — Person `award` (comma list) |
| `PUBLISHER_SPOUSE` | empty | Settings — Person `spouse`, a typed Person node |
| `PUBLISHER_CHILDREN` | empty | Settings — Person `children` (comma list of typed Person nodes) |
| `PUBLISHER_PARENTS` | empty | Settings — Person `parent` (comma list of typed Person nodes) |

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
| `LLMS_MAX_PER_SECTION` | `30` | config.php only |
| `SMTP_HOST` | empty | Settings |
| `SMTP_PORT` | `587` | Settings |
| `SMTP_ENCRYPTION` | `tls` | Settings |
| `SMTP_USERNAME` | empty | Settings |
| `SMTP_PASSWORD` | empty | Settings (blank-means-unchanged on save) |
| `INDEXNOW_KEY` | empty | Crawlers |
| `IDENTITY_ENABLED` | `true` | Crawlers |
| `VCARD_ENABLED` | `true` | Crawlers — also requires `IDENTITY_ENABLED` |
| `YAML_ENABLED` | `true` | Crawlers |
| `FOOTER_LINKS` | `robots,llms,yaml,vcard,json,html,xml` | Crawlers — order and presence of the footer's discovery-file links; a key's own `*_ENABLED` toggle above still governs whether it can be served at all |
| `CRAWLER_LOG_ENABLED` | `true` | Crawlers — whether the AI crawler tracker logs hits at all |
| `CRAWLER_LOG_RETENTION` | `90` | Crawlers — days a hit is kept in `data/crawler-log.jsonl` before opportunistic pruning removes it; clamped to 7–365 |

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
| `FOLIO_URL_SIGNING_KEY` | empty | no — signs "restricted" pdf_access URLs and, once the video gate is confirmed, video URLs too, deliberately separate from `FOLIO_AUTH_PEPPER` |
| `PDF_GATE_CONFIRMED` | `false` | Crawlers, via the PDF-routing preflight — never set by hand |
| `VIDEO_GATE_CONFIRMED` | `false` | Crawlers, via the video-routing preflight — never set by hand |
| `CONTACT_SENDER_EMAIL` | empty | no — what contact mail is sent *as*; empty means `no-reply@` the site's own domain. Never where it goes |
| `AI_ALLOW_TRAIN` | `true` | Crawlers — declared in library.yaml *and* enforced in robots.txt, where each known training crawler is named and either allowed or refused to match |
| `AI_ALLOW_QUOTE` | `true` | Crawlers — declared in library.yaml only; no robots.txt directive expresses it |
| `AI_ALLOW_SUMMARISE` | `true` | Crawlers — as above |
| `AI_ALLOW_COMMERCIAL` | `false` | Crawlers — as above |
| `AI_POLICY_NOTE` | empty | Crawlers — free text, emitted as a one-line comment in robots.txt and in library.yaml |
| `ROBOTS_CRAWL_DELAY` | `0` | no — seconds between requests; 0 emits no directive at all. Google ignores it regardless |
| `CONTACT_ATTACHMENTS` | `true` | no |
| `CONTACT_MAX_ATTACHMENTS` | `3` | no |
| `CONTACT_MAX_FILE_MB` | `5` | no |
| `CONTACT_MAX_TOTAL_MB` | `10` | no — capped further by the server's own `upload_max_filesize`/`post_max_size` |
| `CONTACT_ANTISPAM` | `true` | no |
| `CONTACT_MIN_SECONDS` | `3` | no |
| `CONTACT_RATE_PER_HOUR` | `5` | no |

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
| `?action=video_blur_preview` | blurred frame JPEG for a `restricted`/`hidden` video, generated on demand and cached; never the original file — see § Blurred previews for restricted/hidden video |
| `?action=flipbook` | flip reader, PDF only; refuses `hidden` PDFs outright |
| `?action=sitemap_pdf` | the document files themselves |
| `?action=sitemap_video` | the public video files themselves, `video:` extension tags — empty while the video guard is on, since a signed URL would expire before the sitemap is next crawled |
| `?action=sitemap_categories` | the category archive pages, in their own sitemap |
| `?action=sitemap` | XML sitemap, or index beyond 50,000 URLs |
| `?action=identity` | Schema.org identity document — Person + WebSite, plus any declared Book and Organization nodes (`/identity.json`) |
| `?action=vcard` | Downloadable vCard 3.0 for identity.json's subject (`/vcard.vcf`); requires identity.json enabled, plus its own toggle |
| `?action=yaml` | YAML index of every public document (`/library.yaml`) |
| `?action=sitemap_html` | Human-readable HTML sitemap of library.yaml (`/sitemap.html`; old `/library.html` and `?action=yaml_view` still resolve, redirected) |
| `?action=llms` | llms.txt for AI crawlers |
| `?action=robots` | robots.txt, generated live from current settings — never gated on `SITE_INDEXABLE` or any `*_ENABLED` flag, unlike every other discovery endpoint above, since it is what announces non-indexability in the first place |
| `?action=playlist` | standalone audio or video player for a folder (`&kind=video` for video) |
| `?action=video_preview` | short, silent, looping moving preview clip for a public video's hover/listing thumbnail; requires ffmpeg |
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
| `?action=entities` | reusable Organization and Book entities |
| `?action=image_redacted` | public: an image with redaction boxes burned in; the only image served for a file carrying regions |
| `?action=redirects` | admin: explicit 301/302 rules, the redirect tester, import/export, the 404 Monitor (`&tab=notfound`), and slug history (`&tab=slugs`) |
| `/contact` (`?page=contact`) | public: the contact page and its form; POST submits it |
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
| `?action=redact_page` | admin: page count (`&meta=1`) or one rendered page image, for the redaction editor's live preview |
| `?action=video_gate_test` | admin: a true dry run of the video-routing preflight — writes the deny rule, checks it, always undoes the write before responding |
| `?action=logout` | POST, admin: end the session |

Under clean URLs these become `/slug/`, `/category/slug/`, `/sitemap.xml`,
`/sitemap-pdf.xml`, `/sitemap-video.xml`, `/sitemap-categories.xml`, `/sitemap.html`, `/identity.json`, `/vcard.vcf`, `/llms.txt`,
`/robots.txt`, `/library.yaml`, and
`/{key}.txt`. Admin paths keep their query-string form.

## The AI Discovery Stack

One integrated architecture, not a collection of independent files. Each
surface answers a different question, and none duplicates another's answer:

| Surface | Question it answers |
|---|---|
| `identity.json` | WHO — the canonical machine-readable identity graph |
| About page | WHAT IT MEANS — the authoritative human-readable account (ProfilePage) |
| FAQ page | WHAT IT MEANS — canonical answers, human and machine alike (FAQPage) |
| `llms.txt` | WHERE AN AI SHOULD START — orientation first, inventory second |
| `library.yaml` | WHAT MATERIAL EXISTS — the canonical structured inventory, and how each item relates to the subject |
| Page JSON-LD | WHAT EACH WEB RESOURCE REPRESENTS |
| Sitemaps | WHERE THE URLS ARE |
| `robots.txt` | HOW CRAWLERS SHOULD ACCESS THEM |
| `vcard.vcf` | PORTABLE PERSON IDENTITY |

Each `library.yaml` document additionally carries `entity_relation` — how the
record relates to the archive's subject — and `relation_source`, which states
whether that was chosen by an operator (`explicit`), derived from the folder
(`inferred`), or is the safe default (`fallback`). The provenance is published
rather than hidden so a consumer can weigh the claim instead of treating a
guess as a decision.

No new AI discovery file (`ai.json`, `ai.txt`, `brand.txt`, `faq-ai.txt` or
similar) is to be added while an existing surface can carry the information.
`library.yaml` keeps its name deliberately: it describes exactly what the file
is, is tied to no AI vendor convention, and stays clearly distinct from
`identity.json`.

**AI crawler tracker.** An observability layer over the stack, not a new
surface: it answers WHO ACTUALLY CAME, logging a hit whenever a bot in
`ai_crawlers()` requests one of the seven files above, to
`data/crawler-log.jsonl` via `crawler_maybe_log()`, called from each
discovery route's handler after its own `*_ENABLED`/`SITE_INDEXABLE` gating
already passed — a disabled or 404'd route is never logged as a hit.
Deliberately independent of `AI_ALLOW_*`: those declare a policy in
robots.txt and library.yaml; the tracker records who showed up regardless,
including a bot that ignores the policy. No raw user-agent string and no
ordinary document visitor is ever recorded — only `{timestamp, bot, route}`.
Surfaced on the Crawlers screen, tabbed Overview / Recent hits / Known bots.

**Single source of truth.** Person identity flows from the publisher settings
into identity.json, the About page schema, vcard.vcf and llms.txt. External
profiles flow from `SITE_SAMEAS` alone into identity.json, vcard.vcf, the
footer and page JSON-LD. Documents flow from the metadata catalogue plus the
filesystem into library.yaml, llms.txt, the sitemaps and page JSON-LD — all
through `index_all_files()`, so the inventories cannot drift apart. Canonical
URLs everywhere derive from `SITE_URL`. The stable anchors `#person` and
`#website` are shared by identity.json and every page's JSON-LD, so consumers
can merge the graphs.

**Access tiers and discovery.** Hidden documents are excluded from
`llms.txt`, `library.yaml`, `identity.json`'s derived fields (knowsAbout),
and the folder listing — hidden means not announced. The XML sitemap is the
one deliberate exception: a hidden document's *page* remains indexable by
design (the delisting-only model settled in 1.38.0), because the page is
public and only the bytes are withheld; the sitemap never carries raw file
URLs. Restricted documents stay listed everywhere with their pages, and their
file URLs are never published where enforcement is active. A category that
exists only on hidden material never becomes a public `knowsAbout` claim.

**Person coverage.** The Schema.org Person type's biographical properties
are settable in Settings and flow through one shared emitter
(`schema_person_biography()`) into identity.json, page JSON-LD and vcard.vcf,
so the graphs cannot disagree. The family fields — `spouse`, `children`,
`parent` — are opt-in and empty by default: they publish other people's
names, the Settings note says so plainly, and whether one's family appears
in one's own biography is the publisher's decision (1.62.0 documented them
as permanently excluded; 1.63.0 corrected that overreach). Each publishes as
a typed Person node carrying a name only — no URLs, no `@id`s — because
Folio holds no further facts about the people named and must not imply any.
Still excluded: `sibling`, `knows`, `colleague`, `relatedTo`, and
`homeLocation`/`workLocation` — `addressCountry` already states the coarse
fact without publishing a locality. The vCard is untouched: Folio emits
vCard 3.0, and `RELATED` exists only in vCard 4.

**identity.json restraint.** `knowsAbout` is derived from the categories of
visible documents, never typed in; `subjectOf` points at the About page only
when it has content; no `author` claims are made per document, because Folio
records no per-document author and a biographical archive's documents
(certificates, identity papers) are about their subject, not by them.
Inventing authorship to populate a Schema.org property is exactly what the
stack refuses to do.

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
| `ffmpeg` | Video hover/listing preview: a short moving clip, plus its static poster frame | No preview at all for video; the play glyph shows instead |
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

### Explicit redirects

`lib/redirects.php` adds a rules layer beneath everything above, consulted
only after canonical slugs, aliases, path-derived legacy slugs, page slug
history, and reconciliation have all declined, and the request would
otherwise have become a 404. That position is the whole design: it is why an
explicit rule can never shadow a URL Folio already resolves, and why the two
systems cannot compete. If a document still answers, the redirect layer is
never reached.

All three public 404s route through it — documents, standalone pages, and
raw media — so a historical `/uploads/…` address is covered as well as a
document page. In the media handler only the missing-file branch is hooked,
never the deliberate refusals beneath it, which would otherwise let a rule be
used to probe for withheld documents.

Matching is exact, never patterned. Sources reduce to one comparable form:
slashes trimmed and collapsed, percent-encoding decoded once, both clean and
query-string URL shapes converging, and anything containing traversal or
control characters refused rather than sanitised. Destinations are internal
paths or `http(s)` only. Chains are collapsed at serve time so a visitor
makes one hop; loops are refused at save time.

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
| js-yaml 5.3.0 | MIT | `lib/js-yaml/license.txt` |
| Google API client and dependencies | MIT/Apache-2.0 | `lib/vendor/*/license*` (per-package) |

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
24. Authorship is never asserted without evidence. A document node carries
    `author` only where its record states `authored_by`, explicitly or by
    folder inference. The safe fallback, `archived_by`, claims only that an
    item is deliberately kept. A change that makes any record claim authorship
    by default is a defect, not a convenience — an archive holds work by other
    people, and asserting the subject wrote it is a false public statement
    about every source they merely collected.
25. A relation naming a second party stays silent until that party is
    identified. `published_by` and `issued_by` emit nothing unless the record
    names a declared organisation. They never fall back to the archive's
    subject: an author is not the publisher of his own book, and the
    inherited claim must be cleared, not left standing.
26. Explicit metadata always overrides inference, and the two are visibly
    distinct in the admin. An inferred value presented as a decision is a
    defect: the operator must be able to tell what they chose from what Folio
    guessed.
27. A work, the page about it, and the file encoding it are three entities with
    three identifiers (`#book`, `#page`, `#file`). A file inherits only facts
    that belong to the work — publication date, language — and never its own
    modification date onto the work, nor the work's date onto its own
    `dateModified`.
28. Two different names must never resolve to the same entity identifier
    without the operator being told. `entity_key_from_name()` is lossy —
    punctuation and case are discarded — so distinct names can collide.
    Any code path that writes entities keyed this way must detect a
    collision between two different names before writing, and refuse rather
    than let the second silently overwrite the first.
29. An entity is referenced by `@id`, never repeated as a bare string.
30. Any admin screen using the tab pattern (data-*-tab / data-*-panel) must
    load admin.js, or the tab JavaScript never activates and the page
    silently runs its no-JS fallback with no visible sign anything is
    missing. Verify with a real browser render — a markup-presence check
    alone does not catch this, since the fallback also renders correctly. One
    organisation named by six records is one node with six references. An
    entity that cannot be resolved is omitted rather than emitted as a
    dangling reference or a guess.

## Documentation set

These eight must always exist. Each has a distinct audience; none is a
restatement of another. If two files would say the same thing, one of them is
wrong.

| File | Audience and scope |
| --- | --- |
| `readme.txt` | general: what it is, how to use it, plain text, FAQ |
| `readme.md` | operators and developers: features, architecture, hosting, and the regression suite (running it, coverage, what is not covered) |
| `docs/install.md` | first-time installation only |
| `docs/upgrading.md` | upgrading, migrating, the **roadmap**, removing |
| `changelog.md` | version history; what observably changed |
| `security.md` | threat model, controls, deployment, reporting |
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

Per-file `pdf_access` (`public` / `restricted` / `hidden`) restricts a PDF's raw
bytes without ever affecting the record page's own indexability — see
"Indexability is unaffected" below, which is a hard invariant, not a goal.
Legacy stored value `viewer` is normalised to `restricted` on load and save.

### Metadata fields

On top of the existing `title`, `desc`, `category`, `tags`:

| Field | Values | Notes |
| --- | --- | --- |
| `pdf_access` | `public` (default) \| `restricted` \| `hidden` | validated server-side, `viewer` normalised to `restricted`, other unknown values fall back to `public` |
| `video_access` | `public` (default) \| `restricted` \| `hidden` | same three tiers as `pdf_access`; `viewer` normalised to `restricted`; delisting only: the player and URL are not shown to the public (notice shown), but the file stays reachable at its direct URL, not byte-gated. Intentional (changelog 1.38.0); not private |
| `document_type` | controlled list (certificate, letter, card, article, magazine, tract, report, transcript, form, identity, academic, award, booklet, other) | distinct from the existing free-form `category`; also feeds a conservative Schema.org type override |
| `transcript` | plain text, ~100,000 char cap | rendered server-side in the detail page HTML, never JS-injected — this is what keeps the content crawlable and AI-readable when the PDF itself is restricted |
| `language` | e.g. `en`, `ms`, `ar` | optional, maps to `dcterms:language` / `inLanguage` |
| `placeholder_image` | relative path to an existing image already in `uploads/` | manual fallback preview, shared by two features: `hidden` PDFs when Imagick/Ghostscript isn't available, and `restricted`/`hidden` video when ffmpeg or Imagick isn't available; validated to resolve to a real image file |

### `?action=raw` is the sole enforcement point

`?action=raw` (see Endpoints above) gains two optional query params,
`expires` and `token`, checked only when the requested file's `pdf_access`
is `restricted`. Signature is
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
  `restricted`), never to the record page response.
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

### Blurred previews for restricted/hidden video

Mirrors the PDF mechanism above with a different frame source. Where the
server has both ffmpeg and Imagick (`video_blur_available()`), a
representative frame is extracted with the existing `video_rasterise_frame()`
(the same function the hover-preview poster frame uses), then downscaled
hard, blurred, and scaled back up (`video_blur_generate()`) — the same
irreversible-loss ordering as `pdf_blur_generate()`, for the same reason.
Cached in `data/previews/` alongside the PDF cache but under a distinct key
(`hash('sha256', 'video:' . $rel)`, vs. the PDF cache's bare `hash('sha256', $rel)`,
so the two never collide even given a shared `$rel`), and served through
`?action=video_blur_preview`. `placeholder_image` is the same field the PDF
path uses and takes the same priority when set. Where neither an
auto-generated nor a manual preview is available, the restricted-video card
falls back to a flat CSS texture instead of an image. Availability is
reported on the Diagnostics screen alongside the PDF blur-preview check.

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

`tests/smoke.sh` provisions a temporary installation and asserts thirty-two
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
