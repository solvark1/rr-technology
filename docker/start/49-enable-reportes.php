<?php
// Idempotent activation; no demo data or report queries are changed.
if(PHP_SAPI!=='cli') exit(1);
define('NOLOGIN',1); define('NOREQUIREMENU',1); define('NOREQUIREHTML',1);
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
$user=new User($db);
if($user->fetch(0,getenv('DOLI_ADMIN_LOGIN') ?: 'admin')<=0 || !$user->admin) exit(1);
$user->getrights();
if(!isModEnabled('reportes')) {
	$result=activateModule('modReportes');
	if(!empty($result['errors'])) { fwrite(STDERR,implode('; ',$result['errors'])); exit(1); }
}
$path=DOL_DOCUMENT_ROOT.'/custom/reportes/python/graficos';
if(!is_dir($path) && !mkdir($path,0775,true)) exit(1);
chown($path,'www-data'); chgrp($path,'www-data');
echo "[RR-REPORTES] Module enabled.\n";
