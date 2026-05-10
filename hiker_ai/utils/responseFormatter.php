<?php
/**
 * Utilidad para formatear respuestas del sistema
 * 
 * Proporciona métodos para formatear respuestas consistentes,
 * incluyendo mensajes, tarjetas de ruta y datos meteorológicos.
 */

namespace KahirAI\Utils;

class ResponseFormatter {
    /**
     * Traducciones para diferentes elementos de la respuesta
     */
    private const TRANSLATIONS = [
        'difficulty' => [
            'es' => ['beginner' => 'Principiante', 'intermediate' => 'Intermedio', 'advanced' => 'Avanzado'],
            'en' => ['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'],
            'fr' => ['beginner' => 'Débutant', 'intermediate' => 'Intermédiaire', 'advanced' => 'Avancé'],
            'de' => ['beginner' => 'Anfänger', 'intermediate' => 'Fortgeschritten', 'advanced' => 'Experte'],
            'eu' => ['beginner' => 'Hasiberria', 'intermediate' => 'Tartekoa', 'advanced' => 'Aurreratua']
        ]
    ];

    /**
     * Formatea una respuesta exitosa
     *
     * @param mixed $data Datos de la respuesta
     * @param string $message Mensaje opcional
     * @return array
     */
    public static function success($data = null, string $message = ''): array {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => time()
        ];
    }

    /**
     * Formatea una respuesta de error
     *
     * @param string $message Mensaje de error
     * @param mixed $errors Detalles del error
     * @param int $code Código de error
     * @return array
     */
    public static function error(string $message, $errors = null, int $code = 400): array {
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'code' => $code,
            'timestamp' => time()
        ];
    }

    /**
     * Formatea una tarjeta de ruta
     *
     * @param array $route Datos de la ruta
     * @param string $lang Código de idioma
     * @return array
     */
    public static function formatRouteCard(array $route, string $lang = 'es'): array {
        return [
            'id' => $route['id'],
            'title' => $route['title'],
            'difficulty' => self::translateDifficulty($route['difficulty'], $lang),
            'stats' => [
                'distance' => self::formatDistance($route['length']),
                'elevation' => self::formatElevation($route['elevation']),
                'duration' => self::formatDuration($route['duration'])
            ],
            'location' => [
                'name' => $route['location'],
                'coordinates' => [
                    'lat' => $route['startLat'],
                    'lng' => $route['startLng']
                ]
            ],
            'weather' => isset($route['weather']) ? self::formatWeatherInfo($route['weather']) : null,
            'thumbnail' => $route['imagenes'][0] ?? null,
            'url' => "ruta.html?id={$route['id']}"
        ];
    }

    /**
     * Formatea información meteorológica
     *
     * @param array $weather Datos meteorológicos
     * @return array
     */
    public static function formatWeatherInfo(array $weather): array {
        return [
            'temperature' => [
                'current' => round($weather['temp'] ?? null),
                'min' => round($weather['temp_min'] ?? null),
                'max' => round($weather['temp_max'] ?? null),
                'feels_like' => round($weather['feels_like'] ?? null)
            ],
            'conditions' => [
                'main' => $weather['weather'][0]['main'] ?? null,
                'description' => $weather['weather'][0]['description'] ?? null,
                'icon' => $weather['weather'][0]['icon'] ?? null
            ],
            'wind' => [
                'speed' => round(($weather['wind_speed'] ?? 0) * 3.6, 1), // m/s to km/h
                'direction' => self::formatWindDirection($weather['wind_deg'] ?? 0)
            ],
            'snow_level' => $weather['snow_level'] ?? null,
            'iso_zero' => $weather['iso_zero'] ?? null,
            'precipitation' => [
                'probability' => ($weather['pop'] ?? 0) * 100,
                'amount' => $weather['precipitation'] ?? 0
            ],
            'humidity' => $weather['humidity'] ?? null,
            'visibility' => self::formatVisibility($weather['visibility'] ?? null),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Formatea recomendaciones de ruta
     *
     * @param array $recommendations Lista de recomendaciones
     * @param string $lang Código de idioma
     * @return array
     */
    public static function formatRecommendations(array $recommendations, string $lang = 'es'): array {
        $formatted = [];
        foreach ($recommendations as $rec) {
            $formatted[] = [
                'route' => self::formatRouteCard($rec['route'], $lang),
                'score' => round($rec['score'] * 100),
                'reasons' => $rec['reasons'],
                'safety_status' => self::formatSafetyStatus($rec['safety_checks'])
            ];
        }
        return $formatted;
    }

    /**
     * Formatea la distancia en un formato legible
     *
     * @param float $distance Distancia en kilómetros
     * @return string
     */
    private static function formatDistance(float $distance): string {
        return $distance < 1 
            ? round($distance * 1000) . ' m'
            : round($distance, 1) . ' km';
    }

    /**
     * Formatea la elevación en un formato legible
     *
     * @param float $elevation Elevación en metros
     * @return string
     */
    private static function formatElevation(float $elevation): string {
        return round($elevation) . ' m';
    }

    /**
     * Formatea la duración en un formato legible
     *
     * @param float $duration Duración en horas
     * @return string
     */
    private static function formatDuration(float $duration): string {
        $hours = floor($duration);
        $minutes = round(($duration - $hours) * 60);
        
        if ($hours == 0) {
            return "$minutes min";
        }
        
        return $minutes > 0 
            ? "$hours h $minutes min"
            : "$hours h";
    }

    /**
     * Formatea la dirección del viento
     *
     * @param int $degrees Dirección en grados
     * @return string
     */
    private static function formatWindDirection(int $degrees): string {
        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        return $directions[round($degrees / 45) % 8];
    }

    /**
     * Formatea la visibilidad en un formato legible
     *
     * @param ?int $visibility Visibilidad en metros
     * @return string
     */
    private static function formatVisibility(?int $visibility): string {
        if ($visibility === null) return 'N/A';
        
        return $visibility >= 1000 
            ? round($visibility / 1000, 1) . ' km'
            : $visibility . ' m';
    }

    /**
     * Traduce el nivel de dificultad
     *
     * @param string $difficulty Dificultad
     * @param string $lang Código de idioma
     * @return string
     */
    private static function translateDifficulty(string $difficulty, string $lang): string {
        return self::TRANSLATIONS['difficulty'][$lang][$difficulty] 
            ?? self::TRANSLATIONS['difficulty']['en'][$difficulty] 
            ?? $difficulty;
    }

    /**
     * Formatea el estado de seguridad
     *
     * @param array $safetyChecks Resultados de verificaciones de seguridad
     * @return array
     */
    private static function formatSafetyStatus(array $safetyChecks): array {
        return [
            'is_safe' => !in_array(false, array_values($safetyChecks)),
            'checks' => $safetyChecks,
            'warnings' => array_keys(array_filter($safetyChecks, fn($v) => !$v))
        ];
    }
}