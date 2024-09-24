<?php
// Función para detectar si la conexión es local o remota
function detectarConexion() {
    // Puedes usar variables de entorno, dirección IP, u otros métodos para detectar
    // aquí simplemente vamos a verificar si estamos en localhost
    if ($_SERVER['SERVER_ADDR'] == '127.0.0.1' || $_SERVER['SERVER_ADDR'] == '::1') {
        return 'local'; // Si es localhost, devuelve 'local'
    } else {
        return 'remota'; // Si no es localhost, devuelve 'remota'
    }
}

// Determinamos qué conexión utilizar
$tipoConexion = detectarConexion();

// Dependiendo del tipo de conexión, configuramos las credenciales
if ($tipoConexion == 'local') {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "peliculas";
} else {
    $servername = "sql300.infinityfree.com";
    $username = "if0_36636778";
    $password = "lU9fyeWRZGh";
    $dbname = "if0_36636778_peliculas";
}

// Intentamos conectar a la base de datos
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Verificamos la conexión
if (!$conn) {
    die("Conexión fallida: " . mysqli_connect_error());
} 
?>
