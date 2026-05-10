<?php
/**
 * Analizador de condiciones meteorológicas para Kahir.AI
 * 
 * Procesa y analiza datos meteorológicos para determinar
 * condiciones de seguridad y recomendaciones para actividades.
 */

namespace KahirAI\Core;

use KahirAI\Services\LoggerService;
use KahirAI\Exceptions\WeatherException;

class WeatherAnalyzer {
    /**
     * @var LoggerService
     */
    private LoggerService $logger;

    /**
     * @var array Umbrales para diferentes condiciones
     */
    private const CONDITION_THRESHOLDS = [
        'wind' => [
            'safe' => 20,          // km/h
            'warning' => 35,       // km/h
            'dangerous' => 50      // km/h
        ],
        'temperature' => [
            'min_safe' => -10,     // °C
            'max_safe' => 30,      // °C
            'min_warning' => -15,  // °C
            'max_warning' => 35    // °C
        ],
        'precipitation' => [
            'light' => 2,          // mm/h
            'moderate' => 5,       // mm/h
            'heavy' => 10          // mm/h
        ],
        'visibility' => [
            'poor' => 1000,        // metros
            'moderate' => 3000,    // metros
            'good' => 5000         // metros
        ],
        'snow_accumulation' => [
            'light' => 5,          // cm
            'moderate' => 15,      // cm
            'heavy' => 30          // cm
        ],
        'avalanche_risk' => [
            'low' => 1,
            'moderate' => 2,
            'considerable' => 3,
            'high' => 4,
            'extreme' => 5
        ]
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new LoggerService('weather_analyzer');
    }

    /**
     * Analiza condiciones meteorológicas para una actividad
     *
     * @param array $weatherData Datos meteorológicos
     * @param string $activity Tipo de actividad
     * @return array Análisis detallado
     */
    public function analyzeConditions(array $weatherData, string $activity): array {
        try {
            $baseAnalysis = $this->performBaseAnalysis($weatherData);
            $activitySpecific = $this->analyzeForActivity($weatherData, $activity);
            $risks = $this->assessRisks($weatherData, $activity);
            $recommendations = $this->generateRecommendations($baseAnalysis, $activitySpecific, $risks);

            return [
                'safety_level' => $this->determineSafetyLevel($risks),
                'base_conditions' => $baseAnalysis,
                'activity_specific' => $activitySpecific,
                'risks' => $risks,
                'recommendations' => $recommendations,
                'is_safe' => $this->isSafeForActivity($risks),
                'valid_hours' => $this->calculateValidHours($weatherData, $activity)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error analyzing weather conditions', [
                'activity' => $activity,
                'error' => $e->getMessage()
            ]);
            throw new WeatherException('Error analyzing conditions', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Realiza análisis base de las condiciones
     *
     * @param array $weatherData Datos meteorológicos
     * @return array
     */
    private function performBaseAnalysis(array $weatherData): array {
        return [
            'wind' => $this->analyzeWind($weatherData),
            'temperature' => $this->analyzeTemperature($weatherData),
            'precipitation' => $this->analyzePrecipitation($weatherData),
            'visibility' => $this->analyzeVisibility($weatherData),
            'comfort_level' => $this->calculateComfortLevel($weatherData)
        ];
    }

    /**
     * Analiza condiciones específicas para una actividad
     *
     * @param array $weatherData Datos meteorológicos
     * @param string $activity Tipo de actividad
     * @return array
     */
    private function analyzeForActivity(array $weatherData, string $activity): array {
        $analysis = [];

        switch ($activity) {
            case 'ski_touring':
                $analysis = $this->analyzeSkiConditions($weatherData);
                break;
            case 'hiking':
                $analysis = $this->analyzeHikingConditions($weatherData);
                break;
            case 'climbing':
                $analysis = $this->analyzeClimbingConditions($weatherData);
                break;
            // Otros casos según actividad
        }

        return $analysis;
    }

    /**
     * Evalúa los riesgos meteorológicos
     *
     * @param array $weatherData Datos meteorológicos
     * @param string $activity Tipo de actividad
     * @return array
     */
    private function assessRisks(array $weatherData, string $activity): array {
        $risks = [];

        // Riesgos por viento
        if ($weatherData['wind_speed'] > self::CONDITION_THRESHOLDS['wind']['dangerous']) {
            $risks['wind'] = [
                'level' => 'high',
                'description' => 'Vientos extremadamente fuertes'
            ];
        } elseif ($weatherData['wind_speed'] > self::CONDITION_THRESHOLDS['wind']['warning']) {
            $risks['wind'] = [
                'level' => 'moderate',
                'description' => 'Vientos fuertes'
            ];
        }

        // Riesgos por temperatura
        if ($weatherData['temp'] < self::CONDITION_THRESHOLDS['temperature']['min_warning']) {
            $risks['temperature'] = [
                'level' => 'high',
                'description' => 'Temperatura extremadamente baja'
            ];
        } elseif ($weatherData['temp'] > self::CONDITION_THRESHOLDS['temperature']['max_warning']) {
            $risks['temperature'] = [
                'level' => 'high',
                'description' => 'Temperatura extremadamente alta'
            ];
        }

        // Riesgos específicos por actividad
        switch ($activity) {
            case 'ski_touring':
                $risks = array_merge($risks, $this->assessSkiRisks($weatherData));
                break;
            case 'hiking':
                $risks = array_merge($risks, $this->assessHikingRisks($weatherData));
                break;
            // Otros casos
        }

        return $risks;
    }

    /**
     * Genera recomendaciones basadas en el análisis
     *
     * @param array $baseAnalysis Análisis base
     * @param array $activitySpecific Análisis específico de actividad
     * @param array $risks Riesgos identificados
     * @return array
     */
    private function generateRecommendations(array $baseAnalysis, array $activitySpecific, array $risks): array {
        $recommendations = [];

        // Recomendaciones basadas en viento
        if (isset($baseAnalysis['wind']['speed_rating']) && $baseAnalysis['wind']['speed_rating'] === 'high') {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Se recomienda extrema precaución debido a vientos fuertes',
                'equipment' => ['Ropa cortavientos', 'Protección adicional']
            ];
        }

        // Recomendaciones basadas en temperatura
        if (isset($baseAnalysis['temperature']['comfort_level']) && $baseAnalysis['temperature']['comfort_level'] === 'cold') {
            $recommendations[] = [
                'type' => 'equipment',
                'message' => 'Se recomienda ropa térmica adicional',
                'equipment' => ['Capas térmicas', 'Guantes', 'Gorro']
            ];
        }

        // Recomendaciones basadas en riesgos
        foreach ($risks as $risk) {
            if ($risk['level'] === 'high') {
                $recommendations[] = [
                    'type' => 'safety',
                    'message' => $risk['description'],
                    'priority' => 'high'
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Calcula el nivel de confort térmico
     *
     * @param array $weatherData Datos meteorológicos
     * @return array
     */
    private function calculateComfortLevel(array $weatherData): array {
        $temp = $weatherData['temp'];
        $humidity = $weatherData['humidity'];
        $windSpeed = $weatherData['wind_speed'];

        // Calcular sensación térmica
        $windChill = $this->calculateWindChill($temp, $windSpeed);
        
        // Calcular índice de calor
        $heatIndex = $this->calculateHeatIndex($temp, $humidity);

        return [
            'wind_chill' => $windChill,
            'heat_index' => $heatIndex,
            'comfort_rating' => $this->rateComfortLevel($windChill, $heatIndex),
            'recommendations' => $this->getComfortRecommendations($windChill, $heatIndex)
        ];
    }

    /**
     * Calcula las horas válidas para una actividad
     *
     * @param array $weatherData Datos meteorológicos
     * @param string $activity Tipo de actividad
     * @return array
     */
    private function calculateValidHours(array $weatherData, string $activity): array {
        $validHours = [];
        
        // Si hay pronóstico horario disponible
        if (isset($weatherData['hourly'])) {
            foreach ($weatherData['hourly'] as $hour => $conditions) {
                if ($this->isHourSafeForActivity($conditions, $activity)) {
                    $validHours[] = $hour;
                }
            }
        }

        return [
            'hours' => $validHours,
            'best_start_time' => $this->determineBestStartTime($validHours, $activity),
            'duration_window' => count($validHours)
        ];
    }

    /**
     * Verifica si una hora es segura para una actividad
     *
     * @param array $conditions Condiciones en esa hora
     * @param string $activity Tipo de actividad
     * @return bool
     */
    private function isHourSafeForActivity(array $conditions, string $activity): bool {
        // Verificar condiciones básicas
        if ($conditions['wind_speed'] > self::CONDITION_THRESHOLDS['wind']['dangerous']) {
            return false;
        }

        if ($conditions['temp'] < self::CONDITION_THRESHOLDS['temperature']['min_warning'] ||
            $conditions['temp'] > self::CONDITION_THRESHOLDS['temperature']['max_warning']) {
            return false;
        }

        // Verificaciones específicas por actividad
        switch ($activity) {
            case 'ski_touring':
                return $this->isHourSafeForSkiing($conditions);
            case 'hiking':
                return $this->isHourSafeForHiking($conditions);
            // Otros casos
        }

        return true;
    }

    /**
     * Determina la mejor hora para comenzar la actividad
     *
     * @param array $validHours Horas válidas
     * @param string $activity Tipo de actividad
     * @return int|null
     */
    private function determineBestStartTime(array $validHours, string $activity): ?int {
        if (empty($validHours)) {
            return null;
        }

        // Preferencias por actividad
        $activityPreferences = [
            'ski_touring' => ['ideal_start' => 7, 'max_start' => 11],
            'hiking' => ['ideal_start' => 8, 'max_start' => 14],
            'climbing' => ['ideal_start' => 8, 'max_start' => 12]
        ];

        $prefs = $activityPreferences[$activity] ?? ['ideal_start' => 8, 'max_start' => 12];

        // Encontrar la hora válida más cercana a la ideal
        $idealStart = $prefs['ideal_start'];
        $bestHour = null;
        $minDiff = 24;

        foreach ($validHours as $hour) {
            $diff = abs($hour - $idealStart);
            if ($diff < $minDiff && $hour <= $prefs['max_start']) {
                $minDiff = $diff;
                $bestHour = $hour;
            }
        }

        return $bestHour;
    }
}