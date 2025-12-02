#!/usr/bin/env php
<?php
// post_install_check.php
// Run after installation to verify security and permissions

echo "\n🔍 VTU Platform — Post-Installation Security Check\n";
echo str_repeat("=", 55) . "\n";

$checks = [];
$errors = 0;
$warnings = 0;

// === 1. File System Checks ===
echo "\n📁 Directory & File Checks:\n";

$required_dirs = [
    'config' => 0750,
    'logs' => 0755,
    'storage' => 0755,
    'uploads' => 0755,
    'assets' => 0755,
    'webhooks' => 0750
];

foreach ($required_dirs as $dir => $expected_perms) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        $checks[] = ["❌ MISSING", "Directory {$dir}/ not found"];
        $errors++;
    } else {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $expected_str = sprintf('%04o', $expected_perms);
        
        if ($perms == $expected_str || $perms == '0775') { // Allow 775 for shared hosting
            $checks[] = ["✅ OK", "{$dir}/ ({$perms})"];
        } else {
            $checks[] = ["⚠️ WARN", "{$dir}/ has {$perms} (expected {$expected_str})"];
            $warnings++;
        }
    }
}

// === 2. Config File Checks ===
echo "\n🔐 Configuration Checks:\n";

$config_files = [
    'config/app.php' => [
        'exists' => true,
        'writable' => false,
        'contains_installed' => true
    ],
    '.env' => [
        'exists' => false, // Optional
        'writable' => false,
        'secure_perms' => true
    ]
];

foreach ($config_files as $file => $rules) {
    $path = __DIR__ . '/' . $file;
    
    if ($rules['exists'] && !file_exists($path)) {
        $checks[] = ["❌ MISSING", "{$file} not found"];
        $errors++;
        continue;
    }
    
    if (file_exists($path)) {
        // Check permissions
        if ($rules['writable'] === false) {
            $perms = substr(sprintf('%o', fileperms($path)), -4);
            if ($perms[3] !== '4' && $perms[3] !== '0') { // Not read-only for others
                $checks[] = ["⚠️ WARN", "{$file} has insecure permissions ({$perms})"];
                $warnings++;
            }
        }
        
        // Check content
        $content = file_get_contents($path);
        
        if ($rules['contains_installed'] ?? false) {
            if (strpos($content, "'installed' => true") === false) {
                $checks[] = ["❌ ERROR", "{$file} not properly configured (missing 'installed' => true)"];
                $errors++;
            } else {
                $checks[] = ["✅ OK", "{$file} properly configured"];
            }
        }
        
        if ($rules['secure_perms'] ?? false) {
            $perms = substr(sprintf('%o', fileperms($path)), -4);
            if ($perms !== '0600' && $perms !== '0640') {
                $checks[] = ["⚠️ WARN", "{$file} should be 0600/0640 (current: {$perms})"];
                $warnings++;
            }
        }
    } else {
        if ($file === '.env') {
            $checks[] = ["ℹ️ INFO", ".env not found (optional for overrides)"];
        }
    }
}

// === 3. Web Server Security ===
echo "\n🛡️ Web Server Security:\n";

$htaccess_path = __DIR__ . '/.htaccess';
if (file_exists($htaccess_path)) {
    $content = file_get_contents($htaccess_path);
    $has_security = preg_match('/Deny from all/', $content) && 
                    preg_match('/config/', $content) &&
                    preg_match('/logs/', $content);
    
    if ($has_security) {
        $checks[] = ["✅ OK", ".htaccess has security rules"];
    } else {
        $checks[] = ["⚠️ WARN", ".htaccess missing security rules"];
        $warnings++;
    }
} else {
    $checks[] = ["❌ ERROR", ".htaccess not found - config/logs accessible!"];
    $errors++;
}

// === 4. Database Connection Test ===
echo "\n💾 Database Test:\n";

try {
    if (file_exists(__DIR__ . '/config/app.php')) {
        $config = require __DIR__ . '/config/app.php';
        if ($config['installed'] ?? false) {
            $pdo = new PDO(
                "mysql:host={$config['database']['host']};dbname={$config['database']['name']};charset=utf8mb4",
                $config['database']['user'],
                $config['database']['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM {$config['database']['prefix']}users");
            $user_count = $stmt->fetchColumn();
            
            $checks[] = ["✅ OK", "Database connected ({$user_count} users)"];
        } else {
            $checks[] = ["❌ ERROR", "config/app.php exists but not marked as installed"];
            $errors++;
        }
    } else {
        $checks[] = ["❌ ERROR", "config/app.php not found"];
        $errors++;
    }
} catch (Exception $e) {
    $checks[] = ["❌ ERROR", "Database connection failed: " . $e->getMessage()];
    $errors++;
}

// === 5. Critical Files Check ===
echo "\n⚡ Critical Files:\n";

$critical_files = [
    'admin/login.php',
    'user/login.php',
    'webhooks/flutterwave.php',
    'webhooks/paystack.php',
    'includes/auth.php',
    'includes/security.php'
];

foreach ($critical_files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $checks[] = ["✅ OK", "{$file} exists"];
    } else {
        $checks[] = ["❌ MISSING", "{$file} not found"];
        $errors++;
    }
}

// === Report ===
echo "\n" . str_repeat("=", 55) . "\n";
echo "📋 SECURITY CHECK REPORT\n";
echo str_repeat("=", 55) . "\n";

foreach ($checks as $check) {
    echo "{$check[0]} {$check[1]}\n";
}

echo "\n" . str_repeat("=", 55) . "\n";
echo "SUMMARY: ";
if ($errors > 0) {
    echo "❌ {$errors} errors, ";
}
if ($warnings > 0) {
    echo "⚠️ {$warnings} warnings, ";
}
if ($errors === 0 && $warnings === 0) {
    echo "✅ All checks passed! ";
}
echo "Ready for production.\n";
echo str_repeat("=", 55) . "\n";

// Exit code for CI/CD
exit($errors > 0 ? 1 : 0);
?>