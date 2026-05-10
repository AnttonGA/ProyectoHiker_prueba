<?php
namespace KahirAI\API;

use KahirAI\Services\{AuthService, LoggerService, UserPreferenceService};
use KahirAI\Models\User;
use KahirAI\Exceptions\AIException;

class UserAPI {
    private AuthService $auth;
    private LoggerService $logger;
    private UserPreferenceService $preferences;

    public function __construct() {
        $this->auth = new AuthService();
        $this->logger = new LoggerService('user_api');
        $this->preferences = new UserPreferenceService();
        header('Content-Type: application/json');
    }

    public function handleRequest() {
        try {
            if (!$this->auth->checkSession()) {
                $this->sendResponse(['error' => 'No autorizado'], 401);
                return;
            }

            $method = $_SERVER['REQUEST_METHOD'];
            $action = $_GET['action'] ?? '';

            switch ($method) {
                case 'GET':
                    $this->handleGetRequest($action);
                    break;
                case 'POST':
                    $this->handlePostRequest($action);
                    break;
                case 'PUT':
                    $this->handlePutRequest($action);
                    break;
                default:
                    $this->sendResponse(['error' => 'Método no permitido'], 405);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error en API de usuario', [
                'error' => $e->getMessage(),
                'method' => $_SERVER['REQUEST_METHOD'],
                'action' => $_GET['action'] ?? ''
            ]);
            $this->sendResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function handleGetRequest(string $action) {
        switch ($action) {
            case 'profile':
                $this->getUserProfile($_GET['id'] ?? null);
                break;
            case 'preferences':
                $this->getUserPreferences();
                break;
            case 'favorites':
                $this->getUserFavorites();
                break;
            case 'completed':
                $this->getCompletedRoutes();
                break;
            case 'stats':
                $this->getUserStats();
                break;
            default:
                $this->sendResponse(['error' => 'Acción no válida'], 400);
        }
    }

    private function handlePostRequest(string $action) {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($action) {
            case 'update_preferences':
                $this->updatePreferences($data);
                break;
            case 'toggle_favorite':
                $this->toggleFavorite($data);
                break;
            case 'mark_completed':
                $this->markRouteCompleted($data);
                break;
            default:
                $this->sendResponse(['error' => 'Acción no válida'], 400);
        }
    }

    private function handlePutRequest(string $action) {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($action) {
            case 'update_profile':
                $this->updateProfile($data);
                break;
            case 'change_password':
                $this->changePassword($data);
                break;
            default:
                $this->sendResponse(['error' => 'Acción no válida'], 400);
        }
    }

    private function getUserProfile(?int $userId) {
        try {
            $user = User::findById($userId ?? $_SESSION['id']);
            $this->sendResponse($user->toArray());
        } catch (AIException $e) {
            $this->sendResponse(['error' => 'Usuario no encontrado'], 404);
        }
    }

    private function getUserPreferences() {
        try {
            $preferences = $this->preferences->getUserPreferences($_SESSION['usuario']);
            $this->sendResponse($preferences);
        } catch (AIException $e) {
            $this->sendResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function updatePreferences(array $data) {
        try {
            $success = $this->preferences->updatePreferences($_SESSION['id'], $data);
            $this->sendResponse(['success' => $success]);
        } catch (AIException $e) {
            $this->sendResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function toggleFavorite(array $data) {
        if (!isset($data['route_id'])) {
            $this->sendResponse(['error' => 'ID de ruta requerido'], 400);
            return;
        }

        try {
            $user = User::findById($_SESSION['id']);
            $favorites = $user->getFavoriteRoutes();
            $isFavorite = false;

            foreach ($favorites as $favorite) {
                if ($favorite['id'] == $data['route_id']) {
                    $isFavorite = true;
                    break;
                }
            }

            global $conn;
            if ($isFavorite) {
                $stmt = $conn->prepare("DELETE FROM user_favorites WHERE usuario_id = ? AND ruta_id = ?");
            } else {
                $stmt = $conn->prepare("INSERT INTO user_favorites (usuario_id, ruta_id) VALUES (?, ?)");
            }

            $stmt->bind_param("ii", $_SESSION['id'], $data['route_id']);
            $success = $stmt->execute();

            $this->sendResponse([
                'success' => $success,
                'is_favorite' => !$isFavorite
            ]);
        } catch (AIException $e) {
            $this->sendResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function markRouteCompleted(array $data) {
        if (!isset($data['route_id'])) {
            $this->sendResponse(['error' => 'ID de ruta requerido'], 400);
            return;
        }

        try {
            global $conn;
            $stmt = $conn->prepare("INSERT INTO user_completed (usuario_id, ruta_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $_SESSION['id'], $data['route_id']);
            $success = $stmt->execute();

            $this->sendResponse(['success' => $success]);
        } catch (AIException $e) {
            $this->sendResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function sendResponse($data, int $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
}