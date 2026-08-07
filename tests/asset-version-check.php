<?php
/* Every release-owned asset must be linked with a version query, because the
   server tells browsers to cache these files for a year without revalidating.
   An unversioned link means an upgraded site is styled by the old stylesheet. */
$html = file_get_contents($argv[1]);
$bad = [];
if (preg_match('#(href|src)="[^"]*assets/(css|js)/[^"?]+\.(css|js)"#', $html, $m)) {
    $bad[] = $m[0];
}
foreach (['assets/css/style.css'] as $must) {
    if (strpos($html, $must) !== false && !preg_match('#' . preg_quote($must, '#') . '\?v=#', $html)) {
        $bad[] = $must . ' has no ?v=';
    }
}
if ($bad) {
    fwrite(STDERR, implode('; ', $bad) . "\n");
    exit(1);
}
