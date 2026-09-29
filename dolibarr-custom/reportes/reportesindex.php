<?php
define('CSRFCHECK_WITH_TOKEN',1);
require '../../main.inc.php';
if (!isModEnabled('reportes') || !empty($user->socid) || (int)$conf->entity !== 1) { accessforbidden(); }

/* Actions: fixed report catalog, no user-provided executable or path. */
$top3 = [
    ["script" => "capacidad_flota.py",        "png" => "capacidad_flota.png",        "titulo" => "Capacidad de la flota"],
    ["script" => "incidencias_pendientes.py", "png" => "incidencias_pendientes.png", "titulo" => "Incidencias pendientes"],
    ["script" => "top_alquilados.py",         "png" => "top_alquilados.png",         "titulo" => "Equipos más alquilados"],
];

$otros = [
    "mariadb" => [
        "label"    => "Otros reportes",
        "reportes" => [
            ["script" => "mariaDB/equipos_condicion.py", "png" => "equipos_condicion.png", "titulo" => "Equipos por condición"],
            ["script" => "mariaDB/incidencias_tipo.py",  "png" => "incidencias_tipo.png",  "titulo" => "Incidencias por estado"],
        ],
    ],

    "mongodb" => [
        "label"  => "Redes Sociales",
        "script" => "mongo/reportes_mongo.py", // un único script genera los 5 gráficos
        "reportes" => [
            ["png" => "interacciones_plataforma.png",       "titulo" => "Interacciones por plataforma"],
            ["png" => "interacciones_producto.png",         "titulo" => "Interacciones por producto"],
            ["png" => "interacciones_ubicacion.png",        "titulo" => "Interacciones por ubicación"],
            ["png" => "sentimiento_producto.png",           "titulo" => "Sentimiento por producto"],
            ["png" => "tipo_publicacion_interacciones.png", "titulo" => "Tipo de publicación e interacciones"],
        ],
    ],
];


$fuente=GETPOST('fuente','aZ09');
if (!isset($otros[$fuente])) $fuente='';
$pythonDir=__DIR__.'/python/';
$graficoDir=$pythonDir.'graficos/';
$statusPath=$graficoDir.'report-status.json';
$status=is_file($statusPath)?json_decode(file_get_contents($statusPath),true):array();
if(!is_array($status)) $status=array();
$errors=empty($status['errors'])?array():array('Algunos reportes no pudieron actualizarse. Se conserva su última versión disponible.');
if(GETPOSTINT('status')===1) {
	header('Content-Type: application/json');
	echo json_encode(array('running'=>!empty($status['running']) || is_file($graficoDir.'.refresh-request'),'updated'=>$status['updated'] ?? 0));
	exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && GETPOST('action','aZ09')==='refresh') {
	// Dolibarr validates the POST CSRF token. This fixed marker only requests predefined reports.
	if(file_put_contents($graficoDir.'.refresh-request','1')===false) setEventMessages('No se pudo solicitar la actualización.',null,'errors');
	header('Location: '.dol_buildpath('/reportes/reportesindex.php',1).'?fuente='.urlencode($fuente).'&refresh=1');
	exit;
}
function rrReportCard($r,$directory,$index) {
	$file=$directory.$r['png'];
	$descriptions=array(
		'capacidad_flota.png'=>'Distribución de unidades por producto y estado operativo.',
		'incidencias_pendientes.png'=>'Tiempo de espera de los reportes que siguen abiertos o pendientes de sustitución.',
		'top_alquilados.png'=>'Frecuencia de asignación de equipos registrada en los rentings.',
		'equipos_condicion.png'=>'Condición registrada de los equipos de la flota.',
		'incidencias_tipo.png'=>'Distribución de las incidencias según su estado de atención.',
		'interacciones_plataforma.png'=>'Interacciones acumuladas por red social.',
		'interacciones_producto.png'=>'Productos que concentran la interacción en redes.',
		'interacciones_ubicacion.png'=>'Distribución geográfica de las interacciones.',
		'sentimiento_producto.png'=>'Publicaciones agrupadas por producto y sentimiento.',
		'tipo_publicacion_interacciones.png'=>'Interacciones acumuladas por formato de publicación.'
	);
	print '<article class="ra-card'.(in_array($r['png'],array('interacciones_producto.png','sentimiento_producto.png'))?' ra-wide':'').'"><header><span class="ra-number">'.sprintf('%02d',$index).'</span><div><h3>'.dol_escape_htmltag($r['titulo']).'</h3><p>'.dol_escape_htmltag($descriptions[$r['png']] ?? '').'</p></div></header>';
	if(is_file($file)) {
		$url='python/graficos/'.rawurlencode($r['png']).'?v='.filemtime($file);
		$updated=(new DateTimeImmutable('@'.filemtime($file)))->setTimezone(new DateTimeZone('America/Costa_Rica'))->format('d/m/Y H:i');
		print '<a class="ra-chart" href="'.$url.'" target="_blank" rel="noopener" aria-label="Ampliar '.dol_escape_htmltag($r['titulo']).' (abre otra pestaña)"><img src="'.$url.'" alt="'.dol_escape_htmltag($r['titulo']).'" loading="lazy"></a><footer><span>Actualizado: '.$updated.' · CR</span><a href="'.$url.'" target="_blank" rel="noopener">Ampliar gráfico ↗</a></footer>';
	} else print '<div class="ra-empty"><span class="fa fa-chart-bar" aria-hidden="true"></span><p>Este gráfico todavía no está disponible.</p><small>Volvé a actualizar cuando la fuente de datos esté conectada.</small></div>';
	print '</article>';
}

/* Views */
llxHeader('', 'Análisis de negocio');
print '<script defer src="'.dol_buildpath('/reportes/js/analytics.js',1).'?v=1"></script>';
print '<link rel="stylesheet" href="'.dol_buildpath('/reportes/css/analytics.css',1).'?v=1">';
print '<main class="ra-app"><header class="ra-hero"><div><div class="ra-eyebrow">R&R TECHNOLOGY · INTELIGENCIA DE NEGOCIO</div><h1>Análisis de negocio</h1><p>Una visión de la flota, la atención al cliente y la conversación en redes.</p></div><span class="ra-icon fa fa-chart-bar" aria-hidden="true"></span></header>';
print '<nav class="ra-nav" aria-label="Secciones de análisis"><a href="#prioritarios">Indicadores principales</a><a href="?fuente=mariadb#otros-graficos"'.($fuente==='mariadb'?' aria-current="page"':'').'>Operación</a><a href="?fuente=mongodb#otros-graficos"'.($fuente==='mongodb'?' aria-current="page"':'').'>Redes sociales</a><form method="post" class="ra-refresh"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="refresh"><input type="hidden" name="fuente" value="'.dol_escape_htmltag($fuente).'"><button type="submit">↻ Actualizar reportes</button></form></nav>';
if(GETPOST('refresh','int') || !empty($status['running'])) print '<p class="ra-notice" id="ra-updating" role="status">Actualizando los gráficos… El panel se recargará al terminar.</p>';
foreach(array_unique($errors) as $message) print '<p class="ra-notice" role="status">'.dol_escape_htmltag($message).'</p>';
print '<section id="prioritarios"><div class="ra-section-title"><div><span class="ra-eyebrow">PANORAMA OPERATIVO</span><h2>Indicadores principales</h2></div><span class="ra-source">ERP · MariaDB</span></div><div class="ra-grid ra-priority">';
foreach($top3 as $i=>$r) rrReportCard($r,$graficoDir,$i+1);
print '</div></section><section id="otros-graficos"><div class="ra-section-title"><div><span class="ra-eyebrow">EXPLORAR EN DETALLE</span><h2>'.($fuente==='mongodb'?'Redes sociales':'Más perspectivas del negocio').'</h2></div>'.($fuente?'<span class="ra-source">'.($fuente==='mongodb'?'MongoDB · Redes sociales':'ERP · MariaDB').'</span>':'').'</div>';
if(!$fuente) print '<div class="ra-explore"><a href="?fuente=mariadb#otros-graficos"><span class="fa fa-desktop" aria-hidden="true"></span><h3>Operación y servicio</h3><p>Revisá la condición de la flota y el estado de las incidencias.</p><strong>Ver 2 reportes →</strong></a><a href="?fuente=mongodb#otros-graficos"><span class="fa fa-comments" aria-hidden="true"></span><h3>Conversación en redes</h3><p>Explorá interacciones, productos y sentimiento de las publicaciones.</p><strong>Ver 5 reportes →</strong></a></div>';
else { print '<div class="ra-grid">'; foreach($otros[$fuente]['reportes'] as $i=>$r) rrReportCard($r,$graficoDir,$i+($fuente==='mongodb'?6:4)); print '</div>'; }
print '</section><p class="ra-footnote">Actualización automática cada 5 minutos o al solicitarla. Cada gráfico conserva el alcance de su fuente. Las fechas de actualización se muestran en hora de Costa Rica.</p></main>';
llxFooter();
$db->close();
