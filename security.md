# Security policy

Thank you for taking the time to look into Folio's security.

## Supported version

Folio is a small single-file application shipped as a numbered release. Only
the most recent release receives security fixes. If you are running an older
release, upgrade before reporting an issue.

The current supported release is **1.29.0**.

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
downloaded needs `pdf_access` set to viewer or hidden, which is enforced when
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

### External utilities

Folio can call command-line programs — OCRmyPDF, Tesseract, Poppler, pngquant
— when a server provides them. That means user-controlled filenames reach a
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

### PDF access control

A PDF's `pdf_access` (public/viewer/hidden) is enforced at exactly one place,
`?action=raw`, which every other path to a PDF's bytes (detail-page preview,
flip-view reader, print, direct link, hover preview) is built to route
through rather than duplicate the check. "Viewer" URLs are signed with
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
- If you rely on PDF access control ("viewer"/"hidden"), confirm the
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
"viewer" or "hidden," on a server where that's confirmed enforced, is not
reachable at its plain URL. Every other file format, and every PDF on a
server where enforcement isn't confirmed, has no per-file access control —
`EXCLUDE_PATTERNS` can hide a file from listings and public URLs entirely,
but there is no partial-access tier for anything other than PDFs.

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
- Any way to reach a "hidden" PDF's bytes, or a "viewer" PDF's bytes without a
  valid, unexpired signature, on a server where PDF access control is
  confirmed enforced (`PDF_GATE_CONFIRMED` true and `FOLIO_URL_SIGNING_KEY`
  set). A server where enforcement is not confirmed is expected to serve
  every PDF as public — that is not a vulnerability, it's the documented
  fail-safe default.
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
