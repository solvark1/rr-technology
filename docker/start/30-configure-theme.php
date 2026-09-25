<?php
// Apply the native Dolibarr theme preference after database initialization.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$mode = getenv('RR_THEME_DARK_MODE');
if ($mode === false || $mode === '') {
    exit(0);
}
if (!in_array($mode, array('0', '1', '2'), true)) {
    fwrite(STDERR, "[RR-THEME] RR_THEME_DARK_MODE must be 0, 1 or 2.\n");
    exit(1);
}
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
require_once '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
if (getDolGlobalString('THEME_DARKMODEENABLED') === $mode) {
    print "[RR-THEME] Theme preference already configured.\n";
    exit(0);
}
$result = dolibarr_set_const($db, 'THEME_DARKMODEENABLED', $mode, 'chaine', 0, '', $conf->entity);
if ($result <= 0) {
    fwrite(STDERR, "[RR-THEME] Failed to save theme preference.\n");
    exit(1);
}
print "[RR-THEME] Native dark mode configured: ".$mode."\n";
