<?php
/**
 * Folio — public contact form.
 *
 * A visitor writes a message; Folio emails it to the address already
 * configured as PUBLISHER_EMAIL. The visitor never learns that address: it is
 * read from server-side configuration at the moment the mail is built and
 * never rendered, never sent to the browser, and never accepted as input.
 *
 * Deliberately not a mailbox. Nothing a visitor submits is stored: the
 * message goes out as email and is gone. Attachments are read from PHP's own
 * temporary upload file, attached, and unlinked on every exit path, so a
 * contact attachment never becomes a library document — the distinction
 * Folio's whole architecture rests on, where FTP owns the files.
 *
 * Lives in /lib for the same reason lib/video.php and lib/redirects.php do.
 * Nothing here executes at include time.
 */

/* ------------------------------------------------------------------ */
/* Configuration                                                       */
/* ------------------------------------------------------------------ */

/**
 * Where a submission is sent. Deliberately PUBLISHER_EMAIL rather than a
 * second setting: it is already the publisher's address for vcard.vcf,
 * identity.json, and llms.txt's Contact section, and a site with two
 * different "the owner's email" settings is a site where one of them is
 * quietly wrong. If a library ever genuinely needs to separate them, that is
 * the point to add a setting — not before.
 */
function contact_recipient(): string
{
    $email = trim((string) PUBLISHER_EMAIL);
    return ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : '';
}

/**
 * The From: address. Never the visitor's own address — sending as them would
 * fail SPF and DMARC on any correctly configured domain and get the message
 * spam-foldered or rejected outright. The site sends as itself, and the
 * visitor's address goes in Reply-To, so hitting reply still works.
 */
function contact_sender(): string
{
    $configured = trim((string) CONTACT_SENDER_EMAIL);
    if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
        return $configured;
    }
    // Fall back to something at the site's own domain, which is far more
    // likely to pass sender checks than the visitor's address would.
    $host = (string) parse_url(BASE_URL, PHP_URL_HOST);
    $host = preg_replace('/^www\./i', '', (string) $host);
    return ($host !== '' && $host !== null) ? 'no-reply@' . $host : contact_recipient();
}

/** Whether the form can actually deliver. Both halves must be true. */
function contact_ready(): bool
{
    if (contact_recipient() === '') {
        return false;
    }
    return smtp_configured() || function_exists('mail');
}

/** Effective attachment ceiling, never above what the server itself allows —
 *  promising 10MB on a host capped at 2MB would just fail confusingly. */
function contact_effective_max_bytes(): int
{
    $configured = (int) CONTACT_MAX_TOTAL_MB * 1024 * 1024;
    $limits = [];
    foreach (['upload_max_filesize', 'post_max_size'] as $key) {
        $raw = trim((string) ini_get($key));
        if ($raw === '') {
            continue;
        }
        $unit = strtolower(substr($raw, -1));
        $n = (int) $raw;
        if ($unit === 'g') { $n *= 1024 * 1024 * 1024; }
        elseif ($unit === 'm') { $n *= 1024 * 1024; }
        elseif ($unit === 'k') { $n *= 1024; }
        if ($n > 0) {
            $limits[] = $n;
        }
    }
    return $limits ? min($configured, min($limits)) : $configured;
}

/**
 * Attachment types accepted, as extension => expected detected MIME.
 *
 * An allowlist, never a blocklist: a blocklist is a promise to have thought
 * of every dangerous extension, and .phtml, .phar, .cgi, .htaccess and their
 * relatives make that a promise nobody can keep. Anything not named here is
 * refused, so a type nobody anticipated is refused by default rather than
 * accepted by default.
 */
function contact_allowed_types(): array
{
    return [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'txt'  => ['text/plain'],
        'md'   => ['text/plain', 'text/markdown'],
        'csv'  => ['text/plain', 'text/csv'],
        'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'zip'  => ['application/zip'],
    ];
}

/* ------------------------------------------------------------------ */
/* Rate limiting                                                       */
/* ------------------------------------------------------------------ */

function contact_rate_file(): string
{
    return __DIR__ . '/../data/contact-rate.json';
}

/**
 * A privacy-preserving identifier for rate limiting.
 *
 * Never the raw IP address. The address is truncated to its network — /24 for
 * IPv4, /48 for IPv6 — then salted with a per-install secret and hashed, so
 * the stored value cannot be reversed into an address and cannot be compared
 * against a list of addresses from anywhere else. Truncating first means a
 * whole household or office shares one bucket, which is the correct trade for
 * a form that allows several messages an hour anyway.
 */
function contact_rate_key(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '') {
        return 'unknown';
    }
    if (filter_var($ip, FILTER_FLAG_IPV4 | FILTER_VALIDATE_IP)) {
        $parts = explode('.', $ip);
        array_pop($parts);
        $ip = implode('.', $parts) . '.0';
    } elseif (filter_var($ip, FILTER_VALIDATE_IP)) {
        $bin = @inet_pton($ip);
        $ip = $bin === false ? $ip : bin2hex(substr($bin, 0, 6));
    }
    // Reuse the obscure key: already auto-provisioned, already private, and
    // already outside the web root. No new secret to manage or leak.
    return substr(hash_hmac('sha256', $ip, video_obscure_key()), 0, 32);
}

/** Whether this submitter is over the limit. Prunes expired entries as it
 *  goes, so the file cannot grow without bound. */
function contact_rate_exceeded(): bool
{
    $key = contact_rate_key();
    $now = time();
    $window = 3600;
    $log = [];
    if (is_file(contact_rate_file())) {
        $raw = @file_get_contents(contact_rate_file());
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $log = $decoded;
        }
    }
    $mine = array_values(array_filter(
        (array) ($log[$key] ?? []),
        static fn($t) => is_int($t) && ($now - $t) < $window
    ));
    return count($mine) >= (int) CONTACT_RATE_PER_HOUR;
}

/** Note one accepted submission against the limit. */
function contact_rate_record(): void
{
    $key = contact_rate_file();
    $dir = dirname($key);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return;
    }
    $lock = @fopen($key . '.lock', 'c+b');
    if ($lock === false || !@flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            @fclose($lock);
        }
        return;
    }
    $now = time();
    $window = 3600;
    $log = [];
    if (is_file(contact_rate_file())) {
        $raw = @file_get_contents(contact_rate_file());
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $log = $decoded;
        }
    }
    $id = contact_rate_key();
    $log[$id][] = $now;
    // Prune every bucket, not just this one, so an abandoned key from months
    // ago does not sit in the file forever.
    foreach ($log as $k => $times) {
        $kept = array_values(array_filter(
            (array) $times,
            static fn($t) => is_int($t) && ($now - $t) < $window
        ));
        if ($kept) {
            $log[$k] = $kept;
        } else {
            unset($log[$k]);
        }
    }
    $json = json_encode($log, JSON_UNESCAPED_SLASHES);
    if (is_string($json)) {
        atomic_replace_file(contact_rate_file(), $json . "\n", 0600, false);
    }
    @flock($lock, LOCK_UN);
    @fclose($lock);
}

/* ------------------------------------------------------------------ */
/* Validation                                                          */
/* ------------------------------------------------------------------ */

/**
 * Strip anything that could break out of a mail header into a new one.
 *
 * A newline in a subject is how header injection works: it ends the Subject
 * line and starts whatever the attacker wants, Bcc included. Every value that
 * reaches a header goes through here, and the result is trimmed to a length
 * no header should exceed.
 */
function contact_header_safe(string $value, int $max = 200): string
{
    $value = str_replace(["\r", "\n", "\0"], ' ', $value);
    $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
    return trim(mb_substr_safe((string) $value, $max));
}

/** mb_substr where available, plain substr otherwise — mbstring is optional
 *  in Folio and its absence must cost a capability, never the site. */
function mb_substr_safe(string $s, int $len): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $len);
    }
    return substr($s, 0, $len);
}

function contact_strlen(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

/**
 * Validate a submission. Returns a list of field => message for problems the
 * visitor should be told about and can fix.
 *
 * Anti-spam failures are deliberately NOT returned here — see
 * contact_spam_check(), which reports separately so the visitor-facing
 * response can stay generic and tell an attacker nothing about which layer
 * caught them.
 */
function contact_validate(array $in): array
{
    $errors = [];

    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '') {
        $errors['name'] = 'Please tell us your name.';
    } elseif (contact_strlen($name) > 100) {
        $errors['name'] = 'That name is too long.';
    }

    $email = trim((string) ($in['email'] ?? ''));
    if ($email === '') {
        $errors['email'] = 'Please give an email address so we can reply.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || contact_strlen($email) > 254) {
        $errors['email'] = 'That does not look like a valid email address.';
    } elseif (preg_match('/[\r\n]/', $email)) {
        $errors['email'] = 'That does not look like a valid email address.';
    }

    $subject = trim((string) ($in['subject'] ?? ''));
    if ($subject === '') {
        $errors['subject'] = 'Please give your message a subject.';
    } elseif (contact_strlen($subject) > 150) {
        $errors['subject'] = 'That subject is too long.';
    }

    $message = trim((string) ($in['message'] ?? ''));
    $len = contact_strlen($message);
    if ($message === '') {
        $errors['message'] = 'Please write a message.';
    } elseif ($len < 10) {
        $errors['message'] = 'That message is very short. Please add a little more.';
    } elseif ($len > 5000) {
        $errors['message'] = 'That message is too long. Please keep it under 5,000 characters.';
    }

    return $errors;
}

/**
 * Anti-spam layers. Returns true when the submission looks automated.
 *
 * Which layer objected is never reported to the visitor and never logged in
 * a way that reaches them: telling a bot "honeypot detected" tells whoever
 * wrote it exactly what to change.
 */
function contact_spam_check(array $in): bool
{
    if (!CONTACT_ANTISPAM) {
        return false;
    }

    // Honeypot: a field hidden from people, irresistible to naive bots.
    if (trim((string) ($in['website'] ?? '')) !== '') {
        return true;
    }

    // Timing: a person cannot read a form and write a message in two seconds.
    $started = (int) ($_SESSION['contact_started'] ?? 0);
    if ($started > 0 && (time() - $started) < (int) CONTACT_MIN_SECONDS) {
        return true;
    }

    // Content shape. Deliberately crude and forgiving: a real enquiry often
    // contains a link, so one or two are fine and only a wall of them counts.
    $message = (string) ($in['message'] ?? '');
    if (preg_match_all('#https?://#i', $message) > 8) {
        return true;
    }
    // A "message" that is only a URL and nothing else.
    if (preg_match('#^\s*https?://\S+\s*$#i', $message)) {
        return true;
    }

    return false;
}

/* ------------------------------------------------------------------ */
/* Attachments                                                         */
/* ------------------------------------------------------------------ */

/**
 * Validate uploaded files and return [accepted, errors].
 *
 * Each accepted entry is [tmp_path, safe_filename, mime]. The visitor's own
 * filename is never used as a path — only as a label inside the email — and
 * the file is read from PHP's temporary location, never moved anywhere Folio
 * serves.
 */
function contact_collect_attachments(?array $files, array &$errors): array
{
    $accepted = [];
    if (!CONTACT_ATTACHMENTS || !is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
        return $accepted;
    }

    $allowed = contact_allowed_types();
    $total = 0;
    $count = 0;
    $max_one = (int) CONTACT_MAX_FILE_MB * 1024 * 1024;
    $max_all = contact_effective_max_bytes();

    foreach (array_keys($files['name']) as $i) {
        $err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $original = (string) ($files['name'][$i] ?? '');
        $label = $original !== '' ? $original : 'attachment';

        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            $errors['attachments'] = 'One of those files is larger than this server accepts.';
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $errors['attachments'] = 'One of those files did not upload correctly. Please try again.';
            continue;
        }

        $count++;
        if ($count > (int) CONTACT_MAX_ATTACHMENTS) {
            $errors['attachments'] = 'Please attach no more than ' . (int) CONTACT_MAX_ATTACHMENTS . ' files.';
            break;
        }

        $tmp = (string) ($files['tmp_name'][$i] ?? '');
        // is_uploaded_file is the guard against a crafted request naming an
        // arbitrary server path as its "temporary" file.
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $errors['attachments'] = 'One of those files could not be read.';
            continue;
        }

        $size = (int) ($files['size'][$i] ?? 0);
        if ($size <= 0) {
            $errors['attachments'] = 'One of those files was empty.';
            continue;
        }
        if ($size > $max_one) {
            $errors['attachments'] = 'Each file must be under ' . (int) CONTACT_MAX_FILE_MB . ' MB.';
            continue;
        }
        $total += $size;
        if ($total > $max_all) {
            $errors['attachments'] = 'Those files come to more than the '
                . round($max_all / 1048576) . ' MB total this site accepts.';
            break;
        }

        // Extension is taken from the visitor's filename only to look up what
        // the content should be; it never becomes part of a path.
        $ext = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) {
            $errors['attachments'] = 'That file type is not accepted. Allowed: '
                . implode(', ', array_keys($allowed)) . '.';
            continue;
        }

        // The browser's declared type is ignored entirely: it is visitor
        // input like any other. The content is sniffed instead, and must
        // agree with what the extension claims — so a PHP script renamed
        // to .png is refused, which is the whole point of this check.
        $detected = '';
        if (function_exists('finfo_open')) {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $detected = (string) @finfo_file($fi, $tmp);
                @finfo_close($fi);
            }
        }
        if ($detected !== '' && !in_array($detected, $allowed[$ext], true)) {
            $errors['attachments'] = 'One of those files does not match its file type.';
            continue;
        }

        $accepted[] = [
            'tmp'  => $tmp,
            'name' => contact_safe_filename($original),
            'mime' => $detected !== '' ? $detected : $allowed[$ext][0],
        ];
    }

    return $accepted;
}

/**
 * A filename safe to put in a MIME header. Directory separators, control
 * characters, and quotes are removed rather than escaped, because this value
 * is a label in an email and nothing is gained by preserving them.
 */
function contact_safe_filename(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
    $name = trim((string) $name, '._-');
    if ($name === '') {
        $name = 'attachment';
    }
    return mb_substr_safe($name, 80);
}

/* ------------------------------------------------------------------ */
/* Delivery                                                            */
/* ------------------------------------------------------------------ */

/** RFC 2047 encoding, so a subject with an accent or a non-Latin script
 *  survives instead of arriving as mojibake. */
function contact_encode_header(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

/**
 * Build and send the message. Returns true only when the transport accepted
 * it — a false here must never be reported to the visitor as success.
 *
 * Every header value is passed through contact_header_safe() first, so no
 * visitor input can introduce a header of its own. The recipient is read from
 * configuration inside this function and is never a parameter, so there is no
 * code path by which a request could redirect the message elsewhere.
 */
function contact_send(array $in, array $attachments, string &$error = ''): bool
{
    $to = contact_recipient();
    if ($to === '') {
        return false;
    }

    $name    = contact_header_safe((string) $in['name'], 100);
    $email   = contact_header_safe((string) $in['email'], 254);
    $subject = contact_header_safe((string) $in['subject'], 150);
    $message = (string) $in['message'];

    $site = contact_header_safe((string) SITE_NAME, 100);
    $sender = contact_sender();

    $headers = [];
    $headers[] = 'From: ' . contact_encode_header($site) . ' <' . $sender . '>';
    // The visitor's address goes here and only here, so a reply reaches them
    // while the message itself is sent legitimately by this site.
    $headers[] = 'Reply-To: ' . contact_encode_header($name) . ' <' . $email . '>';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'X-Mailer: Folio';
    $headers[] = 'Auto-Submitted: auto-generated';

    $body = "New message from the contact form on " . SITE_NAME . "\n\n"
          . "Name:    " . $name . "\n"
          . "Email:   " . $email . "\n"
          . "Subject: " . $subject . "\n"
          . "Sent:    " . date('j F Y, H:i') . "\n";
    if ($attachments) {
        $body .= "Files:   " . count($attachments) . " attached\n";
    }
    $body .= "\n" . str_repeat('-', 50) . "\n\n"
          . $message . "\n\n"
          . str_repeat('-', 50) . "\n"
          . "Sent from " . rtrim(BASE_URL, '/') . "/contact\n"
          . "Reply to this email to answer " . $name . " directly.\n";

    $mail_subject = contact_encode_header('[' . $site . '] ' . $subject);

    if (!$attachments) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        return contact_dispatch($to, $mail_subject, $headers, $body, $error);
    }

    $boundary = 'folio-' . bin2hex(random_bytes(12));
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

    $payload  = "--" . $boundary . "\r\n";
    $payload .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $payload .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $payload .= $body . "\r\n";

    foreach ($attachments as $a) {
        $data = @file_get_contents($a['tmp']);
        if ($data === false) {
            continue;
        }
        $payload .= "--" . $boundary . "\r\n";
        $payload .= 'Content-Type: ' . $a['mime'] . '; name="' . $a['name'] . "\"\r\n";
        $payload .= "Content-Transfer-Encoding: base64\r\n";
        $payload .= 'Content-Disposition: attachment; filename="' . $a['name'] . "\"\r\n\r\n";
        $payload .= chunk_split(base64_encode($data)) . "\r\n";
    }
    $payload .= "--" . $boundary . "--\r\n";

    return contact_dispatch($to, $mail_subject, $headers, $payload, $error);
}

/**
 * The one place that actually calls a transport. SMTP is used whenever
 * SMTP_HOST is configured; PHP's mail() is the fallback for every install
 * that has not set it, unchanged from before this function existed. Neither
 * transport is called from more than this one spot, so there is exactly one
 * place that decides which is in use.
 */
function contact_dispatch(string $to, string $subject, array $headers, string $body, string &$error = ''): bool
{
    if (smtp_configured()) {
        $header_block = implode("\r\n", array_merge(
            ['Subject: ' . $subject],
            $headers
        ));
        $ok = smtp_send($to, $header_block, $body, $error);
        if (!$ok) {
            error_log('Folio contact form: SMTP send failed — ' . $error);
        }
        return $ok;
    }
    if (!function_exists('mail')) {
        $error = 'This server has no PHP mail function available.';
        return false;
    }
    $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
    if (!$ok) {
        $error = 'mail() returned false — the local mail transport refused the message.';
    }
    return $ok;
}

/** Remove every temporary upload, on success and on failure alike. PHP
 *  cleans these up itself at request end, but doing it explicitly means a
 *  visitor's file is gone the moment it is no longer needed rather than
 *  whenever the request happens to finish. */
function contact_cleanup(array $attachments): void
{
    foreach ($attachments as $a) {
        if (!empty($a['tmp']) && is_file($a['tmp'])) {
            @unlink($a['tmp']);
        }
    }
}
