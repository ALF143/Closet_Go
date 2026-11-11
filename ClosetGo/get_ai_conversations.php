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
    $sql = "SELECT id, question, answer, product_ids, created_at FROM ai_conversations WHERE user_id = ? ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
} else {
    $sql = "SELECT id, question, answer, product_ids, created_at FROM ai_conversations WHERE user_identifier = ? AND user_id IS NULL ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $userIdentifier);
}

$stmt->execute();
$result = $stmt->get_result();

$conversations = [];
while ($row = $result->fetch_assoc()) {
    $conversations[] = [
        'id' => $row['id'],
        'question' => $row['question'],
        'answer' => $row['answer'],
        'product_ids' => $row['product_ids'] ? json_decode($row['product_ids'], true) : [],
        'created_at' => $row['created_at']
    ];
}

$stmt->close();

echo json_encode(['success' => true, 'conversations' => $conversations]);
?>
