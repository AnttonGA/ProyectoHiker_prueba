<?php
/**
 * procesar_subida.php — Procesa la subida de rutas GPX.
 *
 * Validaciones: autenticación, tipo/tamaño de archivo, estructura GPX,
 * mínimo de puntos, sanitización de nombres. Auto-publica sin moderación.
 * Las imágenes son opcionales (usa imagen por defecto según actividad).
 */

declare(strict_types=1);
ini_set('display_errors', '0');
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();

require_once __DIR__ . '/../inicio_sesion/config.php';
require_once __DIR__ . '/../inicio_sesion/conexion.php';

// ─── Constantes ───────────────────────────────────────────────────────────────

const GPX_MAX_BYTES   = 10 * 1024 * 1024;   // 10 MB
const IMG_MAX_BYTES   =  5 * 1024 * 1024;   //  5 MB por imagen
const IMG_MAX_COUNT   = 8;
const RUTAS_BASE_PATH = __DIR__ . '/../../paginasespecializadas/skitouring/rutas_st/';
const RUTAS_WEB_PATH  = 'rutas_st/';

const DEFAULT_IMAGES = [
    'ski_touring'   => 'default_images/skitouring.jpg',
    'hiking'        => 'default_images/hiking.jpg',
    'cycle_touring' => 'default_images/cyclotouring.jpg',
    'btt'           => 'default_images/btt.jpg',
    'kayaking'      => 'default_images/kayaking.jpg',
    'default'       => 'default_images/hiking.jpg',
];

const VALID_ACTIVITIES  = ['hiking', 'ski_touring', 'climbing', 'cycle_touring', 'snowshoeing', 'via_ferrata', 'btt', 'kayaking'];
const VALID_DIFFICULTIES = ['beginner', 'intermediate', 'advanced'];

// ─── Auth ─────────────────────────────────────────────────────────────────────

if (!isset($_SESSION['usuario'])) {
    redirectError('Debes iniciar sesión para subir rutas.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectError('Método no permitido.');
}

// Resolver usuario_id (puede venir de la sesión o hay que buscarlo)
$sessionUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($sessionUserId === 0) {
    // Fallback: buscar por username
    $stmtUid = $conn->prepare("SELECT id FROM usuarios WHERE username = ? LIMIT 1");
    $stmtUid->bind_param("s", $_SESSION['usuario']);
    $stmtUid->execute();
    $rowUid = $stmtUid->get_result()->fetch_assoc();
    $stmtUid->close();
    if (!$rowUid) {
        redirectError('No se pudo identificar al usuario en la base de datos.');
    }
    $sessionUserId = (int)$rowUid['id'];
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function redirectError(string $msg): never {
    $_SESSION['upload_error'] = $msg;
    header('Location: subir_ruta.html?error=1&msg=' . rawurlencode($msg));
    exit;
}

function redirectSuccess(int $routeId): never {
    $_SESSION['upload_success'] = $routeId;
    header('Location: subir_ruta.html?success=1&id=' . $routeId);
    exit;
}

/** Slugifica un string para usarlo como nombre de carpeta. */
function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[áàäâ]/u', 'a', $text);
    $text = preg_replace('/[éèëê]/u', 'e', $text);
    $text = preg_replace('/[íìïî]/u', 'i', $text);
    $text = preg_replace('/[óòöô]/u', 'o', $text);
    $text = preg_replace('/[úùüû]/u', 'u', $text);
    $text = preg_replace('/ñ/u',      'n', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

/** Sanitiza el nombre de un fichero conservando extensión. */
function safeFilename(string $name): string {
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $base = pathinfo($name, PATHINFO_FILENAME);
    $base = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $base);
    return substr($base, 0, 80) . '.' . $ext;
}

// ─── 0. Token CSRF (lo añade csrf.js al formulario) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid()) {
    redirectError('La sesión ha caducado. Recarga la página e inténtalo de nuevo.');
}

// ─── 1. Validar campos del formulario ────────────────────────────────────────

$nombre      = trim($_POST['nombre']       ?? '');
$descripcion = trim($_POST['descripcion']  ?? '');
$tipoRuta    = trim($_POST['tipo_ruta']    ?? '');
$location    = trim($_POST['location_ruta'] ?? '');
$dificultad  = strtolower(trim($_POST['dificultad'] ?? ''));
$pregunta1   = trim($_POST['pregunta1']    ?? '');
$pregunta2   = trim($_POST['pregunta2']    ?? '');
$pregunta3   = trim($_POST['pregunta3']    ?? '');
$pregunta4   = trim($_POST['pregunta4']    ?? '');

if (empty($nombre))      redirectError('El nombre de la ruta es obligatorio.');
if (strlen($nombre) > 120) redirectError('El nombre no puede superar 120 caracteres.');
if (empty($descripcion)) redirectError('La descripción es obligatoria.');
if (empty($location))    redirectError('La zona es obligatoria.');
if (!in_array($tipoRuta,   VALID_ACTIVITIES,   true)) redirectError('Tipo de actividad no válido.');
if (!in_array($dificultad, VALID_DIFFICULTIES, true)) redirectError('Nivel de dificultad no válido.');
// Las preguntas adicionales son opcionales: acceso, material, notas técnicas, mejor época

// ─── 2. Validar archivo GPX ───────────────────────────────────────────────────

if (!isset($_FILES['archivo_gpx']) || $_FILES['archivo_gpx']['error'] !== UPLOAD_ERR_OK) {
    $phpErr = $_FILES['archivo_gpx']['error'] ?? -1;
    $msg = match ($phpErr) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo GPX supera el tamaño máximo permitido.',
        UPLOAD_ERR_NO_FILE  => 'No se ha seleccionado ningún archivo GPX.',
        default             => 'Error al subir el archivo GPX (código: ' . $phpErr . ').',
    };
    redirectError($msg);
}

$gpxFile = $_FILES['archivo_gpx'];

// Tamaño
if ($gpxFile['size'] > GPX_MAX_BYTES) {
    redirectError('El archivo GPX no puede superar 10 MB.');
}

// Extensión
$gpxExt = strtolower(pathinfo($gpxFile['name'], PATHINFO_EXTENSION));
if ($gpxExt !== 'gpx') {
    redirectError('Solo se aceptan archivos GPX (.gpx).');
}

// Tipo MIME real
$finfo   = finfo_open(FILEINFO_MIME_TYPE);
$mimeReal = finfo_file($finfo, $gpxFile['tmp_name']);
finfo_close($finfo);
$validMimes = ['text/xml', 'application/xml', 'application/gpx+xml', 'application/octet-stream', 'text/plain'];
if (!in_array($mimeReal, $validMimes, true)) {
    redirectError('El contenido del archivo no parece ser XML/GPX válido.');
}

// ─── 3. Parsear y validar estructura GPX ──────────────────────────────────────

$gpxData = parseAndValidateGPX($gpxFile['tmp_name']);
if (isset($gpxData['error'])) {
    redirectError($gpxData['error']);
}

// ─── 4. Crear carpeta de la ruta ──────────────────────────────────────────────

$folderName = slugify($nombre) . '-' . time();
$folderPath = RUTAS_BASE_PATH . $folderName;

if (!is_dir(RUTAS_BASE_PATH)) {
    redirectError('El directorio de rutas no existe en el servidor.');
}
if (!mkdir($folderPath, 0755, true)) {
    redirectError('No se pudo crear el directorio para la ruta.');
}

// ─── 5. Mover el GPX ─────────────────────────────────────────────────────────

$gpxSafeName = safeFilename($gpxFile['name']);
$gpxDest     = $folderPath . '/' . $gpxSafeName;
if (!move_uploaded_file($gpxFile['tmp_name'], $gpxDest)) {
    rmdir($folderPath);
    redirectError('Error al guardar el archivo GPX en el servidor.');
}

// ─── 6. Manejar imágenes (opcionales) ────────────────────────────────────────

$imagenesPaths = [];
$hasImages     = !empty($_FILES['imagenes']['name'][0]);

if ($hasImages) {
    $imgCount = count($_FILES['imagenes']['name']);
    if ($imgCount > IMG_MAX_COUNT) {
        redirectError('Máximo ' . IMG_MAX_COUNT . ' imágenes por ruta.');
    }

    // La extensión se decide por el tipo REAL del archivo, nunca por el nombre que envía el usuario:
    // antes una imagen manipulada llamada "x.php" se guardaba como .php y se podía ejecutar.
    $validImgMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $validImgTypes = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG, 'image/webp' => IMAGETYPE_WEBP];

    foreach ($_FILES['imagenes']['name'] as $k => $imgName) {
        if ($_FILES['imagenes']['error'][$k] !== UPLOAD_ERR_OK) continue;
        $imgNameHtml = htmlspecialchars((string) $imgName, ENT_QUOTES, 'UTF-8');
        if ($_FILES['imagenes']['size'][$k] > IMG_MAX_BYTES) {
            redirectError("La imagen «{$imgNameHtml}» supera 5 MB.");
        }

        $imgTmp  = $_FILES['imagenes']['tmp_name'][$k];
        $imgMime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $imgTmp);
        $imgInfo = @getimagesize($imgTmp);
        if (!isset($validImgMimes[$imgMime]) || $imgInfo === false || $imgInfo[2] !== $validImgTypes[$imgMime]) {
            redirectError("El archivo «{$imgNameHtml}» no es una imagen válida (JPG, PNG o WebP).");
        }

        $imgSafe = 'img-' . ($k + 1) . '-' . bin2hex(random_bytes(4)) . '.' . $validImgMimes[$imgMime];
        $imgDest = $folderPath . '/' . $imgSafe;
        if (move_uploaded_file($_FILES['imagenes']['tmp_name'][$k], $imgDest)) {
            $imagenesPaths[] = RUTAS_WEB_PATH . $folderName . '/' . $imgSafe;
        }
    }
}

// Sin imágenes → usar imagen por defecto según actividad
if (empty($imagenesPaths)) {
    $defaultImg = DEFAULT_IMAGES[$tipoRuta] ?? DEFAULT_IMAGES['default'];
    $imagenesPaths[] = RUTAS_WEB_PATH . $defaultImg;
}

// ─── 7. Insertar ruta en BD ───────────────────────────────────────────────────

$elevation      = $gpxData['elevation']      ?? null;
$length         = $gpxData['length']         ?? null;
$duration       = $gpxData['duration']       ?? null;
$startLat       = $gpxData['startLat']       ?? null;
$startLng       = $gpxData['startLng']       ?? null;
$startElevation = $gpxData['startElevation'] ?? null;

$stmt = $conn->prepare(
    "INSERT INTO rutas
     (usuario_id, gpx_path, titulo, descripcion, elevacion_ganada, duracion_horas,
      dificultad, ubicacion, distancia_km, start_lat, start_lng, start_elevation,
      tipo_ruta, ruta_carpeta, pregunta1, pregunta2, pregunta3, pregunta4)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    redirectError('Error preparando la consulta: ' . $conn->error);
}

$stmt->bind_param(
    "isssddssddddssssss",
    $sessionUserId,
    $gpxSafeName,
    $nombre,
    $descripcion,
    $elevation,
    $duration,
    $dificultad,
    $location,
    $length,
    $startLat,
    $startLng,
    $startElevation,
    $tipoRuta,
    $folderName,
    $pregunta1,
    $pregunta2,
    $pregunta3,
    $pregunta4
);

if (!$stmt->execute()) {
    redirectError('Error al guardar la ruta: ' . $stmt->error);
}

$routeId = (int)$conn->insert_id;
$stmt->close();

// ─── 8. Insertar imágenes ──────────────────────────────────────────────────────

$stmtImg = $conn->prepare("INSERT INTO ruta_imagenes (ruta_id, imagen_path) VALUES (?, ?)");
if ($stmtImg) {
    foreach ($imagenesPaths as $imgPath) {
        $stmtImg->bind_param("is", $routeId, $imgPath);
        $stmtImg->execute();
    }
    $stmtImg->close();
}

// ─── 9. route_popularity — gestionado automáticamente por trigger after_ruta_insert

$conn->close();

redirectSuccess($routeId);

// ─── Funciones GPX ────────────────────────────────────────────────────────────

/**
 * Parsea un fichero GPX y devuelve métricas o ['error' => '...'].
 * Tolera ficheros sin timestamps (calcula distancia/desnivel igualmente).
 */
function parseAndValidateGPX(string $filePath): array {
    // Suprimir warnings de simplexml para mostrar error propio
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($filePath);
    libxml_clear_errors();

    if ($xml === false) {
        return ['error' => 'El archivo GPX no es XML válido.'];
    }

    // Verificar que es un GPX
    $rootName = strtolower($xml->getName());
    if ($rootName !== 'gpx') {
        return ['error' => 'El archivo no tiene estructura GPX válida (raíz: ' . $rootName . ').'];
    }

    // Recopilar todos los puntos de tracks y rutas (rte/wpt)
    $points = collectTrackPoints($xml);

    if (count($points) < 2) {
        return ['error' => 'El GPX debe contener al menos 2 puntos de track para calcular la ruta.'];
    }

    // Punto inicial
    $first          = $points[0];
    $startLat       = (float)$first['lat'];
    $startLng       = (float)$first['lon'];
    $startElevation = isset($first['ele']) ? round((float)$first['ele'], 1) : null;

    // Métricas
    $totalAscent = 0.0;
    $totalLength = 0.0;
    $prevPoint   = null;

    foreach ($points as $pt) {
        if ($prevPoint) {
            $totalLength += haversine(
                (float)$prevPoint['lat'], (float)$prevPoint['lon'],
                (float)$pt['lat'],        (float)$pt['lon']
            );
            if (isset($pt['ele'], $prevPoint['ele'])) {
                $diff = (float)$pt['ele'] - (float)$prevPoint['ele'];
                if ($diff > 0) $totalAscent += $diff;
            }
        }
        $prevPoint = $pt;
    }

    // Duración: solo si hay timestamps en los puntos
    $duration = null;
    if (isset($points[0]['time']) && isset($points[count($points) - 1]['time'])) {
        try {
            $t0 = new DateTime($points[0]['time']);
            $t1 = new DateTime($points[count($points) - 1]['time']);
            $diff = $t0->diff($t1);
            $duration = $diff->days * 24 + $diff->h + round($diff->i / 60, 1);
        } catch (Exception) {
            $duration = null;
        }
    }

    // Estimación de duración si no hay timestamps (Naismith: 5 km/h + 1 h por 600 m)
    if ($duration === null && $totalLength > 0) {
        $duration = round($totalLength / 5 + $totalAscent / 600, 1);
    }

    return [
        'elevation'      => round($totalAscent, 0),
        'length'         => round($totalLength, 2),
        'duration'       => $duration,
        'startLat'       => $startLat,
        'startLng'       => $startLng,
        'startElevation' => $startElevation,
        'point_count'    => count($points),
    ];
}

/**
 * Recoge puntos de todas las secciones del GPX (trk/trkseg/trkpt y rte/rtept).
 * Devuelve array de ['lat'=>, 'lon'=>, 'ele'=>?, 'time'=>?].
 */
function collectTrackPoints(SimpleXMLElement $xml): array {
    $points = [];

    // Detectar namespace GPX si existe
    $ns = array_keys($xml->getNamespaces(true));
    $ns = !empty($ns[0]) ? $ns[0] : null;

    // Intentar con namespace
    if ($ns) {
        $xml->registerXPathNamespace('g', 'http://www.topografix.com/GPX/1/1');
        $xml->registerXPathNamespace('g0', 'http://www.topografix.com/GPX/1/0');
    }

    // Buscar trkpts (tracks)
    foreach ($xml->trk ?? [] as $trk) {
        foreach ($trk->trkseg ?? [] as $seg) {
            foreach ($seg->trkpt ?? [] as $pt) {
                $points[] = extractPoint($pt);
            }
        }
    }

    // Si no hay track points, intentar con route points
    if (empty($points)) {
        foreach ($xml->rte ?? [] as $rte) {
            foreach ($rte->rtept ?? [] as $pt) {
                $points[] = extractPoint($pt);
            }
        }
    }

    // Si sigue vacío, intentar waypoints
    if (empty($points)) {
        foreach ($xml->wpt ?? [] as $wpt) {
            $points[] = extractPoint($wpt);
        }
    }

    return $points;
}

function extractPoint(SimpleXMLElement $pt): array {
    $point = [
        'lat' => (string)$pt['lat'],
        'lon' => (string)$pt['lon'],
    ];
    if (isset($pt->ele)) {
        $point['ele'] = (string)$pt->ele;
    }
    if (isset($pt->time)) {
        $point['time'] = (string)$pt->time;
    }
    return $point;
}

function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R  = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $R * 2 * asin(sqrt($a)) * 1.02; // ×1.02 para compensar irregularidades del terreno
}
