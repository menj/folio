# Upgrading and removing Folio

## Before you start

Two things belong to you rather than to the software, and no upgrade should
ever touch them:

- `uploads/` — your documents, and `uploads/.sfm-meta.json`, which holds every
  title, description, category, and tag you have written.
- `config.php` — your username, password hash, and site settings.

Back up both before any upgrade. The metadata file is small and plain JSON;
losing it means retyping every title in the library.

## Upgrading

1. **Back up.** Download `uploads/` and `config.php` to your own machine.

2. **Replace the code.** Upload the new release over the installation,
   overwriting these:

   ```
   index.php
   assets/
   lib/
   CHANGELOG.md
   README.md
   readme.txt
   UPGRADING.md
   config-sample.php
   hash-tool.php (delete after use)
   ```

   Do **not** upload `config.php`, `uploads/`, or `.htaccess` from the package.
   Yours are already in place and correct.

3. **Delete files the new release no longer uses.** Compare the file list in
   the README against your folder and remove anything left behind. Old files
   are harmless but accumulate, and stale copies of `assets/` can shadow the
   new ones through browser caching.

4. **Compare your settings.** Open `config-sample.php` and check for settings
   the release has added. Anything you leave out falls back to a safe default,
   so this step is optional, though new features often arrive switched off.

5. **Run the self-test** at `index.php?action=selftest`. It confirms the base
   URL, URL mode, `.htaccess` status, and that `uploads/` is still writable.

6. **Hard-refresh the browser** with Ctrl+F5 so the cached stylesheet and
   scripts are replaced.

7. **Log in and check** one document page, one category page, and one edit.

If something breaks, restoring the previous `index.php` and `assets/` folder
puts you back where you were. Your documents and metadata are untouched.

## Moving to another folder or server

1. Copy the entire installation, including `uploads/` and `config.php`.
2. If the folder name changed, nothing needs editing: Folio derives its URLs
   from where `index.php` sits.
3. If you use clean URLs, confirm the `.htaccess` came across; dot-files are
   often skipped by FTP clients. The same applies to `uploads/.htaccess`.
4. Run the self-test on the new location.
5. Submit the new `sitemap.xml` in Search Console, and redirect the old URLs
   if the address changed.

## Removing Folio

1. **Keep your documents.** Download `uploads/` first. Those files are yours
   and exist nowhere else.

2. **Keep your metadata if you may return.** `uploads/.sfm-meta.json` maps
   every filename to its title, description, category, and tags. Reinstalling
   with that file in place restores the whole catalogue.

3. **Delete the installation:**

   ```
   index.php
   config.php
   config-sample.php
   hash-tool.php (delete after use)
   assets/
   lib/
   uploads/
   .htaccess
   .gitignore
   htaccess.txt
   uploads-htaccess.txt
   robots.txt
   README.md
   readme.txt
   CHANGELOG.md
   UPGRADING.md
   ```

4. **Tidy up outside the folder.** The `robots.txt` at your domain root has a
   `Sitemap:` line pointing at Folio. Remove it, or search engines will keep
   requesting a sitemap that no longer exists.

5. **Retire the URLs properly.** If the library was indexed, serve 410 Gone or
   redirect to a replacement page. Leaving soft 404s costs you nothing
   immediately but leaves dead results in the index for months.

Folio writes nothing outside its own folder: no database, no session files
beyond PHP's own, and no configuration elsewhere on the server. Deleting the
folder removes it completely.
