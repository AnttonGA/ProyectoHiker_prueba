<?php
require_once __DIR__ . '/includes/functions.php'; // inicia la sesión segura y define root_path()

// Vaciar y destruir la sesión
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'] ?: 'Lax',
    ]);
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Borrar las cookies informativas que se crean al iniciar sesión
setcookie('usuario', '', time() - 42000, '/');
setcookie('rol', '', time() - 42000, '/');

// Redirige al usuario a la página principal
header("Location: " . root_path('index.php'));
exit;
