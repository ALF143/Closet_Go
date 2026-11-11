<?php
session_start();
require_once 'database/db_config.php';

header('Content-Type: application/json');

// Get POST data
$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$modelPath = isset($_POST['model_path']) ? $_POST['model_path'] : '';
$productName = isset($_POST['product_name']) ? $_POST['product_name'] : '';
$productCategory = isset($_POST['product_category']) ? $_POST['product_category'] : '';
$productPrice = isset($_POST['product_price']) ? $_POST['product_price'] : '';
// Get saved product ID if provided (for updating specific entry)
$savedProductId = isset($_POST['saved_product_id']) ? intval($_POST['saved_product_id']) : 0;
$productStatus = isset($_POST['product_status']) ? $_POST['product_status'] : 'saved';

// Get reservation details
$action = isset($_POST['action']) ? $_POST['action'] : '';
$reserveDate = isset($_POST['reserve_date']) ? $_POST['reserve_date'] : '';
$returnDate = isset($_POST['return_date']) ? $_POST['return_date'] : '';

// Convert empty or zero dates to NULL to avoid '0000-00-00' in database
$reserveDate = (!empty($reserveDate) && $reserveDate !== '0000-00-00') ? $reserveDate : null;
$returnDate = (!empty($returnDate) && $returnDate !== '0000-00-00') ? $returnDate : null;

// Parse measurements from JSON
$size1 = '';
$size2 = '';
$size3 = '';
if (isset($_POST['measurements'])) {
    $measurements = json_decode($_POST['measurements'], true);
    if ($measurements && isset($measurements[0])) {
        $size1 = isset($measurements[0]['size_1']) ? $measurements[0]['size_1'] : '';
        $size2 = isset($measurements[0]['size_2']) ? $measurements[0]['size_2'] : '';
        $size3 = isset($measurements[0]['size_3']) ? $measurements[0]['size_3'] : '';
    }
}

$response = ['success' => false, 'message' => ''];

try {
    // Debug: Log incoming data
    error_log("Save Product - User ID: " . $userId);
    error_log("Save Product - Product ID: " . $productId);
    error_log("Save Product - Action: " . $action);
    error_log("Save Product - POST data: " . print_r($_POST, true));
    error_log("Save Product - Session data: " . print_r($_SESSION, true));
    
    // Validate input
    if ($userId <= 0) {
        throw new Exception('User not logged in. Please login first.');
    }
    
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Handle different actions
    if ($action === 'save_reservation') {
        // Save reservation details - create new entry or update existing
        if (empty($productName) || empty($productCategory) || empty($productPrice)) {
            throw new Exception('Missing required product information');
        }

        if (empty($reserveDate) || empty($returnDate)) {
            throw new Exception('Missing required reservation details');
        }

        // Collect all size measurements
        $sizeData = [];
        for ($i = 1; $i <= 10; $i++) {
            $sizeKey = "size_{$i}";
            if (isset($_POST[$sizeKey]) && $_POST[$sizeKey] !== '') {
                $sizeData[$sizeKey] = $_POST[$sizeKey];
            }
        }
        
        // Collect size data for each item (Item 1 goes to main table, Item 2+ goes to items table)
        $quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;
        $itemData = [];
        for ($item = 1; $item <= $quantity; $item++) {
            $itemSizeData = [];
            for ($i = 1; $i <= 10; $i++) {
                $sizeKeyItem = "size_{$i}_{$item}";
                $sizeKeyPlain = "size_{$i}";
                if (isset($_POST[$sizeKeyItem]) && $_POST[$sizeKeyItem] !== '') {
                    $itemSizeData[$sizeKeyPlain] = $_POST[$sizeKeyItem];
                } elseif (isset($sizeData[$sizeKeyPlain]) && $sizeData[$sizeKeyPlain] !== '') {
                    $itemSizeData[$sizeKeyPlain] = $sizeData[$sizeKeyPlain];
                } else {
                    $itemSizeData[$sizeKeyPlain] = null;
                }
            }
            $itemData[$item] = $itemSizeData;
        }

        // Use saved_product_id if provided, otherwise find by product details
        if ($savedProductId > 0) {
            $targetProductId = $savedProductId;
            $exists = true;
        } else {
            // Find the saved product ID
            $findSavedProductSql = "SELECT id FROM saved_products WHERE user_id = ? AND product_name = ? AND product_category = ? LIMIT 1";
            $findSavedProductStmt = $conn->prepare($findSavedProductSql);
            $findSavedProductStmt->bind_param("iss", $userId, $productName, $productCategory);
            $findSavedProductStmt->execute();
            $savedProductResult = $findSavedProductStmt->get_result();

            if ($savedProductResult->num_rows > 0) {
                $targetProductId = $savedProductResult->fetch_assoc()['id'];
                $exists = true;
            } else {
                $exists = false;
            }
        }

        if ($exists) {
            // Update existing reservation details in saved_products table (Item 1 only)
            $updateReservationSql = "UPDATE saved_products SET 
                                      reserve_date = ?, return_date = ?, product_price = ?, quantity = ?, product_status = 'reserve'";
            
            // Add size columns for Item 1
            for ($i = 1; $i <= 10; $i++) {
                $sizeKey = "size_{$i}";
                $updateReservationSql .= ", {$sizeKey} = ?";
            }
            
            $updateReservationSql .= " WHERE id = ?";
            
            $updateReservationStmt = $conn->prepare($updateReservationSql);
            
            // Bind parameters - use Item 1 size data
            $types = "sssi";
            $params = [$reserveDate, $returnDate, $productPrice, $quantity];
            
            for ($i = 1; $i <= 10; $i++) {
                $sizeKey = "size_{$i}";
                $types .= "s";
                $params[] = isset($itemData[1][$sizeKey]) ? $itemData[1][$sizeKey] : null;
            }
            
            $types .= "i";
            $params[] = $targetProductId;
            
            $updateReservationStmt->bind_param($types, ...$params);
            
            if (!$updateReservationStmt->execute()) {
                throw new Exception('Failed to save reservation details');
            }
            $newSavedProductId = $targetProductId;
            
            // Delete existing items for this saved product
            $deleteItemsSql = "DELETE FROM saved_products_items WHERE saved_product_id = ?";
            $deleteItemsStmt = $conn->prepare($deleteItemsSql);
            $deleteItemsStmt->bind_param("i", $targetProductId);
            $deleteItemsStmt->execute();
            
            // Insert Item 2+ into saved_products_items table
            for ($item = 2; $item <= $quantity; $item++) {
                $insertItemSql = "INSERT INTO saved_products_items 
                                  (saved_product_id, item_number";
                
                // Add size columns
                for ($i = 1; $i <= 10; $i++) {
                    $insertItemSql .= ", size_{$i}";
                }
                
                $insertItemSql .= ") VALUES (?, ?";
                
                // Add size placeholders
                for ($i = 1; $i <= 10; $i++) {
                    $insertItemSql .= ", ?";
                }
                
                $insertItemSql .= ")";
                
                $insertItemStmt = $conn->prepare($insertItemSql);
                
                // Bind parameters
                $types = "ii";
                $params = [$targetProductId, $item];
                
                for ($i = 1; $i <= 10; $i++) {
                    $sizeKey = "size_{$i}";
                    $types .= "s";
                    $params[] = isset($itemData[$item][$sizeKey]) ? $itemData[$item][$sizeKey] : null;
                }
                
                $insertItemStmt->bind_param($types, ...$params);
                
                if (!$insertItemStmt->execute()) {
                    throw new Exception('Failed to save item ' . $item . ' details');
                }
            }
            
            $response['success'] = true;
            $response['message'] = 'Reservation details saved successfully!';
            $response['saved_product_id'] = $newSavedProductId;
        } else {
            // Create new entry in saved_products table (Item 1 only)
            $insertReservationSql = "INSERT INTO saved_products 
                                      (user_id, product_id, product_name, product_category, product_price, model_path, quantity, reserve_date, return_date, product_status";
            
            // Add size columns for Item 1
            for ($i = 1; $i <= 10; $i++) {
                $insertReservationSql .= ", size_{$i}";
            }
            
            $insertReservationSql .= ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'reserve'";
            
            // Add size placeholders for Item 1
            for ($i = 1; $i <= 10; $i++) {
                $insertReservationSql .= ", ?";
            }
            
            $insertReservationSql .= ")";
            
            $insertReservationStmt = $conn->prepare($insertReservationSql);
            
            // Bind parameters - use Item 1 size data
            $types = "iisssssis";
            $params = [$userId, $productId, $productName, $productCategory, $productPrice, $modelPath, $quantity, $reserveDate, $returnDate];
            
            for ($i = 1; $i <= 10; $i++) {
                $sizeKey = "size_{$i}";
                $types .= "s";
                $params[] = isset($itemData[1][$sizeKey]) ? $itemData[1][$sizeKey] : null;
            }
            
            $insertReservationStmt->bind_param($types, ...$params);
            
            if (!$insertReservationStmt->execute()) {
                throw new Exception('Failed to save reservation details');
            }
            
            // Get the ID of the newly inserted record
            $newSavedProductId = $insertReservationStmt->insert_id;
            
            // Insert Item 2+ into saved_products_items table
            for ($item = 2; $item <= $quantity; $item++) {
                $insertItemSql = "INSERT INTO saved_products_items 
                                  (saved_product_id, item_number, size_1, size_2, size_3, size_4, size_5, size_6, size_7, size_8, size_9, size_10)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insertItemStmt = $conn->prepare($insertItemSql);
                $types = "ii" . str_repeat("s", 10);
                $params = [$newSavedProductId, $item];
                for ($i = 1; $i <= 10; $i++) {
                    $sizeKey = "size_{$i}";
                    $params[] = isset($itemData[$item][$sizeKey]) ? $itemData[$item][$sizeKey] : null;
                }
                $insertItemStmt->bind_param($types, ...$params);
                if (!$insertItemStmt->execute()) {
                    throw new Exception('Failed to save item ' . $item . ' details');
                }
            }
            
            $response['success'] = true;
            $response['message'] = 'Reservation details saved successfully!';
            $response['saved_product_id'] = $newSavedProductId;
        }
        
    } elseif ($action === 'save_buy') {
        // Save buy details - create new entry or update existing
        if (empty($productName) || empty($productCategory) || empty($productPrice)) {
            throw new Exception('Missing required product information');
        }

        if (empty($reserveDate) || empty($returnDate)) {
            throw new Exception('Missing required reservation details');
        }

        // Collect all size measurements
        $sizeData = [];
        for ($i = 1; $i <= 10; $i++) {
            $sizeKey = "size_{$i}";
            if (isset($_POST[$sizeKey]) && $_POST[$sizeKey] !== '') {
                $sizeData[$sizeKey] = $_POST[$sizeKey];
            }
        }
        
        // Collect size data for each item (Item 1 goes to main table, Item 2+ goes to items table)
        $quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;
        $itemData = [];
        for ($item = 1; $item <= $quantity; $item++) {
            $itemSizeData = [];
            for ($i = 1; $i <= 10; $i++) {
                $sizeKeyItem = "size_{$i}_{$item}";
                $sizeKeyPlain = "size_{$i}";
                if (isset($_POST[$sizeKeyItem]) && $_POST[$sizeKeyItem] !== '') {
                    $itemSizeData[$sizeKeyPlain] = $_POST[$sizeKeyItem];
                } elseif (isset($sizeData[$sizeKeyPlain]) && $sizeData[$sizeKeyPlain] !== '') {
                    $itemSizeData[$sizeKeyPlain] = $sizeData[$sizeKeyPlain];
                } else {
                    $itemSizeData[$sizeKeyPlain] = null;
                }
            }
            $itemData[$item] = $itemSizeData;
        }

        // Use saved_product_id if provided, otherwise find by product details
        if ($savedProductId > 0) {
            $targetProductId = $savedProductId;
            $exists = true;
        } else {
            // Find the saved product ID
            $findSavedProductSql = "SELECT id FROM saved_products WHERE user_id = ? AND product_name = ? AND product_category = ? LIMIT 1";
            $findSavedProductStmt = $conn->prepare($findSavedProductSql);
            $findSavedProductStmt->bind_param("iss", $userId, $productName, $productCategory);
            $findSavedProductStmt->execute();
            $savedProductResult = $findSavedProductStmt->get_result();

            if ($savedProductResult->num_rows > 0) {
                $targetProductId = $savedProductResult->fetch_assoc()['id'];
                $exists = true;
            } else {
                $exists = false;
            }
        }

        if ($exists) {
            // Update existing buy details in saved_products table with 'buy' status (Item 1 only)
            $updateBuySql = "UPDATE saved_products SET 
                             reserve_date = ?, return_date = ?, product_price = ?, quantity = ?, product_status = 'buy'";
            
            // Add size columns for Item 1
            for ($i = 1; $i <= 10; $i++) {
                $sizeKey = "size_{$i}";
                $updateBuySql .= ", {$sizeKey} = ?";
            }
            
            $updateBuySql .= " WHERE id = ?";
            
            $updateBuyStmt = $conn->prepare($updateBuySql);
            
            // Bind parameters - use Item 1 size data
            $types = "sssi";
            $params = [$reserveDate, $returnDate, $productPrice, $quantity];
            
            for ($i = 1; $i <= 10; $i++) {
                $sizeKey = "size_{$i}";
                $types .= "s";
                $params[] = isset($itemData[1][$sizeKey]) ? $itemData[1][$sizeKey] : null;
            }
            
            $types .= "i";
            $params[] = $targetProductId;
            
            $updateBuyStmt->bind_param($types, ...$params);
            
            if (!$updateBuyStmt->execute()) {
                throw new Exception('Failed to save buy details');
            }
            $newSavedProductId = $targetProductId;
            
            // Delete existing items for this saved product
            $deleteItemsSql = "DELETE FROM saved_products_items WHERE saved_product_id = ?";
            $deleteItemsStmt = $conn->prepare($deleteItemsSql);
            $deleteItemsStmt->bind_param("i", $targetProductId);
            $deleteItemsStmt->execute();
            
            // Insert Item 2+ into saved_products_items table
            for ($item = 2; $item <= $quantity; $item++) {
                $insertItemSql = "INSERT INTO saved_products_items 
                                  (saved_product_id, item_number, size_1, size_2, size_3, size_4, size_5, size_6, size_7, size_8, size_9, size_10)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insertItemStmt = $conn->prepare($insertItemSql);
                $types = "ii" . str_repeat("s", 10);
                $params = [$targetProductId, $item];
                for ($i = 1; $i <= 10; $i++) {
                    $sizeKey = "size_{$i}";
                    $params[] = isset($itemData[$item][$sizeKey]) ? $itemData[$item][$sizeKey] : null;
                }
                $insertItemStmt->bind_param($types, ...$params);
                if (!$insertItemStmt->execute()) {
                    throw new Exception('Failed to save item ' . $item . ' details');
                }
            }
            
            $response['success'] = true;
            $response['message'] = 'Buy details saved successfully!';
            $response['saved_product_id'] = $newSavedProductId;
        } else {
            // Create new entry in saved_products table (Item 1 only)
            $insertBuySql = "INSERT INTO saved_products 
                              (user_id, product_id, product_name, product_category, product_price, model_path, quantity, reserve_date, return_date, product_status";
            
            // Add size columns for Item 1
            for ($i = 1; $i <= 10; $i++) {
                $insertBuySql .= ", size_{$i}";
            }
            
            $insertBuySql .= ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'buy'";
            
            // Add size placeholders for Item 1
            for ($i = 1; $i <= 10; $i++) {
                $insertBuySql .= ", ?";
            }
            
            $insertBuySql .= ")";
            
            $insertBuyStmt = $conn->prepare($insertBuySql);
            
            // Bind parameters - use Item 1 size data
            $types = "iisssssis";
            $params = [$userId, $productId, $productName, $productCategory, $productPrice, $modelPath, $quantity, $reserveDate, $returnDate];
            
            for ($i = 1; $i <= 10; $i++) {
                $sizeKey = "size_{$i}";
                $types .= "s";
                $params[] = isset($itemData[1][$sizeKey]) ? $itemData[1][$sizeKey] : null;
            }
            
            $insertBuyStmt->bind_param($types, ...$params);
            
            if (!$insertBuyStmt->execute()) {
                throw new Exception('Failed to save buy details');
            }
            
            // Get the ID of the newly inserted record
            $newSavedProductId = $insertBuyStmt->insert_id;
            
            // Insert Item 2+ into saved_products_items table
            for ($item = 2; $item <= $quantity; $item++) {
                $insertItemSql = "INSERT INTO saved_products_items 
                                  (saved_product_id, item_number, size_1, size_2, size_3, size_4, size_5, size_6, size_7, size_8, size_9, size_10)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insertItemStmt = $conn->prepare($insertItemSql);
                $types = "ii" . str_repeat("s", 10);
                $params = [$newSavedProductId, $item];
                for ($i = 1; $i <= 10; $i++) {
                    $sizeKey = "size_{$i}";
                    $params[] = isset($itemData[$item][$sizeKey]) ? $itemData[$item][$sizeKey] : null;
                }
                $insertItemStmt->bind_param($types, ...$params);
                if (!$insertItemStmt->execute()) {
                    throw new Exception('Failed to save item ' . $item . ' details');
                }
            }
            
            $response['success'] = true;
            $response['message'] = 'Buy details saved successfully!';
            $response['saved_product_id'] = $newSavedProductId;
        }
        
    } elseif ($action === 'delete_product') {
        // Delete product from saved_products table
        if (empty($productName) || empty($productCategory)) {
            throw new Exception('Missing product information');
        }
        
        // Delete only the specific cart row when its id is known, so duplicate
        // entries of the same product are not removed together
        if ($savedProductId > 0) {
            $deleteSql = "DELETE FROM saved_products WHERE user_id = ? AND id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param("ii", $userId, $savedProductId);
        } else {
            $deleteSql = "DELETE FROM saved_products 
                          WHERE user_id = ? AND product_name = ? AND product_category = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param("iss", $userId, $productName, $productCategory);
        }
        
        if (!$deleteStmt->execute()) {
            throw new Exception('Failed to delete product');
        }
        
        if ($deleteStmt->affected_rows === 0) {
            throw new Exception('Product not found in saved items');
        }
        
        $response['success'] = true;
        $response['message'] = 'Product deleted successfully!';
        
    } elseif ($action === 'get_purchase_price') {
        // Get purchase price from gowns table
        if (empty($productId)) {
            throw new Exception('Missing product ID');
        }
        
        $getPurchasePriceSql = "SELECT purchase_price FROM gowns WHERE id = ?";
        $getPurchasePriceStmt = $conn->prepare($getPurchasePriceSql);
        $getPurchasePriceStmt->bind_param("i", $productId);
        $getPurchasePriceStmt->execute();
        $purchasePriceResult = $getPurchasePriceStmt->get_result();
        
        if ($purchasePriceResult->num_rows > 0) {
            $purchasePriceData = $purchasePriceResult->fetch_assoc();
            $response['success'] = true;
            $response['purchase_price'] = $purchasePriceData['purchase_price'];
            $response['message'] = 'Purchase price found';
        } else {
            $response['success'] = false;
            $response['message'] = 'Purchase price not found';
        }
        
    } elseif ($action === 'get_reservation') {
        // Get reservation details for a product
        if (empty($productName) || empty($productCategory)) {
            throw new Exception('Missing product information');
        }
        
        // Find reservation details in saved_products table (Item 1)
        // Target the exact cart row when its id is known so duplicates stay independent
        if ($savedProductId > 0) {
            $getReservationSql = "SELECT id, product_status, quantity, reserve_date, return_date, size_1, size_2, size_3, size_4, size_5, size_6, size_7, size_8, size_9, size_10 
                                  FROM saved_products 
                                  WHERE user_id = ? AND id = ? 
                                  AND reserve_date IS NOT NULL";
            $getReservationStmt = $conn->prepare($getReservationSql);
            $getReservationStmt->bind_param("ii", $userId, $savedProductId);
        } else {
            $getReservationSql = "SELECT id, product_status, quantity, reserve_date, return_date, size_1, size_2, size_3, size_4, size_5, size_6, size_7, size_8, size_9, size_10 
                                  FROM saved_products 
                                  WHERE user_id = ? AND product_name = ? AND product_category = ? 
                                  AND reserve_date IS NOT NULL";
            $getReservationStmt = $conn->prepare($getReservationSql);
            $getReservationStmt->bind_param("iss", $userId, $productName, $productCategory);
        }
        $getReservationStmt->execute();
        $reservationResult = $getReservationStmt->get_result();
        
        if ($reservationResult->num_rows > 0) {
            $reservation = $reservationResult->fetch_assoc();
            $savedProductId = $reservation['id'];
            
            // Fetch additional items from saved_products_items table (Item 2+)
            $getItemsSql = "SELECT item_number, size_1, size_2, size_3, size_4, size_5, size_6, size_7, size_8, size_9, size_10 
                            FROM saved_products_items 
                            WHERE saved_product_id = ? 
                            ORDER BY item_number";
            $getItemsStmt = $conn->prepare($getItemsSql);
            $getItemsStmt->bind_param("i", $savedProductId);
            $getItemsStmt->execute();
            $itemsResult = $getItemsStmt->get_result();
            
            $additionalItems = [];
            while ($itemRow = $itemsResult->fetch_assoc()) {
                $itemNumber = $itemRow['item_number'];
                $additionalItems[$itemNumber] = $itemRow;
            }
            
            $response['success'] = true;
            $response['reservation'] = $reservation;
            $response['additional_items'] = $additionalItems;
            $response['message'] = 'Reservation details found';
        } else {
            $response['success'] = false;
            $response['message'] = 'No reservation details found';
        }
        
    } elseif ($action === 'get_status') {
        // Get product status from database
        if (empty($productName) || empty($productCategory)) {
            throw new Exception('Missing product information');
        }
        
        // Find product status in saved_products table
        if ($savedProductId > 0) {
            $getStatusSql = "SELECT product_status FROM saved_products WHERE user_id = ? AND id = ?";
            $getStatusStmt = $conn->prepare($getStatusSql);
            $getStatusStmt->bind_param("ii", $userId, $savedProductId);
        } else {
            $getStatusSql = "SELECT product_status FROM saved_products 
                             WHERE user_id = ? AND product_name = ? AND product_category = ?";
            $getStatusStmt = $conn->prepare($getStatusSql);
            $getStatusStmt->bind_param("iss", $userId, $productName, $productCategory);
        }
        $getStatusStmt->execute();
        $statusResult = $getStatusStmt->get_result();
        
        if ($statusResult->num_rows > 0) {
            $statusData = $statusResult->fetch_assoc();
            $response['success'] = true;
            $response['product_status'] = $statusData['product_status'];
            $response['message'] = 'Product status found';
        } else {
            $response['success'] = false;
            $response['message'] = 'Product status not found';
        }
        
    } elseif ($action === 'update_status') {
        // Update product status to 'buy' or 'reserve'
        if (empty($productName) || empty($productCategory)) {
            throw new Exception('Missing product information');
        }
        
        if (empty($productStatus) || !in_array($productStatus, ['buy', 'reserve'])) {
            throw new Exception('Invalid product status');
        }
        
        // Update product status in saved_products table
        if ($savedProductId > 0) {
            $updateStatusSql = "UPDATE saved_products SET product_status = ? WHERE user_id = ? AND id = ?";
            $updateStatusStmt = $conn->prepare($updateStatusSql);
            $updateStatusStmt->bind_param("sii", $productStatus, $userId, $savedProductId);
        } else {
            $updateStatusSql = "UPDATE saved_products SET product_status = ? 
                                WHERE user_id = ? AND product_name = ? AND product_category = ?";
            $updateStatusStmt = $conn->prepare($updateStatusSql);
            $updateStatusStmt->bind_param("siss", $productStatus, $userId, $productName, $productCategory);
        }
        
        if (!$updateStatusStmt->execute()) {
            throw new Exception('Failed to update product status');
        }
        
        $response['success'] = true;
        $response['message'] = 'Product status updated successfully!';
        $response['product_status'] = $productStatus;
        
    } elseif ($action === 'add_to_cart') {
        // Add product to cart with dates and sizes
        if ($productId <= 0) {
            throw new Exception('Invalid product ID');
        }
        
        if (empty($productName) || empty($productCategory) || empty($productPrice)) {
            throw new Exception('Missing required product information');
        }
        
        $cartStatus = (isset($_POST['product_status']) && $_POST['product_status'] === 'buy') ? 'buy' : 'saved';
        
        if ($cartStatus === 'buy') {
            if (empty($reserveDate)) {
                throw new Exception('Missing purchase date');
            }
            // Use purchase price if provided and valid
            if (isset($_POST['purchase_price']) && floatval($_POST['purchase_price']) > 0) {
                $productPrice = strval($_POST['purchase_price']);
            }
        } else {
            if (empty($reserveDate) || empty($returnDate)) {
                throw new Exception('Missing reserve or return date');
            }
        }
        
        // Collect size measurements (checking size_i or size_i_1 for item 1)
        $sizeData = [];
        for ($i = 1; $i <= 10; $i++) {
            $sizeKey = "size_{$i}";
            $sizeKeyItem1 = "size_{$i}_1";
            if (isset($_POST[$sizeKey]) && !empty($_POST[$sizeKey])) {
                $sizeData[$sizeKey] = $_POST[$sizeKey];
            } elseif (isset($_POST[$sizeKeyItem1]) && !empty($_POST[$sizeKeyItem1])) {
                $sizeData[$sizeKey] = $_POST[$sizeKeyItem1];
            }
        }
        
        if (empty($sizeData)) {
            throw new Exception('Please select sizes');
        }
        
        // Always create a new cart row so the same product can be added again
        // as a separate entry with its own id, dates and sizes
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 999;

        $insertCartSql = "INSERT INTO saved_products 
                          (user_id, product_id, product_name, product_category, product_price, model_path, quantity, reserve_date, return_date, product_status, stock";

        // Add size columns
        for ($i = 1; $i <= 10; $i++) {
            $insertCartSql .= ", size_{$i}";
        }

        $insertCartSql .= ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?";

        // Add size placeholders
        for ($i = 1; $i <= 10; $i++) {
            $insertCartSql .= ", ?";
        }

        $insertCartSql .= ")";

        $insertCartStmt = $conn->prepare($insertCartSql);

        // Bind parameters
        $types = "iissssisssi";
        $params = [$userId, $productId, $productName, $productCategory, $productPrice, $modelPath, $quantity, $reserveDate, $returnDate, $cartStatus, $stock];

        for ($i = 1; $i <= 10; $i++) {
            $sizeKey = "size_{$i}";
            $types .= "s";
            $params[] = isset($sizeData[$sizeKey]) ? $sizeData[$sizeKey] : null;
        }

        $insertCartStmt->bind_param($types, ...$params);

        if (!$insertCartStmt->execute()) {
            throw new Exception('Failed to add product to cart');
        }

        $newCartId = $insertCartStmt->insert_id;
        for ($item = 2; $item <= $quantity; $item++) {
            $insertItemSql = "INSERT INTO saved_products_items 
                              (saved_product_id, item_number, size_1, size_2, size_3, size_4, size_5, size_6, size_7, size_8, size_9, size_10)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertItemStmt = $conn->prepare($insertItemSql);
            $itemTypes = "ii" . str_repeat("s", 10);
            $itemParams = [$newCartId, $item];
            for ($i = 1; $i <= 10; $i++) {
                $sizeKeyItem = "size_{$i}_{$item}";
                $sizeKey = "size_{$i}";
                if (isset($_POST[$sizeKeyItem]) && $_POST[$sizeKeyItem] !== '') {
                    $itemParams[] = $_POST[$sizeKeyItem];
                } elseif (isset($sizeData[$sizeKey]) && $sizeData[$sizeKey] !== '') {
                    $itemParams[] = $sizeData[$sizeKey];
                } else {
                    $itemParams[] = null;
                }
            }
            $insertItemStmt->bind_param($itemTypes, ...$itemParams);
            $insertItemStmt->execute();
        }

        $response['success'] = true;
        $response['message'] = 'Product added to cart!';
        $response['saved_product_id'] = $newCartId;
        $response['quantity'] = $quantity;
        
    } else {
        // Original save product logic
        if ($productId <= 0) {
            throw new Exception('Invalid product ID');
        }
        
        if (empty($productName) || empty($productCategory) || empty($productPrice)) {
            throw new Exception('Missing required product information');
        }
        
        // Verify product exists in database
        $checkProductSql = "SELECT id, name FROM gowns WHERE id = ?";
        $checkProductStmt = $conn->prepare($checkProductSql);
        $checkProductStmt->bind_param("i", $productId);
        $checkProductStmt->execute();
        $productResult = $checkProductStmt->get_result();
        
        if ($productResult->num_rows === 0) {
            throw new Exception('Product not found in database');
        }
        
        $productData = $productResult->fetch_assoc();
        error_log("Save Product - Found product: " . print_r($productData, true));
        
        // Check if product is already saved by this user (removed to allow multiple entries)
        // Users can now save the same product multiple times with different sizes/statuses
        
        // Insert new saved product
        $insertSql = "INSERT INTO saved_products (user_id, product_id, model_path, product_name, product_category, product_price, purchase_price, product_status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("iisssdds", $userId, $productId, $modelPath, $productName, $productCategory, $productPrice, $purchasePrice, $productStatus);
        
        if (!$insertStmt->execute()) {
            throw new Exception('Failed to save product');
        }
        
        $response['success'] = true;
        $response['message'] = 'Product saved successfully!';
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
