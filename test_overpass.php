<?php
// Servidores Overpass alternativos
$servers = [
    'https://overpass-api.de/api/interpreter',
    'https://overpass.kumi.systems/api/interpreter',
    'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
];

$query = "[out:json][timeout:30];\nrelation[\"route\"=\"hiking\"](42.5,-6.0,43.0,-5.0);\nout geom qt 5;";

foreach ($servers as $server) {
    echo "Probando: {$server}\n";
    $url = $server . '?data=' . urlencode($query);
    $ctx = stream_context_create(['http' => [
        'timeout'       => 40,
        'user_agent'    => 'KahirAI/1.0',
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        echo "  → FALLO (sin respuesta)\n";
        $e = error_get_last();
        echo "  → " . ($e['message'] ?? 'desconocido') . "\n";
    } else {
        echo "  → HTTP: " . ($http_response_header[0] ?? '?') . "\n";
        echo "  → Bytes: " . strlen($raw) . "\n";
        $data = json_decode($raw, true);
        echo "  → Elementos: " . count($data['elements'] ?? []) . "\n";
        if (isset($data['remark'])) echo "  → Remark: " . $data['remark'] . "\n";
    }
    echo "\n";
}
