<?php
include('../inicio_sesion/conexion.php');

// Verificar si el usuario está logueado
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: ../inicio_sesion/gestion.php');
    exit();
}

$id = $_GET['id'];
$jsonFilePath = '../skitouring/javascript/rutas.json';

// Cargar el archivo JSON
$rutas = json_decode(file_get_contents($jsonFilePath), true);

// Buscar la ruta correspondiente
$ruta = null;
$rutaIndex = null;
foreach ($rutas as $index => $r) {
    if ($r['id'] == $id) {
        $ruta = $r;
        $rutaIndex = $index;
        break;
    }
}

if ($ruta !== null) {
    // Comprobar que el usuario actual es el propietario de la ruta
    if ($_SESSION['usuario'] != $ruta['usuario']) {
        echo "No tienes permiso para eliminar esta ruta.";
        exit();
    }

    // Función para eliminar la carpeta y su contenido
    function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return rmdir($dir);
    }

    // Eliminar la carpeta asociada a la ruta
    $rutaCarpeta = '../skitouring/rutas_st/' . $ruta['ruta_carpeta'];
    if (deleteDirectory($rutaCarpeta)) {
        // Eliminar la ruta del array
        array_splice($rutas, $rutaIndex, 1);

        // Guardar el nuevo contenido del archivo JSON
        if (file_put_contents($jsonFilePath, json_encode($rutas, JSON_PRETTY_PRINT))) {
            // Redirigir a la página de gestión de rutas
            header("Location: my_routes.php");
            exit();
        } else {
            echo "Error al actualizar el archivo JSON.";
        }
    } else {
        echo "Error al eliminar los archivos de la ruta.";
    }
} else {
    echo "Ruta no encontrada.";
}
?>