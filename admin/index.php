<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

// Enforce admin login
if (!auth()->isLoggedIn() || !($_SESSION['is_admin'] ?? false)) {
    header("Location: login.php");
    exit();
}

$pdo = getDB();
$prefix = setting('database.prefix', 'vtu_');

// Fetch stats
$total_users = $pdo->query("SELECT COUNT(*) FROM `{$prefix}users`")->fetchColumn();
$pending_txns = $pdo->query("SELECT COUNT(*) FROM `{$prefix}transactions` WHERE `status` = 'pending'")->fetchColumn();
$success_txns = $pdo->query("SELECT COUNT(*) FROM `{$prefix}transactions` WHERE `status` = 'success'")->fetchColumn();
$failed_txns = $pdo->query("SELECT COUNT(*) FROM `{$prefix}transactions` WHERE `status` = 'failed'")->fetchColumn();

// Recent transactions (last 5)
$recent_txns = $pdo->query("
    SELECT t.*, u.username 
    FROM `{$prefix}transactions` t
    LEFT JOIN `{$prefix}users` u ON t.user_id = u.id
    ORDER BY t.created_at DESC LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= htmlspecialchars(setting('site_name', 'VTU Fintech')) ?> Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4361ee; --success: #06d6a0; --warning: #ffd166; --danger: #ef476f; --dark: #2b2d42; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #333; }
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 250px; background: var(--dark); color: white; padding: 1.5rem 0; }
        .logo { padding: 0 1.5rem 1.5rem; border-bottom: 1px solid #444; }
        .logo h2 { font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
        .logo i { color: var(--primary); }
        .nav-links { padding: 1rem 0; }
        .nav-links a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: #aaa; text-decoration: none; transition: 0.2s; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-links a i { width: 20px; text-align: center; }
        
        /* Main Content */
        .main { flex: 1; overflow: auto; }
        .header { background: white; box-shadow: 0 2px 6px rgba(0,0,0,0.05); padding: 0.75rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-weight: 600; color: var(--dark); }
        .user-menu { display: flex; align-items: center; gap: 0.75rem; }
        
        .content { padding: 1.5rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
        
        .stat-card {
            background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex; flex-direction: column;
        }
        .stat-card.success { border-top: 4px solid var(--success); }
        .stat-card.pending { border-top: 4px solid var(--warning); }
        .stat-card.failed { border-top: 4px solid var(--danger); }
        .stat-card.users { border-top: 4px solid var(--primary); }
        
        .stat-title { font-size: 0.9rem; color: #666; margin-bottom: 0.5rem; }
        .stat-value { font-size: 2rem; font-weight: 700; margin-bottom: 0.25rem; }
        .stat-change { font-size: 0.85rem; color: #666; }
        
        .section { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 1.5rem; margin-bottom: 1.5rem; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .section-header h2 { font-weight: 600; color: var(--dark); }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.75rem; border-bottom: 1px solid #eee; }
        th { color: #555; font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        .status { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        .status-success { background: #e8f5e9; color: #2e7d32; }
        .status-pending { background: #fff8e6; color: #e69100; }
        .status-failed { background: #ffebee; color: #c62828; }
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .quick-btn { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding: 1rem; background: #f1f5f9; border-radius: 12px; text-decoration: none; color: var(--dark); transition: 0.2s; }
        .quick-btn:hover { background: #e2e8f0; transform: translateY(-2px); }
        .quick-btn i { font-size: 1.5rem; color: var(--primary); }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <h2><i class="fas fa-bolt"></i> VTU Admin</h2>
            </div>
            <nav class="nav-links">
                <a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
                <a href="transactions.php"><i class="fas fa-exchange-alt"></i> Transactions</a>
                <a href="users.php"><i class="fas fa-users"></i> Users</a>
                <a href="api_manager.php"><i class="fas fa-key"></i> API Manager</a>
                <a href="payment_gateways.php"><i class="fas fa-credit-card"></i> Payment Gateways</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main">
            <header class="header">
                <h1>Dashboard</h1>
                <div class="user-menu">
                    <span>Hello, <?= htmlspecialchars($_SESSION['username']) ?></span>
                    <i class="fas fa-user-circle" style="font-size: 1.5rem;"></i>
                </div>
            </header>

            <div class="content">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card users">
                        <div class="stat-title">Total Users</div>
                        <div class="stat-value"><?= number_format($total_users) ?></div>
                        <div class="stat-change">↑ 12% from last month</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-title">Successful Txns</div>
                        <div class="stat-value"><?= number_format($success_txns) ?></div>
                        <div class="stat-change">↑ 8% this week</div>
                    </div>
                    <div class="stat-card pending">
                        <div class="stat-title">Pending Txns</div>
                        <div class="stat-value"><?= number_format($pending_txns) ?></div>
                        <div class="stat-change">Requires review</div>
                    </div>
                    <div class="stat-card failed">
                        <div class="stat-title">Failed Txns</div>
                        <div class="stat-value"><?= number_format($failed_txns) ?></div>
                        <div class="stat-change">Check logs</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="section">
                    <div class="section-header">
                        <h2>Quick Actions</h2>
                    </div>
                    <div class="quick-actions">
                        <a href="users.php" class="quick-btn">
                            <i class="fas fa-user-plus"></i>
                            <span>Add User</span>
                        </a>
                        <a href="credit.php" class="quick-btn">
                            <i class="fas fa-wallet"></i>
                            <span>Credit User</span>
                        </a>
                        <a href="approve_deposit.php" class="quick-btn">
                            <i class="fas fa-file-invoice"></i>
                            <span>Approve Deposits</span>
                        </a>
                        <a href="block_ids.php" class="quick-btn">
                            <i class="fas fa-ban"></i>
                            <span>Block IDs</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="section">
                    <div class="section-header">
                        <h2>Recent Transactions</h2>
                        <a href="transactions.php" style="color: var(--primary); text-decoration: none;">View All →</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_txns as $txn): ?>
                            <tr>
                                <td><?= htmlspecialchars(substr($txn['reference'], 0, 8)) ?>...</td>
                                <td><?= htmlspecialchars($txn['username'] ?? '—') ?></td>
                                <td><?= ucfirst($txn['type']) ?></td>
                                <td>₦<?= number_format($txn['amount'], 2) ?></td>
                                <td>
                                    <?php 
                                    $status_class = match($txn['status']) {
                                        'success' => 'status-success',
                                        'pending' => 'status-pending',
                                        'failed' => 'status-failed',
                                        default => ''
                                    };
                                    ?>
                                    <span class="status <?= $status_class ?>"><?= ucfirst($txn['status']) ?></span>
                                </td>
                                <td><?= date('M d, H:i', strtotime($txn['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_txns)): ?>
                            <tr><td colspan="6" style="text-align: center; color: #999;">No transactions yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>