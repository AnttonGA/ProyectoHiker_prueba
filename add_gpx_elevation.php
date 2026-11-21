<?php
/**
 * ════════════════════════════════════════════════════════════════
 *  Kahir — Añade etiquetas <ele> a GPX importados de OSM
 * ════════════════════════════════════════════════════════════════
 *
 * Los GPX generados por importar_osm.php solo tienen lat/lon.
 * Este script añade elevación real via OpenTopoData (SRTM 30m)
 * para que el plugin Leaflet Elevation pueda mostrar el perfil.
 *
 * Uso:
 *   php add_gpx_elevation.php             → procesa todos los GPX sin ele
 *   php add_gpx_elevation.php --dry-run   → simula sin modificar archivos
 *   php add_gpx_elevation.php --force     → reprocesa aunque ya tengan ele
 *   php add_gpx_elevation.php --limit=50  → procesa máximo N archivos
 *
 * Rate limit OpenTopoData: 1 req/s, máx 100 puntos/req (usamos 80).
 * Estimación: ~1-2 segundos por ruta → ~30 min para 1000 rutas.
 */

declare(strict_types=1);


// Script de mantenimiento: solo se puede ejecutar desde la línea de comandos (php archivo.php),
// nunca desde el navegador. Antes cualquiera podía lanzarlo por web sin iniciar sesión.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo disponible desde la línea de comandos.');
}
$DRY_RUN = in_array('--dry-run', $argv ?? []);
$FORCE   = in_array('--force',   $argv ?? []);
$LIMIT   = (int) getArgVal('limit', 0);  // 0 = sin límite

define('ELEV_API',        'https://api.opentopodata.org/v1/srtm30m');
define('ELEV_MAX_PTS',    80);    // puntos por petición API
define('GPX_DIR',         __DIR__ . '/paginasespecializadas/skitouring/rutas_st/osm_import/');

// ── Banner ───────────────────────────────────────────────────────
log_msg("════════════════════════════════════════════════════");
log_msg("  Kahir — Añadir elevación a GPX OSM" . ($DRY_RUN ? "  ⚡ SIMULACIÓN" : ""));
log_msg("════════════════════════════════════════════════════");

// ── Listar archivos GPX ───────────────────────────────────────────
$files = glob(GPX_DIR . 'osm_*.gpx');
if (!$files) {
    log_msg("❌ No se encontraron GPX en: " . GPX_DIR);
    exit(1);
}
log_msg("📂 GPX encontrados: " . count($files));

$stats = ['procesados' => 0, 'ya_tienen_ele' => 0, 'errores' => 0, 'sin_puntos' => 0];
$count = 0;

foreach ($files as $filePath) {
    if ($LIMIT > 0 && $count >= $LIMIT) break;

    $fileName = basename($filePath);

    // ── Leer el GPX ──────────────────────────────────────────────
    $xml = @simplexml_load_file($filePath);
    if (!$xml) {
        log_msg("  ⚠️  XML inválido: {$fileName}");
        $stats['errores']++;
        continue;
    }

    // ── Comprobar si ya tiene ele ─────────────────────────────────
    $namespaces = $xml->getNamespaces(true);
    $ns = $namespaces[''] ?? '';
    $xml->registerXPathNamespace('g', $ns ?: 'http://www.topografix.com/GPX/1/1');

    $trkpts = $xml->xpath('//g:trkpt');
    if (empty($trkpts)) {
        log_msg("  ⏭️  Sin trkpts: {$fileName}");
        $stats['sin_puntos']++;
        continue;
    }

    // Comprobar si ya tiene elevación
    if (!$FORCE) {
        $hasEle = false;
        foreach ($trkpts as $pt) {
            if (isset($pt->ele)) { $hasEle = true; break; }
        }
        if ($hasEle) {
            $stats['ya_tienen_ele']++;
            continue;
        }
    }

    // ── Extraer todos los lat/lon ─────────────────────────────────
    $points = [];
    foreach ($trkpts as $pt) {
        $lat = (float)$pt['lat'];
        $lon = (float)$pt['lon'];
        if ($lat !== 0.0 || $lon !== 0.0) {
            $points[] = ['lat' => $lat, 'lon' => $lon];
        }
    }

    if (count($points) < 2) {
        log_msg("  ⏭️  Sin coordenadas válidas: {$fileName}");
        $stats['sin_puntos']++;
        continue;
    }

    // ── Obtener elevaciones de la API ─────────────────────────────
    $elevations = fetchElevations($points);
    if ($elevations === null) {
        log_msg("  ❌ API elevación falló: {$fileName}");
        $stats['errores']++;
        continue;
    }

    if (count($elevations) !== count($points)) {
        log_msg("  ❌ Respuesta incompleta API ({$fileName}): " . count($points) . " pts → " . count($elevations) . " elev");
        $stats['errores']++;
        continue;
    }

    $count++;

    if ($DRY_RUN) {
        $gain = calcGain($elevations);
        log_msg("  ✅ [{$count}] {$fileName}: " . count($points) . " pts, +{$gain}m (simulación)");
        $stats['procesados']++;
        continue;
    }

    // ── Reescribir el GPX con <ele> ───────────────────────────────
    $newGpx = rewriteGpxWithEle($filePath, $elevations);
    if ($newGpx === null) {
        log_msg("  ❌ Error reescribiendo: {$fileName}");
        $stats['errores']++;
        continue;
    }

    if (file_put_contents($filePath, $newGpx) === false) {
        log_msg("  ❌ Error guardando: {$fileName}");
        $stats['errores']++;
        continue;
    }

    $gain = calcGain($elevations);
    log_msg("  ✅ [{$count}] {$fileName}: " . count($points) . " pts → +{$gain}m");
    $stats['procesados']++;

    // Rate limit: 1 req/s (ya incluido en fetchElevations si hace múltiples llamadas)
}

// ── Resumen ───────────────────────────────────────────────────────
log_msg("");
log_msg("════════════════════════════════════════════════════");
log_msg("  RESUMEN" . ($DRY_RUN ? " — SIMULACIÓN" : ""));
log_msg("════════════════════════════════════════════════════");
log_msg("✅  GPX actualizados     : {$stats['procesados']}");
log_msg("⏭️   Ya tenían elevación  : {$stats['ya_tienen_ele']}");
log_msg("⚠️   Sin puntos válidos   : {$stats['sin_puntos']}");
log_msg("❌  Errores              : {$stats['errores']}");
log_msg("════════════════════════════════════════════════════\n");


// ═════════════════════════════════════════════════════════════════
//  FUNCIONES
// ═════════════════════════════════════════════════════════════════

/**
 * Obtiene elevaciones para un array de puntos lat/lon.
 * Divide en lotes de ELEV_MAX_PTS, respetando 1 req/s.
 * Devuelve array plano de floats en el mismo orden que $points,
 * o null si alguna petición falla.
 */
function fetchElevations(array $points): ?array
{
    $n         = count($points);
    $allElev   = [];
    $batchSize = ELEV_MAX_PTS;

    // Si hay muchos puntos, muestreamos uniformemente y luego interpolamos
    if ($n > $batchSize) {
        return fetchWithSamplingAndInterp($points);
    }

    // Pocos puntos: una sola petición
    $locs = implode('|', array_map(fn($p) => "{$p['lat']},{$p['lon']}", $points));
    $url  = ELEV_API . '?locations=' . $locs;

    $ctx = stream_context_create(['http' => ['timeout' => 20, 'user_agent' => 'KahirAI/1.0']]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return null;

    $data = json_decode($raw, true);
    if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) return null;

    foreach ($data['results'] as $r) {
        $allElev[] = (float)($r['elevation'] ?? 0);
    }
    sleep(1);
    return $allElev;
}

/**
 * Para rutas con muchos puntos: muestrea ELEV_MAX_PTS puntos uniformemente,
 * obtiene su elevación, e interpola linealmente para los intermedios.
 */
function fetchWithSamplingAndInterp(array $points): ?array
{
    $n    = count($points);
    $step = (int)ceil($n / ELEV_MAX_PTS);

    // Índices de los puntos muestreados
    $sampledIdx = [];
    for ($i = 0; $i < $n; $i += $step) {
        $sampledIdx[] = $i;
    }
    // Asegurar el último punto
    if (end($sampledIdx) !== $n - 1) {
        $sampledIdx[] = $n - 1;
    }

    $sampledPts = array_map(fn($i) => $points[$i], $sampledIdx);

    // Llamadas API en lotes de ELEV_MAX_PTS
    $sampledElev = [];
    $batches = array_chunk($sampledPts, ELEV_MAX_PTS);
    foreach ($batches as $batch) {
        $locs = implode('|', array_map(fn($p) => "{$p['lat']},{$p['lon']}", $batch));
        $url  = ELEV_API . '?locations=' . $locs;

        $ctx = stream_context_create(['http' => ['timeout' => 20, 'user_agent' => 'KahirAI/1.0']]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return null;

        $data = json_decode($raw, true);
        if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) return null;

        foreach ($data['results'] as $r) {
            $sampledElev[] = (float)($r['elevation'] ?? 0);
        }
        sleep(1);
    }

    if (count($sampledElev) !== count($sampledIdx)) return null;

    // Interpolar elevación para todos los puntos originales
    $allElev = array_fill(0, $n, 0.0);
    for ($s = 0; $s < count($sampledIdx) - 1; $s++) {
        $iStart = $sampledIdx[$s];
        $iEnd   = $sampledIdx[$s + 1];
        $eStart = $sampledElev[$s];
        $eEnd   = $sampledElev[$s + 1];
        $span   = $iEnd - $iStart;

        for ($i = $iStart; $i <= $iEnd; $i++) {
            $t = ($span > 0) ? ($i - $iStart) / $span : 0;
            $allElev[$i] = $eStart + ($eEnd - $eStart) * $t;
        }
    }

    return $allElev;
}

/**
 * Lee el GPX original como texto y añade/reemplaza <ele> en cada <trkpt>.
 * Usa regex en lugar de reescribir el XML completo para preservar
 * formato, comentarios, y namespaces originales.
 */
function rewriteGpxWithEle(string $filePath, array $elevations): ?string
{
    $content = file_get_contents($filePath);
    if ($content === false) return null;

    $idx = 0;
    $newContent = preg_replace_callback(
        '/<trkpt\s+lat="([^"]+)"\s+lon="([^"]+)"\s*(?:\/>|>.*?<\/trkpt>)/s',
        function ($m) use (&$idx, $elevations) {
            if (!isset($elevations[$idx])) {
                $idx++;
                return $m[0]; // sin cambio si nos quedamos sin elevaciones
            }
            $ele  = round($elevations[$idx], 1);
            $idx++;
            $lat  = $m[1];
            $lon  = $m[2];
            return "<trkpt lat=\"{$lat}\" lon=\"{$lon}\"><ele>{$ele}</ele></trkpt>";
        },
        $content
    );

    return $newContent;
}

/** Calcula desnivel positivo acumulado */
function calcGain(array $elevations): int
{
    $gain = 0;
    $prev = null;
    foreach ($elevations as $e) {
        if ($prev !== null && $e > $prev) $gain += $e - $prev;
        $prev = $e;
    }
    return (int)round($gain);
}

function log_msg(string $msg): void
{
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}

function getArgVal(string $key, mixed $default): mixed
{
    foreach ($GLOBALS['argv'] ?? [] as $arg) {
        if (preg_match("/^--{$key}=(.+)$/", $arg, $m)) {
            return $m[1];
        }
    }
    return $default;
}
