<?php

require '../../main.inc.php';

llxHeader("", "Reportes");

print load_fiche_titre("Reportes");

$pythonDir  = __DIR__ . "/python/";
$graficoDir = $pythonDir . "graficos/";
$python     = "/usr/bin/python3"; // ruta absoluta, evita depender del PATH de www-data

// Asegurar que el directorio de salida existe
if (!is_dir($graficoDir)) {
    mkdir($graficoDir, 0775, true);
}

// ---------------------------------------------------------------------------
// Definición de reportes
// ---------------------------------------------------------------------------
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
            ["script" => "mariaDB/incidencias_tipo.py",  "png" => "incidencias_tipo.png",  "titulo" => "Incidencias por tipo"],
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

// Fuente seleccionada (lista blanca para evitar valores arbitrarios)
$fuente = GETPOST('fuente', 'aZ09');
if (!isset($otros[$fuente])) {
    $fuente = '';
}

// ---------------------------------------------------------------------------
// Funciones auxiliares
// ---------------------------------------------------------------------------

// Recibe una lista de nombres de script (strings)
function ejecutarScripts(array $scripts, $python, $pythonDir)
{
    $disabled = array_map('trim', explode(',', ini_get('disable_functions')));
    if (!function_exists('shell_exec') || in_array('shell_exec', $disabled)) {
        print '<div class="error">shell_exec está deshabilitado en PHP (disable_functions). '
            . 'Hay que habilitarlo en php.ini o usar otro mecanismo (cron, cola de trabajos, etc.).</div>';
        return;
    }

    foreach ($scripts as $script) {
        if (empty($script)) {
            continue; // evita ejecutar python sobre una carpeta
        }

        $cmd = escapeshellcmd($python) . " " .
            escapeshellarg($pythonDir . $script) . " 2>&1";
        $output = shell_exec($cmd);

        // Mostrar la salida solo si parece un error
        if (!empty($output)
            && (stripos($output, 'Traceback') !== false
                || stripos($output, 'Error') !== false
                || stripos($output, "can't") !== false)) {
            print '<div class="warning" style="white-space:pre-wrap;font-family:monospace;">';
            print '<strong>' . dol_escape_htmltag($script) . ':</strong><br>';
            print dol_escape_htmltag($output);
            print '</div>';
        }
    }
}

function mostrarGrafico(array $r, $graficoDir, $v, $maxWidth = 700)
{
    print '<h3>' . dol_escape_htmltag($r['titulo']) . '</h3>';
    if (file_exists($graficoDir . $r['png'])) {
        print '<img src="python/graficos/' . $r['png'] . '?v=' . $v
            . '" style="width:100%; max-width:' . (int) $maxWidth . 'px;">';
    } else {
        print '<div class="warning">Gráfico no generado.</div>';
    }
}

// Cache-busting para forzar recarga de imágenes tras regenerarlas
$v = time();

// ---------------------------------------------------------------------------
// TOP 3 (se carga siempre)
// ---------------------------------------------------------------------------
ejecutarScripts(array_column($top3, 'script'), $python, $pythonDir);

print '<div class="fichecenter">';
print '<h2>Top 3</h2>';
print '</div>';

print '<div class="fichecenter">';

print '<div class="fichehalfleft">';
mostrarGrafico($top3[0], $graficoDir, $v);
print '</div>';

print '<div class="fichehalfright">';
mostrarGrafico($top3[1], $graficoDir, $v);
print '</div>';

print '</div>';

print '<div class="fichecenter">';
mostrarGrafico($top3[2], $graficoDir, $v, 900);
print '</div>';

// ---------------------------------------------------------------------------
// OTROS GRÁFICOS (se carga solo la fuente elegida)
// ---------------------------------------------------------------------------
print '<div class="fichecenter" id="otros-graficos">';
print '<br><h2>Otros gráficos</h2>';

print '<div class="tabsAction" style="text-align:left;">';
foreach ($otros as $clave => $info) {
    $href   = $_SERVER['PHP_SELF'] . '?fuente=' . $clave . '#otros-graficos';
    $estilo = ($clave === $fuente) ? '' : ' style="cursor:pointer;opacity:.7;"';
    print '<a class="butAction"' . $estilo . ' href="' . dol_escape_htmltag($href) . '">'
        . dol_escape_htmltag($info['label']) . '</a>';
}
print '</div>';
print '</div>';

if ($fuente === '') {
    print '<div class="fichecenter"><div class="opacitymedium">'
        . 'Selecciona una fuente para cargar los gráficos.</div></div>';
} else {
    $reportes = $otros[$fuente]['reportes'];

    // Si la fuente tiene un script único (Mongo) se ejecuta una sola vez;
    // si no, se ejecuta el script de cada reporte (MariaDB)
    if (isset($otros[$fuente]['script'])) {
        $scripts = [$otros[$fuente]['script']];
    } else {
        $scripts = array_column($reportes, 'script');
    }
    ejecutarScripts($scripts, $python, $pythonDir);

    print '<div class="fichecenter">';
    print '<h3 style="margin-top:0;">Reportes de ' . dol_escape_htmltag($otros[$fuente]['label']) . '</h3>';
    print '</div>';

    // Dos por fila, en columnas izquierda/derecha
    $chunks = array_chunk($reportes, 2);
    foreach ($chunks as $fila) {
        print '<div class="fichecenter">';

        if (count($fila) === 2) {
            print '<div class="fichehalfleft">';
            mostrarGrafico($fila[0], $graficoDir, $v);
            print '</div>';

            print '<div class="fichehalfright">';
            mostrarGrafico($fila[1], $graficoDir, $v);
            print '</div>';
        } else {
            // Elemento suelto (última fila impar): ancho completo
            mostrarGrafico($fila[0], $graficoDir, $v, 900);
        }

        print '</div>';
    }
}

llxFooter();

$db->close();