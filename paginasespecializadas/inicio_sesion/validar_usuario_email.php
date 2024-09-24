<?php
header('Content-Type: application/json');

// Incluir la conexión a la base de datos u otras configuraciones necesarias
require_once 'conexion.php';

$response = ["usuario_existe" => false, "email_existe" => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['usuario'])) {
        $usuario = $conn->real_escape_string($data['usuario']);
        $query = "SELECT * FROM usuarios WHERE usuario = '$usuario'";
        $result = $conn->query($query);
        if ($result->num_rows > 0) {
            $response["usuario_existe"] = true;
        }
    }

    if (isset($data['email'])) {
        $email = $conn->real_escape_string($data['email']);
        $query = "SELECT * FROM usuarios WHERE mail = '$email'";
        $result = $conn->query($query);
        if ($result->num_rows > 0) {
            $response["email_existe"] = true;
        }
    }
}

$conn->close();
echo json_encode($response);

?>





