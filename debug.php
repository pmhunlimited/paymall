<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test database connection
try {
    $pdo = new PDO('mysql:host=localhost', 'cpaneluser_dbuser', 'password');
    echo "✅ Database connection works";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage();
}

// Test extensions
$required = ['pdo_mysql', 'curl', 'json', 'openssl', 'mbstring'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "<br>✅ $ext loaded";
    } else {
        echo "<br>❌ $ext missing";
    }
}
?>