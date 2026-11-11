<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Handle AJAX requests for profile operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Include database configuration
    require_once 'database/db_config.php';
    
    $action = $_POST['action'];
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        switch ($action) {
            case 'save_profile_image':
                // Check if file was uploaded
                if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
                    exit;
                }
                
                // Check if user is logged in
                $sessionUser = $_SESSION['user_id'] ?? 0;
                $sessionEmail = $_SESSION['email'] ?? ($_SESSION['user_email'] ?? '');
                $email = !empty($sessionEmail) ? $sessionEmail : ($_POST['email'] ?? '');

                if ($sessionUser <= 0 && empty($sessionEmail) && empty($_POST['email'])) {
                    echo json_encode(['success' => false, 'message' => 'User not authenticated. Please log in first.']);
                    exit;
                }
                
                $file = $_FILES['profile_image'];
                
                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!in_array($file['type'], $allowedTypes)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.']);
                    exit;
                }
                
                // Validate file size (max 5MB)
                $maxSize = 5 * 1024 * 1024; // 5MB
                if ($file['size'] > $maxSize) {
                    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
                    exit;
                }
                
                // Create upload directory if it doesn't exist
                $uploadDir = 'uploads/profile_images/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $fileName = uniqid('profile_', true) . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                $filePath = $uploadDir . $fileName;
                
                // Move uploaded file
                if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
                    exit;
                }
                
                // Get user email
                $email = $_POST['email'] ?? $_SESSION['user_email'] ?? '';
                
                if (empty($email)) {
                    echo json_encode(['success' => false, 'message' => 'User email not found']);
                    exit;
                }
                
                // Update profile image in database
                $updateSql = "UPDATE system_users SET profile_image = ? WHERE email = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("ss", $filePath, $email);
                
                if ($updateStmt->execute()) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Profile image updated successfully!',
                        'image_path' => $filePath
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update database']);
                    // Clean up uploaded file if database update fails
                    unlink($filePath);
                }
                break;
                
            case 'save_all_names':
                $sessionUser = $_SESSION['user_id'] ?? 0;
                $sessionEmail = $_SESSION['email'] ?? ($_SESSION['user_email'] ?? '');
                $email = !empty($sessionEmail) ? $sessionEmail : ($_POST['email'] ?? '');

                if ($sessionUser <= 0 && empty($sessionEmail) && empty($_POST['email'])) {
                    echo json_encode(['success' => false, 'message' => 'User not authenticated. Please log in first.']);
                    exit;
                }

                // Get POST data
                $firstName = $_POST['firstName'] ?? '';
                $middleName = $_POST['middleName'] ?? '';
                $lastName = $_POST['lastName'] ?? '';
                $suffix = $_POST['suffix'] ?? '';
                $province = $_POST['province'] ?? '';
                $city = $_POST['city'] ?? '';
                $barangay = $_POST['barangay'] ?? '';
                $phone = trim($_POST['phone'] ?? '');
                $userEmail = $_POST['userEmail'] ?? $email;
                
                if (empty($email)) {
                    echo json_encode(['success' => false, 'message' => 'Email is required. Please log in first.']);
                    exit;
                }
                
                // Validate contact number: must be exactly 11 digits and start with 09
                if (!empty($phone)) {
                    $phone = preg_replace('/\D/', '', $phone);
                    if (!preg_match('/^09\d{9}$/', $phone)) {
                        echo json_encode([
                            'success' => false, 
                            'message' => 'Contact number must be exactly 11 digits and start with 09 (e.g. 09123456789).'
                        ]);
                        exit;
                    }
                }
                
                // Update all name fields, location, and contact info in database (Email remains fixed for logged-in user)
                $updateSql = "UPDATE system_users SET 
                             first_name = ?, 
                             middle_name = ?, 
                             last_name = ?, 
                             suffix = ?,
                             province = ?,
                             city = ?,
                             barangay = ?,
                             phone = ?
                             WHERE email = ?";
                
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("sssssssss", $firstName, $middleName, $lastName, $suffix, $province, $city, $barangay, $phone, $email);
                
                if ($updateStmt->execute()) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'All profile data updated successfully!',
                        'data' => [
                            'firstName' => $firstName,
                            'middleName' => $middleName,
                            'lastName' => $lastName,
                            'suffix' => $suffix,
                            'province' => $province,
                            'city' => $city,
                            'barangay' => $barangay,
                            'phone' => $phone,
                            'email' => $email
                        ]
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update names']);
                }
                break;
                
            case 'get_profile_image':
                // Get email from POST request
                $email = $_POST['email'] ?? '';
                
                if (empty($email)) {
                    echo json_encode(['success' => false, 'message' => 'Email is required']);
                    exit;
                }
                
                // Get user profile image from database
                $sql = "SELECT profile_image FROM system_users WHERE email = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    $profileImage = $user['profile_image'];
                    
                    echo json_encode([
                        'success' => true,
                        'profile_image' => $profileImage
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
                break;
                
            case 'get_user_names':
                // Get email from POST request
                $email = $_POST['email'] ?? '';
                
                if (empty($email)) {
                    echo json_encode(['success' => false, 'message' => 'Email is required']);
                    exit;
                }
                
                // Get user names, location, contact info, and ID verification from database
                $sql = "SELECT first_name, middle_name, last_name, suffix, province, city, barangay, phone, email, id_type, id_front_image, id_back_image, id_verified, id_verified_at FROM system_users WHERE email = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    
                    echo json_encode([
                        'success' => true,
                        'first_name' => $user['first_name'] ?? '',
                        'middle_name' => $user['middle_name'] ?? '',
                        'last_name' => $user['last_name'] ?? '',
                        'suffix' => $user['suffix'] ?? '',
                        'province' => $user['province'] ?? '',
                        'city' => $user['city'] ?? '',
                        'barangay' => $user['barangay'] ?? '',
                        'phone' => $user['phone'] ?? '',
                        'email' => $user['email'] ?? '',
                        'id_type' => $user['id_type'] ?? '',
                        'id_front_image' => $user['id_front_image'] ?? '',
                        'id_back_image' => $user['id_back_image'] ?? '',
                        'id_verified' => intval($user['id_verified'] ?? 0),
                        'id_verified_at' => $user['id_verified_at'] ?? ''
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
                break;

            case 'save_valid_id':
                $sessionUser = $_SESSION['user_id'] ?? 0;
                $sessionEmail = $_SESSION['email'] ?? ($_SESSION['user_email'] ?? '');
                $email = !empty($sessionEmail) ? $sessionEmail : ($_POST['email'] ?? '');
                $idType = trim($_POST['id_type'] ?? '');
                $removeFront = isset($_POST['remove_front']) && $_POST['remove_front'] === '1';
                $removeBack = isset($_POST['remove_back']) && $_POST['remove_back'] === '1';

                if ($sessionUser <= 0 && empty($sessionEmail) && empty($_POST['email'])) {
                    echo json_encode(['success' => false, 'message' => 'User not authenticated. Please log in first.']);
                    exit;
                }
                if (empty($email)) {
                    echo json_encode(['success' => false, 'message' => 'User email is required. Please log in first.']);
                    exit;
                }
                if (empty($idType)) {
                    echo json_encode(['success' => false, 'message' => 'Please select a valid Government ID type.']);
                    exit;
                }

                // Check existing images in database
                $existStmt = $conn->prepare("SELECT id_front_image, id_back_image, id_verified FROM system_users WHERE email = ? LIMIT 1");
                $existStmt->bind_param("s", $email);
                $existStmt->execute();
                $existRes = $existStmt->get_result();
                $existRow = $existRes ? $existRes->fetch_assoc() : null;
                $existStmt->close();

                // If account's ID is verified, it CANNOT be changed!
                if (intval($existRow['id_verified'] ?? 0) === 1) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Your ID has already been verified by the admin and cannot be changed.',
                        'id_verified' => 1
                    ]);
                    exit;
                }

                $frontPath = $removeFront ? null : ($existRow['id_front_image'] ?? null);
                $backPath = $removeFront ? null : ($existRow['id_back_image'] ?? $frontPath);

                $uploadDir = 'uploads/valid_ids/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
                $maxSize = 8 * 1024 * 1024; // 8MB

                // Handle ID Photo (Card front including the user)
                if (isset($_FILES['id_front_image']) && $_FILES['id_front_image']['error'] === UPLOAD_ERR_OK) {
                    $fileFront = $_FILES['id_front_image'];
                    if (!in_array($fileFront['type'], $allowedTypes)) {
                        echo json_encode(['success' => false, 'message' => 'Invalid ID photo format. Allowed: JPG, PNG, WEBP.']);
                        exit;
                    }
                    if ($fileFront['size'] > $maxSize) {
                        echo json_encode(['success' => false, 'message' => 'ID photo file is too large. Max size is 8MB.']);
                        exit;
                    }
                    $extFront = pathinfo($fileFront['name'], PATHINFO_EXTENSION);
                    $fileNameFront = 'id_user_' . uniqid() . '_' . time() . '.' . $extFront;
                    $targetFront = $uploadDir . $fileNameFront;
                    if (move_uploaded_file($fileFront['tmp_name'], $targetFront)) {
                        $frontPath = $targetFront;
                        $backPath = $targetFront;
                    }
                }

                if (empty($frontPath)) {
                    echo json_encode(['success' => false, 'message' => 'Please upload a photo of your ID card front including your face.']);
                    exit;
                }

                $backPath = !empty($backPath) ? $backPath : $frontPath;

                // When re-uploading an ID (e.g. after being rejected or updating), reset status to pending (0) for admin review
                $updateSql = "UPDATE system_users SET id_type = ?, id_front_image = ?, id_back_image = ?, id_verified = 0, id_verified_at = NULL WHERE email = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("ssss", $idType, $frontPath, $backPath, $email);

                if ($updateStmt->execute()) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Valid ID uploaded! Please wait for admin verification before you can reserve or buy.',
                        'id_type' => $idType,
                        'id_front_image' => $frontPath,
                        'id_back_image' => $backPath,
                        'id_verified' => 0
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save ID verification in database.']);
                }
                break;

            case 'get_valid_id':
                $email = $_POST['email'] ?? ($_SESSION['user_email'] ?? ($_SESSION['email'] ?? ''));
                if (empty($email)) {
                    echo json_encode(['success' => false, 'message' => 'Email is required']);
                    exit;
                }
                $sql = "SELECT id_type, id_front_image, id_back_image, id_verified, id_verified_at FROM system_users WHERE email = ? LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $row = $res->fetch_assoc()) {
                    echo json_encode([
                        'success' => true,
                        'id_type' => $row['id_type'] ?? '',
                        'id_front_image' => $row['id_front_image'] ?? '',
                        'id_back_image' => $row['id_back_image'] ?? '',
                        'id_verified' => intval($row['id_verified'] ?? 0),
                        'id_verified_at' => $row['id_verified_at'] ?? ''
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                break;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch current user from database directly in PHP for instant, zero-delay rendering
require_once 'database/db_config.php';
$currentUserData = null;
$sessionUserId = $_SESSION['user_id'] ?? 0;
$sessionEmail = $_SESSION['email'] ?? ($_SESSION['user_email'] ?? '');

try {
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        $stmt = null;
        if ($sessionUserId > 0) {
            $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, suffix, province, city, barangay, phone, email, profile_image, id_type, id_front_image, id_back_image, id_verified, id_verified_at FROM system_users WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $sessionUserId);
        } else if (!empty($sessionEmail)) {
            $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, suffix, province, city, barangay, phone, email, profile_image, id_type, id_front_image, id_back_image, id_verified, id_verified_at FROM system_users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $sessionEmail);
        }
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $currentUserData = $row;
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {
    // Ignore
}

$isLoggedIn = !empty($currentUserData);
$displayName = '';
$userProfileImage = 'images/icon/profile_logo.png';
if ($currentUserData) {
    $fullName = trim(($currentUserData['first_name'] ?? '') . ' ' . ($currentUserData['last_name'] ?? ''));
    if (!empty($fullName)) {
        $displayName = $fullName;
    } elseif (!empty($currentUserData['first_name'])) {
        $displayName = $currentUserData['first_name'];
    } elseif (!empty($currentUserData['email'])) {
        $displayName = $currentUserData['email'];
    }
    if (!empty($currentUserData['profile_image'])) {
        $userProfileImage = $currentUserData['profile_image'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - ClosetGo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/homepage.css?v=<?php echo @filemtime('css/homepage.css'); ?>">
    <style>
        /* ===== Profile View Styles ===== */
        .pv-hero {
            position: relative;
            background: linear-gradient(160deg, #1a1a1a 0%, #2a2a2a 55%, #1a1a1a 100%);
            padding: 44px 20px 28px;
            text-align: center;
            border-bottom: 1px solid #3a3a3a;
            overflow: hidden;
        }
        .pv-hero::before {
            content: '';
            position: absolute;
            top: -30%;
            left: 50%;
            transform: translateX(-50%);
            width: 340px;
            height: 340px;
            background: radial-gradient(circle, rgba(124,99,0,0.18) 0%, transparent 65%);
            pointer-events: none;
        }
        .pv-avatar-wrap {
            position: relative;
            display: inline-block;
            cursor: pointer;
            margin-bottom: 14px;
        }
        .pv-avatar {
            width: 92px;
            height: 92px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #7c6300;
            box-shadow: 0 0 0 4px rgba(124,99,0,0.22), 0 8px 28px rgba(0,0,0,0.55);
            transition: transform 0.22s ease, box-shadow 0.22s ease;
            display: block;
        }
        .pv-avatar-wrap:hover .pv-avatar {
            transform: scale(1.03);
            box-shadow: 0 0 0 5px rgba(255,215,0,0.35), 0 8px 30px rgba(0,0,0,0.6);
        }
        .pv-avatar-wrap:active .pv-avatar { transform: scale(0.96); }
        .pv-avatar-edit-badge {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 26px;
            height: 26px;
            background: #7c6300;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: #fff;
            border: 2px solid #1a1a1a;
            pointer-events: none;
        }
        .pv-name {
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 4px;
            letter-spacing: 0.3px;
        }
        .pv-email {
            color: #888;
            font-size: 0.8rem;
            margin: 0 0 4px;
        }
        .pv-sections {
            padding: 14px 12px 100px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .pv-col {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .pv-section-card {
            background: #2a2a2a;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
        }
        .pv-section-title {
            color: #ffd700;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1.1px;
            text-transform: uppercase;
            padding: 11px 16px 9px;
            border-bottom: 1px solid #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pv-row {
            display: flex;
            align-items: center;
            padding: 13px 16px;
            cursor: pointer;
            transition: background 0.15s ease;
            gap: 10px;
        }
        .pv-row:active { background: rgba(255,255,255,0.06); }
        .pv-row.pv-row-fixed {
            cursor: default !important;
            user-select: text;
        }
        .pv-row.pv-row-fixed:hover,
        .pv-row.pv-row-fixed:active {
            background: transparent !important;
        }
        .pv-row.pv-row-fixed .pv-row-value {
            color: #aaa;
            user-select: text;
        }
        .pv-lock-icon {
            color: #666;
            font-size: 0.72rem;
            flex-shrink: 0;
            margin-left: 2px;
        }
        .pv-row-label {
            color: #ccc;
            font-size: 0.87rem;
            flex: 1;
        }
        .pv-row-value {
            color: #fff;
            font-size: 0.83rem;
            font-weight: 500;
            max-width: 55%;
            text-align: right;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pv-row-value.pv-empty {
            color: #d4a017;
            font-weight: 500;
            font-size: 0.78rem;
        }
        .pv-chevron { color: #555; font-size: 0.68rem; flex-shrink: 0; }
        .pv-link-icon { color: #ffd700; font-size: 0.85rem; width: 18px; text-align: center; flex-shrink: 0; }
        .pv-divider { height: 1px; background: #333; margin: 0 16px; }

        /* Inline Row Editable Inputs & Auto-save */
        .pv-row.pv-row-editable {
            cursor: pointer;
            position: relative;
        }
        .pv-row.pv-row-editable:hover {
            background: rgba(255, 255, 255, 0.04);
        }
        .pv-row.pv-row-editable.is-editing {
            background: rgba(255, 215, 0, 0.03);
        }
        .pv-inline-input-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            position: relative;
            max-width: 65%;
        }
        .pv-inline-input {
            width: 100%;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 6px;
            color: #ffffff;
            font-size: 0.84rem;
            font-weight: 500;
            text-align: right;
            padding: 5px 8px;
            outline: none;
            transition: all 0.2s ease;
            box-sizing: border-box;
            font-family: inherit;
        }
        .pv-inline-input::placeholder {
            color: #d4a017;
            font-weight: 500;
            font-size: 0.78rem;
            font-style: normal;
        }
        .pv-inline-input:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.1);
        }
        .pv-inline-input:focus {
            background: #181920;
            border-color: #ffd700;
            box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.2);
            color: #ffffff;
            text-align: right;
        }
        .pv-inline-status {
            position: absolute;
            right: 4px;
            top: -14px;
            font-size: 0.68rem;
            font-weight: 600;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s ease, transform 0.2s ease;
            transform: translateY(2px);
            white-space: nowrap;
            z-index: 5;
        }
        .pv-inline-status.show {
            opacity: 1;
            transform: translateY(0);
        }
        .pv-inline-status.saving {
            color: #eab308;
        }
        .pv-inline-status.saved {
            color: #4ade80;
        }
        .pv-inline-status.error {
            color: #ef4444;
        }

        /* Inline Location Selectors (No modal, inline select per row) */
        .pv-row.pv-row-select {
            cursor: pointer;
        }
        .pv-inline-select-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            position: relative;
            max-width: 65%;
        }
        .pv-inline-select {
            width: 100%;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 6px;
            color: #ffffff;
            font-size: 0.84rem;
            font-weight: 500;
            text-align: right;
            text-align-last: right;
            padding: 5px 22px 5px 8px;
            outline: none;
            transition: all 0.2s ease;
            box-sizing: border-box;
            font-family: inherit;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }
        .pv-inline-select:disabled {
            color: #666675;
            cursor: not-allowed;
            opacity: 0.65;
        }
        .pv-inline-select option {
            background: #181920;
            color: #ffffff;
            text-align: left;
            padding: 8px 10px;
        }
        .pv-inline-select option:disabled {
            color: #777785;
        }
        .pv-inline-select:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.12);
        }
        .pv-inline-select:focus {
            background: #181920;
            border-color: #ffd700;
            box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.2);
            color: #ffffff;
        }
        .pv-inline-select-chevron {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.65rem;
            color: #ffd700;
            pointer-events: none;
            transition: all 0.2s ease;
        }
        .pv-inline-select:disabled + .pv-inline-select-chevron {
            color: #444450;
            opacity: 0.4;
        }

        .pv-logout-btn {
            width: 100%;
            background: rgba(220,38,38,0.1);
            border: 1px solid rgba(220,38,38,0.3);
            color: #ef4444;
            padding: 14px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .pv-logout-btn:active { background: rgba(220,38,38,0.2); }
        .pv-login-btn {
            width: 100%;
            background: rgba(124,99,0,0.18);
            border: 1px solid #7c6300;
            color: #ffd700;
            padding: 14px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .pv-login-btn:hover, .pv-login-btn:active {
            background: #7c6300;
            color: #ffffff;
        }
        .pv-login-banner {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, rgba(124,99,0,0.22) 0%, rgba(35,30,10,0.65) 100%);
            border: 1px solid rgba(255,215,0,0.35);
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .pv-login-banner:hover {
            border-color: #ffd700;
            background: linear-gradient(135deg, rgba(124,99,0,0.32) 0%, rgba(35,30,10,0.85) 100%);
        }
        .pv-login-banner-icon {
            font-size: 1.4rem;
            color: #ffd700;
            flex-shrink: 0;
        }
        .pv-login-banner-info {
            flex: 1;
        }
        .pv-login-banner-title {
            color: #ffd700;
            font-size: 0.88rem;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .pv-login-banner-sub {
            color: #aaa;
            font-size: 0.76rem;
            line-height: 1.35;
        }
        .pv-login-banner-btn {
            background: #7c6300;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.15s ease;
        }
        .pv-login-banner-btn:hover {
            background: #967800;
        }
        /* Profile view root needs scroll */
        .profile-view { overflow-y: auto; }
        /* Desktop adjustments for profile view */
        @media (min-width: 769px) {
            .pv-hero { padding: 48px 110px 32px; width: 100%; box-sizing: border-box; }
            .pv-avatar { width: 104px; height: 104px; }
            .pv-name { font-size: 1.5rem; }
            .pv-sections {
                max-width: 100%;
                width: 100%;
                margin: 0;
                padding: 28px 110px 60px;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 24px;
                align-items: start;
                box-sizing: border-box;
            }
            .pv-login-banner {
                grid-column: 1 / -1;
                width: 100%;
                box-sizing: border-box;
            }
            .pv-col {
                display: flex;
                flex-direction: column;
                gap: 20px;
                width: 100%;
                min-width: 0;
                box-sizing: border-box;
            }
            .pv-section-card,
            .pv-valid-id-card,
            .pv-auth-wrap {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
                min-width: 0;
            }
            .pv-row {
                width: 100%;
                box-sizing: border-box;
            }
            .pv-row:hover { background: rgba(255,255,255,0.04); }
            .pv-row-value { max-width: 65%; }
        }
        @media (min-width: 769px) and (max-width: 1100px) {
            .pv-hero { padding-left: 60px; padding-right: 60px; }
            .pv-sections { padding-left: 60px; padding-right: 60px; gap: 18px; }
        }

        /* ===== Valid ID Requirements Styles ===== */
        .pv-valid-id-card {
            background: #2a2a2a;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
            box-shadow: none;
        }
        .pv-valid-id-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 16px 9px;
            border-bottom: 1px solid #333;
            background: transparent;
            flex-wrap: nowrap;
            gap: 8px;
        }
        .pv-valid-id-title {
            color: #ffd700;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1.1px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-shrink: 1;
            min-width: 0;
        }
        .pv-valid-id-title i {
            flex-shrink: 0;
        }
        .pv-id-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            white-space: nowrap;
        }
        .pv-id-status-badge {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .pv-id-status-badge.verified {
            background: rgba(34, 197, 94, 0.18);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.4);
        }
        .pv-id-status-badge.submitted {
            background: rgba(234, 179, 8, 0.18);
            color: #facc15;
            border: 1px solid rgba(234, 179, 8, 0.4);
        }
        .pv-id-status-badge.pending {
            background: rgba(245, 158, 11, 0.18);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.45);
        }
        .pv-id-status-badge.rejected {
            background: rgba(239, 68, 68, 0.22);
            color: #ff6b6b;
            border: 1px solid rgba(239, 68, 68, 0.55);
        }
        .pv-id-status-badge.unverified {
            background: rgba(239, 68, 68, 0.18);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }
        .pv-id-rejected-notice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.8rem;
            color: #ffe4e6;
            line-height: 1.45;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.22), rgba(185, 28, 28, 0.28));
            border: 1.5px solid #ef4444;
            padding: 12px 14px;
            border-radius: 9px;
            margin-bottom: 8px;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.2);
            animation: pvPulseReject 2.5s infinite ease-in-out;
        }
        .pv-id-rejected-notice i {
            color: #ff4d4d;
            font-size: 1.15rem;
            margin-top: 2px;
            flex-shrink: 0;
        }
        .pv-id-rejected-notice strong {
            color: #ffffff;
            font-weight: 700;
        }
        @keyframes pvPulseReject {
            0%, 100% { border-color: rgba(239, 68, 68, 0.6); }
            50% { border-color: #ef4444; }
        }
        .pv-valid-id-card.uploaded .pv-valid-id-header {
            border-bottom: none;
            padding: 13px 16px;
        }
        .pv-id-view-upload-btn {
            background: none;
            color: #ffd700;
            border: none;
            padding: 0;
            margin: 0;
            border-radius: 0;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            cursor: pointer;
            text-transform: uppercase;
            transition: color 0.15s ease, opacity 0.15s ease;
            box-shadow: none;
            white-space: nowrap;
            flex-shrink: 0;
            outline: none;
            display: inline-block;
        }
        .pv-id-view-upload-btn:hover {
            background: none;
            color: #ffffff;
            box-shadow: none;
            transform: none;
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .pv-valid-id-header {
                padding: 11px 14px 9px;
                gap: 6px;
            }
            .pv-valid-id-title {
                font-size: 0.68rem;
                letter-spacing: 0.6px;
            }
            .pv-id-view-upload-btn {
                background: none;
                border: none;
                box-shadow: none;
                padding: 0;
                font-size: 0.72rem;
            }
            .pv-id-status-badge {
                font-size: 0.6rem;
                padding: 2px 6px;
            }
        }
        .pv-valid-id-body {
            padding: 14px 16px 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .pv-id-instruction {
            font-size: 0.76rem;
            color: #aaa;
            line-height: 1.4;
            margin: 0;
        }
        .pv-id-select-wrap {
            position: relative;
        }
        .pv-id-select-label {
            display: block;
            font-size: 0.73rem;
            color: #ffd700;
            font-weight: 600;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }
        .pv-id-select-custom {
            width: 100%;
            background: #1c1c1c;
            color: #fff;
            border: 1px solid #484848;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            outline: none;
            cursor: pointer;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23ffd700' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 11px;
            padding-right: 36px;
        }
        .pv-id-select-custom:focus {
            border-color: #ffd700;
            box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.22);
        }
        .pv-id-select-custom option {
            background: #1e1e1e;
            color: #fff;
            padding: 8px;
        }
        .pv-id-upload-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-top: 2px;
        }
        .pv-id-box {
            background: #181818;
            border-radius: 10px;
            border: 1px dashed #555;
            overflow: hidden;
            position: relative;
            min-height: 170px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.22s ease;
            cursor: pointer;
        }
        .pv-id-box:hover {
            border-color: #ffd700;
            background: rgba(124, 99, 0, 0.08);
        }
        .pv-id-box.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        .pv-id-box.disabled:hover {
            border-color: #555;
            background: #181818;
        }
        .pv-id-box.has-image {
            border-style: solid;
            border-color: #7c6300;
            cursor: default;
        }
        .pv-id-placeholder {
            padding: 16px 12px;
            text-align: center;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-sizing: border-box;
        }
        .pv-id-guide-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin-bottom: 4px;
        }
        .pv-id-guide-img {
            max-height: 110px;
            max-width: 200px;
            width: auto;
            object-fit: contain;
            filter: grayscale(100%);
            opacity: 0.6;
            transition: all 0.25s ease;
            border-radius: 6px;
            pointer-events: none;
        }
        .pv-id-box:hover .pv-id-guide-img {
            filter: grayscale(100%);
            opacity: 0.85;
            transform: scale(1.03);
        }
        .pv-id-box.disabled .pv-id-guide-img {
            opacity: 0.3;
        }
        .pv-id-placeholder span {
            font-size: 0.78rem;
            font-weight: 600;
            color: #eee;
        }
        .pv-id-placeholder small {
            font-size: 0.68rem;
            color: #888;
        }
        .pv-id-box.disabled .pv-id-placeholder small {
            color: #666;
        }
        .pv-id-preview-wrap {
            width: 100%;
            height: 190px;
            position: relative;
            background: #000;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pv-id-preview-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.28s ease;
        }
        .pv-id-box:hover .pv-id-preview-img {
            transform: scale(1.05);
        }
        .pv-id-preview-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .pv-id-box:hover .pv-id-preview-overlay {
            opacity: 1;
        }
        .btn-id-action {
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #fff;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-id-action:hover {
            background: #ffd700;
            color: #000;
            border-color: #ffd700;
            transform: scale(1.1);
        }
        .btn-id-action.remove:hover {
            background: #ef4444;
            color: #fff;
            border-color: #ef4444;
        }
        .pv-id-side-tag {
            position: absolute;
            top: 6px;
            left: 6px;
            background: rgba(0, 0, 0, 0.8);
            color: #ffd700;
            font-size: 0.62rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            letter-spacing: 0.4px;
            border: 1px solid rgba(255, 215, 0, 0.35);
            z-index: 2;
        }
        .btn-save-valid-id {
            width: 100%;
            background: linear-gradient(135deg, #7c6300 0%, #a68400 100%);
            border: 1px solid #ffd700;
            color: #ffffff;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 3px 10px rgba(0,0,0,0.3);
            margin-top: 4px;
        }
        .btn-save-valid-id:hover {
            background: linear-gradient(135deg, #a68400 0%, #ffd700 100%);
            color: #111;
            box-shadow: 0 4px 14px rgba(255, 215, 0, 0.25);
            transform: translateY(-1px);
        }
        .btn-save-valid-id:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
        }

        /* ===== Saved / Uploaded Valid ID View Styles ===== */
        .pv-id-saved-banner {
            background: #1e1e1e;
            border: 1px solid #3d3520;
            padding: 12px 14px;
            border-radius: 9px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .pv-id-saved-type-badge {
            color: #ffd700;
            font-size: 0.82rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.3px;
        }
        .pv-id-saved-note {
            margin: 0;
            font-size: 0.74rem;
            color: #aaa;
            display: flex;
            align-items: center;
            gap: 6px;
            line-height: 1.4;
        }
        .pv-id-saved-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .pv-id-thumb-box {
            position: relative;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            background: #111;
            border: 1px solid #444;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.22s ease;
        }
        .pv-id-thumb-box:hover {
            border-color: #ffd700;
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.15);
        }
        .pv-id-thumb-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.28s ease;
        }
        .pv-id-thumb-box:hover img {
            transform: scale(1.08);
        }
        .pv-id-thumb-tag {
            position: absolute;
            top: 5px;
            left: 5px;
            background: rgba(0,0,0,0.82);
            color: #ffd700;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 3px;
            border: 1px solid rgba(255,215,0,0.3);
            z-index: 2;
            letter-spacing: 0.4px;
        }
        .pv-id-thumb-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            color: #fff;
            font-size: 0.72rem;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .pv-id-thumb-box:hover .pv-id-thumb-overlay {
            opacity: 1;
        }
        .btn-view-id-full {
            width: 100%;
            background: linear-gradient(135deg, #7c6300 0%, #a68400 100%);
            border: 1px solid #ffd700;
            color: #ffffff;
            padding: 11px 16px;
            border-radius: 8px;
            font-size: 0.83rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 3px 10px rgba(0,0,0,0.35);
        }
        .btn-view-id-full:hover {
            background: linear-gradient(135deg, #a68400 0%, #ffd700 100%);
            color: #111;
            box-shadow: 0 4px 16px rgba(255, 215, 0, 0.3);
            transform: translateY(-1px);
        }

        /* ===== Valid ID Full Preview Panel (Mobile Bottom Sheet & Desktop Modal) ===== */
        .id-lightbox-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            z-index: 10000;
            display: none;
            align-items: flex-end;
            justify-content: center;
            animation: pvFadeIn 0.25s ease forwards;
        }
        .id-lightbox-modal.active {
            display: flex;
        }
        .id-lightbox-content {
            background: #222222;
            border: 1px solid #3a3a3a;
            border-bottom: none;
            border-radius: 20px 20px 0 0;
            width: 100%;
            max-width: 500px;
            max-height: 85vh;
            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.7);
            animation: pvSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            position: relative;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .id-lightbox-handle {
            width: 36px;
            height: 4px;
            background: #555;
            border-radius: 3px;
            margin: 12px auto 0 auto;
            display: block;
        }
        .id-lightbox-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px 12px;
            margin-bottom: 0;
            border-bottom: 1px solid #333;
            background: transparent;
        }
        .id-lightbox-header #idLightboxTitle {
            margin: 0;
            color: #ffd700;
            font-size: 1.05rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .id-lightbox-close {
            cursor: pointer;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #888;
            font-size: 1.2rem;
            border-radius: 50%;
            border: none;
            background: transparent;
            transition: all 0.2s ease;
            line-height: 1;
            padding: 0;
        }
        .id-lightbox-close:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }
        .id-lightbox-body-scroll {
            padding: 16px 20px 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
            box-sizing: border-box;
        }
        .id-lightbox-meta {
            background: #181818;
            border: 1px solid #3a3a3a;
            padding: 10px 14px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .id-lightbox-dual-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }
        .id-lightbox-side-card {
            background: #181818;
            border: 1px solid #3a3a3a;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .id-lightbox-side-label {
            background: #1f1f1f;
            color: #ffd700;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 8px 12px;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #333;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .id-lightbox-img-box {
            padding: 10px;
            min-height: 150px;
            max-height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0d0d0d;
        }
        .id-lightbox-img-box img {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
            border-radius: 6px;
            border: 1px solid #282828;
            box-shadow: 0 4px 14px rgba(0,0,0,0.5);
        }
        @media (min-width: 769px) {
            .id-lightbox-modal {
                align-items: center;
                padding: 20px;
            }
            .id-lightbox-content {
                border-radius: 20px;
                border-bottom: 1px solid #3a3a3a;
                max-width: 650px;
                animation: pvPopIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                max-height: 90vh;
            }
            .id-lightbox-handle {
                display: none;
            }
            .id-lightbox-dual-grid {
                grid-template-columns: 1fr 1fr;
                gap: 14px;
            }
        }

        /* ===== Bottom Panel / Overlay Styles ===== */
        .bottom-panel-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            z-index: 10000;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            animation: pvFadeIn 0.25s ease forwards;
        }
        .bottom-panel {
            background: #222222;
            border: 1px solid #3a3a3a;
            border-bottom: none;
            border-radius: 20px 20px 0 0;
            width: 100%;
            max-width: 500px;
            padding: 24px 20px 28px;
            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.7);
            animation: pvSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            position: relative;
            box-sizing: border-box;
        }
        @media (min-width: 769px) {
            .bottom-panel-overlay {
                align-items: center;
            }
            .bottom-panel {
                border-radius: 20px;
                border-bottom: 1px solid #3a3a3a;
                animation: pvPopIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                max-height: 90vh;
                overflow-y: auto;
            }
        }
        .bottom-panel-header {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-bottom: 16px;
            padding-bottom: 0;
            border-bottom: none !important;
            border: none !important;
        }
        .bottom-panel-header::after,
        .bottom-panel-header::before,
        .bottom-panel-header h3::after,
        .bottom-panel-header h3::before {
            display: none !important;
            content: none !important;
            width: 0 !important;
            height: 0 !important;
            background: none !important;
            border: none !important;
        }
        .bottom-panel-header h3 {
            margin: 0;
            color: #ffd700;
            font-size: 1.05rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            border: none !important;
            outline: none !important;
        }
        .panel-close-icon {
            cursor: pointer;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #888;
            font-size: 1.1rem;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        .panel-close-icon:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }
        .bottom-panel-input {
            width: 100%;
            padding: 13px 16px;
            background: #181818;
            border: 1px solid #444;
            border-radius: 10px;
            color: #ffffff;
            font-size: 0.95rem;
            box-sizing: border-box;
            margin-bottom: 18px;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .bottom-panel-input:focus {
            border-color: #ffd700;
            box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.2);
        }
        select.bottom-panel-input option {
            background: #222;
            color: #fff;
        }
        .bottom-panel-buttons {
            display: flex;
            gap: 10px;
        }
        .bottom-panel-button {
            flex: 1;
            padding: 13px;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .bottom-panel-button.save {
            background: #7c6300;
            color: #ffffff;
        }
        .bottom-panel-button.save:hover {
            background: #967800;
            transform: translateY(-1px);
        }
        /* ===== Valid ID Requirements Section Styles ===== */
        .pv-valid-id-card {
            background: #2a2a2a;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
            box-shadow: none;
        }
        .pv-valid-id-card:hover {
            border-color: #333;
        }
        .pv-id-status-badge {
            font-size: 0.62rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .pv-id-status-badge.verified {
            background: rgba(76, 175, 80, 0.18);
            color: #81c784;
            border: 1px solid rgba(76, 175, 80, 0.4);
        }
        .pv-id-status-badge.uploaded {
            background: rgba(255, 215, 0, 0.15);
            color: #ffd700;
            border: 1px solid rgba(255, 215, 0, 0.35);
        }
        .pv-id-status-badge.pending {
            background: rgba(255, 255, 255, 0.08);
            color: #aaa;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .pv-valid-id-body {
            padding: 14px 16px 16px;
        }
        .pv-id-warning-notice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.78rem;
            color: #ffecd1;
            line-height: 1.45;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.12), rgba(245, 158, 11, 0.14));
            border: 1.5px solid rgba(245, 158, 11, 0.5);
            padding: 11px 14px;
            border-radius: 9px;
            margin-bottom: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        }
        .pv-id-warning-notice i {
            color: #f59e0b;
            font-size: 1.05rem;
            margin-top: 1px;
            flex-shrink: 0;
        }
        .pv-id-warning-notice strong {
            color: #ffd700;
            font-weight: 700;
        }
        .pv-id-warning-notice b {
            color: #ff4d4d;
            font-weight: 700;
        }
        .pv-id-sub-remind {
            font-size: 0.72rem;
            color: #aaa;
            margin-top: 9px;
            line-height: 1.4;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .pv-id-sub-remind i {
            color: #ffd700;
            font-size: 0.75rem;
        }
        .pv-id-instruction {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 0.75rem;
            color: #ccc;
            line-height: 1.45;
            background: rgba(255, 215, 0, 0.06);
            border: 1px solid rgba(255, 215, 0, 0.18);
            padding: 9px 12px;
            border-radius: 8px;
            margin-bottom: 14px;
        }
        .pv-id-instruction i {
            color: #ffd700;
            font-size: 0.85rem;
            margin-top: 1px;
            flex-shrink: 0;
        }
        .pv-id-select-wrap {
            margin-bottom: 14px;
        }
        .pv-id-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #e0e0e0;
            margin-bottom: 6px;
        }
        .pv-id-select-custom {
            position: relative;
            width: 100%;
        }
        .pv-id-select {
            width: 100%;
            background: #181818;
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.82rem;
            padding: 10px 36px 10px 12px;
            outline: none;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        .pv-id-select:focus {
            border-color: #ffd700;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.15);
            background: #202020;
        }
        .pv-id-select option {
            background: #1f1f1f;
            color: #fff;
            padding: 8px;
        }
        .pv-select-arrow {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #ffd700;
            font-size: 0.75rem;
            pointer-events: none;
        }
        .pv-id-upload-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }
        @media (max-width: 580px) {
            .pv-id-upload-grid {
                grid-template-columns: 1fr;
            }
        }
        .pv-id-box {
            background: #1c1c1c;
            border: 1px solid #383838;
            border-radius: 10px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .pv-id-box:hover {
            border-color: rgba(255, 215, 0, 0.4);
            background: #222;
        }
        .pv-id-box.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        .pv-id-box.disabled:hover {
            border-color: #383838;
            background: #1c1c1c;
        }
        .pv-id-box-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .pv-id-box-title {
            font-size: 0.74rem;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .pv-id-box-title i {
            color: #ffd700;
            font-size: 0.8rem;
        }
        .pv-id-badge-req {
            font-size: 0.6rem;
            color: #ffd700;
            background: rgba(255, 215, 0, 0.12);
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 600;
        }
        .pv-id-dropzone {
            border: 2px dashed rgba(255, 215, 0, 0.35);
            border-radius: 8px;
            height: 120px;
            position: relative;
            cursor: pointer;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.25);
            transition: all 0.2s ease;
        }
        .pv-id-dropzone:hover {
            border-color: #ffd700;
            background: rgba(255, 215, 0, 0.05);
        }
        .pv-id-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 10px;
            text-align: center;
        }
        .pv-id-icon-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 215, 0, 0.12);
            color: #ffd700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            margin-bottom: 2px;
        }
        .pv-id-drop-text {
            font-size: 0.75rem;
            font-weight: 600;
            color: #ffffff;
        }
        .pv-id-drop-sub {
            font-size: 0.62rem;
            color: #888;
        }
        .pv-id-preview-wrap {
            width: 100%;
            height: 100%;
            position: relative;
        }
        .pv-id-img-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: 6px;
            cursor: pointer;
        }
        .pv-id-preview-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.2s ease;
            border-radius: 6px;
        }
        .pv-id-dropzone:hover .pv-id-preview-overlay,
        .pv-id-preview-wrap:focus-within .pv-id-preview-overlay {
            opacity: 1;
        }
        .btn-id-action {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.78rem;
            transition: transform 0.15s ease, background 0.15s ease;
        }
        .btn-id-action:hover {
            transform: scale(1.1);
        }
        .btn-id-view {
            background: #2196f3;
            color: #fff;
        }
        .btn-id-change {
            background: #ffd700;
            color: #111;
        }
        .btn-id-remove {
            background: #f44336;
            color: #fff;
        }
        .pv-id-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-save-valid-id {
            width: 100%;
            background: linear-gradient(135deg, #7c6300, #c9aa2e);
            color: #000;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(124, 99, 0, 0.35);
            transition: all 0.2s ease;
        }
        .btn-save-valid-id:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(255, 215, 0, 0.35);
        }
        .btn-save-valid-id:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }



        @keyframes pvFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes pvFadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        @keyframes pvSlideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }
        /* ── Top Custom Notification / Confirmation Card (Matches index.php Clear Chat Modal) ── */
        .clear-chat-confirm-modal {
            position: fixed;
            top: 20px;
            right: 20px;
            left: auto;
            z-index: 100005;
            pointer-events: none;
            opacity: 0;
            transform: translate(50px, -50px) scale(0.9);
            transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;
        }

        .clear-chat-confirm-modal.show {
            opacity: 1;
            pointer-events: auto;
            transform: translate(0, 0) scale(1);
        }

        @media (max-width: 768px) {
            .clear-chat-confirm-modal {
                top: 20px;
                left: 50%;
                right: auto;
                transform: translate(-50%, -50px) scale(0.9);
                width: calc(100% - 32px);
                max-width: 360px;
                display: flex;
                justify-content: center;
            }

            .clear-chat-confirm-modal.show {
                transform: translate(-50%, 0) scale(1);
            }
        }

        .clear-chat-confirm-card {
            background: linear-gradient(145deg, #242424, #161616);
            border: 1.5px solid rgba(255, 215, 0, 0.45);
            border-radius: 14px;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85), 0 0 20px rgba(255, 215, 0, 0.15);
            padding: 14px 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            width: 100%;
            max-width: 350px;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            color: #fff;
            box-sizing: border-box;
        }

        .clear-chat-confirm-card.success-card {
            border-color: rgba(34, 197, 94, 0.55);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85), 0 0 20px rgba(34, 197, 94, 0.25);
        }

        .clear-chat-confirm-card.info-card {
            border-color: rgba(59, 130, 246, 0.55);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85), 0 0 20px rgba(59, 130, 246, 0.25);
        }

        .clear-chat-confirm-card.error-card {
            border-color: rgba(239, 68, 68, 0.55);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85), 0 0 20px rgba(239, 68, 68, 0.25);
        }

        .clear-chat-confirm-card.warning-card {
            border-color: rgba(245, 158, 11, 0.55);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85), 0 0 20px rgba(245, 158, 11, 0.25);
        }

        .clear-chat-confirm-icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 215, 0, 0.15);
            border: 1px solid rgba(255, 215, 0, 0.4);
            color: #ffd700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .clear-chat-confirm-card.success-card .clear-chat-confirm-icon {
            background: rgba(34, 197, 94, 0.18);
            border-color: rgba(34, 197, 94, 0.5);
            color: #22c55e;
        }

        .clear-chat-confirm-card.info-card .clear-chat-confirm-icon {
            background: rgba(59, 130, 246, 0.18);
            border-color: rgba(59, 130, 246, 0.5);
            color: #3b82f6;
        }

        .clear-chat-confirm-card.error-card .clear-chat-confirm-icon {
            background: rgba(239, 68, 68, 0.18);
            border-color: rgba(239, 68, 68, 0.5);
            color: #ef4444;
        }

        .clear-chat-confirm-card.warning-card .clear-chat-confirm-icon {
            background: rgba(245, 158, 11, 0.18);
            border-color: rgba(245, 158, 11, 0.5);
            color: #f59e0b;
        }

        .clear-chat-confirm-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
        }

        .clear-chat-confirm-title {
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.3;
        }

        .clear-chat-confirm-desc {
            color: #bbbbbb;
            font-size: 11.5px;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.4;
            word-break: break-word;
        }
    </style>
</head>
<body class="profile-page">
    <nav class="mobile-bottom-nav has-straight-nav" id="mobileBottomNav" aria-label="Mobile Navigation">
        <!-- Straight Background Bar -->
        <div class="mobile-nav-bg-container straight">
            <div class="mobile-nav-wing"></div>
        </div>

        <a href="index.php" class="nav-item" onclick="setActiveNav(this)">
            <img src="images/icon/home.png" alt="Home" class="nav-icon">
            <span class="nav-text">Home</span>
        </a>
        <a href="save.php" class="nav-item" onclick="setActiveNav(this)">
            <div style="position: relative; display: inline-flex; align-items: center; justify-content: center;">
                <img src="images/icon/cart.png" alt="Cart" class="nav-icon">
                <span id="cartCount" class="purchase-nav-badge cart-badge" style="display: none;">0</span>
            </div>
            <span class="nav-text">Cart</span>
        </a>
        <a href="purchase.php" class="nav-item" onclick="setActiveNav(this)">
            <div style="position: relative; display: inline-flex; align-items: center; justify-content: center;">
                <img src="images/icon/purchase.png" alt="Purchases" class="nav-icon">
                <span id="purchaseBadge" class="purchase-nav-badge" style="display: none;">0</span>
            </div>
            <span class="nav-text">Purchases</span>
        </a>
        <a href="profile.php" class="nav-item active" onclick="setActiveNav(this)">
            <img src="images/icon/profile.png" alt="Profile" class="nav-icon">
            <span class="nav-text">Profile</span>
        </a>
    </nav>

    <div class="shell">
        <!-- Desktop Top Navigation Bar (same as index.php and purchase.php) -->
        <div class="top-nav">
            <!-- Logo Section (Desktop Only) -->
            <div class="logo-section">
                <div class="logo-text-container">
                    <div class="top-texts">
                        <a href="#" class="download-link">
                            <div class="download-text">Download</div>
                        </a>
                        <div class="text-divider"></div>
                        <div class="follow-text">Follow us on <a href="https://www.facebook.com/ACV.alf?mibextid=ZbWKwL" target="_blank" class="social-link"><img src="images/icon/facebook.gif" alt="Facebook" class="facebook-icon"></a> & <a href="https://www.instagram.com/iam_alff?igsh=YWdjcmQ2MmJ0OXkw" target="_blank" class="social-link"><img src="images/icon/instagram.gif" alt="Instagram" class="instagram-icon"></a></div>
                    </div>
                    <a href="index.php" class="closetgo-link">
                        <img src="images/icon/JMB.png" alt="ClosetGo" class="brand-logo" onerror="this.src='images/icon/chat.png'">
                        <span>ClosetGo</span>
                    </a>
                </div>
            </div>
            
            <!-- Mobile ClosetGo Logo -->
            <a href="index.php" class="mobile-closetgo-link">
                <img src="images/icon/JMB.png" alt="ClosetGo" class="brand-logo" onerror="this.src='images/icon/chat.png'">
                <span>ClosetGo</span>
            </a>
            
            <!-- Right Side Navigation / Live Search -->
            <div class="search-filter-container">
                <div class="search-box">
                    <div style="display: flex; align-items: center; width: 100%;">
                        <img src="images/icon/search_bar.gif" alt="Search" style="width: 25px; height: 25px; margin-right: 8px; cursor: pointer; transition: transform 0.1s ease, opacity 0.1s ease;" 
                             onmouseover="this.style.transform='scale(1.02)'; this.style.opacity='0.9';"
                             onmouseout="this.style.transform='scale(1)'; this.style.opacity='1';"
                             onclick="window.location.href='index.php'">
                        <div style="color: inherit; display: flex; align-items: center; flex: 1;">
                            <input type="text" placeholder="Search products..." id="searchInput" onclick="window.location.href='index.php'" onkeydown="if(event.key==='Enter'){window.location.href='index.php?search='+encodeURIComponent(this.value);}" style="background: none; border: none; outline: none; flex: 1; font-size: 1rem; color: #ffffff; transition: opacity 0.3s ease; cursor: pointer;">
                        </div>
                    </div>
                </div>
            </div>
            <div class="auth-links" id="authLinksContainer">
                <?php if ($isLoggedIn): ?>
                    <div class="auth-user-profile">
                        <span class="auth-user-name" id="topbarUserName"><?php echo htmlspecialchars($displayName); ?></span>
                        <a href="profile.php" class="auth-user-avatar-wrap active" title="View Profile">
                            <img src="<?php echo htmlspecialchars($userProfileImage); ?>" alt="Profile" class="auth-user-avatar" id="topbarUserAvatar" onerror="this.src='images/icon/profile_logo.png'">
                        </a>
                    </div>
                <?php else: ?>
                    <a href="login_user/sign_up.php" class="auth-link">Sign Up</a>
                    <div class="auth-divider"></div>
                    <a href="login_user/login.php" class="auth-link">Login</a>
                <?php endif; ?>
            </div>
            <div class="mobile-top-icons">
                <div class="mobile-top-search" onclick="window.location.href='index.php'" title="Search">
                    <img src="images/icon/search.png" alt="Search">
                </div>
                <div class="purchase-top-container" style="position: relative; display: inline-block;" title="Purchases">
                    <img src="images/icon/purchase.png" alt="Purchases" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                         onmouseover="this.style.transform='scale(1.1)'"
                         onmouseout="this.style.transform='scale(1)'"
                         onclick="window.location.href='purchase.php'">
                    <span id="topbarPurchaseBadge" class="purchase-nav-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
                </div>
                <div class="cart-container" style="position: relative; display: inline-block; margin-right: 120px;" title="Cart">
                    <img src="images/icon/cart.png" alt="Cart" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                         onmouseover="this.style.transform='scale(1.1)'"
                         onmouseout="this.style.transform='scale(1)'"
                         onclick="window.location.href='save.php'">
                    <span id="topbarCartCount" class="cart-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
                </div>
                <div class="chat-top-container" style="position: relative; display: none;" title="Chat with Admin" onclick="window.location.href='index.php'">
                    <img src="images/icon/message.png" alt="Chat" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                         onmouseover="this.style.transform='scale(1.1)'"
                         onmouseout="this.style.transform='scale(1)'">
                </div>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="profile-container">
            <!-- Profile View (Default & Main View) -->
            <div id="profileView" class="profile-view">
                <!-- Rendered by renderProfileView() after data loads -->
                <div style="display:flex;align-items:center;justify-content:center;height:200px;">
                    <div style="color:#555;font-size:0.85rem;">Loading profile...</div>
                </div>
            </div>

            <!-- Hidden File Input for Avatar Upload -->
            <input type="file" id="profileImageInput" accept="image/*" style="display: none;" onchange="handleImageUpload(event)">
        </div>
    </div>

    <!-- Top Custom Notification / Confirmation Card (Matches index.php Clear Chat Confirmation Card) -->
    <div id="notificationToast" class="clear-chat-confirm-modal">
        <div class="clear-chat-confirm-card" id="notificationToastCard">
            <div class="clear-chat-confirm-icon" id="notificationIcon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="clear-chat-confirm-body">
                <div class="clear-chat-confirm-title" id="notificationTitle">Success</div>
                <div class="clear-chat-confirm-desc" id="notificationMessage">Your action completed successfully.</div>
            </div>
        </div>
    </div>

    <script>
        // Handle logout
        function handleLogout() {
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('username');
            localStorage.removeItem('signupEmail');
            localStorage.removeItem('userFirstName');
            localStorage.removeItem('userMiddleName');
            localStorage.removeItem('userLastName');
            localStorage.removeItem('userSuffix');
            localStorage.removeItem('userProvince');
            localStorage.removeItem('userCity');
            localStorage.removeItem('userBarangay');
            localStorage.removeItem('userPhone');
            localStorage.removeItem('userEmail');
            localStorage.removeItem('userIdType');
            localStorage.removeItem('userIdFrontImage');
            localStorage.removeItem('userIdBackImage');
            localStorage.removeItem('userIdVerified');
            
            // Show logout notification
            showNotification('Logged out!', 2000);
            
            // Update profile view to show Login button
            renderProfileView();
            
            // Auto redirect to login page after 1.5 seconds
            setTimeout(() => {
                window.location.href = 'login_user/login.php';
            }, 1500);
        }
        
        // Mobile bottom navigation functionality
        function setActiveNav(element) {
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });

            element.classList.add('active');

            const href = element.getAttribute('href');
            if (href && href.startsWith('#') && href !== '#home') {
                event.preventDefault();
                const targetId = href.substring(1);
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    const offset = 80;
                    const elementPosition = targetElement.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - offset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            }
        }
        
        // Server user data injected directly by PHP for immediate rendering without page refresh
        window.serverUserData = <?php echo json_encode($currentUserData); ?>;

        // Helper to check if current user is actively logged in
        function isUserLoggedIn() {
            return (window.serverUserData && !!window.serverUserData.email) ||
                   (localStorage.getItem('isLoggedIn') === 'true' && !!(localStorage.getItem('username') || localStorage.getItem('userEmail')));
        }

        // Enforce login for any profile edit/add action
        function checkLoginOrRedirect(actionName = 'add or update profile details') {
            if (!isUserLoggedIn()) {
                showNotification('Login required to ' + actionName, 2500);
                setTimeout(() => {
                    window.location.href = 'login_user/login.php';
                }, 1500);
                return false;
            }
            return true;
        }

        // Pending ID upload state
        window.pendingIdFrontFile = null;
        window.pendingIdFrontData = null;
        window.pendingIdBackFile = null;
        window.pendingIdBackData = null;
        window.pendingIdFrontRemoved = false;
        window.pendingIdBackRemoved = false;
        window.initialIdType = '';
        window.profileSavedValues = {};

        function initProfileSavedValues() {
            window.profileSavedValues['firstName']  = localStorage.getItem('userFirstName') || (window.serverUserData && window.serverUserData.first_name) || '';
            window.profileSavedValues['middleName'] = localStorage.getItem('userMiddleName') || (window.serverUserData && window.serverUserData.middle_name) || '';
            window.profileSavedValues['lastName']   = localStorage.getItem('userLastName') || (window.serverUserData && window.serverUserData.last_name) || '';
            window.profileSavedValues['suffix']     = localStorage.getItem('userSuffix') || (window.serverUserData && window.serverUserData.suffix) || '';
            window.profileSavedValues['phone']      = localStorage.getItem('userPhone') || (window.serverUserData && window.serverUserData.phone) || '';
            window.profileSavedValues['province']   = localStorage.getItem('userProvince') || (window.serverUserData && window.serverUserData.province) || '';
            window.profileSavedValues['city']       = localStorage.getItem('userCity') || (window.serverUserData && window.serverUserData.city) || '';
            window.profileSavedValues['barangay']   = localStorage.getItem('userBarangay') || (window.serverUserData && window.serverUserData.barangay) || '';
        }

        // Initialize profile on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (window.serverUserData) {
                localStorage.setItem('userFirstName', window.serverUserData.first_name || '');
                localStorage.setItem('userMiddleName', window.serverUserData.middle_name || '');
                localStorage.setItem('userLastName', window.serverUserData.last_name || '');
                localStorage.setItem('userSuffix', window.serverUserData.suffix || '');
                localStorage.setItem('userProvince', window.serverUserData.province || '');
                localStorage.setItem('userCity', window.serverUserData.city || '');
                localStorage.setItem('userBarangay', window.serverUserData.barangay || '');
                localStorage.setItem('userPhone', window.serverUserData.phone || '');
                localStorage.setItem('userEmail', window.serverUserData.email || '');
                localStorage.setItem('username', window.serverUserData.email || '');
                localStorage.setItem('isLoggedIn', 'true');
                if (window.serverUserData.profile_image) {
                    localStorage.setItem('profileImage', window.serverUserData.profile_image);
                }
                if (window.serverUserData.id_type) {
                    localStorage.setItem('userIdType', window.serverUserData.id_type);
                }
                if (window.serverUserData.id_front_image) {
                    localStorage.setItem('userIdFrontImage', window.serverUserData.id_front_image);
                }
                if (window.serverUserData.id_back_image) {
                    localStorage.setItem('userIdBackImage', window.serverUserData.id_back_image);
                }
                localStorage.setItem('userIdVerified', String(window.serverUserData.id_verified || 0));
                if (String(window.serverUserData.id_verified) === '2') {
                    setTimeout(() => {
                        if (typeof showNotification === 'function') {
                            showNotification('Your uploaded ID was rejected by the admin. Please upload a new valid ID in your Profile.', 'warning', 5000);
                        }
                    }, 500);
                }
            } else {
                // If not authenticated on server, clean up stale login flag
                if (!localStorage.getItem('username') && !localStorage.getItem('userEmail')) {
                    localStorage.removeItem('isLoggedIn');
                }
            }
            window.initialIdType = localStorage.getItem('userIdType') || (window.serverUserData && window.serverUserData.id_type) || '';
            initProfileSavedValues();
            // Render profile view immediately with full database data
            renderProfileView();
            // Then fetch fresh data from server in background if logged in
            if (isUserLoggedIn()) {
                loadProfileData();
            }
            // Initialize Free Location API
            FreeLocationAPI.initialize();

            // Auto-scroll and highlight Valid ID Requirements section if requested or rejected
            const isRejectedOnLoad = (window.serverUserData && String(window.serverUserData.id_verified) === '2') || localStorage.getItem('userIdVerified') === '2';
            if (isRejectedOnLoad || window.location.search.includes('upload_id=true') || window.location.hash === '#validIdRequirementsCard') {
                setTimeout(() => {
                    const idCard = document.getElementById('validIdRequirementsCard');
                    if (idCard) {
                        idCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        idCard.style.outline = isRejectedOnLoad ? '2px solid #ef4444' : '3px solid #ff4757';
                        idCard.style.boxShadow = isRejectedOnLoad ? '0 0 20px rgba(239, 68, 68, 0.4)' : '0 0 20px rgba(255, 71, 87, 0.4)';
                        idCard.style.transition = 'all 0.3s ease';
                        setTimeout(() => {
                            idCard.style.outline = '';
                            idCard.style.boxShadow = '';
                        }, 3500);
                        if (!isRejectedOnLoad && typeof showNotification === 'function') {
                            showNotification('Notice: Reservations will NOT be processed without a valid ID photo (Front with User).', 'warning', 4500);
                        }
                    }
                }, 400);
            }
            
            // Clear any lingering redirect flag so user stays on profile page
            localStorage.removeItem('redirectAfterProfile');
        });
        
        // Load profile data for display view
        function loadProfileData() {
            const userEmail = (window.serverUserData && window.serverUserData.email) 
                ? window.serverUserData.email 
                : (localStorage.getItem('username') || localStorage.getItem('signupEmail'));
            
            if (!userEmail) {
                return; // No user email found
            }
            
            // Load profile image
            const imageFormData = new FormData();
            imageFormData.append('action', 'get_profile_image');
            imageFormData.append('email', userEmail);
            
            fetch('profile.php', {
                method: 'POST',
                body: imageFormData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.profile_image) {
                    const topProfileImage = document.getElementById('topProfileImage');
                    if (topProfileImage) {
                        topProfileImage.src = data.profile_image;
                        topProfileImage.onerror = function() {
                            this.src = 'images/icon/profile_logo.png';
                        };
                    }
                    const pvAvatar = document.getElementById('pvAvatar');
                    if (pvAvatar) {
                        pvAvatar.src = data.profile_image;
                        pvAvatar.onerror = function() {
                            this.src = 'images/icon/profile_logo.png';
                        };
                    }
                }
            })
            .catch(error => {
                console.error('Error loading profile image:', error);
            });
            
            // Load user names and ID data from database
            const namesFormData = new FormData();
            namesFormData.append('action', 'get_user_names');
            namesFormData.append('email', userEmail);
            
            fetch('profile.php', {
                method: 'POST',
                body: namesFormData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const firstName = data.first_name || '';
                    const middleName = data.middle_name || '';
                    const lastName = data.last_name || '';
                    const suffix = data.suffix || '';
                    const province = data.province || '';
                    const city = data.city || '';
                    const barangay = data.barangay || '';
                    const phone = data.phone || '';
                    const profileEmail = data.email || '';
                    const idType = data.id_type || '';
                    const idFrontImage = data.id_front_image || '';
                    const idBackImage = data.id_back_image || '';
                    const idVerified = String(data.id_verified || 0);
                    
                    // Save to localStorage for consistency
                    localStorage.setItem('userFirstName', firstName);
                    localStorage.setItem('userMiddleName', middleName);
                    localStorage.setItem('userLastName', lastName);
                    localStorage.setItem('userSuffix', suffix);
                    localStorage.setItem('userProvince', province);
                    localStorage.setItem('userCity', city);
                    localStorage.setItem('userBarangay', barangay);
                    localStorage.setItem('userPhone', phone);
                    localStorage.setItem('userEmail', profileEmail);
                    if (idType) {
                        localStorage.setItem('userIdType', idType);
                        window.initialIdType = idType;
                    }
                    if (idFrontImage) localStorage.setItem('userIdFrontImage', idFrontImage);
                    if (idBackImage) localStorage.setItem('userIdBackImage', idBackImage);
                    const prevVerified = localStorage.getItem('userIdVerified');
                    localStorage.setItem('userIdVerified', idVerified);
                    if (idVerified === '2' && prevVerified !== '2') {
                        if (typeof showNotification === 'function') {
                            showNotification('Your uploaded ID was rejected by the admin. Please upload a new valid ID in your Profile.', 'warning', 5000);
                        }
                    }
                    
                    initProfileSavedValues();
                    // Refresh profile view with latest data
                    renderProfileView();
                } else {
                    console.error('Error loading user names:', data.message);
                    renderProfileView();
                }
            })
            .catch(error => {
                console.error('Error loading user names:', error);
            });
        }

        // Helper to check if any changes were made to Valid ID section
        function hasValidIdChanges() {
            if (localStorage.getItem('userIdVerified') === '1') return false;
            if (window.pendingIdFrontFile) return true;
            if (window.pendingIdFrontRemoved) return true;
            const currentIdType = (localStorage.getItem('userIdType') || '').trim();
            const initialIdType = (window.initialIdType || '').trim();
            if (currentIdType && currentIdType !== initialIdType) return true;
            return false;
        }

        function updateValidIdSaveButtonVisibility() {
            const btn = document.getElementById('btnSaveValidId');
            if (!btn) return;
            if (localStorage.getItem('userIdVerified') === '1') {
                btn.style.display = 'none';
                return;
            }
            if (hasValidIdChanges()) {
                btn.style.display = 'flex';
            } else {
                btn.style.display = 'none';
            }
        }

        function startChangingId() {
            if (localStorage.getItem('userIdVerified') === '1') {
                showNotification('Your ID has already been verified by the admin and cannot be changed.', 'warning', 3500);
                return;
            }
            window.isChangingId = true;
            renderProfileView();
            setTimeout(() => {
                const idCard = document.getElementById('validIdRequirementsCard');
                if (idCard) idCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);
        }

        function cancelChangingId() {
            window.isChangingId = false;
            window.pendingIdFrontFile = null;
            window.pendingIdFrontData = null;
            window.pendingIdFrontRemoved = false;
            renderProfileView();
        }

        // Build Valid ID Requirements HTML
        function buildValidIdCardHtml() {
            const idType = localStorage.getItem('userIdType') || '';
            const verifiedVal = String(localStorage.getItem('userIdVerified') || '0');
            const isVerified = (verifiedVal === '1');
            const isRejected = (verifiedVal === '2');
            const isPending = (verifiedVal === '0');

            let frontImg = '';
            if (!window.pendingIdFrontRemoved) {
                frontImg = window.pendingIdFrontData || localStorage.getItem('userIdFrontImage') || localStorage.getItem('userIdBackImage') || '';
            }

            // If account's ID is verified: locked compact card, CANNOT change
            if (isVerified) {
                return `
                    <div id="validIdRequirementsCard" class="pv-valid-id-card uploaded verified-locked">
                        <div class="pv-valid-id-header">
                            <div class="pv-valid-id-title">
                                <i class="fas fa-id-card-alt"></i> Valid ID Requirements
                            </div>
                            <div class="pv-id-header-actions">
                                <span class="pv-id-status-badge verified"><i class="fas fa-check-circle"></i> Verified</span>
                                <button type="button" class="pv-id-view-upload-btn" onclick="openCombinedIdModal()" title="View Uploaded ID">
                                    View Upload
                                </button>
                            </div>
                        </div>
                    </div>`;
            }

            // Check if already uploaded and saved in database/localStorage (1 photo: front with user)
            const isAlreadyUploaded = (localStorage.getItem('userIdFrontImage') || localStorage.getItem('userIdBackImage')) && !window.pendingIdFrontFile;

            // If pending and already uploaded (and user is not currently in change mode): show compact card
            if (isPending && isAlreadyUploaded && !window.isChangingId) {
                return `
                    <div id="validIdRequirementsCard" class="pv-valid-id-card uploaded">
                        <div class="pv-valid-id-header">
                            <div class="pv-valid-id-title">
                                <i class="fas fa-id-card-alt"></i> Valid ID Requirements
                            </div>
                            <div class="pv-id-header-actions">
                                <span class="pv-id-status-badge pending"><i class="fas fa-clock"></i> Pending</span>
                                <button type="button" class="pv-id-view-upload-btn" onclick="openCombinedIdModal()" title="View Uploaded ID">
                                    View Upload
                                </button>
                                <button type="button" class="pv-id-view-upload-btn" onclick="startChangingId()" title="Change ID" style="color: #ffd700; margin-left: 6px;">
                                    Change
                                </button>
                            </div>
                        </div>
                    </div>`;
            }

            // For Rejected or Fresh Upload or Changing ID: Full editable form
            let badgeHtml = '';
            if (isRejected) {
                badgeHtml = `<span class="pv-id-status-badge rejected"><i class="fas fa-times-circle"></i> Rejected</span>`;
            } else if (window.pendingIdFrontFile) {
                badgeHtml = `<span class="pv-id-status-badge submitted"><i class="fas fa-clock"></i> Photo Ready</span>`;
            } else {
                badgeHtml = `<span class="pv-id-status-badge unverified"><i class="fas fa-shield-alt"></i> Required</span>`;
            }

            // ID options
            const idOptions = [
                "Philippine Identification (PhilSys / National ID / ePhilID)",
                "Philippine Passport",
                "Driver’s License",
                "Philippine Postal ID",
                "Senior Citizen ID",
                "Unified Multi-Purpose ID (UMID)",
                "PRC ID (Professional Regulation Commission)",
                "Voter’s ID / Voter's Certificate",
                "SSS / GSIS E-Card",
                "Pag-IBIG / HDMF Loyalty Card Plus",
                "PhilHealth ID",
                "PWD ID (Persons with Disability)"
            ];

            let optionsHtml = `<option value="" ${!idType ? 'selected' : ''}>Select Valid ID </option>`;
            idOptions.forEach(opt => {
                const sel = (idType === opt) ? 'selected' : '';
                optionsHtml += `<option value="${opt}" ${sel}>${opt}</option>`;
            });

            // Single ID Box (Card front including user)
            let idBoxHtml = '';
            if (window.pendingIdFrontFile && window.pendingIdFrontData) {
                idBoxHtml = `
                    <div class="pv-id-box has-image">
                        <div class="pv-id-side-tag"><i class="fas fa-id-card"></i> NEW ID PHOTO + USER</div>
                        <div class="pv-id-preview-wrap">
                            <img src="${window.pendingIdFrontData}" alt="New ID Card with User" class="pv-id-preview-img" onclick="openCombinedIdModal()">
                            <div class="pv-id-preview-overlay">
                                <button type="button" class="btn-id-action" title="View Full Photo" onclick="event.stopPropagation(); openCombinedIdModal()"><i class="fas fa-search-plus"></i></button>
                                <button type="button" class="btn-id-action" title="Change Photo" onclick="event.stopPropagation(); triggerIdUpload('front')"><i class="fas fa-pen"></i></button>
                                <button type="button" class="btn-id-action remove" title="Remove Photo" onclick="event.stopPropagation(); removeIdImage('front')"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                    </div>`;
            } else if (!isRejected && frontImg) {
                idBoxHtml = `
                    <div class="pv-id-box has-image">
                        <div class="pv-id-side-tag"><i class="fas fa-id-card"></i> CARD FRONT + USER</div>
                        <div class="pv-id-preview-wrap">
                            <img src="${frontImg}" alt="ID Card with User" class="pv-id-preview-img" onclick="openCombinedIdModal()">
                            <div class="pv-id-preview-overlay">
                                <button type="button" class="btn-id-action" title="View Full Photo" onclick="event.stopPropagation(); openCombinedIdModal()"><i class="fas fa-search-plus"></i></button>
                                <button type="button" class="btn-id-action" title="Change Photo" onclick="event.stopPropagation(); triggerIdUpload('front')"><i class="fas fa-pen"></i></button>
                                <button type="button" class="btn-id-action remove" title="Remove Photo" onclick="event.stopPropagation(); removeIdImage('front')"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                    </div>`;
            } else {
                const disabledClass = !idType ? 'disabled' : '';
                const disabledText = !idType 
                    ? '<small style="color: #ff4757;">Select ID type first</small>' 
                    : (isRejected 
                        ? '<small style="color: #ffd700; font-weight: 600;">Tap to upload a new valid ID photo</small>' 
                        : '<small>Tap to upload photo (Card Front including you)</small>');
                idBoxHtml = `
                    <div class="pv-id-box ${disabledClass}" onclick="triggerIdUpload('front')">
                        <div class="pv-id-side-tag"><i class="fas fa-id-card"></i> CARD FRONT + USER</div>
                        <div class="pv-id-placeholder">
                            <div class="pv-id-guide-wrap">
                                <img src="images/background/ID.png" alt="ID Guide" class="pv-id-guide-img">
                            </div>
                            <span>Hold ID Card in Front with Your Face</span>
                            ${disabledText}
                        </div>
                    </div>`;
            }

            let previousViewBtn = '';
            if (isRejected && (localStorage.getItem('userIdFrontImage') || localStorage.getItem('userIdBackImage'))) {
                previousViewBtn = `
                    <button type="button" class="pv-id-view-upload-btn" onclick="openCombinedIdModal()" title="View Previous ID">
                        View Previous
                    </button>`;
            }

            return `
                <div id="validIdRequirementsCard" class="pv-valid-id-card">
                    <div class="pv-valid-id-header">
                        <div class="pv-valid-id-title">
                            <i class="fas fa-id-card-alt"></i> Valid ID Requirements
                        </div>
                        <div class="pv-id-header-actions">
                            ${badgeHtml}
                            ${previousViewBtn}
                        </div>
                    </div>
                    <div class="pv-valid-id-body">
                        ${isRejected ? `
                        <div class="pv-id-rejected-notice">
                            <i class="fas fa-exclamation-circle"></i>
                            <div>
                                <strong>ID Verification Rejected:</strong> Your uploaded ID was rejected by the admin. Please upload a new valid ID in your Profile.
                            </div>
                        </div>` : `
                        <div class="pv-id-warning-notice">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <strong>Important Notice:</strong> Reservations will <b>NOT</b> be processed if your ID is invalid or unclear. Upload 1 photo showing the ID card front clearly along with your face.
                            </div>
                        </div>`}

                        <div class="pv-id-select-wrap">
                            <label class="pv-id-select-label"><i class="fas fa-list-ul"></i> Select Government ID Type</label>
                            <select id="validIdTypeSelect" class="pv-id-select-custom" onchange="handleValidIdTypeChange(this.value)">
                                ${optionsHtml}
                            </select>
                        </div>

                        <div>
                            <label class="pv-id-select-label"><i class="fas fa-id-card"></i> Upload ID Card (Front with User)</label>
                            <div class="pv-id-upload-grid">
                                ${idBoxHtml}
                            </div>
                        </div>

                        <!-- Hidden File Input -->
                        <input type="file" id="validIdFrontInput" accept="image/jpeg,image/jpg,image/png,image/webp" style="display:none;" onchange="handleIdFileSelect(event, 'front')">

                        <button type="button" class="btn-save-valid-id" id="btnSaveValidId" onclick="saveValidIdDocuments()" style="${hasValidIdChanges() ? 'display: flex;' : 'display: none;'}">
                            <i class="fas fa-cloud-upload-alt"></i> Save & Submit ID
                        </button>
                        ${(window.isChangingId && !isRejected) ? `
                        <button type="button" onclick="cancelChangingId()" style="background: none; border: 1px solid #555; color: #ccc; border-radius: 8px; padding: 8px 14px; font-size: 0.8rem; font-weight: 600; cursor: pointer; margin-top: 4px; width: 100%;">
                            <i class="fas fa-times"></i> Cancel
                        </button>` : ''}
                        <div class="pv-id-sub-remind">
                            <i class="fas fa-shield-alt"></i> Reservations are only accommodated once the ID card front and face photo are verified.
                        </div>
                    </div>
                </div>`;
        }

        function handleValidIdTypeChange(val) {
            if (!checkLoginOrRedirect('select your Government ID type')) return;
            if (localStorage.getItem('userIdVerified') === '1') {
                showNotification('Your ID has already been verified by the admin and cannot be changed.', 'warning', 3500);
                return;
            }
            localStorage.setItem('userIdType', val);
            
            // Update the disabled state of ID upload boxes
            updateIdUploadBoxesState();
            updateValidIdSaveButtonVisibility();
        }
        
        function updateIdUploadBoxesState() {
            const idType = localStorage.getItem('userIdType') || '';
            
            // Get all ID upload boxes
            const allIdBoxes = document.querySelectorAll('.pv-id-box');
            
            allIdBoxes.forEach(box => {
                // Skip boxes that already have images (has-image class)
                if (box.classList.contains('has-image')) {
                    return;
                }
                
                if (idType) {
                    box.classList.remove('disabled');
                } else {
                    box.classList.add('disabled');
                }
            });
        }

        function triggerIdUpload(side) {
            if (!checkLoginOrRedirect('upload a Government ID')) return;
            if (localStorage.getItem('userIdVerified') === '1') {
                showNotification('Your ID has already been verified by the admin and cannot be changed.', 'warning', 3500);
                return;
            }
            
            // Check if user has selected Government ID Type first
            const idType = localStorage.getItem('userIdType') || '';
            if (!idType) {
                showNotification('Please select a Government ID Type first before uploading your ID photo', 3000);
                const idTypeSelect = document.getElementById('validIdTypeSelect');
                if (idTypeSelect) {
                    idTypeSelect.focus();
                    idTypeSelect.style.borderColor = '#ff4757';
                    idTypeSelect.style.boxShadow = '0 0 10px rgba(255, 71, 87, 0.4)';
                    setTimeout(() => {
                        idTypeSelect.style.borderColor = '';
                        idTypeSelect.style.boxShadow = '';
                    }, 2000);
                }
                return;
            }
            
            const input = document.getElementById('validIdFrontInput');
            if (input) input.click();
        }

        function handleIdFileSelect(event, side) {
            if (!checkLoginOrRedirect('upload a Government ID')) return;
            if (localStorage.getItem('userIdVerified') === '1') {
                showNotification('Your ID has already been verified by the admin and cannot be changed.', 'warning', 3500);
                return;
            }
            const file = event.target.files && event.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                showNotification('Please select a valid image file (JPG, PNG, WEBP)', 3000);
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                window.pendingIdFrontFile = file;
                window.pendingIdFrontData = e.target.result;
                window.pendingIdFrontRemoved = false;
                renderProfileView();
                updateValidIdSaveButtonVisibility();
                showNotification('ID photo selected! Click "Save & Submit ID" to save.');
            };
            reader.readAsDataURL(file);
        }

        function removeIdImage(side) {
            if (!checkLoginOrRedirect('modify your Government ID')) return;
            if (localStorage.getItem('userIdVerified') === '1') {
                showNotification('Your ID has already been verified by the admin and cannot be changed.', 'warning', 3500);
                return;
            }
            window.pendingIdFrontFile = null;
            window.pendingIdFrontData = null;
            window.pendingIdFrontRemoved = true;
            window.pendingIdBackFile = null;
            window.pendingIdBackData = null;
            window.pendingIdBackRemoved = true;
            localStorage.removeItem('userIdFrontImage');
            localStorage.removeItem('userIdBackImage');
            renderProfileView();
            updateValidIdSaveButtonVisibility();
            showNotification('ID photo removed. Click "Save & Submit ID" to update.');
        }

        function openCombinedIdModal() {
            let frontImg = window.pendingIdFrontData || localStorage.getItem('userIdFrontImage') || localStorage.getItem('userIdBackImage') || '';

            const modal = document.getElementById('idLightboxModal');
            const frontImgEl = document.getElementById('idLightboxFrontImg');

            if (modal) {
                if (frontImgEl) {
                    frontImgEl.src = frontImg || 'images/icon/profile_logo.png';
                }
                modal.classList.add('active');
            }
        }

        function viewFullIdImage(side) {
            openCombinedIdModal();
        }

        function closeIdLightbox() {
            const modal = document.getElementById('idLightboxModal');
            if (modal) {
                modal.classList.remove('active');
            }
        }

        function saveValidIdDocuments() {
            if (!checkLoginOrRedirect('save your Government ID')) return;
            if (localStorage.getItem('userIdVerified') === '1') {
                showNotification('Your ID has already been verified by the admin and cannot be changed.', 'warning', 3500);
                return;
            }

            const isRejected = (localStorage.getItem('userIdVerified') === '2');
            if (isRejected && !window.pendingIdFrontFile) {
                showNotification('Your uploaded ID was rejected by the admin. Please upload a new valid ID in your Profile.', 'warning', 4500);
                return;
            }

            const userEmail = (window.serverUserData && window.serverUserData.email) 
                ? window.serverUserData.email 
                : (localStorage.getItem('userEmail') || localStorage.getItem('username') || localStorage.getItem('signupEmail'));

            if (!userEmail) {
                showNotification('Please log in to save your Valid ID requirements', 3500);
                setTimeout(() => { window.location.href = 'login_user/login.php'; }, 1500);
                return;
            }

            const selectEl = document.getElementById('validIdTypeSelect');
            const idType = (selectEl ? selectEl.value : (localStorage.getItem('userIdType') || '')).trim();

            if (!idType) {
                showNotification('Please select a valid Government ID type', 3500);
                if (selectEl) selectEl.focus();
                return;
            }

            const existingFront = localStorage.getItem('userIdFrontImage') || localStorage.getItem('userIdBackImage') || '';
            const hasFront = (!window.pendingIdFrontRemoved && (window.pendingIdFrontFile || (!isRejected && existingFront)));

            if (!hasFront) {
                showNotification('Please upload your ID card front photo including your face', 3500);
                return;
            }

            const btn = document.getElementById('btnSaveValidId');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving ID...';
            }

            const fd = new FormData();
            fd.append('action', 'save_valid_id');
            fd.append('email', userEmail);
            fd.append('id_type', idType);

            if (window.pendingIdFrontFile) {
                fd.append('id_front_image', window.pendingIdFrontFile);
            }
            if (window.pendingIdFrontRemoved) {
                fd.append('remove_front', '1');
            }

            fetch('profile.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Save & Submit ID';
                }

                if (data.success) {
                    localStorage.setItem('userIdType', data.id_type || idType);
                    window.initialIdType = data.id_type || idType;
                    if (data.id_front_image) {
                        localStorage.setItem('userIdFrontImage', data.id_front_image);
                        localStorage.setItem('userIdBackImage', data.id_front_image);
                    } else if (window.pendingIdFrontRemoved) {
                        localStorage.removeItem('userIdFrontImage');
                        localStorage.removeItem('userIdBackImage');
                    }
                    localStorage.setItem('userIdVerified', String(data.id_verified || 0));

                    // Clear pending in-memory files and flags
                    window.pendingIdFrontFile = null;
                    window.pendingIdFrontData = null;
                    window.pendingIdBackFile = null;
                    window.pendingIdBackData = null;
                    window.pendingIdFrontRemoved = false;
                    window.pendingIdBackRemoved = false;
                    window.isChangingId = false;

                    renderProfileView();
                    updateValidIdSaveButtonVisibility();
                    showNotification('Valid ID submitted and saved successfully! Pending admin verification.');
                    
                    localStorage.removeItem('redirectAfterProfile');
                } else {
                    showNotification(data.message || 'Failed to save ID. Please try again.', 4000);
                }
            })
            .catch(err => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Save & Submit ID';
                }
                console.error('Error saving ID:', err);
            });
        }

        // Render the profile view from localStorage data
        function renderProfileView() {
            const firstName  = localStorage.getItem('userFirstName')  || '';
            const middleName = localStorage.getItem('userMiddleName') || '';
            const lastName   = localStorage.getItem('userLastName')   || '';
            const suffix     = localStorage.getItem('userSuffix')     || '';
            const province   = localStorage.getItem('userProvince')   || '';
            const city       = localStorage.getItem('userCity')       || '';
            const barangay   = localStorage.getItem('userBarangay')   || '';
            const phone      = localStorage.getItem('userPhone')      || '';
            const email      = localStorage.getItem('userEmail') || localStorage.getItem('username') || localStorage.getItem('signupEmail') || '';

            let fullName = [firstName, middleName, lastName].filter(Boolean).join(' ');
            if (suffix) fullName += ' ' + suffix;

            const pv = document.getElementById('profileView');
            if (!pv) return;

            const loggedIn = isUserLoggedIn();
            const emptyLabel = loggedIn ? 'Add Now' : 'Login to Add';

            const escapeHtmlAttr = (str) => {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            };

            window.escapeHtmlAttr = escapeHtmlAttr;

            const editableRow = (field, label, value, placeholder, inputType = 'text', maxLength = '') => `
                <div class="pv-row pv-row-editable" onclick="handleRowClick('${field}', event)">
                    <span class="pv-row-label">${label}</span>
                    <div class="pv-inline-input-wrap">
                        <input type="${inputType}"
                               id="inline_${field}"
                               class="pv-inline-input"
                               value="${escapeHtmlAttr(value)}"
                               placeholder="${placeholder}"
                               ${maxLength ? `maxlength="${maxLength}"` : ''}
                               autocomplete="off"
                               onfocus="onInlineFieldFocus('${field}', this)"
                               onclick="placeCaretAtEnd(this)"
                               onmouseup="placeCaretAtEnd(this)"
                               oninput="onInlineFieldInput('${field}', this)"
                               onblur="onInlineFieldBlur('${field}', this)"
                               onkeydown="if(event.key === 'Enter') this.blur()">
                        <span class="pv-inline-status" id="status_${field}"></span>
                    </div>
                </div>`;

            const editableSelectRow = (field, label, value, placeholder, isDisabled = false) => `
                <div class="pv-row pv-row-editable pv-row-select" id="row_${field}" onclick="handleLocationRowClick('${field}', event)">
                    <span class="pv-row-label">${label}</span>
                    <div class="pv-inline-input-wrap pv-inline-select-wrap">
                        <select id="inline_${field}"
                                class="pv-inline-select"
                                ${isDisabled ? 'disabled' : ''}
                                onfocus="onInlineLocationFocus('${field}', this)"
                                onchange="onInlineLocationChange('${field}', this)">
                            <option value="" ${!value ? 'selected' : ''}>${placeholder}</option>
                            ${value ? `<option value="${escapeHtmlAttr(value)}" selected>${escapeHtmlAttr(value)}</option>` : ''}
                        </select>
                        <i class="fas fa-chevron-down pv-inline-select-chevron"></i>
                        <span class="pv-inline-status" id="status_${field}"></span>
                    </div>
                </div>`;

            const row = (label, value, onClickFn, extraIcon = '', isFixed = false) => `
                <div class="pv-row${isFixed ? ' pv-row-fixed' : ''}" ${isFixed ? 'title="Email is fixed and cannot be changed"' : `onclick="${onClickFn}"`}>
                    ${extraIcon ? `<i class="${extraIcon} pv-link-icon"></i>` : ''}
                    <span class="pv-row-label">${label}</span>
                    <span class="pv-row-value${!value ? ' pv-empty' : ''}">${value || emptyLabel}</span>
                    ${isFixed ? '<i class="fas fa-lock pv-lock-icon" title="Email is fixed and cannot be changed"></i>' : '<i class="fas fa-chevron-right pv-chevron"></i>'}
                </div>`;

            const divider = '<div class="pv-divider"></div>';

            const authButtonHtml = loggedIn
                ? `<button class="pv-logout-btn" onclick="handleLogout()">
                       <i class="fas fa-sign-out-alt"></i> Logout
                   </button>`
                : `<button class="pv-login-btn" onclick="window.location.href='login_user/login.php'">
                       <i class="fas fa-sign-in-alt"></i> Login
                   </button>`;

            const verifiedVal = String(localStorage.getItem('userIdVerified') || '0');
            const isVerified = (verifiedVal === '1');
            const isRejected = (verifiedVal === '2');

            // Check if ID is already uploaded in compact card layout
            // If rejected or changing ID, force full-height layout so upload controls and rejection notice are displayed
            const isIdUploaded = (isVerified || (verifiedVal === '0' && !!(localStorage.getItem('userIdFrontImage') || localStorage.getItem('userIdBackImage'))))
                && !window.pendingIdFrontFile && !window.isChangingId && !isRejected;

            // Build reusable HTML blocks
            const locationCardHtml = `
                <div class="pv-section-card">
                    <div class="pv-section-title"><i class="fas fa-map-marker-alt"></i> Location</div>
                    ${editableSelectRow('province', 'Province', province, 'Select Province')}
                    ${divider}
                    ${editableSelectRow('city', 'City / Town', city, province ? 'Select City / Town' : 'Select province first', !province)}
                    ${divider}
                    ${editableSelectRow('barangay', 'Barangay', barangay, city ? 'Select Barangay' : 'Select city first', !city)}
                </div>`;

            const contactCardHtml = `
                <div class="pv-section-card">
                    <div class="pv-section-title"><i class="fas fa-address-book"></i> Contact</div>
                    ${editableRow('phone', 'Contact', phone, '09XXXXXXXXX', 'tel', '11')}
                    ${divider}
                    ${row('Email', email, loggedIn ? '' : 'editEmail()', '', loggedIn)}
                </div>`;

            const quickLinksCardHtml = `
                <div class="pv-section-card">
                    <div class="pv-section-title"><i class="fas fa-th-large"></i> Quick Links</div>
                    <div class="pv-row" onclick="window.location.href='purchase.php'">
                        <i class="fas fa-shopping-bag pv-link-icon"></i>
                        <span class="pv-row-label">My Orders</span>
                        <i class="fas fa-chevron-right pv-chevron"></i>
                    </div>
                    ${divider}
                    <div class="pv-row" onclick="window.location.href='save.php'">
                        <i class="fas fa-heart pv-link-icon"></i>
                        <span class="pv-row-label">Saved Items</span>
                        <i class="fas fa-chevron-right pv-chevron"></i>
                    </div>
                </div>`;

            const authWrapHtml = `
                <div class="pv-auth-wrap">
                    ${authButtonHtml}
                </div>`;

            pv.innerHTML = `
                <div class="pv-hero">
                    <div class="pv-avatar-wrap" onclick="triggerFileUpload()" title="${loggedIn ? 'Change Profile Photo' : 'Log in to change photo'}">
                        <img src="images/icon/profile_logo.png" alt="Profile" class="pv-avatar" id="pvAvatar"
                             onerror="this.src='images/icon/profile_logo.png'">
                        <div class="pv-avatar-edit-badge"><i class="fas fa-camera"></i></div>
                    </div>
                    <h2 class="pv-name">${fullName || (loggedIn ? 'Your Name' : 'Guest Visitor')}</h2>
                    <p class="pv-email">${email || (loggedIn ? 'No email set' : 'Not logged in')}</p>
                </div>

                <div class="pv-sections">
                    ${!loggedIn ? `
                    <div class="pv-login-banner" onclick="window.location.href='login_user/login.php'">
                        <i class="fas fa-lock pv-login-banner-icon"></i>
                        <div class="pv-login-banner-info">
                            <div class="pv-login-banner-title">Sign In Required</div>
                            <div class="pv-login-banner-sub">You must be logged in to add or update your profile details. Tap here to login.</div>
                        </div>
                        <button class="pv-login-banner-btn" type="button">Log In</button>
                    </div>` : ''}

                    ${isIdUploaded ? `
                    <!-- ID IS UPLOADED: Left = Personal Info + Location | Right = Valid ID + Contact + Quick Links + Logout -->
                    <div class="pv-col pv-col-left">
                        <div class="pv-section-card">
                            <div class="pv-section-title"><i class="fas fa-user"></i> Personal Info</div>
                            ${editableRow('firstName',  'First Name',  firstName,  'Enter first name')}
                            ${divider}
                            ${editableRow('middleName', 'Middle Name', middleName, 'Enter middle name (optional)')}
                            ${divider}
                            ${editableRow('lastName',   'Last Name',   lastName,   'Enter last name')}
                            ${divider}
                            ${editableRow('suffix',      'Suffix',      suffix,     'e.g. Jr., III (optional)')}
                        </div>

                        ${locationCardHtml}
                    </div>

                    <div class="pv-col pv-col-right">
                        ${buildValidIdCardHtml()}
                        ${contactCardHtml}
                        ${quickLinksCardHtml}
                        ${authWrapHtml}
                    </div>
                    ` : `
                    <!-- ID NOT UPLOADED: Left = Personal Info + Location + Contact + Quick Links | Right = Valid ID (full height) -->
                    <div class="pv-col pv-col-left">
                        <div class="pv-section-card">
                            <div class="pv-section-title"><i class="fas fa-user"></i> Personal Info</div>
                            ${editableRow('firstName',  'First Name',  firstName,  'Enter first name')}
                            ${divider}
                            ${editableRow('middleName', 'Middle Name', middleName, 'Enter middle name (optional)')}
                            ${divider}
                            ${editableRow('lastName',   'Last Name',   lastName,   'Enter last name')}
                            ${divider}
                            ${editableRow('suffix',      'Suffix',      suffix,     'e.g. Jr., III (optional)')}
                        </div>

                        ${locationCardHtml}

                        ${contactCardHtml}
                        ${quickLinksCardHtml}
                    </div>

                    <div class="pv-col pv-col-right">
                        ${buildValidIdCardHtml()}
                        ${authWrapHtml}
                    </div>
                    `}
                </div>
            `;

            // Load fresh profile image into the hero avatar
            loadHeroProfileImage();
            
            // Update ID upload boxes state based on selected ID type
            updateIdUploadBoxesState();

            // Initialize inline location dropdowns (No modal, inline per-row selectors)
            initializeInlineLocationSelectors();
        }

        function loadHeroProfileImage() {
            const userEmail = (window.serverUserData && window.serverUserData.email) 
                ? window.serverUserData.email 
                : (localStorage.getItem('username') || localStorage.getItem('signupEmail'));
            if (!userEmail) return;
            const fd = new FormData();
            fd.append('action', 'get_profile_image');
            fd.append('email', userEmail);
            fetch('profile.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.profile_image) {
                        const pvAvatar = document.getElementById('pvAvatar');
                        if (pvAvatar) {
                            pvAvatar.src = data.profile_image;
                            pvAvatar.onerror = () => { pvAvatar.src = 'images/icon/profile_logo.png'; };
                        }
                    }
                })
                .catch(() => {});
        }

        // Play notification sound
        function playNotificationSound() {
            const audio = new Audio('ringtone/notification.MP3');
            audio.volume = 0.5; // Set volume to 50%
            audio.play().catch(error => {
                console.log('Audio play failed:', error);
            });
        }
        
        // Show notification toast with dynamic icon, color, and title based on action intent
        function showNotification(message, type = 'success', duration = 3000) {
            if (typeof type === 'number') {
                duration = type;
                type = null;
            }
            let modal = document.getElementById('notificationToast');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'notificationToast';
                modal.className = 'clear-chat-confirm-modal';
                modal.innerHTML = `
                    <div class="clear-chat-confirm-card success-card" id="notificationToastCard">
                        <div class="clear-chat-confirm-icon" id="notificationIcon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="clear-chat-confirm-body">
                            <div class="clear-chat-confirm-title" id="notificationTitle">Success</div>
                            <div class="clear-chat-confirm-desc" id="notificationMessage">Your action completed successfully.</div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }

            const card = document.getElementById('notificationToastCard');
            const titleEl = document.getElementById('notificationTitle');
            const msgEl = document.getElementById('notificationMessage');
            const iconEl = document.getElementById('notificationIcon');

            if (msgEl) msgEl.textContent = message;

            const msgLower = (message || '').toLowerCase();

            // Determine type: Error, Warning/Action-Required, Info, or Success
            let detectedType = type;
            if (!detectedType) {
                if (/fail|error|incorrect|invalid|cannot|not available|rejected/i.test(msgLower)) {
                    detectedType = 'error';
                } else if (/complete.*profile|profile.*first|profile|id photo|valid id|pending admin|verification|rejected|login required|please select|please fill|please upload|missing|required|limit reached|wait|checking|terms|policy/i.test(msgLower)) {
                    detectedType = 'warning';
                } else if (/reset|info/i.test(msgLower)) {
                    detectedType = 'info';
                } else {
                    detectedType = 'success';
                }
            }

            let cardClass = 'success-card';
            let title = 'Success';
            let iconHtml = '<i class="fas fa-check-circle"></i>';

            if (detectedType === 'error') {
                cardClass = 'error-card';
                title = 'Action Required';
                iconHtml = '<i class="fas fa-exclamation-circle"></i>';
            } else if (detectedType === 'warning') {
                cardClass = 'warning-card';
                title = 'Notice';
                iconHtml = '<i class="fas fa-exclamation-triangle"></i>';

                if (/profile/i.test(msgLower)) {
                    iconHtml = '<i class="fas fa-user-edit"></i>';
                    title = 'Profile Notice';
                } else if (/id|identification|verification/i.test(msgLower)) {
                    iconHtml = '<i class="fas fa-id-card"></i>';
                    title = 'Valid ID Required';
                } else if (/upload/i.test(msgLower)) {
                    iconHtml = '<i class="fas fa-cloud-upload-alt"></i>';
                    title = 'Upload Required';
                }
            } else if (detectedType === 'info') {
                cardClass = 'info-card';
                title = 'Notice';
                iconHtml = '<i class="fas fa-info-circle"></i>';
            } else {
                cardClass = 'success-card';
                title = 'Success';
                iconHtml = '<i class="fas fa-check-circle"></i>';
            }

            if (card) card.className = 'clear-chat-confirm-card ' + cardClass;
            if (titleEl) titleEl.textContent = title;
            if (iconEl) iconEl.innerHTML = iconHtml;

            playNotificationSound();

            modal.classList.add('show');

            clearTimeout(modal.hideTimeout);
            modal.hideTimeout = setTimeout(() => {
                modal.classList.remove('show');
            }, duration);
        }

        function saveAllNamesToDatabase() {
            const userEmail = (window.serverUserData && window.serverUserData.email) 
                ? window.serverUserData.email 
                : (localStorage.getItem('username') || localStorage.getItem('signupEmail'));
            
            if (!userEmail) {
                console.error('No user email found');
                return Promise.reject(new Error('No user email found'));
            }
            
            const firstName = localStorage.getItem('userFirstName') || '';
            const middleName = localStorage.getItem('userMiddleName') || '';
            const lastName = localStorage.getItem('userLastName') || '';
            const suffix = localStorage.getItem('userSuffix') || '';
            const province = localStorage.getItem('userProvince') || '';
            const city = localStorage.getItem('userCity') || '';
            const barangay = localStorage.getItem('userBarangay') || '';
            let phone = (localStorage.getItem('userPhone') || '').trim().replace(/\D/g, '');
            // Contact number must only be saved if it is exactly 11 digits and starts with 09
            if (phone && !/^09\d{9}$/.test(phone)) {
                phone = (window.serverUserData && /^09\d{9}$/.test(window.serverUserData.phone))
                    ? window.serverUserData.phone
                    : '';
            }
            const profileEmail = localStorage.getItem('userEmail') || userEmail;
            
            const formData = new FormData();
            formData.append('action', 'save_all_names');
            formData.append('email', userEmail);
            formData.append('firstName', firstName);
            formData.append('middleName', middleName);
            formData.append('lastName', lastName);
            formData.append('suffix', suffix);
            formData.append('province', province);
            formData.append('city', city);
            formData.append('barangay', barangay);
            formData.append('phone', phone);
            formData.append('userEmail', profileEmail);
            
            return fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('All names saved to database successfully');
                    if (window.profileSavedValues) {
                        window.profileSavedValues['firstName']  = localStorage.getItem('userFirstName') || '';
                        window.profileSavedValues['middleName'] = localStorage.getItem('userMiddleName') || '';
                        window.profileSavedValues['lastName']   = localStorage.getItem('userLastName') || '';
                        window.profileSavedValues['suffix']     = localStorage.getItem('userSuffix') || '';
                        window.profileSavedValues['phone']      = localStorage.getItem('userPhone') || '';
                        window.profileSavedValues['province']   = localStorage.getItem('userProvince') || '';
                        window.profileSavedValues['city']       = localStorage.getItem('userCity') || '';
                        window.profileSavedValues['barangay']   = localStorage.getItem('userBarangay') || '';
                    }
                    localStorage.removeItem('redirectAfterProfile');
                } else {
                    console.error('Failed to save names:', data.message);
                    if (data.message) {
                        showNotification(data.message, 3500);
                    }
                }
                return data;
            })
            .catch(error => {
                console.error('Error saving names:', error);
                throw error;
            });
        }

        // ===== Inline Field Editing & Auto-Save Handlers (No Modal) =====
        const inlineAutoSaveTimers = {};

        // Helper: Always position keyboard caret / cursor at the very end of text
        function placeCaretAtEnd(el) {
            if (!el) return;
            const apply = () => {
                try {
                    const isEmail = (el.type === 'email');
                    if (isEmail) {
                        try {
                            el.type = 'text';
                            const len = el.value ? el.value.length : 0;
                            el.setSelectionRange(len, len);
                            el.type = 'email';
                        } catch(e) {}
                    } else if (typeof el.setSelectionRange === 'function') {
                        const len = el.value ? el.value.length : 0;
                        el.setSelectionRange(len, len);
                    }
                } catch (e) {
                    try {
                        const len = el.value ? el.value.length : 0;
                        el.selectionStart = len;
                        el.selectionEnd = len;
                    } catch (err) {}
                }
            };

            apply();
            requestAnimationFrame(apply);
            setTimeout(apply, 10);
            setTimeout(apply, 40);
        }

        // Global listeners ensuring when user clicks or taps text inputs, caret is always at the last text
        document.addEventListener('click', function(e) {
            if (e.target && (e.target.classList?.contains('pv-inline-input') || e.target.classList?.contains('bottom-panel-input') || (e.target.tagName === 'INPUT' && (e.target.type === 'text' || e.target.type === 'tel' || e.target.type === 'email')))) {
                if (e.target.id !== 'searchInput') {
                    placeCaretAtEnd(e.target);
                }
            }
        }, true);
        document.addEventListener('mouseup', function(e) {
            if (e.target && (e.target.classList?.contains('pv-inline-input') || e.target.classList?.contains('bottom-panel-input') || (e.target.tagName === 'INPUT' && (e.target.type === 'text' || e.target.type === 'tel' || e.target.type === 'email')))) {
                if (e.target.id !== 'searchInput') {
                    placeCaretAtEnd(e.target);
                }
            }
        }, true);
        document.addEventListener('focusin', function(e) {
            if (e.target && (e.target.classList?.contains('pv-inline-input') || e.target.classList?.contains('bottom-panel-input') || (e.target.tagName === 'INPUT' && (e.target.type === 'text' || e.target.type === 'tel' || e.target.type === 'email')))) {
                if (e.target.id !== 'searchInput') {
                    placeCaretAtEnd(e.target);
                }
            }
        }, true);

        function handleRowClick(field, event) {
            if (!checkLoginOrRedirect('edit your profile')) return;
            const input = document.getElementById('inline_' + field);
            if (input) {
                input.focus();
                placeCaretAtEnd(input);
            }
        }

        function focusInlineField(field) {
            if (!checkLoginOrRedirect('edit your profile')) return;
            const input = document.getElementById('inline_' + field);
            if (input) {
                input.focus();
                placeCaretAtEnd(input);
            }
        }

        function onInlineFieldFocus(field, inputEl) {
            if (!checkLoginOrRedirect('edit your profile')) {
                inputEl.blur();
                return;
            }
            const row = inputEl.closest('.pv-row-editable');
            if (row) row.classList.add('is-editing');
            placeCaretAtEnd(inputEl);
        }

        function onInlineFieldInput(field, inputEl) {
            if (!checkLoginOrRedirect('edit your profile')) {
                inputEl.blur();
                return;
            }

            let val = inputEl.value;
            const statusEl = document.getElementById('status_' + field);
            const savedVal = (window.profileSavedValues && window.profileSavedValues[field] !== undefined)
                ? window.profileSavedValues[field]
                : (localStorage.getItem('user' + field.charAt(0).toUpperCase() + field.slice(1)) || '');

            // Special handling for Contact Number: must NOT save if not 11 digits or doesn't start with 09
            if (field === 'phone') {
                val = val.replace(/\D/g, '').slice(0, 11);
                inputEl.value = val;

                // Clear any pending auto-save timer for phone
                if (inlineAutoSaveTimers['phone']) {
                    clearTimeout(inlineAutoSaveTimers['phone']);
                    delete inlineAutoSaveTimers['phone'];
                }

                // If identical to saved value: DO NOT SHOW SAVE!
                if (val === savedVal) {
                    inputEl.style.borderColor = '';
                    if (statusEl) {
                        statusEl.className = 'pv-inline-status';
                        statusEl.innerHTML = '';
                    }
                    return;
                }

                // If empty: clear input and storage
                if (val === '') {
                    inputEl.style.borderColor = '';
                    localStorage.removeItem('userPhone');
                    if (window.serverUserData) window.serverUserData.phone = '';
                    if (statusEl) {
                        statusEl.className = 'pv-inline-status';
                        statusEl.innerHTML = '';
                    }
                    inlineAutoSaveTimers['phone'] = setTimeout(() => {
                        triggerInlineAutoSave('phone');
                    }, 500);
                    return;
                }

                // If user typed 1 or more digits and it does not start with '09'
                if ((val.length === 1 && val !== '0') || (val.length >= 2 && !val.startsWith('09'))) {
                    inputEl.style.borderColor = '#ef4444';
                    if (statusEl) {
                        statusEl.className = 'pv-inline-status show error';
                        statusEl.style.color = '#ef4444';
                        statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Must start with 09';
                    }
                    // DO NOT SAVE to localStorage or database!
                    return;
                }

                // If it starts with '09' but is not yet 11 digits
                if (val.length < 11) {
                    inputEl.style.borderColor = '#ffd700';
                    if (statusEl) {
                        statusEl.className = 'pv-inline-status show';
                        statusEl.style.color = '#eab308';
                        statusEl.innerHTML = val.length + '/11 digits (Must be 11)';
                    }
                    // DO NOT SAVE to localStorage or database!
                    return;
                }

                // Exactly 11 digits: verify it starts with '09'
                if (val.length === 11) {
                    if (!val.startsWith('09')) {
                        inputEl.style.borderColor = '#ef4444';
                        if (statusEl) {
                            statusEl.className = 'pv-inline-status show error';
                            statusEl.style.color = '#ef4444';
                            statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Must start with 09';
                        }
                        // DO NOT SAVE!
                        return;
                    }

                    // VALID: exactly 11 digits AND starts with 09!
                    inputEl.style.borderColor = '#4ade80';
                    localStorage.setItem('userPhone', val);
                    if (window.serverUserData) window.serverUserData.phone = val;

                    if (statusEl) {
                        statusEl.className = 'pv-inline-status show saving';
                        statusEl.style.color = '#eab308';
                        statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                    }

                    inlineAutoSaveTimers['phone'] = setTimeout(() => {
                        triggerInlineAutoSave('phone');
                    }, 500);
                    return;
                }
            }

            // For other fields: Check if value is identical to saved value!
            if (val === savedVal) {
                if (inlineAutoSaveTimers[field]) {
                    clearTimeout(inlineAutoSaveTimers[field]);
                    delete inlineAutoSaveTimers[field];
                }
                if (statusEl) {
                    statusEl.className = 'pv-inline-status';
                    statusEl.innerHTML = '';
                }
                return; // NO SAVE SHOW IF NO CHANGES USER!
            }

            // Sync other fields with localStorage immediately
            const keyMap = {
                firstName: 'userFirstName',
                middleName: 'userMiddleName',
                lastName: 'userLastName',
                suffix: 'userSuffix'
            };

            const storageKey = keyMap[field];
            if (storageKey) {
                localStorage.setItem(storageKey, val);
            }

            // Sync window.serverUserData
            if (window.serverUserData) {
                if (field === 'firstName') window.serverUserData.first_name = val;
                if (field === 'middleName') window.serverUserData.middle_name = val;
                if (field === 'lastName') window.serverUserData.last_name = val;
                if (field === 'suffix') window.serverUserData.suffix = val;
            }

            // Update live profile card header name
            updateHeroNameLive();

            // Status indicator: "Saving..." ONLY IF CHANGED
            if (statusEl) {
                statusEl.className = 'pv-inline-status show saving';
                statusEl.style.color = '#eab308';
                statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }

            // Debounced auto-save (500ms after last keystroke)
            if (inlineAutoSaveTimers[field]) {
                clearTimeout(inlineAutoSaveTimers[field]);
            }

            inlineAutoSaveTimers[field] = setTimeout(() => {
                triggerInlineAutoSave(field);
            }, 500);
        }

        function triggerInlineAutoSave(field) {
            if (inlineAutoSaveTimers[field]) {
                clearTimeout(inlineAutoSaveTimers[field]);
                delete inlineAutoSaveTimers[field];
            }

            const statusEl = document.getElementById('status_' + field);

            // Strict validation for contact number: must NOT save if not 11 digits or not starting with 09
            if (field === 'phone') {
                const phoneInput = document.getElementById('inline_phone');
                const rawVal = phoneInput ? phoneInput.value.trim() : (localStorage.getItem('userPhone') || '').trim();

                if (rawVal !== '' && !/^09\d{9}$/.test(rawVal)) {
                    if (statusEl) {
                        statusEl.className = 'pv-inline-status show error';
                        statusEl.style.color = '#ef4444';
                        if (!rawVal.startsWith('09')) {
                            statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Must start with 09 (Not saved)';
                        } else {
                            statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Must be 11 digits (Not saved)';
                        }
                        setTimeout(() => statusEl.classList.remove('show'), 3000);
                    }
                    if (phoneInput) phoneInput.style.borderColor = '#ef4444';
                    return; // DO NOT SAVE TO DATABASE!
                }
            }

            if (statusEl) {
                statusEl.className = 'pv-inline-status show saving';
                statusEl.style.color = '#eab308';
                statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }

            saveAllNamesToDatabase()
                .then(data => {
                    if (data && data.success) {
                        if (window.profileSavedValues) {
                            const inp = document.getElementById('inline_' + field);
                            if (inp) {
                                window.profileSavedValues[field] = inp.value;
                            }
                        }
                        if (statusEl) {
                            statusEl.className = 'pv-inline-status show saved';
                            statusEl.style.color = '#4ade80';
                            statusEl.innerHTML = '<i class="fas fa-check"></i> Saved';
                            setTimeout(() => {
                                if (statusEl) statusEl.classList.remove('show');
                            }, 1800);
                        }
                    } else if (data && data.message) {
                        if (statusEl) {
                            statusEl.className = 'pv-inline-status show error';
                            statusEl.style.color = '#ef4444';
                            statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                            setTimeout(() => {
                                if (statusEl) statusEl.classList.remove('show');
                            }, 3000);
                        }
                    }
                })
                .catch(() => {
                    if (statusEl) {
                        statusEl.className = 'pv-inline-status show error';
                        statusEl.style.color = '#ef4444';
                        statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Retry';
                        setTimeout(() => {
                            if (statusEl) statusEl.classList.remove('show');
                        }, 2500);
                    }
                });
        }

        function onInlineFieldBlur(field, inputEl) {
            const row = inputEl.closest('.pv-row-editable');
            if (row) row.classList.remove('is-editing');

            const statusEl = document.getElementById('status_' + field);
            const savedVal = (window.profileSavedValues && window.profileSavedValues[field] !== undefined)
                ? window.profileSavedValues[field]
                : (localStorage.getItem('user' + field.charAt(0).toUpperCase() + field.slice(1)) || '');
            const currentVal = inputEl.value;

            // Contact number on blur: if incomplete or not starting with 09, reject and do NOT save
            if (field === 'phone') {
                const rawVal = currentVal.trim();

                if (rawVal !== '' && !/^09\d{9}$/.test(rawVal)) {
                    inputEl.style.borderColor = '#ef4444';
                    if (statusEl) {
                        statusEl.className = 'pv-inline-status show error';
                        statusEl.style.color = '#ef4444';
                        if (!rawVal.startsWith('09')) {
                            statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Must start with 09 (Not saved)';
                        } else {
                            statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Must be 11 digits (Not saved)';
                        }
                    }
                    // Revert input to last valid phone stored in localStorage / profileSavedValues
                    const lastValid = (window.profileSavedValues && window.profileSavedValues['phone']) || localStorage.getItem('userPhone') || '';
                    setTimeout(() => {
                        inputEl.value = lastValid;
                        inputEl.style.borderColor = '';
                        if (statusEl) {
                            setTimeout(() => statusEl.classList.remove('show'), 1500);
                        }
                    }, 1800);
                    return; // DO NOT SAVE!
                }
                inputEl.style.borderColor = '';
            }

            // CHECK IF VALUE CHANGED FROM SAVED VALUE
            if (currentVal === savedVal) {
                if (inlineAutoSaveTimers[field]) {
                    clearTimeout(inlineAutoSaveTimers[field]);
                    delete inlineAutoSaveTimers[field];
                }
                if (statusEl) {
                    statusEl.className = 'pv-inline-status';
                    statusEl.innerHTML = '';
                }
                return; // NO SAVE SHOW IF NO CHANGES USER!
            }

            triggerInlineAutoSave(field);
        }

        function updateHeroNameLive() {
            const firstName  = localStorage.getItem('userFirstName')  || '';
            const middleName = localStorage.getItem('userMiddleName') || '';
            const lastName   = localStorage.getItem('userLastName')   || '';
            const suffix     = localStorage.getItem('userSuffix')     || '';

            let fullName = [firstName, middleName, lastName].filter(Boolean).join(' ');
            if (suffix) fullName += ' ' + suffix;

            const nameEl = document.querySelector('.pv-name');
            if (nameEl) {
                nameEl.textContent = fullName || (isUserLoggedIn() ? 'Your Name' : 'Guest Visitor');
            }
        }

        // Inline direct edit callers (Focus inline row input instead of opening modal)
        function editName() {
            if (!checkLoginOrRedirect('add your first name')) return;
            focusInlineField('firstName');
        }
        function editMiddleName() {
            if (!checkLoginOrRedirect('add your middle name')) return;
            focusInlineField('middleName');
        }
        function editLastName() {
            if (!checkLoginOrRedirect('add your last name')) return;
            focusInlineField('lastName');
        }
        function editSuffix() {
            if (!checkLoginOrRedirect('add your suffix')) return;
            focusInlineField('suffix');
        }

        // Generic close function for overlays
        function closeAnyBottomPanel() {
            const overlay = document.querySelector('.bottom-panel-overlay');
            if (overlay) {
                overlay.style.animation = 'pvFadeOut 0.2s ease forwards';
                setTimeout(() => {
                    if (overlay && overlay.parentNode) {
                        overlay.parentNode.removeChild(overlay);
                    }
                }, 200);
            }
        }

        // Free Location API
        const FreeLocationAPI = {
            provincesURL: 'https://psgc.gitlab.io/api/provinces.json',
            citiesURL: 'https://psgc.gitlab.io/api/cities.json',
            municipalitiesURL: 'https://psgc.gitlab.io/api/municipalities.json',
            barangaysURL: 'https://psgc.gitlab.io/api/barangays.json',
            
            cache: {
                regions: null,
                provinces: null,
                cities: null,
                municipalities: null,
                barangays: null
            },
            
            async loadProvinces() {
                if (this.cache.provinces) return this.cache.provinces;
                try {
                    const response = await fetch(this.provincesURL);
                    const data = await response.json();
                    this.cache.provinces = data.map(province => ({
                        code: province.code,
                        name: province.name,
                        region: province.region
                    }));
                    return this.cache.provinces;
                } catch (error) {
                    console.error('Error loading provinces:', error);
                    return [];
                }
            },
            
            async loadCities(provinceCode) {
                if (this.cache.cities && this.cache.municipalities) {
                    const cities = this.cache.cities.filter(city => city.provinceCode === provinceCode);
                    const municipalities = this.cache.municipalities.filter(mun => mun.provinceCode === provinceCode);
                    return [...cities, ...municipalities];
                }
                
                try {
                    const citiesResponse = await fetch(this.citiesURL);
                    const citiesData = await citiesResponse.json();
                    this.cache.cities = citiesData.map(city => ({
                        code: city.code,
                        name: city.name,
                        provinceCode: city.provinceCode,
                        type: 'city'
                    }));
                    
                    const municipalitiesResponse = await fetch(this.municipalitiesURL);
                    const municipalitiesData = await municipalitiesResponse.json();
                    this.cache.municipalities = municipalitiesData.map(mun => ({
                        code: mun.code,
                        name: mun.name,
                        provinceCode: mun.provinceCode,
                        type: 'municipality'
                    }));
                    
                    const cities = this.cache.cities.filter(city => city.provinceCode === provinceCode);
                    const municipalities = this.cache.municipalities.filter(mun => mun.provinceCode === provinceCode);
                    return [...cities, ...municipalities];
                } catch (error) {
                    console.error('Error loading cities:', error);
                    return [];
                }
            },
            
            async loadBarangays(cityCode) {
                if (this.cache.barangays) {
                    return this.cache.barangays.filter(b => b.cityCode === cityCode || b.municipalityCode === cityCode);
                }
                
                try {
                    const response = await fetch(this.barangaysURL);
                    const data = await response.json();
                    this.cache.barangays = data.map(barangay => ({
                        code: barangay.code,
                        name: barangay.name,
                        cityCode: barangay.cityCode || null,
                        municipalityCode: barangay.municipalityCode || null
                    }));
                    return this.cache.barangays.filter(b => b.cityCode === cityCode || b.municipalityCode === cityCode);
                } catch (error) {
                    console.error('Error loading barangays:', error);
                    return [];
                }
            },
            
            generateProvinceOptions(selectedProvince = '') {
                let options = '<option value="">Select your province</option>';
                if (this.cache.provinces) {
                    // Sort provinces alphabetically by name
                    const sortedProvinces = [...this.cache.provinces].sort((a, b) => 
                        a.name.localeCompare(b.name)
                    );
                    
                    sortedProvinces.forEach(province => {
                        const selected = province.name === selectedProvince ? 'selected' : '';
                        options += `<option value="${province.name}" ${selected}>${province.name}</option>`;
                    });
                }
                return options;
            },
            
            generateCityOptions(provinceName, selectedCity = '') {
                let options = '<option value="">Select your city/town</option>';
                if (this.cache.provinces) {
                    const province = this.cache.provinces.find(p => p.name === provinceName);
                    if (province) {
                        this.loadCities(province.code).then(citiesAndMuns => {
                            let dynOptions = '<option value="">Select your city/town</option>';
                            citiesAndMuns.forEach(cityOrMun => {
                                const selected = cityOrMun.name === selectedCity ? 'selected' : '';
                                const typeLabel = cityOrMun.type === 'city' ? 'City' : 'Town';
                                dynOptions += `<option value="${cityOrMun.name}" ${selected}>${cityOrMun.name} (${typeLabel})</option>`;
                            });
                            const citySelect = document.getElementById('citySelect');
                            if (citySelect) {
                                citySelect.innerHTML = dynOptions;
                            }
                        });
                    }
                }
                return options;
            },
            
            generateBarangayOptions(cityName, selectedBarangay = '') {
                let options = '<option value="">Select your barangay</option>';
                if (this.cache.cities && this.cache.municipalities) {
                    const city = this.cache.cities.find(c => c.name === cityName);
                    const municipality = this.cache.municipalities.find(m => m.name === cityName);
                    const cityOrMun = city || municipality;
                    
                    if (cityOrMun) {
                        this.loadBarangays(cityOrMun.code).then(barangays => {
                            let dynOptions = '<option value="">Select your barangay</option>';
                            barangays.forEach(barangay => {
                                const selected = barangay.name === selectedBarangay ? 'selected' : '';
                                dynOptions += `<option value="${barangay.name}" ${selected}>${barangay.name}</option>`;
                            });
                            const barangaySelect = document.getElementById('barangaySelect');
                            if (barangaySelect) {
                                barangaySelect.innerHTML = dynOptions;
                            }
                        });
                    }
                }
                return options;
            },
            
            async initialize() {
                try {
                    await this.loadProvinces();
                } catch (error) {
                    console.log('Location API initialized with fallback');
                }
            }
        };

        // Inline Location selection handlers (No modal, inline select per row)
        function handleLocationRowClick(field, event) {
            if (!checkLoginOrRedirect('select your location')) return;
            const selectEl = document.getElementById('inline_' + field);
            if (!selectEl) return;

            if (selectEl.disabled) {
                if (field === 'city' && !localStorage.getItem('userProvince')) {
                    showNotification('Please select your Province first', 2500);
                } else if (field === 'barangay' && !localStorage.getItem('userCity')) {
                    showNotification('Please select your City / Town first', 2500);
                }
                return;
            }

            if (event && event.target !== selectEl) {
                if (typeof selectEl.showPicker === 'function') {
                    try {
                        selectEl.showPicker();
                    } catch (err) {
                        selectEl.focus();
                    }
                } else {
                    selectEl.focus();
                }
            }
        }

        function onInlineLocationFocus(field, selectEl) {
            if (!checkLoginOrRedirect('select your location')) {
                selectEl.blur();
                return;
            }
            const row = selectEl.closest('.pv-row-editable');
            if (row) row.classList.add('is-editing');
        }

        async function onInlineLocationChange(field, selectEl) {
            if (!checkLoginOrRedirect('select your location')) {
                selectEl.blur();
                return;
            }

            const val = selectEl.value;
            const row = selectEl.closest('.pv-row-editable');
            if (row) row.classList.remove('is-editing');

            const statusEl = document.getElementById('status_' + field);
            const savedVal = (window.profileSavedValues && window.profileSavedValues[field] !== undefined)
                ? window.profileSavedValues[field]
                : (localStorage.getItem('user' + field.charAt(0).toUpperCase() + field.slice(1)) || '');

            // If value hasn't changed from saved value, don't show saving or saved!
            if (val === savedVal) {
                if (statusEl) {
                    statusEl.className = 'pv-inline-status';
                    statusEl.innerHTML = '';
                }
                return;
            }

            if (statusEl) {
                statusEl.className = 'pv-inline-status show saving';
                statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }

            const escapeAttr = window.escapeHtmlAttr || ((s) => s || '');

            if (field === 'province') {
                localStorage.setItem('userProvince', val);
                localStorage.removeItem('userCity');
                localStorage.removeItem('userBarangay');
                if (window.serverUserData) {
                    window.serverUserData.province = val;
                    window.serverUserData.city = '';
                    window.serverUserData.barangay = '';
                }

                const citySelect = document.getElementById('inline_city');
                const brgySelect = document.getElementById('inline_barangay');

                if (brgySelect) {
                    brgySelect.innerHTML = '<option value="">Select city first</option>';
                    brgySelect.disabled = true;
                }

                if (citySelect) {
                    if (val) {
                        citySelect.innerHTML = '<option value="">Loading cities...</option>';
                        citySelect.disabled = true;
                        const provinceObj = FreeLocationAPI.cache.provinces?.find(p => p.name === val);
                        if (provinceObj) {
                            const cities = await FreeLocationAPI.loadCities(provinceObj.code);
                            let cOptions = '<option value="">Select City / Town</option>';
                            const sorted = [...cities].sort((a, b) => a.name.localeCompare(b.name));
                            sorted.forEach(c => {
                                const label = c.type === 'city' ? `${c.name} (City)` : `${c.name} (Town)`;
                                cOptions += `<option value="${escapeAttr(c.name)}">${escapeAttr(label)}</option>`;
                            });
                            citySelect.innerHTML = cOptions;
                            citySelect.disabled = false;
                        } else {
                            citySelect.innerHTML = '<option value="">Select City / Town</option>';
                            citySelect.disabled = false;
                        }
                    } else {
                        citySelect.innerHTML = '<option value="">Select province first</option>';
                        citySelect.disabled = true;
                    }
                }
            } else if (field === 'city') {
                localStorage.setItem('userCity', val);
                localStorage.removeItem('userBarangay');
                if (window.serverUserData) {
                    window.serverUserData.city = val;
                    window.serverUserData.barangay = '';
                }

                const brgySelect = document.getElementById('inline_barangay');
                if (brgySelect) {
                    if (val) {
                        brgySelect.innerHTML = '<option value="">Loading barangays...</option>';
                        brgySelect.disabled = true;
                        const cityObj = FreeLocationAPI.cache.cities?.find(c => c.name === val)
                                     || FreeLocationAPI.cache.municipalities?.find(m => m.name === val);
                        if (cityObj) {
                            const brgys = await FreeLocationAPI.loadBarangays(cityObj.code);
                            let bOptions = '<option value="">Select Barangay</option>';
                            const sorted = [...brgys].sort((a, b) => a.name.localeCompare(b.name));
                            sorted.forEach(b => {
                                bOptions += `<option value="${escapeAttr(b.name)}">${escapeAttr(b.name)}</option>`;
                            });
                            brgySelect.innerHTML = bOptions;
                            brgySelect.disabled = false;
                        } else {
                            brgySelect.innerHTML = '<option value="">Select Barangay</option>';
                            brgySelect.disabled = false;
                        }
                    } else {
                        brgySelect.innerHTML = '<option value="">Select city first</option>';
                        brgySelect.disabled = true;
                    }
                }
            } else if (field === 'barangay') {
                localStorage.setItem('userBarangay', val);
                if (window.serverUserData) {
                    window.serverUserData.barangay = val;
                }
            }

            // Auto-save location to database
            saveAllNamesToDatabase()
                .then(data => {
                    if (data && data.success) {
                        if (window.profileSavedValues) {
                            window.profileSavedValues['province'] = localStorage.getItem('userProvince') || '';
                            window.profileSavedValues['city'] = localStorage.getItem('userCity') || '';
                            window.profileSavedValues['barangay'] = localStorage.getItem('userBarangay') || '';
                        }
                        if (statusEl) {
                            statusEl.className = 'pv-inline-status show saved';
                            statusEl.innerHTML = '<i class="fas fa-check"></i> Saved';
                            setTimeout(() => {
                                if (statusEl) statusEl.classList.remove('show');
                            }, 1800);
                        }
                    } else if (data && data.message) {
                        if (statusEl) {
                            statusEl.className = 'pv-inline-status show error';
                            statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                            setTimeout(() => {
                                if (statusEl) statusEl.classList.remove('show');
                            }, 3000);
                        }
                    }
                })
                .catch(() => {
                    if (statusEl) {
                        statusEl.className = 'pv-inline-status show error';
                        statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Retry';
                        setTimeout(() => {
                            if (statusEl) statusEl.classList.remove('show');
                        }, 2500);
                    }
                });
        }

        async function initializeInlineLocationSelectors() {
            const provSelect = document.getElementById('inline_province');
            const citySelect = document.getElementById('inline_city');
            const brgySelect = document.getElementById('inline_barangay');
            if (!provSelect) return;

            const currentProvince = localStorage.getItem('userProvince') || '';
            const currentCity = localStorage.getItem('userCity') || '';
            const currentBarangay = localStorage.getItem('userBarangay') || '';
            const escapeAttr = window.escapeHtmlAttr || ((s) => s || '');

            // Load provinces
            const provinces = await FreeLocationAPI.loadProvinces();
            if (provSelect && provinces && provinces.length) {
                let pOptions = '<option value="">Select Province</option>';
                const sorted = [...provinces].sort((a, b) => a.name.localeCompare(b.name));
                sorted.forEach(p => {
                    const isSel = p.name === currentProvince ? 'selected' : '';
                    pOptions += `<option value="${escapeAttr(p.name)}" ${isSel}>${escapeAttr(p.name)}</option>`;
                });
                provSelect.innerHTML = pOptions;
                if (currentProvince) provSelect.value = currentProvince;
            }

            // Load cities if province exists
            if (currentProvince && citySelect) {
                const provinceObj = FreeLocationAPI.cache.provinces?.find(p => p.name === currentProvince);
                if (provinceObj) {
                    const cities = await FreeLocationAPI.loadCities(provinceObj.code);
                    if (citySelect && cities && cities.length) {
                        let cOptions = '<option value="">Select City / Town</option>';
                        const sortedCities = [...cities].sort((a, b) => a.name.localeCompare(b.name));
                        sortedCities.forEach(c => {
                            const isSel = c.name === currentCity ? 'selected' : '';
                            const label = c.type === 'city' ? `${c.name} (City)` : `${c.name} (Town)`;
                            cOptions += `<option value="${escapeAttr(c.name)}" ${isSel}>${escapeAttr(label)}</option>`;
                        });
                        citySelect.innerHTML = cOptions;
                        citySelect.disabled = false;
                        if (currentCity) citySelect.value = currentCity;
                    }
                }
            }

            // Load barangays if city exists
            if (currentCity && brgySelect) {
                const cityObj = FreeLocationAPI.cache.cities?.find(c => c.name === currentCity) 
                             || FreeLocationAPI.cache.municipalities?.find(m => m.name === currentCity);
                if (cityObj) {
                    const barangays = await FreeLocationAPI.loadBarangays(cityObj.code);
                    if (brgySelect && barangays && barangays.length) {
                        let bOptions = '<option value="">Select Barangay</option>';
                        const sortedBrgys = [...barangays].sort((a, b) => a.name.localeCompare(b.name));
                        sortedBrgys.forEach(b => {
                            const isSel = b.name === currentBarangay ? 'selected' : '';
                            bOptions += `<option value="${escapeAttr(b.name)}" ${isSel}>${escapeAttr(b.name)}</option>`;
                        });
                        brgySelect.innerHTML = bOptions;
                        brgySelect.disabled = false;
                        if (currentBarangay) brgySelect.value = currentBarangay;
                    }
                }
            }
        }

        // Location callers for direct inline focus (No modal)
        function editProvince() {
            if (!checkLoginOrRedirect('select your province')) return;
            handleLocationRowClick('province', { target: null });
        }
        function editCity() {
            if (!checkLoginOrRedirect('select your city')) return;
            handleLocationRowClick('city', { target: null });
        }
        function editBarangay() {
            if (!checkLoginOrRedirect('select your barangay')) return;
            handleLocationRowClick('barangay', { target: null });
        }
        function showProvincePanel() { editProvince(); }
        function showCityPanel() { editCity(); }
        function showBarangayPanel() { editBarangay(); }

        // Edit Contact / Phone Number (Inline, No Modal)
        function editPhone() {
            if (!checkLoginOrRedirect('add your contact number')) return;
            focusInlineField('phone');
        }

        // Edit Email
        function editEmail() {
            if (isUserLoggedIn()) {
                showNotification('Your email is permanently fixed to your account and cannot be changed.', 3500);
                return;
            }
            if (!checkLoginOrRedirect('edit your email')) return;
            showEmailPanel();
        }
        
        function showEmailPanel() {
            if (isUserLoggedIn()) {
                showNotification('Your email is permanently fixed to your account and cannot be changed.', 3500);
                return;
            }
            if (!checkLoginOrRedirect('edit your email')) return;
            closeAnyBottomPanel();
            const overlay = document.createElement('div');
            overlay.className = 'bottom-panel-overlay';
            
            const panel = document.createElement('div');
            panel.className = 'bottom-panel';
            
            const currentEmail = localStorage.getItem('userEmail') || localStorage.getItem('username') || localStorage.getItem('signupEmail') || '';
            
            panel.innerHTML = `
                <div class="bottom-panel-header">
                    <h3>Edit Email</h3>
                </div>
                <input type="email" 
                       class="bottom-panel-input" 
                       id="emailInput" 
                       placeholder="Enter your email" 
                       value="${currentEmail}"
                       onfocus="placeCaretAtEnd(this)"
                       onclick="placeCaretAtEnd(this)"
                       onmouseup="placeCaretAtEnd(this)"
                       oninput="const b = document.getElementById('btnSaveEmail'); if(b) b.style.display = (this.value.trim() !== '${currentEmail}' && this.value.trim() !== '') ? 'inline-block' : 'none';"
                       onkeypress="if(event.key === 'Enter') saveEmail()">
                <div class="bottom-panel-buttons">
                    <button onclick="saveEmail()" class="bottom-panel-button save" id="btnSaveEmail" style="display: none;">
                        Save
                    </button>
                    <button onclick="closeAnyBottomPanel()" class="bottom-panel-button cancel">
                        Cancel
                    </button>
                </div>
            `;
            
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    closeEmailPanel();
                }
            });
            
            overlay.appendChild(panel);
            document.body.appendChild(overlay);
            
            setTimeout(() => {
                const input = document.getElementById('emailInput');
                if (input) input.focus();
            }, 250);
            
            window.saveEmail = function() {
                const input = document.getElementById('emailInput');
                const email = input ? input.value.trim() : '';
                
                if (email) {
                    localStorage.setItem('userEmail', email);
                    saveAllNamesToDatabase();
                    renderProfileView();
                    showNotification('Email saved!');
                    closeEmailPanel();
                } else {
                    showNotification('Please enter your email', 3000);
                }
            };
            
            window.closeEmailPanel = function() {
                closeAnyBottomPanel();
            };
        }

        // Avatar Upload Handlers
        function triggerFileUpload() {
            if (!checkLoginOrRedirect('change your profile photo')) return;
            const input = document.getElementById('profileImageInput');
            if (input) input.click();
        }
        
        function handleImageUpload(event) {
            if (!checkLoginOrRedirect('upload a profile photo')) return;
            const file = event.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const pvAvatar = document.getElementById('pvAvatar');
                    if (pvAvatar) pvAvatar.src = e.target.result;
                    const topProfileImage = document.getElementById('topProfileImage');
                    if (topProfileImage) topProfileImage.src = e.target.result;
                    
                    saveProfileImageToServer(file);
                };
                reader.readAsDataURL(file);
            } else if (file) {
                alert('Please select a valid image file.');
            }
        }
        
        function saveProfileImageToServer(file) {
            if (!checkLoginOrRedirect('upload a profile photo')) return;
            const formData = new FormData();
            formData.append('action', 'save_profile_image');
            formData.append('profile_image', file);
            
            const userEmail = (window.serverUserData && window.serverUserData.email) 
                ? window.serverUserData.email 
                : (localStorage.getItem('username') || localStorage.getItem('signupEmail'));
            if (userEmail) {
                formData.append('email', userEmail);
            }
            
            fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Profile photo updated!');
                    const topProfileImage = document.getElementById('topProfileImage');
                    if (topProfileImage && data.image_path) {
                        topProfileImage.src = data.image_path;
                    }
                    const pvAvatar = document.getElementById('pvAvatar');
                    if (pvAvatar && data.image_path) {
                        pvAvatar.src = data.image_path;
                    }
                    
                    localStorage.removeItem('redirectAfterProfile');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error saving image:', error);
                alert('Error saving image. Please try again.');
            });
        }

        // Purchases button badge calculation (confirmed, completed, cancelled)
        function updatePurchasesBadge(purchasesData) {
            const badge = document.getElementById('purchaseBadge');
            if (!badge) return;

            let viewed = {};
            try {
                viewed = JSON.parse(localStorage.getItem('viewedPurchases') || '{}');
            } catch(e) {}

            let unviewedCount = 0;
            const targetStatuses = ['confirmed', 'completed', 'cancelled'];

            (purchasesData || []).forEach(p => {
                const status = p.order_status || 'pending';
                const pId = p.id;
                if (targetStatuses.includes(status) && viewed[pId] !== status) {
                    unviewedCount++;
                }
            });

            const topbarBadge = document.getElementById('topbarPurchaseBadge');
            if (unviewedCount > 0) {
                badge.textContent = unviewedCount;
                badge.style.display = 'flex';
                if (topbarBadge) {
                    topbarBadge.textContent = unviewedCount;
                    topbarBadge.style.display = 'flex';
                }
            } else {
                badge.style.display = 'none';
                if (topbarBadge) {
                    topbarBadge.style.display = 'none';
                }
            }
        }

        function fetchPurchaseBadgeCount() {
            const badge = document.getElementById('purchaseBadge');
            const topbarBadge = document.getElementById('topbarPurchaseBadge');

            fetch('save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_purchase_badge_data'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && Array.isArray(data.purchases)) {
                    updatePurchasesBadge(data.purchases);
                } else {
                    if (badge) badge.style.display = 'none';
                    if (topbarBadge) topbarBadge.style.display = 'none';
                }
            })
            .catch(error => {
                if (badge) badge.style.display = 'none';
                if (topbarBadge) topbarBadge.style.display = 'none';
            });
        }

        function fetchCartCount() {
            const badge = document.getElementById('cartCount');
            const topBadge = document.getElementById('topbarCartCount');

            fetch('save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_cart_count'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.count > 0) {
                    if (badge) {
                        badge.textContent = data.count;
                        badge.style.display = 'flex';
                    }
                    if (topBadge) {
                        topBadge.textContent = data.count;
                        topBadge.style.display = 'flex';
                    }
                } else {
                    if (badge) badge.style.display = 'none';
                    if (topBadge) topBadge.style.display = 'none';
                }
            })
            .catch(() => {
                if (badge) badge.style.display = 'none';
                if (topBadge) topBadge.style.display = 'none';
            });
        }

        // ── Topbar Notification Dropdown ──
        let cachedNotifications = [];
        let activeNotifFilter = 'all';
        const NOTIF_USER_ID = <?php echo intval($currentUserData['id'] ?? 0); ?>;

        function ensureDropdownContainer() {
            const dd = document.getElementById('notificationDropdown');
            const container = document.getElementById('topbarNotificationContainer');
            if (dd && container && dd.parentElement !== container) {
                container.appendChild(dd);
            }
        }

        function toggleNotificationDropdown(e) {
            if (e) e.stopPropagation();
            ensureDropdownContainer();
            const dropdown = document.getElementById('notificationDropdown');
            if (!dropdown) return;

            const isShowing = dropdown.classList.contains('show');
            if (isShowing) {
                dropdown.classList.remove('show');
            } else {
                dropdown.classList.add('show');
                fetchNotifications(true);
            }
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdown');
            const container = document.getElementById('topbarNotificationContainer');
            if (dropdown && dropdown.classList.contains('show')) {
                if (!container || !container.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            }
        });

        function filterNotifTab(type, tabEl) {
            activeNotifFilter = type;
            const tabs = document.querySelectorAll('.notif-tab');
            tabs.forEach(t => t.classList.remove('active'));
            if (tabEl) tabEl.classList.add('active');
            renderNotificationsList();
        }

        function fetchNotifications(renderAfter = false) {
            const userId = NOTIF_USER_ID || 0;
            if (!userId) return;
            fetch(`notifications_get.php?user_id=${userId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && Array.isArray(data.notifications)) {
                        cachedNotifications = data.notifications;
                        updateNotificationBadge();
                        if (renderAfter || (document.getElementById('notificationDropdown') && document.getElementById('notificationDropdown').classList.contains('show'))) {
                            renderNotificationsList();
                        }
                    }
                })
                .catch(err => console.error('Error fetching notifications:', err));
        }

        function updateNotificationBadge() {
            const desktopBadge = document.getElementById('topbarNotificationBadge');
            const pill = document.getElementById('notifUnreadPill');
            const tabBadgeAll = document.getElementById('notifTabBadgeAll');
            const tabBadgeOrder = document.getElementById('notifTabBadgeOrder');
            const tabBadgeChat = document.getElementById('notifTabBadgeChat');

            const unreadCount = cachedNotifications.filter(n => !n.is_read).length;
            const orderUnread = cachedNotifications.filter(n => !n.is_read && (n.type === 'order' || !n.type)).length;
            const chatUnread  = cachedNotifications.filter(n => !n.is_read && n.type === 'chat').length;

            if (desktopBadge) {
                desktopBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                desktopBadge.style.display = unreadCount > 0 ? 'flex' : 'none';
            }
            if (pill) {
                pill.textContent = `${unreadCount} new`;
                pill.style.display = unreadCount > 0 ? 'inline-block' : 'none';
            }
            if (tabBadgeAll) {
                tabBadgeAll.textContent = unreadCount;
                tabBadgeAll.style.display = unreadCount > 0 ? 'inline-block' : 'none';
            }
            if (tabBadgeOrder) {
                tabBadgeOrder.textContent = orderUnread;
                tabBadgeOrder.style.display = orderUnread > 0 ? 'inline-block' : 'none';
            }
            if (tabBadgeChat) {
                tabBadgeChat.textContent = chatUnread;
                tabBadgeChat.style.display = chatUnread > 0 ? 'inline-block' : 'none';
            }
        }

        function renderNotificationsList() {
            const list = document.getElementById('notifListContainer');
            if (!list) return;
            let items = cachedNotifications;
            if (activeNotifFilter === 'order') {
                items = items.filter(n => n.type === 'order' || !n.type);
            } else if (activeNotifFilter === 'chat') {
                items = items.filter(n => n.type === 'chat');
            }

            if (!items.length) {
                list.innerHTML = '<div style="padding: 24px; text-align: center; color: #888; font-size: 13px;">No notifications in this category.</div>';
                return;
            }

            list.innerHTML = items.map(n => `
                <div class="notif-item ${n.is_read ? 'read' : 'unread'}" onclick="markNotificationRead(${n.id})">
                    <div class="notif-item-icon">
                        <i class="fas ${n.type === 'chat' ? 'fa-comment-alt' : 'fa-box'}"></i>
                    </div>
                    <div class="notif-item-content">
                        <div class="notif-item-title">${escapeHtml(n.title || 'Notification')}</div>
                        <div class="notif-item-desc">${escapeHtml(n.message || '')}</div>
                        <div class="notif-item-time">${escapeHtml(n.created_at || '')}</div>
                    </div>
                </div>
            `).join('');
        }

        function markAllNotificationsAsRead(e) {
            if (e) e.stopPropagation();
            const userId = NOTIF_USER_ID || 0;
            if (!userId) return;
            fetch(`notifications_mark_read.php?user_id=${userId}&all=1`, { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        cachedNotifications.forEach(n => n.is_read = 1);
                        updateNotificationBadge();
                        renderNotificationsList();
                    }
                });
        }

        function markNotificationRead(id) {
            fetch(`notifications_mark_read.php?id=${id}`, { method: 'POST' })
                .then(r => r.json())
                .then(() => {
                    const item = cachedNotifications.find(n => n.id == id);
                    if (item) item.is_read = 1;
                    updateNotificationBadge();
                    renderNotificationsList();
                });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchPurchaseBadgeCount();
            fetchCartCount();
            fetchNotifications();
        });
        window.addEventListener('pageshow', () => {
            fetchPurchaseBadgeCount();
            fetchCartCount();
            fetchNotifications();
        });
        setInterval(fetchPurchaseBadgeCount, 10000);
        setInterval(fetchCartCount, 10000);
        setInterval(fetchNotifications, 10000);
    </script>

    <!-- Full Size Valid ID Modal - Mobile Bottom Panel Style -->
    <div id="idLightboxModal" class="id-lightbox-modal" onclick="closeIdLightbox()">
        <div class="id-lightbox-content" onclick="event.stopPropagation()">
            <div class="id-lightbox-handle"></div>
            <div class="id-lightbox-header">
                <span id="idLightboxTitle"><i class="fas fa-id-card"></i> Valid ID Card (Front with User)</span>
                <button type="button" class="id-lightbox-close" onclick="closeIdLightbox()" title="Close">&times;</button>
            </div>
            <div class="id-lightbox-body-scroll">
                <div class="id-lightbox-dual-grid" style="grid-template-columns: 1fr;">
                    <div class="id-lightbox-side-card">
                        <div class="id-lightbox-side-label"><i class="fas fa-id-badge"></i> CARD FRONT INCLUDING USER</div>
                        <div class="id-lightbox-img-box" style="max-height: 440px; min-height: 220px;">
                            <img id="idLightboxFrontImg" src="" alt="Valid ID with User" class="id-lightbox-img" style="max-height: 420px;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
