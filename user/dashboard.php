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

// Get user data
$user = db_find('users', 'id = ?', [$_SESSION['user_id']]);
if (!$user) {
    auth()->logout();
    header("Location: login.php");
    exit();
}

// Get recent transactions
$recent_txns = db_select('transactions', 'user_id = ?', [$_SESSION['user_id']], 'created_at DESC', 5);

// Check if PIN verified (for sensitive actions)
$pin_verified = $_SESSION['pin_verified'] ?? false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= htmlspecialchars($user['username']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4361ee; --success: #06d6a0; --warning: #ffd166; --danger: #ef476f; --dark: #2b2d42; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; padding: 1.5rem; }
        
        /* Header */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .logo { font-size: 1.8rem; font-weight: 700; }
        .logo i { color: var(--primary); }
        .user-info { text-align: right; }
        .user-name { font-weight: 600; font-size: 1.1rem; }
        .logout-btn { background: none; border: none; color: #6c757d; cursor: pointer; font-size: 0.9rem; }
        .logout-btn:hover { color: var(--danger); }
        
        /* Balance Cards */
        .balance-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .balance-card { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; border-radius: 20px; padding: 1.75rem; text-align: center; box-shadow: 0 10px 25px rgba(67, 97, 238, 0.3); }
        .balance-card.bonus { background: linear-gradient(135deg, #06d6a0, #05a87d); }
        .balance-label { font-size: 0.95rem; opacity: 0.9; margin-bottom: 0.5rem; }
        .balance-amount { font-size: 2.25rem; font-weight: 700; margin: 0.25rem 0; }
        .balance-actions { margin-top: 1rem; }
        .balance-actions button { background: rgba(255,255,255,0.2); border: none; color: white; padding: 0.5rem 1rem; border-radius: 10px; cursor: pointer; font-weight: 500; }
        .balance-actions button:hover { background: rgba(255,255,255,0.3); }
        
        /* Quick Actions */
        .section-header { display: flex; justify-content: space-between; align-items: center; margin: 2rem 0 1.25rem; }
        .section-header h2 { font-weight: 600; color: var(--dark); }
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
        .quick-btn { background: white; border-radius: 16px; padding: 1.25rem; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: all 0.3s; }
        .quick-btn:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .quick-btn i { font-size: 2rem; color: var(--primary); margin-bottom: 0.75rem; }
        .quick-btn h3 { font-weight: 600; margin-bottom: 0.25rem; color: var(--dark); }
        .quick-btn p { color: #666; font-size: 0.9rem; }
        
        /* Recent Transactions */
        .transactions { background: white; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.75rem; border-bottom: 1px solid #eee; }
        th { color: #555; font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        .status { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500; }
        .status-success { background: #e8f5e9; color: #2e7d32; }
        .status-pending { background: #fff8e6; color: #e69100; }
        .status-failed { background: #ffebee; color: #c62828; }
        
        /* PIN Verification Modal */
        #pinModal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .pin-content { background: white; border-radius: 20px; padding: 2rem; width: 90%; max-width: 400px; text-align: center; }
        .pin-input { width: 80%; padding: 1rem; font-size: 1.5rem; text-align: center; border: 2px solid #e2e8f0; border-radius: 12px; margin: 1rem auto; }
        .pin-buttons { display: flex; gap: 0.5rem; justify-content: center; margin-top: 1rem; }
        .pin-btn { flex: 1; padding: 0.75rem; border-radius: 10px; font-weight: 500; cursor: pointer; }
        .pin-btn.cancel { background: #6c757d; color: white; }
        .pin-btn.verify { background: var(--primary); color: white; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <i class="fas fa-bolt"></i> <?= htmlspecialchars(setting('site_name', 'VTU Fintech')) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                <form method="POST" action="../logout.php" style="display: inline;">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
                <?php if ($_SESSION['impersonating'] ?? false): ?>
                    <div style="color: #ef476f; font-size: 0.85rem; margin-top: 0.25rem;">
                        <i class="fas fa-user-secret"></i> Admin impersonation
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <!-- Balance Cards -->
        <div class="balance-cards">
            <div class="balance-card">
                <div class="balance-label">WALLET BALANCE</div>
                <div class="balance-amount">₦<?= number_format($user['wallet_balance'], 2) ?></div>
                <div class="balance-actions">
                    <button onclick="showPinModal('fund')">
                        <i class="fas fa-plus"></i> Fund Wallet
                    </button>
                </div>
            </div>
            <div class="balance-card bonus">
                <div class="balance-label">BONUS BALANCE</div>
                <div class="balance-amount">₦<?= number_format($user['bonus_balance'], 2) ?></div>
                <div class="balance-actions">
                    <button disabled style="opacity: 0.6;">
                        <i class="fas fa-gift"></i> Promo Only
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-header">
            <h2>⚡ Quick Actions</h2>
            <a href="history.php" style="color: var(--primary); text-decoration: none;">View All →</a>
        </div>
        <div class="quick-actions">
            <div class="quick-btn" onclick="showPinModal('data')">
                <i class="fas fa-mobile-alt"></i>
                <h3>Buy Data</h3>
                <p>Single bundle</p>
            </div>
            <div class="quick-btn" onclick="showPinModal('bulk')">
                <i class="fas fa-box"></i>
                <h3>Bulk Data</h3>
                <p>Multiple numbers</p>
            </div>
            <div class="quick-btn" onclick="location.href='profile.php'">
                <i class="fas fa-user-cog"></i>
                <h3>Account</h3>
                <p>PIN, Password</p>
            </div>
            <div class="quick-btn" onclick="location.href='history.php'">
                <i class="fas fa-history"></i>
                <h3>History</h3>
                <p>All transactions</p>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="section-header">
            <h2>📊 Recent Transactions</h2>
        </div>
        <div class="transactions">
            <table>
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_txns as $txn): ?>
                    <tr>
                        <td><?= htmlspecialchars(substr($txn['reference'], 0, 8)) ?>...</td>
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
                    <tr><td colspan="5" style="text-align: center; padding: 2rem; color: #999;">No transactions yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PIN Verification Modal -->
    <div id="pinModal">
        <div class="pin-content">
            <h2 style="margin: 0 0 1rem 0;"><i class="fas fa-key"></i> Security PIN</h2>
            <p>Enter your 4-digit PIN to continue</p>
            <input type="password" id="pinInput" class="pin-input" maxlength="4" inputmode="numeric" 
                   placeholder="••••" autocomplete="off">
            <div class="pin-buttons">
                <button class="pin-btn cancel" onclick="hidePinModal()">Cancel</button>
                <button class="pin-btn verify" onclick="verifyPin()">Verify</button>
            </div>
        </div>
    </div>

    <script>
    let actionType = '';

    function showPinModal(type) {
        actionType = type;
        document.getElementById('pinModal').style.display = 'flex';
        document.getElementById('pinInput').focus();
    }

    function hidePinModal() {
        document.getElementById('pinModal').style.display = 'none';
        document.getElementById('pinInput').value = '';
    }

    function verifyPin() {
        const pin = document.getElementById('pinInput').value;
        if (pin.length !== 4) {
            alert('PIN must be 4 digits');
            return;
        }

        // AJAX verify
        fetch('verify_pin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `pin=${encodeURIComponent(pin)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                hidePinModal();
                if (actionType === 'fund') {
                    location.href = 'fund_wallet.php';
                } else if (actionType === 'data') {
                    location.href = 'buy_data.php';
                } else if (actionType === 'bulk') {
                    location.href = 'bulk_buy.php';
                }
            } else {
                alert('Invalid PIN. Please try again.');
                document.getElementById('pinInput').value = '';
                document.getElementById('pinInput').focus();
            }
        })
        .catch(err => {
            alert('Error verifying PIN. Try again.');
        });
    }

    // Auto-close modal on Esc
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') hidePinModal();
    });
    </script>
</body>
</html>