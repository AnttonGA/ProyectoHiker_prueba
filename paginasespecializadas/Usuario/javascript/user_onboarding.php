<?php
/**
 * user_onboarding.php — Returns completion status of each discovery step.
 * Single query, lightweight, called once on page load.
 */
ini_set('display_errors', 0);
require_once __DIR__ . '/../../../includes/security.php';
secure_session_start();
require_once __DIR__ . '/../../inicio_sesion/conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$username = $_SESSION['usuario'];

$stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}
$user_id = $result->fetch_assoc()['id'];
$stmt->close();

// One round-trip for all five server-side checks
$stmt = $conn->prepare("
    SELECT
        (SELECT COUNT(*) FROM rutas             WHERE usuario_id = ?)                        AS route_count,
        (SELECT COUNT(*) FROM user_favorites    WHERE usuario_id = ?)                        AS fav_count,
        (SELECT COUNT(*) FROM user_lists        WHERE usuario_id = ?)                        AS list_count,
        (SELECT COUNT(*) FROM user_interactions WHERE usuario_id = ? AND tipo = 'download')  AS dl_count,
        (SELECT COUNT(*) FROM chat_sessions     WHERE usuario_id = ?)                        AS chat_count
");
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'steps' => [
        'routes'    => (int)$row['route_count'] > 0,
        'favorites' => (int)$row['fav_count']   > 0,
        'lists'     => (int)$row['list_count']  > 0,
        'downloads' => (int)$row['dl_count']    > 0,
        'chat'      => (int)$row['chat_count']  > 0,
        // 'ai' (profile tab) step is tracked client-side via localStorage
    ],
]);
?>
