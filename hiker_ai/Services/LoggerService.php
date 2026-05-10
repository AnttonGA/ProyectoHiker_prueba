<?php
/**
 * Servicio de logging para Kahir.AI
 */

namespace KahirAI\Services;

class LoggerService {
    private string $channel;
    private string $logDir;

    public function __construct(string $channel, string $logDir = null) {
        $this->channel = $channel;
        $this->logDir = $logDir ?? __DIR__ . '/../../logs';
        
        // Crear directorio de logs si no existe
        if (!file_exists($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }
    }

    public function debug(string $message, array $context = []): void {
        $this->log('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'channel' => $this->channel,
            'level' => $level,
            'message' => $message,
            'context' => $context
        ];

        $logFile = $this->logDir . '/' . date('Y-m-d') . '.log';
        file_put_contents(
            $logFile,
            json_encode($logEntry) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}