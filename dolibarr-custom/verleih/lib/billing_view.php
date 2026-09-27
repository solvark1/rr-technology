<?php
/* Included only from the authenticated renting card. */
if (!defined('DOL_DOCUMENT_ROOT')) exit;
if ($user->hasRight('facture','lire')) {
    $billing=new RrBilling($db,$user,$e);
    $maps=$rr->rows('SELECT rb.*,r.nb_gen_done,r.nb_gen_max,r.date_when,r.suspended FROM '.$rr->table('billing').' rb LEFT JOIN '.$p.'facture_rec r ON r.rowid=rb.fk_template AND r.entity='.$e.' WHERE rb.fk_booking='.(int)$id);
    if($maps) print '<div class="rr-finance-layout">';
    print '<section class="rr-section rr-finance-card" aria-labelledby="rr-billing-heading"><header class="rr-section-heading"><h2 id="rr-billing-heading"><span class="fa fa-file-invoice rr-heading-icon" aria-hidden="true"></span> Facturación del renting</h2><p>Gestioná la programación de los próximos cobros.</p></header>';
    if ($maps) {
        $map=$maps[0];
        $states=array('waiting'=>'Preparada · espera la entrega','running'=>'Activa','paused'=>'Pausada');
        $state=$states[$map->state]??$map->state;
        if($map->nb_gen_max && $map->nb_gen_done >= $map->nb_gen_max) $state='Completada';
        print '<div class="rr-billing-summary"><h3>Programación mensual</h3><span class="rr-badge">'.rrh($state).'</span><p class="rr-billing-count"><strong>'.(int)$map->nb_gen_done.'</strong> de '.(int)$map->nb_gen_max.' mensualidades generadas</p><p>Próxima fecha<br><strong>'.rrh($state==='Completada'?'Sin mensualidades pendientes':substr((string)$map->date_when,0,10)).'</strong></p>';
        if($map->reason) print '<p class="rr-hint">'.rrh($map->reason).'</p>';
        print '<p><a href="'.DOL_URL_ROOT.'/compta/facture/card-rec.php?id='.(int)$map->fk_template.'">Ver plantilla vinculada</a></p>';
        ob_start();
        if($user->hasRight('facture','creer') && $user->hasRight('verleih','creer') && in_array($b->status,array('reserved','active','partial'),true) && $map->nb_gen_done<$map->nb_gen_max) {
            if($map->state!=='paused') {
                print '<details class="rr-billing-actions"'.($action==='billingpause'?' open':'').'><summary>Pausar facturación</summary><p id="rr-pause-help">Detiene las mensualidades futuras. Las facturas ya generadas se conservan.</p>';
                rrf('billingpause','bookings',$id);
                print '<p><label class="rr-full-field">Motivo de la pausa <textarea required name="reason" maxlength="255" rows="4" aria-describedby="rr-pause-help" placeholder="Explicá el motivo o el acuerdo con el cliente…">'.rrh($action==='billingpause'?GETPOST('reason','alphanohtml'):'').'</textarea></label></p>'; rrb('Pausar mensualidades futuras');
                print '</details>';
            } elseif(in_array($b->status,array('active','partial'),true)) {
                print '<details><summary>Revisar y reanudar facturación</summary><p>Solo cambia las mensualidades futuras. No recalcula facturas anteriores ni prorratea días. Una fecha posterior omite los periodos pendientes anteriores a esa fecha: revisalos antes de confirmar.</p>';
                rrf('billingresume','bookings',$id);
                print '<p><label>Próxima mensualidad <input type="date" required name="nextdate" min="'.rrh(max(dol_print_date(dol_now(),'%Y-%m-%d'),substr((string)$map->date_when,0,10))).'"></label></p><p><label>Cantidad a facturar <select name="adjust"><option value="0">Mantener la cantidad actualmente programada</option><option value="1">Ajustar a los equipos que siguen entregados</option></select></label></p><p><label class="rr-full-field">Motivo / acuerdo con el cliente <textarea name="reason" required maxlength="255" rows="4" placeholder="Describí el ajuste acordado…"></textarea></label></p>';
                rrb('Confirmar ajuste y reanudar'); print '</details>';
            }
        }
        $billingActions=ob_get_clean();
        print $billingActions.'</div></section><section class="rr-section rr-invoices-card" aria-labelledby="rr-invoices-heading"><header class="rr-section-heading"><h2 id="rr-invoices-heading"><span class="fa fa-file-invoice rr-heading-icon" aria-hidden="true"></span> Facturas generadas</h2><p>Abrí una factura para revisarla, validarla o registrar su pago.</p></header>';
        $invoices=$rr->rows('SELECT rowid,ref,datef,total_ttc,fk_statut,paye FROM '.$p.'facture WHERE entity='.$e.' AND fk_fac_rec_source='.(int)$map->fk_template.' ORDER BY rowid DESC');
        print '<table class="noborder centpercent"><tr><th>Factura</th><th>Fecha</th><th>Total</th><th>Estado</th></tr>';
        foreach($invoices as $invoice) {
            $status=$invoice->fk_statut==0?'Borrador':($invoice->paye?'Pagada':($invoice->fk_statut==3?'Abandonada':'Validada · revisar saldo'));
            print '<tr><td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int)$invoice->rowid.'">'.rrh($invoice->ref).'</a></td><td>'.rrh(substr((string)$invoice->datef,0,10)).'</td><td>'.price($invoice->total_ttc).' '.rrh($conf->currency).'</td><td><span class="rr-badge">'.rrh($status).'</span></td></tr>';
        }
        if(!$invoices) print '<tr><td colspan="4" class="rr-empty">Todavía no hay facturas. Aparecerán aquí cuando corresponda generar una mensualidad después de la entrega.</td></tr>';
        print '</table>';
    } elseif($binding && in_array($b->status,array('reserved','active'),true) && $user->hasRight('facture','creer') && $user->hasRight('verleih','creer')) {
        $source=$billing->source($id); $periods=$billing->periods($b->date_start,$b->date_end);
        print '<p class="rr-hint">'.(int)$source->qty.' unidades × '.price($source->subprice).' '.rrh($conf->currency).' por unidad al mes · IVA '.rrh($source->tva_tx).'% · descuento '.rrh($source->remise_percent).'%.<br>Se programarán '.$periods.' mensualidades desde '.rrh($b->date_start).', solo después de la entrega. Se recuperan fechas pendientes; no se prorratean meses incompletos.</p>';
        rrf('billingcreate','bookings',$id);
        print '<p><label>Condiciones de pago '; rrs('paymentterms',$rr->rows('SELECT rowid,libelle label FROM '.$p.'c_payment_term WHERE active=1 ORDER BY sortorder,rowid')); print '</label></p>';
        print '<p><label><input type="checkbox" name="confirmed" value="1" required> Confirmo que la tarifa del contrato es mensual y que no hay otra plantilla o factura que duplique estos periodos.</label></p>';
        rrb('Crear programación mensual desde el contrato');
    } else {
        print '<p>No hay programación vinculada. Se puede preparar en un renting reservado o en curso que tenga una línea contractual vinculada.</p>';
    }
    print '</section>';
    if($maps) print '</div>';
}
