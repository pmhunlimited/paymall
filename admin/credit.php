<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!auth()->isLoggedIn() || !($_SESSION['is_admin'] ?? false)) {
    header("Location: login.php");
    exit();
}

$pdo = getDB();
$prefix = setting('database.prefix', 'vtu_');
$message = '';
$error = '';

// Handle credit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credit_user'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $type = $_POST['type'] ?? 'wallet'; // 'wallet' or 'bonus'
    $reason = trim($_POST['reason'] ?? 'Admin credit');

    if ($user_id <= 0) {
        $error = "Invalid user selected.";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than zero.";
    } else {
        // Get current balance
        $user = db_find('users', 'id = ?', [$user_id]);
        if (!$user) {
            $error = "User not found.";
        } else {
            $new_balance = $user["{$type}_balance"] + $amount;
            $field = "{$type}_balance";

            // Update user
            db_update('users', [$field => $new_balance], 'id = ?', [$user_id]);

            // Log transaction
            db_insert('transactions', [
                'user_id' => $user_id,
                'type' => 'fund',
                'amount' => $amount,
                'status' => 'success',
                'reference' => 'CREDIT_' . uniqid(),
                'gateway' => 'admin',
                'error_message' => $reason,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Log admin action
            error_log("ADMIN CREDIT: User {$user_id} +₦{$amount} to {$type} by {$_SESSION['user_id']}");

            $message = "₦" . number_format($amount, 2) . " credited to " . htmlspecialchars($user['username']) . "'s " . ($type === 'wallet' ? 'wallet' : 'bonus') . " balance.";
        }
    }
}

// Search users
$users = [];
$search = $_GET['search'] ?? '';
if ($search) {
    $users = db_select('users', 
        'username LIKE ? OR email LIKE ? OR phone_number LIKE ?', 
        ["%{$search}%", "%{$search}%", "%{$search}%"], 
        'id DESC', 
        10
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit User — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reuse core admin styles */
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
        .section { background: white; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 1.75rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-weight: 500; margin-bottom: 0.5rem; color: #4a5568; }
        input, select { width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 1rem; }
        .btn { background: var(--primary); color: white; border: none; padding: 0.8rem 1.5rem; font-size: 1rem; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #3a56e4; }
        .btn-group { display: flex; gap: 1rem; margin-top: 1.5rem; }
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; }
        .user-card { background: #f8fafc; border-radius: 12px; padding: 1rem; margin-bottom: 0.75rem; border-left: 4px solid var(--primary); }
        .user-card h4 { margin: 0 0 0.25rem 0; color: var(--dark); }
        .user-card p { margin: 0; font-size: 0.9rem; color: #666; }
        .balance-card { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; border-radius: 12px; padding: 1.25rem; text-align: center; }
        .balance-value { font-size: 2rem; font-weight: 700; margin: 0.5rem 0; }
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
                <a href="users.php"><i class="fas fa-users"></i> Users</a>
                <a href="credit.php" class="active"><i class="fas fa-wallet"></i> Credit User</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <main class="main">
            <header class="header">
                <h1><i class="fas fa-wallet"></i> Credit User Account</h1>
                <div class="user-menu">
                    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <div class="content">
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
                    <h2>🔍 Search & Select User</h2>
                    <form method="GET" class="form-group" style="display: flex; gap: 0.5rem;">
                        <input type="text" name="search" placeholder="Username, email, or phone..." 
                               value="<?= htmlspecialchars($search) ?>" autofocus>
                        <button type="submit" class="btn" style="height: 42px; padding: 0 1.5rem;">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>

                    <?php if ($search && empty($users)): ?>
                        <p style="color: #ef476f; margin-top: 1rem;">No users found matching "<?= htmlspecialchars($search) ?>"</p>
                    <?php endif; ?>

                    <?php if (!empty($users)): ?>
                        <h3 style="margin: 1.5rem 0 1rem 0;">Results (<?= count($users) ?>)</h3>
                        <?php foreach ($users as $u): ?>
                            <div class="user-card">
                                <h4><?= htmlspecialchars($u['username']) ?> 
                                    <?php if ($u['is_admin']): ?><span style="background: #06d6a0; color: white; font-size: 0.75rem; padding: 0.1rem 0.5rem; border-radius: 20px; margin-left: 0.5rem;">Admin</span><?php endif; ?>
                                </h4>
                                <p>Email: <?= htmlspecialchars($u['email']) ?></p>
                                <p>Phone: <?= htmlspecialchars($u['phone_number'] ?? '—') ?></p>
                                <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
                                    <div class="balance-card">
                                        <div>Wallet</div>
                                        <div class="balance-value">₦<?= number_format($u['wallet_balance'], 2) ?></div>
                                    </div>
                                    <div class="balance-card" style="background: linear-gradient(135deg, #06d6a0, #05a87d);">
                                        <div>Bonus</div>
                                        <div class="balance-value">₦<?= number_format($u['bonus_balance'], 2) ?></div>
                                    </div>
                                </div>
                                <button onclick="selectUser(<?= $u['id'] ?>, '<?= addslashes($u['username']) ?>')" 
                                        class="btn" style="margin-top: 1rem; width: 100%;">
                                    <i class="fas fa-user-plus"></i> Credit This User
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="section">
                    <h2>💳 Credit Details</h2>
                    <form method="POST" id="creditForm">
                        <input type="hidden" name="credit_user" value="1">
                        <input type="hidden" name="user_id" id="selected_user_id" required>

                        <div class="form-group">
                            <label for="selected_username">Selected User</label>
                            <input type="text" id="selected_username" readonly 
                                   placeholder="Search and select a user above">
                        </div>

                        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                            <div class="form-group">
                                <label for="amount">Amount (₦)</label>
                                <input type="number" step="100" id="amount" name="amount" 
                                       placeholder="e.g., 1000" min="100" required>
                            </div>
                            <div class="form-group">
                                <label for="type">Credit To</label>
                                <select id="type" name="type" required>
                                    <option value="wallet">Wallet Balance</option>
                                    <option value="bonus">Bonus Balance</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reason">Reason (for audit log)</label>
                            <input type="text" id="reason" name="reason" 
                                   placeholder="e.g., Bonus, Refund, Promo" maxlength="100" required>
                        </div>

                        <div class="btn-group">
                            <a href="index.php" class="btn" style="background: #6c757d;">
                                <i class="fas fa-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn" id="creditBtn" disabled>
                                <i class="fas fa-coins"></i> Credit User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
    function selectUser(id, username) {
        document.getElementById('selected_user_id').value = id;
        document.getElementById('selected_username').value = username;
        document.getElementById('creditBtn').disabled = false;
        // Scroll to credit form
        document.querySelector('.section:last-child').scrollIntoView({ behavior: 'smooth' });
    }
    </script>
</body>
</html>