<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!auth()->isLoggedIn() || !($_SESSION['is_admin'] ?? false)) {
    exit('Access denied');
}

$pdo = getDB();
$prefix = setting('database.prefix', 'vtu_');

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action']; // 'approve' or 'reject'
    $user_id = (int)($_POST['user_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);

    if ($id && $user_id && $amount > 0) {
        if ($action === 'approve') {
            // Credit user wallet
            db_update('users', ['wallet_balance' => db_find('users', 'id = ?', [$user_id])['wallet_balance'] + $amount], 'id = ?', [$user_id]);
            
            // Mark as approved
            db_update('bank_payments', ['status' => 'approved', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            
            // Log transaction
            db_insert('transactions', [
                'user_id' => $user_id,
                'type' => 'fund',
                'amount' => $amount,
                'status' => 'success',
                'reference' => 'MANUAL_' . uniqid(),
                'gateway' => 'bank_transfer',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $message = "Deposit of ₦" . number_format($amount, 2) . " approved.";
        } elseif ($action === 'reject') {
            db_update('bank_payments', ['status' => 'rejected', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            $message = "Deposit rejected.";
        }
    }
}

// Fetch pending deposits
$pending_deposits = db_select('bank_payments', 'status = "pending"', [], 'created_at DESC');
?>

<!-- Same HTML structure as above -->
<!-- Show table of pending deposits with Approve/Reject buttons -->
<!-- Each row: User, Amount, Reference, Date, Action buttons -->