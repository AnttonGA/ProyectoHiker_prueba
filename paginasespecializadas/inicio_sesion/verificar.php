<?php
// Incluir la conexión a la base de datos u otras configuraciones necesarias
require_once 'conexion.php';


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
            echo "Correo verificado exitosamente.";
        } else {
            echo "Error al actualizar el estado del usuario.";
        }
    } else {
        echo "Token de verificación inválido.";
    }
    $stmt->close();
} else {
    echo "Token no proporcionado.";
}

$conn->close();
?>
