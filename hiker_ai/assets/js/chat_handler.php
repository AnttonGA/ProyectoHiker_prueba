<?php
/**
 * chat_handler.php — Endpoint HTTP del chat de Kahir.AI
 *
 * Recibe POST JSON desde chat.js, coordina ChatManager + AICore
 * y devuelve JSON con el formato que espera el frontend.
 *
 * URL (relativa a la raíz del proyecto):  hiker_ai/assets/js/chat_handler.php
 */

declare(strict_types=1);

// ── Primero de todo: silenciar errores PHP para que nunca contaminen el JSON ──
ini_set('display_errors', '0');
error_reporting(0);
ob_start();          // Captura cualquier output accidental (notices, warnings HTML)

// ─── Bootstrap ───────────────────────────────────────────────────────────────

// Resolver rutas absolutas desde este fichero
$hiker_ai_dir   = dirname(__DIR__, 2);              // …/hiker_ai
$project_root   = dirname(__DIR__, 3);              // …/ProyectoHiker_seguridad

require_once $project_root . '/includes/env_loader.php';
load_env($project_root . '/.env');

require_once $hiker_ai_dir . '/config/config.php';   // define constantes globales (incluye RATE_LIMIT_*, SESSION_LIFETIME, etc.)
require_once $hiker_ai_dir . '/config/database.php'; // define DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_CHARSET desde .env
// NOTA: config.php reactiva display_errors en modo desarrollo; forzamos de nuevo silencio:
ini_set('display_errors', '0');

require_once $project_root . '/vendor/autoload.php'; // PSR-4: KahirAI\ → hiker_ai/
require_once $project_root . '/includes/security.php'; // sesión segura + CSRF

use KahirAI\Core\{AICore, ChatManager, RouteRecommender};
use KahirAI\Database\DBConnection;
use KahirAI\Services\LoggerService;

// ─── Cabeceras ────────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ─── Sesión ───────────────────────────────────────────────────────────────────

// Cookie HttpOnly + SameSite=Lax (y Secure si la petición llega por HTTPS)
secure_session_start();

// ─── Logger para este endpoint ────────────────────────────────────────────────

$logger = new LoggerService('chat_handler');

// ─── Función de respuesta JSON ────────────────────────────────────────────────

function jsonResponse(array $data, int $httpCode = 200): void
{
    ob_end_clean();   // descartar cualquier output previo (errores PHP, etc.)
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Capturar errores fatales no catcheables y devolverlos como JSON
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        // El detalle solo va al log, nunca al cliente
        error_log('chat_handler.php fatal: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'type'    => 'error',
            'message' => 'Error interno del servidor. Inténtalo de nuevo.',
        ], JSON_UNESCAPED_UNICODE);
    }
});

// ─── Autenticación ────────────────────────────────────────────────────────────

if (empty($_SESSION['usuario'])) {
    jsonResponse([
        'success' => false,
        'type'    => 'error',
        'message' => 'Debes iniciar sesión para usar el asistente.',
    ], 401);
}

// ─── Solo POST ────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'type' => 'error', 'message' => 'Método no permitido.'], 405);
}

// ─── CSRF (cabecera X-CSRF-Token que añade csrf.js) ──────────────────────────

if (!csrf_is_valid()) {
    jsonResponse([
        'success' => false,
        'type'    => 'error',
        'message' => 'La sesión ha caducado. Recarga la página e inténtalo de nuevo.',
    ], 403);
}

// ─── Leer y validar entrada ───────────────────────────────────────────────────

$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput, true);

if (!is_array($input) || empty(trim($input['message'] ?? ''))) {
    jsonResponse(['success' => false, 'type' => 'error', 'message' => 'Mensaje vacío o formato inválido.'], 400);
}

$userMessage = trim($input['message']);

// Sanitizar: máximo 500 caracteres
if (mb_strlen($userMessage) > 500) {
    $userMessage = mb_substr($userMessage, 0, 500);
}

// ─── Conexión a la base de datos ──────────────────────────────────────────────

try {
    $conn = DBConnection::getInstance()->getConnection();
} catch (\Throwable $e) {
    $logger->error('Error de conexión a BD', ['error' => $e->getMessage()]);
    error_log('chat_handler.php BD: ' . $e->getMessage());
    jsonResponse(['success' => false, 'type' => 'error', 'message' => 'El asistente no está disponible en este momento. Inténtalo más tarde.'], 500);
}

// ─── Resolver ID numérico del usuario ────────────────────────────────────────

$sessionUser = $_SESSION['usuario'];
$userIdInt   = null;

try {
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $sessionUser);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (\Throwable $e) {
    $logger->error('Error buscando usuario', ['error' => $e->getMessage()]);
    error_log('chat_handler.php usuario: ' . $e->getMessage());
    jsonResponse(['success' => false, 'type' => 'error', 'message' => 'Error al verificar el usuario.'], 500);
}

if (!$row) {
    jsonResponse(['success' => false, 'type' => 'error', 'message' => 'Usuario no encontrado.'], 403);
}
$userIdInt = (int)$row['id'];

// ─── Rate limiting: contar mensajes de chat del usuario en la última hora ────
// Se usa chat_messages (unido a chat_sessions) para contar solo mensajes de chat,
// no interacciones con rutas. Límites: freemium=30, premium=100 por hora.

$userRole   = $_SESSION['rol'] ?? 'freemium';
$rateLimit  = ($userRole === 'premium') ? 100 : 30;

try {
    $stmtRate = $conn->prepare(
        "SELECT COUNT(*) AS cnt
         FROM chat_messages cm
         JOIN chat_sessions cs ON cm.session_id = cs.id
         WHERE cs.usuario_id = ?
           AND cm.rol     = 'user'
           AND cm.fecha   > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $stmtRate->bind_param("i", $userIdInt);
    $stmtRate->execute();
    $rateRow = $stmtRate->get_result()->fetch_assoc();
    $stmtRate->close();

    if (($rateRow['cnt'] ?? 0) >= $rateLimit) {
        jsonResponse([
            'success' => false,
            'type'    => 'error',
            'message' => "Has alcanzado el límite de {$rateLimit} mensajes por hora. Inténtalo en un rato.",
        ], 429);
    }
} catch (\Throwable $e) {
    // Si la consulta falla NO se deja pasar sin límite: se registra el error y se aplica
    // un límite de respaldo por sesión (mismo número de mensajes por hora).
    $logger->error('Rate limiting por BD no disponible; se usa el límite por sesión', ['error' => $e->getMessage()]);
    error_log('chat_handler.php rate limit: ' . $e->getMessage());

    $ahora    = time();
    $marcas   = array_values(array_filter(
        (array)($_SESSION['chat_rate_fallback'] ?? []),
        static fn($t) => is_int($t) && $t > $ahora - 3600
    ));
    if (count($marcas) >= $rateLimit) {
        jsonResponse([
            'success' => false,
            'type'    => 'error',
            'message' => "Has alcanzado el límite de {$rateLimit} mensajes por hora. Inténtalo en un rato.",
        ], 429);
    }
    $marcas[] = $ahora;
    $_SESSION['chat_rate_fallback'] = $marcas;
}

// ─── Instanciar ChatManager y AICore ─────────────────────────────────────────

try {
    $chatManager = new ChatManager();
    $aiCore      = new AICore($conn);
} catch (\Throwable $e) {
    $logger->error('Error instanciando servicios AI', ['error' => $e->getMessage()]);
    error_log('chat_handler.php init: ' . $e->getMessage());

    // Detectar si es el error de API key no configurada (el detalle queda en el log)
    $errMsg = $e->getMessage();
    if (stripos($errMsg, 'API key') !== false || stripos($errMsg, 'API_KEY') !== false) {
        jsonResponse([
            'success' => false,
            'type'    => 'error',
            'message' => 'El asistente IA no está disponible en este momento. Inténtalo más tarde.',
        ], 503);
    }

    jsonResponse(['success' => false, 'type' => 'error', 'message' => 'No se ha podido iniciar el asistente. Inténtalo más tarde.'], 500);
}

// ─── Sesión de chat persistente ───────────────────────────────────────────────

try {
    // Usar el chat_session_id almacenado en la sesión PHP; si no existe, crear uno.
    // getOrCreateChatSession() reutiliza la sesión activa de las últimas 2 h.
    $chatSessionId = (int)($_SESSION['chat_session_id'] ?? 0);

    if (!$chatSessionId) {
        $chatSessionId = $aiCore->getOrCreateChatSession($userIdInt);
        $_SESSION['chat_session_id'] = $chatSessionId;
    }

    // Historial persistente de la sesión actual (últimos 10 mensajes)
    $history = $aiCore->getChatHistory($chatSessionId, 10);

} catch (\Exception $e) {
    $logger->error('Error gestionando sesión de chat', ['error' => $e->getMessage()]);
    // No es fatal; seguimos sin historial
    $chatSessionId = 0;
    $history       = [];
}

// ─── ChatManager: gestión de clarificaciones ─────────────────────────────────

try {
    $chatResult = $chatManager->processMessage($userMessage);
} catch (\Throwable $e) {
    $logger->error('Error en ChatManager', ['error' => $e->getMessage()]);
    error_log('chat_handler.php ChatManager: ' . $e->getMessage());
    jsonResponse(['success' => false, 'type' => 'error', 'message' => 'Error al procesar tu mensaje.'], 500);
}

// Gestionar los tipos de retorno de ChatManager
switch ($chatResult['type']) {

    case 'invalid':
        // Mensaje rechazado (spam, vacío...)
        jsonResponse([
            'success'    => true,
            'type'       => 'text',
            'message'    => $chatResult['content'],
            'session_id' => $chatSessionId,
            'context'    => [],
        ]);
        break;  // nunca se alcanza (jsonResponse hace exit), pero clarifica el flujo

    case 'greeting':
        // Saludo puro — respuesta inmediata sin gastar tokens de Claude
        jsonResponse([
            'success'    => true,
            'type'       => 'text',
            'message'    => $chatResult['content'],
            'session_id' => $chatSessionId,
            'suggestions'=> [
                'Buscar rutas cerca de Candanchú',
                '¿Qué tiempo hace hoy en Formigal?',
                'Recomendaciones para principiantes',
            ],
            'context'    => [],
        ]);
        break;

    case 'ready':
    default:
        // Continuar con AiCore (flujo normal)
        break;
}

// ─── AICore: procesamiento real ───────────────────────────────────────────────

try {
    $aiResponse = $aiCore->processQuery($userMessage, $sessionUser, $history, $chatSessionId);
} catch (\Throwable $e) {
    error_log('chat_handler.php processQuery: ' . $e->getMessage());
    $logger->error('Error en AICore::processQuery', [
        'userId' => $userIdInt,
        'error'  => $e->getMessage(),
    ]);
    jsonResponse([
        'success' => false,
        'type'    => 'error',
        'message' => 'El asistente no pudo procesar tu consulta. Inténtalo de nuevo.',
    ], 500);
}

// ─── Guardar intercambio en BD ────────────────────────────────────────────────

if ($chatSessionId > 0) {
    try {
        $assistantText = $aiResponse['content'] ?? '';
        if ($assistantText) {
            $aiCore->saveChatExchange($chatSessionId, $userMessage, $assistantText);
        }
    } catch (\Exception $e) {
        // No es fatal
        $logger->warning('No se pudo guardar el intercambio', ['error' => $e->getMessage()]);
    }
}

// ─── Normalizar la respuesta para el frontend ─────────────────────────────────

$responseType    = $aiResponse['type']    ?? 'general';
$responseContent = $aiResponse['content'] ?? '';

// Mapear tipos internos al vocabulario del frontend
$typeMap = [
    'general'      => 'text',
    'safety_info'  => 'text',
    'no_routes'    => 'text',
    'clarification'=> 'clarification_request',
];
$frontendType = $typeMap[$responseType] ?? $responseType;

// Construir payload base
$payload = [
    'success'    => true,
    'type'       => $frontendType,
    'message'    => $responseContent,
    'session_id' => $chatSessionId,
    'intent'     => $aiResponse['intent'] ?? null,
    'context'    => [],
];

// Adjuntar datos específicos según el tipo
switch ($responseType) {

    case 'route_suggestions':
        $payload['routes']  = $aiResponse['routes'] ?? [];

        // Motivos concretos de cada ruta (mismo orden que las rutas)
        $routeReasons = array_values($aiResponse['reasons'] ?? []);

        // Adaptar campos al formato que usa createRouteCardHTML()
        $payload['routes'] = array_map(function (array $route, int $i) use ($routeReasons) {
            // Tiempo en coche formateado para el frontend
            $driveMin = isset($route['drive_time_minutes']) ? (int)$route['drive_time_minutes'] : null;
            $driveText = null;
            if ($driveMin !== null) {
                $h = intdiv($driveMin, 60);
                $m = $driveMin % 60;
                $driveText = ($h > 0 ? "{$h} h " . ($m ? "{$m} min" : '') : "{$m} min") . ' en coche';
            }

            return [
                'id'                 => $route['id']          ?? 0,
                'title'              => $route['title']  ?? ($route['titulo'] ?? ($route['nombre'] ?? 'Ruta sin nombre')),
                'description'        => $route['description'] ?? ($route['descripcion']    ?? ''),
                // DB stores these in Spanish column names; fallback chain covers both naming conventions
                'elevation'          => (float)($route['elevacion_ganada'] ?? $route['elevation'] ?? 0),
                'duration'           => (float)($route['duracion_horas']   ?? $route['duration']  ?? 0),
                'distance'           => (float)($route['distancia_km']     ?? $route['length']    ?? ($route['distance'] ?? 0)),
                'difficulty'         => $route['dificultad']  ?? ($route['difficulty'] ?? ''),
                'tipo_ruta'          => $route['tipo_ruta']   ?? '',
                // Esfuerzo MIDE (Fase 1): columnas generadas en `rutas`
                'esfuerzo'           => isset($route['esfuerzo'])   ? (int) $route['esfuerzo']     : null,
                // Horas MIDE a pie; en BTT/cicloturismo, estimadas por distancia (misma regla que el recomendador)
                'horas_mide'         => (RouteRecommender::routeHours($route) ?: null),
                'location'           => $route['ubicacion']   ?? ($route['location']  ?? ''),
                'drive_time_minutes' => $driveMin,
                'drive_km'           => $route['drive_km']    ?? null,
                'drive_time_text'    => $driveText,
                'image'              => !empty($route['image_url'])
                                            ? $route['image_url']
                                            : 'hiker_ai/assets/img/route-placeholder.svg',
                'reasons'            => array_slice(array_map('strval', (array) ($routeReasons[$i] ?? [])), 0, 3),
            ];
        }, $payload['routes'], array_keys($payload['routes']));

        if (!empty($aiResponse['weather_warning'])) {
            $payload['weather_warning'] = $aiResponse['weather_warning'];
        }

        $payload['suggestions'] = [
            '¿Qué tiempo hace en esa zona?',
            'Muéstrame rutas similares',
            'Cuéntame más sobre la primera ruta',
        ];
        break;

    case 'weather_info':
        // Mapear campos reales de WeatherService::parseCurrentFromHourly() al formato del widget
        $raw = $aiResponse['weather_raw'] ?? null;

        if ($raw) {
            $payload['weather'] = [
                // WeatherService devuelve 'temp', no 'temperature'
                'temperature'    => $raw['temp']        ?? null,
                'conditions'     => $raw['description'] ?? '',
                'wind_speed'     => $raw['windspeed']   ?? null,
                'wind_gusts'     => $raw['windgusts']   ?? null,
                'humidity'       => $raw['cloudcover']  ?? null,  // no hay humidity; usamos nubosidad
                'visibility'     => ($raw['mountain']['visibility_status'] ?? 'good') === 'poor' ? 500 : null,
                'warnings'       => [],
                // snow_level = isocero (isoterma 0°C), NO la cota del manto de nieve
                'snow_level'     => $raw['snow_level']  ?? ($raw['iso_zero'] ?? null),
                // cota_nieve_est = estimación real del inicio del manto (multi-punto o AEMET)
                'cota_nieve_est' => $aiResponse['conditions_report']['cota_nieve_est'] ?? null,
            ];
        }

        // Si es pronóstico multi-día, adjuntar el array completo
        if (!empty($aiResponse['forecast'])) {
            $payload['forecast'] = $aiResponse['forecast'];
            $payload['days']     = $aiResponse['days'] ?? count($aiResponse['forecast']);
        }

        if (!empty($aiResponse['location'])) {
            $payload['location'] = $aiResponse['location'];
        }

        $payload['suggestions'] = [
            'Buscar rutas para los mejores días',
            '¿Qué equipo necesito con este tiempo?',
            'Pronóstico para la próxima semana',
        ];
        break;

    case 'safety_info':
    case 'general':
        $payload['suggestions'] = [
            'Buscar rutas cerca de aquí',
            '¿Qué tiempo hace hoy?',
            'Recomendaciones para principiantes',
        ];
        break;

    case 'route_detail':
        // Detalle de una ruta específica (followup describe)
        if (!empty($aiResponse['route'])) {
            $r = $aiResponse['route'];
            $payload['route'] = [
                'id'         => $r['id']          ?? 0,
                'title'      => $r['title']  ?? ($r['titulo'] ?? ($r['nombre'] ?? 'Ruta sin nombre')),
                'description'=> $r['description']  ?? ($r['descripcion']    ?? ''),
                'elevation'  => (float)($r['elevacion_ganada'] ?? $r['elevation'] ?? 0),
                'duration'   => (float)($r['duracion_horas']   ?? $r['duration']  ?? 0),
                'distance'   => (float)($r['distancia_km']     ?? $r['distance']  ?? 0),
                'difficulty' => $r['dificultad']   ?? ($r['difficulty'] ?? ''),
                'tipo_ruta'  => $r['tipo_ruta']    ?? '',
                'esfuerzo'   => isset($r['esfuerzo'])   ? (int) $r['esfuerzo']     : null,
                'horas_mide' => (RouteRecommender::routeHours($r) ?: null),
                'location'   => $r['ubicacion']    ?? ($r['location']   ?? ''),
                'image'      => !empty($r['image_url'])
                                    ? $r['image_url']
                                    : 'hiker_ai/assets/img/route-placeholder.svg',
            ];
        }
        $payload['suggestions'] = [
            'Ver todas las rutas',
            '¿Qué tiempo hace en esa zona?',
            'Buscar rutas similares',
        ];
        break;

    case 'clarification':
        $payload['type']        = 'clarification_request';
        $payload['questions']   = [];
        $payload['suggestions'] = [];
        break;
}

// Incluir tiempo de procesamiento si AICore lo devuelve
if (isset($aiResponse['processing_ms'])) {
    $payload['processing_ms'] = $aiResponse['processing_ms'];
}

// ─── Enviar respuesta ─────────────────────────────────────────────────────────

$logger->info('Chat response enviada', [
    'userId'       => $userIdInt,
    'type'         => $payload['type'],
    'intent'       => $payload['intent'],
    'session_id'   => $chatSessionId,
]);

jsonResponse($payload);
