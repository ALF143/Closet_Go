<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();
require_once '../database/db_config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['admin_id']) && !isset($_SESSION['admin'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in as admin.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reservation_code VARCHAR(50) DEFAULT NULL AFTER reservation_group_id");
    $conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS payment_option VARCHAR(50) DEFAULT 'full' AFTER payment_method");
    $conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS downpayment_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_option");
    $conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS balance_amount DECIMAL(10,2) DEFAULT 0.00 AFTER downpayment_amount");
    $conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS product_status VARCHAR(20) DEFAULT 'reserve' AFTER order_status");

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $productId = intval($_POST['product_id'] ?? 0);
    $productStatus = ($_POST['product_status'] ?? 'reserve') === 'buy' ? 'buy' : 'reserve';
    $reserveDate = trim($_POST['reserve_date'] ?? '');
    $returnDate = trim($_POST['return_date'] ?? '');
    $reserveTime = trim($_POST['reserve_time'] ?? '10:00 AM');
    $returnTime = trim($_POST['return_time'] ?? '05:00 PM');
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $paymentMethod = trim($_POST['payment_method'] ?? 'cash');
    $paymentOption = trim($_POST['payment_option'] ?? 'full');

    if (empty($firstName) || empty($lastName)) {
        throw new Exception('Customer first and last name are required');
    }

    if ($productId <= 0) {
        throw new Exception('Please select a product/gown');
    }

    if (empty($reserveDate)) {
        throw new Exception('Reserve/Purchase date is required');
    }

    if ($productStatus === 'reserve' && empty($returnDate)) {
        throw new Exception('Return date is required for rental reservations');
    }

    if ($productStatus === 'buy' && empty($returnDate)) {
        $returnDate = $reserveDate;
    }

    // Resolve or create customer in system_users to satisfy foreign key constraint on user_id
    $userId = 0;
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    $customerEmail = !empty($email) ? strtolower(trim($email)) : ('walkin_' . ($cleanPhone ?: time()) . '_' . substr(uniqid(), -4) . '@shop.local');

    $uStmt = $conn->prepare("SELECT id FROM system_users WHERE email = ? LIMIT 1");
    if ($uStmt) {
        $uStmt->bind_param("s", $customerEmail);
        $uStmt->execute();
        $uRow = $uStmt->get_result()->fetch_assoc();
        $uStmt->close();
        if ($uRow) {
            $userId = intval($uRow['id']);
        }
    }

    if ($userId <= 0) {
        // Create user record for this walk-in customer in system_users
        $tempPass = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $insUser = $conn->prepare("INSERT INTO system_users (email, password, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?)");
        if ($insUser) {
            $insUser->bind_param("sssss", $customerEmail, $tempPass, $firstName, $lastName, $phone);
            if ($insUser->execute()) {
                $userId = $insUser->insert_id;
            }
            $insUser->close();
        }
    }

    // Fallback if still 0
    if ($userId <= 0) {
        $fallbackRow = $conn->query("SELECT id FROM system_users ORDER BY id ASC LIMIT 1")->fetch_assoc();
        $userId = $fallbackRow ? intval($fallbackRow['id']) : 1;
    }

    // Fetch product details
    $gstmt = $conn->prepare("SELECT id, name, category, price, model_path, stock FROM gowns WHERE id = ? LIMIT 1");
    $gstmt->bind_param("i", $productId);
    $gstmt->execute();
    $gown = $gstmt->get_result()->fetch_assoc();
    $gstmt->close();

    if (!$gown) {
        throw new Exception('Selected product not found');
    }

    $currentStock = intval($gown['stock'] ?? 0);
    if ($currentStock <= 0) {
        throw new Exception('Selected product is currently out of stock');
    }

    $productName = $gown['name'];
    $category = $gown['category'];
    $unitPrice = floatval($gown['price']);
    $modelPath = $gown['model_path'] ?? '';
    $totalPrice = $unitPrice * $quantity;

    if (isset($_POST['total_price']) && floatval($_POST['total_price']) > 0) {
        $totalPrice = floatval($_POST['total_price']);
    }

    if ($paymentOption === 'first_payment') {
        $firstLimit = min(500.00, $totalPrice);
        $downpaymentAmount = isset($_POST['downpayment_amount']) && floatval($_POST['downpayment_amount']) > 0 
            ? floatval($_POST['downpayment_amount']) 
            : $firstLimit;
        $balanceAmount = max(0.00, round($totalPrice - $downpaymentAmount, 2));
    } else {
        $downpaymentAmount = $totalPrice;
        $balanceAmount = 0.00;
    }

    // Sizes / Measurements
    $size1 = trim($_POST['size_1'] ?? '');
    $size2 = trim($_POST['size_2'] ?? '');
    $size3 = trim($_POST['size_3'] ?? '');
    $size4 = trim($_POST['size_4'] ?? '');
    $size5 = trim($_POST['size_5'] ?? '');
    $size6 = trim($_POST['size_6'] ?? '');
    $size7 = trim($_POST['size_7'] ?? '');
    $size8 = trim($_POST['size_8'] ?? '');
    $size9 = trim($_POST['size_9'] ?? '');
    $size10 = trim($_POST['size_10'] ?? '');

    $reservationGroupId = 'RES-' . date('YmdHis') . '-' . substr(uniqid(), -4);
    $uniqueHex = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
    $reservationCode = 'CGW-' . date('ymd') . '-' . $uniqueHex;

    // Combine phone with name note if needed
    if (!empty($phone)) {
        $lastName .= " (Walk-In: {$phone})";
    }

    $insertSql = "INSERT INTO reserve_products (
        user_id, reservation_group_id, reservation_code, product_id, product_name, category, price, model_path,
        first_name, last_name, payment_method, payment_option, downpayment_amount, balance_amount, total_price,
        reserve_date, return_date, reserve_time, return_time,
        size_1, size_2, size_3, size_4, size_5, size_6, size_7, size_8, size_9, size_10,
        quantity, order_status, product_status
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, 'confirmed', ?
    )";

    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        throw new Exception('Prepare statement failed: ' . $conn->error);
    }

    $stmt->bind_param(
        "ississdsssssdddssssssssssssssis",
        $userId, $reservationGroupId, $reservationCode, $productId, $productName, $category, $unitPrice, $modelPath,
        $firstName, $lastName, $paymentMethod, $paymentOption, $downpaymentAmount, $balanceAmount, $totalPrice,
        $reserveDate, $returnDate, $reserveTime, $returnTime,
        $size1, $size2, $size3, $size4, $size5, $size6, $size7, $size8, $size9, $size10,
        $quantity, $productStatus
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $insertId = $stmt->insert_id;
    $stmt->close();

    // Reduce stock from gowns table only for "buy" status (like product_reserve.php)
    if ($productId > 0 && $productStatus === 'buy') {
        $stockSql = "UPDATE gowns SET stock = GREATEST(0, stock - ?) WHERE id = ?";
        $stockStmt = $conn->prepare($stockSql);
        if ($stockStmt) {
            $stockStmt->bind_param("ii", $quantity, $productId);
            $stockStmt->execute();
            $stockStmt->close();
        }
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Walk-In reservation confirmed successfully!',
        'id' => $insertId,
        'reservation_code' => $reservationCode,
        'product_name' => $productName,
        'reserve_date' => $reserveDate,
        'return_date' => $returnDate
    ]);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
