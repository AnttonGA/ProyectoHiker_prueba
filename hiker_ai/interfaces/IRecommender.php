<?php
/**
 * Interfaz para sistemas de recomendación en Kahir.AI
 * 
 * Define el contrato que deben implementar todos los
 * sistemas de recomendación de rutas.
 */

namespace KahirAI\Interfaces;

interface IRecommender {
    /**
     * Genera recomendaciones basadas en criterios y preferencias
     *
     * @param array $criteria Criterios de búsqueda (actividad, ubicación, dificultad, etc.)
     * @param array $userPreferences Preferencias del usuario
     * @param int $limit Número máximo de recomendaciones
     * @return array Lista de rutas recomendadas
     * @throws \KahirAI\Exceptions\RouteException
     */
    public function getRecommendations(array $criteria, array $userPreferences, int $limit = 5): array;

    /**
     * Encuentra rutas similares a una ruta específica
     *
     * @param int $routeId ID de la ruta de referencia
     * @param int $limit Número máximo de rutas similares
     * @return array Lista de rutas similares
     * @throws \KahirAI\Exceptions\RouteException
     */
    public function getSimilarRoutes(int $routeId, int $limit = 3): array;

    /**
     * Actualiza las preferencias del usuario basado en sus interacciones
     *
     * @param int $userId ID del usuario
     * @param array $interactions Lista de interacciones recientes
     * @return bool
     */
    public function updateUserPreferences(int $userId, array $interactions): bool;

    /**
     * Calcula la puntuación de relevancia de una ruta para un usuario
     *
     * @param array $route Datos de la ruta
     * @param array $userPreferences Preferencias del usuario
     * @return float Puntuación de relevancia (0-1)
     */
    public function calculateRelevanceScore(array $route, array $userPreferences): float;

    /**
     * Valida si una ruta es adecuada según las condiciones actuales
     *
     * @param array $route Datos de la ruta
     * @param array $currentConditions Condiciones actuales (clima, temporada, etc.)
     * @return bool
     */
    public function validateRouteSuitability(array $route, array $currentConditions): bool;

    /**
     * Aplica filtros de seguridad a las recomendaciones
     *
     * @param array $recommendations Lista de recomendaciones
     * @param array $conditions Condiciones actuales
     * @return array Recomendaciones filtradas
     */
    public function applySafetyFilters(array $recommendations, array $conditions): array;

    /**
     * Ordena las recomendaciones según múltiples criterios
     *
     * @param array $recommendations Lista de recomendaciones
     * @param array $criteria Criterios de ordenamiento
     * @return array Recomendaciones ordenadas
     */
    public function rankRecommendations(array $recommendations, array $criteria): array;

    /**
     * Genera explicaciones sobre por qué se recomienda cada ruta
     *
     * @param array $route Datos de la ruta
     * @param array $userPreferences Preferencias del usuario
     * @return array Explicaciones de la recomendación
     */
    public function generateRecommendationExplanation(array $route, array $userPreferences): array;

    /**
     * Prepara recomendaciones alternativas si las principales no son viables
     *
     * @param array $criteria Criterios originales
     * @param array $conditions Condiciones actuales
     * @return array Recomendaciones alternativas
     */
    public function getFallbackRecommendations(array $criteria, array $conditions): array;

    /**
     * Verifica si los criterios de búsqueda son válidos
     *
     * @param array $criteria Criterios a validar
     * @return bool
     * @throws \KahirAI\Exceptions\RouteException
     */
    public function validateSearchCriteria(array $criteria): bool;

    /**
     * Procesa el feedback del usuario sobre las recomendaciones
     *
     * @param int $userId ID del usuario
     * @param int $routeId ID de la ruta
     * @param array $feedback Datos del feedback
     * @return bool
     */
    public function processFeedback(int $userId, int $routeId, array $feedback): bool;
}