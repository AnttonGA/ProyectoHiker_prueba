<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');

// TODO esquema: se comprueba el rol 'A' (esquema antiguo); database/schema.sql usa 'admin' y acceso.php guarda el rol tal cual viene de la BD.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'A') {
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
if (!$id) {
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
    // Eliminar el usuario de la base de datos
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
    $_SESSION['message'] = $ok ? "Usuario eliminado exitosamente" : "Error al eliminar el usuario";
} catch (Throwable $e) {
    error_log('eliminar_usuario.php: ' . $e->getMessage());
    $_SESSION['message'] = "Error al eliminar el usuario";
}

$conn->close();

header("Location: user_management.php");
exit();
