<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';

// Incluir la conexión a la base de datos u otras configuraciones necesarias
require_once 'conexion.php';

// Variables para mensajes de error
$passwordErr = "";
$message = "";

// Validación de contraseña y actualización si se proporciona un token
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify();

    // Validación de la contraseña
    if (empty($_POST['password'])) {
        $passwordErr = "Contraseña es requerida";
    } else {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validación de la longitud mínima de la contraseña
        if (strlen($password) < 8) {
            $passwordErr .= "La contraseña debe tener al menos 8 caracteres.<br>";
        }

        // Validación de letras mayúsculas
        if (!preg_match('/[A-Z]/', $password)) {
            $passwordErr .= "La contraseña debe incluir al menos una letra mayúscula.<br>";
        }

        // Validación de letras minúsculas
        if (!preg_match('/[a-z]/', $password)) {
            $passwordErr .= "La contraseña debe incluir al menos una letra minúscula.<br>";
        }

        // Validación de números
        if (!preg_match('/[0-9]/', $password)) {
            $passwordErr .= "La contraseña debe incluir al menos un número.<br>";
        }

        // Verificar que las contraseñas coincidan
        if ($password !== $confirm_password) {
            $passwordErr .= "Las contraseñas no coinciden.<br>";
        }

        // Si no hay errores de validación, continuar con la actualización
        if (empty($passwordErr)) {
            $token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';

            // Los tokens se generan con bin2hex(random_bytes(32)) (64 caracteres); se rechazan los vacíos o cortos
            try {
                if ($token !== '' && strlen($token) >= 32) {
                    // TODO esquema: este archivo usa las columnas `token` y `password`; database/schema.sql
                    // define `password_hash` y no tiene columna de token. Confirmar con la BD real.
                    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE token = ?");
                    $stmt->bind_param("s", $token);
                    $stmt->execute();
                    $stmt->store_result();

                    if ($stmt->num_rows > 0) {
                        // Se guarda el hash de la contraseña, nunca el texto plano.
                        // El token se vacía con '' (no NULL, por si la columna es NOT NULL); los tokens vacíos se rechazan arriba.
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt_update = $conn->prepare("UPDATE usuarios SET password = ?, token = '' WHERE token = ?");
                        $stmt_update->bind_param("ss", $hash, $token);

                        if ($stmt_update->execute()) {
                            $message = 'Contraseña actualizada correctamente. <a href="gestion.php">Inicia sesion</a>';
                        } else {
                            error_log('change_password.php: ' . $stmt_update->error);
                            $message = "Error al actualizar la contraseña.";
                        }
                        $stmt_update->close();
                    } else {
                        $message = "Token no válido.";
                    }
                    $stmt->close();
                } else {
                    $message = "Token no válido o no proporcionado.";
                }
            } catch (Throwable $ex) {
                error_log('change_password.php: ' . $ex->getMessage());
                $message = "Error al actualizar la contraseña.";
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperación de Contraseña</title>
  <!-- Enlace a Bootstrap 5.2.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Estilos adicionales -->
  <link href="../estilos2.css" rel="stylesheet">
  <style>
    /* Estilos adicionales específicos para esta página si es necesario */
  </style>
</head>
<body>
   <!-- Navbar -->
   <div id="menu"></div>

 
  <!-- Header -->
  <header class="header">
    <div class="overlay">
      <div class="container">
        <h1 class="text-white">Recuperación de Contraseña</h1>
      </div>
    </div>
  </header>

  <section class="container mt-5">
    <!-- Mensaje de éxito o error -->
    <?php if (!empty($message)): ?>
      <div class="alert alert-info"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Formulario de recuperación de contraseña -->
    <form method="post" action="<?php echo e($_SERVER["PHP_SELF"]) . "?token=" . e(urlencode((string) ($_GET['token'] ?? ''))); ?>" class="needs-validation" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label for="password" class="form-label">Nueva contraseña:</label>
        <input type="password" name="password" id="password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label for="confirm_password" class="form-label">Repetir contraseña:</label>
        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
        <span class="error-message"><?php echo $passwordErr; ?></span>
      </div>
      <button type="submit" class="btn btn-primary">Actualizar Contraseña</button>
    </form>
  </section>

   <!-- Footer -->
   <div id="footer"></div>


  <!-- Enlace a Bootstrap 5.2.3 JS (opcional, para funcionalidades avanzadas) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../include.js"></script>
</body>
</html>
