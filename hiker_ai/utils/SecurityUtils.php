<?php
/**
 * Utilidades de seguridad para Kahir.AI
 * 
 * Proporciona métodos para gestionar la seguridad, incluyendo
 * throttling, validación de tokens, encriptación y protección contra ataques.
 */

namespace KahirAI\Utils;

class SecurityUtils {
    /**
     * Tiempo de vida de diferentes tokens en segundos
     */
    private const TOKEN_LIFETIMES = [
        'access' => 7200,      // 2 horas
        'reset' => 3600,       // 1 hora
        'api' => 86400,        // 24 horas
        'verification' => 1800  // 30 minutos
    ];

    /**
     * Límites de intentos para diferentes acciones
     */
    private const ATTEMPT_LIMITS = [
        'login' => ['max' => 5, 'window' => 900],    // 5 intentos en 15 minutos
        'api' => ['max' => 100, 'window' => 3600],   // 100 intentos por hora
        'reset' => ['max' => 3, 'window' => 3600]    // 3 intentos por hora
    ];

    /**
     * Genera un token seguro
     *
     * @param string $type Tipo de token ('access', 'reset', 'api', 'verification')
     * @param array $data Datos adicionales para incluir en el token
     * @return string
     */
    public static function generateToken(string $type, array $data = []): string {
        $payload = array_merge($data, [
            'type' => $type,
            'exp' => time() + self::TOKEN_LIFETIMES[$type],
            'jti' => bin2hex(random_bytes(16))
        ]);

        return self::encodeJWT($payload);
    }

    /**
     * Verifica un token
     *
     * @param string $token Token a verificar
     * @param string $type Tipo de token esperado
     * @return array|false Payload decodificado o false si no es válido
     */
    public static function verifyToken(string $token, string $type) {
        try {
            $payload = self::decodeJWT($token);
            
            if ($payload['type'] !== $type || time() >= $payload['exp']) {
                return false;
            }

            return $payload;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verifica límites de intentos
     *
     * @param string $action Tipo de acción
     * @param string $identifier Identificador (IP, usuario, etc.)
     * @return bool True si está permitido, False si excede límites
     */
    public static function checkRateLimit(string $action, string $identifier): bool {
        if (!isset(self::ATTEMPT_LIMITS[$action])) {
            return true;
        }

        $key = "rate_limit:{$action}:{$identifier}";
        $attempts = self::getAttempts($key);

        if ($attempts >= self::ATTEMPT_LIMITS[$action]['max']) {
            return false;
        }

        self::incrementAttempts($key, self::ATTEMPT_LIMITS[$action]['window']);
        return true;
    }

    /**
     * Genera un hash seguro de contraseña
     *
     * @param string $password Contraseña a hashear
     * @return string
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * Verifica una contraseña contra su hash
     *
     * @param string $password Contraseña a verificar
     * @param string $hash Hash almacenado
     * @return bool
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * Sanitiza y valida una dirección IP
     *
     * @param string $ip Dirección IP a validar
     * @return string|false IP limpia o false si no es válida
     */
    public static function sanitizeIP(string $ip) {
        $cleanIP = filter_var($ip, FILTER_VALIDATE_IP);
        return $cleanIP ?: false;
    }

    /**
     * Genera un identificador de sesión seguro
     *
     * @return string
     */
    public static function generateSessionId(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * Encripta datos sensibles
     *
     * @param string $data Datos a encriptar
     * @param string $key Clave de encriptación
     * @return string
     */
    public static function encrypt(string $data, string $key): string {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($data, $nonce, $key);
        return base64_encode($nonce . $cipher);
    }

    /**
     * Desencripta datos
     *
     * @param string $encrypted Datos encriptados
     * @param string $key Clave de encriptación
     * @return string|false
     */
    public static function decrypt(string $encrypted, string $key) {
        try {
            $decoded = base64_decode($encrypted);
            $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
            $cipher = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
            
            return sodium_crypto_secretbox_open($cipher, $nonce, $key);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Codifica un JWT
     *
     * @param array $payload Datos a codificar
     * @return string
     */
    /** Secreto de firma: obligatorio en .env (JWT_SECRET); nunca un valor por defecto conocido */
    private static function jwtSecret(): string {
        $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');
        if (!is_string($secret) || $secret === '') {
            throw new \Exception('JWT_SECRET no configurado en .env');
        }
        return $secret;
    }

    private static function encodeJWT(array $payload): string {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $encodedHeader = base64_encode(json_encode($header));
        $encodedPayload = base64_encode(json_encode($payload));
        
        $signature = hash_hmac('sha256', 
            "$encodedHeader.$encodedPayload", 
            self::jwtSecret(),
            true
        );
        
        $encodedSignature = base64_encode($signature);

        return "$encodedHeader.$encodedPayload.$encodedSignature";
    }

    /**
     * Decodifica un JWT
     *
     * @param string $jwt Token JWT
     * @return array
     * @throws \Exception Si el token no es válido
     */
    private static function decodeJWT(string $jwt): array {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \Exception('Invalid token format');
        }

        $signature = hash_hmac('sha256',
            "{$parts[0]}.{$parts[1]}",
            self::jwtSecret(),
            true
        );

        if (!hash_equals(base64_encode($signature), $parts[2])) {
            throw new \Exception('Invalid signature');
        }

        return json_decode(base64_decode($parts[1]), true);
    }

    /**
     * Obtiene el número de intentos actual
     *
     * @param string $key Clave de caché
     * @return int
     */
    private static function getAttempts(string $key): int {
        // Implementar con sistema de caché real
        return 0;
    }

    /**
     * Incrementa el contador de intentos
     *
     * @param string $key Clave de caché
     * @param int $window Ventana de tiempo en segundos
     */
    private static function incrementAttempts(string $key, int $window): void {
        // Implementar con sistema de caché real
    }
}