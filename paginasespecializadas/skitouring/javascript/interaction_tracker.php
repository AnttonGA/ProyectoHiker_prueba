<?php
session_start();
require_once '../../inicio_sesion/conexion.php';
require_once '../../inicio_sesion/config.php';

// Configuración de logging
define('LOG_DIR', __DIR__ . '/logs');
define('ERROR_LOG', LOG_DIR . '/error.log');
define('DEBUG_LOG', LOG_DIR . '/debug.log');

if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0777, true);
}

function writeLog($message, $type = 'DEBUG') {
    $logFile = $type === 'ERROR' ? ERROR_LOG : DEBUG_LOG;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    writeLog("Intento de acceso sin sesión de usuario", 'ERROR');
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Obtener el ID del usuario
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $_SESSION['usuario']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    writeLog("Usuario no encontrado en la base de datos: " . $_SESSION['usuario'], 'ERROR');
    echo json_encode(['success' => false, 'error' => 'User not found in database']);
    exit;
}

$userId = $result->fetch_assoc()['id'];
$stmt->close();

$method = $_SERVER['REQUEST_METHOD'];
$action = '';
$routeId = null;
$interactions = null;

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    $routeId = $_GET['routeId'] ?? null;
} elseif ($method === 'POST') {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    if (isset($data['action'])) {
        $action = $data['action'];
        $routeId = $data['routeId'] ?? null;
    } else {
        $interactions = $data;
    }
}

$response = ['success' => true, 'messages' => []];

try {
    $conn->begin_transaction();

    if ($interactions !== null) {
        // Bulk interaction processing
        foreach ($interactions as $interaction) {
            $routeId = $interaction['routeId'] ?? null;
            $type = $interaction['type'] ?? null;
            $viewDuration = $interaction['viewDuration'] ?? null;

            if (!$routeId || !$type) {
                writeLog("Campos requeridos faltantes: routeId=$routeId, type=$type", 'ERROR');
                throw new Exception("Missing required fields for an interaction");
            }

            processInteraction($conn, $userId, $routeId, $type, $viewDuration);
        }
    } else {
        // Single interaction processing
        if (!$routeId) {
            throw new Exception('Route ID is required');
        }

        switch ($action) {
            case 'check':
                $response = checkInteractionStatus($conn, $userId, $routeId);
                break;
            case 'favorite':
            case 'complete':
                processInteraction($conn, $userId, $routeId, $action);
                break;
            default:
                throw new Exception('Invalid action');
        }
    }

    // Calculate and update popularity scores
    updatePopularityScores($conn);

    $conn->commit();
    writeLog("Transacción completada exitosamente");
} catch (Exception $e) {
    $conn->rollback();
    writeLog("Error en la transacción: " . $e->getMessage(), 'ERROR');
    $response = ['success' => false, 'error' => $e->getMessage()];
}

$conn->close();

echo json_encode($response);
writeLog("Respuesta enviada: " . json_encode($response));

function processInteraction($conn, $userId, $routeId, $type, $viewDuration = null) {
    $validTypes = ['view', 'download', 'favorite', 'complete'];
    if (!in_array($type, $validTypes)) {
        writeLog("Tipo de interacción inválido: $type", 'ERROR');
        throw new Exception("Invalid interaction type: $type");
    }

    if ($type === 'favorite' || $type === 'complete') {
        $table = $type === 'favorite' ? 'user_favorites' : 'completed_routes';
        $countField = $type === 'favorite' ? 'favorite_count' : 'complete_count';
        
        // Check if the interaction already exists
        $checkStmt = $conn->prepare("SELECT * FROM $table WHERE user_id = ? AND route_id = ?");
        $checkStmt->bind_param("ii", $userId, $routeId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $exists = $result->num_rows > 0;
        $checkStmt->close();

        if (!$exists) {
            // Add the interaction
            $insertStmt = $conn->prepare("INSERT INTO $table (user_id, route_id) VALUES (?, ?)");
            $insertStmt->bind_param("ii", $userId, $routeId);
            $insertStmt->execute();
            $insertStmt->close();

            // Increment count in route_popularity
            $updateStmt = $conn->prepare("
                INSERT INTO route_popularity (route_id, $countField, last_updated)
                VALUES (?, 1, NOW())
                ON DUPLICATE KEY UPDATE 
                $countField = $countField + 1,
                last_updated = NOW()
            ");
            $updateStmt->bind_param("i", $routeId);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // Remove the interaction
            $deleteStmt = $conn->prepare("DELETE FROM $table WHERE user_id = ? AND route_id = ?");
            $deleteStmt->bind_param("ii", $userId, $routeId);
            $deleteStmt->execute();
            $deleteStmt->close();

            // Decrement count in route_popularity
            $updateStmt = $conn->prepare("
                UPDATE route_popularity 
                SET $countField = GREATEST($countField - 1, 0),
                    last_updated = NOW()
                WHERE route_id = ?
            ");
            $updateStmt->bind_param("i", $routeId);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }

    // Insert into user_interactions
    $stmt = $conn->prepare("INSERT INTO user_interactions (user_id, route_id, interaction_type, view_duration) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $userId, $routeId, $type, $viewDuration);
    
    if (!$stmt->execute()) {
        throw new Exception("Error inserting user interaction: " . $stmt->error);
    }
    $stmt->close();

    // Update view and download counts
    if ($type === 'view' || $type === 'download') {
        $field = $type . '_count';
        $stmt = $conn->prepare("
            INSERT INTO route_popularity (route_id, $field, last_updated)
            VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE
            $field = $field + 1,
            last_updated = NOW()
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

    // Check if route is completed
    $checkCompletedStmt = $conn->prepare("SELECT COUNT(*) as count FROM completed_routes WHERE user_id = ? AND route_id = ?");
    $checkCompletedStmt->bind_param("ii", $userId, $routeId);
    $checkCompletedStmt->execute();
    $completedResult = $checkCompletedStmt->get_result();
    $response['isCompleted'] = $completedResult->fetch_assoc()['count'] > 0;
    $checkCompletedStmt->close();

    // Check if route is favorite
    $checkFavoriteStmt = $conn->prepare("SELECT COUNT(*) as count FROM user_favorites WHERE user_id = ? AND route_id = ?");
    $checkFavoriteStmt->bind_param("ii", $userId, $routeId);
    $checkFavoriteStmt->execute();
    $favoriteResult = $checkFavoriteStmt->get_result();
    $response['isFavorite'] = $favoriteResult->fetch_assoc()['count'] > 0;
    $checkFavoriteStmt->close();

    return $response;
}

function updatePopularityScores($conn) {
    $stmt = $conn->prepare("
        UPDATE route_popularity rp
        JOIN rutas r ON rp.route_id = r.id
        JOIN (
            SELECT route_id, 
                   COUNT(DISTINCT CASE WHEN interaction_type = 'view' THEN user_id END) as unique_views,
                   COUNT(DISTINCT CASE WHEN interaction_type IN ('favorite', 'complete') THEN user_id END) as unique_interactions,
                   SUM(CASE WHEN interaction_type = 'view' THEN view_duration ELSE 0 END) as total_view_time
            FROM user_interactions
            GROUP BY route_id
        ) ui ON rp.route_id = ui.route_id
        SET rp.popularity_score = (
            rp.view_count * 1 + 
            rp.download_count * 2 + 
            rp.favorite_count * 3 + 
            rp.complete_count * 4 +
            ui.unique_views * 0.5 +
            ui.unique_interactions * 1.5 +
            IFNULL(ui.total_view_time, 0) * 0.001
        ) * EXP(-0.05 * DATEDIFF(CURDATE(), r.created_at)) / (DATEDIFF(CURDATE(), r.created_at) + 1)
    ");

    if (!$stmt->execute()) {
        throw new Exception("Error calculating popularity scores: " . $stmt->error);
    }
    $stmt->close();
}
?>