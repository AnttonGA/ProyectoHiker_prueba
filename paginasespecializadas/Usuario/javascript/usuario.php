<?php
ob_start();
session_start();

// Función para registrar mensajes de depuración
function debug_log($message) {
    if (is_writable(__DIR__)) {
        file_put_contents(__DIR__ . '/debug.log', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
    } else {
        error_log("No se puede escribir en el archivo debug.log");
    }
}

debug_log("Script started");
debug_log("SERVER: " . print_r($_SERVER, true));

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Manejo de errores personalizados
function handleError($errno, $errstr, $errfile, $errline) {
    $error = array(
        'error' => 'PHP Error',
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    );
    debug_log("Error occurred: " . json_encode($error));
    echo json_encode($error);
    exit;
}

set_error_handler('handleError');

set_exception_handler(function($e) {
    $error = [
        'error' => 'Uncaught Exception',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    debug_log("Uncaught exception: " . json_encode($error));
    echo json_encode($error);
    exit;
});

try {
    debug_log("About to require connection files");
    require_once '../../inicio_sesion/conexion.php';
    require_once '../../inicio_sesion/config.php';
    debug_log("Connection files included");

    if (!$conn) {
        throw new Exception('Database connection error');
    }
    debug_log("Database connection successful");

    $action = $_REQUEST['action'] ?? '';
    debug_log("Action requested: " . $action);

    switch ($action) {
        case 'check_session':
            checkSession();
            break;
        case 'get_user_routes':
            getUserRoutes($conn);
            break;
        case 'get_favorite_routes':
            getFavoriteRoutes($conn);
            break;
        case 'get_completed_routes':
            getCompletedRoutes($conn);
            break;
        case 'get_user_lists':
            getUserLists($conn);
            break;
        case 'create_list':
            createList($conn);
            break;
        case 'delete_list':
            deleteList($conn);
            break;
        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    http_response_code(500);
    $error = ['error' => $e->getMessage()];
    debug_log("Exception caught: " . json_encode($error));
    echo json_encode($error);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    debug_log("Script ended");
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

function getUserRoutes($conn) {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('Usuario no autenticado');
    }

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
        $row['imagen_path'] = getImageUrl(BASE_URL, $row['imagen_path']);
        $routes[] = $row;
    }

    echo json_encode(['success' => true, 'routes' => $routes]);
}

function getFavoriteRoutes($conn) {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('Usuario no autenticado');
    }

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
        $row['imagen_path'] = getImageUrl(BASE_URL, $row['imagen_path']);
        $routes[] = $row;
    }

    echo json_encode(['success' => true, 'routes' => $routes]);
}

function getCompletedRoutes($conn) {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('Usuario no autenticado');
    }

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
        $row['imagen_path'] = getImageUrl(BASE_URL, $row['imagen_path']);
        $routes[] = $row;
    }

    echo json_encode(['success' => true, 'routes' => $routes]);
}

function getUserLists($conn) {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('Usuario no autenticado');
    }

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

function createList($conn) {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('Usuario no autenticado');
    }

    if (!isset($_POST['list_name']) || empty(trim($_POST['list_name']))) {
        throw new Exception('Nombre de lista no válido');
    }

    $list_name = trim($_POST['list_name']);
    $query = "INSERT INTO user_lists (user_id, list_name) VALUES (?, ?)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $user_id, $list_name);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Lista creada con éxito', 'list_id' => $stmt->insert_id]);
    } else {
        throw new Exception('Error al crear la lista: ' . $stmt->error);
    }
}

function deleteList($conn) {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('Usuario no autenticado');
    }

    if (!isset($_POST['list_id']) || !is_numeric($_POST['list_id'])) {
        throw new Exception('ID de lista no válido');
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
            throw new Exception('No se encontró la lista o no tienes permiso para eliminarla');
        }
    } else {
        throw new Exception('Error al eliminar la lista: ' . $stmt->error);
    }
}

function getImageUrl($baseUrl, $path) {
    return rtrim($baseUrl, '/') . '/paginasespecializadas/skitouring/' . ltrim($path, '/');
}
?>