<?php
require_once dirname(__DIR__, 2) . '/includes/env_loader.php';
load_env();

$servername = env('DB_HOST', 'localhost');
$username   = env('DB_USER', 'root');
$password   = env('DB_PASS', '');
$dbname     = env('DB_NAME', 'kahir_db');

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die('Conexión fallida: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, env('DB_CHARSET', 'utf8mb4'));
