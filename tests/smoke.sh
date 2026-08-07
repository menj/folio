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
return ['PDF_GATE_CONFIRMED' => true];
PHP

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
grep -Fq 'full transcription available' "${TMP}/pdf-llms.txt" || fail 'llms.txt did not note transcription availability for the hidden PDF'
pass 'pdf_access does not affect sitemap, robots meta, or llms.txt indexability'

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
# Neither may be advertised: a crawler invited to them would be refused.
grep -Fq 'public-doc.pdf' "${TMP}/pdf-sitemap.xml" \
    || fail 'a public PDF is missing from the PDF sitemap'
# Every PDF in the library is listed, including ones with a pdf_access
# setting: the sitemap's job is to get documents crawled, and the access
# setting governs delivery, not discovery.
grep -Fq '/foo.pdf' "${TMP}/pdf-sitemap.xml" \
    || fail 'a PDF with an access setting was left out of the PDF sitemap'
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

printf '\nAll Folio smoke tests passed.\n'
