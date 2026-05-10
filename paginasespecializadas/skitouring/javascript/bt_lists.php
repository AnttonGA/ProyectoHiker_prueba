<?php
session_start();
require_once '../../inicio_sesion/conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$action  = $data['action']  ?? '';
$routeId = $data['routeId'] ?? null;

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

    if ($action === 'create') {
        $listName = $data['listName'] ?? '';
        if (empty($listName)) throw new Exception('List name is required');

        $conn->begin_transaction();

        $createListStmt = $conn->prepare(
            "INSERT INTO user_lists (usuario_id, nombre) VALUES (?, ?)"
        );
        $createListStmt->bind_param("is", $userId, $listName);
        $createListStmt->execute();
        $listId = $conn->insert_id;
        $createListStmt->close();

        $addToListStmt = $conn->prepare(
            "INSERT INTO user_list_routes (lista_id, ruta_id) VALUES (?, ?)"
        );
        $addToListStmt->bind_param("ii", $listId, $routeId);
        $addToListStmt->execute();
        $addToListStmt->close();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'New list created and route added',
            'listId'  => $listId
        ]);

    } elseif ($action === 'add') {
        $listId = $data['listId'] ?? null;
        if (!$listId) throw new Exception('List ID is required');

        $checkStmt = $conn->prepare(
            "SELECT COUNT(*) FROM user_list_routes WHERE lista_id = ? AND ruta_id = ?"
        );
        $checkStmt->bind_param("ii", $listId, $routeId);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_row()[0] > 0;
        $checkStmt->close();

        if ($exists) throw new Exception('Route is already in this list');

        $addToListStmt = $conn->prepare(
            "INSERT INTO user_list_routes (lista_id, ruta_id) VALUES (?, ?)"
        );
        $addToListStmt->bind_param("ii", $listId, $routeId);
        $addToListStmt->execute();
        $addToListStmt->close();

        echo json_encode(['success' => true, 'message' => 'Route added to list']);

    } elseif ($action === 'remove') {
        $listId = $data['listId'] ?? null;
        if (!$listId) throw new Exception('List ID is required');

        $removeStmt = $conn->prepare(
            "DELETE FROM user_list_routes WHERE lista_id = ? AND ruta_id = ?"
        );
        $removeStmt->bind_param("ii", $listId, $routeId);
        $removeStmt->execute();
        $removeStmt->close();

        echo json_encode(['success' => true, 'message' => 'Route removed from list']);

    } else {
        throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
