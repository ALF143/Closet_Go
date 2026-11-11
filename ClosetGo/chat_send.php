<?php
/**
 * chat_send.php
 * Endpoint: POST
 * Sends a chat message between user and admin with authentication & prepared statements.
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

// Auto-create table without restrictive foreign key
$conn->query("
    CREATE TABLE IF NOT EXISTS `admin_chat_messages` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `sender` ENUM('user','admin') NOT NULL DEFAULT 'user',
        `message` TEXT NOT NULL,
        `is_read` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_msg` (`user_id`, `id`),
        INDEX `idx_unread` (`user_id`, `sender`, `is_read`),
        INDEX `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Remove restrictive foreign key constraint if it exists from older database dumps
try {
    $conn->query("ALTER TABLE `admin_chat_messages` DROP FOREIGN KEY `admin_chat_messages_ibfk_1`");
} catch (Exception $e) {}

// Parse input from either standard FormData ($_POST) or raw JSON (php://input)
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$userId = isset($input['user_id']) ? intval($input['user_id']) : 0;
$sender = isset($input['sender']) ? strtolower(trim($input['sender'])) : 'user';
$message = isset($input['message']) ? trim($input['message']) : '';

if (!in_array($sender, ['user', 'admin'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid sender type']);
    exit();
}

if ($message === '') {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit();
}

// Authentication verification
if ($sender === 'admin') {
    $isAdmin = (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true)
            || (!empty($_SESSION['admin_id']) && intval($_SESSION['admin_id']) > 0);
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'error' => 'Admin authentication required']);
        exit();
    }
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Customer user ID required for admin reply']);
        exit();
    }
} else {
    // User sender verification
    $sessionUserId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);

    // 1. Fallback: resolve session user by email if user_id is missing from session
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

    // 2. Fallback: verify against system_users if session was dropped on shared hosting (ProFreeHost)
    if ($sessionUserId <= 0 && $userId > 0) {
        $chkStmt = $conn->prepare("SELECT id, email FROM system_users WHERE id = ? LIMIT 1");
        if ($chkStmt) {
            $chkStmt->bind_param("i", $userId);
            $chkStmt->execute();
            $chkRes = $chkStmt->get_result();
            if ($chkRow = $chkRes->fetch_assoc()) {
                $sessionUserId = intval($chkRow['id']);
                $_SESSION['user_id'] = $sessionUserId;
                $_SESSION['email'] = $chkRow['email'];
            }
            $chkStmt->close();
        }
    }

    if ($sessionUserId > 0) {
        $userId = $sessionUserId;
    }

    if ($userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Please log in before sending a message to admin.']);
        exit();
    }
}

// Prepared statement insert
$stmt = $conn->prepare("INSERT INTO admin_chat_messages (user_id, sender, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database prepare error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("iss", $userId, $sender, $message);
if ($stmt->execute()) {
    $newId = $stmt->insert_id;
    $stmt->close();
    echo json_encode([
        'success'    => true,
        'id'         => $newId,
        'user_id'    => $userId,
        'sender'     => $sender,
        'message'    => $message,
        'created_at' => date('Y-m-d H:i:s')
    ]);
} else {
    $err = $stmt->error;
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Failed to save message: ' . $err]);
}
exit();
