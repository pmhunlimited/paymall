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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $updates = [
        'site_name' => trim($_POST['site_name'] ?? 'VTU Fintech'),
        'admin_email' => trim($_POST['admin_email'] ?? ''),
        'min_pin_length' => max(4, (int)($_POST['min_pin_length'] ?? 4)),
        'max_bulk_limit' => min(100, max(1, (int)($_POST['max_bulk_limit'] ?? 50))),
        'timezone' => trim($_POST['timezone'] ?? 'Africa/Lagos'),
    ];

    // Validate email
    if (!empty($updates['admin_email']) && !filter_var($updates['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid admin email format.";
    } else {
        $success = true;
        foreach ($updates as $key => $value) {
            if (!Settings::set($key, $value)) {
                $success = false;
            }
        }

        if ($success) {
            $message = "Settings updated successfully.";
            // Reload settings
            Settings::get('site_name'); // Forces cache refresh
        } else {
            $error = "Failed to save some settings. Check file permissions.";
        }
    }
}

// Load current settings
$site_name = setting('site_name', 'VTU Fintech');
$admin_email = setting('admin_email', '');
$min_pin = (int)setting('min_pin_length', 4);
$max_bulk = (int)setting('max_bulk_limit', 50);
$timezone = setting('timezone', 'Africa/Lagos');
$available_timezones = [
    'Africa/Lagos' => 'West Africa (Lagos)',
    'Africa/Cairo' => 'East Africa (Cairo)',
    'UTC' => 'UTC (Coordinated Universal Time)'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4361ee; --success: #06d6a0; --warning: #ffd166; --danger: #ef476f; --dark: #2b2d42; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #333; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: var(--dark); color: white; padding: 1.5rem 0; }
        .logo { padding: 0 1.5rem 1.5rem; border-bottom: 1px solid #444; }
        .logo h2 { font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
        .nav-links a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: #aaa; text-decoration: none; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255,255,255,0.1); color: white; }
        .main { flex: 1; overflow: auto; }
        .header { background: white; box-shadow: 0 2px 6px rgba(0,0,0,0.05); padding: 0.75rem 1.5rem; display: flex; justify-content: space-between; }
        .content { padding: 1.5rem; }

        /* Progress Bar & Steps (consistent with installer) */
        .progress { background: #e2e8f0; border-radius: 10px; height: 6px; margin-bottom: 1.5rem; overflow: hidden; }
        .progress-bar { height: 100%; background: var(--primary); width: 100%; }

        .section { background: white; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 1.75rem; margin-bottom: 1.5rem; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
        .section-header h2 { font-weight: 600; color: var(--dark); display: flex; align-items: center; gap: 0.5rem; }
        .section-header i { color: var(--primary); }

        /* Form */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-weight: 500; margin-bottom: 0.5rem; color: #4a5568; }
        input, select, textarea { width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s; }
        input:focus, select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15); }
        .btn { background: var(--primary); color: white; border: none; padding: 0.8rem 1.5rem; font-size: 1rem; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #3a56e4; opacity: 0.95; }
        .btn-group { display: flex; gap: 1rem; margin-top: 1.5rem; }

        /* Alerts */
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid var(--success); }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid var(--danger); }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="logo">
                <h2><i class="fas fa-bolt"></i> <?= htmlspecialchars($site_name) ?></h2>
            </div>
            <nav class="nav-links">
                <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="transactions.php"><i class="fas fa-exchange-alt"></i> Transactions</a>
                <a href="users.php"><i class="fas fa-users"></i> Users</a>
                <a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a>
                <a href="payment_gateways.php"><i class="fas fa-credit-card"></i> Payment Gateways</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <main class="main">
            <header class="header">
                <h1><i class="fas fa-cog"></i> System Settings</h1>
                <div class="user-menu">
                    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <div class="content">
                <!-- Progress Indicator (for consistency) -->
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
                        <h2><i class="fas fa-sliders-h"></i> General Configuration</h2>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="save_settings" value="1">

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="site_name">Site Name</label>
                                <input type="text" id="site_name" name="site_name" value="<?= htmlspecialchars($site_name) ?>" required>
                                <small class="note">Appears in emails, receipts, and browser tab</small>
                            </div>

                            <div class="form-group">
                                <label for="admin_email">Admin Email</label>
                                <input type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($admin_email) ?>" required>
                                <small class="note">For notifications, alerts, and system emails</small>
                            </div>

                            <div class="form-group">
                                <label for="min_pin_length">Security PIN Length</label>
                                <select id="min_pin_length" name="min_pin_length">
                                    <option value="4" <?= $min_pin == 4 ? 'selected' : '' ?>>4 digits</option>
                                    <option value="5" <?= $min_pin == 5 ? 'selected' : '' ?>>5 digits</option>
                                    <option value="6" <?= $min_pin == 6 ? 'selected' : '' ?>>6 digits</option>
                                </select>
                                <small class="note">Minimum PIN length for users and admin</small>
                            </div>

                            <div class="form-group">
                                <label for="max_bulk_limit">Max Bulk Bundles</label>
                                <input type="number" id="max_bulk_limit" name="max_bulk_limit" 
                                       value="<?= $max_bulk ?>" min="1" max="100" required>
                                <small class="note">Maximum data bundles per bulk transaction</small>
                            </div>

                            <div class="form-group">
                                <label for="timezone">Timezone</label>
                                <select id="timezone" name="timezone">
                                    <?php foreach ($available_timezones as $tz => $label): ?>
                                        <option value="<?= $tz ?>" <?= $timezone == $tz ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="note">Affects timestamps in reports and logs</small>
                            </div>
                        </div>

                        <div class="btn-group">
                            <a href="index.php" class="btn" style="background: #6c757d;">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                            <button type="submit" class="btn">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security Recommendations -->
                <div class="section">
                    <div class="section-header">
                        <h2><i class="fas fa-shield-alt"></i> Security Recommendations</h2>
                    </div>
                    <ul style="padding-left: 1.5rem; line-height: 1.6;">
                        <li>✅ Set <strong>strong PIN rules</strong> (6+ digits for sensitive operations)</li>
                        <li>✅ Enable <strong>2FA</strong> in future updates</li>
                        <li>✅ Restrict <code>/config/</code> and <code>/logs/</code> directories via .htaccess</li>
                        <li>✅ Regularly rotate API keys in <a href="api_manager.php" style="color: var(--primary);">API Manager</a></li>
                        <li>✅ Monitor failed login attempts in logs</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</body>
</html>