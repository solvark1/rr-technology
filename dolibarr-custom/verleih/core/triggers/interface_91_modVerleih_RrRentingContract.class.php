<?php
/* R&R Technology. GPL-3.0-or-later. */
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
class InterfaceRrRentingContract extends DolibarrTriggers {
    public function __construct($db) {
        $this->db=$db; $this->name='RrRentingContract'; $this->family='contracts';
        $this->description='Protect contractual commitments used by Renting'; $this->version='2.1.0'; $this->picto='fa-desktop';
    }
    public function runTrigger($action,$object,User $user,Translate $langs,Conf $conf) {
        if (!isModEnabled('verleih') || !in_array($action,array('LINECONTRACT_MODIFY','LINECONTRACT_DELETE','CONTRACT_MODIFY','CONTRACT_DELETE','CONTRACT_REOPEN'),true)) { return 0; }
        $p=$this->db->prefix();
        $sql="SELECT b.rowid FROM ".$p."rr_renting_booking b LEFT JOIN ".$p."rr_renting_contract_link cl ON cl.fk_booking=b.rowid WHERE b.entity=".(int)$conf->entity." AND b.fk_contract=".(int)$object->id." AND b.status IN ('reserved','active','partial')";
        if (strpos($action,'LINECONTRACT_')===0) {
            $sql.=' AND (cl.fk_contract_line='.(int)($object->context['line_id']??0).' OR cl.fk_booking IS NULL)';
        }
        $res=$this->db->query($sql);
        if (!$res || $this->db->num_rows($res)) {
            $this->error='No se puede modificar o eliminar este compromiso: hay un renting reservado o en curso. Cancelá la reserva sin entregar o completá la devolución antes de cambiar el contrato.';
            $this->errors[]=$this->error; return -1;
        }
        return 0;
    }
}
