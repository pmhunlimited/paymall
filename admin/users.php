<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!auth()->isLoggedIn() || !($_SESSION['is_admin'] ?? false)) {
    exit('Access denied');
}

$pdo = getDB();
$prefix = setting('database.prefix', 'vtu_');

// Handle admin login-as-user
if (isset($_GET['login_as']) && is_numeric($_GET['login_as'])) {
    $target_user_id = (int)$_GET['login_as'];
    
    // Fetch user
    $user = db_find('users', 'id = ? AND is_active = 1', [$target_user_id]);
    if ($user) {
        // Save original admin session
        $_SESSION['impersonating'] = true;
        $_SESSION['original_user_id'] = $_SESSION['user_id'];
        $_SESSION['original_username'] = $_SESSION['username'];
        $_SESSION['original_is_admin'] = $_SESSION['is_admin'];
        
        // Switch to target user
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = false;
        $_SESSION['pin_verified'] = false;
        
        header("Location: ../user/dashboard.php");
        exit();
    }
}

// Handle user edit/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id && $id != $_SESSION['user_id']) { // Prevent self-lockout
        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone_number' => trim($_POST['phone_number'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'wallet_balance' => (float)($_POST['wallet_balance'] ?? 0),
            'bonus_balance' => (float)($_POST['bonus_balance'] ?? 0),
        ];
        if (db_update('users', $data, 'id = ?', [$id])) {
            $message = "User updated successfully.";
        }
    }
}

// Fetch users
$users = db_select('users', '', [], 'id DESC');
?>

<!DOCTYPE html>
<!-- Same head/sidebar structure -->
<body>
    <div class="container">
        <aside class="sidebar">…</aside>
        <main class="main">
            <header class="header"><h1>User Management</h1>…</header>
            <div class="content">
                <div class="section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h2>Users (<?= count($users) ?>)</h2>
                        <a href="credit.php" class="btn"><i class="fas fa-plus"></i> Credit User</a>
                    </div>

                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Wallet</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= $u['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td><?= htmlspecialchars($u['phone_number'] ?? '—') ?></td>
                                    <td>₦<?= number_format($u['wallet_balance'], 2) ?></td>
                                    <td>
                                        <?php if ($u['is_active']): ?>
                                            <span class="status status-success">Active</span>
                                        <?php else: ?>
                                            <span class="status status-failed">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- Edit (modal or link) -->
                                        <a href="#" onclick="editUser(<?= $u['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></a>
                                        &nbsp;
                                        <!-- Login as user (secure, no password needed) -->
                                        <a href="?login_as=<?= $u['id'] ?>" 
                                           onclick="return confirm('Log in as <?= addslashes($u['username']) ?>? You can return via user menu.')" 
                                           title="Login as user" style="color: #4361ee;">
                                            <i class="fas fa-user-secret"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Hidden Edit Modal (simplified) -->
    <div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div style="background:white; margin:10% auto; padding:2rem; width:90%; max-width:500px; border-radius:12px;">
            <h3>Edit User</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="update_user" value="1">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="edit_username" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone_number" id="edit_phone">
                </div>
                <div class="form-group">
                    <label>Wallet Balance</label>
                    <input type="number" step="0.01" name="wallet_balance" id="edit_wallet">
                </div>
                <div class="form-group">
                    <label>Bonus Balance</label>
                    <input type="number" step="0.01" name="bonus_balance" id="edit_bonus">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="edit_active" checked> Active
                    </label>
                </div>
                <button type="submit" class="btn">Save</button>
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn" style="background:#6c757d;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
    function editUser(id) {
        // In real app: fetch user via AJAX or pre-populate
        document.getElementById('edit_id').value = id;
        document.getElementById('editModal').style.display = 'block';
    }
    </script>
</body>
</html>