<?php
/* Every utility Diagnostics advertises must actually be invoked somewhere.
   Detection without a call site is worse than not supporting the tool: the
   interface reports a capability the application does not have. */
$src = file_get_contents($argv[1]);
if (!preg_match('/\$tool_rows = \[(.*?)\];/s', $src, $m)) {
    fwrite(STDERR, "could not find the advertised tool list\n");
    exit(1);
}
preg_match_all("/'([a-z0-9_-]+)'\s*=>/", $m[1], $names);
$missing = [];
foreach ($names[1] as $tool) {
    $q = preg_quote($tool, '/');
    $used = preg_match("/tool_run\('{$q}'/", $src)          // called directly
         || preg_match("/=\s*'{$q}'/", $src)                // held in a variable
         || preg_match("/tool_have\('{$q}'\)\s*\?/", $src)  // switches an option
         || preg_match("/if \(tool_have\('{$q}'\)\)/", $src);
    if (!$used) { $missing[] = $tool; }
}
if ($missing) {
    fwrite(STDERR, implode(', ', $missing) . " advertised in Diagnostics but never used\n");
    exit(1);
}
