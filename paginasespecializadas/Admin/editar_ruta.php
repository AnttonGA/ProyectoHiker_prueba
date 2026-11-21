<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');

// Verificar si el usuario está logueado y es administrador
// TODO esquema: se comprueba el rol 'A' (esquema antiguo); database/schema.sql usa 'admin'.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'A') {
    header('Location: ../inicio_sesion/gestion.php');
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Identificador de ruta no válido.');
}

// TODO esquema: migrar a BD (tabla rutas). Se mantiene de momento la lógica sobre rutas.json.
$jsonFilePath = __DIR__ . '/../skitouring/javascript/rutas.json';
$rutas = is_readable($jsonFilePath) ? json_decode(file_get_contents($jsonFilePath), true) : [];
if (!is_array($rutas)) {
    $rutas = [];
}

// Buscar la ruta correspondiente
$rutaIndex = null;
foreach ($rutas as $index => $r) {
    if (is_array($r) && isset($r['id']) && (string) $r['id'] === (string) $id) {
        $rutaIndex = $index;
        break;
    }
}

if ($rutaIndex === null) {
    http_response_code(404);
    exit('Ruta no encontrada.');
}
$ruta = &$rutas[$rutaIndex];
if (!isset($ruta['imagenes']) || !is_array($ruta['imagenes'])) {
    $ruta['imagenes'] = [];
}

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();

    if (isset($_POST['update_route'])) {
        // Actualizar la ruta
        $ruta['title'] = (string) ($_POST['title'] ?? '');
        $ruta['description'] = (string) ($_POST['description'] ?? '');
        $ruta['elevation'] = (string) ($_POST['elevation'] ?? '');
        $ruta['duration'] = (string) ($_POST['duration'] ?? '');
        $ruta['difficulty'] = (string) ($_POST['difficulty'] ?? '');
        $ruta['length'] = (string) ($_POST['length'] ?? '');
        $ruta['tipo_ruta'] = (string) ($_POST['tipo_ruta'] ?? '');

        // Guardar cambios en el archivo JSON
        file_put_contents($jsonFilePath, json_encode($rutas, JSON_PRETTY_PRINT), LOCK_EX);
        header("Location: routes_management.php");
        exit();
    }

    if (isset($_POST['delete_image'])) {
        $imageToDelete = (string) ($_POST['image_to_delete'] ?? '');

        // Solo se permite borrar una imagen que figure en esta ruta...
        if ($imageToDelete === '' || !in_array($imageToDelete, $ruta['imagenes'], true)) {
            http_response_code(400);
            exit('Imagen no válida.');
        }

        // ...y cuyo fichero esté realmente dentro de skitouring/rutas_st (sin path traversal)
        $baseRutas = realpath(__DIR__ . '/../skitouring/rutas_st');
        $imagePath = realpath(__DIR__ . '/../skitouring/' . $imageToDelete);
        $dentroDeRutas = $baseRutas !== false && $imagePath !== false
            && strpos($imagePath, $baseRutas . DIRECTORY_SEPARATOR) === 0 && is_file($imagePath);

        if ($dentroDeRutas && unlink($imagePath)) {
            // Actualizar las imágenes de la ruta correspondiente
            $ruta['imagenes'] = array_values(array_filter($ruta['imagenes'], function ($img) use ($imageToDelete) {
                return $img !== $imageToDelete;
            }));

            // Guardar los cambios en el archivo JSON
            file_put_contents($jsonFilePath, json_encode($rutas, JSON_PRETTY_PRINT), LOCK_EX);

            // Redirigir después de eliminar
            header("Location: editar_ruta.php?id=" . $id);
            exit();
        }
        $errorMsg = 'No se pudo eliminar la imagen.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Ruta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
</head>
<body>
    
<?php include('navbar.php'); ?>
    <div class="container mt-5">
        <h1>Editar Ruta</h1>
        <?php if ($errorMsg !== ''): ?>
            <div class="alert alert-danger"><?php echo e($errorMsg); ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="title" class="form-label">Título</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo e($ruta['title'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Descripción</label>
                <textarea class="form-control" id="description" name="description" required><?php echo e($ruta['description'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="elevation" class="form-label">Elevación (m)</label>
                <input type="number" class="form-control" id="elevation" name="elevation" value="<?php echo e($ruta['elevation'] ?? ''); ?>" step="0.01" required>
            </div>
            <div class="mb-3">
                <label for="duration" class="form-label">Duración (horas)</label>
                <input type="number" class="form-control" id="duration" name="duration" value="<?php echo e($ruta['duration'] ?? ''); ?>" step="0.01" required>
            </div>
            <div class="mb-3">
                <label for="difficulty" class="form-label">Dificultad</label>
                <select class="form-select" id="difficulty" name="difficulty" required>
                    <option value="beginner" <?php if (($ruta['difficulty'] ?? '') == 'beginner') echo 'selected'; ?>>Principiante</option>
                    <option value="intermediate" <?php if (($ruta['difficulty'] ?? '') == 'intermediate') echo 'selected'; ?>>Intermedio</option>
                    <option value="advanced" <?php if (($ruta['difficulty'] ?? '') == 'advanced') echo 'selected'; ?>>Avanzado</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="length" class="form-label">Longitud (km)</label>
                <input type="number" class="form-control" id="length" name="length" value="<?php echo e($ruta['length'] ?? ''); ?>" step="0.01" required>
            </div>
            <div class="mb-3">
                <label for="tipo_ruta" class="form-label">Tipo de Ruta</label>
                <select class="form-select" id="tipo_ruta" name="tipo_ruta" required>
                    <option value="ski_touring" <?php if (($ruta['tipo_ruta'] ?? '') == 'ski_touring') echo 'selected'; ?>>Ski Touring</option>
                    <option value="cycle_touring" <?php if (($ruta['tipo_ruta'] ?? '') == 'cycle_touring') echo 'selected'; ?>>Cycle Touring</option>
                    <option value="hiking" <?php if (($ruta['tipo_ruta'] ?? '') == 'hiking') echo 'selected'; ?>>Hiking</option>
                </select>
            </div>
            <button type="submit" name="update_route" class="btn btn-success">Actualizar Ruta</button>
            <a href="routes_management.php" class="btn btn-secondary">Cancelar</a>
        </form>

        <hr>
        <h2>Imágenes Subidas</h2>
        <div class="row">
            <?php foreach ($ruta['imagenes'] as $imagen) : ?>
                <div class="col-md-4">
                    <img src="../skitouring/<?php echo e($imagen); ?>" class="img-thumbnail" alt="Imagen de Ruta">
                    <form method="POST" class="mt-2">
                        <input type="hidden" name="image_to_delete" value="<?php echo e($imagen); ?>">
                        <?= csrf_field() ?>
                        <button type="submit" name="delete_image" class="btn btn-danger btn-sm">Eliminar Imagen</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
   
</body>
</html>



