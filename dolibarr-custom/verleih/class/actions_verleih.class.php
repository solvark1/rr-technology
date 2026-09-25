<?php
/* R&R Technology, 2026. GPL-3.0-or-later. */
class ActionsVerleih
{
    public $db; public $errors=array(); public $error=''; public $resprints='';
    public function __construct($db) { $this->db=$db; }
    public function stockMovementCreate($parameters, &$object, &$action, $hookmanager) {
        global $conf;
        if (!isModEnabled('verleih')) { return 0; }
        if (class_exists('RrRenting',false) && RrRenting::$moving) { return 0; }
        $r=$this->db->query("SELECT available,customer,review,repair FROM ".$this->db->prefix()."rr_renting_config WHERE entity=".(int)$conf->entity);
        if (!$r) { $this->errors[]='No se pudo comprobar la protección del inventario de renting.'; return -1; }
        $c=$this->db->fetch_object($r);
        if (!$c) { return 0; }
        $warehouse=(int)$parameters['entrepot_id'];
        if (in_array($warehouse,array((int)$c->available,(int)$c->customer,(int)$c->review,(int)$c->repair),true)) {
            $this->error='Este almacén está reservado al renting. Usa Renting para incorporar, entregar, devolver o revisar equipos.';
            $this->errors[]=$this->error;
            return -1;
        }
        return 0;
    }
}

