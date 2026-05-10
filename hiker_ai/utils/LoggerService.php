<?php
/**
 * Servicio de logging para Kahir.AI
 * 
 * Proporciona funcionalidades para registrar eventos, errores
 * y actividad del sistema de manera estructurada y configurable.
 */

namespace KahirAI\Services;

class LoggerService {
    /**
     * Niveles de log disponibles
     */
    private const LOG_LEVELS = [
        'DEBUG'     => 100,
        'INFO'      => 200,
        'NOTICE'    => 250,
        'WARNING'   => 300,
        'ERROR'     => 400,
        'CRITICAL'  => 500,
        'ALERT'     => 550,
        'EMERGENCY' => 600
    ];

    /**
     * Tipos de logs específicos
     */
    private const LOG_TYPES = [
        'SYSTEM'     => 'system',
        'SECURITY'   => 'security',
        'API'        => 'api',
        'USER'       => 'user',
        'ROUTE'      => 'route',
        'WEATHER'    => 'weather',
        'AI'         => 'ai',
        'PERFORMANCE'=> 'performance'
    ];

    /**
     * @var string Nombre del canal de logging
     */
    private string $channel;

    /**
     * @var string Directorio base para los logs
     */
    private string $logDir;

    /**
     * @var int Nivel mínimo de logging
     */
    private int $minLevel;

    /**
     * Constructor
     *
     * @param string $channel Nombre del canal de logging
     * @param string $logDir Directorio base para los logs (opcional)
     * @param string $minLevel Nivel mínimo de logging (opcional)
     */
    public function __construct(
        string $channel,
        string $logDir = null,
        string $minLevel = 'DEBUG'
    ) {
        $this->channel = $channel;
        $this->logDir = $logDir ?? __DIR__ . '/../logs';
        $this->minLevel = self::LOG_LEVELS[$minLevel] ?? self::LOG_LEVELS['DEBUG'];

        $this->initializeLogDirectory();
    }

    /**
     * Registra un mensaje de debug
     *
     * @param string $message Mensaje a registrar
     * @param array $context Contexto adicional
     */
    public function debug(string $message, array $context = []): void {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Registra un mensaje informativo
     *
     * @param string $message Mensaje a registrar
     * @param array $context Contexto adicional
     */
    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context);
    }

    /**
     * Registra una advertencia
     *
     * @param string $message Mensaje a registrar
     * @param array $context Contexto adicional
     */
    public function warning(string $message, array $context = []): void {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Registra un error
     *
     * @param string $message Mensaje a registrar
     * @param array $context Contexto adicional
     */
    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Registra un error crítico
     *
     * @param string $message Mensaje a registrar
     * @param array $context Contexto adicional
     */
    public function critical(string $message, array $context = []): void {
        $this->log('CRITICAL', $message, $context);
    }

    /**
     * Registra una interacción de usuario
     *
     * @param int $userId ID del usuario
     * @param string $action Acción realizada
     * @param array $details Detalles de la acción
     */
    public function logUserAction(int $userId, string $action, array $details = []): void {
        $context = [
            'userId' => $userId,
            'action' => $action,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        $this->log('INFO', "User action: $action", $context, self::LOG_TYPES['USER']);
    }

    /**
     * Registra una métrica de rendimiento
     *
     * @param string $metric Nombre de la métrica
     * @param float $value Valor
     * @param array $tags Tags adicionales
     */
    public function logPerformance(string $metric, float $value, array $tags = []): void {
        $context = [
            'metric' => $metric,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true)
        ];
        
        $this->log('INFO', "Performance metric: $metric", $context, self::LOG_TYPES['PERFORMANCE']);
    }

    /**
     * Registra un evento de seguridad
     *
     * @param string $event Tipo de evento
     * @param array $details Detalles del evento
     */
    public function logSecurityEvent(string $event, array $details = []): void {
        $context = array_merge($details, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        $this->log('WARNING', "Security event: $event", $context, self::LOG_TYPES['SECURITY']);
    }

    /**
     * Registra una interacción con la IA
     *
     * @param int $userId ID del usuario
     * @param string $query Consulta del usuario
     * @param array $response Respuesta de la IA
     */
    public function logAIInteraction(int $userId, string $query, array $response): void {
        $context = [
            'userId' => $userId,
            'query' => $query,
            'response' => $response,
            'processingTime' => $response['processingTime'] ?? null
        ];
        
        $this->log('INFO', "AI interaction", $context, self::LOG_TYPES['AI']);
    }

    /**
     * Método principal de logging
     *
     * @param string $level Nivel de log
     * @param string $message Mensaje
     * @param array $context Contexto
     * @param string $type Tipo de log
     */
    private function log(
        string $level,
        string $message,
        array $context = [],
        string $type = null
    ): void {
        if (self::LOG_LEVELS[$level] < $this->minLevel) {
            return;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s.u'),
            'channel' => $this->channel,
            'level' => $level,
            'type' => $type ?? 'general',
            'message' => $message,
            'context' => $context
        ];

        $this->writeLog($logEntry, $type);
    }

    /**
     * Escribe la entrada de log en el archivo correspondiente
     *
     * @param array $entry Entrada de log
     * @param string|null $type Tipo de log
     */
    private function writeLog(array $entry, ?string $type): void {
        $filename = $this->getLogFilename($type);
        $logLine = $this->formatLogEntry($entry);

        file_put_contents(
            $filename,
            $logLine . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        $this->rotateLogIfNeeded($filename);
    }

    /**
     * Obtiene el nombre del archivo de log
     *
     * @param string|null $type Tipo de log
     * @return string
     */
    private function getLogFilename(?string $type): string {
        $date = date('Y-m-d');
        $typeStr = $type ? "_{$type}" : '';
        return "{$this->logDir}/{$this->channel}{$typeStr}_{$date}.log";
    }

    /**
     * Formatea una entrada de log
     *
     * @param array $entry Entrada de log
     * @return string
     */
    private function formatLogEntry(array $entry): string {
        return json_encode($entry);
    }

    /**
     * Rota el archivo de log si es necesario
     *
     * @param string $filename Nombre del archivo
     */
    private function rotateLogIfNeeded(string $filename): void {
        if (!file_exists($filename)) {
            return;
        }

        $maxSize = 10 * 1024 * 1024; // 10MB
        if (filesize($filename) > $maxSize) {
            $this->rotateLog($filename);
        }
    }

    /**
     * Rota un archivo de log
     *
     * @param string $filename Nombre del archivo
     */
    private function rotateLog(string $filename): void {
        $info = pathinfo($filename);
        $rotated = sprintf(
            '%s/%s_%s.log',
            $info['dirname'],
            $info['filename'],
            date('Y-m-d_H-i-s')
        );
        
        rename($filename, $rotated);
        
        // Comprimir archivo rotado
        $this->compressLog($rotated);
        
        // Limpiar logs antiguos
        $this->cleanOldLogs();
    }

    /**
     * Comprime un archivo de log
     *
     * @param string $filename Nombre del archivo
     */
    private function compressLog(string $filename): void {
        $zip = new \ZipArchive();
        $zipName = $filename . '.zip';
        
        if ($zip->open($zipName, \ZipArchive::CREATE) === true) {
            $zip->addFile($filename, basename($filename));
            $zip->close();
            unlink($filename);
        }
    }

    /**
     * Limpia logs antiguos
     */
    private function cleanOldLogs(): void {
        $retention = 30; // días
        $threshold = strtotime("-{$retention} days");

        foreach (glob("{$this->logDir}/*.{log,zip}", GLOB_BRACE) as $file) {
            if (filemtime($file) < $threshold) {
                unlink($file);
            }
        }
    }

    /**
     * Inicializa el directorio de logs
     */
    private function initializeLogDirectory(): void {
        if (!file_exists($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
}