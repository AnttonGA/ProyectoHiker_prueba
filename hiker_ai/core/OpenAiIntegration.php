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
        "Eres Kahir, asistente de montaña especializado en España y sur de Francia. " .
        "Cubres todo el territorio español (Pirineos, Sierra Nevada, Picos de Europa, " .
        "Sistema Central, Sistema Ibérico, Levante, Andalucía, Canarias, Baleares…) " .
        "y el sur de Francia. Ayudas a planificar rutas, consultar el tiempo y resolver " .
        "dudas de montaña. " .
        "REGLA CRÍTICA: Nunca inventes rutas, lugares ni datos que no te hayan sido " .
        "proporcionados explícitamente en el mensaje. Solo recomiendas rutas de la base " .
        "de datos del sistema. " .
        "PROHIBIDO ABSOLUTO: No hagas nunca comentarios sobre la base de datos, sobre " .
        "lo que 'tienes' o 'no tienes' en tu sistema, ni corrijas respuestas anteriores " .
        "haciendo meta-comentarios. Si el sistema te pasa rutas, preséntelas con " .
        "normalidad — son reales y han sido validadas. Nunca digas frases como " .
        "'no tengo rutas para X', 'debo corregir mis respuestas', 'inventarlas sería " .
        "peligroso' o similares. Confía en los datos que el sistema te proporciona. " .
        "Responde siempre en español. Sé conciso y directo.";

    // ── Extensiones de system prompt por intent ───────────────────────────────
    private const SYSTEM_EXTENSIONS = [
        'route_request' =>
            " Presentas las rutas encontradas de forma concisa siguiendo las instrucciones " .
            "del mensaje (una frase o un párrafo breve, según se indique). " .
            "No describes cada ruta individualmente — eso lo hacen las tarjetas del carousel. " .
            "Las rutas que te llegan son reales y están en la base de datos — preséntelas con confianza. " .
            "Usa siempre el nombre de lugar que mencionó el usuario, nunca el identificador técnico de zona. " .
            "La cobertura incluye toda España y sur de Francia.",

        'weather_query' =>
            " Interpretas los datos meteorológicos en clave de montaña: no solo " .
            "temperatura, sino implicaciones concretas para la actividad (visibilidad " .
            "en cresta, riesgo de hielo, condiciones de nieve, etc.).",

        'safety_query' =>
            " Combinas conocimiento experto de seguridad en montaña con los datos " .
            "meteorológicos proporcionados. Prioriza siempre la seguridad. Sé específico " .
            "con los riesgos actuales basándote en los datos reales del momento.",

        'personalized_recommendation' =>
            " Explicas de forma natural POR QUÉ recomiendas cada ruta: nivel del usuario, " .
            "condiciones meteorológicas de la zona y si encaja con su historial. " .
            "Tono personal y motivador.",

        'general' =>
            " Al final de tu respuesta, si la pregunta trata sobre una actividad específica, " .
            "ofrece buscar rutas de ese tipo. Si menciona un lugar, ofrece consultar el tiempo. " .
            "Una sola frase de oferta, natural y no intrusiva.",

        'onboarding' =>
            " Haces tres preguntas en un solo mensaje con tono conversacional y amable. " .
            "No uses listas numeradas rígidas — integra las preguntas de forma natural. " .
            "Las zonas de España que cubres incluyen Pirineos, Sierra Nevada, Picos de Europa, " .
            "Sistema Central, Canarias, Baleares y mucho más.",
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
            $response = $this->callClaude($messages, self::MODEL_HAIKU, 300, null);
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
            $response   = $this->callClaude($messages, self::MODEL_SONNET, 1024, $systemPmt);
            return $this->parseResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('processQuery error', ['error' => $e->getMessage()]);
            throw new AIException('Error processing query', 'CLAUDE_PROCESSING_ERROR');
        }
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
  "activity": "hiking|ski_touring|climbing|cycle_touring|snowshoeing o null",
  "difficulty": "beginner|intermediate|advanced o null",
  "timeframe": "today|tomorrow|this_weekend|this_week|next_days o null",
  "days_ahead": 0,
  "weather_check_needed": false
}

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
        $validActivities = ['hiking', 'ski_touring', 'climbing', 'cycle_touring', 'snowshoeing'];
        $validDiffs      = ['beginner', 'intermediate', 'advanced'];
        $validTimeframes = ['today', 'tomorrow', 'this_weekend', 'this_week', 'next_days'];

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
        array   $messages,
        string  $model,
        int     $maxTokens,
        ?string $systemPrompt
    ): array {
        $body = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => $messages];
        if ($systemPrompt !== null) {
            $body['system'] = $systemPrompt;
        }

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => API_TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: '         . $this->apiKey,
                'anthropic-version: ' . self::API_VERSION,
            ],
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
            $this->logger->error('Claude API error', ['code' => $httpCode]);
            throw new AIException("Claude API HTTP $httpCode", 'API_RESPONSE_ERROR');
        }

        $decoded = json_decode($resp, true);
        if (!$decoded) {
            throw new AIException('Invalid JSON from Claude API', 'INVALID_RESPONSE_FORMAT');
        }
        return $decoded;
    }

    private function parseResponse(array $response): array {
        $text = $response['content'][0]['text'] ?? '';
        preg_match('/\{[\s\S]*\}/u', $text, $m);
        if (!empty($m[0])) {
            $structured = json_decode($m[0], true);
            if ($structured) return $structured;
        }
        return ['type' => 'text', 'content' => trim($text)];
    }

    private function summarizeContext(array $context): string {
        return implode("\n", array_map(
            fn($m) => ($m['role'] === 'user' ? 'Usuario' : 'Kahir') . ': ' . ($m['content'] ?? ''),
            array_slice($context, -6)
        ));
    }
}
