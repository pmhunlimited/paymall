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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_keys'])) {
    $keys = [
        'mtn_api_key' => trim($_POST['mtn_api_key'] ?? ''),
        'flutterwave_public_key' => trim($_POST['flutterwave_public_key'] ?? ''),
        'flutterwave_secret_key' => trim($_POST['flutterwave_secret_key'] ?? ''),
        'paystack_public_key' => trim($_POST['paystack_public_key'] ?? ''),
        'paystack_secret_key' => trim($_POST['paystack_secret_key'] ?? ''),
    ];

    $success = true;
    foreach ($keys as $key => $value) {
        if (!Settings::set($key, $value)) {
            $success = false;
        }
    }

    if ($success) {
        $message = "API keys updated successfully.";
    } else {
        $error = "Failed to save some keys. Check permissions.";
    }
}

// Load current keys (masked for display)
$mtn_key = APIKeys::mask(getMtnApiKey());
$flutter_pub = APIKeys::mask(getFlutterwavePublicKey());
$flutter_sec = APIKeys::mask(getFlutterwaveSecretKey());
$paystack_pub = APIKeys::mask(getPaystackPublicKey());
$paystack_sec = APIKeys::mask(getPaystackSecretKey());
?>

<!DOCTYPE html>
<html>
<head>
    <title>API Manager — Admin</title>
    <!-- Reuse same head/styles from index.php -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>/* ... same CSS as index.php for consistency ... */</style>
    <style>
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        input[type="text"] { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-family: monospace; }
        .hint { font-size: 0.85rem; color: #666; margin-top: 0.25rem; }
        .btn { background: var(--primary); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; }
    </style>
</head>
<body>
    <!-- Include sidebar & header (same as index.php) -->
    <div class="container">
        <aside class="sidebar">
            <div class="logo"><h2><i class="fas fa-bolt"></i> VTU Admin</h2></div>
            <nav class="nav-links">
                <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="api_manager.php" class="active"><i class="fas fa-key"></i> API Manager</a>
                <!-- ... other links ... -->
            </nav>
        </aside>

        <main class="main">
            <header class="header">
                <h1>API Key Manager</h1>
                <div class="user-menu">
                    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <div class="content">
                <div class="section">
                    <h2>Configure Integration Keys</h2>
                    <p style="color: #666; margin-bottom: 1.5rem;">Enter your provider API keys below. Never share these.</p>

                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="save_keys" value="1">

                        <div class="form-group">
                            <label for="mtn_api_key">MTN Subfactory API Key</label>
                            <input type="text" id="mtn_api_key" name="mtn_api_key" value="<?= htmlspecialchars($mtn_key) ?>">
                            <div class="hint">Header: <code>X-API-Key</code> | Endpoint: <code>https://mtn.subfactory.net/api/v1/automated-gifting/</code></div>
                        </div>

                        <div class="form-group">
                            <label for="flutterwave_public_key">Flutterwave Public Key</label>
                            <input type="text" id="flutterwave_public_key" name="flutterwave_public_key" value="<?= htmlspecialchars($flutter_pub) ?>">
                        </div>

                        <div class="form-group">
                            <label for="flutterwave_secret_key">Flutterwave Secret Key</label>
                            <input type="text" id="flutterwave_secret_key" name="flutterwave_secret_key" value="<?= htmlspecialchars($flutter_sec) ?>">
                        </div>

                        <div class="form-group">
                            <label for="paystack_public_key">Paystack Public Key</label>
                            <input type="text" id="paystack_public_key" name="paystack_public_key" value="<?= htmlspecialchars($paystack_pub) ?>">
                        </div>

                        <div class="form-group">
                            <label for="paystack_secret_key">Paystack Secret Key</label>
                            <input type="text" id="paystack_secret_key" name="paystack_secret_key" value="<?= htmlspecialchars($paystack_sec) ?>">
                        </div>

                        <button type="submit" class="btn"><i class="fas fa-save"></i> Save Keys</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>