<?php
require_once __DIR__ . '/../../../includes/security.php';
secure_session_start();
require_once '../../inicio_sesion/conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'error' => 'Entra en tu cuenta para usar tus listas.']);
    exit;
}

csrf_verify(true);

$data    = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = [];
}
$action  = $data['action']  ?? '';
$routeId = isset($data['routeId']) ? (int)$data['routeId'] : 0;

$enTransaccion = false;

/**
 * Comprueba que la lista pertenece al usuario de la sesión.
 */
function listaEsDelUsuario($conn, int $listId, int $userId): bool {
    $stmt = $conn->prepare("SELECT 1 FROM user_lists WHERE id = ? AND usuario_id = ? LIMIT 1");
    $stmt->bind_param("ii", $listId, $userId);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ok;
}

try {
    // Resolver usuario_id
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($userId === 0) {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $_SESSION['usuario']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) throw new InvalidArgumentException('No encontramos tu cuenta. Vuelve a entrar.');
        $userId = (int)$row['id'];
    }

    if (!$routeId) throw new InvalidArgumentException('Falta la ruta. Recarga la página.');

    if ($action === 'create') {
        $listName = trim((string)($data['listName'] ?? ''));
        if ($listName === '') throw new InvalidArgumentException('Escribe un nombre para la lista.');
        if (mb_strlen($listName) > 100) throw new InvalidArgumentException('El nombre es demasiado largo (máximo 100 caracteres).');

        $conn->begin_transaction();
        $enTransaccion = true;

        $createListStmt = $conn->prepare(
            "INSERT INTO user_lists (usuario_id, nombre) VALUES (?, ?)"
        );
        $createListStmt->bind_param("is", $userId, $listName);
        $createListStmt->execute();
        $listId = $conn->insert_id;
        $createListStmt->close();

        $addToListStmt = $conn->prepare(
            "INSERT INTO user_list_routes (lista_id, ruta_id) VALUES (?, ?)"
        );
        $addToListStmt->bind_param("ii", $listId, $routeId);
        $addToListStmt->execute();
        $addToListStmt->close();

        $conn->commit();
        $enTransaccion = false;

        echo json_encode([
            'success' => true,
            'message' => 'Lista creada y ruta añadida',
            'listId'  => $listId
        ]);

    } elseif ($action === 'add') {
        $listId = isset($data['listId']) ? (int)$data['listId'] : 0;
        if (!$listId) throw new InvalidArgumentException('Falta la lista. Recarga la página.');
        if (!listaEsDelUsuario($conn, $listId, $userId)) {
            http_response_code(403);
            throw new InvalidArgumentException('No encontramos esa lista. Recarga la página.');
        }

        $checkStmt = $conn->prepare(
            "SELECT COUNT(*) FROM user_list_routes WHERE lista_id = ? AND ruta_id = ?"
        );
        $checkStmt->bind_param("ii", $listId, $routeId);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_row()[0] > 0;
        $checkStmt->close();

        if ($exists) throw new InvalidArgumentException('La ruta ya está en esta lista.');

        $addToListStmt = $conn->prepare(
            "INSERT INTO user_list_routes (lista_id, ruta_id) VALUES (?, ?)"
        );
        $addToListStmt->bind_param("ii", $listId, $routeId);
        $addToListStmt->execute();
        $addToListStmt->close();

        echo json_encode(['success' => true, 'message' => 'Ruta añadida a la lista']);

    } elseif ($action === 'remove') {
        $listId = isset($data['listId']) ? (int)$data['listId'] : 0;
        if (!$listId) throw new InvalidArgumentException('Falta la lista. Recarga la página.');
        if (!listaEsDelUsuario($conn, $listId, $userId)) {
            http_response_code(403);
            throw new InvalidArgumentException('No encontramos esa lista. Recarga la página.');
        }

        $removeStmt = $conn->prepare(
            "DELETE FROM user_list_routes WHERE lista_id = ? AND ruta_id = ?"
        );
        $removeStmt->bind_param("ii", $listId, $routeId);
        $removeStmt->execute();
        $removeStmt->close();

        echo json_encode(['success' => true, 'message' => 'Ruta quitada de la lista']);

    } else {
        throw new InvalidArgumentException('Acción no válida.');
    }

} catch (InvalidArgumentException $e) {
    if ($enTransaccion) $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($enTransaccion) $conn->rollback();
    error_log('bt_lists.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'No se ha podido actualizar la lista. Inténtalo de nuevo más tarde.']);
}

$conn->close();
