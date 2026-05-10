<?php
ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../../inicio_sesion/conexion.php';
require_once __DIR__ . '/../../inicio_sesion/config.php';

function debug_log($message) {
    $logFile = sys_get_temp_dir() . '/hiker_user_favs.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . " $message\n", FILE_APPEND);
}

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario'])) {
        throw new Exception('Usuario no autenticado');
    }

    $username = $_SESSION['usuario'];

    // Pagination params
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(50, max(1, (int)($_GET['limit'] ?? 12)));
    $offset = ($page - 1) * $limit;

    // Get user ID
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) throw new Exception('Usuario no encontrado en la base de datos');
    $user_id = $result->fetch_assoc()['id'];
    $stmt->close();

    // Count total favourites
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_favorites WHERE usuario_id = ?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    if ($total === 0) {
        echo json_encode(['success' => true, 'message' => '¡Aún no tienes rutas favoritas!', 'total' => 0]);
        exit;
    }

    // Paginated favourites
    $stmt = $conn->prepare(
        "SELECT r.*,
                GROUP_CONCAT(DISTINCT ri.imagen_path) AS imagenes,
                rp.view_count, rp.download_count, rp.favorite_count,
                rp.complete_count, rp.popularity_score
         FROM rutas r
         INNER JOIN user_favorites uf  ON r.id = uf.ruta_id
         LEFT JOIN ruta_imagenes ri    ON r.id = ri.ruta_id
         LEFT JOIN route_popularity rp ON r.id = rp.ruta_id
         WHERE uf.usuario_id = ?
         GROUP BY r.id
         ORDER BY rp.popularity_score DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->bind_param("iii", $user_id, $limit, $offset);
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
    $stmt->close();

    echo json_encode(['success' => true, 'routes' => $routes, 'total' => (int)$total, 'page' => $page, 'limit' => $limit]);

} catch (Exception $e) {
    debug_log("Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>
