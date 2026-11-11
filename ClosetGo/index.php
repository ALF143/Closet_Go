<?php
session_start();
require_once 'database/db_config.php';

// ===== CHAT AJAX ENDPOINT DISPATCHER (Bypasses ProFreeHost 403 Forbidden) =====
if (isset($_REQUEST['chat_action'])) {
    $chatAction = $_REQUEST['chat_action'];
    if ($chatAction === 'send') {
        require_once __DIR__ . '/chat_send.php';
        exit();
    }
    if ($chatAction === 'get') {
        require_once __DIR__ . '/chat_get.php';
        exit();
    }
    if ($chatAction === 'get_items') {
        require_once __DIR__ . '/chat_get_items.php';
        exit();
    }
    if ($chatAction === 'mark_read') {
        require_once __DIR__ . '/chat_mark_read.php';
        exit();
    }
    if ($chatAction === 'get_users') {
        require_once __DIR__ . '/chat_get_users.php';
        exit();
    }
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
        $stmt->close();
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

    $productId = $_POST['product_id'] ?? '';

    $database = new Database();
    $conn = $database->getConnection();

    if ($conn) {
        $sql = "SELECT size_1_min, size_1_max, size_2_min, size_2_max, size_3_min, size_3_max, size_4_min, size_4_max, size_5_min, size_5_max, size_6_min, size_6_max, size_7_min, size_7_max, size_8_min, size_8_max, size_9_min, size_9_max, size_10_min, size_10_max FROM gowns WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                'success' => true,
                'size_1_min' => $row['size_1_min'] ?? null,
                'size_1_max' => $row['size_1_max'] ?? null,
                'size_2_min' => $row['size_2_min'] ?? null,
                'size_2_max' => $row['size_2_max'] ?? null,
                'size_3_min' => $row['size_3_min'] ?? null,
                'size_3_max' => $row['size_3_max'] ?? null,
                'size_4_min' => $row['size_4_min'] ?? null,
                'size_4_max' => $row['size_4_max'] ?? null,
                'size_5_min' => $row['size_5_min'] ?? null,
                'size_5_max' => $row['size_5_max'] ?? null,
                'size_6_min' => $row['size_6_min'] ?? null,
                'size_6_max' => $row['size_6_max'] ?? null,
                'size_7_min' => $row['size_7_min'] ?? null,
                'size_7_max' => $row['size_7_max'] ?? null,
                'size_8_min' => $row['size_8_min'] ?? null,
                'size_8_max' => $row['size_8_max'] ?? null,
                'size_9_min' => $row['size_9_min'] ?? null,
                'size_9_max' => $row['size_9_max'] ?? null,
                'size_10_min' => $row['size_10_min'] ?? null,
                'size_10_max' => $row['size_10_max'] ?? null
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'size_1_min' => null,
                'size_1_max' => null,
                'size_2_min' => null,
                'size_2_max' => null,
                'size_3_min' => null,
                'size_3_max' => null,
                'size_4_min' => null,
                'size_4_max' => null,
                'size_5_min' => null,
                'size_5_max' => null,
                'size_6_min' => null,
                'size_6_max' => null,
                'size_7_min' => null,
                'size_7_max' => null,
                'size_8_min' => null,
                'size_8_max' => null,
                'size_9_min' => null,
                'size_9_max' => null,
                'size_10_min' => null,
                'size_10_max' => null
            ]);
        }
        $stmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'size_1_min' => null,
            'size_1_max' => null,
            'size_2_min' => null,
            'size_2_max' => null,
            'size_3_min' => null,
            'size_3_max' => null,
            'size_4_min' => null,
            'size_4_max' => null,
            'size_5_min' => null,
            'size_5_max' => null,
            'size_6_min' => null,
            'size_6_max' => null,
            'size_7_min' => null,
            'size_7_max' => null,
            'size_8_min' => null,
            'size_8_max' => null,
            'size_9_min' => null,
            'size_9_max' => null,
            'size_10_min' => null,
            'size_10_max' => null
        ]);
    }
    exit();
}

// Check if viewing specific product
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id > 0) {
    $database = new Database();
    $conn = $database->getConnection();

    if ($conn) {
        $sql = "SELECT * FROM gowns WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            header("Location: index.php");
            exit();
        }

        $product = $result->fetch_assoc();
        $stmt->close();

// User profile variables for top navigation
$isLoggedIn = false;
$displayName = '';
$userProfileImage = 'images/icon/profile_logo.png';
$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
$userEmail = isset($_SESSION['email']) ? $_SESSION['email'] : (isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '');

if ($userId > 0 || !empty($userEmail)) {
    try {
        if ($conn) {
            if ($userId > 0) {
                $userStmt = $conn->prepare("SELECT id, email, first_name, middle_name, last_name, suffix, province, city, barangay, phone, profile_image, id_type, id_front_image, id_back_image, id_verified FROM system_users WHERE id = ?");
                $userStmt->bind_param("i", $userId);
            } else {
                $userStmt = $conn->prepare("SELECT id, email, first_name, middle_name, last_name, suffix, province, city, barangay, phone, profile_image, id_type, id_front_image, id_back_image, id_verified FROM system_users WHERE email = ?");
                $userStmt->bind_param("s", $userEmail);
            }
            if ($userStmt) {
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                if ($userResult && $userResult->num_rows > 0) {
                    $uRow = $userResult->fetch_assoc();
                    $isLoggedIn = true;
                    $userId = intval($uRow['id']);
                    $_SESSION['user_id'] = $uRow['id'];
                    $_SESSION['email'] = $uRow['email'];
                    
                    $fullName = trim(($uRow['first_name'] ?? '') . ' ' . ($uRow['last_name'] ?? ''));
                    if (!empty($fullName)) {
                        $displayName = $fullName;
                    } elseif (!empty($uRow['first_name'])) {
                        $displayName = $uRow['first_name'];
                    } elseif (!empty($uRow['email'])) {
                        $displayName = $uRow['email'];
                    }
                    if (!empty($uRow['profile_image'])) {
                        $userProfileImage = $uRow['profile_image'];
                    }
                }
                $userStmt->close();
            }
        }
    } catch (Exception $e) {}
}

// Get current user info for JavaScript
$currentUser = [
    'id' => $userId,
    'email' => $userEmail,
    'isLoggedIn' => $isLoggedIn,
    'first_name' => $uRow['first_name'] ?? '',
    'middle_name' => $uRow['middle_name'] ?? '',
    'last_name' => $uRow['last_name'] ?? '',
    'suffix' => $uRow['suffix'] ?? '',
    'province' => $uRow['province'] ?? '',
    'city' => $uRow['city'] ?? '',
    'barangay' => $uRow['barangay'] ?? '',
    'phone' => $uRow['phone'] ?? '',
    'id_type' => $uRow['id_type'] ?? '',
    'id_front_image' => $uRow['id_front_image'] ?? '',
    'id_back_image' => $uRow['id_back_image'] ?? '',
    'id_verified' => intval($uRow['id_verified'] ?? 0)
];

// Fetch rental duration days & overdue penalty settings
$rentalDurationDays = 10;
$overduePenaltyPercent = 10;
$maxExtensionDays = 7;
$extensionPricePerDay = 100;
try {
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
    error_log("Error fetching rental settings: " . $e->getMessage());
}

// Pre-fetch category size data server-side so sizes render with 0 network latency
$categorySizeData = null;
$categoryName = $product['category'] ?? '';
if (!empty($categoryName) && $conn) {
    try {
        $catStmt = $conn->prepare("SELECT size_label_1, size_label_2, size_label_3, size_label_4, size_label_5, size_label_6, size_label_7, size_label_8, size_label_9, size_label_10, size_guide_image FROM categories WHERE LOWER(TRIM(category_name)) = LOWER(TRIM(?)) LIMIT 1");
        if ($catStmt) {
            $catStmt->bind_param("s", $categoryName);
            $catStmt->execute();
            $catRes = $catStmt->get_result();
            if ($catRes && $catRow = $catRes->fetch_assoc()) {
                $categorySizeData = $catRow;
            }
            $catStmt->close();
        }
    } catch (Exception $e) {}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($product['name']); ?> - ClosetGo</title>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/homepage.css?v=<?php echo filemtime('css/homepage.css'); ?>">
    <?php if (!empty($product['model_path'])): ?>
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.1.1/model-viewer.min.js"></script>
    <?php endif; ?>
    <script defer src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.14/dist/dotlottie-wc.js" type="module"></script>

    <style>
        /* Type Selection (Reserve / Buy) with Shade Circle like in save.php */
        .type-selection-group {
            margin-bottom: 10px;
        }

        /* Laptop Banner Layout */
        @media (min-width: 768px) {
            #rightBanners {
                display: flex !important;
            }
            #bannerCarousel {
                height: 240px;
            }
            #bannerCarousel .banner-slide-img {
                height: 240px;
            }
            #topProductsBanner, #discountProductsBanner {
                height: 115px;
            }
            #topProductsBannerGrid, #discountProductsBannerGrid {
                height: 100%;
            }
        }
        @media (max-width: 767px) {
            #bannerLayout {
                flex-direction: column !important;
            }
            #rightBanners {
                display: none !important;
            }
            #bannerCarousel {
                height: 120px;
            }
        }
        @media (max-width: 768px) {
            .mobile-top-icons .mobile-top-search,
            .mobile-top-search {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                pointer-events: none !important;
                opacity: 0 !important;
                visibility: hidden !important;
            }

            .mobile-top-icons .purchase-top-container {
                display: none !important;
                width: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .top-bar .cart-container,
            .mobile-top-icons .cart-container,
            .cart-container {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 23px !important;
                height: 23px !important;
                margin: 0 !important;
                margin-right: 0 !important;
                padding: 0 !important;
                position: relative !important;
                cursor: pointer !important;
            }

            .mobile-top-icons .cart-container img,
            .cart-container img {
                width: 23px !important;
                height: 23px !important;
                object-fit: contain !important;
                display: block !important;
            }

            .mobile-top-icons {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                margin-left: auto !important;
                margin-right: 2px !important;
            }
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
        .reservation-form .date-row .form-group input.date-input-disabled {
            cursor: not-allowed !important;
            opacity: 0.5 !important;
            background: rgba(255, 255, 255, 0.05) !important;
        }
        .reservation-form .date-row .form-group label,
        .reservation-form .form-group #reserveDateLabel,
        .reservation-form .form-group #returnDateLabel {
            display: block !important;
        }
        .checkout-submit-btn {
            flex: 1;
            background: #2a2a2a;
            color: #ffffff;
            border: none;
            padding: 8px 12px;
            font-size: 0.8rem;
            font-weight: 550;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: inset 0 0 15px rgba(124, 99, 0, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
        }
        .checkout-submit-btn:hover {
            background: #3a3a3a;
            transform: translateY(-2px);
            box-shadow: inset 0 0 20px rgba(124, 99, 0, 0.6);
        }
        .checkout-submit-btn:active {
            transform: translateY(0);
            box-shadow: inset 0 0 25px rgba(124, 99, 0, 0.8);
        }
        .back-submit-btn {
            display: none;
            flex: 1;
            background: #2a2a2a;
            color: #ffffff;
            border: none;
            padding: 8px 12px;
            font-size: 0.8rem;
            font-weight: 550;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: inset 0 0 15px rgba(255, 255, 255, 0.08);
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
        }
        .back-submit-btn:hover {
            background: #3a3a3a;
            transform: translateY(-2px);
        }
        .back-submit-btn:active {
            transform: translateY(0);
        }
        @media (max-width: 768px) {
            .close-reserve-btn {
                display: none !important;
            }
            .back-submit-btn {
                display: flex !important;
            }
        }
        /* Quantity Group in Reservation Form under Reserve Date */
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
        .reservation-form .measurement-group {
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
            border-bottom: none !important;
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
        /* Calendar Modal Guide and Rental Gap Styling (like save.php) */
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
        .calendar-modal-guide .legend-chip.red-gap .chip-box,
        .calendar-modal-guide .legend-chip.gold-gap .chip-box {
            background: rgba(220, 53, 69, 0.4);
            border: 1px solid #ff4444;
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
            background: rgba(220, 53, 69, 0.3) !important;
            color: #ff9999 !important;
            border: 1px solid rgba(220, 53, 69, 0.75) !important;
            font-weight: 600 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            user-select: none !important;
        }
        .calendar-day.rental-gap:hover {
            background: rgba(220, 53, 69, 0.3) !important;
            cursor: not-allowed !important;
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
            border: 1px solid rgba(220, 53, 69, 0.6) !important;
            opacity: 0.85 !important;
            pointer-events: none !important;
        }
        .calendar-day.disabled.exceeds-duration {
            background: #191919 !important;
            color: #dd4444 !important;
            cursor: not-allowed !important;
            opacity: 0.35 !important;
            border-color: transparent !important;
            text-decoration: none !important;
            pointer-events: none !important;
        }
        .calendar-day.disabled.past-date {
            background: #141414 !important;
            color: #555555 !important;
            cursor: not-allowed !important;
            opacity: 0.3 !important;
            border-color: transparent !important;
            pointer-events: none !important;
        }
    </style>

    <script>
    // Pass PHP session data to JavaScript
    const currentUser = <?php echo json_encode($currentUser); ?>;
    window.rentalDurationDays = <?php echo intval($rentalDurationDays); ?>;
    
    // Sync localStorage with PHP session
    if (currentUser.isLoggedIn) {
        localStorage.setItem('isLoggedIn', 'true');
        localStorage.setItem('userId', currentUser.id);
        localStorage.setItem('userEmail', currentUser.email);
        if (currentUser.first_name) localStorage.setItem('userFirstName', currentUser.first_name);
        if (currentUser.middle_name) localStorage.setItem('userMiddleName', currentUser.middle_name);
        if (currentUser.last_name) localStorage.setItem('userLastName', currentUser.last_name);
        if (currentUser.suffix) localStorage.setItem('userSuffix', currentUser.suffix);
        if (currentUser.province) localStorage.setItem('userProvince', currentUser.province);
        if (currentUser.city) localStorage.setItem('userCity', currentUser.city);
        if (currentUser.barangay) localStorage.setItem('userBarangay', currentUser.barangay);
        if (currentUser.phone) localStorage.setItem('userPhone', currentUser.phone);
        if (currentUser.id_type) {
            localStorage.setItem('userIdType', currentUser.id_type);
        } else {
            localStorage.removeItem('userIdType');
        }
        if (currentUser.id_front_image) {
            localStorage.setItem('userIdFrontImage', currentUser.id_front_image);
        } else {
            localStorage.removeItem('userIdFrontImage');
        }
        if (currentUser.id_back_image) {
            localStorage.setItem('userIdBackImage', currentUser.id_back_image);
        } else {
            localStorage.removeItem('userIdBackImage');
        }
        localStorage.setItem('userIdVerified', String(currentUser.id_verified || 0));
        console.log('User logged in - synced localStorage:', currentUser);
    } else {
        localStorage.setItem('isLoggedIn', 'false');
        console.log('User not logged in - localStorage cleared');
    }
    
let lastMediaClickTime = 0;

function isMobileView() {
    return window.innerWidth <= 768;
}

function toggleMobileMediaExpand() {
    const mediaViewer = document.getElementById('productMediaViewer') || document.querySelector('.product-model-viewer');
    if (!mediaViewer) return;
    
    const isExpanded = mediaViewer.classList.toggle('is-expanded');
    const expandIcon = document.getElementById('sliderExpandIcon');
    const expandBtn = document.getElementById('sliderExpandBtn');
    
    if (expandIcon) {
        expandIcon.className = isExpanded ? 'fas fa-compress-alt' : 'fas fa-expand-alt';
    }
    if (expandBtn) {
        expandBtn.setAttribute('title', isExpanded ? 'Collapse view' : 'Expand view to see more');
        expandBtn.setAttribute('aria-label', isExpanded ? 'Collapse view' : 'Expand view to see more');
    }
}

function handleMediaClick(slideIndex) {
    const now = Date.now();
    if (now - lastMediaClickTime < 380) {
        return; // Guard against duplicate synthetic clicks on touch devices
    }
    lastMediaClickTime = now;

    if (typeof slideIndex !== 'number') {
        slideIndex = typeof currentSlide === 'number' ? currentSlide : 0;
    }
    if (isMobileView()) {
        toggleMobileMediaExpand();
    } else {
        openProductMediaModal(slideIndex);
    }
}

let currentModalIndex = 0;
let modalMediaItems = [];

function getProductMediaItems() {
    const sliderTrack = document.getElementById('sliderTrack');
    if (!sliderTrack) return [];
    const slides = sliderTrack.querySelectorAll('.slider-slide');
    const items = [];
    slides.forEach((slide, idx) => {
        const mv = slide.querySelector('model-viewer');
        const img = slide.querySelector('img');
        if (mv) {
            items.push({
                type: 'model',
                src: mv.getAttribute('src') || '',
                title: mv.getAttribute('alt') || '3D Model View',
                index: idx
            });
        } else if (img) {
            items.push({
                type: 'image',
                src: img.getAttribute('src') || '',
                title: img.getAttribute('alt') || 'Product Image',
                index: idx
            });
        }
    });
    return items;
}

function openProductMediaModal(slideIndex = 0) {
    modalMediaItems = getProductMediaItems();
    if (!modalMediaItems.length) return;

    const existingModal = document.getElementById('productMediaModal');
    if (existingModal) existingModal.remove();

    currentModalIndex = Math.max(0, Math.min(slideIndex, modalMediaItems.length - 1));

    const overlay = document.createElement('div');
    overlay.id = 'productMediaModal';
    overlay.className = 'product-media-modal-overlay';

    const hasNav = modalMediaItems.length > 1;

    overlay.innerHTML = `
        <div class="product-media-modal-card" id="productMediaModalCard">
            <div class="product-media-modal-header">
                <div class="pmm-header-left">
                    <span class="pmm-title"><?php echo htmlspecialchars($product['name'] ?? 'Product Media'); ?></span>
                    <span class="pmm-badge" id="pmmBadge"></span>
                </div>
                <button type="button" class="pmm-close-btn" id="pmmCloseBtn" title="Close modal" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="product-media-modal-body" id="pmmBody"></div>
            ${hasNav ? `
                <button type="button" class="pmm-nav-arrow pmm-prev-arrow" id="pmmPrevBtn" aria-label="Previous">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="pmm-nav-arrow pmm-next-arrow" id="pmmNextBtn" aria-label="Next">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="pmm-dots-container" id="pmmDots"></div>
            ` : ''}
        </div>
    `;

    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';

    function renderModalSlide(index) {
        currentModalIndex = index;
        const item = modalMediaItems[currentModalIndex];
        const body = document.getElementById('pmmBody');
        const badge = document.getElementById('pmmBadge');
        if (!body || !item) return;

        if (typeof stopSoft3DRotation === 'function') {
            stopSoft3DRotation();
        }
        body.innerHTML = '';

        if (item.type === 'model') {
            if (badge) badge.textContent = `3D Model (${currentModalIndex + 1}/${modalMediaItems.length})`;
            const mv = document.createElement('model-viewer');
            mv.src = item.src;
            mv.alt = item.title;
            mv.setAttribute('bounds', 'tight');
            mv.setAttribute('auto-rotate', '');
            mv.setAttribute('rotation-per-second', '14deg');
            mv.setAttribute('auto-rotate-delay', '1500');
            mv.setAttribute('camera-controls', '');
            mv.setAttribute('disable-pan', '');
            mv.setAttribute('disable-zoom', '');
            mv.setAttribute('camera-orbit', '0deg 90deg 100%');
            mv.setAttribute('min-camera-orbit', '-Infinity 90deg 100%');
            mv.setAttribute('max-camera-orbit', 'Infinity 90deg 100%');
            mv.setAttribute('shadow-intensity', '0');
            mv.setAttribute('interaction-prompt', 'none');
            mv.setAttribute('power-preference', 'high-performance');
            mv.style.width = '100%';
            mv.style.height = '100%';
            mv.style.cursor = 'grab';
            mv.style.touchAction = 'none';
            mv.style.background = 'radial-gradient(circle at 50% 45%, #202330 0%, #12131a 100%)';
            body.appendChild(mv);
            attachModelViewerEvents(mv);
            if (typeof startSoft3DRotation === 'function') {
                startSoft3DRotation(mv);
            }
        } else {
            if (badge) badge.textContent = `Photo (${currentModalIndex + 1}/${modalMediaItems.length})`;
            const img = document.createElement('img');
            img.src = item.src;
            img.alt = item.title;
            img.className = 'pmm-img-content';
            body.appendChild(img);
        }

        const dots = document.querySelectorAll('.pmm-dot');
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === currentModalIndex);
        });

        if (typeof goToSlide === 'function') {
            goToSlide(currentModalIndex);
        }
    }

    if (hasNav) {
        const dotsContainer = document.getElementById('pmmDots');
        if (dotsContainer) {
            dotsContainer.innerHTML = '';
            modalMediaItems.forEach((_, i) => {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'pmm-dot' + (i === currentModalIndex ? ' active' : '');
                dot.setAttribute('aria-label', `Slide ${i + 1}`);
                dot.onclick = (e) => {
                    e.stopPropagation();
                    renderModalSlide(i);
                };
                dotsContainer.appendChild(dot);
            });
        }

        const prevBtn = document.getElementById('pmmPrevBtn');
        const nextBtn = document.getElementById('pmmNextBtn');
        if (prevBtn) {
            prevBtn.onclick = (e) => {
                e.stopPropagation();
                let nextIdx = currentModalIndex - 1;
                if (nextIdx < 0) nextIdx = modalMediaItems.length - 1;
                renderModalSlide(nextIdx);
            };
        }
        if (nextBtn) {
            nextBtn.onclick = (e) => {
                e.stopPropagation();
                let nextIdx = currentModalIndex + 1;
                if (nextIdx >= modalMediaItems.length) nextIdx = 0;
                renderModalSlide(nextIdx);
            };
        }
    }

    renderModalSlide(currentModalIndex);

    function closeModal() {
        if (typeof stopSoft3DRotation === 'function') {
            stopSoft3DRotation();
        }
        const sliderMV = document.getElementById('sliderModelViewer');
        if (sliderMV && typeof isCurrentSlide3D === 'function' && isCurrentSlide3D() && typeof startSoft3DRotation === 'function') {
            startSoft3DRotation(sliderMV);
        }
        overlay.classList.add('closing');
        setTimeout(() => {
            overlay.remove();
            document.body.style.overflow = '';
        }, 200);
        document.removeEventListener('keydown', handleKeydown);
    }

    function handleKeydown(e) {
        if (e.key === 'Escape') {
            closeModal();
        } else if (e.key === 'ArrowLeft' && hasNav) {
            let nextIdx = currentModalIndex - 1;
            if (nextIdx < 0) nextIdx = modalMediaItems.length - 1;
            renderModalSlide(nextIdx);
        } else if (e.key === 'ArrowRight' && hasNav) {
            let nextIdx = currentModalIndex + 1;
            if (nextIdx >= modalMediaItems.length) nextIdx = 0;
            renderModalSlide(nextIdx);
        }
    }

    document.addEventListener('keydown', handleKeydown);

    const closeBtn = document.getElementById('pmmCloseBtn');
    if (closeBtn) closeBtn.onclick = closeModal;

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closeModal();
        }
    });
}

function openFullscreenImage(imageSrc, imageTitle) {
    const overlay = document.createElement('div');
    overlay.className = 'product-media-modal-overlay';
    
    overlay.innerHTML = `
        <div class="product-media-modal-card">
            <div class="product-media-modal-header">
                <div class="pmm-header-left">
                    <span class="pmm-title">${imageTitle || 'Image View'}</span>
                </div>
                <button type="button" class="pmm-close-btn" title="Close" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="product-media-modal-body">
                <img src="${imageSrc}" alt="${imageTitle || ''}" class="pmm-img-content">
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
    
    function closeSingle() {
        overlay.classList.add('closing');
        setTimeout(() => {
            overlay.remove();
            document.body.style.overflow = '';
        }, 200);
        document.removeEventListener('keydown', onEsc);
    }
    function onEsc(e) { if (e.key === 'Escape') closeSingle(); }
    document.addEventListener('keydown', onEsc);
    overlay.querySelector('.pmm-close-btn').onclick = closeSingle;
    overlay.addEventListener('click', function(e) { if (e.target === overlay) closeSingle(); });
}

function openFullscreenModel(modelSrc, modelTitle) {
    const overlay = document.createElement('div');
    overlay.className = 'product-media-modal-overlay';
    
    overlay.innerHTML = `
        <div class="product-media-modal-card">
            <div class="product-media-modal-header">
                <div class="pmm-header-left">
                    <span class="pmm-title">${modelTitle || '3D Model View'}</span>
                    <span class="pmm-badge">3D Interactive</span>
                </div>
                <button type="button" class="pmm-close-btn" title="Close" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="product-media-modal-body" id="singleModelBody"></div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
    
    const body = overlay.querySelector('#singleModelBody');
    const mv = document.createElement('model-viewer');
    mv.src = modelSrc;
    mv.alt = modelTitle || '3D Model';
    mv.setAttribute('bounds', 'tight');
    mv.setAttribute('auto-rotate', '');
    mv.setAttribute('rotation-per-second', '14deg');
    mv.setAttribute('auto-rotate-delay', '1500');
    mv.setAttribute('camera-controls', '');
    mv.setAttribute('disable-pan', '');
    mv.setAttribute('disable-zoom', '');
    mv.setAttribute('camera-orbit', '0deg 90deg 100%');
    mv.setAttribute('min-camera-orbit', '-Infinity 90deg 100%');
    mv.setAttribute('max-camera-orbit', 'Infinity 90deg 100%');
    mv.setAttribute('shadow-intensity', '0');
    mv.setAttribute('interaction-prompt', 'none');
    mv.setAttribute('power-preference', 'high-performance');
    mv.style.width = '100%';
    mv.style.height = '100%';
    mv.style.cursor = 'grab';
    mv.style.touchAction = 'none';
    mv.style.background = 'radial-gradient(circle at 50% 45%, #202330 0%, #12131a 100%)';
    body.appendChild(mv);
    attachModelViewerEvents(mv);
    if (typeof startSoft3DRotation === 'function') {
        startSoft3DRotation(mv);
    }
    
    function closeSingle() {
        if (typeof stopSoft3DRotation === 'function') {
            stopSoft3DRotation();
        }
        const sliderMV = document.getElementById('sliderModelViewer');
        if (sliderMV && typeof isCurrentSlide3D === 'function' && isCurrentSlide3D() && typeof startSoft3DRotation === 'function') {
            startSoft3DRotation(sliderMV);
        }
        overlay.classList.add('closing');
        setTimeout(() => {
            overlay.remove();
            document.body.style.overflow = '';
        }, 200);
        document.removeEventListener('keydown', onEsc);
    }
    function onEsc(e) { if (e.key === 'Escape') closeSingle(); }
    document.addEventListener('keydown', onEsc);
    overlay.querySelector('.pmm-close-btn').onclick = closeSingle;
    overlay.addEventListener('click', function(e) { if (e.target === overlay) closeSingle(); });
}

// ===== Product Image Slider & 3D Model Interaction =====
let currentSlide = 0;
let slideInterval = null;
let resumeAutoSlideTimeout = null;
const slideIntervalTime = 5000; // 5 seconds per slide
let isUserTouching = false;

function startAutoSlide() {
    clearTimeout(resumeAutoSlideTimeout);
    resumeAutoSlideTimeout = null;
    if (slideInterval) clearInterval(slideInterval);
    
    const sliderTrack = document.getElementById('sliderTrack');
    if (!sliderTrack) return;
    const slides = sliderTrack.querySelectorAll('.slider-slide');
    if (slides.length <= 1) return;

    // Provide 9 seconds on 3D slide so user enjoys the complete left-right soft rotation
    const currentInterval = (typeof isCurrentSlide3D === 'function' && isCurrentSlide3D()) ? 9000 : slideIntervalTime;

    slideInterval = setInterval(function() {
        if (!isUserTouching) {
            nextSlide();
        }
    }, currentInterval);
}

function pauseAutoSlide() {
    if (slideInterval) {
        clearInterval(slideInterval);
        slideInterval = null;
    }
    if (resumeAutoSlideTimeout) {
        clearTimeout(resumeAutoSlideTimeout);
        resumeAutoSlideTimeout = null;
    }
}

function resetResumeTimer(delay = 4000) {
    if (resumeAutoSlideTimeout) clearTimeout(resumeAutoSlideTimeout);
    resumeAutoSlideTimeout = setTimeout(function() {
        if (!isUserTouching) {
            startAutoSlide();
        }
    }, delay);
}

function resetAutoSlide() {
    pauseAutoSlide();
    const currentInterval = (typeof isCurrentSlide3D === 'function' && isCurrentSlide3D()) ? 9000 : slideIntervalTime;
    resetResumeTimer(currentInterval);
}

function goToSlide(index) {
    const sliderTrack = document.getElementById('sliderTrack');
    if (!sliderTrack) return;
    
    const slides = sliderTrack.querySelectorAll('.slider-slide');
    if (!slides.length) return;
    
    const dots = document.querySelectorAll('.slider-dot');
    const galleryItems = document.querySelectorAll('.product-gallery-item');
    
    currentSlide = index;
    if (currentSlide >= slides.length) currentSlide = 0;
    if (currentSlide < 0) currentSlide = slides.length - 1;
    
    sliderTrack.style.transition = 'transform 0.45s cubic-bezier(0.25, 1, 0.5, 1)';
    sliderTrack.style.transform = `translateX(-${currentSlide * 100}%)`;
    
    // Synchronize slider dots
    dots.forEach((dot, i) => {
        dot.classList.toggle('active', i === currentSlide);
    });

    // Synchronize thumbnail gallery active state
    galleryItems.forEach((item, i) => {
        item.classList.toggle('active', i === currentSlide);
    });
    
    // Manage 3D model auto-rotation: only run when 3D slide is active to guarantee silky 60fps performance!
    const sliderMV = document.getElementById('sliderModelViewer');
    if (sliderMV) {
        if (isCurrentSlide3D()) {
            startSoft3DRotation(sliderMV);
        } else {
            stopSoft3DRotation(sliderMV);
        }
    }

    // Reset auto-slide timer so user gets full viewing time before next auto-slide
    if (!isUserTouching) {
        resetAutoSlide();
    }
}

function nextSlide() {
    goToSlide(currentSlide + 1);
}

function prevSlide() {
    goToSlide(currentSlide - 1);
}

function isCurrentSlide3D() {
    const sliderTrack = document.getElementById('sliderTrack');
    if (!sliderTrack) return false;
    const slides = sliderTrack.querySelectorAll('.slider-slide');
    const activeSlide = slides[currentSlide];
    if (!activeSlide) return false;
    return activeSlide.classList.contains('model-slide') || activeSlide.querySelector('model-viewer') !== null;
}

// ===== Soft 3D Left-and-Right Rotation Controller =====
let softRotateMv = null;
let softRotateDirection = 1; // 1 = rotate right, -1 = rotate left
let softRotateInterval = null;
const SOFT_ROTATE_SPEED = 14; // degrees per second (silky smooth, natural pace)
const SOFT_ROTATE_PERIOD = 4200; // reverse direction every 4.2s (~58 deg arc)

function startSoft3DRotation(mv) {
    if (!mv) return;
    stopSoft3DRotation();
    softRotateMv = mv;
    
    // Enable auto-rotate with smooth direction
    mv.setAttribute('auto-rotate', '');
    mv.setAttribute('rotation-per-second', `${softRotateDirection * SOFT_ROTATE_SPEED}deg`);
    
    softRotateInterval = setInterval(() => {
        if (!softRotateMv) {
            stopSoft3DRotation();
            return;
        }
        if (!isUserTouching) {
            softRotateDirection *= -1;
            softRotateMv.setAttribute('rotation-per-second', `${softRotateDirection * SOFT_ROTATE_SPEED}deg`);
        }
    }, SOFT_ROTATE_PERIOD);
}

function stopSoft3DRotation(mv) {
    if (mv && mv === softRotateMv) {
        mv.removeAttribute('auto-rotate');
        softRotateMv = null;
    } else if (!mv) {
        if (softRotateMv) {
            softRotateMv.removeAttribute('auto-rotate');
            softRotateMv = null;
        }
    }
    if (softRotateInterval) {
        clearInterval(softRotateInterval);
        softRotateInterval = null;
    }
}

function attachModelViewerEvents(mv) {
    if (!mv) return;

    let hasCameraRotated = false;
    let mvTouchStartX = 0;
    let mvTouchStartY = 0;
    let mvTouchStartTime = 0;
    let mvPointerStartX = 0;
    let mvPointerStartY = 0;
    let mvPointerStartTime = 0;

    // Detect user rotating 3D camera
    mv.addEventListener('camera-change', function(event) {
        if (event.detail && event.detail.source === 'user-interaction') {
            hasCameraRotated = true;
            isUserTouching = true;
            pauseAutoSlide();
        }
    });

    // Pointer events on 3D viewer (Desktop)
    mv.addEventListener('pointerdown', function(e) {
        mvPointerStartX = e.clientX;
        mvPointerStartY = e.clientY;
        mvPointerStartTime = Date.now();
        hasCameraRotated = false;
        isUserTouching = true;
        pauseAutoSlide();
    });

    mv.addEventListener('pointerup', function(e) {
        isUserTouching = false;
        resetResumeTimer(3500);

        if (isMobileView()) return; // Mobile is handled by touch events

        if (mv.closest('.product-media-modal-card') || mv.closest('.fullscreen-overlay')) {
            return;
        }

        const deltaX = Math.abs(e.clientX - mvPointerStartX);
        const deltaY = Math.abs(e.clientY - mvPointerStartY);
        const duration = Date.now() - mvPointerStartTime;

        // Quick click on 3D model without dragging to rotate (< 8px, < 300ms)
        if (!hasCameraRotated && deltaX < 8 && deltaY < 8 && duration < 300) {
            handleMediaClick(currentSlide);
        }
    });

    mv.addEventListener('pointercancel', function() {
        isUserTouching = false;
        resetResumeTimer(3500);
    });

    // Touch events on 3D viewer (Mobile)
    mv.addEventListener('touchstart', function(e) {
        if (e.touches && e.touches.length === 1) {
            mvTouchStartX = e.touches[0].clientX;
            mvTouchStartY = e.touches[0].clientY;
            mvTouchStartTime = Date.now();
            hasCameraRotated = false;
        }
        isUserTouching = true;
        pauseAutoSlide();
    }, { passive: true });

    mv.addEventListener('touchend', function(e) {
        isUserTouching = false;
        resetResumeTimer(3500);

        if (mv.closest('.product-media-modal-card') || mv.closest('.fullscreen-overlay')) {
            return;
        }

        if (e.changedTouches && e.changedTouches.length === 1) {
            const deltaX = Math.abs(e.changedTouches[0].clientX - mvTouchStartX);
            const deltaY = Math.abs(e.changedTouches[0].clientY - mvTouchStartY);
            const distance = Math.hypot(deltaX, deltaY);
            const duration = Date.now() - mvTouchStartTime;

            // Tap on mobile 3D model without camera rotation (< 18px, < 350ms)
            if (!hasCameraRotated && distance < 18 && duration < 350) {
                handleMediaClick(currentSlide);
            }
        }
    }, { passive: true });

    // Standard click event on model-viewer as a robust backup
    mv.addEventListener('click', function(e) {
        if (mv.closest('.product-media-modal-card') || mv.closest('.fullscreen-overlay')) {
            return;
        }
        if (!hasCameraRotated) {
            handleMediaClick(currentSlide);
        }
    });

    mv.addEventListener('touchcancel', function() {
        isUserTouching = false;
        resetResumeTimer(3500);
    }, { passive: true });
}

let isCarouselDragging = false;

function initSliderTouchAndSwipe() {
    const sliderContainer = document.querySelector('.slider-container');
    const sliderTrack = document.getElementById('sliderTrack');
    if (!sliderContainer || !sliderTrack) return;

    const slides = sliderTrack.querySelectorAll('.slider-slide');
    if (slides.length <= 1) return;

    let touchStartX = 0;
    let touchStartY = 0;
    let touchDeltaX = 0;
    let touchDeltaY = 0;
    let touchStartTime = 0;
    let isHorizontalSwipe = false;
    let isTouchActive = false;

    // Capture phase listener
    function handleTouchStart(e) {
        if (!e.touches || e.touches.length !== 1) return;
        if (e.target && e.target.closest && e.target.closest('.slider-arrow')) return;

        // In 3D: DO NOT slide the carousel. Touch slide rotates the 3D model left & right!
        const is3D = isCurrentSlide3D() || (e.target && (e.target.tagName.toLowerCase() === 'model-viewer' || e.target.closest('model-viewer')));

        if (is3D) {
            isUserTouching = true;
            isTouchActive = false; // Disables carousel track movement so model-viewer gets the touch to rotate
            isHorizontalSwipe = false;
            pauseAutoSlide();
            return;
        }

        // For image slides: enable touch slide left & right
        isUserTouching = true;
        isTouchActive = true;
        isCarouselDragging = false;
        pauseAutoSlide();

        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        touchDeltaX = 0;
        touchDeltaY = 0;
        touchStartTime = Date.now();
        isHorizontalSwipe = false;
    }

    function handleTouchMove(e) {
        // If on 3D slide or not actively swiping an image, let event pass directly to 3D model
        if (!isTouchActive || !e.touches || e.touches.length !== 1) return;

        touchDeltaX = e.touches[0].clientX - touchStartX;
        touchDeltaY = e.touches[0].clientY - touchStartY;

        // Detect horizontal swipe vs vertical scroll intent
        if (!isHorizontalSwipe) {
            const absX = Math.abs(touchDeltaX);
            const absY = Math.abs(touchDeltaY);
            if (absX > 8 && absX > absY) {
                isHorizontalSwipe = true;
                isCarouselDragging = true;
            } else if (absY > 8 && absY >= absX) {
                // User is scrolling the page vertically - don't hijack
                isTouchActive = false;
                isUserTouching = false;
                resetResumeTimer(4000);
                return;
            }
        }

        if (isHorizontalSwipe) {
            // Prevent vertical page jitter while swiping images
            if (e.cancelable) {
                e.preventDefault();
            }
            e.stopPropagation();

            // Real-time track drag feedback for native mobile feel on images
            sliderTrack.style.transition = 'none';
            sliderTrack.style.transform = `translateX(calc(-${currentSlide * 100}% + ${touchDeltaX}px))`;
        }
    }

    function handleTouchEnd(e) {
        if (!isTouchActive) {
            // User was rotating 3D model or touching non-swipe area
            isUserTouching = false;
            resetResumeTimer(4000); // Resume auto-slide if user not touching
            return;
        }

        isTouchActive = false;
        isUserTouching = false;

        const duration = Date.now() - touchStartTime;
        const absDeltaX = Math.abs(touchDeltaX);
        const absDeltaY = Math.abs(touchDeltaY);
        const distance = Math.hypot(touchDeltaX, touchDeltaY);
        const isFlick = duration < 280 && absDeltaX > 25;
        const threshold = 40;

        sliderTrack.style.transition = 'transform 0.45s cubic-bezier(0.25, 1, 0.5, 1)';

        if (isHorizontalSwipe && (absDeltaX > threshold || isFlick)) {
            if (touchDeltaX < 0) {
                // Swiped Left -> Next Slide
                nextSlide();
            } else {
                // Swiped Right -> Prev Slide
                prevSlide();
            }
            setTimeout(() => { isCarouselDragging = false; }, 120);
        } else {
            // Snap back smoothly to current slide
            goToSlide(currentSlide);
            // Detect tap on mobile image slide (tap in place, not a swipe)
            if (!isHorizontalSwipe && distance < 25 && duration < 500) {
                if (!e.target.closest('.slider-arrow') && !e.target.closest('.slider-dots')) {
                    handleMediaClick(currentSlide);
                }
            }
            isCarouselDragging = false;
        }

        isHorizontalSwipe = false;
        touchDeltaX = 0;
        touchDeltaY = 0;

        // Ensure auto-slide resumes when user is not touching
        resetResumeTimer(4000);
    }

    // Attach touch handlers with { capture: true }
    sliderContainer.addEventListener('touchstart', handleTouchStart, { capture: true, passive: false });
    sliderContainer.addEventListener('touchmove', handleTouchMove, { capture: true, passive: false });
    sliderContainer.addEventListener('touchend', handleTouchEnd, { capture: true, passive: false });
    sliderContainer.addEventListener('touchcancel', handleTouchEnd, { capture: true, passive: false });

    // Desktop Mouse Drag Support
    let isMouseDown = false;
    let mouseStartX = 0;
    let mouseStartY = 0;
    let mouseDeltaX = 0;
    let isMouseHorizontal = false;

    sliderContainer.addEventListener('mousedown', function(e) {
        if (isMobileView()) return;
        if (e.button !== 0 || e.target.closest('.slider-arrow') || e.target.closest('.slider-dots')) return;

        // In 3D: allow mouse drag to rotate 3D model, do NOT slide the carousel
        const is3D = isCurrentSlide3D() || (e.target && (e.target.tagName.toLowerCase() === 'model-viewer' || e.target.closest('model-viewer')));
        if (is3D) {
            isUserTouching = true;
            pauseAutoSlide();
            return;
        }

        isMouseDown = true;
        isUserTouching = true;
        isCarouselDragging = false;
        pauseAutoSlide();

        mouseStartX = e.clientX;
        mouseStartY = e.clientY;
        mouseDeltaX = 0;
        isMouseHorizontal = false;
    });

    window.addEventListener('mousemove', function(e) {
        if (!isMouseDown) return;

        mouseDeltaX = e.clientX - mouseStartX;
        const mouseDeltaY = e.clientY - mouseStartY;

        if (!isMouseHorizontal) {
            if (Math.abs(mouseDeltaX) > 10 && Math.abs(mouseDeltaX) > Math.abs(mouseDeltaY)) {
                isMouseHorizontal = true;
                isCarouselDragging = true;
            }
        }

        if (isMouseHorizontal) {
            e.preventDefault();
            sliderTrack.style.transition = 'none';
            sliderTrack.style.transform = `translateX(calc(-${currentSlide * 100}% + ${mouseDeltaX}px))`;
        }
    });

    window.addEventListener('mouseup', function(e) {
        if (!isMouseDown) {
            if (isCurrentSlide3D()) {
                isUserTouching = false;
                resetResumeTimer(4000);
            }
            return;
        }
        isMouseDown = false;
        isUserTouching = false;

        sliderTrack.style.transition = 'transform 0.45s cubic-bezier(0.25, 1, 0.5, 1)';

        if (isMouseHorizontal && Math.abs(mouseDeltaX) > 40) {
            if (mouseDeltaX < 0) {
                nextSlide();
            } else {
                prevSlide();
            }
            setTimeout(() => { isCarouselDragging = false; }, 120);
        } else if (isMouseHorizontal) {
            goToSlide(currentSlide);
            setTimeout(() => { isCarouselDragging = false; }, 120);
        } else if (Math.abs(mouseDeltaX) < 8) {
            // Click on desktop slide (not a drag)
            if (!e.target.closest('.slider-arrow') && !e.target.closest('.slider-dots')) {
                handleMediaClick(currentSlide);
            }
            isCarouselDragging = false;
        }

        isMouseHorizontal = false;
        mouseDeltaX = 0;
        resetResumeTimer(4000);
    });

    // Hover pause for desktop
    sliderContainer.addEventListener('mouseenter', function() {
        pauseAutoSlide();
    });

    sliderContainer.addEventListener('mouseleave', function() {
        if (!isMouseDown && !isTouchActive) {
            resetResumeTimer(2500);
        }
    });
}

function initSlider() {
    const sliderTrack = document.getElementById('sliderTrack');
    const sliderDots = document.getElementById('sliderDots');
    
    if (!sliderTrack || !sliderDots) return;
    
    const slides = sliderTrack.querySelectorAll('.slider-slide');
    const prevArrow = document.getElementById('sliderArrowPrev');
    const nextArrow = document.getElementById('sliderArrowNext');

    if (slides.length <= 1) {
        sliderDots.style.display = 'none';
        if (prevArrow) prevArrow.style.display = 'none';
        if (nextArrow) nextArrow.style.display = 'none';
    } else {
        // Create navigation dots
        sliderDots.innerHTML = '';
        slides.forEach((_, index) => {
            const dot = document.createElement('div');
            dot.className = 'slider-dot' + (index === 0 ? ' active' : '');
            dot.setAttribute('aria-label', `Slide ${index + 1}`);
            dot.onclick = (e) => {
                e.stopPropagation();
                goToSlide(index);
            };
            sliderDots.appendChild(dot);
        });
    }

    // Attach click events on all slides for tap / click to expand (mobile) or open modal (desktop)
    slides.forEach((slide, idx) => {
        slide.addEventListener('click', function(e) {
            if (e.target.closest('.slider-arrow') || e.target.closest('.slider-dots')) return;
            // If user swiped or dragged carousel, don't trigger click
            if (isCarouselDragging) return;
            handleMediaClick(idx);
        });
    });

    // Initialize first thumbnail active state
    const galleryItems = document.querySelectorAll('.product-gallery-item');
    if (galleryItems.length) {
        galleryItems.forEach((item, idx) => item.classList.toggle('active', idx === 0));
    }
    
    // Attach 3D model interaction handlers
    const modelViewers = sliderTrack.querySelectorAll('model-viewer');
    modelViewers.forEach(mv => attachModelViewerEvents(mv));

    // Initialize 3D soft rotation state based on active slide
    const sliderMV = document.getElementById('sliderModelViewer');
    if (sliderMV) {
        if (isCurrentSlide3D()) {
            startSoft3DRotation(sliderMV);
        } else {
            stopSoft3DRotation(sliderMV);
        }
    }

    // Initialize swipe & touch handling
    initSliderTouchAndSwipe();

    if (slides.length > 1) {
        // Start auto-slide if user is not touching
        startAutoSlide();
    }
}

// Resume auto-slide when returning to tab
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        pauseAutoSlide();
    } else {
        if (!isUserTouching) {
            resetResumeTimer(2000);
        }
    }
});

// Initialize slider on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSlider);
} else {
    initSlider();
}

function scrollToReviews() {
    const reviewsSection = document.querySelector('.reviews-section');
    if (reviewsSection) {
        reviewsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
</script>

</head>
<body class="product-info-page">
<!-- Desktop Sidebar -->
<div class="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <a href="index.php" class="logo-link" onclick="goBack(); return false;">
            <img src="img/ClosetGo-logo.png" alt="ClosetGo Logo" class="logo-image">
        </a>
    </div>
    
    <a href="index.php" class="sidebar-icon active" onclick="goBack(); return false;">
        <i class="fas fa-home"></i>
        <span class="sidebar-text">Home</span>
    </a>
  
</div>

<div class="shell">
    <!-- Top Navigation Bar (same as index.php) -->
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
                <a href="index.php" class="closetgo-link" onclick="goBack(); return false;">
                    <img src="images/icon/JMB.png" alt="ClosetGo" class="brand-logo" onerror="this.src='images/icon/chat.png'">
                    <span>ClosetGo</span>
                </a>
            </div>
        </div>
        
        <!-- Mobile ClosetGo Logo -->
        <a href="index.php" class="mobile-closetgo-link" onclick="goBack(); return false;">
            <img src="images/icon/JMB.png" alt="ClosetGo" class="brand-logo" onerror="this.src='images/icon/chat.png'">
            <span>ClosetGo</span>
        </a>

        <!-- Back Button (Mobile) -->
        <img src="images/icon/back.png" alt="Back" class="back-button mobile-back-btn" onclick="goBack()">


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
            <div class="mobile-top-search" onclick="goBack()" title="Search" style="display: none;">
                <img src="images/icon/search.png" alt="Search">
            </div>
            <div class="purchase-top-container" style="position: relative; display: inline-block;" title="Purchases">
                <img src="images/icon/purchase.png" alt="Purchases" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'"
                     onclick="window.location.href='purchase.php'">
                <span id="topbarPurchaseBadge" class="purchase-nav-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
            </div>
            <div class="notification-container" style="position: relative; display: inline-block;" title="Notifications">
                <img src="images/icon/notification.png" alt="Notification" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'">
            </div>
            <div class="cart-container" style="position: relative; display: inline-block;" title="Cart" onclick="window.location.href='save.php'">
                <img src="images/icon/cart.png" alt="Cart" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'">
                <span id="cartCount" class="cart-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
            </div>
           
        </div>
    </div>
    <div class="product-detail-container">
        <!-- Product Image Section -->
        <div class="product-image-section">
            <!-- 3D Model/Image Slider at Top -->
            <div class="product-model-viewer" id="productMediaViewer">
                <div class="slider-container">
                    <div class="slider-track" id="sliderTrack">
                        <?php 
                        $slideCounter = 0;
                        if (!empty($product['image_path'])): ?>
                            <div class="slider-slide" data-slide-index="<?php echo $slideCounter++; ?>">
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; object-position: top; border-radius: 0;">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($product['image_path2'])): ?>
                            <div class="slider-slide" data-slide-index="<?php echo $slideCounter++; ?>">
                                <img src="<?php echo htmlspecialchars($product['image_path2']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; object-position: top; border-radius: 0;">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($product['image_path3'])): ?>
                            <div class="slider-slide" data-slide-index="<?php echo $slideCounter++; ?>">
                                <img src="<?php echo htmlspecialchars($product['image_path3']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; object-position: top; border-radius: 0;">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($product['image_path4'])): ?>
                            <div class="slider-slide" data-slide-index="<?php echo $slideCounter++; ?>">
                                <img src="<?php echo htmlspecialchars($product['image_path4']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; object-position: top; border-radius: 0;">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($product['model_path'])): ?>
                            <div class="slider-slide model-slide" data-slide-index="<?php echo $slideCounter++; ?>">
                                <model-viewer
                                    id="sliderModelViewer"
                                    src="<?php echo htmlspecialchars($product['model_path']); ?>"
                                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                                    loading="eager"
                                    reveal="auto"
                                    bounds="tight"
                                    auto-rotate
                                    rotation-per-second="14deg"
                                    auto-rotate-delay="1500"
                                    camera-orbit="0deg 90deg 100%"
                                    camera-controls
                                    disable-pan
                                    disable-zoom
                                    min-camera-orbit="-Infinity 90deg 100%"
                                    max-camera-orbit="Infinity 90deg 100%"
                                    shadow-intensity="0"
                                    interaction-prompt="none"
                                    power-preference="high-performance"
                                    style="width: 100%; height: 100%; cursor: grab; touch-action: none; background: radial-gradient(circle at 50% 45%, #202330 0%, #12131a 100%);">
                                </model-viewer>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Navigation Arrows -->
                    <button type="button" class="slider-arrow slider-arrow-prev" id="sliderArrowPrev" onclick="prevSlide()" aria-label="Previous slide">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="slider-arrow slider-arrow-next" id="sliderArrowNext" onclick="nextSlide()" aria-label="Next slide">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <!-- Expand/Modal Button -->
                    <button type="button" class="slider-expand-btn" id="sliderExpandBtn" title="Expand view" aria-label="Expand view" onclick="handleMediaClick(currentSlide)">
                        <i class="fas fa-expand-alt" id="sliderExpandIcon"></i>
                    </button>
                    <!-- Slider Dots -->
                    <div class="slider-dots" id="sliderDots"></div>
                </div>
                <?php if (empty($product['image_path']) && empty($product['image_path2']) && empty($product['image_path3']) && empty($product['image_path4']) && empty($product['model_path'])): ?>
                    <div class="product-placeholder">
                        <i class="fas fa-tshirt"></i>
                        <p>No Media Available</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Product Images Gallery -->
            <div class="product-images-gallery">
                <?php 
                $currentGalleryIndex = 0;
                ?>
                <?php if (!empty($product['image_path'])): ?>
                    <div class="product-gallery-item active" onclick="goToSlide(<?php echo $currentGalleryIndex++; ?>)">
                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?> Image 1">
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($product['image_path2'])): ?>
                    <div class="product-gallery-item" onclick="goToSlide(<?php echo $currentGalleryIndex++; ?>)">
                        <img src="<?php echo htmlspecialchars($product['image_path2']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?> Image 2">
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($product['image_path3'])): ?>
                    <div class="product-gallery-item" onclick="goToSlide(<?php echo $currentGalleryIndex++; ?>)">
                        <img src="<?php echo htmlspecialchars($product['image_path3']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?> Image 3">
                    </div>
                <?php endif; ?>

                <?php if (!empty($product['image_path4'])): ?>
                    <div class="product-gallery-item" onclick="goToSlide(<?php echo $currentGalleryIndex++; ?>)">
                        <img src="<?php echo htmlspecialchars($product['image_path4']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?> Image 4">
                    </div>
                <?php endif; ?>

                <?php if (!empty($product['model_path'])): ?>
                    <div class="product-gallery-item gallery-3d-item" onclick="goToSlide(<?php echo $currentGalleryIndex++; ?>)" title="3D View" style="position: relative; overflow: hidden; background: #1a1c23; border-radius: 6px; cursor: pointer;">
                        <model-viewer
                            src="<?php echo htmlspecialchars($product['model_path']); ?>"
                            alt="<?php echo htmlspecialchars($product['name']); ?>"
                            loading="lazy"
                            reveal="auto"
                            interaction-prompt="none"
                            bounds="tight"
                            camera-orbit="-20deg 85deg auto"
                            shadow-intensity="0"
                            power-preference="low-power"
                            style="width: 100%; height: 100%; pointer-events: none; display: block; background: #1a1c23;">
                        </model-viewer>
                    </div>
                <?php endif; ?>
                
                <?php if ($currentGalleryIndex === 0): ?>
                    <div class="no-images-placeholder">
                        <i class="fas fa-image"></i>
                        <p>No Media Available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>    
        <!-- Product Info Section -->
        <div class="product-info-section" style="padding: 20px;">
            <div class="product-name-price-row" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <h1 class="product-title" style="margin: 0; font-size: 1.2rem; font-weight: 500;">
                    <?php echo htmlspecialchars($product['name']); ?>
                </h1>

                <?php
                $originalPrice = $product['price'];
                $discount = $product['discount'] ?? 0;
                $discountedPrice = $discount > 0 ? $originalPrice * (1 - $discount / 100) : $originalPrice;
                ?>
                <div class="product-price" style="margin: 0; text-align: right; display: flex; align-items: baseline; gap: 10px; flex-shrink: 0;">
                    <span class="rental-price-label" style="display: none;">Rental Price: </span>
                    <?php if ($discount > 0): ?>
                        <div style="color: #ff4444 !important; font-weight: bold; font-size: 1.5rem;">₱<?php echo number_format($discountedPrice, 0); ?></div>
                    <?php else: ?>
                        <div style="color: #ff4444 !important; font-weight: bold; font-size: 1.5rem;">₱<?php echo number_format($originalPrice, 0); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Selling Price & Stock Row -->
            <div class="selling-price-stock-row" style="display: flex; justify-content: space-between; align-items: center; margin-top: 2px; margin-bottom: 10px;">
                <div class="product-purchase-price" style="font-size: 0.85rem; color: #888; margin: 0; text-align: left;">
                    <?php if (!empty($product['purchase_price'])): ?>
                        Selling Price: ₱<?php echo number_format($product['purchase_price'], 0); ?>
                    <?php endif; ?>
                </div>
                <div class="product-stock-display" style="font-size: 0.85rem; color: #aaa; margin: 0; text-align: right;">
                    Stocks: <?php echo intval($product['stock'] ?? 0); ?>
                </div>
            </div>

            <!-- Product Rating Row -->
            <div class="product-rating-row" id="productRatingRow" style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px; cursor: pointer;" onclick="scrollToReviews()">
                <div class="product-rating-stars" id="productRatingStars" style="display: flex; align-items: center; gap: 4px;">
                    <span class="rating-value" id="productRatingValue" style="font-size: 1rem; font-weight: 600; color: #ffd700;">0.0</span>
                    <span id="productStarIcons" style="color: #ffd700; font-size: 0.9rem;"></span>
                </div>
                <div class="rating-divider" style="width: 1px; height: 20px; background: #555;"></div>
                <div class="product-total-rates" id="productTotalRates" style="font-size: 0.9rem; color: #999;">0 Ratings</div>
            </div>

            <div class="product-guide-divider"></div>

            <?php if (!empty($product['category'])): ?>
            <div class="product-category-info">
                <span class="category-label">Category:</span>
                <span class="category-value"><?php echo htmlspecialchars(ucfirst($product['category'])); ?></span>
            </div>
            <?php endif; ?>

            <div class="product-guide-divider"></div>

            <?php if (!empty($product['color'])): ?>
            <div class="product-color-info">
                <span class="color-label">Color:</span>
                <span class="color-value"><?php echo htmlspecialchars(ucwords($product['color'])); ?></span>
            </div>
            <?php endif; ?>

            <div class="product-guide-divider"></div>

            <?php if (!empty($product['description'])): ?>
            <div class="product-description">
                <span class="description-label">Description:</span>
                <span class="description-value"><?php echo nl2br(htmlspecialchars($product['description'])); ?></span>
            </div>
            <?php endif; ?>

            <!-- Desktop Reserve Now Button (hidden on mobile) -->
            <div class="desktop-reserve-btn-wrap">
                <button class="desktop-reserve-btn" onclick="showReservePage()">
                    <span>Reserve Now</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Reviews Section -->
    <div class="reviews-section">
        <div class="reviews-title">Customer Reviews</div>
        <div id="reviewsContainer"></div>
    </div>
</div> <!-- End of .shell -->

<!-- Mobile App Bar -->
<div class="mobile-app-bar">
    <div class="app-bar-content">
        <button class="reserve-button" onclick="showReservePage()">
            <span>Reserve Now</span>
        </button>
    </div>
</div>

<!-- Reserve Page Overlay -->
<div id="reserveOverlay" class="reserve-overlay">
    <div class="reserve-content">
        <!-- Close button (X) in top right -->
        <button type="button" class="close-reserve-btn" onclick="closeReservePage()" aria-label="Close" title="Close">
            <i class="fas fa-times"></i>
        </button>
        <div class="reserve-model">
            <?php if (!empty($product['image_path'])): ?>
                <div class="reserve-model-img-wrap" style="width: 100%; height: 100%; position: relative;">
                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
            <?php elseif (!empty($product['image_path2'])): ?>
                <div class="reserve-model-img-wrap" style="width: 100%; height: 100%; position: relative;">
                    <img src="<?php echo htmlspecialchars($product['image_path2']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
            <?php elseif (!empty($product['image_path3'])): ?>
                <div class="reserve-model-img-wrap" style="width: 100%; height: 100%; position: relative;">
                    <img src="<?php echo htmlspecialchars($product['image_path3']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
            <?php elseif (!empty($product['image_path4'])): ?>
                <div class="reserve-model-img-wrap" style="width: 100%; height: 100%; position: relative;">
                    <img src="<?php echo htmlspecialchars($product['image_path4']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
            <?php elseif (!empty($product['model_path'])): ?>
                <model-viewer 
                    src="<?php echo htmlspecialchars($product['model_path']); ?>"
                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                    loading="lazy"
                    bounds="tight"
                    auto-rotate
                    rotation-per-second="14deg"
                    auto-rotate-delay="1500"
                    camera-controls
                    disable-pan
                    disable-zoom
                    camera-orbit="0deg 90deg 100%"
                    min-camera-orbit="-Infinity 90deg 100%"
                    max-camera-orbit="Infinity 90deg 100%"
                    shadow-intensity="0"
                    interaction-prompt="none"
                    power-preference="high-performance"
                    style="width: 100%; height: 100%; cursor: grab; touch-action: none; background: radial-gradient(circle at 50% 45%, #202330 0%, #12131a 100%);">
                </model-viewer>
            <?php else: ?>
                <div style="width: 100%; height: 100%; background: #1c1e26; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                    <i class="fas fa-tshirt" style="font-size: 2rem; color: #555;"></i>
                </div>
            <?php endif; ?>
            <div class="product-name-overlay">
                <?php echo htmlspecialchars($product['name']); ?>
            </div>
             <div class="product-price-overlay">
                <span style="color: gray; font-size: 0.8rem;">Rental Price:</span>
                <?php if ($discount > 0): ?>
                    <span style="color: #ff4444; font-weight: bold;">₱<?php echo number_format($discountedPrice, 0); ?></span>
                <?php else: ?>
                    <span style="color: #ff4444; font-weight: bold;">₱<?php echo number_format($originalPrice, 0); ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($product['purchase_price'])): ?>
            <div class="product-purchase-price-overlay">
                Selling Price: <span style="color: red;">₱<?php echo number_format($product['purchase_price'], 0); ?></span>
            </div>
            <?php endif; ?>
            <div class="product-stock-overlay">
                Stock: <?php echo isset($product['stock']) ? htmlspecialchars($product['stock']) : 'Available'; ?>
            </div>
        </div>
        
        <!-- Reservation Form -->
        <div class="reservation-form no-scroll">
            <!-- Type Selection (Reserve / Buy) with Shade Circle like in save.php (At Top) -->
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

            <!-- Reserve Date and Return Date (Under Type) -->
            <div class="form-row date-row" id="dateRowGroup">
                <div class="form-group" id="reserveDateGroup">
                    <label for="reserveDate" id="reserveDateLabel">Reserve Date</label>
                    <input type="text" id="reserveDate" name="reserveDate" class="date-input-disabled" readonly placeholder="Select date" onclick="openCalendarModal('reserveDate')" required style="cursor: not-allowed; opacity: 0.5;" title="Please select Type first">
                </div>
                <div class="form-group" id="returnDateGroup">
                    <label for="returnDate" id="returnDateLabel">Return Date</label>
                    <input type="text" id="returnDate" name="returnDate" class="date-input-disabled" readonly placeholder="Select date" onclick="openCalendarModal('returnDate')" required style="cursor: not-allowed; opacity: 0.5;" title="Please select Type first">
                </div>
            </div>

            <!-- Overdue Policy Notice with Agreement Checkbox (On Top of Quantity) -->
            <div class="overdue-policy-notice" id="overdueNoticeGroup" style="opacity: 0.5; pointer-events: none;">
                <label class="overdue-agreement-label" for="overdueAgreeCheckbox" title="I agree to the overdue fee of <?php echo $overduePenaltyPercent; ?>%/day based on price">
                    <input type="checkbox" id="overdueAgreeCheckbox" name="overdue_agree" class="overdue-agreement-checkbox" onchange="handleOverdueCheckboxChange()" disabled>
                    <span class="overdue-agreement-text">
                        Overdue fee: <span class="overdue-percent-btn" onclick="openOverdueCalcModal(event)" title="Click to view fee breakdown"><?php echo $overduePenaltyPercent; ?>%</span>/day based on price
                    </span>
                </label>
            </div>
            <div class="guide-line" id="overdueGuideLine" style="height: 1px; background: rgba(255, 255, 255, 0.08); margin: 6px 0 8px 0;"></div>

            <!-- Quantity Selector -->
            <div id="reservationQuantityWrapper" onclick="handleQuantityWrapperClick(event)">
                <div class="quantity-group" id="reservationQuantityGroup" style="opacity: 0.5; pointer-events: none;">
                    <div class="quantity-row">
                        <label class="quantity-label">Quantity</label>
                        <div class="quantity-selector">
                            <button type="button" class="quantity-btn quantity-minus" onclick="decrementQuantity()">-</button>
                            <input type="number" id="totalQuantity" name="totalQuantity" class="quantity-input" value="0" min="0" max="<?php echo isset($product['stock']) ? $product['stock'] : 999; ?>" readonly>
                            <button type="button" class="quantity-btn quantity-plus" onclick="incrementQuantity()">+</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="guide-line" style="height: 1px; background: rgba(255, 255, 255, 0.08); margin: 8px 0 6px 0;"></div>
            <div id="measurementsContainer" style="pointer-events: none;">
                <!-- Dynamic size and measurement fields will be generated here -->
            </div>
        </div>

        <div class="button-container">
            <button type="button" class="back-submit-btn" onclick="closeReservePage()">Back</button>
            <button type="button" class="cart-submit-btn" onclick="addToCart()">Cart</button>
            <button type="button" class="checkout-submit-btn" onclick="handleCheckout()">Check Out</button>
        </div>
    </div>
</div>

<div id="topOverlay" class="top-overlay"></div>

<!-- Calendar Modal -->
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

<!-- Overdue Calculation Breakdown Modal -->
<div id="overdueCalcModal" class="overdue-calc-overlay" onclick="closeOverdueCalcModal(event)">
    <div class="overdue-calc-modal" onclick="event.stopPropagation()">
        <div class="overdue-calc-header">
            <h3>Overdue Fee</h3>
        </div>
        <div class="overdue-calc-body">
            <div style="background: #15171c; border: 1px solid #2d313a; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                <div style="font-size: 0.85rem; color: #aaa; margin-bottom: 8px;">Product: <strong style="color: #fff;"><?php echo htmlspecialchars($product['name']); ?></strong></div>
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 6px;">
                    <span style="color: #888;">Rental Price:</span>
                    <strong style="color: #eee;" id="overdueModalRentalPrice">₱<?php echo number_format($discountedPrice, 2); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem;">
                    <span style="color: #888;">Overdue Rate:</span>
                    <strong style="color: #ffd700;" id="overdueModalRate"><?php echo $overduePenaltyPercent; ?>% / day</strong>
                </div>
            </div>

            <div style="color: #ff4d4f; font-size: 0.8rem; background: rgba(255, 77, 79, 0.08); border: 1px solid rgba(255, 77, 79, 0.3); border-radius: 8px; padding: 10px 12px; margin-bottom: 16px; line-height: 1.45;">
                Important: You will be charged an extra <strong style="color: #ff4d4f;" id="overdueModalWarningFee">₱<?php echo number_format(($discountedPrice * $overduePenaltyPercent) / 100, 2); ?> per day</strong> for late returns beyond the agreed return date.
            </div>

            <button type="button" onclick="closeOverdueCalcModal(event)" style="width: 100%; padding: 10px; background: #2b2e36; border: 1px solid #3c404b; border-radius: 8px; color: #fff; font-size: 0.88rem; font-weight: 600; cursor: pointer;">Close</button>
        </div>
    </div>
</div>

<script>
// Overdue calculation modal handlers
function openOverdueCalcModal(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    const modal = document.getElementById('overdueCalcModal');
    if (!modal) return;

    const qty = parseInt(document.getElementById('totalQuantity')?.value) || 1;
    const unitPrice = <?php echo floatval($discountedPrice); ?>;
    const totalRental = unitPrice * qty;
    const rate = (typeof overduePenaltyPercent !== 'undefined' ? overduePenaltyPercent : <?php echo intval($overduePenaltyPercent); ?>) || 5;
    const feePerDay = (totalRental * rate) / 100;

    const rentalEl = document.getElementById('overdueModalRentalPrice');
    const rateEl = document.getElementById('overdueModalRate');
    const warningEl = document.getElementById('overdueModalWarningFee');

    if (rentalEl) rentalEl.textContent = '₱' + totalRental.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + (qty > 1 ? ` (${qty} items)` : '');
    if (rateEl) rateEl.textContent = rate + '% / day';
    if (warningEl) warningEl.textContent = '₱' + feePerDay.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' per day';

    modal.style.display = 'flex';
}

function closeOverdueCalcModal(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    const modal = document.getElementById('overdueCalcModal');
    if (modal) modal.style.display = 'none';
}
// Store stock quantity globally for quantity limits
window.currentStockQuantity = <?php echo isset($product['stock']) ? $product['stock'] : 999; ?>;

// Initialize button states
document.addEventListener('DOMContentLoaded', function() {
    const totalQuantityInput = document.getElementById('totalQuantity');
    const plusBtn = document.querySelector('.quantity-plus');
    const minusBtn = document.querySelector('.quantity-minus');
    
    if (totalQuantityInput && plusBtn && minusBtn) {
        const minValue = parseInt(totalQuantityInput.min) || 0;
        const maxValue = parseInt(totalQuantityInput.max);
        const currentValue = parseInt(totalQuantityInput.value) || 0;
        
        // Disable minus button if at min (0)
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

// Audio feedback
function playNotificationSound() {
    const audio = new Audio('ringtone/notification.MP3');
    audio.volume = 0.5;
    audio.play().catch(error => { console.log('Audio play failed:', error); });
}

// Show notification toast with dynamic icon, color, and title based on action intent
function showNotification(message, type = 'success', duration = 3000) {
    if (typeof type === 'number') {
        duration = type;
        type = null;
    }
    let modal = document.getElementById('notificationToast');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'notificationToast';
        modal.className = 'clear-chat-confirm-modal';
        modal.innerHTML = `
            <div class="clear-chat-confirm-card success-card" id="notificationToastCard">
                <div class="clear-chat-confirm-icon" id="notificationIcon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="clear-chat-confirm-body">
                    <div class="clear-chat-confirm-title" id="notificationTitle">Success</div>
                    <div class="clear-chat-confirm-desc" id="notificationMessage">Your action completed successfully.</div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    const card = document.getElementById('notificationToastCard');
    const titleEl = document.getElementById('notificationTitle');
    const msgEl = document.getElementById('notificationMessage');
    const iconEl = document.getElementById('notificationIcon');

    if (msgEl) msgEl.textContent = message;

    const msgLower = (message || '').toLowerCase();

    // Determine type: Error, Warning/Action-Required, Info, or Success
    let detectedType = type;
    if (!detectedType) {
        if (/fail|error|incorrect|invalid|cannot select|cannot add|not available|already booked|booked|conflict|rejected/i.test(msgLower)) {
            detectedType = 'error';
        } else if (/complete.*profile|profile.*first|profile|id photo|valid id|pending admin|verification|rejected|login required|please select|please fill|please agree|missing|required|limit reached|wait|checking|terms|policy/i.test(msgLower)) {
            detectedType = 'warning';
        } else if (/reset|standard rental|automatically set|guide|info/i.test(msgLower)) {
            detectedType = 'info';
        } else {
            detectedType = 'success';
        }
    }

    let cardClass = 'success-card';
    let title = 'Success';
    let iconHtml = '<i class="fas fa-check-circle"></i>';

    if (detectedType === 'error') {
        cardClass = 'error-card';
        title = 'Action Required';
        iconHtml = '<i class="fas fa-exclamation-circle"></i>';

        if (/date|booked|unavailable|conflict/i.test(msgLower)) {
            iconHtml = '<i class="fas fa-calendar-times"></i>';
            title = 'Date Unavailable';
        } else if (/cart/i.test(msgLower)) {
            iconHtml = '<i class="fas fa-cart-arrow-down"></i>';
            title = 'Cart Error';
        }
    } else if (detectedType === 'warning') {
        cardClass = 'warning-card';
        title = 'Notice';
        iconHtml = '<i class="fas fa-exclamation-triangle"></i>';

        if (/complete.*profile|profile.*first|profile/i.test(msgLower)) {
            iconHtml = '<i class="fas fa-user-edit"></i>';
            title = 'Profile Incomplete';
        } else if (/id|identification|verification/i.test(msgLower)) {
            iconHtml = '<i class="fas fa-id-card"></i>';
            title = 'Valid ID Required';
        } else if (/login/i.test(msgLower)) {
            iconHtml = '<i class="fas fa-sign-in-alt"></i>';
            title = 'Login Required';
        } else if (/date/i.test(msgLower)) {
            iconHtml = '<i class="fas fa-calendar-alt"></i>';
            title = 'Date Required';
        } else if (/size|measurement/i.test(msgLower)) {
            iconHtml = '<i class="fas fa-ruler-combined"></i>';
            title = 'Size Required';
        } else if (/agree|overdue|policy|terms/i.test(msgLower)) {
            iconHtml = '<i class="fas fa-file-contract"></i>';
            title = 'Agreement Required';
        } else if (/stock|quantity/i.test(msgLower)) {
            iconHtml = '<i class="fas fa-box-open"></i>';
            title = 'Stock Notice';
        } else if (/reserve or buy|type/i.test(msgLower)) {
            iconHtml = '<i class="fas fa-hand-pointer"></i>';
            title = 'Selection Required';
        }
    } else if (detectedType === 'info') {
        cardClass = 'info-card';
        title = 'Notice';
        iconHtml = '<i class="fas fa-info-circle"></i>';

        if (/date|duration/i.test(msgLower)) {
            iconHtml = '<i class="fas fa-calendar-check"></i>';
        }
    } else {
        cardClass = 'success-card';
        title = 'Success';
        iconHtml = '<i class="fas fa-check-circle"></i>';

        if (/cart/i.test(msgLower)) {
            iconHtml = '<i class="fas fa-cart-plus"></i>';
        } else if (/saved/i.test(msgLower)) {
            iconHtml = '<i class="fas fa-bookmark"></i>';
        }
    }

    if (card) {
        card.className = 'clear-chat-confirm-card ' + cardClass;
    }
    if (titleEl) {
        titleEl.textContent = title;
    }
    if (iconEl) {
        iconEl.innerHTML = iconHtml;
    }

    playNotificationSound();

    modal.classList.add('show');

    clearTimeout(modal.hideTimeout);
    modal.hideTimeout = setTimeout(() => {
        modal.classList.remove('show');
    }, duration);
}

// Save Product Function
function saveProduct() {
    // Check if user is logged in
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    
    if (!isLoggedIn) {
        // Redirect to login page
        showNotification('Login required', 'warning', 2000);
        setTimeout(() => {
            window.location.href = 'login_user/login.php';
        }, 1500);
        return;
    }
    
    // Get product details from PHP variables
    const productId = '<?php echo $product_id; ?>';
    const productName = '<?php echo addslashes($product['name']); ?>';
    const productCategory = '<?php echo addslashes($product['category']); ?>';
    const productPrice = '<?php echo $product['price']; ?>';
    const purchasePrice = '<?php echo isset($product['purchase_price']) ? $product['purchase_price'] : 0; ?>';
    const modelPath = '<?php echo addslashes($product['model_path']); ?>';
    
    // Debug: Log data being sent
    console.log('Saving product:', {
        product_id: productId,
        product_name: productName,
        product_category: productCategory,
        product_price: productPrice,
        purchase_price: purchasePrice,
        model_path: modelPath
    });
    
    // Send data to save_product.php
    fetch('save_product.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}&product_price=${productPrice}&purchase_price=${purchasePrice}&model_path=${encodeURIComponent(modelPath)}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Save response:', data);
        if (data.success) {
            showNotification('Product saved successfully!', 'success', 2000);
        } else {
            showNotification(data.message || 'Failed to save product', 'error', 2000);
        }
    })
    .catch(error => {
        console.error('Error saving product:', error);
        showNotification('Error saving product', 'error', 2000);
    });
}

// Play notification sound
function playNotificationSound() {
    const audio = new Audio('ringtone/notification.MP3');
    audio.volume = 0.5; // Set volume to 50%
    audio.play().catch(error => {
        console.log('Audio play failed:', error);
    });
}

// Reserve Page Functions
function resetFormToDisabledState() {
    // Reset type selection to no selection
    const radioReserve = document.getElementById('typeRadioReserve');
    const radioBuy = document.getElementById('typeRadioBuy');
    const textReserve = document.getElementById('typeTextReserve');
    const textBuy = document.getElementById('typeTextBuy');

    if (radioReserve) radioReserve.classList.remove('active');
    if (radioBuy) {
        radioBuy.classList.remove('active');
        radioBuy.classList.remove('buy-active');
    }
    if (textReserve) {
        textReserve.style.color = '#888';
        textReserve.style.fontWeight = 'normal';
    }
    if (textBuy) {
        textBuy.style.color = '#888';
        textBuy.style.fontWeight = 'normal';
    }

    // Clear form values
    clearReservationForm();

    // Disable form fields
    const quantityGroup = document.getElementById('reservationQuantityGroup');
    const measurementsContainer = document.getElementById('measurementsContainer');

    if (quantityGroup) {
        quantityGroup.style.opacity = '0.5';
        quantityGroup.style.pointerEvents = 'none';
    }

    if (measurementsContainer) {
        measurementsContainer.style.pointerEvents = 'none';
        measurementsContainer.innerHTML = '';
        // Generate size fields so they remain visible but not clickable
        generateSizeMeasurementFields();
        // Disable all select elements since type is not selected
        const selectElements = measurementsContainer.querySelectorAll('select');
        selectElements.forEach(select => {
            select.disabled = true;
        });
        // Disable size guide link
        const sizeGuideText = measurementsContainer.querySelector('.size-guide-text');
        if (sizeGuideText) {
            sizeGuideText.style.cursor = 'not-allowed';
            sizeGuideText.style.color = '#888';
            sizeGuideText.onclick = null;
        }
    }

    const overdueNotice = document.getElementById('overdueNoticeGroup') || document.querySelector('#reserveOverlay .overdue-policy-notice');
    const overdueGuideLine = document.getElementById('overdueGuideLine');
    const overdueAgree = document.getElementById('overdueAgreeCheckbox');
    if (overdueNotice) {
        overdueNotice.style.opacity = '0.5';
        overdueNotice.style.pointerEvents = 'none';
        overdueNotice.style.display = 'flex';
    }
    if (overdueGuideLine) {
        overdueGuideLine.style.display = 'block';
    }
    if (overdueAgree) {
        overdueAgree.disabled = true;
        overdueAgree.checked = false;
    }

    window.currentOverlayType = null;
    window.sizeGuideAutoOpenedForOverlay = false;

    // Update date input states
    updateReserveDateInputState();
    updateReturnDateInputState();
}

function showReservePage() {
    // Check if user is logged in
    const isLoggedIn = (typeof currentUser !== 'undefined' && currentUser && currentUser.isLoggedIn)
        ? true
        : (localStorage.getItem('isLoggedIn') === 'true');
    
    if (!isLoggedIn) {
        // Redirect to login page
        showNotification('Login required', 'warning', 2000);
        setTimeout(() => {
            window.location.href = 'login_user/login.php';
        }, 1500);
        return;
    }
    
    // Check if user has completed required profile fields
    const firstName = (typeof currentUser !== 'undefined' && currentUser && currentUser.first_name) ? currentUser.first_name : (localStorage.getItem('userFirstName') || '');
    const middleName = (typeof currentUser !== 'undefined' && currentUser && currentUser.middle_name) ? currentUser.middle_name : (localStorage.getItem('userMiddleName') || '');
    const lastName = (typeof currentUser !== 'undefined' && currentUser && currentUser.last_name) ? currentUser.last_name : (localStorage.getItem('userLastName') || '');
    const province = (typeof currentUser !== 'undefined' && currentUser && currentUser.province) ? currentUser.province : (localStorage.getItem('userProvince') || '');
    const city = (typeof currentUser !== 'undefined' && currentUser && currentUser.city) ? currentUser.city : (localStorage.getItem('userCity') || '');
    const barangay = (typeof currentUser !== 'undefined' && currentUser && currentUser.barangay) ? currentUser.barangay : (localStorage.getItem('userBarangay') || '');
    
    // Required fields: First Name, Last Name, Province, City/Town, Barangay
    const requiredFields = {
        'First Name': firstName,
        'Last Name': lastName,
        'Province': province,
        'City/Town': city,
        'Barangay': barangay
    };
    
    // Check which required fields are missing
    const missingFields = [];
    for (const [fieldName, value] of Object.entries(requiredFields)) {
        if (!value || value.trim() === '') {
            missingFields.push(fieldName);
        }
    }
    
    if (missingFields.length > 0) {
        // Store current product ID for redirect after profile completion
        const currentProductId = '<?php echo $product_id; ?>';
        if (currentProductId > 0) {
            localStorage.setItem('redirectAfterProfile', `index.php?id=${currentProductId}`);
        }
        
        // Show simple message about missing profile information
        showNotification('Complete your profile first', 'warning', 3000);
        
        // Redirect to profile page in edit mode after 2 seconds
        setTimeout(() => {
            window.location.href = 'profile.php?edit=true';
        }, 2000);
        return;
    }

    // Check Valid ID Requirements (Card front with user photo)
    const idType = (typeof currentUser !== 'undefined' && currentUser && currentUser.id_type) ? currentUser.id_type : (localStorage.getItem('userIdType') || '');
    const idFront = (typeof currentUser !== 'undefined' && currentUser && currentUser.id_front_image) ? currentUser.id_front_image : (localStorage.getItem('userIdFrontImage') || '');
    const idBack = (typeof currentUser !== 'undefined' && currentUser && currentUser.id_back_image) ? currentUser.id_back_image : (localStorage.getItem('userIdBackImage') || '');
    const hasIdPhoto = idFront || idBack;

    if (!idType || !hasIdPhoto) {
        // Store current product ID for redirect after profile completion
        const currentProductId = '<?php echo $product_id; ?>';
        if (currentProductId > 0) {
            localStorage.setItem('redirectAfterProfile', `index.php?id=${currentProductId}`);
        }
        
        if (!idType) {
            showNotification('Reservations will NOT be processed without a valid ID. Please select and upload in Profile.', 'warning', 4000);
        } else {
            showNotification('Reservations will NOT be processed without a valid ID photo (Front with User). Please upload in Profile.', 'warning', 4000);
        }
        setTimeout(() => {
            window.location.href = 'profile.php?upload_id=true#validIdRequirementsCard';
        }, 2000);
        return;
    }

    // Uploaded ID must be verified by admin before reserving or buying
    if (!checkIdVerifiedOrNotify()) {
        return;
    }
    
    // If all checks pass, show reserve page
    document.getElementById('topOverlay').style.display = 'block';
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        const overlay = document.getElementById('reserveOverlay');
        if (overlay) {
            overlay.style.display = 'block';
            // Reset form to disabled state and don't auto-select type
            resetFormToDisabledState();
            adjustReserveOverlayHeight(window.currentSizeBoxCount || 0);
        }
    }, 100);
}

// Returns true if the user's uploaded ID has been verified by admin (1 = verified, 2 = rejected, 0 = pending)
function checkIdVerifiedOrNotify() {
    const rawVerified = (typeof currentUser !== 'undefined' && currentUser && currentUser.id_verified !== undefined)
        ? currentUser.id_verified
        : (localStorage.getItem('userIdVerified') || 0);
    const idVerified = parseInt(rawVerified, 10) || 0;
    if (idVerified === 1) return true;

    if (idVerified === 2) {
        showNotification('Your uploaded ID was rejected by the admin. Please upload a new valid ID in your Profile.', 'warning', 4500);
        setTimeout(() => {
            window.location.href = 'profile.php?upload_id=true#validIdRequirementsCard';
        }, 2500);
    } else {
        showNotification('Your ID is pending admin verification. You can reserve or buy once it is verified.', 'warning', 4500);
    }
    return false;
}

function closeReservePage() {
    document.getElementById('reserveOverlay').style.display = 'none';
    document.getElementById('topOverlay').style.display = 'none';
    document.body.style.overflow = 'auto';
    // Reset form to disabled state when closing
    resetFormToDisabledState();
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

    // Clear all form values when type changes
    clearReservationForm();

    // Regenerate size fields with new type state (enabled)
    const measurementsContainer = document.getElementById('measurementsContainer');
    if (measurementsContainer) {
        generateSizeMeasurementFields();
    }

    // Enable form fields after type selection
    enableFormFields();

    // Immediately enable all size selects after type selection (critical for when stock is 1)
    setTimeout(() => {
        if (measurementsContainer) {
            measurementsContainer.style.pointerEvents = 'auto';
            measurementsContainer.style.opacity = '1';
            const allSelects = measurementsContainer.querySelectorAll('select');
            allSelects.forEach(select => {
                select.disabled = false;
            });
            // Also enable size guide texts
            const sizeGuideTexts = measurementsContainer.querySelectorAll('.size-guide-text');
            sizeGuideTexts.forEach(sizeGuideText => {
                sizeGuideText.style.cursor = 'pointer';
                sizeGuideText.style.color = '#6cd488';
                sizeGuideText.onclick = showSizeGuideImage;
            });
        }
    }, 100);

    const returnDateGroup = document.getElementById('returnDateGroup');
    const returnDateInput = document.getElementById('returnDate');
    const reserveDateGroup = document.getElementById('reserveDateGroup');
    const reserveDateLabel = document.getElementById('reserveDateLabel');
    const overdueNotice = document.getElementById('overdueNoticeGroup') || document.querySelector('#reserveOverlay .overdue-policy-notice');
    const overdueGuideLine = document.getElementById('overdueGuideLine');
    const overdueAgree = document.getElementById('overdueAgreeCheckbox');
    const overlayPriceElement = document.querySelector('#reserveOverlay .product-price-overlay');
    const overlayPurchasePriceElement = document.querySelector('#reserveOverlay .product-purchase-price-overlay');

    if (returnDateGroup) {
        returnDateGroup.style.display = isBuy ? 'none' : '';
    }
    if (returnDateInput) {
        returnDateInput.required = !isBuy;
        if (isBuy) returnDateInput.value = '';
    }
    updateReturnDateInputState();
    if (reserveDateGroup) {
        reserveDateGroup.style.gridColumn = isBuy ? '1 / -1' : '';
    }
    if (reserveDateLabel) {
        reserveDateLabel.textContent = isBuy ? 'Purchase Date' : 'Reserve Date';
    }
    if (overdueNotice) {
        if (isBuy) {
            overdueNotice.style.display = 'none';
        } else {
            overdueNotice.style.display = 'flex';
            overdueNotice.style.opacity = '1';
            overdueNotice.style.pointerEvents = 'auto';
        }
    }
    if (overdueGuideLine) {
        overdueGuideLine.style.display = isBuy ? 'none' : 'block';
    }
    if (overdueAgree) {
        overdueAgree.disabled = isBuy;
    }
    handleOverdueCheckboxChange();

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

    // Refresh unavailable dates for the selected type (buy vs reserve)
    if (typeof fetchUnavailableDates === 'function') {
        fetchUnavailableDates(window.currentOverlayType);
    }
    if (typeof updateOverlayPricesRealtime === 'function') {
        updateOverlayPricesRealtime();
    }
}

// Clear all reservation form values
function clearReservationForm() {
    const reserveDate = document.getElementById('reserveDate');
    const returnDate = document.getElementById('returnDate');
    const totalQuantity = document.getElementById('totalQuantity');
    const measurementsContainer = document.getElementById('measurementsContainer');

    if (reserveDate) reserveDate.value = '';
    if (returnDate) returnDate.value = '';
    if (totalQuantity) totalQuantity.value = '0';
    if (measurementsContainer) {
        measurementsContainer.innerHTML = '';
    }
    const overdueAgree = document.getElementById('overdueAgreeCheckbox');
    if (overdueAgree) {
        overdueAgree.checked = false;
    }
    handleOverdueCheckboxChange();
    updateQuantityButtons(0, window.currentStockQuantity || 999);
}

function updateQuantityInteractiveState() {
    const overdueAgree = document.getElementById('overdueAgreeCheckbox');
    const quantityGroup = document.getElementById('reservationQuantityGroup');
    const isBuy = (window.currentOverlayType === 'buy');
    const reserveDateVal = document.getElementById('reserveDate')?.value;

    const hasDate = Boolean(reserveDateVal && reserveDateVal.trim() !== '');
    const isReady = isBuy ? hasDate : (hasDate && overdueAgree && overdueAgree.checked);

    if (quantityGroup) {
        if (isReady) {
            quantityGroup.style.opacity = '1';
            quantityGroup.style.pointerEvents = 'auto';
            quantityGroup.removeAttribute('title');
        } else {
            quantityGroup.style.opacity = '0.5';
            quantityGroup.style.pointerEvents = 'none';
            if (!hasDate) {
                quantityGroup.setAttribute('title', isBuy ? 'Please select Purchase Date first' : 'Please select Reserve Date first');
            } else if (!isBuy && (!overdueAgree || !overdueAgree.checked)) {
                quantityGroup.setAttribute('title', 'Please agree to the overdue fee policy checkbox first');
            }
        }
    }
}

function handleOverdueCheckboxChange() {
    updateQuantityInteractiveState();
}

function handleQuantityWrapperClick(event) {
    const quantityGroup = document.getElementById('reservationQuantityGroup');
    if (quantityGroup && quantityGroup.style.pointerEvents === 'none') {
        const typeSelected = window.currentOverlayType !== null && window.currentOverlayType !== undefined;
        if (!typeSelected) {
            showNotification('Please select Type (Reserve or Buy) first', 'warning', 2500);
            return;
        }

        const isBuy = (window.currentOverlayType === 'buy');
        const reserveDateVal = document.getElementById('reserveDate')?.value;
        if (!reserveDateVal) {
            showNotification(isBuy ? 'Please select a Purchase Date first before setting Quantity' : 'Please select a Reserve Date first before setting Quantity', 'warning', 2500);
            return;
        }

        const overdueAgree = document.getElementById('overdueAgreeCheckbox');
        if (!isBuy && (!overdueAgree || !overdueAgree.checked)) {
            showNotification('Please agree to the overdue fee policy checkbox first', 'warning', 2500);
            return;
        }
    }
}

// Enable form fields after type selection
function enableFormFields() {
    updateQuantityInteractiveState();

    if (measurementsContainer) {
        measurementsContainer.style.pointerEvents = 'auto';
        measurementsContainer.style.opacity = '1';
    }

    // Update date input states
    updateReserveDateInputState();
    updateReturnDateInputState();

    // Enable all select elements in measurements container across all measurement groups
    const selectElements = measurementsContainer ? measurementsContainer.querySelectorAll('select') : [];
    selectElements.forEach(select => {
        select.disabled = false;
    });

    // Update size guide text if it exists in all measurement groups
    const sizeGuideTexts = measurementsContainer ? measurementsContainer.querySelectorAll('.size-guide-text') : [];
    sizeGuideTexts.forEach(sizeGuideText => {
        sizeGuideText.style.cursor = 'pointer';
        sizeGuideText.style.color = '#6cd488';
        sizeGuideText.onclick = showSizeGuideImage;
    });

    // Ensure all existing measurement groups have enabled selects (important when stock is 1)
    setTimeout(() => {
        const measurementGroups = measurementsContainer ? measurementsContainer.querySelectorAll('.measurement-group') : [];
        measurementGroups.forEach(group => {
            const groupSelects = group.querySelectorAll('select');
            groupSelects.forEach(select => {
                select.disabled = false;
            });
        });
    }, 50);
}

// Handle Check Out button click
function handleCheckout() {
    const actionType = (window.currentOverlayType === 'buy') ? 'buy' : 'reserve';
    _submitProductAction(actionType);
}

// Submit Reservation function (backwards compatibility)
function submitReservation() {
    _submitProductAction('reserve');
}

// Submit Buy function (backwards compatibility)
function submitBuy() {
    _submitProductAction('buy');
}

// Shared handler for Reserve and Buy actions
function _submitProductAction(actionType) {
    // Get form data
    const reserveDate = document.getElementById('reserveDate').value;
    const returnDate = document.getElementById('returnDate').value;
    const totalQuantity = parseInt(document.getElementById('totalQuantity').value) || 0;
    if (totalQuantity <= 0) {
        showNotification('Please select a quantity of at least 1', 'warning', 2500);
        return;
    }

    // Get dynamic size inputs
    const sizeInputs = document.querySelectorAll('#measurementsContainer select');
    let hasEmpty = false;
    const sizeData = {};
    sizeInputs.forEach(input => {
        if (!input.value) {
            hasEmpty = true;
        } else {
            sizeData[input.name] = input.value;
        }
    });

    const isBuy = (actionType === 'buy');

    // Validate required fields
    if (isBuy) {
        if (!reserveDate) {
            showNotification('Please select a purchase date', 'warning', 2000);
            return;
        }
        if (typeof unavailableDates !== 'undefined' && unavailableDates.includes(reserveDate)) {
            showNotification('Selected purchase date is not available. Please choose a date after all existing reservations.', 'error', 3500);
            return;
        }
    } else {
        if (!reserveDate || !returnDate) {
            showNotification('Please fill reserve and return dates', 'warning', 2000);
            return;
        }
        if (typeof unavailableDates !== 'undefined' && (unavailableDates.includes(reserveDate) || unavailableDates.includes(returnDate))) {
            showNotification('Selected reservation dates are not available.', 'error', 3000);
            return;
        }

        // Validate Overdue Fee agreement for reservations
        const overdueAgree = document.getElementById('overdueAgreeCheckbox');
        if (overdueAgree && !overdueAgree.checked) {
            showNotification('Please agree to the Overdue Fee to proceed', 'warning', 3000);
            overdueAgree.classList.add('overdue-checkbox-highlight');
            setTimeout(() => overdueAgree.classList.remove('overdue-checkbox-highlight'), 1200);
            overdueAgree.focus();
            return;
        }
    }

    // Check that all size measurements are selected if size options exist
    if (sizeInputs.length > 0 && hasEmpty) {
        showNotification('Please select all size measurements', 'warning', 2000);
        return;
    }

    // Check if user is logged in
    const isLoggedIn = (typeof currentUser !== 'undefined' && currentUser && currentUser.isLoggedIn)
        ? true
        : (localStorage.getItem('isLoggedIn') === 'true');
    if (!isLoggedIn) {
        showNotification('Login required', 'warning', 2000);
        setTimeout(() => { window.location.href = 'login_user/login.php'; }, 1500);
        return;
    }

    // Check Valid ID Requirements for reservations and purchases (Card front with user photo)
    if (actionType === 'reserve' || actionType === 'buy') {
        const idType = (typeof currentUser !== 'undefined' && currentUser && currentUser.id_type) ? currentUser.id_type : (localStorage.getItem('userIdType') || '');
        const idFront = (typeof currentUser !== 'undefined' && currentUser && currentUser.id_front_image) ? currentUser.id_front_image : (localStorage.getItem('userIdFrontImage') || '');
        const idBack = (typeof currentUser !== 'undefined' && currentUser && currentUser.id_back_image) ? currentUser.id_back_image : (localStorage.getItem('userIdBackImage') || '');
        const hasIdPhoto = idFront || idBack;

        if (!idType || !hasIdPhoto) {
            // Store current product ID for redirect after profile completion
            const currentProductId = <?php echo intval($product['id']); ?>;
            if (currentProductId > 0) {
                localStorage.setItem('redirectAfterProfile', `index.php?id=${currentProductId}`);
            }
            
            if (!idType) {
                showNotification('Reservations will NOT be processed without a valid ID. Please select and upload in Profile.', 'warning', 4000);
            } else {
                showNotification('Reservations will NOT be processed without a valid ID photo (Front with User). Please upload in Profile.', 'warning', 4000);
            }
            setTimeout(() => {
                window.location.href = 'profile.php?upload_id=true#validIdRequirementsCard';
            }, 2000);
            return;
        }

        // Uploaded ID must be verified by admin before reserving or buying
        if (!checkIdVerifiedOrNotify()) {
            return;
        }
    }

    // Product details from PHP
    const productId   = <?php echo intval($product['id']); ?>;
    const productName = '<?php echo addslashes($product['name']); ?>';
    const productCategory = '<?php echo addslashes($product['category']); ?>';
    const rentalPrice   = <?php echo floatval($discountedPrice); ?>;
    const purchasePrice = <?php echo floatval(isset($product['purchase_price']) ? $product['purchase_price'] : 0); ?>;
    const modelPath   = '<?php echo addslashes($product['model_path']); ?>';
    const extraFeePerUnit = window.currentExtraFeePerUnit || 0;
    const effectiveRentalPrice = rentalPrice + extraFeePerUnit;

    // Choose price based on action
    const priceToSave  = isBuy ? (purchasePrice > 0 ? purchasePrice : rentalPrice) : effectiveRentalPrice;
    const imagePath = '<?php echo addslashes(!empty($product['image_path']) ? $product['image_path'] : (!empty($product['image_path2']) ? $product['image_path2'] : (!empty($product['image_path3']) ? $product['image_path3'] : (!empty($product['image_path4']) ? $product['image_path4'] : '')))); ?>';

    // Build items array for multi-item quantity
    const itemsList = [];
    for (let itemNum = 1; itemNum <= totalQuantity; itemNum++) {
        const itemObj = { item_number: itemNum };
        for (let s = 1; s <= 10; s++) {
            itemObj[`size_${s}`] = sizeData[`size_${s}_${itemNum}`] || (itemNum === 1 ? (sizeData[`size_${s}`] || '') : '');
        }
        itemsList.push(itemObj);
    }

    // Direct checkout item — do NOT save into saved_products / save.php
    const productItem = {
        id: 0,
        product_id: productId,
        name: productName,
        category: productCategory,
        price: priceToSave,
        original_price: <?php echo floatval($originalPrice); ?>,
        discount: <?php echo floatval($discount); ?>,
        purchase_price: purchasePrice,
        extension_fee: extraFeePerUnit,
        modelPath: modelPath,
        imagePath: imagePath,
        quantity: totalQuantity,
        status: actionType,
        reserve_date: reserveDate,
        return_date: isBuy ? '' : returnDate,
        size_1: itemsList[0]?.size_1 || '',
        size_2: itemsList[0]?.size_2 || '',
        size_3: itemsList[0]?.size_3 || '',
        size_4: itemsList[0]?.size_4 || '',
        size_5: itemsList[0]?.size_5 || '',
        size_6: itemsList[0]?.size_6 || '',
        size_7: itemsList[0]?.size_7 || '',
        size_8: itemsList[0]?.size_8 || '',
        size_9: itemsList[0]?.size_9 || '',
        size_10: itemsList[0]?.size_10 || '',
        items: itemsList
    };

    const params = new URLSearchParams({
        products: JSON.stringify([productItem]),
        reserveDate: reserveDate,
        returnDate: isBuy ? '' : returnDate,
        action: actionType
    });

    window.location.href = `product_reserve.php?${params.toString()}`;
}

// Check if user is admin
document.addEventListener('DOMContentLoaded', function() {
    const adminButton = document.getElementById('adminButton');
    const userRole = '<?php echo isset($_SESSION['user_role']) ? $_SESSION['user_role'] : ''; ?>';
    
    if (userRole === 'admin') {
        adminButton.style.display = 'flex';
    }
    
    // Generate dynamic size inputs immediately (0 network latency)
    generateSizeMeasurementFields();
    // Non-critical background updates deferred so product renders with 0 lag
    setTimeout(loadProducts, 300);
    setTimeout(updateCartCount, 100);
});

// Function to update cart count from database API
function updateCartCount() {
    const cartCountElement = document.getElementById('cartCount');
    if (!cartCountElement) return;

    // Fetch cart count from database
    fetch('save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
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

// Function to dynamically generate size measurement fields immediately without round-trip AJAX
function generateSizeMeasurementFields() {
    const measurementsContainer = document.getElementById('measurementsContainer');
    if (!measurementsContainer) return;

    // Check if type is selected for interactivity, or if container is disabled
    const typeSelected = window.currentOverlayType !== null && window.currentOverlayType !== undefined;
    const containerDisabled = measurementsContainer.style.pointerEvents === 'none';
    const isDisabled = !typeSelected || containerDisabled;

    const categoryData = <?php echo json_encode($categorySizeData ?: new stdClass()); ?>;
    const productData = <?php echo json_encode([
        'size_1_min' => $product['size_1_min'] ?? null,
        'size_1_max' => $product['size_1_max'] ?? null,
        'size_2_min' => $product['size_2_min'] ?? null,
        'size_2_max' => $product['size_2_max'] ?? null,
        'size_3_min' => $product['size_3_min'] ?? null,
        'size_3_max' => $product['size_3_max'] ?? null,
        'size_4_min' => $product['size_4_min'] ?? null,
        'size_4_max' => $product['size_4_max'] ?? null,
        'size_5_min' => $product['size_5_min'] ?? null,
        'size_5_max' => $product['size_5_max'] ?? null,
        'size_6_min' => $product['size_6_min'] ?? null,
        'size_6_max' => $product['size_6_max'] ?? null,
        'size_7_min' => $product['size_7_min'] ?? null,
        'size_7_max' => $product['size_7_max'] ?? null,
        'size_8_min' => $product['size_8_min'] ?? null,
        'size_8_max' => $product['size_8_max'] ?? null,
        'size_9_min' => $product['size_9_min'] ?? null,
        'size_9_max' => $product['size_9_max'] ?? null,
        'size_10_min' => $product['size_10_min'] ?? null,
        'size_10_max' => $product['size_10_max'] ?? null
    ]); ?>;

    window.currentSizeGuideImage = categoryData.size_guide_image || null;

    const sizeLabels = [];
    const sizeRanges = {};
    for (let i = 1; i <= 10; i++) {
        const label = categoryData[`size_label_${i}`];
        if (label && label.trim() !== '') {
            const rawMin = productData[`size_${i}_min`];
            const rawMax = productData[`size_${i}_max`];
            const min = (rawMin !== null && rawMin !== undefined && rawMin !== '') ? parseInt(rawMin) : 50;
            const max = (rawMax !== null && rawMax !== undefined && rawMax !== '') ? parseInt(rawMax) : 130;
            if (rawMin === 0 && rawMax === 0) continue;
            sizeLabels.push({
                index: i,
                label: label,
                min: min,
                max: max
            });
            sizeRanges[`size_${i}_min`] = min;
            sizeRanges[`size_${i}_max`] = max;
        }
    }

    // Store labels and ranges globally for dynamic item generation
    window.currentSizeLabels = sizeLabels;
    window.currentSizeRanges = sizeRanges;
    window.currentStockQuantity = <?php echo isset($product['stock']) ? intval($product['stock']) : 999; ?>;

    // Clear existing fields
    measurementsContainer.innerHTML = '';

    const currentQty = parseInt(document.getElementById('totalQuantity')?.value) || 0;
    if (currentQty > 0 && sizeLabels.length > 0) {
        for (let k = 1; k <= currentQty; k++) {
            addMeasurementGroup(k);
        }
        adjustReserveOverlayHeight(sizeLabels.length);
        updateQuantityButtons(currentQty, window.currentStockQuantity);
    } else {
        updateQuantityButtons(currentQty, window.currentStockQuantity);
    }

    // Enable all select elements if type is selected, disable if not
    setTimeout(() => {
        const selectElements = measurementsContainer.querySelectorAll('select');
        selectElements.forEach(select => {
            select.disabled = isDisabled;
        });

        // Enable size guide text if type is selected
        const sizeGuideTexts = measurementsContainer.querySelectorAll('.size-guide-text');
        sizeGuideTexts.forEach(sizeGuideText => {
            if (isDisabled) {
                sizeGuideText.style.cursor = 'not-allowed';
                sizeGuideText.style.color = '#888';
                sizeGuideText.onclick = null;
            } else {
                sizeGuideText.style.cursor = 'pointer';
                sizeGuideText.style.color = '#6cd488';
                sizeGuideText.onclick = showSizeGuideImage;
            }
        });
    }, 50);
}

// Add a single measurement group for an item index (Item 1 is 'Size', Item 2 is 'Size 2', etc.)
function addMeasurementGroup(index) {
    const measurementsContainer = document.getElementById('measurementsContainer');
    if (!measurementsContainer) return;
    const reservationForm = document.querySelector('.reservation-form');

    const typeSelected = window.currentOverlayType !== null && window.currentOverlayType !== undefined;
    const containerDisabled = measurementsContainer.style.pointerEvents === 'none';
    const isDisabled = !typeSelected || containerDisabled;

    // Ensure size boxes are clickable after type and quantity selection
    if (typeSelected) {
        measurementsContainer.style.pointerEvents = 'auto';
        measurementsContainer.style.opacity = '1';
    }

    const sizeLabels = window.currentSizeLabels || [
        { index: 1, label: 'Chest / Bust', min: 70, max: 120 },
        { index: 2, label: 'Waist', min: 60, max: 100 },
        { index: 3, label: 'Hips', min: 80, max: 130 },
        { index: 4, label: 'Shoulder', min: 40, max: 60 },
        { index: 5, label: 'Arm Length', min: 50, max: 70 },
        { index: 6, label: 'Sleeve Length', min: 20, max: 40 },
        { index: 7, label: 'Inseam', min: 70, max: 90 },
        { index: 8, label: 'Thigh', min: 50, max: 70 },
        { index: 9, label: 'Neck', min: 30, max: 45 },
        { index: 10, label: 'Wrist', min: 15, max: 25 }
    ];
    const sizeRanges = window.currentSizeRanges || {};

    // Generate size dropdowns dynamically based on category size labels
    let sizeDropdownsHTML = '';
    sizeLabels.forEach(sizeLabel => {
        const min = (sizeRanges[`size_${sizeLabel.index}_min`] !== undefined && sizeRanges[`size_${sizeLabel.index}_min`] !== null) ? sizeRanges[`size_${sizeLabel.index}_min`] : (sizeLabel.min || 50);
        const max = (sizeRanges[`size_${sizeLabel.index}_max`] !== undefined && sizeRanges[`size_${sizeLabel.index}_max`] !== null) ? sizeRanges[`size_${sizeLabel.index}_max`] : (sizeLabel.max || 130);
        // Enable select elements when type is selected and container is not disabled
        const selectDisabled = isDisabled ? 'disabled' : '';
        sizeDropdownsHTML += `
            <div class="form-group">
                <label for="size_${sizeLabel.index}_${index}">${sizeLabel.label}</label>
                <select id="size_${sizeLabel.index}_${index}" name="size_${sizeLabel.index}_${index}" required ${selectDisabled}>
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
            ${index === 1 ? `<span class="size-guide-text" onclick="${isDisabled ? '' : 'showSizeGuideImage()'}" style="cursor: ${isDisabled ? 'not-allowed' : 'pointer'}; color: ${isDisabled ? '#888' : '#6cd488'}; font-size: 0.85rem; font-weight: 500;">Size Guide</span>` : ''}
        </div>
        <div class="form-row sizes-row">
            ${sizeDropdownsHTML}
        </div>
    `;

    measurementsContainer.appendChild(fieldGroup);

    // Auto-scroll to the newly added measurement group
    if (index > 1) {
        setTimeout(() => {
            fieldGroup.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }

    // Ensure all select elements are enabled when type is selected
    if (typeSelected) {
        setTimeout(() => {
            const selectElements = fieldGroup.querySelectorAll('select');
            selectElements.forEach(select => {
                select.disabled = false;
            });
            // Enable size guide text for the first item
            if (index === 1) {
                const sizeGuideText = fieldGroup.querySelector('.size-guide-text');
                if (sizeGuideText) {
                    sizeGuideText.style.cursor = 'pointer';
                    sizeGuideText.style.color = '#6cd488';
                    sizeGuideText.onclick = showSizeGuideImage;
                }
            }
        }, 50);
    }

    // Update total quantity input
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
function showSizeGuideImage(isAuto = false) {
    // Check if type is selected first
    const typeSelected = window.currentOverlayType !== null && window.currentOverlayType !== undefined;
    if (!typeSelected) {
        if (!isAuto) showNotification('Please select Type (Reserve or Buy) first', 'warning', 2000);
        return;
    }

    const sizeGuideImage = window.currentSizeGuideImage;
    if (!sizeGuideImage) {
        if (!isAuto) showNotification('No size guide image available for this category.', 'warning', 2000);
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
            z-index: 99999;
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

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeSizeGuideModal();
            }
        });
    }

    document.getElementById('sizeGuideModalImage').src = sizeGuideImage;
    modal.style.display = 'flex';
}

function closeSizeGuideModal() {
    const modal = document.getElementById('sizeGuideModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Load existing reservation/cart details from database into reserve form (for edit/resume only)
function loadExistingReservation() {
    const productName = '<?php echo addslashes($product['name']); ?>';
    const productCategory = '<?php echo addslashes($product['category']); ?>';

    fetch('save_product.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_reservation&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.reservation) {
            const reservation = data.reservation;

            // Populate date fields
            const reserveDateValue = reservation.reserve_date;
            const returnDateValue = reservation.return_date;
            const isValidDate = (dateStr) => dateStr && dateStr !== '0000-00-00' && dateStr !== '00-00-0000' && dateStr.trim() !== '';

            if (isValidDate(reserveDateValue)) {
                document.getElementById('reserveDate').value = reserveDateValue;
            }
            if (isValidDate(returnDateValue)) {
                document.getElementById('returnDate').value = returnDateValue;
            }
            updateReturnDateInputState();

            const qty = parseInt(reservation.quantity) || 1;
            const measurementsContainer = document.getElementById('measurementsContainer');
            if (measurementsContainer) {
                measurementsContainer.innerHTML = '';
                for (let k = 1; k <= qty; k++) {
                    addMeasurementGroup(k);
                }
            }
            const totalQuantityInput = document.getElementById('totalQuantity');
            if (totalQuantityInput) totalQuantityInput.value = qty;
            updateQuantityButtons(qty, window.currentStockQuantity || 999);

            // Populate Item 1 size inputs
            for (let i = 1; i <= 10; i++) {
                const sizeInput = document.getElementById(`size_${i}_1`) || document.getElementById(`size_${i}`);
                const sizeValue = reservation[`size_${i}`];
                if (sizeInput && sizeValue) {
                    sizeInput.value = sizeValue;
                }
            }

            // Populate Item 2+ if items array returned
            if (data.items && Array.isArray(data.items)) {
                data.items.forEach(itemRow => {
                    const itemNum = itemRow.item_number;
                    for (let i = 1; i <= 10; i++) {
                        const sInput = document.getElementById(`size_${i}_${itemNum}`);
                        if (sInput && itemRow[`size_${i}`]) {
                            sInput.value = itemRow[`size_${i}`];
                        }
                    }
                });
            }

            if (reservation.product_status) {
                setReservationType(reservation.product_status === 'buy' ? 'buy' : 'reserve');
            }
        }
    })
    .catch(error => {
        console.error('Error loading existing reservation:', error);
    });
}

// Function to generate size options from min to max
function generateSizeOptions(min, max) {
    let options = '';
    for (let i = min; i <= max; i++) {
        options += `<option value="${i}">${i} cm</option>`;
    }
    return options;
}

// Function to dynamically adjust reserve-overlay height on mobile based on number of size boxes and items
function adjustReserveOverlayHeight(sizeBoxCount) {
    const isMobile = window.innerWidth <= 768;
    const reserveOverlay = document.querySelector('.reserve-overlay') || document.getElementById('reserveOverlay');
    if (!reserveOverlay) return;

    if (!isMobile) {
        reserveOverlay.style.height = '';
        return;
    }

    const count = (typeof sizeBoxCount === 'number' && sizeBoxCount > 0)
        ? sizeBoxCount
        : (window.currentSizeLabels ? window.currentSizeLabels.length : (document.querySelectorAll('#measurementsContainer .sizes-row:first-child .form-group').length || 0));

    const totalQty = parseInt(document.getElementById('totalQuantity')?.value) || 1;

    let height;
    if (totalQty > 1) {
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

// Update plus and minus buttons based on current count and max stock
function updateQuantityButtons(currentCount, maxQuantity) {
    const plusBtn = document.querySelector('.quantity-plus');
    const minusBtn = document.querySelector('.quantity-minus');

    if (minusBtn) {
        if (currentCount <= 0) {
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
        if (currentCount >= maxQuantity) {
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
    // Check if type is selected first
    const typeSelected = window.currentOverlayType !== null && window.currentOverlayType !== undefined;
    if (!typeSelected) {
        showNotification('Please select Type (Reserve or Buy) first', 'warning', 2000);
        return;
    }

    const isBuy = (window.currentOverlayType === 'buy');
    const reserveDateVal = document.getElementById('reserveDate')?.value;

    // Check if Reserve Date or Purchase Date is set first
    if (!reserveDateVal) {
        showNotification(isBuy ? 'Please select a Purchase Date first before setting Quantity' : 'Please select a Reserve Date first before setting Quantity', 'warning', 2500);
        return;
    }

    // Check overdue fee agreement checkbox for rentals
    const overdueAgree = document.getElementById('overdueAgreeCheckbox');
    if (!isBuy && (!overdueAgree || !overdueAgree.checked)) {
        showNotification('Please agree to the overdue fee policy checkbox first', 'warning', 2500);
        return;
    }

    const measurementsContainer = document.getElementById('measurementsContainer');
    const totalQuantityInput = document.getElementById('totalQuantity');
    const measurementGroups = measurementsContainer ? measurementsContainer.querySelectorAll('.measurement-group') : [];
    const rawQty = parseInt(totalQuantityInput?.value);
    const currentCount = !isNaN(rawQty) ? rawQty : measurementGroups.length;
    const maxQuantity = window.currentStockQuantity || <?php echo isset($product['stock']) ? intval($product['stock']) : 999; ?>;

    if (currentCount < maxQuantity) {
        const newIndex = currentCount + 1;
        addMeasurementGroup(newIndex);

        const updatedGroups = measurementsContainer ? measurementsContainer.querySelectorAll('.measurement-group') : [];
        const newCount = updatedGroups.length || newIndex;

        if (totalQuantityInput) {
            totalQuantityInput.value = newCount;
        }

        // Ensure measurements container is enabled and all selects are clickable
        if (measurementsContainer) {
            measurementsContainer.style.pointerEvents = 'auto';
            measurementsContainer.style.opacity = '1';
        }

        // Enable all select elements in all measurement groups (including existing ones)
        setTimeout(() => {
            const allGroups = measurementsContainer ? measurementsContainer.querySelectorAll('.measurement-group') : [];
            allGroups.forEach(group => {
                const selectElements = group.querySelectorAll('select');
                selectElements.forEach(select => {
                    select.disabled = false;
                });
            });
        }, 50);

        updateQuantityButtons(newCount, maxQuantity);
        adjustReserveOverlayHeight();
        if (typeof updateOverlayPricesRealtime === 'function') {
            updateOverlayPricesRealtime();
        }
        if (typeof fetchUnavailableDates === 'function') {
            fetchUnavailableDates(window.currentOverlayType, newCount);
        }

        // Auto open Size Guide on the first click in Quantity only (only once per overlay open)
        if (!window.sizeGuideAutoOpenedForOverlay) {
            window.sizeGuideAutoOpenedForOverlay = true;
            if (typeof showSizeGuideImage === 'function' && window.currentSizeGuideImage) {
                showSizeGuideImage(true);
            }
        }
    } else {
        showNotification('Cannot add more items - stock limit reached', 'warning', 2000);
        updateQuantityButtons(currentCount, maxQuantity);
    }
}

function decrementQuantity() {
    // Check if type is selected first
    const typeSelected = window.currentOverlayType !== null && window.currentOverlayType !== undefined;
    if (!typeSelected) {
        showNotification('Please select Type (Reserve or Buy) first', 'warning', 2000);
        return;
    }

    const isBuy = (window.currentOverlayType === 'buy');
    const reserveDateVal = document.getElementById('reserveDate')?.value;

    // Check if Reserve Date or Purchase Date is set first
    if (!reserveDateVal) {
        showNotification(isBuy ? 'Please select a Purchase Date first before setting Quantity' : 'Please select a Reserve Date first before setting Quantity', 'warning', 2500);
        return;
    }

    // Check overdue fee agreement checkbox for rentals
    const overdueAgree = document.getElementById('overdueAgreeCheckbox');
    if (!isBuy && (!overdueAgree || !overdueAgree.checked)) {
        showNotification('Please agree to the overdue fee policy checkbox first', 'warning', 2500);
        return;
    }

    const measurementsContainer = document.getElementById('measurementsContainer');
    const totalQuantityInput = document.getElementById('totalQuantity');
    const measurementGroups = measurementsContainer ? measurementsContainer.querySelectorAll('.measurement-group') : [];
    const rawQty = parseInt(totalQuantityInput?.value);
    const currentCount = !isNaN(rawQty) ? rawQty : measurementGroups.length;
    const maxQuantity = window.currentStockQuantity || <?php echo isset($product['stock']) ? intval($product['stock']) : 999; ?>;

    if (currentCount > 0) {
        if (measurementGroups.length > 0) {
            measurementGroups[measurementGroups.length - 1].remove();
        }

        const newCount = Math.max(0, currentCount - 1);
        if (totalQuantityInput) {
            totalQuantityInput.value = newCount;
        }

        const reservationForm = document.querySelector('.reservation-form');
        if (reservationForm && newCount <= 1) {
            reservationForm.style.overflowY = 'auto';
        }

        // Ensure measurements container remains enabled and all remaining selects are clickable
        if (measurementsContainer && newCount > 0) {
            measurementsContainer.style.pointerEvents = 'auto';
            measurementsContainer.style.opacity = '1';
        }

        // Enable all select elements in all remaining measurement groups
        setTimeout(() => {
            const allGroups = measurementsContainer ? measurementsContainer.querySelectorAll('.measurement-group') : [];
            allGroups.forEach(group => {
                const selectElements = group.querySelectorAll('select');
                selectElements.forEach(select => {
                    select.disabled = false;
                });
            });
        }, 50);

        updateQuantityButtons(newCount, maxQuantity);
        adjustReserveOverlayHeight();
        if (typeof updateOverlayPricesRealtime === 'function') {
            updateOverlayPricesRealtime();
        }
        if (typeof fetchUnavailableDates === 'function') {
            fetchUnavailableDates(window.currentOverlayType, newCount);
        }
    } else {
        updateQuantityButtons(1, maxQuantity);
    }
}

// Add to Cart function
function addToCart() {
    const isBuy = (window.currentOverlayType === 'buy');

    // Get form data
    const reserveDate = document.getElementById('reserveDate').value;
    const returnDate = document.getElementById('returnDate').value;
    const totalQuantity = parseInt(document.getElementById('totalQuantity').value) || 0;
    if (totalQuantity <= 0) {
        showNotification('Please select a quantity of at least 1', 'warning', 2500);
        return;
    }
    
    // Get dynamic size inputs
    const sizeInputs = document.querySelectorAll('#measurementsContainer select');
    let hasEmpty = false;
    const sizeData = {};
    sizeInputs.forEach(input => {
        if (!input.value) {
            hasEmpty = true;
        } else {
            sizeData[input.name] = input.value;
        }
    });

    // Validate required fields
    if (isBuy) {
        if (!reserveDate) {
            showNotification('Please select a purchase date', 'warning', 2000);
            return;
        }
        if (typeof unavailableDates !== 'undefined' && unavailableDates.includes(reserveDate)) {
            showNotification('Selected purchase date is not available. Please choose a date after all existing reservations.', 'error', 3500);
            return;
        }
    } else {
        if (!reserveDate || !returnDate) {
            showNotification('Please fill reserve and return dates', 'warning', 2000);
            return;
        }
        if (typeof unavailableDates !== 'undefined' && (unavailableDates.includes(reserveDate) || unavailableDates.includes(returnDate))) {
            showNotification('Selected reservation dates are not available.', 'error', 3000);
            return;
        }

        // Validate Overdue Fee agreement for reservations
        const overdueAgree = document.getElementById('overdueAgreeCheckbox');
        if (overdueAgree && !overdueAgree.checked) {
            showNotification('Please agree to the Overdue Fee to proceed', 'warning', 3000);
            overdueAgree.classList.add('overdue-checkbox-highlight');
            setTimeout(() => overdueAgree.classList.remove('overdue-checkbox-highlight'), 1200);
            overdueAgree.focus();
            return;
        }
    }
    
    // Check if all size inputs are filled
    if (sizeInputs.length > 0 && hasEmpty) {
        showNotification('Please select all size measurements', 'warning', 2000);
        return;
    }
    
    // Get product details
    const productId = <?php echo $product['id']; ?>;
    const productName = '<?php echo addslashes($product['name']); ?>';
    const productCategory = '<?php echo addslashes($product['category']); ?>';
    const rentalPrice = '<?php echo $product['price']; ?>';
    const purchasePrice = '<?php echo isset($product['purchase_price']) ? $product['purchase_price'] : 0; ?>';
    const extraFeePerUnit = window.currentExtraFeePerUnit || 0;
    const effectiveRentalPrice = parseFloat(rentalPrice) + extraFeePerUnit;
    const effectivePrice = isBuy ? (parseFloat(purchasePrice) > 0 ? purchasePrice : rentalPrice) : effectiveRentalPrice;
    const modelPath = '<?php echo addslashes($product['model_path']); ?>';
    const stock = <?php echo isset($product['stock']) ? $product['stock'] : 999; ?>;

    // Check if user is logged in
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';

    if (!isLoggedIn) {
        showNotification('Login required', 'warning', 2000);
        setTimeout(() => {
            window.location.href = 'login_user/login.php';
        }, 1500);
        return;
    }

    const actionStatus = isBuy ? 'buy' : 'saved';

    // Prepare form data with dates, sizes, and quantity
    let formData = `action=add_to_cart&product_id=${productId}&product_name=${encodeURIComponent(productName)}&product_category=${encodeURIComponent(productCategory)}&product_price=${effectivePrice}&purchase_price=${purchasePrice}&model_path=${encodeURIComponent(modelPath)}&reserve_date=${reserveDate}&return_date=${isBuy ? '' : returnDate}&quantity=${totalQuantity}&stock=${stock}&product_status=${actionStatus}`;
    
    // Add size measurements for item 1 (size_1..size_10) and all items (size_X_Y)
    for (const [key, value] of Object.entries(sizeData)) {
        formData += `&${encodeURIComponent(key)}=${encodeURIComponent(value)}`;
        const match = key.match(/^size_(\d+)_1$/);
        if (match) {
            formData += `&size_${match[1]}=${encodeURIComponent(value)}`;
        }
    }

    // Send add to cart request
    fetch('save_product.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Add to cart response:', data);
        if (data.success) {
            showNotification('Product added to cart!', 'success', 2000);
            // Close the reserve overlay
            closeReservePage();
            // Redirect to cart page after a short delay
            setTimeout(() => {
                window.location.href = 'save.php';
            }, 1500);
        } else {
            showNotification(data.message || 'Failed to add product to cart', 'error', 2000);
        }
    })
    .catch(error => {
        console.error('Error adding to cart:', error);
        showNotification('Error adding to cart', 'error', 2000);
    });
}

// Load products from admin inventory
function loadProducts() {
    const loadingIndicator = document.getElementById('loadingIndicator');
    const productsGrid = document.getElementById('productsGrid');
    const noProductsMessage = document.getElementById('noProductsMessage');
    const currentProductId = '<?php echo isset($_GET['id']) ? $_GET['id'] : 0; ?>';

    if (productsGrid) {
        productsGrid.innerHTML = '';
        productsGrid.style.display = 'grid';
    }

    fetch('admin/gowns.php?action=getAllGowns&hide_out_of_stock=1')
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {
                // Additional client-side filter: exclude out-of-stock products as safety measure
                const inStockData = data.filter(product => {
                    const stock = parseInt(product.stock || 0);
                    return stock > 0;
                });
                
                // Filter out the current product
                const filteredProducts = inStockData.filter(product => product.id != currentProductId);
                
                if (filteredProducts.length > 0) {
                    productsGrid.style.display = 'grid';
                    displayProducts(filteredProducts);
                } else {
                    noProductsMessage.style.display = 'block';
                }
            } else {
                noProductsMessage.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading products:', error);
            noProductsMessage.style.display = 'block';
        });
}

// Display products in grid
function displayProducts(products) {
    const productsGrid = document.getElementById('productsGrid');
    productsGrid.innerHTML = '';

    // Additional client-side filter: exclude out-of-stock products as safety measure
    const inStockProducts = products.filter(product => {
        const stock = parseInt(product.stock || 0);
        return stock > 0;
    });

    inStockProducts.forEach(product => {
        const productCard = createProductCard(product);
        productsGrid.appendChild(productCard);
    });
}

// Create product card element
function createProductCard(product) {
    const card = document.createElement('div');
    card.className = 'product-card';
    card.onclick = () => viewProduct(product.id);

    let imageContent = '';

    if (product.image_path) {
        // Display uploaded image first (fast, lightweight)
        imageContent = `
            <div class="product-image">
                <img src="${product.image_path}" alt="${product.name}" style="width: 100%; height: 100%; object-fit: cover; padding: 0;" loading="lazy">
                ${product.model_path ? `
                <button class="view-3d-btn" onclick="event.stopPropagation(); view3DModel('${product.model_path}')" title="View 3D Model">
                    <i class="fas fa-cube"></i>
                </button>
                ` : ''}
            </div>
        `;
    } else if (product.model_path) {
        // 3D Model viewer only when no image is available (with lazy loading)
        imageContent = `
            <div class="product-image">
                <model-viewer
                    src="${product.model_path}"
                    alt="${product.name}"
                    loading="lazy"
                    auto-rotate
                    camera-orbit="auto 90deg auto"
                    camera-controls
                    disable-pan
                    disable-zoom
                    min-camera-orbit="auto 90deg auto"
                    max-camera-orbit="auto 90deg auto">
                </model-viewer>
                <button class="view-3d-btn" onclick="event.stopPropagation(); view3DModel('${product.model_path}')">
                    <i class="fas fa-cube"></i>
                </button>
            </div>
        `;
    } else {
        // Placeholder
        imageContent = `
            <div class="product-image">
                <div class="product-placeholder">
                    <i class="fas fa-tshirt"></i>
                    <div>${product.name}</div>
                    <div style="font-size: 0.9rem; opacity: 0.7;">No Media</div>
                </div>
            </div>
        `;
    }

    const categoryUpper = (product.category || '').toUpperCase();
    const colorText = product.color ? ((product.color.includes(',') ? 'Colors: ' : 'Color: ') + product.color) : '';

    card.innerHTML = `
        ${imageContent}
        <div class="product-info">
            <div class="product-name">${product.name}</div>
            ${categoryUpper ? `<div class="product-category">${categoryUpper}</div>` : ''}
            ${colorText ? `<div class="product-color">${colorText}</div>` : ''}
            <div class="product-size" style="display: none;">${product.size || ''}</div>
        </div>
    `;

    return card;
}

// View product details
function viewProduct(productId) {
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'openProduct', productId: productId }, '*');
    } else {
        // Check if user is currently viewing a product and wants to go back
        const currentProductId = '<?php echo isset($_GET['id']) ? $_GET['id'] : 0; ?>';
        if (currentProductId > 0) {
            // Show reminder dialog
            showBackReminder(productId);
        } else {
            window.location.href = `index.php?id=${productId}`;
        }
    }
}

// Show back reminder dialog
function showBackReminder(newProductId) {
    // Create reminder modal
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    `;

    const dialog = document.createElement('div');
    dialog.style.cssText = `
        background: #2a2a2a;
        border: 2px solid #7c6300;
        border-radius: 12px;
        padding: 30px;
        max-width: 400px;
        width: 90%;
        text-align: center;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    `;

    dialog.innerHTML = `
        <h3 style="color: #ffd700; margin: 0 0 15px 0; font-size: 18px;">Reminder</h3>
        <p style="color: #fff; margin: 0 0 25px 0; font-size: 14px; line-height: 1.5;">You are currently viewing a product. Do you want to go back and view another product?</p>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button id="cancelBackBtn" style="background: #666; color: white; border: none; padding: 10px 25px; font-size: 14px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;">Cancel</button>
            <button id="confirmBackBtn" style="background: #7c6300; color: white; border: none; padding: 10px 25px; font-size: 14px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;">OK</button>
        </div>
    `;

    modal.appendChild(dialog);
    document.body.appendChild(modal);

    // Handle cancel button
    document.getElementById('cancelBackBtn').onclick = function() {
        document.body.removeChild(modal);
    };

    // Handle confirm button
    document.getElementById('confirmBackBtn').onclick = function() {
        document.body.removeChild(modal);
        window.location.href = `index.php?id=${newProductId}`;
    };

    // Close on background click
    modal.onclick = function(e) {
        if (e.target === modal) {
            document.body.removeChild(modal);
        }
    };
}

// View 3D model in full screen
function view3DModel(modelPath) {
    // Create modal for 3D view
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        z-index: 10000;
        display: flex;
        justify-content: center;
        align-items: center;
    `;

    const viewer = document.createElement('model-viewer');
    viewer.setAttribute('src', modelPath);
    viewer.setAttribute('bounds', 'tight');
    viewer.setAttribute('camera-controls', '');
    viewer.setAttribute('auto-rotate', '');
    viewer.setAttribute('rotation-per-second', '20deg');
    viewer.setAttribute('auto-rotate-delay', '1000');
    viewer.setAttribute('interpolation-decay', '100');
    viewer.setAttribute('disable-pan', '');
    viewer.setAttribute('disable-zoom', '');
    viewer.setAttribute('camera-orbit', 'auto 90deg 82%');
    viewer.setAttribute('min-camera-orbit', '-Infinity 90deg 82%');
    viewer.setAttribute('max-camera-orbit', 'Infinity 90deg 82%');
    viewer.setAttribute('touch-action', 'pan-y');
    viewer.style.cssText = `
        width: 80%;
        height: 80%;
        max-width: 800px;
        max-height: 600px;
    `;

    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.style.cssText = `
        position: absolute;
        top: 20px;
        right: 20px;
        background: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        cursor: pointer;
        font-size: 18px;
        z-index: 10001;
    `;

    closeBtn.onclick = () => document.body.removeChild(modal);
    modal.appendChild(viewer);
    modal.appendChild(closeBtn);
    document.body.appendChild(modal);
}

// Set active navigation
function setActiveNav(element) {
    document.querySelectorAll('.sidebar-icon, .nav-item').forEach(item => {
        item.classList.remove('active');
    });
    element.classList.add('active');
}

function goBack() {
    // If running inside the product overlay iframe, tell parent to close the overlay cleanly
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'closeProductOverlay' }, '*');
    } else {
        // Standalone product page: use browser history back if possible
        if (window.history.length > 1) {
            window.history.back();
        } else {
            // If no history, go to main page
            window.location.href = 'index.php';
        }
    }
}

// Top nav is always dark, no scroll-based transparency needed

// Custom calendar state and functions
let currentCalendarDate = new Date();
let activeDateInputId = null;
let unavailableDates = [];
let productStock = 0;
let rentalDurationDays = <?php echo intval($rentalDurationDays); ?> || 10;
let maxExtensionDays = <?php echo intval($maxExtensionDays); ?> || 7;
let extensionPricePerDay = <?php echo floatval($extensionPricePerDay); ?> || 100;
let overduePenaltyPercent = <?php echo intval($overduePenaltyPercent); ?> || 10;

// Fetch unavailable dates from server based on product reservations and stock
function fetchUnavailableDates(requestedType = null, requestedQty = null, callback = null) {
    const pid = '<?php echo $product_id; ?>';
    const pname = '<?php echo isset($product['name']) ? addslashes($product['name']) : ''; ?>';
    const pcat = '<?php echo isset($product['category']) ? addslashes($product['category']) : ''; ?>';
    if (!pid && !pname) return;

    const type = requestedType || window.currentOverlayType || 'reserve';
    const qty = requestedQty || parseInt(document.getElementById('totalQuantity')?.value) || 1;

    const url = `get_unavailable_dates.php?product_id=${encodeURIComponent(pid)}&product_name=${encodeURIComponent(pname)}&product_category=${encodeURIComponent(pcat)}&type=${encodeURIComponent(type)}&quantity=${encodeURIComponent(qty)}`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                unavailableDates = data.dates || [];
                productStock = data.stock || 0;

                // Sync real-time settings from calendar.php
                if (typeof data.rental_duration_days !== 'undefined') rentalDurationDays = parseInt(data.rental_duration_days);
                if (typeof data.max_extension_days !== 'undefined') maxExtensionDays = parseInt(data.max_extension_days);
                if (typeof data.extension_price_per_day !== 'undefined') extensionPricePerDay = parseFloat(data.extension_price_per_day);
                if (typeof data.overdue_penalty_percent !== 'undefined') {
                    overduePenaltyPercent = parseInt(data.overdue_penalty_percent);
                    const overdueEl = document.querySelector('.overdue-percent-btn');
                    if (overdueEl) overdueEl.textContent = overduePenaltyPercent + '%';
                }

                // If currently selected reserve/purchase date is in unavailableDates, clear it!
                const reserveInput = document.getElementById('reserveDate');
                if (reserveInput && reserveInput.value && unavailableDates.includes(reserveInput.value)) {
                    reserveInput.value = '';
                    updateReturnDateInputState();
                    if (document.getElementById('returnDate')) {
                        document.getElementById('returnDate').value = '';
                    }
                    showNotification('Selected date is unavailable for ' + (type === 'buy' ? 'purchase' : 'rental') + ' due to existing bookings. Please pick another date.', 'warning', 3500);
                }

                updateOverlayPricesRealtime();

                // If calendar modal is open, re-render it
                const overlay = document.getElementById('calendarModalOverlay');
                if (overlay && overlay.classList.contains('active')) {
                    renderCalendar();
                }

                if (typeof callback === 'function') {
                    callback(data);
                }
            }
        })
        .catch(error => console.error('Error fetching unavailable dates:', error));
}

// Update return date clickable and visual state
function updateReturnDateInputState() {
    const reserveDateVal = document.getElementById('reserveDate')?.value;
    const returnDateInput = document.getElementById('returnDate');
    if (!returnDateInput) return;

    // Check if type is selected
    const isBuy = window.currentOverlayType === 'buy';
    const isReserve = window.currentOverlayType === 'reserve';
    const noTypeSelected = window.currentOverlayType === null || window.currentOverlayType === undefined;

    if (noTypeSelected) {
        returnDateInput.classList.add('date-input-disabled');
        returnDateInput.style.cursor = 'not-allowed';
        returnDateInput.style.opacity = '0.5';
        returnDateInput.title = 'Please select Type first';
        return;
    }

    if (isBuy) {
        returnDateInput.classList.add('date-input-disabled');
        returnDateInput.style.cursor = 'not-allowed';
        returnDateInput.style.opacity = '0.5';
        returnDateInput.title = 'Return date not required for Buy type';
        return;
    }

    if (isReserve && !reserveDateVal) {
        returnDateInput.classList.add('date-input-disabled');
        returnDateInput.style.cursor = 'not-allowed';
        returnDateInput.style.opacity = '0.5';
        returnDateInput.title = 'Please select a Reserve Date first';
        return;
    }

    returnDateInput.classList.remove('date-input-disabled');
    returnDateInput.style.cursor = 'pointer';
    returnDateInput.style.opacity = '1';
    returnDateInput.title = 'Select Return Date';
}

// Update reserve date clickable and visual state
function updateReserveDateInputState() {
    const reserveDateInput = document.getElementById('reserveDate');
    if (!reserveDateInput) return;

    const noTypeSelected = window.currentOverlayType === null || window.currentOverlayType === undefined;

    if (noTypeSelected) {
        reserveDateInput.classList.add('date-input-disabled');
        reserveDateInput.style.cursor = 'not-allowed';
        reserveDateInput.style.opacity = '0.5';
        reserveDateInput.title = 'Please select Type first';
    } else {
        reserveDateInput.classList.remove('date-input-disabled');
        reserveDateInput.style.cursor = 'pointer';
        reserveDateInput.style.opacity = '1';
        reserveDateInput.title = 'Select date';
    }
}

// Fetch on page load
fetchUnavailableDates();
updateReserveDateInputState();
updateReturnDateInputState();

function openCalendarModal(inputId) {
    // Check if type is selected first
    const typeSelected = window.currentOverlayType !== null && window.currentOverlayType !== undefined;
    if (!typeSelected) {
        showNotification('Please select Type (Reserve or Buy) first', 'warning', 2000);
        return;
    }

    // For return date, also check if reserve date is selected
    if (inputId === 'returnDate') {
        const currentReserveVal = document.getElementById('reserveDate')?.value;
        if (!currentReserveVal) {
            showNotification('Please select a Reserve Date first.', 'warning', 2500);
            return;
        }
    }
    activeDateInputId = inputId;
    if (inputId === 'returnDate') {
        const reserveDate = document.getElementById('reserveDate')?.value;
        if (reserveDate) {
            currentCalendarDate = new Date(reserveDate + 'T00:00:00');
        } else {
            currentCalendarDate = new Date();
        }
    } else {
        const reserveDate = document.getElementById('reserveDate')?.value;
        if (reserveDate) {
            currentCalendarDate = new Date(reserveDate + 'T00:00:00');
        } else {
            currentCalendarDate = new Date();
        }
    }
    const overlay = document.getElementById('calendarModalOverlay');
    overlay.classList.add('active');
    renderCalendar();

    // Re-verify availability on modal open
    fetchUnavailableDates(window.currentOverlayType || 'reserve', null, () => {
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
    const requestedDuration = rentalDurationDays;
    const extFeePerDay = extensionPricePerDay;
    const maxExtDays = maxExtensionDays;

    let minDate = today;
    let maxDate = null;
    let standardReturnDate = null;
    let firstBookedAfterReserve = null;

    if (activeDateInputId === 'returnDate') {
        if (reserveDateVal) {
            minDate = reserveDateVal; // cannot be earlier than reserve date
            const standardDays = requestedDuration;
            const stdReturnObj = new Date(reserveDateVal + 'T00:00:00');
            stdReturnObj.setDate(stdReturnObj.getDate() + standardDays);
            standardReturnDate = formatLocalDate(stdReturnObj);

            const maxReturnObj = new Date(reserveDateVal + 'T00:00:00');
            maxReturnObj.setDate(maxReturnObj.getDate() + standardDays + maxExtDays);
            maxDate = formatLocalDate(maxReturnObj); // cannot exceed standard duration + max extension!

            // Find earliest booked date on or after reserveDateVal so user can't select on/past it
            for (let d = 1; d <= (standardDays + maxExtDays + 30); d++) {
                const cObj = new Date(reserveDateVal + 'T00:00:00');
                cObj.setDate(cObj.getDate() + d);
                const cStr = formatLocalDate(cObj);
                if (unavailableDates.includes(cStr)) {
                    firstBookedAfterReserve = cStr;
                    break;
                }
            }
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
                        <span>Select Return Date (Standard: <strong>${requestedDuration} Days</strong>${maxExtDays > 0 ? ` &bull; Max +${maxExtDays} Days Ext` : ''})</span>
                    </div>
                    ${isCurrentlyExtended ? `<button type="button" class="btn-reset-standard" onclick="resetReturnDateToStandard()" style="background: rgba(255, 215, 0, 0.2); border: 1px solid #ffd700; color: #ffd700; border-radius: 4px; padding: 2px 8px; font-size: 0.75rem; cursor: pointer; font-weight: 600;">Reset to Standard (${requestedDuration} Days)</button>` : ''}
                </div>
                <div class="calendar-guide-msg">
                    The <span class="guide-badge-inline red-gap" style="background: rgba(220, 53, 69, 0.25); color: #ff8888; border: 1px solid rgba(220, 53, 69, 0.6); padding: 1px 5px; border-radius: 3px; font-weight: 600;">Red Highlight</span> marks your standard rental duration (<strong>${reserveFormatted}</strong> to <strong>${stdReturnFormatted}</strong>, not clickable).
                    ${maxExtDays > 0 ? `Dates after standard rental (up to <strong>${maxReturnFormatted}</strong>) can be selected to extend your rental for an additional <strong>₱${extFeePerDay.toLocaleString()}/day</strong>.` : ''}
                </div>
                <div class="calendar-guide-legend">
                    <span class="legend-chip red-gap"><span class="chip-box" style="background: rgba(220, 53, 69, 0.4); border: 1px solid #ff4444;"></span> Red Highlight = Standard Rental (Same date to ${requestedDuration} Days After - Not Clickable)</span>
                    ${maxExtDays > 0 ? `<span class="legend-chip extension"><span class="chip-box" style="background: rgba(56, 189, 248, 0.35); border: 1px dashed #38bdf8;"></span> Blue Dashed = Extension (+₱${extFeePerDay}/day)</span>` : ''}
                    <span class="legend-chip booked"><span class="chip-box"></span> Red Strikethrough = Booked / Unavailable (Can't Click)</span>
                </div>
            `;
        } else {
            guideContainer.innerHTML = `
                <div class="calendar-guide-header">
                    <i class="fas fa-calendar-alt" style="color: #ffd700;"></i>
                    <span>Select ${window.currentOverlayType === 'buy' ? 'Purchase' : 'Reserve'} Date</span>
                </div>
                <div class="calendar-guide-msg">
                    ${window.currentOverlayType === 'buy' ? 'Pick your desired purchase / pickup date. Dates with existing reservations cannot be purchased.' : `Pick your pickup date. Your Return Date will automatically be set <strong>${requestedDuration} days forward</strong> based on your standard rental duration.`}
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

        // Standard rental duration highlighting: Red color and NOT clickable
        let isStandardRentalGap = false;
        if (activeDateInputId === 'returnDate' && reserveDateVal && standardReturnDate && !isBooked && !isBeforeMin) {
            if (dateStr >= reserveDateVal && dateStr <= standardReturnDate) {
                isStandardRentalGap = true;
                dayElem.classList.add('rental-gap');
                dayElem.title = `Standard Rental (Same date to ${requestedDuration} Days After) - Not Clickable`;
            } else if (maxDate && dateStr > standardReturnDate && dateStr <= maxDate) {
                dayElem.classList.add('extension-day');
                dayElem.title = `Rental Extension (+₱${extFeePerDay}/day)`;
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
                dayElem.title = (window.currentOverlayType === 'buy') ? 'Reserved / Unavailable for Purchase (Cannot Select)' : 'Fully Booked / Unavailable (Cannot Select)';
            } else if (isStandardRentalGap) {
                dayElem.title = `Standard Rental (Same date to ${requestedDuration} Days After) - Not Clickable`;
            } else if (isExceedsMax) {
                dayElem.classList.add('exceeds-duration');
                dayElem.title = `Exceeds maximum allowed rental period`;
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
    const standardDays = rentalDurationDays;
    const stdReturnObj = new Date(reserveDateVal + 'T00:00:00');
    stdReturnObj.setDate(stdReturnObj.getDate() + standardDays);
    const standardReturnDate = formatLocalDate(stdReturnObj);
    document.getElementById('returnDate').value = standardReturnDate;
    updateAvailableStock();
    updateOverlayPricesRealtime();
    renderCalendar();
    showNotification(`Return Date reset to standard rental duration (${standardDays} days).`, 'info', 2500);
}

// Handle Reserve Date selection with smart availability detection
function handleReserveDateSelection(dateStr) {
    if (unavailableDates.includes(dateStr)) {
        showNotification(window.currentOverlayType === 'buy' ? 'This date cannot be selected for purchase due to upcoming reservations.' : 'This date is already booked.', 'error', 2500);
        return;
    }

    if (window.currentOverlayType === 'buy') {
        document.getElementById('reserveDate').value = dateStr;
        document.getElementById('calendarModalOverlay').classList.remove('active');
        activeDateInputId = null;
        updateOverlayPricesRealtime();
        updateQuantityInteractiveState();
        return;
    }

    const standardDays = rentalDurationDays;
    
    // Check consecutive availability from dateStr up to standardDays after
    let maxAvailableDays = 0;
    let firstConflictDate = null;
    
    for (let d = 1; d <= standardDays; d++) {
        const checkObj = new Date(dateStr + 'T00:00:00');
        checkObj.setDate(checkObj.getDate() + d);
        const checkStr = formatLocalDate(checkObj);
        
        if (unavailableDates.includes(checkStr)) {
            firstConflictDate = checkStr;
            break;
        }
        maxAvailableDays = d;
    }
    
    if (maxAvailableDays >= standardDays) {
        // Full requested duration based on calendar.php setting is available!
        document.getElementById('reserveDate').value = dateStr;
        const reserveObj = new Date(dateStr + 'T00:00:00');
        reserveObj.setDate(reserveObj.getDate() + standardDays);
        const autoReturnDateStr = formatLocalDate(reserveObj);
        
        const returnDateInput = document.getElementById('returnDate');
        if (returnDateInput) {
            returnDateInput.value = autoReturnDateStr;
        }
        
        updateReturnDateInputState();
        updateAvailableStock();
        updateOverlayPricesRealtime();
        updateQuantityInteractiveState();
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
        updateQuantityInteractiveState();
        document.getElementById('calendarModalOverlay').classList.remove('active');
        activeDateInputId = null;

        if (firstConflictDate) {
            showNotification(`Return Date automatically set to ${formatDateNice(maxReturnStr)} because ${formatDateNice(firstConflictDate)} is booked.`, 'info', 3500);
        }
    }
}

// Handle Return Date selection with conflict checking
function handleReturnDateSelection(dateStr) {
    const reserveDateStr = document.getElementById('reserveDate')?.value;
    
    // Strict safety check: If date is booked, DO NOT set!
    if (!dateStr || unavailableDates.includes(dateStr)) {
        showNotification('This date is fully booked and cannot be selected as Return Date.', 'error', 3000);
        return;
    }
    
    if (reserveDateStr) {
        if (dateStr < reserveDateStr) {
            showNotification('Return Date cannot be earlier than Reserve Date.', 'error', 3000);
            return;
        }

        const stdDays = rentalDurationDays;
        const stdReturnObj = new Date(reserveDateStr + 'T00:00:00');
        stdReturnObj.setDate(stdReturnObj.getDate() + stdDays);
        const standardReturnDate = formatLocalDate(stdReturnObj);
        if (dateStr >= reserveDateStr && dateStr <= standardReturnDate) {
            showNotification(`Standard rental period (Same date to ${stdDays} days after) cannot be selected as Return Date.`, 'warning', 3000);
            return;
        }
        
        const reserveObj = new Date(reserveDateStr + 'T00:00:00');
        const returnObj = new Date(dateStr + 'T00:00:00');
        const diffDays = Math.round((returnObj - reserveObj) / (1000 * 60 * 60 * 24));
        
        for (let d = 1; d <= diffDays; d++) {
            const checkObj = new Date(reserveDateStr + 'T00:00:00');
            checkObj.setDate(checkObj.getDate() + d);
            const checkStr = formatLocalDate(checkObj);
            
            if (unavailableDates.includes(checkStr)) {
                showNotification(`Cannot select ${formatDateNice(dateStr)} because ${formatDateNice(checkStr)} is fully booked.`, 'error', 3500);
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

// Real-time overlay price recalculation for reserve and buy actions
function updateOverlayPricesRealtime() {
    const isBuy = (window.currentOverlayType === 'buy');
    const priceOverlay = document.querySelector('.product-price-overlay');
    const purchasePriceOverlay = document.querySelector('.product-purchase-price-overlay');
    if (!priceOverlay) return;

    const baseRentalPrice = <?php echo floatval($discountedPrice); ?>;
    const origRentalPrice = <?php echo floatval($originalPrice); ?>;
    const hasDiscount = <?php echo ($discount > 0) ? 'true' : 'false'; ?>;
    const purchasePrice = <?php echo floatval(isset($product['purchase_price']) ? $product['purchase_price'] : 0); ?>;
    
    const qty = parseInt(document.getElementById('totalQuantity')?.value) || 1;
    const reserveDateVal = document.getElementById('reserveDate')?.value;
    const returnDateVal = document.getElementById('returnDate')?.value;
    
    const stdDuration = rentalDurationDays;
    const extFeePerDay = extensionPricePerDay;
    
    let extraDays = 0;
    let extraFeePerUnit = 0;
    
    if (reserveDateVal && returnDateVal && !isBuy) {
        const rObj = new Date(reserveDateVal + 'T00:00:00');
        const retObj = new Date(returnDateVal + 'T00:00:00');
        const diffDays = Math.round((retObj - rObj) / (1000 * 60 * 60 * 24));
        if (diffDays > stdDuration) {
            extraDays = diffDays - stdDuration;
            extraFeePerUnit = extraDays * extFeePerDay;
        }
    }
    
    window.currentExtraFeePerUnit = extraFeePerUnit;
    const effectiveUnitRental = baseRentalPrice + extraFeePerUnit;
    const totalRental = effectiveUnitRental * qty;
    
    if (isBuy) {
        priceOverlay.style.opacity = '0.45';
        if (hasDiscount) {
            priceOverlay.innerHTML = `<span style="color: gray; font-size: 0.8rem;">Rental Price:</span> <span style="color: #ff4444; font-weight: bold;">₱${(baseRentalPrice * qty).toLocaleString()}</span>`;
        } else {
            priceOverlay.innerHTML = `<span style="color: gray; font-size: 0.8rem;">Rental Price:</span> <span style="color: #ff4444; font-weight: bold;">₱${(baseRentalPrice * qty).toLocaleString()}</span>`;
        }
        if (purchasePriceOverlay) {
            purchasePriceOverlay.style.opacity = '1';
            purchasePriceOverlay.innerHTML = `Selling Price: <span style="color: red; font-weight: bold;">₱${(purchasePrice * qty).toLocaleString()}</span>`;
        }
    } else {
        priceOverlay.style.opacity = '1';
        if (hasDiscount && extraDays === 0) {
            priceOverlay.innerHTML = `<span style="color: gray; font-size: 0.8rem;">Rental Price:</span> <span style="color: #ff4444; font-weight: bold;">₱${totalRental.toLocaleString()}</span>`;
        } else {
            priceOverlay.innerHTML = `<span style="color: gray; font-size: 0.8rem;">Rental Price:</span> <span style="color: #ff4444; font-weight: bold;">₱${totalRental.toLocaleString()}</span>`;
        }
        if (purchasePriceOverlay) {
            purchasePriceOverlay.style.opacity = '0.45';
        }
    }
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
        updateReturnDateInputState();
        updateAvailableStock();
        updateOverlayPricesRealtime();
        updateQuantityInteractiveState();
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
    const pid = '<?php echo $product_id; ?>';

    if (!reserveDate || !returnDate || !pid) return;

    fetch('get_available_stock.php?product_id=' + pid + '&reserve_date=' + reserveDate + '&return_date=' + returnDate)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const available = data.available;
                window.currentStockQuantity = available;

                const totalQuantityInput = document.getElementById('totalQuantity');
                if (totalQuantityInput) {
                    totalQuantityInput.max = available;
                    // If current value exceeds available, reduce it
                    if (parseInt(totalQuantityInput.value) > available) {
                        totalQuantityInput.value = available;
                    }
                }

                // Update + button state
                const plusBtn = document.querySelector('.quantity-plus');
                const minusBtn = document.querySelector('.quantity-minus');
                const currentVal = totalQuantityInput ? parseInt(totalQuantityInput.value) : 1;

                if (currentVal >= available) {
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

                if (currentVal > 0) {
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

// ===== Load Product Reviews =====
function loadProductReviews() {
    const productId = <?php echo $product_id; ?>;
    const container = document.getElementById('reviewsContainer');

    fetch('purchase.php?action=get_product_reviews&product_id=' + productId)
        .then(response => response.json())
        .then(data => {
            if (!data.success || data.reviews.length === 0) {
                container.innerHTML = `
                    <div class="reviews-empty">
                        <i class="fas fa-comment-slash"></i>
                        <p>No reviews yet. Be the first to review this product!</p>
                    </div>
                `;
                return;
            }

            const avg = data.avg_rating;
            const total = data.total_reviews;
            const fullStars = Math.floor(avg);
            const hasHalf = avg % 1 >= 0.5;
            let avgStarsHtml = '';
            for (let i = 0; i < 5; i++) {
                if (i < fullStars) avgStarsHtml += '&#9733;';
                else if (i === fullStars && hasHalf) avgStarsHtml += '&#9733;';
                else avgStarsHtml += '<span style="color:#444">&#9733;</span>';
            }

            // Populate product rating row (desktop)
            const ratingRow = document.getElementById('productRatingRow');
            if (ratingRow) {
                document.getElementById('productRatingValue').textContent = avg.toFixed(1);
                document.getElementById('productStarIcons').innerHTML = avgStarsHtml;
                document.getElementById('productTotalRates').textContent = total + ' Rating' + (total !== 1 ? 's' : '');
                ratingRow.style.display = 'flex';
            }

            let html = `
                <div class="reviews-summary">
                    <div class="avg-rating">${avg.toFixed(1)}</div>
                    <div>
                        <div class="avg-stars">${avgStarsHtml}</div>
                        <div class="review-count">${total} review${total !== 1 ? 's' : ''}</div>
                    </div>
                </div>
                <div class="reviews-list">
            `;

            data.reviews.forEach(function(review) {
                let starsHtml = '';
                for (let i = 0; i < 5; i++) {
                    starsHtml += i < review.rating ? '&#9733;' : '<span style="color:#444">&#9733;</span>';
                }

                let photosHtml = '';
                if (review.photo1) {
                    photosHtml += `<img class="review-photo" src="${review.photo1}" alt="Review photo" onclick="openFullscreenImage('${review.photo1}', 'Review Photo')">`;
                }
                if (review.photo2) {
                    photosHtml += `<img class="review-photo" src="${review.photo2}" alt="Review photo" onclick="openFullscreenImage('${review.photo2}', 'Review Photo')">`;
                }

                const dateStr = review.created_at ? new Date(review.created_at).toLocaleDateString('en-US', {year:'numeric', month:'short', day:'numeric'}) : '';

                // Abbreviate username for privacy
                function abbreviateName(fullName) {
                    if (!fullName) return 'User';
                    const parts = fullName.trim().split(' ');
                    if (parts.length === 1) {
                        const name = parts[0];
                        return name.length > 3 ? name.substring(0, 3) + '...' : name;
                    }
                    const firstName = parts[0];
                    const lastName = parts[parts.length - 1];
                    const firstAbbr = firstName.length > 3 ? firstName.substring(0, 3) + '...' : firstName;
                    const lastAbbr = lastName.length > 4 ? lastName.substring(0, 4) + '...' : lastName;
                    return `${firstAbbr} ${lastAbbr}`;
                }

                // Display profile image if available, otherwise show initials
                let avatarHtml = '';
                if (review.profile_picture) {
                    avatarHtml = `<img src="${review.profile_picture}" alt="${review.initials}" class="review-avatar-img">`;
                } else {
                    avatarHtml = `<div class="review-avatar">${review.initials}</div>`;
                }

                html += `
                    <div class="review-card">
                        <div class="review-card-header">
                            <div class="review-card-user">
                                ${avatarHtml}
                                <div>
                                    <div class="review-username">${abbreviateName(review.username)}</div>
                                    <div class="review-date">${dateStr}</div>
                                </div>
                            </div>
                        </div>
                        <div class="review-stars">${starsHtml}</div>
                        ${review.review_text ? `<div class="review-text">${review.review_text}</div>` : ''}
                        ${photosHtml ? `<div class="review-photos">${photosHtml}</div>` : ''}
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading reviews:', error);
            container.innerHTML = `
                <div class="reviews-empty">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Failed to load reviews.</p>
                </div>
            `;
        });
}

// Load reviews asynchronously so initial product details paint instantly
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(loadProductReviews, 250);
});

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

window.addEventListener('DOMContentLoaded', function() {
    updateCartCount();
    fetchPurchaseBadgeCount();
    setInterval(updateCartCount, 10000);
    setInterval(fetchPurchaseBadgeCount, 10000);
});

window.addEventListener('pageshow', function() {
    updateCartCount();
    fetchPurchaseBadgeCount();
});
</script>

<!-- Top Custom Notification / Confirmation Card (Matches index.php Clear Chat Confirmation Card) -->
<div id="notificationToast" class="clear-chat-confirm-modal">
    <div class="clear-chat-confirm-card success-card" id="notificationToastCard">
        <div class="clear-chat-confirm-icon" id="notificationIcon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="clear-chat-confirm-body">
            <div class="clear-chat-confirm-title" id="notificationTitle">Success</div>
            <div class="clear-chat-confirm-desc" id="notificationMessage">Your action completed successfully.</div>
        </div>
    </div>
</div>

<script>
// When running inside the product overlay iframe, intercept Home/Back links
// so they tell the parent to close the overlay instead of navigating inside iframe.
(function() {
    if (window.parent === window) return; // not in an iframe, skip

    document.addEventListener('click', function(e) {
        var el = e.target.closest('a');
        if (!el) return;
        var href = el.getAttribute('href') || '';
        // Intercept links going to index.php (without ?id=) — these are "back to home" links
        if (href === 'index.php' || href === './' || href === '' || (href.startsWith('index.php') && !href.includes('id='))) {
            e.preventDefault();
            window.parent.postMessage({ type: 'closeProductOverlay' }, '*');
        }
    });
})();
</script>

</body>
</html>

<?php
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Home - ClosetGo</title>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/homepage.css?v=<?php echo @filemtime('css/homepage.css'); ?>">
    <link rel="stylesheet" href="css/skeleton.css?v=<?php echo @filemtime('css/skeleton.css'); ?>">
    <link id="dynamicFavicon" rel="icon" href="images/icon/JMB.png" type="image/jpeg">
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.1.1/model-viewer.min.js"></script>
    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.14/dist/dotlottie-wc.js" type="module"></script>
    <style>
        @media (max-width: 768px) {
            .top-bar .cart-container,
            .mobile-top-icons .cart-container,
            .mobile-top-icons .purchase-top-container,
            .cart-container {
                display: none !important;
                width: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Mobile top bar icons: search, notification, message closer with identical gaps */
            .mobile-top-icons {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                margin-left: auto !important;
                margin-right: 2px !important;
            }

            .mobile-top-icons .mobile-top-search,
            .mobile-top-icons .notification-container,
            .mobile-top-icons .chat-top-container {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                margin: 0 !important;
                padding: 0 !important;
                position: relative !important;
                flex-shrink: 0 !important;
            }

            .mobile-top-icons .mobile-top-search {
                opacity: 1 !important;
                visibility: visible !important;
                pointer-events: auto !important;
                transform: none !important;
            }

            .mobile-top-icons .mobile-top-search img,
            .mobile-top-icons .notification-container img,
            .mobile-top-icons .topbar-notif-icon,
            .mobile-top-icons .chat-top-container img {
                width: 23px !important;
                height: 23px !important;
                object-fit: contain !important;
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .mobile-top-icons .notification-nav-badge {
                top: -5px !important;
                right: -3px !important;
            }

            .mobile-top-icons #userChatBadge {
                top: -5px !important;
                right: -3px !important;
            }
        }
    </style>
</head>
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'database/db_config.php';
$db = new Database();
$conn = $db->getConnection();

// Check if user is logged in
$isLoggedIn = false;
$currentUser = null;
$displayName = '';
$userProfileImage = 'images/icon/profile_logo.png';

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
$userEmail = isset($_SESSION['email']) ? $_SESSION['email'] : (isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '');

if ($userId > 0 || !empty($userEmail)) {
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
}

// Fetch skin tone colors from database
$skinToneColors = [];
$skinToneResult = $conn->query("SELECT skin_tone_colors FROM ai_skinbased LIMIT 1");
if ($skinToneResult && $row = $skinToneResult->fetch_assoc()) {
    $skinToneColors = json_decode($row['skin_tone_colors'], true);
}

// Fetch 3 gowns for product display (exclude out-of-stock)
$gownsResult = $conn->query("SELECT id, name, image_path FROM gowns WHERE stock > 0 LIMIT 3");
$gowns = [];
if ($gownsResult->num_rows > 0) {
    while ($row = $gownsResult->fetch_assoc()) {
        $gowns[] = $row;
    }
}

// Fetch active banners for homepage carousel
$banners = [];
$bannersResult = $conn->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
if ($bannersResult && $bannersResult->num_rows > 0) {
    while ($bRow = $bannersResult->fetch_assoc()) {
        $banners[] = $bRow;
    }
}
if (empty($banners)) {
    $banners = [
        ['id' => 1, 'title' => 'ClosetGo Promo 1', 'image_path' => 'images/background/1.jpg', 'link_url' => ''],
        ['id' => 2, 'title' => 'ClosetGo Promo 2', 'image_path' => 'images/background/2.jpg', 'link_url' => ''],
        ['id' => 3, 'title' => 'ClosetGo Promo 3', 'image_path' => 'images/background/3.jpg', 'link_url' => ''],
        ['id' => 4, 'title' => 'ClosetGo Promo 4', 'image_path' => 'images/background/4.jpg', 'link_url' => '']
    ];
}

// Fetch system/app settings (footer, contact, gcash, social links, etc.)
$appSettings = [];
$settingsRes = $conn->query("SELECT setting_key, setting_value FROM app_settings");
if ($settingsRes) {
    while ($sRow = $settingsRes->fetch_assoc()) {
        $appSettings[$sRow['setting_key']] = $sRow['setting_value'];
    }
}

$siteName = !empty($appSettings['site_name']) ? $appSettings['site_name'] : 'ClosetGo';

// Column 1: About
$footerAboutTitle = $appSettings['footer_about_title'] ?? 'ABOUT CLOSETGOO';
$footerAboutLink = $appSettings['footer_about_link'] ?? 'javascript:void(0)';
$footerTermsLink = $appSettings['footer_terms_link'] ?? 'login_user/terms.php';
$footerPrivacyLink = $appSettings['footer_privacy_link'] ?? 'login_user/privacy.php';

// Column 2: Contact
$footerContactTitle = $appSettings['footer_contact_title'] ?? 'CONTACT US';
$footerAddress = $appSettings['site_address'] ?? 'Alaminos City, Pangasinan';
$footerEmail = $appSettings['site_email'] ?? 'jmbmanagementservices18@gmail.com';
$footerPhone = $appSettings['site_phone'] ?? '0998 868 9518';

// Column 3: Social Links
$footerSocialTitle = $appSettings['footer_social_title'] ?? 'FOLLOW US';
$footerFacebook = $appSettings['footer_facebook'] ?? 'https://www.facebook.com/ACV.alf?mibextid=ZbWKwL';
$footerInstagram = $appSettings['footer_instagram'] ?? 'https://www.instagram.com/iam_alff?igsh=YWdjcmQ2MmJ0OXkw';
?>
<body class="homepage">
<!-- Desktop Sidebar -->
<div class="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <a href="index.php" class="logo-link">
            <img src="img/ClosetGo-logo.png" alt="ClosetGo Logo" class="logo-image">
        </a>
    </div>
    
    <a href="index.php" class="sidebar-icon active">
        <i class="fas fa-home"></i>
        <span class="sidebar-text">Home</span>
    </a>
</div>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-bottom-nav has-straight-nav" id="mobileBottomNav" aria-label="Mobile Navigation">
    <!-- Straight Background Bar -->
    <div class="mobile-nav-bg-container straight">
        <div class="mobile-nav-wing"></div>
    </div>

    <a href="index.php" class="nav-item active" onclick="setActiveNav(this)">
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
    <a href="purchase.php" class="nav-item" onclick="setActiveNav(this)">
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

<!-- White overlay for Lottie animation -->
<div id="whiteOverlay">
    <!-- Top-Left Custom Confirmation Modal for Clear Chat -->
    <div id="clearChatConfirmModal" class="clear-chat-confirm-modal">
        <div class="clear-chat-confirm-card">
            <div class="clear-chat-confirm-icon">
                <i class="fas fa-trash-alt"></i>
            </div>
            <div class="clear-chat-confirm-body">
                <div class="clear-chat-confirm-title">Clear conversation history?</div>
                <div class="clear-chat-confirm-desc">Are you sure you want to clear all conversation history?</div>
                <div class="clear-chat-confirm-actions">
                    <button type="button" class="clear-chat-btn-cancel" onclick="closeClearChatConfirmModal()">Cancel</button>
                    <button type="button" class="clear-chat-btn-delete" onclick="executeClearAllAiConversations()">Clear All</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Laptop close button -->
    <img src="images/icon/close.png" alt="Close" class="laptop-overlay-close" onclick="closeWhiteOverlay()" style="display: none;">
    <!-- Fixed Clear Chat button (pinned at top, does not scroll) -->
    <button type="button" id="clearAiChatBtn" class="closetgo-ai-clear-btn" onclick="promptClearAllAiConversations()" title="Delete all conversations">
        <i class="fas fa-trash-alt closetgo-ai-clear-icon"></i> <span>Clear</span>
    </button>
    <div class="white-overlay-content">
        <div class="closetgo-ai-header">
            <div class="closetgo-ai-back" onclick="closeWhiteOverlay()">
                <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.14/dist/dotlottie-wc.js" type="module"></script>
                <dotlottie-wc src="https://lottie.host/dcf656ae-329b-4587-b8b8-eac3f7241466/7AXNi5II7n.lottie" style="width: 300px;height: 300px" autoplay loop></dotlottie-wc>
            </div>
            <div class="closetgo-ai-title">Aigo</div>
            <img src="images/icon/check.png" alt="Check" class="closetgo-ai-check">
        </div>
        <div class="closetgo-ai-disclaimer">Product recommendations are generated by AI and may not always be accurate or suitable.</div>
        <div id="aiGreeting"></div>

        <!-- Question boxes carousel (initial position at top) -->
        <div id="questionCarouselTop" class="question-carousel">
            <div class="question-box" onclick="selectQuestion(this)">
                <div>Can you suggest products based on my skin?</div>
            </div>
            <div class="question-box" onclick="selectQuestion(this)">
                <div>What's your budget range?</div>
            </div>
            <div class="question-box" onclick="selectQuestion(this)">
                <div>What size do you need?</div>
            </div>
        </div>
    </div>

    <!-- Chat display area -->
    <div id="chatDisplay">
        <!-- Conversation History (all past conversations appended here) -->
        <div id="conversationHistory"></div>

        <div id="userChatContainer" class="chat-message-container" style="display: none;">
            <div id="userMessage" class="user-message"></div>
            <div class="chat-user-profile">
                <img src="images/icon/profile.png" alt="User">
            </div>
        </div>
        <div id="aiReply" class="ai-reply-container" style="display: none;">
            <div class="ai-profile">
                <img src="images/background/Ai_profle.png" alt="AI">
            </div>
            <div class="ai-message">Select your skin type from the options below.</div>
        </div>
        <div id="aiTyping" class="ai-reply-container" style="display: none;">
            <div class="ai-profile">
                <img src="images/background/Ai_profle.png" alt="AI">
            </div>
            <div class="ai-message typing"></div>
        </div>
        <div id="skinToneOptions" class="skin-tone-options" style="display: none;">
            <div class="skin-tone-circle" style="background: #ffdbac;" data-skin-tone="light" onclick="selectSkinTone(this)"></div>
            <div class="skin-tone-circle" style="background: #f1c27d;" data-skin-tone="medium-light" onclick="selectSkinTone(this)"></div>
            <div class="skin-tone-circle" style="background: #e0ac69;" data-skin-tone="medium" onclick="selectSkinTone(this)"></div>
            <div class="skin-tone-circle" style="background: #c68642;" data-skin-tone="medium-dark" onclick="selectSkinTone(this)"></div>
            <div class="skin-tone-circle" style="background: #8d5524;" data-skin-tone="dark" onclick="selectSkinTone(this)"></div>
        </div>

        <!-- Budget Range Options -->
        <div id="budgetOptions" style="display: none; margin-top: 15px;">
            <div style="color: #ffd700; font-size: 14px; font-weight: 600; margin-bottom: 12px;">Choose your purpose:</div>
            <div style="display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap;">
                <div class="budget-type-btn" onclick="showBudgetRanges('buy')" style="padding: 10px 20px; background: linear-gradient(135deg, #7c6300, #988726); color: #fff; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                    <i class="fas fa-shopping-bag"></i> Buy
                </div>
                <div class="budget-type-btn" onclick="showBudgetRanges('reserve')" style="padding: 10px 20px; background: linear-gradient(135deg, #2a2a2a, #1a1a1a); color: #ffd700; border: 1px solid #7c6300; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                    <i class="fas fa-calendar-check"></i> Reserve
                </div>
            </div>
            <div id="budgetRangeList" style="display: flex; flex-direction: column; gap: 8px;"></div>
        </div>

        <!-- Budget user chat message -->
        <div id="budgetUserChat" class="chat-message-container" style="display: none;">
            <div id="budgetUserMessage" class="user-message"></div>
            <div class="chat-user-profile">
                <img src="images/icon/profile.png" alt="User">
            </div>
        </div>

        <!-- Size Input Options -->
        <div id="sizeOptions" style="display: none; margin-top: 15px;">
            <div style="color: #ffd700; font-size: 14px; font-weight: 600; margin-bottom: 12px;">Please enter your measurements:</div>
            <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 14px;">
                <div style="flex: 1; min-width: 120px;">
                    <label style="color: #aaa; font-size: 12px; display: block; margin-bottom: 4px;"><i class="fas fa-ruler-vertical"></i> Height (cm)</label>
                    <input type="number" id="sizeHeightInput" placeholder="e.g. 170" min="100" max="250" style="width: 100%; padding: 10px 12px; background: #1a1a1a; border: 1px solid #333; border-radius: 8px; color: #fff; font-size: 14px; box-sizing: border-box;">
                </div>
                <div style="flex: 1; min-width: 120px;">
                    <label style="color: #aaa; font-size: 12px; display: block; margin-bottom: 4px;"><i class="fas fa-weight-hanging"></i> Weight (kg)</label>
                    <input type="number" id="sizeWeightInput" placeholder="e.g. 65" min="30" max="200" style="width: 100%; padding: 10px 12px; background: #1a1a1a; border: 1px solid #333; border-radius: 8px; color: #fff; font-size: 14px; box-sizing: border-box;">
                </div>
            </div>
            <div class="budget-type-btn" onclick="submitSizeMeasurements()" style="padding: 10px 24px; background: linear-gradient(135deg, #7c6300, #988726); color: #fff; border-radius: 20px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 8px;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                <i class="fas fa-check"></i> Find My Size
            </div>
        </div>

        <!-- Size user chat message -->
        <div id="sizeUserChat" class="chat-message-container" style="display: none;">
            <div id="sizeUserMessage" class="user-message"></div>
            <div class="chat-user-profile">
                <img src="images/icon/profile.png" alt="User">
            </div>
        </div>

        <div id="skinToneUserChat" class="chat-message-container" style="display: none;">
            <div id="skinToneUserMessage" class="user-message"></div>
            <div class="chat-user-profile">
                <img src="images/icon/profile.png" alt="User">
            </div>
        </div>
        <div id="productAiReply" class="ai-reply-container" style="display: none;">
            <div class="ai-profile">
                <img src="images/background/Ai_profle.png" alt="AI">
            </div>
            <div class="ai-message">Great! Based on your skin type, here are some outfit recommendations.</div>
        </div>
        <div id="productAiTyping" class="ai-reply-container" style="display: none;">
            <div class="ai-profile">
                <img src="images/background/Ai_profle.png" alt="AI">
            </div>
            <div class="ai-message typing"></div>
        </div>
        <div id="productDisplay" class="product-display" style="display: none;"></div>

        <!-- Question boxes carousel (displayed at bottom of chat after answers) -->
        <div id="questionCarousel" class="question-carousel" style="display: none;">
            <div class="question-box" onclick="selectQuestion(this)">
                <div>Can you suggest products based on my skin?</div>
            </div>
            <div class="question-box" onclick="selectQuestion(this)">
                <div>What's your budget range?</div>
            </div>
            <div class="question-box" onclick="selectQuestion(this)">
                <div>What size do you need?</div>
            </div>
        </div>
    </div>
</div>

<div class="shell">
    <!-- Top Navigation Bar -->
    <div class="top-nav">
        <!-- Logo Section (Desktop Only) -->
        <div class="logo-section">
            <div class="logo-text-container">
                <div class="top-texts">
                    <a href="#" class="download-link">
                        <div class="download-text">Download</div>
                    </a>
                    <div class="text-divider"></div>
                    <div class="follow-text">Follow us on <a href="<?php echo htmlspecialchars($footerFacebook); ?>" target="_blank" class="social-link"><img src="images/icon/facebook.gif" alt="Facebook" class="facebook-icon"></a> & <a href="<?php echo htmlspecialchars($footerInstagram); ?>" target="_blank" class="social-link"><img src="images/icon/instagram.gif" alt="Instagram" class="instagram-icon"></a></div>
                </div>
                <a href="index.php" class="closetgo-link">
                    <img src="images/icon/JMB.png" alt="<?php echo htmlspecialchars($siteName); ?>" class="brand-logo" onerror="this.src='images/icon/chat.png'">
                    <span><?php echo htmlspecialchars($siteName); ?></span>
                </a>
            </div>
        </div>
        
        <!-- Mobile ClosetGo Logo -->
        <a href="index.php" class="mobile-closetgo-link">
            <img src="images/icon/JMB.png" alt="ClosetGo" class="brand-logo" onerror="this.src='images/icon/chat.png'">
            <span>ClosetGo</span>
        </a>
        
        <!-- Right Side Navigation -->
        <div class="search-filter-container">
            <div class="search-box">
                <div style="display: flex; align-items: center; width: 100%;">
                    <img src="images/icon/search.png" alt="Search" class="search-left-icon" onclick="document.getElementById('searchInput')?.focus(); openSearchOverlay();">
                    <div style="color: inherit; display: flex; align-items: center; flex: 1; min-width: 0; overflow: hidden; transition: transform 0.1s ease, opacity 0.1s ease;">
                        <input type="text" placeholder="" id="searchInput" 
                               onfocus="openSearchOverlay()" 
                               onclick="openSearchOverlay()" 
                               oninput="handleDesktopSearchInput(this)" 
                               style="background: none; border: none; outline: none; width: 100%; min-width: 0; font-size: 1rem; color: #ffffff; pointer-events: auto; transition: opacity 0.3s ease;">
                    </div>
                    <div class="ai-search-prompt" onclick="centerLottieAnimation()" title="Use ClosetGo AiGo">
                        <img src="images/icon/search_bar.png" alt="Search" class="search-right-icon">
                        <span class="ai-search-prompt-text">Use AiGo</span>
                    </div>
                </div>
                <div class="search-lottie-animation" id="mobileLottieAnimation" style="cursor: pointer; transition: all 0.5s ease; display: none;" onclick="centerLottieAnimation()">
                    <dotlottie-wc src="https://lottie.host/96e4e0e3-892e-4f75-8f37-ac9e5df01826/6yqAPa6I7w.lottie" style="width: 60px; height: 60px;" autoplay loop></dotlottie-wc>
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
            <div class="mobile-top-search" onclick="openSearchOverlay()" title="Search">
                <img src="images/icon/search.png" alt="Search">
            </div>
            <div class="purchase-top-container" style="position: relative; display: inline-block;" title="Purchases">
                <img src="images/icon/purchase.png" alt="Purchases" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'"
                     onclick="window.location.href='purchase.php'">
                <span id="topbarPurchaseBadge" class="purchase-nav-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
            </div>
            <div class="notification-container" id="topbarNotificationContainer" style="position: relative; display: inline-block;" title="Notifications" onclick="toggleNotificationDropdown(event)">
                <img src="images/icon/notification.png" alt="Notification" class="topbar-notif-icon" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'">
                <span id="topbarNotificationBadge" class="notification-nav-badge" style="display: none;">0</span>

                <!-- Notification Dropdown Panel -->
                <div id="notificationDropdown" class="notification-dropdown" onclick="event.stopPropagation()">
                    <div class="notif-dropdown-header">
                        <div class="notif-header-title">
                            <i class="fas fa-bell"></i> Notifications
                            <span id="notifUnreadPill" class="notif-unread-pill" style="display: none;">0 new</span>
                        </div>
                        <button type="button" class="notif-mark-read-btn" onclick="markAllNotificationsAsRead(event)">Mark all read</button>
                    </div>
                    <div class="notif-filter-tabs">
                        <div class="notif-tab active" onclick="filterNotifTab('all', this)">
                            <span>All</span>
                            <span id="notifTabBadgeAll" class="notif-tab-badge" style="display: none;">0</span>
                        </div>
                        <div class="notif-tab" onclick="filterNotifTab('order', this)">
                            <span>Orders</span>
                            <span id="notifTabBadgeOrder" class="notif-tab-badge" style="display: none;">0</span>
                        </div>
                    </div>
                    <div id="notifListContainer" class="notif-list-container">
                        <div class="notif-loading" style="padding: 24px; text-align: center; color: #888; font-size: 12px;"><i class="fas fa-spinner fa-spin"></i> Loading notifications...</div>
                    </div>
                </div>
            </div>
            <div class="cart-container" style="position: relative;" title="Cart">
                <img src="images/icon/cart.png" alt="Cart" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'"
                     onclick="window.location.href='save.php'">
                <span id="topbarCartCount" class="cart-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
            </div>
            <div class="chat-top-container" style="position: relative;" title="Chat with Admin" onclick="openUserChat()">
                <img src="images/icon/message.png" alt="Chat" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'">
                <span id="userChatBadge" class="purchase-nav-badge cart-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
            </div>
        </div>
    </div>

    <!-- Loading Indicator (Hidden to reveal skeleton loading instantly) -->
    <div id="loadingIndicator" class="loading" style="display: none; flex-direction: column; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; z-index: 9999; background: #1a1a1a; box-sizing: border-box; transition: opacity 0.2s ease;">
        <dotlottie-wc src="https://lottie.host/13e904a9-c620-4f23-8ba0-ad3804964ff4/22LYnBHCcG.lottie" style="width: 120px; height: 120px;" autoplay loop></dotlottie-wc>
    </div>

    <!-- Search Overlay -->
    <div id="searchOverlay" class="search-overlay">
        <div class="search-overlay-topbar">
            <img src="images/icon/back.png" alt="Back" class="search-overlay-back" onclick="closeSearchOverlay()">
            <div class="search-overlay-box">
                <img src="images/icon/search.png" alt="Search" class="search-overlay-left-icon">
                <input type="text" placeholder="Search for products..." id="searchOverlayInput" class="search-overlay-input">
                <img src="images/icon/search_bar.png" alt="Search" class="search-overlay-icon" onclick="openWhiteOverlayFromSearch()">
            </div>
        </div>
        <div class="search-overlay-body">
            <div class="search-overlay-desktop-header">
                <div class="search-overlay-desktop-title">
                    <i class="fas fa-search"></i> Search Catalog
                </div>
                <button type="button" class="search-overlay-desktop-close" onclick="closeSearchOverlay()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div id="searchOverlayGrid" class="product-grid"></div>
        </div>
    </div>

    <!-- Mobile Category Tabs Bar (fixed, does not scroll with page) -->
    <div class="mobile-category-bar" id="mobileCategoryBar">
        <div class="mobile-category-tab active" data-category="all" onclick="filterMobileCategory('all', this)">All</div>
    </div>
    <!-- Spacer that pushes content below the fixed category bar on mobile -->
    <div class="mobile-category-bar-spacer"></div>

    <!-- Mobile Search Bar -->
    <div class="mobile-search-bar" onclick="openSearchOverlay()">
        <img src="images/icon/search.png" alt="Search" class="mobile-search-left-icon" onclick="openSearchOverlay()">
        <div class="mobile-search-link" onclick="openSearchOverlay()" style="flex: 1; min-width: 0; display: flex; align-items: center; cursor: pointer;">
            <input type="text" placeholder="Search products, gowns, dresses..." id="mobileSearchInput" class="mobile-search-input" 
                   readonly 
                   onclick="openSearchOverlay()" 
                   onfocus="openSearchOverlay()"
                   style="cursor: pointer; pointer-events: auto; width: 100%;">
        </div>
        <div class="ai-search-prompt mobile" onclick="event.stopPropagation(); centerLottieAnimation();" title="Use ClosetGo AiGo">
            <img src="images/icon/search_bar.png" alt="Search" class="mobile-search-icon">
            <span class="ai-search-prompt-text">Use ClosetGo AiGo</span>
        </div>
    </div>

    <!-- Banner Image Carousel -->
    <?php if (!empty($banners)): ?>
    <div id="bannerWrapper" style="width: 100%; margin: 10px 0; display: flex; justify-content: center; padding: 0 10px; position: relative; transition: opacity 0.25s ease, max-height 0.35s ease; overflow: hidden;">
        <div id="bannerLayout" style="display: flex; width: 100%; max-width: 100%; gap: 10px;">
            <!-- Main Banner (Left) -->
            <div id="bannerCarousel" style="flex: 2; background: #f5f5f5; border-radius: 4px; overflow: hidden; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); position: relative; touch-action: pan-y;">
                <!-- Carousel Container -->
                <div id="bannerCarouselTrack" style="display: flex; transition: transform 0.3s ease; width: 100%;">
                    <?php foreach ($banners as $b): ?>
                    <div class="banner-slide" style="width: 100%; min-width: 100%; flex-shrink: 0;">
                        <?php if (!empty($b['link_url'])): ?>
                            <a href="<?php echo htmlspecialchars($b['link_url']); ?>" style="display: block; width: 100%; height: 100%; text-decoration: none;">
                                <img src="<?php echo htmlspecialchars($b['image_path']); ?>" alt="<?php echo htmlspecialchars($b['title'] ?: 'ClosetGo Promo'); ?>" style="width: 100%; height: 120px; display: block; object-fit: cover;" class="banner-slide-img">
                            </a>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($b['image_path']); ?>" alt="<?php echo htmlspecialchars($b['title'] ?: 'ClosetGo Promo'); ?>" style="width: 100%; height: 120px; display: block; object-fit: cover;" class="banner-slide-img">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Dots Indicator -->
                <?php if (count($banners) > 1): ?>
                <div id="bannerDots" style="position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 10;">
                    <?php foreach ($banners as $idx => $b): ?>
                    <span class="banner-dot <?php echo $idx === 0 ? 'active' : ''; ?>" style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo $idx === 0 ? 'white' : 'rgba(255,255,255,0.6)'; ?>; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.3); transition: all 0.3s ease;" onclick="currentBannerSlide = <?php echo $idx; ?>; updateBannerCarousel();"></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Side Banners (Laptop Only) -->
            <div id="rightBanners" style="flex: 1; display: none; flex-direction: column; gap: 10px;">
                <!-- Top Products Banner -->
                <div id="topProductsBanner" style="flex: 1; background: #f5f5f5; border-radius: 4px; overflow: hidden; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); position: relative;">
                    <div style="position: absolute; top: 8px; left: 8px; background: rgba(0,0,0,0.6); color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; z-index: 5;">Top Products</div>
                    <div id="topProductsBannerGrid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px; height: 100%;">
                        <!-- Top products images will be loaded here -->
                    </div>
                </div>

                <!-- Discount Products Banner -->
                <div id="discountProductsBanner" style="flex: 1; background: #f5f5f5; border-radius: 4px; overflow: hidden; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); position: relative;">
                    <div style="position: absolute; top: 8px; left: 8px; background: rgba(0,0,0,0.6); color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; z-index: 5;">Discounts</div>
                    <div id="discountProductsBannerGrid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px; height: 100%;">
                        <!-- Discount products images will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Categories Section -->
<div class="categories-section">
    <div class="section-header-box">
        <h3 class="section-header-title">Categories</h3>
        <img src="images/icon/back.png" alt="View More" id="categoryToggleIcon" style="width: 30px; height: 30px; cursor: pointer; opacity: 0.7; transition: opacity 0.2s ease, transform 0.3s ease; margin-top: 5px;" 
             onmouseover="this.style.opacity='1'" 
             onmouseout="this.style.opacity='0.7'"
             onclick="toggleMoreCategories()">
    </div>
    <div class="section-container">
        <!-- Horizontal Scroll Container -->
        <div class="mobile-categories-container" id="mobileCategoryContainer">
            <div class="mobile-categories-grid" id="mobileCategoryGrid">
                <!-- First row categories will be loaded here -->
            </div>
        </div>
        <!-- More Categories Container (Desktop toggle) -->
        <div id="moreCategoriesContainer" style="overflow: hidden; max-height: 0; transition: max-height 0.5s ease;">
            <div class="mobile-categories-grid" id="moreCategoriesGrid" style="margin-top: 12px;">
                <!-- More categories will be loaded here -->
            </div>
        </div>
    </div>
</div>

    <!-- Top Products Section (Top Reserved Products - Mobile & Laptop) -->
<div class="top-products-section">
    <div class="section-header-box">
        <h3 class="section-header-title">Top Products</h3>
        <img src="images/icon/back.png" alt="View More" id="topProductsToggleIcon" style="width: 30px; height: 30px; cursor: pointer; opacity: 0.7; transition: opacity 0.2s ease, transform 0.3s ease; margin-top: 5px;" 
             onmouseover="this.style.opacity='1'" 
             onmouseout="this.style.opacity='0.7'"
             onclick="toggleMoreTopProducts()">
    </div>
    <div class="section-container">
        <!-- Mobile Horizontal Scroll Container -->
        <div class="mobile-top-products-container" id="mobileTopProductsContainer">
            <div class="mobile-top-products-grid" id="mobileTopProductsGrid">
                <!-- Mobile top products will be loaded here -->
            </div>
        </div>
        
        <!-- Desktop Grids -->
        <div class="top-products-grid" id="topProductsGrid">
            <!-- Top products will be loaded here dynamically -->
        </div>
        <div id="moreTopProductsContainer" style="overflow: hidden; max-height: 0; transition: max-height 0.5s ease;">
            <div class="top-products-grid" id="moreTopProductsGrid" style="margin-top: 12px;">
                <!-- More top products will be loaded here -->
            </div>
        </div>
    </div>
</div>

    <!-- Discount Products Section (Desktop & Mobile) -->
<div class="discount-products-section">
    <div class="section-header-box">
        <h3 class="section-header-title">Special Discounts</h3>
        <img src="images/icon/back.png" alt="View More" id="discountToggleIcon" style="width: 30px; height: 30px; cursor: pointer; opacity: 0.7; transition: opacity 0.2s ease, transform 0.3s ease; margin-top: 5px;" 
             onmouseover="this.style.opacity='1'" 
             onmouseout="this.style.opacity='0.7'"
             onclick="toggleMoreDiscounts()">
    </div>
    <div class="section-container">
    
    <!-- Mobile Horizontal Scroll Container -->
    <div class="mobile-discount-products-container" id="mobileDiscountContainer">
        <div class="mobile-discount-products-grid" id="mobileDiscountGrid">
            <!-- Mobile discount products will be loaded here -->
        </div>
    </div>
    
    <!-- Desktop Grids -->
    <div class="discount-products-grid" id="discountProductsGrid">
        <!-- Discount products will be loaded here dynamically -->
    </div>
    <div id="moreDiscountsContainer" style="overflow: hidden; max-height: 0; transition: max-height 0.5s ease;">
        <div class="discount-products-grid" id="moreDiscountsGrid" style="margin-top: 12px;">
            <!-- More discount products will be loaded here -->
        </div>
    </div>
    </div>
</div>

<!-- Products Header Box -->
<div class="products-header-box">
    <h3 class="products-header-title">DISCOVER PRODUCT</h3>
</div>

<!-- Products Container Box -->
    <div class="products-container">
        <!-- Products Box Container -->
        <div class="products-box">
            <!-- Products Grid -->
            <div id="productsGrid" class="product-grid" style="display: grid;">
                <!-- Skeleton products will be populated immediately -->
            </div>
            <!-- View All Button -->
            <div id="viewAllContainer" style="text-align: center; margin-top: 20px; display: none;">
                <button id="viewAllBtn" style="background: transparent; color: #ffd700; border: none; padding: 10px 25px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 1px;">
                    View All Products
                </button>
                <style>
                    #viewAllBtn:hover {
                        color: #fff;
                        transform: translateY(-2px);
                    }
                    #viewAllBtn:active {
                        transform: translateY(0);
                    }
                </style>
            </div>
        </div>
    </div>

    <!-- No Products Message -->
    <div id="noProductsMessage" style="display: none; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 50px 20px;">
        <dotlottie-wc src="https://lottie.host/7a9e1682-3342-49d1-a823-511f6b3f1fdc/x7SG6ZMpXp.lottie" style="width: 280px; height: 280px; max-width: 90vw;" autoplay loop></dotlottie-wc>
        <p style="color: #666; margin-top: 20px; font-size: 16px;">No saved products yet</p>
    </div>

    <!-- Desktop Footer Section (Laptop / Desktop) -->
    <footer class="desktop-main-footer">
        <div class="footer-inner-grid">
            <!-- Column 1: ABOUT CLOSETGOO -->
            <div class="footer-column">
                <div class="footer-title"><?php echo htmlspecialchars($footerAboutTitle); ?></div>
                <ul class="footer-nav-list">
                    <li><a href="<?php echo htmlspecialchars($footerAboutLink); ?>">About Us</a></li>
                    <li><a href="<?php echo htmlspecialchars($footerTermsLink); ?>">Terms &amp; Conditions</a></li>
                    <li><a href="<?php echo htmlspecialchars($footerPrivacyLink); ?>">Privacy Policy</a></li>
                </ul>
            </div>

            <!-- Column 2: CONTACT US -->
            <div class="footer-column">
                <div class="footer-title"><?php echo htmlspecialchars($footerContactTitle); ?></div>
                <div class="footer-contact-info">
                    <div class="footer-contact-item">
                        <i class="fas fa-map-marker-alt contact-icon-map"></i>
                        <span><?php echo htmlspecialchars($footerAddress); ?></span>
                    </div>
                    <div class="footer-contact-item">
                        <i class="fas fa-envelope contact-icon-email"></i>
                        <a href="mailto:<?php echo htmlspecialchars($footerEmail); ?>" class="footer-contact-link"><?php echo htmlspecialchars($footerEmail); ?></a>
                    </div>
                    <div class="footer-contact-item">
                        <i class="fas fa-phone-alt contact-icon-phone"></i>
                        <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $footerPhone)); ?>" class="footer-contact-link"><?php echo htmlspecialchars($footerPhone); ?></a>
                    </div>
                </div>
            </div>

            <!-- Column 3: FOLLOW US -->
            <div class="footer-column">
                <div class="footer-title"><?php echo htmlspecialchars($footerSocialTitle); ?></div>
                <ul class="footer-social-list">
                    <li><a href="<?php echo htmlspecialchars($footerFacebook); ?>" target="_blank"><i class="fab fa-facebook-f social-icon-fb"></i> Facebook</a></li>
                    <li><a href="<?php echo htmlspecialchars($footerInstagram); ?>" target="_blank"><i class="fab fa-instagram social-icon-ig"></i> Instagram</a></li>
                </ul>
            </div>
        </div>
    </footer>
</div>

<script>
    // Skeleton Loading Generator Functions
    function renderCategoriesSkeleton() {
        const grid = document.getElementById('mobileCategoryGrid');
        if (!grid || grid.children.length > 0) return;
        for (let i = 0; i < 10; i++) {
            const card = document.createElement('div');
            card.className = 'skeleton-category-card';
            grid.appendChild(card);
        }
    }

    function renderTopProductsSkeleton() {
        const mobileGrid = document.getElementById('mobileTopProductsGrid');
        const desktopGrid = document.getElementById('topProductsGrid');
        
        if (mobileGrid && mobileGrid.children.length === 0) {
            for (let i = 0; i < 6; i++) {
                const card = document.createElement('div');
                card.className = 'skeleton-discount-card';
                card.innerHTML = `
                    <div class="skeleton-discount-image"></div>
                    <div class="skeleton-discount-title"></div>
                    <div class="skeleton-discount-price"></div>
                `;
                mobileGrid.appendChild(card);
            }
        }
        
        if (desktopGrid && desktopGrid.children.length === 0) {
            for (let i = 0; i < 6; i++) {
                const card = document.createElement('div');
                card.className = 'skeleton-discount-card';
                card.style.width = '100%';
                card.innerHTML = `
                    <div class="skeleton-discount-image"></div>
                    <div class="skeleton-discount-title"></div>
                    <div class="skeleton-discount-price"></div>
                `;
                desktopGrid.appendChild(card);
            }
        }
    }

    function renderDiscountsSkeleton() {
        const mobileGrid = document.getElementById('mobileDiscountGrid');
        const desktopGrid = document.getElementById('discountProductsGrid');
        
        if (mobileGrid && mobileGrid.children.length === 0) {
            for (let i = 0; i < 6; i++) {
                const card = document.createElement('div');
                card.className = 'skeleton-discount-card';
                card.innerHTML = `
                    <div class="skeleton-discount-image"></div>
                    <div class="skeleton-discount-title"></div>
                    <div class="skeleton-discount-price"></div>
                `;
                mobileGrid.appendChild(card);
            }
        }
        
        if (desktopGrid && desktopGrid.children.length === 0) {
            for (let i = 0; i < 6; i++) {
                const card = document.createElement('div');
                card.className = 'skeleton-discount-card';
                card.style.width = '100%';
                card.innerHTML = `
                    <div class="skeleton-discount-image"></div>
                    <div class="skeleton-discount-title"></div>
                    <div class="skeleton-discount-price"></div>
                `;
                desktopGrid.appendChild(card);
            }
        }
    }

    function renderProductsSkeleton() {
        const productsGrid = document.getElementById('productsGrid');
        if (!productsGrid || productsGrid.children.length > 0) return;
        const count = window.innerWidth <= 768 ? 6 : 8;
        for (let i = 0; i < count; i++) {
            const card = document.createElement('div');
            card.className = 'skeleton-product-card';
            card.innerHTML = `
                <div class="skeleton-product-image"></div>
                <div class="skeleton-product-info">
                    <div class="skeleton-product-title"></div>
                    <div class="skeleton-product-subtitle"></div>
                    <div class="skeleton-product-price-row">
                        <div class="skeleton-product-price"></div>
                        <div class="skeleton-product-btn"></div>
                    </div>
                </div>
            `;
            productsGrid.appendChild(card);
        }
    }

    // Run skeleton rendering immediately
    renderCategoriesSkeleton();
    renderTopProductsSkeleton();
    renderDiscountsSkeleton();
    renderProductsSkeleton();

    // Center Lottie Animation on click
    function closeWhiteOverlay() {
        const lottieDiv = document.getElementById('mobileLottieAnimation');
        const whiteOverlay = document.getElementById('whiteOverlay');
        const aiGreeting = document.getElementById('aiGreeting');

        // Return to original position with animation
        lottieDiv.style.position = 'static';
        lottieDiv.style.bottom = 'auto';
        lottieDiv.style.right = 'auto';
        lottieDiv.style.left = 'auto';
        lottieDiv.style.top = 'auto';
        lottieDiv.style.transform = 'scale(1)';
        lottieDiv.setAttribute('data-centered', 'false');
        
        // Hide animation in search bar
        lottieDiv.style.display = 'none';

        // Reset the Lottie element size to original
        const lottieElement = lottieDiv.querySelector('dotlottie-wc');
        if (lottieElement) {
            const isMobile = window.innerWidth <= 768;
            lottieElement.style.width = isMobile ? '45px' : '60px';
            lottieElement.style.height = isMobile ? '45px' : '60px';
        }

        // Slide white overlay down
        const isLaptop = window.innerWidth > 768;
        if (isLaptop) {
            whiteOverlay.style.right = '-100%';
            document.body.classList.remove('overlay-open-laptop');
        } else {
            whiteOverlay.style.bottom = '-100%';
        }

        // Restore body scrolling
        document.body.style.overflow = '';

        // Keep chat display as-is so conversations persist when reopening
        // Reset padding-top
        whiteOverlay.style.paddingTop = '50vh';

        // Move back to search-box after animation completes
        setTimeout(() => {
            const searchBox = document.querySelector('.search-box');
            if (searchBox) {
                searchBox.appendChild(lottieDiv);
            }
        }, 500);
    }

    function centerLottieAnimation() {
        const lottieDiv = document.getElementById('mobileLottieAnimation');
        const whiteOverlay = document.getElementById('whiteOverlay');
        const aiGreeting = document.getElementById('aiGreeting');
        const isCentered = lottieDiv.getAttribute('data-centered') === 'true';

        if (isCentered) {
            // Don't close overlay when clicking centered lottie
            return;
        } else {
            // Get user's first name from localStorage
            const firstName = localStorage.getItem('userFirstName') || 'User';
            aiGreeting.innerHTML = `Hey ${firstName}<br>How can I assist you?`;

            // Load user's profile image
            loadChatProfileImage();

            // Prevent body scrolling
            document.body.style.overflow = 'hidden';

            // Append to whiteOverlay to follow scrolling
            whiteOverlay.appendChild(lottieDiv);

            // Then animate to center with small delay to ensure transition works
            requestAnimationFrame(() => {
                const isMobile = window.innerWidth <= 768;
                const isSmallMobile = window.innerWidth <= 320;
                
                // Make sure the animation is visible
                lottieDiv.style.display = 'block';
                
                lottieDiv.style.position = 'absolute';
                lottieDiv.style.bottom = 'auto';
                lottieDiv.style.right = 'auto';
                lottieDiv.style.left = '50%';
                lottieDiv.style.top = '50%';
                lottieDiv.style.transform = 'translate(-50%, -50%) scale(1)';
                lottieDiv.style.zIndex = '3000';
                lottieDiv.style.margin = '0';
                lottieDiv.setAttribute('data-centered', 'true');

                // Change the actual size of the Lottie element for clarity
                const lottieElement = lottieDiv.querySelector('dotlottie-wc');
                if (lottieElement) {
                    if (isSmallMobile) {
                        lottieElement.style.width = '220px';
                        lottieElement.style.height = '220px';
                    } else if (isMobile) {
                        lottieElement.style.width = '280px';
                        lottieElement.style.height = '280px';
                    } else {
                        // Laptop devices - smaller size
                        lottieElement.style.width = '200px';
                        lottieElement.style.height = '200px';
                    }
                }

                // Slide white overlay up together with Lottie
                const isLaptop = window.innerWidth > 768;
                if (isLaptop) {
                    whiteOverlay.style.right = '0';
                    document.body.classList.add('overlay-open-laptop');
                } else {
                    whiteOverlay.style.bottom = '0';
                }

                // After centering, slide up automatically
                setTimeout(() => {
                    if (isSmallMobile) {
                        lottieDiv.style.top = '20%';
                    } else {
                        lottieDiv.style.top = '25%';
                    }
                }, 600);

                // Load saved conversations from database
                loadSavedConversations();
            });
        }
    }

    // ===== AI Conversation Persistence =====
    let conversationLoaded = false;

    function getUserIdentifier() {
        let id = localStorage.getItem('aiUserIdentifier');
        if (!id) {
            id = 'user_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('aiUserIdentifier', id);
        }
        return id;
    }

    // Global AI Timer Tracking to prevent stale timers from firing
    let aiActiveTimeouts = [];
    let aiActiveIntervals = [];

    function registerAiTimeout(fn, delay) {
        const id = setTimeout(() => {
            aiActiveTimeouts = aiActiveTimeouts.filter(t => t !== id);
            fn();
        }, delay);
        aiActiveTimeouts.push(id);
        return id;
    }

    function registerAiInterval(fn, delay) {
        const id = setInterval(fn, delay);
        aiActiveIntervals.push(id);
        return id;
    }

    function clearAllAiTimers() {
        aiActiveTimeouts.forEach(id => clearTimeout(id));
        aiActiveIntervals.forEach(id => clearInterval(id));
        aiActiveTimeouts = [];
        aiActiveIntervals = [];
    }

    function scrollAiOverlayToBottom() {
        const whiteOverlay = document.getElementById('whiteOverlay');
        if (whiteOverlay) {
            whiteOverlay.scrollTo({
                top: whiteOverlay.scrollHeight,
                behavior: 'smooth'
            });
        }
    }

    function saveConversation(question, answer, productIds) {
        const userIdentifier = getUserIdentifier();
        fetch('save_ai_conversation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                question: question,
                answer: answer,
                product_ids: productIds || [],
                user_identifier: userIdentifier
            })
        }).then(r => r.json()).then(data => {
            // Conversation saved (or duplicate detected)
        }).catch(err => {
            console.error('Error saving conversation:', err);
        });
    }

    function loadSavedConversations() {
        if (conversationLoaded) return;
        conversationLoaded = true;

        const userIdentifier = getUserIdentifier();
        fetch('get_ai_conversations.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_identifier: userIdentifier })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.conversations && data.conversations.length > 0) {
                const aiGreeting = document.getElementById('aiGreeting');
                const questionCarouselTop = document.getElementById('questionCarouselTop');
                const chatDisplay = document.getElementById('chatDisplay');
                const whiteOverlay = document.getElementById('whiteOverlay');
                const history = document.getElementById('conversationHistory');

                aiGreeting.style.display = 'none';
                questionCarouselTop.style.display = 'none';
                whiteOverlay.style.paddingTop = '31vh';
                chatDisplay.style.display = 'block';

                // Helper to create a conversation snapshot for history
                function createConversationSnapshot(convo, productData) {
                    const snapshot = document.createElement('div');
                    snapshot.className = 'conversation-snapshot';
                    snapshot.style.cssText = 'margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 20px;';

                    // User message
                    const userClone = document.createElement('div');
                    userClone.className = 'chat-message-container';
                    userClone.style.cssText = 'display: flex; justify-content: flex-end; align-items: flex-start; gap: 10px; margin-top: 15px;';
                    const msgDiv = document.createElement('div');
                    msgDiv.className = 'user-message';
                    msgDiv.textContent = convo.question;
                    userClone.appendChild(msgDiv);
                    const originalProfile = document.querySelector('.chat-user-profile');
                    if (originalProfile) {
                        userClone.appendChild(originalProfile.cloneNode(true));
                    } else {
                        const profileDiv = document.createElement('div');
                        profileDiv.className = 'chat-user-profile';
                        profileDiv.innerHTML = '<img src="images/icon/profile.png" alt="User">';
                        userClone.appendChild(profileDiv);
                    }
                    snapshot.appendChild(userClone);

                    // AI reply
                    const aiClone = document.createElement('div');
                    aiClone.className = 'ai-reply-container';
                    aiClone.style.cssText = 'display: flex; align-items: flex-start; gap: 10px; margin-top: 20px;';
                    aiClone.innerHTML = '<div class="ai-profile"><img src="images/background/Ai_profle.png" alt="AI"></div><div class="ai-message">' + escapeHtml(convo.answer) + '</div>';
                    snapshot.appendChild(aiClone);

                    // Products if available
                    if (productData && productData.products && productData.products.length > 0) {
                        const menProducts = productData.men || [];
                        const womenProducts = productData.women || [];

                        function renderSection(title, products) {
                            if (products.length === 0) return;
                            const sectionHeader = document.createElement('div');
                            sectionHeader.style.cssText = 'color: #ffd700; font-size: 14px; font-weight: 600; margin: 15px 0 10px 0; display: flex; align-items: center; gap: 8px;';
                            sectionHeader.innerHTML = '<i class="fas fa-' + (title.includes('Men') ? 'user-tie' : 'user-dress') + '"></i> ' + title;
                            snapshot.appendChild(sectionHeader);

                            const sectionGrid = document.createElement('div');
                            sectionGrid.style.cssText = 'display: flex; gap: 12px; flex-wrap: wrap;';
                            snapshot.appendChild(sectionGrid);

                            products.forEach((product) => {
                                const productItem = document.createElement('div');
                                productItem.className = 'product-item';
                                productItem.style.cursor = 'pointer';
                                // Always display uploaded image in box instead of 3D model
                                if (product.image_path) {
                                    productItem.innerHTML = '<img src="' + product.image_path + '" alt="' + escapeHtml(product.name) + '" class="product-image" style="width: 100%; height: 80px; object-fit: cover; border-radius: 8px; background: #2a2a2a;"><div class="product-name">' + escapeHtml(product.name) + '</div>';
                                } else {
                                    productItem.innerHTML = '<div class="product-image" style="width: 100%; height: 80px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #2a2a2a; color: #888; font-size: 12px;">No Image</div><div class="product-name">' + escapeHtml(product.name) + '</div>';
                                }
                                productItem.addEventListener('click', () => {
                                    viewProduct(product.id);
                                });
                                sectionGrid.appendChild(productItem);
                            });
                        }

                        renderSection("Men's Collection", menProducts);
                        renderSection("Women's Collection", womenProducts);
                    }

                    return snapshot;
                }

                // Render all conversations into history
                const allConvos = data.conversations;
                const historyPromises = allConvos.map(convo => {
                    if (convo.product_ids && convo.product_ids.length > 0) {
                        return fetch('get_products_by_id.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ ids: convo.product_ids })
                        }).then(r => r.json()).then(pData => {
                            history.appendChild(createConversationSnapshot(convo, pData));
                        }).catch(() => {
                            history.appendChild(createConversationSnapshot(convo, null));
                        });
                    } else {
                        history.appendChild(createConversationSnapshot(convo, null));
                        return Promise.resolve();
                    }
                });

                // After all history is loaded, show the question carousel at the bottom
                Promise.all(historyPromises).then(() => {
                    const questionCarousel = document.getElementById('questionCarousel');
                    if (questionCarousel) {
                        questionCarousel.style.display = 'flex';
                        questionCarousel.style.position = 'relative';
                        questionCarousel.style.left = 'auto';
                        questionCarousel.style.right = 'auto';
                        questionCarousel.style.top = 'auto';
                        questionCarousel.style.zIndex = 'auto';
                        questionCarousel.style.marginTop = '40px';
                        questionCarousel.style.marginBottom = '40px';
                    }
                    setTimeout(() => {
                        scrollAiOverlayToBottom();
                    }, 200);
                });
            }
        })
        .catch(err => {
            console.error('Error loading conversations:', err);
        });
    }

    // Sync topbar user profile/login state
    function syncTopbarAuthState() {
        const authContainer = document.getElementById('authLinksContainer');
        if (!authContainer) return;

        const isClientLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
        const userEmail = localStorage.getItem('username') || localStorage.getItem('signupEmail') || localStorage.getItem('userEmail');

        if (isClientLoggedIn || userEmail) {
            const firstName = localStorage.getItem('userFirstName') || '';
            const lastName = localStorage.getItem('userLastName') || '';
            let displayName = [firstName, lastName].filter(Boolean).join(' ');

            if (!displayName && userEmail) {
                displayName = userEmail;
            }

            const currentProfileBlock = authContainer.querySelector('.auth-user-profile');
            if (!currentProfileBlock) {
                authContainer.innerHTML = `
                    <div class="auth-user-profile">
                        <span class="auth-user-name" id="topbarUserName"></span>
                        <a href="profile.php" class="auth-user-avatar-wrap" title="View Profile">
                            <img src="images/icon/profile_logo.png" alt="Profile" class="auth-user-avatar" id="topbarUserAvatar" onerror="this.src='images/icon/profile_logo.png'">
                        </a>
                    </div>
                `;
                const topbarNameEl = document.getElementById('topbarUserName');
                if (topbarNameEl && displayName) topbarNameEl.textContent = displayName;
            } else {
                const nameEl = document.getElementById('topbarUserName');
                if (nameEl && displayName) {
                    nameEl.textContent = displayName;
                }
            }

            if (userEmail) {
                // Fetch profile image
                const imgFormData = new FormData();
                imgFormData.append('action', 'get_profile_image');
                imgFormData.append('email', userEmail);

                fetch('profile.php', {
                    method: 'POST',
                    body: imgFormData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.profile_image) {
                        const topbarAvatar = document.getElementById('topbarUserAvatar');
                        if (topbarAvatar) {
                            topbarAvatar.src = data.profile_image;
                        }
                    }
                })
                .catch(() => {});

                // Fetch latest names and ID info if not cached in localStorage
                if ((!firstName && !lastName) || !localStorage.getItem('userIdType')) {
                    const nameFormData = new FormData();
                    nameFormData.append('action', 'get_user_names');
                    nameFormData.append('email', userEmail);

                    fetch('profile.php', {
                        method: 'POST',
                        body: nameFormData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (data.first_name) localStorage.setItem('userFirstName', data.first_name);
                            if (data.last_name) localStorage.setItem('userLastName', data.last_name);
                            if (data.middle_name) localStorage.setItem('userMiddleName', data.middle_name);
                            if (data.suffix) localStorage.setItem('userSuffix', data.suffix);
                            if (data.province) localStorage.setItem('userProvince', data.province);
                            if (data.city) localStorage.setItem('userCity', data.city);
                            if (data.barangay) localStorage.setItem('userBarangay', data.barangay);
                            if (data.phone) localStorage.setItem('userPhone', data.phone);
                            if (data.id_type) {
                                localStorage.setItem('userIdType', data.id_type);
                            } else {
                                localStorage.removeItem('userIdType');
                            }
                            if (data.id_front_image) {
                                localStorage.setItem('userIdFrontImage', data.id_front_image);
                            } else {
                                localStorage.removeItem('userIdFrontImage');
                            }
                            if (data.id_back_image) {
                                localStorage.setItem('userIdBackImage', data.id_back_image);
                            } else {
                                localStorage.removeItem('userIdBackImage');
                            }
                            if (data.id_verified !== undefined) {
                                localStorage.setItem('userIdVerified', String(data.id_verified));
                            }
                            const fetchedName = [data.first_name, data.last_name].filter(Boolean).join(' ');
                            const finalDisplayName = fetchedName || userEmail;
                            const nameEl = document.getElementById('topbarUserName');
                            if (nameEl && finalDisplayName) nameEl.textContent = finalDisplayName;
                        }
                    })
                    .catch(() => {});
                }
            }
        }
    }

    // Load user's profile image for chat
    function loadChatProfileImage() {
        const userEmail = localStorage.getItem('username') || localStorage.getItem('signupEmail');
        if (!userEmail) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'get_profile_image');
        formData.append('email', userEmail);

        fetch('profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.profile_image) {
                const userImgs = document.querySelectorAll('.chat-user-profile img');
                userImgs.forEach(img => {
                    img.src = data.profile_image;
                    img.onerror = function() {
                        this.src = 'images/icon/profile.png';
                    };
                });
                const topbarAvatar = document.getElementById('topbarUserAvatar');
                if (topbarAvatar) {
                    topbarAvatar.src = data.profile_image;
                }
            }
        })
        .catch(error => {
            console.error('Error loading profile image:', error);
        });
    }

    // Archive current conversation into history before starting a new one
    function archiveCurrentConversation() {
        clearAllAiTimers();

        const history = document.getElementById('conversationHistory');
        const userMsg = document.getElementById('userMessage');
        const userChatContainer = document.getElementById('userChatContainer');
        const aiReply = document.getElementById('aiReply');
        const skinToneUserChat = document.getElementById('skinToneUserChat');
        const skinToneOptions = document.getElementById('skinToneOptions');
        const budgetUserChat = document.getElementById('budgetUserChat');
        const budgetOptions = document.getElementById('budgetOptions');
        const sizeUserChat = document.getElementById('sizeUserChat');
        const sizeOptions = document.getElementById('sizeOptions');
        const productAiReply = document.getElementById('productAiReply');
        const productDisplay = document.getElementById('productDisplay');

        // Check if there's any completed conversation content to archive
        const hasContent = userMsg && userMsg.textContent.trim() !== '';

        if (hasContent) {
            // Create a snapshot container for this conversation
            const snapshot = document.createElement('div');
            snapshot.className = 'conversation-snapshot';
            snapshot.style.cssText = 'margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 20px;';

            // Clone user message (first question)
            const userClone = document.createElement('div');
            userClone.className = 'chat-message-container';
            userClone.style.cssText = 'display: flex; justify-content: flex-end; align-items: flex-start; gap: 10px; margin-top: 15px;';
            const msgDiv = document.createElement('div');
            msgDiv.className = 'user-message';
            msgDiv.textContent = userMsg.textContent;
            userClone.appendChild(msgDiv);
            const originalProfile = document.querySelector('#chatDisplay .chat-user-profile');
            if (originalProfile) {
                userClone.appendChild(originalProfile.cloneNode(true));
            }
            snapshot.appendChild(userClone);

            // Clone AI initial reply if displayed
            if (aiReply && aiReply.style.display === 'flex') {
                const aiClone = aiReply.cloneNode(true);
                aiClone.style.display = 'flex';
                aiClone.removeAttribute('id');
                snapshot.appendChild(aiClone);
            }

            // Clone skin tone user chat
            if (skinToneUserChat && skinToneUserChat.style.display === 'flex' && skinToneUserChat.querySelector('.user-message') && skinToneUserChat.querySelector('.user-message').textContent.trim() !== '') {
                const clone = skinToneUserChat.cloneNode(true);
                clone.style.display = 'flex';
                clone.removeAttribute('id');
                snapshot.appendChild(clone);
            }

            // Clone budget user chat
            if (budgetUserChat && budgetUserChat.style.display === 'flex' && budgetUserChat.querySelector('.user-message') && budgetUserChat.querySelector('.user-message').textContent.trim() !== '') {
                const clone = budgetUserChat.cloneNode(true);
                clone.style.display = 'flex';
                clone.removeAttribute('id');
                snapshot.appendChild(clone);
            }

            // Clone size user chat
            if (sizeUserChat && sizeUserChat.style.display === 'flex' && sizeUserChat.querySelector('.user-message') && sizeUserChat.querySelector('.user-message').textContent.trim() !== '') {
                const clone = sizeUserChat.cloneNode(true);
                clone.style.display = 'flex';
                clone.removeAttribute('id');
                snapshot.appendChild(clone);
            }

            // Clone product AI reply
            if (productAiReply && productAiReply.style.display === 'flex') {
                const clone = productAiReply.cloneNode(true);
                clone.style.display = 'flex';
                clone.removeAttribute('id');
                snapshot.appendChild(clone);
            }

            // Clone product display if it has products
            if (productDisplay && productDisplay.style.display === 'flex' && productDisplay.innerHTML.trim() !== '') {
                const clone = productDisplay.cloneNode(true);
                clone.style.display = 'flex';
                clone.style.flexDirection = 'column';
                clone.removeAttribute('id');
                snapshot.appendChild(clone);
            }

            if (history) {
                history.appendChild(snapshot);
            }
        }

        // Reset all original elements completely
        if (userMsg) userMsg.textContent = '';
        if (userChatContainer) userChatContainer.style.display = 'none';
        if (aiReply) {
            aiReply.style.display = 'none';
            const msg = aiReply.querySelector('.ai-message');
            if (msg) msg.textContent = 'Select your skin type from the options below.';
        }
        if (skinToneUserChat) {
            skinToneUserChat.style.display = 'none';
            const msg = skinToneUserChat.querySelector('.user-message');
            if (msg) msg.textContent = '';
        }
        if (budgetUserChat) {
            budgetUserChat.style.display = 'none';
            const msg = budgetUserChat.querySelector('.user-message');
            if (msg) msg.textContent = '';
        }
        if (sizeUserChat) {
            sizeUserChat.style.display = 'none';
            const msg = sizeUserChat.querySelector('.user-message');
            if (msg) msg.textContent = '';
        }
        if (skinToneOptions) skinToneOptions.style.display = 'none';
        if (budgetOptions) budgetOptions.style.display = 'none';
        if (sizeOptions) sizeOptions.style.display = 'none';
        if (productAiReply) productAiReply.style.display = 'none';
        if (productDisplay) {
            productDisplay.style.display = 'none';
            productDisplay.innerHTML = '';
        }

        const aiTyping = document.getElementById('aiTyping');
        if (aiTyping) {
            aiTyping.style.display = 'none';
            const msg = aiTyping.querySelector('.ai-message');
            if (msg) msg.textContent = '';
        }

        const productAiTyping = document.getElementById('productAiTyping');
        if (productAiTyping) {
            productAiTyping.style.display = 'none';
            const msg = productAiTyping.querySelector('.ai-message');
            if (msg) msg.textContent = '';
        }

        // Reset skin tone circles inside skinToneOptions
        const skinCircles = document.querySelectorAll('#skinToneOptions .skin-tone-circle');
        skinCircles.forEach(circle => {
            circle.setAttribute('data-selected', 'false');
            circle.style.pointerEvents = 'auto';
            circle.style.opacity = '1';
        });

        // Reset budget list
        const budgetRangeList = document.getElementById('budgetRangeList');
        if (budgetRangeList) budgetRangeList.innerHTML = '';

        // Reset size inputs
        const sizeHeightInput = document.getElementById('sizeHeightInput');
        const sizeWeightInput = document.getElementById('sizeWeightInput');
        if (sizeHeightInput) sizeHeightInput.value = '';
        if (sizeWeightInput) sizeWeightInput.value = '';
    }

    // Clear chat confirmation modal triggers
    function promptClearAllAiConversations() {
        const modal = document.getElementById('clearChatConfirmModal');
        if (modal) {
            modal.classList.add('show');
        }
    }

    function closeClearChatConfirmModal() {
        const modal = document.getElementById('clearChatConfirmModal');
        if (modal) {
            modal.classList.remove('show');
        }
    }

    // Execute deletion of all conversation history
    function executeClearAllAiConversations() {
        closeClearChatConfirmModal();
        clearAllAiTimers();

        const userIdentifier = getUserIdentifier();
        fetch('delete_ai_conversations.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_identifier: userIdentifier })
        }).catch(err => {
            console.error('Error deleting conversations:', err);
        });

        const history = document.getElementById('conversationHistory');
        if (history) history.innerHTML = '';

        archiveCurrentConversation();

        const chatDisplay = document.getElementById('chatDisplay');
        const aiGreeting = document.getElementById('aiGreeting');
        const questionCarouselTop = document.getElementById('questionCarouselTop');
        const questionCarousel = document.getElementById('questionCarousel');
        const whiteOverlay = document.getElementById('whiteOverlay');

        if (chatDisplay) chatDisplay.style.display = 'none';
        if (aiGreeting) aiGreeting.style.display = 'block';
        if (questionCarouselTop) questionCarouselTop.style.display = 'flex';
        if (questionCarousel) questionCarousel.style.display = 'none';
        if (whiteOverlay) {
            whiteOverlay.style.paddingTop = '50vh';
            whiteOverlay.scrollTop = 0;
        }

        conversationLoaded = false;
    }

    function clearAllAiConversations() {
        promptClearAllAiConversations();
    }

    // Select question and display as chat
    function selectQuestion(element) {
        const questionText = element.querySelector('div').textContent.trim();
        const aiGreeting = document.getElementById('aiGreeting');
        const questionCarousel = document.getElementById('questionCarousel');
        const chatDisplay = document.getElementById('chatDisplay');
        const userMessage = document.getElementById('userMessage');
        const userChatContainer = document.getElementById('userChatContainer');
        const whiteOverlay = document.getElementById('whiteOverlay');
        const aiReply = document.getElementById('aiReply');
        const skinToneOptions = document.getElementById('skinToneOptions');
        const budgetOptions = document.getElementById('budgetOptions');
        const sizeOptions = document.getElementById('sizeOptions');

        // Archive previous conversation into history (keeps it visible)
        archiveCurrentConversation();

        // Hide greeting and question carousel
        aiGreeting.style.display = 'none';
        document.getElementById('questionCarouselTop').style.display = 'none';
        questionCarousel.style.display = 'none';

        // Move header elements up
        whiteOverlay.style.paddingTop = '31vh';

        // Show chat display and user message container with selected question
        userMessage.textContent = questionText;
        if (userChatContainer) userChatContainer.style.display = 'flex';
        chatDisplay.style.display = 'block';

        loadChatProfileImage();
        scrollAiOverlayToBottom();

        // If it's the skin question, show AI reply and skin tone options
        if (questionText === 'Can you suggest products based on my skin?') {
            const aiTyping = document.getElementById('aiTyping');
            const aiTypingMessage = aiTyping.querySelector('.ai-message');
            const fullMessage = 'Select your skin type from the options below.';
            let charIndex = 0;

            registerAiTimeout(() => {
                aiTyping.style.display = 'flex';
                aiTyping.style.position = 'relative';
                aiTyping.style.left = 'auto';
                aiTyping.style.right = 'auto';
                aiTyping.style.top = 'auto';
                aiTyping.style.zIndex = 'auto';
                aiTyping.style.marginTop = '20px';
                aiTypingMessage.textContent = '';

                // Type letter by letter
                const typeInterval = registerAiInterval(() => {
                    if (charIndex < fullMessage.length) {
                        aiTypingMessage.textContent += fullMessage.charAt(charIndex);
                        charIndex++;
                        scrollAiOverlayToBottom();
                    } else {
                        clearInterval(typeInterval);
                        registerAiTimeout(() => {
                            aiTyping.style.display = 'none';
                            aiTypingMessage.textContent = '';
                            aiReply.style.display = 'flex';
                            aiReply.style.position = 'relative';
                            aiReply.style.left = 'auto';
                            aiReply.style.right = 'auto';
                            aiReply.style.top = 'auto';
                            aiReply.style.zIndex = 'auto';
                            aiReply.style.marginTop = '20px';
                            aiReply.querySelector('.ai-message').textContent = fullMessage;

                            skinToneOptions.style.display = 'flex';
                            skinToneOptions.style.position = 'relative';
                            skinToneOptions.style.left = 'auto';
                            skinToneOptions.style.right = 'auto';
                            skinToneOptions.style.top = 'auto';
                            skinToneOptions.style.zIndex = 'auto';
                            skinToneOptions.style.marginTop = '15px';

                            const circles = document.querySelectorAll('#skinToneOptions .skin-tone-circle');
                            circles.forEach(c => {
                                c.setAttribute('data-selected', 'false');
                                c.style.pointerEvents = 'auto';
                                c.style.opacity = '1';
                            });

                            scrollAiOverlayToBottom();
                        }, 400);
                    }
                }, 40);
            }, 300);
        }

        // If it's the budget question, show AI reply and budget options
        if (questionText === "What's your budget range?") {
            const aiTyping = document.getElementById('aiTyping');
            const aiTypingMessage = aiTyping.querySelector('.ai-message');
            const fullMessage = 'Would you like to buy or reserve? Select an option below.';
            let charIndex = 0;

            registerAiTimeout(() => {
                aiTyping.style.display = 'flex';
                aiTyping.style.position = 'relative';
                aiTyping.style.left = 'auto';
                aiTyping.style.right = 'auto';
                aiTyping.style.top = 'auto';
                aiTyping.style.zIndex = 'auto';
                aiTyping.style.marginTop = '20px';
                aiTypingMessage.textContent = '';

                const typeInterval = registerAiInterval(() => {
                    if (charIndex < fullMessage.length) {
                        aiTypingMessage.textContent += fullMessage.charAt(charIndex);
                        charIndex++;
                        scrollAiOverlayToBottom();
                    } else {
                        clearInterval(typeInterval);
                        registerAiTimeout(() => {
                            aiTyping.style.display = 'none';
                            aiTypingMessage.textContent = '';
                            aiReply.style.display = 'flex';
                            aiReply.style.position = 'relative';
                            aiReply.style.left = 'auto';
                            aiReply.style.right = 'auto';
                            aiReply.style.top = 'auto';
                            aiReply.style.zIndex = 'auto';
                            aiReply.style.marginTop = '20px';
                            aiReply.querySelector('.ai-message').textContent = fullMessage;

                            budgetOptions.style.display = 'block';
                            budgetOptions.style.position = 'relative';
                            budgetOptions.style.left = 'auto';
                            budgetOptions.style.right = 'auto';
                            budgetOptions.style.top = 'auto';
                            budgetOptions.style.zIndex = 'auto';
                            budgetOptions.style.marginTop = '15px';
                            document.getElementById('budgetRangeList').innerHTML = '';

                            scrollAiOverlayToBottom();
                        }, 400);
                    }
                }, 40);
            }, 300);
        }

        // If it's the size question, show AI reply and size input
        if (questionText === "What size do you need?") {
            const aiTyping = document.getElementById('aiTyping');
            const aiTypingMessage = aiTyping.querySelector('.ai-message');
            const fullMessage = 'Please share your height and weight so I can find the best fit for you.';
            let charIndex = 0;

            registerAiTimeout(() => {
                aiTyping.style.display = 'flex';
                aiTyping.style.position = 'relative';
                aiTyping.style.left = 'auto';
                aiTyping.style.right = 'auto';
                aiTyping.style.top = 'auto';
                aiTyping.style.zIndex = 'auto';
                aiTyping.style.marginTop = '20px';
                aiTypingMessage.textContent = '';

                const typeInterval = registerAiInterval(() => {
                    if (charIndex < fullMessage.length) {
                        aiTypingMessage.textContent += fullMessage.charAt(charIndex);
                        charIndex++;
                        scrollAiOverlayToBottom();
                    } else {
                        clearInterval(typeInterval);
                        registerAiTimeout(() => {
                            aiTyping.style.display = 'none';
                            aiTypingMessage.textContent = '';
                            aiReply.style.display = 'flex';
                            aiReply.style.position = 'relative';
                            aiReply.style.left = 'auto';
                            aiReply.style.right = 'auto';
                            aiReply.style.top = 'auto';
                            aiReply.style.zIndex = 'auto';
                            aiReply.style.marginTop = '20px';
                            aiReply.querySelector('.ai-message').textContent = fullMessage;

                            sizeOptions.style.display = 'block';
                            sizeOptions.style.position = 'relative';
                            sizeOptions.style.left = 'auto';
                            sizeOptions.style.right = 'auto';
                            sizeOptions.style.top = 'auto';
                            sizeOptions.style.zIndex = 'auto';
                            sizeOptions.style.marginTop = '15px';

                            const sizeHeightInput = document.getElementById('sizeHeightInput');
                            const sizeWeightInput = document.getElementById('sizeWeightInput');
                            if (sizeHeightInput) sizeHeightInput.value = '';
                            if (sizeWeightInput) sizeWeightInput.value = '';

                            scrollAiOverlayToBottom();
                        }, 400);
                    }
                }, 40);
            }, 300);
        }
    }

    // Select skin tone
    function selectSkinTone(element) {
        // Prevent multiple clicks
        if (element.getAttribute('data-selected') === 'true') {
            return;
        }

        const skinTone = element.getAttribute('data-skin-tone');

        // Mark as selected and disable circles
        const allCircles = document.querySelectorAll('#skinToneOptions .skin-tone-circle');
        allCircles.forEach(circle => {
            circle.setAttribute('data-selected', 'true');
            circle.style.pointerEvents = 'none';
            circle.style.opacity = '0.5';
        });

        // Show user chat message with selected skin tone
        const skinToneUserChat = document.getElementById('skinToneUserChat');
        const skinToneUserMessage = document.getElementById('skinToneUserMessage');
        skinToneUserMessage.textContent = `I selected ${skinTone} skin tone`;
        skinToneUserChat.style.display = 'flex';
        skinToneUserChat.style.position = 'relative';
        skinToneUserChat.style.left = 'auto';
        skinToneUserChat.style.right = 'auto';
        skinToneUserChat.style.top = 'auto';
        skinToneUserChat.style.zIndex = 'auto';
        skinToneUserChat.style.marginTop = '15px';

        // Sync profile image from original chat
        const originalProfileImg = document.querySelector('#chatDisplay .chat-user-profile img');
        const skinToneProfileImg = document.querySelector('#skinToneUserChat .chat-user-profile img');
        if (originalProfileImg && skinToneProfileImg) {
            skinToneProfileImg.src = originalProfileImg.src;
        }

        scrollAiOverlayToBottom();

        // Define colors for each skin tone - fetched from database
        const skinToneColors = <?php echo json_encode($skinToneColors); ?>;
        const colors = skinToneColors[skinTone] || [];

        // Show AI typing animation before products
        const productAiTyping = document.getElementById('productAiTyping');
        const productAiTypingMessage = productAiTyping.querySelector('.ai-message');
        const fullProductMessage = 'Great! Based on your skin type, here are some outfit recommendations.';
        let charIndex = 0;

        productAiTyping.style.display = 'flex';
        productAiTyping.style.position = 'relative';
        productAiTyping.style.left = 'auto';
        productAiTyping.style.right = 'auto';
        productAiTyping.style.top = 'auto';
        productAiTyping.style.zIndex = 'auto';
        productAiTyping.style.marginTop = '20px';
        productAiTypingMessage.textContent = '';

        // Type letter by letter
        const typeInterval = registerAiInterval(() => {
            if (charIndex < fullProductMessage.length) {
                productAiTypingMessage.textContent += fullProductMessage.charAt(charIndex);
                charIndex++;
                scrollAiOverlayToBottom();
            } else {
                clearInterval(typeInterval);
                registerAiTimeout(() => {
                    productAiTyping.style.display = 'none';
                    productAiTypingMessage.textContent = '';
                    const productAiReply = document.getElementById('productAiReply');
                    productAiReply.style.display = 'flex';
                    productAiReply.style.position = 'relative';
                    productAiReply.style.left = 'auto';
                    productAiReply.style.right = 'auto';
                    productAiReply.style.top = 'auto';
                    productAiReply.style.zIndex = 'auto';
                    productAiReply.style.marginTop = '20px';
                    productAiReply.querySelector('.ai-message').textContent = fullProductMessage;

                    scrollAiOverlayToBottom();

                    // Fetch products by colors after typing completes
                    fetch('get_products_by_color.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ colors: colors })
                    })
                    .then(response => response.json())
                    .then(data => {
                        const productDisplay = document.getElementById('productDisplay');
                        productDisplay.innerHTML = '';
                        productDisplay.style.display = 'flex';
                        productDisplay.style.position = 'relative';
                        productDisplay.style.left = 'auto';
                        productDisplay.style.right = 'auto';
                        productDisplay.style.top = 'auto';
                        productDisplay.style.zIndex = 'auto';
                        productDisplay.style.marginTop = '30px';
                        productDisplay.style.flexDirection = 'column';

                        const menProducts = data.men || [];
                        const womenProducts = data.women || [];
                        const totalProducts = menProducts.length + womenProducts.length;
                        let productIndex = 0;

                        function renderSection(title, products) {
                            if (products.length === 0) return;
                            const sectionHeader = document.createElement('div');
                            sectionHeader.style.cssText = 'color: #ffd700; font-size: 14px; font-weight: 600; margin: 15px 0 10px 0; display: flex; align-items: center; gap: 8px;';
                            sectionHeader.innerHTML = '<i class="fas fa-' + (title.includes('Men') ? 'user-tie' : 'user-dress') + '"></i> ' + title;
                            productDisplay.appendChild(sectionHeader);

                            const sectionGrid = document.createElement('div');
                            sectionGrid.style.cssText = 'display: flex; gap: 12px; flex-wrap: wrap;';
                            productDisplay.appendChild(sectionGrid);

                            products.forEach((product) => {
                                registerAiTimeout(() => {
                                    const productItem = document.createElement('div');
                                    productItem.className = 'product-item';
                                    productItem.style.cursor = 'pointer';
                                    productItem.style.opacity = '0';
                                    productItem.style.transition = 'opacity 0.3s ease';

                                    // Always display uploaded image in box instead of 3D model
                                    if (product.image_path) {
                                        productItem.innerHTML = `
                                            <img src="${product.image_path}" alt="${escapeHtml(product.name)}" class="product-image"
                                                style="width: 100%; height: 80px; object-fit: cover; border-radius: 8px; background: #2a2a2a;">
                                            <div class="product-name">${escapeHtml(product.name)}</div>
                                        `;
                                    } else {
                                        productItem.innerHTML = `
                                            <div class="product-image" style="width: 100%; height: 80px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #2a2a2a; color: #888; font-size: 12px;">No Image</div>
                                            <div class="product-name">${escapeHtml(product.name)}</div>
                                        `;
                                    }

                                    productItem.addEventListener('click', () => {
                                        viewProduct(product.id);
                                    });

                                    sectionGrid.appendChild(productItem);

                                    registerAiTimeout(() => {
                                        productItem.style.opacity = '1';
                                        scrollAiOverlayToBottom();
                                    }, 50);
                                }, productIndex++ * 200);
                            });
                        }

                        if (totalProducts > 0) {
                            renderSection("Men's Collection", menProducts);
                            renderSection("Women's Collection", womenProducts);
                        } else {
                            productDisplay.innerHTML = '<div class="no-products">No products found for this skin tone.</div>';
                        }

                        // Save conversation to database
                        const skinProductIds = [...menProducts, ...womenProducts].map(p => p.id);
                        saveConversation(
                            'Can you suggest products based on my skin? (Selected: ' + skinTone + ')',
                            'Great! Based on your skin type, here are some outfit recommendations.',
                            skinProductIds
                        );

                        // Show question carousel after all products are displayed
                        registerAiTimeout(() => {
                            const questionCarousel = document.getElementById('questionCarousel');
                            if (questionCarousel) {
                                questionCarousel.style.display = 'flex';
                                questionCarousel.style.position = 'relative';
                                questionCarousel.style.left = 'auto';
                                questionCarousel.style.right = 'auto';
                                questionCarousel.style.top = 'auto';
                                questionCarousel.style.zIndex = 'auto';
                                questionCarousel.style.marginTop = '40px';
                                questionCarousel.style.marginBottom = '40px';
                                scrollAiOverlayToBottom();
                            }
                        }, totalProducts > 0 ? (totalProducts * 200) + 300 : 300);
                    })
                    .catch(error => {
                        console.error('Error fetching products:', error);
                    });
                }, 400);
            }
        }, 40);
    }

    // ===== Budget Range Functions =====
    const budgetRanges = {
        buy: [
            { label: 'Under ₱1,000', min: 0, max: 1000 },
            { label: '₱1,000 - ₱3,000', min: 1000, max: 3000 },
            { label: '₱3,000 - ₱5,000', min: 3000, max: 5000 },
            { label: '₱5,000 - ₱10,000', min: 5000, max: 10000 },
            { label: '₱10,000+', min: 10000, max: 999999 }
        ],
        reserve: [
            { label: 'Under ₱200', min: 0, max: 200 },
            { label: '₱200 - ₱500', min: 200, max: 500 },
            { label: '₱500 - ₱1,000', min: 500, max: 1000 },
            { label: '₱1,000 - ₱2,000', min: 1000, max: 2000 },
            { label: '₱2,000+', min: 2000, max: 999999 }
        ]
    };

    function showBudgetRanges(type) {
        const rangeList = document.getElementById('budgetRangeList');
        rangeList.innerHTML = '';

        const ranges = budgetRanges[type] || [];
        ranges.forEach(range => {
            const btn = document.createElement('div');
            btn.style.cssText = 'padding: 12px 20px; background: #1a1a1a; border: 1px solid #333; border-radius: 10px; cursor: pointer; font-size: 14px; color: #e0e0e0; transition: all 0.2s ease; display: flex; align-items: center; gap: 10px;';
            btn.innerHTML = '<i class="fas fa-tag" style="color: #ffd700;"></i> ' + range.label;
            btn.onmouseover = function() { this.style.borderColor = '#7c6300'; this.style.background = '#222'; this.style.transform = 'translateX(4px)'; };
            btn.onmouseout = function() { this.style.borderColor = '#333'; this.style.background = '#1a1a1a'; this.style.transform = 'translateX(0)'; };
            btn.onclick = function() { selectBudgetRange(type, range); };
            rangeList.appendChild(btn);
        });

        scrollAiOverlayToBottom();
    }

    function selectBudgetRange(type, range) {
        const budgetOptions = document.getElementById('budgetOptions');
        const budgetUserChat = document.getElementById('budgetUserChat');
        const budgetUserMessage = document.getElementById('budgetUserMessage');
        const productAiTyping = document.getElementById('productAiTyping');
        const productAiTypingMessage = productAiTyping.querySelector('.ai-message');
        const productAiReply = document.getElementById('productAiReply');
        const productDisplay = document.getElementById('productDisplay');

        // Hide budget options
        budgetOptions.style.display = 'none';

        // Show user message
        const typeLabel = type === 'buy' ? 'Buy' : 'Reserve';
        budgetUserMessage.textContent = typeLabel + ': ' + range.label;
        budgetUserChat.style.display = 'flex';
        budgetUserChat.style.position = 'relative';
        budgetUserChat.style.left = 'auto';
        budgetUserChat.style.right = 'auto';
        budgetUserChat.style.top = 'auto';
        budgetUserChat.style.zIndex = 'auto';
        budgetUserChat.style.marginTop = '15px';

        // Sync profile image
        const originalProfileImg = document.querySelector('#chatDisplay .chat-user-profile img');
        const budgetProfileImg = document.querySelector('#budgetUserChat .chat-user-profile img');
        if (originalProfileImg && budgetProfileImg) {
            budgetProfileImg.src = originalProfileImg.src;
        }

        scrollAiOverlayToBottom();

        // AI typing animation
        const fullMessage = type === 'buy'
            ? 'Here are products within your buying budget of ' + range.label + '.'
            : 'Here are products within your rental budget of ' + range.label + '.';
        let charIndex = 0;

        productAiTyping.style.display = 'flex';
        productAiTyping.style.position = 'relative';
        productAiTyping.style.left = 'auto';
        productAiTyping.style.right = 'auto';
        productAiTyping.style.top = 'auto';
        productAiTyping.style.zIndex = 'auto';
        productAiTyping.style.marginTop = '20px';
        productAiTypingMessage.textContent = '';

        const typeInterval = registerAiInterval(() => {
            if (charIndex < fullMessage.length) {
                productAiTypingMessage.textContent += fullMessage.charAt(charIndex);
                charIndex++;
                scrollAiOverlayToBottom();
            } else {
                clearInterval(typeInterval);
                registerAiTimeout(() => {
                    productAiTyping.style.display = 'none';
                    productAiTypingMessage.textContent = '';
                    productAiReply.style.display = 'flex';
                    productAiReply.style.position = 'relative';
                    productAiReply.style.left = 'auto';
                    productAiReply.style.right = 'auto';
                    productAiReply.style.top = 'auto';
                    productAiReply.style.zIndex = 'auto';
                    productAiReply.style.marginTop = '20px';
                    productAiReply.querySelector('.ai-message').textContent = fullMessage;

                    scrollAiOverlayToBottom();

                    // Fetch products by budget
                    fetch('get_products_by_budget.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ budget_type: type, min_price: range.min, max_price: range.max })
                    })
                    .then(response => response.json())
                    .then(data => {
                        productDisplay.innerHTML = '';
                        productDisplay.style.display = 'flex';
                        productDisplay.style.position = 'relative';
                        productDisplay.style.left = 'auto';
                        productDisplay.style.right = 'auto';
                        productDisplay.style.top = 'auto';
                        productDisplay.style.zIndex = 'auto';
                        productDisplay.style.marginTop = '30px';
                        productDisplay.style.flexDirection = 'column';

                        const menProducts = data.men || [];
                        const womenProducts = data.women || [];
                        const totalProducts = menProducts.length + womenProducts.length;
                        let productIndex = 0;

                        function renderBudgetSection(title, products) {
                            if (products.length === 0) return;
                            const sectionHeader = document.createElement('div');
                            sectionHeader.style.cssText = 'color: #ffd700; font-size: 14px; font-weight: 600; margin: 15px 0 10px 0; display: flex; align-items: center; gap: 8px;';
                            sectionHeader.innerHTML = '<i class="fas fa-' + (title.includes('Men') ? 'user-tie' : 'user-dress') + '"></i> ' + title;
                            productDisplay.appendChild(sectionHeader);

                            const sectionGrid = document.createElement('div');
                            sectionGrid.style.cssText = 'display: flex; gap: 12px; flex-wrap: wrap;';
                            productDisplay.appendChild(sectionGrid);

                            products.forEach((product) => {
                                registerAiTimeout(() => {
                                    const productItem = document.createElement('div');
                                    productItem.className = 'product-item';
                                    productItem.style.cursor = 'pointer';
                                    productItem.style.opacity = '0';
                                    productItem.style.transition = 'opacity 0.3s ease';

                                    // Always display uploaded image in box instead of 3D model
                                    if (product.image_path) {
                                        productItem.innerHTML = `
                                            <img src="${product.image_path}" alt="${escapeHtml(product.name)}" class="product-image"
                                                style="width: 100%; height: 80px; object-fit: cover; border-radius: 8px; background: #2a2a2a;">
                                            <div class="product-name">${escapeHtml(product.name)}</div>
                                        `;
                                    } else {
                                        productItem.innerHTML = `
                                            <div class="product-image" style="width: 100%; height: 80px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #2a2a2a; color: #888; font-size: 12px;">No Image</div>
                                            <div class="product-name">${escapeHtml(product.name)}</div>
                                        `;
                                    }

                                    productItem.addEventListener('click', () => {
                                        viewProduct(product.id);
                                    });

                                    sectionGrid.appendChild(productItem);

                                    registerAiTimeout(() => {
                                        productItem.style.opacity = '1';
                                        scrollAiOverlayToBottom();
                                    }, 50);
                                }, productIndex++ * 200);
                            });
                        }

                        if (totalProducts > 0) {
                            renderBudgetSection("Men's Collection", menProducts);
                            renderBudgetSection("Women's Collection", womenProducts);
                        } else {
                            productDisplay.innerHTML = '<div class="no-products">No products found in this budget range.</div>';
                        }

                        // Save conversation to database
                        const budgetProductIds = [...menProducts, ...womenProducts].map(p => p.id);
                        saveConversation(
                            "What's your budget range? (" + (type === 'buy' ? 'Buy' : 'Reserve') + ': ' + range.label + ')',
                            type === 'buy'
                                ? 'Here are products within your buying budget of ' + range.label + '.'
                                : 'Here are products within your rental budget of ' + range.label + '.',
                            budgetProductIds
                        );

                        // Show question carousel after products
                        registerAiTimeout(() => {
                            const questionCarousel = document.getElementById('questionCarousel');
                            if (questionCarousel) {
                                questionCarousel.style.display = 'flex';
                                questionCarousel.style.position = 'relative';
                                questionCarousel.style.left = 'auto';
                                questionCarousel.style.right = 'auto';
                                questionCarousel.style.top = 'auto';
                                questionCarousel.style.zIndex = 'auto';
                                questionCarousel.style.marginTop = '40px';
                                questionCarousel.style.marginBottom = '40px';
                                scrollAiOverlayToBottom();
                            }
                        }, totalProducts > 0 ? (totalProducts * 200) + 300 : 300);
                    })
                    .catch(error => {
                        console.error('Error fetching products by budget:', error);
                    });
                }, 400);
            }
        }, 40);
    }

    // ===== Size Measurement Functions =====
    function submitSizeMeasurements() {
        const height = parseFloat(document.getElementById('sizeHeightInput').value);
        const weight = parseFloat(document.getElementById('sizeWeightInput').value);

        if (!height || !weight || height < 100 || weight < 30) {
            alert('Please enter a valid height (min 100cm) and weight (min 30kg).');
            return;
        }

        const sizeOptions = document.getElementById('sizeOptions');
        const sizeUserChat = document.getElementById('sizeUserChat');
        const sizeUserMessage = document.getElementById('sizeUserMessage');
        const productAiTyping = document.getElementById('productAiTyping');
        const productAiTypingMessage = productAiTyping.querySelector('.ai-message');
        const productAiReply = document.getElementById('productAiReply');
        const productDisplay = document.getElementById('productDisplay');

        // Hide size options
        sizeOptions.style.display = 'none';

        // Show user message
        sizeUserMessage.textContent = 'Height: ' + height + ' cm, Weight: ' + weight + ' kg';
        sizeUserChat.style.display = 'flex';
        sizeUserChat.style.position = 'relative';
        sizeUserChat.style.left = 'auto';
        sizeUserChat.style.right = 'auto';
        sizeUserChat.style.top = 'auto';
        sizeUserChat.style.zIndex = 'auto';
        sizeUserChat.style.marginTop = '15px';

        // Sync profile image
        const originalProfileImg = document.querySelector('#chatDisplay .chat-user-profile img');
        const sizeProfileImg = document.querySelector('#sizeUserChat .chat-user-profile img');
        if (originalProfileImg && sizeProfileImg) {
            sizeProfileImg.src = originalProfileImg.src;
        }

        scrollAiOverlayToBottom();

        // AI typing animation
        const fullMessage = 'Based on your measurements, here are products that should fit you well.';
        let charIndex = 0;

        productAiTyping.style.display = 'flex';
        productAiTyping.style.position = 'relative';
        productAiTyping.style.left = 'auto';
        productAiTyping.style.right = 'auto';
        productAiTyping.style.top = 'auto';
        productAiTyping.style.zIndex = 'auto';
        productAiTyping.style.marginTop = '20px';
        productAiTypingMessage.textContent = '';

        const typeInterval = registerAiInterval(() => {
            if (charIndex < fullMessage.length) {
                productAiTypingMessage.textContent += fullMessage.charAt(charIndex);
                charIndex++;
                scrollAiOverlayToBottom();
            } else {
                clearInterval(typeInterval);
                registerAiTimeout(() => {
                    productAiTyping.style.display = 'none';
                    productAiTypingMessage.textContent = '';
                    productAiReply.style.display = 'flex';
                    productAiReply.style.position = 'relative';
                    productAiReply.style.left = 'auto';
                    productAiReply.style.right = 'auto';
                    productAiReply.style.top = 'auto';
                    productAiReply.style.zIndex = 'auto';
                    productAiReply.style.marginTop = '20px';
                    productAiReply.querySelector('.ai-message').textContent = fullMessage;

                    scrollAiOverlayToBottom();

                    // Fetch products by size
                    fetch('get_products_by_size.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ height: height, weight: weight })
                    })
                    .then(response => response.json())
                    .then(data => {
                        productDisplay.innerHTML = '';
                        productDisplay.style.display = 'flex';
                        productDisplay.style.position = 'relative';
                        productDisplay.style.left = 'auto';
                        productDisplay.style.right = 'auto';
                        productDisplay.style.top = 'auto';
                        productDisplay.style.zIndex = 'auto';
                        productDisplay.style.marginTop = '30px';
                        productDisplay.style.flexDirection = 'column';

                        const menProducts = data.men || [];
                        const womenProducts = data.women || [];
                        const totalProducts = menProducts.length + womenProducts.length;
                        let productIndex = 0;

                        function renderSizeSection(title, products) {
                            if (products.length === 0) return;
                            const sectionHeader = document.createElement('div');
                            sectionHeader.style.cssText = 'color: #ffd700; font-size: 14px; font-weight: 600; margin: 15px 0 10px 0; display: flex; align-items: center; gap: 8px;';
                            sectionHeader.innerHTML = '<i class="fas fa-' + (title.includes('Men') ? 'user-tie' : 'user-dress') + '"></i> ' + title;
                            productDisplay.appendChild(sectionHeader);

                            const sectionGrid = document.createElement('div');
                            sectionGrid.style.cssText = 'display: flex; gap: 12px; flex-wrap: wrap;';
                            productDisplay.appendChild(sectionGrid);

                            products.forEach((product) => {
                                registerAiTimeout(() => {
                                    const productItem = document.createElement('div');
                                    productItem.className = 'product-item';
                                    productItem.style.cursor = 'pointer';
                                    productItem.style.opacity = '0';
                                    productItem.style.transition = 'opacity 0.3s ease';

                                    // Always display uploaded image in box instead of 3D model
                                    if (product.image_path) {
                                        productItem.innerHTML = `
                                            <img src="${product.image_path}" alt="${escapeHtml(product.name)}" class="product-image"
                                                style="width: 100%; height: 80px; object-fit: cover; border-radius: 8px; background: #2a2a2a;">
                                            <div class="product-name">${escapeHtml(product.name)}</div>
                                        `;
                                    } else {
                                        productItem.innerHTML = `
                                            <div class="product-image" style="width: 100%; height: 80px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #2a2a2a; color: #888; font-size: 12px;">No Image</div>
                                            <div class="product-name">${escapeHtml(product.name)}</div>
                                        `;
                                    }

                                    productItem.addEventListener('click', () => {
                                        viewProduct(product.id);
                                    });

                                    sectionGrid.appendChild(productItem);

                                    registerAiTimeout(() => {
                                        productItem.style.opacity = '1';
                                        scrollAiOverlayToBottom();
                                    }, 50);
                                }, productIndex++ * 200);
                            });
                        }

                        if (totalProducts > 0) {
                            renderSizeSection("Men's Collection", menProducts);
                            renderSizeSection("Women's Collection", womenProducts);
                        } else {
                            productDisplay.innerHTML = '<div class="no-products">No products found matching your size.</div>';
                        }

                        // Save conversation to database
                        const sizeProductIds = [...menProducts, ...womenProducts].map(p => p.id);
                        saveConversation(
                            "What size do you need? (Height: " + height + " cm, Weight: " + weight + " kg)",
                            'Based on your measurements, here are products that should fit you well.',
                            sizeProductIds
                        );

                        // Show question carousel after products
                        registerAiTimeout(() => {
                            const questionCarousel = document.getElementById('questionCarousel');
                            if (questionCarousel) {
                                questionCarousel.style.display = 'flex';
                                questionCarousel.style.position = 'relative';
                                questionCarousel.style.left = 'auto';
                                questionCarousel.style.right = 'auto';
                                questionCarousel.style.top = 'auto';
                                questionCarousel.style.zIndex = 'auto';
                                questionCarousel.style.marginTop = '40px';
                                questionCarousel.style.marginBottom = '40px';
                                scrollAiOverlayToBottom();
                            }
                        }, totalProducts > 0 ? (totalProducts * 200) + 300 : 300);
                    })
                    .catch(error => {
                        console.error('Error fetching products by size:', error);
                    });
                }, 400);
            }
        }, 40);
    }

    // Banner Carousel Variables
    let currentBannerSlide = 0;
    let bannerTouchStartX = 0;
    let bannerTouchEndX = 0;
    let bannerSlides = 0;
    let isTransitioning = false;

    // Clone first slide for infinite loop if multiple slides exist
    function setupInfiniteLoop() {
        const track = document.getElementById('bannerCarouselTrack');
        if (!track) return;
        const slides = track.querySelectorAll('.banner-slide');
        if (slides.length <= 1) {
            bannerSlides = slides.length;
            return;
        }
        const firstSlide = slides[0].cloneNode(true);
        track.appendChild(firstSlide);
        bannerSlides = track.querySelectorAll('.banner-slide').length;
    }

    // Update Banner Carousel Position
    function updateBannerCarousel() {
        const track = document.getElementById('bannerCarouselTrack');
        if (!track) return;
        const offset = -currentBannerSlide * 100;
        track.style.transform = `translateX(${offset}%)`;

        // Update dots (only for original slides)
        const dots = document.querySelectorAll('.banner-dot');
        if (dots.length > 0) {
            const numOriginal = (bannerSlides > 1) ? (bannerSlides - 1) : 1;
            const actualSlide = currentBannerSlide % numOriginal;
            dots.forEach((dot, index) => {
                if (index === actualSlide) {
                    dot.classList.add('active');
                    dot.style.background = 'white';
                } else {
                    dot.classList.remove('active');
                    dot.style.background = 'rgba(255,255,255,0.6)';
                }
            });
        }
    }

    // Handle infinite loop transition
    function handleTransitionEnd() {
        const track = document.getElementById('bannerCarouselTrack');
        if (!track || bannerSlides <= 1) {
            isTransitioning = false;
            return;
        }
        if (currentBannerSlide >= bannerSlides - 1) {
            track.style.transition = 'none';
            currentBannerSlide = 0;
            track.style.transform = `translateX(0%)`;
            setTimeout(() => {
                track.style.transition = 'transform 0.3s ease';
            }, 20);
        } else if (currentBannerSlide < 0) {
            track.style.transition = 'none';
            currentBannerSlide = bannerSlides - 2;
            track.style.transform = `translateX(${-(bannerSlides - 2) * 100}%)`;
            setTimeout(() => {
                track.style.transition = 'transform 0.3s ease';
            }, 20);
        }
        isTransitioning = false;
    }

    // Banner Swipe Handler
    function handleBannerSwipe() {
        if (isTransitioning || bannerSlides <= 1) return;
        
        const diff = bannerTouchStartX - bannerTouchEndX;
        
        if (Math.abs(diff) > 40) { // Minimum swipe distance
            isTransitioning = true;
            if (diff > 0) {
                // Swipe left - next slide
                currentBannerSlide++;
            } else {
                // Swipe right - previous slide
                currentBannerSlide--;
            }
            updateBannerCarousel();
            
            // Handle loop after transition
            setTimeout(handleTransitionEnd, 300);
        }
    }

    // Banner Touch Events
    const bannerCarousel = document.getElementById('bannerCarousel');
    if (bannerCarousel) {
        setupInfiniteLoop();

        bannerCarousel.addEventListener('touchstart', (e) => {
            bannerTouchStartX = e.changedTouches[0].screenX;
        }, false);

        bannerCarousel.addEventListener('touchend', (e) => {
            bannerTouchEndX = e.changedTouches[0].screenX;
            handleBannerSwipe();
        }, false);

        // Mouse swipe support for desktop
        let isMouseDown = false;
        bannerCarousel.addEventListener('mousedown', (e) => {
            isMouseDown = true;
            bannerTouchStartX = e.screenX;
        });

        bannerCarousel.addEventListener('mousemove', (e) => {
            if (!isMouseDown) return;
        });

        bannerCarousel.addEventListener('mouseup', (e) => {
            isMouseDown = false;
            bannerTouchEndX = e.screenX;
            handleBannerSwipe();
        });

        bannerCarousel.addEventListener('mouseleave', () => {
            isMouseDown = false;
        });
    }

    // Handle window resize for banner layout responsiveness
    window.addEventListener('resize', function() {
        const bannerLayout = document.getElementById('bannerLayout');
        if (bannerLayout) {
            if (window.innerWidth >= 768) {
                bannerLayout.style.flexDirection = 'row';
            } else {
                bannerLayout.style.flexDirection = 'column';
            }
        }
    });



    // Auto-rotate banner carousel every 5 seconds if multiple slides exist
    setInterval(() => {
        if (!isTransitioning && bannerSlides > 2) {
            isTransitioning = true;
            currentBannerSlide++;
            updateBannerCarousel();
            setTimeout(handleTransitionEnd, 300);
        }
    }, 5000);

    // Admin shortcut functionality

    // Desktop: Ctrl+Q shortcut
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'q') {
            e.preventDefault();
            window.location.href = 'admin/adminlogin.php';
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const adminBtn = document.getElementById('adminButton');
        if (adminBtn) {
            adminBtn.style.display = 'none';
        }

        // Initialize banner layout
        const bannerLayout = document.getElementById('bannerLayout');
        if (bannerLayout) {
            if (window.innerWidth >= 768) {
                bannerLayout.style.flexDirection = 'row';
            } else {
                bannerLayout.style.flexDirection = 'column';
            }
        }

        // Load products
        loadProducts();
        // Load top reserved products
        loadTopProducts();
        // Load discount products
        loadDiscountProducts();
        // Load categories
        loadCategories();
        // Update cart count
        updateCartCount();
        // Update purchase badge count
        fetchPurchaseBadgeCount();
        // Check mobile top search visibility
        handleMobileTopSearchVisibility();
    });

    // Toggle mobile top search icon visibility on scroll
    function handleMobileTopSearchVisibility() {
        const mobileTopSearch = document.querySelector('.mobile-top-search');
        if (!mobileTopSearch) return;

        if (window.scrollY > 40) {
            mobileTopSearch.classList.add('visible');
        } else {
            mobileTopSearch.classList.remove('visible');
        }
    }

    window.addEventListener('scroll', handleMobileTopSearchVisibility, { passive: true });

    // ===== Scroll Position Preservation (Desktop + Mobile) =====
    // Disable browser's native scroll restoration so it doesn't fight our own logic.
    // This is critical for iOS Safari which can reset scroll even after pageshow.
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

    // Save scroll position to sessionStorage.
    // Debounced on scroll for continuous saving.
    let _scrollSaveTimer;
    window.addEventListener('scroll', function() {
        clearTimeout(_scrollSaveTimer);
        _scrollSaveTimer = setTimeout(function() {
            sessionStorage.setItem('indexScrollY', window.scrollY);
        }, 80);
    }, { passive: true });

    // Also save immediately on touchend — captures position right before a
    // mobile swipe-back gesture triggers navigation, before scroll resets.
    window.addEventListener('touchend', function() {
        sessionStorage.setItem('indexScrollY', window.scrollY);
    }, { passive: true });

    // Restore scroll after returning via browser Back button.
    // pageshow fires on both fresh loads and bfcache restores (iOS/Android).
    window.addEventListener('pageshow', function(e) {
        const savedY = parseInt(sessionStorage.getItem('indexScrollY') || '0', 10);
        if (!savedY) return;

        if (e.persisted) {
            // bfcache restore — DOM is already in memory (common on mobile browsers).
            // Use a short retry loop because iOS Safari can reset scroll after pageshow.
            let attempts = 0;
            function restoreScroll() {
                window.scrollTo(0, savedY);
                attempts++;
                if (Math.abs(window.scrollY - savedY) > 5 && attempts < 10) {
                    requestAnimationFrame(restoreScroll);
                }
            }
            requestAnimationFrame(restoreScroll);
        } else {
            // Normal page load — products load asynchronously via fetch,
            // so defer the restore until the grid is rendered (see loadProducts).
            _pendingScrollRestore = savedY;
        }
    });

    // Function to update cart count from database API
    function updateCartCount() {
        const cartBadges = [
            document.getElementById('cartCount'),
            document.getElementById('topbarCartCount')
        ].filter(Boolean);
        if (cartBadges.length === 0) return;

        // Fetch cart count from database
        fetch('save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_cart_count'
        })
        .then(response => response.json())
        .then(data => {
            const count = data.success ? data.count : 0;
            cartBadges.forEach(badge => {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            });
        })
        .catch(error => {
            console.error('Error fetching cart count:', error);
            cartBadges.forEach(badge => {
                badge.style.display = 'none';
            });
        });
    }

    // Function to update Purchases button badge based on confirmed, completed, cancelled tabs
    function updatePurchasesBadge(purchasesData) {
        const badge1 = document.getElementById('purchaseBadge');
        const badge2 = document.getElementById('topbarPurchaseBadge');
        const badges = [badge1, badge2].filter(Boolean);
        if (badges.length === 0) return;

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

        badges.forEach(badge => {
            if (unviewedCount > 0) {
                badge.textContent = unviewedCount;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        });
    }

    function fetchPurchaseBadgeCount() {
        const badge1 = document.getElementById('purchaseBadge');
        const badge2 = document.getElementById('topbarPurchaseBadge');
        if (!badge1 && !badge2) return;

        fetch('save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_purchase_badge_data'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && Array.isArray(data.purchases)) {
                updatePurchasesBadge(data.purchases);
            } else {
                if (badge1) badge1.style.display = 'none';
                if (badge2) badge2.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error fetching purchase badge:', error);
            if (badge1) badge1.style.display = 'none';
            if (badge2) badge2.style.display = 'none';
        });
    }

    // Update cart count and purchase badge periodically (every 10 seconds)
    setInterval(updateCartCount, 10000);
    setInterval(fetchPurchaseBadgeCount, 10000);
    window.addEventListener('pageshow', fetchPurchaseBadgeCount);

    // Mobile bottom navigation functionality
    function setActiveNav(element) {
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });

        element.classList.add('active');

        const href = element.getAttribute('href');
        if (href && href.startsWith('#') && href !== '#home') {
            event.preventDefault();
            const targetId = href.substring(1);
            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                const offset = 80;
                const elementPosition = targetElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - offset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        }
    }

    // Hide loading overlay smoothly
    function hideLoadingIndicator() {
        const loadingIndicator = document.getElementById('loadingIndicator');
        if (loadingIndicator) {
            loadingIndicator.style.opacity = '0';
            loadingIndicator.style.pointerEvents = 'none';
            setTimeout(() => {
                loadingIndicator.style.display = 'none';
            }, 150);
        }
    }

    // Centralized Fast Data Fetchers with sessionStorage Caching
    let _gownsPromise = null;
    function fetchGowns(forceRefresh = false) {
        if (!forceRefresh) {
            try {
                const cached = sessionStorage.getItem('closetgo_cached_gowns');
                if (cached) {
                    const parsed = JSON.parse(cached);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        // Background silent refresh to keep data current
                        setTimeout(() => fetchGowns(true), 1200);
                        return Promise.resolve(parsed);
                    }
                }
            } catch (e) {}
        }

        if (_gownsPromise && !forceRefresh) {
            return _gownsPromise;
        }

        _gownsPromise = fetch('admin/gowns.php?action=getAllGowns&hide_out_of_stock=1')
            .then(response => response.json())
            .then(data => {
                if (Array.isArray(data) && data.length > 0) {
                    try {
                        sessionStorage.setItem('closetgo_cached_gowns', JSON.stringify(data));
                    } catch (e) {}
                }
                return Array.isArray(data) ? data : [];
            })
            .catch(err => {
                console.error('Error fetching gowns:', err);
                return [];
            })
            .finally(() => {
                _gownsPromise = null;
            });

        return _gownsPromise;
    }

    let _categoriesPromise = null;
    function fetchCategories(forceRefresh = false) {
        if (!forceRefresh) {
            try {
                const cached = sessionStorage.getItem('closetgo_cached_categories');
                if (cached) {
                    const parsed = JSON.parse(cached);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        setTimeout(() => fetchCategories(true), 1500);
                        return Promise.resolve(parsed);
                    }
                }
            } catch (e) {}
        }

        if (_categoriesPromise && !forceRefresh) {
            return _categoriesPromise;
        }

        _categoriesPromise = fetch('admin/categories.php?action=getAllCategories')
            .then(response => response.json())
            .then(data => {
                if (Array.isArray(data) && data.length > 0) {
                    try {
                        sessionStorage.setItem('closetgo_cached_categories', JSON.stringify(data));
                    } catch (e) {}
                }
                return Array.isArray(data) ? data : [];
            })
            .catch(err => {
                console.error('Error fetching categories:', err);
                return [];
            })
            .finally(() => {
                _categoriesPromise = null;
            });

        return _categoriesPromise;
    }

    // Holds a scroll-Y to restore once products finish rendering on a normal page load.
    let _pendingScrollRestore = 0;

    // Load products from admin inventory
    function loadProducts() {
        const productsGrid = document.getElementById('productsGrid');
        const noProductsMessage = document.getElementById('noProductsMessage');

        fetchGowns()
            .then(data => {
                hideLoadingIndicator();

                if (data && data.length > 0) {
                    if (productsGrid) productsGrid.style.display = 'grid';
                    if (noProductsMessage) noProductsMessage.style.display = 'none';
                    displayProducts(data);
                    // Start dynamic placeholder rotation
                    startPlaceholderRotation(data);
                } else {
                    if (noProductsMessage) noProductsMessage.style.display = 'flex';
                }

                // Restore scroll position after products are rendered
                const savedScroll = _pendingScrollRestore || parseInt(sessionStorage.getItem('indexScrollY') || '0', 10);
                if (savedScroll > 0) {
                    _pendingScrollRestore = 0;
                    requestAnimationFrame(function() {
                        requestAnimationFrame(function() {
                            window.scrollTo({ top: savedScroll, behavior: 'instant' });
                        });
                    });
                }
            })
            .catch(error => {
                console.error('Error loading products:', error);
                hideLoadingIndicator();
                if (noProductsMessage) noProductsMessage.style.display = 'block';
            });
    }

    // Load top reserved products based on admin dashboard
    let allTopProducts = [];
    function loadTopProducts() {
        const topProductsGrid = document.getElementById('topProductsGrid');
        const mobileTopProductsGrid = document.getElementById('mobileTopProductsGrid');
        const section = document.querySelector('.top-products-section');
        const topProductsBannerGrid = document.getElementById('topProductsBannerGrid');

        fetch('admin/gowns.php?action=getTopReservedGowns&limit=12')
            .then(response => response.json())
            .then(data => {
                if (Array.isArray(data) && data.length > 0) {
                    allTopProducts = data;
                    if (section) section.style.display = '';

                    // Load mobile top products
                    if (mobileTopProductsGrid) {
                        filterMobileTopProducts();
                    }

                    // Load desktop top products
                    if (topProductsGrid) {
                        const firstSix = data.slice(0, 6);
                        displayTopProducts(firstSix);

                        const remaining = data.slice(6);
                        if (remaining.length > 0) {
                            displayMoreTopProducts(remaining);
                        } else {
                            const toggleIcon = document.getElementById('topProductsToggleIcon');
                            if (toggleIcon) toggleIcon.style.display = 'none';
                        }
                    }

                    // Load top products banner for laptop layout
                    if (topProductsBannerGrid) {
                        const bannerProducts = data.slice(0, 4); // Show 4 products in banner
                        topProductsBannerGrid.innerHTML = '';
                        bannerProducts.forEach(product => {
                            const img = document.createElement('img');
                            img.src = product.image_path || 'images/icon/placeholder.jpg';
                            img.alt = product.name;
                            img.style.cssText = 'width: 100%; height: 100%; object-fit: cover; cursor: pointer;';
                            img.onclick = () => window.location.href = `index.php?id=${product.id}`;
                            topProductsBannerGrid.appendChild(img);
                        });
                    }
                } else {
                    if (section) section.style.setProperty('display', 'none', 'important');
                    // Hide top products banner if no top products
                    if (topProductsBannerGrid) {
                        topProductsBannerGrid.parentElement.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading top products:', error);
                if (section) section.style.display = 'none';
                // Hide top products banner on error
                const bannerGrid = document.getElementById('topProductsBannerGrid');
                if (bannerGrid && bannerGrid.parentElement) {
                    bannerGrid.parentElement.style.display = 'none';
                }
            });
    }

    // Load discount products from admin inventory
    function loadDiscountProducts() {
        const discountProductsGrid = document.getElementById('discountProductsGrid');
        const moreDiscountsGrid = document.getElementById('moreDiscountsGrid');
        const mobileDiscountGrid = document.getElementById('mobileDiscountGrid');
        const discountProductsBannerGrid = document.getElementById('discountProductsBannerGrid');

        fetchGowns()
            .then(data => {
                if (data && data.length > 0) {
                    // Additional client-side filter: exclude out-of-stock products
                    const inStockData = data.filter(product => {
                        const stock = parseInt(product.stock || 0);
                        return stock > 0;
                    });
                    
                    // Filter products with discounts
                    const discountProducts = inStockData.filter(product => product.discount && product.discount > 0);

                    if (discountProducts.length > 0) {
                        // Cache all discount products for category filtering
                        allDiscountProducts = discountProducts;

                        // Load mobile products (all discount products for horizontal scrolling)
                        if (mobileDiscountGrid) {
                            filterMobileDiscounts();
                        }

                        // Load discount banner for laptop layout
                        if (discountProductsBannerGrid) {
                            const bannerProducts = discountProducts.slice(0, 4); // Show 4 products in banner
                            discountProductsBannerGrid.innerHTML = '';
                            bannerProducts.forEach(product => {
                                const img = document.createElement('img');
                                img.src = product.image_path || 'images/icon/placeholder.jpg';
                                img.alt = product.name;
                                img.style.cssText = 'width: 100%; height: 100%; object-fit: cover; cursor: pointer;';
                                img.onclick = () => window.location.href = `index.php?id=${product.id}`;
                                discountProductsBannerGrid.appendChild(img);
                            });
                        }

                        // Load desktop products
                        if (discountProductsGrid) {
                            // First 6 products for main grid
                            const firstSixDiscounts = discountProducts.slice(0, 6);
                            displayDiscountProducts(firstSixDiscounts);

                            // Remaining products for "more" section
                            const remainingDiscounts = discountProducts.slice(6);
                            if (remainingDiscounts.length > 0) {
                                displayMoreDiscounts(remainingDiscounts);
                            } else {
                                // Hide toggle icon if no more products
                                const toggleIcon = document.getElementById('discountToggleIcon');
                                if (toggleIcon) {
                                    toggleIcon.style.display = 'none';
                                }
                            }
                        }
                    } else {
                        // Hide discount section if no discount products
                        const discountSection = document.querySelector('.discount-products-section');
                        if (discountSection) {
                            discountSection.style.setProperty('display', 'none', 'important');
                        }
                        // Hide discount banner if no discount products
                        if (discountProductsBannerGrid) {
                            discountProductsBannerGrid.parentElement.style.display = 'none';
                        }
                    }
                } else {
                    // Hide discount section if no products
                    const discountSection = document.querySelector('.discount-products-section');
                    if (discountSection) {
                        discountSection.style.setProperty('display', 'none', 'important');
                    }
                    // Hide discount banner if no products
                    if (discountProductsBannerGrid) {
                        discountProductsBannerGrid.parentElement.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading discount products:', error);
                // Hide discount section on error
                const discountSection = document.querySelector('.discount-products-section');
                if (discountSection) {
                    discountSection.style.display = 'none';
                }
                // Hide discount banner on error
                const bannerGrid = document.getElementById('discountProductsBannerGrid');
                if (bannerGrid && bannerGrid.parentElement) {
                    bannerGrid.parentElement.style.display = 'none';
                }
            });
    }

    // Active mobile category tracking
    let activeMobileCategory = 'all';

    // All discount products cache (populated by loadDiscountProducts)
    let allDiscountProducts = [];

    function filterMobileCategory(catName, tabElement) {
        activeMobileCategory = (catName || 'all').toLowerCase();

        const tabs = document.querySelectorAll('.mobile-category-tab');
        tabs.forEach(t => t.classList.remove('active'));

        let activeTab = tabElement;
        if (activeTab) {
            activeTab.classList.add('active');
        } else {
            tabs.forEach(t => {
                if ((t.getAttribute('data-category') || '').toLowerCase() === activeMobileCategory) {
                    t.classList.add('active');
                    activeTab = t;
                }
            });
        }

        // Auto-scroll the clicked tab into view (centered), like purchase.php
        if (activeTab) {
            activeTab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }

        // Show banner only on "All", hide it on specific category tabs
        const bannerWrapper = document.getElementById('bannerWrapper');
        if (bannerWrapper) {
            if (activeMobileCategory === 'all') {
                bannerWrapper.style.opacity = '1';
                bannerWrapper.style.maxHeight = '200px';
                bannerWrapper.style.margin = '10px 0';
                bannerWrapper.style.pointerEvents = '';
            } else {
                bannerWrapper.style.opacity = '0';
                bannerWrapper.style.maxHeight = '0';
                bannerWrapper.style.margin = '0';
                bannerWrapper.style.pointerEvents = 'none';
            }
        }

        // Filter mobile top products section by selected category
        filterMobileTopProducts();

        // Filter mobile discount section by selected category
        filterMobileDiscounts();

        filterProducts();
    }

    // Filter the mobile Top Products carousel by active category
    function filterMobileTopProducts() {
        const mobileTopProductsGrid = document.getElementById('mobileTopProductsGrid');
        const topSection = document.querySelector('.top-products-section');
        if (!mobileTopProductsGrid) return;

        if (!allTopProducts || allTopProducts.length === 0) {
            if (topSection) {
                topSection.style.setProperty('display', 'none', 'important');
            }
            return;
        }

        const filtered = (!activeMobileCategory || activeMobileCategory === 'all')
            ? allTopProducts
            : allTopProducts.filter(p =>
                (p.category || '').toLowerCase().includes(activeMobileCategory)
            );

        // Re-render mobile top products grid
        mobileTopProductsGrid.innerHTML = '';
        filtered.forEach((product, idx) => {
            mobileTopProductsGrid.appendChild(createMobileTopProductCard(product, idx));
        });

        // Show/hide the whole section based on results
        if (topSection) {
            if (filtered.length === 0) {
                topSection.style.setProperty('display', 'none', 'important');
            } else {
                topSection.style.setProperty('display', 'block', 'important');
            }
        }
    }

    // Filter the mobile Special Discounts carousel by active category
    function filterMobileDiscounts() {
        const mobileDiscountGrid = document.getElementById('mobileDiscountGrid');
        const discountSection = document.querySelector('.discount-products-section');
        if (!mobileDiscountGrid) return;

        if (!allDiscountProducts || allDiscountProducts.length === 0) {
            if (discountSection) {
                discountSection.style.setProperty('display', 'none', 'important');
            }
            return;
        }

        const filtered = (!activeMobileCategory || activeMobileCategory === 'all')
            ? allDiscountProducts
            : allDiscountProducts.filter(p =>
                (p.category || '').toLowerCase().includes(activeMobileCategory)
            );

        // Re-render mobile discount grid
        mobileDiscountGrid.innerHTML = '';
        filtered.forEach(product => {
            mobileDiscountGrid.appendChild(createMobileDiscountProductCard(product));
        });

        // Show/hide the whole section based on results
        if (discountSection) {
            if (filtered.length === 0) {
                discountSection.style.setProperty('display', 'none', 'important');
            } else {
                discountSection.style.setProperty('display', 'block', 'important');
            }
        }
    }

    function populateMobileCategoryTabs(categories) {
        const container = document.getElementById('mobileCategoryBar');
        if (!container) return;

        let categoryNames = [];
        if (Array.isArray(categories) && categories.length > 0) {
            categories.forEach(cat => {
                const name = typeof cat === 'string' ? cat : (cat.category_name || cat.category);
                if (name && !categoryNames.some(c => c.toLowerCase() === name.toLowerCase())) {
                    categoryNames.push(name);
                }
            });
        }

        // Fallbacks if empty
        if (categoryNames.length === 0) {
            categoryNames = ['Ballgown', 'Gown'];
        }

        container.innerHTML = '';

        // "All" tab
        const allTab = document.createElement('div');
        allTab.className = 'mobile-category-tab' + (activeMobileCategory === 'all' ? ' active' : '');
        allTab.setAttribute('data-category', 'all');
        allTab.textContent = 'All';
        allTab.onclick = function() { filterMobileCategory('all', this); };
        container.appendChild(allTab);

        // Category tabs
        categoryNames.forEach(cat => {
            const tab = document.createElement('div');
            const catLower = cat.toLowerCase();
            tab.className = 'mobile-category-tab' + (activeMobileCategory === catLower ? ' active' : '');
            tab.setAttribute('data-category', cat);
            tab.textContent = cat;
            tab.onclick = function() { filterMobileCategory(cat, this); };
            container.appendChild(tab);
        });
    }

    // Load categories from admin inventory
    function loadCategories() {
        const mobileCategoryGrid = document.getElementById('mobileCategoryGrid');
        
        // Fetch categories with images using cached fetch
        fetchCategories()
            .then(categories => {
                populateMobileCategoryTabs(categories);
                if (categories && categories.length > 0) {
                    // Load categories in horizontal scroll
                    if (mobileCategoryGrid) {
                        displayMobileCategories(categories);
                    }
                } else {
                    // Hide categories section if no categories
                    const categoriesSection = document.querySelector('.categories-section');
                    if (categoriesSection) {
                        categoriesSection.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading categories:', error);
                populateMobileCategoryTabs([]);
                // Hide categories section on error
                const categoriesSection = document.querySelector('.categories-section');
                if (categoriesSection) {
                    categoriesSection.style.display = 'none';
                }
            });
    }

    // Display mobile discount products in horizontal scroll
    function displayMobileDiscountProducts(products) {
        const mobileDiscountGrid = document.getElementById('mobileDiscountGrid');
        mobileDiscountGrid.innerHTML = '';

        products.forEach(product => {
            const discountCard = createMobileDiscountProductCard(product);
            mobileDiscountGrid.appendChild(discountCard);
        });
    }

    // Display categories - first 10 in first row, rest in more container
    function displayMobileCategories(categories) {
        const mobileCategoryGrid = document.getElementById('mobileCategoryGrid');
        const moreCategoriesGrid = document.getElementById('moreCategoriesGrid');
        mobileCategoryGrid.innerHTML = '';
        if (moreCategoriesGrid) moreCategoriesGrid.innerHTML = '';

        const firstRow = categories.slice(0, 10);
        const moreCategories = categories.slice(10);

        firstRow.forEach(category => {
            const categoryCard = createMobileCategoryCard(category);
            mobileCategoryGrid.appendChild(categoryCard);
        });

        if (moreCategoriesGrid && moreCategories.length > 0) {
            moreCategories.forEach(category => {
                const categoryCard = createMobileCategoryCard(category);
                moreCategoriesGrid.appendChild(categoryCard);
            });
        }

        // Hide toggle icon if no more categories
        const toggleIcon = document.getElementById('categoryToggleIcon');
        if (toggleIcon) {
            toggleIcon.style.display = moreCategories.length > 0 ? '' : 'none';
        }
    }

    // Toggle more categories (desktop)
    function toggleMoreCategories() {
        const moreContainer = document.getElementById('moreCategoriesContainer');
        const toggleIcon = document.getElementById('categoryToggleIcon');

        if (moreContainer.style.maxHeight === '0px' || moreContainer.style.maxHeight === '') {
            moreContainer.style.maxHeight = moreContainer.scrollHeight + 'px';
            toggleIcon.style.transform = 'rotate(180deg)';
        } else {
            moreContainer.style.maxHeight = '0px';
            toggleIcon.style.transform = 'rotate(0deg)';
        }
    }

    // Create mobile category card element (image with category name overlay)
    function createMobileCategoryCard(category) {
        const card = document.createElement('div');
        card.className = 'mobile-discount-product-card skeleton-fade-in';
        card.onclick = () => goToCategoryPage(category.category_name);
        card.style.position = 'relative';

        card.innerHTML = `
            <div class="mobile-discount-product-image">
                ${category.category_image ?
                    `<img src="${category.category_image}" alt="${category.category_name}">` :
                    `<div style="width: 100%; height: 100%; background: #f8f8f8; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-folder" style="font-size: 1.5rem; color: #ccc;"></i>
                    </div>`
                }
                <div class="category-card-overlay">
                    <div class="category-card-title">${category.category_name}</div>
                </div>
            </div>
        `;

        return card;
    }

    // Go to category page
    function goToCategoryPage(category) {
        // Navigate to product categories page with category filter
        window.location.href = `product_categories.php?category=${encodeURIComponent(category)}`;
    }

    // Create mobile discount product card element
    function createMobileDiscountProductCard(product) {
        const card = document.createElement('div');
        card.className = 'mobile-discount-product-card skeleton-fade-in';
        card.onclick = () => viewProduct(product.id);

        const categoryUpper = (product.category || '').toUpperCase();
        const colorText = product.color ? ((product.color.includes(',') ? 'Colors: ' : 'Color: ') + product.color) : '';

        card.innerHTML = `
            <div class="mobile-discount-product-image" style="position: relative; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                ${product.image_path ?
                    `<img src="${product.image_path}" alt="${product.name}" style="width: 100%; height: 100%; object-fit: cover; padding: 0;">` :
                    (product.model_path ?
                        `<model-viewer
                            src="${product.model_path}"
                            alt="${product.name}"
                            auto-rotate
                            camera-orbit="auto 90deg auto"
                            disable-pan
                            disable-zoom
                            min-camera-orbit="auto 90deg auto"
                            max-camera-orbit="auto 90deg auto"
                            style="width: 100%; height: 100%; display: block; margin: 0 auto;">
                            <div slot="poster" class="model-skeleton-poster">
                                <div class="model-skeleton-shimmer"></div>
                            </div>
                        </model-viewer>` :
                        `<div style="width: 100%; height: 100%; background: #1e1e1e; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-tshirt" style="font-size: 1.8rem; color: #666;"></i>
                        </div>`)
                }
                ${product.discount > 0 ? `<div class="mobile-discount-product-discount">-${Math.round(product.discount)}%</div>` : ''}
            </div>
            <div class="mobile-discount-product-name" style="text-align: left; left: 6px; right: 6px; padding: 0;">${product.name}</div>
            ${categoryUpper ? `<div class="mobile-discount-product-category" style="position: absolute; bottom: 15px; left: 6px; right: 6px; text-align: left; font-size: 0.56rem; font-weight: 700; color: #c5a059; text-transform: uppercase; letter-spacing: 0.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; z-index: 2;">${categoryUpper}</div>` : ''}
            ${colorText ? `<div class="mobile-discount-product-color" style="position: absolute; bottom: 3px; left: 6px; right: 6px; text-align: left; font-size: 0.58rem; color: #b8b8b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; z-index: 2;">${colorText}</div>` : ''}
        `;

        return card;
    }

    // Create mobile top product card element
    function createMobileTopProductCard(product, index) {
        const card = document.createElement('div');
        card.className = 'mobile-discount-product-card top-product-card skeleton-fade-in';
        card.onclick = () => viewProduct(product.id);

        const categoryUpper = (product.category || '').toUpperCase();
        const colorText = product.color ? ((product.color.includes(',') ? 'Colors: ' : 'Color: ') + product.color) : '';

        card.innerHTML = `
            <div class="mobile-discount-product-image" style="position: relative; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                ${product.image_path ?
                    `<img src="${product.image_path}" alt="${product.name}" style="width: 100%; height: 100%; object-fit: cover; padding: 0;">` :
                    (product.model_path ?
                        `<model-viewer
                            src="${product.model_path}"
                            alt="${product.name}"
                            auto-rotate
                            camera-orbit="auto 90deg auto"
                            disable-pan
                            disable-zoom
                            min-camera-orbit="auto 90deg auto"
                            max-camera-orbit="auto 90deg auto"
                            style="width: 100%; height: 100%; display: block; margin: 0 auto;">
                            <div slot="poster" class="model-skeleton-poster">
                                <div class="model-skeleton-shimmer"></div>
                            </div>
                        </model-viewer>` :
                        `<div style="width: 100%; height: 100%; background: #1e1e1e; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-tshirt" style="font-size: 1.8rem; color: #666;"></i>
                        </div>`)
                }
                ${product.discount > 0 ? `<div class="mobile-discount-product-discount">-${Math.round(product.discount)}%</div>` : ''}
                ${index !== undefined ? `<div class="top-product-badge"><i class="fas fa-crown" style="font-size: 0.55rem;"></i> #${index + 1}</div>` : ''}
            </div>
            <div class="mobile-discount-product-name" style="text-align: left; left: 6px; right: 6px; padding: 0;">${product.name}</div>
            ${categoryUpper ? `<div class="mobile-discount-product-category" style="position: absolute; bottom: 15px; left: 6px; right: 6px; text-align: left; font-size: 0.56rem; font-weight: 700; color: #c5a059; text-transform: uppercase; letter-spacing: 0.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; z-index: 2;">${categoryUpper}</div>` : ''}
            ${colorText ? `<div class="mobile-discount-product-color" style="position: absolute; bottom: 3px; left: 6px; right: 6px; text-align: left; font-size: 0.58rem; color: #b8b8b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; z-index: 2;">${colorText}</div>` : ''}
        `;

        return card;
    }

    // Create desktop top product card element (centered 3D, left-aligned name, category & color)
    function createTopProductCard(product, index) {
        const card = document.createElement('div');
        card.className = 'discount-product-card top-product-card skeleton-fade-in';
        card.onclick = () => viewProduct(product.id);

        const categoryUpper = (product.category || '').toUpperCase();
        const colorText = product.color ? ((product.color.includes(',') ? 'Colors: ' : 'Color: ') + product.color) : '';

        card.innerHTML = `
            <div class="discount-product-image" style="position: relative; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                ${product.image_path ?
                    `<img src="${product.image_path}" alt="${product.name}" style="width: 100%; height: 100%; object-fit: cover; padding: 0;">` :
                    (product.model_path ? 
                        `<model-viewer
                            src="${product.model_path}"
                            alt="${product.name}"
                            auto-rotate
                            camera-orbit="auto 90deg auto"
                            disable-pan
                            disable-zoom
                            min-camera-orbit="auto 90deg auto"
                            max-camera-orbit="auto 90deg auto"
                            style="width: 100%; height: 100%; display: block; margin: 0 auto;">
                            <div slot="poster" class="model-skeleton-poster">
                                <div class="model-skeleton-shimmer"></div>
                            </div>
                        </model-viewer>` :
                        `<div style="width: 100%; height: 100%; background: #f8f8f8; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-tshirt" style="font-size: 2rem; color: #ccc;"></i>
                        </div>`)
                }
                ${product.discount > 0 ? `<div class="discount-product-discount">-${Math.round(product.discount)}%</div>` : ''}
            </div>
            <div class="discount-product-name" style="text-align: left; left: 10px; right: 10px; padding: 0;">${product.name}</div>
            ${categoryUpper ? `<div class="discount-product-category" style="position: absolute; bottom: 20px; left: 10px; right: 10px; text-align: left; font-size: 0.7rem; font-weight: 700; color: #c5a059; text-transform: uppercase; letter-spacing: 0.6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; z-index: 2;">${categoryUpper}</div>` : ''}
            ${colorText ? `<div class="discount-product-color" style="position: absolute; bottom: 6px; left: 10px; right: 10px; text-align: left; font-size: 0.72rem; color: #b8b8b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; z-index: 2;">${colorText}</div>` : ''}
        `;

        return card;
    }

    // Display mobile top products in horizontal scroll
    function displayMobileTopProducts(products) {
        const mobileTopGrid = document.getElementById('mobileTopProductsGrid');
        if (!mobileTopGrid) return;
        mobileTopGrid.innerHTML = '';

        products.forEach((product, idx) => {
            const topCard = createMobileTopProductCard(product, idx);
            mobileTopGrid.appendChild(topCard);
        });
    }

    // Display desktop top products in grid
    function displayTopProducts(products) {
        const topProductsGrid = document.getElementById('topProductsGrid');
        if (!topProductsGrid) return;
        topProductsGrid.innerHTML = '';

        products.forEach((product, idx) => {
            const topCard = createTopProductCard(product, idx);
            topProductsGrid.appendChild(topCard);
        });
    }

    // Display more top products in grid
    function displayMoreTopProducts(products) {
        const moreTopGrid = document.getElementById('moreTopProductsGrid');
        if (!moreTopGrid) return;
        moreTopGrid.innerHTML = '';

        products.forEach((product, idx) => {
            const topCard = createTopProductCard(product, idx + 6);
            moreTopGrid.appendChild(topCard);
        });
    }

    // Toggle more top products section
    function toggleMoreTopProducts() {
        const moreContainer = document.getElementById('moreTopProductsContainer');
        const toggleIcon = document.getElementById('topProductsToggleIcon');

        if (!moreContainer || !toggleIcon) return;

        if (moreContainer.style.maxHeight === '0px' || moreContainer.style.maxHeight === '') {
            moreContainer.style.maxHeight = moreContainer.scrollHeight + 'px';
            toggleIcon.style.transform = 'rotate(180deg)';
        } else {
            moreContainer.style.maxHeight = '0px';
            toggleIcon.style.transform = 'rotate(0deg)';
        }
    }

    // Display mobile discount products in horizontal scroll
    function displayMobileDiscountProducts(products) {
        const mobileDiscountGrid = document.getElementById('mobileDiscountGrid');
        if (!mobileDiscountGrid) return;
        mobileDiscountGrid.innerHTML = '';

        products.forEach(product => {
            const discountCard = createMobileDiscountProductCard(product);
            mobileDiscountGrid.appendChild(discountCard);
        });
    }

    // Display discount products in grid
    function displayDiscountProducts(products) {
        const discountProductsGrid = document.getElementById('discountProductsGrid');
        discountProductsGrid.innerHTML = '';

        products.forEach(product => {
            const discountCard = createDiscountProductCard(product);
            discountProductsGrid.appendChild(discountCard);
        });
    }

    // Display more discount products in grid
    function displayMoreDiscounts(products) {
        const moreDiscountsGrid = document.getElementById('moreDiscountsGrid');
        moreDiscountsGrid.innerHTML = '';

        products.forEach(product => {
            const discountCard = createDiscountProductCard(product);
            moreDiscountsGrid.appendChild(discountCard);
        });
    }

    // Toggle more discounts section
    function toggleMoreDiscounts() {
        const moreContainer = document.getElementById('moreDiscountsContainer');
        const toggleIcon = document.getElementById('discountToggleIcon');

        if (moreContainer.style.maxHeight === '0px' || moreContainer.style.maxHeight === '') {
            // Show more discounts
            moreContainer.style.maxHeight = moreContainer.scrollHeight + 'px';
            toggleIcon.style.transform = 'rotate(180deg)';
        } else {
            // Hide more discounts
            moreContainer.style.maxHeight = '0px';
            toggleIcon.style.transform = 'rotate(0deg)';
        }
    }

    // Create discount product card element
    function createDiscountProductCard(product) {
        const card = document.createElement('div');
        card.className = 'discount-product-card skeleton-fade-in';
        card.onclick = () => viewProduct(product.id);

        const categoryUpper = (product.category || '').toUpperCase();
        const colorText = product.color ? ((product.color.includes(',') ? 'Colors: ' : 'Color: ') + product.color) : '';

        card.innerHTML = `
            <div class="discount-product-image">
                ${product.image_path ?
                    `<img src="${product.image_path}" alt="${product.name}" style="width: 100%; height: 100%; object-fit: cover; padding: 0;">` :
                    (product.model_path ? 
                        `<model-viewer
                            src="${product.model_path}"
                            alt="${product.name}"
                            auto-rotate
                            camera-orbit="auto 90deg auto"
                            disable-pan
                            disable-zoom
                            min-camera-orbit="auto 90deg auto"
                            max-camera-orbit="auto 90deg auto"
                            style="width: 100%; height: 100%;">
                            <div slot="poster" class="model-skeleton-poster">
                                <div class="model-skeleton-shimmer"></div>
                            </div>
                        </model-viewer>` :
                        `<div style="width: 100%; height: 100%; background: #f8f8f8; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-tshirt" style="font-size: 2rem; color: #ccc;"></i>
                        </div>`)
                }
                ${product.discount > 0 ? `<div class="discount-product-discount">-${Math.round(product.discount)}%</div>` : ''}
            </div>
            <div class="discount-product-name" style="text-align: left; left: 10px; right: 10px; padding: 0;">${product.name}</div>
            ${categoryUpper ? `<div class="discount-product-category" style="position: absolute; bottom: 20px; left: 10px; right: 10px; text-align: left; font-size: 0.7rem; font-weight: 700; color: #c5a059; text-transform: uppercase; letter-spacing: 0.6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; z-index: 2;">${categoryUpper}</div>` : ''}
            ${colorText ? `<div class="discount-product-color" style="position: absolute; bottom: 6px; left: 10px; right: 10px; text-align: left; font-size: 0.72rem; color: #b8b8b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; z-index: 2;">${colorText}</div>` : ''}
        `;

        return card;
    }

    // Dynamic placeholder rotation
    function startPlaceholderRotation(products) {
        const searchInput = document.getElementById('searchInput');
        const mobileSearchInput = document.getElementById('mobileSearchInput');
        if ((!searchInput && !mobileSearchInput) || !products || products.length === 0) return;

        const staticText = 'Search...';
        let productIndex = 0;
        let staticShown = false;
        
        function updatePlaceholder() {
            // Fade out
            if (searchInput) searchInput.style.opacity = '0';
            if (mobileSearchInput) mobileSearchInput.style.opacity = '0';
            
            setTimeout(() => {
                // Change text
                let text;
                if (!staticShown) {
                    text = staticText;
                    staticShown = true;
                } else {
                    text = products[productIndex].name;
                    productIndex = (productIndex + 1) % products.length;
                }
                if (searchInput) searchInput.placeholder = text;
                if (mobileSearchInput) mobileSearchInput.placeholder = text;
                
                // Fade in
                if (searchInput) searchInput.style.opacity = '1';
                if (mobileSearchInput) mobileSearchInput.style.opacity = '1';
            }, 300); // Wait for fade out to complete
        }

        // Start rotation
        updatePlaceholder();
        setInterval(updatePlaceholder, 4000); // Change every 4 seconds
    }

    // Display products in grid
    function displayProducts(products) {
        const productsGrid = document.getElementById('productsGrid');
        const viewAllContainer = document.getElementById('viewAllContainer');
        const viewAllBtn = document.getElementById('viewAllBtn');
        if (!productsGrid) return;
        productsGrid.innerHTML = '';

        // Additional client-side filter: exclude out-of-stock products as safety measure
        const inStockProducts = products.filter(product => {
            const stock = parseInt(product.stock || 0);
            return stock > 0;
        });

        // Shuffle products to randomize order
        const shuffledProducts = [...inStockProducts].sort(() => Math.random() - 0.5);

        // Always store all products globally for search and view-all functionality
        window.allProducts = shuffledProducts;
        window.showingAllProducts = false;

        // Check if mobile - show all products on mobile
        const isMobile = window.innerWidth <= 768;

        if (isMobile) {
            window.showingAllProducts = true;
            // Show all products on mobile
            shuffledProducts.forEach(product => {
                const productCard = createProductCard(product);
                productsGrid.appendChild(productCard);
            });
            if (viewAllContainer) viewAllContainer.style.display = 'none';
        } else {
            // Laptop/Desktop: Show only 5 rows (20 products with 4 per row)
            const initialCount = 20; // 5 rows × 4 per row

            // Show only initial products
            const productsToShow = shuffledProducts.slice(0, initialCount);
            productsToShow.forEach(product => {
                const productCard = createProductCard(product);
                productsGrid.appendChild(productCard);
            });

            // Show view all button if there are more products
            if (shuffledProducts.length > initialCount) {
                if (viewAllContainer) {
                    viewAllContainer.style.display = 'block';
                    if (viewAllBtn) {
                        viewAllBtn.onclick = function() {
                            showAllProducts();
                        };
                    }
                }
            } else {
                if (viewAllContainer) viewAllContainer.style.display = 'none';
            }
        }

        // If there's an existing search input value, filter immediately
        const desktopSearch = document.getElementById('searchInput') ? document.getElementById('searchInput').value.trim() : '';
        const mobileSearch = document.getElementById('mobileSearchInput') ? document.getElementById('mobileSearchInput').value.trim() : '';
        if (desktopSearch || mobileSearch) {
            filterProducts();
        }
    }

    // Show all products when view all is clicked
    function showAllProducts() {
        const productsGrid = document.getElementById('productsGrid');
        const viewAllContainer = document.getElementById('viewAllContainer');
        if (!productsGrid || !window.allProducts) return;

        // Clear products grid before rendering all products to prevent duplicates
        productsGrid.innerHTML = '';
        window.allProducts.forEach(product => {
            const productCard = createProductCard(product);
            productsGrid.appendChild(productCard);
        });

        window.showingAllProducts = true;
        if (viewAllContainer) viewAllContainer.style.display = 'none';
    }

    // Create product card element
    function createProductCard(product) {
        const card = document.createElement('div');
        card.className = 'product-card skeleton-fade-in';
        card.onclick = () => viewProduct(product.id);

        let imageContent = '';

        if (product.image_path) {
            // Display uploaded image first
            imageContent = `
                <div class="product-image">
                    <img src="${product.image_path}" alt="${product.name}" style="width: 100%; height: 100%; object-fit: cover; padding: 0;">
                </div>
            `;
        } else if (product.model_path) {
            // 3D Model viewer if no image available
            imageContent = `
                <div class="product-image">
                    <model-viewer
                        src="${product.model_path}"
                        alt="${product.name}"
                        auto-rotate
                        camera-orbit="auto 90deg auto"
                        disable-pan
                        disable-zoom
                        min-camera-orbit="auto 90deg auto"
                        max-camera-orbit="auto 90deg auto">
                        <div slot="poster" class="model-skeleton-poster">
                            <div class="model-skeleton-shimmer"></div>
                        </div>
                    </model-viewer>
                    <button class="view-3d-btn" onclick="event.stopPropagation(); view3DModel('${product.model_path}')">
                        <i class="fas fa-cube"></i>
                    </button>
                </div>
            `;
        } else {
            // Placeholder
            imageContent = `
                <div class="product-image">
                    <div class="product-placeholder">
                        <i class="fas fa-tshirt"></i>
                        <div>${product.name}</div>
                        <div style="font-size: 0.9rem; opacity: 0.7;">No Image</div>
                    </div>
                </div>
            `;
        }

        const categoryUpper = (product.category || '').toUpperCase();
        const colorText = product.color ? ((product.color.includes(',') ? 'Colors: ' : 'Color: ') + product.color) : '';

        card.innerHTML = `
            ${imageContent}
            <div class="product-info">
                <div class="product-name">${product.name}</div>
                ${categoryUpper ? `<div class="product-category">${categoryUpper}</div>` : ''}
                ${colorText ? `<div class="product-color">${colorText}</div>` : ''}
                <div class="product-size" style="display: none;">${product.size || ''}</div>
            </div>
            ${product.discount && product.discount > 0 ? `<div class="discount-badge">-${Math.round(product.discount)}%</div>` : ''}
        `;

        return card;
    }

    // View product details — opens product in overlay iframe (no page reload)
    function viewProduct(productId) {
        // Check if user is currently viewing a product and wants to go back
        const currentProductId = '<?php echo isset($_GET['id']) ? $_GET['id'] : 0; ?>';
        if (currentProductId > 0) {
            // Show reminder dialog
            showBackReminder(productId);
        } else {
            openProductOverlay(productId);
        }
    }

    // Show back reminder dialog
    function showBackReminder(newProductId) {
        // Create reminder modal
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        `;

        const dialog = document.createElement('div');
        dialog.style.cssText = `
            background: #2a2a2a;
            border: 2px solid #7c6300;
            border-radius: 12px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        `;

        dialog.innerHTML = `
            <h3 style="color: #ffd700; margin: 0 0 15px 0; font-size: 18px;">Reminder</h3>
            <p style="color: #fff; margin: 0 0 25px 0; font-size: 14px; line-height: 1.5;">You are currently viewing a product. Do you want to go back and view another product?</p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button id="cancelBackBtn" style="background: #666; color: white; border: none; padding: 10px 25px; font-size: 14px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;">Cancel</button>
                <button id="confirmBackBtn" style="background: #7c6300; color: white; border: none; padding: 10px 25px; font-size: 14px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;">OK</button>
            </div>
        `;

        modal.appendChild(dialog);
        document.body.appendChild(modal);

        // Handle cancel button
        document.getElementById('cancelBackBtn').onclick = function() {
            document.body.removeChild(modal);
        };

        // Handle confirm button
        document.getElementById('confirmBackBtn').onclick = function() {
            document.body.removeChild(modal);
            window.location.href = `index.php?id=${newProductId}`;
        };

        // Close on background click
        modal.onclick = function(e) {
            if (e.target === modal) {
                document.body.removeChild(modal);
            }
        };
    }

    // ===== Search Overlay =====
    let searchOverlayProducts = [];
    let searchOverlayLoaded = false;

    function preloadSearchOverlayProducts() {
        if (searchOverlayLoaded) return;
        fetchGowns()
            .then(data => {
                searchOverlayProducts = Array.isArray(data) ? data : [];
                searchOverlayLoaded = true;
                renderSearchOverlayProducts(searchOverlayProducts);
            })
            .catch(error => console.error('Error loading search products:', error));
    }

    function openSearchOverlay() {
        const overlay = document.getElementById('searchOverlay');
        const desktopInput = document.getElementById('searchInput');
        const mobileInput = document.getElementById('searchOverlayInput');
        if (!overlay) return;

        preloadSearchOverlayProducts();

        overlay.classList.add('active');
        document.body.classList.add('search-overlay-open');
        
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            document.body.style.overflow = 'hidden';
            if (mobileInput) {
                if (desktopInput && desktopInput.value) mobileInput.value = desktopInput.value;
                mobileInput.focus({ preventScroll: true });
            }
        } else {
            document.body.style.overflow = '';
            if (desktopInput) {
                if (mobileInput && mobileInput.value && !desktopInput.value) desktopInput.value = mobileInput.value;
                desktopInput.focus({ preventScroll: true });
            }
        }
        filterSearchOverlay();
    }

    function closeSearchOverlay() {
        const overlay = document.getElementById('searchOverlay');
        if (!overlay) return;
        overlay.classList.remove('active');
        document.body.classList.remove('search-overlay-open');
        document.body.style.overflow = '';
        
        // Clear all search input values
        const searchInput = document.getElementById('searchInput');
        const mobileSearchInput = document.getElementById('mobileSearchInput');
        const searchOverlayInput = document.getElementById('searchOverlayInput');
        
        if (searchInput) searchInput.value = '';
        if (mobileSearchInput) mobileSearchInput.value = '';
        if (searchOverlayInput) searchOverlayInput.value = '';
        
        // Reset product display to show all products again
        filterProducts();
    }

    function handleDesktopSearchInput(el) {
        const val = el.value;
        const mobileInput = document.getElementById('searchOverlayInput');
        if (mobileInput) mobileInput.value = val;
        
        const overlay = document.getElementById('searchOverlay');
        if (overlay && !overlay.classList.contains('active')) {
            openSearchOverlay();
        } else {
            filterSearchOverlay();
        }
        
        clearTimeout(window.searchOverlayTimeout);
        window.searchOverlayTimeout = setTimeout(() => {
            const trimmed = val.trim();
            if (trimmed.length > 0) saveSearchOverlayTerm(trimmed);
        }, 700);

        filterProducts();
    }

    // Open the AI white overlay from inside the search overlay
    function openWhiteOverlayFromSearch() {
        closeSearchOverlay();
        setTimeout(() => centerLottieAnimation(), 150);
    }

    function renderSearchOverlayProducts(products) {
        const grid = document.getElementById('searchOverlayGrid');
        if (!grid) return;

        grid.innerHTML = '';

        if (products.length === 0) {
            grid.innerHTML = `
                <div class="search-overlay-empty-inner" style="grid-column: 1 / -1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px 20px; width: 100%;">
                    <dotlottie-wc src="https://lottie.host/7a9e1682-3342-49d1-a823-511f6b3f1fdc/x7SG6ZMpXp.lottie" style="width: 280px; height: 280px; max-width: 90vw;" autoplay loop></dotlottie-wc>
                    <p style="color: #888888; margin-top: 16px; font-size: 16px; font-weight: 500; font-family: 'Poppins', sans-serif;">No products found</p>
                </div>
            `;
        } else {
            products.forEach((product, idx) => {
                const card = createProductCard(product);
                card.style.animationDelay = `${Math.min(idx * 0.03, 0.35)}s`;
                grid.appendChild(card);
            });
        }
    }

    function filterSearchOverlay() {
        const desktopInput = document.getElementById('searchInput');
        const mobileInput = document.getElementById('searchOverlayInput');
        const term = (desktopInput && desktopInput.value.trim() ? desktopInput.value : (mobileInput ? mobileInput.value : '')).trim().toLowerCase();

        if (!term) {
            renderSearchOverlayProducts(searchOverlayProducts);
            return;
        }

        const filtered = searchOverlayProducts.filter(product => {
            const name = (product.name || '').toLowerCase();
            const category = (product.category || '').toLowerCase();
            return name.includes(term) || category.includes(term);
        });

        renderSearchOverlayProducts(filtered);
    }



    document.addEventListener('DOMContentLoaded', function() {
        preloadSearchOverlayProducts();

        const overlayInput = document.getElementById('searchOverlayInput');
        if (overlayInput) {
            overlayInput.addEventListener('input', function(e) {
                const desktopInput = document.getElementById('searchInput');
                if (desktopInput) desktopInput.value = e.target.value;
                filterSearchOverlay();
                filterProducts();

                clearTimeout(window.searchOverlayTimeout);
                window.searchOverlayTimeout = setTimeout(() => {
                    const value = e.target.value.trim();
                    if (value.length > 0) saveSearchOverlayTerm(value);
                }, 700);
            });
        }

        // Close search overlay when clicking outside the search boxes on laptop/desktop only
        document.addEventListener('click', function(e) {
            // Only apply outside-click close on desktop/laptop
            if (window.innerWidth <= 768) return;

            const overlay = document.getElementById('searchOverlay');
            if (!overlay || !overlay.classList.contains('active')) return;

            // If clicking inside the search input or top-nav search container, don't close
            if (e.target.closest('.search-box') || e.target.closest('.search-filter-container') || e.target.closest('#searchInput') || e.target.closest('.mobile-search-bar') || e.target.closest('#mobileSearchInput')) {
                return;
            }

            // If clicking inside the search overlay content itself (product grid or search results cards), don't close
            if (e.target.closest('#searchOverlayGrid') || e.target.closest('.product-card') || e.target.closest('.search-overlay-box') || e.target.closest('.search-overlay-topbar')) {
                return;
            }

            // If clicking other header action items
            if (e.target.closest('.ai-search-prompt') || e.target.closest('#whiteOverlay') || e.target.closest('#notificationDropdown') || e.target.closest('.mobile-top-search')) {
                return;
            }

            // Clicked outside -> close search overlay
            closeSearchOverlay();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSearchOverlay();
        });
    });

    // View 3D model in full screen
    function view3DModel(modelPath) {
        // Create modal for 3D view
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
        `;

        const viewer = document.createElement('model-viewer');
        viewer.setAttribute('bounds', 'tight');
        viewer.setAttribute('auto-rotate', '');
        viewer.setAttribute('rotation-per-second', '20deg');
        viewer.setAttribute('auto-rotate-delay', '1000');
        viewer.setAttribute('interpolation-decay', '100');
        viewer.setAttribute('camera-controls', '');
        viewer.setAttribute('disable-pan', '');
        viewer.setAttribute('disable-zoom', '');
        viewer.setAttribute('camera-orbit', 'auto 90deg 82%');
        viewer.setAttribute('min-camera-orbit', '-Infinity 90deg 82%');
        viewer.setAttribute('max-camera-orbit', 'Infinity 90deg 82%');
        viewer.setAttribute('touch-action', 'pan-y');
        viewer.style.cssText = `
            width: 80%;
            height: 80%;
            max-width: 800px;
            max-height: 600px;
        `;

        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.style.cssText = `
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            font-size: 18px;
            z-index: 10001;
        `;

        closeBtn.onclick = () => document.body.removeChild(modal);
        modal.appendChild(viewer);
        modal.appendChild(closeBtn);
        document.body.appendChild(modal);
    }

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
        filterProducts();
    });

    // Category filter functionality
    document.getElementById('categoryFilter').addEventListener('change', function(e) {
        filterProducts();
    });

    // Size filter functionality
    document.getElementById('sizeFilter').addEventListener('change', function(e) {
        filterProducts();
    });

    // Combined filter function
    function filterProducts() {
        const desktopSearch = document.getElementById('searchInput') ? document.getElementById('searchInput').value.toLowerCase().trim() : '';
        const mobileSearch = document.getElementById('mobileSearchInput') ? document.getElementById('mobileSearchInput').value.toLowerCase().trim() : '';
        const overlaySearch = document.getElementById('searchOverlayInput') ? document.getElementById('searchOverlayInput').value.toLowerCase().trim() : '';
        const searchTerm = desktopSearch || mobileSearch || overlaySearch;

        const categoryFilterEl = document.getElementById('categoryFilter');
        const desktopCategory = categoryFilterEl ? categoryFilterEl.value.toLowerCase().trim() : '';
        const selectedCategory = (typeof activeMobileCategory !== 'undefined' && activeMobileCategory !== 'all') ? activeMobileCategory : desktopCategory;

        const sizeFilterEl = document.getElementById('sizeFilter');
        const selectedSize = sizeFilterEl ? sizeFilterEl.value.toUpperCase().trim() : '';

        const productsGrid = document.getElementById('productsGrid');
        const viewAllContainer = document.getElementById('viewAllContainer');
        const viewAllBtn = document.getElementById('viewAllBtn');
        const noProductsMsg = document.getElementById('noProductsMessage');
        if (!productsGrid) return;

        const isMobile = window.innerWidth <= 768;
        const all = Array.isArray(window.allProducts) ? window.allProducts : [];

        // Fallback if window.allProducts not loaded yet
        if (all.length === 0) {
            const productCards = document.querySelectorAll('#productsGrid .product-card');
            let visibleCount = 0;
            productCards.forEach(card => {
                const productName = (card.querySelector('.product-name')?.textContent || '').toLowerCase();
                const productCategory = (card.querySelector('.product-category')?.textContent || '').toLowerCase();
                const productSizeEl = card.querySelector('.product-size');
                const productSize = (productSizeEl ? productSizeEl.textContent : '').toUpperCase();
                
                let matchesSearch = !searchTerm || productName.includes(searchTerm) || productCategory.includes(searchTerm);
                let matchesCategory = !selectedCategory || selectedCategory === 'all' || productCategory.includes(selectedCategory);
                let matchesSize = !selectedSize || productSize.includes(selectedSize);
                
                if (matchesSearch && matchesCategory && matchesSize) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            return;
        }

        const hasActiveFilter = Boolean(searchTerm || (selectedCategory && selectedCategory !== 'all') || selectedSize);

        if (hasActiveFilter) {
            // When filtering/searching: MUST search across ALL products in window.allProducts!
            // Even if "View All Products" is closed, ALL matching products from the catalog are found and shown!
            productsGrid.innerHTML = '';

            const matchedProducts = all.filter(product => {
                const name = (product.name || '').toLowerCase();
                const category = (product.category || '').toLowerCase();
                const size = (product.size || '').toUpperCase();

                const matchesSearch = !searchTerm || name.includes(searchTerm) || category.includes(searchTerm);
                const matchesCategory = !selectedCategory || selectedCategory === 'all' || category.includes(selectedCategory);
                const matchesSize = !selectedSize || size.includes(selectedSize);

                return matchesSearch && matchesCategory && matchesSize;
            });

            if (matchedProducts.length > 0) {
                matchedProducts.forEach(product => {
                    productsGrid.appendChild(createProductCard(product));
                });
                productsGrid.style.display = 'grid';
                if (noProductsMsg) noProductsMsg.style.display = 'none';
            } else {
                productsGrid.innerHTML = `
                    <div style="grid-column: 1 / -1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px 20px;">
                        <dotlottie-wc src="https://lottie.host/7a9e1682-3342-49d1-a823-511f6b3f1fdc/x7SG6ZMpXp.lottie" style="width: 240px; height: 240px; max-width: 90vw;" autoplay loop></dotlottie-wc>
                        <p style="color: #888; margin-top: 15px; font-size: 15px; font-weight: 500;">No products match your search</p>
                    </div>
                `;
                productsGrid.style.display = 'grid';
                if (noProductsMsg) noProductsMsg.style.display = 'none';
            }

            // Hide "View All Products" button while actively searching/filtering
            if (viewAllContainer) viewAllContainer.style.display = 'none';

        } else {
            // Search / filter is empty: Restore normal catalog display
            productsGrid.innerHTML = '';

            if (all.length === 0) {
                productsGrid.style.display = 'none';
                if (noProductsMsg) noProductsMsg.style.display = 'flex';
                if (viewAllContainer) viewAllContainer.style.display = 'none';
                return;
            }

            productsGrid.style.display = 'grid';
            if (noProductsMsg) noProductsMsg.style.display = 'none';

            if (isMobile || window.showingAllProducts) {
                all.forEach(product => {
                    productsGrid.appendChild(createProductCard(product));
                });
                if (viewAllContainer) viewAllContainer.style.display = 'none';
            } else {
                // Show initial 20 products
                const initialCount = 20;
                const productsToShow = all.slice(0, initialCount);
                productsToShow.forEach(product => {
                    productsGrid.appendChild(createProductCard(product));
                });

                if (all.length > initialCount) {
                    if (viewAllContainer) {
                        viewAllContainer.style.display = 'block';
                        if (viewAllBtn) {
                            viewAllBtn.onclick = function() {
                                showAllProducts();
                            };
                        }
                    }
                } else {
                    if (viewAllContainer) viewAllContainer.style.display = 'none';
                }
            }
        }
    }
    
    function showComingSoon(feature) {
        alert('coming soon, wait baby ko!');
    }
    
    // Dynamic search placeholder with product names from database
    let productNames = [];
    let currentProductIndex = 0;
    let showStaticFirst = true;
    const searchInput = document.getElementById('searchInput');
    const mobileSearchInput2 = document.getElementById('mobileSearchInput');
    
    // Fetch product names from database
    async function fetchProductNames() {
        try {
            const response = await fetch('get_product_names.php');
            if (response.ok) {
                const names = await response.json();
                productNames = names.length > 0 ? names : ["Elegant Evening Gown", "Classic Wedding Dress"];
                updateSearchPlaceholder();
            }
        } catch (error) {
            console.error('Error fetching product names:', error);
            // Fallback to default names if fetch fails
            productNames = ["Elegant Evening Gown", "Classic Wedding Dress", "Summer Floral Dress"];
            updateSearchPlaceholder();
        }
    }
    
    function updateSearchPlaceholder() {
        if (productNames.length > 0) {
            const staticText = 'Used Assistant AI or Seach';
            let text;
            if (showStaticFirst) {
                text = staticText;
                showStaticFirst = false;
            } else {
                text = productNames[currentProductIndex];
                currentProductIndex = (currentProductIndex + 1) % productNames.length;
            }
            
            // Add transition classes
            if (searchInput) searchInput.classList.add('placeholder-transition-out');
            if (mobileSearchInput2) mobileSearchInput2.classList.add('placeholder-transition-out');
            
            // Change text after transition out starts
            setTimeout(() => {
                if (searchInput) {
                    searchInput.placeholder = text;
                    searchInput.classList.remove('placeholder-transition-out');
                    searchInput.classList.add('placeholder-transition-in');
                    setTimeout(() => searchInput.classList.remove('placeholder-transition-in'), 300);
                }
                if (mobileSearchInput2) {
                    mobileSearchInput2.placeholder = text;
                    mobileSearchInput2.classList.remove('placeholder-transition-out');
                    mobileSearchInput2.classList.add('placeholder-transition-in');
                    setTimeout(() => mobileSearchInput2.classList.remove('placeholder-transition-in'), 300);
                }
            }, 300);
        }
    }
    
    // Start the placeholder rotation after fetching names
    fetchProductNames();
    setInterval(updateSearchPlaceholder, 3000);

    // Synchronize topbar authentication status and chat profile image
    syncTopbarAuthState();
    loadChatProfileImage();
</script>

<!-- ===== USER CHAT PANEL (Messenger-style) ===== -->
<div id="userChatPanel">
    <!-- Panel Header -->
    <div style="background:linear-gradient(135deg,#2a2a2a,#1a1a1a); border-bottom:1px solid #7c6300; padding:0 16px; height:52px; display:flex; align-items:center; gap:10px; cursor:pointer; user-select:none; box-sizing:border-box; flex-shrink:0;" onclick="handleChatHeaderClick()">
        <div style="position:relative; display:flex; align-items:center;">
            <img src="images/icon/JMB.png" alt="Admin" style="width:30px; height:30px; border-radius:50%; object-fit:cover; border:1.5px solid #ffd700;" onerror="this.src='images/icon/chat.png'">
            <span style="position:absolute; bottom:0; right:0; width:8px; height:8px; background:#44bb44; border-radius:50%; border:1.5px solid #1a1a1a;"></span>
            <span id="userChatHeaderBadge" style="display:none; position:absolute; top:-6px; right:-6px; background:linear-gradient(135deg,#ff3b30,#e60000); color:white; border-radius:50%; min-width:18px; height:18px; padding:0 3px; font-size:10px; font-weight:700; align-items:center; justify-content:center; line-height:18px; text-align:center; box-shadow:0 0 8px rgba(255,59,48,0.8); border:2px solid #1a1a1a; box-sizing:border-box;">0</span>
        </div>
        <div style="flex:1;">
            <div style="color:#ffd700; font-weight:700; font-size:14px; line-height:1.2;">Chat with Admin</div>
            <div id="chatAdminStatus" style="color:#aaa; font-size:11px; line-height:1.2;">Online · ClosetGo Support</div>
        </div>
        <!-- Desktop Minimize / Expand Toggle Icon (+ / −) with unread badge -->
        <div style="position:relative; display:flex; align-items:center;">
            <div id="chatPanelMinIcon" onclick="event.stopPropagation(); toggleChatPanelMinimize()" style="color:#ffd700; font-size:18px; font-weight:bold; cursor:pointer; padding:2px 8px; border-radius:4px; transition:background 0.2s;" onmouseover="this.style.background='#333'" onmouseout="this.style.background='transparent'">+</div>
            <span id="chatMinBadge" style="display:none; position:absolute; top:-8px; right:0px; background:linear-gradient(135deg,#ff3b30,#e60000); color:white; border-radius:50%; min-width:17px; height:17px; padding:0 3px; font-size:9px; font-weight:700; align-items:center; justify-content:center; line-height:17px; text-align:center; box-shadow:0 0 6px rgba(255,59,48,0.9); border:2px solid #1a1a1a; box-sizing:border-box;">0</span>
        </div>
        <!-- Mobile Close Button (✕) -->
        <div class="chat-panel-close-btn" onclick="event.stopPropagation(); closeMobileChat()" style="color:#aaa; font-size:18px; cursor:pointer; padding:4px 8px; border-radius:4px; transition:color 0.2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#aaa'">✕</div>
    </div>

    <!-- Login Prompt (shown when not logged in) -->
    <div id="chatLoginPrompt" style="display:none; flex:1; flex-direction:column; align-items:center; justify-content:center; padding:30px; text-align:center;">
        <img src="images/icon/JMB.png" style="width:56px; height:56px; border-radius:50%; object-fit:cover; border:2px solid #ffd700; margin-bottom:16px;" onerror="this.src='images/icon/chat.png'">
        <div style="color:#ccc; font-size:14px; margin-bottom:16px;">Please log in to chat with our admin.</div>
        <a href="login_user/login.php" style="background:linear-gradient(135deg,#7c6300,#b8960c); color:#fff; padding:10px 24px; border-radius:20px; text-decoration:none; font-size:13px; font-weight:600;">Log In</a>
    </div>

    <!-- Chat Body -->
    <div id="chatPanelBody" style="display:none; flex-direction:column; flex:1; overflow:hidden; position:relative;">
        <!-- Messages Area -->
        <div id="userChatMessages" style="flex:1; overflow-y:auto; padding:14px 12px; display:flex; flex-direction:column; gap:6px; scrollbar-width:thin; scrollbar-color:#333 transparent;">
            <div style="text-align:center; color:#666; font-size:12px; padding:20px 0;">Send a message to start chatting with admin.</div>
        </div>

        <!-- Attachment Preview Bar (shown when items are attached) -->
        <div id="chatAttachmentPreviewBar" style="display:none; padding:8px 12px; background:#202020; border-top:1px solid #7c6300; align-items:center; justify-content:space-between; gap:10px; z-index:11;">
            <div id="chatAttachmentPreviewContent" style="display:flex; align-items:center; gap:8px; flex:1; min-width:0; overflow:hidden;"></div>
            <button type="button" onclick="clearPendingChatAttachments()" title="Remove attachment" style="background:#2d2d2d; border:1px solid #444; border-radius:50%; width:24px; height:24px; color:#aaa; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; line-height:1; transition:all 0.2s;" onmouseover="this.style.color='#fff'; this.style.borderColor='#ff6b6b';" onmouseout="this.style.color='#aaa'; this.style.borderColor='#444';">✕</button>
        </div>

        <!-- Input Area -->
        <div style="padding:10px 12px; border-top:1px solid #2a2a2a; display:flex; gap:8px; align-items:flex-end; background:#1a1a1a; position:relative; z-index:10;">
            <div style="position:relative; display:flex; align-items:center;">
                <button type="button" id="chatAttachProductBtn" onclick="toggleChatProductPicker()" title="Attach Products, Cart or Purchase" style="background:#262626; border:1px solid #7c6300; border-radius:50%; width:36px; height:36px; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all 0.2s;" onmouseover="this.style.background='#333'; this.style.borderColor='#ffd700'; this.style.transform='scale(1.08)';" onmouseout="this.style.background='#262626'; this.style.borderColor='#7c6300'; this.style.transform='scale(1)';">
                    <img src="images/icon/purchase.png" alt="Attach" style="width:20px; height:20px; object-fit:contain;">
                </button>
                <span id="chatAttachBtnBadge" style="display:none; position:absolute; top:-4px; right:-4px; background:linear-gradient(135deg,#ffd700,#b8960c); color:#000; border-radius:50%; min-width:16px; height:16px; padding:0 3px; font-size:10px; font-weight:800; align-items:center; justify-content:center; line-height:16px; text-align:center; border:1.5px solid #1a1a1a; box-sizing:border-box;">0</span>
            </div>
            <textarea id="userChatInput" placeholder="Type a message..." rows="1" style="flex:1; background:#2a2a2a; border:1px solid #333; border-radius:20px; padding:9px 14px; color:#fff; font-size:13px; resize:none; outline:none; max-height:80px; overflow-y:auto; line-height:1.4; font-family:inherit;" onkeydown="handleUserChatKey(event)" oninput="this.style.height='auto'; this.style.height=Math.min(this.scrollHeight,80)+'px'"></textarea>
            <button onclick="sendUserMessage()" style="background:linear-gradient(135deg,#7c6300,#b8960c); border:none; border-radius:50%; width:36px; height:36px; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/></svg>
            </button>
        </div>
    </div>

    <!-- Product / Cart / Purchase Inquiry Selector Sheet (Full Overlay taking over whole userChatPanel) -->
    <div id="chatProductPickerSheet" style="display:none; position:absolute; inset:0; top:0; left:0; right:0; bottom:0; width:100%; height:100%; background:#161616; z-index:100; flex-direction:column; overflow:hidden; border-radius:inherit; box-sizing:border-box;">
        <!-- Picker Header (Replaces top bar completely) -->
        <div style="background:linear-gradient(135deg,#2a2a2a,#1a1a1a); border-bottom:1px solid #7c6300; padding:0 16px; height:52px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0; box-sizing:border-box;">
            <div style="color:#ffd700; font-weight:700; font-size:14px; display:flex; align-items:center; gap:10px; line-height:1.2;">
                <img src="images/icon/purchase.png" alt="Attach" style="width:20px; height:20px; object-fit:contain;">
                <span>Attach for Inquiry</span>
            </div>
            <div onclick="closeChatProductPicker()" title="Close" style="color:#aaa; font-size:18px; cursor:pointer; padding:4px 8px; border-radius:4px; line-height:1; transition:color 0.2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#aaa'">✕</div>
        </div>

        <!-- 3 Tabs: Products, Cart, Purchase -->
        <div style="display:flex; background:#121212; border-bottom:1px solid #2a2a2a; flex-shrink:0;">
            <div class="chat-picker-tab" id="chatPickerTabProducts" onclick="switchChatPickerTab('products')" style="flex:1; padding:10px 4px; text-align:center; color:#ffd700; font-size:12px; font-weight:700; cursor:pointer; border-bottom:2.5px solid #ffd700; transition:all 0.2s;">
                Products <span id="chatPickerCountProducts" style="font-size:10px; color:#888;"></span>
            </div>
            <div class="chat-picker-tab" id="chatPickerTabCart" onclick="switchChatPickerTab('cart')" style="flex:1; padding:10px 4px; text-align:center; color:#888; font-size:12px; font-weight:600; cursor:pointer; border-bottom:2.5px solid transparent; transition:all 0.2s;">
                Cart <span id="chatPickerCountCart" style="font-size:10px; color:#888;"></span>
            </div>
            <div class="chat-picker-tab" id="chatPickerTabPurchase" onclick="switchChatPickerTab('purchases')" style="flex:1; padding:10px 4px; text-align:center; color:#888; font-size:12px; font-weight:600; cursor:pointer; border-bottom:2.5px solid transparent; transition:all 0.2s;">
                Purchase <span id="chatPickerCountPurchase" style="font-size:10px; color:#888;"></span>
            </div>
        </div>

        <!-- Search input inside picker -->
        <div style="padding:8px 12px; border-bottom:1px solid #222; background:#181818; flex-shrink:0;">
            <input type="text" id="chatPickerSearch" placeholder="Search items..." oninput="filterChatPickerItems()" style="width:100%; background:#242424; border:1px solid #333; border-radius:16px; padding:6px 12px; color:#fff; font-size:12px; outline:none; box-sizing:border-box;">
        </div>

        <!-- Items List -->
        <div id="chatPickerItemsList" style="flex:1; overflow-y:auto; padding:8px 10px; display:flex; flex-direction:column; gap:8px; scrollbar-width:thin; scrollbar-color:#444 transparent;">
            <div style="text-align:center; color:#666; font-size:12px; padding:30px 0;">Loading items...</div>
        </div>

        <!-- Bottom Action Footer -->
        <div style="padding:10px 14px; background:#1a1a1a; border-top:1px solid #2a2a2a; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-shrink:0;">
            <div id="chatPickerSelectedCount" style="color:#aaa; font-size:12px; font-weight:500;">0 items selected</div>
            <button type="button" id="chatPickerSendBtn" onclick="attachSelectedChatItems()" disabled style="background:linear-gradient(135deg,#7c6300,#b8960c); opacity:0.5; color:#fff; border:none; border-radius:20px; padding:8px 18px; font-size:12px; font-weight:700; cursor:not-allowed; display:flex; align-items:center; gap:6px; transition:all 0.2s;">
                <span>Attach for Inquiry</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            </button>
        </div>
    </div>
</div>

<script>
// ===== USER CHAT SYSTEM =====
let USER_CHAT_USER_ID = <?php echo $isLoggedIn ? intval($currentUser['id']) : 'null'; ?>;
let USER_CHAT_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
if (!USER_CHAT_USER_ID) {
    const savedUserId = parseInt(localStorage.getItem('userId') || '0');
    if (savedUserId > 0) {
        USER_CHAT_USER_ID = savedUserId;
        USER_CHAT_LOGGED_IN = true;
    }
}
let USER_CHAT_USER_PROFILE = <?php echo json_encode($userProfileImage ?: 'images/icon/profile_logo.png'); ?>;
const ADMIN_CHAT_PROFILE = 'images/icon/JMB.png';

let userChatMinimized = true;
let userChatLastId = 0;
let userChatPollInterval = null;
let userChatInitialized = false;
let userPrevUnreadCount = 0;

// ===== PRODUCT / CART / PURCHASE PICKER STATE =====
let chatPickerData = { products: [], cart: [], purchases: [] };
let chatPickerLoaded = false;
let chatPickerCurrentTab = 'products';
let selectedChatItems = new Map(); // key: "type_id", value: itemObj
let pendingChatAttachments = [];

function toggleChatProductPicker() {
    const sheet = document.getElementById('chatProductPickerSheet');
    if (!sheet) return;
    if (sheet.style.display === 'flex') {
        closeChatProductPicker();
    } else {
        openChatProductPicker();
    }
}

function openChatProductPicker() {
    const sheet = document.getElementById('chatProductPickerSheet');
    if (!sheet) return;
    if (pendingChatAttachments.length > 0 && selectedChatItems.size === 0) {
        pendingChatAttachments.forEach(it => {
            const key = (it.source || 'product') + '_' + (it.id || it.item_id || it.gown_id);
            selectedChatItems.set(key, it);
        });
        updateChatPickerSelectionUI();
    }
    sheet.style.display = 'flex';
    if (!chatPickerLoaded) {
        loadChatPickerData();
    } else {
        renderChatPickerItems();
    }
}

function closeChatProductPicker() {
    const sheet = document.getElementById('chatProductPickerSheet');
    if (sheet) sheet.style.display = 'none';
    if (pendingChatAttachments.length > 0) {
        selectedChatItems.clear();
        pendingChatAttachments.forEach(it => {
            const key = (it.source || 'product') + '_' + (it.id || it.item_id || it.gown_id);
            selectedChatItems.set(key, it);
        });
        updateChatPickerSelectionUI();
    } else {
        selectedChatItems.clear();
        updateChatPickerSelectionUI();
    }
}

function loadChatPickerData() {
    const container = document.getElementById('chatPickerItemsList');
    if (container) {
        container.innerHTML = `
            <div style="display:flex; flex-direction:column; gap:8px; width:100%;">
                <div class="skeleton-picker-item">
                    <div class="skeleton-chat-card-img skeleton-shimmer"></div>
                    <div class="skeleton-chat-card-info">
                        <div class="skeleton-chat-card-title skeleton-shimmer"></div>
                        <div class="skeleton-chat-card-price skeleton-shimmer"></div>
                    </div>
                </div>
                <div class="skeleton-picker-item">
                    <div class="skeleton-chat-card-img skeleton-shimmer"></div>
                    <div class="skeleton-chat-card-info">
                        <div class="skeleton-chat-card-title skeleton-shimmer" style="width:65%;"></div>
                        <div class="skeleton-chat-card-price skeleton-shimmer"></div>
                    </div>
                </div>
                <div class="skeleton-picker-item">
                    <div class="skeleton-chat-card-img skeleton-shimmer"></div>
                    <div class="skeleton-chat-card-info">
                        <div class="skeleton-chat-card-title skeleton-shimmer" style="width:80%;"></div>
                        <div class="skeleton-chat-card-price skeleton-shimmer"></div>
                    </div>
                </div>
            </div>
        `;
    }
    const itemFetchUrl = 'index.php?chat_action=get_items' + (USER_CHAT_USER_ID ? '&user_id=' + encodeURIComponent(USER_CHAT_USER_ID) : '');
    fetch(itemFetchUrl, { credentials: 'same-origin' })
        .then(r => r.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.warn('chat_get_items non-JSON:', text);
                data = { success: false };
            }
            if (data.success) {
                chatPickerData = {
                    products: data.products || [],
                    cart: data.cart || [],
                    purchases: data.purchases || []
                };
                chatPickerLoaded = true;

                // Update tab counts
                const cp = document.getElementById('chatPickerCountProducts');
                const cc = document.getElementById('chatPickerCountCart');
                const cr = document.getElementById('chatPickerCountPurchase');
                if (cp) cp.textContent = `(${chatPickerData.products.length})`;
                if (cc) cc.textContent = `(${chatPickerData.cart.length})`;
                if (cr) cr.textContent = `(${chatPickerData.purchases.length})`;

                renderChatPickerItems();
            } else {
                if (container) container.innerHTML = '<div style="text-align:center; color:#888; font-size:12px; padding:30px 0;">Failed to load items.</div>';
            }
        }).catch(() => {
            if (container) container.innerHTML = '<div style="text-align:center; color:#888; font-size:12px; padding:30px 0;">Failed to load items.</div>';
        });
}

function switchChatPickerTab(tab) {
    chatPickerCurrentTab = tab;
    ['products', 'cart', 'purchases'].forEach(t => {
        const el = document.getElementById('chatPickerTab' + (t === 'purchases' ? 'Purchase' : (t.charAt(0).toUpperCase() + t.slice(1))));
        if (el) {
            if (t === tab) {
                el.style.color = '#ffd700';
                el.style.borderBottom = '2.5px solid #ffd700';
                el.style.fontWeight = '700';
            } else {
                el.style.color = '#888';
                el.style.borderBottom = '2.5px solid transparent';
                el.style.fontWeight = '600';
            }
        }
    });
    renderChatPickerItems();
}

function renderChatPickerItems() {
    const container = document.getElementById('chatPickerItemsList');
    if (!container) return;

    const search = (document.getElementById('chatPickerSearch')?.value || '').toLowerCase().trim();
    const rawList = chatPickerData[chatPickerCurrentTab] || [];

    const list = rawList.filter(item => {
        if (!search) return true;
        const name = (item.name || '').toLowerCase();
        const cat = (item.category || '').toLowerCase();
        const status = (item.status || '').toLowerCase();
        return name.includes(search) || cat.includes(search) || status.includes(search);
    });

    if (list.length === 0) {
        let emptyText = 'No products found';
        if (chatPickerCurrentTab === 'cart') emptyText = 'Your cart is empty';
        if (chatPickerCurrentTab === 'purchases') emptyText = 'No purchase / reservation history';
        container.innerHTML = `<div style="text-align:center; color:#666; font-size:12px; padding:30px 0;">${emptyText}</div>`;
        return;
    }

    container.innerHTML = '';
    list.forEach(item => {
        const itemKey = `${item.type}_${item.id || item.cart_id || item.reserve_id}`;
        const isSelected = selectedChatItems.has(itemKey);

        const card = document.createElement('div');
        card.style.cssText = `
            border: 1px solid ${isSelected ? '#ffd700' : '#2a2a2a'};
            background: ${isSelected ? '#24200c' : '#1c1c1c'};
            border-radius: 10px;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.15s ease;
            user-select: none;
        `;
        card.onclick = () => toggleChatPickerItem(item, itemKey);

        card.innerHTML = `
            <div style="width:20px; height:20px; border-radius:4px; border:2px solid ${isSelected ? '#ffd700' : '#555'}; background:${isSelected ? '#ffd700' : 'transparent'}; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all 0.15s;">
                ${isSelected ? '<span style="color:#000; font-size:12px; font-weight:bold; line-height:1;">✓</span>' : ''}
            </div>
            <img src="${escapeHtml(item.image_path)}" style="width:44px; height:44px; border-radius:8px; object-fit:cover; border:1px solid #333; flex-shrink:0;" onerror="this.src='images/icon/profile_logo.png'">
            <div style="flex:1; min-width:0; overflow:hidden;">
                <div style="display:flex; align-items:center; gap:6px; margin-bottom:2px; flex-wrap:wrap;">
                    <span style="background:#2a2a2a; color:#ffd700; font-size:9px; font-weight:700; padding:1px 5px; border-radius:6px;">${escapeHtml(item.source || 'Product')}</span>
                    ${item.status ? `<span style="background:#1e381e; color:#55ee55; border:1px solid #2d5a2d; font-size:9px; font-weight:700; padding:1px 5px; border-radius:6px;">${escapeHtml(item.status)}</span>` : ''}
                </div>
                <div style="color:#fff; font-size:12px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(item.name)}</div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:2px;">
                    <span style="color:#ffd700; font-weight:700; font-size:12px;">₱${escapeHtml(String(item.price))}</span>
                    ${item.dates ? `<span style="color:#777; font-size:10px;">📅 ${escapeHtml(item.dates)}</span>` : ''}
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}

function toggleChatPickerItem(item, itemKey) {
    if (selectedChatItems.has(itemKey)) {
        selectedChatItems.delete(itemKey);
    } else {
        selectedChatItems.set(itemKey, item);
    }
    updateChatPickerSelectionUI();
    renderChatPickerItems();
}

function filterChatPickerItems() {
    renderChatPickerItems();
}

function updateChatPickerSelectionUI() {
    const count = selectedChatItems.size;
    const countLabel = document.getElementById('chatPickerSelectedCount');
    const sendBtn = document.getElementById('chatPickerSendBtn');

    if (countLabel) {
        countLabel.textContent = `${count} item${count === 1 ? '' : 's'} selected`;
        countLabel.style.color = count > 0 ? '#ffd700' : '#aaa';
    }

    if (sendBtn) {
        if (count > 0) {
            sendBtn.disabled = false;
            sendBtn.style.opacity = '1';
            sendBtn.style.cursor = 'pointer';
            sendBtn.querySelector('span').textContent = `Attach for Inquiry (${count})`;
        } else {
            sendBtn.disabled = true;
            sendBtn.style.opacity = '0.5';
            sendBtn.style.cursor = 'not-allowed';
            sendBtn.querySelector('span').textContent = 'Attach for Inquiry';
        }
    }
}

function attachSelectedChatItems() {
    if (selectedChatItems.size === 0) return;

    pendingChatAttachments = Array.from(selectedChatItems.values());
    renderChatAttachmentPreview();
    closeChatProductPicker();

    // Focus input so user can type text before sending
    const input = document.getElementById('userChatInput');
    if (input) {
        input.focus();
        input.selectionStart = input.selectionEnd = input.value.length;
    }
}

const sendSelectedChatItems = attachSelectedChatItems;

function clearPendingChatAttachments() {
    pendingChatAttachments = [];
    selectedChatItems.clear();
    updateChatPickerSelectionUI();
    const previewBar = document.getElementById('chatAttachmentPreviewBar');
    if (previewBar) {
        previewBar.style.display = 'none';
    }
    const attachBtn = document.getElementById('chatAttachProductBtn');
    if (attachBtn) {
        attachBtn.style.borderColor = '#7c6300';
        attachBtn.style.background = '#262626';
    }
    const badge = document.getElementById('chatAttachBtnBadge');
    if (badge) {
        badge.style.display = 'none';
        badge.textContent = '0';
    }
    renderChatPickerItems();
}

function renderChatAttachmentPreview() {
    const previewBar = document.getElementById('chatAttachmentPreviewBar');
    const content = document.getElementById('chatAttachmentPreviewContent');
    const attachBtn = document.getElementById('chatAttachProductBtn');
    const badge = document.getElementById('chatAttachBtnBadge');

    if (!previewBar || !content) return;

    if (!pendingChatAttachments || pendingChatAttachments.length === 0) {
        previewBar.style.display = 'none';
        if (attachBtn) {
            attachBtn.style.borderColor = '#7c6300';
            attachBtn.style.background = '#262626';
        }
        if (badge) {
            badge.style.display = 'none';
            badge.textContent = '0';
        }
        return;
    }

    const count = pendingChatAttachments.length;
    const firstItem = pendingChatAttachments[0];
    const firstImg = firstItem.image_path || 'images/icon/purchase.png';

    content.innerHTML = `
        <img src="${escapeHtml(firstImg)}" alt="" style="width:32px; height:32px; border-radius:6px; object-fit:cover; border:1px solid #ffd700; flex-shrink:0;" onerror="this.src='images/icon/purchase.png'">
        <div style="flex:1; min-width:0; overflow:hidden; font-size:11px; line-height:1.2; text-align:left; cursor:pointer;" onclick="openChatProductPicker()" title="Click to view/change attachments">
            <div style="color:#ffd700; font-weight:700; display:flex; align-items:center; gap:5px;">
                <span>Attached for Inquiry (${count})</span>
                <span style="color:#aaa; font-size:10px; font-weight:normal;">• Tap to edit</span>
            </div>
            <div style="color:#ddd; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-top:2px;">
                ${count === 1 ? escapeHtml(firstItem.name) : `${escapeHtml(firstItem.name)} + ${count - 1} more`}
            </div>
        </div>
    `;

    previewBar.style.display = 'flex';

    if (attachBtn) {
        attachBtn.style.borderColor = '#ffd700';
        attachBtn.style.background = '#382f0c';
    }
    if (badge) {
        badge.textContent = count;
        badge.style.display = 'flex';
    }
}

// Central badge update function — call whenever unread count changes
function updateChatBadges(count) {
    const isMobileOpen = window.innerWidth <= 768 && (document.getElementById('userChatPanel')?.classList.contains('mobile-open') || document.body.classList.contains('user-chat-open'));
    const isDesktopOpen = window.innerWidth > 768 && !userChatMinimized;
    if (isMobileOpen || isDesktopOpen) {
        clearAllChatBadges();
        return;
    }

    const displayCount = count > 99 ? '99+' : count;
    // Avatar badge (always visible in header)
    const hBadge = document.getElementById('userChatHeaderBadge');
    // Minimize (+) button badge (desktop only — shown when minimized)
    const mBadge = document.getElementById('chatMinBadge');
    // Mobile topbar icon badge (numbered badge like cart and purchase)
    const tBadge = document.getElementById('userChatBadge');

    if (count > 0) {
        if (hBadge) { hBadge.textContent = displayCount; hBadge.style.display = 'flex'; }
        if (tBadge) { tBadge.textContent = displayCount; tBadge.style.display = 'flex'; }
        // Show min badge only when minimized on desktop
        if (mBadge && userChatMinimized && window.innerWidth > 768) {
            mBadge.textContent = displayCount; mBadge.style.display = 'flex';
        }
        setTabFaviconWithBadge(true);
    } else {
        if (hBadge) hBadge.style.display = 'none';
        if (tBadge) tBadge.style.display = 'none';
        if (mBadge) mBadge.style.display = 'none';
        setTabFaviconWithBadge(false);
    }
}

function clearAllChatBadges() {
    userPrevUnreadCount = 0;
    const hBadge = document.getElementById('userChatHeaderBadge');
    const mBadge = document.getElementById('chatMinBadge');
    const tBadge = document.getElementById('userChatBadge');
    if (hBadge) { hBadge.style.display = 'none'; hBadge.textContent = '0'; }
    if (mBadge) { mBadge.style.display = 'none'; mBadge.textContent = '0'; }
    if (tBadge) { tBadge.style.display = 'none'; tBadge.textContent = '0'; }
    stopTabTitleFlash();

    // Mark admin messages as read in database
    if (USER_CHAT_USER_ID) {
        fetch(`index.php?chat_action=get&user_id=${USER_CHAT_USER_ID}&caller=user&mark_read=1`, { credentials: 'same-origin' }).catch(() => {});
    }

    if (USER_CHAT_USER_ID && userChatLastId > 0) {
        localStorage.setItem('closetgo_last_notified_admin_msg_' + USER_CHAT_USER_ID, String(userChatLastId));
    }
}

// ===== NOTIFICATION & TAB BADGE SYSTEM =====
let originalDocumentTitle = document.title;
let titleFlashInterval = null;
let chatNotificationAudio = null;

try {
    chatNotificationAudio = new Audio('ringtone/notification.MP3');
    chatNotificationAudio.volume = 0.5;
} catch (e) {}

function requestNotificationPermission() {
    if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
    }
}

function playNotificationSound() {
    if (chatNotificationAudio) {
        chatNotificationAudio.currentTime = 0;
        chatNotificationAudio.play().catch(() => {
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
            } catch (err) {}
        });
    }
}

function setTabFaviconWithBadge(hasBadge) {
    const favicon = document.getElementById('dynamicFavicon');
    if (!favicon) return;
    if (!hasBadge) {
        favicon.href = 'images/icon/JMB.png';
        return;
    }
    const canvas = document.createElement('canvas');
    canvas.width = 32;
    canvas.height = 32;
    const ctx = canvas.getContext('2d');
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = 'images/icon/JMB.png';
    img.onload = function() {
        ctx.save();
        ctx.beginPath();
        ctx.arc(16, 16, 15, 0, Math.PI * 2);
        ctx.clip();
        ctx.drawImage(img, 0, 0, 32, 32);
        ctx.restore();
        
        ctx.beginPath();
        ctx.arc(24, 8, 6, 0, Math.PI * 2);
        ctx.fillStyle = '#ff0000';
        ctx.fill();
        ctx.lineWidth = 1.5;
        ctx.strokeStyle = '#ffffff';
        ctx.stroke();

        favicon.href = canvas.toDataURL('image/png');
    };
    img.onerror = function() {
        favicon.href = 'images/icon/JMB.png';
    };
}

function startTabTitleFlash(unreadCount) {
    stopTabTitleFlash();
    let toggle = false;
    titleFlashInterval = setInterval(() => {
        document.title = toggle ? `(${unreadCount}) 🔴 New Message! - ClosetGo` : originalDocumentTitle;
        toggle = !toggle;
    }, 1000);
}

function stopTabTitleFlash() {
    if (titleFlashInterval) {
        clearInterval(titleFlashInterval);
        titleFlashInterval = null;
    }
    document.title = originalDocumentTitle;
    setTabFaviconWithBadge(false);
}

function showWebsiteToast(senderName, messageText, avatarSrc) {
    let toast = document.getElementById('chatWebToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'chatWebToast';
        document.body.appendChild(toast);
    }
    const isMobile = window.innerWidth <= 768;
    toast.style.cssText = isMobile 
        ? 'position:fixed; top:54px; right:10px; left:10px; z-index:30000; background:#1c1c1c; border:1.5px solid #ffd700; border-radius:12px; padding:10px 14px; display:flex; align-items:center; gap:10px; box-shadow:0 8px 32px rgba(0,0,0,0.85); cursor:pointer; box-sizing:border-box; animation:slideDownToast 0.3s ease;'
        : 'position:fixed; top:20px; right:20px; z-index:30000; background:#1c1c1c; border:1px solid #ffd700; border-radius:12px; padding:12px 16px; display:flex; align-items:center; gap:12px; box-shadow:0 8px 32px rgba(0,0,0,0.8); cursor:pointer; max-width:320px; box-sizing:border-box; animation:slideDownToast 0.3s ease;';

    // Format display preview if message contains inquiry items
    let previewText = messageText;
    if (previewText && previewText.includes('[INQUIRY_ITEMS]')) {
        previewText = '📦 Attached product inquiry';
    }

    toast.innerHTML = `
        <img src="${avatarSrc || 'images/icon/JMB.png'}" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:1.5px solid #ffd700; flex-shrink:0;" onerror="this.src='images/icon/chat.png'">
        <div style="flex:1; overflow:hidden;">
            <div style="color:#ffd700; font-weight:700; font-size:13px; line-height:1.2;">${escapeHtml(senderName)}</div>
            <div style="color:#ddd; font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-top:2px;">${escapeHtml(previewText)}</div>
        </div>
        <div onclick="event.stopPropagation(); this.parentElement.style.display='none';" style="color:#888; font-size:16px; padding:2px; cursor:pointer; line-height:1;">✕</div>
    `;
    toast.onclick = () => {
        toast.style.display = 'none';
        openUserChat();
    };
    toast.style.display = 'flex';
    setTimeout(() => {
        if (toast) toast.style.display = 'none';
    }, 6000);
}

function triggerUserNewMessageNotification(senderName, messageText, avatarSrc, unreadCount) {
    playNotificationSound();
    setTabFaviconWithBadge(true);
    startTabTitleFlash(unreadCount || 1);
    showWebsiteToast(senderName, messageText, avatarSrc);

    if ("Notification" in window && Notification.permission === "granted") {
        try {
            let bodyText = messageText;
            if (bodyText && bodyText.includes('[INQUIRY_ITEMS]')) {
                bodyText = '📦 Attached product inquiry';
            }
            const notif = new Notification(senderName, {
                body: bodyText,
                icon: avatarSrc || "images/icon/JMB.png",
                badge: "images/icon/JMB.png",
                tag: "closetgo-user-chat"
            });
            notif.onclick = function() {
                window.focus();
                openUserChat();
                notif.close();
            };
        } catch (e) {}
    }
}

function handleChatHeaderClick() {
    if (window.innerWidth <= 768) return;
    toggleChatPanelMinimize();
}

function openUserChat() {
    requestNotificationPermission();
    clearAllChatBadges();

    const panel = document.getElementById('userChatPanel');
    const body = document.getElementById('chatPanelBody');
    const loginPrompt = document.getElementById('chatLoginPrompt');

    if (window.innerWidth <= 768) {
        document.body.classList.add('user-chat-open');
        document.body.style.overflow = 'hidden';
        panel.classList.add('mobile-open');
        panel.style.display = 'flex';

        if (!USER_CHAT_LOGGED_IN) {
            loginPrompt.style.display = 'flex';
            loginPrompt.style.flexDirection = 'column';
            body.style.display = 'none';
        } else {
            loginPrompt.style.display = 'none';
            body.style.display = 'flex';
            body.style.flexDirection = 'column';

            if (!userChatInitialized) {
                userChatInitialized = true;
                loadAllUserMessages();
            } else {
                fetch(`chat_get.php?user_id=${USER_CHAT_USER_ID}&caller=user&mark_read=1`).catch(() => {});
            }
            startUserChatPolling();
            setTimeout(() => scrollChatToBottom('userChatMessages'), 100);
        }
    } else {
        if (panel) {
            panel.style.display = 'flex';
        }
        if (userChatMinimized) {
            toggleChatPanelMinimize();
        }
    }
}

function closeMobileChat() {
    document.body.classList.remove('user-chat-open');
    document.body.style.overflow = '';
    const panel = document.getElementById('userChatPanel');
    if (panel) {
        panel.classList.remove('mobile-open');
        if (window.innerWidth <= 768) {
            panel.style.display = 'none';
        } else {
            panel.style.display = 'flex';
            panel.style.height = userChatMinimized ? '52px' : '480px';
        }
    }
    closeChatProductPicker();
    stopUserChatPolling();
}

function toggleChatPanelMinimize() {
    if (window.innerWidth <= 768) return;
    requestNotificationPermission();

    const body = document.getElementById('chatPanelBody');
    const loginPrompt = document.getElementById('chatLoginPrompt');
    const minIcon = document.getElementById('chatPanelMinIcon');
    const panel = document.getElementById('userChatPanel');

    userChatMinimized = !userChatMinimized;
    if (userChatMinimized) {
        body.style.display = 'none';
        loginPrompt.style.display = 'none';
        panel.style.height = '52px';
        minIcon.textContent = '+';
        closeChatProductPicker();
        stopUserChatPolling();
        // Re-check for unread so badge shows on + if any came in
        pollUserChatBackground();
    } else {
        clearAllChatBadges();
        panel.style.height = '480px';
        minIcon.textContent = '−';

        if (!USER_CHAT_LOGGED_IN) {
            loginPrompt.style.display = 'flex';
            loginPrompt.style.flexDirection = 'column';
            body.style.display = 'none';
        } else {
            loginPrompt.style.display = 'none';
            body.style.display = 'flex';
            body.style.flexDirection = 'column';

            if (!userChatInitialized) {
                userChatInitialized = true;
                loadAllUserMessages();
            } else {
                fetch(`chat_get.php?user_id=${USER_CHAT_USER_ID}&caller=user&mark_read=1`).catch(() => {});
            }

            startUserChatPolling();
            setTimeout(() => scrollChatToBottom('userChatMessages'), 100);
        }
    }
}

function loadAllUserMessages() {
    if (!USER_CHAT_USER_ID) return;
    const container = document.getElementById('userChatMessages');
    if (container) {
        container.innerHTML = `
            <div class="skeleton-chat-container">
                <div class="skeleton-chat-row incoming">
                    <div class="skeleton-chat-avatar skeleton-shimmer"></div>
                    <div class="skeleton-chat-bubble medium skeleton-shimmer"></div>
                </div>
                <div class="skeleton-chat-row outgoing">
                    <div class="skeleton-chat-bubble long skeleton-shimmer"></div>
                    <div class="skeleton-chat-avatar skeleton-shimmer"></div>
                </div>
                <div class="skeleton-chat-row outgoing">
                    <div class="skeleton-chat-card">
                        <div class="skeleton-chat-card-img skeleton-shimmer"></div>
                        <div class="skeleton-chat-card-info">
                            <div class="skeleton-chat-card-badge skeleton-shimmer"></div>
                            <div class="skeleton-chat-card-title skeleton-shimmer"></div>
                            <div class="skeleton-chat-card-price skeleton-shimmer"></div>
                        </div>
                    </div>
                    <div class="skeleton-chat-avatar skeleton-shimmer"></div>
                </div>
                <div class="skeleton-chat-row incoming">
                    <div class="skeleton-chat-avatar skeleton-shimmer"></div>
                    <div class="skeleton-chat-bubble tall skeleton-shimmer"></div>
                </div>
                <div class="skeleton-chat-row outgoing">
                    <div class="skeleton-chat-bubble short skeleton-shimmer"></div>
                    <div class="skeleton-chat-avatar skeleton-shimmer"></div>
                </div>
            </div>
        `;
    }

    fetch(`index.php?chat_action=get&user_id=${USER_CHAT_USER_ID}&last_id=0&caller=user&mark_read=1`, { credentials: 'same-origin' })
        .then(r => r.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                data = { success: false, error: text };
            }
            if (container) {
                container.innerHTML = '';
            }
            if (data.success && data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    appendUserChatBubble(msg);
                    if (msg.id) userChatLastId = Math.max(userChatLastId, msg.id);
                });
                scrollChatToBottom('userChatMessages');
            } else {
                if (container) {
                    container.innerHTML = '<div style="text-align:center; color:#666; font-size:12px; padding:20px 0;">Send a message to start chatting with admin.</div>';
                }
            }
        }).catch(() => {
            if (container) {
                container.innerHTML = '<div style="text-align:center; color:#666; font-size:12px; padding:20px 0;">Send a message to start chatting with admin.</div>';
            }
        });
}

function startUserChatPolling() {
    if (userChatPollInterval) clearInterval(userChatPollInterval);
    const interval = document.hidden ? 8000 : 1500;
    userChatPollInterval = setInterval(pollUserChat, interval);
}

function stopUserChatPolling() {
    if (userChatPollInterval) {
        clearInterval(userChatPollInterval);
        userChatPollInterval = null;
    }
}

function pollUserChat() {
    if (!USER_CHAT_USER_ID) return;
    fetch(`index.php?chat_action=get&user_id=${USER_CHAT_USER_ID}&last_id=${userChatLastId}&caller=user&mark_read=1`, { credentials: 'same-origin' })
        .then(r => r.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                return;
            }
            if (!data.success) return;
            if (data.messages && data.messages.length > 0) {
                const container = document.getElementById('userChatMessages');
                const placeholder = container.querySelector('div[style*="padding:20px"]');
                if (placeholder) placeholder.remove();

                data.messages.forEach(msg => {
                    appendUserChatBubble(msg);
                    if (msg.id) userChatLastId = Math.max(userChatLastId, msg.id);
                });
                scrollChatToBottom('userChatMessages');
            }
        }).catch(() => {});
}

// Background polling to update unread badge & sound/push notifications
function pollUserChatBackground() {
    if (!USER_CHAT_USER_ID) return;
    const isMobileOpen = window.innerWidth <= 768 && document.getElementById('userChatPanel').classList.contains('mobile-open');
    const isDesktopOpen = window.innerWidth > 768 && !userChatMinimized;
    if (isMobileOpen || isDesktopOpen) return;

    const storageKey = 'closetgo_last_notified_admin_msg_' + USER_CHAT_USER_ID;
    let lastNotifiedAdminMsgId = parseInt(localStorage.getItem(storageKey) || '0');

    fetch(`index.php?chat_action=get&user_id=${USER_CHAT_USER_ID}&last_id=${userChatLastId}&caller=user&mark_read=0`, { credentials: 'same-origin' })
        .then(r => r.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                return;
            }
            if (!data.success) return;
            const count = data.unread_count || 0;

            if (data.messages && data.messages.length > 0) {
                // Filter messages sent by admin
                const adminMsgs = data.messages.filter(m => m.sender === 'admin');
                
                if (adminMsgs.length > 0) {
                    // Only trigger notification for admin messages newer than lastNotifiedAdminMsgId
                    const newUnnotifiedAdminMsgs = adminMsgs.filter(m => m.id && m.id > lastNotifiedAdminMsgId);
                    
                    if (newUnnotifiedAdminMsgs.length > 0) {
                        const latest = newUnnotifiedAdminMsgs[newUnnotifiedAdminMsgs.length - 1];
                        triggerUserNewMessageNotification("ClosetGo Admin (JMB)", latest.message, "images/icon/JMB.png", count);
                        
                        // Update and persist the highest notified admin message ID
                        const highestAdminMsgId = Math.max(...adminMsgs.map(m => m.id || 0), lastNotifiedAdminMsgId);
                        localStorage.setItem(storageKey, String(highestAdminMsgId));
                    } else if (lastNotifiedAdminMsgId === 0 && count === 0) {
                        // Brand new session without unread: sync storage without triggering popups
                        const highestAdminMsgId = Math.max(...adminMsgs.map(m => m.id || 0), 0);
                        localStorage.setItem(storageKey, String(highestAdminMsgId));
                    }
                }

                data.messages.forEach(m => {
                    if (m.id) userChatLastId = Math.max(userChatLastId, m.id);
                });
            }

            userPrevUnreadCount = count;
            updateChatBadges(count);
        }).catch(() => {});
}

function formatChatBubbleContent(rawMessage, isAdminSide) {
    if (!rawMessage) return '';
    const match = rawMessage.match(/\[INQUIRY_ITEMS\]([\s\S]*?)\[\/INQUIRY_ITEMS\]/);
    if (!match) {
        return escapeHtml(rawMessage);
    }
    
    let items = [];
    try {
        items = JSON.parse(match[1]);
    } catch (e) {
        return escapeHtml(rawMessage);
    }
    
    const textPart = rawMessage.replace(/\[INQUIRY_ITEMS\][\s\S]*?\[\/INQUIRY_ITEMS\]/, '').trim();
    let html = '';
    
    if (textPart) {
        html += `<div style="margin-bottom:8px; word-break:break-word; font-size:13px; line-height:1.4;">${escapeHtml(textPart)}</div>`;
    }
    
    html += `<div style="display:flex; flex-direction:column; gap:6px; width:100%; min-width:210px;">`;
    items.forEach(item => {
        let imgSrc = item.image_path || 'images/icon/profile_logo.png';
        if (isAdminSide) {
            if (!imgSrc.startsWith('http') && !imgSrc.startsWith('../') && !imgSrc.startsWith('/')) {
                imgSrc = '../' + imgSrc;
            }
        }
        html += `
            <div style="background:#161616; border:1px solid #7c6300; border-radius:10px; padding:8px 10px; display:flex; align-items:center; gap:10px; box-shadow:0 2px 8px rgba(0,0,0,0.5); text-align:left;">
                <img src="${escapeHtml(imgSrc)}" style="width:48px; height:48px; border-radius:8px; object-fit:cover; border:1px solid #ffd700; flex-shrink:0;" onerror="this.src='${isAdminSide ? '../' : ''}images/icon/profile_logo.png'">
                <div style="flex:1; min-width:0; overflow:hidden;">
                    <div style="display:flex; align-items:center; gap:6px; margin-bottom:2px; flex-wrap:wrap;">
                        <span style="background:linear-gradient(135deg,#7c6300,#b8960c); color:#fff; font-size:9px; font-weight:700; padding:1px 6px; border-radius:6px; text-transform:uppercase;">${escapeHtml(item.source || 'Inquiry')}</span>
                        ${item.status ? `<span style="background:#1e381e; color:#55ee55; border:1px solid #2d5a2d; font-size:9px; font-weight:700; padding:1px 6px; border-radius:6px;">${escapeHtml(item.status)}</span>` : ''}
                    </div>
                    <div style="color:#fff; font-weight:700; font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(item.name || 'Product')}</div>
                    <div style="color:#ffd700; font-weight:700; font-size:12px; margin-top:1px;">₱${escapeHtml(String(item.price || '0.00'))}</div>
                    ${item.dates ? `<div style="color:#aaa; font-size:10px; margin-top:1px;">📅 ${escapeHtml(item.dates)}</div>` : ''}
                </div>
            </div>
        `;
    });
    html += `</div>`;
    return html;
}

function appendUserChatBubble(msg, tempId) {
    const container = document.getElementById('userChatMessages');
    if (!container) return;

    // Deduplication check: if message with this ID already exists, do not duplicate!
    if (msg.id && msg.id > 0) {
        const existingWithId = container.querySelector(`[data-msg-id="${msg.id}"]`);
        if (existingWithId) return;

        // Check if there is an optimistic bubble waiting to be confirmed
        const pendingOptimistic = container.querySelector(`[data-msg-id="0"]`);
        if (pendingOptimistic && pendingOptimistic.getAttribute('data-sender') === msg.sender) {
            pendingOptimistic.setAttribute('data-msg-id', msg.id);
            pendingOptimistic.removeAttribute('data-temp-id');
            return;
        }
    }

    const isUser = msg.sender === 'user';
    const bubbleWrapper = document.createElement('div');
    bubbleWrapper.setAttribute('data-msg-id', msg.id || 0);
    bubbleWrapper.setAttribute('data-sender', msg.sender);
    if (tempId) bubbleWrapper.setAttribute('data-temp-id', tempId);

    bubbleWrapper.style.cssText = `display:flex; flex-direction:row; align-items:flex-end; gap:8px; margin-bottom:10px; justify-content:${isUser ? 'flex-end' : 'flex-start'};`;

    const timeStr = formatChatTime(msg.created_at);
    const userImg = escapeHtml(USER_CHAT_USER_PROFILE || 'images/icon/profile_logo.png');
    const adminImg = ADMIN_CHAT_PROFILE;
    const contentHtml = formatChatBubbleContent(msg.message, false);

    if (isUser) {
        bubbleWrapper.innerHTML = `
            <div style="display:flex; flex-direction:column; align-items:flex-end; max-width:85%;">
                <div style="padding:9px 13px; border-radius:16px 16px 4px 16px; background:linear-gradient(135deg,#7c6300,#b8960c); color:#fff; font-size:13px; line-height:1.5; word-break:break-word; box-shadow:0 2px 8px rgba(0,0,0,0.3);">${contentHtml}</div>
                <div style="font-size:10px; color:#777; margin-top:2px; padding:0 4px; display:flex; align-items:center; gap:4px;">
                    <span>${timeStr}</span>
                    ${!msg.id ? `<span class="chat-msg-status" style="color:#ffd700; font-size:10px;">· Sending...</span>` : ''}
                </div>
            </div>
            <img src="${userImg}" alt="You" style="width:28px; height:28px; border-radius:50%; object-fit:cover; border:1.5px solid #ffd700; flex-shrink:0; margin-bottom:16px;" onerror="this.src='images/icon/profile_logo.png'">
        `;
    } else {
        bubbleWrapper.innerHTML = `
            <img src="${adminImg}" alt="Admin" style="width:28px; height:28px; border-radius:50%; object-fit:cover; border:1.5px solid #7c6300; flex-shrink:0; margin-bottom:16px;" onerror="this.src='images/icon/chat.png'">
            <div style="display:flex; flex-direction:column; align-items:flex-start; max-width:85%;">
                <div style="font-size:11px; color:#ffd700; font-weight:600; margin-bottom:2px; padding-left:4px;">Admin (JMB)</div>
                <div style="padding:9px 13px; border-radius:16px 16px 16px 4px; background:#2a2a2a; color:#fff; font-size:13px; line-height:1.5; word-break:break-word; border:1px solid #3a3a3a; box-shadow:0 2px 8px rgba(0,0,0,0.3);">${contentHtml}</div>
                <div style="font-size:10px; color:#777; margin-top:2px; padding:0 4px;">${timeStr}</div>
            </div>
        `;
    }
    container.appendChild(bubbleWrapper);
}

function sendUserMessageWithContent(messageText) {
    if (!messageText) return;

    if (!USER_CHAT_USER_ID) {
        showWebsiteToast('ClosetGo Support', 'Please log in to chat with our admin.', 'images/icon/JMB.png');
        const loginPrompt = document.getElementById('chatLoginPrompt');
        const body = document.getElementById('chatPanelBody');
        if (loginPrompt && body) {
            loginPrompt.style.display = 'flex';
            body.style.display = 'none';
        }
        return;
    }

    const container = document.getElementById('userChatMessages');
    const placeholder = container.querySelector('div[style*="padding:20px"]');
    if (placeholder) placeholder.remove();

    const tempId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
    const tempMsg = { id: 0, sender: 'user', message: messageText, created_at: new Date().toISOString() };
    appendUserChatBubble(tempMsg, tempId);
    scrollChatToBottom('userChatMessages');

    const formData = new FormData();
    formData.append('user_id', USER_CHAT_USER_ID);
    formData.append('sender', 'user');
    formData.append('message', messageText);

    fetch('index.php?chat_action=send', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(r => r.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.warn('chat_send non-JSON response:', text);
            const cleanErr = text ? text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 100) : 'Server error';
            data = { success: false, error: cleanErr };
        }
        if (data.success && data.id) {
            userChatLastId = Math.max(userChatLastId, data.id);
            const el = container.querySelector(`[data-temp-id="${tempId}"]`);
            if (el) {
                el.setAttribute('data-msg-id', data.id);
                el.removeAttribute('data-temp-id');
                const statusEl = el.querySelector('.chat-msg-status');
                if (statusEl) statusEl.remove();
            }
        } else {
            console.warn('Chat send error:', data);
            const el = container.querySelector(`[data-temp-id="${tempId}"]`);
            if (el) {
                const statusEl = el.querySelector('.chat-msg-status');
                if (statusEl) {
                    statusEl.innerHTML = `<span style="color:#ff6b6b; font-size:10px;">⚠️ Not saved: ${escapeHtml(data.error || 'Server error')}</span>`;
                }
            }
            showWebsiteToast('Chat Warning', data.error || 'Failed to save message in database', 'images/icon/JMB.png');
        }
    })
    .catch(err => {
        console.error('Chat send fetch failed:', err);
        const el = container.querySelector(`[data-temp-id="${tempId}"]`);
        if (el) {
            const statusEl = el.querySelector('.chat-msg-status');
            if (statusEl) {
                statusEl.innerHTML = `<span style="color:#ff6b6b; font-size:10px;">⚠️ Connection issue</span>`;
            }
        }
    });
}

function sendUserMessage() {
    if (!USER_CHAT_USER_ID) return;
    const input = document.getElementById('userChatInput');
    const message = input ? input.value.trim() : '';

    if (!message && (!pendingChatAttachments || pendingChatAttachments.length === 0)) return;

    let payload = '';
    if (pendingChatAttachments && pendingChatAttachments.length > 0) {
        payload = `[INQUIRY_ITEMS]${JSON.stringify(pendingChatAttachments)}[/INQUIRY_ITEMS]`;
        if (message) {
            payload += `\n${message}`;
        }
    } else {
        payload = message;
    }

    if (input) {
        input.value = '';
        input.style.height = 'auto';
    }

    clearPendingChatAttachments();
    sendUserMessageWithContent(payload);
}

function handleUserChatKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendUserMessage();
    }
}

function scrollChatToBottom(containerId) {
    const el = document.getElementById(containerId);
    if (el) el.scrollTop = el.scrollHeight;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(String(text)));
    return div.innerHTML;
}

function formatChatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const now = new Date();
    const diffMs = now - d;
    const diffMins = Math.floor(diffMs / 60000);
    if (diffMins < 1) return 'just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffMins < 1440) return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    return d.toLocaleDateString([], {month:'short', day:'numeric'});
}

function syncUserChatResponsiveView() {
    const panel = document.getElementById('userChatPanel');
    if (!panel) return;
    if (window.innerWidth > 768) {
        panel.classList.remove('mobile-open');
        panel.style.display = 'flex';
        panel.style.height = userChatMinimized ? '52px' : '480px';
    } else {
        if (!panel.classList.contains('mobile-open')) {
            panel.style.display = 'none';
        }
    }
}

// Run immediately on page load to ensure Chat with Admin is always shown in laptop view
syncUserChatResponsiveView();
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncUserChatResponsiveView);
}

window.addEventListener('resize', syncUserChatResponsiveView);

window.addEventListener('focus', function() {
    const isMobileOpen = window.innerWidth <= 768 && document.getElementById('userChatPanel').classList.contains('mobile-open');
    const isDesktopOpen = window.innerWidth > 768 && !userChatMinimized;
    if (isMobileOpen || isDesktopOpen) {
        stopTabTitleFlash();
    }
});

// ProFreeHost Optimization: Adjust user polling based on tab visibility
document.addEventListener('visibilitychange', function() {
    if (!document.hidden && USER_CHAT_LOGGED_IN) {
        const isMobileOpen = window.innerWidth <= 768 && document.getElementById('userChatPanel')?.classList.contains('mobile-open');
        const isDesktopOpen = window.innerWidth > 768 && !userChatMinimized;
        if (isMobileOpen || isDesktopOpen) {
            pollUserChat();
            startUserChatPolling();
        } else {
            pollUserChatBackground();
        }
    } else if (userChatPollInterval) {
        startUserChatPolling();
    }
});

if (USER_CHAT_LOGGED_IN) {
    setInterval(function() {
        if (!document.hidden) {
            pollUserChatBackground();
        }
    }, 4000);
    setTimeout(pollUserChatBackground, 800);
}

// ===== NOTIFICATION DROPDOWN & REAL-TIME SYSTEM =====
let cachedNotifications = [];
let activeNotifFilter = 'all';
let lastKnownNotifCount = 0;

function getReadNotificationIds() {
    try {
        const stored = localStorage.getItem('closetgo_read_notif_ids_' + (USER_CHAT_USER_ID || 'guest'));
        return stored ? JSON.parse(stored) : [];
    } catch (e) {
        return [];
    }
}

function setReadNotificationIds(ids) {
    try {
        localStorage.setItem('closetgo_read_notif_ids_' + (USER_CHAT_USER_ID || 'guest'), JSON.stringify(ids));
    } catch (e) {}
}

function toggleNotificationDropdown(e) {
    if (e) e.stopPropagation();
    const dropdown = document.getElementById('notificationDropdown');
    if (!dropdown) return;

    const isShowing = dropdown.classList.contains('show');
    if (isShowing) {
        dropdown.classList.remove('show');
        // Re-enable body scrolling
        document.body.style.overflow = '';
        document.body.style.position = '';
    } else {
        dropdown.classList.add('show');
        requestNotificationPermission();
        fetchNotifications(true);
        // Disable body scrolling on mobile
        if (window.innerWidth <= 768) {
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';
        }
    }
}

function filterNotifTab(type, tabEl) {
    if (type === 'chat') return; // Skip chat filter
    activeNotifFilter = type;
    const tabs = document.querySelectorAll('.notif-tab');
    tabs.forEach(t => t.classList.remove('active'));
    if (tabEl) tabEl.classList.add('active');
    renderNotificationsList();
}

function fetchNotifications(renderAfter = false) {
    const userId = USER_CHAT_USER_ID || 0;
    fetch(`notifications_get.php?user_id=${userId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && Array.isArray(data.notifications)) {
                cachedNotifications = data.notifications.filter(n => n.type !== 'chat');
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
    const badge = document.getElementById('topbarNotificationBadge');
    const pill = document.getElementById('notifUnreadPill');
    const tabBadgeAll = document.getElementById('notifTabBadgeAll');
    const tabBadgeOrder = document.getElementById('notifTabBadgeOrder');
    const readIds = getReadNotificationIds();

    const unreadList = cachedNotifications.filter(n => !readIds.includes(n.id) && n.type !== 'chat');
    const unreadCount = unreadList.length;

    const unreadOrders = cachedNotifications.filter(n => (n.type === 'order' || n.type === 'reminder') && !readIds.includes(n.id)).length;
    const filteredNotifications = cachedNotifications.filter(n => n.type !== 'chat');

    if (badge) {
        if (unreadCount > 0) {
            badge.style.display = 'inline-flex';
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
        } else {
            badge.style.display = 'none';
        }
    }

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

    // Play sound and trigger browser notification if unread count increased
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
    let filtered = cachedNotifications.filter(n => n.type !== 'chat');

    if (activeNotifFilter === 'order') {
        filtered = cachedNotifications.filter(n => n.type === 'order' || n.type === 'reminder');
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
    // Mark as read
    const readIds = getReadNotificationIds();
    if (!readIds.includes(notifId)) {
        readIds.push(notifId);
        setReadNotificationIds(readIds);
        updateNotificationBadge();
        renderNotificationsList();
    }

    // Close dropdown
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) dropdown.classList.remove('show');
    // Re-enable body scrolling
    document.body.style.overflow = '';
    document.body.style.position = '';

    // Follow link
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
    if (dropdown && dropdown.classList.contains('show')) {
        if (container && !container.contains(e.target)) {
            dropdown.classList.remove('show');
            // Re-enable body scrolling
            document.body.style.overflow = '';
            document.body.style.position = '';
        }
    }
});

// Initial notification poll and recurring interval
fetchNotifications();
setInterval(fetchNotifications, 5000);

// ===== MOBILE NAV & CATEGORY BAR: HIDE ON SCROLL DOWN / SHOW ON SCROLL UP =====
(function() {
    if (window.innerWidth > 900) return; // desktop only

    const mobileNav = document.getElementById('mobileBottomNav');
    const categoryBar = document.getElementById('mobileCategoryBar');
    const topProductsSection = document.querySelector('.top-products-section');

    let lastScrollY = window.scrollY;
    let ticking = false;
    let isHidden = false;

    function getTopProductsBottom() {
        if (!topProductsSection) return 300;
        const rect = topProductsSection.getBoundingClientRect();
        return window.scrollY + rect.bottom;
    }

    function handleScroll() {
        const currentScrollY = window.scrollY;
        const threshold = getTopProductsBottom();
        const pastTopProducts = currentScrollY > threshold;
        const scrollingDown = currentScrollY > lastScrollY;
        const delta = currentScrollY - lastScrollY;

        // Only act if movement is meaningful (>4px) to avoid micro-jitter
        if (Math.abs(delta) > 4) {
            if (pastTopProducts && scrollingDown && !isHidden) {
                // Scrolling DOWN past Top Products → hide
                isHidden = true;
                if (mobileNav) mobileNav.classList.add('nav-hidden');
                if (categoryBar) categoryBar.classList.add('nav-hidden');
            } else if ((!pastTopProducts || !scrollingDown) && isHidden) {
                // Scrolling UP or back above Top Products → show
                isHidden = false;
                if (mobileNav) mobileNav.classList.remove('nav-hidden');
                if (categoryBar) categoryBar.classList.remove('nav-hidden');
            }
            lastScrollY = currentScrollY;
        }

        ticking = false;
    }

    window.addEventListener('scroll', function() {
        if (window.innerWidth > 900) return;
        if (!ticking) {
            requestAnimationFrame(handleScroll);
            ticking = true;
        }
    }, { passive: true });

    // Re-check on resize in case user rotates device
    window.addEventListener('resize', function() {
        if (window.innerWidth > 900) {
            if (mobileNav) mobileNav.classList.remove('nav-hidden');
            if (categoryBar) categoryBar.classList.remove('nav-hidden');
            isHidden = false;
        }
    });
})();
</script>

<!-- ===== Product Detail Overlay (SPA-style, no reload) ===== -->
<div id="productOverlay" style="
    display: none;
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: #1a1a1a;
    flex-direction: column;
    overflow: hidden;
">
    <!-- Product Detail Skeleton Loader -->
    <div id="productOverlaySkeleton">
        <div>
            <!-- Left: Image Skeleton -->
            <div class="skeleton-product-card">
                <div class="skeleton-product-image"></div>
                <!-- Product Gallery Skeleton (5 boxes: 4 upload + 1 3D) -->
                <div class="skeleton-product-gallery">
                    <div class="skeleton-gallery-item"></div>
                    <div class="skeleton-gallery-item"></div>
                    <div class="skeleton-gallery-item"></div>
                    <div class="skeleton-gallery-item"></div>
                    <div class="skeleton-gallery-item skeleton-3d-item"></div>
                </div>
            </div>
            <!-- Right: Info Skeleton -->
            <div class="skeleton-product-info">
                <div class="skeleton-product-title"></div>
                <div class="skeleton-product-price"></div>
                <div class="skeleton-product-subtitle"></div>
                <div class="skeleton-review-stars"></div>
                <div class="skeleton-divider"></div>
                <div class="skeleton-product-subtitle skeleton-sm"></div>
                <div class="skeleton-product-title skeleton-md"></div>
                <div class="skeleton-divider"></div>
                <div class="skeleton-product-subtitle skeleton-sm"></div>
                <div class="skeleton-product-title skeleton-md"></div>
                <div class="skeleton-divider"></div>
                <div class="skeleton-product-subtitle skeleton-xs"></div>
                <div class="skeleton-product-title skeleton-md"></div>
                <div class="skeleton-divider"></div>
                <div class="skeleton-product-subtitle skeleton-sm"></div>
                <div class="skeleton-review-line"></div>
                <div class="skeleton-review-line skeleton-short"></div>
                <div class="skeleton-product-btn"></div>
            </div>
        </div>
    </div>

    <iframe id="productOverlayFrame"
        src="about:blank"
        style="width:100%;height:100%;border:none;flex:1;background:#1a1a1a;opacity:0;transition:opacity 0.15s ease;"
        title="Product Detail"
        allowfullscreen
    ></iframe>
</div>

<script>
(function() {
    var overlay = document.getElementById('productOverlay');
    var frame   = document.getElementById('productOverlayFrame');
    var skeleton = document.getElementById('productOverlaySkeleton');
    var overlayOpen    = false;
    var closingOverlay = false;
    var skeletonTimeout = null;

    function _closeVisually() {
        overlay.style.display = 'none';
        document.body.style.overflow = '';
        overlayOpen = false;
        // Reset frame state and clear source so no stale product is ever retained
        if (frame) {
            frame.dataset.currentId = '';
            frame.style.opacity = '0';
            frame.src = 'about:blank';
        }
        if (skeleton) skeleton.style.display = 'none';
        if (skeletonTimeout) clearTimeout(skeletonTimeout);
    }

    // ─── Instant Preload on Hover / Touch ────────────────────────────────────
    var _prefetched = {};
    function _prefetchProduct(id) {
        if (!id || _prefetched[id]) return;
        _prefetched[id] = true;
        var link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = 'index.php?id=' + id;
        document.head.appendChild(link);
    }

    // Prefetch immediately on mouse hover or touch start so it's already in cache before click completes
    document.addEventListener('pointerenter', function(e) {
        var card = e.target && e.target.closest && e.target.closest('[onclick*="viewProduct"]');
        if (card) {
            var m = (card.getAttribute('onclick') || '').match(/viewProduct\((\d+)\)/);
            if (m && m[1]) _prefetchProduct(m[1]);
        }
    }, { passive: true, capture: true });

    document.addEventListener('touchstart', function(e) {
        var card = e.target && e.target.closest && e.target.closest('[onclick*="viewProduct"]');
        if (card) {
            var m = (card.getAttribute('onclick') || '').match(/viewProduct\((\d+)\)/);
            if (m && m[1]) _prefetchProduct(m[1]);
        }
    }, { passive: true, capture: true });

    // ─── Internal open (instant display, preloaded) ───────────────────────────
    function _openOverlay(productId, pushHistory) {
        if (!overlay || !frame || !productId) return;

        var strId = String(productId);
        var wasAlreadyOpen = overlayOpen;

        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        overlayOpen = true;
        closingOverlay = false;

        // Clear any existing skeleton timeout
        if (skeletonTimeout) clearTimeout(skeletonTimeout);

        // Ensure we load the exact requested product
        if (frame.dataset.currentId !== strId) {
            frame.dataset.currentId = strId;
            frame.style.opacity = '0';

            // Show skeleton immediately
            if (skeleton) skeleton.style.display = 'block';

            frame.onload = function() {
                if (frame.dataset.currentId === strId) {
                    frame.style.opacity = '1';
                    if (skeleton) skeleton.style.display = 'none';
                    if (skeletonTimeout) clearTimeout(skeletonTimeout);
                }
            };

            frame.src = 'index.php?id=' + encodeURIComponent(productId);
        } else {
            frame.style.opacity = '1';
            if (skeleton) skeleton.style.display = 'none';
        }

        if (pushHistory) {
            // If the overlay is ALREADY open (e.g. user clicked related product inside product info),
            // replace the current history state instead of pushing a new one.
            // This guarantees only ONE overlay state exists in history,
            // so clicking "Back" always returns directly to the mainpage (index.php),
            // never cycling through previously opened products.
            if (wasAlreadyOpen) {
                history.replaceState({ productId: productId, overlay: true }, '', 'index.php');
            } else {
                history.pushState({ productId: productId, overlay: true }, '', 'index.php');
            }
        }
    }

    // ─── Public API ──────────────────────────────────────────────────────────
    window.openProductOverlay = function(productId) {
        _openOverlay(productId, true);
    };

    // ─── Browser Back / Forward ──────────────────────────────────────────────
    window.addEventListener('popstate', function(e) {
        if (closingOverlay) {
            closingOverlay = false;
            return;
        }
        if (e.state && e.state.overlay) {
            // Forward or history restored: re-open product overlay
            _openOverlay(e.state.productId, false);
        } else {
            // Back: close overlay cleanly and return to mainpage
            if (overlayOpen) {
                _closeVisually();
            }
        }
    });

    // ─── On page load ────────────────────────────────────────────────────────
    (function onPageLoad() {
        // Clear history state on refresh so the page always stays cleanly on index.php
        history.replaceState(null, '', 'index.php');
    })();

    // ─── Global viewProduct override ─────────────────────────────────────────
    window.viewProduct = function(productId) {
        window.openProductOverlay(productId);
    };

    // ─── Messages from the product detail iframe ──────────────────────────────
    window.addEventListener('message', function(e) {
        if (!e.data) return;
        if (e.data.type === 'closeProductOverlay') {
            if (overlayOpen) {
                closingOverlay = true;
                _closeVisually();
                history.back(); // revert to the base index.php state
            }
        } else if (e.data.type === 'openProduct') {
            // Product info iframe requested opening a new/related product
            if (e.data.productId) {
                _openOverlay(e.data.productId, true);
            }
        }
    });
})();
</script>

</body>
</html>
