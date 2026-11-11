<?php
class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    private $conn;

    public function __construct() {
        // Automatically detect environment: Localhost (XAMPP) vs Live Hosting (ProFreeHost / Ezyro)
        $serverHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $isLocal = ($serverHost === 'localhost' || $serverHost === '127.0.0.1' 
            || strpos($serverHost, 'localhost:') === 0 
            || strpos(__DIR__, 'xampp') !== false 
            || php_sapi_name() === 'cli');

        if ($isLocal) {
            $this->host     = 'localhost';
            $this->username = 'root';
            $this->password = '';
            $this->database = 'gown_management';
        } else {
        $this->host     = 'sql105.ezyro.com';
        $this->username = 'ezyro_42884015';
        $this->password = 'e0f1d67e';
        $this->database = 'ezyro_42884015_jmbcloset';
        }

        $this->connect();
    }

    private function connect() {
        // Fast direct connection when database already exists
        try {
            $this->conn = @new mysqli($this->host, $this->username, $this->password, $this->database);
            if ($this->conn && !$this->conn->connect_error) {
                $this->conn->set_charset("utf8mb4");
                return;
            }
        } catch (Exception $e) {}

        // Fallback: If remote host (e.g. sql105.ezyro.com) fails or is unreachable from local machine, fallback to localhost
        if ($this->host !== 'localhost' && $this->host !== '127.0.0.1') {
            try {
                $fallbackConn = @new mysqli('localhost', 'root', '', 'gown_management');
                if ($fallbackConn && !$fallbackConn->connect_error) {
                    $this->conn = $fallbackConn;
                    $this->conn->set_charset("utf8mb4");
                    return;
                }
            } catch (Exception $e) {}
        }

        // Retry connection to current host
        try {
            $this->conn = @new mysqli($this->host, $this->username, $this->password, $this->database);
            if ($this->conn && !$this->conn->connect_error) {
                $this->conn->set_charset("utf8mb4");
                return;
            }
        } catch (Exception $e) {}

        // Local development database initialization (localhost only — shared hosting disallows CREATE DATABASE)
        if ($this->host === 'localhost' || $this->host === '127.0.0.1') {
            try {
                $initConn = @new mysqli($this->host, $this->username, $this->password);
                if ($initConn && !$initConn->connect_error) {
                    $initConn->query("CREATE DATABASE IF NOT EXISTS `{$this->database}`");
                    $initConn->select_db($this->database);
                    $this->conn = $initConn;
                    $this->conn->set_charset("utf8mb4");
                    $this->createTables();
                    return;
                }
            } catch (Exception $e) {}
        }
    }
private function createTables() {
        // Create gowns table
        $gownsTable = "
            CREATE TABLE IF NOT EXISTS gowns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                category VARCHAR(255) NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                purchase_price DECIMAL(10,2) DEFAULT 0.00,
                discount DECIMAL(5,2) DEFAULT 0.00,
                color VARCHAR(50) NOT NULL,
                description TEXT,
                image_path VARCHAR(500),
                image_path2 VARCHAR(500),
                image_path3 VARCHAR(500),
                image_path4 VARCHAR(500),
                model_path VARCHAR(500),
                stock INT DEFAULT 0
            )
        ";
        
        $this->conn->query($gownsTable);
        
        // Add new image columns if they don't exist
        $this->conn->query("ALTER TABLE gowns ADD COLUMN IF NOT EXISTS image_path2 VARCHAR(500) AFTER image_path");
        $this->conn->query("ALTER TABLE gowns ADD COLUMN IF NOT EXISTS image_path3 VARCHAR(500) AFTER image_path2");
        $this->conn->query("ALTER TABLE gowns ADD COLUMN IF NOT EXISTS image_path4 VARCHAR(500) AFTER image_path3");
        
        // Add purchase_price column if it doesn't exist
        $this->conn->query("ALTER TABLE gowns ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(10,2) DEFAULT 0.00 AFTER price");
        
        // Add discount column if it doesn't exist
        $this->conn->query("ALTER TABLE gowns ADD COLUMN IF NOT EXISTS discount DECIMAL(5,2) DEFAULT 0.00 AFTER purchase_price");
        
        // Add color column if it doesn't exist
        $this->conn->query("ALTER TABLE gowns ADD COLUMN IF NOT EXISTS color VARCHAR(50) NOT NULL DEFAULT 'N/A' AFTER description");
        
        // Add stock column if it doesn't exist
        $this->conn->query("ALTER TABLE gowns ADD COLUMN IF NOT EXISTS stock INT DEFAULT 0 AFTER model_path");
        
        // Remove status column if it exists
        $this->conn->query("ALTER TABLE gowns DROP COLUMN IF EXISTS status");
        
        // Remove size column if it exists
        $this->conn->query("ALTER TABLE gowns DROP COLUMN IF EXISTS size");
        
        // Change category from ENUM to VARCHAR to support custom categories
        $this->conn->query("ALTER TABLE gowns MODIFY COLUMN category VARCHAR(255) NOT NULL");

        // Create admin_users table for authentication
        $adminUsersTable = "
            CREATE TABLE IF NOT EXISTS admin_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                firstname VARCHAR(50) NOT NULL,
                lastname VARCHAR(50) NOT NULL,
                middlename VARCHAR(50),
                suffix VARCHAR(20) DEFAULT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        
        $this->conn->query($adminUsersTable);
        $this->conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS suffix VARCHAR(20) DEFAULT NULL AFTER lastname");
        $this->conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) DEFAULT NULL");
        $this->conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS token_expiry INT DEFAULT NULL");

        // Insert default admin user if not exists
        $defaultAdmin = "
            INSERT IGNORE INTO admin_users (firstname, lastname, middlename, email, password) 
            VALUES ('Admin', 'User', '', 'admin@gown.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
        ";
        
        try {
            $this->conn->query($defaultAdmin);
        } catch (mysqli_sql_exception $e) {
            // If table doesn't exist, create it first
            if (strpos($e->getMessage(), "Unknown column") !== false) {
                // Table exists but column issue - re-create table
                $this->conn->query("DROP TABLE IF EXISTS admin_users");
                $this->conn->query($adminUsersTable);
                $this->conn->query($defaultAdmin);
            }
        }

        // Create users_no table for phone numbers
        $usersNoTable = "
            CREATE TABLE IF NOT EXISTS users_no (
                id INT AUTO_INCREMENT PRIMARY KEY,
                phone_number VARCHAR(20) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        
        $this->conn->query($usersNoTable);

        // Create system_users table for email/password sign up
        $systemUsersTable = "
            CREATE TABLE IF NOT EXISTS system_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                profile_image VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        
        $this->conn->query($systemUsersTable);

        // Add profile_image column if it doesn't exist (for existing tables)
        $addImageColumn = "
            ALTER TABLE system_users 
            ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) DEFAULT NULL 
            AFTER password
        ";
        
        $this->conn->query($addImageColumn);

        // Add first_name column if it doesn't exist (for existing tables)
        $addFirstNameColumn = "
            ALTER TABLE system_users 
            ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) DEFAULT NULL 
            AFTER profile_image
        ";
        
        $this->conn->query($addFirstNameColumn);

        // Add middle_name column if it doesn't exist (for existing tables)
        $addMiddleNameColumn = "
            ALTER TABLE system_users 
            ADD COLUMN IF NOT EXISTS middle_name VARCHAR(100) DEFAULT NULL 
            AFTER first_name
        ";
        
        $this->conn->query($addMiddleNameColumn);

        // Add last_name column if it doesn't exist (for existing tables)
        $addLastNameColumn = "
            ALTER TABLE system_users 
            ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) DEFAULT NULL 
            AFTER middle_name
        ";
        
        $this->conn->query($addLastNameColumn);

        // Add suffix column if it doesn't exist (for existing tables)
        $addSuffixColumn = "
            ALTER TABLE system_users 
            ADD COLUMN IF NOT EXISTS suffix VARCHAR(50) DEFAULT NULL 
            AFTER last_name
        ";
        
        $this->conn->query($addSuffixColumn);

        // Add phone column if it doesn't exist (for existing tables)
        $addPhoneColumn = "
            ALTER TABLE system_users 
            ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL 
            AFTER suffix
        ";
        
        $this->conn->query($addPhoneColumn);

        // Add email column if it doesn't exist (for existing tables)
        $addEmailColumn = "
            ALTER TABLE system_users 
            ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL 
            AFTER phone
        ";
        
        $this->conn->query($addEmailColumn);

        // Add province column if it doesn't exist (for existing tables)
        $addProvinceColumn = "
            ALTER TABLE system_users 
            ADD COLUMN IF NOT EXISTS province VARCHAR(100) DEFAULT NULL 
            AFTER email
        ";
        
        $this->conn->query($addProvinceColumn);

        // Add city column if it doesn't exist (for existing tables)
        $addCityColumn = "
            ALTER TABLE system_users 
            ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT NULL 
            AFTER province
        ";
        
        $this->conn->query($addCityColumn);

        // Add barangay column if it doesn't exist (for existing tables)
        $addBarangayColumn = "
            ALTER TABLE system_users 
            ADD COLUMN IF NOT EXISTS barangay VARCHAR(100) DEFAULT NULL 
            AFTER city
        ";
        
        $this->conn->query($addBarangayColumn);

        // Create saved_products table for user saved items
        $savedProductsTable = "
            CREATE TABLE IF NOT EXISTS saved_products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                product_id INT NOT NULL,
                model_path VARCHAR(500) NOT NULL,
                product_name VARCHAR(255) NOT NULL,
                product_category VARCHAR(100) NOT NULL,
                product_price DECIMAL(10,2) NOT NULL,
                reserve_date DATE NULL,
                return_date DATE NULL,
                size_1 INT NULL,
                size_2 INT NULL,
                size_3 INT NULL,
                saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES gowns(id) ON DELETE CASCADE
            )
        ";
        
        $this->conn->query($savedProductsTable);
        
        // Add reservation columns to saved_products table if they don't exist
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS reserve_date DATE NULL AFTER product_price");
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS return_date DATE NULL AFTER reserve_date");
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS size_1 VARCHAR(50) NULL AFTER return_date");
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS size_2 VARCHAR(50) NULL AFTER size_1");
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS size_3 VARCHAR(50) NULL AFTER size_2");
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS size_4 VARCHAR(50) NULL AFTER size_3");
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS size_5 VARCHAR(50) NULL AFTER size_4");
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS size_6 VARCHAR(50) NULL AFTER size_5");
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS size_7 VARCHAR(50) NULL AFTER size_6");
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS size_8 VARCHAR(50) NULL AFTER size_7");
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS size_9 VARCHAR(50) NULL AFTER size_8");
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS size_10 VARCHAR(50) NULL AFTER size_9");
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS quantity INT DEFAULT 1 AFTER size_10");
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS stock INT DEFAULT 999 AFTER quantity");

        // Drop old columns and rename to new column names
        $this->conn->query("ALTER TABLE saved_products DROP COLUMN IF EXISTS reserve_time");
        $this->conn->query("ALTER TABLE saved_products DROP COLUMN IF EXISTS return_time");
        $this->conn->query("ALTER TABLE saved_products DROP COLUMN IF EXISTS reserve_size");
        $this->conn->query("ALTER TABLE saved_products DROP COLUMN IF EXISTS bust");
        $this->conn->query("ALTER TABLE saved_products DROP COLUMN IF EXISTS waist");
        $this->conn->query("ALTER TABLE saved_products DROP COLUMN IF EXISTS hips");

        // Drop foreign key constraints first
        $this->conn->query("ALTER TABLE saved_products DROP FOREIGN KEY IF EXISTS saved_products_ibfk_1");
        $this->conn->query("ALTER TABLE saved_products DROP FOREIGN KEY IF EXISTS saved_products_ibfk_2");

        // Drop unique key constraint to allow saving same product multiple times
        $this->conn->query("ALTER TABLE saved_products DROP INDEX IF EXISTS unique_user_product");

        // Re-add foreign key constraints
        $this->conn->query("ALTER TABLE saved_products ADD CONSTRAINT saved_products_ibfk_1 FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE CASCADE");
        $this->conn->query("ALTER TABLE saved_products ADD CONSTRAINT saved_products_ibfk_2 FOREIGN KEY (product_id) REFERENCES gowns(id) ON DELETE CASCADE");
        
        // Add product_status column to saved_products table if it doesn't exist
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS product_status ENUM('saved', 'buy', 'reserve') DEFAULT 'saved' AFTER size_3");
        
        // Add purchase_price column to saved_products table if it doesn't exist
        $this->conn->query("ALTER TABLE saved_products ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(10,2) DEFAULT 0.00 AFTER product_price");
        
        // Update existing saved_products with purchase_price from gowns table
        $this->conn->query("UPDATE saved_products sp 
                           LEFT JOIN gowns g ON sp.product_id = g.id 
                           SET sp.purchase_price = g.purchase_price 
                           WHERE sp.purchase_price = 0 OR sp.purchase_price IS NULL");

        // Create reserve_products table for user reservations
        $reserveProductsTable = "
            CREATE TABLE IF NOT EXISTS reserve_products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                product_id INT DEFAULT 0,
                product_name VARCHAR(255) NOT NULL,
                category VARCHAR(100) NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                model_path VARCHAR(500) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                payment_method ENUM('reserve', 'gcash') NOT NULL,
                total_price DECIMAL(10,2) NOT NULL,
                reserve_date DATE NOT NULL,
                return_date DATE NOT NULL,
                reserve_time VARCHAR(20) DEFAULT NULL,
                return_time VARCHAR(20) DEFAULT NULL,
                size_1 VARCHAR(50) NULL,
                size_2 VARCHAR(50) NULL,
                size_3 VARCHAR(50) NULL,
                size_4 VARCHAR(50) NULL,
                size_5 VARCHAR(50) NULL,
                size_6 VARCHAR(50) NULL,
                size_7 VARCHAR(50) NULL,
                size_8 VARCHAR(50) NULL,
                size_9 VARCHAR(50) NULL,
                size_10 VARCHAR(50) NULL,
                quantity INT DEFAULT 1,
                reserved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE CASCADE
            )
        ";

        $this->conn->query($reserveProductsTable);

        // Add new columns to existing reserve_products table if they don't exist
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reservation_group_id VARCHAR(50) DEFAULT NULL AFTER user_id");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reservation_code VARCHAR(50) DEFAULT NULL AFTER reservation_group_id");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS product_id INT DEFAULT 0 AFTER user_id");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS category VARCHAR(100) NOT NULL AFTER product_name");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NOT NULL AFTER category");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) NOT NULL AFTER model_path");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) NOT NULL AFTER first_name");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS payment_method ENUM('reserve', 'gcash') NOT NULL AFTER last_name");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS payment_option VARCHAR(50) DEFAULT 'full' AFTER payment_method");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS downpayment_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_option");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS balance_amount DECIMAL(10,2) DEFAULT 0.00 AFTER downpayment_amount");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS total_price DECIMAL(10,2) NOT NULL AFTER balance_amount");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reserve_date DATE NOT NULL AFTER total_price");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS return_date DATE NOT NULL AFTER reserve_date");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reserve_time VARCHAR(20) DEFAULT NULL AFTER return_date");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS return_time VARCHAR(20) DEFAULT NULL AFTER reserve_time");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS size_1 VARCHAR(50) NULL AFTER return_time");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS size_2 VARCHAR(50) NULL AFTER size_1");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS size_3 VARCHAR(50) NULL AFTER size_2");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS size_4 VARCHAR(50) NULL AFTER size_3");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS size_5 VARCHAR(50) NULL AFTER size_4");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS size_6 VARCHAR(50) NULL AFTER size_5");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS size_7 VARCHAR(50) NULL AFTER size_6");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS size_8 VARCHAR(50) NULL AFTER size_7");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS size_9 VARCHAR(50) NULL AFTER size_8");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS size_10 VARCHAR(50) NULL AFTER size_9");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS quantity INT DEFAULT 1 AFTER size_10");

        // Convert old INT size columns to VARCHAR for string measurements
        $this->conn->query("ALTER TABLE reserve_products MODIFY COLUMN size_1 VARCHAR(50) NULL");
        $this->conn->query("ALTER TABLE reserve_products MODIFY COLUMN size_2 VARCHAR(50) NULL");
        $this->conn->query("ALTER TABLE reserve_products MODIFY COLUMN size_3 VARCHAR(50) NULL");

        // Drop old columns and rename to new column names
        $this->conn->query("ALTER TABLE reserve_products DROP COLUMN IF EXISTS size");
        $this->conn->query("ALTER TABLE reserve_products DROP COLUMN IF EXISTS bust");
        $this->conn->query("ALTER TABLE reserve_products DROP COLUMN IF EXISTS waist");
        $this->conn->query("ALTER TABLE reserve_products DROP COLUMN IF EXISTS hips");

        // Drop old columns if they exist
        $this->conn->query("ALTER TABLE reserve_products DROP COLUMN IF EXISTS product_category");
        $this->conn->query("ALTER TABLE reserve_products DROP COLUMN IF EXISTS product_size");
        $this->conn->query("ALTER TABLE reserve_products DROP COLUMN IF EXISTS product_price");
        $this->conn->query("ALTER TABLE reserve_products DROP COLUMN IF EXISTS status");

        // Add order_status column if it doesn't exist
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS order_status VARCHAR(20) DEFAULT 'pending' AFTER reserved_at");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS product_status VARCHAR(20) DEFAULT 'reserve' AFTER order_status");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL AFTER product_status");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS cancellation_images TEXT NULL AFTER cancellation_reason");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS is_rescheduled TINYINT(1) DEFAULT 0 AFTER cancellation_images");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS rescheduled_at DATETIME DEFAULT NULL AFTER is_rescheduled");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS original_reserve_date DATE DEFAULT NULL AFTER rescheduled_at");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS original_return_date DATE DEFAULT NULL AFTER original_reserve_date");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reschedule_reason TEXT DEFAULT NULL AFTER original_return_date");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS is_lost TINYINT(1) DEFAULT 0 AFTER reschedule_reason");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_status VARCHAR(50) DEFAULT NULL AFTER is_lost");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_reason TEXT DEFAULT NULL AFTER lost_status");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_selling_price DECIMAL(10,2) DEFAULT 0.00 AFTER lost_reason");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_user_paid DECIMAL(10,2) DEFAULT 0.00 AFTER lost_selling_price");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_overdue_fee DECIMAL(10,2) DEFAULT 0.00 AFTER lost_user_paid");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_total_due DECIMAL(10,2) DEFAULT 0.00 AFTER lost_overdue_fee");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_payment_proof VARCHAR(500) DEFAULT NULL AFTER lost_total_due");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_requested_at DATETIME DEFAULT NULL AFTER lost_payment_proof");
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS lost_settled_at DATETIME DEFAULT NULL AFTER lost_requested_at");

        // Create ai_skinbased table for AI skin-based product suggestions
        $aiSkinBasedTable = "
            CREATE TABLE IF NOT EXISTS ai_skinbased (
                id INT AUTO_INCREMENT PRIMARY KEY,
                question TEXT NOT NULL,
                ai_message TEXT NOT NULL,
                skin_tone_colors JSON NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ";

        $this->conn->query($aiSkinBasedTable);

        // Create ai_conversations table for persisting AI chat history
        $aiConversationsTable = "
            CREATE TABLE IF NOT EXISTS ai_conversations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                user_identifier VARCHAR(255) DEFAULT NULL,
                question TEXT NOT NULL,
                answer TEXT NOT NULL,
                product_ids TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->conn->query($aiConversationsTable);

        // Create admin_chat_messages table for user-to-admin chat
        $adminChatTable = "
            CREATE TABLE IF NOT EXISTS admin_chat_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                sender ENUM('user','admin') NOT NULL DEFAULT 'user',
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES system_users(id) ON DELETE CASCADE
            )
        ";
        $this->conn->query($adminChatTable);

        // Insert default skin-based data if not exists
        $checkData = $this->conn->query("SELECT COUNT(*) as count FROM ai_skinbased");
        $row = $checkData->fetch_assoc();

        if ($row['count'] == 0) {
            $question = "Can you suggest products based on my skin?";
            $aiMessage = "Select your skin type from the options below.";

            // Skin tone colors as JSON
            $skinToneColors = json_encode([
                'light' => ['White', 'Ivory', 'Cream', 'Beige', 'Camel', 'Light Gray', 'Charcoal', 'Navy', 'Royal Blue', 'Cobalt Blue', 'Sky Blue', 'Baby Blue', 'Emerald Green', 'Forest Green', 'Teal', 'Turquoise', 'Mint Green', 'Lavender', 'Lilac', 'Blush Pink', 'Rose Pink', 'Coral', 'Peach', 'Burgundy', 'Wine', 'Soft Red', 'Plum'],
                'medium-light' => ['Ivory', 'Cream', 'Beige', 'Camel', 'Light Gray', 'Navy', 'Royal Blue', 'Sky Blue', 'Emerald Green', 'Teal', 'Turquoise', 'Lavender', 'Lilac', 'Blush Pink', 'Rose Pink', 'Coral', 'Peach'],
                'medium' => ['Beige', 'Camel', 'Light Gray', 'Navy', 'Royal Blue', 'Emerald Green', 'Teal', 'Lavender', 'Rose Pink', 'Coral', 'Burgundy', 'Wine'],
                'medium-dark' => ['Camel', 'Charcoal', 'Navy', 'Forest Green', 'Emerald Green', 'Burgundy', 'Wine', 'Plum'],
                'dark' => ['Charcoal', 'Navy', 'Forest Green', 'Burgundy', 'Wine', 'Plum']
            ]);

            $stmt = $this->conn->prepare("INSERT INTO ai_skinbased (question, ai_message, skin_tone_colors) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $question, $aiMessage, $skinToneColors);
            $stmt->execute();
        }

        // ── system_staffs: Staff login accounts ──────────────────────────────
        $systemStaffsTable = "
            CREATE TABLE IF NOT EXISTS system_staffs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                firstname VARCHAR(50) DEFAULT NULL,
                lastname VARCHAR(50) DEFAULT NULL,
                middlename VARCHAR(50) DEFAULT NULL,
                suffix VARCHAR(20) DEFAULT NULL,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                verification_token VARCHAR(255) DEFAULT NULL,
                token_expiry INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->conn->query($systemStaffsTable);

        // Ensure columns exist for existing tables
        $this->conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS firstname VARCHAR(50) DEFAULT NULL");
        $this->conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS lastname VARCHAR(50) DEFAULT NULL");
        $this->conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS middlename VARCHAR(50) DEFAULT NULL");
        $this->conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS suffix VARCHAR(20) DEFAULT NULL AFTER lastname");
        $this->conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) DEFAULT NULL");
        $this->conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS token_expiry INT DEFAULT NULL");
        $this->conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1");

        // Insert default staff account: alfcode4@gmail.com / vergara143
        $defaultStaffHash = password_hash('vergara143', PASSWORD_BCRYPT);
        $this->conn->query("
            INSERT IGNORE INTO system_staffs (name, email, password, is_active)
            VALUES ('Alf Staff', 'alfcode4@gmail.com', '$defaultStaffHash', 1)
        ");

        // Create app_settings table for system configurations (e.g. GCash QR, GCash number)
        $appSettingsTable = "
            CREATE TABLE IF NOT EXISTS app_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ";
        $this->conn->query($appSettingsTable);

        // Seed default GCash & Footer settings if not exists
        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('gcash_qr_path', 'images/gcash_qr_temp.jpg')");
        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('gcash_number', '09123456789')");
        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('gcash_account_name', 'ClosetGoo Admin')");
        
        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('site_name', 'ClosetGoo')");
        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('site_email', 'jmbmanagementservices18@gmail.com')");
        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('site_phone', '0998 868 9518')");
        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('site_address', 'Alaminos City, Pangasinan')");

        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('footer_about_title', 'ABOUT CLOSETGOO')");
        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('footer_about_link', 'javascript:void(0)')");
        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('footer_terms_link', 'login_user/terms.php')");
        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('footer_privacy_link', 'login_user/privacy.php')");
        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('footer_contact_title', 'CONTACT US')");

        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('footer_social_title', 'FOLLOW US')");
        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('footer_facebook', 'https://www.facebook.com/ACV.alf?mibextid=ZbWKwL')");
        $this->conn->query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('footer_instagram', 'https://www.instagram.com/iam_alff?igsh=YWdjcmQ2MmJ0OXkw')");

        // Create payment_proofs table for uploaded customer transaction receipts
        $paymentProofsTable = "
            CREATE TABLE IF NOT EXISTS payment_proofs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                reservation_group_id VARCHAR(50) DEFAULT NULL,
                image_path VARCHAR(500) NOT NULL,
                payment_method VARCHAR(50) DEFAULT 'gcash',
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->conn->query($paymentProofsTable);

        // Add payment_proof column to reserve_products
        $this->conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS payment_proof VARCHAR(500) DEFAULT NULL AFTER payment_option");

        // Create banners table for homepage promotional carousel
        $bannersTable = "
            CREATE TABLE IF NOT EXISTS banners (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) DEFAULT '',
                image_path VARCHAR(500) NOT NULL,
                link_url VARCHAR(500) DEFAULT NULL,
                display_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ";
        $this->conn->query($bannersTable);

        // Seed default banners if table is empty
        $checkBanners = $this->conn->query("SELECT COUNT(*) as count FROM banners");
        if ($checkBanners) {
            $bRow = $checkBanners->fetch_assoc();
            if ($bRow['count'] == 0) {
                $this->conn->query("INSERT INTO banners (title, image_path, link_url, display_order, is_active) VALUES 
                    ('ClosetGo Promo 1', 'images/background/1.jpg', '', 1, 1),
                    ('ClosetGo Promo 2', 'images/background/2.jpg', '', 2, 1),
                    ('ClosetGo Promo 3', 'images/background/3.jpg', '', 3, 1),
                    ('ClosetGo Promo 4', 'images/background/4.jpg', '', 4, 1)
                ");
            }
        }
    }


    public function getConnection() {
        return $this->conn;
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }

    public function getLastInsertId() {
        return $this->conn->insert_id;
    }

    public function query($sql) {
        return $this->conn->query($sql);
    }

    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>
