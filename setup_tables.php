<?php
/**
 * setup_tables.php — crea todas las tablas auxiliares si no existen.
 * Ejecutar UNA vez desde CLI o navegador (solo en local).
 */
require_once __DIR__ . '/paginasespecializadas/inicio_sesion/conexion.php';

$tables = [];

// ── user_interactions ──────────────────────────────────────────────────────────
$tables['user_interactions'] = "
CREATE TABLE IF NOT EXISTS user_interactions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT UNSIGNED NOT NULL,
    ruta_id      INT UNSIGNED NOT NULL,
    tipo         ENUM('view','download','favorite','complete','map_interaction') NOT NULL,
    duracion_seg INT UNSIGNED DEFAULT NULL COMMENT 'Para tipo=view: segundos en página',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario  (usuario_id),
    INDEX idx_ruta     (ruta_id),
    INDEX idx_tipo     (tipo),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── user_favorites ─────────────────────────────────────────────────────────────
$tables['user_favorites'] = "
CREATE TABLE IF NOT EXISTS user_favorites (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT UNSIGNED NOT NULL,
    ruta_id      INT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fav (usuario_id, ruta_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_ruta    (ruta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── user_completed ─────────────────────────────────────────────────────────────
$tables['user_completed'] = "
CREATE TABLE IF NOT EXISTS user_completed (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT UNSIGNED NOT NULL,
    ruta_id      INT UNSIGNED NOT NULL,
    fecha_completada  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notas             TEXT DEFAULT NULL,
    UNIQUE KEY uq_comp (usuario_id, ruta_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_ruta    (ruta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── route_popularity ───────────────────────────────────────────────────────────
$tables['route_popularity'] = "
CREATE TABLE IF NOT EXISTS route_popularity (
    ruta_id               INT UNSIGNED PRIMARY KEY,
    view_count            INT UNSIGNED NOT NULL DEFAULT 0,
    download_count        INT UNSIGNED NOT NULL DEFAULT 0,
    favorite_count        INT UNSIGNED NOT NULL DEFAULT 0,
    complete_count        INT UNSIGNED NOT NULL DEFAULT 0,
    popularity_score      FLOAT        NOT NULL DEFAULT 0,
    ultima_actualizacion  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── user_preferences ───────────────────────────────────────────────────────────
$tables['user_preferences'] = "
CREATE TABLE IF NOT EXISTS user_preferences (
    usuario_id              INT UNSIGNED PRIMARY KEY,
    nivel_experiencia       ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
    actividades_preferidas  TEXT    DEFAULT NULL COMMENT 'JSON array de tipos de ruta',
    ubicaciones_habituales  TEXT    DEFAULT NULL COMMENT 'JSON array de ubicaciones',
    distancia_max_km        FLOAT   DEFAULT NULL,
    duracion_max_horas      FLOAT   DEFAULT NULL,
    preferencias_inferidas  TEXT    DEFAULT NULL COMMENT 'JSON con métricas adicionales',
    ultima_actualizacion    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── Ejecutar ───────────────────────────────────────────────────────────────────
$ok    = 0;
$skip  = 0;
$error = 0;

echo "<pre>\n";
foreach ($tables as $name => $sql) {
    // Check if table already exists
    $res = $conn->query("SHOW TABLES LIKE '$name'");
    if ($res && $res->num_rows > 0) {
        echo "⏭️  $name — ya existe, sin cambios.\n";
        $skip++;
        continue;
    }

    if ($conn->query($sql)) {
        echo "✅  $name — creada.\n";
        $ok++;
    } else {
        echo "❌  $name — ERROR: {$conn->error}\n";
        $error++;
    }
}

// Populate route_popularity for existing routes that don't have a record yet
$conn->query("
    INSERT IGNORE INTO route_popularity (ruta_id)
    SELECT id FROM rutas
");
$affected = $conn->affected_rows;
if ($affected > 0) {
    echo "\n✅  route_popularity — añadidos $affected registros base para rutas existentes.\n";
}

echo "\n════════════════════════════\n";
echo "✅ Creadas: $ok | ⏭️ Ya existían: $skip | ❌ Errores: $error\n";
echo "</pre>\n";

$conn->close();
