<?php
session_start();
require '../inicio_sesion/config.php';
require '../inicio_sesion/conexion.php'; // Asegúrate de que la ruta a este archivo sea correcta

if (isset($_SESSION['usuario'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validación de campos obligatorios
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $tipoRuta = $_POST['tipo_ruta'];
        $locationRuta = $_POST['location_ruta'];
        $dificultad = $_POST['dificultad'];
        $pregunta1 = trim($_POST['pregunta1']);
        $pregunta2 = trim($_POST['pregunta2']);
        $pregunta3 = trim($_POST['pregunta3']);
        $pregunta4 = trim($_POST['pregunta4']);

        $campos = [
            $nombre,
            $descripcion,
            $tipoRuta,
            $locationRuta,
            $dificultad,
            $pregunta1,
            $pregunta2,
            $pregunta3,
            $pregunta4
        ];

        foreach ($campos as $campo) {
            if (empty($campo)) {
                die('Por favor completa todos los campos.');
            }
        }


        // Definición de directorios y rutas
        $baseDir = __DIR__ . '/../../paginasespecializadas/';
        $tipoRutaFolder = 'skitouring/rutas_st/';

        // Crear carpeta para la ruta
        $rutaFolderName = strtolower(str_replace(' ', '-', $nombre)) . '-' . time();
        $rutaFolderPath = $baseDir . $tipoRutaFolder . $rutaFolderName;

        if (!mkdir($rutaFolderPath, 0777, true)) {
            die('Fallo al crear las carpetas.');
        }

        // Manejo del archivo GPX
        $gpxFilePath = '';
        $gpxData = [];
        if (isset($_FILES['archivo_gpx']) && $_FILES['archivo_gpx']['error'] == UPLOAD_ERR_OK) {
            $gpxFile = $_FILES['archivo_gpx'];
            $gpxFilePath = $rutaFolderPath . '/' . basename($gpxFile['name']);
            if (!move_uploaded_file($gpxFile['tmp_name'], $gpxFilePath)) {
                die('Error al mover el archivo GPX.');
            }
            $gpxData = parseGPX($gpxFilePath);
        } else {
            die('Error en la carga del archivo GPX.');
        }

        // Manejo de imágenes
        $imagenesPaths = [];
        if (isset($_FILES['imagenes']) && !empty($_FILES['imagenes']['name'][0])) {
            foreach ($_FILES['imagenes']['name'] as $key => $imageName) {
                if ($_FILES['imagenes']['error'][$key] == UPLOAD_ERR_OK) {
                    $imageTmpName = $_FILES['imagenes']['tmp_name'][$key];
                    $imagePath = $rutaFolderPath . '/' . basename($imageName);
                    if (move_uploaded_file($imageTmpName, $imagePath)) {
                        $imagenesPaths[] = 'rutas_st/' . $rutaFolderName . '/' . basename($imageName);
                    } else {
                        die('Error al mover la imagen.');
                    }
                } else {
                    die('Error en la carga de una de las imágenes.');
                }
            }
        } else {
            die('No se subieron imágenes.');
        }

        // Insertar la nueva ruta en la tabla 'rutas'
        $elevation = isset($gpxData['elevation']) ? $gpxData['elevation'] : null;
        $duration = isset($gpxData['duration']) ? $gpxData['duration'] : null;
        $length = isset($gpxData['length']) ? $gpxData['length'] : null;
        $startLat = isset($gpxData['startLat']) ? $gpxData['startLat'] : null;
        $startLng = isset($gpxData['startLng']) ? $gpxData['startLng'] : null;
        $startElevation = isset($gpxData['startElevation']) ? $gpxData['startElevation'] : null;

        $query = "INSERT INTO rutas (usuario, filename, title, description, elevation, duration, difficulty, location, length, startLat, startLng, startElevation, tipo_ruta, ruta_carpeta, pregunta1, pregunta2, pregunta3, pregunta4) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sssssdssddddssssss", 
            $_SESSION['usuario'],
            basename($gpxFilePath),
            $nombre,
            $descripcion,
            $elevation,
            $duration,
            $dificultad,
            $locationRuta,
            $length,
            $startLat,
            $startLng,
            $startElevation,
            $tipoRuta,
            $rutaFolderName,
            $pregunta1,
            $pregunta2,
            $pregunta3,
            $pregunta4
        );

        if (!mysqli_stmt_execute($stmt)) {
            die("Error al insertar la ruta: " . mysqli_error($conn));
        }

        $ruta_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // Insertar las imágenes en la tabla 'ruta_imagenes'
        $query = "INSERT INTO ruta_imagenes (ruta_id, imagen_path) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        foreach ($imagenesPaths as $imagePath) {
            mysqli_stmt_bind_param($stmt, "is", $ruta_id, $imagePath);
            if (!mysqli_stmt_execute($stmt)) {
                die("Error al insertar la imagen: " . mysqli_error($conn));
            }
        }
        mysqli_stmt_close($stmt);

        mysqli_close($conn);

        header('Location: subir_ruta.html');
        exit;
    } else {
        die('Método no permitido.');
    }
} else {
    header('Location: gestion.php');
    exit;
}

// Función para analizar el archivo GPX
function parseGPX($filePath) {
    $xml = simplexml_load_file($filePath);
    $namespaces = $xml->getNamespaces(true);
    $xml->registerXPathNamespace('ns', $namespaces['']);

    $totalAscent = 0;
    $length = 0;
    $duration = 0;
    $startLat = 'N/A';
    $startLng = 'N/A';
    $startElevation = 'N/A';

    if (isset($xml->trk->trkseg->trkpt)) {
        $points = $xml->trk->trkseg->trkpt;
        $validPoints = [];

        foreach ($points as $point) {
            if (isset($point->time)) {
                $validPoints[] = $point;
            }
        }

        if (count($validPoints) > 0) {
            $startLat = (string) $validPoints[0]['lat'];
            $startLng = (string) $validPoints[0]['lon'];
            $startElevation = (string) $validPoints[0]->ele;

            $previousPoint = null;
            $startTime = new DateTime((string) $validPoints[0]->time);
            $endTime = new DateTime((string) end($validPoints)->time);
            $duration = $startTime->diff($endTime)->h + ($startTime->diff($endTime)->days * 24);

            foreach ($validPoints as $point) {
                if ($previousPoint) {
                    $distance = haversineGreatCircleDistance(
                        (float) $previousPoint['lat'],
                        (float) $previousPoint['lon'],
                        (float) $point['lat'],
                        (float) $point['lon']
                    );
                    $length += $distance;

                    $elevationDifference = (float) $point->ele - (float) $previousPoint->ele;
                    if ($elevationDifference > 0) {
                        $totalAscent += $elevationDifference;
                    }
                }
                $previousPoint = $point;
            }
        } else {
            die('El archivo GPX no contiene puntos válidos con marca de tiempo.');
        }
    }

    return [
        'elevation' => round($totalAscent, 1),
        'length' => round($length, 2), 
        'duration' => $duration, 
        'startLat' => $startLat,
        'startLng' => $startLng,
        'startElevation' => $startElevation
    ];
}

// Función para calcular la distancia entre dos puntos geográficos
function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371) {
    $latFrom = deg2rad($latitudeFrom);
    $lonFrom = deg2rad($longitudeFrom);
    $latTo = deg2rad($latitudeTo);
    $lonTo = deg2rad($longitudeTo);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return $angle * $earthRadius * 1.05; // 1.05 is a factor to account for some inaccuracies
}
?>