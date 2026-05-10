<?php
ini_set('display_errors', 0);
error_reporting(0);

session_start();
include("conexion.php");

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (!isset($_POST['usuario']) || !isset($_POST['password'])) {
    $response['message'] = 'Por favor, proporciona tanto el usuario como la contraseña.';
    echo json_encode($response);
    exit();
}

$usuario  = trim($_POST['usuario']);
$password = $_POST['password'];

if (empty($usuario) || empty($password)) {
    $response['message'] = 'El usuario y la contraseña no pueden estar vacíos.';
    echo json_encode($response);
    exit();
}

try {
    $stmt = $conn->prepare(
        "SELECT id, username, email, password_hash, rol FROM usuarios WHERE (username = ? OR email = ?) AND activo = 1"
    );
    $stmt->bind_param('ss', $usuario, $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $fila   = $result->fetch_assoc();
    $stmt->close();

    if ($fila && password_verify($password, $fila['password_hash'])) {
        $_SESSION['usuario']    = $fila['username'];
        $_SESSION['user_id']    = $fila['id'];
        $_SESSION['rol']        = $fila['rol'];
        $_SESSION['email']      = $fila['email'];

        $expiracion = time() + (30 * 24 * 60 * 60);
        setcookie('usuario', $fila['username'], $expiracion, '/');
        setcookie('rol',     $fila['rol'],      $expiracion, '/');

        $response['success']  = true;
        $response['message']  = '¡Bienvenido! Has iniciado sesión correctamente.';
        $response['redirect'] = '../usuario/my_account.php';
    } else {
        $response['message'] = 'Usuario o contraseña incorrectos.';
    }
} catch (Exception $e) {
    $response['message'] = 'Error al procesar la solicitud. Inténtalo de nuevo.';
}

echo json_encode($response);
mysqli_close($conn);
