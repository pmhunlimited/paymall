<?php

require_once __DIR__ . '/../config/db.php';

class Auth {
    private $pdo;
    private $prefix;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->pdo = getDB();
        // This is a bit of a hack, a proper config object would be better.
        $config = require __DIR__ . '/../config/app.php';
        $this->prefix = $config['database']['prefix'] ?? 'vtu_';
    }

    public function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    public function login(string $identifier, string $password): array {
        $sql = "SELECT * FROM `{$this->prefix}users` WHERE username = :identifier OR email = :identifier";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['identifier' => $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                return ['success' => false, 'message' => 'Your account is disabled.'];
            }
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            return ['success' => true];
        }

        return ['success' => false, 'message' => 'Invalid username/email or password.'];
    }

    public function register(string $username, string $email, string $password, string $phone): array {
        // Validation
        if (empty($username) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Username, email, and password are required.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }

        // Check for duplicates
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$this->prefix}users` WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Username or email already exists.'];
        }

        // Hash password and insert user
        $password_hash = password_hash($password, PASSWORD_ARGON2ID);
        // Default PIN is 0000, hashed
        $pin_hash = password_hash('0000', PASSWORD_ARGON2ID);

        $sql = "INSERT INTO `{$this->prefix}users` (username, email, password_hash, security_pin_hash, phone, is_active) VALUES (?, ?, ?, ?, ?, 1)";
        $stmt = $this->pdo->prepare($sql);

        if ($stmt->execute([$username, $email, $password_hash, $pin_hash, $phone])) {
            return ['success' => true];
        }

        return ['success' => false, 'message' => 'An unexpected error occurred.'];
    }

    public function logout(): void {
        session_destroy();
    }
}
