<?php
// config.php

if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_ADDR'] == '127.0.0.1') {
    define('BASE_URL', 'http://localhost/misproyectos/ProyectoHiker_prueba');
} else {
    define('BASE_URL', 'http://antton.kesug.com'); 
}