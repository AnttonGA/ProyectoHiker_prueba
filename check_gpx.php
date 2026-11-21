<?php
// Script de mantenimiento: solo se puede ejecutar desde la línea de comandos (php archivo.php),
// nunca desde el navegador. Antes cualquiera podía lanzarlo por web sin iniciar sesión.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo disponible desde la línea de comandos.');
}
require 'paginasespecializadas/inicio_sesion/conexion.php';
$r = $conn->query("SELECT id, gpx_path, elevacion_ganada FROM rutas WHERE titulo LIKE '%Feni%' OR titulo LIKE '%Tebarray%' LIMIT 5");
while($row = $r->fetch_assoc()) {
    echo $row['id'] . ' | ' . $row['gpx_path'] . ' | elevacion_ganada=' . $row['elevacion_ganada'] . PHP_EOL;
    $gpx = __DIR__ . '/paginasespecializadas/skitouring/rutas_st/osm_import/' . $row['gpx_path'];
    if (file_exists($gpx)) {
        $xml = simplexml_load_file($gpx);
        // Contar trksegs
        $segs = $xml->xpath('//trkseg') ?: [];
        echo '  → trksegs: ' . count($segs) . PHP_EOL;
        // Puntos por cada trkseg
        foreach ($segs as $i => $seg) {
            $pts = count($seg->trkpt ?? []);
            echo "  → seg[$i]: $pts pts" . PHP_EOL;
        }
        // Total trkpts
        $allPts = $xml->xpath('//trkpt') ?: [];
        echo '  → Total trkpts: ' . count($allPts) . PHP_EOL;
        // Tiene ele?
        $hasEle = isset($allPts[0]->ele);
        echo '  → Tiene <ele>: ' . ($hasEle ? 'SI' : 'NO') . PHP_EOL;
    }
    echo PHP_EOL;
}
