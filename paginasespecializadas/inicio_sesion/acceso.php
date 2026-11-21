<?php
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../includes/security.php';
secure_session_start();

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit();
}

// El formulario de login.php lleva el campo csrf_token (login.js envía el FormData del formulario)
csrf_verify(true);

include("conexion.php");

if (!isset($_POST['usuario']) || !isset($_POST['password'])) {
    $response['message'] = 'Por favor, proporciona tanto el usuario como la contraseña.';
    echo json_encode($response);
    exit();
}

$usuario  = trim((string) $_POST['usuario']);
$password = (string) $_POST['password'];

if ($usuario === '' || $password === '') {
    $response['message'] = 'El usuario y la contraseña no pueden estar vacíos.';
    echo json_encode($response);
    exit();
}

try {
    $stmt = $conn->prepare(
        "SELECT id, username, email, password_hash, rol, email_verificado,
                (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS bloqueado
         FROM usuarios WHERE (username = ? OR email = ?) AND activo = 1"
    );
    if (!$stmt) {
        throw new RuntimeException('prepare: ' . $conn->error);
    }
    $stmt->bind_param('ss', $usuario, $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $fila   = $result->fetch_assoc();
    $stmt->close();

    // Cuenta bloqueada temporalmente por demasiados intentos fallidos
    if ($fila && (int) $fila['bloqueado']) {
        $response['message'] = 'Demasiados intentos fallidos. Inténtalo de nuevo dentro de 15 minutos.';
        echo json_encode($response);
        exit();
    }

    $passwordOk = $fila && password_verify($password, $fila['password_hash']);

    if ($fila && !$passwordOk) {
        // 5 fallos seguidos → bloqueo de 15 minutos
        $upd = $conn->prepare(
            "UPDATE usuarios
                SET intentos_login = intentos_login + 1,
                    bloqueado_hasta = IF(intentos_login + 1 >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), bloqueado_hasta)
              WHERE id = ?"
        );
        $upd->bind_param('i', $fila['id']);
        $upd->execute();
        $upd->close();
    }

    if ($passwordOk && !(int) $fila['email_verificado']) {
        $response['message'] = 'Tu cuenta aún no está verificada. Revisa tu correo y pulsa el enlace de verificación.';
        echo json_encode($response);
        exit();
    }

    if ($passwordOk) {
        $upd = $conn->prepare("UPDATE usuarios SET intentos_login = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id = ?");
        $upd->bind_param('i', $fila['id']);
        $upd->execute();
        $upd->close();

        // Nuevo identificador de sesión (evita fijación de sesión) y nuevo token CSRF
        session_regenerate_id(true);
        unset($_SESSION['csrf_token']);

        $_SESSION['id']         = (int) $fila['id'];
        $_SESSION['usuario']    = $fila['username'];
        $_SESSION['user_id']    = (int) $fila['id'];
        $_SESSION['rol']        = $fila['rol'];
        $_SESSION['email']      = $fila['email'];

        // Cookies solo informativas: la autorización se basa siempre en la sesión
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $opcionesCookie = [
            'expires'  => time() + (30 * 24 * 60 * 60),
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie('usuario', $fila['username'], $opcionesCookie);
        setcookie('rol',     $fila['rol'],      $opcionesCookie);

        $response['success']  = true;
        $response['message']  = '¡Bienvenido! Has iniciado sesión correctamente.';
        $response['redirect'] = '../Usuario/my_account.php';
    } else {
        $response['message'] = 'Usuario o contraseña incorrectos.';
    }
} catch (Throwable $e) {
    error_log('acceso.php: ' . $e->getMessage());
    $response['message'] = 'Error al procesar la solicitud. Inténtalo de nuevo.';
}

echo json_encode($response);
mysqli_close($conn);
