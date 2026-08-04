Put your own site icon in this folder.

    favicon.svg            preferred, scales to any size
    favicon.png            or this
    favicon.ico            for older browsers and some bookmark bars
    apple-touch-icon.png   180x180, for iOS home screens

Folio picks these up automatically. There is nothing to configure, and they
are served both through the <link> tags and at the root paths browsers ask
for on their own, such as /favicon.ico.

Use this folder rather than replacing the files inside assets/. That folder
belongs to the release and is overwritten every time you upgrade, so an icon
put there would silently revert. Nothing in here is ever touched by an
upgrade.

If your icon does not appear straight away, it is almost certainly the
browser's cache: icons are cached hard. Try a private window, or add
?v=2 to the URL once to confirm the file is being served.
