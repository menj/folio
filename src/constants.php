<?php
declare(strict_types=1);

/**
 * Centralised configuration constants for Folio.
 * Adjust values here to control security limits and feature toggles.
 */
// Throttling limits
const LOGIN_MAX_ATTEMPTS = 8;                // IP‑only attempts before lockout
const PER_USER_MAX_ATTEMPTS = 10;            // per‑username attempts before lockout
const THROTTLE_WINDOW_SECONDS = 900;        // 15 minutes

// Password hashing cost (handled automatically by PASSWORD_DEFAULT)
// No explicit constant needed; password_needs_rehash will use defaults.

// Force HTTPS for the entire site (true = redirect HTTP to HTTPS and set Secure flag on cookies)
const FORCE_HTTPS = false;

// CSP reporting endpoint (empty string disables reporting)
const CSP_REPORT_URI = '';

// Session cookie path will be computed dynamically; no constant needed.
?>
