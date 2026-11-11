<?php
require_once 'database/db_config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$budgetType = isset($input['budget_type']) ? $input['budget_type'] : ''; // 'buy' or 'reserve'
$minPrice = isset($input['min_price']) ? floatval($input['min_price']) : 0;
$maxPrice = isset($input['max_price']) ? floatval($input['max_price']) : 999999;

if (empty($budgetType)) {
    echo json_encode(['products' => [], 'men' => [], 'women' => []]);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Men's categories
$menCategories = ['Men', 'Weeding Wear for Men', 'Tuxedo Suit (Kids)', 'Barong'];
// Women's categories (everything else with stock)
$womenCategories = ['Women', 'Weeding Gown', 'Ball Gown', 'Filipiniana', 'Ball Gown (Kids)'];

function fetchProducts($conn, $budgetType, $minPrice, $maxPrice, $categories) {
    $placeholders = implode(',', array_fill(0, count($categories), '?'));
    $catTypes = str_repeat('s', count($categories));

    if ($budgetType === 'buy') {
        $sql = "SELECT id, name, image_path, color, model_path, category, purchase_price FROM gowns 
                WHERE stock > 0 AND purchase_price > 0 AND purchase_price >= ? AND purchase_price <= ? 
                AND category IN ($placeholders)
                ORDER BY purchase_price ASC LIMIT 9";
    } else {
        $sql = "SELECT id, name, image_path, color, model_path, category, price, discount, 
                       (price - (price * discount / 100)) as effective_price FROM gowns 
                WHERE stock > 0 AND price > 0 AND (price - (price * discount / 100)) >= ? AND (price - (price * discount / 100)) <= ? 
                AND category IN ($placeholders)
                ORDER BY effective_price ASC LIMIT 9";
    }

    $stmt = $conn->prepare($sql);
    $types = "dd" . $catTypes;
    $params = [$minPrice, $maxPrice];
    foreach ($categories as $cat) {
        $params[] = $cat;
    }
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

$menProducts = fetchProducts($conn, $budgetType, $minPrice, $maxPrice, $menCategories);
$womenProducts = fetchProducts($conn, $budgetType, $minPrice, $maxPrice, $womenCategories);

// Also keep combined for backward compatibility
$allProducts = array_merge($menProducts, $womenProducts);

echo json_encode([
    'products' => $allProducts,
    'men' => $menProducts,
    'women' => $womenProducts
]);
?>

