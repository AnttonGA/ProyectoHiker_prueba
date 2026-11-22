<?php
// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';

require_once __DIR__ . '/../../includes/functions.php'; // inicia la sesión segura y carga e()/csrf_*

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

// Mensaje único: no se revela si el email/usuario existen
$mensajeGenerico = 'Si los datos corresponden a una cuenta registrada, recibirás un correo con instrucciones para restablecer tu contraseña.';

// Verificar si se envió un formulario POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify();

    $usuarioExiste = false; // Variable para indicar si el usuario existe en la base de datos
    $email = isset($_POST['mail']) && is_string($_POST['mail']) ? trim($_POST['mail']) : '';
    $usuario = isset($_POST['usuario']) && is_string($_POST['usuario']) ? trim($_POST['usuario']) : '';

    // Validación del campo de correo electrónico
    if ($email === '') {
        $emailErr = "Escribe tu correo electrónico";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailErr = "Ese correo no parece válido. Revísalo.";
    } else {
        try {
            // Consulta para verificar si el correo y usuario existen y coinciden
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND username = ?");
            $stmt->bind_param("ss", $email, $usuario);
            $stmt->execute();
            $stmt->store_result();
            $usuarioExiste = $stmt->num_rows > 0;
            $stmt->close();

            if ($usuarioExiste) {
                // Token de recuperación criptográficamente seguro (64 caracteres hex)
                $token = bin2hex(random_bytes(32));

                // En la BD solo se guarda el hash del token y caduca en 1 hora
                // (columnas de database/migration_seguridad.sql)
                $tokenHash = hash('sha256', $token);
                $updateStmt = $conn->prepare(
                    "UPDATE usuarios SET reset_token_hash = ?, reset_expira = DATE_ADD(NOW(), INTERVAL 1 HOUR)
                     WHERE email = ? AND username = ?"
                );
                $updateStmt->bind_param("sss", $tokenHash, $email, $usuario);
                $updateStmt->execute();
                $updateStmt->close();

                // Enviar correo de recuperación
                $mail = new PHPMailer(true);
                try {
                    // Configuración del servidor SMTP
                    $mail->isSMTP();
                    $mail->Host = env('SMTP_HOST', 'smtp.gmail.com');
                    $mail->SMTPAuth = true;
                    $mail->Username = env('SMTP_USER');
                    $mail->Password = env('SMTP_PASS');
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = (int) env('SMTP_PORT', 587);

                    // Destinatario
                    $mail->setFrom(env('SMTP_USER'), env('SMTP_FROM_NAME', 'Kahir.ai'));
                    $mail->addAddress($email);

                    // Contenido del correo (los datos del usuario se escapan)
                    $mail->isHTML(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->Subject = 'Recuperación de contraseña';
                    $resetLink = BASE_URL . "/paginasespecializadas/inicio_sesion/change_password.php?token=" . urlencode($token);
                    $mail->Body = "Hola " . e($usuario) . ",<br><br>Haz clic en el siguiente enlace para restablecer tu contraseña:<br><a href=\"" . e($resetLink) . "\">Restablecer contraseña</a>";

                    $mail->send();
                } catch (Exception $ex) {
                    error_log('forget_password.php: error de Mailer: ' . $mail->ErrorInfo);
                }
            }
        } catch (Throwable $ex) {
            error_log('forget_password.php: ' . $ex->getMessage());
        }

        // Siempre el mismo mensaje, exista o no la cuenta
        $successMessage = $mensajeGenerico;
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar contraseña · Kahir.ai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Estilos adicionales -->
    <link href="../estilos2.css?v=20261122" rel="stylesheet">
  <!-- Sistema de diseño Kahir (después del CSS de la página) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="../../assets/css/kahir.css?v=20261122" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <div id="menu"></div>

    <!-- Header -->
    <div class="header">
        <div class="overlay">
            <h1>Tu cuenta</h1>
        </div>
    </div>

    <h2 class="text-center mb-4">Recuperar contraseña</h2>
    <div class="container">
        <!-- Mostrar alertas de éxito o error -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo e($successMessage); ?>
            </div>
        <?php elseif (!empty($failureMessage)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo e($failureMessage); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo e($_SERVER["PHP_SELF"]); ?>" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="mail" class="form-label">Correo de tu cuenta</label>
                <input type="email" name="mail" id="mail" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                <div class="invalid-feedback">
                    <?php echo e($emailErr); ?>
                </div>
            </div>
            <div class="mb-3">
                <label for="usuario" class="form-label">Nombre de usuario</label>
                <input type="text" name="usuario" id="usuario" class="form-control" value="<?php echo htmlspecialchars($usuario); ?>" required>
                <div class="invalid-feedback">
                    <?php echo e($usuarioErr); ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Recuperar contraseña</button>
        </form>
    </div>

    <!-- Footer -->
    <div id="footer"></div>

    <!-- Enlace a Bootstrap 5.2.3 JS (opcional, para funcionalidades avanzadas) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../include.js"></script>
</body>
</html>
