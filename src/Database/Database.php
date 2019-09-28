<?php
namespace Misuzu\Database;

use PDO;

class Database {
    public $pdo;
    private $stmts = [];

    public function __construct(string $dsn, string $username = '', string $password = '', array $options = []) {
        $this->pdo = new PDO($dsn, $username, $password, $options);
    }

    public function getPDO(): PDO {
        return $this->pdo;
    }

    public function queries(): int {
        return (int)$this->query('SHOW SESSION STATUS LIKE "Questions"')->fetchColumn(1);
    }

    public function exec(string $stmt): int {
        return $this->pdo->exec($stmt);
    }

    public function prepare(string $stmt, array $options = []): DatabaseStatement {
        $encodedOptions = serialize($options);

        if(empty($this->stmts[$stmt][$encodedOptions])) {
            $this->stmts[$stmt][$encodedOptions] = $this->pdo->prepare($stmt, $options);
        }

        return new DatabaseStatement($this->stmts[$stmt][$encodedOptions], $this->pdo, false);
    }

    public function query(string $stmt, ?int $fetchMode = null, ...$args): DatabaseStatement {
        if($fetchMode === null) {
            $pdoStmt = $this->pdo->query($stmt);
        } else {
            $pdoStmt = $this->pdo->query($stmt, $fetchMode, ...$args);
        }

        return new DatabaseStatement($pdoStmt, $this->pdo, true);
    }

    public function lastId(): int {
        return $this->pdo->lastInsertId();
    }
}
