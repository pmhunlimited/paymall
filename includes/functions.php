<?php
// Global helper functions

require_once __DIR__ . '/Auth.php';

if (!function_exists('auth')) {
    /**
     * Returns a singleton instance of the Auth class.
     *
     * @return Auth
     */
    function auth(): Auth {
        static $auth_instance = null;
        if ($auth_instance === null) {
            $auth_instance = new Auth();
        }
        return $auth_instance;
    }
}
