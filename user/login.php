<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (auth()->isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = "Username/email and password are required.";
    } else {
        $result = auth()->login($identifier, $password);
        if ($result['success']) {
            // Redirect to dashboard (or PIN if required for sensitive actions)
            header("Location: dashboard.php");
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= htmlspecialchars(setting('site_name', 'VTU Fintech')) ?></title>
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
        .links { display: flex; justify-content: space-between; margin-top: 1.5rem; font-size: 0.95rem; }
        .links a { color: #4361ee; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
        .alert { background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; text-align: center; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="card-header">
            <div class="logo">⚡</div>
            <h1>Welcome Back</h1>
            <p>Sign in to your VTU account</p>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="identifier">Username or Email</label>
                    <input type="text" id="identifier" name="identifier" 
                           value="<?= htmlspecialchars($_GET['username'] ?? '') ?>" 
                           autofocus required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn">Sign In</button>

                <div class="links">
                    <a href="register.php">Create Account</a>
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>