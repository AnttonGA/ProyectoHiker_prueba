<?php
/**
 * Excepción específica para errores relacionados con el servicio meteorológico
 * 
 * Maneja errores específicos de la integración con OpenWeatherMap y
 * procesamiento de datos meteorológicos.
 */

namespace KahirAI\Exceptions;

class WeatherException extends AIException {
    /**
     * Códigos de error específicos para el servicio meteorológico
     */
    public const ERROR_CODES = [
        'API_CONNECTION_ERROR' => 'Error al conectar con el servicio meteorológico',
        'API_RESPONSE_ERROR' => 'Respuesta inválida del servicio meteorológico',
        'INVALID_LOCATION' => 'Ubicación no válida o no encontrada',
        'FORECAST_UNAVAILABLE' => 'Previsión no disponible para la fecha solicitada',
        'INVALID_DATE_RANGE' => 'Rango de fechas no válido',
        'DATA_PARSING_ERROR' => 'Error al procesar los datos meteorológicos',
        'CACHE_ERROR' => 'Error en la caché de datos meteorológicos',
        'RATE_LIMIT_EXCEEDED' => 'Límite de consultas alcanzado',
        'MISSING_API_KEY' => 'Clave de API no configurada',
        'INVALID_PARAMETERS' => 'Parámetros de consulta inválidos'
    ];

    /**
     * Umbrales para condiciones meteorológicas
     */
    private const WEATHER_THRESHOLDS = [
        'extreme_wind' => 50,      // km/h
        'heavy_rain' => 10,        // mm/h
        'extreme_temp_low' => -15, // °C
        'extreme_temp_high' => 35  // °C
    ];

    /**
     * Constructor específico para excepciones meteorológicas
     *
     * @param string $errorCode Código de error específico
     * @param array $context Información adicional del error
     * @param Throwable|null $previous Excepción previa
     */
    public function __construct(
        string $errorCode,
        array $context = [],
        \Throwable $previous = null
    ) {
        $message = self::ERROR_CODES[$errorCode] ?? 'Error en el servicio meteorológico';
        parent::__construct($message, $errorCode, $context, 0, $previous);
    }

    /**
     * Verifica si las condiciones meteorológicas son seguras
     *
     * @param array $weatherData Datos meteorológicos
     * @return bool
     */
    public static function isWeatherSafe(array $weatherData): bool {
        return !(
            ($weatherData['wind_speed'] ?? 0) > self::WEATHER_THRESHOLDS['extreme_wind'] ||
            ($weatherData['precipitation'] ?? 0) > self::WEATHER_THRESHOLDS['heavy_rain'] ||
            ($weatherData['temperature'] ?? 0) < self::WEATHER_THRESHOLDS['extreme_temp_low'] ||
            ($weatherData['temperature'] ?? 0) > self::WEATHER_THRESHOLDS['extreme_temp_high']
        );
    }

    /**
     * Genera recomendaciones basadas en las condiciones meteorológicas
     *
     * @param array $weatherData Datos meteorológicos
     * @return array
     */
    public static function getWeatherRecommendations(array $weatherData): array {
        $recommendations = [];

        if (($weatherData['wind_speed'] ?? 0) > self::WEATHER_THRESHOLDS['extreme_wind']) {
            $recommendations[] = 'Vientos fuertes detectados. Se recomienda posponer la actividad.';
        }

        if (($weatherData['precipitation'] ?? 0) > self::WEATHER_THRESHOLDS['heavy_rain']) {
            $recommendations[] = 'Precipitaciones intensas previstas. Riesgo de inundaciones o deslizamientos.';
        }

        if (($weatherData['temperature'] ?? 0) < self::WEATHER_THRESHOLDS['extreme_temp_low']) {
            $recommendations[] = 'Temperaturas extremadamente bajas. Riesgo de hipotermia.';
        }

        if (($weatherData['temperature'] ?? 0) > self::WEATHER_THRESHOLDS['extreme_temp_high']) {
            $recommendations[] = 'Temperaturas muy altas. Riesgo de golpe de calor.';
        }

        return $recommendations;
    }

    /**
     * @inheritDoc
     */
    public function getUserFriendlyMessage(): string {
        $friendlyMessages = [
            'API_CONNECTION_ERROR' => 'No podemos obtener la información meteorológica en este momento. Por favor, inténtalo más tarde.',
            'INVALID_LOCATION' => 'No encontramos la ubicación especificada. ¿Podrías verificarla?',
            'FORECAST_UNAVAILABLE' => 'La previsión no está disponible para la fecha solicitada. Prueba con una fecha más cercana.',
            'INVALID_DATE_RANGE' => 'El rango de fechas no es válido. Puedes consultar hasta 3 días en adelante.',
            'RATE_LIMIT_EXCEEDED' => 'Has alcanzado el límite de consultas meteorológicas. Por favor, espera unos minutos.',
            'DEFAULT' => 'Hubo un problema al obtener la información meteorológica.'
        ];

        return $friendlyMessages[$this->getErrorCode()] ?? $friendlyMessages['DEFAULT'];
    }

    /**
     * Verifica si el error es recuperable
     *
     * @return bool
     */
    public function isRecoverable(): bool {
        $recoverableErrors = [
            'API_CONNECTION_ERROR',
            'RATE_LIMIT_EXCEEDED',
            'CACHE_ERROR'
        ];

        return in_array($this->getErrorCode(), $recoverableErrors);
    }

    /**
     * Sugiere una acción alternativa basada en el error
     *
     * @return string
     */
    public function suggestAlternative(): string {
        $alternatives = [
            'API_CONNECTION_ERROR' => 'Puedes consultar la previsión en fuentes alternativas como MeteoFrance.',
            'FORECAST_UNAVAILABLE' => 'Considera consultar la meteorología más cerca de la fecha prevista.',
            'INVALID_LOCATION' => 'Intenta especificar una ubicación cercana o un punto de referencia conocido.',
            'DEFAULT' => 'Considera consultar la información más tarde.'
        ];

        return $alternatives[$this->getErrorCode()] ?? $alternatives['DEFAULT'];
    }
}