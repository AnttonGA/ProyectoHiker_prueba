<?php
ini_set('display_errors', '0');
error_reporting(0);
ob_start();

require_once __DIR__ . '/../../../includes/security.php';
secure_session_start();
header('Content-Type: application/json; charset=utf-8');

// Desactivar excepciones mysqli para usar el patrón safeQuery
mysqli_report(MYSQLI_REPORT_OFF);

function jsonError(string $msg, int $code = 500): void {
    ob_end_clean();
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../inicio_sesion/conexion.php';

if (!isset($_SESSION['usuario'])) {
    jsonError('Tu sesión ha caducado. Vuelve a entrar.', 401);
}

function safeQuery(mysqli $conn, string $sql, string $types = '', array $params = [], $default = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return $default;
    if ($types && $params) $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) { $stmt->close(); return $default; }
    $result = $stmt->get_result();
    $rows   = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows ?: $default;
}

function safeQueryOne(mysqli $conn, string $sql, string $types = '', array $params = [], $default = null) {
    $rows = safeQuery($conn, $sql, $types, $params, []);
    return $rows[0] ?? $default;
}

// ─── 1. Usuario ───────────────────────────────────────────────────────────────

$user = safeQueryOne($conn,
    "SELECT id, username, rol, ultimo_acceso FROM usuarios WHERE username = ? LIMIT 1",
    "s", [$_SESSION['usuario']]
);
if (!$user) jsonError('No se encontró tu cuenta. Sal y vuelve a entrar.', 403);
$userId = (int)$user['id'];

// ─── 2. Preferencias ─────────────────────────────────────────────────────────

$prefsRaw = safeQueryOne($conn,
    "SELECT nivel_experiencia, actividades_preferidas, ubicaciones_habituales,
            distancia_max_km, duracion_max_horas, preferencias_inferidas, ultima_actualizacion
     FROM user_preferences WHERE usuario_id = ? LIMIT 1",
    "i", [$userId]
);

$prefs = null;
if ($prefsRaw) {
    $actividades = json_decode($prefsRaw['actividades_preferidas'] ?? '[]', true) ?? [];
    $ubicaciones = json_decode($prefsRaw['ubicaciones_habituales'] ?? '[]', true) ?? [];
    $inferidas   = json_decode($prefsRaw['preferencias_inferidas'] ?? '{}', true) ?? [];
    $prefs = [
        'experience_level'      => $prefsRaw['nivel_experiencia']   ?? 'beginner',
        'preferred_activities'  => $actividades,
        'preferred_locations'   => $ubicaciones,
        'avg_preferred_distance' => round((float)($prefsRaw['distancia_max_km']  ?? 0), 1),
        'avg_preferred_duration' => round((float)($prefsRaw['duracion_max_horas'] ?? 0), 1),
        'total_interactions'    => (int)($inferidas['total_interactions'] ?? 0),
        'last_updated'          => $prefsRaw['ultima_actualizacion'] ?? null,
    ];
}

// ─── 3. Contadores ───────────────────────────────────────────────────────────

$totalRoutes    = (int)(safeQueryOne($conn,
    "SELECT COUNT(*) AS cnt FROM rutas WHERE usuario_id = ?",
    "i", [$userId], ['cnt' => 0])['cnt'] ?? 0);

$totalFavorites = (int)(safeQueryOne($conn,
    "SELECT COUNT(*) AS cnt FROM user_favorites WHERE usuario_id = ?",
    "i", [$userId], ['cnt' => 0])['cnt'] ?? 0);

$totalCompleted = (int)(safeQueryOne($conn,
    "SELECT COUNT(*) AS cnt FROM user_completed WHERE usuario_id = ?",
    "i", [$userId], ['cnt' => 0])['cnt'] ?? 0);

$totalInteract  = (int)(safeQueryOne($conn,
    "SELECT COUNT(*) AS cnt FROM user_interactions WHERE usuario_id = ?",
    "i", [$userId], ['cnt' => 0])['cnt'] ?? 0);

// ─── 4. Distribución de actividades ──────────────────────────────────────────

$activityBreakdown = safeQuery($conn,
    "SELECT r.tipo_ruta, COUNT(*) AS cnt
     FROM user_interactions ui
     JOIN rutas r ON ui.ruta_id = r.id
     WHERE ui.usuario_id = ?
     GROUP BY r.tipo_ruta ORDER BY cnt DESC LIMIT 8",
    "i", [$userId]
);

// ─── 5. Zonas más visitadas ───────────────────────────────────────────────────

$topZones = safeQuery($conn,
    "SELECT r.ubicacion AS location, COUNT(*) AS cnt
     FROM user_interactions ui
     JOIN rutas r ON ui.ruta_id = r.id
     WHERE ui.usuario_id = ?
     GROUP BY r.ubicacion ORDER BY cnt DESC LIMIT 5",
    "i", [$userId]
);

// ─── 6. Actividad mensual ────────────────────────────────────────────────────

$activityByMonth = safeQuery($conn,
    "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, COUNT(*) AS cnt
     FROM user_interactions
     WHERE usuario_id = ?
       AND fecha >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY mes ORDER BY mes ASC",
    "i", [$userId]
);

// Racha de días consecutivos
$diasRows = safeQuery($conn,
    "SELECT DATE(fecha) AS dia FROM user_interactions
     WHERE usuario_id = ?
     GROUP BY dia ORDER BY dia DESC LIMIT 30",
    "i", [$userId]
);
$streak = 0;
$today  = new DateTime('today');
foreach (array_column($diasRows, 'dia') as $idx => $dia) {
    $expected = (clone $today)->modify("-{$idx} days")->format('Y-m-d');
    if ($dia === $expected) $streak++;
    else break;
}

// ─── 7. Sesiones de chat ─────────────────────────────────────────────────────

$chatSessions = safeQuery($conn,
    "SELECT cs.id, cs.fecha_inicio, cs.ultimo_mensaje,
            COUNT(cm.id) AS num_mensajes
     FROM chat_sessions cs
     LEFT JOIN chat_messages cm ON cm.session_id = cs.id
     WHERE cs.usuario_id = ?
     GROUP BY cs.id, cs.fecha_inicio, cs.ultimo_mensaje
     ORDER BY cs.ultimo_mensaje DESC LIMIT 5",
    "i", [$userId]
);

// ─── Respuesta ────────────────────────────────────────────────────────────────

ob_end_clean();
echo json_encode([
    'success' => true,
    'user'    => [
        'id'            => $userId,
        'nombre'        => $user['username'],
        'usuario'       => $user['username'],
        'rol'           => $user['rol']          ?? 'usuario',
        'ultimo_acceso' => $user['ultimo_acceso'] ?? null,
    ],
    'preferences'       => $prefs,
    'stats' => [
        'total_routes'       => $totalRoutes,
        'total_favorites'    => $totalFavorites,
        'total_completed'    => $totalCompleted,
        'total_interactions' => $totalInteract,
        'streak_days'        => $streak,
    ],
    'activity_breakdown' => $activityBreakdown,
    'top_zones'          => $topZones,
    'activity_by_month'  => $activityByMonth,
    'chat_sessions'      => $chatSessions,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
