<?php
/**
 * Modelo para usuarios en Kahir.AI
 * 
 * Maneja la estructura y comportamiento de los usuarios,
 * incluyendo autenticación, preferencias y estadísticas.
 */

namespace KahirAI\Models;

use KahirAI\Exceptions\AIException;
use KahirAI\Services\LoggerService;

class User {
    /**
     * @var int ID del usuario
     */
    private int $id;

    /**
     * @var string Nombre del usuario
     */
    private string $nombre;

    /**
     * @var string Apellidos del usuario
     */
    private string $apellidos;

    /**
     * @var string Email del usuario
     */
    private string $mail;

    /**
     * @var string Nombre de usuario
     */
    private string $usuario;

    /**
     * @var string Teléfono del usuario
     */
    private string $telefono;

    /**
     * @var string Rol del usuario
     */
    private string $rol;

    /**
     * @var string Estado del usuario
     */
    private string $estado;

    /**
     * @var string Token de verificación/recuperación
     */
    private string $token;

    /**
     * @var string Ruta de la imagen de perfil
     */
    private string $imagen;

    /**
     * @var string Suscripción al boletín
     */
    private string $boletin;

    /**
     * @var int Timestamp de creación
     */
    private int $creado;

    /**
     * @var int Timestamp de última modificación
     */
    private int $modificado;

    /**
     * @var LoggerService
     */
    private LoggerService $logger;

    /**
     * Constructor
     * 
     * @param array $data Datos del usuario
     */
    public function __construct(array $data = []) {
        $this->logger = new LoggerService('user_model');
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }

    /**
     * Carga un usuario por su ID
     * 
     * @param int $id ID del usuario
     * @return self
     * @throws AIException
     */
    public static function findById(int $id): self {
        global $conn;

        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new AIException("Usuario no encontrado", "USER_NOT_FOUND");
        }

        return new self($result->fetch_assoc());
    }

    /**
     * Carga un usuario por su nombre de usuario
     * 
     * @param string $username Nombre de usuario
     * @return self
     * @throws AIException
     */
    public static function findByUsername(string $username): self {
        global $conn;

        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new AIException("Usuario no encontrado", "USER_NOT_FOUND");
        }

        return new self($result->fetch_assoc());
    }

    /**
     * Guarda o actualiza el usuario
     * 
     * @return bool
     * @throws AIException
     */
    public function save(): bool {
        global $conn;

        if (isset($this->id)) {
            return $this->update();
        }

        try {
            $stmt = $conn->prepare(
                "INSERT INTO usuarios (nombre, apellidos, mail, usuario, telefono, 
                rol, estado, token, imagen, boletin, creado, modificado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $now = time();
            $stmt->bind_param(
                "ssssssssssii",
                $this->nombre,
                $this->apellidos,
                $this->mail,
                $this->usuario,
                $this->telefono,
                $this->rol,
                $this->estado,
                $this->token,
                $this->imagen,
                $this->boletin,
                $now,
                $now
            );

            if (!$stmt->execute()) {
                throw new AIException("Error al guardar usuario", "SAVE_ERROR");
            }

            $this->id = $stmt->insert_id;
            $this->creado = $now;
            $this->modificado = $now;

            return true;

        } catch (\Exception $e) {
            $this->logger->error("Error guardando usuario", [
                'error' => $e->getMessage(),
                'usuario' => $this->usuario
            ]);
            throw new AIException("Error al guardar usuario", "SAVE_ERROR");
        }
    }

    /**
     * Actualiza el usuario existente
     * 
     * @return bool
     * @throws AIException
     */
    private function update(): bool {
        global $conn;

        try {
            $stmt = $conn->prepare(
                "UPDATE usuarios SET 
                nombre = ?, apellidos = ?, mail = ?, telefono = ?,
                rol = ?, estado = ?, token = ?, imagen = ?, 
                boletin = ?, modificado = ?
                WHERE id = ?"
            );

            $now = time();
            $stmt->bind_param(
                "sssssssssii",
                $this->nombre,
                $this->apellidos,
                $this->mail,
                $this->telefono,
                $this->rol,
                $this->estado,
                $this->token,
                $this->imagen,
                $this->boletin,
                $now,
                $this->id
            );

            if (!$stmt->execute()) {
                throw new AIException("Error al actualizar usuario", "UPDATE_ERROR");
            }

            $this->modificado = $now;
            return true;

        } catch (\Exception $e) {
            $this->logger->error("Error actualizando usuario", [
                'error' => $e->getMessage(),
                'usuario' => $this->usuario
            ]);
            throw new AIException("Error al actualizar usuario", "UPDATE_ERROR");
        }
    }

    /**
     * Obtiene las rutas favoritas del usuario
     * 
     * @return array
     */
    public function getFavoriteRoutes(): array {
        global $conn;

        $stmt = $conn->prepare(
            "SELECT r.* FROM rutas r
             JOIN user_favorites uf ON r.id = uf.ruta_id
             WHERE uf.usuario_id = ?
             ORDER BY uf.fecha DESC"
        );
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene las rutas completadas por el usuario
     * 
     * @return array
     */
    public function getCompletedRoutes(): array {
        global $conn;

        $stmt = $conn->prepare(
            "SELECT r.* FROM rutas r
             JOIN user_completed uc ON r.id = uc.ruta_id
             WHERE uc.usuario_id = ?
             ORDER BY uc.fecha_completada DESC"
        );
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene las listas del usuario
     * 
     * @return array
     */
    public function getLists(): array {
        global $conn;

        $stmt = $conn->prepare(
            "SELECT * FROM user_lists WHERE usuario_id = ?"
        );
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Valida los datos del usuario
     * 
     * @return bool
     * @throws AIException
     */
    public function validate(): bool {
        $errors = [];

        if (empty($this->nombre)) {
            $errors[] = "El nombre es requerido";
        }

        if (empty($this->usuario)) {
            $errors[] = "El nombre de usuario es requerido";
        }

        if (empty($this->mail) || !filter_var($this->mail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email inválido";
        }

        if (!in_array($this->rol, ['A', 'I', 'U', 'E'])) {
            $errors[] = "Rol inválido";
        }

        if (!in_array($this->estado, ['A', 'P', 'B', 'D'])) {
            $errors[] = "Estado inválido";
        }

        if (!empty($errors)) {
            throw new AIException("Errores de validación", "VALIDATION_ERROR", [
                'errors' => $errors
            ]);
        }

        return true;
    }

    /**
     * Convierte el objeto a array
     * 
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellidos' => $this->apellidos,
            'mail' => $this->mail,
            'usuario' => $this->usuario,
            'telefono' => $this->telefono,
            'rol' => $this->rol,
            'estado' => $this->estado,
            'imagen' => $this->imagen,
            'boletin' => $this->boletin,
            'creado' => $this->creado,
            'modificado' => $this->modificado
        ];
    }

    // Getters y Setters
    // ... (implementar según necesidad)
}