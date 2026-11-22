<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');

if (!is_admin()) {
    header('Location: ../inicio_sesion/gestion.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin · Kahir.ai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Sistema de diseño Kahir (después del CSS de la página) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="../../assets/css/kahir.css?v=20261122" rel="stylesheet">
</head>
<?php include('navbar.php'); ?>
<body>
<div class="container mt-5">
    <h1>Panel de administración</h1>
    <div class="row">
        <div class="col-md-3">
            <div class="card mb-3 h-100">
                <div class="card-body">
                    <h5 class="card-title">Usuarios</h5>
                    <p class="card-text">Administra los usuarios de la plataforma.</p>
                    <a href="user_management.php" class="btn btn-primary">Ver usuarios</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-3 h-100">
                <div class="card-body">
                    <h5 class="card-title">Rutas</h5>
                    <p class="card-text">Administra las rutas publicadas.</p>
                    <a href="routes_management.php" class="btn btn-primary">Ver rutas</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-3 h-100">
                <div class="card-body">
                    <h5 class="card-title">Ver la web</h5>
                    <p class="card-text">Abre el buscador de rutas tal como lo ven los usuarios.</p>
                    <a href="../skitouring/skitouring.html" class="btn btn-primary">Ir al buscador</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-3 h-100">
                <div class="card-body">
                    <h5 class="card-title">Estadísticas</h5>
                    <p class="card-text">Consulta las estadísticas de la web.</p>
                    <a href="statistics.php" class="btn btn-primary">Ver estadísticas</a>
                </div>
            </div>
        </div>
        <!-- Agrega más opciones según sea necesario -->
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
