<?php
session_start();
require_once '../database/db_config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: adminlogin.php');
    exit();
}

// Handle AJAX update for rental duration & overdue penalty
// Handle AJAX update for rental duration, overdue penalty, and extension policy
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_rental_duration') {
    header('Content-Type: application/json');
    $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 10;
    $penaltyPercent = isset($_POST['penalty_percent']) ? intval($_POST['penalty_percent']) : 10;
    $maxExtensionDays = isset($_POST['max_extension_days']) ? intval($_POST['max_extension_days']) : 7;
    $extensionPrice = isset($_POST['extension_price']) ? floatval($_POST['extension_price']) : 100;
    $lowStockThreshold = isset($_POST['low_stock_threshold']) ? intval($_POST['low_stock_threshold']) : 2;

    if ($duration < 1) $duration = 1;
    if ($duration > 90) $duration = 90;
    if ($penaltyPercent < 1) $penaltyPercent = 1;
    if ($penaltyPercent > 100) $penaltyPercent = 100;
    if ($maxExtensionDays < 0) $maxExtensionDays = 0;
    if ($maxExtensionDays > 60) $maxExtensionDays = 60;
    if ($extensionPrice < 0) $extensionPrice = 0;
    if ($lowStockThreshold < 1) $lowStockThreshold = 1;
    if ($lowStockThreshold > 50) $lowStockThreshold = 50;
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        if ($conn) {
            $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('rental_duration_days', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $durStr = strval($duration);
            $stmt->bind_param("ss", $durStr, $durStr);
            $stmt->execute();
            $stmt->close();

            $stmt2 = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('overdue_penalty_percent', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $penStr = strval($penaltyPercent);
            $stmt2->bind_param("ss", $penStr, $penStr);
            $stmt2->execute();
            $stmt2->close();

            $stmt3 = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('max_extension_days', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $extStr = strval($maxExtensionDays);
            $stmt3->bind_param("ss", $extStr, $extStr);
            $stmt3->execute();
            $stmt3->close();

            $stmt4 = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('extension_price_per_day', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $extPriceStr = strval($extensionPrice);
            $stmt4->bind_param("ss", $extPriceStr, $extPriceStr);
            $stmt4->execute();
            $stmt4->close();

            $stmt5 = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('low_stock_threshold', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stockThreshStr = strval($lowStockThreshold);
            $stmt5->bind_param("ss", $stockThreshStr, $stockThreshStr);
            $stmt5->execute();
            $stmt5->close();

            echo json_encode([
                'success' => true,
                'duration' => $duration,
                'penalty_percent' => $penaltyPercent,
                'max_extension_days' => $maxExtensionDays,
                'extension_price' => $extensionPrice,
                'low_stock_threshold' => $lowStockThreshold
            ]);
            exit();
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Handle AJAX fetch of all gowns & categories for real-time product picker
if (isset($_GET['action']) && $_GET['action'] === 'get_all_gowns_picker') {
    header('Content-Type: application/json');
    try {
        $database = new Database();
        $conn = $database->getConnection();
        $list = [];
        $categories = [];
        if ($conn) {
            $catRes = $conn->query("SELECT category_name FROM categories ORDER BY id ASC");
            if ($catRes) {
                while ($crow = $catRes->fetch_assoc()) {
                    if (!empty($crow['category_name'])) {
                        $categories[] = $crow['category_name'];
                    }
                }
            }
            $gownsRes = $conn->query("SELECT id, name, category, price, discount, image_path, image_path2, image_path3, model_path, stock, color, description FROM gowns ORDER BY name ASC");
            if ($gownsRes) {
                while ($r = $gownsRes->fetch_assoc()) {
                    $list[] = $r;
                }
            }
        }
        echo json_encode(['success' => true, 'gowns' => $list, 'categories' => $categories]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Get current month and year from URL or use current date
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get reservations for the current month (for calendar display)
$reservations = [];
// Get ALL reservations (for all-time stats)
$allReservations = [];
$rentalDurationDays = 10; // default duration
$overduePenaltyPercent = 10; // default overdue fee %
$maxExtensionDays = 7; // default max extension days
$extensionPricePerDay = 100; // default extension fee ₱/day
$lowStockThreshold = 2; // default low stock threshold

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        // Fetch or initialize rental duration days, overdue penalty percent, extension policy, and low stock threshold
        $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('rental_duration_days', '10')");
        $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('overdue_penalty_percent', '10')");
        $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('max_extension_days', '7')");
        $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('extension_price_per_day', '100')");
        $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('low_stock_threshold', '2')");
        
        $resSetting = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'rental_duration_days' LIMIT 1");
        if ($resSetting && $rowSetting = $resSetting->fetch_assoc()) {
            $rentalDurationDays = intval($rowSetting['setting_value']);
            if ($rentalDurationDays < 1) $rentalDurationDays = 10;
        }

        $resPen = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'overdue_penalty_percent' LIMIT 1");
        if ($resPen && $rowPen = $resPen->fetch_assoc()) {
            $overduePenaltyPercent = intval($rowPen['setting_value']);
            if ($overduePenaltyPercent < 1) $overduePenaltyPercent = 10;
        }

        $resExtDays = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'max_extension_days' LIMIT 1");
        if ($resExtDays && $rowExtDays = $resExtDays->fetch_assoc()) {
            $maxExtensionDays = intval($rowExtDays['setting_value']);
            if ($maxExtensionDays < 0) $maxExtensionDays = 0;
        }

        $resExtPrice = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'extension_price_per_day' LIMIT 1");
        if ($resExtPrice && $rowExtPrice = $resExtPrice->fetch_assoc()) {
            $extensionPricePerDay = floatval($rowExtPrice['setting_value']);
            if ($extensionPricePerDay < 0) $extensionPricePerDay = 0;
        }

        $resStockThresh = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold' LIMIT 1");
        if ($resStockThresh && $rowStockThresh = $resStockThresh->fetch_assoc()) {
            $lowStockThreshold = intval($rowStockThresh['setting_value']);
            if ($lowStockThreshold < 1) $lowStockThreshold = 1;
        }

        // Auto-heal: Ensure reschedule & lost columns exist on live/online database
        @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS is_rescheduled TINYINT(1) NOT NULL DEFAULT 0");
        @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS rescheduled_at DATETIME DEFAULT NULL");
        @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS original_reserve_date DATE DEFAULT NULL");
        @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS original_return_date DATE DEFAULT NULL");
        @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reschedule_reason TEXT DEFAULT NULL");
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

        // Fetch ALL reservations for stats (newest first)
        $allSql = "SELECT id, user_id, first_name, last_name, order_status, product_status, payment_method, quantity,
                          reserve_date, return_date,
                          is_rescheduled, rescheduled_at, original_reserve_date, original_return_date, reschedule_reason, reserved_at,
                          is_lost, lost_status, lost_reason, lost_selling_price, lost_user_paid, lost_overdue_fee, lost_total_due, lost_payment_proof, lost_requested_at, lost_settled_at
                   FROM reserve_products 
                   ORDER BY reserved_at DESC, id DESC";
        $allResult = $conn->query($allSql);
        if ($allResult) {
            while ($row = $allResult->fetch_assoc()) {
                $allReservations[] = $row;
            }
        }

        // Fetch reservations that overlap with the displayed month (newest reservations first)
        // A reservation overlaps if its reserve_date <= last day of month AND return_date >= first day of month
        $firstOfMonth = sprintf('%04d-%02d-01', $year, $month);
        $lastOfMonth = sprintf('%04d-%02d-%02d', $year, $month, date('t', mktime(0, 0, 0, $month, 1, $year)));
        
        $sql = "SELECT id, user_id, reservation_group_id, reservation_code, product_id, product_name, category, price, total_price, model_path,
                       first_name, last_name, payment_method, payment_option, payment_proof, downpayment_amount, balance_amount, reserve_date, return_date,
                       reserve_time, return_time, quantity, order_status, product_status, reserved_at,
                       is_rescheduled, rescheduled_at, original_reserve_date, original_return_date, reschedule_reason,
                       is_lost, lost_status, lost_reason, lost_selling_price, lost_user_paid, lost_overdue_fee, lost_total_due, lost_payment_proof, lost_requested_at, lost_settled_at
                FROM reserve_products 
                WHERE reserve_date IS NOT NULL AND reserve_date != '0000-00-00' AND reserve_date != ''
                  AND (order_status NOT IN ('cancelled', 'completed') OR order_status IS NULL)
                  AND (product_status != 'buy' OR product_status IS NULL)
                  AND (payment_method != 'buy' OR payment_method IS NULL)
                  AND reserve_date <= ? AND (COALESCE(NULLIF(return_date, '0000-00-00'), NULLIF(return_date, ''), reserve_date) >= ?)
                ORDER BY reserved_at DESC, id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $lastOfMonth, $firstOfMonth);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reservations[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching reservations: " . $e->getMessage());
}

// Helper to validate Y-m-d date format
if (!function_exists('isValidCalDate')) {
    function isValidCalDate($dStr) {
        if (empty($dStr) || $dStr === '0000-00-00' || strlen($dStr) < 10) return false;
        $d = substr($dStr, 0, 10);
        $dt = DateTime::createFromFormat('Y-m-d', $d);
        if (!$dt || $dt->format('Y-m-d') !== $d) return false;
        $y = intval($dt->format('Y'));
        return ($y >= 2000 && $y <= 2100);
    }
}

// Compute all-time stats from ALL reservations
$allTotalCount = count($allReservations);
$allPendingCount = count(array_filter($allReservations, function($r) { return ($r['order_status'] ?? '') === 'pending'; }));
$allConfirmedCount = count(array_filter($allReservations, function($r) { return ($r['order_status'] ?? '') === 'confirmed'; }));
$allCompletedCount = count(array_filter($allReservations, function($r) { return ($r['order_status'] ?? '') === 'completed'; }));
$allRescheduledCount = count(array_filter($allReservations, function($r) {
    $isLost = intval($r['is_lost'] ?? 0) === 1 || in_array(strtolower($r['lost_status'] ?? ''), ['requested', 'settled']);
    return intval($r['is_rescheduled'] ?? 0) === 1 && !$isLost;
}));

// Borrowed records: users who already borrowed items (confirmed or completed rentals)
$borrowedReservations = array_filter($allReservations, function($r) {
    $status = strtolower($r['order_status'] ?? '');
    $pStatus = strtolower($r['product_status'] ?? '');
    $pMethod = strtolower($r['payment_method'] ?? '');
    $isRental = ($pStatus !== 'buy' && $pMethod !== 'buy');
    return $isRental && in_array($status, ['confirmed', 'completed']);
});

$allBorrowedItemsCount = 0;
$borrowedUserKeys = [];
foreach ($borrowedReservations as $br) {
    $allBorrowedItemsCount += intval($br['quantity'] ?? 1);
    $userKey = !empty($br['user_id']) ? 'uid_' . $br['user_id'] : 'name_' . trim(($br['first_name'] ?? '') . ' ' . ($br['last_name'] ?? ''));
    if (!empty($userKey) && $userKey !== 'name_') {
        $borrowedUserKeys[$userKey] = true;
    }
}
$allBorrowedUsersCount = count($borrowedUserKeys);
$allBorrowedCount = count($borrowedReservations);

// Lost item requests from users who borrowed items (lost & overdue)
$lostReservations = array_filter($allReservations, function($r) {
    return intval($r['is_lost'] ?? 0) === 1 || in_array(strtolower($r['lost_status'] ?? ''), ['requested', 'settled']);
});
$allLostRequestsCount = count($lostReservations);
$allLostItemsCount = 0;
$lostUserKeys = [];
foreach ($lostReservations as $lr) {
    $allLostItemsCount += intval($lr['quantity'] ?? 1);
    $userKey = !empty($lr['user_id']) ? 'uid_' . $lr['user_id'] : 'name_' . trim(($lr['first_name'] ?? '') . ' ' . ($lr['last_name'] ?? ''));
    if (!empty($userKey) && $userKey !== 'name_') {
        $lostUserKeys[$userKey] = true;
    }
}
$allLostUsersCount = count($lostUserKeys);

// Daily (today) calculations based on Philippines time (Asia/Manila)
try {
    $phTz = new DateTimeZone('Asia/Manila');
    $phTodayDate = (new DateTime('now', $phTz))->format('Y-m-d');
} catch (Exception $e) {
    $phTodayDate = date('Y-m-d');
}

$isTodayPh = function($dtStr) use ($phTodayDate) {
    if (empty($dtStr) || $dtStr === '0000-00-00' || $dtStr === '0000-00-00 00:00:00') {
        return false;
    }
    return (substr($dtStr, 0, 10) === $phTodayDate);
};

$todayPendingCount = 0;
$todayConfirmedCount = 0;
$todayRescheduledCount = 0;
$todayBorrowedItemsCount = 0;
$todayLostItemsCount = 0;

foreach ($allReservations as $r) {
    $qty = intval($r['quantity'] ?? 1);
    if ($qty < 1) $qty = 1;
    $status = strtolower($r['order_status'] ?? '');
    $resDate = !empty($r['reserved_at']) ? $r['reserved_at'] : ($r['reserve_date'] ?? '');
    
    // Pending today (in Philippines time)
    if ($status === 'pending' && $isTodayPh($resDate)) {
        $todayPendingCount += $qty;
    }
    
    // Confirmed today
    if ($status === 'confirmed' && $isTodayPh($resDate)) {
        $todayConfirmedCount += $qty;
    }
    
    // Rescheduled today
    $isLostResched = intval($r['is_lost'] ?? 0) === 1 || in_array(strtolower($r['lost_status'] ?? ''), ['requested', 'settled']);
    $isResched = intval($r['is_rescheduled'] ?? 0) === 1;
    $reschedDt = !empty($r['rescheduled_at']) ? $r['rescheduled_at'] : $resDate;
    if ($isResched && !$isLostResched && $isTodayPh($reschedDt)) {
        $todayRescheduledCount += $qty;
    }
    
    // Users borrowed today (rental item active/borrowed today)
    $pStatus = strtolower($r['product_status'] ?? '');
    $pMethod = strtolower($r['payment_method'] ?? '');
    $isRental = ($pStatus !== 'buy' && $pMethod !== 'buy');
    if ($isRental && in_array($status, ['confirmed', 'completed'])) {
        $borrowDt = !empty($r['reserve_date']) ? $r['reserve_date'] : $resDate;
        if ($isTodayPh($borrowDt) || $isTodayPh($resDate)) {
            $todayBorrowedItemsCount += $qty;
        }
    }
    
    // Lost & Overdue today
    $isLost = intval($r['is_lost'] ?? 0) === 1 || in_array(strtolower($r['lost_status'] ?? ''), ['requested', 'settled']);
    $lostDt = !empty($r['lost_requested_at']) ? $r['lost_requested_at'] : (!empty($r['lost_settled_at']) ? $r['lost_settled_at'] : $resDate);
    if ($isLost && $isTodayPh($lostDt)) {
        $todayLostItemsCount += $qty;
    }
}

// Calendar calculations
$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDayOfMonth);
$firstDayOfWeek = date('w', $firstDayOfMonth); // 0 = Sunday, 6 = Saturday

// Month names
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear = $year - 1;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear = $year + 1;
}

// Group reservations by date — spread across reserve_date to return_date (excluding buy orders)
$reservationsByDate = [];
foreach ($reservations as $reservation) {
    if (($reservation['order_status'] ?? '') === 'cancelled' || ($reservation['order_status'] ?? '') === 'completed') continue;
    if (($reservation['product_status'] ?? '') === 'buy' || ($reservation['payment_method'] ?? '') === 'buy') continue;
    $reserveDate = $reservation['reserve_date'] ?? '';
    $returnDate = $reservation['return_date'] ?? '';
    if (!isValidCalDate($reserveDate)) continue;
    if (!isValidCalDate($returnDate) || $returnDate < $reserveDate) {
        $returnDate = $reserveDate;
    }
    
    $start = new DateTime(substr($reserveDate, 0, 10));
    $end = new DateTime(substr($returnDate, 0, 10));
    
    // Safety cap max day difference to 60 days to avoid runaway memory usage
    if ($start->diff($end)->days > 60) {
        $end = (clone $start)->modify('+60 days');
    }
    $end->modify('+1 day'); // inclusive
    
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    foreach ($period as $dayObj) {
        $dateKey = $dayObj->format('Y-m-d');
        $reservationsByDate[$dateKey][] = $reservation;
    }
}

// Compute fully-booked dates (reserved item count >= stock for that product on that date)
// Build a map: date => [product_id => total_items_reserved]
// Actual item count = 1 (main row) + COUNT(reserve_products_items rows)
$reservationItemCounts = [];
if (!empty($reservations)) {
    $resIds = array_column($reservations, 'id');
    $resIds = array_filter(array_map('intval', $resIds), function($v) { return $v > 0; });
    if (!empty($resIds)) {
        try {
            $placeholders = implode(',', array_fill(0, count($resIds), '?'));
            $types = str_repeat('i', count($resIds));
            $itemsSql = "SELECT reserve_product_id, COUNT(*) as item_count FROM reserve_products_items WHERE reserve_product_id IN ($placeholders) GROUP BY reserve_product_id";
            $itemsStmt = $conn->prepare($itemsSql);
            if ($itemsStmt) {
                $itemsStmt->bind_param($types, ...$resIds);
                $itemsStmt->execute();
                $itemsResult = $itemsStmt->get_result();
                while ($itemRow = $itemsResult->fetch_assoc()) {
                    $reservationItemCounts[intval($itemRow['reserve_product_id'])] = intval($itemRow['item_count']);
                }
                $itemsStmt->close();
            }
        } catch (Throwable $e) {
            error_log("Error querying reserve_products_items: " . $e->getMessage());
        }
    }
}

$dateProductQty = [];
foreach ($reservations as $reservation) {
    if (($reservation['order_status'] ?? '') === 'cancelled' || ($reservation['order_status'] ?? '') === 'completed') continue;
    if (($reservation['product_status'] ?? '') === 'buy' || ($reservation['payment_method'] ?? '') === 'buy') continue;
    
    $pid = intval($reservation['product_id'] ?? 0);
    if ($pid <= 0) continue;

    // Actual item count = 1 (main) + additional items from reserve_products_items
    $resId = intval($reservation['id'] ?? 0);
    $additionalItems = $reservationItemCounts[$resId] ?? 0;
    $itemCount = 1 + $additionalItems;
    
    $reserveDate = $reservation['reserve_date'] ?? '';
    $returnDate = $reservation['return_date'] ?? '';
    if (!isValidCalDate($reserveDate)) continue;
    if (!isValidCalDate($returnDate) || $returnDate < $reserveDate) {
        $returnDate = $reserveDate;
    }
    
    $start = new DateTime(substr($reserveDate, 0, 10));
    $end = new DateTime(substr($returnDate, 0, 10));
    
    if ($start->diff($end)->days > 60) {
        $end = (clone $start)->modify('+60 days');
    }
    $end->modify('+1 day');
    
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    foreach ($period as $dayObj) {
        $dateKey = $dayObj->format('Y-m-d');
        if (!isset($dateProductQty[$dateKey])) {
            $dateProductQty[$dateKey] = [];
        }
        if (!isset($dateProductQty[$dateKey][$pid])) {
            $dateProductQty[$dateKey][$pid] = 0;
        }
        $dateProductQty[$dateKey][$pid] += $itemCount;
    }
}

// Fetch all gowns/products for filter & walk-in selection
$allGowns = [];
$productStocks = [];
$categorySizeLabelsMap = [];
$allCategoryNames = [];
if ($conn) {
    // Fetch categories in order
    $catRes = $conn->query("SELECT category_name, size_label_1, size_label_2, size_label_3, size_label_4, size_label_5, size_label_6, size_label_7, size_label_8, size_label_9, size_label_10 FROM categories ORDER BY id ASC");
    if ($catRes) {
        while ($crow = $catRes->fetch_assoc()) {
            $catNameRaw = trim($crow['category_name'] ?? '');
            if (!empty($catNameRaw)) {
                $allCategoryNames[] = $catNameRaw;
            }
            $catName = strtolower($catNameRaw);
            $labels = [];
            for ($i = 1; $i <= 10; $i++) {
                $lbl = trim($crow['size_label_' . $i] ?? '');
                if (!empty($lbl)) {
                    $labels[] = ['index' => $i, 'label' => $lbl];
                }
            }
            if (!empty($catName)) {
                $categorySizeLabelsMap[$catName] = $labels;
            }
        }
    }

    $gownsRes = $conn->query("SELECT id, name, category, price, discount, image_path, image_path2, image_path3, model_path, stock, color, description FROM gowns ORDER BY name ASC");
    if ($gownsRes) {
        while ($grow = $gownsRes->fetch_assoc()) {
            $allGowns[] = $grow;
            $productStocks[intval($grow['id'])] = intval($grow['stock']);
            if (!empty($grow['category']) && !in_array($grow['category'], $allCategoryNames)) {
                $allCategoryNames[] = $grow['category'];
            }
        }
    }
}

// Build detailed availability map per date and per product
$dateProductAvailability = [];
foreach ($dateProductQty as $dateKey => $pidMap) {
    foreach ($pidMap as $pid => $totalQty) {
        $stock = $productStocks[$pid] ?? 1;
        $dateProductAvailability[$dateKey][$pid] = [
            'reserved' => $totalQty,
            'stock' => $stock,
            'is_full' => ($totalQty >= $stock)
        ];
    }
}

// A date is only globally fully booked if ALL products in the shop are booked out on that day
$fullyBookedDates = [];
$totalProductCount = count($allGowns);
if ($totalProductCount > 0) {
    foreach ($dateProductQty as $dateKey => $pidMap) {
        $fullCount = 0;
        foreach ($pidMap as $pid => $totalQty) {
            $stock = $productStocks[$pid] ?? 1;
            if ($totalQty >= $stock) {
                $fullCount++;
            }
        }
        if ($fullCount >= $totalProductCount) {
            $fullyBookedDates[$dateKey] = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-modern.css">
    <link rel="stylesheet" href="../css/admin_mobile_css.css">
    <script src="../js/dark-mode.js"></script>
    <script>
    // Preload audio file to eliminate delay
    const buttonClickAudio = new Audio('../ringtone/button_click.MP3');
    buttonClickAudio.volume = 0.3;
    buttonClickAudio.preload = 'auto';
    
    // Button click sound function
    function playButtonClickSound() {
        buttonClickAudio.currentTime = 0; // Reset to start
        buttonClickAudio.play().catch(e => console.log('Audio play failed:', e));
    }

    // Add click sound to all buttons and links
    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('button, .btn, .btn-submit, .btn-back, a[href]');
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                // Don't play sound for sidebar links (handled by sidebar.php)
                if (!this.closest('.sidebar')) {
                    playButtonClickSound();
                }
            });
        });

        syncCalendarHeight();
    });

    // Dynamically adjust calendar day height responsive to screen height
    function syncCalendarHeight() {
        const calDays = document.querySelectorAll('.calendar-day');
        if (!calDays.length) return;

        if (window.innerWidth <= 768) {
            calDays.forEach(d => d.style.minHeight = '');
            return;
        }

        // Available viewport height minus top navbar (70px), stats (~52px), calendar header (~45px), day headers (~35px), and page padding (~45px)
        const overhead = 247;
        const availableHeight = window.innerHeight - overhead;
        const numWeeks = Math.ceil(calDays.length / 7) || 5;
        const targetDayHeight = Math.max(55, Math.floor(availableHeight / numWeeks));

        calDays.forEach(d => {
            d.style.minHeight = targetDayHeight + 'px';
        });
    }

    window.addEventListener('load', syncCalendarHeight);
    window.addEventListener('resize', syncCalendarHeight);
    </script>
    <style>
        .calendar-container {
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            border-radius: 12px;
            padding: 10px 14px;
            border: 1px solid #333;
            box-shadow: inset 0 0 25px rgba(255, 215, 0, 0.10);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            border: 1px solid #222;
        }
        
        .calendar-nav {
            display: flex;
            gap: 8px;
        }
        
        .calendar-nav a {
            background: rgba(0,0,0,0.3);
            border: none;
            color: #ffd700;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 13px;
        }
        
        .calendar-nav a:hover {
            background: rgba(124, 99, 0, 0.3);
            transform: translateY(-1px);
        }
        
        .calendar-title {
            font-size: 1.1rem;
            font-weight: 500;
            color: #ffd700;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #222;
            border: 1px solid #222;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .calendar-day-header {
            background: rgba(255, 255, 255, 0.03);
            padding: 10px 5px;
            text-align: center;
            font-weight: 500;
            color: #7c6300;
            font-size: 0.8rem;
        }
        
        .calendar-day {
            background: #0d0d0d;
            color: #ffffff;
            min-height: clamp(55px, calc((100vh - 250px) / 6), 110px);
            padding: 6px;
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }
        
        .calendar-day:hover {
            background: #1a1a1a;
            border-color: rgba(124, 99, 0, 0.5);
        }
        
        .calendar-day.other-month {
            background: #080808;
            color: #333;
        }
        
        .calendar-day.today {
            background: rgba(124, 99, 0, 0.3);
            color: #FFD700;
            font-weight: 600;
            border-color: #7c6300;
        }
        
        .calendar-day.fully-booked {
            background: rgba(244, 67, 54, 0.15);
            border-color: rgba(244, 67, 54, 0.4);
        }
        
        .calendar-day.fully-booked .calendar-day-number {
            color: #f44336;
        }

        /* Reservation Details Panel Styles */
        .res-detail-section {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid #333;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 14px;
        }

        .res-detail-qr-btn:hover {
            border-color: #ffd700 !important;
            background: #2a2a30 !important;
            transform: translateY(-1px);
        }
        .res-detail-qr-btn:active {
            transform: scale(0.99);
        }

        .res-detail-section-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #FFD700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .res-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .res-detail-field {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .res-detail-label {
            font-size: 0.7rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .res-detail-value {
            font-size: 0.9rem;
            color: #e0e0e0;
            font-weight: 500;
        }

        .res-detail-value.gold {
            color: #FFD700;
        }

        .res-detail-full {
            grid-column: 1 / -1;
        }

        .res-detail-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 0;
            background: none !important;
            border: none !important;
            border-radius: 0;
            box-shadow: none !important;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: capitalize;
        }

        .res-detail-status-badge.pending {
            background: none !important;
            color: #ff9800;
            border: none !important;
        }

        .res-detail-status-badge.confirmed {
            background: none !important;
            color: #60a5fa;
            border: none !important;
        }

        .res-detail-status-badge.completed {
            background: none !important;
            color: #4CAF50;
            border: none !important;
        }

        .res-detail-status-badge.cancelled {
            background: none !important;
            color: #f44336;
            border: none !important;
        }

        .res-detail-status-badge.rescheduled {
            background: none !important;
            color: #fbbf24;
            border: none !important;
        }

        .card-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 0;
            background: none !important;
            border: none !important;
            border-radius: 0;
            box-shadow: none !important;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-type-badge.reserve {
            background: none !important;
            color: #ffc107;
            border: none !important;
        }

        .card-type-badge.buy {
            background: none !important;
            color: #ce93d8;
            border: none !important;
        }

        .card-prod-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 4px 0;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.07);
        }

        .card-prod-row:last-child {
            border-bottom: none;
        }

        /* Custom Slide-in Notification Toast (Top Right with OK button) */
        .custom-slide-toast {
            position: fixed;
            top: 24px;
            right: -450px;
            z-index: 1000050;
            background: #18191f;
            border: 1px solid rgba(255, 215, 0, 0.4);
            border-radius: 12px;
            padding: 14px 18px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.7), 0 0 25px rgba(255, 215, 0, 0.15);
            min-width: 320px;
            max-width: 420px;
            transition: right 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
            opacity: 0;
            pointer-events: none;
        }

        .custom-slide-toast.show {
            right: 24px;
            opacity: 1;
            pointer-events: auto;
        }

        .custom-toast-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .custom-toast-icon-wrap {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(34, 197, 94, 0.18);
            border: 1px solid rgba(34, 197, 94, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #22c55e;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .custom-toast-icon-wrap.error {
            background: rgba(239, 68, 68, 0.18);
            border-color: rgba(239, 68, 68, 0.4);
            color: #ef4444;
        }

        .custom-toast-icon-wrap.info {
            background: rgba(33, 150, 243, 0.18);
            border-color: rgba(33, 150, 243, 0.4);
            color: #2196f3;
        }

        .custom-toast-icon-wrap.warning {
            background: rgba(255, 193, 7, 0.18);
            border-color: rgba(255, 193, 7, 0.4);
            color: #ffc107;
        }

        .custom-toast-body {
            flex: 1;
        }

        .custom-toast-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 2px;
        }

        .custom-toast-msg {
            font-size: 0.8rem;
            color: #ccc;
            line-height: 1.3;
        }

        .custom-toast-btns {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .custom-toast-ok-btn {
            background: #FFD700;
            color: #111;
            border: none;
            border-radius: 6px;
            padding: 7px 15px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            letter-spacing: 0.5px;
        }

        .custom-toast-ok-btn:hover {
            background: #ffea75;
            transform: scale(1.05);
        }

        .custom-toast-cancel-btn {
            background: #2b2d35;
            color: #bbb;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .custom-toast-cancel-btn:hover {
            background: #383b45;
            color: #fff;
        }

        .size-item-card {
            background: rgba(255, 215, 0, 0.05);
            border: 1px solid rgba(255, 215, 0, 0.15);
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 8px;
        }

        .size-item-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .size-item-number {
            background: #7c6300;
            color: #FFD700;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .size-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .size-badge {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid #444;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 0.78rem;
            color: #ccc;
        }

        .size-badge.empty {
            color: #555;
            font-style: italic;
        }

        .res-detail-actions {
            display: flex;
            gap: 10px;
            margin-top: 6px;
            flex-wrap: wrap;
        }

        .res-action-btn {
            flex: 1;
            min-width: 100px;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .res-action-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .res-action-btn-confirm {
            background: rgba(33, 150, 243, 0.2);
            color: #2196f3;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }

        .res-action-btn-confirm:hover:not(:disabled) {
            background: rgba(33, 150, 243, 0.3);
        }

        .res-action-btn-complete {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .res-action-btn-complete:hover:not(:disabled) {
            background: rgba(76, 175, 80, 0.3);
        }

        .res-action-btn-cancel {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        .res-action-btn-cancel:hover:not(:disabled) {
            background: rgba(244, 67, 54, 0.3);
        }

        /* Stat Filter Reservation Cards */
        .stat-reservation-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .stat-reservation-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid #333;
            border-radius: 10px;
            padding: 14px 16px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .stat-reservation-card:hover {
            background: rgba(255, 215, 0, 0.05);
            border-color: rgba(255, 215, 0, 0.2);
            transform: translateY(-1px);
        }

        .stat-reservation-top {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .stat-reservation-name {
            color: #FFD700;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .stat-reservation-sub {
            color: #aaa;
            font-size: 0.8rem;
        }

        .stat-reservation-customer {
            color: #888;
            font-size: 0.75rem;
            margin-top: 3px;
        }

        .stat-reservation-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 0;
            background: none !important;
            border: none !important;
            border-radius: 0;
            box-shadow: none !important;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .stat-reservation-status.pending {
            background: none !important;
            color: #ff9800;
            border: none !important;
        }

        .stat-reservation-status.confirmed {
            background: none !important;
            color: #60a5fa;
            border: none !important;
        }

        .stat-reservation-status.completed {
            background: none !important;
            color: #4CAF50;
            border: none !important;
        }

        .stat-reservation-status.cancelled {
            background: none !important;
            color: #f44336;
            border: none !important;
        }

        .stat-reservation-status.rescheduled {
            background: none !important;
            color: #fbbf24;
            border: none !important;
        }

        .stat-reservation-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            font-size: 0.8rem;
        }

        .stat-reservation-info-item {
            display: flex;
            gap: 4px;
            color: #ccc;
            flex-wrap: wrap;
            align-items: baseline;
        }

        .stat-reservation-info-label {
            color: #666;
            font-size: 0.75rem;
        }

        .stat-reservation-info-value {
            color: #ddd;
            font-weight: 500;
        }

        .stat-filter-count {
            color: #aaa;
            font-size: 0.8rem;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #222;
        }
        
        .calendar-day-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3px;
            line-height: 1;
        }
        
        .calendar-day-number {
            font-weight: 500;
            margin-bottom: 0;
            font-size: 0.8rem;
        }

        .cal-more-text {
            font-size: 0.65rem;
            font-weight: 700;
            color: #ffd700;
            cursor: pointer;
            line-height: 1;
            user-select: none;
            transition: color 0.2s ease, opacity 0.2s ease;
            white-space: nowrap;
            margin-left: auto;
        }

        .cal-more-text:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        
        .reservation-item {
            background: #28a745;
            color: white;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 10px;
            margin: 1px 0;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2px;
            box-sizing: border-box;
        }
        
        .reservation-item:hover {
            background: #218838;
            transform: scale(1.03);
        }
        
        .reservation-item.pending {
            background: rgba(255, 152, 0, 0.3);
            color: #ff9800;
        }
        
        .reservation-item.confirmed {
            background: rgba(33, 150, 243, 0.3);
            color: #2196f3;
        }
        
        .reservation-item.completed {
            background: rgba(76, 175, 80, 0.3);
            color: #4CAF50;
        }
        
        .reservation-item.cancelled {
            background: rgba(244, 67, 54, 0.3);
            color: #f44336;
        }
        
        .status-management {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        
        .status-btn {
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .status-btn:hover { transform: translateY(-1px); }
        
        .status-btn-confirm {
            background: #28a745;
            color: white;
        }
        
        .status-btn-complete {
            background: #17a2b8;
            color: white;
        }
        
        .status-btn-cancel {
            background: #dc3545;
            color: white;
        }
        
        .status-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none;
        }
        
        .cal-stats-overview {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-bottom: 2px;
        }
        
        .cal-stat-item {
            border-radius: 10px;
            padding: 6px 10px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 0 20px rgba(255, 215, 0, 0.22);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .cal-stat-item:hover {
            box-shadow: inset 0 0 22px rgba(255, 215, 0, 0.38);
            transform: translateY(-2px);
        }

        /* Duration Modal Styles */
        .duration-modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.82);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 100000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .duration-modal-backdrop.active {
            display: flex;
            opacity: 1;
        }

        .duration-modal-card {
            background: #18191c;
            border: 1px solid rgba(108, 212, 136, 0.35);
            border-radius: 18px;
            padding: 28px;
            width: 92%;
            max-width: 440px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7), 0 0 35px rgba(108, 212, 136, 0.15);
            color: #fff;
            transform: scale(0.92);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
        }

        .duration-modal-backdrop.active .duration-modal-card {
            transform: scale(1);
        }

        .duration-preset-chip {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 500;
            background: #24262b;
            border: 1px solid #363940;
            color: #ccc;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .duration-preset-chip:hover {
            background: rgba(108, 212, 136, 0.15);
            border-color: #6cd488;
            color: #6cd488;
            transform: translateY(-1px);
        }

        .duration-preset-chip.active {
            background: #6cd488;
            border-color: #6cd488;
            color: #0e1e12;
            font-weight: 700;
            box-shadow: 0 0 12px rgba(108, 212, 136, 0.4);
        }

        .duration-slider-bar {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #2b2e36;
            outline: none;
            transition: background 0.2s;
        }

        .duration-slider-bar::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #6cd488;
            cursor: pointer;
            box-shadow: 0 0 10px rgba(108, 212, 136, 0.7);
            border: 2px solid #fff;
            transition: transform 0.15s ease;
        }

        .duration-slider-bar::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }

        .penalty-preset-chip {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 500;
            background: #24262b;
            border: 1px solid #363940;
            color: #ccc;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .penalty-preset-chip:hover {
            background: rgba(255, 215, 0, 0.15);
            border-color: #ffd700;
            color: #ffd700;
            transform: translateY(-1px);
        }

        .penalty-preset-chip.active {
            background: #ffd700;
            border-color: #ffd700;
            color: #111;
            font-weight: 700;
            box-shadow: 0 0 12px rgba(255, 215, 0, 0.4);
        }

        .lowstock-preset-chip {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 500;
            background: #24262b;
            border: 1px solid #363940;
            color: #ccc;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .lowstock-preset-chip:hover {
            background: rgba(255, 152, 0, 0.15);
            border-color: #ff9800;
            color: #ff9800;
            transform: translateY(-1px);
        }

        .lowstock-preset-chip.active {
            background: #ff9800;
            border-color: #ff9800;
            color: #1a0f00;
            font-weight: 700;
            box-shadow: 0 0 12px rgba(255, 152, 0, 0.4);
        }

        .lowstock-slider-bar {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #2b2e36;
            outline: none;
            transition: background 0.2s;
        }

        .lowstock-slider-bar::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ff9800;
            cursor: pointer;
            box-shadow: 0 0 10px rgba(255, 152, 0, 0.7);
            border: 2px solid #fff;
            transition: transform 0.15s ease;
        }

        .lowstock-slider-bar::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }

        .penalty-slider-bar {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #2b2e36;
            outline: none;
            transition: background 0.2s;
        }

        .penalty-slider-bar::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ffd700;
            cursor: pointer;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.7);
            border: 2px solid #fff;
            transition: transform 0.15s ease;
        }

        .extension-preset-chip, .extension-fee-preset-chip {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 500;
            background: #24262b;
            border: 1px solid #363940;
            color: #ccc;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .extension-preset-chip:hover, .extension-fee-preset-chip:hover {
            background: rgba(56, 189, 248, 0.15);
            border-color: #38bdf8;
            color: #38bdf8;
            transform: translateY(-1px);
        }

        .extension-preset-chip.active, .extension-fee-preset-chip.active {
            background: #38bdf8;
            border-color: #38bdf8;
            color: #0c2333;
            font-weight: 700;
            box-shadow: 0 0 12px rgba(56, 189, 248, 0.4);
        }

        .extension-slider-bar {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #2b2e36;
            outline: none;
            transition: background 0.2s;
        }

        .extension-slider-bar::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #38bdf8;
            cursor: pointer;
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.7);
            border: 2px solid #fff;
            transition: transform 0.15s ease;
        }

        .penalty-slider-bar::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }

        /* Toast notification */
        .cal-toast {
            position: fixed;
            top: 25px;
            right: 25px;
            background: #1e2025;
            border: 1px solid #6cd488;
            color: #fff;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.6), 0 0 20px rgba(108, 212, 136, 0.25);
            z-index: 200000;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            transform: translateX(120%);
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .cal-toast.show {
            transform: translateX(0);
        }
        
        .cal-stat-item:hover {
            box-shadow: inset 0 0 22px rgba(255, 215, 0, 0.38);
        }
        
        .cal-stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 6px;
        }
        
        .cal-stat-title {
            color: #666666;
            font-size: 0.72rem;
            font-weight: 400;
            letter-spacing: 0.4px;
        }
        
        .cal-stat-content {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }
        
        .cal-stat-number {
            color: #cccccc;
            font-size: 1.15rem;
            font-weight: 500;
            line-height: 1.15;
            margin: 0;
        }

        .cal-stat-number.stat-num-pending {
            color: #ffd700 !important;
            text-shadow: 0 0 12px rgba(255, 215, 0, 0.4);
        }

        .cal-stat-number.stat-num-confirmed {
            color: #ffd700 !important;
            text-shadow: 0 0 12px rgba(255, 215, 0, 0.4);
        }

        .cal-stat-number.stat-num-completed {
            color: #ffd700 !important;
            text-shadow: 0 0 12px rgba(255, 215, 0, 0.4);
        }

        .cal-stat-number.stat-num-rescheduled {
            color: #ffd700 !important;
            text-shadow: 0 0 12px rgba(255, 215, 0, 0.4);
        }

        .cal-stat-number.stat-num-borrowed {
            color: #ffd700 !important;
            text-shadow: 0 0 12px rgba(255, 215, 0, 0.4);
        }

        .cal-stat-number.stat-num-lost {
            color: #ffd700 !important;
            text-shadow: 0 0 12px rgba(255, 215, 0, 0.4);
        }
        
        .cal-stat-change {
            font-size: 0.9rem;
            font-weight: 600;
            color: #22c55e !important;
            align-self: flex-start;
        }
        
        .legend {
            display: flex;
            gap: 12px;
            margin: 6px 0;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            color: #aaa;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
        
        .day-short { display: none; }

        /* Mobile reservation count badge — hidden on desktop, shown on mobile */
        .mobile-res-count { display: none; }

        @media (max-width: 1200px) and (min-width: 901px) {
            .cal-stats-overview {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
        }
        
        @media (max-width: 900px) {
            .day-full { display: none !important; }
            .day-short { display: inline !important; font-size: 0.65rem !important; font-weight: 600 !important; }

            .shell.dashboard-shell {
                width: 100% !important;
                max-width: 100vw !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important;
                margin-left: 0 !important;
                padding-left: 10px !important;
                padding-right: 10px !important;
            }

            .cal-stats-overview {
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 8px !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }
            
            .cal-stats-overview .cal-stat-item {
                min-width: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
                box-sizing: border-box !important;
                padding: 8px 10px !important;
                overflow: hidden !important;
            }

            .cal-stats-overview .cal-stat-item:last-child {
                grid-column: auto !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .cal-stat-header {
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 2px !important;
                width: 100% !important;
                min-width: 0 !important;
            }

            .cal-stat-title {
                font-size: 0.70rem !important;
                line-height: 1.25 !important;
                word-break: break-word !important;
                white-space: normal !important;
                color: #aaa !important;
                width: 100% !important;
            }

            .cal-stat-change {
                font-size: 0.78rem !important;
                color: #22c55e !important;
                white-space: nowrap !important;
            }

            .cal-stat-number {
                font-size: 1.15rem !important;
                line-height: 1.15 !important;
                margin: 2px 0 0 0 !important;
            }

            #rentalDurationDisplay {
                font-size: 0.90rem !important;
                flex-wrap: wrap !important;
                gap: 2px 5px !important;
                line-height: 1.3 !important;
            }
            
            .calendar-container {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important;
                padding: 10px 8px !important;
            }

            .calendar-header {
                display: flex !important;
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 8px !important;
            }

            .calendar-header > div {
                width: 100% !important;
                justify-content: space-between !important;
            }

            .cal-product-filter-wrap {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            .cal-product-filter-select {
                width: 100% !important;
                max-width: 100% !important;
            }

            .btn-walk-in {
                width: 100% !important;
                justify-content: center !important;
            }

            .legend {
                gap: 6px 10px !important;
                font-size: 0.72rem !important;
            }

            .calendar-grid {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            .calendar-day {
                min-height: 40px !important;
                padding: 2px !important;
            }
            
            .calendar-day-header {
                padding: 6px 1px !important;
                font-size: 0.65rem !important;
            }
            
            .reservation-item {
                font-size: 8.5px !important;
                padding: 1px 2px !important;
            }

            .cal-more-text {
                font-size: 8px;
            }
            
            .calendar-title {
                font-size: 0.95rem;
            }
        }

        /* ── All Slide Panels Transformed into Big Centered Modals ── */
        .slide-panel {
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            right: auto !important;
            bottom: auto !important;
            width: 920px !important;
            min-width: unset !important;
            max-width: 94vw !important;
            height: auto !important;
            max-height: 88vh !important;
            background: #14151a !important;
            border: 1.5px solid rgba(201, 170, 46, 0.5) !important;
            border-radius: 20px !important;
            box-shadow: 0 25px 85px rgba(0, 0, 0, 0.92), 0 0 45px rgba(201, 170, 46, 0.18) !important;
            z-index: 100000 !important;
            overflow: hidden !important;
            display: none !important;
            transition: none !important;
            animation: calendarModalZoomIn 0.22s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }

        .slide-panel.slide-panel-open {
            display: flex !important;
            flex-direction: column !important;
            right: auto !important;
            transform: translate(-50%, -50%) !important;
        }

        .slide-panel-content {
            height: auto !important;
            max-height: 88vh !important;
            flex: 1 1 auto !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
        }

        .slide-panel-header {
            flex-shrink: 0 !important;
            padding: 18px 24px !important;
            background: linear-gradient(180deg, #1f2027, #16171d) !important;
            border-bottom: 1px solid rgba(255, 215, 0, 0.22) !important;
            border-top-left-radius: 20px !important;
            border-top-right-radius: 20px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }

        .slide-panel-header h2 {
            font-size: 1.15rem !important;
            font-weight: 700 !important;
            color: #ffd700 !important;
            margin: 0 !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
        }

        .slide-panel-header .close-panel-btn {
            background: rgba(255, 255, 255, 0.08) !important;
            border: 1px solid rgba(255, 255, 255, 0.16) !important;
            color: #e0e0e0 !important;
            font-size: 16px !important;
            width: 34px !important;
            height: 34px !important;
            border-radius: 8px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            padding: 0 !important;
        }

        .slide-panel-header .close-panel-btn:hover {
            background: rgba(239, 68, 68, 0.25) !important;
            border-color: #ef4444 !important;
            color: #ff6b6b !important;
            transform: scale(1.08) !important;
        }

        .slide-panel-body {
            flex: 1 1 auto !important;
            height: auto !important;
            max-height: calc(88vh - 72px) !important;
            overflow-y: auto !important;
            padding: 24px 28px !important;
            box-sizing: border-box !important;
        }

        /* Modal Backdrop Overlay */
        .modal-backdrop {
            position: fixed !important;
            inset: 0 !important;
            background: rgba(0, 0, 0, 0.8) !important;
            backdrop-filter: blur(8px) !important;
            -webkit-backdrop-filter: blur(8px) !important;
            z-index: 99990 !important;
            display: none;
            cursor: default !important;
            animation: fadeInCalendarBackdrop 0.2s ease-out;
        }

        body.slide-panel-open .modal-backdrop {
            display: block !important;
        }

        /* Specific Big Modal Widths & Z-Indexes */
        #statFilterModal {
            width: 920px !important;
            z-index: 100001 !important;
        }

        #dayModal {
            width: 900px !important;
            z-index: 100001 !important;
        }

        #reservationModal {
            width: 1020px !important;
            z-index: 100003 !important;
        }

        #cancellationModal {
            width: 780px !important;
            z-index: 100006 !important;
            border: 1.5px solid rgba(244, 67, 54, 0.6) !important;
            box-shadow: 0 25px 85px rgba(0, 0, 0, 0.95), 0 0 45px rgba(244, 67, 54, 0.25) !important;
        }

        #cancellationModal .slide-panel-header {
            background: linear-gradient(180deg, #242022, #181516) !important;
            border-bottom: 1px solid rgba(244, 67, 54, 0.35) !important;
        }

        .cancellation-backdrop {
            position: fixed !important;
            inset: 0 !important;
            background: rgba(0, 0, 0, 0.82) !important;
            backdrop-filter: blur(8px) !important;
            -webkit-backdrop-filter: blur(8px) !important;
            z-index: 100005 !important;
            display: none;
            cursor: default !important;
        }
        .cancellation-backdrop.active {
            display: block !important;
        }

        #durationModal {
            width: 740px !important;
            z-index: 100002 !important;
        }

        #uploadIdModal {
            width: 720px !important;
            z-index: 100006 !important;
        }

        #walkInModal {
            width: 1040px !important;
            z-index: 100002 !important;
        }

        .product-picker-modal-overlay {
            z-index: 100010 !important;
        }

        .day-reservations-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }

        @keyframes calendarModalZoomIn {
            from {
                opacity: 0;
                transform: translate(-50%, -46%) scale(0.94);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        @keyframes fadeInCalendarBackdrop {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .cancel-reason-select {
            width: 100%;
            box-sizing: border-box;
            background: #18191f;
            border: 1px solid #383b45;
            border-radius: 8px;
            color: #fff;
            padding: 12px 36px 12px 14px;
            font-size: 0.9rem;
            outline: none;
            cursor: pointer;
            transition: border 0.2s, box-shadow 0.2s;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23FFD700'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 18px;
        }
        .cancel-reason-select:focus {
            border-color: #f44336;
            box-shadow: 0 0 0 2px rgba(244, 67, 54, 0.2);
        }
        .cancel-reason-select option {
            background: #121316;
            color: #fff;
            padding: 8px;
        }

        .cancel-quick-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
            margin-bottom: 4px;
        }
        .cancel-quick-chip {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #bbb;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.74rem;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }
        .cancel-quick-chip:hover {
            background: rgba(244, 67, 54, 0.15);
            border-color: rgba(244, 67, 54, 0.4);
            color: #fff;
        }
        .cancel-quick-chip.active {
            background: rgba(244, 67, 54, 0.25);
            border-color: #f44336;
            color: #ff8888;
            font-weight: 600;
        }

        /* Product Filter & Walk-In Button (matching gowns.php Update Product & Delete Product CSS box) */
        .cal-product-filter-wrap {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #000000;
            color: white;
            border: 1px solid #7c6300;
            border-radius: 8px;
            padding: 8px 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: inset 0 0 15px rgba(212, 175, 55, 0.8);
            box-sizing: border-box;
        }

        .cal-product-filter-wrap:hover,
        .cal-product-filter-wrap:focus-within {
            transform: translateY(-2px);
            background: #1a1a1a;
            border-color: #ffd700;
            box-shadow: inset 0 0 20px rgba(212, 175, 55, 1);
        }

        .cal-product-filter-select {
            background: transparent;
            color: white;
            font-weight: 600;
            font-size: 14px;
            border: none;
            outline: none;
            cursor: pointer;
            max-width: 260px;
        }

        .cal-product-filter-select option {
            background: #1a1a1a;
            color: #fff;
        }

        .btn-walk-in {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 18px;
            background: #000000;
            color: white;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid #7c6300;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: inset 0 0 15px rgba(212, 175, 55, 0.8);
            box-sizing: border-box;
            line-height: normal;
        }

        .btn-walk-in:hover {
            transform: translateY(-2px);
            background: #1a1a1a;
            border-color: #ffd700;
            color: #ffffff;
            box-shadow: inset 0 0 20px rgba(212, 175, 55, 1);
        }

        .btn-walk-in:active {
            transform: translateY(0);
        }

        .btn-walk-in-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            background: #000000;
            color: white;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid #7c6300;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 14px;
            box-shadow: inset 0 0 15px rgba(212, 175, 55, 0.8);
        }

        .btn-walk-in-secondary:hover {
            transform: translateY(-2px);
            background: #1a1a1a;
            border-color: #ffd700;
            color: #ffffff;
            box-shadow: inset 0 0 20px rgba(212, 175, 55, 1);
        }

        .reservation-item.walkin-item {
            border-left: 3px solid #ffd700 !important;
            box-shadow: 0 1px 6px rgba(255, 215, 0, 0.3);
            font-weight: 600;
        }

        .card-type-badge.walkin {
            background: none !important;
            color: #ffd700;
            border: none !important;
            font-weight: 700;
            padding: 0 !important;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Walk-in Calendar Picker Modal (from save.php) */
        .calendar-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 100000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }

        .calendar-modal-overlay.active {
            display: flex;
        }

        .calendar-modal {
            background: #1a1a1a;
            border-radius: 12px;
            padding: 20px;
            width: 90%;
            max-width: 440px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.7);
            border: 2px solid #333333;
        }

        .calendar-modal-header {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 12px;
            padding: 10px;
            background: #2a2a2a;
            border-radius: 8px;
            color: #ffffff;
        }

        .calendar-modal-nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .calendar-modal-nav button {
            background: rgba(0, 0, 0, 0.3);
            border: none;
            color: #ffd700;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .calendar-modal-nav button:hover {
            background: rgba(255, 215, 0, 0.2);
        }

        .calendar-modal-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: #ffffff;
            min-width: 140px;
            text-align: center;
        }

        .calendar-modal .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            background: #262626;
            border: 1px solid #333333;
            border-radius: 8px;
            padding: 4px;
        }

        .calendar-modal .calendar-day-header {
            background: #2a2a2a;
            color: #ffd700;
            font-weight: 600;
            font-size: 0.75rem;
            text-align: center;
            padding: 8px 2px;
        }

        .calendar-modal .calendar-day {
            background: #1e1e1e;
            color: #ffffff;
            min-height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s ease;
            user-select: none;
        }

        .calendar-modal .calendar-day:hover:not(.disabled):not(.other-month) {
            background: #ffd700;
            color: #111;
            font-weight: bold;
        }

        .calendar-modal .calendar-day.today {
            border: 1px solid #ffd700;
        }

        .calendar-modal .calendar-day.selected {
            background: #ffd700 !important;
            color: #111 !important;
            font-weight: bold;
        }

        .calendar-modal .calendar-day.other-month {
            color: #444;
            background: #161616;
            cursor: default;
        }

        .calendar-modal .calendar-day.disabled {
            background: rgba(244, 67, 54, 0.15) !important;
            color: #884444 !important;
            border: 1px solid rgba(244, 67, 54, 0.3);
            cursor: not-allowed !important;
            text-decoration: line-through;
            opacity: 0.6;
        }

        /* Walk-In Visual Product Picker Modal */
        .product-picker-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.88);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 100005;
            align-items: center;
            justify-content: center;
            padding: 16px;
            box-sizing: border-box;
        }

        .product-picker-modal-overlay.active {
            display: flex;
        }

        .product-picker-modal {
            background: #14161b;
            border: 1.5px solid #7c6300;
            border-radius: 14px;
            width: 100%;
            max-width: 960px;
            max-height: 88vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.9), 0 0 30px rgba(255, 215, 0, 0.15);
            overflow: hidden;
            animation: pickerZoomIn 0.22s ease-out;
        }

        @keyframes pickerZoomIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }

        .product-picker-header {
            padding: 16px 20px 12px 20px;
            border-bottom: 1px solid #282a32;
            background: rgba(255, 255, 255, 0.02);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .close-picker-btn {
            background: none;
            border: none;
            color: #aaa;
            font-size: 28px;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            transition: color 0.2s;
        }

        .close-picker-btn:hover {
            color: #ffd700;
        }

        .picker-search-bar {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .picker-search-input-wrap {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
        }

        .picker-search-input-wrap i {
            position: absolute;
            left: 12px;
            color: #ffd700;
            font-size: 0.85rem;
            pointer-events: none;
        }

        .picker-search-input-wrap input {
            width: 100%;
            box-sizing: border-box;
            background: #1c1e25;
            border: 1px solid #383b45;
            border-radius: 8px;
            color: #fff;
            padding: 9px 34px 9px 34px;
            font-size: 0.85rem;
            transition: border-color 0.2s;
        }

        .picker-search-input-wrap input:focus {
            border-color: #ffd700;
            outline: none;
        }

        .picker-search-input-wrap button {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #888;
            font-size: 16px;
            cursor: pointer;
        }

        .picker-category-filter select {
            background: #1c1e25;
            border: 1px solid #383b45;
            border-radius: 8px;
            color: #ffd700;
            font-weight: 600;
            padding: 9px 12px;
            font-size: 0.85rem;
            cursor: pointer;
            outline: none;
        }

        .picker-category-pills {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge */
            padding: 2px 2px 4px 2px;
        }

        .picker-category-pills::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
            width: 0;
            height: 0;
        }

        .picker-pill {
            background: #1c1e25;
            border: 1px solid #383b45;
            color: #bbb;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .picker-pill:hover {
            border-color: #ffd700;
            color: #fff;
        }

        .picker-pill.active {
            background: #7c6300;
            color: #ffd700;
            border-color: #ffd700;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.2);
        }

        .product-picker-body {
            flex: 1;
            overflow-y: auto;
            padding: 14px 20px 20px 20px;
            min-height: 280px;
        }

        .picker-category-header {
            display: flex;
            align-items: center;
            margin: 18px 0 12px 0;
            width: 100%;
        }

        .picker-category-header:first-child {
            margin-top: 4px;
        }

        .picker-category-title {
            color: #d4af37;
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0;
            margin-right: 14px;
            white-space: nowrap;
            text-shadow: 0 0 8px rgba(212, 175, 55, 0.3);
        }

        .picker-category-line {
            flex: 1;
            height: 1.5px;
            background: linear-gradient(90deg, #7c6300 0%, rgba(124, 99, 0, 0.3) 50%, transparent 100%);
            position: relative;
        }

        .picker-category-line::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 7px;
            height: 7px;
            background: #d4af37;
            border-radius: 50%;
        }

        .picker-gowns-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 14px;
            margin-bottom: 16px;
        }

        .picker-gown-card {
            background: #181920;
            border: 1px solid #2e313d;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .picker-gown-card:hover {
            border-color: #ffd700;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5), 0 0 15px rgba(255, 215, 0, 0.15);
        }

        .picker-gown-card.selected {
            border-color: #ffd700;
            box-shadow: 0 0 0 2px #ffd700, 0 0 20px rgba(255, 215, 0, 0.3);
            background: rgba(255, 215, 0, 0.05);
        }

        .picker-gown-card.out-of-stock {
            opacity: 0.5;
            cursor: not-allowed;
            filter: grayscale(0.7);
        }

        .picker-gown-card.out-of-stock:hover {
            transform: none;
            border-color: #f44336;
            box-shadow: none;
        }

        .picker-gown-img-wrap {
            width: 100%;
            height: 155px;
            background: #0d0e12;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .picker-gown-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .picker-gown-card:hover .picker-gown-img-wrap img {
            transform: scale(1.05);
        }

        .picker-discount-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: #f44336;
            color: #fff;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            z-index: 2;
        }

        .picker-stock-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            backdrop-filter: blur(4px);
            z-index: 2;
        }

        .picker-stock-badge.instock {
            background: rgba(40, 167, 69, 0.9);
            color: #fff;
        }

        .picker-stock-badge.lowstock {
            background: rgba(255, 152, 0, 0.9);
            color: #fff;
        }

        .picker-stock-badge.outofstock {
            background: rgba(244, 67, 54, 0.9);
            color: #fff;
        }

        .picker-gown-info {
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }

        .picker-gown-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .picker-gown-category-tag {
            font-size: 0.7rem;
            color: #888;
        }

        .picker-gown-price-row {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-top: 4px;
        }

        .picker-gown-price {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffd700;
        }

        .picker-gown-price-orig {
            font-size: 0.75rem;
            color: #777;
            text-decoration: line-through;
        }

        .picker-gown-select-btn {
            margin-top: 8px;
            width: 100%;
            padding: 6px;
            border-radius: 6px;
            background: #252833;
            border: 1px solid #3c404e;
            color: #ffd700;
            font-size: 0.76rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .picker-gown-card:hover .picker-gown-select-btn {
            background: #ffd700;
            color: #111;
            border-color: #ffd700;
        }

        .picker-gown-card.selected .picker-gown-select-btn {
            background: #28a745;
            color: #fff;
            border-color: #28a745;
        }

        .product-picker-footer {
            padding: 12px 20px;
            border-top: 1px solid #282a32;
            background: rgba(255, 255, 255, 0.02);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .picker-cancel-btn {
            padding: 8px 18px;
            border-radius: 6px;
            background: #262830;
            border: 1px solid #383b45;
            color: #ccc;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .picker-cancel-btn:hover {
            background: #333;
            color: #fff;
        }

        @media (max-width: 768px) {
            .slide-panel {
                width: 95vw !important;
                min-width: unset !important;
                max-width: 95vw !important;
                max-height: 92vh !important;
                border-radius: 16px !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                right: auto !important;
                bottom: auto !important;
            }
            .slide-panel.slide-panel-open {
                right: auto !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                box-shadow: 0 15px 50px rgba(0, 0, 0, 0.9), 0 0 25px rgba(201, 170, 46, 0.2) !important;
                border: 1.5px solid rgba(201, 170, 46, 0.5) !important;
            }
            .picker-gowns-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 10px;
            }
            .picker-gown-img-wrap {
                height: 120px;
            }

            /* ── Calendar container: full visible, no horizontal cut ── */
            html, body {
                overflow-x: hidden !important;
            }
            .shell.dashboard-shell {
                padding: 10px 8px 50px 8px !important;
                width: 100% !important;
                max-width: 100vw !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important;
            }
            .calendar-container {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important;
                padding: 8px 6px !important;
                border-radius: 8px !important;
            }
            .calendar-header {
                flex-direction: column !important;
                gap: 6px !important;
                padding: 6px 8px !important;
            }
            .calendar-header > div {
                width: 100% !important;
                box-sizing: border-box !important;
            }
            /* Keep filter + walk-in on ONE row on mobile */
            .calendar-header > div:last-child {
                flex-wrap: nowrap !important;
                align-items: center !important;
                gap: 6px !important;
            }
            .cal-product-filter-wrap {
                flex: 1 1 0 !important;
                min-width: 0 !important;
                width: auto !important;
                box-sizing: border-box !important;
            }
            .cal-product-filter-select {
                width: 100% !important;
                max-width: 100% !important;
                font-size: 0.75rem !important;
                min-width: 0 !important;
            }
            .btn-walk-in {
                flex-shrink: 0 !important;
                width: auto !important;
                white-space: nowrap !important;
                justify-content: center !important;
                font-size: 0.75rem !important;
                padding: 7px 10px !important;
                gap: 4px !important;
            }
            .calendar-grid {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            /* ── Calendar days: compact, no overflow ── */
            .calendar-day {
                min-height: 44px !important;
                padding: 3px 2px !important;
                box-sizing: border-box !important;
            }
            .calendar-day-top {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 1px !important;
            }
            .calendar-day-number {
                font-size: 0.75rem !important;
                font-weight: 600 !important;
            }

            /* ── Hide individual reservation items on mobile — show count badge instead ── */
            .reservation-item {
                display: none !important;
            }
            .cal-more-text {
                display: none !important;
            }

            /* ── Mobile reservation count badge ── */
            .mobile-res-count {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                min-width: 16px !important;
                height: 16px !important;
                padding: 0 4px !important;
                background: rgba(255, 215, 0, 0.25) !important;
                border: 1px solid rgba(255, 215, 0, 0.6) !important;
                border-radius: 10px !important;
                font-size: 0.6rem !important;
                font-weight: 700 !important;
                color: #ffd700 !important;
                line-height: 1 !important;
                box-sizing: border-box !important;
            }

            /* ── Legend: hide text labels, keep color dots only ── */
            .legend {
                gap: 6px 8px !important;
                margin: 4px 0 !important;
                flex-wrap: wrap !important;
            }
            .legend-item span {
                display: none !important;
            }
            .legend-item {
                gap: 0 !important;
            }
            .legend-color {
                width: 14px !important;
                height: 14px !important;
                border-radius: 4px !important;
            }

            /* ── Calendar day headers: abbreviated ── */
            .calendar-day-header {
                padding: 5px 1px !important;
                font-size: 0.6rem !important;
            }
            .calendar-title {
                font-size: 0.9rem !important;
            }

            /* ── Slide-panel & Day details on mobile: small clean text ── */
            .slide-panel-header {
                padding: 10px 14px !important;
            }
            .slide-panel-header h2,
            #dayModalTitle {
                font-size: 0.90rem !important;
                font-weight: 600 !important;
                line-height: 1.3 !important;
                color: #ffd700 !important;
                word-break: break-word !important;
            }
            .slide-panel-header .close-panel-btn {
                width: 28px !important;
                height: 28px !important;
                font-size: 13px !important;
            }
            .slide-panel-body {
                padding: 12px 10px !important;
            }
            .btn-walk-in-secondary {
                padding: 7px 12px !important;
                font-size: 0.76rem !important;
                margin-bottom: 10px !important;
            }
            .stat-filter-count {
                font-size: 0.72rem !important;
                margin-bottom: 8px !important;
                padding-bottom: 6px !important;
            }
            .stat-reservation-card {
                padding: 10px 12px !important;
                border-radius: 8px !important;
                margin-bottom: 8px !important;
            }
            .stat-reservation-name {
                font-size: 0.82rem !important;
            }
            .stat-reservation-sub,
            .stat-reservation-customer {
                font-size: 0.70rem !important;
            }
            .card-type-badge {
                font-size: 0.70rem !important;
                padding: 0 !important;
                background: none !important;
                border: none !important;
            }
            .stat-reservation-status {
                font-size: 0.70rem !important;
                padding: 0 !important;
                background: none !important;
                border: none !important;
            }
            .card-prod-row {
                padding: 2px 0 !important;
            }
            .card-prod-row div {
                font-size: 0.72rem !important;
            }
            .stat-reservation-info {
                gap: 4px !important;
                font-size: 0.72rem !important;
            }
            .stat-reservation-info-label {
                font-size: 0.68rem !important;
            }
            .stat-reservation-info-value {
                font-size: 0.72rem !important;
            }
            .res-detail-section {
                padding: 10px 12px !important;
                margin-bottom: 8px !important;
            }
            .res-detail-section-title {
                font-size: 0.68rem !important;
            }
            .res-detail-label {
                font-size: 0.65rem !important;
            }
            .res-detail-value {
                font-size: 0.78rem !important;
            }
            .res-detail-status-badge {
                font-size: 0.70rem !important;
                padding: 0 !important;
                background: none !important;
                border: none !important;
            }
            .res-action-btn {
                padding: 7px 10px !important;
                font-size: 0.75rem !important;
            }
        }

        @media (max-width: 480px) {
            .slide-panel-header {
                padding: 8px 12px !important;
            }
            .slide-panel-header h2,
            #dayModalTitle {
                font-size: 0.82rem !important;
            }
            .stat-reservation-card {
                padding: 8px 10px !important;
            }
            .stat-reservation-name {
                font-size: 0.78rem !important;
            }
            .card-prod-row div {
                font-size: 0.68rem !important;
            }
            .stat-reservation-info {
                font-size: 0.68rem !important;
            }
            .btn-walk-in-secondary {
                font-size: 0.72rem !important;
                padding: 6px 10px !important;
            }
            .calendar-day {
                min-height: 38px !important;
                padding: 2px 1px !important;
            }
            .calendar-day-number {
                font-size: 0.7rem !important;
            }
            .mobile-res-count {
                font-size: 0.55rem !important;
                min-width: 14px !important;
                height: 14px !important;
            }
            .calendar-day-header {
                font-size: 0.55rem !important;
                padding: 4px 1px !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="shell dashboard-shell">
        <!-- Statistics -->
        <div class="cal-stats-overview">
            <div class="cal-stat-item" onclick="showStatFilter('pending')">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Pending</span>
                    <span class="cal-stat-change" title="Today in Philippine time">+<?php echo $todayPendingCount; ?> item<?php echo $todayPendingCount == 1 ? '' : 's'; ?></span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number stat-num-pending" style="color: #ffd700 !important; text-shadow: 0 0 12px rgba(255, 215, 0, 0.4);"><?php echo $allPendingCount; ?></p>
                </div>
            </div>
            <div class="cal-stat-item" onclick="showStatFilter('confirmed')">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Confirmed</span>
                    <span class="cal-stat-change" title="Today in Philippine time">+<?php echo $todayConfirmedCount; ?> item<?php echo $todayConfirmedCount == 1 ? '' : 's'; ?></span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number stat-num-confirmed" style="color: #ffd700 !important; text-shadow: 0 0 12px rgba(255, 215, 0, 0.4);"><?php echo $allConfirmedCount; ?></p>
                </div>
            </div>
            <div class="cal-stat-item" onclick="showStatFilter('rescheduled')" title="Click to view rescheduled reservations">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Rescheduled</span>
                    <span class="cal-stat-change" title="Today in Philippine time">+<?php echo $todayRescheduledCount; ?> item<?php echo $todayRescheduledCount == 1 ? '' : 's'; ?></span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number stat-num-rescheduled" style="color: #ffd700 !important; text-shadow: 0 0 12px rgba(255, 215, 0, 0.4);"><?php echo $allRescheduledCount; ?></p>
                </div>
            </div>
            <div class="cal-stat-item" onclick="showStatFilter('borrowed')" title="Click to view records of users who already borrowed items">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Users Borrowed</span>
                    <span class="cal-stat-change" title="Today in Philippine time">+<?php echo $todayBorrowedItemsCount; ?> item<?php echo $todayBorrowedItemsCount == 1 ? '' : 's'; ?></span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number stat-num-borrowed" style="color: #ffd700 !important; text-shadow: 0 0 12px rgba(255, 215, 0, 0.4); display: flex; align-items: baseline; gap: 5px;">
                        <span><?php echo $allBorrowedUsersCount; ?></span>
                        <span style="font-size: 0.8rem; font-weight: 500; color: #ffd700;">Users</span>
                    </p>
                </div>
            </div>
            <div class="cal-stat-item" onclick="showStatFilter('lost_requests')" title="Click to view requests from users who borrowed items and overdue pay for lost item with summary">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Lost & Overdue</span>
                    <span class="cal-stat-change" title="Today in Philippine time">+<?php echo $todayLostItemsCount; ?> item<?php echo $todayLostItemsCount == 1 ? '' : 's'; ?></span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number stat-num-lost" style="color: #ffd700 !important; text-shadow: 0 0 12px rgba(255, 215, 0, 0.4);"><?php echo $allLostRequestsCount; ?></p>
                </div>
            </div>
            <div class="cal-stat-item" onclick="openDurationModal()" title="Click to change rental duration & overdue fee policy">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Rental Duration</span>
                    <span class="cal-stat-change" id="rentalDurationBadge" style="color: #22c55e !important; font-weight: 600;"><?php echo $rentalDurationDays; ?> days</span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number" id="rentalDurationDisplay" style="color: #ffd700; text-shadow: 0 0 12px rgba(255, 215, 0, 0.4); font-size: 1.15rem; display: flex; align-items: baseline; gap: 5px; margin: 0; line-height: 1.15;">
                        <span><?php echo $rentalDurationDays; ?></span>
                        <span style="font-size: 0.8rem; font-weight: 500; color: #ffd700;">Days</span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Calendar -->
        <div class="calendar-container" style="grid-column: 1 / -1;">
                <div class="calendar-header" style="display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <div class="calendar-nav">
                            <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="calendar-nav">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" class="calendar-nav">
                                <i class="fas fa-home"></i>
                            </a>
                            <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="calendar-nav">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        <div class="calendar-title">
                            <?php echo $monthNames[$month] . ' ' . $year; ?>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <!-- Walk-In Reservation Button -->
                        <button type="button" class="btn-walk-in" onclick="openWalkInModal()" title="Create walk-in reservation for customer in shop">
                            <i class="fas fa-walking"></i> Walk-In
                        </button>
                    </div>
                </div>
                
                <!-- Legend -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: rgba(255, 152, 0, 0.5);"></div>
                        <span>Pending</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: rgba(33, 150, 243, 0.5);"></div>
                        <span>Confirmed</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: rgba(255, 215, 0, 0.25); border: 1px solid #ffd700;"></div>
                        <span><i class="fas fa-walking" style="color: #ffd700;"></i> Walk-In</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: rgba(245, 158, 11, 0.25); border: 1px solid #f59e0b;"></div>
                        <span><i class="fas fa-history" style="color: #f59e0b;"></i> Rescheduled</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: rgba(244, 67, 54, 0.15); border: 1px solid rgba(244, 67, 54, 0.4);"></div>
                        <span id="fullyBookedLegendLabel">Fully Booked (Product Out of Stock)</span>
                    </div>
                </div>
                
                <!-- Calendar Grid -->
                <div class="calendar-grid" id="mainCalendarGrid">
                    <!-- Day Headers -->
                    <div class="calendar-day-header"><span class="day-full">Sunday</span><span class="day-short">Sun</span></div>
                    <div class="calendar-day-header"><span class="day-full">Monday</span><span class="day-short">Mon</span></div>
                    <div class="calendar-day-header"><span class="day-full">Tuesday</span><span class="day-short">Tue</span></div>
                    <div class="calendar-day-header"><span class="day-full">Wednesday</span><span class="day-short">Wed</span></div>
                    <div class="calendar-day-header"><span class="day-full">Thursday</span><span class="day-short">Thu</span></div>
                    <div class="calendar-day-header"><span class="day-full">Friday</span><span class="day-short">Fri</span></div>
                    <div class="calendar-day-header"><span class="day-full">Saturday</span><span class="day-short">Sat</span></div>
                    
                    <!-- Empty cells before first day -->
                    <?php for ($i = 0; $i < $firstDayOfWeek; $i++): ?>
                        <div class="calendar-day other-month"></div>
                    <?php endfor; ?>
                    
                    <!-- Days of the month -->
                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                        <?php 
                        $currentDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                        $isToday = ($currentDate === date('Y-m-d'));
                        $hasReservations = isset($reservationsByDate[$currentDate]);
                        $isFullyBooked = isset($fullyBookedDates[$currentDate]);
                        $dayReservations = $hasReservations ? $reservationsByDate[$currentDate] : [];
                        
                        // Only rental reservations are displayed in calendar grid (buy is excluded)
                        $rentalReservations = array_values(array_filter($dayReservations, function($r) {
                            return (($r['product_status'] ?? '') !== 'buy' && ($r['payment_method'] ?? '') !== 'buy');
                        }));
                        $totalRentalCount = count($rentalReservations);
                        $visibleLimit = 3;
                        $hiddenCount = max(0, $totalRentalCount - $visibleLimit);
                        ?>
                        <div class="calendar-day <?php echo $isToday ? 'today' : ''; ?> <?php echo $isFullyBooked ? 'fully-booked' : ''; ?>" 
                             data-date="<?php echo $currentDate; ?>"
                             onclick="showDayDetails('<?php echo $currentDate; ?>')"
                             style="cursor: pointer;">
                             <div class="calendar-day-top">
                                 <span class="calendar-day-number"><?php echo $day; ?></span>
                                 <?php if ($totalRentalCount > 0): ?>
                                     <span class="mobile-res-count"><?php echo $totalRentalCount; ?></span>
                                 <?php endif; ?>
                                 <?php if ($hiddenCount > 0): ?>
                                     <span class="cal-more-text" 
                                           onclick="event.stopPropagation(); showDayDetails('<?php echo $currentDate; ?>')" 
                                           title="<?php echo $hiddenCount; ?> more reservation<?php echo $hiddenCount > 1 ? 's' : ''; ?>. Click to view all.">
                                         +<?php echo $hiddenCount; ?> more
                                     </span>
                                 <?php endif; ?>
                             </div>
                            
                            <?php if (!empty($rentalReservations)): ?>
                                <?php foreach ($rentalReservations as $idx => $reservation): 
                                    $resId = intval($reservation['id'] ?? 0);
                                    $pid = intval($reservation['product_id'] ?? 0);
                                    $additionalItems = $reservationItemCounts[$resId] ?? 0;
                                    $totalItems = 1 + $additionalItems;
                                    $resCode = $reservation['reservation_code'] ?? '';
                                    $isWalkIn = (strpos($resCode, 'CGW-') === 0) || (stripos($reservation['last_name'] ?? '', 'Walk-In') !== false) || (strtolower($reservation['payment_method'] ?? '') === 'cash');
                                    $isResched = intval($reservation['is_rescheduled'] ?? 0) === 1;
                                    $custName = trim(($reservation['first_name'] ?? '') . ' ' . ($reservation['last_name'] ?? ''));
                                    $isHidden = ($idx >= $visibleLimit);
                                ?>
                                    <div class="reservation-item <?php echo $reservation['order_status'] ?? 'pending'; ?> <?php echo $isWalkIn ? 'walkin-item' : ''; ?>" 
                                         data-product-id="<?php echo $pid; ?>"
                                         <?php if ($isHidden): ?>style="display: none;"<?php endif; ?>
                                         title="<?php echo htmlspecialchars($custName . ' - ' . $reservation['product_name'] . ($isWalkIn ? ' [Walk-In]' : '') . ($isResched ? ' [Rescheduled]' : '') . ' (Reserve, ' . $totalItems . ' item' . ($totalItems > 1 ? 's' : '') . ')'); ?>"
                                         onclick="event.stopPropagation(); showReservationDetails(<?php echo $reservation['id']; ?>)">
                                        <?php if ($isWalkIn): ?>
                                            <i class="fas fa-walking" style="color: #ffd700; font-size: 0.65rem; margin-right: 2px; flex-shrink: 0;" title="Walk-In Customer"></i>
                                        <?php endif; ?>
                                        <?php if ($isResched): ?>
                                            <i class="fas fa-history" style="color: #fbbf24; font-size: 0.65rem; margin-right: 2px; flex-shrink: 0;" title="Rescheduled Reservation"></i>
                                        <?php endif; ?>
                                        <span class="res-item-name" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0;"><?php echo htmlspecialchars($reservation['product_name']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                    
                    <!-- Empty cells after last day -->
                    <?php 
                    $totalCells = $firstDayOfWeek + $daysInMonth;
                    $remainingCells = $totalCells % 7 === 0 ? 0 : 7 - ($totalCells % 7);
                    for ($i = 0; $i < $remainingCells; $i++): ?>
                        <div class="calendar-day other-month"></div>
                    <?php endfor; ?>
                </div>
        </div>
    </div>
    
    <!-- Global Backdrop for Calendar Big Modals (Close on 'X' only) -->
    <div id="globalModalBackdrop" class="modal-backdrop"></div>
    
    <!-- Stats Filter Slide Panel -->
    <div id="statFilterModal" class="slide-panel">
        <div class="slide-panel-content">
            <div class="slide-panel-header">
                <h2 id="statFilterTitle">Reservations</h2>
                <button type="button" class="close-panel-btn" onclick="closeStatFilter()" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="slide-panel-body" id="statFilterBody">
            </div>
        </div>
    </div>
    
    <!-- Day Details Slide Panel -->
    <div id="dayModal" class="slide-panel">
        <div class="slide-panel-content">
            <div class="slide-panel-header">
                <h2 id="dayModalTitle">Day Details</h2>
                <button type="button" class="close-panel-btn" onclick="closeDayModal()" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="slide-panel-body" id="dayModalBody">
            </div>
        </div>
    </div>
    
    <!-- Reservation Details Slide Panel -->
    <div id="reservationModal" class="slide-panel">
        <div class="slide-panel-content">
            <div class="slide-panel-header">
                <h2 id="reservationModalTitle">Reservation Details</h2>
                <button type="button" class="close-panel-btn" onclick="closeModal()" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="slide-panel-body" id="modalBody">
            </div>
        </div>
    </div>

    <!-- Cancellation Backdrop & Slide Panel (Put Front of Reservation Details - Close on 'X' only) -->
    <div id="cancellationBackdrop" class="cancellation-backdrop"></div>
    <div id="cancellationModal" class="slide-panel">
        <div class="slide-panel-content">
            <div class="slide-panel-header" style="border-bottom: 1px solid rgba(244, 67, 54, 0.3);">
                <h2 style="color: #f44336; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-times-circle"></i> Cancel Reservation
                </h2>
                <button type="button" class="close-panel-btn" onclick="closeCancellationModal()" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="slide-panel-body" style="padding: 24px; color: #fff;">
                <form id="cancellationForm" onsubmit="submitCancellation(event)">
                    <input type="hidden" id="cancelReservationId" name="id" value="">
                    <input type="hidden" name="status" value="cancelled">
                    
                    <div id="cancelReservationSummary" style="background: rgba(255,255,255,0.03); border: 1px solid #333; border-radius: 8px; padding: 12px; margin-bottom: 18px; font-size: 0.85rem;">
                    </div>

                    <!-- Series of Cancellation Reason Choices (Admin Only) -->
                    <div style="margin-bottom: 16px;">
                        <label style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; text-transform: uppercase; color: #FFD700; font-weight: 600; margin-bottom: 8px; letter-spacing: 0.5px;">
                            <span><i class="fas fa-list-ul"></i> Cancellation Reason <span style="color: #f44336;">*</span></span>
                            <span style="font-size: 0.7rem; color: #aaa; text-transform: none; font-weight: normal;">(Admin choices)</span>
                        </label>
                        <select id="cancellationReasonSelect" required onchange="handleCancellationReasonChange(this)" class="cancel-reason-select">
                            <option value="">-- Select a reason for cancellation --</option>
                            <option value="Item Damaged or Stained (Requires Repair / Dry Cleaning)">Item Damaged or Stained (Requires Repair / Dry Cleaning)</option>
                            <option value="Item Not Returned on Time by Previous Borrower">Item Not Returned on Time by Previous Borrower</option>
                            <option value="Scheduling Conflict / Double Booking on Selected Dates">Scheduling Conflict / Double Booking on Selected Dates</option>
                            <option value="Payment Unverified / Deposit Deadline Expired">Payment Unverified / Deposit Deadline Expired</option>
                            <option value="Customer Requested Cancellation / Change of Plans">Customer Requested Cancellation / Change of Plans</option>
                            <option value="Measurement Discrepancy or Size Fit Issue">Measurement Discrepancy or Size Fit Issue</option>
                            <option value="Failed Required ID Verification / Contract Incomplete">Failed Required ID Verification / Contract Incomplete</option>
                            <option value="Store Operational Maintenance / Emergency Closure">Store Operational Maintenance / Emergency Closure</option>
                            <option value="Other Reason">Other Reason (Specify detailed explanation below)</option>
                        </select>

                        <!-- Quick Selection Chips for Easy Click -->
                        <div class="cancel-quick-chips">
                            <span class="cancel-quick-chip" data-reason="Item Damaged or Stained (Requires Repair / Dry Cleaning)" onclick="selectCancelChip(this, 'Item Damaged or Stained (Requires Repair / Dry Cleaning)')">Fabric Damage</span>
                            <span class="cancel-quick-chip" data-reason="Item Not Returned on Time by Previous Borrower" onclick="selectCancelChip(this, 'Item Not Returned on Time by Previous Borrower')">Not Returned</span>
                            <span class="cancel-quick-chip" data-reason="Scheduling Conflict / Double Booking on Selected Dates" onclick="selectCancelChip(this, 'Scheduling Conflict / Double Booking on Selected Dates')">Date Conflict</span>
                            <span class="cancel-quick-chip" data-reason="Payment Unverified / Deposit Deadline Expired" onclick="selectCancelChip(this, 'Payment Unverified / Deposit Deadline Expired')">Payment Issue</span>
                            <span class="cancel-quick-chip" data-reason="Customer Requested Cancellation / Change of Plans" onclick="selectCancelChip(this, 'Customer Requested Cancellation / Change of Plans')">Customer Request</span>
                            <span class="cancel-quick-chip" data-reason="Measurement Discrepancy or Size Fit Issue" onclick="selectCancelChip(this, 'Measurement Discrepancy or Size Fit Issue')">Sizing Fit</span>
                            <span class="cancel-quick-chip" data-reason="Failed Required ID Verification / Contract Incomplete" onclick="selectCancelChip(this, 'Failed Required ID Verification / Contract Incomplete')">ID / Policy</span>
                            <span class="cancel-quick-chip" data-reason="Other Reason" onclick="selectCancelChip(this, 'Other Reason')">Other</span>
                        </div>
                    </div>

                    <!-- Additional Details & Explanation for the Customer -->
                    <div style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 0.8rem; text-transform: uppercase; color: #FFD700; font-weight: 600; margin-bottom: 8px; letter-spacing: 0.5px;">
                            <i class="fas fa-comment-alt"></i> Additional Explanation for Customer <span id="notesRequiredStar" style="color: #f44336; display: none;">*</span>
                        </label>
                        <textarea id="cancellationReasonNotes" rows="3" placeholder="Provide extra details or instructions for the customer (optional if standard reason selected)..." style="width: 100%; box-sizing: border-box; background: #18191f; border: 1px solid #383b45; border-radius: 8px; color: #fff; padding: 12px; font-size: 0.88rem; resize: vertical; outline: none; transition: border 0.2s;" onfocus="this.style.borderColor='#FFD700'" onblur="this.style.borderColor='#383b45'"></textarea>
                        <div style="font-size: 0.74rem; color: #888; margin-top: 5px; display: flex; align-items: center; gap: 5px;">
                            <i class="fas fa-info-circle" style="color: #FFD700;"></i>
                            <span>The selected reason and note will be clearly shown to the user on their order status page.</span>
                        </div>
                    </div>

                    <div style="margin-bottom: 24px;">
                        <label style="display: block; font-size: 0.8rem; text-transform: uppercase; color: #FFD700; font-weight: 600; margin-bottom: 8px; letter-spacing: 0.5px;">
                            <i class="fas fa-camera"></i> Attach Images of Problem (Optional)
                        </label>
                        <div style="border: 2px dashed #383b45; border-radius: 8px; padding: 18px; text-align: center; background: #14151a; cursor: pointer; transition: all 0.2s;" onclick="document.getElementById('cancelImagesInput').click()" ondragover="event.preventDefault(); this.style.borderColor='#FFD700';" ondragleave="this.style.borderColor='#383b45';" ondrop="handleCancelImagesDrop(event)">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #888; margin-bottom: 6px; display: block;"></i>
                            <div style="font-size: 0.85rem; color: #bbb; font-weight: 500;">Click or Drag & Drop images here</div>
                            <div style="font-size: 0.75rem; color: #666; margin-top: 4px;">Supports JPG, PNG, WEBP (multiple files allowed)</div>
                            <input type="file" id="cancelImagesInput" multiple accept="image/*" style="display: none;" onchange="handleCancelImagesSelect(this)">
                        </div>
                        <div id="cancelImagesPreview" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px;"></div>
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end; padding-top: 10px; border-top: 1px solid #333;">
                        <button type="button" onclick="closeCancellationModal()" style="padding: 10px 18px; border-radius: 8px; background: #262830; color: #bbb; border: 1px solid #383b45; cursor: pointer; font-size: 0.85rem;">Keep Reservation</button>
                        <button type="submit" id="confirmCancelBtn" style="padding: 10px 20px; border-radius: 8px; background: #f44336; color: #fff; border: none; font-weight: 600; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-times-circle"></i> Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rental Duration & Overdue Policy Slide Panel -->
    <div id="durationModal" class="slide-panel">
        <div class="slide-panel-content">
            <div class="slide-panel-header">
                <h2>Rental & Overdue Policy</h2>
                <button type="button" class="close-panel-btn" onclick="closeDurationModal()" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="slide-panel-body" style="padding: 24px; color: #fff;">
                <p style="color: #aaa; font-size: 0.85rem; margin-bottom: 20px; line-height: 1.5;">
                    Set the default rental duration and the overdue penalty fee percentage charged per day based on product price when items are returned past the agreed return date.
                </p>

                <!-- SECTION 1: RENTAL DURATION -->
                <div style="margin-bottom: 24px; border-bottom: 1px solid #2f323a; padding-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #6cd488; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-calendar-alt"></i> 1. Rental Duration (Days)
                        </label>
                    </div>

                    <!-- Duration Presets -->
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px;">
                        <button type="button" class="duration-preset-chip" data-days="3" onclick="setDurationPreset(3)">3 Days</button>
                        <button type="button" class="duration-preset-chip" data-days="5" onclick="setDurationPreset(5)">5 Days</button>
                        <button type="button" class="duration-preset-chip" data-days="7" onclick="setDurationPreset(7)">7 Days (1 Wk)</button>
                        <button type="button" class="duration-preset-chip" data-days="10" onclick="setDurationPreset(10)">10 Days</button>
                        <button type="button" class="duration-preset-chip" data-days="14" onclick="setDurationPreset(14)">14 Days (2 Wks)</button>
                        <button type="button" class="duration-preset-chip" data-days="30" onclick="setDurationPreset(30)">30 Days (1 Mo)</button>
                    </div>

                    <!-- Stepper Controller -->
                    <div style="background: #1c1d22; border: 1px solid #2f323a; border-radius: 14px; padding: 16px; margin-bottom: 10px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                            <button type="button" onclick="adjustDurationInput(-1)" style="width: 42px; height: 42px; border-radius: 10px; background: #282a32; color: #fff; border: 1px solid #3c404b; font-size: 1.3rem; font-weight: bold; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.background='#343742'" onmouseout="this.style.background='#282a32'">−</button>
                            
                            <div style="display: flex; align-items: baseline; justify-content: center; gap: 6px;">
                                <input type="number" id="modalDurationInput" value="<?php echo $rentalDurationDays; ?>" min="1" max="90" oninput="handleDurationManualInput(this)" style="width: 80px; height: 44px; text-align: center; font-size: 1.7rem; font-weight: 700; background: transparent; border: none; outline: none; color: #6cd488; padding: 0;">
                                <span style="font-size: 0.95rem; color: #888; font-weight: 500;">Days</span>
                            </div>

                            <button type="button" onclick="adjustDurationInput(1)" style="width: 42px; height: 42px; border-radius: 10px; background: #282a32; color: #fff; border: 1px solid #3c404b; font-size: 1.3rem; font-weight: bold; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.background='#343742'" onmouseout="this.style.background='#282a32'">+</button>
                        </div>

                        <!-- Range Slider -->
                        <input type="range" id="modalDurationSlider" min="1" max="60" value="<?php echo $rentalDurationDays; ?>" class="duration-slider-bar" oninput="handleDurationSlider(this.value)">
                        <div style="display: flex; justify-content: space-between; font-size: 0.7rem; color: #666; margin-top: 6px;">
                            <span>1 Day</span>
                            <span>30 Days</span>
                            <span>60 Days</span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: OVERDUE / LATE RETURN PENALTY PERCENTAGE -->
                <div style="margin-bottom: 24px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #ffd700; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-percentage"></i> 2. Overdue / Late Return Fee (% / Day)
                        </label>
                    </div>
                    <p style="font-size: 0.76rem; color: #999; margin-bottom: 12px; line-height: 1.4;">
                        Daily penalty percentage based on product price when customer returns past the return date.
                    </p>

                    <!-- Penalty Presets -->
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px;">
                        <button type="button" class="penalty-preset-chip" data-percent="5" onclick="setPenaltyPreset(5)">5% / day</button>
                        <button type="button" class="penalty-preset-chip" data-percent="10" onclick="setPenaltyPreset(10)">10% / day</button>
                        <button type="button" class="penalty-preset-chip" data-percent="15" onclick="setPenaltyPreset(15)">15% / day</button>
                        <button type="button" class="penalty-preset-chip" data-percent="20" onclick="setPenaltyPreset(20)">20% / day</button>
                        <button type="button" class="penalty-preset-chip" data-percent="25" onclick="setPenaltyPreset(25)">25% / day</button>
                        <button type="button" class="penalty-preset-chip" data-percent="30" onclick="setPenaltyPreset(30)">30% / day</button>
                    </div>

                    <!-- Stepper Controller -->
                    <div style="background: #1c1d22; border: 1px solid rgba(255, 215, 0, 0.25); border-radius: 14px; padding: 16px; margin-bottom: 10px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                            <button type="button" onclick="adjustPenaltyInput(-1)" style="width: 42px; height: 42px; border-radius: 10px; background: #282a32; color: #ffd700; border: 1px solid #3c404b; font-size: 1.3rem; font-weight: bold; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.background='#343742'" onmouseout="this.style.background='#282a32'">−</button>
                            
                            <div style="display: flex; align-items: baseline; justify-content: center; gap: 6px;">
                                <input type="number" id="modalPenaltyInput" value="<?php echo $overduePenaltyPercent; ?>" min="1" max="100" oninput="handlePenaltyManualInput(this)" style="width: 80px; height: 44px; text-align: center; font-size: 1.7rem; font-weight: 700; background: transparent; border: none; outline: none; color: #ffd700; padding: 0;">
                                <span style="font-size: 0.95rem; color: #888; font-weight: 500;">% / day</span>
                            </div>

                            <button type="button" onclick="adjustPenaltyInput(1)" style="width: 42px; height: 42px; border-radius: 10px; background: #282a32; color: #ffd700; border: 1px solid #3c404b; font-size: 1.3rem; font-weight: bold; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.background='#343742'" onmouseout="this.style.background='#282a32'">+</button>
                        </div>

                        <!-- Range Slider -->
                        <input type="range" id="modalPenaltySlider" min="1" max="50" value="<?php echo $overduePenaltyPercent; ?>" class="penalty-slider-bar" oninput="handlePenaltySlider(this.value)">
                        <div style="display: flex; justify-content: space-between; font-size: 0.7rem; color: #666; margin-top: 6px;">
                            <span>1%</span>
                            <span>25%</span>
                            <span>50%</span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: RENTAL EXTENSION & EXTRA DAY PRICING -->
                <div style="margin-bottom: 24px; border-bottom: 1px solid #2f323a; padding-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-calendar-plus"></i> 3. Rental Extension Policy (Allow Extension & Extra Daily Fee)
                        </label>
                    </div>
                    <p style="font-size: 0.76rem; color: #999; margin-bottom: 12px; line-height: 1.4;">
                        Allow customers to extend their return date beyond the standard rental duration, and set the price added to the rental price per extra day.
                    </p>

                    <!-- Max Extension Days -->
                    <div style="margin-bottom: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <span style="font-size: 0.76rem; color: #ccc; font-weight: 600;">Max Extension Allowed (Extra Days):</span>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px;">
                            <button type="button" class="extension-preset-chip" data-days="0" onclick="setExtensionPreset(0)">No Extension (0)</button>
                            <button type="button" class="extension-preset-chip" data-days="3" onclick="setExtensionPreset(3)">+3 Days</button>
                            <button type="button" class="extension-preset-chip" data-days="5" onclick="setExtensionPreset(5)">+5 Days</button>
                            <button type="button" class="extension-preset-chip" data-days="7" onclick="setExtensionPreset(7)">+7 Days</button>
                            <button type="button" class="extension-preset-chip" data-days="10" onclick="setExtensionPreset(10)">+10 Days</button>
                            <button type="button" class="extension-preset-chip" data-days="14" onclick="setExtensionPreset(14)">+14 Days</button>
                        </div>
                        <div style="background: #1c1d22; border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 14px; padding: 14px; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <button type="button" onclick="adjustExtensionDaysInput(-1)" style="width: 40px; height: 40px; border-radius: 10px; background: #282a32; color: #38bdf8; border: 1px solid #3c404b; font-size: 1.3rem; font-weight: bold; cursor: pointer;">−</button>
                                <div style="display: flex; align-items: baseline; gap: 6px;">
                                    <input type="number" id="modalExtensionDaysInput" value="<?php echo $maxExtensionDays; ?>" min="0" max="60" oninput="handleExtensionDaysManualInput(this)" style="width: 75px; height: 40px; text-align: center; font-size: 1.6rem; font-weight: 700; background: transparent; border: none; outline: none; color: #38bdf8; padding: 0;">
                                    <span style="font-size: 0.92rem; color: #888;">Extra Days</span>
                                </div>
                                <button type="button" onclick="adjustExtensionDaysInput(1)" style="width: 40px; height: 40px; border-radius: 10px; background: #282a32; color: #38bdf8; border: 1px solid #3c404b; font-size: 1.3rem; font-weight: bold; cursor: pointer;">+</button>
                            </div>
                        </div>
                    </div>

                    <!-- Extension Fee Added Per Day -->
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <span style="font-size: 0.76rem; color: #ccc; font-weight: 600;">Extension Price Added Per Day (₱ / Day):</span>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px;">
                            <button type="button" class="extension-fee-preset-chip" data-fee="50" onclick="setExtensionFeePreset(50)">₱50/day</button>
                            <button type="button" class="extension-fee-preset-chip" data-fee="100" onclick="setExtensionFeePreset(100)">₱100/day</button>
                            <button type="button" class="extension-fee-preset-chip" data-fee="150" onclick="setExtensionFeePreset(150)">₱150/day</button>
                            <button type="button" class="extension-fee-preset-chip" data-fee="200" onclick="setExtensionFeePreset(200)">₱200/day</button>
                            <button type="button" class="extension-fee-preset-chip" data-fee="300" onclick="setExtensionFeePreset(300)">₱300/day</button>
                            <button type="button" class="extension-fee-preset-chip" data-fee="500" onclick="setExtensionFeePreset(500)">₱500/day</button>
                        </div>
                        <div style="background: #1c1d22; border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 14px; padding: 14px;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <button type="button" onclick="adjustExtensionFeeInput(-50)" style="width: 40px; height: 40px; border-radius: 10px; background: #282a32; color: #38bdf8; border: 1px solid #3c404b; font-size: 1.3rem; font-weight: bold; cursor: pointer;">−</button>
                                <div style="display: flex; align-items: baseline; gap: 6px;">
                                    <span style="font-size: 1.3rem; color: #38bdf8; font-weight: 700;">₱</span>
                                    <input type="number" id="modalExtensionFeeInput" value="<?php echo $extensionPricePerDay; ?>" min="0" max="5000" step="10" oninput="handleExtensionFeeManualInput(this)" style="width: 90px; height: 40px; text-align: center; font-size: 1.6rem; font-weight: 700; background: transparent; border: none; outline: none; color: #38bdf8; padding: 0;">
                                    <span style="font-size: 0.92rem; color: #888;">/ Day</span>
                                </div>
                                <button type="button" onclick="adjustExtensionFeeInput(50)" style="width: 40px; height: 40px; border-radius: 10px; background: #282a32; color: #38bdf8; border: 1px solid #3c404b; font-size: 1.3rem; font-weight: bold; cursor: pointer;">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: LOW STOCK THRESHOLD -->
                <div style="margin-bottom: 24px; border-bottom: 1px solid #2f323a; padding-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #ff9800; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-boxes"></i> 4. Low Stock Alert Threshold (Items)
                        </label>
                    </div>
                    <p style="font-size: 0.76rem; color: #999; margin-bottom: 12px; line-height: 1.4;">
                        Products with stock at or below this number will automatically be flagged as "Low Stock" across the system (Walk-In Calendar picker, Gowns inventory, and Dashboard).
                    </p>

                    <!-- Low Stock Presets -->
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px;">
                        <button type="button" class="lowstock-preset-chip" data-threshold="1" onclick="setLowStockPreset(1)">1 Item</button>
                        <button type="button" class="lowstock-preset-chip" data-threshold="2" onclick="setLowStockPreset(2)">2 Items (Default)</button>
                        <button type="button" class="lowstock-preset-chip" data-threshold="3" onclick="setLowStockPreset(3)">3 Items</button>
                        <button type="button" class="lowstock-preset-chip" data-threshold="5" onclick="setLowStockPreset(5)">5 Items</button>
                        <button type="button" class="lowstock-preset-chip" data-threshold="10" onclick="setLowStockPreset(10)">10 Items</button>
                    </div>

                    <!-- Stepper Controller -->
                    <div style="background: #1c1d22; border: 1px solid rgba(255, 152, 0, 0.25); border-radius: 14px; padding: 16px; margin-bottom: 10px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                            <button type="button" onclick="adjustLowStockInput(-1)" style="width: 42px; height: 42px; border-radius: 10px; background: #282a32; color: #ff9800; border: 1px solid #3c404b; font-size: 1.3rem; font-weight: bold; cursor: pointer; transition: all 0.2s ease;">−</button>
                            
                            <div style="display: flex; align-items: baseline; justify-content: center; gap: 6px;">
                                <input type="number" id="modalLowStockInput" value="<?php echo $lowStockThreshold; ?>" min="1" max="50" oninput="handleLowStockManualInput(this)" style="width: 80px; height: 44px; text-align: center; font-size: 1.7rem; font-weight: 700; background: transparent; border: none; outline: none; color: #ff9800; padding: 0;">
                                <span style="font-size: 0.95rem; color: #888; font-weight: 500;">Items</span>
                            </div>

                            <button type="button" onclick="adjustLowStockInput(1)" style="width: 42px; height: 42px; border-radius: 10px; background: #282a32; color: #ff9800; border: 1px solid #3c404b; font-size: 1.3rem; font-weight: bold; cursor: pointer; transition: all 0.2s ease;">+</button>
                        </div>

                        <!-- Range Slider -->
                        <input type="range" id="modalLowStockSlider" min="1" max="20" value="<?php echo $lowStockThreshold; ?>" class="lowstock-slider-bar" oninput="handleLowStockSlider(this.value)">
                        <div style="display: flex; justify-content: space-between; font-size: 0.7rem; color: #666; margin-top: 6px;">
                            <span>1 Item</span>
                            <span>10 Items</span>
                            <span>20 Items</span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 5: LIVE SIMULATION PREVIEW -->
                <div style="background: rgba(108, 212, 136, 0.05); border: 1px dashed rgba(108, 212, 136, 0.25); border-radius: 12px; padding: 14px; margin-bottom: 24px;">
                    <div style="font-size: 0.72rem; color: #888; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-eye" style="color: #6cd488;"></i> Live Customer Experience Simulation:
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px;">
                        <div style="background: #14161a; border: 1px solid #282b33; border-radius: 8px; padding: 8px 10px; text-align: center; flex: 1;">
                            <span style="display: block; font-size: 0.65rem; color: #777;">Reserve Date</span>
                            <strong id="previewReserveDate" style="font-size: 0.82rem; color: #eee;">Aug 15</strong>
                        </div>

                        <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                            <span id="previewDurationDays" style="font-size: 0.7rem; font-weight: 700; color: #6cd488;"><?php echo $rentalDurationDays; ?> Days (Red Gap)</span>
                            <div style="display: flex; align-items: center; color: #6cd488; font-size: 0.8rem;">
                                <span>───</span><i class="fas fa-chevron-right" style="font-size: 0.65rem;"></i>
                            </div>
                        </div>

                        <div style="background: #14161a; border: 1px solid rgba(108, 212, 136, 0.4); border-radius: 8px; padding: 8px 10px; text-align: center; flex: 1;">
                            <span style="display: block; font-size: 0.65rem; color: #6cd488;">Auto Return</span>
                            <strong id="previewReturnDate" style="font-size: 0.82rem; color: #6cd488;">Aug 25</strong>
                        </div>
                    </div>

                    <!-- Extension simulation info -->
                    <div style="font-size: 0.78rem; color: #ddd; background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 6px; padding: 8px 10px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-calendar-plus" style="color: #38bdf8;"></i>
                        <span>Customer extension: Can extend up to <strong>+<span id="previewExtDaysText"><?php echo $maxExtensionDays; ?></span> days</strong>. If extended by 1 day &rarr; adds <strong>+₱<span id="previewExtFeeText"><?php echo $extensionPricePerDay; ?></span></strong> to rental price.</span>
                    </div>

                    <div style="font-size: 0.78rem; color: #ddd; background: rgba(0,0,0,0.3); border-radius: 6px; padding: 8px 10px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-info-circle" style="color: #ffd700;"></i>
                        <span>Overdue penalty: If product is ₱1,000 &rarr; <strong>₱<span id="previewPenaltyCalc">100</span>/day</strong> (<span id="previewPenaltyText"><?php echo $overduePenaltyPercent; ?></span>%)</span>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeDurationModal()" style="padding: 10px 20px; border-radius: 8px; background: #262830; color: #bbb; border: 1px solid #383b45; cursor: pointer; font-size: 0.85rem;">Cancel</button>
                    <button type="button" id="saveDurationBtn" onclick="saveRentalDuration()" style="padding: 10px 24px; border-radius: 8px; background: linear-gradient(135deg, #6cd488, #48bb78); color: #092011; font-weight: 700; border: none; cursor: pointer; font-size: 0.88rem; box-shadow: 0 4px 15px rgba(108, 212, 136, 0.35); display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-save"></i> Save Policy
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Valid ID Slide Panel -->
    <div id="uploadIdModal" class="slide-panel">
        <div class="slide-panel-content">
            <div class="slide-panel-header" style="border-bottom: 1px solid rgba(255, 215, 0, 0.3);">
                <h2 style="color: #ffd700; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-id-card"></i> Upload Valid ID
                </h2>
                <button type="button" class="close-panel-btn" onclick="closeUploadIdModal()" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="slide-panel-body" style="padding: 24px; color: #fff;">
                <form id="uploadIdForm" onsubmit="submitUploadId(event)">
                    <input type="hidden" id="uploadIdUserId" name="user_id" value="">

                    <div id="uploadIdUserInfo" style="background: rgba(255,255,255,0.03); border: 1px solid #333; border-radius: 8px; padding: 12px; margin-bottom: 18px; font-size: 0.85rem;">
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 0.8rem; text-transform: uppercase; color: #FFD700; font-weight: 600; margin-bottom: 8px; letter-spacing: 0.5px;">
                            <i class="fas fa-id-card"></i> ID Type <span style="color: #f44336;">*</span>
                        </label>
                        <select id="uploadIdType" name="id_type" required style="width: 100%; box-sizing: border-box; background: #18191f; border: 1px solid #383b45; border-radius: 8px; color: #fff; padding: 12px; font-size: 0.9rem; outline: none; transition: border 0.2s;" onfocus="this.style.borderColor='#FFD700'" onblur="this.style.borderColor='#383b45'">
                            <option value="">Select ID Type</option>
                            <option value="Driver's License">Driver's License</option>
                            <option value="Passport">Passport</option>
                            <option value="National ID">National ID</option>
                            <option value="SSS ID">SSS ID</option>
                            <option value="TIN ID">TIN ID</option>
                            <option value="UMID">UMID</option>
                            <option value="Voter's ID">Voter's ID</option>
                            <option value="Postal ID">Postal ID</option>
                            <option value="School ID">School ID</option>
                            <option value="Company ID">Company ID</option>
                            <option value="Government ID">Government ID</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 0.8rem; text-transform: uppercase; color: #FFD700; font-weight: 600; margin-bottom: 8px; letter-spacing: 0.5px;">
                            <i class="fas fa-camera"></i> Front Side of ID <span style="color: #f44336;">*</span>
                        </label>
                        <div style="border: 2px dashed #383b45; border-radius: 8px; padding: 18px; text-align: center; background: #14151a; cursor: pointer; transition: all 0.2s;" onclick="document.getElementById('uploadIdFrontInput').click()" ondragover="event.preventDefault(); this.style.borderColor='#FFD700';" ondragleave="this.style.borderColor='#383b45';" ondrop="handleUploadIdFrontDrop(event)">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #888; margin-bottom: 6px; display: block;"></i>
                            <div style="font-size: 0.85rem; color: #bbb; font-weight: 500;">Click or Drag & Drop front ID image</div>
                            <div style="font-size: 0.75rem; color: #666; margin-top: 4px;">Supports JPG, PNG, WEBP</div>
                            <input type="file" id="uploadIdFrontInput" name="id_front_image" accept="image/*" style="display: none;" onchange="handleUploadIdFrontSelect(this)">
                        </div>
                        <div id="uploadIdFrontPreview" style="margin-top: 12px;"></div>
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 0.8rem; text-transform: uppercase; color: #FFD700; font-weight: 600; margin-bottom: 8px; letter-spacing: 0.5px;">
                            <i class="fas fa-camera"></i> Back Side of ID (Optional)
                        </label>
                        <div style="border: 2px dashed #383b45; border-radius: 8px; padding: 18px; text-align: center; background: #14151a; cursor: pointer; transition: all 0.2s;" onclick="document.getElementById('uploadIdBackInput').click()" ondragover="event.preventDefault(); this.style.borderColor='#FFD700';" ondragleave="this.style.borderColor='#383b45';" ondrop="handleUploadIdBackDrop(event)">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #888; margin-bottom: 6px; display: block;"></i>
                            <div style="font-size: 0.85rem; color: #bbb; font-weight: 500;">Click or Drag & Drop back ID image</div>
                            <div style="font-size: 0.75rem; color: #666; margin-top: 4px;">Supports JPG, PNG, WEBP</div>
                            <input type="file" id="uploadIdBackInput" name="id_back_image" accept="image/*" style="display: none;" onchange="handleUploadIdBackSelect(this)">
                        </div>
                        <div id="uploadIdBackPreview" style="margin-top: 12px;"></div>
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end; padding-top: 10px; border-top: 1px solid #333;">
                        <button type="button" onclick="closeUploadIdModal()" style="padding: 10px 18px; border-radius: 8px; background: #262830; color: #bbb; border: 1px solid #383b45; cursor: pointer; font-size: 0.85rem;">Cancel</button>
                        <button type="submit" id="confirmUploadIdBtn" style="padding: 10px 20px; border-radius: 8px; background: linear-gradient(135deg, #7c6300, #ffd700); color: #0a0a0a; border: none; font-weight: 600; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-upload"></i> Upload ID
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Walk-In Reservation Slide Panel (Shop Customer) -->
    <div id="walkInModal" class="slide-panel">
        <div class="slide-panel-content">
            <div class="slide-panel-header" style="border-bottom: 1px solid rgba(255, 215, 0, 0.3);">
                <h2 style="color: #ffd700; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-walking"></i> Walk-In
                </h2>
                <button type="button" class="close-panel-btn" onclick="closeWalkInModal()" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="slide-panel-body" style="padding: 22px; color: #fff;">
                <form id="walkInForm" onsubmit="submitWalkInReservation(event)">
                    <!-- Customer Information -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid #333; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                        <div style="font-size: 0.78rem; text-transform: uppercase; color: #ffd700; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-user"></i> Customer Information
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">First Name</label>
                                <input type="text" id="walkInFirstName" name="first_name" required placeholder="e.g. Maria" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 10px; font-size: 0.85rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">Last Name</label>
                                <input type="text" id="walkInLastName" name="last_name" required placeholder="e.g. Santos" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 10px; font-size: 0.85rem;">
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">Phone Number</label>
                                <input type="tel" id="walkInPhone" name="phone" required maxlength="11" pattern="09[0-9]{9}" inputmode="numeric" placeholder="09xxxxxxxxx" value="09" onfocus="if(!this.value) this.value='09';" oninput="formatWalkInPhone(this)" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 10px; font-size: 0.85rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">Email (Optional)</label>
                                <input type="email" id="walkInEmail" name="email" placeholder="customer@email.com" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 10px; font-size: 0.85rem;">
                            </div>
                        </div>
                    </div>

                    <!-- Product & Booking Details -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid #333; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                        <div style="font-size: 0.78rem; text-transform: uppercase; color: #ffd700; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-tshirt"></i> Product Selection & Rental Details
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 5px;">
                                Select Gown / Product <span style="color: #ffd700;">*</span>
                            </label>
                            
                            <!-- Synced select for form validation & submission -->
                            <select id="walkInProductId" name="product_id" required onchange="handleWalkInProductChange(this)" style="display: none;">
                                <option value="">-- Choose Gown / Product --</option>
                                <?php foreach ($allGowns as $g): 
                                    $stock = intval($g['stock'] ?? 0);
                                    $disc = floatval($g['discount'] ?? 0);
                                    $origPrice = floatval($g['price'] ?? 0);
                                    $finalPrice = $disc > 0 ? $origPrice * (1 - $disc / 100) : $origPrice;
                                ?>
                                    <option value="<?php echo $g['id']; ?>" 
                                            data-price="<?php echo $finalPrice; ?>" 
                                            data-orig-price="<?php echo $origPrice; ?>"
                                            data-discount="<?php echo $disc; ?>"
                                            data-category="<?php echo htmlspecialchars($g['category'] ?? ''); ?>" 
                                            data-stock="<?php echo $stock; ?>"
                                            data-color="<?php echo htmlspecialchars($g['color'] ?? ''); ?>"
                                            data-image="<?php echo htmlspecialchars($g['image_path'] ?? $g['image_path2'] ?? $g['image_path3'] ?? ''); ?>"
                                            data-model="<?php echo htmlspecialchars($g['model_path'] ?? ''); ?>"
                                            data-name="<?php echo htmlspecialchars($g['name'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($g['name']); ?> — ₱<?php echo number_format($finalPrice, 2); ?> (Stock: <?php echo $stock; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Custom Visual Trigger (Initial / Empty State) -->
                            <div id="walkInProductSelectTrigger" onclick="openWalkInProductPicker()" style="display: flex; align-items: center; justify-content: space-between; background: #16181d; border: 1.5px dashed #ffd700; border-radius: 8px; padding: 12px 14px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#fff'; this.style.background='#1a1d24';" onmouseout="this.style.borderColor='#ffd700'; this.style.background='#16181d';">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(255, 215, 0, 0.12); border: 1px solid rgba(255, 215, 0, 0.35); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: #ffd700;">
                                        <i class="fas fa-tshirt"></i>
                                    </div>
                                </div>
                                <div style="color: #ffd700; font-size: 0.8rem; font-weight: 700; background: rgba(255,215,0,0.15); border: 1px solid rgba(255,215,0,0.4); border-radius: 6px; padding: 6px 12px; display: flex; align-items: center; gap: 5px;">
                                    <span>Browse</span> <i class="fas fa-chevron-right" style="font-size: 0.75rem;"></i>
                                </div>
                            </div>

                            <!-- Rich Selected Product Card Preview (Selected State) -->
                            <div id="walkInSelectedProductCard" style="display: none; background: rgba(255,215,0,0.06); border: 1.5px solid #7c6300; border-radius: 10px; padding: 10px 12px; transition: all 0.2s;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div id="walkInSelectedProductImgWrap" style="width: 58px; height: 58px; border-radius: 8px; overflow: hidden; background: #0d0e12; border: 1px solid #444; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                                        <span style="font-size: 1.5rem;">👗</span>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                            <strong id="walkInSelectedProductName" style="font-size: 0.95rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">--</strong>
                                            <span id="walkInSelectedProductCategory" style="font-size: 0.7rem; background: rgba(255,215,0,0.15); border: 1px solid rgba(255,215,0,0.3); color: #ffd700; border-radius: 4px; padding: 1px 6px;">--</span>
                                            <span id="walkInSelectedProductColor" style="font-size: 0.7rem; color: #bbb;"></span>
                                        </div>
                                        <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 3px;">
                                            <span id="walkInSelectedProductPrice" style="font-size: 1rem; font-weight: 700; color: #ffd700;">₱0</span>
                                            <span id="walkInSelectedProductStock" style="font-size: 0.75rem; color: #6cd488; font-weight: 600;">(Stock: 0)</span>
                                        </div>
                                    </div>
                                    <button type="button" onclick="openWalkInProductPicker()" style="padding: 7px 12px; border-radius: 6px; background: #262830; border: 1px solid #444; color: #ffd700; font-size: 0.8rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 5px; flex-shrink: 0; transition: all 0.2s;" onmouseover="this.style.background='#333'; this.style.borderColor='#ffd700';" onmouseout="this.style.background='#262830'; this.style.borderColor='#444';">
                                        <i class="fas fa-exchange-alt"></i> Change
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">Type</label>
                                <select id="walkInProductStatus" name="product_status" onchange="handleWalkInTypeChange(this)" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 10px; font-size: 0.85rem;">
                                    <option value="reserve">Rental (Reserve)</option>
                                    <option value="buy">Purchase (Buy)</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">
                                    Quantity <span id="walkInStockLabel" style="font-size: 0.7rem; color: #ffd700; font-weight: normal;">(Max: 1)</span>
                                </label>
                                <div style="display: flex; align-items: center; background: #16181d; border: 1px solid #383b45; border-radius: 6px; overflow: hidden; height: 38px;">
                                    <button type="button" id="walkInQtyMinusBtn" onclick="stepWalkInQuantity(-1)" style="width: 38px; height: 100%; background: #262830; border: none; color: #fff; font-size: 1rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; user-select: none;">
                                        <i class="fas fa-minus" style="font-size: 0.7rem;"></i>
                                    </button>
                                    <input type="number" id="walkInQuantity" name="quantity" min="1" max="1" value="1" readonly onchange="recalcWalkInTotals()" style="width: 100%; height: 100%; box-sizing: border-box; background: transparent; border: none; color: #fff; text-align: center; font-size: 0.95rem; font-weight: 700; outline: none; -moz-appearance: textfield;">
                                    <button type="button" id="walkInQtyPlusBtn" onclick="stepWalkInQuantity(1)" style="width: 38px; height: 100%; background: #262830; border: none; color: #fff; font-size: 1rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; user-select: none;">
                                        <i class="fas fa-plus" style="font-size: 0.7rem;"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Active Rental Notice for Purchase (from save.php) -->
                        <div id="walkInBuyScheduleNotice" style="display: none; background: rgba(255, 170, 0, 0.12); border: 1px solid rgba(255, 170, 0, 0.45); border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; color: #fff; font-size: 0.8rem; line-height: 1.4;">
                            <div style="display: flex; align-items: flex-start; gap: 8px;">
                                <i class="fas fa-info-circle" style="color: #ffaa00; font-size: 1.1rem; margin-top: 2px; flex-shrink: 0;"></i>
                                <div>
                                    <strong style="color: #ffaa00; display: block; margin-bottom: 2px;">Upcoming Rental Active</strong>
                                    <span id="walkInBuyScheduleText" style="color: #ddd;">This product has active rental reservations until <strong id="walkInRentalEndText" style="color: #ff8888;">--</strong>. Purchase pickup is available on or after <strong id="walkInEarliestBuyDateText" style="color: #ffd700;">--</strong> (after existing rentals return).</span>
                                </div>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;" id="walkInDatesRow">
                            <div>
                                <label id="walkInReserveDateLabel" style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">Reserve Date</label>
                                <div style="position: relative;">
                                    <input type="text" id="walkInReserveDate" name="reserve_date" readonly placeholder="Select date" onclick="openWalkInCalendarModal('walkInReserveDate')" required style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 30px 8px 10px; font-size: 0.85rem; cursor: pointer;">
                                    <i class="far fa-calendar-alt" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #ffd700; pointer-events: none; font-size: 0.85rem;"></i>
                                </div>
                            </div>
                            <div id="walkInReturnDateWrap">
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">Return Date <span style="font-size: 0.7rem; color: #6cd488;">(+<?php echo $rentalDurationDays; ?> days)</span></label>
                                <div style="position: relative;">
                                    <input type="text" id="walkInReturnDate" name="return_date" readonly placeholder="Select date" onclick="openWalkInCalendarModal('walkInReturnDate')" required style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 30px 8px 10px; font-size: 0.85rem; cursor: pointer;">
                                    <i class="far fa-calendar-check" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #6cd488; pointer-events: none; font-size: 0.85rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sizes / Measurements -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid #333; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                        <div style="font-size: 0.78rem; text-transform: uppercase; color: #ffd700; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
                            <span style="display: flex; align-items: center; gap: 6px;"><i class="fas fa-ruler-combined"></i> Size</span>
                            <span id="walkInSizeCategoryBadge" style="font-size: 0.72rem; color: #a5d8b5; background: rgba(108, 212, 136, 0.15); border: 1px solid rgba(108, 212, 136, 0.3); border-radius: 4px; padding: 2px 7px; text-transform: none;">Category: Standard</span>
                        </div>
                        <div id="walkInSizesFieldsContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px;">
                            <div>
                                <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Bust</label>
                                <input type="text" name="size_1" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Waist</label>
                                <input type="text" name="size_2" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Hips</label>
                                <input type="text" name="size_3" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Height/Other</label>
                                <input type="text" name="size_4" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                            </div>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <!-- Payment Information (Based on product_reserve.php) -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid #333; border-radius: 10px; padding: 14px; margin-bottom: 20px;">
                        <div style="font-size: 0.78rem; text-transform: uppercase; color: #ffd700; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
                            <span style="display: flex; align-items: center; gap: 6px;"><i class="fas fa-money-bill-wave"></i> Payment Information</span>
                            <span style="font-size: 0.7rem; color: #ffd700; background: rgba(255, 215, 0, 0.12); padding: 2px 8px; border-radius: 12px; border: 1px solid rgba(255, 215, 0, 0.3); font-weight: 600;">Select payment terms</span>
                        </div>

                        <!-- Payment Method -->
                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 5px;">Payment Method</label>
                            <select id="walkInPaymentMethod" name="payment_method" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 10px; font-size: 0.85rem;">
                                <option value="cash">💵 Cash (In Shop)</option>
                                <option value="rcbc_qr">📱 RCBC Bank Scan QR</option>
                            </select>
                        </div>

                        <!-- Payment Option (Full Payment vs First Payment ₱500 Deposit) -->
                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 6px;">Payment Option</label>
                            <input type="hidden" id="walkInPaymentOption" name="payment_option" value="full">

                            <div class="walkin-payment-options-grid" style="display: flex; flex-direction: column; gap: 8px;">
                                <!-- Option 1: Full Payment (100%) -->
                                <div class="walkin-payment-option-item active" id="walkInFullPaymentOption" onclick="selectWalkInPaymentOption('full')" style="display: flex; justify-content: space-between; align-items: center; background: rgba(255, 215, 0, 0.08); border: 1.5px solid #ffd700; border-radius: 10px; padding: 10px 14px; cursor: pointer; transition: all 0.2s ease; user-select: none;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="walkin-custom-radio active" id="walkInRadioFull" style="width: 18px; height: 18px; border-radius: 50%; border: 2px solid #ffd700; background: #ffd700; color: #000; display: flex; align-items: center; justify-content: center; font-size: 0.62rem; flex-shrink: 0;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div>
                                            <strong class="walkin-option-title" id="walkInFullTitle" style="display: block; font-size: 0.84rem; color: #ffd700; font-weight: 700;">Full Payment (100%)</strong>
                                            <p style="margin: 2px 0 0; font-size: 0.72rem; color: #888;">Pay the complete amount today</p>
                                        </div>
                                    </div>
                                    <div class="walkin-option-price" id="walkInFullPriceTag" style="font-size: 0.95rem; font-weight: 700; color: #ffd700;">₱0</div>
                                </div>

                                <!-- Option 2: First Payment (₱500 Deposit) -->
                                <div class="walkin-payment-option-item" id="walkInFirstPaymentOption" onclick="selectWalkInPaymentOption('first_payment')" style="display: flex; justify-content: space-between; align-items: center; background: #222224; border: 1.5px solid #38383f; border-radius: 10px; padding: 10px 14px; cursor: pointer; transition: all 0.2s ease; user-select: none;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="walkin-custom-radio" id="walkInRadioFirst" style="width: 18px; height: 18px; border-radius: 50%; border: 2px solid #555; background: transparent; color: transparent; display: flex; align-items: center; justify-content: center; font-size: 0.62rem; flex-shrink: 0;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div>
                                            <strong class="walkin-option-title" id="walkInFirstTitle" style="display: block; font-size: 0.84rem; color: #fff; font-weight: 600;">First Payment (₱500 Deposit)</strong>
                                            <p id="walkInFirstPaymentDesc" style="margin: 2px 0 0; font-size: 0.72rem; color: #888;">Pay ₱500 now to secure booking, balance due on pickup</p>
                                        </div>
                                    </div>
                                    <div class="walkin-option-price" id="walkInFirstPriceTag" style="font-size: 0.95rem; font-weight: 700; color: #ffd700;">₱500</div>
                                </div>
                            </div>
                        </div>

                        <!-- Price Summary -->
                        <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 0.75rem; color: #888;">Total Amount</div>
                                <div id="walkInTotalPriceDisplay" style="font-size: 1.1rem; font-weight: 700; color: #ffd700;">₱0</div>
                                <input type="hidden" id="walkInTotalPrice" name="total_price" value="0">
                            </div>
                            <div style="text-align: right;">
                                <div id="walkInDueLabel" style="font-size: 0.75rem; color: #888;">Full Payment / Balance</div>
                                <div style="font-size: 0.9rem; font-weight: 600;">
                                    <span id="walkInDownpaymentDisplay" style="color: #6cd488;">₱0</span> / <span id="walkInBalanceDisplay" style="color: #ff9800;">₱0</span>
                                </div>
                                <input type="hidden" id="walkInDownpayment" name="downpayment_amount" value="0">
                                <input type="hidden" id="walkInBalance" name="balance_amount" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" onclick="closeWalkInModal()" style="padding: 10px 18px; border-radius: 8px; background: #262830; color: #bbb; border: 1px solid #383b45; cursor: pointer; font-size: 0.85rem;">Cancel</button>
                        <button type="submit" id="submitWalkInBtn" style="padding: 10px 22px; border-radius: 8px; background: linear-gradient(135deg, #ffd700, #e5a900); color: #111; font-weight: 700; border: none; cursor: pointer; font-size: 0.88rem; display: flex; align-items: center; gap: 7px;">
                            <i class="fas fa-check-circle"></i> Confirm Walk-In Reservation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Walk-In Calendar Picker Modal Overlay (like save.php) -->
    <div id="walkInCalendarModalOverlay" class="calendar-modal-overlay" onclick="closeWalkInCalendarModal(event)">
        <div class="calendar-modal" onclick="event.stopPropagation()">
            <div class="calendar-modal-header">
                <div class="calendar-modal-nav">
                    <button type="button" onclick="changeWalkInCalendarMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                    <div class="calendar-modal-title" id="walkInCalendarModalTitle">August 2026</div>
                    <button type="button" onclick="changeWalkInCalendarMonth(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>

            <!-- Legend for Available vs Booked / Unavailable -->
            <div style="display: flex; justify-content: center; gap: 14px; margin-bottom: 12px; font-size: 0.75rem; color: #aaa; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <span style="width: 10px; height: 10px; background: #1e1e1e; border: 1px solid #555; border-radius: 2px;"></span>
                    <span style="color: #ddd;">Available</span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <span style="width: 10px; height: 10px; background: rgba(244, 67, 54, 0.25); border: 1px solid #f44336; border-radius: 2px;"></span>
                    <span style="color: #ff8888;">Unavailable / Booked</span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <span style="width: 10px; height: 10px; background: #ffd700; border-radius: 2px;"></span>
                    <span style="color: #ffd700;">Selected</span>
                </div>
            </div>

            <div class="calendar-grid" id="walkInCalendarModalGrid">
                <!-- Days will be generated by JavaScript -->
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 14px;">
                <button type="button" onclick="closeWalkInCalendarModal()" style="padding: 7px 16px; border-radius: 6px; background: #2a2a2a; color: #ccc; border: 1px solid #444; cursor: pointer; font-size: 0.8rem;">Close</button>
            </div>
        </div>
    </div>

    <!-- Walk-In Visual Product Picker Modal -->
    <div id="walkInProductPickerModal" class="product-picker-modal-overlay" onclick="closeWalkInProductPicker(event)">
        <div class="product-picker-modal" onclick="event.stopPropagation()">
            <!-- Header -->
            <div class="product-picker-header">
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(255,215,0,0.15); border: 1px solid rgba(255,215,0,0.4); display: flex; align-items: center; justify-content: center; color: #ffd700; font-size: 1.1rem;">
                            <i class="fas fa-tshirt"></i>
                        </div>
                        <div>
                            <h3 style="margin: 0; font-size: 1.1rem; color: #ffd700; font-weight: 700;">Select Gown / Product</h3>
                            <span style="font-size: 0.75rem; color: #aaa;">Click on any product to select &bull; Click outside to close</span>
                        </div>
                    </div>
                </div>
                
                <!-- Search & Category Filters Bar -->
                <div class="picker-search-bar">
                    <div class="picker-search-input-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" id="pickerSearchInput" placeholder="Search product by name or color..." oninput="filterPickerProducts()">
                        <button type="button" id="pickerClearSearchBtn" onclick="clearPickerSearch()" style="display:none;">&times;</button>
                    </div>
                    <div class="picker-category-filter">
                        <select id="pickerCategoryFilter" onchange="setPickerCategory(this.value)">
                            <option value="">All Categories</option>
                            <?php foreach ($allCategoryNames as $cname): ?>
                                <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Category Filter Pills -->
                <div class="picker-category-pills" id="pickerCategoryPills">
                    <button type="button" class="picker-pill active" data-category="" onclick="setPickerCategory('', this)">All</button>
                    <?php foreach ($allCategoryNames as $cname): ?>
                        <button type="button" class="picker-pill" data-category="<?php echo htmlspecialchars($cname); ?>" onclick="setPickerCategory('<?php echo htmlspecialchars(addslashes($cname)); ?>', this)">
                            <?php echo htmlspecialchars($cname); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Body Grid with Categories & Product Cards -->
            <div class="product-picker-body" id="pickerProductGrid">
                <!-- Dynamically rendered via JavaScript matching admin/gowns.php -->
            </div>

            <!-- Footer -->
            <div class="product-picker-footer">
                <div style="font-size: 0.8rem; color: #888;">
                    <span id="pickerCountDisplay">Showing all products</span>
                </div>
                <span style="font-size: 0.75rem; color: #666; font-style: italic;">Tip: Click outside to close</span>
            </div>
        </div>
    </div>
    
    <script>
        const allReservations = <?php echo json_encode($reservations); ?>;
        const dateProductAvailability = <?php echo json_encode($dateProductAvailability); ?>;
        const allGownsData = <?php echo json_encode($allGowns); ?>;
        const categorySizeLabelsMap = <?php echo json_encode($categorySizeLabelsMap); ?>;
        const currentRentalDurationDays = <?php echo intval($rentalDurationDays); ?>;
        let selectedProductFilter = 'all';
        let allTimeReservations = null; // will be fetched via AJAX

        // Custom Walk-In Calendar Picker State (matches save.php)
        let walkInCalendarDate = new Date();
        let activeWalkInDateInputId = null;
        let walkInUnavailableDates = [];
        let currentWalkInProductId = 0;
        let currentWalkInStockInfo = null;

        function fetchWalkInUnavailableDates(productId, callback) {
            if (!productId || productId <= 0) {
                walkInUnavailableDates = [];
                if (callback) callback();
                return;
            }
            fetch(`../get_unavailable_dates.php?product_id=${productId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        walkInUnavailableDates = data.dates || [];
                    } else {
                        walkInUnavailableDates = [];
                    }
                    if (callback) callback();
                })
                .catch(err => {
                    console.error('Error fetching unavailable dates:', err);
                    walkInUnavailableDates = [];
                    if (callback) callback();
                });
        }

        function checkWalkInBuyAvailability(callback) {
            const typeSelect = document.getElementById('walkInProductStatus');
            const isBuy = typeSelect && typeSelect.value === 'buy';
            const prodSelect = document.getElementById('walkInProductId');
            const pid = prodSelect ? parseInt(prodSelect.value) || 0 : 0;
            const noticeEl = document.getElementById('walkInBuyScheduleNotice');

            if (!isBuy || pid <= 0) {
                if (noticeEl) noticeEl.style.display = 'none';
                currentWalkInStockInfo = null;
                if (callback) callback();
                return;
            }

            fetch(`../get_available_stock.php?product_id=${pid}&type=buy_check`)
                .then(r => r.json())
                .then(data => {
                    currentWalkInStockInfo = data;
                    if (data.success && !data.can_buy_now && data.earliest_buy_date) {
                        if (noticeEl) {
                            noticeEl.style.display = 'block';
                            const rentEndEl = document.getElementById('walkInRentalEndText');
                            const earlyBuyEl = document.getElementById('walkInEarliestBuyDateText');
                            if (rentEndEl) rentEndEl.textContent = data.latest_reservation_end_formatted || data.latest_return_date;
                            if (earlyBuyEl) earlyBuyEl.textContent = data.earliest_buy_date_formatted || data.earliest_buy_date;
                        }
                    } else {
                        if (noticeEl) noticeEl.style.display = 'none';
                    }
                    if (callback) callback();
                })
                .catch(err => {
                    console.error('Error checking buy stock:', err);
                    if (callback) callback();
                });
        }

        function formatLocalDateStr(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        function openWalkInCalendarModal(inputId) {
            activeWalkInDateInputId = inputId;
            const currentVal = document.getElementById(inputId)?.value;
            if (currentVal) {
                walkInCalendarDate = new Date(currentVal + 'T00:00:00');
            } else if (inputId === 'walkInReturnDate') {
                const resVal = document.getElementById('walkInReserveDate')?.value;
                if (resVal) {
                    walkInCalendarDate = new Date(resVal + 'T00:00:00');
                } else {
                    walkInCalendarDate = new Date();
                }
            } else {
                walkInCalendarDate = new Date();
            }

            const prodSelect = document.getElementById('walkInProductId');
            const selectedPid = prodSelect ? parseInt(prodSelect.value) || 0 : 0;

            const overlay = document.getElementById('walkInCalendarModalOverlay');
            if (overlay) overlay.classList.add('active');

            if (selectedPid > 0) {
                currentWalkInProductId = selectedPid;
                fetchWalkInUnavailableDates(selectedPid, function() {
                    checkWalkInBuyAvailability(function() {
                        renderWalkInCalendar();
                    });
                });
            } else {
                renderWalkInCalendar();
            }
        }

        function closeWalkInCalendarModal(e) {
            if (e && e.target !== document.getElementById('walkInCalendarModalOverlay')) return;
            const overlay = document.getElementById('walkInCalendarModalOverlay');
            if (overlay) overlay.classList.remove('active');
            activeWalkInDateInputId = null;
        }

        function changeWalkInCalendarMonth(direction) {
            walkInCalendarDate.setMonth(walkInCalendarDate.getMonth() + direction);
            renderWalkInCalendar();
        }

        function renderWalkInCalendar() {
            const year = walkInCalendarDate.getFullYear();
            const month = walkInCalendarDate.getMonth();
            const firstDayOfMonth = new Date(year, month, 1);
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const firstDayOfWeek = firstDayOfMonth.getDay();

            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];

            const titleEl = document.getElementById('walkInCalendarModalTitle');
            if (titleEl) titleEl.textContent = `${monthNames[month]} ${year}`;

            const grid = document.getElementById('walkInCalendarModalGrid');
            if (!grid) return;
            grid.innerHTML = '';

            const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayHeaders.forEach(d => {
                const h = document.createElement('div');
                h.className = 'calendar-day-header';
                h.textContent = d;
                grid.appendChild(h);
            });

            for (let i = 0; i < firstDayOfWeek; i++) {
                const empty = document.createElement('div');
                empty.className = 'calendar-day other-month';
                grid.appendChild(empty);
            }

            const today = formatLocalDateStr(new Date());
            const currentInput = activeWalkInDateInputId ? document.getElementById(activeWalkInDateInputId) : null;
            const selectedDate = currentInput ? currentInput.value : '';

            const typeSelect = document.getElementById('walkInProductStatus');
            const isBuy = typeSelect && typeSelect.value === 'buy';
            const earliestBuyDate = (isBuy && currentWalkInStockInfo && !currentWalkInStockInfo.can_buy_now)
                ? currentWalkInStockInfo.earliest_buy_date
                : null;

            let minDate = today;
            if (isBuy && earliestBuyDate && earliestBuyDate > minDate) {
                minDate = earliestBuyDate;
            } else if (activeWalkInDateInputId === 'walkInReturnDate') {
                const resVal = document.getElementById('walkInReserveDate')?.value;
                if (resVal) {
                    minDate = resVal;
                }
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dateObj = new Date(year, month, day);
                const dateStr = formatLocalDateStr(dateObj);
                const dayElem = document.createElement('div');
                dayElem.className = 'calendar-day';
                dayElem.textContent = day;

                if (dateStr === today) {
                    dayElem.classList.add('today');
                }
                if (dateStr === selectedDate) {
                    dayElem.classList.add('selected');
                }

                // For buy: unavailable before earliestBuyDate or past dates
                // For rental: unavailable if in walkInUnavailableDates or past
                let isUnavailable = false;
                let reasonText = '';

                if (isBuy) {
                    if (earliestBuyDate && dateStr < earliestBuyDate) {
                        isUnavailable = true;
                        reasonText = `Active rental until ${currentWalkInStockInfo.latest_reservation_end_formatted || currentWalkInStockInfo.latest_return_date}. Available for purchase pickup on or after ${currentWalkInStockInfo.earliest_buy_date_formatted || earliestBuyDate}`;
                    } else if (dateStr < today) {
                        isUnavailable = true;
                        reasonText = 'Past date';
                    }
                } else {
                    if (walkInUnavailableDates.includes(dateStr)) {
                        isUnavailable = true;
                        reasonText = 'Fully booked for this product';
                    } else if (dateStr < minDate) {
                        isUnavailable = true;
                        reasonText = 'Past / earlier than reserve date';
                    }
                }

                if (isUnavailable) {
                    dayElem.classList.add('disabled');
                    dayElem.title = reasonText;
                } else {
                    dayElem.onclick = function() {
                        if (isBuy) {
                            const resInput = document.getElementById('walkInReserveDate');
                            const retInput = document.getElementById('walkInReturnDate');
                            if (resInput) resInput.value = dateStr;
                            if (retInput) retInput.value = dateStr;
                            closeWalkInCalendarModal();
                        } else {
                            if (activeWalkInDateInputId === 'walkInReserveDate') {
                                const resInput = document.getElementById('walkInReserveDate');
                                if (resInput) resInput.value = dateStr;

                                // Calculate auto return date (+ rental duration days)
                                const autoReturnObj = new Date(dateStr + 'T00:00:00');
                                autoReturnObj.setDate(autoReturnObj.getDate() + (currentRentalDurationDays || 10));
                                const autoReturnStr = formatLocalDateStr(autoReturnObj);

                                const retInput = document.getElementById('walkInReturnDate');
                                if (retInput) retInput.value = autoReturnStr;

                                closeWalkInCalendarModal();
                            } else if (activeWalkInDateInputId === 'walkInReturnDate') {
                                const retInput = document.getElementById('walkInReturnDate');
                                if (retInput) retInput.value = dateStr;
                                closeWalkInCalendarModal();
                            }
                        }
                    };
                }
                grid.appendChild(dayElem);
            }

            const totalCells = firstDayOfWeek + daysInMonth;
            const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
            for (let i = 0; i < remainingCells; i++) {
                const empty = document.createElement('div');
                empty.className = 'calendar-day other-month';
                grid.appendChild(empty);
            }
        }

        // Helper to update or remove the overflow +N more badge on date cell
        function updateCalendarCellBadge(cell, hiddenCount, dateKey) {
            let topRow = cell.querySelector('.calendar-day-top');
            if (!topRow) {
                const numElem = cell.querySelector('.calendar-day-number');
                if (numElem) {
                    topRow = document.createElement('div');
                    topRow.className = 'calendar-day-top';
                    numElem.parentNode.insertBefore(topRow, numElem);
                    topRow.appendChild(numElem);
                }
            }

            // Remove any legacy more badges
            const oldBadge = cell.querySelector('.cal-more-badge');
            if (oldBadge) oldBadge.remove();

            let moreElem = cell.querySelector('.cal-more-text');
            if (hiddenCount > 0) {
                if (!moreElem) {
                    moreElem = document.createElement('span');
                    moreElem.className = 'cal-more-text';
                    moreElem.onclick = function(e) {
                        e.stopPropagation();
                        showDayDetails(dateKey);
                    };
                    if (topRow) {
                        topRow.appendChild(moreElem);
                    } else {
                        cell.appendChild(moreElem);
                    }
                }
                moreElem.textContent = `+${hiddenCount} more`;
                moreElem.title = `${hiddenCount} more reservation${hiddenCount > 1 ? 's' : ''}. Click to view all.`;
                moreElem.style.display = '';
            } else {
                if (moreElem) {
                    moreElem.remove();
                }
            }
        }

        // Product Filter on Calendar
        function filterCalendarByProduct(productId) {
            selectedProductFilter = productId;
            const grid = document.getElementById('mainCalendarGrid');
            if (!grid) return;

            const dayCells = grid.querySelectorAll('.calendar-day[data-date]');
            const legendLabel = document.getElementById('fullyBookedLegendLabel');
            const visibleLimit = 3;

            if (productId === 'all') {
                if (legendLabel) legendLabel.textContent = 'Fully Booked (All Products Out of Stock)';
                
                dayCells.forEach(cell => {
                    const dateKey = cell.getAttribute('data-date');
                    const items = cell.querySelectorAll('.reservation-item');
                    items.forEach((item, idx) => {
                        if (idx < visibleLimit) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });

                    const hiddenCount = Math.max(0, items.length - visibleLimit);
                    updateCalendarCellBadge(cell, hiddenCount, dateKey);

                    // Only full if all products are full
                    const isAllFull = <?php echo json_encode($fullyBookedDates); ?>[dateKey] || false;
                    if (isAllFull) {
                        cell.classList.add('fully-booked');
                    } else {
                        cell.classList.remove('fully-booked');
                    }
                });
            } else {
                const selectedGown = allGownsData.find(g => String(g.id) === String(productId));
                const gownName = selectedGown ? selectedGown.name : 'Product';
                if (legendLabel) legendLabel.textContent = `Fully Booked (${gownName} Out of Stock)`;

                dayCells.forEach(cell => {
                    const dateKey = cell.getAttribute('data-date');
                    const items = cell.querySelectorAll('.reservation-item');
                    let matchingCount = 0;

                    // Filter items to show only this product (max visibleLimit = 3)
                    items.forEach(item => {
                        const itemPid = item.getAttribute('data-product-id');
                        if (String(itemPid) === String(productId)) {
                            matchingCount++;
                            if (matchingCount <= visibleLimit) {
                                item.style.display = '';
                            } else {
                                item.style.display = 'none';
                            }
                        } else {
                            item.style.display = 'none';
                        }
                    });

                    const hiddenCount = Math.max(0, matchingCount - visibleLimit);
                    updateCalendarCellBadge(cell, hiddenCount, dateKey);

                    // Check if this specific product is fully booked on this date
                    const avail = dateProductAvailability[dateKey] && dateProductAvailability[dateKey][productId];
                    if (avail && avail.is_full) {
                        cell.classList.add('fully-booked');
                    } else {
                        cell.classList.remove('fully-booked');
                    }
                });
            }
        }

        function formatWalkInPhone(input) {
            let val = input.value.replace(/[^0-9]/g, '');
            if (!val.startsWith('09')) {
                if (val.startsWith('9')) {
                    val = '0' + val;
                } else if (val === '0' || val === '') {
                    val = '09';
                } else {
                    val = '09' + val.replace(/^0+/, '');
                }
            }
            input.value = val.slice(0, 11);
        }

        // Walk-In Reservation Modal Handlers
        function openWalkInModal(defaultDate) {
            closeStatFilter();
            closeDayModal();
            closeModal();
            closeDurationModal();

            const form = document.getElementById('walkInForm');
            if (form) form.reset();

            const phoneInput = document.getElementById('walkInPhone');
            if (phoneInput) phoneInput.value = '09';

            const qtyInput = document.getElementById('walkInQuantity');
            if (qtyInput) qtyInput.value = '1';
            updateWalkInQtyButtonStates();

            const reserveDateInput = document.getElementById('walkInReserveDate');
            const returnDateInput = document.getElementById('walkInReturnDate');

            const todayStr = new Date().toISOString().split('T')[0];
            const chosenDate = defaultDate || todayStr;

            if (reserveDateInput) {
                reserveDateInput.value = chosenDate;
                const autoReturnObj = new Date(chosenDate + 'T00:00:00');
                autoReturnObj.setDate(autoReturnObj.getDate() + (currentRentalDurationDays || 10));
                if (returnDateInput) returnDateInput.value = formatLocalDateStr(autoReturnObj);
            }

            const prodSelect = document.getElementById('walkInProductId');
            if (prodSelect) {
                if (selectedProductFilter !== 'all') {
                    prodSelect.value = selectedProductFilter;
                }
                handleWalkInProductChange(prodSelect);
            } else {
                recalcWalkInTotals();
            }

            document.getElementById('walkInModal').classList.add('slide-panel-open');
            document.body.classList.add('slide-panel-open');
            document.body.style.overflow = 'hidden';
        }

        function closeWalkInModal() {
            document.getElementById('walkInModal').classList.remove('slide-panel-open');
            document.body.classList.remove('slide-panel-open');
            document.body.style.overflow = 'auto';
        }

        function stepWalkInQuantity(delta) {
            const qtyInput = document.getElementById('walkInQuantity');
            if (!qtyInput) return;
            const currentVal = parseInt(qtyInput.value) || 1;
            const minVal = parseInt(qtyInput.min) || 1;
            const maxVal = parseInt(qtyInput.max) || 1;
            let newVal = currentVal + delta;
            if (newVal < minVal) newVal = minVal;
            if (newVal > maxVal) newVal = maxVal;
            qtyInput.value = newVal;
            updateWalkInQtyButtonStates();
            recalcWalkInTotals();
        }

        function updateWalkInQtyButtonStates() {
            const qtyInput = document.getElementById('walkInQuantity');
            const minusBtn = document.getElementById('walkInQtyMinusBtn');
            const plusBtn = document.getElementById('walkInQtyPlusBtn');
            if (!qtyInput || !minusBtn || !plusBtn) return;
            const currentVal = parseInt(qtyInput.value) || 1;
            const minVal = parseInt(qtyInput.min) || 1;
            const maxVal = parseInt(qtyInput.max) || 1;

            if (currentVal <= minVal) {
                minusBtn.disabled = true;
                minusBtn.style.opacity = '0.4';
                minusBtn.style.cursor = 'not-allowed';
            } else {
                minusBtn.disabled = false;
                minusBtn.style.opacity = '1';
                minusBtn.style.cursor = 'pointer';
            }

            if (currentVal >= maxVal) {
                plusBtn.disabled = true;
                plusBtn.style.opacity = '0.4';
                plusBtn.style.cursor = 'not-allowed';
            } else {
                plusBtn.disabled = false;
                plusBtn.style.opacity = '1';
                plusBtn.style.cursor = 'pointer';
            }
        }

        // Product Picker & Walk-In Selection Handlers
        let pickerCurrentCategory = '';
        let pickerCategoriesList = <?php echo json_encode($allCategoryNames); ?>;
        window.latestGownsList = <?php echo json_encode($allGowns); ?>;

        function openWalkInProductPicker() {
            const modal = document.getElementById('walkInProductPickerModal');
            if (!modal) return;
            modal.classList.add('active');

            // Fetch fresh gowns from server to ensure real-time sync with admin/gowns.php
            fetch('calendar.php?action=get_all_gowns_picker')
                .then(r => r.json())
                .then(data => {
                    if (data && data.success && Array.isArray(data.gowns)) {
                        window.latestGownsList = data.gowns;
                        if (Array.isArray(data.categories) && data.categories.length > 0) {
                            pickerCategoriesList = data.categories;
                        }
                        syncGownsSelectOptions(data.gowns);
                    }
                    renderPickerUI();
                })
                .catch(err => {
                    console.error('Error loading gowns picker:', err);
                    renderPickerUI();
                });
        }

        function closeWalkInProductPicker(e) {
            if (e && e.target !== document.getElementById('walkInProductPickerModal')) return;
            const modal = document.getElementById('walkInProductPickerModal');
            if (modal) modal.classList.remove('active');
        }

        function syncGownsSelectOptions(gowns) {
            const select = document.getElementById('walkInProductId');
            if (!select) return;
            const currentVal = select.value;
            
            let html = '<option value="">-- Choose Gown / Product --</option>';
            gowns.forEach(g => {
                const stock = parseInt(g.stock) || 0;
                const disc = parseFloat(g.discount) || 0;
                const origPrice = parseFloat(g.price) || 0;
                const finalPrice = disc > 0 ? origPrice * (1 - disc / 100) : origPrice;
                
                html += `
                    <option value="${g.id}"
                        data-price="${finalPrice}"
                        data-orig-price="${origPrice}"
                        data-discount="${disc}"
                        data-category="${escapeHtml(g.category || '')}"
                        data-stock="${stock}"
                        data-color="${escapeHtml(g.color || '')}"
                        data-image="${escapeHtml(g.image_path || g.image_path2 || g.image_path3 || '')}"
                        data-model="${escapeHtml(g.model_path || '')}"
                        data-name="${escapeHtml(g.name || '')}">
                        ${escapeHtml(g.name)} — ₱${finalPrice.toFixed(2)} (Stock: ${stock})
                    </option>
                `;
            });
            select.innerHTML = html;
            if (currentVal) {
                select.value = currentVal;
            }
        }

        function setPickerCategory(catName, clickedEl) {
            pickerCurrentCategory = catName || '';
            const catSelect = document.getElementById('pickerCategoryFilter');
            if (catSelect) catSelect.value = pickerCurrentCategory;
            
            let targetPill = clickedEl || null;
            document.querySelectorAll('.picker-pill').forEach(pill => {
                const pCat = pill.getAttribute('data-category') || '';
                if (pCat.toLowerCase() === pickerCurrentCategory.toLowerCase()) {
                    pill.classList.add('active');
                    if (!targetPill) targetPill = pill;
                } else {
                    pill.classList.remove('active');
                }
            });

            // Auto-slide active pill to the center of the pills bar
            if (targetPill) {
                targetPill.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
            
            filterPickerProducts();
        }

        function clearPickerSearch() {
            const input = document.getElementById('pickerSearchInput');
            const clearBtn = document.getElementById('pickerClearSearchBtn');
            if (input) input.value = '';
            if (clearBtn) clearBtn.style.display = 'none';
            filterPickerProducts();
        }

        function renderPickerUI() {
            const gowns = window.latestGownsList || allGownsData || [];
            const catSelect = document.getElementById('pickerCategoryFilter');
            const pillsContainer = document.getElementById('pickerCategoryPills');

            const distinctCats = [];
            pickerCategoriesList.forEach(c => {
                if (!distinctCats.includes(c)) distinctCats.push(c);
            });
            gowns.forEach(g => {
                if (g.category && !distinctCats.includes(g.category)) {
                    distinctCats.push(g.category);
                }
            });

            if (catSelect) {
                let catOptions = '<option value="">All Categories</option>';
                distinctCats.forEach(c => {
                    catOptions += `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`;
                });
                catSelect.innerHTML = catOptions;
                catSelect.value = pickerCurrentCategory;
            }

            if (pillsContainer) {
                let pillsHtml = `<button type="button" class="picker-pill ${pickerCurrentCategory === '' ? 'active' : ''}" data-category="" onclick="setPickerCategory('', this)">All (${gowns.length})</button>`;
                distinctCats.forEach(c => {
                    const count = gowns.filter(g => (g.category || '').toLowerCase() === c.toLowerCase()).length;
                    pillsHtml += `<button type="button" class="picker-pill ${pickerCurrentCategory.toLowerCase() === c.toLowerCase() ? 'active' : ''}" data-category="${escapeHtml(c)}" onclick="setPickerCategory('${escapeHtml(c)}', this)">${escapeHtml(c)} (${count})</button>`;
                });
                pillsContainer.innerHTML = pillsHtml;
            }

            filterPickerProducts();
        }

        function filterPickerProducts() {
            const gowns = window.latestGownsList || allGownsData || [];
            const searchInput = document.getElementById('pickerSearchInput');
            const searchTerm = (searchInput ? searchInput.value : '').toLowerCase().trim();
            const clearBtn = document.getElementById('pickerClearSearchBtn');
            if (clearBtn) clearBtn.style.display = searchTerm ? 'block' : 'none';

            const catSelect = document.getElementById('pickerCategoryFilter');
            const selectedCategory = catSelect ? catSelect.value.trim() : pickerCurrentCategory;
            pickerCurrentCategory = selectedCategory;

            document.querySelectorAll('.picker-pill').forEach(pill => {
                const pCat = pill.getAttribute('data-category');
                if (pCat === selectedCategory) {
                    pill.classList.add('active');
                } else {
                    pill.classList.remove('active');
                }
            });

            const select = document.getElementById('walkInProductId');
            const selectedPid = select ? parseInt(select.value) || 0 : 0;

            const grouped = {};
            const categoriesOrder = [];
            pickerCategoriesList.forEach(c => {
                if (!categoriesOrder.includes(c)) categoriesOrder.push(c);
            });
            gowns.forEach(g => {
                const c = g.category || 'General';
                if (!categoriesOrder.includes(c)) {
                    categoriesOrder.push(c);
                }
                if (!grouped[c]) {
                    grouped[c] = [];
                }
                grouped[c].push(g);
            });

            const grid = document.getElementById('pickerProductGrid');
            if (!grid) return;

            let totalVisibleGowns = 0;
            let html = '';

            categoriesOrder.forEach(category => {
                if (selectedCategory && category.toLowerCase() !== selectedCategory.toLowerCase()) {
                    return;
                }

                const categoryGowns = grouped[category] || [];
                const matchingGowns = categoryGowns.filter(g => {
                    if (!searchTerm) return true;
                    const nameMatch = (g.name || '').toLowerCase().includes(searchTerm);
                    const colorMatch = (g.color || '').toLowerCase().includes(searchTerm);
                    const catMatch = (g.category || '').toLowerCase().includes(searchTerm);
                    return nameMatch || colorMatch || catMatch;
                });

                if (matchingGowns.length === 0) return;

                totalVisibleGowns += matchingGowns.length;

                html += `
                    <div class="picker-category-header" data-category="${escapeHtml(category)}">
                        <h4 class="picker-category-title">${escapeHtml(category)}</h4>
                        <div class="picker-category-line"></div>
                    </div>
                    <div class="picker-gowns-grid">
                `;

                matchingGowns.forEach(g => {
                    const pid = parseInt(g.id);
                    const stock = parseInt(g.stock) || 0;
                    const isOutOfStock = stock <= 0;
                    const isSelected = pid === selectedPid;
                    const disc = parseFloat(g.discount) || 0;
                    const origPrice = parseFloat(g.price) || 0;
                    const finalPrice = disc > 0 ? origPrice * (1 - disc / 100) : origPrice;

                    let imgPath = g.image_path || g.image_path2 || g.image_path3 || '';
                    if (imgPath && !imgPath.startsWith('http') && !imgPath.startsWith('/') && !imgPath.startsWith('../')) {
                        imgPath = '../' + imgPath;
                    }

                    let stockBadgeClass = 'instock';
                    let stockBadgeText = `Stock: ${stock}`;
                    const activeLowStockThreshold = window.systemLowStockThreshold !== undefined ? window.systemLowStockThreshold : <?php echo $lowStockThreshold; ?>;
                    if (isOutOfStock) {
                        stockBadgeClass = 'outofstock';
                        stockBadgeText = 'Out of Stock';
                    } else if (stock <= activeLowStockThreshold) {
                        stockBadgeClass = 'lowstock';
                        stockBadgeText = `Low Stock: ${stock}`;
                    }

                    html += `
                        <div class="picker-gown-card ${isSelected ? 'selected' : ''} ${isOutOfStock ? 'out-of-stock' : ''}" 
                             onclick="${isOutOfStock ? `showNotification('Out of Stock', '${escapeHtml(g.name)} is currently out of stock.', 'warning')` : `selectProductFromPicker(${pid})`}"
                             title="${isOutOfStock ? 'Out of Stock - Cannot be selected' : 'Click to select ' + escapeHtml(g.name)}">
                            
                            <div class="picker-gown-img-wrap">
                                ${imgPath ? `<img src="${imgPath}" alt="${escapeHtml(g.name)}" onerror="this.onerror=null; this.parentElement.innerHTML='<span style=\\'font-size:3rem;\\'>👗</span>';">` : `<span style="font-size: 3rem;">👗</span>`}
                                ${disc > 0 ? `<span class="picker-discount-badge">-${Math.round(disc)}%</span>` : ''}
                                <span class="picker-stock-badge ${stockBadgeClass}">${stockBadgeText}</span>
                            </div>

                            <div class="picker-gown-info">
                                <h5 class="picker-gown-name" title="${escapeHtml(g.name)}">${escapeHtml(g.name)}</h5>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="picker-gown-category-tag">${escapeHtml(g.category || 'Standard')}</span>
                                    ${g.color ? `<span style="font-size: 0.7rem; color: #aaa;">${escapeHtml(g.color)}</span>` : ''}
                                </div>
                                <div class="picker-gown-price-row">
                                    <span class="picker-gown-price">₱${finalPrice.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                    ${disc > 0 ? `<span class="picker-gown-price-orig">₱${origPrice.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>` : ''}
                                </div>
                                <button type="button" class="picker-gown-select-btn" ${isOutOfStock ? 'disabled' : ''}>
                                    ${isSelected ? '<i class="fas fa-check-circle"></i> Selected' : (isOutOfStock ? '<i class="fas fa-ban"></i> Out of Stock' : '<i class="fas fa-hand-pointer"></i> Choose')}
                                </button>
                            </div>
                        </div>
                    `;
                });

                html += `</div>`;
            });

            if (totalVisibleGowns === 0) {
                html = `
                    <div style="text-align: center; padding: 40px 20px; color: #aaa;">
                        <i class="fas fa-tshirt" style="font-size: 40px; margin-bottom: 12px; color: #7c6300;"></i>
                        <h4 style="color: #eee; margin: 0 0 6px 0;">No Products Found</h4>
                        <p style="font-size: 0.82rem; margin: 0;">Try adjusting your search query or selecting a different category.</p>
                    </div>
                `;
            }

            grid.innerHTML = html;

            const countDisplay = document.getElementById('pickerCountDisplay');
            if (countDisplay) {
                countDisplay.textContent = `Showing ${totalVisibleGowns} product${totalVisibleGowns === 1 ? '' : 's'}${selectedCategory ? ' in ' + selectedCategory : ''}`;
            }
        }

        function selectProductFromPicker(productId) {
            const select = document.getElementById('walkInProductId');
            if (!select) return;
            select.value = String(productId);
            handleWalkInProductChange(select);
            closeWalkInProductPicker();
        }

        function handleWalkInProductChange(selectEl) {
            const selectedOption = selectEl ? selectEl.options[selectEl.selectedIndex] : null;
            const selectedPid = selectEl ? parseInt(selectEl.value) || 0 : 0;
            
            const triggerBox = document.getElementById('walkInProductSelectTrigger');
            const selectedBox = document.getElementById('walkInSelectedProductCard');

            if (!selectedPid || !selectedOption || !selectedOption.value) {
                if (triggerBox) triggerBox.style.display = 'flex';
                if (selectedBox) selectedBox.style.display = 'none';
                recalcWalkInTotals();
                return;
            }

            if (triggerBox) triggerBox.style.display = 'none';
            if (selectedBox) selectedBox.style.display = 'block';

            const prodName = selectedOption.getAttribute('data-name') || selectedOption.textContent.split('—')[0].trim();
            const catName = (selectedOption.getAttribute('data-category') || '').trim();
            const colorName = (selectedOption.getAttribute('data-color') || '').trim();
            const unitPrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;
            const prodStock = parseInt(selectedOption.getAttribute('data-stock')) || 1;
            const imgPath = selectedOption.getAttribute('data-image') || '';

            const nameEl = document.getElementById('walkInSelectedProductName');
            const catEl = document.getElementById('walkInSelectedProductCategory');
            const colorEl = document.getElementById('walkInSelectedProductColor');
            const priceEl = document.getElementById('walkInSelectedProductPrice');
            const stockEl = document.getElementById('walkInSelectedProductStock');
            const imgWrap = document.getElementById('walkInSelectedProductImgWrap');

            if (nameEl) nameEl.textContent = prodName;
            if (catEl) catEl.textContent = catName || 'General';
            if (colorEl) colorEl.textContent = colorName ? `• ${colorName}` : '';
            if (priceEl) priceEl.textContent = `₱${unitPrice.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            if (stockEl) {
                stockEl.textContent = `(Stock: ${prodStock})`;
                stockEl.style.color = prodStock <= 0 ? '#f44336' : (prodStock <= 2 ? '#ff9800' : '#6cd488');
            }
            if (imgWrap) {
                if (imgPath) {
                    const cleanPath = imgPath.startsWith('http') || imgPath.startsWith('/') || imgPath.startsWith('../') ? imgPath : ('../' + imgPath);
                    imgWrap.innerHTML = `<img src="${cleanPath}" alt="${escapeHtml(prodName)}" style="width:100%; height:100%; object-fit:contain;" onerror="this.onerror=null; this.parentElement.innerHTML='<span style=\\'font-size:1.5rem;\\'>👗</span>';">`;
                } else {
                    imgWrap.innerHTML = `<span style="font-size: 1.5rem;">👗</span>`;
                }
            }

            // Update quantity input max stock & stock label
            const qtyInput = document.getElementById('walkInQuantity');
            const stockLabel = document.getElementById('walkInStockLabel');
            if (qtyInput) {
                qtyInput.max = prodStock;
                if (parseInt(qtyInput.value) > prodStock) {
                    qtyInput.value = prodStock;
                }
                if (parseInt(qtyInput.value) < 1) {
                    qtyInput.value = 1;
                }
            }
            if (stockLabel) {
                stockLabel.textContent = `(Stock: ${prodStock})`;
            }
            updateWalkInQtyButtonStates();
            
            const catBadge = document.getElementById('walkInSizeCategoryBadge');
            if (catBadge) {
                catBadge.textContent = catName ? `Category: ${catName}` : 'Category: Standard';
            }

            // Fetch unavailable dates and check buy availability for the newly selected gown
            if (selectedPid > 0) {
                currentWalkInProductId = selectedPid;
                fetchWalkInUnavailableDates(selectedPid);
                checkWalkInBuyAvailability();
            }

            const catKey = catName.toLowerCase();
            const sizesContainer = document.getElementById('walkInSizesFieldsContainer');
            if (sizesContainer) {
                const labels = categorySizeLabelsMap[catKey] || [];
                if (labels.length > 0) {
                    let html = '';
                    labels.forEach(lblObj => {
                        html += `
                            <div>
                                <label style="display: block; font-size: 0.72rem; color: #ffd700; margin-bottom: 3px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(lblObj.label)}">${escapeHtml(lblObj.label)}</label>
                                <input type="text" name="size_${lblObj.index}" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #ffd700; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem; transition: border-color 0.2s;">
                            </div>
                        `;
                    });
                    sizesContainer.innerHTML = html;
                } else {
                    // Default fallback measurements
                    sizesContainer.innerHTML = `
                        <div>
                            <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Bust</label>
                            <input type="text" name="size_1" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Waist</label>
                            <input type="text" name="size_2" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Hips</label>
                            <input type="text" name="size_3" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Height/Other</label>
                            <input type="text" name="size_4" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                        </div>
                    `;
                }
            }

            recalcWalkInTotals();
        }

        function handleWalkInTypeChange(selectEl) {
            const isBuy = selectEl.value === 'buy';
            const returnDateWrap = document.getElementById('walkInReturnDateWrap');
            const returnDateInput = document.getElementById('walkInReturnDate');
            const reserveDateLabel = document.getElementById('walkInReserveDateLabel');

            if (returnDateWrap) {
                returnDateWrap.style.display = isBuy ? 'none' : 'block';
            }
            if (returnDateInput) {
                returnDateInput.required = !isBuy;
            }
            if (reserveDateLabel) {
                reserveDateLabel.textContent = isBuy ? 'Purchase / Claim Date' : 'Reserve Date';
            }

            checkWalkInBuyAvailability();
            recalcWalkInTotals();
        }

        function handleWalkInReserveDateChange(reserveDateVal) {
            if (!reserveDateVal) return;
            const resDate = new Date(reserveDateVal + 'T00:00:00');
            const retDate = new Date(resDate);
            retDate.setDate(retDate.getDate() + (currentRentalDurationDays || 10));

            const yyyy = retDate.getFullYear();
            const mm = String(retDate.getMonth() + 1).padStart(2, '0');
            const dd = String(retDate.getDate()).padStart(2, '0');
            const returnDateInput = document.getElementById('walkInReturnDate');
            if (returnDateInput) {
                returnDateInput.value = `${yyyy}-${mm}-${dd}`;
            }
        }

        function selectWalkInPaymentOption(plan) {
            const hiddenInput = document.getElementById('walkInPaymentOption');
            if (hiddenInput) hiddenInput.value = plan;

            const fullItem = document.getElementById('walkInFullPaymentOption');
            const firstItem = document.getElementById('walkInFirstPaymentOption');
            const radioFull = document.getElementById('walkInRadioFull');
            const radioFirst = document.getElementById('walkInRadioFirst');
            const fullTitle = document.getElementById('walkInFullTitle');
            const firstTitle = document.getElementById('walkInFirstTitle');

            if (plan === 'first_payment') {
                if (fullItem) {
                    fullItem.classList.remove('active');
                    fullItem.style.borderColor = '#38383f';
                    fullItem.style.background = '#222224';
                }
                if (radioFull) {
                    radioFull.style.borderColor = '#555';
                    radioFull.style.background = 'transparent';
                    radioFull.style.color = 'transparent';
                }
                if (fullTitle) fullTitle.style.color = '#fff';

                if (firstItem) {
                    firstItem.classList.add('active');
                    firstItem.style.borderColor = '#ffd700';
                    firstItem.style.background = 'rgba(255, 215, 0, 0.08)';
                }
                if (radioFirst) {
                    radioFirst.style.borderColor = '#ffd700';
                    radioFirst.style.background = '#ffd700';
                    radioFirst.style.color = '#000';
                }
                if (firstTitle) firstTitle.style.color = '#ffd700';
            } else {
                if (firstItem) {
                    firstItem.classList.remove('active');
                    firstItem.style.borderColor = '#38383f';
                    firstItem.style.background = '#222224';
                }
                if (radioFirst) {
                    radioFirst.style.borderColor = '#555';
                    radioFirst.style.background = 'transparent';
                    radioFirst.style.color = 'transparent';
                }
                if (firstTitle) firstTitle.style.color = '#fff';

                if (fullItem) {
                    fullItem.classList.add('active');
                    fullItem.style.borderColor = '#ffd700';
                    fullItem.style.background = 'rgba(255, 215, 0, 0.08)';
                }
                if (radioFull) {
                    radioFull.style.borderColor = '#ffd700';
                    radioFull.style.background = '#ffd700';
                    radioFull.style.color = '#000';
                }
                if (fullTitle) fullTitle.style.color = '#ffd700';
            }

            recalcWalkInTotals();
        }

        function handleWalkInPaymentOptionChange(selectEl) {
            if (selectEl) selectWalkInPaymentOption(selectEl.value);
            else recalcWalkInTotals();
        }

        function recalcWalkInTotals() {
            const prodSelect = document.getElementById('walkInProductId');
            const qtyInput = document.getElementById('walkInQuantity');
            const payOptionInput = document.getElementById('walkInPaymentOption');

            const selectedOption = prodSelect ? prodSelect.options[prodSelect.selectedIndex] : null;
            const unitPrice = selectedOption ? (parseFloat(selectedOption.getAttribute('data-price')) || 0) : 0;
            const qty = qtyInput ? (parseInt(qtyInput.value) || 1) : 1;
            const totalPrice = unitPrice * qty;

            const plan = payOptionInput ? payOptionInput.value : 'full';
            const isDeposit = plan === 'first_payment';

            // Base deposit rule on product_reserve.php: First payment is ₱500 (or totalPrice if < 500)
            const firstPaymentAmount = Math.min(500, totalPrice);
            const downpayment = isDeposit ? firstPaymentAmount : totalPrice;
            const balance = isDeposit ? Math.max(0, totalPrice - firstPaymentAmount) : 0;

            const totalDisplay = document.getElementById('walkInTotalPriceDisplay');
            const downDisplay = document.getElementById('walkInDownpaymentDisplay');
            const balDisplay = document.getElementById('walkInBalanceDisplay');
            const dueLabel = document.getElementById('walkInDueLabel');
            const fullPriceTag = document.getElementById('walkInFullPriceTag');
            const firstPriceTag = document.getElementById('walkInFirstPriceTag');
            const firstDesc = document.getElementById('walkInFirstPaymentDesc');

            if (totalDisplay) totalDisplay.textContent = `₱${totalPrice.toLocaleString('en-US')}`;
            if (downDisplay) downDisplay.textContent = `₱${downpayment.toLocaleString('en-US')}`;
            if (balDisplay) balDisplay.textContent = `₱${balance.toLocaleString('en-US')}`;
            if (dueLabel) dueLabel.textContent = isDeposit ? 'Deposit Due Now / Balance' : 'Full Payment / Balance';

            if (fullPriceTag) fullPriceTag.textContent = `₱${totalPrice.toLocaleString('en-US')}`;
            if (firstPriceTag) firstPriceTag.textContent = `₱${firstPaymentAmount.toLocaleString('en-US')}`;
            if (firstDesc) {
                firstDesc.textContent = `Pay ₱${firstPaymentAmount.toLocaleString('en-US')} now to secure booking, balance of ₱${balance.toLocaleString('en-US')} due on pickup`;
            }

            const totalInput = document.getElementById('walkInTotalPrice');
            const downInput = document.getElementById('walkInDownpayment');
            const balInput = document.getElementById('walkInBalance');

            if (totalInput) totalInput.value = totalPrice;
            if (downInput) downInput.value = downpayment;
            if (balInput) balInput.value = balance;
        }

        function submitWalkInReservation(e) {
            e.preventDefault();
            const form = document.getElementById('walkInForm');
            const btn = document.getElementById('submitWalkInBtn');
            const originalBtnHtml = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Confirming Walk-In...';

            const formData = new FormData(form);

            fetch('save_walkin_reservation.php', {
                method: 'POST',
                body: formData
            })
            .then(async r => {
                const text = await r.text();
                try {
                    return JSON.parse(text);
                } catch (jsonErr) {
                    console.error('Server response:', text);
                    throw new Error(text || 'Invalid server response');
                }
            })
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;

                if (data.success) {
                    closeWalkInModal();
                    showNotification('Success', data.message || 'Walk-In reservation confirmed!', 'success', 2000);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification('Save Failed', data.message || 'Failed to save walk-in reservation', 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
                console.error('Walk-in error:', err);
                showNotification('Error', err.message || 'Error saving walk-in reservation', 'error');
            });
        }

        function showCalToast(msg, isSuccess = true) {
            showNotification(isSuccess ? 'Success' : 'Notice', msg, isSuccess ? 'success' : 'info');
        }

        function fetchAllReservations(callback) {
            if (allTimeReservations !== null) {
                callback(allTimeReservations);
                return;
            }
            fetch('get_all_reservations.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allTimeReservations = data.reservations || [];
                    } else {
                        allTimeReservations = [];
                    }
                    callback(allTimeReservations);
                })
                .catch(error => {
                    console.error('Error fetching all reservations:', error);
                    allTimeReservations = [];
                    callback(allTimeReservations);
                });
        }

        let currentStatFilter = null;

        function showStatFilter(filter) {
            currentStatFilter = filter;
            const titles = {
                'all': 'All Reservations',
                'pending': 'Pending Reservations',
                'confirmed': 'Confirmed Reservations',
                'completed': 'Completed Reservations',
                'rescheduled': 'Rescheduled Reservations',
                'borrowed': 'Users Who Already Borrowed Items',
                'lost_requests': 'Lost Items & Overdue Settlement'
            };
            document.getElementById('statFilterTitle').textContent = titles[filter] || 'Reservations';

            fetchAllReservations(function(data) {
                let filtered = data;
                if (filter === 'borrowed') {
                    filtered = data.filter(r => {
                        const isRental = (r.product_status !== 'buy' && r.payment_method !== 'buy');
                        const status = (r.order_status || 'pending').toLowerCase();
                        return isRental && (status === 'confirmed' || status === 'completed');
                    });
                } else if (filter === 'rescheduled') {
                    filtered = data.filter(r => parseInt(r.is_rescheduled || 0) === 1 && parseInt(r.is_lost || 0) !== 1 && (r.lost_status || '') !== 'requested' && (r.lost_status || '') !== 'settled');
                } else if (filter === 'lost_requests') {
                    filtered = data.filter(r => parseInt(r.is_lost || 0) === 1 || r.lost_status === 'requested' || r.lost_status === 'settled');
                } else if (filter !== 'all') {
                    filtered = data.filter(r => (r.order_status || 'pending') === filter);
                }

                const body = document.getElementById('statFilterBody');

                if (filtered.length === 0) {
                    const emptyMsg = filter === 'borrowed' ? 'No users have borrowed items yet' : (filter === 'rescheduled' ? 'No rescheduled reservations found' : (filter === 'lost_requests' ? 'No lost item requests found' : `No ${filter} reservations found`));
                    body.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #cccccc;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; color: #ffd700;"></i>
                            <p>${emptyMsg}</p>
                        </div>
                    `;
                } else {
                    // Group by reservation_group_id
                    const groupedOrders = {};
                    filtered.forEach(r => {
                        const key = r.reservation_group_id ? r.reservation_group_id : ('res_' + r.id);
                        if (!groupedOrders[key]) {
                            groupedOrders[key] = {
                                id: r.id,
                                user_id: r.user_id,
                                reservation_group_id: r.reservation_group_id,
                                reservation_code: r.reservation_code || '',
                                first_name: r.first_name,
                                last_name: r.last_name,
                                payment_method: r.payment_method,
                                payment_option: r.payment_option,
                                order_status: r.order_status,
                                product_status: r.product_status,
                                reserve_date: r.reserve_date,
                                return_date: r.return_date,
                                reserved_at: r.reserved_at,
                                is_rescheduled: r.is_rescheduled,
                                rescheduled_at: r.rescheduled_at,
                                original_reserve_date: r.original_reserve_date,
                                original_return_date: r.original_return_date,
                                reschedule_reason: r.reschedule_reason,
                                is_lost: parseInt(r.is_lost || 0),
                                lost_status: r.lost_status || '',
                                lost_reason: r.lost_reason || '',
                                lost_selling_price: parseFloat(r.lost_selling_price || 0),
                                lost_user_paid: parseFloat(r.lost_user_paid || 0),
                                lost_overdue_fee: parseFloat(r.lost_overdue_fee || 0),
                                lost_total_due: parseFloat(r.lost_total_due || 0),
                                lost_payment_proof: r.lost_payment_proof || '',
                                lost_requested_at: r.lost_requested_at || '',
                                lost_settled_at: r.lost_settled_at || '',
                                total_price: 0,
                                total_downpayment: 0,
                                total_balance: 0,
                                total_qty: 0,
                                products: []
                            };
                        } else {
                            if (parseInt(r.is_lost || 0) === 1 || r.lost_status) {
                                groupedOrders[key].is_lost = 1;
                                groupedOrders[key].lost_status = r.lost_status || groupedOrders[key].lost_status;
                                groupedOrders[key].lost_reason = r.lost_reason || groupedOrders[key].lost_reason;
                                groupedOrders[key].lost_selling_price += parseFloat(r.lost_selling_price || 0);
                                groupedOrders[key].lost_user_paid += parseFloat(r.lost_user_paid || 0);
                                groupedOrders[key].lost_overdue_fee += parseFloat(r.lost_overdue_fee || 0);
                                groupedOrders[key].lost_total_due += parseFloat(r.lost_total_due || 0);
                                groupedOrders[key].lost_payment_proof = r.lost_payment_proof || groupedOrders[key].lost_payment_proof;
                            }
                        }
                        groupedOrders[key].total_price += parseFloat(r.total_price || 0);
                        groupedOrders[key].total_downpayment += parseFloat(r.downpayment_amount || 0);
                        groupedOrders[key].total_balance += parseFloat(r.balance_amount || 0);
                        groupedOrders[key].total_qty += parseInt(r.quantity || 1);
                        groupedOrders[key].products.push(r);
                    });

                    // Order all reservations: newest at the top
                    const groupList = Object.values(groupedOrders);
                    groupList.sort((a, b) => {
                        const dateA = new Date(a.reserved_at || 0).getTime();
                        const dateB = new Date(b.reserved_at || 0).getTime();
                        if (dateB !== dateA) return dateB - dateA;
                        return (b.id || 0) - (a.id || 0);
                    });

                    let countHeader = `${groupList.length} reservation(s)`;
                    if (filter === 'borrowed') {
                        const uniqueUsers = new Set();
                        let totalBorrowedItems = 0;
                        groupList.forEach(order => {
                            const uKey = order.user_id ? ('uid_' + order.user_id) : ('name_' + ((order.first_name || '') + ' ' + (order.last_name || '')).trim());
                            if (uKey && uKey !== 'name_') uniqueUsers.add(uKey);
                            totalBorrowedItems += order.total_qty;
                        });
                        countHeader = `
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; width: 100%;">
                                <div style="font-size: 13px; color: #fff;">
                                    <strong style="color: #a78bfa; font-size: 15px;">${uniqueUsers.size}</strong> User(s) already borrowed <strong style="color: #ffd700; font-size: 15px;">${totalBorrowedItems}</strong> item(s) (${groupList.length} orders)
                                </div>
                                <span style="font-size: 11px; color: #a78bfa; background: rgba(167,139,250,0.12); border: 1px solid rgba(167,139,250,0.3); padding: 3px 10px; border-radius: 999px;">
                                    <i class="fas fa-check-circle"></i> Borrowed Records
                                </span>
                            </div>
                        `;
                    } else if (filter === 'lost_requests') {
                        const uniqueLostUsers = new Set();
                        let totalLostItemsCount = 0;
                        groupList.forEach(order => {
                            const uKey = order.user_id ? ('uid_' + order.user_id) : ('name_' + ((order.first_name || '') + ' ' + (order.last_name || '')).trim());
                            if (uKey && uKey !== 'name_') uniqueLostUsers.add(uKey);
                            totalLostItemsCount += order.total_qty;
                        });
                        countHeader = `
                            <div style="font-size: 13px; color: #eee; width: 100%;">
                                <strong style="color: #ef4444;">${uniqueLostUsers.size}</strong> Users &bull; <strong style="color: #ffd700;">${totalLostItemsCount}</strong> Items <span style="color: #888; font-size: 11.5px;">(${groupList.length} Requests)</span>
                            </div>
                        `;
                    }
                    let content = `<div class="stat-filter-count">${countHeader}</div>`;
                    content += '<div class="stat-reservation-list">';
                    
                    groupList.forEach(order => {
                        // Collect distinct types (reserve, buy, walk-in) and distinct statuses
                        const typesSet = new Set();
                        const statusSet = new Set();
                        const isWalkIn = (order.reservation_code && order.reservation_code.startsWith('CGW-')) ||
                                         (order.last_name && order.last_name.includes('Walk-In')) ||
                                         (order.payment_method === 'cash');

                        const isRescheduledOrder = order.products.some(p => parseInt(p.is_rescheduled || 0) === 1) || parseInt(order.is_rescheduled || 0) === 1;

                        order.products.forEach(p => {
                            const type = (p.product_status === 'buy' || p.payment_method === 'buy') ? 'buy' : 'reserve';
                            typesSet.add(type);
                            statusSet.add(p.order_status || order.order_status || 'pending');
                        });

                        let typeBadgesHtml = '';
                        if (filter !== 'lost_requests') {
                            if (isWalkIn) {
                                typeBadgesHtml += `<span class="card-type-badge walkin"><i class="fas fa-walking"></i> Walk-In</span>`;
                            }
                            typesSet.forEach(t => {
                                typeBadgesHtml += `<span class="card-type-badge ${t}">${t === 'buy' ? 'Buy' : 'Reserve'}</span>`;
                            });
                        }

                        let statusBadgesHtml = '';
                        if (filter === 'lost_requests') {
                            if (order.lost_status === 'settled') {
                                statusBadgesHtml += `<span style="color: #22c55e; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-check-double"></i> Lost Settled</span>`;
                            } else {
                                statusBadgesHtml += `<span style="color: #ef4444; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-exclamation-triangle"></i> Lost Requested</span>`;
                            }
                        } else {
                            statusSet.forEach(st => {
                                statusBadgesHtml += `<span class="stat-reservation-status ${st}">${st}</span>`;
                            });
                        }
                        if (isRescheduledOrder && filter !== 'lost_requests') {
                            statusBadgesHtml += `<span class="stat-reservation-status rescheduled"><i class="fas fa-history"></i> Rescheduled</span>`;
                        }

                        let prodsListHtml = '';
                        order.products.forEach(p => {
                            const pType = (p.product_status === 'buy' || p.payment_method === 'buy') ? 'buy' : 'reserve';
                            const pQty = parseInt(p.quantity || 1);
                            prodsListHtml += `
                                <div class="card-prod-row">
                                    <div style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #fff;">
                                        <i class="fas fa-tag" style="color: #ffd700; font-size: 10px;"></i>
                                        <span>${escapeHtml(p.product_name)}</span>
                                        ${pQty > 1 ? `<span style="font-size:11px; color:#ffd700;">(${pQty}x)</span>` : ''}
                                    </div>
                                </div>
                            `;
                        });

                        const hasReserve = typesSet.has('reserve');
                        const isAllBuy = !hasReserve && (typesSet.has('buy') || order.product_status === 'buy' || order.payment_method === 'buy');

                        let orderBalance = parseFloat(order.total_balance || 0);
                        if (orderBalance <= 0 && order.payment_option === 'first_payment') {
                            if (order.total_downpayment > 0 && order.total_price > order.total_downpayment) {
                                orderBalance = order.total_price - order.total_downpayment;
                            } else {
                                orderBalance = Math.max(0, Math.round(order.total_price * 0.5));
                            }
                        }
                        const isDue = (order.payment_option === 'first_payment' || orderBalance > 0);

                        // Special rendering ONLY if viewing lost item requests filter (Focused on Gold with Red and Green only)
                        if (filter === 'lost_requests') {
                            const sellingVal = order.lost_selling_price > 0 ? order.lost_selling_price : order.total_price;
                            const paidVal = order.lost_user_paid > 0 ? order.lost_user_paid : order.total_downpayment;
                            const overdueVal = order.lost_overdue_fee || 0;
                            const totalDueVal = order.lost_total_due > 0 ? order.lost_total_due : Math.max(0, sellingVal - paidVal + overdueVal);

                            // Check if overdue based on return date
                            const today = new Date().toISOString().slice(0, 10);
                            const isOverdue = order.return_date && order.return_date !== '0000-00-00' && order.return_date < today;

                            content += `
                                <div class="stat-reservation-card" style="border-left: 3px solid #ef4444;" onclick="showReservationDetails(${order.id})">
                                    <div class="stat-reservation-top">
                                        <div>
                                            <div class="stat-reservation-name" style="display: flex; align-items: center; gap: 6px;">
                                                <i class="fas fa-user-circle" style="color: #ffd700;"></i>
                                                <span style="color: #ffd700; font-weight: 700;">${escapeHtml((order.first_name || '') + ' ' + (order.last_name || ''))}</span>
                                                <span style="font-size: 11px; color: #888; font-weight: normal;">(${escapeHtml(order.reservation_code || ('#' + order.id))})</span>
                                            </div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                            ${typeBadgesHtml}
                                            ${statusBadgesHtml}
                                        </div>
                                    </div>

                                    <div style="margin: 6px 0 4px 0;">
                                        ${prodsListHtml}
                                    </div>

                                    ${order.lost_reason ? `
                                    <div style="font-size: 11.5px; color: #ccc; margin: 4px 0 6px 0;">
                                        <strong style="color: #ef4444;">Reason:</strong> ${escapeHtml(order.lost_reason)}
                                    </div>` : ''}

                                    <!-- Financial settlement summary: clean lines without boxes -->
                                    <div style="font-size: 12px; margin: 6px 0; display: flex; flex-direction: column; gap: 3px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 6px;">
                                        <div style="display: flex; justify-content: space-between; color: #aaa;">
                                            <span>Selling Price:</span>
                                            <span style="font-weight: 600; color: #ffd700;">₱${sellingVal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; color: #22c55e;">
                                            <span>Paid (Rental):</span>
                                            <span style="font-weight: 600;">-₱${paidVal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</span>
                                        </div>
                                        ${overdueVal > 0 || isOverdue ? `
                                        <div style="display: flex; justify-content: space-between; color: #ef4444;">
                                            <span>Overdue Fee:</span>
                                            <span style="font-weight: 600;">+₱${overdueVal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</span>
                                        </div>` : ''}
                                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 4px; margin-top: 2px;">
                                            <span style="font-size: 12px; color: #ffd700; font-weight: 600;">Total Due:</span>
                                            <strong style="font-size: 15px; color: #ef4444;">₱${totalDueVal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</strong>
                                        </div>
                                    </div>

                                    <div class="stat-reservation-info" style="margin-top: 6px;">
                                        <div class="stat-reservation-info-item">
                                            <span class="stat-reservation-info-label" style="color: #ffd700;">Borrowed:</span>
                                            <span class="stat-reservation-info-value" style="color: #eee;">${order.reserve_date || '-'} &bull; ${order.return_date || '-'}</span>
                                        </div>
                                    </div>

                                    ${order.lost_payment_proof ? `
                                    <div style="margin-top: 6px; display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 11px; color: #888;">Receipt:</span>
                                        <a href="../${order.lost_payment_proof}" target="_blank" onclick="event.stopPropagation();" style="font-size: 11px; color: #ffd700; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fas fa-receipt" style="color: #ffd700;"></i> View
                                        </a>
                                    </div>` : ''}
                                </div>
                            `;
                        } else {
                            content += `
                                <div class="stat-reservation-card"
                                     onclick="showReservationDetails(${order.id})">
                                    <div class="stat-reservation-top">
                                        <div>
                                            <div class="stat-reservation-name" style="display: flex; align-items: center; gap: 6px;">
                                                <i class="fas fa-user-circle" style="color: #ffd700;"></i>
                                                ${escapeHtml((order.first_name || '') + ' ' + (order.last_name || ''))}
                                            </div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                            ${typeBadgesHtml}
                                            ${statusBadgesHtml}
                                        </div>
                                    </div>
                                    <div style="background: rgba(0,0,0,0.25); border-radius: 6px; padding: 6px 10px; margin: 8px 0;">
                                        <div style="font-size: 10.5px; font-weight: 600; color: #aaa; margin-bottom: 2px;">Products:</div>
                                        ${prodsListHtml}
                                    </div>
                                    <div class="stat-reservation-info">
                                        <div class="stat-reservation-info-item">
                                            <span class="stat-reservation-info-label">${isAllBuy ? 'Date:' : 'Reserve:'}</span>
                                            <span class="stat-reservation-info-value">${order.reserve_date || ''}</span>
                                        </div>
                                        <div class="stat-reservation-info-item">
                                            <span class="stat-reservation-info-label">${isDue ? 'Due on Pickup:' : 'Payment:'}</span>
                                            <span class="stat-reservation-info-value">${isDue ? `<span style="color:#f87171; font-weight:700;">₱${orderBalance.toLocaleString()}</span>` : `<span style="color:#4ade80; font-weight:700;">Paid</span>`}</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                    });
                    content += '</div>';
                    body.innerHTML = content;
                }

                document.getElementById('statFilterModal').classList.add('slide-panel-open');
                document.body.style.overflow = 'hidden';
                document.body.classList.add('slide-panel-open');
            });
        }

        function settleLostItem(resId, action) {
            let title = 'Settle Lost Item?';
            let confirmMsg = `Are you sure you want to mark Reservation #${resId} as settled?`;
            if (action === 'cancel_lost') {
                title = 'Revert Lost Request?';
                confirmMsg = `Are you sure you want to revert Reservation #${resId}?`;
            }

            showConfirmNotification(
                title,
                confirmMsg,
                () => {
                    const fd = new FormData();
                    fd.append('reservation_id', resId);
                    fd.append('action', action);

                    fetch('settle_lost_item.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(async response => {
                        const text = await response.text();
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error(text || 'Invalid server response');
                        }
                    })
                    .then(data => {
                        if (data.success) {
                            showNotification('Success', data.message || 'Lost item settled successfully', 'success');
                            allTimeReservations = null; // Invalidate cache so fresh data is fetched
                            if (currentStatFilter === 'lost_requests') {
                                showStatFilter('lost_requests');
                            }
                            const reservationModal = document.getElementById('reservationModal');
                            if (reservationModal && reservationModal.classList.contains('slide-panel-open')) {
                                showReservationDetails(resId);
                            }
                        } else {
                            showNotification('Error', data.message || 'Operation failed', 'error');
                        }
                    })
                    .catch(err => {
                        console.error('Error settling lost item:', err);
                        showNotification('Error', err.message || 'Error settling lost item', 'error');
                    });
                },
                null,
                'Confirm'
            );
        }
        
        function closeStatFilter() {
            document.getElementById('statFilterModal').classList.remove('slide-panel-open');
            document.getElementById('statFilterModal').style.zIndex = '';
            
            // Don't remove body classes if other slide panels are still open
            const dayModal = document.getElementById('dayModal');
            const reservationModal = document.getElementById('reservationModal');
            if (!dayModal.classList.contains('slide-panel-open') && !reservationModal.classList.contains('slide-panel-open')) {
                document.body.classList.remove('slide-panel-open');
                document.body.style.overflow = 'auto';
            }
        }
        
        // Function to show day details
        function showDayDetails(date) {
            const modalTitle = document.getElementById('dayModalTitle');
            const modalBody = document.getElementById('dayModalBody');
            
            // Format date for display
            const dateObj = new Date(date + 'T00:00:00');
            const formattedDate = dateObj.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            modalTitle.textContent = formattedDate;
            
            // Get reservations for this date from the PHP data
            const reservations = <?php echo json_encode($reservationsByDate); ?>;
            const dayReservations = reservations[date] || [];
            const dayAvail = dateProductAvailability[date] || {};
            
            let content = '';

            // Walk-In Action Button on this day
            content += `
                <button type="button" class="btn-walk-in-secondary" onclick="openWalkInModal('${date}')">
                    <i class="fas fa-walking"></i> + Book Walk-In Customer for this Date
                </button>
            `;


            
            if (dayReservations.length === 0) {
                content += `
                    <div style="text-align: center; padding: 30px; color: #cccccc;">
                        <i class="fas fa-calendar-check" style="font-size: 44px; margin-bottom: 12px; color: #7c6300;"></i>
                        <p>No reservations currently on this day</p>
                    </div>
                `;
            } else {
                // Group by reservation_group_id (or fallback key)
                const groupedOrders = {};
                dayReservations.forEach(r => {
                    const key = r.reservation_group_id ? r.reservation_group_id : ('res_' + r.id);
                    if (!groupedOrders[key]) {
                        groupedOrders[key] = {
                            id: r.id,
                            reservation_group_id: r.reservation_group_id,
                            reservation_code: r.reservation_code || '',
                            first_name: r.first_name,
                            last_name: r.last_name,
                            payment_method: r.payment_method,
                            payment_option: r.payment_option,
                            order_status: r.order_status,
                            product_status: r.product_status,
                            reserved_at: r.reserved_at,
                            is_rescheduled: r.is_rescheduled,
                            rescheduled_at: r.rescheduled_at,
                            original_reserve_date: r.original_reserve_date,
                            original_return_date: r.original_return_date,
                            reschedule_reason: r.reschedule_reason,
                            total_price: 0,
                            total_downpayment: 0,
                            total_balance: 0,
                            total_qty: 0,
                            products: []
                        };
                    }
                    groupedOrders[key].total_price += parseFloat(r.total_price || 0);
                    groupedOrders[key].total_downpayment += parseFloat(r.downpayment_amount || 0);
                    groupedOrders[key].total_balance += parseFloat(r.balance_amount || 0);
                    groupedOrders[key].total_qty += parseInt(r.quantity || 1);
                    groupedOrders[key].products.push(r);
                });

                // Order day reservations: newest at the top
                const groupList = Object.values(groupedOrders);
                groupList.sort((a, b) => {
                    const dateA = new Date(a.reserved_at || 0).getTime();
                    const dateB = new Date(b.reserved_at || 0).getTime();
                    if (dateB !== dateA) return dateB - dateA;
                    return (b.id || 0) - (a.id || 0);
                });

                content += `
                    <div class="stat-filter-count">${groupList.length} reservation(s) on this date</div>
                    <div class="day-reservations-list">
                `;
                
                groupList.forEach(order => {
                    const isMulti = order.products.length > 1;

                    // Collect distinct types (reserve, buy, walk-in) and distinct statuses
                    const typesSet = new Set();
                    const statusSet = new Set();
                    const isWalkIn = (order.reservation_code && order.reservation_code.startsWith('CGW-')) ||
                                     (order.last_name && order.last_name.includes('Walk-In')) ||
                                     (order.payment_method === 'cash');

                    const isRescheduledOrder = order.products.some(p => parseInt(p.is_rescheduled || 0) === 1) || parseInt(order.is_rescheduled || 0) === 1;

                    order.products.forEach(p => {
                        const type = (p.product_status === 'buy' || p.payment_method === 'buy') ? 'buy' : 'reserve';
                        typesSet.add(type);
                        statusSet.add(p.order_status || order.order_status || 'pending');
                    });

                    // Build badges HTML
                    let typeBadgesHtml = '';
                    if (isWalkIn) {
                        typeBadgesHtml += `<span class="card-type-badge walkin"><i class="fas fa-walking"></i> Walk-In</span>`;
                    }
                    typesSet.forEach(t => {
                        typeBadgesHtml += `<span class="card-type-badge ${t}">${t === 'buy' ? 'Buy' : 'Reserve'}</span>`;
                    });

                    let statusBadgesHtml = '';
                    statusSet.forEach(st => {
                        statusBadgesHtml += `<span class="stat-reservation-status ${st}">${st}</span>`;
                    });
                    if (isRescheduledOrder) {
                        statusBadgesHtml += `<span class="stat-reservation-status rescheduled"><i class="fas fa-history"></i> Rescheduled</span>`;
                    }

                    // Build products list HTML
                    let prodsListHtml = '';
                    order.products.forEach(p => {
                        const pType = (p.product_status === 'buy' || p.payment_method === 'buy') ? 'buy' : 'reserve';
                        const pQty = parseInt(p.quantity || 1);
                        prodsListHtml += `
                            <div class="card-prod-row">
                                <div style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #fff;">
                                    <i class="fas fa-tag" style="color: #ffd700; font-size: 10px;"></i>
                                    <span>${escapeHtml(p.product_name)}</span>
                                </div>
                            </div>
                        `;
                    });

                    let orderBalance = parseFloat(order.total_balance || 0);
                    if (orderBalance <= 0 && order.payment_option === 'first_payment') {
                        if (order.total_downpayment > 0 && order.total_price > order.total_downpayment) {
                            orderBalance = order.total_price - order.total_downpayment;
                        } else {
                            orderBalance = Math.max(0, Math.round(order.total_price * 0.5));
                        }
                    }
                    const isDue = (order.payment_option === 'first_payment' || orderBalance > 0);

                    content += `
                        <div class="stat-reservation-card"
                             onclick="showReservationDetails(${order.id})">
                            <div class="stat-reservation-top">
                                <div>
                                    <div class="stat-reservation-name" style="display: flex; align-items: center; gap: 6px;">
                                        <i class="fas fa-user-circle" style="color: #ffd700;"></i>
                                        ${escapeHtml((order.first_name || '') + ' ' + (order.last_name || ''))}
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    ${typeBadgesHtml}
                                    ${statusBadgesHtml}
                                </div>
                            </div>
                            <div style="background: rgba(0,0,0,0.25); border-radius: 6px; padding: 8px 10px; margin: 8px 0;">
                                <div style="font-size: 11px; font-weight: 600; color: #aaa; margin-bottom: 3px;">All Products in this Order:</div>
                                ${prodsListHtml}
                            </div>
                            <div class="stat-reservation-info">
                                <div class="stat-reservation-info-item">
                                    <span class="stat-reservation-info-label">${isDue ? 'Due on Pickup:' : 'Payment:'}</span>
                                    <span class="stat-reservation-info-value">${isDue ? `<span style="color:#f87171; font-weight:700;">₱${orderBalance.toLocaleString()}</span>` : `<span style="color:#4ade80; font-weight:700;">Paid</span>`}</span>
                                </div>
                                <div class="stat-reservation-info-item">
                                    <span class="stat-reservation-info-label">Time:</span>
                                    <span class="stat-reservation-info-value">${new Date(order.reserved_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                content += '</div>';
            }
            
            modalBody.innerHTML = content;
            document.getElementById('dayModal').classList.add('slide-panel-open');
            document.body.style.overflow = 'hidden';
            document.body.classList.add('slide-panel-open');
        }
        
        function closeDayModal() {
            document.getElementById('dayModal').classList.remove('slide-panel-open');
            // Don't remove body classes if reservation modal is still open
            const reservationModal = document.getElementById('reservationModal');
            if (!reservationModal.classList.contains('slide-panel-open')) {
                document.body.classList.remove('slide-panel-open');
                document.body.style.overflow = 'auto';
            }
        }
        
        let currentReservationId = null;
        
        function showReservationDetails(reservationId) {
            currentReservationId = reservationId;
            // Don't close parent slide panels - just open on top
            const dayModal = document.getElementById('dayModal');
            const statFilterModal = document.getElementById('statFilterModal');
            const reservationModal = document.getElementById('reservationModal');
            
            // Adjust z-index: parent panels get lower z-index, reservation modal gets highest
            if (dayModal && dayModal.classList.contains('slide-panel-open')) {
                dayModal.style.zIndex = '99999';
            }
            if (statFilterModal && statFilterModal.classList.contains('slide-panel-open')) {
                statFilterModal.style.zIndex = '99999';
            }
            if (reservationModal) {
                reservationModal.style.zIndex = '100001';
            }
            
            fetch('get_reservation_details.php?id=' + reservationId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modalBody = document.getElementById('modalBody');
                        const r = data.reservation;
                        const products = data.products || [r];
                        const isMulti = products.length > 1;
                        const status = r.order_status || 'pending';
                        const isWalkIn = (r.reservation_code && r.reservation_code.startsWith('CGW-')) ||
                                         (r.last_name && r.last_name.includes('Walk-In')) ||
                                         (r.payment_method === 'cash');
                        
                        let totalOrderPrice = 0;
                        let totalOrderDownpayment = 0;
                        let totalOrderBalance = 0;
                        products.forEach(p => {
                            totalOrderPrice += parseFloat(p.total_price || 0);
                            totalOrderDownpayment += parseFloat(p.downpayment_amount || 0);
                            totalOrderBalance += parseFloat(p.balance_amount || 0);
                        });

                        if (totalOrderBalance <= 0 && r.payment_option === 'first_payment') {
                            if (totalOrderDownpayment > 0 && totalOrderPrice > totalOrderDownpayment) {
                                totalOrderBalance = totalOrderPrice - totalOrderDownpayment;
                            } else {
                                totalOrderBalance = Math.max(0, Math.round(totalOrderPrice * 0.5));
                            }
                        }

                        // Build products section HTML
                        let productsHtml = '';
                        products.forEach((prod, pIdx) => {
                            const prodStatus = prod.order_status || status;
                            const prodType = (prod.product_status === 'buy' || prod.payment_method === 'buy') ? 'buy' : 'reserve';
                            const qty = Math.max(1, parseInt(prod.quantity || 1));
                            const unitPrice = parseFloat(prod.price || 0);
                            const lineTotal = parseFloat(prod.total_price || (unitPrice * qty));

                            // Build size items for this product with size definition labels
                            let itemsHtml = '';
                            const prodItems = prod.items || [];
                            const totalItems = prodItems.length > 0 ? (prodItems.length + 1) : 1;

                            const sizeLabels = (Array.isArray(prod.size_labels) && prod.size_labels.length > 0)
                                ? prod.size_labels
                                : [
                                    { index: 1, label: 'Chest/Bust' },
                                    { index: 2, label: 'Waist' },
                                    { index: 3, label: 'Hips' },
                                    { index: 4, label: 'Shoulder' }
                                ];

                            const allItemsList = [];
                            allItemsList.push({ item_number: 1, sizes: prod });
                            if (Array.isArray(prod.items)) {
                                prod.items.forEach(it => {
                                    allItemsList.push({ item_number: parseInt(it.item_number), sizes: it });
                                });
                            }

                            allItemsList.forEach(item => {
                                let fieldsHtml = '';
                                sizeLabels.forEach(sl => {
                                    const val = item.sizes['size_' + sl.index];
                                    if (val !== undefined && val !== null && val !== '') {
                                        fieldsHtml += `
                                            <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid #444; border-radius: 6px; padding: 5px 10px; display: inline-flex; align-items: center; gap: 6px;">
                                                <span style="color: #aaa; font-size: 11.5px; font-weight: 500;">${escapeHtml(sl.label)}:</span>
                                                <span style="color: #FFD700; font-size: 13px; font-weight: 700;">${escapeHtml(val)}"</span>
                                            </div>
                                        `;
                                    }
                                });

                                if (!fieldsHtml) {
                                    fieldsHtml = '<span class="size-badge empty" style="font-size: 12px;">No measurements specified</span>';
                                }

                                itemsHtml += `
                                    <div class="size-item-card" style="background: rgba(255, 215, 0, 0.03); border: 1px solid rgba(255, 215, 0, 0.15); border-radius: 8px; padding: 10px 12px; margin-bottom: 8px;">
                                        <div class="size-item-header" style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                            <span class="size-item-number" style="background: #7c6300; color: #FFD700; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;">Item ${item.item_number}</span>
                                        </div>
                                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                            ${fieldsHtml}
                                        </div>
                                    </div>
                                `;
                            });

                            const prodReserveDate = prod.reserve_date || r.reserve_date || '';
                            const prodReturnDate = prod.return_date || r.return_date || '';

                            productsHtml += `
                                <div style="background: #252528; border: 1px solid #3a3a40; border-radius: 8px; padding: 14px; margin-bottom: 12px;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 8px;">
                                        <div>
                                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                                <strong style="color: #fff; font-size: 15px;">${escapeHtml(prod.product_name)}</strong>
                                                ${isWalkIn ? `<span class="card-type-badge walkin"><i class="fas fa-walking"></i> Walk-In</span>` : ''}
                                                <span class="card-type-badge ${prodType}">${prodType === 'buy' ? 'Buy' : 'Reserve'}</span>
                                                <span class="res-detail-status-badge ${prodStatus}">${prodStatus}</span>
                                                ${(parseInt(prod.is_rescheduled || 0) === 1 || parseInt(r.is_rescheduled || 0) === 1) ? `
                                                    <span class="res-detail-status-badge rescheduled"><i class="fas fa-history"></i> Rescheduled</span>
                                                ` : ''}
                                            </div>
                                            <div style="color: #888; font-size: 12px; margin-top: 3px;">${escapeHtml(prod.category || '')} &bull; ₱${unitPrice.toLocaleString()} ea</div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="color: #ffd700; font-weight: 700; font-size: 15px;">₱${lineTotal.toLocaleString()}</div>
                                        </div>
                                    </div>
                                    <div style="font-size: 12px; font-weight: 600; color: #aaa; margin: 8px 0 4px 0;">Item Measurements (${totalItems}):</div>
                                    ${itemsHtml}

                                    <!-- Schedule under size items (Date only) -->
                                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #333;">
                                        ${prodType === 'buy' ? `
                                            <div style="font-size: 12px; font-weight: 600; color: #ffd700; margin-bottom: 6px;"><i class="fas fa-shopping-bag"></i> Product Purchase:</div>
                                            <div style="background: rgba(0,0,0,0.25); padding: 8px 10px; border-radius: 6px;">
                                                <span style="display: block; font-size: 11px; color: #888;">Date</span>
                                                <span style="font-size: 12.5px; color: #fff; font-weight: 600;">${prodReserveDate || '-'}</span>
                                            </div>
                                        ` : `
                                            <div style="font-size: 12px; font-weight: 600; color: #ffd700; margin-bottom: 6px;"><i class="fas fa-calendar-alt"></i> Product Rental Period:</div>
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: rgba(0,0,0,0.25); padding: 8px 10px; border-radius: 6px;">
                                                <div>
                                                    <span style="display: block; font-size: 11px; color: #888;">Reserve</span>
                                                    <span style="font-size: 12.5px; color: #fff; font-weight: 600;">${prodReserveDate || '-'}</span>
                                                    ${(prod.original_reserve_date || r.original_reserve_date) ? `
                                                        <span style="display: block; font-size: 10.5px; color: #f87171; text-decoration: line-through;">was ${escapeHtml(prod.original_reserve_date || r.original_reserve_date)}</span>
                                                    ` : ''}
                                                </div>
                                                <div>
                                                    <span style="display: block; font-size: 11px; color: #888;">Return</span>
                                                    <span style="font-size: 12.5px; color: #fff; font-weight: 600;">${prodReturnDate || '-'}</span>
                                                    ${(prod.original_return_date || r.original_return_date) ? `
                                                        <span style="display: block; font-size: 10.5px; color: #f87171; text-decoration: line-through;">was ${escapeHtml(prod.original_return_date || r.original_return_date)}</span>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        `}
                                    </div>
                                </div>
                            `;
                        });

                        const isOrderRescheduled = parseInt(r.is_rescheduled || 0) === 1 || products.some(p => parseInt(p.is_rescheduled || 0) === 1);
                        const isLostReservation = (currentStatFilter === 'lost_requests') || (parseInt(r.is_lost || 0) === 1) || !!r.lost_status || products.some(p => parseInt(p.is_lost || 0) === 1 || !!p.lost_status);
                        const rescheduledDateFormatted = r.rescheduled_at ? new Date(r.rescheduled_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

                        // Update slide panel header text based on lost reservation status
                        const modalTitle = document.getElementById('reservationModalTitle');
                        if (modalTitle) {
                            if (isLostReservation) {
                                modalTitle.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #ef4444; margin-right: 8px;"></i> Lost Item';
                            } else {
                                modalTitle.innerHTML = 'Reservation Details';
                            }
                        }

                        if (isLostReservation) {
                            let lostProductsHtml = '';
                            products.forEach((prod) => {
                                let prodImg = prod.gown_image || prod.gown_model || prod.model_path || '';
                                if (prodImg && !prodImg.startsWith('http') && !prodImg.startsWith('/') && !prodImg.startsWith('../')) {
                                    prodImg = '../' + prodImg;
                                }
                                const unitPrice = parseFloat(prod.price || 0);
                                const sellingPrice = parseFloat(prod.selling_price || prod.lost_selling_price || 0);
                                const qty = Math.max(1, parseInt(prod.quantity || 1));

                                lostProductsHtml += `
                                    <div style="background: #252528; border: 1px solid #3a3a40; border-radius: 8px; padding: 12px; margin-bottom: 10px; display: flex; gap: 12px; align-items: center;">
                                        ${prodImg ? `
                                            <img src="${prodImg}" alt="${escapeHtml(prod.product_name || 'Lost Item')}" style="width: 65px; height: 65px; object-fit: cover; border-radius: 6px; border: 1px solid #ffd700; flex-shrink: 0;">
                                        ` : `
                                            <div style="width: 65px; height: 65px; border-radius: 6px; background: #333; display: flex; align-items: center; justify-content: center; color: #ffd700; flex-shrink: 0;">
                                                <i class="fas fa-tshirt" style="font-size: 24px;"></i>
                                            </div>
                                        `}
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-weight: 700; color: #fff; font-size: 14.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                ${escapeHtml(prod.product_name || 'Product')}
                                            </div>
                                            <div style="color: #aaa; font-size: 11.5px; margin-top: 2px;">
                                                ${escapeHtml(prod.category || 'Rental')} &bull; Qty: ${qty}
                                            </div>
                                            <div style="display: flex; gap: 14px; margin-top: 5px; font-size: 12px; flex-wrap: wrap;">
                                                <span style="color: #bbb;">Rental: <strong style="color: #ffd700;">₱${unitPrice.toLocaleString()}</strong></span>
                                                ${sellingPrice > 0 ? `<span style="color: #bbb;">Replacement: <strong style="color: #f87171;">₱${sellingPrice.toLocaleString()}</strong></span>` : ''}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });

                            let lostSelling = parseFloat(r.lost_selling_price || 0);
                            if (lostSelling <= 0) {
                                products.forEach(p => {
                                    lostSelling += parseFloat(p.lost_selling_price || p.selling_price || p.price || 0);
                                });
                            }
                            if (lostSelling <= 0) {
                                lostSelling = totalOrderPrice;
                            }

                            let lostPaid = parseFloat(r.lost_user_paid || 0);
                            if (lostPaid <= 0 && r.payment_option === 'full_payment') {
                                lostPaid = totalOrderPrice;
                            } else if (lostPaid <= 0 && totalOrderDownpayment > 0) {
                                lostPaid = totalOrderDownpayment;
                            }

                            let lostDiff = parseFloat(r.lost_price_diff || 0);
                            if (lostDiff <= 0) {
                                lostDiff = Math.max(0, lostSelling - lostPaid);
                            }

                            let lostOverdue = parseFloat(r.lost_overdue_fee || 0);
                            let lostTotal = parseFloat(r.lost_total_due || 0);
                            if (lostTotal <= 0) {
                                lostTotal = Math.max(0, lostDiff + lostOverdue);
                            }

                            let proofPath = r.lost_payment_proof || r.payment_proof || '';
                            let proofImgHtml = '';
                            if (proofPath) {
                                let fullImgPath = proofPath.startsWith('http') || proofPath.startsWith('/') || proofPath.startsWith('../') ? proofPath : ('../' + proofPath);
                                proofImgHtml = `
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        <div style="display: flex; align-items: center; justify-content: space-between;">
                                            <span style="font-size: 11px; color: #22c55e; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                <i class="fas fa-check-circle"></i> Proof of Settlement Payment Uploaded
                                            </span>
                                            <a href="${fullImgPath}" target="_blank" style="font-size: 11px; color: #ffd700; text-decoration: underline;">
                                                <i class="fas fa-external-link-alt"></i> Open Full Image
                                            </a>
                                        </div>
                                        <div style="background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; text-align: center;">
                                            <a href="${fullImgPath}" target="_blank" title="Click to view full receipt" style="display: inline-block;">
                                                <img src="${fullImgPath}" alt="Proof of Settlement Payment" style="max-width: 100%; max-height: 240px; object-fit: contain; border-radius: 8px; border: 2px solid #ffd700; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                                            </a>
                                        </div>
                                    </div>
                                `;
                            } else {
                                proofImgHtml = `
                                    <div style="padding: 22px; text-align: center; background: rgba(0,0,0,0.2); border: 1px dashed #555; border-radius: 8px; color: #888; font-size: 12px;">
                                        <i class="fas fa-image" style="font-size: 28px; color: #666; margin-bottom: 8px; display: block;"></i>
                                        No proof of settlement payment uploaded yet
                                    </div>
                                `;
                            }

                            let confirmActionHtml = '';
                            if (r.lost_status === 'settled') {
                                confirmActionHtml = `
                                    <div style="margin-top: 18px; padding-top: 16px; border-top: 1px solid #333;">
                                        <div style="background: rgba(34, 197, 94, 0.12); border: 1.5px solid rgba(34, 197, 94, 0.4); border-radius: 8px; padding: 12px 16px; text-align: center; color: #4ade80; font-weight: 700; font-size: 13.5px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                            <i class="fas fa-check-circle" style="font-size: 17px;"></i> Settlement Confirmed & Completed
                                        </div>
                                    </div>
                                `;
                            } else {
                                confirmActionHtml = `
                                    <div style="margin-top: 18px; padding-top: 16px; border-top: 1px solid #333;">
                                        <button type="button" class="res-action-btn res-action-btn-confirm" onclick="settleLostItem(${r.id}, 'settle')" style="background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; font-weight: 700; border: none; border-radius: 8px; padding: 13px 20px; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; box-shadow: 0 4px 14px rgba(34, 197, 94, 0.35); transition: opacity 0.2s;">
                                            <i class="fas fa-check-circle" style="font-size: 16px;"></i> Confirm
                                        </button>
                                    </div>
                                `;
                            }

                            modalBody.innerHTML = `
                                <div style="display: flex; flex-direction: column; gap: 14px;">
                                    <!-- Customer Summary & Status -->
                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; background: rgba(0,0,0,0.25); border: 1px solid #333; border-radius: 8px; padding: 10px 14px;">
                                        <div>
                                            <div style="color: #ffd700; font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 6px;">
                                                <i class="fas fa-user-circle"></i> ${escapeHtml((r.first_name || '') + ' ' + (r.last_name || ''))}
                                            </div>
                                            <div style="color: #888; font-size: 11.5px; margin-top: 2px;">
                                                Code: <span style="color: #ffd700; font-family: monospace;">${escapeHtml(r.reservation_code || ('#' + r.id))}</span>
                                                ${(r.reserve_date || r.return_date) ? ` &bull; Rental: ${escapeHtml(r.reserve_date || '')} &rarr; ${escapeHtml(r.return_date || '')}` : ''}
                                            </div>
                                        </div>
                                        <div>
                                            <span style="font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; ${r.lost_status === 'settled' ? 'background: rgba(34,197,94,0.15); color: #22c55e; border: 1px solid rgba(34,197,94,0.3);' : 'background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.3);'}">
                                                <i class="fas ${r.lost_status === 'settled' ? 'fa-check-circle' : 'fa-clock'}"></i> ${r.lost_status === 'settled' ? 'Settled' : 'Pending Settlement'}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Lost Item Section -->
                                    <div class="res-detail-section" style="margin-bottom: 0;">
                                        <div class="res-detail-section-title"><i class="fas fa-tshirt"></i> Lost Item (${products.length})</div>
                                        ${lostProductsHtml}
                                    </div>

                                    <!-- Settlement Summary Section -->
                                    <div class="res-detail-section" style="border-left: 3px solid #ef4444; margin-bottom: 0; padding: 12px 14px;">
                                        <div class="res-detail-section-title" style="color: #ffd700; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                                            <span><i class="fas fa-calculator" style="color: #ef4444;"></i> Settlement Summary</span>
                                            <span style="font-size: 11px; color: ${r.lost_status === 'settled' ? '#22c55e' : '#ef4444'}; font-weight: 700;">
                                                ${r.lost_status === 'settled' ? 'Settled' : 'Pending Settlement'}
                                            </span>
                                        </div>
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 8px; font-size: 12px; margin-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 10px;">
                                            <div>
                                                <span style="display: block; color: #888; font-size: 10px; text-transform: uppercase;">Selling Price</span>
                                                <span style="color: #ffd700; font-weight: 700;">₱${lostSelling.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                            </div>
                                            <div>
                                                <span style="display: block; color: #888; font-size: 10px; text-transform: uppercase;">Paid (Rental)</span>
                                                <span style="color: #22c55e; font-weight: 700;">-₱${lostPaid.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                            </div>
                                            <div>
                                                <span style="display: block; color: #888; font-size: 10px; text-transform: uppercase;">Price Diff</span>
                                                <span style="color: #ccc; font-weight: 700;">₱${lostDiff.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                            </div>
                                            <div>
                                                <span style="display: block; color: #888; font-size: 10px; text-transform: uppercase;">Overdue Fee</span>
                                                <span style="color: #ef4444; font-weight: 700;">+₱${lostOverdue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                            </div>
                                            <div>
                                                <span style="display: block; color: #888; font-size: 10px; text-transform: uppercase;">Total Due</span>
                                                <span style="color: #ef4444; font-weight: 800; font-size: 14px;">₱${lostTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                            </div>
                                        </div>
                                        ${r.lost_reason ? `
                                            <div style="font-size: 12px; color: #ccc; margin-top: 6px; background: rgba(0,0,0,0.2); padding: 8px 10px; border-radius: 6px;">
                                                <strong style="color: #ef4444;"><i class="fas fa-comment-alt"></i> Reason:</strong> ${escapeHtml(r.lost_reason)}
                                            </div>
                                        ` : ''}
                                    </div>

                                    <!-- Proof of Settlement Payment Section -->
                                    <div class="res-detail-section" style="margin-bottom: 0;">
                                        <div class="res-detail-section-title"><i class="fas fa-receipt"></i> Proof of Settlement Payment</div>
                                        ${proofImgHtml}
                                    </div>

                                    <!-- Lower Action Button: Confirm -->
                                    ${confirmActionHtml}
                                </div>
                            `;
                        } else {
                            modalBody.innerHTML = `
                            <div style="display: flex; flex-direction: column; gap: 0;">

                                ${isOrderRescheduled ? `
                                    <!-- Rescheduled Notification Banner for Admin -->
                                    <div class="res-detail-section" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.18), rgba(180, 83, 9, 0.1)); border: 1.5px solid #f59e0b; border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; box-shadow: 0 4px 16px rgba(245, 158, 11, 0.15);">
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; margin-bottom: 10px;">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span style="color: #fbbf24; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 5px;">
                                                    <i class="fas fa-history"></i> Rescheduled
                                                </span>
                                                <span style="color: #fbbf24; font-weight: 700; font-size: 13.5px;">Customer Rescheduled This Reservation</span>
                                            </div>
                                            ${rescheduledDateFormatted ? `<span style="font-size: 11px; color: #d1d5db; background: rgba(0,0,0,0.3); padding: 2px 8px; border-radius: 4px;"><i class="far fa-clock"></i> ${rescheduledDateFormatted}</span>` : ''}
                                        </div>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: rgba(0,0,0,0.35); padding: 8px 12px; border-radius: 8px; font-size: 12px; margin-bottom: 8px;">
                                            <div>
                                                <span style="display: block; color: #9ca3af; font-size: 10.5px; text-transform: uppercase; font-weight: 600;">Original (1st) Dates</span>
                                                <span style="color: #f87171; font-weight: 600; text-decoration: line-through;">
                                                    ${escapeHtml(r.original_reserve_date || '-')} &rarr; ${escapeHtml(r.original_return_date || '-')}
                                                </span>
                                            </div>
                                            <div>
                                                <span style="display: block; color: #9ca3af; font-size: 10.5px; text-transform: uppercase; font-weight: 600;">Current (Rescheduled) Dates</span>
                                                <span style="color: #4ade80; font-weight: 700;">
                                                    ${escapeHtml(r.reserve_date || '-')} &rarr; ${escapeHtml(r.return_date || '-')}
                                                </span>
                                            </div>
                                        </div>
                                        ${r.reschedule_reason ? `
                                            <div style="font-size: 12px; color: #f3f4f6; background: rgba(0,0,0,0.25); padding: 7px 10px; border-radius: 6px; border-left: 3px solid #f59e0b;">
                                                <strong style="color: #fbbf24;">Reschedule Reason:</strong> ${escapeHtml(r.reschedule_reason)}
                                            </div>
                                        ` : ''}
                                    </div>
                                ` : ''}

                                <!-- Reserved Products & Details (At the top) -->
                                <div class="res-detail-section">
                                    <div class="res-detail-section-title"><i class="fas fa-tag"></i> Reserved Products & Details (${products.length})</div>
                                    ${productsHtml}
                                </div>

                                <!-- Reservation Code & QR Code + Customer Valid ID (Compact Action Buttons Side-by-Side to Maximize Space) -->
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 14px;">
                                    <!-- Reservation Code & QR Code Button -->
                                    <button type="button" class="res-detail-qr-btn" onclick="showAdminReservationQR(decodeURIComponent('${encodeURIComponent(r.reservation_code || r.id)}'))" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; background: #222226; border: 1px solid rgba(255,215,0,0.35); border-radius: 8px; color: #fff; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(0,0,0,0.25); text-align: left;">
                                        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                                            <div style="width: 34px; height: 34px; border-radius: 8px; background: rgba(255,215,0,0.15); border: 1px solid rgba(255,215,0,0.4); display: flex; align-items: center; justify-content: center; color: #ffd700; font-size: 15px; flex-shrink: 0;">
                                                <i class="fas fa-qrcode"></i>
                                            </div>
                                            <div style="min-width: 0;">
                                                <div style="font-size: 9.5px; color: #888; line-height: 1.2; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Reservation Code</div>
                                                <div style="font-size: 13px; font-weight: 700; color: #ffd700; font-family: monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(r.reservation_code || 'N/A')}</div>
                                            </div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 700; color: #ffd700; background: rgba(255,215,0,0.12); padding: 6px 10px; border-radius: 6px; border: 1px solid rgba(255,215,0,0.25); flex-shrink: 0; margin-left: 6px;">
                                            <i class="fas fa-expand"></i> QR
                                        </div>
                                    </button>

                                    <!-- Customer Valid ID Button -->
                                    <button type="button" id="btnViewCustomerValidId" class="res-detail-qr-btn" onclick="showAdminValidIdCard(decodeURIComponent('${encodeURIComponent(r.id_front_image || '')}'), decodeURIComponent('${encodeURIComponent(r.id_type || '')}'), decodeURIComponent('${encodeURIComponent(((r.first_name || '') + ' ' + (r.last_name || '')).trim())}'))" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; background: #222226; border: 1px solid rgba(255,215,0,0.35); border-radius: 8px; color: #fff; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(0,0,0,0.25); text-align: left;">
                                        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                                            <div style="width: 34px; height: 34px; border-radius: 8px; background: rgba(255,215,0,0.15); border: 1px solid rgba(255,215,0,0.4); display: flex; align-items: center; justify-content: center; color: #ffd700; font-size: 15px; flex-shrink: 0;">
                                                <i class="fas fa-id-card"></i>
                                            </div>
                                            <div style="min-width: 0;">
                                                <div style="font-size: 9.5px; color: #888; line-height: 1.2; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Customer Valid ID</div>
                                                <div style="font-size: 12.5px; font-weight: 700; color: ${r.id_front_image ? '#ffd700' : '#888'}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(r.id_type || (r.id_front_image ? 'Uploaded' : 'No ID uploaded'))}</div>
                                            </div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 700; color: #ffd700; background: rgba(255,215,0,0.12); padding: 6px 10px; border-radius: 6px; border: 1px solid rgba(255,215,0,0.25); flex-shrink: 0; margin-left: 6px;">
                                            <i class="fas fa-expand"></i> ID
                                        </div>
                                    </button>
                                </div>

                                <!-- Customer Info & Payment -->
                                <div class="res-detail-section">
                                    <div class="res-detail-section-title"><i class="fas fa-user"></i> Customer & Payment</div>
                                    <div class="res-detail-grid">
                                        <div class="res-detail-field">
                                            <span class="res-detail-label">Customer Name</span>
                                            <span class="res-detail-value">${escapeHtml((r.first_name || '') + ' ' + (r.last_name || ''))}</span>
                                        </div>
                                        <div class="res-detail-field">
                                            <span class="res-detail-label">Payment Option</span>
                                            <span class="res-detail-value">${r.payment_option === 'first_payment' ? (totalOrderDownpayment > 0 ? `First Payment (₱${totalOrderDownpayment.toLocaleString()} Deposit)` : 'First Payment (Deposit)') : '<span style="color: #4ade80; font-weight: 700;">Full Payment (Paid)</span>'}</span>
                                        </div>
                                        ${r.payment_option === 'first_payment' ? `
                                            <div class="res-detail-field">
                                                <span class="res-detail-label">Deposit Paid</span>
                                                <span class="res-detail-value" style="color: #6cd488;">₱${totalOrderDownpayment.toLocaleString()}</span>
                                            </div>
                                            <div class="res-detail-field">
                                                <span class="res-detail-label">Due on Pickup</span>
                                                <span class="res-detail-value" style="color: #f87171; font-weight: 700;">₱${totalOrderBalance.toLocaleString()}</span>
                                            </div>
                                        ` : ''}
                                        <div class="res-detail-field res-detail-full">
                                            <span class="res-detail-label">Total Amount</span>
                                            <span class="res-detail-value gold" style="font-size: 1.1rem;">₱${totalOrderPrice.toLocaleString()}</span>
                                        </div>

                                        <!-- User Uploaded Payment Proof Section -->
                                        <div class="res-detail-field res-detail-full" style="margin-top: 10px; border-top: 1px solid #383b45; padding-top: 10px;">
                                            <span class="res-detail-label" style="display: flex; align-items: center; gap: 6px; font-weight: 700; color: #ffd700; margin-bottom: 6px;">
                                                Uploaded Payment
                                            </span>
                                            ${(() => {
                                                let proofPath = r.payment_proof || '';
                                                if (proofPath) {
                                                    let fullImgPath = proofPath.startsWith('http') || proofPath.startsWith('/') || proofPath.startsWith('../') ? proofPath : ('../' + proofPath);
                                                    return `
                                                        <div>
                                                            <a href="${fullImgPath}" target="_blank" title="Click to view full size receipt" style="display: inline-block;">
                                                                <img src="${fullImgPath}" alt="Uploaded Payment" style="width: 85px; height: 85px; object-fit: cover; border-radius: 6px; border: 2px solid #ffd700; cursor: pointer; display: block; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                                                            </a>
                                                        </div>
                                                    `;
                                                } else {
                                                    return `
                                                        <div style="font-size: 12px; color: #888; font-style: italic;">
                                                            No payment proof uploaded
                                                        </div>
                                                    `;
                                                }
                                            })()}
                                        </div>
                                    </div>
                                </div>

                                <!-- Manage Status Actions -->
                                <div class="res-detail-section">
                                    <div class="res-detail-section-title"><i class="fas fa-cog"></i> Manage Status</div>
                                    <div class="res-detail-actions">
                                        <button class="res-action-btn res-action-btn-confirm" onclick="promptStatusChange(${r.id}, 'confirmed')" ${status === 'confirmed' || status === 'completed' || status === 'cancelled' ? 'disabled' : ''}>Confirm</button>
                                        <button class="res-action-btn res-action-btn-complete" onclick="promptStatusChange(${r.id}, 'completed')" ${status === 'completed' || status === 'cancelled' ? 'disabled' : ''}>Complete</button>
                                        <button class="res-action-btn res-action-btn-cancel" onclick="openCancellationModal(${r.id}, '${escapeHtml(isMulti ? products.length + ' Products' : r.product_name)}', '${escapeHtml((r.first_name || '') + ' ' + (r.last_name || ''))}', '${escapeHtml(r.reserve_date || '')}')" ${status === 'cancelled' || status === 'completed' ? 'disabled' : ''}>Cancel</button>
                                    </div>
                                </div>

                                ${status === 'cancelled' && (r.cancellation_reason || r.cancellation_images) ? `
                                    <!-- Cancellation Explanation & Images -->
                                    <div class="res-detail-section" style="border-color: rgba(244, 67, 54, 0.4); background: rgba(244, 67, 54, 0.05);">
                                        <div class="res-detail-section-title" style="color: #f44336;"><i class="fas fa-exclamation-circle"></i> Cancellation Reason & Photos</div>
                                        <div style="font-size: 0.85rem; color: #e0e0e0; margin-bottom: 8px; line-height: 1.4;">
                                            <strong>Admin Explanation:</strong> ${r.cancellation_reason || 'No explanation recorded.'}
                                        </div>
                                        ${(() => {
                                            try {
                                                const imgs = JSON.parse(r.cancellation_images || '[]');
                                                if (Array.isArray(imgs) && imgs.length > 0) {
                                                    return '<div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">' +
                                                        imgs.map(img => `<img src="../${img}" alt="Problem" onclick="window.open('../${img}', '_blank')" style="width: 70px; height: 70px; object-fit: cover; border-radius: 6px; border: 1px solid #f44336; cursor: pointer;" title="Click to view full image">`).join('') +
                                                        '</div>';
                                                }
                                            } catch(e) {}
                                            return '';
                                        })()}
                                    </div>
                                ` : ''}

                            </div>
                        `;
                        }
                        
                        const reservationModal = document.getElementById('reservationModal');
                        reservationModal.classList.add('slide-panel-open');
                        document.body.style.overflow = 'hidden';
                        document.body.classList.add('slide-panel-open');
                    } else {
                        showNotification('Load Failed', 'Error loading reservation: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error', 'Error loading reservation details', 'error');
                });
        }
        
        // ===== CUSTOMER VALID ID MODAL (single card: front incl. user) =====
        function showAdminValidIdCard(frontImg, idType, customerName) {
            let modal = document.getElementById('adminValidIdModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'adminValidIdModal';
                modal.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:10000050; display:flex; align-items:center; justify-content:center; padding:20px;';
                modal.onclick = () => { modal.style.display = 'none'; };
                document.body.appendChild(modal);
            }

            let imgHtml;
            if (frontImg) {
                const path = (frontImg.startsWith('http') || frontImg.startsWith('/') || frontImg.startsWith('../')) ? frontImg : ('../' + frontImg);
                imgHtml = `
                    <a href="${escapeHtml(path)}" target="_blank" title="Open full size">
                        <img src="${escapeHtml(path)}" alt="Card Front Including User" style="width:100%; max-height:60vh; object-fit:contain; border-radius:10px; border:2px solid #ffd700; background:#111; display:block;">
                    </a>`;
            } else {
                imgHtml = '<div style="padding:40px 10px; color:#888; font-size:13px; font-style:italic; border:1px dashed #444; border-radius:10px;">No ID image uploaded</div>';
            }

            modal.innerHTML = `
                <div style="background:#1a1a1a; border:2px solid #ffd700; border-radius:18px; padding:22px; max-width:480px; width:100%; text-align:center; box-shadow:0 12px 48px rgba(0,0,0,0.9);" onclick="event.stopPropagation()">
                    <div style="color:#ffd700; font-size:17px; font-weight:800; margin-bottom:4px;"><i class="fas fa-id-card"></i> Customer Valid ID</div>
                    <div style="color:#aaa; font-size:12px; margin-bottom:14px;">${escapeHtml(customerName || 'Customer')} &middot; ${escapeHtml(idType || 'ID type not specified')}</div>
                    <div style="font-size:11px; color:#ffd700; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:8px; text-align:left;">Card Front (Including User)</div>
                    ${imgHtml}
                    <button type="button" onclick="document.getElementById('adminValidIdModal').style.display='none'" style="margin-top:16px; background:linear-gradient(135deg,#7c6300,#b8960c); color:#fff; border:none; border-radius:24px; padding:9px 28px; font-size:12px; font-weight:700; cursor:pointer;">Close</button>
                </div>
            `;
            modal.style.display = 'flex';
        }

        // ===== QR CODE MODAL FOR ADMIN RESERVATION DETAILS =====
        function showAdminReservationQR(code) {
            if (!code) return;
            let modal = document.getElementById('adminReservationQRModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'adminReservationQRModal';
                modal.style.cssText = 'position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.85); z-index:10000050; display:flex; align-items:center; justify-content:center; padding:20px;';
                modal.onclick = () => { modal.style.display = 'none'; };
                document.body.appendChild(modal);
            } else {
                modal.style.zIndex = '10000050';
            }

            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(code)}`;
            modal.innerHTML = `
                <div style="background:#1a1a1a; border:2px solid #ffd700; border-radius:18px; padding:24px; max-width:360px; width:100%; text-align:center; box-shadow:0 12px 48px rgba(0,0,0,0.9);" onclick="event.stopPropagation()">
                    <div style="color:#ffd700; font-size:17px; font-weight:800; margin-bottom:4px;"><i class="fas fa-qrcode"></i> Reservation QR Code</div>
                    <div style="color:#aaa; font-size:12px; margin-bottom:16px;">Scan to verify reservation</div>
                    <div style="background:#ffffff; padding:14px; border-radius:14px; display:inline-block; margin-bottom:14px; box-shadow:0 4px 16px rgba(0,0,0,0.4);">
                        <img src="${qrUrl}" alt="Reservation QR" style="width:200px; height:200px; display:block;">
                    </div>
                    <div style="color:#ffd700; font-size:16px; font-weight:800; letter-spacing:1px; margin-bottom:3px; font-family: monospace;">${escapeHtml(code)}</div>
                    <div style="color:#777; font-size:11px; margin-bottom:16px;">Reservation Code</div>
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button type="button" onclick="navigator.clipboard.writeText('${escapeHtml(code)}'); this.innerHTML='<i class=\\'fas fa-check\\'></i> Copied!'; setTimeout(()=>{this.innerHTML='<i class=\\'far fa-copy\\'></i> Copy Code';}, 2000);" style="background:#2b2d35; border:1px solid #444; color:#ddd; border-radius:24px; padding:9px 18px; font-size:12px; font-weight:600; cursor:pointer;">
                            <i class="far fa-copy"></i> Copy Code
                        </button>
                        <button type="button" onclick="document.getElementById('adminReservationQRModal').style.display='none'" style="background:linear-gradient(135deg,#7c6300,#b8960c); color:#fff; border:none; border-radius:24px; padding:9px 24px; font-size:12px; font-weight:700; cursor:pointer;">Close</button>
                    </div>
                </div>
            `;
            modal.style.display = 'flex';
        }
        
        let notificationTimeout = null;
        let notificationCallback = null;
        let notificationCancelCallback = null;

        function showNotification(title, message, type = 'success', duration = 4000, onOk = null) {
            const toast = document.getElementById('customNotification');
            if (!toast) return;

            notificationCallback = onOk;
            notificationCancelCallback = null;

            const iconWrap = document.getElementById('customToastIcon');
            const titleEl = document.getElementById('customToastTitle');
            const msgEl = document.getElementById('customToastMessage');
            const cancelBtn = document.getElementById('customToastCancelBtn');
            const okBtn = document.getElementById('customToastOkBtn');

            if (cancelBtn) cancelBtn.style.display = 'none';
            if (okBtn) okBtn.textContent = 'OK';

            titleEl.textContent = title;
            msgEl.textContent = message;

            iconWrap.className = 'custom-toast-icon-wrap ' + type;
            if (type === 'error') {
                iconWrap.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
            } else if (type === 'info') {
                iconWrap.innerHTML = '<i class="fas fa-info-circle"></i>';
            } else if (type === 'warning') {
                iconWrap.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
            } else {
                iconWrap.innerHTML = '<i class="fas fa-check-circle"></i>';
            }

            toast.classList.add('show');

            if (notificationTimeout) clearTimeout(notificationTimeout);
            if (duration > 0) {
                notificationTimeout = setTimeout(() => {
                    hideNotification(true);
                }, duration);
            }
        }

        function showConfirmNotification(title, message, onConfirm, onCancel = null, okText = 'OK') {
            const toast = document.getElementById('customNotification');
            if (!toast) return;

            notificationCallback = onConfirm;
            notificationCancelCallback = onCancel;

            const iconWrap = document.getElementById('customToastIcon');
            const titleEl = document.getElementById('customToastTitle');
            const msgEl = document.getElementById('customToastMessage');
            const cancelBtn = document.getElementById('customToastCancelBtn');
            const okBtn = document.getElementById('customToastOkBtn');

            if (cancelBtn) cancelBtn.style.display = 'inline-block';
            if (okBtn) okBtn.textContent = okText || 'OK';

            titleEl.textContent = title;
            msgEl.textContent = message;

            iconWrap.className = 'custom-toast-icon-wrap warning';
            iconWrap.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';

            toast.classList.add('show');

            if (notificationTimeout) clearTimeout(notificationTimeout);
        }

        function hideNotification(isOk = true) {
            const toast = document.getElementById('customNotification');
            if (toast) toast.classList.remove('show');
            if (notificationTimeout) clearTimeout(notificationTimeout);

            if (isOk) {
                if (notificationCallback) {
                    const cb = notificationCallback;
                    notificationCallback = null;
                    notificationCancelCallback = null;
                    cb();
                }
            } else {
                if (notificationCancelCallback) {
                    const cb = notificationCancelCallback;
                    notificationCallback = null;
                    notificationCancelCallback = null;
                    cb();
                }
            }
        }

        function promptStatusChange(reservationId, newStatus) {
            showConfirmNotification(
                'Update Status?',
                `Are you sure you want to mark Reservation #${reservationId} as ${newStatus}?`,
                () => {
                    updateOrderStatus(reservationId, newStatus);
                }
            );
        }

        function updateOrderStatus(reservationId, newStatus) {
            fetch('update_order_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(reservationId) + '&status=' + encodeURIComponent(newStatus)
            })
                .then(async response => {
                    const text = await response.text();
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(text || 'Invalid server response');
                    }
                })
                .then(data => {
                    if (data.success) {
                        closeModal();
                        window.location.reload();
                    } else {
                        showNotification('Update Failed', data.message || 'Unknown error', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error updating status:', error);
                    showNotification('Error', error.message || 'Error updating reservation status', 'error');
                });
        }
        
        function closeModal() {
            closeCancellationModal();
            const reservationModal = document.getElementById('reservationModal');
            if (reservationModal) {
                reservationModal.classList.remove('slide-panel-open');
                reservationModal.style.zIndex = '';
            }

            const modalTitle = document.getElementById('reservationModalTitle');
            if (modalTitle) {
                modalTitle.innerHTML = 'Reservation Details';
            }
            
            // Reset z-index of parent panels
            const dayModal = document.getElementById('dayModal');
            const statFilterModal = document.getElementById('statFilterModal');
            if (dayModal) dayModal.style.zIndex = '';
            if (statFilterModal) statFilterModal.style.zIndex = '';
            
            // Don't remove body classes if parent slide panels are still open
            if (!dayModal.classList.contains('slide-panel-open') && !statFilterModal.classList.contains('slide-panel-open')) {
                document.body.classList.remove('slide-panel-open');
                document.body.style.overflow = 'auto';
            }
        }

        // ===== Cancellation Modal Handlers =====
        let cancelSelectedFiles = [];

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function handleCancellationReasonChange(selectEl) {
            if (!selectEl) return;
            const val = selectEl.value;
            const starEl = document.getElementById('notesRequiredStar');
            const notesEl = document.getElementById('cancellationReasonNotes');
            
            // Update active state on quick chips
            document.querySelectorAll('.cancel-quick-chip').forEach(chip => {
                chip.classList.toggle('active', chip.getAttribute('data-reason') === val);
            });

            if (val === 'Other Reason') {
                if (starEl) starEl.style.display = 'inline';
                if (notesEl) {
                    notesEl.required = true;
                    notesEl.placeholder = 'Please explain the specific cancellation reason for the customer (Required)...';
                    notesEl.focus();
                }
            } else {
                if (starEl) starEl.style.display = 'none';
                if (notesEl) {
                    notesEl.required = false;
                    notesEl.placeholder = 'Provide extra details or instructions for the customer (optional if standard reason selected)...';
                }
            }
        }

        function selectCancelChip(chipEl, reasonText) {
            const selectEl = document.getElementById('cancellationReasonSelect');
            if (!selectEl) return;
            selectEl.value = reasonText;
            handleCancellationReasonChange(selectEl);
        }

        function openCancellationModal(reservationId, productName, customerName, reserveDate) {
            cancelSelectedFiles = [];
            document.getElementById('cancelReservationId').value = reservationId;

            // Reset reason select and notes
            const selectEl = document.getElementById('cancellationReasonSelect');
            if (selectEl) selectEl.value = '';
            const notesEl = document.getElementById('cancellationReasonNotes');
            if (notesEl) {
                notesEl.value = '';
                notesEl.required = false;
                notesEl.placeholder = 'Provide extra details or instructions for the customer (optional if standard reason selected)...';
            }
            const starEl = document.getElementById('notesRequiredStar');
            if (starEl) starEl.style.display = 'none';

            // Reset chips
            document.querySelectorAll('.cancel-quick-chip').forEach(c => c.classList.remove('active'));

            document.getElementById('cancelImagesPreview').innerHTML = '';
            document.getElementById('cancelImagesInput').value = '';

            const summaryEl = document.getElementById('cancelReservationSummary');
            if (summaryEl) {
                summaryEl.innerHTML = `
                    <div style="font-weight: 600; color: #fff; margin-bottom: 4px;">${productName || ('Reservation #' + reservationId)}</div>
                    <div style="color: #aaa; font-size: 0.8rem;">Customer: <span style="color: #FFD700;">${customerName || 'N/A'}</span> &bull; Date: ${reserveDate || 'N/A'}</div>
                `;
            }

            // Put Cancel Reservation IN FRONT of Reservation Details modal
            const cancelModal = document.getElementById('cancellationModal');
            if (cancelModal) {
                cancelModal.style.zIndex = '100005';
                cancelModal.classList.add('slide-panel-open');
            }

            const backdrop = document.getElementById('cancellationBackdrop');
            if (backdrop) backdrop.classList.add('active');

            document.body.style.overflow = 'hidden';
            document.body.classList.add('slide-panel-open');
        }

        function closeCancellationModal() {
            const cancelModal = document.getElementById('cancellationModal');
            if (cancelModal) {
                cancelModal.classList.remove('slide-panel-open');
                cancelModal.style.zIndex = '';
            }

            const backdrop = document.getElementById('cancellationBackdrop');
            if (backdrop) backdrop.classList.remove('active');

            const reservationModal = document.getElementById('reservationModal');
            const dayModal = document.getElementById('dayModal');
            const statFilterModal = document.getElementById('statFilterModal');

            // If reservationModal or another panel is still open, keep slide-panel-open active
            if (reservationModal && reservationModal.classList.contains('slide-panel-open')) {
                document.body.classList.add('slide-panel-open');
                document.body.style.overflow = 'hidden';
            } else if ((!dayModal || !dayModal.classList.contains('slide-panel-open')) &&
                       (!statFilterModal || !statFilterModal.classList.contains('slide-panel-open'))) {
                document.body.classList.remove('slide-panel-open');
                document.body.style.overflow = 'auto';
            }

            cancelSelectedFiles = [];
        }

        // Upload Valid ID Modal Functions
        let uploadedIdFrontFile = null;
        let uploadedIdBackFile = null;

        function openUploadIdModal(userId, userName) {
            const modal = document.getElementById('uploadIdModal');
            const userInfoEl = document.getElementById('uploadIdUserInfo');
            const userIdInput = document.getElementById('uploadIdUserId');

            if (!modal) return;

            if (userInfoEl) {
                userInfoEl.innerHTML = `<strong>Customer:</strong> ${escapeHtml(userName || 'Unknown')}<br><strong>User ID:</strong> ${userId || 'N/A'}`;
            }
            if (userIdInput) userIdInput.value = userId || '';

            // Reset form and state
            document.getElementById('uploadIdForm').reset();
            document.getElementById('uploadIdFrontPreview').innerHTML = '';
            document.getElementById('uploadIdBackPreview').innerHTML = '';
            uploadedIdFrontFile = null;
            uploadedIdBackFile = null;

            modal.classList.add('slide-panel-open');
            modal.style.zIndex = '100002';
            document.body.style.overflow = 'hidden';
            document.body.classList.add('slide-panel-open');
        }

        function closeUploadIdModal() {
            const modal = document.getElementById('uploadIdModal');
            if (modal) {
                modal.classList.remove('slide-panel-open');
                modal.style.zIndex = '';
            }

            // Don't remove body classes if other modals are still open
            const reservationModal = document.getElementById('reservationModal');
            if (!reservationModal.classList.contains('slide-panel-open')) {
                document.body.classList.remove('slide-panel-open');
                document.body.style.overflow = 'auto';
            }
        }

        function handleUploadIdFrontSelect(input) {
            if (input.files && input.files[0]) {
                uploadedIdFrontFile = input.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('uploadIdFrontPreview');
                    preview.innerHTML = `
                        <div style="position: relative; display: inline-block;">
                            <img src="${e.target.result}" alt="ID Front Preview" style="max-width: 200px; max-height: 200px; border-radius: 6px; border: 2px solid #ffd700;">
                            <button type="button" onclick="removeUploadIdFront()" style="position: absolute; top: -8px; right: -8px; background: #f44336; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px;">×</button>
                        </div>
                    `;
                };
                reader.readAsDataURL(uploadedIdFrontFile);
            }
        }

        function handleUploadIdFrontDrop(event) {
            event.preventDefault();
            if (event.dataTransfer.files && event.dataTransfer.files[0]) {
                uploadedIdFrontFile = event.dataTransfer.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('uploadIdFrontPreview');
                    preview.innerHTML = `
                        <div style="position: relative; display: inline-block;">
                            <img src="${e.target.result}" alt="ID Front Preview" style="max-width: 200px; max-height: 200px; border-radius: 6px; border: 2px solid #ffd700;">
                            <button type="button" onclick="removeUploadIdFront()" style="position: absolute; top: -8px; right: -8px; background: #f44336; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px;">×</button>
                        </div>
                    `;
                };
                reader.readAsDataURL(uploadedIdFrontFile);
            }
        }

        function removeUploadIdFront() {
            const input = document.getElementById('uploadIdFrontInput');
            input.value = '';
            uploadedIdFrontFile = null;
            document.getElementById('uploadIdFrontPreview').innerHTML = '';
        }

        function handleUploadIdBackSelect(input) {
            if (input.files && input.files[0]) {
                uploadedIdBackFile = input.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('uploadIdBackPreview');
                    preview.innerHTML = `
                        <div style="position: relative; display: inline-block;">
                            <img src="${e.target.result}" alt="ID Back Preview" style="max-width: 200px; max-height: 200px; border-radius: 6px; border: 2px solid #ffd700;">
                            <button type="button" onclick="removeUploadIdBack()" style="position: absolute; top: -8px; right: -8px; background: #f44336; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px;">×</button>
                        </div>
                    `;
                };
                reader.readAsDataURL(uploadedIdBackFile);
            }
        }

        function handleUploadIdBackDrop(event) {
            event.preventDefault();
            if (event.dataTransfer.files && event.dataTransfer.files[0]) {
                uploadedIdBackFile = event.dataTransfer.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('uploadIdBackPreview');
                    preview.innerHTML = `
                        <div style="position: relative; display: inline-block;">
                            <img src="${e.target.result}" alt="ID Back Preview" style="max-width: 200px; max-height: 200px; border-radius: 6px; border: 2px solid #ffd700;">
                            <button type="button" onclick="removeUploadIdBack()" style="position: absolute; top: -8px; right: -8px; background: #f44336; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px;">×</button>
                        </div>
                    `;
                };
                reader.readAsDataURL(uploadedIdBackFile);
            }
        }

        function removeUploadIdBack() {
            const input = document.getElementById('uploadIdBackInput');
            input.value = '';
            uploadedIdBackFile = null;
            document.getElementById('uploadIdBackPreview').innerHTML = '';
        }

        function submitUploadId(event) {
            event.preventDefault();

            const form = document.getElementById('uploadIdForm');
            const formData = new FormData(form);
            const btn = document.getElementById('confirmUploadIdBtn');

            // Validate required fields
            const idType = document.getElementById('uploadIdType').value;

            if (!idType) {
                showNotification('Validation Error', 'Please select an ID type', 'error');
                return;
            }

            if (!uploadedIdFrontFile) {
                showNotification('Validation Error', 'Please upload the front side of the ID', 'error');
                return;
            }

            // Add files to FormData
            formData.append('id_front_image', uploadedIdFrontFile);
            if (uploadedIdBackFile) {
                formData.append('id_back_image', uploadedIdBackFile);
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

            fetch('upload_customer_id.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Success', 'Valid ID uploaded successfully', 'success');
                    closeUploadIdModal();
                    // Refresh reservation details if modal is open
                    if (currentReservationId) {
                        showReservationDetails(currentReservationId);
                    }
                } else {
                    showNotification('Upload Failed', data.message || 'Error uploading ID', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error', 'Error uploading valid ID', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload"></i> Upload ID';
            });
        }

        function handleCancelImagesSelect(input) {
            if (input.files && input.files.length > 0) {
                for (let i = 0; i < input.files.length; i++) {
                    cancelSelectedFiles.push(input.files[i]);
                }
                renderCancelImagesPreview();
            }
        }

        function handleCancelImagesDrop(e) {
            e.preventDefault();
            e.currentTarget.style.borderColor = '#383b45';
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                for (let i = 0; i < e.dataTransfer.files.length; i++) {
                    if (e.dataTransfer.files[i].type.startsWith('image/')) {
                        cancelSelectedFiles.push(e.dataTransfer.files[i]);
                    }
                }
                renderCancelImagesPreview();
            }
        }

        function removeCancelImage(index) {
            cancelSelectedFiles.splice(index, 1);
            renderCancelImagesPreview();
        }

        function renderCancelImagesPreview() {
            const previewContainer = document.getElementById('cancelImagesPreview');
            previewContainer.innerHTML = '';
            cancelSelectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.style.cssText = 'position: relative; width: 70px; height: 70px; border-radius: 6px; overflow: hidden; border: 1px solid #555;';
                    item.innerHTML = `
                        <img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">
                        <button type="button" onclick="removeCancelImage(${index})" style="position: absolute; top: 2px; right: 2px; background: rgba(0,0,0,0.7); color: #fff; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1;">&times;</button>
                    `;
                    previewContainer.appendChild(item);
                };
                reader.readAsDataURL(file);
            });
        }

        function submitCancellation(event) {
            event.preventDefault();
            const resId = document.getElementById('cancelReservationId').value;
            const selectEl = document.getElementById('cancellationReasonSelect');
            const selectedReason = selectEl ? selectEl.value.trim() : '';
            const notesEl = document.getElementById('cancellationReasonNotes');
            const notes = notesEl ? notesEl.value.trim() : '';

            if (!selectedReason) {
                showNotification('Reason Required', 'Please select a cancellation reason from the choices.', 'error');
                if (selectEl) selectEl.focus();
                return;
            }

            if (selectedReason === 'Other Reason' && !notes) {
                showNotification('Explanation Required', 'Please specify the detailed explanation for the cancellation.', 'error');
                if (notesEl) notesEl.focus();
                return;
            }

            // Build final reason string for the user and admin logs
            let finalReason = selectedReason;
            if (selectedReason === 'Other Reason') {
                finalReason = notes;
            } else if (notes) {
                finalReason = selectedReason + ' — Note: ' + notes;
            }

            const btn = document.getElementById('confirmCancelBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';

            const formData = new FormData();
            formData.append('id', resId);
            formData.append('status', 'cancelled');
            formData.append('cancellation_reason', finalReason);
            cancelSelectedFiles.forEach((file) => {
                formData.append('cancellation_images[]', file);
            });

            fetch('update_order_status.php', {
                method: 'POST',
                body: formData
            })
            .then(async res => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(text || 'Invalid server response');
                }
            })
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-times-circle"></i> Confirm Cancellation';
                if (data.success) {
                    closeCancellationModal();
                    closeModal();
                    window.location.reload();
                } else {
                    showNotification('Cancellation Failed', data.message || 'Unknown error', 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-times-circle"></i> Confirm Cancellation';
                console.error(err);
                showNotification('Error', err.message || 'Error cancelling reservation', 'error');
            });
        }

        function openDurationModal() {
            closeStatFilter();
            closeDayModal();
            closeModal();
            document.getElementById('durationModal').classList.add('slide-panel-open');
            document.body.style.overflow = 'hidden';
            document.body.classList.add('slide-panel-open');
            updateDurationUI(parseInt(document.getElementById('modalDurationInput').value) || 10);
            updatePenaltyUI(parseInt(document.getElementById('modalPenaltyInput').value) || 10);
            updateLowStockUI(parseInt(document.getElementById('modalLowStockInput')?.value) || 2);
        }

        function closeDurationModal() {
            document.getElementById('durationModal').classList.remove('slide-panel-open');
            document.body.classList.remove('slide-panel-open');
            document.body.style.overflow = 'auto';
        }

        function setDurationPreset(days) {
            updateDurationUI(days);
        }

        function adjustDurationInput(amount) {
            const input = document.getElementById('modalDurationInput');
            let val = parseInt(input.value) || 10;
            val += amount;
            if (val < 1) val = 1;
            if (val > 90) val = 90;
            updateDurationUI(val);
        }

        function handleDurationSlider(val) {
            updateDurationUI(parseInt(val));
        }

        function handleDurationManualInput(input) {
            let val = parseInt(input.value);
            if (isNaN(val) || val < 1) val = 1;
            if (val > 90) val = 90;
            updateDurationUI(val);
        }

        function updateDurationUI(val) {
            const input = document.getElementById('modalDurationInput');
            const slider = document.getElementById('modalDurationSlider');
            if (input) input.value = val;
            if (slider) slider.value = val;

            // Highlight matching preset chip
            document.querySelectorAll('.duration-preset-chip').forEach(chip => {
                const chipDays = parseInt(chip.getAttribute('data-days'));
                if (chipDays === val) {
                    chip.classList.add('active');
                } else {
                    chip.classList.remove('active');
                }
            });

            // Update live simulation preview dates
            const today = new Date();
            const returnDate = new Date();
            returnDate.setDate(today.getDate() + val);

            const options = { month: 'short', day: 'numeric' };
            const todayFormatted = today.toLocaleDateString('en-US', options);
            const returnFormatted = returnDate.toLocaleDateString('en-US', options);

            const previewReserve = document.getElementById('previewReserveDate');
            const previewReturn = document.getElementById('previewReturnDate');
            const previewDays = document.getElementById('previewDurationDays');

            if (previewReserve) previewReserve.textContent = todayFormatted;
            if (previewReturn) previewReturn.textContent = returnFormatted;
            if (previewDays) previewDays.textContent = val + (val === 1 ? ' Day' : ' Days');
        }

        function setPenaltyPreset(pct) {
            updatePenaltyUI(pct);
        }

        function adjustPenaltyInput(amount) {
            const input = document.getElementById('modalPenaltyInput');
            let val = parseInt(input.value) || 10;
            val += amount;
            if (val < 1) val = 1;
            if (val > 100) val = 100;
            updatePenaltyUI(val);
        }

        function handlePenaltySlider(val) {
            updatePenaltyUI(parseInt(val));
        }

        function handlePenaltyManualInput(input) {
            let val = parseInt(input.value);
            if (isNaN(val) || val < 1) val = 1;
            if (val > 100) val = 100;
            updatePenaltyUI(val);
        }

        function updatePenaltyUI(val) {
            const input = document.getElementById('modalPenaltyInput');
            const slider = document.getElementById('modalPenaltySlider');
            if (input) input.value = val;
            if (slider) slider.value = Math.min(50, val);

            // Highlight matching preset chip
            document.querySelectorAll('.penalty-preset-chip').forEach(chip => {
                const chipPct = parseInt(chip.getAttribute('data-percent'));
                if (chipPct === val) {
                    chip.classList.add('active');
                } else {
                    chip.classList.remove('active');
                }
            });

            // Update live simulation preview calculation
            const samplePrice = 1000;
            const calcFee = Math.round((samplePrice * val) / 100);
            const previewCalc = document.getElementById('previewPenaltyCalc');
            const previewText = document.getElementById('previewPenaltyText');
            if (previewCalc) previewCalc.textContent = calcFee;
            if (previewText) previewText.textContent = val;
        }

        function setExtensionPreset(days) {
            updateExtensionDaysUI(days);
        }

        function adjustExtensionDaysInput(amount) {
            const input = document.getElementById('modalExtensionDaysInput');
            let val = parseInt(input.value) || 0;
            val += amount;
            if (val < 0) val = 0;
            if (val > 60) val = 60;
            updateExtensionDaysUI(val);
        }

        function handleExtensionDaysManualInput(input) {
            let val = parseInt(input.value);
            if (isNaN(val) || val < 0) val = 0;
            if (val > 60) val = 60;
            updateExtensionDaysUI(val);
        }

        function updateExtensionDaysUI(val) {
            const input = document.getElementById('modalExtensionDaysInput');
            if (input) input.value = val;

            document.querySelectorAll('.extension-preset-chip').forEach(chip => {
                const chipDays = parseInt(chip.getAttribute('data-days'));
                if (chipDays === val) {
                    chip.classList.add('active');
                } else {
                    chip.classList.remove('active');
                }
            });

            const previewExtDays = document.getElementById('previewExtDaysText');
            if (previewExtDays) previewExtDays.textContent = val;
        }

        function setExtensionFeePreset(fee) {
            updateExtensionFeeUI(fee);
        }

        function adjustExtensionFeeInput(amount) {
            const input = document.getElementById('modalExtensionFeeInput');
            let val = parseFloat(input.value) || 0;
            val += amount;
            if (val < 0) val = 0;
            if (val > 5000) val = 5000;
            updateExtensionFeeUI(val);
        }

        function handleExtensionFeeManualInput(input) {
            let val = parseFloat(input.value);
            if (isNaN(val) || val < 0) val = 0;
            if (val > 5000) val = 5000;
            updateExtensionFeeUI(val);
        }

        function updateExtensionFeeUI(val) {
            const input = document.getElementById('modalExtensionFeeInput');
            if (input) input.value = val;

            document.querySelectorAll('.extension-fee-preset-chip').forEach(chip => {
                const chipFee = parseFloat(chip.getAttribute('data-fee'));
                if (chipFee === val) {
                    chip.classList.add('active');
                } else {
                    chip.classList.remove('active');
                }
            });

            const previewExtFee = document.getElementById('previewExtFeeText');
            if (previewExtFee) previewExtFee.textContent = val;
        }

        function setLowStockPreset(threshold) {
            updateLowStockUI(threshold);
        }

        function adjustLowStockInput(amount) {
            const input = document.getElementById('modalLowStockInput');
            let val = parseInt(input.value) || 2;
            val += amount;
            if (val < 1) val = 1;
            if (val > 50) val = 50;
            updateLowStockUI(val);
        }

        function handleLowStockSlider(val) {
            updateLowStockUI(parseInt(val));
        }

        function handleLowStockManualInput(input) {
            let val = parseInt(input.value);
            if (isNaN(val) || val < 1) val = 1;
            if (val > 50) val = 50;
            updateLowStockUI(val);
        }

        function updateLowStockUI(val) {
            const input = document.getElementById('modalLowStockInput');
            const slider = document.getElementById('modalLowStockSlider');
            if (input) input.value = val;
            if (slider) slider.value = Math.min(20, val);

            document.querySelectorAll('.lowstock-preset-chip').forEach(chip => {
                const chipVal = parseInt(chip.getAttribute('data-threshold'));
                if (chipVal === val) {
                    chip.classList.add('active');
                } else {
                    chip.classList.remove('active');
                }
            });
        }

        function saveRentalDuration() {
            const inputDuration = document.getElementById('modalDurationInput');
            const inputPenalty = document.getElementById('modalPenaltyInput');
            const inputExtensionDays = document.getElementById('modalExtensionDaysInput');
            const inputExtensionFee = document.getElementById('modalExtensionFeeInput');
            const inputLowStock = document.getElementById('modalLowStockInput');
            const saveBtn = document.getElementById('saveDurationBtn');

            const valDuration = parseInt(inputDuration.value) || 10;
            const valPenalty = parseInt(inputPenalty.value) || 10;
            const valExtensionDays = parseInt(inputExtensionDays.value) || 0;
            const valExtensionFee = parseFloat(inputExtensionFee.value) || 0;
            const valLowStock = parseInt(inputLowStock?.value) || 2;
            
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }

            const bodyParams = new URLSearchParams();
            bodyParams.append('action', 'update_rental_duration');
            bodyParams.append('duration', valDuration);
            bodyParams.append('penalty_percent', valPenalty);
            bodyParams.append('max_extension_days', valExtensionDays);
            bodyParams.append('extension_price', valExtensionFee);
            bodyParams.append('low_stock_threshold', valLowStock);

            fetch('calendar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: bodyParams.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Policy';
                }

                if (data.success) {
                    window.systemLowStockThreshold = data.low_stock_threshold || 2;
                    document.getElementById('rentalDurationDisplay').innerHTML = `
                        <span>${data.duration}</span>
                        <span style="font-size: 0.8rem; font-weight: 500; color: #ffd700;">Days</span>
                    `;
                    const badgeEl = document.getElementById('rentalDurationBadge');
                    if (badgeEl) badgeEl.textContent = `${data.duration} days`;
                    closeDurationModal();
                    showNotification('Policy Updated', `Duration: ${data.duration}d | Penalty: ${data.penalty_percent}% | Ext: +${data.max_extension_days}d | Low Stock Alert: ≤ ${data.low_stock_threshold} items`, 'success');
                } else {
                    showNotification('Save Failed', data.message || 'Error', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Policy';
                }
                showNotification('Error', 'Error saving rental policy.', 'error');
            });
        }

        // Close topmost modal function (used by global backdrop and Escape key)
        function closeTopmostCalendarModal() {
            const cancelModal = document.getElementById('cancellationModal');
            if (cancelModal && cancelModal.classList.contains('slide-panel-open')) {
                closeCancellationModal();
                return;
            }
            const uploadModal = document.getElementById('uploadIdModal');
            if (uploadModal && uploadModal.classList.contains('slide-panel-open')) {
                closeUploadIdModal();
                return;
            }
            const resModal = document.getElementById('reservationModal');
            if (resModal && resModal.classList.contains('slide-panel-open')) {
                closeModal();
                return;
            }
            const dayModal = document.getElementById('dayModal');
            if (dayModal && dayModal.classList.contains('slide-panel-open')) {
                closeDayModal();
                return;
            }
            const statModal = document.getElementById('statFilterModal');
            if (statModal && statModal.classList.contains('slide-panel-open')) {
                closeStatFilter();
                return;
            }
            const walkInModal = document.getElementById('walkInModal');
            if (walkInModal && walkInModal.classList.contains('slide-panel-open')) {
                closeWalkInModal();
                return;
            }
            const durationModal = document.getElementById('durationModal');
            if (durationModal && durationModal.classList.contains('slide-panel-open')) {
                closeDurationModal();
                return;
            }
        }

        // Modals close ONLY via the 'X' button (backdrop click and Escape key disabled)
        // document.addEventListener('keydown', function(e) {
        //     if (e.key === 'Escape') {
        //         closeTopmostCalendarModal();
        //     }
        // });
    </script>

    <!-- Unified Top-Right Slide UI (Notification & Confirmation) -->
    <div id="customNotification" class="custom-slide-toast">
        <div class="custom-toast-content">
            <div class="custom-toast-icon-wrap" id="customToastIcon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="custom-toast-body">
                <div class="custom-toast-title" id="customToastTitle">Notification</div>
                <div class="custom-toast-msg" id="customToastMessage">Message content here.</div>
            </div>
            <div class="custom-toast-btns" id="customToastBtns">
                <button type="button" class="custom-toast-cancel-btn" id="customToastCancelBtn" onclick="hideNotification(false)" style="display: none;">Cancel</button>
                <button type="button" class="custom-toast-ok-btn" id="customToastOkBtn" onclick="hideNotification(true)">OK</button>
            </div>
        </div>
    </div>
</body>
</html>
