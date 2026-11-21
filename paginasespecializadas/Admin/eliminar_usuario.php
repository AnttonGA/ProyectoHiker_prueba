<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');

if (!is_admin()) {
    header('Location: ../inicio_sesion/gestion.php');
    exit();
}

// Solo por POST y con token CSRF (antes bastaba con abrir un enlace GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}
csrf_verify();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identificador de usuario no válido.');
}

// Evitar que un administrador se borre a sí mismo por error
$miId = (int) ($_SESSION['id'] ?? ($_SESSION['user_id'] ?? 0));
if ($miId === $id) {
    $_SESSION['message'] = "No puedes eliminar tu propio usuario";
    header("Location: user_management.php");
    exit();
}

try {
    // rutas.usuario_id tiene ON DELETE CASCADE: borrar a un autor borraría todas sus rutas.
    // Por eso no se elimina a usuarios con rutas; se recomienda desactivarlos.
    $stmt = $conn->prepare("SELECT COUNT(*) FROM rutas WHERE usuario_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($numRutas);
    $stmt->fetch();
    $stmt->close();

    if ((int) $numRutas > 0) {
        $_SESSION['message'] = "No se puede eliminar un usuario que tiene rutas (se borrarían en cascada). Desactívalo desde Editar en su lugar.";
    } else {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $borrados = $stmt->affected_rows;
        $stmt->close();
        $_SESSION['message'] = $borrados > 0 ? "Usuario eliminado exitosamente" : "El usuario no existe";
    }
} catch (Throwable $ex) {
    // p. ej. una restricción de clave foránea (error 1451)
    error_log('eliminar_usuario.php: ' . $ex->getMessage());
    $_SESSION['message'] = "No se pudo eliminar el usuario. Si tiene datos asociados, desactívalo desde Editar en su lugar.";
}

$conn->close();

header("Location: user_management.php");
exit();
