<?php
/**
 * Servicio de autenticación y autorización para Kahir.AI
 */

namespace KahirAI\Services;

use KahirAI\Utils\SecurityUtils;
use KahirAI\Exceptions\AIException;

class AuthService {
    /**
     * @var LoggerService
     */
    private LoggerService $logger;

    /**
     * @var int Duración de la sesión en segundos
     */
    private const SESSION_LIFETIME = 7200; // 2 horas

    /**
     * @var int Intentos máximos de inicio de sesión
     */
    private const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * @var int Tiempo de bloqueo después de exceder intentos (15 minutos)
     */
    private const LOCKOUT_TIME = 900;

    /**
     * Roles y sus niveles de acceso
     */
    private const ROLES = [
        'A' => ['name' => 'admin', 'level' => 100],     // Administrador
        'E' => ['name' => 'editor', 'level' => 75],     // Editor
        'U' => ['name' => 'user', 'level' => 50],       // Usuario normal
        'I' => ['name' => 'guest', 'level' => 25]       // Invitado
    ];

    /**
     * Estados de usuario
     */
    private const ESTADOS = [
        'A' => 'active',      // Activo
        'P' => 'pending',     // Preinscrito
        'B' => 'deleted',     // Borrado
        'D' => 'disabled'     // Desactivado
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new LoggerService('auth');
    }

    /**
     * Verifica si hay una sesión activa
     */
    public function checkSession(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['usuario'])) {
            return false;
        }

        // Verificar si la sesión ha expirado
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > self::SESSION_LIFETIME)) {
            $this->logout();
            return false;
        }

        // Actualizar timestamp de última actividad
        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Autentica un usuario
     */
    public function authenticate(string $username, string $password): array {
        global $conn;

        try {
            $stmt = $conn->prepare(
                "SELECT id, username, password_hash, rol
                 FROM usuarios
                 WHERE (username = ? OR email = ?) AND activo = 1"
            );
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new AIException('Usuario no encontrado', 'AUTH_FAILED');
            }

            $user = $result->fetch_assoc();

            // Verificar contraseña
            if (!password_verify($password, $user['password_hash'])) {
                $this->logFailedLogin($username);
                throw new AIException('Contraseña incorrecta', 'AUTH_FAILED');
            }

            // Iniciar sesión
            $this->initSession($user);
            $this->logSuccessfulLogin($user['id']);

            return [
                'id'       => $user['id'],
                'username' => $user['username'],
                'role'     => $user['rol'],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error de autenticación', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            throw new AIException(
                'Error de autenticación', 
                'AUTH_ERROR', 
                ['original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Verifica si el usuario tiene un rol específico
     */
    public function hasRole(string $requiredRole): bool {
        if (!isset($_SESSION['rol'])) {
            return false;
        }

        $userRoleLevel = self::ROLES[$_SESSION['rol']]['level'];
        $requiredRoleLevel = self::ROLES[$requiredRole]['level'];

        return $userRoleLevel >= $requiredRoleLevel;
    }

    /**
     * Verifica si el usuario está autenticado
     */
    public function isAuthenticated(): bool {
        return isset($_SESSION['usuario']);
    }

    /**
     * Cierra la sesión del usuario
     */
    public function logout(): void {
        if (isset($_SESSION['usuario'])) {
            $this->logLogout($_SESSION['usuario']);
        }
        
        session_destroy();
        setcookie('usuario', '', time() - 3600, '/');
        setcookie('rol', '', time() - 3600, '/');
    }

    /**
     * Verifica límites de uso según el tipo de usuario
     */
    public function checkUsageLimit(string $action): bool {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $limits = [
            'message' => [
                'I' => 4,   // Límite para invitados
                'U' => 20,  // Límite para usuarios normales
                'E' => 50,  // Límite para editores
                'A' => -1   // Sin límite para administradores
            ]
        ];

        if (!isset($limits[$action])) {
            return true;
        }

        $userRole = $_SESSION['rol'];
        $limit = $limits[$action][$userRole];

        if ($limit === -1) {
            return true;
        }

        // Verificar uso actual
        $usage = $this->getCurrentUsage($_SESSION['id'], $action);
        return $usage < $limit;
    }

    /**
     * Inicializa la sesión de usuario
     */
    private function initSession(array $user): void {
        $_SESSION['id']      = $user['id'];
        $_SESSION['usuario'] = $user['username'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['rol']     = $user['rol'];
        $_SESSION['last_activity'] = time();

        // Establecer cookies seguras si se solicita "recordar"
        if (isset($_POST['mantenerseLogeado'])) {
            $expiration = time() + (30 * 24 * 60 * 60); // 30 días
            setcookie('usuario', $user['usuario'], $expiration, '/', '', true, true);
            setcookie('rol', $user['rol'], $expiration, '/', '', true, true);
        }
    }

    /**
     * Registra un intento fallido de inicio de sesión
     */
    private function logFailedLogin(string $username): void {
        global $conn;

        $stmt = $conn->prepare(
            "INSERT INTO login_attempts (email, ip_address, exitoso)
             VALUES (?, ?, 0)"
        );
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("ss", $username, $ip);
        $stmt->execute();

        $this->logger->warning('Intento de inicio de sesión fallido', [
            'username' => $username,
            'ip' => $ip
        ]);
    }

    /**
     * Registra un inicio de sesión exitoso
     */
    private function logSuccessfulLogin(int $userId): void {
        $this->logger->info('Inicio de sesión exitoso', [
            'userId' => $userId,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
    }

    /**
     * Registra un cierre de sesión
     */
    private function logLogout(string $username): void {
        $this->logger->info('Cierre de sesión', [
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
    }

    /**
     * Obtiene el uso actual de un servicio
     */
    private function getCurrentUsage(int $userId, string $action): int {
        global $conn;

        $stmt = $conn->prepare(
            "SELECT COUNT(*) as count 
             FROM user_interactions 
             WHERE user_id = ? 
             AND interaction_type = ? 
             AND DATE(interaction_date) = CURDATE()"
        );
        $stmt->bind_param("is", $userId, $action);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int)$row['count'];
    }
}