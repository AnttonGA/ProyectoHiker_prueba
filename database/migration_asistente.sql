-- Fase D del asistente: descripciones generadas a partir del track e índice para buscar por cercanía.
-- Aplicar después de migration_seguridad.sql y migration_fase1.sql.
-- Se puede ejecutar más de una vez: cada cambio comprueba antes si ya existe.
-- Solo añade una columna y un índice; no modifica ni borra datos existentes.

-- 1. rutas.descripcion_auto (1 = descripción generada por tools/generar_contenido_rutas.php)
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rutas' AND COLUMN_NAME = 'descripcion_auto');
SET @sql := IF(@existe = 0,
    'ALTER TABLE rutas ADD COLUMN descripcion_auto TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = descripción generada automáticamente (tools/generar_contenido_rutas.php), no escrita por una persona'' AFTER descripcion',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. Índice por punto de inicio (la búsqueda filtra primero por una caja de latitud/longitud)
SET @existe := (SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rutas' AND INDEX_NAME = 'idx_rutas_inicio');
SET @sql := IF(@existe = 0,
    'ALTER TABLE rutas ADD INDEX idx_rutas_inicio (start_lat, start_lng)',
    'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
