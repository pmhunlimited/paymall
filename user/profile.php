<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

if (!auth()->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$pdo = getDB();
$user = db_find('users', 'id = ?', [$_SESSION['user_id']]);
$message = '';
$error = '';

// Handle PIN update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pin'])) {
    $current_pin = $_POST['current_pin'] ?? '';
    $new_pin = $_POST['new_pin'] ?? '';
    $confirm_pin = $_POST['confirm_pin'] ?? '';

    if ($new_pin !== $confirm_pin) {
        $error = "New PINs do not match.";
    } else {
        $result = security()->updatePin($user['id'], $current_pin, $new_pin);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (!password_verify($current_pass, $user['password_hash'])) {
        $error = "Current password is incorrect.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_pass) < 8) {
        $error = "New password must be at least 8 characters.";
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_ARGON2ID);
        db_update('users', ['password_hash' => $new_hash], 'id = ?', [$user['id']]);
        $message = "Password updated successfully.";
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
            <h2 style="margin-bottom: 1.5rem; text-align: center;">Account Settings</h2>

            <?php if ($message): ?>
                <div class="alert" style="background: #e8f5e9; color: #2e7d32; text-align: center; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert" style="background: #ffebee; color: #c62828; text-align: center; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Update PIN -->
            <div class="transactions" style="margin-bottom: 2rem;">
                <h3 style="margin-bottom: 1rem;"><i class="fas fa-key"></i> Security PIN</h3>
                <form method="POST">
                    <input type="hidden" name="update_pin" value="1">
                    <div class="form-group">
                        <label>Current PIN</label>
                        <input type="password" name="current_pin" maxlength="4" inputmode="numeric" required 
                               style="width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px;">
                    </div>
                    <div class="form-group">
                        <label>New PIN (<?= setting('min_pin_length', 4) ?> digits)</label>
                        <input type="password" name="new_pin" maxlength="6" inputmode="numeric" required 
                               style="width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px;">
                    </div>
                    <div class="form-group">
                        <label>Confirm New PIN</label>
                        <input type="password" name="confirm_pin" maxlength="6" inputmode="numeric" required 
                               style="width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px;">
                    </div>
                    <button type="submit" class="btn" style="width: 100%; background: #06d6a0;">
                        <i class="fas fa-lock"></i> Update PIN
                    </button>
                </form>
            </div>

            <!-- Update Password -->
            <div class="transactions">
                <h3 style="margin-bottom: 1rem;"><i class="fas fa-lock"></i> Password</h3>
                <form method="POST">
                    <input type="hidden" name="update_password" value="1">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required 
                               style="width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px;">
                    </div>
                    <div class="form-group">
                        <label>New Password (min 8 chars)</label>
                        <input type="password" name="new_password" minlength="8" required 
                               style="width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px;">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" minlength="8" required 
                               style="width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px;">
                    </div>
                    <button type="submit" class="btn" style="width: 100%; background: #4361ee;">
                        <i class="fas fa-key"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>