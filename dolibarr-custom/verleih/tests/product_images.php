<?php
if(PHP_SAPI!=='cli') exit(1);
define('NOLOGIN',1); define('NOREQUIREMENU',1); define('NOREQUIREHTML',1); define('USEDOLIBARRSERVER',1);
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/website.lib.php';
$results=array();
foreach(array('RR-PC-001','RR-MON-001','RR-KEY-001','RR-MOUSE-001','RR-AUDIO-001') as $ref) {
 $product=new Product($db); if($product->fetch(0,$ref)<=0) throw new RuntimeException('Missing '.$ref);
 $covers=array_filter(getPublicFilesOfObject($product),function($f){return strpos($f['filename'],'image_cover_')===0;});
 if(count($covers)!==1) throw new RuntimeException('Expected one public cover: '.$ref);
 $file=reset($covers);
 $results[]=array('ref'=>$ref,'url'=>$file['url']);
}
echo json_encode($results);