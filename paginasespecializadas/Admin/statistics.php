<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');

// TODO esquema: se comprueba el rol 'A' (esquema antiguo); database/schema.sql usa 'admin' y acceso.php guarda el rol tal cual viene de la BD.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'A') {
    header('Location: ../inicio_sesion/gestion.php');
    exit();
}

// Obtener usuarios desde la base de datos
$sqlUsuarios = "SELECT id FROM usuarios";
$resultUsuarios = $conn->query($sqlUsuarios);
$totalUsuarios = $resultUsuarios->num_rows;

// Obtener rutas desde el archivo JSON
// TODO esquema: migrar a BD (tabla rutas). rutas.json ya no existe en esta copia.
$jsonFile = '../skitouring/javascript/rutas.json';
$rutas = is_readable($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
if (!is_array($rutas)) {
    $rutas = [];
}

// Calcular estadísticas de rutas
$rutasPorTipo = [];
$rutasPopulares = [];

foreach ($rutas as $ruta) {
    if (!is_array($ruta) || !isset($ruta['tipo_ruta'])) {
        continue;
    }
    // Agrupar rutas por tipo
    if (!isset($rutasPorTipo[$ruta['tipo_ruta']])) {
        $rutasPorTipo[$ruta['tipo_ruta']] = 0;
    }
    $rutasPorTipo[$ruta['tipo_ruta']]++;

    // Rutas populares basadas en el número de vistas
    if (!isset($ruta['views'])) {
        $ruta['views'] = 0; // Si no hay vistas registradas, asigna 0
    }
    $rutasPopulares[] = $ruta;
}

usort($rutasPopulares, function ($a, $b) {
    return $b['views'] - $a['views'];
});

$rutasPopulares = array_slice($rutasPopulares, 0, 5);

// Convertir datos a JSON para usar en Chart.js
// Flags para que el JSON pueda incrustarse con seguridad dentro de <script>
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$rutasPorTipoJson = json_encode($rutasPorTipo, $jsonFlags);
$rutasPopularesJson = json_encode($rutasPopulares, $jsonFlags);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de la Web</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include('navbar.php'); ?>

    <div class="container mt-5">
        <h1>Estadísticas de la Web</h1>
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-3 h-100">
                    <div class="card-body">
                        <h5 class="card-title">Número de Usuarios</h5>
                        <p class="card-text">Total de usuarios registrados: <?php echo (int) $totalUsuarios; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card mb-3 h-100">
                    <div class="card-body">
                        <h5 class="card-title">Rutas por Tipo</h5>
                        <canvas id="rutasPorTipoChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3 h-100">
                    <div class="card-body">
                        <h5 class="card-title">Rutas Más Populares</h5>
                        <canvas id="rutasPopularesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Datos para el gráfico de rutas por tipo
        var rutasPorTipo = <?php echo $rutasPorTipoJson; ?>;
        var ctx1 = document.getElementById('rutasPorTipoChart').getContext('2d');
        var rutasPorTipoChart = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: Object.keys(rutasPorTipo),
                datasets: [{
                    label: 'Número de Rutas',
                    data: Object.values(rutasPorTipo),
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Datos para el gráfico de rutas más populares
        var rutasPopulares = <?php echo $rutasPopularesJson; ?>;
        var ctx2 = document.getElementById('rutasPopularesChart').getContext('2d');
        var rutasPopularesChart = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: rutasPopulares.map(ruta => ruta.title),
                datasets: [{
                    label: 'Vistas',
                    data: rutasPopulares.map(ruta => ruta.views),
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

