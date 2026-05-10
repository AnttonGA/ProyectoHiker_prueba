<?php
// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';

include '../../includes/functions.php'; 

require_once 'config.php';

// Incluir la conexión a la base de datos u otras configuraciones necesarias
require_once 'conexion.php';

// Variables para mensajes de error y éxito
$emailErr = "";
$usuarioErr = "";
$successMessage = "";
$failureMessage = "";
// Variables para los campos de entrada
$email = "";
$usuario = "";

// Verificar si se envió un formulario POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Inicializar variables
    $email = "";
    $usuario = "";
    $usuarioExiste = false; // Variable para indicar si el usuario existe en la base de datos

    // Validación y limpieza del campo de correo electrónico
    if (empty($_POST['mail'])) {
        $emailErr = "El email es requerido";
    } else {
        $email = mysqli_real_escape_string($conn, $_POST['mail']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailErr = "Formato de email inválido";
        } else {
            // Validación y consulta para verificar si el correo y usuario existen
            $usuario = mysqli_real_escape_string($conn, $_POST['usuario']);
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND username = ?");
            $stmt->bind_param("ss", $email, $usuario);
            $stmt->execute();
            $stmt->store_result();

            // Si hay al menos una fila, el usuario existe
            if ($stmt->num_rows > 0) {
                $usuarioExiste = true;
            } else {
                $failureMessage = "El correo electrónico y/o usuario no están registrados o no coinciden";
            }

            $stmt->close();
        }
    }

    // Si el usuario existe, proceder con el envío del correo de recuperación
    if ($usuarioExiste) {
        // Generar token de recuperación (ejemplo básico, mejorar para entorno de producción)
        $token = uniqid();

        // Actualizar token en la base de datos para el usuario correspondiente (por seguridad)
        $updateStmt = $conn->prepare("UPDATE usuarios SET token = ? WHERE mail = ?");
        $updateStmt->bind_param("ss", $token, $email);
        $updateStmt->execute();
        $updateStmt->close();

        // Enviar correo de recuperación
        $mail = new PHPMailer(true);
        try {
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Cambia esto por tu servidor SMTP
            $mail->SMTPAuth = true;
            $mail->Username = 'thedonkeysocialclub@gmail.com'; // Cambia esto por tu correo
            $mail->Password = 'soye uknz vwci ymzg'; // Cambia esto por tu contraseña
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Destinatario
            $mail->setFrom('thedonkeysocialclub@gmail.com', 'The Donkey Team');
            $mail->addAddress($email); // Aquí deberías usar el correo del usuario encontrado

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = 'Recuperación de contraseña';
            $resetLink = BASE_URL . "/paginasespecializadas/inicio_sesion/change_password.php?token=$token";
            $mail->Body = "Hola $usuario,<br><br>Haz clic en el siguiente enlace para restablecer tu contraseña:<br><a href='$resetLink'>Restablecer contraseña</a>";

            $mail->send();
            $successMessage = 'El mensaje ha sido enviado con instrucciones para restablecer tu contraseña.';
        } catch (Exception $e) {
            $failureMessage = "No se pudo enviar el mensaje. Error de Mailer: {$mail->ErrorInfo}";
        }
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar contraseña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Estilos adicionales -->
    <link href="../estilos2.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <div id="menu"></div>

    <!-- Header -->
    <div class="header">
        <div class="overlay">
            <h1>TU CUENTA</h1>
        </div>
    </div>

    <h2 class="text-center mb-4">Recuperar contraseña</h2>
    <div class="container">
        <!-- Mostrar alertas de éxito o error -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $successMessage; ?>
            </div>
        <?php elseif (!empty($failureMessage)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $failureMessage; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="mail" class="form-label">Email asociado a tu cuenta:</label>
                <input type="email" name="mail" id="mail" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                <div class="invalid-feedback">
                    <?php echo $emailErr; ?>
                </div>
            </div>
            <div class="mb-3">
                <label for="usuario" class="form-label">Usuario:</label>
                <input type="text" name="usuario" id="usuario" class="form-control" value="<?php echo htmlspecialchars($usuario); ?>" required>
                <div class="invalid-feedback">
                    <?php echo $usuarioErr; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Recuperar Contraseña</button>
        </form>
    </div>

    <!-- Footer -->
    <div id="footer"></div>

    <!-- Enlace a Bootstrap 5.2.3 JS (opcional, para funcionalidades avanzadas) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../include.js"></script>
</body>
</html>
