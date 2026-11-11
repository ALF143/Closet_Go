<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

// Include database configuration
require_once __DIR__ . '/database/db_config.php';

// Get the email and password from POST request
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

// Validate password requirements
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    exit;
}

if (!preg_match('/^[A-Z]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password must start with an uppercase letter']);
    exit;
}

if (!preg_match('/[!@#$%^&*]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least 1 symbol (!@#$%^&*)']);
    exit;
}

try {
    // Initialize database
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again.']);
        exit;
    }
    
    // Check if email already exists in system_users table
    $checkSql = "SELECT id FROM system_users WHERE email = ? LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $checkStmt->close();
            echo json_encode(['success' => false, 'message' => 'Email already exists. Please use a different email or login.']);
            exit;
        }
        $checkStmt->close();
    }
    
    // Hash the password for security
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user into system_users table
    $insertSql = "INSERT INTO system_users (email, password) VALUES (?, ?)";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        echo json_encode(['success' => false, 'message' => 'Query preparation error: ' . $conn->error]);
        exit;
    }

    $insertStmt->bind_param("ss", $email, $hashedPassword);
    
    if ($insertStmt->execute()) {
        $newId = $insertStmt->insert_id;
        $insertStmt->close();
        echo json_encode(['success' => true, 'message' => 'Sign up successful! Welcome to ClosetGo.', 'user_id' => $newId]);
    } else {
        $err = $insertStmt->error;
        $insertStmt->close();
        echo json_encode(['success' => false, 'message' => 'Failed to create account: ' . $err]);
    }
    
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;

