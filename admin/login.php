<?php
// admin/login.php

// These must be included first in this order.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in as admin
if (auth()->isLoggedIn() && ($_SESSION['is_admin'] ?? false)) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        $result = auth()->login($username, $password);
        if ($result['success'] && ($_SESSION['is_admin'] ?? false)) {
            header("Location: index.php");
            exit();
        } else {
            // Provide a generic error to avoid user enumeration
            $error = "Invalid credentials or not an administrator.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= htmlspecialchars(setting('site_name', 'VTU Fintech')) ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #4361ee, #3a0ca3); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .login-card { background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 100%; max-width: 420px; overflow: hidden; }
        .card-header { background: #2b2d42; color: white; padding: 1.8rem 2rem; text-align: center; }
        .card-header h1 { font-weight: 600; font-size: 1.6rem; }
        .card-body { padding: 2rem; }
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; font-size: 0.9rem; font-weight: 500; margin-bottom: 0.5rem; color: #4a5568; }
        input { width: 100%; padding: 0.85rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1rem; transition: border-color 0.2s; }
        input:focus { outline: none; border-color: #4361ee; box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2); }
        .btn { background: #4361ee; color: white; border: none; padding: 0.9rem; font-size: 1rem; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 0.5rem; }
        .btn:hover { background: #3a56e4; }
        .alert { background: #fee2e2; color: #b91c1c; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.95rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="card-header">
            <h1>🔐 Admin Login</h1>
            <p>Secure access to VTU dashboard</p>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>
