<?php
require_once 'database/db_config.php';

header('Content-Type: application/json');

session_start();

$db = new Database();
$conn = $db->getConnection();

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if ($userId <= 0 && isset($_GET['user_id'])) {
    $userId = intval($_GET['user_id']);
}

$notifications = [];
$unreadCount = 0;

if ($userId > 0) {
    // 1. Fetch Reservation / Order updates from reserve_products
    $stmt = $conn->prepare("
        SELECT id, reservation_group_id, reservation_code, product_id, product_name, category, price, 
               model_path, payment_method, product_status, order_status, reserve_date, return_date, reserved_at
        FROM reserve_products 
        WHERE user_id = ? 
        ORDER BY id DESC 
        LIMIT 20
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $resResult = $stmt->get_result();

    $today = date('Y-m-d');

    while ($row = $resResult->fetch_assoc()) {
        $orderStatus = strtolower($row['order_status'] ?? 'pending');
        $code = !empty($row['reservation_code']) ? $row['reservation_code'] : ('#' . $row['id']);
        $prodName = !empty($row['product_name']) ? $row['product_name'] : 'Gown';
        $timestamp = !empty($row['reserved_at']) ? strtotime($row['reserved_at']) : time();

        $title = '';
        $desc = '';
        $icon = 'fas fa-calendar-check';
        $type = 'order';
        $badgeColor = '#ffd700';

        if ($orderStatus === 'confirmed') {
            $title = 'Reservation Confirmed!';
            $desc = "Your reservation {$code} for {$prodName} has been approved by admin.";
            $icon = 'fas fa-check-circle';
            $badgeColor = '#28a745';
        } elseif ($orderStatus === 'completed') {
            $title = 'Order Completed';
            $desc = "Your order for {$prodName} has been marked as completed.";
            $icon = 'fas fa-award';
            $badgeColor = '#17a2b8';
        } elseif ($orderStatus === 'cancelled') {
            $title = 'Reservation Cancelled';
            $desc = "Reservation {$code} for {$prodName} was cancelled.";
            $icon = 'fas fa-times-circle';
            $badgeColor = '#dc3545';
        } else {
            $title = 'Reservation Placed';
            $desc = "Your reservation request {$code} for {$prodName} is pending admin review.";
            $icon = 'fas fa-clock';
            $badgeColor = '#ffc107';
        }

        $notifications[] = [
            'id' => 'order_' . $row['id'] . '_' . $orderStatus,
            'type' => $type,
            'title' => $title,
            'message' => $desc,
            'time' => date('M d, Y h:i A', $timestamp),
            'timestamp' => $timestamp,
            'icon' => $icon,
            'badgeColor' => $badgeColor,
            'link' => 'purchase.php',
            'product_name' => $prodName,
            'product_id' => $row['product_id'],
            'model_path' => $row['model_path']
        ];

        // Return date reminder if rental and return date is close
        if (!empty($row['return_date']) && $orderStatus === 'confirmed') {
            $returnTs = strtotime($row['return_date']);
            $diffDays = ceil(($returnTs - time()) / 86400);

            if ($diffDays >= 0 && $diffDays <= 3) {
                $remindText = ($diffDays === 0) 
                    ? "Today is the return date for {$prodName}." 
                    : "Reminder: {$prodName} return is due in {$diffDays} day(s) on {$row['return_date']}.";

                $notifications[] = [
                    'id' => 'return_due_' . $row['id'],
                    'type' => 'reminder',
                    'title' => 'Return Reminder',
                    'message' => $remindText,
                    'time' => date('M d, Y', $returnTs),
                    'timestamp' => $returnTs,
                    'icon' => 'fas fa-calendar-day',
                    'badgeColor' => '#ff9800',
                    'link' => 'purchase.php',
                    'product_name' => $prodName,
                    'product_id' => $row['product_id'],
                    'model_path' => $row['model_path']
                ];
            }
        }
    }
    $stmt->close();

    // 2. Fetch Admin Chat Messages from admin_chat_messages
    $stmt = $conn->prepare("
        SELECT id, sender, message, is_read, created_at 
        FROM admin_chat_messages 
        WHERE user_id = ? AND sender = 'admin' 
        ORDER BY id DESC 
        LIMIT 10
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $chatResult = $stmt->get_result();

    while ($msgRow = $chatResult->fetch_assoc()) {
        $msgTimestamp = !empty($msgRow['created_at']) ? strtotime($msgRow['created_at']) : time();
        $isUnread = intval($msgRow['is_read']) === 0;

        $notifications[] = [
            'id' => 'chat_' . $msgRow['id'],
            'type' => 'chat',
            'title' => 'ClosetGo Support (Admin)',
            'message' => htmlspecialchars($msgRow['message']),
            'time' => date('M d, h:i A', $msgTimestamp),
            'timestamp' => $msgTimestamp,
            'icon' => 'fas fa-comments',
            'badgeColor' => '#007bff',
            'link' => 'javascript:openUserChat()',
            'is_unread' => $isUnread
        ];
    }
    $stmt->close();
}

// 3. System Welcome / AI Stylist Announcement
$notifications[] = [
    'id' => 'system_ai_welcome',
    'type' => 'system',
    'title' => 'Meet Vergara AI Stylist',
    'message' => 'Explore personalized gown recommendations based on skin tone, budget, and sizing.',
    'time' => 'Always Active',
    'timestamp' => time() - 3600,
    'icon' => 'fas fa-magic',
    'badgeColor' => '#ffd700',
    'link' => 'javascript:centerLottieAnimation()',
    'is_unread' => false
];

// Sort all notifications by timestamp descending
usort($notifications, function($a, $b) {
    return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
});

echo json_encode([
    'success' => true,
    'user_id' => $userId,
    'notifications' => $notifications,
    'count' => count($notifications)
]);
?>
