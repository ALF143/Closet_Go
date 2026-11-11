<?php
/**
 * staff/api_get_reservation.php
 * Staff API endpoint: GET { code / search / status / id }
 * Returns full reservation details including products, sizes, dates, customer info, and status.
 */
session_start();
header('Content-Type: application/json');

require_once '../database/db_config.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Auto-migrate column if not present
$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reservation_code VARCHAR(50) DEFAULT NULL AFTER reservation_group_id");
$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS is_rescheduled TINYINT(1) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS rescheduled_at DATETIME DEFAULT NULL");
$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS original_reserve_date DATE DEFAULT NULL");
$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS original_return_date DATE DEFAULT NULL");
$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reschedule_reason TEXT DEFAULT NULL");

$code   = isset($_GET['code']) ? trim($_GET['code']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$resId  = isset($_GET['id']) ? intval($_GET['id']) : 0;

$whereClauses = [];
$params = [];
$types = '';

$cleanCode = '';
$exactId = 0;
$legacyPaddedId = 0;

if (!empty($code)) {
    // Exact, numeric, prefix, or pattern match on reservation code, group ID, or ID
    $cleanCode = trim(preg_replace('/[^a-zA-Z0-9\-_]/', '', $code));
    
    // Check for direct numeric ID ("74", "#74")
    $rawDigits = preg_replace('/[^0-9]/', '', $code);
    $exactId = (is_numeric($rawDigits) && strlen($rawDigits) <= 8) ? intval($rawDigits) : 0;

    // Check for "CG-74" or "CGW-74"
    if (preg_match('/^(?:CGW|CG)-?(\d{1,8})$/i', $cleanCode, $m)) {
        $exactId = intval($m[1]);
    }

    // Check for legacy generated codes where last segment is padded ID, e.g. "CG-260730-0015"
    if (preg_match('/^(?:CGW|CG)-?\d{6}-(\d{1,8})$/i', $cleanCode, $m)) {
        $legacyPaddedId = intval($m[1]);
    }

    $whereClauses[] = "(
        rp.reservation_code = ? 
        OR rp.reservation_group_id = ? 
        OR rp.reservation_code = ? 
        OR rp.reservation_group_id = ? 
        OR CONCAT('CG-', rp.id) = ? 
        OR CONCAT('CGW-', rp.id) = ? 
        OR CONCAT('#', rp.id) = ? 
        OR (rp.id > 0 AND rp.id = ?) 
        OR (rp.id > 0 AND rp.id = ?) 
        OR rp.reservation_code LIKE ? 
        OR rp.reservation_group_id LIKE ?
    )";

    $params[] = $code;
    $params[] = $code;
    $params[] = $cleanCode;
    $params[] = $cleanCode;
    $params[] = $cleanCode;
    $params[] = $cleanCode;
    $params[] = $code;
    $params[] = $exactId;
    $params[] = $legacyPaddedId;
    $codeLike = '%' . $cleanCode . '%';
    $params[] = $codeLike;
    $params[] = $codeLike;
    $types .= 'sssssssiiss';
} elseif ($resId > 0) {
    $whereClauses[] = "rp.id = ?";
    $params[] = $resId;
    $types .= 'i';
} elseif (!empty($search)) {
    $searchLike = '%' . $search . '%';
    $whereClauses[] = "(rp.reservation_code LIKE ? OR rp.reservation_group_id LIKE ? OR rp.id = ? OR rp.first_name LIKE ? OR rp.last_name LIKE ? OR CONCAT(rp.first_name, ' ', rp.last_name) LIKE ? OR u.email LIKE ? OR rp.product_name LIKE ?)";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = intval($search);
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= 'ssisssss';
}

if ($status === 'walkin') {
    $whereClauses[] = "(rp.reservation_code LIKE 'CGW-%' OR rp.last_name LIKE '%Walk-In%' OR rp.payment_method = 'cash')";
} elseif ($status === 'rescheduled') {
    $whereClauses[] = "rp.is_rescheduled = 1";
} elseif ($status !== 'all' && !empty($status)) {
    $whereClauses[] = "rp.order_status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$orderBySql = "ORDER BY rp.reserved_at DESC, rp.id DESC";
if (!empty($code)) {
    $escCode = "'" . $conn->real_escape_string($cleanCode) . "'";
    $sortId = intval($exactId > 0 ? $exactId : $legacyPaddedId);
    $orderBySql = "ORDER BY 
        (CASE 
            WHEN rp.reservation_code = $escCode THEN 1 
            WHEN rp.reservation_group_id = $escCode THEN 2 
            WHEN rp.id = $sortId THEN 3 
            WHEN CONCAT('CG-', rp.id) = $escCode OR CONCAT('CGW-', rp.id) = $escCode THEN 4 
            ELSE 10 
        END) ASC, 
        rp.reserved_at DESC, 
        rp.id DESC";
}

$sql = "SELECT rp.*, 
               COALESCE(u.email, '') as user_email,
               COALESCE(u.phone, '') as user_phone,
               COALESCE(u.first_name, rp.first_name) as cust_first_name,
               COALESCE(u.last_name, rp.last_name) as cust_last_name,
               COALESCE(u.profile_image, '') as user_profile,
               COALESCE(u.id_type, '') as id_type,
               COALESCE(u.id_front_image, '') as id_front_image,
               COALESCE(u.id_back_image, '') as id_back_image,
               COALESCE(g.image_path, '') as image_path,
               COALESCE(g.image_path2, '') as image_path2,
               COALESCE(g.image_path3, '') as image_path3,
               COALESCE(rp.model_path, g.model_path) as model_path
        FROM reserve_products rp
        LEFT JOIN system_users u ON rp.user_id = u.id
        LEFT JOIN gowns g ON rp.product_id = g.id
        $whereSql
        $orderBySql
        LIMIT 100";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Query error: ' . $conn->error]);
    exit;
}

if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$rawList = [];
while ($row = $result->fetch_assoc()) {
    // Generate fallback reservation_code if empty
    if (empty($row['reservation_code'])) {
        $row['reservation_code'] = 'CG-' . date('ymd', strtotime($row['reserved_at'] ?: 'now')) . '-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT);
    }

    // Fetch Item 2+ sizes
    $itemsSql = "SELECT * FROM reserve_products_items WHERE reserve_product_id = ? ORDER BY item_number ASC";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("i", $row['id']);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    $row['items'] = [];
    while ($itemRow = $itemsResult->fetch_assoc()) {
        $row['items'][] = $itemRow;
    }
    $itemsStmt->close();

    // Fetch size labels from categories table
    $catName = $row['category'] ?? '';
    $labelSql = "SELECT size_label_1, size_label_2, size_label_3, size_label_4, size_label_5, size_label_6, size_label_7, size_label_8, size_label_9, size_label_10 FROM categories WHERE LOWER(TRIM(category_name)) = LOWER(TRIM(?)) LIMIT 1";
    $labelStmt = $conn->prepare($labelSql);
    $labelStmt->bind_param("s", $catName);
    $labelStmt->execute();
    $labelResult = $labelStmt->get_result();
    $row['size_labels'] = [];
    if ($labelRow = $labelResult->fetch_assoc()) {
        for ($i = 1; $i <= 10; $i++) {
            $labelText = $labelRow["size_label_{$i}"] ?? null;
            if ($labelText !== null && $labelText !== '') {
                $row['size_labels'][] = ['index' => $i, 'label' => $labelText];
            }
        }
    }
    $labelStmt->close();

    $rawList[] = $row;
}
$stmt->close();

// Group reservations by reservation_code or reservation_group_id
$grouped = [];
$groupMap = [];

foreach ($rawList as $row) {
    $groupKey = !empty($row['reservation_code'])
        ? $row['reservation_code']
        : (!empty($row['reservation_group_id']) ? $row['reservation_group_id'] : 'RES_' . $row['id']);

    $isWalkIn = (strpos($row['reservation_code'] ?? '', 'CGW-') === 0) 
                || (stripos($row['last_name'] ?? '', 'Walk-In') !== false) 
                || (strtolower($row['payment_method'] ?? '') === 'cash');

    $proof = $row['payment_proof'] ?? '';

    if (!isset($groupMap[$groupKey])) {
        $idx = count($grouped);
        $groupMap[$groupKey] = $idx;
        $idType = !empty($row['id_type']) ? $row['id_type'] : '';
        $idFront = !empty($row['id_front_image']) ? $row['id_front_image'] : '';
        $idBack = !empty($row['id_back_image']) ? $row['id_back_image'] : '';
        $validIdDisplay = !empty($idType) ? $idType : (!empty($idFront) ? 'Uploaded' : 'N/A');

        $grouped[$idx] = [
            'id'                   => $row['id'],
            'user_id'              => $row['user_id'] ?? 0,
            'reservation_code'     => $row['reservation_code'],
            'reservation_group_id' => $row['reservation_group_id'],
            'order_status'         => $row['order_status'] ?? 'pending',
            'product_status'       => $row['product_status'] ?? 'reserve',
            'is_walkin'            => $isWalkIn,
            'customer_name'        => trim(($row['cust_first_name'] ?? '') . ' ' . ($row['cust_last_name'] ?? '')),
            'customer_email'       => $row['user_email'] ?? '',
            'customer_phone'       => $row['user_phone'] ?? '',
            'customer_profile'     => $row['user_profile'] ?? '',
            'customer_valid_id'    => $validIdDisplay,
            'id_type'              => $idType,
            'id_front_image'       => $idFront,
            'id_back_image'        => $idBack,
            'payment_method'       => $row['payment_method'] ?? 'reserve',
            'payment_option'       => $row['payment_option'] ?? 'full',
            'payment_proof'        => $proof,
            'downpayment_amount'   => floatval($row['downpayment_amount'] ?? 0),
            'balance_amount'       => floatval($row['balance_amount'] ?? 0),
            'total_price'          => floatval($row['total_price'] ?? 0),
            'reserve_date'         => $row['reserve_date'] ?? '',
            'return_date'          => $row['return_date'] ?? '',
            'reserve_time'         => $row['reserve_time'] ?? '',
            'return_time'          => $row['return_time'] ?? '',
            'reserved_at'          => $row['reserved_at'] ?? '',
            'cancellation_reason'  => $row['cancellation_reason'] ?? '',
            'is_rescheduled'       => intval($row['is_rescheduled'] ?? 0),
            'rescheduled_at'       => $row['rescheduled_at'] ?? '',
            'original_reserve_date'=> $row['original_reserve_date'] ?? '',
            'original_return_date' => $row['original_return_date'] ?? '',
            'reschedule_reason'    => $row['reschedule_reason'] ?? '',
            'products'             => []
        ];
    } else {
        $idx = $groupMap[$groupKey];
        $grouped[$idx]['total_price'] += floatval($row['total_price'] ?? 0);
        $grouped[$idx]['downpayment_amount'] += floatval($row['downpayment_amount'] ?? 0);
        $grouped[$idx]['balance_amount'] += floatval($row['balance_amount'] ?? 0);
        if (!empty($proof) && empty($grouped[$idx]['payment_proof'])) {
            $grouped[$idx]['payment_proof'] = $proof;
        }
        if ($isWalkIn) {
            $grouped[$idx]['is_walkin'] = true;
        }
        if (empty($grouped[$idx]['id_front_image']) && !empty($row['id_front_image'])) {
            $grouped[$idx]['id_front_image'] = $row['id_front_image'];
        }
        if (empty($grouped[$idx]['id_back_image']) && !empty($row['id_back_image'])) {
            $grouped[$idx]['id_back_image'] = $row['id_back_image'];
        }
        if (empty($grouped[$idx]['id_type']) && !empty($row['id_type'])) {
            $grouped[$idx]['id_type'] = $row['id_type'];
            $grouped[$idx]['customer_valid_id'] = $row['id_type'];
        }
        if (empty($grouped[$idx]['customer_phone']) && !empty($row['user_phone'])) {
            $grouped[$idx]['customer_phone'] = $row['user_phone'];
        }
        if (!empty($row['is_rescheduled'])) {
            $grouped[$idx]['is_rescheduled'] = 1;
            if (empty($grouped[$idx]['rescheduled_at']) && !empty($row['rescheduled_at'])) {
                $grouped[$idx]['rescheduled_at'] = $row['rescheduled_at'];
            }
            if (empty($grouped[$idx]['original_reserve_date']) && !empty($row['original_reserve_date'])) {
                $grouped[$idx]['original_reserve_date'] = $row['original_reserve_date'];
            }
            if (empty($grouped[$idx]['original_return_date']) && !empty($row['original_return_date'])) {
                $grouped[$idx]['original_return_date'] = $row['original_return_date'];
            }
            if (empty($grouped[$idx]['reschedule_reason']) && !empty($row['reschedule_reason'])) {
                $grouped[$idx]['reschedule_reason'] = $row['reschedule_reason'];
            }
        }
    }

    $grouped[$groupMap[$groupKey]]['products'][] = $row;
}

// Fallback: check payment_proofs table for any missing payment_proof values
foreach ($grouped as &$g) {
    if (empty($g['payment_proof']) && !empty($g['reservation_group_id'])) {
        $ppStmt = $conn->prepare("SELECT image_path FROM payment_proofs WHERE reservation_group_id = ? ORDER BY id DESC LIMIT 1");
        if ($ppStmt) {
            $ppStmt->bind_param("s", $g['reservation_group_id']);
            $ppStmt->execute();
            $ppRes = $ppStmt->get_result();
            if ($ppRow = $ppRes->fetch_assoc()) {
                $g['payment_proof'] = $ppRow['image_path'];
            }
            $ppStmt->close();
        }
    }
}
unset($g);

// Summary KPI counts
$countsSql = "SELECT 
    COUNT(DISTINCT COALESCE(NULLIF(reservation_code, ''), NULLIF(reservation_group_id, ''), id)) as total_count,
    COUNT(DISTINCT CASE WHEN order_status = 'pending' THEN COALESCE(NULLIF(reservation_code, ''), NULLIF(reservation_group_id, ''), id) END) as pending_count,
    COUNT(DISTINCT CASE WHEN order_status = 'confirmed' THEN COALESCE(NULLIF(reservation_code, ''), NULLIF(reservation_group_id, ''), id) END) as confirmed_count,
    COUNT(DISTINCT CASE WHEN order_status = 'completed' THEN COALESCE(NULLIF(reservation_code, ''), NULLIF(reservation_group_id, ''), id) END) as completed_count,
    COUNT(DISTINCT CASE WHEN is_rescheduled = 1 THEN COALESCE(NULLIF(reservation_code, ''), NULLIF(reservation_group_id, ''), id) END) as rescheduled_count,
    COUNT(DISTINCT CASE WHEN order_status = 'cancelled' THEN COALESCE(NULLIF(reservation_code, ''), NULLIF(reservation_group_id, ''), id) END) as cancelled_count,
    COUNT(DISTINCT CASE WHEN (reservation_code LIKE 'CGW-%' OR last_name LIKE '%Walk-In%' OR payment_method = 'cash') THEN COALESCE(NULLIF(reservation_code, ''), NULLIF(reservation_group_id, ''), id) END) as walkin_count
FROM reserve_products";
$countsRes = $conn->query($countsSql);
$counts = $countsRes ? $countsRes->fetch_assoc() : [
    'total_count' => 0, 'pending_count' => 0, 'confirmed_count' => 0, 'completed_count' => 0, 'rescheduled_count' => 0, 'cancelled_count' => 0, 'walkin_count' => 0
];

echo json_encode([
    'success'      => true,
    'reservations' => $grouped,
    'count'        => count($grouped),
    'kpis'         => [
        'total'       => (int)($counts['total_count'] ?? 0),
        'pending'     => (int)($counts['pending_count'] ?? 0),
        'confirmed'   => (int)($counts['confirmed_count'] ?? 0),
        'completed'   => (int)($counts['completed_count'] ?? 0),
        'rescheduled' => (int)($counts['rescheduled_count'] ?? 0),
        'cancelled'   => (int)($counts['cancelled_count'] ?? 0),
        'walkin'      => (int)($counts['walkin_count'] ?? 0),
    ]
]);
