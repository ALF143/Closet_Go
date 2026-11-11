<?php
/**
 * staff/api_update_status.php
 * Staff API endpoint: POST { code / group_id / id, status: 'confirmed' | 'completed' | 'cancelled' | 'pending', cancellation_reason / reason, cancellation_images[]? }
 * Updates the reservation status in the reserve_products table.
 */
session_start();
header('Content-Type: application/json');

require_once '../database/db_config.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Auto-migrate cancellation columns
@$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS cancellation_reason TEXT DEFAULT NULL");
@$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS cancellation_images TEXT DEFAULT NULL");

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;

$code    = isset($data['code']) ? trim($data['code']) : '';
$groupId = isset($data['group_id']) ? trim($data['group_id']) : '';
$resId   = isset($data['id']) ? intval($data['id']) : 0;
$status  = isset($data['status']) ? trim($data['status']) : '';
$reason  = isset($data['cancellation_reason']) ? trim($data['cancellation_reason']) : (isset($data['reason']) ? trim($data['reason']) : '');

$validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

if (empty($code) && empty($groupId) && $resId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing reservation identifier']);
    exit;
}

// Handle cancellation images upload
$uploadedImages = [];
if ($status === 'cancelled' && isset($_FILES['cancellation_images'])) {
    $uploadDir = '../uploads/cancellations/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $files = $_FILES['cancellation_images'];
    if (is_array($files['name'])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $filename = 'cancel_staff_' . ($resId > 0 ? $resId : preg_replace('/[^a-zA-Z0-9]/', '', $code)) . '_' . time() . '_' . $i . '.' . $ext;
                    if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $filename)) {
                        $uploadedImages[] = 'uploads/cancellations/' . $filename;
                    }
                }
            }
        }
    } else if ($files['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($files['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $filename = 'cancel_staff_' . ($resId > 0 ? $resId : preg_replace('/[^a-zA-Z0-9]/', '', $code)) . '_' . time() . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'], $uploadDir . $filename)) {
                $uploadedImages[] = 'uploads/cancellations/' . $filename;
            }
        }
    }
}
$cancellationImagesJson = !empty($uploadedImages) ? json_encode($uploadedImages) : null;

// Fetch reservation records before updating for logging
$fetchSql = "SELECT id, reservation_code, reservation_group_id, product_name, first_name, last_name, order_status, product_status, payment_method FROM reserve_products WHERE ";
if (!empty($code)) {
    $fetchSql .= "reservation_code = ? OR reservation_group_id = ? OR id = ?";
    $fStmt = $conn->prepare($fetchSql);
    $fStmt->bind_param("ssi", $code, $code, $resId);
} elseif (!empty($groupId)) {
    $fetchSql .= "reservation_group_id = ? OR reservation_code = ?";
    $fStmt = $conn->prepare($fetchSql);
    $fStmt->bind_param("ss", $groupId, $groupId);
} else {
    $fetchSql .= "id = ?";
    $fStmt = $conn->prepare($fetchSql);
    $fStmt->bind_param("i", $resId);
}
$recordsToLog = [];
if ($fStmt && $fStmt->execute()) {
    $fRes = $fStmt->get_result();
    while ($r = $fRes->fetch_assoc()) {
        $recordsToLog[] = $r;
    }
    $fStmt->close();
}

// Build update statement
if ($status === 'cancelled') {
    if (!empty($code)) {
        if ($cancellationImagesJson !== null) {
            $stmt = $conn->prepare("UPDATE reserve_products SET order_status = ?, cancellation_reason = ?, cancellation_images = ? WHERE reservation_code = ? OR reservation_group_id = ? OR id = ?");
            $stmt->bind_param("sssssi", $status, $reason, $cancellationImagesJson, $code, $code, $resId);
        } else {
            $stmt = $conn->prepare("UPDATE reserve_products SET order_status = ?, cancellation_reason = ? WHERE reservation_code = ? OR reservation_group_id = ? OR id = ?");
            $stmt->bind_param("ssssi", $status, $reason, $code, $code, $resId);
        }
    } elseif (!empty($groupId)) {
        if ($cancellationImagesJson !== null) {
            $stmt = $conn->prepare("UPDATE reserve_products SET order_status = ?, cancellation_reason = ?, cancellation_images = ? WHERE reservation_group_id = ? OR reservation_code = ?");
            $stmt->bind_param("sssss", $status, $reason, $cancellationImagesJson, $groupId, $groupId);
        } else {
            $stmt = $conn->prepare("UPDATE reserve_products SET order_status = ?, cancellation_reason = ? WHERE reservation_group_id = ? OR reservation_code = ?");
            $stmt->bind_param("ssss", $status, $reason, $groupId, $groupId);
        }
    } else {
        if ($cancellationImagesJson !== null) {
            $stmt = $conn->prepare("UPDATE reserve_products SET order_status = ?, cancellation_reason = ?, cancellation_images = ? WHERE id = ?");
            $stmt->bind_param("sssi", $status, $reason, $cancellationImagesJson, $resId);
        } else {
            $stmt = $conn->prepare("UPDATE reserve_products SET order_status = ?, cancellation_reason = ? WHERE id = ?");
            $stmt->bind_param("ssi", $status, $reason, $resId);
        }
    }
} else {
    if (!empty($code)) {
        $stmt = $conn->prepare("UPDATE reserve_products SET order_status = ? WHERE reservation_code = ? OR reservation_group_id = ? OR id = ?");
        $stmt->bind_param("sssi", $status, $code, $code, $resId);
    } elseif (!empty($groupId)) {
        $stmt = $conn->prepare("UPDATE reserve_products SET order_status = ? WHERE reservation_group_id = ? OR reservation_code = ?");
        $stmt->bind_param("sss", $status, $groupId, $groupId);
    } else {
        $stmt = $conn->prepare("UPDATE reserve_products SET order_status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $resId);
    }
}

if (!$stmt || !$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Failed to update: ' . ($stmt ? $stmt->error : $conn->error)]);
    exit;
}

$affected = $stmt->affected_rows;
$stmt->close();

// Log to order_status_logs
$staffId = intval($_SESSION['staff_id'] ?? 0);
$staffName = trim($_SESSION['staff_name'] ?? 'Staff Member');
$staffEmail = trim($_SESSION['staff_email'] ?? '');

$logStmt = $conn->prepare("INSERT INTO order_status_logs (reservation_id, reservation_code, reservation_group_id, product_name, customer_name, order_type, old_status, new_status, changed_by_type, staff_id, staff_name, staff_email, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'staff', ?, ?, ?, ?, NOW())");
if ($logStmt) {
    foreach ($recordsToLog as $rec) {
        $rId = intval($rec['id']);
        $rCode = $rec['reservation_code'] ?? $code;
        $rGroup = $rec['reservation_group_id'] ?? $groupId;
        $pName = $rec['product_name'] ?? '';
        $custName = trim(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? ''));
        $orderType = (!empty($rec['product_status']) && $rec['product_status'] === 'buy') || (!empty($rec['payment_method']) && $rec['payment_method'] === 'buy') ? 'buy' : 'reserve';
        $oldStatus = $rec['order_status'] ?? 'pending';

        $logStmt->bind_param("isssssssisss", $rId, $rCode, $rGroup, $pName, $custName, $orderType, $oldStatus, $status, $staffId, $staffName, $staffEmail, $reason);
        $logStmt->execute();
    }
    $logStmt->close();
}

// If marking as cancelled, restore product stock if it was a buy order
if ($status === 'cancelled') {
    $stockQuery = "SELECT product_id, quantity, product_status FROM reserve_products WHERE (reservation_code = ? OR reservation_group_id = ? OR id = ?) AND product_status = 'buy'";
    $sStmt = $conn->prepare($stockQuery);
    $sStmt->bind_param("ssi", $code, $groupId, $resId);
    $sStmt->execute();
    $sRes = $sStmt->get_result();
    while ($row = $sRes->fetch_assoc()) {
        $pId = intval($row['product_id']);
        $qty = intval($row['quantity']);
        if ($pId > 0 && $qty > 0) {
            $conn->query("UPDATE gowns SET stock = stock + $qty WHERE id = $pId");
        }
    }
    $sStmt->close();
}

echo json_encode([
    'success'             => true,
    'message'             => 'Status updated to ' . ucfirst($status),
    'status'              => $status,
    'cancellation_reason' => $reason,
    'cancellation_images' => $cancellationImagesJson,
    'affected'            => $affected
]);
