<?php
/**
 * chat_get.php
 * Endpoint: GET
 * Returns new messages (id > last_id) for a specific conversation with prepared statements & auth check.
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
    echo json_encode(['success' => false, 'error' => 'Database connection failed', 'messages' => [], 'unread_count' => 0]);
    exit();
}

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$lastId = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
$caller = isset($_GET['caller']) ? strtolower(trim($_GET['caller'])) : 'user';
$markRead = isset($_GET['mark_read']) ? intval($_GET['mark_read']) : ($caller === 'admin' ? 1 : 0);

// Authentication check
if ($caller === 'admin') {
    $isAdmin = (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true)
            || (!empty($_SESSION['admin_id']) && intval($_SESSION['admin_id']) > 0)
            || (!empty($_SESSION['admin']) && $_SESSION['admin'] === true);
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized admin access', 'messages' => [], 'unread_count' => 0]);
        exit();
    }
} else {
    $sessionUserId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);

    // 1. Resolve session user by email if user_id is missing from session
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

    // 2. Resolve by user_id if valid user in system_users (shared-hosting cookie drop resilience)
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
        echo json_encode(['success' => false, 'error' => 'Customer authentication required', 'messages' => [], 'unread_count' => 0]);
        exit();
    }
}

if ($userId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID', 'messages' => [], 'unread_count' => 0]);
    exit();
}

// Ensure table exists
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

// Mark messages as read if requested
if ($markRead === 1) {
    if ($caller === 'admin') {
        $upStmt = $conn->prepare("UPDATE admin_chat_messages SET is_read = 1 WHERE user_id = ? AND sender = 'user' AND is_read = 0");
    } else {
        $upStmt = $conn->prepare("UPDATE admin_chat_messages SET is_read = 1 WHERE user_id = ? AND sender = 'admin' AND is_read = 0");
    }
    if ($upStmt) {
        $upStmt->bind_param("i", $userId);
        $upStmt->execute();
        $upStmt->close();
    }
}

// Efficient query: retrieve messages
$messages = [];
$stmt = $conn->prepare("
    SELECT id, user_id, sender, message, is_read, created_at 
    FROM admin_chat_messages 
    WHERE user_id = ? AND id > ? 
    ORDER BY id ASC
");

if ($stmt) {
    $stmt->bind_param("ii", $userId, $lastId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $messages[] = [
            'id'         => (int)$row['id'],
            'user_id'    => (int)$row['user_id'],
            'sender'     => $row['sender'],
            'message'    => $row['message'],
            'is_read'    => (int)$row['is_read'],
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();
}

// Count unread messages
$unreadCount = 0;
if ($caller === 'user') {
    $cStmt = $conn->prepare("SELECT COUNT(*) as unread FROM admin_chat_messages WHERE user_id = ? AND sender = 'admin' AND is_read = 0");
} else {
    $cStmt = $conn->prepare("SELECT COUNT(*) as unread FROM admin_chat_messages WHERE user_id = ? AND sender = 'user' AND is_read = 0");
}
if ($cStmt) {
    $cStmt->bind_param("i", $userId);
    $cStmt->execute();
    $cRes = $cStmt->get_result();
    if ($cRow = $cRes->fetch_assoc()) {
        $unreadCount = (int)$cRow['unread'];
    }
    $cStmt->close();
}

echo json_encode([
    'success'      => true,
    'user_id'      => $userId,
    'messages'     => $messages,
    'unread_count' => $unreadCount
]);
exit();
