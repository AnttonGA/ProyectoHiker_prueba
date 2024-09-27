<?php
ob_start();

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

// Registrar manejador de errores
set_error_handler('handleError');

// Asegurar el uso de excepciones en caso de errores fatales
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

    // Verificar conexión con la base de datos
    if (!$conn) {
        throw new Exception('Database connection error');
    }
    debug_log("Database connection successful");

    // Obtener y filtrar ID
    $id = isset($_GET['id']) ? filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT) : null;

    // Función modificada para construir las URLs de las imágenes
    function getImageUrl($baseUrl, $path) {
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    debug_log("About to execute query");

    if ($id) {
        // Consulta para una única ruta
        $query = "SELECT r.*, 
                         GROUP_CONCAT(DISTINCT ri.imagen_path) as imagenes,
                         rp.view_count, rp.download_count, rp.favorite_count, rp.complete_count, rp.popularity_score
                  FROM rutas r
                  LEFT JOIN ruta_imagenes ri ON r.id = ri.ruta_id
                  LEFT JOIN route_popularity rp ON r.id = rp.route_id
                  WHERE r.id = ?
                  GROUP BY r.id";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Error preparing statement: ' . $conn->error);
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Route not found');
        }

        $ruta = $result->fetch_assoc();
        $ruta['imagenes'] = $ruta['imagenes'] ? explode(',', $ruta['imagenes']) : [];
        
        // Actualizar las rutas de las imágenes
        $ruta['imagenes'] = array_map(function($path) use ($ruta) {
            return getImageUrl(BASE_URL, $path);
        }, $ruta['imagenes']);
        
        debug_log("Single route processed");
        echo json_encode($ruta);
    } else {
        // Consulta para todas las rutas
        $query = "SELECT r.*, 
                         GROUP_CONCAT(DISTINCT ri.imagen_path) as imagenes,
                         rp.view_count, rp.download_count, rp.favorite_count, rp.complete_count, rp.popularity_score
                  FROM rutas r
                  LEFT JOIN ruta_imagenes ri ON r.id = ri.ruta_id
                  LEFT JOIN route_popularity rp ON r.id = rp.route_id
                  GROUP BY r.id
                  ORDER BY rp.popularity_score DESC";

        debug_log("Executing all routes query");
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception('Query error: ' . $conn->error);
        }

        $rutas = [];
        while ($row = $result->fetch_assoc()) {
            $row['imagenes'] = $row['imagenes'] ? explode(',', $row['imagenes']) : [];
            
            // Actualizar las rutas de las imágenes
            $row['imagenes'] = array_map(function($path) use ($row) {
                return getImageUrl(BASE_URL, $path);
            }, $row['imagenes']);
            
            $rutas[] = $row;
        }

        debug_log("Number of routes fetched: " . count($rutas));
        echo json_encode($rutas);
    }

    debug_log("Query results processed and sent");

} catch (Exception $e) {
    http_response_code(500);
    $error = ['error' => $e->getMessage()];
    debug_log("Exception caught: " . json_encode($error));
    echo json_encode($error);
} finally {
    // Cerrar el statement y la conexión
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    debug_log("Script ended");
}
?>