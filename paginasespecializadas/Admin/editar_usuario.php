<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');
require_once __DIR__ . '/../../includes/functions.php';

if (!is_admin()) {
    header("Location: ../inicio_sesion/gestion.php");
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identificador de usuario no válido.');
}

// Valores del ENUM usuarios.rol
$rolesValidos = [
    'admin'   => 'Administrador',
    'editor'  => 'Editor',
    'usuario' => 'Usuario',
    'guest'   => 'Invitado',
];

$miId = (int) ($_SESSION['id'] ?? 0);
$esYoMismo = ($miId === $id);

// Obtener datos del usuario
try {
    $stmt = $conn->prepare("SELECT id, username, email, rol, activo, email_verificado FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Throwable $ex) {
    error_log('editar_usuario.php (select): ' . $ex->getMessage());
    http_response_code(500);
    exit('Error al cargar el usuario.');
}

if (!$usuario) {
    http_response_code(404);
    exit('Usuario no encontrado.');
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username        = trim((string) ($_POST['username'] ?? ''));
    $email           = trim((string) ($_POST['email'] ?? ''));
    $rol             = (string) ($_POST['rol'] ?? '');
    $activo          = (string) ($_POST['activo'] ?? '');
    $emailVerificado = (string) ($_POST['email_verificado'] ?? '');

    $lenUser = mb_strlen($username, 'UTF-8');
    if ($lenUser < 3 || $lenUser > 50 || preg_match('/[\x00-\x1F\x7F<>]/u', $username) !== 0) {
        $errores[] = 'El nombre de usuario debe tener entre 3 y 50 caracteres válidos.';
    }
    if (strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errores[] = 'El email no es válido.';
    }
    if (!array_key_exists($rol, $rolesValidos)) {
        $errores[] = 'Rol no válido.';
    }
    if (!in_array($activo, ['0', '1'], true) || !in_array($emailVerificado, ['0', '1'], true)) {
        $errores[] = 'Datos no válidos.';
    }
    // Un administrador no puede quitarse su propio rol de admin ni desactivarse
    if ($esYoMismo && ($rol !== 'admin' || $activo !== '1')) {
        $errores[] = 'No puedes quitarte el rol de administrador ni desactivar tu propia cuenta.';
    }

    if (!$errores) {
        $activoInt = (int) $activo;
        $verifInt  = (int) $emailVerificado;
        try {
            $stmt = $conn->prepare(
                "UPDATE usuarios
                    SET username = ?, email = ?, rol = ?, activo = ?, email_verificado = ?
                  WHERE id = ?"
            );
            $stmt->bind_param("sssiii", $username, $email, $rol, $activoInt, $verifInt, $id);
            $stmt->execute();
            $stmt->close();

            if ($esYoMismo) {
                // Mantener la sesión coherente con los nuevos datos
                $_SESSION['usuario'] = $username;
                $_SESSION['email'] = $email;
            }

            $_SESSION['message'] = "Usuario actualizado exitosamente";
            $conn->close();
            header("Location: user_management.php");
            exit();
        } catch (mysqli_sql_exception $ex) {
            error_log('editar_usuario.php (update): ' . $ex->getMessage());
            if ((int) $ex->getCode() === 1062) {
                // Clave única duplicada (username o email); mensaje genérico
                $errores[] = 'El nombre de usuario o el email ya están en uso.';
            } else {
                $errores[] = 'Error al actualizar el usuario.';
            }
        } catch (Throwable $ex) {
            error_log('editar_usuario.php (update): ' . $ex->getMessage());
            $errores[] = 'Error al actualizar el usuario.';
        }
    }

    // Volver a mostrar el formulario con lo que envió el administrador
    $usuario['username'] = $username;
    $usuario['email'] = $email;
    if (array_key_exists($rol, $rolesValidos)) {
        $usuario['rol'] = $rol;
    }
    if (in_array($activo, ['0', '1'], true)) {
        $usuario['activo'] = (int) $activo;
    }
    if (in_array($emailVerificado, ['0', '1'], true)) {
        $usuario['email_verificado'] = (int) $emailVerificado;
    }
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
        <?php if ($errores): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errores as $err): ?>
                        <li><?php echo e($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($esYoMismo): ?>
            <div class="alert alert-warning">Estás editando tu propia cuenta: no puedes quitarte el rol de administrador ni desactivarla.</div>
        <?php endif; ?>
        <form action="editar_usuario.php?id=<?php echo (int) $id; ?>" method="POST">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="username" class="form-label">Nombre de usuario</label>
                <input type="text" class="form-control" id="username" name="username" maxlength="50" minlength="3" value="<?php echo e($usuario['username']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" maxlength="100" value="<?php echo e($usuario['email']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="rol" class="form-label">Rol</label>
                <select class="form-select" id="rol" name="rol" required>
                    <?php foreach ($rolesValidos as $valor => $etiqueta): ?>
                        <option value="<?php echo e($valor); ?>" <?php if ($usuario['rol'] === $valor) echo 'selected'; ?>><?php echo e($etiqueta); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="activo" class="form-label">Cuenta activa</label>
                <select class="form-select" id="activo" name="activo" required>
                    <option value="1" <?php if ((int) $usuario['activo'] === 1) echo 'selected'; ?>>Sí</option>
                    <option value="0" <?php if ((int) $usuario['activo'] !== 1) echo 'selected'; ?>>No</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="email_verificado" class="form-label">Email verificado</label>
                <select class="form-select" id="email_verificado" name="email_verificado" required>
                    <option value="1" <?php if ((int) $usuario['email_verificado'] === 1) echo 'selected'; ?>>Sí</option>
                    <option value="0" <?php if ((int) $usuario['email_verificado'] !== 1) echo 'selected'; ?>>No</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="user_management.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
