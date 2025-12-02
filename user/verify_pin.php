<?php
// user/verify_pin.php
session_start();
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !auth()->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$pin = $_POST['pin'] ?? '';
if (strlen($pin) !== 4 || !ctype_digit($pin)) {
    echo json_encode(['success' => false, 'message' => 'Invalid PIN format']);
    exit();
}

$result = auth()->verifyPin($_SESSION['user_id'], $pin);
echo json_encode(['success' => $result]);
?>