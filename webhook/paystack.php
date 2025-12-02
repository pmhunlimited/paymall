<?php
// webhooks/paystack.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['event']) || !isset($input['data'])) {
    error_log("Paystack webhook: Invalid payload");
    http_response_code(400);
    exit('Bad Request');
}

// Verify signature (Paystack uses x-paystack-signature header)
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$computed = hash_hmac('sha512', file_get_contents('php://input'), setting('paystack_secret_key', ''));
if ($signature !== $computed) {
    error_log("Paystack webhook: Invalid signature");
    http_response_code(401);
    exit('Unauthorized');
}

try {
    $pdo = getDB();
    $prefix = setting('database.prefix', 'vtu_');

    if ($input['event'] === 'charge.success') {
        $data = $input['data'];
        $status = $data['status'] ?? '';
        $reference = $data['reference'] ?? '';
        $amount = (float)($data['amount'] ?? 0) / 100; // Convert kobo to Naira
        $customer_email = $data['customer']['email'] ?? '';

        if ($status === 'success' && $amount > 0) {
            $txn = db_find('transactions', 'reference = ? AND status = "pending" AND gateway = "paystack"', [$reference]);
            if ($txn) {
                db_update('users', [
                    'wallet_balance' => db_find('users', 'id = ?', [$txn['user_id']])['wallet_balance'] + $amount
                ], 'id = ?', [$txn['user_id']]);

                db_update('transactions', [
                    'status' => 'success',
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$txn['id']]);

                error_log("✅ Paystack verified: {$reference}, ₦{$amount} to user {$txn['user_id']}");
            }
        }
    }

    http_response_code(200);
    echo "OK";
} catch (Exception $e) {
    error_log("Paystack webhook error: " . $e->getMessage());
    http_response_code(500);
    echo "Error";
}
?>