<?php
//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require 'vendor/autoload.php';

// Este archivo también se incluye desde gestion.php y verificar.php, que ya inician la sesión antes de la salida
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();

require_once 'config.php';

// Incluir la conexión a la base de datos u otras configuraciones necesarias
require_once 'conexion.php';


// Inicializar variables y mensajes de error
$nombre = $apellidos = $mail = $email = $usuario = $telefono = "";
$nombreErr = $apellidosErr = $mailErr = $usuarioErr = $passwordErr = $telefonoErr = $terminosErr = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify();
    $error = false;

    if (empty($_POST['nombre'])) {
        $nombreErr = "Escribe tu nombre";
        $error = true;
    } else {
        $nombre = trim((string) $_POST['nombre']);
    }

    if (empty($_POST['apellidos'])) {
        $apellidosErr = "Escribe tus apellidos";
        $error = true;
    } else {
        $apellidos = trim((string) $_POST['apellidos']);
    }

    if (empty($_POST['mail'])) {
        $mailErr = "Escribe tu correo electrónico";
        $error = true;
    } else {
        $email = trim((string) $_POST['mail']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mailErr = "Ese correo no parece válido. Revísalo.";
            $error = true;
        } else {
            // Verificar si el correo ya está registrado
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $mailErr = "Ya hay una cuenta con este correo. Entra o recupera tu contraseña.";
                $error = true;
            }
            $stmt->close();
        }
    }

   // Validación y limpieza del campo de usuario
if (empty($_POST['usuario'])) {
    $usuarioErr = "Elige un nombre de usuario";
    $error = true; // Marcar error si el campo está vacío
} else {
    $usuario = trim((string) $_POST['usuario']);
    
    // Verificar si el usuario ya existe en la base de datos
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $usuarioErr = "Ese nombre de usuario ya está cogido. Prueba con otro."; // Mensaje de error si el usuario ya existe
        $error = true; // Marcar error
    }

    $stmt->close();
}

    
    // Validación de contraseña
    if (empty($_POST['password'])) {
        $passwordErr = "Escribe una contraseña";
        $error = true;
    } else {
        $password = $_POST['password'];
        $passwordErr = "";

        // Validación de la longitud mínima de la contraseña
        if (strlen($password) < 8) {
            $passwordErr .= "La contraseña debe tener al menos 8 caracteres.<br>";
            $error = true;
        }

        // Validación de letras mayúsculas
        if (!preg_match('/[A-Z]/', $password)) {
            $passwordErr .= "La contraseña debe incluir al menos una letra mayúscula.<br>";
            $error = true;
        }

        // Validación de letras minúsculas
        if (!preg_match('/[a-z]/', $password)) {
            $passwordErr .= "La contraseña debe incluir al menos una letra minúscula.<br>";
            $error = true;
        }

        // Validación de números
        if (!preg_match('/[0-9]/', $password)) {
            $passwordErr .= "La contraseña debe incluir al menos un número.<br>";
            $error = true;
        }
        if (!$error) {
            $password = password_hash($password, PASSWORD_BCRYPT);
        }
    }

    if (empty($_POST['telefono'])) {
        $telefonoErr = "Escribe tu teléfono";
        $error = true;
    } else {
        $telefono = trim((string) $_POST['telefono']);
    }

    // Validar aceptación de términos y condiciones
    if (!isset($_POST['terminos'])) {
        $terminosErr = "Acepta los términos y condiciones para continuar";
        $error = true;
    }

    // Capturar suscripción al boletín
    $boletin = isset($_POST['boletin']) ? 'S' : 'N';

    if (!$error) {
        // Token de verificación del correo (antes se usaba $token sin definir)
        $token = bin2hex(random_bytes(32));

        // Usuario nuevo sin verificar; verificar.php lo activa con el token del correo
        // (columna token_verificacion: database/migration_seguridad.sql)
        $stmt = $conn->prepare(
            "INSERT INTO usuarios (username, email, password_hash, rol, email_verificado, token_verificacion)
             VALUES (?, ?, ?, 'usuario', 0, ?)"
        );
        $stmt->bind_param("ssss", $usuario, $email, $password, $token);

        $insertOk = false;
        try {
            $insertOk = $stmt->execute();
        } catch (Throwable $ex) {
            error_log('registro_ia.php: ' . $ex->getMessage());
        }

        if ($insertOk) {
            // Enviar correo de verificación
            $mail = new PHPMailer(true);
            try {
                // Configuración del servidor
                $mail->isSMTP();
                $mail->Host = env('SMTP_HOST', 'smtp.gmail.com');
                $mail->SMTPAuth = true;
                $mail->Username = env('SMTP_USER');
                $mail->Password = env('SMTP_PASS');
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = (int) env('SMTP_PORT', 587);

                // Destinatario
                $mail->setFrom(env('SMTP_USER'), env('SMTP_FROM_NAME', 'Kahir.ai'));
                $mail->addAddress($email, $nombre);

                // Contenido del correo
                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = 'Verificación de correo electrónico';
                
                // Usar BASE_URL para construir el enlace de verificación
                $verificationLink = BASE_URL . "/paginasespecializadas/inicio_sesion/verificar.php?token=" . urlencode($token);
                $mail->Body = "Hola " . e($nombre) . ",<br><br>Pulsa este enlace para verificar tu correo electrónico y activar tu cuenta de Kahir.ai:<br><a href=\"" . e($verificationLink) . "\">Verificar correo</a>";

                $mail->send();
            } catch (Exception $e) {
                error_log('registro_ia.php: error de Mailer: ' . $mail->ErrorInfo);
            }

            $nombre = $apellidos = $mail = $usuario = $telefono = ""; // Limpiar campos
            header("Location: gestion.php");
            exit(); // Asegúrate de terminar la ejecución del script después de la redirección
        } else {
            error_log('registro_ia.php: ' . $stmt->error);
            echo '<div class="alert alert-danger">No se pudo completar el registro. Inténtalo de nuevo más tarde.</div>';
        }
        $stmt->close();
    }
}

mysqli_close($conn);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta · Kahir.ai</title>
</head>
<body>
    <div class="container">
        <h2 class="mb-4">Crear cuenta</h2>
        <form name="registroForm" id="registroForm" method="post" action="<?php echo e($_SERVER["PHP_SELF"]);?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo isset($nombre) ? htmlspecialchars($nombre) : ''; ?>">
                <div class="text-danger" id="nombreError"><?php echo isset($nombreErr) ? $nombreErr : ''; ?></div>
            </div>
            <div class="mb-3">
                <label for="apellidos" class="form-label">Apellidos</label>
                <input type="text" class="form-control" id="apellidos" name="apellidos" value="<?php echo isset($apellidos) ? htmlspecialchars($apellidos) : ''; ?>">
                <div class="text-danger" id="apellidosError"><?php echo isset($apellidosErr) ? $apellidosErr : ''; ?></div>
            </div>
            <div class="mb-3">
                <label for="mail" class="form-label">Correo electrónico</label>
                <input type="email" class="form-control" id="mail" name="mail" value="<?php echo e($email); ?>">
                <div class="text-danger" id="mailError"><?php echo isset($mailErr) ? $mailErr : ''; ?></div>
            </div>
            <div class="mb-3">
                <label for="usuarioa" class="form-label">Nombre de usuario</label>
                <input type="text" class="form-control" id="usuarioa" name="usuario" value="<?php echo isset($usuario) ? htmlspecialchars($usuario) : ''; ?>">
                <div class="text-danger" id="usuarioError"><?php echo isset($usuarioErr) ? $usuarioErr : ''; ?></div>
            </div>
            <div class="mb-3">
                <label for="passworda" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="passworda" name="password">
                <div class="text-danger" id="passwordError"><?php echo isset($passwordErr) ? $passwordErr : ''; ?></div>
            </div>
            <div class="mb-3">
                <label for="telefono" class="form-label">Teléfono</label>
                <input type="tel" class="form-control" id="telefono" name="telefono" value="<?php echo isset($telefono) ? htmlspecialchars($telefono) : ''; ?>">
                <div class="text-danger" id="telefonoError"><?php echo isset($telefonoErr) ? $telefonoErr : ''; ?></div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="terminos" name="terminos" <?php echo isset($_POST['terminos']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="terminos">Acepto los términos y condiciones de uso</label>
                <div class="text-danger" id="terminosError"><?php echo isset($terminosErr) ? $terminosErr : ''; ?></div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="boletin" name="boletin" value="si">
                <label class="form-check-label" for="boletin">Quiero recibir el boletín mensual</label>
            </div>
            <button type="submit" class="btn btn-primary">Crear cuenta</button>
        </form>
    </div>
    <script src="register_validation.js?v=20261122es"></script>
</body>
</html>