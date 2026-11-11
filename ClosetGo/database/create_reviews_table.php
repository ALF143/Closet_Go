<?php
require_once 'db_config.php';

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die("Database connection failed.\n");
}

$createTable = "
CREATE TABLE IF NOT EXISTS product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reserve_product_id INT NOT NULL,
    product_id INT NOT NULL DEFAULT 0,
    user_id INT NOT NULL,
    rating INT NOT NULL DEFAULT 5,
    review_text TEXT NULL,
    photo1_path VARCHAR(500) NULL,
    photo2_path VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_review (reserve_product_id),
    FOREIGN KEY (reserve_product_id) REFERENCES reserve_products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE CASCADE
)
";

if ($conn->query($createTable)) {
    echo "Table 'product_reviews' created successfully (or already exists).\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
echo "Done.\n";
