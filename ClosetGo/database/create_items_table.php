<?php
// Script to create saved_products_items table for Item 2 and above
require_once 'db_config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Check if table already exists
    $checkTableSql = "SHOW TABLES LIKE 'saved_products_items'";
    $checkTableStmt = $conn->prepare($checkTableSql);
    $checkTableStmt->execute();
    $checkTableResult = $checkTableStmt->get_result();
    
    if ($checkTableResult->num_rows > 0) {
        echo "<p style='color: blue;'>ℹ Table 'saved_products_items' already exists.</p>";
    } else {
        // Create the table
        $createTableSql = "CREATE TABLE `saved_products_items` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `saved_product_id` int(11) NOT NULL COMMENT 'Foreign key to saved_products table',
            `item_number` int(11) NOT NULL COMMENT 'Item number (2, 3, 4, etc.)',
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
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `saved_product_id` (`saved_product_id`),
            KEY `item_number` (`item_number`),
            CONSTRAINT `fk_saved_product_id` FOREIGN KEY (`saved_product_id`) REFERENCES `saved_products` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        
        if ($conn->query($createTableSql)) {
            echo "<p style='color: green;'>✓ Table 'saved_products_items' created successfully!</p>";
        } else {
            throw new Exception("Error creating table: " . $conn->error);
        }
    }
    
    // Show current table structure
    echo "<h3>Current saved_products_items table structure:</h3>";
    $showTableSql = "DESCRIBE saved_products_items";
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
