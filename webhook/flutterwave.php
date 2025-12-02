<?php
// webhooks/flutterwave.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/database.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Verify webhook signature
$signature = $_SERVER['HTTP_VERIF_HASH'] ?? '';
$computed = hash_hmac('sha512', file_get_contents('php://input'), setting('webhook_secret', ''));
if ($signature !== $computed) {
    error_log("Flutterwave webhook: Invalid signature");
    http_response_code(401);
    exit('Unauthorized');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['event']) || !isset($input['data'])) {
    error_log("Flutterwave webhook: Invalid payload");
    http_response_code(400);
    exit('Bad Request');
}

try {
    $pdo = getDB();
    $prefix = setting('database.prefix', 'vtu_');

    // Handle charge completed
    if ($input['event'] === 'charge.completed') {
        $data = $input['data'];
        $status = $data['status'] ?? '';
        $tx_ref = $data['tx_ref'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $currency = $data['currency'] ?? '';
        $customer_email = $data['customer']['email'] ?? '';

        if ($status === 'successful' && $currency === 'NGN' && $amount > 0) {
            // Find pending transaction
            $txn = db_find('transactions', 'reference = ? AND status = "pending" AND gateway = "flutterwave"', [$tx_ref]);
            if ($txn) {
                // Credit user wallet
                db_update('users', [
                    'wallet_balance' => db_find('users', 'id = ?', [$txn['user_id']])['wallet_balance'] + $amount
                ], 'id = ?', [$txn['user_id']]);

                // Update transaction
                db_update('transactions', [
                    'status' => 'success',
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$txn['id']]);

                // Log success
                error_log("✅ Flutterwave verified: {$tx_ref}, ₦{$amount} to user {$txn['user_id']}");
            }
        }
    }

    // Respond with 200 OK (Flutterwave requires this)
    http_response_code(200);
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    error_log("Flutterwave webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
?>