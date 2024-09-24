<?php
include('../inicio_sesion/conexion.php');
session_start();

if (isset($_SESSION['rol']) && $_SESSION['rol'] == 'A') {

    $sql = "SELECT * FROM usuarios";
    $result = $conn->query($sql);
    $usuarios = $result->fetch_all(MYSQLI_ASSOC);
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

        <input type="text" id="search" class="form-control search-box" placeholder="Buscar usuarios...">

        <table class="table table-bordered table-striped mt-3" id="userTable" data-order="">
            <thead class="thead-dark">
                <tr>
                    <th onclick="sortTable(0, this, event)" aria-sort="none">ID</th>
                    <th onclick="sortTable(1, this, event)" aria-sort="none">Nombre</th>
                    <th onclick="sortTable(2, this, event)" aria-sort="none">Apellidos</th>
                    <th onclick="sortTable(3, this, event)" aria-sort="none">Mail</th>
                    <th onclick="sortTable(4, this, event)" aria-sort="none">Usuario</th>
                    <th onclick="sortTable(5, this, event)" aria-sort="none">Rol</th>
                    <th onclick="sortTable(6, this, event)" aria-sort="none">Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $row) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($row['apellidos']); ?></td>
                        <td><?php echo htmlspecialchars($row['mail']); ?></td>
                        <td><?php echo htmlspecialchars($row['usuario']); ?></td>
                        <td><?php echo htmlspecialchars($row['rol']); ?></td>
                        <td><?php echo htmlspecialchars($row['estado']); ?></td>
                        <td>
                            <a href="editar_usuario.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-primary btn-sm">Editar</a>
                            <a href="eliminar_usuario.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-danger btn-sm">Eliminar</a>
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
            const table = document.getElementById("userTable");
            originalOrder = Array.from(table.querySelectorAll('tbody tr'));
        }

        // Función para asegurar la visibilidad de los botones
        function ensureButtonsVisibility() {
            const rows = Array.from(document.querySelectorAll('#userTable tbody tr'));
            rows.forEach(row => {
                const actionsCell = row.querySelector('td:last-child');
                const id = row.querySelector('td:first-child').textContent;
                actionsCell.innerHTML = `
                    <a href="editar_usuario.php?id=${id}" class="btn btn-primary btn-sm d-inline-block">Editar</a>
                    <a href="eliminar_usuario.php?id=${id}" class="btn btn-danger btn-sm d-inline-block">Eliminar</a>
                `;
            });
        }

        // Función para configurar el listener de clic fuera de la tabla
        function setupClickOutsideListener() {
            document.addEventListener('click', function(event) {
                const table = document.getElementById("userTable");
                const isClickInsideTable = table.contains(event.target);
                
                if (!isClickInsideTable) {
                    resetTableHeader();
                }
            });
        }

        // Función para resetear el encabezado de la tabla
        function resetTableHeader() {
            const table = document.getElementById("userTable");
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
                    return newOrder === 'asc' ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
                });
                rows.forEach(row => tbody.appendChild(row));
            }

            table.setAttribute('data-order', newOrder);

            const searchInput = document.getElementById('search');
            const keyupEvent = new Event('keyup');
            searchInput.dispatchEvent(keyupEvent);
        }

        // Función para buscar usuarios
        function searchUsers() {
            const filter = this.value.toLowerCase();
            const rows = Array.from(document.querySelectorAll('#userTable tbody tr'));

            rows.forEach(row => {
                const textContent = row.textContent.toLowerCase();
                const shouldDisplay = textContent.includes(filter);
                row.style.display = shouldDisplay ? '' : 'none';
                
                if (shouldDisplay) {
                    const actionsCell = row.querySelector('td:last-child');
                    const id = row.querySelector('td:first-child').textContent;
                    actionsCell.innerHTML = `
                        <a href="editar_usuario.php?id=${id}" class="btn btn-primary btn-sm d-inline-block">Editar</a>
                        <a href="eliminar_usuario.php?id=${id}" class="btn btn-danger btn-sm d-inline-block">Eliminar</a>
                    `;
                }
            });
        }

        // Inicialización cuando se carga el DOM
        document.addEventListener('DOMContentLoaded', () => {
            saveOriginalOrder();
            ensureButtonsVisibility();
            setupClickOutsideListener();

            // Configurar el evento de búsqueda
            const searchInput = document.getElementById('search');
            searchInput.addEventListener('keyup', searchUsers);
        });
    </script>
</body>
</html>

<?php
} else {
    header('Location: ../inicio_sesion/gestion.php'); // Redirige al usuario a la página de inicio de sesión si no está autorizado
    exit();
}
?>

