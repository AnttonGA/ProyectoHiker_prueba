<?php
/**
 * Utilidades de seguridad compartidas: sesión segura, CSRF y escape de HTML.
 *
 *   require_once __DIR__ . '/../../includes/security.php';
 *   <?= csrf_field() ?>        dentro de cada <form method="post">
 *   csrf_verify();             al principio del procesamiento de un POST
 *   <?= e($valor) ?>           para imprimir cualquier dato en HTML
 */

if (!function_exists('secure_session_start')) {
    function secure_session_start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (headers_sent()) {
            // No se puede iniciar la sesión después de enviar contenido
            return;
        }
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        secure_session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_is_valid')) {
    /**
     * Comprueba el token CSRF. Lo busca en la cabecera X-CSRF-Token, en $_POST['csrf_token']
     * o, si se pasa, en $tokenExtra (por ejemplo el campo csrf_token de un cuerpo JSON).
     */
    function csrf_is_valid(?string $tokenExtra = null): bool {
        secure_session_start();
        $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ($tokenExtra ?? ''));
        return !empty($_SESSION['csrf_token']) && is_string($enviado)
            && hash_equals($_SESSION['csrf_token'], $enviado);
    }
}

if (!function_exists('csrf_verify')) {
    /** Corta la petición con 403 si el token CSRF no es válido. */
    function csrf_verify(bool $json = false, ?string $tokenExtra = null): void {
        if (csrf_is_valid($tokenExtra)) {
            return;
        }
        http_response_code(403);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'La sesión ha caducado. Recarga la página e inténtalo de nuevo.']);
        } else {
            echo 'La sesión ha caducado. Recarga la página e inténtalo de nuevo.';
        }
        exit();
    }
}

if (!function_exists('e')) {
    function e($valor): string {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
