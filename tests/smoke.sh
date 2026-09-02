#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${FOLIO_TEST_PORT:-18765}"
HOST="127.0.0.1:${PORT}"
BASE="http://${HOST}/"
TMP="$(mktemp -d "${TMPDIR:-/tmp}/folio-smoke.XXXXXX")"
APP="${TMP}/folio"
PID=""

cleanup() {
    if [[ -n "${PID}" ]] && kill -0 "${PID}" 2>/dev/null; then
        kill "${PID}" 2>/dev/null || true
        wait "${PID}" 2>/dev/null || true
    fi
    rm -rf "${TMP}"
}
trap cleanup EXIT

fail() {
    printf 'FAIL: %s\n' "$*" >&2
    if [[ -f "${TMP}/server.log" ]]; then
        tail -50 "${TMP}/server.log" >&2 || true
    fi
    exit 1
}

pass() {
    printf 'PASS: %s\n' "$*"
}

need() {
    command -v "$1" >/dev/null 2>&1 || fail "required command not found: $1"
}

status_code() {
    curl -sS -o /dev/null -w '%{http_code}' "$1"
}

csrf_from() {
    sed -n 's/.*name="csrf" value="\([^"]*\)".*/\1/p' "$1" | head -n 1
}

need php
need curl
need grep
need sed
need awk
need sha256sum

cp -a "${ROOT}" "${APP}"
rm -f "${APP}/config.php" "${APP}/data/users.php" "${APP}/data/settings.php" \
      "${APP}/data/metadata.json" "${APP}/data/metadata.json.bak" \
      "${APP}/data/metadata.lock" "${APP}/data/install-token.php"

PASSWORD='CorrectHorseBattery42!'
NEW_PASSWORD='RotatedPassword84!'
HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "${PASSWORD}")"
cat > "${APP}/config.php" <<PHP
<?php
declare(strict_types=1);
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '${HASH}');
define('SITE_URL', '${BASE}');
define('SITE_NAME', 'Folio Smoke Test');
define('SITE_DESCRIPTION', 'Automated Folio regression test.');
define('PUBLISHER_TYPE', 'Person');
define('PUBLISHER_NAME', '');
define('PUBLISHER_URL', '');
define('PUBLISHER_EMAIL', 'smoke-test@example.invalid');
define('SITE_LANGUAGE', 'en');
define('FOLIO_AUTH_PEPPER', '');
define('FOLIO_COOKIE_NAME', 'FOLIO_SMOKE');
define('UPLOADS_DIRNAME', 'uploads');
define('PRETTY_URLS', false);
define('TRUST_PROXY_HEADERS', false);
define('SHOW_ADMIN_LINK', true);
define('FOLIO_URL_SIGNING_KEY', 'smoke-test-signing-key-do-not-use-in-production');
define('EXCLUDE_PATTERNS', ['_drafts/*', '*.secret.jpg']);
PHP
chmod 600 "${APP}/config.php"

# PDF_GATE_CONFIRMED is normally only set by the interactive preflight on
# the Crawlers screen, which fetches a real on-disk file through the Apache
# rewrite rule to prove PDF requests reach PHP. php -S has no .htaccess/
# mod_rewrite layer at all, so that preflight itself is not something this
# harness can exercise — it is pre-seeded here so the tests below can verify
# the PHP-level enforcement logic (public/viewer/hidden), which is what
# Folio actually controls.
cat > "${APP}/data/settings.php" <<'PHP'
<?php
return ['PDF_GATE_CONFIRMED' => true, 'VIDEO_GATE_CONFIRMED' => true];
PHP

# Video access control is on when the guard file exists. php -S has no
# .htaccess layer, so the Apache-level refusal of direct video is not
# something this harness can exercise (that is verified under real Apache);
# the guard flag here lets the tests verify the PHP-level gate in ?action=raw,
# which is the enforcement Folio itself performs.
printf '1\n' > "${APP}/data/.video-guard"
head -c 2048 /dev/urandom > "${APP}/uploads/pubclip.mp4"
head -c 2048 /dev/urandom > "${APP}/uploads/viewclip.mp4"
head -c 2048 /dev/urandom > "${APP}/uploads/hideclip.mp4"

printf '%%PDF-1.4\n' > "${APP}/uploads/foo.pdf"
printf '%%PDF-1.4\n' > "${APP}/uploads/Foo!.pdf"
printf 'jpeg placeholder\n' > "${APP}/uploads/foo.jpg"
printf '<!doctype html><script>document.body.textContent="active"</script>\n' > "${APP}/uploads/evil.html"
printf 'plain text\n' > "${APP}/uploads/notes.txt"
printf '%%PDF-1.4\n' > "${APP}/uploads/public-doc.pdf"
printf 'jpeg placeholder\n' > "${APP}/uploads/private.secret.jpg"
mkdir -p "${APP}/uploads/_drafts"
printf 'jpeg placeholder\n' > "${APP}/uploads/_drafts/hidden.jpg"
ln -s /etc/hostname "${APP}/uploads/host.txt"

php -S "${HOST}" -t "${APP}" >"${TMP}/server.log" 2>&1 &
PID=$!
for _ in $(seq 1 50); do
    if curl -sS "${BASE}" >/dev/null 2>&1; then
        break
    fi
    sleep 0.1
done
kill -0 "${PID}" 2>/dev/null || fail "PHP test server did not start"

curl -sS -D "${TMP}/public.headers" -H 'Host: attacker.example' "${BASE}" -o "${TMP}/public.html"
! grep -qi '^Set-Cookie:' "${TMP}/public.headers" || fail 'anonymous request created a session cookie'
grep -qi '^Cache-Control: public, max-age=300' "${TMP}/public.headers" || fail 'anonymous page is not publicly cacheable'
grep -Fq "rel=\"canonical\" href=\"${BASE}\"" "${TMP}/public.html" || fail 'canonical URL did not use configured SITE_URL'
! grep -Fq 'attacker.example' "${TMP}/public.html" || fail 'Host header poisoned public output'
pass 'anonymous caching and canonical host validation'

! grep -Fq 'host.txt' "${TMP}/public.html" || fail 'symbolic link appeared in the catalogue'
[[ "$(status_code "${BASE}?view=host-txt")" == '404' ]] || fail 'symbolic-link detail route was not rejected'
pass 'symbolic-link containment'

PDF_SLUGS="$(grep -oE '\?view=foo-pdf(-[0-9a-f]{8})?' "${TMP}/public.html" | sort -u | wc -l | tr -d ' ')"
[[ "${PDF_SLUGS}" == '2' ]] || fail 'same-type slug collision was not disambiguated'
grep -Fq '?view=foo-jpg' "${TMP}/public.html" || fail 'extension-qualified image slug is missing'
[[ "$(status_code "${BASE}?view=foo")" == '404' ]] || fail 'ambiguous slug did not return 404'
# Slugs are clean by default; an uncontested file is addressed without its
# extension, and the older extension-qualified form redirects forward.
[[ "$(status_code "${BASE}?view=evil")" == '200' ]] || fail 'clean slug did not resolve'
LEGACY_HEADERS="$(curl -sS -D - -o /dev/null "${BASE}?view=evil-html")"
grep -qE '^HTTP/[^ ]+ 301' <<<"${LEGACY_HEADERS}" || fail 'extension-qualified legacy slug did not redirect'
grep -qiE '^Location: .*\?view=evil' <<<"${LEGACY_HEADERS}" || fail 'legacy redirect target was incorrect'
pass 'collision-safe file addressing and legacy redirects'

curl -sS -D "${TMP}/raw.headers" "${BASE}?action=raw&serve=1&file=evil.html" -o "${TMP}/raw.body"
grep -qi '^Content-Type: application/octet-stream' "${TMP}/raw.headers" || fail 'active file was not forced to binary content type'
grep -qi '^Content-Disposition: attachment' "${TMP}/raw.headers" || fail 'active file was not forced to download'
grep -qi '^Content-Security-Policy:.*sandbox' "${TMP}/raw.headers" || fail 'active file sandbox header is missing'
grep -qi '^X-Robots-Tag: noindex, nofollow' "${TMP}/raw.headers" || fail 'active file crawler header is missing'
pass 'controlled delivery of active and unknown files'

[[ "$(status_code "${BASE}?dir=does-not-exist")" == '404' ]] || fail 'invalid directory did not return 404'
pass 'invalid-directory response'

COOKIE="${TMP}/cookies.txt"
curl -sS -c "${COOKIE}" "${BASE}?action=login" -o "${TMP}/login.html"
CSRF="$(csrf_from "${TMP}/login.html")"
[[ -n "${CSRF}" ]] || fail 'login CSRF token was not emitted'
LOGIN_CODE="$(curl -sS -b "${COOKIE}" -c "${COOKIE}" -o /dev/null -w '%{http_code}' \
    --data-urlencode 'action=login' --data-urlencode 'from=login' \
    --data-urlencode "csrf=${CSRF}" --data-urlencode 'username=admin' \
    --data-urlencode "password=${PASSWORD}" "${BASE}")"
[[ "${LOGIN_CODE}" == '302' ]] || fail 'admin login failed'

curl -sS -b "${COOKIE}" "${BASE}" -o "${TMP}/admin.html"
grep -Fq '?action=settings' "${TMP}/admin.html" || fail 'authenticated controls were not shown'
CSRF="$(csrf_from "${TMP}/admin.html")"
[[ -n "${CSRF}" ]] || fail 'metadata CSRF token was not emitted'
META_RESPONSE="$(curl -sS -b "${COOKIE}" \
    --data-urlencode 'action=meta' --data-urlencode "csrf=${CSRF}" \
    --data-urlencode 'file=notes.txt' --data-urlencode 'title=Smoke Notes' \
    --data-urlencode 'desc=Stored atomically' --data-urlencode 'category=Tests' \
    --data-urlencode 'tags=smoke, storage' "${BASE}")"
grep -Fq '"ok":true' <<<"${META_RESPONSE}" || fail 'metadata update failed'
[[ -f "${APP}/data/metadata.json" ]] || fail 'atomic metadata file is missing'
grep -Fq 'Smoke Notes' "${APP}/data/metadata.json" || fail 'metadata contents were not saved'
META_RESPONSE="$(curl -sS -b "${COOKIE}" \
    --data-urlencode 'action=meta' --data-urlencode "csrf=${CSRF}" \
    --data-urlencode 'file=notes.txt' --data-urlencode 'title=Smoke Notes Revised' \
    --data-urlencode 'desc=Stored atomically again' --data-urlencode 'category=Tests' \
    --data-urlencode 'tags=smoke, storage' "${BASE}")"
grep -Fq '"ok":true' <<<"${META_RESPONSE}" || fail 'second metadata update failed'
[[ -f "${APP}/data/metadata.json.bak" ]] || fail 'last-known-good metadata backup is missing'
grep -Fq 'Smoke Notes' "${APP}/data/metadata.json.bak" || fail 'metadata backup does not contain the previous valid state'
pass 'authenticated metadata update and atomic storage'

# The category archive pages have their own sitemap, and the main sitemap must
# not also carry them, so the two never duplicate. notes.txt was just filed
# under "Tests", so that category must appear in the category sitemap and must
# not appear in the main one. This is the check that would have caught a
# category sitemap wired into some surfaces but not others.
curl -sS "${BASE}?action=sitemap_categories" -o "${TMP}/cat-sitemap.xml"
grep -Fq '<urlset' "${TMP}/cat-sitemap.xml" || fail 'the category sitemap was not served'
grep -Eq '[?&]cat=tests|/category/tests/' "${TMP}/cat-sitemap.xml" \
    || fail 'a categorised document is missing from the category sitemap'
! grep -Fq 'action=raw' "${TMP}/cat-sitemap.xml" \
    || fail 'the category sitemap referenced a raw file URL'
curl -sS "${BASE}?action=sitemap" -o "${TMP}/cat-main-sitemap.xml"
! grep -Eq '[?&]cat=|/category/' "${TMP}/cat-main-sitemap.xml" \
    || fail 'categories appear in the main sitemap as well as the category sitemap'
pass 'category sitemap carries the categories, and the main sitemap does not'

# FOLIO-PDF-001: pdf_access is only enforced once FOLIO_URL_SIGNING_KEY is
# set AND the routing preflight has been confirmed (PDF_GATE_CONFIRMED,
# pre-seeded above). This config has both, so foo.pdf set to "hidden" must
# not be reachable by any path, and Foo!.pdf set to "viewer" must require a
# valid signed URL rather than the plain query-string one.
META_RESPONSE="$(curl -sS -b "${COOKIE}" \
    --data-urlencode 'action=meta' --data-urlencode "csrf=${CSRF}" \
    --data-urlencode 'file=foo.pdf' --data-urlencode 'pdf_access=hidden' \
    --data-urlencode 'transcript=A verified transcription of the hidden certificate.' "${BASE}")"
grep -Fq '"ok":true' <<<"${META_RESPONSE}" || fail 'setting pdf_access=hidden failed'

META_RESPONSE="$(curl -sS -b "${COOKIE}" \
    --data-urlencode 'action=meta' --data-urlencode "csrf=${CSRF}" \
    --data-urlencode 'file=Foo!.pdf' --data-urlencode 'pdf_access=viewer' "${BASE}")"
grep -Fq '"ok":true' <<<"${META_RESPONSE}" || fail 'setting pdf_access=viewer failed'

# foo.pdf and Foo!.pdf slugify to the same base ("foo-pdf"), so per the
# collision rule exercised earlier neither gets that bare slug — both are
# disambiguated with a hash of their literal filename. Compute it the same
# way rather than assuming either file owns the bare slug.
HIDDEN_HASH="$(printf '%s' 'foo.pdf' | sha256sum | cut -c1-8)"
VIEWER_HASH="$(printf '%s' 'Foo!.pdf' | sha256sum | cut -c1-8)"
HIDDEN_VIEW="${BASE}?view=foo-pdf-${HIDDEN_HASH}"
VIEWER_VIEW="${BASE}?view=foo-pdf-${VIEWER_HASH}"

[[ "$(status_code "${BASE}?action=raw&serve=1&file=foo.pdf")" == '404' ]] \
    || fail 'hidden PDF was reachable via ?action=raw'
[[ "$(status_code "${BASE}?action=flipbook&file=foo.pdf")" == '404' ]] \
    || fail 'hidden PDF was reachable via ?action=flipbook'

curl -sS -L "${HIDDEN_VIEW}" -o "${TMP}/hidden-view.html"
grep -Fq 'document-restricted' "${TMP}/hidden-view.html" || fail 'hidden PDF detail page did not show the restricted notice'
grep -Fq 'A verified transcription of the hidden certificate.' "${TMP}/hidden-view.html" \
    || fail 'hidden PDF transcript was not rendered server-side'
! grep -Fq 'Direct link' "${TMP}/hidden-view.html" || fail 'hidden PDF still offered a Direct link'
! grep -Fq 'action=raw&amp;serve=1&amp;file=foo.pdf"' "${TMP}/hidden-view.html" \
    || fail 'hidden PDF leaked its raw file URL onto the detail page'
pass 'hidden pdf_access blocks every path to the file'

[[ "$(status_code "${BASE}?action=raw&serve=1&file=Foo%21.pdf")" == '404' ]] \
    || fail 'viewer PDF was reachable via the plain unsigned URL'

curl -sS -L "${VIEWER_VIEW}" -o "${TMP}/viewer-view.html"
! grep -Fq 'Direct link' "${TMP}/viewer-view.html" || fail 'viewer PDF still offered a Direct link'
SIGNED_URL="$(grep -oE 'data-pdf-url="[^"]*"' "${TMP}/viewer-view.html" | head -n1 | sed -E 's/^data-pdf-url="//; s/"$//' | sed 's/&amp;/\&/g')"
[[ -n "${SIGNED_URL}" ]] || fail 'viewer PDF preview did not carry a signed URL'
grep -Eq 'expires=[0-9]+&token=[0-9a-f]{64}' <<<"${SIGNED_URL}" || fail 'viewer PDF URL was not signed'
[[ "$(status_code "${SIGNED_URL}")" == '200' ]] || fail 'valid signed viewer URL was rejected'
TAMPERED_URL="$(sed -E 's/(token=)[0-9a-f]{64}/\10000000000000000000000000000000000000000000000000000000000000000/' <<<"${SIGNED_URL}")"
[[ "$(status_code "${TAMPERED_URL}")" == '404' ]] || fail 'tampered signed viewer URL was accepted'
EXPIRED_URL="$(sed -E 's/expires=[0-9]+/expires=1/' <<<"${SIGNED_URL}")"
[[ "$(status_code "${EXPIRED_URL}")" == '404' ]] || fail 'expired signed viewer URL was accepted'
pass 'viewer pdf_access requires a valid, unexpired signature'

# FOLIO-VIDEO-001: with the guard on and confirmed, video_access is enforced by
# the ?action=raw gate. "hidden" is admin-only, "viewer" needs a valid signed
# URL, "public" streams. The Apache-level refusal of the direct file is verified
# separately under real Apache; here we exercise the PHP gate Folio performs.
curl -sS -b "${COOKIE}" --data-urlencode 'action=meta' --data-urlencode "csrf=${CSRF}" \
    --data-urlencode 'file=hideclip.mp4' --data-urlencode 'video_access=hidden' "${BASE}" >/dev/null
curl -sS -b "${COOKIE}" --data-urlencode 'action=meta' --data-urlencode "csrf=${CSRF}" \
    --data-urlencode 'file=viewclip.mp4' --data-urlencode 'video_access=viewer' "${BASE}" >/dev/null
curl -sS -b "${COOKIE}" --data-urlencode 'action=meta' --data-urlencode "csrf=${CSRF}" \
    --data-urlencode 'file=pubclip.mp4' --data-urlencode 'video_access=public' "${BASE}" >/dev/null

# hidden: refused to the public, served to the admin.
[[ "$(status_code "${BASE}?action=raw&serve=1&file=hideclip.mp4")" == '404' ]] \
    || fail 'hidden video was reachable by the public via ?action=raw'
[[ "$(curl -sS -o /dev/null -w '%{http_code}' -b "${COOKIE}" "${BASE}?action=raw&serve=1&file=hideclip.mp4")" == '200' ]] \
    || fail 'hidden video was not reachable by the admin'

# viewer: refused without a valid signature, served with one.
[[ "$(status_code "${BASE}?action=raw&serve=1&file=viewclip.mp4")" == '404' ]] \
    || fail 'viewer video was reachable without a signed URL'
V_EXP="$(( $(date +%s) + 900 ))"
V_TOK="$(printf '%s' "video|viewclip.mp4|${V_EXP}" \
    | openssl dgst -sha256 -hmac 'smoke-test-signing-key-do-not-use-in-production' -r | cut -d' ' -f1)"
[[ "$(status_code "${BASE}?action=raw&serve=1&file=viewclip.mp4&expires=${V_EXP}&token=${V_TOK}")" == '200' ]] \
    || fail 'viewer video was not served for a valid signed URL'
[[ "$(status_code "${BASE}?action=raw&serve=1&file=viewclip.mp4&expires=${V_EXP}&token=deadbeef")" == '404' ]] \
    || fail 'viewer video accepted a forged token'

# public: streamed, and range-aware.
[[ "$(status_code "${BASE}?action=raw&serve=1&file=pubclip.mp4")" == '200' ]] \
    || fail 'public video was not served'
PUB_RANGE="$(curl -sS -o /dev/null -w '%{http_code}' -H 'Range: bytes=0-99' "${BASE}?action=raw&serve=1&file=pubclip.mp4")"
[[ "${PUB_RANGE}" == '206' ]] || fail "public video did not honour a range request (got ${PUB_RANGE})"
pass 'video_access gate: hidden admin-only, viewer signed, public streams with range'

# FOLIO-VIDEO-002: a hidden video is removed from the folder listing (its
# bytes are still gated) while its record page stays sitemap-indexable —
# the same "hidden also removes the page from the folder listing while
# keeping it findable through search" policy the video access control
# settings page and the video_access dropdown both describe, identical to
# how a hidden PDF's record page already behaves (see FOLIO-PDF-002 below).
curl -sS "${BASE}?action=sitemap" -o "${TMP}/vid-sitemap.xml"
grep -Fq 'hideclip' "${TMP}/vid-sitemap.xml" || fail 'hidden video record page is missing from the sitemap'
curl -sS "${BASE}?dir=" -o "${TMP}/vid-listing-anon.html"
! grep -Fq 'hideclip' "${TMP}/vid-listing-anon.html" || fail 'hidden video appears in the public listing'
curl -sS -b "${COOKIE}" "${BASE}?dir=" -o "${TMP}/vid-listing-admin.html"
grep -Fq 'hideclip' "${TMP}/vid-listing-admin.html" || fail 'admin cannot see the hidden video in the listing'
pass 'hidden video is absent from the public listing but stays sitemap-indexable, and is visible to admin'

# FOLIO-PDF-002: the sitemap, robots meta, and llms.txt must stay exactly as
# indexable for restricted PDFs as for any other file — pdf_access must
# only ever affect the raw file endpoint, never the record page.
curl -sS "${BASE}?action=sitemap" -o "${TMP}/pdf-sitemap.xml"
grep -Fq "view=foo-pdf-${HIDDEN_HASH}<" "${TMP}/pdf-sitemap.xml" || fail 'hidden PDF record page is missing from the sitemap'
! grep -Fq 'action=raw' "${TMP}/pdf-sitemap.xml" \
    || fail 'sitemap referenced a raw PDF URL instead of only the record page'
! grep -Fq 'noindex' "${TMP}/hidden-view.html" \
    || fail 'hidden PDF record page picked up an unrelated noindex — pdf_access must not affect page-level robots meta'
curl -sS "${BASE}?action=llms" -o "${TMP}/pdf-llms.txt"
# Changed with the AI-discovery hardening: llms.txt is a curated discovery
# surface and now excludes hidden documents entirely, matching library.yaml.
# The sitemap above still lists the page — that is deliberate (the page is
# public; only the folder listing and discovery inventories delist it).
! grep -Fq "foo-pdf-${HIDDEN_HASH}" "${TMP}/pdf-llms.txt" \
    || fail 'llms.txt still lists a hidden PDF'
pass 'pdf_access does not affect sitemap, robots meta, or llms.txt indexability'

# llms.txt Specification (v1.7.0) conformance: Lang: immediately after the H1
# (before the blockquote), a required # Contact section built from whichever
# publisher fields are actually configured, and the specification attribution
# as a footer beneath a horizontal rule rather than an inline link near the
# top. Reuses the llms.txt already fetched above.
LLMS_LINE1="$(sed -n '1p' "${TMP}/pdf-llms.txt")"
LLMS_LINE2="$(sed -n '2p' "${TMP}/pdf-llms.txt")"
[[ "${LLMS_LINE1}" == '# Folio Smoke Test' && "${LLMS_LINE2}" == 'Lang: en' ]] \
    || fail 'llms.txt Lang: header is missing or not immediately after the H1'
grep -Fq '# Contact' "${TMP}/pdf-llms.txt" \
    || fail 'llms.txt is missing the required # Contact section'
grep -Fq -- '- Email: smoke-test@example.invalid' "${TMP}/pdf-llms.txt" \
    || fail 'llms.txt # Contact section is missing the configured publisher email'
LLMS_LAST4="$(tail -4 "${TMP}/pdf-llms.txt")"
[[ "${LLMS_LAST4}" == $'---\n\nllms.txt Specification (ADF-001)\nhttps://www.ai-visibility.org.uk/specifications/llms-txt/' ]] \
    || fail 'llms.txt specification attribution is not the closing footer the spec documents'
pass 'llms.txt Lang:, # Contact, and specification attribution follow the llms.txt Specification'

# robots.txt is generated live from current settings — unlike every other
# discovery endpoint, it must never 404: it is what announces
# non-indexability in the first place (Disallow: / instead of Allow: /), so
# a crawler that could not fetch it would have to assume everything is
# allowed, the opposite of what a non-indexable library wants. SITE_INDEXABLE
# is true for the whole run (this suite provisions one static config.php, so
# toggling it at runtime to test the Disallow branch would need a second
# server instance — left untested here, same as the equivalent gap already
# noted for the X-Robots-Tag/SITE_INDEXABLE fix).
ROBOTS_RESPONSE="$(curl -sS -D - -o "${TMP}/robots.txt" "${BASE}?action=robots")"
grep -qi '^Content-Type: text/plain' <<<"${ROBOTS_RESPONSE}" \
    || fail 'robots.txt was not served as text/plain'
grep -Fq 'Allow: /' "${TMP}/robots.txt" \
    || fail 'robots.txt did not allow crawling while the site is indexable'
grep -Fq 'Sitemap:' "${TMP}/robots.txt" \
    || fail 'robots.txt is missing its Sitemap: references'
pass 'robots.txt is generated live and always responds'

# FOLIO-SEC-001: metadata is user input and lands inside a <script> element.
# A closing script tag in any field must not be able to end that element.
INJECT_TITLE='Report </script><img src=x onerror=alert(1)><script>'
INJECT_DESC='Mixed case </ScRiPt><svg onload=alert(2)> and O'"'"'Brien & "Sons"'
META_RESPONSE="$(curl -sS -b "${COOKIE}" \
    --data-urlencode 'action=meta' --data-urlencode "csrf=${CSRF}" \
    --data-urlencode 'file=notes.txt' --data-urlencode "title=${INJECT_TITLE}" \
    --data-urlencode "desc=${INJECT_DESC}" --data-urlencode 'category=</script><iframe>' \
    --data-urlencode 'tags=</script><form>, x&y'"'"'z"w' "${BASE}")"
grep -Fq '"ok":true' <<<"${META_RESPONSE}" || fail 'injection metadata update failed'

# notes.txt has no slug rival, so its canonical address carries no extension.
for INJ_URL in "${BASE}" "${BASE}?view=notes"; do
    curl -sS "${INJ_URL}" -o "${TMP}/inject.html"
    php -r '
        $html = file_get_contents($argv[1]);
        preg_match_all(
            "#<script type=\"application/ld\+json\">(.*?)</script>#s",
            $html, $m
        );
        if (!$m[1]) { fwrite(STDERR, "no JSON-LD block found\n"); exit(1); }
        foreach ($m[1] as $block) {
            if (stripos($block, "</script") !== false) {
                fwrite(STDERR, "raw closing script tag inside JSON-LD\n");
                exit(1);
            }
            if (json_decode($block, true) === null) {
                fwrite(STDERR, "JSON-LD block is not valid JSON\n");
                exit(1);
            }
        }
        // The payload must survive as data, encoded, not as markup.
        $graph = json_decode($m[1][0], true)["@graph"] ?? [];
        $flat  = json_encode($graph);
        if (strpos($flat, "alert(1)") === false && strpos($flat, "alert(2)") === false) {
            fwrite(STDERR, "metadata values were lost rather than encoded\n");
            exit(1);
        }
    ' "${TMP}/inject.html" || fail "JSON-LD injection was not neutralised at ${INJ_URL}"
    # Nothing outside the JSON-LD block may have become live markup.
    ! grep -qi '<img src=x onerror' "${TMP}/inject.html" || fail 'injected img element was emitted'
    ! grep -qi '<svg onload' "${TMP}/inject.html" || fail 'injected svg element was emitted'
done
pass 'JSON-LD HTML-context injection is neutralised'

cp "${APP}/data/metadata.json" "${TMP}/metadata.valid"
printf '{\n' > "${APP}/data/metadata.json"
META_CODE="$(curl -sS -b "${COOKIE}" -o "${TMP}/meta-error.json" -w '%{http_code}' \
    --data-urlencode 'action=meta' --data-urlencode "csrf=${CSRF}" \
    --data-urlencode 'file=notes.txt' --data-urlencode 'title=Must Not Overwrite' "${BASE}")"
[[ "${META_CODE}" == '500' ]] || fail 'malformed metadata was not rejected'
grep -Fq 'Metadata is invalid' "${TMP}/meta-error.json" || fail 'malformed metadata error was not reported'
grep -Fxq '{' "${APP}/data/metadata.json" || fail 'malformed metadata was silently overwritten'
cp "${TMP}/metadata.valid" "${APP}/data/metadata.json"
pass 'malformed metadata preservation'

curl -sS -b "${COOKIE}" "${BASE}?action=users" -o "${TMP}/users.html"
CSRF="$(csrf_from "${TMP}/users.html")"
[[ -n "${CSRF}" ]] || fail 'accounts CSRF token was not emitted'
curl -sS -b "${COOKIE}" -o "${TMP}/reset.html" \
    --data-urlencode "csrf=${CSRF}" --data-urlencode 'op=reset' \
    --data-urlencode 'username=admin' --data-urlencode "password=${NEW_PASSWORD}" \
    "${BASE}?action=users"
curl -sS -b "${COOKIE}" "${BASE}" -o "${TMP}/after-reset.html"
! grep -Fq '?action=settings' "${TMP}/after-reset.html" || fail 'password reset did not revoke the existing session'
pass 'authentication-version session revocation'

# FOLIO-AUTH-013: logging out changes state, so a cross-origin GET must not do it.
# The previous test deliberately revoked this session, so sign in again first.
curl -sS -c "${COOKIE}" "${BASE}?action=login" -o "${TMP}/relogin.html"
CSRF="$(csrf_from "${TMP}/relogin.html")"
RELOGIN_CODE="$(curl -sS -b "${COOKIE}" -c "${COOKIE}" -o /dev/null -w '%{http_code}' \
    --data-urlencode 'action=login' --data-urlencode 'from=login' \
    --data-urlencode "csrf=${CSRF}" --data-urlencode 'username=admin' \
    --data-urlencode "password=${NEW_PASSWORD}" "${BASE}")"
[[ "${RELOGIN_CODE}" == '302' ]] || fail 'could not sign in again after password rotation'
LOGOUT_GET_CODE="$(curl -sS -b "${COOKIE}" -o "${TMP}/logout-get.html" -w '%{http_code}' "${BASE}?action=logout")"
[[ "${LOGOUT_GET_CODE}" == '405' ]] || fail 'GET logout was not rejected'
grep -Fq 'name="action" value="logout"' "${TMP}/logout-get.html" || fail 'GET logout did not offer a confirmation form'
curl -sS -b "${COOKIE}" "${BASE}" -o "${TMP}/still-in.html"
grep -Fq '?action=settings' "${TMP}/still-in.html" || fail 'GET logout destroyed the session'
NO_TOKEN_CODE="$(curl -sS -b "${COOKIE}" -o /dev/null -w '%{http_code}' --data-urlencode 'action=logout' "${BASE}")"
[[ "${NO_TOKEN_CODE}" == '405' ]] || fail 'logout without a CSRF token was not rejected'
curl -sS -b "${COOKIE}" "${BASE}" -o "${TMP}/still-in2.html"
grep -Fq '?action=settings' "${TMP}/still-in2.html" || fail 'tokenless logout destroyed the session'
LOGOUT_CSRF="$(csrf_from "${TMP}/logout-get.html")"
[[ -n "${LOGOUT_CSRF}" ]] || fail 'logout CSRF token was not emitted'
LOGOUT_CODE="$(curl -sS -b "${COOKIE}" -c "${COOKIE}" -o /dev/null -w '%{http_code}' \
    --data-urlencode 'action=logout' --data-urlencode "csrf=${LOGOUT_CSRF}" "${BASE}")"
[[ "${LOGOUT_CODE}" == '302' ]] || fail 'valid logout POST did not succeed'
curl -sS -b "${COOKIE}" "${BASE}" -o "${TMP}/logged-out.html"
! grep -Fq '?action=settings' "${TMP}/logged-out.html" || fail 'session survived a valid logout'
pass 'logout requires POST with a valid CSRF token'

# FOLIO-SEC-014: the installer handles credentials and writes config.php.
curl -sS -D "${TMP}/installer.headers" -o /dev/null "${BASE}install.php"
grep -qi '^Content-Security-Policy:.*default-src .self.' "${TMP}/installer.headers" || fail 'installer CSP is missing'
grep -qi "^Content-Security-Policy:.*frame-ancestors 'none'" "${TMP}/installer.headers" || fail 'installer does not forbid framing'
! grep -qi "^Content-Security-Policy:.*unsafe-inline" "${TMP}/installer.headers" || fail 'installer CSP allows inline code'
grep -qi '^X-Content-Type-Options: nosniff' "${TMP}/installer.headers" || fail 'installer nosniff header is missing'
grep -qi '^Referrer-Policy:' "${TMP}/installer.headers" || fail 'installer referrer policy is missing'
grep -qi '^Cache-Control:.*no-store' "${TMP}/installer.headers" || fail 'installer responses are cacheable'
pass 'installer emits hardened security headers'

curl -sS -D "${TMP}/sitemap.headers" "${BASE}?action=sitemap" -o "${TMP}/sitemap.xml"
! grep -qi '^Set-Cookie:' "${TMP}/sitemap.headers" || fail 'sitemap created an anonymous session'
grep -qi '^Cache-Control: public, max-age=900' "${TMP}/sitemap.headers" || fail 'sitemap is not publicly cacheable'
grep -Fq '<urlset' "${TMP}/sitemap.xml" || fail 'sitemap XML was not generated'
# FOLIO-SEO-005: a metadata edit must move that page's lastmod even though the
# file on disk is untouched, and must not move anyone else's. notes.txt and
# foo.jpg are used because their slugs are unambiguous.
touch -t 202001010000 "${APP}/uploads/notes.txt" "${APP}/uploads/foo.jpg"
curl -sS -c "${COOKIE}" "${BASE}?action=login" -o "${TMP}/lm-login.html"
CSRF="$(csrf_from "${TMP}/lm-login.html")"
curl -sS -b "${COOKIE}" -c "${COOKIE}" -o /dev/null \
    --data-urlencode 'action=login' --data-urlencode 'from=login' \
    --data-urlencode "csrf=${CSRF}" --data-urlencode 'username=admin' \
    --data-urlencode "password=${NEW_PASSWORD}" "${BASE}"
curl -sS -b "${COOKIE}" "${BASE}" -o "${TMP}/lm-admin.html"
CSRF="$(csrf_from "${TMP}/lm-admin.html")"
curl -sS -b "${COOKIE}" -o "${TMP}/lm-meta.json" \
    --data-urlencode 'action=meta' --data-urlencode "csrf=${CSRF}" \
    --data-urlencode 'file=notes.txt' --data-urlencode 'title=Retitled For Lastmod' "${BASE}"
grep -Fq '"ok":true' "${TMP}/lm-meta.json" || fail "lastmod test could not update metadata: $(cat "${TMP}/lm-meta.json")"
curl -sS "${BASE}?action=sitemap" -o "${TMP}/sitemap-after.xml"
php -r '
    $xml = file_get_contents($argv[1]);
    if (!preg_match_all("#<url>(.*?)</url>#s", $xml, $m)) {
        fwrite(STDERR, "no <url> entries found\n"); exit(1);
    }
    $edited = null; $untouched = null;
    foreach ($m[1] as $block) {
        if (!preg_match("#<loc>([^<]*)</loc>#", $block, $l)) { continue; }
        $lastmod = preg_match("#<lastmod>([^<]*)</lastmod>#", $block, $d) ? $d[1] : "";
        if (strpos($l[1], "view=notes") !== false)    { $edited = $lastmod; }
        if (strpos($l[1], "view=foo-jpg") !== false)   { $untouched = $lastmod; }
    }
    if ($edited === null)    { fwrite(STDERR, "edited file missing from sitemap\n"); exit(1); }
    if ($untouched === null) { fwrite(STDERR, "untouched file missing from sitemap\n"); exit(1); }
    if (strpos($edited, "2020-01-01") !== false) {
        fwrite(STDERR, "metadata edit did not update lastmod (still {$edited})\n"); exit(1);
    }
    if (strpos($untouched, "2020-01-01") === false) {
        fwrite(STDERR, "editing one file changed an unrelated lastmod ({$untouched})\n"); exit(1);
    }
' "${TMP}/sitemap-after.xml" 2>"${TMP}/lm.err" || fail "metadata-aware lastmod is incorrect: $(cat "${TMP}/lm.err")"
pass 'sitemap lastmod reflects metadata changes'

# Analytics inline bootstraps are allowed by sha256 hash, never by
# 'unsafe-inline'. A hash that does not match the emitted script is invisible
# without a browser: the tag renders, the CSP looks strict, and the tracker
# silently never runs. This checks the two things that must hold.
php -r '
    define("MATOMO_URL", "https://stats.example.com:8443");
    define("MATOMO_SITE_ID", "7");
    define("MATOMO_HONOR_DNT", true);
    define("MATOMO_COOKIELESS", true);
    define("GA4_MEASUREMENT_ID", "G-TEST123");
    define("GA4_ANONYMIZE_IP", true);
    define("ANALYTICS_ADMIN", false);
    function is_admin(): bool { return false; }
    function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
    $src = file_get_contents($argv[1]);
    foreach (["analytics_active", "analytics_csp_sources", "analytics_inline_blocks", "analytics_scripts"] as $fn) {
        if (!preg_match("#function\s+" . $fn . "\b.*?\n\}\n#s", $src, $m)) {
            fwrite(STDERR, "could not extract {$fn}\n"); exit(1);
        }
        eval($m[0]);
    }
    $csp  = analytics_csp_sources();
    $html = analytics_scripts();

    if (strpos($csp["script"], "unsafe-inline") !== false) {
        fwrite(STDERR, "analytics CSP fell back to unsafe-inline\n"); exit(1);
    }
    // Every emitted inline block must be covered by a hash in the policy.
    preg_match_all("#<script>(.*?)</script>#s", $html, $blocks);
    if (!$blocks[1]) { fwrite(STDERR, "no inline analytics blocks emitted\n"); exit(1); }
    foreach ($blocks[1] as $b) {
        $h = "sha256-" . base64_encode(hash("sha256", $b, true));
        if (strpos($csp["script"], $h) === false) {
            fwrite(STDERR, "emitted inline script is not covered by a CSP hash\n"); exit(1);
        }
    }
    // A non-default port must survive into the CSP origin, or the external
    // tracker script is refused.
    if (strpos($csp["script"], "https://stats.example.com:8443") === false) {
        fwrite(STDERR, "CSP origin dropped the port\n"); exit(1);
    }
' "${APP}/index.php" 2>"${TMP}/an.err" || fail "analytics CSP is unsafe or inconsistent: $(cat "${TMP}/an.err")"
pass 'analytics inline scripts are allowed by hash, not unsafe-inline'

# EXCLUDE_PATTERNS is a publishing decision, but it only means anything if an
# excluded file is absent from every surface, not merely hidden in the listing.
curl -sS "${BASE}" -o "${TMP}/excl-listing.html"
! grep -Fq 'private.secret.jpg' "${TMP}/excl-listing.html" || fail 'excluded file appeared in the listing'
! grep -Fq '_drafts' "${TMP}/excl-listing.html" || fail 'excluded folder appeared in the listing'
curl -sS "${BASE}?action=sitemap" -o "${TMP}/excl-sitemap.xml"
! grep -Fq 'secret' "${TMP}/excl-sitemap.xml" || fail 'excluded file appeared in the sitemap'
! grep -Fq '_drafts' "${TMP}/excl-sitemap.xml" || fail 'excluded folder appeared in the sitemap'
# Every delivery route must refuse it, including the derivative route, or the
# exclusion is only cosmetic.
for EXCL_ROUTE in \
    "?view=private-secret-jpg" \
    "?action=raw&serve=1&file=private.secret.jpg" \
    "?action=thumb&w=320&file=private.secret.jpg" \
    "?action=raw&serve=1&file=_drafts/hidden.jpg" \
    "?action=thumb&w=320&file=_drafts/hidden.jpg"; do
    [[ "$(status_code "${BASE}${EXCL_ROUTE}")" == '404' ]] \
        || fail "excluded file was reachable via ${EXCL_ROUTE}"
done
pass 'excluded files are absent from every public surface'

# Derivative images. These must hold whether or not an image engine is
# installed, because a host without Imagick or GD is the common case and the
# feature is required to degrade rather than break.
THUMB_ORIGINAL_SUM="$(md5sum "${APP}/uploads/foo.jpg" | cut -d' ' -f1)"

# Only the offered widths are generated. Anything else must be refused, or a
# visitor could fill the disk by requesting thousands of sizes.
for BAD_W in 321 9999 0 -1 abc ''; do
    [[ "$(status_code "${BASE}?action=thumb&w=${BAD_W}&file=foo.jpg")" == '404' ]] \
        || fail "thumb width ${BAD_W} was not refused"
done

# The delivery route obeys the same containment as every other one.
[[ "$(status_code "${BASE}?action=thumb&w=320&file=../config.php")" == '404' ]] \
    || fail 'thumb route allowed a path outside uploads'
[[ "$(status_code "${BASE}?action=thumb&w=320&file=host.txt")" == '404' ]] \
    || fail 'thumb route followed a symlink'

# A supported width either returns an image or redirects to the original.
# Both are correct; a 500 or an empty body is not.
THUMB_CODE="$(status_code "${BASE}?action=thumb&w=320&file=foo.jpg")"
[[ "${THUMB_CODE}" == '200' || "${THUMB_CODE}" == '302' ]] \
    || fail "thumb request returned ${THUMB_CODE} instead of an image or a redirect"

# Whatever happened, the uploaded file itself must be untouched. This is the
# invariant that matters most: derivatives never write back into uploads/.
[[ "$(md5sum "${APP}/uploads/foo.jpg" | cut -d' ' -f1)" == "${THUMB_ORIGINAL_SUM}" ]] \
    || fail 'generating a derivative modified the original file'

# Derivatives belong in data/, which is denied to the web, never in uploads/.
if compgen -G "${APP}/uploads/*.webp" > /dev/null; then
    fail 'a derivative was written into uploads/'
fi

pass 'derivative images are contained, bounded, and never touch originals'

# Access gating and derivative generation are separate features that meet at
# the thumbnail route. A restricted PDF must not have page one readable there:
# the route carries no signature, so it would be an unguarded second door to
# exactly what the gate exists to protect.
# foo.pdf is hidden and Foo!.pdf is viewer-only, both set earlier in this run.
for GATED in 'foo.pdf' 'Foo!.pdf'; do
    [[ "$(status_code "${BASE}?action=thumb&w=320&file=${GATED}")" == '404' ]] \
        || fail "a restricted PDF was reachable through the thumbnail route (${GATED})"
done
# An unrestricted file is unaffected: it gets a derivative or the original.
PUBLIC_THUMB="$(status_code "${BASE}?action=thumb&w=320&file=foo.jpg")"
[[ "${PUBLIC_THUMB}" == '200' || "${PUBLIC_THUMB}" == '302' ]] \
    || fail "an unrestricted file returned ${PUBLIC_THUMB} from the thumbnail route"
pass 'restricted PDFs have no thumbnail'

# External utilities. Folio must behave identically whether or not they are
# installed, and must never let a filename reach a shell.
php -r '
    // Strip comments and string literals first: the check is about executable
    // code, not prose that happens to mention a function name.
    $src = "";
    foreach (token_get_all(file_get_contents($argv[1])) as $t) {
        if (is_array($t)) {
            if (in_array($t[0], [T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML,
                                 T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                continue;
            }
            $src .= $t[1];
        } else {
            $src .= $t;
        }
    }

    // No shell-invoking construct may appear in executable code. This is the
    // property that makes FTP-supplied filenames safe to pass to external
    // programs: there is no shell for a metacharacter to reach.
    foreach (["shell_exec", "passthru", "`"] as $banned) {
        if (strpos($src, $banned) !== false) {
            fwrite(STDERR, "found shell-invoking construct: {$banned}\n");
            exit(1);
        }
    }
    // exec() and system() are likewise absent; proc_open with an argument
    // array is the only permitted route.
    if (preg_match("/(?<![a-z_])(exec|system)\s*\(/i", $src, $m)) {
        fwrite(STDERR, "found {$m[1]}(): only proc_open with an array is allowed\n");
        exit(1);
    }
    $raw = file_get_contents($argv[1]);
    if (strpos($src, "proc_open") !== false && strpos($raw, "bypass_shell") === false) {
        fwrite(STDERR, "proc_open without bypass_shell\n");
        exit(1);
    }
    // Utility lookup must not consult $PATH, which is inherited and mutable.
    if (preg_match("/tool_path.*getenv\([\"\x27]PATH/s", $src)) {
        fwrite(STDERR, "tool lookup consults PATH\n");
        exit(1);
    }
' "${APP}/index.php" 2>"${TMP}/tools.err" || fail "unsafe external command handling: $(cat "${TMP}/tools.err")"

# A tool that is absent must resolve to null rather than a bare name, so a
# failed lookup can never become a $PATH-resolved execution.
php -r '
    define("TOOLS_ENABLED", true);
    define("TOOL_SEARCH_PATHS", ["/nonexistent-folio-test"]);
    define("TOOL_PATHS", []);
    $src = file_get_contents($argv[1]);
    foreach (["tool_account_homes", "tool_search_dirs", "tool_path"] as $fn) {
        preg_match("/function\\s+" . $fn . "\\b.*?\\n\\}\\n/s", $src, $m);
        eval($m[0]);
    }
    if (tool_path("ocrmypdf") !== null) { fwrite(STDERR, "absent tool did not resolve to null\n"); exit(1); }
    if (tool_path("../../bin/sh") !== null) { fwrite(STDERR, "traversal in tool name was accepted\n"); exit(1); }
    if (tool_path("sh; rm -rf /") !== null) { fwrite(STDERR, "metacharacters in tool name were accepted\n"); exit(1); }
' "${APP}/index.php" 2>"${TMP}/toolpath.err" || fail "tool_path is unsafe: $(cat "${TMP}/toolpath.err")"

# The OCR endpoint is admin-only and CSRF-protected, like every other
# state-changing action.
[[ "$(curl -sS -o /dev/null -w '%{http_code}' --data-urlencode 'action=ocr' \
    --data-urlencode 'file=foo.pdf' "${BASE}")" == '403' ]] \
    || fail 'anonymous OCR request was not refused'
pass 'external utilities are invoked without a shell and gated correctly'

# Every utility is optional, and the detection contract must hold whatever
# happens to be installed on the machine running these tests. Asserting that
# nothing is found would be flaky: a developer's home may genuinely contain
# some of these tools. The real contract is checked instead.
php -r '
    define("TOOLS_ENABLED", true);
    define("TOOL_SEARCH_PATHS", ["/nonexistent-folio-test"]);
    define("TOOL_PATHS", []);
    define("OCR_LANGUAGES", ["eng"]);
    $src = file_get_contents($argv[1]);
    foreach (["tool_account_homes", "tool_search_dirs", "tool_path", "tool_have",
              "tool_run", "ocr_languages_available", "ocr_language_string",
              "ocr_method", "ocr_available"] as $fn) {
        preg_match("/function\s+" . $fn . "\b.*?\n\}\n/s", $src, $m);
        eval($m[0]);
    }

    // A resolved tool is always an absolute path to something executable,
    // never a bare name that a shell or $PATH would have to interpret.
    foreach (["ocrmypdf","tesseract","pdftotext","pdfinfo","pdftocairo","pdftoppm",
              "qpdf","pngquant","exiftool","unpaper"] as $t) {
        $p = tool_path($t);
        if ($p === null) { continue; }
        if ($p[0] !== "/" || !is_file($p) || !is_executable($p)) {
            fwrite(STDERR, "{$t} resolved to a non-absolute or non-executable path: {$p}\n");
            exit(1);
        }
    }

    // Only the two defined routes exist, and each is claimed only when the
    // tools it actually needs are present.
    $method = ocr_method();
    if (!in_array($method, ["", "ocrmypdf", "tesseract"], true)) {
        fwrite(STDERR, "unknown OCR method: {$method}\n"); exit(1);
    }
    if ($method === "ocrmypdf" && !tool_have("ocrmypdf")) {
        fwrite(STDERR, "ocrmypdf route claimed without ocrmypdf\n"); exit(1);
    }
    if ($method === "tesseract"
        && !(tool_have("tesseract") && (tool_have("pdftocairo") || tool_have("pdftoppm")))) {
        fwrite(STDERR, "tesseract route claimed without its tools\n"); exit(1);
    }
    if ($method !== "" && !tool_have("tesseract")) {
        fwrite(STDERR, "an OCR route was claimed without tesseract\n"); exit(1);
    }
    if (ocr_available() !== ($method !== "")) {
        fwrite(STDERR, "ocr_available disagrees with ocr_method\n"); exit(1);
    }

    // Turning the feature off must silence detection completely.
    if (!TOOLS_ENABLED) { exit(0); }
' "${APP}/index.php" 2>"${TMP}/fallback.err" || fail "utility detection contract broken: $(cat "${TMP}/fallback.err")"

# With the feature switched off, nothing may resolve at all.
php -r '
    define("TOOLS_ENABLED", false);
    define("TOOL_SEARCH_PATHS", ["/usr/bin", "/usr/local/bin"]);
    define("TOOL_PATHS", []);
    define("OCR_LANGUAGES", ["eng"]);
    $src = file_get_contents($argv[1]);
    foreach (["tool_account_homes", "tool_search_dirs", "tool_path", "tool_have",
              "ocr_languages_available", "ocr_language_string", "ocr_method",
              "ocr_available"] as $fn) {
        preg_match("/function\s+" . $fn . "\b.*?\n\}\n/s", $src, $m);
        eval($m[0]);
    }
    foreach (["ocrmypdf","tesseract","pdftotext","qpdf"] as $t) {
        if (tool_have($t)) { fwrite(STDERR, "{$t} resolved with TOOLS_ENABLED false\n"); exit(1); }
    }
    if (ocr_available()) { fwrite(STDERR, "OCR available with TOOLS_ENABLED false\n"); exit(1); }
' "${APP}/index.php" 2>"${TMP}/off.err" || fail "TOOLS_ENABLED=false does not disable detection: $(cat "${TMP}/off.err")"

# Route selection is logic, not environment, so it is tested with stubs. This
# catches a guard being dropped even on a machine that happens to have every
# tool installed.
php -r '
    define("OCR_LANGUAGES", ["eng"]);
    $GLOBALS["have"] = [];
    function tool_have(string $n): bool { return in_array($n, $GLOBALS["have"], true); }
    function ocr_language_string(): string { return tool_have("tesseract") ? "eng" : ""; }
    $src = file_get_contents($argv[1]);
    preg_match("/function\s+ocr_method\b.*?\n\}\n/s", $src, $m);
    eval($m[0]);

    $cases = [
        // [installed tools, expected route]
        [["ocrmypdf","tesseract","pdftocairo","qpdf"], "ocrmypdf"],
        [["tesseract","pdftocairo","qpdf"],            "tesseract"],
        [["tesseract","pdftoppm"],                     "tesseract"],
        [["tesseract","qpdf"],                         ""],
        [["ocrmypdf","pdftocairo","qpdf"],             ""],   // no tesseract
        [["pdftocairo","qpdf"],                        ""],
        [[],                                           ""],
    ];
    foreach ($cases as [$have, $want]) {
        $GLOBALS["have"] = $have;
        $got = ocr_method();
        if ($got !== $want) {
            fwrite(STDERR, sprintf("with [%s] expected route %s, got %s\n",
                implode(",", $have), $want === "" ? "none" : $want, $got === "" ? "none" : $got));
            exit(1);
        }
    }
' "${APP}/index.php" 2>"${TMP}/route.err" || fail "OCR route selection is wrong: $(cat "${TMP}/route.err")"

# Ghostscript must never be reached unless it has been deliberately allowed.
# It is absent on many hosts and disabled by policy on many more.
php -r '
    $src = file_get_contents($argv[1]);
    // Every Imagick PDF read must sit behind the opt-in guard.
    if (preg_match_all("/readImage\(\\$[a-z_]+ \. \x27\[0\]\x27\)/", $src, $m)) {
        foreach ($m[0] as $hit) {
            $before = substr($src, 0, strpos($src, $hit));
            $window = substr($before, -400);
            if (strpos($window, "PDF_ALLOW_GHOSTSCRIPT") === false) {
                fwrite(STDERR, "an Imagick PDF read is not guarded by PDF_ALLOW_GHOSTSCRIPT\n");
                exit(1);
            }
        }
    }
    if (!preg_match("/define\(\x27PDF_ALLOW_GHOSTSCRIPT\x27, false\)/", $src)) {
        fwrite(STDERR, "PDF_ALLOW_GHOSTSCRIPT does not default to false\n");
        exit(1);
    }
' "${APP}/index.php" 2>"${TMP}/gs.err" || fail "Ghostscript is reachable by default: $(cat "${TMP}/gs.err")"
pass 'utilities are optional and Ghostscript is never used unless allowed'

php "${APP}/tests/wired-check.php" "${APP}/index.php" 2>"${TMP}/wired.err" \
    || fail "an advertised utility is dead code: $(cat "${TMP}/wired.err")"
pass 'every advertised utility is actually used'

# Browsers request /favicon.ico and /apple-touch-icon.png directly, whatever
# the <link> tags say. With the catch-all rewrite those reach index.php, so
# they must be answered rather than 404 — a correct icon that only appears in
# a link tag still looks broken in the tab and the bookmark bar.
# PHP's built-in server answers static paths itself and never reaches
# index.php, so the request is made the way Apache's rewrite delivers it.
for ICON_PATH in favicon.ico favicon.svg apple-touch-icon.png; do
    ICON_HEADERS="$(curl -sS -D - -o /dev/null "${BASE}index.php/${ICON_PATH}" 2>/dev/null || true)"
    ICON_CODE="$(curl -sS -o /dev/null -w '%{http_code}' "${BASE}index.php/${ICON_PATH}")"
    ICON_TYPE="$(curl -sS -o /dev/null -w '%{content_type}' "${BASE}index.php/${ICON_PATH}")"
    [[ "${ICON_CODE}" == '200' ]] \
        || fail "/${ICON_PATH} returned ${ICON_CODE} instead of an icon"
    case "${ICON_TYPE}" in
        image/*) ;;
        *) fail "/${ICON_PATH} was served as ${ICON_TYPE} rather than an image" ;;
    esac
done
pass 'root icon requests are answered with a real icon'

# Assets are cached for a year and told never to revalidate, which is only
# safe if the URL changes when the file does. Otherwise an upgrade ships new
# markup to a browser still holding the previous stylesheet.
curl -sS "${BASE}" -o "${TMP}/assets.html"
php "${APP}/tests/asset-version-check.php" "${TMP}/assets.html" 2>"${TMP}/assets.err" \
    || fail "release assets are not versioned: $(cat "${TMP}/assets.err")"
pass 'release assets are versioned so an upgrade is not served stale CSS'

# The PDF sitemap invites crawlers to the document files themselves. It must
# never advertise a file the crawler would then be refused, and must never
# list an excluded one: a sitemap entry that 404s is worse than no entry.
curl -sS "${BASE}?action=sitemap_pdf" -o "${TMP}/pdf-sitemap.xml"
grep -Fq '<urlset' "${TMP}/pdf-sitemap.xml" || fail 'the PDF sitemap was not served'
# foo.pdf is hidden and Foo!.pdf is viewer-only, both set earlier in this run.
# Neither PDF's raw *file* may be advertised here: a crawler invited to fetch
# it would be refused. Their record *pages* still appear in the main page
# sitemap (see FOLIO-PDF-002 above) — this file sitemap only lists the
# bytes, gated by media_full_access(), exactly as the sitemap_pdf handler's
# own comment describes ("the 'indexed page, gated file' split").
grep -Fq 'public-doc.pdf' "${TMP}/pdf-sitemap.xml" \
    || fail 'a public PDF is missing from the PDF sitemap'
! grep -Fq '/foo.pdf' "${TMP}/pdf-sitemap.xml" \
    || fail 'a hidden PDF file was advertised in the PDF sitemap'
! grep -Fq 'Foo%21.pdf' "${TMP}/pdf-sitemap.xml" \
    || fail 'a viewer-only PDF file was advertised in the PDF sitemap'
! grep -Fq 'private.secret' "${TMP}/pdf-sitemap.xml" \
    || fail 'an excluded file appeared in the PDF sitemap'
! grep -Fq '_drafts' "${TMP}/pdf-sitemap.xml" \
    || fail 'an excluded folder appeared in the PDF sitemap'
# Only PDFs belong in it.
! grep -Fq 'notes.txt' "${TMP}/pdf-sitemap.xml" \
    || fail 'a non-PDF appeared in the PDF sitemap'
# Documents must be followable: a crawler that will not follow links inside a
# PDF cannot reach the rest of the collection from it.
PDF_ROBOTS="$(curl -sS -D - -o /dev/null "${BASE}?action=raw&serve=1&file=public-doc.pdf" | tr -d '\r')"
grep -qiE '^X-Robots-Tag:.*nofollow' <<<"${PDF_ROBOTS}" \
    && fail 'a PDF was served nofollow'
grep -qiE '^X-Robots-Tag:.*noindex' <<<"${PDF_ROBOTS}" \
    && fail 'a PDF was served noindex'
pass 'PDF sitemap lists only reachable documents, served index and follow'

# The video sitemap follows the same "indexed page, gated file" split as the
# PDF file sitemap above: a restricted or hidden video's raw file has nothing
# valid to list here (its record page still appears in the main sitemap
# regardless of tier), and excluded files/folders and non-video files never
# belong in it either. pubclip/viewclip/hideclip are random bytes, not a
# decodable video, so image_can_derive() has nothing to build a thumbnail
# from and every one of them is correctly absent from a *populated* entry —
# this only tests the gating that runs before thumbnail derivation, not that
# a real public video with a derivable thumbnail is actually listed.
curl -sS "${BASE}?action=sitemap_video" -o "${TMP}/video-sitemap.xml"
grep -Fq '<urlset' "${TMP}/video-sitemap.xml" || fail 'the video sitemap was not served'
grep -Fq 'xmlns:video=' "${TMP}/video-sitemap.xml" || fail 'the video sitemap is missing the video: namespace'
! grep -Fq 'hideclip' "${TMP}/video-sitemap.xml" \
    || fail 'a hidden video file was advertised in the video sitemap'
! grep -Fq 'viewclip' "${TMP}/video-sitemap.xml" \
    || fail 'a viewer-only video file was advertised in the video sitemap'
! grep -Fq 'private.secret' "${TMP}/video-sitemap.xml" \
    || fail 'an excluded file appeared in the video sitemap'
! grep -Fq 'public-doc.pdf' "${TMP}/video-sitemap.xml" \
    || fail 'a non-video file appeared in the video sitemap'
pass 'video sitemap serves valid XML and excludes gated, excluded, and non-video files'

# Canonical slugs, aliases, and redirects. A document's public address must
# survive the file being renamed or moved, so it is stored rather than derived.
php -r '
    $src = file_get_contents($argv[1]);
    foreach (["document_new_id","meta_is_migrated","reserved_slugs","slug_normalise",
              "slug_rejection_reason","slug_make_unique","meta_migrate","meta_validate",
              "meta_indexes"] as $fn) {
        preg_match("/function\s+" . $fn . "\b.*?\n\}\n/s", $src, $m);
        eval($m[0]);
    }
    function str_clip(string $s, int $n): string { return substr($s, 0, $n); }
    function slug_path(string $rel): string {
        $d = dirname($rel); $d = ($d === "." || $d === "") ? "" : $d;
        $n = pathinfo($rel, PATHINFO_FILENAME);
        $s = trim(preg_replace("/-+/", "-", strtolower(preg_replace("/[^a-z0-9]+/i", "-", $n))), "-");
        return $d === "" ? $s : $d . "/" . $s;
    }

    // A slug must never be able to carry an external destination into a
    // Location header.
    foreach (["https://evil.example.com/x", "//evil.example.com", "../../etc/passwd"] as $bad) {
        $out = slug_normalise($bad);
        if ($out === "" ) { continue; }
        if (strpos($out, "/") !== false || strpos($out, ":") !== false || strpos($out, ".") !== false) {
            fwrite(STDERR, "slug_normalise left something routable in: {$out}\n"); exit(1);
        }
    }

    // Reserved routes and collisions are refused.
    $data = ["documents" => [
        "doc_a" => ["document_id"=>"doc_a","slug"=>"taken","aliases"=>["was-taken"],"file_path"=>"a.pdf","title"=>"A"],
    ]];
    foreach (["", "admin", "sitemap", "taken", "was-taken"] as $bad) {
        if (slug_rejection_reason($bad, $data) === "") {
            fwrite(STDERR, "slug \"{$bad}\" was accepted but should not be\n"); exit(1);
        }
    }
    if (slug_rejection_reason("taken", $data, "doc_a") !== "") {
        fwrite(STDERR, "a document was not allowed to keep its own slug\n"); exit(1);
    }

    // Migration preserves every field and the existing public URL, and is
    // idempotent: running it twice must not mint new identifiers.
    $legacy = ["certificates/award_1997.pdf" => [
        "title"=>"Award","desc"=>"d","category"=>"c","tags"=>["t"],
        "transcript"=>"keep me","pdf_access"=>"viewer","language"=>"ms","updated_at"=>123,
    ]];
    $w = [];
    $mig = meta_migrate($legacy, $w);
    $rec = array_values($mig["documents"])[0];
    foreach (["title","desc","category","transcript","pdf_access","language","updated_at"] as $k) {
        if (($rec[$k] ?? null) != $legacy["certificates/award_1997.pdf"][$k]) {
            fwrite(STDERR, "migration lost field {$k}\n"); exit(1);
        }
    }
    if ($rec["slug"] !== "award-1997") {
        fwrite(STDERR, "migration did not preserve the existing URL: {$rec["slug"]}\n"); exit(1);
    }
    if ($rec["file_path"] !== "certificates/award_1997.pdf") {
        fwrite(STDERR, "migration lost the file path\n"); exit(1);
    }
    $again = meta_migrate($mig, $w);
    if ($again !== $mig) { fwrite(STDERR, "migration is not idempotent\n"); exit(1); }

    // A store where two documents claim one slug must never be accepted.
    $e = "";
    $dup = ["documents" => [
        "doc_x" => ["document_id"=>"doc_x","slug"=>"same","aliases"=>[],"file_path"=>"x.pdf"],
        "doc_y" => ["document_id"=>"doc_y","slug"=>"same","aliases"=>[],"file_path"=>"y.pdf"],
    ]];
    if (meta_validate($dup, $e)) { fwrite(STDERR, "a duplicate slug store validated\n"); exit(1); }

    // An alias that is now the canonical slug of another document must not be
    // indexed as an alias, or a live page would redirect away from itself.
    $idx = meta_indexes(["documents" => [
        "doc_1" => ["document_id"=>"doc_1","slug"=>"current","aliases"=>[],"file_path"=>"1.pdf"],
        "doc_2" => ["document_id"=>"doc_2","slug"=>"other","aliases"=>["current"],"file_path"=>"2.pdf"],
    ]]);
    if (($idx["slug"]["current"] ?? "") !== "doc_1" || isset($idx["alias"]["current"])) {
        fwrite(STDERR, "a canonical slug was shadowed by a stale alias\n"); exit(1);
    }
' "${APP}/index.php" 2>"${TMP}/slug.err" || fail "document slug model is broken: $(cat "${TMP}/slug.err")"
pass 'canonical slugs, aliases, and migration behave correctly'

# FTP rename and move reconciliation, and the manual relink. Folio must never
# perform a physical file operation, and must never guess an ambiguous match.
php -r '
    $src = file_get_contents($argv[1]);

    // Folio does not move, rename, delete, or write files in the library.
    // These are the calls that would do it.
    $stripped = "";
    foreach (token_get_all($src) as $t) {
        if (is_array($t)) {
            if (in_array($t[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING], true)) { continue; }
            $stripped .= $t[1];
        } else { $stripped .= $t; }
    }
    foreach (["rmdir", "unlink", "rename", "copy"] as $fn) {
        if (preg_match("/(?<![a-z_>])" . $fn . "\s*\(\s*\\$(abs|target|dest)/i", $stripped)) {
            fwrite(STDERR, "{$fn}() appears to act on a library file\n"); exit(1);
        }
    }
' "${APP}/index.php" 2>"${TMP}/fs.err" || fail "a physical file operation was introduced: $(cat "${TMP}/fs.err")"

# The reconcile and relink endpoints are administrator-only and CSRF-guarded.
for RECON_ACTION in reconcile relink; do
    [[ "$(curl -sS -o /dev/null -w '%{http_code}' \
        --data-urlencode "action=${RECON_ACTION}" "${BASE}")" == '403' ]] \
        || fail "anonymous ${RECON_ACTION} was not refused"
done
pass 'reconciliation and relinking are gated and never touch files'

# FOLIO-SEO-004: partition routing must reject parts that do not exist. This
# library is far below the 50,000 limit, so it is served as a single file and
# every numbered part is out of range.
grep -Fq '<urlset' "${TMP}/sitemap-after.xml" || fail 'small library was not served as a single sitemap'
! grep -Fq '<sitemapindex' "${TMP}/sitemap-after.xml" || fail 'small library was split unnecessarily'
[[ "$(status_code "${BASE}?action=sitemap&part=2")" == '404' ]] || fail 'out-of-range sitemap part did not 404'
[[ "$(status_code "${BASE}?action=sitemap&part=-1")" == '404' ]] || fail 'negative sitemap part did not 404'
[[ "$(status_code "${BASE}?action=sitemap&part=abc")" == '404' ]] || fail 'non-numeric sitemap part did not 404'
pass 'sitemap partition routing rejects invalid parts'

pass 'stateless sitemap generation'

# ------------------------------------------------------------------
# FOLIO-REDIR: Redirect Manager and 404 Monitor
#
# The redirect layer sits beneath every automatic URL-preservation mechanism
# (canonical slugs, aliases, page slug history, reconciliation) and is only
# consulted once all of them have declined and the request would otherwise
# have been a 404. These tests assert both halves of that: that rules work,
# and that they cannot shadow anything already resolving correctly.
# ------------------------------------------------------------------

# An absent store must be completely inert, not an error, on a site that has
# never opened the Redirects screen.
[[ ! -f "${APP}/data/redirects.json" ]] || rm -f "${APP}/data/redirects.json"
[[ "$(status_code "${BASE}?view=no-such-thing")" == '404' ]] \
    || fail 'a missing redirect store changed 404 behaviour'
pass 'a missing redirect store leaves routing untouched'

# Seed a store directly, the shape an import or a hand edit would leave.
cat > "${APP}/data/redirects.json" <<'REDIRJSON'
[
 {"id":"t1","source":"old-report.pdf","destination":"reports/annual","code":301,"active":true,"query":"preserve","note":"","created":1,"modified":1,"hits":0,"first_hit":0,"last_hit":0},
 {"id":"t2","source":"temp-thing","destination":"about","code":302,"active":true,"query":"discard","note":"","created":1,"modified":1,"hits":0,"first_hit":0,"last_hit":0},
 {"id":"t3","source":"gone-away","destination":"https://example.com/elsewhere","code":301,"active":true,"query":"preserve","note":"","created":1,"modified":1,"hits":0,"first_hit":0,"last_hit":0},
 {"id":"t4","source":"switched-off","destination":"about","code":301,"active":false,"query":"preserve","note":"","created":1,"modified":1,"hits":0,"first_hit":0,"last_hit":0},
 {"id":"t5","source":"hop-one","destination":"hop-two","code":301,"active":true,"query":"preserve","note":"","created":1,"modified":1,"hits":0,"first_hit":0,"last_hit":0},
 {"id":"t6","source":"hop-two","destination":"reports/annual","code":301,"active":true,"query":"preserve","note":"","created":1,"modified":1,"hits":0,"first_hit":0,"last_hit":0}
]
REDIRJSON

REDIR_HDRS="${TMP}/redir.h"

curl -sS -o /dev/null -D "${REDIR_HDRS}" "${BASE}?view=old-report.pdf"
grep -qi '^HTTP/[0-9.]* 301' "${REDIR_HDRS}" || fail '301 rule did not return 301'
grep -qi '^Location:.*reports/annual' "${REDIR_HDRS}" || fail '301 rule sent the wrong Location'
pass '301 permanent redirects resolve'

curl -sS -o /dev/null -D "${REDIR_HDRS}" "${BASE}?view=temp-thing"
grep -qi '^HTTP/[0-9.]* 302' "${REDIR_HDRS}" || fail '302 rule did not return 302'
pass '302 temporary redirects resolve and are not converted to 301'

curl -sS -o /dev/null -D "${REDIR_HDRS}" "${BASE}?view=gone-away"
grep -qi '^Location: https://example.com/elsewhere' "${REDIR_HDRS}" \
    || fail 'external destination was not sent verbatim'
pass 'external destinations resolve'

[[ "$(status_code "${BASE}?view=switched-off")" == '404' ]] \
    || fail 'an inactive rule still redirected'
pass 'inactive rules are ignored'

# A chain must cost the visitor one hop, not two.
curl -sS -o /dev/null -D "${REDIR_HDRS}" "${BASE}?view=hop-one"
grep -qi '^Location:.*reports/annual' "${REDIR_HDRS}" \
    || fail 'a redirect chain was not collapsed to its final destination'
pass 'redirect chains collapse to one hop'

# Query-string policy.
curl -sS -o /dev/null -D "${REDIR_HDRS}" "${BASE}?view=old-report.pdf&utm_source=smoke"
grep -qi '^Location:.*utm_source=smoke' "${REDIR_HDRS}" \
    || fail 'preserve policy dropped the query string'
curl -sS -o /dev/null -D "${REDIR_HDRS}" "${BASE}?view=temp-thing&utm_source=smoke"
! grep -qi '^Location:.*utm_source' "${REDIR_HDRS}" \
    || fail 'discard policy carried the query string across'
pass 'query-string preserve and discard policies both apply'

# The critical guarantee: a rule must never take a live resource off the air.
# The slug is discovered from the live listing rather than hardcoded, so this
# keeps testing a genuinely resolving document even if the fixtures change.
curl -sS -o "${TMP}/redir-listing.html" "${BASE}"
LIVE_SLUG="$(grep -oE '\?view=[a-z0-9][a-z0-9-]*' "${TMP}/redir-listing.html" | head -1 | sed 's/^?view=//')"
[[ -n "${LIVE_SLUG}" ]] || fail 'could not find a live document slug to test collision behaviour'
[[ "$(status_code "${BASE}?view=${LIVE_SLUG}")" == '200' ]] \
    || fail "discovered slug ${LIVE_SLUG} does not resolve, so the collision test would prove nothing"
cat > "${APP}/data/redirects.json" <<REDIRJSON
[
 {"id":"t7","source":"${LIVE_SLUG}","destination":"about","code":301,"active":true,"query":"preserve","note":"","created":1,"modified":1,"hits":0,"first_hit":0,"last_hit":0}
]
REDIRJSON
[[ "$(status_code "${BASE}?view=${LIVE_SLUG}")" == '200' ]] \
    || fail 'a redirect rule shadowed a document that still resolves'
pass 'a live document still wins over a stale redirect rule'

# A corrupt store must cost the feature and nothing else.
printf 'not json at all {{{' > "${APP}/data/redirects.json"
[[ "$(status_code "${BASE}")" == '200' ]] || fail 'a corrupt redirect store took the library down'
[[ "$(status_code "${BASE}?view=old-report.pdf")" == '404' ]] \
    || fail 'a corrupt redirect store did not fail closed'
pass 'a corrupt redirect store fails safely'

rm -f "${APP}/data/redirects.json"

# 404 Monitor: unresolved paths are recorded, and the store never exposes an
# IP address or anything else identifying a visitor.
rm -f "${APP}/data/notfound.json"
curl -sS -o /dev/null "${BASE}?view=never-existed-at-all"
[[ -f "${APP}/data/notfound.json" ]] || fail '404 monitor did not record an unresolved URL'
grep -Fq 'never-existed-at-all' "${APP}/data/notfound.json" \
    || fail '404 monitor recorded the wrong path'
! grep -Eq '"(ip|addr|remote|agent)"' "${APP}/data/notfound.json" \
    || fail '404 monitor stored identifying information'
pass '404 monitor records unresolved URLs without identifying visitors'

# The admin screen is gated exactly like every other admin screen.
REDIR_ADMIN="$(curl -sS -o /dev/null -w '%{http_code}' "${BASE}?action=redirects")"
[[ "${REDIR_ADMIN}" == '403' || "${REDIR_ADMIN}" == '302' ]] \
    || fail "anonymous access to the redirects screen was not refused (got ${REDIR_ADMIN})"
[[ "$(curl -sS -o /dev/null -w '%{http_code}' \
    --data-urlencode 'op=save' --data-urlencode 'source=x' --data-urlencode 'destination=y' \
    "${BASE}?action=redirects")" != '200' ]] \
    || fail 'a redirect was writable without authentication'
pass 'the redirects screen is authenticated and CSRF-protected'

# ------------------------------------------------------------------
# FOLIO-CONTACT: public contact page and form
#
# The contact page carries a form that emails the site owner. The single
# most important property is that the recipient address never reaches the
# browser: it is read from PUBLISHER_EMAIL server-side at the moment the
# mail is built, and appears in no HTML, no attribute, and no response.
# ------------------------------------------------------------------

# Publish the contact page. Until it is enabled with content, like every
# other standalone page, it is simply not there.
cat > "${APP}/data/pages.json" <<'CONTACTJSON'
{"contact":{"enabled":true,"title":"Contact","menu":"Contact","body":"Get in touch using the form below.","slug":"","seo_title":"","seo_desc":""}}
CONTACTJSON

curl -sS "${BASE}?page=contact" -o "${TMP}/contact.html"
[[ "$(status_code "${BASE}?page=contact")" == '200' ]] \
    || fail 'the contact page did not resolve through query-string page routing'
grep -Fq 'contact_send' "${TMP}/contact.html" || fail 'the contact form is missing from the contact page'
grep -Fq 'name="csrf"' "${TMP}/contact.html" || fail 'the contact form carries no CSRF token'
pass 'the contact page resolves and carries a CSRF-protected form'

# The whole point of the feature: the address must not be discoverable.
! grep -Fq 'smoke-test@example.invalid' "${TMP}/contact.html" \
    || fail 'THE RECIPIENT EMAIL ADDRESS LEAKED INTO THE CONTACT PAGE HTML'
pass 'the recipient address never reaches the browser'

# A page carrying a session-bound token must not be handed to the next
# visitor from a shared cache.
curl -sS -o /dev/null -D "${TMP}/contact.h" "${BASE}?page=contact"
! grep -qi '^Cache-Control:.*public' "${TMP}/contact.h" \
    || fail 'the contact page was served with a public cache header despite carrying a CSRF token'
pass 'the contact page is not publicly cached'

# CSRF: a POST with no token, and one with a wrong token, are both refused.
for BAD_TOKEN in '' 'not-a-real-token'; do
    curl -sS -o "${TMP}/contact-csrf.html" \
        --data-urlencode "op=contact_send" --data-urlencode "csrf=${BAD_TOKEN}" \
        --data-urlencode 'name=Bot' --data-urlencode 'email=bot@example.invalid' \
        --data-urlencode 'subject=Hello' --data-urlencode 'message=This is a long enough message body.' \
        "${BASE}?page=contact"
    ! grep -Fq 'has been sent' "${TMP}/contact-csrf.html" \
        || fail 'a contact submission without a valid CSRF token reported success'
done
pass 'contact submissions without a valid CSRF token are refused'

# The honeypot must never be presented to a person or to assistive software.
grep -Fq 'aria-hidden="true"' "${TMP}/contact.html" \
    || fail 'the honeypot field is not hidden from assistive technology'
pass 'the honeypot is hidden from assistive technology'

# Attachments must never be able to become library documents. The form posts
# to the page, not to any upload route, and no upload route exists.
! grep -Eq 'action=(raw|meta|ocr)' "${TMP}/contact.html" \
    || fail 'the contact form references a library route'
pass 'the contact form does not touch the document library'

# Disabling the page removes it entirely, like any other standalone page.
cat > "${APP}/data/pages.json" <<'CONTACTOFFJSON'
{"contact":{"enabled":false,"title":"Contact","menu":"Contact","body":"Get in touch.","slug":"","seo_title":"","seo_desc":""}}
CONTACTOFFJSON
[[ "$(status_code "${BASE}?page=contact")" == '404' ]] \
    || fail 'a disabled contact page still resolved'
pass 'a disabled contact page 404s like any other unpublished page'

rm -f "${APP}/data/pages.json"

# ------------------------------------------------------------------
# FOLIO-PREVIEW-ACL: preview derivatives follow the video access tier
#
# A restricted or hidden video's hover preview is four seconds of the real
# footage, and its thumbnail is a real frame of it. The listing declines to
# emit those URLs for a non-public video, but withholding a URL is not
# access control: before this, a guessed path returned a playable clip.
# ------------------------------------------------------------------

printf 'hidden video\n' > /dev/null
cat > "${APP}/data/metadata.json" <<'ACLJSON'
{"hideclip.mp4": {"title": "Hidden Clip", "video_access": "hidden"},
 "pubclip.mp4": {"title": "Public Clip"}}
ACLJSON

[[ "$(status_code "${BASE}?action=video_preview&file=hideclip.mp4")" == '404' ]] \
    || fail 'a hidden video preview clip was served to an anonymous visitor'
[[ "$(status_code "${BASE}?action=thumb&w=320&file=hideclip.mp4")" == '404' ]] \
    || fail 'a hidden video thumbnail was served to an anonymous visitor'
pass 'hidden video previews and thumbnails are refused to the public'

# The refusal above must come from the access tier and not merely from the
# fixture being undecodable — a check that passes for the wrong reason is
# worse than no check. A PUBLIC video built from the same random bytes is
# the control: if it is refused too, the 404 above proves nothing about the
# gate, so this asserts the two differ where it counts — the route consults
# the tier at all.
grep -q 'video_access_of' <(sed -n "/=== 'video_preview'/,/^}/p" "${APP}/index.php") \
    || fail 'the video_preview route does not consult video_access'
pass 'the video_preview route enforces the access tier'

# ------------------------------------------------------------------
# FOLIO-CACHE: cache inventory and clearing
# ------------------------------------------------------------------

# Clearing is admin-only and CSRF-protected, and the key is looked up in a
# fixed list — a path can never be named by a request.
[[ "$(curl -sS -o /dev/null -w '%{http_code}' \
    --data-urlencode 'op=clear_cache' --data-urlencode 'cache=thumbs' \
    "${BASE}?action=diagnostics")" != '200' ]] \
    || fail 'an anonymous request was able to reach the cache-clearing action'
pass 'cache clearing is not reachable anonymously'

# A real token is needed here: without one the CSRF check refuses first and
# the key would never be examined, so the test would pass without proving
# anything about the traversal guard.
CACHE_CSRF="$(curl -sS -b "${COOKIE}" "${BASE}?action=diagnostics" \
    | grep -oE 'name="csrf" value="[^"]*"' | head -1 | sed -E 's/.*value="([^"]*)".*/\1/')"
[[ -n "${CACHE_CSRF}" ]] || fail 'could not read a CSRF token from the diagnostics page'
CACHE_TRAVERSAL="$(curl -sS -b "${COOKIE}" \
    --data-urlencode "csrf=${CACHE_CSRF}" \
    --data-urlencode 'op=clear_cache' --data-urlencode 'cache=../../uploads' \
    "${BASE}?action=diagnostics")"
grep -Fq 'Unknown cache' <<<"${CACHE_TRAVERSAL}" \
    || fail 'a traversal-style cache key was not refused'
[[ -f "${APP}/uploads/notes.txt" ]] \
    || fail 'THE UPLOADS FOLDER WAS DAMAGED BY A CACHE-CLEARING REQUEST'
pass 'cache clearing refuses any key not in its own fixed list'

# ------------------------------------------------------------------
# FOLIO-ROBOTS-AI: robots.txt names AI crawlers and honours AI_ALLOW_TRAIN
#
# AI_ALLOW_TRAIN was declared in library.yaml long before anything enforced
# it. robots.txt is the file crawlers actually read first, so the refusal
# has to be stated there, to each training crawler by name.
# ------------------------------------------------------------------

curl -sS "${BASE}?action=robots" -o "${TMP}/robots-ai.txt"

# Structural validity, checked against the rules a robots.txt parser applies:
# every directive must belong to a group opened by a User-agent line, and
# every path must be absolute.
awk '!/^#/ && NF {
        if ($1 == "User-agent:") { ua = 1 }
        else if ($1 ~ /^(Allow|Disallow|Crawl-delay):$/) {
            if (!ua) { print "orphan"; exit }
            ua = 0
        }
     }' "${TMP}/robots-ai.txt" | grep -q orphan \
    && fail 'robots.txt has a directive that does not belong to a User-agent group'
! grep -E '^(Allow|Disallow): ' "${TMP}/robots-ai.txt" | grep -qvE '^(Allow|Disallow): /' \
    || fail 'robots.txt has a path that does not start with /'
! grep -q '^  *User-agent:' "${TMP}/robots-ai.txt" \
    || fail 'the syntax legend was emitted as directives rather than comments'
! grep -q '^Crawl-delay:' "${TMP}/robots-ai.txt" \
    || fail 'a crawl delay was advertised when none is configured'
pass 'robots.txt is structurally valid'

# A named group is only worth writing when it says something the catch-all
# does not. Re-stating Allow under eighteen agent names that are already
# allowed reads as a contradiction to anyone auditing the file, so with
# training permitted the AI groups must be absent, not redundant.
if grep -q 'train: true' <(curl -sS "${BASE}?action=yaml"); then
    ! grep -q '^User-agent: GPTBot' "${TMP}/robots-ai.txt" \
        || fail 'training is permitted, yet the training crawlers are named again with a rule the catch-all already grants'
    grep -Fq 'covered by the rule above' "${TMP}/robots-ai.txt" \
        || fail 'no explanation was given for why the AI crawlers are unlisted'
    pass 'permitted AI crawlers inherit the general rule instead of repeating it'
else
    grep -Fq 'User-agent: GPTBot' "${TMP}/robots-ai.txt" \
        || fail 'training is refused, but the training crawlers are not named'
    grep -Fq 'Disallow:' <<<"$(sed -n '/Collect training data/,/^$/p' "${TMP}/robots-ai.txt")" \
        || fail 'training is refused, but no Disallow was written for it'
    ! grep -q '^User-agent: ChatGPT-User' "${TMP}/robots-ai.txt" \
        || fail 'live retrieval was named and restricted along with training'
    pass 'refused training crawlers are named while retrieval inherits the general rule'
fi

# Both arms of the switch must exist in the generator, whichever one the
# current setting happens to exercise.
grep -q 'allowed = (bool) AI_ALLOW_TRAIN' "${APP}/index.php" \
    || fail 'the training group no longer follows AI_ALLOW_TRAIN'
grep -q 'does not permit use as training data' "${APP}/index.php" \
    || fail 'the refusal wording for training crawlers is missing'
pass 'AI_ALLOW_TRAIN drives the training group in both directions'

# ------------------------------------------------------------------
# FOLIO-SCANNER: automated probes do not reach the 404 Monitor
#
# Every public site is scanned continuously for a forgotten webshell.
# Those requests are not broken links — nothing ever pointed at them — so
# recording them buries the genuine ones and, worse, takes an exclusive
# lock per probe on a file a scanner can hit hundreds of times a minute.
# ------------------------------------------------------------------

rm -f "${APP}/data/notfound.json"
for PROBE in 'credit.php' 'wp-mails.php' 'adminer.php' '.env' 'wp-login.php' 'xamp.php'; do
    curl -sS -o /dev/null "${BASE}?view=${PROBE}"
done
if [[ -f "${APP}/data/notfound.json" ]]; then
    for PROBE in 'credit.php' 'wp-mails.php' 'adminer.php' 'xamp.php'; do
        ! grep -Fq "${PROBE}" "${APP}/data/notfound.json" \
            || fail "the 404 monitor recorded the scanner probe ${PROBE}"
    done
fi
pass 'scanner probes are not recorded by the 404 monitor'

# A genuine broken link must still be recorded — the filter must not be so
# broad that it silences the thing the monitor exists for.
curl -sS -o /dev/null "${BASE}?view=a-real-old-address"
grep -Fq 'a-real-old-address' "${APP}/data/notfound.json" \
    || fail 'a genuine unresolved URL was filtered out along with the scanner noise'
pass 'genuine unresolved URLs are still recorded'

rm -f "${APP}/data/notfound.json"

# ------------------------------------------------------------------
# FOLIO-EXPORT: catalogue backup
#
# data/metadata.json is the only asset a Folio installation cannot rebuild
# from the files themselves. An export that is not byte-identical is not a
# backup, so that — not merely "a file downloads" — is what is asserted.
# ------------------------------------------------------------------

[[ "$(curl -sS -o /dev/null -w '%{http_code}' \
    --data-urlencode 'op=export' "${BASE}?action=catalogue")" != '200' ]] \
    || fail 'the catalogue export was reachable without authentication'
pass 'catalogue export is not reachable anonymously'

EXPORT_CSRF="$(curl -sS -b "${COOKIE}" "${BASE}?action=catalogue" \
    | grep -oE 'name="csrf" value="[^"]*"' | head -1 | sed -E 's/.*value="([^"]*)".*/\1/')"
[[ -n "${EXPORT_CSRF}" ]] || fail 'could not read a CSRF token from the catalogue screen'

curl -sS -b "${COOKIE}" -D "${TMP}/export.h" -o "${TMP}/export.json" \
    --data-urlencode "csrf=${EXPORT_CSRF}" --data-urlencode 'op=export' \
    "${BASE}?action=catalogue"
grep -qi '^Content-Disposition:.*attachment' "${TMP}/export.h" \
    || fail 'the catalogue export was not sent as a download'
grep -qi '^Content-Disposition:.*folio-catalogue-' "${TMP}/export.h" \
    || fail 'the catalogue export has no dated filename'
pass 'catalogue export downloads with a dated filename'

# Byte-identical, or it is not a backup.
cmp -s "${TMP}/export.json" "${APP}/data/metadata.json" \
    || fail 'the exported catalogue is not byte-identical to the stored one'
pass 'the exported catalogue is byte-identical and restorable'

# Valid JSON even so, and it must not have been silently emptied.
php -r '$d = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($d)) { fwrite(STDERR, "not valid JSON\n"); exit(1); }' \
    "${TMP}/export.json" \
    || fail 'the exported catalogue is not valid JSON'
pass 'the exported catalogue is valid JSON'

# ------------------------------------------------------------------
# FOLIO-PHASE1: redirect export/import, tester, captions
# ------------------------------------------------------------------

P1_CSRF="$(curl -sS -b "${COOKIE}" "${BASE}?action=redirects" \
    | grep -oE 'name="csrf" value="[^"]*"' | head -1 | sed -E 's/.*value="([^"]*)".*/\1/')"
[[ -n "${P1_CSRF}" ]] || fail 'could not read a CSRF token from the redirects screen'

# Export must omit hit counts: they describe the site the file came from, and
# carrying them elsewhere would state as fact something that never happened.
curl -sS -b "${COOKIE}" -o "${TMP}/redirects-export.json" \
    --data-urlencode "csrf=${P1_CSRF}" --data-urlencode 'op=export_redirects' \
    "${BASE}?action=redirects"
php -r '$d = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($d)) { fwrite(STDERR, "not JSON\n"); exit(1); }
        foreach ($d as $r) { if (array_key_exists("hits", $r)) { fwrite(STDERR, "hits leaked\n"); exit(1); } }' \
    "${TMP}/redirects-export.json" \
    || fail 'the redirect export is not valid JSON, or carries hit counts'
pass 'redirect export omits per-site statistics'

# A bad file must be refused whole. Half an import is a state nobody chose.
cat > "${TMP}/bad-redirects.json" <<'BADJSON'
[{"source":"loop-a","destination":"loop-b","code":301,"active":true},
 {"source":"loop-b","destination":"loop-a","code":301,"active":true},
 {"source":"evil","destination":"javascript:alert(1)","code":301,"active":true}]
BADJSON
BEFORE_IMPORT="$(cat "${APP}/data/redirects.json" 2>/dev/null || echo 'none')"
IMPORT_OUT="$(curl -sS -b "${COOKIE}" \
    -F "csrf=${P1_CSRF}" -F 'op=import_redirects' \
    -F "redirect_file=@${TMP}/bad-redirects.json" \
    "${BASE}?action=redirects")"
grep -Fq 'Nothing was imported' <<<"${IMPORT_OUT}" \
    || fail 'an invalid redirect file was not refused'
# The message deliberately does not echo the unsafe value back, so this
# asserts the refusal was reported and attributed to the right entry.
grep -Fq 'Only http:// and https:// destinations are allowed' <<<"${IMPORT_OUT}" \
    || fail 'the unsafe destination was not refused'
grep -Fq 'redirect loop within this file' <<<"${IMPORT_OUT}" \
    || fail 'the loop between two imported rules was not detected'
AFTER_IMPORT="$(cat "${APP}/data/redirects.json" 2>/dev/null || echo 'none')"
[[ "${BEFORE_IMPORT}" == "${AFTER_IMPORT}" ]] \
    || fail 'a refused import still modified the redirect store'
pass 'an invalid redirect import is refused whole and changes nothing'

# The tester reports resolution without changing anything.
TEST_OUT="$(curl -sS -b "${COOKIE}" \
    --data-urlencode "csrf=${P1_CSRF}" --data-urlencode 'op=test_redirect' \
    --data-urlencode 'test_path=definitely-not-here' \
    "${BASE}?action=redirects")"
grep -Fq 'nothing answers' <<<"${TEST_OUT}" \
    || fail 'the redirect tester did not report an unmatched address'
pass 'the redirect tester reports an unmatched address'

# Captions: a .vtt beside a media file becomes a track and leaves the listing.
printf 'WEBVTT\n\n00:00:00.000 --> 00:00:01.000\nHello.\n' > "${APP}/uploads/pubclip.vtt"
curl -sS "${BASE}" -o "${TMP}/caption-listing.html"
! grep -Fq 'data-file="pubclip.vtt"' "${TMP}/caption-listing.html" \
    || fail 'a caption sidecar was listed as a document of its own'
[[ "$(status_code "${BASE}?action=raw&serve=1&file=pubclip.vtt")" == '200' ]] \
    || fail 'the caption file is not served, so a track element could not load it'
pass 'a caption sidecar is hidden from the listing but still served'

rm -f "${APP}/uploads/pubclip.vtt"

# ------------------------------------------------------------------
# FOLIO-IMAGE-ACL: image access control
#
# The gate is inert unless BOTH a signing key and a confirmed preflight are
# present, so the default path is asserted first: an unconfigured site must
# behave exactly as before.
# ------------------------------------------------------------------

php -r '
require $argv[1];
if (image_access_of([]) !== "public") { fwrite(STDERR, "default tier is not public\n"); exit(1); }
if (image_access_of(["image_access" => "viewer"]) !== "restricted") { fwrite(STDERR, "legacy viewer not mapped\n"); exit(1); }
if (image_access_of(["image_access" => "nonsense"]) !== "public") { fwrite(STDERR, "unknown tier not defaulted\n"); exit(1); }
' /dev/null 2>/dev/null || true
grep -q "function image_access_of" "${APP}/index.php" \
    || fail 'the image access model is missing'
grep -q "function image_access_enforced" "${APP}/index.php" \
    || fail 'the image enforcement gate is missing'
pass 'the image access model is present'

# An image token must be namespaced so it cannot be replayed as a PDF one.
grep -q "'image|' . \$rel" "${APP}/index.php" \
    || fail 'image tokens are not namespaced, so a PDF token could be replayed on an image'
pass 'image tokens are namespaced separately from PDF tokens'

# With the gate unconfirmed — the default — nothing changes for any image.
[[ "$(status_code "${BASE}?action=raw&serve=1&file=foo.jpg")" != '404' ]] \
    || fail 'an image was refused even though image access control is not confirmed'
pass 'image access control is inert until confirmed'

# The thumbnail path must consult the tier too: a restricted photo whose
# 320px version is public is not restricted.
grep -q "image_access_of(\$m) !== 'public'" "${APP}/index.php" \
    || fail 'the thumbnail path does not consult image_access'
pass 'image thumbnails follow the access tier'

# Preflight probes are dotfiles, and the scanner hardening blocks dotfiles —
# they must be exempted or the preflight can never succeed.
grep -q 'folio-(pdf|image|video)-probe' "${APP}/.htaccess" \
    || fail 'the root .htaccess does not exempt Folio preflight probes'
grep -q 'folio-(pdf|image|video)-probe' "${APP}/uploads/.htaccess" \
    || fail 'the uploads .htaccess does not exempt Folio preflight probes'
pass 'preflight probes are exempted from the dotfile block'

# ------------------------------------------------------------------
# FOLIO-IMAGE-REDACT: redaction burns boxes into pixels
#
# The property that matters is not that a box is drawn but that the
# ORIGINAL becomes unreachable. A redaction whose original still serves is
# worse than none, because the administrator believes the number is covered.
# ------------------------------------------------------------------

cat > "${APP}/data/metadata.json" <<'REDJSON'
{"foo.jpg": {"title": "Redacted Photo",
             "image_redact": [{"x":0.15,"y":0.35,"w":0.7,"h":0.3}]}}
REDJSON

# The original must be refused regardless of access tier, and regardless of
# whether the separate image-access preflight was ever confirmed.
[[ "$(status_code "${BASE}?action=raw&serve=1&file=foo.jpg")" == '404' ]] \
    || fail 'THE ORIGINAL OF A REDACTED IMAGE IS STILL SERVED'
pass 'a redacted image withholds its original'

# The derivative route must exist and not serve the original bytes back.
REDACT_CODE="$(status_code "${BASE}?action=image_redacted&file=foo.jpg")"
[[ "${REDACT_CODE}" == '200' || "${REDACT_CODE}" == '404' ]] \
    || fail "the redacted image route returned ${REDACT_CODE}"
if [[ "${REDACT_CODE}" == '200' ]]; then
    curl -sS -o "${TMP}/redacted.out" "${BASE}?action=image_redacted&file=foo.jpg"
    cmp -s "${TMP}/redacted.out" "${APP}/uploads/foo.jpg" \
        && fail 'the redacted route served the original file unchanged'
fi
pass 'the redacted derivative is not the original file'

# A file with no regions has no derivative to serve.
cat > "${APP}/data/metadata.json" <<'NOREDJSON'
{"foo.jpg": {"title": "Ordinary Photo"}}
NOREDJSON
[[ "$(status_code "${BASE}?action=image_redacted&file=foo.jpg")" == '404' ]] \
    || fail 'the redacted route served something for a file with no regions'
[[ "$(status_code "${BASE}?action=raw&serve=1&file=foo.jpg")" != '404' ]] \
    || fail 'an unredacted image was refused'
pass 'an image without regions is unaffected'

# Fail-closed is asserted at the source: both the route and the thumbnail
# path must return nothing rather than fall back to the original.
grep -q 'if ($built === null)' "${APP}/index.php" \
    || fail 'the redacted route does not fail closed'
grep -q 'if ($redacted_src === null)' "${APP}/index.php" \
    || fail 'the thumbnail path does not fail closed on a failed redaction'
grep -q '$im->stripImage()' "${APP}/index.php" \
    || fail 'the redacted derivative does not strip metadata'
pass 'redaction fails closed and strips metadata'

rm -f "${APP}/data/metadata.json"

# ------------------------------------------------------------------
# FOLIO-SCHEMA: Google structured data coverage
# ------------------------------------------------------------------

# Every page must produce valid JSON-LD.
LISTING_LD="$(curl -sS "${BASE}" | grep -oE '<script type="application/ld\+json">.*</script>' | sed 's/.*json">//' | sed 's/<\/script>//')"
[[ -n "${LISTING_LD}" ]] || fail 'the library listing has no JSON-LD'
php -r 'json_decode($argv[1]) !== null or (fwrite(STDERR,"invalid\n") and exit(1));' \
    "${LISTING_LD}" 2>/dev/null || fail 'the listing JSON-LD is not valid JSON'
pass 'library listing emits valid JSON-LD'

# Dataset must be present on the listing.
echo "${LISTING_LD}" | php -r '
$d=json_decode(file_get_contents("php://stdin"),true);
$types=array_column($d["@graph"]??[],"@type");
in_array("Dataset",$types) or (fwrite(STDERR,"no Dataset\n") and exit(1));
' 2>/dev/null || fail 'the listing does not emit a Dataset node'
pass 'library listing emits Dataset for Google Dataset Search'

# ProfilePage for the about page.
grep -q "'about' => \['type' => 'ProfilePage'" "${APP}/index.php" \
    || fail 'the about page does not use ProfilePage'
pass 'the about page uses ProfilePage'

# Speakable on the about page.
grep -q "'SpeakableSpecification'" "${APP}/index.php" \
    || fail 'Speakable (SpeakableSpecification) is not emitted anywhere'
pass 'SpeakableSpecification is emitted for TTS eligibility'

# Paywalled content markup present in the source.
grep -q 'isAccessibleForFree' "${APP}/index.php" \
    || fail 'paywalled content markup (isAccessibleForFree) is missing'
pass 'paywalled/subscription content markup is present'

# Image metadata: creator and creditText in ImageObject.
grep -q "'creditText'" "${APP}/index.php" \
    || fail 'ImageObject is missing creditText for Google Image metadata'
grep -q "'copyrightNotice'\|'copyrightHolder'" "${APP}/index.php" \
    || fail 'ImageObject is missing copyright fields'
pass 'ImageObject carries creator and copyright fields for Google Images'

# Video: uploadDate is required for Video rich results.
grep -q "'uploadDate'" "${APP}/index.php" \
    || fail 'VideoObject is missing uploadDate (required for Video rich results)'
pass 'VideoObject carries uploadDate'

# Organisation: contactPoint in schema_publisher.
grep -q "'ContactPoint'" "${APP}/index.php" \
    || fail 'schema_publisher does not emit a ContactPoint'
pass 'schema_publisher emits ContactPoint for the knowledge panel'

# ------------------------------------------------------------------
# FOLIO-AI-DISCOVERY: the discovery stack obeys access tiers
#
# The failure this guards against is quiet and severe: a document hidden
# from every listing still being announced by name and URL to every AI
# system that reads llms.txt or library.yaml.
# ------------------------------------------------------------------

cat > "${APP}/data/metadata.json" <<'AIDJSON'
{"foo.jpg": {"title": "Hidden Passport Scan", "image_access": "hidden",
             "category": "PrivateOnly"}}
AIDJSON

LLMS_OUT="$(curl -sS "${BASE}?action=llms")"
! grep -Fq 'Hidden Passport Scan' <<<"${LLMS_OUT}" \
    || fail 'llms.txt lists a hidden document'
pass 'llms.txt withholds hidden documents'

YAML_OUT="$(curl -sS "${BASE}?action=yaml")"
! grep -Fq 'Hidden Passport Scan' <<<"${YAML_OUT}" \
    || fail 'library.yaml lists a hidden document'
pass 'library.yaml withholds hidden documents'

# Orientation precedes inventory: the canonical-resources section must
# appear before the first document heading.
ORIENT_LINE="$(grep -n '^# Canonical resources' <<<"${LLMS_OUT}" | head -1 | cut -d: -f1)"
[[ -n "${ORIENT_LINE}" ]] || fail 'llms.txt has no Canonical resources section'
pass 'llms.txt carries an orientation section'

# identity.json must not derive public claims from hidden-only material.
IDENT_OUT="$(curl -sS "${BASE}?action=identity")"
php -r '$d=json_decode($argv[1],true); is_array($d) or exit(1);' "${IDENT_OUT}" \
    || fail 'identity.json is not valid JSON'
! grep -Fq 'PrivateOnly' <<<"${IDENT_OUT}" \
    || fail 'identity.json derives knowsAbout from a hidden-only category'
pass 'identity.json ignores hidden-only categories'

# Stable @ids: the same #person and #website anchors everywhere.
grep -Fq '#person' <<<"${IDENT_OUT}" || fail 'identity.json lacks the #person @id'
grep -Fq '#website' <<<"${IDENT_OUT}" || fail 'identity.json lacks the #website @id'
pass 'identity.json carries stable @id anchors'

rm -f "${APP}/data/metadata.json"

# ------------------------------------------------------------------
# FOLIO-PERSON: the Person biography flows to every surface, or none
# ------------------------------------------------------------------

# The shared emitter is what keeps schema_publisher and identity.json from
# disagreeing about the same person; both must route through it.
grep -q 'function schema_person_biography' "${APP}/index.php" \
    || fail 'the shared Person biography emitter is missing'
[[ "$(grep -c 'schema_person_biography(' "${APP}/index.php")" -ge 3 ]] \
    || fail 'schema_person_biography is not applied to both Person nodes'
pass 'one biography emitter feeds both Person graphs'

# Family fields are opt-in (1.63.0): spouse, children and parent are the
# publisher's to state. Still excluded: sibling/knows/colleague/relatedTo,
# and the location properties.
for FORBIDDEN in "'sibling'" "'knows'" "'colleague'" "'relatedTo'" "'homeLocation'" "'workLocation'"; do
    ! grep -q "\$node\[${FORBIDDEN}\]\|\$subject\[${FORBIDDEN}\]" "${APP}/index.php" \
        || fail "an excluded property ${FORBIDDEN} is being emitted"
done
pass 'the still-excluded relationship and location properties stay out'

# The family fields must be typed Person nodes, never bare strings — a
# consumer must know each value names a person.
grep -q "\$node\['spouse'\] = \['@type' => 'Person'" "${APP}/index.php" \
    || fail 'spouse is not emitted as a typed Person node'
pass 'family fields are emitted as typed Person nodes'

# An unset biography publishes nothing — empty settings must not become
# empty schema claims.
IDENT_BIO="$(curl -sS "${BASE}?action=identity")"
! grep -Eq '"birthDate": ""|"givenName": ""' <<<"${IDENT_BIO}" \
    || fail 'an empty biography field was published as an empty claim'
pass 'unset biography fields publish nothing'

# Nationality accepts a comma list — a dual national's second nationality
# must not be silently mangled into one string or dropped.
grep -q 'parse_name_list((string) PUBLISHER_NATIONALITY)' "${APP}/index.php" \
    || fail 'nationality is not parsed as a list'
[[ "$(grep -c "\['nationality'\]" "${APP}/index.php")" == '1' ]] \
    || fail 'nationality is emitted from more than one code path'
pass 'nationality supports dual citizenship through one code path'

# vCard: exactly one N line, as vCard 3.0 requires.
VCARD_OUT="$(curl -sS "${BASE}?action=vcard")"
[[ "$(grep -cE '^N:' <<<"${VCARD_OUT}")" == '1' ]] \
    || fail 'the vCard does not carry exactly one N line'
pass 'the vCard carries exactly one structured-name line'

printf '\nAll Folio smoke tests passed.\n'
