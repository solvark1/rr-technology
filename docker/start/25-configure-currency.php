<?php
// Configure the base currency before any demo data is seeded.
if (PHP_SAPI !== 'cli') { exit(1); }
$currency = getenv('RR_CURRENCY');
if (!$currency) { exit(0); }
if ($currency !== 'CRC') { fwrite(STDERR, "[RR-CURRENCY] Expected CRC.\n"); exit(1); }
define('NOLOGIN', 1); define('NOREQUIREMENU', 1); define('NOREQUIREHTML', 1); define('NOREQUIREAJAX', 1);
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
if (getDolGlobalString('MAIN_MONNAIE') !== $currency && dolibarr_set_const($db, 'MAIN_MONNAIE', $currency, 'chaine', 0, '', $conf->entity) <= 0) {
    fwrite(STDERR, "[RR-CURRENCY] Could not configure currency.\n"); exit(1);
}
print "[RR-CURRENCY] Base currency: CRC. Existing amounts are not converted.\n";
$db->close();
