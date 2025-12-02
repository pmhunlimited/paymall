<?php
// cron/cleanup.php

if (php_sapi_name() !== 'cli') die('CLI access only');

// Delete logs older than 30 days
$log_dir = __DIR__ . '/../logs';
if (is_dir($log_dir)) {
    $files = glob($log_dir . '/*.log');
    foreach ($files as $file) {
        if (filemtime($file) < strtotime('-30 days')) {
            unlink($file);
        }
    }
}

// Clean up old temp files
$temp_dir = sys_get_temp_dir();
$pattern = $temp_dir . '/pin_attempts_*.json';
foreach (glob($pattern) as $file) {
    if (filemtime($file) < time() - 3600) { // 1 hour
        unlink($file);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Cleanup completed.\n";
?>