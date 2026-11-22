<?php
// php hiker_ai/tests/unit/FollowupDetectionTest.php → clasificación de seguimientos sobre rutas mostradas (sin red ni BD)
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__, 3);
require_once $root . '/vendor/autoload.php';

$ref    = new ReflectionClass(KahirAI\Core\AICore::class);
$core   = $ref->newInstanceWithoutConstructor();
$detect = $ref->getMethod('detectFollowupIntent');
$parse  = $ref->getMethod('parseFilterCriteria');
$ctx    = ['active_routes' => [1, 2, 3]];

$cases = [
    '¿Cuál será el tiempo el sábado?'               => null,
    '¿Qué tiempo va a hacer allí?'                  => null,
    '¿Es seguro ir allí con niebla?'                => null,
    'Compara las dos primeras'                      => 'conjunto',
    '¿Cuál es la más segura?'                       => 'conjunto',
    '¿Cuál me conviene si no quiero pasar de 3 horas?' => 'conjunto',
    'Vale, apúntame la primera para el sábado'      => 'save',
    'Guárdame la segunda'                           => 'save',
    '¿Y algo más corto?'                            => 'filter',
    '¿Alguna más cerca?'                            => 'filter',
    'Cuéntame más de la tercera'                    => 'describe',
    'Busca rutas en Ordesa'                         => null,
];
$fail = 0;
foreach ($cases as $q => $want) {
    $got = $detect->invoke($core, $q, $ctx)['followup_subtype'] ?? null;
    $ok = $got === $want; if (!$ok) $fail++;
    printf("%s  %-50s → %s\n", $ok ? 'OK ' : 'MAL', $q, var_export($got, true) . ($ok ? '' : ' (esperado ' . var_export($want, true) . ')'));
}
$idx = $detect->invoke($core, 'Guárdame la segunda', $ctx)['ordinal_index'] ?? null;
if ($idx !== 1) { $fail++; echo "MAL  ordinal de 'la segunda' = " . var_export($idx, true) . "\n"; } else echo "OK   ordinal de 'la segunda' = 1\n";

$shown = [['id' => 1, 'distancia_km' => 8, 'elevacion_ganada' => 500, 'horas_mide' => 3, 'dist_km' => 6],
          ['id' => 2, 'distancia_km' => 12, 'elevacion_ganada' => 800, 'horas_mide' => 4.5, 'dist_km' => 11]];
$f = $parse->invoke($core, '¿alguna más cerca?', $shown, ['radius_km' => 20]);
$ok = ($f['radius_km'] ?? null) == 6 && in_array(1, $f['exclude_ids'] ?? [], true); if (!$ok) $fail++;
printf("%s  'más cerca' → radio %s km, excluye las mostradas\n", $ok ? 'OK ' : 'MAL', var_export($f['radius_km'] ?? null, true));
$f = $parse->invoke($core, '¿alguna con menos horas?', $shown, []);
$ok = ($f['max_duration'] ?? null) == 2.7; if (!$ok) $fail++;
printf("%s  'menos horas' → máx %s h\n", $ok ? 'OK ' : 'MAL', var_export($f['max_duration'] ?? null, true));
$f = $parse->invoke($core, '¿alguna más chula?', $shown, []);
$ok = $f === []; if (!$ok) $fail++;
printf("%s  sin criterio reconocible → sin filtros (%s)\n", $ok ? 'OK ' : 'MAL', json_encode($f));

echo $fail ? "\n$fail fallos\n" : "\nTodo correcto\n";
exit($fail ? 1 : 0);
