<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/database/db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
$userEmail = $_SESSION['email'] ?? ($_SESSION['user_email'] ?? '');

if ($userId <= 0 && empty($userEmail)) {
    echo json_encode(['success' => false, 'message' => 'Please log in to reschedule your reservation.']);
    exit();
}

$orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$newReserveDate = isset($_POST['new_reserve_date']) ? trim($_POST['new_reserve_date']) : '';
$newReturnDate = isset($_POST['new_return_date']) ? trim($_POST['new_return_date']) : '';
$rescheduleReason = isset($_POST['reschedule_reason']) ? trim($_POST['reschedule_reason']) : '';

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid reservation ID.']);
    exit();
}

if (empty($newReserveDate) || empty($newReturnDate)) {
    echo json_encode(['success' => false, 'message' => 'Both reserve date and return date are required.']);
    exit();
}

// Basic date format validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newReserveDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newReturnDate)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Expected YYYY-MM-DD.']);
    exit();
}

$today = date('Y-m-d');
if ($newReserveDate < $today) {
    echo json_encode(['success' => false, 'message' => 'New reserve date cannot be in the past.']);
    exit();
}

if ($newReturnDate < $newReserveDate) {
    echo json_encode(['success' => false, 'message' => 'Return date cannot be earlier than reserve date.']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit();
    }

    // Auto-create reschedule columns if missing on online database
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS is_rescheduled TINYINT(1) NOT NULL DEFAULT 0");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS rescheduled_at DATETIME DEFAULT NULL");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS original_reserve_date DATE DEFAULT NULL");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS original_return_date DATE DEFAULT NULL");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reschedule_reason TEXT DEFAULT NULL");

    // Resolve user ID if only email is present
    if ($userId <= 0 && !empty($userEmail)) {
        $uStmt = $conn->prepare("SELECT id FROM system_users WHERE email = ? LIMIT 1");
        $uStmt->bind_param("s", $userEmail);
        $uStmt->execute();
        $uRow = $uStmt->get_result()->fetch_assoc();
        if ($uRow) {
            $userId = intval($uRow['id']);
        }
        $uStmt->close();
    }

    // Fetch the target reservation
    $stmt = $conn->prepare("SELECT * FROM reserve_products WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found or does not belong to you.']);
        exit();
    }

    // Check if buy order
    if (($order['product_status'] ?? '') === 'buy' || ($order['payment_method'] ?? '') === 'buy') {
        echo json_encode(['success' => false, 'message' => 'Purchase orders cannot be rescheduled. Rescheduling is only for rental reservations.']);
        exit();
    }

    // Check status
    $orderStatus = strtolower(trim($order['order_status'] ?? 'pending'));
    if ($orderStatus === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'Cancelled reservations cannot be rescheduled.']);
        exit();
    }
    if ($orderStatus === 'completed') {
        echo json_encode(['success' => false, 'message' => 'Completed reservations cannot be rescheduled.']);
        exit();
    }

    // Enforce ONE-TIME reschedule rule
    if (intval($order['is_rescheduled'] ?? 0) >= 1) {
        echo json_encode(['success' => false, 'message' => 'This reservation has already been rescheduled once. Rescheduling is permitted only once per reservation.']);
        exit();
    }

    // Enforce rule: cannot reschedule if current reserve date is past date or present in <= 3 days
    $existingReserveDate = $order['reserve_date'] ?? '';
    $cutoffNoticeDate = date('Y-m-d', strtotime('+3 days'));
    if (empty($existingReserveDate) || $existingReserveDate < $today) {
        echo json_encode(['success' => false, 'message' => 'Cannot reschedule: your reservation date has already passed.']);
        exit();
    }
    if ($existingReserveDate <= $cutoffNoticeDate) {
        echo json_encode(['success' => false, 'message' => 'Cannot reschedule: your current Reserve Date is within 3 days of today. Rescheduling is only allowed more than 3 days ahead of your reservation date.']);
        exit();
    }

    $groupId = trim($order['reservation_group_id'] ?? '');

    // Fetch all products in the reservation group (or just this single order)
    $groupItems = [];
    if (!empty($groupId)) {
        $gStmt = $conn->prepare("SELECT id, product_id, product_name, category, quantity FROM reserve_products WHERE reservation_group_id = ? AND user_id = ? AND order_status NOT IN ('cancelled', 'completed')");
        $gStmt->bind_param("si", $groupId, $userId);
        $gStmt->execute();
        $gRes = $gStmt->get_result();
        while ($gRow = $gRes->fetch_assoc()) {
            $groupItems[] = $gRow;
        }
        $gStmt->close();
    }
    if (empty($groupItems)) {
        $groupItems[] = $order;
    }

    // Verify availability for each product in the reservation
    foreach ($groupItems as $item) {
        $itemPid = intval($item['product_id'] ?? 0);
        $itemPname = trim($item['product_name'] ?? '');
        $itemCat = trim($item['category'] ?? '');
        $itemQty = max(intval($item['quantity'] ?? 1), 1);
        $itemOrderId = intval($item['id'] ?? 0);

        // Fetch stock
        $itemStock = 1;
        if ($itemPid > 0) {
            $sStmt = $conn->prepare("SELECT stock FROM gowns WHERE id = ?");
            $sStmt->bind_param("i", $itemPid);
            $sStmt->execute();
            $sRow = $sStmt->get_result()->fetch_assoc();
            if ($sRow) $itemStock = max(1, intval($sRow['stock'] ?? 1));
            $sStmt->close();
        } elseif (!empty($itemPname)) {
            $sStmt = $conn->prepare("SELECT stock FROM gowns WHERE name = ? LIMIT 1");
            $sStmt->bind_param("s", $itemPname);
            $sStmt->execute();
            $sRow = $sStmt->get_result()->fetch_assoc();
            if ($sRow) $itemStock = max(1, intval($sRow['stock'] ?? 1));
            $sStmt->close();
        }

        // Check overlapping reservations with 2-day buffer
        $checkSql = "SELECT rp.id, rp.quantity,
                            (SELECT COUNT(*) FROM reserve_products_items rpi WHERE rpi.reserve_product_id = rp.id) as additional_items
                     FROM reserve_products rp
                     WHERE (rp.product_id = ? OR (rp.product_name = ? AND rp.category = ?))
                       AND rp.order_status NOT IN ('cancelled', 'completed')
                       AND rp.product_status != 'buy'
                       AND rp.id != ?
                       AND rp.reserve_date <= DATE_ADD(?, INTERVAL 2 DAY)
                       AND rp.return_date >= DATE_SUB(?, INTERVAL 2 DAY)";
        if (!empty($groupId)) {
            $checkSql .= " AND (rp.reservation_group_id IS NULL OR rp.reservation_group_id != '" . $conn->real_escape_string($groupId) . "')";
        }

        $cStmt = $conn->prepare($checkSql);
        $cStmt->bind_param("ississ", $itemPid, $itemPname, $itemCat, $itemOrderId, $newReturnDate, $newReserveDate);
        $cStmt->execute();
        $cRes = $cStmt->get_result();

        $totalReserved = 0;
        while ($cRow = $cRes->fetch_assoc()) {
            $totalReserved += max(intval($cRow['quantity'] ?? 1), 1 + intval($cRow['additional_items'] ?? 0));
        }
        $cStmt->close();

        if (($totalReserved + $itemQty) > $itemStock && $itemStock > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'The selected dates (' . $newReserveDate . ' to ' . $newReturnDate . ') are fully booked for ' . htmlspecialchars($itemPname ?: 'your product') . '. Please select other available dates on the calendar.'
            ]);
            exit();
        }
    }

    // Preserve original dates
    $originalReserveDate = !empty($order['original_reserve_date']) ? $order['original_reserve_date'] : $order['reserve_date'];
    $originalReturnDate = !empty($order['original_return_date']) ? $order['original_return_date'] : $order['return_date'];

    // Update reservation
    if (!empty($groupId)) {
        $updSql = "UPDATE reserve_products 
                   SET original_reserve_date = COALESCE(original_reserve_date, reserve_date),
                       original_return_date = COALESCE(original_return_date, return_date),
                       reserve_date = ?,
                       return_date = ?,
                       is_rescheduled = 1,
                       rescheduled_at = NOW(),
                       reschedule_reason = ?
                   WHERE reservation_group_id = ? AND user_id = ?";
        $updStmt = $conn->prepare($updSql);
        $updStmt->bind_param("ssssi", $newReserveDate, $newReturnDate, $rescheduleReason, $groupId, $userId);
        $updStmt->execute();
        $updStmt->close();
    } else {
        $updSql = "UPDATE reserve_products 
                   SET original_reserve_date = COALESCE(original_reserve_date, reserve_date),
                       original_return_date = COALESCE(original_return_date, return_date),
                       reserve_date = ?,
                       return_date = ?,
                       is_rescheduled = 1,
                       rescheduled_at = NOW(),
                       reschedule_reason = ?
                   WHERE id = ? AND user_id = ?";
        $updStmt = $conn->prepare($updSql);
        $updStmt->bind_param("sssii", $newReserveDate, $newReturnDate, $rescheduleReason, $orderId, $userId);
        $updStmt->execute();
        $updStmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Your reservation dates have been successfully rescheduled!',
        'order_id' => $orderId,
        'new_reserve_date' => $newReserveDate,
        'new_return_date' => $newReturnDate,
        'original_reserve_date' => $originalReserveDate,
        'original_return_date' => $originalReturnDate,
        'is_rescheduled' => 1
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
