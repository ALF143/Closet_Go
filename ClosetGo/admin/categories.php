<?php
session_start();
require_once __DIR__ . '/../database/db_config.php';

// Handle API requests
if (isset($_GET['action']) && $_GET['action'] == 'getAllCategories') {
    header('Content-Type: application/json');
    
    $categoryManager = new CategoryManager();
    $categories = $categoryManager->getAllCategories();
    
    echo json_encode($categories);
    exit();
}

// Handle Add Category form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_category') {
    header('Content-Type: application/json');

    $categoryManager = new CategoryManager();

    $categoryName = trim($_POST['categoryName'] ?? '');
    $sizeLabel1 = $_POST['itemSize1'] ?? null;
    $sizeLabel2 = $_POST['itemSize2'] ?? null;
    $sizeLabel3 = $_POST['itemSize3'] ?? null;
    $sizeLabel4 = $_POST['itemSize4'] ?? null;
    $sizeLabel5 = $_POST['itemSize5'] ?? null;
    $sizeLabel6 = $_POST['itemSize6'] ?? null;
    $sizeLabel7 = $_POST['itemSize7'] ?? null;
    $sizeLabel8 = $_POST['itemSize8'] ?? null;
    $sizeLabel9 = $_POST['itemSize9'] ?? null;
    $sizeLabel10 = $_POST['itemSize10'] ?? null;
    $imagePath = null;
    $sizeGuideImagePath = null;

    if (empty($categoryName)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required.']);
        exit();
    }

    if ($categoryManager->categoryNameExists($categoryName)) {
        echo json_encode(['success' => false, 'message' => 'A category with this name already exists.']);
        exit();
    }

    // Handle image upload
    $uploadDir = __DIR__ . '/../uploads/categories/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    if (isset($_FILES['categoryImage']) && $_FILES['categoryImage']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['categoryImage']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Category image upload failed (error code ' . $_FILES['categoryImage']['error'] . ').']);
            exit();
        }
        $ext = strtolower(pathinfo($_FILES['categoryImage']['name'], PATHINFO_EXTENSION));
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['categoryImage']['name'], PATHINFO_FILENAME));
        $imageName = time() . '_' . $safeName . '.' . $ext;
        if (move_uploaded_file($_FILES['categoryImage']['tmp_name'], $uploadDir . $imageName)) {
            $imagePath = 'uploads/categories/' . $imageName;
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not save Category Image to disk. Please check directory permissions.']);
            exit();
        }
    }

    // Handle size guide image upload
    if (isset($_FILES['sizeGuideImage']) && $_FILES['sizeGuideImage']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['sizeGuideImage']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Size Guide image upload failed (error code ' . $_FILES['sizeGuideImage']['error'] . ').']);
            exit();
        }
        $ext = strtolower(pathinfo($_FILES['sizeGuideImage']['name'], PATHINFO_EXTENSION));
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['sizeGuideImage']['name'], PATHINFO_FILENAME));
        $sizeGuideImageName = time() . '_sizeguide_' . $safeName . '.' . $ext;
        if (move_uploaded_file($_FILES['sizeGuideImage']['tmp_name'], $uploadDir . $sizeGuideImageName)) {
            $sizeGuideImagePath = 'uploads/categories/' . $sizeGuideImageName;
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not save Size Guide Image to disk. Please check directory permissions.']);
            exit();
        }
    }

    $result = $categoryManager->addCategory($categoryName, $imagePath, $sizeLabel1, $sizeLabel2, $sizeLabel3, $sizeLabel4, $sizeLabel5, $sizeLabel6, $sizeLabel7, $sizeLabel8, $sizeLabel9, $sizeLabel10, $sizeGuideImagePath);

    echo json_encode(['success' => (bool)$result]);
    exit();
}

// Handle Update Category form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_category') {
    header('Content-Type: application/json');

    $categoryManager = new CategoryManager();

    $categoryId = isset($_POST['categoryId']) && $_POST['categoryId'] !== '' ? intval($_POST['categoryId']) : 0;
    $categoryName = trim($_POST['categoryName'] ?? '');
    $oldCategoryName = trim($_POST['oldCategoryName'] ?? '');
    $sizeLabel1 = $_POST['itemSize1'] ?? null;
    $sizeLabel2 = $_POST['itemSize2'] ?? null;
    $sizeLabel3 = $_POST['itemSize3'] ?? null;
    $sizeLabel4 = $_POST['itemSize4'] ?? null;
    $sizeLabel5 = $_POST['itemSize5'] ?? null;
    $sizeLabel6 = $_POST['itemSize6'] ?? null;
    $sizeLabel7 = $_POST['itemSize7'] ?? null;
    $sizeLabel8 = $_POST['itemSize8'] ?? null;
    $sizeLabel9 = $_POST['itemSize9'] ?? null;
    $sizeLabel10 = $_POST['itemSize10'] ?? null;
    $removeCatImage = !empty($_POST['removeCategoryImage']) && $_POST['removeCategoryImage'] == '1';
    $removeSizeGuide = !empty($_POST['removeSizeGuideImage']) && $_POST['removeSizeGuideImage'] == '1';
    $imagePath = null;
    $sizeGuideImagePath = null;

    if (empty($categoryName)) {
        echo json_encode(['success' => false, 'message' => 'Category name cannot be empty.']);
        exit();
    }

    $uploadDir = __DIR__ . '/../uploads/categories/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    // Handle image upload
    if (isset($_FILES['categoryImage']) && $_FILES['categoryImage']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['categoryImage']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Category Image upload failed (error code ' . $_FILES['categoryImage']['error'] . ').']);
            exit();
        }
        $ext = strtolower(pathinfo($_FILES['categoryImage']['name'], PATHINFO_EXTENSION));
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['categoryImage']['name'], PATHINFO_FILENAME));
        $imageName = time() . '_' . $safeName . '.' . $ext;
        if (move_uploaded_file($_FILES['categoryImage']['tmp_name'], $uploadDir . $imageName)) {
            $imagePath = 'uploads/categories/' . $imageName;
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not save Category Image to disk. Please check directory permissions.']);
            exit();
        }
    }

    // Handle size guide image upload
    if (isset($_FILES['sizeGuideImage']) && $_FILES['sizeGuideImage']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['sizeGuideImage']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Size Guide Image upload failed (error code ' . $_FILES['sizeGuideImage']['error'] . ').']);
            exit();
        }
        $ext = strtolower(pathinfo($_FILES['sizeGuideImage']['name'], PATHINFO_EXTENSION));
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['sizeGuideImage']['name'], PATHINFO_FILENAME));
        $sizeGuideImageName = time() . '_sizeguide_' . $safeName . '.' . $ext;
        if (move_uploaded_file($_FILES['sizeGuideImage']['tmp_name'], $uploadDir . $sizeGuideImageName)) {
            $sizeGuideImagePath = 'uploads/categories/' . $sizeGuideImageName;
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not save Size Guide Image to disk. Please check directory permissions.']);
            exit();
        }
    }

    $result = $categoryManager->updateCategory($categoryId, $categoryName, $imagePath, $sizeLabel1, $sizeLabel2, $sizeLabel3, $sizeLabel4, $sizeLabel5, $sizeLabel6, $sizeLabel7, $sizeLabel8, $sizeLabel9, $sizeLabel10, $sizeGuideImagePath, $oldCategoryName, $removeCatImage, $removeSizeGuide);

    echo json_encode($result);
    exit();
}

// Handle Delete Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_category') {
    header('Content-Type: application/json');
    
    $categoryManager = new CategoryManager();
    $categoryId = isset($_POST['categoryId']) ? intval($_POST['categoryId']) : 0;
    
    $result = $categoryManager->deleteCategory($categoryId);
    
    echo json_encode($result);
    exit();
}

// Category Manager Class
class CategoryManager {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        
        // Create categories table if it doesn't exist
        $this->createCategoriesTable();
    }
    
    private function getConnection() {
        return $this->db->getConnection();
    }
    
    private function createCategoriesTable() {
        $conn = $this->getConnection();
        $sql = "CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(255) NOT NULL UNIQUE,
            category_image VARCHAR(255),
            size_label_1 VARCHAR(255),
            size_label_2 VARCHAR(255),
            size_label_3 VARCHAR(255),
            size_label_4 VARCHAR(255),
            size_label_5 VARCHAR(255),
            size_label_6 VARCHAR(255),
            size_label_7 VARCHAR(255),
            size_label_8 VARCHAR(255),
            size_label_9 VARCHAR(255),
            size_label_10 VARCHAR(255),
            size_guide_image VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        $conn->query($sql);

        // Add columns if they don't exist (for existing tables)
        $this->addColumnIfNotExists('categories', 'size_label_1', "VARCHAR(255)");
        $this->addColumnIfNotExists('categories', 'size_label_2', "VARCHAR(255)");
        $this->addColumnIfNotExists('categories', 'size_label_3', "VARCHAR(255)");
        $this->addColumnIfNotExists('categories', 'size_label_4', "VARCHAR(255)");
        $this->addColumnIfNotExists('categories', 'size_label_5', "VARCHAR(255)");
        $this->addColumnIfNotExists('categories', 'size_label_6', "VARCHAR(255)");
        $this->addColumnIfNotExists('categories', 'size_label_7', "VARCHAR(255)");
        $this->addColumnIfNotExists('categories', 'size_label_8', "VARCHAR(255)");
        $this->addColumnIfNotExists('categories', 'size_label_9', "VARCHAR(255)");
        $this->addColumnIfNotExists('categories', 'size_label_10', "VARCHAR(255)");
        $this->addColumnIfNotExists('categories', 'size_guide_image', "VARCHAR(255)");
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
    
    public function addCategory($categoryName, $imagePath = null, $sizeLabel1 = null, $sizeLabel2 = null, $sizeLabel3 = null, $sizeLabel4 = null, $sizeLabel5 = null, $sizeLabel6 = null, $sizeLabel7 = null, $sizeLabel8 = null, $sizeLabel9 = null, $sizeLabel10 = null, $sizeGuideImagePath = null) {
        $conn = $this->getConnection();
        $sql = "INSERT INTO categories (category_name, category_image, size_label_1, size_label_2, size_label_3, size_label_4, size_label_5, size_label_6, size_label_7, size_label_8, size_label_9, size_label_10, size_guide_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $res = $stmt->execute([$categoryName, $imagePath, $sizeLabel1, $sizeLabel2, $sizeLabel3, $sizeLabel4, $sizeLabel5, $sizeLabel6, $sizeLabel7, $sizeLabel8, $sizeLabel9, $sizeLabel10, $sizeGuideImagePath]);

        if ($res) {
            // Also normalize any gowns that had this category name with slight case or whitespace variation
            $stmtGowns = $conn->prepare("UPDATE gowns SET category = ? WHERE LOWER(TRIM(category)) = LOWER(TRIM(?))");
            $stmtGowns->execute([$categoryName, $categoryName]);
        }

        return $res;
    }

    public function getCategoryByName($categoryName) {
        $conn = $this->getConnection();
        $stmt = $conn->prepare("SELECT * FROM categories WHERE LOWER(TRIM(category_name)) = LOWER(TRIM(?)) LIMIT 1");
        $stmt->execute([$categoryName]);
        $res = $stmt->get_result();
        return $res ? $res->fetch_assoc() : null;
    }

    public function categoryNameExists($categoryName, $excludeId = 0) {
        $conn = $this->getConnection();
        if ($excludeId > 0) {
            $stmt = $conn->prepare("SELECT id FROM categories WHERE LOWER(TRIM(category_name)) = LOWER(TRIM(?)) AND id != ?");
            $stmt->execute([$categoryName, $excludeId]);
        } else {
            $stmt = $conn->prepare("SELECT id FROM categories WHERE LOWER(TRIM(category_name)) = LOWER(TRIM(?))");
            $stmt->execute([$categoryName]);
        }
        $res = $stmt->get_result();
        return $res && $res->num_rows > 0;
    }
    
    public function updateCategory($categoryId, $categoryName, $imagePath = null, $sizeLabel1 = null, $sizeLabel2 = null, $sizeLabel3 = null, $sizeLabel4 = null, $sizeLabel5 = null, $sizeLabel6 = null, $sizeLabel7 = null, $sizeLabel8 = null, $sizeLabel9 = null, $sizeLabel10 = null, $sizeGuideImagePath = null, $oldCategoryName = null, $removeCatImage = false, $removeSizeGuide = false) {
        $conn = $this->getConnection();

        $targetId = intval($categoryId);
        $oldCat = !empty($oldCategoryName) ? trim($oldCategoryName) : '';
        $newCat = trim($categoryName);

        if (empty($newCat)) {
            return ['success' => false, 'message' => 'Category name cannot be empty.'];
        }

        // Verify or retrieve old category name from DB if targetId is given
        if ($targetId > 0) {
            $catRecord = $this->getCategoryById($targetId);
            if ($catRecord) {
                if (empty($oldCat)) {
                    $oldCat = trim($catRecord['category_name']);
                }
            }
        }
        
        // If targetId is not known or 0, try resolving by old category name or new category name
        if ($targetId <= 0 && !empty($oldCat)) {
            $catRecord = $this->getCategoryByName($oldCat);
            if ($catRecord) {
                $targetId = intval($catRecord['id']);
            }
        }
        if ($targetId <= 0 && !empty($newCat)) {
            $catRecord = $this->getCategoryByName($newCat);
            if ($catRecord) {
                $targetId = intval($catRecord['id']);
            }
        }

        // Check for duplicate category name on other rows
        if ($this->categoryNameExists($newCat, $targetId)) {
            return ['success' => false, 'message' => 'A category with this name already exists.'];
        }

        // Update existing category or insert new record
        if ($targetId > 0) {
            $updates = [];
            $params = [];

            $updates[] = "category_name = ?";
            $params[] = $newCat;

            if ($removeCatImage) {
                $updates[] = "category_image = NULL";
            } elseif ($imagePath !== null) {
                $updates[] = "category_image = ?";
                $params[] = $imagePath;
            }

            if ($sizeLabel1 !== null) {
                $updates[] = "size_label_1 = ?";
                $params[] = $sizeLabel1;
            }
            if ($sizeLabel2 !== null) {
                $updates[] = "size_label_2 = ?";
                $params[] = $sizeLabel2;
            }
            if ($sizeLabel3 !== null) {
                $updates[] = "size_label_3 = ?";
                $params[] = $sizeLabel3;
            }
            if ($sizeLabel4 !== null) {
                $updates[] = "size_label_4 = ?";
                $params[] = $sizeLabel4;
            }
            if ($sizeLabel5 !== null) {
                $updates[] = "size_label_5 = ?";
                $params[] = $sizeLabel5;
            }
            if ($sizeLabel6 !== null) {
                $updates[] = "size_label_6 = ?";
                $params[] = $sizeLabel6;
            }
            if ($sizeLabel7 !== null) {
                $updates[] = "size_label_7 = ?";
                $params[] = $sizeLabel7;
            }
            if ($sizeLabel8 !== null) {
                $updates[] = "size_label_8 = ?";
                $params[] = $sizeLabel8;
            }
            if ($sizeLabel9 !== null) {
                $updates[] = "size_label_9 = ?";
                $params[] = $sizeLabel9;
            }
            if ($sizeLabel10 !== null) {
                $updates[] = "size_label_10 = ?";
                $params[] = $sizeLabel10;
            }

            if ($removeSizeGuide) {
                $updates[] = "size_guide_image = NULL";
            } elseif ($sizeGuideImagePath !== null) {
                $updates[] = "size_guide_image = ?";
                $params[] = $sizeGuideImagePath;
            }

            $sql = "UPDATE categories SET " . implode(', ', $updates) . " WHERE id = ?";
            $params[] = $targetId;
            $stmt = $conn->prepare($sql);
            if (!$stmt->execute($params)) {
                return ['success' => false, 'message' => 'Failed to update category record: ' . $conn->error];
            }
        } else {
            $insertRes = $this->addCategory($newCat, $imagePath, $sizeLabel1, $sizeLabel2, $sizeLabel3, $sizeLabel4, $sizeLabel5, $sizeLabel6, $sizeLabel7, $sizeLabel8, $sizeLabel9, $sizeLabel10, $sizeGuideImagePath);
            if (!$insertRes) {
                return ['success' => false, 'message' => 'Failed to create category record: ' . $conn->error];
            }
        }

        // CRITICAL: Keep products attached to this category when category name changes!
        // Update gowns, saved_products, and reserve_products tables in the database
        if (!empty($oldCat) && strcasecmp($oldCat, $newCat) !== 0) {
            // Update gowns
            $stmtGowns = $conn->prepare("UPDATE gowns SET category = ? WHERE LOWER(TRIM(category)) = LOWER(TRIM(?))");
            $stmtGowns->execute([$newCat, $oldCat]);

            // Update saved_products
            $stmtSaved = $conn->prepare("UPDATE saved_products SET product_category = ? WHERE LOWER(TRIM(product_category)) = LOWER(TRIM(?))");
            $stmtSaved->execute([$newCat, $oldCat]);

            // Update reserve_products
            $stmtReserve = $conn->prepare("UPDATE reserve_products SET category = ? WHERE LOWER(TRIM(category)) = LOWER(TRIM(?))");
            $stmtReserve->execute([$newCat, $oldCat]);
        } elseif (!empty($oldCat) && $oldCat !== $newCat) {
            // Exact casing update in gowns
            $stmtGowns = $conn->prepare("UPDATE gowns SET category = ? WHERE LOWER(TRIM(category)) = LOWER(TRIM(?))");
            $stmtGowns->execute([$newCat, $oldCat]);
        }

        return ['success' => true];
    }
    
    public function deleteCategory($categoryId) {
        $conn = $this->getConnection();
        $cat = $this->getCategoryById($categoryId);
        if ($cat) {
            $catName = trim($cat['category_name']);
            // Check if there are any products attached to this category
            $stmtCheck = $conn->prepare("SELECT COUNT(*) as cnt FROM gowns WHERE LOWER(TRIM(category)) = LOWER(TRIM(?))");
            $stmtCheck->execute([$catName]);
            $res = $stmtCheck->get_result()->fetch_assoc();
            if ($res && intval($res['cnt']) > 0) {
                return ['success' => false, 'message' => 'Cannot delete "' . $catName . '" because ' . $res['cnt'] . ' product(s) belong to it. Please reassign or delete the products first.'];
            }
        }
        $sql = "DELETE FROM categories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $res = $stmt->execute([$categoryId]);
        return ['success' => (bool)$res];
    }
    
    public function getCategoryById($categoryId) {
        $conn = $this->getConnection();
        $sql = "SELECT * FROM categories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$categoryId]);
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - Gown Rental Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-modern.css">
    <link rel="stylesheet" href="../css/admin_mobile_css.css">
    <style>
        /* Custom Slide-in Notification Toast (Top Right with OK button) */
        .custom-slide-toast {
            position: fixed;
            top: 24px;
            right: -450px;
            z-index: 999999;
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
            flex-shrink: 0;
            letter-spacing: 0.5px;
        }

        .custom-toast-ok-btn:hover {
            background: #ffea75;
            transform: scale(1.05);
        }

        /* Delete confirmation modal backdrop */
        .confirm-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            z-index: 100000;
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
            background: #1e1e24;
            border: 1px solid rgba(244, 67, 54, 0.4);
            border-radius: 14px;
            padding: 24px;
            width: 90%;
            max-width: 420px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.8), 0 0 25px rgba(244, 67, 54, 0.15);
            text-align: center;
            color: #fff;
        }

        .confirm-modal-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(244, 67, 54, 0.15);
            border: 1px solid rgba(244, 67, 54, 0.35);
            color: #f44336;
            font-size: 1.5rem;
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
        }

        .confirm-modal-desc {
            font-size: 0.85rem;
            color: #bbb;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .confirm-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .confirm-btn-cancel {
            padding: 9px 20px;
            background: #2b2d35;
            color: #ccc;
            border: 1px solid #444;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .confirm-btn-cancel:hover {
            background: #353842;
            color: #fff;
        }

        .confirm-btn-delete {
            padding: 9px 22px;
            background: #f44336;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .confirm-btn-delete:hover {
            background: #d32f2f;
            transform: scale(1.03);
        }
        /* ── categories.php mobile page fixes ── */
        @media (max-width: 768px) {
            html, body {
                overflow-x: hidden !important;
                width: 100% !important;
            }
            .admin-container {
                width: 100% !important;
                max-width: 100vw !important;
                overflow-x: hidden !important;
            }
            .main-content {
                width: 100% !important;
                max-width: 100vw !important;
                padding: 0 !important;
                margin: 0 !important;
                overflow-x: hidden !important;
                box-sizing: border-box !important;
            }
            #categoriesGrid {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important;
            }
            /* Add Category button full-width */
            .add-new-product-btn {
                width: 100% !important;
                box-sizing: border-box !important;
            }
            /* Slide panels: full width, no side cut */
            .slide-panel {
                width: 100% !important;
                max-width: 100vw !important;
            }
            .slide-panel-body {
                overflow-x: hidden !important;
            }
            .size-inputs {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 6px !important;
            }
            .form-group input[type="text"],
            .form-group input[type="file"] {
                font-size: 0.85rem !important;
                padding: 8px 10px !important;
            }
            .form-actions {
                flex-direction: column !important;
                gap: 8px !important;
            }
            .btn-primary, .btn-secondary {
                width: 100% !important;
                padding: 10px !important;
                font-size: 0.88rem !important;
            }
        }
        @media (max-width: 480px) {
            .size-inputs {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        /* ── Categories Page Toolbar & Grid Alignment ── */
        .categories-page .search-filter-section-top {
            position: relative !important;
            top: auto !important;
            right: auto !important;
            left: auto !important;
            width: 100% !important;
            box-sizing: border-box !important;
            padding: 10px 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            border: none !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            margin: 0 0 10px 0 !important;
        }

        .categories-page .search-filter-row {
            display: flex !important;
            justify-content: flex-end !important;
            align-items: center !important;
            width: 100% !important;
            margin: 0 !important;
            gap: 0 !important;
        }

        .categories-page .search-filter-section-top .form-group {
            margin: 0 !important;
            padding: 0 !important;
            flex: 0 0 auto !important;
            width: auto !important;
            max-width: none !important;
            display: flex !important;
            justify-content: flex-end !important;
        }

        .categories-page .categories-grid {
            padding: 0 0 30px 0 !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        @media (min-width: 769px) {
            .categories-page .add-new-product-btn {
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
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                text-align: center !important;
                cursor: pointer !important;
                white-space: nowrap !important;
                transition: all 0.3s ease !important;
                margin: 0 !important;
            }

            .categories-page .add-new-product-btn:hover {
                background: #1a1a1a !important;
                box-shadow: inset 0 0 12px rgba(212, 175, 55, 0.6) !important;
                transform: translateY(-1px);
            }
        }

        /* ─── Big Modal Styles for Add / Edit Category (Matching Categories Management) ─── */
        .cat-modal-overlay {
            position: fixed;
            inset: 0;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100% !important;
            height: 100% !important;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px !important;
            box-sizing: border-box;
            animation: catModalFadeIn 0.22s ease-out;
        }

        @keyframes catModalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .cat-modal-container {
            width: calc(100% - 24px) !important;
            max-width: 1550px !important;
            height: calc(100% - 24px) !important;
            max-height: calc(100vh - 24px) !important;
            background: #14151a;
            border: 1.5px solid #7c6300;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.95), 0 0 28px rgba(212, 175, 55, 0.28);
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

        .cat-modal-header {
            padding: 14px 22px;
            background: #1a1a1f;
            border-bottom: 1px solid rgba(212, 175, 55, 0.3);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .cat-modal-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.15rem;
            font-weight: 700;
            color: #ffd700;
            letter-spacing: 0.5px;
        }

        .cat-modal-close-btn {
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

        .cat-modal-close-btn:hover {
            color: #ffd700;
            border-color: #ffd700;
            background: rgba(212, 175, 55, 0.15);
            transform: rotate(90deg);
        }

        .cat-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 28px 36px;
            box-sizing: border-box;
        }

        .cat-form-group {
            margin-bottom: 0;
        }

        .cat-form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #d4af37;
            letter-spacing: 0.3px;
        }

        .cat-form-group input[type="text"] {
            width: 100%;
            height: 44px;
            background: #0d0e12;
            border: 1px solid rgba(212, 175, 55, 0.35);
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.95rem;
            padding: 0 16px;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .cat-form-group input[type="text"]:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.35);
        }

        .cat-form-group input[type="file"] {
            width: 100%;
            padding: 10px 14px;
            background: #0d0e12;
            border: 1px dashed rgba(212, 175, 55, 0.4);
            border-radius: 8px;
            color: #bbb;
            font-size: 0.88rem;
            box-sizing: border-box;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .cat-form-group input[type="file"]:hover {
            border-color: #ffd700;
        }

        .cat-sizes-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
        }

        .cat-size-item {
            position: relative;
            display: flex;
            align-items: center;
        }

        .cat-size-badge {
            position: absolute;
            left: 10px;
            color: #ffd700;
            font-size: 0.72rem;
            font-weight: 700;
            pointer-events: none;
            opacity: 0.75;
        }

        .cat-size-item input[type="text"] {
            padding-left: 26px !important;
            height: 40px !important;
            font-size: 0.88rem !important;
        }

        .cat-media-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .cat-image-preview {
            margin-top: 10px;
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.45);
            border: 1px dashed rgba(212, 175, 55, 0.35);
            border-radius: 8px;
            overflow: hidden;
            padding: 10px;
            box-sizing: border-box;
            position: relative;
        }

        .cat-preview-box {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .cat-preview-box img, .cat-image-preview img, .cat-image-preview .preview-image {
            max-height: 130px;
            max-width: 100%;
            object-fit: contain;
            border-radius: 6px;
            border: 1px solid rgba(212, 175, 55, 0.25);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
            background: #111;
        }

        .cat-preview-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0 4px;
            box-sizing: border-box;
            gap: 8px;
        }

        .cat-preview-info {
            font-size: 0.78rem;
            color: #d4af37;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 65%;
            font-weight: 500;
        }

        .btn-remove-cat-img {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.4);
            padding: 4px 9px;
            border-radius: 5px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-remove-cat-img:hover {
            background: #ef4444;
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-undo-remove {
            background: rgba(212, 175, 55, 0.2);
            color: #ffd700;
            border: 1px solid rgba(212, 175, 55, 0.5);
            padding: 4px 9px;
            border-radius: 5px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-undo-remove:hover {
            background: #ffd700;
            color: #000;
        }

        .cat-preview-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: #666;
            font-size: 0.82rem;
            padding: 14px 0;
            text-align: center;
        }

        .cat-preview-empty-state i {
            font-size: 1.8rem;
            color: #444;
        }

        /* ── Size Guide badge on Category card ── */
        .cat-card-size-guide-row {
            margin: 6px 0 10px 0;
            display: flex;
            align-items: center;
        }

        .btn-guide-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-guide-tag.has-guide {
            background: rgba(212, 175, 55, 0.18);
            color: #ffd700;
            border: 1px solid rgba(212, 175, 55, 0.45);
        }

        .btn-guide-tag.has-guide:hover {
            background: rgba(212, 175, 55, 0.35);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.25);
        }

        .btn-guide-tag.no-guide {
            background: rgba(255, 255, 255, 0.05);
            color: #777;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: default;
        }

        .cat-modal-footer {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid rgba(212, 175, 55, 0.2);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-cancel-modal {
            padding: 9px 24px;
            background: #23242a;
            color: #ccc;
            border: 1px solid #444;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancel-modal:hover {
            background: #2e3038;
            color: #fff;
            border-color: #666;
        }

        .btn-submit-modal {
            padding: 9px 28px;
            background: #ffd700;
            color: #111;
            border: 1px solid #ffd700;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.25);
            transition: all 0.2s;
        }

        .btn-submit-modal:hover {
            background: #ffea75;
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
            transform: translateY(-1px);
        }

        @media (max-width: 820px) {
            .cat-sizes-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .cat-media-grid {
                grid-template-columns: 1fr;
            }
            .cat-modal-container {
                width: 95vw;
                max-height: 94vh;
            }
        }

        <?php if (isset($_GET['embed']) || isset($_GET['modal'])): ?>
        html, body {
            background: #14151a !important;
            height: 100% !important;
            overflow-x: hidden !important;
        }
        .admin-container {
            padding: 0 !important;
            margin: 0 !important;
            display: block !important;
            width: 100% !important;
            min-height: 100% !important;
        }
        .main-content, .main-content.categories-page {
            margin-left: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            padding: 16px 20px 40px 20px !important;
            box-sizing: border-box !important;
        }
        .sidebar, #mobileMenuToggle, #sidebarOverlay {
            display: none !important;
        }
        .search-filter-section-top {
            position: sticky !important;
            top: 0 !important;
            z-index: 10 !important;
            background: #14151a !important;
            padding: 10px 0 !important;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <div class="admin-container">
        <?php if (!isset($_GET['embed']) && !isset($_GET['modal'])): ?>
            <?php include 'sidebar.php'; ?>
        <?php endif; ?>
        
        <div class="main-content categories-page">
            <div class="search-filter-section-top">
                <div class="search-filter-row">
                    <div class="form-group">
                        <button type="button" class="add-new-product-btn" onclick="openAddCategoryPanel()">Add Category</button>
                    </div>
                </div>
            </div>

            <div class="categories-grid" id="categoriesGrid">
                <!-- Categories will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Add Category Big Modal -->
    <div id="addCategoryPanel" class="cat-modal-overlay" style="display: none;">
        <div class="cat-modal-container">
            <div class="cat-modal-header">
                <div class="cat-modal-title">
                    <i class="fas fa-tags" style="color: #ffd700;"></i>
                    <span>Add New Category</span>
                </div>
                <button type="button" class="cat-modal-close-btn" onclick="closeAddCategoryPanel()" aria-label="Close" title="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="cat-modal-body">
                <form id="addCategoryForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_category">

                    <div class="cat-form-group">
                        <label for="categoryName">Category Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="categoryName" name="categoryName" placeholder="Enter category name..." required>
                    </div>

                    <div class="cat-form-group" style="margin-top: 18px;">
                        <label>Item Name Sizes <small style="color: #999; font-weight: normal; margin-left: 6px;">(Measurement Labels)</small></label>
                        <div class="cat-sizes-grid">
                            <div class="cat-size-item"><span class="cat-size-badge">1</span><input type="text" id="itemSize1" name="itemSize1" placeholder="Size 1 (e.g. Bust)"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">2</span><input type="text" id="itemSize2" name="itemSize2" placeholder="Size 2 (e.g. Waist)"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">3</span><input type="text" id="itemSize3" name="itemSize3" placeholder="Size 3 (e.g. Hips)"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">4</span><input type="text" id="itemSize4" name="itemSize4" placeholder="Size 4 (e.g. Length)"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">5</span><input type="text" id="itemSize5" name="itemSize5" placeholder="Size 5"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">6</span><input type="text" id="itemSize6" name="itemSize6" placeholder="Size 6"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">7</span><input type="text" id="itemSize7" name="itemSize7" placeholder="Size 7"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">8</span><input type="text" id="itemSize8" name="itemSize8" placeholder="Size 8"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">9</span><input type="text" id="itemSize9" name="itemSize9" placeholder="Size 9"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">10</span><input type="text" id="itemSize10" name="itemSize10" placeholder="Size 10"></div>
                        </div>
                    </div>

                    <div class="cat-media-grid" style="margin-top: 18px;">
                        <div class="cat-form-group">
                            <label for="sizeGuideImage">Size Guide Image</label>
                            <input type="file" id="sizeGuideImage" name="sizeGuideImage" accept="image/*">
                            <div class="cat-image-preview" id="addSizeGuidePreview"></div>
                        </div>

                        <div class="cat-form-group">
                            <label for="categoryImage">Category Image</label>
                            <input type="file" id="categoryImage" name="categoryImage" accept="image/*">
                            <div class="cat-image-preview" id="addImagePreview"></div>
                        </div>
                    </div>

                    <div class="cat-modal-footer">
                        <button type="submit" class="btn-submit-modal">Add Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Big Modal -->
    <div id="editCategoryPanel" class="cat-modal-overlay" style="display: none;">
        <div class="cat-modal-container">
            <div class="cat-modal-header">
                <div class="cat-modal-title">
                    <i class="fas fa-edit" style="color: #ffd700;"></i>
                    <span>Edit Category</span>
                </div>
                <button type="button" class="cat-modal-close-btn" onclick="closeEditCategoryPanel()" aria-label="Close" title="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="cat-modal-body">
                <form id="editCategoryForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_category">
                    <input type="hidden" id="editCategoryId" name="categoryId">
                    <input type="hidden" id="editOldCategoryName" name="oldCategoryName">
                    <input type="hidden" id="removeCategoryImage" name="removeCategoryImage" value="0">
                    <input type="hidden" id="removeSizeGuideImage" name="removeSizeGuideImage" value="0">

                    <div class="cat-form-group">
                        <label for="editCategoryName">Category Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="editCategoryName" name="categoryName" placeholder="Enter category name..." required>
                    </div>

                    <div class="cat-form-group" style="margin-top: 18px;">
                        <label>Item Name Sizes <small style="color: #999; font-weight: normal; margin-left: 6px;">(Measurement Labels)</small></label>
                        <div class="cat-sizes-grid">
                            <div class="cat-size-item"><span class="cat-size-badge">1</span><input type="text" id="editItemSize1" name="itemSize1" placeholder="Size 1"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">2</span><input type="text" id="editItemSize2" name="itemSize2" placeholder="Size 2"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">3</span><input type="text" id="editItemSize3" name="itemSize3" placeholder="Size 3"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">4</span><input type="text" id="editItemSize4" name="itemSize4" placeholder="Size 4"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">5</span><input type="text" id="editItemSize5" name="itemSize5" placeholder="Size 5"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">6</span><input type="text" id="editItemSize6" name="itemSize6" placeholder="Size 6"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">7</span><input type="text" id="editItemSize7" name="itemSize7" placeholder="Size 7"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">8</span><input type="text" id="editItemSize8" name="itemSize8" placeholder="Size 8"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">9</span><input type="text" id="editItemSize9" name="itemSize9" placeholder="Size 9"></div>
                            <div class="cat-size-item"><span class="cat-size-badge">10</span><input type="text" id="editItemSize10" name="itemSize10" placeholder="Size 10"></div>
                        </div>
                    </div>

                    <div class="cat-media-grid" style="margin-top: 18px;">
                        <div class="cat-form-group">
                            <label for="editSizeGuideImage">Size Guide Image</label>
                            <input type="file" id="editSizeGuideImage" name="sizeGuideImage" accept="image/*">
                            <div class="cat-image-preview" id="editSizeGuidePreview"></div>
                        </div>

                        <div class="cat-form-group">
                            <label for="editCategoryImage">Category Image</label>
                            <input type="file" id="editCategoryImage" name="categoryImage" accept="image/*">
                            <div class="cat-image-preview" id="editImagePreview"></div>
                        </div>
                    </div>

                    <div class="cat-modal-footer">
                        <button type="submit" class="btn-submit-modal">Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Size Guide High-Res View Modal -->
    <div id="catImageModal" class="cat-modal-overlay" style="display: none; z-index: 100001;" onclick="closeSizeGuideModal()">
        <div style="position: relative; max-width: 90vw; max-height: 90vh; background: #14151a; border: 1.5px solid #7c6300; border-radius: 12px; padding: 18px; box-shadow: 0 20px 50px rgba(0,0,0,0.95); display: flex; flex-direction: column; align-items: center;" onclick="event.stopPropagation()">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 12px; border-bottom: 1px solid rgba(212,175,55,0.25); padding-bottom: 10px;">
                <h3 id="catImageModalTitle" style="margin: 0; color: #ffd700; font-size: 1.15rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-ruler-combined"></i> <span>Size Guide</span>
                </h3>
                <button type="button" class="cat-modal-close-btn" onclick="closeSizeGuideModal()" aria-label="Close" title="Close"><i class="fas fa-times"></i></button>
            </div>
            <img id="catImageModalImg" src="" alt="Size Guide Preview" style="max-width: 85vw; max-height: 75vh; object-fit: contain; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: #000;">
        </div>
    </div>

    <script>
        let currentCategoriesList = [];

        // Load categories on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCategories();
        });

        // Robust Image URL Formatter
        function formatImgUrl(path) {
            if (!path) return '';
            path = String(path).trim();
            if (!path) return '';
            if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) return path;
            if (path.startsWith('../')) return encodeURI(path);
            if (path.startsWith('/')) return encodeURI('..' + path);
            return encodeURI('../' + path);
        }
        
        // Load categories from server
        function loadCategories() {
            fetch('categories.php?action=getAllCategories')
                .then(response => response.json())
                .then(data => {
                    currentCategoriesList = Array.isArray(data) ? data : [];
                    displayCategories(currentCategoriesList);
                    try {
                        if (window.parent && window.parent !== window && typeof window.parent.loadCategories === 'function') {
                            window.parent.loadCategories();
                        }
                    } catch(e) {}
                })
                .catch(error => {
                    console.error('Error loading categories:', error);
                });
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        // Custom Notification / Confirmation Toast Handlers
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

        function showConfirmNotification(title, message, onConfirm, onCancel = null) {
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
            if (okBtn) okBtn.textContent = 'OK';

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

        // Open High-Res Size Guide Viewer Modal
        function viewSizeGuideModal(imageUrl, categoryName) {
            if (!imageUrl) return;
            const modal = document.getElementById('catImageModal');
            const titleEl = document.getElementById('catImageModalTitle');
            const imgEl = document.getElementById('catImageModalImg');
            if (titleEl) {
                titleEl.innerHTML = `<i class="fas fa-ruler-combined"></i> <span>${escapeHtml(categoryName || 'Category')} Size Guide</span>`;
            }
            if (imgEl) {
                imgEl.src = imageUrl;
            }
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeSizeGuideModal() {
            const modal = document.getElementById('catImageModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
            const imgEl = document.getElementById('catImageModalImg');
            if (imgEl) imgEl.src = '';
        }

        // Display categories in grid
        function displayCategories(categories) {
            const grid = document.getElementById('categoriesGrid');
            grid.innerHTML = '';

            if (!categories || categories.length === 0) {
                grid.innerHTML = `
                    <div style="grid-column: 1 / -1; text-align: center; padding: 50px 20px; color: #888;">
                        <i class="fas fa-folder-open" style="font-size: 2.5rem; margin-bottom: 12px; opacity: 0.5;"></i>
                        <p style="font-size: 1rem;">No categories found.</p>
                    </div>
                `;
                return;
            }

            categories.forEach(category => {
                const card = document.createElement('div');
                card.className = 'category-card';
                const hasId = category.id !== null && category.id !== undefined && category.id !== '';
                const catImgUrl = formatImgUrl(category.category_image);
                const guideImgUrl = formatImgUrl(category.size_guide_image);

                card.innerHTML = `
                    <div class="category-image-container">
                        ${catImgUrl ?
                            `<img src="${catImgUrl}" alt="${escapeHtml(category.category_name)}" class="category-image" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'category-placeholder\\'><i class=\\'fas fa-image\\'></i></div>';">` :
                            `<div class="category-placeholder">
                                <i class="fas fa-folder"></i>
                            </div>`
                        }
                    </div>
                    <div class="category-info">
                        <h3>${escapeHtml(category.category_name)}</h3>
                        <div class="cat-card-size-guide-row">
                            ${guideImgUrl ? `
                                <button type="button" class="btn-guide-tag has-guide btn-view-guide" title="Click to view full Size Guide">
                                    <i class="fas fa-ruler-combined"></i> Size Guide
                                </button>
                            ` : `
                                <span class="btn-guide-tag no-guide" title="No size guide image uploaded">
                                    <i class="fas fa-minus-circle"></i> No Size Guide
                                </span>
                            `}
                        </div>
                        ${category.from_gowns ? '<small style="color: #ffd700; font-size: 0.76rem; display: block; margin-top: 4px;"><i class="fas fa-info-circle"></i> From Products</small>' : ''}
                    </div>
                    <div class="category-actions">
                        <button type="button" class="btn-edit" title="Edit Category">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        ${hasId ? `
                        <button type="button" class="btn-delete" title="Delete Category">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                `;

                // Double click card to edit
                card.addEventListener('dblclick', function(e) {
                    if (e.target.closest('.btn-view-guide') || e.target.closest('.btn-delete')) return;
                    openEditCategoryPanel(hasId ? category.id : null, category.category_name);
                });

                // Edit button listener
                const editBtn = card.querySelector('.btn-edit');
                if (editBtn) {
                    editBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        openEditCategoryPanel(hasId ? category.id : null, category.category_name);
                    });
                }

                // Delete button listener
                const deleteBtn = card.querySelector('.btn-delete');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        promptDeleteCategory(category.id, category.category_name);
                    });
                }

                // View size guide listener
                const viewGuideBtn = card.querySelector('.btn-view-guide');
                if (viewGuideBtn) {
                    viewGuideBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        viewSizeGuideModal(guideImgUrl, category.category_name);
                    });
                }

                grid.appendChild(card);
            });
        }
        
        // Open Add Category Modal
        function openAddCategoryPanel() {
            const modal = document.getElementById('addCategoryPanel');
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
            renderEmptyPreview('addImagePreview', 'Category Image');
            renderEmptyPreview('addSizeGuidePreview', 'Size Guide Image');
        }

        // Close Add Category Modal
        function closeAddCategoryPanel() {
            const modal = document.getElementById('addCategoryPanel');
            if (modal) {
                modal.style.display = 'none';
            }
            document.body.style.overflow = 'auto';
            const form = document.getElementById('addCategoryForm');
            if (form) form.reset();
            renderEmptyPreview('addImagePreview', 'Category Image');
            renderEmptyPreview('addSizeGuidePreview', 'Size Guide Image');
        }

        // Render Empty Preview State
        function renderEmptyPreview(containerId, label = 'Image') {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.innerHTML = `
                <div class="cat-preview-empty-state">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>No ${escapeHtml(label)} selected</span>
                </div>
            `;
        }

        // Render Active Image Preview in Edit Modal with Remove / Undo Controls
        function renderExistingImagePreview(containerId, imageUrl, label, removeFieldId, inputFieldId) {
            const container = document.getElementById(containerId);
            const removeField = document.getElementById(removeFieldId);
            if (!container) return;

            if (!imageUrl) {
                if (removeField) removeField.value = '0';
                renderEmptyPreview(containerId, label);
                return;
            }

            const formatted = formatImgUrl(imageUrl);
            const fileName = imageUrl.split('/').pop() || 'image';

            container.innerHTML = `
                <div class="cat-preview-box">
                    <img src="${formatted}" alt="${escapeHtml(label)}" class="preview-image" onerror="this.onerror=null; this.src='../images/placeholder.png';">
                    <div class="cat-preview-actions">
                        <span class="cat-preview-info" title="${escapeHtml(fileName)}">
                            <i class="fas fa-check-circle" style="color: #10b981;"></i> Current Image
                        </span>
                        <button type="button" class="btn-remove-cat-img" title="Remove this image upon saving">
                            <i class="fas fa-trash-alt"></i> Remove
                        </button>
                    </div>
                </div>
            `;

            if (removeField) removeField.value = '0';

            const removeBtn = container.querySelector('.btn-remove-cat-img');
            if (removeBtn) {
                removeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (removeField) removeField.value = '1';
                    const fileInput = document.getElementById(inputFieldId);
                    if (fileInput) fileInput.value = '';

                    container.innerHTML = `
                        <div class="cat-preview-box" style="padding: 12px 0;">
                            <span style="color: #ef4444; font-size: 0.85rem; font-weight: 600;">
                                <i class="fas fa-exclamation-circle"></i> Marked for removal on save
                            </span>
                            <button type="button" class="btn-undo-remove">
                                <i class="fas fa-undo"></i> Undo Removal
                            </button>
                        </div>
                    `;

                    const undoBtn = container.querySelector('.btn-undo-remove');
                    if (undoBtn) {
                        undoBtn.addEventListener('click', function(ev) {
                            ev.preventDefault();
                            renderExistingImagePreview(containerId, imageUrl, label, removeFieldId, inputFieldId);
                        });
                    }
                });
            }
        }

        // Render Newly Selected Local File Preview
        function renderNewFilePreview(containerId, dataUrl, fileName, removeFieldId, inputFieldId, originalUrl, label) {
            const container = document.getElementById(containerId);
            const removeField = document.getElementById(removeFieldId);
            if (!container) return;

            if (removeField) removeField.value = '0';

            container.innerHTML = `
                <div class="cat-preview-box">
                    <img src="${dataUrl}" alt="Selected File" class="preview-image">
                    <div class="cat-preview-actions">
                        <span class="cat-preview-info" title="${escapeHtml(fileName)}">
                            <i class="fas fa-file-image" style="color: #ffd700;"></i> New: ${escapeHtml(fileName)}
                        </span>
                        <button type="button" class="btn-remove-cat-img" title="Cancel this selection">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            `;

            const cancelBtn = container.querySelector('.btn-remove-cat-img');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const fileInput = document.getElementById(inputFieldId);
                    if (fileInput) fileInput.value = '';
                    if (originalUrl) {
                        renderExistingImagePreview(containerId, originalUrl, label, removeFieldId, inputFieldId);
                    } else {
                        renderEmptyPreview(containerId, label);
                    }
                });
            }
        }

        // Open Edit Category Modal
        function openEditCategoryPanel(categoryId, categoryName) {
            const modal = document.getElementById('editCategoryPanel');
            const form = document.getElementById('editCategoryForm');
            if (form) form.reset();

            document.getElementById('removeCategoryImage').value = '0';
            document.getElementById('removeSizeGuideImage').value = '0';
            document.getElementById('editCategoryImage').value = '';
            document.getElementById('editSizeGuideImage').value = '';

            // Find category from memory cache
            let category = null;
            if (currentCategoriesList && currentCategoriesList.length > 0) {
                if (categoryId) {
                    category = currentCategoriesList.find(c => c.id == categoryId);
                }
                if (!category && categoryName) {
                    category = currentCategoriesList.find(c => c.category_name && c.category_name.toLowerCase().trim() === String(categoryName).toLowerCase().trim());
                }
            }

            const populateModal = (cat) => {
                const targetId = cat && cat.id ? cat.id : (categoryId || '');
                const targetName = cat && cat.category_name ? cat.category_name : (categoryName || '');

                document.getElementById('editCategoryId').value = targetId;
                document.getElementById('editOldCategoryName').value = targetName;
                document.getElementById('editCategoryName').value = targetName;
                document.getElementById('editItemSize1').value = cat ? (cat.size_label_1 || '') : '';
                document.getElementById('editItemSize2').value = cat ? (cat.size_label_2 || '') : '';
                document.getElementById('editItemSize3').value = cat ? (cat.size_label_3 || '') : '';
                document.getElementById('editItemSize4').value = cat ? (cat.size_label_4 || '') : '';
                document.getElementById('editItemSize5').value = cat ? (cat.size_label_5 || '') : '';
                document.getElementById('editItemSize6').value = cat ? (cat.size_label_6 || '') : '';
                document.getElementById('editItemSize7').value = cat ? (cat.size_label_7 || '') : '';
                document.getElementById('editItemSize8').value = cat ? (cat.size_label_8 || '') : '';
                document.getElementById('editItemSize9').value = cat ? (cat.size_label_9 || '') : '';
                document.getElementById('editItemSize10').value = cat ? (cat.size_label_10 || '') : '';

                // Store current original URLs on modal for cancel operations
                modal.dataset.currentCategoryImg = cat && cat.category_image ? cat.category_image : '';
                modal.dataset.currentSizeGuideImg = cat && cat.size_guide_image ? cat.size_guide_image : '';

                // Render current images or placeholders
                renderExistingImagePreview('editImagePreview', modal.dataset.currentCategoryImg, 'Category Image', 'removeCategoryImage', 'editCategoryImage');
                renderExistingImagePreview('editSizeGuidePreview', modal.dataset.currentSizeGuideImg, 'Size Guide Image', 'removeSizeGuideImage', 'editSizeGuideImage');

                if (modal) {
                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
            };

            if (category) {
                populateModal(category);
            } else if (categoryId) {
                // If not in cache, fetch fresh data
                fetch('categories.php?action=getAllCategories')
                    .then(response => response.json())
                    .then(data => {
                        currentCategoriesList = Array.isArray(data) ? data : [];
                        const freshCat = currentCategoriesList.find(c => c.id == categoryId || (c.category_name && c.category_name.toLowerCase().trim() === String(categoryName).toLowerCase().trim()));
                        populateModal(freshCat);
                    })
                    .catch(() => {
                        populateModal(null);
                    });
            } else {
                populateModal(null);
            }
        }

        // Close Edit Category Modal
        function closeEditCategoryPanel() {
            const modal = document.getElementById('editCategoryPanel');
            if (modal) {
                modal.style.display = 'none';
            }
            document.body.style.overflow = 'auto';
            const form = document.getElementById('editCategoryForm');
            if (form) form.reset();
            document.getElementById('editOldCategoryName').value = '';
            document.getElementById('removeCategoryImage').value = '0';
            document.getElementById('removeSizeGuideImage').value = '0';
            renderEmptyPreview('editImagePreview', 'Category Image');
            renderEmptyPreview('editSizeGuidePreview', 'Size Guide Image');
        }

        // Delete Category Prompt
        function promptDeleteCategory(categoryId, categoryName) {
            showConfirmNotification(
                'Delete Category?',
                `Are you sure you want to delete "${categoryName}"?`,
                () => {
                    executeDeleteCategory(categoryId);
                }
            );
        }

        function executeDeleteCategory(categoryId) {
            const formData = new FormData();
            formData.append('action', 'delete_category');
            formData.append('categoryId', categoryId);
            
            fetch('categories.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadCategories();
                    showNotification('Deleted', 'Category deleted successfully.', 'success');
                } else {
                    showNotification('Delete Failed', data.message || 'Failed to delete category.', 'error');
                }
            })
            .catch(error => {
                console.error('Error deleting category:', error);
                showNotification('Error', 'Error deleting category.', 'error');
            });
        }
        
        // Handle Add Category Form Submission
        document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const origText = submitBtn ? submitBtn.innerHTML : 'Add Category';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }

            const formData = new FormData(this);

            fetch('categories.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAddCategoryPanel();
                    loadCategories();
                    showNotification('Category Added', 'New category created successfully.', 'success');
                } else {
                    showNotification('Creation Failed', data.message || 'Failed to add category.', 'error');
                }
            })
            .catch(error => {
                console.error('Error adding category:', error);
                showNotification('Error', 'Error adding category.', 'error');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                }
            });
        });

        // Handle Edit Category Form Submission
        document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const origText = submitBtn ? submitBtn.innerHTML : 'Update Category';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }

            const formData = new FormData(this);

            fetch('categories.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeEditCategoryPanel();
                    loadCategories();
                    showNotification('Category Updated', 'Category updated successfully.', 'success');
                } else {
                    showNotification('Update Failed', data.message || 'Failed to update category.', 'error');
                }
            })
            .catch(error => {
                console.error('Error updating category:', error);
                showNotification('Error', 'Error updating category.', 'error');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                }
            });
        });
        
        // Image preview for Add Category
        document.getElementById('categoryImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    renderNewFilePreview('addImagePreview', ev.target.result, file.name, '', 'categoryImage', '', 'Category Image');
                };
                reader.readAsDataURL(file);
            } else {
                renderEmptyPreview('addImagePreview', 'Category Image');
            }
        });
        
        // Size Guide image preview for Add Category
        document.getElementById('sizeGuideImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    renderNewFilePreview('addSizeGuidePreview', ev.target.result, file.name, '', 'sizeGuideImage', '', 'Size Guide Image');
                };
                reader.readAsDataURL(file);
            } else {
                renderEmptyPreview('addSizeGuidePreview', 'Size Guide Image');
            }
        });

        // Image preview for Edit Category
        document.getElementById('editCategoryImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const modal = document.getElementById('editCategoryPanel');
            const origUrl = modal ? modal.dataset.currentCategoryImg : '';
            if (file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    renderNewFilePreview('editImagePreview', ev.target.result, file.name, 'removeCategoryImage', 'editCategoryImage', origUrl, 'Category Image');
                };
                reader.readAsDataURL(file);
            } else {
                if (origUrl) {
                    renderExistingImagePreview('editImagePreview', origUrl, 'Category Image', 'removeCategoryImage', 'editCategoryImage');
                } else {
                    renderEmptyPreview('editImagePreview', 'Category Image');
                }
            }
        });

        // Size Guide image preview for Edit Category
        document.getElementById('editSizeGuideImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const modal = document.getElementById('editCategoryPanel');
            const origUrl = modal ? modal.dataset.currentSizeGuideImg : '';
            if (file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    renderNewFilePreview('editSizeGuidePreview', ev.target.result, file.name, 'removeSizeGuideImage', 'editSizeGuideImage', origUrl, 'Size Guide Image');
                };
                reader.readAsDataURL(file);
            } else {
                if (origUrl) {
                    renderExistingImagePreview('editSizeGuidePreview', origUrl, 'Size Guide Image', 'removeSizeGuideImage', 'editSizeGuideImage');
                } else {
                    renderEmptyPreview('editSizeGuidePreview', 'Size Guide Image');
                }
            }
        });
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
