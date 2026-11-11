<?php
session_start();

$token   = trim($_GET['token'] ?? '');
$adminId = intval($_GET['admin_id'] ?? 0);

if (empty($token) || $adminId <= 0) {
    header('Location: adminlogin.php?error=invalid');
    exit();
}

require_once __DIR__ . '/../database/db_config.php';

$verified = false;
$adminData = null;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Ensure table has verification columns
    @$conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) DEFAULT NULL");
    @$conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS token_expiry INT DEFAULT NULL");

    // 1. Check Database first (works across devices/browsers)
    $stmt = $conn->prepare("SELECT id, firstname, lastname, middlename, suffix, email, verification_token, token_expiry FROM admin_users WHERE id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // If token matches in DB and has not expired
        if (!empty($row['verification_token']) && hash_equals($row['verification_token'], $token) && time() <= intval($row['token_expiry'])) {
            $verified = true;
            $adminData = $row;
            
            // Clear the used token in database
            $clearStmt = $conn->prepare("UPDATE admin_users SET verification_token = NULL, token_expiry = NULL WHERE id = ?");
            $clearStmt->bind_param("i", $adminId);
            $clearStmt->execute();
        }
    }
} catch (\Throwable $e) {
    // Database check error fallback to session
}

// 2. Fallback to Session check if DB token was already cleared or session exists
if (!$verified && !empty($_SESSION['pending_admin_token']) && !empty($_SESSION['pending_admin_id'])) {
    $storedToken  = $_SESSION['pending_admin_token'];
    $storedId     = intval($_SESSION['pending_admin_id']);
    $tokenExpiry  = intval($_SESSION['pending_admin_expiry'] ?? 0);

    if (hash_equals($storedToken, $token) && $adminId === $storedId && time() <= $tokenExpiry) {
        $verified = true;
        $adminData = [
            'id' => $storedId,
            'firstname' => $_SESSION['pending_admin_name'] ?? 'Admin',
            'lastname' => '',
            'email' => $_SESSION['pending_admin_email'] ?? ''
        ];
    }
}

if (!$verified) {
    header('Location: adminlogin.php?error=expired');
    exit();
}

// Token is valid! Set full admin session
$_SESSION['admin_logged_in']      = true;
$_SESSION['admin_id']             = $adminData['id'];
$_SESSION['admin_name']           = trim($adminData['firstname'] . ' ' . (!empty($adminData['middlename']) ? $adminData['middlename'] . ' ' : '') . ($adminData['lastname'] ?? '') . (!empty($adminData['suffix']) ? ' ' . $adminData['suffix'] : ''));
$_SESSION['admin_email']          = $adminData['email'];
$_SESSION['admin_verified_at']    = time();
$_SESSION['admin_email_verified'] = true;

// Clean up pending session tokens
unset(
    $_SESSION['pending_admin_token'],
    $_SESSION['pending_admin_id'],
    $_SESSION['pending_admin_name'],
    $_SESSION['pending_admin_email'],
    $_SESSION['pending_admin_expiry']
);

// Redirect directly to dashboard
header('Location: dashboard.php');
exit();

