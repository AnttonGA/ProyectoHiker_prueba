<?php
namespace KahirAI\Core;

use KahirAI\Services\{WeatherService, LoggerService};
use KahirAI\Interfaces\IRecommender;
use KahirAI\Exceptions\RouteException;

class RouteRecommender implements IRecommender {

    private LoggerService $logger;
    private WeatherService $weather;
    private \mysqli $conn;

    /** IDs del último pool de 50 candidatos (pre-scoring). Usados por route_followup filtros. */
    private array $lastCandidateIds = [];

    /** Devuelve los IDs del último pool de candidatos consultado. */
    public function getLastCandidateIds(): array { return $this->lastCandidateIds; }

    private const WEIGHTS = [
        'difficulty_match'   => 0.25,
        'location_match'     => 0.20,
        'weather_conditions' => 0.20,
        'popularity'         => 0.15,
        'user_history'       => 0.10,
        'seasonal_relevance' => 0.10,
    ];

    private const DIFFICULTY_MAP = [
        'beginner'     => 1,
        'intermediate' => 2,
        'advanced'     => 3,
        'expert'       => 4,
    ];

    public function __construct(\mysqli $conn) {
        $this->conn   = $conn;
        $this->logger = new LoggerService('route_recommender');
        $this->weather = new WeatherService();
    }

    // -----------------------------------------------------------------
    // IRecommender
    // -----------------------------------------------------------------

    public function getRecommendations(array $criteria, array $userPreferences, int $limit = 5): array {
        try {
            $candidates = $this->getCandidateRoutes($criteria);

            if (empty($candidates)) {
                return $this->getFallbackRecommendations($criteria);
            }

            $weatherConditions = $this->getWeatherConditions($criteria);

            $scored = [];
            foreach ($candidates as $route) {
                $score = $this->calculateRelevanceScore($route, $userPreferences, $weatherConditions);
                $scored[] = [
                    'route'   => $route,
                    'score'   => $score,
                    'reasons' => $this->generateRecommendationExplanation($route, $userPreferences),
                ];
            }

            // Ordenar por score descendente
            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

            // Filtros de seguridad
            $safe = $this->applySafetyFilters($scored, ['weather' => $weatherConditions]);

            // Si tras el filtro quedan menos de 2 rutas, devolvemos sin filtrar
            if (count($safe) < 2) {
                $safe = $scored;
            }

            return array_values(array_slice($safe, 0, $limit));

        } catch (\Exception $e) {
            $this->logger->error('Error en recomendaciones', ['error' => $e->getMessage()]);
            throw new RouteException('Error generating recommendations', ['error' => $e->getMessage()]);
        }
    }

    public function getSimilarRoutes(int $routeId, int $limit = 3): array {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM rutas WHERE id = ?");
            $stmt->bind_param("i", $routeId);
            $stmt->execute();
            $base = $stmt->get_result()->fetch_assoc();

            if (!$base) {
                throw new RouteException('Route not found');
            }

            // Buscar rutas similares: mismo tipo, misma zona, dificultad parecida
            $stmt = $this->conn->prepare(
                "SELECT r.*, rp.popularity_score
                 FROM rutas r
                 LEFT JOIN route_popularity rp ON r.id = rp.ruta_id
                 WHERE r.id != ?
                   AND r.tipo_ruta = ?
                   AND r.ubicacion   = ?
                 ORDER BY ABS(r.elevacion_ganada - ?) ASC, RAND()
                 LIMIT ?"
            );
            $elev = (float)($base['elevation'] ?? 500);
            $stmt->bind_param("issdi", $routeId, $base['tipo_ruta'], $base['location'], $elev, $limit);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            return array_map(fn($r) => [
                'route'            => $r,
                'similarity_score' => $this->calcSimilarityScore($base, $r),
                'common_features'  => $this->getCommonFeatures($base, $r),
            ], $rows);

        } catch (\Exception $e) {
            $this->logger->error('Error finding similar routes', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function calculateRelevanceScore(array $route, array $userPreferences, array $weather = []): float {
        $scores = [
            'difficulty_match'   => $this->calculateDifficultyMatch($route, $userPreferences),
            'location_match'     => $this->calculateLocationMatch($route, $userPreferences),
            'weather_conditions' => $this->calculateWeatherScore($route, $weather),
            'popularity'         => $this->calculatePopularityScore($route),
            'user_history'       => $this->calculateUserHistoryScore($route, $userPreferences),
            'seasonal_relevance' => $this->calculateSeasonalRelevance($route),
        ];

        $total = 0.0;
        foreach ($scores as $factor => $score) {
            $total += $score * self::WEIGHTS[$factor];
        }
        return round($total, 4);
    }

    public function validateRouteSuitability(array $route, array $conditions): bool {
        if (!empty($conditions['weather'])) {
            if (!$this->weather->isSafeForActivity($conditions['weather'], $route['tipo_ruta'] ?? 'hiking')) {
                return false;
            }
        }
        if (!$this->isInSeason($route)) return false;
        if (!$this->hasAdequateDaylight($route))  return false;
        return true;
    }

    public function applySafetyFilters(array $recommendations, array $conditions): array {
        return array_values(array_filter(
            $recommendations,
            fn($r) => $this->validateRouteSuitability($r['route'], $conditions)
        ));
    }

    public function rankRecommendations(array $recommendations, array $criteria): array {
        usort($recommendations, function ($a, $b) use ($criteria) {
            foreach ($criteria as $criterion => $weight) {
                $sa = $this->getCriterionScore($a['route'], $criterion);
                $sb = $this->getCriterionScore($b['route'], $criterion);
                $diff = ($sb - $sa) * $weight;
                if (abs($diff) > 0.001) return $diff > 0 ? 1 : -1;
            }
            return 0;
        });
        return $recommendations;
    }

    // -----------------------------------------------------------------
    // Scoring privado
    // -----------------------------------------------------------------

    private function calculateDifficultyMatch(array $route, array $preferences): float {
        $routeLevel = self::DIFFICULTY_MAP[$route['difficulty'] ?? 'intermediate'] ?? 2;
        $userLevel  = self::DIFFICULTY_MAP[$preferences['experience_level'] ?? 'intermediate'] ?? 2;
        $diff = $routeLevel - $userLevel;

        // Penalizar rutas demasiado difíciles más que las demasiado fáciles
        if ($diff > 0) {
            return max(0.0, 1.0 - ($diff * 0.5));
        }
        return max(0.0, 1.0 - (abs($diff) * 0.2));
    }

    private function calculateLocationMatch(array $route, array $preferences): float {
        $preferred = $preferences['preferred_locations'] ?? [];
        if (is_string($preferred)) {
            $preferred = array_map('trim', explode(',', $preferred));
        }
        if (empty($preferred)) return 0.5;
        return in_array($route['location'] ?? '', $preferred, true) ? 1.0 : 0.2;
    }

    private function calculateWeatherScore(array $route, array $weather): float {
        if (empty($weather)) return 0.5;
        $safe = $this->weather->isSafeForActivity($weather, $route['tipo_ruta'] ?? 'hiking');
        return $safe ? 1.0 : 0.0;
    }

    private function calculatePopularityScore(array $route): float {
        $raw = (float)($route['popularity_score'] ?? 0);
        // Los scores reales están entre 0 y ~0.1; normalizar a 0-1
        return min(1.0, $raw * 15);
    }

    private function calculateUserHistoryScore(array $route, array $preferences): float {
        // Si la actividad coincide con las preferidas del usuario, bonus
        $preferred = $preferences['preferred_activities'] ?? [];
        if (is_string($preferred)) {
            $preferred = array_map('trim', explode(',', $preferred));
        }
        if (empty($preferred)) return 0.5;
        return in_array($route['tipo_ruta'] ?? '', $preferred, true) ? 0.8 : 0.4;
    }

    private function calculateSeasonalRelevance(array $route): float {
        $month    = (int)date('n');
        $activity = $route['tipo_ruta'] ?? 'hiking';

        if ($activity === 'ski_touring') {
            return in_array($month, [12, 1, 2, 3, 4], true) ? 1.0 : 0.05;
        }
        if ($activity === 'hiking') {
            if (in_array($month, [6, 7, 8], true))      return 1.0;
            if (in_array($month, [5, 9, 10], true))     return 0.8;
            if (in_array($month, [4, 11], true))        return 0.5;
            return 0.3;
        }
        return 0.6;
    }

    // -----------------------------------------------------------------
    // Utilidades privadas
    // -----------------------------------------------------------------

    /**
     * Zonas válidas en la BD. Cualquier otro valor de location se ignora en el filtro
     * para no devolver 0 resultados cuando el usuario escribe un nombre libre ("Candanchú").
     */
    private const KNOWN_DB_LOCATIONS = ['Western_Pyrenees', 'Central_Pyrenees', 'Aragon', 'Euskadi'];

    /**
     * Obtiene rutas candidatas con diversidad mediante ORDER BY aleatorio ponderado.
     *
     * Modo proximidad (cuando criteria tiene 'proximity_km'):
     *   Filtra por bounding box geográfico usando startLat/startLng.
     *   Evita que zonas amplias como "Aragon" devuelvan rutas de Teruel
     *   cuando el usuario busca cerca de un lugar concreto (Candanchú, Astún…).
     *
     * Modo zona (sin proximity_km):
     *   Comportamiento original: filtro por columna location de la BD.
     */
    private function getCandidateRoutes(array $criteria): array {
        $conditions = [];
        $params     = [];
        $types      = '';

        if (!empty($criteria['difficulty'])) {
            $conditions[] = "r.dificultad = ?";
            $params[]     = $criteria['difficulty'];
            $types       .= 's';
        }

        // ── Modo proximidad: bounding box geográfico ──────────────────────────
        if (!empty($criteria['coordinates']) && !empty($criteria['proximity_km'])) {
            $cLat     = (float)$criteria['coordinates']['lat'];
            $cLon     = (float)$criteria['coordinates']['lon'];
            $km       = (float)$criteria['proximity_km'];

            // Deltas aproximadas (error < 1% en latitudes pirenaicas ≈ 42-43°)
            $latDelta = $km / 111.0;
            $lonDelta = $km / (111.0 * cos(deg2rad($cLat)));

            $conditions[] = "r.start_lat IS NOT NULL";
            $conditions[] = "r.start_lng IS NOT NULL";
            $conditions[] = "r.start_lat BETWEEN ? AND ?";
            $conditions[] = "r.start_lng BETWEEN ? AND ?";
            $params[]     = $cLat - $latDelta;
            $params[]     = $cLat + $latDelta;
            $params[]     = $cLon - $lonDelta;
            $params[]     = $cLon + $lonDelta;
            $types       .= 'dddd';
        } else {
            // ── Modo zona: filtro por columna location ────────────────────────
            // Solo si es un valor reconocido en la BD; si el usuario escribió
            // "Candanchú", "Ordesa", etc., se ignora el filtro y el scoring decide.
            $locationToFilter = $criteria['zone'] ?? $criteria['location'] ?? null;
            if (!empty($locationToFilter) && in_array($locationToFilter, self::KNOWN_DB_LOCATIONS, true)) {
                $conditions[] = "r.ubicacion = ?";
                $params[]     = $locationToFilter;
                $types       .= 's';
            }
        }

        if (!empty($criteria['activity'])) {
            $conditions[] = "r.tipo_ruta = ?";
            $params[]     = $criteria['activity'];
            $types       .= 's';
        }
        if (!empty($criteria['max_distance'])) {
            $conditions[] = "r.distancia_km <= ?";
            $params[]     = (float)$criteria['max_distance'];
            $types       .= 'd';
        }
        if (!empty($criteria['max_duration'])) {
            $conditions[] = "r.duracion_horas <= ?";
            $params[]     = (int)$criteria['max_duration'];
            $types       .= 'i';
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // ORDER BY aleatorio ponderado: rutas populares salen más pero no siempre las mismas
        $query = "SELECT r.*, rp.view_count, rp.download_count, rp.favorite_count,
                         rp.complete_count, rp.popularity_score
                  FROM rutas r
                  LEFT JOIN route_popularity rp ON r.id = rp.ruta_id
                  $where
                  ORDER BY (COALESCE(rp.popularity_score, 0.001) * 10 + RAND()) DESC
                  LIMIT 50";

        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Guardar IDs del pool completo para route_followup filtros (sin re-ejecutar el pipeline)
        $this->lastCandidateIds = array_map(fn($r) => (int)$r['id'], $rows);

        return $rows;
    }

    public function getFallbackRecommendations(array $criteria, array $conditions = []): array {
        // Relajar criterios: quitar dificultad y probar solo con actividad/zona
        $relaxed    = array_intersect_key($criteria, array_flip(['activity', 'location']));
        $candidates = $this->getCandidateRoutes($relaxed);
        return array_slice(array_map(fn($r) => [
            'route'   => $r,
            'score'   => 0.3,
            'reasons' => ['Sugerencia alternativa — no se encontraron rutas exactas'],
        ], $candidates), 0, 3);
    }

    private function getWeatherConditions(array $criteria): array {
        if (empty($criteria['coordinates'])) return [];
        try {
            return $this->weather->getCurrentWeather(
                (float)$criteria['coordinates']['lat'],
                (float)$criteria['coordinates']['lon'],
                ['elevation' => $criteria['elevation'] ?? null]
            );
        } catch (\Exception) {
            return [];
        }
    }

    public function generateRecommendationExplanation(array $route, array $preferences): array {
        $reasons = [];

        $userLevel  = self::DIFFICULTY_MAP[$preferences['experience_level'] ?? 'intermediate'] ?? 2;
        $routeLevel = self::DIFFICULTY_MAP[$route['difficulty'] ?? 'intermediate'] ?? 2;
        if ($routeLevel === $userLevel)  $reasons[] = 'Dificultad perfecta para tu nivel';
        if ($routeLevel < $userLevel)    $reasons[] = 'Buena opción de calentamiento';

        if (($route['popularity_score'] ?? 0) > 0.03) {
            $reasons[] = 'Muy bien valorada por otros usuarios';
        }

        $preferred = $preferences['preferred_locations'] ?? [];
        if (is_string($preferred)) $preferred = explode(',', $preferred);
        if (in_array($route['location'] ?? '', $preferred, true)) {
            $reasons[] = 'En tu zona habitual';
        }

        if ($this->calculateSeasonalRelevance($route) >= 0.8) {
            $reasons[] = 'Ideal para la época del año';
        }

        return $reasons ?: ['Ruta adecuada para tus criterios'];
    }

    private function getCommonFeatures(array $r1, array $r2): array {
        $f = [];
        if (($r1['location']   ?? '') === ($r2['location']   ?? '')) $f[] = 'misma zona';
        if (($r1['difficulty'] ?? '') === ($r2['difficulty'] ?? '')) $f[] = 'misma dificultad';
        if (($r1['tipo_ruta']  ?? '') === ($r2['tipo_ruta']  ?? '')) $f[] = 'mismo tipo de actividad';
        return $f;
    }

    private function calcSimilarityScore(array $base, array $other): float {
        $score = 0.0;
        if (($base['location']   ?? '') === ($other['location']   ?? '')) $score += 0.4;
        if (($base['difficulty'] ?? '') === ($other['difficulty'] ?? '')) $score += 0.3;
        if (($base['tipo_ruta']  ?? '') === ($other['tipo_ruta']  ?? '')) $score += 0.3;
        return $score;
    }

    private function getCriterionScore(array $route, string $criterion): float {
        return match($criterion) {
            'difficulty' => (self::DIFFICULTY_MAP[$route['difficulty'] ?? 'intermediate'] ?? 2) / 4,
            'popularity' => min(1.0, ($route['popularity_score'] ?? 0) * 15),
            'distance'   => min(1.0, (float)($route['length'] ?? 10) / 30),
            'elevation'  => min(1.0, (float)($route['elevation'] ?? 500) / 2000),
            default      => 0.5,
        };
    }

    private function isInSeason(array $route): bool {
        $month    = (int)date('n');
        $activity = $route['tipo_ruta'] ?? 'hiking';
        if ($activity === 'ski_touring') {
            return in_array($month, [12, 1, 2, 3, 4], true);
        }
        return true;
    }

    private function hasAdequateDaylight(array $route): bool {
        $hour     = (int)date('G');
        $duration = (int)($route['duration'] ?? 4);
        return ($hour + $duration) <= 21;
    }

    // -----------------------------------------------------------------
    // IRecommender — métodos adicionales requeridos por la interfaz
    // -----------------------------------------------------------------

    public function updateUserPreferences(int $userId, array $interactions): bool {
        if (empty($interactions)) return true;
        try {
            $activities = [];
            $locations  = [];
            foreach ($interactions as $inter) {
                $r = $inter['route'] ?? $inter;
                if (!empty($r['tipo_ruta'])) $activities[] = $r['tipo_ruta'];
                if (!empty($r['location']))  $locations[]  = $r['location'];
            }
            $topActivity = !empty($activities)
                ? array_search(max(array_count_values($activities)), array_count_values($activities))
                : null;
            $topLocation = !empty($locations)
                ? array_search(max(array_count_values($locations)), array_count_values($locations))
                : null;

            $sets  = [];
            $vals  = [];
            $types = '';
            if ($topActivity) { $sets[] = 'preferred_activities = ?'; $vals[] = $topActivity; $types .= 's'; }
            if ($topLocation)  { $sets[] = 'preferred_locations = ?';  $vals[] = $topLocation;  $types .= 's'; }
            if (empty($sets)) return true;

            $sets[]  = 'last_updated = NOW()';
            $vals[]  = $userId;
            $types  .= 'i';

            $stmt = $this->conn->prepare(
                'UPDATE user_preferences SET ' . implode(', ', $sets) . ' WHERE user_id = ?'
            );
            $stmt->bind_param($types, ...$vals);
            return $stmt->execute();
        } catch (\Exception $e) {
            $this->logger->error('Error actualizando preferencias', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function validateSearchCriteria(array $criteria): bool {
        // Validar tipos y rangos básicos
        if (isset($criteria['max_distance']) && (float)$criteria['max_distance'] <= 0) return false;
        if (isset($criteria['max_duration']) && (int)$criteria['max_duration']   <= 0) return false;
        if (isset($criteria['difficulty']) &&
            !array_key_exists($criteria['difficulty'], self::DIFFICULTY_MAP)) return false;
        return true;
    }

    public function processFeedback(int $userId, int $routeId, array $feedback): bool {
        try {
            $type = $feedback['type'] ?? 'view';  // 'like', 'dislike', 'complete', 'view'
            $stmt = $this->conn->prepare(
                'INSERT INTO user_interactions (usuario_id, ruta_id, tipo) VALUES (?, ?, ?)'
            );
            $stmt->bind_param('iis', $userId, $routeId, $type);
            return $stmt->execute();
        } catch (\Exception $e) {
            $this->logger->error('Error procesando feedback', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
