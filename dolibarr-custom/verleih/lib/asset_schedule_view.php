<?php
// Read-only schedule of this physical unit, including past and cancelled assignments.
$schedule=$rr->assetBookings(array($id),false);
print '<section><h3>Reservas y contratos de este equipo</h3><p>Las reservas comprometen únicamente el periodo indicado. Podés usar el equipo en otro renting si sus fechas no se cruzan y la unidad está apta y disponible.</p>';
if(!$schedule) {
    print '<p class="rr-hint">Este equipo todavía no tiene reservas registradas.</p>';
} else {
    print '<table class="noborder centpercent"><tr class="liste_titre"><th>Periodo</th><th>Cliente</th><th>Renting</th><th>Contrato</th><th>Situación de esta unidad</th></tr>';
    foreach($schedule as $entry) {
        if($entry->status==='cancelled') $situation='Reserva cancelada';
        elseif($entry->date_return) $situation='Devuelto el '.substr($entry->date_return,0,10);
        elseif($entry->status==='closed') $situation='Finalizado';
        elseif($entry->date_end<$rr->today()) $situation=$entry->date_out?'Devolución vencida':'Reserva vencida · pendiente de resolver';
        elseif($entry->date_out) $situation='Entregado';
        elseif($entry->date_start>$rr->today()) $situation='Reserva futura';
        else $situation='Reserva vigente · pendiente de entrega';
        print '<tr><td>'.rrh($entry->date_start.' → '.$entry->date_end).'</td><td>'.rrh($entry->customer).'</td><td><a href="?view=bookings&id='.(int)$entry->booking_id.'">'.rrh($entry->ref).'</a></td><td><a href="'.DOL_URL_ROOT.'/contrat/card.php?id='.(int)$entry->contract_id.'">'.rrh($entry->contract_ref).'</a></td><td>'.rrh($situation).'</td></tr>';
    }
    print '</table>';
}
print '</section>';
