<?php
/**
 * Modelo para rutas en Kahir.AI
 * 
 * Maneja la estructura y comportamiento de las rutas,
 * incluyendo validación, cálculos y relaciones.
 */

namespace KahirAI\Models;

use KahirAI\Exceptions\RouteException;

class Route {
    /**
     * @var int ID de la ruta
     */
    private int $id;

    /**
     * @var string Usuario que creó la ruta
     */
    private string $usuario;

    /**
     * @var string Nombre del archivo GPX
     */
    private string $filename;

    /**
     * @var string Título de la ruta
     */
    private string $title;

    /**
     * @var string|null Descripción de la ruta
     */
    private ?string $description;

    /**
     * @var float Elevación en metros
     */
    private float $elevation;

    /**
     * @var float Duración en horas
     */
    private float $duration;

    /**
     * @var string Nivel de dificultad
     */
    private string $difficulty;

    /**
     * @var string Ubicación
     */
    private string $location;

    /**
     * @var float Longitud en kilómetros
     */
    private float $length;

    /**
     * @var float Latitud inicial
     */
    private float $startLat;

    /**
     * @var float Longitud inicial
     */
    private float $startLng;

    /**
     * @var float Elevación inicial
     */
    private float $startElevation;

    /**
     * @var string Tipo de ruta
     */
    private string $tipo_ruta;

    /**
     * @var string Carpeta de la ruta
     */
    private string $ruta_carpeta;

    /**
     * @var array Imágenes asociadas
     */
    private array $images = [];

    /**
     * @var array Popularidad y estadísticas
     */
    private array $stats = [];

    /**
     * Constructor
     * 
     * @param array $data Datos de la ruta
     */
    public function __construct(array $data) {
        $this->hydrate($data);
    }

    /**
     * Hidrata el modelo con datos
     * 
     * @param array $data Datos para hidratar
     */
    private function hydrate(array $data): void {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    /**
     * Guarda la ruta en la base de datos
     * 
     * @return bool
     * @throws RouteException
     */
    public function save(): bool {
        global $conn;

        try {
            $stmt = $conn->prepare(
                "INSERT INTO rutas (usuario_id, gpx_path, titulo, descripcion,
                elevacion_ganada, duracion_horas, dificultad, ubicacion,
                distancia_km, start_lat, start_lng, start_elevation,
                tipo_ruta, ruta_carpeta)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "isssddssddddss",
                $this->usuario,
                $this->filename,
                $this->title,
                $this->description,
                $this->elevation,
                $this->duration,
                $this->difficulty,
                $this->location,
                $this->length,
                $this->startLat,
                $this->startLng,
                $this->startElevation,
                $this->tipo_ruta,
                $this->ruta_carpeta
            );

            if (!$stmt->execute()) {
                throw new RouteException('Error saving route', ['error' => $stmt->error]);
            }

            $this->id = $stmt->insert_id;
            $this->saveImages();
            $this->initializeStats();

            return true;

        } catch (\Exception $e) {
            throw new RouteException(
                'Error saving route', 
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Guarda las imágenes asociadas
     * 
     * @throws RouteException
     */
    private function saveImages(): void {
        global $conn;

        if (empty($this->images)) {
            return;
        }

        $stmt = $conn->prepare(
            "INSERT INTO ruta_imagenes (ruta_id, imagen_path) VALUES (?, ?)"
        );

        foreach ($this->images as $image) {
            $stmt->bind_param("is", $this->id, $image);
            if (!$stmt->execute()) {
                throw new RouteException('Error saving route images');
            }
        }
    }

    /**
     * Inicializa estadísticas de la ruta
     */
    private function initializeStats(): void {
        global $conn;

        // El trigger after_ruta_insert ya crea el registro en route_popularity automáticamente.
        // Este INSERT IGNORE es solo por seguridad en caso de que el trigger no exista.
        $stmt = $conn->prepare(
            "INSERT IGNORE INTO route_popularity (ruta_id) VALUES (?)"
        );

        $stmt->bind_param("i", $this->id);
        $stmt->execute();
    }

    /**
     * Calcula la puntuación de popularidad
     * 
     * @return float
     */
    public function calculatePopularityScore(): float {
        $weights = [
            'views' => 1,
            'downloads' => 2,
            'favorites' => 3,
            'completes' => 4
        ];

        return (
            $this->stats['view_count'] * $weights['views'] +
            $this->stats['download_count'] * $weights['downloads'] +
            $this->stats['favorite_count'] * $weights['favorites'] +
            $this->stats['complete_count'] * $weights['completes']
        ) / array_sum($weights);
    }

    /**
     * Calcula el ritmo promedio
     * 
     * @return float
     */
    public function calculatePace(): float {
        return $this->length > 0 ? $this->duration / $this->length : 0;
    }

    /**
     * Valida los datos de la ruta
     * 
     * @return bool
     * @throws RouteException
     */
    public function validate(): bool {
        $errors = [];

        if (empty($this->title)) {
            $errors[] = 'Title is required';
        }

        if ($this->elevation < 0) {
            $errors[] = 'Elevation must be positive';
        }

        if ($this->duration <= 0) {
            $errors[] = 'Duration must be positive';
        }

        if (!in_array($this->difficulty, ['beginner', 'intermediate', 'advanced'])) {
            $errors[] = 'Invalid difficulty level';
        }

        if ($this->length <= 0) {
            $errors[] = 'Length must be positive';
        }

        if (!$this->validateCoordinates()) {
            $errors[] = 'Invalid coordinates';
        }

        if (!empty($errors)) {
            throw new RouteException(
                'Route validation failed', 
                ['errors' => $errors]
            );
        }

        return true;
    }

    /**
     * Valida las coordenadas
     * 
     * @return bool
     */
    private function validateCoordinates(): bool {
        return $this->startLat >= 42.0 && $this->startLat <= 43.5 &&
               $this->startLng >= -2.5 && $this->startLng <= 3.5;
    }

    // Getters y Setters
    public function getId(): int {
        return $this->id;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function setTitle(string $title): void {
        $this->title = trim($title);
    }

    // ... Resto de getters y setters

    /**
     * Convierte el objeto a array
     * 
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'usuario' => $this->usuario,
            'title' => $this->title,
            'description' => $this->description,
            'elevation' => $this->elevation,
            'duration' => $this->duration,
            'difficulty' => $this->difficulty,
            'location' => $this->location,
            'length' => $this->length,
            'startLat' => $this->startLat,
            'startLng' => $this->startLng,
            'startElevation' => $this->startElevation,
            'tipo_ruta' => $this->tipo_ruta,
            'images' => $this->images,
            'stats' => $this->stats
        ];
    }
}