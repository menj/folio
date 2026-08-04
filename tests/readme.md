# Testing Folio

Folio ships one test suite: an integration smoke test that exercises a real
installation over HTTP. There is no unit-test framework and no browser-driver
suite, which is a deliberate trade for a single-file application with no
Composer dependency — but it does mean the coverage boundaries below are worth
reading before you rely on a green run.

## Running the suite

From the Folio root:

```sh
bash tests/smoke.sh
```

It prints one `PASS:` line per group, exits `0` when everything passes, and
exits `1` on the first failure with a `FAIL:` line naming what broke.

## Requirements

- **PHP command line**, matching the version the site runs on. The suite uses
  PHP's built-in development server, so no separate web server is needed.
- **curl**, and standard POSIX tools (`grep`, `sed`, `mktemp`, `touch`).
- **The `mbstring` extension**, which Folio requires at runtime.
- The suite deliberately avoids optional extensions. It parses XML with
  pattern matching rather than SimpleXML, because SimpleXML is absent from
  some PHP builds and a test suite should not fail for a reason unrelated to
  the code under test.

Set `FOLIO_TEST_PORT` if the default port `18765` is in use:

```sh
FOLIO_TEST_PORT=19000 bash tests/smoke.sh
```

## How it works

Each run builds a throwaway installation in a temporary directory: a fresh
`config.php` with a generated password hash, an empty `data/`, and a small
fixture library. It starts PHP's development server against that directory,
runs every check over HTTP as a real client would, then removes the directory.

Nothing touches your live installation, and no test depends on the order you
ran it in — except where noted below, since a few checks deliberately build on
the state left by the previous one.

The fixture library is small but awkward on purpose:

| Fixture | Why it exists |
| --- | --- |
| `foo.pdf` and `Foo!.pdf` | Two files whose names normalise to the same slug, to prove collisions are disambiguated |
| `foo.jpg` | Same stem as `foo.pdf`, to prove the extension participates in addressing |
| `evil.html` | An active format that must be forced to download, never rendered in-origin |
| `notes.txt` | A plain file used for metadata and `lastmod` checks |
| `host.txt` | A symbolic link to `/etc/hostname`, which must never be served |

## What is covered

| Group | What it proves |
| --- | --- |
| Anonymous caching and canonical host | Public pages set no cookie, stay cacheable, and build canonical URLs from `SITE_URL` even when the request `Host` header claims otherwise |
| Symbolic-link containment | A symlink pointing outside `uploads/` is not followed or served |
| Collision-safe addressing | Colliding names get distinct slugs; unambiguous legacy slugs redirect; ambiguous ones 404 |
| Controlled file delivery | Active and unknown formats are forced to `application/octet-stream` with an attachment disposition, a sandboxing CSP, and `noindex` |
| Invalid directory | A nonexistent folder returns 404 rather than an error page |
| Metadata storage | An authenticated edit is written atomically and leaves a last-known-good `.bak` |
| JSON-LD injection | Metadata containing `</script>`, mixed-case variants, ampersands, and quotes cannot terminate the structured-data element or create markup |
| Malformed metadata | A corrupt store is rejected rather than overwritten, and the valid copy survives |
| Session revocation | Resetting a password invalidates sessions already holding the old `auth_version` |
| Logout protection | `GET` logout is refused with `405`, a tokenless `POST` is refused, and only a valid CSRF `POST` ends the session |
| Installer headers | The installer emits a CSP with no `unsafe-inline`, forbids framing, and is not cacheable |
| Sitemap `lastmod` | Editing one document's metadata moves only that entry's date, without touching the file on disk |
| Sitemap partitioning | Small libraries stay a single `urlset`; invalid, negative, and out-of-range part numbers 404 |
| Stateless sitemap | The sitemap generates without a session and reflects the current library |

## Security regression payloads

The JSON-LD check writes real attack strings through the ordinary metadata
form and then inspects the rendered page. The payloads include
`Report </script><img src=x onerror=alert(1)><script>`, a mixed-case
`</ScRiPt><svg onload=alert(2)>`, and values containing ampersands,
apostrophes, and quotation marks.

The expected result is that every one survives as *data*: each JSON-LD block
still parses as JSON, the raw text contains no closing script tag, and no
`img`, `svg`, `iframe`, or `form` element appears in the document. A failure
here means output encoding regressed, which is a release blocker.

This check was confirmed to fail against the pre-1.0.1 encoder, so it is known
to detect the bug it guards rather than merely passing.

## What is not covered

Being explicit about this matters more than the list above, because a green
run is easy to over-read.

- **Concurrency.** PHP's development server handles one request at a time, so
  the suite cannot exercise parallel logins or simultaneous administrative
  writes. The login throttle's locking was verified separately by running
  eight processes performing twenty-five increments each against one counter
  and confirming all two hundred were recorded; that harness is not part of
  the suite. Concurrent administrative edits are a known gap.
- **External services.** No test contacts IndexNow, and nothing is mocked,
  because the suite never triggers a submission. Batching is verified by
  reasoning about `array_chunk` boundaries rather than by observing requests.
- **Scale.** There is no large-library dataset. Sitemap partitioning is
  verified structurally, by lowering `SITEMAP_MAX_URLS` in a scratch copy and
  confirming the index and parts are correct, rather than by generating fifty
  thousand files.
- **Browsers.** No JavaScript is executed. Client-side search, hover cards,
  the PDF flip reader, keyboard navigation, and CSP enforcement in a real
  browser are all unverified by this suite.
- **Upgrades.** There is no automated test that upgrades a populated older
  installation.

## Adding a test

Follow the existing shape: perform the request, assert on the response, then
call `pass 'short description'`. Use `fail 'what went wrong'` for a failure so
the suite exits non-zero.

Two habits are worth keeping. Assert on the smallest thing that proves the
behaviour, so a failure names the cause rather than a symptom. And before
trusting a new regression test, break the fix it guards and confirm the test
actually fails — a test that cannot fail is worse than no test, because it
looks like coverage.
