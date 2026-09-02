<?php
declare(strict_types=1);

/**
 * A minimal SMTP client, written by hand rather than pulled in as a
 * dependency — consistent with the rest of Folio, which has none.
 *
 * This exists because PHP's built-in mail() only ever reports whether the
 * message was handed to the local mail transport, never whether it actually
 * left the server or was accepted by the destination. On most modern hosts
 * — cloud VPS providers block outbound port 25 by default — that handoff
 * succeeds locally and the message then silently dies, with mail() none the
 * wiser and nothing bouncing back to tell you. Authenticated SMTP on port
 * 587 sidesteps that class of problem entirely, and unlike mail() it gives
 * back a real reason when something goes wrong.
 *
 * Deliberately does not implement: connection pooling, multiple recipients,
 * DSN requests, or anything else a contact-form mailer does not need. One
 * message, one recipient, one attempt, a clear error either way.
 */

/**
 * Send one message over SMTP. Returns true on acceptance by the destination
 * server; on failure, $error carries the server's own explanation where one
 * was given, which is the entire reason this exists alongside mail().
 *
 * $headers must NOT include a body separator — pass the finished header
 * block (as contact_send() already builds it) and the body separately, so
 * this function controls the CRLF + dot-stuffing itself rather than trusting
 * a caller to have gotten DATA framing right.
 */
function smtp_send(string $to, string $mail_headers, string $body, string &$error = ''): bool
{
    $host = trim((string) SMTP_HOST);
    $port = (int) SMTP_PORT;
    $enc  = strtolower(trim((string) SMTP_ENCRYPTION));
    $user = trim((string) SMTP_USERNAME);
    $pass = (string) SMTP_PASSWORD;

    if ($host === '' || $port <= 0) {
        $error = 'SMTP host or port is not configured.';
        return false;
    }

    $transport = $enc === 'ssl' ? 'ssl://' . $host : $host;
    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'allow_self_signed' => false,
    ]]);

    $fp = @stream_socket_client(
        $transport . ':' . $port,
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT,
        $ctx
    );
    if (!$fp) {
        $error = 'Could not connect to ' . $host . ':' . $port . ($errstr !== '' ? ' — ' . $errstr : '');
        return false;
    }
    stream_set_timeout($fp, 15);

    $fail = static function (string $why) use ($fp, &$error): bool {
        $error = $why;
        @fwrite($fp, "QUIT\r\n");
        @fclose($fp);
        return false;
    };

    // Every SMTP exchange is "send a command, read the (possibly
    // multi-line) reply, check its status code" — pulled into one closure
    // rather than repeated seven times below.
    $read_reply = static function () use ($fp): array {
        $lines = [];
        do {
            $line = @fgets($fp, 2048);
            if ($line === false) {
                return [0, ['Connection closed unexpectedly.']];
            }
            $lines[] = rtrim($line, "\r\n");
            // A hyphen after the code means more lines follow; a space
            // means this is the last line of the reply.
            $continues = isset($line[3]) && $line[3] === '-';
        } while ($continues);
        $code = (int) substr($lines[0] ?? '', 0, 3);
        return [$code, $lines];
    };
    $command = static function (string $cmd) use ($fp, $read_reply): array {
        @fwrite($fp, $cmd . "\r\n");
        return $read_reply();
    };

    [$code, $lines] = $read_reply(); // server greeting
    if ($code !== 220) {
        return $fail('Server did not offer a greeting: ' . implode(' ', $lines));
    }

    $client_name = (string) parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost';
    [$code, $lines] = $command('EHLO ' . $client_name);
    if ($code !== 250) {
        return $fail('EHLO was refused: ' . implode(' ', $lines));
    }
    $ehlo_reply = implode("\n", $lines);

    if ($enc === 'tls') {
        if (stripos($ehlo_reply, 'STARTTLS') === false) {
            return $fail('The server does not offer STARTTLS on this port.');
        }
        [$code, $lines] = $command('STARTTLS');
        if ($code !== 220) {
            return $fail('STARTTLS was refused: ' . implode(' ', $lines));
        }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return $fail('The TLS handshake failed.');
        }
        // Most servers require EHLO to be re-sent after STARTTLS, since the
        // pre-TLS capability list is unauthenticated and untrusted.
        [$code, $lines] = $command('EHLO ' . $client_name);
        if ($code !== 250) {
            return $fail('EHLO after STARTTLS was refused: ' . implode(' ', $lines));
        }
    }

    if ($user !== '') {
        [$code, $lines] = $command('AUTH LOGIN');
        if ($code !== 334) {
            return $fail('AUTH LOGIN was refused: ' . implode(' ', $lines));
        }
        [$code, $lines] = $command(base64_encode($user));
        if ($code !== 334) {
            return $fail('The username was rejected: ' . implode(' ', $lines));
        }
        [$code, $lines] = $command(base64_encode($pass));
        if ($code !== 235) {
            return $fail('Authentication failed — check the SMTP username and password: ' . implode(' ', $lines));
        }
    }

    $sender = smtp_envelope_sender();
    [$code, $lines] = $command('MAIL FROM:<' . $sender . '>');
    if ($code !== 250) {
        return $fail('MAIL FROM was refused: ' . implode(' ', $lines));
    }
    [$code, $lines] = $command('RCPT TO:<' . $to . '>');
    if ($code !== 250 && $code !== 251) {
        return $fail('The server refused the recipient address: ' . implode(' ', $lines));
    }
    [$code, $lines] = $command('DATA');
    if ($code !== 354) {
        return $fail('DATA was refused: ' . implode(' ', $lines));
    }

    // Lines beginning with a lone "." must be escaped by doubling it, or
    // the SMTP server reads that line as the end-of-message marker and
    // truncates the message right there — a message containing a line
    // that happens to be just "." would otherwise be silently cut short.
    $payload = $mail_headers . "\r\n\r\n" . $body;
    $payload = preg_replace('/^\./m', '..', $payload) ?? $payload;
    @fwrite($fp, $payload . "\r\n.\r\n");
    [$code, $lines] = $read_reply();
    if ($code !== 250) {
        return $fail('The message was rejected after sending: ' . implode(' ', $lines));
    }

    @fwrite($fp, "QUIT\r\n");
    @fclose($fp);
    return true;
}

/**
 * The envelope sender (SMTP "MAIL FROM"), distinct from the visible From:
 * header. Kept in its own function because smtp_send() needs it before any
 * header block exists, and contact.php's own contact_sender() already
 * encodes the same "prefer configured, else no-reply@this-domain" logic for
 * the visible header — this mirrors it rather than duplicating the fallback
 * chain a second time with room to drift.
 */
function smtp_envelope_sender(): string
{
    return function_exists('contact_sender') ? contact_sender() : '';
}

/** Whether enough SMTP settings are present to attempt a connection at all. */
function smtp_configured(): bool
{
    return trim((string) SMTP_HOST) !== '' && (int) SMTP_PORT > 0;
}
