<?php
require_once 'db_config.php';

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die("Database connection failed.\n");
}

$checkSql = "SHOW COLUMNS FROM reserve_products LIKE 'order_status'";
$result = $conn->query($checkSql);

if ($result && $result->num_rows > 0) {
    echo "Column 'order_status' already exists in reserve_products table.\n";
} else {
    $alterSql = "ALTER TABLE reserve_products ADD COLUMN order_status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending' AFTER quantity";
    if ($conn->query($alterSql)) {
        echo "Column 'order_status' added successfully to reserve_products table.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
}

$conn->close();
echo "Done.\n";
