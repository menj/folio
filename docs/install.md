# Installing Folio

## Requirements

- PHP 8.4 or newer
- JSON, password, random and `mbstring` support
- Read access for PHP to `uploads/` and write access to `data/`
- Apache/LiteSpeed with the supplied `.htaccess`, `mod_mime`, and
  `mod_headers`; `mod_rewrite` is required only for clean URLs

## Upload the package

Unpack the release and upload the contents of the `folio/` folder into the
public folder that will contain the library. Do not add another nested
`folio/` directory unless that is the intended URL.

On Apache or LiteSpeed the shipped `.htaccess` is already active, with clean
URLs enabled. Keep `uploads/.htaccess` and `data/.htaccess`.

Make `uploads/` readable by PHP and `data/` writable by the web-server account.
Typical directory modes are 0755 or 0775 depending on ownership; do not use
0777 unless the host leaves no safer option.

## Run the locked installer

Open `install.php` in a browser. On first access it creates a private one-time
secret at `data/install-token.php`. Read that file over FTP or a hosting file
manager and copy the returned token. The web server must deny direct access to
`data/`.

Enter the token in the installer together with:

- the exact canonical URL of the Folio folder, including `https://` and its
  trailing slash;
- the first administrator username and password;
- the site and publisher identity;
- the content language.

The canonical URL is stored as `SITE_URL`. Folio deliberately does not trust the
incoming `Host` header when generating canonical, sitemap, Open Graph, or
structured-data URLs.

The installer creates `config.php` exclusively with restrictive permissions,
removes the token file, and refuses to run again while configuration exists.
Delete `install.php` after a successful installation.

## Verify

Open the library and sign in through `?action=login`. Run the admin-only
`?action=diagnostics` page. Public pages should not set a PHP session cookie;
login and authenticated routes should.

Upload a test document and verify its listing, detail page, preview, direct
link, and metadata save. Unsupported formats should download rather than render
as active same-origin content.

## Clean URLs

Query-string URLs work on every supported server. To use clean URLs, enable the
commented rewrite block in `.htaccess`, set `PRETTY_URLS` to `true` in
`config.php`, then run diagnostics again.

## Crawlers

Customise the supplied `robots.txt`, especially its absolute sitemap URL, and
publish it at the domain root. The Crawlers screen controls site indexability,
the XML sitemap, and llms.txt. Sitemap and llms.txt return 404 while the site is
non-indexable.
