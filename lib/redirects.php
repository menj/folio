<?php
/**
 * Folio — Redirect Manager and 404 Monitor.
 *
 * Folio already preserves URLs in several ways: canonical document slugs and
 * their aliases, path-derived legacy slugs, page slug history, the legacy
 * category map, and FTP rename/move reconciliation. All of those run first
 * and emit their own 301s. This module is the layer beneath them — the
 * explicit, administrator-declared safety net for URLs that no automatic
 * mechanism can infer, consulted only once every one of them has declined
 * and the request is otherwise about to become a 404.
 *
 * It is deliberately not a rewrite engine. Sources are matched exactly, never
 * by pattern, so what a rule does is knowable by reading it. No regex, no
 * wildcards, no rule language.
 *
 * Lives in /lib for the same reason lib/video.php does: index.php is already
 * past the size the single-file design serves well, and new subsystems should
 * not keep adding to it. Nothing here executes at include time, so it is safe
 * to require early, before BASE_URL and the other constants its function
 * bodies reference exist.
 */

/** Where the redirect rules live. data/ is already denied to the web and has
 *  the PHP engine switched off, so the store inherits both protections. */
function redirects_file(): string
{
    return __DIR__ . '/../data/redirects.json';
}

function redirects_lock_file(): string
{
    return __DIR__ . '/../data/redirects.lock';
}

/** Where unresolved-URL counts live. Separate from the rules so a busy 404
 *  log can never block or corrupt a rule write, and so clearing one does not
 *  touch the other. */
function notfound_file(): string
{
    return __DIR__ . '/../data/notfound.json';
}

function notfound_lock_file(): string
{
    return __DIR__ . '/../data/notfound.lock';
}

/** Hard ceiling on distinct recorded 404 paths. A crawler hunting for
 *  wp-admin variants can invent thousands of unique URLs in a minute; without
 *  a bound the store would grow until the disk or the JSON decoder gave out.
 *  When full, the least-recently-seen half is dropped. */
define('FOLIO_NOTFOUND_MAX', 500);

/** Read the redirect store. Returns [] for absent, unreadable, empty, or
 *  structurally invalid content — a broken store must cost the redirect
 *  feature and nothing else, never the site. */
function redirects_load(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $path = redirects_file();
    if (!is_file($path)) {
        return $cache = [];
    }
    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $cache = [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        error_log('Folio: data/redirects.json is not valid JSON; redirects are inactive until it is fixed or removed.');
        return $cache = [];
    }
    $out = [];
    foreach ($data as $row) {
        $row = redirect_sanitise_record($row);
        if ($row !== null) {
            $out[$row['id']] = $row;
        }
    }
    return $cache = $out;
}

/**
 * Normalise one stored record, or return null if it cannot be trusted.
 * Unknown keys are preserved untouched, so a record written by a later
 * version that added fields survives a round trip through an older one.
 */
function redirect_sanitise_record($row): ?array
{
    if (!is_array($row)) {
        return null;
    }
    $id = (string) ($row['id'] ?? '');
    $source = (string) ($row['source'] ?? '');
    $dest = (string) ($row['destination'] ?? '');
    if ($id === '' || $source === '' || $dest === '') {
        return null;
    }
    $code = (int) ($row['code'] ?? 301);
    if ($code !== 301 && $code !== 302) {
        $code = 301;
    }
    $row['id'] = $id;
    $row['source'] = $source;
    $row['destination'] = $dest;
    $row['code'] = $code;
    $row['active'] = !empty($row['active']);
    $row['query'] = (($row['query'] ?? 'preserve') === 'discard') ? 'discard' : 'preserve';
    $row['note'] = (string) ($row['note'] ?? '');
    $row['created'] = (int) ($row['created'] ?? 0);
    $row['modified'] = (int) ($row['modified'] ?? 0);
    $row['hits'] = max(0, (int) ($row['hits'] ?? 0));
    $row['first_hit'] = (int) ($row['first_hit'] ?? 0);
    $row['last_hit'] = (int) ($row['last_hit'] ?? 0);
    return $row;
}

/**
 * Mutate the redirect store under an exclusive lock, mirroring
 * meta_update()'s shape exactly: lock, read, mutate, atomic replace with a
 * last-known-good backup. Returns the new array, or false if anything
 * prevented a complete write — never a partial one.
 */
function redirects_update(callable $mutator)
{
    $dir = dirname(redirects_file());
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return false;
    }
    if (is_link(redirects_lock_file())) {
        return false;
    }
    $lock = @fopen(redirects_lock_file(), 'c+b');
    if ($lock === false || !@flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            @fclose($lock);
        }
        return false;
    }
    // Re-read inside the lock rather than trusting the cache: another request
    // may have written since this one started.
    $current = [];
    if (is_file(redirects_file())) {
        $raw = @file_get_contents(redirects_file());
        $decoded = ($raw === false || trim($raw) === '') ? [] : json_decode($raw, true);
        if (!is_array($decoded)) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
            return false;
        }
        foreach ($decoded as $row) {
            $row = redirect_sanitise_record($row);
            if ($row !== null) {
                $current[$row['id']] = $row;
            }
        }
    }
    $next = $mutator($current);
    if (!is_array($next)) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
        return false;
    }
    $json = json_encode(array_values($next), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ok = is_string($json) && atomic_replace_file(redirects_file(), $json . "\n", 0600, true);
    @flock($lock, LOCK_UN);
    @fclose($lock);
    if ($ok) {
        $GLOBALS['folio_redirects_override'] = $next;
    }
    return $ok ? $next : false;
}

/** The live rule set, preferring anything written during this request. */
function redirects_all(): array
{
    if (isset($GLOBALS['folio_redirects_override']) && is_array($GLOBALS['folio_redirects_override'])) {
        return $GLOBALS['folio_redirects_override'];
    }
    return redirects_load();
}

/**
 * Reduce a requested URL to the comparable form a rule is stored in.
 *
 * Both clean URLs (/old/report.pdf) and query-string URLs (?view=old) reduce
 * to the same shape, so a rule keeps working if PRETTY_URLS is ever toggled.
 * Percent-encoding is decoded once so an encoded and unencoded spelling of
 * the same path match each other; the result is compared as bytes, never
 * re-encoded or re-interpreted.
 *
 * Returns '' for anything that cannot be safely reduced — traversal, control
 * characters, a scheme — which callers must treat as "no rule can match".
 */
function redirect_normalise_source(string $raw): string
{
    $s = trim($raw);
    if ($s === '') {
        return '';
    }
    // A full URL is accepted for convenience when typing a rule, but only its
    // path survives: matching is always against this site's own paths.
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $s)) {
        $parsed = @parse_url($s);
        if (!is_array($parsed) || !isset($parsed['path'])) {
            return '';
        }
        $s = (string) $parsed['path'];
    }
    $s = (string) strtok($s, '#');       // fragments never reach the server
    $s = (string) strtok($s, '?');       // the query is policy, not identity
    $s = rawurldecode($s);
    // Reject rather than sanitise: silently "cleaning" a traversal attempt
    // into something harmless would store a rule whose text does not describe
    // what it matches.
    if (strpos($s, "\0") !== false || preg_match('/[\x00-\x1F\x7F]/', $s)) {
        return '';
    }
    $s = str_replace('\\', '/', $s);
    $s = preg_replace('#/+#', '/', $s);
    $s = trim((string) $s, '/');
    if ($s === '') {
        return '';
    }
    foreach (explode('/', $s) as $seg) {
        if ($seg === '.' || $seg === '..') {
            return '';
        }
    }
    return $s;
}

/**
 * Validate a destination and return it, or '' if it must be refused.
 *
 * Internal destinations are stored in the same normalised, root-relative form
 * as a source. External ones keep their absolute URL but must be http(s):
 * anything else — javascript:, data:, vbscript: — is refused outright, since
 * a stored redirect is a link the site will follow on a visitor's behalf.
 */
function redirect_validate_destination(string $raw, ?string &$error = null): string
{
    $d = trim($raw);
    if ($d === '') {
        $error = 'A destination is required.';
        return '';
    }
    if (strpos($d, "\0") !== false || preg_match('/[\x00-\x1F\x7F]/', $d)) {
        // A newline in a destination is a Location: header injection attempt.
        $error = 'The destination contains control characters.';
        return '';
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $d, $m)) {
        $scheme = strtolower(rtrim($m[0], ':'));
        if ($scheme !== 'http' && $scheme !== 'https') {
            $error = 'Only http:// and https:// destinations are allowed.';
            return '';
        }
        if (filter_var($d, FILTER_VALIDATE_URL) === false) {
            $error = 'That does not look like a valid URL.';
            return '';
        }
        return $d;
    }
    // Protocol-relative //evil.example is an external destination wearing an
    // internal-looking spelling. Refuse it rather than guess.
    if (strpos($d, '//') === 0) {
        $error = 'Write an external destination with its full https:// address.';
        return '';
    }
    $internal = redirect_normalise_source($d);
    if ($internal === '') {
        $error = 'That destination is not a valid internal path.';
        return '';
    }
    return $internal;
}

/** Whether a stored destination points off this site. */
function redirect_destination_is_external(string $dest): bool
{
    return (bool) preg_match('#^https?://#i', $dest);
}

/** The absolute URL a destination resolves to, for the Location header. */
function redirect_destination_url(string $dest): string
{
    if (redirect_destination_is_external($dest)) {
        return $dest;
    }
    return rtrim(BASE_URL, '/') . '/' . str_replace('%2F', '/', rawurlencode($dest)) . '/';
}

/** Find the single active rule matching a normalised path, or null. */
function redirect_match(string $normalised): ?array
{
    if ($normalised === '') {
        return null;
    }
    foreach (redirects_all() as $row) {
        if (!empty($row['active']) && $row['source'] === $normalised) {
            return $row;
        }
    }
    return null;
}

/**
 * Follow a rule to its ultimate destination, collapsing a chain so a visitor
 * makes one hop rather than several. Bounded, so a cycle that somehow reached
 * the store — an older file, a hand edit — costs one wasted lookup rather
 * than hanging the request.
 */
function redirect_follow(array $row, int $limit = 8): array
{
    $seen = [$row['source'] => true];
    $current = $row;
    while ($limit-- > 0) {
        if (redirect_destination_is_external($current['destination'])) {
            break;
        }
        $next = redirect_match($current['destination']);
        if ($next === null || isset($seen[$next['source']])) {
            break;
        }
        $seen[$next['source']] = true;
        $current = $next;
    }
    return $current;
}

/**
 * Record one hit. Deliberately a separate, best-effort write: a failure to
 * count must never prevent the redirect itself from being sent.
 */
function redirect_record_hit(string $id): void
{
    redirects_update(static function (array $rules) use ($id): array {
        if (!isset($rules[$id])) {
            return $rules;
        }
        $now = time();
        $rules[$id]['hits'] = (int) ($rules[$id]['hits'] ?? 0) + 1;
        if (empty($rules[$id]['first_hit'])) {
            $rules[$id]['first_hit'] = $now;
        }
        $rules[$id]['last_hit'] = $now;
        return $rules;
    });
}

/**
 * The path the visitor actually asked for, in route form.
 *
 * Derived from REQUEST_URI rather than from the $_GET parameters Folio's own
 * URL mapping produced, because by the time a 404 is being rendered those
 * have been rewritten — a request for /uploads/old.pdf arrives at the raw
 * handler as file=old.pdf, having lost the prefix a rule would be written
 * against. The rewrite environment variable is preferred when present, for
 * the same reason the URL mapper prefers it, and the SCRIPT_NAME directory is
 * stripped so Folio installed in a subfolder compares like-for-like.
 */
function redirect_current_path(): string
{
    $route = (string) ($_SERVER['SFM_ROUTE'] ?? $_SERVER['REDIRECT_SFM_ROUTE'] ?? '');
    if ($route === '') {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $dir  = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        if ($dir !== '' && strpos($path, $dir) === 0) {
            $path = substr($path, strlen($dir));
        }
        $route = $path;
    }
    $route = trim($route, '/');
    if ($route === '' || $route === 'index.php') {
        // A query-string install has no route path of its own; fall back to
        // whichever parameter names the thing that was not found, so rules
        // still match with PRETTY_URLS off.
        foreach (['view', 'page', 'file'] as $key) {
            $candidate = (string) ($_GET[$key] ?? '');
            if ($candidate !== '') {
                return redirect_normalise_source($candidate);
            }
        }
        return '';
    }
    return redirect_normalise_source($route);
}

/**
 * The one entry point index.php calls. Given the path a request was for,
 * either send a redirect and exit, or return so the caller can continue to
 * its own 404. Never throws, never emits anything on the no-match path.
 */
function redirect_dispatch(string $requested_path): void
{
    $normalised = redirect_normalise_source($requested_path);
    if ($normalised === '') {
        return;
    }
    $row = redirect_match($normalised);
    if ($row === null) {
        return;
    }
    $target = redirect_follow($row);
    $url = redirect_destination_url($target['destination']);

    if (($row['query'] ?? 'preserve') === 'preserve') {
        $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
        // Strip the routing parameters Folio's own rewrite added; they
        // describe how the request reached PHP, not what the visitor asked
        // for, and forwarding them would leak internals into the new URL.
        if ($qs !== '') {
            parse_str($qs, $parts);
            unset($parts['action'], $parts['view'], $parts['file'], $parts['serve'], $parts['page'], $parts['cat']);
            $qs = http_build_query($parts);
        }
        if ($qs !== '') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . $qs;
        }
    }

    redirect_record_hit($row['id']);

    // A Location value is checked one final time at the point of use: no
    // header can be split even if a malformed value somehow reached storage.
    if (preg_match('/[\r\n]/', $url)) {
        return;
    }
    header('Location: ' . $url, true, (int) $row['code']);
    exit;
}

/* ------------------------------------------------------------------ */
/* 404 Monitor                                                         */
/* ------------------------------------------------------------------ */

function notfound_load(): array
{
    $path = notfound_file();
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function notfound_update(callable $mutator): bool
{
    $dir = dirname(notfound_file());
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return false;
    }
    if (is_link(notfound_lock_file())) {
        return false;
    }
    $lock = @fopen(notfound_lock_file(), 'c+b');
    if ($lock === false || !@flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            @fclose($lock);
        }
        return false;
    }
    $current = notfound_load();
    $next = $mutator($current);
    $ok = false;
    if (is_array($next)) {
        $json = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ok = is_string($json) && atomic_replace_file(notfound_file(), $json . "\n", 0600, false);
    }
    @flock($lock, LOCK_UN);
    @fclose($lock);
    return $ok;
}

/**
 * Note one unresolved URL. Counts only — no IP address, no full user agent,
 * nothing that identifies a visitor. Best effort throughout: the 404 page
 * renders whether or not this succeeds.
 */
function notfound_record(string $requested_path): void
{
    $normalised = redirect_normalise_source($requested_path);
    if ($normalised === '') {
        return;
    }
    // Never record a path that an active rule already answers: the monitor
    // exists to show what still needs attention.
    if (redirect_match($normalised) !== null) {
        return;
    }
    notfound_update(static function (array $log) use ($normalised): array {
        $now = time();
        if (isset($log[$normalised]) && is_array($log[$normalised])) {
            $log[$normalised]['hits'] = (int) ($log[$normalised]['hits'] ?? 0) + 1;
            $log[$normalised]['last'] = $now;
        } else {
            $log[$normalised] = ['hits' => 1, 'first' => $now, 'last' => $now];
        }
        if (count($log) > FOLIO_NOTFOUND_MAX) {
            // Drop the least recently seen half. Keeping the most recent is
            // the useful half: an old one-off 404 nobody has hit in months is
            // exactly what an administrator would skip anyway.
            uasort($log, static fn($a, $b) => ($b['last'] ?? 0) <=> ($a['last'] ?? 0));
            $log = array_slice($log, 0, (int) (FOLIO_NOTFOUND_MAX / 2), true);
        }
        return $log;
    });
}

/* ------------------------------------------------------------------ */
/* Validation used by the admin screen                                 */
/* ------------------------------------------------------------------ */

/**
 * Check a proposed rule against every rule that must not be broken, and
 * return a list of human-readable problems. An empty list means safe to save.
 *
 * $ignore_id lets an existing rule be edited without colliding with itself.
 */
function redirect_problems(string $source, string $dest, ?string $ignore_id = null): array
{
    $problems = [];
    if ($source === '') {
        $problems[] = 'The source path is not valid.';
        return $problems;
    }
    if ($dest === '') {
        $problems[] = 'The destination is not valid.';
        return $problems;
    }

    // Self-loop.
    if (!redirect_destination_is_external($dest) && $dest === $source) {
        $problems[] = 'A redirect cannot point at itself.';
        return $problems;
    }

    // Duplicate source: one active rule per source, always.
    foreach (redirects_all() as $row) {
        if ($row['id'] === $ignore_id) {
            continue;
        }
        if ($row['source'] === $source && !empty($row['active'])) {
            $problems[] = 'An active redirect for that source already exists, pointing at '
                . $row['destination'] . '. Edit or remove it rather than adding a second.';
            break;
        }
    }

    // Cycle: walk forward from the destination and see whether we come back.
    if (!redirect_destination_is_external($dest)) {
        $seen = [$source => true];
        $cursor = $dest;
        for ($i = 0; $i < 16; $i++) {
            if (isset($seen[$cursor])) {
                $problems[] = 'That would create a redirect loop.';
                break;
            }
            $seen[$cursor] = true;
            $next = redirect_match($cursor);
            if ($next === null || $next['id'] === $ignore_id) {
                break;
            }
            if (redirect_destination_is_external($next['destination'])) {
                break;
            }
            $cursor = $next['destination'];
        }
    }

    return $problems;
}

/**
 * Non-fatal observations about a rule: things worth telling the administrator
 * without refusing the save. Kept separate from redirect_problems() so the
 * difference between "cannot" and "should look at" stays explicit.
 */
function redirect_warnings(string $source, string $dest, ?string $ignore_id = null): array
{
    $warnings = [];

    // Chain: the destination is itself redirected somewhere else.
    if (!redirect_destination_is_external($dest)) {
        $next = redirect_match($dest);
        if ($next !== null && $next['id'] !== $ignore_id) {
            $warnings[] = 'Redirect chain: the destination is itself redirected to '
                . $next['destination'] . '. Visitors are sent straight there in one hop, '
                . 'but pointing this rule at the final address directly is clearer.';
        }
    }

    if (redirect_destination_is_external($dest)) {
        $warnings[] = 'This destination is on another site.';
    }

    return $warnings;
}
