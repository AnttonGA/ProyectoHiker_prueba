<?php
session_start();
include("conexion.php");
include("config.php");
include("../../includes/functions.php");

$response = ['success' => false, 'message' => ''];

if (!isset($_POST['usuario']) || !isset($_POST['password'])) {
    $response['message'] = 'Por favor, proporciona tanto el usuario como la contraseña.';
    echo json_encode($response);
    exit();
}

$usuario = $_POST['usuario'];
$password = $_POST['password'];

if (empty($usuario) || empty($password)) {
    $response['message'] = 'El usuario y la contraseña no pueden estar vacíos.';
    echo json_encode($response);
    exit();
}

// Consulta a la base de datos
$query = "SELECT * FROM usuarios WHERE usuario = '$usuario' AND password = '$password'";
$resultado = mysqli_query($conn, $query);

if (!$resultado) {
    $response['message'] = 'Ocurrió un error al procesar tu solicitud. Por favor, intenta de nuevo más tarde.';
    echo json_encode($response);
    exit();
}

$numFilas = mysqli_num_rows($resultado);

if ($numFilas > 0) {
    $fila = mysqli_fetch_array($resultado);

    // Crear variables de sesión
    $_SESSION['usuario'] = $fila['usuario'];
    $_SESSION['rol'] = $fila['rol'];

    // Crear cookies
    $expiracion = time() + (30 * 24 * 60 * 60); // 30 días en segundos
    setcookie('usuario', $fila['usuario'], $expiracion, '/');
    setcookie('rol', $fila['rol'], $expiracion, '/');

    $response['success'] = true;
    $response['message'] = '¡Bienvenido! Has iniciado sesión correctamente.';
    $response['redirect'] = root_path('paginasespecializadas/Usuario/my_account.php');
} else {
    $response['message'] = 'El usuario o la contraseña son incorrectos. Por favor, verifica tus datos e intenta de nuevo.';
}

echo json_encode($response);
mysqli_close($conn);
?>