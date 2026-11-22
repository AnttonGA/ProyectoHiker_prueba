<?php
// Cargar dependencias
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/paginasespecializadas/inicio_sesion/conexion.php';
require_once __DIR__ . '/paginasespecializadas/inicio_sesion/config.php';
require_once __DIR__ . '/includes/security.php';

// Sesión con cookie HttpOnly + SameSite antes de que AuthService la abra
secure_session_start();

// Importar clases necesarias
use KahirAI\Core\{AICore, ChatManager};
use KahirAI\Services\{AuthService, LoggerService, UserPreferenceService};
use KahirAI\Database\DBConnection;

// Inicializar servicios básicos
$auth = new AuthService();
$logger = new LoggerService('main');
$isLoggedIn = $auth->checkSession();
$userPreferences = [];

// Verificar conexión a la base de datos
if (!isset($conn) || $conn === null) {
    $logger->error('Error de conexión a la base de datos');
    die('Ahora mismo no podemos atenderte. Inténtalo de nuevo más tarde.');
}

if ($isLoggedIn) {
    try {
        $preferences = new UserPreferenceService($conn);
        $chatManager = new ChatManager();
        $ai = new AICore($conn);
        $userPreferences = $preferences->getUserPreferences($_SESSION['usuario']);
    } catch (\Exception $e) {
        $logger->error('Error al inicializar servicios', ['error' => $e->getMessage()]);
        $userPreferences = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?php echo $isLoggedIn ? 'Asistente · Kahir.ai' : 'Kahir.ai · Tu asistente de montaña'; ?></title>
    
    <!-- Meta tags -->
    <meta name="description" content="Asistente inteligente para rutas de montaña y senderismo en los Pirineos">
    <meta name="keywords" content="senderismo, montaña, Pirineos, rutas, IA, asistente virtual">
    
    <!-- Preload de recursos críticos -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <!-- CSS Base -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <?php if ($isLoggedIn): ?>
        <!-- Estilos para la interfaz de chat -->
        <link href="hiker_ai/assets/css/chat.css?v=20261122d" rel="stylesheet">
        <link href="assets/css/kahir.css?v=20261122" rel="stylesheet">
    <?php else: ?>
        <!-- Portada para visitantes -->
        <link href="assets/css/kahir.css?v=20261122" rel="stylesheet">
        <link href="landing/landing.css?v=20261122" rel="stylesheet">
        <link rel="preload" as="image" href="imagenes/GR_route.jpg">
    <?php endif; ?>
</head>

<body class="d-flex flex-column min-vh-100">
    <!-- Menú de navegación -->
    <div id="menu"></div>

    <?php if ($isLoggedIn): ?>
        <!-- Interfaz de chat -->
        <main class="kchat flex-grow-1 d-flex flex-column">
            <div class="kchat-column flex-grow-1 d-flex flex-column">
                <!-- Estado vacío: bienvenida + ejemplos de preguntas (lo rellena chat.js) -->
                <div id="prompt-container" class="prompt-container"></div>

                <!-- Conversación -->
                <div id="chat-area" class="chat-area flex-grow-1 d-none">
                    <div id="chat-container" class="chat-container" aria-live="polite"></div>
                    <div id="typing-indicator" class="typing-indicator d-none" role="status">
                        <img class="message-avatar" src="assets/img/kahir-mark.svg" alt="" aria-hidden="true" width="32" height="32">
                        <div class="typing-bubble">
                            <span class="typing-dot"></span>
                            <span class="typing-dot"></span>
                            <span class="typing-dot"></span>
                            <span class="typing-label">Kahir está mirando el mapa…</span>
                        </div>
                    </div>
                </div>

                <!-- Sugerencias de seguimiento -->
                <div id="suggestions-panel" class="suggestions-panel d-none" aria-label="Sugerencias"></div>
            </div>

            <!-- Compositor fijo abajo -->
            <div class="kchat-composer">
                <form id="chat-form" class="chat-form kchat-column">
                    <label for="user-input" class="visually-hidden">Escribe tu pregunta</label>
                    <div class="composer-box">
                        <input type="text" id="user-input" class="form-control composer-input"
                               placeholder="Pregunta por rutas, el tiempo o qué llevar…"
                               maxlength="500" autocomplete="off" required>
                        <button class="btn btn-primary composer-send" type="submit" aria-label="Enviar">
                            <i class="bi bi-arrow-up" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="composer-meta">
                        <span class="composer-hint">Revisa siempre el parte y las condiciones antes de salir.</span>
                        <span id="char-counter" class="char-counter"><span id="char-count">0</span>/500</span>
                    </div>
                </form>
            </div>
        </main>
    <?php else: ?>
        <!-- Portada (HTML del servidor: se ve sin JavaScript y la indexan los buscadores) -->
        <?php require __DIR__ . '/landing/landing_content.php'; ?>
    <?php endif; ?>

    <!-- Pie de página -->
    <div id="footer"></div>

    <!-- Scripts comunes -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- CSRF: añade X-CSRF-Token a fetch()/$.ajax POST (después de jQuery, antes de los scripts propios) -->
    <script src="csrf.js"></script>
    <!-- Marked.js: renderiza Markdown en las respuestas del asistente -->
    <script src="https://cdn.jsdelivr.net/npm/marked@9/marked.min.js"></script>

    <?php if ($isLoggedIn): ?>
        <!-- Scripts para el chat -->
        <?php $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE; ?>
        <script>
            window.appConfig = {
                userId: <?php echo json_encode(isset($_SESSION['id']) ? (string)$_SESSION['id'] : '', $jsonFlags); ?>,
                username: <?php echo json_encode(isset($_SESSION['usuario']) ? (string)$_SESSION['usuario'] : '', $jsonFlags); ?>,
                preferences: <?php echo !empty($userPreferences) ? (json_encode($userPreferences, $jsonFlags) ?: '{}') : '{}'; ?>,
                apiEndpoints: {
                    chat: 'api/chat_handler.php',
                    weather: 'api/weather_handler.php',
                    routes: 'api/route_handler.php'
                }
            };
        </script>
        <script src="hiker_ai/assets/js/chat.js?v=20261122h"></script>
        <script src="hiker_ai/assets/js/chatcard.js?v=20261122"></script>
        <script src="hiker_ai/assets/js/weather_card.js?v=20261122"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof ChatApp !== 'undefined' && window.appConfig) {
                    ChatApp.init(window.appConfig);
                } else {
                    console.error('ChatApp or configuration not available');
                }
            });
        </script>
    <?php endif; ?>

    <script src="include.js"></script>
</body>
</html>