<?php
// get_user_routes.php
session_start();
require_once 'conexion.php';

$user_id = $_SESSION['user_id'];

$query = "SELECT r.*, ri.imagen_path 
          FROM rutas r 
          LEFT JOIN ruta_imagenes ri ON r.id = ri.ruta_id 
          WHERE r.usuario = ?
          GROUP BY r.id";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$routes = [];
while ($row = $result->fetch_assoc()) {
    $routes[] = $row;
}

echo json_encode($routes);

// get_favorite_routes.php
session_start();
require_once 'conexion.php';

$user_id = $_SESSION['user_id'];

$query = "SELECT r.*, ri.imagen_path 
          FROM rutas r 
          LEFT JOIN ruta_imagenes ri ON r.id = ri.ruta_id 
          INNER JOIN user_favorites uf ON r.id = uf.route_id 
          WHERE uf.user_id = ?
          GROUP BY r.id";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$routes = [];
while ($row = $result->fetch_assoc()) {
    $routes[] = $row;
}

echo json_encode($routes);

// get_completed_routes.php
session_start();
require_once 'conexion.php';

$user_id = $_SESSION['user_id'];

$query = "SELECT r.*, ri.imagen_path 
          FROM rutas r 
          LEFT JOIN ruta_imagenes ri ON r.id = ri.ruta_id 
          INNER JOIN completed_routes cr ON r.id = cr.route_id 
          WHERE cr.user_id = ?
          GROUP BY r.id";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$routes = [];
while ($row = $result->fetch_assoc()) {
    $routes[] = $row;
}

echo json_encode($routes);

// get_user_lists.php
session_start();
require_once 'conexion.php';

$user_id = $_SESSION['user_id'];

$query = "SELECT * FROM user_lists WHERE user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$lists = [];
while ($row = $result->fetch_assoc()) {
    $lists[] = $row;
}

echo json_encode($lists);

// create_list.php
session_start();
require_once 'conexion.php';

$user_id = $_SESSION['user_id'];
$list_name = $_POST['list_name'];

$query = "INSERT INTO user_lists (user_id, list_name) VALUES (?, ?)";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $user_id, $list_name);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Lista creada con éxito']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al crear la lista']);
}

// delete_list.php
session_start();
require_once 'conexion.php';

$user_id = $_SESSION['user_id'];
$list_id = $_POST['list_id'];

$query = "DELETE FROM user_lists WHERE id = ? AND user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $list_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Lista eliminada con éxito']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la lista']);
}