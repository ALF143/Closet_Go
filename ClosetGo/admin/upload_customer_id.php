<?php
session_start();
require_once '../database/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$idType = isset($_POST['id_type']) ? trim($_POST['id_type']) : '';

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

if (empty($idType)) {
    echo json_encode(['success' => false, 'message' => 'ID type is required']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'DB connection failed']);
        exit();
    }

    // Check if user exists
    $checkUser = $conn->prepare("SELECT id FROM system_users WHERE id = ?");
    $checkUser->bind_param("i", $userId);
    $checkUser->execute();
    $userResult = $checkUser->get_result();

    if ($userResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    $checkUser->close();

    // Handle file uploads
    $uploadDir = '../uploads/valid_ids/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $frontImagePath = null;
    $backImagePath = null;

    // Process front ID image
    if (!isset($_FILES['id_front_image']) || $_FILES['id_front_image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Front ID image is required']);
        exit();
    }

    $frontFile = $_FILES['id_front_image'];
    $frontExt = strtolower(pathinfo($frontFile['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($frontExt, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type for front ID. Allowed: JPG, PNG, WEBP']);
        exit();
    }

    $frontFileName = 'user_' . $userId . '_front_' . time() . '.' . $frontExt;
    $frontFilePath = $uploadDir . $frontFileName;

    if (!move_uploaded_file($frontFile['tmp_name'], $frontFilePath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload front ID image']);
        exit();
    }

    $frontImagePath = 'uploads/valid_ids/' . $frontFileName;

    // Process back ID image (optional)
    if (isset($_FILES['id_back_image']) && $_FILES['id_back_image']['error'] === UPLOAD_ERR_OK) {
        $backFile = $_FILES['id_back_image'];
        $backExt = strtolower(pathinfo($backFile['name'], PATHINFO_EXTENSION));

        if (!in_array($backExt, $allowedExts)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type for back ID. Allowed: JPG, PNG, WEBP']);
            exit();
        }

        $backFileName = 'user_' . $userId . '_back_' . time() . '.' . $backExt;
        $backFilePath = $uploadDir . $backFileName;

        if (!move_uploaded_file($backFile['tmp_name'], $backFilePath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload back ID image']);
            exit();
        }

        $backImagePath = 'uploads/valid_ids/' . $backFileName;
    }

    // Update user record
    $updateSql = "UPDATE system_users SET id_type = ?";
    $params = [$idType];
    $types = "s";

    if ($frontImagePath) {
        $updateSql .= ", id_front_image = ?";
        $params[] = $frontImagePath;
        $types .= "s";
    }

    if ($backImagePath) {
        $updateSql .= ", id_back_image = ?";
        $params[] = $backImagePath;
        $types .= "s";
    }

    $updateSql .= " WHERE id = ?";
    $params[] = $userId;
    $types .= "i";

    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param($types, ...$params);

    if ($updateStmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Valid ID uploaded successfully',
            'front_image' => $frontImagePath,
            'back_image' => $backImagePath
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user record']);
    }

    $updateStmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>