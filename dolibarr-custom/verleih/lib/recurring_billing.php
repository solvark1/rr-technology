<?php
/* R&R Technology. GPL-3.0-or-later. */
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
require_once __DIR__.'/../class/rrbilling.class.php';

/** Generate due native templates in draft mode only. Returns processed template count. */
function rrRunRecurringBilling($db, $entity, $templateId = 0)
{
    global $user;
    $lock = 'rr-recurring-billing-'.(int) $entity;
    $result = $db->query("SELECT GET_LOCK('".$lock."', 0) acquired");
    if (!$result) { throw new RuntimeException($db->lasterror()); }
    if ((int) $db->fetch_object($result)->acquired !== 1) { return 0; }
    try {
        $sql = 'SELECT rowid FROM '.$db->prefix().'facture_rec WHERE entity='.(int) $entity;
        $sql .= ' AND frequency>0 AND suspended=0 AND auto_validate=0';
        $sql .= ' AND date_when IS NOT NULL';
        $sql .= " AND date_when <= '".$db->idate(dol_now())."'";
        $sql .= ' AND (nb_gen_max=0 OR nb_gen_done<nb_gen_max)';
        if ($templateId > 0) { $sql .= ' AND rowid='.(int) $templateId; }
        $result = $db->query($sql);
        if (!$result) { throw new RuntimeException($db->lasterror()); }
        $ids = array();
        while ($row = $db->fetch_object($result)) { $ids[] = (int) $row->rowid; }
        foreach ($ids as $id) {
            $billing=new RrBilling($db,$user,$entity);
            $maps=$billing->rows('SELECT rb.fk_booking FROM '.$billing->table('billing').' rb JOIN '.$billing->table('booking').' b ON b.rowid=rb.fk_booking WHERE rb.fk_template='.(int)$id.' AND b.entity='.(int)$entity);
            if($maps) {
                $billing->atomic(function()use($billing,$maps,$id,$db){
                    $booking=$billing->booking($maps[0]->fk_booking,true);
                    $map=$billing->one('SELECT state FROM '.$billing->table('billing').' WHERE fk_booking='.(int)$booking->rowid);
                    $template=new FactureRec($db);
                    if($template->fetch($id)<=0) throw new RuntimeException('Plantilla vinculada no encontrada.');
                    if($map->state!=='running' || !in_array($booking->status,array('active','partial'),true) || $template->suspended) return;
                    $source=$billing->source($booking->rowid);
                    if(count($template->lines)!==1 || $template->frequency!=1 || $template->unit_frequency!=='m' || $template->nb_gen_max<1) throw new RuntimeException('Configuración mensual alterada; revisá la plantilla desde Renting.');
                    $tl=$template->lines[0];
                    if((int)$tl->fk_product!==(int)$source->fk_product || $tl->qty<1 || $tl->qty>$source->qty || abs((float)$tl->subprice-(float)$source->subprice)>0.000001 || abs((float)$tl->tva_tx-(float)$source->tva_tx)>0.000001 || abs((float)$tl->remise_percent-(float)$source->remise_percent)>0.000001) throw new RuntimeException('La plantilla no coincide con las condiciones contractuales.');
                    if(!$template->date_when || date('Y-m-d',$template->date_when)>=$booking->date_end) {
                        $billing->pauseBilling($booking->rowid,'Fin del periodo contractual.'); return;
                    }
                    // Always drafts, no mail; hold the booking lock through generation
                    // so a concurrent return cannot race the scheduled invoice.
                    $billing->query('UPDATE '.$db->prefix().'facture_rec SET auto_validate=0,generate_pdf=0 WHERE rowid='.(int)$id);
                    RrBilling::$internal=true;
                    try {
                        if($template->createRecurringInvoices($id)!==0) throw new RuntimeException($template->error.' '.implode('; ',$template->errors));
                    } finally { RrBilling::$internal=false; }
                });
                continue;
            }
            $recurring = new FactureRec($db);
            if ($recurring->createRecurringInvoices($id) !== 0) {
                throw new RuntimeException('Template '.$id.': '.$recurring->error.' '.implode('; ', $recurring->errors));
            }
        }
        return count($ids);
    } finally {
        $db->query("SELECT RELEASE_LOCK('".$lock."')");
    }
}
