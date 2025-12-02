<?php
// config/api_keys.php

/**
 * Secure API Key Handler
 * Prevents accidental exposure in logs, errors, or dumps.
 * Keys are loaded from DB via Settings, never stored in this file.
 */

class APIKeys {
    /**
     * Get a named API key (e.g., 'mtn', 'flutterwave_secret')
     * @param string $service e.g. 'mtn', 'flutterwave_public', 'paystack_secret'
     * @return string|null Returns key or null if missing
     */
    public static function get(string $service): ?string {
        $keyMap = [
            'mtn'                 => 'mtn_api_key',
            'flutterwave_public'  => 'flutterwave_public_key',
            'flutterwave_secret'  => 'flutterwave_secret_key',
            'paystack_public'     => 'paystack_public_key',
            'paystack_secret'     => 'paystack_secret_key',
        ];

        $settingKey = $keyMap[$service] ?? null;
        if (!$settingKey) {
            error_log("APIKeys::get() called with invalid service: " . $service);
            return null;
        }

        $key = setting($settingKey);
        
        // Security: Never return empty/placeholder keys
        if (empty($key) || $key === '' || $key === 'YOUR_KEY_HERE') {
            return null;
        }

        return $key;
    }

    /**
     * Mask key for logs/display (e.g., "sk_test_****abcd")
     * @param string|null $key
     * @return string
     */
    public static function mask(?string $key, int $visible = 4): string {
        if (empty($key)) return '******';
        
        $len = strlen($key);
        if ($len <= $visible * 2) {
            return str_repeat('*', $len);
        }
        
        return substr($key, 0, $visible) . str_repeat('*', $len - ($visible * 2)) . substr($key, -$visible);
    }
}

// Helper functions
function getMtnApiKey(): ?string {
    return APIKeys::get('mtn');
}

function getFlutterwavePublicKey(): ?string {
    return APIKeys::get('flutterwave_public');
}

function getFlutterwaveSecretKey(): ?string {
    return APIKeys::get('flutterwave_secret');
}

function getPaystackPublicKey(): ?string {
    return APIKeys::get('paystack_public');
}

function getPaystackSecretKey(): ?string {
    return APIKeys::get('paystack_secret');
}