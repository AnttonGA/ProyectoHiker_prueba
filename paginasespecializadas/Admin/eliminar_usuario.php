<?php
include('../inicio_sesion/conexion.php');
session_start();

if (isset($_SESSION['rol']) && $_SESSION['rol'] == 'A') {
    if (isset($_GET['id'])) {
        $id = $_GET['id'];

        // Eliminar el usuario de la base de datos
        $sql = "DELETE FROM usuarios WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Usuario eliminado exitosamente";
        } else {
            $_SESSION['message'] = "Error al eliminar el usuario";
        }

        $stmt->close();
        $conn->close();

        header("Location: admin_dashboard.php");
        exit();
    }
} else {
    header('Location: ../inicio_sesion/gestion.php');
    exit();
}
?>
