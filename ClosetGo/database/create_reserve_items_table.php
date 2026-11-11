<?php
require_once 'db_config.php';

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die("Database connection failed.\n");
}

$checkSql = "SHOW TABLES LIKE 'reserve_products_items'";
$result = $conn->query($checkSql);

if ($result && $result->num_rows > 0) {
    echo "Table 'reserve_products_items' already exists.\n";
} else {
    $createSql = "CREATE TABLE `reserve_products_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `reserve_product_id` int(11) NOT NULL COMMENT 'Foreign key to reserve_products table',
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
        KEY `reserve_product_id` (`reserve_product_id`),
        KEY `item_number` (`item_number`),
        CONSTRAINT `fk_reserve_product_id` FOREIGN KEY (`reserve_product_id`) REFERENCES `reserve_products` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if ($conn->query($createSql)) {
        echo "Table 'reserve_products_items' created successfully.\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
}

echo "Done.\n";
