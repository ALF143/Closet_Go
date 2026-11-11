<?php
session_start();
require_once 'database/db_config.php';

// Check if user is logged in & fetch details
$isLoggedIn = false;
$currentUser = null;
$displayName = '';
$userProfileImage = 'images/icon/profile_logo.png';
$userFirstName = '';
$userLastName = '';

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
$userEmail = isset($_SESSION['email']) ? $_SESSION['email'] : (isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '');

$database = new Database();
$conn = $database->getConnection();

if ($userId > 0 || !empty($userEmail)) {
    if ($userId > 0) {
        $userStmt = $conn->prepare("SELECT id, email, first_name, middle_name, last_name, profile_image, COALESCE(id_front_image, '') AS id_front_image, COALESCE(id_back_image, '') AS id_back_image, COALESCE(id_verified, 0) AS id_verified FROM system_users WHERE id = ?");
        $userStmt->bind_param("i", $userId);
    } else {
        $userStmt = $conn->prepare("SELECT id, email, first_name, middle_name, last_name, profile_image, COALESCE(id_front_image, '') AS id_front_image, COALESCE(id_back_image, '') AS id_back_image, COALESCE(id_verified, 0) AS id_verified FROM system_users WHERE email = ?");
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
            $userFirstName = $currentUser['first_name'] ?? '';
            $userLastName = $currentUser['last_name'] ?? '';
            
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

// Valid ID verification status: 'none' | 'pending' | 'rejected' | 'verified'
$idVerifyStatus = 'none';
if ($currentUser) {
    $hasIdImg = ($currentUser['id_front_image'] ?? '') !== '' || ($currentUser['id_back_image'] ?? '') !== '';
    $idV = (int)($currentUser['id_verified'] ?? 0);
    if (!$hasIdImg) $idVerifyStatus = 'none';
    elseif ($idV === 1) $idVerifyStatus = 'verified';
    elseif ($idV === 2) $idVerifyStatus = 'rejected';
    else $idVerifyStatus = 'pending';
}

// Helper to normalize dates
function formatReserveDate($input) {
    if (empty($input)) return date('Y-m-d');
    $d = DateTime::createFromFormat('m/d/Y', $input);
    if ($d) return $d->format('Y-m-d');
    $d = DateTime::createFromFormat('Y-m-d', $input);
    if ($d) return $d->format('Y-m-d');
    return date('Y-m-d');
}

// Helper to normalize times, the reserve_time/return_time columns are NOT NULL
function formatReserveTime($input) {
    $input = trim((string)$input);
    if ($input === '') return '00:00:00';
    foreach (['H:i:s', 'H:i', 'h:i A', 'h:iA', 'h:i a'] as $format) {
        $d = DateTime::createFromFormat($format, $input);
        if ($d) return $d->format('H:i:s');
    }
    return '00:00:00';
}

// Get products from URL parameters
$productsData = isset($_GET['products']) ? json_decode(urldecode($_GET['products']), true) : [];

// Get reservation details from URL parameters
$reserveDate = isset($_GET['reserveDate']) ? formatReserveDate($_GET['reserveDate']) : date('Y-m-d');
$returnDate = isset($_GET['returnDate']) ? formatReserveDate($_GET['returnDate']) : date('Y-m-d');
$reserveTime = isset($_GET['reserveTime']) ? htmlspecialchars($_GET['reserveTime']) : '';
$returnTime = isset($_GET['returnTime']) ? htmlspecialchars($_GET['returnTime']) : '';

// Load full saved product details from database
$products = [];
$displayPrice = 0;
$totalSaved = 0;

if ($userId > 0 || !isset($conn) || !$conn) {
    $database = new Database();
    $conn = $database->getConnection();
}

// Load app settings (GCash QR, number, account name)
$appSettings = [];
if (isset($conn) && $conn) {
    $setRes = $conn->query("SELECT setting_key, setting_value FROM app_settings");
    if ($setRes) {
        while ($sRow = $setRes->fetch_assoc()) {
            $appSettings[$sRow['setting_key']] = $sRow['setting_value'];
        }
    }
}
$gcashQrPath = !empty($appSettings['qrph_qr_path']) ? $appSettings['qrph_qr_path'] : (!empty($appSettings['gcash_qr_path']) ? $appSettings['gcash_qr_path'] : 'images/gcash_qr_temp.jpg');
$gcashNumber = !empty($appSettings['rcbc_account_number']) ? $appSettings['rcbc_account_number'] : (!empty($appSettings['gcash_number']) ? $appSettings['gcash_number'] : '09123456789');
$gcashAccountName = !empty($appSettings['rcbc_account_name']) ? $appSettings['rcbc_account_name'] : (!empty($appSettings['gcash_account_name']) ? $appSettings['gcash_account_name'] : 'ClosetGoo Admin');

// Load system settings (rental duration & extension policy)
$sysRentalDurationDays = 10;
$sysExtensionPricePerDay = 100;
if (isset($conn) && $conn) {
    $sysRes = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('rental_duration_days', 'extension_price_per_day')");
    if ($sysRes) {
        while ($sysRow = $sysRes->fetch_assoc()) {
            if ($sysRow['setting_key'] === 'rental_duration_days') {
                $sysRentalDurationDays = max(1, intval($sysRow['setting_value']));
            } elseif ($sysRow['setting_key'] === 'extension_price_per_day') {
                $sysExtensionPricePerDay = max(0, floatval($sysRow['setting_value']));
            }
        }
    }
}

if (!empty($productsData) && isset($conn)) {
    $selectFields = "SELECT sp.*, 
            COALESCE(g.discount, 0) as discount,
            COALESCE(g.price, sp.product_price, 0) as gown_price,
            COALESCE(g.purchase_price, sp.purchase_price, 0) as gown_purchase_price,
            COALESCE(sp.model_path, g.model_path, '') as model_path,
            COALESCE(g.image_path, '') as image_path,
            COALESCE(g.image_path2, '') as image_path2,
            COALESCE(g.image_path3, '') as image_path3,
            GREATEST(COALESCE(sp.quantity, 1), 1 + (SELECT COUNT(*) FROM saved_products_items spi WHERE spi.saved_product_id = sp.id)) as quantity
            FROM saved_products sp
            LEFT JOIN gowns g ON (
                (sp.product_id > 0 AND sp.product_id = g.id)
                OR ((sp.product_id IS NULL OR sp.product_id = 0) AND LOWER(TRIM(sp.product_name)) = LOWER(TRIM(g.name)))
            )";

    // Match the exact cart row when its id is known so duplicate entries of the
    // same product keep their own status, dates and sizes
    $cartStmt = $conn->prepare($selectFields . " WHERE sp.user_id = ? AND sp.id = ?");
    // Fallback: match by product name/category for legacy links lacking an id
    $cartLegacyStmt = $conn->prepare($selectFields . " WHERE sp.user_id = ? AND sp.product_name = ? AND sp.product_category = ?");

    // Prepare statement to fetch full gown details for direct checkouts (id = 0)
    $gownInfoSql = "SELECT id, name, category, price, discount, purchase_price, model_path, image_path, image_path2, image_path3 FROM gowns WHERE id = ? LIMIT 1";
    $gownInfoStmt = $conn->prepare($gownInfoSql);

    foreach ($productsData as $p) {
        $cartRowId = intval($p['id'] ?? 0);
        $name = $p['name'] ?? '';
        $category = $p['category'] ?? '';
        $paramProductId = intval($p['product_id'] ?? 0);
        $paramModelPath = $p['modelPath'] ?? ($p['model_path'] ?? '');
        $paramImagePath = $p['imagePath'] ?? ($p['image_path'] ?? '');

        // Fetch from saved_products only if item actually came from the cart (id > 0)
        $row = null;
        if ($cartRowId > 0 && $cartStmt) {
            $cartStmt->bind_param("ii", $userId, $cartRowId);
            $cartStmt->execute();
            $row = $cartStmt->get_result()->fetch_assoc();
        }

        $base = [
            'product_id' => $paramProductId,
            'modelPath' => $paramModelPath,
            'imagePath' => $paramImagePath,
            'name' => $name,
            'category' => $category,
            'reserve_date' => $reserveDate,
            'return_date' => $returnDate,
            'reserve_time' => $reserveTime,
            'return_time' => $returnTime
        ];

        if ($row) {
            // Checked out from Cart (saved_products)
            $quantity = max(1, intval($row['quantity'] ?? 1));
            $discountPercent = floatval($row['discount'] ?? 0);
            $rentalPrice = floatval($row['gown_price'] > 0 ? $row['gown_price'] : ($row['product_price'] ?? 0));
            $discountedRentalPrice = $discountPercent > 0 ? round($rentalPrice * (1 - $discountPercent / 100), 2) : $rentalPrice;
            $savedPurchasePrice = floatval($row['gown_purchase_price'] > 0 ? $row['gown_purchase_price'] : ($row['purchase_price'] ?? 0));
            $effectivePurchasePrice = $savedPurchasePrice > 0 ? $savedPurchasePrice : $rentalPrice;
            
            $itemStatus = $row['product_status'] ?: 'reserve';
            $itemReserveDate = $row['reserve_date'] ?: $reserveDate;
            $itemReturnDate = $row['return_date'] ?: $returnDate;
            $extraFeePerUnit = 0;
            if ($itemStatus !== 'buy' && !empty($itemReserveDate) && !empty($itemReturnDate)) {
                $rObj = new DateTime($itemReserveDate);
                $retObj = new DateTime($itemReturnDate);
                $diffDays = (int)$rObj->diff($retObj)->format("%r%a");
                if ($diffDays > $sysRentalDurationDays) {
                    $extraFeePerUnit = ($diffDays - $sysRentalDurationDays) * $sysExtensionPricePerDay;
                }
            }
            $unitPrice = ($itemStatus === 'buy' ? $effectivePurchasePrice : ($discountedRentalPrice + $extraFeePerUnit));
            $productTotal = round($unitPrice * $quantity, 2);
            $rowModelPath = trim((string)($row['model_path'] ?? ''));
            $rowImagePath = trim((string)($row['image_path'] ?: ($row['image_path2'] ?: ($row['image_path3'] ?? ''))));
            $products[] = array_merge($base, [
                'id' => $row['id'],
                'product_id' => intval($row['product_id'] ?? 0),
                'modelPath' => !empty($rowModelPath) ? $rowModelPath : $paramModelPath,
                'imagePath' => !empty($rowImagePath) ? $rowImagePath : $paramImagePath,
                'price' => $discountedRentalPrice + $extraFeePerUnit,
                'original_price' => $rentalPrice,
                'discount' => $discountPercent,
                'purchase_price' => $effectivePurchasePrice,
                'extension_fee' => $extraFeePerUnit,
                'quantity' => $quantity,
                'status' => $row['product_status'] ?: 'reserve',
                'reserve_date' => $row['reserve_date'] ?: $reserveDate,
                'return_date' => $row['return_date'] ?: $returnDate,
                'size_1' => $row['size_1'] ?? '',
                'size_2' => $row['size_2'] ?? '',
                'size_3' => $row['size_3'] ?? '',
                'size_4' => $row['size_4'] ?? '',
                'size_5' => $row['size_5'] ?? '',
                'size_6' => $row['size_6'] ?? '',
                'size_7' => $row['size_7'] ?? '',
                'size_8' => $row['size_8'] ?? '',
                'size_9' => $row['size_9'] ?? '',
                'size_10' => $row['size_10'] ?? '',
                'total' => $productTotal
            ]);
            $displayPrice += $productTotal;
            if (($row['product_status'] ?? 'reserve') !== 'buy' && $discountPercent > 0) {
                $totalSaved += round(($rentalPrice - $discountedRentalPrice) * $quantity, 2);
            }
        } else {
            // Direct Buy or Direct Reserve from Product Info page (NOT saved in cart / save.php)
            $gownRow = null;
            if ($paramProductId > 0 && $gownInfoStmt) {
                $gownInfoStmt->bind_param("i", $paramProductId);
                $gownInfoStmt->execute();
                $gownRow = $gownInfoStmt->get_result()->fetch_assoc();
            }

            $effectiveProductId = $paramProductId > 0 ? $paramProductId : intval($gownRow['id'] ?? 0);
            $quantity = max(1, intval($p['quantity'] ?? 1));
            $actionParam = strtolower($p['status'] ?? ($_GET['action'] ?? 'reserve'));
            $status = ($actionParam === 'buy') ? 'buy' : 'reserve';

            $rentalPrice = floatval($gownRow['price'] ?? ($p['original_price'] ?? ($p['price'] ?? 0)));
            $discountPercent = floatval($gownRow['discount'] ?? ($p['discount'] ?? 0));
            $discountedRentalPrice = $discountPercent > 0 ? round($rentalPrice * (1 - $discountPercent / 100), 2) : $rentalPrice;
            $purchasePrice = floatval($gownRow['purchase_price'] ?? ($p['purchase_price'] ?? 0));
            $effectivePurchasePrice = $purchasePrice > 0 ? $purchasePrice : $rentalPrice;

            $itemReserveDate = $p['reserve_date'] ?? $reserveDate;
            $itemReturnDate = $p['return_date'] ?? $returnDate;
            $extraFeePerUnit = 0;
            if ($status !== 'buy' && !empty($itemReserveDate) && !empty($itemReturnDate)) {
                $rObj = new DateTime($itemReserveDate);
                $retObj = new DateTime($itemReturnDate);
                $diffDays = (int)$rObj->diff($retObj)->format("%r%a");
                if ($diffDays > $sysRentalDurationDays) {
                    $extraFeePerUnit = ($diffDays - $sysRentalDurationDays) * $sysExtensionPricePerDay;
                }
            }

            $unitPrice = ($status === 'buy' ? $effectivePurchasePrice : ($discountedRentalPrice + $extraFeePerUnit));
            $productTotal = round($unitPrice * $quantity, 2);

            $rowModelPath = trim((string)($gownRow['model_path'] ?? $paramModelPath));
            $rowImagePath = trim((string)($gownRow['image_path'] ?: ($gownRow['image_path2'] ?: ($gownRow['image_path3'] ?? $paramImagePath))));

            $products[] = array_merge($base, [
                'id' => 0, // ID 0 means not in cart / saved_products
                'product_id' => $effectiveProductId,
                'name' => !empty($gownRow['name']) ? $gownRow['name'] : $name,
                'category' => !empty($gownRow['category']) ? $gownRow['category'] : $category,
                'modelPath' => !empty($rowModelPath) ? $rowModelPath : $paramModelPath,
                'imagePath' => !empty($rowImagePath) ? $rowImagePath : $paramImagePath,
                'price' => $discountedRentalPrice + $extraFeePerUnit,
                'original_price' => $rentalPrice,
                'discount' => $discountPercent,
                'purchase_price' => $effectivePurchasePrice,
                'extension_fee' => $extraFeePerUnit,
                'quantity' => $quantity,
                'status' => $status,
                'reserve_date' => $p['reserve_date'] ?? $reserveDate,
                'return_date' => $p['return_date'] ?? $returnDate,
                'size_1' => $p['size_1'] ?? ($p['size'] ?? ''),
                'size_2' => $p['size_2'] ?? '',
                'size_3' => $p['size_3'] ?? '',
                'size_4' => $p['size_4'] ?? '',
                'size_5' => $p['size_5'] ?? '',
                'size_6' => $p['size_6'] ?? '',
                'size_7' => $p['size_7'] ?? '',
                'size_8' => $p['size_8'] ?? '',
                'size_9' => $p['size_9'] ?? '',
                'size_10' => $p['size_10'] ?? '',
                'items' => (!empty($p['items']) && is_array($p['items'])) ? $p['items'] : [],
                'total' => $productTotal
            ]);
            $displayPrice += $productTotal;
            if ($status !== 'buy' && $discountPercent > 0) {
                $totalSaved += round(($rentalPrice - $discountedRentalPrice) * $quantity, 2);
            }
        }
    }
    if (isset($gownInfoStmt) && $gownInfoStmt) $gownInfoStmt->close();
    if (isset($cartStmt) && $cartStmt) $cartStmt->close();
    if (isset($cartLegacyStmt) && $cartLegacyStmt) $cartLegacyStmt->close();
} else {
    // Fallback to single product for backward compatibility
    $model = isset($_GET['model']) ? htmlspecialchars($_GET['model']) : '';
    $image = isset($_GET['image']) ? htmlspecialchars($_GET['image']) : '';
    $name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'Product Name';
    $category = isset($_GET['category']) ? htmlspecialchars($_GET['category']) : 'Category';
    $size = isset($_GET['size']) ? htmlspecialchars($_GET['size']) : '';
    $price = isset($_GET['price']) ? floatval($_GET['price']) : 1000;
    $displayPrice = $price;
    $products = [[
        'id' => 0,
        'product_id' => 0,
        'modelPath' => $model,
        'imagePath' => $image,
        'name' => $name,
        'category' => $category,
        'price' => $price,
        'purchase_price' => 0,
        'quantity' => 1,
        'status' => 'reserve',
        'reserve_date' => $reserveDate,
        'return_date' => $returnDate,
        'reserve_time' => $reserveTime,
        'return_time' => $returnTime,
        'size_1' => $size,
        'size_2' => '', 'size_3' => '', 'size_4' => '', 'size_5' => '',
        'size_6' => '', 'size_7' => '', 'size_8' => '', 'size_9' => '', 'size_10' => '',
        'total' => $price
    ]];
}

$displayPrice = round($displayPrice, 2);
$totalSaved = round($totalSaved, 2);
$originalSubtotal = round($displayPrice + $totalSaved, 2);
$firstPaymentAmount = min(500.00, $displayPrice);
$remainingBalanceAmount = max(0.00, round($displayPrice - $firstPaymentAmount, 2));

// Load per-item measurements and category size labels (used by the View Items overlay)
if (isset($conn) && $conn) {
    $labelSql = "SELECT size_label_1, size_label_2, size_label_3, size_label_4, size_label_5, size_label_6, size_label_7, size_label_8, size_label_9, size_label_10 FROM categories WHERE LOWER(TRIM(category_name)) = LOWER(TRIM(?)) LIMIT 1";
    $labelStmt = $conn->prepare($labelSql);
    $itemsStmt = $conn->prepare("SELECT * FROM saved_products_items WHERE saved_product_id = ? ORDER BY item_number ASC");

    foreach ($products as &$productRef) {
        // Category size labels
        $sizeLabels = [];
        if ($labelStmt) {
            $labelStmt->bind_param("s", $productRef['category']);
            $labelStmt->execute();
            $labelResult = $labelStmt->get_result();
            if ($labelRow = $labelResult->fetch_assoc()) {
                for ($i = 1; $i <= 10; $i++) {
                    $label = trim((string)($labelRow["size_label_{$i}"] ?? ''));
                    if ($label !== '') {
                        $sizeLabels[] = ['index' => $i, 'label' => $label];
                    }
                }
            }
        }
        $productRef['size_labels'] = $sizeLabels;

        // Item 1 measurements come from saved_products
        $items = [];
        $firstItem = ['item_number' => 1];
        for ($i = 1; $i <= 10; $i++) {
            $firstItem["size_{$i}"] = $productRef["size_{$i}"] ?? '';
        }
        $items[] = $firstItem;

        // Item 2+ measurements come from saved_products_items or directly from items payload
        if ($itemsStmt && !empty($productRef['id'])) {
            $savedProductId = intval($productRef['id']);
            $itemsStmt->bind_param("i", $savedProductId);
            $itemsStmt->execute();
            $itemsResult = $itemsStmt->get_result();
            while ($itemRow = $itemsResult->fetch_assoc()) {
                $item = ['item_number' => intval($itemRow['item_number'])];
                for ($i = 1; $i <= 10; $i++) {
                    $item["size_{$i}"] = $itemRow["size_{$i}"] ?? '';
                }
                $items[] = $item;
            }
        } elseif (!empty($productRef['items']) && is_array($productRef['items'])) {
            $items = $productRef['items'];
        }

        // Make sure every reserved item is represented
        $itemCount = max(1, intval($productRef['quantity'] ?? 1));
        for ($n = count($items) + 1; $n <= $itemCount; $n++) {
            $item = ['item_number' => $n];
            for ($i = 1; $i <= 10; $i++) {
                $item["size_{$i}"] = $productRef["size_{$i}"] ?? '';
            }
            $items[] = $item;
        }

        $productRef['items'] = $items;
    }
    unset($productRef);

    if ($labelStmt) $labelStmt->close();
    if ($itemsStmt) $itemsStmt->close();
}

// Handle proof of payment upload via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_proof') {
    header('Content-Type: application/json');
    if (!isset($conn) || !$conn) {
        $database = new Database();
        $conn = $database->getConnection();
    }

    if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid image file.']);
        exit;
    }

    $fileTmp = $_FILES['proof_image']['tmp_name'];
    $fileName = $_FILES['proof_image']['name'];
    $fileSize = $_FILES['proof_image']['size'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!in_array($ext, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, WEBP, and GIF images are allowed.']);
        exit;
    }

    if ($fileSize > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit.']);
        exit;
    }

    $uploadDir = __DIR__ . '/uploads/payment_proofs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newFileName = 'proof_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $uploadDir . $newFileName;
    $relPath = 'uploads/payment_proofs/' . $newFileName;

    $moved = is_uploaded_file($fileTmp) ? move_uploaded_file($fileTmp, $destPath) : copy($fileTmp, $destPath);

    if ($moved) {
        $uId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($userId) ? intval($userId) : 0);
        $resGroupId = trim($_POST['reservation_group_id'] ?? '');

        // Ensure table exists
        $conn->query("
            CREATE TABLE IF NOT EXISTS payment_proofs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT 0,
                reservation_group_id VARCHAR(50) DEFAULT NULL,
                image_path VARCHAR(500) NOT NULL,
                payment_method VARCHAR(50) DEFAULT 'QR Ph (RCBC)',
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $stmt = $conn->prepare("INSERT INTO payment_proofs (user_id, reservation_group_id, image_path, payment_method) VALUES (?, ?, ?, 'QR Ph (RCBC)')");
        if ($stmt) {
            $stmt->bind_param("iss", $uId, $resGroupId, $relPath);
            $stmt->execute();
        }

        echo json_encode([
            'success' => true,
            'file_path' => $relPath,
            'message' => 'Payment proof uploaded successfully!'
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image.']);
        exit;
    }
}

// Handle reservation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reserve') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (!isset($conn) || !$conn) {
        $database = new Database();
        $conn = $database->getConnection();
    }
    if (!$conn) {
        $response['message'] = 'Database connection failed';
        echo json_encode($response);
        exit;
    }

    try {
        if (empty($products)) {
            throw new Exception('No products to reserve');
        }

        // Require admin-verified valid ID before any reserve or buy
        if ($userId <= 0) {
            throw new Exception('Please log in to continue.');
        }
        $idChk = $conn->prepare("SELECT COALESCE(id_front_image, '') AS f, COALESCE(id_back_image, '') AS b, COALESCE(id_verified, 0) AS v FROM system_users WHERE id = ? LIMIT 1");
        $idChk->bind_param("i", $userId);
        $idChk->execute();
        $idRow = $idChk->get_result()->fetch_assoc();
        $idChk->close();
        if (!$idRow || ($idRow['f'] === '' && $idRow['b'] === '')) {
            throw new Exception('Please upload a valid ID in your Profile before you can reserve or buy.');
        }
        if ((int)$idRow['v'] === 2) {
            throw new Exception('Your uploaded ID was rejected by the admin. Please upload a new valid ID in your Profile.');
        }
        if ((int)$idRow['v'] !== 1) {
            throw new Exception('Your ID is pending admin verification. You can reserve or buy once it is verified.');
        }

        $paymentMethod = 'QR Ph (RCBC)';
        $paymentOption = $_POST['payment_option'] ?? 'full'; // 'full' or 'first_payment'
        $paymentProof = trim($_POST['payment_proof'] ?? '');

        // Ensure columns exist in database table
        $conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reservation_group_id VARCHAR(50) DEFAULT NULL AFTER user_id");
        $conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reservation_code VARCHAR(50) DEFAULT NULL AFTER reservation_group_id");
        $conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS payment_option VARCHAR(50) DEFAULT 'full' AFTER payment_method");
        $conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS payment_proof VARCHAR(500) DEFAULT NULL AFTER payment_option");
        $conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS downpayment_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_proof");
        $conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS balance_amount DECIMAL(10,2) DEFAULT 0.00 AFTER downpayment_amount");
        $conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS product_status VARCHAR(20) DEFAULT 'reserve' AFTER order_status");

        // Generate a single reservation group ID and unique alphanumeric reference code for this checkout
        $reservationGroupId = 'RES-' . date('YmdHis') . '-' . substr(uniqid(), -4);
        $uniqueHex = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
        $reservationCode = 'CG-' . date('ymd') . '-' . $uniqueHex;

        $insertSql = "INSERT INTO reserve_products (user_id, reservation_group_id, reservation_code, product_id, product_name, category, price, model_path, first_name, last_name, payment_method, payment_option, payment_proof, downpayment_amount, balance_amount, total_price, reserve_date, return_date, reserve_time, return_time, size_1, size_2, size_3, size_4, size_5, size_6, size_7, size_8, size_9, size_10, quantity, order_status, product_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
        $stmt = $conn->prepare($insertSql);
        if (!$stmt) {
            throw new Exception('Failed to prepare reservation query: ' . $conn->error);
        }

        // 32 variables: user_id(i), reservation_group_id(s), reservation_code(s), product_id(i), name(s), category(s), price(d), model_path(s),
        // first_name(s), last_name(s), payment_method(s), payment_option(s), payment_proof(s), downpayment(d), balance(d),
        // total_price(d), 2 dates(s,s), 2 times(s,s), size_1..size_10(10 s), quantity(i), product_status(s)
        $types = "ississdssssssdddssss" . str_repeat("s", 10) . "is";

        // Prepare item insert statement for Item 2+ sizes
        $insertItemSql = "INSERT INTO reserve_products_items
                          (reserve_product_id, item_number, size_1, size_2, size_3, size_4, size_5, size_6, size_7, size_8, size_9, size_10)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $itemStmt = $conn->prepare($insertItemSql);
        if (!$itemStmt) {
            throw new Exception('Failed to prepare reserve items query: ' . $conn->error);
        }
        $itemTypes = "iissssssssss";
        $remainingDownpaymentToAllocate = ($paymentOption === 'first_payment') ? 500.00 : 0.00;

        foreach ($products as $product) {
            $productStatus = ($product['status'] ?? 'reserve') === 'buy' ? 'buy' : 'reserve';
            $unitPrice = $productStatus === 'buy'
                ? floatval($product['purchase_price'] ?? 0)
                : floatval($product['price'] ?? 0);

            $quantity = max(1, intval($product['quantity'] ?? 1));
            $productTotal = floatval($product['total'] ?? ($unitPrice * $quantity));

            // Server-side validation for Buy: ensure purchase date does not conflict with active reservations
            if ($productStatus === 'buy') {
                $chkPid = intval($product['product_id'] ?? 0);
                $chkName = trim($product['name'] ?? '');
                $chkCat = trim($product['category'] ?? '');
                $chkDate = trim($product['reserve_date'] ?? $reserveDate);

                // Check gowns table for available physical stock
                $gStock = 0;
                if ($chkPid > 0) {
                    $gStmt = $conn->prepare("SELECT stock FROM gowns WHERE id = ?");
                    $gStmt->bind_param("i", $chkPid);
                    $gStmt->execute();
                    $gRow = $gStmt->get_result()->fetch_assoc();
                    $gStmt->close();
                    if ($gRow) $gStock = intval($gRow['stock'] ?? 0);
                }
                if ($quantity > $gStock) {
                    throw new Exception("Cannot purchase " . htmlspecialchars($product['name'] ?? 'product') . ": requested quantity ({$quantity}) exceeds available stock ({$gStock}).");
                }

                // Check future reservations that conflict with this purchase
                if (!empty($chkDate)) {
                    $chkSql = "SELECT rp.id, rp.reserve_date, rp.return_date, rp.quantity,
                                      (SELECT COUNT(*) FROM reserve_products_items rpi WHERE rpi.reserve_product_id = rp.id) as additional_items
                               FROM reserve_products rp
                               WHERE (rp.product_id = ? OR (rp.product_name = ? AND rp.category = ?))
                               AND rp.order_status != 'cancelled'
                               AND rp.product_status != 'buy'
                               AND rp.return_date >= DATE_SUB(?, INTERVAL 2 DAY)";
                    $chkStmt = $conn->prepare($chkSql);
                    $chkStmt->bind_param("isss", $chkPid, $chkName, $chkCat, $chkDate);
                    $chkStmt->execute();
                    $chkRes = $chkStmt->get_result();

                    $dailyReserved = [];
                    while ($rRow = $chkRes->fetch_assoc()) {
                        $rStart = substr($rRow['reserve_date'] ?? '', 0, 10);
                        $rEnd = substr($rRow['return_date'] ?? '', 0, 10);
                        if (empty($rEnd) || $rEnd < $rStart) $rEnd = $rStart;
                        $rCount = max(intval($rRow['quantity'] ?? 1), 1 + intval($rRow['additional_items'] ?? 0));

                        $dStart = new DateTime($rStart);
                        $dStart->modify('-2 days');
                        $dEnd = new DateTime($rEnd);
                        $dEnd->modify('+2 days');

                        $period = new DatePeriod($dStart, new DateInterval('P1D'), (clone $dEnd)->modify('+1 day'));
                        foreach ($period as $dDay) {
                            $dKey = $dDay->format('Y-m-d');
                            $dailyReserved[$dKey] = ($dailyReserved[$dKey] ?? 0) + $rCount;
                        }
                    }
                    $chkStmt->close();

                    foreach ($dailyReserved as $dKey => $resQty) {
                        if ($dKey >= $chkDate && ($resQty + $quantity) > $gStock) {
                            throw new Exception("The selected purchase date ({$chkDate}) for " . htmlspecialchars($product['name'] ?? 'product') . " conflicts with an existing reservation. You may only purchase after all reservations conclude.");
                        }
                    }
                }
            }

            if ($paymentOption === 'first_payment') {
                $downpayment = min($productTotal, $remainingDownpaymentToAllocate);
                $remainingDownpaymentToAllocate = round($remainingDownpaymentToAllocate - $downpayment, 2);
                $balance = round($productTotal - $downpayment, 2);
            } else {
                $downpayment = $productTotal;
                $balance = 0.00;
            }

            // Insert one row per product (Item 1 sizes in main row)
            $params = [
                $userId,
                $reservationGroupId,
                $reservationCode,
                $product['product_id'] ?? 0,
                $product['name'],
                $product['category'],
                $unitPrice,
                $product['modelPath'],
                $userFirstName,
                $userLastName,
                $paymentMethod,
                $paymentOption,
                $paymentProof,
                $downpayment,
                $balance,
                $productTotal,
                $product['reserve_date'] ?: $reserveDate,
                $product['return_date'] ?: $returnDate,
                formatReserveTime($product['reserve_time'] ?: $reserveTime),
                formatReserveTime($product['return_time'] ?: $returnTime),
                $product['size_1'] ?? '',
                $product['size_2'] ?? '',
                $product['size_3'] ?? '',
                $product['size_4'] ?? '',
                $product['size_5'] ?? '',
                $product['size_6'] ?? '',
                $product['size_7'] ?? '',
                $product['size_8'] ?? '',
                $product['size_9'] ?? '',
                $product['size_10'] ?? '',
                $quantity,
                $productStatus
            ];
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) {
                throw new Exception('Failed to save reservation: ' . $stmt->error);
            }

            // Insert Item 2+ sizes into reserve_products_items
            $reserveProductId = $conn->insert_id;
            $items = $product['items'] ?? [];
            if (is_array($items)) {
                foreach ($items as $item) {
                    $itemNumber = intval($item['item_number'] ?? 0);
                    if ($itemNumber < 2) continue;

                    $itemParams = [
                        $reserveProductId,
                        $itemNumber,
                        $item['size_1'] ?? '',
                        $item['size_2'] ?? '',
                        $item['size_3'] ?? '',
                        $item['size_4'] ?? '',
                        $item['size_5'] ?? '',
                        $item['size_6'] ?? '',
                        $item['size_7'] ?? '',
                        $item['size_8'] ?? '',
                        $item['size_9'] ?? '',
                        $item['size_10'] ?? ''
                    ];
                    $itemStmt->bind_param($itemTypes, ...$itemParams);
                    if (!$itemStmt->execute()) {
                        throw new Exception('Failed to save item ' . $itemNumber . ' sizes: ' . $itemStmt->error);
                    }
                }
            }

            // Reduce stock from gowns table — only for "buy" status, not "reserve"
            $productId = intval($product['product_id'] ?? 0);
            $productStatus = $product['status'] ?? 'reserve';
            if ($productId > 0 && $productStatus === 'buy') {
                $stockSql = "UPDATE gowns SET stock = GREATEST(0, stock - ?) WHERE id = ?";
                $stockStmt = $conn->prepare($stockSql);
                if ($stockStmt) {
                    $stockStmt->bind_param("ii", $quantity, $productId);
                    $stockStmt->execute();
                    $stockStmt->close();
                }
            }

            // Remove from saved_products (cart) ONLY if it was checked out from the cart
            if ($userId > 0 && !empty($product['id']) && intval($product['id']) > 0) {
                $savedId = intval($product['id']);
                $conn->query("DELETE FROM saved_products_items WHERE saved_product_id = $savedId");
                $conn->query("DELETE FROM saved_products WHERE id = $savedId AND user_id = $userId");
            }
        }

        $response['success'] = true;
        $response['reservation_group_id'] = $reservationGroupId;
        $response['reservation_code'] = $reservationCode;
        $response['message'] = 'Reservation saved successfully';
    } catch (Throwable $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// Pass PHP data to JavaScript
$scriptData = [
    'userId' => $userId,
    'isLoggedIn' => $userId > 0,
    'products' => $products,
    'totalPrice' => $displayPrice,
    'reserveDate' => $reserveDate,
    'returnDate' => $returnDate,
    'reserveTime' => $reserveTime,
    'returnTime' => $returnTime
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Reserve - ClosetGo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/homepage.css?v=<?php echo @filemtime('css/homepage.css'); ?>">
    <link id="dynamicFavicon" rel="icon" href="images/icon/JMB.jpg" type="image/jpeg">
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.1.1/model-viewer.min.js"></script>
    <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.14/dist/dotlottie-wc.js" type="module"></script>
    <style>
        /* Remove guide line from product box */
        .product-reserve .save-content {
            border-top: none !important;
        }
        
        /* Multiple products container */
        .products-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 10px;
            margin-top: -50px;
            padding-bottom: 140px;
        }
        
        /* Wider product boxes for reserve page */
        .product-reserve .login-box {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 15px;
        }

        .product-reserve .left-box {
            width: 80px;
            height: 80px;
        }

        .product-reserve .hi-container {
            gap: 10px;
        }

        /* Payment Option & Details Cards */
        .payment-option-card, .payment-details-card {
            background: #2a2a2a;
            border: 1px solid #3c3c3c;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
            box-sizing: border-box;
        }

        .payment-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 1px solid #3a3a3a;
            margin-bottom: 14px;
            gap: 10px;
        }

        .payment-card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
        }

        .payment-badge-note {
            font-size: 0.72rem;
            color: #aaa;
            background: #222222;
            padding: 3px 8px;
            border-radius: 12px;
            border: 1px solid #444;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .payment-badge-green {
            font-size: 0.72rem;
            color: #6cd488;
            background: rgba(108, 212, 136, 0.12);
            padding: 3px 9px;
            border-radius: 12px;
            border: 1px solid rgba(108, 212, 136, 0.3);
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .payment-badge-gold {
            font-size: 0.72rem;
            color: #ffd700;
            background: rgba(255, 215, 0, 0.12);
            padding: 3px 9px;
            border-radius: 12px;
            border: 1px solid rgba(255, 215, 0, 0.3);
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .payment-options-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .payment-option-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #222224;
            border: 1.5px solid #38383f;
            border-radius: 10px;
            padding: 12px 14px;
            cursor: pointer;
            transition: all 0.25s ease;
            user-select: none;
        }

        .payment-option-item:hover {
            border-color: #555;
            background: #28282c;
        }

        .payment-option-item.active {
            border-color: #ffd700;
            background: rgba(255, 215, 0, 0.08);
            box-shadow: 0 0 14px rgba(255, 215, 0, 0.18);
        }

        .payment-option-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .custom-radio {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #555;
            display: flex;
            align-items: center;
            justify-content: center;
            color: transparent;
            font-size: 0.65rem;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .payment-option-item.active .custom-radio {
            border-color: #ffd700;
            background: #ffd700;
            color: #000;
        }

        .option-title {
            display: block;
            font-size: 0.85rem;
            color: #fff;
            font-weight: 600;
        }

        .payment-option-item.active .option-title {
            color: #ffd700;
        }

        .option-desc {
            margin: 2px 0 0 0;
            font-size: 0.72rem;
            color: #888;
        }

        .payment-option-price {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ff4444;
            white-space: nowrap;
        }

        .payment-details-body {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .payment-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.82rem;
        }

        .detail-name {
            color: #bbb;
        }

        .detail-val {
            color: #eee;
            font-weight: 500;
        }

        .payment-divider {
            height: 1px;
            background: #3a3a40;
            margin: 6px 0;
        }

        .total-due-row {
            background: #18181b;
            border: 1px solid #33333a;
            border-radius: 8px;
            padding: 10px 12px;
            margin-top: 4px;
        }

        .total-due-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #fff;
        }

        .total-due-sub {
            display: block;
            font-size: 0.68rem;
            color: #888;
            margin-top: 2px;
        }

        .total-due-amount {
            font-size: 1.15rem;
            font-weight: 700;
            color: #ff4444;
        }
        
        /* Responsive for mobile */
        @media (max-width: 768px) {
            .products-container {
                padding: 10px 10px 140px 10px;
                gap: 12px;
            }
            
            .product-reserve .login-box {
                max-width: 100%;
                width: 100%;
            }

            .payment-card-title {
                font-size: 0.85rem;
            }

            .payment-badge-note, .payment-badge-green, .payment-badge-gold {
                font-size: 0.68rem;
                padding: 2px 7px;
            }
        }
        
        /* Bottom Bar Styling */
        .login-box-bottom-bar {
         
            padding: 1px;
            text-align: center;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: -15px;
            margin-left: -20px;
            margin-right: -20px;
            
        }
        
      
        
        .view-details-text {
            color: gray;
            font-size: 13px;
            letter-spacing: 0.5px;
            border-top: 1px solid #ddd;
            padding-top: 13px;
            display: block;
        }

        /* Login box footer with solid line and total items */
        .login-box-footer {
            border-top: 1px solid #7c6300;
            padding: 8px 15px 4px 15px;
            margin: 5px -15px 0 -15px;
            width: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .box-total {
            font-size: 0.75rem;
            color: #7c6300;
            font-weight: 600;
        }

        .box-total-price {
            font-size: 0.75rem;
            color: #ff0000;
            font-weight: 600;
        }
        
        /* Details Overlay Styling */
        .details-overlay {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(100%);
            transition: transform 0.3s ease;
            z-index: 1000;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .details-overlay.active {
            transform: translateY(0);
        }
        
        .details-overlay-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .details-overlay-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .details-close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 5px;
        }
        
        .details-overlay-content {
            padding: 20px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .detail-value {
            color: #666;
            font-size: 14px;
        }
        
        .overlay-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 999;
        }
        
        .overlay-backdrop.active {
            display: block;
        }
        
        /* Mobile App Bar */
        .mobile-app-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #2a2a2a;
            border-top: 1px solid #7c6300;
            box-shadow: 0 -2px 20px rgba(0, 0, 0, 0.1);
            padding: 0px;
            height: 50px;
            z-index: 1000;
            display: block;
        }
        
        .app-bar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            height: 100%;
            padding: 0;
            margin: 0;
        }
        
        .app-bar-left {
            display: flex;
            align-items: center;
            padding-left: 15px;
            flex: 1;
        }
        
        .total-price {
            color: #ffffff;
            font-size: 12px;
            font-weight: 300;
        }

        .price-amount {
            font-size: 0.85rem;
            color: #ff4444;
            font-weight: 600;
        }

        .saved-price {
            color: #4caf50;
            font-size: 10px;
            font-weight: 300;
            margin-top: 2px;
        }

        .saved-amount {
            color: #4caf50;
            font-size: 10px;
            font-weight: 600;
        }
        
        .reserve-button {
            background: #2a2a2a;
            color: #ffffff;
            border: none;
            padding: 9.5px 20px;
            font-size: 13px;
            margin-left: 10px;
            font-weight: 300;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: inset 0 0 15px rgba(124, 99, 0, 0.4);
            width: calc(100% - 60px);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .reserve-button:hover {
            background: #3a3a3a;
            transform: translateY(-2px);
            box-shadow: inset 0 0 20px rgba(124, 99, 0, 0.6);
        }
        
        .reserve-button:active {
            transform: translateY(0);
            box-shadow: inset 0 0 25px rgba(124, 99, 0, 0.8);
        }

        /* ===== GCash Payment Styles ===== */
        .gcash-section-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 10px;
        }

        .payment-method-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1e1e22;
            border: 1.5px solid #38383f;
            border-radius: 10px;
            padding: 12px 14px;
            cursor: pointer;
            transition: all 0.25s ease;
            user-select: none;
        }

        .payment-method-item:hover {
            border-color: #007aff;
            background: rgba(0, 122, 255, 0.05);
        }

        .payment-method-item.gcash-selected {
            border-color: #007aff;
            background: rgba(0, 122, 255, 0.08);
            box-shadow: 0 0 14px rgba(0, 122, 255, 0.2);
        }

        /* GCash circle radio */
        .gcash-radio {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #555;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s ease;
            background: transparent;
        }

        .gcash-radio.active {
            border-color: #007aff;
            background: #007aff;
            box-shadow: 0 0 8px rgba(0, 122, 255, 0.5);
        }

        .gcash-radio-inner {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #fff;
            opacity: 0;
            transform: scale(0);
            transition: all 0.2s ease;
        }

        .gcash-radio.active .gcash-radio-inner {
            opacity: 1;
            transform: scale(1);
        }

        /* GCash logo */
        .gcash-logo-wrap {
            width: 34px;
            height: 34px;
            background: linear-gradient(135deg, #0059c1, #007aff);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .gcash-logo-text {
            font-size: 0.68rem;
            font-weight: 900;
            color: #fff;
            letter-spacing: -0.5px;
        }

        /* Status badges */
        .gcash-status-pending {
            font-size: 0.68rem;
            color: #888;
            background: #222;
            padding: 3px 9px;
            border-radius: 10px;
            border: 1px solid #444;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .gcash-status-paid {
            font-size: 0.68rem;
            color: #4caf50;
            background: rgba(76, 175, 80, 0.12);
            padding: 3px 9px;
            border-radius: 10px;
            border: 1px solid rgba(76, 175, 80, 0.35);
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* QR Panel */
        .gcash-qr-panel {
            margin-top: 14px;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid rgba(0, 122, 255, 0.35);
            background: #16181e;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            animation: fadeSlideIn 0.3s ease;
        }

        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .gcash-qr-inner {
            padding: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .gcash-qr-label {
            font-size: 0.78rem;
            color: #aaa;
            text-align: center;
            margin: 0;
        }

        /* QR Ph Highlight Instruction Card (Matched to top QR Ph RCBC box) */
        .qrph-highlight-card {
            width: 100%;
            box-sizing: border-box;
            background: rgba(0, 122, 255, 0.08);
            border: 1px solid rgba(0, 122, 255, 0.4);
            border-radius: 10px;
            padding: 12px 14px;
            box-shadow: 0 2px 10px rgba(0, 122, 255, 0.08);
            position: relative;
            overflow: hidden;
            margin-bottom: 4px;
        }

        .qrph-highlight-top {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .qrph-icon-glow {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: rgba(0, 122, 255, 0.2);
            color: #93c5fd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
            border: 1px solid rgba(0, 122, 255, 0.35);
        }

        .qrph-highlight-text strong {
            display: block;
            font-size: 0.85rem;
            color: #ffffff;
            font-weight: 700;
            line-height: 1.35;
            letter-spacing: 0.1px;
        }

        .qrph-highlight-text p {
            margin: 3px 0 0 0;
            font-size: 0.72rem;
            color: #93c5fd;
            font-weight: 500;
        }

        /* App Logos & Badges Row */
        .qrph-badges-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            padding-top: 10px;
            border-top: 1px dashed rgba(0, 122, 255, 0.3);
        }

        .qrph-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 9px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            transition: all 0.2s;
            user-select: none;
        }

        .badge-qrph {
            background: #ffffff;
            color: #0b1f44;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
        }

        .qrph-badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ef4444 0%, #3b82f6 50%, #facc15 100%);
        }

        .badge-rcbc {
            background: linear-gradient(135deg, #002868, #0a4ba0);
            color: #ffffff;
            border: 1px solid rgba(147, 197, 253, 0.4);
            box-shadow: 0 2px 6px rgba(0, 40, 104, 0.3);
        }

        .badge-gcash {
            background: #007aff;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 2px 8px rgba(0, 122, 255, 0.35);
        }

        .brand-g {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14px;
            height: 14px;
            background: #fff;
            color: #007aff;
            font-weight: 900;
            border-radius: 50%;
            font-size: 9px;
            font-family: sans-serif;
            line-height: 1;
        }

        .badge-maya {
            background: #00d66c;
            color: #002816;
            border: 1px solid rgba(0, 214, 108, 0.6);
            box-shadow: 0 2px 8px rgba(0, 214, 108, 0.3);
        }

        .brand-m {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14px;
            height: 14px;
            background: #002816;
            color: #00d66c;
            font-weight: 900;
            border-radius: 4px;
            font-size: 9px;
            font-family: sans-serif;
            line-height: 1;
        }

        .badge-banks {
            background: rgba(255, 255, 255, 0.08);
            color: #eee;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Save QR to Photos Button */
        .qr-save-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffd700;
            font-size: 0.76rem;
            font-weight: 600;
            padding: 7px 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: -2px;
            margin-bottom: 2px;
        }

        .qr-save-btn:hover {
            background: rgba(255, 215, 0, 0.15);
            border-color: #ffd700;
            transform: translateY(-1px);
        }

        /* Interactive Guide Card */
        .qrph-guide-card {
            width: 100%;
            box-sizing: border-box;
            background: #18191f;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 4px;
        }

        .qrph-guide-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: #1f2026;
            cursor: pointer;
            user-select: none;
            transition: background 0.2s;
        }

        .qrph-guide-header:hover {
            background: #272832;
        }

        .qrph-guide-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #eee;
        }

        .qrph-guide-toggle-icon {
            color: #888;
            font-size: 0.75rem;
            transition: transform 0.2s;
        }

        .qrph-guide-body {
            padding: 12px 14px;
        }

        .qrph-guide-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 12px;
            overflow-x: auto;
            padding-bottom: 4px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .qrph-guide-tabs::-webkit-scrollbar {
            display: none;
        }

        .qrph-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.73rem;
            font-weight: 600;
            background: #131317;
            border: 1px solid #33333e;
            color: #bbb;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s ease;
        }

        .qrph-tab-btn:hover {
            background: #252630;
            color: #fff;
        }

        .qrph-tab-btn.active {
            background: #007aff;
            border-color: #007aff;
            color: #fff;
            box-shadow: 0 2px 8px rgba(0, 122, 255, 0.4);
        }

        .brand-g-small, .brand-m-small {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            font-size: 8px;
            font-weight: 900;
            font-family: sans-serif;
            line-height: 1;
        }

        .brand-g-small {
            background: #fff;
            color: #007aff;
        }

        .brand-m-small {
            background: #00d66c;
            color: #002816;
            border-radius: 3px;
        }

        .guide-step {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.76rem;
            color: #ccc;
            line-height: 1.45;
            margin-bottom: 8px;
        }

        .guide-step:last-child {
            margin-bottom: 0;
        }

        .guide-step strong {
            color: #fff;
        }

        .step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: rgba(255, 215, 0, 0.18);
            border: 1px solid rgba(255, 215, 0, 0.4);
            color: #ffd700;
            font-size: 0.68rem;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* GCash Account Info Box */
        .gcash-account-box {
            width: 100%;
            background: rgba(0, 122, 255, 0.08);
            border: 1px dashed rgba(0, 122, 255, 0.4);
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-sizing: border-box;
            gap: 10px;
        }

        .gcash-account-details {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .gcash-account-name {
            font-size: 0.72rem;
            color: #888;
            font-weight: 500;
        }

        .gcash-account-num {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.5px;
        }

        .gcash-copy-btn {
            background: rgba(0, 122, 255, 0.2);
            border: 1px solid #007aff;
            color: #007aff;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .gcash-copy-btn:hover {
            background: #007aff;
            color: #ffffff;
        }

        /* Pay via GCash App Button */
        .gcash-open-app-btn {
            width: 100%;
            background: #007aff;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 10px 0;
            font-size: 0.82rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
            transition: all 0.2s ease;
        }

        .gcash-open-app-btn:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }

        .gcash-open-app-btn img {
            width: 18px;
            height: 18px;
            object-fit: contain;
        }

        .gcash-qr-img-wrap {
            position: relative;
            width: 190px;
            height: 190px;
            border-radius: 12px;
            overflow: hidden;
            background: #ffffff;
            border: 2px solid #007aff;
            box-shadow: 0 4px 20px rgba(0, 122, 255, 0.35);
        }

        .gcash-qr-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .gcash-qr-amount-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.85);
            color: #ffd700;
            font-size: 0.95rem;
            font-weight: 700;
            text-align: center;
            padding: 4px 0;
            letter-spacing: 0.5px;
            border-top: 1px solid rgba(255, 215, 0, 0.3);
        }

        /* Proof of Payment Upload Section */
        .gcash-proof-section {
            width: 100%;
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 12px;
            box-sizing: border-box;
            margin-top: 4px;
        }

        /* Non-Refundable Notice Banner (matching purchase.php settlement details banner card design) */
        .reservation-non-refundable-banner {
            background: rgba(220, 38, 38, 0.12);
            border: 1.5px solid rgba(239, 68, 68, 0.55);
            border-radius: 12px;
            padding: 12px 14px;
            margin-top: 10px;
            box-shadow: 0 4px 14px rgba(220, 38, 38, 0.15);
            transition: all 0.25s ease;
        }

        .reservation-non-refundable-banner .notice-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .reservation-non-refundable-banner .notice-title {
            color: #ef4444;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.2px;
        }

        .reservation-non-refundable-banner .notice-pill {
            font-size: 10.5px;
            padding: 3px 9px;
            border-radius: 999px;
            background: rgba(239, 68, 68, 0.25);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.5);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .reservation-non-refundable-banner .notice-desc {
            font-size: 11.5px;
            color: #fca5a5;
            line-height: 1.45;
        }

        .gcash-proof-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
        }

        .gcash-proof-title .req-badge {
            background: #ff3b30;
            color: #fff;
            font-size: 0.6rem;
            padding: 2px 5px;
            border-radius: 4px;
            font-weight: 700;
        }

        .gcash-dropzone {
            border: 2px dashed #444;
            border-radius: 10px;
            padding: 14px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s ease;
            background: rgba(255, 255, 255, 0.02);
            position: relative;
        }

        .gcash-dropzone:hover {
            border-color: #007aff;
            background: rgba(0, 122, 255, 0.05);
        }

        .gcash-dropzone.has-file {
            border-color: #4cd964;
            background: rgba(76, 175, 80, 0.08);
            padding: 8px;
        }

        .gcash-dropzone.shake {
            animation: dropzoneShake 0.4s ease;
            border-color: #ff3b30 !important;
            background: rgba(255, 59, 48, 0.1) !important;
        }

        @keyframes dropzoneShake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }

        .dropzone-prompt i {
            font-size: 1.4rem;
            color: #007aff;
            margin-bottom: 4px;
        }

        .dropzone-prompt p {
            margin: 2px 0 0;
            font-size: 0.74rem;
            color: #ccc;
        }

        .dropzone-prompt span {
            font-size: 0.68rem;
            color: #777;
        }

        .proof-preview-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
        }

        .proof-preview-thumb {
            width: 54px;
            height: 54px;
            border-radius: 8px;
            object-fit: cover;
            border: 1.5px solid #4cd964;
            flex-shrink: 0;
        }

        .proof-file-info {
            flex: 1;
            min-width: 0;
        }

        .proof-file-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .proof-file-status {
            font-size: 0.68rem;
            color: #4cd964;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 2px;
        }

        .proof-remove-btn {
            background: rgba(255, 59, 48, 0.15);
            border: 1px solid rgba(255, 59, 48, 0.4);
            color: #ff453a;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .proof-remove-btn:hover {
            background: #ff3b30;
            color: #ffffff;
        }

        .gcash-paid-btn {
            width: 100%;
            background: linear-gradient(135deg, #0059c1, #007aff);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 0;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
            margin-top: 6px;
        }

        .gcash-paid-btn:hover {
            background: linear-gradient(135deg, #004cb0, #005fe0);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(0, 122, 255, 0.4);
        }

        .gcash-paid-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Confirmed banner */
        .gcash-confirmed-banner {
            margin-top: 14px;
            background: rgba(76, 175, 80, 0.12);
            border: 1px solid rgba(76, 175, 80, 0.4);
            border-radius: 12px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeSlideIn 0.35s ease;
        }

        .gcash-confirmed-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2e7d32, #4caf50);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1rem;
            flex-shrink: 0;
            box-shadow: 0 0 12px rgba(76, 175, 80, 0.45);
            animation: popIn 0.4s cubic-bezier(0.175,0.885,0.32,1.275);
        }

        @keyframes popIn {
            from { transform: scale(0); }
            to   { transform: scale(1); }
        }

        .gcash-confirmed-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #4caf50;
        }

        .gcash-confirmed-sub {
            font-size: 0.7rem;
            color: #888;
            margin-top: 2px;
        }

        .gcash-confirmed-proof-thumb {
            width: 34px;
            height: 34px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #4cd964;
            margin-top: 4px;
            cursor: pointer;
        }

        .gcash-undo-btn {
            margin-left: auto;
            background: transparent;
            border: 1px solid #444;
            color: #888;
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 0.72rem;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .gcash-undo-btn:hover {
            border-color: #666;
            color: #ccc;
        }

        /* Pending / Disabled reserve button state */
        .reserve-button.disabled-btn {
            opacity: 0.75;
            cursor: pointer;
        }

        .reserve-button.disabled-btn:hover {
            opacity: 1;
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="product-reserve product-reserve-page">
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
                        <div class="notif-tab" onclick="filterNotifTab('chat', this)">
                            <span>Messages</span>
                            <span id="notifTabBadgeChat" class="notif-tab-badge" style="display: none;">0</span>
                        </div>
                    </div>
                    <div id="notifListContainer" class="notif-list-container">
                        <div class="notif-loading" style="padding: 24px; text-align: center; color: #888; font-size: 12px;"><i class="fas fa-spinner fa-spin"></i> Loading notifications...</div>
                    </div>
                </div>
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
                <span id="userChatBadge" style="display:none; position:absolute; top:-2px; right:0px; background:#ff3b30; width:9px; height:9px; border-radius:50%; box-shadow:0 0 6px rgba(255,59,48,0.9); border:1.5px solid #1a1a1a; box-sizing:border-box;"></span>
            </div>
        </div>
    </div>

    <!-- Mobile Top Bar -->
    <div class="top-bar">
        <div class="top-bar-content" style="justify-content: flex-start;">
            <img src="images/icon/back.png" alt="Back" class="back-button" onclick="goBack()">
            <span class="top-bar-text" style="margin-left: 10px;">Product Reserve</span>
        </div>
    </div>
    
    <!-- Products Container -->
    <div class="products-container" id="productsContainer">
        <?php foreach ($products as $index => $product) {
            $statusDisplay = ($product['status'] ?? 'reserve') === 'buy' ? '<span style="color: #28a745; font-weight: bold; font-size: 0.6rem;">BUY</span>' : '<span style="color: #007bff; font-weight: bold; font-size: 0.6rem;">RESERVE</span>';
            $unitPrice = ($product['status'] ?? 'reserve') === 'buy' ? floatval($product['purchase_price'] ?? 0) : floatval($product['price'] ?? 0);
            $quantity = max(1, intval($product['quantity'] ?? 1));
            $priceDisplay = $unitPrice * $quantity;
            $originalUnitPrice = floatval($product['original_price'] ?? $product['price'] ?? 0);
            $hasDiscount = ($product['status'] ?? 'reserve') !== 'buy' && floatval($product['discount'] ?? 0) > 0 && $originalUnitPrice > $unitPrice;
        ?>
            <div class="login-box">
                <div class="save-content">
                    <div class="hi-container">
                        <div class="left-box">
                            <?php 
                                $prodModel = trim($product['modelPath'] ?? '');
                                $prodImage = trim($product['imagePath'] ?? '');
                                if (!empty($prodImage)) { 
                            ?>
                                <img src="<?php echo htmlspecialchars($prodImage); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name'] ?? 'Product'); ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover; object-position: top; border-radius: 6px; display: block;">
                            <?php } elseif (!empty($prodModel)) { ?>
                                <model-viewer
                                    src="<?php echo htmlspecialchars($prodModel); ?>"
                                    alt="Product Model"
                                    auto-rotate
                                    camera-orbit="auto 90deg auto"
                                    camera-controls
                                    disable-pan
                                    disable-zoom
                                    min-camera-orbit="auto 90deg auto"
                                    max-camera-orbit="auto 90deg auto"
                                    style="width: 100%; height: 100%; border-radius: 8px;">
                                </model-viewer>
                            <?php } else { ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #2a2a2a; border-radius: 6px; color: #666;">
                                    <i class="fas fa-image" style="font-size: 1.6rem;"></i>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="product-info">
                            <div class="product-name"><?php echo $product['name']; ?></div>
                            <div class="product-category" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $product['category']; ?></div>
                            <?php if ($quantity > 1) { ?>
                                <div class="product-total-quantity" style="font-size: 0.7rem; color: #7c6300; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: left;">Items: <?php echo $quantity; ?></div>
                            <?php } ?>
                            <div class="product-price-row">
                                <div class="product-price">₱<?php echo number_format($unitPrice, 0); ?></div>
                                <?php if ($hasDiscount) { ?>
                                    <div class="product-original-price">₱<?php echo number_format($originalUnitPrice, 0); ?></div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="checkbox-container">
                            <div class="product-status"><?php echo $statusDisplay; ?></div>
                            <span class="edit-text" onclick="viewItems(<?php echo $index; ?>); event.stopPropagation();">View Items</span>
                        </div>
                    </div>
                </div>
                <div class="login-box-footer">
                    <span class="box-total">Total <?php echo $quantity; ?> Items</span>
                    <span class="box-total-price">₱<?php echo number_format($priceDisplay, 0); ?></span>
                </div>
            </div>
        <?php } ?>

        <!-- BOX 1: Choose Payment Option (First Payment vs Full Payment) -->
        <div class="login-box payment-option-card">
            <div class="payment-card-header">
                <span class="payment-card-title">Payment Option</span>
                <span class="payment-badge-note">Select payment terms</span>
            </div>
            
            <div class="payment-options-grid">
                <!-- Option 1: Full Payment (Auto-Selected) -->
                <label class="payment-option-item active" id="fullPaymentOption" onclick="selectPaymentOption('full')">
                    <div class="payment-option-left">
                        <input type="radio" name="payment_plan" value="full" checked style="display: none;">
                        <div class="custom-radio"><i class="fas fa-check"></i></div>
                        <div>
                            <strong class="option-title">Full Payment (100%)</strong>
                            <p class="option-desc">Pay the complete amount today</p>
                        </div>
                    </div>
                    <div class="payment-option-price" id="fullPaymentAmountDisplay">₱<?php echo number_format($displayPrice, 0); ?></div>
                </label>

                <!-- Option 2: First Payment (₱500 Deposit) -->
                <label class="payment-option-item" id="firstPaymentOption" onclick="selectPaymentOption('first_payment')">
                    <div class="payment-option-left">
                        <input type="radio" name="payment_plan" value="first_payment" style="display: none;">
                        <div class="custom-radio"><i class="fas fa-check"></i></div>
                        <div>
                            <strong class="option-title">First Payment (₱500 Deposit)</strong>
                            <p class="option-desc">Pay ₱500 now to secure booking (₱<?php echo number_format($firstPaymentAmount, 0); ?>), balance of ₱<?php echo number_format($remainingBalanceAmount, 0); ?> due on pickup</p>
                        </div>
                    </div>
                    <div class="payment-option-price" id="firstPaymentAmountDisplay">₱<?php echo number_format($firstPaymentAmount, 0); ?></div>
                </label>
            </div>

            <!-- Divider -->
            <div class="payment-divider" style="margin: 16px 0 14px;"></div>

            <!-- QR Ph / RCBC Payment Method -->
            <div class="gcash-section-title">Payment Method</div>

            <div class="payment-method-item gcash-selected" id="gcashMethodItem" onclick="selectGcashPayment()">
                <div class="payment-option-left" style="gap: 12px;">
                    <div class="gcash-radio active" id="gcashRadio">
                        <div class="gcash-radio-inner" id="gcashRadioInner"></div>
                    </div>
                    <div class="qrph-logo-wrap" style="width: 44px; height: 34px; background: linear-gradient(135deg, #002868, #0056b3); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,40,104,0.4);">
                        <span style="font-weight: 900; font-size: 0.78rem; color: #fff; letter-spacing: 0.5px;">QR<span style="color: #ffcc00;">Ph</span></span>
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <strong class="option-title" style="font-size:0.85rem;">QR Ph</strong>
                            <span style="font-size: 0.65rem; background: #002868; color: #93c5fd; border: 1px solid rgba(147,197,253,0.3); padding: 1px 6px; border-radius: 4px; font-weight: 700;">RCBC</span>
                        </div>
                    </div>
                </div>
                <div id="gcashStatusBadge" class="gcash-status-pending">Unpaid</div>
            </div>

            <!-- QR Ph / RCBC QR Panel (Shown automatically) -->
            <div id="gcashQrPanel" class="gcash-qr-panel" style="display:block;">
                <div class="gcash-qr-inner">
                    <!-- RCBC / QR Ph Account Details Box with Copy -->
                    <div class="gcash-account-box">
                        <div class="gcash-account-details">
                            <span style="font-size: 0.68rem; color: #93c5fd; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;">
                                <i class="fas fa-university"></i> QR Ph • Powered by RCBC
                            </span>
                            <span class="gcash-account-name"><?php echo htmlspecialchars($gcashAccountName); ?></span>
                            <span class="gcash-account-num" id="gcashNumberText"><?php echo htmlspecialchars($gcashNumber); ?></span>
                        </div>
                        <button type="button" class="gcash-copy-btn" onclick="copyGcashNumber()" title="Copy Account / Mobile Number">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>

                    <!-- Highlighted QR Ph Instruction Card with Logos -->
                    <div class="qrph-highlight-card">
                        <div class="qrph-highlight-top">
                            <div class="qrph-icon-glow">
                                <i class="fas fa-qrcode"></i>
                            </div>
                            <div class="qrph-highlight-text">
                                <strong>Scan the QR Ph code below using RCBC, GCash, Maya, or any QR Ph banking app</strong>
                                <p>Standard Philippine QR Ph • Zero extra fees • Instant payment confirmation</p>
                            </div>
                        </div>

                        <!-- Brand Logo Chips (GCash, Maya, RCBC, QR Ph, All Banks) -->
                        <div class="qrph-badges-row">
                            <span class="qrph-badge badge-qrph" title="Official National QR Standard">
                                <span class="qrph-badge-dot"></span> QR Ph
                            </span>
                            <span class="qrph-badge badge-rcbc" title="Powered by RCBC">
                                <i class="fas fa-university"></i> RCBC
                            </span>
                            <span class="qrph-badge badge-gcash" title="Scan with GCash">
                                <span class="brand-g">G</span> GCash
                            </span>
                            <span class="qrph-badge badge-maya" title="Scan with Maya">
                                <span class="brand-m">M</span> Maya
                            </span>
                            <span class="qrph-badge badge-banks" title="BDO, BPI, UnionBank, GoTyme, SeaBank & other banks">
                                <i class="fas fa-building-columns"></i> Any Bank App
                            </span>
                        </div>
                    </div>
                    
                    <!-- QR Code with Amount Overlay -->
                    <div class="gcash-qr-img-wrap">
                        <img src="<?php echo htmlspecialchars($gcashQrPath); ?>" id="gcashQrImg" alt="QR Ph RCBC Code" class="gcash-qr-img">
                        <div class="gcash-qr-amount-overlay" id="gcashQrAmountOverlay">
                            ₱<?php echo number_format($displayPrice, 0); ?>
                        </div>
                    </div>

                    <!-- Action Button: Save QR to Photos for Gallery Upload -->
                    <button type="button" class="qr-save-btn" onclick="saveOrOpenQrImage()" title="Save QR Code image to your photos">
                        <i class="fas fa-download"></i> Save QR 
                    </button>

                    <!-- Interactive Step-by-Step Payment Guide -->
                    <div class="qrph-guide-card">
                        <div class="qrph-guide-header" onclick="toggleQrGuide()" title="Click to show / hide guide">
                            <div class="qrph-guide-title">
                                
                                <span>How to scan and pay?</span>
                            </div>
                            <span class="qrph-guide-toggle-icon" id="qrGuideToggleIcon"><i class="fas fa-chevron-down"></i></span>
                        </div>
                        
                        <div class="qrph-guide-body" id="qrGuideBody" style="display: none;">
                            <!-- App Selector Tabs -->
                            <div class="qrph-guide-tabs">
                                <button type="button" class="qrph-tab-btn active" onclick="switchQrGuideTab('gcash', this)">
                                    <span class="brand-g-small">G</span> GCash
                                </button>
                                <button type="button" class="qrph-tab-btn" onclick="switchQrGuideTab('maya', this)">
                                    <span class="brand-m-small">M</span> Maya
                                </button>
                                <button type="button" class="qrph-tab-btn" onclick="switchQrGuideTab('rcbc', this)">
                                    <i class="fas fa-university" style="font-size:10px;"></i> RCBC Pulz
                                </button>
                                <button type="button" class="qrph-tab-btn" onclick="switchQrGuideTab('other', this)">
                                    <i class="fas fa-building-columns" style="font-size:10px;"></i> Other Banks
                                </button>
                            </div>

                            <!-- Tab 1: GCash -->
                            <div class="qrph-tab-content" id="qrTabGcash">
                                <div class="guide-step">
                                    <span class="step-num">1</span>
                                    <span>Open the <strong>GCash</strong> app and tap <strong>QR</strong> or <strong>Pay QR</strong> on your dashboard.</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">2</span>
                                    <span>Scan the <strong>QR Ph code</strong> above (or tap <strong>Upload from Gallery</strong> if you saved the QR image).</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">3</span>
                                    <span>Verify the merchant name: <strong><?php echo htmlspecialchars($gcashAccountName); ?></strong> (RCBC via QR Ph).</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">4</span>
                                    <span>Enter the total amount <strong>₱<?php echo number_format($displayPrice, 0); ?></strong> and press <strong>Pay</strong>.</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">5</span>
                                    <span>Take a screenshot of the GCash receipt slip and upload it in the <strong>Proof of Payment</strong> box below.</span>
                                </div>
                            </div>

                            <!-- Tab 2: Maya -->
                            <div class="qrph-tab-content" id="qrTabMaya" style="display:none;">
                                <div class="guide-step">
                                    <span class="step-num">1</span>
                                    <span>Open the <strong>Maya</strong> app and tap <strong>Scan to Pay</strong> (QR icon) at the bottom.</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">2</span>
                                    <span>Scan the <strong>QR Ph code</strong> above, or tap the gallery icon to select the saved QR picture.</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">3</span>
                                    <span>Recipient will show as <strong><?php echo htmlspecialchars($gcashAccountName); ?></strong> via RCBC.</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">4</span>
                                    <span>Input <strong>₱<?php echo number_format($displayPrice, 0); ?></strong>, verify transaction details, and confirm.</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">5</span>
                                    <span>Save your payment confirmation screenshot and attach it below.</span>
                                </div>
                            </div>

                            <!-- Tab 3: RCBC Pulz -->
                            <div class="qrph-tab-content" id="qrTabRcbc" style="display:none;">
                                <div class="guide-step">
                                    <span class="step-num">1</span>
                                    <span>Log in to <strong>RCBC Pulz</strong> (or RCBC Online Banking) and tap <strong>Scan to Pay</strong>.</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">2</span>
                                    <span>Scan the QR code above or select it from your saved photos.</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">3</span>
                                    <span>Confirm transfer to <strong><?php echo htmlspecialchars($gcashAccountName); ?></strong> (No. <strong><?php echo htmlspecialchars($gcashNumber); ?></strong>).</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">4</span>
                                    <span>Transfer <strong>₱<?php echo number_format($displayPrice, 0); ?></strong> and authenticate with your Biometrics/PIN.</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">5</span>
                                    <span>Save/Screenshot the confirmation receipt and upload below.</span>
                                </div>
                            </div>

                            <!-- Tab 4: Other Banks -->
                            <div class="qrph-tab-content" id="qrTabOther" style="display:none;">
                                <div class="guide-step">
                                    <span class="step-num">1</span>
                                    <span>Open any Philippine bank app (<strong>BDO, BPI, UnionBank, GoTyme, SeaBank, Landbank, Metrobank</strong>, etc.).</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">2</span>
                                    <span>Select <strong>Scan QR / QR Ph</strong> from your app menu.</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">3</span>
                                    <span>Scan the code above. The destination bank will automatically recognize <strong>RCBC</strong>.</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">4</span>
                                    <span>Verify receiver <strong><?php echo htmlspecialchars($gcashAccountName); ?></strong> and send <strong>₱<?php echo number_format($displayPrice, 0); ?></strong>.</span>
                                </div>
                                <div class="guide-step">
                                    <span class="step-num">5</span>
                                    <span>Upload your successful transaction receipt screenshot below.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Required Proof of Payment Upload -->
                    <div class="gcash-proof-section">
                        <div class="gcash-proof-title">
                            <i class="fas fa-file-invoice-dollar" style="color: #60a5fa;"></i>
                            <span>Proof of Payment</span>
                            <span class="req-badge">Required</span>
                        </div>
                        
                        <div class="gcash-dropzone" id="gcashDropzone" onclick="triggerGcashProofSelect()">
                            <input type="file" id="gcashProofInput" accept="image/*" style="display:none;" onchange="handleGcashProofSelected(this)">
                            
                            <div id="dropzonePrompt" class="dropzone-prompt">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Tap to upload payment screenshot / receipt</p>
                                <span>Supports RCBC, GCash, Maya & Banking Apps (Max 10MB)</span>
                            </div>

                            <div id="proofPreviewWrap" class="proof-preview-wrap" style="display:none;">
                                <img id="proofPreviewThumb" class="proof-preview-thumb" src="" alt="Proof Preview">
                                <div class="proof-file-info">
                                    <div id="proofFileName" class="proof-file-name">screenshot.png</div>
                                    <div class="proof-file-status"><i class="fas fa-check-circle"></i> Ready to submit</div>
                                </div>
                                <button type="button" class="proof-remove-btn" onclick="removeGcashProof(event)">
                                    <i class="fas fa-trash-alt"></i> Remove
                                </button>
                            </div>
                        </div>

                        <!-- Non-Refundable Policy Notice Banner (Under Proof of Payment box) -->
                        <div class="reservation-non-refundable-banner">
                            <div class="notice-header">
                                <div class="notice-title">
                                    <i class="fas fa-exclamation-triangle"></i> Reservation Policy Notice
                                </div>
                                <span class="notice-pill"><i class="fas fa-ban" style="font-size: 9.5px;"></i> Non-Refundable</span>
                            </div>
                            <div class="notice-desc">
                                This reservation is not refundable. As per policy, all reservation fees, downpayments, and rental payments are strictly non-refundable once submitted.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QR Ph Confirmed Banner -->
            <div id="gcashConfirmedBanner" class="gcash-confirmed-banner" style="display:none;">
                <div class="gcash-confirmed-circle">
                    <i class="fas fa-check"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div class="gcash-confirmed-title">Payment Proof Submitted!</div>
                    <div class="gcash-confirmed-sub">QR Ph (RCBC) transaction marked as paid</div>
                    <img id="confirmedProofThumb" class="gcash-confirmed-proof-thumb" src="" alt="Receipt" style="display:none;" onclick="openProofPreviewModal()" title="View full receipt">
                </div>
                <button type="button" class="gcash-undo-btn" onclick="undoGcashPayment()">Change Proof</button>
            </div>

            <!-- Non-Refundable Reminder on Confirmed State -->
            <div id="gcashConfirmedNotice" class="reservation-non-refundable-banner" style="display:none; margin-top: 10px;">
                <div class="notice-header">
                    <div class="notice-title">
                        <i class="fas fa-exclamation-triangle"></i> Reservation Policy Notice
                    </div>
                    <span class="notice-pill"><i class="fas fa-ban" style="font-size: 9.5px;"></i> Non-Refundable</span>
                </div>
                <div class="notice-desc">
                    This reservation is not refundable. As per policy, all reservation fees, downpayments, and rental payments are strictly non-refundable once submitted.
                </div>
            </div>
        </div>

        <!-- BOX 2: Order Summary (Computed Breakdown & Reservation Details) -->
        <div class="login-box payment-details-card order-summary-card">
            <div class="payment-card-header">
                <span class="payment-card-title"><i class="fas fa-clipboard-list" style="color: #ffd700; margin-right: 6px;"></i>Order Summary</span>
                <span class="payment-badge-note" id="detailsPlanBadge">Full Payment (100%)</span>
            </div>

            <div class="payment-details-body">
                <!-- Reservation Schedule & Details -->
                <div class="order-summary-details" style="background: rgba(255,255,255,0.03); border: 1px solid #33333a; border-radius: 8px; padding: 10px 12px; margin-bottom: 6px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-size: 0.75rem; color: #888;"><i class="far fa-calendar-check" style="color: #ffd700; margin-right: 5px;"></i>Reserve Date:</span>
                        <span style="font-size: 0.82rem; color: #fff; font-weight: 600;"><?php echo date('M d, Y', strtotime($reserveDate)); ?></span>
                    </div>
                    <?php if (!empty($returnDate) && $returnDate !== $reserveDate): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-size: 0.75rem; color: #888;"><i class="far fa-calendar-times" style="color: #f87171; margin-right: 5px;"></i>Return Date:</span>
                        <span style="font-size: 0.82rem; color: #fff; font-weight: 600;"><?php echo date('M d, Y', strtotime($returnDate)); ?></span>
                    </div>
                    <?php 
                        $daysDiff = max(1, (int)round((strtotime($returnDate) - strtotime($reserveDate)) / 86400));
                    ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-size: 0.75rem; color: #888;"><i class="far fa-clock" style="color: #60a5fa; margin-right: 5px;"></i>Rental Duration:</span>
                        <span style="font-size: 0.78rem; color: #93c5fd; font-weight: 600; background: rgba(96,165,250,0.12); padding: 2px 8px; border-radius: 4px;"><?php echo $daysDiff; ?> Days</span>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.75rem; color: #888;"><i class="fas fa-tshirt" style="color: #a78bfa; margin-right: 5px;"></i>Total Items:</span>
                        <span style="font-size: 0.82rem; color: #fff; font-weight: 600;"><?php echo count($products); ?> Product(s) (<?php 
                            $totQty = 0;
                            foreach ($products as $p) $totQty += max(1, intval($p['quantity'] ?? 1));
                            echo $totQty;
                        ?> items)</span>
                    </div>
                </div>
                <div class="payment-detail-row">
                    <span class="detail-name">Merchandise Subtotal</span>
                    <span class="detail-val" id="detailSubtotal">₱<?php echo number_format($originalSubtotal, 0); ?></span>
                </div>

                <?php if ($totalSaved > 0) { ?>
                <div class="payment-detail-row discount-row" id="detailDiscountRow">
                    <span class="detail-name" style="color: #4caf50;">Total Savings (Discount)</span>
                    <span class="detail-val" style="color: #4caf50;" id="detailSavings">-₱<?php echo number_format($totalSaved, 0); ?></span>
                </div>
                <?php } ?>

                <div class="payment-detail-row">
                    <span class="detail-name">Total Reservation Price</span>
                    <span class="detail-val" id="detailTotalPrice">₱<?php echo number_format($displayPrice, 0); ?></span>
                </div>

                <div class="payment-divider"></div>

                <!-- First Payment vs Full Payment Breakdowns -->
                <div class="payment-detail-row" id="rowFirstPayment" style="display: none;">
                    <span class="detail-name" style="color: #ffd700; font-weight: 600;">First Payment (₱500 Deposit)</span>
                    <span class="detail-val" style="color: #ffd700; font-weight: 600;" id="detailFirstPaymentAmount">₱<?php echo number_format($firstPaymentAmount, 0); ?></span>
                </div>

                <div class="payment-detail-row" id="rowRemainingBalance" style="display: none;">
                    <span class="detail-name" style="color: #aaa;">Remaining Balance (Due on pickup)</span>
                    <span class="detail-val" style="color: #ccc;" id="detailRemainingBalanceAmount">₱<?php echo number_format($remainingBalanceAmount, 0); ?></span>
                </div>

                <div class="payment-divider" id="rowDueDivider" style="display: none;"></div>

                <div class="payment-detail-row total-due-row">
                    <div>
                        <span class="total-due-label" id="totalDueLabel">Total Amount Due Now:</span>
                        <span class="total-due-sub" id="totalDueSub">100% Full Payment</span>
                    </div>
                    <span class="total-due-amount" id="totalDueAmount">₱<?php echo number_format($displayPrice, 0); ?></span>
                </div>
            </div>
        </div>
    </div>
    
<!-- View Items Overlay (read-only) -->
<div id="viewItemsOverlay" class="reserve-overlay">
    <div class="reserve-content">
        <div class="reserve-model">
            <model-viewer
                id="viewItemsModel"
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
            <img id="viewItemsImage" src="" alt="" style="width: 100%; height: 100%; object-fit: cover; object-position: top; border-radius: 8px; display: none;">
            <div id="viewItemsNoMedia" style="width: 100%; height: 100%; display: none; align-items: center; justify-content: center; background: #2a2a2a; border-radius: 8px; color: #666;">
                <i class="fas fa-image" style="font-size: 2.5rem;"></i>
            </div>
            <div class="product-name-overlay" id="viewItemsName">Selected Product</div>
            <div class="product-price-overlay" id="viewItemsPrice">₱0</div>
            <div class="product-stock-overlay" id="viewItemsQuantity">Items: 1</div>
        </div>

        <div class="reservation-form">
            <div class="form-row">
                <div class="form-group" id="viewReserveDateGroup">
                    <label for="viewReserveDate" id="viewReserveDateLabel">Reserve Date</label>
                    <input type="text" id="viewReserveDate" readonly disabled>
                </div>
                <div class="form-group" id="viewReturnDateGroup">
                    <label for="viewReturnDate">Return Date</label>
                    <input type="text" id="viewReturnDate" readonly disabled>
                </div>
            </div>
            <div class="guide-line"></div>
            <div id="viewItemsContainer">
                <!-- Item measurements are rendered here -->
            </div>
        </div>
    </div>
</div>

<div id="viewItemsBackdrop" class="top-overlay" onclick="closeViewItems()"></div>

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
            <!-- Left side content if needed -->
        </div>
        <div class="app-bar-buttons" style="display: flex; justify-content: flex-end; align-items: center; gap: 8px; width: 100%;">
            <span class="total-price" style="display: inline-flex; flex-direction: column; align-items: flex-start; line-height: 1.15;">
                <span style="font-weight: 700; font-size: 0.88rem;"><span id="barDueLabel">Due Now:</span> <span class="price-amount" id="barPriceAmount">₱0</span></span>
                <span id="barBalanceWrap" style="font-size: 0.72rem; color: #ffd700; font-weight: 600; display: none;">Balance: <span id="barBalanceAmount">₱0</span></span>
                <span class="saved-price">Saved: <span class="saved-amount">₱<?php echo number_format($totalSaved, 0); ?></span></span>
            </span>
            <button class="reserve-button disabled-btn" onclick="submitReservation()" style="height: 40px; border-radius: 5px; box-sizing: border-box; white-space: nowrap; width: auto; min-width: 110px; margin-right: 15px;">
                <span id="barReserveBtnText">Select Payment Option</span>
            </button>
        </div>
    </div>
</div>
</div> <!-- End of .shell -->

<script>
// Pass PHP data to JavaScript
const products = <?php echo json_encode($products); ?>;
let userId = <?php echo $userId; ?>;
let isLoggedIn = userId > 0;
const reservationData = {
    reserveDate: '<?php echo $reserveDate; ?>',
    returnDate: '<?php echo $returnDate; ?>',
    reserveTime: '<?php echo $reserveTime; ?>',
    returnTime: '<?php echo $returnTime; ?>'
};

// Check localStorage for login status if not logged in via PHP session
if (!isLoggedIn) {
    const localLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const localUserId = localStorage.getItem('userId');
    
    if (localLoggedIn && localUserId) {
        userId = parseInt(localUserId);
        isLoggedIn = true;
        console.log('Found login in localStorage, userId:', userId);
    }
}

console.log('Products:', products);
console.log('User ID:', userId);
console.log('Is Logged In:', isLoggedIn);
console.log('Total Price:', <?php echo $displayPrice; ?>);
console.log('Reservation Data:', reservationData);

let currentPaymentPlan = 'full'; // Default to 'full' payment
const totalPrice = <?php echo $displayPrice; ?>;
const originalSubtotal = <?php echo $originalSubtotal; ?>;
const totalSaved = <?php echo $totalSaved; ?>;

let gcashSelected = true; // Auto-select GCash by default
let gcashPaid    = false;

// Keep QR amount overlay in sync with selected plan
function updateGcashQrAmount() {
    const overlay = document.getElementById('gcashQrAmountOverlay');
    if (!overlay) return;
    const amount = currentPaymentPlan === 'first_payment'
        ? Math.min(500, totalPrice)
        : totalPrice;
    overlay.textContent = '₱' + amount.toLocaleString();
}

// Apply / remove disabled state on the reserve button
function syncReserveButton() {
    const btns = document.querySelectorAll('.reserve-button');
    const textEl = document.getElementById('barReserveBtnText');
    
    if (!currentPaymentPlan) {
        btns.forEach(btn => btn.classList.add('disabled-btn'));
        if (textEl) textEl.textContent = 'Select Payment Option';
    } else if (!gcashPaid) {
        btns.forEach(btn => btn.classList.add('disabled-btn'));
        if (textEl) textEl.textContent = 'Pay via QR Ph First';
    } else {
        btns.forEach(btn => btn.classList.remove('disabled-btn'));
        if (textEl) textEl.textContent = 'Confirm Reserve';
    }
}

function selectPaymentOption(plan) {
    currentPaymentPlan = plan;
    const fullOption = document.getElementById('fullPaymentOption');
    const firstOption = document.getElementById('firstPaymentOption');
    const rowFirstPayment = document.getElementById('rowFirstPayment');
    const rowRemainingBalance = document.getElementById('rowRemainingBalance');
    const rowDueDivider = document.getElementById('rowDueDivider');
    const totalDueLabel = document.getElementById('totalDueLabel');
    const totalDueSub = document.getElementById('totalDueSub');
    const totalDueAmount = document.getElementById('totalDueAmount');
    const detailsPlanBadge = document.getElementById('detailsPlanBadge');
    const barPriceAmount = document.getElementById('barPriceAmount');
    const barDueLabel = document.getElementById('barDueLabel');
    const barBalanceWrap = document.getElementById('barBalanceWrap');
    const barBalanceAmount = document.getElementById('barBalanceAmount');
    const detailFirst = document.getElementById('detailFirstPaymentAmount');
    const detailRem = document.getElementById('detailRemainingBalanceAmount');
    const radioFull = document.querySelector('input[name="payment_plan"][value="full"]');
    const radioFirst = document.querySelector('input[name="payment_plan"][value="first_payment"]');

    const firstAmount = Math.min(500, totalPrice);
    const remainingAmount = Math.max(0, Math.round((totalPrice - firstAmount) * 100) / 100);

    if (detailFirst) detailFirst.textContent = '₱' + firstAmount.toLocaleString();
    if (detailRem) detailRem.textContent = '₱' + remainingAmount.toLocaleString();

    if (plan === 'first_payment') {
        if (fullOption) fullOption.classList.remove('active');
        if (firstOption) firstOption.classList.add('active');
        if (radioFirst) radioFirst.checked = true;
        if (radioFull) radioFull.checked = false;

        if (rowFirstPayment) rowFirstPayment.style.display = 'flex';
        if (rowRemainingBalance) rowRemainingBalance.style.display = 'flex';
        if (rowDueDivider) rowDueDivider.style.display = 'block';

        if (detailsPlanBadge) {
            detailsPlanBadge.textContent = 'First Payment (₱500)';
            detailsPlanBadge.className = 'payment-badge-gold';
        }
        if (totalDueLabel) totalDueLabel.textContent = 'First Payment Due Now:';
        if (totalDueSub) totalDueSub.textContent = '₱500 Initial Deposit • Balance: ₱' + remainingAmount.toLocaleString() + ' (Due on pickup)';
        if (totalDueAmount) totalDueAmount.textContent = '₱' + firstAmount.toLocaleString();
        if (barPriceAmount) barPriceAmount.textContent = '₱' + firstAmount.toLocaleString();
        if (barDueLabel) barDueLabel.textContent = 'Due Now:';
        if (barBalanceWrap) barBalanceWrap.style.display = 'inline-block';
        if (barBalanceAmount) barBalanceAmount.textContent = '₱' + remainingAmount.toLocaleString();
    } else if (plan === 'full') {
        if (firstOption) firstOption.classList.remove('active');
        if (fullOption) fullOption.classList.add('active');
        if (radioFull) radioFull.checked = true;
        if (radioFirst) radioFirst.checked = false;

        if (rowFirstPayment) rowFirstPayment.style.display = 'none';
        if (rowRemainingBalance) rowRemainingBalance.style.display = 'none';
        if (rowDueDivider) rowDueDivider.style.display = 'none';

        if (detailsPlanBadge) {
            detailsPlanBadge.textContent = 'Full Payment (100%)';
            detailsPlanBadge.className = 'payment-badge-green';
        }
        if (totalDueLabel) totalDueLabel.textContent = 'Total Amount Due Now:';
        if (totalDueSub) totalDueSub.textContent = '100% Full Payment • Fully paid';
        if (totalDueAmount) totalDueAmount.textContent = '₱' + totalPrice.toLocaleString();
        if (barPriceAmount) barPriceAmount.textContent = '₱' + totalPrice.toLocaleString();
        if (barDueLabel) barDueLabel.textContent = 'Due Now:';
        if (barBalanceWrap) barBalanceWrap.style.display = 'none';
    }

    // Automatically ensure QR Ph is active and QR is displayed with updated amount
    if (!gcashPaid) {
        gcashSelected = true;
        const methodItem  = document.getElementById('gcashMethodItem');
        const gcashRadio  = document.getElementById('gcashRadio');
        const qrPanel     = document.getElementById('gcashQrPanel');
        if (methodItem) methodItem.classList.add('gcash-selected');
        if (gcashRadio) gcashRadio.classList.add('active');
        if (qrPanel) qrPanel.style.display = 'block';
    }

    // Sync QR amount & button state
    updateGcashQrAmount();
    syncReserveButton();
}

// ---- GCash & Proof of Payment State & Functions ----
let selectedProofFile = null;
let uploadedProofPath = '';

function triggerGcashProofSelect() {
    const input = document.getElementById('gcashProofInput');
    if (input) input.click();
}

function handleGcashProofSelected(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    // Validate image format
    if (!file.type.match(/^image\//i)) {
        showNotification('Please select a valid image file (JPG, PNG, WEBP).', 3000);
        input.value = '';
        return;
    }

    // Validate size (10MB max)
    if (file.size > 10 * 1024 * 1024) {
        showNotification('File size exceeds 10MB limit.', 3000);
        input.value = '';
        return;
    }

    selectedProofFile = file;
    uploadedProofPath = '';

    const reader = new FileReader();
    reader.onload = function(e) {
        const thumb = document.getElementById('proofPreviewThumb');
        const nameEl = document.getElementById('proofFileName');
        const previewWrap = document.getElementById('proofPreviewWrap');
        const promptEl = document.getElementById('dropzonePrompt');
        const dropzone = document.getElementById('gcashDropzone');
        const statusEl = document.querySelector('.proof-file-status');

        if (thumb) thumb.src = e.target.result;
        if (nameEl) nameEl.textContent = file.name;
        if (statusEl) statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading proof...';
        if (previewWrap) previewWrap.style.display = 'flex';
        if (promptEl) promptEl.style.display = 'none';
        if (dropzone) {
            dropzone.classList.add('has-file');
            dropzone.classList.remove('shake');
        }

        // Auto-upload selected file
        autoUploadGcashProof(file);
    };
    reader.readAsDataURL(file);
}

function removeGcashProof(event) {
    if (event) event.stopPropagation();
    selectedProofFile = null;
    uploadedProofPath = '';
    gcashPaid = false;
    
    const input = document.getElementById('gcashProofInput');
    if (input) input.value = '';

    const previewWrap = document.getElementById('proofPreviewWrap');
    const promptEl = document.getElementById('dropzonePrompt');
    const dropzone = document.getElementById('gcashDropzone');
    const thumb = document.getElementById('proofPreviewThumb');

    if (previewWrap) previewWrap.style.display = 'none';
    if (promptEl) promptEl.style.display = 'block';
    if (thumb) thumb.src = '';
    if (dropzone) dropzone.classList.remove('has-file');

    syncReserveButton();
}

function copyGcashNumber() {
    const numText = document.getElementById('gcashNumberText');
    const num = numText ? numText.textContent.trim() : '<?php echo htmlspecialchars($gcashNumber); ?>';
    const copyBtn = document.querySelector('.gcash-copy-btn');
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(num).then(() => {
            if (copyBtn) {
                const orig = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                copyBtn.style.background = '#007aff';
                copyBtn.style.color = '#fff';
                setTimeout(() => {
                    copyBtn.innerHTML = orig;
                    copyBtn.style.background = '';
                    copyBtn.style.color = '';
                }, 2000);
            }
            showNotification('QR Ph / RCBC account number copied to clipboard: ' + num, 2500);
        }).catch(() => {
            fallbackCopyText(num);
        });
    } else {
        fallbackCopyText(num);
    }
}

function saveOrOpenQrImage() {
    const qrImg = document.getElementById('gcashQrImg');
    if (!qrImg || !qrImg.src) return;
    
    const link = document.createElement('a');
    link.href = qrImg.src;
    link.download = 'ClosetGoo_QRPh_RCBC.jpg';
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showNotification('QR image downloaded! You can now open GCash or Maya and select "Upload from Gallery".', 4000);
}

function toggleQrGuide() {
    const body = document.getElementById('qrGuideBody');
    const icon = document.getElementById('qrGuideToggleIcon');
    if (!body) return;
    if (body.style.display === 'none') {
        body.style.display = 'block';
        if (icon) icon.innerHTML = '<i class="fas fa-chevron-up"></i>';
    } else {
        body.style.display = 'none';
        if (icon) icon.innerHTML = '<i class="fas fa-chevron-down"></i>';
    }
}

function switchQrGuideTab(tabId, btn) {
    document.querySelectorAll('.qrph-tab-btn').forEach(b => b.classList.remove('active'));
    if (btn) {
        btn.classList.add('active');

        // Auto slide clicked tab into center on mobile and small devices
        const container = btn.closest('.qrph-guide-tabs');
        if (container) {
            const containerWidth = container.clientWidth;
            const btnLeft = btn.offsetLeft;
            const btnWidth = btn.clientWidth;
            const targetScrollLeft = btnLeft - (containerWidth / 2) + (btnWidth / 2);
            container.scrollTo({
                left: Math.max(0, targetScrollLeft),
                behavior: 'smooth'
            });
        }
    }

    document.querySelectorAll('.qrph-tab-content').forEach(c => c.style.display = 'none');
    const targetId = 'qrTab' + tabId.charAt(0).toUpperCase() + tabId.slice(1);
    const target = document.getElementById(targetId);
    if (target) target.style.display = 'block';
}

function fallbackCopyText(text) {
    const tempInput = document.createElement('input');
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    try {
        document.execCommand('copy');
        showNotification('QR Ph / RCBC account number copied: ' + text, 2500);
    } catch(e) {
        showNotification('QR Ph / RCBC Account: ' + text, 3000);
    }
    document.body.removeChild(tempInput);
}

function openGcashApp() {
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    if (isMobile) {
        // Attempt deep link to GCash app
        window.location.href = 'gcash://';
        setTimeout(() => {
            // Fallback instruction
            showNotification('If GCash did not open, please switch to your GCash app and scan the QR.', 4000);
        }, 1500);
    } else {
        showNotification('Please open your GCash app on your mobile phone and scan the QR code.', 3500);
    }
}

function selectGcashPayment() {
    if (gcashPaid) return; // Already confirmed, keep confirmed state

    gcashSelected = true;
    const methodItem  = document.getElementById('gcashMethodItem');
    const gcashRadio  = document.getElementById('gcashRadio');
    const qrPanel     = document.getElementById('gcashQrPanel');

    if (methodItem) methodItem.classList.add('gcash-selected');
    if (gcashRadio) gcashRadio.classList.add('active');
    if (qrPanel) {
        qrPanel.style.display = 'block';
        updateGcashQrAmount();
    }

    syncReserveButton();
}

let isUploadingProof = false;

function autoUploadGcashProof(file, proceedAfterUpload = false) {
    if (!file) return;
    isUploadingProof = true;
    const textEl = document.getElementById('barReserveBtnText');
    if (textEl && proceedAfterUpload) {
        textEl.textContent = 'Uploading Proof...';
    }

    const formData = new FormData();
    formData.append('action', 'upload_proof');
    formData.append('proof_image', file);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        isUploadingProof = false;
        if (data.success) {
            uploadedProofPath = data.file_path;
            applyGcashPaymentSuccess();
            showNotification('Payment proof uploaded successfully!', 2500);
            if (proceedAfterUpload) {
                saveReservationToDatabase();
            }
        } else {
            showNotification(data.message || 'Failed to upload proof. Please try again.', 3000);
            const statusEl = document.querySelector('.proof-file-status');
            if (statusEl) statusEl.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#ff6b6b;"></i> Upload failed';
            syncReserveButton();
        }
    })
    .catch(err => {
        isUploadingProof = false;
        console.error('Error uploading payment proof:', err);
        showNotification('Error uploading payment proof. Please try again.', 3000);
        const statusEl = document.querySelector('.proof-file-status');
        if (statusEl) statusEl.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#ff6b6b;"></i> Upload failed';
        syncReserveButton();
    });
}

function applyGcashPaymentSuccess() {
    gcashPaid = true;
    gcashSelected = true;

    // Hide QR panel, show confirmed banner
    document.getElementById('gcashQrPanel').style.display = 'none';
    const banner = document.getElementById('gcashConfirmedBanner');
    if (banner) banner.style.display = 'flex';
    const confNotice = document.getElementById('gcashConfirmedNotice');
    if (confNotice) confNotice.style.display = 'block';

    // Show proof thumbnail on confirmed banner
    const proofThumb = document.getElementById('confirmedProofThumb');
    if (proofThumb && uploadedProofPath) {
        proofThumb.src = uploadedProofPath;
        proofThumb.style.display = 'block';
    }

    // Update status badge
    const badge = document.getElementById('gcashStatusBadge');
    if (badge) {
        badge.textContent = '✓ Paid';
        badge.className = 'gcash-status-paid';
    }

    // Keep method item highlighted
    const methodItem = document.getElementById('gcashMethodItem');
    if (methodItem) methodItem.classList.add('gcash-selected');
    const gcashRadio = document.getElementById('gcashRadio');
    if (gcashRadio) gcashRadio.classList.add('active');

    syncReserveButton();
}

function undoGcashPayment() {
    gcashPaid = false;
    uploadedProofPath = '';
    selectedProofFile = null;

    // Reset dropzone inputs
    const input = document.getElementById('gcashProofInput');
    if (input) input.value = '';
    const previewWrap = document.getElementById('proofPreviewWrap');
    if (previewWrap) previewWrap.style.display = 'none';
    const promptEl = document.getElementById('dropzonePrompt');
    if (promptEl) promptEl.style.display = 'block';
    const thumb = document.getElementById('proofPreviewThumb');
    if (thumb) thumb.src = '';
    const dropzone = document.getElementById('gcashDropzone');
    if (dropzone) dropzone.classList.remove('has-file');

    // Hide confirmed banner, show QR panel again
    const banner = document.getElementById('gcashConfirmedBanner');
    if (banner) banner.style.display = 'none';
    const confNotice = document.getElementById('gcashConfirmedNotice');
    if (confNotice) confNotice.style.display = 'none';
    const qrPanel = document.getElementById('gcashQrPanel');
    if (qrPanel) qrPanel.style.display = 'block';
    updateGcashQrAmount();

    // Reset badge
    const badge = document.getElementById('gcashStatusBadge');
    if (badge) {
        badge.textContent = 'Unpaid';
        badge.className = 'gcash-status-pending';
    }

    syncReserveButton();
    showNotification('Payment proof cleared. Please upload a new proof.', 2500);
}

function openProofPreviewModal() {
    if (!uploadedProofPath) return;
    window.open(uploadedProofPath, '_blank');
}

function submitReservation() {
    if (!isLoggedIn || userId <= 0) {
        showNotification('Please login!', 2000);
        return;
    }
    // Block if payment option not selected
    if (!currentPaymentPlan) {
        showNotification('Please select a payment option first!', 2500);
        const optCard = document.querySelector('.payment-option-card');
        if (optCard) optCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    if (isUploadingProof) {
        showNotification('Uploading payment proof, please wait a moment...', 2500);
        return;
    }

    // Handle by Pay via GCash First button if no upload payment
    if (!gcashPaid && !uploadedProofPath) {
        if (selectedProofFile) {
            // File chosen but upload was pending/failed: upload and proceed
            autoUploadGcashProof(selectedProofFile, true);
            return;
        }

        showNotification('Please upload your QR Ph / RCBC payment receipt or screenshot first!', 3500);
        if (!gcashSelected) {
            selectGcashPayment();
        }
        const qrPanel = document.getElementById('gcashQrPanel');
        if (qrPanel) {
            qrPanel.style.display = 'block';
        }
        const dropzone = document.getElementById('gcashDropzone');
        if (dropzone) {
            dropzone.classList.remove('shake');
            void dropzone.offsetWidth; // Trigger reflow
            dropzone.classList.add('shake');
            dropzone.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }

    saveReservationToDatabase();
}

function saveReservationToDatabase() {
    const formData = new FormData();
    formData.append('action', 'reserve');
    formData.append('payment_option', currentPaymentPlan);
    formData.append('payment_proof', uploadedProofPath || '');

    const btn = document.querySelector('.reserve-button');
    if (btn) {
        btn.classList.add('disabled-btn');
        const textEl = document.getElementById('barReserveBtnText');
        if (textEl) textEl.textContent = 'Saving Reservation...';
    }

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Unexpected server response:', text);
            throw new Error('Unexpected server response');
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            // Remove reserved products from localStorage savedProducts
            try {
                let localSaved = JSON.parse(localStorage.getItem('savedProducts') || '[]');
                if (Array.isArray(localSaved) && localSaved.length > 0) {
                    const reservedNames = (products || []).map(p => (p.name || '').trim().toLowerCase());
                    const reservedIds = (products || []).map(p => String(p.id || p.product_id || ''));
                    localSaved = localSaved.filter(item => {
                        const itemName = (item.product_name || item.name || '').trim().toLowerCase();
                        const itemId = String(item.id || item.product_id || '');
                        if (itemId && reservedIds.includes(itemId)) return false;
                        if (itemName && reservedNames.includes(itemName)) return false;
                        return true;
                    });
                    localStorage.setItem('savedProducts', JSON.stringify(localSaved));
                }
            } catch(e) {
                console.error('Error updating localStorage saved products:', e);
            }

            // Clear checked state in sessionStorage for these products
            sessionStorage.removeItem('checkedCartProductIds');

            showNotification('Reservation Confirmed!', 2000);
            setTimeout(() => {
                window.location.href = 'purchase.php';
            }, 1000);
        } else {
            if (btn) btn.classList.remove('disabled-btn');
            const textEl = document.getElementById('barReserveBtnText');
            if (textEl) textEl.textContent = 'Confirm Reserve';
            showNotification(data.message || 'Try again!', 2500);
            console.error('Failed to save reservation:', data.message);
        }
    })
    .catch(error => {
        if (btn) btn.classList.remove('disabled-btn');
        const textEl = document.getElementById('barReserveBtnText');
        if (textEl) textEl.textContent = 'Confirm Reserve';
        console.error('Error saving reservation:', error);
        showNotification('Error saving reservation!', 2000);
    });
}

// Show read-only View Items overlay
function viewItems(index) {
    const product = products[index];
    if (!product) return;

    const overlay = document.getElementById('viewItemsOverlay');
    const backdrop = document.getElementById('viewItemsBackdrop');
    const container = document.getElementById('viewItemsContainer');
    const modelViewer = document.getElementById('viewItemsModel');
    const modelImg = document.getElementById('viewItemsImage');
    const noMedia = document.getElementById('viewItemsNoMedia');

    const unitPrice = (product.status === 'buy')
        ? parseFloat(product.purchase_price || 0)
        : parseFloat(product.price || 0);
    const items = Array.isArray(product.items) && product.items.length ? product.items : [{ item_number: 1 }];
    const sizeLabels = (Array.isArray(product.size_labels) && product.size_labels.length)
        ? product.size_labels
        : [
            { index: 1, label: 'Chest / Bust' },
            { index: 2, label: 'Waist' },
            { index: 3, label: 'Hips' },
            { index: 4, label: 'Shoulder' }
        ];

    const prodModel = (product.modelPath || product.model_path || '').trim();
    const prodImage = (product.imagePath || product.image_path || product.image || '').trim();

    if (prodImage !== '') {
        if (modelViewer) modelViewer.style.display = 'none';
        if (modelImg) {
            modelImg.setAttribute('src', prodImage);
            modelImg.style.display = 'block';
        }
        if (noMedia) noMedia.style.display = 'none';
    } else if (prodModel !== '') {
        if (modelViewer) {
            modelViewer.setAttribute('src', prodModel);
            modelViewer.style.display = 'block';
        }
        if (modelImg) modelImg.style.display = 'none';
        if (noMedia) noMedia.style.display = 'none';
    } else {
        if (modelViewer) modelViewer.style.display = 'none';
        if (modelImg) modelImg.style.display = 'none';
        if (noMedia) noMedia.style.display = 'flex';
    }

    document.getElementById('viewItemsName').textContent = product.name || '';
    document.getElementById('viewItemsPrice').innerHTML =
        `<span style="color: gray; font-size: 0.8rem;">${product.status === 'buy' ? 'Selling Price:' : 'Rental Price:'}</span> ₱${unitPrice.toLocaleString(undefined, {maximumFractionDigits: 0})}`;
    document.getElementById('viewItemsQuantity').textContent = `Items: ${items.length}`;
    document.getElementById('viewReserveDate').value = product.reserve_date || reservationData.reserveDate || '';
    document.getElementById('viewReturnDate').value = product.return_date || reservationData.returnDate || '';

    const isBuyProd = (product.status === 'buy');
    const returnGroup = document.getElementById('viewReturnDateGroup');
    const reserveLabel = document.getElementById('viewReserveDateLabel');
    if (returnGroup) {
        returnGroup.style.display = isBuyProd ? 'none' : 'block';
    }
    if (reserveLabel) {
        reserveLabel.textContent = isBuyProd ? 'Reserve / Pick-up Date' : 'Reserve Date';
    }

    container.innerHTML = items.map((item, i) => {
        const itemNumber = item.item_number || (i + 1);
        const fields = sizeLabels.map(sizeLabel => {
            const value = item[`size_${sizeLabel.index}`] || '';
            return `
                <div class="form-group">
                    <label>${sizeLabel.label}</label>
                    <input type="text" value="${value !== '' ? value : '-'}" readonly disabled>
                </div>
            `;
        }).join('');

        return `
            <div class="measurement-group">
                <div class="measurement-label">
                    <span>Item ${itemNumber}</span>
                </div>
                <div class="form-row sizes-row">
                    ${fields}
                </div>
            </div>
        `;
    }).join('');

    backdrop.style.display = 'block';
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        overlay.style.display = 'block';
    }, 100);
}

function closeViewItems() {
    document.getElementById('viewItemsOverlay').style.display = 'none';
    document.getElementById('viewItemsBackdrop').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function goBack() {
    // Navigate back to previous page
    window.history.back();
}

// Play notification sound
function playNotificationSound() {
    const audio = new Audio('ringtone/notification.MP3');
    audio.volume = 0.5; // Set volume to 50%
    audio.play().catch(error => {
        console.log('Audio play failed:', error);
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

// Initialize default plan and button states automatically on load
document.addEventListener('DOMContentLoaded', () => {
    selectPaymentOption('full');
    syncReserveButton();
});
selectPaymentOption('full');
syncReserveButton();

// Function to update cart count from database API
function updateCartCount() {
    const cartCountElement = document.getElementById('cartCount');
    if (!cartCountElement) return;

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
            badge.style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Error fetching purchase badge:', error);
        badge.style.display = 'none';
    });
}

// Sync localStorage user profile fallback if not set by PHP
(function syncLocalStorageProfile() {
    if (!isLoggedIn) {
        const localFirstName = localStorage.getItem('userFirstName') || '';
        const localLastName = localStorage.getItem('userLastName') || '';
        const localEmail = localStorage.getItem('userEmail') || '';
        const localImage = localStorage.getItem('userProfileImage') || '';
        const authContainer = document.getElementById('authLinksContainer');
        
        const localName = (localFirstName + ' ' + localLastName).trim() || localFirstName || localEmail;
        if (localName && authContainer) {
            authContainer.innerHTML = `
                <div class="auth-user-profile">
                    <span class="auth-user-name" id="topbarUserName">${localName}</span>
                    <a href="profile.php" class="auth-user-avatar-wrap" title="View Profile">
                        <img src="${localImage || 'images/icon/profile_logo.png'}" alt="Profile" class="auth-user-avatar" id="topbarUserAvatar" onerror="this.src='images/icon/profile_logo.png'">
                    </a>
                </div>
            `;
        }
    }
})();

updateCartCount();
fetchPurchaseBadgeCount();
setInterval(updateCartCount, 10000);
setInterval(fetchPurchaseBadgeCount, 10000);
window.addEventListener('pageshow', fetchPurchaseBadgeCount);

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

<script>
// ===== Valid ID verification gate (reserve & buy require admin-verified ID) =====
(function () {
    const idVerifyStatus = <?php echo json_encode($idVerifyStatus); ?>;
    const isLoggedInUser = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
    if (!isLoggedInUser || idVerifyStatus === 'verified') return;

    const messages = {
        none: 'Please upload a valid ID in your Profile before you can reserve or buy.',
        pending: 'Your ID is pending admin verification. You can reserve or buy once it is verified.',
        rejected: 'Your uploaded ID was rejected by the admin. Please upload a new valid ID in your Profile.'
    };
    const msg = messages[idVerifyStatus] || messages.pending;
    const needsUpload = idVerifyStatus === 'none' || idVerifyStatus === 'rejected';

    // Intercept confirm button
    window.submitReservation = function () {
        showNotification(msg, 4000);
        if (needsUpload) {
            setTimeout(() => { window.location.href = 'profile.php?upload_id=true#validIdRequirementsCard'; }, 2000);
        }
    };

    function renderBanner() {
        const banner = document.createElement('div');
        banner.id = 'idVerifyGateBanner';
        const color = idVerifyStatus === 'rejected' ? '#f87171' : '#fbbf24';
        const icon = idVerifyStatus === 'pending' ? 'fa-user-clock' : 'fa-id-card';
        banner.style.cssText = `position:fixed;left:50%;transform:translateX(-50%);bottom:72px;z-index:9999;max-width:92%;width:560px;display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;background:rgba(20,20,20,0.96);border:1px solid ${color};box-shadow:0 8px 24px rgba(0,0,0,0.45);color:#fff;font-size:0.85rem;line-height:1.4;`;
        banner.innerHTML = `<i class="fas ${icon}" style="color:${color};font-size:1.3rem;flex-shrink:0;"></i>
            <span style="flex:1;">${msg}</span>
            ${needsUpload ? `<a href="profile.php?upload_id=true#validIdRequirementsCard" style="color:#000;background:${color};padding:6px 12px;border-radius:8px;font-weight:700;text-decoration:none;white-space:nowrap;font-size:0.78rem;">Upload ID</a>` : ''}`;
        document.body.appendChild(banner);

        const btn = document.querySelector('.reserve-button');
        if (btn) btn.classList.add('disabled-btn');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderBanner);
    } else {
        renderBanner();
    }
})();
</script>
</body>
</html>
