<?php
header('Content-Type: application/json');
require_once 'conexion.php';

$response = ["usuario_existe" => false, "email_existe" => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['usuario'])) {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $data['usuario']);
        $stmt->execute();
        $response["usuario_existe"] = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    }

    if (isset($data['email'])) {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $data['email']);
        $stmt->execute();
        $response["email_existe"] = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    }
}

$conn->close();
echo json_encode($response);
