<?php
if (!defined('DOL_DOCUMENT_ROOT')) exit;
print '<section class="rr-section"><h2>Revisión y reparación</h2><p>Revisá las unidades que regresan y registrá el diagnóstico. Una unidad reparada puede volver directamente a la flota: no necesita pasar por venta ni ser nueva.</p><p class="rr-hint">Para retirarla de la flota, abrí su ficha y elegí «Trasladar a venta». No se permite si tiene reservas pendientes. Se conserva el historial de uso.</p>';
$queue=$rr->rows('SELECT a.*,pr.label FROM '.$rr->table('asset').' a JOIN '.$p.'product pr ON pr.rowid=a.fk_product WHERE a.entity='.$e." AND a.status IN ('review','repair') ORDER BY a.status,a.rowid");
if(!$queue) print '<p class="rr-empty">No hay equipos pendientes de revisión o reparación.</p>';
foreach($queue as $asset) {
    $last=$rr->rows('SELECT ev.note,ev.date_creation,u.login FROM '.$rr->table('event').' ev LEFT JOIN '.$p.'user u ON u.rowid=ev.fk_user WHERE ev.entity='.$e.' AND ev.fk_asset='.(int)$asset->rowid.' ORDER BY ev.rowid DESC LIMIT 1');
    print '<article class="rr-incident"><h3><a href="?view=assets&id='.(int)$asset->rowid.'">'.rrh($asset->label.' · '.$asset->serial).'</a> <span class="rr-badge '.rrh($asset->status).'">'.rrh(rrstate($asset->status)).'</span></h3><p>Condición registrada: '.rrh(rrstate($asset->item_condition)).'</p>';
    if($last) print '<p>Última actuación: '.rrh($last[0]->note ?: 'Sin observación').' · '.rrh($last[0]->login).' · '.rrh($last[0]->date_creation).'</p>';
    $future=$rr->one('SELECT COUNT(*) qty FROM '.$rr->table('line').' l JOIN '.$rr->table('booking')." b ON b.rowid=l.fk_booking WHERE l.fk_asset=".(int)$asset->rowid." AND b.entity=".$e." AND b.status='reserved' AND l.date_return IS NULL");
    if($future->qty) print '<p class="rr-hint">Esta unidad tiene '.(int)$future->qty.' reserva(s) pendiente(s). Coordiná su reparación antes de la próxima entrega.</p>';
    if($user->hasRight('verleih','ausgeben')) {
        print '<details><summary>Registrar diagnóstico y destino</summary>'; rrf('inspect','repairs',$asset->rowid);
        print '<p><label>Resultado <select required name="destination"><option value="">Seleccioná un destino</option><option value="available">Reintegrar a la flota de renting</option><option value="repair">Enviar o mantener en reparación</option><option value="sale">Retirar de la flota y trasladar a venta</option></select></label></p><p><label>Condición después de la revisión <select required name="condition"><option value="">Seleccioná una condición</option><option value="good">Bueno</option><option value="worn">Con desgaste, apto para uso</option><option value="damaged">Dañado / falla pendiente</option></select></label></p><p><label class="rr-full-field">Diagnóstico y trabajo realizado <textarea required name="note" rows="3" maxlength="255" placeholder="Indicá las pruebas, reparación y motivo del destino…"></textarea></label></p>';
        rrb('Guardar revisión y mover equipo'); print '</details>';
    }
    print '</article>';
}
print '</section><section class="rr-section"><h2>Incidencias pendientes de resolver</h2><p>Estas incidencias se resuelven desde el renting. Un reporte por sí solo no devuelve el equipo ni pausa su facturación.</p><table class="noborder centpercent"><tr><th>Renting</th><th>Serie</th><th>Falla reportada</th></tr>';
foreach($rr->rows('SELECT i.reason,b.rowid,b.ref,a.serial FROM '.$rr->table('incident').' i JOIN '.$rr->table('booking').' b ON b.rowid=i.fk_booking JOIN '.$rr->table('line').' l ON l.rowid=i.fk_line JOIN '.$rr->table('asset')." a ON a.rowid=l.fk_asset WHERE i.entity=".$e." AND i.status IN ('open','received') ORDER BY i.rowid") as $incident) {
    print '<tr><td><a href="?view=bookings&id='.(int)$incident->rowid.'">'.rrh($incident->ref).'</a></td><td>'.rrh($incident->serial).'</td><td>'.rrh($incident->reason).'</td></tr>';
}
print '</table></section>';
