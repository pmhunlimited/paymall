<?php
// installer/index.php
session_start();

// Define paths
$install_lock_file = __DIR__ . '/.installed';
$config_file = __DIR__ . '/../config/app.php';

// Check if already installed
if (file_exists($install_lock_file) || (file_exists($config_file) && is_array(@include $config_file) && (@include $config_file)['installed'] ?? false)) {
    die('<!DOCTYPE html>
    <html>
    <head><title>Already Installed</title>
        <style>body{font-family:sans-serif;text-align:center;padding:5rem;background:#f8f9fa;} 
        .box{background:white;padding:2rem;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.1);max-width:500px;margin:0 auto;}
        .error{color:#e53e3e;font-weight:bold;}</style>
    </head>
    <body>
        <div class="box">
            <h2>❌ Installation Already Completed</h2>
            <p class="error">The system is already installed.</p>
            <p>To reinstall:</p>
            <ol style="text-align:left;margin:1.5rem auto;max-width:400px;">
                <li>Delete <code>/installer/.installed</code></li>
                <li>Delete or rename <code>/config/app.php</code></li>
                <li>Refresh this page</li>
            </ol>
            <a href="../index.php" style="display:inline-block;margin-top:1rem;background:#4361ee;color:white;padding:0.75rem 1.5rem;text-decoration:none;border-radius:6px;">Go to Homepage</a>
        </div>
    </body>
    </html>');
}

// Determine current stage
$current_stage = 1;
if (isset($_SESSION['stage1_passed']) && $_SESSION['stage1_passed']) $current_stage = 2;
if (isset($_SESSION['stage2_passed']) && $_SESSION['stage2_passed']) $current_stage = 3;
if (isset($_SESSION['stage3_passed']) && $_SESSION['stage3_passed']) $current_stage = 4;

// Redirect to appropriate stage
switch ($current_stage) {
    case 1:
        header("Location: stage1.php");
        break;
    case 2:
        header("Location: stage2.php");
        break;
    case 3:
        header("Location: stage3.php");
        break;
    case 4:
        header("Location: stage4.php");
        break;
    default:
        header("Location: stage1.php");
}
exit();
?>