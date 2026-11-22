<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
include('../inicio_sesion/conexion.php');

// Verificar si el usuario está logueado y es administrador
if (!is_admin()) {
    header('Location: ../inicio_sesion/gestion.php');
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identificador de ruta no válido.');
}

// Listas blancas = valores de los ENUM de la tabla rutas (valor => etiqueta)
$tiposValidos = [
    'ski_touring'   => 'Esquí de travesía',
    'hiking'        => 'Senderismo',
    'cycle_touring' => 'Cicloturismo',
    'climbing'      => 'Escalada',
    'snowshoeing'   => 'Raquetas',
    'via_ferrata'   => 'Vía ferrata',
    'btt'           => 'BTT',
    'kayaking'      => 'Kayak',
];
$ubicacionesValidas = [
    'Western_Pyrenees' => 'Pirineo occidental',
    'Central_Pyrenees' => 'Pirineo central',
    'Eastern_Pyrenees' => 'Pirineo oriental',
    'Other'            => 'Otras zonas',
];
$dificultadesValidas = [
    'beginner'     => 'Fácil',
    'intermediate' => 'Moderada',
    'advanced'     => 'Difícil',
    'expert'       => 'Experta',
];
$estadosValidos = [
    'publicada' => 'Publicada',
    'borrador'  => 'Borrador',
    'eliminada' => 'Eliminada',
];

function cargar_ruta(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare(
        "SELECT id, titulo, descripcion, tipo_ruta, ubicacion, dificultad,
                distancia_km, elevacion_ganada, duracion_horas, estado
           FROM rutas WHERE id = ?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

/**
 * Valida un número >= 0 (vacío = NULL). Devuelve [ok, valor].
 */
function numero_opcional(string $valor, float $max, bool $entero): array {
    $valor = trim(str_replace(',', '.', $valor));
    if ($valor === '') {
        return [true, null];
    }
    $num = $entero ? filter_var($valor, FILTER_VALIDATE_INT) : filter_var($valor, FILTER_VALIDATE_FLOAT);
    if ($num === false || $num < 0 || $num > $max) {
        return [false, null];
    }
    return [true, $entero ? (int) $num : round((float) $num, 2)];
}

try {
    $ruta = cargar_ruta($conn, $id);
} catch (Throwable $ex) {
    error_log('editar_ruta.php (select): ' . $ex->getMessage());
    http_response_code(500);
    exit('No se pudo cargar la ruta. Inténtalo de nuevo.');
}

if ($ruta === null) {
    http_response_code(404);
    exit('No existe ninguna ruta con ese identificador.');
}

$errores = [];
$okMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['update_route'])) {
        $titulo      = trim((string) ($_POST['titulo'] ?? ''));
        $descripcion = (string) ($_POST['descripcion'] ?? '');
        $tipoRuta    = (string) ($_POST['tipo_ruta'] ?? '');
        $ubicacion   = (string) ($_POST['ubicacion'] ?? '');
        $dificultad  = (string) ($_POST['dificultad'] ?? '');
        $estado      = (string) ($_POST['estado'] ?? '');

        [$okDist, $distancia] = numero_opcional((string) ($_POST['distancia_km'] ?? ''), 9999.99, false);
        [$okElev, $elevacion] = numero_opcional((string) ($_POST['elevacion_ganada'] ?? ''), 100000, true);
        [$okDur, $duracion]   = numero_opcional((string) ($_POST['duracion_horas'] ?? ''), 999.99, false);

        $lenTitulo = mb_strlen($titulo, 'UTF-8');
        if ($lenTitulo < 1 || $lenTitulo > 255) {
            $errores[] = 'El título es obligatorio (máximo 255 caracteres).';
        }
        if (mb_strlen($descripcion, 'UTF-8') > 65000) {
            $errores[] = 'La descripción es demasiado larga.';
        }
        if (!array_key_exists($tipoRuta, $tiposValidos)
            || !array_key_exists($ubicacion, $ubicacionesValidas)
            || !array_key_exists($dificultad, $dificultadesValidas)
            || !array_key_exists($estado, $estadosValidos)) {
            $errores[] = 'Algún valor de las listas no es válido. Revisa el formulario.';
        }
        if (!$okDist) {
            $errores[] = 'La distancia debe ser un número entre 0 y 9999,99.';
        }
        if (!$okElev) {
            $errores[] = 'El desnivel debe ser un número entero mayor o igual que 0.';
        }
        if (!$okDur) {
            $errores[] = 'La duración debe ser un número entre 0 y 999,99.';
        }

        if (!$errores) {
            try {
                $stmt = $conn->prepare(
                    // Si la descripción cambia, ya la ha escrito una persona: deja de ser automática.
                    // (MySQL aplica las asignaciones en orden: descripcion_auto se compara con la antigua)
                    "UPDATE rutas
                        SET descripcion_auto = IF(descripcion <=> ?, descripcion_auto, 0),
                            titulo = ?, descripcion = ?, tipo_ruta = ?, ubicacion = ?, dificultad = ?,
                            distancia_km = ?, elevacion_ganada = ?, duracion_horas = ?, estado = ?
                      WHERE id = ?"
                );
                $stmt->bind_param(
                    "ssssssdidsi",
                    $descripcion,
                    $titulo, $descripcion, $tipoRuta, $ubicacion, $dificultad,
                    $distancia, $elevacion, $duracion, $estado, $id
                );
                $stmt->execute();
                $stmt->close();
                $_SESSION['message'] = 'Ruta guardada.';
                header("Location: routes_management.php");
                exit();
            } catch (Throwable $ex) {
                error_log('editar_ruta.php (update): ' . $ex->getMessage());
                $errores[] = 'No se pudo guardar la ruta. Inténtalo de nuevo.';
            }
        }

        // Volver a mostrar lo enviado
        $ruta = array_merge($ruta, [
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'distancia_km' => $_POST['distancia_km'] ?? '',
            'elevacion_ganada' => $_POST['elevacion_ganada'] ?? '',
            'duracion_horas' => $_POST['duracion_horas'] ?? '',
        ]);
        foreach (['tipo_ruta' => $tiposValidos, 'ubicacion' => $ubicacionesValidas,
                  'dificultad' => $dificultadesValidas, 'estado' => $estadosValidos] as $campo => $lista) {
            $v = (string) ($_POST[$campo] ?? '');
            if (array_key_exists($v, $lista)) {
                $ruta[$campo] = $v;
            }
        }
    }

    if (isset($_POST['delete_image'])) {
        $imagenId = filter_input(INPUT_POST, 'image_id', FILTER_VALIDATE_INT);
        if (!$imagenId || $imagenId < 1) {
            http_response_code(400);
            exit('Imagen no válida.');
        }

        try {
            // Solo una imagen que pertenezca a ESTA ruta
            $stmt = $conn->prepare("SELECT imagen_path FROM ruta_imagenes WHERE id = ? AND ruta_id = ?");
            $stmt->bind_param("ii", $imagenId, $id);
            $stmt->execute();
            $img = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$img) {
                http_response_code(400);
                exit('Imagen no válida.');
            }

            // El fichero debe estar realmente dentro de skitouring/rutas_st (sin path traversal)
            $relativa  = (string) $img['imagen_path'];
            $baseRutas = realpath(__DIR__ . '/../skitouring/rutas_st');
            $imagePath = realpath(__DIR__ . '/../skitouring/' . $relativa);
            $dentroDeRutas = $baseRutas !== false && $imagePath !== false
                && strpos($imagePath, $baseRutas . DIRECTORY_SEPARATOR) === 0 && is_file($imagePath);
            // Si el fichero ya no existe, se permite limpiar el registro siempre que la ruta no intente salir del directorio
            $ficheroAusente = $imagePath === false
                && strpos(str_replace('\\', '/', $relativa), 'rutas_st/') === 0
                && strpos($relativa, '..') === false;

            if ($dentroDeRutas || $ficheroAusente) {
                $stmt = $conn->prepare("DELETE FROM ruta_imagenes WHERE id = ? AND ruta_id = ?");
                $stmt->bind_param("ii", $imagenId, $id);
                $stmt->execute();
                $stmt->close();

                if ($dentroDeRutas && !unlink($imagePath)) {
                    error_log('editar_ruta.php: no se pudo borrar el fichero de la imagen ' . $imagenId);
                }

                header("Location: editar_ruta.php?id=" . $id);
                exit();
            }
            error_log('editar_ruta.php: imagen ' . $imagenId . ' fuera de rutas_st');
            $errores[] = 'No se pudo eliminar la imagen.';
        } catch (Throwable $ex) {
            error_log('editar_ruta.php (delete_image): ' . $ex->getMessage());
            $errores[] = 'No se pudo eliminar la imagen.';
        }
    }
}

// Imágenes de la ruta
$imagenes = [];
try {
    $stmt = $conn->prepare("SELECT id, imagen_path FROM ruta_imagenes WHERE ruta_id = ? ORDER BY orden, id");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $imagenes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $ex) {
    error_log('editar_ruta.php (imagenes): ' . $ex->getMessage());
    $errores[] = 'No se pudieron cargar las imágenes.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar ruta · Admin · Kahir.ai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Sistema de diseño Kahir (después del CSS de la página) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="../../assets/css/kahir.css?v=20261122" rel="stylesheet">
</head>
<body>

<?php include('navbar.php'); ?>
    <div class="container mt-5">
        <h1>Editar ruta #<?php echo (int) $id; ?></h1>
        <?php if ($errores): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errores as $err): ?>
                        <li><?php echo e($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="POST" action="editar_ruta.php?id=<?php echo (int) $id; ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="titulo" class="form-label">Título</label>
                <input type="text" class="form-control" id="titulo" name="titulo" maxlength="255" value="<?php echo e($ruta['titulo']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción</label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="6"><?php echo e($ruta['descripcion'] ?? ''); ?></textarea>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="distancia_km" class="form-label">Distancia (km)</label>
                    <input type="number" class="form-control" id="distancia_km" name="distancia_km" value="<?php echo e($ruta['distancia_km'] ?? ''); ?>" step="0.01" min="0" max="9999.99">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="elevacion_ganada" class="form-label">Desnivel (m)</label>
                    <input type="number" class="form-control" id="elevacion_ganada" name="elevacion_ganada" value="<?php echo e($ruta['elevacion_ganada'] ?? ''); ?>" step="1" min="0">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="duracion_horas" class="form-label">Duración (horas)</label>
                    <input type="number" class="form-control" id="duracion_horas" name="duracion_horas" value="<?php echo e($ruta['duracion_horas'] ?? ''); ?>" step="0.01" min="0" max="999.99">
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="tipo_ruta" class="form-label">Actividad</label>
                    <select class="form-select" id="tipo_ruta" name="tipo_ruta" required>
                        <?php foreach ($tiposValidos as $valor => $etiqueta): ?>
                            <option value="<?php echo e($valor); ?>" <?php if ($ruta['tipo_ruta'] === $valor) echo 'selected'; ?>><?php echo e($etiqueta); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="ubicacion" class="form-label">Zona</label>
                    <select class="form-select" id="ubicacion" name="ubicacion" required>
                        <?php foreach ($ubicacionesValidas as $valor => $etiqueta): ?>
                            <option value="<?php echo e($valor); ?>" <?php if ($ruta['ubicacion'] === $valor) echo 'selected'; ?>><?php echo e($etiqueta); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="dificultad" class="form-label">Dificultad</label>
                    <select class="form-select" id="dificultad" name="dificultad" required>
                        <?php foreach ($dificultadesValidas as $valor => $etiqueta): ?>
                            <option value="<?php echo e($valor); ?>" <?php if ($ruta['dificultad'] === $valor) echo 'selected'; ?>><?php echo e($etiqueta); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado" required>
                        <?php foreach ($estadosValidos as $valor => $etiqueta): ?>
                            <option value="<?php echo e($valor); ?>" <?php if ($ruta['estado'] === $valor) echo 'selected'; ?>><?php echo e($etiqueta); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" name="update_route" value="1" class="btn btn-success">Guardar</button>
            <a href="routes_management.php" class="btn btn-secondary">Cancelar</a>
            <a href="../skitouring/ruta.html?id=<?php echo (int) $id; ?>" class="btn btn-outline-info" target="_blank" rel="noopener">Ver ruta</a>
        </form>

        <hr>
        <h2>Imágenes</h2>
        <?php if (!$imagenes): ?>
            <p class="text-muted">Esta ruta no tiene imágenes.</p>
        <?php endif; ?>
        <div class="row">
            <?php foreach ($imagenes as $imagen) : ?>
                <div class="col-md-4 mb-3">
                    <img src="../skitouring/<?php echo e($imagen['imagen_path']); ?>" class="img-thumbnail" alt="Imagen de la ruta">
                    <form method="POST" action="editar_ruta.php?id=<?php echo (int) $id; ?>" class="mt-2" onsubmit="return confirm('¿Eliminar esta imagen? Se borrará el archivo.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="image_id" value="<?php echo (int) $imagen['id']; ?>">
                        <button type="submit" name="delete_image" value="1" class="btn btn-danger btn-sm">Eliminar imagen</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
