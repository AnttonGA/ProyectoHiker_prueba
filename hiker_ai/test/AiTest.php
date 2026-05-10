<?php
namespace KahirAI\Tests;

use PHPUnit\Framework\TestCase;
use KahirAI\Core\AICore;
use KahirAI\Core\ChatManager;
use KahirAI\Services\LoggerService;

class AITest extends TestCase {
    private AICore $ai;
    private ChatManager $chatManager;

    protected function setUp(): void {
        $this->ai = new AICore();
        $this->chatManager = new ChatManager();
    }

    public function testRouteRecommendation() {
        $query = "Busco una ruta de senderismo fácil cerca de Candanchú";
        $userId = 1;

        $response = $this->ai->processQuery($query, $userId);

        $this->assertArrayHasKey('type', $response);
        $this->assertEquals('route_suggestions', $response['type']);
        $this->assertArrayHasKey('routes', $response);
        $this->assertNotEmpty($response['routes']);

        foreach ($response['routes'] as $route) {
            $this->assertArrayHasKey('difficulty', $route);
            $this->assertEquals('beginner', $route['difficulty']);
            $this->assertArrayHasKey('location', $route);
            $this->assertArrayHasKey('title', $route);
        }
    }

    public function testWeatherQuery() {
        $query = "¿Qué tiempo hace hoy en Formigal?";
        $userId = 1;

        $response = $this->ai->processQuery($query, $userId);

        $this->assertArrayHasKey('type', $response);
        $this->assertEquals('weather_info', $response['type']);
        $this->assertArrayHasKey('current', $response);
        $this->assertArrayHasKey('temperature', $response['current']);
        $this->assertArrayHasKey('conditions', $response['current']);
    }

    public function testContextMaintenance() {
        $queries = [
            "Busco una ruta de ski",
            "Que sea para principiantes",
            "¿Qué tiempo hará ese día?"
        ];

        foreach ($queries as $query) {
            $response = $this->chatManager->processMessage($query);
            $this->assertNotNull($response);
        }

        $context = $this->chatManager->getConversationHistory();
        $this->assertCount(3, $context);
    }

    public function testIntentDetection() {
        $testCases = [
            [
                'query' => "Busco una ruta de senderismo",
                'expectedIntent' => 'route_request'
            ],
            [
                'query' => "¿Qué tiempo hará mañana?",
                'expectedIntent' => 'weather_request'
            ],
            [
                'query' => "¿Cómo me equipo para ski de montaña?",
                'expectedIntent' => 'information_request'
            ]
        ];

        foreach ($testCases as $case) {
            $response = $this->ai->processQuery($case['query'], 1);
            $this->assertEquals($case['expectedIntent'], $response['intent']);
        }
    }

    public function testSafetyChecks() {
        $query = "Ruta para mañana en Panticosa";
        $userId = 1;

        // Simular condiciones meteorológicas adversas
        $badWeather = [
            'wind_speed' => 60,
            'temperature' => -20,
            'visibility' => 500
        ];

        $response = $this->ai->processQuery($query, $userId);
        
        $this->assertArrayHasKey('type', $response);
        $this->assertEquals('weather_warning', $response['type']);
        $this->assertArrayHasKey('recommendations', $response);
    }

    public function testErrorHandling() {
        $invalidQueries = [
            "",
            str_repeat("a", 1000),
            "{\invalid}json"
        ];

        foreach ($invalidQueries as $query) {
            try {
                $this->ai->processQuery($query, 1);
                $this->fail("Se esperaba una excepción para query inválido");
            } catch (\Exception $e) {
                $this->assertInstanceOf(\KahirAI\Exceptions\AIException::class, $e);
            }
        }
    }

    public function testUserPreferenceLearning() {
        $userId = 1;
        $queries = [
            "Ruta fácil en Candanchú",
            "Otra ruta como la anterior",
            "Ruta similar pero más larga"
        ];

        foreach ($queries as $query) {
            $response = $this->ai->processQuery($query, $userId);
            $this->assertNotNull($response);
        }

        // Verificar que las preferencias se actualizaron
        $preferences = $this->ai->getUserPreferences($userId);
        $this->assertNotNull($preferences);
        $this->assertEquals('beginner', $preferences['experience_level']);
    }
}