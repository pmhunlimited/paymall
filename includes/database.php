<?php
// includes/database.php

require_once __DIR__ . '/../config/db.php';

/**
 * Safe DB helpers with prefix support
 */
class DB {
    public static function table(string $name): string {
        $prefix = setting('database.prefix', 'vtu_');
        return "`{$prefix}{$name}`";
    }

    /**
     * Insert record and return ID
     */
    public static function insert(string $table, array $data): ?int {
        $pdo = getDB();
        $columns = implode('`, `', array_keys($data));
        $placeholders = str_repeat('?,', count($data) - 1) . '?';

        $stmt = $pdo->prepare("INSERT INTO " . self::table($table) . " (`{$columns}`) VALUES ({$placeholders})");
        if ($stmt->execute(array_values($data))) {
            return $pdo->lastInsertId();
        }
        return null;
    }

    /**
     * Update record
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int {
        $pdo = getDB();
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "`{$col}` = ?";
        }
        $setClause = implode(', ', $set);

        $stmt = $pdo->prepare("UPDATE " . self::table($table) . " SET {$setClause} WHERE {$where}");
        $params = array_values($data);
        $params = array_merge($params, $whereParams);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Fetch all with optional where
     */
    public static function select(string $table, string $where = '1=1', array $params = [], string $orderBy = '', int $limit = 0): array {
        $pdo = getDB();
        $sql = "SELECT * FROM " . self::table($table) . " WHERE {$where}";
        if ($orderBy) $sql .= " ORDER BY {$orderBy}";
        if ($limit > 0) $sql .= " LIMIT {$limit}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch one
     */
    public static function find(string $table, string $where, array $params = []): ?array {
        $results = self::select($table, $where, $params, '', 1);
        return $results[0] ?? null;
    }
}

// Aliases
function db_insert(string $table, array $data): ?int {
    return DB::insert($table, $data);
}

function db_update(string $table, array $data, string $where, array $whereParams = []): int {
    return DB::update($table, $data, $where, $whereParams);
}

function db_find(string $table, string $where, array $params = []): ?array {
    return DB::find($table, $where, $params);
}

function db_select(string $table, string $where = '1=1', array $params = [], string $orderBy = '', int $limit = 0): array {
    return DB::select($table, $where, $params, $orderBy, $limit);
}