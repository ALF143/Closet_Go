<?php
session_start();
require_once '../database/db_config.php';

// CategoryManager class for fetching categories
class CategoryManager {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    private function getConnection() {
        return $this->db->getConnection();
    }

    public function getAllCategories() {
        $conn = $this->getConnection();

        // Get categories from categories table (with images)
        $sql = "SELECT * FROM categories ORDER BY category_name ASC";
        $result = $conn->query($sql);

        $managedCategories = [];
        $managedLookup = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $name = trim($row['category_name']);
                $managedCategories[$name] = $row;
                $managedLookup[strtolower($name)] = $name;
            }
        }

        // Get categories from gowns table (without images)
        $sql = "SELECT DISTINCT category FROM gowns WHERE category IS NOT NULL AND TRIM(category) != '' ORDER BY category ASC";
        $result = $conn->query($sql);

        $gownCategories = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $trimmed = trim($row['category']);
                if (!empty($trimmed)) {
                    $gownCategories[] = $trimmed;
                }
            }
        }

        // Merge categories - prioritize managed categories
        $allCategories = [];
        $addedLookup = [];

        foreach ($managedCategories as $categoryName => $category) {
            $lower = strtolower($categoryName);
            $category['from_gowns'] = false;
            $allCategories[] = $category;
            $addedLookup[$lower] = true;
        }

        foreach ($gownCategories as $categoryName) {
            $lower = strtolower($categoryName);
            if (!isset($addedLookup[$lower])) {
                $allCategories[] = [
                    'id' => null,
                    'category_name' => $categoryName,
                    'category_image' => null,
                    'created_at' => null,
                    'from_gowns' => true
                ];
                $addedLookup[$lower] = true;
            }
        }

        return $allCategories;
    }
}

// Handle API requests
if (isset($_GET['action']) && $_GET['action'] == 'getAllGowns') {
    header('Content-Type: application/json');
    
    $gownManager = new GownManager();
    $gowns = $gownManager->getAllGowns();
    
    // Filter out out-of-stock products if requested (for customer-facing pages)
    if (isset($_GET['hide_out_of_stock']) && $_GET['hide_out_of_stock'] == '1') {
        $gowns = array_filter($gowns, function($g) {
            return intval($g['stock'] ?? 0) > 0;
        });
        $gowns = array_values($gowns);
    }
    
    echo json_encode($gowns);
    exit();
}

// Handle Top Reserved Gowns API request
if (isset($_GET['action']) && $_GET['action'] == 'getTopReservedGowns') {
    header('Content-Type: application/json');
    $db = new Database();
    $conn = $db->getConnection();
    
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 6;
    if ($limit <= 0) $limit = 6;
    
    $sql = "
        SELECT g.id, g.name, g.category, g.color, g.price, g.discount, g.image_path, g.image_path2, g.image_path3, g.image_path4, g.model_path, g.stock,
               COUNT(rp.id) as times_reserved
        FROM gowns g
        LEFT JOIN reserve_products rp ON (rp.product_id = g.id OR rp.product_name = g.name)
        WHERE g.stock > 0
        GROUP BY g.id, g.name, g.category, g.color, g.price, g.discount, g.image_path, g.image_path2, g.image_path3, g.image_path4, g.model_path, g.stock
        ORDER BY times_reserved DESC, g.id DESC
        LIMIT ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $topProducts = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $topProducts[] = $row;
        }
    }
    $stmt->close();
    
    echo json_encode($topProducts);
    exit();
}

// Check if POST Content-Length exceeded post_max_size (which causes empty $_POST in PHP)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && intval($_SERVER['CONTENT_LENGTH']) > 0) {
    $sizeMB = round(intval($_SERVER['CONTENT_LENGTH']) / (1024 * 1024), 1);
    $_SESSION['error_message'] = "Uploaded content is too large ({$sizeMB} MB) and exceeds the server's post_max_size limit. Please choose a smaller file.";
    header('Location: gowns.php');
    exit();
}

// Handle Add New Product form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_gown') {
    // Enable error reporting but prevent HTML output
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);

    header('Content-Type: application/json');

    try {
        $gownManager = new GownManager();

        // Debug: Log all POST data
        error_log("Add Gown POST data: " . print_r($_POST, true));
        error_log("Add Gown FILES data: " . print_r($_FILES, true));

        $name = $_POST['gownName'] ?? '';
        $category = $_POST['gownCategory'] ?? '';
        $price = $_POST['price'] ?? 0;
        $purchase_price = $_POST['purchase_price'] ?? 0;
        $stock = $_POST['stock'] ?? 0;
        $color = $_POST['color'] ?? '';
        $description = $_POST['description'] ?? '';

        // Handle dynamic size ranges (size1 to size10)
        $sizeRanges = [];
        for ($i = 1; $i <= 10; $i++) {
            $minKey = "size{$i}Min";
            $maxKey = "size{$i}Max";
            $sizeRanges[$i]['min'] = (isset($_POST[$minKey]) && $_POST[$minKey] !== '') ? intval($_POST[$minKey]) : null;
            $sizeRanges[$i]['max'] = (isset($_POST[$maxKey]) && $_POST[$maxKey] !== '') ? intval($_POST[$maxKey]) : null;
        }

        error_log("Add Gown values - Name: $name, Category: $category, Price: $price");

        $imagePath = null;
        $imagePath2 = null;
        $imagePath3 = null;
        $imagePath4 = null;
        $modelPath = null;

        // Process images from array, individual slots (image1, image2, image3, image4), or fallback
        $uploadDir = '../images/gowns/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Handle 4 individual image slots: image1, image2, image3, image4
        for ($slot = 1; $slot <= 4; $slot++) {
            $slotKey = 'image' . $slot;
            if (isset($_FILES[$slotKey]) && $_FILES[$slotKey]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$slotKey]['name'], PATHINFO_EXTENSION);
                $imageName = time() . '_' . $slot . '_' . uniqid() . '.' . $ext;
                $currentImagePath = 'images/gowns/' . $imageName;
                if (move_uploaded_file($_FILES[$slotKey]['tmp_name'], $uploadDir . $imageName)) {
                    if ($slot == 1) $imagePath = $currentImagePath;
                    elseif ($slot == 2) $imagePath2 = $currentImagePath;
                    elseif ($slot == 3) $imagePath3 = $currentImagePath;
                    elseif ($slot == 4) $imagePath4 = $currentImagePath;
                    error_log("Uploaded slot $slot image: $currentImagePath");
                }
            }
        }

        // Fallback: Handle multiple images[] array upload
        if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
            $imageFiles = $_FILES['images'];
            $fileCount = count($imageFiles['name']);
            for ($i = 0; $i < min($fileCount, 4); $i++) {
                if ($imageFiles['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($imageFiles['name'][$i], PATHINFO_EXTENSION);
                    $imageName = time() . '_' . ($i + 1) . '_' . uniqid() . '.' . $ext;
                    $currentImagePath = 'images/gowns/' . $imageName;
                    if (move_uploaded_file($imageFiles['tmp_name'][$i], $uploadDir . $imageName)) {
                        if ($i == 0 && empty($imagePath)) $imagePath = $currentImagePath;
                        elseif ($i == 1 && empty($imagePath2)) $imagePath2 = $currentImagePath;
                        elseif ($i == 2 && empty($imagePath3)) $imagePath3 = $currentImagePath;
                        elseif ($i == 3 && empty($imagePath4)) $imagePath4 = $currentImagePath;
                        error_log("Uploaded images array item $i: $currentImagePath");
                    }
                }
            }
        }

        // Handle 3D model
        if (isset($_FILES['model']) && $_FILES['model']['error'] === UPLOAD_ERR_OK) {
            error_log("Processing 3D model upload");
            $uploadDir = '../models/gowns/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
                error_log("Created models directory: $uploadDir");
            }
            $modelName = time() . '_' . basename($_FILES['model']['name']);
            $modelPath = 'models/gowns/' . $modelName;
            error_log("Processing model: $modelName to $uploadDir$ modelName");
            
            if (move_uploaded_file($_FILES['model']['tmp_name'], $uploadDir . $modelName)) {
                error_log("Successfully uploaded model: $modelPath");
            } else {
                error_log("Failed to upload model");
            }
        } else {
            error_log("No model upload found or error code: " . (isset($_FILES['model']) ? $_FILES['model']['error'] : 'not set'));
        }

        error_log("Calling addGown with paths - Image: $imagePath, Image2: $imagePath2, Image3: $imagePath3, Image4: $imagePath4, Model: $modelPath");

        $result = $gownManager->addGown($name, $category, $price, $description, $imagePath, $imagePath2, $imagePath3, $imagePath4, $modelPath, 0, $stock, $color, $purchase_price, $sizeRanges);
        
        error_log("addGown result: " . ($result ? 'true' : 'false'));
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Product added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add product. Check error logs for details.']);
        }
    } catch (Exception $e) {
        error_log("Exception in add_gown: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'PHP Error: ' . $e->getMessage()]);
    } catch (Error $e) {
        error_log("Error in add_gown: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'PHP Error: ' . $e->getMessage()]);
    }
    exit();
}

class GownManager {
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->ensureSizeRangeColumns();
    }

    private function getConnection() {
        return $this->db->getConnection();
    }

    private function ensureSizeRangeColumns() {
        $conn = $this->getConnection();
        // Add columns if they don't exist
        $this->addColumnIfNotExists('gowns', 'size_1_min', "INT DEFAULT 70");
        $this->addColumnIfNotExists('gowns', 'size_1_max', "INT DEFAULT 120");
        $this->addColumnIfNotExists('gowns', 'size_2_min', "INT DEFAULT 60");
        $this->addColumnIfNotExists('gowns', 'size_2_max', "INT DEFAULT 100");
        $this->addColumnIfNotExists('gowns', 'size_3_min', "INT DEFAULT 80");
        $this->addColumnIfNotExists('gowns', 'size_3_max', "INT DEFAULT 130");
        $this->addColumnIfNotExists('gowns', 'size_4_min', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'size_4_max', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'size_5_min', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'size_5_max', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'size_6_min', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'size_6_max', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'size_7_min', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'size_7_max', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'size_8_min', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'size_8_max', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'size_9_min', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'size_9_max', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'size_10_min', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'size_10_max', "INT DEFAULT 0");
        $this->addColumnIfNotExists('gowns', 'image_path4', "VARCHAR(500) NULL DEFAULT NULL");
    }

    private function addColumnIfNotExists($table, $column, $definition) {
        $conn = $this->getConnection();
        $checkSql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
        $result = $conn->query($checkSql);
        if ($result->num_rows == 0) {
            $alterSql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
            $conn->query($alterSql);
        }
    }

    // Add new gown
    public function addGown($name, $category, $price, $description, $imagePath = null, $imagePath2 = null, $imagePath3 = null, $imagePath4 = null, $modelPath = null, $discount = 0, $stock = 0, $color = '', $purchase_price = 0, $sizeRanges = []) {
        $conn = $this->db->getConnection();

        // Build dynamic SQL based on which size ranges are provided
        $columns = ['name', 'category', 'price', 'description', 'image_path', 'image_path2', 'image_path3', 'image_path4', 'model_path', 'discount', 'stock', 'color', 'purchase_price'];
        $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'];
        $params = [$name, $category, $price, $description, $imagePath, $imagePath2, $imagePath3, $imagePath4, $modelPath, $discount, $stock, $color, $purchase_price];
        $types = "ssdssssssdisd";

        // Add size range columns dynamically
        for ($i = 1; $i <= 10; $i++) {
            if (isset($sizeRanges[$i]) && $sizeRanges[$i]['min'] !== null && $sizeRanges[$i]['max'] !== null) {
                $columns[] = "size_{$i}_min";
                $columns[] = "size_{$i}_max";
                $placeholders[] = '?';
                $placeholders[] = '?';
                $params[] = $sizeRanges[$i]['min'];
                $params[] = $sizeRanges[$i]['max'];
                $types .= 'ii';
            }
        }

        $sql = "INSERT INTO gowns (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }

    // Get all gowns
    public function getAllGowns() {
        $conn = $this->db->getConnection();
        
        $result = $conn->query("SELECT * FROM gowns ORDER BY discount DESC, id DESC");
        $gowns = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $gowns[] = $row;
            }
        }
        
        return $gowns;
    }

    // Get gown by ID
    public function getGownById($id) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM gowns WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Update gown
    public function updateGown($id, $name, $category, $price, $discount = 0, $color = null, $description = null, $imagePath = null, $imagePath2 = null, $imagePath3 = null, $imagePath4 = null, $modelPath = null, $stock = null, $purchase_price = null, $sizeRanges = []) {
        $conn = $this->db->getConnection();

        // Build dynamic query based on what parameters are provided
        $sql = "UPDATE gowns SET name = ?, category = ?, price = ?, discount = ?";
        $params = [$name, $category, $price, $discount];
        $types = "ssdi";

        // Add color if provided
        if ($color !== null) {
            $sql .= ", color = ?";
            $params[] = $color;
            $types .= "s";
        }

        // Add description if provided
        if ($description !== null) {
            $sql .= ", description = ?";
            $params[] = $description;
            $types .= "s";
        }

        // Add image path if provided
        if ($imagePath !== null) {
            $sql .= ", image_path = ?";
            $params[] = $imagePath;
            $types .= "s";
        }

        // Add image path 2 if provided
        if ($imagePath2 !== null) {
            $sql .= ", image_path2 = ?";
            $params[] = $imagePath2;
            $types .= "s";
        }

        // Add image path 3 if provided
        if ($imagePath3 !== null) {
            $sql .= ", image_path3 = ?";
            $params[] = $imagePath3;
            $types .= "s";
        }

        // Add image path 4 if provided
        if ($imagePath4 !== null) {
            $sql .= ", image_path4 = ?";
            $params[] = $imagePath4;
            $types .= "s";
        }

        // Add model path (handle NULL for removal)
        if ($modelPath !== null) {
            $sql .= ", model_path = ?";
            $params[] = $modelPath;
            $types .= "s";
        } else {
            $sql .= ", model_path = NULL";
        }

        // Add stock if provided
        if ($stock !== null) {
            $sql .= ", stock = ?";
            $params[] = $stock;
            $types .= "i";
        }

        // Add purchase_price if provided
        if ($purchase_price !== null) {
            $sql .= ", purchase_price = ?";
            $params[] = $purchase_price;
            $types .= "d";
        }

        // Add dynamic size ranges (size1 to size10)
        for ($i = 1; $i <= 10; $i++) {
            if (isset($sizeRanges[$i])) {
                $minVal = (isset($sizeRanges[$i]['min']) && $sizeRanges[$i]['min'] !== null && $sizeRanges[$i]['min'] !== '') ? intval($sizeRanges[$i]['min']) : null;
                $maxVal = (isset($sizeRanges[$i]['max']) && $sizeRanges[$i]['max'] !== null && $sizeRanges[$i]['max'] !== '') ? intval($sizeRanges[$i]['max']) : null;

                $sql .= ", size_{$i}_min = ?, size_{$i}_max = ?";
                $params[] = $minVal;
                $params[] = $maxVal;
                $types .= ($minVal !== null ? "i" : "s") . ($maxVal !== null ? "i" : "s");
            }
        }

        // Add WHERE clause
        $sql .= " WHERE id = ?";
        $params[] = $id;
        $types .= "i";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }

    // Update gown stock
    public function updateGownStock($id, $stock) {
        $conn = $this->db->getConnection();
        
        // Update stock quantity
        $stmt = $conn->prepare("UPDATE gowns SET stock = ? WHERE id = ?");
        $stmt->bind_param("ii", $stock, $id);
        
        return $stmt->execute();
    }
    
    // Add stock to existing gown
    public function addStock($id, $quantity) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("UPDATE gowns SET stock = stock + ? WHERE id = ?");
        $stmt->bind_param("ii", $quantity, $id);
        
        return $stmt->execute();
    }
    
    // Remove stock from existing gown
    public function removeStock($id, $quantity) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("UPDATE gowns SET stock = GREATEST(0, stock - ?) WHERE id = ?");
        $stmt->bind_param("ii", $quantity, $id);
        
        return $stmt->execute();
    }

    // Delete gown
    public function deleteGown($id) {
        $conn = $this->db->getConnection();
        
        // Get gown info first to delete files
        $gown = $this->getGownById($id);
        
        if ($gown) {
            // Delete image files if exist
            for ($imgIdx = 1; $imgIdx <= 4; $imgIdx++) {
                $colKey = $imgIdx === 1 ? 'image_path' : ('image_path' . $imgIdx);
                if (!empty($gown[$colKey]) && file_exists('../' . $gown[$colKey])) {
                    unlink('../' . $gown[$colKey]);
                }
            }
            
            // Delete model file if exists
            if ($gown['model_path'] && file_exists('../' . $gown['model_path'])) {
                unlink('../' . $gown['model_path']);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM gowns WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        return $stmt->execute();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $gownManager = new GownManager();
    
    if ($_POST['action'] == 'add_gown') {
        $name = $_POST['gownName'];
        $category = $_POST['gownCategory'];
        $price = $_POST['gownPrice'];
        $size = $_POST['gownSize'];
        $description = $_POST['gownDescription'];
        $discount = isset($_POST['gownDiscount']) ? $_POST['gownDiscount'] : 0;
        $stock = isset($_POST['gownStock']) ? $_POST['gownStock'] : 0;
        
        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['gownImage']) && $_FILES['gownImage']['error'] == 0) {
            $uploadDir = '../images/gowns/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $imageName = time() . '_' . basename($_FILES['gownImage']['name']);
            $imagePath = 'images/gowns/' . $imageName;
            move_uploaded_file($_FILES['gownImage']['tmp_name'], $uploadDir . $imageName);
        }
        
        // Handle 3D model upload
        $modelPath = null;
        if (isset($_FILES['gown3DModel']) && $_FILES['gown3DModel']['error'] == 0) {
            $uploadDir = '../models/gowns/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $modelName = time() . '_' . basename($_FILES['gown3DModel']['name']);
            $modelPath = 'models/gowns/' . $modelName;
            move_uploaded_file($_FILES['gown3DModel']['tmp_name'], $uploadDir . $modelName);
        }
        
        if ($gownManager->addGown($name, $category, $price, $size, $description, $imagePath, $modelPath, $discount, $stock)) {
            $_SESSION['success_message'] = 'Gown added successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to add gown.';
        }
        
        header('Location: gowns.php');
        exit();
    }
    
    if ($_POST['action'] == 'upload_image') {
        $gownId = $_POST['gown_id'];
        $imageNumber = $_POST['image_number'];
        
        // Handle file upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $imageName = time() . '_' . $imageNumber . '_' . basename($file['name']);
            $imagePath = 'uploads/' . $imageName;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], '../' . $imagePath)) {
                // Update the appropriate image column in database
                $database = new Database();
                $conn = $database->getConnection();
                $column = 'image_path' . ($imageNumber > 1 ? $imageNumber : '');
                
                $stmt = $conn->prepare("UPDATE gowns SET $column = ? WHERE id = ?");
                $stmt->bind_param("si", $imagePath, $gownId);
                
                if ($stmt->execute()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Image uploaded successfully']);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Failed to update database']);
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        }
        exit();
    }
    
    if ($_POST['action'] == 'remove_image') {
        $gownId = intval($_POST['gown_id'] ?? 0);
        $imageNumber = intval($_POST['image_number'] ?? 1);
        if ($gownId > 0) {
            $database = new Database();
            $conn = $database->getConnection();
            $column = 'image_path' . ($imageNumber > 1 ? $imageNumber : '');
            $stmt = $conn->prepare("UPDATE gowns SET $column = NULL WHERE id = ?");
            $stmt->bind_param("i", $gownId);
            $stmt->execute();
            $stmt->close();
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit();
        }
    }
    
    if ($_POST['action'] == 'edit_gown') {
        $id = $_POST['gown_id'];
        $name = $_POST['gownName'];
        $category = $_POST['gownCategory'];
        $price = $_POST['gownPrice'];
        $purchase_price = $_POST['gownPurchasePrice'];
        $color = $_POST['gownColor'];
        $description = $_POST['gownDescription'];
        $discount = isset($_POST['gownDiscount']) ? $_POST['gownDiscount'] : 0;
        $stock = isset($_POST['gownStock']) ? $_POST['gownStock'] : null;

        // Handle dynamic size ranges (size1 to size10)
        $sizeRanges = [];
        for ($i = 1; $i <= 10; $i++) {
            $minKey = "size{$i}Min";
            $maxKey = "size{$i}Max";
            $sizeRanges[$i]['min'] = (isset($_POST[$minKey]) && $_POST[$minKey] !== '') ? intval($_POST[$minKey]) : null;
            $sizeRanges[$i]['max'] = (isset($_POST[$maxKey]) && $_POST[$maxKey] !== '') ? intval($_POST[$maxKey]) : null;
        }
        
        // Check if model should be removed
        $removeModel = isset($_POST['remove_model']) && $_POST['remove_model'] == '1';
        
        // Get current gown data
        $currentGown = $gownManager->getGownById($id);
        
        // Handle image upload for all 4 image slots
        $imagePath = $currentGown['image_path'] ?? null;
        $imagePath2 = $currentGown['image_path2'] ?? null;
        $imagePath3 = $currentGown['image_path3'] ?? null;
        $imagePath4 = $currentGown['image_path4'] ?? null;
        
        $uploadDir = '../images/gowns/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Slot 1: check gownImage1 or editGownImage
        $file1 = (!empty($_FILES['gownImage1']) && $_FILES['gownImage1']['error'] == 0) ? $_FILES['gownImage1'] : ((!empty($_FILES['editGownImage']) && $_FILES['editGownImage']['error'] == 0) ? $_FILES['editGownImage'] : null);
        if ($file1) {
            if ($imagePath && file_exists('../' . $imagePath)) {
                unlink('../' . $imagePath);
            }
            $imageName = time() . '_1_' . basename($file1['name']);
            $imagePath = 'images/gowns/' . $imageName;
            move_uploaded_file($file1['tmp_name'], $uploadDir . $imageName);
        }

        // Slot 2: gownImage2
        if (isset($_FILES['gownImage2']) && $_FILES['gownImage2']['error'] == 0) {
            if ($imagePath2 && file_exists('../' . $imagePath2)) {
                unlink('../' . $imagePath2);
            }
            $imageName = time() . '_2_' . basename($_FILES['gownImage2']['name']);
            $imagePath2 = 'images/gowns/' . $imageName;
            move_uploaded_file($_FILES['gownImage2']['tmp_name'], $uploadDir . $imageName);
        }

        // Slot 3: gownImage3
        if (isset($_FILES['gownImage3']) && $_FILES['gownImage3']['error'] == 0) {
            if ($imagePath3 && file_exists('../' . $imagePath3)) {
                unlink('../' . $imagePath3);
            }
            $imageName = time() . '_3_' . basename($_FILES['gownImage3']['name']);
            $imagePath3 = 'images/gowns/' . $imageName;
            move_uploaded_file($_FILES['gownImage3']['tmp_name'], $uploadDir . $imageName);
        }

        // Slot 4: gownImage4
        if (isset($_FILES['gownImage4']) && $_FILES['gownImage4']['error'] == 0) {
            if ($imagePath4 && file_exists('../' . $imagePath4)) {
                unlink('../' . $imagePath4);
            }
            $imageName = time() . '_4_' . basename($_FILES['gownImage4']['name']);
            $imagePath4 = 'images/gowns/' . $imageName;
            move_uploaded_file($_FILES['gownImage4']['tmp_name'], $uploadDir . $imageName);
        }
        
        // Handle 3D model upload or removal
        $modelPath = $currentGown['model_path']; // Keep existing by default
        
        // If a new 3D model file was uploaded, process it (takes priority over removal flag)
        if (isset($_FILES['gown3DModel']) && $_FILES['gown3DModel']['error'] != UPLOAD_ERR_NO_FILE) {
            error_log("3D Model upload detected with error code: " . $_FILES['gown3DModel']['error']);
            
            if ($_FILES['gown3DModel']['error'] == UPLOAD_ERR_OK) {
                error_log("Processing 3D model upload: " . $_FILES['gown3DModel']['name']);
                
                $uploadDir = '../models/gowns/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Delete old model file
                if ($currentGown['model_path'] && file_exists('../' . $currentGown['model_path'])) {
                    unlink('../' . $currentGown['model_path']);
                    error_log("Deleted old model: " . $currentGown['model_path']);
                }
                
                $ext = pathinfo($_FILES['gown3DModel']['name'], PATHINFO_EXTENSION);
                $cleanBaseName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($_FILES['gown3DModel']['name'], PATHINFO_FILENAME));
                $modelName = time() . '_' . $cleanBaseName . '.' . ($ext ?: 'glb');
                $modelPath = 'models/gowns/' . $modelName;
                
                if (move_uploaded_file($_FILES['gown3DModel']['tmp_name'], $uploadDir . $modelName)) {
                    error_log("3D Model uploaded successfully: " . $modelPath);
                } else {
                    error_log("Failed to move uploaded 3D model from tmp_name: " . $_FILES['gown3DModel']['tmp_name']);
                    $_SESSION['error_message'] = "Failed to save the uploaded 3D model to the models folder. Please check directory permissions.";
                    $modelPath = $currentGown['model_path'];
                }
            } else {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'The uploaded 3D model file exceeds the upload_max_filesize limit in php.ini.',
                    UPLOAD_ERR_FORM_SIZE => 'The uploaded 3D model exceeds the MAX_FILE_SIZE limit.',
                    UPLOAD_ERR_PARTIAL => 'The 3D model file was only partially uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write 3D model file to disk.',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
                ];
                $errorDesc = $uploadErrors[$_FILES['gown3DModel']['error']] ?? ('Upload error code: ' . $_FILES['gown3DModel']['error']);
                error_log("3D Model upload error: " . $errorDesc);
                $_SESSION['error_message'] = "3D model upload failed: " . $errorDesc;
            }
        } elseif ($removeModel) {
            // Remove 3D model if removal was requested and no new file was uploaded
            if ($currentGown['model_path'] && file_exists('../' . $currentGown['model_path'])) {
                unlink('../' . $currentGown['model_path']);
            }
            $modelPath = null; // Set to null in database
        }
        
        error_log("Final model path before database update: " . ($modelPath ?? 'null'));

        if ($gownManager->updateGown($id, $name, $category, $price, $discount, $color, $description, $imagePath, $imagePath2, $imagePath3, $imagePath4, $modelPath, $stock, $purchase_price, $sizeRanges)) {
            $_SESSION['success_message'] = 'Gown updated successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to update gown.';
        }
        
        header('Location: gowns.php');
        exit();
    }
    
    if ($_POST['action'] == 'delete_gown') {
        $id = $_POST['gown_id'];
        
        if ($gownManager->deleteGown($id)) {
            $_SESSION['success_message'] = 'Gown deleted successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to delete gown.';
        }
        
        header('Location: gowns.php');
        exit();
    }
}

// Get all gowns
$gownManager = new GownManager();
$gowns = $gownManager->getAllGowns();

// Fetch system low stock threshold
$lowStockThreshold = 2;
try {
    $dbStock = new Database();
    $connStock = $dbStock->getConnection();
    if ($connStock) {
        $stRes = $connStock->query("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold' LIMIT 1");
        if ($stRes && $stRow = $stRes->fetch_assoc()) {
            $lowStockThreshold = max(1, intval($stRow['setting_value']));
        }
    }
} catch (Exception $e) {}

// Handle AJAX request for gown data
if (isset($_GET['action']) && $_GET['action'] == 'get_gown' && isset($_GET['id'])) {
    $gownId = $_GET['id'];
    $gown = $gownManager->getGownById($gownId);
    
    if ($gown) {
        header('Content-Type: application/json');
        echo json_encode($gown);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Gown not found']);
    }
    exit();
}
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Gowns</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-modern.css">
    <link rel="stylesheet" href="../css/admin_mobile_css.css">
    <style>
        /* ── Add / Edit Product: centered full-page modal (show only modal on screen) ── */
        body.product-modal-open,
        body:has(#addNewProductModal.slide-panel-open),
        body:has(#editModal.slide-panel-open) {
            overflow: hidden !important;
        }

        /* 1. HIDE SIDEBAR COMPLETELY WHEN MODAL IS OPEN */
        body.product-modal-open .sidebar,
        body:has(#addNewProductModal.slide-panel-open) .sidebar,
        body:has(#editModal.slide-panel-open) .sidebar {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
            transform: translateX(-100%) !important;
        }

        /* 2. HIDE BUTTONS IN TOP / SEARCH & FILTER TOOLBAR */
        body.product-modal-open .search-filter-section-top,
        body:has(#addNewProductModal.slide-panel-open) .search-filter-section-top,
        body:has(#editModal.slide-panel-open) .search-filter-section-top,
        body.product-modal-open .add-new-product-btn,
        body:has(#addNewProductModal.slide-panel-open) .add-new-product-btn,
        body:has(#editModal.slide-panel-open) .add-new-product-btn,
        body.product-modal-open #mobileMenuToggle,
        body:has(#addNewProductModal.slide-panel-open) #mobileMenuToggle,
        body:has(#editModal.slide-panel-open) #mobileMenuToggle,
        body.product-modal-open #mobilePageTitle,
        body:has(#addNewProductModal.slide-panel-open) #mobilePageTitle,
        body:has(#editModal.slide-panel-open) #mobilePageTitle,
        body.product-modal-open #sidebarOverlay,
        body:has(#addNewProductModal.slide-panel-open) #sidebarOverlay,
        body:has(#editModal.slide-panel-open) #sidebarOverlay {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        /* 3. MODAL FULL SCREEN CONTAINER (SHOW ONLY MODAL ON SCREEN) */
        html body #addNewProductModal.slide-panel,
        html body #editModal.slide-panel {
            display: none !important;
            position: fixed !important;
            inset: 0 !important;
            top: 0 !important;
            right: 0 !important;
            left: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            margin: 0 !important;
            padding: 20px !important;
            box-sizing: border-box !important;
            background: rgba(10, 10, 10, 0.96) !important;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            transition: none !important;
            transform: none !important;
            overflow: hidden !important;
            z-index: 999999 !important;
            align-items: center !important;
            justify-content: center !important;
        }

        html body #addNewProductModal.slide-panel.slide-panel-open,
        html body #editModal.slide-panel.slide-panel-open {
            display: flex !important;
            animation: productModalFade 0.18s ease-out;
        }

        #addNewProductModal .slide-panel-content,
        #editModal .slide-panel-content {
            width: 100% !important;
            max-width: 1350px !important;
            height: 100% !important;
            max-height: calc(100vh - 40px) !important;
            display: flex !important;
            flex-direction: column !important;
            background: #111111 !important;
            border: 1px solid rgba(201, 170, 46, 0.55) !important;
            border-radius: 16px !important;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.95), 0 0 25px rgba(201, 170, 46, 0.2) !important;
            overflow: hidden !important;
        }

        #addNewProductModal .slide-panel-header,
        #editModal .slide-panel-header {
            flex-shrink: 0 !important;
            background: #161616 !important;
            border-radius: 16px 16px 0 0 !important;
            border: none !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            padding: 18px 24px !important;
        }

        #addNewProductModal .slide-panel-body,
        #editModal .slide-panel-body {
            flex: 1 1 auto !important;
            overflow-y: auto !important;
            margin-bottom: 0 !important;
            padding: 24px !important;
        }

        @keyframes productModalFade {
            from { opacity: 0; transform: scale(0.985); }
            to   { opacity: 1; transform: scale(1); }
        }

        @media (max-width: 768px) {
            html body #addNewProductModal.slide-panel,
            html body #editModal.slide-panel {
                padding: 0 !important;
            }
            #addNewProductModal .slide-panel-content,
            #editModal .slide-panel-content {
                max-width: 100% !important;
                max-height: 100vh !important;
                border-radius: 0 !important;
                border: none !important;
            }
            #addNewProductModal .slide-panel-header,
            #editModal .slide-panel-header {
                border-radius: 0 !important;
            }
        }

        /* ─── Top Toolbar: Search, Category, Add New Product, Categories (Equal Width/Height & Inline Spacing) ─── */
        .search-filter-section-top {
            position: fixed !important;
            top: 10px !important;
            right: 20px !important;
            left: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            z-index: 100 !important;
            height: auto !important;
            display: flex !important;
            align-items: center !important;
        }

        .search-filter-section-top .search-filter-row {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
            gap: 12px !important;
            width: auto !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .search-filter-section-top .form-group {
            margin: 0 !important;
            padding: 0 !important;
            flex: 0 0 175px !important;
            width: 175px !important;
            max-width: 175px !important;
            box-sizing: border-box !important;
        }

        .search-filter-section-top input[type="text"],
        .search-filter-section-top select,
        .search-filter-section-top .add-new-product-btn {
            width: 175px !important;
            max-width: 175px !important;
            height: 40px !important;
            min-height: 40px !important;
            max-height: 40px !important;
            line-height: 38px !important;
            box-sizing: border-box !important;
            border: 1px solid #7c6300 !important;
            border-radius: 8px !important;
            background: #000000 !important;
            color: #ffffff !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            padding: 0 14px !important;
            box-shadow: inset 0 0 8px rgba(212, 175, 55, 0.4) !important;
            transition: all 0.3s ease !important;
            white-space: nowrap !important;
            text-overflow: ellipsis !important;
            overflow: hidden !important;
            margin: 0 !important;
        }

        .search-filter-section-top input[type="text"] {
            text-align: left !important;
            cursor: text !important;
        }

        .search-filter-section-top select {
            text-align: left !important;
            cursor: pointer !important;
            padding-right: 24px !important;
        }

        .search-filter-section-top .add-new-product-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center !important;
            cursor: pointer !important;
        }

        .search-filter-section-top input[type="text"]:hover,
        .search-filter-section-top select:hover,
        .search-filter-section-top .add-new-product-btn:hover {
            background: #1a1a1a !important;
            box-shadow: inset 0 0 12px rgba(212, 175, 55, 0.6) !important;
            transform: translateY(-1px);
        }

        .search-filter-section-top input[type="text"]:focus,
        .search-filter-section-top select:focus,
        .search-filter-section-top .add-new-product-btn:focus {
            outline: none !important;
            border-color: #d4af37 !important;
            background: #1a1a1a !important;
            box-shadow: inset 0 0 12px rgba(212, 175, 55, 0.6) !important;
        }

        @media (max-width: 1024px) {
            .search-filter-section-top {
                right: 12px !important;
            }
            .search-filter-section-top .search-filter-row {
                gap: 8px !important;
            }
            .search-filter-section-top .form-group,
            .search-filter-section-top input[type="text"],
            .search-filter-section-top select,
            .search-filter-section-top .add-new-product-btn {
                flex: 0 0 150px !important;
                width: 150px !important;
                max-width: 150px !important;
                font-size: 13px !important;
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
        }

        @media (max-width: 768px) {
            .search-filter-section-top {
                position: static !important;
                width: 100% !important;
                padding: 10px 14px !important;
                margin-top: 62px !important;
            }
            .search-filter-section-top .search-filter-row {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 8px !important;
                width: 100% !important;
            }
            .search-filter-section-top .form-group {
                flex: 1 1 auto !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .search-filter-section-top input[type="text"],
            .search-filter-section-top select,
            .search-filter-section-top .add-new-product-btn {
                width: 100% !important;
                max-width: 100% !important;
                height: 40px !important;
            }
        }

        @media (max-width: 480px) {
            .search-filter-section-top .search-filter-row {
                grid-template-columns: 1fr !important;
            }
        }

        /* ─── Big Categories Modal Styles ─── */
        .categories-modal-overlay {
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
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            box-sizing: border-box;
            animation: catModalFadeIn 0.22s ease-out;
        }

        @keyframes catModalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .categories-modal-container {
            width: 95vw;
            max-width: 1400px;
            height: 92vh;
            max-height: 950px;
            background: #14151a;
            border: 1.5px solid #7c6300;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.9), 0 0 24px rgba(212, 175, 55, 0.25);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
            animation: catModalScaleIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes catModalScaleIn {
            from { transform: scale(0.96); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .categories-modal-header {
            padding: 14px 22px;
            background: #1a1a1f;
            border-bottom: 1px solid rgba(212, 175, 55, 0.3);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .categories-modal-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.15rem;
            font-weight: 700;
            color: #ffd700;
            letter-spacing: 0.5px;
        }

        .categories-modal-close-btn {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ccc;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            transition: all 0.2s ease;
        }

        .categories-modal-close-btn:hover {
            color: #ffd700;
            border-color: #ffd700;
            background: rgba(212, 175, 55, 0.15);
            transform: rotate(90deg);
        }

        .categories-modal-body {
            flex: 1;
            width: 100%;
            height: 100%;
            min-height: 0;
            overflow: hidden;
            position: relative;
            background: #14151a;
        }

        @media (max-width: 768px) {
            .categories-modal-overlay {
                padding: 0;
            }
            .categories-modal-container {
                width: 100vw;
                height: 100dvh;
                max-width: 100vw;
                max-height: 100dvh;
                border-radius: 0;
                border: none;
            }
            .categories-modal-header {
                padding: 12px 16px;
            }
        }
    </style>
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.1.1/model-viewer.min.js"></script>
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

    // Load categories from categories table
    function loadCategories() {
        fetch('categories.php?action=getAllCategories')
            .then(response => response.json())
            .then(categories => {
                populateCategoryDropdowns(categories);
            })
            .catch(error => {
                console.error('Error loading categories:', error);
            });
    }

    // Populate all category dropdowns with loaded categories
    function populateCategoryDropdowns(categories) {
        const dropdowns = ['filterCategory', 'gownCategory', 'newCategory', 'editCategory'];

        dropdowns.forEach(dropdownId => {
            const dropdown = document.getElementById(dropdownId);
            if (dropdown) {
                // Save current selection
                const currentValue = dropdown.value;

                // Clear existing options except the first one
                dropdown.innerHTML = dropdown.options[0].outerHTML;

                // Add categories from categories table
                categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.category_name;
                    option.textContent = category.category_name;
                    dropdown.appendChild(option);
                });

                // Restore selection if it still exists
                if (currentValue) {
                    dropdown.value = currentValue;
                }
            }
        });
    }

    // Load size labels for a selected category and dynamically generate size range inputs
    function loadSizeLabelsForCategory(categorySelectId, containerId) {
        const categorySelect = document.getElementById(categorySelectId);
        const container = document.getElementById(containerId);
        if (!container) return;
        const categoryName = categorySelect ? categorySelect.value : '';

        if (!categoryName) {
            container.innerHTML = '<p style="color: #888; font-size: 13px; margin: 5px 0;">Please select a category to see size ranges.</p>';
            return;
        }

        // Get all categories to find the selected one
        fetch('categories.php?action=getAllCategories')
            .then(response => response.json())
            .then(categories => {
                const category = categories.find(c => c.category_name && c.category_name.trim().toLowerCase() === categoryName.trim().toLowerCase());
                if (category) {
                    // Get all non-empty size labels
                    const sizeLabels = [];
                    for (let i = 1; i <= 10; i++) {
                        const label = category[`size_label_${i}`];
                        if (label && label.trim() !== '') {
                            sizeLabels.push({ index: i, label: label.trim() });
                        }
                    }

                    // Generate size range inputs based on labels
                    if (sizeLabels.length > 0) {
                        container.innerHTML = sizeLabels.map(item => `
                            <div class="size-range-group">
                                <label>${item.label} Range</label>
                                <div class="range-inputs">
                                    <input type="number" id="size${item.index}Min" name="size${item.index}Min" placeholder="Min" min="0">
                                    <span>to</span>
                                    <input type="number" id="size${item.index}Max" name="size${item.index}Max" placeholder="Max" min="0">
                                </div>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<p style="color: #888; font-size: 13px; margin: 5px 0;">No size labels defined for this category.</p>';
                    }
                } else {
                    container.innerHTML = '<p style="color: #888; font-size: 13px; margin: 5px 0;">Category not found.</p>';
                }
            })
            .catch(error => {
                console.error('Error loading category size labels:', error);
                container.innerHTML = '<p style="color: #e74c3c; font-size: 13px; margin: 5px 0;">Error loading size labels.</p>';
            });
    }

    // Load size labels for a selected category and populate with existing product data
    function loadSizeLabelsWithValues(categorySelectId, containerId, productData) {
        const categorySelect = document.getElementById(categorySelectId);
        const container = document.getElementById(containerId);
        if (!container) return;
        const categoryName = categorySelect ? categorySelect.value : (productData ? productData.category : '');

        if (!categoryName) {
            container.innerHTML = '<p style="color: #888; font-size: 13px; margin: 5px 0;">Please select a category to see size ranges.</p>';
            return;
        }

        // Get all categories to find the selected one
        fetch('categories.php?action=getAllCategories')
            .then(response => response.json())
            .then(categories => {
                const category = categories.find(c => c.category_name && c.category_name.trim().toLowerCase() === categoryName.trim().toLowerCase());
                if (category) {
                    // Get all non-empty size labels
                    const sizeLabels = [];
                    for (let i = 1; i <= 10; i++) {
                        const label = category[`size_label_${i}`];
                        if (label && label.trim() !== '') {
                            sizeLabels.push({ index: i, label: label.trim() });
                        }
                    }

                    // Generate size range inputs based on labels and populate with existing values
                    if (sizeLabels.length > 0) {
                        container.innerHTML = sizeLabels.map(item => {
                            const minVal = (productData && productData[`size_${item.index}_min`] !== null && productData[`size_${item.index}_min`] !== undefined) ? productData[`size_${item.index}_min`] : '';
                            const maxVal = (productData && productData[`size_${item.index}_max`] !== null && productData[`size_${item.index}_max`] !== undefined) ? productData[`size_${item.index}_max`] : '';
                            return `
                            <div class="size-range-group">
                                <label>${item.label} Range</label>
                                <div class="range-inputs">
                                    <input type="number" id="size${item.index}Min" name="size${item.index}Min" placeholder="Min" min="0" value="${minVal}">
                                    <span>to</span>
                                    <input type="number" id="size${item.index}Max" name="size${item.index}Max" placeholder="Max" min="0" value="${maxVal}">
                                </div>
                            </div>
                        `;
                        }).join('');
                    } else {
                        container.innerHTML = '<p style="color: #888; font-size: 13px; margin: 5px 0;">No size labels defined for this category.</p>';
                    }
                } else {
                    container.innerHTML = '<p style="color: #888; font-size: 13px; margin: 5px 0;">Category not found.</p>';
                }
            })
            .catch(error => {
                console.error('Error loading category size labels:', error);
                container.innerHTML = '<p style="color: #e74c3c; font-size: 13px; margin: 5px 0;">Error loading size labels.</p>';
            });
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

        // Load categories from categories table
        loadCategories();
    });
    </script>
</head>
<body class="gowns-page">
    <?php include 'sidebar.php'; ?>

    <!-- Search and Filter Section - Outside Main Container -->
    <div class="search-filter-section-top">
        <div class="search-filter-row">
            <div class="form-group form-group-search">
                <input type="text" id="searchName" placeholder="Search..." onkeyup="filterGowns()">
            </div>
            <div class="form-group form-group-category">
                <select id="filterCategory" onchange="filterGowns()">
                    <option value="">Category</option>
                    <option value="Men">Men</option>
                    <option value="Upper Wear (Men)">Upper Wear (Men)</option>
                    <option value="Lower Wear (Men)">Lower Wear (Men)</option>
                    <option value="Women">Women</option>
                    <option value="Children">Children</option>
                </select>
            </div>
            <div class="form-group form-group-add">
                <button type="button" class="add-new-product-btn" onclick="openAddNewProductPanel()">Add New Product</button>
            </div>
            <div class="form-group form-group-categories">
                <button type="button" class="add-new-product-btn" onclick="openCategoriesModal()">Categories</button>
            </div>
        </div>
    </div>

    <div class="shell">
        <!-- Gowns Grid -->
            <div id="addGownModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Add New Product</h2>
                        <button type="button" class="close-panel-btn" onclick="closeAddGownModal()" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="modal-body">
                        <form id="addGownForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_gown">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="gownName">Gown Name</label>
                                    <input type="text" id="gownName" name="gownName" required>
                                </div>
                                <div class="form-group">
                                    <label for="gownCategory">Category</label>
                                    <select id="gownCategory" name="gownCategory" required>
                                        <option value="">Select Category</option>
                                        <option value="Men">Men</option>
                                        <option value="Upper Wear (Men)">Upper Wear (Men)</option>
                                        <option value="Lower Wear (Men)">Lower Wear (Men)</option>
                                        <option value="Women">Women</option>
                                        <option value="Children">Children</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="gownPrice">Price (₱)</label>
                                    <input type="number" id="gownPrice" name="gownPrice" min="0" step="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label for="gownPurchasePrice">Purchase Price (₱)</label>
                                    <input type="number" id="gownPurchasePrice" name="gownPurchasePrice" min="0" step="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label for="gownDiscount">Discount (%)</label>
                                    <input type="number" id="gownDiscount" name="gownDiscount" min="0" max="100" step="0.01" value="0">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="gownStock">Stock Quantity</label>
                                    <input type="number" id="gownStock" name="gownStock" min="0" value="0" required>
                                </div>
                                
                            </div>
                            
                            <div class="form-group">
                                <label for="gownDescription">Description</label>
                                <textarea id="gownDescription" name="gownDescription" rows="3"></textarea>
                            </div>

                            <div class="form-group">
                                <label>Size Ranges (cm)</label>
                                <div class="size-ranges-container">
                                    <div class="size-range-group">
                                        <label>Size 1 Range</label>
                                        <div class="range-inputs">
                                            <input type="number" id="size1Min" name="size1Min" placeholder="Min" min="0" value="70" required>
                                            <span>to</span>
                                            <input type="number" id="size1Max" name="size1Max" placeholder="Max" min="0" value="120" required>
                                        </div>
                                    </div>
                                    <div class="size-range-group">
                                        <label>Size 2 Range</label>
                                        <div class="range-inputs">
                                            <input type="number" id="size2Min" name="size2Min" placeholder="Min" min="0" value="60" required>
                                            <span>to</span>
                                            <input type="number" id="size2Max" name="size2Max" placeholder="Max" min="0" value="100" required>
                                        </div>
                                    </div>
                                    <div class="size-range-group">
                                        <label>Size 3 Range</label>
                                        <div class="range-inputs">
                                            <input type="number" id="size3Min" name="size3Min" placeholder="Min" min="0" value="80" required>
                                            <span>to</span>
                                            <input type="number" id="size3Max" name="size3Max" placeholder="Max" min="0" value="130" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="gownImage">Gown Image</label>
                                <input type="file" id="gownImage" name="gownImage" accept="image/*">
                            </div>
                            
                            <div class="form-group">
                                <label for="gown3DModel">3D Model (.glb file)</label>
                                <div class="file-drop-area" id="dropArea">
                                    <span class="drop-message">Drag & drop your .glb file here or click to browse</span>
                                    <input type="file" id="gown3DModel" name="gown3DModel" accept=".glb" hidden>
                                </div>
                                <div id="3dPreview" class="model-preview" style="display: none;">
                                        <model-viewer 
                                            id="modelViewer"
                                            src=""
                                            alt="3D Gown Model"
                                            auto-rotate
                                            camera-orbit="auto 90deg auto"
                                            camera-controls
                                            disable-pan
                                            disable-zoom
                                            min-camera-orbit="auto 90deg auto"
                                            max-camera-orbit="auto 90deg auto"
                                            style="width: 100%; height: 300px; border-radius: 8px;">
                                    </model-viewer>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn-submit">Add Product</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal Backdrop Overlay -->
            <div id="modalBackdrop" class="modal-backdrop"></div>

            <!-- Add New Product Slide-in Panel -->
            <div id="addNewProductModal" class="slide-panel">
                <div class="slide-panel-content">
                    <div class="slide-panel-header">
                        <h2>Add New Product</h2>
                        <button type="button" class="close-panel-btn" onclick="closeAddNewProductModal()" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="slide-panel-body">
                        <form id="addNewProductForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_gown">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="newProductName">Product</label>
                                    <input type="text" id="newProductName" name="gownName" required>
                                </div>
                                <div class="form-group">
                                    <label for="newCategory">Category</label>
                                    <select id="newCategory" name="gownCategory" required onchange="loadSizeLabelsForCategory('newCategory', 'newSizeRanges')">
                                        <option value="">Select Category</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="newPrice">Reservation Price</label>
                                    <input type="number" id="newPrice" name="price" min="0" step="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label for="newPurchasePrice">Selling Price</label>
                                    <input type="number" id="newPurchasePrice" name="purchase_price" min="0" step="0.01" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="newStock">Stock </label>
                                    <input type="number" id="newStock" name="stock" min="0" required>
                                </div>
                                <div class="form-group">
                                    <label for="newColor">Color</label>
                                    <input type="text" id="newColor" name="color" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Size Ranges (cm)</label>
                                <div class="size-ranges-container" id="newSizeRanges">
                                    <!-- Size ranges will be dynamically generated based on category -->
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="newDescription">Description</label>
                                <textarea id="newDescription" name="description" rows="2" required></textarea>
                            </div>

                            <div class="form-row" style="align-items: flex-start; gap: 20px;">
                                <div class="form-group" style="flex: 1.2;">
                                    <label>
                                        <i class="fas fa-images"></i> Product Images (Select up to 4 images)
                                    </label>
                                    <small style="display: block; color: #888; margin-bottom: 8px;">Click any box below to upload image</small>
                                    
                                    <div class="new-product-image-slots" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 6px;">
                                        <!-- Slot 1 -->
                                        <div class="new-image-slot" id="slotContainer1" tabindex="0" onclick="triggerNewImageSlot(1)" style="height: 120px; border: 1.5px dashed rgba(124, 99, 0, 0.45); border-radius: 10px; background: #181818; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; position: relative; overflow: hidden; transition: all 0.25s ease;">
                                            <input type="file" id="newImageSlot1" name="image1" accept="image/*" style="display: none;" onchange="handleNewImageSlotChange(this, 1)">
                                            <div class="slot-placeholder" id="slotPlaceholder1" style="display: flex; flex-direction: column; align-items: center; gap: 4px; text-align: center; padding: 6px;">
                                                <i class="fas fa-camera" style="font-size: 20px; color: #ffd700;"></i>
                                                <span style="font-size: 11px; font-weight: 700; color: #ffd700;">Image 1 (Main)</span>
                                                <span style="font-size: 10px; color: #888;">+ Click to add</span>
                                            </div>
                                            <img id="slotImgPreview1" src="" alt="Slot 1 Preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                            <button type="button" class="slot-remove-btn" id="slotRemoveBtn1" onclick="removeNewImageSlot(event, 1)" style="display: none; position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%; background: rgba(220, 53, 69, 0.9); color: white; border: none; font-size: 14px; font-weight: bold; line-height: 1; cursor: pointer; align-items: center; justify-content: center; z-index: 5;" title="Remove image">×</button>
                                        </div>

                                        <!-- Slot 2 -->
                                        <div class="new-image-slot" id="slotContainer2" tabindex="0" onclick="triggerNewImageSlot(2)" style="height: 120px; border: 1.5px dashed rgba(124, 99, 0, 0.45); border-radius: 10px; background: #181818; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; position: relative; overflow: hidden; transition: all 0.25s ease;">
                                            <input type="file" id="newImageSlot2" name="image2" accept="image/*" style="display: none;" onchange="handleNewImageSlotChange(this, 2)">
                                            <div class="slot-placeholder" id="slotPlaceholder2" style="display: flex; flex-direction: column; align-items: center; gap: 4px; text-align: center; padding: 6px;">
                                                <i class="fas fa-camera" style="font-size: 20px; color: #ffd700;"></i>
                                                <span style="font-size: 11px; font-weight: 700; color: #ffd700;">Image 2</span>
                                                <span style="font-size: 10px; color: #888;">+ Click to add</span>
                                            </div>
                                            <img id="slotImgPreview2" src="" alt="Slot 2 Preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                            <button type="button" class="slot-remove-btn" id="slotRemoveBtn2" onclick="removeNewImageSlot(event, 2)" style="display: none; position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%; background: rgba(220, 53, 69, 0.9); color: white; border: none; font-size: 14px; font-weight: bold; line-height: 1; cursor: pointer; align-items: center; justify-content: center; z-index: 5;" title="Remove image">×</button>
                                        </div>

                                        <!-- Slot 3 -->
                                        <div class="new-image-slot" id="slotContainer3" tabindex="0" onclick="triggerNewImageSlot(3)" style="height: 120px; border: 1.5px dashed rgba(124, 99, 0, 0.45); border-radius: 10px; background: #181818; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; position: relative; overflow: hidden; transition: all 0.25s ease;">
                                            <input type="file" id="newImageSlot3" name="image3" accept="image/*" style="display: none;" onchange="handleNewImageSlotChange(this, 3)">
                                            <div class="slot-placeholder" id="slotPlaceholder3" style="display: flex; flex-direction: column; align-items: center; gap: 4px; text-align: center; padding: 6px;">
                                                <i class="fas fa-camera" style="font-size: 20px; color: #ffd700;"></i>
                                                <span style="font-size: 11px; font-weight: 700; color: #ffd700;">Image 3</span>
                                                <span style="font-size: 10px; color: #888;">+ Click to add</span>
                                            </div>
                                            <img id="slotImgPreview3" src="" alt="Slot 3 Preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                            <button type="button" class="slot-remove-btn" id="slotRemoveBtn3" onclick="removeNewImageSlot(event, 3)" style="display: none; position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%; background: rgba(220, 53, 69, 0.9); color: white; border: none; font-size: 14px; font-weight: bold; line-height: 1; cursor: pointer; align-items: center; justify-content: center; z-index: 5;" title="Remove image">×</button>
                                        </div>

                                        <!-- Slot 4 -->
                                        <div class="new-image-slot" id="slotContainer4" tabindex="0" onclick="triggerNewImageSlot(4)" style="height: 120px; border: 1.5px dashed rgba(124, 99, 0, 0.45); border-radius: 10px; background: #181818; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; position: relative; overflow: hidden; transition: all 0.25s ease;">
                                            <input type="file" id="newImageSlot4" name="image4" accept="image/*" style="display: none;" onchange="handleNewImageSlotChange(this, 4)">
                                            <div class="slot-placeholder" id="slotPlaceholder4" style="display: flex; flex-direction: column; align-items: center; gap: 4px; text-align: center; padding: 6px;">
                                                <i class="fas fa-camera" style="font-size: 20px; color: #ffd700;"></i>
                                                <span style="font-size: 11px; font-weight: 700; color: #ffd700;">Image 4</span>
                                                <span style="font-size: 10px; color: #888;">+ Click to add</span>
                                            </div>
                                            <img id="slotImgPreview4" src="" alt="Slot 4 Preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                            <button type="button" class="slot-remove-btn" id="slotRemoveBtn4" onclick="removeNewImageSlot(event, 4)" style="display: none; position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%; background: rgba(220, 53, 69, 0.9); color: white; border: none; font-size: 14px; font-weight: bold; line-height: 1; cursor: pointer; align-items: center; justify-content: center; z-index: 5;" title="Remove image">×</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group" style="flex: 1;">
                                    <label for="newModel">
                                        <i class="fas fa-cube"></i> 3D Model (.glb / .gltf)
                                    </label>
                                    <small style="display: block; color: #888; margin-bottom: 8px;">3D model for interactive 360° view</small>
                                    <input type="file" id="newModel" name="model" accept=".glb,.gltf" style="display: none;" onchange="previewNewProductModel(this)">
                                    
                                    <!-- Empty State Placeholder (No white cover) -->
                                    <div id="newModelPlaceholder" tabindex="0" onclick="document.getElementById('newModel').click()" style="height: 120px; border: 1.5px dashed rgba(124, 99, 0, 0.45); border-radius: 10px; background: #181818; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; gap: 4px; padding: 10px; text-align: center; transition: all 0.25s ease;">
                                        <i class="fas fa-cube" style="font-size: 24px; color: #ffd700;"></i>
                                        <span style="font-size: 11px; font-weight: 700; color: #ffd700;">Click to Upload 3D Model</span>
                                        <span style="font-size: 10px; color: #888;">Select .glb or .gltf file</span>
                                    </div>

                                    <!-- Active 3D Model Preview (Only shown when 3D model loaded) -->
                                    <div id="newModelPreview" class="model-preview-container" style="display: none;">
                                        <div style="position: relative; border-radius: 10px; overflow: hidden; border: 1.5px solid rgba(124, 99, 0, 0.45); background: #151515;">
                                            <model-viewer 
                                                id="newModelViewer"
                                                src=""
                                                alt="3D Gown Model Preview"
                                                auto-rotate
                                                camera-orbit="auto 90deg auto"
                                                camera-controls
                                                disable-pan
                                                disable-zoom
                                                min-camera-orbit="auto 90deg auto"
                                                max-camera-orbit="auto 90deg auto"
                                                style="width: 100%; height: 220px; background-color: #151515; --poster-color: transparent;">
                                            </model-viewer>
                                            <div style="padding: 6px 10px; background: #1e1e1e; display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #333;">
                                                <span id="newModelFileName" style="font-size: 11px; color: #ffd700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 65%;"><i class="fas fa-cube"></i> Model loaded</span>
                                                <button type="button" onclick="removeNewProductModel()" style="padding: 3px 8px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                                                    <i class="fas fa-trash-alt"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <button type="submit" class="btn-submit">Add Product</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Edit Product Slide-in Panel -->
            <div id="editModal" class="slide-panel">
                <div class="slide-panel-content">
                    <div class="slide-panel-header">
                        <h2>Edit Product</h2>
                        <button type="button" class="close-panel-btn" onclick="closeEditModal()" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="slide-panel-body">
                        <div class="edit-modal-grid">
                            <!-- Left Column - 3D Model -->
                            <div class="edit-model-section">
                                <div id="editModelPreview" class="edit-model-preview" style="position: relative; width: 100%; height: 165px; border-radius: 8px; overflow: hidden; background: #18191f; display: flex; align-items: center; justify-content: center; border: 1.5px dashed rgba(201, 170, 46, 0.45);">
                                    <!-- Empty placeholder when no model is attached -->
                                    <div id="editModelPlaceholder" style="display: none; flex-direction: column; align-items: center; justify-content: center; gap: 6px; color: #888; text-align: center; padding: 10px; cursor: pointer; width: 100%; height: 100%;" onclick="document.getElementById('editModelFile').click()">
                                        <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(201, 170, 46, 0.15); display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-cube" style="font-size: 20px; color: #c9aa2e;"></i>
                                        </div>
                                        <div style="font-weight: 600; color: #e0e0e0; font-size: 13px;">3D Model (.glb / .gltf)</div>
                                        <div style="font-size: 11px; color: #aaa;">Click "Upload 3D Model" button below</div>
                                    </div>
                                    
                                    <!-- Active 3D model viewer -->
                                    <model-viewer
                                        id="editModelViewer"
                                        src=""
                                        alt="3D Gown Model"
                                        auto-rotate
                                        bounds="tight"
                                        camera-orbit="auto 90deg 82%"
                                        camera-controls
                                        disable-pan
                                        disable-zoom
                                        min-camera-orbit="auto 90deg 82%"
                                        max-camera-orbit="auto 90deg 82%"
                                        style="width: 100%; height: 100%; border-radius: 8px;">
                                    </model-viewer>
                                </div>
                                <div style="display: flex; gap: 8px; margin-top: 8px;">
                                    <button type="button" class="btn-change-model" id="editChangeModelBtn" onclick="document.getElementById('editModelFile').click()" style="flex: 2; padding: 7px 12px; background: #c9aa2e; color: #111; font-weight: 700; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s ease;">
                                        <i class="fas fa-cloud-upload-alt"></i> <span id="editModelBtnText">Upload 3D Model</span>
                                    </button>
                                    <button type="button" class="btn-remove-model" id="editRemoveModelBtn" onclick="remove3DModel()" style="flex: 1; padding: 7px 12px; background: #dc3545; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; display: none; align-items: center; justify-content: center; gap: 6px;">
                                        <i class="fas fa-trash-alt"></i> Remove
                                    </button>
                                </div>
                                <div id="editModelStatus" style="font-size: 11px; margin-top: 4px; min-height: 16px; text-align: center;"></div>
                                
                                <!-- Image Boxes: Horizontal 1 Row Per Image -->
                                <div class="form-group" style="margin-top: 15px;">
                                    <label style="display: block; margin-bottom: 4px; font-size: 13px; font-weight: 600; color: #cccccc;">
                                        <i class="fas fa-images"></i> Product Images (Select up to 4 images)
                                    </label>
                                    <small style="display: block; color: #888; margin-bottom: 8px;">Click any box below to upload image</small>
                                    
                                    <div class="image-boxes-container edit-product-image-slots" style="display: flex; flex-direction: column; gap: 10px; width: 100%; margin-top: 6px;">
                                        <!-- Slot 1 -->
                                        <div class="edit-image-slot-row" id="editSlotContainer1" tabindex="0" onclick="triggerEditImageSlot(1)">
                                            <input type="file" id="editImage1" name="gownImage1" accept="image/*" style="display: none;" onchange="handleEditImageSlotChange(this, 1)">
                                            
                                            <!-- Empty State / Placeholder -->
                                            <div class="edit-slot-placeholder" id="editSlotPlaceholder1">
                                                <div class="slot-icon-badge">
                                                    <i class="fas fa-camera"></i>
                                                </div>
                                                <div class="slot-text-col">
                                                    <span class="slot-title">Image 1 (Main)</span>
                                                    <span class="slot-subtitle">+ Click to upload image</span>
                                                </div>
                                                <div class="slot-action-icon">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                </div>
                                            </div>
                                            
                                            <!-- Loaded State: Full box image with front remove X button -->
                                            <img id="editSlotImgPreview1" src="" alt="Slot 1 Preview" class="slot-full-img" style="display: none;">
                                            <span class="slot-floating-badge" id="editSlotBadge1" style="display: none;">Image 1 (Main)</span>
                                            <button type="button" class="slot-remove-btn" id="editSlotRemoveBtn1" onclick="removeEditImageSlot(event, 1)" style="display: none;" title="Remove image">×</button>
                                        </div>

                                        <!-- Slot 2 -->
                                        <div class="edit-image-slot-row" id="editSlotContainer2" tabindex="0" onclick="triggerEditImageSlot(2)">
                                            <input type="file" id="editImage2" name="gownImage2" accept="image/*" style="display: none;" onchange="handleEditImageSlotChange(this, 2)">
                                            
                                            <!-- Empty State / Placeholder -->
                                            <div class="edit-slot-placeholder" id="editSlotPlaceholder2">
                                                <div class="slot-icon-badge">
                                                    <i class="fas fa-camera"></i>
                                                </div>
                                                <div class="slot-text-col">
                                                    <span class="slot-title">Image 2</span>
                                                    <span class="slot-subtitle">+ Click to upload image</span>
                                                </div>
                                                <div class="slot-action-icon">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                </div>
                                            </div>
                                            
                                            <!-- Loaded State: Full box image with front remove X button -->
                                            <img id="editSlotImgPreview2" src="" alt="Slot 2 Preview" class="slot-full-img" style="display: none;">
                                            <span class="slot-floating-badge" id="editSlotBadge2" style="display: none;">Image 2</span>
                                            <button type="button" class="slot-remove-btn" id="editSlotRemoveBtn2" onclick="removeEditImageSlot(event, 2)" style="display: none;" title="Remove image">×</button>
                                        </div>

                                        <!-- Slot 3 -->
                                        <div class="edit-image-slot-row" id="editSlotContainer3" tabindex="0" onclick="triggerEditImageSlot(3)">
                                            <input type="file" id="editImage3" name="gownImage3" accept="image/*" style="display: none;" onchange="handleEditImageSlotChange(this, 3)">
                                            
                                            <!-- Empty State / Placeholder -->
                                            <div class="edit-slot-placeholder" id="editSlotPlaceholder3">
                                                <div class="slot-icon-badge">
                                                    <i class="fas fa-camera"></i>
                                                </div>
                                                <div class="slot-text-col">
                                                    <span class="slot-title">Image 3</span>
                                                    <span class="slot-subtitle">+ Click to upload image</span>
                                                </div>
                                                <div class="slot-action-icon">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                </div>
                                            </div>
                                            
                                            <!-- Loaded State: Full box image with front remove X button -->
                                            <img id="editSlotImgPreview3" src="" alt="Slot 3 Preview" class="slot-full-img" style="display: none;">
                                            <span class="slot-floating-badge" id="editSlotBadge3" style="display: none;">Image 3</span>
                                            <button type="button" class="slot-remove-btn" id="editSlotRemoveBtn3" onclick="removeEditImageSlot(event, 3)" style="display: none;" title="Remove image">×</button>
                                        </div>

                                        <!-- Slot 4 -->
                                        <div class="edit-image-slot-row" id="editSlotContainer4" tabindex="0" onclick="triggerEditImageSlot(4)">
                                            <input type="file" id="editImage4" name="gownImage4" accept="image/*" style="display: none;" onchange="handleEditImageSlotChange(this, 4)">
                                            
                                            <!-- Empty State / Placeholder -->
                                            <div class="edit-slot-placeholder" id="editSlotPlaceholder4">
                                                <div class="slot-icon-badge">
                                                    <i class="fas fa-camera"></i>
                                                </div>
                                                <div class="slot-text-col">
                                                    <span class="slot-title">Image 4</span>
                                                    <span class="slot-subtitle">+ Click to upload image</span>
                                                </div>
                                                <div class="slot-action-icon">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                </div>
                                            </div>
                                            
                                            <!-- Loaded State: Full box image with front remove X button -->
                                            <img id="editSlotImgPreview4" src="" alt="Slot 4 Preview" class="slot-full-img" style="display: none;">
                                            <span class="slot-floating-badge" id="editSlotBadge4" style="display: none;">Image 4</span>
                                            <button type="button" class="slot-remove-btn" id="editSlotRemoveBtn4" onclick="removeEditImageSlot(event, 4)" style="display: none;" title="Remove image">×</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column - Form Fields -->
                            <div class="edit-form-section">
                                <form id="editForm" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="edit_gown">
                                    <input type="hidden" id="editId" name="gown_id">
                                    <input type="hidden" id="removeModelFlag" name="remove_model" value="0">
                                    <input type="file" id="editModelFile" name="gown3DModel" form="editForm" accept=".glb,.gltf" style="display: none;" onchange="handleModelFileChange(this)">
                                    
                                    <div class="form-group">
                                        <label for="editName">Product</label>
                                        <input type="text" id="editName" name="gownName" required>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="editColor">Color</label>
                                            <input type="text" id="editColor" name="gownColor" required placeholder="Enter color">
                                        </div>
                                        <div class="form-group">
                                            <label for="editCategory">Category</label>
                                            <select id="editCategory" name="gownCategory" required onchange="loadSizeLabelsForCategory('editCategory', 'editSizeRanges')">
                                                <option value="">Select Category</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="editPrice">Price (₱)</label>
                                            <input type="number" id="editPrice" name="gownPrice" min="0" step="0.01" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="editPurchasePrice">Purchase Price (₱)</label>
                                            <input type="number" id="editPurchasePrice" name="gownPurchasePrice" min="0" step="0.01" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="editDiscount">Discount (%)</label>
                                            <input type="number" id="editDiscount" name="gownDiscount" min="0" max="100" step="0.01" value="0">
                                        </div>
                                        <div class="form-group">
                                            <label for="editStock">Stock Quantity</label>
                                            <input type="number" id="editStock" name="gownStock" min="0" value="0">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Size Ranges (cm)</label>
                                        <div class="size-ranges-container" id="editSizeRanges">
                                            <!-- Size ranges will be dynamically generated based on category -->
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="editDescription">Description</label>
                                        <textarea id="editDescription" name="gownDescription" rows="2"></textarea>
                                    </div>

                                    <div class="form-actions">
                                        <button type="submit" class="btn-submit">Update Product</button>
                                        <button type="button" class="btn-delete" onclick="deleteProduct()">Delete Product</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <form id="addGownForm" method="POST" enctype="multipart/form-data" style="display: none;">
                <input type="hidden" name="action" value="add_gown">
            </form>

            <!-- Gowns Grid -->
            <div class="content-section">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="success-message"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="error-message"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>
                
                <div class="gowns-grid">
                    <?php if (!empty($gowns)): ?>
                        <?php
                        // Group gowns by category
                        $grouped_gowns = [];
                        foreach ($gowns as $gown) {
                            $grouped_gowns[$gown['category']][] = $gown;
                        }

                        // Get categories from categories table
                        $categoryManager = new CategoryManager();
                        $categories_from_db = $categoryManager->getAllCategories();

                        // Extract category names from database
                        $category_order = [];
                        foreach ($categories_from_db as $cat) {
                            $category_order[] = $cat['category_name'];
                        }

                        // Also include any categories from gowns that might not be in categories table
                        foreach ($grouped_gowns as $category => $gowns_list) {
                            if (!in_array($category, $category_order)) {
                                $category_order[] = $category;
                            }
                        }

                        foreach ($category_order as $category): ?>
                            <!-- Category Header -->
                            <div class="category-header" data-category="<?php echo htmlspecialchars($category); ?>">
                                <h3 class="category-title"><?php echo htmlspecialchars($category); ?></h3>
                                <div class="category-line"></div>
                            </div>

                            <!-- Products in this category -->
                            <div class="category-row" data-category="<?php echo htmlspecialchars($category); ?>">
                                <?php if (isset($grouped_gowns[$category]) && !empty($grouped_gowns[$category])): ?>
                                    <?php foreach ($grouped_gowns[$category] as $gown): ?>
                                        <div class="gown-card" style="position: relative;" data-category="<?php echo htmlspecialchars($gown['category']); ?>" data-gown-id="<?php echo $gown['id']; ?>" ondblclick="openEditModal(<?php echo $gown['id']; ?>)">
                                            <?php if (!empty($gown['image_path'])): ?>
                                                <img src="../<?php echo htmlspecialchars($gown['image_path']); ?>" alt="<?php echo htmlspecialchars($gown['name']); ?>" class="model-viewer" style="object-fit: contain; padding: 0;">
                                            <?php elseif (!empty($gown['image_path2'])): ?>
                                                <img src="../<?php echo htmlspecialchars($gown['image_path2']); ?>" alt="<?php echo htmlspecialchars($gown['name']); ?>" class="model-viewer" style="object-fit: contain; padding: 0;">
                                            <?php elseif (!empty($gown['image_path3'])): ?>
                                                <img src="../<?php echo htmlspecialchars($gown['image_path3']); ?>" alt="<?php echo htmlspecialchars($gown['name']); ?>" class="model-viewer" style="object-fit: contain; padding: 0;">
                                            <?php elseif (!empty($gown['image_path4'])): ?>
                                                <img src="../<?php echo htmlspecialchars($gown['image_path4']); ?>" alt="<?php echo htmlspecialchars($gown['name']); ?>" class="model-viewer" style="object-fit: contain; padding: 0;">
                                            <?php elseif (!empty($gown['model_path'])): ?>
                                                <!-- Display 3D model only when no image is uploaded -->
                                                <model-viewer
                                                    src="../<?php echo htmlspecialchars($gown['model_path']); ?>"
                                                    alt="<?php echo htmlspecialchars($gown['name']); ?>"
                                                    auto-rotate
                                                    bounds="tight"
                                                    camera-orbit="auto 90deg 82%"
                                                    camera-controls
                                                    disable-pan
                                                    disable-zoom
                                                    min-camera-orbit="auto 90deg 82%"
                                                    max-camera-orbit="auto 90deg 82%"
                                                    class="model-viewer">
                                                </model-viewer>
                                            <?php else: ?>
                                                <!-- Default placeholder for gowns without images or 3D models -->
                                                <div class="model-viewer" style="display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, 0.05);">
                                                    <div style="text-align: center; color: rgba(255, 255, 255, 0.6);">
                                                        <div style="font-size: 3rem; margin-bottom: 10px;">👗</div>
                                                        <div><?php echo htmlspecialchars($gown['name']); ?></div>
                                                        <div style="font-size: 0.9rem; opacity: 0.7;">No Image</div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($gown['model_path'])): ?>
                                                <span class="card-3d-badge" title="Has 3D Model" style="position: absolute; top: 8px; right: 8px; background: rgba(0, 0, 0, 0.75); color: #c9aa2e; border: 1px solid rgba(201, 170, 46, 0.45); border-radius: 6px; padding: 2px 7px; font-size: 10px; font-weight: 700; z-index: 2; pointer-events: none; display: flex; align-items: center; gap: 4px;">
                                                    <i class="fas fa-cube"></i> 3D
                                                </span>
                                            <?php endif; ?>
                                            <div class="gown-card-content">
                                                <h3><?php echo htmlspecialchars($gown['name']); ?></h3>
                                                <p class="gown-category"><?php echo htmlspecialchars($gown['category']); ?></p>
                                                <div class="gown-meta">
                                                    <?php
                                                    $originalPrice = $gown['price'];
                                                    $discount = $gown['discount'] ?? 0;
                                                    $discountedPrice = $discount > 0 ? $originalPrice * (1 - $discount / 100) : $originalPrice;
                                                    ?>
                                                    <span class="price">
                                                        <?php if ($discount > 0): ?>
                                                            <span style="color: #ff4444; font-weight: bold;"> ₱<?php echo number_format($discountedPrice, 2); ?></span>
                                                            <span style="text-decoration: line-through; color: #999; font-size: 0.9rem;"> ₱<?php echo number_format($originalPrice, 2); ?></span>
                                                        <?php else: ?>
                                                            ₱<?php echo number_format($originalPrice, 2); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                    <?php if ($discount > 0): ?>
                                                        <span class="discount-badge">-<?php echo round($discount); ?>%</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="gown-stock">
                                                    <?php
                                                    $stock = intval($gown['stock'] ?? 0);
                                                    $stockColor = $stock == 0 ? '#f44336' : ($stock <= $lowStockThreshold ? '#ff9800' : '#4CAF50');
                                                    $stockLabel = $stock == 0 ? 'Out of Stock' : ($stock <= $lowStockThreshold ? "Low Stock: {$stock}" : "Stock: {$stock}");
                                                    ?>
                                                    <span style="color: <?php echo $stockColor; ?>; font-size: 0.8rem; font-weight: 600;">
                                                        <i class="fas fa-box" style="margin-right: 4px;"></i><?php echo $stockLabel; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="color: rgba(255, 255, 255, 0.5); padding: 20px;">No products in this category yet</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="content-section" style="text-align: center;">
                            <h3 style="color: white;">No gowns found</h3>
                            <p style="color: rgba(255, 255, 255, 0.8);">Start by adding your first gown to inventory.</p>
                            <button class="cta-button" onclick="openAddGownModal()">Add Your First Gown</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddGownModal() {
            document.getElementById('addGownModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            document.body.classList.add('modal-open');
        }

        function closeAddGownModal() {
            document.getElementById('addGownModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.body.classList.remove('modal-open');
            resetForm();
        }

        function resetForm() {
            document.getElementById('addGownForm').reset();
            document.getElementById('3dPreview').style.display = 'none';
            document.getElementById('dropArea').classList.remove('drag-over');
        }

        // Drag and drop functionality
        const dropArea = document.getElementById('dropArea');
        const fileInput = document.getElementById('gown3DModel');
        const dropMessage = dropArea.querySelector('.drop-message');
        const modelPreview = document.getElementById('3dPreview');
        const modelViewer = document.getElementById('modelViewer');

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropArea.classList.add('drag-over');
        }

        function unhighlight(e) {
            dropArea.classList.remove('drag-over');
        }

        // Handle dropped files
        dropArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        }

        // Handle file input change
        fileInput.addEventListener('change', function(e) {
            handleFiles(this.files);
        });

        // Click to browse
        dropArea.addEventListener('click', function() {
            fileInput.click();
        });

        function handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                if (file.name.toLowerCase().endsWith('.glb')) {
                    display3DModel(file);
                    dropMessage.textContent = `Selected: ${file.name}`;
                } else {
                    alert('Please select a .glb file');
                }
            }
        }

        function display3DModel(file) {
            const url = URL.createObjectURL(file);
            modelViewer.src = url;
            modelPreview.style.display = 'block';
        }

        // Form validation before submission
        document.getElementById('addGownForm').addEventListener('submit', function(e) {
            const gownName = document.getElementById('gownName').value.trim();
            const gownCategory = document.getElementById('gownCategory').value;
            const gownPrice = document.getElementById('gownPrice').value;
            const gownSize = document.getElementById('gownSize').value;
            
            if (!gownName || !gownCategory || !gownPrice || !gownSize) {
                e.preventDefault();
                alert('Please fill in all required fields (Name, Category, Price, Size)');
                return false;
            }
            
            if (parseFloat(gownPrice) <= 0) {
                e.preventDefault();
                alert('Price must be greater than 0');
                return false;
            }
            
            // Let the form submit normally to the server
            // The PHP code will handle the database insertion
        });

        function triggerEditImageSlot(slotNum) {
            const input = document.getElementById('editImage' + slotNum);
            if (input) input.click();
        }

        // Handle image upload for edit modal
        function handleEditImageSlotChange(input, boxNumber) {
            const file = input.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewImg = document.getElementById('editSlotImgPreview' + boxNumber);
                    const placeholder = document.getElementById('editSlotPlaceholder' + boxNumber);
                    const badge = document.getElementById('editSlotBadge' + boxNumber);
                    const removeBtn = document.getElementById('editSlotRemoveBtn' + boxNumber);
                    const container = document.getElementById('editSlotContainer' + boxNumber);

                    if (previewImg) {
                        previewImg.src = e.target.result;
                        previewImg.style.display = 'block';
                    }
                    if (placeholder) placeholder.style.display = 'none';
                    if (badge) badge.style.display = 'block';
                    if (removeBtn) removeBtn.style.display = 'flex';
                    if (container) {
                        container.classList.add('has-image');
                        container.style.borderColor = '#c9aa2e';
                        container.style.borderStyle = 'solid';
                        container.style.boxShadow = '0 0 12px rgba(201, 170, 46, 0.25)';
                    }
                    
                    // Auto-save the image to database
                    saveImageToDatabase(boxNumber, file);
                };
                reader.readAsDataURL(file);
            }
        }

        // Backward compatibility alias
        function handleImageUpload(input, boxNumber) {
            handleEditImageSlotChange(input, boxNumber);
        }

        function removeEditImageSlot(event, slotNum) {
            if (event) event.stopPropagation();

            const input = document.getElementById('editImage' + slotNum);
            if (input) input.value = '';

            const previewImg = document.getElementById('editSlotImgPreview' + slotNum);
            const placeholder = document.getElementById('editSlotPlaceholder' + slotNum);
            const badge = document.getElementById('editSlotBadge' + slotNum);
            const removeBtn = document.getElementById('editSlotRemoveBtn' + slotNum);
            const container = document.getElementById('editSlotContainer' + slotNum);

            if (previewImg) {
                previewImg.src = '';
                previewImg.style.display = 'none';
            }
            if (badge) badge.style.display = 'none';
            if (placeholder) placeholder.style.display = 'flex';
            if (removeBtn) removeBtn.style.display = 'none';
            if (container) {
                container.classList.remove('has-image');
                container.style.borderColor = 'rgba(124, 99, 0, 0.45)';
                container.style.borderStyle = 'dashed';
                container.style.boxShadow = 'none';
            }

            // Remove image from database
            const gownId = document.getElementById('editId').value;
            if (gownId) {
                const formData = new FormData();
                formData.append('action', 'remove_image');
                formData.append('gown_id', gownId);
                formData.append('image_number', slotNum);
                fetch('gowns.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        console.log(`Image ${slotNum} removed from database`);
                    }
                })
                .catch(err => console.error('Error removing image:', err));
            }
        }

        function saveImageToDatabase(boxNumber, file) {
            const gownId = document.getElementById('editId').value;
            const formData = new FormData();
            
            formData.append('action', 'upload_image');
            formData.append('gown_id', gownId);
            formData.append('image_number', boxNumber);
            formData.append('image', file);
            
            fetch('gowns.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`Image ${boxNumber} saved successfully`);
                } else {
                    console.error(`Failed to save image ${boxNumber}:`, data.error);
                    alert(`Failed to save image: ${data.error || 'Unknown error'}`);
                }
            })
            .catch(error => {
                console.error('Error saving image:', error);
                alert('Error saving image to database');
            });
        }

        // Edit modal functions
        function openEditModal(gownId) {
            // Fetch gown data
            fetch(`gowns.php?action=get_gown&id=${gownId}`)
                .then(response => response.json())
                .then(data => {
                    // Populate form fields
                    document.getElementById('editId').value = data.id;
                    document.getElementById('editName').value = data.name;
                    document.getElementById('editCategory').value = data.category;
                    document.getElementById('editPrice').value = data.price;
                    document.getElementById('editPurchasePrice').value = data.purchase_price || 0;
                    document.getElementById('editColor').value = data.color || '';
                    document.getElementById('editDescription').value = data.description || '';
                    document.getElementById('editDiscount').value = data.discount || 0;
                    document.getElementById('editStock').value = data.stock || 0;

                    // Load size labels and populate size ranges
                    loadSizeLabelsWithValues('editCategory', 'editSizeRanges', data);

                    // Load 3D model if exists
                    const editModelViewer = document.getElementById('editModelViewer');
                    const editModelPlaceholder = document.getElementById('editModelPlaceholder');
                    const editModelBtnText = document.getElementById('editModelBtnText');
                    const editRemoveModelBtn = document.getElementById('editRemoveModelBtn');
                    const editModelStatus = document.getElementById('editModelStatus');

                    // Reset 3D model form state
                    const editModelFile = document.getElementById('editModelFile');
                    if (editModelFile) editModelFile.value = '';
                    const removeFlag = document.getElementById('removeModelFlag');
                    if (removeFlag) removeFlag.value = '0';

                    if (data.model_path && String(data.model_path).trim() !== '') {
                        editModelViewer.src = '../' + data.model_path;
                        editModelViewer.style.display = 'block';
                        if (editModelPlaceholder) editModelPlaceholder.style.display = 'none';
                        if (editModelBtnText) editModelBtnText.textContent = 'Change 3D Model';
                        if (editRemoveModelBtn) editRemoveModelBtn.style.display = 'flex';
                        if (editModelStatus) editModelStatus.innerHTML = '<span style="color: #4ade80;"><i class="fas fa-cube"></i> Current 3D model loaded</span>';
                    } else {
                        editModelViewer.src = '';
                        editModelViewer.style.display = 'none';
                        if (editModelPlaceholder) editModelPlaceholder.style.display = 'flex';
                        if (editModelBtnText) editModelBtnText.textContent = 'Upload 3D Model';
                        if (editRemoveModelBtn) editRemoveModelBtn.style.display = 'none';
                        if (editModelStatus) editModelStatus.innerHTML = '<span style="color: #888;">No 3D model attached</span>';
                    }

                    // Load images into the 4 boxes
                    loadImagesIntoBoxes(data);

                    // Close mobile sidebar if open
                    if (typeof closeSidebar === 'function') {
                        closeSidebar();
                    }

                    // Hide mobile top title
                    const mobileTitle = document.getElementById('mobilePageTitle');
                    if (mobileTitle) mobileTitle.style.display = 'none';

                    // Hide sidebar and top buttons/toolbar
                    document.querySelectorAll('.sidebar').forEach(el => el.style.setProperty('display', 'none', 'important'));
                    document.querySelectorAll('.search-filter-section-top').forEach(el => el.style.setProperty('display', 'none', 'important'));

                    // Show modal
                    document.getElementById('editModal').classList.add('slide-panel-open');
                    document.body.style.overflow = 'hidden';
                    document.body.classList.add('product-modal-open');
                })
                .catch(error => {
                    console.error('Error fetching gown data:', error);
                    alert('Error loading gown data');
                });
        }

        function loadImagesIntoBoxes(data) {
            for (let i = 1; i <= 4; i++) {
                const key = i === 1 ? 'image_path' : ('image_path' + i);
                const imgPath = data ? data[key] : null;
                const container = document.getElementById('editSlotContainer' + i);
                const previewImg = document.getElementById('editSlotImgPreview' + i);
                const placeholder = document.getElementById('editSlotPlaceholder' + i);
                const badge = document.getElementById('editSlotBadge' + i);
                const removeBtn = document.getElementById('editSlotRemoveBtn' + i);
                const input = document.getElementById('editImage' + i);

                if (input) input.value = '';

                if (container && previewImg && placeholder) {
                    if (imgPath && String(imgPath).trim() !== '') {
                        previewImg.src = '../' + imgPath;
                        previewImg.style.display = 'block';
                        if (badge) badge.style.display = 'block';
                        placeholder.style.display = 'none';
                        if (removeBtn) removeBtn.style.display = 'flex';
                        container.classList.add('has-image');
                        container.style.borderColor = '#c9aa2e';
                        container.style.borderStyle = 'solid';
                        container.style.boxShadow = '0 0 12px rgba(201, 170, 46, 0.25)';
                    } else {
                        previewImg.src = '';
                        previewImg.style.display = 'none';
                        if (badge) badge.style.display = 'none';
                        placeholder.style.display = 'flex';
                        if (removeBtn) removeBtn.style.display = 'none';
                        container.classList.remove('has-image');
                        container.style.borderColor = 'rgba(124, 99, 0, 0.45)';
                        container.style.borderStyle = 'dashed';
                        container.style.boxShadow = 'none';
                    }
                }
            }
        }

        function closeEditModal() {
            const mobileTitle = document.getElementById('mobilePageTitle');
            if (mobileTitle) mobileTitle.style.display = '';

            document.getElementById('editModal').classList.remove('slide-panel-open');
            if (!document.getElementById('addNewProductModal').classList.contains('slide-panel-open')) {
                document.body.style.overflow = 'auto';
                document.body.classList.remove('product-modal-open');
                document.querySelectorAll('.sidebar').forEach(el => el.style.removeProperty('display'));
                document.querySelectorAll('.search-filter-section-top').forEach(el => el.style.removeProperty('display'));
            }
            document.getElementById('editForm').reset();

            // Re-enable double-click on gown cards
            const gownCards = document.querySelectorAll('.gown-card');
            gownCards.forEach(card => {
                card.style.pointerEvents = 'auto';
                card.style.opacity = '1';
            });
        }

        function openAddNewProductPanel() {
            if (typeof closeSidebar === 'function') {
                closeSidebar();
            }

            // Hide mobile top title
            const mobileTitle = document.getElementById('mobilePageTitle');
            if (mobileTitle) mobileTitle.style.display = 'none';

            // Hide sidebar and top buttons/toolbar
            document.querySelectorAll('.sidebar').forEach(el => el.style.setProperty('display', 'none', 'important'));
            document.querySelectorAll('.search-filter-section-top').forEach(el => el.style.setProperty('display', 'none', 'important'));

            document.getElementById('addNewProductModal').classList.add('slide-panel-open');
            document.body.style.overflow = 'hidden';
            document.body.classList.add('product-modal-open');

            // Disable double-click on gown cards
            const gownCards = document.querySelectorAll('.gown-card');
            gownCards.forEach(card => {
                card.style.pointerEvents = 'none';
                card.style.opacity = '0.6';
            });

            // Set up category change listener and initial display for Add New Product form
            const newCategory = document.getElementById('newCategory');
            if (newCategory) {
                newCategory.onchange = function() {
                    loadSizeLabelsForCategory('newCategory', 'newSizeRanges');
                };
                if (newCategory.value) {
                    loadSizeLabelsForCategory('newCategory', 'newSizeRanges');
                } else {
                    const newSizeRanges = document.getElementById('newSizeRanges');
                    if (newSizeRanges) {
                        newSizeRanges.innerHTML = '<p style="color: #888; font-size: 13px; margin: 5px 0;">Please select a category to see size ranges.</p>';
                    }
                }
            }
        }

        function closeAllPanels() {
            closeAddNewProductModal();
            closeEditModal();
        }

        function closeAddNewProductModal() {
            const mobileTitle = document.getElementById('mobilePageTitle');
            if (mobileTitle) mobileTitle.style.display = '';

            document.getElementById('addNewProductModal').classList.remove('slide-panel-open');
            if (!document.getElementById('editModal').classList.contains('slide-panel-open')) {
                document.body.style.overflow = 'auto';
                document.body.classList.remove('product-modal-open');
                document.querySelectorAll('.sidebar').forEach(el => el.style.removeProperty('display'));
                document.querySelectorAll('.search-filter-section-top').forEach(el => el.style.removeProperty('display'));
            }

            // Re-enable double-click on gown cards
            const gownCards = document.querySelectorAll('.gown-card');
            gownCards.forEach(card => {
                card.style.pointerEvents = 'auto';
                card.style.opacity = '1';
            });

            const addNewProductForm = document.getElementById('addNewProductForm');
            if (addNewProductForm) addNewProductForm.reset();
            const newSizeRanges = document.getElementById('newSizeRanges');
            if (newSizeRanges) newSizeRanges.innerHTML = '';
            
            // Reset image slots & 3D model
            if (typeof resetNewProductSlots === 'function') {
                resetNewProductSlots();
            }
        }

        // ===== BIG CATEGORIES MODAL FUNCTIONS =====
        function openCategoriesModal() {
            if (typeof closeSidebar === 'function') {
                closeSidebar();
            }
            if (typeof closeAllPanels === 'function') {
                closeAllPanels();
            }

            const modal = document.getElementById('categoriesModal');
            const iframe = document.getElementById('categoriesIframe');
            if (!modal || !iframe) return;

            // Load categories.php in embed mode
            iframe.src = 'categories.php?embed=1';

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeCategoriesModal() {
            const modal = document.getElementById('categoriesModal');
            const iframe = document.getElementById('categoriesIframe');
            if (modal) {
                modal.style.display = 'none';
            }
            document.body.style.overflow = 'auto';
            if (iframe) {
                iframe.src = 'about:blank';
            }
            // Refresh categories dropdowns in gowns.php so changes reflect immediately
            if (typeof loadCategories === 'function') {
                loadCategories();
            }
        }


        function handleAddNewProductSubmit(e) {
            e.preventDefault();

            // Show add confirmation modal with Lottie animation
            showAddConfirmationModal();
        }

        // Show add confirmation modal without animation
        function showAddConfirmationModal() {
            // Create modal if it doesn't exist
            let addModal = document.getElementById('addConfirmationModal');
            if (!addModal) {
                addModal = document.createElement('div');
                addModal.id = 'addConfirmationModal';
                addModal.style.cssText = `
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
                addModal.innerHTML = `
                    <div id="addModalContent" style="background: #2a2a2a; padding: 30px; border-radius: 12px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 10px 30px rgba(0,0,0,0.6);">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(255, 215, 0, 0.15); display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                            <i class="fas fa-plus-circle" style="font-size: 26px; color: #ffd700;"></i>
                        </div>
                        <h3 style="color: #fff; margin: 0 0 10px 0; font-size: 18px;">Add Product?</h3>
                        <p style="color: #999; margin-bottom: 20px; font-size: 13px; line-height: 1.5;">Are you sure you want to add this product to the catalog?</p>
                        <div id="addButtons" style="display: flex; gap: 10px; justify-content: center;">
                            <button type="button" id="cancelAddBtn" style="background: #555; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">Cancel</button>
                            <button type="button" id="confirmAddBtn" style="background: #988726; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">Add</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(addModal);

                // Add event listeners
                document.getElementById('cancelAddBtn').addEventListener('click', () => {
                    addModal.style.display = 'none';
                });

                document.getElementById('confirmAddBtn').addEventListener('click', () => {
                    addModal.style.display = 'none';
                    executeAddProduct();
                });
            }

            // Show modal
            addModal.style.display = 'flex';
        }

        // Reset add modal to initial state
        function resetAddModal() {
            const addModal = document.getElementById('addConfirmationModal');
            if (addModal) {
                addModal.style.display = 'none';
            }
        }

        // Execute the actual add product operation
        function executeAddProduct() {
            const form = document.getElementById('addNewProductForm');
            const formData = new FormData(form);

            // Attach 4 image slots if selected
            if (typeof newProductImageFiles !== 'undefined') {
                for (let i = 1; i <= 4; i++) {
                    if (newProductImageFiles[i]) {
                        formData.set('image' + i, newProductImageFiles[i]);
                    }
                }
            }

            // Attach 3D model if selected
            if (typeof selectedNewProductModel !== 'undefined' && selectedNewProductModel && selectedNewProductModel.file) {
                formData.set('model', selectedNewProductModel.file);
            }

            // Show loading overlay with progress
            showLoadingOverlay();

            let progress = 0;
            const startTime = Date.now();
            const totalDuration = 4000; // 4 seconds base duration
            const updateInterval = 50; // Update every 50ms

            const progressInterval = setInterval(() => {
                const elapsed = Date.now() - startTime;
                progress = Math.min(100, Math.floor((elapsed / totalDuration) * 100));
                updateLoadingProgress(progress);

                if (progress >= 100) {
                    clearInterval(progressInterval);
                }
            }, updateInterval);

            fetch('gowns.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                clearInterval(progressInterval);
                updateLoadingProgress(100);

                console.log('Response data:', data);
                if (data.success) {
                    setTimeout(() => {
                        hideLoadingOverlay();
                        closeAddNewProductModal();
                        sessionStorage.setItem('toast_notification', JSON.stringify({
                            title: 'Product Added',
                            message: 'Product added successfully!',
                            type: 'success'
                        }));
                        setTimeout(() => {
                            location.reload();
                        }, 200);
                    }, 500);
                } else {
                    hideLoadingOverlay();
                    showNotification('Error Adding Product', data.message || 'Failed to add product.', 'error');
                }
            })
            .catch(error => {
                clearInterval(progressInterval);
                hideLoadingOverlay();
                console.error('Error:', error);
                showNotification('Error', error.message || 'Error adding product.', 'error');
            });
        }

        // Handle 3D model file change in edit modal
        function handleModelFileChange(input) {
            const file = input.files && input.files[0];
            if (!file) return;

            const fileName = file.name.toLowerCase();
            const isValidModel = fileName.endsWith('.glb') || fileName.endsWith('.gltf') || file.type.includes('gltf') || file.type.includes('model');
            
            if (isValidModel) {
                const fileSizeMB = (file.size / (1024 * 1024)).toFixed(1);
                if (file.size > 512 * 1024 * 1024) {
                    alert(`Selected file (${fileSizeMB} MB) exceeds the 512 MB server upload limit. Please select a smaller file.`);
                    input.value = '';
                    return;
                }

                const viewer = document.getElementById('editModelViewer');
                const url = URL.createObjectURL(file);
                viewer.src = url;
                viewer.style.display = 'block';

                const placeholder = document.getElementById('editModelPlaceholder');
                if (placeholder) placeholder.style.display = 'none';
                
                // Reset remove flag since a new model is selected
                document.getElementById('removeModelFlag').value = '0';

                const btnText = document.getElementById('editModelBtnText');
                if (btnText) btnText.textContent = 'Change 3D Model';

                const removeBtn = document.getElementById('editRemoveModelBtn');
                if (removeBtn) removeBtn.style.display = 'flex';

                const status = document.getElementById('editModelStatus');
                if (status) {
                    status.innerHTML = `<span style="color: #4ade80; font-weight: 600;"><i class="fas fa-check-circle"></i> Ready: ${file.name} (${fileSizeMB} MB). Click "Update Product" to save!</span>`;
                }
            } else {
                alert('Please select a valid 3D model file (.glb or .gltf)');
                input.value = '';
            }
        }

        // Show custom remove 3D confirmation modal (styled same as delete & add modals)
        function showRemove3DConfirmationModal() {
            let remove3DModal = document.getElementById('remove3DConfirmationModal');
            if (!remove3DModal) {
                remove3DModal = document.createElement('div');
                remove3DModal.id = 'remove3DConfirmationModal';
                remove3DModal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.8);
                    display: none;
                    align-items: center;
                    justify-content: center;
                    z-index: 10005;
                `;
                remove3DModal.innerHTML = `
                    <div id="remove3DModalContent" style="background: #2a2a2a; padding: 30px; border-radius: 12px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 10px 30px rgba(0,0,0,0.6);">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(255, 68, 68, 0.15); display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                            <i class="fas fa-cube" style="font-size: 24px; color: #ff4444;"></i>
                        </div>
                        <h3 style="color: #fff; margin: 0 0 10px 0; font-size: 18px;">Remove 3D Model?</h3>
                        <p style="color: #999; margin-bottom: 20px; font-size: 13px; line-height: 1.5;">Are you sure you want to remove the 3D model? The product will only display photos.</p>
                        <div id="remove3DButtons" style="display: flex; gap: 10px; justify-content: center;">
                            <button type="button" id="cancelRemove3DBtn" style="background: #555; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 13px; font-weight: 600;">Cancel</button>
                            <button type="button" id="confirmRemove3DBtn" style="background: #ff4444; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 13px; font-weight: 600;">Remove</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(remove3DModal);

                document.getElementById('cancelRemove3DBtn').addEventListener('click', () => {
                    remove3DModal.style.display = 'none';
                });

                document.getElementById('confirmRemove3DBtn').addEventListener('click', () => {
                    remove3DModal.style.display = 'none';
                    executeRemove3DModel();
                });
            }

            remove3DModal.style.display = 'flex';
        }

        // Remove 3D model trigger
        function remove3DModel() {
            showRemove3DConfirmationModal();
        }

        // Execute removal
        function executeRemove3DModel() {
            const viewer = document.getElementById('editModelViewer');
            viewer.src = '';
            viewer.style.display = 'none';
            
            const placeholder = document.getElementById('editModelPlaceholder');
            if (placeholder) placeholder.style.display = 'flex';
            
            // Set remove flag
            document.getElementById('removeModelFlag').value = '1';
            
            // Clear file input
            document.getElementById('editModelFile').value = '';
            
            const btnText = document.getElementById('editModelBtnText');
            if (btnText) btnText.textContent = 'Upload 3D Model';

            const removeBtn = document.getElementById('editRemoveModelBtn');
            if (removeBtn) removeBtn.style.display = 'none';

            const status = document.getElementById('editModelStatus');
            if (status) {
                status.innerHTML = '<span style="color: #ff6b6b; font-weight: 600;"><i class="fas fa-trash-alt"></i> 3D model will be removed. Click "Update Product" to save.</span>';
            }
        }

        // Add form submit handler
        document.addEventListener('DOMContentLoaded', function() {
            const addNewProductForm = document.getElementById('addNewProductForm');
            if (addNewProductForm) {
                addNewProductForm.addEventListener('submit', handleAddNewProductSubmit);
            }
        });

        // 4 Image Slots Management for Add New Product
        let newProductImageFiles = { 1: null, 2: null, 3: null, 4: null };
        let selectedNewProductModel = null;

        function triggerNewImageSlot(slotNum) {
            const input = document.getElementById('newImageSlot' + slotNum);
            if (input) input.click();
        }

        function handleNewImageSlotChange(input, slotNum) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                newProductImageFiles[slotNum] = file;

                const url = URL.createObjectURL(file);
                const previewImg = document.getElementById('slotImgPreview' + slotNum);
                const placeholder = document.getElementById('slotPlaceholder' + slotNum);
                const removeBtn = document.getElementById('slotRemoveBtn' + slotNum);
                const container = document.getElementById('slotContainer' + slotNum);

                if (previewImg) {
                    previewImg.src = url;
                    previewImg.style.display = 'block';
                }
                if (placeholder) placeholder.style.display = 'none';
                if (removeBtn) removeBtn.style.display = 'flex';
                if (container) {
                    container.style.borderColor = '#c9aa2e';
                    container.style.borderStyle = 'solid';
                    container.style.boxShadow = '0 0 12px rgba(201, 170, 46, 0.25)';
                }
            }
        }

        function removeNewImageSlot(event, slotNum) {
            if (event) event.stopPropagation();

            const input = document.getElementById('newImageSlot' + slotNum);
            if (input) input.value = '';
            newProductImageFiles[slotNum] = null;

            const previewImg = document.getElementById('slotImgPreview' + slotNum);
            const placeholder = document.getElementById('slotPlaceholder' + slotNum);
            const removeBtn = document.getElementById('slotRemoveBtn' + slotNum);
            const container = document.getElementById('slotContainer' + slotNum);

            if (previewImg) {
                previewImg.src = '';
                previewImg.style.display = 'none';
            }
            if (placeholder) placeholder.style.display = 'flex';
            if (removeBtn) removeBtn.style.display = 'none';
            if (container) {
                container.style.borderColor = 'rgba(124, 99, 0, 0.45)';
                container.style.borderStyle = 'dashed';
                container.style.boxShadow = 'none';
            }
        }

        function resetNewProductSlots() {
            for (let i = 1; i <= 4; i++) {
                removeNewImageSlot(null, i);
            }
            removeNewProductModel();
        }

        // 3D Model Preview Function for Add New Product (Fixes White Cover)
        function previewNewProductModel(input) {
            const previewContainer = document.getElementById('newModelPreview');
            const placeholder = document.getElementById('newModelPlaceholder');
            const modelViewer = document.getElementById('newModelViewer');
            const fileNameSpan = document.getElementById('newModelFileName');
            
            if (input.files && input.files.length > 0) {
                const file = input.files[0];
                const fileName = file.name.toLowerCase();
                
                if (fileName.endsWith('.glb') || fileName.endsWith('.gltf')) {
                    selectedNewProductModel = {
                        file: file,
                        url: URL.createObjectURL(file),
                        name: file.name
                    };

                    if (modelViewer) {
                        modelViewer.src = selectedNewProductModel.url;
                    }
                    if (fileNameSpan) {
                        fileNameSpan.innerHTML = '<i class="fas fa-cube"></i> ' + file.name;
                    }
                    if (placeholder) placeholder.style.display = 'none';
                    if (previewContainer) previewContainer.style.display = 'block';
                } else {
                    alert('Please select a valid 3D model file (.glb or .gltf)');
                    input.value = '';
                }
            }
        }

        function removeNewProductModel() {
            selectedNewProductModel = null;
            const modelInput = document.getElementById('newModel');
            if (modelInput) modelInput.value = '';

            const modelViewer = document.getElementById('newModelViewer');
            if (modelViewer) modelViewer.src = '';

            const previewContainer = document.getElementById('newModelPreview');
            const placeholder = document.getElementById('newModelPlaceholder');

            if (previewContainer) previewContainer.style.display = 'none';
            if (placeholder) placeholder.style.display = 'flex';
        }

        function deleteProduct() {
            const gownId = document.getElementById('editId').value;
            const gownName = document.getElementById('editName').value;

            // Show custom delete confirmation modal with Lottie animation
            showDeleteConfirmationModal(gownId, gownName);
        }

        // Show delete confirmation modal without animation
        function showDeleteConfirmationModal(gownId, gownName) {
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
                    <div id="deleteModalContent" style="background: #2a2a2a; padding: 30px; border-radius: 12px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 10px 30px rgba(0,0,0,0.6);">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(255, 68, 68, 0.15); display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                            <i class="fas fa-trash-alt" style="font-size: 24px; color: #ff4444;"></i>
                        </div>
                        <h3 style="color: #fff; margin: 0 0 10px 0; font-size: 18px;">Delete Product?</h3>
                        <p id="deleteModalMsg" style="color: #999; margin-bottom: 20px; font-size: 13px; line-height: 1.5;">Are you sure you want to delete "${escapeHtml(gownName)}"? This action cannot be undone.</p>
                        <div id="deleteButtons" style="display: flex; gap: 10px; justify-content: center;">
                            <button type="button" id="cancelDeleteBtn" style="background: #555; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">Cancel</button>
                            <button type="button" id="confirmDeleteBtn" style="background: #ff4444; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">Delete</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(deleteModal);

                // Add event listeners
                document.getElementById('cancelDeleteBtn').addEventListener('click', () => {
                    deleteModal.style.display = 'none';
                });
            } else {
                // Update the modal content with current gown name
                const msgEl = document.getElementById('deleteModalMsg') || document.querySelector('#deleteModalContent p');
                if (msgEl) msgEl.textContent = `Are you sure you want to delete "${gownName}"? This action cannot be undone.`;
            }

            // Update event listener with current gownId
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.onclick = () => {
                deleteModal.style.display = 'none';
                sessionStorage.setItem('toast_notification', JSON.stringify({
                    title: 'Product Deleted',
                    message: `"${gownName}" was deleted successfully!`,
                    type: 'success'
                }));
                executeDelete(gownId);
            };

            // Show modal
            deleteModal.style.display = 'flex';
        }

        // Reset delete modal to initial state
        function resetDeleteModal() {
            const deleteModal = document.getElementById('deleteConfirmationModal');
            if (deleteModal) {
                deleteModal.style.display = 'none';
            }
        }

        // Execute the actual delete operation
        function executeDelete(gownId) {
            // Create a form for deletion
            const deleteForm = document.createElement('form');
            deleteForm.method = 'POST';
            deleteForm.style.display = 'none';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_gown';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'gown_id';
            idInput.value = gownId;

            deleteForm.appendChild(actionInput);
            deleteForm.appendChild(idInput);
            document.body.appendChild(deleteForm);
            deleteForm.submit();
        }

        // Edit form validation and submission
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const gownName = document.getElementById('editName').value.trim();
            const gownCategory = document.getElementById('editCategory').value;
            const gownPrice = document.getElementById('editPrice').value;
            const gownPurchasePrice = document.getElementById('editPurchasePrice').value;

            if (!gownName || !gownCategory || !gownPrice || !gownPurchasePrice) {
                alert('Please fill in all required fields (Name, Category, Price, Purchase Price)');
                return false;
            }

            if (parseFloat(gownPrice) <= 0) {
                alert('Price must be greater than 0');
                return false;
            }

            if (parseFloat(gownPurchasePrice) <= 0) {
                alert('Purchase Price must be greater than 0');
                return false;
            }

            // Check if 3D model file is selected
            const modelFile = document.getElementById('editModelFile');
            if (modelFile && modelFile.files && modelFile.files[0]) {
                const f = modelFile.files[0];
                if (f.size > 512 * 1024 * 1024) {
                    alert(`The selected 3D model file is too large (${(f.size / (1024 * 1024)).toFixed(1)} MB). Maximum allowed size is 512 MB.`);
                    return false;
                }
            }

            // Disable submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.75';
                submitBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Updating...`;
            }

            // Prepare form data
            const formData = new FormData(this);

            // Show loading overlay with progress
            showLoadingOverlay();

            let progress = 0;
            const startTime = Date.now();
            const totalDuration = 4000; // 4 seconds base duration
            const updateInterval = 50; // Update every 50ms

            const progressInterval = setInterval(() => {
                const elapsed = Date.now() - startTime;
                progress = Math.min(100, Math.floor((elapsed / totalDuration) * 100));
                updateLoadingProgress(progress);

                if (progress >= 100) {
                    clearInterval(progressInterval);
                }
            }, updateInterval);

            // Submit via AJAX
            fetch('gowns.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                clearInterval(progressInterval);
                updateLoadingProgress(100);
                return response.text();
            })
            .then(data => {
                setTimeout(() => {
                    hideLoadingOverlay();
                    // Check if the response contains success indication
                    if (data.includes('updated successfully') || data.includes('success')) {
                        showNotification('Success', 'Product updated successfully!', 'success');
                        closeEditModal();
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification('Error', 'Failed to update product. Please try again.', 'error');
                        // Re-enable submit button
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.style.opacity = '1';
                            submitBtn.innerHTML = 'Update Product';
                        }
                    }
                }, 500);
            })
            .catch(error => {
                clearInterval(progressInterval);
                hideLoadingOverlay();
                console.error('Error:', error);
                showNotification('Error', 'Error updating product. Please try again.', 'error');
                // Re-enable submit button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.innerHTML = 'Update Product';
                }
            });
        });

        // Search and Filter Functions
        function filterGowns() {
            const searchTerm = document.getElementById('searchName').value.toLowerCase().trim();
            const categoryFilter = document.getElementById('filterCategory').value.trim();
            
            const gownCards = document.querySelectorAll('.gown-card');
            
            gownCards.forEach(card => {
                const gownName = (card.querySelector('h3')?.textContent || '').toLowerCase();
                const gownCategory = (card.getAttribute('data-category') || card.querySelector('.gown-category')?.textContent || '').trim();
                
                let showCard = true;
                
                // Filter by name
                if (searchTerm && !gownName.includes(searchTerm)) {
                    showCard = false;
                }
                
                // Filter by category (only if category is selected)
                if (categoryFilter && gownCategory.toLowerCase() !== categoryFilter.toLowerCase()) {
                    showCard = false;
                }
                
                card.style.display = showCard ? 'block' : 'none';
            });

            // Update visibility of category headers and rows
            const categoryHeaders = document.querySelectorAll('.category-header');
            const categoryRows = document.querySelectorAll('.category-row');
            
            categoryHeaders.forEach(header => {
                const headerCat = (header.getAttribute('data-category') || '').trim().toLowerCase();
                const matchingRow = Array.from(categoryRows).find(row => 
                    (row.getAttribute('data-category') || '').trim().toLowerCase() === headerCat
                );
                
                if (categoryFilter && headerCat !== categoryFilter.toLowerCase()) {
                    header.style.display = 'none';
                    if (matchingRow) matchingRow.style.display = 'none';
                } else {
                    if (matchingRow) {
                        const visibleCards = matchingRow.querySelectorAll('.gown-card:not([style*="display: none"])');
                        const totalCards = matchingRow.querySelectorAll('.gown-card');
                        
                        if (searchTerm && totalCards.length > 0 && visibleCards.length === 0) {
                            header.style.display = 'none';
                            matchingRow.style.display = 'none';
                        } else {
                            header.style.display = '';
                            matchingRow.style.display = '';
                        }
                    } else {
                        header.style.display = categoryFilter ? 'none' : '';
                    }
                }
            });
        }
        
        function clearFilters() {
            document.getElementById('searchName').value = '';
            document.getElementById('filterCategory').value = '';
            filterGowns();
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const addModal = document.getElementById('addGownModal');
            const editModal = document.getElementById('editGownModal');
            
            if (e.target === addModal) {
                closeAddGownModal();
            }
            if (e.target === editModal) {
                closeEditGownModal();
            }
        });

        // Loading Overlay Functions
        let loadingOverlay = null;
        let loadingProgressInterval = null;

        function showLoadingOverlay() {
            // Create loading overlay if it doesn't exist
            if (!loadingOverlay) {
                loadingOverlay = document.createElement('div');
                loadingOverlay.id = 'loadingOverlay';
                loadingOverlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.85);
                    backdrop-filter: blur(8px);
                    display: none;
                    align-items: center;
                    justify-content: center;
                    z-index: 99999;
                `;
                loadingOverlay.innerHTML = `
                    <div style="text-align: center; color: #fff;">
                        <div id="loadingProgress" style="font-size: 32px; font-weight: 700; color: #ffd700;">0%</div>
                    </div>
                `;
                document.body.appendChild(loadingOverlay);
            }

            // Reset progress and show overlay
            document.getElementById('loadingProgress').textContent = '0%';
            loadingOverlay.style.display = 'flex';
        }

        function updateLoadingProgress(progress) {
            if (loadingOverlay) {
                const progressEl = document.getElementById('loadingProgress');
                if (progressEl) {
                    progressEl.textContent = progress + '%';
                }
            }
        }

        function hideLoadingOverlay() {
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
            }
            if (loadingProgressInterval) {
                clearInterval(loadingProgressInterval);
                loadingProgressInterval = null;
            }
        }

        // Custom Slide-in Notification Toast Handlers
        let notificationTimeout = null;
        let notificationCallback = null;

        function showNotification(title, message, type = 'success', duration = 4000, onOk = null) {
            const toast = document.getElementById('customNotification');
            if (!toast) return;

            notificationCallback = onOk;

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

        function hideNotification(isOk = true) {
            const toast = document.getElementById('customNotification');
            if (toast) toast.classList.remove('show');
            if (notificationTimeout) clearTimeout(notificationTimeout);

            if (isOk && notificationCallback) {
                const cb = notificationCallback;
                notificationCallback = null;
                cb();
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        // Automatic trigger on page load for session messages or stored toast
        document.addEventListener('DOMContentLoaded', () => {
            // ── Mobile double-tap to open Edit Product ──────────────────────────
            // ondblclick is unreliable on touch screens; this detects double-tap via
            // touchend events and calls openEditModal() when two taps occur within 350ms.
            (function attachDoubleTap() {
                let lastTapCard = null;
                let lastTapTime = 0;

                function bindDoubleTap(card) {
                    card.addEventListener('touchend', function(e) {
                        // If the card's pointer-events are disabled (panel open), skip
                        if (card.style.pointerEvents === 'none') return;

                        const now = Date.now();
                        const isSameCard = (lastTapCard === card);
                        const isDoubleTap = isSameCard && (now - lastTapTime) < 350;

                        if (isDoubleTap) {
                            e.preventDefault(); // Prevent zoom / 300ms delay
                            lastTapCard = null;
                            lastTapTime = 0;
                            const gownId = card.getAttribute('data-gown-id');
                            if (gownId) openEditModal(parseInt(gownId, 10));
                        } else {
                            lastTapCard = card;
                            lastTapTime = now;
                        }
                    }, { passive: false });
                }

                // Bind to all currently rendered cards
                document.querySelectorAll('.gown-card').forEach(bindDoubleTap);

                // Expose so new cards can also be bound (if ever added dynamically)
                window.bindGownCardDoubleTap = bindDoubleTap;
            })();
            // ────────────────────────────────────────────────────────────────────

            const storedToast = sessionStorage.getItem('toast_notification');
            if (storedToast) {
                try {
                    const parsed = JSON.parse(storedToast);
                    sessionStorage.removeItem('toast_notification');
                    showNotification(parsed.title, parsed.message, parsed.type || 'success');
                } catch(e) {
                    sessionStorage.removeItem('toast_notification');
                }
            } else {
                <?php if (isset($_SESSION['success_message'])): ?>
                    showNotification('Success', '<?php echo addslashes($_SESSION['success_message']); ?>', 'success');
                    <?php unset($_SESSION['success_message']); ?>
                <?php elseif (isset($_SESSION['error_message'])): ?>
                    showNotification('Error', '<?php echo addslashes($_SESSION['error_message']); ?>', 'error');
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
            }
        });
    </script>

    <!-- Big Categories Management Modal -->
    <div id="categoriesModal" class="categories-modal-overlay" style="display: none;">
        <div class="categories-modal-container">
            <div class="categories-modal-header">
                <div class="categories-modal-title">
                    <i class="fas fa-tags" style="color: #ffd700;"></i>
                    <span>Categories Management</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="categories-modal-close-btn" onclick="closeCategoriesModal()" aria-label="Close" title="Close Categories Modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="categories-modal-body">
                <iframe id="categoriesIframe" src="about:blank" frameborder="0" style="width: 100%; height: 100%; border: none; background: #14151a;" title="Categories Management"></iframe>
            </div>
        </div>
    </div>

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
