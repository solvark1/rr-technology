<?php
/** Synthetic analytical history. CLI-only import; never relaxes operational date guards. */
if(PHP_SAPI!=='cli') exit(1);
if(getenv('RR_ANALYTICS_SEED')!=='1') { echo "[RR-ANALYTICS] Disabled.\n"; exit(0); }
define('NOLOGIN',1); define('NOREQUIREMENU',1); define('NOREQUIREHTML',1); define('NOREQUIREAJAX',1);
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/verleih/class/rrbilling.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
$user=new User($db);
if($user->fetch(0,getenv('DOLI_ADMIN_LOGIN') ?: 'admin')<=0 || !$user->admin) exit(1);
$user->getrights();
$rr=new RrBilling($db,$user,$conf->entity); $p=$db->prefix(); $e=(int)$conf->entity;
$tag='RRA1'; $note='[SIMULACION ANALITICA RRA1] Datos ficticios para visualizaciones; no representan operaciones reales.';
$dry=in_array('--dry-run',$argv,true); $lock='rr-analytics-'.$e; $locked=false;
function must($result,$object,$context) { if($result<=0) throw new RuntimeException($context.': '.($object->error ?? '').' '.implode('; ',(array)($object->errors ?? array()))); return $result; }
function stamp($time) { global $rr,$db; return $rr->quote($db->idate($time)); }
function audit($name,$asset,$booking,$time,$text='') {
 global $rr,$user,$e,$note;
 $rr->query('INSERT INTO '.$rr->table('event').' (entity,fk_asset,fk_booking,event,note,fk_user,date_creation) VALUES ('.$e.','.(int)$asset.','.(int)$booking.','.$rr->quote($name).','.$rr->quote($text ?: $note).','.(int)$user->id.','.stamp($time).')');
}
function unit($product,$serial,$time) {
 global $db,$user,$rr,$c,$p,$note,$e;
 $m=new MouvementStock($db);
 must($m->reception($user,$product,$c->sale,1,0,$note,0,0,$serial,$time),$m,'Stock '.$serial);
 $rr->query('INSERT INTO '.$rr->table('asset').' (entity,fk_product,serial,status,item_condition,fk_warehouse,note,date_creation) VALUES ('.$e.','.(int)$product.','.$rr->quote($serial).",'sale','good',".(int)$c->sale.','.$rr->quote($note).','.stamp($time).')');
 $id=$db->last_insert_id($rr->table('asset'));
 moveUnit($id,'available','good',$time,0,'enroll');
 return $id;
}
function moveUnit($id,$state,$condition,$time,$booking,$event) {
 global $rr,$c,$p;
 $a=$rr->asset($id,true); $field=$state==='out'?'customer':$state; $to=(int)$c->$field;
 if((int)$a->fk_warehouse!==$to) {
  $before=(int)$rr->one('SELECT COALESCE(MAX(rowid),0) id FROM '.$p.'stock_mouvement')->id;
  $rr->transfer($a->fk_product,$a->serial,$a->fk_warehouse,$to,'[SIMULACION RRA1] '.$event);
  $rr->query('UPDATE '.$p.'stock_mouvement SET datem='.stamp($time).' WHERE rowid>'.$before.' AND fk_product='.(int)$a->fk_product.' AND batch='.$rr->quote($a->serial));
 }
 $rr->query('UPDATE '.$rr->table('asset').' SET status='.$rr->quote($state).',item_condition='.$rr->quote($condition).',fk_warehouse='.$to.' WHERE rowid='.(int)$id);
 if($event==='inspection') $rr->query('UPDATE '.$rr->table('line').' SET condition_in='.$rr->quote($condition).' WHERE fk_asset='.(int)$id." AND date_return IS NOT NULL AND condition_in='pending'");
 audit($event,$id,$booking,$time);
}
function delivery($booking,$asset,$time) {
 global $rr,$db;
 $a=$rr->asset($asset);
 moveUnit($asset,'out',$a->item_condition,$time,$booking,'checkout');
 $rr->query('INSERT INTO '.$rr->table('line').' (fk_booking,fk_asset,condition_out,date_out) VALUES ('.(int)$booking.','.(int)$asset.','.$rr->quote($a->item_condition).','.stamp($time).')');
 return $db->last_insert_id($rr->table('line'));
}
function receiveHistory($booking,$line,$time) {
 global $rr;
 $l=$rr->one('SELECT * FROM '.$rr->table('line').' WHERE rowid='.(int)$line);
 moveUnit($l->fk_asset,'review','pending',$time,$booking,'return');
 $rr->query('UPDATE '.$rr->table('line')." SET condition_in='pending',date_return=".stamp($time).' WHERE rowid='.(int)$line);
}
function invoiceHistory($customer,$product,$qty,$price,$date,$key,$template,$paymentKind,$draft=false) {
 global $db,$user,$rr,$p,$note,$terms,$paymode,$bankId,$now,$counts;
 $f=new Facture($db); $f->socid=$customer; $f->type=0; $f->date=$date;
 $f->cond_reglement_id=$terms; $f->mode_reglement_id=$paymode;
 $f->note_private=$note; $f->ref_ext=$key; $f->fk_fac_rec_source=$template; $f->fac_rec=$template;
 RrBilling::$internal=true;
 try { must($f->create($user),$f,'Invoice '.$key); } finally { RrBilling::$internal=false; }
 must($f->addline($note,$price,$qty,13,0,0,$product),$f,'Invoice line');
 $rr->query('UPDATE '.$p.'facture SET datec='.stamp($date).',date_lim_reglement='.$rr->quote(date('Y-m-d',strtotime('+30 days',$date))).' WHERE rowid='.(int)$f->id);
 if(!$draft) {
  must($f->validate($user,$key),$f,'Invoice validation');
  $rr->query('UPDATE '.$p.'facture SET date_valid='.stamp($date).' WHERE rowid='.(int)$f->id);
  $f->fetch($f->id);
  if($paymentKind!==0) {
   $paidDate=strtotime('+'.(4+($paymentKind%8)).' days',$date);
   if($paidDate<=$now) {
    $payment=new Paiement($db); $payment->datepaye=$paidDate; $payment->paiementid=$paymode;
    $payment->amounts=array($f->id=>round($f->total_ttc*($paymentKind===1?0.5:1),2));
    $payment->note_private=$note; $payment->ref_ext=$key.'-PAY';
    must($payment->create($user,1),$payment,'Payment');
    must($payment->addPaymentToBank($user,'payment',$note,$bankId,'Cliente simulado','Banco simulado'),$payment,'Bank payment');
    $rr->query('UPDATE '.$p.'paiement SET datec='.stamp($paidDate).' WHERE rowid='.(int)$payment->id);
    $counts['pagos']++;
   }
  }
 }
 $counts['facturas']++; return $f->id;
}
try {
 $locked=(int)$rr->one('SELECT GET_LOCK('.$rr->quote($lock).',30) acquired')->acquired===1;
 if(!$locked) throw new RuntimeException('Seeder ocupado');
 if($rr->rows('SELECT rowid FROM '.$p."const WHERE entity=".$e." AND name='RR_ANALYTICS_SEED_VERSION' AND value='1'")) { echo "[RR-ANALYTICS] Version 1 already loaded; unchanged.\n"; exit(0); }
 if($rr->rows('SELECT rowid FROM '.$rr->table('booking')." WHERE entity=".$e." AND ref LIKE 'RRA1-%'")) throw new RuntimeException('Datos RRA1 sin marcador: revisar antes de importar.');
 $db->begin(); $c=$rr->config(true); $now=dol_now(); $month=strtotime(date('Y-m-01',$now));
 $terms=(int)$rr->one('SELECT rowid FROM '.$p."c_payment_term WHERE active=1 ORDER BY CASE WHEN code='30D' THEN 0 ELSE 1 END,rowid LIMIT 1")->rowid;
 $paymode=(int)$rr->one('SELECT id FROM '.$p."c_paiement WHERE code='VIR' AND active=1")->id;
 $counts=array('clientes'=>0,'contratos'=>0,'rentings'=>0,'facturas'=>0,'pagos'=>0,'ventas'=>0,'incidencias'=>0);
 $country=(int)$rr->one('SELECT rowid FROM '.$p."c_country WHERE code='CR'")->rowid;
 $bank=new Account($db); $bank->ref='RRA1-BANK'; $bank->label='SIMULACION ANALITICA - CRC'; $bank->bank='Banco ficticio';
 $bank->type=Account::TYPE_CURRENT; $bank->courant=Account::TYPE_CURRENT; $bank->status=0; $bank->clos=0;
 $bank->country_id=$country; $bank->owner_name='R&R - SIMULACION'; $bank->currency_code=$conf->currency;
 $bank->balance=0; $bank->date_solde=strtotime('-7 months',$month); $bank->comment=$note;
 $bankId=must($bank->create($user),$bank,'Analytical bank');
 $clients=array();
 $names=array('Nexo Esports','Aurora Render','Circuito Creativo','Orbita IA','Pixel Norte','Estudio Prisma','Liga del Valle','Vector Arquitectura','Mateo Demo','Sofia Demo','Campus Digital','Arena Central');
 foreach($names as $i=>$name) {
  $s=new Societe($db); $s->name=$name.' [SIMULADO]'; $s->client=1; $s->status=1; $s->code_client='auto'; $s->country_id=$country;
  $s->ref_ext='RRA1-CLIENT-'.($i+1); $s->email='rra1-'.($i+1).'@example.invalid'; $s->note_private=$note;
  $clients[]=must($s->create($user),$s,'Customer'); $counts['clientes']++;
 }
 $refs=array('RR-PC-001'=>115000,'RR-MON-001'=>18000,'RR-KEY-001'=>4500,'RR-MOUSE-001'=>3500,'RR-AUDIO-001'=>6500);
 $products=array(); $pi=0;
 foreach($refs as $ref=>$rate) {
  $pr=new Product($db); must($pr->fetch(0,$ref),$pr,'Existing product');
  if((int)$pr->entity!==$e) throw new RuntimeException('Product entity mismatch');
  if((int)$pr->status_batch!==2) {
   $stock=$rr->one('SELECT COALESCE(SUM(ABS(reel)),0) qty FROM '.$p.'product_stock WHERE fk_product='.(int)$pr->id);
   if((float)$stock->qty!==0.0) throw new RuntimeException($ref.' tiene stock sin serie unica; regularizar antes de activar la simulacion.');
   $pr->status_batch=2; must($pr->update($pr->id,$user),$pr,'Enable serial tracking');
  }
  $products[]=$pr->id;
  $svc=new Product($db); $svc->ref='RRA1-SVC-'.($pi+1); $svc->label='Alquiler mensual '.$pr->label.' [SIMULADO]';
  $svc->type=1; $svc->status=1; $svc->status_buy=0; $svc->price=$rate; $svc->price_base_type='HT'; $svc->tva_tx=13; $svc->description=$note;
  must($svc->create($user),$svc,'Monthly service');
  for($j=0;$j<7;$j++) {
   $key=sprintf('RRA1-%02d-%02d',$pi+1,$j+1); $qty=1+(($pi+$j)%3);
   $start=$j<4?strtotime('-'.(6-$j).' months +4 days',$month):($j===4?strtotime('-1 month +4 days',$month):strtotime('+7 days',$now));
   $end=strtotime('+2 months',$start); $customer=$clients[($pi*3+$j)%count($clients)];
   $contract=new Contrat($db); $contract->socid=$customer; $contract->date_contrat=min(strtotime('-5 days',$start),$now);
   $contract->ref=$key; $contract->ref_ext=$key; $contract->note_private=$note;
   $contract->commercial_signature_id=$user->id; $contract->commercial_suivi_id=$user->id;
   must($contract->create($user),$contract,'Contract');
   $cl=must($contract->addline($svc->label,$rate,$qty,13,0,0,$svc->id,0,$start,$end),$contract,'Contract line');
   must($contract->validate($user,$key),$contract,'Contract validation');
   $contract->fetch($contract->id);
   if($j<5) must($contract->active_line($user,$cl,$start,$end,$note),$contract,'Activate contract service');
   $rr->query('UPDATE '.$p.'contrat SET datec='.stamp(min(strtotime('-5 days',$start),$now)).' WHERE rowid='.(int)$contract->id);
   $counts['contratos']++;
   $initial=$j<5?'active':'reserved';
   $rr->query('INSERT INTO '.$rr->table('booking').' (entity,ref,fk_soc,fk_contract,fk_service,date_start,date_end,status,note,fk_user,date_creation) VALUES ('.$e.','.$rr->quote($key).','.$customer.','.$contract->id.','.$svc->id.','.$rr->quote(date('Y-m-d',$start)).','.$rr->quote(date('Y-m-d',$end)).','.$rr->quote($initial).','.$rr->quote($note).','.$user->id.','.stamp(min(strtotime('-4 days',$start),$now)).')');
   $bid=$db->last_insert_id($rr->table('booking'));
   $rr->query('INSERT INTO '.$rr->table('contract_link').' (fk_booking,fk_contract_line,fk_product,qty) VALUES ('.$bid.','.$cl.','.$pr->id.','.$qty.')');
   $live=array();
   for($u=0;$u<$qty;$u++) {
    $asset=unit($pr->id,$key.'-U'.($u+1),min(strtotime('-10 days',$start),$now-86400));
    if($j<5) $live[]=delivery($bid,$asset,$start);
    else { $rr->query('INSERT INTO '.$rr->table('line').' (fk_booking,fk_asset) VALUES ('.$bid.','.$asset.')'); }
   }
   // One resolved historical fault and one current fault per product.
   if($j===1 || $j===4) {
    $oldLine=$live[0]; $old=$rr->one('SELECT fk_asset FROM '.$rr->table('line').' WHERE rowid='.$oldLine)->fk_asset;
    $report=$j===1?strtotime('+12 days',$start):strtotime('-'.(2+$pi).' days',$now);
    $received=$report+3600; $resolved=$received+($pi+1)*86400;
    $kind=$j===1?'replaced':($pi%3===0?'open':($pi%3===1?'received':'replaced'));
    if($kind==='replaced' && $resolved>$now) $resolved=$now-3600;
    $newLine=0;
    audit('incident',$old,$bid,$report,'[SIMULADO] Fallo intermitente reportado por cliente');
    if($kind!=='open') {
     receiveHistory($bid,$oldLine,$received);
     moveUnit($old,'repair','damaged',$received+1800,$bid,'inspection');
     if($kind==='replaced') {
      $spare=unit($pr->id,$key.'-REPL',strtotime('-1 day',$report));
      $newLine=delivery($bid,$spare,$resolved); $live[0]=$newLine;
      if($j===1) moveUnit($old,'available','good',$resolved+86400,$bid,'inspection');
     } else array_shift($live);
    }
    $rr->query('INSERT INTO '.$rr->table('incident').' (entity,fk_booking,fk_line,reason,status,fk_new_line,resolution,fk_user,date_creation,date_resolution) VALUES ('.$e.','.$bid.','.$oldLine.','.$rr->quote('[SIMULADO] Fallo intermitente: requiere diagnostico').','.$rr->quote($kind).','.($newLine?:'NULL').','.$rr->quote($newLine?'[SIMULADO] Sustituto entregado':'').','.$user->id.','.stamp($report).','.($newLine?stamp($resolved):'NULL').')');
    $counts['incidencias']++;
   }
   $template=$rr->createSchedule($bid,true,$terms);
   $count=0; $next=$start;
   while($j<5 && $next<=$now && $next<$end) {
    invoiceHistory($customer,$svc->id,$qty,$rate,$next,$key.'-F'.($count+1),$template,($pi+$j+$count)%4,($j===4 && $pi===4 && $count===1));
    $next=strtotime('+1 month',$next); $count++;
   }
   if($j<4) {
    foreach($live as $li) {
     receiveHistory($bid,$li,$end);
     $aid=$rr->one('SELECT fk_asset FROM '.$rr->table('line').' WHERE rowid='.$li)->fk_asset;
     $target=$j===3?($pi%2===0?'review':'repair'):($j===2 && $pi===4?'sale':'available');
     if($target!=='review') moveUnit($aid,$target,$target==='repair'?'damaged':'good',$end+86400,$bid,'inspection');
    }
    $rr->query('UPDATE '.$rr->table('booking')." SET status='closed' WHERE rowid=".$bid);
    must($contract->close_line($user,$cl,$end,$note),$contract,'Close contract service');
    audit('return_all',0,$bid,$end);
   } elseif($j===6) { $rr->query('UPDATE '.$rr->table('booking')." SET status='cancelled' WHERE rowid=".$bid); audit('cancel',0,$bid,$now); }
   $state=$j===4?'running':($j===5?'waiting':'paused'); $suspended=$state==='running'?0:1;
   $rr->query('UPDATE '.$p.'facture_rec SET nb_gen_done='.$count.',date_when='.stamp($next).',suspended='.$suspended.' WHERE rowid='.$template);
   $rr->query('UPDATE '.$rr->table('billing').' SET state='.$rr->quote($state).',reason='.$rr->quote($j<4?'[SIMULADO] Periodo concluido':($j===6?'[SIMULADO] Reserva cancelada':'')).' WHERE fk_booking='.$bid);
   $rr->query('UPDATE '.$rr->table('event').' SET date_creation='.stamp(min(strtotime('-3 days',$start),$now)).' WHERE fk_booking='.$bid." AND event='billing_create'");
   $counts['rentings']++;
  }
  $pi++;
 }
 // Twenty completed sales: native invoices and physical departures from RR-VENTA.
 for($i=0;$i<20;$i++) {
  $idx=$i%5; $product=$products[$idx]; $date=strtotime('-'.(1+($i%6)).' months +'.(5+$i%15).' days',$month);
  $key=sprintf('RRA1-SALE-%02d',$i+1); $serial=$key.'-U1';
  $m=new MouvementStock($db); must($m->reception($user,$product,$c->sale,1,0,$note,0,0,$serial,$date-86400),$m,'Sale stock receipt');
  $price=array(707080,140000,26000,18000,42000)[$idx];
  $fid=invoiceHistory($clients[$i%12],$product,1,$price,$date,$key,0,$i%4);
  $out=new MouvementStock($db); $out->origin_type='facture'; $out->origin_id=$fid;
  must($out->livraison($user,$product,$c->sale,1,0,$note,$date,'','',$serial),$out,'Completed sale departure');
  $counts['ventas']++;
 }
 // Reconcile every synthetic fleet serial against native batch stock.
 foreach($rr->rows('SELECT * FROM '.$rr->table('asset')." WHERE entity=".$e." AND serial LIKE 'RRA1-%'") as $a) {
  $rows=$rr->rows('SELECT ps.fk_entrepot,pb.qty FROM '.$p.'product_batch pb JOIN '.$p.'product_stock ps ON ps.rowid=pb.fk_product_stock WHERE ps.fk_product='.(int)$a->fk_product.' AND pb.batch='.$rr->quote($a->serial).' AND pb.qty<>0');
  if(count($rows)!==1 || (float)$rows[0]->qty!==1.0 || (int)$rows[0]->fk_entrepot!==(int)$a->fk_warehouse) throw new RuntimeException('Stock inconsistent: '.$a->serial);
 }
 // Validate analytical joins and outstanding commitments before committing.
 $invalid=$rr->one('SELECT COUNT(*) n FROM '.$rr->table('line').' l JOIN '.$rr->table('booking')." b ON b.rowid=l.fk_booking WHERE b.entity=".$e." AND b.ref LIKE 'RRA1-%' AND ((l.date_return IS NOT NULL AND (l.date_out IS NULL OR l.date_return<l.date_out)) OR (b.status='closed' AND l.date_return IS NULL))");
 if($invalid->n) throw new RuntimeException('Invalid historical delivery interval');
 $missing=$rr->one('SELECT COUNT(*) n FROM '.$rr->table('booking').' b LEFT JOIN '.$rr->table('billing')." rb ON rb.fk_booking=b.rowid WHERE b.entity=".$e." AND b.ref LIKE 'RRA1-%' AND rb.fk_booking IS NULL");
 if($missing->n) throw new RuntimeException('Missing billing relationship');
 $overpaid=$rr->one('SELECT COUNT(*) n FROM '.$p.'facture f JOIN (SELECT fk_facture,SUM(amount) paid FROM '.$p.'paiement_facture GROUP BY fk_facture) q ON q.fk_facture=f.rowid WHERE f.entity='.$e." AND f.ref_ext LIKE 'RRA1-%' AND q.paid>f.total_ttc+0.01");
 if($overpaid->n) throw new RuntimeException('Overpaid synthetic invoice');
 $mismatch=$rr->one('SELECT COUNT(*) n FROM '.$rr->table('billing').' rb JOIN '.$rr->table('booking').' b ON b.rowid=rb.fk_booking JOIN '.$p.'facture_rec r ON r.rowid=rb.fk_template WHERE b.entity='.$e." AND b.ref LIKE 'RRA1-%' AND r.nb_gen_done<>(SELECT COUNT(*) FROM ".$p.'facture f WHERE f.fk_fac_rec_source=r.rowid AND f.entity='.$e.')');
 if($mismatch->n) throw new RuntimeException('Template count differs from invoices');
 must(dolibarr_set_const($db,'RR_ANALYTICS_SEED_VERSION','1','chaine',0,$note,$e),$db,'Seed marker');
 must(dolibarr_set_const($db,'RR_ANALYTICS_SEED_DATE',date('Y-m-d',$now),'chaine',0,$note,$e),$db,'Seed date');
 if($dry) { $db->query('ROLLBACK'); $db->transaction_opened=0; echo '[RR-ANALYTICS] DRY RUN rolled back. '; }
 else { if($db->commit()<=0) throw new RuntimeException('Commit failed'); echo '[RR-ANALYTICS] COMMITTED. '; }
 echo json_encode($counts,JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch(Throwable $ex) { $db->query('ROLLBACK'); $db->transaction_opened=0; fwrite(STDERR,'[RR-ANALYTICS] '.$ex->getMessage().PHP_EOL); exit(1); }
finally { if($locked) $db->query('SELECT RELEASE_LOCK('.$rr->quote($lock).')'); }