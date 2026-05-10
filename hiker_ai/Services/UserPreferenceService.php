<?php
/**
 * Servicio para gestionar preferencias de usuario en Kahir.AI
 * Adaptado al schema actual (MySQL 8, columnas en español/JSON).
 */

namespace KahirAI\Services;

class UserPreferenceService {

    private \mysqli $conn;
    private LoggerService $logger;
    private const MIN_INTERACTIONS = 5;
    private const UPDATE_INTERVAL  = 86400; // 24h

    public function __construct(\mysqli $connection) {
        $this->conn   = $connection;
        $this->logger = new LoggerService('user_preferences');
    }

    // ── API pública ───────────────────────────────────────────────────────────

    /**
     * Devuelve preferencias con las claves que AiCore espera
     * (experience_level, preferred_activities, preferred_locations, total_interactions).
     */
    public function getUserPreferences(string $username): array {
        try {
            $userId = $this->getUserId($username);
            if (!$userId) return $this->defaultPrefs();

            $stmt = $this->conn->prepare(
                "SELECT nivel_experiencia, actividades_preferidas, ubicaciones_habituales,
                        distancia_max_km, duracion_max_horas,
                        preferencias_inferidas, ultima_actualizacion
                 FROM user_preferences WHERE usuario_id = ? LIMIT 1"
            );
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) return $this->defaultPrefs();

            $inferidas = json_decode($row['preferencias_inferidas'] ?? '{}', true) ?? [];

            return [
                // Claves que AiCore usa internamente
                'experience_level'      => $row['nivel_experiencia'] ?? 'beginner',
                'preferred_activities'  => json_decode($row['actividades_preferidas'] ?? '[]', true) ?? [],
                'preferred_locations'   => json_decode($row['ubicaciones_habituales'] ?? '[]', true) ?? [],
                'avg_preferred_distance' => (float)($row['distancia_max_km']   ?? 0),
                'avg_preferred_duration' => (float)($row['duracion_max_horas'] ?? 0),
                'total_interactions'    => (int)($inferidas['total_interactions'] ?? 0),
                'last_updated'          => $row['ultima_actualizacion'] ?? null,
                // ID para operaciones internas
                '_user_id'              => $userId,
            ];

        } catch (\Exception $e) {
            $this->logger->error('getUserPreferences error', ['username' => $username, 'error' => $e->getMessage()]);
            return $this->defaultPrefs();
        }
    }

    public function processInteraction(int $userId, array $interaction): bool {
        try {
            $rutaId = $interaction['route_id'] ?? null;
            $tipo   = $interaction['type']     ?? 'view';

            $stmt = $this->conn->prepare(
                "INSERT INTO interacciones (usuario_id, ruta_id, tipo)
                 VALUES (?, ?, ?)"
            );
            $stmt->bind_param("iis", $userId, $rutaId, $tipo);
            $success = $stmt->execute();
            $stmt->close();

            if ($success && $this->shouldUpdate($userId)) {
                $this->updatePreferences($userId);
            }
            return $success;

        } catch (\Exception $e) {
            $this->logger->error('processInteraction error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function updatePreferences(int $userId): bool {
        try {
            $interactions = $this->getRecentInteractions($userId);
            if (count($interactions) < self::MIN_INTERACTIONS) return true;

            $patterns  = $this->analyzeInteractionPatterns($interactions);
            $inferidas = json_encode([
                'total_interactions' => $patterns['total_interactions'],
                'avg_view_time'      => $patterns['avg_view_time'],
            ]);

            $stmt = $this->conn->prepare(
                "INSERT INTO user_preferences
                    (usuario_id, nivel_experiencia, actividades_preferidas,
                     ubicaciones_habituales, distancia_max_km, duracion_max_horas,
                     preferencias_inferidas, ultima_actualizacion)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    nivel_experiencia      = VALUES(nivel_experiencia),
                    actividades_preferidas = VALUES(actividades_preferidas),
                    ubicaciones_habituales = VALUES(ubicaciones_habituales),
                    distancia_max_km       = VALUES(distancia_max_km),
                    duracion_max_horas     = VALUES(duracion_max_horas),
                    preferencias_inferidas = VALUES(preferencias_inferidas),
                    ultima_actualizacion   = NOW()"
            );

            $acts  = json_encode(array_slice($patterns['activities'], 0, 3));
            $locs  = json_encode(array_slice($patterns['locations'],  0, 3));
            $dist  = $patterns['avg_distance'];
            $dur   = $patterns['avg_duration'];
            $level = $patterns['experience_level'];

            $stmt->bind_param("isssdds", $userId, $level, $acts, $locs, $dist, $dur, $inferidas);
            $result = $stmt->execute();
            $stmt->close();
            return $result;

        } catch (\Exception $e) {
            $this->logger->error('updatePreferences error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ── Privados ──────────────────────────────────────────────────────────────

    private function getUserId(string $username): ?int {
        // Acepta tanto username textual como ID numérico (ej. cuando se pasa '1')
        if (ctype_digit($username)) {
            $id   = (int)$username;
            $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $row  = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ? $id : null;
        }
        $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id'] : null;
    }

    private function shouldUpdate(int $userId): bool {
        $stmt = $this->conn->prepare(
            "SELECT ultima_actualizacion FROM user_preferences WHERE usuario_id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return true;
        return (time() - strtotime($row['ultima_actualizacion'])) >= self::UPDATE_INTERVAL;
    }

    private function getRecentInteractions(int $userId): array {
        $stmt = $this->conn->prepare(
            "SELECT ui.*, r.tipo_ruta, r.ubicacion, r.distancia_km,
                    r.duracion_horas, r.dificultad
             FROM interacciones ui
             LEFT JOIN rutas r ON ui.ruta_id = r.id
             WHERE ui.usuario_id = ?
             ORDER BY ui.fecha DESC
             LIMIT 100"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    private function analyzeInteractionPatterns(array $interactions): array {
        $activities = [];
        $locations  = [];
        $distances  = [];
        $durations  = [];
        $difficulties = [];
        $viewTimes  = [];

        foreach ($interactions as $row) {
            if (!empty($row['tipo_ruta']))   $activities[$row['tipo_ruta']]  = ($activities[$row['tipo_ruta']]  ?? 0) + 1;
            if (!empty($row['ubicacion']))   $locations[$row['ubicacion']]   = ($locations[$row['ubicacion']]   ?? 0) + 1;
            if (!empty($row['distancia_km'])) $distances[] = (float)$row['distancia_km'];
            if (!empty($row['duracion_horas'])) $durations[] = (float)$row['duracion_horas'];
            if (!empty($row['dificultad']))  $difficulties[] = $row['dificultad'];
        }

        arsort($activities);
        arsort($locations);

        return [
            'activities'         => array_keys($activities),
            'locations'          => array_keys($locations),
            'avg_distance'       => $distances  ? array_sum($distances)  / count($distances)  : 0,
            'avg_duration'       => $durations  ? array_sum($durations)  / count($durations)  : 0,
            'avg_view_time'      => 0,
            'experience_level'   => $this->determineExperienceLevel($difficulties),
            'total_interactions' => count($interactions),
        ];
    }

    private function determineExperienceLevel(array $levels): string {
        if (empty($levels)) return 'beginner';
        $counts = array_count_values(array_map('strtolower', $levels));
        if (($counts['advanced'] ?? 0) > ($counts['beginner'] ?? 0)) return 'advanced';
        if (($counts['intermediate'] ?? 0) > ($counts['beginner'] ?? 0) || count($levels) > 50) return 'intermediate';
        return 'beginner';
    }

    private function defaultPrefs(): array {
        return [
            'experience_level'       => 'beginner',
            'preferred_activities'   => [],
            'preferred_locations'    => [],
            'avg_preferred_distance' => 0,
            'avg_preferred_duration' => 0,
            'total_interactions'     => 0,
            'last_updated'           => null,
            '_user_id'               => null,
        ];
    }
}
