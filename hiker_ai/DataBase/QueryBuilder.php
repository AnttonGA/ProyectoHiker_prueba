<?php
namespace KahirAI\Database;

class QueryBuilder {
    private string $table;
    private array $where = [];
    private array $params = [];
    private string $paramTypes = '';
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $joins = [];

    public function __construct(string $table) {
        $this->table = $table;
    }

    public function where(string $column, string $operator, $value): self {
        $this->where[] = "$column $operator ?";
        $this->params[] = $value;
        $this->paramTypes .= $this->getParamType($value);
        return $this;
    }

    public function join(string $table, string $condition, string $type = 'INNER'): self {
        $this->joins[] = "$type JOIN $table ON $condition";
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self {
        $this->orderBy[] = "$column $direction";
        return $this;
    }

    public function limit(int $limit): self {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self {
        $this->offset = $offset;
        return $this;
    }

    public function get(): array {
        $query = $this->buildQuery();
        $stmt = $this->prepare($query);
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function first(): ?array {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function insert(array $data): int {
        $columns = array_keys($data);
        $values = array_fill(0, count($columns), '?');
        
        $query = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") 
                 VALUES (" . implode(', ', $values) . ")";
        
        foreach ($data as $value) {
            $this->params[] = $value;
            $this->paramTypes .= $this->getParamType($value);
        }

        $stmt = $this->prepare($query);
        return $stmt->insert_id;
    }

    public function update(array $data): bool {
        $set = [];
        foreach ($data as $column => $value) {
            $set[] = "$column = ?";
            $this->params[] = $value;
            $this->paramTypes .= $this->getParamType($value);
        }

        $query = "UPDATE {$this->table} SET " . implode(', ', $set);
        if (!empty($this->where)) {
            $query .= " WHERE " . implode(' AND ', $this->where);
        }

        $stmt = $this->prepare($query);
        return $stmt->affected_rows > 0;
    }

    public function delete(): bool {
        $query = "DELETE FROM {$this->table}";
        if (!empty($this->where)) {
            $query .= " WHERE " . implode(' AND ', $this->where);
        }

        $stmt = $this->prepare($query);
        return $stmt->affected_rows > 0;
    }

    private function buildQuery(): string {
        $query = "SELECT * FROM {$this->table}";

        if (!empty($this->joins)) {
            $query .= " " . implode(' ', $this->joins);
        }

        if (!empty($this->where)) {
            $query .= " WHERE " . implode(' AND ', $this->where);
        }

        if (!empty($this->orderBy)) {
            $query .= " ORDER BY " . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $query .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $query .= " OFFSET {$this->offset}";
        }

        return $query;
    }

    private function prepare(string $query): \mysqli_stmt {
        $conn = DBConnection::getInstance()->getConnection();
        $stmt = $conn->prepare($query);
        
        if (!empty($this->params)) {
            $stmt->bind_param($this->paramTypes, ...$this->params);
        }
        
        $stmt->execute();
        return $stmt;
    }

    private function getParamType($value): string {
        return match(gettype($value)) {
            'integer' => 'i',
            'double' => 'd',
            'string' => 's',
            default => 'b'
        };
    }
}