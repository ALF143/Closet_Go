<?php
/**
 * chat_mark_read.php
 * Endpoint: POST
 * Marks messages in a conversation as read with authentication verification.
 */
error_reporting(0);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    if (ini_get('session.use_cookies')) {
        $cp = session_get_cookie_params();
        session_set_cookie_params($cp['lifetime'], '/', $cp['domain'], $cp['secure'], $cp['httponly']);
    }
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

if (file_exists(__DIR__ . '/database/db_config.php')) {
    require_once __DIR__ . '/database/db_config.php';
} else {
    require_once __DIR__ . '/../database/db_config.php';
}

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$userId = isset($input['user_id']) ? intval($input['user_id']) : 0;
$caller = isset($input['caller']) ? strtolower(trim($input['caller'])) : 'admin';

if ($userId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit();
}

// Authentication verification
if ($caller === 'admin') {
    $isAdmin = (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true)
            || (!empty($_SESSION['admin_id']) && intval($_SESSION['admin_id']) > 0)
            || (!empty($_SESSION['admin']) && $_SESSION['admin'] === true);
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'error' => 'Admin authentication required']);
        exit();
    }
    $stmt = $conn->prepare("UPDATE admin_chat_messages SET is_read = 1 WHERE user_id = ? AND sender = 'user' AND is_read = 0");
} else {
    $sessionUserId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
    if ($sessionUserId <= 0 && (!empty($_SESSION['email']) || !empty($_SESSION['user_email']))) {
        $email = trim($_SESSION['email'] ?? $_SESSION['user_email']);
        $uStmt = $conn->prepare("SELECT id FROM system_users WHERE email = ? LIMIT 1");
        if ($uStmt) {
            $uStmt->bind_param("s", $email);
            $uStmt->execute();
            $uRes = $uStmt->get_result();
            if ($uRow = $uRes->fetch_assoc()) {
                $sessionUserId = intval($uRow['id']);
                $_SESSION['user_id'] = $sessionUserId;
            }
            $uStmt->close();
        }
    }
    if ($sessionUserId > 0) {
        $userId = $sessionUserId;
    }
    $stmt = $conn->prepare("UPDATE admin_chat_messages SET is_read = 1 WHERE user_id = ? AND sender = 'admin' AND is_read = 0");
}

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    echo json_encode(['success' => true, 'marked_read' => $affected]);
} else {
    echo json_encode(['success' => false, 'error' => 'Prepare statement failed: ' . $conn->error]);
}
exit();
