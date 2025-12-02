<?php
// includes/BulkProcessor.php

require_once __DIR__ . '/api_client.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db.php';

class BulkProcessor {
    private $pdo;
    private $prefix;
    private $queue_file;

    public function __construct() {
        $this->pdo = getDB();
        $this->prefix = setting('database.prefix', 'vtu_');
        $this->queue_file = __DIR__ . '/../storage/bulk_queue.json';
    }

    /**
     * Add bulk job to queue
     * @param int $user_id
     * @param array $bundles [ ['phone' => '081...', 'plan' => 'ME2U_...', 'network' => 'mtn'], ... ]
     */
    public function enqueueBulk(int $user_id, array $bundles): string {
        $job_id = 'BULK_' . uniqid();
        
        $job = [
            'job_id' => $job_id,
            'user_id' => $user_id,
            'created_at' => date('c'),
            'status' => 'queued',
            'total' => count($bundles),
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'bundles' => array_values($bundles)
        ];

        // Save to queue file (simple file-based queue)
        $queue = $this->getQueue();
        $queue[] = $job;
        file_put_contents($this->queue_file, json_encode($queue, JSON_PRETTY_PRINT));

        return $job_id;
    }

    /**
     * Process next job in queue (run via cron every 30s)
     */
    public function processNext(): void {
        $queue = $this->getQueue();
        if (empty($queue)) return;

        $job = array_shift($queue); // Get first job
        $this->saveQueue($queue); // Update queue immediately

        try {
            $this->processJob($job);
        } catch (Exception $e) {
            error_log("BulkProcessor error: " . $e->getMessage());
            // Re-queue on critical error
            $queue = $this->getQueue();
            array_unshift($queue, $job);
            $this->saveQueue($queue);
        }
    }

    private function processJob(array &$job): void {
        $job['status'] = 'processing';
        $this->updateJobStatus($job);

        foreach ($job['bundles'] as $i => $bundle) {
            // Check for duplicate *before* sending
            $ref = $job['job_id'] . '_B' . ($i + 1);
            
            if (security()->isDuplicateTransaction($ref, $bundle['phone'], $bundle['plan'])) {
                $job['failed']++;
                $this->logTransaction($job['user_id'], $bundle, 'failed', 'Duplicate detected', $ref);
                continue;
            }

            // Send data
            $result = mtn_api()->sendData(
                $bundle['network'],
                $bundle['plan'],
                $bundle['phone'],
                $bundle['phone'],
                $ref
            );

            if ($result['success'] ?? false) {
                $job['success']++;
                $job['processed']++;
                $this->logTransaction($job['user_id'], $bundle, 'success', '', $ref, $result['data']['amount_charged'] ?? null);
            } else {
                $job['failed']++;
                $job['processed']++;
                $error = $result['error'] ?? 'Unknown error';
                $this->logTransaction($job['user_id'], $bundle, 'failed', $error, $ref);
                
                // Notify admin & user on failure
                $this->notifyFailure($job['user_id'], $bundle, $error);
            }

            // Update progress
            $this->updateJobStatus($job);

            // Delay 3-5s between requests (MTN rate limiting)
            usleep(3000000 + rand(0, 2000000)); // 3-5 seconds
        }

        $job['status'] = 'completed';
        $this->updateJobStatus($job);
    }

    private function logTransaction(int $user_id, array $bundle, string $status, string $error, string $ref, ?float $cost = null): void {
        db_insert('transactions', [
            'user_id' => $user_id,
            'type' => 'data',
            'amount' => (float)setting('data_plan_prices', [])[$bundle['plan']] ?? 0,
            'status' => $status,
            'reference' => $ref,
            'gateway' => 'bulk',
            'network' => $bundle['network'],
            'data_plan' => $bundle['plan'],
            'phone_number' => $bundle['phone'],
            'error_message' => $error,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Optional: Adjust selling price if cost changed significantly
        if ($status === 'success' && $cost !== null) {
            $current_sell = (float)setting('data_plan_prices', [])[$bundle['plan']] ?? 0;
            $margin = $current_sell - $cost;
            if ($margin < 300) { // Alert if margin too low
                error_log("⚠️ Low margin on {$bundle['plan']}: Sell ₦{$current_sell}, Cost ₦{$cost}");
            }
        }
    }

    private function notifyFailure(int $user_id, array $bundle, string $error): void {
        // In real app: send email/SMS
        error_log("🔔 FAILED DATA: User {$user_id}, {$bundle['phone']}, {$bundle['plan']} — {$error}");
    }

    private function getQueue(): array {
        if (!file_exists($this->queue_file)) {
            return [];
        }
        $data = json_decode(file_get_contents($this->queue_file), true);
        return is_array($data) ? $data : [];
    }

    private function saveQueue(array $queue): void {
        @mkdir(dirname($this->queue_file), 0755, true);
        file_put_contents($this->queue_file, json_encode($queue, JSON_PRETTY_PRINT));
    }

    private function updateJobStatus(array $job): void {
        // Save progress to DB (for admin monitoring)
        db_update('bulk_jobs', [
            'status' => $job['status'],
            'processed' => $job['processed'],
            'success' => $job['success'],
            'failed' => $job['failed'],
            'updated_at' => date('Y-m-d H:i:s')
        ], 'job_id = ?', [$job['job_id']]);
    }
}