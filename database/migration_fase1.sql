-- =============================================================================
-- Kahir.AI — Migración Fase 1 (personalización)
-- =============================================================================
-- Aplicar después de migration_seguridad.sql. Hacer copia de la BD antes.
--
-- 1. Esfuerzo tipo MIDE en cada ruta, calculado solo (columnas generadas):
--      tiempo horizontal = distancia_km / 4 km/h
--      tiempo vertical   = elevacion_ganada / 400 m/h
--      horas_mide        = el mayor + la mitad del menor   (método horario MIDE)
--      esfuerzo (1–5)    = ≤1 h · 1–3 h · 3–6 h · 6–10 h · >10 h   (escala MIDE)
--    Es aproximado: solo se conoce el desnivel de subida, no el de bajada.
--    Al ser columnas generadas se mantienen al día solas (subidas, importación OSM,
--    edición en el panel) sin tocar el código que inserta rutas.
--    La expresión se repite en ambas columnas porque MariaDB no permite que una
--    columna generada dependa de otra.
--
-- 2. Tabla route_feedback: valoración del usuario tras completar una ruta
--    (fácil / adecuada / dura + estrellas 1–5).
-- =============================================================================

ALTER TABLE `rutas`
  ADD COLUMN `horas_mide` DECIMAL(7,2)
    GENERATED ALWAYS AS (
      IF(`distancia_km` IS NULL AND `elevacion_ganada` IS NULL, NULL,
         GREATEST(COALESCE(`distancia_km`, 0) / 4, COALESCE(`elevacion_ganada`, 0) / 400)
         + LEAST(COALESCE(`distancia_km`, 0) / 4, COALESCE(`elevacion_ganada`, 0) / 400) / 2)
    ) STORED,
  ADD COLUMN `esfuerzo` TINYINT UNSIGNED
    GENERATED ALWAYS AS (
      CASE
        WHEN `distancia_km` IS NULL AND `elevacion_ganada` IS NULL THEN NULL
        WHEN GREATEST(COALESCE(`distancia_km`, 0) / 4, COALESCE(`elevacion_ganada`, 0) / 400)
             + LEAST(COALESCE(`distancia_km`, 0) / 4, COALESCE(`elevacion_ganada`, 0) / 400) / 2 <= 1  THEN 1
        WHEN GREATEST(COALESCE(`distancia_km`, 0) / 4, COALESCE(`elevacion_ganada`, 0) / 400)
             + LEAST(COALESCE(`distancia_km`, 0) / 4, COALESCE(`elevacion_ganada`, 0) / 400) / 2 <= 3  THEN 2
        WHEN GREATEST(COALESCE(`distancia_km`, 0) / 4, COALESCE(`elevacion_ganada`, 0) / 400)
             + LEAST(COALESCE(`distancia_km`, 0) / 4, COALESCE(`elevacion_ganada`, 0) / 400) / 2 <= 6  THEN 3
        WHEN GREATEST(COALESCE(`distancia_km`, 0) / 4, COALESCE(`elevacion_ganada`, 0) / 400)
             + LEAST(COALESCE(`distancia_km`, 0) / 4, COALESCE(`elevacion_ganada`, 0) / 400) / 2 <= 10 THEN 4
        ELSE 5
      END
    ) STORED,
  ADD INDEX `idx_esfuerzo` (`esfuerzo`);

CREATE TABLE IF NOT EXISTS `route_feedback` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `usuario_id`  INT NOT NULL,
  `ruta_id`     INT NOT NULL,
  `percepcion`  ENUM('facil','adecuada','dura') NOT NULL,
  `estrellas`   TINYINT UNSIGNED NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_usuario_ruta` (`usuario_id`, `ruta_id`),
  KEY `idx_ruta` (`ruta_id`),
  CONSTRAINT `fk_feedback_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_feedback_ruta`    FOREIGN KEY (`ruta_id`)    REFERENCES `rutas` (`id`)    ON DELETE CASCADE,
  CONSTRAINT `chk_estrellas` CHECK (`estrellas` IS NULL OR `estrellas` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
