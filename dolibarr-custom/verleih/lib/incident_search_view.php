<?php
if (!defined('DOL_DOCUMENT_ROOT')) exit;
$customer=GETPOSTINT('customer');
if($id) { $b=$rr->booking($id); $customer=(int)$b->fk_soc; }
$customers=$rr->rows('SELECT DISTINCT s.rowid,s.nom label FROM '.$rr->table('booking').' b JOIN '.$p."societe s ON s.rowid=b.fk_soc WHERE b.entity=".$e." AND s.entity=".$e." AND b.status IN ('active','partial') ORDER BY s.nom");
print '<section class="rr-section"><header class="rr-section-heading"><h2>Atención de incidencias</h2><p>Elegí el cliente y su renting. Las opciones cargan al seleccionar. Después elegí el equipo que necesita atención.</p></header><form method="get" class="rr-incident-filters"><input type="hidden" name="view" value="incidents"><label>1 · Cliente con renting activo <select name="customer" data-rr-autoload required><option value="">Seleccioná un cliente</option>';
foreach($customers as $c) print '<option value="'.(int)$c->rowid.'"'.($customer===(int)$c->rowid?' selected':'').'>'.rrh($c->label).'</option>';
print '</select></label></form>';
if($customer) {
    $bookings=$rr->rows('SELECT rowid,ref,date_start,date_end FROM '.$rr->table('booking').' WHERE entity='.$e.' AND fk_soc='.(int)$customer." AND status IN ('active','partial') ORDER BY rowid DESC");
    if(!$id && count($bookings)===1) { $id=(int)$bookings[0]->rowid; $b=$rr->booking($id); }
    print '<form method="get" class="rr-incident-filters"><input type="hidden" name="view" value="incidents"><input type="hidden" name="customer" value="'.(int)$customer.'"><label>2 · Renting activo <select name="id" data-rr-autoload required><option value="">Seleccioná un renting</option>';
    foreach($bookings as $r) print '<option value="'.(int)$r->rowid.'"'.($id===(int)$r->rowid?' selected':'').'>'.rrh($r->ref.' · '.$r->date_start.' → '.$r->date_end).'</option>';
    print '</select></label></form>';
    if(!$bookings) print '<p class="rr-empty">Este cliente no tiene rentings en curso.</p>';
}
print '</section>';
if($id) {
    print '<div class="rr-action-bar"><strong>'.rrh($b->ref).' · '.rrh(rrstate($b->status)).'</strong><a href="?view=bookings&id='.(int)$id.'">Ver ficha del renting →</a></div>';
    require __DIR__.'/incidents_view.php';
} else print '<p class="rr-empty">Seleccioná un renting para consultar sus reportes y registrar una incidencia.</p>';
