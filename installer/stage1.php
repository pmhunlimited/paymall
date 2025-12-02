<?php
// installer/stage1.php
session_start();

$required_php_version = '7.4.0';
$required_extensions = ['mysqli', 'curl', 'json', 'openssl', 'mbstring'];

$errors = [];

if (version_compare(PHP_VERSION, $required_php_version, '<')) {
    $errors[] = "PHP version {$required_php_version} or higher is required. You are running " . PHP_VERSION;
}

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "PHP extension '{$ext}' is not loaded.";
    }
}

if (empty($errors)) {
    // All checks passed, proceed to stage 2
    header("Location: stage2.php");
    exit();
} else {
    // Display errors
    echo "<h1>Installation Requirements Not Met</h1>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>{$error}</li>";
    }
    echo "</ul>";
    echo "<p>Please install the required PHP version and extensions before continuing.</p>";
}
?>