<?php

require '../../main.inc.php';

llxHeader("", "Reportes");

print load_fiche_titre("Reportes");

$pythonDir  = __DIR__ . "/python/";
$graficoDir = $pythonDir . "graficos/";

// Asegurar que el directorio de salida existe y es escribible
if (!is_dir($graficoDir)) {
    mkdir($graficoDir, 0775, true);
}

// Verificar que shell_exec esté disponible
$disabled = array_map('trim', explode(',', ini_get('disable_functions')));
if (!function_exists('shell_exec') || in_array('shell_exec', $disabled)) {
    print '<div class="error">shell_exec está deshabilitado en PHP (disable_functions). '
        . 'Hay que habilitarlo en php.ini o usar otro mecanismo (cron, cola de trabajos, etc.).</div>';
} else {

    $python = "/usr/bin/python3"; // ruta absoluta, evita depender del PATH de www-data

    $scripts = [
        "capacidad_flota.py",
        "incidencias_pendientes.py",
        "top_alquilados.py",
    ];

    foreach ($scripts as $script) {
        $cmd = escapeshellcmd($python) . " " .
               escapeshellarg($pythonDir . $script) . " 2>&1";
        $output = shell_exec($cmd);

        // Mostrar la salida solo si hay error, para depurar (quitar en producción)
        if (!empty($output)) {
            print '<div class="warning" style="white-space:pre-wrap;font-family:monospace;">';
            print '<strong>' . dol_escape_htmltag($script) . ':</strong><br>';
            print dol_escape_htmltag($output);
            print '</div>';
        }
    }
}

// Cache-busting para forzar recarga de imágenes tras regenerarlas
$v = time();

print '<div class="fichecenter">';

print '<div class="fichehalfleft">';
print '<h3>Capacidad de la flota</h3>';
if (file_exists($graficoDir . "capacidad_flota.png")) {
    print '<img src="python/graficos/capacidad_flota.png?v=' . $v . '" style="width:100%; max-width:700px;">';
} else {
    print '<div class="warning">Gráfico no generado.</div>';
}
print '</div>';

print '<div class="fichehalfright">';
print '<h3>Incidencias pendientes</h3>';
if (file_exists($graficoDir . "incidencias_pendientes.png")) {
    print '<img src="python/graficos/incidencias_pendientes.png?v=' . $v . '" style="width:100%; max-width:700px;">';
} else {
    print '<div class="warning">Gráfico no generado.</div>';
}
print '</div>';

print '</div>';

print '<div class="fichecenter">';
print '<h3>Equipos más alquilados</h3>';
if (file_exists($graficoDir . "top_alquilados.png")) {
    print '<img src="python/graficos/top_alquilados.png?v=' . $v . '" style="width:100%; max-width:900px;">';
} else {
    print '<div class="warning">Gráfico no generado.</div>';
}
print '</div>';

llxFooter();

$db->close();