<?php

declare(strict_types=1);

namespace MediTrack\Core;

class QueryBuilder
{
    private Database $db;
    private string $table = '';
    private array $columns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private bool $forUpdate = false;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public static function for(Database $db, string $table): self
    {
        $builder = new self($db);
        return $builder->table($table);
    }

    public function table(string $table): self
    {
        $this->validateIdentifier($table);
        $this->table = $table;
        return $this;
    }

    public function select(array|string $columns = ['*']): self
    {
        $cols = is_array($columns) ? $columns : func_get_args();
        foreach ($cols as $col) {
            if ($col !== '*') {
                $this->validateIdentifier($col);
            }
        }
        $this->columns = $cols;
        return $this;
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        $this->validateIdentifier($column);

        if (func_num_args() === 2) {
            $operator = '=';
            $val = $operatorOrValue;
        } else {
            $operator = strtoupper((string)$operatorOrValue);
            $val = $value;
        }

        $allowedOperators = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];
        if (!in_array($operator, $allowedOperators, true)) {
            throw new \InvalidArgumentException("Invalid operator: {$operator}");
        }

        $this->wheres[] = "{$column} {$operator} ?";
        $this->bindings[] = $val;

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->validateIdentifier($column);
        if (empty($values)) {
            $this->wheres[] = '0 = 1';
            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = "{$column} IN ({$placeholders})";
        foreach ($values as $val) {
            $this->bindings[] = $val;
        }

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->validateIdentifier($column);
        $this->wheres[] = "{$column} IS NULL";
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->validateIdentifier($column);
        $this->wheres[] = "{$column} IS NOT NULL";
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->validateIdentifier($column);
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = "{$column} {$dir}";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function lockForUpdate(): self
    {
        $this->forUpdate = true;
        return $this;
    }

    public function toSql(): string
    {
        if (empty($this->table)) {
            throw new \RuntimeException('Table not specified in QueryBuilder.');
        }

        $cols = implode(', ', $this->columns);
        $sql = "SELECT {$cols} FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        if (!empty($this->orders)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        if ($this->forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $sql;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function get(): array
    {
        return $this->db->fetchAll($this->toSql(), $this->bindings);
    }

    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limit(1);
        return $this->db->fetchOne($clone->toSql(), $clone->bindings);
    }

    public function count(): int
    {
        $clone = clone $this;
        $clone->columns = ['COUNT(*) as aggregate_count'];
        $clone->orders = [];
        $clone->limit = null;
        $clone->offset = null;
        $row = $this->db->fetchOne($clone->toSql(), $clone->bindings);
        return (int)($row['aggregate_count'] ?? 0);
    }

    public function insert(array $data): string
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('No data provided for insert.');
        }

        $columns = [];
        $placeholders = [];
        $bindings = [];

        foreach ($data as $col => $val) {
            $this->validateIdentifier($col);
            $columns[] = $col;
            $placeholders[] = '?';
            $bindings[] = $val;
        }

        $colList = implode(', ', $columns);
        $phList = implode(', ', $placeholders);
        $sql = "INSERT INTO {$this->table} ({$colList}) VALUES ({$phList})";

        $this->db->execute($sql, $bindings);
        return $this->db->lastInsertId();
    }

    public function update(array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('No data provided for update.');
        }

        $sets = [];
        $bindings = [];

        foreach ($data as $col => $val) {
            $this->validateIdentifier($col);
            $sets[] = "{$col} = ?";
            $bindings[] = $val;
        }

        $setClause = implode(', ', $sets);
        $sql = "UPDATE {$this->table} SET {$setClause}";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
            $bindings = array_merge($bindings, $this->bindings);
        }

        return $this->db->execute($sql, $bindings);
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";
        $bindings = [];

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
            $bindings = $this->bindings;
        }

        return $this->db->execute($sql, $bindings);
    }

    private function validateIdentifier(string $identifier): void
    {
        // Whitelist safe identifier names: table, column, alias
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_\.]*$/', $identifier)) {
            throw new \InvalidArgumentException("Invalid SQL identifier: {$identifier}");
        }
    }
}

