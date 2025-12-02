<?php
session_start();

// Ensure Stage 2 passed
if (!isset($_SESSION['stage2_passed']) || !$_SESSION['stage2_passed']) {
    header("Location: stage2.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $pin = $_POST['pin'] ?? '';

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($pin)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!preg_match('/^\d{4}$/', $pin)) {
        $error = "Security PIN must be exactly 4 digits.";
    } else {
        try {
            // Reuse DB config from Stage 2
            $db = $_SESSION['db_config'];
            $pdo = new PDO(
                "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
                $db['user'],
                $db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Hash password and PIN
            $password_hash = password_hash($password, PASSWORD_ARGON2ID);
            $pin_hash = password_hash($pin, PASSWORD_ARGON2ID);

            // Insert admin user
            $stmt = $pdo->prepare("
                INSERT INTO `{$db['prefix']}users` 
                (`username`, `email`, `password_hash`, `security_pin_hash`, `is_admin`, `is_active`) 
                VALUES (?, ?, ?, ?, 1, 1)
            ");
            $stmt->execute([$username, $email, $password_hash, $pin_hash]);

            // Update admin email in settings
            $stmt = $pdo->prepare("
                UPDATE `{$db['prefix']}admin_settings` 
                SET `setting_value` = ? 
                WHERE `setting_key` = 'admin_email'
            ");
            $stmt->execute([$email]);

            $success = "Admin account created successfully!";
            $_SESSION['stage3_passed'] = true;
            $_SESSION['admin_created'] = [
                'username' => $username,
                'email' => $email
            ];
            header("Refresh: 2; url=stage4.php");
            exit();

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                $error = "Username or email already exists. Try another.";
            } else {
                $error = "Database error: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VTU Installer — Stage 3: Admin Setup</title>
    <style>
        /* Reuse same CSS as stage2.php */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fb; color: #333; padding: 2rem; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 6px 16px rgba(0,0,0,0.08); overflow: hidden; }
        .header { background: #06d6a0; color: white; padding: 1.5rem; text-align: center; }
        .header h2 { font-weight: 600; }
        .content { padding: 2rem; }
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        input { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }
        input:focus { outline: none; border-color: #06d6a0; box-shadow: 0 0 0 3px rgba(6, 214, 160, 0.2); }
        .btn { background: #06d6a0; color: white; border: none; padding: 0.85rem 1.5rem; font-size: 1rem; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 1rem; }
        .btn:hover { background: #05c18f; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .note { font-size: 0.85rem; color: #666; margin-top: 0.5rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Stage 3: Create Admin Account</h2>
            <p>You'll use this to log in to the dashboard</p>
        </div>
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Admin Username</label>
                    <input type="text" id="username" name="username" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="email">Admin Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                    <p class="note">At least 8 characters</p>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="form-group">
                    <label for="pin">Security PIN (4 digits)</label>
                    <input type="password" id="pin" name="pin" required inputmode="numeric" pattern="[0-9]{4}" maxlength="4">
                    <p class="note">Used for sensitive actions (e.g., fund withdrawal)</p>
                </div>

                <button type="submit" class="btn">Create Admin Account</button>
            </form>
        </div>
    </div>
</body>
</html>