<?php
// Repeatable image seed: source images remain read-only, native photos live in documents.
if (PHP_SAPI !== 'cli') exit(1);
if (getenv('RR_RENTING_DEMO_SEED') !== '1') exit(0);
define('NOLOGIN',1); define('NOREQUIREMENU',1); define('NOREQUIREHTML',1); define('NOREQUIREAJAX',1);
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
$user=new User($db);
if($user->fetch(0,getenv('DOLI_ADMIN_LOGIN') ?: 'admin')<=0) { fwrite(STDERR,'Image seed: administrator missing'); exit(1); }
$user->getrights();
$source=getenv('RR_PRODUCT_IMAGES_DIR') ?: '/var/www/rr-seed-products';
try {
    foreach(array('RR-PC-001','RR-MON-001','RR-KEY-001','RR-MOUSE-001','RR-AUDIO-001') as $ref) {
        $product=new Product($db);
        if($product->fetch(0,$ref)<=0 || (int)$product->entity!==(int)$conf->entity) throw new RuntimeException('Producto no encontrado: '.$ref);
        $file=strtolower($ref).'.png'; $input=$source.'/'.$file;
        if(!is_file($input) || !getimagesize($input)) throw new RuntimeException('Imagen no disponible: '.$file);
        $base=$conf->product->multidir_output[$product->entity];
        $dir=getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO') ? $base.'/'.get_exdir($product->id,2,0,0,$product,'product').$product->id.'/photos' : $base.'/'.get_exdir(0,0,0,1,$product,'product');
        if(dol_mkdir($dir)<0 || !is_writable($dir)) throw new RuntimeException('Directorio no escribible: '.$dir);
        $target=$dir.'/image_cover_'.strtolower($ref).'.png';
        $changed=!is_file($target) || hash_file('sha256',$input)!==hash_file('sha256',$target);
        if($changed) {
            $tmp=tempnam($dir,'.rr-image-');
            if(!$tmp || !copy($input,$tmp) || !rename($tmp,$target)) throw new RuntimeException('No se pudo copiar '.$file);
            chmod($target,0644);
        }
        $product->addThumbs($target);
        if(!is_file($target) || hash_file('sha256',$input)!==hash_file('sha256',$target)) throw new RuntimeException('VerificaciÃ³n fallida: '.$file);
        $relative=ltrim(substr($dir,strlen(DOL_DATA_ROOT)),'/');
        $ecm=new EcmFiles($db);
        $found=$ecm->fetch(0,'',$relative.'/'.basename($target));
        if($found<0) throw new RuntimeException($ecm->error);
        $ecm->entity=(int)$product->entity;
        $ecm->filepath=$relative; $ecm->filename=basename($target);
        $ecm->label=md5_file($target); $ecm->fullpath_orig=$target;
        $ecm->src_object_type=$product->element; $ecm->src_object_id=$product->id;
        $ecm->gen_or_uploaded='uploaded'; $ecm->cover=1;
        if(empty($ecm->share)) $ecm->share=bin2hex(random_bytes(24));
        $result=$found>0 ? $ecm->update($user) : $ecm->create($user);
        if($result<=0) throw new RuntimeException('Registro de portada: '.$ecm->error);
        echo '[RR-IMAGES] '.$ref.($changed?' imagen cargada':' imagen ya disponible').PHP_EOL;
    }
} catch(Throwable $ex) { fwrite(STDERR,'[RR-IMAGES] '.$ex->getMessage().PHP_EOL); exit(1); }
