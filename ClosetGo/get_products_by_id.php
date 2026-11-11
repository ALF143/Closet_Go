<?php
require_once 'database/db_config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$ids = isset($input['ids']) ? $input['ids'] : [];

if (empty($ids)) {
    echo json_encode(['products' => [], 'men' => [], 'women' => []]);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Sanitize IDs to integers
$intIds = array_map('intval', $ids);
$intIds = array_filter($intIds, function($id) { return $id > 0; });

if (empty($intIds)) {
    echo json_encode(['products' => [], 'men' => [], 'women' => []]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($intIds), '?'));
$types = str_repeat('i', count($intIds));

$sql = "SELECT id, name, image_path, color, model_path, category FROM gowns WHERE id IN ($placeholders)";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$intIds);
$stmt->execute();
$result = $stmt->get_result();

$menCategories = ['Men', 'Weeding Wear for Men', 'Tuxedo Suit (Kids)', 'Barong'];
$menProducts = [];
$womenProducts = [];

while ($row = $result->fetch_assoc()) {
    $product = [
        'id' => $row['id'],
        'name' => $row['name'],
        'image_path' => $row['image_path'],
        'color' => $row['color'],
        'model_path' => $row['model_path'],
        'category' => $row['category']
    ];

    if (in_array($row['category'], $menCategories)) {
        $menProducts[] = $product;
    } else {
        $womenProducts[] = $product;
    }
}

$stmt->close();

echo json_encode([
    'products' => array_merge($menProducts, $womenProducts),
    'men' => $menProducts,
    'women' => $womenProducts
]);
?>
