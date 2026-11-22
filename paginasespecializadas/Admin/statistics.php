<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');

if (!is_admin()) {
    header('Location: ../inicio_sesion/gestion.php');
    exit();
}

/** Ejecuta una consulta preparada sin parámetros y devuelve todas las filas. */
function consulta(mysqli $conn, string $sql): array {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

$errorMsg = '';
$usuariosStats = ['total' => 0, 'activos' => 0, 'verificados' => 0];
$rutasPorEstado = ['publicada' => 0, 'borrador' => 0, 'eliminada' => 0];
$rutasPorTipo = [];
$rutasPopulares = [];
$totales = ['favoritos' => 0, 'completadas' => 0, 'chats' => 0];

try {
    $fila = consulta($conn,
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(activo = 1), 0) AS activos,
                COALESCE(SUM(email_verificado = 1), 0) AS verificados
           FROM usuarios"
    )[0] ?? [];
    foreach ($usuariosStats as $k => $_) {
        $usuariosStats[$k] = (int) ($fila[$k] ?? 0);
    }

    foreach (consulta($conn, "SELECT estado, COUNT(*) AS n FROM rutas GROUP BY estado") as $f) {
        $rutasPorEstado[$f['estado']] = (int) $f['n'];
    }

    $etiquetasTipo = [
        'ski_touring' => 'Esquí de travesía', 'hiking' => 'Senderismo', 'cycle_touring' => 'Cicloturismo',
        'climbing' => 'Escalada', 'snowshoeing' => 'Raquetas', 'via_ferrata' => 'Vía ferrata',
        'btt' => 'BTT', 'kayaking' => 'Kayak',
    ];
    foreach (consulta($conn, "SELECT tipo_ruta, COUNT(*) AS n FROM rutas WHERE estado <> 'eliminada' GROUP BY tipo_ruta ORDER BY n DESC") as $f) {
        $rutasPorTipo[$etiquetasTipo[(string) $f['tipo_ruta']] ?? ucfirst(str_replace('_', ' ', (string) $f['tipo_ruta']))] = (int) $f['n'];
    }

    $rutasPopulares = consulta($conn,
        "SELECT r.id, r.titulo, rp.view_count AS views
           FROM route_popularity rp
           JOIN rutas r ON r.id = rp.ruta_id
          WHERE r.estado <> 'eliminada'
          ORDER BY rp.view_count DESC, r.id
          LIMIT 10"
    );

    $fila = consulta($conn,
        "SELECT (SELECT COUNT(*) FROM user_favorites) AS favoritos,
                (SELECT COUNT(*) FROM user_completed) AS completadas,
                (SELECT COUNT(*) FROM chat_sessions)  AS chats"
    )[0] ?? [];
    foreach ($totales as $k => $_) {
        $totales[$k] = (int) ($fila[$k] ?? 0);
    }
} catch (Throwable $ex) {
    error_log('statistics.php: ' . $ex->getMessage());
    $errorMsg = 'No se pudieron cargar todas las estadísticas.';
}

$totalRutas = array_sum($rutasPorEstado);

// Convertir datos a JSON para usar en Chart.js
// Flags para que el JSON pueda incrustarse con seguridad dentro de <script>
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$rutasPorTipoJson = json_encode([
    'labels' => array_keys($rutasPorTipo),
    'data'   => array_values($rutasPorTipo),
], $jsonFlags);

function n(int $v): string {
    return number_format($v, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas · Admin · Kahir.ai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <!-- Sistema de diseño Kahir (después del CSS de la página) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="../../assets/css/kahir.css?v=20261122" rel="stylesheet">
</head>
<body>
    <?php include('navbar.php'); ?>

    <div class="container mt-5 mb-5">
        <h1>Estadísticas de la web</h1>
        <?php if ($errorMsg !== ''): ?>
            <div class="alert alert-warning"><?php echo e($errorMsg); ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Usuarios</h5>
                        <p class="card-text mb-1">Registrados: <strong><?php echo e(n($usuariosStats['total'])); ?></strong></p>
                        <p class="card-text mb-1">Activos: <strong><?php echo e(n($usuariosStats['activos'])); ?></strong></p>
                        <p class="card-text">Correo verificado: <strong><?php echo e(n($usuariosStats['verificados'])); ?></strong></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Rutas</h5>
                        <p class="card-text mb-1">Total: <strong><?php echo e(n($totalRutas)); ?></strong></p>
                        <?php foreach ($rutasPorEstado as $estado => $num): ?>
                            <p class="card-text mb-1"><?php echo e(ucfirst($estado)); ?>: <strong><?php echo e(n($num)); ?></strong></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Actividad</h5>
                        <p class="card-text mb-1">Rutas favoritas: <strong><?php echo e(n($totales['favoritos'])); ?></strong></p>
                        <p class="card-text mb-1">Rutas completadas: <strong><?php echo e(n($totales['completadas'])); ?></strong></p>
                        <p class="card-text">Sesiones de chat: <strong><?php echo e(n($totales['chats'])); ?></strong></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Rutas por actividad <small class="text-muted">(sin eliminadas)</small></h5>
                        <canvas id="rutasPorTipoChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Las 10 rutas más vistas</h5>
                        <?php if (!$rutasPopulares): ?>
                            <p class="text-muted">Aún no hay visitas registradas.</p>
                        <?php else: ?>
                            <table class="table table-sm table-striped mb-0">
                                <thead><tr><th>#</th><th>Título</th><th class="text-end">Vistas</th></tr></thead>
                                <tbody>
                                    <?php foreach ($rutasPopulares as $i => $r): ?>
                                        <tr>
                                            <td><?php echo (int) $i + 1; ?></td>
                                            <td><a href="../skitouring/ruta.html?id=<?php echo (int) $r['id']; ?>" target="_blank" rel="noopener"><?php echo e($r['titulo']); ?></a></td>
                                            <td class="text-end"><?php echo e(n((int) $r['views'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Datos para el gráfico de rutas por tipo
        var rutasPorTipo = <?php echo $rutasPorTipoJson; ?>;
        if (window.Chart) {
            new Chart(document.getElementById('rutasPorTipoChart'), {
                type: 'bar',
                data: {
                    labels: rutasPorTipo.labels,
                    datasets: [{
                        label: 'Número de rutas',
                        data: rutasPorTipo.data,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    locale: 'es-ES',
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
