<?php
/**
 * Integración con Claude (Anthropic) para Kahir.AI
 *
 * Haiku  → extractQueryData() : extrae intent + parámetros estructurados del mensaje
 * Sonnet → processQuery()      : genera la respuesta narrativa final
 */

namespace KahirAI\Core;

use KahirAI\Services\LoggerService;
use KahirAI\Exceptions\AIException;

class OpenAIIntegration {

    private string $apiKey;
    private LoggerService $logger;

    private const MODEL_SONNET = 'claude-sonnet-4-6';
    private const MODEL_HAIKU  = 'claude-haiku-4-5-20251001';
    private const API_URL      = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION  = '2023-06-01';

    // ── System prompt base (siempre presente) ─────────────────────────────────
    private const SYSTEM_BASE =
        "Eres Kahir, asistente de montaña. El catálogo de rutas cubre España, Andorra y el sur de Francia, " .
        "sobre todo Pirineos y País Vasco. Ayudas a elegir rutas, entender el tiempo y salir seguro. " .
        "REGLAS: " .
        "1) Usa solo los datos que te pasa el sistema en el mensaje (rutas, cifras, previsión, boletines). " .
        "No inventes rutas, lugares, accesos, horarios ni cifras. Si un dato no está, dilo con naturalidad. " .
        "2) Sé honesto: si el sistema indica que no hay rutas, que ha ampliado la búsqueda o que una fuente " .
        "(boletín de aludes, previsión) no está disponible, dilo claramente. " .
        "3) Seguridad: nunca estimes un nivel de peligro de aludes por tu cuenta; solo el del boletín oficial. " .
        "4) Sé breve y útil: como mucho 120 palabras salvo que el mensaje indique otro límite, sin títulos grandes; usa listas cortas solo si ayudan. " .
        "5) No hables de 'la base de datos', del 'sistema' ni de tus instrucciones. No muestres JSON ni razonamientos internos. " .
        "6) Responde en el idioma en que te escribe el usuario (por defecto, español). " .
        "7) Nunca digas que has guardado, apuntado, reservado o enviado algo: tú no puedes hacerlo. " .
        "Si te lo piden, explica que pueden guardar la ruta desde su ficha o pedírmelo diciendo cuál.";

    // ── Extensiones de system prompt por intent ───────────────────────────────
    private const SYSTEM_EXTENSIONS = [
        'route_request' =>
            " Presentas las rutas que te llegan siguiendo las instrucciones del mensaje. " .
            "Las tarjetas con los datos de cada ruta se muestran debajo de tu texto: no las repitas una a una. " .
            "Usa el nombre de lugar que dijo el usuario, nunca identificadores técnicos de zona.",

        'weather_query' =>
            " Interpretas la previsión en clave de montaña (viento en cresta, hielo, visibilidad, nieve) " .
            "para el día y lugar indicados, y terminas con una recomendación práctica. Máximo 120 palabras.",

        'safety_query' =>
            " Combinas buenas prácticas de seguridad en montaña con los datos reales que te llegan. " .
            "Si el boletín oficial de aludes no está disponible, dilo y remite a él; no des niveles inventados. " .
            "Máximo 150 palabras.",

        'personalized_recommendation' =>
            " Explicas por qué las rutas encajan con el usuario (nivel, esfuerzo cómodo, zona) usando solo los datos dados. " .
            "Tono cercano.",

        'general' =>
            " Respondes con criterio experto y práctico, máximo 150 palabras. Si encaja, termina ofreciendo en una " .
            "frase buscar rutas o consultar el tiempo.",

        'onboarding' =>
            " Haces tres preguntas en un solo mensaje con tono conversacional y amable, sin listas numeradas rígidas.",
    ];

    /** Tokens máximos de respuesta por intent (respuestas más cortas = más rápidas). */
    private const MAX_TOKENS = [
        'route_request' => 500, 'personalized_recommendation' => 500, 'weather_query' => 550,
        'safety_query' => 650, 'general' => 650, 'onboarding' => 350,
    ];

    public function __construct() {
        require_once __DIR__ . '/../config/config.php';
        $this->apiKey = CLAUDE_API_KEY;
        $this->logger = new LoggerService('claude');

        if (empty($this->apiKey)) {
            throw new AIException('Claude API key not configured', 'API_KEY_MISSING');
        }
    }

    // ── API pública ──────────────────────────────────────────────────────────

    /**
     * Llama directamente a Haiku con mensajes ya construidos.
     * Útil para prompts propios (onboarding, etc.) que no pasan por buildHaikuPrompt.
     *
     * @param  array  $messages  Array de mensajes [{role, content}]
     * @param  int    $maxTokens Tokens máximos de respuesta
     * @return array  Respuesta raw de la API de Claude
     */
    public function callHaikuRaw(array $messages, int $maxTokens = 300): array {
        return $this->callClaude($messages, self::MODEL_HAIKU, $maxTokens, null);
    }

    /**
     * Extrae intent + parámetros estructurados del mensaje del usuario.
     * Usa Claude Haiku (rápido, barato). Devuelve el schema completo.
     *
     * @return array{
     *   intent: string,
     *   zone: string|null,
     *   activity: string|null,
     *   difficulty: string|null,
     *   timeframe: string|null,
     *   days_ahead: int,
     *   weather_check_needed: bool
     * }
     */
    public function extractQueryData(string $query): array {
        $default = [
            'intent'               => 'general',
            'zone'                 => null,
            'activity'             => null,
            'difficulty'           => null,
            'timeframe'            => null,
            'days_ahead'           => 0,
            'weather_check_needed' => false,
        ];

        try {
            $prompt = $this->buildHaikuPrompt($query);
            $messages = [['role' => 'user', 'content' => $prompt]];

            // Haiku no necesita system prompt — solo extrae estructura
            $response = $this->callClaude($messages, self::MODEL_HAIKU, 400, null);
            $text     = $response['content'][0]['text'] ?? '';

            // Extraer el bloque JSON de la respuesta
            preg_match('/\{[\s\S]*\}/u', $text, $m);
            if (empty($m[0])) {
                return $default;
            }
            $decoded = json_decode($m[0], true);
            if (!is_array($decoded)) {
                return $default;
            }

            // Normalizar y validar campos
            return $this->normalizeExtractedData($decoded, $default);

        } catch (\Exception $e) {
            $this->logger->error('extractQueryData error', ['error' => $e->getMessage()]);
            return $default;
        }
    }

    /**
     * Genera la respuesta narrativa con Claude Sonnet.
     * Acepta un system prompt personalizado por intent.
     */
    public function processQuery(
        string $userPrompt,
        array  $context       = [],
        string $intentForSys  = 'general'
    ): array {
        try {
            $messages   = $this->buildMessages($userPrompt, $context);
            $systemPmt  = $this->buildSystemPrompt($intentForSys);
            $maxTokens  = self::MAX_TOKENS[$intentForSys] ?? 600;
            $response   = $this->callClaude($messages, self::MODEL_SONNET, $maxTokens, $systemPmt);
            return $this->parseResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('processQuery error', ['error' => $e->getMessage()]);
            throw new AIException('Error processing query', 'CLAUDE_PROCESSING_ERROR');
        }
    }

    /**
     * Llamada a Sonnet con herramientas (modo agente). Devuelve la respuesta cruda de la API
     * (content con bloques text / tool_use y stop_reason). El system llega en bloques para
     * poder cachear la parte fija (instrucciones + herramientas).
     */
    public function callWithTools(array $messages, array $systemBlocks, array $tools, int $maxTokens = 1000): array {
        return $this->callClaude($messages, self::MODEL_SONNET, $maxTokens, $systemBlocks, $tools);
    }

    /**
     * Genera 3 sugerencias contextuales de seguimiento.
     */
    public function generateSuggestions(array $context): array {
        try {
            $summary  = $this->summarizeContext($context);
            $messages = [[
                'role'    => 'user',
                'content' => "Conversación sobre montaña:\n$summary\n\n" .
                             "Genera exactamente 3 preguntas cortas que el usuario podría hacer a continuación. " .
                             "Responde ÚNICAMENTE con un JSON array de strings.",
            ]];

            $response    = $this->callClaude($messages, self::MODEL_HAIKU, 200, null);
            $text        = $response['content'][0]['text'] ?? '[]';
            preg_match('/\[[\s\S]*\]/u', $text, $m);
            $suggestions = isset($m[0]) ? json_decode($m[0], true) : [];

            return is_array($suggestions) ? array_slice($suggestions, 0, 3) : [];

        } catch (\Exception $e) {
            $this->logger->error('generateSuggestions error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ── Privados ──────────────────────────────────────────────────────────────

    private function buildHaikuPrompt(string $query): string {
        return <<<PROMPT
Eres un extractor de datos para una app de montaña que cubre España y sur de Francia.
Analiza el mensaje y devuelve ÚNICAMENTE JSON válido con estos campos:

{
  "intent": "route_request|weather_query|safety_query|personalized_recommendation|general",
  "zone": "lugar/zona mencionado o null",
  "activity": "hiking|ski_touring|btt|cycle_touring|climbing|snowshoeing o null",
  "difficulty": "beginner|intermediate|advanced o null",
  "timeframe": "today|tomorrow|this_weekend|this_week|next_days o null",
  "days_ahead": 0,
  "weather_check_needed": false,
  "max_elevation": null,
  "max_distance": null,
  "min_distance": null,
  "target_duration": null,
  "max_duration": null,
  "with_kids": false,
  "out_of_coverage": false,
  "language": "es"
}

REGLAS DE ACTIVIDAD: "BTT", "bici de montaña", "mountain bike", "MTB" → btt. "cicloturismo", "bici de carretera", "vía verde en bici" → cycle_touring. "senderismo", "andar", "caminar", "hike", "subir a un monte" → hiking. "esquí de travesía", "esquí de montaña", "skimo" → ski_touring.
REGLAS DE DIFICULTAD: "fácil", "sencilla", "con niños", "para principiantes" → beginner. "exigente", "dura", "difícil", "para expertos" → advanced. "intermedia", "moderada" → intermediate.
REGLAS NUMÉRICAS (null si no se mencionan):
- max_elevation: desnivel máximo en metros ("menos de 500 m de desnivel" → 500; "sin mucho desnivel" → 400).
- max_distance / min_distance: km ("menos de 10 km" → max_distance 10).
- target_duration: horas deseadas ("unas 6 horas" → 6; "media jornada" → 4; "toda la jornada" → 7).
- max_duration: horas máximas ("menos de 3 horas" → 3).
with_kids: true si menciona niños, críos, peques, familia con hijos.
out_of_coverage: true si la zona está claramente fuera de España, Andorra y el sur de Francia (Alpes, Dolomitas, Escocia, Nepal…).
language: código ISO del idioma del mensaje ("es", "en", "eu", "fr", "ca"…).

REGLAS DE INTENT:
- route_request: busca rutas, senderos, excursiones. TAMBIÉN cuando usa "recomiéndame", "sugiere" o "qué hay" si menciona una zona geográfica concreta o una actividad concreta.
- weather_query: pregunta por tiempo, meteorología, previsión, nieve, lluvia, viento
- safety_query: pregunta por seguridad, peligros, aludes, riesgo, avalanchas
- personalized_recommendation: pide recomendaciones SIN especificar ninguna zona geográfica concreta ("recomiéndame algo", "qué me propones", "sorpréndeme", "para este finde sin zona"). Si hay zona → route_request.
- general: equipamiento, técnica, temporadas, normativa, información genérica

REGLA DE PRIORIDAD DE INTENT: Si el mensaje menciona zona geográfica concreta (ciudad, montaña, comarca, parque…) Y pide rutas o actividad → SIEMPRE route_request, aunque use verbos como "recomiéndame", "sugiere", "qué puedo hacer", "cerca de". personalized_recommendation SOLO cuando no hay ninguna zona.

REGLA ACTIVIDAD SIN ZONA: Si el mensaje menciona una actividad concreta (senderismo, escalada, ski touring, BTT, snowshoeing…) pero NO menciona ninguna zona geográfica → intent:route_request igualmente (zone:null). El sistema pedirá la zona si es necesario.

REGLA PREGUNTA CLIMÁTICA vs METEOROLÓGICA: Si la pregunta usa expresiones EXPLÍCITAS de temporada o climatología histórica como "mejor época del año", "cuándo suele nevar normalmente", "qué meses son mejores", "temporada ideal", "climatología histórica", "cuándo es la mejor temporada" → intent:general (NO weather_query).
weather_query es para: condiciones actuales, pronóstico de días concretos, preguntas sobre el tiempo que el usuario quiere consultar AHORA. Ejemplo: "¿y el tiempo allí?" → weather_query (quiere consultar el tiempo, no climatología).

REGLAS DE ZONE: Extrae el nombre geográfico tal como lo dice el usuario.
Ejemplos válidos: "Candanchú", "Valle de Tena", "Sierra Nevada", "Picos de Europa",
"Teide", "Mallorca", "Cazorla", "Gúdar", "Guadarrama", "Galicia".
null si no menciona ningún lugar geográfico concreto.
PALABRAS QUE NO SON TOPÓNIMOS → zone: null (aunque aparezcan como referencia de lugar):
"spot", "el sitio", "aquí", "allí", "allá", "la zona", "el monte", "el pico",
"el lugar", "el paraje", "por aquí", "cerca", "la estación", "las pistas",
"el refugio", "ahí", "ese sitio", "ese lugar", "el punto".

REGLAS DE TIMEFRAME:
- "hoy" / sin mención → today, days_ahead: 0
- "mañana" → tomorrow, days_ahead: 1
- "este finde" / "fin de semana" → this_weekend, days_ahead: 2
- "esta semana" → this_week, days_ahead: 7
- "X días" → next_days, days_ahead: X
- "¿cuándo habrá…?" / "ventana de buen tiempo" / "mejor momento" / "cuándo es mejor" (sin
  fecha concreta) → next_days, days_ahead: 7  (se necesita horizonte amplio para buscar la ventana)

REGLAS DE weather_check_needed: true si menciona tiempo, condiciones, nieve, lluvia,
viento, alud, o si el intent es safety_query o weather_query, o si pregunta por el
mejor día / qué día salir / cuándo ir / en qué día / qué día es mejor.

Mensaje del usuario: "$query"
PROMPT;
    }

    private function normalizeExtractedData(array $raw, array $default): array {
        $validIntents    = ['route_request', 'weather_query', 'safety_query',
                            'personalized_recommendation', 'general'];
        $validActivities = ['hiking', 'ski_touring', 'btt', 'cycle_touring', 'climbing', 'snowshoeing'];
        $validDiffs      = ['beginner', 'intermediate', 'advanced'];
        $validTimeframes = ['today', 'tomorrow', 'this_weekend', 'this_week', 'next_days'];
        // Número dentro de un rango razonable, o null
        $num = static function ($v, float $min, float $max): ?float {
            if (!is_numeric($v)) return null;
            $v = (float) $v;
            return ($v >= $min && $v <= $max) ? $v : null;
        };

        return [
            'intent'               => in_array($raw['intent'] ?? '', $validIntents, true)
                                          ? $raw['intent'] : $default['intent'],
            'zone'                 => !empty($raw['zone']) && is_string($raw['zone'])
                                          ? trim($raw['zone']) : null,
            'activity'             => in_array($raw['activity'] ?? '', $validActivities, true)
                                          ? $raw['activity'] : null,
            'difficulty'           => in_array($raw['difficulty'] ?? '', $validDiffs, true)
                                          ? $raw['difficulty'] : null,
            'timeframe'            => in_array($raw['timeframe'] ?? '', $validTimeframes, true)
                                          ? $raw['timeframe'] : null,
            'days_ahead'           => isset($raw['days_ahead']) ? (int)$raw['days_ahead'] : 0,
            'weather_check_needed' => (bool)($raw['weather_check_needed'] ?? false),
            'max_elevation'        => $num($raw['max_elevation'] ?? null, 50, 5000),
            'max_distance'         => $num($raw['max_distance'] ?? null, 1, 300),
            'min_distance'         => $num($raw['min_distance'] ?? null, 1, 300),
            'target_duration'      => $num($raw['target_duration'] ?? null, 0.5, 14),
            'max_duration'         => $num($raw['max_duration'] ?? null, 0.5, 14),
            'with_kids'            => (bool)($raw['with_kids'] ?? false),
            'out_of_coverage'      => (bool)($raw['out_of_coverage'] ?? false),
            'language'             => is_string($raw['language'] ?? null) && preg_match('/^[a-z]{2}$/', $raw['language'])
                                          ? $raw['language'] : 'es',
        ];
    }

    private function buildSystemPrompt(string $intent): string {
        $diasEs = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
        $hoy    = $diasEs[(int)date('w')] . ' ' . date('d') . ' de ' . $this->mesEs((int)date('n')) . ' de ' . date('Y');
        $dateCtx = "Fecha de hoy: {$hoy}. Usa esta fecha para calcular correctamente 'mañana', 'este fin de semana', 'esta semana', etc. ";

        $extension = self::SYSTEM_EXTENSIONS[$intent] ?? self::SYSTEM_EXTENSIONS['general'];
        return $dateCtx . self::SYSTEM_BASE . $extension;
    }

    private function mesEs(int $n): string {
        return ['','enero','febrero','marzo','abril','mayo','junio',
                'julio','agosto','septiembre','octubre','noviembre','diciembre'][$n];
    }

    private function buildMessages(string $query, array $context): array {
        $messages = [];
        foreach (array_slice($context, -4) as $msg) {   // últimos 4 (diseño doc)
            if (isset($msg['role'], $msg['content'])) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $query];
        return $messages;
    }

    private function callClaude(
        array             $messages,
        string            $model,
        int               $maxTokens,
        string|array|null $systemPrompt,
        array             $tools = []
    ): array {
        $body = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => $messages];
        if ($systemPrompt !== null) {
            $body['system'] = $systemPrompt;
        }
        if ($tools) {
            $body['tools'] = $tools;
        }

        $headers = [
            'Content-Type: application/json',
            'x-api-key: '         . $this->apiKey,
            'anthropic-version: ' . self::API_VERSION,
        ];
        // Claves de organización (no asociadas a un workspace) exigen indicar el workspace
        $workspaceId = trim((string) ($_ENV['CLAUDE_WORKSPACE_ID'] ?? getenv('CLAUDE_WORKSPACE_ID') ?: ''));
        if ($workspaceId !== '') {
            $headers[] = 'anthropic-workspace-id: ' . $workspaceId;
        }

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => API_TIMEOUT,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new AIException('cURL error: ' . $curlErr, 'API_CONNECTION_ERROR');
        }
        if ($httpCode !== 200) {
            // Guardar también el tipo y el mensaje de error de la API (nunca incluyen la clave)
            $errBody = json_decode((string) $resp, true);
            $this->logger->error('Claude API error', [
                'code'    => $httpCode,
                'type'    => $errBody['error']['type'] ?? null,
                'message' => isset($errBody['error']['message']) ? mb_substr((string) $errBody['error']['message'], 0, 300) : null,
            ]);
            throw new AIException("Claude API HTTP $httpCode", 'API_RESPONSE_ERROR');
        }

        $decoded = json_decode($resp, true);
        if (!$decoded) {
            throw new AIException('Invalid JSON from Claude API', 'INVALID_RESPONSE_FORMAT');
        }
        return $decoded;
    }

    private function parseResponse(array $response): array {
        $text = '';
        foreach (($response['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') $text .= $block['text'];
        }
        $text = trim($text);
        // Cortada por el límite de tokens: se recorta a la última frase completa en vez de
        // dejar la respuesta a medias
        if (($response['stop_reason'] ?? '') === 'max_tokens' && $text !== '') {
            $this->logger->warning('Respuesta de Claude cortada por max_tokens', ['len' => mb_strlen($text)]);
            if (preg_match('/^.*[.!?…](?=\s|$)/su', $text, $m) && mb_strlen($m[0]) > mb_strlen($text) * 0.5) {
                $text = $m[0];
            } else {
                $text = rtrim($text, " ,;:-") . '…';
            }
        }
        return ['type' => 'text', 'content' => $text];
    }

    private function summarizeContext(array $context): string {
        return implode("\n", array_map(
            fn($m) => ($m['role'] === 'user' ? 'Usuario' : 'Kahir') . ': ' . ($m['content'] ?? ''),
            array_slice($context, -6)
        ));
    }
}
