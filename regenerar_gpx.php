<?php
/**
 * ════════════════════════════════════════════════════════════════
 *  Kahir — Regenera GPX de rutas OSM con el algoritmo corregido
 * ════════════════════════════════════════════════════════════════
 *
 * Las rutas importadas antes de que se corrigiera el ensamblado de ways
 * pueden tener grandes líneas rectas en el track. Este script:
 *   1. Detecta GPX con segmentos sospechosamente largos (> UMBRAL_KM)
 *   2. Re-descarga la geometría de la relación OSM via Overpass
 *   3. Reconstruye el GPX con el algoritmo greedy de ensamblado
 *   4. Guarda el GPX corregido (sobrescribe el original)
 *
 * Uso:
 *   php regenerar_gpx.php                → detecta y regenera automáticamente
 *   php regenerar_gpx.php --dry-run      → muestra qué regeneraría sin cambiar
 *   php regenerar_gpx.php --all          → regenera TODOS (no solo sospechosos)
 *   php regenerar_gpx.php --id=12345     → regenera solo la ruta con ese osm_id
 *   php regenerar_gpx.php --limit=50     → procesa máximo N rutas
 *
 * Nota: usa el mismo algoritmo extractPoints() que importar_osm.php.
 */

declare(strict_types=1);

$DRY_RUN    = in_array('--dry-run', $argv ?? []);
$ALL        = in_array('--all',     $argv ?? []);
$LIMIT      = (int) getArgVal('limit', 0);
$SINGLE_ID  = (int) getArgVal('id', 0);

define('UMBRAL_SEGMENTO_KM', 5.0);   // segmento > 5km entre dos puntos consecutivos = sospechoso
define('GPX_BASE_DIR',       __DIR__ . '/paginasespecializadas/skitouring/rutas_st/osm_import/');
define('OVERPASS_TIMEOUT',   120);

$OVERPASS_SERVERS = [
    'https://overpass-api.de/api/interpreter',
    'https://overpass.kumi.systems/api/interpreter',
];

require_once __DIR__ . '/paginasespecializadas/inicio_sesion/conexion.php';

log_msg("════════════════════════════════════════════════════");
log_msg("  Kahir — Regenerar GPX con track corregido" . ($DRY_RUN ? "  ⚡ SIMULACIÓN" : ""));
log_msg("════════════════════════════════════════════════════");

// ── Cargar rutas OSM de la BD ─────────────────────────────────────
$where = "osm_id IS NOT NULL AND estado != 'eliminada' AND gpx_path IS NOT NULL";
if ($SINGLE_ID > 0) $where .= " AND osm_id = {$SINGLE_ID}";
$res = $conn->query("SELECT id, titulo, osm_id, gpx_path FROM rutas WHERE {$where} ORDER BY id ASC");
if (!$res) { log_msg("❌ BD error: " . $conn->error); exit(1); }

$rutas = $res->fetch_all(MYSQLI_ASSOC);
log_msg("📊 Rutas OSM en BD: " . count($rutas));

$stats = ['regeneradas' => 0, 'sin_problema' => 0, 'overpass_error' => 0,
          'sin_geometria' => 0, 'errores' => 0, 'sospechosas' => 0];

$processed = 0;

foreach ($rutas as $ruta) {
    if ($LIMIT > 0 && $processed >= $LIMIT) break;

    $osmId    = (int)$ruta['osm_id'];
    $titulo   = $ruta['titulo'];
    $gpxFile  = GPX_BASE_DIR . $ruta['gpx_path'];

    // ── Comprobar si el GPX existe ────────────────────────────────
    if (!file_exists($gpxFile)) {
        log_msg("  ⏭️  GPX no encontrado: {$ruta['gpx_path']}");
        $stats['errores']++;
        continue;
    }

    // ── Detectar problema: segmentos rectos sospechosos ──────────
    if (!$ALL && $SINGLE_ID === 0) {
        $suspicious = hasStraightLines($gpxFile);
        if (!$suspicious) {
            $stats['sin_problema']++;
            continue;
        }
        $stats['sospechosas']++;
        log_msg("  ⚠️  Track sospechoso [osm_id={$osmId}]: {$titulo}");
    }

    $processed++;

    if ($DRY_RUN) {
        log_msg("  🔄 [{$processed}] Regeneraría: {$titulo} (osm_id={$osmId})");
        $stats['regeneradas']++;
        continue;
    }

    // ── Re-descargar geometría desde Overpass ─────────────────────
    $elements = fetchOsmRelation($osmId);
    if ($elements === null) {
        log_msg("  ❌ Overpass falló [osm_id={$osmId}]: {$titulo}");
        $stats['overpass_error']++;
        sleep(2);
        continue;
    }
    if (empty($elements)) {
        log_msg("  ❌ Relación no encontrada en OSM [id={$osmId}]: {$titulo}");
        $stats['sin_geometria']++;
        continue;
    }

    $osm = $elements[0];

    // ── Ensamblar puntos con algoritmo corregido ──────────────────
    $puntos = extractPoints($osm);
    if (count($puntos) < 2) {
        log_msg("  ❌ Sin geometría suficiente [osm_id={$osmId}]: {$titulo}");
        $stats['sin_geometria']++;
        continue;
    }

    // ── Construir nuevo GPX ───────────────────────────────────────
    $desc   = cleanText($osm['tags']['description'] ?? $osm['tags']['note'] ?? null);
    $gpxXml = buildGpx($titulo, $puntos, $desc);

    // ── Guardar ───────────────────────────────────────────────────
    if (file_put_contents($gpxFile, $gpxXml) === false) {
        log_msg("  ❌ Error guardando GPX: " . $ruta['gpx_path']);
        $stats['errores']++;
        continue;
    }

    $nPuntos  = count(array_filter($puntos, fn($p) => !is_nan($p['lat'])));
    $distancia = estimateDistance($puntos);
    log_msg("  ✅ [{$processed}] Regenerado: {$titulo} — {$distancia}km, {$nPuntos} pts");
    $stats['regeneradas']++;

    sleep(1); // No saturar Overpass
}

// ── Resumen ───────────────────────────────────────────────────────
log_msg("");
log_msg("════════════════════════════════════════════════════");
log_msg("  RESUMEN" . ($DRY_RUN ? " — SIMULACIÓN" : ""));
log_msg("════════════════════════════════════════════════════");
log_msg("🔄  GPX regenerados      : {$stats['regeneradas']}");
log_msg("✅  Sin problema (OK)    : {$stats['sin_problema']}");
log_msg("⚠️   Con líneas sospechosas: {$stats['sospechosas']}");
log_msg("❌  Errores Overpass     : {$stats['overpass_error']}");
log_msg("❌  Sin geometría        : {$stats['sin_geometria']}");
log_msg("❌  Otros errores        : {$stats['errores']}");
log_msg("════════════════════════════════════════════════════\n");

$conn->close();


// ═════════════════════════════════════════════════════════════════
//  FUNCIONES
// ═════════════════════════════════════════════════════════════════

/**
 * Detecta si un GPX tiene segmentos sospechosamente largos (líneas rectas).
 * Devuelve true si hay al menos un salto > UMBRAL_SEGMENTO_KM entre puntos.
 */
function hasStraightLines(string $gpxFile): bool
{
    $xml = @simplexml_load_file($gpxFile);
    if (!$xml) return false;

    $ns = ($xml->getNamespaces(true))[''] ?? 'http://www.topografix.com/GPX/1/1';
    $xml->registerXPathNamespace('g', $ns);
    $trkpts = $xml->xpath('//g:trkpt');
    if (count($trkpts) < 2) return false;

    $prev = null;
    foreach ($trkpts as $pt) {
        $lat = (float)$pt['lat'];
        $lon = (float)$pt['lon'];
        if ($prev !== null) {
            $dist = haversine($prev[0], $prev[1], $lat, $lon);
            if ($dist > UMBRAL_SEGMENTO_KM) return true;
        }
        $prev = [$lat, $lon];
    }
    return false;
}

/**
 * Descarga la relación OSM con su geometría via Overpass.
 * Prueba dos servidores; devuelve null si ambos fallan.
 */
function fetchOsmRelation(int $osmId): ?array
{
    global $OVERPASS_SERVERS;

    $query = "[out:json][timeout:" . OVERPASS_TIMEOUT . "];\nrelation({$osmId});\nout geom;";
    $ctx   = stream_context_create([
        'http' => ['timeout' => OVERPASS_TIMEOUT + 10, 'user_agent' => 'KahirAI/1.0',
                   'ignore_errors' => true]
    ]);

    foreach ($OVERPASS_SERVERS as $server) {
        $url = $server . '?data=' . urlencode($query);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) continue;
        $data = json_decode($raw, true);
        if (isset($data['elements'])) return $data['elements'];
    }
    return null;
}

/**
 * Ensambla los ways de la relación en un track continuo.
 * Algoritmo greedy: siempre conecta el segmento más cercano al extremo actual.
 * Inserta NaN como separador cuando hay un hueco real (> 50m).
 */
function extractPoints(array $osm): array
{
    $ways = [];
    foreach ($osm['members'] ?? [] as $member) {
        if ($member['type'] !== 'way' || empty($member['geometry'])) continue;
        $pts = [];
        foreach ($member['geometry'] as $pt) {
            if (isset($pt['lat'], $pt['lon'])) {
                $pts[] = ['lat' => (float)$pt['lat'], 'lon' => (float)$pt['lon']];
            }
        }
        if (count($pts) >= 2) $ways[] = $pts;
    }

    if (empty($ways))      return [];
    if (count($ways) === 1) return $ways[0];

    $chain    = array_shift($ways);
    $maxGapKm = 0.05; // 50 m

    while (!empty($ways)) {
        $tail     = end($chain);
        $bestIdx  = 0;
        $bestDist = PHP_FLOAT_MAX;
        $bestRev  = false;

        foreach ($ways as $i => $way) {
            $head = $way[0];
            $foot = $way[count($way) - 1];
            $dH   = haversine($tail['lat'], $tail['lon'], $head['lat'], $head['lon']);
            $dF   = haversine($tail['lat'], $tail['lon'], $foot['lat'], $foot['lon']);
            if ($dH < $bestDist) { $bestDist = $dH; $bestIdx = $i; $bestRev = false; }
            if ($dF < $bestDist) { $bestDist = $dF; $bestIdx = $i; $bestRev = true;  }
        }

        $next = $ways[$bestIdx];
        array_splice($ways, $bestIdx, 1);
        if ($bestRev) $next = array_reverse($next);

        if ($bestDist < $maxGapKm) {
            array_shift($next);
            $chain = array_merge($chain, $next);
        } else {
            $chain[] = ['lat' => NAN, 'lon' => NAN];
            $chain   = array_merge($chain, $next);
        }
    }

    return $chain;
}

function buildGpx(string $name, array $puntos, ?string $desc = null): string
{
    $nameEsc = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $descEsc = $desc ? '<desc>' . htmlspecialchars($desc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</desc>' : '';

    $trkpts = "    <trkseg>\n";
    foreach ($puntos as $pt) {
        if (is_nan($pt['lat']) || is_nan($pt['lon'])) {
            $trkpts .= "    </trkseg>\n    <trkseg>\n";
            continue;
        }
        $trkpts .= sprintf("      <trkpt lat=\"%.7f\" lon=\"%.7f\"/>\n", $pt['lat'], $pt['lon']);
    }
    $trkpts .= "    </trkseg>\n";

    return <<<GPX
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="Kahir OSM Import"
     xmlns="http://www.topografix.com/GPX/1/1"
     xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
     xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd">
  <trk>
    <name>{$nameEsc}</name>
    {$descEsc}
{$trkpts}  </trk>
</gpx>
GPX;
}

function estimateDistance(array $puntos): float
{
    $total = 0.0;
    for ($i = 1, $n = count($puntos); $i < $n; $i++) {
        if (is_nan($puntos[$i-1]['lat']) || is_nan($puntos[$i]['lat'])) continue;
        $total += haversine($puntos[$i-1]['lat'], $puntos[$i-1]['lon'],
                            $puntos[$i]['lat'],   $puntos[$i]['lon']);
    }
    return round($total, 2);
}

function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $R  = 6371.0;
    $φ1 = deg2rad($lat1); $φ2 = deg2rad($lat2);
    $Δφ = deg2rad($lat2 - $lat1);
    $Δλ = deg2rad($lon2 - $lon1);
    $a  = sin($Δφ/2)**2 + cos($φ1)*cos($φ2)*sin($Δλ/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}

function cleanText(?string $s): ?string
{
    if ($s === null) return null;
    $s = strip_tags($s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s) ?: null;
}

function log_msg(string $msg): void
{
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}

function getArgVal(string $key, mixed $default): mixed
{
    foreach ($GLOBALS['argv'] ?? [] as $arg) {
        if (preg_match("/^--{$key}=(.+)$/", $arg, $m)) return $m[1];
    }
    return $default;
}
