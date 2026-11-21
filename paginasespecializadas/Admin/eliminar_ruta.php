<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');

// Hay que haber iniciado sesión
if (!isset($_SESSION['usuario'], $_SESSION['rol'])) {
    header('Location: ../inicio_sesion/gestion.php');
    exit();
}

// Solo por POST y con token CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}
csrf_verify();

// TODO esquema: se comprueba el rol 'A' (esquema antiguo); database/schema.sql usa 'admin'.
$esAdmin = ($_SESSION['rol'] === 'A');
$destino = $esAdmin ? 'routes_management.php' : '../Usuario/my_account.php';

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Identificador de ruta no válido.');
}

// TODO esquema: migrar a BD (tabla rutas). Se mantiene de momento la lógica sobre rutas.json.
$jsonFilePath = __DIR__ . '/../skitouring/javascript/rutas.json';
if (!is_readable($jsonFilePath)) {
    http_response_code(404);
    exit('Ruta no encontrada.');
}

// Cargar el archivo JSON
$rutas = json_decode(file_get_contents($jsonFilePath), true);
if (!is_array($rutas)) {
    $rutas = [];
}

// Buscar la ruta correspondiente
$ruta = null;
$rutaIndex = null;
foreach ($rutas as $index => $r) {
    if (is_array($r) && isset($r['id']) && (string) $r['id'] === (string) $id) {
        $ruta = $r;
        $rutaIndex = $index;
        break;
    }
}

if ($ruta === null) {
    http_response_code(404);
    exit('Ruta no encontrada.');
}

// Solo un administrador o el propietario de la ruta pueden borrarla
// TODO esquema: se asume que cada ruta del JSON guarda su autor en 'usuario'.
$propietario = $ruta['usuario'] ?? null;
if (!$esAdmin && (!is_string($propietario) || $propietario !== $_SESSION['usuario'])) {
    http_response_code(403);
    exit('No tienes permiso para eliminar esta ruta.');
}

// Función para eliminar la carpeta y su contenido
function deleteDirectory($dir) {
    if (!file_exists($dir) && !is_link($dir)) {
        return true;
    }
    if (!is_dir($dir) || is_link($dir)) {
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

// Eliminar la carpeta asociada a la ruta: solo una subcarpeta de rutas_st, nunca rutas_st entera
$baseRutas = realpath(__DIR__ . '/../skitouring/rutas_st');
$carpeta = basename(str_replace('\\', '/', (string) ($ruta['ruta_carpeta'] ?? '')));
if ($baseRutas !== false && $carpeta !== '' && $carpeta !== '.' && $carpeta !== '..') {
    $rutaCarpeta = realpath($baseRutas . DIRECTORY_SEPARATOR . $carpeta);
    if ($rutaCarpeta !== false && $rutaCarpeta !== $baseRutas
        && strpos($rutaCarpeta, $baseRutas . DIRECTORY_SEPARATOR) === 0) {
        deleteDirectory($rutaCarpeta);
    }
}

// Eliminar la ruta del array y guardar el JSON
unset($rutas[$rutaIndex]);
$rutas = array_values($rutas);
file_put_contents($jsonFilePath, json_encode($rutas, JSON_PRETTY_PRINT), LOCK_EX);

$_SESSION['message'] = 'Ruta eliminada.';
header('Location: ' . $destino);
exit();
