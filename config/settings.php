<?php
// config/settings.php

/**
 * Centralized Settings Manager
 * Loads from `admin_settings` table (or cache/fallback)
 * Includes caching to reduce DB hits
 */

class Settings {
    private static $cache = null;
    private static $pdo = null;

    private static function getPdo(): PDO {
        if (self::$pdo === null) {
            self::$pdo = getDB();
        }
        return self::$pdo;
    }

    /**
     * Get a setting value by key
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        // Load cache once per request
        if (self::$cache === null) {
            self::loadCache();
        }

        return self::$cache[$key] ?? $default;
    }

    /**
     * Set a setting (admin use only — not for runtime)
     */
    public static function set(string $key, $value): bool {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare("
            INSERT INTO `" . self::getTablePrefix() . "admin_settings` 
            (`setting_key`, `setting_value`) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)
        ");
        $result = $stmt->execute([$key, is_array($value) ? json_encode($value) : $value]);
        
        // Invalidate cache
        self::$cache = null;
        return $result;
    }

    private static function loadCache(): void {
        self::$cache = [];

        try {
            $pdo = self::getPdo();
            $stmt = $pdo->prepare("SELECT `setting_key`, `setting_value` FROM `" . self::getTablePrefix() . "admin_settings`");
            $stmt->execute();
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $key = $row['setting_key'];
                $val = $row['setting_value'];

                // Auto-decode JSON if it looks like it
                if (is_string($val) && in_array(substr($val, 0, 1), ['{', '['])) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $val = $decoded;
                    }
                }

                self::$cache[$key] = $val;
            }
        } catch (Exception $e) {
            error_log("Settings load error: " . $e->getMessage());
            // Use fallback defaults
            self::$cache = [
                'site_name' => 'VTU Fintech',
                'min_pin_length' => 4,
                'max_bulk_limit' => 50,
                'data_plan_prices' => [
                    'ME2U_NG_Data2Share_1621' => 597.00,
                    'ME2U_NG_Data2Share_1622' => 1060.00,
                    'ME2U_NG_Data2Share_1623' => 1365.00,
                    'ME2U_NG_Data2Share_2051' => 1975.00
                ]
            ];
        }
    }

    private static function getTablePrefix(): string {
        $config = require __DIR__ . '/app.php';
        return $config['database']['prefix'] ?? 'vtu_';
    }
}

// Helper function
function setting(string $key, $default = null) {
    return Settings::get($key, $default);
}