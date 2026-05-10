<?php
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
        if (isset($_GET['token'])) {
            $token = $_GET['token'];

            // Verificar si el token es válido
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                // Actualizar el estado del usuario a 'A' (Activo)
                $stmt = $conn->prepare("UPDATE usuarios SET estado = 'A', token = '' WHERE token = ?");
                $stmt->bind_param("s", $token);
                if ($stmt->execute()) {
                    echo '<div class="alert alert-success" role="alert">';
                    echo "<h4 class='alert-heading'>Verificación Exitosa</h4>";
                    echo "<p>Tu correo ha sido verificado exitosamente. Ahora puedes acceder a tu cuenta.</p>";
                    echo '</div>';
                    // Incluir el formulario de inicio de sesión
                    include 'login.php'; 
                } else {
                    echo '<div class="alert alert-warning" role="alert">';
                    echo "<h4 class='alert-heading'>Token Inválido</h4>";
                    echo "<p>El token de verificación no es válido. Por favor, revisa tu correo e inténtalo de nuevo.</p>";
                    echo '</div>';
                }
            } else {
                echo '<div class="alert alert-warning" role="alert">';
                echo "<h4 class='alert-heading'>Token Inválido</h4>";
                echo "<p>El token de verificación no es válido. Por favor, revisa tu correo e inténtalo de nuevo.</p>";
                echo '</div>';
                
                // Incluir el formulario de registro en caso de token inválido
                include 'registro_ia.php'; 
            }
            $stmt->close();
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

