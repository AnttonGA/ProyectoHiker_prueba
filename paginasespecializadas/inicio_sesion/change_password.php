

<?php
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
    // Validación de la contraseña
    if (empty($_POST['password'])) {
        $passwordErr = "Contraseña es requerida";
    } else {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

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
            if (isset($_GET['token'])) {
                $token = $_GET['token'];

                // Verificar que el token existe en la base de datos
                $stmt = $conn->prepare("SELECT id FROM usuarios WHERE token = ?");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    // Token encontrado, proceder con la actualización de la contraseña sin hashing
                    $stmt_update = $conn->prepare("UPDATE usuarios SET password = ?, token = NULL WHERE token = ?");
                    $stmt_update->bind_param("ss", $password, $token);

                    if ($stmt_update->execute()) {
                        $message = 'Contraseña actualizada correctamente. <a href="gestion.php">Inicia sesion</a>';
                    } else {
                        $message = "Error al actualizar la contraseña.";
                    }
                    $stmt_update->close();
                } else {
                    $message = "Token no válido.";
                }
                $stmt->close();
            } else {
                $message = "Token no proporcionado.";
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
    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?token=" . htmlspecialchars($_GET['token']); ?>" class="needs-validation" novalidate>
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
