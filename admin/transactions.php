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

// Pagination & Filtering
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = "1=1";
$params = [];

// Status filter
$status = $_GET['status'] ?? '';
if (in_array($status, ['pending', 'success', 'failed'])) {
    $where .= " AND t.status = ?";
    $params[] = $status;
}

// User filter
$user_search = $_GET['user'] ?? '';
if ($user_search) {
    $where .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ?)";
    $like = "%{$user_search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Date range
$start_date = $_GET['start'] ?? '';
$end_date = $_GET['end'] ?? '';
if ($start_date) {
    $where .= " AND DATE(t.created_at) >= ?";
    $params[] = $start_date;
}
if ($end_date) {
    $where .= " AND DATE(t.created_at) <= ?";
    $params[] = $end_date;
}

// Count total
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM `{$prefix}transactions` t
    LEFT JOIN `{$prefix}users` u ON t.user_id = u.id
    WHERE {$where}
");
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();
$pages = ceil($total / $limit);

// Fetch data
$data_stmt = $pdo->prepare("
    SELECT t.*, u.username, u.email 
    FROM `{$prefix}transactions` t
    LEFT JOIN `{$prefix}users` u ON t.user_id = u.id
    WHERE {$where}
    ORDER BY t.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
");
$data_stmt->execute($params);
$transactions = $data_stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Transactions — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reuse core styles from index.php */
        :root { --primary: #4361ee; --success: #06d6a0; --warning: #ffd166; --danger: #ef476f; --dark: #2b2d42; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: var(--dark); color: white; padding: 1.5rem 0; }
        .logo { padding: 0 1.5rem 1.5rem; border-bottom: 1px solid #444; }
        .logo h2 { font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
        .nav-links a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; color: #aaa; text-decoration: none; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255,255,255,0.1); color: white; }
        .main { flex: 1; overflow: auto; }
        .header { background: white; box-shadow: 0 2px 6px rgba(0,0,0,0.05); padding: 0.75rem 1.5rem; display: flex; justify-content: space-between; }
        .content { padding: 1.5rem; }
        .section { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 1.5rem; margin-bottom: 1.5rem; }

        /* Filters */
        .filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .filter-group { display: flex; flex-direction: column; }
        label { font-size: 0.85rem; font-weight: 500; margin-bottom: 0.25rem; color: #555; }
        input, select { padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem; }
        .btn { background: var(--primary); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; font-weight: 500; }
        .btn-filter { background: #e2e8f0; color: #4a5568; }
        .btn:hover { opacity: 0.9; }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.75rem 0.5rem; border-bottom: 1px solid #eee; font-size: 0.95rem; }
        th { color: #4a5568; font-weight: 600; background: #f8fafc; }
        tr:hover td { background: #f1f5f9; }
        .status { padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .status-success { background: #e8f5e9; color: #2e7d32; }
        .status-pending { background: #fff8e6; color: #e69100; }
        .status-failed { background: #ffebee; color: #c62828; }

        /* Pagination */
        .pagination { display: flex; justify-content: center; gap: 0.25rem; margin-top: 1rem; }
        .pagination a { padding: 0.4rem 0.75rem; border: 1px solid #ddd; text-decoration: none; color: #4a5568; border-radius: 4px; }
        .pagination .active { background: var(--primary); color: white; border-color: var(--primary); }
        .pagination .disabled { color: #cbd5e0; pointer-events: none; }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="logo"><h2><i class="fas fa-bolt"></i> VTU Admin</h2></div>
            <nav class="nav-links">
                <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="transactions.php" class="active"><i class="fas fa-exchange-alt"></i> Transactions</a>
                <a href="users.php"><i class="fas fa-users"></i> Users</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <main class="main">
            <header class="header">
                <h1>Transaction History</h1>
                <div class="user-menu">
                    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <div class="content">
                <div class="section">
                    <!-- Filters -->
                    <form method="GET" class="filters">
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="">All</option>
                                <option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Success</option>
                                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>User (name/email/phone)</label>
                            <input type="text" name="user" value="<?= htmlspecialchars($user_search) ?>" placeholder="Search...">
                        </div>
                        <div class="filter-group">
                            <label>Start Date</label>
                            <input type="date" name="start" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="filter-group">
                            <label>End Date</label>
                            <input type="date" name="end" value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                        <div class="filter-group" style="align-self: flex-end;">
                            <button type="submit" class="btn"><i class="fas fa-filter"></i> Filter</button>
                            <a href="transactions.php" class="btn btn-filter" style="margin-top: 0.25rem;">Reset</a>
                        </div>
                    </form>

                    <!-- Table -->
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Ref</th>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Network/Plan</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $txn): ?>
                                <tr>
                                    <td title="<?= htmlspecialchars($txn['reference']) ?>">
                                        <?= htmlspecialchars(substr($txn['reference'], 0, 10)) ?>...
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($txn['username'] ?? '—') ?><br>
                                        <small><?= htmlspecialchars($txn['email'] ?? '') ?></small>
                                    </td>
                                    <td><?= ucfirst($txn['type']) ?></td>
                                    <td>₦<?= number_format($txn['amount'], 2) ?></td>
                                    <td>
                                        <?= $txn['network'] ? htmlspecialchars(strtoupper($txn['network'])) : '—' ?><br>
                                        <small><?= htmlspecialchars(substr($txn['data_plan'] ?? '', 0, 12)) ?>...</small>
                                    </td>
                                    <td><?= htmlspecialchars($txn['phone_number'] ?? '—') ?></td>
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
                                        <?php if ($txn['error_message']): ?>
                                            <br><small title="<?= htmlspecialchars($txn['error_message']) ?>">⚠️ Error</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, H:i', strtotime($txn['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($transactions)): ?>
                                <tr><td colspan="8" style="text-align: center; padding: 2rem; color: #999;">No transactions found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page'))) ?>">&laquo; Prev</a>
                        <?php else: ?>
                            <span class="disabled">&laquo; Prev</span>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page'))) ?>" 
                               class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $pages): ?>
                            <a href="?page=<?= $page + 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page'))) ?>">Next &raquo;</a>
                        <?php else: ?>
                            <span class="disabled">Next &raquo;</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>