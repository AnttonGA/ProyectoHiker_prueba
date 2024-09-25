<?php
session_start();
require_once __DIR__ . '/../../inicio_sesion/conexion.php';
require_once __DIR__ . '/../../inicio_sesion/config.php';

function debug_log($message) {
    $logFile = __DIR__ . '/error.log';
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($logFile, "$timestamp $message\n", FILE_APPEND);
}

debug_log("Script started");

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Usuario no autenticado');
    }

    $username = $_SESSION['usuario'];
    debug_log("User authenticated: " . $username);

    // Obtener el ID del usuario
    $query = "SELECT id FROM usuarios WHERE usuario = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error preparing user query: ' . $conn->error);
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Usuario no encontrado en la base de datos');
    }
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $stmt->close();
    
    debug_log("User ID: " . $user_id);

    // Consulta para verificar si el usuario tiene rutas
    $check_query = "SELECT COUNT(*) as route_count FROM rutas WHERE usuario = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("s", $username);  // Nota: Usamos el nombre de usuario, no el ID
    $check_stmt->execute();
    $check_result = $check_stmt->get_result()->fetch_assoc();
    debug_log("Number of routes found for user: " . $check_result['route_count']);
    $check_stmt->close();

    // Cargar las rutas del usuario
    $query = "SELECT r.*, 
                     GROUP_CONCAT(DISTINCT ri.imagen_path) as imagenes,
                     rp.view_count, rp.download_count, rp.favorite_count, rp.complete_count, rp.popularity_score
              FROM rutas r
              LEFT JOIN ruta_imagenes ri ON r.id = ri.ruta_id
              LEFT JOIN route_popularity rp ON r.id = rp.route_id
              WHERE r.usuario = ?
              GROUP BY r.id
              ORDER BY rp.popularity_score DESC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error preparing routes query: ' . $conn->error);
    }

    $stmt->bind_param("s", $username);  // Nota: Usamos el nombre de usuario, no el ID
    $stmt->execute();
    $result = $stmt->get_result();

    $routes = [];
    while ($row = $result->fetch_assoc()) {
        $row['imagenes'] = $row['imagenes'] ? explode(',', $row['imagenes']) : [];
        $row['imagenes'] = array_map(function($path) {
            return rtrim(BASE_URL, '/') . '/paginasespecializadas/skitouring/' . ltrim($path, '/');
        }, $row['imagenes']);
        $routes[] = $row;
    }

    debug_log("Number of routes fetched: " . count($routes));

    if (empty($routes)) {
        echo json_encode([
            'success' => true, 
            'message' => '¡Aún no has subido ninguna ruta! ¿Qué tal si comienzas tu primera aventura?'
        ]);
    } else {
        echo json_encode(['success' => true, 'routes' => $routes]);
    }

} catch (Exception $e) {
    debug_log("Exception caught: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    debug_log("Script ended");
}
?>