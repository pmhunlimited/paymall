<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!auth()->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$pdo = getDB();
$prefix = setting('database.prefix', 'vtu_');
$user_id = $_SESSION['user_id'];

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Filters
$where = "user_id = ?";
$params = [$user_id];
$status = $_GET['status'] ?? '';
if (in_array($status, ['success', 'pending', 'failed'])) {
    $where .= " AND status = ?";
    $params[] = $status;
}

// Count
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$prefix}transactions` WHERE {$where}");
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();
$pages = ceil($total / $limit);

// Fetch
$stmt = $pdo->prepare("
    SELECT * FROM `{$prefix}transactions` 
    WHERE {$where} 
    ORDER BY created_at DESC 
    LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();
?>

<!-- Same head/styles -->
<body>
    <div class="container">
        <header class="header">
            <a href="dashboard.php" style="color: var(--dark); text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars(db_find('users', 'id = ?', [$user_id])['username']) ?></div>
            </div>
        </header>

        <div style="max-width: 1000px; margin: 2rem auto;">
            <h2 style="margin-bottom: 1.5rem; text-align: center;">Transaction History</h2>

            <!-- Filters -->
            <div style="background: white; border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: end;">
                    <div style="flex: 1; min-width: 200px;">
                        <label>Status</label>
                        <select name="status" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px;">
                            <option value="">All</option>
                            <option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Success</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                        </select>
                    </div>
                    <button type="submit" class="btn" style="height: 42px; padding: 0 1.5rem;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </form>
            </div>

            <!-- Transactions Table -->
            <div class="transactions">
                <table>
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Type</th>
                            <th>Details</th>
                            <th>Amount</th>
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
                            <td><?= ucfirst($txn['type']) ?></td>
                            <td>
                                <?php if ($txn['type'] === 'data'): ?>
                                    <strong><?= htmlspecialchars(strtoupper($txn['network'] ?? '—')) ?></strong><br>
                                    <small><?= htmlspecialchars(substr($txn['data_plan'] ?? '', 0, 15)) ?>...</small><br>
                                    <?= htmlspecialchars($txn['phone_number'] ?? '—') ?>
                                <?php else: ?>
                                    Wallet Funding
                                <?php endif; ?>
                            </td>
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
                                <?php if ($txn['error_message']): ?>
                                    <br><small title="<?= htmlspecialchars($txn['error_message']) ?>">⚠️</small>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, H:i', strtotime($txn['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transactions)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 2rem; color: #999;">No transactions found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
            <div style="display: flex; justify-content: center; gap: 0.25rem; margin-top: 2rem;">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <a href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page'))) ?>" 
                       style="padding: 0.5rem 1rem; text-decoration: none; color: #4a5568; 
                              <?= $i == $page ? 'background: var(--primary); color: white; border-radius: 6px;' : 'background: #f1f5f9;' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>