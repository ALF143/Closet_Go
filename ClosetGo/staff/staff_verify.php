<?php
/**
 * staff/staff_verify.php
 * Handles the email verification link click for staff login.
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

    // Ensure columns exist
    @$conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) DEFAULT NULL");
    @$conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS token_expiry INT DEFAULT NULL");

    // Check DB token first (works across devices / browsers)
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
            // Clear token so it cannot be reused
            $cl = $conn->prepare("UPDATE system_staffs SET verification_token = NULL, token_expiry = NULL WHERE id = ?");
            $cl->bind_param("i", $staffId);
            $cl->execute();
        }
    }
} catch (\Throwable $e) {
    // Fall through to session check
}

// Session fallback (same browser / same device)
if (!$verified && !empty($_SESSION['pending_staff_token']) && !empty($_SESSION['pending_staff_id'])) {
    $storedToken  = $_SESSION['pending_staff_token'];
    $storedId     = intval($_SESSION['pending_staff_id']);
    $tokenExpiry  = intval($_SESSION['pending_staff_expiry'] ?? 0);

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

// ✅ Token valid — create full staff session
$_SESSION['staff_logged_in']      = true;
$_SESSION['staff_id']             = $staffData['id'];
$_SESSION['staff_name']           = $staffData['name'];
$_SESSION['staff_email']          = $staffData['email'];
$_SESSION['staff_email_verified'] = true;
$_SESSION['staff_verified_at']    = time();

// Clean pending session data
unset(
    $_SESSION['pending_staff_token'],
    $_SESSION['pending_staff_pin'],
    $_SESSION['pending_staff_id'],
    $_SESSION['pending_staff_name'],
    $_SESSION['pending_staff_email'],
    $_SESSION['pending_staff_expiry']
);

header('Location: index.php');
exit;
