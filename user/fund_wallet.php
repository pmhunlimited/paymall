<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/settings.php';

if (!auth()->isLoggedIn() || !security()->requirePin()) {
    header("Location: dashboard.php");
    exit();
}

$user = db_find('users', 'id = ?', [$_SESSION['user_id']]);
$message = '';
$error = '';

// Handle fund request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fund_wallet'])) {
    $amount = (float)($_POST['amount'] ?? 0);
    $gateway = $_POST['gateway'] ?? '';

    if ($amount < (float)setting('min_data_amount', 500)) {
        $error = "Minimum funding amount is ₦" . number_format(setting('min_data_amount', 500), 2);
    } elseif (!in_array($gateway, ['flutterwave', 'paystack', 'bank_transfer'])) {
        $error = "Invalid payment method.";
    } else {
        $ref = 'FUND_' . uniqid();

        // Create pending transaction
        $txn_id = db_insert('transactions', [
            'user_id' => $user['id'],
            'type' => 'fund',
            'amount' => $amount,
            'status' => 'pending',
            'reference' => $ref,
            'gateway' => $gateway,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        if (!$txn_id) {
            $error = "Failed to create transaction. Try again.";
        } else {
            if ($gateway === 'flutterwave') {
                $result = flutterwave_init([
                    'reference' => $ref,
                    'amount' => $amount,
                    'email' => $user['email'],
                    'name' => $user['username'],
                    'phone' => $user['phone_number'] ?? '',
                    'description' => 'Wallet Funding',
                    'callback_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/user/dashboard.php'
                ]);

                if ($result['status'] === 'success') {
                    header("Location: " . $result['link']);
                    exit();
                } else {
                    $error = "Payment gateway error. Try again.";
                }

            } elseif ($gateway === 'paystack') {
                $result = paystack_init([
                    'reference' => $ref,
                    'amount' => $amount,
                    'email' => $user['email'],
                    'name' => $user['username'],
                    'phone' => $user['phone_number'] ?? '',
                    'callback_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/user/dashboard.php'
                ]);

                if ($result['status'] === 'success') {
                    header("Location: " . $result['link']);
                    exit();
                } else {
                    $error = "Payment gateway error. Try again.";
                }

            } elseif ($gateway === 'bank_transfer') {
                // Create bank payment record
                $bank_id = db_insert('bank_payments', [
                    'user_id' => $user['id'],
                    'amount' => $amount,
                    'reference' => $ref,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                if ($bank_id) {
                    $message = "✅ Bank transfer initiated! Reference: <strong>{$ref}</strong>";
                } else {
                    $error = "Failed to create bank payment record.";
                }
            }
        }
    }
}
?>

<!-- Same head/styles -->
<body>
    <div class="container">
        <header class="header">
            <a href="dashboard.php" style="color: var(--dark); text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
            </div>
        </header>

        <div style="max-width: 600px; margin: 2rem auto;">
            <div class="balance-card" style="background: linear-gradient(135deg, #4361ee, #3a0ca3);">
                <div class="balance-label">CURRENT BALANCE</div>
                <div class="balance-amount">₦<?= number_format($user['wallet_balance'], 2) ?></div>
            </div>

            <?php if ($message): ?>
                <div class="alert" style="background: #e8f5e9; color: #2e7d32; text-align: center; padding: 1.5rem; border-radius: 16px; margin: 1.5rem 0;">
                    <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i><br>
                    <?= $message ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert" style="background: #ffebee; color: #c62828; text-align: center; padding: 1.5rem; border-radius: 16px; margin: 1.5rem 0;">
                    <i class="fas fa-times-circle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i><br>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="transactions" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; text-align: center;">Fund Your Wallet</h2>
                <form method="POST">
                    <input type="hidden" name="fund_wallet" value="1">

                    <div class="form-group">
                        <label>Amount (₦)</label>
                        <input type="number" name="amount" step="100" min="<?= setting('min_data_amount', 500) ?>" 
                               placeholder="e.g., 1000" required 
                               style="width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px;">
                        <small style="color: #666; display: block; margin-top: 0.5rem;">
                            Minimum: ₦<?= number_format(setting('min_data_amount', 500), 2) ?>
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Payment Method</label>
                        <div style="display: grid; gap: 1rem; margin-top: 0.5rem;">
                            <?php if (setting('flutterwave_enabled', '1') === '1'): ?>
                            <label style="background: #f8fafc; border-radius: 12px; padding: 1rem; cursor: pointer; border: 2px solid #e2e8f0;">
                                <input type="radio" name="gateway" value="flutterwave" required>
                                <span style="margin-left: 0.75rem;">
                                    <strong>Flutterwave</strong><br>
                                    <small>Cards, Bank Transfer, USSD</small>
                                </span>
                            </label>
                            <?php endif; ?>

                            <?php if (setting('paystack_enabled', '1') === '1'): ?>
                            <label style="background: #f8fafc; border-radius: 12px; padding: 1rem; cursor: pointer; border: 2px solid #e2e8f0;">
                                <input type="radio" name="gateway" value="paystack">
                                <span style="margin-left: 0.75rem;">
                                    <strong>Paystack</strong><br>
                                    <small>Cards, Bank Transfer, QR</small>
                                </span>
                            </label>
                            <?php endif; ?>

                            <?php if (setting('bank_transfer_enabled', '1') === '1'): ?>
                            <label style="background: #f8fafc; border-radius: 12px; padding: 1rem; cursor: pointer; border: 2px solid #e2e8f0;">
                                <input type="radio" name="gateway" value="bank_transfer">
                                <span style="margin-left: 0.75rem;">
                                    <strong>Bank Transfer</strong><br>
                                    <small>Upload proof for admin approval</small>
                                </span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn" style="width: 100%; background: #06d6a0; padding: 1rem; font-size: 1.1rem; margin-top: 1.5rem;">
                        <i class="fas fa-wallet"></i> Continue to Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>