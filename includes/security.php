// In verifyPin() method
public function verifyPin(int $user_id, string $pin): bool {
    $user_key = 'pin_' . $user_id;
    
    // Check rate limit first
    if (security_config()->isRateLimited($user_key)) {
        return false;
    }

    // Validate PIN format
    $validation = security_config()->validatePIN($pin);
    if (!$validation['valid']) {
        security_config()->isRateLimited($user_key); // Count as attempt
        return false;
    }

    // ... rest of verification ...
}