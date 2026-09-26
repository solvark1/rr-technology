<?php
/* R&R Technology renting workflow, derived from the Verleih checkout/return model.
 * Original Verleih: Copyright (C) 2026 Kim Wittkowski.
 * Modifications: R&R Technology, 2026. GPL-3.0-or-later.
 */
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

class RrRenting
{
    public static $moving = false;
    public $db;
    public $entity;
    public $user;
    public function __construct($db, $user, $entity) {
        $this->db = $db; $this->user = $user; $this->entity = (int) $entity;
    }
    public function table($name) { return $this->db->prefix().'rr_renting_'.$name; }
    public function quote($value) { return "'".$this->db->escape((string) $value)."'"; }
    public function query($sql) {
        $r = $this->db->query($sql);
        if (!$r) { throw new RuntimeException($this->db->lasterror()); }
        return $r;
    }
    public function rows($sql) {
        $r = $this->query($sql); $rows = array();
        while ($o = $this->db->fetch_object($r)) { $rows[] = $o; }
        return $rows;
    }
    public function one($sql) {
        $rows = $this->rows($sql);
        if (!$rows) { throw new RuntimeException('Registro no encontrado o no disponible para esta entidad.'); }
        return $rows[0];
    }
    public function permission($right) {
        if (!empty($this->user->socid) || !$this->user->hasRight('verleih', $right)) {
            throw new RuntimeException('No tienes permiso para realizar esta operación.');
        }
    }
    public function atomic($fn) {
        $this->db->begin();
        $savepoint='rr_'.bin2hex(random_bytes(6));
        $this->query('SAVEPOINT '.$savepoint);
        try {
            $result = $fn();
            $this->query('RELEASE SAVEPOINT '.$savepoint);
            if ($this->db->commit() <= 0) { throw new RuntimeException('No se pudo confirmar la operación.'); }
            return $result;
        } catch (Throwable $e) {
            // Dolibarr's nested rollback only decrements a counter; restore our own writes too.
            $this->db->query('ROLLBACK TO SAVEPOINT '.$savepoint);
            $this->db->rollback();
            throw $e;
        }
    }
    public function config($lock = false) {
        return $this->one("SELECT * FROM ".$this->table('config')." WHERE entity=".$this->entity.($lock ? " FOR UPDATE" : ""));
    }
    public function event($name, $asset = 0, $booking = 0, $note = '') {
        $this->query("INSERT INTO ".$this->table('event')." (entity,fk_asset,fk_booking,event,note,fk_user,date_creation) VALUES (".$this->entity.",".(int)$asset.",".(int)$booking.",".$this->quote($name).",".$this->quote(mb_substr($note,0,255)).",".(int)$this->user->id.",".$this->quote($this->db->idate(dol_now())).")");
    }
    public function setup() {
        $this->permission('configurer');
        return $this->atomic(function () {
            if ($this->rows("SELECT * FROM ".$this->table('config')." WHERE entity=".$this->entity." FOR UPDATE")) { return; }
            $ids = array();
            foreach (array('VENTA'=>'Unidades disponibles para venta', 'RENTING'=>'Equipos disponibles para renting', 'CLIENTES'=>'Equipos de R&R entregados a clientes', 'REVISION'=>'Devoluciones pendientes de revisión', 'REPARACION'=>'Equipos en mantenimiento') as $code=>$label) {
                $ref = 'RR-'.$code;
                $existing = $this->rows("SELECT rowid FROM ".$this->db->prefix()."entrepot WHERE entity=".$this->entity." AND ref=".$this->quote($ref)." AND statut=1");
                if ($existing) { $ids[]=(int)$existing[0]->rowid; continue; }
                $w = new Entrepot($this->db); $w->ref=$ref; $w->label=$ref; $w->description=$label; $w->statut=1; $w->entity=$this->entity;
                $id=$w->create($this->user);
                if ($id<=0) { throw new RuntimeException('No se pudo crear el almacén '.$ref.': '.$w->error); }
                $ids[]=$id;
            }
            $this->query("INSERT INTO ".$this->table('config')." (entity,sale,available,customer,review,repair) VALUES (".$this->entity.",".implode(',', $ids).")");
            $this->event('setup');
        });
    }
    public function date($date) {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d')!==$date) { throw new RuntimeException('Fecha inválida.'); }
        return $date;
    }
    public function asset($id, $lock = false) {
        return $this->one("SELECT * FROM ".$this->table('asset')." WHERE rowid=".(int)$id." AND entity=".$this->entity.($lock ? " FOR UPDATE" : ""));
    }
    public function fleet($category=0) {
        $p=$this->db->prefix(); $where='';
        if($category>0) {
            $this->one('SELECT rowid FROM '.$p.'categorie WHERE rowid='.(int)$category.' AND entity='.$this->entity.' AND type=0');
            $where=' AND EXISTS (SELECT 1 FROM '.$p.'categorie_product cp WHERE cp.fk_product=a.fk_product AND cp.fk_categorie='.(int)$category.')';
        } elseif($category===-1) {
            $where=' AND NOT EXISTS (SELECT 1 FROM '.$p.'categorie_product cp JOIN '.$p.'categorie c ON c.rowid=cp.fk_categorie WHERE cp.fk_product=a.fk_product AND c.entity='.$this->entity.' AND c.type=0)';
        }
        return $this->rows('SELECT a.*,p.label FROM '.$this->table('asset').' a JOIN '.$p.'product p ON p.rowid=a.fk_product WHERE a.entity='.$this->entity.$where.' ORDER BY a.rowid DESC LIMIT 500');
    }
    public function booking($id, $lock=false) {
        return $this->one("SELECT * FROM ".$this->table('booking')." WHERE rowid=".(int)$id." AND entity=".$this->entity.($lock ? " FOR UPDATE" : ""));
    }
    public function transfer($product, $serial, $from, $to, $label) {
        // Caller holds the entity configuration lock and wraps both native movements in a transaction.
        $p = $this->db->prefix();
        $this->one("SELECT rowid FROM ".$p."product WHERE rowid=".(int)$product." AND entity=".$this->entity." FOR UPDATE");
        foreach (array($from,$to) as $warehouse) {
            $this->one("SELECT rowid FROM ".$p."entrepot WHERE rowid=".(int)$warehouse." AND entity=".$this->entity." AND statut=1");
        }
        $batch=$this->one("SELECT pb.rowid,pb.qty FROM ".$p."product_batch pb JOIN ".$p."product_stock ps ON ps.rowid=pb.fk_product_stock WHERE ps.fk_product=".(int)$product." AND ps.fk_entrepot=".(int)$from." AND pb.batch=".$this->quote($serial)." FOR UPDATE");
        if ((float)$batch->qty !== 1.0) { throw new RuntimeException('El número de serie debe tener exactamente una unidad en el almacén de origen.'); }
        self::$moving=true;
        try {
            $out=new MouvementStock($this->db); $out->origin_type='rr_renting';
            $in=new MouvementStock($this->db); $in->origin_type='rr_renting';
            // Disable recursive component movements: each enrolled asset is one serialized unit.
            if ($out->_create($this->user,$product,$from,-1,2,0,$label,'', '',0,0,$serial,false,$batch->rowid,1,1)<0) {
                throw new RuntimeException('Error al retirar la unidad: '.$out->error.' '.implode('; ', $out->errors));
            }
            if ($in->_create($this->user,$product,$to,1,0,0,$label,'','',0,0,$serial,false,0,1,1)<0) {
                throw new RuntimeException('Error al ingresar la unidad: '.$in->error.' '.implode('; ', $in->errors));
            }
        } finally { self::$moving=false; }
    }
    public function enroll($product, $serial, $note='') {
        $this->permission('creer'); $serial=trim($serial);
        if ($serial==='' || mb_strlen($serial)>128) { throw new RuntimeException('Indica un número de serie válido.'); }
        return $this->atomic(function () use ($product,$serial,$note) {
            $c=$this->config(true);
            $p=$this->one("SELECT rowid,tobatch AS status_batch,fk_product_type FROM ".$this->db->prefix()."product WHERE rowid=".(int)$product." AND entity=".$this->entity." FOR UPDATE");
            if ((int)$p->fk_product_type!==0 || (int)$p->status_batch!==2) { throw new RuntimeException('Selecciona un producto físico con gestión por número de serie único.'); }
            $old=$this->rows("SELECT * FROM ".$this->table('asset')." WHERE entity=".$this->entity." AND fk_product=".(int)$product." AND serial=".$this->quote($serial)." FOR UPDATE");
            if ($old && $old[0]->status!=='sale') { throw new RuntimeException('El equipo ya pertenece al renting.'); }
            // Do not take quantities already promised in validated customer orders.
            $stock=$this->one("SELECT COALESCE(SUM(reel),0) qty FROM ".$this->db->prefix()."product_stock WHERE fk_product=".(int)$product." AND fk_entrepot=".(int)$c->sale);
            $pending=$this->one("SELECT COALESCE(SUM(d.qty),0) qty FROM ".$this->db->prefix()."commandedet d JOIN ".$this->db->prefix()."commande o ON o.rowid=d.fk_commande WHERE o.entity=".$this->entity." AND o.fk_statut IN (1,2) AND d.fk_product=".(int)$product);
            if ((float)$stock->qty - (float)$pending->qty < 1) { throw new RuntimeException('Stock de venta comprometido en pedidos abiertos. Completa o cierra esos pedidos antes de trasladarlo.'); }
            $this->transfer($product,$serial,$c->sale,$c->available,'Renting: incorporación de '.$serial);
            if ($old) {
                $id=(int)$old[0]->rowid;
                $this->query("UPDATE ".$this->table('asset')." SET status='available',fk_warehouse=".(int)$c->available." WHERE rowid=".$id);
            } else {
                $this->query("INSERT INTO ".$this->table('asset')." (entity,fk_product,serial,status,item_condition,fk_warehouse,note,date_creation) VALUES (".$this->entity.",".(int)$product.",".$this->quote($serial).",'available','good',".(int)$c->available.",".$this->quote(mb_substr($note,0,255)).",".$this->quote($this->db->idate(dol_now())).")");
                $id=$this->db->last_insert_id($this->table('asset'));
            }
            $this->event('enroll',$id,0,$note); return $id;
        });
    }
    public function reserveFromContract($soc,$contract,$lineId,$product,array $assets,$note='') {
        $this->permission('creer');
        return $this->atomic(function () use ($soc,$contract,$lineId,$product,$assets,$note) {
            // Lock the parent first, as native contract edits do.
            $this->one("SELECT rowid FROM ".$this->db->prefix()."contrat WHERE rowid=".(int)$contract." AND entity=".$this->entity." AND fk_soc=".(int)$soc." AND statut=1 FOR UPDATE");
            $line=$this->one("SELECT * FROM ".$this->db->prefix()."contratdet WHERE rowid=".(int)$lineId." AND fk_contrat=".(int)$contract." FOR UPDATE");
            $qty=(float)$line->qty;
            if ($qty<1 || $qty!=floor($qty)) { throw new RuntimeException('La línea del contrato debe indicar una cantidad entera de equipos mayor que cero.'); }
            $start=substr((string)$line->date_ouverture_prevue,0,10); $end=substr((string)$line->date_fin_validite,0,10);
            if (!$start || !$end) { throw new RuntimeException('Completá las fechas previstas de inicio y fin en la línea del contrato.'); }
            $already=$this->rows("SELECT b.rowid FROM ".$this->table('contract_link')." cl JOIN ".$this->table('booking')." b ON b.rowid=cl.fk_booking WHERE cl.fk_contract_line=".(int)$lineId." AND b.entity=".$this->entity." AND b.status<>'cancelled'");
            if ($already) { throw new RuntimeException('Esta línea ya tiene un renting. Para otro periodo, creá una nueva línea contractual.'); }
            // Legacy bookings cannot be assigned to a line safely by guessing.
            $legacy=$this->rows("SELECT b.rowid FROM ".$this->table('booking')." b LEFT JOIN ".$this->table('contract_link')." cl ON cl.fk_booking=b.rowid WHERE b.entity=".$this->entity." AND b.fk_contract=".(int)$contract." AND b.fk_service=".(int)$line->fk_product." AND b.status IN ('reserved','active','partial') AND cl.fk_booking IS NULL");
            if ($legacy) { throw new RuntimeException('Este servicio tiene un renting anterior sin vínculo de línea. Resolvelo antes de crear otra reserva para el mismo servicio.'); }
            $assets=array_values(array_unique(array_map('intval',$assets)));
            if (count($assets)!=(int)$qty) { throw new RuntimeException('El contrato requiere '.(int)$qty.' unidades; seleccionaste '.count($assets).'. No se puede reservar una entrega incompleta.'); }
            $this->one("SELECT rowid FROM ".$this->db->prefix()."product WHERE rowid=".(int)$product." AND entity=".$this->entity." AND fk_product_type=0 AND tobatch=2");
            foreach($assets as $assetId) {
                $asset=$this->asset($assetId,true);
                if ((int)$asset->fk_product!==(int)$product) { throw new RuntimeException('Todos los equipos deben corresponder al producto físico seleccionado para esta línea.'); }
            }
            $id=$this->reserve($soc,$contract,$line->fk_product,$start,$end,$assets,$note);
            $this->query("INSERT INTO ".$this->table('contract_link')." (fk_booking,fk_contract_line,fk_product,qty) VALUES (".(int)$id.",".(int)$lineId.",".(int)$product.",".(int)$qty.")");
            return $id;
        });
    }
    public function reserve($soc,$contract,$service,$start,$end,array $assets,$note='') {
        $this->permission('creer'); $this->date($start); $this->date($end);
        if ($end<$start || $start<dol_print_date(dol_now(),'%Y-%m-%d')) { throw new RuntimeException('El periodo debe empezar hoy o después y terminar en esa fecha o posteriormente.'); }
        $assets=array_values(array_unique(array_map('intval',$assets))); sort($assets);
        if (!$assets) { throw new RuntimeException('Selecciona al menos un equipo.'); }
        return $this->atomic(function () use ($soc,$contract,$service,$start,$end,$assets,$note) {
            $this->config(true); $p=$this->db->prefix();
            $this->one("SELECT rowid FROM ".$p."societe WHERE rowid=".(int)$soc." AND entity=".$this->entity." AND client IN (1,3) AND status=1");
            $this->one("SELECT rowid FROM ".$p."contrat WHERE rowid=".(int)$contract." AND entity=".$this->entity." AND fk_soc=".(int)$soc." AND statut=1");
            $this->one("SELECT rowid FROM ".$p."product WHERE rowid=".(int)$service." AND entity=".$this->entity." AND fk_product_type=1 AND tosell=1");
            $this->one("SELECT rowid FROM ".$p."contratdet WHERE fk_contrat=".(int)$contract." AND fk_product=".(int)$service);
            foreach ($assets as $id) {
                $a=$this->asset($id,true);
                if (!in_array($a->status,array('available','out'),true)) { throw new RuntimeException('Un equipo está en revisión, reparación o venta.'); }
                $overlap=$this->rows("SELECT l.rowid FROM ".$this->table('line')." l JOIN ".$this->table('booking')." b ON b.rowid=l.fk_booking WHERE l.fk_asset=".$id." AND b.entity=".$this->entity." AND b.status IN ('reserved','active','partial') AND l.date_return IS NULL AND b.date_start<=".$this->quote($end)." AND b.date_end>=".$this->quote($start));
                if ($overlap) { throw new RuntimeException('El equipo '.$a->serial.' ya está reservado en ese periodo.'); }
                $overdue=$this->rows("SELECT l.rowid FROM ".$this->table('line')." l JOIN ".$this->table('booking')." b ON b.rowid=l.fk_booking WHERE l.fk_asset=".$id." AND l.date_out IS NOT NULL AND l.date_return IS NULL AND b.date_end<".$this->quote(dol_print_date(dol_now(),'%Y-%m-%d')));
                if ($overdue) { throw new RuntimeException('El equipo '.$a->serial.' tiene una devolución vencida.'); }
            }
            $ref='RT-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(4)));
            $this->query("INSERT INTO ".$this->table('booking')." (entity,ref,fk_soc,fk_contract,fk_service,date_start,date_end,status,note,fk_user,date_creation) VALUES (".$this->entity.",".$this->quote($ref).",".(int)$soc.",".(int)$contract.",".(int)$service.",".$this->quote($start).",".$this->quote($end).",'reserved',".$this->quote(mb_substr($note,0,255)).",".(int)$this->user->id.",".$this->quote($this->db->idate(dol_now())).")");
            $id=$this->db->last_insert_id($this->table('booking'));
            foreach ($assets as $asset) { $this->query("INSERT INTO ".$this->table('line')." (fk_booking,fk_asset) VALUES (".(int)$id.",".$asset.")"); }
            $this->event('reserve',0,$id,$note); return $id;
        });
    }
    public function checkout($id) {
        $this->permission('ausgeben');
        return $this->atomic(function () use ($id) {
            $c=$this->config(true); $b=$this->booking($id,true);
            $today=dol_print_date(dol_now(),'%Y-%m-%d');
            if ($b->status!=='reserved' || $b->date_start>$today || $b->date_end<$today) { throw new RuntimeException('La reserva debe estar vigente para entregar los equipos.'); }
            $this->one("SELECT rowid FROM ".$this->db->prefix()."contrat WHERE rowid=".(int)$b->fk_contract." AND entity=".$this->entity." AND fk_soc=".(int)$b->fk_soc." AND statut=1");
            $lines=$this->rows("SELECT * FROM ".$this->table('line')." WHERE fk_booking=".(int)$id." ORDER BY fk_asset FOR UPDATE");
            if (!$lines) { throw new RuntimeException('La reserva no contiene equipos.'); }
            $links=$this->rows("SELECT * FROM ".$this->table('contract_link')." WHERE fk_booking=".(int)$id);
            if ($links) {
                $link=$links[0];
                $source=$this->one("SELECT * FROM ".$this->db->prefix()."contratdet WHERE rowid=".(int)$link->fk_contract_line." AND fk_contrat=".(int)$b->fk_contract." FOR UPDATE");
                if ((float)$source->qty!=(int)$link->qty || count($lines)!=(int)$link->qty || (int)$source->fk_product!==(int)$b->fk_service || substr((string)$source->date_ouverture_prevue,0,10)!==$b->date_start || substr((string)$source->date_fin_validite,0,10)!==$b->date_end) {
                    throw new RuntimeException('La reserva ya no coincide con la línea contractual. No se permite entregar hasta resolver la diferencia.');
                }
            }
            foreach ($lines as $line) {
                $a=$this->asset($line->fk_asset,true);
                if ($a->status!=='available' || (int)$a->fk_warehouse!==(int)$c->available) { throw new RuntimeException('El equipo '.$a->serial.' aún no está disponible para entrega.'); }
                $this->transfer($a->fk_product,$a->serial,$c->available,$c->customer,'Renting: entrega '.$b->ref);
                $this->query("UPDATE ".$this->table('asset')." SET status='out',fk_warehouse=".(int)$c->customer." WHERE rowid=".(int)$a->rowid);
                $this->query("UPDATE ".$this->table('line')." SET date_out=".$this->quote($this->db->idate(dol_now())).",condition_out=".$this->quote($a->item_condition)." WHERE rowid=".(int)$line->rowid);
                $this->event('checkout',$a->rowid,$id);
            }
            $this->query("UPDATE ".$this->table('booking')." SET status='active' WHERE rowid=".(int)$id);
            $waiting=$this->rows('SELECT fk_template FROM '.$this->table('billing')." WHERE fk_booking=".(int)$id." AND state='waiting'");
            if($waiting) {
                $this->query('UPDATE '.$this->db->prefix().'facture_rec SET suspended=0 WHERE rowid='.(int)$waiting[0]->fk_template.' AND entity='.$this->entity);
                $this->query('UPDATE '.$this->table('billing')." SET state='running' WHERE fk_booking=".(int)$id);
                $this->event('billing_start',0,$id,'Programación activada con la entrega.');
            }
        });
    }
    public function cancel($id) {
        $this->permission('creer');
        return $this->atomic(function () use ($id) {
            $this->config(true); $b=$this->booking($id,true);
            if ($b->status!=='reserved') { throw new RuntimeException('Solo se pueden cancelar reservas sin entregar.'); }
            $this->query("UPDATE ".$this->table('booking')." SET status='cancelled' WHERE rowid=".(int)$id);
            $this->pauseBilling($id,'Reserva cancelada.');
            $this->event('cancel',0,$id);
        });
    }
    public function reportIncident($id,$lineid,$reason) {
        $this->permission('ausgeben');
        if(trim($reason)==='' || mb_strlen($reason)>255) throw new RuntimeException('Describí la falla en un máximo de 255 caracteres.');
        return $this->atomic(function()use($id,$lineid,$reason){
            $b=$this->booking($id,true);
            if(!in_array($b->status,array('active','partial'),true)) throw new RuntimeException('La incidencia requiere un renting en curso.');
            $line=$this->one('SELECT * FROM '.$this->table('line').' WHERE rowid='.(int)$lineid.' AND fk_booking='.(int)$id.' FOR UPDATE');
            if(!$line->date_out || $line->date_return) throw new RuntimeException('El equipo ya no está entregado al cliente.');
            if($this->rows('SELECT rowid FROM '.$this->table('incident').' WHERE fk_line='.(int)$lineid." AND status='open'")) throw new RuntimeException('Esta unidad ya tiene una incidencia abierta.');
            $this->query('INSERT INTO '.$this->table('incident').' (entity,fk_booking,fk_line,reason,fk_user,date_creation) VALUES ('.$this->entity.','.(int)$id.','.(int)$lineid.','.$this->quote(trim($reason)).','.(int)$this->user->id.','.$this->quote($this->db->idate(dol_now())).')');
            $incident=$this->db->last_insert_id($this->table('incident'));
            $this->event('incident',$line->fk_asset,$id,$reason);
            return $incident;
        });
    }
    public function receiveIncident($id,$lineid,$reason) {
        $this->permission('ausgeben');
        return $this->atomic(function()use($id,$lineid,$reason){
            $c=$this->config(true); $b=$this->booking($id,true);
            if(!in_array($b->status,array('active','partial'),true)) throw new RuntimeException('El renting no está en curso.');
            $line=$this->one('SELECT * FROM '.$this->table('line').' WHERE rowid='.(int)$lineid.' AND fk_booking='.(int)$id.' FOR UPDATE');
            if(!$line->date_out || $line->date_return) throw new RuntimeException('El equipo ya fue recibido.');
            $incidents=$this->rows('SELECT rowid FROM '.$this->table('incident').' WHERE fk_booking='.(int)$id.' AND fk_line='.(int)$lineid." AND status='open' AND entity=".$this->entity);
            $incident=$incidents ? $incidents[0]->rowid : $this->reportIncident($id,$lineid,$reason);
            $a=$this->asset($line->fk_asset,true);
            if($a->status!=='out' || (int)$a->fk_warehouse!==(int)$c->customer) throw new RuntimeException('El equipo no figura en poder del cliente.');
            $this->transfer($a->fk_product,$a->serial,$c->customer,$c->review,'Renting: recepción por incidencia '.$b->ref);
            $this->query("UPDATE ".$this->table('asset')." SET status='review',item_condition='pending',fk_warehouse=".(int)$c->review.' WHERE rowid='.(int)$a->rowid);
            $this->query('UPDATE '.$this->table('line')." SET date_return=".$this->quote($this->db->idate(dol_now())).",condition_in='pending' WHERE rowid=".(int)$lineid);
            $this->query('UPDATE '.$this->table('incident')." SET status='received' WHERE rowid=".(int)$incident);
            $this->event('incident_receive',$a->rowid,$id,'Recibido para revisión; entrega pendiente. Facturación sin cambios.');
            return $incident;
        });
    }
    public function finishIncident($id,$incidentId,$reason) {
        $this->permission('ausgeben');
        if(trim($reason)==='' || mb_strlen($reason)>255) throw new RuntimeException('Indicá el motivo de la devolución definitiva.');
        return $this->atomic(function()use($id,$incidentId,$reason){
            $b=$this->booking($id,true);
            $i=$this->one('SELECT * FROM '.$this->table('incident').' WHERE rowid='.(int)$incidentId.' AND fk_booking='.(int)$id.' AND entity='.$this->entity.' FOR UPDATE');
            if($i->status!=='received' || !in_array($b->status,array('active','partial'),true)) throw new RuntimeException('No hay una entrega pendiente que finalizar.');
            $this->query('UPDATE '.$this->table('incident')." SET status='returned',resolution=".$this->quote($reason).',date_resolution='.$this->quote($this->db->idate(dol_now())).' WHERE rowid='.(int)$incidentId);
            $this->updateReturnStatus($id);
            $this->pauseBilling($id,'Devolución definitiva: '.$reason);
            $this->event('incident_finish',0,$id,$reason);
        });
    }
    private function updateReturnStatus($id) {
        $pending=$this->one('SELECT COUNT(*) qty FROM '.$this->table('line').' WHERE fk_booking='.(int)$id.' AND date_return IS NULL');
        $waiting=$this->one('SELECT COUNT(*) qty FROM '.$this->table('incident').' WHERE fk_booking='.(int)$id." AND status='received' AND entity=".$this->entity);
        $this->query('UPDATE '.$this->table('booking').' SET status='.$this->quote(($pending->qty || $waiting->qty)?'partial':'closed').' WHERE rowid='.(int)$id);
    }
    public function replacementCandidates($id,$lineid) {
        $b=$this->booking($id); $c=$this->config();
        $line=$this->one('SELECT a.fk_product FROM '.$this->table('line').' l JOIN '.$this->table('asset').' a ON a.rowid=l.fk_asset WHERE l.rowid='.(int)$lineid.' AND l.fk_booking='.(int)$id.' AND a.entity='.$this->entity);
        $today=dol_print_date(dol_now(),'%Y-%m-%d');
        return $this->rows('SELECT a.rowid,a.serial AS label FROM '.$this->table('asset').' a WHERE a.entity='.$this->entity.' AND a.fk_product='.(int)$line->fk_product." AND a.status='available' AND a.item_condition IN ('good','worn') AND a.fk_warehouse=".(int)$c->available.
            ' AND NOT EXISTS (SELECT 1 FROM '.$this->table('line').' l JOIN '.$this->table('booking')." b ON b.rowid=l.fk_booking WHERE l.fk_asset=a.rowid AND b.status IN ('reserved','active','partial') AND l.date_return IS NULL AND ((b.date_start<=".$this->quote($b->date_end).' AND b.date_end>='.$this->quote($today).') OR l.date_out IS NOT NULL)) ORDER BY a.serial');
    }
    public function replaceEquipment($id,$incidentId,$replacement,$note) {
        $this->permission('ausgeben');
        if(trim($note)==='' || mb_strlen($note)>255) throw new RuntimeException('Indicá la justificación del cambio (máximo 255 caracteres).');
        return $this->atomic(function()use($id,$incidentId,$replacement,$note){
            $c=$this->config(true); $b=$this->booking($id,true);
            $today=dol_print_date(dol_now(),'%Y-%m-%d');
            if(!in_array($b->status,array('active','partial'),true) || $b->date_end<$today) throw new RuntimeException('Solo se sustituye equipo en un renting vigente y entregado.');
            $incident=$this->one('SELECT * FROM '.$this->table('incident').' WHERE rowid='.(int)$incidentId.' AND fk_booking='.(int)$id.' AND entity='.$this->entity.' FOR UPDATE');
            if(!in_array($incident->status,array('open','received'),true)) throw new RuntimeException('La incidencia ya fue atendida.');
            $line=$this->one('SELECT * FROM '.$this->table('line').' WHERE rowid='.(int)$incident->fk_line.' AND fk_booking='.(int)$id.' FOR UPDATE');
            if(!$line->date_out || ($incident->status==='open' && $line->date_return) || ($incident->status==='received' && !$line->date_return)) throw new RuntimeException('La recepción no coincide con la incidencia.');
            $old=$this->asset($line->fk_asset,true); $new=$this->asset($replacement,true);
            if($incident->status==='open' && ($old->status!=='out' || (int)$old->fk_warehouse!==(int)$c->customer)) throw new RuntimeException('El equipo original no figura en poder del cliente.');
            $candidates=$this->replacementCandidates($id,$line->rowid);
            if(!in_array((int)$replacement,array_map(function($a){return (int)$a->rowid;},$candidates),true)) throw new RuntimeException('El reemplazo debe ser del mismo producto y estar disponible durante todo el periodo restante.');
            $now=$this->quote($this->db->idate(dol_now()));
            if($incident->status==='open') $this->receiveIncident($id,$line->rowid,$incident->reason);
            $this->transfer($new->fk_product,$new->serial,$c->available,$c->customer,'Renting: equipo sustituto '.$b->ref);
            $this->query("UPDATE ".$this->table('asset')." SET status='out',fk_warehouse=".(int)$c->customer.' WHERE rowid='.(int)$new->rowid);
            $this->query('INSERT INTO '.$this->table('line').' (fk_booking,fk_asset,condition_out,date_out) VALUES ('.(int)$id.','.(int)$new->rowid.','.$this->quote($new->item_condition).','.$now.')');
            $newLine=$this->db->last_insert_id($this->table('line'));
            $this->query('UPDATE '.$this->table('incident')." SET status='replaced',fk_new_line=".(int)$newLine.',resolution='.$this->quote($note).',date_resolution='.$now.' WHERE rowid='.(int)$incidentId);
            $this->event('replace_out',$old->rowid,$id,'Sustituido por '.$new->serial.'. '.$note);
            $this->event('replace_in',$new->rowid,$id,'Reemplaza a '.$old->serial.'. '.$note);
            // Deliberately do not call receive(): this exchange preserves active units,
            // booking status and the entire billing schedule, including manual pauses.
            return $newLine;
        });
    }
    public function receiveAll($id,array $expectedLines) {
        $this->permission('ausgeben');
        return $this->atomic(function()use($id,$expectedLines){
            $this->config(true); $b=$this->booking($id,true);
            if(!in_array($b->status,array('active','partial'),true)) throw new RuntimeException('Este renting ya no admite una devolución definitiva.');
            $lines=$this->rows('SELECT rowid,date_out FROM '.$this->table('line').' WHERE fk_booking='.(int)$id.' AND date_return IS NULL ORDER BY rowid FOR UPDATE');
            $actual=array_map(function($l){return (int)$l->rowid;},$lines);
            $expected=array_map('intval',$expectedLines); sort($expected); sort($actual);
            if($actual!==$expected) throw new RuntimeException('Los equipos pendientes cambiaron. Recargá y comprobá las series antes de recibir todos.');
            foreach($lines as $line) {
                if(!$line->date_out) throw new RuntimeException('Hay unidades sin entregar: revisá el renting antes de finalizar.');
                $this->receive($id,$line->rowid);
            }
            $this->query('UPDATE '.$this->table('incident')." SET status='returned',resolution='Renting finalizado por devolución definitiva de todos los equipos',date_resolution=".$this->quote($this->db->idate(dol_now())).' WHERE fk_booking='.(int)$id.' AND entity='.$this->entity." AND status='received'");
            $this->updateReturnStatus($id);
            $this->pauseBilling($id,'Devolución definitiva de todos los equipos: renting finalizado.');
            $this->event('return_all',0,$id,'Recepción conjunta de '.count($lines).' equipo(s). Entregas pendientes canceladas.');
        });
    }
    public function receive($id, $lineid, $condition='pending') {
        $this->permission('ausgeben');
        if (!in_array($condition,array('pending','good','worn','damaged'),true)) { throw new RuntimeException('Condición de devolución inválida.'); }
        return $this->atomic(function () use ($id,$lineid,$condition) {
            $c=$this->config(true); $b=$this->booking($id,true);
            if (!in_array($b->status,array('active','partial'),true)) { throw new RuntimeException('Este renting no admite devoluciones.'); }
            $l=$this->one("SELECT * FROM ".$this->table('line')." WHERE rowid=".(int)$lineid." AND fk_booking=".(int)$id." FOR UPDATE");
            if (!$l->date_out || $l->date_return) { throw new RuntimeException('La línea no está pendiente de devolución.'); }
            $a=$this->asset($l->fk_asset,true);
            if ($a->status!=='out') { throw new RuntimeException('El equipo no figura entregado.'); }
            $this->transfer($a->fk_product,$a->serial,$c->customer,$c->review,'Renting: devolución '.$b->ref);
            $this->query("UPDATE ".$this->table('asset')." SET status='review',item_condition=".$this->quote($condition).",fk_warehouse=".(int)$c->review." WHERE rowid=".(int)$a->rowid);
            $this->query("UPDATE ".$this->table('line')." SET date_return=".$this->quote($this->db->idate(dol_now())).",condition_in=".$this->quote($condition)." WHERE rowid=".(int)$lineid);
            $pending=$this->one("SELECT COUNT(*) qty FROM ".$this->table('line')." WHERE fk_booking=".(int)$id." AND date_return IS NULL");
            $this->updateReturnStatus($id);
            $this->pauseBilling($id,$pending->qty?'Devolución parcial: revisar cantidad y próxima mensualidad.':'Todos los equipos devueltos: facturación detenida.');
            $this->event('return',$a->rowid,$id,$condition);
            $this->query('UPDATE '.$this->table('incident')." SET status='returned',resolution='Devolución sin sustitución',date_resolution=".$this->quote($this->db->idate(dol_now())).' WHERE fk_line='.(int)$lineid." AND status='open' AND entity=".$this->entity);
        });
    }
    public function pauseBilling($id,$reason) {
        $maps=$this->rows('SELECT fk_template FROM '.$this->table('billing').' WHERE fk_booking='.(int)$id);
        if(!$maps) return;
        $this->query('UPDATE '.$this->db->prefix().'facture_rec SET suspended=1 WHERE rowid='.(int)$maps[0]->fk_template.' AND entity='.$this->entity);
        $this->query('UPDATE '.$this->table('billing')." SET state='paused',reason=".$this->quote(mb_substr($reason,0,255)).' WHERE fk_booking='.(int)$id);
        $this->event('billing_pause',0,$id,$reason);
    }
    public function inspect($id,$destination,$condition,$note) {
        $this->permission('ausgeben');
        if (!in_array($destination,array('available','repair','sale'),true) || !in_array($condition,array('good','worn','damaged'),true) || trim($note)==='') { throw new RuntimeException('Selecciona destino, condición y escribe el resultado de la revisión.'); }
        return $this->atomic(function () use ($id,$destination,$condition,$note) {
            $c=$this->config(true); $a=$this->asset($id,true);
            if (!in_array($a->status,array('available','review','repair'),true)) { throw new RuntimeException('No se puede mover un equipo entregado o destinado a venta.'); }
            if ($destination!=='repair' && $condition==='damaged') { throw new RuntimeException('Un equipo dañado debe permanecer en reparación.'); }
            if ($destination==='sale') {
                $pending=$this->rows("SELECT l.rowid FROM ".$this->table('line')." l JOIN ".$this->table('booking')." b ON b.rowid=l.fk_booking WHERE l.fk_asset=".(int)$id." AND b.status IN ('reserved','active','partial') AND l.date_return IS NULL");
                if ($pending) { throw new RuntimeException('El equipo tiene compromisos de renting y no puede pasar a venta.'); }
            }
            $to=(int)$c->$destination;
            if ((int)$a->fk_warehouse!==$to) { $this->transfer($a->fk_product,$a->serial,$a->fk_warehouse,$to,'Renting: revisión '.$a->serial); }
            $this->query("UPDATE ".$this->table('asset')." SET status=".$this->quote($destination).",item_condition=".$this->quote($condition).",fk_warehouse=".$to." WHERE rowid=".(int)$id);
            $this->query('UPDATE '.$this->table('line').' SET condition_in='.$this->quote($condition).' WHERE fk_asset='.(int)$id." AND date_return IS NOT NULL AND condition_in='pending'");
            $this->event('inspection',$id,0,$note);
        });
    }
}
