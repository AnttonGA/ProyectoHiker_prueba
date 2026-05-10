<?php
// Configurar manejo de errores
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Establecer headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Función para manejar errores y enviarlos como JSON
function handleError($message, $code = 500) {
    echo json_encode([
        'success' => false,
        'error' => $message,
        'code' => $code
    ]);
    exit;
}

// Registrar manejador de errores personalizado
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    handleError($errstr);
});

try {
    session_start();
    require_once __DIR__ . '/hiker_ai/ia_integration.php';
    require_once __DIR__ . '/hiker_ai/exceptions/AIException.php';
    require_once __DIR__ . '/hiker_ai/services/LoggerService.php';

    // Verificar método de solicitud
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Método no permitido', 405);
    }

    // Verificar autenticación
    if (!isset($_SESSION['usuario'])) {
        handleError('Usuario no autenticado', 401);
    }

    // Verificar y procesar la consulta
    if (!isset($_POST['query']) || empty(trim($_POST['query']))) {
        handleError('No se proporcionó ninguna consulta', 400);
    }

    // Inicializar conexión a la base de datos
    $conn = new mysqli(
        ($_SERVER['SERVER_NAME'] == 'localhost') ? 'localhost' : 'sql300.infinityfree.com',
        ($_SERVER['SERVER_NAME'] == 'localhost') ? 'root' : 'if0_36636778',
        ($_SERVER['SERVER_NAME'] == 'localhost') ? '' : 'lU9fyeWRZGh',
        ($_SERVER['SERVER_NAME'] == 'localhost') ? 'peliculas' : 'if0_36636778_peliculas'
    );
    
    if ($conn->connect_error) {
        handleError("Error de conexión a la base de datos");
    }
    
    $conn->set_charset("utf8mb4");

    // Obtener ID del usuario
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ? AND activo = 1");
    if (!$stmt) {
        handleError("Error en la preparación de la consulta");
    }

    $stmt->bind_param("s", $_SESSION['usuario']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        handleError('Usuario no encontrado o inactivo', 404);
    }

    $userId = $result->fetch_assoc()['id'];
    $stmt->close();

    // Procesar la consulta
    $query = trim($_POST['query']);
    
    // Inicializar IA y procesar respuesta
    $ai = new IAIntegration();
    $result = $ai->generateResponse($userId, $query);

    // Asegurar que la respuesta es JSON válido
    if (!is_array($result)) {
        $result = [
            'success' => true,
            'response' => $result
        ];
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    handleError($e->getMessage());
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}