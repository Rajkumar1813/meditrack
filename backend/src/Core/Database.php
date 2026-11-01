<?php

declare(strict_types=1);

namespace MediTrack\Core;

use PDO;
use PDOStatement;
use PDOException;

class Database
{
    private ?PDO $pdo = null;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    public function setPdo(PDO $pdo): void
    {
        $this->pdo = $pdo;
    }

    private function connect(): void
    {
        $driver = $this->config['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            $database = $this->config['database'] ?? ':memory:';
            $dsn = "sqlite:{$database}";
            $username = null;
            $password = null;
        } else {
            $host = $this->config['host'] ?? '127.0.0.1';
            $port = $this->config['port'] ?? 3306;
            $database = $this->config['database'] ?? 'meditrack';
            $charset = $this->config['charset'] ?? 'utf8mb4';
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
            $username = $this->config['username'] ?? 'root';
            $password = $this->config['password'] ?? '';
        }

        $options = $this->config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
            if ($driver === 'sqlite') {
                $this->pdo->sqliteCreateFunction('DATEDIFF', function ($date1, $date2) {
                    $d1 = new \DateTime((string)$date1);
                    $d2 = new \DateTime((string)$date2);
                    return (int)$d2->diff($d1)->format('%r%a');
                }, 2);

                $this->pdo->sqliteCreateFunction('GREATEST', function (...$args) {
                    return max($args);
                });
            }
        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public function driver(): string
    {
        return $this->config['driver'] ?? 'mysql';
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        if (!empty($params)) {
            $isPositional = array_is_list($params);
            foreach ($params as $key => $val) {
                $paramIndex = $isPositional ? $key + 1 : $key;
                $type = match (true) {
                    is_int($val) => PDO::PARAM_INT,
                    is_bool($val) => PDO::PARAM_BOOL,
                    is_null($val) => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                };
                $stmt->bindValue($paramIndex, $val, $type);
            }
            $stmt->execute();
        } else {
            $stmt->execute();
        }
        return $stmt;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetchColumn($column);
        return $result === false ? null : $result;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->pdo()->lastInsertId();
    }

    /**
     * Run a callback inside a database transaction.
     * Rolls back automatically if an exception is thrown.
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $isAlreadyInTransaction = $pdo->inTransaction();

        if (!$isAlreadyInTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($this);
            if (!$isAlreadyInTransaction) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if (!$isAlreadyInTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

