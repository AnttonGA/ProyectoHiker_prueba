<?php
/**
 * Excepción específica para errores relacionados con las rutas
 * 
 * Maneja errores específicos del procesamiento, recomendación y 
 * validación de rutas.
 */

namespace KahirAI\Exceptions;

class RouteException extends AIException {
    /**
     * Códigos de error específicos para rutas
     */
    public const ERROR_CODES = [
        'ROUTE_NOT_FOUND' => 'Ruta no encontrada',
        'INVALID_DIFFICULTY' => 'Nivel de dificultad no válido',
        'ROUTE_INCOMPLETE' => 'Datos de ruta incompletos',
        'INVALID_COORDINATES' => 'Coordenadas no válidas',
        'GPX_PARSE_ERROR' => 'Error al procesar archivo GPX',
        'NO_MATCHING_ROUTES' => 'No se encontraron rutas que coincidan con los criterios',
        'ROUTE_ACCESS_DENIED' => 'Acceso a la ruta no autorizado',
        'INVALID_PARAMETERS' => 'Parámetros de búsqueda inválidos',
        'ROUTE_UNAVAILABLE' => 'Ruta temporalmente no disponible',
        'ELEVATION_DATA_ERROR' => 'Error en datos de elevación'
    ];

    /**
     * Umbrales para validación de rutas
     */
    private const ROUTE_THRESHOLDS = [
        'min_distance' => 0.5,     // km
        'max_distance' => 50,      // km
        'min_duration' => 0.5,     // horas
        'max_duration' => 72,      // horas
        'min_elevation' => 0,      // metros
        'max_elevation' => 4000    // metros
    ];

    /**
     * Constructor específico para excepciones de rutas
     *
     * @param string $errorCode Código de error específico
     * @param array $context Información adicional del error
     * @param \Throwable|null $previous Excepción previa
     */
    public function __construct(
        string $errorCode,
        array $context = [],
        \Throwable $previous = null
    ) {
        $message = self::ERROR_CODES[$errorCode] ?? 'Error en el procesamiento de la ruta';
        parent::__construct($message, $errorCode, $context, 0, $previous);
    }

    /**
     * Valida los parámetros básicos de una ruta
     *
     * @param array $routeData Datos de la ruta
     * @return bool
     * @throws RouteException
     */
    public static function validateRouteParameters(array $routeData): bool {
        // Validar distancia
        if (isset($routeData['length'])) {
            if ($routeData['length'] < self::ROUTE_THRESHOLDS['min_distance'] ||
                $routeData['length'] > self::ROUTE_THRESHOLDS['max_distance']) {
                throw new self('INVALID_PARAMETERS', [
                    'parameter' => 'length',
                    'value' => $routeData['length'],
                    'valid_range' => [
                        self::ROUTE_THRESHOLDS['min_distance'],
                        self::ROUTE_THRESHOLDS['max_distance']
                    ]
                ]);
            }
        }

        // Validar duración
        if (isset($routeData['duration'])) {
            if ($routeData['duration'] < self::ROUTE_THRESHOLDS['min_duration'] ||
                $routeData['duration'] > self::ROUTE_THRESHOLDS['max_duration']) {
                throw new self('INVALID_PARAMETERS', [
                    'parameter' => 'duration',
                    'value' => $routeData['duration'],
                    'valid_range' => [
                        self::ROUTE_THRESHOLDS['min_duration'],
                        self::ROUTE_THRESHOLDS['max_duration']
                    ]
                ]);
            }
        }

        // Validar elevación
        if (isset($routeData['elevation'])) {
            if ($routeData['elevation'] < self::ROUTE_THRESHOLDS['min_elevation'] ||
                $routeData['elevation'] > self::ROUTE_THRESHOLDS['max_elevation']) {
                throw new self('INVALID_PARAMETERS', [
                    'parameter' => 'elevation',
                    'value' => $routeData['elevation'],
                    'valid_range' => [
                        self::ROUTE_THRESHOLDS['min_elevation'],
                        self::ROUTE_THRESHOLDS['max_elevation']
                    ]
                ]);
            }
        }

        // Validar coordenadas
        if (isset($routeData['startLat']) && isset($routeData['startLng'])) {
            if (!self::validateCoordinates($routeData['startLat'], $routeData['startLng'])) {
                throw new self('INVALID_COORDINATES', [
                    'lat' => $routeData['startLat'],
                    'lng' => $routeData['startLng']
                ]);
            }
        }

        return true;
    }

    /**
     * Valida coordenadas geográficas
     *
     * @param float $lat Latitud
     * @param float $lng Longitud
     * @return bool
     */
    public static function validateCoordinates(float $lat, float $lng): bool {
        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }

    /**
     * Sugiere rutas alternativas basadas en los parámetros originales
     *
     * @param array $originalParams Parámetros originales de búsqueda
     * @return array
     */
    public function suggestAlternativeRoutes(array $originalParams): array {
        $suggestions = [];
        
        // Ajustar parámetros para búsqueda más flexible
        $flexibleParams = array_merge($originalParams, [
            'radius' => ($originalParams['radius'] ?? 10) * 1.5,
            'difficulty' => $this->getFlexibleDifficulty($originalParams['difficulty'] ?? null),
            'duration' => [
                'min' => ($originalParams['duration']['min'] ?? 0) * 0.8,
                'max' => ($originalParams['duration']['max'] ?? PHP_FLOAT_MAX) * 1.2
            ]
        ]);

        // Aquí iría la lógica de búsqueda con los parámetros flexibles
        // Por ahora retornamos un array vacío
        return $suggestions;
    }

    /**
     * Obtiene un nivel de dificultad más flexible
     *
     * @param string|null $difficulty
     * @return array
     */
    private function getFlexibleDifficulty(?string $difficulty): array {
        $difficultyLevels = ['beginner', 'intermediate', 'advanced'];
        if (!$difficulty) {
            return $difficultyLevels;
        }

        $currentIndex = array_search(strtolower($difficulty), $difficultyLevels);
        if ($currentIndex === false) {
            return $difficultyLevels;
        }

        $flexible = [$difficulty];
        if ($currentIndex > 0) {
            $flexible[] = $difficultyLevels[$currentIndex - 1];
        }
        if ($currentIndex < count($difficultyLevels) - 1) {
            $flexible[] = $difficultyLevels[$currentIndex + 1];
        }

        return $flexible;
    }

    /**
     * @inheritDoc
     */
    public function getUserFriendlyMessage(): string {
        $friendlyMessages = [
            'ROUTE_NOT_FOUND' => 'No pudimos encontrar la ruta que buscas. ¿Te gustaría ver alternativas similares?',
            'INVALID_DIFFICULTY' => 'El nivel de dificultad especificado no es válido. Por favor, elige entre principiante, intermedio o avanzado.',
            'ROUTE_INCOMPLETE' => 'La información de la ruta está incompleta. Intenta con otra ruta o contacta con soporte.',
            'NO_MATCHING_ROUTES' => 'No encontramos rutas que coincidan exactamente con tus criterios. ¿Te gustaría ver opciones similares?',
            'ROUTE_UNAVAILABLE' => 'Esta ruta no está disponible temporalmente. Te sugerimos algunas alternativas.',
            'DEFAULT' => 'Hubo un problema al procesar la información de la ruta.'
        ];

        return $friendlyMessages[$this->getErrorCode()] ?? $friendlyMessages['DEFAULT'];
    }

    /**
     * Verifica si una ruta es realizable en condiciones actuales
     *
     * @param array $routeData Datos de la ruta
     * @param array $currentConditions Condiciones actuales
     * @return bool
     */
    public static function isRouteFeasible(array $routeData, array $currentConditions): bool {
        // Implementar lógica de verificación de viabilidad
        // Por ejemplo, verificar condiciones meteorológicas, estado del terreno, etc.
        return true;
    }
}