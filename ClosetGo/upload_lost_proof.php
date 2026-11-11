<?php
session_start();
require_once __DIR__ . '/database/db_config.php';

header('Content-Type: application/json');

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please log in to upload proof of payment.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$reservationId = isset($_POST['reservation_id']) ? intval($_POST['reservation_id']) : 0;
if ($reservationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid reservation ID.']);
    exit();
}

$proofFile = isset($_FILES['payment_proof']) ? $_FILES['payment_proof'] : (isset($_FILES['lost_payment_proof']) ? $_FILES['lost_payment_proof'] : null);
if (!$proofFile || $proofFile['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid image file for proof of settlement payment.']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit();
    }

    // Verify reservation belongs to user and is marked as lost
    $stmt = $conn->prepare("SELECT * FROM reserve_products WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $reservationId, $userId);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reservation) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found or does not belong to you.']);
        exit();
    }

    if (intval($reservation['is_lost'] ?? 0) !== 1) {
        echo json_encode(['success' => false, 'message' => 'This order is not marked as a lost item.']);
        exit();
    }

    if (($reservation['lost_status'] ?? '') === 'settled') {
        echo json_encode(['success' => false, 'message' => 'This lost item settlement has already been settled and finalized.']);
        exit();
    }

    // Process file upload
    $uploadDir = __DIR__ . '/uploads/lost_proofs/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $fileTmp  = $proofFile['tmp_name'];
    $fileName = $proofFile['name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file format. Allowed formats: JPG, JPEG, PNG, WEBP, GIF.']);
        exit();
    }

    // Size limit 10MB
    if ($proofFile['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit.']);
        exit();
    }

    $newFileName = 'lost_proof_' . $reservationId . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $targetPath  = $uploadDir . $newFileName;
    $dbPath = 'uploads/lost_proofs/' . $newFileName;

    if (!move_uploaded_file($fileTmp, $targetPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file to server.']);
        exit();
    }

    // Update reservation with new payment proof
    $newStatus = ($reservation['lost_status'] === 'settled') ? 'settled' : 'proof_submitted';
    $upStmt = $conn->prepare("UPDATE reserve_products SET lost_payment_proof = ?, lost_status = ? WHERE id = ?");
    $upStmt->bind_param("ssi", $dbPath, $newStatus, $reservationId);
    $upStmt->execute();
    $upStmt->close();

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
    $logReason = "User uploaded/updated proof of settlement payment for lost item.";

    $logStmt = $conn->prepare("INSERT INTO order_status_logs (reservation_id, reservation_code, reservation_group_id, product_name, customer_name, order_type, old_status, new_status, changed_by_type, reason) VALUES (?, ?, ?, ?, ?, 'rental', 'lost_requested', 'proof_submitted', 'user', ?)");
    if ($logStmt) {
        $logStmt->bind_param("isssss", $reservationId, $logCode, $logGroup, $logProd, $logCust, $logReason);
        $logStmt->execute();
        $logStmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Proof of settlement payment uploaded successfully!',
        'proof_path' => $dbPath,
        'lost_status' => $newStatus
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
