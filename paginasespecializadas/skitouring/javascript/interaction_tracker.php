<?php
require_once __DIR__ . '/../../../includes/security.php';
secure_session_start();
require_once '../../inicio_sesion/conexion.php';
require_once '../../inicio_sesion/config.php';

define('LOG_DIR', __DIR__ . '/logs');
define('ERROR_LOG', LOG_DIR . '/error.log');
define('DEBUG_LOG', LOG_DIR . '/debug.log');

if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

function writeLog($message, $type = 'DEBUG') {
    $logFile = $type === 'ERROR' ? ERROR_LOG : DEBUG_LOG;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] [$type] $message" . PHP_EOL, FILE_APPEND);
}

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    writeLog("Intento de acceso sin sesión de usuario", 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Entra en tu cuenta para continuar.']);
    exit;
}

// Resolver usuario_id
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($userId === 0) {
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $_SESSION['usuario']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'No encontramos tu cuenta. Vuelve a entrar.']);
        exit;
    }
    $userId = (int)$row['id'];
}

$method  = $_SERVER['REQUEST_METHOD'];
$action  = '';
$routeId = null;
$interactions = null;

if ($method === 'GET') {
    // Por GET solo se permite consultar el estado (no modifica datos)
    $action  = $_GET['action']  ?? '';
    $routeId = $_GET['routeId'] ?? null;
    if ($action !== 'check') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        exit;
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        $data = [];
    }
    // CSRF: cabecera X-CSRF-Token (csrf.js) o campo csrf_token en el cuerpo JSON
    $tokenCuerpo = (isset($data['csrf_token']) && is_string($data['csrf_token'])) ? $data['csrf_token'] : null;
    csrf_verify(true, $tokenCuerpo);

    if (isset($data['action'])) {
        $action  = $data['action'];
        $routeId = $data['routeId'] ?? null;
    } elseif (isset($data['interactions']) && is_array($data['interactions'])) {
        $interactions = $data['interactions'];
    } else {
        unset($data['csrf_token']);
        $interactions = $data;
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$response = ['success' => true, 'messages' => []];

// 'view_end' solo actualiza la duración de la última vista (no cuenta otra vista).
// Se procesa fuera de la transacción para que un fallo no deshaga el resto.
if ($interactions !== null) {
    $pendientes = [];
    foreach ($interactions as $interaction) {
        if (!is_array($interaction)) {
            continue;
        }
        $tipo = $interaction['type'] ?? null;
        if ($tipo === 'map_interaction') {
            // El ENUM de user_interactions no lo admite: se ignora
            continue;
        }
        if ($tipo === 'view_end') {
            updateViewDuration($conn, $userId, $interaction['routeId'] ?? null, $interaction['viewDuration'] ?? null);
            continue;
        }
        $pendientes[] = $interaction;
    }
    $interactions = $pendientes;
    if (!$interactions) {
        $conn->close();
        echo json_encode($response);
        exit;
    }
}

try {
    $conn->begin_transaction();

    if ($interactions !== null) {
        foreach ($interactions as $interaction) {
            $routeId = $interaction['routeId'] ?? null;
            $type    = $interaction['type']    ?? null;
            if (!$routeId || !$type) {
                throw new Exception("Missing required fields for an interaction");
            }
            processInteraction($conn, $userId, (int)$routeId, $type);
        }
    } else {
        if (!$routeId) {
            throw new Exception('Route ID is required');
        }
        switch ($action) {
            case 'check':
                $response = checkInteractionStatus($conn, $userId, (int)$routeId);
                break;
            case 'favorite':
            case 'complete':
                processInteraction($conn, $userId, (int)$routeId, $action);
                break;
            default:
                throw new Exception('Invalid action');
        }
    }

    updatePopularityScores($conn);
    $conn->commit();
    writeLog("Transacción completada exitosamente");

    maybeUpdateUserPreferences($conn, $userId);

} catch (Throwable $e) {
    $conn->rollback();
    writeLog("Error en la transacción: " . $e->getMessage(), 'ERROR');
    error_log('interaction_tracker.php: ' . $e->getMessage());
    $response = ['success' => false, 'error' => 'No se ha podido registrar la acción. Inténtalo de nuevo más tarde.'];
}

$conn->close();
echo json_encode($response);

// ─── Funciones ────────────────────────────────────────────────────────────────

/**
 * Guarda la duración (segundos) en la última fila 'view' de este usuario y ruta.
 * No inserta una vista nueva. Si falla (p. ej. la columna no existe), solo se registra en el log.
 */
function updateViewDuration($conn, $userId, $routeId, $seconds) {
    $routeId = (int)$routeId;
    if ($routeId <= 0 || !is_numeric($seconds)) {
        return;
    }
    $seconds = max(0, min((int)$seconds, 86400));
    try {
        $stmt = $conn->prepare(
            "UPDATE user_interactions SET duracion_seg = ?
             WHERE usuario_id = ? AND ruta_id = ? AND tipo = 'view'
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->bind_param("iii", $seconds, $userId, $routeId);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        writeLog("Error guardando la duración de la vista: " . $e->getMessage(), 'ERROR');
        error_log('interaction_tracker.php (view_end): ' . $e->getMessage());
    }
}

function processInteraction($conn, $userId, $routeId, $type) {
    $validTypes = ['view', 'download', 'favorite', 'complete'];
    if (!in_array($type, $validTypes)) {
        throw new Exception("Invalid interaction type: $type");
    }

    if ($type === 'favorite' || $type === 'complete') {
        $table      = $type === 'favorite' ? 'user_favorites' : 'user_completed';
        $countField = $type === 'favorite' ? 'favorite_count' : 'complete_count';

        $checkStmt = $conn->prepare("SELECT id FROM $table WHERE usuario_id = ? AND ruta_id = ?");
        $checkStmt->bind_param("ii", $userId, $routeId);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();

        if (!$exists) {
            $insertStmt = $conn->prepare("INSERT INTO $table (usuario_id, ruta_id) VALUES (?, ?)");
            $insertStmt->bind_param("ii", $userId, $routeId);
            $insertStmt->execute();
            $insertStmt->close();

            $updateStmt = $conn->prepare("
                INSERT INTO route_popularity (ruta_id, $countField)
                VALUES (?, 1)
                ON DUPLICATE KEY UPDATE
                $countField = $countField + 1,
                ultima_actualizacion = NOW()
            ");
            $updateStmt->bind_param("i", $routeId);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            $deleteStmt = $conn->prepare("DELETE FROM $table WHERE usuario_id = ? AND ruta_id = ?");
            $deleteStmt->bind_param("ii", $userId, $routeId);
            $deleteStmt->execute();
            $deleteStmt->close();

            $updateStmt = $conn->prepare("
                UPDATE route_popularity
                SET $countField = GREATEST($countField - 1, 0),
                    ultima_actualizacion = NOW()
                WHERE ruta_id = ?
            ");
            $updateStmt->bind_param("i", $routeId);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }

    // Registrar en user_interactions
    $stmt = $conn->prepare(
        "INSERT INTO user_interactions (usuario_id, ruta_id, tipo) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iis", $userId, $routeId, $type);
    if (!$stmt->execute()) {
        throw new Exception("Error inserting user interaction: " . $stmt->error);
    }
    $stmt->close();

    // Actualizar contadores de visitas/descargas
    if ($type === 'view' || $type === 'download') {
        $field = $type . '_count';
        $stmt  = $conn->prepare("
            INSERT INTO route_popularity (ruta_id, $field)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE
            $field = $field + 1,
            ultima_actualizacion = NOW()
        ");
        $stmt->bind_param("i", $routeId);
        if (!$stmt->execute()) {
            throw new Exception("Error updating route popularity: " . $stmt->error);
        }
        $stmt->close();
    }
}

function checkInteractionStatus($conn, $userId, $routeId) {
    $response = ['success' => true];

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM user_completed WHERE usuario_id = ? AND ruta_id = ?"
    );
    $stmt->bind_param("ii", $userId, $routeId);
    $stmt->execute();
    $response['isCompleted'] = (bool)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM user_favorites WHERE usuario_id = ? AND ruta_id = ?"
    );
    $stmt->bind_param("ii", $userId, $routeId);
    $stmt->execute();
    $response['isFavorite'] = (bool)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    return $response;
}

function maybeUpdateUserPreferences($conn, $userId) {
    try {
        $stmt = $conn->prepare(
            "SELECT ultima_actualizacion, preferencias_inferidas
             FROM user_preferences WHERE usuario_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $pref = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $inferidas = $pref ? (json_decode($pref['preferencias_inferidas'] ?? '{}', true) ?? []) : [];
        $totalPrev = (int)($inferidas['total_interactions'] ?? 0);

        $shouldUpdate = !$pref
            || (time() - strtotime($pref['ultima_actualizacion'])) > 21600
            || $totalPrev < 5;

        if (!$shouldUpdate) return;

        $stmt = $conn->prepare(
            "SELECT r.dificultad, r.tipo_ruta, r.ubicacion,
                    r.distancia_km, r.duracion_horas, r.elevacion_ganada,
                    ui.tipo
             FROM user_interactions ui
             JOIN rutas r ON ui.ruta_id = r.id
             WHERE ui.usuario_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (count($rows) < 5) return;

        $diffCounts     = ['beginner' => 0, 'intermediate' => 0, 'advanced' => 0];
        $activityCounts = [];
        $locationCounts = [];
        $distances      = [];
        $durations      = [];

        foreach ($rows as $row) {
            $diff = strtolower($row['dificultad'] ?? '');
            if (isset($diffCounts[$diff])) $diffCounts[$diff]++;
            if (!empty($row['tipo_ruta'])) {
                $activityCounts[$row['tipo_ruta']] = ($activityCounts[$row['tipo_ruta']] ?? 0) + 1;
            }
            if (!empty($row['ubicacion'])) {
                $locationCounts[$row['ubicacion']] = ($locationCounts[$row['ubicacion']] ?? 0) + 1;
            }
            if (!empty($row['distancia_km']))    $distances[] = (float)$row['distancia_km'];
            if (!empty($row['duracion_horas']))  $durations[] = (float)$row['duracion_horas'];
        }

        $experienceLevel = 'beginner';
        if ($diffCounts['advanced'] > $diffCounts['beginner'] && $diffCounts['advanced'] > $diffCounts['intermediate']) {
            $experienceLevel = 'advanced';
        } elseif ($diffCounts['intermediate'] > $diffCounts['beginner'] || count($rows) > 50) {
            $experienceLevel = 'intermediate';
        }

        arsort($activityCounts);
        arsort($locationCounts);
        $prefActivities = json_encode(array_keys(array_slice($activityCounts, 0, 3, true)));
        $prefLocations  = json_encode(array_keys(array_slice($locationCounts, 0, 3, true)));
        $avgDist  = $distances ? array_sum($distances) / count($distances) : 0;
        $avgDur   = $durations ? array_sum($durations) / count($durations) : 0;
        $inferidas['total_interactions'] = count($rows);
        $inferidas = json_encode($inferidas);

        $stmt = $conn->prepare(
            "INSERT INTO user_preferences
                (usuario_id, nivel_experiencia, actividades_preferidas, ubicaciones_habituales,
                 distancia_max_km, duracion_max_horas, preferencias_inferidas, ultima_actualizacion)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                nivel_experiencia      = VALUES(nivel_experiencia),
                actividades_preferidas = VALUES(actividades_preferidas),
                ubicaciones_habituales = VALUES(ubicaciones_habituales),
                distancia_max_km       = VALUES(distancia_max_km),
                duracion_max_horas     = VALUES(duracion_max_horas),
                preferencias_inferidas = VALUES(preferencias_inferidas),
                ultima_actualizacion   = NOW()"
        );
        $stmt->bind_param("isssdds",
            $userId, $experienceLevel, $prefActivities, $prefLocations,
            $avgDist, $avgDur, $inferidas
        );
        $stmt->execute();
        $stmt->close();

        writeLog("Preferencias actualizadas para usuario_id=$userId (nivel=$experienceLevel)");

    } catch (Exception $e) {
        writeLog("Error actualizando preferencias para usuario_id=$userId: " . $e->getMessage(), 'ERROR');
    }
}

function updatePopularityScores($conn) {
    $stmt = $conn->prepare("
        UPDATE route_popularity rp
        JOIN rutas r ON rp.ruta_id = r.id
        JOIN (
            SELECT ruta_id,
                   COUNT(DISTINCT CASE WHEN tipo = 'view'                       THEN usuario_id END) AS unique_views,
                   COUNT(DISTINCT CASE WHEN tipo IN ('favorite','complete') THEN usuario_id END) AS unique_interactions
            FROM user_interactions
            GROUP BY ruta_id
        ) ui ON rp.ruta_id = ui.ruta_id
        SET rp.popularity_score = (
            rp.view_count      * 1 +
            rp.download_count  * 2 +
            rp.favorite_count  * 3 +
            rp.complete_count  * 4 +
            ui.unique_views    * 0.5 +
            ui.unique_interactions * 1.5
        ) * EXP(-0.05 * DATEDIFF(CURDATE(), r.fecha_subida)) / (DATEDIFF(CURDATE(), r.fecha_subida) + 1)
    ");

    if (!$stmt->execute()) {
        throw new Exception("Error calculating popularity scores: " . $stmt->error);
    }
    $stmt->close();
}
