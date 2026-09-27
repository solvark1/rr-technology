<?php
// Idempotent module activation and migration for R&R Renting.
if (PHP_SAPI !== 'cli') { exit(1); }
define('NOLOGIN',1); define('NOREQUIREMENU',1); define('NOREQUIREHTML',1); define('NOREQUIREAJAX',1);
require_once '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/verleih/lib/renting_schema.php';
$user=new User($db);
if ($user->fetch('',getenv('DOLI_ADMIN_LOGIN') ?: 'admin')<=0 || empty($user->admin)) {
    fwrite(STDERR,"[RR-RENTING] Configured administrator not found.\n"); exit(1);
}
$user->getrights();
try {
    rrRentingMigrate($db);
    foreach(array('Societe','Product','Service','Stock','ProductBatch','Contrat') as $module) {
        $result=activateModule('mod'.$module);
        if (!empty($result['errors'])) { throw new RuntimeException(implode('; ',$result['errors'])); }
    }
    if (!isModEnabled('verleih') || getDolGlobalString('RR_RENTING_SCHEMA_VERSION')!=='2.5.0') {
        foreach (array('STOCK_CALCULATE_ON_SHIPMENT'=>1,'STOCK_CALCULATE_ON_VALIDATE_ORDER'=>0,'STOCK_CALCULATE_ON_BILL'=>0,'STOCK_CALCULATE_ON_SHIPMENT_CLOSE'=>0) as $key=>$value) {
            if (dolibarr_set_const($db,$key,(string)$value,'chaine',0,'',$conf->entity)<=0) { throw new RuntimeException('Could not configure stock policy.'); }
        }
        // Force reinitialization refreshes menu labels and hook registration without deleting data.
        $result=activateModule('modVerleih',1,1);
        if (!empty($result['errors'])) { throw new RuntimeException(implode('; ',$result['errors'])); }
        if (dolibarr_set_const($db,'RR_RENTING_SCHEMA_VERSION','2.5.0','chaine',0,'',$conf->entity)<=0) {
            throw new RuntimeException('Could not save migration version.');
        }
    }
    require_once DOL_DOCUMENT_ROOT.'/custom/verleih/class/rrrenting.class.php';
    $renting = new RrRenting($db, $user, $conf->entity);
    $renting->setup();
    print "[RR-RENTING] Renting 2.5.0 and warehouses ready.\n";
} catch(Throwable $e) { fwrite(STDERR,"[RR-RENTING] ".$e->getMessage()."\n"); exit(1); }
