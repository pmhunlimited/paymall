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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_security'])) {
    $updates = [
        'session_timeout' => max(300, min(86400, (int)($_POST['session_timeout'] ?? 3600))), // 5m–24h
        'pin_attempts_limit' => max(1, min(10, (int)($_POST['pin_attempts_limit'] ?? 3))),
        'pin_lockout_minutes' => max(1, min(120, (int)($_POST['pin_lockout_minutes'] ?? 5))),
        'failed_login_limit' => max(1, min(20, (int)($_POST['failed_login_limit'] ?? 5))),
        'failed_login_lockout' => max(5, min(1440, (int)($_POST['failed_login_lockout'] ?? 30))),
        'require_pin_for_fund' => isset($_POST['require_pin_for_fund']) ? '1' : '0',
        'require_pin_for_data' => isset($_POST['require_pin_for_data']) ? '1' : '0',
        'security_questions_enabled' => isset($_POST['security_questions_enabled']) ? '1' : '0',
    ];

    $success = true;
    foreach ($updates as $key => $value) {
        if (!Settings::set($key, $value)) {
            $success = false;
        }
    }

    if ($success) {
        $message = "Security settings updated successfully.";
    } else {
        $error = "Failed to save some settings. Check permissions.";
    }
}

// Load current settings
$session_timeout = (int)setting('session_timeout', 3600);
$pin_attempts = (int)setting('pin_attempts_limit', 3);
$pin_lockout = (int)setting('pin_lockout_minutes', 5);
$failed_login_limit = (int)setting('failed_login_limit', 5);
$failed_login_lockout = (int)setting('failed_login_lockout', 30);
$require_pin_fund = setting('require_pin_for_fund', '1') === '1';
$require_pin_data = setting('require_pin_for_data', '1') === '1';
$security_questions = setting('security_questions_enabled', '0') === '1';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Manager — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reuse core styles from previous admin files */
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
        input[type="number"], select { width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 1rem; }
        .checkbox-group { display: flex; align-items: center; }
        .btn { background: var(--primary); color: white; border: none; padding: 0.8rem 1.5rem; font-size: 1rem; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #3a56e4; }
        .btn-group { display: flex; gap: 1rem; margin-top: 1.5rem; }
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid var(--success); }
        .security-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; background: #f8fafc; }
        .security-card h3 { margin: 0 0 1rem 0; color: var(--dark); display: flex; align-items: center; gap: 0.5rem; }
        .security-card i { color: var(--primary); }
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
                <a href="security.php" class="active"><i class="fas fa-shield-alt"></i> Security</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <main class="main">
            <header class="header">
                <h1><i class="fas fa-shield-alt"></i> Security Manager</h1>
                <div class="user-menu">
                    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <div class="content">
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

                <form method="POST">
                    <input type="hidden" name="save_security" value="1">

                    <!-- Session Security -->
                    <div class="section">
                        <div class="security-card">
                            <h3><i class="fas fa-hourglass-half"></i> Session Security</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="session_timeout">Session Timeout (seconds)</label>
                                    <input type="number" id="session_timeout" name="session_timeout" 
                                           value="<?= $session_timeout ?>" min="300" max="86400" required>
                                    <span class="note">Auto-logout after inactivity (5 min – 24 hrs)</span>
                                </div>
                                <div class="form-group">
                                    <label for="failed_login_limit">Failed Login Limit</label>
                                    <input type="number" id="failed_login_limit" name="failed_login_limit" 
                                           value="<?= $failed_login_limit ?>" min="1" max="20" required>
                                    <span class="note">Lock account after N failed attempts</span>
                                </div>
                                <div class="form-group">
                                    <label for="failed_login_lockout">Lockout Duration (minutes)</label>
                                    <input type="number" id="failed_login_lockout" name="failed_login_lockout" 
                                           value="<?= $failed_login_lockout ?>" min="5" max="1440" required>
                                    <span class="note">Account locked for N minutes after too many failures</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PIN Security -->
                    <div class="section">
                        <div class="security-card">
                            <h3><i class="fas fa-key"></i> Security PIN Controls</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="pin_attempts_limit">PIN Attempts Limit</label>
                                    <input type="number" id="pin_attempts_limit" name="pin_attempts_limit" 
                                           value="<?= $pin_attempts ?>" min="1" max="10" required>
                                    <span class="note">Lock PIN entry after N wrong attempts</span>
                                </div>
                                <div class="form-group">
                                    <label for="pin_lockout_minutes">PIN Lockout (minutes)</label>
                                    <input type="number" id="pin_lockout_minutes" name="pin_lockout_minutes" 
                                           value="<?= $pin_lockout ?>" min="1" max="120" required>
                                    <span class="note">Wait N minutes before retrying PIN</span>
                                </div>
                                <div class="form-group" style="align-self: flex-end;">
                                    <label class="checkbox-group">
                                        <input type="checkbox" name="require_pin_for_fund" <?= $require_pin_fund ? 'checked' : '' ?>>
                                        <span>Require PIN for Wallet Funding</span>
                                    </label>
                                    <label class="checkbox-group">
                                        <input type="checkbox" name="require_pin_for_data" <?= $require_pin_data ? 'checked' : '' ?>>
                                        <span>Require PIN for Data Purchase</span>
                                    </label>
                                    <label class="checkbox-group">
                                        <input type="checkbox" name="security_questions_enabled" <?= $security_questions ? 'checked' : '' ?>>
                                        <span>Enable Security Questions (future)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- IP Blocking (Placeholder) -->
                    <div class="section">
                        <div class="security-card">
                            <h3><i class="fas fa-ban"></i> IP Blocking (Coming Soon)</h3>
                            <p class="note">
                                🔜 In next update:  
                                • Block suspicious IPs  
                                • Fail2ban integration  
                                • Geo-restrictions  
                            </p>
                        </div>
                    </div>

                    <div class="btn-group">
                        <a href="index.php" class="btn" style="background: #6c757d;">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Save Security Settings
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>