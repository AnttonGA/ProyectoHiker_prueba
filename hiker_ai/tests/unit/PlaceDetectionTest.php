<?php
// php hiker_ai/tests/unit/PlaceDetectionTest.php → detección de topónimos y de zonas fuera de cobertura (sin red ni BD)
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__, 3);
require_once $root . '/vendor/autoload.php';

$ref  = new ReflectionClass(KahirAI\Core\AICore::class);
$core = $ref->newInstanceWithoutConstructor();
$find = $ref->getMethod('findSpecificLocationInQuery');
$ooc  = $ref->getMethod('isZoneOutOfCoverage');

$places = [
    'Rutas cerca de Bilbao que no haya hecho'        => null,
    'De hecho prefiero algo corto'                    => null,
    'Una ruta con fuente de agua'                     => null,
    'Algo de descanso cerca del resort'               => null,
    'Rutas por el valle de Hecho'                     => 'hecho',
    'Quiero ir a Fuente De'                           => 'fuente de',
    'Rutas en Sort'                                   => 'sort',
    'Rutas en el Valle de Tena'                       => 'valle de tena',
    'Quiero subir al Gorbea'                          => 'gorbea',
    'Una ruta en Ansó'                                => 'anso',
    'Busco una ruta por Panticosa para el sábado'     => 'panticosa',
];
$cover = [
    'Rutas de senderismo en los Alpes suizos'         => true,
    'Una ruta en la que no andes mucho'               => false,
    'Algo como los Alpes pero cerca de Benasque'      => false,
    'Trekking en los Andes'                           => true,
    'Quiero ir a Suiza'                               => true,
    'rutas por Andorra'                               => false,
];
$fail = 0;
foreach ($places as $q => $want) {
    $got = $find->invoke($core, $q)['key'] ?? null;
    $ok = $got === $want; if (!$ok) $fail++;
    printf("%s  %-46s → %s\n", $ok ? 'OK ' : 'MAL', $q, var_export($got, true) . ($ok ? '' : ' (esperado ' . var_export($want, true) . ')'));
}
foreach ($cover as $q => $want) {
    $got = $ooc->invoke($core, $q, true);
    $ok = $got === $want; if (!$ok) $fail++;
    printf("%s  fuera de cobertura: %-30s → %s\n", $ok ? 'OK ' : 'MAL', $q, var_export($got, true));
}
echo $fail ? "\n$fail fallos\n" : "\nTodo correcto\n";
exit($fail ? 1 : 0);
