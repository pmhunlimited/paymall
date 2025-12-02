<?php
// config/security.php

/**
 * Security Policy Manager
 * PIN strength, rate limiting, session security
 * Configurable via admin UI or .env
 */

class SecurityConfig {
    private $config;

    public function __construct() {
        $this->loadConfig();
    }

    private function loadConfig(): void {
        $this->config = [
            // PIN Policy
            'min_pin_length' => (int)$this->getEnv('MIN_PIN_LENGTH', setting('min_pin_length', 4)),
            'max_pin_length' => (int)$this->getEnv('MAX_PIN_LENGTH', 6),
            'pin_chars' => $this->getEnv('PIN_CHARS', 'digits'), // digits, alphanumeric, all
            
            // Rate Limiting
            'pin_attempts_limit' => (int)$this->getEnv('PIN_ATTEMPTS_LIMIT', setting('pin_attempts_limit', 3)),
            'pin_lockout_minutes' => (int)$this->getEnv('PIN_LOCKOUT_MINUTES', setting('pin_lockout_minutes', 5)),
            'login_attempts_limit' => (int)$this->getEnv('LOGIN_ATTEMPTS_LIMIT', setting('failed_login_limit', 5)),
            'login_lockout_minutes' => (int)$this->getEnv('LOGIN_LOCKOUT_MINUTES', setting('failed_login_lockout', 30)),
            
            // Session Security
            'session_timeout' => (int)$this->getEnv('SESSION_TIMEOUT', setting('session_timeout', 3600)),
            'session_regenerate' => (bool)$this->getEnv('SESSION_REGENERATE', true),
            
            // Security Headers
            'csp_enabled' => (bool)$this->getEnv('CSP_ENABLED', false),
            'hsts_enabled' => (bool)$this->getEnv('HSTS_ENABLED', false),
        ];
    }

    // === PIN Validation ===
    public function validatePIN(string $pin): array {
        $errors = [];
        
        if (strlen($pin) < $this->config['min_pin_length']) {
            $errors[] = "PIN must be at least {$this->config['min_pin_length']} characters.";
        }
        
        if (strlen($pin) > $this->config['max_pin_length']) {
            $errors[] = "PIN cannot exceed {$this->config['max_pin_length']} characters.";
        }
        
        if ($this->config['pin_chars'] === 'digits' && !ctype_digit($pin)) {
            $errors[] = "PIN must contain only digits.";
        }
        
        if ($this->config['pin_chars'] === 'alphanumeric' && !ctype_alnum($pin)) {
            $errors[] = "PIN must contain only letters and numbers.";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    // === Rate Limiting ===
    public function isRateLimited(string $key, int $max_attempts = null, int $window_minutes = null): bool {
        $max_attempts = $max_attempts ?? $this->config['pin_attempts_limit'];
        $window_minutes = $window_minutes ?? $this->config['pin_lockout_minutes'];
        
        $cache_file = $this->getCachePath("rate_limit_{$key}.json");
        
        $data = ['attempts' => [], 'expires' => 0];
        if (file_exists($cache_file)) {
            $data = json_decode(file_get_contents($cache_file), true) ?: $data;
        }

        // Remove expired attempts
        $cutoff = time() - ($window_minutes * 60);
        $data['attempts'] = array_filter($data['attempts'], fn($t) => $t > $cutoff);

        if (count($data['attempts']) >= $max_attempts) {
            return true; // Rate limited
        }

        // Log new attempt
        $data['attempts'][] = time();
        $data['expires'] = time() + ($window_minutes * 60);
        file_put_contents($cache_file, json_encode($data));

        return false;
    }

    public function clearRateLimit(string $key): void {
        $cache_file = $this->getCachePath("rate_limit_{$key}.json");
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
    }

    // === Session Security ===
    public function configureSession(): void {
        // Session timeout
        ini_set('session.gc_maxlifetime', $this->config['session_timeout']);
        
        // Secure cookie settings
        session_set_cookie_params([
            'lifetime' => $this->config['session_timeout'],
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        // Regenerate ID on login
        if ($this->config['session_regenerate']) {
            session_regenerate_id(true);
        }
    }

    // === Security Headers ===
    public function sendSecurityHeaders(): void {
        if ($this->config['hsts_enabled']) {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains", false);
        }
        
        if ($this->config['csp_enabled']) {
            $csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com;";
            header("Content-Security-Policy: {$csp}", false);
        }
        
        header("X-Content-Type-Options: nosniff", false);
        header("X-Frame-Options: DENY", false);
        header("X-XSS-Protection: 1; mode=block", false);
        header("Referrer-Policy: strict-origin-when-cross-origin", false);
    }

    // Helper
    private function getCachePath(string $filename): string {
        $dir = __DIR__ . '/../storage/security';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/' . $filename;
    }

    private function getEnv(string $key, $default = null) {
        static $env = null;
        if ($env === null) {
            $env = [];
            $env_file = __DIR__ . '/../.env';
            if (file_exists($env_file)) {
                foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if (strpos($line, '=') !== false && $line[0] !== '#') {
                        [$name, $value] = explode('=', $line, 2);
                        $env[trim($name)] = trim($value, '"\'');
                    }
                }
            }
        }
        return $env[$key] ?? $default;
    }
}

// Global helper
function security_config(): SecurityConfig {
    static $sec;
    if ($sec === null) {
        $sec = new SecurityConfig();
    }
    return $sec;
}

// Auto-configure session on include
security_config()->configureSession();
security_config()->sendSecurityHeaders();
?>