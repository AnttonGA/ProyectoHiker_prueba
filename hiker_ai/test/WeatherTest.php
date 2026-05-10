<?php
namespace KahirAI\Tests;

use PHPUnit\Framework\TestCase;
use KahirAI\Services\WeatherService;
use KahirAI\Core\WeatherAnalyzer;
use KahirAI\Exceptions\WeatherException;

class WeatherTest extends TestCase {
    private WeatherService $weatherService;
    private WeatherAnalyzer $weatherAnalyzer;

    protected function setUp(): void {
        $this->weatherService = new WeatherService();
        $this->weatherAnalyzer = new WeatherAnalyzer();
    }

    public function testGetCurrentWeather() {
        $latitude = 42.7833;
        $longitude = -0.5333;

        $weather = $this->weatherService->getCurrentWeather($latitude, $longitude);

        $this->assertArrayHasKey('temperature', $weather);
        $this->assertArrayHasKey('humidity', $weather);
        $this->assertArrayHasKey('wind_speed', $weather);
        $this->assertArrayHasKey('conditions', $weather);
    }

    public function testGetMountainConditions() {
        $latitude = 42.7833;
        $longitude = -0.5333;

        $conditions = $this->weatherService->getMountainConditions($latitude, $longitude);

        $this->assertArrayHasKey('snow_level', $conditions);
        $this->assertArrayHasKey('iso_zero', $conditions);
        $this->assertArrayHasKey('avalanche_risk', $conditions);
    }

    public function testSafetyAnalysis() {
        $weatherData = [
            'temperature' => 5,
            'wind_speed' => 20,
            'visibility' => 2000,
            'snow_level' => 1800,
            'precipitation' => 0
        ];

        $skiResult = $this->weatherAnalyzer->analyzeConditions($weatherData, 'ski_touring');
        $hikingResult = $this->weatherAnalyzer->analyzeConditions($weatherData, 'hiking');

        $this->assertTrue($skiResult['is_safe']);
        $this->assertTrue($hikingResult['is_safe']);
    }

    public function testUnsafeConditions() {
        $weatherData = [
            'temperature' => -20,
            'wind_speed' => 60,
            'visibility' => 500,
            'snow_level' => 1800,
            'precipitation' => 15
        ];

        $analysis = $this->weatherAnalyzer->analyzeConditions($weatherData, 'ski_touring');

        $this->assertFalse($analysis['is_safe']);
        $this->assertNotEmpty($analysis['risks']);
    }

    public function testForecast() {
        $latitude = 42.7833;
        $longitude = -0.5333;

        $forecast = $this->weatherService->getForecast($latitude, $longitude, 3);

        $this->assertCount(3, $forecast['daily']);
        foreach ($forecast['daily'] as $day) {
            $this->assertArrayHasKey('temp_min', $day);
            $this->assertArrayHasKey('temp_max', $day);
            $this->assertArrayHasKey('precipitation', $day);
        }
    }

    public function testWeatherAlerts() {
        $latitude = 42.7833;
        $longitude = -0.5333;

        $alerts = $this->weatherService->getWeatherAlerts($latitude, $longitude);

        $this->assertIsArray($alerts);
        if (!empty($alerts)) {
            $this->assertArrayHasKey('severity', $alerts[0]);
            $this->assertArrayHasKey('description', $alerts[0]);
        }
    }

    public function testInvalidCoordinates() {
        $this->expectException(WeatherException::class);
        $this->weatherService->getCurrentWeather(91.0, 181.0);
    }

    public function testAPIErrorHandling() {
        $this->weatherService->setApiKey('invalid_key');
        
        $this->expectException(WeatherException::class);
        $this->weatherService->getCurrentWeather(42.7833, -0.5333);
    }

    public function testCaching() {
        $latitude = 42.7833;
        $longitude = -0.5333;

        $firstCall = $this->weatherService->getCurrentWeather($latitude, $longitude);
        $secondCall = $this->weatherService->getCurrentWeather($latitude, $longitude);

        $this->assertEquals($firstCall, $secondCall);
    }
}