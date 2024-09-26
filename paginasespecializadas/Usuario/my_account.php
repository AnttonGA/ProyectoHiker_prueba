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
            /* Estilos del submenú y botón */
            #submenuToggle {
                z-index: 1000; /* Asegura que el botón esté siempre visible */
            }

            #advancedOptions {
                z-index: 999; /* Asegura que el submenú esté sobre otros elementos */
                transition: transform 0.3s ease, opacity 0.3s ease;
            }

            #advancedOptions.show {
                opacity: 1;
                transform: translateX(0); /* Mostrar submenú */
            }

            #advancedOptions.hide {
                opacity: 0;
                transform: translateX(100%); /* Ocultar submenú fuera de la pantalla */
            }

            /* Estilos generales */
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

            /* Estilos de la presentación */
            #presentacionContainer {
                position: relative;
                width: 100%;
                margin-bottom: 20px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                border-radius: 10px;
                overflow: hidden;
            }

            .carousel-item {
                height: 300px;
            }

            .carousel-item .content {
                height: 100%;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 20px;
                text-align: center;
                color: white;
            }

            .carousel-item h2 {
                font-size: 2.5rem;
                margin-bottom: 1rem;
            }

            .carousel-item p {
                font-size: 1.2rem;
                margin-bottom: 1.5rem;
            }

            .carousel-item .btn {
                font-size: 1rem;
                font-weight: bold;
                padding: 10px 20px;
                transition: transform 0.3s ease;
            }

            .carousel-item .btn:hover {
                transform: scale(1.05);
            }

            #cerrarPresentacion {
                z-index: 10;
                opacity: 0.7;
                transition: opacity 0.3s ease;
            }

            #cerrarPresentacion:hover {
                opacity: 1;
            }

            /* Estilos responsivos */
            @media (max-width: 768px) {
                .carousel-item .col-6 {
                    flex: 0 0 100%;
                    max-width: 100%;
                }
            }

            /* Estilos adicionales que podrían ser necesarios */
            #presentacionContainer {
                position: relative;
            }

            #funcionalidadesCarrusel {
                width: 100%;
            }

            .carousel-inner {
                width: 100%;
                display: flex;
            }

            .carousel-item {
                width: 100%;
            }

            .carousel-item .row {
                width: 100%;
                margin: 0;
            }

            .carousel-item .col-6 {
                padding: 0;
            }

            .carousel-inner .btn {
                font-size: 1rem;
                font-weight: bold;
            }

            .btn-close {
                filter: invert(100%); /* Para hacer el botón X blanco */
            }
</style>
    </style>
</head>
<body>
    <!-- Navbar -->
    <div id="menu"></div>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Welcome, <?php echo $username; ?>!</h1>
                <p>Explore your routes, your favs and much more.</p>
            </div>
            <li class="list-unstyled">
                <button id="submenuToggle" class="btn btn-secondary">Opciones avanzadas</button>
                <div id="advancedOptions" class="d-none position-fixed" style="background-color: #f8f9fa; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                    <ul class="list-group">
                        <li class="list-group-item"><a href="#">Ajustes</a></li>
                        <li class="list-group-item"><a href="#">Perfil</a></li>
                        <li class="list-group-item"><a href="logout.php">Cerrar sesión</a></li>
                    </ul>
                </div>
            </li>
        </div>

        <div id="presentacionContainer" class="mb-5">
            <div id="funcionalidadesCarrusel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner" id="carouselSlides">
                    <!-- Las diapositivas se generarán dinámicamente -->
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#funcionalidadesCarrusel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#funcionalidadesCarrusel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
            </div>
            <button id="cerrarPresentacion" class="btn btn-sm btn-light position-absolute top-0 end-0 m-2" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
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
    <script src="javascript/submenu.js"></script>
    <script src="javascript/diapositivas.js"></script>
</body>
</html>