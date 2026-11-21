<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');

if (!is_admin()) {
    header('Location: ../inicio_sesion/gestion.php'); // Redirige al usuario a la página de inicio de sesión si no está autorizado
    exit();
}

$miId = (int) ($_SESSION['id'] ?? 0);
$usuarios = [];
$errorMsg = '';

try {
    $stmt = $conn->prepare(
        "SELECT id, username, email, rol, activo, email_verificado, fecha_registro, ultimo_acceso
           FROM usuarios
          ORDER BY id"
    );
    $stmt->execute();
    $usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $ex) {
    error_log('user_management.php: ' . $ex->getMessage());
    $errorMsg = 'No se pudo cargar el listado de usuarios.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
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
        <h1>Gestión de Usuarios</h1>
        <?php if (!empty($_SESSION['message'])): ?>
            <div class="alert alert-info"><?php echo e($_SESSION['message']); ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        <?php if ($errorMsg !== ''): ?>
            <div class="alert alert-danger"><?php echo e($errorMsg); ?></div>
        <?php endif; ?>

        <input type="text" id="search" class="form-control search-box" placeholder="Buscar usuarios...">

        <div class="table-responsive">
        <table class="table table-bordered table-striped mt-3" id="userTable" data-order="">
            <thead class="thead-dark">
                <tr>
                    <th onclick="sortTable(0, this, event)" aria-sort="none">ID</th>
                    <th onclick="sortTable(1, this, event)" aria-sort="none">Usuario</th>
                    <th onclick="sortTable(2, this, event)" aria-sort="none">Email</th>
                    <th onclick="sortTable(3, this, event)" aria-sort="none">Rol</th>
                    <th onclick="sortTable(4, this, event)" aria-sort="none">Activo</th>
                    <th onclick="sortTable(5, this, event)" aria-sort="none">Email verificado</th>
                    <th onclick="sortTable(6, this, event)" aria-sort="none">Registro</th>
                    <th onclick="sortTable(7, this, event)" aria-sort="none">Último acceso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $row) { $rowId = (int) $row['id']; ?>
                    <tr>
                        <td><?php echo $rowId; ?></td>
                        <td><?php echo e($row['username']); ?></td>
                        <td><?php echo e($row['email']); ?></td>
                        <td><?php echo e($row['rol']); ?></td>
                        <td><?php echo e((int) $row['activo'] === 1 ? 'Sí' : 'No'); ?></td>
                        <td><?php echo e((int) $row['email_verificado'] === 1 ? 'Sí' : 'No'); ?></td>
                        <td><?php echo e($row['fecha_registro'] ?? ''); ?></td>
                        <td><?php echo e($row['ultimo_acceso'] ?? '—'); ?></td>
                        <td>
                            <a href="editar_usuario.php?id=<?php echo $rowId; ?>" class="btn btn-primary btn-sm">Editar</a>
                            <?php if ($rowId !== $miId): ?>
                            <form method="POST" action="eliminar_usuario.php" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario? Esta acción no se puede deshacer.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?php echo $rowId; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Guardamos el orden original de las filas
        let originalOrder;

        // Función para guardar el orden original
        function saveOriginalOrder() {
            const table = document.getElementById("userTable");
            originalOrder = Array.from(table.querySelectorAll('tbody tr'));
        }

        // Función para asegurar la visibilidad de los botones
        function ensureButtonsVisibility() {
            const rows = Array.from(document.querySelectorAll('#userTable tbody tr'));
            // Los botones se generan en el servidor (eliminar es un formulario POST con token CSRF); no se reescriben con innerHTML.
            rows.forEach(row => {
                row.querySelectorAll('td:last-child .btn').forEach(btn => btn.classList.add('d-inline-block'));
            });
        }

        // Función para configurar el listener de clic fuera de la tabla
        function setupClickOutsideListener() {
            document.addEventListener('click', function(event) {
                const table = document.getElementById("userTable");
                if (!table.contains(event.target)) {
                    resetTableHeader();
                }
            });
        }

        // Función para resetear el encabezado de la tabla
        function resetTableHeader() {
            const table = document.getElementById("userTable");
            table.querySelectorAll('th').forEach(header => {
                header.classList.remove('active-header');
                header.setAttribute('aria-sort', 'none');
            });
            table.setAttribute('data-order', 'default');
        }

        // Función para ordenar la tabla
        function sortTable(columnIndex, header, event) {
            event.stopPropagation();

            const table = document.getElementById("userTable");
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
                    const cmp = cellA.localeCompare(cellB, undefined, { numeric: true });
                    return newOrder === 'asc' ? cmp : -cmp;
                });
                rows.forEach(row => tbody.appendChild(row));
            }

            table.setAttribute('data-order', newOrder);
            document.getElementById('search').dispatchEvent(new Event('keyup'));
        }

        // Función para buscar usuarios (filtro en cliente)
        function searchUsers() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#userTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        }

        // Inicialización cuando se carga el DOM
        document.addEventListener('DOMContentLoaded', () => {
            saveOriginalOrder();
            ensureButtonsVisibility();
            setupClickOutsideListener();
            document.getElementById('search').addEventListener('keyup', searchUsers);
        });
    </script>
</body>
</html>
