<?php
/* Integration checks against Dolibarr 24. CLI only; all fixtures rolled back.
 * R&R Technology, 2026. GPL-3.0-or-later. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('NOLOGIN',1); define('NOREQUIREMENU',1); define('NOREQUIREHTML',1); define('NOREQUIREAJAX',1);
require '/var/www/html/master.inc.php';
require_once __DIR__.'/../class/rrrenting.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
$user=new User($db); $user->fetch('',getenv('DOLI_ADMIN_LOGIN') ?: 'admin'); $user->getrights();
$rr=new RrRenting($db,$user,$conf->entity); $passed=0;
function check($ok,$msg) { global $passed; if (!$ok) { throw new RuntimeException($msg); } $passed++; print "PASS ".$msg."\n"; }
function rejected($fn,$msg) {
    global $db;
    $db->query('SAVEPOINT expected_failure');
    $caught=false;
    try { $fn(); } catch (RuntimeException $e) { $caught=true; }
    $db->query('ROLLBACK TO SAVEPOINT expected_failure');
    check($caught,$msg);
}
function productFixture($ref,$type) {
    global $db,$user;
    $p=new Product($db); $p->ref=$ref; $p->label=$ref; $p->type=$type; $p->status=1; $p->status_buy=1;
    $p->price=100; $p->price_base_type='HT'; $p->tva_tx=0; $p->status_batch=$type?0:2;
    $id=$p->create($user);
    if ($id<=0) { throw new RuntimeException('Fixture product: '.$p->error.' '.implode('; ',$p->errors)); }
    return $id;
}
$db->begin();
try {
    $rr->setup(); $c=$rr->config();
    $tag='RRTEST-'.bin2hex(random_bytes(4));
    $product=productFixture($tag.'-PC',0); $service=productFixture($tag.'-SERVICE',1);
    $soc=new Societe($db); $soc->name=$tag; $soc->client=1; $soc->status=1; $soc->code_client='auto';
    check($soc->create($user)>0,'customer fixture created');
    $contract=new Contrat($db); $contract->socid=$soc->id; $contract->date_contrat=dol_now(); $contract->commercial_signature_id=$user->id; $contract->commercial_suivi_id=$user->id;
    check($contract->create($user)>0,'native contract created');
    check($contract->addline('Renting test',100,1,0,0,0,$service,0,dol_now(),dol_now()+86400)>0,'native service attached to contract');
    check($contract->validate($user)>0,'native contract validated');
    $series=array($tag.'-001',$tag.'-002');
    foreach($series as $serial) {
        $m=new MouvementStock($db);
        check($m->reception($user,$product,$c->sale,1,100,'Renting integration test',0,0,$serial)>0,'serialized stock received');
    }
    $a=$rr->enroll($product,$series[0]); $b=$rr->enroll($product,$series[1]);
    check($a>0 && $b>0,'two assets enrolled by native transfer');
    $total=$rr->one("SELECT SUM(reel) qty FROM ".$db->prefix()."product_stock WHERE fk_product=".$product);
    check((float)$total->qty===2.0,'enrollment preserves total stock');
    rejected(function()use($rr,$product,$series){$rr->enroll($product,$series[0]);},'duplicate enrollment rejected');
    $today=dol_print_date(dol_now(),'%Y-%m-%d'); $end=date('Y-m-d',strtotime($today.' +2 days'));
    $contractLine=$rr->one('SELECT rowid FROM '.$db->prefix().'contratdet WHERE fk_contrat='.(int)$contract->id)->rowid;
    check($contract->updateline($contractLine,'Two serialized units',100,2,0,strtotime($today),strtotime($end),0)>0,'unreserved contractual conditions can be edited');
    rejected(function()use($rr,$soc,$contract,$contractLine,$product,$a){$rr->reserveFromContract($soc->id,$contract->id,$contractLine,$product,array($a));},'contract quantity rejects incomplete selection');
    rejected(function()use($rr,$soc,$contract,$contractLine,$service,$a,$b){$rr->reserveFromContract($soc->id,$contract->id,$contractLine,$service,array($a,$b));},'equipment must be a serialized physical product');
    $bound=$rr->reserveFromContract($soc->id,$contract->id,$contractLine,$product,array($a,$b));
    $boundBooking=$rr->booking($bound);
    check($boundBooking->date_start===$today && $boundBooking->date_end===$end,'reservation inherits contractual dates');
    rejected(function()use($rr,$soc,$contract,$contractLine,$product,$a,$b){$rr->reserveFromContract($soc->id,$contract->id,$contractLine,$product,array($a,$b));},'same contractual commitment cannot be reserved twice');
    check($contract->deleteLine($contractLine,$user)<0,'native contract line deletion blocked while reserved');
    $db->query('SAVEPOINT contract_edit');
    check($contract->updateline($contractLine,'Reduced quantity',100,1,0,strtotime($today),strtotime($end),0)<0,'native contract quantity modification blocked while reserved');
    $db->query('ROLLBACK TO SAVEPOINT contract_edit');
    $db->query('SAVEPOINT contract_drift');
    $rr->query('UPDATE '.$db->prefix().'contratdet SET qty=3 WHERE rowid='.(int)$contractLine);
    rejected(function()use($rr,$bound){$rr->checkout($bound);},'delivery rejects a contract changed outside normal controls');
    $db->query('ROLLBACK TO SAVEPOINT contract_drift');
    $db->query('SAVEPOINT bound_delivery');
    $rr->checkout($bound);
    check($rr->booking($bound)->status==='active','contract-bound reservation delivers complete equipment set');
    check($contract->deleteLine($contractLine,$user)<0,'contract line remains protected after delivery');
    $db->query('ROLLBACK TO SAVEPOINT bound_delivery');
    $rr->cancel($bound);
    check($contract->updateline($contractLine,'Two units',100,2,0,strtotime($today),strtotime($end),0)>0,'contract line unlocked after cancellation');
    rejected(function()use($rr,$soc,$contract,$service,$today,$end){$rr->reserve($soc->id,$contract->id,$service,$end,$today,array(0));},'invalid dates rejected');
    rejected(function()use($rr,$soc,$contract,$service,$today,$end,$a){$rr->reserve($soc->id+999999,$contract->id,$service,$today,$end,array($a));},'wrong customer rejected');
    $booking=$rr->reserve($soc->id,$contract->id,$service,$today,$end,array($a,$b));
    rejected(function()use($rr,$soc,$contract,$service,$today,$end,$a){$rr->reserve($soc->id,$contract->id,$service,$today,$end,array($a));},'overlapping reservation rejected');
    rejected(function()use($rr,$a){$rr->inspect($a,'sale','good','test');},'reserved asset cannot be moved to sale');
    $future=$rr->reserve($soc->id,$contract->id,$service,date('Y-m-d',strtotime($end.' +1 day')),date('Y-m-d',strtotime($end.' +3 days')),array($a));
    check($future>0,'nonoverlapping future reservation accepted');
    rejected(function()use($rr,$future){$rr->checkout($future);},'early delivery rejected');
    $rr->cancel($future);
    // Force the second transfer to fail and verify the first is undone by the workflow itself.
    $rr->query("UPDATE ".$db->prefix()."product_batch pb JOIN ".$db->prefix()."product_stock ps ON ps.rowid=pb.fk_product_stock SET pb.qty=0 WHERE ps.fk_product=".$product." AND pb.batch=".$rr->quote($series[1]));
    $failed=false;
    try { $rr->checkout($booking); } catch (RuntimeException $ex) { $failed=true; }
    check($failed && $rr->asset($a)->status==='available','failed multi-equipment checkout rolls back earlier asset updates');
    $aStock=$rr->one("SELECT pb.qty FROM ".$db->prefix()."product_batch pb JOIN ".$db->prefix()."product_stock ps ON ps.rowid=pb.fk_product_stock WHERE ps.fk_entrepot=".(int)$c->available." AND ps.fk_product=".$product." AND pb.batch=".$rr->quote($series[0]));
    check((float)$aStock->qty===1.0,'failed checkout restores original warehouse stock');
    $rr->query("UPDATE ".$db->prefix()."product_batch pb JOIN ".$db->prefix()."product_stock ps ON ps.rowid=pb.fk_product_stock SET pb.qty=1 WHERE ps.fk_product=".$product." AND pb.batch=".$rr->quote($series[1]));
    $rr->checkout($booking);
    check($rr->asset($a)->status==='out','checkout marks asset delivered');
    rejected(function()use($rr,$booking){$rr->checkout($booking);},'repeated checkout rejected');
    $m=new MouvementStock($db);
    $db->query('SAVEPOINT stock_guard');
    $result=$m->livraison($user,$product,$c->customer,1,0,'Unauthorized sale','',0,0,$series[0]);
    check($result<0,'native sale from renting warehouse blocked by hook');
    $db->query('ROLLBACK TO SAVEPOINT stock_guard');
    $lines=$rr->rows("SELECT * FROM ".$rr->table('line')." WHERE fk_booking=".$booking." ORDER BY rowid");
    $rr->receive($booking,$lines[0]->rowid,'good');
    check($rr->booking($booking)->status==='partial','partial return tracked');
    check($rr->asset($a)->status==='review','return goes to review instead of immediately available');
    rejected(function()use($rr,$booking,$lines){$rr->receive($booking,$lines[0]->rowid,'good');},'duplicate return rejected');
    rejected(function()use($rr,$a){$rr->inspect($a,'available','damaged','test');},'damaged equipment cannot become available');
    $rr->inspect($a,'available','good','Inspection passed');
    $rr->receive($booking,$lines[1]->rowid,'damaged');
    $rr->inspect($b,'repair','damaged','Repair required');
    check($rr->booking($booking)->status==='closed','all returns close the renting');
    check($rr->asset($b)->status==='repair','damaged equipment retained in repair');
    $rr->inspect($b,'available','good','Repair completed');
    $rr->inspect($a,'sale','worn','Reviewed for used sale');
    check($rr->asset($a)->status==='sale','uncommitted reviewed asset transferred to sale');
    $total=$rr->one("SELECT SUM(reel) qty FROM ".$db->prefix()."product_stock WHERE fk_product=".$product);
    check((float)$total->qty===2.0,'full workflow preserves exactly two physical units');
    require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
    $oversell=new Commande($db); $oversell->socid=$soc->id; $oversell->date=dol_now();
    check($oversell->create($user)>0,'native sales order created');
    check($oversell->addline('Hardware',100,2,0,0,0,$product)>0,'draft sale can be prepared');
    $db->query('SAVEPOINT order_reject');
    $result=$oversell->valid($user);
    check($result<0 && strpos($oversell->error.' '.implode(' ',$oversell->errors),'Stock de venta insuficiente')!==false,'sale cannot count renting stock when validating order');
    $db->query('ROLLBACK TO SAVEPOINT order_reject');
    $sale=new Commande($db); $sale->socid=$soc->id; $sale->date=dol_now();
    check($sale->create($user)>0,'second native sales order created');
    check($sale->addline('Hardware',100,1,0,0,0,$product)>0,'sale line for available unit created');
    check($sale->valid($user)>0,'sale within RR-VENTA stock is allowed');
    rejected(function()use($rr,$product,$series){$rr->enroll($product,$series[0]);},'stock committed to validated sale cannot move to renting');
    $other=new RrRenting($db,$user,$conf->entity+999);
    rejected(function()use($other,$a){$other->asset($a);},'cross-entity asset lookup blocked');
    $restricted=new User($db); $restricted->id=999999; $restricted->admin=0;
    $limited=new RrRenting($db,$restricted,$conf->entity);
    rejected(function()use($limited,$product,$series){$limited->enroll($product,$series[0]);},'write operation without permission rejected');
    print "SUCCESS: ".$passed." integration checks. Fixtures rolled back.\n";
} catch(Throwable $ex) {
    fwrite(STDERR,"FAIL: ".$ex->getMessage()."\n".$ex->getTraceAsString()."\n");
    $db->query('ROLLBACK'); $db->transaction_opened=0; exit(1);
} finally { $db->query('ROLLBACK'); $db->transaction_opened=0; }
