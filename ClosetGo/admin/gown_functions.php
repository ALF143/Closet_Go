<?php
require_once '../database/db_config.php';

// Handle API requests
if (isset($_GET['action']) && $_GET['action'] == 'getAllGowns') {
    header('Content-Type: application/json');
    
    $gownManager = new GownManager();
    $gowns = $gownManager->getAllGowns();
    
    echo json_encode($gowns);
    exit();
}

class GownManager {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Add new gown
    public function addGown($name, $category, $price, $size, $description, $imagePath = null, $modelPath = null) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO gowns (name, category, price, size, description, image_path, model_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("ssdssss", $name, $category, $price, $size, $description, $imagePath, $modelPath);
        
        if ($stmt->execute()) {
            return $this->db->getLastInsertId();
        } else {
            return false;
        }
    }

    // Get all gowns
    public function getAllGowns() {
        $conn = $this->db->getConnection();
        
        $result = $conn->query("SELECT * FROM gowns ORDER BY id DESC");
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
    public function updateGown($id, $name, $category, $price, $size, $description, $imagePath = null, $modelPath = null) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("
            UPDATE gowns 
            SET name = ?, category = ?, price = ?, size = ?, description = ?, image_path = ?, model_path = ? 
            WHERE id = ?
        ");
        
        $stmt->bind_param("ssdssssi", $name, $category, $price, $size, $description, $imagePath, $modelPath, $id);
        
        return $stmt->execute();
    }

    // Delete gown
    public function deleteGown($id) {
        $conn = $this->db->getConnection();
        
        // Get gown info first to delete files
        $gown = $this->getGownById($id);
        
        if ($gown) {
            // Delete image file if exists
            if ($gown['image_path'] && file_exists('../' . $gown['image_path'])) {
                unlink('../' . $gown['image_path']);
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

    // Search gowns
    public function searchGowns($keyword, $category = '', $size = '', $minPrice = 0, $maxPrice = 999999) {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT * FROM gowns WHERE (name LIKE ? OR description LIKE ?) AND price BETWEEN ? AND ?";
        $params = ["%$keyword%", "%$keyword%", $minPrice, $maxPrice];
        $types = "ssdd";
        
        if (!empty($category)) {
            $sql .= " AND category = ?";
            $params[] = $category;
            $types .= "s";
        }
        
        if (!empty($size)) {
            $sql .= " AND size = ?";
            $params[] = $size;
            $types .= "s";
        }
        
        $sql .= " ORDER BY id DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $gowns = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $gowns[] = $row;
            }
        }
        
        return $gowns;
    }

    // Update gown status
    public function updateGownStatus($id, $status) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("UPDATE gowns SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        
        return $stmt->execute();
    }

    // Get gown statistics
    public function getGownStats() {
        $conn = $this->db->getConnection();
        
        $stats = [];
        
        // Total gowns
        $result = $conn->query("SELECT COUNT(*) as total FROM gowns");
        $stats['total'] = $result->fetch_assoc()['total'];
        
        // By category
        $result = $conn->query("SELECT category, COUNT(*) as count FROM gowns GROUP BY category");
        $stats['by_category'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['by_category'][$row['category']] = $row['count'];
        }
        
        // By status
        $result = $conn->query("SELECT status, COUNT(*) as count FROM gowns GROUP BY status");
        $stats['by_status'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['by_status'][$row['status']] = $row['count'];
        }
        
        return $stats;
    }
}
?>
