<?php
// Script to add missing size columns to saved_products table
require_once 'db_config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Check if table exists
    $checkTableSql = "SHOW TABLES LIKE 'saved_products'";
    $checkTableStmt = $conn->prepare($checkTableSql);
    $checkTableStmt->execute();
    $checkTableResult = $checkTableStmt->get_result();
    
    if ($checkTableResult->num_rows == 0) {
        // Create the table if it doesn't exist
        $createTableSql = "CREATE TABLE `saved_products` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `product_id` int(11) DEFAULT NULL,
            `product_name` varchar(255) NOT NULL,
            `product_category` varchar(100) NOT NULL,
            `product_price` decimal(10,2) NOT NULL,
            `model_path` varchar(500) DEFAULT NULL,
            `reserve_date` date DEFAULT NULL,
            `return_date` date DEFAULT NULL,
            `size_1` varchar(50) DEFAULT NULL,
            `size_2` varchar(50) DEFAULT NULL,
            `size_3` varchar(50) DEFAULT NULL,
            `size_4` varchar(50) DEFAULT NULL,
            `size_5` varchar(50) DEFAULT NULL,
            `size_6` varchar(50) DEFAULT NULL,
            `size_7` varchar(50) DEFAULT NULL,
            `size_8` varchar(50) DEFAULT NULL,
            `size_9` varchar(50) DEFAULT NULL,
            `size_10` varchar(50) DEFAULT NULL,
            `quantity` int(11) DEFAULT 1,
            `product_status` enum('saved','reserve','buy') DEFAULT 'saved',
            `purchase_price` decimal(10,2) DEFAULT NULL,
            `stock` int(11) DEFAULT 999,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `product_id` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        
        if ($conn->query($createTableSql)) {
            echo "<p style='color: green;'>✓ saved_products table created successfully with all 10 size columns!</p>";
        } else {
            throw new Exception("Error creating table: " . $conn->error);
        }
    } else {
        // Check existing columns
        $checkColumnsSql = "SHOW COLUMNS FROM saved_products";
        $checkColumnsStmt = $conn->prepare($checkColumnsSql);
        $checkColumnsStmt->execute();
        $checkColumnsResult = $checkColumnsStmt->get_result();
        $existingColumns = [];
        while ($row = $checkColumnsResult->fetch_assoc()) {
            $existingColumns[] = $row['Field'];
        }
        
        $columnsToAdd = [];
        for ($i = 4; $i <= 10; $i++) {
            $columnName = "size_{$i}";
            if (!in_array($columnName, $existingColumns)) {
                $columnsToAdd[] = $columnName;
            }
        }
        
        // Check if stock column exists
        if (!in_array('stock', $existingColumns)) {
            $columnsToAdd[] = 'stock';
        }
        
        if (empty($columnsToAdd)) {
            echo "<p style='color: blue;'>ℹ All required columns already exist in saved_products table.</p>";
        } else {
            // Add missing columns
            foreach ($columnsToAdd as $column) {
                if ($column === 'stock') {
                    $alterSql = "ALTER TABLE saved_products ADD COLUMN stock INT(11) DEFAULT 999 AFTER product_status";
                } else {
                    $alterSql = "ALTER TABLE saved_products ADD COLUMN {$column} VARCHAR(50) DEFAULT NULL AFTER size_" . ($column === 'size_4' ? '3' : str_replace('size_', '', $column) - 1);
                }
                
                if ($conn->query($alterSql)) {
                    echo "<p style='color: green;'>✓ Column {$column} added successfully!</p>";
                } else {
                    echo "<p style='color: red;'>✗ Error adding column {$column}: " . $conn->error . "</p>";
                }
            }
        }
    }
    
    // Show current table structure
    echo "<h3>Current saved_products table structure:</h3>";
    $showTableSql = "DESCRIBE saved_products";
    $showTableStmt = $conn->prepare($showTableSql);
    $showTableStmt->execute();
    $showTableResult = $showTableStmt->get_result();
    
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($column = $showTableResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><a href='../save.php'>Go to save.php</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}
?>
