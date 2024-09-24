<?php
session_start();
require_once '../../inicio_sesion/conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$routeId = $_GET['routeId'] ?? null;

if (!$routeId) {
    echo json_encode(['success' => false, 'error' => 'Route ID is required']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $_SESSION['usuario']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('User not found');
    }

    $userId = $result->fetch_assoc()['id'];
    $stmt->close();

    $listStmt = $conn->prepare("
        SELECT ul.id, ul.list_name, 
               CASE WHEN lr.route_id IS NOT NULL THEN 1 ELSE 0 END AS is_route_in_list
        FROM user_lists ul
        LEFT JOIN list_routes lr ON ul.id = lr.list_id AND lr.route_id = ?
        WHERE ul.user_id = ?
    ");
    $listStmt->bind_param("ii", $routeId, $userId);
    $listStmt->execute();
    $listResult = $listStmt->get_result();

    $lists = [];
    while ($row = $listResult->fetch_assoc()) {
        $lists[] = $row;
    }

    echo json_encode(['success' => true, 'lists' => $lists]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>