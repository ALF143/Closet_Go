<?php
session_start();
require_once 'database/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a review']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$userId = intval($_SESSION['user_id']);
$reserveProductId = isset($_POST['reserve_product_id']) ? intval($_POST['reserve_product_id']) : 0;
$productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$reviewText = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';
if (mb_strlen($reviewText) > 50) {
    $reviewText = mb_substr($reviewText, 0, 50);
}

if ($reserveProductId <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    // Check that this reservation belongs to the user and is completed
    $checkSql = "SELECT id, order_status FROM reserve_products WHERE id = ? AND user_id = ? LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ii", $reserveProductId, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $reservation = $checkResult->fetch_assoc();
    $checkStmt->close();

    if (!$reservation) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
        exit();
    }

    if ($reservation['order_status'] !== 'completed') {
        echo json_encode(['success' => false, 'message' => 'Only completed purchases can be reviewed']);
        exit();
    }

    // Handle photo uploads
    $photo1Path = null;
    $photo2Path = null;
    $uploadDir = 'images/reviews/';

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    for ($i = 1; $i <= 2; $i++) {
        $fileKey = 'photo' . $i;
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
            $fileName = time() . '_review_' . $reserveProductId . '_' . $i . '.' . $ext;
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetPath)) {
                if ($i === 1) $photo1Path = $targetPath;
                else $photo2Path = $targetPath;
            }
        }
    }

    // Check if this reservation belongs to a multi-product reservation group
    $grpStmt = $conn->prepare("SELECT reservation_group_id FROM reserve_products WHERE id = ?");
    $grpStmt->bind_param("i", $reserveProductId);
    $grpStmt->execute();
    $grpRow = $grpStmt->get_result()->fetch_assoc();
    $grpStmt->close();
    $resGroupId = !empty($grpRow['reservation_group_id']) ? $grpRow['reservation_group_id'] : null;

    $allReserveItems = [];
    if ($resGroupId) {
        $allStmt = $conn->prepare("SELECT id, product_id FROM reserve_products WHERE reservation_group_id = ?");
        $allStmt->bind_param("s", $resGroupId);
        $allStmt->execute();
        $allRes = $allStmt->get_result();
        while ($aRow = $allRes->fetch_assoc()) {
            $allReserveItems[] = $aRow;
        }
        $allStmt->close();
    }

    if (empty($allReserveItems)) {
        $allReserveItems[] = ['id' => $reserveProductId, 'product_id' => $productId];
    }

    // Insert or update review for all products in the reservation group with the same rating
    $insertSql = "INSERT INTO product_reviews (reserve_product_id, product_id, user_id, rating, review_text, photo1_path, photo2_path) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text), photo1_path = VALUES(photo1_path), photo2_path = VALUES(photo2_path)";
    $insertStmt = $conn->prepare($insertSql);

    $successCount = 0;
    foreach ($allReserveItems as $rItem) {
        $rId = intval($rItem['id']);
        $pId = intval($rItem['product_id']);
        $insertStmt->bind_param("iiiisss", $rId, $pId, $userId, $rating, $reviewText, $photo1Path, $photo2Path);
        if ($insertStmt->execute()) {
            $successCount++;
        }
    }
    $insertStmt->close();

    if ($successCount > 0) {
        echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit review']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
