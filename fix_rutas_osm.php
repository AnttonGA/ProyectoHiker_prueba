<?php
/**
 * ════════════════════════════════════════════════════════════════
 *  Kahir — Corrige desnivel, duración y dificultad de rutas OSM
 * ════════════════════════════════════════════════════════════════
 *
 * Uso:
 *   php fix_rutas_osm.php             → aplica los cambios
 *   php fix_rutas_osm.php --dry-run   → muestra lo que cambiaría sin guardar
 *   php fix_rutas_osm.php --skip-elev → salta el cálculo de desnivel (más rápido)
 *
 * Desnivel: se calcula via OpenTopoData (SRTM 30m, gratuita, sin API key).
 *   Límite: 1 req/s, 100 puntos/req. Para 182 rutas ~3-5 minutos.
 */

declare(strict_types=1);


// Script de mantenimiento: solo se puede ejecutar desde la línea de comandos (php archivo.php),
// nunca desde el navegador. Antes cualquiera podía lanzarlo por web sin iniciar sesión.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo disponible desde la línea de comandos.');
}
$DRY_RUN    = in_array('--dry-run',    $argv ?? []);
$SKIP_ELEV  = in_array('--skip-elev', $argv ?? []);

define('ELEV_API',        'https://api.opentopodata.org/v1/srtm30m');
define('ELEV_MAX_POINTS', 80);   // puntos por petición (máximo 100, dejamos margen)
define('GPX_BASE_DIR',    __DIR__ . '/paginasespecializadas/skitouring/rutas_st/osm_import/');

require_once __DIR__ . '/paginasespecializadas/inicio_sesion/conexion.php';

log_msg("════════════════════════════════════════════════════");
log_msg("  Kahir — Fix desnivel, duración y dificultad OSM" . ($DRY_RUN ? "  ⚡ SIMULACIÓN" : ""));
log_msg("════════════════════════════════════════════════════");
if ($SKIP_ELEV) log_msg("  ⚡ Cálculo de desnivel desactivado (--skip-elev)");

// ── 1. Corregir registros con dificultad='expert' (no existe en el ENUM) ─
$conn->query("UPDATE rutas SET dificultad = 'advanced' WHERE dificultad = 'expert' AND osm_id IS NOT NULL");
$fixed = $conn->affected_rows;
if ($fixed > 0) log_msg("🔧 {$fixed} registros 'expert' corregidos a 'advanced'");

// ── 2. Cargar rutas OSM ───────────────────────────────────────────────────
$res = $conn->query("
    SELECT id, titulo, tipo_ruta, distancia_km, elevacion_ganada, duracion_horas, dificultad,
           gpx_path, ruta_carpeta, start_lat, start_lng
    FROM rutas
    WHERE osm_id IS NOT NULL AND estado != 'eliminada'
    ORDER BY id ASC
");

if (!$res) { log_msg("❌ Error BD: " . $conn->error); exit(1); }

$rutas = $res->fetch_all(MYSQLI_ASSOC);
log_msg("📊 Rutas OSM a procesar: " . count($rutas));
log_msg("");

$stats = ['desnivel' => 0, 'duracion' => 0, 'dificultad' => 0, 'sin_datos' => 0, 'api_error' => 0];

foreach ($rutas as $ruta) {
    $id        = (int)$ruta['id'];
    $titulo    = $ruta['titulo'];
    $tipo      = $ruta['tipo_ruta'];
    $distancia = $ruta['distancia_km'] !== null ? (float)$ruta['distancia_km'] : null;
    $elevacion = $ruta['elevacion_ganada'] !== null ? (int)$ruta['elevacion_ganada'] : null;
    $durActual = $ruta['duracion_horas'];
    $difActual = $ruta['dificultad'];

    if ($distancia === null || $distancia <= 0) {
        log_msg("  ⏭️  [{$id}] Sin distancia: {$titulo}");
        $stats['sin_datos']++;
        continue;
    }

    // ── Calcular desnivel desde GPX via API de elevación ────────────────
    $nuevaElevacion = $elevacion; // mantener el valor actual si ya existe

    if (!$SKIP_ELEV && $elevacion === null) {
        $puntos = readGpxPoints($ruta['ruta_carpeta'], $ruta['gpx_path']);

        if (!empty($puntos)) {
            $gain = getElevationGain($puntos);
            if ($gain !== null) {
                $nuevaElevacion = $gain;
                $stats['desnivel']++;
                log_msg("  ⛰️  [{$id}] Desnivel calculado: +{$gain}m  ({$titulo})");
            } else {
                $stats['api_error']++;
                log_msg("  ⚠️  [{$id}] API elevación falló: {$titulo}");
            }
            sleep(1); // respetar rate limit 1 req/s
        }
    }

    // ── Calcular duración y dificultad ───────────────────────────────────
    $nuevaDur = estimateDuration($distancia, $nuevaElevacion, $tipo);
    $nuevaDif = inferDifficulty($distancia, $nuevaElevacion, $tipo);

    $cambios = [];
    if ($durActual === null)                                               $cambios[] = "duración: NULL → {$nuevaDur}h";
    if ($elevacion === null && $nuevaElevacion !== null)                   $cambios[] = "desnivel: NULL → +{$nuevaElevacion}m";
    if ($difActual !== $nuevaDif)                                          $cambios[] = "dificultad: {$difActual} → {$nuevaDif}";

    if (empty($cambios)) continue;

    log_msg("  ✏️  [{$id}] {$titulo} ({$distancia}km)");
    foreach ($cambios as $c) log_msg("         → {$c}");

    if (!$DRY_RUN) {
        $stmt = $conn->prepare("
            UPDATE rutas
            SET duracion_horas   = COALESCE(duracion_horas, ?),
                elevacion_ganada = COALESCE(elevacion_ganada, ?),
                dificultad       = ?
            WHERE id = ?
        ");
        $stmt->bind_param('disd', $nuevaDur, $nuevaElevacion, $nuevaDif, $id);
        $stmt->execute();
        $stmt->close();
    }

    if ($durActual === null)                    $stats['duracion']++;
    if ($difActual !== $nuevaDif)              $stats['dificultad']++;
}

// ── Resumen ──────────────────────────────────────────────────────────────
log_msg("");
log_msg("════════════════════════════════════════════════════");
log_msg("  RESUMEN" . ($DRY_RUN ? " — SIMULACIÓN" : ""));
log_msg("════════════════════════════════════════════════════");
log_msg("⛰️   Desnivelés calculados  : {$stats['desnivel']}");
log_msg("⏱️   Duraciones estimadas   : {$stats['duracion']}");
log_msg("🎯  Dificultades revisadas  : {$stats['dificultad']}");
log_msg("❌  Errores API elevación   : {$stats['api_error']}");
log_msg("⏭️   Sin distancia (skip)   : {$stats['sin_datos']}");
log_msg("════════════════════════════════════════════════════\n");

$conn->close();


// ═════════════════════════════════════════════════════════════════
//  FUNCIONES
// ═════════════════════════════════════════════════════════════════

/**
 * Lee los puntos lat/lon de un GPX guardado localmente.
 */
function readGpxPoints(string $carpeta, string $gpxFile): array
{
    $path = GPX_BASE_DIR . $gpxFile;
    if (!file_exists($path)) return [];

    $xml = @simplexml_load_file($path);
    if (!$xml) return [];

    $puntos = [];
    foreach ($xml->trk->trkseg->trkpt ?? [] as $pt) {
        $lat = (float)$pt['lat'];
        $lon = (float)$pt['lon'];
        if ($lat !== 0.0 || $lon !== 0.0) {
            $puntos[] = ['lat' => $lat, 'lon' => $lon];
        }
    }
    return $puntos;
}

/**
 * Calcula el desnivel positivo acumulado usando OpenTopoData (SRTM 30m).
 * Muestrea los puntos para no superar el límite de la API.
 */
function getElevationGain(array $puntos): ?int
{
    // Muestreo uniforme: máximo ELEV_MAX_POINTS puntos
    $n        = count($puntos);
    $step     = max(1, (int)ceil($n / ELEV_MAX_POINTS));
    $sampled  = [];
    for ($i = 0; $i < $n; $i += $step) {
        $sampled[] = $puntos[$i];
    }
    // Aseguramos el último punto
    if (end($sampled) !== end($puntos)) {
        $sampled[] = end($puntos);
    }

    $locations = implode('|', array_map(fn($p) => "{$p['lat']},{$p['lon']}", $sampled));
    $url       = ELEV_API . '?locations=' . $locations;

    $ctx = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'KahirAI/1.0']]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return null;

    $data = json_decode($raw, true);
    if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) return null;

    // Desnivel positivo acumulado
    $gain = 0;
    $prev = null;
    foreach ($data['results'] as $r) {
        $elev = $r['elevation'] ?? null;
        if ($elev === null) continue;
        if ($prev !== null && $elev > $prev) {
            $gain += $elev - $prev;
        }
        $prev = $elev;
    }

    return $gain > 0 ? (int)round($gain) : 0;
}

// ─────────────────────────────────────────────────────────────────
function estimateDuration(float $dist, ?int $elev, string $tipo): float
{
    $e = $elev ?? 0;
    $horas = match(true) {
        in_array($tipo, ['ski_touring', 'snowshoeing']) => ($dist / 3.0)  + ($e / 400.0),
        $tipo === 'cycle_touring'                       => ($dist / 15.0),
        $tipo === 'btt'                                 => ($dist / 10.0),
        default                                         => ($dist / 4.0)  + ($e / 600.0),
    };
    return round(max(0.5, min($horas, 24.0)), 1);
}

// ─────────────────────────────────────────────────────────────────
function inferDifficulty(float $dist, ?int $elev, string $tipo): string
{
    $e = $elev ?? 0;

    if (in_array($tipo, ['ski_touring', 'snowshoeing'])) {
        if ($dist > 15 || $e > 800)  return 'advanced';
        if ($dist > 6  || $e > 400)  return 'intermediate';
        return 'beginner';
    }
    if (in_array($tipo, ['cycle_touring', 'btt'])) {
        if ($dist > 60)  return 'advanced';
        if ($dist > 25)  return 'intermediate';
        return 'beginner';
    }
    // hiking, climbing, general
    if ($dist > 20 || $e > 900)  return 'advanced';
    if ($dist > 8  || $e > 400)  return 'intermediate';
    return 'beginner';
}

// ─────────────────────────────────────────────────────────────────
function log_msg(string $msg): void
{
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}
