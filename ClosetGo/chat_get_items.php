<?php
/**
 * chat_get_items.php
 * Endpoint: GET
 * Returns products, cart items, and purchase items for the chat inquiry picker.
 */
error_reporting(0);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_set_cookie_params(['path' => '/']);
    }
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/database/db_config.php';

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
if ($userId <= 0 && isset($_GET['user_id'])) {
    $userId = intval($_GET['user_id']);
}

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// 1. Store Products (from gowns)
$products = [];
$prodSql = "SELECT id, name, category, price, purchase_price, discount, color, image_path, stock FROM gowns WHERE stock > 0 ORDER BY id DESC LIMIT 60";
$prodResult = $conn->query($prodSql);
if ($prodResult) {
    while ($row = $prodResult->fetch_assoc()) {
        $price = (float)$row['price'];
        $products[] = [
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'category'       => $row['category'],
            'price'          => number_format($price, 2),
            'price_raw'      => $price,
            'purchase_price' => number_format((float)($row['purchase_price'] ?? 0), 2),
            'discount'       => (float)($row['discount'] ?? 0),
            'color'          => $row['color'] ?? '',
            'image_path'     => $row['image_path'] ?: 'images/icon/profile_logo.png',
            'type'           => 'product',
            'source'         => 'Store Product'
        ];
    }
}

// 2. Cart Items (from saved_products)
$cartItems = [];
if ($userId > 0) {
    $cartSql = "SELECT sp.id as cart_id, sp.product_id, sp.product_name as name, sp.product_category as category, sp.product_price as price, sp.reserve_date, sp.return_date, COALESCE(g.image_path, '') as image_path FROM saved_products sp LEFT JOIN gowns g ON sp.product_id = g.id WHERE sp.user_id = ? ORDER BY sp.saved_at DESC";
    $cartStmt = $conn->prepare($cartSql);
    if ($cartStmt) {
        $cartStmt->bind_param("i", $userId);
        $cartStmt->execute();
        $cartResult = $cartStmt->get_result();
        while ($row = $cartResult->fetch_assoc()) {
            $price = (float)$row['price'];
            $dates = '';
            if (!empty($row['reserve_date']) && !empty($row['return_date'])) {
                $dates = date('M d', strtotime($row['reserve_date'])) . ' - ' . date('M d, Y', strtotime($row['return_date']));
            }
            $cartItems[] = [
                'id'         => (int)($row['product_id'] ?: $row['cart_id']),
                'cart_id'    => (int)$row['cart_id'],
                'name'       => $row['name'],
                'category'   => $row['category'],
                'price'      => number_format($price, 2),
                'price_raw'  => $price,
                'dates'      => $dates,
                'image_path' => $row['image_path'] ?: 'images/icon/profile_logo.png',
                'type'       => 'cart',
                'source'     => 'In Cart'
            ];
        }
        $cartStmt->close();
    }
}

// 3. Purchase Items (from reserve_products)
$purchases = [];
if ($userId > 0) {
    $purchSql = "SELECT rp.id, rp.product_id, rp.product_name as name, rp.category, rp.price, rp.total_price, rp.order_status, rp.reserve_date, rp.return_date, rp.reserved_at, COALESCE(g.image_path, '') as image_path FROM reserve_products rp LEFT JOIN gowns g ON rp.product_id = g.id WHERE rp.user_id = ? ORDER BY rp.reserved_at DESC, rp.id DESC";
    $purchStmt = $conn->prepare($purchSql);
    if ($purchStmt) {
        $purchStmt->bind_param("i", $userId);
        $purchStmt->execute();
        $purchResult = $purchStmt->get_result();
        while ($row = $purchResult->fetch_assoc()) {
            $price = (float)($row['total_price'] ?: $row['price']);
            $dates = '';
            if (!empty($row['reserve_date']) && !empty($row['return_date'])) {
                $dates = date('M d', strtotime($row['reserve_date'])) . ' - ' . date('M d, Y', strtotime($row['return_date']));
            }
            $status = ucfirst($row['order_status'] ?? 'Pending');
            $purchases[] = [
                'id'           => (int)($row['product_id'] ?: $row['id']),
                'reserve_id'   => (int)$row['id'],
                'name'         => $row['name'],
                'category'     => $row['category'],
                'price'        => number_format($price, 2),
                'price_raw'    => $price,
                'dates'        => $dates,
                'status'       => $status,
                'order_status' => $row['order_status'],
                'image_path'   => $row['image_path'] ?: 'images/icon/profile_logo.png',
                'type'         => 'purchase',
                'source'       => 'Order #' . $row['id'] . ' (' . $status . ')'
            ];
        }
        $purchStmt->close();
    }
}

echo json_encode([
    'success'   => true,
    'products'  => $products,
    'cart'      => $cartItems,
    'purchases' => $purchases,
    'logged_in' => ($userId > 0)
]);
exit();

