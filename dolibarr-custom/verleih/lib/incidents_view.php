<?php
if (!defined('DOL_DOCUMENT_ROOT')) exit;
$incidents=$rr->rows('SELECT i.*,a.serial,pr.label,n.serial replacement FROM '.$rr->table('incident').' i JOIN '.$rr->table('line').' l ON l.rowid=i.fk_line JOIN '.$rr->table('asset').' a ON a.rowid=l.fk_asset JOIN '.$p.'product pr ON pr.rowid=a.fk_product LEFT JOIN '.$rr->table('line').' nl ON nl.rowid=i.fk_new_line LEFT JOIN '.$rr->table('asset').' n ON n.rowid=nl.fk_asset WHERE i.entity='.$e.' AND i.fk_booking='.(int)$id.' ORDER BY i.rowid DESC');
print '<section class="rr-section"><h2>Incidencias y sustituciones</h2><p>Registrá el reporte del cliente. Podés recibir primero la unidad para revisión y entregar después un sustituto o el original reparado. La recepción por incidencia mantiene la facturación.</p>';
if($user->hasRight('verleih','ausgeben') && in_array($b->status,array('active','partial'),true)) {
    $reportable=$rr->rows('SELECT l.rowid,CONCAT(pr.label,\' · \',a.serial) label FROM '.$rr->table('line').' l JOIN '.$rr->table('asset').' a ON a.rowid=l.fk_asset JOIN '.$p.'product pr ON pr.rowid=a.fk_product WHERE l.fk_booking='.(int)$id.' AND a.entity='.$e.' AND l.date_out IS NOT NULL AND l.date_return IS NULL AND NOT EXISTS (SELECT 1 FROM '.$rr->table('incident')." i WHERE i.fk_line=l.rowid AND i.status='open')");
    if($reportable) {
        print '<details'.(GETPOSTINT('equipment')?' open':'').'><summary>+ Registrar un reporte</summary>'; rrf('incident',$view,$id);
        print '<p><label class="rr-full-field">Buscar equipo por serie o producto <input type="search" class="rr-equipment-search" placeholder="Escribí parte de la serie o nombre…"></label></p><p><label class="rr-full-field">Equipo '; rrs('line',$reportable); print '</label></p><p><label class="rr-full-field">Falla reportada <textarea required name="reason" maxlength="255" rows="3" placeholder="Describí el daño o mal funcionamiento…"></textarea></label></p>';
        rrb('Registrar incidencia'); print '</details>';
    }
}
if(!$incidents) print '<p class="rr-empty">No hay incidencias registradas para este renting.</p>';
foreach($incidents as $incident) {
    $labels=array('open'=>'Pendiente de recepción','received'=>'Recibido · entrega pendiente','replaced'=>'Equipo sustituido','returned'=>'Devuelto sin sustitución');
    print '<article class="rr-incident"><h3>'.rrh($incident->label.' · '.$incident->serial).' <span class="rr-badge">'.rrh($labels[$incident->status]??$incident->status).'</span></h3><p>'.rrh($incident->reason).'</p><p>Reporte: '.rrh($incident->date_creation).'</p>';
    if($incident->replacement) print '<p>Reemplazo entregado: <strong>'.rrh($incident->replacement).'</strong> · '.rrh($incident->date_resolution).'</p>';
    if($incident->resolution) print '<p>'.rrh($incident->resolution).'</p>';
    if(in_array($incident->status,array('open','received'),true) && $user->hasRight('verleih','ausgeben')) {
        if($incident->status==='open') {
            rrf('incidentreceive',$view,$id);
            print '<input type="hidden" name="line" value="'.(int)$incident->fk_line.'"><p>Se recibe para revisión sin determinar aún su condición. No se detienen las mensualidades.</p>';
            rrb('Recibir por incidencia y dejar entrega pendiente');
        }
        $choices=$rr->replacementCandidates($id,$incident->fk_line);
        if($choices && $b->date_end>=dol_print_date(dol_now(),'%Y-%m-%d')) {
            print '<details><summary>'.($incident->status==='received'?'Entregar sustituto o equipo reparado':'Recibir y sustituir en el mismo momento').'</summary><p>Se ofrecen unidades del mismo producto disponibles durante el resto del contrato. No es una devolución que termine el alquiler.</p>';
            rrf('replace',$view,$id); print '<input type="hidden" name="incident" value="'.(int)$incident->rowid.'"><p><label>Serie de reemplazo '; rrs('replacement',$choices); print '</label></p><p><label class="rr-full-field">Observación de la entrega <textarea required name="note" maxlength="255" rows="3"></textarea></label></p><p><label class="rr-check"><input type="checkbox" name="exchangeconfirmed" value="1" required> Confirmo la recepción del original (ahora o previamente) y la entrega física de la unidad seleccionada.</label></p>';
            rrb('Confirmar sustitución sin cambiar facturación'); print '</details>';
        } else print '<p class="rr-hint">No hay una unidad apta y disponible para este periodo, o el renting ya venció. La entrega queda pendiente. Si esperás al original, el técnico debe revisarlo y devolverlo a la flota antes de entregarlo.</p>';
    }
    if($incident->status==='received' && $user->hasRight('verleih','ausgeben')) {
        print '<details><summary>El cliente ya no desea continuar: devolución definitiva</summary><p>Cancela esta entrega pendiente y detiene las mensualidades vinculadas. No borra facturas anteriores.</p>';
        rrf('incidentfinish',$view,$id);
        print '<input type="hidden" name="incident" value="'.(int)$incident->rowid.'"><label>Motivo <textarea required name="reason" rows="3" maxlength="255"></textarea></label>';
        rrb('Confirmar devolución definitiva'); print '</details>';
    }
    print '</article>';
}
print '</section>';
