<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/security.php';
secure_session_start();

// "email_existe" se devuelve siempre false para no revelar qué correos están registrados.
// La duplicidad del email se sigue comprobando en el servidor al registrarse (registro_ia.php).
$response = ["usuario_existe" => false, "email_existe" => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// Límite sencillo por sesión: 30 comprobaciones cada 10 minutos
$ahora = time();
$intentos = array_filter($_SESSION['validar_usuario_intentos'] ?? [], function ($t) use ($ahora) {
    return is_int($t) && $t > $ahora - 600;
});
if (count($intentos) >= 30) {
    http_response_code(429);
    echo json_encode($response);
    exit;
}
$intentos[] = $ahora;
$_SESSION['validar_usuario_intentos'] = array_values($intentos);

$data = json_decode(file_get_contents('php://input'), true);

if (is_array($data) && isset($data['usuario']) && is_string($data['usuario']) && $data['usuario'] !== '') {
    require_once 'conexion.php';
    try {
        $stmt = $conn->prepare("SELECT 1 FROM usuarios WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $data['usuario']);
        $stmt->execute();
        $stmt->store_result();
        $response["usuario_existe"] = $stmt->num_rows > 0;
        $stmt->close();
    } catch (Throwable $e) {
        error_log('validar_usuario_email.php: ' . $e->getMessage());
    }
    $conn->close();
}

echo json_encode($response);
