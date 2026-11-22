<?php
// php hiker_ai/tests/unit/TripDateTest.php  → pruebas de TripDate con una fecha fija (domingo 22-11-2026)
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../../core/TripDate.php';
use KahirAI\Core\TripDate;

$now = new DateTimeImmutable('2026-11-22 10:00', new DateTimeZone('Europe/Madrid')); // domingo
$cases = [
    'Quiero hacer una ruta este sábado'                => ['2026-11-28'],
    'el sábado por la mañana'                         => ['2026-11-28'],
    'mañana por la mañana'                            => ['2026-11-23'],
    'esta mañana'                                     => ['2026-11-22'],
    'hoy'                                             => ['2026-11-22'],
    'pasado mañana'                                   => ['2026-11-24'],
    'entre el sábado y el domingo'                    => ['2026-11-28', '2026-11-29'],
    'el domingo'                                      => ['2026-11-22'],
    'el próximo domingo'                              => ['2026-11-29'],
    'este fin de semana'                              => ['2026-11-22'],
    'el finde que viene'                              => ['2026-11-28', '2026-11-29'],
    '31 de febrero'                                   => null,
    '5 de diciembre'                                  => ['2026-12-05'],
    '3 de enero'                                      => ['2027-01-03'],
    'rutas por la mañana cerca de Jaca'               => null,
    'Can you suggest a hike for Saturday?'            => ['2026-11-28'],
    'tomorrow morning'                                => ['2026-11-23'],
    'rutas en Ordesa'                                 => null,
];
$fail = 0;
foreach ($cases as $q => $want) {
    $got = TripDate::resolve($q, $now);
    $got = $got['dates'] ?? null;
    $ok = $got === $want;
    if (!$ok) $fail++;
    printf("%s  %-40s → %s%s\n", $ok ? 'OK ' : 'MAL', $q, json_encode($got), $ok ? '' : '  (esperado ' . json_encode($want) . ')');
}
echo $fail ? "\n$fail fallos\n" : "\nTodo correcto\n";
exit($fail ? 1 : 0);
