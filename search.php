<?php
/**
 * search.php — DESACTIVADO
 *
 * Este endpoint antiguo no lo usa ninguna página y dependía de hiker_ai/ia_integration.php,
 * que ya no existe (siempre fallaba). Además tenía escrita la contraseña de la base de datos
 * de producción (InfinityFree), que quedó publicada en GitHub: hay que cambiarla.
 *
 * El asistente actual funciona con hiker_ai/assets/js/chat_handler.php.
 */
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'error' => 'Este servicio ya no está disponible.']);
