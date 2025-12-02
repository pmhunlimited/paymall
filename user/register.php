<?php
session_start();
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

<!-- Same head/styles as login.php -->
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
                <div class="alert"><?= htmlspecialchars($error) ?></div>
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
                    <a href="login.php">Already have an account?</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>