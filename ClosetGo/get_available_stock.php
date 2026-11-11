<?php
require_once 'database/db_config.php';

header('Content-Type: application/json');

$productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$productName = isset($_GET['product_name']) ? trim($_GET['product_name']) : '';
$productCategory = isset($_GET['product_category']) ? trim($_GET['product_category']) : '';
$reserveDate = isset($_GET['reserve_date']) ? trim($_GET['reserve_date']) : '';
$returnDate = isset($_GET['return_date']) ? trim($_GET['return_date']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$excludeReservationId = isset($_GET['exclude_reservation_id']) ? intval($_GET['exclude_reservation_id']) : (isset($_GET['exclude_order_id']) ? intval($_GET['exclude_order_id']) : 0);
$excludeGroupId = isset($_GET['exclude_group_id']) ? trim($_GET['exclude_group_id']) : '';

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        echo json_encode(['success' => false, 'available' => 0]);
        exit();
    }

    // Get product stock and id from gowns table
    $productStock = 0;
    $stockRow = null;

    if ($productId > 0) {
        $stockSql = "SELECT id, name, category, stock FROM gowns WHERE id = ?";
        $stockStmt = $conn->prepare($stockSql);
        $stockStmt->bind_param("i", $productId);
        $stockStmt->execute();
        $stockRow = $stockStmt->get_result()->fetch_assoc();
        $stockStmt->close();
    }

    if (!$stockRow && !empty($productName)) {
        if (!empty($productCategory)) {
            $stockSql = "SELECT id, name, category, stock FROM gowns WHERE name = ? AND category = ? LIMIT 1";
            $stockStmt = $conn->prepare($stockSql);
            $stockStmt->bind_param("ss", $productName, $productCategory);
        } else {
            $stockSql = "SELECT id, name, category, stock FROM gowns WHERE name = ? LIMIT 1";
            $stockStmt = $conn->prepare($stockSql);
            $stockStmt->bind_param("s", $productName);
        }
        $stockStmt->execute();
        $stockRow = $stockStmt->get_result()->fetch_assoc();
        $stockStmt->close();
    }

    if ($stockRow) {
        $productId = intval($stockRow['id']);
        $productStock = intval($stockRow['stock'] ?? 1);
        if ($productStock <= 0) $productStock = 1; // Default fallback to 1 if not set
    } else {
        // Fallback default stock to 1 if gown row not found
        $productStock = 1;
    }

    // Fetch all active/future rental reservations from today onwards (based on calendar / reserve_products)
    $today = date('Y-m-d');
    $futureResSql = "SELECT rp.id, rp.reserve_date, rp.return_date,
                            (1 + COALESCE((SELECT COUNT(*) FROM reserve_products_items rpi WHERE rpi.reserve_product_id = rp.id), 0)) as total_items
                     FROM reserve_products rp
                     WHERE (rp.product_id = ? OR (rp.product_name = ? AND rp.category = ?))
                     AND rp.order_status != 'cancelled'
                     AND rp.product_status != 'buy'
                     AND rp.return_date >= ?";
    if ($excludeReservationId > 0) {
        $futureResSql .= " AND rp.id != " . intval($excludeReservationId);
    }
    if (!empty($excludeGroupId)) {
        $futureResSql .= " AND (rp.reservation_group_id IS NULL OR rp.reservation_group_id != '" . $conn->real_escape_string($excludeGroupId) . "')";
    }
    $futureResSql .= " ORDER BY rp.return_date DESC";
    $fStmt = $conn->prepare($futureResSql);
    $fStmt->bind_param("isss", $productId, $productName, $productCategory, $today);
    $fStmt->execute();
    $fRes = $fStmt->get_result();

    $futureReservations = [];
    $latestReturnDate = '';
    $maxConcurrentFuture = 0;
    $dateMap = [];

    while ($row = $fRes->fetch_assoc()) {
        $rStart = $row['reserve_date'];
        $rEnd = $row['return_date'];
        $items = intval($row['total_items']);

        if (empty($latestReturnDate) || $rEnd > $latestReturnDate) {
            $latestReturnDate = $rEnd;
        }

        // Count per day for concurrency check
        $cur = new DateTime($rStart);
        $endObj = new DateTime($rEnd);
        while ($cur <= $endObj) {
            $dStr = $cur->format('Y-m-d');
            $dateMap[$dStr] = ($dateMap[$dStr] ?? 0) + $items;
            if ($dateMap[$dStr] > $maxConcurrentFuture) {
                $maxConcurrentFuture = $dateMap[$dStr];
            }
            $cur->modify('+1 day');
        }

        $futureReservations[] = [
            'id' => $row['id'],
            'reserve_date' => $rStart,
            'return_date' => $rEnd,
            'items' => $items
        ];
    }
    $fStmt->close();

    // Calculate earliest available buy date
    $canBuyNow = true;
    $earliestBuyDate = $today;
    $earliestBuyDateFormatted = date('M j, Y', strtotime($today));
    $latestReservationEndFormatted = '';

    if ($maxConcurrentFuture >= $productStock && !empty($latestReturnDate)) {
        $canBuyNow = false;
        // Earliest buy is latest return date + 2 days (buffer for return & cleaning)
        $availObj = new DateTime($latestReturnDate);
        $availObj->modify('+2 days');
        $earliestBuyDate = $availObj->format('Y-m-d');
        $earliestBuyDateFormatted = $availObj->format('M j, Y');
        $latestReservationEndFormatted = date('M j, Y', strtotime($latestReturnDate));
    }

    if ($type === 'buy_check' || $type === 'buy') {
        echo json_encode([
            'success' => true,
            'stock' => $productStock,
            'can_buy_now' => $canBuyNow,
            'has_future_reservations' => count($futureReservations) > 0,
            'future_reservations_count' => count($futureReservations),
            'latest_return_date' => $latestReturnDate,
            'latest_reservation_end_formatted' => $latestReservationEndFormatted,
            'earliest_buy_date' => $earliestBuyDate,
            'earliest_buy_date_formatted' => $earliestBuyDateFormatted,
            'max_concurrent_future' => $maxConcurrentFuture
        ]);
        exit();
    }

    // Standard rental overlap check if specific dates provided
    $totalReservedItems = 0;
    if (!empty($reserveDate) && !empty($returnDate)) {
        $resSql = "SELECT rp.id,
                          (SELECT COUNT(*) FROM reserve_products_items rpi WHERE rpi.reserve_product_id = rp.id) as additional_items
                   FROM reserve_products rp
                   WHERE (rp.product_id = ? OR (rp.product_name = ? AND rp.category = ?))
                   AND rp.order_status != 'cancelled'
                   AND rp.product_status != 'buy'
                   AND rp.reserve_date <= DATE_ADD(?, INTERVAL 2 DAY)
                   AND rp.return_date >= DATE_SUB(?, INTERVAL 2 DAY)";
        if ($excludeReservationId > 0) {
            $resSql .= " AND rp.id != " . intval($excludeReservationId);
        }
        if (!empty($excludeGroupId)) {
            $resSql .= " AND (rp.reservation_group_id IS NULL OR rp.reservation_group_id != '" . $conn->real_escape_string($excludeGroupId) . "')";
        }
        $resStmt = $conn->prepare($resSql);
        $resStmt->bind_param("issss", $productId, $productName, $productCategory, $returnDate, $reserveDate);
        $resStmt->execute();
        $resResult = $resStmt->get_result();

        while ($row = $resResult->fetch_assoc()) {
            $totalReservedItems += (1 + intval($row['additional_items']));
        }
        $resStmt->close();
    }

    $availableStock = max(0, $productStock - $totalReservedItems);

    echo json_encode([
        'success' => true,
        'stock' => $productStock,
        'reserved' => $totalReservedItems,
        'available' => $availableStock,
        'can_buy_now' => $canBuyNow,
        'earliest_buy_date' => $earliestBuyDate,
        'earliest_buy_date_formatted' => $earliestBuyDateFormatted,
        'latest_return_date' => $latestReturnDate,
        'latest_reservation_end_formatted' => $latestReservationEndFormatted
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'available' => 0, 'error' => $e->getMessage()]);
}
