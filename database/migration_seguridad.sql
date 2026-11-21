-- =============================================================================
-- Kahir.AI — Migración de seguridad (tokens de verificación y de recuperación)
-- =============================================================================
-- Aplicar sobre la base de datos de la app (kahir_db en local; la misma en la
-- Raspberry) ANTES de desplegar el código de la rama `seguridad`.
--
-- Qué hace:
--   1. Añade a `usuarios` las columnas que necesitan la verificación de email y
--      la recuperación de contraseña (no existían: esas funciones no podían ir).
--   2. Marca como verificados a los usuarios que ya existían, para que no se
--      queden sin poder entrar al empezar a exigir la verificación.
--
-- No borra ni cambia contraseñas. Hacer copia de la base de datos antes.
-- Cómo aplicarla: phpMyAdmin/HeidiSQL → base de datos → pestaña SQL → ejecutar.
-- =============================================================================

ALTER TABLE `usuarios`
  ADD COLUMN `token_verificacion` CHAR(64) NULL DEFAULT NULL AFTER `email_verificado`,
  ADD COLUMN `reset_token_hash`   CHAR(64) NULL DEFAULT NULL AFTER `token_verificacion`,
  ADD COLUMN `reset_expira`       DATETIME NULL DEFAULT NULL AFTER `reset_token_hash`,
  ADD INDEX `idx_token_verificacion` (`token_verificacion`),
  ADD INDEX `idx_reset_token_hash` (`reset_token_hash`);

-- Usuarios anteriores a la verificación por email: se dan por verificados
UPDATE `usuarios`
   SET `email_verificado` = 1
 WHERE `email_verificado` = 0
   AND `token_verificacion` IS NULL;

-- -----------------------------------------------------------------------------
-- Nombrar un administrador (EDITAR el nombre de usuario antes de ejecutar).
-- El panel de administración exige rol = 'admin' y ahora mismo no hay ninguno.
-- -----------------------------------------------------------------------------
-- UPDATE `usuarios` SET `rol` = 'admin' WHERE `username` = 'TU_USUARIO';
