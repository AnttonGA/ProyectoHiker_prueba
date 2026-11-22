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
    exit('No se pudo cargar el usuario. Inténtalo de nuevo.');
}

if (!$usuario) {
    http_response_code(404);
    exit('No existe ningún usuario con ese identificador.');
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
        $errores[] = 'El correo no es válido.';
    }
    if (!array_key_exists($rol, $rolesValidos)) {
        $errores[] = 'Elige un rol de la lista.';
    }
    if (!in_array($activo, ['0', '1'], true) || !in_array($emailVerificado, ['0', '1'], true)) {
        $errores[] = 'Algún valor del formulario no es válido. Revísalo.';
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

            $_SESSION['message'] = "Usuario guardado.";
            $conn->close();
            header("Location: user_management.php");
            exit();
        } catch (mysqli_sql_exception $ex) {
            error_log('editar_usuario.php (update): ' . $ex->getMessage());
            if ((int) $ex->getCode() === 1062) {
                // Clave única duplicada (username o email); mensaje genérico
                $errores[] = 'Ese nombre de usuario o ese correo ya están en uso.';
            } else {
                $errores[] = 'No se pudo guardar el usuario. Inténtalo de nuevo.';
            }
        } catch (Throwable $ex) {
            error_log('editar_usuario.php (update): ' . $ex->getMessage());
            $errores[] = 'No se pudo guardar el usuario. Inténtalo de nuevo.';
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
    <title>Editar usuario · Admin · Kahir.ai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Sistema de diseño Kahir (después del CSS de la página) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="../../assets/css/kahir.css?v=20261122" rel="stylesheet">
</head>
<body>
<?php include('navbar.php'); ?>
    <div class="container mt-5">
        <h1>Editar usuario</h1>
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
                <label for="email" class="form-label">Correo electrónico</label>
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
                <label for="email_verificado" class="form-label">Correo verificado</label>
                <select class="form-select" id="email_verificado" name="email_verificado" required>
                    <option value="1" <?php if ((int) $usuario['email_verificado'] === 1) echo 'selected'; ?>>Sí</option>
                    <option value="0" <?php if ((int) $usuario['email_verificado'] !== 1) echo 'selected'; ?>>No</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="user_management.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
