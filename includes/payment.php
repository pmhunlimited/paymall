<?php
// includes/payment.php

require_once __DIR__ . '/../config/api_keys.php';

class PaymentGateway {
    const FLUTTERWAVE = 'flutterwave';
    const PAYSTACK = 'paystack';
    const BANK_TRANSFER = 'bank_transfer';

    /**
     * Initialize Flutterwave payment
     */
    public static function flutterwaveInit(array $data): ?array {
        $secret = getFlutterwaveSecretKey();
        if (!$secret) {
            error_log("Flutterwave secret key not configured");
            return null;
        }

        $payload = [
            'tx_ref' => $data['reference'],
            'amount' => $data['amount'],
            'currency' => 'NGN',
            'redirect_url' => $data['callback_url'],
            'customer' => [
                'email' => $data['email'],
                'phonenumber' => $data['phone'] ?? '',
                'name' => $data['name'] ?? ''
            ],
            'customizations' => [
                'title' => setting('site_name', 'VTU Fintech'),
                'description' => $data['description'] ?? 'Data bundle purchase',
            ]
        ];

        $ch = curl_init('https://api.flutterwave.com/v3/payments');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $secret,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            $result = json_decode($response, true);
            if ($result && $result['status'] === 'success') {
                return [
                    'status' => 'success',
                    'link' => $result['data']['link'],
                    'flw_ref' => $result['data']['flw_ref']
                ];
            }
        }

        error_log("Flutterwave init failed: " . $response);
        return ['status' => 'error', 'message' => 'Payment gateway error.'];
    }

    /**
     * Verify Flutterwave payment
     */
    public static function flutterwaveVerify(string $tx_ref): ?array {
        $secret = getFlutterwaveSecretKey();
        if (!$secret) return null;

        $ch = curl_init("https://api.flutterwave.com/v3/transactions/{$tx_ref}/verify");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secret]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            $result = json_decode($response, true);
            if ($result['status'] === 'success' && $result['data']['status'] === 'successful') {
                return [
                    'status' => 'success',
                    'amount' => $result['data']['amount'],
                    'currency' => $result['data']['currency'],
                    'customer' => $result['data']['customer']['email'] ?? ''
                ];
            }
        }
        return ['status' => 'failed'];
    }

    /**
     * Initialize Paystack payment
     */
    public static function paystackInit(array $data): ?array {
        $secret = getPaystackSecretKey();
        if (!$secret) {
            error_log("Paystack secret key not configured");
            return null;
        }

        $payload = [
            'email' => $data['email'],
            'amount' => (int)($data['amount'] * 100), // in kobo
            'reference' => $data['reference'],
            'callback_url' => $data['callback_url'],
            'metadata' => [
                'custom_fields' => [
                    ['display_name' => "Phone", 'variable_name' => "phone", 'value' => $data['phone'] ?? '']
                ]
            ]
        ];

        $ch = curl_init('https://api.paystack.co/transaction/initialize');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $secret,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            $result = json_decode($response, true);
            if ($result['status']) {
                return [
                    'status' => 'success',
                    'link' => $result['data']['authorization_url'],
                    'access_code' => $result['data']['access_code']
                ];
            }
        }

        error_log("Paystack init failed: " . $response);
        return ['status' => 'error', 'message' => 'Payment gateway error.'];
    }

    /**
     * Verify Paystack payment (via webhook or manual)
     */
    public static function paystackVerify(string $reference): ?array {
        $secret = getPaystackSecretKey();
        if (!$secret) return null;

        $ch = curl_init("https://api.paystack.co/transaction/verify/{$reference}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secret]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            $result = json_decode($response, true);
            if ($result['status'] && $result['data']['status'] === 'success') {
                return [
                    'status' => 'success',
                    'amount' => $result['data']['amount'] / 100, // convert back to Naira
                    'currency' => $result['data']['currency'],
                    'customer' => $result['data']['customer']['email'] ?? ''
                ];
            }
        }
        return ['status' => 'failed'];
    }

    /**
     * Handle bank transfer: create pending record
     */
    public static function createBankTransfer(int $user_id, float $amount, string $reference): ?int {
        return db_insert('bank_payments', [
            'user_id' => $user_id,
            'amount' => $amount,
            'reference' => $reference,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}

// Helpers
function flutterwave_init(array $data): ?array {
    return PaymentGateway::flutterwaveInit($data);
}

function flutterwave_verify(string $tx_ref): ?array {
    return PaymentGateway::flutterwaveVerify($tx_ref);
}

function paystack_init(array $data): ?array {
    return PaymentGateway::paystackInit($data);
}

function paystack_verify(string $reference): ?array {
    return PaymentGateway::paystackVerify($reference);
}