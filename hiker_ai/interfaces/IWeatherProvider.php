<?php
/**
 * Interfaz para proveedores de servicios meteorológicos en Kahir.AI
 * 
 * Define el contrato que deben implementar todos los proveedores
 * de información meteorológica (OpenWeatherMap, etc.)
 */

namespace KahirAI\Interfaces;

interface IWeatherProvider {
    /**
     * Obtiene las condiciones meteorológicas actuales
     *
     * @param float $latitude Latitud
     * @param float $longitude Longitud
     * @param array $options Opciones adicionales
     * @return array Datos meteorológicos
     * @throws \KahirAI\Exceptions\WeatherException
     */
    public function getCurrentWeather(float $latitude, float $longitude, array $options = []): array;

    /**
     * Obtiene el pronóstico meteorológico
     *
     * @param float $latitude Latitud
     * @param float $longitude Longitud
     * @param int $days Número de días (máximo 3)
     * @param array $options Opciones adicionales
     * @return array Pronóstico meteorológico
     * @throws \KahirAI\Exceptions\WeatherException
     */
    public function getForecast(float $latitude, float $longitude, int $days = 3, array $options = []): array;

    /**
     * Obtiene alertas meteorológicas activas
     *
     * @param float $latitude Latitud
     * @param float $longitude Longitud
     * @return array Lista de alertas
     * @throws \KahirAI\Exceptions\WeatherException
     */
    public function getWeatherAlerts(float $latitude, float $longitude): array;

    /**
     * Obtiene condiciones específicas de montaña
     *
     * @param float $latitude Latitud
     * @param float $longitude Longitud
     * @param array $options Opciones adicionales
     * @return array Condiciones de montaña
     * @throws \KahirAI\Exceptions\WeatherException
     */
    public function getMountainConditions(float $latitude, float $longitude, array $options = []): array;

    /**
     * Evalúa si las condiciones son seguras para una actividad
     *
     * @param array $weatherData Datos meteorológicos
     * @param string $activityType Tipo de actividad
     * @return bool
     */
    public function isSafeForActivity(array $weatherData, string $activityType): bool;

    /**
     * Genera recomendaciones basadas en las condiciones
     *
     * @param array $weatherData Datos meteorológicos
     * @param string $activityType Tipo de actividad
     * @return array Lista de recomendaciones
     */
    public function getWeatherRecommendations(array $weatherData, string $activityType): array;

    /**
     * Obtiene la cota de nieve
     *
     * @param float $latitude Latitud
     * @param float $longitude Longitud
     * @return ?int Cota de nieve en metros (null si no hay nieve)
     * @throws \KahirAI\Exceptions\WeatherException
     */
    public function getSnowLevel(float $latitude, float $longitude): ?int;

    /**
     * Obtiene el nivel de la ISO 0
     *
     * @param float $latitude Latitud
     * @param float $longitude Longitud
     * @return ?int Altura de la ISO 0 en metros (null si no disponible)
     * @throws \KahirAI\Exceptions\WeatherException
     */
    public function getIsoZeroLevel(float $latitude, float $longitude): ?int;

    /**
     * Verifica si una ubicación está dentro del área de cobertura
     *
     * @param float $latitude Latitud
     * @param float $longitude Longitud
     * @return bool
     */
    public function isLocationCovered(float $latitude, float $longitude): bool;

    /**
     * Obtiene el índice UV
     *
     * @param float $latitude Latitud
     * @param float $longitude Longitud
     * @return int Índice UV
     * @throws \KahirAI\Exceptions\WeatherException
     */
    public function getUVIndex(float $latitude, float $longitude): int;

    /**
     * Obtiene datos históricos del tiempo
     *
     * @param float $latitude Latitud
     * @param float $longitude Longitud
     * @param \DateTime $date Fecha específica
     * @return array Datos históricos
     * @throws \KahirAI\Exceptions\WeatherException
     */
    public function getHistoricalWeather(float $latitude, float $longitude, \DateTime $date): array;

    /**
     * Verifica el estado de la API
     *
     * @return bool
     */
    public function checkAPIStatus(): bool;

    /**
     * Obtiene información sobre límites de uso de la API
     *
     * @return array Información sobre límites y uso
     */
    public function getAPILimits(): array;

    /**
     * Formatea los datos meteorológicos
     *
     * @param array $weatherData Datos meteorológicos crudos
     * @param string $format Formato deseado ('basic', 'detailed', 'technical')
     * @return array Datos formateados
     */
    public function formatWeatherData(array $weatherData, string $format = 'basic'): array;
}