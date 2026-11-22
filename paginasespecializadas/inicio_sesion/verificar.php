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
    <title>Verificar correo · Kahir.ai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../estilos2.css?v=20261122" rel="stylesheet">
  <!-- Sistema de diseño Kahir (después del CSS de la página) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="../../assets/css/kahir.css?v=20261122" rel="stylesheet">
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
            echo "<h4 class='alert-heading'>Correo verificado</h4>";
            echo "<p>Tu correo ya está verificado. Ya puedes entrar en tu cuenta.</p>";
            echo '</div>';
            // Incluir el formulario de inicio de sesión
            include 'login.php';
        } elseif ($tokenProporcionado) {
            echo '<div class="alert alert-warning" role="alert">';
            echo "<h4 class='alert-heading'>Enlace no válido</h4>";
            echo "<p>El enlace de verificación no es válido o ya se ha usado. Revisa tu correo o vuelve a crear la cuenta.</p>";
            echo '</div>';

            // Incluir el formulario de registro en caso de token inválido
            include 'registro_ia.php';
        } else {
            echo '<div class="alert alert-info" role="alert">';
            echo "<h4 class='alert-heading'>Falta el enlace de verificación</h4>";
            echo "<p>Para verificar tu correo, abre el enlace que te hemos enviado al registrarte.</p>";
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../include.js"></script>

