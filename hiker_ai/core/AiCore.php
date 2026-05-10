<?php
namespace KahirAI\Core;

use KahirAI\Services\{AuthService, LoggerService, WeatherService, GeocodingService, UserPreferenceService, MountainConditionsService};
use KahirAI\Exceptions\AIException;

/**
 * AiCore — Orquestador principal de Kahir.AI
 *
 * BLOQUE 1 implementado:
 *  - Haiku como extractor único (extractQueryData)
 *  - Contexto de sesión persistente en BD (columna context JSON en chat_sessions)
 *  - Herencia de zona entre mensajes (last_zone)
 *  - Gestión de awaiting_clarification sin ChatManager
 *
 * SQL de migración necesaria (ejecutar una vez en la BD):
 *   ALTER TABLE chat_sessions ADD COLUMN context JSON NULL DEFAULT NULL;
 */
class AICore {

    private AuthService               $auth;
    private LoggerService             $logger;
    private WeatherService            $weather;
    private GeocodingService          $geocoding;
    private UserPreferenceService     $preferences;
    private OpenAIIntegration         $claude;
    private RouteRecommender          $recommender;
    private MountainConditionsService $mountainConditions;
    private \mysqli                   $conn;

    /**
     * Punto de referencia geográfico por zona.
     * Se usa cuando el usuario menciona una zona genérica sin lugar concreto.
     * Las 4 primeras corresponden a zonas reales en la BD; el resto son puntos
     * de referencia para meteorología y búsqueda por proximidad.
     */
    private const ZONE_REFERENCE_POINTS = [
        // ── Zonas de la BD ───────────────────────────────────────────────────
        'Euskadi'          => ['name' => 'San Sebastián',  'lat' => 43.3183, 'lon' =>  -1.9812],
        'Western_Pyrenees' => ['name' => 'Pamplona',       'lat' => 42.8169, 'lon' =>  -1.6432],
        'Aragon'           => ['name' => 'Jaca',            'lat' => 42.5700, 'lon' =>  -0.5500],
        'Central_Pyrenees' => ['name' => 'Sort',            'lat' => 42.4079, 'lon' =>   1.1328],
        // ── Cantábrico y norte ───────────────────────────────────────────────
        'Cantabria'        => ['name' => 'Santander',       'lat' => 43.4623, 'lon' =>  -3.8099],
        'Galicia'          => ['name' => 'Santiago',        'lat' => 42.8782, 'lon' =>  -8.5448],
        // ── Sistema Central ──────────────────────────────────────────────────
        'Sistema_Central'  => ['name' => 'Segovia',         'lat' => 40.9429, 'lon' =>  -4.1088],
        // ── Sistema Ibérico ──────────────────────────────────────────────────
        'Sistema_Iberico'  => ['name' => 'Teruel',          'lat' => 40.3456, 'lon' =>  -1.1065],
        // ── Sur ─────────────────────────────────────────────────────────────
        'Sierra_Nevada'    => ['name' => 'Granada',         'lat' => 37.1773, 'lon' =>  -3.5986],
        'Andalucia'        => ['name' => 'Málaga',           'lat' => 36.7213, 'lon' =>  -4.4214],
        // ── Levante ──────────────────────────────────────────────────────────
        'Levante'          => ['name' => 'Valencia',        'lat' => 39.4699, 'lon' =>  -0.3763],
        // ── Islas ────────────────────────────────────────────────────────────
        'Canarias'         => ['name' => 'Las Palmas',      'lat' => 28.1235, 'lon' => -15.4366],
        'Baleares'         => ['name' => 'Palma',           'lat' => 39.5696, 'lon' =>   2.6502],
        // ── Sur de Francia ───────────────────────────────────────────────────
        'Francia_Sur'      => ['name' => 'Toulouse',        'lat' => 43.6047, 'lon' =>   1.4442],
    ];

    /**
     * Topónimos concretos de montaña → zona de BD + consulta optimizada para Nominatim.
     * Se usan como capa de detección PRE-Haiku: si el usuario menciona alguno, se
     * geocodifica directamente y el displayName mantiene el término exacto del usuario.
     * Las claves deben ir en minúsculas sin acentos para la comparación normalizada.
     */
    private const SPECIFIC_LOCATIONS = [
        // ── Pirineo Aragonés ────────────────────────────────────────────────
        'valle de tena'   => ['zone' => 'Aragon',           'geocode' => 'Valle de Tena, Huesca, España'],
        'sallent'         => ['zone' => 'Aragon',           'geocode' => 'Sallent de Gállego, Huesca'],
        'formigal'        => ['zone' => 'Aragon',           'geocode' => 'Formigal, Huesca, España'],
        'panticosa'       => ['zone' => 'Aragon',           'geocode' => 'Panticosa, Huesca, España'],
        'candanchu'       => ['zone' => 'Aragon',           'geocode' => 'Candanchú, Huesca, España'],
        'candanchu'       => ['zone' => 'Aragon',           'geocode' => 'Candanchú, Huesca, España'],
        'astun'           => ['zone' => 'Aragon',           'geocode' => 'Astún, Huesca, España'],
        'benasque'        => ['zone' => 'Aragon',           'geocode' => 'Benasque, Huesca, España'],
        'aneto'           => ['zone' => 'Aragon',           'geocode' => 'Aneto, Huesca, España'],
        'ordesa'          => ['zone' => 'Aragon',           'geocode' => 'Ordesa, Huesca, España'],
        'bielsa'          => ['zone' => 'Aragon',           'geocode' => 'Bielsa, Huesca, España'],
        'broto'           => ['zone' => 'Aragon',           'geocode' => 'Broto, Huesca, España'],
        'torla'           => ['zone' => 'Aragon',           'geocode' => 'Torla, Huesca, España'],
        'hecho'           => ['zone' => 'Aragon',           'geocode' => 'Hecho, Huesca, España'],
        'anso'            => ['zone' => 'Aragon',           'geocode' => 'Ansó, Huesca, España'],
        'zuriza'          => ['zone' => 'Aragon',           'geocode' => 'Zuriza, Huesca, España'],
        'pineta'          => ['zone' => 'Aragon',           'geocode' => 'Valle de Pineta, Huesca'],
        'plan de estan'   => ['zone' => 'Aragon',           'geocode' => 'Plan de Estáin, Huesca'],
        // ── Pirineo Occidental / Navarra ────────────────────────────────────
        'pamplona'        => ['zone' => 'Western_Pyrenees', 'geocode' => 'Pamplona, Navarra, España'],
        'iruña'           => ['zone' => 'Western_Pyrenees', 'geocode' => 'Pamplona, Navarra, España'],
        'tudela'          => ['zone' => 'Western_Pyrenees', 'geocode' => 'Tudela, Navarra, España'],
        'estella'         => ['zone' => 'Western_Pyrenees', 'geocode' => 'Estella, Navarra, España'],
        'roncesvalles'    => ['zone' => 'Western_Pyrenees', 'geocode' => 'Roncesvalles, Navarra'],
        'isaba'           => ['zone' => 'Western_Pyrenees', 'geocode' => 'Isaba, Navarra'],
        'ochagavia'       => ['zone' => 'Western_Pyrenees', 'geocode' => 'Ochagavía, Navarra'],
        'roncal'          => ['zone' => 'Western_Pyrenees', 'geocode' => 'Valle del Roncal, Navarra'],
        'belagua'         => ['zone' => 'Western_Pyrenees', 'geocode' => 'Belagua, Navarra'],
        // ── Pirineo Central / Cataluña ──────────────────────────────────────
        'baqueira'        => ['zone' => 'Central_Pyrenees', 'geocode' => 'Baqueira-Beret, Lleida'],
        'baqueira beret'  => ['zone' => 'Central_Pyrenees', 'geocode' => 'Baqueira-Beret, Lleida'],
        'sort'            => ['zone' => 'Central_Pyrenees', 'geocode' => 'Sort, Lleida'],
        'espot'           => ['zone' => 'Central_Pyrenees', 'geocode' => 'Espot, Lleida'],
        'la molina'       => ['zone' => 'Central_Pyrenees', 'geocode' => 'La Molina, Girona'],
        'nuria'           => ['zone' => 'Central_Pyrenees', 'geocode' => 'Vall de Núria, Girona'],
        'aiguestortes'    => ['zone' => 'Central_Pyrenees', 'geocode' => 'Aigüestortes, Lleida'],
        'val d aran'      => ['zone' => 'Central_Pyrenees', 'geocode' => 'Val d\'Aran, Lleida'],
        'bohi taull'      => ['zone' => 'Central_Pyrenees', 'geocode' => 'Boí Taüll, Lleida'],
        'andorra'         => ['zone' => 'Central_Pyrenees', 'geocode' => 'Andorra la Vella, Andorra'],
        // ── Cantabria / Picos de Europa ─────────────────────────────────────
        'picos de europa' => ['zone' => 'Cantabria',        'geocode' => 'Picos de Europa, España'],
        'fuente de'       => ['zone' => 'Cantabria',        'geocode' => 'Fuente Dé, Cantabria'],
        'potes'           => ['zone' => 'Cantabria',        'geocode' => 'Potes, Cantabria'],
        'cangas de onis'  => ['zone' => 'Cantabria',        'geocode' => 'Cangas de Onís, Asturias'],
        'covadonga'       => ['zone' => 'Cantabria',        'geocode' => 'Covadonga, Asturias'],
        'somiedo'         => ['zone' => 'Cantabria',        'geocode' => 'Somiedo, Asturias'],
        // ── Sistema Central ─────────────────────────────────────────────────
        'navacerrada'     => ['zone' => 'Sistema_Central',  'geocode' => 'Navacerrada, Madrid'],
        'guadarrama'      => ['zone' => 'Sistema_Central',  'geocode' => 'Guadarrama, Madrid'],
        'penalara'        => ['zone' => 'Sistema_Central',  'geocode' => 'Peñalara, Madrid'],
        'la pedriza'      => ['zone' => 'Sistema_Central',  'geocode' => 'La Pedriza, Madrid'],
        'gredos'          => ['zone' => 'Sistema_Central',  'geocode' => 'Gredos, Ávila'],
        'cotos'           => ['zone' => 'Sistema_Central',  'geocode' => 'Puerto de Cotos, Madrid'],
        // ── Sierra Nevada ───────────────────────────────────────────────────
        'mulhacen'        => ['zone' => 'Sierra_Nevada',    'geocode' => 'Mulhacén, Granada'],
        'veleta'          => ['zone' => 'Sierra_Nevada',    'geocode' => 'Veleta, Granada'],
        'solynieve'       => ['zone' => 'Sierra_Nevada',    'geocode' => 'Sierra Nevada, Granada'],
        // ── Sistema Ibérico ─────────────────────────────────────────────────
        'moncayo'         => ['zone' => 'Sistema_Iberico',  'geocode' => 'Moncayo, Zaragoza'],
        'gudar'           => ['zone' => 'Sistema_Iberico',  'geocode' => 'Gúdar, Teruel'],
        'javalambre'      => ['zone' => 'Sistema_Iberico',  'geocode' => 'Javalambre, Teruel'],
        // ── Canarias ───────────────────────────────────────────────────────
        'teide'           => ['zone' => 'Canarias',         'geocode' => 'Teide, Tenerife'],
        'caldera de taburiente' => ['zone' => 'Canarias',   'geocode' => 'Caldera de Taburiente, La Palma'],
        // ── Baleares ───────────────────────────────────────────────────────
        'tramuntana'      => ['zone' => 'Baleares',         'geocode' => 'Serra de Tramuntana, Mallorca'],
        // ── Levante ─────────────────────────────────────────────────────────
        'penyagolosa'     => ['zone' => 'Levante',          'geocode' => 'Penyagolosa, Castellón'],
        'montgo'          => ['zone' => 'Levante',          'geocode' => 'Montgó, Alicante'],
        // ── Galicia ─────────────────────────────────────────────────────────
        'o cebreiro'      => ['zone' => 'Galicia',          'geocode' => 'O Cebreiro, Lugo'],
        'ancares'         => ['zone' => 'Galicia',          'geocode' => 'Os Ancares, Lugo'],
        'courel'          => ['zone' => 'Galicia',          'geocode' => 'Serra do Courel, Lugo'],
    ];

    /** Zonas adyacentes para el fallback de pocas rutas (Caso A). */
    private const ZONE_ADJACENCY = [
        'Western_Pyrenees' => 'Aragon',
        'Central_Pyrenees' => 'Aragon',
        'Aragon'           => 'Western_Pyrenees',
        'Euskadi'          => 'Western_Pyrenees',
        'Cantabria'        => 'Euskadi',
        'Galicia'          => 'Cantabria',
        'Sistema_Central'  => 'Sistema_Iberico',
        'Sistema_Iberico'  => 'Aragon',
        'Sierra_Nevada'    => 'Andalucia',
        'Andalucia'        => 'Sierra_Nevada',
        'Levante'          => 'Sistema_Iberico',
    ];

    public function __construct(\mysqli $conn) {
        $this->conn                = $conn;
        $this->auth                = new AuthService();
        $this->logger              = new LoggerService('ai_core');
        $this->weather             = new WeatherService();
        $this->geocoding           = new GeocodingService();
        $this->preferences         = new UserPreferenceService($conn);
        $this->claude              = new OpenAIIntegration();
        $this->recommender         = new RouteRecommender($conn);
        $this->mountainConditions  = new MountainConditionsService();
    }

    // =========================================================================
    // PUNTO DE ENTRADA PRINCIPAL
    // =========================================================================

    /**
     * Procesa un mensaje del usuario.
     *
     * @param string $query      Mensaje del usuario
     * @param string $userId     Username o ID numérico del usuario
     * @param array  $history    Historial de chat [{role, content, fecha}]
     * @param int    $sessionId  ID de la sesión de chat en BD (0 = sin sesión)
     */
    public function processQuery(
        string $query,
        string $userId,
        array  $history   = [],
        int    $sessionId = 0
    ): array {
        $start = microtime(true);

        // Variables de auditoría (se rellenan a lo largo del flujo)
        $auditDetectionMethod = 'haiku';
        $auditFollowupSubtype = null;
        $auditZoneInherited   = false;
        $auditRouteCount      = 0;
        $auditError           = null;

        try {
            // 1. Cargar perfil y contexto de sesión
            $userProfile = $this->preferences->getUserPreferences($userId);
            $sessionCtx  = $sessionId > 0 ? $this->loadSessionContext($sessionId) : [];

            // Snapshot del contexto de entrada para el log
            $ctxIn = [
                'last_zone'    => $sessionCtx['last_zone']    ?? null,
                'last_intent'  => $sessionCtx['last_intent']  ?? null,
                'active_count' => count($sessionCtx['active_routes'] ?? []),
                'awaiting'     => $sessionCtx['awaiting_clarification'] ?? null,
            ];

            // 2. Pre-Haiku: detectar route_followup sin coste de tokens cuando hay active_routes
            $followupOverride = $this->detectFollowupIntent($query, $sessionCtx);
            if ($followupOverride !== null) {
                $auditDetectionMethod = 'pre-haiku';
                $auditFollowupSubtype = $followupOverride['followup_subtype'] ?? null;
                $extracted = array_merge([
                    'intent'               => 'route_followup',
                    'zone'                 => null,
                    'activity'             => null,
                    'difficulty'           => null,
                    'timeframe'            => null,
                    'days_ahead'           => 0,
                    'weather_check_needed' => false,
                ], $followupOverride);
            } else {
                // Haiku extrae intent + parámetros estructurados
                $extracted = $this->claude->extractQueryData($query);
            }

            // 3. Gestionar awaiting_clarification pendiente
            if (!empty($sessionCtx['awaiting_clarification'])) {
                $extracted = $this->resolvePendingClarification($extracted, $sessionCtx);
            }

            $intent = $extracted['intent'];

            // 4. Herencia de zona: si intent geográfico y zone null → usar last_zone
            if (
                empty($extracted['zone']) &&
                in_array($intent, ['route_request', 'weather_query', 'safety_query'], true) &&
                !empty($sessionCtx['last_zone'])
            ) {
                $extracted['zone']           = $sessionCtx['last_zone'];
                $extracted['inherited_zone'] = true;
                $auditZoneInherited          = true;
            }

            // 5. Despachar al handler correcto
            $response = match ($intent) {
                'route_request'               => $this->handleRouteRequest($query, $userId, $userProfile, $history, $extracted, $sessionCtx),
                'weather_query'               => $this->handleWeatherRequest($query, $userProfile, $history, $extracted, $sessionCtx),
                'safety_query'                => $this->handleSafetyQuery($query, $userProfile, $history, $extracted, $sessionCtx),
                'personalized_recommendation' => $this->handlePersonalizedRecommendation($query, $userId, $userProfile, $history, $extracted, $sessionCtx),
                'onboarding_response'         => $this->handleOnboardingResponse($query, $userId, $userProfile, $history, $sessionCtx),
                'route_followup'              => $this->handleRouteFollowup($query, $userProfile, $history, $extracted, $sessionCtx),
                default                       => $this->handleGeneralQuery($query, $userId, $userProfile, $history),
            };

            $auditRouteCount = count($response['routes'] ?? []);

            // 6. Guardar interacción y actualizar contexto de sesión
            $this->saveInteraction($userId, $query, $response, $intent);

            if ($sessionId > 0) {
                $updatedCtx = $this->updateSessionContext($sessionCtx, $extracted, $response);
                $this->saveSessionContext($sessionId, $updatedCtx);
            }

            $processingMs = round((microtime(true) - $start) * 1000);

            if (defined('KAHIR_AUDIT_LOG') && KAHIR_AUDIT_LOG) {
                $this->writeAuditLog([
                    'query'            => $query,
                    'user_id'          => $userId,
                    'session_id'       => $sessionId,
                    'detection'        => $auditDetectionMethod,
                    'intent'           => $intent,
                    'zone'             => $extracted['zone']             ?? null,
                    'zone_inherited'   => $auditZoneInherited,
                    'activity'         => $extracted['activity']         ?? null,
                    'difficulty'       => $extracted['difficulty']       ?? null,
                    'days_ahead'       => $extracted['days_ahead']       ?? 0,
                    'weather_needed'   => $extracted['weather_check_needed'] ?? false,
                    'followup_subtype' => $auditFollowupSubtype,
                    'response_type'    => $response['type']              ?? 'unknown',
                    'route_count'      => $auditRouteCount,
                    'processing_ms'    => $processingMs,
                    'ctx_in'           => $ctxIn,
                    'error'            => null,
                ]);
            }

            return array_merge($response, [
                'intent'        => $intent,
                'processing_ms' => $processingMs,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error en processQuery', [
                'userId' => $userId,
                'query'  => $query,
                'error'  => $e->getMessage(),
            ]);
            if (defined('KAHIR_AUDIT_LOG') && KAHIR_AUDIT_LOG) {
                $this->writeAuditLog([
                    'query'          => $query,
                    'user_id'        => $userId,
                    'session_id'     => $sessionId,
                    'detection'      => $auditDetectionMethod,
                    'intent'         => 'ERROR',
                    'response_type'  => 'error',
                    'route_count'    => 0,
                    'processing_ms'  => round((microtime(true) - $start) * 1000),
                    'error'          => $e->getMessage(),
                ]);
            }
            throw new AIException('Error al procesar la consulta', 'PROCESSING_ERROR');
        }
    }

    // =========================================================================
    // CONTEXTO DE SESIÓN (BD)
    // =========================================================================

    /**
     * Carga el contexto de sesión desde la BD.
     * Devuelve array vacío si no hay contexto o falla la lectura.
     */
    public function loadSessionContext(int $sessionId): array {
        try {
            $stmt = $this->conn->prepare(
                "SELECT context FROM chat_sessions WHERE id = ? LIMIT 1"
            );
            $stmt->bind_param("i", $sessionId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row || empty($row['context'])) {
                return [];
            }
            $decoded = json_decode($row['context'], true);
            return is_array($decoded) ? $decoded : [];

        } catch (\Exception $e) {
            $this->logger->warning('loadSessionContext error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Guarda el contexto de sesión en la BD.
     */
    public function saveSessionContext(int $sessionId, array $context): void {
        try {
            $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmt = $this->conn->prepare(
                "UPDATE chat_sessions SET context = ? WHERE id = ?"
            );
            $stmt->bind_param("si", $json, $sessionId);
            $stmt->execute();
            $stmt->close();
        } catch (\Exception $e) {
            $this->logger->warning('saveSessionContext error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Actualiza el contexto de sesión según el resultado del mensaje actual.
     * Aplica las reglas de ciclo de vida definidas en el documento de diseño.
     */
    private function updateSessionContext(array $ctx, array $extracted, array $response): array {
        $intent = $extracted['intent'];

        // Actualizar zona solo si es nueva (no heredada)
        if (!empty($extracted['zone']) && empty($extracted['inherited_zone'])) {
            $ctx['last_zone'] = $extracted['zone'];
            // last_coords se actualizará si se geocodificó
        }
        if (!empty($extracted['resolved_coords'])) {
            $ctx['last_coords'] = $extracted['resolved_coords'];
        }

        // Actualizar actividad si viene explícita
        if (!empty($extracted['activity'])) {
            $ctx['last_activity'] = $extracted['activity'];
        }

        // Actualizar intent
        $ctx['last_intent'] = $intent;

        // active_routes + last_routes + last_candidate_ids
        if ($intent === 'route_request') {
            // Nueva búsqueda: reemplazar todo
            if (!empty($response['routes'])) {
                $ctx['active_routes'] = array_values(array_filter(
                    array_map(fn($r) => (int)($r['id'] ?? 0), $response['routes'])
                ));
                // Guardar objetos completos de las rutas mostradas (para describe/compare en followup)
                $ctx['last_routes'] = $response['routes'];
                // Guardar IDs del pool completo pre-scoring (para filtros consistentes en followup)
                if (!empty($extracted['last_candidate_ids'])) {
                    $ctx['last_candidate_ids'] = $extracted['last_candidate_ids'];
                }
            }
        } elseif ($intent === 'route_followup') {
            // filter: actualizar active_routes con las rutas filtradas; preservar last_candidate_ids
            if (($extracted['followup_subtype'] ?? '') === 'filter' && !empty($response['routes'])) {
                $ctx['active_routes'] = array_values(array_filter(
                    array_map(fn($r) => (int)($r['id'] ?? 0), $response['routes'])
                ));
                $ctx['last_routes'] = $response['routes'];
                // last_candidate_ids se preserva (misma pool de candidatos)
            }
            // describe/conjunto: no tocar nada — el contexto de rutas se mantiene intacto
        } else {
            // Cualquier otro intent: borrar contexto de rutas
            $ctx['active_routes']      = [];
            $ctx['last_routes']        = [];
            $ctx['last_candidate_ids'] = [];
        }

        // awaiting_clarification: se establece desde la respuesta, se limpia cuando se resuelve
        if (!empty($response['awaiting_clarification'])) {
            $ctx['awaiting_clarification'] = $response['awaiting_clarification'];
            // Guardar parámetros pendientes para recuperarlos al resolver
            $ctx['pending_intent']   = $intent;
            $ctx['pending_activity'] = $extracted['activity']   ?? null;
            $ctx['pending_diff']     = $extracted['difficulty'] ?? null;
        } else {
            $ctx['awaiting_clarification'] = null;
            $ctx['pending_intent']         = null;
            $ctx['pending_activity']       = null;
            $ctx['pending_diff']           = null;
        }

        return $ctx;
    }

    /**
     * Resuelve una clarificación pendiente.
     * Cuando el usuario responde a una pregunta anterior, combina los parámetros
     * guardados en el contexto con lo que Haiku acaba de extraer del mensaje.
     */
    private function resolvePendingClarification(array $extracted, array $ctx): array {
        $awaiting = $ctx['awaiting_clarification'];

        // Caso especial: onboarding (parse de perfil)
        if ($awaiting === 'onboarding') {
            $extracted['intent'] = 'onboarding_response';
            return $extracted;
        }

        // Caso general: el usuario responde con la zona que faltaba
        if ($awaiting === 'zone' && !empty($extracted['zone'])) {
            // Restaurar el intent y parámetros previos, añadir la zona respondida
            $extracted['intent']   = $ctx['pending_intent']   ?? $extracted['intent'];
            $extracted['activity'] = $extracted['activity']   ?? $ctx['pending_activity'];
            $extracted['difficulty'] = $extracted['difficulty'] ?? $ctx['pending_diff'];
            // Marcar la clarificación como resuelta
            $extracted['clarification_resolved'] = true;
        }

        return $extracted;
    }

    // =========================================================================
    // HANDLERS DE INTENT
    // =========================================================================

    private function handleRouteRequest(
        string $query,
        string $userId,
        array  $prefs,
        array  $history,
        array  $extracted,
        array  $ctx
    ): array {
        // ── Construir parámetros desde Haiku + perfil ────────────────────────
        $rawZone = $extracted['zone'] ?? null;

        // Detección de topónimo concreto: resuelve "Valle de Tena" → geocode preciso
        $specificLoc = $this->findSpecificLocationInQuery($query);
        $zoneName    = $specificLoc ? $specificLoc['geocode'] : $rawZone;

        // Si no hay zona → pedir clarificación
        if (empty($zoneName)) {
            return $this->makeClarificationResponse('zone',
                '¿En qué zona o lugar buscas las rutas? (p. ej. Candanchú, Sierra Nevada, Picos de Europa, Teide…)'
            );
        }

        // Verificación rápida de zonas claramente fuera de cobertura.
        // Nominatim puede devolver un pequeño municipio español homónimo (p. ej. "Los Alpes"
        // en Álava) para rangos montañosos internacionales; la blocklist lo corta antes.
        if ($this->isZoneOutOfCoverage($rawZone ?? $zoneName)) {
            $text = $this->askClaude(
                "El usuario pregunta: \"$query\"\n\n" .
                "La zona mencionada está fuera de la cobertura de Kahir. " .
                "Kahir cubre España (incluyendo Canarias y Baleares) y el sur de Francia. " .
                "Explícalo amablemente y ofrece buscar en una zona de España o sur de Francia.",
                $history, $prefs, 'general'
            );
            return ['type' => 'general', 'content' => $text];
        }

        // Actividad y dificultad: Haiku primero, luego perfil del usuario
        $activity = $extracted['activity'] ?? $this->getFirstPreferredActivity($prefs);
        $difficulty = $extracted['difficulty'] ?? ($prefs['experience_level'] ?? 'intermediate');

        // ── Geocodificación de la zona ────────────────────────────────────────
        $locationData = $this->resolveZoneName($zoneName);
        // Si viene de topónimo concreto, preservar el nombre del usuario
        if ($specificLoc && !empty($locationData['location'])) {
            $locationData['location'] = $specificLoc['display_name'];
        }

        // Caso C: zona fuera de cobertura (fuera de España / sur de Francia / Islas)
        if (!empty($locationData['out_of_bounds'])) {
            $text = $this->askClaude(
                "El usuario pregunta: \"$query\"\n\n" .
                "La zona '{$zoneName}' está fuera de nuestra cobertura. " .
                "Kahir cubre España (incluyendo Canarias y Baleares) y sur de Francia. " .
                "Explícalo amablemente y ofrece buscar en una zona de España o sur de Francia.",
                $history, $prefs, 'general'
            );
            return ['type' => 'general', 'content' => $text];
        }

        // ¿Es un lugar concreto geocodificado (Candanchú, Astún…) o una zona abstracta (Aragon)?
        $isSpecificLocation = $locationData['is_specific_location'] ?? false;

        $params = [
            'activity'    => $activity,
            'difficulty'  => $difficulty,
            'location'    => $locationData['location'] ?? $zoneName,
            'zone'        => $locationData['zone']     ?? null,
            'coordinates' => $locationData['coordinates'] ?? null,
            'timeframe'   => $extracted['timeframe']   ?? 'today',
            'days_ahead'  => $extracted['days_ahead']  ?? 0,
            // Radio inicial de 80 km solo para lugares concretos geocodificados.
            // Para zonas abstractas ("rutas en Aragon") se usa el filtro de zona clásico.
            'proximity_km'=> $isSpecificLocation ? 80.0 : null,
        ];

        // Guardar coords resueltas para el contexto
        if (!empty($params['coordinates'])) {
            $extracted['resolved_coords'] = $params['coordinates'];
        }

        // Si no se pudieron resolver coordenadas → usar referencia de zona
        if (empty($params['coordinates']) && !empty($params['zone'])) {
            $ref = self::ZONE_REFERENCE_POINTS[$params['zone']] ?? null;
            if ($ref) {
                $params['coordinates'] = ['lat' => $ref['lat'], 'lon' => $ref['lon']];
            }
        }

        // ── Comprobar meteorología ────────────────────────────────────────────
        // Fetch weather when days_ahead >= 1 (mañana o más adelante) o cuando
        // Haiku detectó explícitamente weather_check_needed.
        $weatherWarning = null;
        $daysAhead = (int)($params['days_ahead'] ?? 0);
        $needsWeather = ($extracted['weather_check_needed'] ?? false) || $daysAhead >= 1;

        if (!empty($params['coordinates']) && $needsWeather) {
            try {
                if ($daysAhead <= 1) {
                    $weather = $this->weather->getCurrentWeather(
                        (float)$params['coordinates']['lat'],
                        (float)$params['coordinates']['lon']
                    );
                } else {
                    $weather = $this->weather->getForecast(
                        (float)$params['coordinates']['lat'],
                        (float)$params['coordinates']['lon'],
                        max(2, $daysAhead + 1)
                    );
                }
                if (!empty($weather) && !$this->weather->isSafeForActivity($weather, $activity ?? 'hiking')) {
                    $weatherWarning = $this->weather->getWeatherRecommendations($weather, $activity ?? 'hiking');
                }
                $params['weather'] = $weather;
            } catch (\Exception) { /* continuar sin tiempo */ }
        }

        // ── Condiciones de montaña (AEMET + Météo-France + EAWS) ─────────────
        // Se ejecuta ANTES de buscar rutas para poder ajustar la dificultad
        $conditionsReport = null;
        if (!empty($params['coordinates']) && !empty($params['zone'])) {
            try {
                $cLat = (float)$params['coordinates']['lat'];
                $cLon = (float)$params['coordinates']['lon'];

                // Precipitación acumulada 3 días → riesgo de barro
                $precip3d = $this->weather->getPrecip3d($cLat, $cLon);

                // Isocero del tiempo ya descargado (getMountainConditions lo incluye si se llamó)
                $isoZero = $params['weather']['iso_zero'] ?? null;

                // Snow line multi-punto (N+S, 5 offsets cada uno) → estimación real de cota
                $snowLine = $this->weather->getSnowLine($cLat, $cLon);

                // Cota crítica de referencia por zona (m)
                $zoneCotaRef = [
                    'Aragon'           => 1600, 'Western_Pyrenees' => 1500,
                    'Central_Pyrenees' => 1700, 'Euskadi'          => 1200,
                    'Cantabria'        => 1400, 'Sistema_Central'  => 1500,
                    'Sierra_Nevada'    => 2000, 'Galicia'          => 800,
                    'Andalucia'        => 1000, 'Sistema_Iberico'  => 1200,
                    'Levante'          => 900,  'Canarias'         => 1400,
                    'Baleares'         => 900,  'Francia_Sur'      => 1600,
                ];
                $cotaCritica = $zoneCotaRef[$params['zone']] ?? 1200;

                $conditionsReport = $this->mountainConditions->buildConditionsReport(
                    $cLat, $cLon,
                    $params['zone'],
                    $activity ?? 'hiking',
                    $cotaCritica,
                    $precip3d,
                    $isoZero,
                    $snowLine
                );

                // Ajustar dificultad antes de buscar rutas si las condiciones lo exigen
                if (!empty($conditionsReport['conditions_index']) && $conditionsReport['conditions_index'] !== 'BUENAS') {
                    $params['difficulty'] = $this->adjustDifficultyForConditions(
                        $params['difficulty'],
                        $conditionsReport['conditions_index']
                    );
                }
            } catch (\Exception) { /* continuar sin datos de condiciones */ }
        }

        // ── Buscar rutas en BD ────────────────────────────────────────────────
        $results = $this->recommender->getRecommendations($params, $prefs, MAX_ROUTE_SUGGESTIONS);
        // Capturar IDs del pool pre-scoring para route_followup filtros (se guardarán en sesión)
        $extracted['last_candidate_ids'] = $this->recommender->getLastCandidateIds();

        // ── Caso A: pocas rutas ───────────────────────────────────────────────
        $expandedSearch  = false;
        $expandedToAdj   = false;
        $expandedRadius  = false;
        $originalCount   = count($results);

        // Paso 1: relajar dificultad si hay pocos resultados
        if ($originalCount < 3 && !empty($params['difficulty'])) {
            $relaxedDiff    = array_merge($params, ['difficulty' => null]);
            $relaxedResults = $this->recommender->getRecommendations($relaxedDiff, $prefs, MAX_ROUTE_SUGGESTIONS);
            if (count($relaxedResults) > $originalCount) {
                $results        = $relaxedResults;
                $expandedSearch = true;
            }
        }

        // Paso 2a (modo proximidad): ampliar radio de 80 km a 150 km
        if (count($results) < 3 && $isSpecificLocation && !empty($params['proximity_km'])) {
            $wideParams  = array_merge($params, ['proximity_km' => 150.0, 'difficulty' => null]);
            $wideResults = $this->recommender->getRecommendations($wideParams, $prefs, MAX_ROUTE_SUGGESTIONS);
            if (count($wideResults) > count($results)) {
                $results        = $wideResults;
                $expandedRadius = true;
                $expandedSearch = true;
            }
        }

        // Paso 2b (modo zona): añadir zona adyacente como antes
        if (count($results) < 3 && !$isSpecificLocation && !empty($params['zone'])) {
            $adjZone = self::ZONE_ADJACENCY[$params['zone']] ?? null;
            if ($adjZone) {
                $adjParams  = ['zone' => $adjZone, 'activity' => $activity];
                $adjResults = $this->recommender->getFallbackRecommendations($adjParams);
                if (!empty($adjResults)) {
                    $needed        = MAX_ROUTE_SUGGESTIONS - count($results);
                    $results       = array_merge($results, array_slice($adjResults, 0, $needed));
                    $expandedToAdj = true;
                    $expandedSearch= true;
                }
            }
        }

        if (empty($results)) {
            $text = $this->askClaude(
                "El usuario pregunta: \"$query\"\n\n" .
                "No se han encontrado rutas con esos criterios. Explica esto amablemente y " .
                "sugiere ampliar la búsqueda (zona, dificultad o actividad). " .
                "NO inventes rutas ni lugares.",
                $history, $prefs, 'route_request'
            );
            return ['type' => 'no_routes', 'content' => $text, 'weather_warning' => $weatherWarning];
        }

        // ── Calcular tiempos en coche (OSRM en paralelo) ──────────────────────
        if (!empty($params['coordinates'])) {
            $destinations = array_map(function ($r) {
                $route = $r['route'];
                // Soporte para distintos nombres de columna en la tabla rutas
                $lat = $route['startLat'] ?? $route['start_lat'] ?? $route['latitud_inicio'] ?? null;
                $lon = $route['startLng'] ?? $route['start_lng'] ?? $route['start_lon'] ?? $route['longitud_inicio'] ?? null;
                return ['lat' => $lat, 'lon' => $lon];
            }, $results);

            $driveTimes = $this->geocoding->getDrivingTimeBatch(
                (float)$params['coordinates']['lat'],
                (float)$params['coordinates']['lon'],
                $destinations
            );

            foreach ($results as $i => &$result) {
                if (!empty($driveTimes[$i])) {
                    $result['route']['drive_time_minutes'] = $driveTimes[$i]['minutes'];
                    $result['route']['drive_km']           = $driveTimes[$i]['km'];
                }
            }
            unset($result);

            // En modo proximidad: filtrar rutas con tiempo en coche > 90 min
            // y ordenar las que quedan de más cercana a más lejana.
            if ($isSpecificLocation) {
                $withTime    = array_filter($results, fn($r) => isset($r['route']['drive_time_minutes']));
                $halfOrMore  = count($withTime) >= max(1, (int)(count($results) / 2));

                if ($halfOrMore) {
                    // Filtrar a ≤ 90 min; mantener rutas sin dato OSRM para no perder resultados
                    $filtered = array_values(array_filter($results, function ($r) {
                        $mins = $r['route']['drive_time_minutes'] ?? null;
                        return $mins === null || $mins <= 90;
                    }));

                    // Solo aplicar el filtro si quedan al menos 2 rutas
                    if (count($filtered) >= 2) {
                        $results = $filtered;
                    }

                    // Ordenar por tiempo en coche ascendente
                    usort($results, function ($a, $b) {
                        $ta = $a['route']['drive_time_minutes'] ?? 9999;
                        $tb = $b['route']['drive_time_minutes'] ?? 9999;
                        return $ta <=> $tb;
                    });
                }
            }
        }

        // ── Intro de 1 frase con Sonnet ───────────────────────────────────────
        // Normalizar nombres de campo para que funcione con cualquier esquema de BD
        foreach ($results as &$r) {
            if (!isset($r['route']['title']) && isset($r['route']['nombre'])) {
                $r['route']['title'] = $r['route']['nombre'];
            }
        }
        unset($r);

        $count = count($results);
        $loc   = $params['location'] ?? $params['zone'] ?? '';

        $expandNote = '';
        if ($expandedRadius) {
            $expandNote = " He ampliado el radio de búsqueda a 150 km para encontrar más opciones.";
        } elseif ($expandedToAdj) {
            $adjZoneName = self::ZONE_ADJACENCY[$params['zone'] ?? ''] ?? 'zona adyacente';
            $expandNote  = " He ampliado la búsqueda a {$adjZoneName} para completar resultados.";
        } elseif ($expandedSearch) {
            $expandNote  = " He relajado el filtro de dificultad para mostrar más opciones.";
        }

        // Nota de condiciones de montaña para el prompt introductorio
        $conditionsNote = '';
        if (!empty($conditionsReport['conditions_index']) && $conditionsReport['conditions_index'] !== 'BUENAS') {
            $conditionsNote = " [Condiciones: {$conditionsReport['conditions_index']}]";
        }
        if (!empty($conditionsReport['narrative_context'])) {
            $conditionsNote .= " Contexto de montaña: {$conditionsReport['narrative_context']}";
        }
        if (!empty($conditionsReport['cota_nieve_est'])) {
            $conditionsNote .= " Cota de nieve del manto estimada: ~{$conditionsReport['cota_nieve_est']} m.";
        }

        // ── Fiabilidad del pronóstico: nota para Sonnet según days_ahead ────────
        // days_ahead 0-2: pronóstico fiable en montaña → sin nota
        // days_ahead 3-5: fiabilidad media → mencionar incertidumbre
        // days_ahead 6+:  orientativo → decirlo explícitamente
        $forecastReliabilityNote = '';
        if ($daysAhead >= 6) {
            $forecastReliabilityNote =
                "\nNOTA DE PRONÓSTICO: El horizonte es de {$daysAhead} días — orientativo en montaña. " .
                "Menciona brevemente que el usuario debería revisar las condiciones 48h antes de salir.";
        } elseif ($daysAhead >= 3) {
            $forecastReliabilityNote =
                "\nNOTA DE PRONÓSTICO: A {$daysAhead} días el margen de error en montaña es notable. " .
                "Menciona la incertidumbre de forma natural, sin alarmar.";
        }

        // ── Intro text with optional best-day forecast ────────────────────────
        $locLabel = $loc ?: 'la zona';

        // Cuando hay días por delante (>= 1) y datos de tiempo, incluir pronóstico
        // para que Sonnet pueda recomendar un día concreto.
        if ($daysAhead >= 1 && !empty($params['weather'])) {
            $forecastSummary = $this->summarizeForecastForPrompt($params['weather'], $daysAhead);
            $introPrompt =
                "El usuario pregunta: \"$query\"\n\n" .
                "He encontrado {$count} rutas en/cerca de {$locLabel}.{$expandNote}{$conditionsNote}\n\n" .
                "Pronóstico para los próximos {$daysAhead} días en {$locLabel}:\n{$forecastSummary}" .
                $forecastReliabilityNote . "\n\n" .
                "Escribe una respuesta breve (máximo 3 frases / 60 palabras):\n" .
                "1) Presenta las rutas encontradas cerca de \"{$locLabel}\" (no uses el nombre técnico de zona).\n" .
                "2) Indica de forma concreta cuál es el mejor día del pronóstico para salir de ruta y por qué (p. ej. temperatura, lluvia, viento).\n" .
                "NO describas rutas individuales.";
        } else {
            $introPrompt =
                "El usuario pregunta: \"$query\"\n\n" .
                "He encontrado {$count} rutas" . ($loc ? " en/cerca de \"{$loc}\"" : '') . ".{$expandNote}{$conditionsNote}" .
                $forecastReliabilityNote . " " .
                "Escribe EXACTAMENTE una sola frase de introducción (máximo 20 palabras). " .
                "Usa el nombre de lugar que el usuario mencionó, no el nombre técnico de zona. " .
                "NO describas rutas individuales.";
        }

        $text = $this->askClaude($introPrompt, $history, $prefs, 'route_request');

        return [
            'type'              => 'route_suggestions',
            'content'           => $text,
            'routes'            => array_map(fn($r) => $r['route'], $results),
            'reasons'           => array_map(fn($r) => $r['reasons'], $results),
            'weather_warning'   => $weatherWarning,
            'expanded_search'   => $expandedSearch,
            'conditions_report' => $conditionsReport,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function handleWeatherRequest(
        string $query,
        array  $prefs,
        array  $history,
        array  $extracted,
        array  $ctx
    ): array {
        $daysAhead = (int)($extracted['days_ahead'] ?? 0);

        // ── Detección de topónimo concreto (pre-Haiku safety net) ──────────
        // Si el usuario dice "Valle de Tena", usamos ese nombre exacto en la respuesta
        // en vez del alias que devuelva Nominatim (p. ej. "Astún" o "Sallent").
        $specificLoc  = $this->findSpecificLocationInQuery($query);
        $zoneName     = $specificLoc ? $specificLoc['geocode'] : ($extracted['zone'] ?? null);
        $displayName  = $specificLoc ? $specificLoc['display_name'] : null; // nombre para el usuario

        // Resolver zona → coordenadas
        $coords = null;
        $locationName = null;

        if (!empty($zoneName)) {
            $locData = $this->resolveZoneName($zoneName);
            $coords       = $locData['coordinates'] ?? null;
            // Prioridad: nombre exacto del usuario > nombre de Nominatim > zona extraída
            $locationName = $displayName ?? ($locData['location'] ?? $extracted['zone'] ?? $zoneName);

            // Si no hay coords pero tenemos zona DB → referencia
            if (empty($coords) && !empty($locData['zone'])) {
                $ref = self::ZONE_REFERENCE_POINTS[$locData['zone']] ?? null;
                if ($ref) {
                    $coords       = ['lat' => $ref['lat'], 'lon' => $ref['lon']];
                    $locationName = $locationName ?? $ref['name'];
                }
            }

            // NOTA: weather_query NO tiene límite geográfico — "¿qué tiempo hace en los Alpes?"
            // es una consulta válida aunque no haya rutas allí. El check de bounds solo aplica
            // a route_request (necesita rutas en BD). Aquí solo descartamos coordenadas
            // claramente absurdas (continentes equivocados por Nominatim mal interpretando slang).
            if (!empty($coords)) {
                $chkLat = (float)$coords['lat'];
                $chkLon = (float)$coords['lon'];
                // Descartar solo si Nominatim devolvió algo claramente fuera del planeta razonable
                // (ej: "el spot" geocodificado a un lugar en Asia o América)
                if ($chkLat < -60 || $chkLat > 75 || $chkLon < -30 || $chkLon > 45) {
                    $this->logger->warning('Weather: coords fuera de rango Europa/Africa, descartando', [
                        'zone' => $zoneName, 'lat' => $chkLat, 'lon' => $chkLon,
                    ]);
                    $coords       = null;
                    $locationName = null;
                }
            }
        }

        // Fallback 1: coordenadas del turno anterior de la misma sesión
        // (útil cuando el usuario dice "y en ese sitio" / "dime las condiciones en spot")
        if (empty($coords) && !empty($ctx['last_coords'])) {
            $coords       = $ctx['last_coords'];
            $locationName = $locationName ?? ($ctx['last_zone'] ?? null);
        }

        // Fallback 2: preferencias del usuario
        // EXCEPCIÓN anafórica: si el query contiene un deíctico que apunta a un sitio
        // mencionado antes ("allí", "ahí", "ese lugar"…) pero no hay last_zone en sesión,
        // NO usamos la zona habitual del perfil — pedimos clarificación para evitar
        // responder sobre Aragon cuando el usuario quería decir "el pico del que hablamos".
        $isAnaphoric = !empty(preg_match(
            '/\b(all[íi]|ah[íi]|ese\s+lugar|esa\s+zona|ese\s+sitio|en\s+ese|all[áa])\b/ui',
            $query, $_m
        )) && empty($ctx['last_zone']);
        if (empty($coords) && !empty($prefs['preferred_locations']) && !$isAnaphoric) {
            $locs   = is_array($prefs['preferred_locations'])
                    ? $prefs['preferred_locations']
                    : explode(',', $prefs['preferred_locations']);
            $first  = trim($locs[0]);
            $coords = $this->geocoding->getCoordinates($first);
            if ($coords) $locationName = $first;
        }

        if (empty($coords)) {
            return $this->makeClarificationResponse('zone',
                '¿Para qué zona o lugar quieres consultar el tiempo? (p. ej. Sierra Nevada, Picos de Europa, Teide, Ordesa…)'
            );
        }

        // Guardar coords resueltas para el contexto
        $extracted['resolved_coords'] = $coords;

        $lat  = (float)$coords['lat'];
        $lon  = (float)$coords['lon'];

        // ── Condiciones de montaña (AEMET + Météo-France + EAWS) ─────────────
        $condNarrative   = '';
        $conditionsRep   = null;
        try {
            $wZone    = $this->coordsToDbZone($lat, $lon);
            $precip3d = $this->weather->getPrecip3d($lat, $lon);
            $snowLine = $this->weather->getSnowLine($lat, $lon);
            $conditionsRep = $this->mountainConditions->buildConditionsReport(
                $lat, $lon, $wZone,
                $ctx['last_activity'] ?? 'hiking',
                1200,
                $precip3d,
                null,       // isoZero se afina más abajo de getMountainConditions
                $snowLine
            );
            if (!empty($conditionsRep['narrative_context'])) {
                $condNarrative = "\nCondiciones de montaña adicionales (AEMET/EAWS):\n" .
                                 $conditionsRep['narrative_context'];
            }
        } catch (\Exception) { /* sin datos de condiciones */ }

        try {
            if ($daysAhead <= 1) {
                $weatherData = $this->weather->getMountainConditions($lat, $lon);

                // Re-lanzar buildConditionsReport con el isocero real ahora disponible,
                // si el primero (antes del getMountainConditions) dejó cota_nieve_est nula.
                // Así el fallback ③ (isocero - 300m) puede actuar con el valor correcto.
                if ($conditionsRep && $conditionsRep['cota_nieve_est'] === null) {
                    $realIso = (int)($weatherData['iso_zero'] ?? 0);
                    if ($realIso > 0) {
                        try {
                            $wZone2 = $this->coordsToDbZone($lat, $lon);
                            $conditionsRep = $this->mountainConditions->buildConditionsReport(
                                $lat, $lon, $wZone2,
                                $ctx['last_activity'] ?? 'hiking',
                                1200,
                                $this->weather->getPrecip3d($lat, $lon),
                                $realIso,
                                $snowLine
                            );
                            if (!empty($conditionsRep['narrative_context'])) {
                                $condNarrative = "\nCondiciones de montaña adicionales (AEMET/EAWS):\n" .
                                                 $conditionsRep['narrative_context'];
                            }
                        } catch (\Exception) { /* mantener conditionsRep original */ }
                    }
                }

                $weatherSummary = $this->summarizeWeatherForPrompt($weatherData);

                // Añadir cota de nieve estimada al prompt (o aviso de incertidumbre)
                $isoZeroVal = (int)($weatherData['iso_zero'] ?? 0);
                if ($conditionsRep && $conditionsRep['cota_nieve_est'] !== null) {
                    // Tenemos estimación real: informar a Sonnet de la horquilla
                    $cotaEst = $conditionsRep['cota_nieve_est'];
                    $condNarrative .= "\nCota de nieve del manto acumulado estimada: ~{$cotaEst} m. "
                        . "El isocero hoy está a {$isoZeroVal} m — recuerda distinguir entre "
                        . "isocero (dónde cae nieve hoy) y cota del manto (dónde hay nieve acumulada).";
                } elseif ($isoZeroVal > 0) {
                    // Sin datos de manto: advertir a Sonnet que no use el isocero como cota de nieve
                    $condNarrative .= "\nIsocero hoy: {$isoZeroVal} m (isoterma 0°C, no cota del manto). "
                        . "NOTA IMPORTANTE: El modelo no detecta nieve en el rango altitudinal "
                        . "muestreado alrededor de este punto. En zonas de alta montaña puede "
                        . "existir manto acumulado de invierno por encima de 2.000 m aunque el "
                        . "isocero esté alto. No afirmes ni niegues la presencia de nieve en "
                        . "cotas altas sin datos confirmados — señala la incertidumbre al usuario.";
                }

                $prompt = "El usuario pregunta: \"$query\"\n" .
                          "Lugar: " . ($locationName ?? 'zona consultada') . "\n" .
                          "Condiciones actuales:\n$weatherSummary" .
                          $condNarrative . "\n" .
                          "Interpreta en clave de montaña y actividad." .
                          (!empty($ctx['last_activity']) ? " Actividad en contexto: {$ctx['last_activity']}." : "");

                $text = $this->askClaude($prompt, $history, $prefs, 'weather_query');

                return [
                    'type'              => 'weather_info',
                    'content'           => $text,
                    'weather_raw'       => $weatherData,
                    'location'          => $locationName,
                    'days'              => 1,
                    'conditions_report' => $conditionsRep,
                ];
            } else {
                // Pronóstico multi-día.
                // Si la query busca la "mejor ventana" sin especificar días, ampliamos
                // al horizonte máximo (16 días) para que getOptimalWindow() tenga más datos.
                $isWindowSearch = preg_match(
                    '/cu[aá]ndo|ventana|mejor\s+(d[ií]a|momento)|qu[eé]\s+d[ií]as?\s+(son|estar[aá])/ui',
                    $query
                );
                $days     = $isWindowSearch ? 16 : min($daysAhead + 1, 16);
                $forecast = null;

                // Intentar pronóstico; si falla, degradar a condiciones actuales
                try {
                    $forecast = $this->weather->getForecast($lat, $lon, $days);
                } catch (\Exception $fe) {
                    $this->logger->warning('getForecast fallback a current', ['error' => $fe->getMessage()]);
                }

                if (!empty($forecast)) {
                    $forecastSummary = $this->summarizeForecastForPrompt($forecast, $daysAhead);

                    // ── Ventana óptima ──────────────────────────────────────
                    $wActivity = $ctx['last_activity'] ?? ($extracted['activity'] ?? 'hiking');
                    $optWin    = $this->weather->getOptimalWindow($forecast, $wActivity);

                    $optimalWindowSection = '';
                    if ($optWin['found']) {
                        $optimalWindowSection = "\n\nVentana óptima calculada: " . $optWin['summary'];
                        if ($optWin['confidence'] !== 'alta') {
                            $optimalWindowSection .=
                                "\nRECUERDA mencionar la incertidumbre y aconsejar revisar " .
                                "las condiciones 48 h antes de la salida.";
                        }
                    } else {
                        $optimalWindowSection = "\n\nNOTA: " . $optWin['summary'] .
                            " Informa al usuario sobre las condiciones adversas esperadas.";
                    }

                    // ── Nota de fiabilidad según horizonte temporal ─────────
                    $forecastReliabilityNote = '';
                    if ($daysAhead >= 6) {
                        $forecastReliabilityNote =
                            "\nNOTA OBLIGATORIA: El horizonte es de {$daysAhead} días — " .
                            "DEBES mencionar explícitamente al inicio que a más de 5 días " .
                            "la fiabilidad es reducida y el pronóstico es orientativo. " .
                            "Recomienda revisar las condiciones 48h antes de salir.";
                    } elseif ($daysAhead >= 3) {
                        $forecastReliabilityNote =
                            "\nNOTA: A {$daysAhead} días el margen de error en montaña es notable. " .
                            "Menciona la incertidumbre de forma natural, sin alarmar.";
                    }

                    $prompt = "El usuario pregunta: \"$query\"\n" .
                              "Lugar: " . ($locationName ?? 'zona consultada') . "\n" .
                              "Pronóstico ({$daysAhead} días):\n$forecastSummary" .
                              $condNarrative .
                              $optimalWindowSection .
                              $forecastReliabilityNote . "\n" .
                              "Resume de forma práctica indicando la ventana óptima y los mejores días " .
                              "para salir, con la incertidumbre apropiada según el horizonte temporal.";

                    $text = $this->askClaude($prompt, $history, $prefs, 'weather_query');

                    return [
                        'type'              => 'weather_info',
                        'content'           => $text,
                        'forecast'          => $forecast,
                        'location'          => $locationName,
                        'days'              => $daysAhead,
                        'conditions_report' => $conditionsRep,
                    ];
                }

                // Fallback: condiciones actuales si el pronóstico no está disponible
                $weatherData    = $this->weather->getMountainConditions($lat, $lon);
                $weatherSummary = $this->summarizeWeatherForPrompt($weatherData);
                $prompt = "El usuario preguntaba por el pronóstico de {$daysAhead} días " .
                          "pero solo tengo datos actuales. Indícalo brevemente y describe " .
                          "las condiciones de hoy en " . ($locationName ?? 'la zona') . ":\n" .
                          $weatherSummary . $condNarrative;

                $text = $this->askClaude($prompt, $history, $prefs, 'weather_query');

                return [
                    'type'              => 'weather_info',
                    'content'           => $text,
                    'weather_raw'       => $weatherData,
                    'location'          => $locationName,
                    'days'              => 1,
                    'conditions_report' => $conditionsRep,
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('handleWeatherRequest error', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'lat'   => $lat ?? null,
                'lon'   => $lon ?? null,
            ]);
            return [
                'type'    => 'weather_info',
                'content' => 'No he podido obtener los datos meteorológicos en este momento. ' .
                             'Inténtalo de nuevo en unos segundos.',
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function handleSafetyQuery(
        string $query,
        array  $prefs,
        array  $history,
        array  $extracted,
        array  $ctx = []
    ): array {
        $weatherContext    = '';
        $conditionsSection = '';

        // safety_query siempre intenta obtener datos meteorológicos reales
        $specificLoc = $this->findSpecificLocationInQuery($query);
        $zoneName    = $specificLoc ? $specificLoc['geocode'] : ($extracted['zone'] ?? null);
        $safetyDisplayName = $specificLoc ? $specificLoc['display_name'] : ($extracted['zone'] ?? null);

        $coords = null;

        if (!empty($zoneName)) {
            $locData = $this->resolveZoneName($zoneName);
            $coords  = $locData['coordinates'] ?? null;

            // safety_query tampoco tiene límite geográfico — el check de bounds solo aplica
            // a route_request. Aquí solo descartamos coords claramente absurdas.
            if (!empty($coords)) {
                $chkLat = (float)$coords['lat'];
                $chkLon = (float)$coords['lon'];
                if ($chkLat < -60 || $chkLat > 75 || $chkLon < -30 || $chkLon > 45) {
                    $this->logger->warning('Safety: coords fuera de rango, descartando', [
                        'zone' => $zoneName, 'lat' => $chkLat, 'lon' => $chkLon,
                    ]);
                    $coords = null;
                    $safetyDisplayName = null;
                }
            }
        }

        // Fallback: coordenadas de la consulta anterior en esta sesión
        if (empty($coords) && !empty($ctx['last_coords'])) {
            $coords = $ctx['last_coords'];
            $safetyDisplayName = $safetyDisplayName ?? $ctx['last_zone'] ?? null;
        }

        if (!empty($coords)) {
            $sLat = (float)$coords['lat'];
            $sLon = (float)$coords['lon'];
            $safetyLocLabel = $safetyDisplayName ?? $zoneName ?? 'la zona';

            // Meteorología actual
            try {
                $weather = $this->weather->getCurrentWeather($sLat, $sLon);
                $weatherContext = "\n\nDatos meteorológicos actuales en {$safetyLocLabel}:\n" .
                                 $this->summarizeWeatherForPrompt($weather);
            } catch (\Exception) { /* sin datos */ }

            // Condiciones de montaña (alertas, aludes, barro, nieve)
            try {
                $sZone    = $this->coordsToDbZone($sLat, $sLon);
                $precip3d = $this->weather->getPrecip3d($sLat, $sLon);
                $snowLine = $this->weather->getSnowLine($sLat, $sLon);
                $condRep  = $this->mountainConditions->buildConditionsReport(
                    $sLat, $sLon, $sZone,
                    $extracted['activity'] ?? 'hiking',
                    1200,
                    $precip3d,
                    null,
                    $snowLine
                );

                $parts = [];
                if (!empty($condRep['alert_level']) && $condRep['alert_level'] !== 'green') {
                    $lvlLabel = ['yellow' => 'amarilla', 'orange' => 'naranja', 'red' => 'roja'];
                    $parts[] = "Alerta " . ($lvlLabel[$condRep['alert_level']] ?? $condRep['alert_level']) .
                               (empty($condRep['alert_types']) ? '' : " por " . implode(', ', $condRep['alert_types'])) . ".";
                }
                if (!empty($condRep['avalanche_level'])) {
                    $parts[] = "Peligro de aludes: nivel {$condRep['avalanche_level']}/5 ({$condRep['avalanche_source']}).";
                }
                if (!empty($condRep['narrative_context'])) {
                    $parts[] = $condRep['narrative_context'];
                }
                if (!empty($condRep['terrain_mud_risk'])) {
                    $parts[] = "Riesgo elevado de barro (lluvia reciente: " . round($condRep['precip_3d_mm'], 1) . " mm en 3 días).";
                }
                if (!empty($parts)) {
                    $conditionsSection = "\n\nCondiciones de montaña en {$safetyLocLabel} (AEMET/EAWS):\n" .
                                        implode(' ', $parts);
                }
            } catch (\Exception) { /* sin datos de condiciones */ }
        }

        $text = $this->askClaude(
            "El usuario pregunta sobre seguridad: \"$query\"\n" .
            "Responde con conocimiento experto, priorizando la seguridad." .
            $weatherContext . $conditionsSection,
            $history, $prefs, 'safety_query'
        );

        return ['type' => 'safety_info', 'content' => $text];
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function handlePersonalizedRecommendation(
        string $query,
        string $userId,
        array  $prefs,
        array  $history,
        array  $extracted,
        array  $ctx
    ): array {
        // Perfil insuficiente → onboarding
        $totalInteractions = (int)($prefs['total_interactions'] ?? 0);
        $hasActivity = !empty($prefs['preferred_activities']);
        $hasLevel    = !empty($prefs['experience_level']);

        if ($totalInteractions < 3 || !$hasActivity || !$hasLevel) {
            $text = $this->askClaude(
                "El usuario quiere recomendaciones personalizadas pero no tenemos datos de su perfil. " .
                "Hazle tres preguntas en un solo mensaje conversacional para conocer: " .
                "1) qué actividad practica habitualmente, 2) su nivel, 3) en qué zonas de España suele salir " .
                "(Pirineos, Sierra Nevada, Picos de Europa, Canarias, etc.).",
                $history, $prefs, 'onboarding'
            );
            return [
                'type'                  => 'clarification_request',
                'content'               => $text,
                'questions'             => [],
                'awaiting_clarification'=> 'onboarding',
            ];
        }

        // Normalizar preferred_activities (puede ser string CSV o array)
        $activityRaw = $prefs['preferred_activities'] ?? null;
        $preferredActivity = is_array($activityRaw)
            ? trim($activityRaw[0] ?? 'hiking')
            : trim(explode(',', $activityRaw ?? 'hiking')[0]);

        // Perfil completo → evaluar tiempo en zonas preferidas en paralelo
        $preferredZones = is_array($prefs['preferred_locations'])
            ? $prefs['preferred_locations']
            : array_filter(array_map('trim', explode(',', $prefs['preferred_locations'] ?? '')));

        if (empty($preferredZones)) {
            // Fallback 1: user_zones (zonas registradas automáticamente al buscar rutas)
            $numericId = $this->resolveUserId($userId);
            if ($numericId) {
                $preferredZones = $this->inferZonesFromUserZones($numericId);
            }
            // Fallback 2: rutas favoritas del usuario → extraer ubicacion más frecuente
            if (empty($preferredZones) && $numericId) {
                $preferredZones = $this->inferZonesFromFavorites($numericId);
            }
        }

        if (empty($preferredZones)) {
            // Último recurso: zona con más rutas en la BD para la actividad del usuario.
            // Garantiza que un usuario nuevo con actividad pero sin historial geográfico
            // recibe rutas en vez de una clarification vacía.
            $preferredZones = $this->inferZonesFromPopularRoutes($preferredActivity);
        }

        if (empty($preferredZones)) {
            return $this->makeClarificationResponse('zone',
                '¿En qué zonas te gustaría que busque? (p. ej. Pirineos, Sierra Nevada, Picos de Europa, Canarias…)'
            );
        }

        // ── Evaluación paralela del tiempo por zona ───────────────────────────
        // Recopilar coordenadas de referencia y lanzar batch
        $zoneKeys   = [];
        $batchCoords = [];
        foreach ($preferredZones as $zone) {
            $ref = self::ZONE_REFERENCE_POINTS[$zone] ?? null;
            if (!$ref) continue;
            $zoneKeys[]   = $zone;
            $batchCoords[] = ['lat' => $ref['lat'], 'lon' => $ref['lon']];
        }

        $weatherResults = [];
        if (!empty($batchCoords)) {
            try {
                $weatherResults = $this->weather->getCurrentWeatherBatch($batchCoords);
            } catch (\Exception) { /* continuar sin datos de tiempo */ }
        }

        // Clasificar zonas por seguridad
        $zoneWeather = [];
        foreach ($zoneKeys as $idx => $zone) {
            $ref = self::ZONE_REFERENCE_POINTS[$zone];
            $w   = $weatherResults[$idx] ?? null;
            $safe = $w ? $this->weather->isSafeForActivity($w, $preferredActivity) : null;
            $zoneWeather[$zone] = ['weather' => $w, 'safe' => $safe, 'ref' => $ref];
        }

        // Elegir zona ganadora: primero con tiempo seguro, luego "menos mala"
        $bestZone   = null;
        $bestCoords = null;
        $allUnsafe  = true;

        foreach ($zoneWeather as $zone => $data) {
            if ($data['safe'] === true) {
                $bestZone   = $zone;
                $bestCoords = $data['ref'];
                $allUnsafe  = false;
                break;
            }
        }

        // Si ninguna zona es segura → elegir la primera con datos (menos mala)
        if (!$bestZone) {
            foreach ($zoneWeather as $zone => $data) {
                if ($data['weather'] !== null) {
                    $bestZone   = $zone;
                    $bestCoords = $data['ref'];
                    break;
                }
            }
        }

        // Fallback sin datos de tiempo → primera zona preferida
        if (!$bestZone) {
            $bestZone = $zoneKeys[0] ?? null;
            $ref = $bestZone ? (self::ZONE_REFERENCE_POINTS[$bestZone] ?? null) : null;
            $bestCoords = $ref;
            $allUnsafe  = false; // sin datos no advertimos
        }

        if (!$bestZone) {
            return ['type' => 'general', 'content' => 'No he podido determinar una zona adecuada. ¿En qué zona de España te gustaría buscar?'];
        }

        // ── Buscar rutas en la zona ganadora ─────────────────────────────────
        // Para zonas que no están en la BD como columna `location`, activar modo
        // proximidad (bounding box) en lugar del filtro de zona exacto.
        $knownDbZones = ['Aragon', 'Western_Pyrenees', 'Central_Pyrenees', 'Euskadi'];
        $useProximity = !in_array($bestZone, $knownDbZones, true);

        $params = [
            'zone'         => $bestZone,
            'activity'     => $preferredActivity,
            'difficulty'   => $prefs['experience_level'] ?? 'intermediate',
            'coordinates'  => $bestCoords ? ['lat' => (float)$bestCoords['lat'], 'lon' => (float)$bestCoords['lon']] : null,
            'proximity_km' => ($useProximity && $bestCoords) ? 120.0 : null,
        ];
        if (!empty($zoneWeather[$bestZone]['weather'])) {
            $params['weather'] = $zoneWeather[$bestZone]['weather'];
        }

        // ── Condiciones de montaña para la zona ganadora ─────────────────────
        $perCondRep = null;
        $perCondNote = '';
        if ($bestCoords) {
            try {
                $pLat     = (float)$bestCoords['lat'];
                $pLon     = (float)$bestCoords['lon'];
                $precip3d = $this->weather->getPrecip3d($pLat, $pLon);
                $zoneCotaRef = [
                    'Aragon' => 1600, 'Western_Pyrenees' => 1500, 'Central_Pyrenees' => 1700,
                    'Euskadi' => 1200, 'Cantabria' => 1400, 'Sistema_Central' => 1500,
                    'Sierra_Nevada' => 2000, 'Galicia' => 800, 'Andalucia' => 1000,
                    'Sistema_Iberico' => 1200, 'Levante' => 900, 'Canarias' => 1400,
                    'Baleares' => 900, 'Francia_Sur' => 1600,
                ];
                $snowLine   = $this->weather->getSnowLine($pLat, $pLon);
                $perCondRep = $this->mountainConditions->buildConditionsReport(
                    $pLat, $pLon, $bestZone, $preferredActivity,
                    $zoneCotaRef[$bestZone] ?? 1200,
                    $precip3d, null, $snowLine
                );
                // Ajustar dificultad antes de buscar
                if (!empty($perCondRep['conditions_index']) && $perCondRep['conditions_index'] !== 'BUENAS') {
                    $params['difficulty'] = $this->adjustDifficultyForConditions(
                        $params['difficulty'], $perCondRep['conditions_index']
                    );
                }
                if (!empty($perCondRep['narrative_context'])) {
                    $perCondNote = "\nCondiciones de montaña: {$perCondRep['narrative_context']}";
                }
                if (!empty($perCondRep['cota_nieve_est'])) {
                    $perCondNote .= " Cota de nieve del manto estimada: ~{$perCondRep['cota_nieve_est']} m.";
                }
            } catch (\Exception) { /* continuar sin datos */ }
        }

        $results = $this->recommender->getRecommendations($params, $prefs, MAX_ROUTE_SUGGESTIONS);

        if (empty($results)) {
            $text = $this->askClaude(
                "No se han encontrado rutas adecuadas para el perfil del usuario en {$bestZone}. " .
                "Explícalo amablemente y sugiere ampliar criterios o explorar otras zonas.",
                $history, $prefs, 'personalized_recommendation'
            );
            return ['type' => 'no_routes', 'content' => $text];
        }

        // ── Calcular tiempos en coche desde el punto de referencia de zona ───
        if (!empty($bestCoords)) {
            $destinations = array_map(function ($r) {
                $route = $r['route'];
                $lat = $route['startLat'] ?? $route['start_lat'] ?? $route['latitud_inicio'] ?? null;
                $lon = $route['startLng'] ?? $route['start_lng'] ?? $route['start_lon'] ?? $route['longitud_inicio'] ?? null;
                return ['lat' => $lat, 'lon' => $lon];
            }, $results);

            try {
                $driveTimes = $this->geocoding->getDrivingTimeBatch(
                    (float)$bestCoords['lat'],
                    (float)$bestCoords['lon'],
                    $destinations
                );

                foreach ($results as $i => &$result) {
                    if (!empty($driveTimes[$i])) {
                        $result['route']['drive_time_minutes'] = $driveTimes[$i]['minutes'];
                        $result['route']['drive_km']           = $driveTimes[$i]['km'];
                    }
                }
                unset($result);
            } catch (\Exception) { /* continuar sin tiempos en coche */ }
        }

        // ── Construir prompt para Sonnet ─────────────────────────────────────
        $zoneCtxParts = [];
        foreach ($zoneWeather as $zone => $data) {
            if ($data['weather'] === null) continue;
            $label = $data['safe'] === true ? '✅ buenas condiciones' : '⚠️ condiciones desfavorables';
            $zoneCtxParts[] = "{$zone}: {$label}";
        }
        $zoneCtxStr = implode('; ', $zoneCtxParts);

        $weatherWarnMsg = null;
        if ($allUnsafe && !empty($zoneWeather[$bestZone]['weather'])) {
            $recs = $this->weather->getWeatherRecommendations($zoneWeather[$bestZone]['weather'], $preferredActivity);
            $weatherWarnMsg = $recs;
        }

        $trimmedResults = array_map(
            fn($r) => array_merge($r, ['route' => $this->trimRouteForPrompt($r['route'])]),
            $results
        );
        $routeSummary = $this->formatRoutesForPrompt($trimmedResults);

        $allUnsafeNote = $allUnsafe
            ? "NOTA IMPORTANTE: ninguna zona tiene condiciones ideales hoy. He elegido {$bestZone} como la mejor opción disponible. Menciónalo con honestidad pero sin dramatismo.\n"
            : '';

        $text = $this->askClaude(
            "El usuario pide recomendaciones personalizadas.\n" .
            "Perfil: nivel {$prefs['experience_level']}, actividad habitual {$preferredActivity}.\n" .
            ($zoneCtxStr ? "Condiciones por zona: {$zoneCtxStr}.\n" : '') .
            $allUnsafeNote .
            "Zona elegida: {$bestZone}." . $perCondNote . "\n\n" .
            "Rutas encontradas:\n{$routeSummary}\n\n" .
            "Explica de forma personal y motivadora por qué estas rutas y esta zona.",
            $history, $prefs, 'personalized_recommendation'
        );

        return [
            'type'              => 'route_suggestions',
            'content'           => $text,
            'routes'            => array_map(fn($r) => $r['route'], $results),
            'reasons'           => array_map(fn($r) => $r['reasons'], $results),
            'weather_warning'   => $weatherWarnMsg,
            'conditions_report' => $perCondRep,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function handleGeneralQuery(
        string $query,
        string $userId,
        array  $prefs,
        array  $history
    ): array {
        $needsProfile = empty($prefs) || ($prefs['total_interactions'] ?? 0) < 3;
        $extra = $needsProfile
            ? "\nNOTA: Si el usuario se presenta o saluda, pregúntale amablemente qué actividades practica."
            : '';

        $text = $this->askClaude($query . $extra, $history, $prefs, 'general');
        $this->maybeUpdatePreferencesFromChat($userId, $query, $prefs);

        return ['type' => 'general', 'content' => $text];
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Procesa la respuesta libre del usuario al onboarding.
     * Usa Haiku para extraer actividad, nivel y zonas preferidas,
     * actualiza las preferencias en BD y redirige a personalized_recommendation.
     */
    private function handleOnboardingResponse(
        string $query,
        string $userId,
        array  $prefs,
        array  $history,
        array  $ctx
    ): array {
        // Haiku extrae datos del perfil desde la respuesta libre
        $profileData = $this->extractOnboardingProfile($query);

        // Actualizar preferencias en BD con lo que se extrajo
        $numericId = $this->resolveUserId($userId);
        if ($numericId && !empty($profileData)) {
            $this->saveOnboardingProfile($numericId, $profileData);
            // Recargar preferencias actualizadas
            $prefs = $this->preferences->getUserPreferences($userId);
        }

        // Confirmar y recomendar directamente si hay suficiente información
        $hasEnough = !empty($profileData['activity']) || !empty($profileData['preferred_zones']);
        if ($hasEnough) {
            $extracted = [
                'intent'               => 'personalized_recommendation',
                'zone'                 => null,
                'activity'             => $profileData['activity'] ?? null,
                'difficulty'           => $profileData['difficulty'] ?? null,
                'timeframe'            => 'today',
                'days_ahead'           => 0,
                'weather_check_needed' => false,
            ];
            // Recomendar directamente con el perfil recién guardado
            return $this->handlePersonalizedRecommendation(
                $query, $userId, $prefs, $history, $extracted, $ctx
            );
        }

        // Si no se pudo extraer nada útil → respuesta genérica
        $text = $this->askClaude(
            "El usuario respondió al onboarding: \"{$query}\"\n" .
            "No he podido extraer sus preferencias claramente. " .
            "Pídele que repita con ejemplos concretos (p. ej. 'hago senderismo, nivel intermedio, en Ordesa').",
            $history, $prefs, 'onboarding'
        );
        return [
            'type'                   => 'clarification_request',
            'content'                => $text,
            'questions'              => [],
            'awaiting_clarification' => 'onboarding',
        ];
    }

    /**
     * Llama a Haiku para extraer perfil de montañismo de una respuesta libre de onboarding.
     * Devuelve [activity, difficulty, preferred_zones[]].
     */
    private function extractOnboardingProfile(string $query): array {
        $default = ['activity' => null, 'difficulty' => null, 'preferred_zones' => []];

        $prompt = <<<PROMPT
Analiza la respuesta de un usuario sobre su perfil de montañismo y devuelve ÚNICAMENTE JSON con:

{
  "activity": "hiking|ski_touring|climbing|cycle_touring|snowshoeing|null",
  "difficulty": "beginner|intermediate|advanced|null",
  "preferred_zones": []
}

REGLAS DE ZONAS (array con los valores exactos):
- "Pirineos aragoneses", "Huesca", "Ordesa", "Formigal", "Benasque", "Candanchú", "Aragón" → "Aragon"
- "Pirineo navarro", "occidental", "Roncal", "Ansó", "Hecho", "Roncesvalles" → "Western_Pyrenees"
- "Cataluña", "Lleida", "Pallars", "Aigüestortes", "Andorra", "Val d'Aran", "Sort" → "Central_Pyrenees"
- "Euskadi", "País Vasco", "Donostia", "Gipuzkoa", "Bizkaia" → "Euskadi"
- "Cantabria", "Picos de Europa", "Asturias", "Liébana" → "Cantabria"
- "Galicia", "Courel", "Ancares", "Xurés" → "Galicia"
- "Madrid sierra", "Guadarrama", "Gredos", "Somosierra", "Segovia" → "Sistema_Central"
- "Teruel", "Maestrazgo", "Gúdar", "Javalambre", "Moncayo", "Sistema Ibérico" → "Sistema_Iberico"
- "Sierra Nevada", "Mulhacén", "Granada sierra" → "Sierra_Nevada"
- "Andalucía", "Sierra de las Nieves", "Cazorla", "Segura", "Málaga", "Sevilla" → "Andalucia"
- "Valencia", "Castellón", "Alicante", "Levante", "Murcia" → "Levante"
- "Canarias", "Tenerife", "Gran Canaria", "La Palma", "Teide" → "Canarias"
- "Mallorca", "Menorca", "Ibiza", "Baleares", "Tramuntana" → "Baleares"
- "Francia", "Pirineos franceses", "Toulouse", "Occitania" → "Francia_Sur"
null si no menciona zona concreta.

REGLAS DE DIFICULTAD:
- "principiante", "fácil", "empezando" → "beginner"
- "intermedio", "media", "algunos años" → "intermediate"
- "avanzado", "experto", "mucha experiencia" → "advanced"

Respuesta del usuario: "$query"
PROMPT;

        try {
            $messages = [['role' => 'user', 'content' => $prompt]];
            // Usamos extractQueryData internamente pero con prompt propio
            $response = $this->claude->callHaikuRaw($messages, 200);
            $text     = $response['content'][0]['text'] ?? '';

            preg_match('/\{[\s\S]*\}/u', $text, $m);
            if (empty($m[0])) return $default;

            $decoded = json_decode($m[0], true);
            if (!is_array($decoded)) return $default;

            $validActivities = ['hiking', 'ski_touring', 'climbing', 'cycle_touring', 'snowshoeing'];
            $validDiffs      = ['beginner', 'intermediate', 'advanced'];
            $validZones      = array_keys(self::ZONE_REFERENCE_POINTS);

            return [
                'activity'        => in_array($decoded['activity'] ?? '', $validActivities, true) ? $decoded['activity'] : null,
                'difficulty'      => in_array($decoded['difficulty'] ?? '', $validDiffs, true) ? $decoded['difficulty'] : null,
                'preferred_zones' => array_values(array_filter(
                    (array)($decoded['preferred_zones'] ?? []),
                    fn($z) => in_array($z, $validZones, true)
                )),
            ];
        } catch (\Exception $e) {
            $this->logger->warning('extractOnboardingProfile error', ['error' => $e->getMessage()]);
            return $default;
        }
    }

    /**
     * Persiste el perfil extraído del onboarding en user_preferences.
     */
    private function saveOnboardingProfile(int $userId, array $profile): void {
        $sets  = [];
        $vals  = [];
        $types = '';

        if (!empty($profile['activity'])) {
            $sets[]  = 'actividades_preferidas = ?';
            $vals[]  = json_encode([$profile['activity']]);
            $types  .= 's';
        }
        if (!empty($profile['difficulty'])) {
            $sets[]  = 'nivel_experiencia = ?';
            $vals[]  = $profile['difficulty'];
            $types  .= 's';
        }
        if (!empty($profile['preferred_zones'])) {
            $sets[]  = 'ubicaciones_habituales = ?';
            $vals[]  = json_encode($profile['preferred_zones']);
            $types  .= 's';
        }
        if (empty($sets)) return;

        $sets[]  = 'ultima_actualizacion = NOW()';
        $vals[]  = $userId;
        $types  .= 'i';

        try {
            $stmt = $this->conn->prepare(
                'UPDATE user_preferences SET ' . implode(', ', $sets) . ' WHERE usuario_id = ?'
            );
            $stmt->bind_param($types, ...$vals);
            $stmt->execute();
        } catch (\Exception $e) {
            $this->logger->error('saveOnboardingProfile error', ['error' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // HELPERS DE GEOCODIFICACIÓN Y ZONAS
    // =========================================================================

    /**
     * Resuelve un nombre de zona/lugar a coordenadas y zona de BD.
     * Orden: tabla locations → Nominatim → detectZoneFromQuery.
     */
    private function resolveZoneName(string $zoneName): array {
        $result = [];

        // 1. Buscar en tabla locations
        try {
            $stmt = $this->conn->prepare(
                "SELECT name, lat, lon FROM locations
                 WHERE LOWER(?) LIKE CONCAT('%', LOWER(normalized_name), '%')
                    OR LOWER(?) LIKE CONCAT('%', LOWER(name), '%')
                 LIMIT 1"
            );
            $stmt->bind_param("ss", $zoneName, $zoneName);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                return [
                    'location'             => $row['name'],
                    'coordinates'          => ['lat' => (float)$row['lat'], 'lon' => (float)$row['lon']],
                    'zone'                 => $this->coordsToDbZone((float)$row['lat'], (float)$row['lon']),
                    'is_specific_location' => true,   // lugar geocodificado → modo proximidad
                ];
            }
        } catch (\Exception) { /* continuar */ }

        // 2. Nominatim — buscar dentro del área de cobertura (España + sur de Francia + Islas)
        // NOTA: viewbox es preferencia, no límite estricto — Nominatim puede devolver
        // coords fuera del viewbox (ej. "Los Alpes" → Suiza). Verificamos cobertura.
        $coords = $this->geocoding->getCoordinates($zoneName);
        if ($coords) {
            if (!$this->geocoding->isInCoveredArea($coords['lat'], $coords['lon'])) {
                // Fuera de cobertura — dejar caer al paso 4 para devolver out_of_bounds
                $coords = null;
            } else {
                return [
                    'location'             => $zoneName,
                    'coordinates'          => $coords,
                    'zone'                 => $this->coordsToDbZone((float)$coords['lat'], (float)$coords['lon']),
                    'is_specific_location' => true,   // lugar geocodificado → modo proximidad
                ];
            }
        }

        // 3. Detección por palabras clave → zona de referencia (abstracta, NO proximidad)
        $zone = $this->detectZoneFromQuery($zoneName);
        if ($zone) {
            $ref = self::ZONE_REFERENCE_POINTS[$zone];
            return [
                'location'             => $ref['name'],
                'coordinates'          => ['lat' => $ref['lat'], 'lon' => $ref['lon']],
                'zone'                 => $zone,
                'is_specific_location' => false,  // zona abstracta → modo zona clásico
            ];
        }

        // 4. Último intento sin restricción de viewbox
        //    - Si está fuera de España/Francia → Caso C (out_of_bounds)
        //    - Si está dentro → usar coords con modo proximidad
        $coordsLoose = $this->geocoding->getCoordinates($zoneName, false);
        if ($coordsLoose) {
            if (!$this->geocoding->isInCoveredArea($coordsLoose['lat'], $coordsLoose['lon'])) {
                return ['out_of_bounds' => true, 'location' => $zoneName];
            }
            return [
                'location'             => $zoneName,
                'coordinates'          => $coordsLoose,
                'zone'                 => $this->coordsToDbZone((float)$coordsLoose['lat'], (float)$coordsLoose['lon']),
                'is_specific_location' => true,
            ];
        }

        return ['location' => $zoneName];
    }

    /**
     * Mapea coordenadas a zona del sistema.
     * Las 4 zonas pirenaicas (Aragon, Western_Pyrenees, Central_Pyrenees, Euskadi)
     * coinciden con los valores reales de la BD. El resto son zonas de referencia
     * para meteorología y búsqueda por proximidad.
     */
    /**
     * Detecta topónimos concretos de montaña en la query del usuario.
     * Devuelve ['display_name', 'zone', 'geocode'] o null.
     * La detección es pre-Haiku: asegura que el nombre en la respuesta coincide
     * exactamente con lo que el usuario escribió, no con el alias interno de Nominatim.
     */
    private function findSpecificLocationInQuery(string $query): ?array {
        // Normalizar: minúsculas, quitar acentos comunes, quitar signos
        $q = mb_strtolower($query, 'UTF-8');
        $q = strtr($q, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
            'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u',
        ]);

        // Buscar primero las claves más largas (evita que 'sort' matches 'sort' dentro de 'resort')
        $sorted = self::SPECIFIC_LOCATIONS;
        uksort($sorted, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($sorted as $keyword => $data) {
            $kNorm = strtr(mb_strtolower($keyword, 'UTF-8'), [
                'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            ]);
            if (mb_strpos($q, $kNorm) !== false) {
                // Reconstruir el nombre de display con capitalización original
                $display = implode(' ', array_map('ucfirst', explode(' ', $keyword)));
                return [
                    'display_name' => $display,
                    'zone'         => $data['zone'],
                    'geocode'      => $data['geocode'],
                ];
            }
        }
        return null;
    }

    /**
     * Baja la dificultad un nivel cuando las condiciones de montaña son adversas.
     * MALAS → siempre 'beginner'; ACEPTABLE → un nivel por debajo del actual.
     */
    private function adjustDifficultyForConditions(?string $difficulty, string $conditionsIndex): ?string {
        if ($conditionsIndex === 'MALAS') {
            return 'beginner';
        }
        if ($conditionsIndex === 'ACEPTABLE') {
            return match ($difficulty) {
                'advanced'     => 'intermediate',
                'intermediate' => 'beginner',
                default        => 'beginner',
            };
        }
        return $difficulty;
    }

    private function coordsToDbZone(float $lat, float $lon): string {
        // Islas Canarias
        if ($lat < 30.0) return 'Canarias';
        // Islas Baleares (Mallorca, Menorca, Ibiza)
        if ($lat >= 38.5 && $lat <= 40.5 && $lon >= 1.0) return 'Baleares';
        // Sur de Francia (norte del Pirineo)
        if ($lat > 43.8) return 'Francia_Sur';

        // Zona pirenaica → zonas actuales de la BD
        if ($lat >= 42.0 && $lat <= 43.8) {
            if ($lon < -1.8) return 'Euskadi';
            if ($lon < -0.1) return 'Western_Pyrenees';
            if ($lon <  1.5) return 'Aragon';
            return 'Central_Pyrenees';
        }

        // Cantábrico (norte de España, lat 42.5–43.8, oeste del País Vasco)
        if ($lat >= 42.5 && $lon <= -5.5) return 'Galicia';
        if ($lat >= 42.5 && $lon <= -3.0) return 'Cantabria';

        // Sistema Central (Gredos, Guadarrama, Somosierra…)
        if ($lat >= 40.0 && $lat < 42.0 && $lon >= -6.0 && $lon <= -2.0) return 'Sistema_Central';

        // Sur / Sierra Nevada / Andalucía
        if ($lat < 37.5) return 'Andalucia';
        if ($lat >= 36.5 && $lat < 38.5 && $lon >= -5.5 && $lon <= -1.5) return 'Sierra_Nevada';

        // Levante / Comunidad Valenciana / Murcia
        if ($lat >= 37.5 && $lat <= 41.0 && $lon >= -0.5) return 'Levante';

        // Sistema Ibérico y Aragón interior (Teruel, Maestrazgo, Gúdar…)
        if ($lat >= 39.0 && $lat < 42.0 && $lon >= -2.5 && $lon <= 0.5) return 'Sistema_Iberico';

        return 'Aragon'; // fallback para zona interior de la Meseta
    }

    private function detectZoneFromQuery(string $query): ?string {
        $patterns = [
            // ── Zonas de la BD ───────────────────────────────────────────────
            'Euskadi'          => ['euskadi','país vasco','pais vasco','gipuzkoa','bizkaia','donostia','san sebastian'],
            'Western_Pyrenees' => ['pirineo occidental','navarra','iruña','pamplona','roncal','isaba','roncesvalles','ochagavia','ansó','anso','hecho','zuriza','selva de oza'],
            'Aragon'           => ['aragón','aragon','huesca','jaca','ordesa','benasque','formigal','candanchú','candanchu','astún','astun','panticosa','sallent','tena','valle de tena','bielsa','broto','torla'],
            'Central_Pyrenees' => ['cataluña','cataluna','lleida','sort','pallars','aigüestortes','aiguestortes','andorra','aran','val d\'aran','puigcerda','espot'],
            // ── Nuevas zonas ─────────────────────────────────────────────────
            'Cantabria'        => ['cantabria','liébana','picos de europa','picos','tresviso','santander','asturias','ponga','somiedo'],
            'Galicia'          => ['galicia','galiza','courel','ancares','xurés','ourense','lugo','pontevedra','a coruña'],
            'Sistema_Central'  => ['sistema central','guadarrama','gredos','somosierra','peñalara','madrid sierra','segovia','ávila','avila','la pedriza'],
            'Sistema_Iberico'  => ['sistema ibérico','sistema iberico','teruel','maestrazgo','gúdar','gudar','javalambre','moncayo','penyagolosa','desierto de las palmas'],
            'Sierra_Nevada'    => ['sierra nevada','mulhacén','mulhacen','veleta','granada sierra','solynieve','güéjar','guéjar'],
            'Andalucia'        => ['andalucía','andalucia','sierra de las nieves','cazorla','segura','las villas','málaga','malaga','sevilla','córdoba','cordoba','cádiz','cadiz','huelva','almería','almeria','jaén','jaen'],
            'Levante'          => ['levante','comunidad valenciana','valencia','castellón','castellon','alicante','murcia','benicàssim','calpe','ifach','montgó','montgo'],
            'Canarias'         => ['canarias','canaria','tenerife','gran canaria','la palma','lanzarote','fuerteventura','la gomera','el hierro','teide'],
            'Baleares'         => ['baleares','mallorca','menorca','ibiza','formentera','tramuntana'],
            'Francia_Sur'      => ['france','francia','occitanie','occitania','toulouse','pirineos franceses','haute-garonne','ariège','ariege','pyrenees'],
        ];
        $q = mb_strtolower($query, 'UTF-8');
        foreach ($patterns as $zone => $kws) {
            foreach ($kws as $kw) {
                if (mb_strpos($q, $kw) !== false) return $zone;
            }
        }
        return null;
    }

    // =========================================================================
    // RESUMEN DE DATOS METEOROLÓGICOS (reduce tokens a Sonnet)
    // =========================================================================

    /**
     * Convierte la respuesta de WeatherService en ~150 tokens de texto plano.
     * Usa los nombres de campo reales que devuelve parseCurrentFromHourly()
     * y getMountainConditions().
     */
    private function summarizeWeatherForPrompt(array $data): string {
        $lines = [];

        // Campos de parseCurrentFromHourly()
        $temp = $data['temp'] ?? null;
        if ($temp !== null)                     $lines[] = "Temperatura: {$temp}°C";
        if (isset($data['feels_like']))         $lines[] = "Sensación: {$data['feels_like']}°C";
        if (isset($data['description']))        $lines[] = "Condiciones: {$data['description']}";
        if (isset($data['windspeed']))          $lines[] = "Viento: {$data['windspeed']} km/h";
        if (isset($data['windgusts']))          $lines[] = "Rachas: {$data['windgusts']} km/h";
        if (isset($data['precipitation']) && $data['precipitation'] > 0)
                                                $lines[] = "Precipitación: {$data['precipitation']} mm";
        if (isset($data['snowfall']) && $data['snowfall'] > 0)
                                                $lines[] = "Nevada: {$data['snowfall']} cm";
        if (isset($data['cloudcover']))         $lines[] = "Nubosidad: {$data['cloudcover']}%";

        // Campos de getMountainConditions() (añadidos al array anterior)
        // NOTA: snow_level / iso_zero = isoterma 0°C de Open-Meteo (≠ cota de nieve real)
        $isoZeroVal = $data['iso_zero'] ?? $data['snow_level'] ?? null;
        if ($isoZeroVal)                        $lines[] = "Isocero (cota 0°C): {$isoZeroVal} m";

        // ⚠️ NO mostrar mountain['avalanche_risk']: es una fórmula interna estimada,
        // NO datos oficiales. El riesgo de aludes solo lo proporciona MountainConditionsService
        // a partir de AEMET BPA, Météo-France BRA o EAWS.
        $mountain = $data['mountain'] ?? [];
        if (($mountain['visibility_status'] ?? '') === 'poor') {
            $lines[] = "⚠️ Visibilidad reducida (niebla)";
        }

        return implode("\n", $lines) ?: 'Datos no disponibles';
    }

    /**
     * Resume un pronóstico de parseDaily() en texto compacto.
     * getForecast() devuelve un array plano de día-objetos (no anidado en 'daily').
     */
    private function summarizeForecastForPrompt(array $forecast, int $daysAhead): string {
        if (empty($forecast)) return 'Pronóstico no disponible';

        // getForecast() → array plano; si viniera anidado bajo 'daily', desempaquetar
        $days  = isset($forecast[0]) ? $forecast : ($forecast['daily'] ?? $forecast);
        $lines = [];
        $limit = min($daysAhead + 1, count($days), 7);

        for ($i = 0; $i < $limit; $i++) {
            $day = $days[$i] ?? null;
            if (!is_array($day)) continue;

            $date  = $day['date']          ?? "Día " . ($i + 1);
            $tmax  = $day['temp_max']      ?? '?';
            $tmin  = $day['temp_min']      ?? '?';
            $rain  = $day['precipitation'] ?? 0;
            $wind  = $day['windspeed_max'] ?? '?';
            $desc  = $day['description']   ?? '';
            $safe  = ($day['safe_for_hiking'] ?? true) ? '' : ' ⚠️';

            $lines[] = "{$date}: {$tmin}–{$tmax}°C, {$desc}, lluvia {$rain}mm, viento {$wind}km/h{$safe}";
        }
        return implode("\n", $lines) ?: 'Pronóstico no disponible';
    }

    // =========================================================================
    // AUDIT LOG
    // =========================================================================

    /**
     * Escribe una entrada legible en el log de auditoría de Kahir.
     * Una línea por mensaje — permite seguir exactamente qué decidió el pipeline.
     * Nunca lanza excepciones para no interferir con el flujo principal.
     */
    private function writeAuditLog(array $data): void
    {
        try {
            $logDir  = defined('LOG_PATH') ? LOG_PATH : __DIR__ . '/../../logs';
            $logFile = $logDir . '/kahir_audit_' . date('Y-m-d') . '.log';

            if (!is_dir($logDir)) mkdir($logDir, 0755, true);

            // ── Línea de cabecera ──────────────────────────────────────────
            $ts    = date('H:i:s');
            $ms    = $data['processing_ms'] ?? '?';
            $uid   = $data['user_id']   ?? '?';
            $sid   = $data['session_id'] ?? 0;
            $query = mb_substr($data['query'] ?? '', 0, 120);

            // ── Zona ──────────────────────────────────────────────────────
            $zone = $data['zone'] ?? 'null';
            if ($data['zone_inherited'] ?? false) $zone .= ' (heredada)';

            // ── Detección ─────────────────────────────────────────────────
            $detect = strtoupper($data['detection'] ?? 'HAIKU');
            if (!empty($data['followup_subtype'])) {
                $detect .= " → followup:{$data['followup_subtype']}";
            }

            // ── Extras ────────────────────────────────────────────────────
            $extras = [];
            if (($data['days_ahead'] ?? 0) > 0)       $extras[] = "days_ahead={$data['days_ahead']}";
            if ($data['weather_needed'] ?? false)       $extras[] = 'weather✓';
            if (!empty($data['activity']))              $extras[] = "act={$data['activity']}";
            if (!empty($data['difficulty']))            $extras[] = "diff={$data['difficulty']}";
            $extrasStr = $extras ? ' | ' . implode(' ', $extras) : '';

            // ── Respuesta ─────────────────────────────────────────────────
            $rtype  = $data['response_type'] ?? '?';
            $routes = ($data['route_count'] ?? 0) > 0 ? " ({$data['route_count']} rutas)" : '';
            $err    = !empty($data['error']) ? "\n  ⛔ ERROR: {$data['error']}" : '';

            // ── Contexto de entrada ───────────────────────────────────────
            $ctxIn = $data['ctx_in'] ?? [];
            $ctxStr = '';
            if (!empty($ctxIn['last_zone']))    $ctxStr .= "last_zone={$ctxIn['last_zone']} ";
            if (!empty($ctxIn['last_intent']))  $ctxStr .= "last_intent={$ctxIn['last_intent']} ";
            if (($ctxIn['active_count'] ?? 0) > 0) $ctxStr .= "active_routes={$ctxIn['active_count']} ";
            if (!empty($ctxIn['awaiting']))     $ctxStr .= "awaiting={$ctxIn['awaiting']}";

            $line = implode("\n", array_filter([
                "─── [{$ts}] {$ms}ms | user:{$uid} | session:{$sid}",
                "  Q: \"{$query}\"",
                "  DETECT : {$detect}",
                "  INTENT : {$data['intent']} | zone:{$zone}{$extrasStr}",
                "  HANDLER: {$rtype}{$routes}",
                $ctxStr ? "  CTX_IN : {$ctxStr}" : null,
                $err,
            ])) . "\n";

            file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        } catch (\Throwable) {
            // Silenciar — el log nunca debe romper la experiencia del usuario
        }
    }

    // =========================================================================
    // HELPERS CLAUDE
    // =========================================================================

    /**
     * Llama a Sonnet con el system prompt adecuado al intent.
     */
    private function askClaude(
        string $userPrompt,
        array  $history,
        array  $prefs,
        string $intent = 'general'
    ): string {
        $context = array_map(fn($m) => [
            'role'    => $m['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => $m['content'],
        ], array_slice($history, -4));   // últimos 4 mensajes

        $response = $this->claude->processQuery($userPrompt, $context, $intent);
        return $response['content']
            ?? (is_array($response) ? json_encode($response, JSON_UNESCAPED_UNICODE) : (string)$response);
    }

    /**
     * Construye una respuesta de clarificación estándar.
     */
    private function makeClarificationResponse(string $field, string $question): array {
        return [
            'type'                   => 'clarification_request',
            'content'                => $question,
            'questions'              => [$question],
            'awaiting_clarification' => $field,
        ];
    }

    private function getFirstPreferredActivity(array $prefs): ?string {
        $acts = $prefs['preferred_activities'] ?? null;
        if (empty($acts)) return null;
        $list = is_array($acts) ? $acts : explode(',', $acts);
        return trim($list[0]) ?: null;
    }

    // =========================================================================
    // BASE DE DATOS
    // =========================================================================

    private function getUserData(string $userId): array {
        try {
            $stmt = $this->conn->prepare(
                "SELECT id, username, rol FROM usuarios WHERE username = ? OR id = ? LIMIT 1"
            );
            $stmt->bind_param("ss", $userId, $userId);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc() ?? [];
        } catch (\Exception) {
            return [];
        }
    }

    private function saveInteraction(string $userId, string $query, array $response, string $intent): void {
        try {
            $numericId = $this->resolveUserId($userId);
            if (!$numericId) return;
            $stmt = $this->conn->prepare(
                "INSERT INTO user_interactions (usuario_id, ruta_id, tipo)
                 VALUES (?, NULL, ?)"
            );
            $stmt->bind_param("is", $numericId, $intent);
            $stmt->execute();
        } catch (\Exception $e) {
            $this->logger->error('saveInteraction error', ['error' => $e->getMessage()]);
        }
    }

    private function saveChatMessage(int $sessionId, string $role, string $content): void {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO chat_messages (session_id, rol, contenido) VALUES (?, ?, ?)"
            );
            $stmt->bind_param("iss", $sessionId, $role, $content);
            $stmt->execute();
        } catch (\Exception $e) {
            $this->logger->warning('saveChatMessage error', ['error' => $e->getMessage()]);
        }
    }

    public function getOrCreateChatSession(int $userId): int {
        $stmt = $this->conn->prepare(
            "SELECT id FROM chat_sessions
             WHERE usuario_id = ? AND activa = 1
               AND ultimo_mensaje > DATE_SUB(NOW(), INTERVAL 2 HOUR)
             ORDER BY ultimo_mensaje DESC LIMIT 1"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) return (int)$row['id'];

        $stmt = $this->conn->prepare("INSERT INTO chat_sessions (usuario_id) VALUES (?)");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return (int)$this->conn->insert_id;
    }

    public function getChatHistory(int $sessionId, int $limit = 10): array {
        $stmt = $this->conn->prepare(
            "SELECT rol AS role, contenido AS content, fecha
             FROM chat_messages
             WHERE session_id = ?
             ORDER BY fecha DESC
             LIMIT ?"
        );
        $stmt->bind_param("ii", $sessionId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return array_reverse($rows);
    }

    public function saveChatExchange(int $sessionId, string $userMsg, string $assistantMsg): void {
        $this->saveChatMessage($sessionId, 'user',      $userMsg);
        $this->saveChatMessage($sessionId, 'assistant', $assistantMsg);
        try {
            $stmt = $this->conn->prepare(
                "UPDATE chat_sessions SET ultimo_mensaje = NOW() WHERE id = ?"
            );
            $stmt->bind_param("i", $sessionId);
            $stmt->execute();
        } catch (\Exception) { /* no fatal */ }
    }

    private function resolveUserId(string $userIdentifier): ?int {
        if (is_numeric($userIdentifier)) return (int)$userIdentifier;
        try {
            $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $userIdentifier);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            return $row ? (int)$row['id'] : null;
        } catch (\Exception) {
            return null;
        }
    }

    // =========================================================================
    // PREFERENCIAS Y FORMATO
    // =========================================================================

    private function maybeUpdatePreferencesFromChat(string $userId, string $query, array $currentPrefs): void {
        $numericId = $this->resolveUserId($userId);
        if (!$numericId) return;

        $updates = [];
        $actMap = ['senderismo|hiking' => 'hiking', 'ski touring|esquí' => 'ski_touring', 'escalada' => 'climbing'];
        foreach ($actMap as $pattern => $value) {
            if (preg_match("/($pattern)/iu", $query)) { $updates['actividades_preferidas'] = json_encode([$value]); break; }
        }
        if (preg_match('/soy (principiante|intermedio|avanzado|experto)/iu', $query, $m)) {
            $map = ['principiante' => 'beginner', 'intermedio' => 'intermediate', 'avanzado' => 'advanced', 'experto' => 'advanced'];
            $updates['nivel_experiencia'] = $map[strtolower($m[1])] ?? null;
        }
        if (empty($updates)) return;

        try {
            $sets  = implode(', ', array_map(fn($k) => "$k = ?", array_keys($updates)));
            $vals  = array_values($updates);
            $vals[] = $numericId;
            $types = str_repeat('s', count($updates)) . 'i';
            $stmt = $this->conn->prepare(
                "UPDATE user_preferences SET $sets, ultima_actualizacion = NOW() WHERE usuario_id = ?"
            );
            $stmt->bind_param($types, ...$vals);
            $stmt->execute();
        } catch (\Exception $e) {
            $this->logger->error('maybeUpdatePreferences error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Comprueba si la zona mencionada por el usuario es claramente un destino fuera
     * de la cobertura de Kahir (España + sur de Francia).
     * Evita que Nominatim resuelva "Los Alpes" a un municipio español homónimo.
     */
    /**
     * Fallback de última instancia: devuelve las zonas con más rutas publicadas
     * en la BD para la actividad dada. Garantiza que usuarios sin historial
     * geográfico siempre reciben rutas en la recomendación personalizada.
     */
    private function inferZonesFromPopularRoutes(string $activity): array
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT ubicacion, COUNT(*) AS cnt
                 FROM rutas
                 WHERE estado = 'publicada' AND tipo_ruta = ?
                 GROUP BY ubicacion
                 ORDER BY cnt DESC
                 LIMIT 2"
            );
            $stmt->bind_param("s", $activity);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $enumToZone = [
                'Western_Pyrenees' => 'Western_Pyrenees',
                'Central_Pyrenees' => 'Central_Pyrenees',
                'Eastern_Pyrenees' => 'Central_Pyrenees',
                'Other'            => 'Aragon',  // "Other" → Aragon como zona neutra
            ];

            $zones = [];
            foreach ($rows as $row) {
                $mapped = $enumToZone[$row['ubicacion']] ?? 'Aragon';
                if (!in_array($mapped, $zones, true)) {
                    $zones[] = $mapped;
                }
            }
            // Si la consulta no devuelve nada → Aragon tiene las rutas más variadas
            return $zones ?: ['Aragon'];
        } catch (\Exception) {
            return ['Aragon'];
        }
    }

    private function isZoneOutOfCoverage(?string $zone): bool
    {
        if (empty($zone)) return false;

        $q = mb_strtolower($zone, 'UTF-8');
        $q = strtr($q, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
                         'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
                         'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u','ñ'=>'n']);

        $outOfScope = [
            // Cordilleras europeas no cubiertas
            'alpes','alps','alpi','alpen',
            'dolomitas','dolomites','dolomiti',
            'carpatos','carpathians','tatras',
            'apeninos','apennines',
            // Países fuera de cobertura
            'italia','italie','italy',
            'suiza','suisse','schweiz','switzerland',
            'austria','osterreich',
            'alemania','allemagne','germany','deutschland',
            'belgica','belgique','belgium',
            'holanda','nederland','netherlands',
            'escocia','scotland',
            'irlanda','ireland',
            'noruega','norge','norway',
            'suecia','sverige','sweden',
            'finlandia','finland',
            'polonia','poland',
            'grecia','greece',
            'turquia','turkey',
            'rumania','romania',
            'croacia','croatia',
            // Otros continentes/rangos
            'himalaya','himalayas',
            'andes','cordillera de los andes',
            'atlas','mont atlas',
            'rocosas','rockies',
            'kilimanjaro','mont blanc',
            // Países extra-europeos
            'marruecos','maroc','morocco',
            'argelia','algeria',
            'tunez','tunisie','tunisia',
        ];

        foreach ($outOfScope as $term) {
            // Coincidencia de palabra completa (evita "Andorra" → "and")
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/u', $q)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Infiere zonas preferidas del usuario desde user_zones (tabla actualizada automáticamente
     * cada vez que el usuario busca rutas en el chat).
     * Devuelve array de zone-keys ordenados por frecuencia de visita.
     */
    private function inferZonesFromUserZones(int $userId): array
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT lat, lng FROM user_zones
                 WHERE usuario_id = ?
                 ORDER BY frecuencia DESC, ultima_vez DESC
                 LIMIT 5"
            );
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $zones = [];
            foreach ($rows as $row) {
                $zone = $this->coordsToDbZone((float)$row['lat'], (float)$row['lng']);
                if (!in_array($zone, $zones, true)) {
                    $zones[] = $zone;
                }
            }
            return $zones;
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Infiere zonas preferidas a partir de las rutas marcadas como favoritas.
     * Mapea el ENUM ubicacion de rutas a las zone-keys del sistema.
     */
    private function inferZonesFromFavorites(int $userId): array
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT r.ubicacion, COUNT(*) AS cnt
                 FROM user_favorites uf
                 JOIN rutas r ON r.id = uf.ruta_id
                 WHERE uf.usuario_id = ?
                 GROUP BY r.ubicacion
                 ORDER BY cnt DESC
                 LIMIT 3"
            );
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Mapa de ENUM ubicacion → zone-key del sistema
            $enumToZone = [
                'Western_Pyrenees' => 'Western_Pyrenees',
                'Central_Pyrenees' => 'Central_Pyrenees',
                'Eastern_Pyrenees' => 'Central_Pyrenees',  // Eastern → Central como aproximación
                'Other'            => null,                  // "Other" no es geocodificable
            ];

            $zones = [];
            foreach ($rows as $row) {
                $mapped = $enumToZone[$row['ubicacion']] ?? null;
                if ($mapped && !in_array($mapped, $zones, true)) {
                    $zones[] = $mapped;
                }
            }
            return $zones;
        } catch (\Exception) {
            return [];
        }
    }

    private function formatRoutesForPrompt(array $results): string {
        $lines = [];
        foreach (array_slice($results, 0, 5) as $i => $r) {
            $route   = $r['route'];
            $lines[] = sprintf(
                "%d. **%s** | %s | %s | %.1f km | %d h | +%d m | %s",
                $i + 1,
                $route['title']      ?? ($route['titulo'] ?? ($route['nombre'] ?? 'Sin nombre')),
                $route['difficulty'] ?? ($route['dificultad'] ?? '-'),
                $route['tipo_ruta']  ?? '-',
                (float)($route['distance'] ?? ($route['distancia_km'] ?? ($route['length'] ?? 0))),
                (int)($route['duration']   ?? ($route['duracion_horas'] ?? 0)),
                (int)($route['elevation']  ?? ($route['elevacion_ganada'] ?? 0)),
                $route['location']   ?? ($route['ubicacion'] ?? '-')
            );
        }
        return implode("\n", $lines);
    }

    /**
     * Extrae solo los campos que Sonnet necesita para generar la frase intro.
     * Evita pasar el objeto completo de BD con todos sus campos (imagen, coords, etc.)
     */
    private function trimRouteForPrompt(array $route): array {
        return [
            'title'      => $route['title']      ?? ($route['titulo'] ?? ($route['nombre'] ?? 'Sin nombre')),
            'difficulty' => $route['difficulty']  ?? '-',
            'tipo_ruta'  => $route['tipo_ruta']   ?? '-',
            'distance'   => (float)($route['length'] ?? ($route['distance'] ?? 0)),
            'duration'   => (int)($route['duration']  ?? 0),
            'elevation'  => (int)($route['elevation']  ?? 0),
            'location'   => $route['location']    ?? '-',
        ];
    }

    // =========================================================================
    // ROUTE FOLLOWUP — detección pre-Haiku + handlers
    // =========================================================================

    /**
     * Detección pre-Haiku de intents de seguimiento sobre rutas mostradas.
     * Se llama ANTES de Haiku para ahorrar tokens cuando hay active_routes en sesión.
     * Devuelve array con followup_subtype + ordinal_index, o null si no aplica.
     */
    private function detectFollowupIntent(string $query, array $ctx): ?array
    {
        // Solo tiene sentido si hay rutas activas en sesión
        if (empty($ctx['active_routes'])) return null;

        $q = mb_strtolower($query, 'UTF-8');
        $q = strtr($q, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n',
        ]);

        // ── Señales de "intent nuevo": abortar followup aunque haya active_routes ──
        // Si el usuario claramente hace una búsqueda nueva, no interceptamos el mensaje.
        $newIntentSignals = [
            '/\b(busca|buscar|encuentra|encontrar)\b/u',
            '/\brec[ou]miend[ao]?\b/u',
            '/\bsorpr[eé]nd[eé]?me\b/u',
            '/\bqu[eé]\s+(hay|puedo|se\s+puede)\b/u',
            '/\bmuéstrame\s+rutas\b/u',
            '/\bexcursion(es)?\s+(en|por|cerca|para)\b/u',
            '/\brutas?\s+(en|por|cerca|de|para)\s+[a-z]/u',
            '/\b(senderismo|escalada|ski|esqui|btt|cicloturismo)\s+(en|por|cerca)\b/u',
            '/\bcu[aá]l\s+es\s+el\s+mejor\b/u',
        ];
        foreach ($newIntentSignals as $pat) {
            if (preg_match($pat, $q)) return null;
        }

        // ── Patrones ordinales / referencia a posición ─────────────────────
        $ordinalPatterns = [
            '/\b(primer[ao]?|uno?|1\.?\s*a?|1er)\b/u',          // idx 0
            '/\b(segund[ao]?|dos|2\.?\s*a?)\b/u',               // idx 1
            '/\b(tercer[ao]?|tres|3\.?\s*a?)\b/u',              // idx 2
            '/\b(cuart[ao]?|cuatro|4\.?\s*a?)\b/u',             // idx 3
            '/\b(quint[ao]?|cinco|5\.?\s*a?)\b/u',              // idx 4
            '/\b(esa|esta|aquella|dicha)\s+ruta\b/u',           // ref contextual
            '/\bopc[io]on\s*(1|2|3|4|5|uno|dos|tres|cuatro|cinco)\b/u',
            '/\bruta\s*(1|2|3|4|5)\b/u',
            '/\bcuentame\s*(mas|de ella|algo mas)\b/u',
            '/\bdime\s*(mas|algo mas)\b/u',
            '/\bmas\s*(info|informacion|detalles|sobre ella)\b/u',
            '/\b(detallame|describeme|descr[ií]beme)\b/u',
        ];

        // ── Patrones de conjunto / comparativa ────────────────────────────
        $conjuntoPatterns = [
            '/\b(todas|el conjunto|ver todas|muestra(me)?\s+todas)\b/u',
            '/\bcompar(a|ar|alas|arlas)\b/u',
            '/\b(resumen|res[uú]meme|resumelas|pon(me)?\s+en\s+resumen)\b/u',
            '/\bpon(me|las)?\s+(todas|el\s+resumen|en\s+conjunto)\b/u',
            '/\bque\s+(diferencia|diferencias)\b/u',
            // Comparativos directos: "¿cuál tiene menos desnivel?", "¿cuál es la más corta?"
            '/¿?cu[aá]l.{0,25}(m[aá]s|menos|mayor|menor)/ui',
            '/¿?cu[aá]l.{0,15}(tiene|es|resulta|queda|ser[ií]a)/ui',
            '/(m[aá]s\s+(corta?|larga?|f[aá]cil|dif[íi]cil|alta?|baja?|suave))/ui',
            '/(menos\s+(desnivel|distancia|dificultad|km|horas?|metros?))/ui',
            '/entre\s+(ellas|ellos|todas|ambas|las\s+(dos|tres|cuatro|cinco))/ui',
        ];

        // ── Patrones de filtro ─────────────────────────────────────────────
        $filterPatterns = [
            '/\b(menos de|mas de|maximo|minimo|no\s+mas\s+de|entre)\s+\d+/u',
            '/\b(facil(es)?|sencill[ao]|para\s+principiantes?)\b/u',
            '/\b(corta(s)?|poca\s+distancia|distancia\s+corta)\b/u',
            '/\b(larga(s)?|mucha\s+distancia)\b/u',
            '/\b(sin\s+(mucho\s+)?desnivel|llana?s?|poca\s+pendiente)\b/u',
            '/\b(desnivel\s+alto|mucho\s+desnivel|exigente)\b/u',
            '/\b\d+\s*(km|kil[oo]metros?|horas?|metros?\s+de\s+desnivel)\b/u',
            '/\b(filtra|quita|descarta|solo\s+las?|s[oo]lo\s+las?)\b/u',
        ];

        $isOrdinal  = false;
        $isConjunto = false;
        $isFilter   = false;
        $ordinalIdx = null;

        foreach ($ordinalPatterns as $i => $pat) {
            if (preg_match($pat, $q)) {
                $isOrdinal = true;
                if ($i < 5) $ordinalIdx = $i;   // índice 0-based si es posicional
                break;
            }
        }

        if (!$isOrdinal) {
            foreach ($conjuntoPatterns as $pat) {
                if (preg_match($pat, $q)) { $isConjunto = true; break; }
            }
        }

        if (!$isOrdinal && !$isConjunto) {
            foreach ($filterPatterns as $pat) {
                if (preg_match($pat, $q)) { $isFilter = true; break; }
            }
        }

        if (!$isOrdinal && !$isConjunto && !$isFilter) return null;

        $subtype = $isOrdinal ? 'describe' : ($isConjunto ? 'conjunto' : 'filter');

        return [
            'intent'           => 'route_followup',
            'followup_subtype' => $subtype,
            'ordinal_index'    => $ordinalIdx,
        ];
    }

    /**
     * Handler principal de route_followup.
     * Despacha a describe, conjunto o filter según el subtipo detectado.
     */
    private function handleRouteFollowup(
        string $query,
        array  $prefs,
        array  $history,
        array  $extracted,
        array  $ctx
    ): array {
        $activeRoutes = $ctx['active_routes'] ?? [];
        $lastRoutes   = $ctx['last_routes']   ?? [];

        if (empty($activeRoutes)) {
            return $this->handleGeneralFollowupNoRoutes($query, $prefs, $history);
        }

        $subtype = $extracted['followup_subtype'] ?? 'describe';

        return match ($subtype) {
            'conjunto' => $this->handleFollowupConjunto($query, $prefs, $history, $lastRoutes, $activeRoutes),
            'filter'   => $this->handleFollowupFilter($query, $prefs, $history, $extracted, $ctx),
            default    => $this->handleFollowupDescribe($query, $prefs, $history, $extracted, $lastRoutes, $activeRoutes),
        };
    }

    /**
     * Describe una ruta específica (ordinal o referencia contextual).
     */
    private function handleFollowupDescribe(
        string $query,
        array  $prefs,
        array  $history,
        array  $extracted,
        array  $lastRoutes,
        array  $activeRoutes
    ): array {
        $idx   = $extracted['ordinal_index'] ?? null;
        $route = null;

        // Intentar localizar la ruta por índice posicional
        if ($idx !== null && isset($lastRoutes[$idx])) {
            $route = $lastRoutes[$idx];
        } elseif (!empty($lastRoutes)) {
            // Sin índice claro → buscar número en el texto
            $numMap = ['1'=>0,'uno'=>0,'2'=>1,'dos'=>1,'3'=>2,'tres'=>2,
                       '4'=>3,'cuatro'=>3,'5'=>4,'cinco'=>4];
            if (preg_match('/\b([1-5]|uno|dos|tres|cuatro|cinco)\b/ui', $query, $m)) {
                $k = strtolower($m[1]);
                if (isset($numMap[$k], $lastRoutes[$numMap[$k]])) {
                    $route = $lastRoutes[$numMap[$k]];
                }
            }
            if ($route === null) $route = $lastRoutes[0];   // primera por defecto
        }

        // Si no tenemos el objeto en sesión → recuperar desde BD
        if ($route === null && !empty($activeRoutes)) {
            $targetId = ($idx !== null && isset($activeRoutes[$idx]))
                ? $activeRoutes[$idx]
                : $activeRoutes[0];
            $route = $this->fetchFullRouteById($targetId);
        }

        if ($route === null) {
            return ['type' => 'general',
                    'content' => 'No he encontrado los datos de esa ruta. ¿Puedes especificar cuál te interesa?'];
        }

        $block = $this->buildRouteDataBlock($route, 'full');

        $text = $this->askClaude(
            "El usuario quiere más detalles sobre esta ruta:\n{$block}\n\n" .
            "Pregunta del usuario: \"{$query}\"\n\n" .
            "INSTRUCCIÓN CRÍTICA: Describe ÚNICAMENTE los datos que aparecen en el bloque anterior. " .
            "NO inventes información adicional (acceso exacto, puntos de paso, albergues, etc.) " .
            "que no esté explícitamente en los datos. " .
            "Si el usuario pregunta algo que no está en los datos, dilo con naturalidad.",
            $history, $prefs, 'route_request'
        );

        return [
            'type'    => 'route_detail',
            'content' => $text,
            'route'   => $route,
        ];
    }

    /**
     * Presenta todas las rutas activas de forma comparativa/resumen.
     */
    private function handleFollowupConjunto(
        string $query,
        array  $prefs,
        array  $history,
        array  $lastRoutes,
        array  $activeRoutes
    ): array {
        // Si no hay objetos en sesión → recuperar desde BD
        if (empty($lastRoutes) && !empty($activeRoutes)) {
            $lastRoutes = $this->fetchRoutesById($activeRoutes);
        }

        if (empty($lastRoutes)) {
            return ['type' => 'general',
                    'content' => 'No tengo los datos de las rutas anteriores disponibles. ¿Quieres que busque nuevas rutas?'];
        }

        $blocks = [];
        foreach (array_slice($lastRoutes, 0, 5) as $i => $route) {
            $blocks[] = "Ruta " . ($i + 1) . ":\n" . $this->buildRouteDataBlock($route, 'compact');
        }
        $allBlocks = implode("\n\n", $blocks);

        $text = $this->askClaude(
            "El usuario quiere ver el resumen o comparativa de las rutas que le mostré:\n\n{$allBlocks}\n\n" .
            "Pregunta del usuario: \"{$query}\"\n\n" .
            "INSTRUCCIÓN CRÍTICA: Usa ÚNICAMENTE los datos del bloque anterior. No inventes datos. " .
            "Presenta las rutas de forma comparativa y útil para que el usuario pueda decidir.",
            $history, $prefs, 'route_request'
        );

        return [
            'type'    => 'route_suggestions',
            'content' => $text,
            'routes'  => $lastRoutes,
        ];
    }

    /**
     * Filtra rutas del pool last_candidate_ids con criterios adicionales del usuario.
     */
    private function handleFollowupFilter(
        string $query,
        array  $prefs,
        array  $history,
        array  $extracted,
        array  $ctx
    ): array {
        $candidateIds = $ctx['last_candidate_ids'] ?? [];

        // Sin pool de candidatos → usar las rutas activas directamente como conjunto
        if (empty($candidateIds)) {
            return $this->handleFollowupConjunto(
                $query, $prefs, $history,
                $ctx['last_routes']   ?? [],
                $ctx['active_routes'] ?? []
            );
        }

        $filters = $this->parseFilterCriteria($query);
        $routes  = $this->fetchRoutesWithFilter($candidateIds, $filters, MAX_ROUTE_SUGGESTIONS);

        if (empty($routes)) {
            $text = $this->askClaude(
                "El usuario aplicó un filtro (\"{$query}\") a las rutas anteriores pero no quedó ninguna. " .
                "Explícalo brevemente y sugiere relajar el filtro o buscar de nuevo.",
                $history, $prefs, 'route_request'
            );
            return ['type' => 'general', 'content' => $text];
        }

        $routeSummary = $this->formatRoutesForPrompt(
            array_map(fn($r) => ['route' => $r], $routes)
        );

        $text = $this->askClaude(
            "El usuario aplicó el filtro: \"{$query}\"\n\n" .
            "Rutas que cumplen los criterios:\n{$routeSummary}\n\n" .
            "INSTRUCCIÓN CRÍTICA: Usa solo los datos proporcionados. Menciona brevemente qué filtro " .
            "aplicaste y presenta las rutas resultantes de forma concisa.",
            $history, $prefs, 'route_request'
        );

        return [
            'type'    => 'route_suggestions',
            'content' => $text,
            'routes'  => $routes,
        ];
    }

    /**
     * Fallback cuando el usuario hace referencia a rutas pero no hay active_routes en sesión.
     */
    private function handleGeneralFollowupNoRoutes(string $query, array $prefs, array $history): array
    {
        $text = $this->askClaude(
            "El usuario hace referencia a rutas anteriores: \"{$query}\"\n" .
            "No hay rutas en el contexto actual de la sesión. " .
            "Pídele amablemente que especifique qué zona o tipo de ruta busca.",
            $history, $prefs, 'general'
        );
        return ['type' => 'general', 'content' => $text];
    }

    /**
     * Extrae criterios de filtro estructurados desde texto libre del usuario.
     */
    private function parseFilterCriteria(string $query): array
    {
        $q       = mb_strtolower($query, 'UTF-8');
        $q       = strtr($q, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        $filters = [];

        // Distancia máxima
        if (preg_match('/(?:menos\s+de|maximo|no\s+mas\s+de)\s+(\d+(?:[.,]\d+)?)\s*km/u', $q, $m)) {
            $filters['max_distance'] = (float)str_replace(',', '.', $m[1]);
        }
        // Distancia mínima
        if (preg_match('/(?:mas\s+de|minimo)\s+(\d+(?:[.,]\d+)?)\s*km/u', $q, $m)) {
            $filters['min_distance'] = (float)str_replace(',', '.', $m[1]);
        }
        // Duración máxima
        if (preg_match('/(?:menos\s+de|maximo|no\s+mas\s+de)\s+(\d+(?:[.,]\d+)?)\s*h(?:oras?)?/u', $q, $m)) {
            $filters['max_duration'] = (float)str_replace(',', '.', $m[1]);
        }
        // Desnivel máximo explícito
        if (preg_match('/(?:menos\s+de|maximo)\s+(\d+)\s*m(?:etros?)?\s*(?:de\s+)?desnivel/u', $q, $m)) {
            $filters['max_elevation'] = (int)$m[1];
        }
        // Desnivel bajo implícito
        if (preg_match('/\b(?:sin\s+(?:mucho\s+)?desnivel|llana?s?|poca\s+pendiente)\b/u', $q)) {
            $filters['max_elevation'] = $filters['max_elevation'] ?? 400;
        }
        // Dificultad
        if (preg_match('/\b(?:facil(es)?|sencill[ao]|principiante)\b/u', $q)) {
            $filters['difficulty'] = 'beginner';
        } elseif (preg_match('/\b(?:intermedi[ao]|moderada?)\b/u', $q)) {
            $filters['difficulty'] = 'intermediate';
        } elseif (preg_match('/\b(?:dif[ii]cil|avanzad[ao]|exigente)\b/u', $q)) {
            $filters['difficulty'] = 'advanced';
        }
        // Corta / larga sin número
        if (empty($filters['max_distance']) && preg_match('/\b(?:corta|poca\s+distancia)\b/u', $q)) {
            $filters['max_distance'] = 10.0;
        }
        if (empty($filters['min_distance']) && preg_match('/\b(?:larga|mucha\s+distancia)\b/u', $q)) {
            $filters['min_distance'] = 15.0;
        }

        return $filters;
    }

    /**
     * Construye un bloque de datos de ruta (compact o full) para el prompt de Sonnet.
     */
    private function buildRouteDataBlock(array $route, string $mode = 'compact'): string
    {
        $name = $route['title']      ?? ($route['titulo'] ?? ($route['nombre'] ?? 'Sin nombre'));
        $diff = $route['difficulty'] ?? ($route['dificultad']      ?? '-');
        $dist = (float)($route['distance'] ?? ($route['distancia_km'] ?? ($route['length'] ?? 0)));
        $dur  = (float)($route['duration'] ?? ($route['duracion_horas'] ?? 0));
        $elev = (int)($route['elevation']  ?? ($route['elevacion_ganada'] ?? 0));
        $loc  = $route['location']   ?? ($route['ubicacion']       ?? '-');
        $tipo = $route['tipo_ruta']  ?? '-';

        $compact = "Nombre: {$name}\nDificultad: {$diff}\n" .
                   "Distancia: {$dist} km | Duración: {$dur} h | Desnivel: +{$elev} m\n" .
                   "Ubicación: {$loc} | Tipo: {$tipo}";

        if ($mode === 'compact') return $compact;

        // Modo full: añadir descripción truncada
        $desc     = $route['description'] ?? ($route['descripcion'] ?? '');
        $startLat = $route['startLat']    ?? ($route['start_lat']  ?? ($route['latitud_inicio']  ?? null));
        $startLon = $route['startLng']    ?? ($route['start_lng']  ?? ($route['longitud_inicio'] ?? null));

        $full = $compact;
        if (!empty($desc))      $full .= "\nDescripción: " . mb_substr($desc, 0, 300);
        if ($startLat !== null) $full .= "\nInicio: {$startLat}, {$startLon}";

        return $full;
    }

    /**
     * Recupera una ruta completa desde BD por ID.
     */
    private function fetchFullRouteById(int $id): ?array
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT id, titulo AS title, dificultad AS difficulty,
                        distancia_km AS distance, duracion_horas AS duration,
                        elevacion_ganada AS elevation, ubicacion AS location,
                        tipo_ruta, descripcion AS description,
                        latitud_inicio AS startLat, longitud_inicio AS startLng
                 FROM rutas WHERE id = ? LIMIT 1"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ?: null;
        } catch (\Exception $e) {
            $this->logger->warning('fetchFullRouteById error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Recupera múltiples rutas por array de IDs, preservando el orden original.
     */
    private function fetchRoutesById(array $ids): array
    {
        if (empty($ids)) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types        = str_repeat('i', count($ids));
            $stmt = $this->conn->prepare(
                "SELECT id, titulo AS title, dificultad AS difficulty,
                        distancia_km AS distance, duracion_horas AS duration,
                        elevacion_ganada AS elevation, ubicacion AS location,
                        tipo_ruta, descripcion AS description,
                        latitud_inicio AS startLat, longitud_inicio AS startLng
                 FROM rutas WHERE id IN ({$placeholders}) LIMIT 10"
            );
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Preservar el orden original de $ids
            $indexed = array_column($rows, null, 'id');
            return array_values(array_filter(
                array_map(fn($id) => $indexed[$id] ?? null, $ids)
            ));
        } catch (\Exception $e) {
            $this->logger->warning('fetchRoutesById error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Recupera rutas del pool de candidatos aplicando filtros adicionales.
     * Opera siempre sobre last_candidate_ids para garantizar consistencia.
     */
    private function fetchRoutesWithFilter(array $candidateIds, array $filters, int $limit = 5): array
    {
        if (empty($candidateIds)) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
            $types        = str_repeat('i', count($candidateIds));
            $params       = $candidateIds;
            $where        = ["id IN ({$placeholders})"];

            if (!empty($filters['max_distance'])) {
                $where[]   = 'distancia_km <= ?';
                $params[]  = $filters['max_distance'];
                $types    .= 'd';
            }
            if (!empty($filters['min_distance'])) {
                $where[]   = 'distancia_km >= ?';
                $params[]  = $filters['min_distance'];
                $types    .= 'd';
            }
            if (!empty($filters['max_duration'])) {
                $where[]   = 'duracion_horas <= ?';
                $params[]  = $filters['max_duration'];
                $types    .= 'd';
            }
            if (!empty($filters['max_elevation'])) {
                $where[]   = 'elevacion_ganada <= ?';
                $params[]  = $filters['max_elevation'];
                $types    .= 'i';
            }
            if (!empty($filters['difficulty'])) {
                $where[]   = 'dificultad = ?';
                $params[]  = $filters['difficulty'];
                $types    .= 's';
            }

            $params[] = $limit;
            $types   .= 'i';

            $stmt = $this->conn->prepare(
                "SELECT id, titulo AS title, dificultad AS difficulty,
                        distancia_km AS distance, duracion_horas AS duration,
                        elevacion_ganada AS elevation, ubicacion AS location,
                        tipo_ruta
                 FROM rutas
                 WHERE " . implode(' AND ', $where) . "
                 LIMIT ?"
            );
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows;
        } catch (\Exception $e) {
            $this->logger->warning('fetchRoutesWithFilter error', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
