<?php
namespace Misuzu\Database;

use PDO;
use PDOStatement;

class DatabaseStatement {
    public $pdo;
    public $stmt;
    private $isQuery;
    private $hasExecuted = false;

    public function __construct(PDOStatement $stmt, PDO $pdo, bool $isQuery) {
        $this->stmt = $stmt;
        $this->pdo = $pdo;
        $this->isQuery = $isQuery;
    }

    public function bind($param, $value, int $dataType = PDO::PARAM_STR): DatabaseStatement {
        $this->stmt->bindValue($param, $value, $dataType);
        return $this;
    }

    public function execute(array $params = []): bool {
        if($this->hasExecuted)
            return true;
        $this->hasExecuted = true;

        return count($params) ? $this->stmt->execute($params) : $this->stmt->execute();
    }

    public function executeGetId(array $params = []): int {
        if($this->hasExecuted)
            return true;
        $this->hasExecuted = true;

        return $this->execute($params) ? $this->pdo->lastInsertId() : 0;
    }

    public function reset(): DatabaseStatement {
        $this->hasExecuted = false;
        return $this;
    }

    public function fetch($default = []) {
        $out = $this->isQuery || $this->execute() ? $this->stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $out ? $out : $default;
    }

    public function fetchAll($default = []) {
        $out = $this->isQuery || $this->execute() ? $this->stmt->fetchAll(PDO::FETCH_ASSOC) : false;
        return $out ? $out : $default;
    }

    public function fetchColumn(int $num = 0, $default = null) {
        $out = $this->isQuery || $this->execute() ? $this->stmt->fetchColumn($num) : false;
        return $out ? $out : $default;
    }

    public function fetchObject(string $className = 'stdClass', ?array $args = null, $default = null) {
        $out = false;

        if($this->isQuery || $this->execute()) {
            $out = $args === null ? $this->fetchObject($className) : $this->fetchObject($className, $args);
        }

        return $out !== false ? $out : $default;
    }

    public function fetchObjects(string $className = 'stdClass', ?array $args = null): array {
        $objects = [];

        while(($object = $this->fetchObject($className, $args, false)) !== false) {
            $objects[] = $object;
        }

        return $objects;
    }
}
