<?php
namespace KahirAI\Tests;

use PHPUnit\Framework\TestCase;
use KahirAI\Models\Route;
use KahirAI\Core\RouteRecommender;
use KahirAI\Exceptions\RouteException;

class RouteTest extends TestCase {
    private Route $route;
    private RouteRecommender $recommender;

    protected function setUp(): void {
        $this->route = new Route([
            'title' => 'Test Route',
            'description' => 'Test Description',
            'difficulty' => 'intermediate',
            'elevation' => 800,
            'duration' => 4,
            'location' => 'Western_Pyrenees',
            'length' => 12,
            'startLat' => 42.7833,
            'startLng' => -0.5333
        ]);
        $this->recommender = new RouteRecommender();
    }

    public function testRouteCreation() {
        $this->assertEquals('Test Route', $this->route->getTitle());
        $this->assertEquals('intermediate', $this->route->getDifficulty());
        $this->assertEquals(800, $this->route->getElevation());
    }

    public function testRouteValidation() {
        $this->assertTrue($this->route->validate());

        $invalidRoute = new Route([
            'title' => '',
            'elevation' => -100,
            'duration' => 0
        ]);

        $this->expectException(RouteException::class);
        $invalidRoute->validate();
    }

    public function testGetRecommendations() {
        $criteria = [
            'difficulty' => 'intermediate',
            'location' => 'Western_Pyrenees',
            'activity' => 'hiking'
        ];
        
        $userPreferences = [
            'experience_level' => 'intermediate',
            'preferred_activities' => ['hiking'],
            'preferred_locations' => ['Western_Pyrenees']
        ];

        $recommendations = $this->recommender->getRecommendations($criteria, $userPreferences);
        
        $this->assertNotEmpty($recommendations);
        foreach ($recommendations as $recommendation) {
            $this->assertArrayHasKey('route', $recommendation);
            $this->assertArrayHasKey('score', $recommendation);
            $this->assertGreaterThanOrEqual(0.6, $recommendation['score']);
        }
    }

    public function testSimilarRoutes() {
        $routeId = $this->route->save();
        $similarRoutes = $this->recommender->getSimilarRoutes($routeId);

        $this->assertIsArray($similarRoutes);
        if (!empty($similarRoutes)) {
            foreach ($similarRoutes as $similar) {
                $this->assertArrayHasKey('similarity_score', $similar);
                $this->assertArrayHasKey('common_features', $similar);
            }
        }
    }

    public function testRouteSafety() {
        $conditions = [
            'weather' => [
                'temperature' => 20,
                'wind_speed' => 15,
                'visibility' => 5000
            ],
            'season' => 'summer'
        ];

        $isSafe = $this->recommender->validateRouteSuitability($this->route->toArray(), $conditions);
        $this->assertTrue($isSafe);
    }

    public function testRouteSearch() {
        $searchCriteria = [
            'difficulty' => 'intermediate',
            'min_distance' => 5,
            'max_distance' => 15,
            'location' => 'Western_Pyrenees'
        ];

        $foundRoutes = Route::search($searchCriteria);
        
        foreach ($foundRoutes as $route) {
            $this->assertEquals('intermediate', $route['difficulty']);
            $this->assertGreaterThanOrEqual(5, $route['length']);
            $this->assertLessThanOrEqual(15, $route['length']);
            $this->assertEquals('Western_Pyrenees', $route['location']);
        }
    }

    public function testRoutePopularity() {
        $routeId = $this->route->save();
        
        // Simular interacciones
        $this->simulateInteractions($routeId);
        
        $popularityScore = $this->route->calculatePopularityScore();
        $this->assertGreaterThan(0, $popularityScore);
    }

    public function testGPXProcessing() {
        $gpxFile = __DIR__ . '/fixtures/test_route.gpx';
        $gpxData = Route::parseGPXFile($gpxFile);

        $this->assertArrayHasKey('elevation', $gpxData);
        $this->assertArrayHasKey('length', $gpxData);
        $this->assertArrayHasKey('duration', $gpxData);
    }

    private function simulateInteractions($routeId) {
        global $conn;
        $interactions = [
            ['view', 10],
            ['download', 5],
            ['favorite', 3],
            ['complete', 2]
        ];

        foreach ($interactions as [$type, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $stmt = $conn->prepare(
                    "INSERT INTO user_interactions (user_id, route_id, interaction_type)
                     VALUES (1, ?, ?)"
                );
                $stmt->bind_param("is", $routeId, $type);
                $stmt->execute();
            }
        }
    }
}