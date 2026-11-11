<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: adminlogin.php');
    exit();
}

// Include database connection
require_once '../database/db_config.php';
$db = new Database();
$conn = $db->getConnection();

// Helper: Format byte sizes nicely
function formatBackupBytes($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

// Helper: Generate a complete SQL database backup
function generateDatabaseBackup($conn, $backupDir) {
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }
    
    $dbNameRes = $conn->query("SELECT DATABASE()");
    $dbName = $dbNameRes ? $dbNameRes->fetch_row()[0] : 'gown_management';
    $serverVer = $conn->server_info;
    $dateStr = date('Y-m-d H:i:s');
    $fileName = 'closetgoo_backup_' . date('Y-m-d_His') . '.sql';
    $filePath = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;

    $handle = @fopen($filePath, 'w');
    if (!$handle) {
        return ['success' => false, 'message' => 'Unable to write backup file on server disk. Please check directory permissions.'];
    }

    $header = "-- ------------------------------------------------------------\n"
            . "-- ClosetGoo Database Backup\n"
            . "-- Database   : `{$dbName}`\n"
            . "-- Generated  : {$dateStr}\n"
            . "-- Server     : MySQL {$serverVer}\n"
            . "-- ------------------------------------------------------------\n\n"
            . "SET NAMES utf8mb4;\n"
            . "SET FOREIGN_KEY_CHECKS = 0;\n"
            . "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n"
            . "SET time_zone = '+00:00';\n\n";
    fwrite($handle, $header);

    $tablesRes = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    $tables = [];
    if ($tablesRes) {
        while ($tRow = $tablesRes->fetch_row()) {
            $tables[] = $tRow[0];
        }
    }

    foreach ($tables as $table) {
        fwrite($handle, "-- ------------------------------------------------------------\n");
        fwrite($handle, "-- Table structure for `{$table}`\n");
        fwrite($handle, "-- ------------------------------------------------------------\n");
        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");

        $createRes = $conn->query("SHOW CREATE TABLE `{$table}`");
        if ($createRes && $createRow = $createRes->fetch_row()) {
            fwrite($handle, $createRow[1] . ";\n\n");
        }

        $cntRes = $conn->query("SELECT COUNT(*) FROM `{$table}`");
        $rowCount = $cntRes ? intval($cntRes->fetch_row()[0]) : 0;

        if ($rowCount > 0) {
            fwrite($handle, "-- Data for `{$table}`\n");

            $colsRes = $conn->query("SHOW COLUMNS FROM `{$table}`");
            $colNames = [];
            while ($col = $colsRes->fetch_assoc()) {
                $colNames[] = '`' . $col['Field'] . '`';
            }
            $colsList = implode(', ', $colNames);

            $batchSize = 200;
            $offset = 0;
            while ($offset < $rowCount) {
                $dataRes = $conn->query("SELECT * FROM `{$table}` LIMIT {$offset}, {$batchSize}");
                if ($dataRes && $dataRes->num_rows > 0) {
                    $insertSql = "INSERT INTO `{$table}` ({$colsList}) VALUES\n";
                    $valueRows = [];
                    while ($row = $dataRes->fetch_row()) {
                        $values = [];
                        foreach ($row as $val) {
                            if (is_null($val)) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = "'" . $conn->real_escape_string($val) . "'";
                            }
                        }
                        $valueRows[] = "(" . implode(', ', $values) . ")";
                    }
                    $insertSql .= implode(",\n", $valueRows) . ";\n";
                    fwrite($handle, $insertSql);
                }
                $offset += $batchSize;
            }
            fwrite($handle, "\n");
        }
    }

    fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fwrite($handle, "-- ------------------------------------------------------------\n");
    fwrite($handle, "-- Backup successfully generated at " . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, "-- ------------------------------------------------------------\n");
    fclose($handle);

    return [
        'success' => true,
        'filename' => $fileName,
        'filepath' => $filePath,
        'size' => filesize($filePath)
    ];
}

// Helper: Restore database from an SQL file
function restoreDatabaseBackup($conn, $filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return ['success' => false, 'message' => 'Backup file not found or not readable on server.'];
    }

    $conn->query("SET FOREIGN_KEY_CHECKS = 0;");

    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return ['success' => false, 'message' => 'Unable to read backup file.'];
    }

    $templine = '';
    $queryCount = 0;
    $errors = [];

    while (($line = fgets($handle)) !== false) {
        $trimmed = trim($line);
        if ($trimmed === '' || substr($trimmed, 0, 2) === '--' || substr($trimmed, 0, 2) === '/*' || substr($trimmed, 0, 1) === '#') {
            continue;
        }

        $templine .= $line;
        if (substr($trimmed, -1) === ';') {
            if (!$conn->query($templine)) {
                $errors[] = $conn->error;
                if (count($errors) > 20) {
                    break;
                }
            } else {
                $queryCount++;
            }
            $templine = '';
        }
    }
    fclose($handle);

    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");

    if (!empty($errors)) {
        return [
            'success' => false,
            'message' => 'Restored with ' . count($errors) . ' errors. First error: ' . $errors[0],
            'executed' => $queryCount
        ];
    }

    return [
        'success' => true,
        'message' => 'Database successfully restored (' . $queryCount . ' statements executed).',
        'executed' => $queryCount
    ];
}

// Handle direct GET file download
if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    $file = basename($_GET['file'] ?? '');
    if (!empty($file) && preg_match('/\.sql$/i', $file)) {
        $filePath = '../backups/' . $file;
        if (file_exists($filePath) && is_file($filePath)) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit();
        }
    }
    $_SESSION['error_message'] = 'Requested backup archive not found or invalid filename.';
    header('Location: settings.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_backup':
                @set_time_limit(300);
                @ini_set('memory_limit', '256M');
                $res = generateDatabaseBackup($conn, '../backups');
                if ($res['success']) {
                    $_SESSION['success_message'] = 'Database backup created successfully: ' . htmlspecialchars($res['filename']) . ' (' . formatBackupBytes($res['size']) . ')';
                } else {
                    $_SESSION['error_message'] = 'Backup failed: ' . htmlspecialchars($res['message']);
                }
                break;

            case 'restore_server_backup':
                @set_time_limit(300);
                @ini_set('memory_limit', '256M');
                $filename = basename($_POST['file_name'] ?? '');
                if (empty($filename) || !preg_match('/\.sql$/i', $filename)) {
                    $_SESSION['error_message'] = 'Invalid backup filename specified.';
                    break;
                }
                $filePath = '../backups/' . $filename;
                if (!file_exists($filePath)) {
                    $_SESSION['error_message'] = 'Backup file not found on server.';
                    break;
                }
                $res = restoreDatabaseBackup($conn, $filePath);
                if ($res['success']) {
                    $_SESSION['success_message'] = 'Database successfully restored from ' . htmlspecialchars($filename) . ' (' . $res['executed'] . ' statements executed)!';
                } else {
                    $_SESSION['error_message'] = 'Database restore failed: ' . htmlspecialchars($res['message']);
                }
                break;

            case 'upload_restore_backup':
                @set_time_limit(300);
                @ini_set('memory_limit', '256M');
                if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                    $_SESSION['error_message'] = 'Please select a valid .sql backup file to upload.';
                    break;
                }
                $origName = $_FILES['backup_file']['name'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if ($ext !== 'sql') {
                    $_SESSION['error_message'] = 'Invalid file type. Only .sql database backup files are permitted.';
                    break;
                }
                $backupDir = '../backups/';
                if (!is_dir($backupDir)) {
                    mkdir($backupDir, 0777, true);
                }
                $cleanName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
                $newFilename = 'closetgoo_uploaded_' . date('Y-m-d_His') . '_' . $cleanName . '.sql';
                $destination = $backupDir . $newFilename;
                if (!move_uploaded_file($_FILES['backup_file']['tmp_name'], $destination)) {
                    $_SESSION['error_message'] = 'Failed to move uploaded backup file to server directory.';
                    break;
                }
                $res = restoreDatabaseBackup($conn, $destination);
                if ($res['success']) {
                    $_SESSION['success_message'] = 'Database successfully restored from uploaded file: ' . htmlspecialchars($origName) . ' (' . $res['executed'] . ' statements executed)!';
                } else {
                    $_SESSION['error_message'] = 'Database restore encountered errors: ' . htmlspecialchars($res['message']);
                }
                break;

            case 'delete_backup':
                $filename = basename($_POST['backup_filename'] ?? '');
                if (empty($filename) || !preg_match('/\.sql$/i', $filename)) {
                    $_SESSION['error_message'] = 'Invalid backup filename specified.';
                    break;
                }
                $filePath = '../backups/' . $filename;
                if (file_exists($filePath) && is_file($filePath)) {
                    if (@unlink($filePath)) {
                        $_SESSION['success_message'] = 'Backup archive "' . htmlspecialchars($filename) . '" deleted successfully.';
                    } else {
                        $_SESSION['error_message'] = 'Unable to delete backup file from server disk.';
                    }
                } else {
                    $_SESSION['error_message'] = 'Backup file not found on server.';
                }
                break;

            case 'update_general_footer':
                $fields = [
                    'site_name' => trim($_POST['site_name'] ?? ''),
                    'site_email' => trim($_POST['site_email'] ?? ''),
                    'site_phone' => trim($_POST['site_phone'] ?? ''),
                    'site_address' => trim($_POST['site_address'] ?? ''),
                    'footer_about_title' => trim($_POST['footer_about_title'] ?? ''),
                    'footer_about_link' => trim($_POST['footer_about_link'] ?? ''),
                    'footer_terms_link' => trim($_POST['footer_terms_link'] ?? ''),
                    'footer_privacy_link' => trim($_POST['footer_privacy_link'] ?? ''),
                    'footer_contact_title' => trim($_POST['footer_contact_title'] ?? '')
                ];

                foreach ($fields as $key => $val) {
                    $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    if ($stmt) {
                        $stmt->bind_param("ss", $key, $val);
                        $stmt->execute();
                    }
                }
                $_SESSION['success_message'] = 'General & Footer Information updated successfully!';
                break;

            case 'update_inventory_settings':
                $lowStockThreshold = max(1, min(50, intval($_POST['low_stock_threshold'] ?? 2)));
                $rentalDuration = max(1, min(90, intval($_POST['rental_duration_days'] ?? 10)));
                $overduePenalty = max(1, min(100, intval($_POST['overdue_penalty_percent'] ?? 10)));
                $maxExtension = max(0, min(60, intval($_POST['max_extension_days'] ?? 7)));
                $extensionPrice = max(0, floatval($_POST['extension_price_per_day'] ?? 100));

                $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
                    setting_key VARCHAR(100) PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");

                $sysFields = [
                    'low_stock_threshold' => strval($lowStockThreshold),
                    'rental_duration_days' => strval($rentalDuration),
                    'overdue_penalty_percent' => strval($overduePenalty),
                    'max_extension_days' => strval($maxExtension),
                    'extension_price_per_day' => strval($extensionPrice)
                ];

                foreach ($sysFields as $k => $v) {
                    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    if ($stmt) {
                        $stmt->bind_param("sss", $k, $v, $v);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                $_SESSION['success_message'] = 'Inventory & Policy settings updated successfully (Low Stock Threshold: ' . $lowStockThreshold . ' items)!';
                break;

            case 'update_social_app':
                $fields = [
                    'footer_social_title' => trim($_POST['footer_social_title'] ?? ''),
                    'footer_facebook' => trim($_POST['footer_facebook'] ?? ''),
                    'footer_instagram' => trim($_POST['footer_instagram'] ?? '')
                ];

                foreach ($fields as $key => $val) {
                    $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    if ($stmt) {
                        $stmt->bind_param("ss", $key, $val);
                        $stmt->execute();
                    }
                }

                $_SESSION['success_message'] = 'Footer Social Links updated successfully!';
                break;

            case 'update_gcash':
                $gcash_number = trim($_POST['gcash_number'] ?? '');
                $gcash_account_name = trim($_POST['gcash_account_name'] ?? '');
                
                $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('gcash_number', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                if ($stmt) {
                    $stmt->bind_param("s", $gcash_number);
                    $stmt->execute();
                }

                $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('gcash_account_name', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                if ($stmt) {
                    $stmt->bind_param("s", $gcash_account_name);
                    $stmt->execute();
                }

                // Handle QR file upload
                if (isset($_FILES['gcash_qr_image']) && $_FILES['gcash_qr_image']['error'] === UPLOAD_ERR_OK) {
                    $fileTmp = $_FILES['gcash_qr_image']['tmp_name'];
                    $fileName = $_FILES['gcash_qr_image']['name'];
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    
                    if (in_array($ext, $allowedExts)) {
                        $uploadDir = '../uploads/gcash/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $newFileName = 'gcash_qr_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                        $destination = $uploadDir . $newFileName;
                        
                        if (move_uploaded_file($fileTmp, $destination)) {
                            $dbPath = 'uploads/gcash/' . $newFileName;
                            $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('gcash_qr_path', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                            if ($stmt) {
                                $stmt->bind_param("s", $dbPath);
                                $stmt->execute();
                            }
                        }
                    } else {
                        $_SESSION['error_message'] = 'Invalid image format for RCBC QR. Please upload JPG, PNG, WEBP, or GIF.';
                        header('Location: settings.php');
                        exit();
                    }
                }
                
                $_SESSION['success_message'] = 'RCBC QR & payment settings updated successfully!';
                break;

            case 'add_banner':
                $title = trim($_POST['banner_title'] ?? '');
                $link_url = trim($_POST['banner_link_url'] ?? '');
                $display_order = intval($_POST['banner_order'] ?? 0);
                $is_active = isset($_POST['banner_is_active']) ? 1 : 0;

                if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                    $fileTmp = $_FILES['banner_image']['tmp_name'];
                    $fileName = $_FILES['banner_image']['name'];
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                    if (in_array($ext, $allowedExts)) {
                        $uploadDir = '../uploads/banners/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $newFileName = 'banner_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                        $destination = $uploadDir . $newFileName;

                        if (move_uploaded_file($fileTmp, $destination)) {
                            $dbPath = 'uploads/banners/' . $newFileName;
                            $stmt = $conn->prepare("INSERT INTO banners (title, image_path, link_url, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
                            if ($stmt) {
                                $stmt->bind_param("sssii", $title, $dbPath, $link_url, $display_order, $is_active);
                                $stmt->execute();
                                $_SESSION['success_message'] = 'New homepage banner added successfully!';
                            } else {
                                $_SESSION['error_message'] = 'Database error adding banner.';
                            }
                        } else {
                            $_SESSION['error_message'] = 'Failed to upload banner image file.';
                        }
                    } else {
                        $_SESSION['error_message'] = 'Invalid image format. Allowed: JPG, JPEG, PNG, WEBP, GIF.';
                    }
                } else {
                    $_SESSION['error_message'] = 'Please choose a valid banner image to upload.';
                }
                break;

            case 'edit_banner':
                $banner_id = intval($_POST['banner_id'] ?? 0);
                $title = trim($_POST['banner_title'] ?? '');
                $link_url = trim($_POST['banner_link_url'] ?? '');
                $display_order = intval($_POST['banner_order'] ?? 0);
                $is_active = isset($_POST['banner_is_active']) ? 1 : 0;

                if ($banner_id > 0) {
                    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                        $fileTmp = $_FILES['banner_image']['tmp_name'];
                        $fileName = $_FILES['banner_image']['name'];
                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                        if (in_array($ext, $allowedExts)) {
                            $uploadDir = '../uploads/banners/';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            $newFileName = 'banner_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                            $destination = $uploadDir . $newFileName;

                            if (move_uploaded_file($fileTmp, $destination)) {
                                $newDbPath = 'uploads/banners/' . $newFileName;

                                // Cleanup old uploaded file if in uploads/banners/
                                $oldRes = $conn->query("SELECT image_path FROM banners WHERE id = $banner_id");
                                if ($oldRes && $oldRow = $oldRes->fetch_assoc()) {
                                    $oldImg = $oldRow['image_path'];
                                    if (strpos($oldImg, 'uploads/banners/') === 0 && file_exists('../' . $oldImg)) {
                                        @unlink('../' . $oldImg);
                                    }
                                }

                                $stmt = $conn->prepare("UPDATE banners SET title = ?, image_path = ?, link_url = ?, display_order = ?, is_active = ? WHERE id = ?");
                                if ($stmt) {
                                    $stmt->bind_param("sssiii", $title, $newDbPath, $link_url, $display_order, $is_active, $banner_id);
                                    $stmt->execute();
                                }
                            }
                        } else {
                            $_SESSION['error_message'] = 'Invalid image format. Other changes applied.';
                        }
                    } else {
                        $stmt = $conn->prepare("UPDATE banners SET title = ?, link_url = ?, display_order = ?, is_active = ? WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("ssiii", $title, $link_url, $display_order, $is_active, $banner_id);
                            $stmt->execute();
                        }
                    }
                    $_SESSION['success_message'] = 'Banner updated successfully!';
                }
                break;

            case 'delete_banner':
                $banner_id = intval($_POST['banner_id'] ?? 0);
                if ($banner_id > 0) {
                    $oldRes = $conn->query("SELECT image_path FROM banners WHERE id = $banner_id");
                    if ($oldRes && $oldRow = $oldRes->fetch_assoc()) {
                        $oldImg = $oldRow['image_path'];
                        if (strpos($oldImg, 'uploads/banners/') === 0 && file_exists('../' . $oldImg)) {
                            @unlink('../' . $oldImg);
                        }
                    }
                    $stmt = $conn->prepare("DELETE FROM banners WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $banner_id);
                        $stmt->execute();
                        $_SESSION['success_message'] = 'Banner deleted successfully!';
                    }
                }
                break;

            case 'toggle_banner_status':
                $banner_id = intval($_POST['banner_id'] ?? 0);
                if ($banner_id > 0) {
                    $stmt = $conn->prepare("UPDATE banners SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $banner_id);
                        $stmt->execute();
                        $_SESSION['success_message'] = 'Banner visibility toggled!';
                    }
                }
                break;
        }
        
        header('Location: settings.php');
        exit();
    }
}

// Fetch current app settings from database
$appSettings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM app_settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $appSettings[$row['setting_key']] = $row['setting_value'];
    }
}

// Fetch system settings (low stock threshold, rental duration, overdue penalty)
$lowStockThreshold = 2;
$rentalDurationDays = 10;
$overduePenaltyPercent = 10;
$maxExtensionDays = 7;
$extensionPricePerDay = 100;

try {
    $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('low_stock_threshold', '2')");
    $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('rental_duration_days', '10')");
    $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('overdue_penalty_percent', '10')");
    $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('max_extension_days', '7')");
    $conn->query("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('extension_price_per_day', '100')");

    $sysRes = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    if ($sysRes) {
        while ($sRow = $sysRes->fetch_assoc()) {
            if ($sRow['setting_key'] === 'low_stock_threshold') $lowStockThreshold = max(1, intval($sRow['setting_value']));
            if ($sRow['setting_key'] === 'rental_duration_days') $rentalDurationDays = max(1, intval($sRow['setting_value']));
            if ($sRow['setting_key'] === 'overdue_penalty_percent') $overduePenaltyPercent = max(1, intval($sRow['setting_value']));
            if ($sRow['setting_key'] === 'max_extension_days') $maxExtensionDays = max(0, intval($sRow['setting_value']));
            if ($sRow['setting_key'] === 'extension_price_per_day') $extensionPricePerDay = max(0, floatval($sRow['setting_value']));
        }
    }
} catch (Exception $e) {}

// General & Contact Defaults
$currentSiteName = !empty($appSettings['site_name']) ? $appSettings['site_name'] : 'ClosetGo';
$currentSiteEmail = $appSettings['site_email'] ?? 'jmbmanagementservices18@gmail.com';
$currentSitePhone = $appSettings['site_phone'] ?? '0998 868 9518';
$currentSiteAddress = $appSettings['site_address'] ?? 'Alaminos City, Pangasinan';

// Footer Column 1: About
$footerAboutTitle = $appSettings['footer_about_title'] ?? 'ABOUT CLOSETGOO';
$footerAboutLink = $appSettings['footer_about_link'] ?? 'javascript:void(0)';
$footerTermsLink = $appSettings['footer_terms_link'] ?? 'login_user/terms.php';
$footerPrivacyLink = $appSettings['footer_privacy_link'] ?? 'login_user/privacy.php';

// Footer Column 2: Contact
$footerContactTitle = $appSettings['footer_contact_title'] ?? 'CONTACT US';

// Footer Column 3: Social
$footerSocialTitle = $appSettings['footer_social_title'] ?? 'FOLLOW US';
$footerFacebook = $appSettings['footer_facebook'] ?? 'https://www.facebook.com/ACV.alf?mibextid=ZbWKwL';
$footerInstagram = $appSettings['footer_instagram'] ?? 'https://www.instagram.com/iam_alff?igsh=YWdjcmQ2MmJ0OXkw';

// RCBC Settings
$currentGcashQr = $appSettings['gcash_qr_path'] ?? 'images/gcash_qr_temp.jpg';
$currentGcashNum = $appSettings['gcash_number'] ?? '09123456789';
$currentGcashName = $appSettings['gcash_account_name'] ?? 'ClosetGoo Admin';

// Fetch Homepage Banners
$bannersList = [];
$bRes = $conn->query("SELECT * FROM banners ORDER BY display_order ASC, id ASC");
if ($bRes) {
    while ($bRow = $bRes->fetch_assoc()) {
        $bannersList[] = $bRow;
    }
}

// Fetch Database Backups from ../backups directory
$backupDir = '../backups';
$backupList = [];
if (is_dir($backupDir)) {
    $scannedFiles = @scandir($backupDir);
    if ($scannedFiles) {
        foreach ($scannedFiles as $file) {
            if ($file === '.' || $file === '..' || substr($file, -4) !== '.sql') {
                continue;
            }
            $fPath = $backupDir . '/' . $file;
            if (is_file($fPath)) {
                $backupList[] = [
                    'name' => $file,
                    'path' => $fPath,
                    'size' => filesize($fPath),
                    'date' => filemtime($fPath),
                ];
            }
        }
        // Sort descending by date (newest first)
        usort($backupList, function($a, $b) {
            return $b['date'] - $a['date'];
        });
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-modern.css">
    <link rel="stylesheet" href="../css/admin_mobile_css.css">
    <script src="../js/dark-mode.js"></script>
    <script>
    const buttonClickAudio = new Audio('../ringtone/button_click.MP3');
    buttonClickAudio.volume = 0.3;
    buttonClickAudio.preload = 'auto';
    
    function playButtonClickSound() {
        buttonClickAudio.currentTime = 0;
        buttonClickAudio.play().catch(e => console.log('Audio play failed:', e));
    }

    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('button, .btn, .btn-submit, .btn-back, a[href]');
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                if (!this.closest('.sidebar')) {
                    playButtonClickSound();
                }
            });
        });
    });
    </script>
    <style>
    /* Admin Settings Dark Luxury Theme */
    .shell {
        padding-top: 20px !important;
    }

    .settings-wrapper {
        padding: 0 0 40px;
    }

    .settings-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 0;
        border-bottom: none;
    }

    .settings-header-spacer {
        flex: 1;
        display: block;
    }

    .settings-header h1 {
        color: #ffd700;
        font-size: 1.85rem;
        font-weight: 700;
        margin: 0;
        text-align: center;
        flex: 2;
        letter-spacing: 0.5px;
    }

    .settings-header-actions {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    @media (max-width: 768px) {
        .shell {
            padding-top: 65px !important;
        }
        .settings-header {
            flex-direction: column;
            align-items: center;
            gap: 14px;
        }
        .settings-header-spacer {
            display: none;
        }
        .settings-header h1 {
            text-align: center;
            width: 100%;
        }
        .settings-header-actions {
            width: 100%;
            justify-content: center;
        }
        .settings-header-actions .btn-gold-add {
            width: 100%;
            justify-content: center;
        }
    }

    /* Big Add Admin / Staff Modal Styles */
    .admin-staff-modal-overlay {
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
        padding: 20px;
        box-sizing: border-box;
        animation: adminStaffModalFadeIn 0.2s ease;
    }

    @keyframes adminStaffModalFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .admin-staff-modal-container {
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
        animation: adminStaffModalScaleIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes adminStaffModalScaleIn {
        from { transform: scale(0.96); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    .admin-staff-modal-header {
        padding: 14px 22px;
        background: #1a1a1f;
        border-bottom: 1px solid rgba(212, 175, 55, 0.3);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    .admin-staff-modal-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.15rem;
        font-weight: 700;
        color: #ffd700;
        letter-spacing: 0.5px;
    }

    .admin-staff-modal-close-btn {
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

    .admin-staff-modal-close-btn:hover {
        color: #ffd700;
        border-color: #ffd700;
        background: rgba(212, 175, 55, 0.15);
        transform: rotate(90deg);
    }

    .admin-staff-modal-body {
        flex: 1;
        width: 100%;
        height: 100%;
        min-height: 0;
        overflow: hidden;
        position: relative;
        background: #14151a;
    }

    @media (max-width: 768px) {
        .admin-staff-modal-overlay {
            padding: 0;
        }
        .admin-staff-modal-container {
            width: 100vw;
            height: 100dvh;
            max-width: 100vw;
            max-height: 100dvh;
            border-radius: 0;
            border: none;
        }
        .admin-staff-modal-header {
            padding: 12px 16px;
        }
    }

    /* Banner Management Section */
    .banner-management-card {
        background: #1a1a1a;
        border: 1px solid #7c6300;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 6px 24px rgba(0, 0, 0, 0.6);
        margin-bottom: 30px;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .banner-management-card:hover {
        border-color: #ffd700;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.8), 0 0 15px rgba(255, 215, 0, 0.12);
    }

    .banner-card-header {
        padding: 18px 24px;
        background: #212328;
        border-bottom: 1px solid #7c6300;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 14px;
    }

    .banner-header-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .btn-gold-add {
        background: linear-gradient(135deg, #ffd700, #b8860b);
        color: #0a0a0a;
        border: none;
        padding: 9px 18px;
        border-radius: 8px;
        font-size: 0.88rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(255, 215, 0, 0.25);
    }

    .btn-gold-add:hover {
        background: linear-gradient(135deg, #ffe033, #cf9812);
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(255, 215, 0, 0.4);
    }

    .banner-table-container {
        padding: 0;
        overflow-x: auto;
    }

    .banner-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .banner-table th {
        background: #151515;
        color: #ffd700;
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 14px 18px;
        border-bottom: 1px solid #333;
    }

    .banner-table td {
        padding: 14px 18px;
        border-bottom: 1px solid #282828;
        color: #e5e5e5;
        font-size: 0.88rem;
        vertical-align: middle;
    }

    .banner-table tr:hover td {
        background: rgba(255, 215, 0, 0.03);
    }

    .banner-thumb-img {
        width: 140px;
        height: 65px;
        border-radius: 6px;
        object-fit: cover;
        background: #2a2a2a;
        border: 1px solid #7c6300;
        display: block;
        transition: transform 0.2s ease, border-color 0.2s;
    }

    .banner-thumb-img:hover {
        transform: scale(1.05);
        border-color: #ffd700;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .status-active {
        background: rgba(76, 175, 80, 0.18);
        color: #4ade80;
        border: 1px solid rgba(76, 175, 80, 0.4);
    }

    .status-inactive {
        background: rgba(255, 255, 255, 0.08);
        color: #888888;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .action-btn-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-action-icon {
        width: 34px;
        height: 34px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }

    .btn-action-toggle {
        background: #2a2a2a;
        color: #ffd700;
        border: 1px solid #555;
    }
    .btn-action-toggle:hover {
        background: #ffd700;
        color: #0a0a0a;
    }

    .btn-action-edit {
        background: rgba(0, 122, 255, 0.15);
        color: #38bdf8;
        border: 1px solid rgba(0, 122, 255, 0.35);
    }
    .btn-action-edit:hover {
        background: #007aff;
        color: #fff;
    }

    .btn-action-delete {
        background: rgba(255, 59, 48, 0.15);
        color: #f87171;
        border: 1px solid rgba(255, 59, 48, 0.35);
    }
    .btn-action-delete:hover {
        background: #ef4444;
        color: #fff;
    }

    /* Banner Desktop / Mobile visibility toggles */
    .banner-desktop-table {
        display: block;
    }
    .banner-mobile-cards {
        display: none;
    }

    .banner-counter-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 9px;
        border-radius: 12px;
        font-size: 0.74rem;
        font-weight: 700;
        background: rgba(255, 215, 0, 0.15);
        color: #ffd700;
        border: 1px solid rgba(255, 215, 0, 0.35);
        margin-left: 8px;
        vertical-align: middle;
    }

    /* Modal Responsive Form Row */
    .modal-flex-row {
        display: flex;
        gap: 16px;
        align-items: center;
    }
    .modal-flex-row .form-group-custom {
        flex: 1;
        margin-bottom: 0;
    }
    .modal-flex-row .modal-checkbox-wrap {
        padding-top: 22px;
    }

    /* Mobile Banner Management Responsive Overrides */
    @media (max-width: 768px) {
        .banner-desktop-table {
            display: none !important;
        }

        .banner-mobile-cards {
            display: flex !important;
            flex-direction: column !important;
            gap: 14px;
            padding: 14px 12px;
        }

        .banner-table-container {
            overflow-x: visible !important;
        }

        .banner-card-header {
            padding: 14px 14px !important;
            gap: 12px !important;
            flex-direction: column !important;
            align-items: stretch !important;
        }

        .banner-header-left {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            gap: 12px !important;
            width: 100% !important;
        }

        .banner-header-left .custom-card-icon {
            width: 42px !important;
            height: 42px !important;
            font-size: 1.15rem !important;
            flex-shrink: 0;
        }

        .banner-header-left .custom-card-title {
            flex: 1;
            min-width: 0;
        }

        .banner-header-left .custom-card-title h3 {
            font-size: 1rem !important;
            line-height: 1.25;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .banner-header-left .custom-card-title p {
            font-size: 0.74rem !important;
            color: #888;
            margin-top: 2px;
        }

        .btn-gold-add {
            width: 100% !important;
            justify-content: center !important;
            padding: 11px 16px !important;
            font-size: 0.88rem !important;
            border-radius: 8px !important;
        }

        /* Mobile Card Item */
        .banner-m-card {
            background: linear-gradient(145deg, #22252c, #181a1f);
            border: 1px solid rgba(255, 215, 0, 0.22);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            transition: border-color 0.2s ease, transform 0.15s ease;
        }

        .banner-m-card.is-inactive {
            border-color: rgba(255, 255, 255, 0.12);
            opacity: 0.85;
            background: linear-gradient(145deg, #1c1d22, #141518);
        }

        .banner-m-card:active {
            transform: scale(0.995);
        }

        /* Top row with Order & Status */
        .banner-m-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            background: rgba(0, 0, 0, 0.25);
            border-bottom: 1px solid rgba(255, 215, 0, 0.12);
        }

        .banner-m-order-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.76rem;
            font-weight: 700;
            color: #ffd700;
            background: rgba(255, 215, 0, 0.12);
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid rgba(255, 215, 0, 0.25);
        }

        .banner-m-status .status-badge {
            font-size: 0.72rem !important;
            padding: 3px 9px !important;
        }

        /* Visual Banner Thumbnail */
        .banner-m-visual {
            position: relative;
            width: 100%;
            height: 125px;
            background: #0d0e12;
            overflow: hidden;
            border-bottom: 1px solid rgba(255, 215, 0, 0.15);
        }

        .banner-m-img-link {
            display: block;
            width: 100%;
            height: 100%;
            position: relative;
            text-decoration: none;
        }

        .banner-m-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.25s ease;
        }

        .banner-m-img-link:hover .banner-m-img,
        .banner-m-img-link:active .banner-m-img {
            transform: scale(1.03);
        }

        .banner-m-zoom-pill {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.75);
            color: #ffd700;
            border: 1px solid rgba(255, 215, 0, 0.4);
            font-size: 0.7rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            pointer-events: none;
        }

        /* Body Details */
        .banner-m-body {
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .banner-m-title {
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 700;
            line-height: 1.3;
            word-break: break-word;
        }

        .banner-m-path {
            font-size: 0.72rem;
            color: #888888;
            word-break: break-all;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .banner-m-path i {
            color: #ffd700;
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .banner-m-link-box {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
            font-size: 0.76rem;
            padding: 6px 10px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            min-width: 0;
        }

        .banner-m-link-label {
            color: #aaa;
            font-weight: 600;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .banner-m-link-url {
            color: #38bdf8;
            text-decoration: underline;
            word-break: break-all;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            flex: 1;
            min-width: 0;
        }

        .banner-m-no-link {
            color: #777;
            font-style: italic;
        }

        /* Action Buttons Grid */
        .banner-m-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            padding: 10px 12px 12px;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 215, 0, 0.1);
        }

        .banner-m-act-form {
            margin: 0;
            width: 100%;
            display: flex;
        }

        .banner-m-act-btn {
            width: 100%;
            height: 38px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s ease;
            text-decoration: none;
            box-sizing: border-box;
            padding: 0 6px;
        }

        .banner-m-act-btn:active {
            transform: scale(0.96);
        }

        .banner-m-act-toggle.is-active-btn {
            background: rgba(255, 215, 0, 0.12);
            color: #ffd700;
            border-color: rgba(255, 215, 0, 0.35);
        }
        .banner-m-act-toggle.is-active-btn:active {
            background: rgba(255, 215, 0, 0.25);
        }

        .banner-m-act-toggle.is-inactive-btn {
            background: rgba(74, 222, 128, 0.12);
            color: #4ade80;
            border-color: rgba(74, 222, 128, 0.35);
        }
        .banner-m-act-toggle.is-inactive-btn:active {
            background: rgba(74, 222, 128, 0.25);
        }

        .banner-m-act-edit {
            background: rgba(0, 122, 255, 0.15);
            color: #38bdf8;
            border-color: rgba(0, 122, 255, 0.35);
        }
        .banner-m-act-edit:active {
            background: rgba(0, 122, 255, 0.3);
            color: #ffffff;
        }

        .banner-m-act-delete {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.35);
        }
        .banner-m-act-delete:active {
            background: rgba(239, 68, 68, 0.3);
            color: #ffffff;
        }
    }

    @media (max-width: 480px) {
        .modal-flex-row {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 10px !important;
        }
        .modal-flex-row .modal-checkbox-wrap {
            padding-top: 0 !important;
        }
        .banner-m-actions {
            gap: 6px;
            padding: 8px 10px 10px;
        }
        .banner-m-act-btn {
            height: 36px;
            font-size: 0.74rem;
            gap: 4px;
        }
    }

    /* Settings 3-Per-Row Grid Cards */
    .settings-modules-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-top: 15px;
        margin-bottom: 30px;
    }

    .settings-module-box {
        background: rgba(26, 26, 26, 0.7);
        border: 1px solid rgba(255, 215, 0, 0.25);
        border-radius: 14px;
        padding: 22px 20px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 16px;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: inset 0 0 20px rgba(255, 215, 0, 0.08), 0 4px 20px rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(10px);
    }

    .settings-module-box::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, transparent, #ffd700, transparent);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .settings-module-box:hover {
        transform: translateY(-4px);
        border-color: rgba(255, 215, 0, 0.6);
        box-shadow: inset 0 0 25px rgba(255, 215, 0, 0.2), 0 8px 30px rgba(0, 0, 0, 0.6);
    }

    .settings-module-box:hover::before {
        opacity: 1;
    }

    .settings-box-top {
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }

    .settings-box-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: rgba(255, 215, 0, 0.12);
        border: 1px solid rgba(255, 215, 0, 0.35);
        color: #ffd700;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
        transition: all 0.3s ease;
    }

    .settings-module-box:hover .settings-box-icon {
        background: rgba(255, 215, 0, 0.22);
        transform: scale(1.06);
        box-shadow: 0 0 15px rgba(255, 215, 0, 0.35);
    }

    .settings-box-info {
        flex: 1;
        min-width: 0;
    }

    .settings-box-title {
        color: #ffffff;
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0 0 5px 0;
        line-height: 1.3;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .settings-box-desc {
        color: #999999;
        font-size: 0.8rem;
        line-height: 1.45;
        margin: 0;
    }

    .settings-box-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 14px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        margin-top: auto;
    }

    .settings-box-pill {
        font-size: 0.75rem;
        color: #ffd700;
        background: rgba(255, 215, 0, 0.1);
        border: 1px solid rgba(255, 215, 0, 0.25);
        border-radius: 6px;
        padding: 3px 8px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .settings-box-btn {
        font-size: 0.8rem;
        color: #e5e5e5;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }

    .settings-module-box:hover .settings-box-btn {
        color: #ffd700;
        transform: translateX(4px);
    }

    @media (max-width: 1024px) {
        .settings-modules-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
    }

    @media (max-width: 680px) {
        .settings-modules-grid {
            grid-template-columns: 1fr;
            gap: 14px;
        }
    }

    /* Modal Styles */
    .custom-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.82);
        backdrop-filter: blur(5px);
        z-index: 99990;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
    }

    .custom-modal-overlay.custom-submodal {
        z-index: 100000 !important;
    }

    .custom-modal-overlay.active {
        display: flex;
    }

    .custom-modal-box {
        background: #18191f;
        border: 1px solid #ffd700;
        border-radius: 14px;
        width: 100%;
        max-width: 540px;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.9), 0 0 25px rgba(255, 215, 0, 0.2);
        overflow: hidden;
        animation: modalFadeIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
    }

    .custom-modal-box.modal-large {
        max-width: 1120px;
        width: 95%;
    }

    .custom-modal-box.modal-medium {
        max-width: 720px;
        width: 95%;
    }

    .custom-modal-box.modal-standard {
        max-width: 620px;
        width: 95%;
    }

    .custom-modal-scrollable {
        max-height: calc(88vh - 75px);
        overflow-y: auto;
        padding: 22px 24px;
    }

    .custom-modal-scrollable::-webkit-scrollbar {
        width: 8px;
    }
    .custom-modal-scrollable::-webkit-scrollbar-track {
        background: #141418;
    }
    .custom-modal-scrollable::-webkit-scrollbar-thumb {
        background: #333;
        border-radius: 4px;
    }
    .custom-modal-scrollable::-webkit-scrollbar-thumb:hover {
        background: #ffd700;
    }

    .custom-modal-scrollable .banner-management-card {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        margin-bottom: 0 !important;
    }
    .custom-modal-scrollable .banner-card-header {
        border-radius: 10px;
        margin-bottom: 16px;
    }
    .custom-modal-scrollable .backup-tools-container {
        margin-bottom: 20px;
    }

    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.95) translateY(-10px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }

    .custom-modal-header {
        padding: 18px 24px;
        background: #272727;
        border-bottom: 1px solid #444;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .custom-modal-header h3 {
        color: #ffd700;
        margin: 0;
        font-size: 1.15rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-close-btn {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: #ffffff;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 15px;
        padding: 0;
        transition: all 0.2s ease;
        box-sizing: border-box;
        flex-shrink: 0;
        line-height: 1;
    }
    .modal-close-btn:hover {
        background: rgba(220, 53, 69, 0.85);
        border-color: rgba(220, 53, 69, 1);
        color: #ffffff;
        transform: rotate(90deg);
    }

    .custom-modal-body {
        padding: 24px;
        max-height: 80vh;
        overflow-y: auto;
    }

    .custom-modal-footer {
        padding: 16px 24px;
        background: #252525;
        border-top: 1px solid #383838;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
    }

    .btn-modal-cancel {
        background: #333;
        color: #ccc;
        border: 1px solid #555;
        padding: 10px 18px;
        border-radius: 8px;
        font-size: 0.88rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-modal-cancel:hover {
        background: #444;
        color: #fff;
    }

    .banner-live-preview {
        width: 100%;
        height: 140px;
        border-radius: 8px;
        border: 2px dashed #7c6300;
        background: #151515;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        margin-top: 8px;
        position: relative;
    }

    .banner-live-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: none;
    }

    .banner-live-preview.has-image img {
        display: block;
    }

    .banner-live-preview.has-image .preview-placeholder {
        display: none;
    }

    .preview-placeholder {
        color: #777;
        font-size: 0.84rem;
        text-align: center;
    }

    .preview-placeholder i {
        font-size: 1.8rem;
        display: block;
        margin-bottom: 6px;
        color: #555;
    }

    .settings-grid-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
    }

    .custom-settings-card {
        background: #1a1a1a;
        border: 1px solid #7c6300;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 6px 24px rgba(0, 0, 0, 0.6);
        transition: transform 0.2s ease, border-color 0.2s ease;
        display: flex;
        flex-direction: column;
    }

    .custom-settings-card:hover {
        border-color: #ffd700;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.8), 0 0 15px rgba(255, 215, 0, 0.15);
    }

    .custom-card-header {
        padding: 18px 22px;
        background: #212328;
        border-bottom: 1px solid #7c6300;
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .custom-card-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 215, 0, 0.15);
        color: #ffd700;
        font-size: 1.25rem;
        border: 1px solid rgba(255, 215, 0, 0.3);
        flex-shrink: 0;
    }

    .custom-card-icon.gcash-icon {
        background: rgba(0, 122, 255, 0.15);
        color: #007aff;
        border: 1px solid rgba(0, 122, 255, 0.35);
    }

    .custom-card-icon.social-icon {
        background: rgba(233, 30, 99, 0.15);
        color: #e91e63;
        border: 1px solid rgba(233, 30, 99, 0.35);
    }

    .custom-card-icon.banner-icon {
        background: rgba(255, 152, 0, 0.15);
        color: #ff9800;
        border: 1px solid rgba(255, 152, 0, 0.35);
    }

    .custom-card-title h3 {
        color: #ffd700;
        font-size: 1.12rem;
        font-weight: 700;
        margin: 0 0 3px 0;
    }

    .custom-card-title p {
        color: #999999;
        font-size: 0.8rem;
        margin: 0;
    }

    .custom-card-body {
        padding: 22px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .form-section-divider {
        font-size: 0.78rem;
        font-weight: 700;
        color: #ffd700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 12px 0 10px;
        padding-bottom: 4px;
        border-bottom: 1px dashed rgba(255, 215, 0, 0.2);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .form-group-custom {
        margin-bottom: 15px;
    }

    .form-group-custom label {
        display: block;
        color: #e5e5e5;
        font-weight: 600;
        font-size: 0.84rem;
        margin-bottom: 6px;
    }

    .form-group-custom label i {
        color: #ffd700;
        margin-right: 5px;
    }

    .form-input-custom,
    .form-textarea-custom {
        width: 100%;
        padding: 10px 13px;
        background: #2a2a2a;
        border: 1px solid #444444;
        border-radius: 8px;
        font-size: 0.88rem;
        color: #ffffff;
        box-sizing: border-box;
        transition: border-color 0.2s, box-shadow 0.2s;
        font-family: inherit;
    }

    .form-input-custom:focus,
    .form-textarea-custom:focus {
        outline: none;
        border-color: #ffd700;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15);
        background: #303030;
    }

    .form-textarea-custom {
        resize: vertical;
        min-height: 70px;
    }

    .custom-file-input {
        width: 100%;
        padding: 9px 12px;
        background: #2a2a2a;
        border: 1px dashed #7c6300;
        border-radius: 8px;
        color: #ccc;
        box-sizing: border-box;
        cursor: pointer;
        font-size: 0.84rem;
    }

    .btn-gold-save {
        width: 100%;
        background: linear-gradient(135deg, #ffd700, #b8860b);
        color: #0a0a0a;
        border: none;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 0.92rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 15px;
        box-shadow: 0 4px 14px rgba(255, 215, 0, 0.25);
    }

    .btn-gold-save:hover {
        background: linear-gradient(135deg, #ffe033, #cf9812);
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(255, 215, 0, 0.4);
    }

    .qr-preview-box {
        display: flex;
        align-items: center;
        gap: 14px;
        background: #212328;
        border: 1px solid #7c6300;
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 12px;
    }

    .qr-thumb-frame {
        width: 84px;
        height: 84px;
        border-radius: 8px;
        background: #ffffff;
        border: 2px solid #ffd700;
        overflow: hidden;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .qr-thumb-frame img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .qr-info-text {
        flex: 1;
        min-width: 0;
    }

    .qr-info-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: #ffd700;
    }

    .qr-info-path {
        font-size: 0.72rem;
        color: #888888;
        margin-top: 2px;
        word-break: break-all;
    }

    .qr-info-status {
        font-size: 0.74rem;
        color: #4cd964;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
        font-weight: 600;
    }

    .alert-banner {
        padding: 12px 18px;
        border-radius: 10px;
        margin-bottom: 22px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .alert-success {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
        border: 1px solid rgba(34, 197, 94, 0.4);
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.4);
    }

    /* Backup Management Section Styling */
    .backup-management-card {
        margin-top: 10px;
    }

    .backup-tools-container {
        padding: 20px 24px;
        background: #18191d;
        border-bottom: 1px solid rgba(255, 215, 0, 0.2);
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .backup-upload-box {
        background: #212328;
        border: 1px solid rgba(255, 215, 0, 0.25);
        border-radius: 10px;
        padding: 16px 18px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .backup-tool-title {
        font-size: 0.92rem;
        font-weight: 700;
        color: #ffffff;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
    }

    .backup-tool-desc {
        color: #aaa;
        font-size: 0.8rem;
        line-height: 1.45;
        margin: 0 0 14px 0;
    }

    .backup-tool-desc code {
        background: rgba(255, 215, 0, 0.15);
        color: #ffd700;
        padding: 2px 5px;
        border-radius: 4px;
        font-size: 0.82rem;
    }

    .backup-upload-row {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .backup-file-chooser {
        flex: 1;
        min-width: 0;
    }

    .btn-restore-action {
        background: linear-gradient(135deg, #0284c7, #0369a1);
        color: #ffffff;
        border: 1px solid #38bdf8;
        padding: 9px 16px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
    }

    .btn-restore-action:hover {
        background: linear-gradient(135deg, #0ea5e9, #0284c7);
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(14, 165, 233, 0.45);
    }

    .backup-info-card {
        background: #212328;
        border: 1px solid rgba(56, 189, 248, 0.25);
        border-radius: 10px;
        padding: 16px 18px;
    }

    .backup-info-header {
        font-size: 0.92rem;
        font-weight: 700;
        color: #38bdf8;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
    }

    .backup-info-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .backup-info-list li {
        font-size: 0.8rem;
        color: #bbb;
        line-height: 1.45;
        display: flex;
        align-items: flex-start;
        gap: 8px;
    }

    .backup-info-list li i {
        margin-top: 3px;
        flex-shrink: 0;
    }

    .backup-info-list li code {
        background: rgba(255, 255, 255, 0.08);
        color: #ffd700;
        padding: 1px 4px;
        border-radius: 4px;
    }

    .backup-file-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        background: rgba(56, 189, 248, 0.12);
        color: #38bdf8;
        border: 1px solid rgba(56, 189, 248, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .backup-size-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 9px;
        border-radius: 6px;
        font-size: 0.78rem;
        font-weight: 700;
        background: rgba(255, 215, 0, 0.12);
        color: #ffd700;
        border: 1px solid rgba(255, 215, 0, 0.25);
    }

    .btn-action-download {
        background: rgba(34, 197, 94, 0.15);
        color: #4ade80;
        border: 1px solid rgba(34, 197, 94, 0.35);
    }
    .btn-action-download:hover {
        background: #22c55e;
        color: #000;
    }

    .btn-action-restore {
        background: rgba(245, 158, 11, 0.15);
        color: #fbbf24;
        border: 1px solid rgba(245, 158, 11, 0.35);
    }
    .btn-action-restore:hover {
        background: #f59e0b;
        color: #000;
    }

    @media (max-width: 900px) {
        .backup-tools-container {
            grid-template-columns: 1fr;
            padding: 16px;
        }
        .backup-upload-row {
            flex-direction: column;
            align-items: stretch;
        }
        .btn-restore-action {
            justify-content: center;
        }
    }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="shell">
        <div class="settings-wrapper">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert-banner alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert-banner alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></span>
                </div>
            <?php endif; ?>


            <!-- 6 MODULES IN A 3-PER-ROW GRID -->
            <div class="settings-modules-grid">
                <!-- 1. Homepage Promo Banners -->
                <div class="settings-module-box" onclick="openModal('modalHomepageBanners')">
                    <div class="settings-box-top">
                        <div class="settings-box-icon">
                            <i class="fas fa-images"></i>
                        </div>
                        <div class="settings-box-info">
                            <div class="settings-box-title">
                                <span>Homepage Promo Banners</span>
                            </div>
                            <p class="settings-box-desc">Add, edit, reorder, or delete banners displayed in the customer hero slider</p>
                        </div>
                    </div>
                    <div class="settings-box-bottom">
                        <span class="settings-box-pill"><i class="fas fa-layer-group"></i> <?php echo count($bannersList); ?> <?php echo count($bannersList) === 1 ? 'Banner' : 'Banners'; ?></span>
                        <span class="settings-box-btn">Manage <i class="fas fa-chevron-right"></i></span>
                    </div>
                </div>

                <!-- 2. Footer: About & Contact -->
                <div class="settings-module-box" onclick="openModal('modalAboutContact')">
                    <div class="settings-box-top">
                        <div class="settings-box-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="settings-box-info">
                            <div class="settings-box-title">
                                <span>Footer: About &amp; Contact</span>
                            </div>
                            <p class="settings-box-desc">Store brand name, about section, links, physical address &amp; hotline</p>
                        </div>
                    </div>
                    <div class="settings-box-bottom">
                        <span class="settings-box-pill"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($currentSiteName); ?></span>
                        <span class="settings-box-btn">Configure <i class="fas fa-chevron-right"></i></span>
                    </div>
                </div>

                <!-- 3. Footer: Social Links -->
                <div class="settings-module-box" onclick="openModal('modalSocialLinks')">
                    <div class="settings-box-top">
                        <div class="settings-box-icon" style="background: rgba(228, 64, 95, 0.15); color: #e4405f; border-color: rgba(228, 64, 95, 0.35);">
                            <i class="fas fa-share-alt"></i>
                        </div>
                        <div class="settings-box-info">
                            <div class="settings-box-title">
                                <span>Footer: Social Links</span>
                            </div>
                            <p class="settings-box-desc">Configure Facebook, Instagram and connected social media profiles</p>
                        </div>
                    </div>
                    <div class="settings-box-bottom">
                        <span class="settings-box-pill"><i class="fab fa-facebook-f"></i> <i class="fab fa-instagram"></i> Social Media</span>
                        <span class="settings-box-btn">Configure <i class="fas fa-chevron-right"></i></span>
                    </div>
                </div>

                <!-- 4. RCBC Payment Settings -->
                <div class="settings-module-box" onclick="openModal('modalRcbcPayment')">
                    <div class="settings-box-top">
                        <div class="settings-box-icon" style="background: rgba(0, 122, 255, 0.15); color: #38bdf8; border-color: rgba(0, 122, 255, 0.35);">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <div class="settings-box-info">
                            <div class="settings-box-title">
                                <span>RCBC Payment Settings</span>
                            </div>
                            <p class="settings-box-desc">Update checkout payment QR code screenshot and RCBC account details</p>
                        </div>
                    </div>
                    <div class="settings-box-bottom">
                        <span class="settings-box-pill"><i class="fas fa-university"></i> <?php echo htmlspecialchars($currentGcashNum ?: 'Configured'); ?></span>
                        <span class="settings-box-btn">Configure <i class="fas fa-chevron-right"></i></span>
                    </div>
                </div>

                <!-- 5. Admin & Staff Accounts -->
                <div class="settings-module-box" onclick="openModal('modalAdminStaff')">
                    <div class="settings-box-top">
                        <div class="settings-box-icon" style="background: rgba(124, 99, 0, 0.2); color: #ffd700; border-color: rgba(255, 215, 0, 0.4);">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="settings-box-info">
                            <div class="settings-box-title">
                                <span>Admin &amp; Staff Accounts</span>
                            </div>
                            <p class="settings-box-desc">Create administrators and staff credentials, view roster and assign roles</p>
                        </div>
                    </div>
                    <div class="settings-box-bottom">
                        <span class="settings-box-pill"><i class="fas fa-users-cog"></i> Access &amp; Roles</span>
                        <span class="settings-box-btn">Manage <i class="fas fa-chevron-right"></i></span>
                    </div>
                </div>

                <!-- 6. Database Backup & System Restore -->
                <div class="settings-module-box" onclick="openModal('modalDatabaseBackup')">
                    <div class="settings-box-top">
                        <div class="settings-box-icon" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8; border-color: rgba(56, 189, 248, 0.4);">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="settings-box-info">
                            <div class="settings-box-title">
                                <span>Database Backup &amp; System Restore</span>
                            </div>
                            <p class="settings-box-desc">Generate complete SQL snapshots, download archives, or restore store data</p>
                        </div>
                    </div>
                    <div class="settings-box-bottom">
                        <span class="settings-box-pill"><i class="fas fa-server"></i> <?php echo count($backupList); ?> <?php echo count($backupList) === 1 ? 'Archive' : 'Archives'; ?></span>
                        <span class="settings-box-btn">Manage <i class="fas fa-chevron-right"></i></span>
                    </div>
                </div>

                <!-- 7. Inventory & Low Stock Policy -->
                <div class="settings-module-box" onclick="openModal('modalInventorySettings')">
                    <div class="settings-box-top">
                        <div class="settings-box-icon" style="background: rgba(255, 152, 0, 0.15); color: #ff9800; border-color: rgba(255, 152, 0, 0.4);">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="settings-box-info">
                            <div class="settings-box-title">
                                <span>Inventory &amp; Low Stock Threshold</span>
                            </div>
                            <p class="settings-box-desc">Configure system low stock alert limit, rental duration, and overdue fee policy</p>
                        </div>
                    </div>
                    <div class="settings-box-bottom">
                        <span class="settings-box-pill" style="color: #ff9800;"><i class="fas fa-exclamation-triangle"></i> Alert at &le; <?php echo $lowStockThreshold; ?> items</span>
                        <span class="settings-box-btn">Configure <i class="fas fa-chevron-right"></i></span>
                    </div>
                </div>
            </div>

            <!-- MODAL 7: INVENTORY & LOW STOCK THRESHOLD -->
            <div id="modalInventorySettings" class="custom-modal-overlay">
                <div class="custom-modal-box">
                    <div class="custom-modal-header">
                        <h3><i class="fas fa-boxes" style="color: #ff9800;"></i> System Inventory &amp; Low Stock Threshold</h3>
                        <button type="button" class="modal-close-btn" onclick="closeModal('modalInventorySettings')" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <form method="POST" action="settings.php" style="padding: 24px;">
                        <input type="hidden" name="action" value="update_inventory_settings">

                        <div style="background: rgba(255, 152, 0, 0.08); border: 1px solid rgba(255, 152, 0, 0.3); border-radius: 10px; padding: 14px; margin-bottom: 20px;">
                            <div style="font-size: 0.9rem; font-weight: 700; color: #ff9800; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-exclamation-triangle"></i> System Low Stock Alert Threshold
                            </div>
                            <p style="font-size: 0.8rem; color: #ccc; margin: 0; line-height: 1.4;">
                                Set the global threshold number. Products with available stock at or below this value are automatically flagged as "Low Stock" across the admin calendar, gowns inventory, and dashboard.
                            </p>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #ffd700; margin-bottom: 8px; text-transform: uppercase;">
                                <i class="fas fa-box-open"></i> Low Stock Threshold (Quantity)
                            </label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="number" name="low_stock_threshold" id="settingsLowStockInput" value="<?php echo $lowStockThreshold; ?>" min="1" max="50" required style="width: 120px; padding: 10px 14px; border-radius: 8px; background: #1c1d22; border: 1px solid rgba(255,152,0,0.4); color: #ff9800; font-size: 1.3rem; font-weight: 700; text-align: center;">
                                <span style="color: #aaa; font-size: 0.85rem;">Items or fewer triggers Low Stock</span>
                            </div>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px;">
                                <button type="button" style="background: #282a32; color: #ccc; border: 1px solid #3c404b; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; cursor: pointer;" onclick="document.getElementById('settingsLowStockInput').value = 1">1 Item</button>
                                <button type="button" style="background: #282a32; color: #ccc; border: 1px solid #3c404b; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; cursor: pointer;" onclick="document.getElementById('settingsLowStockInput').value = 2">2 Items (Default)</button>
                                <button type="button" style="background: #282a32; color: #ccc; border: 1px solid #3c404b; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; cursor: pointer;" onclick="document.getElementById('settingsLowStockInput').value = 3">3 Items</button>
                                <button type="button" style="background: #282a32; color: #ccc; border: 1px solid #3c404b; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; cursor: pointer;" onclick="document.getElementById('settingsLowStockInput').value = 5">5 Items</button>
                                <button type="button" style="background: #282a32; color: #ccc; border: 1px solid #3c404b; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; cursor: pointer;" onclick="document.getElementById('settingsLowStockInput').value = 10">10 Items</button>
                            </div>
                        </div>

                        <div style="border-top: 1px solid #2f323a; padding-top: 18px; margin-top: 18px;">
                            <h4 style="color: #ffd700; font-size: 0.9rem; margin-bottom: 14px;">Store Policy Configurations</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                <div class="form-group">
                                    <label style="display: block; font-size: 0.76rem; color: #aaa; margin-bottom: 6px;">Rental Duration (Days)</label>
                                    <input type="number" name="rental_duration_days" value="<?php echo $rentalDurationDays; ?>" min="1" max="90" style="width: 100%; padding: 8px 10px; border-radius: 6px; background: #18191f; border: 1px solid #383b45; color: #fff; box-sizing: border-box;">
                                </div>
                                <div class="form-group">
                                    <label style="display: block; font-size: 0.76rem; color: #aaa; margin-bottom: 6px;">Overdue Penalty (%/Day)</label>
                                    <input type="number" name="overdue_penalty_percent" value="<?php echo $overduePenaltyPercent; ?>" min="1" max="100" style="width: 100%; padding: 8px 10px; border-radius: 6px; background: #18191f; border: 1px solid #383b45; color: #fff; box-sizing: border-box;">
                                </div>
                                <div class="form-group">
                                    <label style="display: block; font-size: 0.76rem; color: #aaa; margin-bottom: 6px;">Max Extension (Days)</label>
                                    <input type="number" name="max_extension_days" value="<?php echo $maxExtensionDays; ?>" min="0" max="60" style="width: 100%; padding: 8px 10px; border-radius: 6px; background: #18191f; border: 1px solid #383b45; color: #fff; box-sizing: border-box;">
                                </div>
                                <div class="form-group">
                                    <label style="display: block; font-size: 0.76rem; color: #aaa; margin-bottom: 6px;">Extension Fee (₱/Day)</label>
                                    <input type="number" name="extension_price_per_day" value="<?php echo $extensionPricePerDay; ?>" min="0" max="5000" style="width: 100%; padding: 8px 10px; border-radius: 6px; background: #18191f; border: 1px solid #383b45; color: #fff; box-sizing: border-box;">
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
                            <button type="button" class="btn-cancel" onclick="closeModal('modalInventorySettings')" style="padding: 10px 18px; border-radius: 8px; background: #262830; color: #bbb; border: 1px solid #383b45; cursor: pointer;">Cancel</button>
                            <button type="submit" class="btn-submit" style="padding: 10px 22px; border-radius: 8px; background: linear-gradient(135deg, #ffd700, #ffb300); color: #000; font-weight: 700; border: none; cursor: pointer;">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL 1: HOMEPAGE PROMOTIONAL BANNERS -->
            <div id="modalHomepageBanners" class="custom-modal-overlay">
                <div class="custom-modal-box modal-large">
                    <div class="custom-modal-header">
                        <h3><i class="fas fa-images"></i> Homepage Promo Banners (index.php)</h3>
                        <button type="button" class="modal-close-btn" onclick="closeModal('modalHomepageBanners')" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="custom-modal-scrollable">
                        <div class="banner-management-card">
                <div class="banner-card-header">
                    <div class="banner-header-left">
                        <div class="custom-card-icon banner-icon">
                            <i class="fas fa-images"></i>
                        </div>
                        <div class="custom-card-title">
                            <h3>Homepage Promo Banners (index.php) <span class="banner-counter-badge"><?php echo count($bannersList); ?> <?php echo count($bannersList) === 1 ? 'Banner' : 'Banners'; ?></span></h3>
                            <p>Add, edit, reorder, or delete banners displayed in the customer hero slider</p>
                        </div>
                    </div>
                    <button type="button" class="btn-gold-add" onclick="openAddBannerModal()">
                        <i class="fas fa-plus"></i> Add New Banner
                    </button>
                </div>

                <div class="banner-table-container">
                    <?php if (!empty($bannersList)): ?>
                        <!-- DESKTOP TABLE VIEW (Visible on tablet & desktop) -->
                        <div class="banner-desktop-table">
                            <table class="banner-table">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">Order</th>
                                        <th style="width: 170px;">Banner Image</th>
                                        <th>Title / Label</th>
                                        <th>Target Link</th>
                                        <th style="width: 100px;">Status</th>
                                        <th style="width: 140px; text-align: right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bannersList as $banner): ?>
                                        <tr>
                                            <td>
                                                <span style="font-weight: 700; color: #ffd700;"><?php echo intval($banner['display_order']); ?></span>
                                            </td>
                                            <td>
                                                <a href="../<?php echo htmlspecialchars($banner['image_path']); ?>" target="_blank" title="View Full Image">
                                                    <img src="../<?php echo htmlspecialchars($banner['image_path']); ?>" alt="Banner" class="banner-thumb-img">
                                                </a>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($banner['title'] ?: 'Untitled Banner'); ?></div>
                                                <div style="font-size: 0.75rem; color: #888; margin-top: 2px;"><?php echo htmlspecialchars($banner['image_path']); ?></div>
                                            </td>
                                            <td>
                                                <?php if (!empty($banner['link_url'])): ?>
                                                    <a href="<?php echo htmlspecialchars($banner['link_url']); ?>" target="_blank" style="color: #38bdf8; font-size: 0.82rem; text-decoration: underline; word-break: break-all;">
                                                        <i class="fas fa-external-link-alt" style="font-size: 0.75rem;"></i> <?php echo htmlspecialchars($banner['link_url']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: #777; font-size: 0.82rem; font-style: italic;">None (Display Only)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($banner['is_active'] == 1): ?>
                                                    <span class="status-badge status-active"><i class="fas fa-eye"></i> Active</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-inactive"><i class="fas fa-eye-slash"></i> Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <div class="action-btn-group" style="justify-content: flex-end;">
                                                    <!-- Toggle visibility form -->
                                                    <form method="POST" action="settings.php" style="margin: 0; display: inline;">
                                                        <input type="hidden" name="action" value="toggle_banner_status">
                                                        <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                                                        <button type="submit" class="btn-action-icon btn-action-toggle" title="<?php echo $banner['is_active'] == 1 ? 'Hide on Homepage' : 'Show on Homepage'; ?>">
                                                            <i class="fas <?php echo $banner['is_active'] == 1 ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                                        </button>
                                                    </form>

                                                    <!-- Edit button -->
                                                    <button type="button" class="btn-action-icon btn-action-edit" title="Edit Banner" 
                                                            onclick='openEditBannerModal(<?php echo htmlspecialchars(json_encode($banner), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                        <i class="fas fa-pencil-alt"></i>
                                                    </button>

                                                    <!-- Delete button -->
                                                    <button type="button" class="btn-action-icon btn-action-delete" title="Delete Banner"
                                                            onclick="openDeleteBannerModal(<?php echo $banner['id']; ?>, '<?php echo htmlspecialchars(addslashes($banner['title'] ?: 'Banner #' . $banner['id'])); ?>')">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- MOBILE CARD VIEW (Custom Responsive Card Layout for Mobile Screens) -->
                        <div class="banner-mobile-cards">
                            <?php foreach ($bannersList as $banner): ?>
                                <div class="banner-m-card <?php echo $banner['is_active'] == 1 ? 'is-active' : 'is-inactive'; ?>">
                                    <!-- Mobile Card Top: Order & Status -->
                                    <div class="banner-m-top">
                                        <div class="banner-m-order-badge">
                                            <i class="fas fa-layer-group"></i> Slide #<?php echo intval($banner['display_order']); ?>
                                        </div>
                                        <div class="banner-m-status">
                                            <?php if ($banner['is_active'] == 1): ?>
                                                <span class="status-badge status-active"><i class="fas fa-check-circle"></i> Active</span>
                                            <?php else: ?>
                                                <span class="status-badge status-inactive"><i class="fas fa-eye-slash"></i> Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Visual Preview with Quick-View Action -->
                                    <div class="banner-m-visual">
                                        <a href="../<?php echo htmlspecialchars($banner['image_path']); ?>" target="_blank" class="banner-m-img-link" title="Tap to view full banner image">
                                            <img src="../<?php echo htmlspecialchars($banner['image_path']); ?>" alt="<?php echo htmlspecialchars($banner['title'] ?: 'Banner'); ?>" class="banner-m-img" loading="lazy">
                                            <span class="banner-m-zoom-pill">
                                                <i class="fas fa-search-plus"></i> View Full
                                            </span>
                                        </a>
                                    </div>

                                    <!-- Banner Info Details -->
                                    <div class="banner-m-body">
                                        <div class="banner-m-title">
                                            <?php echo htmlspecialchars($banner['title'] ?: 'Untitled Promo Banner'); ?>
                                        </div>
                                        <div class="banner-m-path" title="<?php echo htmlspecialchars($banner['image_path']); ?>">
                                            <i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($banner['image_path']); ?>
                                        </div>
                                        <div class="banner-m-link-box">
                                            <span class="banner-m-link-label"><i class="fas fa-link"></i> Link:</span>
                                            <?php if (!empty($banner['link_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($banner['link_url']); ?>" target="_blank" class="banner-m-link-url" title="<?php echo htmlspecialchars($banner['link_url']); ?>">
                                                    <span><?php echo htmlspecialchars($banner['link_url']); ?></span>
                                                    <i class="fas fa-external-link-alt" style="font-size: 0.7rem; flex-shrink: 0;"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="banner-m-no-link">None (Display Only)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Mobile Touch-Optimized Actions -->
                                    <div class="banner-m-actions">
                                        <!-- Toggle Status -->
                                        <form method="POST" action="settings.php" class="banner-m-act-form">
                                            <input type="hidden" name="action" value="toggle_banner_status">
                                            <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                                            <button type="submit" class="banner-m-act-btn banner-m-act-toggle <?php echo $banner['is_active'] == 1 ? 'is-active-btn' : 'is-inactive-btn'; ?>" title="<?php echo $banner['is_active'] == 1 ? 'Hide banner on homepage' : 'Show banner on homepage'; ?>">
                                                <i class="fas <?php echo $banner['is_active'] == 1 ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                                <span><?php echo $banner['is_active'] == 1 ? 'Hide' : 'Show'; ?></span>
                                            </button>
                                        </form>

                                        <!-- Edit Button -->
                                        <button type="button" class="banner-m-act-btn banner-m-act-edit" title="Edit Banner Details"
                                                onclick='openEditBannerModal(<?php echo htmlspecialchars(json_encode($banner), ENT_QUOTES, 'UTF-8'); ?>)'>
                                            <i class="fas fa-pencil-alt"></i>
                                            <span>Edit</span>
                                        </button>

                                        <!-- Delete Button -->
                                        <button type="button" class="banner-m-act-btn banner-m-act-delete" title="Delete Banner"
                                                onclick="openDeleteBannerModal(<?php echo $banner['id']; ?>, '<?php echo htmlspecialchars(addslashes($banner['title'] ?: 'Banner #' . $banner['id'])); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                            <span>Delete</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="padding: 40px 20px; text-align: center; color: #888;">
                            <i class="fas fa-image" style="font-size: 2.5rem; color: #555; margin-bottom: 10px; display: block;"></i>
                            <p style="margin: 0 0 14px 0; font-size: 0.95rem;">No homepage banners found.</p>
                            <button type="button" class="btn-gold-add" onclick="openAddBannerModal()">
                                <i class="fas fa-plus"></i> Add Your First Banner
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
                    </div>
                </div>
            </div><!-- End modalHomepageBanners -->

            <!-- MODAL 2: FOOTER ABOUT & CONTACT -->
            <div id="modalAboutContact" class="custom-modal-overlay">
                <div class="custom-modal-box modal-medium">
                    <div class="custom-modal-header">
                        <h3><i class="fas fa-store"></i> Footer: About &amp; Contact</h3>
                        <button type="button" class="modal-close-btn" onclick="closeModal('modalAboutContact')" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="custom-modal-scrollable">
                        <div class="custom-card-body" style="padding: 0;">
                        <form method="POST" action="settings.php">
                            <input type="hidden" name="action" value="update_general_footer">
                            
                            <div class="form-section-divider"><i class="fas fa-info-circle"></i> General & About ClosetGo</div>

                            <div class="form-group-custom">
                                <label for="site_name"><i class="fas fa-store"></i> Store / Brand Name (Top Bar Logo Text)</label>
                                <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($currentSiteName); ?>" class="form-input-custom" placeholder="ClosetGo" required>
                            </div>

                            <div class="form-group-custom">
                                <label for="footer_about_title"><i class="fas fa-heading"></i> About Section Title</label>
                                <input type="text" id="footer_about_title" name="footer_about_title" value="<?php echo htmlspecialchars($footerAboutTitle); ?>" class="form-input-custom" required>
                            </div>

                            <div class="form-group-custom">
                                <label for="footer_about_link"><i class="fas fa-link"></i> About Us Page Link</label>
                                <input type="text" id="footer_about_link" name="footer_about_link" value="<?php echo htmlspecialchars($footerAboutLink); ?>" class="form-input-custom" required>
                            </div>

                            <div class="form-group-custom">
                                <label for="footer_terms_link"><i class="fas fa-file-contract"></i> Terms & Conditions Link</label>
                                <input type="text" id="footer_terms_link" name="footer_terms_link" value="<?php echo htmlspecialchars($footerTermsLink); ?>" class="form-input-custom" required>
                            </div>

                            <div class="form-group-custom">
                                <label for="footer_privacy_link"><i class="fas fa-user-shield"></i> Privacy Policy Link</label>
                                <input type="text" id="footer_privacy_link" name="footer_privacy_link" value="<?php echo htmlspecialchars($footerPrivacyLink); ?>" class="form-input-custom" required>
                            </div>

                            <div class="form-section-divider"><i class="fas fa-phone-alt"></i> Column 2: Contact Information</div>

                            <div class="form-group-custom">
                                <label for="footer_contact_title"><i class="fas fa-heading"></i> Contact Section Title</label>
                                <input type="text" id="footer_contact_title" name="footer_contact_title" value="<?php echo htmlspecialchars($footerContactTitle); ?>" class="form-input-custom" required>
                            </div>

                            <div class="form-group-custom">
                                <label for="site_address"><i class="fas fa-map-marker-alt"></i> Physical Address / Location</label>
                                <input type="text" id="site_address" name="site_address" value="<?php echo htmlspecialchars($currentSiteAddress); ?>" class="form-input-custom" required>
                            </div>

                            <div class="form-group-custom">
                                <label for="site_email"><i class="fas fa-envelope"></i> Contact Email</label>
                                <input type="email" id="site_email" name="site_email" value="<?php echo htmlspecialchars($currentSiteEmail); ?>" class="form-input-custom" required>
                            </div>
                            
                            <div class="form-group-custom">
                                <label for="site_phone"><i class="fas fa-phone"></i> Contact Phone / Hotline</label>
                                <input type="tel" id="site_phone" name="site_phone" value="<?php echo htmlspecialchars($currentSitePhone); ?>" class="form-input-custom" required>
                            </div>
                            
                            <button type="submit" class="btn-gold-save">
                                <i class="fas fa-save"></i> Save About & Contact Details
                            </button>
                        </form>
                    </div>
                    </div>
                </div>
            </div><!-- End modalAboutContact -->

            <!-- MODAL 3: FOOTER SOCIAL LINKS -->
            <div id="modalSocialLinks" class="custom-modal-overlay">
                <div class="custom-modal-box modal-standard">
                    <div class="custom-modal-header">
                        <h3><i class="fas fa-share-alt"></i> Footer: Social Links</h3>
                        <button type="button" class="modal-close-btn" onclick="closeModal('modalSocialLinks')" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="custom-modal-scrollable">
                        <div class="custom-card-body" style="padding: 0;">
                        <form method="POST" action="settings.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_social_app">
                            
                            <div class="form-section-divider"><i class="fas fa-hashtag"></i> Column 3: Follow Us (Social Links)</div>

                            <div class="form-group-custom">
                                <label for="footer_social_title"><i class="fas fa-heading"></i> Social Section Title</label>
                                <input type="text" id="footer_social_title" name="footer_social_title" value="<?php echo htmlspecialchars($footerSocialTitle); ?>" class="form-input-custom" required>
                            </div>

                            <div class="form-group-custom">
                                <label for="footer_facebook"><i class="fab fa-facebook-f" style="color:#1877f2;"></i> Facebook URL</label>
                                <input type="url" id="footer_facebook" name="footer_facebook" value="<?php echo htmlspecialchars($footerFacebook); ?>" class="form-input-custom" placeholder="https://facebook.com/..." required>
                            </div>

                            <div class="form-group-custom">
                                <label for="footer_instagram"><i class="fab fa-instagram" style="color:#e4405f;"></i> Instagram URL</label>
                                <input type="url" id="footer_instagram" name="footer_instagram" value="<?php echo htmlspecialchars($footerInstagram); ?>" class="form-input-custom" placeholder="https://instagram.com/..." required>
                            </div>

                            <button type="submit" class="btn-gold-save">
                                <i class="fas fa-save"></i> Save Social Links
                            </button>
                        </form>
                    </div>
                    </div>
                </div>
            </div><!-- End modalSocialLinks -->

            <!-- MODAL 4: RCBC PAYMENT SETTINGS -->
            <div id="modalRcbcPayment" class="custom-modal-overlay">
                <div class="custom-modal-box modal-standard">
                    <div class="custom-modal-header">
                        <h3><i class="fas fa-qrcode"></i> RCBC Payment Settings</h3>
                        <button type="button" class="modal-close-btn" onclick="closeModal('modalRcbcPayment')" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="custom-modal-scrollable">
                        <div class="custom-card-body" style="padding: 0;">
                        <form method="POST" action="settings.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_gcash">

                            <div class="form-group-custom">
                                <label for="gcash_account_name"><i class="fas fa-user-check"></i> RCBC Account Name</label>
                                <input type="text" id="gcash_account_name" name="gcash_account_name" value="<?php echo htmlspecialchars($currentGcashName); ?>" placeholder="e.g. Maria C." class="form-input-custom" required>
                            </div>

                            <div class="form-group-custom">
                                <label for="gcash_number"><i class="fas fa-mobile-alt"></i> RCBC Account Number</label>
                                <input type="text" id="gcash_number" name="gcash_number" value="<?php echo htmlspecialchars($currentGcashNum); ?>" placeholder="e.g. 09171234567" class="form-input-custom" required>
                            </div>

                            <div class="form-group-custom">
                                <label><i class="fas fa-image"></i> Active Checkout QR Code</label>
                                <div class="qr-preview-box">
                                    <div class="qr-thumb-frame">
                                        <img id="gcashQrPreviewImg" src="../<?php echo htmlspecialchars($currentGcashQr); ?>" alt="Active QR">
                                    </div>
                                    <div class="qr-info-text">
                                        <div class="qr-info-title">Displayed on Checkout</div>
                                        <div class="qr-info-path"><?php echo htmlspecialchars($currentGcashQr); ?></div>
                                        <div class="qr-info-status"><i class="fas fa-check-circle"></i> Visible to customers</div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group-custom">
                                <label for="gcash_qr_image"><i class="fas fa-upload"></i> Upload New QR Code Image</label>
                                <input type="file" id="gcash_qr_image" name="gcash_qr_image" accept="image/*" class="custom-file-input" onchange="previewAdminGcashQr(this)">
                                <p style="font-size: 0.74rem; color: #888; margin: 5px 0 0 0;">Upload clear JPG, PNG, or WEBP screenshot of your RCBC QR.</p>
                            </div>

                            <button type="submit" class="btn-gold-save" style="background: linear-gradient(135deg, #007aff, #0056b3); color: #fff; box-shadow: 0 4px 14px rgba(0, 122, 255, 0.3);">
                                <i class="fas fa-save"></i> Save RCBC Settings
                            </button>
                        </form>
                    </div>
                    </div>
                </div>
            </div><!-- End modalRcbcPayment -->

            <!-- MODAL 5: ADMIN & STAFF ACCOUNTS -->
            <div id="modalAdminStaff" class="custom-modal-overlay">
                <div class="custom-modal-box modal-medium">
                    <div class="custom-modal-header">
                        <h3><i class="fas fa-user-shield"></i> Admin &amp; Staff Accounts</h3>
                        <button type="button" class="modal-close-btn" onclick="closeModal('modalAdminStaff')" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="custom-modal-scrollable">
                        <div class="custom-card-body" style="padding: 0;">
                        <div>
                            <div class="form-section-divider"><i class="fas fa-shield-alt"></i> Access &amp; Roles</div>
                            <p style="color: #bbb; font-size: 0.85rem; line-height: 1.5; margin-bottom: 16px;">
                                Create new administrator or staff accounts, assign login credentials, verify user access, and oversee staff directory.
                            </p>
                            <div style="background: #212328; border: 1px solid rgba(255, 215, 0, 0.2); border-radius: 8px; padding: 12px 14px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-around;">
                                <div style="text-align: center;">
                                    <div style="color: #ffd700; font-size: 1.3rem; font-weight: 700;"><i class="fas fa-user-shield"></i></div>
                                    <div style="color: #ccc; font-size: 0.78rem; margin-top: 4px;">Full Admin</div>
                                </div>
                                <div style="height: 32px; width: 1px; background: rgba(255,215,0,0.2);"></div>
                                <div style="text-align: center;">
                                    <div style="color: #38bdf8; font-size: 1.3rem; font-weight: 700;"><i class="fas fa-id-badge"></i></div>
                                    <div style="color: #ccc; font-size: 0.78rem; margin-top: 4px;">Staff Member</div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn-gold-save" onclick="closeModal('modalAdminStaff'); openAddAdminModal();" style="width: 100%; justify-content: center; padding: 13px; font-size: 0.95rem; margin-top: 15px;">
                            <i class="fas fa-user-plus"></i> Open Admin &amp; Staff Management Portal
                        </button>
                    </div>
                    </div>
                </div>
            </div><!-- End modalAdminStaff -->

            <!-- MODAL 6: DATABASE BACKUP & SYSTEM RESTORE -->
            <div id="modalDatabaseBackup" class="custom-modal-overlay">
                <div class="custom-modal-box modal-large">
                    <div class="custom-modal-header">
                        <h3><i class="fas fa-database"></i> Database Backup &amp; System Restore</h3>
                        <button type="button" class="modal-close-btn" onclick="closeModal('modalDatabaseBackup')" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="custom-modal-scrollable">
                        <div class="banner-management-card backup-management-card" id="backup-restore-section" style="margin-bottom: 0;">
                <div class="banner-card-header">
                    <div class="banner-header-left">
                        <div class="custom-card-icon" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8; border-color: rgba(56, 189, 248, 0.4);">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="custom-card-title">
                            <h3>Database Backup &amp; System Restore <span class="banner-counter-badge"><?php echo count($backupList); ?> <?php echo count($backupList) === 1 ? 'Archive' : 'Archives'; ?></span></h3>
                            <p>Generate SQL snapshot backups, download local copies, or restore store data</p>
                        </div>
                    </div>
                    <form method="POST" action="settings.php" onsubmit="return handleCreateBackupSubmit(this)">
                        <input type="hidden" name="action" value="create_backup">
                        <button type="submit" class="btn-gold-add" id="btnCreateBackup">
                            <i class="fas fa-plus-circle"></i> Create Backup Now
                        </button>
                    </form>
                </div>

                <div class="backup-tools-container">
                    <!-- Left: Upload & Restore box -->
                    <div class="backup-upload-box">
                        <div class="backup-tool-title">
                            <i class="fas fa-file-import" style="color: #ffd700;"></i>
                            <span>Upload &amp; Restore .SQL Backup</span>
                        </div>
                        <p class="backup-tool-desc">
                            Select an existing <code>.sql</code> backup file from your computer to restore database tables and records.
                        </p>
                        <form method="POST" action="settings.php" enctype="multipart/form-data" onsubmit="return confirmUploadRestore(this)">
                            <input type="hidden" name="action" value="upload_restore_backup">
                            <div class="backup-upload-row">
                                <input type="file" name="backup_file" accept=".sql" required class="custom-file-input backup-file-chooser">
                                <button type="submit" class="btn-restore-action">
                                    <i class="fas fa-upload"></i> Upload &amp; Restore
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Right: System Info / Safeguard notice -->
                    <div class="backup-info-card">
                        <div class="backup-info-header">
                            <i class="fas fa-shield-alt" style="color: #38bdf8;"></i>
                            <span>Safety &amp; Archive Guidelines</span>
                        </div>
                        <ul class="backup-info-list">
                            <li><i class="fas fa-check" style="color: #4ade80;"></i> Backups are saved inside the protected server <code>backups/</code> directory.</li>
                            <li><i class="fas fa-check" style="color: #4ade80;"></i> Backs up all 20 store tables including gowns, reservations, and user accounts.</li>
                            <li><i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> Restoring a backup overwrites current database tables. Back up first!</li>
                        </ul>
                    </div>
                </div>

                <div class="banner-table-container">
                    <?php if (!empty($backupList)): ?>
                        <!-- Desktop Table -->
                        <div class="banner-desktop-table">
                            <table class="banner-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">Backup File Name</th>
                                        <th style="width: 22%;">Date Created</th>
                                        <th style="width: 14%;">File Size</th>
                                        <th style="width: 24%; text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backupList as $bk): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div class="backup-file-icon">
                                                        <i class="fas fa-file-code"></i>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 700; color: #fff; font-size: 0.9rem; word-break: break-all;">
                                                            <?php echo htmlspecialchars($bk['name']); ?>
                                                        </div>
                                                        <div style="font-size: 0.72rem; color: #888; margin-top: 2px;">
                                                            <?php echo strpos($bk['name'], 'uploaded') !== false ? '<span style="color: #38bdf8;"><i class="fas fa-cloud-upload-alt"></i> External Upload</span>' : '<span style="color: #4ade80;"><i class="fas fa-server"></i> System Snapshot</span>'; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="color: #e5e5e5; font-size: 0.85rem;">
                                                    <i class="far fa-calendar-alt" style="color: #ffd700; margin-right: 5px;"></i>
                                                    <?php echo date('M d, Y', $bk['date']); ?>
                                                </div>
                                                <div style="color: #888; font-size: 0.74rem; margin-top: 2px;">
                                                    <i class="far fa-clock" style="margin-right: 4px;"></i>
                                                    <?php echo date('h:i:s A', $bk['date']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="backup-size-badge">
                                                    <?php echo formatBackupBytes($bk['size']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-btn-group" style="justify-content: center;">
                                                    <!-- Download Button -->
                                                    <a href="settings.php?action=download_backup&file=<?php echo urlencode($bk['name']); ?>" 
                                                       class="btn-action-icon btn-action-download" 
                                                       title="Download SQL Backup File">
                                                        <i class="fas fa-download"></i>
                                                    </a>

                                                    <!-- Restore Button -->
                                                    <button type="button" 
                                                            class="btn-action-icon btn-action-restore" 
                                                            title="Restore Database from this file"
                                                            onclick="openRestoreServerModal('<?php echo htmlspecialchars(addslashes($bk['name'])); ?>')">
                                                        <i class="fas fa-history"></i>
                                                    </button>

                                                    <!-- Delete Button -->
                                                    <button type="button" 
                                                            class="btn-action-icon btn-action-delete" 
                                                            title="Delete Backup File"
                                                            onclick="openDeleteBackupModal('<?php echo htmlspecialchars(addslashes($bk['name'])); ?>')">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Cards -->
                        <div class="banner-mobile-cards">
                            <?php foreach ($backupList as $bk): ?>
                                <div class="banner-m-card" style="padding: 14px;">
                                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 10px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div class="backup-file-icon">
                                                <i class="fas fa-file-code"></i>
                                            </div>
                                            <div style="font-weight: 700; color: #fff; font-size: 0.88rem; word-break: break-all;">
                                                <?php echo htmlspecialchars($bk['name']); ?>
                                            </div>
                                        </div>
                                        <span class="backup-size-badge">
                                            <?php echo formatBackupBytes($bk['size']); ?>
                                        </span>
                                    </div>

                                    <div style="display: flex; align-items: center; justify-content: space-between; color: #888; font-size: 0.76rem; margin-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 8px;">
                                        <span><i class="far fa-calendar-alt" style="color: #ffd700;"></i> <?php echo date('M d, Y h:i A', $bk['date']); ?></span>
                                        <span><?php echo strpos($bk['name'], 'uploaded') !== false ? 'Uploaded' : 'System'; ?></span>
                                    </div>

                                    <div style="display: flex; gap: 8px;">
                                        <a href="settings.php?action=download_backup&file=<?php echo urlencode($bk['name']); ?>" 
                                           class="btn-action-icon btn-action-download" style="flex: 1; height: 38px; border-radius: 8px; gap: 6px; text-decoration: none;" title="Download">
                                            <i class="fas fa-download"></i> <span style="font-size: 0.8rem; font-weight: 600;">Download</span>
                                        </a>

                                        <button type="button" 
                                                class="btn-action-icon btn-action-restore" style="flex: 1; height: 38px; border-radius: 8px; gap: 6px;" title="Restore"
                                                onclick="openRestoreServerModal('<?php echo htmlspecialchars(addslashes($bk['name'])); ?>')">
                                            <i class="fas fa-history"></i> <span style="font-size: 0.8rem; font-weight: 600;">Restore</span>
                                        </button>

                                        <button type="button" 
                                                class="btn-action-icon btn-action-delete" style="width: 44px; height: 38px; border-radius: 8px;" title="Delete"
                                                onclick="openDeleteBackupModal('<?php echo htmlspecialchars(addslashes($bk['name'])); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="padding: 40px 20px; text-align: center; color: #888;">
                            <i class="fas fa-database" style="font-size: 2.5rem; color: #555; margin-bottom: 10px; display: block;"></i>
                            <p style="margin: 0 0 14px 0; font-size: 0.95rem;">No database backup archives found.</p>
                            <form method="POST" action="settings.php" onsubmit="return handleCreateBackupSubmit(this)" style="display: inline-block;">
                                <input type="hidden" name="action" value="create_backup">
                                <button type="submit" class="btn-gold-add">
                                    <i class="fas fa-plus-circle"></i> Create Your First Backup
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
                    </div>
                </div>
            </div><!-- End modalDatabaseBackup -->
        </div>
    </div>

    <!-- ADD BANNER MODAL -->
    <div id="addBannerModal" class="custom-modal-overlay custom-submodal">
        <div class="custom-modal-box">
            <div class="custom-modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Homepage Banner</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('addBannerModal')" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="settings.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_banner">
                <div class="custom-modal-body">
                    <div class="form-group-custom">
                        <label for="add_banner_image"><i class="fas fa-image"></i> Banner Image File <span style="color:#ffd700;">*</span></label>
                        <input type="file" id="add_banner_image" name="banner_image" accept="image/*" class="custom-file-input" required onchange="previewBannerUpload(this, 'addBannerPreview')">
                        <p style="font-size: 0.74rem; color: #888; margin: 4px 0 0 0;">Recommended size: ~1200x300px or ~16:4 aspect ratio (JPG, PNG, WEBP, GIF).</p>
                        <div id="addBannerPreview" class="banner-live-preview">
                            <div class="preview-placeholder">
                                <i class="fas fa-image"></i>
                                Selected banner preview will appear here
                            </div>
                            <img src="" alt="Preview">
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label for="add_banner_title"><i class="fas fa-heading"></i> Promo Title / Alt Text</label>
                        <input type="text" id="add_banner_title" name="banner_title" placeholder="e.g. Summer Collection 20% Off" class="form-input-custom">
                    </div>

                    <div class="form-group-custom">
                        <label for="add_banner_link_url"><i class="fas fa-link"></i> Target Click URL (Optional)</label>
                        <input type="text" id="add_banner_link_url" name="banner_link_url" placeholder="e.g. product_categories.php or purchase.php?id=1" class="form-input-custom">
                        <p style="font-size: 0.72rem; color: #888; margin: 3px 0 0 0;">Leave empty if the banner is purely informative.</p>
                    </div>

                    <div class="modal-flex-row">
                        <div class="form-group-custom">
                            <label for="add_banner_order"><i class="fas fa-sort-numeric-down"></i> Display Order</label>
                            <input type="number" id="add_banner_order" name="banner_order" value="<?php echo count($bannersList) + 1; ?>" min="0" class="form-input-custom">
                        </div>

                        <div class="form-group-custom modal-checkbox-wrap">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="banner_is_active" value="1" checked style="width: 18px; height: 18px; accent-color: #ffd700;">
                                <span style="font-size: 0.88rem; color: #fff; font-weight: 600;">Active / Visible</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeModal('addBannerModal')">Cancel</button>
                    <button type="submit" class="btn-gold-add" style="border-radius: 8px; padding: 10px 20px;">
                        <i class="fas fa-upload"></i> Upload &amp; Save Banner
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT BANNER MODAL -->
    <div id="editBannerModal" class="custom-modal-overlay custom-submodal">
        <div class="custom-modal-box">
            <div class="custom-modal-header">
                <h3><i class="fas fa-edit"></i> Edit Homepage Banner</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('editBannerModal')" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="settings.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_banner">
                <input type="hidden" id="edit_banner_id" name="banner_id" value="">
                <div class="custom-modal-body">
                    <div class="form-group-custom">
                        <label><i class="fas fa-image"></i> Current Banner Image</label>
                        <div id="editBannerCurrentPreview" class="banner-live-preview has-image">
                            <img id="edit_banner_img_current" src="" alt="Current Banner">
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label for="edit_banner_image"><i class="fas fa-upload"></i> Replace Image File (Optional)</label>
                        <input type="file" id="edit_banner_image" name="banner_image" accept="image/*" class="custom-file-input" onchange="previewBannerUpload(this, 'editBannerCurrentPreview')">
                        <p style="font-size: 0.72rem; color: #888; margin: 3px 0 0 0;">Leave empty to keep the existing banner image.</p>
                    </div>

                    <div class="form-group-custom">
                        <label for="edit_banner_title"><i class="fas fa-heading"></i> Promo Title / Alt Text</label>
                        <input type="text" id="edit_banner_title" name="banner_title" class="form-input-custom">
                    </div>

                    <div class="form-group-custom">
                        <label for="edit_banner_link_url"><i class="fas fa-link"></i> Target Click URL (Optional)</label>
                        <input type="text" id="edit_banner_link_url" name="banner_link_url" class="form-input-custom">
                    </div>

                    <div class="modal-flex-row">
                        <div class="form-group-custom">
                            <label for="edit_banner_order"><i class="fas fa-sort-numeric-down"></i> Display Order</label>
                            <input type="number" id="edit_banner_order" name="banner_order" min="0" class="form-input-custom">
                        </div>

                        <div class="form-group-custom modal-checkbox-wrap">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" id="edit_banner_is_active" name="banner_is_active" value="1" style="width: 18px; height: 18px; accent-color: #ffd700;">
                                <span style="font-size: 0.88rem; color: #fff; font-weight: 600;">Active / Visible</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeModal('editBannerModal')">Cancel</button>
                    <button type="submit" class="btn-gold-add" style="border-radius: 8px; padding: 10px 20px;">
                        <i class="fas fa-save"></i> Update Banner
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE BANNER MODAL -->
    <div id="deleteBannerModal" class="custom-modal-overlay custom-submodal">
        <div class="custom-modal-box" style="max-width: 440px;">
            <div class="custom-modal-header" style="background: #2a1515; border-bottom: 1px solid #772222;">
                <h3 style="color: #ff6b6b;"><i class="fas fa-exclamation-triangle"></i> Delete Banner</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('deleteBannerModal')" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="delete_banner">
                <input type="hidden" id="delete_banner_id" name="banner_id" value="">
                <div class="custom-modal-body" style="text-align: center; padding: 25px 20px;">
                    <i class="fas fa-trash-alt" style="font-size: 3rem; color: #f87171; margin-bottom: 15px; display: block;"></i>
                    <p style="color: #fff; font-size: 1rem; margin-bottom: 8px;">Are you sure you want to delete this banner?</p>
                    <p id="delete_banner_label" style="color: #ffd700; font-weight: 700; font-size: 0.95rem; margin: 0;"></p>
                    <p style="color: #888; font-size: 0.78rem; margin-top: 10px;">This action cannot be undone.</p>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeModal('deleteBannerModal')">Cancel</button>
                    <button type="submit" class="btn-modal-cancel" style="background: #dc2626; color: #fff; border-color: #ef4444;">
                        <i class="fas fa-trash"></i> Yes, Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RESTORE SERVER BACKUP MODAL -->
    <div id="restoreServerModal" class="custom-modal-overlay custom-submodal">
        <div class="custom-modal-box" style="max-width: 480px;">
            <div class="custom-modal-header" style="background: #2a2215; border-bottom: 1px solid #7c6300;">
                <h3 style="color: #fbbf24;"><i class="fas fa-history"></i> Restore Database Backup</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('restoreServerModal')" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="settings.php" onsubmit="return handleRestoreSubmit(this)">
                <input type="hidden" name="action" value="restore_server_backup">
                <input type="hidden" id="restore_server_filename" name="file_name" value="">
                <div class="custom-modal-body" style="text-align: center; padding: 25px 22px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #f59e0b; margin-bottom: 15px; display: block;"></i>
                    <p style="color: #fff; font-size: 1.05rem; font-weight: 600; margin-bottom: 8px;">Are you sure you want to restore the database?</p>
                    <p id="restore_server_filename_display" style="color: #ffd700; font-weight: 700; font-size: 0.95rem; word-break: break-all; margin: 0 0 14px 0;"></p>
                    <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 8px; padding: 12px; text-align: left; color: #e5e5e5; font-size: 0.82rem; line-height: 1.5;">
                        <strong style="color: #fbbf24;"><i class="fas fa-info-circle"></i> Important Warning:</strong>
                        Restoring will overwrite current tables, reservations, gown products, and user accounts with the records stored in this backup archive.
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeModal('restoreServerModal')">Cancel</button>
                    <button type="submit" class="btn-gold-save" id="btnConfirmRestore" style="width: auto; margin-top: 0; padding: 10px 20px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff;">
                        <i class="fas fa-history"></i> Yes, Restore Database
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE BACKUP MODAL -->
    <div id="deleteBackupModal" class="custom-modal-overlay custom-submodal">
        <div class="custom-modal-box" style="max-width: 440px;">
            <div class="custom-modal-header" style="background: #2a1515; border-bottom: 1px solid #772222;">
                <h3 style="color: #ff6b6b;"><i class="fas fa-trash-alt"></i> Delete Backup Archive</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('deleteBackupModal')" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="delete_backup">
                <input type="hidden" id="delete_backup_filename" name="backup_filename" value="">
                <div class="custom-modal-body" style="text-align: center; padding: 25px 20px;">
                    <i class="fas fa-trash-alt" style="font-size: 3rem; color: #f87171; margin-bottom: 15px; display: block;"></i>
                    <p style="color: #fff; font-size: 1rem; margin-bottom: 8px;">Are you sure you want to delete this backup?</p>
                    <p id="delete_backup_filename_display" style="color: #ffd700; font-weight: 700; font-size: 0.95rem; word-break: break-all; margin: 0;"></p>
                    <p style="color: #888; font-size: 0.78rem; margin-top: 10px;">This SQL backup file will be permanently removed from the server disk.</p>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeModal('deleteBackupModal')">Cancel</button>
                    <button type="submit" class="btn-modal-cancel" style="background: #dc2626; color: #fff; border-color: #ef4444;">
                        <i class="fas fa-trash"></i> Yes, Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Big Add Admin / Staff Modal -->
    <div id="addAdminStaffModal" class="admin-staff-modal-overlay" style="display: none;" onclick="handleAddAdminModalBackdropClick(event)">
        <div class="admin-staff-modal-container">
            <div class="admin-staff-modal-header">
                <div class="admin-staff-modal-title">
                    <i class="fas fa-user-shield" style="color: #ffd700;"></i>
                    <span>Add Admin / Staff Management</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="admin-staff-modal-close-btn" onclick="closeAddAdminModal()" aria-label="Close" title="Close Modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="admin-staff-modal-body">
                <iframe id="addAdminIframe" src="about:blank" frameborder="0" style="width: 100%; height: 100%; border: none; background: #14151a;" title="Add Admin and Staff"></iframe>
            </div>
        </div>
    </div>

    <script>
    function openAddBannerModal() {
        if (typeof playButtonClickSound === 'function') {
            playButtonClickSound();
        }
        document.getElementById('addBannerModal').classList.add('active');
    }

    function openEditBannerModal(banner) {
        if (typeof playButtonClickSound === 'function') {
            playButtonClickSound();
        }
        document.getElementById('edit_banner_id').value = banner.id;
        document.getElementById('edit_banner_title').value = banner.title || '';
        document.getElementById('edit_banner_link_url').value = banner.link_url || '';
        document.getElementById('edit_banner_order').value = banner.display_order || 0;
        document.getElementById('edit_banner_is_active').checked = (parseInt(banner.is_active) === 1);
        
        const previewImg = document.getElementById('edit_banner_img_current');
        if (previewImg) {
            previewImg.src = '../' + banner.image_path;
            previewImg.style.display = 'block';
        }
        
        document.getElementById('editBannerModal').classList.add('active');
    }

    function openDeleteBannerModal(id, title) {
        if (typeof playButtonClickSound === 'function') {
            playButtonClickSound();
        }
        document.getElementById('delete_banner_id').value = id;
        document.getElementById('delete_banner_label').textContent = title || 'this banner';
        document.getElementById('deleteBannerModal').classList.add('active');
    }

    function openRestoreServerModal(fileName) {
        if (typeof playButtonClickSound === 'function') {
            playButtonClickSound();
        }
        document.getElementById('restore_server_filename').value = fileName;
        document.getElementById('restore_server_filename_display').textContent = fileName;
        const modal = document.getElementById('restoreServerModal');
        if (modal) {
            modal.classList.add('active');
        }
    }

    function openDeleteBackupModal(fileName) {
        if (typeof playButtonClickSound === 'function') {
            playButtonClickSound();
        }
        document.getElementById('delete_backup_filename').value = fileName;
        document.getElementById('delete_backup_filename_display').textContent = fileName;
        const modal = document.getElementById('deleteBackupModal');
        if (modal) {
            modal.classList.add('active');
        }
    }

    function handleCreateBackupSubmit(form) {
        if (typeof playButtonClickSound === 'function') {
            playButtonClickSound();
        }
        const btn = document.getElementById('btnCreateBackup');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Backup...';
        }
        return true;
    }

    function handleRestoreSubmit(form) {
        if (typeof playButtonClickSound === 'function') {
            playButtonClickSound();
        }
        const btn = document.getElementById('btnConfirmRestore');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Restoring Database...';
        }
        return true;
    }

    function confirmUploadRestore(form) {
        const fileInput = form.querySelector('input[type="file"]');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            alert('Please choose a .sql backup file to upload.');
            return false;
        }
        const fileName = fileInput.files[0].name;
        if (!fileName.toLowerCase().endsWith('.sql')) {
            alert('Only .sql backup files are supported.');
            return false;
        }
        if (typeof playButtonClickSound === 'function') {
            playButtonClickSound();
        }
        const confirmed = confirm('⚠️ WARNING: Restoring from "' + fileName + '" will overwrite all current database tables with data from this file.\n\nAre you sure you want to proceed?');
        if (confirmed) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Restoring...';
            }
            return true;
        }
        return false;
    }

    function openModal(modalId) {
        if (typeof playButtonClickSound === 'function') {
            playButtonClickSound();
        }
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
        }
    }

    function closeModal(modalId) {
        if (typeof playButtonClickSound === 'function') {
            playButtonClickSound();
        }
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
        }
    }

    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('custom-modal-overlay')) {
            e.target.classList.remove('active');
        }
    });

    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Close submodals first
            const submodals = ['addBannerModal', 'editBannerModal', 'deleteBannerModal', 'restoreServerModal', 'deleteBackupModal'];
            let closedSub = false;
            submodals.forEach(function(mId) {
                const m = document.getElementById(mId);
                if (m && m.classList.contains('active')) {
                    closeModal(mId);
                    closedSub = true;
                }
            });
            if (closedSub) return;

            // Close parent section modals
            ['modalHomepageBanners', 'modalAboutContact', 'modalSocialLinks', 'modalRcbcPayment', 'modalAdminStaff', 'modalDatabaseBackup'].forEach(function(mId) {
                closeModal(mId);
            });
            const adminModal = document.getElementById('addAdminStaffModal');
            if (adminModal && adminModal.style.display !== 'none') {
                closeAddAdminModal();
            }
        }
    });

    function previewBannerUpload(input, previewContainerId) {
        const container = document.getElementById(previewContainerId);
        if (input.files && input.files[0] && container) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = container.querySelector('img');
                if (img) {
                    img.src = e.target.result;
                    img.style.display = 'block';
                    container.classList.add('has-image');
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function previewAdminGcashQr(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('gcashQrPreviewImg');
                if (img) {
                    img.src = e.target.result;
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // ===== BIG ADD ADMIN / STAFF MODAL FUNCTIONS =====
    function openAddAdminModal() {
        if (typeof closeSidebar === 'function') {
            closeSidebar();
        }
        if (typeof playButtonClickSound === 'function') {
            playButtonClickSound();
        }
        const modal = document.getElementById('addAdminStaffModal');
        const iframe = document.getElementById('addAdminIframe');
        if (!modal || !iframe) return;

        iframe.src = 'add_admin.php?embed=1';
        modal.style.display = 'flex';
    }

    function closeAddAdminModal() {
        if (typeof playButtonClickSound === 'function') {
            playButtonClickSound();
        }
        const modal = document.getElementById('addAdminStaffModal');
        const iframe = document.getElementById('addAdminIframe');
        if (modal) {
            modal.style.display = 'none';
            if (iframe) iframe.src = 'about:blank';
        }
    }

    function handleAddAdminModalBackdropClick(e) {
        if (e.target.id === 'addAdminStaffModal') {
            closeAddAdminModal();
        }
    }
    </script>
</body>
</html>
