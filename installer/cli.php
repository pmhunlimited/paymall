#!/usr/bin/env php
<?php
// installer/cli.php

echo "\n🚀 VTU Platform — CLI Installer\n";
echo "================================\n\n";

// Check PHP version & extensions
$required_php = '7.4.0';
if (version_compare(PHP_VERSION, $required_php, '<')) {
    die("❌ PHP {$required_php}+ required. You have " . PHP_VERSION . "\n");
}

$required_exts = ['pdo_mysql', 'curl', 'json', 'openssl', 'mbstring'];
$missing = [];
foreach ($required_exts as $ext) {
    if (!extension_loaded($ext)) $missing[] = $ext;
}
if ($missing) {
    die("❌ Missing PHP extensions: " . implode(', ', $missing) . "\n");
}
echo "✅ PHP requirements met.\n";

// Get DB config
echo "\n🔧 Database Configuration:\n";
$db_host = readline("Host [localhost]: ") ?: 'localhost';
$db_name = readline("Database name: ");
$db_user = readline("Username: ");
$db_pass = getenv('INSTALLER_DB_PASS') ?: readline("Password (input hidden): ");
if (!$db_pass) {
    echo "⚠️ No password provided.\n";
}

$prefix = readline("Table prefix [vtu_]: ") ?: 'vtu_';

// Test connection
try {
    $pdo = new PDO("mysql:host={$db_host};charset=utf8mb4", $db_user, $db_pass);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$db_name}`");
    echo "✅ Database connection successful.\n";
} catch (Exception $e) {
    die("❌ DB Error: " . $e->getMessage() . "\n");
}

// Install schema
$schema = file_get_contents(__DIR__ . '/schema.sql');
$schema = str_replace('{PREFIX}', $prefix, $schema);
$pdo->exec($schema);
echo "✅ Schema installed.\n";

// Create admin
echo "\n👤 Admin Account:\n";
$username = readline("Admin username: ");
$email = readline("Admin email: ");
$password = readline("Password (min 8 chars): ");
$pin = readline("Security PIN (4 digits): ");

if (strlen($password) < 8) die("❌ Password too short.\n");
if (!preg_match('/^\d{4}$/', $pin)) die("❌ PIN must be 4 digits.\n");

$password_hash = password_hash($password, PASSWORD_ARGON2ID);
$pin_hash = password_hash($pin, PASSWORD_ARGON2ID);

$stmt = $pdo->prepare("
    INSERT INTO `{$prefix}users` 
    (`username`, `email`, `password_hash`, `security_pin_hash`, `is_admin`, `is_active`) 
    VALUES (?, ?, ?, ?, 1, 1)
");
$stmt->execute([$username, $email, $password_hash, $pin_hash]);

// Create config
$config = "<?php\nreturn [\n";
$config .= "    'database' => [\n";
$config .= "        'host'     => '" . addslashes($db_host) . "',\n";
$config .= "        'name'     => '" . addslashes($db_name) . "',\n";
$config .= "        'user'     => '" . addslashes($db_user) . "',\n";
$config .= "        'pass'     => '" . addslashes($db_pass) . "',\n";
$config .= "        'prefix'   => '" . addslashes($prefix) . "',\n";
$config .= "        'charset'  => 'utf8mb4',\n";
$config .= "    ],\n";
$config .= "    'installed' => true,\n";
$config .= "    'install_date' => '" . date('Y-m-d H:i:s') . "',\n";
$config .= "];\n";

file_put_contents(__DIR__ . '/../config/app.php', $config);
echo "✅ Config file generated.\n";

// Finalize
file_put_contents(__DIR__ . '/.installed', time());
echo "\n🎉 Installation complete!\n";
echo "   - Admin: {$username} / {$email}\n";
echo "   - Config: ../config/app.php\n";
echo "   - 🔒 Delete /installer/ folder for security.\n\n";
?>