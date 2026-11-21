<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');
require_once __DIR__ . '/../../includes/functions.php';

// TODO esquema: este archivo usa el esquema antiguo (nombre, apellidos, mail, usuario, estado y roles 'A'/'U'/'I'/'E');
// database/schema.sql usa username/email/activo y roles 'admin'/'editor'/'usuario'/'guest'. Confirmar con la BD real.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'A') {
    header("Location: ../inicio_sesion/gestion.php");
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Identificador de usuario no válido.');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();

    // Actualizar datos del usuario
    $nombre = (string) ($_POST['nombre'] ?? '');
    $apellidos = (string) ($_POST['apellidos'] ?? '');
    $mail = (string) ($_POST['mail'] ?? '');
    $usuarioNombre = (string) ($_POST['usuario'] ?? '');
    $rol = (string) ($_POST['rol'] ?? '');
    $estado = (string) ($_POST['estado'] ?? '');

    // Solo se aceptan los valores que ofrece el formulario
    if (!in_array($rol, ['A', 'U', 'I', 'E'], true) || !in_array($estado, ['A', 'P', 'D', 'B'], true)) {
        http_response_code(400);
        exit('Datos no válidos.');
    }

    try {
        $sql = "UPDATE usuarios SET nombre = ?, apellidos = ?, mail = ?, usuario = ?, rol = ?, estado = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $nombre, $apellidos, $mail, $usuarioNombre, $rol, $estado, $id);
        $ok = $stmt->execute();
        $stmt->close();
        $_SESSION['message'] = $ok ? "Usuario actualizado exitosamente" : "Error al actualizar el usuario";
    } catch (Throwable $e) {
        error_log('editar_usuario.php: ' . $e->getMessage());
        $_SESSION['message'] = "Error al actualizar el usuario";
    }

    $conn->close();

    header("Location: user_management.php");
    exit();
}

// Obtener datos del usuario
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    http_response_code(404);
    exit('Usuario no encontrado.');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include('navbar.php'); ?>
    <div class="container mt-5">
        <h1>Editar Usuario</h1>
        <form action="editar_usuario.php?id=<?php echo (int) $id; ?>" method="POST">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo e($usuario['nombre'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="apellidos" class="form-label">Apellidos</label>
                <input type="text" class="form-control" id="apellidos" name="apellidos" value="<?php echo e($usuario['apellidos'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="mail" class="form-label">Mail</label>
                <input type="email" class="form-control" id="mail" name="mail" value="<?php echo e($usuario['mail'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="usuario" class="form-label">Usuario</label>
                <input type="text" class="form-control" id="usuario" name="usuario" value="<?php echo e($usuario['usuario'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="rol" class="form-label">Rol</label>
                <select class="form-select" id="rol" name="rol" required>
                    <option value="A" <?php if (($usuario['rol'] ?? '') == 'A') echo 'selected'; ?>>Administrador</option>
                    <option value="U" <?php if (($usuario['rol'] ?? '') == 'U') echo 'selected'; ?>>Usuario</option>
                    <option value="I" <?php if (($usuario['rol'] ?? '') == 'I') echo 'selected'; ?>>Invitado</option>
                    <option value="E" <?php if (($usuario['rol'] ?? '') == 'E') echo 'selected'; ?>>Editor</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="estado" class="form-label">Estado</label>
                <select class="form-select" id="estado" name="estado" required>
                    <option value="A" <?php if (($usuario['estado'] ?? '') == 'A') echo 'selected'; ?>>Activo</option>
                    <option value="P" <?php if (($usuario['estado'] ?? '') == 'P') echo 'selected'; ?>>Preinscrito</option>
                    <option value="D" <?php if (($usuario['estado'] ?? '') == 'D') echo 'selected'; ?>>Desactivado</option>
                    <option value="B" <?php if (($usuario['estado'] ?? '') == 'B') echo 'selected'; ?>>Borrado</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="admin_dashboard.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
