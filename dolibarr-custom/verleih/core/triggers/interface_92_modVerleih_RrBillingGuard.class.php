<?php
/* R&R Technology. GPL-3.0-or-later. */
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
class InterfaceRrBillingGuard extends DolibarrTriggers {
    public function __construct($db) {
        $this->db=$db; $this->name='RrBillingGuard'; $this->family='financial';
        $this->description='Managed renting billing'; $this->version='2.2.0'; $this->picto='bill';
    }
    public function runTrigger($action,$object,User $user,Translate $langs,Conf $conf) {
        if(!isModEnabled('verleih') || !in_array($action,array('BILL_CREATE','BILLREC_MODIFY','BILLREC_DELETE','LINEBILLREC_MODIFY','LINEBILLREC_DELETE'),true)) return 0;
        require_once __DIR__.'/../../class/rrbilling.class.php';
        if(RrBilling::$internal) return 0;
        if($action==='BILL_CREATE') $id=(int)($object->fk_fac_rec_source ?: ($object->fac_rec ?? 0));
        elseif(strpos($action,'LINEBILLREC_')===0) $id=(int)($object->fk_facture ?? 0);
        else $id=(int)$object->id;
        if(!$id) return 0;
        $sql='SELECT rb.fk_booking FROM '.$this->db->prefix().'rr_renting_billing rb JOIN '.$this->db->prefix().'rr_renting_booking b ON b.rowid=rb.fk_booking WHERE rb.fk_template='.$id.' AND b.entity='.(int)$conf->entity;
        $res=$this->db->query($sql);
        if(!$res || $this->db->num_rows($res)) {
            $this->error='Esta plantilla se gestiona desde el renting: usá sus acciones de pausa y ajuste. La generación corresponde al proceso automático.';
            $this->errors[]=$this->error; return -1;
        }
        return 0;
    }
}
