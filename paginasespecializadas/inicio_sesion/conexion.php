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
    $servername = "zpj83vpaccjer3ah.chr7pe7iynqr.eu-west-1.rds.amazonaws.com:3306";
    $username = "nbjr9xh3zmf4a6pn";
    $password = "hnpacxfc79grmpa6";
    $dbname = "rp5mw8s5obgkn97d";
}

// Intentamos conectar a la base de datos
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Verificamos la conexión
if (!$conn) {
    die("Conexión fallida: " . mysqli_connect_error());
} 
?>
