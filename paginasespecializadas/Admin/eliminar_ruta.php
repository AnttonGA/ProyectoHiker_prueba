<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');

// Hay que haber iniciado sesión
$miId = (int) ($_SESSION['id'] ?? 0);
if ($miId < 1 || !isset($_SESSION['rol'])) {
    header('Location: ../inicio_sesion/gestion.php');
    exit();
}

// Solo por POST y con token CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}
csrf_verify();

$esAdmin = is_admin();
$destino = $esAdmin ? 'routes_management.php' : '../Usuario/my_account.php';

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identificador de ruta no válido.');
}

try {
    $stmt = $conn->prepare("SELECT usuario_id, estado FROM rutas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $ruta = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Throwable $ex) {
    error_log('eliminar_ruta.php (select): ' . $ex->getMessage());
    $_SESSION['message'] = 'No se pudo eliminar la ruta.';
    header('Location: ' . $destino);
    exit();
}

if (!$ruta) {
    http_response_code(404);
    exit('Ruta no encontrada.');
}

// Solo un administrador o el propietario de la ruta pueden borrarla
if (!$esAdmin && (int) $ruta['usuario_id'] !== $miId) {
    http_response_code(403);
    exit('No tienes permiso para eliminar esta ruta.');
}

try {
    // Borrado lógico: no se tocan filas relacionadas ni ficheros (GPX, imágenes)
    $stmt = $conn->prepare("UPDATE rutas SET estado = 'eliminada' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['message'] = 'Ruta eliminada.';
} catch (Throwable $ex) {
    error_log('eliminar_ruta.php (update): ' . $ex->getMessage());
    $_SESSION['message'] = 'No se pudo eliminar la ruta.';
}

$conn->close();

header('Location: ' . $destino);
exit();
