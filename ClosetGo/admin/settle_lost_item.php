<?php
session_start();
require_once __DIR__ . '/../database/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$reservationId = isset($_POST['reservation_id']) ? intval($_POST['reservation_id']) : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : 'settle'; // 'settle', 'cancel_lost', 'adjust'
$adminNote = isset($_POST['admin_note']) ? trim($_POST['admin_note']) : '';

if ($reservationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid reservation ID.']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit();
    }

    // Auto-heal lost columns
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS is_lost TINYINT(1) NOT NULL DEFAULT 0");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_status VARCHAR(50) DEFAULT NULL");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_reason TEXT DEFAULT NULL");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_selling_price DECIMAL(10,2) DEFAULT 0.00");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_user_paid DECIMAL(10,2) DEFAULT 0.00");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_overdue_fee DECIMAL(10,2) DEFAULT 0.00");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_total_due DECIMAL(10,2) DEFAULT 0.00");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_payment_proof VARCHAR(500) DEFAULT NULL");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_requested_at DATETIME DEFAULT NULL");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_settled_at DATETIME DEFAULT NULL");

    // Fetch existing reservation
    $stmt = $conn->prepare("SELECT * FROM reserve_products WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $reservationId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found.']);
        exit();
    }

    $adminName = $_SESSION['admin_username'] ?? 'Admin';

    if ($action === 'settle') {
        // Mark lost item as fully settled
        $updateStmt = $conn->prepare("UPDATE reserve_products SET 
                                        is_lost = 1,
                                        lost_status = 'settled',
                                        lost_settled_at = NOW(),
                                        order_status = 'completed'
                                      WHERE id = ?");
        $updateStmt->bind_param("i", $reservationId);
        $updateStmt->execute();
        $updateStmt->close();

        // Also update all items in the reservation group if any
        if (!empty($res['reservation_group_id'])) {
            $groupStmt = $conn->prepare("UPDATE reserve_products SET order_status = 'completed' WHERE reservation_group_id = ? AND is_lost = 1");
            $groupStmt->bind_param("s", $res['reservation_group_id']);
            $groupStmt->execute();
            $groupStmt->close();
        }

        // Log action (non-blocking)
        try {
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
                changed_by_type ENUM('staff','admin','user') DEFAULT 'admin',
                staff_name VARCHAR(150) DEFAULT NULL,
                reason TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $logCode = $res['reservation_code'] ?? ('CG-' . $reservationId);
            $logGroup = $res['reservation_group_id'] ?? null;
            $logProd = $res['product_name'] ?? 'Product';
            $logCust = trim(($res['first_name'] ?? '') . ' ' . ($res['last_name'] ?? ''));
            $logReason = "Lost item settled & payment verified by " . $adminName . ". Settlement amount: ₱" . number_format(floatval($res['lost_total_due'] ?? 0), 2) . ($adminNote ? " (Note: " . $adminNote . ")" : "");

            $logStmt = $conn->prepare("INSERT INTO order_status_logs (reservation_id, reservation_code, reservation_group_id, product_name, customer_name, order_type, old_status, new_status, changed_by_type, staff_name, reason) VALUES (?, ?, ?, ?, ?, 'rental', ?, 'completed', 'admin', ?, ?)");
            if ($logStmt) {
                $oldSt = $res['order_status'] ?? 'confirmed';
                $logStmt->bind_param("isssssss", $reservationId, $logCode, $logGroup, $logProd, $logCust, $oldSt, $adminName, $logReason);
                $logStmt->execute();
                $logStmt->close();
            }
        } catch (Throwable $logEx) {
            // Silently ignore logging errors so settlement succeeds
        }

        echo json_encode([
            'success' => true,
            'message' => 'Lost item settled successfully. Order marked as completed.',
            'lost_status' => 'settled',
            'order_status' => 'completed'
        ]);
    } elseif ($action === 'cancel_lost') {
        // Revert lost item flag (item was found / recovered)
        $updateStmt = $conn->prepare("UPDATE reserve_products SET 
                                        is_lost = 0,
                                        lost_status = NULL,
                                        lost_reason = NULL,
                                        lost_settled_at = NULL
                                      WHERE id = ?");
        $updateStmt->bind_param("i", $reservationId);
        $updateStmt->execute();
        $updateStmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Lost item request reverted. Item marked as returned / recovered.',
            'lost_status' => null
        ]);
    } elseif ($action === 'adjust') {
        // Adjust total due or waive fee
        $newTotalDue = isset($_POST['new_total_due']) ? max(0, floatval($_POST['new_total_due'])) : floatval($res['lost_total_due']);
        $updateStmt = $conn->prepare("UPDATE reserve_products SET lost_total_due = ? WHERE id = ?");
        $updateStmt->bind_param("di", $newTotalDue, $reservationId);
        $updateStmt->execute();
        $updateStmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Settlement amount updated to ₱' . number_format($newTotalDue, 2),
            'new_total_due' => $newTotalDue
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
