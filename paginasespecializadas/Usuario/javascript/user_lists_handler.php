<?php
ini_set('display_errors', 0);
session_start();
require_once '../../inicio_sesion/conexion.php';

header('Content-Type: application/json');

function debug_log($message) {
    $logFile = __DIR__ . '/user_lists_error.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . " $message\n", FILE_APPEND);
}

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit;
}

$username = $_SESSION['usuario'];

// Obtener ID del usuario
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Usuario no encontrado']);
    exit;
}
$user_id = $result->fetch_assoc()['id'];
$stmt->close();

function getUserLists($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id, nombre AS list_name FROM user_lists WHERE usuario_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getListRoutes($conn, $list_id, $user_id) {
    $stmt = $conn->prepare(
        "SELECT r.id, r.titulo, r.descripcion
         FROM rutas r
         JOIN user_list_routes ulr ON r.id = ulr.ruta_id
         JOIN user_lists ul        ON ulr.lista_id = ul.id
         WHERE ulr.lista_id = ? AND ul.usuario_id = ?"
    );
    $stmt->bind_param("ii", $list_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getLists':
        echo json_encode(getUserLists($conn, $user_id));
        break;

    case 'getListRoutes':
        $list_id = (int)($_GET['list_id'] ?? 0);
        echo json_encode(getListRoutes($conn, $list_id, $user_id));
        break;

    case 'createList':
        $nombre = trim($_POST['list_name'] ?? '');
        if (!empty($nombre)) {
            $stmt = $conn->prepare("INSERT INTO user_lists (usuario_id, nombre) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $nombre);
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
        $list_id  = (int)($_POST['list_id']  ?? 0);
        $route_id = (int)($_POST['route_id'] ?? 0);
        if ($list_id && $route_id) {
            $stmt = $conn->prepare(
                "DELETE ulr FROM user_list_routes ulr
                 JOIN user_lists ul ON ulr.lista_id = ul.id
                 WHERE ulr.lista_id = ? AND ulr.ruta_id = ? AND ul.usuario_id = ?"
            );
            $stmt->bind_param("iii", $list_id, $route_id, $user_id);
            echo json_encode($stmt->execute() ? ['success' => true] : ['error' => $conn->error]);
        } else {
            echo json_encode(['error' => 'Parámetros inválidos']);
        }
        break;

    case 'deleteList':
        $list_id = (int)($_POST['list_id'] ?? 0);
        if ($list_id) {
            // Borrar rutas de la lista
            $stmt = $conn->prepare(
                "DELETE ulr FROM user_list_routes ulr
                 JOIN user_lists ul ON ulr.lista_id = ul.id
                 WHERE ulr.lista_id = ? AND ul.usuario_id = ?"
            );
            $stmt->bind_param("ii", $list_id, $user_id);
            $stmt->execute();
            // Borrar la lista
            $stmt = $conn->prepare("DELETE FROM user_lists WHERE id = ? AND usuario_id = ?");
            $stmt->bind_param("ii", $list_id, $user_id);
            $stmt->execute();
            echo json_encode($stmt->affected_rows > 0 ? ['success' => true] : ['error' => 'Lista no encontrada']);
        } else {
            echo json_encode(['error' => 'ID de lista inválido']);
        }
        break;

    default:
        echo json_encode(['error' => 'Acción no reconocida']);
}

$conn->close();
?>
