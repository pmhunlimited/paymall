<?php
// logout.php
session_start();
require_once __DIR__ . '/includes/auth.php';

// Restore admin session if impersonating
if ($_SESSION['impersonating'] ?? false) {
    $_SESSION['user_id'] = $_SESSION['original_user_id'];
    $_SESSION['username'] = $_SESSION['original_username'];
    $_SESSION['is_admin'] = $_SESSION['original_is_admin'];
    unset($_SESSION['impersonating']);
    unset($_SESSION['original_user_id']);
    unset($_SESSION['original_username']);
    unset($_SESSION['original_is_admin']);
    header("Location: admin/index.php");
    exit();
}

auth()->logout();
header("Location: user/login.php");
exit();
?>