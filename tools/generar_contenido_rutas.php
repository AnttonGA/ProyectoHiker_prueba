<?php
/**
 * Completa el contenido de las rutas a partir de sus tracks GPX (CLI).
 *
 *   php tools/generar_contenido_rutas.php            → simulación: muestra qué haría
 *   php tools/generar_contenido_rutas.php --aplicar  → escribe en la BD
 *   … --limite=200                                   → procesa como mucho N rutas
 *
 * Para cada ruta publicada:
 *   - elevacion_max: cota máxima del track, si el GPX trae altitudes (<ele>) y el campo está vacío.
 *   - descripcion: si está vacía, una descripción breve y factual con los datos reales
 *     (tipo de recorrido, km, desnivel, cota máxima, dificultad, horas MIDE), marcada con
 *     descripcion_auto = 1. Nunca sobrescribe descripciones escritas por personas.
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

$sql = "SELECT id, titulo, tipo_ruta, dificultad, distancia_km, elevacion_ganada, elevacion_max,
               horas_mide, esfuerzo, gpx_path, ruta_carpeta, descripcion
        FROM rutas
        WHERE estado = 'publicada'
          AND ((descripcion IS NULL OR descripcion = '') OR elevacion_max IS NULL)
        ORDER BY id" . ($limit ? " LIMIT $limit" : '');
$rows = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

$updDesc = $db->prepare("UPDATE rutas SET descripcion = ?, descripcion_auto = 1 WHERE id = ? AND (descripcion IS NULL OR descripcion = '')");
$updEle  = $db->prepare("UPDATE rutas SET elevacion_max = ? WHERE id = ? AND elevacion_max IS NULL");

$stats = ['rutas' => count($rows), 'sin_gpx' => 0, 'cota' => 0, 'descripcion' => 0];
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

    if (trim((string) $r['descripcion']) === '') {
        $desc = buildDescription($r, $track);
        $stats['descripcion']++;
        if (count($samples) < 5) $samples[] = "#{$r['id']} {$r['titulo']}\n   → $desc";
        if ($apply) { $updDesc->bind_param('si', $desc, $r['id']); $updDesc->execute(); }
    }
}

echo ($apply ? "APLICADO" : "SIMULACIÓN (usa --aplicar para escribir)") . "\n";
printf("Rutas revisadas: %d · sin GPX legible: %d · cotas máximas: %d · descripciones: %d\n",
    $stats['rutas'], $stats['sin_gpx'], $stats['cota'], $stats['descripcion']);
echo "\nEjemplos:\n" . implode("\n", $samples) . "\n";

// ── Funciones ────────────────────────────────────────────────────────────────

/** Lee el GPX: cota máxima/mínima (si hay <ele>) y si el recorrido es circular. */
function readTrack(array $r, array $dirs): ?array {
    $file = basename((string) $r['gpx_path']);
    if ($file === '') return null;
    $path = null;
    foreach ($dirs as $d) {
        $p = sprintf($d, (string) $r['ruta_carpeta']) . $file;
        if (is_file($p)) { $path = $p; break; }
    }
    if (!$path || filesize($path) > 30 * 1024 * 1024) return null;

    $prev = libxml_use_internal_errors(true);
    $xml  = simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NONET);
    libxml_use_internal_errors($prev);
    if (!$xml) return null;
    $xml->registerXPathNamespace('g', $xml->getNamespaces()[''] ?? 'http://www.topografix.com/GPX/1/1');
    $pts = $xml->xpath('//g:trkpt') ?: $xml->xpath('//g:rtept') ?: $xml->xpath('//trkpt') ?: [];
    if (count($pts) < 2) return null;

    $eles = [];
    foreach ($pts as $pt) {
        $e = isset($pt->ele) ? (string) $pt->ele : '';
        if ($e !== '' && is_numeric($e) && (float) $e > -100 && (float) $e < 4900) $eles[] = (float) $e;
    }
    $first = $pts[0];
    $last  = $pts[count($pts) - 1];
    $gap = haversineM((float) $first['lat'], (float) $first['lon'], (float) $last['lat'], (float) $last['lon']);
    return [
        'max_ele'  => count($eles) >= 2 ? round(max($eles)) : null,
        'min_ele'  => count($eles) >= 2 ? round(min($eles)) : null,
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
    $dif = ['beginner' => 'fácil', 'intermediate' => 'intermedia', 'advanced' => 'difícil', 'expert' => 'muy difícil'][$r['dificultad']] ?? null;
    $num = fn(float $v, int $dec = 0) => number_format($v, $dec, ',', '.');

    $tipo = $track === null ? 'Ruta' : ($track['circular'] ? 'Ruta circular' : 'Ruta lineal');
    $txt  = "$tipo de $act de " . $num((float) $r['distancia_km'], 1) . ' km';
    if ((int) $r['elevacion_ganada'] > 0) $txt .= ' con ' . $num((float) $r['elevacion_ganada']) . ' m de desnivel positivo';
    if (!empty($r['elevacion_max'])) $txt .= ', que alcanza los ' . $num((float) $r['elevacion_max']) . ' m de altitud';
    $txt .= '.';
    $parts = [];
    if ($dif) $parts[] = "Nivel $dif";
    if (!empty($r['esfuerzo'])) $parts[] = "esfuerzo MIDE {$r['esfuerzo']}/5";
    if ($parts) $txt .= ' ' . implode(', ', $parts);
    if (!empty($r['horas_mide']) && in_array($r['tipo_ruta'], ['hiking', 'ski_touring'], true)) {
        $h = (float) $r['horas_mide'];
        if ($h > 12) {
            $etapas = (int) ceil($h / 7);
            return rtrim($txt, '.') . ($parts ? '. ' : ' ') . "Recorrido largo para hacer en varias etapas (unas {$etapas} jornadas de marcha según el método MIDE)."
                 . ' Descripción generada automáticamente a partir del track.';
        }
        $hh = (int) floor($h);
        $mm = (int) round(($h - $hh) * 60 / 5) * 5;
        if ($mm === 60) { $hh++; $mm = 0; }
        $txt .= ($parts ? ' y unas ' : ' Unas ') . $hh . ' h' . ($mm ? " $mm min" : '') . ' de marcha según el método MIDE';
    }
    return rtrim($txt, '.') . '. Descripción generada automáticamente a partir del track.';
}
