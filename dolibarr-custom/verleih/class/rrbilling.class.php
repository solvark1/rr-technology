<?php
/* R&R Technology. GPL-3.0-or-later. */
require_once __DIR__.'/rrrenting.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
class RrBilling extends RrRenting {
    public static $internal = false;
    public function billingPermission() {
        $this->permission('creer');
        if (!$this->user->hasRight('facture','creer')) throw new RuntimeException('Necesitás permiso para crear facturas.');
    }
    public function source($id) {
        $b=$this->booking($id);
        $d=$this->one('SELECT d.*,c.fk_soc,c.statut AS contract_status,s.cond_reglement AS fk_cond_reglement,s.mode_reglement AS fk_mode_reglement FROM '.$this->table('contract_link').' cl JOIN '.$this->db->prefix().'contratdet d ON d.rowid=cl.fk_contract_line JOIN '.$this->db->prefix().'contrat c ON c.rowid=d.fk_contrat JOIN '.$this->db->prefix().'societe s ON s.rowid=c.fk_soc WHERE cl.fk_booking='.(int)$id.' AND c.entity='.$this->entity);
        if ((int)$d->fk_soc!==(int)$b->fk_soc || (int)$d->contract_status!==1 || (int)$d->fk_product!==(int)$b->fk_service || substr((string)$d->date_ouverture_prevue,0,10)!==$b->date_start || substr((string)$d->date_fin_validite,0,10)!==$b->date_end) throw new RuntimeException('La línea contractual ya no coincide con el renting.');
        return $d;
    }
    // Monthly prepaid periods. The ending boundary does not start a new charge.
    public function periods($start,$end) {
        $first=strtotime($start); $finish=strtotime($end); $count=0;
        if(!$first || !$finish || $finish<=$first) throw new RuntimeException('La facturación mensual requiere una fecha de fin posterior al inicio.');
        $calendar=new FactureRec($this->db); $calendar->frequency=1; $calendar->unit_frequency='m'; $calendar->date_when=$first;
        while($calendar->date_when<$finish && $count<1200) { $count++; $calendar->date_when=$calendar->getNextDate(); }
        if($count>=1200) throw new RuntimeException('Periodo de facturación demasiado largo.');
        return $count;
    }
    public function createSchedule($id,$confirmed,$paymentTerms=0) {
        $this->billingPermission();
        if (!$confirmed) throw new RuntimeException('Confirmá que la tarifa es mensual y que no existe otra plantilla para este renting.');
        return $this->atomic(function()use($id,$paymentTerms){
            $b=$this->booking($id,true);
            if(!in_array($b->status,array('reserved','active'),true)) throw new RuntimeException('Creá la programación antes de registrar devoluciones.');
            if($this->rows('SELECT fk_booking FROM '.$this->table('billing').' WHERE fk_booking='.(int)$id)) throw new RuntimeException('Este renting ya tiene una programación.');
            $d=$this->source($id);
            $link=$this->one('SELECT qty FROM '.$this->table('contract_link').' WHERE fk_booking='.(int)$id);
            if((float)$d->qty!=(int)$link->qty) throw new RuntimeException('La cantidad contractual cambió.');
            if((float)$d->subprice<0 || (float)$d->localtax1_tx!=0 || (float)$d->localtax2_tx!=0) throw new RuntimeException('Esta integración admite IVA sin impuestos locales adicionales y tarifas no negativas.');
            $terms=(int)($paymentTerms ?: $d->fk_cond_reglement);
            $this->one('SELECT rowid FROM '.$this->db->prefix().'c_payment_term WHERE rowid='.$terms.' AND active=1');
            $invoice=new Facture($this->db); $invoice->socid=$b->fk_soc; $invoice->date=dol_now(); $invoice->type=0;
            $invoice->cond_reglement_id=$terms; $invoice->mode_reglement_id=(int)$d->fk_mode_reglement;
            if($invoice->create($this->user)<=0) throw new RuntimeException($invoice->error);
            $desc='Renting '.$b->ref.' — mensualidad. '.$d->description;
            if($invoice->addline($desc,$d->subprice,$d->qty,$d->tva_tx,0,0,$d->fk_product,$d->remise_percent,'','',0,$d->info_bits,0,'HT',0,1)<=0) throw new RuntimeException($invoice->error);
            $rec=new FactureRec($this->db); $rec->title='Renting '.$b->ref; $rec->frequency=1; $rec->unit_frequency='m';
            $rec->date_when=strtotime($b->date_start); $rec->nb_gen_max=$this->periods($b->date_start,$b->date_end);
            $rec->cond_reglement_id=$terms; $rec->mode_reglement_id=(int)$d->fk_mode_reglement;
            $rec->auto_validate=0; $rec->generate_pdf=0; $rec->suspended=$b->status==='reserved'?1:0;
            $rec->note_public='Renting '.$b->ref; $rec->note_private='Gestionada desde Renting. Mensualidades completas, sin prorrateo automático.';
            if($rec->create($this->user,$invoice->id)<=0) throw new RuntimeException($rec->error.' '.implode('; ',$rec->errors));
            // Native template creation copies a draft. Remove that technical draft
            // in the same transaction: only the scheduled invoices remain.
            if($invoice->delete($this->user)<=0) throw new RuntimeException('No se pudo retirar el borrador técnico: '.$invoice->error);
            $state=$b->status==='reserved'?'waiting':'running';
            $this->query('INSERT INTO '.$this->table('billing').' (fk_booking,fk_template,state,reason) VALUES ('.(int)$id.','.(int)$rec->id.','.$this->quote($state).",'')");
            $this->event('billing_create',0,$id,'Plantilla '.$rec->id.'; '.$rec->nb_gen_max.' mensualidades.');
            return $rec->id;
        });
    }
    public function pause($id,$reason) {
        $this->billingPermission(); if(!trim($reason)) throw new RuntimeException('Indicá el motivo de la pausa.');
        return $this->atomic(function()use($id,$reason){$this->booking($id,true);$this->pauseBilling($id,$reason);});
    }
    public function resume($id,$date,$adjust,$reason) {
        $this->billingPermission(); $this->date($date);
        if(!trim($reason)) throw new RuntimeException('Indicá el motivo del ajuste o reanudación.');
        return $this->atomic(function()use($id,$date,$adjust,$reason){
            $b=$this->booking($id,true);
            if(!in_array($b->status,array('active','partial'),true)) throw new RuntimeException('Solo se reanuda un renting con unidades entregadas.');
            if($date<dol_print_date(dol_now(),'%Y-%m-%d') || $date>=$b->date_end) throw new RuntimeException('La próxima fecha debe ser hoy o posterior, y anterior al fin del contrato.');
            $map=$this->one('SELECT * FROM '.$this->table('billing').' WHERE fk_booking='.(int)$id);
            if($map->state!=='paused') throw new RuntimeException('La programación debe estar pausada.');
            $rec=new FactureRec($this->db);
            if($rec->fetch($map->fk_template)<=0 || count($rec->lines)!==1) throw new RuntimeException('Plantilla no disponible o modificada fuera del flujo.');
            if((int)$rec->nb_gen_done>=(int)$rec->nb_gen_max) throw new RuntimeException('La programación ya completó sus mensualidades.');
            if($rec->date_when && strtotime($date)<$rec->date_when) throw new RuntimeException('No se puede adelantar la próxima mensualidad ni repetir un periodo ya facturado.');
            $qty=$this->one('SELECT COUNT(*) qty FROM '.$this->table('line').' WHERE fk_booking='.(int)$id.' AND date_out IS NOT NULL AND date_return IS NULL')->qty;
            $d=$this->source($id); $line=$rec->lines[0];
            $qty+=(int)$this->one('SELECT COUNT(*) qty FROM '.$this->table('incident').' WHERE fk_booking='.(int)$id." AND status='received' AND entity=".$this->entity)->qty;
            if(!$adjust) $qty=$line->qty;
            if($qty<1) throw new RuntimeException('No quedan equipos para facturar.');
            self::$internal=true;
            try {
                if($rec->updateline($line->id,$line->desc,$d->subprice,$qty,$d->tva_tx,0,0,$d->fk_product,$d->remise_percent,'HT',$d->info_bits,0,0,1)<0) throw new RuntimeException($rec->error);
                $remaining=min((int)$rec->nb_gen_max-(int)$rec->nb_gen_done,$this->periods($date,$b->date_end));
                $this->query('UPDATE '.$this->db->prefix().'facture_rec SET suspended=0,auto_validate=0,date_when='.$this->quote($this->db->idate(strtotime($date))).',nb_gen_max='.((int)$rec->nb_gen_done+$remaining).' WHERE rowid='.(int)$rec->id.' AND entity='.$this->entity);
                $this->query('UPDATE '.$this->table('billing')." SET state='running',reason=".$this->quote(mb_substr($reason,0,255)).' WHERE fk_booking='.(int)$id);
                $this->event('billing_resume',0,$id,'Cantidad '.$qty.'; próxima '.$date.'; '.$reason);
            } finally { self::$internal=false; }
        });
    }
}
