<?php
/**
 * ════════════════════════════════════════════════════════════════
 *  Kahir — Recalcula elevacion_ganada desde etiquetas <ele> del GPX
 * ════════════════════════════════════════════════════════════════
 *
 * Ejecutar DESPUÉS de add_gpx_elevation.php (que añade <ele> a todos los GPX).
 * Este script:
 *   1. Lee TODOS los trkpt de TODOS los trksegs del GPX (XPath con namespace)
 *   2. Suma las diferencias positivas de elevación → elevacion_ganada real
 *   3. Recalcula duracion_horas y dificultad con esos valores correctos
 *   4. Actualiza la BD
 *
 * Ventajas sobre fix_rutas_osm.php:
 *   - Usa <ele> ya calculados (sin llamadas API adicionales)
 *   - Lee todos los trksegs correctamente
 *   - Procesa TODOS los puntos del track (no solo 80 muestreados)
 *
 * Uso:
 *   php recalcular_desnivel.php             → actualiza todos
 *   php recalcular_desnivel.php --dry-run   → simula sin guardar
 *   php recalcular_desnivel.php --force     → recalcula aunque ya tengan valor
 *   php recalcular_desnivel.php --limit=100 → máximo N rutas
 */

declare(strict_types=1);

$DRY_RUN = in_array('--dry-run', $argv ?? []);
$FORCE   = in_array('--force',   $argv ?? []);
$LIMIT   = (int) getArgVal('limit', 0);

define('GPX_BASE_DIR', __DIR__ . '/paginasespecializadas/skitouring/rutas_st/osm_import/');
define('GPX_NS',       'http://www.topografix.com/GPX/1/1');

require_once __DIR__ . '/paginasespecializadas/inicio_sesion/conexion.php';

log_msg("════════════════════════════════════════════════════");
log_msg("  Kahir — Recalcular desnivel desde GPX <ele>" . ($DRY_RUN ? "  ⚡ SIMULACIÓN" : ""));
log_msg("════════════════════════════════════════════════════");

// ── Corregir 'expert' residuales ─────────────────────────────────
$conn->query("UPDATE rutas SET dificultad = 'advanced' WHERE dificultad = 'expert' AND osm_id IS NOT NULL");
$fixed = $conn->affected_rows;
if ($fixed > 0) log_msg("🔧 {$fixed} dificultades 'expert' corregidas a 'advanced'");

// ── Cargar rutas OSM ──────────────────────────────────────────────
$where = $FORCE
    ? "osm_id IS NOT NULL AND estado != 'eliminada' AND gpx_path IS NOT NULL"
    : "osm_id IS NOT NULL AND estado != 'eliminada' AND gpx_path IS NOT NULL AND elevacion_ganada IS NULL";

$res = $conn->query("
    SELECT id, titulo, tipo_ruta, distancia_km, elevacion_ganada, duracion_horas, dificultad, gpx_path
    FROM rutas
    WHERE {$where}
    ORDER BY id ASC
");
if (!$res) { log_msg("❌ BD error: " . $conn->error); exit(1); }

$rutas = $res->fetch_all(MYSQLI_ASSOC);
log_msg("📊 Rutas a procesar: " . count($rutas));
log_msg("");

$stats = ['actualizadas' => 0, 'sin_ele' => 0, 'sin_gpx' => 0, 'errores' => 0];
$count = 0;

foreach ($rutas as $ruta) {
    if ($LIMIT > 0 && $count >= $LIMIT) break;

    $id        = (int)$ruta['id'];
    $titulo    = $ruta['titulo'];
    $tipo      = $ruta['tipo_ruta'];
    $distancia = $ruta['distancia_km'] !== null ? (float)$ruta['distancia_km'] : null;
    $gpxFile   = GPX_BASE_DIR . $ruta['gpx_path'];

    // ── Verificar GPX ─────────────────────────────────────────────
    if (!file_exists($gpxFile)) {
        $stats['sin_gpx']++;
        continue;
    }

    // ── Leer elevaciones del GPX ──────────────────────────────────
    $elevaciones = readEleFromGpx($gpxFile);
    if ($elevaciones === null) {
        $stats['sin_ele']++;
        continue;
    }
    if (count($elevaciones) < 2) {
        $stats['sin_ele']++;
        continue;
    }

    // ── Calcular desnivel positivo acumulado ──────────────────────
    $nuevaElevacion = calcGain($elevaciones);

    // ── Recalcular duración y dificultad ─────────────────────────
    if ($distancia === null || $distancia <= 0) {
        // Sin distancia, solo actualizar elevación si cambia
        if ($ruta['elevacion_ganada'] === null || $FORCE) {
            if (!$DRY_RUN) {
                $conn->query("UPDATE rutas SET elevacion_ganada = {$nuevaElevacion} WHERE id = {$id}");
            }
            $count++;
            $stats['actualizadas']++;
        }
        continue;
    }

    $nuevaDur = estimateDuration($distancia, $nuevaElevacion, $tipo);
    $nuevaDif = inferDifficulty($distancia, $nuevaElevacion, $tipo);

    // ── Detectar cambios ──────────────────────────────────────────
    $cambios = [];
    $elevActual = $ruta['elevacion_ganada'];
    $durActual  = $ruta['duracion_horas'];
    $difActual  = $ruta['dificultad'];

    if ($elevActual === null || $FORCE)                $cambios[] = "desnivel: " . ($elevActual ?? 'NULL') . " → +{$nuevaElevacion}m";
    if ($durActual === null)                           $cambios[] = "duración: NULL → {$nuevaDur}h";
    if ($difActual !== $nuevaDif)                      $cambios[] = "dificultad: {$difActual} → {$nuevaDif}";

    if (empty($cambios)) continue;

    $count++;
    log_msg("  ✏️  [{$id}] {$titulo} ({$distancia}km, " . count($elevaciones) . " pts)");
    foreach ($cambios as $c) log_msg("         → {$c}");

    if (!$DRY_RUN) {
        // elevacion_ganada: siempre actualizar si FORCE, o si era NULL
        $setElev = ($elevActual === null || $FORCE)
            ? "elevacion_ganada = {$nuevaElevacion},"
            : '';

        $stmt = $conn->prepare("
            UPDATE rutas
            SET {$setElev}
                duracion_horas   = COALESCE(duracion_horas, ?),
                dificultad       = ?
            WHERE id = ?
        ");
        $stmt->bind_param('dsi', $nuevaDur, $nuevaDif, $id);
        $stmt->execute();
        $stmt->close();
    }

    $stats['actualizadas']++;
}

// ── Resumen ───────────────────────────────────────────────────────
log_msg("");
log_msg("════════════════════════════════════════════════════");
log_msg("  RESUMEN" . ($DRY_RUN ? " — SIMULACIÓN" : ""));
log_msg("════════════════════════════════════════════════════");
log_msg("✅  Rutas actualizadas   : {$stats['actualizadas']}");
log_msg("⏭️   Sin <ele> en GPX     : {$stats['sin_ele']}");
log_msg("⏭️   GPX no encontrado    : {$stats['sin_gpx']}");
log_msg("❌  Errores              : {$stats['errores']}");
log_msg("════════════════════════════════════════════════════\n");

$conn->close();


// ═════════════════════════════════════════════════════════════════
//  FUNCIONES
// ═════════════════════════════════════════════════════════════════

/**
 * Lee TODOS los valores <ele> de TODOS los trksegs del GPX.
 * Usa XPath con namespace registrado para evitar el bug de SimpleXML.
 * Devuelve array de floats, o null si el GPX no tiene <ele>.
 */
function readEleFromGpx(string $gpxFile): ?array
{
    $xml = @simplexml_load_file($gpxFile);
    if (!$xml) return null;

    // Registrar namespace (necesario para XPath con namespace por defecto)
    $xml->registerXPathNamespace('g', GPX_NS);

    // Obtener TODOS los trkpt de TODOS los trksegs
    $trkpts = $xml->xpath('//g:trkpt');
    if (empty($trkpts)) {
        // Intentar sin namespace (GPX sin declaración xmlns)
        $trkpts = $xml->xpath('//trkpt');
        if (empty($trkpts)) return null;
    }

    // Verificar que existen <ele> tags
    $hasEle = false;
    foreach ($trkpts as $pt) {
        if (isset($pt->ele) || count($pt->children(GPX_NS)) > 0) {
            $hasEle = true;
            break;
        }
        // Intento sin namespace
        if (isset($pt->ele)) { $hasEle = true; break; }
    }
    if (!$hasEle) return null;

    $elevaciones = [];
    foreach ($trkpts as $pt) {
        // Intentar con namespace explícito primero
        $eleNs = $pt->children(GPX_NS);
        if (isset($eleNs->ele)) {
            $elevaciones[] = (float)$eleNs->ele;
        } elseif (isset($pt->ele)) {
            $elevaciones[] = (float)$pt->ele;
        }
        // Si no hay ele para este punto, lo ignoramos (no añadimos 0)
    }

    return count($elevaciones) >= 2 ? $elevaciones : null;
}

/** Suma desnivel positivo acumulado de un array de elevaciones */
function calcGain(array $elevaciones): int
{
    $gain = 0;
    $prev = null;
    foreach ($elevaciones as $e) {
        if ($prev !== null && $e > $prev) {
            $gain += $e - $prev;
        }
        $prev = $e;
    }
    return (int)round($gain);
}

function estimateDuration(float $dist, int $elev, string $tipo): float
{
    $e = $elev;
    $horas = match(true) {
        in_array($tipo, ['ski_touring', 'snowshoeing']) => ($dist / 3.0)  + ($e / 400.0),
        $tipo === 'cycle_touring'                       => ($dist / 15.0),
        $tipo === 'btt'                                 => ($dist / 10.0),
        default                                         => ($dist / 4.0)  + ($e / 600.0),
    };
    return round(max(0.5, min($horas, 24.0)), 1);
}

function inferDifficulty(float $dist, int $elev, string $tipo): string
{
    $e = $elev;
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
    if ($dist > 20 || $e > 900)  return 'advanced';
    if ($dist > 8  || $e > 400)  return 'intermediate';
    return 'beginner';
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
