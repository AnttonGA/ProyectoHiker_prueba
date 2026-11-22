<?php
/**
 * Completa el contenido de las rutas a partir de sus tracks GPX (CLI).
 *
 *   php tools/generar_contenido_rutas.php                    → simulación: muestra qué haría
 *   php tools/generar_contenido_rutas.php --aplicar          → escribe en la BD
 *   … --limite=200                                           → procesa como mucho N rutas
 *   … --regenerar-auto                                       → rehace también las descripciones automáticas ya escritas
 *
 * Para cada ruta publicada:
 *   - elevacion_max: cota máxima del track, si el GPX trae altitudes (<ele>) y el campo está vacío.
 *   - descripcion: si está vacía (o es automática y se pide regenerar), una descripción breve y factual
 *     con los datos reales (tipo de recorrido, km, desnivel, cota máxima, dificultad, horas MIDE),
 *     marcada con descripcion_auto = 1. Nunca sobrescribe descripciones escritas por personas.
 *     Las rutas sin distancia registrada no reciben descripción (sería "0,0 km").
 *
 * Requiere database/migration_asistente.sql.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo disponible desde la línea de comandos.');
}

$root = dirname(__DIR__);
require_once $root . '/includes/env_loader.php';
load_env($root . '/.env');

$apply = in_array('--aplicar', $argv, true);
$regen = in_array('--regenerar-auto', $argv, true);
$limit = 0;
foreach ($argv as $a) {
    if (preg_match('/^--limite=(\d+)$/', $a, $m)) $limit = (int) $m[1];
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(env('DB_HOST', 'localhost'), env('DB_USER', 'root'), env('DB_PASS', ''), env('DB_NAME', 'kahir_db'));
$db->set_charset('utf8mb4');

$hasCol = $db->query("SHOW COLUMNS FROM rutas LIKE 'descripcion_auto'")->num_rows > 0;
if (!$hasCol) {
    fwrite(STDERR, "Falta la columna rutas.descripcion_auto: aplica antes database/migration_asistente.sql\n");
    exit(1);
}

$GPX_DIRS = [
    $root . '/paginasespecializadas/skitouring/rutas_st/%s/',
    $root . '/paginasespecializadas/skitouring/rutas_st/osm_import/',
];

$emptyDesc = "TRIM(COALESCE(descripcion, '')) = ''";
$sql = "SELECT id, titulo, tipo_ruta, dificultad, distancia_km, elevacion_ganada, elevacion_max,
               horas_mide, esfuerzo, gpx_path, ruta_carpeta, descripcion, descripcion_auto
        FROM rutas
        WHERE estado = 'publicada'
          AND ($emptyDesc OR elevacion_max IS NULL" . ($regen ? " OR descripcion_auto = 1" : '') . ")
        ORDER BY id" . ($limit ? " LIMIT $limit" : '');
$rows = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

$updDesc = $db->prepare("UPDATE rutas SET descripcion = ?, descripcion_auto = 1 WHERE id = ? AND ($emptyDesc OR descripcion_auto = 1)");
$updEle  = $db->prepare("UPDATE rutas SET elevacion_max = ? WHERE id = ? AND elevacion_max IS NULL");
$clrDesc = $db->prepare("UPDATE rutas SET descripcion = NULL, descripcion_auto = 0 WHERE id = ? AND descripcion_auto = 1");

$stats = ['rutas' => count($rows), 'sin_gpx' => 0, 'cota' => 0, 'descripcion' => 0, 'sin_distancia' => 0];
$samples = [];

foreach ($rows as $r) {
    $track = readTrack($r, $GPX_DIRS);
    if ($track === null) $stats['sin_gpx']++;

    $maxEle = $track['max_ele'] ?? null;
    if ($maxEle !== null && $r['elevacion_max'] === null) {
        $stats['cota']++;
        if ($apply) { $updEle->bind_param('di', $maxEle, $r['id']); $updEle->execute(); }
        $r['elevacion_max'] = $maxEle;
    }

    $needsDesc = trim((string) $r['descripcion']) === '' || ($regen && (int) $r['descripcion_auto'] === 1);
    if ($needsDesc) {
        if ((float) $r['distancia_km'] < 0.1) {
            // Sin distancia no hay descripción posible; si había una automática ("0,0 km"), se quita
            $stats['sin_distancia']++;
            if ($apply && (int) $r['descripcion_auto'] === 1) { $clrDesc->bind_param('i', $r['id']); $clrDesc->execute(); }
            continue;
        }
        $desc = buildDescription($r, $track);
        if ($desc === trim((string) $r['descripcion'])) continue;
        $stats['descripcion']++;
        if (count($samples) < 5) $samples[] = "#{$r['id']} {$r['titulo']}\n   → $desc";
        if ($apply) { $updDesc->bind_param('si', $desc, $r['id']); $updDesc->execute(); }
    }
}

echo ($apply ? "APLICADO" : "SIMULACIÓN (usa --aplicar para escribir)") . "\n";
printf("Rutas revisadas: %d · sin GPX legible: %d · cotas máximas: %d · descripciones: %d · sin distancia (omitidas): %d\n",
    $stats['rutas'], $stats['sin_gpx'], $stats['cota'], $stats['descripcion'], $stats['sin_distancia']);
echo "\nEjemplos:\n" . implode("\n", $samples) . "\n";

// ── Funciones ────────────────────────────────────────────────────────────────

/**
 * Lee el GPX en streaming (XMLReader: sirve para ficheros grandes sin cargarlos en memoria):
 * cota máxima/mínima (si hay <ele>) y si el recorrido es circular. Acepta trkpt y rtept,
 * con o sin espacio de nombres.
 */
function readTrack(array $r, array $dirs): ?array {
    $file = basename((string) $r['gpx_path']);
    if ($file === '') return null;
    $path = null;
    foreach ($dirs as $d) {
        $p = sprintf($d, (string) $r['ruta_carpeta']) . $file;
        if (is_file($p)) { $path = $p; break; }
    }
    if (!$path) return null;

    $xr = new XMLReader();
    if (!@$xr->open($path, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) return null;

    $first = $last = null;
    $count = 0;
    $maxE = $minE = null;
    $eleCount = 0;
    $usePoint = null;               // 'trkpt' si hay track; si no, 'rtept'
    $inPoint = false;
    try {
        while (@$xr->read()) {
            if ($xr->nodeType === XMLReader::ELEMENT) {
                $name = $xr->localName;
                if ($name === 'trkpt' || $name === 'rtept') {
                    if ($usePoint === null) $usePoint = $name;
                    if ($name !== $usePoint) continue;
                    $pt = [(float) $xr->getAttribute('lat'), (float) $xr->getAttribute('lon')];
                    $first ??= $pt;
                    $last = $pt;
                    $count++;
                    $inPoint = !$xr->isEmptyElement;
                } elseif ($name === 'ele' && $inPoint) {
                    $e = trim($xr->readString());
                    if ($e !== '' && is_numeric($e) && (float) $e > -100 && (float) $e < 4900) {
                        $v = (float) $e;
                        $maxE = $maxE === null ? $v : max($maxE, $v);
                        $minE = $minE === null ? $v : min($minE, $v);
                        $eleCount++;
                    }
                }
            } elseif ($xr->nodeType === XMLReader::END_ELEMENT && $xr->localName === $usePoint) {
                $inPoint = false;
            }
        }
    } finally {
        $xr->close();
    }
    if ($count < 2) return null;

    $gap = haversineM($first[0], $first[1], $last[0], $last[1]);
    return [
        'max_ele'  => $eleCount >= 2 ? round($maxE) : null,
        'min_ele'  => $eleCount >= 2 ? round($minE) : null,
        'circular' => $gap < 300,
    ];
}

function haversineM(float $a, float $b, float $c, float $d): float {
    $dLat = deg2rad($c - $a);
    $dLon = deg2rad($d - $b);
    $x = sin($dLat / 2) ** 2 + cos(deg2rad($a)) * cos(deg2rad($c)) * sin($dLon / 2) ** 2;
    return 12742000 * asin(min(1, sqrt($x)));
}

/** Descripción breve y factual: solo datos que constan en la BD o en el track. */
function buildDescription(array $r, ?array $track): string {
    $act = ['hiking' => 'senderismo', 'ski_touring' => 'esquí de travesía', 'btt' => 'BTT', 'cycle_touring' => 'cicloturismo'][$r['tipo_ruta']] ?? 'montaña';
    $dif = ['beginner' => 'fácil', 'intermediate' => 'intermedio', 'advanced' => 'difícil', 'expert' => 'muy difícil'][$r['dificultad']] ?? null;
    $num = fn(float $v, int $dec = 0) => number_format($v, $dec, ',', '.');
    // El método MIDE es para recorridos a pie: en bici no se citan ni su esfuerzo ni sus horas
    $onFoot = in_array($r['tipo_ruta'], ['hiking', 'ski_touring'], true);

    $tipo = $track === null ? 'Ruta' : ($track['circular'] ? 'Ruta circular' : 'Ruta lineal');
    $txt  = "$tipo de $act de " . $num((float) $r['distancia_km'], 1) . ' km';
    if ((int) $r['elevacion_ganada'] > 0) $txt .= ' con ' . $num((float) $r['elevacion_ganada']) . ' m de desnivel positivo';
    if (!empty($r['elevacion_max'])) $txt .= ', que alcanza los ' . $num((float) $r['elevacion_max']) . ' m de altitud';
    $txt .= '.';

    $parts = [];
    if ($dif) $parts[] = "Nivel $dif";
    if ($onFoot && !empty($r['esfuerzo'])) $parts[] = "esfuerzo MIDE {$r['esfuerzo']}/5";

    $tail = '';
    if ($onFoot && !empty($r['horas_mide'])) {
        $h = (float) $r['horas_mide'];
        if ($h > 12) {
            $etapas = (int) ceil($h / 7);
            $sentences = [];
            if ($parts) $sentences[] = implode(', ', $parts) . '.';
            $sentences[] = "Recorrido largo para hacer en varias etapas (unas {$etapas} jornadas de marcha según el método MIDE).";
            return $txt . ' ' . implode(' ', $sentences) . ' Descripción generada automáticamente a partir del track.';
        }
        $hh = (int) floor($h);
        $mm = (int) round(($h - $hh) * 60 / 5) * 5;
        if ($mm === 60) { $hh++; $mm = 0; }
        $tail = ($parts ? ' y unas ' : 'Unas ') . $hh . ' h' . ($mm ? " $mm min" : '') . ' de marcha según el método MIDE';
    }
    if ($parts || $tail) $txt .= ' ' . implode(', ', $parts) . $tail . '.';
    return $txt . ' Descripción generada automáticamente a partir del track.';
}
