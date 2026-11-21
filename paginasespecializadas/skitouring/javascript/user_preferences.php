<?php
require_once __DIR__ . '/../../../includes/security.php';
secure_session_start();
require_once __DIR__ . '/../../inicio_sesion/conexion.php';
require_once __DIR__ . '/../../inicio_sesion/config.php';

function debug_log($message) {
    $logFile = __DIR__ . '/preferences_error.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . " $message\n", FILE_APPEND);
}

debug_log("Update preferences script started");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    if (!isset($_SESSION['usuario'])) {
        echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
        exit;
    }

    // CSRF: sendBeacon no admite cabeceras, así que el token viaja en el cuerpo JSON.
    $body = json_decode(file_get_contents('php://input'), true);
    $tokenCuerpo = (is_array($body) && isset($body['csrf_token']) && is_string($body['csrf_token']))
        ? $body['csrf_token'] : null;
    if (!csrf_is_valid($tokenCuerpo)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'La sesión ha caducado. Recarga la página.']);
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
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            exit;
        }
        $userId = (int)$row['id'];
    }
    debug_log("User ID: $userId");

    // Obtener interacciones del usuario con datos de la ruta
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

    if (count($rows) < 5) {
        echo json_encode(['success' => true, 'message' => 'Insufficient interactions to update preferences']);
        exit;
    }

    $diffCounts     = ['beginner' => 0, 'intermediate' => 0, 'advanced' => 0];
    $activityCounts = [];
    $locationCounts = [];
    $distances      = [];
    $durations      = [];
    $totalInteractions = count($rows);

    foreach ($rows as $row) {
        $diff = strtolower($row['dificultad'] ?? '');
        if (isset($diffCounts[$diff])) $diffCounts[$diff]++;

        if (!empty($row['tipo_ruta'])) {
            $activityCounts[$row['tipo_ruta']] = ($activityCounts[$row['tipo_ruta']] ?? 0) + 1;
        }
        if (!empty($row['ubicacion'])) {
            $locationCounts[$row['ubicacion']] = ($locationCounts[$row['ubicacion']] ?? 0) + 1;
        }
        if (!empty($row['distancia_km']))   $distances[] = (float)$row['distancia_km'];
        if (!empty($row['duracion_horas'])) $durations[] = (float)$row['duracion_horas'];
    }

    // Nivel de experiencia
    $experienceLevel = 'beginner';
    if ($diffCounts['advanced'] > $diffCounts['beginner'] && $diffCounts['advanced'] > $diffCounts['intermediate']) {
        $experienceLevel = 'advanced';
    } elseif ($diffCounts['intermediate'] > $diffCounts['beginner'] || $totalInteractions > 50) {
        $experienceLevel = 'intermediate';
    }

    arsort($activityCounts);
    arsort($locationCounts);
    $prefActivities = json_encode(array_keys(array_slice($activityCounts, 0, 3, true)));
    $prefLocations  = json_encode(array_keys(array_slice($locationCounts, 0, 3, true)));
    $avgDist = $distances ? round(array_sum($distances) / count($distances), 2) : 0;
    $avgDur  = $durations  ? round(array_sum($durations)  / count($durations),  2) : 0;

    // Guardar en preferencias_inferidas el total de interacciones
    $inferidas = json_encode(['total_interactions' => $totalInteractions]);

    debug_log("Preferences: level=$experienceLevel, interactions=$totalInteractions");

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

    debug_log("Preferences updated successfully for user ID: $userId");

    echo json_encode([
        'success' => true,
        'message' => 'Preferencias actualizadas con éxito',
        'preferences' => [
            'experience_level'       => $experienceLevel,
            'preferred_activities'   => $prefActivities,
            'preferred_locations'    => $prefLocations,
            'avg_preferred_distance' => $avgDist,
            'avg_preferred_duration' => $avgDur,
            'total_interactions'     => $totalInteractions,
        ]
    ]);

} catch (Throwable $e) {
    debug_log("Exception: " . $e->getMessage());
    error_log('user_preferences.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'No se han podido actualizar las preferencias.']);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
    if (isset($conn) && $conn instanceof mysqli)       $conn->close();
    debug_log("Script ended");
}
