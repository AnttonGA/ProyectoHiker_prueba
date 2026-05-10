<?php
include('../inicio_sesion/conexion.php');
include '../../includes/functions.php';
session_start();

if (isset($_SESSION['rol']) && $_SESSION['rol'] == 'A') {
    if (isset($_GET['id'])) {
        $id = $_GET['id'];

        // Obtener datos del usuario
        $sql = "SELECT * FROM usuarios WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuario = $result->fetch_assoc();

        $stmt->close();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Actualizar datos del usuario
        $nombre = $_POST['nombre'];
        $apellidos = $_POST['apellidos'];
        $mail = $_POST['mail'];
        $usuarioNombre = $_POST['usuario'];
        $rol = $_POST['rol'];
        $estado = $_POST['estado'];

        $sql = "UPDATE usuarios SET nombre = ?, apellidos = ?, mail = ?, usuario = ?, rol = ?, estado = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $nombre, $apellidos, $mail, $usuarioNombre, $rol, $estado, $id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Usuario actualizado exitosamente";
        } else {
            $_SESSION['message'] = "Error al actualizar el usuario";
        }

        $stmt->close();
        $conn->close();
        
        header("Location: " . root_path('user_management.php'));
        exit();
    }
} else {
    header("Location: " . root_path('gestion.php'));
    exit();
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
        <form action="editar_usuario.php?id=<?php echo $id; ?>" method="POST">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo $usuario['nombre']; ?>" required>
            </div>
            <div class="mb-3">
                <label for="apellidos" class="form-label">Apellidos</label>
                <input type="text" class="form-control" id="apellidos" name="apellidos" value="<?php echo $usuario['apellidos']; ?>" required>
            </div>
            <div class="mb-3">
                <label for="mail" class="form-label">Mail</label>
                <input type="email" class="form-control" id="mail" name="mail" value="<?php echo $usuario['mail']; ?>" required>
            </div>
            <div class="mb-3">
                <label for="usuario" class="form-label">Usuario</label>
                <input type="text" class="form-control" id="usuario" name="usuario" value="<?php echo $usuario['usuario']; ?>" required>
            </div>
            <div class="mb-3">
                <label for="rol" class="form-label">Rol</label>
                <select class="form-select" id="rol" name="rol" required>
                    <option value="A" <?php if ($usuario['rol'] == 'A') echo 'selected'; ?>>Administrador</option>
                    <option value="U" <?php if ($usuario['rol'] == 'U') echo 'selected'; ?>>Usuario</option>
                    <option value="I" <?php if ($usuario['rol'] == 'I') echo 'selected'; ?>>Invitado</option>
                    <option value="E" <?php if ($usuario['rol'] == 'E') echo 'selected'; ?>>Editor</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="estado" class="form-label">Estado</label>
                <select class="form-select" id="estado" name="estado" required>
                    <option value="A" <?php if ($usuario['estado'] == 'A') echo 'selected'; ?>>Activo</option>
                    <option value="P" <?php if ($usuario['estado'] == 'P') echo 'selected'; ?>>Preinscrito</option>
                    <option value="D" <?php if ($usuario['estado'] == 'D') echo 'selected'; ?>>Desactivado</option>
                    <option value="B" <?php if ($usuario['estado'] == 'B') echo 'selected'; ?>>Borrado</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="admin_dashboard.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
