Folio's private working folder. Nothing here is ever served to the web.

It holds the settings, accounts, document catalogue and standalone pages, plus
generated caches for thumbnails, extracted text, OCR results and blurred
previews. The caches are disposable: delete them and they rebuild.

The .htaccess file beside this one denies web access to the whole folder. Keep
it, and make sure the folder is writable by the web server.

This readme is only here so the folder survives being committed to git and
uploaded to GitHub, which ignore empty directories and hidden files. You can
delete it on your own server.
