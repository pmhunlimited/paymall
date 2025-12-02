<?php
// includes/api_client.php

require_once __DIR__ . '/../config/api_keys.php';
require_once __DIR__ . '/security.php';

class MTNDataAPI {
    private $api_url = 'https://mtn.subfactory.net/api/v1/automated-gifting/';
    private $api_key;

    public function __construct(?string $api_key = null) {
        $this->api_key = $api_key ?? getMtnApiKey();
        if (!$this->api_key) {
            throw new Exception("MTN API key not configured");
        }
    }

    /**
     * Send data bundle (with duplicate prevention)
     */
    public function sendData(string $network, string $data_plan, string $phone, string $confirm_phone, string $reference): array {
        // 🔒 Prevent duplicates
        if (security()->isDuplicateTransaction($reference, $phone, $data_plan)) {
            return [
                'success' => false,
                'error' => 'Duplicate transaction detected. Please wait or contact support.',
                'duplicate' => true
            ];
        }

        $payload = [
            'network' => strtolower($network),
            'data_plan' => $data_plan,
            'phone_number' => $phone,
            'confirm_phone_number' => $confirm_phone
        ];

        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $this->api_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // MTN can be slow

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("MTN API cURL error: {$error}");
            return ['success' => false, 'error' => 'Network error. Try again.'];
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("MTN API JSON error: " . json_last_error_msg());
            return ['success' => false, 'error' => 'Invalid API response.'];
        }

        // ✅ On success: extract `amount_charged` for dynamic pricing
        if ($result['success'] ?? false) {
            $amount_charged = $result['data']['amount_charged'] ?? null;
            if ($amount_charged !== null) {
                // Optional: Log cost for margin adjustment
                error_log("MTN Cost for {$data_plan}: ₦{$amount_charged}");
            }
        }

        return $result;
    }

    /**
     * Get current balance (for admin monitoring)
     */
    public function getBalance(): ?float {
        // Note: MTN API doesn't have a balance endpoint — this is a placeholder
        // In practice, you'd track via transaction diffs or request from provider
        return null;
    }
}

// Helper
function mtn_api(): MTNDataAPI {
    static $api;
    if ($api === null) {
        $api = new MTNDataAPI();
    }
    return $api;
}