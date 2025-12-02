// In login() method
public function login(string $identifier, string $password): array {
    // Add rate limiting
    $ip_key = 'login_' . $_SERVER['REMOTE_ADDR'];
    if (security_config()->isRateLimited($ip_key)) {
        return ['success' => false, 'message' => 'Too many attempts. Try again later.'];
    }

    // ... rest of login logic ...

    if (!$result['success']) {
        // Log failed attempt
        security_config()->isRateLimited($ip_key); // Increments counter
    }
}