<?php
/**
 * login_user/staff_verify_from_user.php
 * Handles the email verification link when staff logs in via login_user/login.php.
 */
session_start();

$token   = trim($_GET['token']    ?? '');
$staffId = intval($_GET['staff_id'] ?? 0);

if (empty($token) || $staffId <= 0) {
    header('Location: login.php?error=invalid');
    exit;
}

require_once __DIR__ . '/../database/db_config.php';

$verified  = false;
$staffData = null;

try {
    $db   = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id, name, email, verification_token, token_expiry FROM system_staffs WHERE id = ?");
    $stmt->bind_param("i", $staffId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (!empty($row['verification_token'])
            && hash_equals($row['verification_token'], $token)
            && time() <= intval($row['token_expiry'])) {
            $verified  = true;
            $staffData = $row;
            // Clear token
            $conn->query("UPDATE system_staffs SET verification_token=NULL, token_expiry=NULL WHERE id=" . intval($staffId));
        }
    }
} catch (\Throwable $e) {}

// Session fallback (same browser)
if (!$verified && !empty($_SESSION['pending_staff_token']) && !empty($_SESSION['pending_staff_id'])) {
    $storedToken = $_SESSION['pending_staff_token'];
    $storedId    = intval($_SESSION['pending_staff_id']);
    $tokenExpiry = intval($_SESSION['pending_staff_expiry'] ?? 0);
    if (hash_equals($storedToken, $token) && $staffId === $storedId && time() <= $tokenExpiry) {
        $verified  = true;
        $staffData = [
            'id'    => $storedId,
            'name'  => $_SESSION['pending_staff_name']  ?? 'Staff',
            'email' => $_SESSION['pending_staff_email'] ?? '',
        ];
    }
}

if (!$verified) {
    header('Location: login.php?error=expired');
    exit;
}

// ✅ Valid — create staff session
$_SESSION['staff_logged_in']      = true;
$_SESSION['staff_id']             = $staffData['id'];
$_SESSION['staff_name']           = $staffData['name'];
$_SESSION['staff_email']          = $staffData['email'];
$_SESSION['staff_email_verified'] = true;
$_SESSION['staff_verified_at']    = time();

unset(
    $_SESSION['pending_staff_token'],
    $_SESSION['pending_staff_pin'],
    $_SESSION['pending_staff_id'],
    $_SESSION['pending_staff_name'],
    $_SESSION['pending_staff_email'],
    $_SESSION['pending_staff_expiry']
);

header('Location: ../staff/index.php');
exit;
