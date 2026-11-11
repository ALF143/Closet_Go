<?php
require_once 'database/db_config.php';

header('Content-Type: application/json');

session_start();

$input = json_decode(file_get_contents('php://input'), true);
$question = isset($input['question']) ? trim($input['question']) : '';
$answer = isset($input['answer']) ? trim($input['answer']) : '';
$productIds = isset($input['product_ids']) ? $input['product_ids'] : [];

if (empty($question) || empty($answer)) {
    echo json_encode(['success' => false, 'message' => 'Missing question or answer']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get user identifier - use session user_id if logged in, otherwise use a browser fingerprint
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$userIdentifier = $userId ? null : (isset($input['user_identifier']) ? $input['user_identifier'] : 'anonymous');

// Save the conversation (allow multiple/repeated questions to save sequentially)
$productIdsJson = !empty($productIds) ? json_encode($productIds) : null;

if ($userId) {
    $sql = "INSERT INTO ai_conversations (user_id, question, answer, product_ids) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $userId, $question, $answer, $productIdsJson);
} else {
    $sql = "INSERT INTO ai_conversations (user_identifier, question, answer, product_ids) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $userIdentifier, $question, $answer, $productIdsJson);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save conversation']);
}

$stmt->close();
?>
