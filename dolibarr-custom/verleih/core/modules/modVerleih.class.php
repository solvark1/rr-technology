<?php
/* Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
 * Adaptation for R&R Technology, 2026.
 * GNU General Public License version 3 or (at your option) any later version.
 * Technical module identity retained for existing installations and permissions.
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
class modVerleih extends DolibarrModules
{
    public function __construct($db) {
        $this->db=$db;
        $this->numero=501000; $this->rights_class='verleih'; $this->family='products'; $this->module_position='75';
        $this->name='Verleih'; $this->const_name='MAIN_MODULE_VERLEIH';
        $this->description='Renting de equipos y componentes con clientes e inventario de Dolibarr';
        $this->descriptionlong='R&R Technology: reservas por fechas, equipos por número de serie, contratos, entregas, devoluciones y revisión.';
        $this->editor_name='R&R Technology / Verleih'; $this->editor_url=''; $this->version='2.0.1';
        $this->picto='fa-desktop'; $this->langfiles=array('verleih@verleih');
        $this->module_parts=array('triggers'=>1,'hooks'=>array('mouvementstock'),'css'=>array(),'js'=>array());
        $this->dirs=array(); $this->config_page_url=array('setup.php@verleih');
        $this->hidden=false; $this->depends=array('modSociete','modProduct','modService','modStock','modProductBatch','modContrat');
        $this->requiredby=array(); $this->conflictwith=array(); $this->phpmin=array(7,4); $this->need_dolibarr_version=array(24,0);
        $this->warnings_activation=array(); $this->warnings_activation_ext=array();
        $this->const=array(); $this->tabs=array(); $this->dictionaries=array(); $this->boxes=array(); $this->cronjobs=array();
        $this->rights=array();
        $permissions=array('lire'=>'Consultar renting e historial','creer'=>'Incorporar equipos y gestionar reservas','supprimer'=>'Permiso reservado (sin eliminación de historial)','ausgeben'=>'Entregar, recibir y revisar equipos','etiketten'=>'Permiso reservado para etiquetas','configurer'=>'Configurar almacenes de renting');
        $i=0; foreach($permissions as $key=>$label) {
            $this->rights[$i]=array(0=>5010001+$i,1=>$label,3=>($key==='lire'?1:0),4=>$key); $i++;
        }
        $this->menu=array();
        $this->menu[]=array('fk_menu'=>'','type'=>'top','titre'=>'VerleihMenuTop','prefix'=>img_picto('','fa-desktop','class="pictofixedwidth valignmiddle"'),'mainmenu'=>'verleih','leftmenu'=>'','url'=>'/verleih/renting.php','langs'=>'verleih@verleih','position'=>1080,'enabled'=>'isModEnabled("verleih")','perms'=>'$user->hasRight("verleih", "lire")','target'=>'','user'=>2);
        foreach(array('dashboard'=>'Resumen','assets'=>'Equipos','bookings'=>'Rentings','settings'=>'Configuración') as $view=>$label) {
            $right=$view==='settings'?'configurer':'lire';
            $this->menu[]=array('fk_menu'=>'fk_mainmenu=verleih','type'=>'left','titre'=>$label,'mainmenu'=>'verleih','leftmenu'=>'rr_'.$view,'url'=>'/verleih/renting.php?view='.$view,'langs'=>'verleih@verleih','position'=>1081+count($this->menu),'enabled'=>'isModEnabled("verleih")','perms'=>'$user->hasRight("verleih", "'.$right.'")','target'=>'','user'=>2);
        }
    }
    public function init($options='') {
        require_once __DIR__.'/../../lib/renting_schema.php';
        try { rrRentingMigrate($this->db); } catch (Throwable $e) { $this->error=$e->getMessage(); return -1; }
        $this->db->begin();
        if ($this->delete_menus()) { $this->db->rollback(); return -1; }
        $result=$this->_init(array(),$options);
        if ($result<=0) { $this->db->rollback(); return $result; }
        $this->db->commit();
        return $result;
    }
    public function remove($options='') { return $this->_remove(array(),$options); }
}

