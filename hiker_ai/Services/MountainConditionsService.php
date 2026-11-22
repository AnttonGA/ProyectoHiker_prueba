<?php
/**
 * MountainConditionsService — Condiciones de montaña en tiempo real
 *
 * Fuentes de datos (con fallback en cascada):
 *   1. AEMET OpenData  → alertas CAP + BPA (aludes) + estaciones nivométricas (España)
 *   2. Météo-France    → alertas vigilancia + BRA (aludes) + estaciones Nivôses (Francia)
 *
 * Todas las llamadas están cacheadas en fichero temporal.
 * AEMET usa un proceso de dos pasos: primera petición → URL datos → segunda petición.
 * Météo-France usa OAuth2 client_credentials; si no hay credenciales, se omite silenciosamente.
 */

namespace KahirAI\Services;

class MountainConditionsService {

    private LoggerService $logger;

    // TTL de caché por tipo de dato
    private const CACHE_BPA   = 14400;  // 4 h  — boletines de aludes (se publican 1x/día)
    private const CACHE_ALERT = 1800;   // 30 min — alertas más dinámicas
    private const CACHE_SNOW  = 3600;   // 1 h  — estaciones nivométricas
    private const CACHE_TOKEN = 3500;   // ~58 min — token OAuth2 MF (válido 1 h)

    // ── Zonas de montaña AEMET ────────────────────────────────────────────
    // Mapeo coordenadas → zona para el endpoint de predicción de montaña de AEMET.
    // Los IDs son los códigos de zona que usa AEMET en su API OpenData.
    // Bounds generosos y solapados para evitar que coordenadas en el límite no caigan
    // en ninguna zona (ej: Candanchú a lon -0.52 cae en pirineo_navarro_aragones).
    private const AEMET_MOUNTAIN_ZONES = [
        'pirineo_navarro_aragones' => [
            'id' => '61',
            'lat_min' => 42.2, 'lat_max' => 43.3,
            'lon_min' => -2.0, 'lon_max' => -0.4,   // hasta -0.4 (cubre Candanchú -0.52)
            'label'   => 'Pirineo Navarro-Aragonés',
        ],
        'pirineo_aragones' => [
            'id' => '62',
            'lat_min' => 42.2, 'lat_max' => 43.1,
            'lon_min' => -0.6, 'lon_max' =>  0.75,  // hasta 0.75 para ceder la frontera a pirineo_catalan
            'label'   => 'Pirineo Aragonés',
        ],
        'pirineo_catalan' => [
            'id' => '63',
            'lat_min' => 41.8, 'lat_max' => 43.1,
            'lon_min' =>  0.70, 'lon_max' =>  3.5,  // desde 0.70 → cubre Aigüestortes (0.88°E) y Baqueira (0.93°E)
            'label'   => 'Pirineo Catalán',
        ],
        'cantabrico' => [
            'id' => '64',
            'lat_min' => 42.0, 'lat_max' => 44.0,
            'lon_min' => -8.0, 'lon_max' => -1.5,
            'label'   => 'Cordillera Cantábrica y Galicia',
        ],
        'sistema_central' => [
            'id' => '65',
            'lat_min' => 39.5, 'lat_max' => 41.8,
            'lon_min' => -7.0, 'lon_max' => -2.0,
            'label'   => 'Sistema Central',
        ],
        'sierra_nevada' => [
            'id' => '66',
            'lat_min' => 36.5, 'lat_max' => 38.5,
            'lon_min' => -5.5, 'lon_max' => -1.5,
            'label'   => 'Sierras Béticas / Sierra Nevada',
        ],
    ];

    // ── Macizos del Pirineo francés (Météo-France BRA) ────────────────────
    // IDs 64–74, distribuidos de oeste a este por longitud
    private const MF_MASSIFS = [
        64 => ['name' => 'Pays-Basque',       'lon_min' => -1.80, 'lon_max' => -0.85],
        65 => ['name' => 'Aspe-Ossau',         'lon_min' => -0.85, 'lon_max' => -0.20],
        66 => ['name' => 'Haute-Bigorre',      'lon_min' => -0.20, 'lon_max' =>  0.30],
        67 => ['name' => 'Aure-Louron',        'lon_min' =>  0.30, 'lon_max' =>  0.70],
        68 => ['name' => 'Luchonnais',         'lon_min' =>  0.70, 'lon_max' =>  1.10],
        69 => ['name' => 'Couserans',          'lon_min' =>  1.10, 'lon_max' =>  1.60],
        70 => ['name' => 'Haute-Ariège',       'lon_min' =>  1.60, 'lon_max' =>  2.00],
        71 => ['name' => 'Andorre',            'lon_min' =>  1.40, 'lon_max' =>  1.80],
        72 => ['name' => 'Orlu',               'lon_min' =>  2.00, 'lon_max' =>  2.30],
        73 => ['name' => 'Capcir-Puymorens',   'lon_min' =>  2.00, 'lon_max' =>  2.50],
        74 => ['name' => 'Cerdagne-Canigou',   'lon_min' =>  2.30, 'lon_max' =>  3.20],
    ];

    // ── Estaciones nivométricas de AEMET por zona ─────────────────────────
    // Códigos verificados del catálogo de estaciones AEMET
    private const AEMET_SNOW_STATIONS = [
        'Aragon' => [
            ['id' => '9198',  'name' => 'Candanchú',       'alt' => 1560],
            ['id' => '9263',  'name' => 'Formigal',        'alt' => 1526],
        ],
        'Western_Pyrenees' => [
            ['id' => '9222',  'name' => 'Isaba-Belagua',   'alt' => 1406],
        ],
        'Central_Pyrenees' => [
            ['id' => '0229I', 'name' => 'Boí-Taüll',       'alt' => 1540],
            ['id' => '0246',  'name' => 'La Molina',        'alt' => 1700],
        ],
        'Cantabria' => [
            ['id' => '1274',  'name' => 'Picos (Fuente Dé)','alt' => 1090],
        ],
        'Sistema_Central' => [
            ['id' => '3279',  'name' => 'Navacerrada',      'alt' => 1860],
            ['id' => '3286',  'name' => 'Cotos',            'alt' => 1830],
        ],
        'Sierra_Nevada' => [
            ['id' => '6293X', 'name' => 'Sierra Nevada',    'alt' => 2500],
        ],
    ];


    /** Segundos que se considera caída una fuente tras un error (corta-circuito). */
    private const HOST_DOWN_TTL = 900;

    public function __construct() {
        $this->logger = new LoggerService('mountain_conditions');
    }

    // =========================================================================
    // PUNTO DE ENTRADA PRINCIPAL
    // =========================================================================

    /**
     * Construye el informe completo de condiciones de montaña.
     *
     * @param float  $lat          Latitud del lugar
     * @param float  $lon          Longitud del lugar
     * @param string $zone         Zona del sistema (Aragon, Sierra_Nevada, etc.)
     * @param string $activity     Actividad (hiking, ski_touring, etc.)
     * @param int    $cotaCritica  Altitud crítica estimada de la ruta (startElevation + gain×0.6)
     * @param float  $precip3dMm   Precipitación acumulada últimos 3 días (de Open-Meteo past_days)
     * @param int|null $isoZero    Nivel isocero en metros (de Open-Meteo, puede ser null)
     */
    /**
     * @param float      $lat
     * @param float      $lon
     * @param string     $zone
     * @param string     $activity
     * @param int        $cotaCritica   Altitud crítica estimada de la ruta (m)
     * @param float      $precip3dMm   Precipitación acumulada 3 días (Open-Meteo past_days)
     * @param int|null   $isoZero      Isoterma 0°C en metros (Open-Meteo hourly)
     * @param array|null $snowLine     Resultado de WeatherService::getSnowLine():
     *                                 ['line_n_m' => int|null, 'line_s_m' => int|null,
     *                                  'depth_max_n_cm' => int, 'depth_max_s_cm' => int,
     *                                  'confidence' => string, 'points_n' => array, 'points_s' => array]
     *                                 Null si no está disponible; se usará AEMET o isocero como fallback.
     */
    public function buildConditionsReport(
        float   $lat,
        float   $lon,
        string  $zone,
        string  $activity,
        int     $cotaCritica,
        float   $precip3dMm  = 0.0,
        ?int    $isoZero     = null,
        ?array  $snowLine    = null   // resultado de WeatherService::getSnowLine()
    ): array {
        $report = [
            'alert_level'        => null,   // 'green'|'yellow'|'orange'|'red'
            'alert_types'        => [],     // ['viento', 'nieve', ...]
            'alert_areas'        => [],     // areaDesc de las features afectadas
            'avalanche_level'    => null,   // 1–5  (solo de fuente oficial)
            'avalanche_source'   => null,   // 'AEMET (boletín nivológico)'|'MeteoFrance'
            'avalanche_massif'   => null,
            'avalanche_unavailable' => false, // true si tocaba boletín y no se pudo obtener
            'snow_depth_cm'      => null,
            'snow_station_name'  => null,
            'snow_station_alt'   => null,
            'snow_at_route'      => null,   // bool
            'cota_nieve_est'     => null,   // metros estimados
            'terrain_mud_risk'   => false,
            'precip_3d_mm'       => $precip3dMm,
            'conditions_index'   => 'BUENAS',
            'narrative_context'  => '',
            'sources_used'       => [],
        ];

        $isFrenchSide   = ($lon > -1.8 && $lat >= 43.0 && $lat <= 44.5);
        // Todas las zonas con relieve significativo donde AEMET emite BPA o alertas de montaña.
        // Canarias (Teide) y Baleares (Tramontana) incluidas a efectos de alertas generales.
        $isMountainZone = in_array($zone, [
            'Aragon', 'Western_Pyrenees', 'Central_Pyrenees', 'Euskadi',
            'Cantabria', 'Galicia', 'Sistema_Central', 'Sistema_Iberico',
            'Sierra_Nevada', 'Andalucia', 'Francia_Sur', 'Canarias', 'Baleares', 'Levante',
        ], true);
        $isSnowRelevant = $this->isSnowSeason() ||
                          in_array($activity, ['ski_touring', 'snowshoeing'], true);

        // ── 1. Alertas ────────────────────────────────────────────────────
        $alerts = $isFrenchSide
            ? ($this->getMFAlerts($lat, $lon) ?? $this->getAemetAlerts($lat, $lon))
            : ($this->getAemetAlerts($lat, $lon) ?? $this->getMFAlerts($lat, $lon));

        if ($alerts) {
            $report['alert_level']    = $alerts['level'];
            $report['alert_types']    = $alerts['types'];
            $report['alert_areas']    = $alerts['areas'] ?? [];
            $report['sources_used'][] = $alerts['source'];
        }

        // ── 2. Boletín de aludes (solo en zonas y temporada relevantes) ───
        if ($isMountainZone && $isSnowRelevant) {
            // Solo fuentes oficiales: boletín nivológico de AEMET y BRA de Météo-France
            $bpa = $isFrenchSide
                ? ($this->getMFBRA($lat, $lon)    ?? $this->getAemetNivo($lat, $lon))
                : ($this->getAemetNivo($lat, $lon) ?? $this->getMFBRA($lat, $lon));
            if (!$bpa && $this->isPyrenees($lat, $lon)) {
                $report['avalanche_unavailable'] = true;
            }

            if ($bpa) {
                $report['avalanche_level']  = $bpa['level'];
                $report['avalanche_source'] = $bpa['source'];
                $report['avalanche_massif'] = $bpa['massif'];
                if (!empty($bpa['narrative'])) {
                    $report['narrative_context'] .= $bpa['narrative'] . "\n";
                }
                $report['sources_used'][] = $bpa['source'];
            }
        }

        // ── 3. Datos de estaciones nivométricas (AEMET) ───────────────────
        if ($isMountainZone) {
            $snow = $this->fetchSnowStationData($zone);
            if ($snow) {
                $report['snow_depth_cm']    = $snow['depth_cm'];
                $report['snow_station_name']= $snow['station'];
                $report['snow_station_alt'] = $snow['alt'];
                $report['sources_used'][]   = 'AEMET_NIVO';
            }
        }

        // ── 4. Estimación de cota de nieve ────────────────────────────────
        // Prioridad: ① AEMET estación medida → ② Open-Meteo multi-punto (N/S) → ③ isocero proxy
        //
        // ① es la fuente más fiable (dato real de campo).
        // ② muestrea 10 puntos (5N + 5S, cada ~1 km) y detecta la transición
        //    sin-nieve → con-nieve — mucho más preciso que un solo punto del modelo.
        // ③ isocero - 300 m es solo orientativo para nieve fresca del día,
        //    NO refleja el manto acumulado de invierno que puede estar 1.000 m más abajo.

        if ($report['snow_depth_cm'] !== null && $report['snow_depth_cm'] > 2) {
            // ① Estación AEMET — manto real; la cota puede bajar hasta alt - 150 m en umbrías
            $report['cota_nieve_est'] = max(0, (int)$report['snow_station_alt'] - 150);

        } elseif (!empty($snowLine)
               && ($snowLine['line_n_m'] !== null || $snowLine['line_s_m'] !== null)) {
            // ② Open-Meteo multi-punto: tenemos transición detectada en al menos una orientación
            $lineN = $snowLine['line_n_m'];   // umbría (aguanta más tiempo)
            $lineS = $snowLine['line_s_m'];   // solana (desaparece antes)
            $conf  = $snowLine['confidence'] ?? 'media';

            // Cota de referencia = umbría/norte (valor más conservador para seguridad)
            if ($lineN !== null) {
                $report['cota_nieve_est'] = $lineN;
            } else {
                // Solo solana detectada: estimamos norte ≈ sur - 200 m
                $report['cota_nieve_est'] = max(0, $lineS - 200);
            }

            // Grosor máximo detectado como referencia de profundidad
            $maxDepth = max($snowLine['depth_max_n_cm'] ?? 0, $snowLine['depth_max_s_cm'] ?? 0);
            if ($maxDepth > 0 && $report['snow_depth_cm'] === null) {
                $report['snow_depth_cm']     = $maxDepth;
                $report['snow_station_name'] = "Open-Meteo multi-punto ({$conf})";
            }

            // Añadir horquilla N/S a la narrativa si tenemos ambas orientaciones
            if ($lineN !== null && $lineS !== null) {
                $report['narrative_context'] .=
                    "Cota de nieve (modelo): ~{$lineN} m umbría · ~{$lineS} m solana "
                    . "(confianza {$conf}).\n";
            } elseif ($lineN !== null) {
                $report['narrative_context'] .=
                    "Cota de nieve estimada (umbría): ~{$lineN} m (confianza {$conf}).\n";
            } else {
                $report['narrative_context'] .=
                    "Cota de nieve estimada (solana): ~{$lineS} m (confianza {$conf}).\n";
            }
            $report['sources_used'][] = 'OpenMeteo_multipoint';

        } elseif ($isoZero !== null && $isoZero > 0) {
            // ③ Fallback: isocero - 300 m. Solo orientativo para nieve fresca del día.
            $report['cota_nieve_est'] = max(0, $isoZero - 300);
        }

        if ($report['cota_nieve_est'] !== null) {
            $report['snow_at_route'] = $cotaCritica >= ($report['cota_nieve_est'] - 100);
        }

        // ── 5. Riesgo de barro por precipitación reciente ─────────────────
        $report['terrain_mud_risk'] = ($precip3dMm >= 15.0);

        // ── 6. Índice de condiciones global ───────────────────────────────
        $report['conditions_index'] = $this->calcConditionsIndex($report, $activity);

        // ── 7. Narrativa compacta para Sonnet ─────────────────────────────
        $report['narrative_context'] = trim($this->buildNarrative($report, $cotaCritica));

        return $report;
    }

    // =========================================================================
    // ALERTAS
    // =========================================================================

    private function getAemetAlerts(float $lat, float $lon): ?array {
        // La caché es por punto (grado completo de resolución) para que distintas
        // provincias no compartan la misma entrada aunque el GeoJSON sea el mismo
        $cacheKey = 'aemet_alerts_' . round($lat, 1) . '_' . round($lon, 1);
        $cached   = $this->getCache($cacheKey, self::CACHE_ALERT);
        if ($cached !== null) return $cached;

        if (empty(AEMET_API_KEY)) return null;

        try {
            // Paso 1: metadatos → URL de datos
            $meta = $this->aemetRequest(AEMET_BASE_URL . '/avisos_cap/ultimo/');
            if (empty($meta['datos'])) return null;

            // Paso 2: descargar el GeoJSON completo de alertas
            $body = $this->httpGet($meta['datos']);
            if (!$body) return null;

            $data = json_decode($body, true);
            if (!isset($data['features'])) return null;

            // Filtrar solo las features que afectan a las coordenadas del usuario
            $relevant = $this->filterAlertsByLocation($data['features'], $lat, $lon);
            if (empty($relevant)) {
                // Sin alertas para esta ubicación → cachear null explícitamente como array vacío
                $this->setCache($cacheKey, [], self::CACHE_ALERT);
                return null;
            }

            $result = $this->parseAemetAlerts($relevant);
            $this->setCache($cacheKey, $result ?? [], self::CACHE_ALERT);
            return $result;

        } catch (\Exception $e) {
            $this->logger->warning('AEMET alerts error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Filtra las features GeoJSON de CAP que geográficamente contienen el punto dado.
     * Usa ray-casting para polígonos y bounding-box como fallback si no hay geometría.
     */
    private function filterAlertsByLocation(array $features, float $lat, float $lon): array {
        $relevant = [];
        foreach ($features as $feature) {
            $geometry = $feature['geometry'] ?? null;
            if (!$geometry) {
                // Sin geometría → incluir (no podemos descartar)
                $relevant[] = $feature;
                continue;
            }
            $type = $geometry['type'] ?? '';
            $coords = $geometry['coordinates'] ?? [];

            $inside = false;
            if ($type === 'Polygon') {
                $inside = $this->pointInPolygonRings($lat, $lon, $coords);
            } elseif ($type === 'MultiPolygon') {
                foreach ($coords as $polygon) {
                    if ($this->pointInPolygonRings($lat, $lon, $polygon)) {
                        $inside = true;
                        break;
                    }
                }
            } elseif ($type === 'GeometryCollection') {
                // Recursivo
                $sub = array_map(fn($g) => ['type' => 'Feature', 'geometry' => $g, 'properties' => $feature['properties']], $geometry['geometries'] ?? []);
                if (!empty($this->filterAlertsByLocation($sub, $lat, $lon))) $inside = true;
            }

            if ($inside) $relevant[] = $feature;
        }
        return $relevant;
    }

    /**
     * Ray-casting point-in-polygon para un polígono GeoJSON (exterior ring + huecos).
     * GeoJSON usa [longitude, latitude], así que invertimos al comparar.
     */
    private function pointInPolygonRings(float $lat, float $lon, array $rings): bool {
        if (empty($rings)) return false;
        $exterior = $rings[0]; // el primer anillo es el exterior
        if (!$this->rayIntersects($lat, $lon, $exterior)) return false;
        // Comprobar que no cae en ningún hueco (anillos interiores)
        for ($i = 1; $i < count($rings); $i++) {
            if ($this->rayIntersects($lat, $lon, $rings[$i])) return false;
        }
        return true;
    }

    private function rayIntersects(float $lat, float $lon, array $ring): bool {
        $inside = false;
        $n      = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            // GeoJSON: [lon, lat]
            $xi = (float)$ring[$i][0]; $yi = (float)$ring[$i][1];
            $xj = (float)$ring[$j][0]; $yj = (float)$ring[$j][1];
            if ((($yi > $lat) !== ($yj > $lat))
                && ($lon < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }
        return $inside;
    }

    private function parseAemetAlerts(array $features): ?array {
        $levelOrder = ['green' => 0, 'yellow' => 1, 'orange' => 2, 'red' => 3];
        $colorMap   = [
            // CAP severity → color
            'extreme'  => 'red',
            'severe'   => 'orange',
            'moderate' => 'yellow',
            'minor'    => 'green',
            // A veces viene en español
            'extremo'  => 'red',
            'grave'    => 'orange',
            'moderado' => 'yellow',
            'menor'    => 'green',
        ];
        $maxLevel = 'green';
        $types    = [];
        $areas    = [];

        $typeMap = [
            'viento'    => ['wind', 'viento'],
            'lluvia'    => ['rain', 'lluvia', 'precip', 'inundac'],
            'nieve'     => ['snow', 'nieve', 'ice', 'hielo'],
            'tormenta'  => ['thunder', 'tormenta', 'storm', 'granizo'],
            'aludes'    => ['avalanche', 'alud'],
            'niebla'    => ['fog', 'niebla'],
            'calor'     => ['heat', 'calor', 'temperatura alta'],
            'frio'      => ['cold', 'frío', 'frio', 'temperatura baja'],
            'costero'   => ['coastal', 'costa', 'maritim', 'oleaje'],
        ];

        foreach ($features as $feature) {
            $props    = $feature['properties'] ?? [];
            $severity = strtolower($props['severity'] ?? $props['severidad'] ?? 'minor');
            // El event en CAP de AEMET puede venir en 'event', 'headline' o 'description'
            $event    = strtolower(
                ($props['event']       ?? '') . ' ' .
                ($props['headline']    ?? '') . ' ' .
                ($props['description'] ?? '')
            );

            $level = $colorMap[$severity] ?? 'green';
            if ($levelOrder[$level] > $levelOrder[$maxLevel]) {
                $maxLevel = $level;
            }

            foreach ($typeMap as $label => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($event, $kw) && !in_array($label, $types, true)) {
                        $types[] = $label;
                    }
                }
            }
            if (!empty($props['areaDesc'])) $areas[] = $props['areaDesc'];
        }

        if ($maxLevel === 'green' && empty($types)) return null;

        return [
            'level'  => $maxLevel,
            'types'  => $types,
            'areas'  => array_unique($areas),
            'source' => 'AEMET',
        ];
    }

    private function getMFAlerts(float $lat, float $lon): ?array {
        if (empty(METEOFRANCE_CLIENT_ID)) return null;

        $cacheKey = 'mf_alerts_' . round($lat, 1) . '_' . round($lon, 1);
        $cached   = $this->getCache($cacheKey, self::CACHE_ALERT);
        if ($cached !== null) return $cached;

        try {
            $token = $this->getMFToken();
            if (!$token) return null;

            $url  = METEOFRANCE_BASE_URL . '/DPVigilance/v1/vigimeteo/departements/forecast';
            $body = $this->httpGet($url, ['Authorization' => "Bearer $token"]);
            if (!$body) return null;

            $data   = json_decode($body, true);
            $result = $this->parseMFAlerts($data ?? []);
            if ($result) $this->setCache($cacheKey, $result, self::CACHE_ALERT);
            return $result;

        } catch (\Exception $e) {
            $this->logger->warning('MF alerts error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function parseMFAlerts(array $data): ?array {
        $levelOrder = ['green' => 0, 'yellow' => 1, 'orange' => 2, 'red' => 3];
        $colorMap   = [1 => 'green', 2 => 'yellow', 3 => 'orange', 4 => 'red'];
        $maxLevel   = 'green';
        $types      = [];

        // Estructura Météo-France vigilance: product → periods → timelaps
        $periods = $data['product']['periods'] ?? [];
        foreach ($periods as $period) {
            foreach ($period['timelaps'] ?? [] as $item) {
                $color  = $colorMap[$item['color_id'] ?? 1] ?? 'green';
                if ($levelOrder[$color] > $levelOrder[$maxLevel]) {
                    $maxLevel = $color;
                }
                $phenom = strtolower($item['phenomenon_id_libelle'] ?? '');
                $typeMap = [
                    'viento'    => ['vent', 'wind'],
                    'lluvia'    => ['pluie', 'rain', 'inond'],
                    'nieve'     => ['neige', 'snow'],
                    'tormenta'  => ['orage', 'thunder'],
                    'avalancha' => ['avalanche'],
                    'niebla'    => ['brouillard', 'fog'],
                ];
                foreach ($typeMap as $label => $keywords) {
                    foreach ($keywords as $kw) {
                        if (str_contains($phenom, $kw) && !in_array($label, $types, true)) {
                            $types[] = $label;
                        }
                    }
                }
            }
        }

        if ($maxLevel === 'green' && empty($types)) return null;
        return ['level' => $maxLevel, 'types' => $types, 'source' => 'MeteoFrance'];
    }

    // =========================================================================
    // BOLETINES DE ALUDES
    // =========================================================================

    /** Pirineo (vertiente sur y norte) — zona con boletín de peligro de aludes. */
    private function isPyrenees(float $lat, float $lon): bool {
        return $lat >= 42.2 && $lat <= 43.2 && $lon >= -2.0 && $lon <= 3.3;
    }

    /**
     * Boletín de peligro de aludes de AEMET (predicción nivológica).
     * Área 1: Pirineo navarro y aragonés · Área 0: Pirineo catalán.
     */
    private function getAemetNivo(float $lat, float $lon): ?array {
        if (!$this->isPyrenees($lat, $lon) || empty(AEMET_API_KEY)) return null;
        $area  = $lon < 0.75 ? '1' : '0';
        $label = $area === '1' ? 'Pirineo navarro y aragonés' : 'Pirineo catalán';

        $cacheKey = 'aemet_nivo_' . $area;
        $cached   = $this->getCache($cacheKey, self::CACHE_BPA);
        if ($cached !== null) return $cached;

        try {
            $meta = $this->aemetRequest(AEMET_BASE_URL . "/prediccion/especifica/nivologica/{$area}");
            if (empty($meta['datos'])) return null;
            $body = $this->httpGet($meta['datos']);
            if (!$body) return null;
            // AEMET sirve el texto en ISO-8859-15
            if (!mb_check_encoding($body, 'UTF-8')) {
                $body = mb_convert_encoding($body, 'UTF-8', 'ISO-8859-15');
            }
            $data   = json_decode($body, true);
            $result = $this->parseAemetBPA(is_array($data) ? $data : [$body], $label);
            if ($result) {
                $result['source'] = 'AEMET (boletín nivológico)';
                $this->setCache($cacheKey, $result, self::CACHE_BPA);
            }
            return $result;
        } catch (\Exception $e) {
            $this->logger->warning('AEMET nivológica error', ['area' => $area, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function getAemetBPA(float $lat, float $lon): ?array {
        $zoneData = $this->coordsToAemetZone($lat, $lon);
        if (!$zoneData) return null;

        $cacheKey = 'aemet_bpa_' . $zoneData['id'];
        $cached   = $this->getCache($cacheKey, self::CACHE_BPA);
        if ($cached !== null) return $cached;

        if (empty(AEMET_API_KEY)) return null;

        try {
            // AEMET: predicción específica de montaña para día 0 (hoy)
            $url  = AEMET_BASE_URL . "/prediccion/especifica/montaña/{$zoneData['id']}/dia/0/";
            $meta = $this->aemetRequest($url);
            if (empty($meta['datos'])) return null;

            $body = $this->httpGet($meta['datos']);
            if (!$body) return null;

            $data   = json_decode($body, true);
            $result = $this->parseAemetBPA($data ?? [], $zoneData['label']);
            if ($result) $this->setCache($cacheKey, $result, self::CACHE_BPA);
            return $result;

        } catch (\Exception $e) {
            $this->logger->warning('AEMET BPA error', ['zone' => $zoneData['id'], 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function parseAemetBPA(array $data, string $zoneName): ?array {
        // La predicción de montaña AEMET viene como array de períodos/días.
        // Concatenamos todos los campos de texto para buscar el nivel de peligro.
        $texto = $this->extractAllText($data);
        if (empty($texto)) return null;

        // ── Extracción del nivel de peligro (terminología EAWS estándar en español) ──
        // Nivel 1: Débil / Limited / Low
        // Nivel 2: Limitado / Considerable (en algunos países) / Moderate
        // Nivel 3: Notable / Considerable
        // Nivel 4: Fuerte / High
        // Nivel 5: Muy fuerte / Very High / Extreme
        $level = null;

        // Primero intentar con la palabra clave de nivel numérico
        if (preg_match('/nivel\s+de\s+peligro[^0-9]*([1-5])/iu', $texto, $m)) {
            $level = (int)$m[1];
        } elseif (preg_match('/peligro[^0-9]{0,20}([1-5])\b/iu', $texto, $m)) {
            $level = (int)$m[1];
        } elseif (preg_match('/nivel\s+([1-5])\b/iu', $texto, $m)) {
            $level = (int)$m[1];
        } elseif (preg_match('/\b([1-5])\s*\/\s*5/u', $texto, $m)) {
            $level = (int)$m[1];
        }

        // Fallback con terminología textual EAWS en español
        if (!$level) {
            $keywords = [
                5 => ['muy fuerte', 'muy alto', 'extremo', 'extrema'],
                4 => ['fuerte', 'alto', 'high'],
                3 => ['notable', 'considerable', 'marcado'],
                2 => ['limitado', 'moderado', 'moderate'],
                1 => ['débil', 'debil', 'bajo', 'escaso', 'low'],
            ];
            foreach ($keywords as $lvl => $terms) {
                foreach ($terms as $t) {
                    // Buscar cerca de "peligro" o "riesgo" o "aludes"
                    if (preg_match('/(?:peligro|riesgo|aludes)[^.]{0,60}' . preg_quote($t, '/') . '/iu', $texto)
                     || preg_match('/' . preg_quote($t, '/') . '[^.]{0,60}(?:peligro|riesgo|aludes)/iu', $texto)) {
                        $level = $lvl;
                        break 2;
                    }
                }
            }
        }

        // Si no encontramos nivel pero hay texto sobre aludes, devolver nivel null
        // (el texto narrativo puede ser útil de todas formas)
        $hasAvalancheContent = preg_match('/aludes?|nieve|deslizamiento/iu', $texto);
        if (!$level && !$hasAvalancheContent) return null;

        $narrative = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($texto))), 0, 600);

        return [
            'level'     => $level,
            'massif'    => $zoneName,
            'narrative' => $narrative ?: null,
            'source'    => 'AEMET',
        ];
    }

    /**
     * Extrae recursivamente todos los valores de cadena de un array anidado.
     * Útil para el JSON de predicción de montaña de AEMET (estructura variable).
     */
    private function extractAllText(mixed $data, int $depth = 0): string {
        if ($depth > 5) return '';
        if (is_string($data) && mb_strlen($data) > 15) return ' ' . $data;
        if (!is_array($data)) return '';
        $out = '';
        foreach ($data as $val) {
            $out .= $this->extractAllText($val, $depth + 1);
        }
        return $out;
    }

    private function getMFBRA(float $lat, float $lon): ?array {
        if (empty(METEOFRANCE_CLIENT_ID)) return null;

        $massifId = $this->coordsToMFMassif($lon);
        if (!$massifId) return null;

        $cacheKey = "mf_bra_{$massifId}";
        $cached   = $this->getCache($cacheKey, self::CACHE_BPA);
        if ($cached !== null) return $cached;

        try {
            $token = $this->getMFToken();
            if (!$token) return null;

            // Météo-France BRA en JSON (si el endpoint lo soporta; fallback a XML)
            $url  = METEOFRANCE_BASE_URL . "/DPBRA/v1/massif/BRA?id-massif={$massifId}&format=json";
            $body = $this->httpGet($url, ['Authorization' => "Bearer $token"]);

            // Fallback a XML si JSON no disponible
            if (!$body) {
                $url  = METEOFRANCE_BASE_URL . "/DPBRA/v1/massif/BRA?id-massif={$massifId}&format=xml";
                $body = $this->httpGet($url, ['Authorization' => "Bearer $token"]);
                if ($body) {
                    $result = $this->parseMFBRAXml($body, $massifId);
                } else {
                    return null;
                }
            } else {
                $data   = json_decode($body, true);
                $result = $this->parseMFBRA($data ?? [], $massifId);
            }

            if ($result) $this->setCache($cacheKey, $result, self::CACHE_BPA);
            return $result;

        } catch (\Exception $e) {
            $this->logger->warning('MF BRA error', ['massif' => $massifId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function parseMFBRA(array $data, int $massifId): ?array {
        $name  = self::MF_MASSIFS[$massifId]['name'] ?? "Macizo $massifId";
        // Intentar varios paths posibles en el JSON de Météo-France
        $level = $data['bra']['danger']['level']
              ?? $data['massif_bra']['danger_level']
              ?? $data['dangerLevel']
              ?? null;
        $narr  = $data['bra']['conditions']['description']
              ?? $data['massif_bra']['situation']
              ?? $data['snowpackStructure']
              ?? null;

        if (!$level) return null;
        return [
            'level'     => (int)$level,
            'massif'    => $name,
            'narrative' => $narr ? mb_substr(strip_tags((string)$narr), 0, 500) : null,
            'source'    => 'MeteoFrance',
        ];
    }

    private function parseMFBRAXml(string $xml, int $massifId): ?array {
        $name = self::MF_MASSIFS[$massifId]['name'] ?? "Macizo $massifId";
        try {
            $dom = new \SimpleXMLElement($xml);
            // CAAML v5/v6: buscar DangerRating > mainValue
            $dangerNodes = $dom->xpath('//*[local-name()="DangerRating"]/*[local-name()="mainValue"]');
            $level = !empty($dangerNodes) ? (int)(string)$dangerNodes[0] : null;

            $commentNodes = $dom->xpath('//*[local-name()="comment"]');
            $narrative    = !empty($commentNodes) ? mb_substr((string)$commentNodes[0], 0, 500) : null;

            if (!$level) return null;
            return ['level' => $level, 'massif' => $name, 'narrative' => $narrative, 'source' => 'MeteoFrance'];

        } catch (\Exception) {
            return null;
        }
    }



    // =========================================================================
    // ESTACIONES DE NIEVE
    // =========================================================================

    private function fetchSnowStationData(string $zone): ?array {
        $stations = self::AEMET_SNOW_STATIONS[$zone] ?? null;
        if (!$stations || empty(AEMET_API_KEY)) return null;

        foreach ($stations as $station) {
            $result = $this->getAemetStationObs($station);
            if ($result) return $result;
        }
        return null;
    }

    private function getAemetStationObs(array $station): ?array {
        $cacheKey = "aemet_nivo_{$station['id']}";
        $cached   = $this->getCache($cacheKey, self::CACHE_SNOW);
        if ($cached !== null) return $cached;

        try {
            $url  = AEMET_BASE_URL . "/observacion/convencional/datos/estacion/{$station['id']}/";
            $meta = $this->aemetRequest($url);
            if (empty($meta['datos'])) return null;

            $body = $this->httpGet($meta['datos']);
            if (!$body) return null;

            $obs = json_decode($body, true);
            if (!is_array($obs)) return null;

            // El último registro de observación de la estación
            $latest  = end($obs);
            if (!is_array($latest)) return null;

            // Campos posibles para grosor de nieve en observaciones AEMET
            // AEMET usa 'nieve' (cm) en estaciones convencionales con sensor de nieve
            $snowCm = $latest['nieve'] ?? $latest['neve'] ?? $latest['snow'] ?? $latest['hs'] ?? null;
            if ($snowCm === null || $snowCm === '' || $snowCm === -999 || $snowCm === '-999') return null;

            $result = [
                'depth_cm' => round((float)$snowCm, 1),
                'station'  => "{$station['name']} ({$station['alt']} m)",
                'alt'      => (int)$station['alt'],
            ];
            $this->setCache($cacheKey, $result, self::CACHE_SNOW);
            return $result;

        } catch (\Exception $e) {
            $this->logger->warning('AEMET nivo error', ['id' => $station['id'], 'error' => $e->getMessage()]);
            return null;
        }
    }

    // =========================================================================
    // OAUTH2 MÉTÉO-FRANCE
    // =========================================================================

    private function getMFToken(): ?string {
        $cached = $this->getCache('mf_oauth_token', self::CACHE_TOKEN);
        if ($cached !== null) return $cached;

        try {
            $ch = curl_init(METEOFRANCE_TOKEN_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'grant_type'    => 'client_credentials',
                    'client_id'     => METEOFRANCE_CLIENT_ID,
                    'client_secret' => METEOFRANCE_CLIENT_SECRET,
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200 || !$resp) return null;

            $data  = json_decode($resp, true);
            $token = $data['access_token'] ?? null;
            if ($token) $this->setCache('mf_oauth_token', $token, self::CACHE_TOKEN);
            return $token;

        } catch (\Exception $e) {
            $this->logger->warning('MF OAuth2 error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // =========================================================================
    // ÍNDICE DE CONDICIONES Y NARRATIVA
    // =========================================================================

    /**
     * Calcula el índice global: BUENAS / ACEPTABLE / MALAS.
     * Usado por AiCore para ajustar dificultad y comunicar a Sonnet.
     */
    public function calcConditionsIndex(array $report, string $activity): string {
        $alertOrder = ['green' => 0, 'yellow' => 1, 'orange' => 2, 'red' => 3];
        $alertLevel = $alertOrder[$report['alert_level'] ?? 'green'] ?? 0;
        $avalLevel  = $report['avalanche_level'] ?? 0;
        $mud        = $report['terrain_mud_risk'] ?? false;
        $snow       = $report['snow_at_route']    ?? false;
        $isSkiSnow  = in_array($activity, ['ski_touring', 'snowshoeing'], true);

        // MALAS
        if ($alertLevel >= 3)                             return 'MALAS'; // alerta naranja/roja
        if ($avalLevel  >= 4)                             return 'MALAS'; // peligro alto/muy alto
        if ($avalLevel  >= 3 && $isSkiSnow)               return 'MALAS'; // nivel 3 es crítico para ski
        if ($snow && $activity === 'hiking')               return 'MALAS'; // nieve en ruta de senderismo

        // ACEPTABLE
        if ($alertLevel >= 2)                             return 'ACEPTABLE'; // alerta amarilla
        if ($avalLevel  >= 3)                             return 'ACEPTABLE';
        if ($mud)                                         return 'ACEPTABLE';
        if ($snow && $isSkiSnow && $avalLevel >= 2)       return 'ACEPTABLE';

        return 'BUENAS';
    }

    /**
     * Genera texto de contexto compacto para incluir en el prompt de Sonnet.
     */
    private function buildNarrative(array $report, int $cotaCritica): string {
        $parts = [];

        // Alertas AEMET oficiales con área afectada
        if ($report['alert_level'] && $report['alert_level'] !== 'green') {
            $labels = ['yellow' => 'amarilla ⚠️', 'orange' => 'naranja 🟠', 'red' => 'roja 🔴'];
            $level  = $labels[$report['alert_level']] ?? $report['alert_level'];
            $types  = implode(', ', $report['alert_types']);
            $areas  = !empty($report['alert_areas'])
                    ? ' (zona: ' . implode(', ', array_slice($report['alert_areas'], 0, 2)) . ')'
                    : '';
            $parts[] = "Alerta AEMET {$level}" . ($types ? " por {$types}" : '') . "{$areas}.";
        }

        // Peligro de aludes (solo de fuente oficial: AEMET o Météo-France)
        if ($report['avalanche_level'] !== null) {
            $src    = $report['avalanche_source'] ?? 'boletín oficial';
            $massif = $report['avalanche_massif'] ? " ({$report['avalanche_massif']})" : '';
            $emoji  = match(true) {
                $report['avalanche_level'] >= 4 => '🔴',
                $report['avalanche_level'] === 3  => '🟠',
                $report['avalanche_level'] === 2  => '🟡',
                default                          => '🟢',
            };
            $parts[] = "Peligro de aludes {$emoji} nivel {$report['avalanche_level']}/5 según {$src}{$massif}.";
        } elseif (!empty($report['avalanche_unavailable'])) {
            $parts[] = "BOLETÍN DE ALUDES NO DISPONIBLE: no se ha podido consultar el boletín oficial de peligro de aludes. "
                     . "No estimes ningún nivel de peligro: dilo y remite al boletín de AEMET (Pirineo navarro y aragonés / catalán), "
                     . "del ICGC en Cataluña o de Météo-France (BRA) en la vertiente francesa.";
        }

        // Nieve medida (estación AEMET o modelo Open-Meteo)
        if ($report['snow_depth_cm'] !== null && $report['snow_station_name']) {
            $isModel = str_contains($report['snow_station_name'] ?? '', 'Open-Meteo');
            $srcLabel = $isModel ? 'estimación Open-Meteo' : 'dato AEMET';
            $parts[] = "{$report['snow_depth_cm']} cm de manto en {$report['snow_station_name']} ({$srcLabel}).";
        }

        // Cota de nieve estimada vs cota crítica de la ruta
        if ($report['cota_nieve_est'] !== null) {
            $isIsozeroBased = ($report['snow_depth_cm'] === null);
            $disclaimer = $isIsozeroBased
                ? ' (estimado por isocero; puede haber manto residual más bajo en primavera)'
                : '';
            $txt = "Cota de nieve estimada: ~{$report['cota_nieve_est']} m{$disclaimer}";
            if ($cotaCritica > 0) {
                $txt .= "; cota crítica de la ruta: ~{$cotaCritica} m";
                $txt .= ($report['snow_at_route'] ? ' → nieve probable en ruta.' : ' → ruta probablemente sin nieve.');
            }
            $parts[] = $txt . ".";
        }

        // Riesgo de barro por precipitación reciente
        if ($report['terrain_mud_risk']) {
            $mm      = round($report['precip_3d_mm'], 1);
            $parts[] = "Riesgo de barro: {$mm} mm acumulados en 3 días.";
        }

        return implode(' ', $parts);
    }

    // =========================================================================
    // MAPEO COORDENADAS → ZONAS
    // =========================================================================

    private function coordsToAemetZone(float $lat, float $lon): ?array {
        foreach (self::AEMET_MOUNTAIN_ZONES as $zone) {
            if ($lat >= $zone['lat_min'] && $lat <= $zone['lat_max']
             && $lon >= $zone['lon_min'] && $lon <= $zone['lon_max']) {
                return $zone;
            }
        }
        return null;
    }

    private function coordsToMFMassif(float $lon): ?int {
        foreach (self::MF_MASSIFS as $id => $massif) {
            if ($lon >= $massif['lon_min'] && $lon <= $massif['lon_max']) {
                return $id;
            }
        }
        return null;
    }


    // =========================================================================
    // HELPERS
    // =========================================================================

    private function isSnowSeason(): bool {
        return in_array((int)date('n'), [11, 12, 1, 2, 3, 4, 5], true);
    }

    /** Petición AEMET con dos pasos: meta → URL datos. */
    private function aemetRequest(string $url): array {
        $sep  = str_contains($url, '?') ? '&' : '?';
        $body = $this->httpGet($url . $sep . 'api_key=' . urlencode(AEMET_API_KEY));
        if (!$body) return [];
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    private function httpGet(string $url, array $headers = []): ?string {
        $host = parse_url($url, PHP_URL_HOST) ?: 'unknown';
        if ($this->getCache('down_' . $host, self::HOST_DOWN_TTL) !== null) {
            return null; // fuente caída o sin permiso hace poco: no esperar otra vez
        }
        $ch         = curl_init($url);
        $httpHeaders = ['User-Agent: KahirAI/1.0 (mountain-assistant)'];
        foreach ($headers as $k => $v) {
            $httpHeaders[] = "$k: $v";
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $httpHeaders,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || in_array($code, [401, 403, 429, 500, 502, 503, 504], true)) {
            // No se registra la URL completa: puede llevar la api_key en la query
            $this->logger->warning('Fuente externa no disponible', ['host' => $host, 'http' => $code, 'err' => $err ?: null]);
            $this->setCache('down_' . $host, ['code' => $code], self::HOST_DOWN_TTL);
            return null;
        }
        return ($code === 200 && $body) ? $body : null;
    }

    private function getCache(string $key, int $ttl): mixed {
        $file = sys_get_temp_dir() . '/kahir_mountain_' . md5($key);
        if (!file_exists($file)) return null;
        $raw = json_decode(file_get_contents($file), true);
        if (!$raw || (time() - ($raw['t'] ?? 0)) > $ttl) {
            @unlink($file);
            return null;
        }
        return $raw['d'];
    }

    private function setCache(string $key, mixed $data, int $ttl): void {
        $file = sys_get_temp_dir() . '/kahir_mountain_' . md5($key);
        file_put_contents($file, json_encode(['t' => time(), 'd' => $data, 'ttl' => $ttl]), LOCK_EX);
    }
}
