<?php
// config.php

if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_ADDR'] == '127.0.0.1') {
    define('BASE_URL', 'http://localhost/misproyectos/ProyectoHiker_prueba');
} else {
    define('BASE_URL', 'kahir-7054078a5f30.herokuapp.com/ProyectoHiker_prueba/'); 
}