<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');

// TODO esquema: se comprueba el rol 'A' (esquema antiguo); database/schema.sql usa 'admin' y acceso.php guarda el rol tal cual viene de la BD.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'A') {
    header('Location: ../inicio_sesion/login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<?php include('navbar.php'); ?>
<body>
<div class="container mt-5">
    <h1>Panel de Administración</h1>
    <div class="row">
        <div class="col-md-3">
            <div class="card mb-3 h-100">
                <div class="card-body">
                    <h5 class="card-title">Gestión de Usuarios</h5>
                    <p class="card-text">Administra los usuarios de la plataforma.</p>
                    <a href="user_management.php" class="btn btn-primary">Ver Usuarios</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-3 h-100">
                <div class="card-body">
                    <h5 class="card-title">Gestión de Rutas</h5>
                    <p class="card-text">Administra las rutas en el sistema.</p>
                    <a href="routes_management.php" class="btn btn-primary">Ver Rutas</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-3 h-100">
                <div class="card-body">
                    <h5 class="card-title">Configuraciones</h5>
                    <p class="card-text">Configura la plataforma según tus necesidades.</p>
                    <a href="configuraciones.php" class="btn btn-primary">Configuraciones</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-3 h-100">
                <div class="card-body">
                    <h5 class="card-title">Statistics</h5>
                    <p class="card-text">Conoce las estadisticas de la web.</p>
                    <a href="statistics.php" class="btn btn-primary">Estadísticas</a>
                </div>
            </div>
        </div>
        <!-- Agrega más opciones según sea necesario -->
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
