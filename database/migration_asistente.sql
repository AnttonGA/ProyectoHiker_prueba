-- Fase D del asistente: descripciones generadas a partir del track.
-- Aplicar después de migration_seguridad.sql y migration_fase1.sql.
-- Solo añade una columna; no modifica ni borra datos existentes.

ALTER TABLE rutas
    ADD COLUMN descripcion_auto TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = descripción generada automáticamente (tools/generar_contenido_rutas.php), no escrita por una persona'
        AFTER descripcion;
