<?php
// user/register.php

// These must be included first in this order.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/functions.php';

if (auth()->isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');

    $result = auth()->register($username, $email, $password, $phone);
    if ($result['success']) {
        $success = "Account created successfully! Default PIN is <strong>0000</strong> — change it after login.";
    } else {
        $error = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — <?= htmlspecialchars(setting('site_name', 'VTU Fintech')) ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #4361ee, #3a0ca3); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .login-card { background: white; border-radius: 20px; box-shadow: 0 15px 50px rgba(0,0,0,0.2); width: 100%; max-width: 450px; overflow: hidden; }
        .card-header { background: #2b2d42; color: white; padding: 2rem; text-align: center; }
        .card-header h1 { font-weight: 700; font-size: 1.8rem; margin-bottom: 0.5rem; }
        .logo { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .card-body { padding: 2.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; font-weight: 500; margin-bottom: 0.5rem; color: #4a5568; }
        input { width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1.05rem; transition: all 0.3s; }
        input:focus { outline: none; border-color: #4361ee; box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.2); }
        .btn { background: #4361ee; color: white; border: none; padding: 1rem; font-size: 1.1rem; border-radius: 12px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 0.5rem; transition: all 0.3s; }
        .btn:hover { background: #3a56e4; transform: translateY(-2px); }
        .links { text-align: center; margin-top: 1.5rem; font-size: 0.95rem; }
        .links a { color: #4361ee; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
        .alert { padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; text-align: center; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="card-header">
            <div class="logo">⚡</div>
            <h1>Create Account</h1>
            <p>Get started with data bundles in seconds</p>
        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert" style="background: #e8f5e9; color: #2e7d32;">
                    <strong>✅ Success!</strong> <?= $success ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert" style="background: #fee2e2; color: #b91c1c;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number (optional)</label>
                    <input type="tel" id="phone" name="phone" placeholder="08123456789">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" minlength="8" required>
                    <small style="color: #666; font-size: 0.85rem;">At least 8 characters</small>
                </div>
                <button type="submit" class="btn">Create Account</button>

                <div class="links">
                    <a href="login.php">Already have an account? Sign In</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
