<?php
/**
 * API para gestión de rutas en Kahir.AI
 * 
 * Proporciona endpoints para buscar, crear, actualizar
 * y gestionar rutas e información relacionada.
 */

namespace KahirAI\API;

use KahirAI\Services\{AuthService, LoggerService};
use KahirAI\Models\Route;
use KahirAI\Exceptions\RouteException;

class RouteAPI {
    /**
     * @var AuthService
     */
    private AuthService $auth;

    /**
     * @var LoggerService
     */
    private LoggerService $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->auth = new AuthService();
        $this->logger = new LoggerService('route_api');
        header('Content-Type: application/json');
    }

    /**
     * Maneja las peticiones a la API
     */
    public function handleRequest() {
        try {
            // Verificar autenticación
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
                case 'DELETE':
                    $this->handleDeleteRequest($action);
                    break;
                default:
                    $this->sendResponse(['error' => 'Método no permitido'], 405);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error en API de rutas', [
                'error' => $e->getMessage(),
                'method' => $_SERVER['REQUEST_METHOD'],
                'action' => $_GET['action'] ?? ''
            ]);
            $this->sendResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Maneja peticiones GET
     */
    private function handleGetRequest(string $action) {
        switch ($action) {
            case 'search':
                $this->searchRoutes($_GET);
                break;
            case 'detail':
                $this->getRouteDetail($_GET['id'] ?? null);
                break;
            case 'recommendations':
                $this->getRecommendations($_GET);
                break;
            case 'similar':
                $this->getSimilarRoutes($_GET['id'] ?? null);
                break;
            default:
                $this->sendResponse(['error' => 'Acción no válida'], 400);
        }
    }

    /**
     * Maneja peticiones POST
     */
    private function handlePostRequest(string $action) {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($action) {
            case 'create':
                $this->createRoute($data);
                break;
            case 'upload':
                $this->uploadRouteFiles($_FILES, $_POST);
                break;
            case 'rate':
                $this->rateRoute($data);
                break;
            default:
                $this->sendResponse(['error' => 'Acción no válida'], 400);
        }
    }

    /**
     * Maneja peticiones PUT
     */
    private function handlePutRequest(string $action) {
        $data = json_decode(file_get_contents('php://input'), true);

        switch ($action) {
            case 'update':
                $this->updateRoute($data);
                break;
            case 'status':
                $this->updateRouteStatus($data);
                break;
            default:
                $this->sendResponse(['error' => 'Acción no válida'], 400);
        }
    }

    /**
     * Maneja peticiones DELETE
     */
    private function handleDeleteRequest(string $action) {
        switch ($action) {
            case 'delete':
                $this->deleteRoute($_GET['id'] ?? null);
                break;
            default:
                $this->sendResponse(['error' => 'Acción no válida'], 400);
        }
    }

    /**
     * Busca rutas según criterios
     */
    private function searchRoutes(array $params) {
        global $conn;

        try {
            $conditions = [];
            $values = [];
            $types = '';

            // Construir la consulta según los parámetros
            if (!empty($params['difficulty'])) {
                $conditions[] = "difficulty = ?";
                $values[] = $params['difficulty'];
                $types .= 's';
            }

            if (!empty($params['location'])) {
                $conditions[] = "location = ?";
                $values[] = $params['location'];
                $types .= 's';
            }

            if (!empty($params['activity'])) {
                $conditions[] = "tipo_ruta = ?";
                $values[] = $params['activity'];
                $types .= 's';
            }

            // Construir la consulta SQL
            $sql = "SELECT r.*, rp.popularity_score 
                   FROM rutas r
                   LEFT JOIN route_popularity rp ON r.id = rp.ruta_id";

            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }

            $sql .= " ORDER BY rp.popularity_score DESC";

            // Preparar y ejecutar la consulta
            $stmt = $conn->prepare($sql);
            if (!empty($values)) {
                $stmt->bind_param($types, ...$values);
            }
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $this->sendResponse(['routes' => $result]);

        } catch (\Exception $e) {
            $this->sendResponse(['error' => 'Error en la búsqueda'], 500);
        }
    }

    /**
     * Obtiene detalles de una ruta
     */
    private function getRouteDetail(?int $routeId) {
        if (!$routeId) {
            $this->sendResponse(['error' => 'ID de ruta requerido'], 400);
            return;
        }

        try {
            $route = Route::findById($routeId);
            $this->sendResponse($route->toArray());
        } catch (RouteException $e) {
            $this->sendResponse(['error' => 'Ruta no encontrada'], 404);
        }
    }

    /**
     * Crea una nueva ruta
     */
    private function createRoute(array $data) {
        try {
            $route = new Route($data);
            $route->validate();
            $route->save();
            
            $this->sendResponse([
                'message' => 'Ruta creada exitosamente',
                'route_id' => $route->getId()
            ], 201);

        } catch (RouteException $e) {
            $this->sendResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Envía una respuesta JSON
     */
    private function sendResponse($data, int $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    // Implementar resto de métodos según necesidad...
}