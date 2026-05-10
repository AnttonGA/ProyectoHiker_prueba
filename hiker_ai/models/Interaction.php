<?php
/**
 * Modelo para interacciones de usuario en Kahir.AI
 * 
 * Maneja el registro y análisis de interacciones de usuarios
 * con rutas, clima y el sistema AI.
 */

namespace KahirAI\Models;

use KahirAI\Exceptions\AIException;
use KahirAI\Services\LoggerService;

class Interaction {
    /**
     * @var int ID de la interacción
     */
    private int $id;

    /**
     * @var int ID del usuario
     */
    private int $user_id;

    /**
     * @var int|null ID de la ruta (si aplica)
     */
    private ?int $route_id;

    /**
     * @var string Tipo de interacción
     */
    private string $interaction_type;

    /**
     * @var \DateTime Fecha de la interacción
     */
    private \DateTime $interaction_date;

    /**
     * @var int|null Duración de la vista en segundos
     */
    private ?int $view_duration;

    /**
     * @var LoggerService
     */
    private LoggerService $logger;

    /**
     * @var array Tipos de interacción válidos
     */
    private const VALID_TYPES = [
        'view',
        'download',
        'favorite',
        'complete',
        'ai_query',
        'weather_check',
        'route_search'
    ];

    /**
     * Constructor
     * 
     * @param array $data Datos de la interacción
     */
    public function __construct(array $data = []) {
        $this->logger = new LoggerService('interaction_model');
        $this->interaction_date = new \DateTime();
        
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }

    /**
     * Registra una nueva interacción
     * 
     * @param int $userId ID del usuario
     * @param string $type Tipo de interacción
     * @param array $data Datos adicionales
     * @return self
     * @throws AIException
     */
    public static function record(int $userId, string $type, array $data = []): self {
        $interaction = new self([
            'user_id' => $userId,
            'interaction_type' => $type,
            'route_id' => $data['route_id'] ?? null,
            'view_duration' => $data['view_duration'] ?? null
        ]);

        $interaction->save();
        return $interaction;
    }

    /**
     * Guarda la interacción
     * 
     * @return bool
     * @throws AIException
     */
    public function save(): bool {
        global $conn;

        try {
            $stmt = $conn->prepare(
                "INSERT INTO user_interactions (usuario_id, ruta_id, tipo) VALUES (?, ?, ?)"
            );

            $stmt->bind_param(
                "iis",
                $this->user_id,
                $this->route_id,
                $this->interaction_type
            );

            if (!$stmt->execute()) {
                throw new AIException("Error al guardar interacción", "SAVE_ERROR");
            }

            $this->id = $stmt->insert_id;

            // Actualizar estadísticas relacionadas
            $this->updateRelatedStats();

            return true;

        } catch (\Exception $e) {
            $this->logger->error("Error guardando interacción", [
                'error' => $e->getMessage(),
                'user_id' => $this->user_id,
                'type' => $this->interaction_type
            ]);
            throw new AIException("Error al guardar interacción", "SAVE_ERROR");
        }
    }

    /**
     * Actualiza estadísticas relacionadas
     */
    private function updateRelatedStats(): void {
        if (!$this->route_id) {
            return;
        }

        global $conn;

        // Actualizar contadores en route_popularity
        $field = $this->interaction_type . '_count';
        $stmt = $conn->prepare(
            "INSERT INTO route_popularity (ruta_id, $field)
             VALUES (?, 1)
             ON DUPLICATE KEY UPDATE
             $field = $field + 1,
             ultima_actualizacion = NOW()"
        );
        $stmt->bind_param("i", $this->route_id);
        $stmt->execute();

        // Actualizar puntuación de popularidad
        $this->updatePopularityScore();
    }

    /**
     * Actualiza la puntuación de popularidad de la ruta
     */
    private function updatePopularityScore(): void {
        global $conn;

        $stmt = $conn->prepare(
            "UPDATE route_popularity SET
             popularity_score = (
                (view_count * 1) +
                (download_count * 2) +
                (favorite_count * 3) +
                (complete_count * 4)
             ) / 10
             WHERE ruta_id = ?"
        );
        $stmt->bind_param("i", $this->route_id);
        $stmt->execute();
    }

    /**
     * Obtiene las interacciones recientes de un usuario
     * 
     * @param int $userId ID del usuario
     * @param int $limit Límite de resultados
     * @return array
     */
    public static function getRecentByUser(int $userId, int $limit = 10): array {
        global $conn;

        $stmt = $conn->prepare(
            "SELECT * FROM user_interactions
             WHERE user_id = ?
             ORDER BY interaction_date DESC
             LIMIT ?"
        );
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene estadísticas de interacción de un usuario
     * 
     * @param int $userId ID del usuario
     * @return array
     */
    public static function getUserStats(int $userId): array {
        global $conn;

        $stmt = $conn->prepare(
            "SELECT 
                COUNT(*) as total_interactions,
                COUNT(DISTINCT route_id) as unique_routes,
                SUM(CASE WHEN interaction_type = 'complete' THEN 1 ELSE 0 END) as completed_routes,
                SUM(CASE WHEN interaction_type = 'favorite' THEN 1 ELSE 0 END) as favorite_routes,
                AVG(CASE WHEN interaction_type = 'view' THEN view_duration ELSE NULL END) as avg_view_time
             FROM user_interactions
             WHERE user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Valida los datos de la interacción
     * 
     * @return bool
     * @throws AIException
     */
    public function validate(): bool {
        $errors = [];

        if (!$this->user_id) {
            $errors[] = "User ID es requerido";
        }

        if (!in_array($this->interaction_type, self::VALID_TYPES)) {
            $errors[] = "Tipo de interacción inválido";
        }

        if ($this->view_duration !== null && $this->view_duration < 0) {
            $errors[] = "Duración de vista inválida";
        }

        if (!empty($errors)) {
            throw new AIException("Errores de validación", "VALIDATION_ERROR", [
                'errors' => $errors
            ]);
        }

        return true;
    }

    /**
     * Convierte el objeto a array
     * 
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'route_id' => $this->route_id,
            'interaction_type' => $this->interaction_type,
            'interaction_date' => $this->interaction_date->format('Y-m-d H:i:s'),
            'view_duration' => $this->view_duration
        ];
    }

    // Getters y Setters
    // ... (implementar según necesidad)
}