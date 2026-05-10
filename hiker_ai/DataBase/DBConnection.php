<?php
namespace KahirAI\Database;

class DBConnection {
    private static ?DBConnection $instance = null;
    private \mysqli $connection;

    private function __construct() {
        $this->connection = new \mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );

        if ($this->connection->connect_error) {
            throw new \Exception('Error de conexión: ' . $this->connection->connect_error);
        }

        $this->connection->set_charset(DB_CHARSET);
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): \mysqli {
        return $this->connection;
    }

    public function __destruct() {
        $this->connection->close();
    }

    private function __clone() {}
}