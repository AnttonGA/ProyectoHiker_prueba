<?php
/**
 * Utilidad para validación de entradas de usuario
 * 
 * Proporciona métodos para validar y sanitizar diferentes tipos
 * de entradas, especialmente para rutas y consultas meteorológicas.
 */

namespace KahirAI\Utils;

class InputValidator {
    /**
     * Rangos válidos para diferentes parámetros
     */
    private const VALID_RANGES = [
        'elevation' => ['min' => 0, 'max' => 4000],
        'distance' => ['min' => 0.5, 'max' => 50],
        'duration' => ['min' => 0.5, 'max' => 72],
        'latitude' => ['min' => 42.0, 'max' => 43.5],  // Rango Pirineos
        'longitude' => ['min' => -2.5, 'max' => 3.5],  // Rango Pirineos
    ];

    /**
     * Niveles de dificultad válidos
     */
    private const VALID_DIFFICULTIES = ['beginner', 'intermediate', 'advanced'];

    /**
     * Tipos de actividad válidos
     */
    private const VALID_ACTIVITIES = ['ski_touring', 'hiking', 'climbing', 'cycling'];

    /**
     * Valida los criterios de búsqueda de ruta
     *
     * @param array $criteria Criterios a validar
     * @return array Array con errores encontrados
     */
    public static function validateRouteSearchCriteria(array $criteria): array {
        $errors = [];

        // Validar actividad
        if (isset($criteria['activity'])) {
            if (!in_array($criteria['activity'], self::VALID_ACTIVITIES)) {
                $errors['activity'] = 'Tipo de actividad no válido';
            }
        }

        // Validar dificultad
        if (isset($criteria['difficulty'])) {
            if (!in_array($criteria['difficulty'], self::VALID_DIFFICULTIES)) {
                $errors['difficulty'] = 'Nivel de dificultad no válido';
            }
        }

        // Validar elevación
        if (isset($criteria['elevation'])) {
            if (!self::isInRange($criteria['elevation'], 'elevation')) {
                $errors['elevation'] = sprintf(
                    'La elevación debe estar entre %d y %d metros',
                    self::VALID_RANGES['elevation']['min'],
                    self::VALID_RANGES['elevation']['max']
                );
            }
        }

        // Validar distancia
        if (isset($criteria['distance'])) {
            if (!self::isInRange($criteria['distance'], 'distance')) {
                $errors['distance'] = sprintf(
                    'La distancia debe estar entre %.1f y %d kilómetros',
                    self::VALID_RANGES['distance']['min'],
                    self::VALID_RANGES['distance']['max']
                );
            }
        }

        // Validar duración
        if (isset($criteria['duration'])) {
            if (!self::isInRange($criteria['duration'], 'duration')) {
                $errors['duration'] = sprintf(
                    'La duración debe estar entre %.1f y %d horas',
                    self::VALID_RANGES['duration']['min'],
                    self::VALID_RANGES['duration']['max']
                );
            }
        }

        // Validar ubicación
        if (isset($criteria['latitude']) && isset($criteria['longitude'])) {
            if (!self::isValidLocation($criteria['latitude'], $criteria['longitude'])) {
                $errors['location'] = 'Ubicación fuera del área de los Pirineos';
            }
        }

        // Validar fecha
        if (isset($criteria['date'])) {
            if (!DateUtils::isValidFutureDate($criteria['date'])) {
                $errors['date'] = 'Fecha no válida o en el pasado';
            }
        }

        return $errors;
    }

    /**
     * Valida parámetros para consulta meteorológica
     *
     * @param array $params Parámetros a validar
     * @return array Array con errores encontrados
     */
    public static function validateWeatherParams(array $params): array {
        $errors = [];

        // Validar ubicación
        if (!isset($params['latitude']) || !isset($params['longitude'])) {
            $errors['location'] = 'Se requiere latitud y longitud';
        } elseif (!self::isValidLocation($params['latitude'], $params['longitude'])) {
            $errors['location'] = 'Ubicación fuera del área de los Pirineos';
        }

        // Validar fecha
        if (isset($params['date'])) {
            if (!DateUtils::isValidFutureDate($params['date'])) {
                $errors['date'] = 'Fecha no válida o en el pasado';
            }
            if (!DateUtils::isWithinForecastRange($params['date'])) {
                $errors['date'] = 'Fecha fuera del rango de previsión';
            }
        }

        return $errors;
    }

    /**
     * Sanitiza una cadena de entrada
     *
     * @param string $input Cadena a sanitizar
     * @return string
     */
    public static function sanitizeString(string $input): string {
        return htmlspecialchars(
            strip_tags(trim($input)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }

    /**
     * Verifica si un valor está dentro del rango válido
     *
     * @param float $value Valor a verificar
     * @param string $parameter Nombre del parámetro
     * @return bool
     */
    private static function isInRange(float $value, string $parameter): bool {
        return isset(self::VALID_RANGES[$parameter]) &&
               $value >= self::VALID_RANGES[$parameter]['min'] &&
               $value <= self::VALID_RANGES[$parameter]['max'];
    }

    /**
     * Verifica si una ubicación está dentro del área de los Pirineos
     *
     * @param float $latitude Latitud
     * @param float $longitude Longitud
     * @return bool
     */
    private static function isValidLocation(float $latitude, float $longitude): bool {
        return self::isInRange($latitude, 'latitude') &&
               self::isInRange($longitude, 'longitude');
    }

    /**
     * Verifica si un texto contiene patrones maliciosos
     *
     * @param string $input Texto a verificar
     * @return bool
     */
    public static function hasMaliciousPatterns(string $input): bool {
        $patterns = [
            '/[<>]/',                    // Tags HTML
            '/javascript:/i',            // JavaScript
            '/on\w+\s*=/i',             // Event handlers
            '/data:\s*[^\s]*/i',        // Data URIs
            '/base64/i',                // Base64
            '/eval\s*\(/i',             // Eval
            '/script/i',                // Script
            '/alert/i',                 // Alert
            '/document\./i',            // Document
            '/window\./i'               // Window
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Valida el formato de un archivo GPX
     *
     * @param string $filePath Ruta al archivo GPX
     * @return bool|string True si es válido, mensaje de error si no lo es
     */
    public static function validateGPXFile(string $filePath) {
        if (!file_exists($filePath)) {
            return 'Archivo no encontrado';
        }

        if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'gpx') {
            return 'El archivo debe ser de tipo GPX';
        }

        $xml = @simplexml_load_file($filePath);
        if ($xml === false) {
            return 'Archivo GPX inválido o corrupto';
        }

        if (!isset($xml->trk) || !isset($xml->trk->trkseg)) {
            return 'El archivo GPX no contiene datos de ruta';
        }

        return true;
    }

    /**
     * Limpia y valida una matriz de datos
     *
     * @param array $data Datos a limpiar
     * @param array $allowedKeys Claves permitidas
     * @return array Datos limpios
     */
    public static function sanitizeArray(array $data, array $allowedKeys = []): array {
        $clean = [];
        foreach ($data as $key => $value) {
            if (empty($allowedKeys) || in_array($key, $allowedKeys)) {
                if (is_array($value)) {
                    $clean[$key] = self::sanitizeArray($value);
                } else {
                    $clean[$key] = self::sanitizeString((string)$value);
                }
            }
        }
        return $clean;
    }
}