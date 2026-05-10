<?php
require_once dirname(__DIR__, 2) . '/includes/env_loader.php';
load_env();

define('DB_HOST',    env('DB_HOST', 'localhost'));
define('DB_USER',    env('DB_USER', 'root'));
define('DB_PASS',    env('DB_PASS', ''));
define('DB_NAME',    env('DB_NAME', 'kahir_db'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));
