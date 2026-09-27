<?php
// Read-only integrity check of the synthetic analytics seed. CLI only.
if(PHP_SAPI!=='cli') exit(1);
define('NOLOGIN',1); define('NOREQUIREMENU',1); define('NOREQUIREHTML',1); define('NOREQUIREAJAX',1);
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/verleih/class/rrrenting.class.php';
$rr=new RrRenting($db,$user,$conf->entity); $p=$db->prefix(); $e=(int)$conf->entity;
$result=array();
$result['rentings']=$rr->rows('SELECT status,COUNT(*) qty FROM '.$rr->table('booking')." WHERE entity=".$e." AND ref LIKE 'RRA1-%' GROUP BY status ORDER BY status");
$result['equipos']=$rr->rows('SELECT status,COUNT(*) qty FROM '.$rr->table('asset')." WHERE entity=".$e." AND serial LIKE 'RRA1-%' GROUP BY status ORDER BY status");
$result['incidencias']=$rr->rows('SELECT i.status,COUNT(*) qty FROM '.$rr->table('incident').' i JOIN '.$rr->table('booking')." b ON b.rowid=i.fk_booking WHERE b.entity=".$e." AND b.ref LIKE 'RRA1-%' GROUP BY i.status ORDER BY i.status");
$result['facturas']=$rr->rows('SELECT DATE_FORMAT(datef,\'%Y-%m\') month,COUNT(*) qty,SUM(CASE WHEN fk_statut=0 THEN 1 ELSE 0 END) drafts FROM '.$p."facture WHERE entity=".$e." AND ref_ext LIKE 'RRA1-%' GROUP BY month ORDER BY month");
$result['pagos']=$rr->one('SELECT COUNT(*) qty FROM '.$p."paiement WHERE entity=".$e." AND ref_ext LIKE 'RRA1-%'");
$checks=array(
 'closed_with_outstanding'=>$rr->one('SELECT COUNT(*) qty FROM '.$rr->table('booking').' b JOIN '.$rr->table('line')." l ON l.fk_booking=b.rowid WHERE b.entity=".$e." AND b.ref LIKE 'RRA1-%' AND b.status='closed' AND l.date_return IS NULL")->qty,
 'invoice_template_orphans'=>$rr->one('SELECT COUNT(*) qty FROM '.$p.'facture f LEFT JOIN '.$rr->table('billing')." rb ON rb.fk_template=f.fk_fac_rec_source WHERE f.entity=".$e." AND f.ref_ext LIKE 'RRA1-%' AND f.fk_fac_rec_source>0 AND rb.fk_booking IS NULL")->qty,
 'duplicate_live_serial'=>$rr->one('SELECT COUNT(*) qty FROM (SELECT l.fk_asset FROM '.$rr->table('line').' l JOIN '.$rr->table('booking')." b ON b.rowid=l.fk_booking WHERE b.entity=".$e." AND b.ref LIKE 'RRA1-%' AND l.date_out IS NOT NULL AND l.date_return IS NULL GROUP BY l.fk_asset HAVING COUNT(*)>1) q")->qty
);
foreach($checks as $name=>$value) if((int)$value!==0) throw new RuntimeException($name.': '.$value);
$result['checks']=$checks;
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;