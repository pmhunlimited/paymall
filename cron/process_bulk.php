<?php
// cron/process_bulk.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/BulkProcessor.php';

// Only allow CLI access
if (php_sapi_name() !== 'cli') {
    die('CLI access only');
}

try {
    $processor = new BulkProcessor();
    $processor->processNext();
    echo "[" . date('Y-m-d H:i:s') . "] Bulk job processed.\n";
} catch (Exception $e) {
    error_log("CRON ERROR: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>