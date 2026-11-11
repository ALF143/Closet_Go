<?php
/**
 * Migration Script: Add Reschedule and Cancellation Columns to reserve_products
 * 
 * You can run this file directly via browser:
 *   http://localhost/ClosetGoo_gmb_test/database/add_reschedule_columns.php
 * Or on your online server:
 *   https://yourdomain.com/database/add_reschedule_columns.php
 * Or via command line:
 *   php add_reschedule_columns.php
 */

require_once __DIR__ . '/db_config.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("Database connection failed. Please check db_config.php settings.\n");
}

$columnsToAdd = [
    'is_rescheduled' => "ALTER TABLE `reserve_products` ADD COLUMN `is_rescheduled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reserved_at`",
    'rescheduled_at' => "ALTER TABLE `reserve_products` ADD COLUMN `rescheduled_at` DATETIME DEFAULT NULL AFTER `is_rescheduled`",
    'original_reserve_date' => "ALTER TABLE `reserve_products` ADD COLUMN `original_reserve_date` DATE DEFAULT NULL AFTER `rescheduled_at`",
    'original_return_date' => "ALTER TABLE `reserve_products` ADD COLUMN `original_return_date` DATE DEFAULT NULL AFTER `original_reserve_date`",
    'reschedule_reason' => "ALTER TABLE `reserve_products` ADD COLUMN `reschedule_reason` TEXT DEFAULT NULL AFTER `original_return_date`",
    'cancellation_reason' => "ALTER TABLE `reserve_products` ADD COLUMN `cancellation_reason` TEXT DEFAULT NULL",
    'cancellation_images' => "ALTER TABLE `reserve_products` ADD COLUMN `cancellation_images` TEXT DEFAULT NULL",
    'product_status' => "ALTER TABLE `reserve_products` ADD COLUMN `product_status` VARCHAR(20) DEFAULT 'reserve'",
];

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Database Migration - Reschedule Columns</title>";
    echo "<style>body{font-family: Arial, sans-serif; padding: 24px; background: #0f172a; color: #f8fafc;}";
    echo ".card{max-width: 650px; margin: 0 auto; background: #1e293b; padding: 24px; border-radius: 8px; border: 1px solid #334155;}";
    echo ".success{color: #4ade80; margin: 8px 0;} .exists{color: #38bdf8; margin: 8px 0;} .error{color: #f87171; margin: 8px 0;}";
    echo "h2{color: #f59e0b; margin-top: 0;} hr{border: 0; border-top: 1px solid #334155; margin: 16px 0;}</style></head><body>";
    echo "<div class='card'><h2>Database Migration: reserve_products Reschedule Columns</h2><hr>";
}

$successCount = 0;
$existCount = 0;
$errorCount = 0;

foreach ($columnsToAdd as $columnName => $alterSql) {
    $checkQuery = "SHOW COLUMNS FROM `reserve_products` LIKE '$columnName'";
    $checkResult = $conn->query($checkQuery);

    if ($checkResult && $checkResult->num_rows > 0) {
        $msg = "Column '<strong>$columnName</strong>' already exists in reserve_products.";
        if ($isCli) {
            echo "[ALREADY EXISTS] Column '$columnName'\n";
        } else {
            echo "<div class='exists'>✓ $msg</div>";
        }
        $existCount++;
    } else {
        if ($conn->query($alterSql)) {
            $msg = "Column '<strong>$columnName</strong>' was successfully added to reserve_products.";
            if ($isCli) {
                echo "[SUCCESS] Added column '$columnName'\n";
            } else {
                echo "<div class='success'>+ $msg</div>";
            }
            $successCount++;
        } else {
            $err = $conn->error;
            $msg = "Failed to add column '<strong>$columnName</strong>': $err";
            if ($isCli) {
                echo "[ERROR] Failed to add '$columnName': $err\n";
            } else {
                echo "<div class='error'>✗ $msg</div>";
            }
            $errorCount++;
        }
    }
}

if (!$isCli) {
    echo "<hr><p>Summary: $successCount added, $existCount already existed, $errorCount errors.</p>";
    echo "<p><a href='../purchase.php' style='color:#38bdf8; text-decoration:none;'>&larr; Return to Purchase page</a></p>";
    echo "</div></body></html>";
} else {
    echo "\nDone. Added: $successCount, Already Existed: $existCount, Errors: $errorCount\n";
}

