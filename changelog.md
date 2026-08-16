# Changelog

All notable changes to Folio are recorded here. Versions follow semantic
versioning: major for breaking changes, minor for features, patch for fixes.

## 1.29.0 — 17 August 2026

### Added

- **PDF redaction.** A PDF can now be redacted per file: an admin draws opaque
  boxes over parts of a rendered page in the metadata editor, and the public is
  served a flattened, image-only copy with those areas blacked out. Because
  every page is rasterised, the text under a box is genuinely removed rather
  than merely covered — nothing to select, copy, or extract. The admin always
  sees the untouched original. Regions are stored as fractional coordinates, so
  they survive re-rendering at any resolution, and are edited through a
  drag-to-draw modal (an enqueued script, no inline JS). Enforcement funnels
  through the same points every PDF path already uses — `url_raw_effective()`
  and the `?action=raw` gate — so the flipbook viewer shows the redacted copy
  too. It fails **closed**: if the derivative cannot be built (no Poppler or
  Imagick PDF support, a document over the page cap, or a render error) the
  public is refused rather than handed the original bytes. The redacted
  derivative is cached under `data/redacted/`, which already denies direct
  access and disables PHP. Redaction is content-level and independent of the
  PDF access levels; the editor warns when a redacted file is not yet behind
  the direct-access guard.
- **Idle collection card.** The right-hand preview column, previously blank
  until a document was hovered, now shows a quiet colophon at rest: the
  collection name and live counts of folders and documents. It cross-fades to
  the hover preview and back.
- **Feed link in the footer.** The JSON Feed, previously discoverable only via
  the `<link rel="alternate">` in the head, now has a visible **Feed** link in
  the footer navigation.

### Fixed

- **Playlist could expose restricted media.** The audio/video playlist builders
  fell back to the unguarded raw URL when a file's access-aware hotlink was
  empty, which for a Hidden or unverified-Viewer video meant its direct URL was
  embedded in the playlist for the public. The builders now skip any file
  without a linkable hotlink and never fall back to the raw URL; audio, which
  always has a hotlink, is unaffected. The "Play videos" button count now
  matches the playable queue.
- **Catalogue reconnect screen overflowed.** The reconnect table reused the
  fixed-layout diagnostics table styles, so a relink `<select>` listing full
  file paths grew wider than its cell and pushed the layout past the viewport.
  The table now has its own auto layout with the select constrained to its cell.

### Changed

- **Footer redesign.** The *Powered by Folio* colophon no longer wraps onto its
  own row; it sits inline on the identity line and renders Folio as a styled
  **FOLIO** wordmark. Folder rows in the listing were refined: a stronger folder
  name, and the description aligned beneath it for clearer hierarchy.

## 1.28.0 — 15 August 2026

### Added

- **Video access control.** Video can now be set, per file, to Public (plays,
  downloadable), Viewer only (plays, no download), or Hidden (admin only), the
  same three levels PDFs already have. It is opt-in per library and off by
  default: public-only libraries keep the webserver serving video directly. When
  switched on under Crawlers, Folio fills a managed block in
  `uploads/.htaccess` that refuses direct access to video, so the only route to
  a clip's bytes is through Folio, which checks each file's access level and
  streams with range support. Folio writes only that managed block and a small
  flag in `data/`; it never rewrites the rest of your `.htaccess`.

  Unlike PDFs, video fails **closed**: a preflight confirms the webserver
  refuses direct video, and until it does, Hidden and Viewer video are
  kept off every public surface rather than served. Hidden video is absent from
  the listing, the sitemaps, and the JSON feed, and shows a restricted panel to
  the public; an admin still sees it, with a warning while protection is
  unverified. Viewer video plays from a signed, expiring URL with no download
  affordance. Switching the feature off empties the managed block and returns
  video to direct serving, without touching any file's stored access level.

### Fixed

- The image sitemap no longer lists audio or video files as images; it carries
  only actual images, as it should have.

## 1.27.1 — 15 August 2026

### Fixed

- **Video sizes to its own shape and no longer previews as a bar.** The video
  box had no height of its own, so before a clip's metadata arrived it collapsed
  into a short wide strip, worst on large files that take a moment to report
  their dimensions. The box now holds a stable frame while loading, then takes
  the clip's real aspect ratio once it is known: a portrait clip gets a
  portrait frame capped by height and centred, a landscape clip fills the width,
  and fullscreen still fills the screen.

## 1.27.0 — 15 August 2026

### Added

- **A JSON Feed.** Folio now publishes a JSON Feed v1.1 at `/feed.json`, listing
  the most recent documents, for feed readers and AI agents. It is paired with
  llms.txt in both directions: the feed carries a link to llms.txt in its
  `_folio` extension, and llms.txt's machine-readable section links back to the
  feed. The extension also names the site's sitemaps. A plain JSON Feed reader
  that ignores the extension still reads every item. Every public page
  advertises the feed with a `rel="alternate"` link, and it is served with the
  `application/feed+json` type. Like the sitemaps and llms.txt, it returns 404
  while the whole site is non-indexable.

## 1.26.3 — 15 August 2026

### Fixed

- **The category sitemap is announced everywhere the others are.** When the
  category sitemap was added, the static `robots.txt`, the readme URL table, and
  `docs/ssot.md` were left listing only the page and document sitemaps. All
  three now name `sitemap-categories.xml` as well, the readme no longer claims
  the main sitemap still carries categories, and a smoke test now checks that a
  categorised document appears in the category sitemap and not in the main one.

## 1.26.2 — 14 August 2026

### Changed

- **Media caches and streams instead of re-downloading.** Audio and video now
  carry `Accept-Ranges` and a week-long `Cache-Control`, so the browser can
  start playing before the whole file arrives, can seek, and does not fetch the
  file again on a replay or a seek. This needs the new `uploads/.htaccess`.

- **Playlist durations no longer compete with playback.** On the playlist page
  the track times were fetched several at once, which took bandwidth from the
  track being played. They now load one at a time, hold off until the current
  track can play, and pause whenever the player is buffering.

## 1.26.1 — 14 August 2026

### Changed

- **Folio is the default colour scheme.** A visitor with no saved choice always
  opens on Folio, the oxblood house scheme. The theme no longer switches to
  Night when the operating system asks for dark mode; only a scheme the reader
  picks from the header changes it.

- **The audio edit form shows only fields that fit audio.** Editing an audio or
  video record no longer offers Document type (the types are paper-document
  kinds), and the date field reads "Date of the recording" with a matching
  note, and the transcript field is labelled for a recording. Any value already
  stored in a hidden field is kept, so nothing is lost. Document editing is
  unchanged.

## 1.26.0 — 13 August 2026

Folder descriptions, a category sitemap, and the audio playlist moved to its
own page.

### Added

- **Descriptions for folders.** A folder can now carry a short description,
  shown under its name in the listing and edited inline from the dashboard by
  an admin. Descriptions live in their own flat file,
  `data/folder-descriptions.json`, kept apart from the document metadata: a
  folder is not a document, so it never gets a document identity, a slug, or a
  place in a sitemap. Clearing a description removes its record entirely.

- **A category sitemap.** The category archive pages now have their own
  sitemap at `sitemap-categories.xml`, announced in `robots.txt` and
  `llms.txt` beside the page and document sitemaps. Categories were removed
  from the main `sitemap.xml` at the same time, so the two never duplicate
  each other.

### Changed

- **The audio playlist moved to its own page.** In 1.25.0 the queue sat on
  every audio document page, which made no sense, since a document page is
  about one file. A folder holding two or more audio files now gains a "Play
  all" button that opens a dedicated playlist page: one player and the folder's
  tracks as a numbered list, each with its title and running time, the playing
  track marked with an equaliser, with auto-advance, previous and next, and
  repeat and shuffle. A document page and the listing preview pane now play
  only their own single file. Durations fill in as each track's metadata loads,
  so nothing extra is stored. The `AUDIO_PLAYLIST` setting still turns the
  whole feature on or off.

- **The category item count reads as a count.** The number beside a category
  name in the filter chips was set in the same size and colour as the name, so
  "Website 2" looked like a name rather than the category Website with two
  items. The count now sits in its own small, muted pill.

## 1.25.0 — 13 August 2026

Everything shipped on 13 August, gathered under one entry. In-page media
arrived — audio and video now play in the page, with an optional folder
playlist — and the player was then made easier to operate by touch. The folder
listing was made lighter on the wire without changing anything visible, the
footer was reworked to credit the project and show the version to a logged-in
admin, and a set of accessibility and theming items landed, including honouring
the operating system's dark-mode preference on a first visit.

### Added

- **An audio playlist, on by default.** A folder holding more than one audio
  file plays as a queue: the tracks in listing order, with auto-advance to the
  next when one ends, previous and next controls, repeat and shuffle toggles,
  and a track list with the current one marked. It works on the document page
  and in the listing preview pane, and stays folder-scoped so there is no
  playlist to store or manage. On by default (`AUDIO_PLAYLIST`), and an admin
  can turn it off in Settings.

- **Audio and video play in the page.** Files that were preview-and-download
  only now open in a player styled to the active theme, on the document page
  and in the listing preview pane, for both audio and video. The transport has
  play and pause, a seek bar with a buffered indicator, a time readout, volume
  and mute, and full screen for video, and is keyboard operable. Supported
  extensions are `.mp3`, `.m4a`, `.aac`, `.wav`, `.flac`, `.ogg`, `.oga`,
  `.opus`, and `.weba` for audio, and `.mp4`, `.m4v`, `.webm`, `.ogv`, and
  `.mov` for video.

- **The web server delivers media directly, so seeking works.** Media
  extensions are on the direct-serve path and declared in the uploads rules
  with the right type and without the download-and-sandbox headers other
  formats get, which lets the browser request byte ranges and seek. Playback
  needs no new dependency, no database, and no build step, and the file on
  disk is never touched.

- **A no-JavaScript fallback.** The page ships the browser's own player, and
  the theme enhances it only when scripts run, so a reader without JavaScript
  still gets working playback.

- **A skip-to-content link.** Every page with the standard header now opens
  with a link that jumps a keyboard or screen-reader user straight to the main
  content, past the header and its navigation. It stays off screen until it
  receives focus, so nothing changes visually for a pointer user. The main
  element carries `id="folio-main"` as its target.

### Changed

- **The logged-in header no longer crowds.** The eight admin screen links plus
  Log out were sharing the single header row with the site title, page nav,
  search, and theme picker, which forced the public navigation to wrap and read
  as a mess. The admin tools now sit in their own quiet toolbar directly under
  the header — labelled *Admin*, links in the middle, Log out held to the
  right — and on a narrow screen that toolbar scrolls sideways rather than
  stacking. The public header is unchanged for a logged-out visitor.

- **The hover preview card shows only the image.** The title and the
  size · date line under it were removed: the listing row beside the card
  already carries all of that, so repeating it in the floating preview was
  noise over the image. The unused `data-hover-meta` attribute is no longer
  emitted on each row, and the caption's markup, styles, and script are gone.

- **The footer credits the project, and shows the version when logged in.** A
  new colophon line reads *Powered by Folio*, linking the project repository, so
  the software is attributed rather than left anonymous. The identity line above
  it is now just the site name and year: the publisher was dropped from the
  footer because it duplicated the site title on single-owner archives and added
  nothing. `PUBLISHER_NAME` / `PUBLISHER_URL` are unaffected everywhere else —
  they still populate the schema.org publisher node, which is where they matter
  for search engines. The running version is appended to the colophon for a
  logged-in admin only (*Powered by Folio · v1.25.0*), so it is visible at a
  glance on every page without exposing it to the public.

- **The media player is easier to use by touch.** On coarse pointers the seek
  bar's hit area grows from 16px to 28px and its knob from 12px to 18px, so it
  can be grabbed and dragged with a fingertip, and playlist track rows are given
  a 24px-minimum tap height. The visible track, the buffered and played fills,
  and the desktop layout are unchanged — the rules are gated behind
  `@media (pointer: coarse)`.

- **The folder listing is smaller on the wire.** Two changes with no visible
  effect. In-page row links (document titles, category chips, folder rows, the
  flip-view button) are now root-relative rather than repeating the full site
  address on every row, which on a large folder removed tens of thousands of
  copies of the base URL. And the indentation whitespace the template injects
  between cells is collapsed at render time, which is masked around any
  `<textarea>` so the admin transcript field keeps its formatting. Canonical
  tags, structured data, sitemap entries, and IndexNow stay fully absolute, as
  they must.

- **File-kind detection is centralised.** A single `file_kind()` decides what
  every file is, replacing three copies of the same logic that would otherwise
  need the audio and video rules added in three places.

- **The operating system's dark-mode preference is honoured on a first
  visit.** With no stored choice, a reader whose system asks for dark now
  opens on the Night theme. A saved choice still wins, and the theme picker
  behaves exactly as before once anything is chosen.

- **The listing search field has an accessible name.** Its label held only a
  decorative glyph, so a screen reader announced an empty label and fell back
  to the placeholder. The field now carries an `aria-label` matching its scope,
  *Search this folder* or *Search this page* on a paginated folder.

- **Mobile touch targets.** On coarse pointers (phones and tablets) the header
  links, breadcrumb links, pager links, filter chips and category/tag chips now
  meet a 24px minimum hit area, and their labels are lifted off the sub-12px
  floor (chips 0.8rem, category/tag chips 0.75rem, sort marks to 1em). Desktop
  layout and density are unchanged — the rules are gated behind
  `@media (pointer: coarse)`.

### Documentation

- **A version-agnostic upgrade procedure.** `docs/upgrading.md` now opens with
  a single "any version to the latest" section — upload everything but your own
  files, reload once, verify — so the per-version sections read as deltas on top
  of it rather than each restating the mechanics. A consolidated note covers the
  1.17.0–1.24.0 span, flagging the two first-visit-visible changes in it (in-page
  media and the system dark-mode default).

- **A new invariant records which URLs must stay absolute.** The SSOT gains
  invariant 23: canonical tags, structured data, sitemap `<loc>`, and IndexNow
  are always absolute, while in-page listing links may be root-relative through
  `root_relative()`. This protects the page-weight change from being undone by a
  later well-meant edit that makes an indexing surface relative.

- **The roadmap was reviewed against this release.** The two resolved
  page-weight items were removed from Known issues, the `index.php` size figure
  re-measured, and a scoped near-term item added for captions and a transcript
  beside the in-page media player.

- **The roadmap gained a phased plan for the archive features** in
  `docs/upgrading.md`, covering separated dates, standardised archival
  metadata, related records, and a chronological timeline, with the build
  order, the structured-data mappings, and the decisions each phase depends on.

## 1.21.0 — 6 August 2026

### Added

- **Large folders are paginated.** A folder of 1,500 documents emitted a
  2.7 MB page of some 15,000 elements; it now sends 505 KB. Below `PER_PAGE`
  (200 by default, 0 to disable) nothing changes at all: the folder renders
  whole and the browser sorts, filters and searches it instantly, as before.

  **Sorting moves to the server when a folder is paginated**, and this is the
  part that mattered. Sorting is done in the browser over the rows it holds,
  so paginating without changing it would have quietly reduced "sort by size"
  to "sort the hundred in front of you". A reader who asks for the largest
  file is entitled to the largest file. Verified against 1,500 files with
  random sizes: descending opens on the true maximum, ascending on the true
  minimum.

  Each page carries its own canonical, with `rel="prev"` and `rel="next"`.
  Every document already has its own sitemap entry, so nothing became less
  visible to a crawler.

- **llms.txt now names the files, not only the pages.** Each public PDF
  carries its direct address beside its page, because a reader that can parse
  a PDF should be told where the PDF is. Access-restricted files deliberately
  do not: the line above them already explains why, and naming a URL that
  answers 403 helps nobody. The machine-readable section lists both sitemaps.

- **A link to llms.txt in the footer**, beside the sitemap, on the same terms:
  shown when the file is enabled and the library is indexable.

- **Project metadata**: author MENJ (<https://menj.blog>) and the repository at
  <https://github.com/menj/folio>, recorded in the file header, in
  `readme.txt` and `readme.md`, and in `docs/ssot.md`. `FOLIO_AUTHOR`,
  `FOLIO_AUTHOR_URI` and `FOLIO_REPO_URI` hold the values so nothing else
  repeats them.
- Diagnostics names the author and links the repository beside the version, so
  anyone administering an installation can find where to report a problem.
- `Contributors` in `readme.txt` was the placeholder `folio`; it is now `menj`.

- **A search title and search description for every document and every
  standalone page.** The title above a document is written for someone
  reading the page; the one that works in a result list is often different,
  and usually shorter. The two are now separate fields, in the Edit panel and
  on the Pages screen.

  Limits are 60 characters for the title and 150 for the description, which is
  roughly what a result shows before truncating. Both are enforced when saved,
  not only in the browser.

  A search title is used as the whole `<title>`, with no site name appended,
  since controlling the entire tag is the point of the field. Both feed
  `og:title` and `og:description` too, so social cards match. Left empty,
  every page behaves exactly as it did before.

- **A library can have as many standalone pages as it wants.** There were five
  slots: About, FAQ, and three numbered ones. Wanting a sixth meant editing
  the source. The Pages screen now has **Add a page** and a per-page **Delete
  on save**, with no ceiling.

  About and FAQ stay built in and cannot be deleted, because their schema.org
  types say something a generic page cannot: an FAQ page carries its questions
  as structured data, and an About page is read as identity information.
  Everything added is a `WebPage`.

  A new page derives its address from its title when no slug is given, so the
  internal slot identifier never appears in a URL. New pages join the sitemap,
  the header, and the footer exactly as the fixed slots always did.

- `tools/minify.js`, the script that builds the minified twins, so a release
  is reproducible rather than depending on something done by hand once. It
  uses clean-css and terser rather than a hand-written regex, and exits
  non-zero if any file fails.

### Changed

- The listing search field reads *Search this page* on a paginated folder
  rather than *Search this folder*, because that is what it does. Searching a
  whole paginated folder needs the server, and is now recorded as a known
  issue rather than implied by a label.

- **The loading note carries an animated sweep.** A thin rule in the accent
  travels beneath the words while a document loads. It is indeterminate on
  purpose: the wait cannot be measured, and a bar that fills to a number it
  invented is worse than one that simply says *working*. Under
  `prefers-reduced-motion` it holds still as a hairline and the words carry
  the message.

- **Corners follow a scale rather than a habit.** The interface used 2px, 3px
  and 6px in different places, which reads as assembled rather than
  considered. There are now three named radii — 4px for chips, 7px for
  buttons and inputs, 10px for documents and panels — and everything uses
  them.

- **Documents sit on the page instead of being printed into it.** The PDF
  viewer, page renders, images, the hover card and the search panel carry a
  soft two-layer shadow. Each theme defines its own: on Night a drop shadow
  reads as a smudge, so its depth is darker and tighter.

- **Controls answer when touched.** Buttons lift a single pixel on hover and
  settle on press, with a hairline of light along the top edge the way a
  pressed key catches it. Inputs take an accent ring on focus. Chips get the
  softer corner and an eased colour change but no lift: they are apparatus,
  not buttons, and should stay quiet.

- Focus rings are now deliberate — accent, offset, and following the same
  radius scale — rather than whatever the browser supplies.

Every transition is short and eased, and all of them are switched off under
`prefers-reduced-motion`. No gradients on surfaces, and no colour that is not
already in the theme: the library still reads as paper.

- **The stylesheet is delivered inside the document.** A linked stylesheet
  blocks the first paint for an entire extra round trip: parse the document,
  request the file, wait. PageSpeed measured that at 120ms on desktop and
  270ms on a throttled mobile connection, on a page that otherwise paints in
  half a second. The stylesheet is now inlined, so the page paints from the
  first response and there is nothing to wait for.

  It is allowed by a **Content-Security-Policy hash** of its exact bytes, not
  by `'unsafe-inline'`, which would permit every inline style on the page, and
  not by a nonce, which changes per request and would make public pages
  uncacheable. The hash is computed by `tools/minify.js` and recorded in the
  manifest, so no work happens per request.

  The trade is a few compressed kilobytes repeated per page instead of one
  cached request. It is worth it here because Folio's pages are publicly
  cacheable, so a proxy stores the whole document including its styles.

- **It degrades to a linked stylesheet whenever inlining is not safe**: when
  the source has been edited since the twin was built, when the minified file
  is missing, when there is no manifest, or when the stylesheet grows past
  60 KB and repeating it stops paying. Every one of those paths was tested,
  and none of them can leave a page unstyled.

- The fourteen places that emitted a stylesheet tag now call one function, so
  the inline and linked paths cannot diverge screen by screen.

- **Category addresses drop the hash suffix.** Every category carried one —
  `/category/tracts-2a4f72ad/`, `/category/language-literature-a6888cfe/` —
  because two names can slugify to the same string, "Q&A" and "Q A" among
  them, and both would otherwise claim one URL. Guarding against that by
  suffixing every category made a rare problem everyone's problem.

  The suffix is now added only to names that actually collide, and to every
  member of the colliding group so none of them silently wins the plain
  address. A library with no clash has clean addresses: `/category/tracts/`.

- **Diagnostics is tabbed, and opens on what is wrong.** Thirty-eight checks
  in one column meant reading the whole page to find the one line that
  mattered. Anything not passing is now gathered into a **Needs attention**
  tab shown first, labelled with the section each row came from; Environment,
  Addressing and Configuration follow. When everything passes there is no
  attention tab at all.

- **A passing check states its finding and stops.** Notes carried the guidance
  for fixing a thing even when there was nothing to fix: `OPcache` explained
  why Folio benefits from it, `site icon` gave five sentences on branding
  folders, `external utilities` listed nine absolute paths. Guidance now
  appears when a check is not passing, which is when it is useful. Across a
  healthy install the passing rows fell from several thousand characters to
  roughly six hundred: *Imagick*, *Enabled*, *pdf.js 5.4.149*,
  *9 found, 1 missing (unpaper)*.

  Nothing was deleted. Every explanation still appears the moment its check
  needs attention.

- Tabs are keyboard-navigable with the arrow keys, and without JavaScript
  every panel stays visible so the page degrades to labelled sections rather
  than hiding its own contents.

- **The document page had its buttons in two places.** "Read in flip view"
  sat under the preview while Print and Direct link sat below a rule further
  down, with a line of file facts wedged between them. That split was an
  accident of 1.2.0, which removed a duplicate download button and left the
  remaining one stranded inside the preview. All three actions are now one
  row beneath the document.

- **The document sitemap is now visible in the admin.** `robots.txt` has
  announced `sitemap-pdf.xml` since 1.9.0, but the Crawlers screen never
  mentioned it: you were publishing a sitemap you could not see, count, or
  click. The Sitemap preview lists both, each with the number of URLs it
  carries and a line saying what it is for. The PDF count is computed with
  the same rule the endpoint applies, so the two cannot drift apart.

- **Standalone pages carry a `BreadcrumbList`.** Every other public page type
  had one; About, FAQ and Privacy did not, so a search result showed a bare
  URL rather than the library path. Found by auditing structured data across
  every page type: everything else was complete, including a typed node per
  file kind and Dublin Core alongside schema.org.

- **The flip reader button is labelled "Flip view" on the document page**, as
  it already was on every listing row. It read "Read in flip view", which was
  both longer than the two buttons beside it and a different name for the same
  thing one screen away.

- **Metadata is grouped above the document instead of scattered around it.**
  The page ran title, description, chips, a labelled `Document type` block,
  the document, a facts line, then buttons. Type, format, size and date are
  now a single quiet line sitting with the chips, so everything describing
  the document comes before it and everything acting on it comes after.

- **The document's own date is shown at last.** 1.10.0 added a date field and
  emitted it in the structured data, but the page still displayed the file's
  modification time — so a magazine from 1998 read "Updated 29 July 2026",
  the exact confusion that field was added to remove. The recorded date is
  used when there is one, falling back to the file date when there is not.

- **Stylesheets and scripts ship minified.** PageSpeed had flagged this since
  the first report and it was declined twice on the grounds that Folio has no
  build step. That reasoning was wrong: the constraint is that *installing*
  Folio is copying a folder, and it says nothing about what the maintainer
  runs before packaging. The minified files ship already built, so nothing
  about installation changes and Node is never needed to run Folio.

  Transferred, which is what a visitor actually waits for: the stylesheet
  falls from 10.0 KiB to 5.8 KiB and `app.js` from 7.2 KiB to 3.6 KiB, a 41%
  and 49% reduction on top of compression. Uncompressed, where parse time
  lives, the six files fall from 104 KiB to 52 KiB.

- **Editing a stylesheet still works, with nothing to rebuild.** Folio serves
  a minified file only when it is not older than its source. Change
  `style.css` to adjust a theme and it immediately outranks the stale
  `style.min.css` — no setting, no flag, no build. Deleting the `.min.` files
  reverts to readable sources permanently. This keeps the promise made in the
  roadmap, that the stylesheet is editable directly rather than through a
  package format.

### Fixed

- **A loading document looked like a broken one.** The browser's PDF viewer
  is an empty dark rectangle until the file arrives, and a large scan can sit
  like that for a long time with nothing to say it is working. A reader has no
  way to tell a slow document from a failed one.

  The viewer is now covered until it is ready by a quiet note on the page
  colour, naming the size so the wait is explicable: *Loading the document
  (846 KB)…*. The frame keeps its space while hidden, so nothing shifts when
  the document appears.

  After six seconds the note acknowledges the wait rather than repeating
  itself. After twenty it reveals the viewer regardless: some browsers never
  report an embedded PDF as loaded, and a viewer the reader can see is better
  than a note that never leaves.

- **The inline PDF viewer ignored the shape of the document.** Its height was
  fixed, which suits an A4 page and leaves a screenful of empty dark viewer
  under anything wider: a birth certificate, a landscape results slip, a
  scanned card. The page was fine; the frame around it was not.

  The viewer now takes its height from the first page's own proportions.
  `pdfinfo` already reported the page size, so this only had to be carried to
  the browser: a wide certificate that previously reserved a full portrait
  frame now takes roughly half of it, with nothing empty below.

  The height is capped at 82% of the window so a very tall page cannot push
  the buttons out of view, and floored so a very wide one stays readable. When
  the page size cannot be read — no `pdfinfo`, or a file it cannot parse — the
  previous fixed height is used, so nothing depends on the tool being there.

- The measurement is cached in `data/aspect.json`, keyed on each file's size
  and modification time, so `pdfinfo` runs once per document rather than on
  every page view, and a replaced file is measured again.

- **Print did the same thing as Direct link on a PDF page.** Both opened the
  raw file: two buttons side by side with one behaviour between them.

  The cause was a stale assumption. When the inline PDF viewer was removed in
  1.0.1 there was nothing left on the page to print, so Print was changed to
  hand the file to the browser. 1.15.0 restored the viewer, and this was never
  changed back. Print now prints the document where it sits, which is what the
  button is for.

  Where there is no viewer to print — mobile, where the preview is a rendered
  first page rather than an embedded document — the file is still handed to the
  browser, and a viewer that refuses to print falls back the same way.

- **Chip rows on two screens rendered as full-width stacked bars.** The
  documentation viewer and the category archive placed their chips directly
  inside `.filter-bar`, which became a vertical flex container when the search
  field moved into it. Each chip then became a column item and stretched the
  full width. The library listing had been updated to wrap its chips in a
  `.filter-chips` row; these two were missed and had been wrong ever since.

  Both now use the same row container. Found by auditing every `.filter-bar`
  in the file rather than fixing the one in the screenshot: the category
  archive is a public page, so every visitor was seeing it too.

- **Minified assets were not being served.** 1.14.0 shipped minified twins and
  chose between them and the readable sources by comparing modification times.
  That comparison cannot survive an upload: FTP sets mtimes by upload order and
  by whatever the client decides, so a perfectly good `style.min.css` can look
  older than its source and be passed over indefinitely. A live site was
  serving the full 11 KiB stylesheet with the minified one sitting beside it
  unused.

  The choice is now made from `assets/manifest.json`, written by
  `tools/minify.js`, which records the byte length of the source each twin was
  built from. A byte length survives upload unchanged and still changes the
  moment the file is edited, so editing a stylesheet still takes precedence
  with nothing to rebuild, and an installation with no manifest falls back to
  readable sources rather than breaking.

- **Chips were too small to tap reliably.** Category and tag chips stood about
  fifteen pixels tall against the twenty-four a finger needs, which is what
  cost the Accessibility score. They now meet the minimum on touch devices,
  with more space between them. Scoped to coarse pointers, so the desktop
  appearance is unchanged.

- **IndexNow submitted only part of the site.** It sent the library root and
  the document pages, and nothing else: folder listings, category archives,
  standalone pages, and every PDF file were announced by the sitemaps but
  never pushed. The mechanism that delivers immediately was the one carrying
  the least.

  The submission is now the union of both sitemaps, built from the same
  sources they use so the three cannot drift apart. Verified by set
  comparison: nothing in either sitemap goes unsubmitted, and nothing is
  submitted that is not in one.

- The button now states how many URLs it will send, and the screen says what
  is covered, so the gap could not sit unnoticed again.

- **Standalone pages emitted no meta description at all.** About, FAQ and the
  rest had none, so a search engine composed its own snippet from wherever in
  the body it chose. They now carry one: the written description when there is
  one, and otherwise the opening 150 characters of the page, which is at least
  the beginning rather than an arbitrary middle.

- **Addresses issued before this release still work.** The hashed form is
  recognised and 301-redirects to the current address, so links already
  indexed, bookmarked, or sitting in a submitted sitemap do not break. The
  bare slugified name redirects too, for links predating the suffix entirely.

- **Diagnostics contradicted itself about reconciliation.** When no document
  could be matched by content it reported "No document can be matched
  automatically. Open Catalogue to apply it." — naming an action that did not
  exist, since there was nothing to apply. The closing sentence was appended
  unconditionally. It now says what is actually true: the files were deleted
  rather than renamed, or were replaced with different content, and the way
  forward is to relink by hand or remove the records.

- **The catalogue note promised content matching would work before checking
  whether it could.** It always said "Folio will match them by content and
  keep their URLs", even where the match preview had already found nothing.
  Both notes are now built from a single preview, so they cannot disagree,
  and the preview is computed once rather than twice.

- **Compression failed outright on hosts running qpdf older than 10.0**, with
  the raw error `unknown option --recompress-flate`. Folio passed
  `--recompress-flate` and `--compression-level` unconditionally, and both
  arrived in qpdf 10.0; an older qpdf rejects an unknown option rather than
  ignoring it, so nothing was compressed at all. Shared hosts commonly ship
  8.x or 9.x.

  The flag list is now built from the installed version. Everything qpdf has
  understood for years is always sent; the two newer flags are added only
  where they exist. Their absence costs a few percent of the saving rather
  than the feature: on a test document both flag sets produced byte-identical
  output.

- **The failure message no longer hands the reader a tool's internal error.**
  An unknown-option failure now names the qpdf version on the server and asks
  for a report, rather than showing a command-line flag to someone with no
  reason to know qpdf's release history.

### Documentation

- **The roadmap's Known issues were re-measured against this release.** The
  entry for `index.php` still quoted 8,279 lines and 151 functions; it is now
  8,964 and 163, having grown by roughly 700 lines since the note was written.
  A figure that quietly goes stale is worse than no figure, so the list now
  says it is re-measured whenever it is reviewed.

- **The search-field entry was wrong about the cause.** It said the field had
  no label. It has one — `<label for="listing-search">` — but the label's only
  content is a decorative glyph marked `aria-hidden`, so a screen reader finds
  an empty label and falls back to the placeholder. The fix is different from
  the one implied, so the entry now describes what is actually there.

- Principle 6, on disposable caches, listed four directories and had not been
  updated for `data/compressed/` or `data/aspect.json`. Both are disposable
  and both are now named.

The three payload measurements were re-checked on a 1,500-document folder and
hold: 2.7 MB, 33% indentation, `BASE_URL` written 7,514 times.

- **A Known issues section in the roadmap**, recording what a review of this
  release actually measured rather than what it assumed. The largest item is
  that a folder of 1,500 documents renders every row at once, producing a
  2.8 MB page of some 15,000 elements: compression hides it in transit, but
  the browser still parses all of it and the in-page search and sort walk the
  whole set on every keystroke. Server time is unaffected. Alongside it: a
  third of that page is indentation whitespace, `BASE_URL` is repeated 7,515
  times on it, there is no skip-to-content link, the search field has no
  label, `prefers-color-scheme` is ignored, `index.php` has grown to 8,279
  lines with 775 of them inline admin markup, and the catalogue cannot be
  exported without FTP.
- **Structured dates removed from the roadmap**, having shipped in 1.10.0
  including the partial and uncertain dates the entry said needed design.
  Chronological browsing, the part still outstanding, is listed in its place.

- **The upgrade guide skipped thirteen releases.** It documented 1.2.0 through
  1.6.0 and then stopped, leaving no path for anyone on 1.7.0 or later. The
  span from 1.7.0 to 1.13.1 is now covered in one section, since those
  releases shipped over two days and share a single procedure.

  It states plainly the two things people skip: `.htaccess` is mandatory
  rather than optional, because restricted PDFs, `/sitemap-pdf.xml`, and the
  compression and caching rules all depend on rules it gained between 1.9.0
  and 1.11.x, and a site missing them looks perfectly fine while silently
  serving restricted files without a check; and `uploads/.htaccess` carries
  the 1.8.3 fix for a fault that could return 403 for every document.

  It also covers the new `branding/` folder, the one cache-clearing reload
  1.13.1 needs, the licence change in 1.8.0, and the Catalogue screen added in
  1.13.0.

- `tests/asset-version-check.php` and `tests/wired-check.php` were missing from
  the file inventory in `docs/ssot.md`, which claims to be authoritative.

### Compatibility

- **Existing installations need no migration.** The slot list is now read from
  `data/pages.json` rather than hardcoded, so an installation carrying
  `page1`, `page2` and `page3` keeps them, their content, and their addresses.
  Verified against a pre-1.17 file: all three slots still list in the admin,
  and their pages still resolve and still appear in the sitemap.

## 1.13.1 — 5 August 2026

Fixes for the issues PageSpeed Insights reports on a live installation.

### Added

- **A Catalogue screen**, at `?action=catalogue`. Reconciliation and relinking
  have worked since 1.6.0 but could only be reached by sending a POST by hand,
  which meant a library with records adrift from their files had no way to
  repair itself. This was the largest gap between what Folio could do and what
  an administrator could reach.

  The screen lists documents whose file has gone missing and files that are
  not yet catalogued, and offers the two safe repairs:

  **Reconnect automatically.** Matches records to files by comparing contents.
  A match is only accepted when exactly one file has the right contents and
  exactly one record wants it — anything ambiguous is left alone rather than
  guessed at.

  **Relink by hand.** For the cases content cannot settle, such as a document
  that was edited as well as renamed. You choose the file; only the path and
  fingerprint change.

  Either way the document keeps its URL, title, description, tags, date,
  transcript and access setting. Nothing on disk is renamed, moved, replaced
  or deleted — the screen says so, and it remains true.

  Linked from the admin bar and from the Diagnostics rows that report the
  problem.

- **Compress a PDF.** Scanners often write PDFs that store their page images
  with little or no compression, so a single certificate can arrive as tens of
  megabytes. A **Compress** button on each PDF row prepares a smaller copy
  with qpdf: the structure is rewritten and the streams recompressed, and the
  images themselves are left alone. Measured on an unoptimised scan, 42.1 MB
  became 57.4 KB.

  **Lossless only.** This is an archive, and a scan of a birth certificate
  should not be quietly degraded to save bandwidth. The result is the same
  document to any reader.

  **Your file is not replaced.** The copy is written under `data/compressed/`
  and offered as a download; putting it in place is done over FTP, as with
  everything else in the library. The button reports the saving so you can
  decide whether it is worth doing.

  A copy is only kept if it saves at least 3% and if `pdfinfo` can still read
  it — a smaller file that will not open is worse than no file. An
  already-efficient document is reported as such rather than duplicated.

  Both the preparation and the download are administrator-only.

- **Sortable columns.** Name, Size and Date each sort ascending on the first
  click and descending on the second. The active column shows an arrow, and
  the header is a real button, so it works by keyboard and is announced
  correctly with `aria-sort`.

  Each column sorts on a stored value rather than on what is displayed:
  `1.5 MB` and `900 B` do not compare as strings, and `Oktober 1998` has no
  order without its machine-readable form. Names use the browser's own
  collator, so accented and non-English titles order sensibly.

  A document with no date of its own sorts after those that have one, in both
  directions. The fallback shown for those rows is a file timestamp — a
  different kind of fact — and letting it mix in among real dates would imply
  an ordering that is not real.

  Sorting reorders rows; it does not decide which are visible. The category
  chips and the search box still own that, so a sort applied to a filtered
  view keeps exactly the same set of documents. The chosen order is remembered
  for the session, so moving between folders does not silently reset it.

- **A date field for each document.** Folio recorded when a file was last
  modified, which for an archive is nearly meaningless: it says when the scan
  was uploaded, not when the certificate was issued. Re-uploading a 1979
  document made it look like a 2026 one.

  The field is free text, because historical documents state their dates in
  whatever form they please. `1996`, `Oktober 1998`, `30/11/1991`,
  `1991-11-30` and `c. 1985` are all understood, including Malay month names,
  and day-first numeric dates are read as day-first. What cannot be parsed is
  still displayed exactly as entered.

- **Dates in the structured data.** A parsed date is emitted as
  `dateCreated`, `datePublished`, `temporalCoverage` and `dcterms:date`,
  alongside the existing `dateModified`. Search engines could previously only
  see the file's modification time, so every document in the collection looked
  contemporary. A document with no date makes no claim at all rather than
  publishing an empty one.

- The listing shows the document's date beneath its description, as a proper
  `<time>` element with a machine-readable value where one could be derived.

### Changed

- **The browser's own PDF toolbar no longer sits on top of the preview.**
  Clicking Preview on a PDF embeds it, and Chrome and Edge draw their own
  download, print and menu buttons over Folio's — duplicating actions already
  beside the document and, on a restricted document, implying they are
  available. The embed now asks for those controls to be dropped.

  This is presentation, not protection. Firefox and Safari ignore the
  parameters, and the file's own URL is reachable regardless. A document that
  must not be downloaded needs `pdf_access` set to viewer or hidden, which is
  enforced on the server rather than requested of the browser.

- **Category and tags are visually distinct.** They sat in one row styled
  almost identically — a bordered chip each, differing only by a `#` and some
  letter-spacing — so a document with six tags showed seven near-identical
  boxes and nothing said which was which.

  They are two different kinds of fact. A document belongs to exactly one
  category, and that is how the library is navigated; tags are annotations,
  and there may be many. Giving them equal weight said they were the same
  thing.

  The category now sits on its own line as a tinted, bordered link. Tags sit
  beneath it with no box at all — plain muted words that read as a line of
  text rather than a row of competing buttons. Both still filter on click.

- **Search is a nav icon and an overlay, not a bar across the listing.** The
  field sat in the filter bar, where it had to be wide enough to look
  deliberate and so dominated a row that is otherwise small chips — on a wide
  screen it stretched most of the page for no reason. It is now a magnifier
  in the header. Clicking it opens a panel; the categories get their row back.

  Escape and the close button dismiss it, clicking the backdrop dismisses it,
  and `/` opens it from anywhere on the page. Focus moves into the field on
  open and returns to where it came from on close, and stays inside the panel
  while it is open.

  **Closing keeps the results.** Dismissing the panel and losing the filtered
  list at the same time is not what a reader is asking for; clearing is done
  by deleting the text.

- **The filter bar is laid out properly.** Category chips and the search box
  shared one wrapping flex container, with the search pushed right by
  `margin-left: auto`. That meant the search landed at the end of whatever row
  the chips happened to stop on, and moved every time a category was added or
  the window resized. They are now two rows: search across the full width with
  a magnifier, categories beneath it.
- The search field is full width rather than a fixed 15rem, with a visible
  search icon, and no longer needs a separate mobile rule to behave.
- **The date column shows the document's date**, not the file's modification
  time. `2026-08-04` on a 1980 birth certificate answered a question nobody
  was asking. Where no document date is recorded the file time is still shown,
  muted, with a tooltip saying what it is.

### Fixed

- **An upgrade could leave the site styled by the previous stylesheet.**
  1.9.2 told browsers to cache `style.css` and the scripts for a year and
  never revalidate — correct for files that only change on upgrade, but only
  if the URL changes with them. It did not. A browser that had visited before
  kept the old stylesheet and applied it to the new markup: sort buttons drew
  as plain boxes, tags kept borders the new rules removed, and the header lost
  its styling entirely.

  Every release-owned asset is now linked with `?v=` and the version number,
  so an upgrade is a new URL and the cache is bypassed exactly when it should
  be. The year-long cache stays, and is now safe.

  A regression test fails the build if an asset is linked without a version.

**If a page still looks wrong after upgrading**, it is the old file in your
browser cache: reload once with Ctrl-Shift-R, or open a private window. From
this release on it corrects itself.

- **A PDF whose preview could not be generated showed a broken-image icon.**
  When rendering failed, the thumbnail route redirected to the original file.
  That is the right answer for an image, and exactly wrong for a PDF: the
  browser received a PDF where it expected an image and drew the broken icon
  the fallback existed to avoid. It now returns 404 for anything an `<img>`
  cannot display, and the hover card shows its document placeholder instead.

- **A failed render said nothing.** A library where only some previews work
  gave nothing to diagnose. The reason is now written to the error log —
  `Syntax Error: Couldn't find trailer dictionary`, `Document stream is
  empty`, a timeout — naming the file each time.

Encrypted, truncated and empty PDFs are the usual causes; all three now
degrade to a placeholder rather than a broken image. A document that renders
is unaffected.

- **Nothing was compressed.** `.htaccess` carried no `mod_deflate` or
  `mod_brotli` rules, so the stylesheet and script were sent uncompressed on
  every visit — 54 KB where 13 KB would do. Both compress by **77%**. Rules
  are now included for HTML, CSS, JavaScript, JSON, XML and SVG, and skip
  formats that are already compressed.

- **Assets had no cache lifetime.** Every page view re-fetched
  `style.css` and `app.js` because nothing set an expiry. They are
  release-owned and change only on upgrade, so they now carry a one-year
  `immutable` cache. Documents get an hour; HTML stays uncached.

- **Scripts were render-blocking.** `app.js`, `admin.js` and `view.js` now
  load with `defer`, taking them off the critical rendering path. PageSpeed
  estimated 340 ms desktop, 760 ms mobile.

- **Three of the four themes failed WCAG AA contrast.** The muted `--quiet`
  colour, used by the header links, category counts and footer, measured
  4.22, 4.04 and 3.97 against its background where 4.5 is required. Darkened
  by a few percent — visually almost identical — to 4.60, 4.68 and 4.73. The
  night theme already passed.

- **The theme-picker buttons were 13px**, well under the 24px minimum tap
  target, and four sat side by side. The button is now 24×24 while the
  coloured dot stays 13px: the tap area grew, the design did not change.
  Keyboard focus is now visible on them too.

- **Hovering a PDF row downloaded the whole document.** Image rows used a
  cached thumbnail, but PDF rows were given the file itself and rendered page
  one in the browser with pdf.js. On a 6 MB scan that meant waiting several
  seconds and transferring the entire document — every hover, for a preview a
  few hundred pixels wide.

  Where the server can render PDF pages, the hover card now loads the same
  cached WebP derivative the rest of Folio uses. Measured on a large scan:
  176,506,623 bytes down to 1,190. The client-side renderer remains as the
  fallback for servers without Poppler.

## 1.9.0 — 4 August 2026

Getting the documents themselves into search results.

Folio is free software.

Two Diagnostics faults, both of which made a healthy installation look broken.

Standalone pages now work the way documents do.

Documentation completeness, and a roadmap.

Diagnostics now reports every PHP extension Folio can use.

Two utilities were detected and advertised but never actually used.

OCR is now usable from the interface. It was implemented and tested in 1.5.0
but there was no way to start it.

Interface fixes, and a site icon you can actually change.

Document URLs are now permanent. They no longer follow the filename or the
folder, so renaming or reorganising the library over FTP does not break links.

### Added

- **A sitemap for the document files**, at `/sitemap-pdf.xml`, listing every
  PDF in the library with its modification date. Search engines index PDFs as
  documents in their own right, so this is what gets a scanned certificate
  found rather than only the page describing it. It is announced in
  `robots.txt` alongside the main sitemap.

  Every PDF in the library is listed, whatever its access setting: the
  sitemap's job is discovery, and access control governs delivery. Files
  matching `EXCLUDE_PATTERNS` are not listed, because they are not part of the
  library and return 404 on every route.

- A regression test asserting the root icon paths return an actual image
  rather than an HTML page. It fails if the handler is removed.

- A step-by-step **"Turning on PDF access control"** section in the readme
  covering the signing key, the preflight, the `.htaccess` rule, and the
  warning that renaming the uploads folder silently disables enforcement
  unless the rule is updated to match.

- **A roadmap in `docs/upgrading.md`.** It records the principles that will
  not change, what is planned next, what is under consideration, and — just as
  usefully — what has been considered and declined, with reasons. The two
  questions "what changes if I upgrade" and "what is going to change later"
  belong in the same file.
- A note in the readme and readme.txt pointing at it, so that something listed
  as declined is not mistaken for something merely not done yet.

- Diagnostics rows for **image engine**, **fileinfo**, **iconv**, and
  **OPcache**, alongside the existing PHP version, mbstring, JSON, password
  hashing and randomness checks. Each says what is actually lost when the
  extension is missing rather than only naming it: GD alone cannot convert
  TIFF, HEIC or AVIF; without fileinfo a mislabelled file is served with the
  wrong type; without iconv accented characters are stripped from slugs
  instead of transliterated.
- OPcache reports as a check rather than OK when it is off, since a
  single-file application benefits from it noticeably.

- A regression test asserting that **every utility Diagnostics advertises is
  invoked somewhere**. Detection without a call site is worse than not
  supporting a tool: the interface reports a capability the application does
  not have. Verified to fail when either utility is returned to its previous
  state.

- **An OCR button on PDF rows**, shown to signed-in administrators when OCR is
  available. It counts up while working — a control that sits silent for two
  minutes reads as broken — reports how much text was found, and stays marked
  so you can see which documents are done. A PDF that already contains text is
  reported as already searchable rather than reprocessed.
- **A step-by-step OCR guide** in the readme: how to check what the server has,
  what to ask a host to install, where Folio looks for a per-account virtual
  environment, how to choose languages, and what to do when OCR reads a
  document badly.

- **A site icon you can change.** Put `favicon.svg`, `.png`, `.ico`, or
  `apple-touch-icon.png` in a `branding/` folder at the root and Folio uses
  them, with no configuration. `branding/` is not release-owned, so an upgrade
  cannot overwrite your icon — replacing the file inside `assets/` would be
  undone by the next update, which is why it never appeared to work. The
  optional `SITE_ICON` setting points somewhere else. Icon markup is now
  emitted from one place rather than repeated across sixteen page templates.
- Diagnostics reports which icon is in use and where it came from.

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

- **PDFs are now served `index, follow` explicitly**, rather than being left
  to the default. Following the links inside a document is how a crawler
  reaches the rest of a collection from it. The same applies to `.txt` and
  `.md`.

Formats a browser would execute are unaffected: HTML, XHTML, XML, MHTML and
anything unrecognised are still forced to download with `noindex, nofollow`.

- **Stopped labouring the Ghostscript point.** Folio does not use Ghostscript,
  and the documentation said so 77 times across seven files — in the readmes,
  the security notes, the architecture reference, the upgrade guide, the
  sample configuration, and on the Diagnostics screen. Repeating it made an
  irrelevance look like a caveat. The user-facing text now simply says PDF
  pages are rendered with Poppler.

  What remains is only the `PDF_ALLOW_GHOSTSCRIPT` setting name, which cannot
  be renamed without breaking existing configurations. Behaviour is unchanged.

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

- Canonical URLs, sitemap entries, structured-data identifiers, `og:url`, and
  every internal link now use the saved slug. Aliases never appear in any of
  them; they only ever redirect.
- Older path-derived URLs continue to work and redirect to the current
  canonical address, so existing links and search-engine results survive.
- Legacy path-keyed metadata is migrated on first write, preserving every
  field **and the URL each document already answered on** — a migration that
  silently changed every address would undo years of indexing. The pre-
  migration file is kept as a dated backup.

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

- **The image engine row had disappeared.** It was added in 1.5.0 and lost in
  the 1.6.0 merge, because the branch it was merged onto predated it. Whether
  Imagick or GD was available — the difference between working thumbnails and
  none — was not reported anywhere.

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

- **`admin.js` was never loaded on the listing page**, so every admin-only row
  behaviour defined there was unreachable. It is now loaded for signed-in
  administrators alongside `app.js`.

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

### Unchanged

- The bundled components keep their own licences and are unaffected:
  Parsedown (MIT), Mozilla pdf.js (Apache-2.0), and the OpenJPEG and QCMS
  WebAssembly decoders.

- Only mbstring is genuinely required, and only for Markdown. Every other
  extension has a fallback: verified by serving the listing, a document page
  and the sitemap with Imagick, GD, fileinfo, iconv and mbstring all absent —
  all returned 200.

### Note

Icons are cached hard by browsers. If the old one persists after upgrading,
try a private window before assuming it has not worked.

## 1.5.0 — 3 August 2026

Folio now notices the command-line utilities a server already provides and
uses them. Nothing here is required: with none installed, behaviour is
unchanged.

Documentation accuracy. No functional change.

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

### Changed

- The version-sync table in `docs/ssot.md` now lists all six locations that
  carry the version, with the exact string in each, plus a shell snippet that
  reports any that are stale. It previously listed three, omitting `readme.md`
  and `security.md` — the two that had actually drifted in the past.
- `docs/upgrading.md` separates the 1.3.0 and 1.4.0 steps. The three PDF
  access control steps belonged to 1.3.0 but sat under the 1.4.0 heading, so
  anyone already on 1.3.0 was walked through work they had done.

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

- **The analytics tracker was blocked by the site's own security policy and
  collected nothing.** Matomo/GA4's inline bootstrap needs a `<script>`
  block the Content-Security-Policy didn't allow, since permitting it with
  `'unsafe-inline'` would have switched off inline-script protection
  site-wide. Each exact inline block is now allowed individually by its
  sha256 hash (`analytics_csp_sources()`), computed from the same strings
  that are emitted, so the two cannot drift apart. A self-hosted Matomo on a
  non-default port is also now carried through correctly in the CSP
  origin, which previously matched only the scheme's default port.

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

- `FOLIO_VERSION` constant as the single source of truth for the release.
- Diagnostics reports the Folio version and whether clean URLs are active,
  naming the reason when they are not.

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

### Action required when upgrading

- **Upload the new `.htaccess`.** It now ships as a real dot-file with clean
  URLs already active, replacing the old `htaccess.txt` you had to rename by
  hand. If you customised yours, merge rather than overwrite.
- `htaccess.txt`, `uploads-htaccess.txt`, and `data-htaccess.txt` are gone from
  the package. Delete them from your installation.

Nothing else changes on upgrade. Your `config.php`, `data/`, and `uploads/` are
untouched, and an installation that keeps an older `.htaccess` keeps working on
query-string URLs rather than breaking.

## 1.0.1 — 31 July 2026

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

## 1.0.0 — 30 July 2026

First release.

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
