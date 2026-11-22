<?php
require_once dirname(__DIR__, 2) . '/includes/env_loader.php';
load_env();

// Entorno: si .env no define APP_ENV se asume producción (sin errores en pantalla)
define('APP_ENV',    env('APP_ENV', 'production') ?: 'production');
define('DEBUG_MODE', APP_ENV === 'development');

// Claude API (Anthropic)
define('CLAUDE_API_KEY', env('CLAUDE_API_KEY', ''));

// AEMET OpenData (España) — boletines de aludes, alertas, nivometría
define('AEMET_API_KEY',  env('AEMET_API_KEY',  ''));
define('AEMET_BASE_URL', 'https://opendata.aemet.es/opendata/api');

// Météo-France (Francia) — BRA, vigilancia, estaciones nivo
// Requiere registro gratuito en portail-api.meteofrance.fr (OAuth2 client credentials)
define('METEOFRANCE_CLIENT_ID',     env('METEOFRANCE_CLIENT_ID',     ''));
define('METEOFRANCE_CLIENT_SECRET', env('METEOFRANCE_CLIENT_SECRET',  ''));
define('METEOFRANCE_TOKEN_URL',     'https://portail-api.meteofrance.fr/token');
define('METEOFRANCE_BASE_URL',      'https://public-api.meteofrance.fr/public');

// Open-Meteo no requiere clave de API

// Geocodificación
define('OPENSTREETMAP_API_URL', 'https://nominatim.openstreetmap.org');

// Límites de uso por rol
define('RATE_LIMIT_FREEMIUM', 4);
define('RATE_LIMIT_PREMIUM',  20);
define('API_TIMEOUT',         30);

// Idiomas soportados
define('SUPPORTED_LANGUAGES', [
    'es' => 'Español',
    'fr' => 'Français',
    'en' => 'English',
    'de' => 'Deutsch',
    'it' => 'Italiano',
    'eu' => 'Euskara',
]);
define('DEFAULT_LANGUAGE', 'es');

// Recomendador
define('MIN_ROUTE_RATING',      3.0);
define('MAX_ROUTE_SUGGESTIONS', 5);
define('MAX_SIMILAR_ROUTES',    3);

// Meteorología
define('WEATHER_CACHE_DURATION',  1800);  // 30 min
define('MAX_FORECAST_DAYS',       16);    // Open-Meteo soporta hasta 16 días
define('WEATHER_UPDATE_INTERVAL', 3600);

// Seguridad de sesión
define('SESSION_LIFETIME',   7200);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME',       900);
define('PASSWORD_MIN_LENGTH', 8);

// Logging
define('LOG_PATH',           __DIR__ . '/../logs');
define('MAX_LOG_FILES',      30);
define('LOG_ROTATION_SIZE',  10485760); // 10 MB
// Audit log de Kahir — ponlo en true cuando quieras activar el rastreo del pipeline
if (!defined('KAHIR_AUDIT_LOG')) define('KAHIR_AUDIT_LOG', true);  // los scripts de prueba pueden fijarlo antes

// Caché de archivos
define('CACHE_ENABLED',     true);
define('CACHE_PATH',        __DIR__ . '/../cache');
define('CACHE_DEFAULT_TTL', 3600);

// Preferencias de usuario
define('USER_PREFERENCES_UPDATE_INTERVAL', 86400);
define('MIN_INTERACTIONS_FOR_PREFERENCES', 5);

// Respuesta
define('MAX_RESPONSE_TIME',       5000);
define('DEFAULT_PAGINATION_LIMIT', 10);

// Paths de subida
define('UPLOAD_PATH', __DIR__ . '/../uploads');
define('TEMP_PATH',   __DIR__ . '/../temp');

// Errores
error_reporting(DEBUG_MODE ? E_ALL : E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', DEBUG_MODE ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php-error.log');

date_default_timezone_set('Europe/Madrid');

function get_config(string $key, mixed $default = null): mixed {
    return defined($key) ? constant($key) : $default;
}
