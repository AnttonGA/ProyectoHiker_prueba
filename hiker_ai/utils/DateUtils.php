<?php
/**
 * Utilidades para el manejo de fechas y tiempo
 * 
 * Proporciona funciones comunes para manipulación y validación
 * de fechas, especialmente para planificación de rutas y previsiones
 * meteorológicas.
 */

namespace KahirAI\Utils;

class DateUtils {
    /**
     * Formatos de fecha soportados
     */
    private const DATE_FORMATS = [
        'Y-m-d',           // 2024-12-01
        'd/m/Y',          // 01/12/2024
        'd-m-Y',          // 01-12-2024
        'Y/m/d',          // 2024/12/01
        'd.m.Y',          // 01.12.2024
        'Y.m.d',          // 2024.12.01
    ];

    /**
     * Nombres de los días en diferentes idiomas
     */
    private const DAYS_LOCALIZED = [
        'es' => ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
        'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        'fr' => ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
        'de' => ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        'eu' => ['Igandea', 'Astelehena', 'Asteartea', 'Asteazkena', 'Osteguna', 'Ostirala', 'Larunbata']
    ];

    /**
     * Verifica si una fecha es válida y está en el futuro
     *
     * @param string $date Fecha a verificar
     * @param string $format Formato de la fecha (opcional)
     * @return bool
     */
    public static function isValidFutureDate(string $date, string $format = 'Y-m-d'): bool {
        $dateTime = \DateTime::createFromFormat($format, $date);
        return $dateTime !== false && $dateTime > new \DateTime();
    }

    /**
     * Verifica si una fecha está dentro del rango permitido para previsiones
     *
     * @param string $date Fecha a verificar
     * @param int $maxDays Días máximos en el futuro (por defecto 3)
     * @return bool
     */
    public static function isWithinForecastRange(string $date, int $maxDays = 3): bool {
        $dateTime = new \DateTime($date);
        $maxDate = new \DateTime("+{$maxDays} days");
        return $dateTime <= $maxDate;
    }

    /**
     * Formatea una fecha al formato deseado
     *
     * @param string $date Fecha a formatear
     * @param string $targetFormat Formato deseado
     * @return string Fecha formateada
     */
    public static function formatDate(string $date, string $targetFormat = 'Y-m-d'): string {
        foreach (self::DATE_FORMATS as $format) {
            $dateTime = \DateTime::createFromFormat($format, $date);
            if ($dateTime !== false) {
                return $dateTime->format($targetFormat);
            }
        }
        throw new \InvalidArgumentException('Formato de fecha no reconocido');
    }

    /**
     * Obtiene el nombre del día de la semana en el idioma especificado
     *
     * @param string $date Fecha
     * @param string $lang Código de idioma ('es', 'en', 'fr', 'de', 'eu')
     * @return string
     */
    public static function getDayName(string $date, string $lang = 'es'): string {
        $dateTime = new \DateTime($date);
        $dayIndex = (int)$dateTime->format('w');
        return self::DAYS_LOCALIZED[$lang][$dayIndex] ?? self::DAYS_LOCALIZED['en'][$dayIndex];
    }

    /**
     * Calcula la duración entre dos timestamps en formato legible
     *
     * @param int $start Timestamp de inicio
     * @param int $end Timestamp de fin
     * @param string $lang Código de idioma
     * @return string
     */
    public static function getDuration(int $start, int $end, string $lang = 'es'): string {
        $duration = $end - $start;
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);

        $formats = [
            'es' => ['hora', 'horas', 'minuto', 'minutos'],
            'en' => ['hour', 'hours', 'minute', 'minutes'],
            'fr' => ['heure', 'heures', 'minute', 'minutes'],
            'de' => ['Stunde', 'Stunden', 'Minute', 'Minuten'],
            'eu' => ['ordu', 'ordu', 'minutu', 'minutu']
        ];

        $f = $formats[$lang] ?? $formats['en'];
        
        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . ' ' . ($hours == 1 ? $f[0] : $f[1]);
        }
        if ($minutes > 0) {
            $parts[] = $minutes . ' ' . ($minutes == 1 ? $f[2] : $f[3]);
        }

        return implode(' ', $parts);
    }

    /**
     * Determina si es temporada alta para actividades de montaña
     *
     * @param string $date Fecha a verificar
     * @param string $activity Tipo de actividad
     * @return bool
     */
    public static function isHighSeason(string $date, string $activity): bool {
        $month = (int)(new \DateTime($date))->format('n');
        
        $highSeasonRanges = [
            'ski_touring' => [12, 1, 2, 3],    // Diciembre a Marzo
            'hiking' => [6, 7, 8, 9],          // Junio a Septiembre
            'climbing' => [6, 7, 8, 9],        // Junio a Septiembre
            'cycling' => [5, 6, 7, 8, 9, 10]   // Mayo a Octubre
        ];

        return in_array($month, $highSeasonRanges[$activity] ?? []);
    }

    /**
     * Verifica si hay suficiente luz diurna para una actividad
     *
     * @param string $date Fecha
     * @param float $latitude Latitud
     * @param float $longitude Longitud
     * @param int $duration Duración de la actividad en horas
     * @return bool
     */
    public static function hasSufficientDaylight(
        string $date,
        float $latitude,
        float $longitude,
        int $duration
    ): bool {
        $dateTime = new \DateTime($date);
        $sunInfo = date_sun_info(
            $dateTime->getTimestamp(),
            $latitude,
            $longitude
        );

        $daylightHours = ($sunInfo['sunset'] - $sunInfo['sunrise']) / 3600;
        return $duration <= ($daylightHours - 2); // 2 horas de margen
    }

    /**
     * Genera un rango de fechas entre dos fechas
     *
     * @param string $start Fecha inicial
     * @param string $end Fecha final
     * @return array
     */
    public static function getDateRange(string $start, string $end): array {
        $dates = [];
        $current = new \DateTime($start);
        $endDate = new \DateTime($end);

        while ($current <= $endDate) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        return $dates;
    }

    /**
     * Calcula la hora estimada de llegada
     *
     * @param string $startTime Hora de inicio
     * @param int $duration Duración en minutos
     * @return string
     */
    public static function calculateETA(string $startTime, int $duration): string {
        $start = new \DateTime($startTime);
        return $start->modify("+{$duration} minutes")->format('H:i');
    }
}