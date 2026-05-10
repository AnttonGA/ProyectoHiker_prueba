<?php
/**
 * Excepción base personalizada para el sistema Kahir.AI
 * 
 * Esta clase proporciona la funcionalidad base para todas las excepciones
 * del sistema, incluyendo logging y formateo de errores.
 */

namespace KahirAI\Exceptions;

use Exception;
use Throwable;

class AIException extends Exception {
    /**
     * @var array Datos adicionales sobre el error
     */
    protected $context;

    /**
     * @var string Código de error legible
     */
    protected $errorCode;

    /**
     * Constructor de la excepción
     *
     * @param string $message Mensaje de error
     * @param string $errorCode Código de error legible
     * @param array $context Datos adicionales sobre el error
     * @param int $code Código numérico del error
     * @param Throwable|null $previous Excepción previa
     */
    public function __construct(
        string $message = "",
        string $errorCode = "UNKNOWN_ERROR",
        array $context = [],
        int $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
        $this->logError();
    }

    /**
     * Registra el error en el sistema de logging
     */
    protected function logError(): void {
        $logMessage = sprintf(
            "[%s] %s: %s in %s:%d\nContext: %s\nStack trace:\n%s",
            $this->errorCode,
            get_class($this),
            $this->message,
            $this->file,
            $this->line,
            json_encode($this->context, JSON_PRETTY_PRINT),
            $this->getTraceAsString()
        );

        error_log($logMessage);
    }

    /**
     * Obtiene el código de error legible
     *
     * @return string
     */
    public function getErrorCode(): string {
        return $this->errorCode;
    }

    /**
     * Obtiene el contexto del error
     *
     * @return array
     */
    public function getContext(): array {
        return $this->context;
    }

    /**
     * Obtiene los detalles completos del error en formato array
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'message' => $this->message,
            'errorCode' => $this->errorCode,
            'context' => $this->context,
            'code' => $this->code,
            'file' => $this->file,
            'line' => $this->line
        ];
    }

    /**
     * Genera una respuesta amigable para el usuario
     *
     * @return string
     */
    public function getUserFriendlyMessage(): string {
        // Mensajes predefinidos según el código de error
        $friendlyMessages = [
            'ROUTE_NOT_FOUND' => 'No pudimos encontrar la ruta que buscas.',
            'WEATHER_API_ERROR' => 'No podemos obtener la información meteorológica en este momento.',
            'INVALID_INPUT' => 'Por favor, verifica la información proporcionada.',
            'UNKNOWN_ERROR' => 'Ha ocurrido un error inesperado.'
        ];

        return $friendlyMessages[$this->errorCode] ?? $friendlyMessages['UNKNOWN_ERROR'];
    }

    /**
     * Determina si el error es crítico
     *
     * @return bool
     */
    public function isCritical(): bool {
        return $this->code >= 500;
    }

    /**
     * Determina si el error requiere notificación al administrador
     *
     * @return bool
     */
    public function requiresNotification(): bool {
        return $this->isCritical() || 
               isset($this->context['notify_admin']) && 
               $this->context['notify_admin'] === true;
    }
}