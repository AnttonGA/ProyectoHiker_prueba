<?php
require_once __DIR__ . '/../../../includes/security.php';
secure_session_start();
require_once '../../inicio_sesion/conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$routeId = isset($_GET['routeId']) ? (int)$_GET['routeId'] : 0;
if (!$routeId) {
    echo json_encode(['success' => false, 'error' => 'Route ID is required']);
    exit;
}

try {
    // Resolver usuario_id
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($userId === 0) {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $_SESSION['usuario']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) throw new Exception('User not found');
        $userId = (int)$row['id'];
    }

    $listStmt = $conn->prepare("
        SELECT ul.id,
               ul.nombre AS list_name,
               CASE WHEN ulr.ruta_id IS NOT NULL THEN 1 ELSE 0 END AS is_route_in_list
        FROM user_lists ul
        LEFT JOIN user_list_routes ulr
               ON ul.id = ulr.lista_id AND ulr.ruta_id = ?
        WHERE ul.usuario_id = ?
    ");
    $listStmt->bind_param("ii", $routeId, $userId);
    $listStmt->execute();
    $lists = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $listStmt->close();

    echo json_encode(['success' => true, 'lists' => $lists]);

} catch (Throwable $e) {
    error_log('get_user_lists.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'No se han podido cargar tus listas.']);
}

$conn->close();
