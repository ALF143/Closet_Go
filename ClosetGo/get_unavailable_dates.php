<?php
require_once __DIR__ . '/database/db_config.php';

if (!headers_sent()) {
    header('Content-Type: application/json');
}

$productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$productName = isset($_GET['product_name']) ? trim($_GET['product_name']) : '';
$productCategory = isset($_GET['product_category']) ? trim($_GET['product_category']) : '';
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'reserve';
$quantity = isset($_GET['quantity']) ? max(1, intval($_GET['quantity'])) : 1;

$excludeReservationId = isset($_GET['exclude_reservation_id']) ? intval($_GET['exclude_reservation_id']) : (isset($_GET['exclude_order_id']) ? intval($_GET['exclude_order_id']) : 0);
$excludeGroupId = isset($_GET['exclude_group_id']) ? trim($_GET['exclude_group_id']) : '';

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        echo json_encode(['success' => false, 'dates' => []]);
        exit();
    }

    // Get product stock & details
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
        if (empty($productName)) $productName = $stockRow['name'];
        if (empty($productCategory)) $productCategory = $stockRow['category'];
        $productStock = max(0, intval($stockRow['stock'] ?? 0));
    }

    // Get all non-cancelled rental reservations for this product
    $resSql = "SELECT rp.id, rp.reserve_date, rp.return_date, rp.quantity,
                      (SELECT COUNT(*) FROM reserve_products_items rpi WHERE rpi.reserve_product_id = rp.id) as additional_items
               FROM reserve_products rp
               WHERE (rp.product_id = ? OR (rp.product_name = ? AND rp.category = ?))
               AND rp.order_status NOT IN ('cancelled', 'completed')
               AND rp.product_status != 'buy'";

    if ($excludeReservationId > 0) {
        $resSql .= " AND rp.id != " . intval($excludeReservationId);
    }
    if (!empty($excludeGroupId)) {
        $resSql .= " AND (rp.reservation_group_id IS NULL OR rp.reservation_group_id != '" . $conn->real_escape_string($excludeGroupId) . "')";
    }
    $resStmt = $conn->prepare($resSql);
    $resStmt->bind_param("iss", $productId, $productName, $productCategory);
    $resStmt->execute();
    $resResult = $resStmt->get_result();

    // Build a map of date => total reserved items (with 2-day gap before and after)
    $dateQuantities = [];

    while ($row = $resResult->fetch_assoc()) {
        $reserveDate = substr($row['reserve_date'] ?? '', 0, 10);
        $returnDate = substr($row['return_date'] ?? '', 0, 10);
        $itemCount = max(intval($row['quantity'] ?? 1), 1 + intval($row['additional_items'] ?? 0));

        if (empty($reserveDate) || $reserveDate === '0000-00-00' || strlen($reserveDate) < 10) continue;
        if (empty($returnDate) || $returnDate === '0000-00-00' || strlen($returnDate) < 10 || $returnDate < $reserveDate) {
            $returnDate = $reserveDate;
        }

        // Extend range by 2 days before and after for preparation and cleaning buffer
        $start = new DateTime($reserveDate);
        $start->modify('-2 days');
        $end = new DateTime($returnDate);
        $end->modify('+2 days');

        if ($start->diff($end)->days > 90) {
            $end = (clone $start)->modify('+90 days');
        }

        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, (clone $end)->modify('+1 day'));

        foreach ($period as $day) {
            $dateKey = $day->format('Y-m-d');
            if (!isset($dateQuantities[$dateKey])) {
                $dateQuantities[$dateKey] = 0;
            }
            $dateQuantities[$dateKey] += $itemCount;
        }
    }
    $resStmt->close();

    $today = date('Y-m-d');
    $unavailableDates = [];

    if ($type === 'buy') {
        if ($productStock <= 0 || $quantity > $productStock) {
            // Out of stock or requested quantity exceeds total stock: block all dates for next 365 days
            $cur = new DateTime($today);
            for ($i = 0; $i < 365; $i++) {
                $unavailableDates[] = $cur->format('Y-m-d');
                $cur->modify('+1 day');
            }
        } else {
            // A purchase permanently removes $quantity items starting from purchase date d into the future.
            // If on ANY future date T >= d, existing active reservations + $quantity > productStock,
            // date d cannot be selected because it would cause a shortage for reservations on date T.
            // Find all conflict dates T >= today where ($dateQuantities[T] + $quantity) > $productStock:
            $conflictDates = [];
            foreach ($dateQuantities as $d => $qty) {
                if ($d >= $today && ($qty + $quantity) > $productStock) {
                    $conflictDates[] = $d;
                }
            }

            if (!empty($conflictDates)) {
                $latestConflictDate = max($conflictDates);

                // All dates from current month start up to latest conflict date are unavailable
                $startMonth = date('Y-m-01');
                $cur = new DateTime($startMonth);
                $conflictEnd = new DateTime($latestConflictDate);

                while ($cur <= $conflictEnd) {
                    $unavailableDates[] = $cur->format('Y-m-d');
                    $cur->modify('+1 day');
                }
            }

            // Also ensure any specific dates where reservations alone >= stock are marked unavailable
            foreach ($dateQuantities as $d => $qty) {
                if ($qty >= $productStock && !in_array($d, $unavailableDates)) {
                    $unavailableDates[] = $d;
                }
            }
        }
    } else {
        // Type === 'reserve' (rental)
        // Unavailable if existing reserved + requested quantity > productStock
        foreach ($dateQuantities as $date => $totalQty) {
            if (($totalQty + $quantity - 1) >= $productStock || $totalQty >= $productStock) {
                $unavailableDates[] = $date;
            }
        }
    }

    $unavailableDates = array_values(array_unique($unavailableDates));
    sort($unavailableDates);

    // Fetch rental settings in real-time from system_settings
    $rentalDurationDays = 10;
    $maxExtensionDays = 7;
    $extensionPricePerDay = 100;
    $overduePenaltyPercent = 10;

    $resSetting = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('rental_duration_days', 'max_extension_days', 'extension_price_per_day', 'overdue_penalty_percent')");
    if ($resSetting) {
        while ($rowSetting = $resSetting->fetch_assoc()) {
            if ($rowSetting['setting_key'] === 'rental_duration_days') {
                $rentalDurationDays = max(1, intval($rowSetting['setting_value']));
            } elseif ($rowSetting['setting_key'] === 'max_extension_days') {
                $maxExtensionDays = max(0, intval($rowSetting['setting_value']));
            } elseif ($rowSetting['setting_key'] === 'extension_price_per_day') {
                $extensionPricePerDay = max(0, floatval($rowSetting['setting_value']));
            } elseif ($rowSetting['setting_key'] === 'overdue_penalty_percent') {
                $overduePenaltyPercent = max(1, intval($rowSetting['setting_value']));
            }
        }
    }

    echo json_encode([
        'success' => true,
        'dates' => $unavailableDates,
        'stock' => $productStock,
        'type' => $type,
        'quantity' => $quantity,
        'rental_duration_days' => $rentalDurationDays,
        'max_extension_days' => $maxExtensionDays,
        'extension_price_per_day' => $extensionPricePerDay,
        'overdue_penalty_percent' => $overduePenaltyPercent
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'dates' => [], 'error' => $e->getMessage()]);
}

