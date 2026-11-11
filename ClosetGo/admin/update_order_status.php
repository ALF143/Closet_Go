<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../database/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit();
}

$reservationId = isset($_POST['id']) ? intval($_POST['id']) : 0;
$newStatus = isset($_POST['status']) ? trim($_POST['status']) : '';

$validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
if ($reservationId <= 0 || !in_array($newStatus, $validStatuses)) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'message' => 'DB connection failed']);
        exit();
    }

    // Ensure order_status_logs table exists
    @$conn->query("CREATE TABLE IF NOT EXISTS order_status_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reservation_id INT NOT NULL,
        reservation_code VARCHAR(50) DEFAULT NULL,
        reservation_group_id VARCHAR(50) DEFAULT NULL,
        product_name VARCHAR(255) DEFAULT NULL,
        customer_name VARCHAR(255) DEFAULT NULL,
        order_type VARCHAR(50) DEFAULT NULL,
        old_status VARCHAR(50) DEFAULT NULL,
        new_status VARCHAR(50) DEFAULT NULL,
        changed_by_type ENUM('staff','admin') DEFAULT 'admin',
        staff_id INT DEFAULT NULL,
        staff_name VARCHAR(150) DEFAULT NULL,
        staff_email VARCHAR(255) DEFAULT NULL,
        reason TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Fetch existing reservation details before update to get original status and group
    $fetchSql = "SELECT id, reservation_code, reservation_group_id, product_name, first_name, last_name, order_status, product_status, payment_method, quantity, product_id FROM reserve_products WHERE id = ?";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->bind_param("i", $reservationId);
    $fetchStmt->execute();
    $resData = $fetchStmt->get_result()->fetch_assoc();
    $fetchStmt->close();

    if (!$resData) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
        exit();
    }

    $resGroupId = !empty($resData['reservation_group_id']) ? $resData['reservation_group_id'] : null;
    $oldStatus  = !empty($resData['order_status']) ? $resData['order_status'] : 'pending';

    // If cancelling and old status was not cancelled, restore stock for buy orders
    if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
        if ($resGroupId) {
            $stockSql = "SELECT product_id, quantity, product_status, payment_method FROM reserve_products WHERE reservation_group_id = ?";
            $sStmt = $conn->prepare($stockSql);
            $sStmt->bind_param("s", $resGroupId);
            $sStmt->execute();
            $sRes = $sStmt->get_result();
            while ($sRow = $sRes->fetch_assoc()) {
                $pId = intval($sRow['product_id']);
                $qty = intval($sRow['quantity']);
                $isBuy = (!empty($sRow['product_status']) && $sRow['product_status'] === 'buy') || (!empty($sRow['payment_method']) && $sRow['payment_method'] === 'buy');
                if ($pId > 0 && $qty > 0 && $isBuy) {
                    $restoreStmt = $conn->prepare("UPDATE gowns SET stock = stock + ? WHERE id = ?");
                    $restoreStmt->bind_param("ii", $qty, $pId);
                    $restoreStmt->execute();
                    $restoreStmt->close();
                }
            }
            $sStmt->close();
        } else {
            $pId = intval($resData['product_id']);
            $qty = intval($resData['quantity']);
            $isBuy = (!empty($resData['product_status']) && $resData['product_status'] === 'buy') || (!empty($resData['payment_method']) && $resData['payment_method'] === 'buy');
            if ($pId > 0 && $qty > 0 && $isBuy) {
                $restoreStmt = $conn->prepare("UPDATE gowns SET stock = stock + ? WHERE id = ?");
                $restoreStmt->bind_param("ii", $qty, $pId);
                $restoreStmt->execute();
                $restoreStmt->close();
            }
        }
    }

    $cancellationReason = null;
    $cancellationImagesJson = null;

    if ($newStatus === 'cancelled') {
        $cancellationReason = isset($_POST['cancellation_reason']) ? trim($_POST['cancellation_reason']) : null;
        $uploadedImages = [];

        if (isset($_FILES['cancellation_images'])) {
            $uploadDir = '../uploads/cancellations/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $files = $_FILES['cancellation_images'];
            if (is_array($files['name'])) {
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                            $filename = 'cancel_' . $reservationId . '_' . time() . '_' . $i . '.' . $ext;
                            if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $filename)) {
                                $uploadedImages[] = 'uploads/cancellations/' . $filename;
                            }
                        }
                    }
                }
            } else {
                if ($files['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($files['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                        $filename = 'cancel_' . $reservationId . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($files['tmp_name'], $uploadDir . $filename)) {
                            $uploadedImages[] = 'uploads/cancellations/' . $filename;
                        }
                    }
                }
            }
        }

        $cancellationImagesJson = !empty($uploadedImages) ? json_encode($uploadedImages) : null;

        if ($resGroupId) {
            $sql = "UPDATE reserve_products SET order_status = ?, cancellation_reason = ?, cancellation_images = ? WHERE reservation_group_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $newStatus, $cancellationReason, $cancellationImagesJson, $resGroupId);
        } else {
            $sql = "UPDATE reserve_products SET order_status = ?, cancellation_reason = ?, cancellation_images = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $newStatus, $cancellationReason, $cancellationImagesJson, $reservationId);
        }
    } else {
        if ($resGroupId) {
            $sql = "UPDATE reserve_products SET order_status = ? WHERE reservation_group_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $newStatus, $resGroupId);
        } else {
            $sql = "UPDATE reserve_products SET order_status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $newStatus, $reservationId);
        }
    }

    if ($stmt->execute()) {
        $stmt->close();

        // Log action to order_status_logs
        $adminId = intval($_SESSION['admin_id'] ?? 0);
        $adminName = trim($_SESSION['admin_name'] ?? 'Admin');
        $adminEmail = trim($_SESSION['admin_email'] ?? '');

        $logFetchSql = "SELECT id, reservation_code, reservation_group_id, product_name, first_name, last_name, order_status, product_status, payment_method FROM reserve_products WHERE " . ($resGroupId ? "reservation_group_id = ?" : "id = ?");
        $lfStmt = $conn->prepare($logFetchSql);
        if ($resGroupId) {
            $lfStmt->bind_param("s", $resGroupId);
        } else {
            $lfStmt->bind_param("i", $reservationId);
        }

        if ($lfStmt->execute()) {
            $lfRes = $lfStmt->get_result();
            $logStmt = $conn->prepare("INSERT INTO order_status_logs (reservation_id, reservation_code, reservation_group_id, product_name, customer_name, order_type, old_status, new_status, changed_by_type, staff_id, staff_name, staff_email, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'admin', ?, ?, ?, ?, NOW())");
            if ($logStmt) {
                while ($rec = $lfRes->fetch_assoc()) {
                    $rId = intval($rec['id']);
                    $rCode = $rec['reservation_code'] ?? '';
                    $rGroup = $rec['reservation_group_id'] ?? '';
                    $pName = $rec['product_name'] ?? '';
                    $custName = trim(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? ''));
                    $orderType = (!empty($rec['product_status']) && $rec['product_status'] === 'buy') || (!empty($rec['payment_method']) && $rec['payment_method'] === 'buy') ? 'buy' : 'reserve';

                    $logStmt->bind_param("isssssssisss", $rId, $rCode, $rGroup, $pName, $custName, $orderType, $oldStatus, $newStatus, $adminId, $adminName, $adminEmail, $cancellationReason);
                    $logStmt->execute();
                }
                $logStmt->close();
            }
            $lfStmt->close();
        }

        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => true, 'message' => 'Status updated to ' . $newStatus]);
    } else {
        $err = $stmt->error;
        $stmt->close();
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'message' => $err]);
    }
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
