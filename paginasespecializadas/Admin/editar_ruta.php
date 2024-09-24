<?php
include('../inicio_sesion/conexion.php');

// Verificar si el usuario está logueado y es administrador
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'A') {
    header('Location: ../inicio_sesion/gestion.php');
    exit();
}

$id = $_GET['id'];
$jsonFilePath = '../skitouring/javascript/rutas.json';

// Cargar el archivo JSON
$rutas = json_decode(file_get_contents($jsonFilePath), true);

// Buscar la ruta correspondiente
$ruta = null;
foreach ($rutas as &$r) {
    if ($r['id'] == $id) {
        $ruta = &$r;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_route'])) {
        // Actualizar la ruta
        $ruta['title'] = $_POST['title'];
        $ruta['description'] = $_POST['description'];
        $ruta['elevation'] = $_POST['elevation'];
        $ruta['duration'] = $_POST['duration'];
        $ruta['difficulty'] = $_POST['difficulty'];
        $ruta['length'] = $_POST['length'];
        $ruta['tipo_ruta'] = $_POST['tipo_ruta'];

        // Guardar cambios en el archivo JSON
        file_put_contents($jsonFilePath, json_encode($rutas, JSON_PRETTY_PRINT));
        header("Location: routes_management.php");
        exit();
    }

    if (isset($_POST['delete_image'])) {
        $imageToDelete = $_POST['image_to_delete'];
        $imagePath = '../skitouring/' . $imageToDelete;

        // Verificar la ruta y el archivo antes de eliminar
        if (file_exists($imagePath)) {
            // Eliminar la imagen del sistema
            if (unlink($imagePath)) {
                // Actualizar las imágenes de la ruta correspondiente
                $ruta['imagenes'] = array_values(array_filter($ruta['imagenes'], function ($img) use ($imageToDelete) {
                    return $img !== $imageToDelete;
                }));

                // Guardar los cambios en el archivo JSON
                file_put_contents($jsonFilePath, json_encode($rutas, JSON_PRETTY_PRINT));

                // Redirigir después de eliminar
                header("Location: editar_ruta.php?id=" . $id);
                exit();
            } else {
                echo "No se pudo eliminar el archivo.";
            }
        } else {
            echo "El archivo no existe: " . $imagePath;
        }
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
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="title" class="form-label">Título</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo $ruta['title']; ?>" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Descripción</label>
                <textarea class="form-control" id="description" name="description" required><?php echo $ruta['description']; ?></textarea>
            </div>
            <div class="mb-3">
                <label for="elevation" class="form-label">Elevación (m)</label>
                <input type="number" class="form-control" id="elevation" name="elevation" value="<?php echo $ruta['elevation']; ?>" step="0.01" required>
            </div>
            <div class="mb-3">
                <label for="duration" class="form-label">Duración (horas)</label>
                <input type="number" class="form-control" id="duration" name="duration" value="<?php echo $ruta['duration']; ?>" step="0.01" required>
            </div>
            <div class="mb-3">
                <label for="difficulty" class="form-label">Dificultad</label>
                <select class="form-select" id="difficulty" name="difficulty" required>
                    <option value="beginner" <?php if ($ruta['difficulty'] == 'beginner') echo 'selected'; ?>>Principiante</option>
                    <option value="intermediate" <?php if ($ruta['difficulty'] == 'intermediate') echo 'selected'; ?>>Intermedio</option>
                    <option value="advanced" <?php if ($ruta['difficulty'] == 'advanced') echo 'selected'; ?>>Avanzado</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="length" class="form-label">Longitud (km)</label>
                <input type="number" class="form-control" id="length" name="length" value="<?php echo $ruta['length']; ?>" step="0.01" required>
            </div>
            <div class="mb-3">
                <label for="tipo_ruta" class="form-label">Tipo de Ruta</label>
                <select class="form-select" id="tipo_ruta" name="tipo_ruta" required>
                    <option value="ski_touring" <?php if ($ruta['tipo_ruta'] == 'ski_touring') echo 'selected'; ?>>Ski Touring</option>
                    <option value="cycle_touring" <?php if ($ruta['tipo_ruta'] == 'cycle_touring') echo 'selected'; ?>>Cycle Touring</option>
                    <option value="hiking" <?php if ($ruta['tipo_ruta'] == 'hiking') echo 'selected'; ?>>Hiking</option>
                </select>
            </div>
            <button type="submit" name="update_route" class="btn btn-success">Actualizar Ruta</button>
            <a href="my_routes.php" class="btn btn-secondary">Cancelar</a>
        </form>

        <hr>
        <h2>Imágenes Subidas</h2>
        <div class="row">
            <?php foreach ($ruta['imagenes'] as $imagen) : ?>
                <div class="col-md-4">
                    <img src="../skitouring/<?php echo $imagen; ?>" class="img-thumbnail" alt="Imagen de Ruta">
                    <form method="POST" class="mt-2">
                        <input type="hidden" name="image_to_delete" value="<?php echo $imagen; ?>">
                        <button type="submit" name="delete_image" class="btn btn-danger btn-sm">Eliminar Imagen</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
   
</body>
</html>



