<?php
/**
 * user_activities.php — Actividades que el usuario DECLARA practicar (Fase 1)
 *
 *   GET                     → {success, declared: [...] | null}
 *   POST {actividades: []}  → guarda la declaración (requiere CSRF)
 *
 * Se guarda en user_preferences.preferencias_declaradas (JSON, clave "actividades")
 * y también en actividades_preferidas, que es lo que usa el asistente al recomendar.
 * Lo declarado tiene prioridad sobre lo que la app infiere del comportamiento
 * (ver UserPreferenceService::getUserPreferences).
 */
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/security.php';
secure_session_start();
require_once __DIR__ . '/../../inicio_sesion/conexion.php';

header('Content-Type: application/json; charset=utf-8');

const ACTIVIDADES_VALIDAS = [
    'hiking', 'ski_touring', 'snowshoeing', 'climbing', 'via_ferrata', 'cycle_touring', 'btt', 'kayaking',
];

function activities_reply(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
if ($userId <= 0) {
    activities_reply(['success' => false, 'error' => 'Inicia sesión.'], 401);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->prepare("SELECT preferencias_declaradas FROM user_preferences WHERE usuario_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $decl = $row ? json_decode((string) ($row['preferencias_declaradas'] ?? ''), true) : null;
        $acts = is_array($decl) && isset($decl['actividades']) && is_array($decl['actividades'])
            ? array_values(array_intersect($decl['actividades'], ACTIVIDADES_VALIDAS))
            : null;

        activities_reply(['success' => true, 'declared' => $acts]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        activities_reply(['success' => false, 'error' => 'Método no permitido.'], 405);
    }

    $data = json_decode((string) file_get_contents('php://input'), true);
    $tokenCuerpo = is_array($data) && is_string($data['csrf_token'] ?? null) ? $data['csrf_token'] : null;
    if (!csrf_is_valid($tokenCuerpo)) {
        activities_reply(['success' => false, 'error' => 'La sesión ha caducado. Recarga la página.'], 403);
    }

    $acts = is_array($data['actividades'] ?? null) ? $data['actividades'] : null;
    if ($acts === null) {
        activities_reply(['success' => false, 'error' => 'Datos no válidos.'], 400);
    }
    // Solo valores conocidos, sin repetir, en el orden de la lista
    $acts = array_values(array_intersect(ACTIVIDADES_VALIDAS, array_filter($acts, 'is_string')));
    if (!$acts) {
        activities_reply(['success' => false, 'error' => 'Elige al menos una actividad.'], 400);
    }
    $json = json_encode($acts);

    $stmt = $conn->prepare(
        "INSERT INTO user_preferences (usuario_id, actividades_preferidas, preferencias_declaradas)
         VALUES (?, ?, JSON_OBJECT('actividades', CAST(? AS JSON), 'actualizado', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')))
         ON DUPLICATE KEY UPDATE
           actividades_preferidas  = VALUES(actividades_preferidas),
           preferencias_declaradas = JSON_SET(COALESCE(preferencias_declaradas, JSON_OBJECT()),
                                              '$.actividades', CAST(? AS JSON),
                                              '$.actualizado', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'))"
    );
    $stmt->bind_param('isss', $userId, $json, $json, $json);
    $stmt->execute();
    $stmt->close();

    activities_reply(['success' => true, 'declared' => $acts, 'message' => 'Actividades guardadas.']);

} catch (Throwable $e) {
    error_log('user_activities.php: ' . $e->getMessage());
    activities_reply(['success' => false, 'error' => 'No se pudieron guardar tus actividades.'], 500);
}
