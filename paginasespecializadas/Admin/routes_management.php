<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');

// TODO esquema: se comprueba el rol 'A' (esquema antiguo); database/schema.sql usa 'admin' y acceso.php guarda el rol tal cual viene de la BD.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'A') {
    header('Location: ../inicio_sesion/gestion.php');
    exit();
}

// TODO esquema: migrar a BD (tabla rutas). rutas.json ya no existe en esta copia.
$jsonFile = '../skitouring/javascript/rutas.json';
$rutas = is_readable($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
if (!is_array($rutas)) {
    $rutas = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Rutas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .search-box {
            margin-bottom: 20px;
        }
        .active-header {
            background-color: #007bff !important;
            color: white !important;
        }
        .btn-primary, .btn-danger {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}
    </style>
</head>
<?php include('navbar.php'); ?>
<body>
    <div class="container mt-5">
        <h1>Gestión de Rutas</h1>
        <?php if (!empty($_SESSION['message'])): ?>
            <div class="alert alert-info"><?php echo e($_SESSION['message']); ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        <div class="mb-3">
            <a href="../Usuario/subir_ruta.html" class="btn btn-success">Agregar Ruta</a>
        </div>
        <input type="text" id="search" class="form-control search-box" placeholder="Buscar rutas...">

        <table class="table table-bordered table-striped mt-3" id="routeTable" data-order="">
    <thead class="thead-dark">
        <tr>
            <th onclick="sortTable(0, this, event)" aria-sort="none">ID</th>
            <th onclick="sortTable(1, this, event)" aria-sort="none">Título</th>
            <th onclick="sortTable(2, this, event)" aria-sort="none">Tipo</th>
            <th onclick="sortTable(3, this, event)" aria-sort="none">Dificultad</th>
            <th>Acciones</th>
        </tr>
    </thead>
            <tbody>
                <?php foreach ($rutas as $ruta) { if (!is_array($ruta) || !isset($ruta['id'])) { continue; } ?>
                    <tr>
                        <td><?php echo e($ruta['id']); ?></td>
                        <td><?php echo e($ruta['title'] ?? ''); ?></td>
                        <td><?php echo e(ucfirst(str_replace('_', ' ', (string) ($ruta['tipo_ruta'] ?? '')))); ?></td>
                        <td><?php echo e(ucfirst((string) ($ruta['difficulty'] ?? ''))); ?></td>
                        <td>
                            <a href="editar_ruta.php?id=<?php echo (int) $ruta['id']; ?>" class="btn btn-primary btn-sm">Editar</a>
                            <form method="POST" action="eliminar_ruta.php" class="d-inline" onsubmit="return confirm('¿Eliminar esta ruta y sus archivos? Esta acción no se puede deshacer.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?php echo (int) $ruta['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
// Guardamos el orden original de las filas
let originalOrder;

// Función para guardar el orden original
function saveOriginalOrder() {
    const table = document.getElementById("routeTable");
    originalOrder = Array.from(table.querySelectorAll('tbody tr'));
}

// Función para asegurar la visibilidad de los botones
function ensureButtonsVisibility() {
    const rows = Array.from(document.querySelectorAll('#routeTable tbody tr'));
    // Los botones se generan en el servidor (eliminar es un formulario POST con token CSRF); no se reescriben con innerHTML.
    rows.forEach(row => {
        row.querySelectorAll('td:last-child .btn').forEach(btn => btn.classList.add('d-inline-block'));
    });
}

// Función para configurar el listener de clic fuera de la tabla
function setupClickOutsideListener() {
    document.addEventListener('click', function(event) {
        const table = document.getElementById("routeTable");
        const isClickInsideTable = table.contains(event.target);
        
        if (!isClickInsideTable) {
            resetTableHeader();
        }
    });
}

// Función para resetear el encabezado de la tabla
function resetTableHeader() {
    const table = document.getElementById("routeTable");
    const headers = table.querySelectorAll('th');
    
    headers.forEach(header => {
        header.classList.remove('active-header');
        header.setAttribute('aria-sort', 'none');
    });
    
    table.setAttribute('data-order', 'default');
}

// Función para ordenar la tabla
function sortTable(columnIndex, header, event) {
    event.stopPropagation();

    const table = document.getElementById("routeTable");
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    const currentOrder = table.getAttribute('data-order') || 'default';
    
    let newOrder;
    if (currentOrder === 'default') {
        newOrder = 'asc';
    } else if (currentOrder === 'asc') {
        newOrder = 'desc';
    } else {
        newOrder = 'default';
    }

    resetTableHeader();
    header.classList.add('active-header');

    if (newOrder === 'default') {
        originalOrder.forEach(row => tbody.appendChild(row));
        header.setAttribute('aria-sort', 'none');
    } else {
        header.setAttribute('aria-sort', newOrder === 'asc' ? 'ascending' : 'descending');
        rows.sort((a, b) => {
            const cellA = a.cells[columnIndex].textContent.trim();
            const cellB = b.cells[columnIndex].textContent.trim();
            return newOrder === 'asc' ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
        });
        rows.forEach(row => tbody.appendChild(row));
    }

    table.setAttribute('data-order', newOrder);

    const searchInput = document.getElementById('search');
    const keyupEvent = new Event('keyup');
    searchInput.dispatchEvent(keyupEvent);
}

// Función para buscar rutas
function searchRoutes() {
    const filter = this.value.toLowerCase();
    const rows = Array.from(document.querySelectorAll('#routeTable tbody tr'));

    rows.forEach(row => {
        const textContent = row.textContent.toLowerCase();
        const shouldDisplay = textContent.includes(filter);
        row.style.display = shouldDisplay ? '' : 'none';
        
    });
}

// Inicialización cuando se carga el DOM
document.addEventListener('DOMContentLoaded', () => {
    saveOriginalOrder();
    ensureButtonsVisibility();
    setupClickOutsideListener();

    // Configurar el evento de búsqueda
    const searchInput = document.getElementById('search');
    searchInput.addEventListener('keyup', searchRoutes);
});
    </script>
</body>
</html>


