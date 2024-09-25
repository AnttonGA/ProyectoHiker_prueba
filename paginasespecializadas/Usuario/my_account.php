<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: gestion.php");
    exit();
}
$username = htmlspecialchars($_SESSION['usuario']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta - <?php echo $username; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        .welcome-banner {
            background-color: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .tab-pane {
            padding-top: 20px;
        }
        .route-card {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
     <!-- Navbar -->
 <div id="menu"></div>
 <body>
 <div class="container mt-5">
        <div class="">
            <h1>Bienvenido, <?php echo $username; ?>!</h1>
            <p>Explora tus rutas, favoritos y más en tu perfil personalizado.</p>
        </div>

        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="routes-tab" data-bs-toggle="tab" data-bs-target="#routes" type="button" role="tab" aria-controls="routes" aria-selected="true">Mis Rutas</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="favorites-tab" data-bs-toggle="tab" data-bs-target="#favorites" type="button" role="tab" aria-controls="favorites" aria-selected="false">Favoritas</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab" aria-controls="completed" aria-selected="false">Completadas</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="lists-tab" data-bs-toggle="tab" data-bs-target="#lists" type="button" role="tab" aria-controls="lists" aria-selected="false">Mis Listas</button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="routes" role="tabpanel" aria-labelledby="routes-tab">
                <div id="userRoutes" class="row">
                    <!-- Las rutas del usuario se cargarán aquí -->
                </div>
            </div>
            <div class="tab-pane fade" id="favorites" role="tabpanel" aria-labelledby="favorites-tab">
                <div id="favoriteRoutes" class="row">
                    <!-- Las rutas favoritas se cargarán aquí -->
                </div>
            </div>
            <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
                <div id="completedRoutes" class="row">
                    <!-- Las rutas completadas se cargarán aquí -->
                </div>
            </div>
            <div class="tab-pane fade" id="lists" role="tabpanel" aria-labelledby="lists-tab">
                <div id="userLists">
                    <!-- Las listas del usuario se cargarán aquí -->
                </div>
                <button class="btn btn-primary mt-3" id="createListBtn">Crear Nueva Lista</button>
            </div>
        </div>
    </div>

    <!-- Modal para ver rutas de una lista -->
    <div class="modal fade" id="listRoutesModal" tabindex="-1" aria-labelledby="listRoutesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="listRoutesModalLabel">Rutas en la lista</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="listRoutesContent">
                    <!-- Las rutas de la lista se cargarán aquí -->
                </div>
            </div>
        </div>
    </div>
     <!-- Footer -->
  <div id="footer"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="javascript/completadasusuario.js" defer></script>
    <script src="javascript/rutasusuario.js" defer></script>
    <script src="javascript/favoritosusuario.js" defer></script>
    <script src="javascript/user_lists.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../include.js"></script>
    
</body>
</html>