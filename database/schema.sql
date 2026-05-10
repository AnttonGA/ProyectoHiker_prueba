-- =================================================================
-- Kahir.AI — Esquema completo de base de datos
-- Ejecutar en phpMyAdmin o con: mysql -u root kahir_db < schema.sql
-- =================================================================

CREATE DATABASE IF NOT EXISTS kahir_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE kahir_db;

-- -----------------------------------------------------------------
-- USUARIOS
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    username         VARCHAR(50)  NOT NULL UNIQUE,
    email            VARCHAR(100) NOT NULL UNIQUE,
    password_hash    VARCHAR(255) NOT NULL,
    foto_perfil      VARCHAR(500) DEFAULT NULL,
    rol              ENUM('admin','editor','usuario','guest') NOT NULL DEFAULT 'usuario',
    activo           TINYINT(1)  NOT NULL DEFAULT 1,
    email_verificado TINYINT(1)  NOT NULL DEFAULT 0,
    fecha_registro   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso    TIMESTAMP   NULL,
    intentos_login   INT         NOT NULL DEFAULT 0,
    bloqueado_hasta  TIMESTAMP   NULL,
    INDEX idx_email  (email),
    INDEX idx_rol    (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- RUTAS
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rutas (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT          NOT NULL,
    titulo           VARCHAR(255) NOT NULL,
    descripcion      TEXT         DEFAULT NULL,
    gpx_path         VARCHAR(500) NOT NULL,
    ruta_carpeta     VARCHAR(255) NOT NULL,
    tipo_ruta        ENUM('ski_touring','hiking','cycle_touring','climbing','snowshoeing') NOT NULL,
    ubicacion        ENUM('Western_Pyrenees','Central_Pyrenees','Eastern_Pyrenees','Other') NOT NULL,
    dificultad       ENUM('Beginner','Intermediate','Advanced','Expert') NOT NULL,
    distancia_km     DECIMAL(6,2) DEFAULT NULL,
    elevacion_max    INT          DEFAULT NULL,
    elevacion_ganada INT          DEFAULT NULL,
    duracion_horas   DECIMAL(5,2) DEFAULT NULL,
    start_lat        DECIMAL(10,7) DEFAULT NULL,
    start_lng        DECIMAL(10,7) DEFAULT NULL,
    start_elevation  INT          DEFAULT NULL,
    pregunta1        TEXT         DEFAULT NULL,
    pregunta2        TEXT         DEFAULT NULL,
    pregunta3        TEXT         DEFAULT NULL,
    pregunta4        TEXT         DEFAULT NULL,
    estado           ENUM('publicada','borrador','eliminada') NOT NULL DEFAULT 'publicada',
    fecha_subida     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_tipo     (tipo_ruta),
    INDEX idx_ubicacion (ubicacion),
    INDEX idx_dificultad (dificultad),
    INDEX idx_estado   (estado),
    INDEX idx_usuario  (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- IMÁGENES DE RUTA
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ruta_imagenes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    ruta_id    INT          NOT NULL,
    imagen_path VARCHAR(500) NOT NULL,
    orden      INT          NOT NULL DEFAULT 0,
    FOREIGN KEY (ruta_id) REFERENCES rutas(id) ON DELETE CASCADE,
    INDEX idx_ruta_img (ruta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- POPULARIDAD Y ESTADÍSTICAS DE RUTA
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS route_popularity (
    ruta_id            INT PRIMARY KEY,
    view_count         INT          NOT NULL DEFAULT 0,
    download_count     INT          NOT NULL DEFAULT 0,
    favorite_count     INT          NOT NULL DEFAULT 0,
    complete_count     INT          NOT NULL DEFAULT 0,
    rating_sum         DECIMAL(10,2) NOT NULL DEFAULT 0,
    rating_count       INT          NOT NULL DEFAULT 0,
    popularity_score   DECIMAL(8,4) NOT NULL DEFAULT 0,
    ultima_actualizacion TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ruta_id) REFERENCES rutas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trigger: crear registro de popularidad al insertar una ruta
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS after_ruta_insert
AFTER INSERT ON rutas
FOR EACH ROW
BEGIN
    INSERT IGNORE INTO route_popularity (ruta_id) VALUES (NEW.id);
END$$
DELIMITER ;

-- -----------------------------------------------------------------
-- FAVORITOS
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_favorites (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    ruta_id    INT NOT NULL,
    fecha      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fav (usuario_id, ruta_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (ruta_id)    REFERENCES rutas(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- RUTAS COMPLETADAS
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_completed (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT  NOT NULL,
    ruta_id          INT  NOT NULL,
    fecha_completada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notas            TEXT DEFAULT NULL,
    UNIQUE KEY uq_completed (usuario_id, ruta_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (ruta_id)    REFERENCES rutas(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- LISTAS DE RUTAS (colecciones personalizadas)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_lists (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT          NOT NULL,
    nombre         VARCHAR(100) NOT NULL,
    descripcion    TEXT         DEFAULT NULL,
    privada        TINYINT(1)   NOT NULL DEFAULT 0,
    fecha_creacion TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_lista_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_list_routes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    lista_id       INT NOT NULL,
    ruta_id        INT NOT NULL,
    fecha_agregada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_list_route (lista_id, ruta_id),
    FOREIGN KEY (lista_id) REFERENCES user_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (ruta_id)  REFERENCES rutas(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- PREFERENCIAS DE USUARIO
-- Separa lo declarado (formulario) de lo inferido (comportamiento)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_preferences (
    usuario_id              INT PRIMARY KEY,
    nivel_experiencia       ENUM('beginner','intermediate','advanced','expert') NOT NULL DEFAULT 'beginner',
    actividades_preferidas  JSON DEFAULT NULL,   -- ["ski_touring","hiking"]
    ubicaciones_habituales  JSON DEFAULT NULL,   -- ["Western_Pyrenees"]
    dificultad_habitual     ENUM('Beginner','Intermediate','Advanced','Expert') DEFAULT 'Beginner',
    distancia_max_km        INT          DEFAULT NULL,
    duracion_max_horas      DECIMAL(5,2) DEFAULT NULL,
    preferencias_declaradas JSON DEFAULT NULL,   -- lo que el usuario rellena
    preferencias_inferidas  JSON DEFAULT NULL,   -- lo que el sistema aprende
    ultima_actualizacion    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trigger: crear preferencias vacías al registrar un usuario
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS after_usuario_insert
AFTER INSERT ON usuarios
FOR EACH ROW
BEGIN
    INSERT IGNORE INTO user_preferences (usuario_id) VALUES (NEW.id);
END$$
DELIMITER ;

-- -----------------------------------------------------------------
-- ZONAS GEOGRÁFICAS HABITUALES DEL USUARIO
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_zones (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT          NOT NULL,
    nombre     VARCHAR(100) DEFAULT NULL,
    lat        DECIMAL(10,7) NOT NULL,
    lng        DECIMAL(10,7) NOT NULL,
    radio_km   INT          NOT NULL DEFAULT 50,
    frecuencia INT          NOT NULL DEFAULT 1,
    ultima_vez TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_zona_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- INTERACCIONES (registro de toda la actividad del usuario)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS interacciones (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    ruta_id    INT DEFAULT NULL,
    tipo       ENUM(
                   'view','download','favorite','unfavorite',
                   'complete','uncomplete','list_add','list_remove',
                   'share','map_interaction','chat_message'
               ) NOT NULL,
    datos      JSON    DEFAULT NULL,   -- metadatos extra (tiempo en página, etc.)
    fecha      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (ruta_id)    REFERENCES rutas(id)    ON DELETE SET NULL,
    INDEX idx_int_usuario (usuario_id),
    INDEX idx_int_tipo    (tipo),
    INDEX idx_int_fecha   (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- SESIONES DE CHAT
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_sessions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT       NOT NULL,
    fecha_inicio  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_mensaje TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    activa        TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_chat_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT  NOT NULL,
    rol        ENUM('user','assistant') NOT NULL,
    contenido  TEXT NOT NULL,
    fecha      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE,
    INDEX idx_msg_sesion (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- SIMILITUD ENTRE RUTAS (precalculada)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS route_similarity (
    ruta_id         INT NOT NULL,
    similar_ruta_id INT NOT NULL,
    similarity_score DECIMAL(5,4) NOT NULL DEFAULT 0,
    PRIMARY KEY (ruta_id, similar_ruta_id),
    FOREIGN KEY (ruta_id)         REFERENCES rutas(id) ON DELETE CASCADE,
    FOREIGN KEY (similar_ruta_id) REFERENCES rutas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- INTENTOS DE LOGIN (seguridad)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45)  NOT NULL,
    exitoso    TINYINT(1)   NOT NULL DEFAULT 0,
    fecha      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_intentos (email),
    INDEX idx_ip_intentos    (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- Vista útil: resumen de actividad por usuario
-- =================================================================
CREATE OR REPLACE VIEW v_user_activity AS
SELECT
    u.id,
    u.username,
    u.email,
    u.rol,
    COUNT(DISTINCT r.id)   AS rutas_subidas,
    COUNT(DISTINCT uf.ruta_id)  AS rutas_favoritas,
    COUNT(DISTINCT uc.ruta_id)  AS rutas_completadas,
    COUNT(DISTINCT i.id)   AS total_interacciones,
    u.ultimo_acceso
FROM usuarios u
LEFT JOIN rutas         r  ON r.usuario_id  = u.id AND r.estado = 'publicada'
LEFT JOIN user_favorites uf ON uf.usuario_id = u.id
LEFT JOIN user_completed uc ON uc.usuario_id = u.id
LEFT JOIN interacciones  i  ON i.usuario_id  = u.id
GROUP BY u.id;
