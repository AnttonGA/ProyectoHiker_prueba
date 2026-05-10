<?php
/**
 * Cargador de variables de entorno desde .env
 * Busca el archivo .env subiendo desde la ubicación actual hasta la raíz del proyecto.
 */
function load_env(string $path = null): void {
    if ($path === null) {
        // Buscar .env subiendo directorios desde este archivo
        $dir = __DIR__;
        for ($i = 0; $i < 4; $i++) {
            $candidate = $dir . '/.env';
            if (file_exists($candidate)) {
                $path = $candidate;
                break;
            }
            $dir = dirname($dir);
        }
    }

    if ($path === null || !file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Eliminar comillas opcionales
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
}

function env(string $key, mixed $default = null): mixed {
    $value = $_ENV[$key] ?? getenv($key);
    return ($value !== false && $value !== null) ? $value : $default;
}
