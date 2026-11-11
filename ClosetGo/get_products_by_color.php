<?php
require_once 'database/db_config.php';

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$colors = isset($input['colors']) ? $input['colors'] : [];

if (empty($colors)) {
    echo json_encode(['products' => [], 'men' => [], 'women' => []]);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Men's categories
$menCategories = ['Men', 'Weeding Wear for Men', 'Tuxedo Suit (Kids)', 'Barong'];
// Women's categories
$womenCategories = ['Women', 'Weeding Gown', 'Ball Gown', 'Filipiniana', 'Ball Gown (Kids)'];

function fetchProductsByColor($conn, $colors, $categories) {
    $colorPlaceholders = implode(',', array_fill(0, count($colors), '?'));
    $catPlaceholders = implode(',', array_fill(0, count($categories), '?'));
    $sql = "SELECT id, name, image_path, color, model_path, category FROM gowns 
            WHERE color IN ($colorPlaceholders) AND category IN ($catPlaceholders) AND stock > 0 LIMIT 9";

    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($colors)) . str_repeat('s', count($categories));
    $params = array_merge($colors, $categories);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $result = $stmt->get_result();
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'image_path' => $row['image_path'],
            'color' => $row['color'],
            'model_path' => $row['model_path'],
            'category' => $row['category']
        ];
    }

    $stmt->close();
    return $products;
}

$menProducts = fetchProductsByColor($conn, $colors, $menCategories);
$womenProducts = fetchProductsByColor($conn, $colors, $womenCategories);
$allProducts = array_merge($menProducts, $womenProducts);

echo json_encode([
    'products' => $allProducts,
    'men' => $menProducts,
    'women' => $womenProducts
]);
?>
