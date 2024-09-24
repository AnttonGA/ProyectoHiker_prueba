<?php
include('../inicio_sesion/conexion.php');

// Verificar si el usuario está logueado y es administrador
session_start();
if (!isset($_SESSION['rol'])) {
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
    deleteDirectory($rutaCarpeta);

    // Eliminar la ruta del array
    array_splice($rutas, $rutaIndex, 1);

    // Guardar el nuevo contenido del archivo JSON
    file_put_contents($jsonFilePath, json_encode($rutas, JSON_PRETTY_PRINT));

    if (isset($_SESSION['rol']) && $_SESSION['rol'] == 'A') {
       // Redirigir a la página de gestión de rutas
    header("Location: routes_management.php");
      }
    }
    if (isset($_SESSION['usuario'])) {
        header("Location: ../Usuario/my_routes.php");
      }

 else {
    echo "Ruta no encontrada.";
}
?>
