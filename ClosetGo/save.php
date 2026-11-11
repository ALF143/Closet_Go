<?php
session_start();
require_once 'database/db_config.php';

// Handle fetch cart count request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'get_cart_count') {
    header('Content-Type: application/json');

    $database = new Database();
    $conn = $database->getConnection();

    if ($conn && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $sql = "SELECT COUNT(*) as count FROM saved_products WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                'success' => true,
                'count' => $row['count']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'count' => 0
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'count' => 0
        ]);
    }
    exit();
}

// Handle fetch purchase badge data request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'get_purchase_badge_data') {
    header('Content-Type: application/json');

    $database = new Database();
    $conn = $database->getConnection();

    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['id']) ? $_SESSION['id'] : 0);

    if ($conn && $userId > 0) {
        $sql = "SELECT id, order_status, reservation_group_id, reserved_at, reserve_date, return_date FROM reserve_products WHERE user_id = ? ORDER BY reserved_at DESC, id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $groupMap = [];
        $purchases = [];
        while ($row = $result->fetch_assoc()) {
            $groupKey = !empty($row['reservation_group_id'])
                ? $row['reservation_group_id']
                : ($userId . '_' . $row['reserved_at'] . '_' . ($row['reserve_date'] ?? '') . '_' . ($row['return_date'] ?? ''));

            if (!isset($groupMap[$groupKey])) {
                $idx = count($purchases);
                $groupMap[$groupKey] = $idx;
                $purchases[$idx] = [
                    'id' => (int)$row['id'],
                    'order_status' => $row['order_status'] ?? 'pending'
                ];
            }
        }
        $stmt->close();

        echo json_encode([
            'success' => true,
            'purchases' => $purchases
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'purchases' => []
        ]);
    }
    exit();
}

// Handle fetch category size labels request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'get_category_size_labels') {
    header('Content-Type: application/json');

    $categoryName = $_POST['category_name'] ?? '';

    $database = new Database();
    $conn = $database->getConnection();

    if ($conn) {
        $sql = "SELECT size_label_1, size_label_2, size_label_3, size_label_4, size_label_5, size_label_6, size_label_7, size_label_8, size_label_9, size_label_10, size_guide_image FROM categories WHERE LOWER(TRIM(category_name)) = LOWER(TRIM(?)) LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $categoryName);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                'success' => true,
                'size_label_1' => $row['size_label_1'] ?? null,
                'size_label_2' => $row['size_label_2'] ?? null,
                'size_label_3' => $row['size_label_3'] ?? null,
                'size_label_4' => $row['size_label_4'] ?? null,
                'size_label_5' => $row['size_label_5'] ?? null,
                'size_label_6' => $row['size_label_6'] ?? null,
                'size_label_7' => $row['size_label_7'] ?? null,
                'size_label_8' => $row['size_label_8'] ?? null,
                'size_label_9' => $row['size_label_9'] ?? null,
                'size_label_10' => $row['size_label_10'] ?? null,
                'size_guide_image' => $row['size_guide_image'] ?? null
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'size_label_1' => null,
                'size_label_2' => null,
                'size_label_3' => null,
                'size_label_4' => null,
                'size_label_5' => null,
                'size_label_6' => null,
                'size_label_7' => null,
                'size_label_8' => null,
                'size_label_9' => null,
                'size_label_10' => null,
                'size_guide_image' => null
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'size_label_1' => null,
            'size_label_2' => null,
            'size_label_3' => null,
            'size_label_4' => null,
            'size_label_5' => null,
            'size_label_6' => null,
            'size_label_7' => null,
            'size_label_8' => null,
            'size_label_9' => null,
            'size_label_10' => null,
            'size_guide_image' => null
        ]);
    }
    exit();
}

// Handle fetch product size ranges request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'get_product_size_ranges') {
    header('Content-Type: application/json');

    $productName = $_POST['product_name'] ?? '';
    $productCategory = $_POST['product_category'] ?? '';

    $database = new Database();
    $conn = $database->getConnection();

    if ($conn) {
        $sql = "SELECT size_1_min, size_1_max, size_2_min, size_2_max, size_3_min, size_3_max, size_4_min, size_4_max, size_5_min, size_5_max, size_6_min, size_6_max, size_7_min, size_7_max, size_8_min, size_8_max, size_9_min, size_9_max, size_10_min, size_10_max FROM gowns WHERE name = ? AND category = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $productName, $productCategory);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                'success' => true,
                'size_1_min' => $row['size_1_min'] ?? 0,
                'size_1_max' => $row['size_1_max'] ?? 0,
                'size_2_min' => $row['size_2_min'] ?? 0,
                'size_2_max' => $row['size_2_max'] ?? 0,
                'size_3_min' => $row['size_3_min'] ?? 0,
                'size_3_max' => $row['size_3_max'] ?? 0,
                'size_4_min' => $row['size_4_min'] ?? 0,
                'size_4_max' => $row['size_4_max'] ?? 0,
                'size_5_min' => $row['size_5_min'] ?? 0,
                'size_5_max' => $row['size_5_max'] ?? 0,
                'size_6_min' => $row['size_6_min'] ?? 0,
                'size_6_max' => $row['size_6_max'] ?? 0,
                'size_7_min' => $row['size_7_min'] ?? 0,
                'size_7_max' => $row['size_7_max'] ?? 0,
                'size_8_min' => $row['size_8_min'] ?? 0,
                'size_8_max' => $row['size_8_max'] ?? 0,
                'size_9_min' => $row['size_9_min'] ?? 0,
                'size_9_max' => $row['size_9_max'] ?? 0,
                'size_10_min' => $row['size_10_min'] ?? 0,
                'size_10_max' => $row['size_10_max'] ?? 0
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'size_1_min' => 0,
                'size_1_max' => 0,
                'size_2_min' => 0,
                'size_2_max' => 0,
                'size_3_min' => 0,
                'size_3_max' => 0,
                'size_4_min' => 0,
                'size_4_max' => 0,
                'size_5_min' => 0,
                'size_5_max' => 0,
                'size_6_min' => 0,
                'size_6_max' => 0,
                'size_7_min' => 0,
                'size_7_max' => 0,
                'size_8_min' => 0,
                'size_8_max' => 0,
                'size_9_min' => 0,
                'size_9_max' => 0,
                'size_10_min' => 0,
                'size_10_max' => 0
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'size_1_min' => 0,
            'size_1_max' => 0,
            'size_2_min' => 0,
            'size_2_max' => 0,
            'size_3_min' => 0,
            'size_3_max' => 0,
            'size_4_min' => 0,
            'size_4_max' => 0,
            'size_5_min' => 0,
            'size_5_max' => 0,
            'size_6_min' => 0,
            'size_6_max' => 0,
            'size_7_min' => 0,
            'size_7_max' => 0,
            'size_8_min' => 0,
            'size_8_max' => 0,
            'size_9_min' => 0,
            'size_9_max' => 0,
            'size_10_min' => 0,
            'size_10_max' => 0
        ]);
    }
    exit();
}

// Handle fetch live cart prices and stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_live_cart_prices') {
    header('Content-Type: application/json');

    $database = new Database();
    $conn = $database->getConnection();
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);

    if ($conn && $userId > 0) {
        $sql = "SELECT sp.id as saved_product_id,
                       sp.product_id,
                       sp.product_name,
                       sp.product_category,
                        sp.product_status,
                        sp.reserve_date,
                        sp.return_date,
                        COALESCE(g.price, sp.product_price, 0) as rental_price,
                        COALESCE(g.purchase_price, sp.purchase_price, 0) as purchase_price,
                        COALESCE(g.discount, 0) as discount,
                        COALESCE(g.stock, 0) as stock_quantity,
                        COALESCE(g.image_path, '') as image_path,
                        COALESCE(g.image_path2, '') as image_path2,
                        COALESCE(g.image_path3, '') as image_path3,
                        COALESCE(sp.model_path, g.model_path, '') as model_path,
                        GREATEST(COALESCE(sp.quantity, 1), 1 + (SELECT COUNT(*) FROM saved_products_items spi WHERE spi.saved_product_id = sp.id)) as quantity
                 FROM saved_products sp
                 LEFT JOIN gowns g ON (
                     (sp.product_id > 0 AND sp.product_id = g.id)
                     OR ((sp.product_id IS NULL OR sp.product_id = 0) AND LOWER(TRIM(sp.product_name)) = LOWER(TRIM(g.name)) AND LOWER(TRIM(sp.product_category)) = LOWER(TRIM(g.category)))
                     OR ((sp.product_id IS NULL OR sp.product_id = 0) AND LOWER(TRIM(sp.product_name)) = LOWER(TRIM(g.name)))
                 )
                 WHERE sp.user_id = ?
                 ORDER BY sp.saved_at DESC";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            $liveProducts = [];
            while ($row = $result->fetch_assoc()) {
                $rentalPrice = floatval($row['rental_price']);
                $discount = floatval($row['discount']);
                $discountedRental = ($discount > 0) ? ($rentalPrice * (1 - $discount / 100)) : $rentalPrice;

                $liveProducts[] = [
                    'saved_product_id' => intval($row['saved_product_id']),
                    'product_id' => intval($row['product_id']),
                    'product_name' => $row['product_name'],
                    'product_category' => $row['product_category'],
                    'product_status' => $row['product_status'] ?? 'saved',
                    'rental_price' => $rentalPrice,
                    'discount' => $discount,
                    'discounted_rental_price' => $discountedRental,
                    'purchase_price' => floatval($row['purchase_price']),
                    'stock_quantity' => intval($row['stock_quantity']),
                    'quantity' => intval($row['quantity']),
                    'reserve_date' => $row['reserve_date'],
                    'return_date' => $row['return_date'],
                    'image_path' => $row['image_path'] ?: ($row['image_path2'] ?: ($row['image_path3'] ?? '')),
                    'model_path' => $row['model_path'] ?? ''
                ];
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'products' => $liveProducts
            ]);
            exit();
        }
    }

    echo json_encode([
        'success' => false,
        'products' => []
    ]);
    exit();
}


// Check if user is logged in
$isLoggedIn = false;
$currentUser = null;
$displayName = '';
$userProfileImage = 'images/icon/profile_logo.png';

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
$userEmail = isset($_SESSION['email']) ? $_SESSION['email'] : (isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '');

// Get saved products from database if user is logged in
$savedProducts = [];
$debugInfo = [];

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

            if ($userId > 0) {
                $sql = "SELECT sp.*,
                        COALESCE(g.price, sp.product_price) as product_price,
                        COALESCE(g.price, sp.product_price) as price,
                        COALESCE(g.purchase_price, sp.purchase_price, 0) as purchase_price,
                        COALESCE(g.stock, 0) as stock_quantity,
                        COALESCE(g.discount, 0) as discount,
                        COALESCE(sp.model_path, g.model_path) as model_path,
                        COALESCE(g.image_path, '') as image_path,
                        COALESCE(g.image_path2, '') as image_path2,
                        COALESCE(g.image_path3, '') as image_path3,
                        GREATEST(COALESCE(sp.quantity, 1), 1 + (SELECT COUNT(*) FROM saved_products_items spi WHERE spi.saved_product_id = sp.id)) as quantity
                        FROM saved_products sp
                        LEFT JOIN gowns g ON (
                            (sp.product_id > 0 AND sp.product_id = g.id)
                            OR ((sp.product_id IS NULL OR sp.product_id = 0) AND LOWER(TRIM(sp.product_name)) = LOWER(TRIM(g.name)) AND LOWER(TRIM(sp.product_category)) = LOWER(TRIM(g.category)))
                            OR ((sp.product_id IS NULL OR sp.product_id = 0) AND LOWER(TRIM(sp.product_name)) = LOWER(TRIM(g.name)))
                        )
                        WHERE sp.user_id = ? ORDER BY sp.saved_at DESC";
                $stmt = $conn->prepare($sql);
                
                if ($stmt) {
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        $savedProducts[] = $row;
                    }
                    
                    $debugInfo['user_id'] = $userId;
                    $debugInfo['products_count'] = count($savedProducts);
                    $debugInfo['database_connected'] = true;
                } else {
                    $debugInfo['error'] = 'Failed to prepare statement';
                }
            }
        } else {
            $debugInfo['error'] = 'Failed to get database connection';
        }
    } catch (Exception $e) {
        $debugInfo['error'] = 'Database exception: ' . $e->getMessage();
    }
} else {
    $debugInfo['user_id'] = 0;
    $debugInfo['message'] = 'User not logged in';
}

// Also get localStorage products for display
$localStorageProducts = [];
if (isset($_SESSION['localStorage_products'])) {
    $localStorageProducts = json_decode($_SESSION['localStorage_products'], true) ?: [];
}

// Fetch rental duration days & overdue penalty settings
$rentalDurationDays = 10;
$overduePenaltyPercent = 10;
$maxExtensionDays = 7;
$extensionPricePerDay = 100;
try {
    $database = new Database();
    $conn = $database->getConnection();
    if ($conn) {
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
    }
} catch (Exception $e) {
    error_log("Error fetching rental settings in save.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Save - ClosetGo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/homepage.css?v=<?php echo filemtime('css/homepage.css'); ?>">
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.1.1/model-viewer.min.js"></script>
    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.14/dist/dotlottie-wc.js" type="module"></script>
    <style>
        .type-selection-group {
            margin-bottom: 10px;
        }
        .type-radio-row {
            display: flex;
            align-items: center;
            gap: 22px;
        }
        .type-radio-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
            padding: 4px 6px;
            border-radius: 6px;
            transition: background 0.2s ease;
        }
        .type-radio-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        .type-circle-radio {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #666;
            background: transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
            box-sizing: border-box;
            position: relative;
        }
        .type-circle-radio .type-circle-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: transparent;
            transition: all 0.2s ease;
        }
        .type-circle-radio.active {
            border-color: #ffd700;
            background: #ffd700 !important;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.7);
        }
        .type-circle-radio.active .type-circle-dot {
            display: none;
        }
        .type-circle-radio.active.buy-active {
            border-color: #22c55e;
            background: #22c55e !important;
            box-shadow: 0 0 10px rgba(34, 197, 94, 0.7);
        }
        .type-circle-radio.active.buy-active .type-circle-dot {
            display: none;
        }
        .type-radio-text {
            font-size: 13px;
            color: #888;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        .type-label-heading {
            display: block !important;
            font-size: 12px !important;
            color: #ffffff !important;
            margin-bottom: 6px !important;
            font-weight: 600 !important;
            margin-top: 0 !important;
            padding: 0 !important;
            line-height: normal !important;
            text-align: left !important;
        }
        .reservation-form .date-row {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 10px !important;
            margin-bottom: 10px !important;
            position: static !important;
        }
        .reservation-form .date-row .form-group {
            margin-bottom: 0 !important;
        }
        .reservation-form .date-row .form-group input {
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .reservation-form .date-row .form-group label,
        .reservation-form .form-group #reserveDateLabel,
        .reservation-form .form-group #returnDateLabel {
            display: block !important;
            font-size: 12px !important;
            color: #ffffff !important;
            margin-bottom: 6px !important;
            font-weight: 600 !important;
            margin-top: 0 !important;
            padding: 0 !important;
            line-height: normal !important;
            text-align: left !important;
        }
        .reservation-form .quantity-group {
            position: static !important;
            bottom: auto !important;
            left: auto !important;
            right: auto !important;
            background: transparent !important;
            padding: 0 !important;
            margin: 0 0 10px 0 !important;
            z-index: auto !important;
            width: auto !important;
            display: block !important;
        }
        .reservation-form .quantity-group .quantity-row {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            gap: 0 !important;
            margin-top: 0 !important;
            width: auto !important;
        }
        .reservation-form .quantity-group .quantity-label {
            display: block !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            color: #ffffff !important;
            margin-bottom: 6px !important;
            margin-top: 0 !important;
            padding: 0 !important;
            line-height: normal !important;
            text-align: left !important;
        }
        .reservation-form .quantity-group .quantity-selector {
            display: inline-flex !important;
            align-items: center !important;
            gap: 3px !important;
        }
        .reservation-form .quantity-group .quantity-btn {
            width: 24px !important;
            height: 24px !important;
            background: #333 !important;
            color: #fff !important;
            border: 1px solid #555 !important;
            border-radius: 3px !important;
            font-size: 14px !important;
            font-weight: bold !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            line-height: 1 !important;
            transition: all 0.2s ease !important;
        }
        .reservation-form .quantity-group .quantity-btn:hover {
            background: #444 !important;
            border-color: #ffd700 !important;
        }
        .reservation-form .quantity-group .quantity-input {
            width: 36px !important;
            height: 24px !important;
            background: #222 !important;
            color: #fff !important;
            border: 1px solid #555 !important;
            border-radius: 3px !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            text-align: center !important;
            padding: 0 !important;
            box-sizing: border-box !important;
            -moz-appearance: textfield !important;
        }
        .reservation-form .quantity-group .quantity-input::-webkit-outer-spin-button,
        .reservation-form .quantity-group .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none !important;
            margin: 0 !important;
        }
        .reservation-form .measurement-label {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            margin-bottom: 6px !important;
            margin-top: 10px !important;
            padding: 0 !important;
        }
        .reservation-form .measurement-label > span:first-child,
        .reservation-form .measurement-label .item-label-text {
            font-size: 12px !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            display: inline-block !important;
            line-height: normal !important;
            margin: 0 !important;
            padding: 0 !important;
            text-align: left !important;
        }
        .save-submit-btn {
            width: 100% !important;
            background: #2a2a2a !important;
            color: #ffffff !important;
            border: none !important;
            padding: 9.5px 20px !important;
            font-size: 14px !important;
            font-weight: 550 !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            box-shadow: inset 0 0 15px rgba(124, 99, 0, 0.4) !important;
            border-radius: 5px !important;
            height: 40px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-sizing: border-box !important;
        }
        .save-submit-btn:hover {
            background: #3a3a3a !important;
            transform: translateY(-2px) !important;
            box-shadow: inset 0 0 20px rgba(124, 99, 0, 0.6) !important;
        }
        .save-submit-btn:active {
            transform: translateY(0) !important;
            box-shadow: inset 0 0 25px rgba(124, 99, 0, 0.8) !important;
        }
        .delete-submit-btn {
            flex: 1;
            background: #dc3545 !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            border-radius: 6px !important;
            height: 38px !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            border: none !important;
            font-size: 0.9rem !important;
            transition: all 0.2s ease !important;
        }
        .delete-submit-btn:hover {
            background: #b02a37 !important;
            transform: translateY(-1px) !important;
        }
        /* Calendar Modal Guide and Rental Gap Styling */
        .calendar-modal-guide {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 215, 0, 0.22);
            border-radius: 8px;
            padding: 9px 12px;
            margin-bottom: 10px;
            font-size: 11px;
            line-height: 1.4;
            color: #ddd;
            display: flex;
            flex-direction: column;
            gap: 5px;
            text-align: left;
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
            background: rgba(220, 53, 69, 0.45);
            border: 1px solid #dc3545;
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
        .calendar-day.rental-gap {
            background: rgba(220, 53, 69, 0.35) !important;
            color: #ffffff !important;
            border: 1px solid rgba(220, 53, 69, 0.75) !important;
            font-weight: 600 !important;
        }
        .calendar-day.rental-gap:hover:not(.disabled) {
            background: rgba(220, 53, 69, 0.65) !important;
            border-color: #ff4d4d !important;
            color: #ffffff !important;
            cursor: pointer !important;
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
            background: rgba(255, 215, 0, 0.25) !important;
            border: 1.5px dashed #ffd700 !important;
            color: #ffd700 !important;
            font-weight: 700 !important;
        }
        .calendar-day.selected {
            background: #ffd700 !important;
            color: #000000 !important;
            font-weight: 700 !important;
            border: 2px solid #ffd700 !important;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.6) !important;
        }
        .calendar-day.disabled.booked-date {
            background: rgba(45, 15, 15, 0.85) !important;
            color: #ff4444 !important;
            text-decoration: line-through !important;
            cursor: not-allowed !important;
            border: 1px solid rgba(220, 53, 69, 0.35) !important;
            opacity: 0.75 !important;
        }
        .calendar-day.disabled.exceeds-duration {
            background: #191919 !important;
            color: #dd4444 !important;
            cursor: not-allowed !important;
            opacity: 0.35 !important;
            border-color: transparent !important;
            text-decoration: none !important;
        }
        .calendar-day.disabled.past-date {
            background: #141414 !important;
            color: #555555 !important;
            cursor: not-allowed !important;
            opacity: 0.3 !important;
            border-color: transparent !important;
        }
    </style>
</head>
<body class="save-page product-reserve">
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
                            <input type="text" placeholder="Search saved products..." id="searchInput" oninput="filterSavedProducts()" onkeyup="filterSavedProducts()" style="background: none; border: none; outline: none; flex: 1; font-size: 1rem; color: #ffffff; transition: opacity 0.3s ease;">
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
                    <span id="cartCount" class="cart-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
                </div>
                <div class="chat-top-container" style="position: relative; display: none;" title="Chat with Admin" onclick="window.location.href='index.php'">
                    <img src="images/icon/message.png" alt="Chat" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                         onmouseover="this.style.transform='scale(1.1)'"
                         onmouseout="this.style.transform='scale(1)'">
                    <span id="userChatBadge" class="purchase-nav-badge cart-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
                </div>
            </div>
        </div>

        <!-- Mobile Top Bar -->
        <div class="top-bar">
            <div class="top-bar-content" style="justify-content: flex-start;">
                <img src="images/icon/back.png" alt="Back" class="back-button" onclick="goBack()">
                <span class="top-bar-text" style="margin-left: 10px;">Products Cart <span style="color: #999; font-size: 0.85em; position: relative; top: -2px;">(<?php echo count($savedProducts); ?>)</span></span>
                <span id="topEditButton" onclick="toggleEditMode()" style="margin-left: auto; font-size: 14px; color: #ffffff; cursor: pointer;">Edit</span>
            </div>
        </div>
        
        <!-- Main Content -->
        <div id="productsContainer">
            <!-- Product boxes will be dynamically generated here -->
        </div>
    </div> <!-- End of .shell -->

    <script>
    // Pass PHP data to JavaScript
    const savedProductsFromDB = <?php echo json_encode($savedProducts); ?>;
    const localStorageProducts = <?php echo json_encode($localStorageProducts); ?>;
    const debugInfo = <?php echo json_encode($debugInfo); ?>;
    const isLoggedIn = <?php echo $userId > 0 ? 'true' : 'false'; ?>;
    const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0'; ?>;
    let isEditMode = false;
    
    console.log('Database products:', savedProductsFromDB);
    console.log('LocalStorage products:', localStorageProducts);
    console.log('User logged in:', isLoggedIn);
    
    // Keep page at the top on refresh until the user scrolls
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    
    function goBack() {
        // Navigate back to previous page
        window.history.back();
    }

    // Function to update cart count from database API
    function updateCartCount() {
        const cartCountElement = document.getElementById('cartCount');
        if (!cartCountElement) return;

        fetch('save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_cart_count'
        })
        .then(response => response.json())
        .then(data => {
            const count = data.success ? data.count : 0;
            if (count > 0) {
                cartCountElement.textContent = count;
                cartCountElement.style.display = 'flex';
            } else {
                cartCountElement.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error fetching cart count:', error);
            cartCountElement.style.display = 'none';
        });
    }

    // Function to update Purchases button badge
    function updatePurchasesBadge(purchasesData) {
        const badge = document.getElementById('topbarPurchaseBadge');
        if (!badge) return;

        let viewed = {};
        try {
            viewed = JSON.parse(localStorage.getItem('viewedPurchases') || '{}');
        } catch(e) {}

        let unviewedCount = 0;
        const targetStatuses = ['confirmed', 'completed', 'cancelled'];

        (purchasesData || []).forEach(p => {
            const status = p.order_status || 'pending';
            const pId = p.id;
            if (targetStatuses.includes(status) && viewed[pId] !== status) {
                unviewedCount++;
            }
        });

        if (unviewedCount > 0) {
            badge.textContent = unviewedCount;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    function fetchPurchaseBadgeCount() {
        const badge = document.getElementById('topbarPurchaseBadge');
        if (!badge) return;

        fetch('save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_purchase_badge_data'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && Array.isArray(data.purchases)) {
                updatePurchasesBadge(data.purchases);
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error fetching purchase badge:', error);
            badge.style.display = 'none';
        });
    }
    
    // Function to filter saved products based on search input in real time
    function filterSavedProducts() {
        const searchInput = document.getElementById('searchInput');
        if (!searchInput) return;
        const query = searchInput.value.trim().toLowerCase();
        const container = document.getElementById('productsContainer');
        if (!container) return;
        const boxes = container.querySelectorAll('.login-box');
        let visibleCount = 0;
        let noResultsEl = document.getElementById('noSearchResultsMessage');

        boxes.forEach(box => {
            const name = (box.querySelector('.product-name')?.textContent || '').toLowerCase();
            const category = (box.querySelector('.product-category')?.textContent || '').toLowerCase();

            if (!query || name.includes(query) || category.includes(query)) {
                box.style.display = '';
                visibleCount++;
            } else {
                box.style.display = 'none';
            }
        });

        if (boxes.length > 0) {
            if (visibleCount === 0 && query !== '') {
                if (!noResultsEl) {
                    noResultsEl = document.createElement('div');
                    noResultsEl.id = 'noSearchResultsMessage';
                    noResultsEl.className = 'empty-state';
                    noResultsEl.style.cssText = 'display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px 20px; width: 100%;';
                    noResultsEl.innerHTML = '<dotlottie-wc src="https://lottie.host/7a9e1682-3342-49d1-a823-511f6b3f1fdc/x7SG6ZMpXp.lottie" style="width: 280px; height: 280px; max-width: 90vw;" autoplay loop></dotlottie-wc><p style="color: #888888; margin-top: 16px; font-size: 16px; font-weight: 500; font-family: \'Poppins\', sans-serif;">No saved products match "<strong>' + query.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</strong>"</p>';
                    container.appendChild(noResultsEl);
                } else {
                    noResultsEl.innerHTML = '<dotlottie-wc src="https://lottie.host/7a9e1682-3342-49d1-a823-511f6b3f1fdc/x7SG6ZMpXp.lottie" style="width: 280px; height: 280px; max-width: 90vw;" autoplay loop></dotlottie-wc><p style="color: #888888; margin-top: 16px; font-size: 16px; font-weight: 500; font-family: \'Poppins\', sans-serif;">No saved products match "<strong>' + query.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</strong>"</p>';
                    noResultsEl.style.display = 'flex';
                }
            } else {
                if (noResultsEl) {
                    noResultsEl.style.display = 'none';
                }
            }
        }
    }

    // Load saved products
    window.addEventListener('DOMContentLoaded', function() {
        window.scrollTo(0, 0);
        loadSavedProducts();
        updateCartCount();
        fetchPurchaseBadgeCount();

        // Update cart count and purchase badge periodically (every 10 seconds)
        setInterval(updateCartCount, 10000);
        setInterval(fetchPurchaseBadgeCount, 10000);
        
        // Initialize button states for quantity selector
        const totalQuantityInput = document.getElementById('totalQuantity');
        const plusBtn = document.querySelector('.quantity-plus');
        const minusBtn = document.querySelector('.quantity-minus');
        
        if (totalQuantityInput && plusBtn && minusBtn) {
            const minValue = parseInt(totalQuantityInput.min);
            const maxValue = parseInt(totalQuantityInput.max);
            const currentValue = parseInt(totalQuantityInput.value);
            
            // Disable minus button if at min (1)
            if (currentValue <= minValue) {
                minusBtn.disabled = true;
                minusBtn.style.opacity = '0.5';
                minusBtn.style.cursor = 'not-allowed';
            }
            
            // Disable plus button if at max
            if (currentValue >= maxValue) {
                plusBtn.disabled = true;
                plusBtn.style.opacity = '0.5';
                plusBtn.style.cursor = 'not-allowed';
            }
        }
    });

    // Custom calendar state
    let currentCalendarDate = new Date();
    let activeDateInputId = null;
    let unavailableDates = [];
    let currentCalendarProductId = 0;
    let currentRentalDurationDays = <?php echo intval($rentalDurationDays); ?> || 10;
    let currentMaxExtensionDays = <?php echo intval($maxExtensionDays); ?> || 7;
    let currentExtensionPricePerDay = <?php echo floatval($extensionPricePerDay); ?> || 100;
    let currentOverduePenaltyPercent = <?php echo intval($overduePenaltyPercent); ?> || 10;

    function fetchUnavailableDatesForCalendar(productId, productName = '', productCategory = '', callback) {
        const type = window.currentOverlayType || 'reserve';
        const measurementsContainer = document.getElementById('measurementsContainer');
        const count = measurementsContainer ? measurementsContainer.querySelectorAll('.measurement-group').length : 1;
        const qty = Math.max(1, count);
        const url = `get_unavailable_dates.php?product_id=${encodeURIComponent(productId || '')}&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}&type=${encodeURIComponent(type)}&quantity=${encodeURIComponent(qty)}`;
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    unavailableDates = data.dates || [];
                    if (typeof data.rental_duration_days !== 'undefined') currentRentalDurationDays = parseInt(data.rental_duration_days);
                    if (typeof data.max_extension_days !== 'undefined') currentMaxExtensionDays = parseInt(data.max_extension_days);
                    if (typeof data.extension_price_per_day !== 'undefined') currentExtensionPricePerDay = parseFloat(data.extension_price_per_day);
                    if (typeof data.overdue_penalty_percent !== 'undefined') currentOverduePenaltyPercent = parseInt(data.overdue_penalty_percent);

                    // If currently selected reserve/purchase date is in unavailableDates, clear it!
                    const reserveInput = document.getElementById('reserveDate');
                    if (reserveInput && reserveInput.value && unavailableDates.includes(reserveInput.value)) {
                        reserveInput.value = '';
                        updateReturnDateInputState();
                        if (document.getElementById('returnDate')) {
                            document.getElementById('returnDate').value = '';
                        }
                        showNotification('Selected date is unavailable due to existing bookings or stock limits. Please pick another date.', 3500);
                    }

                    updateOverlayPricesRealtime();

                    // If calendar modal is open, re-render it
                    const overlay = document.getElementById('calendarModalOverlay');
                    if (overlay && overlay.classList.contains('active')) {
                        renderCalendar();
                    }
                }
                if (callback) callback();
            })
            .catch(error => {
                console.error('Error fetching unavailable dates:', error);
                if (callback) callback();
            });
    }

    function updateReturnDateInputState() {
        const reserveDateVal = document.getElementById('reserveDate')?.value;
        const returnDateInput = document.getElementById('returnDate');
        if (!returnDateInput) return;

        if (!reserveDateVal) {
            returnDateInput.classList.add('date-input-disabled');
            returnDateInput.style.cursor = 'not-allowed';
            returnDateInput.style.opacity = '0.5';
            returnDateInput.title = 'Please select a Reserve Date first';
        } else {
            returnDateInput.classList.remove('date-input-disabled');
            returnDateInput.style.cursor = 'pointer';
            returnDateInput.style.opacity = '1';
            returnDateInput.title = 'Select Return Date';
        }
    }

    function openCalendarModal(inputId) {
        if (inputId === 'returnDate') {
            const currentReserveVal = document.getElementById('reserveDate')?.value;
            if (!currentReserveVal) {
                showNotification('Please select a Reserve Date first.', 3000);
                return;
            }
        }
        activeDateInputId = inputId;

        // Fetch unavailable dates for the current product from the overlay
        const reserveOverlay = document.getElementById('reserveOverlay');
        const gownId = reserveOverlay ? (reserveOverlay.getAttribute('data-gown-id') || '') : '';
        const productName = document.querySelector('#reserveOverlay .product-name-overlay')?.textContent?.trim() || '';
        
        let productCategory = 'Gown';
        const allProductNames = document.querySelectorAll('[id^="productName"]');
        for (let element of allProductNames) {
            if (element.textContent.trim() === productName) {
                const boxNumber = element.id.replace('productName', '');
                const categoryElement = document.getElementById(`productCategory${boxNumber}`);
                if (categoryElement) {
                    productCategory = categoryElement.textContent.trim();
                    break;
                }
            }
        }

        // If opening return date, start from reserve date month if set
        const reserveDate = document.getElementById('reserveDate')?.value;
        if (reserveDate) {
            currentCalendarDate = new Date(reserveDate + 'T00:00:00');
        } else if (window.currentEarliestBuyDate) {
            currentCalendarDate = new Date(window.currentEarliestBuyDate + 'T00:00:00');
        } else {
            currentCalendarDate = new Date();
        }
        const overlay = document.getElementById('calendarModalOverlay');
        overlay.classList.add('active');

        // Always render immediately and fetch updated unavailable dates based on stock, qty and type
        renderCalendar();
        fetchUnavailableDatesForCalendar(gownId, productName, productCategory, function() {
            renderCalendar();
        });
    }

    function formatLocalDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function closeCalendarModal(event) {
        if (event && event.target !== document.getElementById('calendarModalOverlay')) return;
        document.getElementById('calendarModalOverlay').classList.remove('active');
        activeDateInputId = null;
        window.isPickingBuyDate = false;
    }

    function changeCalendarMonth(direction) {
        currentCalendarDate.setMonth(currentCalendarDate.getMonth() + direction);
        renderCalendar();
    }

    function renderCalendar() {
        const year = currentCalendarDate.getFullYear();
        const month = currentCalendarDate.getMonth();
        const firstDayOfMonth = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const firstDayOfWeek = firstDayOfMonth.getDay();

        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        document.getElementById('calendarModalTitle').textContent = `${monthNames[month]} ${year}`;

        const grid = document.getElementById('calendarModalGrid');
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
        const currentInput = activeDateInputId ? document.getElementById(activeDateInputId) : null;
        const selectedDate = currentInput ? currentInput.value : '';
        const reserveDateVal = document.getElementById('reserveDate')?.value || '';
        const returnDateVal = document.getElementById('returnDate')?.value || '';
        const requestedDuration = (typeof window.currentRentalDurationDays !== 'undefined') ? window.currentRentalDurationDays : (<?php echo intval($rentalDurationDays); ?> || 10);
        const extensionPricePerDay = (typeof window.currentExtensionPricePerDay !== 'undefined') ? window.currentExtensionPricePerDay : (<?php echo floatval($extensionPricePerDay); ?> || 100);
        const maxExtensionDays = (typeof window.currentMaxExtensionDays !== 'undefined') ? window.currentMaxExtensionDays : (<?php echo intval($maxExtensionDays); ?> || 7);

        let minDate = today;
        let maxDate = null;
        let standardReturnDate = null;
        let firstBookedAfterReserve = null;

        if (activeDateInputId === 'returnDate') {
            if (reserveDateVal) {
                minDate = reserveDateVal; // cannot be earlier than reserve date
                const stdReturnObj = new Date(reserveDateVal + 'T00:00:00');
                stdReturnObj.setDate(stdReturnObj.getDate() + requestedDuration);
                standardReturnDate = formatLocalDate(stdReturnObj);

                const maxReturnObj = new Date(reserveDateVal + 'T00:00:00');
                maxReturnObj.setDate(maxReturnObj.getDate() + requestedDuration + maxExtensionDays);
                maxDate = formatLocalDate(maxReturnObj); // cannot exceed standard duration + max extension!

                // Find earliest booked date on or after reserveDateVal so user can't select on/past it
                for (let d = 1; d <= (requestedDuration + maxExtensionDays + 30); d++) {
                    const cObj = new Date(reserveDateVal + 'T00:00:00');
                    cObj.setDate(cObj.getDate() + d);
                    const cStr = formatLocalDate(cObj);
                    if (unavailableDates.includes(cStr)) {
                        firstBookedAfterReserve = cStr;
                        break;
                    }
                }
            }
        } else if (window.isPickingBuyDate && window.currentEarliestBuyDate) {
            if (window.currentEarliestBuyDate > minDate) {
                minDate = window.currentEarliestBuyDate;
            }
        }

        // Render Guide Text and Legend inside Calendar Modal
        const guideContainer = document.getElementById('calendarModalGuide');
        if (guideContainer) {
            if (activeDateInputId === 'returnDate') {
                const stdReturnFormatted = standardReturnDate ? formatDateNice(standardReturnDate) : '';
                const maxReturnFormatted = maxDate ? formatDateNice(maxDate) : '';
                const reserveFormatted = reserveDateVal ? formatDateNice(reserveDateVal) : 'Reserve Date';
                const isCurrentlyExtended = returnDateVal && standardReturnDate && returnDateVal > standardReturnDate;
                guideContainer.innerHTML = `
                    <div class="calendar-guide-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <i class="fas fa-calendar-check" style="color: #ffd700;"></i>
                            <span>Select Return Date (Standard: <strong>${requestedDuration} Days</strong>${maxExtensionDays > 0 ? ` &bull; Max +${maxExtensionDays} Days Ext` : ''})</span>
                        </div>
                        ${isCurrentlyExtended ? `<button type="button" class="btn-reset-standard" onclick="resetReturnDateToStandard()" style="background: rgba(255, 215, 0, 0.2); border: 1px solid #ffd700; color: #ffd700; border-radius: 4px; padding: 2px 8px; font-size: 0.75rem; cursor: pointer; font-weight: 600;">Reset to Standard (${requestedDuration} Days)</button>` : ''}
                    </div>
                    <div class="calendar-guide-msg">
                        The <span class="guide-badge-inline red-gap">Red Highlight</span> marks your standard rental duration gap (<strong>${reserveFormatted}</strong> to <strong>${stdReturnFormatted}</strong>, not clickable).
                        ${maxExtensionDays > 0 ? `Dates after the red gap (up to <strong>${maxReturnFormatted}</strong>) can be selected to extend your rental for an additional <strong>₱${extensionPricePerDay.toLocaleString()}/day</strong>.` : ''}
                    </div>
                    <div class="calendar-guide-legend">
                        <span class="legend-chip red-gap"><span class="chip-box"></span> Red Highlight = Standard Rental (Same date to ${requestedDuration} Days After - Not Clickable)</span>
                        ${maxExtensionDays > 0 ? `<span class="legend-chip extension"><span class="chip-box" style="background: rgba(56, 189, 248, 0.35); border: 1px dashed #38bdf8;"></span> Blue Dashed = Extension (+₱${extensionPricePerDay}/day)</span>` : ''}
                        <span class="legend-chip booked"><span class="chip-box"></span> Red Strikethrough / Dimmed = Booked / Not Allowed</span>
                    </div>
                `;
            } else {
                guideContainer.innerHTML = `
                    <div class="calendar-guide-header">
                        <i class="fas fa-calendar-alt" style="color: #ffd700;"></i>
                        <span>Select ${window.currentOverlayType === 'buy' ? 'Purchase' : 'Reserve'} Date (Standard Duration: <strong>${requestedDuration} Days</strong>)</span>
                    </div>
                    <div class="calendar-guide-msg">
                        ${window.currentOverlayType === 'buy' ? 'Pick your desired purchase / pickup date. Dates with existing reservations cannot be purchased.' : `Pick your pickup date. Your Return Date will automatically be set <strong>${requestedDuration} days</strong> forward based on your rental duration.`}
                    </div>
                    <div class="calendar-guide-legend">
                        <span class="legend-chip booked"><span class="chip-box"></span> Red Strikethrough = ${window.currentOverlayType === 'buy' ? 'Reserved / Unavailable (Can\'t Buy)' : 'Fully Booked / Unavailable (Can\'t Click)'}</span>
                    </div>
                `;
            }
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dateObj = new Date(year, month, day);
            const dateStr = formatLocalDate(dateObj);
            const dayElem = document.createElement('div');
            dayElem.className = 'calendar-day';
            dayElem.textContent = day;

            if (dateStr === today) {
                dayElem.classList.add('today');
            }

            let isPastBooked = false;
            if (activeDateInputId === 'returnDate' && reserveDateVal && firstBookedAfterReserve) {
                if (dateStr >= firstBookedAfterReserve) {
                    isPastBooked = true;
                }
            }

            const isBooked = unavailableDates.includes(dateStr) || isPastBooked;
            const isBeforeMin = (dateStr < minDate);
            const isExceedsMax = (maxDate && dateStr > maxDate);

            // Rental Duration Gap & Extension Highlighting (ONLY visible when selecting Return Date)
            let isStandardRentalGap = false;
            if (activeDateInputId === 'returnDate' && reserveDateVal && standardReturnDate && !isBooked && !isBeforeMin) {
                if (dateStr >= reserveDateVal && dateStr <= standardReturnDate) {
                    isStandardRentalGap = true;
                    dayElem.classList.add('rental-gap');
                    dayElem.title = `Standard Rental (Same date to ${requestedDuration} Days After) - Not Clickable`;
                } else if (maxDate && dateStr > standardReturnDate && dateStr <= maxDate) {
                    dayElem.classList.add('extension-day');
                    dayElem.title = `Rental Extension (+₱${extensionPricePerDay}/day)`;
                }
            }

            if (dateStr === selectedDate) {
                dayElem.classList.add('selected');
            } else if (activeDateInputId === 'returnDate' && dateStr === reserveDateVal) {
                dayElem.classList.add('reserve-anchor');
                dayElem.title = 'Reserve Date';
            }

            if (isBooked || isBeforeMin || isExceedsMax || isStandardRentalGap) {
                dayElem.classList.add('disabled');
                if (isBooked) {
                    dayElem.classList.add('booked-date');
                    dayElem.title = 'Fully Booked / Unavailable';
                } else if (isStandardRentalGap) {
                    dayElem.title = `Standard Rental (Same date to ${requestedDuration} Days After) - Not Clickable`;
                } else if (isExceedsMax) {
                    dayElem.classList.add('exceeds-duration');
                    const maxDaysTotal = requestedDuration + maxExtensionDays;
                    dayElem.title = `Exceeds maximum allowed rental period of ${maxDaysTotal} days`;
                } else if (isBeforeMin) {
                    dayElem.classList.add('past-date');
                    dayElem.title = (activeDateInputId === 'returnDate') ? 'Cannot return before Reserve Date' : 'Past date';
                }
                dayElem.style.pointerEvents = 'none';
                dayElem.onclick = null;
            } else {
                dayElem.style.pointerEvents = 'auto';
                dayElem.onclick = function() {
                    if (activeDateInputId === 'reserveDate') {
                        handleReserveDateSelection(dateStr);
                    } else if (activeDateInputId === 'returnDate') {
                        handleReturnDateSelection(dateStr);
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

    let pendingAdjustment = null;

    function formatDateNice(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    // Reset Return Date back to standard rental duration
    function resetReturnDateToStandard() {
        const reserveDateVal = document.getElementById('reserveDate')?.value;
        if (!reserveDateVal) return;
        const requestedDuration = (typeof window.currentRentalDurationDays !== 'undefined') ? window.currentRentalDurationDays : (<?php echo intval($rentalDurationDays); ?> || 10);
        const stdReturnObj = new Date(reserveDateVal + 'T00:00:00');
        stdReturnObj.setDate(stdReturnObj.getDate() + requestedDuration);
        const standardReturnDate = formatLocalDate(stdReturnObj);
        document.getElementById('returnDate').value = standardReturnDate;
        updateAvailableStock();
        updateOverlayPricesRealtime();
        renderCalendar();
        showNotification(`Return Date reset to standard rental duration (${requestedDuration} days).`, 2500);
    }

    // Handle Reserve Date selection with smart availability detection
    function handleReserveDateSelection(dateStr) {
        if (unavailableDates.includes(dateStr)) {
            showNotification(window.currentOverlayType === 'buy' ? 'This date cannot be selected for purchase due to upcoming reservations.' : 'This date is already booked.', 2500);
            return;
        }

        if (window.currentOverlayType === 'buy') {
            document.getElementById('reserveDate').value = dateStr;
            document.getElementById('calendarModalOverlay').classList.remove('active');
            activeDateInputId = null;
            updateOverlayPricesRealtime();
            return;
        }

        const requestedDuration = (typeof window.currentRentalDurationDays !== 'undefined') ? window.currentRentalDurationDays : (<?php echo intval($rentalDurationDays); ?> || 10);
        
        // Check consecutive availability from dateStr up to requestedDuration after
        let maxAvailableDays = 0;
        let firstConflictDate = null;
        
        for (let d = 1; d <= requestedDuration; d++) {
            const checkObj = new Date(dateStr + 'T00:00:00');
            checkObj.setDate(checkObj.getDate() + d);
            const checkStr = formatLocalDate(checkObj);
            
            if (unavailableDates.includes(checkStr)) {
                firstConflictDate = checkStr;
                break;
            }
            maxAvailableDays = d;
        }
        
        if (maxAvailableDays >= requestedDuration) {
            document.getElementById('reserveDate').value = dateStr;
            const reserveObj = new Date(dateStr + 'T00:00:00');
            reserveObj.setDate(reserveObj.getDate() + requestedDuration);
            const autoReturnDateStr = formatLocalDate(reserveObj);
            
            const returnDateInput = document.getElementById('returnDate');
            if (returnDateInput) {
                returnDateInput.value = autoReturnDateStr;
            }
            
            updateReturnDateInputState();
            updateAvailableStock();
            updateOverlayPricesRealtime();
            document.getElementById('calendarModalOverlay').classList.remove('active');
            activeDateInputId = null;
        } else {
            // Partial duration or same day only available
            document.getElementById('reserveDate').value = dateStr;
            const maxReturnObj = new Date(dateStr + 'T00:00:00');
            maxReturnObj.setDate(maxReturnObj.getDate() + maxAvailableDays);
            const maxReturnStr = formatLocalDate(maxReturnObj);
            
            const returnDateInput = document.getElementById('returnDate');
            if (returnDateInput) {
                returnDateInput.value = maxReturnStr;
            }
            
            updateReturnDateInputState();
            updateAvailableStock();
            updateOverlayPricesRealtime();
            document.getElementById('calendarModalOverlay').classList.remove('active');
            activeDateInputId = null;

            if (firstConflictDate) {
                showNotification(`Return Date automatically set to ${formatDateNice(maxReturnStr)} because ${formatDateNice(firstConflictDate)} is booked.`, 3500);
            }
        }
    }

    // Handle Return Date selection with conflict checking
    function handleReturnDateSelection(dateStr) {
        const reserveDateStr = document.getElementById('reserveDate')?.value;
        const requestedDuration = (typeof window.currentRentalDurationDays !== 'undefined') ? window.currentRentalDurationDays : (<?php echo intval($rentalDurationDays); ?> || 10);
        const maxExtensionDays = (typeof window.currentMaxExtensionDays !== 'undefined') ? window.currentMaxExtensionDays : (<?php echo intval($maxExtensionDays); ?> || 7);
        const maxAllowedDays = requestedDuration + maxExtensionDays;
        
        // Strict safety check: If date is booked, DO NOT set!
        if (!dateStr || unavailableDates.includes(dateStr)) {
            showNotification('This date is fully booked and cannot be selected as Return Date.', 3000);
            return;
        }

        if (reserveDateStr) {
            if (dateStr < reserveDateStr) {
                showNotification('Return Date cannot be earlier than Reserve Date.', 3000);
                return;
            }

            const stdReturnObj = new Date(reserveDateStr + 'T00:00:00');
            stdReturnObj.setDate(stdReturnObj.getDate() + requestedDuration);
            const standardReturnDate = formatLocalDate(stdReturnObj);
            if (dateStr >= reserveDateStr && dateStr <= standardReturnDate) {
                showNotification(`Standard rental period (Same date to ${requestedDuration} days after) cannot be selected as Return Date.`, 3000);
                return;
            }
            
            const reserveObj = new Date(reserveDateStr + 'T00:00:00');
            const returnObj = new Date(dateStr + 'T00:00:00');
            const diffDays = Math.round((returnObj - reserveObj) / (1000 * 60 * 60 * 24));
            
            if (diffDays > maxAllowedDays) {
                showNotification(`Return Date cannot exceed the maximum allowed duration of ${maxAllowedDays} days (${requestedDuration} standard + ${maxExtensionDays} extension days).`, 3000);
                return;
            }

            for (let d = 1; d <= diffDays; d++) {
                const checkObj = new Date(reserveDateStr + 'T00:00:00');
                checkObj.setDate(checkObj.getDate() + d);
                const checkStr = formatLocalDate(checkObj);
                
                if (unavailableDates.includes(checkStr)) {
                    showNotification(`Cannot select ${formatDateNice(dateStr)} because ${formatDateNice(checkStr)} is fully booked.`, 3500);
                    return;
                }
            }
        }
        
        document.getElementById('returnDate').value = dateStr;
        updateAvailableStock();
        updateOverlayPricesRealtime();
        document.getElementById('calendarModalOverlay').classList.remove('active');
        activeDateInputId = null;
    }

    function showAvailabilityModal(data) {
        document.getElementById('availModalReserve').textContent = formatDateNice(data.reserveDate);
        document.getElementById('availModalRequested').textContent = data.requestedDays + ' Days';
        document.getElementById('availModalMaxReturn').textContent = `${formatDateNice(data.returnDate)} (${data.availableDays} Days)`;
        document.getElementById('availModalConflict').innerHTML = `<i class="fas fa-ban"></i> ${formatDateNice(data.conflictDate)}`;
        document.getElementById('availModalConfirmDays').textContent = data.availableDays + (data.availableDays === 1 ? ' day' : ' days');
        document.getElementById('availBtnDaysText').textContent = data.availableDays + (data.availableDays === 1 ? ' Day' : ' Days');
        
        document.getElementById('availabilityModalMsg').innerHTML = 
            `The standard rental period is <strong>${data.requestedDays} days</strong>, but this gown only has <strong>${data.availableDays} available days</strong> starting from your Reserve Date before being out of stock on <strong>${formatDateNice(data.conflictDate)}</strong>.`;
            
        const modal = document.getElementById('availabilityPromptModal');
        modal.style.display = 'flex';
    }

    function confirmAvailabilityAdjustment() {
        if (pendingAdjustment) {
            document.getElementById('reserveDate').value = pendingAdjustment.reserveDate;
            document.getElementById('returnDate').value = pendingAdjustment.returnDate;
            updateAvailableStock();
            pendingAdjustment = null;
        }
        document.getElementById('availabilityPromptModal').style.display = 'none';
        document.getElementById('calendarModalOverlay').classList.remove('active');
        activeDateInputId = null;
    }

    function closeAvailabilityModal(confirmed) {
        document.getElementById('availabilityPromptModal').style.display = 'none';
        pendingAdjustment = null;
    }

    // Fetch available stock for selected dates and limit + button
    function updateAvailableStock() {
        const reserveDate = document.getElementById('reserveDate')?.value;
        const returnDate = document.getElementById('returnDate')?.value;
        const reserveOverlay = document.getElementById('reserveOverlay');
        const gownId = reserveOverlay ? (reserveOverlay.getAttribute('data-gown-id') || '') : '';
        const productName = document.querySelector('#reserveOverlay .product-name-overlay')?.textContent?.trim() || '';
        
        let productCategory = 'Gown';
        const allProductNames = document.querySelectorAll('[id^="productName"]');
        for (let element of allProductNames) {
            if (element.textContent.trim() === productName) {
                const boxNumber = element.id.replace('productName', '');
                const categoryElement = document.getElementById(`productCategory${boxNumber}`);
                if (categoryElement) {
                    productCategory = categoryElement.textContent.trim();
                    break;
                }
            }
        }

        if (!reserveDate || !returnDate) return;

        const url = `get_available_stock.php?product_id=${gownId}&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}&reserve_date=${reserveDate}&return_date=${returnDate}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const available = parseInt(data.available);
                    window.currentStockQuantity = available;

                    // Update max on quantity input
                    const totalQuantityInput = document.getElementById('totalQuantity');
                    if (totalQuantityInput) {
                        totalQuantityInput.max = Math.max(1, available);
                    }

                    // Get current item count
                    const measurementsContainer = document.getElementById('measurementsContainer');
                    let measurementGroups = measurementsContainer ? measurementsContainer.querySelectorAll('.measurement-group') : [];
                    let currentCount = measurementGroups.length;

                    // If measurementsContainer was empty (0 item), re-generate Item 1 size boxes!
                    if (currentCount === 0) {
                        addMeasurementGroup(1);
                        measurementGroups = measurementsContainer.querySelectorAll('.measurement-group');
                        currentCount = measurementGroups.length;
                        if (totalQuantityInput) {
                            totalQuantityInput.value = 1;
                        }
                    }

                    // Keep at least Item 1 visible at all times so size boxes are always displayed
                    if (available > 0 && currentCount > available) {
                        for (let i = currentCount; i > available; i--) {
                            if (i > 1 && measurementGroups[i - 1]) {
                                measurementGroups[i - 1].remove();
                            }
                        }
                        if (totalQuantityInput) {
                            totalQuantityInput.value = available;
                        }
                        showNotification('Reduced to ' + available + ' item(s) - stock limit for these dates', 3000);
                    } else if (available === 0) {
                        // When stock is 0 for these dates, keep 1 size box visible and notify
                        showNotification('Selected dates are fully booked (0 available). Please pick another date.', 3500);
                    }

                    // Update + / - button states
                    const plusBtn = document.querySelector('.quantity-plus');
                    const minusBtn = document.querySelector('.quantity-minus');
                    const newCount = measurementsContainer.querySelectorAll('.measurement-group').length;

                    if (available <= 0 || newCount >= available) {
                        if (plusBtn) {
                            plusBtn.disabled = true;
                            plusBtn.style.opacity = '0.5';
                            plusBtn.style.cursor = 'not-allowed';
                        }
                    } else {
                        if (plusBtn) {
                            plusBtn.disabled = false;
                            plusBtn.style.opacity = '1';
                            plusBtn.style.cursor = 'pointer';
                        }
                    }

                    if (newCount > 1 && available > 1) {
                        if (minusBtn) {
                            minusBtn.disabled = false;
                            minusBtn.style.opacity = '1';
                            minusBtn.style.cursor = 'pointer';
                        }
                    } else {
                        if (minusBtn) {
                            minusBtn.disabled = true;
                            minusBtn.style.opacity = '0.5';
                            minusBtn.style.cursor = 'not-allowed';
                        }
                    }
                }
            })
            .catch(error => console.error('Error fetching available stock:', error));
    }

    // Function to load saved products
    function loadSavedProducts() {
        const container = document.getElementById('productsContainer');

        // Clear existing content
        container.innerHTML = '';

        // Check if user is logged in (prioritize PHP session over localStorage)
        const userLoggedIn = isLoggedIn || localStorage.getItem('isLoggedIn') === 'true';

        console.log('User logged in check:', {
            localStorage: localStorage.getItem('isLoggedIn'),
            session: isLoggedIn,
            combined: userLoggedIn
        });

        if (!userLoggedIn) {
            container.innerHTML = '<div style="text-align: center; padding: 50px; color: #666;">Please login to view saved products</div>';
            return;
        }

        // Use database products only when logged in to avoid duplication
        const allProducts = savedProductsFromDB;

        // Update localStorage with product count for cart badge
        localStorage.setItem('savedProducts', JSON.stringify(allProducts.map(p => ({
            id: p.product_id || p.id,
            name: p.product_name || p.name,
            category: p.product_category || p.category
        }))));

        // Create a login-box for each product
        setTimeout(() => {
            container.innerHTML = ''; // Clear loading message

            if (allProducts.length === 0) {
                // Show Lottie animation when no products are saved
                container.innerHTML = `
                    <div class="empty-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px 20px; width: 100%;">
                        <dotlottie-wc src="https://lottie.host/7a9e1682-3342-49d1-a823-511f6b3f1fdc/x7SG6ZMpXp.lottie" style="width: 280px; height: 280px; max-width: 90vw;" autoplay loop></dotlottie-wc>
                        <p style="color: #888888; margin-top: 16px; font-size: 16px; font-weight: 500; font-family: 'Poppins', sans-serif;">No saved products yet</p>
                    </div>
                `;
            } else {
                allProducts.forEach((product, index) => {
                    const productBox = createProductBox(product, index);
                    container.appendChild(productBox);
                });

                // Restore previous checked states if returning from product_reserve.php or overlay (respecting stock limits)
                const savedCheckedIds = JSON.parse(sessionStorage.getItem('checkedCartProductIds') || '[]');
                if (Array.isArray(savedCheckedIds) && savedCheckedIds.length > 0) {
                    const productCheckedCounts = {};
                    savedCheckedIds.forEach(id => {
                        const targetBox = document.querySelector(`.login-box[data-db-id="${id}"]`) ||
                                          document.querySelector(`.login-box[data-product-id="${id}"]`);
                        if (targetBox) {
                            const cb = targetBox.querySelector('.checkbox');
                            const gownId = targetBox.getAttribute('data-gown-id') || '';
                            const pName = (targetBox.querySelector('.product-name')?.textContent || '').trim().toLowerCase();
                            const pCat = (targetBox.querySelector('.product-category')?.textContent || '').trim().toLowerCase();
                            const pKey = (gownId && gownId !== '0') ? ('id_' + gownId) : (pName + '_' + pCat);
                            const stock = parseInt(targetBox.getAttribute('data-stock-quantity')) || 0;
                            const qty = parseInt(targetBox.getAttribute('data-quantity')) || 1;

                            if (stock > 0 && ((productCheckedCounts[pKey] || 0) + qty <= stock)) {
                                if (cb) cb.classList.add('checked');
                                productCheckedCounts[pKey] = (productCheckedCounts[pKey] || 0) + qty;
                            }
                        }
                    });
                }

                // Auto-check product if returning from overlay save/buy
                const autoCheckId = sessionStorage.getItem('autoCheckSavedProductId');
                if (autoCheckId) {
                    const targetCheckbox = document.querySelector(`.login-box[data-db-id="${autoCheckId}"] .checkbox`);
                    if (targetCheckbox) {
                        targetCheckbox.classList.add('checked');
                    }
                    sessionStorage.removeItem('autoCheckSavedProductId');
                }

                console.log(`Successfully loaded ${allProducts.length} saved products (database: ${savedProductsFromDB.length}, localStorage: ${localStorageProducts.length})`);

                // Update mobile app bar with initial total price (reflecting restored / auto-checked boxes)
                updateTotalPrice();
            }
        }, 100);
    }
    
    // Function to create a product box
    function createProductBox(product, index) {
        const boxNumber = index + 1;
        
        // Create main login-box div
        const loginBox = document.createElement('div');
        loginBox.className = 'login-box';

        // Remove navigation from entire login-box - navigation will be on left-box only

        // Create save-content div
        const saveContent = document.createElement('div');
        saveContent.className = 'save-content';
        
        // Create edit slide box
        const editSlideBox = document.createElement('div');
        editSlideBox.className = 'edit-slide-box';
        editSlideBox.id = `editSlideBox${boxNumber}`;
        editSlideBox.innerHTML = `
            <div class="delete-content" onclick="showDeleteConfirmation(${boxNumber}); event.stopPropagation();">
                <span>Delete</span>
            </div>
        `;
        
        // Create hi-container
        const hiContainer = document.createElement('div');
        hiContainer.className = 'hi-container';
        
        // Create left-box for clickable uploaded image display
        const leftBox = document.createElement('div');
        leftBox.className = 'left-box';
        leftBox.id = `modelContainer${boxNumber}`;

        // Make left-box clickable to go to product info
        const productId = product.product_id || product.id || '';
        if (productId) {
            leftBox.style.cursor = 'pointer';
            leftBox.onclick = (e) => {
                e.stopPropagation(); // Prevent triggering other click events
                if (loginBox.dataset.sliding === 'true') {
                    loginBox.dataset.sliding = '';
                    return;
                }
                console.log(`Navigating to index.php with product ID: ${productId}`);
                window.location.href = `index.php?id=${productId}`;
            };
        }

        // Display uploaded image if available, otherwise 3D model
        const modelPath = (product.model_path || product.modelPath || '').trim();
        const imagePath = (product.image_path || product.imagePath || product.image_path2 || product.image_path3 || product.image || '').trim();

        loginBox.setAttribute('data-model-path', modelPath);
        loginBox.setAttribute('data-image-path', imagePath);

        if (imagePath !== '') {
            leftBox.innerHTML = `
                <img src="${imagePath}"
                     alt="${product.product_name || product.name || 'Product'}"
                     style="width: 100%; height: 100%; object-fit: cover; object-position: top; border-radius: 6px; display: block;">
            `;
        } else if (modelPath !== '') {
            leftBox.innerHTML = `
                <model-viewer
                    src="${modelPath}"
                    camera-controls
                    auto-rotate
                    camera-orbit="auto 90deg auto"
                    disable-pan
                    disable-zoom
                    min-camera-orbit="auto 90deg auto"
                    max-camera-orbit="auto 90deg auto"
                    style="width: 100%; height: 100%; border-radius: 8px;">
                </model-viewer>
            `;
        } else {
            leftBox.innerHTML = `
                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #2a2a2a; border-radius: 6px; color: #666;">
                    <i class="fas fa-image" style="font-size: 1.6rem;"></i>
                </div>
            `;
        }
        
        // Create product-info div
        const productInfo = document.createElement('div');
        productInfo.className = 'product-info';
        const productName = product.product_name || product.name || '';
        const productCategory = product.product_category || product.category || '';
        const productSize = product.product_size || product.size || '';
        const productPrice = `₱${parseFloat(product.product_price || product.price || 0).toLocaleString(undefined, {maximumFractionDigits: 0})}`;
        const productStatus = product.product_status || 'saved';
        const purchasePrice = product.purchase_price || 0;
        const stockQuantity = product.stock_quantity || 0;

        
        
        // Format status for display
        let statusDisplay = '';
        if (productStatus === 'buy') {
            statusDisplay = '<span style="color: #28a745; font-weight: bold; font-size: 0.6rem;">BUY</span>';
        } else if (productStatus === 'reserve') {
            statusDisplay = '<span style="color: #007bff; font-weight: bold; font-size: 0.6rem;">RESERVE</span>';
        }
        // For 'saved' status, display nothing
        
        // Compute totals (use discounted rental price if any and factor in extension days)
        const totalQuantity = parseInt(product.quantity) || 1;
        const rentalPrice = parseFloat(product.product_price || product.price || 0);
        const sellingPrice = parseFloat(purchasePrice) || 0;
        const discountPercent = parseFloat(product.discount || 0) || 0;
        const hasDiscount = discountPercent > 0;
        const discountedRentalPrice = hasDiscount ? rentalPrice * (1 - discountPercent / 100) : rentalPrice;

        const stdDuration = (typeof window.currentRentalDurationDays !== 'undefined') ? window.currentRentalDurationDays : (<?php echo intval($rentalDurationDays); ?> || 10);
        const extDailyFee = (typeof window.currentExtensionPricePerDay !== 'undefined') ? window.currentExtensionPricePerDay : (<?php echo floatval($extensionPricePerDay); ?> || 100);
        let extraFeePerUnit = 0;
        if (productStatus !== 'buy' && product.reserve_date && product.return_date) {
            const rObj = new Date(product.reserve_date + 'T00:00:00');
            const retObj = new Date(product.return_date + 'T00:00:00');
            const diffDays = Math.round((retObj - rObj) / (1000 * 60 * 60 * 24));
            if (diffDays > stdDuration) {
                extraFeePerUnit = (diffDays - stdDuration) * extDailyFee;
            }
        }
        const effectiveUnitRental = discountedRentalPrice + extraFeePerUnit;
        const totalRentalValue = effectiveUnitRental * totalQuantity;
        const totalSellingValue = sellingPrice * totalQuantity;
        const formatPeso = (value) => `₱${parseFloat(value).toLocaleString(undefined, {maximumFractionDigits: 0})}`;

        // Format purchase price for display
        let purchasePriceDisplay = '';
        if (purchasePrice > 0 && productStatus !== 'reserve') {
            if (productStatus === 'buy') {
                // For buy status, show unit selling price
                purchasePriceDisplay = `<div class="product-purchase-price" id="productPurchasePrice${boxNumber}" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: left;"><span style="color: red;">₱${sellingPrice.toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>`;
            } else {
                // For saved status, show only the number
                purchasePriceDisplay = `<div class="product-purchase-price" id="productPurchasePrice${boxNumber}" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: left;"><span style="color: red;">₱${parseFloat(purchasePrice).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>`;
            }
        } else {
            purchasePriceDisplay = `<div class="product-purchase-price" id="productPurchasePrice${boxNumber}" style="display: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: left;"><span style="color: red;">₱0</span></div>`;
        }

        // Format stock for display
        let stockDisplay = '';
        if (stockQuantity > 0) {
            stockDisplay = `<div class="product-stock" id="productStock${boxNumber}" style="color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: left;">Stock: ${stockQuantity}</div>`;
        }

        // Hide price if product status is 'buy'
        let priceDisplay = '';
        if (productStatus === 'buy') {
            priceDisplay = `<div class="product-price" id="productPrice${boxNumber}" style="display: none;">${formatPeso(discountedRentalPrice)}</div>`;
        } else {
            priceDisplay = `
                <div class="product-price-row">
                    <div class="product-price" id="productPrice${boxNumber}">${formatPeso(discountedRentalPrice)}</div>
                    ${hasDiscount ? `<div class="product-original-price" id="productOriginalPrice${boxNumber}">${formatPeso(rentalPrice)}</div>` : `<div class="product-original-price" id="productOriginalPrice${boxNumber}" style="display: none;"></div>`}
                </div>
            `;
        }

        productInfo.innerHTML = `
            <div class="product-name" id="productName${boxNumber}">${productName}</div>
            <div class="product-category" id="productCategory${boxNumber}" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${productCategory}</div>
            <div class="product-size" id="productSize${boxNumber}" style="display: none;">${productSize}</div>
            ${priceDisplay}
            ${purchasePriceDisplay}
            ${stockDisplay}
        `;

        // Create checkbox
        const checkbox = document.createElement('div');
        checkbox.className = 'checkbox';
        checkbox.id = `productCheckbox${boxNumber}`;
        checkbox.setAttribute('onclick', `toggleCheckbox(${boxNumber}); event.stopPropagation();`);
        checkbox.innerHTML = '<i class="fas fa-check"></i>';

        // Create checkbox container
        const checkboxContainer = document.createElement('div');
        checkboxContainer.className = 'checkbox-container';
        checkboxContainer.innerHTML = `
            <div class="product-status" id="productStatus${boxNumber}">${statusDisplay}</div>
            <span class="edit-text" onclick="goToReserve(${boxNumber}); event.stopPropagation();">Customize</span>
            <div class="quantity-box" id="quantityBox${boxNumber}" data-stock-quantity="${stockQuantity}">
            </div>
        `;
        
        // Assemble the box
        hiContainer.appendChild(checkbox);
        hiContainer.appendChild(leftBox);
        hiContainer.appendChild(productInfo);
        hiContainer.appendChild(checkboxContainer);

        saveContent.appendChild(editSlideBox);
        saveContent.appendChild(hiContainer);

        loginBox.appendChild(saveContent);

        // Slide-to-delete gesture
        let startX = 0, startY = 0, currentX = 0, isDragging = false;
        const slideThreshold = 50;
        const tapThreshold = 10;

        saveContent.addEventListener('touchstart', function(e) {
            if (e.target.closest('.checkbox, .edit-text, .delete-content')) return;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            currentX = 0;
            isDragging = true;
        }, { passive: true });

        saveContent.addEventListener('touchmove', function(e) {
            if (!isDragging) return;
            const x = e.touches[0].clientX;
            const y = e.touches[0].clientY;
            currentX = x - startX;
            const deltaY = y - startY;
            if (currentX < 0 && Math.abs(currentX) > Math.abs(deltaY)) {
                e.preventDefault();
            }
        }, { passive: false });

        saveContent.addEventListener('touchend', function(e) {
            if (!isDragging) return;
            isDragging = false;
            if (Math.abs(currentX) > tapThreshold) {
                loginBox.dataset.sliding = 'true';
            }
            if (currentX < -slideThreshold) {
                document.querySelectorAll('.edit-slide-box.active').forEach(box => {
                    if (box !== editSlideBox) box.classList.remove('active');
                });
                editSlideBox.classList.add('active');
            } else if (currentX > slideThreshold) {
                editSlideBox.classList.remove('active');
            }
        });

        // Create footer line with total items and total value
        const loginBoxFooter = document.createElement('div');
        loginBoxFooter.className = 'login-box-footer';
        const displayTotal = productStatus === 'buy' ? totalSellingValue : totalRentalValue;
        loginBoxFooter.innerHTML = `
            <span class="box-total">Total ${totalQuantity} Items</span>
            <span class="box-total-price">₱${displayTotal.toLocaleString(undefined, {maximumFractionDigits: 0})}</span>
        `;
        loginBox.appendChild(loginBoxFooter);

        // Store product ID for deletion and stock verification
        loginBox.setAttribute('data-product-id', product.id || index);
        loginBox.setAttribute('data-db-id', product.id || '');
        loginBox.setAttribute('data-gown-id', product.product_id || '');
        loginBox.setAttribute('data-stock-quantity', stockQuantity);

        // Store live unit prices and discount
        loginBox.setAttribute('data-unit-rental-price', rentalPrice);
        loginBox.setAttribute('data-unit-discounted-rental-price', discountedRentalPrice);
        loginBox.setAttribute('data-effective-unit-rental', effectiveUnitRental);
        loginBox.setAttribute('data-unit-selling-price', sellingPrice);
        loginBox.setAttribute('data-discount', discountPercent);
        loginBox.setAttribute('data-status', productStatus);
        loginBox.setAttribute('data-reserve-date', product.reserve_date || '');
        loginBox.setAttribute('data-return-date', product.return_date || '');

        // Store computed amounts so the app bar totals always match this box
        loginBox.setAttribute('data-quantity', totalQuantity);
        loginBox.setAttribute('data-line-total', displayTotal);
        loginBox.setAttribute('data-line-saved', productStatus === 'buy' ? 0 : (rentalPrice - discountedRentalPrice) * totalQuantity);
        
        return loginBox;
    }
    
    // Update price display based on quantity
    function updatePriceWithQuantity(boxNumber, quantity) {
        const priceElement = document.getElementById(`productPrice${boxNumber}`);
        const productName = document.getElementById(`productName${boxNumber}`).textContent;
        const productCategory = document.getElementById(`productCategory${boxNumber}`).textContent;

        // Find the original price from saved products
        const savedProductsFromDB = <?php echo json_encode($savedProducts); ?>;
        let originalPrice = 0;
        let discountPercent = 0;
        for (const product of savedProductsFromDB) {
            if (product.product_name === productName && product.product_category === productCategory) {
                originalPrice = parseFloat(product.product_price || product.price || 0);
                discountPercent = parseFloat(product.discount || 0) || 0;
                break;
            }
        }

        // Calculate new price based on quantity (apply discount if any)
        const unitPrice = discountPercent > 0 ? originalPrice * (1 - discountPercent / 100) : originalPrice;
        const newPrice = unitPrice * quantity;
        priceElement.textContent = `₱${newPrice.toLocaleString(undefined, {maximumFractionDigits: 0})}`;

        // Keep the slashed original price in sync with quantity
        const originalPriceElement = priceElement.parentElement
            ? priceElement.parentElement.querySelector('.product-original-price')
            : null;
        if (originalPriceElement) {
            originalPriceElement.textContent = `₱${(originalPrice * quantity).toLocaleString(undefined, {maximumFractionDigits: 0})}`;
        }

        // Update total price
        updateTotalPrice();
    }

    // Toggle checkbox function (enforces stock limits per product)
    function toggleCheckbox(boxNumber) {
        event.stopPropagation();

        const checkbox = document.getElementById(`productCheckbox${boxNumber}`);
        const loginBox = checkbox ? checkbox.closest('.login-box') : null;
        if (!loginBox) return;

        if (checkbox.classList.contains('checked')) {
            // Unchecking is always allowed
            checkbox.classList.remove('checked');
            updateTotalPrice();
            return;
        }

        // Product data & stock verification
        const productName = (document.getElementById(`productName${boxNumber}`)?.textContent || '').trim();
        const productCategory = (document.getElementById(`productCategory${boxNumber}`)?.textContent || '').trim();
        const savedProductId = loginBox.getAttribute('data-db-id') || '';
        const gownId = loginBox.getAttribute('data-gown-id') || '';
        const stockQuantity = parseInt(loginBox.getAttribute('data-stock-quantity')) || 0;
        const thisBoxQuantity = parseInt(loginBox.getAttribute('data-quantity')) || 1;

        // Check if out of stock
        if (stockQuantity <= 0) {
            showNotification('This product is out of stock', 3000);
            return;
        }

        // Calculate how many items of THIS SAME PRODUCT are already checked in other boxes
        const productKey = (gownId && gownId !== '0')
            ? ('id_' + gownId)
            : (productName.toLowerCase() + '_' + productCategory.toLowerCase());

        let alreadyCheckedCount = 0;
        document.querySelectorAll('.login-box').forEach(otherBox => {
            const otherCheckbox = otherBox.querySelector('.checkbox');
            if (otherBox !== loginBox && otherCheckbox && otherCheckbox.classList.contains('checked')) {
                const otherGownId = otherBox.getAttribute('data-gown-id') || '';
                const otherName = (otherBox.querySelector('.product-name')?.textContent || '').trim().toLowerCase();
                const otherCategory = (otherBox.querySelector('.product-category')?.textContent || '').trim().toLowerCase();
                const otherKey = (otherGownId && otherGownId !== '0')
                    ? ('id_' + otherGownId)
                    : (otherName + '_' + otherCategory);

                if (otherKey === productKey) {
                    const otherQty = parseInt(otherBox.getAttribute('data-quantity')) || 1;
                    alreadyCheckedCount += otherQty;
                }
            }
        });

        if (alreadyCheckedCount + thisBoxQuantity > stockQuantity) {
            const availableRemaining = Math.max(0, stockQuantity - alreadyCheckedCount);
            if (availableRemaining === 0) {
                showNotification(`Stock limit reached (${stockQuantity} stock only)`, 3000);
            } else {
                showNotification(`Only ${availableRemaining} more item(s) available (Stock: ${stockQuantity})`, 3000);
            }
            return;
        }

        // Fetch reservation details to check if product has values
        fetch('save_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_reservation&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}&saved_product_id=${savedProductId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.reservation) {
                const reservation = data.reservation;
                const isBuy = (reservation.product_status === 'buy');
                const hasValidDates = isBuy ? !!reservation.reserve_date : (!!reservation.reserve_date && !!reservation.return_date);
                if (hasValidDates) {
                    checkbox.classList.add('checked');
                } else {
                    showNotification('Edit details', 3000);
                    setTimeout(() => {
                        goToReserve(boxNumber);
                    }, 1000);
                }
            } else {
                showNotification('Add details first.', 3000);
                setTimeout(() => {
                    goToReserve(boxNumber);
                }, 1000);
            }
            updateTotalPrice();
        })
        .catch(error => {
            console.error('Error checking reservation details:', error);
            showNotification('Error checking reservation details', 2000);
        });
    }
    
    // Toggle all product checkboxes from app bar (respects stock limits per product)
    function toggleSelectAll() {
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const isChecked = selectAllCheckbox.classList.contains('checked');
        const loginBoxes = document.querySelectorAll('.login-box');

        if (isChecked) {
            loginBoxes.forEach(box => {
                const cb = box.querySelector('.checkbox');
                if (cb) cb.classList.remove('checked');
            });
            selectAllCheckbox.classList.remove('checked');
        } else {
            const productCheckedCounts = {};
            let reachedStockLimit = false;

            loginBoxes.forEach(box => {
                const cb = box.querySelector('.checkbox');
                if (!cb) return;

                const gownId = box.getAttribute('data-gown-id') || '';
                const productName = (box.querySelector('.product-name')?.textContent || '').trim().toLowerCase();
                const productCategory = (box.querySelector('.product-category')?.textContent || '').trim().toLowerCase();
                const productKey = (gownId && gownId !== '0')
                    ? ('id_' + gownId)
                    : (productName + '_' + productCategory);

                const stockQuantity = parseInt(box.getAttribute('data-stock-quantity')) || 0;
                const boxQuantity = parseInt(box.getAttribute('data-quantity')) || 1;

                if (stockQuantity <= 0) {
                    cb.classList.remove('checked');
                    reachedStockLimit = true;
                    return;
                }

                const currentCount = productCheckedCounts[productKey] || 0;
                if (currentCount + boxQuantity <= stockQuantity) {
                    cb.classList.add('checked');
                    productCheckedCounts[productKey] = currentCount + boxQuantity;
                } else {
                    cb.classList.remove('checked');
                    reachedStockLimit = true;
                }
            });

            if (reachedStockLimit) {
                showNotification('Some items were skipped due to stock limits', 3000);
            }
        }

        updateTotalPrice();
    }
    
    // Show reserve overlay (similar to product_info.php)
    function showReserveOverlay(boxNumber) {
        // Get product information for the clicked box
        const nameElement = document.getElementById(`productName${boxNumber}`);
        const categoryElement = document.getElementById(`productCategory${boxNumber}`);
        const priceElement = document.getElementById(`productPrice${boxNumber}`);
        const modelContainer = document.getElementById(`modelContainer${boxNumber}`);
        const statusElement = document.getElementById(`productStatus${boxNumber}`);
        
        if (nameElement && categoryElement && priceElement && modelContainer) {
            const loginBox = nameElement.closest('.login-box');
            const savedProductId = loginBox ? (loginBox.getAttribute('data-db-id') || '') : '';
            const gownProductId = loginBox ? (loginBox.getAttribute('data-gown-id') || '') : '';
            const reserveOverlayElement = document.getElementById('reserveOverlay');
            if (reserveOverlayElement) {
                reserveOverlayElement.setAttribute('data-saved-product-id', savedProductId);
                reserveOverlayElement.setAttribute('data-target-box-number', boxNumber);
                reserveOverlayElement.setAttribute('data-gown-id', gownProductId);
            }

            const modelViewer = modelContainer.querySelector('model-viewer');
            const imgElement = modelContainer.querySelector('img');
            const modelPath = modelViewer ? (modelViewer.getAttribute('src') || '') : '';
            const imagePath = imgElement ? (imgElement.getAttribute('src') || '') : '';
            const productName = nameElement.textContent;
            const productCategory = categoryElement.textContent;
            const productPrice = priceElement.textContent;
            
            // Extract live unit rental and selling prices from loginBox or saved products
            let unitRental = loginBox ? parseFloat(loginBox.getAttribute('data-unit-discounted-rental-price') || loginBox.getAttribute('data-unit-rental-price')) : 0;
            if (!unitRental || isNaN(unitRental) || unitRental <= 0) {
                unitRental = parseFloat(String(productPrice).replace(/[^0-9.]/g, '')) || 0;
            }
            let unitSelling = loginBox ? parseFloat(loginBox.getAttribute('data-unit-selling-price')) : 0;
            let discountPercent = loginBox ? parseFloat(loginBox.getAttribute('data-discount')) || 0 : 0;

            if (!unitSelling || isNaN(unitSelling)) {
                for (const prod of (window.savedProductsLive || savedProductsFromDB)) {
                    if (prod.product_name === productName && prod.product_category === productCategory) {
                        unitSelling = parseFloat(prod.purchase_price) || 0;
                        if (!unitRental) {
                            const disc = parseFloat(prod.discount) || 0;
                            const orig = parseFloat(prod.product_price || prod.price || 0);
                            unitRental = disc > 0 ? orig * (1 - disc / 100) : orig;
                        }
                        break;
                    }
                }
            }

            window.currentOverlayUnitRentalPrice = unitRental || 0;
            window.currentOverlayUnitSellingPrice = unitSelling || 0;
            window.currentOverlayDiscountPercent = discountPercent;

            if (overlayNameElement) overlayNameElement.textContent = productName;
            if (typeof updateOverlayPricesRealtime === 'function') {
                updateOverlayPricesRealtime();
            } else {
                if (overlayPriceElement) overlayPriceElement.innerHTML = `<span style="color: gray; font-size: 0.8rem;">Rental Price:</span> ₱${window.currentOverlayUnitRentalPrice.toLocaleString(undefined, {maximumFractionDigits: 0})}`;
                if (overlayPurchasePriceElement && window.currentOverlayUnitSellingPrice > 0) {
                    overlayPurchasePriceElement.innerHTML = `Selling Price: <span style="color: red;">₱${window.currentOverlayUnitSellingPrice.toLocaleString(undefined, {maximumFractionDigits: 0})}</span>`;
                    overlayPurchasePriceElement.style.display = 'block';
                } else if (overlayPurchasePriceElement) {
                    overlayPurchasePriceElement.style.display = 'none';
                }
            }
            if (overlayStockElement && stockQuantity > 0) {
                overlayStockElement.textContent = `Stock: ${stockQuantity}`;
            } else if (overlayStockElement) {
                overlayStockElement.textContent = 'Stock: Available';
            }

            // Fetch product status from database
            fetch('save_product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_status&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}&saved_product_id=${savedProductId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.product_status) {
                    setReservationType(data.product_status === 'buy' ? 'buy' : 'reserve');
                } else {
                    setReservationType('reserve');
                }
            })
            .catch(error => {
                console.error('Error fetching product status:', error);
                setReservationType('reserve');
            });
        }
        
        document.getElementById('topOverlay').style.display = 'block';
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            document.getElementById('reserveOverlay').style.display = 'block';
        }, 100);
    }
    
    // Close reserve overlay
    function closeReserveOverlay() {
        document.getElementById('reserveOverlay').style.display = 'none';
        document.getElementById('topOverlay').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    // Save state of checked product checkboxes into sessionStorage
    function saveCheckedBoxesState() {
        const checkedBoxes = document.querySelectorAll('.login-box .checkbox.checked');
        const checkedIds = [];
        checkedBoxes.forEach(cb => {
            const loginBox = cb.closest('.login-box');
            if (loginBox) {
                const id = loginBox.getAttribute('data-db-id') || loginBox.getAttribute('data-product-id');
                if (id) checkedIds.push(id);
            }
        });
        sessionStorage.setItem('checkedCartProductIds', JSON.stringify(checkedIds));
    }

    // Real-time synchronization of rental & selling prices from the database
    function syncLiveProductPrices(callback) {
        if (!isLoggedIn) return;

        fetch('save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=get_live_cart_prices'
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.success && Array.isArray(data.products)) {
                window.savedProductsLive = data.products;

                // Loop through returned products and update cards on the page
                data.products.forEach(liveItem => {
                    const savedId = liveItem.saved_product_id;
                    const loginBox = document.querySelector(`.login-box[data-db-id="${savedId}"]`) ||
                                     document.querySelector(`.login-box[data-product-id="${savedId}"]`);
                    if (!loginBox) return;

                    const rentalUnit = parseFloat(liveItem.rental_price) || 0;
                    const disc = parseFloat(liveItem.discount) || 0;
                    const discRentalUnit = parseFloat(liveItem.discounted_rental_price) || rentalUnit;
                    const sellingUnit = parseFloat(liveItem.purchase_price) || 0;
                    const stock = parseInt(liveItem.stock_quantity) || 0;
                    const qty = parseInt(liveItem.quantity) || parseInt(loginBox.getAttribute('data-quantity')) || 1;
                    const status = liveItem.product_status || loginBox.getAttribute('data-status') || 'saved';

                    const reserveDateVal = liveItem.reserve_date || loginBox.getAttribute('data-reserve-date') || '';
                    const returnDateVal = liveItem.return_date || loginBox.getAttribute('data-return-date') || '';

                    const stdDuration = (typeof window.currentRentalDurationDays !== 'undefined') ? window.currentRentalDurationDays : (<?php echo intval($rentalDurationDays); ?> || 10);
                    const extDailyFee = (typeof window.currentExtensionPricePerDay !== 'undefined') ? window.currentExtensionPricePerDay : (<?php echo floatval($extensionPricePerDay); ?> || 100);

                    let extraFeePerUnit = 0;
                    if (status !== 'buy' && reserveDateVal && returnDateVal) {
                        const rObj = new Date(reserveDateVal + 'T00:00:00');
                        const retObj = new Date(returnDateVal + 'T00:00:00');
                        const diffDays = Math.round((retObj - rObj) / (1000 * 60 * 60 * 24));
                        if (diffDays > stdDuration) {
                            extraFeePerUnit = (diffDays - stdDuration) * extDailyFee;
                        }
                    }

                    const effectiveUnitRental = discRentalUnit + extraFeePerUnit;
                    const lineTotal = status === 'buy' ? (sellingUnit * qty) : (effectiveUnitRental * qty);
                    const lineSaved = status === 'buy' ? 0 : ((rentalUnit - discRentalUnit) * qty);

                    // Update login-box attributes
                    loginBox.setAttribute('data-unit-rental-price', rentalUnit);
                    loginBox.setAttribute('data-unit-discounted-rental-price', discRentalUnit);
                    loginBox.setAttribute('data-effective-unit-rental', effectiveUnitRental);
                    loginBox.setAttribute('data-unit-selling-price', sellingUnit);
                    loginBox.setAttribute('data-discount', disc);
                    loginBox.setAttribute('data-stock-quantity', stock);
                    loginBox.setAttribute('data-quantity', qty);
                    loginBox.setAttribute('data-status', status);
                    if (reserveDateVal) loginBox.setAttribute('data-reserve-date', reserveDateVal);
                    if (returnDateVal) loginBox.setAttribute('data-return-date', returnDateVal);
                    loginBox.setAttribute('data-line-total', lineTotal);
                    loginBox.setAttribute('data-line-saved', lineSaved);

                    // Update DOM price elements
                    const priceEl = loginBox.querySelector('.product-price');
                    if (priceEl && status !== 'buy') {
                        priceEl.textContent = `₱${discRentalUnit.toLocaleString(undefined, {maximumFractionDigits: 0})}`;
                    }

                    const origPriceEl = loginBox.querySelector('.product-original-price');
                    if (origPriceEl && status !== 'buy') {
                        if (disc > 0) {
                            origPriceEl.textContent = `₱${rentalUnit.toLocaleString(undefined, {maximumFractionDigits: 0})}`;
                            origPriceEl.style.display = 'block';
                        } else {
                            origPriceEl.style.display = 'none';
                        }
                    }

                    const purchasePriceEl = loginBox.querySelector('.product-purchase-price');
                    if (purchasePriceEl) {
                        if (sellingUnit > 0 && status !== 'reserve') {
                            purchasePriceEl.innerHTML = `<span style="color: red;">₱${sellingUnit.toLocaleString(undefined, {maximumFractionDigits: 0})}</span>`;
                            purchasePriceEl.style.display = 'block';
                        } else {
                            purchasePriceEl.style.display = 'none';
                        }
                    }

                    const stockEl = loginBox.querySelector('.product-stock');
                    if (stockEl) {
                        stockEl.textContent = stock > 0 ? `Stock: ${stock}` : 'Out of stock';
                    }

                    // Update footer total
                    const footerTotalEl = loginBox.querySelector('.box-total-price');
                    if (footerTotalEl) {
                        footerTotalEl.textContent = `₱${lineTotal.toLocaleString(undefined, {maximumFractionDigits: 0})}`;
                    }
                    const footerItemsEl = loginBox.querySelector('.box-total');
                    if (footerItemsEl) {
                        footerItemsEl.textContent = `Total ${qty} Items`;
                    }

                    // If overlay is currently open on this item, sync overlay prices too
                    const overlay = document.getElementById('reserveOverlay');
                    if (overlay && overlay.style.display !== 'none' && overlay.getAttribute('data-saved-product-id') == savedId) {
                        window.currentOverlayUnitRentalPrice = discRentalUnit;
                        window.currentOverlayUnitSellingPrice = sellingUnit;
                        window.currentOverlayDiscountPercent = disc;
                        window.currentStockQuantity = stock;

                        const overlayStockEl = document.querySelector('#reserveOverlay .product-stock-overlay');
                        if (overlayStockEl) {
                            overlayStockEl.textContent = stock > 0 ? `Stock: ${stock}` : 'Out of stock';
                        }

                        if (typeof updateOverlayPricesRealtime === 'function') {
                            updateOverlayPricesRealtime();
                        }
                    }
                });

                // Update app bar total
                updateTotalPrice();

                if (typeof callback === 'function') callback();
            }
        })
        .catch(err => console.error('Price sync failed:', err));
    }

    // Auto sync live prices every 6 seconds and when tab becomes active
    setInterval(syncLiveProductPrices, 6000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) syncLiveProductPrices();
    });

    // Calculate and update total price of checked products
    function updateTotalPrice() {
        let totalPrice = 0;
        let totalDiscount = 0;

        // Sum the amounts already computed for each checked box so the app bar
        // always matches the box footers and the reserve page
        const checkedBoxes = document.querySelectorAll('.login-box .checkbox.checked');
        checkedBoxes.forEach(checkbox => {
            const loginBox = checkbox.closest('.login-box');
            if (!loginBox) return;

            const lineTotal = parseFloat(loginBox.getAttribute('data-line-total')) || 0;
            const lineSaved = parseFloat(loginBox.getAttribute('data-line-saved')) || 0;

            totalPrice += lineTotal;
            totalDiscount += lineSaved;
        });
        
        // Update mobile app bar with total price
        const totalPriceElement = document.getElementById('totalPrice');
        if (totalPriceElement) {
            totalPriceElement.textContent = `₱${totalPrice.toLocaleString(undefined, {maximumFractionDigits: 0})}`;
        }

        // Update checkout count
        const checkoutCountElement = document.getElementById('checkoutCount');
        if (checkoutCountElement) {
            checkoutCountElement.textContent = `(${checkedBoxes.length})`;
        }

        const totalDiscountElement = document.getElementById('totalDiscount');
        if (totalDiscountElement) {
            totalDiscountElement.textContent = `₱${totalDiscount.toLocaleString(undefined, {maximumFractionDigits: 0})}`;
        }

        // Sync Select All checkbox state
        const allProductBoxes = document.querySelectorAll('.login-box .checkbox');
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        if (selectAllCheckbox) {
            if (allProductBoxes.length > 0 && allProductBoxes.length === checkedBoxes.length) {
                selectAllCheckbox.classList.add('checked');
            } else {
                selectAllCheckbox.classList.remove('checked');
            }
        }

        saveCheckedBoxesState();

        console.log(`Updated total price: ₱${totalPrice.toLocaleString(undefined, {maximumFractionDigits: 0})}`);
    }
    
    // Toggle edit box function (updated for dynamic boxes)
    function toggleEditBox(boxNumber) {
        const editBox = document.getElementById(`editSlideBox${boxNumber}`);
        const editButton = event.target;
        
        editBox.classList.toggle('active');
        
        // Toggle text between Edit and Done
        if (editBox.classList.contains('active')) {
            editButton.textContent = 'Done';
        } else {
            editButton.textContent = 'Edit';
        }
    }
    
    // Delete product function
    function deleteProduct(boxNumber) {
        const loginBox = document.querySelector(`#productsContainer .login-box:nth-child(${boxNumber})`);
        const dbId = loginBox.getAttribute('data-db-id');

        const productName = loginBox.querySelector('.product-name').textContent;
        const productCategory = loginBox.querySelector('.product-category').textContent;

        if (dbId && dbId !== '') {
            // Delete from database
            deleteFromDatabase(productName, productCategory, true, dbId);
        } else {
            // Fallback to localStorage
            deleteFromLocalStorage(boxNumber);
        }
    }
    
    // Delete from database
    function deleteFromDatabase(productName, productCategory, reload = true, savedProductId = '') {
        return fetch('save_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_product&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}&saved_product_id=${savedProductId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (reload) {
                    // Reload the products display
                    location.reload();
                }
            } else {
                alert('Failed to delete product: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error deleting product:', error);
            alert('Error deleting product');
        });
    }
    
    // Delete from localStorage (fallback)
    function deleteFromLocalStorage(boxNumber) {
        const savedProducts = JSON.parse(localStorage.getItem('savedProducts') || '[]');
        const indexToRemove = boxNumber - 1;
        
        if (indexToRemove >= 0 && indexToRemove < savedProducts.length) {
            savedProducts.splice(indexToRemove, 1);
            localStorage.setItem('savedProducts', JSON.stringify(savedProducts));
            
            // Update total price after deletion
            updateTotalPrice();
            
            loadSavedProducts();
        }
    }
    
    // Toggle edit mode and app bar action
    function toggleEditMode() {
        isEditMode = !isEditMode;
        const topEditBtn = document.getElementById('topEditButton');
        const actionBtn = document.getElementById('appBarActionButton');
        const appBarTotal = document.getElementById('appBarTotalSummary');
        
        if (isEditMode) {
            if (topEditBtn) topEditBtn.textContent = 'Done';
            if (actionBtn) {
                actionBtn.innerHTML = '<span>Delete</span>';
                actionBtn.onclick = showDeleteSelectedConfirmation;
            }
            if (appBarTotal) appBarTotal.style.display = 'none';
        } else {
            if (topEditBtn) topEditBtn.textContent = 'Edit';
            if (actionBtn) {
                actionBtn.innerHTML = '<span>Check Out <span id="checkoutCount">(0)</span></span>';
                actionBtn.onclick = goToProductReserve;
                updateTotalPrice();
            }
            if (appBarTotal) appBarTotal.style.display = 'inline-flex';
        }
    }
    
    // Show confirmation before deleting selected products
    function showDeleteSelectedConfirmation() {
        const checkedBoxes = document.querySelectorAll('.login-box .checkbox.checked');
        if (checkedBoxes.length === 0) {
            showNotification('Please select items to delete', 2000);
            return;
        }
        document.getElementById('batchDeleteConfirmOverlay').classList.add('active');
    }

    // Delete selected products from app bar button
    function deleteSelectedProducts() {
        const checkedBoxes = document.querySelectorAll('.login-box .checkbox.checked');
        if (checkedBoxes.length === 0) {
            showNotification('Please select items to delete', 2000);
            return;
        }
        showDeleteSelectedConfirmation();
    }
    
    function confirmDeleteSelected() {
        document.getElementById('batchDeleteConfirmOverlay').classList.remove('active');
        executeDeleteSelectedProducts();
    }
    
    function cancelDeleteSelected() {
        document.getElementById('batchDeleteConfirmOverlay').classList.remove('active');
    }
    
    // Delete selected products
    function executeDeleteSelectedProducts() {
        const checkedBoxes = document.querySelectorAll('.login-box .checkbox.checked');
        const dbPromises = [];
        checkedBoxes.forEach(checkbox => {
            const loginBox = checkbox.closest('.login-box');
            if (!loginBox) return;
            
            const dbId = loginBox.getAttribute('data-db-id');
            const productName = loginBox.querySelector('.product-name')?.textContent || '';
            const productCategory = loginBox.querySelector('.product-category')?.textContent || '';
            
            if (dbId && dbId !== '') {
                dbPromises.push(deleteFromDatabase(productName, productCategory, false, dbId));
            }
        });
        
        if (dbPromises.length > 0) {
            Promise.all(dbPromises).then(() => {
                location.reload();
            });
        }
    }
    
    // Go to reserve page with checked products
    function goToReserve(boxNumber = null) {
        // Use provided boxNumber or get first available product
        let targetBoxNumber = boxNumber;

        if (!targetBoxNumber) {
            const checkedBoxes = document.querySelectorAll('.login-box .checkbox.checked');
            targetBoxNumber = 1; // Default to first product

            if (checkedBoxes.length > 0) {
                // Get the box number of the first checked product
                const firstCheckedId = checkedBoxes[0].id;
                targetBoxNumber = firstCheckedId.replace('productCheckbox', '');
            }
        }

        // Get quantity for this product (default to 1 since we removed the quantity-number display)
        const quantity = 1;

        // Get the saved product ID from the login box using quantityBox
        const quantityBox = document.getElementById(`quantityBox${targetBoxNumber}`);
        const loginBox = quantityBox ? quantityBox.closest('.login-box') : null;
        const savedProductId = loginBox ? loginBox.getAttribute('data-db-id') || '' : '';

        // Remember which cart row this overlay is editing so duplicates stay independent
        const reserveOverlayElement = document.getElementById('reserveOverlay');
        if (reserveOverlayElement) reserveOverlayElement.setAttribute('data-saved-product-id', savedProductId);

        // Store gown product_id for calendar conflict checking
        const gownProductId = loginBox ? loginBox.getAttribute('data-gown-id') || '' : '';
        if (reserveOverlayElement) reserveOverlayElement.setAttribute('data-gown-id', gownProductId);

        // Immediately sync Type selection (Buy vs Reserve) based on the product's saved status
        const currentProductStatus = loginBox ? (loginBox.getAttribute('data-status') || 'saved') : 'saved';
        if (currentProductStatus === 'buy') {
            setReservationType('buy');
        } else {
            setReservationType('reserve');
        }

        // Update overlay with product information
        const nameElement = document.getElementById(`productName${targetBoxNumber}`);
        const categoryElement = document.getElementById(`productCategory${targetBoxNumber}`);
        const priceElement = document.getElementById(`productPrice${targetBoxNumber}`);
        const modelContainer = document.getElementById(`modelContainer${targetBoxNumber}`);

        if (nameElement && categoryElement && priceElement && modelContainer) {
            const modelViewer = modelContainer.querySelector('model-viewer');
            const imgElement = modelContainer.querySelector('img');
            const modelPath = (modelViewer ? (modelViewer.getAttribute('src') || '') : '') || (loginBox ? (loginBox.getAttribute('data-model-path') || '') : '');
            const imagePath = (imgElement ? (imgElement.getAttribute('src') || '') : '') || (loginBox ? (loginBox.getAttribute('data-image-path') || '') : '');
            const productName = nameElement.textContent;
            const productCategory = categoryElement.textContent;
            const productPrice = priceElement.textContent;

            // Update overlay with product information
            const overlayModelDiv = document.querySelector('#reserveOverlay .reserve-model');
            const overlayModelViewer = document.querySelector('#reserveOverlay model-viewer');
            let overlayImg = document.querySelector('#reserveOverlay .reserve-model-image');
            const overlayNameElement = document.querySelector('#reserveOverlay .product-name-overlay');
            const overlayPriceElement = document.querySelector('#reserveOverlay .product-price-overlay');
            const overlayPurchasePriceElement = document.querySelector('#reserveOverlay .product-purchase-price-overlay');
            const overlayStockElement = document.querySelector('#reserveOverlay .product-stock-overlay');

            if (imagePath) {
                if (overlayModelViewer) overlayModelViewer.style.display = 'none';
                if (!overlayImg && overlayModelDiv) {
                    overlayImg = document.createElement('img');
                    overlayImg.className = 'reserve-model-image';
                    overlayImg.style.cssText = 'width: 100%; height: 100%; object-fit: cover; object-position: top; border-radius: 8px; display: block;';
                    overlayModelDiv.insertBefore(overlayImg, overlayModelDiv.firstChild);
                }
                if (overlayImg) {
                    overlayImg.src = imagePath;
                    overlayImg.alt = productName;
                    overlayImg.style.display = 'block';
                }
            } else if (modelPath) {
                if (overlayModelViewer) {
                    overlayModelViewer.setAttribute('src', modelPath);
                    overlayModelViewer.style.display = 'block';
                }
                if (overlayImg) overlayImg.style.display = 'none';
            } else {
                if (overlayModelViewer) overlayModelViewer.style.display = 'none';
                if (overlayImg) overlayImg.style.display = 'none';
            }
            if (overlayNameElement) overlayNameElement.textContent = productName;

            // Extract unit rental and selling prices from loginBox attributes
            let unitRental = loginBox ? parseFloat(loginBox.getAttribute('data-unit-discounted-rental-price') || loginBox.getAttribute('data-unit-rental-price')) : 0;
            if (!unitRental || isNaN(unitRental) || unitRental <= 0) {
                unitRental = parseFloat(String(productPrice).replace(/[^0-9.]/g, '')) || 0;
            }
            let unitSelling = loginBox ? parseFloat(loginBox.getAttribute('data-unit-selling-price')) : 0;
            let discountPercent = loginBox ? parseFloat(loginBox.getAttribute('data-discount')) || 0 : 0;

            // Fallback to savedProducts array if needed
            if (!unitSelling || isNaN(unitSelling)) {
                for (const prod of (window.savedProductsLive || savedProductsFromDB)) {
                    if (prod.product_name === productName && prod.product_category === productCategory) {
                        unitSelling = parseFloat(prod.purchase_price) || 0;
                        if (!unitRental) {
                            const disc = parseFloat(prod.discount) || 0;
                            const orig = parseFloat(prod.product_price || prod.price || 0);
                            unitRental = disc > 0 ? orig * (1 - disc / 100) : orig;
                        }
                        break;
                    }
                }
            }

            window.currentOverlayUnitRentalPrice = unitRental || 0;
            window.currentOverlayUnitSellingPrice = unitSelling || 0;
            window.currentOverlayDiscountPercent = discountPercent;

            // Update overlay prices dynamically
            if (typeof updateOverlayPricesRealtime === 'function') {
                updateOverlayPricesRealtime();
            } else {
                if (overlayPriceElement) overlayPriceElement.innerHTML = `<span style="color: gray; font-size: 0.8rem;">Rental Price:</span> ₱${window.currentOverlayUnitRentalPrice.toLocaleString(undefined, {maximumFractionDigits: 0})}`;
                if (overlayPurchasePriceElement && window.currentOverlayUnitSellingPrice > 0) {
                    overlayPurchasePriceElement.innerHTML = `Selling Price: <span style="color: red;">₱${window.currentOverlayUnitSellingPrice.toLocaleString(undefined, {maximumFractionDigits: 0})}</span>`;
                    overlayPurchasePriceElement.style.display = 'block';
                } else if (overlayPurchasePriceElement) {
                    overlayPurchasePriceElement.style.display = 'none';
                }
            }


            // Get stock quantity from product box
            const quantityBox = document.getElementById(`quantityBox${targetBoxNumber}`);
            const stockQuantity = quantityBox ? parseInt(quantityBox.getAttribute('data-stock-quantity')) || 0 : 0;
            if (overlayStockElement && stockQuantity > 0) {
                overlayStockElement.textContent = `Stock: ${stockQuantity}`;
            } else if (overlayStockElement) {
                overlayStockElement.textContent = 'Stock: Available';
            }

            // Store stock quantity globally for quantity limits
            window.currentStockQuantity = stockQuantity;

            // Store size guide image globally
            window.currentSizeGuideImage = null;

            // Generate size/measurement fields based on quantity
            console.log('Generating measurement fields for quantity:', quantity);
            generateSizeMeasurementFields(quantity, productCategory, productName);

            // Check future reservation schedule for buying notice
            const noticeBox = document.getElementById('buyScheduleNotice');
            if (noticeBox) noticeBox.style.display = 'none';
            window.currentEarliestBuyDate = null;

            const checkUrl = `get_available_stock.php?product_id=${gownProductId}&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}&type=buy_check`;
            fetch(checkUrl)
                .then(r => r.json())
                .then(stk => {
                    if (stk.success && !stk.can_buy_now) {
                        window.currentEarliestBuyDate = stk.earliest_buy_date;
                    }
                })
                .catch(e => console.error(e));

            // Load existing reservation details after form fields are generated and rendered
            setTimeout(() => {
                loadReservationDetails(productName, productCategory, savedProductId);
            }, 100);

            console.log('Updated overlay with product:', {
                boxNumber: targetBoxNumber,
                name: productName,
                category: productCategory,
                price: productPrice,
                modelPath: modelPath,
                quantity: quantity
            });
        }

        // Show reserve overlay
        document.getElementById('topOverlay').style.display = 'block';
        document.body.style.overflow = 'hidden';

        // Immediately sync latest live prices from admin/database
        if (typeof syncLiveProductPrices === 'function') {
            syncLiveProductPrices();
        }

        setTimeout(() => {
            const overlay = document.getElementById('reserveOverlay');
            if (overlay) {
                overlay.style.display = 'block';
                adjustReserveOverlayHeight();
            }
        }, 100);
    }

    // Overdue calculation modal handlers for saved items
    function openOverdueCalcModal(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        const overlayName = document.querySelector('#reserveOverlay .product-name-overlay')?.textContent?.trim() || 'Selected Product';
        const priceText = document.querySelector('#reserveOverlay .product-price-overlay')?.textContent || '0';
        const rentalPrice = parseFloat(priceText.replace(/[^0-9.]/g, '')) || 0;
        const penaltyPct = <?php echo $overduePenaltyPercent; ?>;
        const feePerDay = (rentalPrice * penaltyPct) / 100;

        const prodNameEl = document.getElementById('overdueCalcProdName');
        const rentalPriceEl = document.getElementById('overdueCalcRentalPrice');
        const formulaPriceEl = document.getElementById('overdueCalcFormulaPrice');
        const feePerDayEl = document.getElementById('overdueCalcFeePerDay');
        const warningFeeEl = document.getElementById('overdueCalcWarningFee');

        if (prodNameEl) prodNameEl.textContent = overlayName;
        if (rentalPriceEl) rentalPriceEl.textContent = '₱' + rentalPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (formulaPriceEl) formulaPriceEl.textContent = '₱' + rentalPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (feePerDayEl) feePerDayEl.textContent = '₱' + feePerDay.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (warningFeeEl) warningFeeEl.textContent = '₱' + feePerDay.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' per day';

        const modal = document.getElementById('overdueCalcModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeOverdueCalcModal(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        const modal = document.getElementById('overdueCalcModal');
        if (modal) modal.style.display = 'none';
    }

    // Generate size and measurement fields based on quantity
    function generateSizeMeasurementFields(quantity, productCategory, productName) {
        const measurementsContainer = document.getElementById('measurementsContainer');
        const reservationForm = document.querySelector('.reservation-form');
        if (!measurementsContainer) {
            console.log('measurementsContainer not found');
            return;
        }

        console.log('Generating measurement fields for quantity:', quantity, 'category:', productCategory, 'product:', productName);

        // Fetch category size labels and product size ranges from database
        Promise.all([
            fetch('save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_category_size_labels&category_name=${encodeURIComponent(productCategory)}`
            }),
            fetch('save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_product_size_ranges&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}`
            })
        ])
        .then(responses => Promise.all(responses.map(r => r.json())))
        .then(([labelData, rangeData]) => {
            // Store size guide image globally
            window.currentSizeGuideImage = labelData.size_guide_image || null;

            // Get all non-empty size labels from category
            const sizeLabels = [];
            for (let i = 1; i <= 10; i++) {
                const label = labelData[`size_label_${i}`];
                if (label && label.trim() !== '') {
                    sizeLabels.push({ index: i, label: label });
                }
            }

            // Store labels and ranges globally for dynamic item generation
            window.currentSizeLabels = sizeLabels;
            window.currentSizeRanges = rangeData;

            // Adjust reserve-overlay height on mobile in realtime based on number of size boxes and quantity
            adjustReserveOverlayHeight(sizeLabels.length);

            // Clear existing fields
            measurementsContainer.innerHTML = '';

            // Generate item groups based on quantity
            for (let i = 1; i <= quantity; i++) {
                const fieldGroup = document.createElement('div');
                fieldGroup.className = 'measurement-group';

                // Generate size dropdowns dynamically based on category size labels
                let sizeDropdownsHTML = '';
                sizeLabels.forEach(sizeLabel => {
                    const min = rangeData[`size_${sizeLabel.index}_min`] || 0;
                    const max = rangeData[`size_${sizeLabel.index}_max`] || 0;
                    sizeDropdownsHTML += `
                        <div class="form-group">
                            <label for="size_${sizeLabel.index}_${i}">${sizeLabel.label}</label>
                            <select id="size_${sizeLabel.index}_${i}" name="size_${sizeLabel.index}_${i}">
                                <option value="">Select</option>
                                ${generateSizeOptions(min, max)}
                            </select>
                        </div>
                    `;
                });

                fieldGroup.innerHTML = `
                    <div class="measurement-label">
                        <span class="item-label-text">${i === 1 ? 'Size' : 'Size ' + i}</span>
                        ${i === 1 ? '<span class="size-guide-text" onclick="showSizeGuideImage()" style="cursor: pointer; color: #667eea;">Size Guide</span>' : ''}
                    </div>
                    <div class="form-row sizes-row">
                        ${sizeDropdownsHTML}
                    </div>
                `;
                measurementsContainer.appendChild(fieldGroup);
            }

            // Update quantity selector under Reserve Date
            const totalQuantityInput = document.getElementById('totalQuantity');
            if (totalQuantityInput) {
                totalQuantityInput.value = quantity;
                totalQuantityInput.max = window.currentStockQuantity || 999;
            }
            updateQuantityButtons(quantity, window.currentStockQuantity || 999);

            console.log('Generated', measurementsContainer.children.length, 'measurement groups');

            // Set overflow based on quantity
            if (reservationForm) {
                if (quantity > 1) {
                    reservationForm.style.overflowY = 'auto';
                } else {
                    reservationForm.style.overflowY = 'hidden';
                }
            }
        })
        .catch(error => {
            console.error('Error fetching category labels or product ranges:', error);
            // Fallback to default labels and ranges
            const sizeLabels = [
                { index: 1, label: 'Chest / Bust' },
                { index: 2, label: 'Waist' },
                { index: 3, label: 'Hips' },
                { index: 4, label: 'Shoulder' },
                { index: 5, label: 'Arm Length' },
                { index: 6, label: 'Sleeve Length' },
                { index: 7, label: 'Inseam' },
                { index: 8, label: 'Thigh' },
                { index: 9, label: 'Neck' },
                { index: 10, label: 'Wrist' }
            ];

            // Adjust reserve-overlay height on mobile in realtime
            adjustReserveOverlayHeight(sizeLabels.length);

            const sizeRanges = {
                size_1_min: 70,
                size_1_max: 120,
                size_2_min: 60,
                size_2_max: 100,
                size_3_min: 80,
                size_3_max: 130,
                size_4_min: 40,
                size_4_max: 60,
                size_5_min: 50,
                size_5_max: 70,
                size_6_min: 20,
                size_6_max: 40,
                size_7_min: 70,
                size_7_max: 90,
                size_8_min: 50,
                size_8_max: 70,
                size_9_min: 30,
                size_9_max: 45,
                size_10_min: 15,
                size_10_max: 25
            };

            // Store labels and ranges globally for dynamic item generation
            window.currentSizeLabels = sizeLabels;
            window.currentSizeRanges = sizeRanges;

            // Clear existing fields
            measurementsContainer.innerHTML = '';

            // Generate item groups based on quantity
            for (let i = 1; i <= quantity; i++) {
                const fieldGroup = document.createElement('div');
                fieldGroup.className = 'measurement-group';

                // Generate size dropdowns dynamically based on category size labels
                let sizeDropdownsHTML = '';
                sizeLabels.forEach(sizeLabel => {
                    const min = sizeRanges[`size_${sizeLabel.index}_min`] || 0;
                    const max = sizeRanges[`size_${sizeLabel.index}_max`] || 0;
                    sizeDropdownsHTML += `
                        <div class="form-group">
                            <label for="size_${sizeLabel.index}_${i}">${sizeLabel.label}</label>
                            <select id="size_${sizeLabel.index}_${i}" name="size_${sizeLabel.index}_${i}">
                                <option value="">Select</option>
                                ${generateSizeOptions(min, max)}
                            </select>
                        </div>
                    `;
                });

                fieldGroup.innerHTML = `
                    <div class="measurement-label">
                        <span class="item-label-text">${i === 1 ? 'Size' : 'Size ' + i}</span>
                        ${i === 1 ? '<span class="size-guide-text" onclick="showSizeGuideImage()" style="cursor: pointer; color: #667eea;">Size Guide</span>' : ''}
                    </div>
                    <div class="form-row sizes-row">
                        ${sizeDropdownsHTML}
                    </div>
                `;
                measurementsContainer.appendChild(fieldGroup);
            }

            console.log('Generated', measurementsContainer.children.length, 'measurement groups (fallback)');

            // Set overflow based on quantity
            if (reservationForm) {
                if (quantity > 1) {
                    reservationForm.style.overflowY = 'auto';
                } else {
                    reservationForm.style.overflowY = 'hidden';
                }
            }
        });
    }

    // Function to dynamically adjust reserve-overlay height on mobile based on number of size boxes and quantity
    function adjustReserveOverlayHeight(sizeBoxCount) {
        const isMobile = window.innerWidth <= 768;
        const reserveOverlay = document.getElementById('reserveOverlay') || document.querySelector('.reserve-overlay');
        if (!reserveOverlay) return;

        if (!isMobile) {
            reserveOverlay.style.height = '';
            return;
        }

        const count = (typeof sizeBoxCount === 'number' && sizeBoxCount > 0)
            ? sizeBoxCount
            : (window.currentSizeLabels ? window.currentSizeLabels.length : 10);

        const measurementGroups = document.querySelectorAll('#measurementsContainer .measurement-group');
        const quantity = measurementGroups.length || 1;

        let height;
        if (quantity > 1) {
            height = '95%';
        } else if (count >= 1 && count <= 3) {
            height = '68%';
        } else if (count >= 4 && count <= 6) {
            height = '80%';
        } else if (count >= 7 && count <= 9) {
            height = '88%';
        } else if (count >= 10) {
            height = '95%';
        } else {
            height = '82%';
        }

        reserveOverlay.style.height = height;
    }

    // Listen to screen resize for dynamic overlay height adjustment
    window.addEventListener('resize', function() {
        adjustReserveOverlayHeight();
    });

    // Helper functions to generate option HTML
    function generateSizeOptions(min, max) {
        let options = '';
        for (let i = min; i <= max; i += 1) {
            options += `<option value="${i}">${i} cm</option>`;
        }
        return options;
    }

    function generateBustOptions() {
        let options = '';
        for (let i = 70; i <= 120; i += 2) {
            options += `<option value="${i}">${i} cm</option>`;
        }
        return options;
    }

    function generateWaistOptions() {
        let options = '';
        for (let i = 50; i <= 100; i += 2) {
            options += `<option value="${i}">${i} cm</option>`;
        }
        return options;
    }

    function generateHipsOptions() {
        let options = '';
        for (let i = 70; i <= 130; i += 2) {
            options += `<option value="${i}">${i} cm</option>`;
        }
        return options;
    }

    // Global variables for overlay pricing
    window.currentOverlayUnitRentalPrice = 0;
    window.currentOverlayUnitSellingPrice = 0;
    window.currentOverlayDiscountPercent = 0;
    window.currentOverlayType = 'reserve';

    // Recalculates and updates overlay Rental Price and Selling Price in realtime based on quantity, type, and extension days
    function updateOverlayPricesRealtime() {
        const overlayPriceElement = document.querySelector('#reserveOverlay .product-price-overlay');
        const overlayPurchasePriceElement = document.querySelector('#reserveOverlay .product-purchase-price-overlay');
        const measurementsContainer = document.getElementById('measurementsContainer');
        const count = measurementsContainer ? measurementsContainer.querySelectorAll('.measurement-group').length : 1;
        const qty = Math.max(1, count);

        const baseUnitRental = parseFloat(window.currentOverlayUnitRentalPrice) || 0;
        const unitSelling = parseFloat(window.currentOverlayUnitSellingPrice) || 0;
        const isBuy = (window.currentOverlayType === 'buy');

        // Check if Return Date extends past standard rental duration
        const reserveDateVal = document.getElementById('reserveDate')?.value;
        const returnDateVal = document.getElementById('returnDate')?.value;
        const stdDuration = (typeof window.currentRentalDurationDays !== 'undefined') ? window.currentRentalDurationDays : (<?php echo intval($rentalDurationDays); ?> || 10);
        const extDailyFee = (typeof window.currentExtensionPricePerDay !== 'undefined') ? window.currentExtensionPricePerDay : (<?php echo floatval($extensionPricePerDay); ?> || 100);

        let extraDays = 0;
        let extraFeePerUnit = 0;
        let actualDurationDays = stdDuration;

        if (reserveDateVal && returnDateVal && !isBuy) {
            const rObj = new Date(reserveDateVal + 'T00:00:00');
            const retObj = new Date(returnDateVal + 'T00:00:00');
            const diffDays = Math.round((retObj - rObj) / (1000 * 60 * 60 * 24));
            actualDurationDays = Math.max(0, diffDays);
            if (actualDurationDays > stdDuration) {
                extraDays = actualDurationDays - stdDuration;
                extraFeePerUnit = extraDays * extDailyFee;
            }
        }

        const effectiveUnitRental = baseUnitRental + extraFeePerUnit;
        window.currentCalculatedUnitRentalPrice = effectiveUnitRental;
        window.currentCalculatedTotalRentalPrice = effectiveUnitRental * qty;

        const totalRental = effectiveUnitRental * qty;
        const totalSelling = unitSelling * qty;

        if (overlayPriceElement) {
            const rentalText = `<span style="color: gray; font-size: 0.8rem;">Rental Price:</span> ₱${totalRental.toLocaleString(undefined, {maximumFractionDigits: 0})}`;
            overlayPriceElement.innerHTML = rentalText;

            if (isBuy) {
                overlayPriceElement.style.opacity = '0.45';
            } else {
                overlayPriceElement.style.opacity = '1';
            }
        }

        if (overlayPurchasePriceElement) {
            if (unitSelling > 0) {
                const sellingText = `Selling Price: <span style="color: red;">₱${totalSelling.toLocaleString(undefined, {maximumFractionDigits: 0})}</span>`;
                overlayPurchasePriceElement.innerHTML = sellingText;
                overlayPurchasePriceElement.style.display = 'block';

                if (isBuy) {
                    overlayPurchasePriceElement.style.opacity = '1';
                    overlayPurchasePriceElement.style.fontWeight = 'bold';
                } else {
                    overlayPurchasePriceElement.style.opacity = '0.85';
                    overlayPurchasePriceElement.style.fontWeight = 'normal';
                }
            } else {
                overlayPurchasePriceElement.style.display = 'none';
            }
        }
    }

    // Type (Reserve / Buy) selection state and handler
    function setReservationType(type) {
        const isBuy = (type === 'buy');
        window.currentOverlayType = isBuy ? 'buy' : 'reserve';

        const radioReserve = document.getElementById('typeRadioReserve');
        const radioBuy = document.getElementById('typeRadioBuy');
        const textReserve = document.getElementById('typeTextReserve');
        const textBuy = document.getElementById('typeTextBuy');

        if (radioReserve && radioBuy) {
            if (isBuy) {
                radioReserve.classList.remove('active');
                radioBuy.classList.add('active');
                radioBuy.classList.add('buy-active');
                if (textReserve) { textReserve.style.color = '#888'; textReserve.style.fontWeight = 'normal'; }
                if (textBuy) { textBuy.style.color = '#fff'; textBuy.style.fontWeight = '600'; }
            } else {
                radioBuy.classList.remove('active');
                radioBuy.classList.remove('buy-active');
                radioReserve.classList.add('active');
                if (textReserve) { textReserve.style.color = '#fff'; textReserve.style.fontWeight = '600'; }
                if (textBuy) { textBuy.style.color = '#888'; textBuy.style.fontWeight = 'normal'; }
            }
        }

        const returnDateGroup = document.getElementById('returnDateGroup');
        const returnDateInput = document.getElementById('returnDate');
        const reserveDateGroup = document.getElementById('reserveDateGroup');
        const reserveDateLabel = document.getElementById('reserveDateLabel');
        const overdueNotice = document.querySelector('#reserveOverlay .overdue-policy-notice');
        const overlayPriceElement = document.querySelector('#reserveOverlay .product-price-overlay');
        const overlayPurchasePriceElement = document.querySelector('#reserveOverlay .product-purchase-price-overlay');

        if (returnDateGroup) {
            returnDateGroup.style.display = isBuy ? 'none' : '';
        }
        if (returnDateInput) {
            returnDateInput.required = !isBuy;
        }
        if (reserveDateGroup) {
            reserveDateGroup.style.gridColumn = '';
        }
        if (reserveDateLabel) {
            reserveDateLabel.textContent = isBuy ? 'Purchase Date' : 'Reserve Date';
        }
        if (overdueNotice) {
            overdueNotice.style.display = isBuy ? 'none' : 'block';
        }

        if (overlayPriceElement && overlayPurchasePriceElement) {
            if (isBuy) {
                overlayPriceElement.style.opacity = '0.45';
                overlayPurchasePriceElement.style.opacity = '1';
                overlayPurchasePriceElement.style.fontWeight = 'bold';
            } else {
                overlayPriceElement.style.opacity = '1';
                overlayPurchasePriceElement.style.opacity = '0.85';
                overlayPurchasePriceElement.style.fontWeight = 'normal';
            }
        }

        const noticeBox = document.getElementById('buyScheduleNotice');
        if (noticeBox) {
            noticeBox.style.display = (isBuy && window.currentEarliestBuyDate) ? 'block' : 'none';
        }

        updateOverlayPricesRealtime();
        refreshOverlayUnavailableDates();
    }

    // Helper to refresh unavailable dates for current overlay product, type, and quantity
    function refreshOverlayUnavailableDates() {
        const reserveOverlay = document.getElementById('reserveOverlay');
        const gownId = reserveOverlay ? (reserveOverlay.getAttribute('data-gown-id') || '') : '';
        const productName = document.querySelector('#reserveOverlay .product-name-overlay')?.textContent?.trim() || '';
        let productCategory = 'Gown';
        const allProductNames = document.querySelectorAll('[id^="productName"]');
        for (let element of allProductNames) {
            if (element.textContent.trim() === productName) {
                const boxNumber = element.id.replace('productName', '');
                const categoryElement = document.getElementById(`productCategory${boxNumber}`);
                if (categoryElement) { productCategory = categoryElement.textContent.trim(); break; }
            }
        }
        if (typeof fetchUnavailableDatesForCalendar === 'function' && (gownId || productName)) {
            fetchUnavailableDatesForCalendar(gownId, productName, productCategory);
        }
    }

    function handleOverlaySave() {
        if (window.currentOverlayType === 'buy') {
            buyProductFromOverlay();
        } else {
            saveProductFromOverlay();
        }
    }

    // Update quantity buttons enabled/disabled states
    function updateQuantityButtons(current, max) {
        const plusBtn = document.querySelector('.quantity-plus');
        const minusBtn = document.querySelector('.quantity-minus');
        const cur = parseInt(current) || 1;
        const mx = parseInt(max) || 999;
        if (minusBtn) {
            if (cur <= 1) {
                minusBtn.disabled = true;
                minusBtn.style.opacity = '0.5';
                minusBtn.style.cursor = 'not-allowed';
            } else {
                minusBtn.disabled = false;
                minusBtn.style.opacity = '1';
                minusBtn.style.cursor = 'pointer';
            }
        }
        if (plusBtn) {
            if (cur >= mx) {
                plusBtn.disabled = true;
                plusBtn.style.opacity = '0.5';
                plusBtn.style.cursor = 'not-allowed';
            } else {
                plusBtn.disabled = false;
                plusBtn.style.opacity = '1';
                plusBtn.style.cursor = 'pointer';
            }
        }
    }

    // Quantity increment/decrement functions
    function incrementQuantity() {
        const measurementsContainer = document.getElementById('measurementsContainer');
        const totalQuantityInput = document.getElementById('totalQuantity');
        const measurementGroups = measurementsContainer ? measurementsContainer.querySelectorAll('.measurement-group') : [];
        const currentCount = Math.max(measurementGroups.length, parseInt(totalQuantityInput?.value) || 1);
        const maxQuantity = window.currentStockQuantity || 999;

        if (currentCount < maxQuantity) {
            const newIndex = currentCount + 1;
            addMeasurementGroup(newIndex);
            
            // Re-query after adding to get accurate count
            const updatedGroups = measurementsContainer ? measurementsContainer.querySelectorAll('.measurement-group') : [];
            const newCount = updatedGroups.length || newIndex;
            
            if (totalQuantityInput) {
                totalQuantityInput.value = newCount;
            }

            updateQuantityButtons(newCount, maxQuantity);
            adjustReserveOverlayHeight();
            updateOverlayPricesRealtime();
            refreshOverlayUnavailableDates();
        } else {
            showNotification('Cannot add more items - stock limit reached for these dates', 2000);
            updateQuantityButtons(currentCount, maxQuantity);
        }
    }

    function decrementQuantity() {
        const measurementsContainer = document.getElementById('measurementsContainer');
        const totalQuantityInput = document.getElementById('totalQuantity');
        const measurementGroups = measurementsContainer ? measurementsContainer.querySelectorAll('.measurement-group') : [];
        const currentCount = Math.max(measurementGroups.length, parseInt(totalQuantityInput?.value) || 1);
        const maxQuantity = window.currentStockQuantity || 999;

        if (currentCount > 1) {
            if (measurementGroups.length > 1) {
                measurementGroups[measurementGroups.length - 1].remove();
            }

            const newCount = currentCount - 1;
            if (totalQuantityInput) {
                totalQuantityInput.value = newCount;
            }

            // Disable scroll if only one item
            const reservationForm = document.querySelector('.reservation-form');
            if (reservationForm && newCount === 1) {
                reservationForm.style.overflowY = 'hidden';
            }

            updateQuantityButtons(newCount, maxQuantity);
            adjustReserveOverlayHeight();
            updateOverlayPricesRealtime();
            refreshOverlayUnavailableDates();
        } else {
            updateQuantityButtons(1, maxQuantity);
        }
    }

    function addMeasurementGroup(index) {
        const measurementsContainer = document.getElementById('measurementsContainer');
        const reservationForm = document.querySelector('.reservation-form');

        // Use globally stored labels and ranges (or defaults if not set)
        const sizeLabels = window.currentSizeLabels || [
            { index: 1, label: 'Chest / Bust' },
            { index: 2, label: 'Waist' },
            { index: 3, label: 'Hips' },
            { index: 4, label: 'Shoulder' },
            { index: 5, label: 'Arm Length' },
            { index: 6, label: 'Sleeve Length' },
            { index: 7, label: 'Inseam' },
            { index: 8, label: 'Thigh' },
            { index: 9, label: 'Neck' },
            { index: 10, label: 'Wrist' }
        ];
        const sizeRanges = window.currentSizeRanges || {
            size_1_min: 70,
            size_1_max: 120,
            size_2_min: 60,
            size_2_max: 100,
            size_3_min: 80,
            size_3_max: 130,
            size_4_min: 40,
            size_4_max: 60,
            size_5_min: 50,
            size_5_max: 70,
            size_6_min: 20,
            size_6_max: 40,
            size_7_min: 70,
            size_7_max: 90,
            size_8_min: 50,
            size_8_max: 70,
            size_9_min: 30,
            size_9_max: 45,
            size_10_min: 15,
            size_10_max: 25
        };

        // Generate size dropdowns dynamically based on category size labels
        let sizeDropdownsHTML = '';
        sizeLabels.forEach(sizeLabel => {
            const min = sizeRanges[`size_${sizeLabel.index}_min`] || 0;
            const max = sizeRanges[`size_${sizeLabel.index}_max`] || 0;
            sizeDropdownsHTML += `
                <div class="form-group">
                    <label for="size_${sizeLabel.index}_${index}">${sizeLabel.label}</label>
                    <select id="size_${sizeLabel.index}_${index}" name="size_${sizeLabel.index}_${index}">
                        <option value="">Select</option>
                        ${generateSizeOptions(min, max)}
                    </select>
                </div>
            `;
        });

        const fieldGroup = document.createElement('div');
        fieldGroup.className = 'measurement-group';
        fieldGroup.innerHTML = `
            <div class="measurement-label">
                <span class="item-label-text">${index === 1 ? 'Size' : 'Size ' + index}</span>
            </div>
            <div class="form-row sizes-row">
                ${sizeDropdownsHTML}
            </div>
        `;

        // Insert before the quantity-group (which should always be last)
        const quantityGroup = measurementsContainer.querySelector('.quantity-group');
        if (quantityGroup) {
            measurementsContainer.insertBefore(fieldGroup, quantityGroup);
        } else {
            measurementsContainer.appendChild(fieldGroup);
        }

        // Auto-scroll to the newly added measurement group
        setTimeout(() => {
            fieldGroup.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);

        // Update the total quantity input
        const totalQuantityInput = document.getElementById('totalQuantity');
        if (totalQuantityInput) {
            totalQuantityInput.value = index;
        }

        // Enable scroll if multiple items
        if (reservationForm) {
            reservationForm.style.overflowY = 'auto';
        }
    }

    // Show Size Guide Image Modal
    function showSizeGuideImage() {
        const sizeGuideImage = window.currentSizeGuideImage;
        if (!sizeGuideImage) {
            alert('No size guide image available for this category.');
            return;
        }

        // Create modal if it doesn't exist
        let modal = document.getElementById('sizeGuideModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'sizeGuideModal';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.8);
                display: none;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            `;

            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: #fff;
                padding: 20px;
                border-radius: 10px;
                max-width: 90%;
                max-height: 90%;
                overflow: auto;
                position: relative;
            `;

            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.cssText = `
                position: absolute;
                top: 10px;
                right: 15px;
                background: none;
                border: none;
                font-size: 28px;
                cursor: pointer;
                color: #333;
            `;
            closeBtn.onclick = closeSizeGuideModal;

            const image = document.createElement('img');
            image.id = 'sizeGuideModalImage';
            image.style.cssText = `
                max-width: 100%;
                max-height: 80vh;
                display: block;
                margin: 0 auto;
            `;

            modalContent.appendChild(closeBtn);
            modalContent.appendChild(image);
            modal.appendChild(modalContent);
            document.body.appendChild(modal);

            // Close on background click
            modal.onclick = function(e) {
                if (e.target === modal) {
                    closeSizeGuideModal();
                }
            };
        }

        // Set image source and show modal
        document.getElementById('sizeGuideModalImage').src = /^(https?:)?\//i.test(sizeGuideImage)
            ? sizeGuideImage
            : sizeGuideImage.replace(/^(\.\.\/)+/, '');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeSizeGuideModal() {
        const modal = document.getElementById('sizeGuideModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    // Play notification sound
    function playNotificationSound() {
        const audio = new Audio('ringtone/notification.MP3');
        audio.volume = 0.5; // Set volume to 50%
        audio.play().catch(error => {
            console.log('Audio play failed:', error);
        });
    }
    
    // Load reservation details from database and populate form
    function loadReservationDetails(productName, productCategory, savedProductId = '') {
        // Fetch reservation details from server
        fetch('save_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_reservation&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}&saved_product_id=${savedProductId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.reservation) {
                // Populate form with existing reservation details
                const reservation = data.reservation;
                const additionalItems = data.additional_items || {};
                
                // Calculate total number of items
                const totalItems = Math.max(parseInt(reservation.quantity) || 1, 1 + Object.keys(additionalItems).length);
                console.log('Total items to display:', totalItems);
                
                // Ensure overlay has enough measurement groups
                const currentMeasurementGroups = measurementsContainer.querySelectorAll('.measurement-group');
                const currentGroupCount = currentMeasurementGroups.length;
                
                if (currentGroupCount < totalItems) {
                    // Add missing measurement groups
                    for (let i = currentGroupCount + 1; i <= totalItems; i++) {
                        addMeasurementGroup(i);
                    }
                } else if (currentGroupCount > totalItems) {
                    // Remove excess measurement groups
                    for (let i = totalItems + 1; i <= currentGroupCount; i++) {
                        const groupToRemove = document.getElementById(`measurementGroup_${i}`);
                        if (groupToRemove) {
                            groupToRemove.remove();
                        }
                    }
                }
                
                // Update quantity display
                const quantityDisplay = document.getElementById('quantity');
                if (quantityDisplay) {
                    quantityDisplay.textContent = totalItems;
                }
                const totalQuantityInput = document.getElementById('totalQuantity');
                if (totalQuantityInput) {
                    totalQuantityInput.value = totalItems;
                    totalQuantityInput.max = window.currentStockQuantity || 999;
                }
                updateQuantityButtons(totalItems, window.currentStockQuantity || 999);
                
                // Populate date fields
                if (reservation.reserve_date) {
                    document.getElementById('reserveDate').value = reservation.reserve_date;
                }
                if (reservation.return_date) {
                    document.getElementById('returnDate').value = reservation.return_date;
                }

                // Sync reservation type
                if (reservation.product_status) {
                    setReservationType(reservation.product_status === 'buy' ? 'buy' : 'reserve');
                }

                // Recalculate overlay prices with loaded quantity
                updateOverlayPricesRealtime();

                // Check available stock for the loaded dates
                updateAvailableStock();

                // Populate measurement fields
                console.log('Attempting to populate measurement fields');
                console.log('size_1 value:', reservation.size_1);
                console.log('size_2 value:', reservation.size_2);
                console.log('size_3 value:', reservation.size_3);

                const measurementGroups = measurementsContainer.querySelectorAll('.measurement-group');
                console.log('Found', measurementGroups.length, 'measurement groups to populate');

                // Populate Item 1 with the reservation data
                if (measurementGroups.length > 0) {
                    // Populate all 10 size fields from database for Item 1
                    for (let j = 1; j <= 10; j++) {
                        const sizeElement = document.getElementById(`size_${j}_1`);
                        const sizeValue = reservation[`size_${j}`];
                        
                        if (sizeValue && sizeElement) {
                            sizeElement.value = sizeValue;
                            console.log(`Set size_${j}_1 to:`, sizeValue);
                        }
                    }
                }
                
                // Populate Item 2+ with the additional items data
                for (const [itemNumber, itemData] of Object.entries(additionalItems)) {
                    const itemNum = parseInt(itemNumber);
                    if (itemNum > 1 && measurementGroups.length >= itemNum) {
                        // Populate all 10 size fields for this item
                        for (let j = 1; j <= 10; j++) {
                            const sizeElement = document.getElementById(`size_${j}_${itemNum}`);
                            const sizeValue = itemData[`size_${j}`];
                            
                            if (sizeValue && sizeElement) {
                                sizeElement.value = sizeValue;
                                console.log(`Set size_${j}_${itemNum} to:`, sizeValue);
                            }
                        }
                    }
                }
                
                console.log('Loaded reservation details:', reservation);
            } else {
                // Clear form if no reservation details exist
                clearReservationForm();
                console.log('No existing reservation details found');
            }
        })
        .catch(error => {
            console.error('Error loading reservation details:', error);
        });
    }
    
    // Clear reservation form
    function clearReservationForm() {
        document.getElementById('reserveDate').value = '';
        document.getElementById('returnDate').value = '';
        document.getElementById('reserveTime').value = '';
        document.getElementById('returnTime').value = '';

        // Don't clear dynamic measurements container - it should be managed by generateSizeMeasurementFields
    }
    
    // Go to product reserve page with checked products
    function goToProductReserve() {
        // Get all checked products
        const checkedBoxes = document.querySelectorAll('.login-box .checkbox.checked');
        
        if (checkedBoxes.length === 0) {
            showNotification('Please select at least one product to reserve', 2000);
            return;
        }
        
        // Collect product data
        const products = [];
        let totalPrice = 0;
        
        checkedBoxes.forEach((checkbox, index) => {
            const boxNumber = checkbox.id.replace('productCheckbox', '');
            const loginBox = checkbox.closest('.login-box');
            
            // Get product information
            const nameElement = document.getElementById(`productName${boxNumber}`);
            const categoryElement = document.getElementById(`productCategory${boxNumber}`);
            const priceElement = document.getElementById(`productPrice${boxNumber}`);
            const modelContainer = document.getElementById(`modelContainer${boxNumber}`);
            
            if (nameElement && categoryElement && priceElement && modelContainer) {
                const modelViewer = modelContainer.querySelector('model-viewer');
                const imgElement = modelContainer.querySelector('img');
                const modelPath = (modelViewer ? (modelViewer.getAttribute('src') || '') : '') || (loginBox?.getAttribute('data-model-path') || '');
                const imagePath = (imgElement ? (imgElement.getAttribute('src') || '') : '') || (loginBox?.getAttribute('data-image-path') || '');
                const productName = nameElement.textContent;
                const productCategory = categoryElement.textContent;
                const productPrice = priceElement.textContent.replace(/[^0-9.]/g, '');

                products.push({
                    id: loginBox?.getAttribute('data-db-id') || '',
                    product_id: loginBox?.getAttribute('data-gown-id') || '',
                    name: productName,
                    category: productCategory,
                    price: parseFloat(productPrice),
                    modelPath: modelPath,
                    imagePath: imagePath
                });
                
                totalPrice += parseFloat(loginBox?.getAttribute('data-line-total')) || parseFloat(productPrice) || 0;
            }
        });
        
        // Get reservation details from first checked product
        const firstBox = checkedBoxes[0] ? checkedBoxes[0].closest('.login-box') : null;
        const firstBoxNumber = checkedBoxes[0].id.replace('productCheckbox', '');
        const reserveDate = (firstBox ? (firstBox.getAttribute('data-reserve-date') || '') : '') || 
                           (document.getElementById(`reserveDate${firstBoxNumber}`) ? document.getElementById(`reserveDate${firstBoxNumber}`).value : '') ||
                           (document.getElementById('reserveDate') ? document.getElementById('reserveDate').value : '');
        const returnDate = (firstBox ? (firstBox.getAttribute('data-return-date') || '') : '') || 
                          (document.getElementById(`returnDate${firstBoxNumber}`) ? document.getElementById(`returnDate${firstBoxNumber}`).value : '') ||
                          (document.getElementById('returnDate') ? document.getElementById('returnDate').value : '');
        const reserveTime = (document.getElementById(`reserveTime${firstBoxNumber}`) ? document.getElementById(`reserveTime${firstBoxNumber}`).value : '') ||
                            (document.getElementById('reserveTime') ? document.getElementById('reserveTime').value : '');
        const returnTime = (document.getElementById(`returnTime${firstBoxNumber}`) ? document.getElementById(`returnTime${firstBoxNumber}`).value : '') ||
                           (document.getElementById('returnTime') ? document.getElementById('returnTime').value : '');
        
        // Build URL parameters
        const params = new URLSearchParams();
        params.append('products', JSON.stringify(products));
        params.append('totalPrice', Math.round(totalPrice));
        params.append('count', products.length);
        
        if (reserveDate) params.append('reserveDate', reserveDate);
        if (returnDate) params.append('returnDate', returnDate);
        if (reserveTime) params.append('reserveTime', reserveTime);
        if (returnTime) params.append('returnTime', returnTime);
        
        // Save checked state before navigating
        saveCheckedBoxesState();

        // Redirect to product reserve page
        window.location.href = `product_reserve.php?${params.toString()}`;
    }
    
    // Go to buy page with checked products
    function goToBuy() {
        // Get all checked products
        const checkedBoxes = document.querySelectorAll('.login-box .checkbox.checked');
        
        if (checkedBoxes.length === 0) {
            showNotification('Please select at least one product to buy', 2000);
            return;
        }
        
        // Collect product data
        const products = [];
        let totalPrice = 0;
        
        checkedBoxes.forEach((checkbox, index) => {
            const boxNumber = checkbox.id.replace('productCheckbox', '');
            const loginBox = checkbox.closest('.login-box');
            
            // Get product information
            const nameElement = document.getElementById(`productName${boxNumber}`);
            const categoryElement = document.getElementById(`productCategory${boxNumber}`);
            const priceElement = document.getElementById(`productPrice${boxNumber}`);
            const modelContainer = document.getElementById(`modelContainer${boxNumber}`);
            
            if (nameElement && categoryElement && priceElement && modelContainer) {
                const modelViewer = modelContainer.querySelector('model-viewer');
                const imgElement = modelContainer.querySelector('img');
                const modelPath = (modelViewer ? (modelViewer.getAttribute('src') || '') : '') || (loginBox?.getAttribute('data-model-path') || '');
                const imagePath = (imgElement ? (imgElement.getAttribute('src') || '') : '') || (loginBox?.getAttribute('data-image-path') || '');
                const productName = nameElement.textContent;
                const productCategory = categoryElement.textContent;
                const productPrice = priceElement.textContent.replace(/[^0-9.]/g, '');
                
                products.push({
                    id: loginBox?.getAttribute('data-db-id') || '',
                    product_id: loginBox?.getAttribute('data-gown-id') || '',
                    name: productName,
                    category: productCategory,
                    price: parseFloat(productPrice),
                    modelPath: modelPath,
                    imagePath: imagePath
                });
                
                totalPrice += parseFloat(loginBox?.getAttribute('data-line-total')) || parseFloat(productPrice) || 0;
            }
        });
        
        // Build URL parameters for buy page
        const params = new URLSearchParams();
        params.append('products', JSON.stringify(products));
        params.append('totalPrice', Math.round(totalPrice));
        params.append('count', products.length);
        params.append('action', 'buy');
        
        // Redirect to buy page (you can create product_buy.php or use existing checkout page)
        window.location.href = `product_buy.php?${params.toString()}`;
    }
    
    // Save product from overlay function
    function saveProductFromOverlay() {
        // Get current product data from overlay
        const overlayNameElement = document.querySelector('#reserveOverlay .product-name-overlay');
        const overlayPriceElement = document.querySelector('#reserveOverlay .product-price-overlay');
        const overlayModelViewer = document.querySelector('#reserveOverlay model-viewer');
        
        if (!overlayNameElement || !overlayPriceElement || !overlayModelViewer) {
            showNotification('Product data not available', 2000);
            return;
        }
        
        const productName = overlayNameElement.textContent;
        const productPrice = (window.currentCalculatedUnitRentalPrice !== undefined && window.currentCalculatedUnitRentalPrice !== null) 
            ? window.currentCalculatedUnitRentalPrice 
            : overlayPriceElement.textContent.replace(/[^0-9.]/g, '');
        const modelPath = overlayModelViewer.getAttribute('src');
        
        // Get saved_product_id from overlay data
        const reserveOverlay = document.getElementById('reserveOverlay');
        const savedProductId = reserveOverlay ? (reserveOverlay.getAttribute('data-saved-product-id') || '') : '';
        
        // Get form data for reservation details
        const reserveDate = document.getElementById('reserveDate').value;
        const returnDate = document.getElementById('returnDate').value;

        // Get quantity and collect all measurements
        const measurementsContainer = document.getElementById('measurementsContainer');
        const measurementGroups = measurementsContainer.querySelectorAll('.measurement-group');
        const quantity = measurementGroups.length;

        // Validate required fields (only date is required)
        if (!reserveDate || !returnDate) {
            showNotification('Fill  required  details', 2000);
            return;
        }

        // Validate that all item measurements are filled
        for (let i = 1; i <= quantity; i++) {
            // Collect all 10 size fields for each item
            let hasSize = false;
            for (let j = 1; j <= 10; j++) {
                const sizeField = document.getElementById(`size_${j}_${i}`);
                if (sizeField && sizeField.value) {
                    hasSize = true;
                    break;
                }
            }
            
            if (!hasSize) {
                showNotification(`Please fill measurement fields for ${i === 1 ? 'Size' : 'Size ' + i}`, 2000);
                return;
            }
        }

        // Collect all size measurements for all items
        const sizeData = {};
        for (let i = 1; i <= quantity; i++) {
            // Collect all 10 size fields for each item
            for (let j = 1; j <= 10; j++) {
                const sizeField = document.getElementById(`size_${j}_${i}`);
                if (sizeField && sizeField.value) {
                    sizeData[`size_${j}_${i}`] = sizeField.value;
                }
            }
        }
        
        // Get category (try to extract from existing products or use default)
        let productCategory = 'Gown'; // Default category
        const allProductNames = document.querySelectorAll('[id^="productName"]');
        for (let element of allProductNames) {
            if (element.textContent === productName) {
                const boxNumber = element.id.replace('productName', '');
                const categoryElement = document.getElementById(`productCategory${boxNumber}`);
                if (categoryElement) {
                    productCategory = categoryElement.textContent;
                    break;
                }
            }
        }
        
        // Check if user is logged in
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        
        if (!isLoggedIn) {
            showNotification('Login required', 2000);
            setTimeout(() => {
                window.location.href = 'login_user/login.php';
            }, 1500);
            return;
        }
        
        // Send reservation data to save_product.php
        let formData = `action=save_reservation&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}&product_price=${productPrice}&model_path=${encodeURIComponent(modelPath)}&reserve_date=${reserveDate}&return_date=${returnDate}&quantity=${quantity}&saved_product_id=${savedProductId}`;
        
        // Add all size measurements to form data
        for (const [key, value] of Object.entries(sizeData)) {
            formData += `&${key}=${value}`;
        }

        fetch('save_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Save response:', data);
            if (data.success) {
                showNotification('Reservation details saved successfully!', 2000);
                
                // Auto-check this product's checkbox
                if (savedProductId) {
                    sessionStorage.setItem('autoCheckSavedProductId', savedProductId);
                }
                const targetLoginBox = document.querySelector(`.login-box[data-db-id="${savedProductId}"]`);
                if (targetLoginBox) {
                    const totalRental = (window.currentCalculatedTotalRentalPrice !== undefined && window.currentCalculatedTotalRentalPrice !== null) 
                        ? window.currentCalculatedTotalRentalPrice 
                        : ((parseFloat(window.currentCalculatedUnitRentalPrice) || parseFloat(productPrice) || 0) * quantity);
                    
                    targetLoginBox.setAttribute('data-quantity', quantity);
                    targetLoginBox.setAttribute('data-line-total', totalRental);
                    targetLoginBox.setAttribute('data-reserve-date', reserveDate);
                    targetLoginBox.setAttribute('data-return-date', returnDate);
                    targetLoginBox.setAttribute('data-status', 'reserve');
                    const footerTotalEl = targetLoginBox.querySelector('.box-total-price');
                    if (footerTotalEl) {
                        footerTotalEl.textContent = `₱${totalRental.toLocaleString(undefined, {maximumFractionDigits: 0})}`;
                    }
                    const footerItemsEl = targetLoginBox.querySelector('.box-total');
                    if (footerItemsEl) {
                        footerItemsEl.textContent = `Total ${quantity} Items`;
                    }
                }
                const targetCheckbox = document.querySelector(`.login-box[data-db-id="${savedProductId}"] .checkbox`) ||
                                       document.getElementById(`productCheckbox${document.getElementById('reserveOverlay')?.getAttribute('data-target-box-number')}`);
                if (targetCheckbox) {
                    targetCheckbox.classList.add('checked');
                    updateTotalPrice();
                }

                closeReserveOverlay(); // Close overlay after saving
                // Reload page to update button display
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showNotification(data.message || 'Failed to save reservation details', 2000);
            }
        })
        .catch(error => {
            console.error('Error saving reservation details:', error);
            showNotification('Error saving reservation details', 2000);
        });
    }

    // Buy product from overlay function (with reservation conflict protection)
    function buyProductFromOverlay() {
        // Get current product data from overlay
        const overlayNameElement = document.querySelector('#reserveOverlay .product-name-overlay');
        const overlayPriceElement = document.querySelector('#reserveOverlay .product-price-overlay');
        const overlayModelViewer = document.querySelector('#reserveOverlay model-viewer');

        if (!overlayNameElement || !overlayPriceElement || !overlayModelViewer) {
            showNotification('Product data not available', 2000);
            return;
        }

        const productName = overlayNameElement.textContent;
        const productPrice = overlayPriceElement.textContent.replace(/[^0-9.]/g, '');
        const modelPath = overlayModelViewer.getAttribute('src');
        
        // Get saved_product_id and gown_id from overlay data
        const reserveOverlay = document.getElementById('reserveOverlay');
        const savedProductId = reserveOverlay ? (reserveOverlay.getAttribute('data-saved-product-id') || '') : '';
        const gownId = reserveOverlay ? (reserveOverlay.getAttribute('data-gown-id') || '') : '';

        // Get form data for reservation details
        let reserveDate = document.getElementById('reserveDate').value;
        let returnDate = document.getElementById('returnDate').value;

        // Check if user is logged in
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        if (!isLoggedIn) {
            showNotification('Login required', 2000);
            setTimeout(() => {
                window.location.href = 'login_user/login.php';
            }, 1500);
            return;
        }

        // Get category (try to extract from existing products or use default)
        let productCategory = 'Gown';
        const allProductNames = document.querySelectorAll('[id^="productName"]');
        for (let element of allProductNames) {
            if (element.textContent === productName) {
                const boxNumber = element.id.replace('productName', '');
                const categoryElement = document.getElementById(`productCategory${boxNumber}`);
                if (categoryElement) {
                    productCategory = categoryElement.textContent;
                    break;
                }
            }
        }

        // Validate that all item measurements are filled
        const measurementsContainer = document.getElementById('measurementsContainer');
        const measurementGroups = measurementsContainer.querySelectorAll('.measurement-group');
        const quantity = measurementGroups.length;

        for (let i = 1; i <= quantity; i++) {
            let hasSize = false;
            for (let j = 1; j <= 10; j++) {
                const sizeField = document.getElementById(`size_${j}_${i}`);
                if (sizeField && sizeField.value) {
                    hasSize = true;
                    break;
                }
            }

            if (!hasSize) {
                showNotification(`Please fill measurement fields for ${i === 1 ? 'Size' : 'Size ' + i}`, 2000);
                return;
            }
        }

        // Check future reservations from calendar/database
        const checkStockUrl = `get_available_stock.php?product_id=${gownId}&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}&type=buy_check`;
        
        fetch(checkStockUrl)
            .then(res => res.json())
            .then(stockInfo => {
                if (stockInfo.success && !stockInfo.can_buy_now) {
                    const earliestDate = stockInfo.earliest_buy_date;
                    window.currentEarliestBuyDate = earliestDate;

                    // If user has not chosen a date or chosen date is BEFORE the earliest available buy date
                    if (!reserveDate || reserveDate < earliestDate) {
                        showUpcomingRentalModal(stockInfo, reserveDate);
                        return;
                    }
                }

                // If date is valid, proceed with purchase
                executeBuyProduct();
            })
            .catch(error => {
                console.error('Error checking stock for buy:', error);
                showNotification('Error checking stock', 2000);
            });
    }

    // Modal controllers for Upcoming Rental Active
    function showUpcomingRentalModal(stockInfo, selectedDate) {
        window.pendingBuyStockInfo = stockInfo;
        const rentalEnd = stockInfo.latest_reservation_end_formatted || 'N/A';
        const earliestFormatted = stockInfo.earliest_buy_date_formatted || '';
        
        let dateText = selectedDate || 'Not selected';
        if (selectedDate) {
            try {
                const parts = selectedDate.split('-');
                if (parts.length === 3) {
                    const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                    dateText = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                }
            } catch(e){}
        }

        const rentalEndEl = document.getElementById('upcomingModalRentalEnd');
        const selectedDateEl = document.getElementById('upcomingModalSelectedDate');
        const earliestDateEl = document.getElementById('upcomingModalEarliestDate');
        const confirmDateEl = document.getElementById('upcomingModalConfirmDate');

        if (rentalEndEl) rentalEndEl.textContent = rentalEnd;
        if (selectedDateEl) selectedDateEl.textContent = dateText;
        if (earliestDateEl) earliestDateEl.textContent = earliestFormatted;
        if (confirmDateEl) confirmDateEl.textContent = earliestFormatted;

        const modal = document.getElementById('upcomingRentalModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function closeUpcomingRentalModal(event) {
        if (event && event.target !== document.getElementById('upcomingRentalModal')) return;
        const modal = document.getElementById('upcomingRentalModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function closeUpcomingRentalModalAndPickDate() {
        const modal = document.getElementById('upcomingRentalModal');
        if (modal) modal.style.display = 'none';

        if (window.pendingBuyStockInfo && window.pendingBuyStockInfo.earliest_buy_date) {
            window.currentEarliestBuyDate = window.pendingBuyStockInfo.earliest_buy_date;
            window.isPickingBuyDate = true;
            currentCalendarDate = new Date(window.pendingBuyStockInfo.earliest_buy_date + 'T00:00:00');
        }
        openCalendarModal('reserveDate');
    }

    function acceptUpcomingRentalDateAndBuy() {
        const modal = document.getElementById('upcomingRentalModal');
        if (modal) modal.style.display = 'none';

        if (window.pendingBuyStockInfo && window.pendingBuyStockInfo.earliest_buy_date) {
            const earliestDate = window.pendingBuyStockInfo.earliest_buy_date;
            const reserveInput = document.getElementById('reserveDate');
            const returnInput = document.getElementById('returnDate');
            if (reserveInput) reserveInput.value = earliestDate;
            if (returnInput) returnInput.value = earliestDate;
            window.currentEarliestBuyDate = earliestDate;
            
            // Execute the buy operation with confirmed date
            executeBuyProduct();
        }
    }

    function executeBuyProduct() {
        const reserveOverlay = document.getElementById('reserveOverlay');
        const savedProductId = reserveOverlay ? (reserveOverlay.getAttribute('data-saved-product-id') || '') : '';
        const productName = document.querySelector('#reserveOverlay .product-name-overlay')?.textContent?.trim() || '';
        const productPrice = document.querySelector('#reserveOverlay .product-purchase-price-overlay span')?.textContent?.trim() || '0';
        const modelPath = document.querySelector('#reserveOverlay model-viewer')?.getAttribute('src') || '';
        
        let reserveDate = document.getElementById('reserveDate').value;
        let returnDate = document.getElementById('returnDate').value || reserveDate;

        if (!reserveDate) {
            const todayStr = formatLocalDate(new Date());
            document.getElementById('reserveDate').value = todayStr;
            document.getElementById('returnDate').value = todayStr;
            reserveDate = todayStr;
            returnDate = todayStr;
        }

        let productCategory = 'Gown';
        const allProductNames = document.querySelectorAll('[id^="productName"]');
        for (let element of allProductNames) {
            if (element.textContent.trim() === productName) {
                const boxNumber = element.id.replace('productName', '');
                const categoryElement = document.getElementById(`productCategory${boxNumber}`);
                if (categoryElement) {
                    productCategory = categoryElement.textContent.trim();
                    break;
                }
            }
        }

        const measurementsContainer = document.getElementById('measurementsContainer');
        const measurementGroups = measurementsContainer.querySelectorAll('.measurement-group');
        const quantity = measurementGroups.length;

        // Collect all size measurements for all items
        const sizeData = {};
        for (let i = 1; i <= quantity; i++) {
            for (let j = 1; j <= 10; j++) {
                const sizeField = document.getElementById(`size_${j}_${i}`);
                if (sizeField && sizeField.value) {
                    sizeData[`size_${j}_${i}`] = sizeField.value;
                }
            }
        }

        // Save buy details to database
        let formData = `action=save_buy&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}&product_price=${productPrice}&model_path=${encodeURIComponent(modelPath)}&reserve_date=${reserveDate}&return_date=${returnDate}&quantity=${quantity}&saved_product_id=${savedProductId}`;
        
        for (const [key, value] of Object.entries(sizeData)) {
            formData += `&${key}=${value}`;
        }

        fetch('save_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Buy details saved successfully!', 2000);

                if (savedProductId) {
                    sessionStorage.setItem('autoCheckSavedProductId', savedProductId);
                }
                const targetLoginBox = document.querySelector(`.login-box[data-db-id="${savedProductId}"]`);
                if (targetLoginBox) {
                    const unitSelling = parseFloat(window.currentOverlayUnitSellingPrice) || parseFloat(productPrice) || 0;
                    const totalSelling = unitSelling * quantity;
                    targetLoginBox.setAttribute('data-quantity', quantity);
                    targetLoginBox.setAttribute('data-line-total', totalSelling);
                    targetLoginBox.setAttribute('data-reserve-date', reserveDate);
                    targetLoginBox.setAttribute('data-return-date', returnDate);
                    targetLoginBox.setAttribute('data-status', 'buy');
                    const footerTotalEl = targetLoginBox.querySelector('.box-total-price');
                    if (footerTotalEl) {
                        footerTotalEl.textContent = `₱${totalSelling.toLocaleString(undefined, {maximumFractionDigits: 0})}`;
                    }
                    const footerItemsEl = targetLoginBox.querySelector('.box-total');
                    if (footerItemsEl) {
                        footerItemsEl.textContent = `Total ${quantity} Items`;
                    }
                }
                const targetCheckbox = document.querySelector(`.login-box[data-db-id="${savedProductId}"] .checkbox`) ||
                                       document.getElementById(`productCheckbox${document.getElementById('reserveOverlay')?.getAttribute('data-target-box-number')}`);
                if (targetCheckbox) {
                    targetCheckbox.classList.add('checked');
                    updateTotalPrice();
                }

                closeReserveOverlay();
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showNotification(data.message || 'Failed to save buy details', 2000);
            }
        })
        .catch(error => {
            console.error('Error saving buy details:', error);
            showNotification('Error saving buy details', 2000);
        });
    }

    // Delete product from overlay function
    function deleteProductFromOverlay() {
        // Get current product data from overlay
        const overlayNameElement = document.querySelector('#reserveOverlay .product-name-overlay');
        const overlayPriceElement = document.querySelector('#reserveOverlay .product-price-overlay');

        if (!overlayNameElement || !overlayPriceElement) {
            showNotification('Product data not available', 2000);
            return;
        }

        const productName = overlayNameElement.textContent;
        const productPrice = overlayPriceElement.textContent.replace(/[^0-9.]/g, '');

        // Get category (try to extract from existing products or use default)
        let productCategory = 'Gown'; // Default category
        const allProductNames = document.querySelectorAll('[id^="productName"]');
        for (let element of allProductNames) {
            if (element.textContent === productName) {
                const boxNumber = element.id.replace('productName', '');
                const categoryElement = document.getElementById(`productCategory${boxNumber}`);
                if (categoryElement) {
                    productCategory = categoryElement.textContent;
                    break;
                }
            }
        }

        // Check if user is logged in
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';

        if (!isLoggedIn) {
            showNotification('Login required', 2000);
            setTimeout(() => {
                window.location.href = 'login_user/login.php';
            }, 1500);
            return;
        }

        // Show custom delete confirmation modal with Lottie animation
        showDeleteConfirmationModal(productName, productCategory);
    }

    // Show delete confirmation modal with Lottie animation
    function showDeleteConfirmationModal(productName, productCategory) {
        // Create modal if it doesn't exist
        let deleteModal = document.getElementById('deleteConfirmationModal');
        if (!deleteModal) {
            deleteModal = document.createElement('div');
            deleteModal.id = 'deleteConfirmationModal';
            deleteModal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 10000;
            `;
            deleteModal.innerHTML = `
                <div id="deleteModalContent" style="background: #2a2a2a; padding: 30px; border-radius: 12px; text-align: center; max-width: 400px; width: 90%;">
                    <h3 style="color: #fff; margin: 20px 0 10px 0;">Delete Product?</h3>
                    <p style="color: #999; margin-bottom: 20px;">Are you sure you want to delete this product from your saved items?</p>
                    <div id="deleteButtons" style="display: flex; gap: 10px; justify-content: center;">
                        <button id="cancelDeleteBtn" style="background: #555; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Cancel</button>
                        <button id="confirmDeleteBtn" style="background: #ff4444; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Delete</button>
                    </div>
                    <div id="deleteAnimationContainer" style="display: none;">
                        <dotlottie-wc id="deleteAnimation" src="https://lottie.host/752787f5-2645-423e-bb57-38e88f5c05db/5Bxijm7F90.lottie" style="width: 100px; height: 100px; margin: 0 auto;" autoplay loop></dotlottie-wc>
                    </div>
                </div>
            `;
            document.body.appendChild(deleteModal);

            // Add event listeners
            document.getElementById('cancelDeleteBtn').addEventListener('click', () => {
                deleteModal.style.display = 'none';
                resetDeleteModal();
            });

            document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
                // Show animation first
                document.getElementById('deleteButtons').style.display = 'none';
                document.getElementById('deleteAnimationContainer').style.display = 'block';
                document.querySelector('#deleteModalContent h3').style.display = 'none';
                document.querySelector('#deleteModalContent p').style.display = 'none';
                document.querySelector('#deleteModalContent').style.background = 'transparent';

                // Execute delete after a short delay
                setTimeout(() => {
                    deleteModal.style.display = 'none';
                    resetDeleteModal();
                    executeDelete(productName, productCategory);
                }, 2000);
            });
        }

        // Reset modal to initial state
        resetDeleteModal();

        // Show modal
        deleteModal.style.display = 'flex';
    }

    // Reset delete modal to initial state
    function resetDeleteModal() {
        const deleteModal = document.getElementById('deleteConfirmationModal');
        if (deleteModal) {
            document.getElementById('deleteButtons').style.display = 'flex';
            document.getElementById('deleteAnimationContainer').style.display = 'none';
            document.querySelector('#deleteModalContent h3').style.display = 'block';
            document.querySelector('#deleteModalContent p').style.display = 'block';
            document.querySelector('#deleteModalContent').style.background = '#2a2a2a';
        }
    }

    // Execute the actual delete operation
    function executeDelete(productName, productCategory) {
        const reserveOverlay = document.getElementById('reserveOverlay');
        const savedProductId = reserveOverlay ? (reserveOverlay.getAttribute('data-saved-product-id') || '') : '';

        // Send delete request to server
        fetch('save_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_product&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}&saved_product_id=${savedProductId}`
        })
        .then(response => response.json())
        .then(data => {
            console.log('Delete response:', data);
            if (data.success) {
                                                                                                                                                                                                                showNotification('Product deleted successfully!', 2000);
                closeReserveOverlay();
                // Reload the page to update the saved products list
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showNotification(data.message || 'Failed to delete product', 2000);
            }
        })
        .catch(error => {
            console.error('Error deleting product:', error);
            showNotification('Error deleting product', 2000);
        });
    }

    // Show notification toast
    function showNotification(message, duration = 3000) {
        const toast = document.getElementById('notificationToast');
        const messageElement = document.getElementById('notificationMessage');
        
        // Set message
        messageElement.textContent = message;
        
        // Show toast with animation
        toast.classList.add('show');
        
        // Play notification sound
        playNotificationSound();
        
        // Hide after duration
        setTimeout(() => {
            toast.classList.remove('show');
        }, duration);
    }

    let pendingDeleteBoxNumber = null;

    function showDeleteConfirmation(boxNumber) {
        pendingDeleteBoxNumber = boxNumber;
        document.getElementById('deleteConfirmOverlay').classList.add('active');
    }

    function confirmDelete() {
        document.getElementById('deleteConfirmOverlay').classList.remove('active');
        if (pendingDeleteBoxNumber !== null) {
            deleteProduct(pendingDeleteBoxNumber);
            pendingDeleteBoxNumber = null;
        }
    }

    function cancelDelete() {
        pendingDeleteBoxNumber = null;
        document.getElementById('deleteConfirmOverlay').classList.remove('active');
    }

    // Handle bfcache / back navigation to ensure checked state is preserved (respecting stock limits)
    window.addEventListener('pageshow', function(event) {
        const savedCheckedIds = JSON.parse(sessionStorage.getItem('checkedCartProductIds') || '[]');
        if (Array.isArray(savedCheckedIds) && savedCheckedIds.length > 0) {
            const productCheckedCounts = {};
            savedCheckedIds.forEach(id => {
                const targetBox = document.querySelector(`.login-box[data-db-id="${id}"]`) ||
                                  document.querySelector(`.login-box[data-product-id="${id}"]`);
                if (targetBox) {
                    const cb = targetBox.querySelector('.checkbox');
                    const gownId = targetBox.getAttribute('data-gown-id') || '';
                    const pName = (targetBox.querySelector('.product-name')?.textContent || '').trim().toLowerCase();
                    const pCat = (targetBox.querySelector('.product-category')?.textContent || '').trim().toLowerCase();
                    const pKey = (gownId && gownId !== '0') ? ('id_' + gownId) : (pName + '_' + pCat);
                    const stock = parseInt(targetBox.getAttribute('data-stock-quantity')) || 0;
                    const qty = parseInt(targetBox.getAttribute('data-quantity')) || 1;

                    if (stock > 0 && ((productCheckedCounts[pKey] || 0) + qty <= stock)) {
                        if (cb) cb.classList.add('checked');
                        productCheckedCounts[pKey] = (productCheckedCounts[pKey] || 0) + qty;
                    }
                }
            });
            updateTotalPrice();
        }
        updateCartCount();
        fetchPurchaseBadgeCount();
    });

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

    window.addEventListener('resize', ensureDropdownContainer);
    window.addEventListener('DOMContentLoaded', () => {
        ensureDropdownContainer();
        fetchNotifications();
    });
    setInterval(fetchNotifications, 5000);
    </script>
</body>

<!-- Notification Toast Container -->
<div id="notificationToast" class="notification-toast">
    <div class="notification-icon">
        <i class="fas fa-check"></i>
    </div>
    <div class="notification-message" id="notificationMessage">
        Success!
    </div>
</div>

<!-- Mobile App Bar -->
<div class="mobile-app-bar">
    <div class="app-bar-content">
        <div class="app-bar-left">
            <div class="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll();" style="margin-right: 10px;">
                <i class="fas fa-check"></i>
            </div>
            <span style="font-size: 14px; color: #ffffff;">All</span>
        </div>
        <div class="app-bar-buttons" style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; width: 100%;">
            <span class="total-price" id="appBarTotalSummary" style="display: inline-flex; flex-direction: column; align-items: flex-start; line-height: 1.1;">
                <span>Total  <span class="price-amount" id="totalPrice">₱0</span></span>
                <span class="saved-price">Saved: <span class="saved-amount" id="totalDiscount">₱0</span></span>
            </span>
            <button id="appBarActionButton" class="reserve-button" onclick="goToProductReserve()" style="height: 40px; border-radius: 5px; box-sizing: border-box; white-space: nowrap; width: auto; min-width: 100px; margin-right: 0;">
                <span>Check Out <span id="checkoutCount">(0)</span></span>
            </button>
            <button id="appBarDeleteButton" class="delete-button" onclick="deleteSelectedProducts()" style="height: 40px; border-radius: 5px; box-sizing: border-box; white-space: nowrap; width: auto; min-width: 80px; margin-right: 15px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s ease;">
                <span>Delete</span>
            </button>
        </div>
    </div>
</div>

<!-- click btn Reserve Now to slide pataas  -->
<div id="reserveOverlay" class="reserve-overlay">
    <div class="reserve-content">
        <!-- Close button (X) in top right -->
        <button type="button" class="close-reserve-btn" onclick="closeReserveOverlay()" aria-label="Close" title="Close">
            <i class="fas fa-times"></i>
        </button>
        <div class="reserve-model">
            <model-viewer 
                src=""
                alt=""
                auto-rotate
                camera-controls
                disable-pan
                disable-zoom
                min-camera-orbit="auto 90deg auto"
                max-camera-orbit="auto 90deg auto"
                shadow-intensity="1"
                style="width: 100%; height: 100%; cursor: default;">
            </model-viewer>
            <div class="product-name-overlay">
                Selected Product
            </div>
             <div class="product-price-overlay">
                ₱0
            </div>
            <div class="product-purchase-price-overlay">
                Selling Price: <span style="color: red;">0</span>
            </div>
            <div class="product-stock-overlay">
                Stock: Available
            </div>
        </div>
        
    
        <div class="reservation-form">
            <!-- Buy Schedule Notice Banner -->
            <div id="buyScheduleNotice" style="display: none; background: rgba(255, 170, 0, 0.12); border: 1px solid rgba(255, 170, 0, 0.45); border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; color: #fff; font-size: 0.84rem; line-height: 1.45;">
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <i class="fas fa-info-circle" style="color: #ffaa00; font-size: 1.15rem; margin-top: 2px; flex-shrink: 0;"></i>
                    <div>
                        <strong style="color: #ffaa00; display: block; margin-bottom: 3px;">Upcoming Rental Active</strong>
                        <span id="buyScheduleText" style="color: #ddd;">This product is reserved for rental. Purchase pickup is available after existing rentals return.</span>
                    </div>
                </div>
            </div>

            <!-- Type Selection (Reserve / Buy) with Shade Circle like Walk-In Calendar -->
            <div class="type-selection-group">
                <label class="type-label-heading">Type</label>
                <div class="type-radio-row">
                    <label class="type-radio-item" onclick="setReservationType('reserve')">
                        <span class="type-circle-radio active" id="typeRadioReserve">
                            <span class="type-circle-dot"></span>
                        </span>
                        <span class="type-radio-text" id="typeTextReserve" style="color: #fff; font-weight: 600;">Reserve</span>
                    </label>
                    <label class="type-radio-item" onclick="setReservationType('buy')">
                        <span class="type-circle-radio" id="typeRadioBuy">
                            <span class="type-circle-dot"></span>
                        </span>
                        <span class="type-radio-text" id="typeTextBuy">Buy</span>
                    </label>
                </div>
            </div>

            <div class="form-row date-row">
                <div class="form-group" id="reserveDateGroup">
                    <label for="reserveDate" id="reserveDateLabel">Reserve Date</label>
                    <input type="text" id="reserveDate" name="reserveDate" readonly placeholder="Select date" onclick="openCalendarModal('reserveDate')" required>
                </div>
                <div class="form-group" id="returnDateGroup">
                    <label for="returnDate" id="returnDateLabel">Return Date</label>
                    <input type="text" id="returnDate" name="returnDate" readonly placeholder="Select date" onclick="openCalendarModal('returnDate')" required>
                </div>
            </div>

            <!-- Quantity Selector under Reserve Date -->
            <div class="quantity-group" id="reservationQuantityGroup">
                <div class="quantity-row">
                    <label class="quantity-label">Quantity</label>
                    <div class="quantity-selector">
                        <button type="button" class="quantity-btn quantity-minus" onclick="decrementQuantity()">-</button>
                        <input type="number" id="totalQuantity" name="totalQuantity" class="quantity-input" value="1" min="1" max="999" readonly>
                        <button type="button" class="quantity-btn quantity-plus" onclick="incrementQuantity()">+</button>
                    </div>
                </div>
            </div>
            <!-- Overdue Policy Notice (No Box / No Checkbox, Click % to see fee) -->
            <div class="overdue-policy-notice">
                Overdue fee: <span class="overdue-percent-btn" onclick="openOverdueCalcModal(event)" title="Click to view fee"><?php echo $overduePenaltyPercent; ?>%</span>/day based on price
            </div>
            <div class="guide-line"></div>
            <div id="measurementsContainer">
                <!-- Dynamic size and measurement fields will be generated here -->
            </div>
        </div>

        <div class="button-container">
            <button type="button" class="back-submit-btn" onclick="closeReserveOverlay()">Back</button>
            <button type="button" class="save-submit-btn" onclick="handleOverlaySave()">Save</button>
        </div>
    </div>
</div>

<div id="calendarModalOverlay" class="calendar-modal-overlay" onclick="closeCalendarModal(event)">
    <div class="calendar-modal" onclick="event.stopPropagation()">
        <div class="calendar-modal-header">
            <div class="calendar-modal-nav">
                <button type="button" onclick="changeCalendarMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                <div class="calendar-modal-title" id="calendarModalTitle">July 2026</div>
                <button type="button" onclick="changeCalendarMonth(1)"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        <div id="calendarModalGuide" class="calendar-modal-guide">
            <!-- Dynamic guide text and legend will be rendered here -->
        </div>
        <div class="calendar-grid" id="calendarModalGrid">
            <!-- Days will be generated by JavaScript -->
        </div>
    </div>
</div>

<!-- Availability Adjustment Modal -->
<div id="availabilityPromptModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 100002; justify-content: center; align-items: center;" onclick="closeAvailabilityModal(false)">
    <div style="background: #191b1f; border: 1px solid rgba(255, 180, 0, 0.45); border-radius: 16px; padding: 26px; width: 92%; max-width: 440px; box-shadow: 0 20px 50px rgba(0,0,0,0.8), 0 0 30px rgba(255, 180, 0, 0.15); color: #fff; text-align: left;" onclick="event.stopPropagation()">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(255, 180, 0, 0.15); border: 1px solid rgba(255, 180, 0, 0.4); display: flex; align-items: center; justify-content: center; color: #ffb400; font-size: 1.3rem; flex-shrink: 0;">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 600; color: #fff;">Adjust Return Date</h3>
                <p style="margin: 3px 0 0 0; font-size: 0.78rem; color: #aaa;">Limited stock available for this period</p>
            </div>
        </div>

        <p id="availabilityModalMsg" style="font-size: 0.88rem; color: #ddd; line-height: 1.5; margin-bottom: 18px;">
            The standard rental period is <strong>20 days</strong>, but only <strong>13 days</strong> are available before subsequent dates are booked.
        </p>

        <div style="background: #111215; border: 1px solid #282a30; border-radius: 12px; padding: 14px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.82rem;">
                <span style="color: #888;">Reserve Date:</span>
                <strong id="availModalReserve" style="color: #fff;">Aug 15, 2026</strong>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.82rem;">
                <span style="color: #888;">Requested Duration:</span>
                <span id="availModalRequested" style="color: #ff8888; text-decoration: line-through;">20 Days</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.85rem; background: rgba(108, 212, 136, 0.1); padding: 8px 10px; border-radius: 8px; border: 1px solid rgba(108, 212, 136, 0.3);">
                <span style="color: #6cd488; font-weight: 600;">Max Available Return:</span>
                <strong id="availModalMaxReturn" style="color: #6cd488; font-size: 0.95rem;">Aug 28, 2026 (13 Days)</strong>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; color: #e57373;">
                <span>Out of stock from:</span>
                <span id="availModalConflict"><i class="fas fa-ban"></i> Aug 29, 2026</span>
            </div>
        </div>

        <p style="font-size: 0.84rem; color: #aaa; margin-bottom: 22px; text-align: center;">
            Click <strong>OK</strong> to confirm your reservation for <strong id="availModalConfirmDays" style="color: #6cd488;">13 days</strong>, or pick a different date.
        </p>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" onclick="closeAvailabilityModal(false)" style="padding: 10px 18px; border-radius: 8px; background: #262830; color: #ccc; border: 1px solid #383b45; cursor: pointer; font-size: 0.85rem; font-weight: 500;">Pick Another Date</button>
            <button type="button" onclick="confirmAvailabilityAdjustment()" style="padding: 10px 22px; border-radius: 8px; background: linear-gradient(135deg, #6cd488, #48bb78); color: #092011; font-weight: 700; border: none; cursor: pointer; font-size: 0.88rem; box-shadow: 0 4px 15px rgba(108, 212, 136, 0.35); display: inline-flex; align-items: center; gap: 6px;">
                <i class="fas fa-check"></i> OK, Confirm <span id="availBtnDaysText">13 Days</span>
            </button>
        </div>
    </div>
</div>

<!-- Upcoming Rental Active Modal -->
<div id="upcomingRentalModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 100002; justify-content: center; align-items: center;" onclick="closeUpcomingRentalModal(event)">
    <div style="background: #191b1f; border: 1px solid rgba(255, 170, 0, 0.45); border-radius: 16px; padding: 26px; width: 92%; max-width: 440px; box-shadow: 0 20px 50px rgba(0,0,0,0.8), 0 0 30px rgba(255, 170, 0, 0.15); color: #fff; text-align: left;" onclick="event.stopPropagation()">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(255, 170, 0, 0.15); border: 1px solid rgba(255, 170, 0, 0.4); display: flex; align-items: center; justify-content: center; color: #ffaa00; font-size: 1.3rem; flex-shrink: 0;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 600; color: #fff;">Upcoming Rental Active</h3>
                <p style="margin: 3px 0 0 0; font-size: 0.78rem; color: #aaa;">Product currently reserved for rental</p>
            </div>
        </div>

        <p style="font-size: 0.88rem; color: #ddd; line-height: 1.5; margin-bottom: 18px;">
            This product has active rental reservations. Purchase claim/pickup is only available after existing rentals return.
        </p>

        <div style="background: #111215; border: 1px solid #282a30; border-radius: 12px; padding: 14px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.82rem;">
                <span style="color: #888;">Active Rental Until:</span>
                <strong id="upcomingModalRentalEnd" style="color: #ff8888;">Aug 24, 2026</strong>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.82rem;">
                <span style="color: #888;">Your Selected Date:</span>
                <span id="upcomingModalSelectedDate" style="color: #ff8888; text-decoration: line-through;">Not selected</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; background: rgba(255, 215, 0, 0.1); padding: 8px 10px; border-radius: 8px; border: 1px solid rgba(255, 215, 0, 0.3);">
                <span style="color: #ffd700; font-weight: 600;">Earliest Available Pickup:</span>
                <strong id="upcomingModalEarliestDate" style="color: #ffd700; font-size: 0.95rem;">Aug 25, 2026</strong>
            </div>
        </div>

        <p style="font-size: 0.84rem; color: #aaa; margin-bottom: 22px; text-align: center;">
            Please change your date to <strong id="upcomingModalConfirmDate" style="color: #ffd700;">Aug 25, 2026</strong> or later to proceed with the purchase.
        </p>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" onclick="closeUpcomingRentalModalAndPickDate()" style="padding: 10px 18px; border-radius: 8px; background: #262830; color: #ccc; border: 1px solid #383b45; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                <i class="far fa-calendar-alt" style="margin-right: 4px;"></i> Pick Date
            </button>
            <button type="button" onclick="acceptUpcomingRentalDateAndBuy()" style="padding: 10px 20px; border-radius: 8px; background: linear-gradient(135deg, #ffd700, #e6b800); color: #111; font-weight: 700; border: none; cursor: pointer; font-size: 0.88rem; box-shadow: 0 4px 15px rgba(255, 215, 0, 0.35); display: inline-flex; align-items: center; gap: 6px;">
                <i class="fas fa-check"></i> Set Date & Buy
            </button>
        </div>
    </div>
</div>

<div id="topOverlay" class="top-overlay"></div>

<!-- Delete Confirmation Overlay -->
<div id="deleteConfirmOverlay" class="delete-confirm-overlay">
    <div class="delete-confirm-dialog">
        <h3>Delete Product?</h3>
        <p>Are you sure you want to delete this item?</p>
        <div class="delete-confirm-buttons">
            <button class="delete-confirm-cancel" onclick="cancelDelete();">Cancel</button>
            <button class="delete-confirm-delete" onclick="confirmDelete();">Delete</button>
        </div>
    </div>
</div>

<!-- Overdue Calculation Breakdown Modal -->
<div id="overdueCalcModal" class="overdue-calc-overlay" onclick="closeOverdueCalcModal(event)">
    <div class="overdue-calc-modal" onclick="event.stopPropagation()">
        <div class="overdue-calc-header">
            <h3>Overdue Fee</h3>
            <button type="button" class="overdue-calc-close-btn" onclick="closeOverdueCalcModal(event)">&times;</button>
        </div>
        <div class="overdue-calc-body">
            <div style="background: #15171c; border: 1px solid #2d313a; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                <div style="font-size: 0.85rem; color: #aaa; margin-bottom: 8px;">Product: <strong id="overdueCalcProdName" style="color: #fff;">Selected Product</strong></div>
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 6px;">
                    <span style="color: #888;">Rental Price:</span>
                    <strong id="overdueCalcRentalPrice" style="color: #eee;">₱0.00</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem;">
                    <span style="color: #888;">Overdue Rate:</span>
                    <strong style="color: #ffd700;"><?php echo $overduePenaltyPercent; ?>% / day</strong>
                </div>
            </div>

            <div style="background: #111215; border: 1px solid #2c303a; border-radius: 8px; padding: 14px; margin-bottom: 14px; text-align: center;">
                <div style="font-size: 1rem; color: #fff; font-weight: 600;">
                    <span id="overdueCalcFormulaPrice">₱0.00</span> &times; <?php echo $overduePenaltyPercent; ?>% = <span id="overdueCalcFeePerDay" style="color: #ff4d4f; font-size: 1.15rem; font-weight: 700;">₱0.00</span> / day
                </div>
            </div>

            <div style="color: #ff4d4f; font-size: 0.8rem; background: rgba(255, 77, 79, 0.08); border: 1px solid rgba(255, 77, 79, 0.3); border-radius: 8px; padding: 10px 12px; margin-bottom: 16px; line-height: 1.45;">
                Important: You will be charged an extra <strong id="overdueCalcWarningFee" style="color: #ff4d4f;">₱0.00 per day</strong> for late returns beyond the agreed return date.
            </div>

            <button type="button" onclick="closeOverdueCalcModal(event)" style="width: 100%; padding: 10px; background: #2b2e36; border: 1px solid #3c404b; border-radius: 8px; color: #fff; font-size: 0.88rem; font-weight: 600; cursor: pointer;">Close</button>
        </div>
    </div>
</div>

<!-- Batch Delete Confirmation Overlay -->
<div id="batchDeleteConfirmOverlay" class="delete-confirm-overlay">
    <div class="delete-confirm-dialog">
        <h3>Delete Selected?</h3>
        <p>Are you sure you want to delete the selected items?</p>
        <div class="delete-confirm-buttons">
            <button class="delete-confirm-cancel" onclick="cancelDeleteSelected();">Cancel</button>
            <button class="delete-confirm-delete" onclick="confirmDeleteSelected();">Delete</button>
        </div>
    </div>
</div>
</html>
