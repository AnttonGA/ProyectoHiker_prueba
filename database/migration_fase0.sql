-- =================================================================
-- Kahir.AI — Migración Fase 0
-- Añade las tablas y columnas que faltan sin tocar los datos existentes.
-- Ejecutar en phpMyAdmin sobre la base de datos "peliculas"
-- =================================================================

USE peliculas;

-- -----------------------------------------------------------------
-- TABLAS QUE FALTAN
-- -----------------------------------------------------------------

-- Zonas geográficas habituales del usuario
CREATE TABLE IF NOT EXISTS user_zones (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT           NOT NULL,
    nombre     VARCHAR(100)  DEFAULT NULL,
    lat        DECIMAL(10,7) NOT NULL,
    lng        DECIMAL(10,7) NOT NULL,
    radio_km   INT           NOT NULL DEFAULT 50,
    frecuencia INT           NOT NULL DEFAULT 1,
    ultima_vez TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_zona_usuario (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sesiones de chat (para historial persistente)
CREATE TABLE IF NOT EXISTS chat_sessions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT       NOT NULL,
    fecha_inicio   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_mensaje TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    activa         TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_chat_usuario (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mensajes de chat
CREATE TABLE IF NOT EXISTS chat_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT  NOT NULL,
    rol        ENUM('user','assistant') NOT NULL,
    contenido  TEXT NOT NULL,
    fecha      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_msg_sesion (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intentos de login (seguridad)
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(250) NOT NULL,
    ip_address VARCHAR(45)  NOT NULL,
    exitoso    TINYINT(1)   NOT NULL DEFAULT 0,
    fecha      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_intentos (email),
    INDEX idx_ip_intentos    (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- COLUMNAS QUE FALTAN EN TABLAS EXISTENTES
-- -----------------------------------------------------------------

-- usuarios: añadir control de sesión y seguridad
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS email_verificado TINYINT(1)  NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS intentos_login   INT         NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS bloqueado_hasta  TIMESTAMP   NULL,
    ADD COLUMN IF NOT EXISTS ultimo_acceso    TIMESTAMP   NULL;

-- user_preferences: añadir campos declarados vs inferidos
ALTER TABLE user_preferences
    ADD COLUMN IF NOT EXISTS preferencias_declaradas JSON DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS preferencias_inferidas  JSON DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS ubicaciones_habituales  JSON DEFAULT NULL;

-- user_interactions: añadir campo JSON para metadatos extra
ALTER TABLE user_interactions
    ADD COLUMN IF NOT EXISTS datos JSON DEFAULT NULL;

-- route_popularity: añadir rating (puntuación media)
ALTER TABLE route_popularity
    ADD COLUMN IF NOT EXISTS rating_sum   DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS rating_count INT           NOT NULL DEFAULT 0;

-- -----------------------------------------------------------------
-- NOTAS IMPORTANTES SOBRE DIFERENCIAS DE SCHEMA
-- -----------------------------------------------------------------
-- La tabla `rutas` usa `usuario` (VARCHAR, nombre de usuario) en lugar de
-- una clave foránea entera. Esto es intencional — no lo cambiamos para
-- no romper el código existente. En Fase 1 se añadirá compatibilidad.
--
-- La tabla `usuarios` guarda `password` en texto plano. En Fase 1 se
-- migrará a password_hash con bcrypt.
--
-- Tablas existentes mapeadas a nuestro plan:
--   user_interactions  → equivalente a nuestra "interacciones"
--   completed_routes   → equivalente a nuestra "user_completed"
--   list_routes        → equivalente a nuestra "user_list_routes"
--   ai_interactions    → historial de chat anterior (distinto de chat_sessions)
-- =================================================================
