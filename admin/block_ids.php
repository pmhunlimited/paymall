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

// Handle block submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_id'])) {
    $identifier = trim($_POST['identifier'] ?? '');
    $reason = trim($_POST['reason'] ?? 'No reason given');
    $type = $_POST['type'] ?? 'phone'; // 'phone' or 'smartcard'

    if (empty($identifier)) {
        $error = "Identifier is required.";
    } else {
        // Normalize phone numbers (remove +, spaces, dashes)
        if ($type === 'phone') {
            $identifier = preg_replace('/[^0-9]/', '', $identifier);
            if (strlen($identifier) !== 11 || !in_array(substr($identifier, 0, 3), ['070', '080', '081', '090', '091'])) {
                $error = "Invalid Nigerian phone number format (e.g., 08123456789).";
            }
        }

        if (!$error) {
            try {
                db_insert('blocked_ids', [
                    'identifier' => $identifier,
                    'reason' => $reason,
                    'blocked_by' => $_SESSION['user_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $message = "'" . htmlspecialchars($identifier) . "' blocked successfully.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Duplicate
                    $error = "Identifier '" . htmlspecialchars($identifier) . "' is already blocked.";
                } else {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle unblock
if (isset($_GET['unblock'])) {
    $id = (int)$_GET['unblock'];
    db_update('blocked_ids', ['created_at' => date('Y-m-d H:i:s')], 'id = ? AND blocked_by = ?', [$id, $_SESSION['user_id']]);
    $pdo->exec("DELETE FROM `{$prefix}blocked_ids` WHERE id = {$id}");
    header("Location: block_ids.php?unblocked=1");
    exit();
}

// Fetch blocked IDs
$blocked = db_select('blocked_ids', '', [], 'created_at DESC', 20);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Block IDs — Admin</title>
    <!-- Reuse same head/styles -->
    <style>
        /* ... same CSS as credit.php ... */
        .blocked-card { background: #fff5f5; border-left: 4px solid var(--danger); border-radius: 12px; padding: 1rem; margin-bottom: 0.75rem; }
        .blocked-card h4 { color: var(--danger); margin: 0 0 0.25rem 0; }
        .unblock-btn { background: #ef476f; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.85rem; cursor: pointer; }
        .unblock-btn:hover { background: #d6305f; }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">…</aside>
        <main class="main">
            <header class="header">
                <h1><i class="fas fa-ban"></i> Block IDs</h1>
                …
            </header>

            <div class="content">
                <?php if ($_GET['unblocked'] ?? false): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> ID unblocked successfully.
                    </div>
                <?php endif; ?>
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
                    <h2>🚫 Block New Identifier</h2>
                    <form method="POST">
                        <input type="hidden" name="block_id" value="1">

                        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                            <div class="form-group">
                                <label for="type">ID Type</label>
                                <select id="type" name="type" onchange="updatePlaceholder()">
                                    <option value="phone">Phone Number</option>
                                    <option value="smartcard">Smart Card ID</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="identifier">Identifier</label>
                                <input type="text" id="identifier" name="identifier" 
                                       placeholder="e.g., 08123456789" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reason">Reason</label>
                            <input type="text" id="reason" name="reason" 
                                   placeholder="e.g., Fraud, Chargeback, Spam" maxlength="100" required>
                        </div>

                        <button type="submit" class="btn">
                            <i class="fas fa-ban"></i> Block Identifier
                        </button>
                    </form>
                </div>

                <div class="section">
                    <h2>🔒 Currently Blocked IDs (<?= count($blocked) ?>)</h2>
                    <?php if (empty($blocked)): ?>
                        <p style="color: #666; text-align: center; padding: 1.5rem;">No IDs blocked yet.</p>
                    <?php else: ?>
                        <?php foreach ($blocked as $b): ?>
                            <div class="blocked-card">
                                <h4><?= htmlspecialchars($b['identifier']) ?></h4>
                                <p><strong>Reason:</strong> <?= htmlspecialchars($b['reason']) ?></p>
                                <p><small>Blocked by admin #<?= $b['blocked_by'] ?> on <?= date('M d, Y', strtotime($b['created_at'])) ?></small></p>
                                <a href="?unblock=<?= $b['id'] ?>" 
                                   onclick="return confirm('Unblock <?= addslashes($b['identifier']) ?>?')" 
                                   class="unblock-btn">
                                    <i class="fas fa-unlock"></i> Unblock
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
    function updatePlaceholder() {
        const type = document.getElementById('type').value;
        const input = document.getElementById('identifier');
        input.placeholder = type === 'phone' ? 'e.g., 08123456789' : 'e.g., SC123456789';
    }
    </script>
</body>
</html>