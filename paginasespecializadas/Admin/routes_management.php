<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');

if (!is_admin()) {
    header('Location: ../inicio_sesion/gestion.php');
    exit();
}

// Listas blancas = valores de los ENUM de la tabla rutas
$tiposValidos       = ['ski_touring', 'hiking', 'cycle_touring', 'climbing', 'snowshoeing', 'via_ferrata', 'btt', 'kayaking'];
$dificultadesValidas = ['beginner', 'intermediate', 'advanced', 'expert'];
$estadosValidos     = ['publicada', 'borrador', 'eliminada'];

$porPagina = 50;

// Parámetros GET (búsqueda y filtros)
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
if (mb_strlen($q, 'UTF-8') > 100) {
    $q = mb_substr($q, 0, 100, 'UTF-8');
}
$tipo       = in_array($_GET['tipo_ruta'] ?? '', $tiposValidos, true) ? $_GET['tipo_ruta'] : '';
$dificultad = in_array($_GET['dificultad'] ?? '', $dificultadesValidas, true) ? $_GET['dificultad'] : '';
$estado     = in_array($_GET['estado'] ?? '', $estadosValidos, true) ? $_GET['estado'] : '';
$pagina     = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

// Construcción del WHERE con parámetros preparados
$condiciones = [];
$tipos = '';
$params = [];

if ($q !== '') {
    // Escapar los comodines de LIKE para que se busquen literalmente
    $qLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $condiciones[] = 'r.titulo LIKE ?';
    $tipos .= 's';
    $params[] = $qLike;
}
if ($tipo !== '') {
    $condiciones[] = 'r.tipo_ruta = ?';
    $tipos .= 's';
    $params[] = $tipo;
}
if ($dificultad !== '') {
    $condiciones[] = 'r.dificultad = ?';
    $tipos .= 's';
    $params[] = $dificultad;
}
if ($estado !== '') {
    $condiciones[] = 'r.estado = ?';
    $tipos .= 's';
    $params[] = $estado;
}
$where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

$rutas = [];
$total = 0;
$totalPaginas = 1;
$errorMsg = '';

try {
    // Total de resultados
    $stmt = $conn->prepare("SELECT COUNT(*) FROM rutas r $where");
    if ($params) {
        $stmt->bind_param($tipos, ...$params);
    }
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    $total = (int) $total;
    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    if ($pagina > $totalPaginas) {
        $pagina = $totalPaginas;
    }
    $offset = ($pagina - 1) * $porPagina;

    // Página actual
    $sql = "SELECT r.id, r.titulo, r.tipo_ruta, r.dificultad, r.ubicacion, r.distancia_km, r.estado,
                   u.username AS autor, COALESCE(rp.view_count, 0) AS views
              FROM rutas r
              LEFT JOIN usuarios u ON u.id = r.usuario_id
              LEFT JOIN route_popularity rp ON rp.ruta_id = r.id
              $where
             ORDER BY r.id DESC
             LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $tiposPagina = $tipos . 'ii';
    $paramsPagina = array_merge($params, [$porPagina, $offset]);
    $stmt->bind_param($tiposPagina, ...$paramsPagina);
    $stmt->execute();
    $rutas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $ex) {
    error_log('routes_management.php: ' . $ex->getMessage());
    $errorMsg = 'No se pudo cargar el listado de rutas.';
}

// Genera la URL de una página conservando búsqueda y filtros
function pagina_url(int $n, string $q, string $tipo, string $dificultad, string $estado): string {
    $query = array_filter([
        'q' => $q,
        'tipo_ruta' => $tipo,
        'dificultad' => $dificultad,
        'estado' => $estado,
    ], fn($v) => $v !== '');
    $query['page'] = $n;
    return 'routes_management.php?' . http_build_query($query);
}

function etiqueta_enum(?string $valor): string {
    // Etiquetas visibles en español; los valores que viajan al servidor no cambian
    static $etiquetas = [
        'ski_touring' => 'Esquí de travesía', 'hiking' => 'Senderismo', 'cycle_touring' => 'Cicloturismo',
        'climbing' => 'Escalada', 'snowshoeing' => 'Raquetas', 'via_ferrata' => 'Vía ferrata',
        'btt' => 'BTT', 'kayaking' => 'Kayak',
        'beginner' => 'Fácil', 'intermediate' => 'Moderada', 'advanced' => 'Difícil', 'expert' => 'Experta',
        'Western_Pyrenees' => 'Pirineo occidental', 'Central_Pyrenees' => 'Pirineo central',
        'Eastern_Pyrenees' => 'Pirineo oriental', 'Other' => 'Otras zonas',
        'publicada' => 'Publicada', 'borrador' => 'Borrador', 'eliminada' => 'Eliminada',
    ];
    $valor = (string) $valor;
    return $etiquetas[$valor] ?? ucfirst(str_replace('_', ' ', $valor));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rutas · Admin · Kahir.ai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .search-box {
            margin-bottom: 20px;
        }
        .btn-primary, .btn-danger, .btn-info {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
    </style>
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
        <h1>Gestión de rutas</h1>
        <?php if (!empty($_SESSION['message'])): ?>
            <div class="alert alert-info"><?php echo e($_SESSION['message']); ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        <?php if ($errorMsg !== ''): ?>
            <div class="alert alert-danger"><?php echo e($errorMsg); ?></div>
        <?php endif; ?>
        <div class="mb-3">
            <a href="../Usuario/subir_ruta.html" class="btn btn-success">Subir ruta</a>
        </div>

        <!-- Búsqueda y filtros en servidor -->
        <form method="GET" action="routes_management.php" class="row g-2 search-box">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control" maxlength="100" placeholder="Buscar por título" aria-label="Buscar por título" value="<?php echo e($q); ?>">
            </div>
            <div class="col-md-2">
                <select name="tipo_ruta" class="form-select" aria-label="Actividad">
                    <option value="">Todas las actividades</option>
                    <?php foreach ($tiposValidos as $t): ?>
                        <option value="<?php echo e($t); ?>" <?php if ($tipo === $t) echo 'selected'; ?>><?php echo e(etiqueta_enum($t)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="dificultad" class="form-select" aria-label="Dificultad">
                    <option value="">Todas las dificultades</option>
                    <?php foreach ($dificultadesValidas as $d): ?>
                        <option value="<?php echo e($d); ?>" <?php if ($dificultad === $d) echo 'selected'; ?>><?php echo e(etiqueta_enum($d)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="estado" class="form-select" aria-label="Estado">
                    <option value="">Todos los estados</option>
                    <?php foreach ($estadosValidos as $es): ?>
                        <option value="<?php echo e($es); ?>" <?php if ($estado === $es) echo 'selected'; ?>><?php echo e(etiqueta_enum($es)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="routes_management.php" class="btn btn-outline-secondary">Quitar filtros</a>
            </div>
        </form>

        <p class="text-muted">
            <?php echo e(number_format($total, 0, ',', '.')); ?> rutas encontradas
            — página <?php echo (int) $pagina; ?> de <?php echo (int) $totalPaginas; ?>
        </p>

        <div class="table-responsive">
        <table class="table table-bordered table-striped mt-3" id="routeTable">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Actividad</th>
                    <th>Dificultad</th>
                    <th>Zona</th>
                    <th>Distancia (km)</th>
                    <th>Estado</th>
                    <th>Autor</th>
                    <th>Vistas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rutas as $ruta) { $rid = (int) $ruta['id']; ?>
                    <tr>
                        <td><?php echo $rid; ?></td>
                        <td><?php echo e($ruta['titulo']); ?></td>
                        <td><?php echo e(etiqueta_enum($ruta['tipo_ruta'])); ?></td>
                        <td><?php echo e(etiqueta_enum($ruta['dificultad'])); ?></td>
                        <td><?php echo e(etiqueta_enum($ruta['ubicacion'])); ?></td>
                        <td><?php echo $ruta['distancia_km'] !== null && $ruta['distancia_km'] !== '' ? e(number_format((float) $ruta['distancia_km'], 1, ',', '.')) : ''; ?></td>
                        <td><?php echo e(etiqueta_enum($ruta['estado'])); ?></td>
                        <td><?php echo e($ruta['autor'] ?? '—'); ?></td>
                        <td><?php echo e(number_format((int) $ruta['views'], 0, ',', '.')); ?></td>
                        <td class="text-nowrap">
                            <a href="../skitouring/ruta.html?id=<?php echo $rid; ?>" class="btn btn-info btn-sm" target="_blank" rel="noopener">Ver</a>
                            <a href="editar_ruta.php?id=<?php echo $rid; ?>" class="btn btn-primary btn-sm">Editar</a>
                            <?php if ($ruta['estado'] !== 'eliminada'): ?>
                            <form method="POST" action="eliminar_ruta.php" class="d-inline" onsubmit="return confirm('¿Marcar esta ruta como eliminada? Dejará de mostrarse, pero se puede restaurar desde Editar.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?php echo $rid; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php } ?>
                <?php if (!$rutas && $errorMsg === ''): ?>
                    <tr><td colspan="10" class="text-center text-muted">Ninguna ruta coincide con la búsqueda. Prueba a quitar filtros.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
        <nav aria-label="Paginación de rutas">
            <ul class="pagination flex-wrap">
                <li class="page-item <?php if ($pagina <= 1) echo 'disabled'; ?>">
                    <a class="page-link" href="<?php echo e(pagina_url(max(1, $pagina - 1), $q, $tipo, $dificultad, $estado)); ?>">Anterior</a>
                </li>
                <?php
                $desde = max(1, $pagina - 3);
                $hasta = min($totalPaginas, $pagina + 3);
                if ($desde > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?php echo e(pagina_url(1, $q, $tipo, $dificultad, $estado)); ?>">1</a></li>
                    <?php if ($desde > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <?php endif; ?>
                <?php for ($n = $desde; $n <= $hasta; $n++): ?>
                    <li class="page-item <?php if ($n === $pagina) echo 'active'; ?>">
                        <a class="page-link" href="<?php echo e(pagina_url($n, $q, $tipo, $dificultad, $estado)); ?>"><?php echo $n; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($hasta < $totalPaginas): ?>
                    <?php if ($hasta < $totalPaginas - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="<?php echo e(pagina_url($totalPaginas, $q, $tipo, $dificultad, $estado)); ?>"><?php echo (int) $totalPaginas; ?></a></li>
                <?php endif; ?>
                <li class="page-item <?php if ($pagina >= $totalPaginas) echo 'disabled'; ?>">
                    <a class="page-link" href="<?php echo e(pagina_url(min($totalPaginas, $pagina + 1), $q, $tipo, $dificultad, $estado)); ?>">Siguiente</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
