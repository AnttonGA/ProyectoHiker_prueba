<?php
/**
 * ════════════════════════════════════════════════════════════════
 *  Kahir — Importador de rutas desde OpenStreetMap (Overpass API)
 * ════════════════════════════════════════════════════════════════
 *
 * Uso (CLI):
 *   php importar_osm.php                                     → todo, sin límite
 *   php importar_osm.php --dry-run                           → simula sin guardar
 *   php importar_osm.php --region=navarra --activity=hiking  → región+actividad
 *   php importar_osm.php --limit=50                          → máximo N nuevas rutas por par
 *   php importar_osm.php --skip-elevation                    → no llama a OpenTopoData
 *   php importar_osm.php --sleep=2                           → segundos entre llamadas Overpass
 *
 * Regiones disponibles:
 *   cantabria, asturias_occ, asturias_ori,
 *   leon_no, leon_ne, leon_so, leon_se,
 *   navarra, euskadi, la_rioja,
 *   aragon_huesca, aragon_zaragoza, aragon_teruel,
 *   cat_pirineos, cat_prepirineo, cat_central, cat_costa,
 *   galicia_occ, galicia_ori,
 *   burgos, palencia, zamora, salamanca, valladolid, segovia, avila, soria,
 *   madrid,
 *   cuenca, guadalajara, toledo, ciudad_real, albacete,
 *   caceres, badajoz,
 *   andalucia_jaen, sierra_nevada, andalucia_malaga,
 *   andalucia_huelva, andalucia_cordoba, andalucia_sevilla, andalucia_cadiz, andalucia_almeria,
 *   valencia_norte, valencia_sur, murcia,
 *   mallorca, menorca, ibiza,
 *   tenerife, gran_canaria, lanzarote, fuerteventura, la_palma, la_gomera, el_hierro, la_graciosa,
 *   pirineos_oeste, pirineos_central, pirineos_este
 *
 * Actividades: hiking, ski, cycling, mtb
 *
 * Flujo por ruta:
 *   1. Overpass → geometría OSM
 *   2. GPX generado con lat/lon (greedy chain, sin líneas rectas)
 *   3. OpenTopoData SRTM → <ele> añadidos al GPX
 *   4. elevacion_ganada, duracion_horas, dificultad calculados con datos reales
 *   5. INSERT en BD
 */

declare(strict_types=1);

ini_set('memory_limit', '2048M');

// ─────────────────────────────────────────────────────────────────
// ARGUMENTOS
// ─────────────────────────────────────────────────────────────────
$DRY_RUN          = in_array('--dry-run',        $argv ?? []);
$SKIP_ELEVATION   = in_array('--skip-elevation', $argv ?? []);
$LIMIT            = (int) getArg('limit',  0);    // 0 = sin límite
$SLEEP_OVERPASS   = (int) getArg('sleep',  1);    // segundos entre llamadas Overpass
$REGION_FILTER    = strtolower(getArg('region',   'all'));
$ACTIVITY_FILTER  = strtolower(getArg('activity', 'all'));

// ─────────────────────────────────────────────────────────────────
// CONSTANTES
// ─────────────────────────────────────────────────────────────────
define('RADIO_DUPLICADO_KM', 0.3);
define('OVERPASS_TIMEOUT',   180);
define('GPX_BASE_DIR',       __DIR__ . '/paginasespecializadas/skitouring/rutas_st/osm_import/');
define('LOG_FILE',           __DIR__ . '/osm_import.log');
define('ELEV_API',           'https://api.opentopodata.org/v1/srtm30m');
define('ELEV_MAX_PTS',       80);

// ─────────────────────────────────────────────────────────────────
// REGIONES  [clave => [nombre, sur, oeste, norte, este, ubicacion_db]]
// ─────────────────────────────────────────────────────────────────
$REGIONES = [

    // ── Ya importadas (disponibles para actividades nuevas) ──────
    'cantabria'          => ['Cantabria',                  43.00, -4.60, 43.55, -3.30, 'Other'],
    'asturias_occ'       => ['Asturias Occ.',               43.10, -7.20, 43.70, -5.80, 'Other'],
    'asturias_ori'       => ['Asturias Orient.',            43.10, -5.80, 43.70, -4.50, 'Other'],
    'leon_no'            => ['León NO',                    42.50, -7.00, 43.20, -5.75, 'Other'],
    'leon_ne'            => ['León NE',                    42.50, -5.75, 43.20, -4.50, 'Other'],
    'leon_so'            => ['León SO',                    42.00, -7.00, 42.50, -5.75, 'Other'],
    'leon_se'            => ['León SE',                    42.00, -5.75, 42.50, -4.50, 'Other'],
    'pirineos_oeste'     => ['Pirineos Occ.',               42.50, -2.50, 43.50, -0.50, 'Western_Pyrenees'],
    'pirineos_central'   => ['Pirineos Cent.',              42.30, -0.50, 42.90,  1.50, 'Central_Pyrenees'],
    'pirineos_este'      => ['Pirineos Orient.',            42.30,  1.50, 42.70,  3.50, 'Eastern_Pyrenees'],
    'tenerife'           => ['Tenerife',                   27.90,-16.95, 28.60,-16.10, 'Other'],
    'gran_canaria'       => ['Gran Canaria',                27.70,-15.85, 28.20,-15.35, 'Other'],
    'lanzarote'          => ['Lanzarote',                  28.80,-14.00, 29.25,-13.40, 'Other'],
    'fuerteventura'      => ['Fuerteventura',              27.90,-14.55, 28.75,-13.80, 'Other'],
    'la_palma'           => ['La Palma',                   28.40,-17.98, 28.85,-17.73, 'Other'],
    'la_gomera'          => ['La Gomera',                  27.97,-17.40, 28.22,-17.09, 'Other'],
    'el_hierro'          => ['El Hierro',                  27.62,-18.18, 27.85,-17.87, 'Other'],
    'la_graciosa'        => ['La Graciosa',                29.20,-13.55, 29.30,-13.44, 'Other'],

    // ── Navarra (dividida N/S) ────────────────────────────────────
    'navarra_norte'      => ['Navarra Norte / Pirineos',   42.50, -2.20, 43.50,  1.70, 'Western_Pyrenees'],
    'navarra_sur'        => ['Navarra Sur / Ribera',       41.90, -2.20, 42.50,  1.70, 'Other'],

    // ── País Vasco ───────────────────────────────────────────────
    'euskadi_occ'        => ['País Vasco Occ.',            42.80, -3.20, 43.55, -2.20, 'Other'],
    'euskadi_ori'        => ['País Vasco Orient.',         42.80, -2.20, 43.55, -1.60, 'Other'],

    // ── La Rioja ─────────────────────────────────────────────────
    'la_rioja'           => ['La Rioja',                   42.00, -3.20, 42.70, -1.60, 'Other'],

    // ── Aragón ───────────────────────────────────────────────────
    'aragon_huesca_no'   => ['Aragón - Huesca NO',         42.40, -1.50, 43.00, -0.40, 'Central_Pyrenees'],
    'aragon_huesca_ne'   => ['Aragón - Huesca NE',         42.40, -0.40, 43.00,  0.70, 'Central_Pyrenees'],
    'aragon_huesca_sur'  => ['Aragón - Huesca Sur',        42.00, -1.50, 42.40,  0.70, 'Other'],
    'aragon_zaragoza'    => ['Aragón - Zaragoza',          41.00, -2.20, 42.00,  0.30, 'Other'],
    'aragon_teruel'      => ['Aragón - Teruel',            39.80, -2.00, 41.00,  0.60, 'Other'],

    // ── Cataluña ─────────────────────────────────────────────────
    'cat_pir_occ'        => ['Cataluña Pirineos Occ.',     42.20,  0.60, 42.80,  1.80, 'Eastern_Pyrenees'],
    'cat_pir_ori'        => ['Cataluña Pirineos Orient.',  42.20,  1.80, 42.80,  3.30, 'Eastern_Pyrenees'],
    'cat_prepirineo_occ' => ['Cataluña Pre-Pir. Occ.',     41.60,  0.40, 42.20,  1.60, 'Other'],
    'cat_prepirineo_ori' => ['Cataluña Pre-Pir. Orient.',  41.60,  1.60, 42.20,  3.00, 'Other'],
    'cat_central'        => ['Cataluña Central',           41.00,  1.40, 42.00,  2.50, 'Other'],
    'cat_costa_nor'      => ['Cataluña Costa Norte',       41.50,  2.50, 42.50,  3.40, 'Other'],
    'cat_costa_sur'      => ['Cataluña Costa Sur',         40.50,  0.50, 41.50,  2.50, 'Other'],

    // ── Galicia ──────────────────────────────────────────────────
    'galicia_nw'         => ['Galicia NW',                 43.00, -9.30, 43.80, -8.00, 'Other'],
    'galicia_sw'         => ['Galicia SW',                 42.00, -9.30, 43.00, -8.00, 'Other'],
    'galicia_ne'         => ['Galicia NE',                 43.00, -8.00, 43.80, -6.80, 'Other'],
    'galicia_se'         => ['Galicia SE',                 42.00, -8.00, 43.00, -6.80, 'Other'],

    // ── Castilla y León (excepto León ya importado) ──────────────
    'burgos_norte'       => ['Burgos Norte',               42.40, -4.50, 43.00, -2.80, 'Other'],
    'burgos_sur'         => ['Burgos Sur',                 41.80, -4.50, 42.40, -2.80, 'Other'],
    'palencia'           => ['Palencia',                   42.00, -5.10, 43.00, -3.80, 'Other'],
    'zamora'             => ['Zamora',                     41.20, -7.10, 42.20, -5.20, 'Other'],
    'salamanca'          => ['Salamanca',                  40.40, -7.20, 41.30, -5.00, 'Other'],
    'valladolid'         => ['Valladolid',                 41.20, -5.50, 42.00, -3.80, 'Other'],
    'segovia'            => ['Segovia',                    40.60, -4.60, 41.50, -3.40, 'Other'],
    'avila'              => ['Ávila / Gredos',             40.00, -5.80, 40.90, -4.20, 'Other'],
    'soria'              => ['Soria',                      41.00, -3.40, 42.00, -1.80, 'Other'],

    // ── Madrid / Sistema Central ─────────────────────────────────
    'madrid'             => ['Madrid / Guadarrama',        40.20, -4.60, 41.20, -3.20, 'Other'],

    // ── Castilla-La Mancha ───────────────────────────────────────
    'cuenca'             => ['Cuenca / Serranía',          39.60, -2.60, 40.80, -1.40, 'Other'],
    'guadalajara'        => ['Guadalajara',                40.40, -3.50, 41.30, -1.60, 'Other'],
    'toledo'             => ['Toledo / Montes de Toledo',  39.20, -5.60, 40.40, -3.00, 'Other'],
    'ciudad_real'        => ['Ciudad Real',                38.40, -5.20, 39.50, -2.80, 'Other'],
    'albacete'           => ['Albacete / Sierra Segura',   37.80, -2.80, 39.20, -1.00, 'Other'],

    // ── Extremadura ──────────────────────────────────────────────
    'caceres'            => ['Cáceres',                    39.50, -7.60, 40.50, -5.20, 'Other'],
    'badajoz'            => ['Badajoz',                    38.10, -7.60, 39.50, -4.80, 'Other'],

    // ── Andalucía ────────────────────────────────────────────────
    'andalucia_jaen'     => ['Jaén / Cazorla / Segura',   37.30, -4.30, 38.80, -2.00, 'Other'],
    'sierra_nevada'      => ['Sierra Nevada / Granada',    36.80, -4.00, 37.80, -2.50, 'Other'],
    'andalucia_malaga'   => ['Málaga / Serranía de Ronda', 36.40, -5.50, 37.30, -3.50, 'Other'],
    'andalucia_huelva'   => ['Huelva / Sierra Morena',     37.00, -7.60, 38.30, -5.50, 'Other'],
    'andalucia_cordoba'  => ['Córdoba / Sierra Morena',    37.50, -5.50, 38.50, -3.80, 'Other'],
    'andalucia_sevilla'  => ['Sevilla / Sierra Norte',     37.00, -6.50, 38.00, -5.20, 'Other'],
    'andalucia_cadiz'    => ['Cádiz / Los Alcornocales',   36.00, -5.90, 37.00, -5.00, 'Other'],
    'andalucia_almeria'  => ['Almería',                    36.70, -3.00, 37.60, -1.60, 'Other'],

    // ── Comunidad Valenciana / Murcia ────────────────────────────
    'valencia_norte'     => ['Valencia Norte',             39.70, -1.60, 40.80,  0.50, 'Other'],
    'valencia_sur'       => ['Valencia Sur / Alicante',    37.80, -1.20, 39.70,  0.20, 'Other'],
    'murcia'             => ['Murcia',                     37.30, -2.40, 38.60, -0.60, 'Other'],

    // ── Islas Baleares ───────────────────────────────────────────
    'mallorca'           => ['Mallorca',                   39.20,  2.20, 40.10,  3.60, 'Other'],
    'menorca'            => ['Menorca',                    39.70,  3.70, 40.20,  4.45, 'Other'],
    'ibiza'              => ['Ibiza / Formentera',         38.60,  1.10, 39.10,  1.65, 'Other'],
];

// ─────────────────────────────────────────────────────────────────
// ACTIVIDADES  [clave => [osm_tag, tipo_ruta en BD]]
// ─────────────────────────────────────────────────────────────────
$ACTIVIDADES = [
    'hiking'  => ['route_tag' => 'hiking',  'tipo_ruta' => 'hiking'],
    'ski'     => ['route_tag' => 'ski',     'tipo_ruta' => 'ski_touring'],
    'cycling' => ['route_tag' => 'bicycle', 'tipo_ruta' => 'cycle_touring'],
    'mtb'     => ['route_tag' => 'mtb',     'tipo_ruta' => 'btt'],
];

// ═════════════════════════════════════════════════════════════════
//  INICIO
// ═════════════════════════════════════════════════════════════════
log_msg("════════════════════════════════════════════════════════════");
log_msg("  Kahir OSM Importer" . ($DRY_RUN ? "  ⚡ SIMULACIÓN" : "") . ($SKIP_ELEVATION ? "  ⏩ sin elevación" : ""));
log_msg("════════════════════════════════════════════════════════════");

require_once __DIR__ . '/paginasespecializadas/inicio_sesion/conexion.php';

addOsmIdColumn($conn);

if (!$DRY_RUN && !is_dir(GPX_BASE_DIR)) {
    mkdir(GPX_BASE_DIR, 0755, true);
    log_msg("📁 Directorio creado: " . GPX_BASE_DIR);
}

$sistemaUserId   = getSistemaUserId($conn);
log_msg("👤 Usuario ID: {$sistemaUserId}");

log_msg("📊 Cargando rutas existentes...");
$rutasExistentes = loadExistingRoutes($conn);
log_msg("   → " . count($rutasExistentes) . " rutas en BD");

// ─────────────────────────────────────────────────────────────────
// BUCLE PRINCIPAL
// ─────────────────────────────────────────────────────────────────
$stats = ['insertadas' => 0, 'duplicadas' => 0, 'sin_geometria' => 0,
          'errores' => 0, 'sin_elevacion_api' => 0];

$regionesAProcesar    = filterMap($REGIONES,    $REGION_FILTER);
$actividadesAProcesar = filterMap($ACTIVIDADES, $ACTIVITY_FILTER);

$totalPares = count($regionesAProcesar) * count($actividadesAProcesar);
$parActual  = 0;

foreach ($regionesAProcesar as $regionKey => $region) {
    [$nombre, $sur, $oeste, $norte, $este, $ubicacion] = $region;
    log_msg("\n🗺️  REGIÓN: {$nombre}");

    foreach ($actividadesAProcesar as $actKey => $actividad) {
        $parActual++;
        log_msg("   🏃 [{$parActual}/{$totalPares}] {$actividad['route_tag']}");

        // ── Consultar Overpass (con reintento) ───────────────────
        $elementos = fetchOverpassWithRetry($actividad['route_tag'], $sur, $oeste, $norte, $este);

        if ($elementos === false) {
            log_msg("   ❌ Overpass falló tras reintentos. Saltando.");
            $stats['errores']++;
            if ($SLEEP_OVERPASS > 0) sleep($SLEEP_OVERPASS);
            continue;
        }

        $total = count($elementos);
        log_msg("   → {$total} rutas en OSM" . ($LIMIT > 0 && $total > $LIMIT ? " (límite {$LIMIT})" : ""));

        // ── Procesar rutas ───────────────────────────────────────
        $insertadasEnEste = 0;
        foreach ($elementos as $osm) {
            if ($LIMIT > 0 && $insertadasEnEste >= $LIMIT) break;

            $resultado = processRoute(
                $conn, $osm, $actividad['tipo_ruta'], $ubicacion,
                $rutasExistentes, $sistemaUserId, $DRY_RUN, $SKIP_ELEVATION
            );

            $stats[$resultado]++;
            if ($resultado === 'insertadas')        $insertadasEnEste++;
            if ($resultado === 'sin_elevacion_api') $insertadasEnEste++; // cuenta igual
        }

        if ($SLEEP_OVERPASS > 0) sleep($SLEEP_OVERPASS);
    }
}

// ─────────────────────────────────────────────────────────────────
// RESUMEN
// ─────────────────────────────────────────────────────────────────
log_msg("\n════════════════════════════════════════════════════════════");
log_msg("  RESUMEN FINAL" . ($DRY_RUN ? " — SIMULACIÓN" : ""));
log_msg("════════════════════════════════════════════════════════════");
log_msg("✅  Rutas insertadas (con elevación real) : {$stats['insertadas']}");
log_msg("⚠️   Rutas insertadas (API elevación falló): {$stats['sin_elevacion_api']}");
log_msg("⚠️   Duplicados omitidos                  : {$stats['duplicadas']}");
log_msg("⏭️   Sin geometría GPS                     : {$stats['sin_geometria']}");
log_msg("❌  Errores                               : {$stats['errores']}");
if ($stats['sin_elevacion_api'] > 0) {
    log_msg("");
    log_msg("💡 Para añadir elevación a las rutas pendientes:");
    log_msg("   php add_gpx_elevation.php --force");
    log_msg("   php recalcular_desnivel.php --force");
}
log_msg("════════════════════════════════════════════════════════════\n");

$conn->close();
exit(0);


// ═════════════════════════════════════════════════════════════════
//  FUNCIÓN PRINCIPAL: procesar una ruta OSM
// ═════════════════════════════════════════════════════════════════

function processRoute(
    mysqli $conn,
    array  $osm,
    string $tipoRuta,
    string $ubicacion,
    array  &$rutasExistentes,
    int    $sistemaUserId,
    bool   $dryRun,
    bool   $skipElevation
): string {

    $osmId = (int)$osm['id'];
    $tags  = $osm['tags'] ?? [];
    $name  = trim($tags['name'] ?? $tags['ref'] ?? "Ruta OSM {$osmId}");

    // ── Geometría ────────────────────────────────────────────────
    $puntos = extractPoints($osm);
    if (count($puntos) < 2) {
        log_msg("   ⏭️  Sin geometría: {$name}");
        return 'sin_geometria';
    }

    // Primer punto válido (no NaN)
    $primerPunto = null;
    foreach ($puntos as $p) {
        if (!is_nan($p['lat'])) { $primerPunto = $p; break; }
    }
    if (!$primerPunto) return 'sin_geometria';

    $startLat = $primerPunto['lat'];
    $startLng = $primerPunto['lon'];

    // ── Deduplicación ────────────────────────────────────────────
    if (osmIdExists($conn, $osmId)) {
        return 'duplicadas';
    }
    if (findNearby($rutasExistentes, $startLat, $startLng)) {
        return 'duplicadas';
    }
    if (findByName($rutasExistentes, $name)) {
        log_msg("   ⚠️  Dup. nombre: {$name}");
        return 'duplicadas';
    }

    // ── Campos básicos ───────────────────────────────────────────
    $distancia   = isset($tags['distance']) ? (float)$tags['distance'] : estimateDistance($puntos);
    $descripcion = cleanText($tags['description'] ?? $tags['note'] ?? null);
    $ubicacion   = refineUbicacion($startLat, $startLng, $ubicacion);

    // ── Dry-run ──────────────────────────────────────────────────
    if ($dryRun) {
        $dificultad = mapDifficulty($tags) !== 'intermediate' || hasDifficultyTag($tags)
            ? mapDifficulty($tags)
            : inferDifficulty($distancia, null, $tipoRuta);
        log_msg("   ✅ [DRY] '{$name}' | {$distancia}km | {$dificultad} | {$ubicacion}");
        $rutasExistentes[] = ['titulo' => $name, 'start_lat' => $startLat,
                               'start_lng' => $startLng, 'osm_id' => $osmId];
        return 'insertadas';
    }

    // ── Generar GPX (lat/lon) ────────────────────────────────────
    $gpxFileName = "osm_{$osmId}.gpx";
    $gpxFilePath = GPX_BASE_DIR . $gpxFileName;
    $gpxContent  = buildGpx($name, $puntos, $descripcion);

    if (file_put_contents($gpxFilePath, $gpxContent) === false) {
        log_msg("   ❌ Error guardando GPX: {$gpxFilePath}");
        return 'errores';
    }

    // ── Añadir elevación SRTM al GPX ─────────────────────────────
    $elevacionGanada = null;
    $elevResult      = 'insertadas';

    if (!$skipElevation) {
        $puntosValidos = array_values(array_filter($puntos, fn($p) => !is_nan($p['lat'])));
        $elevaciones   = fetchElevations($puntosValidos);

        if ($elevaciones !== null && count($elevaciones) === count($puntosValidos)) {
            // Reescribir GPX con <ele>
            $gpxConEle = rewriteGpxWithEle($gpxContent, $elevaciones);
            if ($gpxConEle !== null) {
                file_put_contents($gpxFilePath, $gpxConEle);
            }
            $elevacionGanada = calcGain($elevaciones);
        } else {
            log_msg("   ⚠️  OpenTopoData falló para: {$name} (se guardará sin elevación)");
            $elevResult = 'sin_elevacion_api';
        }
    }

    // ── Calcular dificultad y duración con elevación real ─────────
    $dificultad = mapDifficulty($tags);
    if (!hasDifficultyTag($tags)) {
        $dificultad = inferDifficulty($distancia, $elevacionGanada, $tipoRuta);
    }

    $duracion = isset($tags['duration']) ? parseDuration($tags['duration']) : null;
    if ($duracion === null) {
        $duracion = estimateDuration($distancia, $elevacionGanada, $tipoRuta);
    }

    $elevTag = $elevacionGanada !== null ? "+{$elevacionGanada}m" : "sin ele";
    log_msg("   ✅ '{$name}' | {$distancia}km | {$elevTag} | {$dificultad} | {$ubicacion}");

    // ── INSERT en BD ─────────────────────────────────────────────
    $carpeta = 'osm_import';
    $stmt = $conn->prepare("
        INSERT INTO rutas
            (usuario_id, titulo, descripcion, gpx_path, ruta_carpeta,
             tipo_ruta, ubicacion, dificultad,
             distancia_km, elevacion_ganada, duracion_horas,
             start_lat, start_lng, osm_id, estado)
        VALUES
            (?, ?, ?, ?, ?,
             ?, ?, ?,
             ?, ?, ?,
             ?, ?, ?, 'publicada')
    ");

    if (!$stmt) {
        log_msg("   ❌ Error prepare: " . $conn->error);
        @unlink($gpxFilePath);
        return 'errores';
    }

    $stmt->bind_param(
        'issss' . 'sss' . 'did' . 'ddi',
        $sistemaUserId,
        $name, $descripcion, $gpxFileName, $carpeta,
        $tipoRuta, $ubicacion, $dificultad,
        $distancia, $elevacionGanada, $duracion,
        $startLat, $startLng, $osmId
    );

    if (!$stmt->execute()) {
        log_msg("   ❌ Error BD: " . $stmt->error);
        @unlink($gpxFilePath);
        $stmt->close();
        return 'errores';
    }
    $stmt->close();

    $rutasExistentes[] = ['titulo' => $name, 'start_lat' => $startLat,
                           'start_lng' => $startLng, 'osm_id' => $osmId];

    return $elevResult;
}


// ═════════════════════════════════════════════════════════════════
//  OVERPASS
// ═════════════════════════════════════════════════════════════════

function fetchOverpassWithRetry(string $routeTag, float $sur, float $oeste, float $norte, float $este): array|false
{
    $servers = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
    ];

    $query = "[out:json][timeout:120][maxsize:536870912];\n"
           . "relation[\"route\"=\"{$routeTag}\"]({$sur},{$oeste},{$norte},{$este});\n"
           . "out geom;";

    $ctx = stream_context_create([
        'http' => [
            'timeout'       => OVERPASS_TIMEOUT,
            'user_agent'    => 'KahirAI/1.0 (rutas montaña)',
            'ignore_errors' => true,
        ]
    ]);

    foreach ($servers as $server) {
        // 2 intentos por servidor
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $url = $server . '?data=' . urlencode($query);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) { sleep(3); continue; }

            $data = json_decode($raw, true);
            if (isset($data['elements'])) return $data['elements'];

            // Respuesta inválida (HTML de error, rate limit, etc.)
            sleep(5);
        }
    }

    return false;
}


// ═════════════════════════════════════════════════════════════════
//  ELEVACIÓN OPENTOPODATA
// ═════════════════════════════════════════════════════════════════

/**
 * Obtiene elevaciones SRTM para un array de puntos lat/lon.
 * Para rutas con más de ELEV_MAX_PTS puntos, muestrea e interpola.
 * Devuelve array de floats en el mismo orden que $points, o null si falla.
 */
function fetchElevations(array $points): ?array
{
    $n = count($points);
    if ($n === 0) return null;

    if ($n <= ELEV_MAX_PTS) {
        return fetchElevBatch($points);
    }

    return fetchWithSamplingAndInterp($points);
}

function fetchElevBatch(array $points): ?array
{
    $locs = implode('|', array_map(fn($p) => "{$p['lat']},{$p['lon']}", $points));
    $url  = ELEV_API . '?locations=' . $locs;
    $ctx  = stream_context_create(['http' => ['timeout' => 25, 'user_agent' => 'KahirAI/1.0']]);

    $raw  = @file_get_contents($url, false, $ctx);
    if ($raw === false) return null;

    $data = json_decode($raw, true);
    if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) return null;

    $elev = array_map(fn($r) => (float)($r['elevation'] ?? 0), $data['results']);
    sleep(1); // rate limit: 1 req/s
    return $elev;
}

function fetchWithSamplingAndInterp(array $points): ?array
{
    $n    = count($points);
    $step = (int)ceil($n / ELEV_MAX_PTS);

    $sampledIdx = [];
    for ($i = 0; $i < $n; $i += $step) $sampledIdx[] = $i;
    if (end($sampledIdx) !== $n - 1) $sampledIdx[] = $n - 1;

    $sampledPts  = array_map(fn($i) => $points[$i], $sampledIdx);
    $sampledElev = [];

    foreach (array_chunk($sampledPts, ELEV_MAX_PTS) as $batch) {
        $batchElev = fetchElevBatch($batch);
        if ($batchElev === null) return null;
        $sampledElev = array_merge($sampledElev, $batchElev);
    }

    if (count($sampledElev) !== count($sampledIdx)) return null;

    // Interpolación lineal para todos los puntos
    $allElev = array_fill(0, $n, 0.0);
    for ($s = 0; $s < count($sampledIdx) - 1; $s++) {
        $iStart = $sampledIdx[$s];
        $iEnd   = $sampledIdx[$s + 1];
        $eStart = $sampledElev[$s];
        $eEnd   = $sampledElev[$s + 1];
        $span   = $iEnd - $iStart;
        for ($i = $iStart; $i <= $iEnd; $i++) {
            $t = $span > 0 ? ($i - $iStart) / $span : 0;
            $allElev[$i] = $eStart + ($eEnd - $eStart) * $t;
        }
    }

    return $allElev;
}

/**
 * Añade <ele> a cada <trkpt> del contenido GPX usando las elevaciones dadas.
 * Opera sobre string (no archivo) para no releer el disco.
 */
function rewriteGpxWithEle(string $gpxContent, array $elevations): ?string
{
    $idx = 0;
    return preg_replace_callback(
        '/<trkpt\s+lat="([^"]+)"\s+lon="([^"]+)"\s*(?:\/>|>.*?<\/trkpt>)/s',
        function ($m) use (&$idx, $elevations) {
            if (!isset($elevations[$idx])) { $idx++; return $m[0]; }
            $ele = round($elevations[$idx], 1);
            $idx++;
            return "<trkpt lat=\"{$m[1]}\" lon=\"{$m[2]}\"><ele>{$ele}</ele></trkpt>";
        },
        $gpxContent
    );
}

/** Suma desnivel positivo acumulado */
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


// ═════════════════════════════════════════════════════════════════
//  GPX
// ═════════════════════════════════════════════════════════════════

/**
 * Ensambla los ways OSM en un track continuo con algoritmo greedy.
 * - Une el segmento más cercano al extremo actual en cada paso.
 * - Invierte el way si conecta mejor por su extremo final.
 * - Inserta separador NaN cuando el hueco es real (> 50 m).
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

    if (empty($ways))       return [];
    if (count($ways) === 1) return $ways[0];

    $chain    = array_shift($ways);
    $maxGapKm = 0.05; // 50 m → unión limpia; más → hueco real (nuevo trkseg)

    while (!empty($ways)) {
        $tail     = end($chain);
        $bestIdx  = 0;
        $bestDist = PHP_FLOAT_MAX;
        $bestRev  = false;

        foreach ($ways as $i => $way) {
            $dH = haversine($tail['lat'], $tail['lon'], $way[0]['lat'],                  $way[0]['lon']);
            $dF = haversine($tail['lat'], $tail['lon'], $way[count($way)-1]['lat'], $way[count($way)-1]['lon']);
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


// ═════════════════════════════════════════════════════════════════
//  DIFICULTAD / DURACIÓN / DISTANCIA
// ═════════════════════════════════════════════════════════════════

function hasDifficultyTag(array $tags): bool
{
    return isset($tags['sac_scale'], $tags['piste:difficulty'], $tags['mtb:scale'], $tags['difficulty'])
        || array_key_exists('sac_scale', $tags)
        || array_key_exists('piste:difficulty', $tags)
        || array_key_exists('mtb:scale', $tags);
}

function mapDifficulty(array $tags): string
{
    if (isset($tags['sac_scale'])) {
        return match($tags['sac_scale']) {
            'hiking'                         => 'beginner',
            'mountain_hiking'                => 'intermediate',
            'demanding_mountain_hiking', 'alpine_hiking',
            'demanding_alpine_hiking', 'difficult_alpine_hiking' => 'advanced',
            default                          => 'intermediate',
        };
    }

    if (isset($tags['piste:difficulty'])) {
        return match(strtolower($tags['piste:difficulty'])) {
            'novice', 'easy'                             => 'beginner',
            'intermediate'                               => 'intermediate',
            'advanced', 'expert', 'freeride', 'extreme' => 'advanced',
            default                                      => 'intermediate',
        };
    }

    if (isset($tags['mtb:scale'])) {
        $scale = (int)$tags['mtb:scale'];
        if ($scale <= 1) return 'beginner';
        if ($scale <= 2) return 'intermediate';
        return 'advanced';
    }

    $diff = strtolower($tags['difficulty'] ?? '');
    if ($diff) {
        if (str_contains($diff, 'easy')   || str_contains($diff, 'facil'))       return 'beginner';
        if (str_contains($diff, 'medium') || str_contains($diff, 'intermediate')) return 'intermediate';
        if (str_contains($diff, 'hard')   || str_contains($diff, 'difficult')
         || str_contains($diff, 'expert') || str_contains($diff, 'extreme'))      return 'advanced';
    }

    return 'intermediate';
}

function inferDifficulty(float $dist, ?int $elev, string $tipo): string
{
    $e = $elev ?? 0;

    if (in_array($tipo, ['ski_touring', 'snowshoeing'])) {
        if ($dist > 15 || $e > 800) return 'advanced';
        if ($dist > 6  || $e > 400) return 'intermediate';
        return 'beginner';
    }
    if (in_array($tipo, ['cycle_touring', 'btt'])) {
        if ($dist > 60) return 'advanced';
        if ($dist > 25) return 'intermediate';
        return 'beginner';
    }
    // hiking / general
    if ($dist > 20 || $e > 900) return 'advanced';
    if ($dist > 8  || $e > 400) return 'intermediate';
    return 'beginner';
}

function estimateDuration(float $dist, ?int $elev, string $tipo): float
{
    $e = $elev ?? 0;
    $h = match(true) {
        in_array($tipo, ['ski_touring', 'snowshoeing']) => ($dist / 3.0)  + ($e / 400.0),
        $tipo === 'cycle_touring'                       => ($dist / 15.0),
        $tipo === 'btt'                                 => ($dist / 10.0),
        default                                         => ($dist / 4.0)  + ($e / 600.0),
    };
    return round(max(0.5, min($h, 24.0)), 1);
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

function parseDuration(string $raw): ?float
{
    $raw = trim($raw);
    if (preg_match('/^(\d+):(\d{2})$/', $raw, $m))           return (float)$m[1] + (float)$m[2] / 60;
    if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/i', $raw, $m)) return ((float)($m[1] ?? 0)) + ((float)($m[2] ?? 0)) / 60;
    if (preg_match('/^(\d+(?:\.\d+)?)\s*h/i', $raw, $m))     return (float)$m[1];
    if (is_numeric($raw))                                     return (float)$raw;
    return null;
}


// ═════════════════════════════════════════════════════════════════
//  UTILIDADES
// ═════════════════════════════════════════════════════════════════

function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $R  = 6371.0;
    $dL = deg2rad($lat2 - $lat1);
    $dO = deg2rad($lon2 - $lon1);
    $a  = sin($dL/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dO/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function findNearby(array $existentes, float $lat, float $lng): ?array
{
    foreach ($existentes as $r) {
        if (!isset($r['start_lat'], $r['start_lng'])) continue;
        $dist = haversine($lat, $lng, (float)$r['start_lat'], (float)$r['start_lng']);
        if ($dist <= RADIO_DUPLICADO_KM) {
            return ['titulo' => $r['titulo'], 'metros' => (int)($dist * 1000)];
        }
    }
    return null;
}

function findByName(array $existentes, string $name): bool
{
    $norm = strtolower(trim($name));
    foreach ($existentes as $r) {
        if (strtolower(trim($r['titulo'] ?? '')) === $norm) return true;
    }
    return false;
}

function refineUbicacion(float $lat, float $lng, string $default): string
{
    if ($lat >= 42.0 && $lat <= 43.5) {
        if ($lng >= 1.5  && $lng <= 3.5)  return 'Eastern_Pyrenees';
        if ($lng >= -0.5 && $lng <  1.5)  return 'Central_Pyrenees';
        if ($lng >= -2.5 && $lng < -0.5)  return 'Western_Pyrenees';
    }
    return $default;
}

function osmIdExists(mysqli $conn, int $osmId): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM rutas WHERE osm_id = ? LIMIT 1");
    $stmt->bind_param('i', $osmId);
    $stmt->execute();
    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $existe;
}

function loadExistingRoutes(mysqli $conn): array
{
    $res = $conn->query("SELECT titulo, start_lat, start_lng, osm_id FROM rutas WHERE estado != 'eliminada'");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function getSistemaUserId(mysqli $conn): int
{
    $res = $conn->query("SELECT id FROM usuarios WHERE activo = 1 ORDER BY FIELD(rol,'admin','editor','usuario') ASC, id ASC LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    return $row ? (int)$row['id'] : 1;
}

function addOsmIdColumn(mysqli $conn): void
{
    $check = $conn->query("SHOW COLUMNS FROM rutas LIKE 'osm_id'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE rutas ADD COLUMN osm_id BIGINT UNSIGNED NULL DEFAULT NULL UNIQUE, ADD INDEX idx_osm_id (osm_id)");
        log_msg("🔧 Columna osm_id añadida");
    }
}

function filterMap(array $map, string $filter): array
{
    if ($filter === 'all') return $map;
    return isset($map[$filter]) ? [$filter => $map[$filter]] : $map;
}

function cleanText(?string $text): ?string
{
    if ($text === null) return null;
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', ' ', $text);
    return mb_substr(trim($text), 0, 5000) ?: null;
}

function getArg(string $key, mixed $default): mixed
{
    global $argv;
    foreach ($argv ?? [] as $arg) {
        if (preg_match("/^--{$key}=(.+)$/", $arg, $m)) return $m[1];
    }
    return $default;
}

function log_msg(string $msg): void
{
    $line = '[' . date('H:i:s') . '] ' . $msg;
    echo $line . PHP_EOL;
    @file_put_contents(LOG_FILE, $line . PHP_EOL, FILE_APPEND);
}
