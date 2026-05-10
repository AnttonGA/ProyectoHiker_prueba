<?php
/**
 * Batch: resto de España (no alpinas) — hiking + mtb + cycling
 * Ejecutar: php import_batch_resto.php
 */
$php = 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$script = __DIR__ . '\\importar_osm.php';

$regiones = [
    // Navarra Sur + Euskadi + Rioja
    'navarra_sur', 'euskadi_occ', 'euskadi_ori', 'la_rioja',
    // Aragón interior
    'aragon_zaragoza', 'aragon_teruel',
    // Cataluña interior + costa
    'cat_central', 'cat_costa_nor', 'cat_costa_sur',
    // Galicia
    'galicia_nw', 'galicia_sw', 'galicia_ne', 'galicia_se',
    // Castilla y León (sin León ya importado)
    'burgos_norte', 'burgos_sur', 'palencia', 'zamora',
    'salamanca', 'valladolid', 'segovia', 'avila', 'soria',
    // CLM + Extremadura
    'cuenca', 'guadalajara', 'toledo', 'ciudad_real', 'albacete',
    'caceres', 'badajoz',
    // Andalucía
    'andalucia_jaen', 'andalucia_malaga', 'andalucia_huelva',
    'andalucia_cordoba', 'andalucia_sevilla', 'andalucia_cadiz', 'andalucia_almeria',
    // Levante + Baleares
    'valencia_norte', 'valencia_sur', 'murcia',
    'mallorca', 'menorca', 'ibiza',
];
$actividades = ['hiking', 'mtb', 'cycling'];

foreach ($regiones as $region) {
    foreach ($actividades as $actividad) {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo ">> {$region} / {$actividad}\n";
        echo str_repeat('=', 60) . "\n";
        passthru("{$php} \"{$script}\" --region={$region} --activity={$actividad} --sleep=2");
    }
}
echo "\n✅ Batch resto completado.\n";
