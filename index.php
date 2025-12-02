<?php
// index.php

require_once __DIR__ . '/config/settings.php';

// Check if installed
$config_file = __DIR__ . '/config/app.php';
if (!file_exists($config_file) || !(@include $config_file)['installed']) {
    header("Location: installer/");
    exit();
}

// Redirect based on login status
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['is_admin'] ?? false) {
        header("Location: admin/index.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit();
}

// Public landing page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(setting('site_name', 'VTU Fintech')) ?> — Instant Data Bundles</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #333; }
        .hero { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; padding: 5rem 2rem; text-align: center; }
        .hero h1 { font-weight: 700; font-size: 3rem; margin-bottom: 1rem; }
        .hero p { font-size: 1.25rem; max-width: 700px; margin: 0 auto 2rem; opacity: 0.9; }
        .cta { display: inline-flex; gap: 1rem; flex-wrap: wrap; justify-content: center; }
        .btn { display: inline-block; padding: 0.85rem 2rem; border-radius: 12px; font-weight: 600; text-decoration: none; font-size: 1.1rem; }
        .btn-primary { background: white; color: #4361ee; }
        .btn-secondary { background: rgba(255,255,255,0.2); color: white; }
        .features { max-width: 1200px; margin: 4rem auto; padding: 0 2rem; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
        .feature-card { background: white; border-radius: 16px; padding: 2rem; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .feature-icon { width: 70px; height: 70px; background: #eef2ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem; color: #4361ee; }
        footer { text-align: center; padding: 2rem; color: #6c757d; border-top: 1px solid #eee; margin-top: 3rem; }
    </style>
</head>
<body>
    <section class="hero">
        <h1>⚡ Instant Data Bundles</h1>
        <p>Buy MTN data bundles in seconds — single or bulk. Secure, reliable, and affordable.</p>
        <div class="cta">
            <a href="user/login.php" class="btn btn-primary">Sign In</a>
            <a href="user/register.php" class="btn btn-secondary">Create Account</a>
        </div>
    </section>

    <section class="features">
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🚀</div>
                <h3>Instant Delivery</h3>
                <p>Data bundles delivered to your phone in under 60 seconds.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Bulk Purchase</h3>
                <p>Upload CSV and send data to hundreds of numbers at once.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Bank-Grade Security</h3>
                <p>2-factor PIN protection and encrypted transactions.</p>
            </div>
        </div>
    </section>

    <footer>
        <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(setting('site_name', 'VTU Fintech')) ?>. All rights reserved.</p>
    </footer>
</body>
</html>