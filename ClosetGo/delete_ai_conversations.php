<?php
require_once 'database/db_config.php';

header('Content-Type: application/json');

session_start();

$input = json_decode(file_get_contents('php://input'), true);
$userIdentifier = isset($input['user_identifier']) ? $input['user_identifier'] : 'anonymous';

$db = new Database();
$conn = $db->getConnection();

$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if ($userId) {
    $stmt = $conn->prepare("DELETE FROM ai_conversations WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
} else {
    $stmt = $conn->prepare("DELETE FROM ai_conversations WHERE user_identifier = ? AND user_id IS NULL");
    $stmt->bind_param("s", $userIdentifier);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Conversations deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete conversations']);
}

$stmt->close();
?>
