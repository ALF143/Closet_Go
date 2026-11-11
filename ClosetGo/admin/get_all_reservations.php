<?php
session_start();
require_once '../database/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'DB connection failed']);
        exit();
    }

    $sql = "SELECT id, user_id, reservation_group_id, reservation_code, product_id, product_name, category, price, total_price, model_path,
                   first_name, last_name, payment_method, payment_option, downpayment_amount, balance_amount, reserve_date, return_date,
                   reserve_time, return_time, quantity, order_status, product_status, reserved_at,
                   is_rescheduled, rescheduled_at, original_reserve_date, original_return_date, reschedule_reason,
                   is_lost, lost_status, lost_reason, lost_selling_price, lost_user_paid, lost_overdue_fee, lost_total_due, lost_payment_proof, lost_requested_at, lost_settled_at
            FROM reserve_products
            ORDER BY reserved_at DESC, id DESC";
    $result = $conn->query($sql);

    $reservations = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $reservations[] = $row;
        }
    }

    echo json_encode(['success' => true, 'reservations' => $reservations]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
