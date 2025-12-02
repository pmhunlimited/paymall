<?php
// config/db.php

/**
 * Database Connection Handler
 * Uses singleton pattern to avoid redundant connections.
 * Credentials loaded from config/app.php — never hard-coded.
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $config;

    private function __construct() {
        // Load config
        $config_path = __DIR__ . '/app.php';
        if (!file_exists($config_path)) {
            throw new Exception("Configuration file missing. Run installer first.");
        }
        $this->config = require $config_path;

        if (!$this->config['installed'] ?? false) {
            throw new Exception("Application not installed. Run /installer/");
        }

        $db = $this->config['database'] ?? [];
        $host = $db['host'] ?? 'localhost';
        $name = $db['name'] ?? '';
        $user = $db['user'] ?? '';
        $pass = $db['pass'] ?? '';
        $charset = $db['charset'] ?? 'utf8mb4';

        if (empty($name)) {
            throw new Exception("Database name not configured.");
        }

        try {
            $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'"
            ];
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Log error internally, show generic message
            error_log("DB Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed. Contact administrator.");
        }
    }

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }

    // Prevent cloning
    private function __clone() {}
    public function __wakeup() {}
}

// Helper function for quick access
function getDB(): PDO {
    return Database::getInstance();
}