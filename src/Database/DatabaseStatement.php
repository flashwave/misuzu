<?php
namespace Misuzu\Database;

use PDO;
use PDOStatement;

class DatabaseStatement {
    public $pdo;
    public $stmt;
    private $isQuery;

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
        return count($params) ? $this->stmt->execute($params) : $this->stmt->execute();
    }

    public function executeGetId(array $params = []): int {
        return $this->execute($params) ? $this->pdo->lastInsertId() : 0;
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
            $out = $args === null ? $this->stmt->fetchObject($className) : $this->stmt->fetchObject($className, $args);
        }

        return $out !== false ? $out : $default;
    }

    public function fetchObjects(string $className = 'stdClass', ?array $args = null): array {
        $objects = [];

        if($this->isQuery || $this->execute()) {
            while(($object = $this->stmt->fetchObject($className, $args)) !== false) {
                $objects[] = $object;
            }
        }

        return $objects;
    }
}
