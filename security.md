# Security policy

Thank you for taking the time to look into Folio's security.

## Supported version

Folio is a small single-file application shipped as a numbered release. Only
the most recent release receives security fixes. If you are running an older
release, upgrade before reporting an issue.

The current supported release is **1.67.1**.

## Security controls

This section records what Folio actually enforces, so that a future change
does not quietly remove a protection. Each item is covered by the regression
suite in `tests/`.

### Output encoding

Structured data is JSON, but it is written inside a `<script>` element, which
is an HTML context. It is therefore encoded with `JSON_HEX_TAG`,
`JSON_HEX_AMP`, `JSON_HEX_APOS`, and `JSON_HEX_QUOT`, and **not** with
`JSON_UNESCAPED_SLASHES`. A document title containing a closing script tag
must never be able to end the element. If encoding fails, an empty graph is
emitted rather than a malformed one. All page types share one encoder.

Markdown, from both uploaded `.md` files and standalone pages, is rendered by
Parsedown in safe mode, so raw HTML in that content is escaped rather than
executed. Every other value interpolated into a page goes through `e()`.

### Path containment

Every path derived from a request is resolved and confirmed to sit inside
`uploads/`. Symbolic links are rejected outright rather than followed, at
every directory scan and at each delivery endpoint. Files matching
`EXCLUDE_PATTERNS` are treated as absent: they are missing from the listing,
sitemap, categories, and IndexNow submissions, and requesting one directly
returns 404 rather than serving it.

### File delivery

Formats a browser will execute in the page's own origin — HTML, XHTML, XML,
MHTML — and any unrecognised type are forced to `application/octet-stream`
with `Content-Disposition: attachment`, a sandboxing Content-Security-Policy,
and `X-Robots-Tag: noindex`. This is enforced both in PHP and in the shipped
`uploads/.htaccess`, so neither alone is a single point of failure. SVG is
served under a sandboxing policy so embedded script cannot run.

### Authentication

Passwords are stored as `password_hash()` digests, optionally peppered with
`FOLIO_AUTH_PEPPER`. Each account carries an `auth_version` that is
incremented when its password is changed or reset, or when the account is
deleted; existing sessions carrying an older value are rejected on their next
request. Sessions are only started for requests that need one, so anonymous
public pages stay cacheable and set no cookie.

Failed logins are counted per address, and the entire read-modify-write is
performed while holding an exclusive lock, so parallel attempts cannot
overwrite one another's increments.

**Limitation, stated plainly:** this throttle is a speed bump, not
brute-force protection. It is keyed by `REMOTE_ADDR`, so an attacker with many
addresses is not meaningfully slowed, and its counters live in the system
temporary directory, which some shared hosts clear or isolate per process.
Treat a strong password and HTTPS as the actual controls, and add rate
limiting at the web server or CDN if the library is a likely target.

### Request forgery

Every state-changing action requires a POST carrying a valid CSRF token:
metadata edits, settings, crawler controls, standalone pages, account
management, and logging out. Logging out is included deliberately — a plain
`GET` logout lets any third-party page end an administrator's session, so the
old URL now returns `405` with a confirmation form instead of acting.

### Response headers

Application responses carry a Content-Security-Policy with no `unsafe-inline`,
`X-Content-Type-Options: nosniff`, a referrer policy, and framing
restrictions. There are no inline event handlers or inline `<script>` blocks
anywhere in the application and all of its own JavaScript is in external
files, so the policy needs no exceptions for Folio's own code.

The installer emits the same protections plus `frame-ancestors 'none'` and
`no-store` caching, because its pages display one-time tokens and generated
secrets.

The PDF flip-view reader is the one screen that relaxes the policy, adding
`wasm-unsafe-eval` and `worker-src 'self'` because WebAssembly rendering
requires them. It is a separate page for exactly this reason: the listing and
every other screen keep the stricter policy.

### Analytics

Analytics is off unless a provider is configured. With `MATOMO_URL`,
`MATOMO_SITE_ID`, and `GA4_MEASUREMENT_ID` all empty, no third-party origin
appears in the policy and no tracker tag is emitted: the responses are
identical to a build without the feature.

When a provider is configured, the policy is widened to exactly the origins
that provider needs, carrying the port through when the URL specifies one.
Both providers require a short inline bootstrap. Rather than allow inline
script generally, each block is admitted by its own `sha256` hash, computed
from the same string that is emitted. Enabling analytics therefore does not
weaken inline-script protection on any page.

A mismatched hash is a silent failure — the tag renders, the policy still
looks strict, and the tracker never runs — so the regression suite asserts
that every emitted block is covered by a hash and that `'unsafe-inline'` never
appears.

Analytics is not applied to authenticated sessions unless `ANALYTICS_ADMIN` is
enabled, so administrators' own browsing stays out of the figures and out of
the third party's records.

Folio itself records nothing: no visit log, no IP addresses, no geolocation.
Anything collected is collected by the configured provider under its own terms,
which is a decision for whoever runs the site.

### Browser PDF controls

Embedded PDFs ask the browser to drop its own toolbar, so its download and
print buttons do not sit on top of Folio's. That is presentation only. The
parameters are advisory, several browsers ignore them, and the file's URL is
reachable whatever the viewer displays.

Nothing about hiding a button restricts access. A document that must not be
downloaded needs `pdf_access` set to restricted or hidden, which is enforced when
the request reaches the server.

### Derivative images

Generating a thumbnail means handing an uploaded file to an image decoder,
which is a meaningful attack surface: decoders are complex, and ImageMagick's
delegates in particular have a long CVE history. The feature is therefore
bounded rather than trusted.

Dimensions are read with `pingImage()` before any pixels are decoded, and
anything beyond `IMAGE_MAX_PIXELS` is refused. This is what stops a
decompression bomb — a small file declaring enormous dimensions — from
exhausting memory, and it costs nothing because the image is never decoded.
Memory, wall-clock, and thread ceilings apply to every conversion.

The delivery route honours only the widths in `THUMB_WIDTHS`. Without that, a
visitor could request thousands of arbitrary sizes and fill the disk. It
enforces the same path containment and exclusion rules as every other delivery
route, so an excluded file has no derivative and returns 404 rather than
leaking through a second door.

Derivatives are written only under `data/`, which is denied to the web and
served through PHP. Uploaded files are never modified and never written to.
Generated images are stripped of metadata, so EXIF GPS coordinates and camera
serial numbers are not republished in a public thumbnail.

SVG is never rasterised. It is already a web format and can contain script, so
it continues to be served under the sandboxing policy that applies to active
formats.

`PDF_SERVER_PREVIEW` is off by default because rasterising a PDF invokes
ImageMagick's PDF delegate, which has a poor security record. Folio's in-browser reader previews PDFs without it, so
the default costs nothing. Turn it on only for libraries whose documents you
control.

If no image engine is installed, every part of this is inert and the original
file is served exactly as before.

A PDF's page-one thumbnail is never rasterised from the original file when
the document has redaction regions set. It is rasterised from the same
redacted derivative the document itself serves, so a black box drawn over
page one cannot be bypassed by hovering the file in a listing. If the
redacted derivative cannot be built, the thumbnail route serves no preview
at all rather than ever falling back to the unredacted page — the same
fail-closed rule that governs the redacted PDF delivery itself.

A video's hover/listing preview — one extracted frame, and where ffmpeg
supports it, a short silent looping clip — is generated the same way and
under the same bounds as the PDF preview above: a source file over
`VIDEO_PREVIEW_MAX_SOURCE_MB` is skipped rather than encoded, and every
`ffmpeg` invocation carries the timeout and process-boundary protections
described in External utilities below. The moving clip strips audio
unconditionally (`-an`), so nothing beyond silent video is ever generated or
served from this route. It is offered for a video whose `video_access` is
`public`, and for any tier to the admin — the same viewer who already gets a
full, unrestricted player for that file regardless of tier; a restricted or
hidden video gives a public visitor no listing preview, moving or still, the
same way it already gives them no direct download link.

A video's hover preview can carry its own redaction, independent of
`video_access`: one or more boxes drawn over the reference frame, blurred
into both the still frame and the moving clip. This affects only the hover
preview — the small poster and the short clip — never the full video played
from the document page, which is unrestricted for whoever the access tier
already permits. Like the PDF and image thumbnail paths, this is generated
through `ffmpeg`'s own filter graph (crop, blur, overlay back at the same
position) rather than composited from a separate, potentially inconsistent
step, so there is no code path that could emit the unblurred frame by
mistake: if the filtered render fails for any reason, the function returns
nothing rather than falling back to an unfiltered one. The cache key for
both the still frame and the clip folds in a hash of the redaction regions,
so editing or clearing them invalidates the cached derivative rather than
continuing to serve one built under the previous settings.

### External utilities

Folio can call command-line programs — OCRmyPDF, Tesseract, Poppler,
pngquant, ffmpeg — when a server provides them. That means user-controlled filenames reach a
process boundary, so the mechanism matters more than the feature.

Programs are started with `proc_open()` given an **argument array**, with
`bypass_shell` set. No shell is spawned, so shell metacharacters in a filename
are not special: a file named `x.pdf"; rm -rf /; echo "` is passed to the
program as that exact literal name and treated as a missing file. This is what
makes it safe for Folio, whose filenames arrive over FTP and are never
sanitised by the application, to call external programs at all. `shell_exec`,
`exec`, `system`, `passthru`, and backticks appear nowhere in the codebase,
and the regression suite fails the build if any of them is introduced or if
`proc_open` is used without `bypass_shell`.

Utilities are located only in the directories listed in `TOOL_SEARCH_PATHS`,
or named outright in `TOOL_PATHS`. `$PATH` is deliberately not consulted: it is
inherited from whatever started PHP, and on shared hosting it is not something
worth trusting to decide which binary runs. A utility that is not found
resolves to `null`, never to a bare command name, so a failed lookup cannot
become a `$PATH`-resolved execution. Names are validated against a strict
pattern even though they are internal constants.

Every invocation carries a timeout and an output cap, so a malformed or
hostile document cannot hang a request or exhaust memory. Utilities inherit a
minimal environment rather than the request's.

OCR is admin-only and CSRF-protected, and applies the same path containment
and exclusion rules as every other action. It is never triggered by a
visitor's page load: it takes seconds to minutes, and that is not work to do
inside a page request.

PDF pages are rendered with Poppler, which reads PDFs directly. ImageMagick's
own PDF support is reached only when `PDF_ALLOW_GHOSTSCRIPT` is explicitly set
to true; it defaults to false, and the regression suite fails the build if
that default changes or an unguarded ImageMagick PDF read appears. Both OCR
routes avoid it as well.

With that setting false and Poppler absent, PDF previews are simply not
generated and the original file is served — a missing capability, not an
error.


### Canonical addressing

Canonical URLs, Open Graph URLs, sitemap entries, and JSON-LD identifiers are
all built from the configured `SITE_URL`. The request `Host` header is never
trusted, so it cannot be used to poison a cached page or a structured-data
identifier. `X-Forwarded-Proto` is honoured only when `TRUST_PROXY_HEADERS`
is explicitly enabled, which should only be done behind a proxy that
overwrites that header.

### Image redaction

An image carrying redaction regions is never served to the public in its
original form. The public link points at `?action=image_redacted`, which
returns a copy with opaque boxes drawn into the pixels and re-encoded as
JPEG, so nothing of what was underneath survives in the file.

Withholding the original's URL is not on its own access control, so
`?action=raw` refuses a redacted image outright for anyone who is not signed
in — that is the check that holds when someone simply guesses the path.
Deliberately *not* conditional on `IMAGE_GATE_CONFIRMED`: a redaction is an
explicit instruction to cover something, and honouring it only when a
separate preflight happens to have been confirmed would be the wrong default.

Thumbnails are generated from the redacted derivative, never the original. A
320px thumbnail is small but perfectly legible for a name or a number, so a
thumbnail built from the source would defeat the whole feature.

Both paths fail closed. If Imagick is missing or the render fails, the route
and the thumbnail each return nothing rather than falling back to the
unredacted file — the failure mode of a redaction feature must never be
"publish the original".

The derivative is stripped of every profile and EXIF block. The metadata of
the original can describe the very thing the box covers — a location, a name,
a camera serial — and an embedded EXIF thumbnail is a small copy of the
unredacted picture. Verified: the derivative carries no EXIF and no embedded
thumbnail.

An administrator still sees the original, since they drew the boxes and need
to check the boxes cover what they meant.

### Image access control

Images have the same three tiers as PDFs and video — public, restricted,
hidden — and, like PDF, this is genuinely enforced rather than
delisting-only: an image is small enough to serve through Folio without the
seeking and bandwidth cost that made video's lighter model the right call.

Enforcement requires both a non-empty `FOLIO_URL_SIGNING_KEY` and
`IMAGE_GATE_CONFIRMED`, set only after the Crawlers screen's preflight proves
a request for an image actually reaches PHP on this host. Until both hold,
every image behaves as public and the editor says so beside the setting — a
restriction that might not be enforced is worse than none, because the
administrator would believe it held.

A restricted image is delivered through a short-lived signed URL; a hidden one
is not delivered at all, even with a valid token. Tokens are namespaced with
an `image|` prefix, so a token minted for a PDF cannot be replayed against an
image of the same path, and vice versa. Thumbnails follow the tier: a
restricted photo whose 320px version was public would not be restricted.

Verified against a real Apache: with the gate off nothing changes; with it on,
a restricted image with no token, a forged token, an expired token, a
PDF-namespaced token, a hidden image with a valid token, and a thumbnail of a
gated image all return 404, while a public image, a valid signed token, and an
admin session all return 200.

Folio's preflight probe files are dotfiles, so they never clutter a folder
listing — but the scanner hardening blocks dotfiles, which would have left
every preflight permanently unable to test the thing it exists to test. The
probes are exempted by name in both `.htaccess` files. `.env`, `.git/config`
and editor leftovers remain blocked.

### Hardening against automated scans

Every public site is scanned continuously for a forgotten webshell or another
application's admin panel. The root `.htaccess` refuses these before PHP
starts, and `lib/redirects.php` declines to record them so the 404 Monitor
keeps showing genuine broken links rather than attack noise.

Refused at the webserver: any executable extension (`index.php` explicitly
exempted, since it is the application); dotfiles, with `.env` and `.git/config`
the ones that matter, as both routinely carry credentials; editor and deploy
debris (`.bak`, `.old`, `~`, `.swp`), which is worse than it sounds because
`index.php.bak` is source code served as plain text — the extension no longer
says PHP, so nothing executes it; database and config extensions; and the
`.git`, `.svn`, `node_modules`, `.idea` and `.vscode` directories. Directory
listing is off, so a folder added later cannot hand out an index of itself.

Verified against a real Apache 2.4 rather than by reading the rules:
`credit.php`, `wp-mails.php`, `adminer.php`, `.env`, `.git/config`,
`index.php.bak`, `config.php`, `data/notfound.json` and a bare directory all
returned 403, while the library, `index.php`, an uploaded file, the stylesheet
and `robots.txt` all still returned 200.

Let's Encrypt is unaffected. `FilesMatch` tests the filename alone, never the
path, and an ACME challenge token does not begin with a dot, so renewal
through `/.well-known/acme-challenge/` still works.

`X-Powered-By` is unset, since PHP announcing its exact build tells a scanner
which exploits to try. The `Server:` header is **not** something `.htaccess`
can change — that is `ServerTokens`, which Apache reads only from its main
configuration. Confirmed by testing: the header still reports the version.
Ask your host for `ServerTokens Prod` if that matters to you; Folio does not
claim to have closed it.

**On answering scanners with a message.** Technically possible with
`ErrorDocument`, and measured: an insulting page is actually *smaller* than
Apache's default 403 body, so it costs nothing in bandwidth. It is still not
shipped, for a better reason — a scanner is a script that reads a status code
and moves on. Nothing is on the other end to read the message. What a custom
body does change is that a distinctive one makes a site fingerprintable:
identical wording across every Folio install is a signature saying which
software is running, which is the opposite of what hardening is for. A plain
refusal tells an attacker nothing.

### Contact form

The contact page emails a visitor's message to `PUBLISHER_EMAIL`. The
recipient address is read from server-side configuration at the moment the
message is built and is never rendered into HTML, never placed in a hidden
field or data attribute, never sent to JavaScript, and never accepted as
input — so there is no request that can redirect a submission to a different
address. `contact_send()` takes no recipient parameter for exactly that
reason.

The visitor's own address is used as `Reply-To`, never as `From`. Sending as
the visitor would fail SPF and DMARC on any correctly configured domain and
get the message rejected or spam-foldered; the site sends as itself
(`CONTACT_SENDER_EMAIL`, or `no-reply@` its own domain) so replies still
reach the visitor while the mail is legitimately sent.

Every value that reaches a mail header passes through `contact_header_safe()`,
which strips CR, LF, and other control characters. This is what prevents
header injection: a newline in a subject would otherwise end the Subject line
and let a submitter add `Bcc:` of their own.

Attachments are read from PHP's own temporary upload file, attached, and
unlinked on every exit path — success, validation failure, and delivery
failure alike. They are never moved into `uploads/`, never become documents,
and never acquire a URL. Type checking is an allowlist by extension *and* a
content sniff via `finfo`, which must agree: a PHP script renamed `.png` is
detected as `text/x-php`, disagrees with what `.png` should be, and is
refused. `$_FILES['type']` is visitor input and is ignored entirely.
`is_uploaded_file()` guards against a crafted request naming an arbitrary
server path as its temporary file, and the visitor's filename is used only as
a label inside the email, never as a path.

Anti-spam is layered: a honeypot field hidden from both people and assistive
software, a minimum time between page load and submission, crude content
checks (a message that is nothing but a URL, or carries a wall of them), and
per-submitter rate limiting. Which layer objected is never reported —
a bot told "honeypot detected" is a bot that gets fixed — so every anti-spam
rejection is worded identically to a delivery failure.

Rate limiting never stores a raw IP address. The address is truncated to its
network (/24 for IPv4, /48 for IPv6), then salted with the install's own
private key and hashed, so the stored value cannot be reversed or matched
against addresses from anywhere else. Entries expire after an hour and the
file prunes itself.

Nothing a visitor submits is stored. There is no inbox, no log of message
contents, and no retained attachment: the email is the only copy. A delivery
failure is recorded in the server error log as a one-line diagnostic with no
message content, and the visitor sees a generic failure that reveals nothing
about the mail configuration.

The page carries a session-bound CSRF token, so it is deliberately excluded
from the public cache that every other standalone page uses — a cached copy
would hand the next visitor a token that is not theirs.

The administrator's test-email function sends only to the configured
recipient. It has no destination field, deliberately: an authenticated mailer
that accepts an arbitrary address is an open relay waiting to be found.

### Preview derivatives follow the access tier

A video's hover preview is roughly four seconds of the actual footage and its
thumbnail is a real frame of it, so both are as revealing as the video. Until
1.52.2 neither `?action=video_preview` nor `?action=thumb` consulted
`video_access` at all: the listing declined to *emit* those URLs for a
restricted or hidden video unless an admin was looking, but withholding a URL
is not access control, and a guessed path returned a playable clip to anyone.

Both routes now check the tier, refusing a non-public video's preview and
thumbnail to anyone who is not signed in. An admin, who already watches the
video itself at any tier, is unaffected. Note that this is enforced regardless
of the opt-in webserver guard: video's delisting-only default still means the
public is not meant to see these frames, and a URL that returns one anyway is
a leak rather than a delisting.

The path obscuring described below remains useful but was never a substitute
for this: it hides the address in the page's HTML, which does nothing about an
address someone guesses.

### Cache clearing

`data/` holds several disposable caches. Clearing one is a POST behind the
same admin authentication and CSRF check as every other write. The action
takes a *key* into a fixed list (`folio_caches()`) and never a path, so no
request can name a directory of its own; an unknown key is refused rather than
interpreted. The resolved directory is additionally checked to be genuinely
inside `data/` before anything is removed, which a symlink could otherwise
arrange, and symlinks encountered during the walk are unlinked rather than
followed.

### Hover-preview path obscuring

A restricted or hidden video's hover-preview thumbnail and moving clip are
only ever shown to an admin — but the URL for them still ends up written
into the page's own HTML, and under the default delisting-only model
(guard off, the common case), that URL is the file's own literal,
permanently-guessable direct path. If that HTML were ever exposed —
screenshotted, cached, scraped by a browser extension — the path itself
would be exposed with it, regardless of anything the video access-control
guard does.

`video_obscure_path()`/`video_unobscure_path()` close this specifically:
AES-256-GCM, authenticated so a tampered token fails outright rather than
decrypting to a different, wrong path, under a dedicated key
(`data/.obscure-key`) generated once on first use and never requiring
manual setup — unlike `FOLIO_URL_SIGNING_KEY`, this protects every install
by default, not only ones that have opted into the PDF or video gate.
`?action=thumb` and `?action=video_preview` try to decode the `file`
parameter as an obscured token first, falling back to treating it as a
plain path if that fails — so an ordinary link (public content, or any
install from before this existed) keeps working unchanged, and a public
video's own hover preview stays a plain, cacheable URL, since there is no
secrecy benefit to obscuring a path anyone can already see linked. Verified
with a real forged/tampered token (rejected) and a real round trip through
the live routes (an obscured token correctly still resolves to and serves
the right file).

Not fixed by this: the admin's own "Preview" button still links to the
file's bare direct URL, because it points at a file Apache serves directly,
with no PHP route in the path to decode an obscured reference against.
Tracked in `docs/upgrading.md`'s roadmap, under Known issues.

### Video access control

By default, video follows a delisting-only model: a restricted or hidden
video's URL is simply never emitted to a visitor who shouldn't have it — the
bytes themselves are still directly reachable by anyone who already holds
the exact URL. This is a deliberate, settled trade-off (documented in the
Crawlers screen: "not secret from someone who already holds its direct
URL... for a personal archive this is usually the right balance of speed and
privacy") and is not what's described below.

An opt-in guard (`video_guard_active()`, mirroring `pdf_access_enforced()`'s
exact two-condition shape) exists to move video from that delisting-only
model to one enforced at the webserver: with it on, direct requests for any
video file are refused unconditionally by Apache, and every video is instead
served through `?action=raw`, signed the same way a restricted PDF already
is — `hash_hmac('sha256', ...)` under `FOLIO_URL_SIGNING_KEY`, verified with
`hash_equals`, a short expiry, hidden refused unconditionally. Enforcement
requires both a non-empty `FOLIO_URL_SIGNING_KEY` and `VIDEO_GATE_CONFIRMED`,
a setting turned on only from the Crawlers screen after its own preflight
succeeds — the same fail-safe shape as `PDF_GATE_CONFIRMED`, checked live on
every request rather than trusting a stale flag, so a signing key cleared
after the fact immediately stops being trusted.

Confirming is a two-step, verify-before-trust flow, the same reasoning as
the PDF gate's own preflight but adapted for a real difference: a PDF's
preflight is read-only (does a request already reach `?action=raw`?), while
testing video's rule requires writing it first — there is nothing to
observe about a deny rule that has not been written yet. The "Test video
routing" button calls an admin-only endpoint that performs a true dry run:
write the rule, make an outbound request to a reserved probe file and check
for a genuine 403, then unconditionally undo the write before responding,
whatever the result. That endpoint can never be the thing that leaves
Apache refusing video while Folio still believes the guard is off, because
it never leaves a change in place. Only "Confirm and enforce" — a separate,
independently-verified POST — makes a persistent change, and even that
rolls back the `.htaccess` write if its own verification fails, so a
half-succeeded confirmation can never leave the two sides disagreeing about
whether video is actually being blocked.

An earlier version of this mechanism had `.htaccess` check a token's shape
— 64 hex characters, an 8-or-more-digit expiry — rather than its signature,
because `mod_rewrite` has no way to compute or hold an HMAC key at all. A
regex can only confirm a token looks right, never that it is right, so
anyone who noticed the shape being matched could construct a passing query
string with no knowledge of the actual key. Compounding it, nothing in
Folio ever generated a matching token for a direct video URL in the first
place, so enabling the guard would have simultaneously let a forged request
through and refused every legitimate one, including the admin's own. Fixed
by removing every conditional path from the `.htaccess` rule — it now
refuses unconditionally, with no shape it can be tricked into accepting —
and building the same real, PHP-verified signing PDF's restricted tier
already used correctly. A second, unrelated piece of code — predating this
fix, on the Crawlers screen — automatically cleared the guard's state every
time that screen loaded, which would have silently undone an admin's own
confirmation the moment they landed back on the same page after confirming
it. Removed; its original justification (forcing video through PHP causing
slow buffering for a logged-in visitor) was a session-locking bug fixed
separately and is no longer applicable.

Verified end to end against a real running instance, not just read: a
forged token, an expired token, a missing token, and a hidden video with
any token all correctly refused; a genuine valid token correctly served the
file with Range support intact; the confirm handler correctly refuses to
enable enforcement when its own verification shows the rule isn't actually
blocking anything, with a clear reason why; the guard's state now persists
across repeated page loads rather than resetting; and disabling correctly
restores the default delisting-only model.

A second cause of the same "gated video buffers, public video doesn't"
symptom was found afterward: `stream_file_bytes()`, the function this gate
routes non-public video through, never raised PHP's execution-time limit
before streaming. Public video is served directly by the webserver and
never touches PHP at all, so it was never exposed to this; a gated video
of any real size or over a slow connection could easily outlast a shared
host's default `max_execution_time` (commonly 30s), and PHP would kill the
process mid-transfer rather than finish sending it — indistinguishable at
the player from ordinary buffering that simply never resolves. Fixed with
an uncapped `set_time_limit(0)` at the top of the function, covering both
of its call sites (the gated-video fallback and the cached hover-preview
clip) in one place.

A restricted or hidden video's blurred hover-preview frame — a separate
feature from the byte-gating above, generated by `video_blur_generate()`
in `lib/video.php` — can fail to generate for reasons a public response
must never explain: ffmpeg or Imagick missing, the source file unreadable,
`data/previews/` unwritable. `video_blur_log()` records the specific
reason to the server's own PHP error log only; the public-facing response
(`?action=video_blur_preview`) is a plain 404 in every case, carrying no
path, binary name, or exception detail regardless of what was logged
server-side. Controlled by `VIDEO_BLUR_DIAGNOSTICS` (on by default, safe
to leave on — nothing it logs reaches the browser); set false in
`config.php` to silence the entries entirely.

### PDF access control

A PDF's `pdf_access` (public/viewer/hidden) is enforced at exactly one place,
`?action=raw`, which every other path to a PDF's bytes (detail-page preview,
flip-view reader, print, direct link, hover preview) is built to route
through rather than duplicate the check. "Restricted" PDF URLs are signed with
`hash_hmac('sha256', ...)` under `FOLIO_URL_SIGNING_KEY`, verified with
`hash_equals`, and carry a short expiry; "hidden" is refused unconditionally,
with no valid URL of any kind.

This is a fail-safe design, not a fail-open one: enforcement requires both a
non-empty `FOLIO_URL_SIGNING_KEY` and an explicitly confirmed routing
preflight (`PDF_GATE_CONFIRMED`, set only after the Crawlers screen verifies
that requests to a real file actually reach `?action=raw`). Absent either
condition, every PDF behaves as public regardless of its stored setting,
with a visible warning in Diagnostics and in each affected file's editor.
The alternative — restricting based on a setting that might not actually be
enforced on a given server — would be a false sense of security, which is
worse than no restriction at all.

The preflight itself makes one outbound HTTPS request, from the server to
its own `SITE_URL`, to verify the rewrite independently rather than trust
the browser's report alone (the same pattern the IndexNow submission already
uses). This request is admin-authenticated (only reachable through the
Crawlers screen's POST handler) and targets only the configured `SITE_URL`,
never a value derived from the request; see "Canonical addressing" above for
why that's safe from Host-header injection. If the request fails outright —
common on hosts that block outbound HTTP — Folio falls back to trusting the
browser's own successful probe rather than blocking the feature entirely.

**This feature requires Apache or LiteSpeed** (see "Deployment expectations"
below — Nginx isn't a supported deployment target at all). The preflight
above simply never confirms on an unsupported server, so every PDF
continues to behave as public — the same fail-safe fallback as any other
unconfirmed server, never a silent gap.

A related but distinct signal is whether a *page* Google is allowed to reach
should be indexed at all: `SITE_INDEXABLE` (off while a library isn't ready
to be found) flips every HTML page's `<meta name="robots">` between
`index, follow` and `noindex, nofollow`. A PDF served through `?action=raw`
has no `<head>` of its own for that meta tag to live in, so `X-Robots-Tag`
is the only way the raw bytes can carry the same signal — and until 1.50.21,
this route asserted `index, follow` unconditionally regardless of
`SITE_INDEXABLE`, meaning a library explicitly marked not ready to be found
was still telling Google to index every PDF served through it. Fixed to
follow `SITE_INDEXABLE` the same way the HTML pages already do; unrelated
to the `pdf_access` enforcement above, which governs whether a given PDF's
bytes are reachable at all, not whether a reachable one should be indexed.

### Stored state

Accounts, settings, metadata, and standalone pages are written through a
locked, atomic replace: write to a temporary file, fsync, rename into place,
and retain a last-known-good `.bak`. A malformed or partially written store is
rejected rather than adopted, and the previous good copy is preserved.

**Known weakness:** `data/users.php` and `data/settings.php` are PHP files
loaded with `include`. They are runtime-writable, so any unrelated
arbitrary-file-write flaw could be escalated to code execution. Moving these
to a non-executable format is planned. Until then, keeping `data/` outside the
document root, or read-only except when settings are being changed, is the
strongest available mitigation.

### Deployment expectations

Folio cannot enforce these from inside the application:

- Serve the site over HTTPS.
- Keep `data/` unreadable from the web. The shipped `data/.htaccess` denies
  access on Apache and LiteSpeed. Nginx is not a supported deployment
  target (see `readme.md`) and has no equivalent rule maintained by Folio.
- Delete `install.php` after installation, and confirm the Diagnostics screen
  reports it as removed.
- Give the web-server account the narrowest ownership that still allows
  writing to `data/` and reading `uploads/`. The application root does not
  need to be writable during normal operation.
- Set `FOLIO_AUTH_PEPPER` and keep it out of version control. Changing it
  later invalidates every stored password.
- Treat the IndexNow key in `data/settings.php` as a secret; it is excluded
  by the shipped `.gitignore`.
- If you rely on PDF access control ("restricted"/"hidden"), confirm the
  preflight on the Crawlers screen after every deploy that touches
  `.htaccess`, and re-confirm after moving hosts. It fails safe to public
  rather than silently, but a restriction you believe is active and isn't is
  still worth catching promptly.

### Not protected

Folio is fundamentally a public library: every account has full
administrative authority, and anyone with FTP access to `uploads/` controls
the library's contents, which is the intended workflow rather than a flaw.
Documents are public by default and remain so unless explicitly restricted.

The one exception is PDF access control (above): a PDF explicitly set to
"restricted" or "hidden," on a server where that's confirmed enforced, is not
reachable at its plain URL. Video is deliberately different and lighter. Its
"restricted" and "hidden" tiers **delist** a clip rather than withhold it. The
player and the file's URL are not shown to the public, who see a notice in
place of the player, yet the file itself is served directly by the webserver
and is **not gated**: there is no webserver block, no signed URL, and no
byte-level check. The direct URL is derived from the filename, so a restricted
or hidden video must be treated as reachable by anyone who has, or can
construct, that URL, and any clip that was public before it was hidden is
already crawled and cached. This is intentional, an accepted trade-off for
direct-serve playback speed (see changelog 1.38.0), and it is the settled
design: video tiers hide a clip from view, they do not make it private. A
video that must be private should be kept out of the library entirely (for
example with `EXCLUDE_PATTERNS`) or restricted at the webserver, rather than
relying on the tier. Every other file format has no per-file access control.
`EXCLUDE_PATTERNS` can hide a file from listings and public URLs entirely, and
there is no partial-access tier beyond PDFs and video.

Note that a "restricted" document's *detail page* is intentionally public and
indexable — that is the point of the tier: the page (title, description,
transcript, structured data) is meant to be found in search, while the file
itself is withheld. A crawlable page for a restricted document is by design,
not an exposure. A "hidden" document's page is also indexable (so it can be
found via search) but is removed from the folder listing, so the public
reaches it only through a search engine or direct link.

## Reporting a vulnerability

Please do **not** open a public issue or pull request for security problems.

Instead, send a private report to the maintainer with the details. Include:

- A description of the issue and its impact.
- The Folio version, PHP version, and web server (Apache, LiteSpeed, Nginx).
- Steps to reproduce, or a proof of concept if you have one. A minimal HTTP
  request or a short script is ideal.
- Whether the issue is already public and, if so, where.

You should receive an acknowledgement within a few days. If you have not,
please follow up.

## What counts as a vulnerability

Yes, please report:

- Any way to bypass the login, read `data/users.php`, or read `data/settings.php`
  as a public visitor.
- Any way to read a file that `EXCLUDE_PATTERNS` should hide.
- Any way to reach a "hidden" PDF's bytes, or a "restricted" PDF's bytes without a
  valid, unexpired signature, on a server where PDF access control is
  confirmed enforced (`PDF_GATE_CONFIRMED` true and `FOLIO_URL_SIGNING_KEY`
  set). A server where enforcement is not confirmed is expected to serve
  every PDF as public — that is not a vulnerability, it's the documented
  fail-safe default. This applies to PDFs only: reaching a "restricted" or
  "hidden" **video** at its direct URL is expected and intentional (the video
  tiers delist a clip, they do not withhold it, as described above), so it is
  not a vulnerability and does not need reporting.
- Any way to read or write files outside `uploads/` and `data/`.
- Any way to execute arbitrary PHP or shell commands.
- Any way to make Folio serve an active file format (HTML, XML, MHTML) inline
  same-origin instead of forcing an attachment download.
- Any way to poison a canonical URL, an Open Graph URL, a sitemap entry, or a
  JSON-LD `@id` through the request Host header or another untrusted input.
- Any way to corrupt or delete the metadata store, the settings store, the
  accounts store, or the pages store, or to observe a partial write from
  another request.
- Any way to keep an authenticated session alive after the account is deleted,
  its password is changed, or its password is reset.
- Anything that would let a stored file preview or a stored page body execute
  script in a visitor's browser.
- Anything that could let an anonymous request cause outbound HTTP from the
  server, other than the deliberate Bing ping, IndexNow submit, and PDF
  access-control preflight self-verification, which are admin-authenticated
  and target hardcoded, allowlisted hosts or the configured `SITE_URL`.

Not a Folio issue:

- Vulnerabilities in a PHP extension, in Apache, LiteSpeed, or Nginx.
- Vulnerabilities in Parsedown itself (report those upstream). Parsedown is
  run in safe mode, so raw HTML in Markdown is escaped.
- Issues that require an attacker to already have FTP access to the server.
  FTP is Folio's deliberate content-upload channel; the trust boundary is at
  the FTP account.
- Denial of service through large uploads or unbounded requests. Rate limiting
  belongs at the web-server or CDN layer.
- Missing features that would improve defense in depth but do not cross a
  trust boundary. Suggestions welcome as regular issues.

## Disclosure

Once a fix is prepared, we will coordinate a release with you. A brief
credit will be added to the changelog unless you ask to remain anonymous.
