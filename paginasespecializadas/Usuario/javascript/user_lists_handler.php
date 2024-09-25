<?php
session_start();
require_once '../../inicio_sesion/conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit;
}

$user_id = $_SESSION['id'];

function getUserLists($conn, $user_id) {
    $query = "SELECT id, list_name FROM user_lists WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getListRoutes($conn, $list_id) {
    $query = "SELECT r.id, r.title, r.description 
              FROM rutas r 
              JOIN list_routes lr ON r.id = lr.route_id 
              WHERE lr.list_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $list_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getLists':
        echo json_encode(getUserLists($conn, $user_id));
        break;
    
    case 'getListRoutes':
        $list_id = $_GET['list_id'] ?? 0;
        echo json_encode(getListRoutes($conn, $list_id));
        break;
    
    case 'createList':
        $list_name = $_POST['list_name'] ?? '';
        if (!empty($list_name)) {
            $query = "INSERT INTO user_lists (user_id, list_name) VALUES (?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("is", $user_id, $list_name);
            $stmt->execute();
            echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['error' => 'Nombre de lista vacío']);
        }
        break;
    
    case 'removeRouteFromList':
        $list_id = $_POST['list_id'] ?? 0;
        $route_id = $_POST['route_id'] ?? 0;
        if ($list_id && $route_id) {
            $query = "DELETE FROM list_routes WHERE list_id = ? AND route_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $list_id, $route_id);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Parámetros inválidos']);
        }
        break;
    
    default:
        echo json_encode(['error' => 'Acción no reconocida']);
}

$conn->close();
?>