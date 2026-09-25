<?php
/* R&R Technology adaptation of Verleih (Kim Wittkowski, 2026).
 * GPL-3.0-or-later. */
define('CSRFCHECK_WITH_TOKEN',1);
require '../../main.inc.php';
require_once __DIR__.'/class/rrrenting.class.php';
if (!isModEnabled('verleih') || !empty($user->socid) || !$user->hasRight('verleih','lire')) { accessforbidden(); }
$rr=new RrRenting($db,$user,$conf->entity);
$p=$db->prefix(); $e=(int)$conf->entity;
$view=GETPOST('view','aZ09'); if (!in_array($view,array('assets','bookings','settings','dashboard'),true)) { $view='dashboard'; }
$id=GETPOSTINT('id'); $action=GETPOST('action','aZ09');
function rrh($s) { return dol_escape_htmltag((string)$s); }
function rrf($action,$view,$id=0) {
    print '<form method="post" action="'.rrh($_SERVER['PHP_SELF']).'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="'.rrh($action).'"><input type="hidden" name="view" value="'.rrh($view).'"><input type="hidden" name="id" value="'.(int)$id.'">';
}
function rrs($name,$rows,$placeholder='Selecciona una opción') {
    print '<select required class="flat minwidth200 maxwidth500" name="'.rrh($name).'"><option value="">'.rrh($placeholder).'</option>';
    foreach($rows as $r) { print '<option value="'.(int)$r->rowid.'">'.rrh($r->label).'</option>'; } print '</select>';
}
function rrb($label) { print '<button class="button" type="submit">'.rrh($label).'</button></form>'; }
function rrstate($s) {
    $labels=array('available'=>'Disponible','out'=>'Entregado','review'=>'En revisión','repair'=>'En reparación','sale'=>'Destinado a venta','reserved'=>'Reservado','active'=>'En curso','partial'=>'Devolución parcial','closed'=>'Finalizado','cancelled'=>'Cancelado','good'=>'Bueno','worn'=>'Con desgaste','damaged'=>'Dañado');
    return $labels[$s] ?? $s;
}
if ($action && $_SERVER['REQUEST_METHOD']!=='POST') { accessforbidden(); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        switch($action) {
            case 'setup': $rr->setup(); break;
            case 'enroll': $rr->enroll(GETPOSTINT('product'),GETPOST('serial','alphanohtml'),GETPOST('note','alphanohtml')); $id=0; break;
            case 'reserve':
                $ids=GETPOST('assets','array');
                if (!is_array($ids)) { $ids=array(); }
                $id=$rr->reserveFromContract(GETPOSTINT('soc'),GETPOSTINT('contract'),GETPOSTINT('contractline'),GETPOSTINT('equipmentproduct'),$ids,GETPOST('note','alphanohtml'));
                break;
            case 'checkout': $rr->checkout($id); break;
            case 'cancel': $rr->cancel($id); break;
            case 'receive': $rr->receive($id,GETPOSTINT('line'),GETPOST('condition','aZ09')); break;
            case 'inspect': $rr->inspect($id,GETPOST('destination','aZ09'),GETPOST('condition','aZ09'),GETPOST('note','alphanohtml')); break;
            default: throw new RuntimeException('Acción desconocida.');
        }
        setEventMessages('Operación registrada correctamente.',null,'mesgs');
        header('Location: '.dol_buildpath('/verleih/renting.php',1).'?view='.$view.'&id='.(int)$id); exit;
    } catch(Throwable $ex) { setEventMessages($ex->getMessage(),null,'errors'); }
}
llxHeader('','Renting');
print '<link rel="stylesheet" href="'.dol_buildpath('/verleih/css/renting.css',1).'?v=2">';
print '<script defer src="'.dol_buildpath('/verleih/js/renting.js',1).'?v=3"></script>';
print '<div class="rr-app"><header class="rr-hero"><div><div class="rr-eyebrow">R&R Technology · Gestión de equipos</div><h1>Renting</h1><p>Controlá tus equipos, organizá las entregas y acompañá cada devolución.</p></div><div class="rr-mark" aria-hidden="true"><span class="fa fa-desktop"></span></div></header>';
print '<nav class="rr-nav" aria-label="Secciones de renting">';
foreach(array('dashboard'=>'Resumen','assets'=>'Equipos','bookings'=>'Rentings','settings'=>'Configuración') as $v=>$label) {
    if ($v==='settings' && !$user->hasRight('verleih','configurer')) { continue; }
    print '<a '.($view===$v?'aria-current="page"':'').' href="?view='.$v.'">'.rrh($label).'</a>';
}
print '</nav><div class="rr-content">';
try {
    $configRows=$rr->rows("SELECT * FROM ".$rr->table('config')." WHERE entity=".$e);
    if (!$configRows && $view!=='settings') {
        print '<div class="info">Configura primero los almacenes en la pestaña Configuración.</div>';
    } elseif ($view==='settings') {
        $rr->permission('configurer');
        print '<h2>Separación de venta y renting</h2><p>Los equipos se incorporan desde RR-VENTA. Las entregas y devoluciones trasladan la misma unidad por número de serie; no crean existencias adicionales.</p>';
        if (!$configRows) {
            print '<p>Este asistente crea cinco almacenes operativos sin añadir productos ni existencias.</p>';
            rrf('setup','settings'); rrb('Preparar almacenes');
        } else {
            print '<table class="noborder centpercent"><tr class="liste_titre"><th>Uso</th><th>Almacén</th></tr>';
            foreach(array('sale'=>'Venta','available'=>'Renting disponible','customer'=>'En poder de clientes','review'=>'Revisión de devoluciones','repair'=>'Mantenimiento') as $field=>$label) {
                $w=$rr->one("SELECT ref FROM ".$p."entrepot WHERE rowid=".(int)$configRows[0]->$field." AND entity=".$e);
                print '<tr class="oddeven"><td>'.rrh($label).'</td><td><a href="'.DOL_URL_ROOT.'/product/stock/card.php?id='.(int)$configRows[0]->$field.'">'.rrh($w->ref).'</a></td></tr>';
            }
            print '</table><p>Los almacenes de renting quedan protegidos contra movimientos manuales y salidas de venta. Gestiona sus movimientos desde este módulo.</p>';
        }
        print '<h3>Antes del primer renting</h3><ol><li>Crea el cliente en Terceros.</li><li>Crea el producto físico y activa su gestión por número de serie único.</li><li>Recibe cada unidad en RR-VENTA con su número de serie.</li><li>Crea un servicio de renting y un contrato validado para el cliente, incluyendo ese servicio.</li><li>Incorpora los equipos desde la pestaña Equipos y crea la reserva.</li></ol>';
        print '<div class="rr-hint"><strong>Recordá revisar la facturación.</strong> Configurá una plantilla con el servicio, importe y mensualidades acordadas. Si el cliente termina antes, suspendé también esa plantilla: devolver los equipos no detiene las facturas.</div>';
    } elseif ($view==='assets') {
        if ($id) {
            $a=$rr->asset($id); $product=$rr->one("SELECT ref,label FROM ".$p."product WHERE rowid=".(int)$a->fk_product." AND entity=".$e);
            print '<h2>'.rrh($product->label).' · '.rrh($a->serial).'</h2><p>Estado: <strong>'.rrh(rrstate($a->status)).'</strong> · Condición: '.rrh(rrstate($a->item_condition)).'</p>';
            print '<p><a href="'.DOL_URL_ROOT.'/product/card.php?id='.(int)$a->fk_product.'">Ver producto en Dolibarr</a></p>';
            if ($user->hasRight('verleih','ausgeben') && in_array($a->status,array('available','review','repair'),true)) {
                print '<h3>Revisar equipo</h3>';
                rrf('inspect','assets',$id);
                print '<p><label>Resultado <select required name="destination"><option value="available">Disponible para renting</option><option value="repair">Reparación</option><option value="sale">Trasladar a venta</option></select></label></p>';
                print '<p><label>Condición <select required name="condition"><option value="good">Bueno</option><option value="worn">Con desgaste</option><option value="damaged">Dañado</option></select></label></p>';
                print '<p><label>Observaciones <input required name="note" maxlength="255" class="minwidth300"></label></p>';
                print '<p>Si vuelve a venta, informa al comprador de su uso previo. No se permite trasladar equipos con reservas pendientes.</p>';
                rrb('Registrar revisión');
            }
            print '<h3>Historial</h3><table class="noborder centpercent"><tr class="liste_titre"><th>Fecha</th><th>Evento</th><th>Renting</th><th>Observación</th></tr>';
            $events=$rr->rows("SELECT ev.*,b.ref FROM ".$rr->table('event')." ev LEFT JOIN ".$rr->table('booking')." b ON b.rowid=ev.fk_booking WHERE ev.entity=".$e." AND ev.fk_asset=".(int)$id." ORDER BY ev.rowid DESC LIMIT 100");
            $names=array('enroll'=>'Incorporación','checkout'=>'Entrega','return'=>'Devolución','inspection'=>'Revisión');
            foreach($events as $ev) { print '<tr class="oddeven"><td>'.rrh($ev->date_creation).'</td><td>'.rrh($names[$ev->event]??$ev->event).'</td><td>'.($ev->fk_booking?'<a href="?view=bookings&id='.(int)$ev->fk_booking.'">'.rrh($ev->ref).'</a>':'').'</td><td>'.rrh($ev->note).'</td></tr>'; }
            print '</table><p><a href="?view=assets">Volver a equipos</a></p>';
        } else {
            if ($user->hasRight('verleih','creer')) {
                print '<details'.($_SERVER['REQUEST_METHOD']==='POST'?' open':'').'><summary>+ Incorporar equipo a la flota</summary><p>Seleccioná el producto y la serie de una unidad que ya esté en el almacén de venta.</p>';
                $products=$rr->rows("SELECT rowid,CONCAT(ref,' — ',label) label FROM ".$p."product WHERE entity=".$e." AND fk_product_type=0 AND tobatch=2 ORDER BY ref LIMIT 500");
                rrf('enroll','assets');
                print '<p><label>Producto '; rrs('product',$products); print '</label></p>';
                $series=$rr->rows("SELECT ps.fk_product,pb.batch FROM ".$p."product_batch pb JOIN ".$p."product_stock ps ON ps.rowid=pb.fk_product_stock JOIN ".$p."product pr ON pr.rowid=ps.fk_product WHERE pr.entity=".$e." AND pr.fk_product_type=0 AND pr.tobatch=2 AND ps.fk_entrepot=".(int)$configRows[0]->sale." AND pb.qty=1 AND NOT EXISTS (SELECT 1 FROM ".$rr->table('asset')." a WHERE a.entity=".$e." AND a.fk_product=ps.fk_product AND a.serial=pb.batch AND a.status<>'sale') ORDER BY pb.batch");
                print '<p><label>Número de serie <input required name="serial" list="rr-series" autocomplete="off" maxlength="128" class="minwidth200" placeholder="Buscá o elegí una serie" aria-describedby="rr-series-help"></label></p><datalist id="rr-series">';
                foreach($series as $serial) { print '<option data-product="'.(int)$serial->fk_product.'" value="'.rrh($serial->batch).'"></option>'; }
                print '</datalist><p id="rr-series-help" class="rr-hint" aria-live="polite">Elegí el producto para consultar sus series en el almacén de venta. La disponibilidad se vuelve a comprobar al guardar.</p><p><label>Observación <input name="note" maxlength="255" class="minwidth300"></label></p>';
                rrb('Trasladar de venta a renting');
                print '</details>';
            }
            print '<h2>Equipos registrados</h2><table class="noborder centpercent"><tr class="liste_titre"><th>Producto</th><th>Serie</th><th>Estado</th><th>Condición</th></tr>';
            $assets=$rr->rows("SELECT a.*,p.label FROM ".$rr->table('asset')." a JOIN ".$p."product p ON p.rowid=a.fk_product WHERE a.entity=".$e." ORDER BY a.rowid DESC LIMIT 500");
            foreach($assets as $a) { print '<tr class="oddeven"><td>'.rrh($a->label).'</td><td><a href="?view=assets&id='.(int)$a->rowid.'">'.rrh($a->serial).'</a></td><td>'.rrh(rrstate($a->status)).'</td><td>'.rrh(rrstate($a->item_condition)).'</td></tr>'; } print '</table>';
        }
    } elseif ($view==='bookings') {
        if ($id) {
            $b=$rr->booking($id); $soc=$rr->one("SELECT nom FROM ".$p."societe WHERE rowid=".(int)$b->fk_soc." AND entity=".$e);
            print '<h2>'.rrh($b->ref).' · '.rrh(rrstate($b->status)).'</h2><p>Cliente: <a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.(int)$b->fk_soc.'">'.rrh($soc->nom).'</a></p>';
            $binding=$rr->rows("SELECT cl.*,pr.label FROM ".$rr->table('contract_link')." cl JOIN ".$p."product pr ON pr.rowid=cl.fk_product WHERE cl.fk_booking=".(int)$id);
            if ($binding) {
                print '<p class="rr-hint"><strong>Condiciones tomadas del contrato:</strong> '.(int)$binding[0]->qty.' unidades de '.rrh($binding[0]->label).'. La línea contractual queda protegida mientras este renting esté reservado o en curso.</p>';
            } else {
                print '<p class="rr-hint">Renting anterior a la vinculación por línea: conserva sus condiciones originales. No se ha reasignado automáticamente a una línea del contrato.</p>';
            }
            print '<p>Periodo: '.rrh($b->date_start).' → '.rrh($b->date_end).' · <a href="'.DOL_URL_ROOT.'/contrat/card.php?id='.(int)$b->fk_contract.'">Ver contrato y servicio facturable</a></p><p>'.rrh($b->note).'</p>';
            if ($user->hasRight('facture','creer')) {
                print '<p><a class="butAction" href="'.DOL_URL_ROOT.'/compta/facture/card.php?action=create&origin=contrat&originid='.(int)$b->fk_contract.'&socid='.(int)$b->fk_soc.'">Preparar factura del contrato</a> <a href="'.DOL_URL_ROOT.'/compta/facture/invoicetemplate_list.php">Plantillas de facturación recurrente</a></p>';
                print '<p class="opacitymedium">Configura una plantilla por renting con el servicio y los periodos acordados. Al devolver los equipos anticipadamente, suspende también su plantilla.</p>';
            }
            if ($b->status==='reserved') {
                if ($user->hasRight('verleih','ausgeben')) { rrf('checkout','bookings',$id); rrb('Registrar entrega de todos los equipos'); }
                if ($user->hasRight('verleih','creer')) { rrf('cancel','bookings',$id); rrb('Cancelar reserva'); }
            }
            print '<table class="noborder centpercent"><tr class="liste_titre"><th>Equipo</th><th>Entrega</th><th>Devolución</th><th>Acción</th></tr>';
            $lines=$rr->rows("SELECT l.*,a.serial,p.label FROM ".$rr->table('line')." l JOIN ".$rr->table('asset')." a ON a.rowid=l.fk_asset JOIN ".$p."product p ON p.rowid=a.fk_product WHERE l.fk_booking=".(int)$id." AND a.entity=".$e);
            foreach($lines as $l) {
                print '<tr class="oddeven"><td><a href="?view=assets&id='.(int)$l->fk_asset.'">'.rrh($l->label.' · '.$l->serial).'</a></td><td>'.rrh($l->date_out).'</td><td>'.rrh($l->date_return).'</td><td>';
                if ($l->date_out && !$l->date_return && $user->hasRight('verleih','ausgeben')) {
                    rrf('receive','bookings',$id);
                    print '<input type="hidden" name="line" value="'.(int)$l->rowid.'"><select name="condition"><option value="good">Bueno</option><option value="worn">Con desgaste</option><option value="damaged">Dañado</option></select>';
                    rrb('Recibir y enviar a revisión');
                } print '</td></tr>';
            } print '</table><p><a href="?view=bookings">Volver a rentings</a></p>';
        } else {
            if ($user->hasRight('verleih','creer')) {
                print '<details'.($_SERVER['REQUEST_METHOD']==='POST'?' open':'').'><summary>+ Crear una reserva</summary><p>Elegí el cliente y una línea de su contrato. Las fechas y la cantidad se toman de esa línea; seleccioná las unidades físicas que la cumplirán.</p>';
                rrf('reserve','bookings');
                print '<p><label>Cliente '; rrs('soc',$rr->rows("SELECT rowid,nom label FROM ".$p."societe WHERE entity=".$e." AND client IN (1,3) AND status=1 ORDER BY nom LIMIT 500")); print '</label></p>';
                print '<p><label>Contrato validado <select required name="contract"><option value="">Seleccioná un contrato</option>';
                foreach($rr->rows("SELECT c.rowid,c.ref,c.fk_soc,s.nom FROM ".$p."contrat c JOIN ".$p."societe s ON s.rowid=c.fk_soc WHERE c.entity=".$e." AND c.statut=1 ORDER BY c.rowid DESC LIMIT 500") as $contract) {
                    print '<option value="'.(int)$contract->rowid.'" data-soc="'.(int)$contract->fk_soc.'">'.rrh($contract->ref.' — '.$contract->nom).'</option>';
                }
                print '</select></label></p><p><label>Línea de servicio <select required name="contractline"><option value="">Seleccioná una línea</option>';
                foreach($rr->rows("SELECT d.rowid,d.fk_contrat,d.qty,d.date_ouverture_prevue,d.date_fin_validite,pr.label,c.ref FROM ".$p."contratdet d JOIN ".$p."contrat c ON c.rowid=d.fk_contrat JOIN ".$p."product pr ON pr.rowid=d.fk_product WHERE c.entity=".$e." AND c.statut=1 AND pr.entity=".$e." AND pr.fk_product_type=1 AND pr.tosell=1 ORDER BY d.rowid") as $cl) {
                    print '<option value="'.(int)$cl->rowid.'" data-contract="'.(int)$cl->fk_contrat.'" data-qty="'.rrh($cl->qty).'" data-start="'.rrh(substr((string)$cl->date_ouverture_prevue,0,10)).'" data-end="'.rrh(substr((string)$cl->date_fin_validite,0,10)).'">'.rrh($cl->label.' · '.$cl->qty.' unidades · '.$cl->ref.' · línea '.$cl->rowid).'</option>';
                }
                print '</select></label></p><p class="rr-hint" id="rr-contract-summary" aria-live="polite">Seleccioná una línea para ver el periodo y la cantidad contratada.</p>';
                print '<p><label>Producto físico que cumple el servicio '; rrs('equipmentproduct',$rr->rows("SELECT rowid,CONCAT(ref,' — ',label) label FROM ".$p."product WHERE entity=".$e." AND fk_product_type=0 AND tobatch=2 ORDER BY ref")); print '</label></p><p>Una unidad del servicio equivale a una unidad física. Usá líneas separadas para productos diferentes (PCs, monitores, tarjetas, etc.).</p>';
                print '<p>Selecciona equipos; al guardar se comprueba la disponibilidad para el periodo completo. Las fechas de inicio y fin se incluyen en la reserva.</p><div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>Elegir</th><th>Producto</th><th>Serie</th><th>Situación actual</th></tr>';
                foreach($rr->rows("SELECT a.*,p.label FROM ".$rr->table('asset')." a JOIN ".$p."product p ON p.rowid=a.fk_product WHERE a.entity=".$e." AND a.status IN ('available','out') ORDER BY p.label,a.serial LIMIT 500") as $a) {
                    $busy=$rr->rows("SELECT b.date_start,b.date_end FROM ".$rr->table('line')." l JOIN ".$rr->table('booking')." b ON b.rowid=l.fk_booking WHERE l.fk_asset=".(int)$a->rowid." AND b.entity=".$e." AND b.status IN ('reserved','active','partial') AND l.date_return IS NULL");
                    print '<tr class="oddeven" data-product="'.(int)$a->fk_product.'" data-state="'.rrh($a->status).'" data-busy="'.rrh(json_encode($busy)).'"><td><input type="checkbox" name="assets[]" value="'.(int)$a->rowid.'"></td><td>'.rrh($a->label).'</td><td>'.rrh($a->serial).'</td><td>'.rrh(rrstate($a->status)).'</td></tr>';
                }
                print '</table></div><p><label>Observación <input name="note" maxlength="255" class="minwidth300"></label></p>'; rrb('Crear reserva');
                print '</details>';
            }
            print '<h2>Operaciones de renting</h2><table class="noborder centpercent"><tr class="liste_titre"><th>Referencia</th><th>Cliente</th><th>Periodo</th><th>Estado</th></tr>';
            foreach($rr->rows("SELECT b.*,s.nom FROM ".$rr->table('booking')." b JOIN ".$p."societe s ON s.rowid=b.fk_soc WHERE b.entity=".$e." ORDER BY b.rowid DESC LIMIT 500") as $b) {
                print '<tr class="oddeven"><td><a href="?view=bookings&id='.(int)$b->rowid.'">'.rrh($b->ref).'</a></td><td>'.rrh($b->nom).'</td><td>'.rrh($b->date_start.' → '.$b->date_end).'</td><td>'.rrh(rrstate($b->status)).'</td></tr>';
            } print '</table>';
        }
    } else {
        $counts=array('available'=>0,'out'=>0,'review'=>0,'repair'=>0);
        foreach($rr->rows("SELECT status,COUNT(*) qty FROM ".$rr->table('asset')." WHERE entity=".$e." GROUP BY status") as $s) { $counts[$s->status]=(int)$s->qty; }
        print '<h2>Tu flota, de un vistazo</h2><div class="rr-stats">';
        foreach(array('available'=>array('En almacén para renting','Verificá las reservas por fecha'),'out'=>array('Con clientes','Equipos entregados'),'review'=>array('Por revisar','Devoluciones recibidas'),'repair'=>array('En reparación','Fuera de disponibilidad')) as $key=>$text) {
            print '<div class="rr-stat"><span>'.rrh($text[0]).'</span><strong>'.$counts[$key].'</strong><small>'.rrh($text[1]).'</small></div>';
        }
        print '</div>';
        print '<p class="rr-hint">Estar en almacén no garantiza disponibilidad para cualquier fecha. Al crear una reserva, comprobamos el periodo completo de cada equipo.</p>';
        print '<h2>Próximas entregas y devoluciones pendientes</h2><table class="noborder centpercent"><tr class="liste_titre"><th>Renting</th><th>Cliente</th><th>Inicio</th><th>Fin</th><th>Situación</th></tr>';
        foreach($rr->rows("SELECT b.*,s.nom FROM ".$rr->table('booking')." b JOIN ".$p."societe s ON s.rowid=b.fk_soc WHERE b.entity=".$e." AND b.status IN ('reserved','active','partial') ORDER BY b.date_end LIMIT 100") as $b) {
            $late=$b->date_end<dol_print_date(dol_now(),'%Y-%m-%d');
            print '<tr class="oddeven"><td><a href="?view=bookings&id='.(int)$b->rowid.'">'.rrh($b->ref).'</a></td><td>'.rrh($b->nom).'</td><td>'.rrh($b->date_start).'</td><td>'.rrh($b->date_end).'</td><td>'.rrh($late?'Vencido — revisar':rrstate($b->status)).'</td></tr>';
        } print '</table>';
        print '<p><a class="butAction" href="?view=assets">Incorporar equipos</a> <a class="butAction" href="?view=bookings">Gestionar renting</a></p>';
    }
} catch(Throwable $ex) { print '<div class="error">'.rrh($ex->getMessage()).'</div>'; }
print '</div></div>'; llxFooter(); $db->close();
