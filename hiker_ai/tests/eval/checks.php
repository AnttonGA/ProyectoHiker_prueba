<?php
/**
 * Comprobaciones automáticas de la batería de evaluación.
 * Cada check devuelve ['name' => …, 'ok' => bool, 'detail' => string].
 */
declare(strict_types=1);

/** Rutas de la respuesta, completadas con los datos reales de la BD (por id). */
function kahir_eval_routes(mysqli $conn, array $resp): array {
    $ids = [];
    foreach (($resp['routes'] ?? []) as $r) {
        if (!empty($r['id'])) $ids[] = (int) $r['id'];
    }
    if (!empty($resp['route']['id'])) $ids[] = (int) $resp['route']['id'];
    foreach (($resp['compare'] ?? $resp['comparison'] ?? []) as $r) {
        if (!empty($r['id'])) $ids[] = (int) $r['id'];
    }
    $ids = array_values(array_unique(array_filter($ids)));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $conn->prepare("SELECT id, titulo, tipo_ruta, ubicacion, dificultad, distancia_km, elevacion_ganada,
                                 duracion_horas, horas_mide, esfuerzo, elevacion_max, start_lat, start_lng, estado FROM rutas WHERE id IN ($in)");
    $st->bind_param(str_repeat('i', count($ids)), ...$ids);
    $st->execute();
    $byId = [];
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $byId[(int) $row['id']] = $row;
    $out = [];
    foreach ($ids as $id) if (isset($byId[$id])) $out[] = $byId[$id];
    return $out;
}

function kahir_eval_km(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return 2 * $r * asin(min(1, sqrt($a)));
}

function kahir_eval_check(string $name, bool $ok, string $detail = ''): array {
    return ['name' => $name, 'ok' => $ok, 'detail' => $detail];
}

/** "1.200" → 1200 (millares) · "12,5" / "12.5" → 12.5 (decimales) */
function kahir_eval_num(string $s): float {
    if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) return (float) str_replace('.', '', $s);
    return (float) str_replace(',', '.', $s);
}

function kahir_eval_run_checks(array $checks, array $resp, array $routes, ?array $prev): array {
    $out = [];
    $text = mb_strtolower((string) ($resp['content'] ?? ''));
    $n = count($routes);

    // Siempre: un turno que lanza una excepción o no contesta nada es un fallo
    $out[] = kahir_eval_check('responde sin error', ($resp['type'] ?? '') !== 'exception' && trim($text) !== '',
        ($resp['type'] ?? '') === 'exception' ? 'excepción' : (trim($text) === '' ? 'texto vacío' : ''));

    foreach ($checks as $type => $arg) {
        switch ($type) {
            case 'has_routes':
                $out[] = kahir_eval_check("devuelve ≥ $arg rutas", $n >= $arg, "$n rutas");
                break;

            case 'no_routes':
                $out[] = kahir_eval_check('no devuelve rutas', $n === 0, "$n rutas");
                break;

            case 'near': // [lat, lon, km]
                [$lat, $lon, $km] = $arg;
                $inside = 0;
                $far = [];
                foreach ($routes as $r) {
                    $d = kahir_eval_km($lat, $lon, (float) $r['start_lat'], (float) $r['start_lng']);
                    if ($d <= $km) $inside++; else $far[] = '#' . $r['id'] . ' a ' . round($d) . ' km';
                }
                // Si el catálogo no tiene bastante cerca, vale ampliar el radio siempre que se diga (y hasta 2×)
                $toldWider = preg_match('/\bamplia(do|r|ndo|mos)\b|\bampli[eé]\b|más lejos|algo más lejos/u', $text) && !array_filter($routes,
                    fn($r) => kahir_eval_km($lat, $lon, (float) $r['start_lat'], (float) $r['start_lng']) > 2 * $km);
                $out[] = kahir_eval_check("todas a ≤ $km km del lugar (o lo avisa)", $n > 0 && ($inside === $n || $toldWider),
                    ($far ? implode(', ', $far) : "$inside/$n") . ($toldWider && $far ? ' · avisa de que amplía' : ''));
                break;

            case 'bbox': // [latMin, latMax, lonMin, lonMax, nombre]
                [$a, $b, $c, $d, $label] = $arg;
                $bad = array_filter($routes, fn($r) => !((float) $r['start_lat'] >= $a && (float) $r['start_lat'] <= $b
                    && (float) $r['start_lng'] >= $c && (float) $r['start_lng'] <= $d));
                $out[] = kahir_eval_check("todas en $label", $n > 0 && !$bad, $bad ? (count($bad) . " fuera") : "$n dentro");
                break;

            case 'activity':
                $bad = array_filter($routes, fn($r) => $r['tipo_ruta'] !== $arg);
                $out[] = kahir_eval_check("actividad = $arg", $n > 0 && !$bad, $bad ? count($bad) . ' de otra actividad' : '');
                break;

            case 'difficulty_in':
                $bad = array_filter($routes, fn($r) => !in_array(strtolower((string) $r['dificultad']), $arg, true));
                $honest = str_contains($text, 'no he encontrado') || str_contains($text, 'no hay rutas') || str_contains($text, 'he ampliado') || str_contains($text, 'relaj');
                $out[] = kahir_eval_check('dificultad pedida (o lo avisa)', $n > 0 && (!$bad || $honest), $bad ? count($bad) . ' con otra dificultad' : '');
                break;

            case 'max_elevation':
                $bad = array_filter($routes, fn($r) => (int) $r['elevacion_ganada'] > $arg);
                $out[] = kahir_eval_check("desnivel ≤ $arg m", $n > 0 && !$bad, $bad ? count($bad) . ' por encima' : '');
                break;

            case 'min_km':
                $bad = array_filter($routes, fn($r) => (float) $r['distancia_km'] < $arg);
                $out[] = kahir_eval_check("sin rutas basura (< $arg km)", $n > 0 && !$bad, $bad ? count($bad) . ' demasiado cortas' : '');
                break;

            case 'shorter_than_prev':
                $prevMin = $prev && $prev['routes'] ? min(array_map(fn($r) => (float) $r['distancia_km'], $prev['routes'])) : null;
                $curMax = $n ? max(array_map(fn($r) => (float) $r['distancia_km'], $routes)) : null;
                $ids = fn($rs) => array_map(fn($r) => (int) $r['id'], $rs);
                $same = $prev && $n && !array_diff($ids($routes), $ids($prev['routes']));
                $ok = $n > 0 && $prevMin !== null && ($curMax < $prevMin || ($curMax <= $prevMin && !$same));
                $out[] = kahir_eval_check('rutas más cortas que las anteriores', $ok, "máx ahora $curMax km, mín antes $prevMin km");
                break;

            case 'compares':
                // El texto debe nombrar al menos dos de las rutas de las que se habla (por su nombre)
                $pool = $routes ?: ($prev['routes'] ?? []);
                $named = 0;
                foreach ($pool as $r) {
                    $words = array_slice(array_filter(preg_split('/[\s_\-()]+/u', mb_strtolower((string) $r['titulo'])),
                        fn($w) => mb_strlen($w) > 3 && !in_array($w, ['ruta', 'desde', 'hasta', 'por', 'circular', 'subida'], true)), 0, 2);
                    if ($words && array_filter($words, fn($w) => str_contains($text, $w))) $named++;
                }
                $ok = $named >= 2 || (preg_match('/\bprimera\b/u', $text) && preg_match('/\bsegunda\b/u', $text));
                $out[] = kahir_eval_check('compara al menos 2 rutas', (bool) $ok, "$named rutas nombradas");
                break;

            case 'saved_list':
                $new = (int) ($resp['_new_lists'] ?? 0);
                $out[] = kahir_eval_check('guarda la salida de verdad (lista nueva en la BD)', $new >= 1, "$new listas nuevas");
                break;

            case 'text_any':
                $hit = array_filter($arg, fn($w) => str_contains($text, mb_strtolower($w)));
                $out[] = kahir_eval_check('menciona: ' . implode(' / ', $arg), (bool) $hit);
                break;

            case 'text_none':
                $hit = array_filter($arg, fn($w) => str_contains($text, mb_strtolower($w)));
                $out[] = kahir_eval_check('no dice: ' . implode(' / ', $arg), !$hit, $hit ? implode(', ', $hit) : '');
                break;

            case 'type_in':
                $out[] = kahir_eval_check('tipo de respuesta ' . implode('/', $arg), in_array($resp['type'] ?? '', $arg, true), (string) ($resp['type'] ?? ''));
                break;

            case 'no_invented_numbers':
                // Cifras de altitud/desnivel/km en el texto deben corresponder a alguna ruta devuelta (±15 %)
                // "a 12 km de…", "a solo 1 km" = distancia al punto de inicio (dato real, no se compara)
                $clean = preg_replace([
                    '/\ba\s+(solo\s+|unos\s+|menos\s+de\s+)?\*{0,2}\d[\d\.,]*\s?km\b/u',
                    '/\d[\d\.,]*\s?km\s*(\/|y)\s*\d+\s?min/u',          // "5 km / 12 min en coche"
                    '/\d[\d\.,]*\s?km\s+(en\s+coche|por\s+carretera)/u',
                    '/\d[\d\.,]*\s?km\/h/u',                            // velocidades de viento
                    // cotas meteorológicas: solo la cifra que sigue de cerca a isocero/cota/nieve/aludes
                    '/(isocero|cota|nieve|aludes?|por\s+encima\s+de|por\s+debajo\s+de)[^.\d]{0,30}\d[\d\.,]*\s?(m|metros)\b/u',
                ], '', $text);
                preg_match_all('/(\d[\d\.,]*)\s?(km|kilómetros|metros|m|horas|hora|h)\b/u', $clean, $m, PREG_SET_ORDER);
                $unitOf = ['km' => 'km', 'kilómetros' => 'km', 'metros' => 'm', 'm' => 'm', 'horas' => 'h', 'hora' => 'h', 'h' => 'h'];
                $known = [];
                foreach ($routes as $r) {
                    $known[] = ['m', (float) $r['elevacion_ganada']];
                    $known[] = ['km', (float) $r['distancia_km']];
                    $known[] = ['m', (float) ($r['elevacion_max'] ?? 0)];
                    $known[] = ['h', \KahirAI\Core\RouteRecommender::routeHours($r)];
                    $known[] = ['h', (float) ($r['duracion_horas'] ?? 0)];
                }
                $bad = [];
                foreach ($m as $hit) {
                    $v = kahir_eval_num(rtrim($hit[1], '.,'));
                    $unit = $unitOf[$hit[2]];
                    if ($unit === 'm' && $v < 50) continue;  // tolerar "10 m" genéricos
                    if ($unit === 'h' && $v <= 1) continue;   // "1 h antes", "cada hora"
                    $match = false;
                    foreach ($known as [$ku, $kv]) {
                        $tol = $ku === 'h' ? 0.25 : 0.15;
                        if ($ku === $unit && $kv > 0 && abs($v - $kv) / $kv <= $tol) { $match = true; break; }
                    }
                    if (!$match) $bad[] = $hit[0];
                }
                $out[] = kahir_eval_check('sin cifras inventadas', !$bad, $bad ? implode(', ', array_slice($bad, 0, 4)) : '');
                break;
        }
    }
    return $out;
}
