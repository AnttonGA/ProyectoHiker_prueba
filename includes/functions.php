<?php
function root_path($path = '') {
    // Obtén el nombre del directorio de la raíz del proyecto
    $root = dirname($_SERVER['SCRIPT_NAME']);
    
    // Si estamos en la raíz del servidor, ajusta $root a una cadena vacía
    if ($root == '/') {
        $root = '';
    }
    
    // Asegúrate de que la ruta no contenga 'includes' y vuelve a calcular la raíz
    $root = str_replace('/includes', '', $root);

    // Devuelve la ruta completa desde la raíz del proyecto
    return $root . '/' . ltrim($path, '/');
}
require_once __DIR__ . '/security.php';
secure_session_start();
?>
