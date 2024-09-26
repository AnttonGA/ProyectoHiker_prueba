<?php
session_start();
require_once '../../inicio_sesion/conexion.php';

header('Content-Type: application/json');

function debug_log($message) {
    $logFile = __DIR__ . '/user_lists_error.log';
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($logFile, "$timestamp $message\n", FILE_APPEND);
}

debug_log("User lists handler script started");

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['error' => 'Usuario no autenticado']);
    debug_log("Error: Usuario no autenticado");
    exit;
}

$username = $_SESSION['usuario'];

// Obtener el ID del usuario
$query = "SELECT id FROM usuarios WHERE usuario = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Usuario no encontrado']);
    debug_log("Error: Usuario no encontrado - " . $username);
    exit;
}
$user = $result->fetch_assoc();
$user_id = $user['id'];
$stmt->close();

debug_log("User ID: " . $user_id);

function getUserLists($conn, $user_id) {
    $query = "SELECT id, list_name FROM user_lists WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getListRoutes($conn, $list_id, $user_id) {
    $query = "SELECT r.id, r.title, r.description 
              FROM rutas r 
              JOIN list_routes lr ON r.id = lr.route_id 
              JOIN user_lists ul ON lr.list_id = ul.id
              WHERE lr.list_id = ? AND ul.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $list_id, $user_id);
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
        echo json_encode(getListRoutes($conn, $list_id, $user_id));
        break;
    
    case 'createList':
        $list_name = $_POST['list_name'] ?? '';
        if (!empty($list_name)) {
            $query = "INSERT INTO user_lists (user_id, list_name) VALUES (?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("is", $user_id, $list_name);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $conn->insert_id]);
            } else {
                echo json_encode(['error' => 'Error al crear la lista: ' . $conn->error]);
            }
        } else {
            echo json_encode(['error' => 'Nombre de lista vacío']);
        }
        break;
    
    case 'removeRouteFromList':
        $list_id = $_POST['list_id'] ?? 0;
        $route_id = $_POST['route_id'] ?? 0;
        if ($list_id && $route_id) {
            $query = "DELETE lr FROM list_routes lr 
                      JOIN user_lists ul ON lr.list_id = ul.id 
                      WHERE lr.list_id = ? AND lr.route_id = ? AND ul.user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iii", $list_id, $route_id, $user_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Error al quitar la ruta de la lista: ' . $conn->error]);
            }
        } else {
            echo json_encode(['error' => 'Parámetros inválidos']);
        }
        break;
    
    case 'deleteList':
        $list_id = $_POST['list_id'] ?? 0;
        if ($list_id) {
            // Primero, eliminar todas las rutas asociadas a la lista
            $query = "DELETE lr FROM list_routes lr 
                      JOIN user_lists ul ON lr.list_id = ul.id 
                      WHERE lr.list_id = ? AND ul.user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $list_id, $user_id);
            $stmt->execute();
            
            // Luego, eliminar la lista
            $query = "DELETE FROM user_lists WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $list_id, $user_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['error' => 'No se pudo eliminar la lista o no se encontró']);
                }
            } else {
                echo json_encode(['error' => 'Error al eliminar la lista: ' . $conn->error]);
            }
        } else {
            echo json_encode(['error' => 'ID de lista inválido']);
        }
        break;
    
    default:
        echo json_encode(['error' => 'Acción no reconocida']);
}

$conn->close();
debug_log("User lists handler script ended");
?>