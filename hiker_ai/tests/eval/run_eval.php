<?php
/**
 * Batería de evaluación del asistente Kahir (CLI).
 *
 *   php hiker_ai/tests/eval/run_eval.php            → ejecuta todos los casos
 *   php hiker_ai/tests/eval/run_eval.php jaca,tena  → solo esos casos
 *   … --keep                                          → conserva en la BD lo que escribe
 *
 * Usa el mismo recorrido que chat_handler.php (ChatManager + AICore) con el usuario
 * de prueba local y comprueba automáticamente cada respuesta. Hace llamadas reales
 * a la API de Claude (coste: ~1–3 llamadas por pregunta).
 * Guarda el resultado completo en <temp>/kahir_eval/<fecha>.json (fuera de la web; o en $KAHIR_EVAL_OUT).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo disponible desde la línea de comandos.');
}

// La batería no escribe en el registro de auditoría (no se puede deshacer con la transacción)
define('KAHIR_AUDIT_LOG', false);

$root = dirname(__DIR__, 3);
require_once $root . '/includes/env_loader.php';
load_env($root . '/.env');
require_once $root . '/hiker_ai/config/config.php';
require_once $root . '/hiker_ai/config/database.php';
require_once $root . '/vendor/autoload.php';
require_once __DIR__ . '/checks.php';
$cases = require __DIR__ . '/cases.php';

use KahirAI\Core\{AICore, ChatManager};
use KahirAI\Database\DBConnection;

$EVAL_USER    = getenv('KAHIR_EVAL_USER') ?: 'prueba_admin';
$_SESSION     = ['usuario' => $EVAL_USER, 'rol' => 'admin'];
$conn         = DBConnection::getInstance()->getConnection();

$st = $conn->prepare("SELECT id FROM usuarios WHERE username = ?");
$st->bind_param('s', $EVAL_USER);
$st->execute();
$userId = (int) ($st->get_result()->fetch_assoc()['id'] ?? 0);
if (!$userId) {
    fwrite(STDERR, "No existe el usuario de evaluación '$EVAL_USER'.\n");
    exit(1);
}

$keep = in_array('--keep', $argv, true);
$args = array_values(array_filter(array_slice($argv, 1), fn($a) => $a !== '--keep'));
$only = isset($args[0]) ? array_filter(explode(',', $args[0])) : [];

// Todo lo que escribe la batería en la BD (sesiones, mensajes, zonas aprendidas, listas guardadas)
// se deshace al final de cada caso, para no gastar el límite de mensajes del usuario de pruebas
// ni ensuciar su perfil. Usa --keep para conservarlo. Las cachés de ficheros (tiempo, geocodificación)
// sí se quedan: son cachés.
$results = [];
$totalChecks = 0;
$passedChecks = 0;
$latencies = [];

foreach ($cases as $caseId => $case) {
    if ($only && !in_array($caseId, $only, true)) {
        continue;
    }
    if (!$keep) $conn->begin_transaction();
    $cm = new ChatManager();
    $ai = new AICore($conn);
    $st = $conn->prepare("INSERT INTO chat_sessions (usuario_id) VALUES (?)");
    $st->bind_param('i', $userId);
    $st->execute();
    $sid = (int) $conn->insert_id;

    $prev = null;
    foreach ($case['turns'] as $i => $turn) {
        $q = $turn['q'];
        $t = microtime(true);
        $resp = null;
        $error = null;
        $listsBefore = kahir_eval_count_lists($conn, $userId);
        try {
            $cr = $cm->processMessage($q);
            if (($cr['type'] ?? '') !== 'ready') {
                $resp = ['type' => $cr['type'] ?? 'chatmanager', 'content' => $cr['content'] ?? '', 'routes' => []];
            } else {
                $hist = $ai->getChatHistory($sid, 10);
                $resp = $ai->processQuery($q, $EVAL_USER, $hist, $sid);
                if (!empty($resp['content'])) {
                    $ai->saveChatExchange($sid, $q, $resp['content']);
                }
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $resp = ['type' => 'exception', 'content' => '', 'routes' => []];
        }
        $lat = round(microtime(true) - $t, 2);
        $latencies[] = $lat;
        $resp['_new_lists'] = kahir_eval_count_lists($conn, $userId) - $listsBefore;

        $routes = kahir_eval_routes($conn, $resp);
        $checkResults = kahir_eval_run_checks($turn['checks'] ?? [], $resp, $routes, $prev);
        foreach ($checkResults as $cr2) {
            $totalChecks++;
            if ($cr2['ok']) $passedChecks++;
        }
        $passed = count(array_filter($checkResults, fn($c) => $c['ok']));
        $n = count($checkResults);

        printf("\n[%s #%d] %s\n  tipo=%s · %d rutas · %.1fs · checks %d/%d%s\n",
            $caseId, $i + 1, $q, $resp['type'] ?? '?', count($routes), $lat, $passed, $n, $error ? " · ERROR: $error" : '');
        foreach ($checkResults as $c) {
            printf("    %s %s%s\n", $c['ok'] ? 'OK  ' : 'FALLA', $c['name'], $c['detail'] !== '' ? " — {$c['detail']}" : '');
        }
        foreach (array_slice($routes, 0, 5) as $r) {
            printf("    · #%d %s | %s | %.1f km +%d m | %s | esf %s | %s\n", $r['id'], mb_substr($r['titulo'], 0, 50),
                $r['tipo_ruta'], (float) $r['distancia_km'], (int) $r['elevacion_ganada'], $r['dificultad'], $r['esfuerzo'] ?? '-', $r['ubicacion']);
        }
        echo '    texto: ' . mb_substr(preg_replace('/\s+/', ' ', (string) ($resp['content'] ?? '')), 0, 260) . "\n";

        $results[] = [
            'case' => $caseId, 'turn' => $i + 1, 'q' => $q, 'type' => $resp['type'] ?? null,
            'latency' => $lat, 'error' => $error, 'checks' => $checkResults,
            'routes' => array_map(fn($r) => ['id' => $r['id'], 'titulo' => $r['titulo'], 'km' => (float) $r['distancia_km'],
                'desnivel' => (int) $r['elevacion_ganada'], 'dificultad' => $r['dificultad'], 'tipo' => $r['tipo_ruta']], $routes),
            'content' => $resp['content'] ?? '',
        ];
        $prev = ['resp' => $resp, 'routes' => $routes];
    }
    if (!$keep) $conn->rollback();
}

sort($latencies);
$p = fn(float $q) => $latencies ? $latencies[(int) floor(($q) * (count($latencies) - 1))] : 0;
$score = $totalChecks ? round($passedChecks / $totalChecks * 100, 1) : 0;
printf("\n==== RESULTADO: %d/%d comprobaciones (%.1f %%) · latencia p50 %.1fs · p90 %.1fs\n",
    $passedChecks, $totalChecks, $score, $p(0.5), $p(0.9));

// Resultados fuera de la carpeta pública (con nginx o sin .htaccess se servirían)
$outDir = getenv('KAHIR_EVAL_OUT') ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kahir_eval';
@mkdir($outDir, 0700, true);
$outFile = $outDir . DIRECTORY_SEPARATOR . date('Ymd-His') . '.json';
echo "Detalle guardado en $outFile\n";
file_put_contents($outFile, json_encode([
    'score' => $score, 'passed' => $passedChecks, 'total' => $totalChecks,
    'p50' => $p(0.5), 'p90' => $p(0.9), 'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

function kahir_eval_count_lists(mysqli $conn, int $userId): int {
    $st = $conn->prepare("SELECT COUNT(*) FROM user_lists WHERE usuario_id = ?");
    $st->bind_param('i', $userId);
    $st->execute();
    return (int) $st->get_result()->fetch_row()[0];
}
