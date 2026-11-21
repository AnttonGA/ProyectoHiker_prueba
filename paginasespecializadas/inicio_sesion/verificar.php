<?php
// La sesión se inicia antes de cualquier salida: más abajo se incluyen login.php y registro_ia.php (formularios con CSRF)
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();

// Incluir la conexión a la base de datos u otras configuraciones necesarias
require_once 'conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Usuario</title>
    <link rel="stylesheet" href="styles.css"> <!-- Enlace a tu archivo CSS si lo tienes -->
</head>
<body>

<!-- Cabecera -->
<header>
     <!-- Navbar -->
 <div id="menu"></div>

<!-- Contenido principal dinámico -->
<main>
    <div class="container">
        <?php
        $token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
        $tokenValido = false;
        $tokenProporcionado = isset($_GET['token']);

        // Se rechazan tokens vacíos o demasiado cortos (los válidos son bin2hex(random_bytes(32)))
        if ($token !== '' && strlen($token) >= 32) {
            try {
                // Marca el email como verificado y anula el token (un solo uso)
                $stmt = $conn->prepare(
                    "UPDATE usuarios SET email_verificado = 1, token_verificacion = NULL
                     WHERE token_verificacion = ? AND email_verificado = 0"
                );
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $tokenValido = $stmt->affected_rows === 1;
                $stmt->close();
            } catch (Throwable $ex) {
                error_log('verificar.php: ' . $ex->getMessage());
                $tokenValido = false;
            }
        }

        if ($tokenValido) {
            echo '<div class="alert alert-success" role="alert">';
            echo "<h4 class='alert-heading'>Verificación Exitosa</h4>";
            echo "<p>Tu correo ha sido verificado exitosamente. Ahora puedes acceder a tu cuenta.</p>";
            echo '</div>';
            // Incluir el formulario de inicio de sesión
            include 'login.php';
        } elseif ($tokenProporcionado) {
            echo '<div class="alert alert-warning" role="alert">';
            echo "<h4 class='alert-heading'>Token Inválido</h4>";
            echo "<p>El token de verificación no es válido. Por favor, revisa tu correo e inténtalo de nuevo.</p>";
            echo '</div>';

            // Incluir el formulario de registro en caso de token inválido
            include 'registro_ia.php';
        } else {
            echo '<div class="alert alert-info" role="alert">';
            echo "<h4 class='alert-heading'>Token No Proporcionado</h4>";
            echo "<p>No se ha proporcionado ningún token para verificar tu correo. Por favor, revisa tu correo y sigue el enlace de verificación.</p>";
            echo '</div>';

            // Incluir un enlace para reenviar el token
            include 'registro_ia.php';
        }

        ?>
    </div>
</main>

  <!-- Footer -->
  <div id="footer"></div>
</body>
</html>

<script src="../../include.js"></script>

