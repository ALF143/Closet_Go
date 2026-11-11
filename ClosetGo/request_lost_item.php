<?php
session_start();
require_once __DIR__ . '/database/db_config.php';

header('Content-Type: application/json');

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a lost item request.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$reservationId = isset($_POST['reservation_id']) ? intval($_POST['reservation_id']) : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if ($reservationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid reservation ID.']);
    exit();
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Please provide an explanation or reason for the lost item.']);
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

    // Fetch reservation and gown details
    $sql = "SELECT rp.*, 
                   COALESCE(NULLIF(g.purchase_price, 0), g.price, rp.price, 0) AS gown_selling_price,
                   g.name AS gown_db_name
            FROM reserve_products rp
            LEFT JOIN gowns g ON ((rp.product_id > 0 AND rp.product_id = g.id) 
                               OR ((rp.product_id IS NULL OR rp.product_id = 0) AND LOWER(TRIM(rp.product_name)) = LOWER(TRIM(g.name))))
            WHERE rp.id = ? AND rp.user_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $reservationId, $userId);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reservation) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found or does not belong to you.']);
        exit();
    }

    // Verify it is a borrowed rental item with confirmed status
    $orderStatus = strtolower($reservation['order_status'] ?? 'pending');
    $productStatus = strtolower($reservation['product_status'] ?? '');
    $paymentMethod = strtolower($reservation['payment_method'] ?? '');
    if ($productStatus === 'buy' || $paymentMethod === 'buy') {
        echo json_encode(['success' => false, 'message' => 'Purchased items cannot be reported as lost borrowed items.']);
        exit();
    }

    if ($orderStatus !== 'confirmed') {
        echo json_encode(['success' => false, 'message' => 'Only confirmed reservations can be reported as lost borrowed items.']);
        exit();
    }

    if (intval($reservation['is_lost'] ?? 0) === 1) {
        echo json_encode(['success' => false, 'message' => 'This item has already been reported as lost.']);
        exit();
    }

    // Fetch overdue penalty percent from system_settings
    $overduePenaltyPercent = 10;
    $resPen = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'overdue_penalty_percent' LIMIT 1");
    if ($resPen && $rowPen = $resPen->fetch_assoc()) {
        $overduePenaltyPercent = max(1, intval($rowPen['setting_value']));
    }

    // Selling price of the item (replacement base)
    $sellingPrice = floatval($reservation['gown_selling_price'] ?? 0);
    if ($sellingPrice <= 0) {
        $sellingPrice = floatval($reservation['price'] ?? 0);
    }
    // Multiply by quantity if > 1
    $quantity = max(1, intval($reservation['quantity'] ?? 1));
    $totalSellingPrice = $sellingPrice * $quantity;

    // What user already paid for this rental
    $paymentOption = strtolower($reservation['payment_option'] ?? 'full');
    $userPaid = 0;
    if ($paymentOption === 'first_payment') {
        $userPaid = floatval($reservation['downpayment_amount'] ?? 0);
        if ($userPaid <= 0) {
            $userPaid = min(500, floatval($reservation['total_price'] ?? 0));
        }
    } else {
        $userPaid = floatval($reservation['total_price'] ?? 0);
    }

    // Replacement balance: user pays selling price minus what they already paid
    $replacementBalance = max(0, $totalSellingPrice - $userPaid);

    // Overdue calculation: if return_date is past today
    $todayStr = date('Y-m-d');
    $returnDate = $reservation['return_date'] ?? '';
    $overdueDays = 0;
    $overdueFee = 0.00;

    if (!empty($returnDate) && $returnDate !== '0000-00-00' && $returnDate < $todayStr) {
        $dReturn = new DateTime($returnDate);
        $dToday  = new DateTime($todayStr);
        $diff    = $dToday->diff($dReturn);
        $overdueDays = max(0, intval($diff->days));
        if ($overdueDays > 0) {
            $rentalPricePerDayBase = floatval($reservation['price'] ?? 0);
            $dailyPenalty = ($rentalPricePerDayBase * ($overduePenaltyPercent / 100));
            $overdueFee = round($overdueDays * $dailyPenalty * $quantity, 2);
        }
    }

    // Total due for lost item
    $totalLostDue = round($replacementBalance + $overdueFee, 2);

    // Handle payment proof upload if provided
    $paymentProofPath = null;
    $proofFile = isset($_FILES['payment_proof']) ? $_FILES['payment_proof'] : (isset($_FILES['lost_payment_proof']) ? $_FILES['lost_payment_proof'] : null);
    if ($proofFile && $proofFile['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/lost_proofs/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $fileTmp  = $proofFile['tmp_name'];
        $fileName = $proofFile['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($ext, $allowed)) {
            $newFileName = 'lost_proof_' . $reservationId . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $targetPath  = $uploadDir . $newFileName;
            if (move_uploaded_file($fileTmp, $targetPath)) {
                $paymentProofPath = 'uploads/lost_proofs/' . $newFileName;
            }
        }
    }

    // Update reservation with lost item request details
    $updateSql = "UPDATE reserve_products SET 
                    is_lost = 1,
                    lost_status = 'requested',
                    lost_reason = ?,
                    lost_selling_price = ?,
                    lost_user_paid = ?,
                    lost_overdue_fee = ?,
                    lost_total_due = ?,
                    lost_payment_proof = COALESCE(?, lost_payment_proof),
                    lost_requested_at = NOW()
                  WHERE id = ?";

    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("sddddsi", $reason, $totalSellingPrice, $userPaid, $overdueFee, $totalLostDue, $paymentProofPath, $reservationId);
    $updateStmt->execute();
    $updateStmt->close();

    // Log to order_status_logs
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
        changed_by_type ENUM('staff','admin','user') DEFAULT 'user',
        staff_id INT DEFAULT NULL,
        staff_name VARCHAR(150) DEFAULT NULL,
        staff_email VARCHAR(255) DEFAULT NULL,
        reason TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $logCode = $reservation['reservation_code'] ?? ('CG-' . $reservationId);
    $logGroup = $reservation['reservation_group_id'] ?? null;
    $logProd = $reservation['product_name'] ?? 'Product';
    $logCust = trim(($reservation['first_name'] ?? '') . ' ' . ($reservation['last_name'] ?? ''));
    $logReason = "User reported borrowed item as lost. Selling Price: ₱" . number_format($totalSellingPrice, 2) . " | Paid: ₱" . number_format($userPaid, 2) . " | Overdue: ₱" . number_format($overdueFee, 2) . " | Total Due: ₱" . number_format($totalLostDue, 2) . " | Note: " . $reason;

    $logStmt = $conn->prepare("INSERT INTO order_status_logs (reservation_id, reservation_code, reservation_group_id, product_name, customer_name, order_type, old_status, new_status, changed_by_type, reason) VALUES (?, ?, ?, ?, ?, 'rental', ?, 'lost_requested', 'user', ?)");
    if ($logStmt) {
        $logStmt->bind_param("issssss", $reservationId, $logCode, $logGroup, $logProd, $logCust, $orderStatus, $logReason);
        $logStmt->execute();
        $logStmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Lost item request submitted successfully. Admin will review the settlement.',
        'data' => [
            'reservation_id' => $reservationId,
            'selling_price' => $totalSellingPrice,
            'user_paid' => $userPaid,
            'replacement_balance' => $replacementBalance,
            'overdue_days' => $overdueDays,
            'overdue_fee' => $overdueFee,
            'total_due' => $totalLostDue,
            'lost_status' => 'requested',
            'payment_proof' => $paymentProofPath
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
