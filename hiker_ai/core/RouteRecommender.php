<?php
namespace KahirAI\Core;

use KahirAI\Services\{WeatherService, LoggerService};
use KahirAI\Interfaces\IRecommender;
use KahirAI\Exceptions\RouteException;

/**
 * Recomendador de rutas.
 *
 * Búsqueda por distancia real (Haversine sobre start_lat/start_lng) alrededor de un punto,
 * o dentro de la caja de una región. La columna `ubicacion` de la BD no se usa para filtrar:
 * el 70 % de las rutas está en "Other" y no sirve para localizar.
 *
 * Criterios aceptados por getRecommendations():
 *   coordinates ['lat','lon']  centro de búsqueda
 *   radius_km                  radio (por defecto 30 km)
 *   bbox [s, n, w, e]          caja de la región (si se da, filtra por ella y ordena por cercanía al centro)
 *   activity                   hiking | ski_touring | cycle_touring | btt
 *   difficulty | difficulty_in nivel exacto o lista de niveles admitidos
 *   min/max_distance (km), min/max_duration (h), max_elevation / min_elevation (m de desnivel)
 *   target_duration (h)        duración deseada ("unas 6 horas")
 *   exclude_ids                rutas a no repetir
 */
class RouteRecommender implements IRecommender {

    private LoggerService $logger;
    private WeatherService $weather;
    private \mysqli $conn;

    /** IDs del último pool de candidatos (pre-scoring). */
    private array $lastCandidateIds = [];

    public function getLastCandidateIds(): array { return $this->lastCandidateIds; }

    /** Rutas basura: tramos minúsculos, enlaces o rutas sin nombre importadas de OSM. */
    public const MIN_ROUTE_KM = 1.5;

    private const WEIGHTS = [
        'proximity'          => 0.30,
        'difficulty_match'   => 0.25,
        'size_match'         => 0.15,
        'popularity'         => 0.10,
        'user_history'       => 0.10,
        'seasonal_relevance' => 0.10,
    ];

    private const DIFFICULTY_MAP = [
        'beginner'     => 1,
        'intermediate' => 2,
        'advanced'     => 3,
        'expert'       => 4,
    ];

    private const LEVEL_ES = [
        'beginner' => 'fácil', 'intermediate' => 'intermedia', 'advanced' => 'difícil', 'expert' => 'muy difícil',
    ];

    /** Rutas ya hechas / favoritas del usuario (cache por petición). */
    private array $userRouteSets = [];

    public function __construct(\mysqli $conn) {
        $this->conn    = $conn;
        $this->logger  = new LoggerService('route_recommender');
        $this->weather = new WeatherService();
    }

    // -----------------------------------------------------------------
    // IRecommender
    // -----------------------------------------------------------------

    public function getRecommendations(array $criteria, array $userPreferences, int $limit = 5): array {
        try {
            $candidates = $this->getCandidateRoutes($criteria);
            if (empty($candidates)) {
                return [];
            }

            $userId = (int) ($userPreferences['_user_id'] ?? 0);
            $sets   = $this->getUserRouteSets($userId);

            $scored = [];
            foreach ($candidates as $route) {
                $id = (int) $route['id'];
                $route['_completed'] = isset($sets['completed'][$id]);
                $route['_favorite']  = isset($sets['favorites'][$id]);
                $score = $this->scoreRoute($route, $userPreferences, $criteria);
                // Las rutas ya hechas bajan mucho (pero pueden salir si no hay otra cosa)
                if ($route['_completed']) $score *= 0.4;
                $scored[] = [
                    'route'   => $route,
                    'score'   => $score,
                    'reasons' => $this->buildReasons($route, $userPreferences, $criteria),
                ];
            }

            usort($scored, fn($a, $b) => $b['score'] <=> $a['score'] ?: ((float) $a['route']['dist_km'] <=> (float) $b['route']['dist_km']));

            // Fuera de temporada (p. ej. esquí de travesía en verano): solo se quitan si quedan otras
            $inSeason = array_values(array_filter($scored, fn($r) => $this->isInSeason($r['route'])));
            if (count($inSeason) >= min(2, count($scored))) {
                $scored = $inSeason;
            }

            return array_slice($this->dedupe($scored), 0, $limit);

        } catch (\Exception $e) {
            $this->logger->error('Error en recomendaciones', ['error' => $e->getMessage()]);
            throw new RouteException('Error generating recommendations', ['error' => $e->getMessage()]);
        }
    }

    public function getSimilarRoutes(int $routeId, int $limit = 3): array {
        $base = $this->getRouteById($routeId);
        if (!$base) return [];
        $criteria = [
            'coordinates' => ['lat' => (float) $base['start_lat'], 'lon' => (float) $base['start_lng']],
            'radius_km'   => 25,
            'activity'    => $base['tipo_ruta'],
            'exclude_ids' => [$routeId],
            'min_distance' => max(self::MIN_ROUTE_KM, (float) $base['distancia_km'] * 0.6),
            'max_distance' => (float) $base['distancia_km'] * 1.5,
        ];
        return array_map(fn($r) => [
            'route'            => $r['route'],
            'similarity_score' => $r['score'],
            'common_features'  => $r['reasons'],
        ], $this->getRecommendations($criteria, [], $limit));
    }

    /** Puntuación 0–1 (interfaz). */
    public function calculateRelevanceScore(array $route, array $userPreferences, array $criteria = []): float {
        return $this->scoreRoute($route, $userPreferences, $criteria);
    }

    public function validateRouteSuitability(array $route, array $conditions): bool {
        return $this->isInSeason($route);
    }

    public function applySafetyFilters(array $recommendations, array $conditions): array {
        return array_values(array_filter($recommendations, fn($r) => $this->validateRouteSuitability($r['route'], $conditions)));
    }

    public function rankRecommendations(array $recommendations, array $criteria): array {
        usort($recommendations, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        return $recommendations;
    }

    public function generateRecommendationExplanation(array $route, array $preferences): array {
        return $this->buildReasons($route, $preferences, []);
    }

    /** Relaja la dificultad manteniendo zona y actividad. */
    public function getFallbackRecommendations(array $criteria, array $conditions = []): array {
        unset($criteria['difficulty'], $criteria['difficulty_in']);
        return $this->getRecommendations($criteria, $conditions['prefs'] ?? [], 5);
    }

    public function validateSearchCriteria(array $criteria): bool {
        if (isset($criteria['max_distance']) && (float) $criteria['max_distance'] <= 0) return false;
        if (isset($criteria['max_duration']) && (float) $criteria['max_duration'] <= 0) return false;
        if (isset($criteria['difficulty']) && !array_key_exists($criteria['difficulty'], self::DIFFICULTY_MAP)) return false;
        return true;
    }

    public function updateUserPreferences(int $userId, array $interactions): bool {
        // Las preferencias se infieren en UserPreferenceService; aquí no se duplica esa lógica.
        return true;
    }

    public function processFeedback(int $userId, int $routeId, array $feedback): bool {
        try {
            $type = in_array($feedback['type'] ?? '', ['view', 'download', 'favorite', 'complete'], true) ? $feedback['type'] : 'view';
            $stmt = $this->conn->prepare('INSERT INTO user_interactions (usuario_id, ruta_id, tipo) VALUES (?, ?, ?)');
            $stmt->bind_param('iis', $userId, $routeId, $type);
            return $stmt->execute();
        } catch (\Exception $e) {
            $this->logger->error('Error procesando feedback', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // -----------------------------------------------------------------
    // Búsqueda
    // -----------------------------------------------------------------

    /** Ruta completa por id (o null). */
    public function getRouteById(int $id): ?array {
        $stmt = $this->conn->prepare(
            "SELECT r.*, rp.popularity_score FROM rutas r
             LEFT JOIN route_popularity rp ON rp.ruta_id = r.id
             WHERE r.id = ? AND r.estado = 'publicada' LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    private function getCandidateRoutes(array $criteria): array {
        $conditions = [
            "r.estado = 'publicada'",
            "r.distancia_km >= ?",
            "r.titulo NOT LIKE 'Ruta OSM %'",
            "r.start_lat IS NOT NULL",
        ];
        $params = [max(self::MIN_ROUTE_KM, (float) ($criteria['min_distance'] ?? 0))];
        $types  = 'd';

        $levels = $criteria['difficulty_in'] ?? (!empty($criteria['difficulty']) ? [$criteria['difficulty']] : []);
        $levels = array_values(array_intersect($levels, array_keys(self::DIFFICULTY_MAP)));
        if ($levels) {
            $conditions[] = 'r.dificultad IN (' . implode(',', array_fill(0, count($levels), '?')) . ')';
            array_push($params, ...$levels);
            $types .= str_repeat('s', count($levels));
        }
        if (!empty($criteria['activity'])) {
            $conditions[] = 'r.tipo_ruta = ?';
            $params[] = $criteria['activity'];
            $types   .= 's';
        }
        foreach ([
            'max_distance'  => 'r.distancia_km <= ?',
            'max_duration'  => 'r.duracion_horas <= ?',
            'min_duration'  => 'r.duracion_horas >= ?',
            'max_elevation' => 'r.elevacion_ganada <= ?',
            'min_elevation' => 'r.elevacion_ganada >= ?',
        ] as $key => $sql) {
            if (isset($criteria[$key]) && $criteria[$key] !== null && $criteria[$key] !== '') {
                $conditions[] = $sql;
                $params[] = (float) $criteria[$key];
                $types   .= 'd';
            }
        }
        $exclude = array_values(array_filter(array_map('intval', $criteria['exclude_ids'] ?? [])));
        if ($exclude) {
            $conditions[] = 'r.id NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')';
            array_push($params, ...$exclude);
            $types .= str_repeat('i', count($exclude));
        }

        // ── Geografía ──────────────────────────────────────────────────────
        $distExpr = '0';
        $having   = '';
        $order    = 'COALESCE(rp.popularity_score, 0) DESC, r.id ASC';
        $distParams = [];
        if (!empty($criteria['coordinates'])) {
            $lat = (float) $criteria['coordinates']['lat'];
            $lon = (float) $criteria['coordinates']['lon'];
            $distExpr = '6371 * 2 * ASIN(LEAST(1, SQRT(POWER(SIN(RADIANS(r.start_lat - ?) / 2), 2) + '
                      . 'COS(RADIANS(?)) * COS(RADIANS(r.start_lat)) * POWER(SIN(RADIANS(r.start_lng - ?) / 2), 2))))';
            $distParams = [$lat, $lat, $lon];

            if (!empty($criteria['bbox']) && count($criteria['bbox']) === 4) {
                [$s, $n, $w, $e] = array_map('floatval', $criteria['bbox']);
                $conditions[] = 'r.start_lat BETWEEN ? AND ? AND r.start_lng BETWEEN ? AND ?';
                array_push($params, $s, $n, $w, $e);
                $types .= 'dddd';
            } else {
                $km = (float) ($criteria['radius_km'] ?? 30);
                $latD = $km / 111.0;
                $lonD = $km / (111.0 * max(0.2, cos(deg2rad($lat))));
                $conditions[] = 'r.start_lat BETWEEN ? AND ? AND r.start_lng BETWEEN ? AND ?';
                array_push($params, $lat - $latD, $lat + $latD, $lon - $lonD, $lon + $lonD);
                $types .= 'dddd';
                $having = 'HAVING dist_km <= ' . number_format($km, 2, '.', '');
            }
            $order = 'dist_km ASC';
        }

        $query = "SELECT r.*, rp.popularity_score, $distExpr AS dist_km
                  FROM rutas r
                  LEFT JOIN route_popularity rp ON r.id = rp.ruta_id
                  WHERE " . implode(' AND ', $conditions) . "
                  $having
                  ORDER BY $order
                  LIMIT 80";

        $stmt = $this->conn->prepare($query);
        $allParams = array_merge($distParams, $params);
        $stmt->bind_param(str_repeat('d', count($distParams)) . $types, ...$allParams);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $this->lastCandidateIds = array_map(fn($r) => (int) $r['id'], $rows);
        return $rows;
    }

    // -----------------------------------------------------------------
    // Puntuación
    // -----------------------------------------------------------------

    private function scoreRoute(array $route, array $prefs, array $criteria): float {
        $scores = [
            'proximity'          => $this->proximityScore($route, $criteria),
            'difficulty_match'   => $this->calculateDifficultyMatch($route, $prefs, $criteria),
            'size_match'         => $this->sizeMatch($route, $prefs, $criteria),
            'popularity'         => min(1.0, (float) ($route['popularity_score'] ?? 0) * 15),
            'user_history'       => $this->userHistoryScore($route, $prefs),
            'seasonal_relevance' => $this->calculateSeasonalRelevance($route),
        ];
        $total = 0.0;
        foreach ($scores as $k => $v) $total += $v * self::WEIGHTS[$k];
        return round($total, 4);
    }

    private function proximityScore(array $route, array $criteria): float {
        if (empty($criteria['coordinates']) || !isset($route['dist_km'])) return 0.5;
        $radius = (float) ($criteria['radius_km'] ?? 40);
        return max(0.0, 1.0 - (float) $route['dist_km'] / max(10.0, $radius * 1.2));
    }

    private function calculateDifficultyMatch(array $route, array $preferences, array $criteria = []): float {
        // Si el usuario pidió un nivel en el mensaje, ese nivel es la referencia
        $prefs = $preferences;
        if (!empty($criteria['difficulty'])) {
            $prefs['experience_level'] = $criteria['difficulty'];
            $prefs['comfortable_effort'] = null;
        }
        $levelMatch  = $this->calculateLevelMatch($route, $prefs);
        $effortMatch = $this->calculateEffortMatch($route, $prefs);
        return $effortMatch === null ? $levelMatch : round(0.4 * $levelMatch + 0.6 * $effortMatch, 4);
    }

    /** Encaje entre el esfuerzo MIDE (1–5) de la ruta y el esfuerzo cómodo del usuario. */
    private function calculateEffortMatch(array $route, array $preferences): ?float {
        $comfortable = $preferences['comfortable_effort'] ?? null;
        $effort      = $route['esfuerzo'] ?? null;
        if ($comfortable === null || $effort === null || $effort === '') return null;
        $diff = (float) $effort - (float) $comfortable;
        return $diff > 0 ? max(0.0, 1.0 - $diff * 0.45) : max(0.0, 1.0 - abs($diff) * 0.2);
    }

    private function calculateLevelMatch(array $route, array $preferences): float {
        $routeLevel = self::DIFFICULTY_MAP[$route['dificultad'] ?? 'intermediate'] ?? 2;
        $userLevel  = self::DIFFICULTY_MAP[$preferences['experience_level'] ?? 'intermediate'] ?? 2;
        $diff = $routeLevel - $userLevel;
        return $diff > 0 ? max(0.0, 1.0 - $diff * 0.5) : max(0.0, 1.0 - abs($diff) * 0.2);
    }

    /** Encaje de duración/distancia con lo pedido o con lo habitual del usuario. */
    private function sizeMatch(array $route, array $prefs, array $criteria): float {
        $h = (float) ($route['horas_mide'] ?? $route['duracion_horas'] ?? 0);
        $target = (float) ($criteria['target_duration'] ?? 0);
        if ($target > 0 && $h > 0) {
            return max(0.0, 1.0 - abs($h - $target) / $target);
        }
        // Sin petición explícita: rutas de jornada razonable (2–6 h) puntúan más que paseos o travesías
        if ($h <= 0) return 0.5;
        if ($h < 1)  return 0.3;
        if ($h <= 6) return 1.0;
        return max(0.2, 1.0 - ($h - 6) * 0.1);
    }

    private function userHistoryScore(array $route, array $prefs): float {
        if (!empty($route['_favorite'])) return 1.0;
        $preferred = $prefs['preferred_activities'] ?? [];
        if (is_string($preferred)) $preferred = array_map('trim', explode(',', $preferred));
        if (empty($preferred)) return 0.5;
        return in_array($route['tipo_ruta'] ?? '', $preferred, true) ? 0.8 : 0.4;
    }

    private function calculateSeasonalRelevance(array $route): float {
        $month    = (int) date('n');
        $activity = $route['tipo_ruta'] ?? 'hiking';
        if ($activity === 'ski_touring') {
            return in_array($month, [12, 1, 2, 3, 4], true) ? 1.0 : 0.05;
        }
        if ($activity === 'hiking') {
            if (in_array($month, [6, 7, 8], true))  return 1.0;
            if (in_array($month, [5, 9, 10], true)) return 0.8;
            if (in_array($month, [4, 11], true))    return 0.6;
            return 0.5;
        }
        return 0.6;
    }

    private function isInSeason(array $route): bool {
        if (($route['tipo_ruta'] ?? '') === 'ski_touring') {
            return in_array((int) date('n'), [11, 12, 1, 2, 3, 4, 5], true);
        }
        return true;
    }

    // -----------------------------------------------------------------
    // Utilidades
    // -----------------------------------------------------------------

    /** Motivos concretos y verificables de por qué sale cada ruta. */
    private function buildReasons(array $route, array $prefs, array $criteria): array {
        $reasons = [];
        if (isset($route['dist_km']) && !empty($criteria['coordinates'])) {
            $d = (float) $route['dist_km'];
            $place = $criteria['place_label'] ?? null;
            $reasons[] = $d < 3
                ? 'Sale desde ' . ($place ?: 'el mismo lugar')
                : 'Empieza a ' . round($d) . ' km' . ($place ? " de $place" : '');
        }
        $level = $route['dificultad'] ?? null;
        if (!empty($criteria['difficulty']) && $level === $criteria['difficulty']) {
            $reasons[] = 'Dificultad ' . (self::LEVEL_ES[$level] ?? $level) . ', la que pides';
        } elseif (($c = $prefs['comfortable_effort'] ?? null) !== null && isset($route['esfuerzo'])
                  && abs((float) $route['esfuerzo'] - (float) $c) < 0.75) {
            $reasons[] = "Esfuerzo {$route['esfuerzo']}/5, como las que valoras cómodas";
        } elseif ($level && $level === ($prefs['experience_level'] ?? null)) {
            $reasons[] = 'Encaja con tu nivel (' . (self::LEVEL_ES[$level] ?? $level) . ')';
        }
        $h = (float) ($route['horas_mide'] ?? $route['duracion_horas'] ?? 0);
        if (!empty($criteria['target_duration']) && $h > 0 && abs($h - $criteria['target_duration']) <= 1.5) {
            $reasons[] = 'Unas ' . str_replace('.', ',', (string) round($h, 1)) . ' h, cerca de lo que buscas';
        }
        if (!empty($route['_favorite']))  $reasons[] = 'Está en tus favoritas';
        if (!empty($route['_completed'])) $reasons[] = 'Ya la has hecho';
        if (($route['popularity_score'] ?? 0) > 0.03) $reasons[] = 'Muy valorada por otros usuarios';
        return $reasons ?: ['Cumple lo que pides'];
    }

    /** Quita duplicados evidentes (mismo nombre normalizado). */
    private function dedupe(array $scored): array {
        $seen = [];
        $out  = [];
        foreach ($scored as $r) {
            $key = preg_replace('/[^a-z0-9]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $r['route']['titulo']) ?: ''));
            if ($key !== '' && isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $r;
        }
        return $out;
    }

    private function getUserRouteSets(int $userId): array {
        if (!$userId) return ['completed' => [], 'favorites' => []];
        if (isset($this->userRouteSets[$userId])) return $this->userRouteSets[$userId];
        $sets = ['completed' => [], 'favorites' => []];
        foreach (['completed' => 'user_completed', 'favorites' => 'user_favorites'] as $k => $table) {
            try {
                $st = $this->conn->prepare("SELECT ruta_id FROM $table WHERE usuario_id = ?");
                $st->bind_param('i', $userId);
                $st->execute();
                foreach ($st->get_result()->fetch_all(MYSQLI_NUM) as [$rid]) $sets[$k][(int) $rid] = true;
            } catch (\Throwable) { /* tabla opcional */ }
        }
        return $this->userRouteSets[$userId] = $sets;
    }
}
