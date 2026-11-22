<?php
/**
 * route_feedback.php — Valoración de una ruta completada (Fase 1)
 *
 *   GET  ?ruta_id=N                         → la valoración del usuario para esa ruta (o null)
 *   POST {ruta_id, percepcion, estrellas}   → guarda/actualiza la valoración (requiere CSRF)
 *
 * percepcion: 'facil' | 'adecuada' | 'dura'  — alimenta la personalización (nivel de esfuerzo cómodo)
 * estrellas:  1–5 o null                     — alimenta route_popularity.rating_sum / rating_count
 *
 * Solo se puede valorar una ruta que el usuario ha marcado como completada.
 */
declare(strict_types=1);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../includes/security.php';
secure_session_start();
require_once __DIR__ . '/../../inicio_sesion/conexion.php';

header('Content-Type: application/json; charset=utf-8');

function feedback_reply(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
if ($userId <= 0) {
    feedback_reply(['success' => false, 'error' => 'Inicia sesión para valorar rutas.'], 401);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $rutaId = filter_input(INPUT_GET, 'ruta_id', FILTER_VALIDATE_INT);
        if (!$rutaId) {
            feedback_reply(['success' => false, 'error' => 'Ruta no válida.'], 400);
        }
        $stmt = $conn->prepare(
            "SELECT percepcion, estrellas FROM route_feedback WHERE usuario_id = ? AND ruta_id = ?"
        );
        $stmt->bind_param('ii', $userId, $rutaId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        feedback_reply([
            'success'  => true,
            'feedback' => $row ? [
                'percepcion' => $row['percepcion'],
                'estrellas'  => $row['estrellas'] !== null ? (int) $row['estrellas'] : null,
            ] : null,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        feedback_reply(['success' => false, 'error' => 'Método no permitido.'], 405);
    }

    $data = json_decode((string) file_get_contents('php://input'), true);
    $tokenCuerpo = is_array($data) && is_string($data['csrf_token'] ?? null) ? $data['csrf_token'] : null;
    if (!csrf_is_valid($tokenCuerpo)) {
        feedback_reply(['success' => false, 'error' => 'La sesión ha caducado. Recarga la página.'], 403);
    }

    $rutaId     = is_array($data) ? filter_var($data['ruta_id'] ?? null, FILTER_VALIDATE_INT) : false;
    $percepcion = is_array($data) ? ($data['percepcion'] ?? '') : '';
    $estrellas  = is_array($data) && isset($data['estrellas']) && $data['estrellas'] !== null
        ? filter_var($data['estrellas'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]])
        : null;

    if (!$rutaId || !in_array($percepcion, ['facil', 'adecuada', 'dura'], true) || $estrellas === false) {
        feedback_reply(['success' => false, 'error' => 'Valoración no válida.'], 400);
    }

    // Solo rutas completadas por el usuario
    $stmt = $conn->prepare("SELECT 1 FROM user_completed WHERE usuario_id = ? AND ruta_id = ? LIMIT 1");
    $stmt->bind_param('ii', $userId, $rutaId);
    $stmt->execute();
    $stmt->store_result();
    $completada = $stmt->num_rows > 0;
    $stmt->close();
    if (!$completada) {
        feedback_reply(['success' => false, 'error' => 'Marca la ruta como completada antes de valorarla.'], 409);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "INSERT INTO route_feedback (usuario_id, ruta_id, percepcion, estrellas)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE percepcion = VALUES(percepcion), estrellas = VALUES(estrellas)"
        );
        $stmt->bind_param('iisi', $userId, $rutaId, $percepcion, $estrellas);
        $stmt->execute();
        $stmt->close();

        // Recalcular la nota de la ruta desde las valoraciones (sin acumular errores)
        $stmt = $conn->prepare(
            "INSERT INTO route_popularity (ruta_id, rating_sum, rating_count)
             SELECT ?, COALESCE(SUM(estrellas), 0), COUNT(estrellas) FROM route_feedback WHERE ruta_id = ?
             ON DUPLICATE KEY UPDATE rating_sum = VALUES(rating_sum), rating_count = VALUES(rating_count)"
        );
        $stmt->bind_param('ii', $rutaId, $rutaId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    feedback_reply(['success' => true, 'message' => 'Gracias. Usaremos tu valoración para recomendarte mejor.']);

} catch (Throwable $e) {
    error_log('route_feedback.php: ' . $e->getMessage());
    feedback_reply(['success' => false, 'error' => 'No se pudo guardar la valoración. Inténtalo de nuevo.'], 500);
}
