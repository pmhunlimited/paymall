<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/settings.php';

if (!auth()->isLoggedIn() || !security()->requirePin()) {
    header("Location: dashboard.php");
    exit();
}

$user = db_find('users', 'id = ?', [$_SESSION['user_id']]);
$data_plans = json_decode(setting('data_plan_prices', '{}'), true) ?: [];

$message = '';
$error = '';

// Handle purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_data'])) {
    $network = trim($_POST['network'] ?? 'mtn');
    $plan_id = trim($_POST['plan_id'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Validate
    if (!isset($data_plans[$plan_id])) {
        $error = "Invalid data plan selected.";
    } elseif (empty($phone)) {
        $error = "Phone number is required.";
    } else {
        // Normalize phone
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '8') {
            $phone = '0' . $phone;
        } elseif (strlen($phone) === 11 && substr($phone, 0, 3) === '234') {
            $phone = '0' . substr($phone, 3);
        }

        if (!preg_match('/^0(70|80|81|90|91)\d{8}$/', $phone)) {
            $error = "Invalid Nigerian phone number.";
        } else {
            $amount = $data_plans[$plan_id];
            if ($user['wallet_balance'] < $amount) {
                $error = "Insufficient wallet balance. Fund your account.";
            } else {
                // Create pending transaction
                $ref = 'DATA_' . uniqid();
                $txn_id = db_insert('transactions', [
                    'user_id' => $user['id'],
                    'type' => 'data',
                    'amount' => $amount,
                    'status' => 'pending',
                    'reference' => $ref,
                    'gateway' => 'direct',
                    'network' => strtolower($network),
                    'data_plan' => $plan_id,
                    'phone_number' => $phone,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                if ($txn_id) {
                    // Process immediately (or queue for background)
                    $result = mtn_api()->sendData($network, $plan_id, $phone, $phone, $ref);
                    
                    if ($result['success'] ?? false) {
                        // Deduct balance
                        db_update('users', ['wallet_balance' => $user['wallet_balance'] - $amount], 'id = ?', [$user['id']]);
                        
                        // Update transaction
                        db_update('transactions', [
                            'status' => 'success',
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$txn_id]);

                        $message = "✅ " . htmlspecialchars($result['data']['message'] ?? 'Data sent successfully!');
                        $user = db_find('users', 'id = ?', [$user['id']]); // Refresh balance
                    } else {
                        $error = "❌ " . htmlspecialchars($result['error'] ?? 'Failed to send data. Try again.');
                        // Mark failed
                        db_update('transactions', [
                            'status' => 'failed',
                            'error_message' => $result['error'] ?? 'API error',
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$txn_id]);
                    }
                } else {
                    $error = "Failed to create transaction. Try again.";
                }
            }
        }
    }
}
?>

<!-- Same head/styles as dashboard.php -->
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
                <div class="balance-label">AVAILABLE BALANCE</div>
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
                <h2 style="margin-bottom: 1.5rem; text-align: center;">Buy Data Bundle</h2>
                <form method="POST">
                    <input type="hidden" name="buy_data" value="1">

                    <div class="form-group">
                        <label>Network</label>
                        <select name="network" class="form-control" required style="width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px;">
                            <option value="mtn">MTN</option>
                            <!-- Add others later -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" placeholder="08123456789" 
                               value="<?= htmlspecialchars($_GET['phone'] ?? '') ?>" 
                               required style="width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px;">
                    </div>

                    <div class="form-group">
                        <label>Select Data Plan</label>
                        <div style="display: grid; gap: 1rem; margin-top: 0.5rem;">
                            <?php foreach ($data_plans as $id => $price): 
                                // Extract size from ID (e.g., ME2U_NG_Data2Share_1621 → 1GB)
                                $size = '1GB';
                                if (strpos($id, '1621') !== false) $size = '1GB';
                                elseif (strpos($id, '1622') !== false) $size = '2GB (Good Offer)';
                                elseif (strpos($id, '1623') !== false) $size = '3GB (Better Offer)';
                                elseif (strpos($id, '2051') !== false) $size = '5GB (Best Offer)';
                            ?>
                            <label style="background: #f8fafc; border-radius: 12px; padding: 1rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; border: 2px solid #e2e8f0;">
                                <div>
                                    <strong><?= $size ?></strong><br>
                                    <small style="color: #666;"><?= $id ?></small>
                                </div>
                                <div>
                                    <strong>₦<?= number_format($price, 2) ?></strong><br>
                                    <input type="radio" name="plan_id" value="<?= $id ?>" required 
                                           style="width: 18px; height: 18px;" <?= $id === 'ME2U_NG_Data2Share_2051' ? 'checked' : '' ?>>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn" style="background: #06d6a0; width: 100%; padding: 1rem; font-size: 1.1rem; margin-top: 1.5rem;">
                        <i class="fas fa-mobile-alt"></i> Buy Data Bundle
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>