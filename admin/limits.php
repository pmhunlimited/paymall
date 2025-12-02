<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/settings.php';

if (!auth()->isLoggedIn() || !($_SESSION['is_admin'] ?? false)) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_limits'])) {
    $updates = [
        'min_data_amount' => max(100, (float)($_POST['min_data_amount'] ?? 500)),
        'max_data_amount' => min(50000, max(1000, (float)($_POST['max_data_amount'] ?? 10000))),
        'daily_transaction_limit' => min(1000000, max(1000, (float)($_POST['daily_transaction_limit'] ?? 50000))),
        'max_bulk_bundles' => min(100, max(1, (int)($_POST['max_bulk_bundles'] ?? 50))),
        'min_wallet_balance' => max(0, (float)($_POST['min_wallet_balance'] ?? 0)),
    ];

    $success = true;
    foreach ($updates as $key => $value) {
        if (!Settings::set($key, $value)) {
            $success = false;
        }
    }

    if ($success) {
        $message = "Transaction limits updated successfully.";
    } else {
        $error = "Failed to save limits.";
    }
}

// Load current limits
$min_data = (float)setting('min_data_amount', 500);
$max_data = (float)setting('max_data_amount', 10000);
$daily_limit = (float)setting('daily_transaction_limit', 50000);
$max_bulk = (int)setting('max_bulk_bundles', 50);
$min_balance = (float)setting('min_wallet_balance', 0);
?>

<!-- Same head/sidebar structure -->
<body>
    <div class="container">
        <aside class="sidebar">…</aside>
        <main class="main">
            <header class="header">
                <h1><i class="fas fa-ruler-combined"></i> Transaction Limits</h1>
                …
            </header>

            <div class="content">
                …
                <div class="section">
                    <div class="security-card">
                        <h3><i class="fas fa-mobile-alt"></i> Data Bundle Limits</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="min_data_amount">Minimum Data Purchase (₦)</label>
                                <input type="number" step="100" id="min_data_amount" name="min_data_amount" 
                                       value="<?= $min_data ?>" min="100" required>
                                <span class="note">Prevent micro-transactions</span>
                            </div>
                            <div class="form-group">
                                <label for="max_data_amount">Maximum Single Purchase (₦)</label>
                                <input type="number" step="100" id="max_data_amount" name="max_data_amount" 
                                       value="<?= $max_data ?>" min="1000" required>
                                <span class="note">Per transaction cap</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <div class="security-card">
                        <h3><i class="fas fa-calendar-day"></i> Daily & Bulk Limits</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="daily_transaction_limit">Daily Transaction Limit (₦)</label>
                                <input type="number" step="1000" id="daily_transaction_limit" name="daily_transaction_limit" 
                                       value="<?= $daily_limit ?>" min="1000" required>
                                <span class="note">Per user per day</span>
                            </div>
                            <div class="form-group">
                                <label for="max_bulk_bundles">Max Bulk Bundles</label>
                                <input type="number" id="max_bulk_bundles" name="max_bulk_bundles" 
                                       value="<?= $max_bulk ?>" min="1" max="100" required>
                                <span class="note">Maximum bundles per bulk job</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <div class="security-card">
                        <h3><i class="fas fa-wallet"></i> Wallet Rules</h3>
                        <div class="form-group">
                            <label for="min_wallet_balance">Minimum Wallet Balance (₦)</label>
                            <input type="number" step="100" id="min_wallet_balance" name="min_wallet_balance" 
                                   value="<?= $min_balance ?>" min="0" required>
                            <span class="note">Prevent negative balances (set >0 for buffer)</span>
                        </div>
                    </div>
                </div>
                …
            </div>
        </main>
    </div>
</body>
</html>