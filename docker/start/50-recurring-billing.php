<?php
// One bounded billing pass; Compose invokes this every minute.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
if (getenv('RR_RECURRING_BILLING') !== '1') { exit(0); }
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/verleih/lib/recurring_billing.php';
try {
    if (!isModEnabled('facture')) { throw new RuntimeException('Invoice module is disabled.'); }
    $user = new User($db);
    if ($user->fetch('', getenv('DOLI_ADMIN_LOGIN') ?: 'admin') <= 0 || empty($user->admin)) {
        throw new RuntimeException('Configured administrator not found.');
    }
    $user->getrights();
    $count = rrRunRecurringBilling($db, $conf->entity);
    if ($count) { print '[RR-BILLING] Processed '.$count." due draft template(s).\n"; }
} catch (Throwable $error) {
    fwrite(STDERR, '[RR-BILLING] '.$error->getMessage()."\n");
    exit(1);
} finally {
    $db->close();
}
