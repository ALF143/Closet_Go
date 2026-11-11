<?php
session_start();
require_once 'database/db_config.php';

// Handle get_product_reviews request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] == 'get_product_reviews') {
    header('Content-Type: application/json');

    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID', 'reviews' => []]);
        exit();
    }

    try {
        $database = new Database();
        $conn = $database->getConnection();

        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'DB connection failed', 'reviews' => []]);
            exit();
        }

        $sql = "SELECT r.id, r.reserve_product_id, r.rating, r.review_text, r.photo1_path, r.photo2_path, r.created_at,
                       u.first_name, u.last_name, u.email, u.profile_image
                FROM product_reviews r
                LEFT JOIN system_users u ON r.user_id = u.id
                WHERE r.product_id = ?
                ORDER BY r.created_at DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $reviews = [];
        $totalRating = 0;
        while ($row = $result->fetch_assoc()) {
            $firstName = $row['first_name'] ?? '';
            $lastName = $row['last_name'] ?? '';
            $displayName = trim($firstName . ' ' . $lastName);
            if (empty($displayName)) {
                $displayName = $row['email'] ? explode('@', $row['email'])[0] : 'Anonymous';
            }
            $initials = strtoupper(substr($firstName ?: 'A', 0, 1) . substr($lastName ?: '', 0, 1));

            $reviews[] = [
                'id' => $row['id'],
                'rating' => intval($row['rating']),
                'review_text' => $row['review_text'] ?? '',
                'photo1' => $row['photo1_path'] ?? null,
                'photo2' => $row['photo2_path'] ?? null,
                'created_at' => $row['created_at'] ?? '',
                'username' => $displayName,
                'initials' => $initials,
                'profile_picture' => $row['profile_image'] ?? null
            ];
            $totalRating += intval($row['rating']);
        }
        $stmt->close();

        $avgRating = count($reviews) > 0 ? round($totalRating / count($reviews), 1) : 0;

        echo json_encode([
            'success' => true,
            'reviews' => $reviews,
            'avg_rating' => $avgRating,
            'total_reviews' => count($reviews)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage(), 'reviews' => []]);
    }
    exit();
}

$isLoggedIn = false;
$currentUser = null;
$displayName = '';
$userProfileImage = 'images/icon/profile_logo.png';

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
$userEmail = isset($_SESSION['email']) ? $_SESSION['email'] : (isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '');

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
$validTabs = ['all', 'pending', 'confirmed', 'completed', 'cancelled', 'lost'];
if (!in_array($tab, $validTabs)) {
    $tab = 'all';
}

$purchases = [];
$errorMsg = '';

if ($userId > 0 || !empty($userEmail)) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        if ($conn) {
            // Load user profile details
            if ($userId > 0) {
                $userStmt = $conn->prepare("SELECT id, email, first_name, middle_name, last_name, profile_image FROM system_users WHERE id = ?");
                $userStmt->bind_param("i", $userId);
            } else {
                $userStmt = $conn->prepare("SELECT id, email, first_name, middle_name, last_name, profile_image FROM system_users WHERE email = ?");
                $userStmt->bind_param("s", $userEmail);
            }
            
            if ($userStmt) {
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                if ($userResult && $userResult->num_rows > 0) {
                    $currentUser = $userResult->fetch_assoc();
                    $isLoggedIn = true;
                    $userId = intval($currentUser['id']);
                    $_SESSION['user_id'] = $currentUser['id'];
                    $_SESSION['email'] = $currentUser['email'];
                    
                    $fullName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
                    if (!empty($fullName)) {
                        $displayName = $fullName;
                    } elseif (!empty($currentUser['first_name'])) {
                        $displayName = $currentUser['first_name'];
                    } elseif (!empty($currentUser['email'])) {
                        $displayName = $currentUser['email'];
                    } else {
                        $displayName = '';
                    }
                    
                    if (!empty($currentUser['profile_image'])) {
                        $userProfileImage = $currentUser['profile_image'];
                    }
                }
                $userStmt->close();
            }
            // Auto-heal: Ensure reschedule & lost item columns exist on live/online database
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

            // Fetch ALL purchases — filtering by tab is done client-side
            $sql = "SELECT rp.*, COALESCE(g.image_path, '') as image_path, COALESCE(g.image_path2, '') as image_path2, COALESCE(g.image_path3, '') as image_path3, COALESCE(rp.model_path, g.model_path) as model_path, COALESCE(NULLIF(g.purchase_price, 0), g.price, rp.price, 0) as selling_price FROM reserve_products rp LEFT JOIN gowns g ON ((rp.product_id > 0 AND rp.product_id = g.id) OR ((rp.product_id IS NULL OR rp.product_id = 0) AND LOWER(TRIM(rp.product_name)) = LOWER(TRIM(g.name)))) WHERE rp.user_id = ? ORDER BY rp.reserved_at DESC, rp.id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $rawPurchases = [];
            while ($row = $result->fetch_assoc()) {
                // Fetch Item 2+ sizes from reserve_products_items
                $itemsSql = "SELECT * FROM reserve_products_items WHERE reserve_product_id = ? ORDER BY item_number ASC";
                $itemsStmt = $conn->prepare($itemsSql);
                $itemsStmt->bind_param("i", $row['id']);
                $itemsStmt->execute();
                $itemsResult = $itemsStmt->get_result();
                $row['items'] = [];
                while ($itemRow = $itemsResult->fetch_assoc()) {
                    $row['items'][] = $itemRow;
                }
                $itemsStmt->close();

                // Fetch size labels from categories table
                $catName = $row['category'] ?? '';
                $labelSql = "SELECT size_label_1, size_label_2, size_label_3, size_label_4, size_label_5, size_label_6, size_label_7, size_label_8, size_label_9, size_label_10 FROM categories WHERE LOWER(TRIM(category_name)) = LOWER(TRIM(?)) LIMIT 1";
                $labelStmt = $conn->prepare($labelSql);
                $labelStmt->bind_param("s", $catName);
                $labelStmt->execute();
                $labelResult = $labelStmt->get_result();
                $row['size_labels'] = [];
                if ($labelRow = $labelResult->fetch_assoc()) {
                    for ($i = 1; $i <= 10; $i++) {
                        $labelText = $labelRow["size_label_{$i}"] ?? null;
                        if ($labelText !== null && $labelText !== '') {
                            $row['size_labels'][] = ['index' => $i, 'label' => $labelText];
                        }
                    }
                }
                $labelStmt->close();

                // Check if this reservation has already been reviewed and fetch user rating
                $reviewCheckSql = "SELECT id, rating FROM product_reviews WHERE reserve_product_id = ? LIMIT 1";
                $reviewCheckStmt = $conn->prepare($reviewCheckSql);
                $reviewCheckStmt->bind_param("i", $row['id']);
                $reviewCheckStmt->execute();
                $reviewResult = $reviewCheckStmt->get_result();
                if ($revRow = $reviewResult->fetch_assoc()) {
                    $row['has_review'] = true;
                    $row['user_rating'] = intval($revRow['rating']);
                } else {
                    $row['has_review'] = false;
                    $row['user_rating'] = 0;
                }
                $reviewCheckStmt->close();

                $rawPurchases[] = $row;
            }

            // Group purchases into combined reservations/orders (combining products from product_reserve.php)
            $groupMap = [];
            foreach ($rawPurchases as $row) {
                $groupKey = !empty($row['reservation_group_id'])
                    ? $row['reservation_group_id']
                    : ($row['user_id'] . '_' . $row['reserved_at'] . '_' . ($row['reserve_date'] ?? '') . '_' . ($row['return_date'] ?? ''));

                if (!isset($groupMap[$groupKey])) {
                    $idx = count($purchases);
                    $groupMap[$groupKey] = $idx;
                    $resCode = !empty($row['reservation_code']) 
                        ? $row['reservation_code'] 
                        : ('CG-' . date('ymd', strtotime($row['reserved_at'] ?: 'now')) . '-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT));

                    $purchases[$idx] = [
                        'id' => $row['id'],
                        'reservation_code' => $resCode,
                        'reservation_group_id' => $row['reservation_group_id'] ?? $groupKey,
                        'order_status' => $row['order_status'] ?? 'pending',
                        'product_status' => $row['product_status'] ?? ($row['payment_method'] ?? 'reserve'),
                        'first_name' => $row['first_name'] ?? '',
                        'last_name' => $row['last_name'] ?? '',
                        'payment_method' => $row['payment_method'] ?? 'reserve',
                        'payment_option' => $row['payment_option'] ?? 'full',
                        'downpayment_amount' => floatval($row['downpayment_amount'] ?? 0),
                        'balance_amount' => floatval($row['balance_amount'] ?? 0),
                        'total_price' => floatval($row['total_price'] ?? 0),
                        'reserve_date' => $row['reserve_date'] ?? '',
                        'return_date' => $row['return_date'] ?? '',
                        'reserve_time' => $row['reserve_time'] ?? '',
                        'return_time' => $row['return_time'] ?? '',
                        'reserved_at' => $row['reserved_at'] ?? '',
                        'cancellation_reason' => $row['cancellation_reason'] ?? '',
                        'cancellation_images' => $row['cancellation_images'] ?? '',
                        'is_rescheduled' => intval($row['is_rescheduled'] ?? 0),
                        'rescheduled_at' => $row['rescheduled_at'] ?? '',
                        'original_reserve_date' => $row['original_reserve_date'] ?? '',
                        'original_return_date' => $row['original_return_date'] ?? '',
                        'reschedule_reason' => $row['reschedule_reason'] ?? '',
                        'is_lost' => intval($row['is_lost'] ?? 0),
                        'lost_status' => $row['lost_status'] ?? '',
                        'lost_reason' => $row['lost_reason'] ?? '',
                        'lost_selling_price' => floatval($row['lost_selling_price'] ?? 0),
                        'lost_user_paid' => floatval($row['lost_user_paid'] ?? 0),
                        'lost_overdue_fee' => floatval($row['lost_overdue_fee'] ?? 0),
                        'lost_total_due' => floatval($row['lost_total_due'] ?? 0),
                        'lost_payment_proof' => $row['lost_payment_proof'] ?? '',
                        'lost_requested_at' => $row['lost_requested_at'] ?? '',
                        'lost_settled_at' => $row['lost_settled_at'] ?? '',
                        'products' => []
                    ];
                } else {
                    $idx = $groupMap[$groupKey];
                    $purchases[$idx]['total_price'] += floatval($row['total_price'] ?? 0);
                    $purchases[$idx]['downpayment_amount'] += floatval($row['downpayment_amount'] ?? 0);
                    $purchases[$idx]['balance_amount'] += floatval($row['balance_amount'] ?? 0);
                    if (intval($row['is_lost'] ?? 0) === 1) {
                        $purchases[$idx]['is_lost'] = 1;
                        $purchases[$idx]['lost_status'] = $row['lost_status'] ?? $purchases[$idx]['lost_status'];
                        $purchases[$idx]['lost_reason'] = $row['lost_reason'] ?? $purchases[$idx]['lost_reason'];
                        $purchases[$idx]['lost_selling_price'] += floatval($row['lost_selling_price'] ?? 0);
                        $purchases[$idx]['lost_user_paid'] += floatval($row['lost_user_paid'] ?? 0);
                        $purchases[$idx]['lost_overdue_fee'] += floatval($row['lost_overdue_fee'] ?? 0);
                        $purchases[$idx]['lost_total_due'] += floatval($row['lost_total_due'] ?? 0);
                        $purchases[$idx]['lost_payment_proof'] = $row['lost_payment_proof'] ?? $purchases[$idx]['lost_payment_proof'];
                    }
                }

                $purchases[$groupMap[$groupKey]]['products'][] = $row;
            }
        } else {
            $errorMsg = 'Database connection failed';
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }
} else {
    // Not logged in: purchases remain empty to display the standard 'No purchases yet' animation
    $purchases = [];
}

// Fetch rental system settings from database (matching index.php)
$rentalDurationDays = 10;
$maxExtensionDays = 7;
$extensionPricePerDay = 100;
$overduePenaltyPercent = 10;

try {
    if (!isset($conn) || !$conn) {
        $database = new Database();
        $conn = $database->getConnection();
    }
    if ($conn) {
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
        // Load app settings (QR Ph RCBC code, account name, number)
        $appSettings = [];
        $setRes = $conn->query("SELECT setting_key, setting_value FROM app_settings");
        if ($setRes) {
            while ($sRow = $setRes->fetch_assoc()) {
                $appSettings[$sRow['setting_key']] = $sRow['setting_value'];
            }
        }
        $gcashQrPath = !empty($appSettings['qrph_qr_path']) ? $appSettings['qrph_qr_path'] : (!empty($appSettings['gcash_qr_path']) ? $appSettings['gcash_qr_path'] : 'images/gcash_qr_temp.jpg');
        $gcashNumber = !empty($appSettings['rcbc_account_number']) ? $appSettings['rcbc_account_number'] : (!empty($appSettings['gcash_number']) ? $appSettings['gcash_number'] : '09123456789');
        $gcashAccountName = !empty($appSettings['rcbc_account_name']) ? $appSettings['rcbc_account_name'] : (!empty($appSettings['gcash_account_name']) ? $appSettings['gcash_account_name'] : 'ClosetGoo Admin');
    }
} catch (Exception $e) {}

$tabLabels = [
    'all' => 'All',
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'lost' => 'Report Lost'
];

// Count pending purchases
$pendingCount = 0;
$lostCount = 0;
foreach ($purchases as $purchase) {
    if (($purchase['order_status'] ?? 'pending') === 'pending') {
        $pendingCount++;
    }
    if (intval($purchase['is_lost'] ?? 0) === 1) {
        $lostCount++;
    }
}

$tabColors = [
    'pending' => '#ff9800',
    'confirmed' => '#2196f3',
    'completed' => '#4caf50',
    'cancelled' => '#f44336',
    'lost' => '#ff9800'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchases - ClosetGo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/homepage.css?v=<?php echo @filemtime('css/homepage.css'); ?>">
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.1.1/model-viewer.min.js"></script>
    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.14/dist/dotlottie-wc.js" type="module"></script>
    <style>
        /* Calendar Modal styles identical to index.php */
        .calendar-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 100002;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
        .calendar-modal-overlay.active {
            display: flex;
        }
        .calendar-modal {
            background: #1a1a1a;
            border-radius: 14px;
            padding: 24px 26px;
            width: 95%;
            max-width: 650px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8), 0 0 25px rgba(255, 215, 0, 0.15);
            z-index: 100003;
            border: 1.5px solid rgba(255, 215, 0, 0.3);
            color: #fff;
        }
        .calendar-modal-header {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 12px;
            padding: 10px 14px;
            background: #24252a;
            border-radius: 8px;
            color: #ffffff;
            border: 1px solid #383a42;
        }
        .calendar-modal-title {
            font-size: 17px;
            font-weight: 700;
            color: #ffffff;
            text-align: center;
            margin: 0 16px;
            letter-spacing: 0.5px;
        }
        .calendar-modal-nav {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .calendar-modal-nav button {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffd700;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .calendar-modal-nav button:hover {
            background: rgba(255, 215, 0, 0.2);
            border-color: #ffd700;
        }
        .calendar-modal-guide {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 215, 0, 0.2);
            border-radius: 8px;
            padding: 8px 14px;
            margin-bottom: 12px;
            font-size: 11.5px;
            color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .calendar-modal-guide .calendar-guide-header {
            font-weight: 700;
            color: #ffd700;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
        }
        .calendar-modal-guide .calendar-guide-msg {
            color: #cccccc;
            font-size: 11px;
        }
        .calendar-modal-guide .guide-badge-inline.red-gap {
            background: rgba(220, 53, 69, 0.35);
            color: #ff9999;
            padding: 1px 5px;
            border-radius: 3px;
            border: 1px solid rgba(220, 53, 69, 0.6);
            font-weight: 600;
        }
        .calendar-modal-guide .calendar-guide-legend {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 2px;
            padding-top: 5px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }
        .calendar-modal-guide .legend-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10.5px;
            color: #bbb;
        }
        .calendar-modal-guide .legend-chip .chip-box {
            width: 11px;
            height: 11px;
            border-radius: 2.5px;
            display: inline-block;
            flex-shrink: 0;
        }
        .calendar-modal-guide .legend-chip.red-gap .chip-box {
            background: rgba(220, 53, 69, 0.4);
            border: 1px solid #ff4444;
        }
        .calendar-modal-guide .legend-chip.extension .chip-box {
            background: rgba(56, 189, 248, 0.35);
            border: 1px dashed #38bdf8;
        }
        .calendar-modal-guide .legend-chip.booked .chip-box {
            background: #2a1515;
            border: 1px solid #ff4444;
            position: relative;
        }
        .calendar-modal-guide .legend-chip.booked .chip-box::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 1px;
            right: 1px;
            height: 1px;
            background: #ff4444;
            transform: translateY(-50%) rotate(-35deg);
        }
        .calendar-modal-guide .legend-chip.available .chip-box {
            background: #18191d;
            border: 1px solid #555;
        }
        .calendar-modal-guide .legend-chip.orig-date .chip-box {
            background: rgba(108, 212, 136, 0.3);
            border: 1px solid #6cd488;
        }
        .calendar-modal-guide .legend-chip.lead-time .chip-box {
            background: #141417;
            border: 1px dashed #666;
            opacity: 0.5;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            background: #2a2a30;
            border: 1px solid #383a42;
            border-radius: 8px;
            overflow: hidden;
            padding: 2px;
        }
        .calendar-day-header {
            background: #202227;
            padding: 10px 3px;
            text-align: center;
            font-weight: 700;
            color: #ffd700;
            font-size: 12px;
        }
        .calendar-day {
            background: #18191d;
            min-height: 48px;
            padding: 8px 4px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1.5px solid transparent;
            color: #ffffff;
            font-size: 13.5px;
            font-weight: 500;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            position: relative;
        }
        .calendar-day:hover:not(.disabled):not(.other-month) {
            background: #282a32;
            border-color: #ffd700;
            transform: scale(1.02);
        }
        .calendar-day.other-month {
            background: #121316;
            color: #444;
            pointer-events: none;
        }
        .calendar-day.today {
            border-color: rgba(255, 215, 0, 0.6);
            font-weight: 700;
        }
        .calendar-day.selected {
            background: #ffd700 !important;
            color: #000000 !important;
            font-weight: 800 !important;
            border: 2px solid #ffd700 !important;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.6) !important;
        }
        .calendar-day.rental-gap {
            background: rgba(220, 53, 69, 0.25) !important;
            color: #ff9999 !important;
            border: 1px solid rgba(220, 53, 69, 0.6) !important;
            font-weight: 600 !important;
            user-select: none !important;
        }
        .calendar-day.extension-day {
            background: rgba(56, 189, 248, 0.18) !important;
            color: #bae6fd !important;
            border: 1.5px dashed rgba(56, 189, 248, 0.6) !important;
            font-weight: 600 !important;
        }
        .calendar-day.extension-day:hover:not(.disabled) {
            background: rgba(56, 189, 248, 0.4) !important;
            border-color: #38bdf8 !important;
            color: #ffffff !important;
            cursor: pointer !important;
        }
        .calendar-day.reserve-anchor {
            background: rgba(255, 215, 0, 0.2) !important;
            border: 1.5px dashed #ffd700 !important;
            color: #ffd700 !important;
            font-weight: 700 !important;
        }
        .calendar-day.orig-date-marker {
            background: rgba(108, 212, 136, 0.15) !important;
            border: 1px solid rgba(108, 212, 136, 0.5) !important;
            color: #6cd488 !important;
        }
        .calendar-day.disabled {
            pointer-events: none !important;
            cursor: not-allowed !important;
            user-select: none !important;
        }
        .calendar-day.disabled.booked-date,
        .calendar-day.today.disabled.booked-date {
            background: rgba(45, 15, 15, 0.92) !important;
            color: #ff4444 !important;
            text-decoration: line-through !important;
            cursor: not-allowed !important;
            border: 1px solid rgba(220, 53, 69, 0.5) !important;
            opacity: 0.8 !important;
            pointer-events: none !important;
        }
        .calendar-day.disabled.past-date {
            background: #141417 !important;
            color: #4a4a55 !important;
            cursor: not-allowed !important;
            opacity: 0.3 !important;
            border-color: transparent !important;
            pointer-events: none !important;
        }
        .calendar-day.disabled.lead-time-disabled {
            background: #141417 !important;
            color: #555564 !important;
            cursor: not-allowed !important;
            opacity: 0.35 !important;
            border-color: transparent !important;
            pointer-events: none !important;
        }
        .calendar-day.disabled.exceeds-duration {
            background: #19191c !important;
            color: #dd4444 !important;
            cursor: not-allowed !important;
            opacity: 0.35 !important;
            border-color: transparent !important;
            pointer-events: none !important;
        }

        /* Reschedule buttons and badges */
        .reschedule-btn {
            background: linear-gradient(135deg, #7c6300, #ffd700);
            color: #000;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.25);
        }
        .reschedule-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(255, 215, 0, 0.45);
            filter: brightness(1.08);
        }
        .reschedule-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(33, 150, 243, 0.15);
            color: #64b5f6;
            border: 1px solid rgba(33, 150, 243, 0.4);
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .reschedule-mini-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Custom Reschedule Confirmation Modal (UI matching other confirmation modals) */
        .confirm-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.78);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 100010;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        .confirm-modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        .confirm-modal-card {
            background: #1a1a1f;
            border: 1.5px solid rgba(255, 215, 0, 0.45);
            border-radius: 14px;
            padding: 24px;
            width: 90%;
            max-width: 410px;
            box-shadow: 0 16px 45px rgba(0, 0, 0, 0.85), 0 0 25px rgba(255, 215, 0, 0.15);
            text-align: center;
            color: #fff;
            transform: scale(0.95);
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            box-sizing: border-box;
        }
        .confirm-modal-overlay.active .confirm-modal-card {
            transform: scale(1);
        }
        .confirm-modal-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 215, 0, 0.15);
            border: 1px solid rgba(255, 215, 0, 0.4);
            color: #ffd700;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px auto;
        }
        .confirm-modal-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }
        .confirm-modal-desc {
            font-size: 0.86rem;
            color: #bbb;
            margin-bottom: 16px;
            line-height: 1.45;
        }
        .confirm-modal-dates-box {
            background: #121216;
            border: 1px solid rgba(255, 215, 0, 0.22);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 14px;
            text-align: left;
        }
        .confirm-modal-date-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            margin-bottom: 6px;
        }
        .confirm-modal-date-row:last-child {
            margin-bottom: 0;
        }
        .confirm-modal-date-row span {
            color: #888;
        }
        .confirm-modal-date-row strong {
            font-weight: 700;
        }
        .confirm-modal-warning {
            background: rgba(240, 173, 78, 0.1);
            border: 1px dashed rgba(240, 173, 78, 0.4);
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 11px;
            color: #f0ad4e;
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 20px;
            text-align: left;
            line-height: 1.35;
        }
        .confirm-modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            align-items: center;
        }
        .confirm-btn-cancel {
            flex: 1;
            padding: 10px 18px;
            background: #2b2d35;
            color: #ccc;
            border: 1px solid #444;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.88rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .confirm-btn-cancel:hover {
            background: #383a44;
            color: #fff;
        }
        .confirm-btn-save {
            flex: 1;
            padding: 10px 22px;
            background: linear-gradient(135deg, #7c6300, #ffd700);
            color: #000;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(255, 215, 0, 0.28);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .confirm-btn-save:hover {
            box-shadow: 0 6px 18px rgba(255, 215, 0, 0.45);
            filter: brightness(1.08);
        }
        .confirm-btn-save:disabled,
        .confirm-btn-cancel:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Purchase Card Pointer & Details Styling */
        .purchase-card {
            cursor: pointer;
        }
        .detail-prod-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10px;
            font-weight: 700;
            padding: 2.5px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex-shrink: 0;
            line-height: 1;
        }
        .detail-prod-type-badge.badge-reserve {
            background: rgba(255, 215, 0, 0.15);
            color: #ffd700;
            border: 1px solid rgba(255, 215, 0, 0.4);
        }
        .detail-prod-type-badge.badge-buy {
            background: rgba(108, 212, 136, 0.15);
            color: #6cd488;
            border: 1px solid rgba(108, 212, 136, 0.4);
        }
        .detail-qr-btn {
            transition: all 0.25s ease;
        }
        .detail-qr-btn:hover {
            border-color: #ffd700 !important;
            background: #2b2b32 !important;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.25) !important;
        }
        /* Lost Item Feature Styles */
        .lost-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(244, 67, 54, 0.15);
            color: #ff5252;
            border: 1px solid rgba(244, 67, 54, 0.4);
            white-space: nowrap;
        }
        .lost-status-pill.settled {
            background: rgba(108, 212, 136, 0.15);
            color: #6cd488;
            border-color: rgba(108, 212, 136, 0.4);
        }
        .card-actions-bottom .lost-status-pill,
        .multi-card-actions-row .lost-status-pill,
        .detail-product-actions .lost-status-pill {
            padding: 5px 12px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            white-space: nowrap;
        }
        .report-lost-btn {
            background: rgba(255, 152, 0, 0.12);
            color: #ffb74d;
            border: 1px solid rgba(255, 152, 0, 0.4);
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .report-lost-btn:hover {
            background: rgba(255, 152, 0, 0.22);
            border-color: #ffa726;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.25);
        }
        .view-lost-btn {
            background: rgba(33, 150, 243, 0.12);
            color: #64b5f6;
            border: 1px solid rgba(33, 150, 243, 0.4);
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .view-lost-btn:hover {
            background: rgba(33, 150, 243, 0.22);
            border-color: #42a5f5;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.25);
        }
        .lost-item-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            z-index: 100000;
        }
        .lost-item-modal {
            display: none;
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            right: auto !important;
            bottom: auto !important;
            transform: translate(-50%, -50%) !important;
            width: 94% !important;
            max-width: 860px !important;
            max-height: 88vh !important;
            height: auto !important;
            background: #18181c !important;
            border: 1.5px solid rgba(255, 152, 0, 0.42) !important;
            border-radius: 16px !important;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.9), 0 0 35px rgba(255, 152, 0, 0.16) !important;
            z-index: 100001 !important;
            overflow: hidden !important;
            flex-direction: column !important;
            animation: detailModalPopIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
            color: #e0e0e0;
        }
        .lost-modal-header {
            position: sticky;
            top: 0;
            background: #1f1f25;
            border-bottom: 1px solid rgba(255, 152, 0, 0.3);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 10;
            flex-shrink: 0;
        }
        .lost-modal-title-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .lost-modal-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(255, 152, 0, 0.15);
            border: 1px solid rgba(255, 152, 0, 0.4);
            color: #ffb74d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        .lost-modal-title {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.3px;
            line-height: 1.25;
        }
        .lost-modal-subtitle {
            font-size: 12px;
            color: #aaa;
            margin-top: 2px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .lost-modal-close-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #bbb;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            line-height: 1;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        .lost-modal-close-btn:hover {
            background: rgba(255, 152, 0, 0.2);
            border-color: #ff9800;
            color: #ffd700;
            transform: scale(1.08);
        }
        .lost-modal-body {
            flex: 1;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 22px 28px 30px 28px !important;
            overflow-y: auto !important;
            max-height: calc(88vh - 72px) !important;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 152, 0, 0.35) #16161a;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .lost-policy-banner {
            background: rgba(255, 152, 0, 0.08);
            border: 1px solid rgba(255, 152, 0, 0.35);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 12.5px;
            line-height: 1.5;
            color: #ffb74d;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .lost-policy-banner i {
            font-size: 16px;
            margin-top: 2px;
            flex-shrink: 0;
            color: #ffa726;
        }
        .lost-modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }
        .lost-modal-col {
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-width: 0;
        }
        .lost-section-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #ffd700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 2px;
        }
        .lost-calc-card {
            background: #121215;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 16px;
        }
        .lost-calc-header {
            font-size: 12px;
            font-weight: 700;
            color: #ffd700;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .lost-calc-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 13px;
        }
        .lost-calc-row.total-row {
            border-top: 1px dashed rgba(255, 255, 255, 0.15);
            margin-top: 8px;
            padding-top: 12px;
            font-size: 15px;
            font-weight: 700;
        }
        .lost-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .lost-form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #ccc;
        }
        .lost-textarea {
            width: 100%;
            height: 82px;
            background: #121215;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px;
            padding: 10px 12px;
            color: #fff;
            font-size: 12.5px;
            resize: none;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        .lost-textarea:focus {
            border-color: #ff9800;
            box-shadow: 0 0 0 2px rgba(255, 152, 0, 0.2);
        }
        .lost-qr-card {
            background: #121215;
            border: 1px solid rgba(255, 215, 0, 0.28);
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            gap: 14px;
            align-items: center;
        }
        .lost-qr-thumb-wrap {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            flex-shrink: 0;
        }
        #lostQrImg {
            width: 90px;
            height: 90px;
            object-fit: contain;
            border-radius: 8px;
            background: #fff;
            padding: 4px;
            border: 1px solid rgba(255,215,0,0.35);
            cursor: pointer;
            transition: transform 0.2s;
            display: block;
        }
        #lostQrImg:hover {
            transform: scale(1.04);
        }
        .lost-qr-tap-hint {
            font-size: 9.5px;
            color: #aaa;
            margin-top: 3px;
            font-weight: 500;
        }
        .lost-qr-info {
            flex: 1;
            min-width: 0;
        }
        .lost-qr-badge {
            font-size: 11px;
            text-transform: uppercase;
            color: #ffd700;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .lost-qr-acc-name {
            font-size: 12.5px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .lost-qr-acc-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }
        .lost-qr-acc-num {
            font-family: monospace;
            font-size: 13px;
            color: #ffd700;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .lost-qr-copy-btn {
            background: rgba(255,215,0,0.15);
            border: 1px solid rgba(255,215,0,0.35);
            color: #ffd700;
            padding: 3px 9px;
            border-radius: 4px;
            font-size: 10.5px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        .lost-qr-copy-btn:hover {
            background: rgba(255,215,0,0.3);
            transform: translateY(-1px);
        }
        .lost-qr-sub {
            font-size: 11px;
            color: #888;
            line-height: 1.35;
        }
        .lost-upload-dropzone {
            position: relative;
            border: 1.5px dashed rgba(255,215,0,0.35);
            border-radius: 10px;
            padding: 12px;
            background: #121215;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .lost-upload-dropzone:hover {
            border-color: #ffd700;
            background: rgba(255,215,0,0.03);
        }
        .lost-modal-actions {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        .lost-cancel-btn {
            background: #000000;
            color: #ffffff;
            border: 1px solid #7c6300;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
            box-shadow: inset 0 0 15px rgba(212, 175, 55, 0.8);
            outline: none;
        }
        .lost-cancel-btn:hover {
            background: #1a1a1a;
            transform: translateY(-2px);
            box-shadow: inset 0 0 20px rgba(212, 175, 55, 1);
            color: #ffffff;
        }
        .lost-cancel-btn:active {
            transform: translateY(0);
        }
        .lost-submit-btn {
            background: #000000;
            color: #ffffff;
            border: 1px solid #7c6300;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: inset 0 0 15px rgba(212, 175, 55, 0.8);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1.5;
            outline: none;
        }
        .lost-submit-btn:hover {
            transform: translateY(-2px);
            background: #1a1a1a;
            box-shadow: inset 0 0 20px rgba(212, 175, 55, 1);
            color: #ffffff;
        }
        .lost-submit-btn:active {
            transform: translateY(0);
        }
        .cancellation-lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.92);
            backdrop-filter: blur(8px);
            z-index: 100005 !important;
            align-items: center;
            justify-content: center;
            padding: 20px;
            cursor: zoom-out;
        }
        .cancellation-lightbox img {
            max-width: 90vw;
            max-height: 85vh;
            object-fit: contain;
            border-radius: 10px;
            border: 2px solid rgba(255, 215, 0, 0.4);
            box-shadow: 0 15px 50px rgba(0,0,0,0.85);
        }
        /* Purchase Detail Modal (Big Centered Modal) */
        .purchase-detail-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.82);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 99998;
            transition: opacity 0.25s ease;
        }

        .purchase-detail-overlay {
            display: none;
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            right: auto !important;
            bottom: auto !important;
            transform: translate(-50%, -50%) !important;
            width: 94% !important;
            max-width: 860px !important;
            max-height: 88vh !important;
            height: auto !important;
            background: #18181c !important;
            border: 1.5px solid rgba(255, 215, 0, 0.35) !important;
            border-radius: 16px !important;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.9), 0 0 35px rgba(255, 215, 0, 0.12) !important;
            z-index: 99999 !important;
            overflow: hidden !important;
            flex-direction: column !important;
            animation: detailModalPopIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }

        @keyframes detailModalPopIn {
            from {
                opacity: 0;
                transform: translate(-50%, -46%) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        .purchase-detail-header {
            position: sticky;
            top: 0;
            background: #1f1f25;
            border-bottom: 1px solid rgba(255, 215, 0, 0.25);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 10;
            flex-shrink: 0;
        }

        .purchase-detail-title-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .purchase-detail-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(255, 215, 0, 0.12);
            border: 1px solid rgba(255, 215, 0, 0.35);
            color: #ffd700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .purchase-detail-title {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.3px;
            line-height: 1.25;
        }

        .purchase-detail-subtitle {
            font-size: 12px;
            color: #aaa;
            margin-top: 2px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .purchase-detail-close-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #bbb;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            line-height: 1;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .purchase-detail-close-btn:hover {
            background: rgba(255, 215, 0, 0.15);
            border-color: #ffd700;
            color: #ffd700;
            transform: scale(1.08);
        }

        .purchase-detail-body {
            flex: 1;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 22px 28px 30px 28px !important;
            overflow-y: auto !important;
            max-height: calc(88vh - 72px) !important;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 215, 0, 0.35) #16161a;
        }

        @media (max-width: 768px) {
            .lost-modal-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }

        @media (max-width: 600px) {
            .purchase-detail-overlay,
            .lost-item-modal {
                width: 96% !important;
                max-height: 92vh !important;
                border-radius: 14px !important;
            }
            .purchase-detail-header,
            .lost-modal-header {
                padding: 12px 16px;
            }
            .purchase-detail-title,
            .lost-modal-title {
                font-size: 15px;
            }
            .purchase-detail-body,
            .lost-modal-body {
                padding: 16px 14px 24px 14px !important;
                max-height: calc(92vh - 65px) !important;
            }
            .lost-policy-banner {
                padding: 10px 12px;
                font-size: 11.5px;
            }
            .lost-qr-card {
                padding: 10px 12px;
                gap: 10px;
            }
            #lostQrImg {
                width: 75px;
                height: 75px;
            }
            .lost-qr-acc-num {
                font-size: 12px;
            }
            .lost-modal-actions {
                flex-direction: row;
                gap: 10px;
                margin-top: 14px;
            }
            .lost-cancel-btn {
                padding: 11px 14px;
                font-size: 13px;
            }
            .lost-submit-btn {
                padding: 11px 16px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body class="purchase-page">
    <div class="shell">
        <!-- Desktop Top Navigation Bar (same as index.php) -->
        <div class="top-nav">
        <!-- Logo Section (Desktop Only) -->
        <div class="logo-section">
            <div class="logo-text-container">
                <div class="top-texts">
                    <a href="#" class="download-link">
                        <div class="download-text">Download</div>
                    </a>
                    <div class="text-divider"></div>
                    <div class="follow-text">Follow us on <a href="https://www.facebook.com/ACV.alf?mibextid=ZbWKwL" target="_blank" class="social-link"><img src="images/icon/facebook.gif" alt="Facebook" class="facebook-icon"></a> & <a href="https://www.instagram.com/iam_alff?igsh=YWdjcmQ2MmJ0OXkw" target="_blank" class="social-link"><img src="images/icon/instagram.gif" alt="Instagram" class="instagram-icon"></a></div>
                </div>
                <a href="index.php" class="closetgo-link">
                    <img src="images/icon/JMB.png" alt="ClosetGo" class="brand-logo" onerror="this.src='images/icon/chat.png'">
                    <span>ClosetGo</span>
                </a>
            </div>
        </div>
        
        <!-- Mobile ClosetGo Logo -->
        <a href="index.php" class="mobile-closetgo-link">
            <img src="images/icon/JMB.png" alt="ClosetGo" class="brand-logo" onerror="this.src='images/icon/chat.png'">
            <span>ClosetGo</span>
        </a>
        
        <!-- Right Side Navigation / Live Search -->
        <div class="search-filter-container">
            <div class="search-box">
                <div style="display: flex; align-items: center; width: 100%;">
                    <img src="images/icon/search_bar.gif" alt="Search" style="width: 25px; height: 25px; margin-right: 8px; cursor: pointer; transition: transform 0.1s ease, opacity 0.1s ease;" 
                         onmouseover="this.style.transform='scale(1.02)'; this.style.opacity='0.9';"
                         onmouseout="this.style.transform='scale(1)'; this.style.opacity='1';"
                         onclick="document.getElementById('searchInput')?.focus()">
                    <div style="color: inherit; display: flex; align-items: center; flex: 1;">
                        <input type="text" placeholder="Search purchases..." id="searchInput" oninput="filterPurchases()" onkeyup="filterPurchases()" style="background: none; border: none; outline: none; flex: 1; font-size: 1rem; color: #ffffff; transition: opacity 0.3s ease;">
                    </div>
                </div>
            </div>
        </div>
        <div class="auth-links" id="authLinksContainer">
            <?php if ($isLoggedIn): ?>
                <div class="auth-user-profile">
                    <span class="auth-user-name" id="topbarUserName"><?php echo htmlspecialchars($displayName); ?></span>
                    <a href="profile.php" class="auth-user-avatar-wrap" title="View Profile">
                        <img src="<?php echo htmlspecialchars($userProfileImage); ?>" alt="Profile" class="auth-user-avatar" id="topbarUserAvatar" onerror="this.src='images/icon/profile_logo.png'">
                    </a>
                </div>
            <?php else: ?>
                <a href="login_user/sign_up.php" class="auth-link">Sign Up</a>
                <div class="auth-divider"></div>
                <a href="login_user/login.php" class="auth-link">Login</a>
            <?php endif; ?>
        </div>
        <div class="mobile-top-icons">
            <div class="mobile-top-search" onclick="window.location.href='index.php'" title="Search">
                <img src="images/icon/search.png" alt="Search">
            </div>
            <div class="purchase-top-container" style="position: relative; display: inline-block;" title="Purchases">
                <img src="images/icon/purchase.png" alt="Purchases" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'"
                     onclick="window.location.href='purchase.php'">
                <span id="topbarPurchaseBadge" class="purchase-nav-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
            </div>
            <div class="cart-container" style="position: relative; display: inline-block; margin-right: 120px;" title="Cart">
                <img src="images/icon/cart.png" alt="Cart" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'"
                     onclick="window.location.href='save.php'">
                <span id="topbarCartCount" class="cart-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
            </div>
            <div class="chat-top-container" style="position: relative; display: none;" title="Chat with Admin" onclick="window.location.href='index.php'">
                <img src="images/icon/message.png" alt="Chat" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'">
                <span id="userChatBadge" style="display:none; position:absolute; top:-2px; right:0px; background:#ff3b30; width:9px; height:9px; border-radius:50%; box-shadow:0 0 6px rgba(255,59,48,0.9); border:1.5px solid #1a1a1a; box-sizing:border-box;"></span>
            </div>
        </div>
    </div>

    <!-- Mobile Top Bar -->
    <div class="top-bar">
        <div class="top-bar-content" style="justify-content: flex-start;">
            <span class="top-bar-text">Purchase</span>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toastNotification" class="toast-notification">
        <div class="toast-icon"><i class="fas fa-check-circle"></i></div>
        <div class="toast-message" id="toastMessage">Review submitted successfully!</div>
        <div class="toast-sub" id="toastSub">Thank you for your feedback</div>
    </div>

    <!-- Filter Tabs -->
    <div class="purchase-tabs">
        <?php foreach ($validTabs as $idx => $t): ?>
            <div class="purchase-tab <?php echo $tab === $t ? 'active' : ''; ?>" 
                 data-tab="<?php echo $t; ?>" 
                 data-index="<?php echo $idx; ?>"
                 onclick="switchTab('<?php echo $t; ?>', <?php echo $idx; ?>)">
                <?php echo $tabLabels[$t]; ?>
                <span class="tab-badge" id="badge-<?php echo $t; ?>" style="display: none;"></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Sliding Panels -->
    <div class="purchase-panels-wrapper">
        <div class="purchase-panels-track" id="panelsTrack">
            <?php foreach ($validTabs as $panelIdx => $t): ?>
                <div class="purchase-panel" id="panel-<?php echo $t; ?>">
                    <?php if ($errorMsg): ?>
                        <div class="error-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <p><?php echo htmlspecialchars($errorMsg); ?></p>
                        </div>
                    <?php else: ?>
                        <?php
                        $panelPurchases = array_filter($purchases, function($p) use ($t) {
                            if ($t === 'all') return true;
                            if ($t === 'lost') {
                                return intval($p['is_lost'] ?? 0) === 1;
                            }
                            // Exclude lost orders from regular tabs (pending, confirmed, completed, cancelled)
                            if (intval($p['is_lost'] ?? 0) === 1) {
                                return false;
                            }
                            return ($p['order_status'] ?? 'pending') === $t;
                        });
                        ?>
                        <?php if (empty($panelPurchases)): ?>
                            <div class="empty-state">
                                <dotlottie-wc src="https://lottie.host/7a9e1682-3342-49d1-a823-511f6b3f1fdc/x7SG6ZMpXp.lottie" style="width: 280px; height: 280px; max-width: 90vw;" autoplay loop></dotlottie-wc>
                                <p><?php echo $t === 'lost' ? 'No reported lost borrowed items yet' : ($t !== 'all' ? 'No ' . htmlspecialchars($tabLabels[$t]) . ' purchases yet' : 'No purchases yet'); ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($panelPurchases as $globalIndex => $purchase):
                                $status = $purchase['order_status'] ?? 'pending';
                                $statusClass = 'status-' . $status;
                                $orderProducts = $purchase['products'] ?? [];
                                $productCount = count($orderProducts);
                                $totalQty = 0;
                                foreach ($orderProducts as $op) {
                                    $totalQty += max(1, intval($op['quantity'] ?? 1));
                                }
                                $totalPrice = floatval($purchase['total_price'] ?? 0);
                                $origIndex = array_search($purchase, $purchases, true);
                                $actualOrigIndex = $origIndex !== false ? $origIndex : $globalIndex;
                                $isBuyOrder = ($purchase['product_status'] ?? '') === 'buy' || ($purchase['payment_method'] ?? '') === 'buy';
                                if (!$isBuyOrder && !empty($purchase['products'])) {
                                    $allBuy = true;
                                    foreach ($purchase['products'] as $op) {
                                        if (($op['product_status'] ?? '') !== 'buy') {
                                            $allBuy = false;
                                            break;
                                        }
                                    }
                                    if ($allBuy) $isBuyOrder = true;
                                }
                                $isLostOrder = intval($purchase['is_lost'] ?? 0) === 1;
                                $hasActions = (in_array($status, ['completed', 'cancelled']) || $isLostOrder);
                                $firstProduct = $orderProducts[0] ?? [];
                            ?>
                                <?php if ($productCount > 1): ?>
                                    <!-- Combined Multi-Product Purchase Card -->
                                    <div class="purchase-card multi-product <?php echo $hasActions ? 'has-actions' : ''; ?>" data-purchase-id="<?php echo $purchase['id']; ?>" data-status="<?php echo htmlspecialchars($status); ?>" onclick="showPurchaseDetail(<?php echo $actualOrigIndex; ?>)">
                                        <div class="multi-card-top">
                                            <div class="multi-card-order-tag" style="display:flex; align-items:center; gap:6px;">
                                                <i class="fas fa-shopping-bag" style="color:#ffd700;"></i>
                                                <span style="color:#ffd700; font-weight:600;"><?php echo $productCount; ?> item<?php echo $productCount > 1 ? 's' : ''; ?></span>
                                            </div>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <?php if (!$isLostOrder): ?>
                                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="multi-products-list">
                                            <?php foreach ($orderProducts as $pIdx => $p): 
                                                $pModel = $p['model_path'] ?? '';
                                                $pImage = $p['image_path'] ?? ($p['image_path2'] ?? ($p['image_path3'] ?? ''));
                                                $pHasModel = !empty($pModel) && pathinfo($pModel, PATHINFO_EXTENSION) === 'glb';
                                                $pDisplayImg = !empty($pImage) ? $pImage : (!empty($pModel) && pathinfo($pModel, PATHINFO_EXTENSION) !== 'glb' ? $pModel : '');
                                                $pQty = max(1, intval($p['quantity'] ?? 1));
                                                $pTotal = floatval($p['total_price'] ?? 0);
                                            ?>
                                                <div class="multi-product-item">
                                                    <div class="multi-item-thumb">
                                                        <?php if (!empty($pDisplayImg)): ?>
                                                            <img src="<?php echo htmlspecialchars($pDisplayImg); ?>" alt="<?php echo htmlspecialchars($p['product_name']); ?>" style="width: 100%; height: 100%; object-fit: cover; object-position: top; border-radius: 6px; display: block;">
                                                        <?php elseif ($pHasModel): ?>
                                                            <model-viewer
                                                                src="<?php echo htmlspecialchars($pModel); ?>"
                                                                alt="<?php echo htmlspecialchars($p['product_name']); ?>"
                                                                auto-rotate
                                                                camera-orbit="auto 90deg auto"
                                                                camera-controls
                                                                disable-pan
                                                                disable-zoom
                                                                min-camera-orbit="auto 90deg auto"
                                                                max-camera-orbit="auto 90deg auto"
                                                                style="width: 100%; height: 100%; border-radius: 6px;">
                                                            </model-viewer>
                                                        <?php else: ?>
                                                            <i class="fas fa-image" style="font-size: 1.3rem; color: #555;"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="multi-item-info">
                                                        <div class="multi-item-name"><?php echo htmlspecialchars($p['product_name']); ?></div>
                                                        <div class="multi-item-category"><?php echo htmlspecialchars($p['category'] ?? ''); ?></div>
                                                    </div>
                                                    <div class="multi-item-price-col">
                                                        <div class="multi-item-price">₱<?php echo number_format($pTotal, 0); ?></div>
                                                        <div class="multi-item-qty"><?php echo $pQty; ?> item<?php echo $pQty > 1 ? 's' : ''; ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="multi-card-bottom">
                                            <div class="multi-card-dates">
                                                <i class="far fa-calendar-alt" style="margin-right: 4px;"></i>
                                                <?php if ($isBuyOrder || empty($purchase['return_date'])): ?>
                                                    <?php echo $isBuyOrder ? 'Pick-up Date: ' : 'Reserve Date: '; ?><?php echo htmlspecialchars($purchase['reserve_date']); ?>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($purchase['reserve_date']); ?> &bull; <?php echo htmlspecialchars($purchase['return_date']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="multi-card-total-box">
                                                <span class="multi-card-total-label">Total (<?php echo $totalQty; ?> items):</span>
                                                <span class="multi-card-total-val">₱<?php echo number_format($totalPrice, 0); ?></span>
                                            </div>
                                        </div>

                                        <?php if ($hasActions): ?>
                                            <div class="multi-card-actions-row">
                                                <?php if ($isLostOrder): ?>
                                                    <?php $isSettled = ($purchase['lost_status'] ?? '') === 'settled'; ?>
                                                    <span class="lost-status-pill <?php echo $isSettled ? 'settled' : ''; ?>"><?php echo $isSettled ? '<i class="fas fa-check-circle"></i> Settled' : '<i class="fas fa-clock"></i> Requested'; ?></span>
                                                <?php elseif ($status === 'completed' && $t !== 'lost'): ?>
                                                    <button class="buy-again-btn" onclick="event.stopPropagation(); buyAgain(<?php echo $firstProduct['product_id'] ?? 0; ?>)">Buy Again</button>
                                                    <?php 
                                                        $hasGroupReview = false;
                                                        $groupRating = 5;
                                                        foreach ($orderProducts as $op) {
                                                            if (!empty($op['has_review'])) {
                                                                $hasGroupReview = true;
                                                                $groupRating = intval($op['user_rating'] ?? 5);
                                                                break;
                                                            }
                                                        }
                                                    ?>
                                                    <?php if ($hasGroupReview): ?>
                                                        <div class="card-review-row reviewed" onclick="event.stopPropagation();">
                                                            <span class="review-label">Reviewed</span>
                                                            <div class="card-stars">
                                                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                                                    <span class="card-star <?php echo $s <= $groupRating ? 'rated' : 'unrated'; ?>">&#9733;</span>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="card-review-row" onclick="event.stopPropagation();">
                                                            <span class="review-label" onclick="openReviewOverlay(<?php echo $actualOrigIndex; ?>, 0, 5)">Review</span>
                                                            <div class="card-stars" onmouseleave="resetCardStars(this)">
                                                                <span class="card-star" onmouseenter="hoverCardStars(this, 1)" onclick="openReviewOverlay(<?php echo $actualOrigIndex; ?>, 0, 1)">&#9733;</span>
                                                                <span class="card-star" onmouseenter="hoverCardStars(this, 2)" onclick="openReviewOverlay(<?php echo $actualOrigIndex; ?>, 0, 2)">&#9733;</span>
                                                                <span class="card-star" onmouseenter="hoverCardStars(this, 3)" onclick="openReviewOverlay(<?php echo $actualOrigIndex; ?>, 0, 3)">&#9733;</span>
                                                                <span class="card-star" onmouseenter="hoverCardStars(this, 4)" onclick="openReviewOverlay(<?php echo $actualOrigIndex; ?>, 0, 4)">&#9733;</span>
                                                                <span class="card-star" onmouseenter="hoverCardStars(this, 5)" onclick="openReviewOverlay(<?php echo $actualOrigIndex; ?>, 0, 5)">&#9733;</span>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php elseif ($status === 'cancelled'): ?>
                                                    <button class="buy-again-btn" onclick="event.stopPropagation(); buyAgain(<?php echo $firstProduct['product_id'] ?? 0; ?>)">Buy Again</button>
                                                    <button class="cancel-reason-btn" onclick="event.stopPropagation(); showCancellationReason(<?php echo $actualOrigIndex; ?>)">View Reason</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <!-- Single Product Purchase Card -->
                                    <?php 
                                        $modelPath = $firstProduct['model_path'] ?? ($purchase['model_path'] ?? '');
                                        $imagePath = $firstProduct['image_path'] ?? ($purchase['image_path'] ?? '');
                                        $hasModel = !empty($modelPath) && pathinfo($modelPath, PATHINFO_EXTENSION) === 'glb';
                                        $displayImage = !empty($imagePath) ? $imagePath : (!empty($modelPath) && pathinfo($modelPath, PATHINFO_EXTENSION) !== 'glb' ? $modelPath : '');
                                        $quantity = max(1, intval($firstProduct['quantity'] ?? ($purchase['quantity'] ?? 1)));
                                        $unitPrice = floatval($firstProduct['price'] ?? ($purchase['price'] ?? 0));
                                        $prodName = $firstProduct['product_name'] ?? ($purchase['product_name'] ?? '');
                                        $prodCat = $firstProduct['category'] ?? ($purchase['category'] ?? '');
                                    ?>
                                    <div class="purchase-card <?php echo $hasActions ? 'has-actions' : ''; ?>" data-purchase-id="<?php echo $purchase['id']; ?>" data-status="<?php echo htmlspecialchars($status); ?>" onclick="showPurchaseDetail(<?php echo $actualOrigIndex; ?>)">
                                        <div class="purchase-card-body">
                                            <div class="purchase-card-model">
                                            <?php if (!empty($displayImage)): ?>
                                                <img src="<?php echo htmlspecialchars($displayImage); ?>" alt="<?php echo htmlspecialchars($prodName); ?>" style="width: 100%; height: 100%; object-fit: cover; object-position: top; border-radius: 8px; display: block;">
                                            <?php elseif ($hasModel): ?>
                                                <model-viewer
                                                    src="<?php echo htmlspecialchars($modelPath); ?>"
                                                    alt="<?php echo htmlspecialchars($prodName); ?>"
                                                    auto-rotate
                                                    camera-orbit="auto 90deg auto"
                                                    camera-controls
                                                    disable-pan
                                                    disable-zoom
                                                    min-camera-orbit="auto 90deg auto"
                                                    max-camera-orbit="auto 90deg auto"
                                                    style="width: 100%; height: 100%; border-radius: 8px;">
                                                </model-viewer>
                                            <?php else: ?>
                                                <i class="fas fa-image" style="font-size: 1.8rem; color: #555;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="purchase-card-info">
                                            <div class="purchase-card-name"><?php echo htmlspecialchars($prodName); ?></div>
                                            <div class="purchase-card-category"><?php echo htmlspecialchars($prodCat); ?></div>
                                            <?php 
                                                $isSingleBuy = ($firstProduct['product_status'] ?? ($purchase['product_status'] ?? '')) === 'buy' || ($purchase['payment_method'] ?? '') === 'buy';
                                                $isSingleLost = intval($purchase['is_lost'] ?? 0) === 1;
                                                $hasActions = (in_array($status, ['completed', 'cancelled']) || $isSingleLost);
                                            ?>
                                            <div class="purchase-card-dates-preview" style="font-size: 11px; color: #888; margin-bottom: 4px;">
                                                <i class="far fa-calendar-alt" style="margin-right: 4px;"></i>
                                                <?php if ($isSingleBuy || empty($purchase['return_date'])): ?>
                                                    <?php echo $isSingleBuy ? 'Pick-up Date: ' : 'Reserve Date: '; ?><?php echo htmlspecialchars($purchase['reserve_date']); ?>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($purchase['reserve_date']); ?> &bull; <?php echo htmlspecialchars($purchase['return_date']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="purchase-card-price">
                                                ₱<?php echo number_format($totalPrice > 0 ? $totalPrice : $unitPrice, 0); ?>
                                                <?php if ($quantity > 1): ?>
                                                    <span style="color: #aaa; font-size: 11px; font-weight: 400; margin-left: 5px;"><?php echo $quantity; ?> items</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        </div>
                                        <div class="purchase-card-status">
                                            <?php if (!$isSingleLost): ?>
                                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if ($hasActions): ?>
                                            <div class="card-actions-bottom">
                                                <?php if ($isSingleLost): ?>
                                                    <?php $isSettled = ($purchase['lost_status'] ?? '') === 'settled'; ?>
                                                    <span class="lost-status-pill <?php echo $isSettled ? 'settled' : ''; ?>"><?php echo $isSettled ? '<i class="fas fa-check-circle"></i> Settled' : '<i class="fas fa-clock"></i> Requested'; ?></span>
                                                <?php elseif ($status === 'completed'): ?>
                                                    <button class="buy-again-btn" onclick="event.stopPropagation(); buyAgain(<?php echo $firstProduct['product_id'] ?? 0; ?>)">Buy Again</button>
                                                <?php elseif ($status === 'cancelled'): ?>
                                                    <button class="buy-again-btn" onclick="event.stopPropagation(); buyAgain(<?php echo $firstProduct['product_id'] ?? 0; ?>)">Buy Again</button>
                                                    <button class="cancel-reason-btn" onclick="event.stopPropagation(); showCancellationReason(<?php echo $actualOrigIndex; ?>)">View Reason</button>
                                                <?php endif; ?>

                                                <?php if ($status === 'completed' && $t !== 'lost' && !$isSingleLost): ?>
                                                    <?php if (!empty($firstProduct['has_review'])): ?>
                                                        <?php $userRating = intval($firstProduct['user_rating'] ?? 5); ?>
                                                        <div class="card-review-row reviewed" onclick="event.stopPropagation();">
                                                            <span class="review-label">Reviewed</span>
                                                            <div class="card-stars">
                                                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                                                    <span class="card-star <?php echo $s <= $userRating ? 'rated' : 'unrated'; ?>">&#9733;</span>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="card-review-row" onclick="event.stopPropagation();">
                                                            <span class="review-label" onclick="openReviewOverlay(<?php echo $actualOrigIndex; ?>, 0, 5)">Review</span>
                                                            <div class="card-stars" onmouseleave="resetCardStars(this)">
                                                                <span class="card-star" onmouseenter="hoverCardStars(this, 1)" onclick="openReviewOverlay(<?php echo $actualOrigIndex; ?>, 0, 1)">&#9733;</span>
                                                                <span class="card-star" onmouseenter="hoverCardStars(this, 2)" onclick="openReviewOverlay(<?php echo $actualOrigIndex; ?>, 0, 2)">&#9733;</span>
                                                                <span class="card-star" onmouseenter="hoverCardStars(this, 3)" onclick="openReviewOverlay(<?php echo $actualOrigIndex; ?>, 0, 3)">&#9733;</span>
                                                                <span class="card-star" onmouseenter="hoverCardStars(this, 4)" onclick="openReviewOverlay(<?php echo $actualOrigIndex; ?>, 0, 4)">&#9733;</span>
                                                                <span class="card-star" onmouseenter="hoverCardStars(this, 5)" onclick="openReviewOverlay(<?php echo $actualOrigIndex; ?>, 0, 5)">&#9733;</span>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    </div> <!-- End of .shell -->

    <!-- Purchase Detail Modal (Big Centered Modal) -->
    <div id="purchaseDetailBackdrop" class="purchase-detail-backdrop" onclick="closePurchaseDetail()"></div>
    <div id="purchaseDetailOverlay" class="purchase-detail-overlay" role="dialog" aria-modal="true" aria-labelledby="purchaseDetailTitle">
        <div class="purchase-detail-header">
            <div class="purchase-detail-title-wrap">
                <div class="purchase-detail-icon" id="purchaseDetailIcon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div>
                    <h3 id="purchaseDetailTitle" class="purchase-detail-title">Purchase Details</h3>
                    <div id="purchaseDetailSubTitle" class="purchase-detail-subtitle">Reservation &amp; Item Summary</div>
                </div>
            </div>
            <button type="button" class="purchase-detail-close-btn" onclick="closePurchaseDetail()" title="Close Details (Esc)" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="purchase-detail-body" id="purchaseDetailBody">
        </div>
    </div>

    <!-- Review Overlay -->
    <div id="reviewOverlay" class="review-overlay">
        <div class="review-header">
            <img src="images/icon/back.png" alt="Back" onclick="closeReviewOverlay()">
            <span>Write a Review</span>
        </div>
        <div class="review-body" id="reviewBody">
        </div>
    </div>

    <!-- Cancellation Reason Overlay -->
    <div id="cancellationOverlay" class="cancellation-overlay">
        <div class="cancellation-header">
            <img src="images/icon/back.png" alt="Back" onclick="closeCancellationReason()">
            <span>Cancellation Details</span>
        </div>
        <div class="cancellation-body" id="cancellationBody">
        </div>
    </div>

    <!-- Photo Lightbox Modal -->
    <div id="cancellationLightbox" class="cancellation-lightbox" onclick="closeCancellationLightbox()">
        <img id="cancellationLightboxImg" src="" alt="Full Photo View">
        <button type="button" class="lightbox-close-btn">&times;</button>
    </div>

    <!-- Calendar Date Picker Modal for Rescheduling (Identical to index.php) -->
    <div id="calendarModalOverlay" class="calendar-modal-overlay" onclick="closeRescheduleModal(event)">
        <div class="calendar-modal" onclick="event.stopPropagation()">
            <!-- Reschedule Header & Details -->
            <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.12);">
                <div>
                    <div style="font-size: 15px; font-weight: 700; color: #ffd700; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-calendar-alt"></i> Reschedule Product Rental Period
                    </div>
                    <div id="rescheduleProductSubtitle" style="font-size: 12px; color: #bbb; margin-top: 3px;">
                        Loading product info...
                    </div>
                </div>
                <button type="button" onclick="closeRescheduleModal()" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #aaa; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 15px; line-height: 1; transition: all 0.2s;" onmouseover="this.style.color='#fff'; this.style.borderColor='#ffd700';" onmouseout="this.style.color='#aaa'; this.style.borderColor='rgba(255,255,255,0.15)';">
                    &times;
                </button>
            </div>

            <!-- Current vs New Selection Box -->
            <div style="background: #111215; border: 1px solid rgba(255,215,0,0.25); border-radius: 10px; padding: 12px 14px; margin-bottom: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: 11.5px;">
                    <span style="color: #888;">Current Reserved Period:</span>
                    <span id="rescheduleCurrentDatesDisplay" style="color: #fff; font-weight: 600;">-</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: 11.5px;">
                    <span style="color: #888;">New Reserve Date:</span>
                    <strong id="rescheduleNewReserveDisplay" style="color: #ffd700; font-weight: 700;">Please click an available date below</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11.5px;">
                    <span style="color: #888;">New Return Date:</span>
                    <strong id="rescheduleNewReturnDisplay" style="color: #6cd488; font-weight: 700;">-</strong>
                </div>
                <div style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed rgba(255,255,255,0.1); font-size: 10.5px; color: #f0ad4e; display: flex; align-items: center; gap: 5px;">
                    <i class="fas fa-exclamation-triangle"></i> You can only reschedule once. The 1st reserve date is now available.
                </div>
            </div>

            <!-- Calendar Navigator (identical to index.php) -->
            <div class="calendar-modal-header">
                <div class="calendar-modal-nav">
                    <button type="button" id="reschedulePrevMonthBtn" onclick="changeRescheduleCalendarMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                    <div class="calendar-modal-title" id="calendarModalTitle">October 2026</div>
                    <button type="button" id="rescheduleNextMonthBtn" onclick="changeRescheduleCalendarMonth(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>

            <!-- Calendar Guide & Legend (identical to index.php) -->
            <div id="calendarModalGuide" class="calendar-modal-guide">
                <!-- Dynamic guide text and legend -->
            </div>

            <!-- Calendar Grid (identical to index.php) -->
            <div class="calendar-grid" id="calendarModalGrid">
                <!-- Days generated by JS -->
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; margin-top: 16px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.1);">
                <button type="button" onclick="closeRescheduleModal()" style="padding: 9px 18px; background: #262830; color: #ddd; border: 1px solid #383b45; border-radius: 8px; font-size: 12.5px; cursor: pointer; font-weight: 500;">
                    Cancel
                </button>
                <button type="button" id="confirmRescheduleBtn" onclick="submitReschedule()" disabled style="padding: 9px 22px; background: linear-gradient(135deg, #7c6300, #ffd700); color: #000; border: none; border-radius: 8px; font-size: 12.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; opacity: 0.5; box-shadow: 0 4px 14px rgba(255,215,0,0.25);">
                    <i class="fas fa-check"></i> Confirm Reschedule
                </button>
            </div>
        </div>
    </div>

    <!-- Custom Reschedule Confirmation Modal (Matching UI of other confirmation modals) -->
    <div id="rescheduleConfirmModal" class="confirm-modal-overlay" onclick="closeRescheduleConfirmModal(event)">
        <div class="confirm-modal-card" onclick="event.stopPropagation()">
            <div class="confirm-modal-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="confirm-modal-title">Confirm Reschedule</div>
            <div class="confirm-modal-desc">Are you sure you want to reschedule your reservation to the selected dates?</div>
            <div class="confirm-modal-dates-box">
                <div class="confirm-modal-date-row">
                    <span>New Reserve Date:</span>
                    <strong id="confirmModalReserveDate" style="color: #ffd700;">-</strong>
                </div>
                <div class="confirm-modal-date-row">
                    <span>New Return Date:</span>
                    <strong id="confirmModalReturnDate" style="color: #6cd488;">-</strong>
                </div>
            </div>
            <div class="confirm-modal-warning">
                <i class="fas fa-exclamation-triangle" style="flex-shrink: 0;"></i>
                <span>You can only reschedule once. Once confirmed, this cannot be reversed!</span>
            </div>
            <div class="confirm-modal-actions">
                <button type="button" class="confirm-btn-cancel" id="cancelRescheduleConfirmBtn" onclick="closeRescheduleConfirmModal()">Cancel</button>
                <button type="button" class="confirm-btn-save" id="saveRescheduleConfirmBtn" onclick="executeRescheduleSubmission()">Save</button>
            </div>
        </div>
    </div>

    <!-- Lost Item Request & Settlement Modal (Big Modal matching Purchase Details) -->
    <div id="lostItemBackdrop" class="lost-item-backdrop" onclick="closeLostItemModal()" style="display: none;"></div>
    <div id="lostItemModal" class="lost-item-modal" role="dialog" aria-modal="true" aria-labelledby="lostModalTitle" style="display: none;">
        <div class="lost-modal-header">
            <div class="lost-modal-title-wrap">
                <div class="lost-modal-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h3 id="lostModalTitle" class="lost-modal-title">Report Lost Borrowed Item</h3>
                    <div class="lost-modal-subtitle">Order #<span id="lostModalOrderId">-</span> &bull; Settlement Request &amp; Replacement</div>
                </div>
            </div>
            <button type="button" class="lost-modal-close-btn" onclick="closeLostItemModal()" title="Close (Esc)" aria-label="Close modal">
                &times;
            </button>
        </div>
        <div class="lost-modal-body">
            <!-- Notice Banner -->
            <div class="lost-policy-banner">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Lost Item Policy:</strong> If you lost a borrowed item, you are only required to pay for the item based on its replacement selling price, <strong>minus the rental payments you already made</strong>, plus overdue late fees if past the return date.
                </div>
            </div>

            <!-- Two-Column Responsive Layout -->
            <div class="lost-modal-grid">
                <!-- Left Column: Items & Calculation Breakdown -->
                <div class="lost-modal-col">
                    <div class="lost-section-label">
                        <i class="fas fa-tshirt" style="color:#ffb74d;"></i> Borrowed Items
                    </div>
                    <!-- Product Summary List -->
                    <div id="lostModalProductsList" style="display: flex; flex-direction: column; gap: 8px;">
                    </div>

                    <!-- Calculation Breakdown Card -->
                    <div class="lost-calc-card">
                        <div class="lost-calc-header">
                            <i class="fas fa-receipt" style="color: #ffd700;"></i> Settlement Summary
                        </div>
                        <div class="lost-calc-row">
                            <span style="color: #aaa;">Total Selling Price:</span>
                            <span id="lostCalcSellingPrice" style="color: #fff; font-weight: 600;">₱0</span>
                        </div>
                        <div class="lost-calc-row">
                            <span style="color: #aaa;">Less: Rental Amount Paid:</span>
                            <span id="lostCalcUserPaid" style="color: #6cd488; font-weight: 600;">-₱0</span>
                        </div>
                        <div class="lost-calc-row">
                            <span style="color: #aaa;">Selling Price Difference:</span>
                            <span id="lostCalcDiff" style="color: #fff; font-weight: 600;">₱0</span>
                        </div>
                        <div class="lost-calc-row" id="lostCalcOverdueRow" style="display: none;">
                            <span style="color: #ffb74d;">Overdue Late Fee (<span id="lostCalcOverdueDays">0</span> days):</span>
                            <span id="lostCalcOverdueFee" style="color: #ffb74d; font-weight: 600;">+₱0</span>
                        </div>
                        <div class="lost-calc-row total-row">
                            <span style="color: #ffd700;">Total Settlement Due:</span>
                            <span id="lostCalcTotalDue" style="color: #ffd700; font-size: 18px; font-weight: 700;">₱0</span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Reason, QR Ph Payment, & Proof Upload -->
                <div class="lost-modal-col">
                    <!-- Lost Reason -->
                    <div class="lost-form-group">
                        <label class="lost-form-label">
                            Explanation / Reason for Lost Item <span style="color: #ff5252;">*</span>
                        </label>
                        <textarea id="lostItemReason" class="lost-textarea" placeholder="Please explain how or when the item was lost..."></textarea>
                    </div>

                    <!-- QR Ph Payment Instructions -->
                    <div class="lost-qr-card">
                        <div class="lost-qr-thumb-wrap">
                            <img src="<?php echo htmlspecialchars($gcashQrPath); ?>" alt="QR Ph RCBC" id="lostQrImg" onclick="openCancellationLightbox('<?php echo htmlspecialchars($gcashQrPath); ?>')" title="Tap to view full QR">
                            <span class="lost-qr-tap-hint"><i class="fas fa-search-plus"></i> Tap to view</span>
                        </div>
                        <div class="lost-qr-info">
                            <div class="lost-qr-badge">Pay via QR Ph / RCBC</div>
                            <div class="lost-qr-acc-name"><?php echo htmlspecialchars($gcashAccountName); ?></div>
                            <div class="lost-qr-acc-row">
                                <span class="lost-qr-acc-num" id="lostRcbcNumber"><?php echo htmlspecialchars($gcashNumber); ?></span>
                                <button type="button" class="lost-qr-copy-btn" onclick="copyLostAccountNumber()">
                                    <i class="far fa-copy"></i> Copy
                                </button>
                            </div>
                            <div class="lost-qr-sub">Scan using GCash, Maya, RCBC, or any QR Ph banking app.</div>
                        </div>
                    </div>

                    <!-- Proof of Payment Upload -->
                    <div class="lost-form-group">
                        <label class="lost-form-label">
                            Upload Proof of Settlement Payment <span style="font-size: 11px; color: #888; font-weight: 400;">(Optional now or submit after payment)</span>
                        </label>
                        <div class="lost-upload-dropzone" onclick="document.getElementById('lostProofInput').click()">
                            <input type="file" id="lostProofInput" accept="image/*" style="display: none;" onchange="previewLostProof(this)">
                            <div id="lostProofPlaceholder">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: #ffd700; margin-bottom: 4px;"></i>
                                <div style="font-size: 12.5px; color: #ddd; font-weight: 500;">Click to choose payment receipt / screenshot</div>
                                <div style="font-size: 11px; color: #777;">PNG, JPG, JPEG up to 10MB</div>
                            </div>
                            <div id="lostProofPreviewContainer" style="display: none; align-items: center; justify-content: center; gap: 12px;">
                                <img id="lostProofPreviewImg" src="" alt="Proof Preview" style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #ffd700;">
                                <div style="text-align: left;">
                                    <div id="lostProofFileName" style="font-size: 12px; color: #fff; font-weight: 600; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">filename.jpg</div>
                                    <button type="button" onclick="event.stopPropagation(); clearLostProof();" style="background: none; border: none; color: #ff5252; font-size: 11px; cursor: pointer; padding: 0; margin-top: 4px; display: flex; align-items: center; gap: 4px;">
                                        <i class="fas fa-times"></i> Remove photo
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="lost-modal-actions">
                <button type="button" class="lost-cancel-btn" onclick="closeLostItemModal()">
                    Cancel
                </button>
                <button type="button" id="submitLostItemBtn" class="lost-submit-btn" onclick="submitLostItemRequest()">
                    Submit
                </button>
            </div>
        </div>
    </div>

    <nav class="mobile-bottom-nav has-straight-nav" id="mobileBottomNav" aria-label="Mobile Navigation">
        <!-- Straight Background Bar -->
        <div class="mobile-nav-bg-container straight">
            <div class="mobile-nav-wing"></div>
        </div>

        <a href="index.php" class="nav-item" onclick="setActiveNav(this)">
            <img src="images/icon/home.png" alt="Home" class="nav-icon">
            <span class="nav-text">Home</span>
        </a>
        <a href="save.php" class="nav-item" onclick="setActiveNav(this)">
            <div style="position: relative; display: inline-flex; align-items: center; justify-content: center;">
                <img src="images/icon/cart.png" alt="Cart" class="nav-icon">
                <span id="cartCount" class="purchase-nav-badge cart-badge" style="display: none;">0</span>
            </div>
            <span class="nav-text">Cart</span>
        </a>
        <a href="purchase.php" class="nav-item active" onclick="setActiveNav(this)">
            <div style="position: relative; display: inline-flex; align-items: center; justify-content: center;">
                <img src="images/icon/purchase.png" alt="Purchases" class="nav-icon">
                <span id="purchaseBadge" class="purchase-nav-badge" style="display: none;">0</span>
            </div>
            <span class="nav-text">Purchases</span>
        </a>
        <a href="profile.php" class="nav-item" onclick="setActiveNav(this)">
            <img src="images/icon/profile.png" alt="Profile" class="nav-icon">
            <span class="nav-text">Profile</span>
        </a>
    </nav>

    <script>
        const purchasesData = <?php echo json_encode($purchases); ?>;
        const validTabs = <?php echo json_encode($validTabs); ?>;
        let currentTabIndex = <?php echo array_search($tab, $validTabs); ?>;
        const rentalDurationDaysSetting = <?php echo intval($rentalDurationDays); ?>;
        const maxExtensionDaysSetting = <?php echo intval($maxExtensionDays); ?>;
        const extensionPricePerDaySetting = <?php echo floatval($extensionPricePerDay); ?>;
        const overduePenaltyPercentSetting = <?php echo intval($overduePenaltyPercent); ?>;
        const rcbcQrPathSetting = <?php echo json_encode($gcashQrPath); ?>;
        const rcbcNumberSetting = <?php echo json_encode($gcashNumber); ?>;
        const rcbcNameSetting = <?php echo json_encode($gcashAccountName); ?>;

        let currentLostIndex = null;
        let currentLostProofFile = null;

        function calculateLostSettlement(p) {
            const products = p.products || [];
            let totalSelling = 0;
            let qty = 1;
            let rentalPrice = parseFloat(p.price || 0);

            if (products.length > 0) {
                totalSelling = products.reduce((acc, prod) => {
                    const sp = parseFloat(prod.selling_price || prod.purchase_price || prod.price || 0);
                    const q = Math.max(1, parseInt(prod.quantity || 1));
                    return acc + (sp * q);
                }, 0);
                qty = products.reduce((acc, prod) => acc + Math.max(1, parseInt(prod.quantity || 1)), 0);
                rentalPrice = products.reduce((acc, prod) => acc + parseFloat(prod.price || 0), 0) / products.length;
            } else {
                const sp = parseFloat(p.selling_price || p.purchase_price || p.price || 0);
                qty = Math.max(1, parseInt(p.quantity || 1));
                totalSelling = sp * qty;
            }

            const paymentOption = p.payment_option || 'full';
            let userPaid = 0;
            if (paymentOption === 'first_payment') {
                userPaid = parseFloat(p.downpayment_amount || 0);
            } else {
                userPaid = parseFloat(p.total_price || 0);
            }

            const diff = Math.max(0, totalSelling - userPaid);

            let overdueDays = 0;
            let overdueFee = 0;
            if (p.return_date) {
                const retD = new Date(p.return_date + 'T00:00:00');
                const nowD = new Date();
                nowD.setHours(0, 0, 0, 0);
                if (nowD > retD) {
                    overdueDays = Math.max(0, Math.floor((nowD - retD) / (1000 * 60 * 60 * 24)));
                    overdueFee = overdueDays * (rentalPrice * (overduePenaltyPercentSetting / 100)) * qty;
                }
            }

            const totalDue = diff + overdueFee;

            return {
                totalSelling,
                userPaid,
                diff,
                overdueDays,
                overdueFee,
                totalDue,
                qty
            };
        }

        function openLostItemModal(index) {
            const p = purchasesData[index];
            if (!p) return;

            const status = (p.order_status || 'pending').toLowerCase();
            const isBuy = (p.product_status === 'buy' || p.payment_method === 'buy' || (Array.isArray(p.products) && p.products.length > 0 && p.products.every(op => op.product_status === 'buy')));
            if (isBuy || status !== 'confirmed') {
                showToast('Not Eligible', 'Only confirmed reservations can be reported as lost borrowed items.');
                return;
            }

            if (parseInt(p.is_lost || 0) === 1) {
                showToast('Already Reported', 'This item has already been reported as lost.');
                return;
            }

            currentLostIndex = index;
            currentLostProofFile = null;

            document.getElementById('lostModalOrderId').textContent = p.id;
            document.getElementById('lostItemReason').value = '';
            clearLostProof();

            // Render Products in modal
            const listEl = document.getElementById('lostModalProductsList');
            const prods = p.products || [];
            let prodsHtml = '';

            const renderProdList = prods.length > 0 ? prods : [p];
            renderProdList.forEach(prod => {
                const pImg = prod.image_path || prod.imagePath || prod.image_path2 || prod.image_path3 || '';
                const pName = prod.product_name || 'Borrowed Item';
                const pCat = prod.category || '';
                const pQty = Math.max(1, parseInt(prod.quantity || 1));
                const sp = parseFloat(prod.selling_price || prod.purchase_price || prod.price || 0);

                prodsHtml += `
                    <div style="display: flex; gap: 10px; background: #121215; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 10px; align-items: center;">
                        <div style="width: 48px; height: 48px; border-radius: 6px; overflow: hidden; background: #222; flex-shrink: 0;">
                            ${pImg ? `<img src="${pImg}" alt="${pName}" style="width: 100%; height: 100%; object-fit: cover;">` : `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#666;"><i class="fas fa-tshirt"></i></div>`}
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 13px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${pName}</div>
                            <div style="font-size: 11px; color: #888;">${pCat} &bull; Qty: ${pQty}</div>
                        </div>
                        <div style="text-align: right; flex-shrink: 0;">
                            <div style="font-size: 11px; color: #aaa;">Selling Price:</div>
                            <div style="font-size: 13px; font-weight: 700; color: #ffd700;">₱${(sp * pQty).toLocaleString(undefined, {maximumFractionDigits:0})}</div>
                        </div>
                    </div>
                `;
            });
            listEl.innerHTML = prodsHtml;

            // Calculations
            const calc = calculateLostSettlement(p);
            document.getElementById('lostCalcSellingPrice').textContent = '₱' + calc.totalSelling.toLocaleString(undefined, {maximumFractionDigits:0});
            document.getElementById('lostCalcUserPaid').textContent = '-₱' + calc.userPaid.toLocaleString(undefined, {maximumFractionDigits:0});
            document.getElementById('lostCalcDiff').textContent = '₱' + calc.diff.toLocaleString(undefined, {maximumFractionDigits:0});

            const overdueRow = document.getElementById('lostCalcOverdueRow');
            if (calc.overdueDays > 0 && calc.overdueFee > 0) {
                overdueRow.style.display = 'flex';
                document.getElementById('lostCalcOverdueDays').textContent = calc.overdueDays;
                document.getElementById('lostCalcOverdueFee').textContent = '+₱' + calc.overdueFee.toLocaleString(undefined, {maximumFractionDigits:0});
            } else {
                overdueRow.style.display = 'none';
            }

            document.getElementById('lostCalcTotalDue').textContent = '₱' + calc.totalDue.toLocaleString(undefined, {maximumFractionDigits:0});

            // Show modal
            const lostModal = document.getElementById('lostItemModal');
            if (lostModal) lostModal.scrollTop = 0;
            document.getElementById('lostItemBackdrop').style.display = 'block';
            if (lostModal) lostModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeLostItemModal() {
            document.getElementById('lostItemBackdrop').style.display = 'none';
            document.getElementById('lostItemModal').style.display = 'none';
            currentLostIndex = null;
            currentLostProofFile = null;
            const detailOverlay = document.getElementById('purchaseDetailOverlay');
            if (!detailOverlay || detailOverlay.style.display === 'none') {
                document.body.style.overflow = 'auto';
            }
        }

        function copyLostAccountNumber() {
            const num = document.getElementById('lostRcbcNumber')?.textContent || '';
            if (navigator.clipboard) {
                navigator.clipboard.writeText(num).then(() => {
                    showToast('Copied to Clipboard', 'Account number ' + num + ' copied.');
                });
            } else {
                const input = document.createElement('input');
                input.value = num;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                showToast('Copied to Clipboard', 'Account number copied.');
            }
        }

        function previewLostProof(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.size > 10 * 1024 * 1024) {
                    showToast('File Too Large', 'Please select an image smaller than 10MB.');
                    input.value = '';
                    return;
                }
                currentLostProofFile = file;
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('lostProofPreviewImg').src = e.target.result;
                    document.getElementById('lostProofFileName').textContent = file.name;
                    document.getElementById('lostProofPlaceholder').style.display = 'none';
                    document.getElementById('lostProofPreviewContainer').style.display = 'flex';
                };
                reader.readAsDataURL(file);
            }
        }

        function clearLostProof() {
            currentLostProofFile = null;
            const input = document.getElementById('lostProofInput');
            if (input) input.value = '';
            const previewImg = document.getElementById('lostProofPreviewImg');
            if (previewImg) previewImg.src = '';
            const placeholder = document.getElementById('lostProofPlaceholder');
            if (placeholder) placeholder.style.display = 'block';
            const container = document.getElementById('lostProofPreviewContainer');
            if (container) container.style.display = 'none';
        }

        function submitLostItemRequest() {
            if (currentLostIndex === null) return;
            const p = purchasesData[currentLostIndex];
            if (!p) return;

            const reason = document.getElementById('lostItemReason').value.trim();
            if (!reason) {
                showToast('Reason Required', 'Please enter an explanation of how or when the item was lost.');
                document.getElementById('lostItemReason').focus();
                return;
            }

            const btn = document.getElementById('submitLostItemBtn');
            const origText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

            const formData = new FormData();
            formData.append('reservation_id', p.id);
            formData.append('reason', reason);
            if (currentLostProofFile) {
                formData.append('payment_proof', currentLostProofFile);
            }

            fetch('request_lost_item.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = origText;

                if (data.success) {
                    showToast('Lost Request Submitted', 'Lost item reported. Settle amount: ₱' + (data.total_due || 0).toLocaleString());
                    
                    p.is_lost = 1;
                    p.lost_status = data.lost_status || 'pending_payment';
                    p.lost_reason = reason;
                    p.lost_selling_price = data.selling_price;
                    p.lost_user_paid = data.user_paid;
                    p.lost_overdue_fee = data.overdue_fee;
                    p.lost_total_due = data.total_due;
                    if (data.proof_path) {
                        p.lost_payment_proof = data.proof_path;
                    }

                    closeLostItemModal();

                    const overlay = document.getElementById('purchaseDetailOverlay');
                    if (overlay && (overlay.style.display === 'flex' || overlay.style.display === 'block')) {
                        showPurchaseDetail(currentLostIndex);
                    }

                    setTimeout(() => {
                        window.location.href = 'purchase.php?tab=lost';
                    }, 1400);
                } else {
                    showToast('Error', data.message || 'Could not submit lost item request.');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = origText;
                showToast('Error', 'Connection failed. Please try again.');
            });
        }

        // Function to filter purchases based on search input in real time
        function filterPurchases() {
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) return;
            const query = searchInput.value.trim().toLowerCase();

            document.querySelectorAll('.purchase-panel').forEach(panel => {
                const cards = panel.querySelectorAll('.purchase-card');
                let visibleCount = 0;
                let noResultsEl = panel.querySelector('.no-search-results');
                const defaultEmptyState = panel.querySelector('.empty-state:not(.no-search-results)');

                cards.forEach(card => {
                    const text = card.textContent.toLowerCase();
                    if (!query || text.includes(query)) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (cards.length > 0) {
                    if (visibleCount === 0 && query !== '') {
                        if (defaultEmptyState) defaultEmptyState.style.display = 'none';
                        if (!noResultsEl) {
                            noResultsEl = document.createElement('div');
                            noResultsEl.className = 'empty-state no-search-results';
                            noResultsEl.innerHTML = '<dotlottie-wc src="https://lottie.host/7a9e1682-3342-49d1-a823-511f6b3f1fdc/x7SG6ZMpXp.lottie" style="width: 280px; height: 280px; max-width: 90vw;" autoplay loop></dotlottie-wc><p>No purchases match "<strong>' + query.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</strong>"</p>';
                            panel.appendChild(noResultsEl);
                        } else {
                            noResultsEl.innerHTML = '<dotlottie-wc src="https://lottie.host/7a9e1682-3342-49d1-a823-511f6b3f1fdc/x7SG6ZMpXp.lottie" style="width: 280px; height: 280px; max-width: 90vw;" autoplay loop></dotlottie-wc><p>No purchases match "<strong>' + query.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</strong>"</p>';
                            noResultsEl.style.display = 'flex';
                        }
                    } else {
                        if (noResultsEl) noResultsEl.style.display = 'none';
                    }
                }
            });
        }

        function goBack() {
            window.history.back();
        }
        function setActiveNav(el) {
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            el.classList.add('active');
        }
        function showComingSoon(name) {
            alert(name + ' coming soon!');
        }

        function buyAgain(productId) {
            if (productId > 0) {
                window.location.href = 'index.php?id=' + productId;
            }
        }

        function switchTab(tabName, tabIndex) {
            if (tabIndex === currentTabIndex) return;
            const direction = tabIndex > currentTabIndex ? 'right' : 'left';
            const track = document.getElementById('panelsTrack');
            const isDesktop = window.innerWidth >= 769;

            // Update active tab styling
            document.querySelectorAll('.purchase-tab').forEach(t => t.classList.remove('active'));
            const activeTab = document.querySelector('.purchase-tab[data-tab="' + tabName + '"]');
            if (activeTab) {
                activeTab.classList.add('active');
                if (!isDesktop) {
                    activeTab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            }
            
            // In desktop view: show active panel without slide animation
            if (isDesktop) {
                if (track) track.style.transform = 'none';
                document.querySelectorAll('.purchase-panel').forEach(p => {
                    p.classList.remove('active-desktop', 'slide-in-right', 'slide-in-left');
                });
                const targetPanel = document.getElementById('panel-' + tabName);
                if (targetPanel) {
                    targetPanel.classList.add('active-desktop');
                }
            } else {
                // In mobile view: slide the track
                if (track) track.style.transform = 'translateX(-' + (tabIndex * 100) + '%)';
                const targetPanel = document.getElementById('panel-' + tabName);
                if (targetPanel) {
                    targetPanel.classList.remove('slide-in-right', 'slide-in-left');
                    void targetPanel.offsetWidth; // force reflow
                    targetPanel.classList.add(direction === 'right' ? 'slide-in-right' : 'slide-in-left');
                }
            }
            
            currentTabIndex = tabIndex;
            
            // Update URL without reload
            const newUrl = window.location.pathname + '?tab=' + tabName;
            window.history.replaceState({tab: tabName}, '', newUrl);
        }

        // Initialize active panel for desktop on load and handle resize
        function applyDesktopTabState() {
            const isDesktop = window.innerWidth >= 769;
            const track = document.getElementById('panelsTrack');
            const currentTabName = validTabs[currentTabIndex] || 'all';

            if (isDesktop) {
                if (track) track.style.transform = 'none';
                document.querySelectorAll('.purchase-panel').forEach(p => {
                    p.classList.remove('active-desktop', 'slide-in-right', 'slide-in-left');
                });
                const currentPanel = document.getElementById('panel-' + currentTabName);
                if (currentPanel) currentPanel.classList.add('active-desktop');
            } else {
                document.querySelectorAll('.purchase-panel').forEach(p => {
                    p.classList.remove('active-desktop');
                });
                if (track) track.style.transform = 'translateX(-' + (currentTabIndex * 100) + '%)';
            }
        }
        window.addEventListener('DOMContentLoaded', applyDesktopTabState);
        window.addEventListener('resize', applyDesktopTabState);

        // Touch swipe support
        let touchStartX = 0;
        let touchEndX = 0;
        const wrapper = document.querySelector('.purchase-panels-wrapper');
        if (wrapper) {
            wrapper.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            }, {passive: true});
            wrapper.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            }, {passive: true});
        }
        function handleSwipe() {
            const swipeThreshold = 50;
            const diff = touchEndX - touchStartX;
            if (Math.abs(diff) < swipeThreshold) return;
            if (diff < 0 && currentTabIndex < validTabs.length - 1) {
                // Swipe left → next tab
                switchTab(validTabs[currentTabIndex + 1], currentTabIndex + 1);
            } else if (diff > 0 && currentTabIndex > 0) {
                // Swipe right → previous tab
                switchTab(validTabs[currentTabIndex - 1], currentTabIndex - 1);
            }
        }

        // ===== Viewed Purchases & Tab Badge Tracking =====
        function getViewedPurchases() {
            try {
                return JSON.parse(localStorage.getItem('viewedPurchases') || '{}');
            } catch(e) {
                return {};
            }
        }

        function markPurchaseAsViewed(purchaseId, currentStatus) {
            if (!purchaseId) return;
            const viewed = getViewedPurchases();
            viewed[purchaseId] = currentStatus || 'pending';
            localStorage.setItem('viewedPurchases', JSON.stringify(viewed));
            updateTabBadges();
        }

        function updateTabBadges() {
            const viewed = getViewedPurchases();
            
            // Total counts for All and Pending (always static, no decrement on click)
            const totalAllCount = purchasesData.length;
            const totalPendingCount = purchasesData.filter(p => (p.order_status || 'pending') === 'pending').length;

            // Unviewed counts for Confirmed, Completed, Cancelled (decrements on click)
            const unviewedCounts = {
                confirmed: 0,
                completed: 0,
                cancelled: 0
            };

            purchasesData.forEach(p => {
                const status = p.order_status || 'pending';
                const pId = p.id;
                const isViewed = viewed[pId] === status;
                if (!isViewed && unviewedCounts[status] !== undefined) {
                    unviewedCounts[status]++;
                }
            });

            // Update All badge: (number of product), no decrement
            const allBadge = document.getElementById('badge-all');
            if (allBadge) {
                if (totalAllCount > 0) {
                    allBadge.className = 'tab-count';
                    allBadge.textContent = `(${totalAllCount})`;
                    allBadge.style.display = 'inline-block';
                } else {
                    allBadge.style.display = 'none';
                }
            }

            // Update Pending badge: (number of product), no decrement
            const pendingBadge = document.getElementById('badge-pending');
            if (pendingBadge) {
                if (totalPendingCount > 0) {
                    pendingBadge.className = 'tab-count';
                    pendingBadge.textContent = `(${totalPendingCount})`;
                    pendingBadge.style.display = 'inline-block';
                } else {
                    pendingBadge.style.display = 'none';
                }
            }

            // Update Confirmed, Completed, Cancelled: red badge, decrements on click
            ['confirmed', 'completed', 'cancelled'].forEach(tabKey => {
                const badge = document.getElementById(`badge-${tabKey}`);
                if (badge) {
                    const count = unviewedCounts[tabKey] || 0;
                    if (count > 0) {
                        badge.className = 'tab-badge badge-red';
                        badge.textContent = count;
                        badge.style.display = 'inline-flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            });

            // Update Report Lost badge: count of reported lost items
            const lostBadge = document.getElementById('badge-lost');
            if (lostBadge) {
                const totalLostCount = purchasesData.filter(p => parseInt(p.is_lost || 0) === 1).length;
                if (totalLostCount > 0) {
                    lostBadge.className = 'tab-count';
                    lostBadge.textContent = `(${totalLostCount})`;
                    lostBadge.style.display = 'inline-block';
                } else {
                    lostBadge.style.display = 'none';
                }
            }

            // Update bottom nav Purchases button badge and laptop topbar badge (sum of unviewed confirmed, completed, cancelled)
            const navBadge = document.getElementById('purchaseBadge');
            const topBadge = document.getElementById('topbarPurchaseBadge');
            const totalUnviewed = (unviewedCounts.confirmed || 0) + (unviewedCounts.completed || 0) + (unviewedCounts.cancelled || 0);

            [navBadge, topBadge].forEach(badge => {
                if (badge) {
                    if (totalUnviewed > 0) {
                        badge.textContent = totalUnviewed;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            });

            // Update purchase card visual state (unopened / transparent)
            document.querySelectorAll('.purchase-card').forEach(card => {
                const pId = card.getAttribute('data-purchase-id');
                const status = card.getAttribute('data-status') || 'pending';
                if (['confirmed', 'completed', 'cancelled'].includes(status) && viewed[pId] !== status) {
                    card.classList.add('unopened');
                } else {
                    card.classList.remove('unopened');
                }
            });
        }

        // Set initial track position on page load and update badges
        (function() {
            const track = document.getElementById('panelsTrack');
            if (track && currentTabIndex > 0) {
                track.style.transform = 'translateX(-' + (currentTabIndex * 100) + '%)';
            }
            updateTabBadges();
        })();



        function showPurchaseDetail(index) {
            const p = purchasesData[index];
            if (!p) return;

            // Mark this purchase as viewed in its current status (decrements tab badge)
            markPurchaseAsViewed(p.id, p.order_status || 'pending');
            if (Array.isArray(p.products)) {
                p.products.forEach(prod => {
                    if (prod.id) markPurchaseAsViewed(prod.id, p.order_status || 'pending');
                });
            }

            const body = document.getElementById('purchaseDetailBody');
            const status = p.order_status || 'pending';
            const statusClass = 'status-' + status;
            const orderProducts = p.products || [];
            const isMulti = orderProducts.length > 1;
            const totalPrice = parseFloat(p.total_price || 0);
            const reserveDate = p.reserve_date || '';
            const returnDate = p.return_date || '';
            const paymentOption = p.payment_option || 'full';
            const downpayment = parseFloat(p.downpayment_amount || 0);
            const balance = parseFloat(p.balance_amount || 0);
            const isLost = parseInt(p.is_lost || 0) === 1;
            const lostStatus = p.lost_status || 'reported';
            const isSettled = lostStatus === 'settled';
            const resCode = p.reservation_code || ('CG-' + p.id);

            // Update Modal Header based on Lost Item vs Purchase Details
            const titleEl = document.getElementById('purchaseDetailTitle');
            const subTitleEl = document.getElementById('purchaseDetailSubTitle');
            const iconEl = document.getElementById('purchaseDetailIcon');

            if (isLost) {
                if (titleEl) titleEl.textContent = 'Lost Item';
                if (subTitleEl) subTitleEl.textContent = `Order #${p.id} • Settlement Details`;
                if (iconEl) {
                    iconEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                    iconEl.style.background = 'rgba(255, 152, 0, 0.18)';
                    iconEl.style.borderColor = 'rgba(255, 152, 0, 0.45)';
                    iconEl.style.color = '#ff9800';
                }
            } else {
                if (titleEl) titleEl.textContent = 'Purchase Details';
                if (subTitleEl) subTitleEl.textContent = `Order #${p.id} • ${resCode}`;
                if (iconEl) {
                    iconEl.innerHTML = '<i class="fas fa-file-invoice"></i>';
                    iconEl.style.background = '';
                    iconEl.style.borderColor = '';
                    iconEl.style.color = '';
                }
            }

            // If this is a Lost Item, show ONLY the Lost Item Settlement Details
            if (isLost) {
                const calc = calculateLostSettlement(p);
                const lostSelling = parseFloat(p.lost_selling_price || 0) || calc.totalSelling;
                const lostPaid = parseFloat(p.lost_user_paid || 0) || calc.userPaid;
                const lostDiff = Math.max(0, lostSelling - lostPaid);
                const lostOverdue = parseFloat(p.lost_overdue_fee || 0) || calc.overdueFee;
                const lostTotal = parseFloat(p.lost_total_due || 0) || (lostDiff + lostOverdue);

                // Build clean borrowed items list
                const renderLostProds = (orderProducts.length > 0) ? orderProducts : [p];
                let lostProductsHtml = '';
                renderLostProds.forEach(prod => {
                    const prodModel = prod.model_path || '';
                    const prodImg = prod.image_path || prod.imagePath || prod.image_path2 || prod.image_path3 || '';
                    const hasModel = prodModel && prodModel.split('.').pop().toLowerCase() === 'glb';
                    const displayImg = prodImg || (!hasModel && prodModel ? prodModel : '');
                    const pName = prod.product_name || 'Borrowed Item';
                    const pCat = prod.category || '';
                    const pQty = Math.max(1, parseInt(prod.quantity || 1));
                    const sp = parseFloat(prod.selling_price || prod.purchase_price || prod.price || 0);

                    let thumbHtml = '';
                    if (displayImg) {
                        thumbHtml = `<img src="${displayImg}" alt="${pName}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">`;
                    } else if (hasModel) {
                        thumbHtml = `<model-viewer src="${prodModel}" alt="${pName}" auto-rotate camera-controls disable-pan disable-zoom min-camera-orbit="auto 90deg auto" max-camera-orbit="auto 90deg auto" style="width:100%;height:100%;border-radius:6px;"></model-viewer>`;
                    } else {
                        thumbHtml = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#666;font-size:1.2rem;"><i class="fas fa-tshirt"></i></div>`;
                    }

                    lostProductsHtml += `
                        <div style="display: flex; gap: 12px; background: #121215; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 10px 12px; align-items: center; margin-bottom: 8px;">
                            <div style="width: 52px; height: 52px; border-radius: 6px; overflow: hidden; background: #222; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.06);">
                                ${thumbHtml}
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 13.5px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${pName}</div>
                                <div style="font-size: 11px; color: #888; margin-top: 2px;">${pCat ? pCat + ' &bull; ' : ''}Qty: ${pQty}</div>
                            </div>
                            <div style="text-align: right; flex-shrink: 0;">
                                <div style="font-size: 10.5px; color: #888;">Replacement Selling:</div>
                                <div style="font-size: 13.5px; font-weight: 700; color: #ffd700;">₱${(sp * pQty).toLocaleString(undefined, {maximumFractionDigits:0})}</div>
                            </div>
                        </div>
                    `;
                });

                // Status Pill & Verification State
                let statusPillHtml = '';
                if (isSettled) {
                    statusPillHtml = `<span class="lost-status-pill settled" style="font-size: 11.5px; padding: 5px 12px;"><i class="fas fa-check-circle"></i> Settled</span>`;
                } else if (p.lost_payment_proof) {
                    statusPillHtml = `<span class="lost-status-pill" style="font-size: 11.5px; padding: 5px 12px; background: rgba(33, 150, 243, 0.2); color: #64b5f6; border: 1px solid rgba(33, 150, 243, 0.45);"><i class="fas fa-clock"></i> Proof Submitted &bull; Under Verification</span>`;
                } else {
                    statusPillHtml = `<span class="lost-status-pill" style="font-size: 11.5px; padding: 5px 12px; background: rgba(255, 152, 0, 0.18); color: #ff9800; border: 1px solid rgba(255, 152, 0, 0.4);"><i class="fas fa-exclamation-circle"></i> Settlement Payment Pending</span>`;
                }

                // Proof of Settlement Payment Display (displays the proof uploaded in Report Lost Borrowed Item)
                let proofSectionHtml = '';
                if (p.lost_payment_proof) {
                    proofSectionHtml = `
                        <div style="margin-top: 14px; background: #121215; border: 1px solid rgba(255, 215, 0, 0.28); border-radius: 12px; padding: 14px 16px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                <div style="font-size: 13px; font-weight: 700; color: #ffd700; display: flex; align-items: center; gap: 7px;">
                                    <i class="fas fa-file-invoice-dollar"></i> Proof of Settlement Payment
                                </div>
                                <span style="font-size: 11px; padding: 3px 8px; border-radius: 4px; background: rgba(33, 150, 243, 0.2); color: #90caf9; border: 1px solid rgba(33, 150, 243, 0.4);">
                                    <i class="fas fa-check-circle"></i> Uploaded in Report Lost
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 14px;">
                                <img src="${p.lost_payment_proof}" alt="Proof of Settlement Payment" onclick="openCancellationLightbox('${p.lost_payment_proof}')" style="width: 85px; height: 85px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,215,0,0.5); cursor: pointer;" title="Tap to view full receipt">
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 12.5px; color: #eee; font-weight: 600; margin-bottom: 4px;">Payment Receipt Screenshot</div>
                                    <div style="font-size: 11px; color: #aaa; line-height: 1.4;">Attached during your Report Lost submission. Tap thumbnail to view full receipt.</div>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    proofSectionHtml = `
                        <div style="margin-top: 14px; background: #121215; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 14px 16px;">
                            <div style="font-size: 12.5px; font-weight: 700; color: #aaa; display: flex; align-items: center; gap: 7px; margin-bottom: 6px;">
                                <i class="fas fa-file-invoice-dollar"></i> Proof of Settlement Payment
                            </div>
                            <div style="font-size: 11.5px; color: #888; line-height: 1.4;">
                                No payment proof was attached when reporting this item lost.
                            </div>
                        </div>
                    `;
                }

                let settledNoticeHtml = '';
                if (isSettled) {
                    settledNoticeHtml = `
                        <div style="margin-top: 14px; padding: 12px 14px; background: rgba(76, 175, 80, 0.1); border: 1px solid rgba(76, 175, 80, 0.35); border-radius: 10px; font-size: 12px; color: #6cd488; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-check-circle" style="font-size: 16px;"></i>
                            <span>This lost item settlement was confirmed and settled${p.lost_settled_at ? ' on <strong style="color:#fff;">' + p.lost_settled_at + '</strong>' : ''}. Thank you for fulfilling the settlement.</span>
                        </div>
                    `;
                }

                // Render ONLY the Lost Item Settlement Details
                body.innerHTML = `
                    <!-- Policy & Status Header Banner -->
                    <div style="background: rgba(255, 152, 0, 0.08); border: 1px solid rgba(255, 152, 0, 0.35); border-radius: 12px; padding: 14px 16px; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                            <div style="color: #ff9800; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-exclamation-triangle"></i> Lost Item Settlement Details
                            </div>
                            ${statusPillHtml}
                        </div>
                        <div style="font-size: 12px; color: #bbb; line-height: 1.45;">
                            This borrowed item was reported lost. As per policy, settlement is calculated from item selling price less rental already paid plus overdue fees.
                        </div>
                    </div>

                    <!-- Borrowed Item(s) Summary -->
                    <div style="margin-bottom: 14px;">
                        <div style="font-size: 12.5px; font-weight: 700; color: #ffd700; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-tshirt"></i> Borrowed Item(s) (${renderLostProds.length})
                        </div>
                        ${lostProductsHtml}
                    </div>

                    <!-- Settlement Calculation Breakdown Card -->
                    <div class="lost-calc-card" style="margin-bottom: 14px;">
                        <div class="lost-calc-header">
                            <i class="fas fa-receipt" style="color: #ffd700;"></i> Settlement Calculation Breakdown
                        </div>
                        <div class="lost-calc-row">
                            <span style="color:#aaa;">Item Replacement Selling Price:</span>
                            <span style="color:#fff; font-weight:600;">₱${lostSelling.toLocaleString(undefined, {maximumFractionDigits:0})}</span>
                        </div>
                        <div class="lost-calc-row">
                            <span style="color:#aaa;">Less: Rental Paid by You:</span>
                            <span style="color:#6cd488; font-weight:600;">-₱${lostPaid.toLocaleString(undefined, {maximumFractionDigits:0})}</span>
                        </div>
                        <div class="lost-calc-row">
                            <span style="color:#aaa;">Price Difference:</span>
                            <span style="color:#fff; font-weight:600;">₱${lostDiff.toLocaleString(undefined, {maximumFractionDigits:0})}</span>
                        </div>
                        ${lostOverdue > 0 ? `
                        <div class="lost-calc-row">
                            <span style="color:#ffb74d;">Overdue Late Fee:</span>
                            <span style="color:#ffb74d; font-weight:600;">+₱${lostOverdue.toLocaleString(undefined, {maximumFractionDigits:0})}</span>
                        </div>` : ''}
                        <div class="lost-calc-row total-row">
                            <span style="color:#ffd700;">Total Settlement ${isSettled ? 'Paid' : 'Due'}:</span>
                            <span style="color:#ffd700; font-size:17px; font-weight:700;">₱${lostTotal.toLocaleString(undefined, {maximumFractionDigits:0})}</span>
                        </div>
                    </div>

                    <!-- Reason Provided -->
                    ${p.lost_reason ? `
                    <div style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); padding: 12px 14px; border-radius: 8px; font-size: 12px; color: #ddd; margin-bottom: 14px; line-height: 1.45;">
                        <div style="color: #ffb74d; font-weight: 600; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-comment-alt"></i> Reason Provided:
                        </div>
                        <div style="white-space: pre-line; color: #eee;">${p.lost_reason}</div>
                    </div>` : ''}

                    <!-- Proof of Settlement Payment Display (from Report Lost) -->
                    ${proofSectionHtml}

                    <!-- Settled Notice -->
                    ${settledNoticeHtml}
                `;
            } else {
                // Standard Purchase Details (for active reservations & completed purchases)
                let cancelSectionHtml = '';
                if (status === 'cancelled') {
                    let cancelImgsHtml = '';
                    try {
                        const imgs = JSON.parse(p.cancellation_images || '[]');
                        if (Array.isArray(imgs) && imgs.length > 0) {
                            cancelImgsHtml = `
                                <div style="margin-top: 10px;">
                                    <div style="font-size: 11px; color: #aaa; margin-bottom: 6px; font-weight: 500;">Attached Problem Photos (Tap to enlarge):</div>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        ${imgs.map(img => `<img src="${img}" alt="Cancellation Photo" onclick="openCancellationLightbox('${img}')" style="width: 65px; height: 65px; object-fit: cover; border-radius: 6px; border: 1px solid rgba(244,67,54,0.5); cursor: pointer;" title="Tap to view full image">`).join('')}
                                    </div>
                                </div>
                            `;
                        }
                    } catch(e) {}

                    cancelSectionHtml = `
                        <div style="background: rgba(244, 67, 54, 0.08); border: 1px solid rgba(244, 67, 54, 0.35); border-radius: 10px; padding: 14px; margin-bottom: 18px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <div style="color: #ff5252; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px;">
                                    <i class="fas fa-exclamation-circle"></i> Explanation from Store Admin
                                </div>
                            </div>
                            <div style="color: #e0e0e0; font-size: 12.5px; line-height: 1.5; white-space: pre-line; background: rgba(0,0,0,0.25); border-radius: 6px; padding: 10px; border-left: 3px solid #f44336;">${p.cancellation_reason || 'This reservation was cancelled by the store administrator.'}</div>
                            ${cancelImgsHtml}
                        </div>
                    `;
                }

                // Build products list HTML
                let productsHtml = '';
                orderProducts.forEach((prod, prodIdx) => {
                    const prodModel = prod.model_path || '';
                    const prodImg = prod.image_path || prod.imagePath || prod.image_path2 || prod.image_path3 || '';
                    const hasModel = prodModel && prodModel.split('.').pop().toLowerCase() === 'glb';
                    const displayImg = prodImg || (!hasModel && prodModel ? prodModel : '');
                    const qty = Math.max(1, parseInt(prod.quantity || 1));
                    const unitPrice = parseFloat(prod.price || 0);
                    const lineTotal = parseFloat(prod.total_price || (unitPrice * qty));

                    let modelHtml = '';
                    if (displayImg) {
                        modelHtml = `<img src="${displayImg}" alt="${prod.product_name}" style="width:100%;height:100%;object-fit:cover;object-position:top;border-radius:8px;display:block;">`;
                    } else if (hasModel) {
                        modelHtml = `<model-viewer src="${prodModel}" alt="${prod.product_name}" auto-rotate camera-controls disable-pan disable-zoom min-camera-orbit="auto 90deg auto" max-camera-orbit="auto 90deg auto" style="width:100%;height:100%;border-radius:8px;"></model-viewer>`;
                    } else {
                        modelHtml = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#555;font-size:1.8rem;"><i class="fas fa-image"></i></div>`;
                    }

                    // Build measurements
                    const sizeLabels = (Array.isArray(prod.size_labels) && prod.size_labels.length)
                        ? prod.size_labels
                        : [{index:1,label:'Chest/Bust'},{index:2,label:'Waist'},{index:3,label:'Hips'},{index:4,label:'Shoulder'}];

                    const items = [];
                    items.push({ item_number: 1, sizes: prod });
                    if (Array.isArray(prod.items)) {
                        prod.items.forEach(it => {
                            items.push({ item_number: parseInt(it.item_number), sizes: it });
                        });
                    }

                    let itemsHtml = `<div class="detail-items-title" style="margin-top: 10px; font-size: 13px;">Item Measurements (${items.length})</div>`;
                    items.forEach(item => {
                        let fieldsHtml = sizeLabels.map(sl => {
                            const val = item.sizes['size_' + sl.index] || '';
                            return `<div class="detail-size-field"><label>${sl.label}</label><div class="size-value${val ? '' : ' empty'}">${val || '-'}</div></div>`;
                        }).join('');
                        itemsHtml += `
                            <div class="detail-item-group">
                                <div class="detail-item-header">Item ${item.item_number}</div>
                                <div class="detail-sizes-grid">${fieldsHtml}</div>
                            </div>`;
                    });

                    // Review & Buy actions for each product in completed/cancelled
                    let prodActionsHtml = '';
                    if (status === 'completed') {
                        const hasReview = !!prod.has_review || !!p.has_review;
                        const userRating = parseInt(prod.user_rating || p.user_rating || 5);
                        let reviewPart = '';
                        if (hasReview) {
                            let starsHtml = '';
                            for (let s = 1; s <= 5; s++) {
                                starsHtml += `<span class="card-star ${s <= userRating ? 'rated' : 'unrated'}">&#9733;</span>`;
                            }
                            reviewPart = `
                                <div class="card-review-row reviewed">
                                    <span class="review-label">Reviewed</span>
                                    <div class="card-stars">${starsHtml}</div>
                                </div>
                            `;
                        } else {
                            reviewPart = `
                                <button class="review-btn" onclick="openReviewOverlay(${index}, 0, 5)" style="background: linear-gradient(135deg, #7c6300, #ffd700); color: #000; border: none; border-radius: 6px; padding: 6px 14px; font-weight: 700; font-size: 12px; cursor: pointer;">Review</button>
                            `;
                        }

                        prodActionsHtml = `
                            <div class="detail-product-actions">
                                <button class="buy-again-btn" onclick="buyAgain(${prod.product_id || 0})" style="padding: 6px 14px; font-size: 12px;">Buy Again</button>
                                ${reviewPart}
                            </div>
                        `;
                    } else if (status === 'cancelled') {
                        prodActionsHtml = `
                            <div class="detail-product-actions">
                                <button class="buy-again-btn" onclick="buyAgain(${prod.product_id || 0})" style="padding: 6px 14px; font-size: 12px;">Buy Again</button>
                            </div>
                        `;
                    }

                    const isBuyProd = (prod.product_status === 'buy' || p.product_status === 'buy' || p.payment_method === 'buy');
                    const prodReserve = prod.reserve_date || p.reserve_date || '';
                    const prodReturn = prod.return_date || p.return_date || '';
                    const isOrderRescheduled = parseInt(p.is_rescheduled || 0) === 1;

                    // 3-day notice rule on existing reservation: cannot reschedule if reserve date is in the past or <= 3 days from today
                    const todayStr = formatLocalDate(new Date());
                    const cutoffNoticeObj = new Date();
                    cutoffNoticeObj.setDate(cutoffNoticeObj.getDate() + 3);
                    const cutoffNoticeDate = formatLocalDate(cutoffNoticeObj);

                    const targetReserveDate = prodReserve || p.reserve_date || '';
                    const isPastReserve = targetReserveDate && (targetReserveDate < todayStr);
                    const isWithin3DaysReserve = targetReserveDate && (targetReserveDate <= cutoffNoticeDate);
                    const isReserveEligible = targetReserveDate && !isPastReserve && !isWithin3DaysReserve;

                    let rescheduleHeaderBadge = '';
                    if (isOrderRescheduled) {
                        rescheduleHeaderBadge = `
                            <span class="reschedule-badge" title="Rescheduled once (limit reached)">
                                <i class="fas fa-check-circle"></i> Rescheduled
                            </span>
                        `;
                    } else if (!isBuyProd && (status === 'pending' || status === 'confirmed')) {
                        if (isReserveEligible) {
                            rescheduleHeaderBadge = `
                                <button type="button" class="reschedule-btn" onclick="openRescheduleModal(${index})" style="padding: 4px 12px; font-size: 11px;">
                                    <i class="far fa-calendar-alt"></i> Reschedule
                                </button>
                            `;
                        } else {
                            const closedReason = isPastReserve ? 'Past date' : '&le; 3 days';
                            const closedTitle = isPastReserve 
                                ? 'Cannot reschedule: reservation date has already passed' 
                                : 'Rescheduling is closed within 3 days of reserve date (requires > 3 days advance notice)';
                            rescheduleHeaderBadge = `
                                <span class="reschedule-badge disabled" style="background: rgba(255,255,255,0.05); color: #888; border: 1px solid rgba(255,255,255,0.12); padding: 3px 8px; border-radius: 4px; font-size: 10px;" title="${closedTitle}">
                                    <i class="fas fa-ban"></i> Reschedule Closed (${closedReason})
                                </span>
                            `;
                        }
                    }

                    let originalDatesRow = '';
                    if (isOrderRescheduled && (p.original_reserve_date || p.original_return_date)) {
                        originalDatesRow = `
                            <div style="margin-top: 6px; font-size: 10.5px; color: #888; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-history" style="color: #64b5f6;"></i>
                                <span>1st Reserve Date: <strong style="color: #bbb;">${p.original_reserve_date || '-'}</strong> ${p.original_return_date ? `&bull; Return: <strong style="color: #bbb;">${p.original_return_date}</strong>` : ''}</span>
                            </div>
                        `;
                    }

                    const prodScheduleHtml = `
                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #333;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                <div style="font-size: 11px; font-weight: 600; color: #ffd700;">
                                    ${isBuyProd ? '<i class="fas fa-shopping-bag"></i> Buy / Purchase Details:' : '<i class="far fa-calendar-alt"></i> Product Rental Period:'}
                                </div>
                                ${rescheduleHeaderBadge}
                            </div>
                            <div style="display: grid; grid-template-columns: ${isBuyProd ? '1fr' : '1fr 1fr'}; gap: 8px; background: rgba(0,0,0,0.25); padding: 8px 10px; border-radius: 6px;">
                                <div>
                                    <span style="display: block; font-size: 10px; color: #888;">${isBuyProd ? 'Pick-up / Order Date' : 'Reserve Date'}</span>
                                    <span style="font-size: 12px; color: #fff; font-weight: 600;">${prodReserve || '-'}</span>
                                </div>
                                ${!isBuyProd && prodReturn ? `
                                <div>
                                    <span style="display: block; font-size: 10px; color: #888;">Return Date</span>
                                    <span style="font-size: 12px; color: #fff; font-weight: 600;">${prodReturn || '-'}</span>
                                </div>` : ''}
                            </div>
                            ${originalDatesRow}
                        </div>
                    `;

                    productsHtml += `
                        <div class="detail-product-card">
                            <div class="detail-product-card-header">
                                <div class="detail-product-card-thumb">${modelHtml}</div>
                                <div class="detail-product-card-info">
                                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 6px;">
                                        <div class="detail-product-card-name">${prod.product_name}</div>
                                        <span class="detail-prod-type-badge ${isBuyProd ? 'badge-buy' : 'badge-reserve'}">
                                            ${isBuyProd ? '<i class="fas fa-shopping-bag"></i> Buy' : '<i class="far fa-calendar-alt"></i> Reserve'}
                                        </span>
                                    </div>
                                    <div class="detail-product-card-cat">${prod.category || ''}</div>
                                    <div class="detail-product-card-price">
                                        ₱${lineTotal.toLocaleString(undefined, {maximumFractionDigits:0})}
                                        ${qty > 1 ? `<span class="unit-breakdown" style="color:#888; font-size:12px; font-weight:400; margin-left:4px;">(₱${unitPrice.toLocaleString(undefined, {maximumFractionDigits:0})} × ${qty})</span>` : ''}
                                    </div>
                                </div>
                            </div>
                            ${itemsHtml}
                            ${prodScheduleHtml}
                            ${prodActionsHtml}
                        </div>
                    `;
                });

                const isBuyOrder = (p.product_status === 'buy' || p.payment_method === 'buy' || (Array.isArray(orderProducts) && orderProducts.length > 0 && orderProducts.every(op => op.product_status === 'buy')));
                const isOrderRescheduled = parseInt(p.is_rescheduled || 0) === 1;
                const canReportLost = !isBuyOrder && (status === 'confirmed');

                let reportLostActionBtnHtml = '';
                if (canReportLost) {
                    reportLostActionBtnHtml = `
                        <div style="margin: 0 0 16px 0;">
                            <button type="button" class="report-lost-btn" onclick="openLostItemModal(${index})" style="width: 100%; justify-content: center; padding: 12px 16px; font-size: 13.5px; font-weight: 700; border-radius: 8px; background: linear-gradient(135deg, rgba(255,152,0,0.22), rgba(245,124,0,0.32)); border: 1.5px solid #ff9800; color: #ffd700; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(255,152,0,0.25); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 6px 18px rgba(255,152,0,0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 14px rgba(255,152,0,0.25)';">
                                <i class="fas fa-exclamation-triangle" style="font-size: 15px; color: #ffb74d;"></i> Report Borrowed Item Lost 
                            </button>
                        </div>
                    `;
                }

                body.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: space-between; margin: 0 0 12px 0;">
                        <div style="font-size: 14px; font-weight: 700; color: #ffd700;">
                            Order Items (${orderProducts.length})
                        </div>
                        <span class="status-badge ${statusClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>
                    </div>

                    ${productsHtml}

                    <!-- Compact Reference & QR Code Action Button -->
                    <div style="margin: 14px 0 16px 0;">
                        <button type="button" class="detail-qr-btn" onclick="showPurchaseQR('${resCode}')" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 11px 16px; background: #222226; border: 1px solid rgba(255,215,0,0.35); border-radius: 10px; color: #fff; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(0,0,0,0.25);">
                            <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                                <div style="width: 34px; height: 34px; border-radius: 8px; background: rgba(255,215,0,0.15); border: 1px solid rgba(255,215,0,0.4); display: flex; align-items: center; justify-content: center; color: #ffd700; font-size: 15px; flex-shrink: 0;">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <div style="text-align: left; min-width: 0;">
                                    <div style="font-size: 11px; color: #888; line-height: 1.2;">Reference Code</div>
                                    <div style="font-size: 13.5px; font-weight: 700; color: #ffd700; font-family: monospace; letter-spacing: 0.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${resCode}</div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #ffd700; background: rgba(255,215,0,0.12); padding: 6px 14px; border-radius: 6px; border: 1px solid rgba(255,215,0,0.25); flex-shrink: 0;">
                                <i class="fas fa-expand"></i> View QR
                            </div>
                        </button>
                    </div>

                    ${reportLostActionBtnHtml}

                    <!-- Order Summary Card -->
                    <div class="detail-summary-card">
                        <div class="detail-summary-row" style="padding-bottom: 8px; border-bottom: 1px solid #333; margin-bottom: 10px;">
                            <span class="detail-summary-label" style="font-weight: 700; color: #ffd700;">Order Summary</span>
                            <span style="color: #888; font-size: 12px;">#${p.id}</span>
                        </div>
                        <div class="detail-summary-row">
                            <span class="detail-summary-label">${isBuyOrder ? 'Pick-up Date:' : 'Reserve Date:'}</span>
                            <span class="detail-summary-val">${reserveDate || '-'} ${isOrderRescheduled ? '<span style="color:#64b5f6; font-size:11px; margin-left:4px; font-weight:600;">(Rescheduled)</span>' : ''}</span>
                        </div>
                        ${!isBuyOrder && returnDate ? `
                        <div class="detail-summary-row">
                            <span class="detail-summary-label">Return Date:</span>
                            <span class="detail-summary-val">${returnDate}</span>
                        </div>` : ''}
                        ${isOrderRescheduled && p.original_reserve_date ? `
                        <div class="detail-summary-row" style="font-size: 11px; color: #888;">
                            <span class="detail-summary-label">1st Reserve Period:</span>
                            <span class="detail-summary-val" style="color: #bbb;">${p.original_reserve_date} &bull; ${p.original_return_date || '-'}</span>
                        </div>` : ''}
                        <div class="detail-summary-row">
                            <span class="detail-summary-label">Payment Option:</span>
                            <span class="detail-summary-val" style="color: ${paymentOption === 'first_payment' ? '#ffd700' : '#6cd488'};">
                                ${paymentOption === 'first_payment' ? 'First Payment (₱500 Deposit)' : 'Full Payment (100%)'}
                            </span>
                        </div>
                        <div class="detail-summary-row">
                            <span class="detail-summary-label">Payment Status:</span>
                            <span class="detail-summary-val" style="color: #6cd488;">QR Ph RCBC</span>
                        </div>
                        ${paymentOption === 'first_payment' ? `
                            <div class="detail-summary-row">
                                <span class="detail-summary-label">Total Amount:</span>
                                <span class="detail-summary-val" style="color: #fff;">₱${totalPrice.toLocaleString(undefined, {maximumFractionDigits:0})}</span>
                            </div>
                            <div class="detail-summary-row">
                                <span class="detail-summary-label">Deposit Paid:</span>
                                <span class="detail-summary-val" style="color: #6cd488;">₱${downpayment.toLocaleString(undefined, {maximumFractionDigits:0})}</span>
                            </div>
                            <div class="detail-summary-row" style="padding-top: 8px; border-top: 1px solid #333; margin-top: 8px;">
                                <span class="detail-summary-label" style="font-weight: 700; color: #fff;">Balance Due (on Pickup):</span>
                                <span class="detail-summary-val" style="font-size: 16px; color: #ffd700;">₱${balance.toLocaleString(undefined, {maximumFractionDigits:0})}</span>
                            </div>
                        ` : `
                            <div class="detail-summary-row" style="padding-top: 8px; border-top: 1px solid #333; margin-top: 8px;">
                                <span class="detail-summary-label" style="font-weight: 700; color: #fff;">Total Amount:</span>
                                <span class="detail-summary-val" style="font-size: 16px; color: #ffd700;">₱${totalPrice.toLocaleString(undefined, {maximumFractionDigits:0})}</span>
                            </div>
                        `}
                    </div>

                    ${cancelSectionHtml}
                `;
            }

            document.getElementById('purchaseDetailBackdrop').style.display = 'block';
            document.getElementById('purchaseDetailOverlay').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closePurchaseDetail() {
            document.getElementById('purchaseDetailOverlay').style.display = 'none';
            document.getElementById('purchaseDetailBackdrop').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // ===== Reschedule Date Picker & Calendar System (Identical to index.php) =====
        let rescheduleCurrentIndex = null;
        let rescheduleCalendarCurrentDate = new Date();
        let rescheduleUnavailableDates = [];
        let selectedNewReserveDate = null;
        let selectedNewReturnDate = null;
        let rescheduleRentalDuration = rentalDurationDaysSetting || 10;
        let rescheduleMaxExtDays = maxExtensionDaysSetting || 7;
        let rescheduleExtFeePerDay = extensionPricePerDaySetting || 100;

        function formatLocalDate(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        function formatDateNice(dateStr) {
            if (!dateStr) return '';
            const parts = dateStr.split('-');
            if (parts.length !== 3) return dateStr;
            const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function openRescheduleModal(index) {
            const p = purchasesData[index];
            if (!p) return;

            if (parseInt(p.is_rescheduled || 0) >= 1) {
                showToast('Already Rescheduled', 'This reservation can only be rescheduled once.');
                return;
            }

            const status = (p.order_status || 'pending').toLowerCase();
            if (status === 'cancelled' || status === 'completed') {
                showToast('Cannot Reschedule', `This reservation is already ${status}.`);
                return;
            }

            if (p.product_status === 'buy' || p.payment_method === 'buy') {
                showToast('Cannot Reschedule', 'Purchase orders cannot be rescheduled.');
                return;
            }

            // Enforce notice rule: cannot reschedule if reserve date is in the past or <= 3 days of today
            const todayStr = formatLocalDate(new Date());
            const cutoffNoticeObj = new Date();
            cutoffNoticeObj.setDate(cutoffNoticeObj.getDate() + 3);
            const cutoffNoticeDate = formatLocalDate(cutoffNoticeObj);
            const curReserveDate = p.reserve_date || '';

            if (!curReserveDate || curReserveDate < todayStr) {
                showToast('Cannot Reschedule', 'Cannot reschedule: your reservation date has already passed.');
                return;
            }
            if (curReserveDate <= cutoffNoticeDate) {
                showToast('Cannot Reschedule', 'Cannot reschedule: your Reserve Date is within 3 days of today. Rescheduling must be requested more than 3 days ahead.');
                return;
            }

            rescheduleCurrentIndex = index;
            selectedNewReserveDate = null;
            selectedNewReturnDate = null;

            // Compute default rental duration based on original reservation duration if set
            if (p.reserve_date && p.return_date) {
                const origStart = new Date(p.reserve_date + 'T00:00:00');
                const origEnd = new Date(p.return_date + 'T00:00:00');
                const diffDays = Math.round((origEnd - origStart) / (1000 * 60 * 60 * 24));
                if (diffDays > 0) {
                    rescheduleRentalDuration = diffDays;
                }
            } else {
                rescheduleRentalDuration = rentalDurationDaysSetting || 10;
            }

            const confirmBtn = document.getElementById('confirmRescheduleBtn');
            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.style.opacity = '0.5';
                confirmBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Reschedule';
            }

            const products = p.products || [];
            const firstProd = products[0] || {};
            const prodName = firstProd.product_name || p.product_name || 'Gown';
            const prodCat = firstProd.category || p.category || '';
            const subtitleText = products.length > 1 
                ? `${products.length} Products (${prodName} & others)` 
                : `${prodName}${prodCat ? ` &bull; ${prodCat}` : ''}`;

            const subtitleEl = document.getElementById('rescheduleProductSubtitle');
            if (subtitleEl) subtitleEl.innerHTML = subtitleText;

            const currDatesEl = document.getElementById('rescheduleCurrentDatesDisplay');
            if (currDatesEl) currDatesEl.textContent = `${p.reserve_date || 'N/A'} - ${p.return_date || 'N/A'}`;

            const newResEl = document.getElementById('rescheduleNewReserveDisplay');
            if (newResEl) newResEl.textContent = 'Please click an available date below';

            const newRetEl = document.getElementById('rescheduleNewReturnDisplay');
            if (newRetEl) newRetEl.textContent = `Auto-calculated (${rescheduleRentalDuration} Days)`;

            // Always start at present day (today / current month)
            rescheduleCalendarCurrentDate = new Date();

            const overlay = document.getElementById('calendarModalOverlay');
            if (overlay) {
                overlay.classList.add('active');
            }

            // Immediately render calendar for the present month so boxes are visible
            renderRescheduleCalendar();

            // Fetch real-time available/unavailable dates from server
            fetchRescheduleUnavailableDates(p, () => {
                renderRescheduleCalendar();
            });
        }

        function closeRescheduleModal(e) {
            if (e && e.target !== document.getElementById('calendarModalOverlay')) return;
            const overlay = document.getElementById('calendarModalOverlay');
            if (overlay) {
                overlay.classList.remove('active');
            }
            closeRescheduleConfirmModal();
            rescheduleCurrentIndex = null;
            selectedNewReserveDate = null;
            selectedNewReturnDate = null;
        }

        function changeRescheduleCalendarMonth(dir) {
            const now = new Date();
            const currentYear = now.getFullYear();
            const currentMonth = now.getMonth();
            if (dir < 0) {
                const year = rescheduleCalendarCurrentDate.getFullYear();
                const month = rescheduleCalendarCurrentDate.getMonth();
                if ((year < currentYear) || (year === currentYear && month <= currentMonth)) {
                    return;
                }
            }
            rescheduleCalendarCurrentDate.setMonth(rescheduleCalendarCurrentDate.getMonth() + dir);
            renderRescheduleCalendar();
        }

        function fetchRescheduleUnavailableDates(p, callback) {
            const products = (p.products && p.products.length > 0) ? p.products : [p];
            const orderId = p.id || 0;
            const groupId = p.reservation_group_id || '';

            const fetchPromises = products.map(prod => {
                const pid = prod.product_id || p.product_id || 0;
                const pname = prod.product_name || p.product_name || '';
                const pcat = prod.category || p.category || '';
                const qty = prod.quantity || p.quantity || 1;
                const url = `get_unavailable_dates.php?product_id=${encodeURIComponent(pid)}&product_name=${encodeURIComponent(pname)}&product_category=${encodeURIComponent(pcat)}&type=reserve&quantity=${encodeURIComponent(qty)}&exclude_reservation_id=${encodeURIComponent(orderId)}&exclude_group_id=${encodeURIComponent(groupId)}&_t=${Date.now()}`;
                return fetch(url).then(r => r.json()).catch(() => ({ success: false, dates: [] }));
            });

            Promise.all(fetchPromises)
                .then(results => {
                    const combinedDates = new Set();
                    results.forEach(data => {
                        if (data && data.success && Array.isArray(data.dates)) {
                            data.dates.forEach(d => combinedDates.add(d));
                        }
                        if (data && data.rental_duration_days && !p.reserve_date) {
                            rescheduleRentalDuration = parseInt(data.rental_duration_days);
                        }
                        if (data && data.max_extension_days) rescheduleMaxExtDays = parseInt(data.max_extension_days);
                        if (data && data.extension_price_per_day) rescheduleExtFeePerDay = parseFloat(data.extension_price_per_day);
                    });
                    rescheduleUnavailableDates = Array.from(combinedDates);
                    if (typeof callback === 'function') callback();
                })
                .catch(err => {
                    console.error('Error fetching reschedule availability:', err);
                    rescheduleUnavailableDates = [];
                    if (typeof callback === 'function') callback();
                });
        }

        function renderRescheduleCalendar() {
            const year = rescheduleCalendarCurrentDate.getFullYear();
            const month = rescheduleCalendarCurrentDate.getMonth();
            const firstDayOfMonth = new Date(year, month, 1);
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const firstDayOfWeek = firstDayOfMonth.getDay();

            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];

            const titleEl = document.getElementById('calendarModalTitle');
            if (titleEl) {
                titleEl.textContent = `${monthNames[month]} ${year}`;
            }

            // Disable prev month button if viewing present month or earlier
            const now = new Date();
            const currentYear = now.getFullYear();
            const currentMonth = now.getMonth();
            const isCurrentOrPastMonth = (year < currentYear) || (year === currentYear && month <= currentMonth);

            const prevBtn = document.getElementById('reschedulePrevMonthBtn');
            if (prevBtn) {
                if (isCurrentOrPastMonth) {
                    prevBtn.disabled = true;
                    prevBtn.style.opacity = '0.35';
                    prevBtn.style.cursor = 'not-allowed';
                    prevBtn.title = 'Cannot navigate to past months';
                } else {
                    prevBtn.disabled = false;
                    prevBtn.style.opacity = '1';
                    prevBtn.style.cursor = 'pointer';
                    prevBtn.title = 'Previous month';
                }
            }

            const grid = document.getElementById('calendarModalGrid');
            if (!grid) return;
            grid.innerHTML = '';

            const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayHeaders.forEach(day => {
                const header = document.createElement('div');
                header.className = 'calendar-day-header';
                header.textContent = day;
                grid.appendChild(header);
            });

            for (let i = 0; i < firstDayOfWeek; i++) {
                const empty = document.createElement('div');
                empty.className = 'calendar-day other-month';
                grid.appendChild(empty);
            }

            const today = formatLocalDate(new Date());
            const cutoffObj = new Date();
            cutoffObj.setDate(cutoffObj.getDate() + 3);
            const cutoffDate = formatLocalDate(cutoffObj);

            const p = purchasesData[rescheduleCurrentIndex];
            const currentOrderReserveDate = p ? p.reserve_date : '';
            const standardDays = rescheduleRentalDuration;

            // Render Legend inside Calendar Modal (Red Strikethrough and Green only)
            const guideContainer = document.getElementById('calendarModalGuide');
            if (guideContainer) {
                guideContainer.innerHTML = `
                    <div class="calendar-guide-legend" style="border-top: none; margin-top: 0; padding-top: 0; display: flex; align-items: center; justify-content: center; gap: 20px; flex-wrap: wrap;">
                        <span class="legend-chip booked"><span class="chip-box"></span> Red Strikethrough = Booked / Unavailable</span>
                        <span class="legend-chip orig-date"><span class="chip-box"></span> Green = Your 1st Reserve Date</span>
                    </div>
                `;
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dateObj = new Date(year, month, day);
                const dateStr = formatLocalDate(dateObj);
                const dayElem = document.createElement('div');
                dayElem.className = 'calendar-day';
                dayElem.textContent = day;

                const isToday = (dateStr === today);
                if (isToday) {
                    dayElem.classList.add('today');
                }

                const isBooked = rescheduleUnavailableDates.includes(dateStr);
                const isPast = (dateStr < today);
                const is1stReserveDate = (dateStr === currentOrderReserveDate);

                // Highlight 1st reserve date as available with marker if not in past and not booked
                if (is1stReserveDate && !isBooked && !isPast) {
                    dayElem.classList.add('orig-date-marker');
                    dayElem.title = 'Your 1st reserve date (Available)';
                }

                if (dateStr === selectedNewReserveDate) {
                    dayElem.classList.add('selected');
                } else if (dateStr === selectedNewReturnDate) {
                    dayElem.classList.add('reserve-anchor');
                    dayElem.title = 'New Return Date';
                }

                // Highlight rental gap between selected reserve date and return date
                if (selectedNewReserveDate && selectedNewReturnDate && !isBooked && !isPast) {
                    if (dateStr > selectedNewReserveDate && dateStr < selectedNewReturnDate) {
                        dayElem.classList.add('rental-gap');
                    }
                }

                if (isPast) {
                    dayElem.classList.add('disabled');
                    dayElem.classList.add('past-date');
                    dayElem.title = 'Past date (Cannot select)';
                    dayElem.style.pointerEvents = 'none';
                    dayElem.onclick = null;
                } else if (isBooked) {
                    dayElem.classList.add('disabled');
                    dayElem.classList.add('booked-date');
                    dayElem.title = 'Fully Booked / Unavailable (Cannot select)';
                    dayElem.style.pointerEvents = 'none';
                    dayElem.onclick = null;
                } else {
                    // Available date: present or future date (>= today) and product is available
                    dayElem.classList.remove('disabled');
                    dayElem.style.pointerEvents = 'auto';
                    dayElem.style.cursor = 'pointer';
                    dayElem.title = isToday ? `Today (${formatDateNice(dateStr)}) - Click to select` : `Available (${formatDateNice(dateStr)}) - Click to select`;
                    dayElem.onclick = function() {
                        handleRescheduleDateClick(dateStr);
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

        function handleRescheduleDateClick(dateStr) {
            const today = formatLocalDate(new Date());
            if (dateStr < today) {
                showToast('Invalid Date', 'Cannot select a past date.');
                return;
            }

            if (rescheduleUnavailableDates.includes(dateStr)) {
                showToast('Date Unavailable', 'This date is already booked by another reservation.');
                return;
            }

            const standardDays = rescheduleRentalDuration;
            let maxAvailableDays = 0;

            for (let d = 1; d <= standardDays; d++) {
                const checkObj = new Date(dateStr + 'T00:00:00');
                checkObj.setDate(checkObj.getDate() + d);
                const checkStr = formatLocalDate(checkObj);

                if (rescheduleUnavailableDates.includes(checkStr)) {
                    break;
                }
                maxAvailableDays = d;
            }

            if (maxAvailableDays < 1) {
                showToast('Date Range Unavailable', 'The product is booked on the following day. Please choose another date with available rental duration.');
                return;
            }

            selectedNewReserveDate = dateStr;

            if (maxAvailableDays >= standardDays) {
                const reserveObj = new Date(dateStr + 'T00:00:00');
                reserveObj.setDate(reserveObj.getDate() + standardDays);
                selectedNewReturnDate = formatLocalDate(reserveObj);
            } else {
                const maxReturnObj = new Date(dateStr + 'T00:00:00');
                maxReturnObj.setDate(maxReturnObj.getDate() + maxAvailableDays);
                selectedNewReturnDate = formatLocalDate(maxReturnObj);
            }

            const durationDays = Math.max(1, Math.round((new Date(selectedNewReturnDate) - new Date(selectedNewReserveDate)) / (1000 * 60 * 60 * 24)));

            const newResDisplay = document.getElementById('rescheduleNewReserveDisplay');
            if (newResDisplay) newResDisplay.textContent = formatDateNice(selectedNewReserveDate);

            const newRetDisplay = document.getElementById('rescheduleNewReturnDisplay');
            if (newRetDisplay) newRetDisplay.textContent = `${formatDateNice(selectedNewReturnDate)} (${durationDays} Days)`;

            const confirmBtn = document.getElementById('confirmRescheduleBtn');
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.style.opacity = '1';
            }

            renderRescheduleCalendar();
        }

        function submitReschedule() {
            if (rescheduleCurrentIndex === null || !selectedNewReserveDate || !selectedNewReturnDate) {
                showToast('Select a date', 'Please select an available Reserve Date on the calendar.');
                return;
            }

            const p = purchasesData[rescheduleCurrentIndex];
            if (!p) return;

            // Populate dates in the custom confirmation modal
            const resElem = document.getElementById('confirmModalReserveDate');
            const retElem = document.getElementById('confirmModalReturnDate');
            if (resElem) resElem.textContent = formatDateNice(selectedNewReserveDate);
            if (retElem) {
                const durationDays = Math.max(1, Math.round((new Date(selectedNewReturnDate) - new Date(selectedNewReserveDate)) / (1000 * 60 * 60 * 24)));
                retElem.textContent = `${formatDateNice(selectedNewReturnDate)} (${durationDays} Days)`;
            }

            const saveBtn = document.getElementById('saveRescheduleConfirmBtn');
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = 'Save';
            }
            const cancelBtn = document.getElementById('cancelRescheduleConfirmBtn');
            if (cancelBtn) cancelBtn.disabled = false;

            const modal = document.getElementById('rescheduleConfirmModal');
            if (modal) modal.classList.add('active');
        }

        function closeRescheduleConfirmModal(e) {
            if (e && e.target && e.target !== document.getElementById('rescheduleConfirmModal') && !e.target.classList.contains('confirm-btn-cancel')) {
                return;
            }
            const modal = document.getElementById('rescheduleConfirmModal');
            if (modal) modal.classList.remove('active');
        }

        function executeRescheduleSubmission() {
            if (rescheduleCurrentIndex === null || !selectedNewReserveDate || !selectedNewReturnDate) {
                return;
            }

            const p = purchasesData[rescheduleCurrentIndex];
            if (!p) return;

            const saveBtn = document.getElementById('saveRescheduleConfirmBtn');
            const cancelBtn = document.getElementById('cancelRescheduleConfirmBtn');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }
            if (cancelBtn) cancelBtn.disabled = true;

            const confirmBtn = document.getElementById('confirmRescheduleBtn');
            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rescheduling...';
            }

            const formData = new FormData();
            formData.append('order_id', p.id);
            formData.append('new_reserve_date', selectedNewReserveDate);
            formData.append('new_return_date', selectedNewReturnDate);

            fetch('reschedule_reservation.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update client purchase model
                    p.reserve_date = data.new_reserve_date;
                    p.return_date = data.new_return_date;
                    p.original_reserve_date = data.original_reserve_date;
                    p.original_return_date = data.original_return_date;
                    p.is_rescheduled = 1;

                    if (Array.isArray(p.products)) {
                        p.products.forEach(prod => {
                            prod.reserve_date = data.new_reserve_date;
                            prod.return_date = data.new_return_date;
                            prod.is_rescheduled = 1;
                            prod.original_reserve_date = data.original_reserve_date;
                            prod.original_return_date = data.original_return_date;
                        });
                    }

                    // Update corresponding card on purchases list in real time
                    const cardElem = document.querySelector(`.purchase-card[data-purchase-id="${p.id}"]`);
                    if (cardElem) {
                        const datesElem = cardElem.querySelector('.purchase-card-dates-preview, .multi-card-dates');
                        if (datesElem) {
                            datesElem.innerHTML = `
                                <i class="far fa-calendar-alt" style="margin-right: 4px;"></i>
                                ${data.new_reserve_date} &bull; ${data.new_return_date}
                                <span class="reschedule-mini-tag" style="background: rgba(33, 150, 243, 0.2); color: #64b5f6; border: 1px solid rgba(33, 150, 243, 0.4); border-radius: 4px; padding: 1px 6px; font-size: 10px; margin-left: 6px; font-weight: 600;"><i class="fas fa-check-circle"></i> Rescheduled</span>
                            `;
                        }
                        const actionsElem = cardElem.querySelector('.card-actions-bottom, .multi-card-actions-row');
                        if (actionsElem) {
                            const resBtn = actionsElem.querySelector('.reschedule-btn');
                            if (resBtn) {
                                resBtn.outerHTML = `<span class="reschedule-badge" title="Rescheduled once (limit reached)"><i class="fas fa-history"></i> Rescheduled</span>`;
                            }
                        }
                    }

                    // Re-render purchase detail modal if open
                    const detailOverlay = document.getElementById('purchaseDetailOverlay');
                    if (detailOverlay && (detailOverlay.style.display === 'flex' || detailOverlay.style.display === 'block')) {
                        showPurchaseDetail(rescheduleCurrentIndex);
                    }

                    closeRescheduleConfirmModal();
                    closeRescheduleModal();
                    showToast('Rescheduled Successfully!', `New period: ${data.new_reserve_date} to ${data.new_return_date}`);
                } else {
                    closeRescheduleConfirmModal();
                    showToast('Reschedule Failed', data.message || 'Could not reschedule reservation.');
                    if (confirmBtn) {
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Reschedule';
                    }
                }
            })
            .catch(err => {
                console.error('Error submitting reschedule:', err);
                closeRescheduleConfirmModal();
                showToast('Error', 'Network or server error while rescheduling.');
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Reschedule';
                }
            });
        }

        // Close confirmation modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const confirmModal = document.getElementById('rescheduleConfirmModal');
                if (confirmModal && confirmModal.classList.contains('active')) {
                    closeRescheduleConfirmModal();
                }
            }
        });

        // ===== Cancellation Reason Overlay & Lightbox Handlers =====
        function showCancellationReason(index) {
            const p = purchasesData[index];
            if (!p) return;

            markPurchaseAsViewed(p.id, 'cancelled');

            const body = document.getElementById('cancellationBody');
            let cancelImgsHtml = '';
            try {
                const imgs = JSON.parse(p.cancellation_images || '[]');
                if (Array.isArray(imgs) && imgs.length > 0) {
                    cancelImgsHtml = `
                        <div style="margin-top: 18px;">
                            <div style="font-size: 12px; font-weight: 600; color: #ffd700; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
                                <i class="fas fa-images"></i> Attached Problem Photos (${imgs.length})
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 10px;">
                                ${imgs.map(img => `
                                    <div style="position: relative; padding-bottom: 100%; border-radius: 8px; overflow: hidden; border: 1px solid rgba(244,67,54,0.4); cursor: pointer; background: #111;" onclick="openCancellationLightbox('${img}')">
                                        <img src="${img}" alt="Cancellation Image" style="position: absolute; top:0; left:0; width: 100%; height: 100%; object-fit: cover;" title="Tap to view full image">
                                    </div>
                                `).join('')}
                            </div>
                            <div style="font-size: 11px; color: #888; margin-top: 6px;">Tap any photo to view full size</div>
                        </div>`;
                }
            } catch(e) {}

            const orderProducts = p.products || [];
            let productsPreviewHtml = '';
            orderProducts.forEach(prod => {
                const modelPath = prod.model_path || '';
                const imagePath = prod.image_path || prod.imagePath || prod.image_path2 || prod.image_path3 || '';
                const displayImage = imagePath || modelPath || '';
                productsPreviewHtml += `
                    <div style="display: flex; align-items: center; gap: 10px; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.06);">
                        ${displayImage ? `<img src="${displayImage}" style="width: 44px; height: 44px; border-radius: 6px; object-fit: cover;">` : `<div style="width: 44px; height: 44px; border-radius: 6px; background: #1a1a1a; display: flex; align-items: center; justify-content: center;"><i class="fas fa-tshirt" style="color: #666;"></i></div>`}
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 13px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${prod.product_name}</div>
                            <div style="font-size: 11px; color: #888;">${prod.category || ''} &bull; ₱${parseFloat(prod.total_price || 0).toLocaleString()}</div>
                        </div>
                    </div>
                `;
            });

            body.innerHTML = `
                <div style="background: #2a2a2a; border-radius: 12px; padding: 15px; border: 1px solid #333; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 13px; font-weight: 700; color: #ffd700;">Reservation #${p.id}</span>
                        <span class="status-badge status-cancelled" style="font-size: 10px; padding: 2px 8px;">Cancelled</span>
                    </div>
                    <div style="font-size: 11px; color: #888; margin-bottom: 10px;">Reserved Dates: ${p.reserve_date || 'N/A'} - ${p.return_date || 'N/A'}</div>
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        ${productsPreviewHtml}
                    </div>
                </div>

                <div style="background: rgba(244, 67, 54, 0.08); border: 1px solid rgba(244, 67, 54, 0.35); border-radius: 12px; padding: 18px;">
                    <div style="color: #ff5252; font-size: 13px; font-weight: 600; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-info-circle"></i> Explanation from Store Admin
                    </div>
                    <div style="color: #f0f0f0; font-size: 13px; line-height: 1.6; background: rgba(0,0,0,0.3); border-radius: 8px; padding: 14px; border-left: 3px solid #f44336; white-space: pre-line;">
                        ${p.cancellation_reason ? p.cancellation_reason : 'No detailed explanation was provided by the administrator. Please contact store support for more details.'}
                    </div>
                    ${cancelImgsHtml}
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button onclick="window.location.href='index.php'" class="buy-again-btn" style="flex: 1; padding: 12px; font-size: 13px; border-radius: 8px;">Browse Other Items</button>
                    <button onclick="closeCancellationReason()" style="padding: 12px 20px; background: #333; color: #ccc; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">Close</button>
                </div>
            `;

            document.body.classList.add('slide-panel-open');
            document.getElementById('cancellationOverlay').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeCancellationReason() {
            document.body.classList.remove('slide-panel-open');
            document.getElementById('cancellationOverlay').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openCancellationLightbox(src) {
            const lightbox = document.getElementById('cancellationLightbox');
            const img = document.getElementById('cancellationLightboxImg');
            if (lightbox && img) {
                img.src = src;
                lightbox.style.display = 'flex';
            }
        }

        function closeCancellationLightbox() {
            const lightbox = document.getElementById('cancellationLightbox');
            if (lightbox) {
                lightbox.style.display = 'none';
            }
        }

        // ===== Toast Notification =====
        function showToast(message, sub) {
            const toast = document.getElementById('toastNotification');
            const msgEl = document.getElementById('toastMessage');
            const subEl = document.getElementById('toastSub');
            if (msgEl) msgEl.textContent = message;
            if (subEl) subEl.textContent = sub || '';
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 2500);
        }

        // ===== Card Star Hover Shading =====
        function hoverCardStars(starEl, count) {
            const row = starEl.closest('.card-stars');
            if (!row) return;
            const stars = row.querySelectorAll('.card-star');
            stars.forEach((s, idx) => {
                if (idx < count) {
                    s.classList.add('hover-active');
                } else {
                    s.classList.remove('hover-active');
                }
            });
        }

        function resetCardStars(starContainer) {
            const stars = starContainer.querySelectorAll('.card-star');
            stars.forEach(s => s.classList.remove('hover-active'));
        }

        // ===== Review Overlay & Sentiments =====
        let currentReviewOrderIndex = null;
        let currentReviewProductIndex = 0;
        let selectedRating = 0;
        let reviewPhotos = [null, null];

        const ratingSentiments = {
            1: 'Terrible',
            2: 'Bad',
            3: 'Okay',
            4: 'Good',
            5: 'Excellent'
        };

        const ratingPlaceholders = {
            1: 'What problem or issue about this product?',
            2: 'What did you dislike about this product?',
            3: 'What was not satisfactory with this product?',
            4: 'What did you like about this product?',
            5: 'What did you think of the item?'
        };

        function openReviewOverlay(orderIndex, productIndex = 0, initialRating = 0) {
            const order = purchasesData[orderIndex];
            if (!order || parseInt(order.is_lost || 0) === 1) return;
            const prods = order.products || [order];
            const isMulti = prods.length > 1;
            const p = prods[productIndex] || prods[0] || order;
            currentReviewOrderIndex = orderIndex;
            currentReviewProductIndex = productIndex;
            selectedRating = initialRating || 0;
            reviewPhotos = [null, null];

            const body = document.getElementById('reviewBody');
            let previewHtml = '';

            if (isMulti) {
                let thumbsHtml = '';
                prods.forEach(item => {
                    const modelPath = item.model_path || '';
                    const imagePath = item.image_path || item.imagePath || item.image_path2 || item.image_path3 || '';
                    const hasModel = modelPath && modelPath.split('.').pop().toLowerCase() === 'glb';
                    const displayImage = imagePath || (!hasModel && modelPath ? modelPath : '');
                    if (displayImage) {
                        thumbsHtml += `<img src="${displayImage}" style="width:48px;height:48px;border-radius:6px;object-fit:cover;object-position:top;flex-shrink:0;">`;
                    } else if (hasModel) {
                        thumbsHtml += `<model-viewer src="${modelPath}" style="width:48px;height:48px;border-radius:6px;flex-shrink:0;" auto-rotate camera-controls disable-pan disable-zoom></model-viewer>`;
                    } else {
                        thumbsHtml += `<div style="width:48px;height:48px;border-radius:6px;background:#1a1a1a;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-image" style="color:#555;"></i></div>`;
                    }
                });

                previewHtml = `
                    <div style="background:#242426; border-radius:10px; padding:12px; border:1px solid #383838; margin-bottom:14px;">
                        <div style="font-size:14px; font-weight:700; color:#ffd700; margin-bottom:4px;">Reservation #${order.id} (${prods.length} Products)</div>
                        <div style="font-size:12px; color:#aaa; margin-bottom:10px;">Your rating and review will apply to all reserved products in this order.</div>
                        <div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:4px;">
                            ${thumbsHtml}
                        </div>
                    </div>
                `;
            } else {
                const modelPath = p.model_path || '';
                const imagePath = p.image_path || p.imagePath || p.image_path2 || p.image_path3 || '';
                const hasModel = modelPath && modelPath.split('.').pop().toLowerCase() === 'glb';
                const displayImage = imagePath || (!hasModel && modelPath ? modelPath : '');

                let modelHtml = '';
                if (displayImage) {
                    modelHtml = `<img src="${displayImage}" style="width:60px;height:60px;border-radius:8px;object-fit:cover;object-position:top;flex-shrink:0;">`;
                } else if (hasModel) {
                    modelHtml = `<model-viewer src="${modelPath}" style="width:60px;height:60px;border-radius:8px;flex-shrink:0;" auto-rotate camera-controls disable-pan disable-zoom></model-viewer>`;
                } else {
                    modelHtml = `<div style="width:60px;height:60px;border-radius:8px;background:#1a1a1a;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-image" style="color:#555;"></i></div>`;
                }

                previewHtml = `
                    <div class="review-product-info">
                        ${modelHtml}
                        <div>
                            <div class="rpi-name">${p.product_name}</div>
                            <div class="rpi-category">${p.category || ''}</div>
                        </div>
                    </div>
                `;
            }

            body.innerHTML = `
                ${previewHtml}
                <div class="review-section-title">Rate your Item</div>
                <div class="star-rating" id="starRating">
                    <span class="star" data-value="1" onclick="setRating(1)" onmouseover="hoverRating(1)" onmouseout="resetHover()">&#9733;</span>
                    <span class="star" data-value="2" onclick="setRating(2)" onmouseover="hoverRating(2)" onmouseout="resetHover()">&#9733;</span>
                    <span class="star" data-value="3" onclick="setRating(3)" onmouseover="hoverRating(3)" onmouseout="resetHover()">&#9733;</span>
                    <span class="star" data-value="4" onclick="setRating(4)" onmouseover="hoverRating(4)" onmouseout="resetHover()">&#9733;</span>
                    <span class="star" data-value="5" onclick="setRating(5)" onmouseover="hoverRating(5)" onmouseout="resetHover()">&#9733;</span>
                </div>
                <div class="rating-sentiment" id="ratingSentiment">${ratingSentiments[selectedRating] || ''}</div>
                <div class="review-section-title">Write your review</div>
                <textarea class="review-textarea" id="reviewText" maxlength="50" oninput="updateCharCount(this)" placeholder="${ratingPlaceholders[selectedRating] || 'Share your experience with this product...'}"></textarea>
                <div class="review-char-count"><span id="charCount">0</span>/50</div>
                <div class="review-section-title">Add Photos</div>
                <div class="review-photo-upload">
                    <div class="review-photo-box" id="photoBox0" onclick="document.getElementById('reviewPhoto0').click()">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div class="review-photo-box" id="photoBox1" onclick="document.getElementById('reviewPhoto1').click()">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                <input type="file" id="reviewPhoto0" accept="image/*" style="display:none" onchange="handleReviewPhoto(this, 0)">
                <input type="file" id="reviewPhoto1" accept="image/*" style="display:none" onchange="handleReviewPhoto(this, 1)">
                <button class="review-submit-btn" id="reviewSubmitBtn" onclick="submitReview()" disabled>Submit Review</button>
            `;

            document.body.classList.add('slide-panel-open');
            document.getElementById('reviewOverlay').style.display = 'block';
            document.body.style.overflow = 'hidden';

            if (selectedRating > 0) {
                updateStars();
                updateSubmitBtn();
            }
        }

        function closeReviewOverlay() {
            document.getElementById('reviewOverlay').style.display = 'none';
            const detailOverlay = document.getElementById('purchaseDetailOverlay');
            if (detailOverlay && detailOverlay.style.display === 'block') {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.classList.remove('slide-panel-open');
                document.body.style.overflow = 'auto';
            }
        }

        function setRating(value) {
            selectedRating = value;
            updateStars();
            updateSubmitBtn();
            const sentimentEl = document.getElementById('ratingSentiment');
            if (sentimentEl) {
                sentimentEl.textContent = ratingSentiments[value] || '';
            }
            const reviewText = document.getElementById('reviewText');
            if (reviewText) {
                reviewText.placeholder = ratingPlaceholders[value] || 'Share your experience with this product...';
            }
        }

        function hoverRating(value) {
            const stars = document.querySelectorAll('#starRating .star');
            stars.forEach((s, i) => {
                s.classList.remove('hover-active');
                if (i < value) s.classList.add('hover-active');
            });
            const sentimentEl = document.getElementById('ratingSentiment');
            if (sentimentEl) {
                sentimentEl.textContent = ratingSentiments[value] || (ratingSentiments[selectedRating] || '');
            }
            const reviewText = document.getElementById('reviewText');
            if (reviewText && !reviewText.value) {
                reviewText.placeholder = ratingPlaceholders[value] || (ratingPlaceholders[selectedRating] || 'Share your experience with this product...');
            }
        }

        function resetHover() {
            document.querySelectorAll('#starRating .star').forEach(s => s.classList.remove('hover-active'));
            const sentimentEl = document.getElementById('ratingSentiment');
            if (sentimentEl) {
                sentimentEl.textContent = ratingSentiments[selectedRating] || '';
            }
            const reviewText = document.getElementById('reviewText');
            if (reviewText && !reviewText.value) {
                reviewText.placeholder = ratingPlaceholders[selectedRating] || 'Share your experience with this product...';
            }
        }

        function updateStars() {
            const stars = document.querySelectorAll('#starRating .star');
            stars.forEach((s, i) => {
                s.classList.toggle('active', i < selectedRating);
            });
        }

        function updateCharCount(el) {
            const countEl = document.getElementById('charCount');
            if (countEl) {
                countEl.textContent = el.value.length;
                if (el.value.length >= 50) {
                    countEl.style.color = '#ffd700';
                } else {
                    countEl.style.color = '#888';
                }
            }
        }

        function updateSubmitBtn() {
            const btn = document.getElementById('reviewSubmitBtn');
            if (btn) btn.disabled = selectedRating === 0;
        }

        function handleReviewPhoto(input, slot) {
            const file = input.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                reviewPhotos[slot] = file;
                const box = document.getElementById('photoBox' + slot);
                box.innerHTML = `<img src="${e.target.result}" alt="Review photo"><button class="remove-photo" onclick="removeReviewPhoto(${slot}); event.stopPropagation();">&times;</button>`;
            };
            reader.readAsDataURL(file);
        }

        function removeReviewPhoto(slot) {
            reviewPhotos[slot] = null;
            const box = document.getElementById('photoBox' + slot);
            box.innerHTML = '<i class="fas fa-camera"></i>';
            document.getElementById('reviewPhoto' + slot).value = '';
        }

        function submitReview() {
            if (selectedRating === 0) return;
            const order = purchasesData[currentReviewOrderIndex];
            if (!order) return;
            const prods = order.products || [order];
            const p = prods[currentReviewProductIndex] || prods[0] || order;

            const reviewText = document.getElementById('reviewText').value.trim();
            const btn = document.getElementById('reviewSubmitBtn');
            btn.disabled = true;

            const formData = new FormData();
            formData.append('reserve_product_id', p.id);
            formData.append('product_id', p.product_id || 0);
            formData.append('user_id', p.user_id || 0);
            formData.append('rating', selectedRating);
            formData.append('review_text', reviewText);
            if (reviewPhotos[0]) formData.append('photo1', reviewPhotos[0]);
            if (reviewPhotos[1]) formData.append('photo2', reviewPhotos[1]);

            // Simulate loading progress (0 to 100%)
            let progress = 0;
            const startTime = Date.now();
            const totalDuration = 3000; // 3 seconds base duration
            const updateInterval = 50; // Update every 50ms

            const progressInterval = setInterval(() => {
                const elapsed = Date.now() - startTime;
                progress = Math.min(100, Math.floor((elapsed / totalDuration) * 100));
                btn.textContent = `Submitting... ${progress}%`;

                if (progress >= 100) {
                    clearInterval(progressInterval);
                }
            }, updateInterval);

            fetch('submit_review.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(progressInterval);
                if (data.success) {
                    btn.textContent = '100%';
                    setTimeout(() => {
                        showToast('Review Submitted!', 'Thank you for your feedback');
                        closeReviewOverlay();

                        // Mark order and all products in data as reviewed with this rating
                        order.has_review = true;
                        order.user_rating = selectedRating;
                        if (Array.isArray(order.products)) {
                            order.products.forEach(prod => {
                                prod.has_review = true;
                                prod.user_rating = selectedRating;
                            });
                        }

                        // Re-render detail overlay if open
                        const detailOverlay = document.getElementById('purchaseDetailOverlay');
                        if (detailOverlay && (detailOverlay.style.display === 'flex' || detailOverlay.style.display === 'block')) {
                            showPurchaseDetail(currentReviewOrderIndex);
                        }

                        // Update card review row (works for both single and multi-product cards)
                        const card = document.querySelector(`.purchase-card[data-purchase-id="${order.id}"]`);
                        if (card) {
                            const reviewRow = card.querySelector('.card-review-row');
                            if (reviewRow) {
                                let starsHtml = '';
                                for (let s = 1; s <= 5; s++) {
                                    starsHtml += `<span class="card-star ${s <= selectedRating ? 'rated' : 'unrated'}">&#9733;</span>`;
                                }
                                reviewRow.className = 'card-review-row reviewed';
                                reviewRow.innerHTML = `
                                    <span class="review-label">Reviewed</span>
                                    <div class="card-stars">
                                        ${starsHtml}
                                    </div>
                                `;
                            }
                        }
                    }, 500);
                } else {
                    showToast('Review Failed', data.message || 'Please try again');
                    btn.disabled = false;
                    btn.textContent = 'Submit Review';
                }
            })
            .catch(error => {
                clearInterval(progressInterval);
                console.error('Error:', error);
                showToast('Error', 'Could not submit review');
                btn.disabled = false;
                btn.textContent = 'Submit Review';
            });
        }

        // ===== QR CODE MODAL FOR PURCHASE PAGE =====
        function showPurchaseQR(code) {
            let modal = document.getElementById('userPurchaseQRModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'userPurchaseQRModal';
                modal.style.cssText = 'position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.85); z-index:100020; display:flex; align-items:center; justify-content:center; padding:20px;';
                modal.onclick = () => { modal.style.display = 'none'; };
                document.body.appendChild(modal);
            } else {
                modal.style.zIndex = '100020';
            }

            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(code)}`;
            modal.innerHTML = `
                <div style="background:#1a1a1a; border:2px solid #ffd700; border-radius:18px; padding:24px; max-width:360px; width:100%; text-align:center; box-shadow:0 12px 48px rgba(0,0,0,0.9);" onclick="event.stopPropagation()">
                    <div style="color:#ffd700; font-size:17px; font-weight:800; margin-bottom:4px;">Reference & QR Code</div>
                    <div style="color:#aaa; font-size:12px; margin-bottom:16px;">Show this to staff upon store pick-up / return</div>
                    <div style="background:#ffffff; padding:14px; border-radius:14px; display:inline-block; margin-bottom:14px; box-shadow:0 4px 16px rgba(0,0,0,0.4);">
                        <img src="${qrUrl}" alt="QR" style="width:200px; height:200px; display:block;">
                    </div>
                    <div style="color:#ffd700; font-size:16px; font-weight:800; letter-spacing:1px; margin-bottom:3px; font-family: monospace;">${code}</div>
                    <div style="color:#777; font-size:11px; margin-bottom:16px;">Reference Code</div>
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button type="button" onclick="navigator.clipboard.writeText('${code}'); this.innerHTML='<i class=\\'fas fa-check\\'></i> Copied!'; setTimeout(()=>{this.innerHTML='<i class=\\'far fa-copy\\'></i> Copy Ref';}, 2000);" style="background:#2b2d35; border:1px solid #444; color:#ddd; border-radius:24px; padding:9px 18px; font-size:12px; font-weight:600; cursor:pointer;">
                            <i class="far fa-copy"></i> Copy Ref
                        </button>
                        <button type="button" onclick="document.getElementById('userPurchaseQRModal').style.display='none'" style="background:linear-gradient(135deg,#7c6300,#b8960c); color:#fff; border:none; border-radius:24px; padding:9px 24px; font-size:12px; font-weight:700; cursor:pointer;">Close</button>
                    </div>
                </div>
            `;
            modal.style.display = 'flex';
        }

        // Fetch Cart Count for Top Bar and Bottom Nav
        function fetchCartCount() {
            fetch('save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_cart_count'
            })
            .then(r => r.json())
            .then(data => {
                const badges = [
                    document.getElementById('cartCount'),
                    document.getElementById('topbarCartCount')
                ].filter(Boolean);
                badges.forEach(badge => {
                    if (data.success && data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                });
            })
            .catch(() => {});
        }
        // ===== NOTIFICATION DROPDOWN & REAL-TIME SYSTEM =====
        const NOTIF_USER_ID = <?php echo intval($userId); ?>;
        let cachedNotifications = [];
        let activeNotifFilter = 'all';
        let lastKnownNotifCount = 0;

        function ensureDropdownContainer() {
            const dropdown = document.getElementById('notificationDropdown');
            const desktopContainer = document.getElementById('topbarNotificationContainer');
            if (!dropdown) return;

            if (window.innerWidth <= 768) {
                if (dropdown.parentElement !== document.body) {
                    document.body.appendChild(dropdown);
                }
            } else {
                if (desktopContainer && dropdown.parentElement !== desktopContainer) {
                    desktopContainer.appendChild(dropdown);
                }
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(String(text)));
            return div.innerHTML;
        }

        function requestNotificationPermission() {
            if ("Notification" in window && Notification.permission === "default") {
                Notification.requestPermission();
            }
        }

        function playNotificationSound() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, ctx.currentTime);
                osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1);
                gain.gain.setValueAtTime(0.2, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.35);
            } catch (e) {}
        }

        function getReadNotificationIds() {
            try {
                const stored = localStorage.getItem('closetgo_read_notif_ids_' + (NOTIF_USER_ID || 'guest'));
                return stored ? JSON.parse(stored) : [];
            } catch (e) {
                return [];
            }
        }

        function setReadNotificationIds(ids) {
            try {
                localStorage.setItem('closetgo_read_notif_ids_' + (NOTIF_USER_ID || 'guest'), JSON.stringify(ids));
            } catch (e) {}
        }

        function toggleNotificationDropdown(e) {
            if (e) e.stopPropagation();
            ensureDropdownContainer();
            const dropdown = document.getElementById('notificationDropdown');
            if (!dropdown) return;

            const isShowing = dropdown.classList.contains('show');
            if (isShowing) {
                dropdown.classList.remove('show');
            } else {
                dropdown.classList.add('show');
                requestNotificationPermission();
                fetchNotifications(true);
            }
        }

        function filterNotifTab(type, tabEl) {
            activeNotifFilter = type;
            const tabs = document.querySelectorAll('.notif-tab');
            tabs.forEach(t => t.classList.remove('active'));
            if (tabEl) tabEl.classList.add('active');
            renderNotificationsList();
        }

        function fetchNotifications(renderAfter = false) {
            const userId = NOTIF_USER_ID || 0;
            fetch(`notifications_get.php?user_id=${userId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && Array.isArray(data.notifications)) {
                        cachedNotifications = data.notifications;
                        updateNotificationBadge();
                        if (renderAfter || (document.getElementById('notificationDropdown') && document.getElementById('notificationDropdown').classList.contains('show'))) {
                            renderNotificationsList();
                        }
                    }
                })
                .catch(err => {
                    console.error('Error fetching notifications:', err);
                });
        }

        function updateNotificationBadge() {
            const desktopBadge = document.getElementById('topbarNotificationBadge');
            const mobileBadge = document.getElementById('mobileTopbarNotificationBadge');
            const pill = document.getElementById('notifUnreadPill');
            const tabBadgeAll = document.getElementById('notifTabBadgeAll');
            const tabBadgeOrder = document.getElementById('notifTabBadgeOrder');
            const tabBadgeChat = document.getElementById('notifTabBadgeChat');
            const readIds = getReadNotificationIds();

            const unreadList = cachedNotifications.filter(n => !readIds.includes(n.id));
            const unreadCount = unreadList.length;

            const unreadOrders = cachedNotifications.filter(n => (n.type === 'order' || n.type === 'reminder') && !readIds.includes(n.id)).length;
            const unreadChat = cachedNotifications.filter(n => n.type === 'chat' && !readIds.includes(n.id)).length;

            [desktopBadge, mobileBadge].forEach(badge => {
                if (badge) {
                    if (unreadCount > 0) {
                        badge.style.display = 'inline-flex';
                        badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                    } else {
                        badge.style.display = 'none';
                    }
                }
            });

            if (pill) {
                if (unreadCount > 0) {
                    pill.style.display = 'inline-block';
                    pill.textContent = unreadCount + ' new';
                } else {
                    pill.style.display = 'none';
                }
            }

            if (tabBadgeAll) {
                if (unreadCount > 0) {
                    tabBadgeAll.style.display = 'inline-block';
                    tabBadgeAll.textContent = unreadCount > 99 ? '99+' : unreadCount;
                } else {
                    tabBadgeAll.style.display = 'none';
                }
            }

            if (tabBadgeOrder) {
                if (unreadOrders > 0) {
                    tabBadgeOrder.style.display = 'inline-block';
                    tabBadgeOrder.textContent = unreadOrders > 99 ? '99+' : unreadOrders;
                } else {
                    tabBadgeOrder.style.display = 'none';
                }
            }

            if (tabBadgeChat) {
                if (unreadChat > 0) {
                    tabBadgeChat.style.display = 'inline-block';
                    tabBadgeChat.textContent = unreadChat > 99 ? '99+' : unreadChat;
                } else {
                    tabBadgeChat.style.display = 'none';
                }
            }

            if (unreadCount > lastKnownNotifCount && lastKnownNotifCount !== 0) {
                playNotificationSound();
                const latest = unreadList[0];
                if (latest && "Notification" in window && Notification.permission === "granted") {
                    try {
                        const notif = new Notification(latest.title || "ClosetGo Notification", {
                            body: latest.message || "You have a new update",
                            icon: "images/icon/notification.png"
                        });
                        notif.onclick = function() {
                            window.focus();
                            if (latest.link && latest.link.startsWith('javascript:')) {
                                eval(latest.link.replace('javascript:', ''));
                            } else if (latest.link) {
                                window.location.href = latest.link;
                            }
                            notif.close();
                        };
                    } catch (e) {}
                }
            }
            lastKnownNotifCount = unreadCount;
        }

        function renderNotificationsList() {
            const container = document.getElementById('notifListContainer');
            if (!container) return;

            const readIds = getReadNotificationIds();
            let filtered = cachedNotifications;

            if (activeNotifFilter === 'order') {
                filtered = cachedNotifications.filter(n => n.type === 'order' || n.type === 'reminder');
            } else if (activeNotifFilter === 'chat') {
                filtered = cachedNotifications.filter(n => n.type === 'chat');
            }

            if (filtered.length === 0) {
                container.innerHTML = `
                    <div class="notif-empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <div style="font-weight:600; color:#aaa;">No notifications found</div>
                        <div style="font-size:11px; color:#666;">You're all caught up!</div>
                    </div>
                `;
                return;
            }

            const todayDate = new Date();
            const todayYear = todayDate.getFullYear();
            const todayMonth = todayDate.getMonth();
            const todayDay = todayDate.getDate();

            const todayItems = [];
            const earlierItems = [];

            filtered.forEach(n => {
                let isToday = false;
                if (n.timestamp) {
                    const itemDate = new Date(n.timestamp * 1000);
                    if (itemDate.getFullYear() === todayYear && itemDate.getMonth() === todayMonth && itemDate.getDate() === todayDay) {
                        isToday = true;
                    }
                } else if (n.time && (n.time.toLowerCase().includes('just now') || n.time.toLowerCase().includes('min') || n.time.toLowerCase().includes('hour') || n.time.toLowerCase().includes('today') || n.time.toLowerCase().includes('sec'))) {
                    isToday = true;
                }

                if (isToday) {
                    todayItems.push(n);
                } else {
                    earlierItems.push(n);
                }
            });

            function renderItem(n) {
                const isRead = readIds.includes(n.id);
                const iconHtml = `<div class="notif-item-icon" style="background:rgba(255,215,0,0.12); color:${n.badgeColor || '#ffd700'};"><i class="${n.icon || 'fas fa-bell'}"></i></div>`;

                return `
                    <div class="notif-item ${isRead ? '' : 'unread'}" onclick="handleNotificationClick('${escapeHtml(n.id)}', '${escapeHtml(n.link || '')}')">
                        ${iconHtml}
                        <div class="notif-item-content">
                            <div class="notif-item-title">${escapeHtml(n.title || '')}</div>
                            <div class="notif-item-desc">${escapeHtml(n.message || '')}</div>
                            <div class="notif-item-time">${escapeHtml(n.time || '')}</div>
                        </div>
                        ${isRead ? '' : '<div class="notif-unread-dot"></div>'}
                    </div>
                `;
            }

            let html = '';

            if (todayItems.length > 0) {
                html += `<div class="notif-section-header"><i class="fas fa-calendar-day"></i> Today</div>`;
                todayItems.forEach(n => {
                    html += renderItem(n);
                });
            }

            if (earlierItems.length > 0) {
                html += `<div class="notif-section-header"><i class="fas fa-history"></i> Earlier</div>`;
                earlierItems.forEach(n => {
                    html += renderItem(n);
                });
            }

            container.innerHTML = html;
        }

        function handleNotificationClick(notifId, link) {
            const readIds = getReadNotificationIds();
            if (!readIds.includes(notifId)) {
                readIds.push(notifId);
                setReadNotificationIds(readIds);
                updateNotificationBadge();
                renderNotificationsList();
            }

            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) dropdown.classList.remove('show');

            if (link) {
                if (link.startsWith('javascript:')) {
                    const code = link.replace('javascript:', '');
                    eval(code);
                } else {
                    window.location.href = link;
                }
            }
        }

        function markAllNotificationsAsRead(e) {
            if (e) e.stopPropagation();
            const allIds = cachedNotifications.map(n => n.id);
            setReadNotificationIds(allIds);
            updateNotificationBadge();
            renderNotificationsList();
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdown');
            const container = document.getElementById('topbarNotificationContainer');
            const mobileContainer = document.getElementById('mobileTopbarNotificationContainer');
            if (dropdown && dropdown.classList.contains('show')) {
                const inDesktop = container && container.contains(e.target);
                const inMobile = mobileContainer && mobileContainer.contains(e.target);
                const inDropdown = dropdown.contains(e.target);
                if (!inDesktop && !inMobile && !inDropdown) {
                    dropdown.classList.remove('show');
                }
            }
        });

        // Close Purchase Detail modal on Escape key press
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                const detailOverlay = document.getElementById('purchaseDetailOverlay');
                if (detailOverlay && (detailOverlay.style.display === 'flex' || detailOverlay.style.display === 'block')) {
                    closePurchaseDetail();
                }
            }
        });

        window.addEventListener('resize', ensureDropdownContainer);
        window.addEventListener('DOMContentLoaded', () => {
            fetchCartCount();
            ensureDropdownContainer();
            fetchNotifications();
            setInterval(fetchCartCount, 10000);
            setInterval(fetchNotifications, 5000);
        });
    </script>
</body>
</html>
