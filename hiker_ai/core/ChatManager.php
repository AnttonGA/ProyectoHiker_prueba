<?php
namespace KahirAI\Core;

use KahirAI\Services\LoggerService;

/**
 * ChatManager — Validador ligero de mensajes entrantes.
 *
 * BLOQUE 5: Simplificado. Toda la lógica de intents, clarificaciones
 * y contexto vive en AiCore + BD. Esta clase solo hace comprobaciones
 * básicas de formato antes de que el mensaje llegue a AiCore.
 *
 * Responsabilidades:
 *  - Validar que el mensaje no está vacío ni es spam básico
 *  - Detectar saludos simples para responderlos sin gastar tokens de Haiku
 *  - Devolver siempre ['type' => 'ready'] si el mensaje es procesable
 */
class ChatManager {

    private LoggerService $logger;

    // Saludos que no necesitan pasar por Claude
    private const GREETINGS = [
        'hola', 'buenos días', 'buenas tardes', 'buenas noches',
        'buenas', 'hey', 'hi', 'hello', 'ey',
    ];

    // Mensajes de test/spam a ignorar silenciosamente
    private const SPAM_PATTERNS = [
        '/^(.)\1{4,}$/u',      // "aaaaa", ".....", etc.
        '/^[\W\s]+$/u',         // Solo puntuación o espacios
    ];

    public function __construct() {
        $this->logger = new LoggerService('chat_manager');
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Valida el mensaje y devuelve su tipo de procesamiento.
     *
     * Tipos de retorno:
     *  - ['type' => 'ready']              → procesar con AiCore normalmente
     *  - ['type' => 'greeting', 'content' => string] → saludo simple, sin AiCore
     *  - ['type' => 'invalid', 'content' => string]  → mensaje rechazado
     */
    public function processMessage(string $message): array {
        $message = trim($message);

        // Mensaje vacío (no debería llegar aquí, pero por seguridad)
        if ($message === '') {
            return ['type' => 'invalid', 'content' => 'El mensaje está vacío.'];
        }

        // Spam / basura
        foreach (self::SPAM_PATTERNS as $pattern) {
            if (preg_match($pattern, $message)) {
                $this->logger->warning('Mensaje spam descartado', ['msg' => mb_substr($message, 0, 50)]);
                return ['type' => 'invalid', 'content' => 'No he entendido tu mensaje. ¿Puedes reformularlo?'];
            }
        }

        // Saludo puro → respuesta instantánea sin consumir tokens de Haiku/Sonnet
        if ($this->isPureGreeting($message)) {
            return [
                'type'    => 'greeting',
                'content' => $this->greetingResponse(),
            ];
        }

        // Todo lo demás pasa a AiCore
        return ['type' => 'ready'];
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function isPureGreeting(string $message): bool {
        $lower  = mb_strtolower(trim($message), 'UTF-8');
        // Solo es saludo puro si el mensaje completo es un saludo (sin texto adicional)
        foreach (self::GREETINGS as $g) {
            if ($lower === $g || $lower === $g . '!' || $lower === $g . '.') {
                return true;
            }
        }
        return false;
    }

    private function greetingResponse(): string {
        $responses = [
            '¡Hola! Soy Kahir, tu asistente de montaña. ¿Qué planes tienes hoy? Puedo ayudarte a buscar rutas, consultar el tiempo o resolver cualquier duda sobre los Pirineos.',
            '¡Buenas! ¿En qué puedo ayudarte? Busca rutas, consulta el tiempo o pregúntame lo que necesites sobre los Pirineos y Euskadi.',
            '¡Hola! ¿Listo para la montaña? Dime qué necesitas: rutas, previsión meteorológica o información de seguridad.',
        ];
        return $responses[array_rand($responses)];
    }
}
