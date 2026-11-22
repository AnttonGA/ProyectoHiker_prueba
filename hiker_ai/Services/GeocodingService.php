<?php
/**
 * Servicio de geocodificación para Kahir.AI
 * 
 * Maneja la conversión entre nombres de lugares y coordenadas
 * usando OpenStreetMap Nominatim, con enfoque en los Pirineos.
 */

namespace KahirAI\Services;

class GeocodingService {
    /**
     * @var LoggerService
     */
    private LoggerService $logger;

    /**
     * @var int Tiempo de caché para resultados (24 horas)
     */
    private const CACHE_DURATION = 86400;

    /**
     * @var array Cobertura geográfica: España + sur de Francia + Islas Canarias y Baleares
     */
    private const COVERAGE_BOUNDS = [
        'min_lat' => 27.6,   // Islas Canarias (El Hierro)
        'max_lat' => 43.9,   // Norte de España / Pirineos / sur de Francia
        'min_lon' => -18.2,  // Islas Canarias (La Palma)
        'max_lon' =>  4.5,   // Frontera franco-española / sur de Francia
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new LoggerService('geocoding');
    }

    /**
     * Obtiene coordenadas para una ubicación
     *
     * @param string $location Nombre del lugar
     * @param bool $strictBounds Si debe restringirse a los límites de los Pirineos
     * @return array|null Coordenadas [lat, lon] o null si no se encuentra
     */
    public function getCoordinates(string $location, bool $strictBounds = true): ?array {
        $cacheKey = "geocode_v2_" . md5($location . $strictBounds);
        
        // Verificar caché
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $url = $this->buildNominatimUrl('search', [
                'q' => $location,
                'format' => 'jsonv2',
                'limit' => 1,
                'viewbox' => $this->getPyreneesViewbox(),
                'bounded' => $strictBounds ? 1 : 0
            ]);

            $response = $this->makeRequest($url);
            
            if (empty($response)) {
                return null;
            }

            $result = $response[0];
            $coordinates = [
                'lat' => (float)$result['lat'],
                'lon' => (float)$result['lon'],
                // Caja [sur, norte, oeste, este] y tipo de lugar: permiten distinguir un pueblo
                // (búsqueda por radio) de una región (búsqueda dentro de su caja)
                'bbox' => isset($result['boundingbox']) && count($result['boundingbox']) === 4
                    ? array_map('floatval', $result['boundingbox']) : null,
                'kind' => $result['addresstype'] ?? ($result['type'] ?? null),
            ];

            if ($strictBounds && !$this->isInCoveredArea($coordinates['lat'], $coordinates['lon'])) {
                return null;
            }

            // Guardar en caché
            $this->saveToCache($cacheKey, $coordinates);

            return $coordinates;

        } catch (\Exception $e) {
            $this->logger->error('Error en geocodificación', [
                'location' => $location,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Obtiene el nombre de lugar para unas coordenadas
     *
     * @param float $latitude Latitud
     * @param float $longitude Longitud
     * @return array|null Información del lugar o null si no se encuentra
     */
    public function reverseGeocode(float $latitude, float $longitude): ?array {
        $cacheKey = "reverse_geocode_{$latitude}_{$longitude}";
        
        // Verificar caché
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            if (!$this->isInCoveredArea($latitude, $longitude)) {
                return null;
            }

            $url = $this->buildNominatimUrl('reverse', [
                'lat' => $latitude,
                'lon' => $longitude,
                'format' => 'json',
                'zoom' => 14,
                'addressdetails' => 1
            ]);

            $response = $this->makeRequest($url);
            
            if (!$response) {
                return null;
            }

            $locationInfo = [
                'name' => $response['display_name'],
                'type' => $response['type'],
                'address' => $response['address'],
                'importance' => $response['importance'] ?? null,
                'coordinates' => [
                    'lat' => (float)$latitude,
                    'lon' => (float)$longitude
                ]
            ];

            // Guardar en caché
            $this->saveToCache($cacheKey, $locationInfo);

            return $locationInfo;

        } catch (\Exception $e) {
            $this->logger->error('Error en geocodificación inversa', [
                'lat' => $latitude,
                'lon' => $longitude,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Busca lugares cercanos a unas coordenadas
     *
     * @param float $latitude Latitud
     * @param float $longitude Longitud
     * @param float $radius Radio en kilómetros
     * @param array $types Tipos de lugares a buscar
     * @return array Lista de lugares cercanos
     */
    public function findNearbyPlaces(
        float $latitude, 
        float $longitude, 
        float $radius = 5.0, 
        array $types = ['peak', 'shelter', 'parking']
    ): array {
        try {
            $url = $this->buildNominatimUrl('search', [
                'format' => 'json',
                'lat' => $latitude,
                'lon' => $longitude,
                'radius' => $radius * 1000, // convertir a metros
                'limit' => 50
            ]);

            $response = $this->makeRequest($url);
            
            return array_filter($response, function($place) use ($types) {
                return in_array($place['type'], $types);
            });

        } catch (\Exception $e) {
            $this->logger->error('Error buscando lugares cercanos', [
                'lat' => $latitude,
                'lon' => $longitude,
                'radius' => $radius,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Calcula tiempos de conducción desde un origen a varios destinos en paralelo
     * usando la API pública de OSRM (sin coste, sin API key).
     *
     * @param float $originLat   Latitud del punto de partida
     * @param float $originLon   Longitud del punto de partida
     * @param array  $destinations Array de ['lat'=>float|null, 'lon'=>float|null]
     * @return array  Mismo índice que $destinations; cada elemento es
     *                ['minutes'=>int, 'km'=>float] o null si falla/no hay coords.
     */
    public function getDrivingTimeBatch(float $originLat, float $originLon, array $destinations): array {
        $mh      = curl_multi_init();
        $handles = [];
        $results = array_fill(0, count($destinations), null);

        foreach ($destinations as $i => $dest) {
            $dLat = (float)($dest['lat'] ?? 0);
            $dLon = (float)($dest['lon'] ?? 0);
            if (!$dLat || !$dLon) {
                continue;
            }

            $url = sprintf(
                'https://router.project-osrm.org/route/v1/driving/%s,%s;%s,%s?overview=false',
                number_format($originLon, 6, '.', ''),
                number_format($originLat, 6, '.', ''),
                number_format($dLon, 6, '.', ''),
                number_format($dLat, 6, '.', '')
            );

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 4,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_USERAGENT      => 'KahirAI/1.0 (mountain-assistant)',
            ]);
            $handles[$i] = $ch;
            curl_multi_add_handle($mh, $ch);
        }

        // Ejecutar todas las peticiones en paralelo
        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 0.5);
            }
        } while ($running > 0 && $status === CURLM_OK);

        // Recoger resultados
        foreach ($handles as $i => $ch) {
            $body  = curl_multi_getcontent($ch);
            $error = curl_error($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($error || !$body) {
                continue;
            }
            $data = json_decode($body, true);
            if (empty($data['routes'][0])) {
                continue;
            }
            $r = $data['routes'][0];
            $results[$i] = [
                'minutes' => (int)round($r['duration'] / 60),
                'km'      => round($r['distance'] / 1000, 1),
            ];
        }

        curl_multi_close($mh);
        return $results;
    }

    /**
     * Verifica si unas coordenadas están dentro del área de cobertura:
     * España (península + Canarias + Baleares) y sur de Francia.
     */
    public function isInCoveredArea(float $latitude, float $longitude): bool {
        return $latitude  >= self::COVERAGE_BOUNDS['min_lat'] &&
               $latitude  <= self::COVERAGE_BOUNDS['max_lat'] &&
               $longitude >= self::COVERAGE_BOUNDS['min_lon'] &&
               $longitude <= self::COVERAGE_BOUNDS['max_lon'];
    }

    /**
     * @deprecated Usar isInCoveredArea(). Mantenido por compatibilidad.
     */
    public function isInPyrenees(float $latitude, float $longitude): bool {
        return $this->isInCoveredArea($latitude, $longitude);
    }

    /**
     * Calcula la distancia entre dos puntos
     *
     * @param float $lat1 Latitud del punto 1
     * @param float $lon1 Longitud del punto 1
     * @param float $lat2 Latitud del punto 2
     * @param float $lon2 Longitud del punto 2
     * @return float Distancia en kilómetros
     */
    public function calculateDistance(
        float $lat1, 
        float $lon1, 
        float $lat2, 
        float $lon2
    ): float {
        $earthRadius = 6371; // Radio de la Tierra en km

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Construye una URL para Nominatim
     *
     * @param string $endpoint Endpoint de la API
     * @param array $params Parámetros adicionales
     * @return string
     */
    private function buildNominatimUrl(string $endpoint, array $params): string {
        $baseParams = [
            'email' => 'anttontton@gmail.com',
            'accept-language' => 'es'
        ];

        $params = array_merge($baseParams, $params);
        return OPENSTREETMAP_API_URL . "/$endpoint?" . http_build_query($params);
    }

    /**
     * Realiza una petición HTTP
     *
     * @param string $url URL a consultar
     * @return array
     * @throws \Exception
     */
    private function makeRequest(string $url): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,   // Nominatim: max 5s en contexto de chat
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT      => 'KahirAI/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Error en la petición HTTP: $error");
        }

        $data = json_decode($response, true);
        if (!$data && json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Error al decodificar la respuesta JSON');
        }

        return $data;
    }

    /**
     * Obtiene el viewbox de cobertura (España + sur de Francia + Islas) para Nominatim.
     * Formato: min_lon, min_lat, max_lon, max_lat
     */
    private function getPyreneesViewbox(): string {
        return implode(',', [
            self::COVERAGE_BOUNDS['min_lon'],
            self::COVERAGE_BOUNDS['min_lat'],
            self::COVERAGE_BOUNDS['max_lon'],
            self::COVERAGE_BOUNDS['max_lat'],
        ]);
    }

    /**
     * Obtiene datos de la caché
     *
     * @param string $key Clave de caché
     * @return mixed
     */
    private function getFromCache(string $key) {
        // Implementar con sistema de caché real
        return null;
    }

    /**
     * Guarda datos en la caché
     *
     * @param string $key Clave de caché
     * @param mixed $data Datos a guardar
     * @return bool
     */
    private function saveToCache(string $key, $data): bool {
        // Implementar con sistema de caché real
        return true;
    }
}