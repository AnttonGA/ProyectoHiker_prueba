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
    <title><?php echo $isLoggedIn ? 'Kahir.ai Chat - Asistente de Rutas' : 'Kahir.AI - Tu Asistente de Montaña'; ?></title>
    
    <!-- Meta tags -->
    <meta name="description" content="Asistente inteligente para rutas de montaña y senderismo en los Pirineos">
    <meta name="keywords" content="senderismo, montaña, Pirineos, rutas, AI, asistente virtual">
    
    <!-- Preload de recursos críticos -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <!-- CSS Base -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <?php if ($isLoggedIn): ?>
        <!-- Estilos para la interfaz de chat -->
        <link href="hiker_ai/assets/css/chat.css" rel="stylesheet">
    <?php else: ?>
        <!-- Estilos para la página de inicio -->
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
        <link href="landing/landing.css" rel="stylesheet">
    <?php endif; ?>
</head>

<body class="<?php echo $isLoggedIn ? 'd-flex flex-column min-vh-100' : 'bg-white'; ?>">
    <!-- Menú de navegación -->
    <div id="menu"></div>

    <?php if ($isLoggedIn): ?>
        <!-- Interfaz de chat -->
        <main class="flex-grow-1 d-flex flex-column">
            <div class="container my-4 flex-grow-1 d-flex flex-column">
                <div class="row justify-content-center flex-grow-1">
                    <div class="col-md-10 col-lg-8">
                        <!-- Contenedor de prompts -->
                        <div id="prompt-container" class="prompt-container mb-4"></div>

                        <!-- Área de chat -->
                        <div id="chat-area" class="chat-area flex-grow-1 d-none">
                            <div id="chat-container" class="chat-container mb-3"></div>
                            <div id="typing-indicator" class="typing-indicator d-none">
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                            </div>
                        </div>

                        <!-- Panel de sugerencias -->
                        <div id="suggestions-panel" class="suggestions-panel mb-3 d-none"></div>
                    </div>
                </div>

                <!-- Formulario de chat -->
                <form id="chat-form" class="chat-form">
                    <div class="input-group">
                        <input type="text" id="user-input" class="form-control" 
                               placeholder="Pregunta sobre rutas, tiempo o recomendaciones..." 
                               maxlength="500" required>
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div id="char-counter" class="text-muted small mt-1">
                        <span id="char-count">0</span>/500
                    </div>
                </form>
            </div>
        </main>
    <?php else: ?>
        <!-- Página de inicio -->
        <div id="landing-root"></div>
    <?php endif; ?>

    <!-- Pie de página -->
    <div id="footer"></div>

    <!-- Scripts comunes -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
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
        <script src="hiker_ai/assets/js/chat.js"></script>
        <script src="hiker_ai/assets/js/chatcard.js"></script>
        <script src="hiker_ai/assets/js/weather_card.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof ChatApp !== 'undefined' && window.appConfig) {
                    ChatApp.init(window.appConfig);
                } else {
                    console.error('ChatApp or configuration not available');
                }
            });
        </script>
    <?php else: ?>
        <!-- Scripts para la página de inicio -->
        <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
        <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
        <script src="landing/landing.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const root = ReactDOM.createRoot(document.getElementById('landing-root'));
                root.render(React.createElement(LandingPage));

                // Observador de intersección para animaciones
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('visible');
                        }
                    });
                }, {
                    threshold: 0.1
                });

                // Observar todas las secciones
                document.querySelectorAll('section').forEach(section => {
                    observer.observe(section);
                });
            });
        </script>
    <?php endif; ?>

    <script src="include.js"></script>
</body>
</html>