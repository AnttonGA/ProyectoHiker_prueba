<?php
namespace KahirAI\API;

use KahirAI\Services\{AuthService, LoggerService, WeatherService};
use KahirAI\Exceptions\WeatherException;

class WeatherAPI {
    private WeatherService $weather;
    private AuthService $auth;
    private LoggerService $logger;

    public function __construct() {
        $this->weather = new WeatherService();
        $this->auth = new AuthService();
        $this->logger = new LoggerService('weather_api');
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
                default:
                    $this->sendResponse(['error' => 'Método no permitido'], 405);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error en API del tiempo', [
                'error' => $e->getMessage(),
                'method' => $_SERVER['REQUEST_METHOD'],
                'action' => $_GET['action'] ?? ''
            ]);
            $this->sendResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function handleGetRequest(string $action) {
        switch ($action) {
            case 'current':
                $this->getCurrentWeather($_GET);
                break;
            case 'forecast':
                $this->getForecast($_GET);
                break;
            case 'mountain':
                $this->getMountainConditions($_GET);
                break;
            case 'alerts':
                $this->getWeatherAlerts($_GET);
                break;
            default:
                $this->sendResponse(['error' => 'Acción no válida'], 400);
        }
    }

    private function getCurrentWeather(array $params) {
        if (!isset($params['lat'], $params['lon'])) {
            $this->sendResponse(['error' => 'Coordenadas requeridas'], 400);
            return;
        }

        try {
            $weatherData = $this->weather->getCurrentWeather(
                (float)$params['lat'],
                (float)$params['lon']
            );

            $this->sendResponse($weatherData);
        } catch (WeatherException $e) {
            $this->sendResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function getForecast(array $params) {
        if (!isset($params['lat'], $params['lon'])) {
            $this->sendResponse(['error' => 'Coordenadas requeridas'], 400);
            return;
        }

        $days = isset($params['days']) ? (int)$params['days'] : 3;

        try {
            $forecast = $this->weather->getForecast(
                (float)$params['lat'],
                (float)$params['lon'],
                $days
            );

            $this->sendResponse($forecast);
        } catch (WeatherException $e) {
            $this->sendResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function getMountainConditions(array $params) {
        if (!isset($params['lat'], $params['lon'])) {
            $this->sendResponse(['error' => 'Coordenadas requeridas'], 400);
            return;
        }

        try {
            $conditions = $this->weather->getMountainConditions(
                (float)$params['lat'],
                (float)$params['lon']
            );

            $this->sendResponse($conditions);
        } catch (WeatherException $e) {
            $this->sendResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function getWeatherAlerts(array $params) {
        if (!isset($params['lat'], $params['lon'])) {
            $this->sendResponse(['error' => 'Coordenadas requeridas'], 400);
            return;
        }

        try {
            $alerts = $this->weather->getWeatherAlerts(
                (float)$params['lat'],
                (float)$params['lon']
            );

            $this->sendResponse($alerts);
        } catch (WeatherException $e) {
            $this->sendResponse(['error' => $e->getMessage()], 400);
        }
    }

    private function sendResponse($data, int $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
}