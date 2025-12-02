<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/api_keys.php';

if (!auth()->isLoggedIn() || !($_SESSION['is_admin'] ?? false)) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gateways'])) {
    $updates = [
        'flutterwave_enabled' => isset($_POST['flutterwave_enabled']) ? '1' : '0',
        'paystack_enabled' => isset($_POST['paystack_enabled']) ? '1' : '0',
        'bank_transfer_enabled' => isset($_POST['bank_transfer_enabled']) ? '1' : '0',
        'flutterwave_test_mode' => isset($_POST['flutterwave_test_mode']) ? '1' : '0',
        'paystack_test_mode' => isset($_POST['paystack_test_mode']) ? '1' : '0',
        'webhook_secret' => trim($_POST['webhook_secret'] ?? ''),
    ];

    // API Keys (only update if non-empty)
    if (!empty($_POST['flutterwave_public_key'])) {
        $updates['flutterwave_public_key'] = trim($_POST['flutterwave_public_key']);
    }
    if (!empty($_POST['flutterwave_secret_key'])) {
        $updates['flutterwave_secret_key'] = trim($_POST['flutterwave_secret_key']);
    }
    if (!empty($_POST['paystack_public_key'])) {
        $updates['paystack_public_key'] = trim($_POST['paystack_public_key']);
    }
    if (!empty($_POST['paystack_secret_key'])) {
        $updates['paystack_secret_key'] = trim($_POST['paystack_secret_key']);
    }

    // Validate webhook secret (min 16 chars)
    if (strlen($updates['webhook_secret']) < 16) {
        $error = "Webhook secret must be at least 16 characters for security.";
    } else {
        $success = true;
        foreach ($updates as $key => $value) {
            if (!Settings::set($key, $value)) {
                $success = false;
            }
        }

        if ($success) {
            $message = "Payment gateways updated successfully.";
        } else {
            $error = "Failed to save some settings.";
        }
    }
}

// Load current settings
$flutterwave_enabled = setting('flutterwave_enabled', '1') === '1';
$paystack_enabled = setting('paystack_enabled', '1') === '1';
$bank_transfer_enabled = setting('bank_transfer_enabled', '1') === '1';
$flutterwave_test = setting('flutterwave_test_mode', '1') === '1';
$paystack_test = setting('paystack_test_mode', '1') === '1';
$webhook_secret = setting('webhook_secret', 'your_strong_secret_here');

// Mask keys for display
$flutter_pub = APIKeys::mask(getFlutterwavePublicKey());
$flutter_sec = APIKeys::mask(getFlutterwaveSecretKey());
$paystack_pub = APIKeys::mask(getPaystackPublicKey());
$paystack_sec = APIKeys::mask(getPaystackSecretKey());
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateways — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reuse core styles from settings.php */
        :root { --primary: #4361ee; --success: #06d6a0; --warning: #ffd166; --danger: #ef476f; --dark: #2b2d42; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: var(--dark); color: white; padding: 1.5rem 0; }
        .logo { padding: 0 1.5rem 1.5rem; border-bottom: 1px solid #444; }
        .nav-links a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: #aaa; text-decoration: none; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255,255,255,0.1); color: white; }
        .main { flex: 1; overflow: auto; }
        .header { background: white; box-shadow: 0 2px 6px rgba(0,0,0,0.05); padding: 0.75rem 1.5rem; display: flex; justify-content: space-between; }
        .content { padding: 1.5rem; }
        .progress { background: #e2e8f0; border-radius: 10px; height: 6px; margin-bottom: 1.5rem; overflow: hidden; }
        .progress-bar { height: 100%; background: var(--primary); width: 100%; }

        .section { background: white; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 1.75rem; margin-bottom: 1.5rem; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
        .section-header h2 { font-weight: 600; color: var(--dark); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-weight: 500; margin-bottom: 0.5rem; color: #4a5568; }
        input[type="text"], input[type="password"], select { width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 1rem; }
        input[type="checkbox"] { width: auto; margin-right: 0.5rem; }
        .checkbox-group { display: flex; align-items: center; }
        .btn { background: var(--primary); color: white; border: none; padding: 0.8rem 1.5rem; font-size: 1rem; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #3a56e4; }
        .btn-group { display: flex; gap: 1rem; margin-top: 1.5rem; }
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid var(--success); }
        .alert-warning { background: #fff8e6; color: #926a00; border-left: 4px solid var(--warning); }
        .gateway-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; transition: all 0.2s; }
        .gateway-card.active { border-color: var(--primary); box-shadow: 0 4px 12px rgba(67, 97, 238, 0.15); }
        .gateway-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
        .gateway-logo { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-weight: bold; color: white; }
        .flutterwave { background: #ff4e00; } /* Flutterwave orange */
        .paystack { background: #3399cc; } /* Paystack blue */
        .bank-transfer { background: #2b2d42; } /* Dark */
        .note { font-size: 0.85rem; color: #666; margin-top: 0.25rem; display: block; }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="logo">
                <h2><i class="fas fa-bolt"></i> <?= htmlspecialchars(setting('site_name', 'VTU Fintech')) ?></h2>
            </div>
            <nav class="nav-links">
                <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="payment_gateways.php" class="active"><i class="fas fa-credit-card"></i> Payment Gateways</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="api_manager.php"><i class="fas fa-key"></i> API Manager</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <main class="main">
            <header class="header">
                <h1><i class="fas fa-credit-card"></i> Payment Gateway Configuration</h1>
                <div class="user-menu">
                    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <div class="content">
                <!-- Progress -->
                <div class="progress">
                    <div class="progress-bar"></div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="section">
                    <div class="section-header">
                        <h2><i class="fas fa-shield-alt"></i> Security Settings</h2>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="save_gateways" value="1">

                        <div class="form-group">
                            <label for="webhook_secret">Webhook Secret Key</label>
                            <input type="password" id="webhook_secret" name="webhook_secret" 
                                   value="<?= htmlspecialchars($webhook_secret) ?>" 
                                   placeholder="Enter a strong secret (min 16 chars)" required>
                            <span class="note">
                                Used to verify Flutterwave/Paystack webhook authenticity.<br>
                                🔒 Never share this. Store securely.
                            </span>
                        </div>
                    </form>
                </div>

                <!-- Flutterwave Card -->
                <div class="section">
                    <div class="gateway-card <?= $flutterwave_enabled ? 'active' : '' ?>">
                        <div class="gateway-header">
                            <div class="gateway-logo flutterwave">F</div>
                            <h3 style="margin: 0;">Flutterwave</h3>
                            <label class="checkbox-group" style="margin-left: auto;">
                                <input type="checkbox" name="flutterwave_enabled" <?= $flutterwave_enabled ? 'checked' : '' ?>>
                                <span>Enable Flutterwave</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Public Key</label>
                            <input type="text" name="flutterwave_public_key" 
                                   placeholder="Enter public key (leave blank to keep current)" 
                                   value="<?= htmlspecialchars($flutter_pub) ?>">
                        </div>
                        <div class="form-group">
                            <label>Secret Key</label>
                            <input type="password" name="flutterwave_secret_key" 
                                   placeholder="Enter secret key (leave blank to keep current)">
                            <span class="note">Masked for security: <?= htmlspecialchars($flutter_sec) ?></span>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-group">
                                <input type="checkbox" name="flutterwave_test_mode" <?= $flutterwave_test ? 'checked' : '' ?>>
                                <span>Enable Test Mode</span>
                            </label>
                            <span class="note">Use test keys (<code>FLWPUBK_TEST_...</code>) in test mode</span>
                        </div>
                    </div>
                </div>

                <!-- Paystack Card -->
                <div class="section">
                    <div class="gateway-card <?= $paystack_enabled ? 'active' : '' ?>">
                        <div class="gateway-header">
                            <div class="gateway-logo paystack">P</div>
                            <h3 style="margin: 0;">Paystack</h3>
                            <label class="checkbox-group" style="margin-left: auto;">
                                <input type="checkbox" name="paystack_enabled" <?= $paystack_enabled ? 'checked' : '' ?>>
                                <span>Enable Paystack</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Public Key</label>
                            <input type="text" name="paystack_public_key" 
                                   placeholder="Enter public key (leave blank to keep current)" 
                                   value="<?= htmlspecialchars($paystack_pub) ?>">
                        </div>
                        <div class="form-group">
                            <label>Secret Key</label>
                            <input type="password" name="paystack_secret_key" 
                                   placeholder="Enter secret key (leave blank to keep current)">
                            <span class="note">Masked for security: <?= htmlspecialchars($paystack_sec) ?></span>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-group">
                                <input type="checkbox" name="paystack_test_mode" <?= $paystack_test ? 'checked' : '' ?>>
                                <span>Enable Test Mode</span>
                            </label>
                            <span class="note">Use test keys (<code>pk_test_...</code>) in test mode</span>
                        </div>
                    </div>
                </div>

                <!-- Bank Transfer -->
                <div class="section">
                    <div class="gateway-card <?= $bank_transfer_enabled ? 'active' : '' ?>">
                        <div class="gateway-header">
                            <div class="gateway-logo bank-transfer"><i class="fas fa-university"></i></div>
                            <h3 style="margin: 0;">Bank Transfer</h3>
                            <label class="checkbox-group" style="margin-left: auto;">
                                <input type="checkbox" name="bank_transfer_enabled" <?= $bank_transfer_enabled ? 'checked' : '' ?>>
                                <span>Enable Manual Deposits</span>
                            </label>
                        </div>
                        <p class="note">
                            Users can upload payment proof. Admins approve via 
                            <a href="approve_deposit.php" style="color: var(--primary);">Approve Deposits</a>.
                        </p>
                    </div>
                </div>

                <!-- Save Buttons -->
                <div class="btn-group">
                    <a href="index.php" class="btn" style="background: #6c757d;">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <button type="submit" class="btn" formaction="">
                        <i class="fas fa-save"></i> Save Gateway Settings
                    </button>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Toggle gateway card active state on checkbox change
    document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const card = this.closest('.gateway-card');
            if (this.name.endsWith('_enabled')) {
                card.classList.toggle('active', this.checked);
            }
        });
    });
    </script>
</body>
</html>