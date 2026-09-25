<?php
/* R&R Technology, 2026. GPL-3.0-or-later. */
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
class InterfaceRrRentingSales extends DolibarrTriggers {
    public function __construct($db) {
        $this->db=$db; $this->name='RrRentingSales'; $this->family='products';
        $this->description='Protect sale availability from renting stock';
        $this->version='2.0.1'; $this->picto='fa-desktop';
    }
    public function runTrigger($action,$object,User $user,Translate $langs,Conf $conf) {
        if (!isModEnabled('verleih') || !in_array($action,array('ORDER_VALIDATE','LINEORDER_UPDATE','LINEORDER_INSERT'),true)) { return 0; }
        require_once __DIR__.'/../../class/rrrenting.class.php';
        $rr=new RrRenting($this->db,$user,$conf->entity);
        try {
            $configs=$rr->rows("SELECT sale FROM ".$rr->table('config')." WHERE entity=".(int)$conf->entity);
            if (!$configs) { return 0; }
            $orderid=$action==='ORDER_VALIDATE'?(int)$object->id:(int)$object->fk_commande;
            $p=$this->db->prefix();
            $order=$rr->one("SELECT rowid,fk_statut FROM ".$p."commande WHERE rowid=".$orderid." AND entity=".(int)$conf->entity);
            if (!in_array((int)$order->fk_statut,array(1,2),true)) { return 0; }
            if (!getDolGlobalInt('STOCK_CALCULATE_ON_SHIPMENT') || getDolGlobalInt('STOCK_CALCULATE_ON_VALIDATE_ORDER') || getDolGlobalInt('STOCK_CALCULATE_ON_BILL') || getDolGlobalInt('STOCK_CALCULATE_ON_SHIPMENT_CLOSE')) {
                throw new RuntimeException('Renting requiere descontar stock solo al validar expediciones. Revisa Configuración > Módulos > Stocks.');
            }
            $lines=$rr->rows("SELECT d.fk_product,SUM(d.qty) qty,p.ref FROM ".$p."commandedet d JOIN ".$p."product p ON p.rowid=d.fk_product WHERE d.fk_commande=".$orderid." AND p.fk_product_type=0 GROUP BY d.fk_product,p.ref ORDER BY d.fk_product");
            foreach($lines as $l) {
                $rr->one("SELECT rowid FROM ".$p."product WHERE rowid=".(int)$l->fk_product." AND entity=".(int)$conf->entity." FOR UPDATE");
                $stock=$rr->one("SELECT COALESCE(SUM(reel),0) qty FROM ".$p."product_stock WHERE fk_product=".(int)$l->fk_product." AND fk_entrepot=".(int)$configs[0]->sale);
                $pending=$rr->one("SELECT COALESCE(SUM(d.qty),0) qty FROM ".$p."commandedet d JOIN ".$p."commande o ON o.rowid=d.fk_commande WHERE o.entity=".(int)$conf->entity." AND o.fk_statut IN (1,2) AND o.rowid<>".$orderid." AND d.fk_product=".(int)$l->fk_product);
                if ((float)$l->qty>max(0,(float)$stock->qty-(float)$pending->qty)) {
                    throw new RuntimeException('Stock de venta insuficiente para '.$l->ref.'. Solo se utiliza RR-VENTA; los equipos de renting no se pueden vender.');
                }
            }
            return 0;
        } catch(Throwable $e) { $this->error=$e->getMessage(); $this->errors[]=$this->error; return -1; }
    }
}

