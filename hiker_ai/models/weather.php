<?php
/**
 * Modelo para datos meteorológicos en Kahir.AI
 * 
 * Maneja la estructura y procesamiento de datos meteorológicos,
 * incluyendo validación y cálculos específicos para actividades de montaña.
 */

namespace KahirAI\Models;

use KahirAI\Exceptions\WeatherException;

class Weather {
    /**
     * @var float Temperatura actual
     */
    private float $temperature;

    /**
     * @var float Sensación térmica
     */
    private float $feelsLike;

    /**
     * @var int Humedad relativa
     */
    private int $humidity;

    /**
     * @var float Velocidad del viento
     */
    private float $windSpeed;

    /**
     * @var int Dirección del viento
     */
    private int $windDirection;

    /**
     * @var int Visibilidad en metros
     */
    private int $visibility;

    /**
     * @var array Condición meteorológica
     */
    private array $conditions;

    /**
     * @var float|null Nivel de nieve
     */
    private ?float $snowLevel = null;

    /**
     * @var float|null Nivel de ISO 0
     */
    private ?float $isoZeroLevel = null;

    /**
     * @var array|null Pronóstico horario
     */
    private ?array $hourlyForecast = null;

    /**
     * @var array|null Alertas meteorológicas
     */
    private ?array $alerts = null;

    /**
     * @var \DateTime Timestamp de la medición
     */
    private \DateTime $timestamp;

    /**
     * Constructor
     * 
     * @param array $data Datos meteorológicos
     */
    public function __construct(array $data) {
        $this->timestamp = new \DateTime();
        $this->hydrate($data);
    }

    /**
     * Hidrata el modelo con datos
     * 
     * @param array $data Datos para hidratar
     */
    private function hydrate(array $data): void {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    /**
     * Evalúa si las condiciones son seguras para una actividad
     * 
     * @param string $activity Tipo de actividad
     * @return bool
     */
    public function isSafeForActivity(string $activity): bool {
        // Verificar condiciones básicas de seguridad
        if (!$this->isBasicallySafe()) {
            return false;
        }

        // Verificaciones específicas por actividad
        switch ($activity) {
            case 'ski_touring':
                return $this->isSafeForSkiing();
            case 'hiking':
                return $this->isSafeForHiking();
            case 'climbing':
                return $this->isSafeForClimbing();
            default:
                return $this->isBasicallySafe();
        }
    }

    /**
     * Verifica condiciones básicas de seguridad
     * 
     * @return bool
     */
    private function isBasicallySafe(): bool {
        return $this->windSpeed < 50 && // 50 km/h máximo
               $this->visibility >= 1000 && // Mínimo 1km de visibilidad
               $this->temperature > -15 && // Temperatura mínima
               $this->temperature < 35 && // Temperatura máxima
               !$this->hasActiveAlerts();
    }

    /**
     * Verifica condiciones para esquí
     * 
     * @return bool
     */
    private function isSafeForSkiing(): bool {
        return $this->snowLevel !== null &&
               $this->temperature < 10 && // Máximo 10°C para esquí
               $this->windSpeed < 40 && // Máximo 40 km/h para esquí
               $this->visibility >= 2000; // Mínimo 2km visibilidad
    }

    /**
     * Verifica condiciones para senderismo
     * 
     * @return bool
     */
    private function isSafeForHiking(): bool {
        return $this->temperature > 0 && // Mínimo 0°C
               $this->temperature < 30 && // Máximo 30°C
               $this->visibility >= 1500 && // Mínimo 1.5km visibilidad
               !$this->hasThunderstorm();
    }

    /**
     * Verifica condiciones para escalada
     * 
     * @return bool
     */
    private function isSafeForClimbing(): bool {
        return $this->temperature > 5 && // Mínimo 5°C
               $this->temperature < 28 && // Máximo 28°C
               $this->windSpeed < 30 && // Máximo 30 km/h
               $this->visibility >= 2000 && // Mínimo 2km visibilidad
               !$this->hasRain() &&
               !$this->hasThunderstorm();
    }

    /**
     * Calcula el índice de confort térmico
     * 
     * @return float
     */
    public function calculateComfortIndex(): float {
        // Calcular sensación térmica por frío o calor según temperatura
        if ($this->temperature < 10) {
            return $this->calculateWindChill();
        } else {
            return $this->calculateHeatIndex();
        }
    }

    /**
     * Calcula la sensación térmica por viento
     * 
     * @return float
     */
    private function calculateWindChill(): float {
        $t = $this->temperature;
        $v = $this->windSpeed * 3.6; // Convertir a km/h

        return 13.12 + 0.6215 * $t - 11.37 * pow($v, 0.16) + 0.3965 * $t * pow($v, 0.16);
    }

    /**
     * Calcula el índice de calor
     * 
     * @return float
     */
    private function calculateHeatIndex(): float {
        $t = $this->temperature;
        $h = $this->humidity;

        return -8.78469475556 +
               1.61139411 * $t +
               2.33854883889 * $h +
               -0.14611605 * $t * $h +
               -0.012308094 * pow($t, 2) +
               -0.0164248277778 * pow($h, 2) +
               0.002211732 * pow($t, 2) * $h +
               0.00072546 * $t * pow($h, 2) +
               -0.000003582 * pow($t, 2) * pow($h, 2);
    }

    /**
     * Obtiene alertas activas
     * 
     * @return array
     */
    public function getActiveAlerts(): array {
        if (!$this->alerts) {
            return [];
        }

        return array_filter($this->alerts, function($alert) {
            return $alert['end_time'] > time();
        });
    }

    /**
     * Verifica si hay alertas activas
     * 
     * @return bool
     */
    public function hasActiveAlerts(): bool {
        return count($this->getActiveAlerts()) > 0;
    }

    /**
     * Verifica si hay tormenta
     * 
     * @return bool
     */
    private function hasThunderstorm(): bool {
        return isset($this->conditions['id']) && 
               ($this->conditions['id'] >= 200 && $this->conditions['id'] < 300);
    }

    /**
     * Verifica si hay lluvia
     * 
     * @return bool
     */
    private function hasRain(): bool {
        return isset($this->conditions['id']) && 
               ($this->conditions['id'] >= 500 && $this->conditions['id'] < 600);
    }

    /**
     * Convierte el objeto a array
     * 
     * @return array
     */
    public function toArray(): array {
        return [
            'temperature' => $this->temperature,
            'feels_like' => $this->feelsLike,
            'humidity' => $this->humidity,
            'wind_speed' => $this->windSpeed,
            'wind_direction' => $this->windDirection,
            'visibility' => $this->visibility,
            'conditions' => $this->conditions,
            'snow_level' => $this->snowLevel,
            'iso_zero_level' => $this->isoZeroLevel,
            'hourly_forecast' => $this->hourlyForecast,
            'alerts' => $this->alerts,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }

    // Getters y setters
    // ... (implementar según necesidad)
}