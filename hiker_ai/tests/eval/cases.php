<?php
/**
 * Casos de la batería de evaluación. Cada caso es una conversación (varios turnos).
 * Coordenadas de referencia: Jaca 42.57,-0.55 · Sallent/Valle de Tena 42.76,-0.33 ·
 * Bilbao 43.26,-2.93 · Pamplona 42.81,-1.64 · Benasque 42.60,0.52 · Ordesa 42.65,-0.06
 */
$GENERIC_BAD = ['razonamiento', 'json', 'intent', '{"', 'como modelo de lenguaje'];
$BIZKAIA = [43.0, 43.46, -3.46, -2.40, 'Bizkaia'];

return [
    'jaca' => ['turns' => [
        ['q' => 'Quiero hacer una ruta de senderismo cerca de Jaca este sábado', 'checks' => [
            'has_routes' => 3, 'near' => [42.57, -0.55, 40], 'activity' => 'hiking', 'min_km' => 2,
            'no_invented_numbers' => true, 'text_none' => $GENERIC_BAD]],
        ['q' => '¿Y algo más corto?', 'checks' => [
            'has_routes' => 1, 'near' => [42.57, -0.55, 40], 'shorter_than_prev' => true]],
        ['q' => '¿Qué tiempo va a hacer allí?', 'checks' => [
            'text_any' => ['sábado', 'temperatura', 'ºc', '°c', 'grados'], 'text_none' => $GENERIC_BAD]],
    ]],
    'tena' => ['turns' => [
        ['q' => 'Busco una ruta de esquí de travesía en el Valle de Tena de nivel intermedio', 'checks' => [
            'has_routes' => 1, 'near' => [42.76, -0.33, 30], 'activity' => 'ski_touring',
            'text_any' => ['alud', 'aludes', 'boletín', 'nieve']]],
    ]],
    'aludes' => ['turns' => [
        ['q' => '¿Cómo está el riesgo de aludes en el Pirineo aragonés?', 'checks' => [
            'text_any' => ['boletín', 'aemet', 'météo-france', 'no tengo', 'no dispongo', 'no he podido', 'no está disponible', 'no puedo consultar'],
            'text_none' => array_merge($GENERIC_BAD, ['eaws'])]],
    ]],
    'nivel' => ['turns' => [
        ['q' => 'Recomiéndame una ruta para mi nivel', 'checks' => [
            'has_routes' => 1, 'min_km' => 2, 'no_invented_numbers' => true, 'text_none' => $GENERIC_BAD]],
    ]],
    'bizkaia' => ['turns' => [
        ['q' => 'Rutas en Bizkaia con menos de 500 metros de desnivel', 'checks' => [
            'has_routes' => 3, 'bbox' => $BIZKAIA, 'max_elevation' => 500, 'min_km' => 2]],
        ['q' => 'Compara las dos primeras rutas que me diste', 'checks' => [
            'compares' => true, 'text_none' => $GENERIC_BAD]],
    ]],
    'material' => ['turns' => [
        ['q' => '¿Qué material necesito para una ruta invernal con nieve?', 'checks' => [
            'text_any' => ['crampones', 'piolet', 'arva', 'raquetas'], 'text_none' => $GENERIC_BAD]],
    ]],
    'alpes' => ['turns' => [
        ['q' => 'Rutas de senderismo en los Alpes suizos', 'checks' => [
            'no_routes' => true, 'text_any' => ['pirineo', 'no tengo', 'no dispongo', 'fuera', 'cobertura', 'euskadi', 'país vasco']]],
    ]],
    'ninos' => ['turns' => [
        ['q' => 'Una ruta fácil para ir con niños cerca de Bilbao', 'checks' => [
            'has_routes' => 2, 'near' => [43.26, -2.93, 35], 'difficulty_in' => ['beginner', 'easy'], 'min_km' => 1]],
    ]],
    'hola' => ['turns' => [
        ['q' => 'hola', 'checks' => ['no_routes' => true, 'text_none' => $GENERIC_BAD]],
    ]],
    'benasque' => ['turns' => [
        ['q' => 'Ruta exigente de montaña desde Benasque, unas 6 horas', 'checks' => [
            'has_routes' => 2, 'near' => [42.60, 0.52, 30], 'activity' => 'hiking', 'no_invented_numbers' => true]],
    ]],
    'ordesa' => ['turns' => [
        ['q' => 'Rutas por Ordesa', 'checks' => [
            'has_routes' => 2, 'near' => [42.65, -0.06, 25], 'min_km' => 2]],
        ['q' => 'Cuéntame más de la primera', 'checks' => [
            'has_routes' => 1, 'no_invented_numbers' => true, 'text_none' => $GENERIC_BAD]],
    ]],
    'btt' => ['turns' => [
        ['q' => 'Una ruta en BTT cerca de Pamplona', 'checks' => [
            'has_routes' => 1, 'near' => [42.81, -1.64, 40], 'activity' => 'btt']],
    ]],
    'english' => ['turns' => [
        ['q' => 'Can you suggest an easy hike near San Sebastian?', 'checks' => [
            'has_routes' => 1, 'near' => [43.32, -1.98, 35], 'text_any' => ['route', 'hike', 'trail', 'walk']]],
    ]],
    'gorbea' => ['turns' => [
        ['q' => 'Quiero subir al Gorbea', 'checks' => [
            'has_routes' => 1, 'near' => [43.03, -2.78, 20], 'no_invented_numbers' => true]],
    ]],
    'manana' => ['turns' => [
        // Debe hablar del día de mañana por su nombre (lunes, martes…)
        ['q' => '¿Qué tiempo hará mañana en Benasque?', 'checks' => [
            'type_in' => ['weather_info'],
            'text_any' => [['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'][(int) date('w', strtotime('+1 day'))]],
            'text_none' => $GENERIC_BAD]],
    ]],
    'lejano' => ['turns' => [
        // Fuera del horizonte de previsión: debe decirlo, no inventar
        ['q' => '¿Qué tiempo hará en Jaca el ' . (int) date('j', strtotime('+40 days')) . ' de '
              . ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'][(int) date('n', strtotime('+40 days'))] . '?',
         'checks' => ['text_any' => ['todavía no hay previsión', 'no hay previsión', 'días vista', 'demasiado lejos'], 'text_none' => ['°c']]],
    ]],
    'plan' => ['turns' => [
        ['q' => 'Busco una ruta por Panticosa para el sábado', 'checks' => [
            'has_routes' => 2, 'near' => [42.72, -0.28, 25], 'text_any' => ['sábado']]],
        ['q' => '¿Cuál me conviene si no quiero pasar de 3 horas?', 'checks' => [
            'text_none' => $GENERIC_BAD, 'no_invented_numbers' => true]],
        ['q' => 'Vale, apúntame la primera para el sábado', 'checks' => [
            'saved_list' => true, 'text_any' => ['sábado']]],
    ]],
    'finde' => ['turns' => [
        ['q' => '¿Qué ruta me recomiendas para este fin de semana en el Pirineo navarro?', 'checks' => [
            'has_routes' => 2, 'bbox' => [42.75, 43.1, -1.6, -0.7, 'Pirineo navarro'],
            'text_any' => ['sábado', 'domingo', 'previsión']]],
    ]],
    // ── Regresiones de la revisión de noviembre de 2026 ──────────────────────
    'hecho' => ['turns' => [
        // "hecho" es un verbo aquí, no el Valle de Hecho
        ['q' => 'Rutas cerca de Bilbao que no haya hecho', 'checks' => [
            'has_routes' => 2, 'near' => [43.26, -2.93, 40]]],
    ]],
    'andes' => ['turns' => [
        // "andes" es el verbo andar, no la cordillera
        ['q' => 'Una ruta cerca de Jaca en la que no andes mucho', 'checks' => [
            'has_routes' => 1, 'near' => [42.57, -0.55, 40]]],
    ]],
    'contexto' => ['turns' => [
        ['q' => 'Rutas en el Gorbea', 'checks' => ['has_routes' => 1, 'near' => [43.03, -2.78, 25]]],
        ['q' => '¿Qué tiempo hará en Ordesa el sábado?', 'checks' => [
            'type_in' => ['weather_info'], 'text_any' => ['ordesa'], 'text_none' => ['gorbea']]],
        // "¿y el domingo?" sigue hablando de Ordesa, no del Gorbea
        ['q' => '¿Y el domingo?', 'checks' => [
            'type_in' => ['weather_info'], 'text_any' => ['ordesa', 'domingo'], 'text_none' => ['gorbea']]],
    ]],
    'filtros' => ['turns' => [
        ['q' => 'Rutas de senderismo cerca de Jaca', 'checks' => ['has_routes' => 3]],
        ['q' => '¿Alguna más corta?', 'checks' => ['has_routes' => 1, 'shorter_than_prev' => true]],
        // "más larga" sustituye al límite anterior en vez de sumarse ("no hay ninguna")
        ['q' => '¿Y alguna más larga?', 'checks' => ['has_routes' => 1]],
    ]],
    'cual_tiempo' => ['turns' => [
        ['q' => 'Rutas por Panticosa', 'checks' => ['has_routes' => 2]],
        // Lleva "cuál" pero pregunta por el tiempo: no es una comparación de rutas
        ['q' => '¿Cuál será el tiempo el sábado?', 'checks' => [
            'type_in' => ['weather_info'], 'text_any' => ['sábado']]],
    ]],
    'franja' => ['turns' => [
        // "por la mañana" es una franja del sábado, no el día de mañana
        ['q' => '¿Qué tiempo hará el sábado por la mañana en Benasque?', 'checks' => [
            'type_in' => ['weather_info'], 'text_any' => ['sábado'],
            'text_none' => array_values(array_diff(
                [['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'][(int) date('w', strtotime('+1 day'))]],
                ['sábado']))]],
    ]],
    'comparacion' => ['turns' => [
        // Una comparación con los Alpes no es una petición fuera de cobertura
        ['q' => 'Quiero algo con paisaje como los Alpes pero cerca de Benasque', 'checks' => [
            'has_routes' => 1, 'near' => [42.60, 0.52, 40]]],
    ]],
    'candanchu' => ['turns' => [
        // Conversación real revisada en noviembre de 2026: distancias coherentes, nieve con datos, previsión
        ['q' => 'Busco una ruta fácil cerca de Candanchú', 'checks' => [
            'has_routes' => 2, 'near' => [42.79, -0.53, 25], 'no_invented_numbers' => true]],
        ['q' => '¿Qué tiempo hace en esa zona?', 'checks' => [
            'type_in' => ['weather_info'], 'text_any' => ['candanchú', 'candanchu']]],
        ['q' => '¿Y cómo viene la semana?', 'checks' => [
            'type_in' => ['weather_info'], 'text_any' => ['sábado', 'domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes']]],
    ]],
];
