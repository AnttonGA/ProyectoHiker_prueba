<?php
// usuario.php
session_start();
require_once '../../inicio_sesion/conexion.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) && $_REQUEST['action'] !== 'check_session') {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

// Manejar diferentes acciones basadas en la solicitud
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'check_session':
        checkSession();
        break;
    case 'get_user_routes':
        getUserRoutes($conn, $user_id);
        break;
    case 'get_favorite_routes':
        getFavoriteRoutes($conn, $user_id);
        break;
    case 'get_completed_routes':
        getCompletedRoutes($conn, $user_id);
        break;
    case 'get_user_lists':
        getUserLists($conn, $user_id);
        break;
    case 'create_list':
        createList($conn, $user_id);
        break;
    case 'delete_list':
        deleteList($conn, $user_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function checkSession() {
    if (isset($_SESSION['usuario'])) {
        echo json_encode([
            'success' => true,
            'logged_in' => true,
            'usuario' => $_SESSION['usuario']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'logged_in' => false
        ]);
    }
}

function getUserRoutes($conn, $user_id) {
    $query = "SELECT r.*, ri.imagen_path 
              FROM rutas r 
              LEFT JOIN (
                  SELECT ruta_id, MIN(imagen_path) as imagen_path
                  FROM ruta_imagenes
                  GROUP BY ruta_id
              ) ri ON r.id = ri.ruta_id 
              WHERE r.usuario = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $routes = [];
    while ($row = $result->fetch_assoc()) {
        $routes[] = $row;
    }

    echo json_encode(['success' => true, 'routes' => $routes]);
}

function getFavoriteRoutes($conn, $user_id) {
    $query = "SELECT r.*, ri.imagen_path 
              FROM rutas r 
              LEFT JOIN (
                  SELECT ruta_id, MIN(imagen_path) as imagen_path
                  FROM ruta_imagenes
                  GROUP BY ruta_id
              ) ri ON r.id = ri.ruta_id 
              INNER JOIN user_favorites uf ON r.id = uf.route_id 
              WHERE uf.user_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $routes = [];
    while ($row = $result->fetch_assoc()) {
        $routes[] = $row;
    }

    echo json_encode(['success' => true, 'routes' => $routes]);
}

function getCompletedRoutes($conn, $user_id) {
    $query = "SELECT r.*, ri.imagen_path 
              FROM rutas r 
              LEFT JOIN (
                  SELECT ruta_id, MIN(imagen_path) as imagen_path
                  FROM ruta_imagenes
                  GROUP BY ruta_id
              ) ri ON r.id = ri.ruta_id 
              INNER JOIN completed_routes cr ON r.id = cr.route_id 
              WHERE cr.user_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $routes = [];
    while ($row = $result->fetch_assoc()) {
        $routes[] = $row;
    }

    echo json_encode(['success' => true, 'routes' => $routes]);
}

function getUserLists($conn, $user_id) {
    $query = "SELECT ul.*, COUNT(lr.route_id) as route_count 
              FROM user_lists ul
              LEFT JOIN list_routes lr ON ul.id = lr.list_id
              WHERE ul.user_id = ?
              GROUP BY ul.id";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $lists = [];
    while ($row = $result->fetch_assoc()) {
        $lists[] = $row;
    }

    echo json_encode(['success' => true, 'lists' => $lists]);
}

function createList($conn, $user_id) {
    if (!isset($_POST['list_name']) || empty(trim($_POST['list_name']))) {
        echo json_encode(['success' => false, 'message' => 'Nombre de lista no válido']);
        return;
    }

    $list_name = trim($_POST['list_name']);
    $query = "INSERT INTO user_lists (user_id, list_name) VALUES (?, ?)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $user_id, $list_name);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Lista creada con éxito', 'list_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear la lista: ' . $stmt->error]);
    }
}

function deleteList($conn, $user_id) {
    if (!isset($_POST['list_id']) || !is_numeric($_POST['list_id'])) {
        echo json_encode(['success' => false, 'message' => 'ID de lista no válido']);
        return;
    }

    $list_id = intval($_POST['list_id']);
    
    // Primero, eliminar las entradas relacionadas en list_routes
    $delete_routes_query = "DELETE FROM list_routes WHERE list_id = ? AND list_id IN (SELECT id FROM user_lists WHERE user_id = ?)";
    $stmt = $conn->prepare($delete_routes_query);
    $stmt->bind_param("ii", $list_id, $user_id);
    $stmt->execute();

    // Luego, eliminar la lista
    $delete_list_query = "DELETE FROM user_lists WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($delete_list_query);
    $stmt->bind_param("ii", $list_id, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Lista eliminada con éxito']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se encontró la lista o no tienes permiso para eliminarla']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la lista: ' . $stmt->error]);
    }
}

// No cerramos la conexión aquí, ya que se maneja en conexion.php
?>