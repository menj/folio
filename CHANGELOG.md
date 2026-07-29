# Changelog

All notable changes to Folio are recorded here. Versions follow semantic
versioning: major for breaking changes, minor for features, patch for fixes.

## 1.0.0 — 29 July 2026

First release.

### Library

- Directory listing with subfolder navigation and breadcrumbs.
- Inline preview and printing for PDF, image, and Markdown files.
- Plain text and all other formats listed with a download link.
- Files managed entirely over FTP; the application never writes to them.

### Cataloguing

- Editable title and short description for every file, stored in
  `uploads/.sfm-meta.json`.
- Raw filenames and extensions hidden from visitors.
- One category and up to ten tags per file.
- Category archive pages at `/category/<slug>/`, gathering documents from every
  folder, with their own indexable URLs.
- Tag chips filter the current view in the browser.

### Addressing

- Page URLs are the filename minus its extension, slugified.
- Files are served from their real location in `uploads/`, directly by the web
  server rather than proxied through PHP.
- Requests carrying the old extension or the former `?action=raw` endpoint are
  301-redirected.
- Optional clean URLs through mod_rewrite; query-string URLs by default.

### Discovery

- Canonical URLs, Open Graph, and Twitter Card tags on every page.
- schema.org JSON-LD graph: `WebSite`, publisher, `BreadcrumbList`,
  `CollectionPage`, `ItemList`, and a typed node for each file.
- XML sitemap covering folders, file pages, and category archives, with Google
  image extensions.
- `robots.txt` opening the library to search engines and AI crawlers.

### Interface

- Codex-inspired layout: listing and preview as two leaves divided by a gutter.
- Garamond stack for text, sans for the apparatus, hairline rules throughout.
- Four colour schemes: Folio, Ledger, Garden, Night.
- Site icon in SVG, ICO, and Apple touch formats.

### Security

- Admin login with a bcrypt password hash and constant-time username check.
- CSRF tokens on every POST, hardened session cookies, and login throttling
  after eight failed attempts.
- Path traversal guards on every endpoint.
- Content-Security-Policy, `X-Frame-Options`, `Referrer-Policy`, and `nosniff`
  on every page.
- SVG files sandboxed so embedded scripts cannot run; script execution disabled
  inside `uploads/`.
- Markdown rendered by Parsedown in safe mode.

### Operations

- Self-test at `index.php?action=selftest` reporting URL mode, mod_rewrite
  status, `.htaccess` presence, and whether `uploads/` is writable.
- Documentation viewer for the admin at `index.php?action=docs`.
