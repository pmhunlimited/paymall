<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!auth()->isLoggedIn() || !($_SESSION['is_admin'] ?? false)) {
    header("Location: login.php");
    exit();
}

$pdo = getDB();
$prefix = setting('database.prefix', 'vtu_');
$message = '';
$error = '';

// Handle email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $target = $_POST['target'] ?? 'all'; // 'all', 'active', 'inactive', 'custom'

    if (empty($subject) || empty($body)) {
        $error = "Subject and message body are required.";
    } else {
        // Get recipients
        $where = '1=1';
        $params = [];
        if ($target === 'active') $where = 'is_active = 1';
        if ($target === 'inactive') $where = 'is_active = 0';
        if ($target === 'custom') {
            $emails = array_filter(array_map('trim', explode(',', $_POST['custom_emails'] ?? '')));
            if (empty($emails)) {
                $error = "Custom email list is empty.";
            } else {
                $placeholders = str_repeat('?,', count($emails) - 1) . '?';
                $where = "email IN ({$placeholders})";
                $params = $emails;
            }
        }

        if (!$error) {
            $recipients = db_select('users', $where, $params, '', 1000);
            
            if (empty($recipients)) {
                $error = "No recipients found for the selected target.";
            } else {
                // In real app: use PHPMailer + SMTP
                // For now: simulate + log
                $sent = 0;
                foreach ($recipients as $user) {
                    // Simulate email send
                    $personalized = str_replace(
                        ['{name}', '{username}', '{balance}'],
                        [
                            htmlspecialchars($user['username']),
                            htmlspecialchars($user['username']),
                            number_format($user['wallet_balance'], 2)
                        ],
                        $body
                    );

                    // Log to file (replace with real mailer)
                    error_log("EMAIL SENT TO {$user['email']}: Subject: {$subject}");

                    $sent++;
                }

                $message = "Email sent to {$sent} recipients.";
            }
        }
    }
}

// Get recipient stats
$total_users = db_select('users', '', [], '', 1)[0]['COUNT(*)'] ?? 0;
$active_users = db_select('users', 'is_active = 1', [], '', 1)[0]['COUNT(*)'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Send Email — Admin</title>
    <!-- Reuse same styles -->
    <style>
        .template-btn { background: #e2e8f0; color: #4a5568; border: none; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.85rem; cursor: pointer; margin-right: 0.5rem; margin-bottom: 0.5rem; }
        .template-btn:hover { background: #cbd5e0; }
        textarea { height: 200px; font-family: 'Inter', monospace; }
        .recipient-stats { background: #f0fdf4; border-radius: 10px; padding: 1rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">…</aside>
        <main class="main">
            <header class="header">
                <h1><i class="fas fa-envelope"></i> Send Email to Users</h1>
                …
            </header>

            <div class="content">
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="section">
                    <h2>📩 Compose Message</h2>

                    <div class="recipient-stats">
                        <strong>Recipient Stats:</strong> 
                        Total: <?= $total_users ?> users | 
                        Active: <?= $active_users ?> | 
                        Inactive: <?= $total_users - $active_users ?>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="send_email" value="1">

                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" 
                                   placeholder="e.g., System Maintenance Notice" required>
                        </div>

                        <div class="form-group">
                            <label>
                                Recipients
                                <span style="font-size: 0.85rem; color: #666; margin-left: 0.5rem;">
                                    (Use {name}, {username}, {balance} in body)
                                </span>
                            </label>
                            <div style="margin: 0.5rem 0;">
                                <label class="checkbox-group">
                                    <input type="radio" name="target" value="all" checked> All Users (<?= $total_users ?>)
                                </label>
                            </div>
                            <div style="margin: 0.5rem 0;">
                                <label class="checkbox-group">
                                    <input type="radio" name="target" value="active"> Active Users (<?= $active_users ?>)
                                </label>
                            </div>
                            <div style="margin: 0.5rem 0;">
                                <label class="checkbox-group">
                                    <input type="radio" name="target" value="inactive"> Inactive Users (<?= $total_users - $active_users ?>)
                                </label>
                            </div>
                            <div style="margin: 0.5rem 0;">
                                <label class="checkbox-group">
                                    <input type="radio" name="target" value="custom" id="custom_target"> Custom List
                                </label>
                                <div id="custom_emails_div" style="display: none; margin-top: 0.5rem;">
                                    <input type="text" name="custom_emails" 
                                           placeholder="email1@example.com, email2@example.com" 
                                           style="width: 100%; padding: 0.5rem; border-radius: 6px;">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="body">Message Body</label>
                            <div style="margin-bottom: 0.5rem;">
                                <button type="button" class="template-btn" 
                                        onclick="insertTemplate('welcome')">Welcome</button>
                                <button type="button" class="template-btn" 
                                        onclick="insertTemplate('balance_low')">Low Balance</button>
                                <button type="button" class="template-btn" 
                                        onclick="insertTemplate('promo')">Promo Offer</button>
                            </div>
                            <textarea id="body" name="body" placeholder="Hello {name}, ..." required></textarea>
                        </div>

                        <div class="btn-group">
                            <a href="index.php" class="btn" style="background: #6c757d;">
                                <i class="fas fa-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn" style="background: #06d6a0;">
                                <i class="fas fa-paper-plane"></i> Send Email
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.getElementById('custom_target').addEventListener('change', function() {
        document.getElementById('custom_emails_div').style.display = this.checked ? 'block' : 'none';
    });

    function insertTemplate(type) {
        const textarea = document.getElementById('body');
        let template = '';
        
        switch(type) {
            case 'welcome':
                template = "Hello {name},\n\nWelcome to <?= htmlspecialchars(setting('site_name', 'VTU Fintech')) ?>! 🎉\n\nYour account is now active. Start buying data bundles instantly.\n\nBest regards,\nThe <?= setting('site_name', 'VTU Fintech') ?> Team";
                break;
            case 'balance_low':
                template = "Hello {name},\n\nYour wallet balance is low: ₦{balance}\n\nFund your account now to avoid transaction delays.\n\n[Fund Wallet Button]\n\nThank you!";
                break;
            case 'promo':
                template = "Hello {name},\n\n🔥 FLASH SALE: Get 10% EXTRA DATA on all bundles today!\n\nUse code: EXTRA10 at checkout.\n\nOffer ends in 24 hours.\n\n<?= setting('site_name', 'VTU Fintech') ?>";
                break;
        }
        
        textarea.value = template;
    }
    </script>
</body>
</html>