<?php
require_once '/../../inicio_sesion/conexion.php';

function updateUserPreferences($userId, $conn) {
    // Obtener todas las rutas con las que el usuario ha interactuado
    $query = "SELECT DISTINCT r.* FROM rutas r
              LEFT JOIN user_favorites uf ON r.id = uf.route_id
              LEFT JOIN user_interactions ui ON r.id = ui.route_id
              LEFT JOIN completed_routes cr ON r.id = cr.route_id
              WHERE uf.user_id = ? OR ui.user_id = ? OR cr.user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $userId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $totalRoutes = 0;
    $totalDistance = 0;
    $totalDuration = 0;
    $totalElevation = 0;
    $activities = [];
    $locations = [];
    $difficulties = [];

    while ($route = $result->fetch_assoc()) {
        $totalRoutes++;
        $totalDistance += $route['length'];
        $totalDuration += $route['duration'];
        $totalElevation += $route['elevation'];
        $activities[] = $route['tipo_ruta'];
        $locations[] = $route['location'];
        $difficulties[] = $route['difficulty'];
    }

    // Calcular promedios
    $avgDistance = $totalRoutes > 0 ? $totalDistance / $totalRoutes : 0;
    $avgDuration = $totalRoutes > 0 ? $totalDuration / $totalRoutes : 0;
    $avgElevation = $totalRoutes > 0 ? $totalElevation / $totalRoutes : 0;

    // Determinar actividad preferida
    $preferredActivities = array_count_values($activities);
    arsort($preferredActivities);
    $preferredActivity = key($preferredActivities);

    // Determinar ubicación preferida
    $preferredLocations = array_count_values($locations);
    arsort($preferredLocations);
    $preferredLocation = key($preferredLocations);

    // Determinar nivel de experiencia basado en dificultades
    $difficultyLevels = array_count_values($difficulties);
    arsort($difficultyLevels);
    $mostCommonDifficulty = key($difficultyLevels);

    $experienceLevel = 'beginner';
    if ($mostCommonDifficulty == 'advanced' || ($mostCommonDifficulty == 'intermediate' && $difficultyLevels['advanced'] > 0)) {
        $experienceLevel = 'advanced';
    } elseif ($mostCommonDifficulty == 'intermediate') {
        $experienceLevel = 'intermediate';
    }

    // Actualizar la tabla user_preferences
    $updateQuery = "INSERT INTO user_preferences 
                    (user_id, experience_level, preferred_activities, preferred_location, 
                     avg_preferred_distance, avg_preferred_duration, avg_preferred_elevation, last_updated) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                    experience_level = VALUES(experience_level),
                    preferred_activities = VALUES(preferred_activities),
                    preferred_location = VALUES(preferred_location),
                    avg_preferred_distance = VALUES(avg_preferred_distance),
                    avg_preferred_duration = VALUES(avg_preferred_duration),
                    avg_preferred_elevation = VALUES(avg_preferred_elevation),
                    last_updated = NOW()";

    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("isssddd", $userId, $experienceLevel, $preferredActivity, $preferredLocation, 
                      $avgDistance, $avgDuration, $avgElevation);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        logMessage("Preferencias actualizadas para el usuario $userId");
    } else {
        logMessage("No se pudieron actualizar las preferencias para el usuario $userId");
    }

    $stmt->close();
}

function getAllUsers($conn) {
    $users = [];
    $userQuery = "SELECT id FROM usuarios";
    $userResult = $conn->query($userQuery);
    while ($user = $userResult->fetch_assoc()) {
        $users[] = $user['id'];
    }
    return $users;
}

function logMessage($message) {
    $logFile = 'javascript/logs/user_preferences_update.log';
    $message = date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
    file_put_contents($logFile, $message, FILE_APPEND);
}

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }

    $users = getAllUsers($conn);
    foreach ($users as $userId) {
        updateUserPreferences($userId, $conn);
    }

    $conn->close();
    logMessage("Actualización de preferencias completada exitosamente.");
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
}
?>