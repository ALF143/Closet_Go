<?php
session_start();
require_once '../database/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$reservationId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($reservationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'DB connection failed']);
        exit();
    }

    $sql = "SELECT rp.*, COALESCE(g.image_path, '') as gown_image, COALESCE(rp.model_path, g.model_path) as gown_model,
                   u.id_type, u.id_front_image, u.id_back_image, u.email, u.phone
            FROM reserve_products rp 
            LEFT JOIN gowns g ON rp.product_id = g.id 
            LEFT JOIN system_users u ON rp.user_id = u.id
            WHERE rp.id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $reservationId);
    $stmt->execute();
    $result = $stmt->get_result();
    $mainReservation = $result->fetch_assoc();
    $stmt->close();

    if (!$mainReservation) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
        exit();
    }

    $groupId = !empty($mainReservation['reservation_group_id']) ? $mainReservation['reservation_group_id'] : null;

    $products = [];
    if ($groupId) {
        $pSql = "SELECT rp.*, COALESCE(g.image_path, '') as gown_image, COALESCE(rp.model_path, g.model_path) as gown_model,
                        u.id_type, u.id_front_image, u.id_back_image, u.email, u.phone
                 FROM reserve_products rp 
                 LEFT JOIN gowns g ON rp.product_id = g.id 
                 LEFT JOIN system_users u ON rp.user_id = u.id
                 WHERE rp.reservation_group_id = ? ORDER BY rp.id ASC";
        $pStmt = $conn->prepare($pSql);
        $pStmt->bind_param("s", $groupId);
        $pStmt->execute();
        $pRes = $pStmt->get_result();
        while ($pRow = $pRes->fetch_assoc()) {
            $products[] = $pRow;
        }
        $pStmt->close();
    }

    if (empty($products)) {
        $products[] = $mainReservation;
    }

    // Resolve payment proof image path from reserve_products or payment_proofs table
    $paymentProofPath = !empty($mainReservation['payment_proof']) ? $mainReservation['payment_proof'] : '';
    if (empty($paymentProofPath)) {
        foreach ($products as $p) {
            if (!empty($p['payment_proof'])) {
                $paymentProofPath = $p['payment_proof'];
                break;
            }
        }
    }
    if (empty($paymentProofPath) && $groupId) {
        $proofStmt = $conn->prepare("SELECT image_path FROM payment_proofs WHERE reservation_group_id = ? ORDER BY id DESC LIMIT 1");
        if ($proofStmt) {
            $proofStmt->bind_param("s", $groupId);
            $proofStmt->execute();
            $proofRes = $proofStmt->get_result();
            if ($proofRow = $proofRes->fetch_assoc()) {
                $paymentProofPath = $proofRow['image_path'];
            }
            $proofStmt->close();
        }
    }
    $mainReservation['payment_proof'] = $paymentProofPath;

    // Resolve lost payment proof image path if not on main reservation
    $lostPaymentProofPath = !empty($mainReservation['lost_payment_proof']) ? $mainReservation['lost_payment_proof'] : '';
    if (empty($lostPaymentProofPath)) {
        foreach ($products as $p) {
            if (!empty($p['lost_payment_proof'])) {
                $lostPaymentProofPath = $p['lost_payment_proof'];
                break;
            }
        }
    }
    $mainReservation['lost_payment_proof'] = $lostPaymentProofPath;

    // Check if any product in group is lost and populate lost fields if missing
    if (empty($mainReservation['is_lost']) || empty($mainReservation['lost_status'])) {
        foreach ($products as $p) {
            if (!empty($p['is_lost']) || !empty($p['lost_status'])) {
                $mainReservation['is_lost'] = $p['is_lost'] ?? 1;
                $mainReservation['lost_status'] = $p['lost_status'] ?? 'requested';
                if (empty($mainReservation['lost_reason']) && !empty($p['lost_reason'])) {
                    $mainReservation['lost_reason'] = $p['lost_reason'];
                }
                if (empty($mainReservation['lost_selling_price']) && !empty($p['lost_selling_price'])) {
                    $mainReservation['lost_selling_price'] = $p['lost_selling_price'];
                }
                if (empty($mainReservation['lost_user_paid']) && !empty($p['lost_user_paid'])) {
                    $mainReservation['lost_user_paid'] = $p['lost_user_paid'];
                }
                if (empty($mainReservation['lost_overdue_fee']) && !empty($p['lost_overdue_fee'])) {
                    $mainReservation['lost_overdue_fee'] = $p['lost_overdue_fee'];
                }
                if (empty($mainReservation['lost_total_due']) && !empty($p['lost_total_due'])) {
                    $mainReservation['lost_total_due'] = $p['lost_total_due'];
                }
                break;
            }
        }
    }

    // For each product, fetch its size items and size labels
    foreach ($products as &$prod) {
        $items = [];
        $itemsSql = "SELECT * FROM reserve_products_items WHERE reserve_product_id = ? ORDER BY item_number ASC";
        $itemsStmt = $conn->prepare($itemsSql);
        $itemsStmt->bind_param("i", $prod['id']);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        while ($itemRow = $itemsResult->fetch_assoc()) {
            $items[] = $itemRow;
        }
        $itemsStmt->close();
        $prod['items'] = $items;

        // Fetch size labels for category
        $catName = $prod['category'] ?? '';
        $labelSql = "SELECT size_label_1, size_label_2, size_label_3, size_label_4, size_label_5, size_label_6, size_label_7, size_label_8, size_label_9, size_label_10 FROM categories WHERE LOWER(TRIM(category_name)) = LOWER(TRIM(?)) LIMIT 1";
        $labelStmt = $conn->prepare($labelSql);
        $labelStmt->bind_param("s", $catName);
        $labelStmt->execute();
        $labelResult = $labelStmt->get_result();
        $prod['size_labels'] = [];
        if ($labelRow = $labelResult->fetch_assoc()) {
            for ($i = 1; $i <= 10; $i++) {
                $labelText = $labelRow["size_label_{$i}"] ?? null;
                if ($labelText !== null && $labelText !== '') {
                    $prod['size_labels'][] = ['index' => $i, 'label' => $labelText];
                }
            }
        }
        $labelStmt->close();
    }
    unset($prod);

    echo json_encode([
        'success' => true,
        'reservation' => $mainReservation,
        'products' => $products,
        'items' => $products[0]['items'] ?? []
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
