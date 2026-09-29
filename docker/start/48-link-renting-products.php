<?php
// Idempotent demo relationships. Never overwrite a relation configured by the user.
if (PHP_SAPI !== 'cli') exit(1);
define('NOLOGIN',1); define('NOREQUIREMENU',1); define('NOREQUIREHTML',1);
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/verleih/lib/renting_schema.php';
rrRentingMigrate($db);
$p=$db->prefix(); $entity=(int)$conf->entity;
$pairs=array('RR-RENT-PC-MES'=>'RR-PC-001','RRA1-SVC-1'=>'RR-PC-001','RRA1-SVC-2'=>'RR-MON-001','RRA1-SVC-3'=>'RR-KEY-001','RRA1-SVC-4'=>'RR-MOUSE-001','RRA1-SVC-5'=>'RR-AUDIO-001');
foreach($pairs as $service=>$product) {
    $sql="INSERT INTO ".$p."rr_renting_service_product (entity,fk_service,fk_product) SELECT s.entity,s.rowid,p.rowid FROM ".$p."product s JOIN ".$p."product p ON p.entity=s.entity WHERE s.entity=".$entity." AND s.ref='".$db->escape($service)."' AND p.ref='".$db->escape($product)."' AND s.fk_product_type=1 AND p.fk_product_type=0 AND p.tobatch=2 AND NOT EXISTS (SELECT 1 FROM ".$p."rr_renting_service_product m WHERE m.entity=s.entity AND m.fk_service=s.rowid)";
    if(!$db->query($sql)) { fwrite(STDERR,$db->lasterror()); exit(1); }
}
echo "[RR-RENTING] Service/product relationships ready.\n";
