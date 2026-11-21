<?php
/**
 * Devuelve el token CSRF de la sesión actual para las páginas HTML estáticas.
 * Lo usa csrf.js. Otros sitios no pueden leer esta respuesta (política del mismo origen).
 */
require_once __DIR__ . '/security.php';
secure_session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

echo json_encode(['token' => csrf_token()]);
