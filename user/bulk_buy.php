<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/BulkProcessor.php';

if (!auth()->isLoggedIn() || !security()->requirePin()) {
    header("Location: dashboard.php");
    exit();
}

$user = db_find('users', 'id = ?', [$_SESSION['user_id']]);
$data_plans = json_decode(setting('data_plan_prices', '{}'), true) ?: [];
$message = '';
$error = '';
$job_id = '';
$uploaded = false;

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv'])) {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "No file uploaded.";
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) {
            $error = "Failed to read CSV file.";
        } else {
            $bundles = [];
            $header = fgetcsv($handle); // Skip header
            $max_limit = (int)setting('max_bulk_limit', 50);
            $row_num = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $row_num++;
                if (count($bundles) >= $max_limit) {
                    $error = "CSV exceeds maximum limit of {$max_limit} bundles.";
                    fclose($handle);
                    break;
                }

                // Expect: phone,plan_id (e.g., "08123456789,ME2U_NG_Data2Share_2051")
                $phone = trim($row[0] ?? '');
                $plan_id = trim($row[1] ?? '');

                if (empty($phone) || empty($plan_id) || !isset($data_plans[$plan_id])) {
                    $error = "Invalid data in row {$row_num}. Format: phone,plan_id";
                    fclose($handle);
                    break;
                }

                // Normalize phone
                $phone = preg_replace('/[^0-9]/', '', $phone);
                if (strlen($phone) === 10 && substr($phone, 0, 1) === '8') {
                    $phone = '0' . $phone;
                }
                if (!preg_match('/^0(70|80|81|90|91)\d{8}$/', $phone)) {
                    $error = "Invalid phone in row {$row_num}: {$row[0]}";
                    fclose($handle);
                    break;
                }

                $bundles[] = [
                    'phone' => $phone,
                    'plan' => $plan_id,
                    'network' => 'mtn'
                ];
            }
            fclose($handle);

            if (!$error && !empty($bundles)) {
                // Calculate total cost
                $total_cost = 0;
                foreach ($bundles as $b) {
                    $total_cost += $data_plans[$b['plan']] ?? 0;
                }

                if ($user['wallet_balance'] < $total_cost) {
                    $error = "Insufficient balance. Total: ₦" . number_format($total_cost, 2);
                } else {
                    // Enqueue job
                    $processor = new BulkProcessor();
                    $job_id = $processor->enqueueBulk($user['id'], $bundles);
                    $uploaded = true;
                    $message = "✅ Bulk job queued! ID: " . htmlspecialchars($job_id) . ". Processing will start shortly.";
                }
            }
        }
    }
}
?>

<!-- Same head/styles as dashboard.php -->
<body>
    <div class="container">
        <header class="header">
            <a href="dashboard.php" style="color: var(--dark); text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
            </div>
        </header>

        <div style="max-width: 800px; margin: 2rem auto;">
            <div class="balance-card" style="background: linear-gradient(135deg, #4361ee, #3a0ca3);">
                <div class="balance-label">AVAILABLE BALANCE</div>
                <div class="balance-amount">₦<?= number_format($user['wallet_balance'], 2) ?></div>
            </div>

            <?php if ($message): ?>
                <div class="alert" style="background: #e8f5e9; color: #2e7d32; text-align: center; padding: 1.5rem; border-radius: 16px; margin: 1.5rem 0;">
                    <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i><br>
                    <?= $message ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert" style="background: #ffebee; color: #c62828; text-align: center; padding: 1.5rem; border-radius: 16px; margin: 1.5rem 0;">
                    <i class="fas fa-times-circle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i><br>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="transactions" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; text-align: center;">Bulk Data Purchase</h2>
                
                <?php if (!$uploaded): ?>
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div style="background: #fff8e6; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem;">
                        <h3 style="color: #e69100; margin: 0 0 1rem 0;"><i class="fas fa-info-circle"></i> CSV Format</h3>
                        <p style="font-family: monospace; background: white; padding: 1rem; border-radius: 8px; text-align: left;">
                            phone,plan_id<br>
                            08123456789,ME2U_NG_Data2Share_2051<br>
                            09012345678,ME2U_NG_Data2Share_1622<br>
                            ...
                        </p>
                        <p style="margin-top: 1rem; color: #666;">
                            Max: <strong><?= setting('max_bulk_limit', 50) ?> bundles</strong> | Supported networks: <strong>MTN</strong>
                        </p>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="upload_csv" value="1">
                        
                        <div class="form-group">
                            <label>Upload CSV File</label>
                            <input type="file" name="csv_file" accept=".csv" required 
                                   style="width: 100%; padding: 1rem; border: 2px dashed #cbd5e0; border-radius: 12px; text-align: center;">
                        </div>

                        <div class="form-group">
                            <label>Select Default Plan (for manual entry)</label>
                            <select name="default_plan" style="width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px;">
                                <?php foreach ($data_plans as $id => $price): 
                                    $size = '1GB';
                                    if (strpos($id, '1621') !== false) $size = '1GB';
                                    elseif (strpos($id, '1622') !== false) $size = '2GB';
                                    elseif (strpos($id, '1623') !== false) $size = '3GB';
                                    elseif (strpos($id, '2051') !== false) $size = '5GB';
                                ?>
                                <option value="<?= $id ?>"><?= $size ?> - ₦<?= number_format($price, 2) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn" style="background: #06d6a0; width: 100%; padding: 1rem; font-size: 1.1rem; margin-top: 1.5rem;">
                            <i class="fas fa-upload"></i> Upload & Process
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">⏳</div>
                    <h3>Bulk Job Submitted</h3>
                    <p style="color: #666; margin: 1rem 0;">Your job ID: <strong><?= htmlspecialchars($job_id) ?></strong></p>
                    <p>Check <a href="history.php" style="color: var(--primary);">Transaction History</a> for real-time progress.</p>
                    <button onclick="location.href='dashboard.php'" class="btn" style="margin-top: 1.5rem;">
                        <i class="fas fa-home"></i> Back to Dashboard
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>