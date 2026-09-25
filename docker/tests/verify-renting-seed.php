<?php
// CLI integration checks for the isolated rr-renting-seed-test Compose project.
if (PHP_SAPI !== 'cli') { exit(1); }
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/verleih/class/rrrenting.class.php';
$mode = $argv[1] ?? 'initial';
$user = new User($db);
if ($user->fetch(0, getenv('DOLI_ADMIN_LOGIN') ?: 'admin') <= 0) { throw new RuntimeException('Admin missing'); }
$user->getrights();
$rr = new RrRenting($db, $user, $conf->entity);
$p = $db->prefix();
$entity = (int) $conf->entity;
function seedCheck($ok, $message) {
    if (!$ok) { throw new RuntimeException($message); }
    print 'PASS '.$message.PHP_EOL;
}
$c = $rr->config();
seedCheck(count(array_unique(array($c->sale,$c->available,$c->customer,$c->review,$c->repair))) === 5, 'five distinct warehouses configured');
$clients = $rr->one("SELECT COUNT(*) n FROM ".$p."societe WHERE entity=".$entity." AND ref_ext IN ('rr-demo-client-esports','rr-demo-client-corporate','rr-demo-client-individual')");
seedCheck((int)$clients->n === 3, 'exactly three demo customers');
$product = $rr->one("SELECT rowid,tobatch FROM ".$p."product WHERE entity=".$entity." AND ref='RR-PC-001'");
seedCheck((int)$product->tobatch === 2, 'product uses unique serial numbers');
$service = $rr->one("SELECT rowid,fk_product_type FROM ".$p."product WHERE entity=".$entity." AND ref='RR-RENT-PC-MES'");
seedCheck((int)$service->fk_product_type === 1, 'renting billed as a service');
$contracts = $rr->one("SELECT COUNT(*) n FROM ".$p."contrat c JOIN ".$p."societe s ON s.rowid=c.fk_soc WHERE c.entity=".$entity." AND c.statut=1 AND c.ref IN ('RR-DEMO-ESP-001','RR-DEMO-CORP-001','RR-DEMO-B2C-001') AND c.ref_ext=REPLACE(s.ref_ext,'client','contract') AND EXISTS (SELECT 1 FROM ".$p."contratdet d WHERE d.fk_contrat=c.rowid AND d.fk_product=".(int)$service->rowid.")");
seedCheck((int)$contracts->n === 3, 'three validated contracts with matching customer and service');
$serials = $rr->one("SELECT COUNT(DISTINCT pb.batch) n,SUM(pb.qty) qty FROM ".$p."product_batch pb JOIN ".$p."product_stock ps ON ps.rowid=pb.fk_product_stock WHERE ps.fk_product=".(int)$product->rowid." AND pb.batch IN ('RR-FORGE-001','RR-FORGE-002','RR-FORGE-003','RR-FORGE-004','RR-FORGE-005','RR-FORGE-006')");
seedCheck((int)$serials->n === 6 && (float)$serials->qty === 6.0, 'six serials and exactly six physical units');
if ($mode === 'move') {
    if (getenv('RR_SEED_TEST_ALLOW_MUTATION') !== 'isolated-project-only') { throw new RuntimeException('Mutation is permitted only in the test project'); }
    $rr->enroll($product->rowid, 'RR-FORGE-001');
    print "PASS one unit transferred for restart test\n";
    exit(0);
}
$sale = $rr->one("SELECT COALESCE(SUM(reel),0) qty FROM ".$p."product_stock WHERE fk_product=".(int)$product->rowid." AND fk_entrepot=".(int)$c->sale);
$assets = $rr->one("SELECT COUNT(*) n FROM ".$rr->table('asset')." WHERE entity=".$entity);
$bookings = $rr->one("SELECT COUNT(*) n FROM ".$rr->table('booking')." WHERE entity=".$entity);
seedCheck((int)$bookings->n === 0, 'no renting bookings created');
seedCheck((float)$sale->qty === ($mode === 'moved' ? 5.0 : 6.0), 'origin stock reflects user transfers');
seedCheck((int)$assets->n === ($mode === 'moved' ? 1 : 0), 'no automatic enrollment or duplicate asset');
$orders = $rr->one("SELECT COUNT(*) n FROM ".$p."commande WHERE entity=".$entity);
$invoices = $rr->one("SELECT COUNT(*) n FROM ".$p."facture WHERE entity=".$entity);
seedCheck((int)$orders->n === 0 && (int)$invoices->n === 0, 'no orders or invoices created');
seedCheck(getDolGlobalString('RR_RENTING_DEMO_SEED_VERSION') === '1', 'seed version stored');
print "SUCCESS: seed verification (".$mode.")\n";
