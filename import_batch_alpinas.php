<?php
/**
 * Batch: regiones alpinas/pirenaicas — hiking + ski + mtb
 * Ejecutar: php import_batch_alpinas.php
 */
$php = 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$script = __DIR__ . '\\importar_osm.php';

$regiones    = [
    'navarra_norte',
    'aragon_huesca_no', 'aragon_huesca_ne', 'aragon_huesca_sur',
    'cat_pir_occ', 'cat_pir_ori',
    'cat_prepirineo_occ', 'cat_prepirineo_ori',
    'sierra_nevada',
    'madrid',
];
$actividades = ['hiking', 'ski', 'mtb'];

foreach ($regiones as $region) {
    foreach ($actividades as $actividad) {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo ">> {$region} / {$actividad}\n";
        echo str_repeat('=', 60) . "\n";
        passthru("{$php} \"{$script}\" --region={$region} --activity={$actividad} --sleep=2");
    }
}
echo "\n✅ Batch alpinas completado.\n";
