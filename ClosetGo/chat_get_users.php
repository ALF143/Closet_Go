<?php
/**
 * chat_get_users.php
 * Endpoint: GET
 * Returns all users who have chat messages with admin, along with unread counts and last message info.
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

// Admin authentication check
$isAdmin = (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true)
        || (!empty($_SESSION['admin_id']) && intval($_SESSION['admin_id']) > 0)
        || (!empty($_SESSION['admin']) && $_SESSION['admin'] === true);

if (!$isAdmin) {
    echo json_encode(['success' => false, 'error' => 'Admin authentication required', 'users' => [], 'total_unread' => 0]);
    exit();
}

if (file_exists(__DIR__ . '/database/db_config.php')) {
    require_once __DIR__ . '/database/db_config.php';
} else {
    require_once __DIR__ . '/../database/db_config.php';
}

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed', 'users' => [], 'total_unread' => 0]);
    exit();
}

// Ensure admin_chat_messages table exists
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

// Retrieve distinct conversations
$statsRes = $conn->query("
    SELECT 
        user_id,
        SUM(CASE WHEN sender = 'user' AND is_read = 0 THEN 1 ELSE 0 END) as unread_count,
        MAX(id) as max_msg_id
    FROM admin_chat_messages
    GROUP BY user_id
    ORDER BY max_msg_id DESC
");

$users = [];
$totalUnread = 0;

if ($statsRes) {
    while ($row = $statsRes->fetch_assoc()) {
        $uid = intval($row['user_id']);
        $maxId = intval($row['max_msg_id']);
        $unread = intval($row['unread_count']);
        $totalUnread += $unread;

        // Get latest message details
        $msgRes = $conn->query("SELECT message, sender, created_at FROM admin_chat_messages WHERE id = $maxId LIMIT 1");
        $msgRow = $msgRes ? $msgRes->fetch_assoc() : null;

        // Get user details
        $uRes = $conn->query("SELECT id, email, first_name, middle_name, last_name, profile_image FROM system_users WHERE id = $uid LIMIT 1");
        $uRow = $uRes ? $uRes->fetch_assoc() : null;

        // Determine user display name
        $nameParts = array_filter([
            $uRow['first_name'] ?? '',
            $uRow['middle_name'] ?? '',
            $uRow['last_name'] ?? ''
        ]);
        $displayName = trim(implode(' ', $nameParts));
        if (empty($displayName)) {
            if (!empty($uRow['email'])) {
                $displayName = explode('@', $uRow['email'])[0];
            } else {
                $displayName = 'Customer #' . $uid;
            }
        }

        // Clean last message snippet if it's an inquiry
        $msgPreview = $msgRow['message'] ?? '';
        if (preg_match('/\[INQUIRY_ITEMS\][\s\S]*?\[\/INQUIRY_ITEMS\](.*)/s', $msgPreview, $inqMatch)) {
            $extra = trim($inqMatch[1] ?? '');
            $msgPreview = '🛍️ Attached items' . ($extra ? ': ' . $extra : '');
        }

        $users[] = [
            'id'            => $uid,
            'name'          => $displayName,
            'email'         => $uRow['email'] ?? '',
            'profile_image' => $uRow['profile_image'] ?? 'images/icon/profile_logo.png',
            'unread_count'  => $unread,
            'last_message'  => $msgPreview,
            'last_sender'   => $msgRow['sender'] ?? 'user',
            'last_time'     => $msgRow['created_at'] ?? date('Y-m-d H:i:s')
        ];
    }
}

echo json_encode([
    'success'      => true,
    'total_unread' => $totalUnread,
    'users'        => $users
]);
exit();
