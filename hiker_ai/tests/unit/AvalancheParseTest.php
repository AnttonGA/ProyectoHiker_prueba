<?php
// php hiker_ai/tests/unit/AvalancheParseTest.php → lectura del nivel de aludes y vertiente (sin red)
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__, 3);
require_once $root . '/includes/env_loader.php';
load_env($root . '/.env');
require_once $root . '/hiker_ai/config/config.php';
require_once $root . '/vendor/autoload.php';

$ref = new ReflectionClass(KahirAI\Services\MountainConditionsService::class);
$svc = $ref->newInstanceWithoutConstructor();
$parse  = $ref->getMethod('parseAemetBPA');
$french = $ref->getMethod('isFrenchSide');

$texts = [
    'Peligro de aludes: 3 (notable) por encima de 2200 m; 2 (limitado) por debajo.' => 3,
    'Nivel de peligro 2 en el Alto Aragón, débil por debajo de 1800 m.'             => 2,
    'El peligro es notable en orientaciones norte.'                                 => 3,
    'Estado del manto en el Alto Aragón: nieve venteada por debajo de 2000 m. 2200 m: 3 cm.' => null,
    'Peligro débil (1) en general.'                                                 => 1,
];
$points = [
    'Benasque'   => [42.60, 0.52, false],
    'Candanchú'  => [42.79, -0.53, false],
    'Cauterets'  => [42.89, -0.11, true],
    'Luchon'     => [42.79, 0.60, true],
    'Bayona'     => [43.49, -1.47, true],
    'Pamplona'   => [42.81, -1.64, false],
    'Andorra'    => [42.51, 1.52, false],
    'Font-Romeu' => [42.50, 2.04, true],
];
$fail = 0;
foreach ($texts as $t => $want) {
    $got = $parse->invoke($svc, [$t], 'Pirineo')['level'] ?? null;
    $ok = $got === $want; if (!$ok) $fail++;
    printf("%s  nivel %-4s ← %s%s\n", $ok ? 'OK ' : 'MAL', var_export($got, true), mb_substr($t, 0, 70), $ok ? '' : ' (esperado ' . var_export($want, true) . ')');
}
foreach ($points as $name => [$lat, $lon, $want]) {
    $got = $french->invoke($svc, $lat, $lon);
    $ok = $got === $want; if (!$ok) $fail++;
    printf("%s  vertiente francesa %-11s → %s\n", $ok ? 'OK ' : 'MAL', $name, var_export($got, true));
}
echo $fail ? "\n$fail fallos\n" : "\nTodo correcto\n";
exit($fail ? 1 : 0);
