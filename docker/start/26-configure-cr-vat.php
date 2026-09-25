<?php
// Register the requested Costa Rica rate in Dolibarr's native VAT dictionary.
if (PHP_SAPI !== 'cli') { exit(1); }
define('NOLOGIN', 1); define('NOREQUIREMENU', 1); define('NOREQUIREHTML', 1); define('NOREQUIREAJAX', 1);
require '/var/www/html/master.inc.php';
$lock = 'rr-cr-vat-'.(int) $conf->entity;
$locked = false;
try {
    $res = $db->query("SELECT GET_LOCK('".$lock."',30) acquired");
    if (!$res || (int) $db->fetch_object($res)->acquired !== 1) { throw new RuntimeException('Cannot lock VAT configuration.'); }
    $locked = true;
    $res = $db->query("SELECT rowid FROM ".$db->prefix()."c_country WHERE code='CR'");
    if (!$res || !($country = $db->fetch_object($res))) { throw new RuntimeException('Country CR not found.'); }
    $table = $db->prefix().'c_tva';
    $where = 'entity='.(int) $conf->entity.' AND fk_pays='.(int) $country->rowid.' AND taux=13 AND type_vat=0 AND recuperableonly=0 AND fk_department_buyer IS NULL';
    $res = $db->query('SELECT rowid FROM '.$table.' WHERE '.$where.' ORDER BY active DESC,rowid LIMIT 1');
    if (!$res) { throw new RuntimeException($db->lasterror()); }
    $existing = $db->fetch_object($res);
    if ($existing) {
        $res = $db->query('UPDATE '.$table.' SET active=1 WHERE rowid='.(int) $existing->rowid);
    } else {
        $res = $db->query("INSERT INTO ".$table." (entity,fk_pays,code,type_vat,taux,localtax1,localtax1_type,localtax2,localtax2_type,recuperableonly,note,active) VALUES (".(int) $conf->entity.",".(int) $country->rowid.",'',0,13,'0','0','0','0',0,'IVA Costa Rica 13%',1)");
    }
    if (!$res) { throw new RuntimeException($db->lasterror()); }
    print "[RR-VAT] Costa Rica 13% available in VAT dictionary.\n";
} catch (Throwable $error) {
    fwrite(STDERR, '[RR-VAT] '.$error->getMessage()."\n"); exit(1);
} finally {
    if ($locked) { $db->query("SELECT RELEASE_LOCK('".$lock."')"); }
    $db->close();
}
