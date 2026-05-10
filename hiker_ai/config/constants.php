<?php
namespace KahirAI\Config;

// Configuración de sesión
const SESSION_LIFETIME = 7200;        // 2 horas
const MAX_LOGIN_ATTEMPTS = 5;         // Intentos máximos de inicio de sesión
const LOCKOUT_TIME = 900;            // Tiempo de bloqueo tras exceder intentos (15 min)

// Configuración de API
const OPENWEATHER_API_KEY = '';  // Configurar en .env: OPENWEATHER_API_KEY=tu_clave
const OPENWEATHER_API_URL = 'https://api.openweathermap.org/data/2.5';
const OPENSTREETMAP_API_URL = 'https://nominatim.openstreetmap.org';

// Límites de uso
const RATE_LIMIT_FREEMIUM = 4;       // Mensajes por día para usuarios freemium
const RATE_LIMIT_PREMIUM = 20;        // Mensajes por día para usuarios premium
const API_TIMEOUT = 30;              // Tiempo máximo de espera para APIs externas

// Configuración de caché
const CACHE_DURATION = 1800;         // 30 minutos
const MAX_CACHE_SIZE = 100000000;    // 100MB

// Configuración de logging
const LOG_PATH = __DIR__ . '/../logs';
const MAX_LOG_FILES = 30;
const LOG_ROTATION_SIZE = 10485760;  // 10MB