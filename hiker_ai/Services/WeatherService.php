<?php
/**
 * Servicio meteorológico usando Open-Meteo (gratuito, sin API key, 16 días).
 *
 * Documentación: https://open-meteo.com/en/docs
 * Open-Meteo usa WMO Weather Codes. El campo `freezing_level_height`
 * proporciona la cota de nieve/isoterma 0°C, crucial para ski touring.
 */

namespace KahirAI\Services;

use KahirAI\Interfaces\IWeatherProvider;
use KahirAI\Exceptions\WeatherException;

class WeatherService implements IWeatherProvider {

    private const BASE_URL      = 'https://api.open-meteo.com/v1/forecast';
    private const CACHE_SECONDS = 1800; // 30 min

    // Umbrales de peligro (por actividad donde difieren)
    private const THRESHOLDS = [
        'wind_kmh'      => 50,
        'precip_mm'     => 10,
        'visibility_m'  => 1000,
        'temp_min'      => -20,
        'temp_max'      => 38,
    ];

    // WMO codes considerados peligrosos
    private const DANGEROUS_CODES = [
        95, 96, 99,           // tormentas eléctricas
        65, 75, 82, 86,       // lluvia/nieve intensa
        77,                   // granizo de nieve
    ];

    private LoggerService $logger;

    public function __construct() {
        $this->logger = new LoggerService('weather');
    }

    // -----------------------------------------------------------------
    // IWeatherProvider — métodos públicos
    // -----------------------------------------------------------------

    /**
     * Tiempo actual (primer slot horario de hoy).
     */
    public function getCurrentWeather(float $latitude, float $longitude, array $options = []): array {
        $elevation = $options['elevation'] ?? null;
        $cacheKey  = "current_{$latitude}_{$longitude}_" . ($elevation ?? 'auto');

        $cached = $this->getCache($cacheKey);
        if ($cached !== null) return $cached;

        $raw  = $this->fetchHourly($latitude, $longitude, 1, $elevation);
        $data = $this->parseCurrentFromHourly($raw);

        $this->setCache($cacheKey, $data);
        return $data;
    }

    /**
     * Obtiene el tiempo actual para múltiples zonas en paralelo (curl_multi).
     * Cada elemento de $coords es ['lat' => float, 'lon' => float].
     * Devuelve array indexado igual que $coords; null si falla la petición.
     *
     * @param  array[] $coords  Lista de coordenadas
     * @return array[]          Datos meteorológicos por índice (o null)
     */
    public function getCurrentWeatherBatch(array $coords): array {
        $results = array_fill(0, count($coords), null);
        $mh      = curl_multi_init();
        $handles = [];

        foreach ($coords as $i => $c) {
            $lat = (float)($c['lat'] ?? 0);
            $lon = (float)($c['lon'] ?? 0);

            // Verificar caché individual primero
            $cacheKey = "current_{$lat}_{$lon}_auto";
            $cached   = $this->getCache($cacheKey);
            if ($cached !== null) {
                $results[$i] = $cached;
                continue;
            }

            $params = $this->baseParams($lat, $lon, 1);
            $params['hourly'] = implode(',', [
                'temperature_2m', 'apparent_temperature',
                'precipitation', 'rain', 'snowfall', 'snow_depth',
                'windspeed_10m', 'windgusts_10m', 'winddirection_10m',
                'weathercode', 'cloudcover',
            ]);

            $url = self::BASE_URL . '?' . http_build_query($params);
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'KahirAI/1.0',
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = ['ch' => $ch, 'lat' => $lat, 'lon' => $lon, 'cacheKey' => $cacheKey];
        }

        // Ejecutar en paralelo
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh);
        } while ($running > 0);

        // Recoger resultados
        foreach ($handles as $i => $h) {
            $body = curl_multi_getcontent($h['ch']);
            curl_multi_remove_handle($mh, $h['ch']);
            curl_close($h['ch']);

            if (!$body) continue;
            $raw = json_decode($body, true);
            if (!is_array($raw)) continue;

            $data = $this->parseCurrentFromHourly($raw);
            $this->setCache($h['cacheKey'], $data);
            $results[$i] = $data;
        }

        curl_multi_close($mh);
        return $results;
    }

    /**
     * Pronóstico diario hasta 16 días.
     *
     * @param int $days 1–16
     */
    public function getForecast(float $latitude, float $longitude, int $days = 7, array $options = []): array {
        $days      = min(max($days, 1), 16);
        $elevation = $options['elevation'] ?? null;
        $cacheKey  = "forecast_{$latitude}_{$longitude}_{$days}_" . ($elevation ?? 'auto');

        $cached = $this->getCache($cacheKey);
        if ($cached !== null) return $cached;

        $raw  = $this->fetchDaily($latitude, $longitude, $days, $elevation);
        $data = $this->parseDaily($raw, $days);

        $this->setCache($cacheKey, $data);
        return $data;
    }

    /**
     * Open-Meteo no tiene alertas propias; generamos alertas basadas en umbrales.
     */
    public function getWeatherAlerts(float $latitude, float $longitude): array {
        try {
            $forecast = $this->getForecast($latitude, $longitude, 3);
            $alerts   = [];

            foreach ($forecast as $day) {
                if (($day['windspeed_max'] ?? 0) > self::THRESHOLDS['wind_kmh']) {
                    $alerts[] = [
                        'type'        => 'wind',
                        'description' => "Vientos fuertes previstos: {$day['windspeed_max']} km/h",
                        'date'        => $day['date'],
                        'severity'    => 'moderate',
                    ];
                }
                if (in_array($day['weathercode'] ?? 0, self::DANGEROUS_CODES, true)) {
                    $alerts[] = [
                        'type'        => 'storm',
                        'description' => 'Tormenta eléctrica prevista',
                        'date'        => $day['date'],
                        'severity'    => 'high',
                    ];
                }
            }

            return $alerts;

        } catch (\Exception $e) {
            $this->logger->error('Error generando alertas', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Condiciones específicas de montaña: incluye cota de nieve e isocero.
     */
    public function getMountainConditions(float $latitude, float $longitude, array $options = []): array {
        $current   = $this->getCurrentWeather($latitude, $longitude, $options);
        $isoZero   = $this->getIsoZeroLevel($latitude, $longitude);
        $snowLevel = $this->getSnowLevel($latitude, $longitude);

        return array_merge($current, [
            'iso_zero'   => $isoZero,
            'snow_level' => $snowLevel,
            'mountain'   => [
                'avalanche_risk'    => $this->calcAvalancheRisk($current, $snowLevel),
                'wind_chill'        => $this->calcWindChill($current['temp'], $current['windspeed']),
                'visibility_status' => $this->assessVisibility($current['weathercode'] ?? 0),
            ],
        ]);
    }

    /**
     * Previsión diaria para unas fechas concretas (Y-m-d). Devuelve solo esos días, en orden.
     * Las fechas fuera del horizonte de Open-Meteo (16 días) no aparecen.
     */
    public function getForecastForDates(float $latitude, float $longitude, array $dates, array $options = []): array {
        if (!$dates) return [];
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Madrid'));
        $last  = new \DateTimeImmutable(max($dates), new \DateTimeZone('Europe/Madrid'));
        $days  = (int) $today->diff($last)->days + 1;
        if ($days > 16) $days = 16;
        $all = $this->getForecast($latitude, $longitude, $days, $options);
        return array_values(array_filter($all, fn($d) => in_array($d['date'] ?? '', $dates, true)));
    }

    /**
     * Devuelve true si las condiciones son seguras para la actividad.
     * Acepta el tiempo actual (getCurrentWeather), un día de getForecast() o una lista de días
     * (en ese caso, todos deben ser seguros).
     */
    public function isSafeForActivity(array $weatherData, string $activityType): bool {
        if (isset($weatherData[0]) && is_array($weatherData[0])) {
            foreach ($weatherData as $day) {
                if (!$this->isSafeForActivity($day, $activityType)) return false;
            }
            return true;
        }
        // Día de previsión: claves *_max / precipitation (suma diaria)
        $isDay  = array_key_exists('windspeed_max', $weatherData);
        $wind   = (float) ($isDay ? ($weatherData['windspeed_max'] ?? 0) : ($weatherData['windspeed'] ?? 0));
        $gusts  = (float) ($isDay ? ($weatherData['windgusts_max'] ?? 0) : ($weatherData['windgusts'] ?? 0));
        $precip = (float) ($weatherData['precipitation'] ?? 0);
        $tMin   = $isDay ? ($weatherData['temp_min'] ?? null) : ($weatherData['temp'] ?? null);
        $tMax   = $isDay ? ($weatherData['temp_max'] ?? null) : ($weatherData['temp'] ?? null);
        $code   = (int) ($weatherData['weathercode'] ?? 0);

        if ($wind > self::THRESHOLDS['wind_kmh'] || $gusts > 80) return false;
        if ($tMin !== null && $tMin < self::THRESHOLDS['temp_min']) return false;
        if ($tMax !== null && $tMax > self::THRESHOLDS['temp_max']) return false;
        if (in_array($code, self::DANGEROUS_CODES, true)) return false;

        switch ($activityType) {
            case 'ski_touring':
            case 'snowshoeing':
                // La nieve y los aludes se valoran con el boletín oficial, no con el tiempo
                if ($precip > 15) return false;
                break;
            case 'climbing':
                if ($precip > 0.5 || $wind > 30) return false;
                break;
            default: // hiking, btt, cycle_touring
                if ($precip > ($isDay ? 10 : 5)) return false;
        }
        return true;
    }

    /**
     * Recomendaciones de equipamiento según condiciones y actividad.
     */
    public function getWeatherRecommendations(array $weatherData, string $activityType): array {
        if (isset($weatherData[0]) && is_array($weatherData[0])) {
            $all = [];
            foreach ($weatherData as $day) {
                foreach ($this->getWeatherRecommendations($day, $activityType) as $r) $all[$r] = true;
            }
            return array_keys($all);
        }
        // Mismas magnitudes y umbrales que isSafeForActivity(): cada motivo de "no es seguro" tiene su aviso
        $isDay  = array_key_exists('windspeed_max', $weatherData);
        $wind   = (float) ($isDay ? ($weatherData['windspeed_max'] ?? 0) : ($weatherData['windspeed'] ?? 0));
        $gusts  = (float) ($isDay ? ($weatherData['windgusts_max'] ?? 0) : ($weatherData['windgusts'] ?? 0));
        $tMin   = $isDay ? ($weatherData['temp_min'] ?? null) : ($weatherData['temp'] ?? null);
        $tMax   = $isDay ? ($weatherData['temp_max'] ?? null) : ($weatherData['temp'] ?? null);
        $precip = (float) ($weatherData['precipitation'] ?? 0);
        $code   = (int) ($weatherData['weathercode'] ?? 0);
        $recs   = [];

        if ($gusts > 80)       $recs[] = 'Rachas de viento muy fuertes (' . round($gusts) . ' km/h): evita crestas y zonas expuestas.';
        elseif ($wind > self::THRESHOLDS['wind_kmh']) $recs[] = 'Viento muy fuerte: evita crestas y zonas expuestas.';
        elseif ($wind > 30 || $gusts > 50) $recs[] = 'Viento fuerte: lleva ropa cortavientos.';
        if ($tMin !== null && $tMin < self::THRESHOLDS['temp_min']) $recs[] = 'Frío extremo (' . round((float) $tMin) . ' °C): equipo térmico imprescindible.';
        elseif ($tMin !== null && $tMin < 0) $recs[] = 'Temperaturas bajo cero: equipo térmico imprescindible.';
        elseif ($tMin !== null && $tMin < 5) $recs[] = 'Temperatura baja: lleva capas de abrigo.';
        if ($tMax !== null && $tMax > self::THRESHOLDS['temp_max']) $recs[] = 'Calor extremo (' . round((float) $tMax) . ' °C): evita las horas centrales, mucha agua y protección solar.';
        elseif ($tMax !== null && $tMax > 28) $recs[] = 'Calor intenso: hidratación y protección solar.';
        if ($precip > 0)       $recs[] = 'Precipitación prevista: lleva ropa impermeable.';
        if (in_array($code, [45, 48], true)) $recs[] = 'Niebla prevista: usa brújula o GPS.';
        if (in_array($code, self::DANGEROUS_CODES, true)) {
            $recs[] = 'ALERTA: Tormenta eléctrica prevista. No salgas a zonas expuestas.';
        }

        switch ($activityType) {
            case 'ski_touring':
            case 'snowshoeing':
                $isoZero = $weatherData['iso_zero'] ?? null;
                if ($isoZero && $isoZero < 1500) $recs[] = "Cota de nieve baja ({$isoZero} m): verifica el acceso.";
                if ($precip > 15) $recs[] = 'Nevada o lluvia abundante: consulta el boletín de aludes antes de salir.';
                break;
            case 'climbing':
                if ($precip > 0.5) $recs[] = 'Roca mojada: no es día de escalada.';
                break;
            case 'hiking':
                if (($weatherData['visibility_status'] ?? '') === 'poor') {
                    $recs[] = 'Visibilidad reducida: lleva GPS y mapa físico.';
                }
                if ($precip > ($isDay ? 10 : 5)) $recs[] = 'Lluvia abundante: barro y arroyos crecidos; valora aplazar.';
                break;
            default:
                if ($precip > ($isDay ? 10 : 5)) $recs[] = 'Lluvia abundante: barro y arroyos crecidos; valora aplazar.';
        }

        return $recs;
    }

    /**
     * Cota de nieve aproximada basada en isoterma 0°C del pronóstico.
     */
    public function getSnowLevel(float $latitude, float $longitude): ?int {
        return $this->getIsoZeroLevel($latitude, $longitude);
    }

    /**
     * Altura de la isoterma 0°C (freezing_level_height) del pronóstico de hoy.
     * NOTA: freezing_level_height es variable HORARIA en Open-Meteo, no diaria.
     */
    public function getIsoZeroLevel(float $latitude, float $longitude): ?int {
        $cacheKey = "isozero_{$latitude}_{$longitude}";
        $cached   = $this->getCache($cacheKey);
        if ($cached !== null) return $cached;

        try {
            $params = $this->baseParams($latitude, $longitude, 1);
            $params['hourly'] = 'freezing_level_height';
            // sin 'daily' para no causar HTTP 400

            $raw    = $this->request($params);
            $values = $raw['hourly']['freezing_level_height'] ?? [];
            if (empty($values)) return null;

            // Tomar el valor de mediodía del primer día (índice 12), o el más cercano disponible
            $idx   = min(12, count($values) - 1);
            $value = $values[$idx] ?? null;
            if ($value === null) return null;

            $value = (int)$value;
            $this->setCache($cacheKey, $value);
            return $value;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Precipitación acumulada en los últimos 3 días (Open-Meteo past_days=3).
     * Útil para estimar riesgo de barro o avalanchas por nieve reciente.
     */
    /**
     * Grosor del manto de nieve (Open-Meteo, variable horaria `snow_depth`).
     * Devuelve ['depth_m' => float, 'elevation' => int] o null si sin datos.
     * La clave 'elevation' es la altitud de la celda del modelo — permite saber
     * a qué altitud corresponde el grosor medido.
     */
    public function getSnowDepth(float $latitude, float $longitude): ?array {
        $cacheKey = "snowdepth_{$latitude}_{$longitude}";
        $cached   = $this->getCache($cacheKey);
        // setCache guarda [] cuando no hay nieve → tratamos [] como "sin datos conocidos"
        if ($cached !== null) return !empty($cached) ? $cached : null;

        try {
            $params = $this->baseParams($latitude, $longitude, 1);
            $params['hourly'] = 'snow_depth';

            $raw       = $this->request($params);
            $modelElev = (int)($raw['elevation'] ?? 0);
            $values    = $raw['hourly']['snow_depth'] ?? [];

            if (empty($values)) {
                $this->setCache($cacheKey, []);
                return null;
            }

            // Tomar el valor de la hora actual
            $now   = time();
            $times = $raw['hourly']['time'] ?? [];
            $idx   = 0;
            foreach ($times as $i => $t) {
                if (strtotime($t) <= $now) $idx = $i;
                else break;
            }

            $depth = $values[$idx] ?? null;
            if ($depth === null) {
                $this->setCache($cacheKey, []);
                return null;
            }

            $result = ['depth_m' => round((float)$depth, 2), 'elevation' => $modelElev];
            $this->setCache($cacheKey, $result);
            return $result;

        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Estima la cota de nieve mediante muestreo multi-punto (N y S).
     *
     * Genera 5 puntos hacia el norte y 5 hacia el sur desde la coordenada base
     * (offsets de 0.01°–0.05° lat, ≈1–5 km), los consulta en una sola request
     * batch a Open-Meteo y detecta la transición sin-nieve → con-nieve por
     * orientación usando la variable horaria `snow_depth`.
     *
     * Ventaja sobre getSnowDepth(): en vez de un único punto del modelo
     * (que puede estar a baja altitud y mostrar 0 cm aunque encima haya nieve),
     * muestreamos un rango altitudinal real y encontramos dónde empieza el manto.
     *
     * @return array{
     *   line_n_m: int|null,       cota de nieve umbría/norte (metros)
     *   line_s_m: int|null,       cota de nieve solana/sur
     *   depth_max_n_cm: int,      grosor máximo detectado en N (cm)
     *   depth_max_s_cm: int,      grosor máximo detectado en S (cm)
     *   confidence: string,       'alta'|'media'|'baja'
     *   points_n: array,          datos brutos puntos norte [{elev, depth_cm}]
     *   points_s: array,          datos brutos puntos sur
     * }
     */
    public function getSnowLine(float $latitude, float $longitude): array {
        $cacheKey = "snowline_{$latitude}_{$longitude}";
        $cached   = $this->getCache($cacheKey);
        if ($cached !== null) return $cached;

        // 5 offsets de latitud (~1 km cada uno)
        $offsets = [0.01, 0.02, 0.03, 0.04, 0.05];
        $allLats = [];
        $allLons = [];
        $allDirs = [];

        foreach ($offsets as $d) {
            $allLats[] = round($latitude + $d, 4);   // Norte
            $allLons[] = round($longitude,     4);
            $allDirs[] = 'N';
        }
        foreach ($offsets as $d) {
            $allLats[] = round($latitude - $d, 4);   // Sur
            $allLons[] = round($longitude,     4);
            $allDirs[] = 'S';
        }

        try {
            $params = [
                'latitude'      => implode(',', $allLats),
                'longitude'     => implode(',', $allLons),
                'hourly'        => 'snow_depth',
                'forecast_days' => 1,
                'timezone'      => 'Europe/Madrid',
            ];

            // requestBatch construye la URL sin codificar las comas de lat/lon
            $locations = $this->requestBatch($params);

            // Índice de hora actual (dentro del forecast_days=1, max 24 slots)
            $now     = time();
            $timeIdx = 0;
            $times   = $locations[0]['hourly']['time'] ?? [];
            foreach ($times as $i => $t) {
                if (strtotime($t) <= $now) $timeIdx = $i;
                else break;
            }

            // Construir puntos por orientación
            $pointsN = [];
            $pointsS = [];
            foreach ($locations as $i => $loc) {
                $elev    = (int)($loc['elevation'] ?? 0);
                $depthM  = (float)($loc['hourly']['snow_depth'][$timeIdx] ?? 0);
                $depthCm = (int)round($depthM * 100);
                $point   = ['elev' => $elev, 'depth_cm' => $depthCm];
                if (($allDirs[$i] ?? 'N') === 'N') $pointsN[] = $point;
                else                                $pointsS[] = $point;
            }

            // Ordenar por elevación ascendente para leer la transición
            usort($pointsN, fn($a, $b) => $a['elev'] <=> $b['elev']);
            usort($pointsS, fn($a, $b) => $a['elev'] <=> $b['elev']);

            $result = [
                'line_n_m'       => $this->findSnowLineTrans($pointsN),
                'line_s_m'       => $this->findSnowLineTrans($pointsS),
                'depth_max_n_cm' => !empty($pointsN) ? max(array_column($pointsN, 'depth_cm')) : 0,
                'depth_max_s_cm' => !empty($pointsS) ? max(array_column($pointsS, 'depth_cm')) : 0,
                'confidence'     => count($locations) >= 8 ? 'alta' : (count($locations) >= 4 ? 'media' : 'baja'),
                'points_n'       => $pointsN,
                'points_s'       => $pointsS,
            ];

            $this->setCache($cacheKey, $result);
            return $result;

        } catch (\Exception $e) {
            $this->logger->error('getSnowLine error', ['error' => $e->getMessage()]);
            return $this->emptySnowLine();
        }
    }

    /**
     * Encuentra la cota de transición sin-nieve → con-nieve.
     * Umbral de manto significativo: 5 cm.
     *
     * @param  array $points  [{elev: int, depth_cm: int}] ordenados por elev asc
     * @return int|null       Altitud estimada de inicio de nieve, null si no detectada
     */
    private function findSnowLineTrans(array $points): ?int {
        if (empty($points)) return null;

        // Si el punto más bajo ya tiene nieve → la cota baja más de lo que alcanzamos
        if (($points[0]['depth_cm'] ?? 0) > 5) {
            return max(0, $points[0]['elev'] - 200);
        }

        // Buscar primer par donde curr ≤ 5 cm y next > 5 cm
        for ($i = 0; $i < count($points) - 1; $i++) {
            $curr = $points[$i]['depth_cm']     ?? 0;
            $next = $points[$i + 1]['depth_cm'] ?? 0;
            if ($curr <= 5 && $next > 5) {
                // Interpolar entre los dos niveles
                return (int)(($points[$i]['elev'] + $points[$i + 1]['elev']) / 2);
            }
        }

        // Ningún punto supera el umbral → sin nieve detectada en el rango
        return null;
    }

    /** Resultado vacío cuando getSnowLine falla */
    private function emptySnowLine(): array {
        return [
            'line_n_m'       => null,
            'line_s_m'       => null,
            'depth_max_n_cm' => 0,
            'depth_max_s_cm' => 0,
            'confidence'     => 'baja',
            'points_n'       => [],
            'points_s'       => [],
        ];
    }

    /**
     * Nieve reciente en un punto: nevada acumulada de los últimos 7 días (Open-Meteo, snowfall_sum),
     * fecha de la última nevada y espesor de manto que da el modelo ahora mismo en ese punto.
     * El modelo trabaja a la altitud de su celda (devuelta en 'elevation'), no a la de cada cumbre.
     *
     * @return array{snowfall_7d_cm: float, last_snow_date: ?string, depth_cm: ?int, elevation: ?int}|null
     */
    public function getRecentSnow(float $latitude, float $longitude): ?array {
        $cacheKey = "recentsnow_{$latitude}_{$longitude}";
        $cached   = $this->getCache($cacheKey);
        if ($cached !== null) return $cached ?: null;

        try {
            $raw = $this->request([
                'latitude'      => $latitude,
                'longitude'     => $longitude,
                'past_days'     => 7,
                'forecast_days' => 1,
                'daily'         => 'snowfall_sum',
                'hourly'        => 'snow_depth',
                'timezone'      => 'Europe/Madrid',
            ]);
            $dates = $raw['daily']['time'] ?? [];
            $snow  = $raw['daily']['snowfall_sum'] ?? [];
            $today = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Madrid')))->format('Y-m-d');
            $total = 0.0;
            $last  = null;
            foreach ($dates as $i => $d) {
                if ($d >= $today) break;                 // solo días ya pasados
                $v = (float) ($snow[$i] ?? 0);
                $total += $v;
                if ($v >= 1.0) $last = $d;
            }
            // Espesor de manto a la hora actual
            $depth = null;
            $times = $raw['hourly']['time'] ?? [];
            $now   = time();
            foreach ($times as $i => $t) {
                if (strtotime($t) > $now) break;
                $v = $raw['hourly']['snow_depth'][$i] ?? null;
                if ($v !== null) $depth = (int) round((float) $v * 100);
            }
            $result = [
                'snowfall_7d_cm' => round($total, 1),
                'last_snow_date' => $last,
                'depth_cm'       => $depth,
                'elevation'      => isset($raw['elevation']) ? (int) $raw['elevation'] : null,
            ];
            $this->setCache($cacheKey, $result);
            return $result;
        } catch (\Exception $e) {
            $this->logger->warning('getRecentSnow error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getPrecip3d(float $latitude, float $longitude): float {
        $cacheKey = "precip3d_{$latitude}_{$longitude}";
        $cached   = $this->getCache($cacheKey);
        if ($cached !== null) return (float)$cached;

        try {
            $params = [
                'latitude'      => $latitude,
                'longitude'     => $longitude,
                'past_days'     => 3,
                'forecast_days' => 1,
                'daily'         => 'precipitation_sum',
                'timezone'      => 'Europe/Madrid',
            ];
            $raw    = $this->request($params);
            $values = $raw['daily']['precipitation_sum'] ?? [];
            // past_days=3 devuelve 3 días pasados + hoy = hasta 4 valores; tomamos los 3 pasados
            $past3  = array_slice($values, 0, 3);
            $total  = (float)array_sum(array_filter($past3, fn($v) => $v !== null));
            $this->setCache($cacheKey, $total);
            return $total;
        } catch (\Exception) {
            return 0.0;
        }
    }

    public function isLocationCovered(float $latitude, float $longitude): bool {
        // Open-Meteo es global; cubrimos España + sur de Francia + Islas Canarias y Baleares
        return $latitude  >= 27.6 && $latitude  <= 43.9
            && $longitude >= -18.2 && $longitude <= 4.5;
    }

    public function getUVIndex(float $latitude, float $longitude): int {
        try {
            $params = $this->baseParams($latitude, $longitude, 1);
            $params['daily']  = 'uv_index_max';
            $params['hourly'] = '';
            $raw = $this->request($params);
            return (int) ($raw['daily']['uv_index_max'][0] ?? 0);
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * Datos históricos usando la API de archivo de Open-Meteo.
     * Endpoint gratuito: https://archive-api.open-meteo.com/v1/archive
     */
    public function getHistoricalWeather(float $latitude, float $longitude, \DateTime $date): array {
        try {
            $dateStr = $date->format('Y-m-d');
            $url = 'https://archive-api.open-meteo.com/v1/archive?' . http_build_query([
                'latitude'   => $latitude,
                'longitude'  => $longitude,
                'start_date' => $dateStr,
                'end_date'   => $dateStr,
                'daily'      => implode(',', [
                    'temperature_2m_max', 'temperature_2m_min',
                    'precipitation_sum', 'windspeed_10m_max', 'weathercode',
                ]),
                'timezone'   => 'Europe/Madrid',
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => defined('API_TIMEOUT') ? API_TIMEOUT : 30,
                CURLOPT_USERAGENT      => 'KahirAI/1.0',
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200 || !$body) return [];

            $raw = json_decode($body, true);
            $d   = $raw['daily'] ?? [];

            return [
                'date'        => $dateStr,
                'temp_max'    => $d['temperature_2m_max'][0]  ?? null,
                'temp_min'    => $d['temperature_2m_min'][0]  ?? null,
                'precipitation' => $d['precipitation_sum'][0] ?? 0,
                'windspeed'   => $d['windspeed_10m_max'][0]   ?? 0,
                'weathercode' => $d['weathercode'][0]          ?? 0,
                'description' => $this->codeToDescription($d['weathercode'][0] ?? 0),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error obteniendo datos históricos', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Open-Meteo es gratuito y sin clave; comprobamos que la API responde.
     */
    public function checkAPIStatus(): bool {
        try {
            $ch = curl_init('https://api.open-meteo.com/v1/forecast?latitude=42.8&longitude=0.5&current_weather=true');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_NOBODY         => true,   // HEAD-like, sin descargar cuerpo
                CURLOPT_USERAGENT      => 'KahirAI/1.0',
            ]);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code === 200;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Open-Meteo no tiene límites de uso (API pública y gratuita).
     */
    public function getAPILimits(): array {
        return [
            'provider'       => 'Open-Meteo',
            'requires_key'   => false,
            'daily_limit'    => 'ilimitado',
            'rate_limit'     => '10 000 req/día (fair use)',
            'max_forecast'   => '16 días',
            'historical'     => 'desde 1940 (archive-api)',
            'cost'           => 'gratuito',
        ];
    }

    /**
     * Formatea datos meteorológicos en tres niveles de detalle.
     */
    public function formatWeatherData(array $weatherData, string $format = 'basic'): array {
        $base = [
            'temperature' => $weatherData['temp']        ?? $weatherData['temp_max'] ?? null,
            'description' => $weatherData['description'] ?? '',
            'windspeed'   => $weatherData['windspeed']   ?? $weatherData['windspeed_max'] ?? 0,
            'safe'        => !in_array($weatherData['weathercode'] ?? 0, self::DANGEROUS_CODES, true),
        ];

        if ($format === 'basic') {
            return $base;
        }

        $detailed = array_merge($base, [
            'feels_like'    => $weatherData['feels_like']    ?? null,
            'precipitation' => $weatherData['precipitation'] ?? $weatherData['precipitation_sum'] ?? 0,
            'windgusts'     => $weatherData['windgusts']     ?? $weatherData['windgusts_max'] ?? 0,
            'weathercode'   => $weatherData['weathercode']   ?? 0,
            'cloudcover'    => $weatherData['cloudcover']    ?? null,
        ]);

        if ($format === 'detailed') {
            return $detailed;
        }

        // 'technical': todo
        return array_merge($detailed, [
            'snow_depth'     => $weatherData['snow_depth']    ?? 0,
            'snowfall'       => $weatherData['snowfall']      ?? 0,
            'freezing_level' => $weatherData['freezing_level'] ?? $weatherData['iso_zero'] ?? null,
            'uv_index'       => $weatherData['uv_index']      ?? null,
            'sunrise'        => $weatherData['sunrise']        ?? null,
            'sunset'         => $weatherData['sunset']         ?? null,
            'mountain'       => $weatherData['mountain']       ?? [],
        ]);
    }

    // -----------------------------------------------------------------
    // Fetch helpers
    // -----------------------------------------------------------------

    private function fetchHourly(float $lat, float $lng, int $days, ?float $elevation): array {
        $params = $this->baseParams($lat, $lng, $days, $elevation);
        $params['hourly'] = implode(',', [
            'temperature_2m', 'apparent_temperature',
            'precipitation', 'rain', 'snowfall', 'snow_depth',
            'windspeed_10m', 'windgusts_10m', 'winddirection_10m',
            'weathercode', 'cloudcover',
        ]);
        return $this->request($params);
    }

    private function fetchDaily(float $lat, float $lng, int $days, ?float $elevation): array {
        $params = $this->baseParams($lat, $lng, $days, $elevation);
        // NOTA: snow_depth y freezing_level_height son variables HORARIAS en la
        // API actual de Open-Meteo — incluirlas en daily causa HTTP 400 y el
        // forecast fallaba siempre. Se eliminan; freezing_level se obtiene por
        // el endpoint de isozero (fetchHourly) cuando se necesite.
        $params['daily'] = implode(',', [
            'temperature_2m_max', 'temperature_2m_min',
            'apparent_temperature_max', 'apparent_temperature_min',
            'precipitation_sum', 'precipitation_probability_max',
            'rain_sum', 'snowfall_sum',
            'windspeed_10m_max', 'windgusts_10m_max', 'winddirection_10m_dominant',
            'weathercode',
            'uv_index_max',
            'sunrise', 'sunset',
        ]);
        return $this->request($params);
    }

    private function baseParams(float $lat, float $lng, int $days, ?float $elevation = null): array {
        $params = [
            'latitude'      => $lat,
            'longitude'     => $lng,
            'forecast_days' => $days,
            'timezone'      => 'Europe/Madrid',
        ];
        if ($elevation !== null) {
            $params['elevation'] = $elevation;
        }
        return $params;
    }

    // -----------------------------------------------------------------
    // Parsers
    // -----------------------------------------------------------------

    private function parseCurrentFromHourly(array $raw): array {
        $h = $raw['hourly'] ?? [];
        // Primer slot de la hora actual
        $now   = time();
        $times = $h['time'] ?? [];
        $idx   = 0;
        foreach ($times as $i => $t) {
            if (strtotime($t) <= $now) $idx = $i;
            else break;
        }

        return [
            'temp'          => $h['temperature_2m'][$idx]      ?? null,
            'feels_like'    => $h['apparent_temperature'][$idx] ?? null,
            'windspeed'     => $h['windspeed_10m'][$idx]        ?? 0,
            'windgusts'     => $h['windgusts_10m'][$idx]        ?? 0,
            'wind_dir'      => $h['winddirection_10m'][$idx]    ?? 0,
            'precipitation' => $h['precipitation'][$idx]        ?? 0,
            'snowfall'      => $h['snowfall'][$idx]             ?? 0,
            'snow_depth'    => $h['snow_depth'][$idx]           ?? 0,
            'weathercode'   => $h['weathercode'][$idx]          ?? 0,
            'cloudcover'    => $h['cloudcover'][$idx]           ?? 0,
            'description'   => $this->codeToDescription($h['weathercode'][$idx] ?? 0),
        ];
    }

    private function parseDaily(array $raw, int $days): array {
        $d      = $raw['daily'] ?? [];
        $dates  = $d['time'] ?? [];
        $result = [];

        for ($i = 0; $i < min($days, count($dates)); $i++) {
            $result[] = [
                'date'                 => $dates[$i],
                'temp_max'             => $d['temperature_2m_max'][$i]          ?? null,
                'temp_min'             => $d['temperature_2m_min'][$i]          ?? null,
                'feels_max'            => $d['apparent_temperature_max'][$i]    ?? null,
                'feels_min'            => $d['apparent_temperature_min'][$i]    ?? null,
                'precipitation'        => $d['precipitation_sum'][$i]             ?? 0,
                'precip_probability'   => $d['precipitation_probability_max'][$i] ?? 0,
                'rain'                 => $d['rain_sum'][$i]                      ?? 0,
                'snowfall'             => $d['snowfall_sum'][$i]                  ?? 0,
                'snow_depth'           => null,   // variable horaria, no disponible en daily
                'windspeed_max'        => $d['windspeed_10m_max'][$i]             ?? 0,
                'windgusts_max'        => $d['windgusts_10m_max'][$i]             ?? 0,
                'wind_dir'             => $d['winddirection_10m_dominant'][$i]    ?? 0,
                'weathercode'          => $d['weathercode'][$i]                   ?? 0,
                'description'          => $this->codeToDescription($d['weathercode'][$i] ?? 0),
                'freezing_level'       => null,   // obtener via getIsoZeroLevel() si se necesita
                'uv_index'             => $d['uv_index_max'][$i]                  ?? 0,
                'sunrise'              => $d['sunrise'][$i]                     ?? null,
                'sunset'               => $d['sunset'][$i]                      ?? null,
                'safe_for_skiing'      => $this->isDaySafeForSkiing($d, $i),
                'safe_for_hiking'      => $this->isDaySafeForHiking($d, $i),
            ];
        }

        return $result;
    }

    // -----------------------------------------------------------------
    // HTTP
    // -----------------------------------------------------------------

    private function request(array $params): array {
        $url = self::BASE_URL . '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => defined('API_TIMEOUT') ? API_TIMEOUT : 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'KahirAI/1.0',
        ]);

        $body    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new WeatherException('API_CONNECTION_ERROR', ['error' => $curlErr]);
        }
        if ($code !== 200) {
            throw new WeatherException('API_RESPONSE_ERROR', ['http_code' => $code]);
        }

        $data = json_decode($body, true);
        if (!$data) {
            throw new WeatherException('DATA_PARSING_ERROR');
        }

        return $data;
    }

    /**
     * Versión de request() para múltiples coordenadas (getSnowLine).
     *
     * Open-Meteo acepta lat/lon separados por comas en la URL, pero
     * http_build_query codifica las comas como %2C. Esta función construye
     * la URL manualmente para preservar las comas y devuelve siempre un
     * array de objetos de ubicación (normaliza el caso de un solo resultado).
     *
     * @param  array $params  Igual que request(), pero 'latitude' y 'longitude'
     *                        son strings de valores separados por coma.
     * @return array          Array de ubicaciones [{elevation, hourly, ...}]
     */
    private function requestBatch(array $params): array {
        // Extraer lat/lon antes de http_build_query (que codificaría las comas)
        $lats = $params['latitude'];
        $lons = $params['longitude'];
        unset($params['latitude'], $params['longitude']);

        $query = http_build_query($params);
        $url   = self::BASE_URL . "?latitude={$lats}&longitude={$lons}&{$query}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => defined('API_TIMEOUT') ? API_TIMEOUT : 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'KahirAI/1.0',
        ]);

        $body    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) throw new WeatherException('API_CONNECTION_ERROR', ['error' => $curlErr]);
        if ($code !== 200) {
            $this->logger->error('requestBatch HTTP error', ['code' => $code, 'url' => $url]);
            throw new WeatherException('API_RESPONSE_ERROR', ['http_code' => $code]);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) throw new WeatherException('DATA_PARSING_ERROR');

        // Un solo punto devuelve objeto {latitude, ...}; múltiples devuelven array
        if (isset($data['latitude'])) $data = [$data];

        return $data;
    }

    // -----------------------------------------------------------------
    // Cálculos
    // -----------------------------------------------------------------

    private function calcWindChill(float $temp, float $windKmh): float {
        if ($temp > 10 || $windKmh < 4.8) return $temp;
        return 13.12 + 0.6215 * $temp
            - 11.37 * pow($windKmh, 0.16)
            + 0.3965 * $temp * pow($windKmh, 0.16);
    }

    private function calcAvalancheRisk(array $weather, ?int $snowLevel): int {
        if (!$snowLevel || $snowLevel < 1000) return 0;
        $risk = 1;
        if (($weather['temp'] ?? -5) > 0)  $risk++;
        if (($weather['temp'] ?? -5) > 5)  $risk++;
        if (($weather['windspeed'] ?? 0) > 40) $risk++;
        if (($weather['snowfall'] ?? 0) > 10)  $risk++;
        return min($risk, 5);
    }

    private function assessVisibility(int $wmoCode): string {
        return in_array($wmoCode, [45, 48], true) ? 'poor' : 'good';
    }

    private function isDaySafeForSkiing(array $d, int $i): bool {
        $code = $d['weathercode'][$i]       ?? 0;
        $wind = $d['windspeed_10m_max'][$i] ?? 0;
        $snow = $d['snowfall_sum'][$i]       ?? 0;
        if (in_array($code, self::DANGEROUS_CODES, true)) return false;
        if ($wind > self::THRESHOLDS['wind_kmh']) return false;
        // freezing_level_height no está disponible como daily; lo omitimos
        if ($snow < 5) return false; // criterio mínimo de nieve fresca
        return true;
    }

    private function isDaySafeForHiking(array $d, int $i): bool {
        $code  = $d['weathercode'][$i]       ?? 0;
        $wind  = $d['windspeed_10m_max'][$i] ?? 0;
        $precip = $d['precipitation_sum'][$i] ?? 0;
        if (in_array($code, self::DANGEROUS_CODES, true)) return false;
        if ($wind > self::THRESHOLDS['wind_kmh']) return false;
        if ($precip > 10) return false;
        return true;
    }

    // -----------------------------------------------------------------
    // Ventana óptima de tiempo para una actividad
    // -----------------------------------------------------------------

    /**
     * Encuentra la mejor ventana de días consecutivos aptos para una actividad.
     *
     * Usa los campos safe_for_hiking / safe_for_skiing de parseDaily().
     * La confianza baja conforme avanza el horizonte temporal:
     *   - Días 1–3  (índices 0–2): 'alta'
     *   - Días 4–7  (índices 3–6): 'media'
     *   - Días 8+   (índice  7+ ): 'baja'  (pronóstico orientativo)
     *
     * @param array  $forecast  Resultado de getForecast() — array plano de día-objetos
     * @param string $activity  'hiking' | 'ski_touring' | 'snowshoeing' | …
     * @return array {
     *   found: bool,
     *   start_date: string|null,
     *   end_date: string|null,
     *   start_day_idx: int,   // índice base-0 desde hoy
     *   length: int,          // días consecutivos en la ventana
     *   days: array,          // día-objetos incluidos
     *   confidence: string,   // 'alta' | 'media' | 'baja'
     *   summary: string       // descripción legible para el prompt de Sonnet
     * }
     */
    public function getOptimalWindow(array $forecast, string $activity = 'hiking'): array {
        $empty = [
            'found'         => false,
            'start_date'    => null,
            'end_date'      => null,
            'start_day_idx' => 0,
            'length'        => 0,
            'days'          => [],
            'confidence'    => 'baja',
            'summary'       => '',
        ];

        if (empty($forecast)) {
            $empty['summary'] = 'Pronóstico no disponible.';
            return $empty;
        }

        $safeKey = in_array($activity, ['ski_touring', 'snowshoeing'], true)
            ? 'safe_for_skiing' : 'safe_for_hiking';

        // Buscar la carrera de días seguros más larga
        $bestStart  = null;
        $bestLength = 0;
        $curStart   = null;
        $curLen     = 0;

        foreach ($forecast as $i => $day) {
            if ($day[$safeKey] ?? true) {
                if ($curStart === null) { $curStart = $i; $curLen = 0; }
                $curLen++;
                if ($curLen > $bestLength) {
                    $bestStart  = $curStart;
                    $bestLength = $curLen;
                }
            } else {
                $curStart = null;
                $curLen   = 0;
            }
        }

        if ($bestStart === null || $bestLength === 0) {
            $total = count($forecast);
            $empty['summary'] = "No se detecta ninguna ventana apta en los próximos {$total} días según el pronóstico.";
            return $empty;
        }

        // Confianza según el índice final de la ventana
        $endIdx     = $bestStart + $bestLength - 1;
        $confidence = $endIdx <= 2 ? 'alta' : ($endIdx <= 6 ? 'media' : 'baja');

        $startDate = $forecast[$bestStart]['date']                     ?? null;
        $endDate   = $forecast[$bestStart + $bestLength - 1]['date']   ?? null;
        $days      = array_slice($forecast, $bestStart, $bestLength);

        $reliabilityNote = match ($confidence) {
            'alta'  => '',
            'media' => ' (confianza media: margen de error notable; revisar 48 h antes)',
            'baja'  => ' (confianza baja: pronóstico orientativo a más de 7 días; revisar próxima semana)',
        };

        $rangeStr = ($bestLength === 1)
            ? $startDate
            : "{$startDate} – {$endDate} ({$bestLength} días consecutivos)";

        $summary = "Ventana óptima: {$rangeStr}{$reliabilityNote}.";

        return [
            'found'         => true,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'start_day_idx' => $bestStart,
            'length'        => $bestLength,
            'days'          => $days,
            'confidence'    => $confidence,
            'summary'       => $summary,
        ];
    }

    // -----------------------------------------------------------------
    // WMO Weather Interpretation Codes → descripción en español
    // -----------------------------------------------------------------

    private function codeToDescription(int $code): string {
        return match (true) {
            $code === 0            => 'Cielo despejado',
            $code === 1            => 'Mayormente despejado',
            $code === 2            => 'Parcialmente nublado',
            $code === 3            => 'Nublado',
            in_array($code, [45, 48]) => 'Niebla',
            in_array($code, [51, 53, 55]) => 'Llovizna',
            in_array($code, [61, 63]) => 'Lluvia moderada',
            $code === 65           => 'Lluvia intensa',
            in_array($code, [71, 73]) => 'Nevada moderada',
            $code === 75           => 'Nevada intensa',
            $code === 77           => 'Granizo de nieve',
            in_array($code, [80, 81]) => 'Chubascos',
            $code === 82           => 'Chubascos intensos',
            in_array($code, [85, 86]) => 'Chubascos de nieve',
            $code === 95           => 'Tormenta eléctrica',
            in_array($code, [96, 99]) => 'Tormenta con granizo',
            default                => 'Condiciones variables',
        };
    }

    // -----------------------------------------------------------------
    // Caché en fichero temporal
    // -----------------------------------------------------------------

    private function getCache(string $key): mixed {
        $file = sys_get_temp_dir() . '/kahir_weather_' . md5($key);
        if (!file_exists($file)) return null;
        $raw = json_decode(file_get_contents($file), true);
        if (!$raw || (time() - $raw['t']) > self::CACHE_SECONDS) {
            @unlink($file);
            return null;
        }
        return $raw['d'];
    }

    private function setCache(string $key, mixed $data): void {
        $file = sys_get_temp_dir() . '/kahir_weather_' . md5($key);
        file_put_contents($file, json_encode(['t' => time(), 'd' => $data]), LOCK_EX);
    }
}
