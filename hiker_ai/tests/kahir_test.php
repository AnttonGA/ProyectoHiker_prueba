<?php
/**
 * kahir_test.php — Suite de 63 pruebas de Kahir.AI
 * Activa el audit log automáticamente durante la ejecución.
 *
 * USO navegador: http://localhost/misproyectos/ProyectoHiker_seguridad/hiker_ai/tests/kahir_test.php
 * USO CLI:       C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe kahir_test.php
 */
declare(strict_types=1);

// Activar audit log antes de cargar config.php (define() es silencioso si ya existe)
define('KAHIR_AUDIT_LOG', true);

// ── Configuración ─────────────────────────────────────────────────────────────
const TEST_USER_ID = '1';   // ← ID real de tu tabla `usuarios`
const SLOW_MS      = 9000;
const PAUSE_MS     = 400;

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$hiker_ai_dir = dirname(__DIR__);
$project_root = dirname(__DIR__, 2);

require_once $project_root . '/includes/env_loader.php';
load_env($project_root . '/.env');
require_once $hiker_ai_dir . '/config/config.php';
require_once $hiker_ai_dir . '/config/database.php';
require_once $project_root . '/vendor/autoload.php';

use KahirAI\Core\AICore;
use KahirAI\Database\DBConnection;

$isCli = (PHP_SAPI === 'cli');

// ── Output helpers ────────────────────────────────────────────────────────────
function c(string $t, string $col = ''): string {
    global $isCli;
    if ($isCli) {
        $m = ['green'=>'32','red'=>'31','yellow'=>'33','cyan'=>'36','bold'=>'1','dim'=>'2','mag'=>'35','white'=>'97'];
        return isset($m[$col]) ? "\033[{$m[$col]}m{$t}\033[0m" : $t;
    }
    $s = ['green'=>'color:#4ade80;font-weight:bold','red'=>'color:#f87171;font-weight:bold',
          'yellow'=>'color:#facc15','cyan'=>'color:#22d3ee','bold'=>'font-weight:bold',
          'dim'=>'color:#64748b','mag'=>'color:#c084fc','white'=>'color:#f1f5f9'];
    $style = $s[$col] ?? '';
    return $style ? "<span style=\"{$style}\">" . htmlspecialchars($t, ENT_QUOTES) . "</span>"
                  : htmlspecialchars($t, ENT_QUOTES);
}
function p(string $l): void { global $isCli; echo $l . ($isCli ? "\n" : "<br>"); }
function section(string $g, string $sub = ''): void {
    global $isCli;
    $sub = $sub ? " — {$sub}" : '';
    if ($isCli) { p(''); p(c("  ┌─ {$g}{$sub} ", 'cyan') . c(str_repeat('─', max(0,62-strlen($g.$sub))), 'dim')); }
    else echo "<h3 style='color:#22d3ee;margin:14px 0 4px;border-bottom:1px solid #1e293b;padding-bottom:3px'>"
            . htmlspecialchars($g.$sub, ENT_QUOTES) . "</h3>";
}

// ── Crear sesión fresca ───────────────────────────────────────────────────────
function freshSession(\mysqli $conn): int {
    $uid  = (int)TEST_USER_ID;
    $stmt = $conn->prepare("INSERT INTO chat_sessions (usuario_id) VALUES (?)");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}

// ── 30 casos de prueba ───────────────────────────────────────────────────────
//
// expect campos:
//   intent            → string   intent esperado
//   response          → string   tipo de respuesta esperado
//   routes            → bool     true = deben llegar rutas
//   no_routes         → bool     true = NO deben llegar rutas (fuera cobertura / sin resultados)
//   content_contains  → array    al menos UNA palabra debe aparecer en el texto de respuesta
//   manual            → bool     no auto-valida, solo registra en log para revisión
//
// chain = conversación multi-paso sobre la misma sesión
// note  = descripción de lo que verifica ese paso

$CASES = [

// ════════════════════════════════════════════════════════════════════════════
// GRUPO A — Buscar rutas
// ════════════════════════════════════════════════════════════════════════════
['group'=>'A — Buscar rutas','diff'=>'Fácil',
 'label'=>'A01 · Happy path completo (zona+actividad+dificultad+día)',
 'q'=>'Quiero hacer senderismo en Ordesa este sábado, nivel intermedio.',
 'note'=>'Ningún parámetro falta. El sistema no debe pedir nada.',
 'expect'=>['intent'=>'route_request','response'=>'route_suggestions','routes'=>true]],

['group'=>'A — Buscar rutas','diff'=>'Fácil',
 'label'=>'A02 · Ski touring fin de semana (pronóstico, no hoy)',
 'q'=>'Busco rutas de ski touring en Benasque para el fin de semana.',
 'note'=>'Debe comprobar pronóstico finde, no tiempo actual.',
 'expect'=>['intent'=>'route_request','routes'=>true]],

['group'=>'A — Buscar rutas','diff'=>'Fácil',
 'label'=>'A03 · Zona Euskadi + dificultad + actividad implícita',
 'q'=>'Rutas fáciles por Aralar para ir con niños.',
 'note'=>'No debe pedir más datos. Actividad = hiking implícito.',
 'expect'=>['intent'=>'route_request']],

['group'=>'A — Buscar rutas','diff'=>'Media',
 'label'=>'A04 · Zona abstracta sin actividad ni dificultad',
 'q'=>'Quiero hacer algo en los Pirineos.',
 'note'=>'Debe usar perfil de usuario para completar parámetros.',
 'expect'=>['intent'=>'route_request','routes'=>true]],

['group'=>'A — Buscar rutas','diff'=>'Media',
 'label'=>'A05 · Degradación: Western_Pyrenees pocas rutas',
 'q'=>'Busco rutas de escalada en los Pirineos occidentales.',
 'note'=>'Puede no tener rutas de escalada. Sistema debe gestionar la ausencia.',
 'expect'=>['intent'=>'route_request']],

['group'=>'A — Buscar rutas','diff'=>'Difícil',
 'label'=>'A06 · Fuera de cobertura — NO inventar rutas',
 'q'=>'Rutas de senderismo en los Alpes.',
 'note'=>'Caso C1: fuera de BD. No debe inventar rutas ni usar conocimiento general de Claude.',
 'expect'=>['intent'=>'route_request','no_routes'=>true,
            'content_contains'=>['cobertura','España','fuera','Alpes']]],

['group'=>'A — Buscar rutas','diff'=>'Media',
 'label'=>'A07 · Lugar específico + mañana + restricción distancia',
 'q'=>'Busco algo para mañana cerca de Jaca, que no sea muy largo.',
 'note'=>'Proximidad 80km, days_ahead=1, debe obtener previsión de mañana.',
 'expect'=>['intent'=>'route_request','routes'=>true]],

['group'=>'A — Buscar rutas','diff'=>'Difícil',
 'label'=>'A08 · Snowshoeing (actividad poco común)',
 'q'=>'Rutas de snowshoeing en Candanchú para este fin de semana.',
 'note'=>'Si no hay rutas de snowshoeing, debe gestionar ausencia sin inventar.',
 'expect'=>['intent'=>'route_request']],

['group'=>'A — Buscar rutas','diff'=>'Difícil',
 'label'=>'A09 · Sin zona — no debe pedir zona, usar personalized',
 'q'=>'Busco rutas de senderismo.',
 'note'=>'Sin zona: debería ir a personalized_recommendation o pedir zona vía clarification.',
 'expect'=>['intent'=>'route_request']],  // puede caer a clarification_request

['group'=>'A — Buscar rutas','diff'=>'Difícil',
 'label'=>'A10 · Tiempo peligroso mañana (resultado variable según el día)',
 'q'=>'Rutas de senderismo en Benasque para mañana.',
 'note'=>'Si hay mal tiempo debe aparecer weather_warning. Resultado variable según día real.',
 'manual'=>true,
 'expect'=>['intent'=>'route_request','routes'=>true]],

// ════════════════════════════════════════════════════════════════════════════
// GRUPO B — Tiempo
// ════════════════════════════════════════════════════════════════════════════
['group'=>'B — Tiempo','diff'=>'Fácil',
 'label'=>'B01 · Happy path tiempo actual lugar específico',
 'q'=>'¿Qué tiempo hace en Formigal?',
 'note'=>'Widget numérico + texto Sonnet. Sin horizonte = hoy.',
 'expect'=>['intent'=>'weather_query','response'=>'weather_info']],

['group'=>'B — Tiempo','diff'=>'Fácil',
 'label'=>'B02 · Zona abstracta + fin de semana',
 'q'=>'¿Cómo estará el tiempo en el Pirineo aragonés este fin de semana?',
 'note'=>'Solo sábado y domingo, no 7 días completos.',
 'expect'=>['intent'=>'weather_query','response'=>'weather_info']],

['group'=>'B — Tiempo','diff'=>'Media',
 'label'=>'B03 · Pronóstico >5 días — debe mencionar incertidumbre',
 'q'=>'¿Qué tiempo tendrá la próxima semana en los Pirineos?',
 'note'=>'days_ahead>5: Sonnet debe mencionar incertidumbre explícitamente.',
 'expect'=>['intent'=>'weather_query',
            'content_contains'=>['incertidumbre','orientativo','margen','revisar','aproximado']]],

['group'=>'B — Tiempo','diff'=>'Difícil',
 'label'=>'B04 · Pregunta climática fuera de cobertura — SÍ responder',
 'q'=>'¿Cuándo hay mejor nieve en los Alpes?',
 'note'=>'Caso C2: pregunta climática general, no meteorológica en tiempo real. Claude SÍ puede responder.',
 'expect'=>['intent'=>'general']],

// ════════════════════════════════════════════════════════════════════════════
// GRUPO C — Seguridad
// ════════════════════════════════════════════════════════════════════════════
['group'=>'C — Seguridad','diff'=>'Fácil',
 'label'=>'C01 · Safety query pico específico + fin de semana',
 'q'=>'¿Es seguro subir al Aneto este fin de semana?',
 'note'=>'Debe disparar Open-Meteo + EAWS. No solo respuesta genérica.',
 'expect'=>['intent'=>'safety_query',
            'content_contains'=>['alud','viento','nieve','temperatura','riesgo','peligro']]],

['group'=>'C — Seguridad','diff'=>'Fácil',
 'label'=>'C02 · Aludes Benasque — nivel EAWS obligatorio',
 'q'=>'¿Hay riesgo de aludes en Benasque ahora mismo?',
 'note'=>'Respuesta debe incluir nivel de peligro oficial 1-5.',
 'expect'=>['intent'=>'safety_query',
            'content_contains'=>['nivel','1','2','3','4','5','alud','EAWS','peligro']]],

['group'=>'C — Seguridad','diff'=>'Media',
 'label'=>'C03 · Safety sin zona — conocimiento experto puro',
 'q'=>'¿Qué hago si me pilla una tormenta eléctrica en la cima?',
 'note'=>'No necesita coordenadas ni Open-Meteo. Respuesta con conocimiento experto.',
 'expect'=>['intent'=>'safety_query',
            'content_contains'=>['rayo','metal','cumbre','refugio','agachar','separar']]],

['group'=>'C — Seguridad','diff'=>'Difícil',
 'label'=>'C04 · Pregunta técnica umbral alud nivel 3',
 'q'=>'¿Es recomendable hacer ski touring con nivel de alud 3?',
 'note'=>'Sonnet debe matizar que nivel 3 es advertencia seria para ski touring.',
 'expect'=>['intent'=>'safety_query',
            'content_contains'=>['nivel 3','considerable','precaución','terreno','pendiente']]],

// ════════════════════════════════════════════════════════════════════════════
// GRUPO D — Recomendación personalizada
// ════════════════════════════════════════════════════════════════════════════
['group'=>'D — Recomendación personalizada','diff'=>'Fácil',
 'label'=>'D01 · Personalized con perfil completo',
 'q'=>'Recomiéndame algo para este fin de semana.',
 'note'=>'Debe evaluar tiempo en zonas preferidas y elegir la ganadora.',
 'expect'=>['intent'=>'personalized_recommendation','routes'=>true]],

['group'=>'D — Recomendación personalizada','diff'=>'Media',
 'label'=>'D02 · Sorpréndeme — sin parámetros',
 'q'=>'Sorpréndeme.',
 'note'=>'Sin zona. Usa perfil completo. No debe pedir zona.',
 'expect'=>['intent'=>'personalized_recommendation']],

['group'=>'D — Recomendación personalizada','diff'=>'Difícil',
 'label'=>'D03 · Perfil vacío debe activar onboarding',
 'q'=>'¿Qué me recomiendas para alguien como yo?',
 'note'=>'Si perfil vacío → onboarding. Si perfil completo → personalized_recommendation.',
 'manual'=>true,
 'expect'=>['intent'=>'personalized_recommendation']],

// ════════════════════════════════════════════════════════════════════════════
// GRUPO E — General
// ════════════════════════════════════════════════════════════════════════════
['group'=>'E — General','diff'=>'Fácil',
 'label'=>'E01 · Equipamiento ski touring principiante',
 'q'=>'¿Qué equipo necesito para hacer ski touring por primera vez?',
 'note'=>'Sin APIs. Al final debe ofrecer buscar rutas de ski touring principiante.',
 'expect'=>['intent'=>'general',
            'content_contains'=>['esquís','pieles','botas','avalancha','airbag','arva','pala']]],

['group'=>'E — General','diff'=>'Fácil',
 'label'=>'E02 · Temporadas refugios Pirineo',
 'q'=>'¿En qué meses están abiertos los refugios del Pirineo?',
 'note'=>'Conocimiento experto. Sin rutas ni tiempo.',
 'expect'=>['intent'=>'general',
            'content_contains'=>['julio','agosto','junio','septiembre','invierno','verano']]],

['group'=>'E — General','diff'=>'Media',
 'label'=>'E03 · Normativa acampada Ordesa',
 'q'=>'¿Se puede acampar libremente en el Parque Nacional de Ordesa?',
 'note'=>'Respuesta precisa sobre regulación. Oferta contextual de rutas al final.',
 'expect'=>['intent'=>'general',
            'content_contains'=>['prohibido','autorización','parque nacional','bivac','permiso']]],

// ════════════════════════════════════════════════════════════════════════════
// GRUPO F — Contexto y follow-up (conversaciones encadenadas)
// ════════════════════════════════════════════════════════════════════════════
['group'=>'F — Contexto/Follow-up','diff'=>'Difícil',
 'label'=>'F01 · Anáfora sin contexto previo — debe pedir clarificación',
 'q'=>'¿Y el tiempo allí?',
 'note'=>'Sin last_zone. No debe asumir ni usar Jaca como fallback. Debe pedir zona.',
 'expect'=>['intent'=>'weather_query',   // Haiku probablemente extrae weather_query
            'content_contains'=>['dónde','zona','lugar','qué zona','dónde quieres']]],

['group'=>'F — Contexto/Follow-up','diff'=>'Media',
 'label'=>'F02 · Herencia de zona tras buscar rutas en Candanchú',
 'chain'=>true,
 'steps'=>[
     ['q'=>'Rutas de senderismo en Candanchú.',
      'expect'=>['intent'=>'route_request','routes'=>true],
      'note'=>'Establece last_zone=Candanchú'],
     ['q'=>'¿Qué tiempo hace mañana?',
      'expect'=>['intent'=>'weather_query'],
      'note'=>'Debe heredar Candanchú sin pedirla de nuevo'],
 ]],

['group'=>'F — Contexto/Follow-up','diff'=>'Fácil',
 'label'=>'F03 · Ordinal: cuéntame más de la primera ruta',
 'chain'=>true,
 'steps'=>[
     ['q'=>'Rutas de senderismo en el Pirineo aragonés.',
      'expect'=>['intent'=>'route_request','routes'=>true],
      'note'=>'Carga active_routes en sesión'],
     ['q'=>'Cuéntame más de la primera.',
      'expect'=>['intent'=>'route_followup','response'=>'route_detail'],
      'note'=>'Pre-Haiku detecta ordinal→describe. No llama a Haiku.'],
 ]],

['group'=>'F — Contexto/Follow-up','diff'=>'Media',
 'label'=>'F04 · Comparativa: ¿cuál tiene menos desnivel?',
 'chain'=>true,
 'steps'=>[
     ['q'=>'Rutas en Aragón para senderismo.',
      'expect'=>['intent'=>'route_request','routes'=>true],
      'note'=>'Carga 5 rutas en sesión'],
     ['q'=>'¿Cuál tiene menos desnivel?',
      'expect'=>['intent'=>'route_followup'],
      'note'=>'Followup comparativo. Re-fetch de las 5 rutas. Sin inventar.'],
 ]],

['group'=>'F — Contexto/Follow-up','diff'=>'Difícil',
 'label'=>'F05 · Filtro sobre last_candidate_ids (no re-ejecutar pipeline)',
 'chain'=>true,
 'steps'=>[
     ['q'=>'Rutas de senderismo en el Pirineo.',
      'expect'=>['intent'=>'route_request','routes'=>true],
      'note'=>'Llena last_candidate_ids con el pool de 50'],
     ['q'=>'Solo muéstrame las de menos de 10 km.',
      'expect'=>['intent'=>'route_followup','response'=>'route_suggestions'],
      'note'=>'Filtro sobre pool consistente, no nuevo pipeline aleatorio.'],
 ]],

['group'=>'F — Contexto/Follow-up','diff'=>'Media',
 'label'=>'F06 · Nueva búsqueda NO activa followup aunque haya active_routes',
 'chain'=>true,
 'steps'=>[
     ['q'=>'Rutas en el Pirineo.',
      'expect'=>['intent'=>'route_request','routes'=>true],
      'note'=>'Establece active_routes'],
     ['q'=>'Busca rutas de escalada en Sierra Nevada.',
      'expect'=>['intent'=>'route_request'],
      'note'=>'Nueva búsqueda explícita — pre-Haiku NO debe interceptar.'],
 ]],


// ════════════════════════════════════════════════════════════════════════════
// GRUPO A (nuevos) — Buscar rutas
// ════════════════════════════════════════════════════════════════════════════
['group'=>'A — Buscar rutas','diff'=>'Fácil',
 'label'=>'A11 · Ciudad capital como referencia geográfica (Huesca + día de semana)',
 'q'=>'Me gustaría hacer algo de alta montaña el jueves por Huesca.',
 'note'=>'Nominatim debe resolver Huesca como punto de proximidad. days_ahead ~= 4.',
 'expect'=>['intent'=>'route_request','routes'=>true]],

['group'=>'A — Buscar rutas','diff'=>'Fácil',
 'label'=>'A12 · Dificultad implícita por contexto verbal',
 'q'=>'Llevo un año sin salir al monte. Necesito algo suave por Euskadi.',
 'note'=>"Haiku debe extraer difficulty:beginner de 'suave' y 'un año sin salir'.",
 'expect'=>['intent'=>'route_request','routes'=>true]],

['group'=>'A — Buscar rutas','diff'=>'Media',
 'label'=>'A13 · Restricción de duración explícita (3-4 horas)',
 'q'=>'¿Hay alguna circular por el Valle de Hecho de unas 3-4 horas?',
 'note'=>'Verifica que el filtro de duración máxima llega al SQL y no solo al scoring.',
 'expect'=>['intent'=>'route_request']],

['group'=>'A — Buscar rutas','diff'=>'Media',
 'label'=>'A14 · Restricción inusual (perro) + condición de nieve',
 'q'=>'Busco una ruta para hacer con mi perro, sin nieve.',
 'note'=>"Campo 'perro' no existe en BD. Sonnet debe mencionarlo. 'Sin nieve' implica cota/temporada.",
 'expect'=>['intent'=>'route_request']],

['group'=>'A — Buscar rutas','diff'=>'Media',
 'label'=>'A15 · Tremil — vocabulario técnico de alta montaña',
 'q'=>'Quiero subir a un tremil este verano por Aragón.',
 'note'=>"'Tremil' = pico >3.000m. Haiku debe entender climbing o alta montaña nivel avanzado.",
 'expect'=>['intent'=>'route_request']],

['group'=>'A — Buscar rutas','diff'=>'Difícil',
 'label'=>'A16 · Popularidad invertida — rutas poco concurridas',
 'q'=>'Dame rutas donde no haya mucha gente.',
 'note'=>'Criterio de popularidad invertido. El sistema debe priorizar popularity_score bajo.',
 'manual'=>true,
 'expect'=>['intent'=>'route_request']],

['group'=>'A — Buscar rutas','diff'=>'Fácil',
 'label'=>'A17 · Estación de ski como zona geográfica',
 'q'=>'Algo de ski touring en Formigal, no muy técnico.',
 'note'=>"'No muy técnico' = beginner o intermediate. Happy path con zona estación de ski.",
 'expect'=>['intent'=>'route_request']],

['group'=>'A — Buscar rutas','diff'=>'Difícil',
 'label'=>'A18 · Restricción de desnivel máximo explícita',
 'q'=>'Rutas en el Pirineo pero sin pasarme de 1.000 metros de desnivel.',
 'note'=>'Verifica que el filtro elevacion_ganada <= 1000 llega al SQL.',
 'expect'=>['intent'=>'route_request','routes'=>true]],

['group'=>'A — Buscar rutas','diff'=>'Difícil',
 'label'=>'A19 · Vía ferrata en Riglos — actividad muy específica',
 'q'=>'Busco rutas de via ferrata en Riglos.',
 'note'=>'Vía ferrata ≈ climbing en el ENUM. Riglos (Mallos) en Huesca. Gestionar ausencia si no hay.',
 'expect'=>['intent'=>'route_request']],

// ════════════════════════════════════════════════════════════════════════════
// GRUPO B (nuevos) — Tiempo
// ════════════════════════════════════════════════════════════════════════════
['group'=>'B — Tiempo','diff'=>'Fácil',
 'label'=>'B05 · Ciudad costera (Donostia) + mañana',
 'q'=>'¿Lloverá mañana en Donostia?',
 'note'=>'Open-Meteo funciona en ciudad costera. Sonnet debe contextualizar para actividad de montaña.',
 'expect'=>['intent'=>'weather_query','response'=>'weather_info']],

['group'=>'B — Tiempo','diff'=>'Fácil',
 'label'=>'B06 · Condiciones de nieve en estación de ski',
 'q'=>'¿Cómo está la nieve en Baqueira esta semana?',
 'note'=>'Cota real, acumulación reciente y previsión. No estimada.',
 'expect'=>['intent'=>'weather_query',
            'content_contains'=>['nieve','cm','cota','acumulación','nevada','polvo','fresca']]],

['group'=>'B — Tiempo','diff'=>'Difícil',
 'label'=>'B07 · Ventana de buen tiempo — pronóstico interpretativo a varios días',
 'q'=>'¿Cuándo crees que habrá ventana de buen tiempo en el Pirineo central?',
 'note'=>'Sonnet debe identificar mejor ventana en los 16 días con incertidumbre para días > 5.',
 'expect'=>['intent'=>'weather_query',
            'content_contains'=>['incertidumbre','fiabilidad','orientativo','revisar','ventana','días']]],

['group'=>'B — Tiempo','diff'=>'Media',
 'label'=>'B08 · Condiciones actuales + interpretación actividad',
 'q'=>'Estoy en Jaca ahora mismo, ¿merece la pena salir hoy?',
 'note'=>"'Merece la pena salir' es semánticamente ambiguo: puede ser weather_query (¿hay buen tiempo?) o route_request (dame rutas). Ambos intents son válidos.",
 'manual'=>true,
 'expect'=>['intent'=>'weather_query']],

// ════════════════════════════════════════════════════════════════════════════
// GRUPO C (nuevos) — Seguridad
// ════════════════════════════════════════════════════════════════════════════
['group'=>'C — Seguridad','diff'=>'Fácil',
 'label'=>'C05 · Peligro aludes Pirineo navarro con horizonte temporal',
 'q'=>'¿Qué peligro de aludes hay esta semana en el Pirineo navarro?',
 'note'=>'EAWS para Pirineo occidental/navarro. Nivel oficial + evolución prevista.',
 'expect'=>['intent'=>'safety_query',
            'content_contains'=>['nivel','alud','peligro','navarro','EAWS','riesgo','occidental']]],

['group'=>'C — Seguridad','diff'=>'Difícil',
 'label'=>'C06 · Estado de accesos — fuera del alcance del sistema',
 'q'=>'Me han dicho que el Portalet está cortado por nieve. ¿Es verdad?',
 'note'=>'Sin datos de DGT ni cierres de carretera. Debe reconocer la limitación y sugerir fuentes.',
 'expect'=>['content_contains'=>['DGT','carretera','tráfico','oficial','información','fuente','limitación']]],

['group'=>'C — Seguridad','diff'=>'Media',
 'label'=>'C07 · Safety conceptual — temperatura mínima senderismo de invierno',
 'q'=>'¿A partir de qué temperatura es peligroso hacer senderismo en invierno?',
 'note'=>"Pregunta conceptual de seguridad — Haiku puede clasificarla como general o safety_query (ambos válidos). Lo crítico es el contenido.",
 'expect'=>['content_contains'=>['temperatura','hipotermia','viento','frío','congelación','riesgo']]],

['group'=>'C — Seguridad','diff'=>'Difícil',
 'label'=>'C08 · Aneto ruta normal — material + pronóstico a >5 días',
 'q'=>'Voy al Aneto por la ruta normal la semana que viene, ¿qué material necesito?',
 'note'=>"Pregunta de equipamiento que puede clasificarse como general o safety_query. Lo crítico: material correcto + incertidumbre en pronóstico >5 días.",
 'expect'=>['content_contains'=>['crampones','piolet','casco','arnés','material','equipo','incertidumbre','orientativo']]],

['group'=>'C — Seguridad','diff'=>'Media',
 'label'=>'C09 · Protocolo emergencia — perdido sin cobertura',
 'q'=>'¿Qué hago si me pierdo en la montaña sin cobertura?',
 'note'=>'Safety puro. Sin Open-Meteo, sin zona. Respuesta experta sobre orientación y SOS.',
 'expect'=>['intent'=>'safety_query',
            'content_contains'=>['SOS','socorro','orientar','señal','brújula','sol','ruta','calma']]],

// ════════════════════════════════════════════════════════════════════════════
// GRUPO D (nuevos) — Recomendación personalizada
// ════════════════════════════════════════════════════════════════════════════
['group'=>'D — Recomendación personalizada','diff'=>'Media',
 'label'=>'D04 · Personalized con horizonte semanal',
 'q'=>'¿Tienes algo bueno para mí esta semana?',
 'note'=>'Perfil completo (seeded). Evalúa qué día tiene mejor tiempo en zonas preferidas.',
 'expect'=>['intent'=>'personalized_recommendation','routes'=>true]],

['group'=>'D — Recomendación personalizada','diff'=>'Difícil',
 'label'=>'D05 · Historial saturado — proponer zona diferente',
 'q'=>'Llevo tres fines de semana seguidos en Aragón. Recomiéndame algo diferente.',
 'note'=>'El sistema debería elegir zona distinta de las preferidas si el historial reciente la satura.',
 'manual'=>true,
 'expect'=>['intent'=>'personalized_recommendation']],

['group'=>'D — Recomendación personalizada','diff'=>'Difícil',
 'label'=>'D06 · Señal implícita de mejora de nivel',
 'q'=>'Mi nivel ha mejorado mucho este año. ¿Qué me propones?',
 'note'=>'No puede actualizar experience_level, pero debe proponer rutas en el límite superior del nivel actual y ofrecer actualizar el perfil.',
 'manual'=>true,
 'expect'=>['intent'=>'personalized_recommendation']],

['group'=>'D — Recomendación personalizada','diff'=>'Difícil',
 'label'=>'D07 · Onboarding — 3 preguntas en un solo mensaje (requiere usuario sin perfil)',
 'q'=>'Dame una recomendación.',
 'note'=>'Requiere usuario sin perfil. Las 3 preguntas (actividad, nivel, zona) deben ir en UN SOLO mensaje conversacional.',
 'manual'=>true,
 'expect'=>['intent'=>'personalized_recommendation']],

// ════════════════════════════════════════════════════════════════════════════
// GRUPO E (nuevos) — General
// ════════════════════════════════════════════════════════════════════════════
['group'=>'E — General','diff'=>'Fácil',
 'label'=>'E04 · Técnica de rapel — general técnico puro',
 'q'=>'¿Cómo se hace el rappel?',
 'note'=>'Sin zona, sin rutas, sin tiempo. Al final debe ofrecer rutas de escalada.',
 'expect'=>['intent'=>'general',
            'content_contains'=>['cuerda','descensor','ocho','arnés','anclaje','frenado']]],

['group'=>'E — General','diff'=>'Fácil',
 'label'=>'E05 · Circular vs travesía — conceptual básico',
 'q'=>'¿Qué diferencia hay entre una ruta circular y una travesía?',
 'note'=>'Respuesta corta y directa. Sin transición contextual necesaria.',
 'expect'=>['intent'=>'general',
            'content_contains'=>['circular','travesía','inicio','regreso','punto','destino']]],

['group'=>'E — General','diff'=>'Media',
 'label'=>'E06 · GR11 por Benasque — pregunta factual de sendero',
 'q'=>'¿El GR11 pasa por Benasque?',
 'note'=>"Haiku puede clasificar como general (factual) o route_request (Benasque implica búsqueda). Respuesta correcta en ambos casos. Lo crítico: mencionar GR11 y Benasque.",
 'expect'=>['content_contains'=>['GR11','Benasque','etapa','Pirenaica','sendero','sí']]],

// ════════════════════════════════════════════════════════════════════════════
// GRUPO F (nuevos) — Contexto y follow-up (conversaciones encadenadas)
// ════════════════════════════════════════════════════════════════════════════
['group'=>'F — Contexto/Follow-up','diff'=>'Fácil',
 'label'=>'F07 · Superlativo: ¿cuál es la más larga?',
 'chain'=>true,
 'steps'=>[
     ['q'=>'Rutas de senderismo en Ordesa.',
      'expect'=>['intent'=>'route_request','routes'=>true],
      'note'=>'Carga active_routes en sesión'],
     ['q'=>'¿Cuál es la más larga?',
      'expect'=>['intent'=>'route_followup'],
      'note'=>'Superlativo sobre active_routes. Re-fetch y comparación por distancia_km.'],
 ]],

['group'=>'F — Contexto/Follow-up','diff'=>'Media',
 'label'=>'F08 · Filtro de altitud sobre active_routes',
 'chain'=>true,
 'steps'=>[
     ['q'=>'Rutas de senderismo en el Valle de Benasque.',
      'expect'=>['intent'=>'route_request','routes'=>true],
      'note'=>'Carga active_routes en sesión'],
     ['q'=>'¿Alguna de esas llega a más de 2.000 metros?',
      'expect'=>['intent'=>'route_followup'],
      'note'=>'Filtro de altitud. Sonnet debe usar elevacion_max reales, no inventar si el campo está vacío.'],
 ]],

['group'=>'F — Contexto/Follow-up','diff'=>'Media',
 'label'=>'F09 · Filtro de dificultad sobre last_candidate_ids',
 'chain'=>true,
 'steps'=>[
     ['q'=>'Rutas de senderismo en el Pirineo aragonés.',
      'expect'=>['intent'=>'route_request','routes'=>true],
      'note'=>'Llena last_candidate_ids con el pool de candidatas'],
     ['q'=>'Muéstrame solo las de dificultad avanzada.',
      'expect'=>['intent'=>'route_followup'],
      'note'=>'Filtro por dificultad sobre pool existente. Si no hay avanzadas, debe decirlo sin inventar.'],
 ]],

['group'=>'F — Contexto/Follow-up','diff'=>'Media',
 'label'=>'F10 · Seguimiento interpretativo sobre datos de tiempo ya mostrados',
 'chain'=>true,
 'steps'=>[
     ['q'=>'¿Qué tiempo hace en Candanchú?',
      'expect'=>['intent'=>'weather_query','response'=>'weather_info'],
      'note'=>'Establece last_zone=Candanchú y datos de tiempo recientes en sesión'],
     ['q'=>'¿Y para esquiar está bien?',
      'expect'=>['intent'=>'weather_query'],
      'note'=>'Follow-up interpretativo. Debe usar datos ya mostrados sin rellamar a Open-Meteo.'],
 ]],

['group'=>'F — Contexto/Follow-up','diff'=>'Difícil',
 'label'=>'F11 · Follow-up con restricción no estructurada (hijos de 8 años)',
 'chain'=>true,
 'steps'=>[
     ['q'=>'Rutas de senderismo en el Pirineo.',
      'expect'=>['intent'=>'route_request','routes'=>true],
      'note'=>'Carga active_routes en sesión'],
     ['q'=>'¿Puedo hacer la tercera con mis hijos de 8 años?',
      'expect'=>['intent'=>'route_followup'],
      'note'=>"No hay campo 'niños' en BD. Sonnet debe razonar con la dificultad real de la ruta."],
 ]],

];

// ── Inicializar AiCore ────────────────────────────────────────────────────────
if (!$isCli) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Kahir Test</title>"
       . "<style>body{background:#0f172a;color:#e2e8f0;font-family:monospace;font-size:13px;padding:20px;max-width:1100px}"
       . "h1{color:#60a5fa} h3{color:#22d3ee;margin:14px 0 3px;border-bottom:1px solid #1e293b;padding-bottom:3px}"
       . ".dim{color:#64748b} a{color:#60a5fa}</style></head><body>"
       . "<h1>🧪 Kahir.AI — Test Suite</h1>"
       . "<p class='dim'>" . date('Y-m-d H:i:s') . " | usuario:" . TEST_USER_ID . "</p>";
}

try {
    $conn   = DBConnection::getInstance()->getConnection();
    $aiCore = new AICore($conn);
} catch (\Throwable $e) {
    p(c('⛔ Error inicializando AiCore: ' . $e->getMessage(), 'red'));
    exit(1);
}

// ── Seed de perfil para D01 ───────────────────────────────────────────────────
// D01 valida "Personalized con perfil completo". El usuario de test (id=1) necesita
// un perfil con actividad, nivel y zona para que handlePersonalizedRecommendation()
// no caiga en el flujo de onboarding.
//
// IMPORTANTE: total_interactions lo lee UserPreferenceService desde
// preferencias_inferidas->total_interactions (JSON), NO desde la tabla interacciones.
// El umbral en AiCore.php es < 3; lo forzamos a 5 para tener margen.
try {
    $uid     = (int)TEST_USER_ID;
    $inferidas = json_encode(['total_interactions' => 5, 'avg_view_time' => 0]);

    $conn->query(
        "INSERT INTO user_preferences
             (usuario_id, actividades_preferidas, ubicaciones_habituales,
              nivel_experiencia, dificultad_habitual, preferencias_inferidas)
         VALUES ({$uid}, '[\"hiking\"]', '[\"Aragon\"]', 'intermediate', 'Intermediate', '{$inferidas}')
         ON DUPLICATE KEY UPDATE
             actividades_preferidas  = '[\"hiking\"]',
             ubicaciones_habituales  = '[\"Aragon\"]',
             nivel_experiencia       = 'intermediate',
             dificultad_habitual     = 'Intermediate',
             preferencias_inferidas  = '{$inferidas}'"
    );
} catch (\Throwable) { /* silenciar — no debe bloquear los tests */ }

// ── Ejecutar ──────────────────────────────────────────────────────────────────
$results   = [];   // acumula todos los resultados para el informe final
$lastGroup = '';
$total = $pass = $fail = $slow = $manual = 0;

foreach ($CASES as $case) {
    $grp = $case['group'] ?? '';
    if ($grp !== $lastGroup) { section($grp); $lastGroup = $grp; }

    if (!empty($case['chain'])) {
        $sessionId = freshSession($conn);
        p(c("  ┆ cadena · sesión:{$sessionId}", 'dim'));
        foreach ($case['steps'] as $si => $step) {
            $total++;
            $label = $case['label'] . " [paso " . ($si+1) . "/" . count($case['steps']) . "]";
            $r = runTest($aiCore, $step['q'], $step['expect'] ?? [], $sessionId,
                         $label, $step['note'] ?? '', $case['diff'] ?? '?', false);
            $results[] = $r;
            if ($r['manual'])  $manual++;
            elseif ($r['ok'])  $pass++;
            else               $fail++;
            if ($r['ms'] >= SLOW_MS) $slow++;
            if ($si < count($case['steps'])-1) usleep(PAUSE_MS * 1000);
        }
    } else {
        $total++;
        $sessionId = freshSession($conn);
        $r = runTest($aiCore, $case['q'], $case['expect'] ?? [], $sessionId,
                     $case['label'], $case['note'] ?? '', $case['diff'] ?? '?',
                     $case['manual'] ?? false);
        $results[] = $r;
        if ($r['manual'])  $manual++;
        elseif ($r['ok'])  $pass++;
        else               $fail++;
        if ($r['ms'] >= SLOW_MS) $slow++;
        usleep(PAUSE_MS * 1000);
    }
}

// ── INFORME FINAL ─────────────────────────────────────────────────────────────
$logFile = LOG_PATH . '/kahir_audit_' . date('Y-m-d') . '.log';

p(''); p('');
if ($isCli) {
    p(c('  ╔══════════════════════════════════════════════════════════╗','cyan'));
    p(c('  ║               INFORME DE RESULTADOS                     ║','cyan'));
    p(c('  ╚══════════════════════════════════════════════════════════╝','cyan'));
} else {
    echo "<h2 style='color:#60a5fa;margin-top:24px'>📊 Informe de Resultados</h2>";
}

// Métricas globales
$autoTotal = $total - $manual;
$pct       = $autoTotal > 0 ? round($pass / $autoTotal * 100) : 0;
$col       = $pct >= 85 ? 'green' : ($pct >= 65 ? 'yellow' : 'red');

p(c("  Resultado global: {$pass}/{$autoTotal} automáticos correctos ({$pct}%)", $col));
p(c("  Revisión manual:  {$manual} casos | Lentos (>".SLOW_MS."ms): {$slow}", 'dim'));

// Por grupo
p('');
p(c('  ── Por grupo ─────────────────────────────────────────────', 'dim'));
$byGroup = [];
foreach ($results as $r) {
    $g = $r['group'];
    if (!isset($byGroup[$g])) $byGroup[$g] = ['pass'=>0,'fail'=>0,'manual'=>0,'slow'=>0];
    if ($r['manual'])          $byGroup[$g]['manual']++;
    elseif ($r['ok'])          $byGroup[$g]['pass']++;
    else                       $byGroup[$g]['fail']++;
    if ($r['ms'] >= SLOW_MS)   $byGroup[$g]['slow']++;
}
foreach ($byGroup as $g => $m) {
    $t   = $m['pass'] + $m['fail'];
    $pct = $t > 0 ? round($m['pass']/$t*100) : 0;
    $col = $pct >= 85 ? 'green' : ($pct >= 65 ? 'yellow' : 'red');
    $man = $m['manual'] > 0 ? c(" ({$m['manual']} manual)", 'dim') : '';
    $sl  = $m['slow']   > 0 ? c(" ⚡{$m['slow']} lentos", 'yellow') : '';
    p("  " . c(str_pad(substr($g,0,30),32), 'bold') . c("{$m['pass']}/{$t} ({$pct}%)", $col) . $man . $sl);
}

// Por dificultad
p('');
p(c('  ── Por dificultad ────────────────────────────────────────', 'dim'));
$byDiff = [];
foreach ($results as $r) {
    $d = $r['diff'];
    if (!isset($byDiff[$d])) $byDiff[$d] = ['pass'=>0,'fail'=>0];
    if (!$r['manual']) { if ($r['ok']) $byDiff[$d]['pass']++; else $byDiff[$d]['fail']++; }
}
foreach (['Fácil','Media','Difícil'] as $d) {
    if (!isset($byDiff[$d])) continue;
    $t   = $byDiff[$d]['pass'] + $byDiff[$d]['fail'];
    $pct = $t > 0 ? round($byDiff[$d]['pass']/$t*100) : 0;
    $col = $pct >= 85 ? 'green' : ($pct >= 65 ? 'yellow' : 'red');
    p("  " . c(str_pad($d,12), 'bold') . c("{$byDiff[$d]['pass']}/{$t} ({$pct}%)", $col));
}

// Fallos detallados
$failures = array_filter($results, fn($r) => !$r['ok'] && !$r['manual']);
if (!empty($failures)) {
    p(''); p(c('  ── Fallos detallados ─────────────────────────────────────', 'red'));
    foreach ($failures as $r) {
        p("  " . c('❌ ' . $r['label'], 'red'));
        foreach ($r['errors'] as $e) p(c("     ✗ {$e}", 'red'));
        p(c('     Q: "' . mb_substr($r['q'], 0, 80) . '"', 'dim'));
        p(c("     intent:{$r['intent']} type:{$r['type']} routes:{$r['routes']}", 'dim'));
    }
}

// Casos manuales
$manuals = array_filter($results, fn($r) => $r['manual']);
if (!empty($manuals)) {
    p(''); p(c('  ── Revisión manual requerida ─────────────────────────────', 'yellow'));
    foreach ($manuals as $r) {
        p("  " . c('👁 ' . $r['label'], 'yellow'));
        p(c('     Q: "' . mb_substr($r['q'], 0, 80) . '"', 'dim'));
        p(c("     intent:{$r['intent']} type:{$r['type']} routes:{$r['routes']} [{$r['ms']}ms]", 'dim'));
        if ($r['note']) p(c("     → {$r['note']}", 'dim'));
    }
}

p('');
p(c("  Audit log: {$logFile}", 'dim'));
p('');

if (!$isCli) echo "</body></html>";

// ── Función de ejecución + validación ─────────────────────────────────────────
function runTest(
    AICore $aiCore,
    string $query,
    array  $expect,
    int    $sessionId,
    string $label,
    string $note,
    string $diff,
    bool   $isManual
): array {
    $t0 = microtime(true);
    try {
        $result = $aiCore->processQuery($query, TEST_USER_ID, [], $sessionId);
        $ms     = (int)round((microtime(true) - $t0) * 1000);
    } catch (\Throwable $e) {
        $ms = (int)round((microtime(true) - $t0) * 1000);
        p("  " . c('❌', 'red') . " " . c($label, 'bold') . c(" [ERROR: " . mb_substr($e->getMessage(),0,60) . "]", 'red'));
        p(c('     Q: "' . mb_substr($query, 0, 80) . '"', 'dim'));
        $group = explode(' — ', $label)[0] ?? '';
        return ['ok'=>false,'manual'=>$isManual,'errors'=>['EXCEPCIÓN: '.$e->getMessage()],
                'label'=>$label,'q'=>$query,'group'=>$group,'diff'=>$diff,
                'intent'=>'ERROR','type'=>'error','routes'=>0,'ms'=>$ms,'note'=>$note];
    }

    $errors = [];
    $gotIntent   = $result['intent']       ?? '?';
    $gotType     = $result['type']         ?? '?';
    $gotRoutes   = count($result['routes'] ?? []);
    $gotContent  = $result['content']      ?? '';

    if (!$isManual) {
        if (isset($expect['intent']) && $gotIntent !== $expect['intent'])
            $errors[] = "intent esperado [{$expect['intent']}] → obtenido [{$gotIntent}]";
        if (isset($expect['response']) && $gotType !== $expect['response'])
            $errors[] = "response_type esperado [{$expect['response']}] → obtenido [{$gotType}]";
        if (!empty($expect['routes']) && $gotRoutes === 0)
            $errors[] = "se esperaban rutas → llegaron 0";
        if (!empty($expect['no_routes']) && $gotRoutes > 0)
            $errors[] = "NO se esperaban rutas (fuera de cobertura) → llegaron {$gotRoutes}";
        if (!empty($expect['content_contains'])) {
            $haystack = mb_strtolower($gotContent);
            $found    = false;
            foreach ($expect['content_contains'] as $kw) {
                if (mb_strpos($haystack, mb_strtolower($kw)) !== false) { $found = true; break; }
            }
            if (!$found) $errors[] = "content_contains: ninguna de [" . implode('|', $expect['content_contains']) . "] en la respuesta";
        }
    }

    $ok      = $isManual || empty($errors);
    $msColor = $ms >= SLOW_MS ? 'yellow' : 'dim';
    $icon    = $isManual ? c('👁', 'yellow') : ($ok ? c('✅', 'green') : c('❌', 'red'));
    $diffCol = ['Fácil'=>'green','Media'=>'yellow','Difícil'=>'red'][$diff] ?? 'dim';

    p("  {$icon} " . c($label, 'bold') . "  "
      . c("intent:{$gotIntent} type:{$gotType} routes:{$gotRoutes}", '')
      . "  " . c("[{$ms}ms]", $msColor) . " " . c("[{$diff}]", $diffCol));
    if ($note) p(c("     ↳ {$note}", 'dim'));
    foreach ($errors as $err) p(c("     ✗ {$err}", 'red'));
    p(c('     Q: "' . mb_substr($query, 0, 80) . '"', 'dim'));

    // Mostrar fragmento de la respuesta para casos de calidad
    if (!empty($expect['content_contains']) && !empty($gotContent)) {
        p(c('     R: "' . mb_substr($gotContent, 0, 120) . '…"', 'dim'));
    }

    $group = explode(' — ', $label)[0] ?? $label;
    return ['ok'=>$ok,'manual'=>$isManual,'errors'=>$errors,
            'label'=>$label,'q'=>$query,'group'=>$group,'diff'=>$diff,
            'intent'=>$gotIntent,'type'=>$gotType,'routes'=>$gotRoutes,'ms'=>$ms,'note'=>$note];
}
