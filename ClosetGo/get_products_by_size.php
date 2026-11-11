<?php
require_once 'database/db_config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$height = isset($input['height']) ? floatval($input['height']) : 0;
$weight = isset($input['weight']) ? floatval($input['weight']) : 0;

if ($height <= 0 || $weight <= 0) {
    echo json_encode(['products' => [], 'men' => [], 'women' => []]);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Estimate body measurements from height and weight
// These are rough estimates based on typical body proportion formulas
$estimatedChest = round(50 + ($weight * 0.4) + ($height - 170) * 0.2);   // cm
$estimatedWaist = round(40 + ($weight * 0.35) + ($height - 170) * 0.15); // cm
$estimatedHips  = round(55 + ($weight * 0.4) + ($height - 170) * 0.2);   // cm
$estimatedShoulder = round(38 + ($weight * 0.1) + ($height - 170) * 0.15); // cm

// Men's categories
$menCategories = ['Men', 'Weeding Wear for Men', 'Tuxedo Suit (Kids)', 'Barong'];
// Women's categories
$womenCategories = ['Women', 'Weeding Gown', 'Ball Gown', 'Filipiniana', 'Ball Gown (Kids)'];

function fetchProductsBySize($conn, $height, $weight, $estChest, $estWaist, $estHips, $estShoulder, $categories) {
    $catPlaceholders = implode(',', array_fill(0, count($categories), '?'));
    $catTypes = str_repeat('s', count($categories));

    // Fetch all products in these categories with stock > 0
    $sql = "SELECT id, name, image_path, color, model_path, category,
                   size_1_min, size_1_max, size_2_min, size_2_max,
                   size_3_min, size_3_max, size_4_min, size_4_max,
                   size_5_min, size_5_max, size_6_min, size_6_max,
                   size_7_min, size_7_max, size_8_min, size_8_max,
                   size_9_min, size_9_max, size_10_min, size_10_max
            FROM gowns
            WHERE stock > 0 AND category IN ($catPlaceholders)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($catTypes, ...$categories);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch size labels per category
    $products = [];
    while ($row = $result->fetch_assoc()) {
        // Get size labels for this category
        $catName = $row['category'];
        $labelSql = "SELECT size_label_1, size_label_2, size_label_3, size_label_4, size_label_5, size_label_6, size_label_7, size_label_8, size_label_9, size_label_10 FROM categories WHERE LOWER(TRIM(category_name)) = LOWER(TRIM(?)) LIMIT 1";
        $labelStmt = $conn->prepare($labelSql);
        $labelStmt->bind_param("s", $catName);
        $labelStmt->execute();
        $labelResult = $labelStmt->get_result();
        $labels = [];
        if ($labelRow = $labelResult->fetch_assoc()) {
            for ($i = 1; $i <= 10; $i++) {
                $labels[$i] = trim((string)($labelRow["size_label_{$i}"] ?? ''));
            }
        }
        $labelStmt->close();

        // Check if user's estimated measurements fit within any size range
        $fits = false;
        $matchCount = 0;
        $totalRanges = 0;

        for ($i = 1; $i <= 10; $i++) {
            $minCol = "size_{$i}_min";
            $maxCol = "size_{$i}_max";
            $minVal = (int)($row[$minCol] ?? 0);
            $maxVal = (int)($row[$maxCol] ?? 0);
            $label = strtolower($labels[$i] ?? '');

            // Skip empty ranges (both 0)
            if ($minVal <= 0 && $maxVal <= 0) continue;
            $totalRanges++;

            // Map label to estimated measurement
            $userMeasurement = null;
            if (strpos($label, 'bust') !== false || strpos($label, 'chest') !== false) {
                $userMeasurement = $estChest;
            } elseif (strpos($label, 'waist') !== false || strpos($label, 'w') === 0) {
                $userMeasurement = $estWaist;
            } elseif (strpos($label, 'hip') !== false) {
                $userMeasurement = $estHips;
            } elseif (strpos($label, 'shoulder') !== false || strpos($label, 's') === 0) {
                $userMeasurement = $estShoulder;
            } elseif (strpos($label, 'length') !== false || strpos($label, 'sleeve') !== false || strpos($label, 'top') !== false) {
                // Length-based: use height as approximation
                $userMeasurement = $height;
            }

            if ($userMeasurement !== null && $userMeasurement >= $minVal && $userMeasurement <= $maxVal) {
                $matchCount++;
            }
        }

        // Product fits if it matches at least half of its defined size ranges
        if ($totalRanges > 0 && $matchCount >= ceil($totalRanges / 2)) {
            $fits = true;
        }
        // Also include if product has no meaningful size data (all 0s) - don't exclude
        if ($totalRanges === 0) {
            $fits = true;
        }

        if ($fits) {
            $products[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'image_path' => $row['image_path'],
                'color' => $row['color'],
                'model_path' => $row['model_path'],
                'category' => $row['category']
            ];
        }
    }
    $stmt->close();

    // Limit to 9
    return array_slice($products, 0, 9);
}

$menProducts = fetchProductsBySize($conn, $height, $weight, $estimatedChest, $estimatedWaist, $estimatedHips, $estimatedShoulder, $menCategories);
$womenProducts = fetchProductsBySize($conn, $height, $weight, $estimatedChest, $estimatedWaist, $estimatedHips, $estimatedShoulder, $womenCategories);
$allProducts = array_merge($menProducts, $womenProducts);

echo json_encode([
    'products' => $allProducts,
    'men' => $menProducts,
    'women' => $womenProducts,
    'estimates' => [
        'chest' => $estimatedChest,
        'waist' => $estimatedWaist,
        'hips' => $estimatedHips,
        'shoulder' => $estimatedShoulder
    ]
]);
?>
