<?php
if (session_status() === PHP_SESSION_NONE) {
    if (ini_get('session.use_cookies')) {
        $cp = session_get_cookie_params();
        session_set_cookie_params($cp['lifetime'], '/', $cp['domain'], $cp['secure'], $cp['httponly']);
    }
    session_start();
}
require_once __DIR__ . '/../database/db_config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: adminlogin.php');
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// ===== CHAT AJAX ENDPOINT DISPATCHER (Bypasses ProFreeHost 403 Forbidden) =====
if (isset($_REQUEST['chat_action'])) {
    $chatAction = $_REQUEST['chat_action'];
    if ($chatAction === 'get_users') {
        require_once __DIR__ . '/../chat_get_users.php';
        exit();
    }
    if ($chatAction === 'get') {
        require_once __DIR__ . '/../chat_get.php';
        exit();
    }
    if ($chatAction === 'send') {
        require_once __DIR__ . '/../chat_send.php';
        exit();
    }
    if ($chatAction === 'mark_read') {
        require_once __DIR__ . '/../chat_mark_read.php';
        exit();
    }
}

// Ensure ID verification columns exist (0 = pending/unverified, 1 = verified, 2 = rejected)
@$conn->query("ALTER TABLE system_users ADD COLUMN IF NOT EXISTS id_verified TINYINT(1) DEFAULT 0");
@$conn->query("ALTER TABLE system_users ADD COLUMN IF NOT EXISTS id_verified_at DATETIME DEFAULT NULL");

// ===== AJAX Handler: Approve / Reject Customer Uploaded ID =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_customer_id') {
    header('Content-Type: application/json');
    $custId = intval($_POST['user_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';

    if ($custId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
        exit();
    }

    if ($decision === 'approve') {
        $chk = $conn->prepare("SELECT COALESCE(id_front_image, '') AS f, COALESCE(id_back_image, '') AS b FROM system_users WHERE id = ? LIMIT 1");
        $chk->bind_param("i", $custId);
        $chk->execute();
        $chkRow = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$chkRow) {
            echo json_encode(['status' => 'error', 'message' => 'Customer not found.']);
            exit();
        }
        if ($chkRow['f'] === '' && $chkRow['b'] === '') {
            echo json_encode(['status' => 'error', 'message' => 'This customer has not uploaded a valid ID yet.']);
            exit();
        }
        $stmt = $conn->prepare("UPDATE system_users SET id_verified = 1, id_verified_at = NOW() WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE system_users SET id_verified = 2, id_verified_at = NULL WHERE id = ?");
    }
    $stmt->bind_param("i", $custId);
    $ok = $stmt->execute();
    $stmt->close();

    $pendingNow = 0;
    $pRes = $conn->query("SELECT COUNT(*) AS cnt FROM system_users WHERE COALESCE(id_verified, 0) = 0 AND (COALESCE(id_front_image, '') <> '' OR COALESCE(id_back_image, '') <> '')");
    if ($pRes) $pendingNow = (int)$pRes->fetch_assoc()['cnt'];

    echo json_encode([
        'status' => $ok ? 'success' : 'error',
        'message' => $ok ? ($decision === 'approve' ? 'Customer ID verified. Account can now reserve and buy.' : 'Customer ID rejected. Customer must upload a new ID.') : 'Failed to update verification status.',
        'id_verified' => $decision === 'approve' ? 1 : 2,
        'pending_count' => $pendingNow
    ]);
    exit();
}

// ===== AJAX Handler: Fetch Customers for Modal (Daily, Week, Month, All) =====
if (isset($_GET['action']) && in_array($_GET['action'], ['get_customers_details', 'get_daily_customers'])) {
    header('Content-Type: application/json');
    $filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'day';
    $selectedDate = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
        $selectedDate = date('Y-m-d');
    }

    $whereClause = "WHERE 1=1";
    $params = [];
    $types = "";
    $title = "Customer Accounts";

    if ($filter === 'today') {
        $whereClause = "WHERE DATE(u.created_at) = CURDATE()";
        $title = "Customers Registered Today";
    } elseif ($filter === 'yesterday') {
        $whereClause = "WHERE DATE(u.created_at) = CURDATE() - INTERVAL 1 DAY";
        $title = "Customers Registered Yesterday";
    } elseif ($filter === 'week') {
        $whereClause = "WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $title = "Customers Registered in Past 7 Days";
    } elseif ($filter === 'month') {
        $whereClause = "WHERE MONTH(u.created_at) = MONTH(CURDATE()) AND YEAR(u.created_at) = YEAR(CURDATE())";
        $title = "Customers Registered This Month (" . date('F Y') . ")";
    } elseif ($filter === 'all') {
        $whereClause = "WHERE 1=1";
        $title = "All Registered Customer Accounts";
    } elseif ($filter === 'pending_id') {
        $whereClause = "WHERE COALESCE(u.id_verified, 0) = 0 AND (COALESCE(u.id_front_image, '') <> '' OR COALESCE(u.id_back_image, '') <> '')";
        $title = "Pending ID Verification";
    } else {
        $whereClause = "WHERE DATE(u.created_at) = ?";
        $params[] = $selectedDate;
        $types .= "s";
        $title = "Customer Registrations on " . date('F d, Y', strtotime($selectedDate));
    }

    $sql = "
        SELECT u.id, u.first_name, u.middle_name, u.last_name, u.suffix, u.email, u.phone,
               u.province, u.city, u.barangay, u.profile_image, u.created_at,
               u.id_type, u.id_front_image, u.id_back_image,
               COALESCE(u.id_verified, 0) as id_verified,
               DATE_FORMAT(u.created_at, '%b %d, %Y') as joined_date,
               DATE_FORMAT(u.created_at, '%h:%i %p') as time_str,
               COUNT(rp.id) as total_orders
        FROM system_users u
        LEFT JOIN reserve_products rp ON u.id = rp.user_id
        $whereClause
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT 300
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $customers = [];
    while ($row = $res->fetch_assoc()) {
        $name = trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? '') . ' ' . ($row['suffix'] ?? ''));
        if (empty($name)) {
            $name = explode('@', $row['email'])[0];
        }
        $row['display_name'] = $name;
        
        $locationParts = array_filter([$row['barangay'] ?? '', $row['city'] ?? '', $row['province'] ?? '']);
        $row['location'] = !empty($locationParts) ? implode(', ', $locationParts) : 'Not provided';
        
        $customers[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'title' => $title,
        'filter' => $filter,
        'date' => $selectedDate,
        'count' => count($customers),
        'customers' => $customers
    ]);
    exit();
}

// ===== AJAX Handler: Fetch Revenue Growth Data for Realtime & Custom Timeframes =====
if (isset($_GET['action']) && $_GET['action'] === 'get_revenue_growth') {
    header('Content-Type: application/json');
    try {
        $period = $_GET['period'] ?? '6months';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';

        $labels = [];
        $revenue = [];
        $periodLabel = '';

        if ($period === '1day' || $period === 'today') {
            $targetDate = (!empty($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) ? $startDate : date('Y-m-d');
            $periodLabel = ($targetDate === date('Y-m-d')) ? 'Today (' . date('M j, Y') . ')' : '1 Day (' . date('M j, Y', strtotime($targetDate)) . ')';

            $hourlyData = array_fill(0, 24, 0.0);
            $stmt = $conn->prepare("
                SELECT HOUR(COALESCE(reserved_at, CONCAT(reserve_date, ' 00:00:00'))) as hr, COALESCE(SUM(total_price), 0) as rev
                FROM reserve_products
                WHERE order_status = 'completed'
                  AND DATE(COALESCE(reserved_at, reserve_date)) = ?
                GROUP BY hr
            ");
            if ($stmt) {
                $stmt->bind_param("s", $targetDate);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $hr = (int)$row['hr'];
                    if ($hr >= 0 && $hr < 24) {
                        $hourlyData[$hr] = round((float)$row['rev'], 2);
                    }
                }
                $stmt->close();
            }

            for ($h = 0; $h < 24; $h++) {
                $labels[] = date('g A', strtotime("$h:00"));
                $revenue[] = $hourlyData[$h];
            }
        } elseif ($period === '1week' || $period === '7days') {
            $end = date('Y-m-d');
            $start = date('Y-m-d', strtotime('-6 days'));
            $periodLabel = date('M j', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end)) . ' (7 days)';

            $dailyData = [];
            $stmt = $conn->prepare("
                SELECT DATE(COALESCE(reserved_at, reserve_date)) as d, COALESCE(SUM(total_price), 0) as rev
                FROM reserve_products
                WHERE order_status = 'completed'
                  AND COALESCE(reserved_at, reserve_date) >= ?
                  AND COALESCE(reserved_at, reserve_date) <= ?
                GROUP BY d
            ");
            if ($stmt) {
                $sParam = $start . ' 00:00:00';
                $eParam = $end . ' 23:59:59';
                $stmt->bind_param("ss", $sParam, $eParam);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $dailyData[$row['d']] = round((float)$row['rev'], 2);
                }
                $stmt->close();
            }

            for ($i = 6; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $labels[] = date('D, M j', strtotime($d));
                $revenue[] = $dailyData[$d] ?? 0.0;
            }
        } elseif ($period === '1month' || $period === '30days') {
            $end = date('Y-m-d');
            $start = date('Y-m-d', strtotime('-29 days'));
            $periodLabel = date('M j', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end)) . ' (30 days)';

            $dailyData = [];
            $stmt = $conn->prepare("
                SELECT DATE(COALESCE(reserved_at, reserve_date)) as d, COALESCE(SUM(total_price), 0) as rev
                FROM reserve_products
                WHERE order_status = 'completed'
                  AND COALESCE(reserved_at, reserve_date) >= ?
                  AND COALESCE(reserved_at, reserve_date) <= ?
                GROUP BY d
            ");
            if ($stmt) {
                $sParam = $start . ' 00:00:00';
                $eParam = $end . ' 23:59:59';
                $stmt->bind_param("ss", $sParam, $eParam);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $dailyData[$row['d']] = round((float)$row['rev'], 2);
                }
                $stmt->close();
            }

            for ($i = 29; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $labels[] = date('M j', strtotime($d));
                $revenue[] = $dailyData[$d] ?? 0.0;
            }
        } elseif ($period === '1year' || $period === '12months') {
            $periodLabel = date('M Y', strtotime('-11 months')) . ' - ' . date('M Y') . ' (12 months)';
            for ($i = 11; $i >= 0; $i--) {
                $monthStart = date('Y-m-01', strtotime("-$i months"));
                $monthEnd = date('Y-m-t', strtotime("-$i months"));
                $mLabel = date('M Y', strtotime("-$i months"));

                $mRev = 0.0;
                $mRes = $conn->query("
                    SELECT COALESCE(SUM(total_price), 0) as rev 
                    FROM reserve_products 
                    WHERE order_status = 'completed' 
                      AND COALESCE(reserved_at, reserve_date) >= '$monthStart 00:00:00' 
                      AND COALESCE(reserved_at, reserve_date) <= '$monthEnd 23:59:59'
                ");
                if ($mRes && $row = $mRes->fetch_assoc()) {
                    $mRev = round((float)$row['rev'], 2);
                }
                $labels[] = $mLabel;
                $revenue[] = $mRev;
            }
        } elseif ($period === 'custom') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                $startDate = date('Y-m-d', strtotime('-30 days'));
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                $endDate = date('Y-m-d');
            }
            if (strtotime($startDate) > strtotime($endDate)) {
                $tmp = $startDate;
                $startDate = $endDate;
                $endDate = $tmp;
            }

            $diffDays = (int)round((strtotime($endDate) - strtotime($startDate)) / 86400);

            if ($diffDays == 0) {
                // Single day custom date: 24-hour breakdown
                $periodLabel = date('M j, Y', strtotime($startDate));
                $hourlyData = array_fill(0, 24, 0.0);
                $stmt = $conn->prepare("
                    SELECT HOUR(COALESCE(reserved_at, CONCAT(reserve_date, ' 00:00:00'))) as hr, COALESCE(SUM(total_price), 0) as rev
                    FROM reserve_products
                    WHERE order_status = 'completed'
                      AND DATE(COALESCE(reserved_at, reserve_date)) = ?
                    GROUP BY hr
                ");
                if ($stmt) {
                    $stmt->bind_param("s", $startDate);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $hr = (int)$row['hr'];
                        if ($hr >= 0 && $hr < 24) {
                            $hourlyData[$hr] = round((float)$row['rev'], 2);
                        }
                    }
                    $stmt->close();
                }
                for ($h = 0; $h < 24; $h++) {
                    $labels[] = date('g A', strtotime("$h:00"));
                    $revenue[] = $hourlyData[$h];
                }
            } elseif ($diffDays <= 31) {
                // Daily breakdown for up to 31 days
                $periodLabel = date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate)) . ' (' . ($diffDays + 1) . ' days)';
                $dailyData = [];
                $stmt = $conn->prepare("
                    SELECT DATE(COALESCE(reserved_at, reserve_date)) as d, COALESCE(SUM(total_price), 0) as rev
                    FROM reserve_products
                    WHERE order_status = 'completed'
                      AND COALESCE(reserved_at, reserve_date) >= ?
                      AND COALESCE(reserved_at, reserve_date) <= ?
                    GROUP BY d
                ");
                if ($stmt) {
                    $sParam = $startDate . ' 00:00:00';
                    $eParam = $endDate . ' 23:59:59';
                    $stmt->bind_param("ss", $sParam, $eParam);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $dailyData[$row['d']] = round((float)$row['rev'], 2);
                    }
                    $stmt->close();
                }

                $cur = strtotime($startDate);
                $endTs = strtotime($endDate);
                while ($cur <= $endTs) {
                    $d = date('Y-m-d', $cur);
                    $labels[] = date('M j', $cur);
                    $revenue[] = $dailyData[$d] ?? 0.0;
                    $cur = strtotime('+1 day', $cur);
                }
            } else {
                // Monthly breakdown for ranges > 31 days
                $periodLabel = date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate));
                $monthlyData = [];
                $stmt = $conn->prepare("
                    SELECT DATE_FORMAT(COALESCE(reserved_at, reserve_date), '%Y-%m') as ym, COALESCE(SUM(total_price), 0) as rev
                    FROM reserve_products
                    WHERE order_status = 'completed'
                      AND COALESCE(reserved_at, reserve_date) >= ?
                      AND COALESCE(reserved_at, reserve_date) <= ?
                    GROUP BY ym
                ");
                if ($stmt) {
                    $sParam = $startDate . ' 00:00:00';
                    $eParam = $endDate . ' 23:59:59';
                    $stmt->bind_param("ss", $sParam, $eParam);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $monthlyData[$row['ym']] = round((float)$row['rev'], 2);
                    }
                    $stmt->close();
                }

                $cur = strtotime(date('Y-m-01', strtotime($startDate)));
                $endTs = strtotime(date('Y-m-01', strtotime($endDate)));
                while ($cur <= $endTs) {
                    $ym = date('Y-m', $cur);
                    $labels[] = date('M Y', $cur);
                    $revenue[] = $monthlyData[$ym] ?? 0.0;
                    $cur = strtotime('+1 month', $cur);
                }
            }
        } else {
            // Default: 6 months
            $periodLabel = date('M', strtotime('-5 months')) . ' - ' . date('M') . ' (6 months)';
            for ($i = 5; $i >= 0; $i--) {
                $monthStart = date('Y-m-01', strtotime("-$i months"));
                $monthEnd = date('Y-m-t', strtotime("-$i months"));
                $monthLabel = date('M', strtotime("-$i months"));

                $mRev = 0.0;
                $mRes = $conn->query("
                    SELECT COALESCE(SUM(total_price), 0) as rev 
                    FROM reserve_products 
                    WHERE order_status = 'completed' 
                      AND COALESCE(reserved_at, reserve_date) >= '$monthStart 00:00:00' 
                      AND COALESCE(reserved_at, reserve_date) <= '$monthEnd 23:59:59'
                ");
                if ($mRes && $row = $mRes->fetch_assoc()) {
                    $mRev = round((float)$row['rev'], 2);
                }
                $labels[] = $monthLabel;
                $revenue[] = $mRev;
            }
        }

        $totalPeriod = array_sum($revenue);
        $maxVal = count($revenue) > 0 ? max($revenue) : 0;
        if ($maxVal < 100) $maxVal = 100;
        else $maxVal = ceil($maxVal / 100) * 100;

        echo json_encode([
            'status' => 'success',
            'period' => $period,
            'period_label' => $periodLabel,
            'labels' => $labels,
            'revenue' => $revenue,
            'total_revenue' => $totalPeriod,
            'formatted_total' => '₱' . number_format($totalPeriod, 2),
            'max_val' => $maxVal
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ===== Fetch Real Stats =====

// Total Products
$totalProducts = 0;
$result = $conn->query("SELECT COUNT(*) as cnt FROM gowns");
if ($result) $totalProducts = (int)$result->fetch_assoc()['cnt'];

// Total Reservations
$totalReservations = 0;
$result = $conn->query("SELECT COUNT(*) as cnt FROM reserve_products");
if ($result) $totalReservations = (int)$result->fetch_assoc()['cnt'];

// Reservations by status
$pendingCount = 0; $confirmedCount = 0; $completedCount = 0; $cancelledCount = 0;
$statusResult = $conn->query("SELECT order_status, COUNT(*) as cnt FROM reserve_products GROUP BY order_status");
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        switch ($row['order_status']) {
            case 'pending': $pendingCount = (int)$row['cnt']; break;
            case 'confirmed': $confirmedCount = (int)$row['cnt']; break;
            case 'completed': $completedCount = (int)$row['cnt']; break;
            case 'cancelled': $cancelledCount = (int)$row['cnt']; break;
        }
    }
}

// Selling = completed reservations
$sellingCount = $completedCount;

// Total Revenue (from completed reservations total_price)
$totalRevenue = 0;
$revResult = $conn->query("SELECT COALESCE(SUM(total_price), 0) as revenue FROM reserve_products WHERE order_status = 'completed'");
if ($revResult) $totalRevenue = (float)$revResult->fetch_assoc()['revenue'];

// Customer Stats (Total, Today/In a Day, Yesterday, Week, Month, 7-Day Trend)
$totalCustomers = 0;
$custResult = $conn->query("SELECT COUNT(*) as cnt FROM system_users");
if ($custResult) $totalCustomers = (int)$custResult->fetch_assoc()['cnt'];

// New Customers Today (in a day)
$newCustomersToday = 0;
$todayCustResult = $conn->query("SELECT COUNT(*) as cnt FROM system_users WHERE DATE(created_at) = CURDATE()");
if ($todayCustResult) $newCustomersToday = (int)$todayCustResult->fetch_assoc()['cnt'];

// New Customers Yesterday
$newCustomersYesterday = 0;
$yestCustResult = $conn->query("SELECT COUNT(*) as cnt FROM system_users WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY");
if ($yestCustResult) $newCustomersYesterday = (int)$yestCustResult->fetch_assoc()['cnt'];

// New Customers in the Past 7 Days
$newCustomersWeek = 0;
$weekCustResult = $conn->query("SELECT COUNT(*) as cnt FROM system_users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($weekCustResult) $newCustomersWeek = (int)$weekCustResult->fetch_assoc()['cnt'];

// New Customers This Month
$newCustomersMonth = 0;
$monthCustResult = $conn->query("SELECT COUNT(*) as cnt FROM system_users WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
if ($monthCustResult) $newCustomersMonth = (int)$monthCustResult->fetch_assoc()['cnt'];

// Accounts with uploaded ID waiting for admin verification
$pendingIdCount = 0;
$pendingIdRes = $conn->query("SELECT COUNT(*) as cnt FROM system_users WHERE COALESCE(id_verified, 0) = 0 AND (COALESCE(id_front_image, '') <> '' OR COALESCE(id_back_image, '') <> '')");
if ($pendingIdRes) $pendingIdCount = (int)$pendingIdRes->fetch_assoc()['cnt'];

// Accounts that have not uploaded any ID yet
$noIdCount = 0;
$noIdRes = $conn->query("SELECT COUNT(*) as cnt FROM system_users WHERE COALESCE(id_front_image, '') = '' AND COALESCE(id_back_image, '') = ''");
if ($noIdRes) $noIdCount = (int)$noIdRes->fetch_assoc()['cnt'];

// Daily breakdown for the last 7 days
$dailyMap = [];
$dailyRes = $conn->query("
    SELECT DATE(created_at) as reg_date, COUNT(*) as cnt 
    FROM system_users 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
");
if ($dailyRes) {
    while ($r = $dailyRes->fetch_assoc()) {
        $dailyMap[$r['reg_date']] = (int)$r['cnt'];
    }
}
$dailyCustomerStats = [];
for ($d = 6; $d >= 0; $d--) {
    $tDate = date('Y-m-d', strtotime("-$d days"));
    $dailyCustomerStats[] = [
        'date' => $tDate,
        'label' => ($d === 0) ? 'Today' : (($d === 1) ? 'Yesterday' : date('M d', strtotime("-$d days"))),
        'day' => ($d === 0) ? 'Today' : date('D', strtotime("-$d days")),
        'count' => $dailyMap[$tDate] ?? 0,
        'is_today' => ($d === 0)
    ];
}

// Initial customers registered today (or latest if 0 for demonstration)
$todayCustomersList = [];
$todayListRes = $conn->query("
    SELECT id, first_name, middle_name, last_name, email, phone, created_at,
           DATE_FORMAT(created_at, '%h:%i %p') as time_str
    FROM system_users
    WHERE DATE(created_at) = CURDATE()
    ORDER BY created_at DESC
");
if ($todayListRes) {
    while ($r = $todayListRes->fetch_assoc()) {
        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        if (empty($name)) {
            $name = explode('@', $r['email'])[0];
        }
        $r['display_name'] = $name;
        $todayCustomersList[] = $r;
    }
}

// Customer Satisfaction (average rating from product_reviews)
$avgRating = 0;
$totalReviews = 0;
$reviewResult = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM product_reviews");
if ($reviewResult) {
    $reviewData = $reviewResult->fetch_assoc();
    $avgRating = $reviewData['avg_rating'] ? round((float)$reviewData['avg_rating'], 1) : 0;
    $totalReviews = (int)$reviewData['cnt'];
}

// Low stock threshold from system_settings
$lowStockThreshold = 2;
$stRes = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold' LIMIT 1");
if ($stRes && $stRow = $stRes->fetch_assoc()) {
    $lowStockThreshold = max(1, intval($stRow['setting_value']));
}

// Low stock products (stock <= $lowStockThreshold and stock > 0)
$lowStockProducts = [];
$lowStockResult = $conn->query("SELECT id, name, stock, category FROM gowns WHERE stock <= $lowStockThreshold AND stock > 0 ORDER BY stock ASC LIMIT 10");
if ($lowStockResult) {
    while ($row = $lowStockResult->fetch_assoc()) {
        $lowStockProducts[] = $row;
    }
}

// Total stock and out-of-stock count
$totalStock = 0;
$outOfStockCount = 0;
$stockResult = $conn->query("SELECT COALESCE(SUM(stock), 0) as total_stock, COUNT(CASE WHEN stock = 0 THEN 1 END) as out_of_stock FROM gowns");
if ($stockResult) {
    $stockData = $stockResult->fetch_assoc();
    $totalStock = (int)$stockData['total_stock'];
    $outOfStockCount = (int)$stockData['out_of_stock'];
}

// Top reserved products
$topProducts = [];
$topResult = $conn->query("
    SELECT product_name, COUNT(*) as times_reserved, SUM(total_price) as revenue
    FROM reserve_products
    GROUP BY product_name
    ORDER BY times_reserved DESC
    LIMIT 5
");
if ($topResult) {
    while ($row = $topResult->fetch_assoc()) {
        $topProducts[] = $row;
    }
}

// Category distribution
$categoryStats = [];
$catResult = $conn->query("SELECT category, COUNT(*) as cnt FROM gowns GROUP BY category ORDER BY cnt DESC");
if ($catResult) {
    while ($row = $catResult->fetch_assoc()) {
        $categoryStats[] = $row;
    }
}

// Recent reviews
$recentReviews = [];
$reviewRecentResult = $conn->query("
    SELECT r.rating, r.review_text, r.created_at, g.name as product_name,
           u.first_name, u.last_name
    FROM product_reviews r
    LEFT JOIN gowns g ON r.product_id = g.id
    LEFT JOIN system_users u ON r.user_id = u.id
    ORDER BY r.created_at DESC
    LIMIT 3
");
if ($reviewRecentResult) {
    while ($row = $reviewRecentResult->fetch_assoc()) {
        $recentReviews[] = $row;
    }
}

// Recent reservations
$recentReservations = [];
$recentResult = $conn->query("
    SELECT rp.id, rp.product_name, rp.category, rp.total_price, rp.order_status, rp.reserved_at,
           u.first_name, u.last_name
    FROM reserve_products rp
    LEFT JOIN system_users u ON rp.user_id = u.id
    ORDER BY rp.reserved_at DESC
    LIMIT 5
");
if ($recentResult) {
    while ($row = $recentResult->fetch_assoc()) {
        $recentReservations[] = $row;
    }
}

// Today's Calendar Schedule (Reservations & Purchases)
$todayDate = date('Y-m-d');
$todayDay = date('j');
$todayMonthName = date('F');
$todayMonthShort = date('M');
$todayYear = date('Y');
$todayWeekday = date('l');

$todayOrders = [];
$todayReserveCount = 0;
$todayBuyCount = 0;

$todayQuery = "
    SELECT rp.id, rp.product_name, rp.product_status, rp.payment_method, rp.order_status, 
           rp.reserve_date, rp.return_date, rp.total_price, rp.quantity,
           u.first_name, u.last_name
    FROM reserve_products rp
    LEFT JOIN system_users u ON rp.user_id = u.id
    WHERE rp.reserve_date IS NOT NULL AND rp.reserve_date != '0000-00-00' AND rp.reserve_date != ''
      AND (rp.order_status != 'cancelled' OR rp.order_status IS NULL)
      AND rp.reserve_date <= ? 
      AND (COALESCE(NULLIF(rp.return_date, '0000-00-00'), NULLIF(rp.return_date, ''), rp.reserve_date) >= ?)
    ORDER BY rp.id DESC
    LIMIT 20
";
$tStmt = $conn->prepare($todayQuery);
if ($tStmt) {
    $tStmt->bind_param("ss", $todayDate, $todayDate);
    $tStmt->execute();
    $tRes = $tStmt->get_result();
    while ($r = $tRes->fetch_assoc()) {
        $isB = (($r['product_status'] ?? '') === 'buy' || ($r['payment_method'] ?? '') === 'buy');
        if ($isB) {
            $todayBuyCount++;
        } else {
            $todayReserveCount++;
        }
        $todayOrders[] = $r;
    }
    $tStmt->close();
}
$todayTotalOrders = count($todayOrders);

// Monthly revenue for chart (last 6 months)
$monthlyRevenue = [];
$monthlyLabels = [];
for ($i = 5; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd = date('Y-m-t', strtotime("-$i months"));
    $monthLabel = date('M', strtotime("-$i months"));

    $monthRev = 0;
    $mResult = $conn->query("SELECT COALESCE(SUM(total_price), 0) as rev FROM reserve_products WHERE order_status = 'completed' AND reserved_at >= '$monthStart' AND reserved_at <= '$monthEnd 23:59:59'");
    if ($mResult) $monthRev = (float)$mResult->fetch_assoc()['rev'];

    $monthlyLabels[] = $monthLabel;
    $monthlyRevenue[] = round($monthRev, 2);
}
$totalRevenuePeriod = array_sum($monthlyRevenue);

// Format revenue for display
function formatRevenue($amount) {
    if ($amount >= 1000000) {
        return number_format($amount / 1000000, 1) . 'M';
    } elseif ($amount >= 1000) {
        return number_format($amount / 1000, 1) . 'K';
    }
    return number_format($amount, 0);
}

$revenueDisplay = formatRevenue($totalRevenue);
$revenueSuffix = '';
if ($totalRevenue >= 1000000) $revenueSuffix = 'M';
elseif ($totalRevenue >= 1000) $revenueSuffix = 'K';

// Chart max
$chartMax = max($monthlyRevenue);
if ($chartMax < 100) $chartMax = 100;
else $chartMax = ceil($chartMax / 100) * 100;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-modern.css">
    <link rel="stylesheet" href="../css/admin_mobile_css.css">
    <link id="dynamicFavicon" rel="icon" href="../images/icon/JMB.png" type="image/jpeg">
    <style>
    /* ===== Customer ID Verification (Pending) ===== */
    .cust-header-badge-wrapper { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .cust-pending-id-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 12px; border-radius: 999px; cursor: pointer;
        font-size: 0.78rem; color: #9ca3af;
        background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.2s ease;
    }
    .cust-pending-id-badge strong { color: #e5e7eb; }
    .cust-pending-id-badge.has-pending {
        color: #fbbf24; background: rgba(251,191,36,0.1); border-color: rgba(251,191,36,0.45);
        animation: pendingIdPulse 2.2s infinite ease-in-out;
    }
    .cust-pending-id-badge.has-pending strong { color: #fde68a; }
    .cust-pending-id-badge:hover { transform: translateY(-1px); border-color: #ffd700; }
    @keyframes pendingIdPulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(251,191,36,0.35); }
        50% { box-shadow: 0 0 0 5px rgba(251,191,36,0); }
    }

    .cust-pending-id-strip {
        display: flex; align-items: center; gap: 14px;
        margin: 14px 0 4px; padding: 12px 16px; border-radius: 12px; cursor: pointer;
        background: linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
        border: 1px solid rgba(255,255,255,0.08);
        transition: all 0.25s ease;
    }
    .cust-pending-id-strip.has-pending {
        background: linear-gradient(135deg, rgba(251,191,36,0.12), rgba(124,99,0,0.06));
        border-color: rgba(251,191,36,0.4);
    }
    .cust-pending-id-strip:hover { transform: translateY(-2px); border-color: #ffd700; box-shadow: 0 6px 18px rgba(255,215,0,0.12); }
    .cust-pending-id-icon {
        width: 40px; height: 40px; flex-shrink: 0; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,0.06); color: #9ca3af; font-size: 1.1rem;
    }
    .cust-pending-id-strip.has-pending .cust-pending-id-icon { background: rgba(251,191,36,0.18); color: #fbbf24; }
    .cust-pending-id-text { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
    .cust-pending-id-label { color: #fff; font-weight: 700; font-size: 0.9rem; }
    .cust-pending-id-sub { color: #9ca3af; font-size: 0.74rem; line-height: 1.35; }
    .cust-pending-id-count { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
    .cust-pending-id-count > span:first-child { font-size: 1.5rem; font-weight: 800; color: #e5e7eb; line-height: 1; }
    .cust-pending-id-strip.has-pending .cust-pending-id-count > span:first-child { color: #fbbf24; }
    .cust-pending-id-cta { font-size: 0.7rem; color: #ffd700; font-weight: 600; white-space: nowrap; }

    .cust-id-status {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 8px; border-radius: 999px; font-size: 0.68rem; font-weight: 700;
        margin-left: 6px; white-space: nowrap; vertical-align: middle;
    }
    .cust-id-status.pending  { color: #fbbf24; background: rgba(251,191,36,0.12); border: 1px solid rgba(251,191,36,0.4); }
    .cust-id-status.verified { color: #34d399; background: rgba(52,211,153,0.12); border: 1px solid rgba(52,211,153,0.4); }
    .cust-id-status.rejected { color: #f87171; background: rgba(248,113,113,0.12); border: 1px solid rgba(248,113,113,0.4); }
    .cust-id-status.no-id    { color: #9ca3af; background: rgba(156,163,175,0.1); border: 1px solid rgba(156,163,175,0.3); }

    .cust-btn-verify {
        display: inline-flex; align-items: center; justify-content: center; gap: 5px;
        padding: 6px 11px; border-radius: 8px; font-size: 0.74rem; font-weight: 700;
        cursor: pointer; border: 1px solid transparent; transition: all 0.2s ease;
    }
    .cust-btn-verify.approve { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
    .cust-btn-verify.approve:hover { box-shadow: 0 4px 12px rgba(16,185,129,0.4); transform: translateY(-1px); }
    .cust-btn-verify.reject { background: rgba(248,113,113,0.1); color: #f87171; border-color: rgba(248,113,113,0.45); }
    .cust-btn-verify.reject:hover { background: rgba(248,113,113,0.2); }
    .cust-btn-verify:disabled { opacity: 0.6; cursor: wait; transform: none; }

    .cust-id-verify-footer {
        display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;
        padding: 12px 18px; border-top: 1px solid rgba(255,255,255,0.08);
    }
    .cust-id-verify-footer .cust-id-status { margin-left: 0; font-size: 0.76rem; padding: 4px 10px; }
    .cust-id-verify-btns { display: flex; gap: 8px; }
    .cust-id-verify-footer .cust-btn-verify { padding: 8px 14px; font-size: 0.8rem; }

    @media (max-width: 768px) {
        .cust-pending-id-strip { padding: 10px 12px; gap: 10px; }
        .cust-pending-id-sub { display: none; }
    }

    /* ===== Custom Confirmation Modal (Reject ID) ===== */
    .custom-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.82);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        z-index: 100020;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
        box-sizing: border-box;
    }
    .custom-modal-overlay.active {
        display: flex;
    }
    .reject-confirm-modal-box {
        background: #18191f;
        border: 1px solid rgba(239, 68, 68, 0.45);
        border-radius: 14px;
        width: 100%;
        max-width: 440px;
        box-shadow: 0 16px 45px rgba(0, 0, 0, 0.85), 0 0 25px rgba(239, 68, 68, 0.2);
        overflow: hidden;
        animation: modalFadeIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
    }
    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.95) translateY(-10px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .reject-modal-header {
        padding: 16px 20px;
        background: #2a1515;
        border-bottom: 1px solid rgba(239, 68, 68, 0.35);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .reject-modal-header h3 {
        color: #ff6b6b;
        margin: 0;
        font-size: 1.02rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .modal-close-btn {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: #ffffff;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 14px;
        padding: 0;
        transition: all 0.2s ease;
        box-sizing: border-box;
        flex-shrink: 0;
        line-height: 1;
    }
    .modal-close-btn:hover {
        background: rgba(220, 53, 69, 0.85);
        border-color: rgba(220, 53, 69, 1);
        color: #ffffff;
        transform: rotate(90deg);
    }
    .reject-modal-icon-wrap {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        background: rgba(239, 68, 68, 0.14);
        border: 1px solid rgba(239, 68, 68, 0.45);
        color: #ef4444;
        font-size: 26px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 14px;
        position: relative;
    }
    .reject-modal-icon-badge {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #dc2626;
        color: #fff;
        font-size: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #18191f;
    }
    .reject-modal-notice {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 8px;
        padding: 11px 14px;
        color: #e5e5e5;
        font-size: 0.82rem;
        line-height: 1.45;
        text-align: left;
        display: flex;
        align-items: flex-start;
        gap: 9px;
    }
    .custom-modal-footer {
        padding: 14px 20px;
        background: #16161a;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
    }
    .btn-modal-cancel {
        background: #2a2a2e;
        color: #ccc;
        border: 1px solid #444;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.84rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-modal-cancel:hover {
        background: #38383e;
        color: #fff;
    }
    .btn-modal-reject {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff;
        border: 1px solid #ef4444;
        padding: 8px 18px;
        border-radius: 8px;
        font-size: 0.84rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);
    }
    .btn-modal-reject:hover {
        background: linear-gradient(135deg, #f87171, #ef4444);
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(239, 68, 68, 0.5);
    }

    /* ===== Admin Chat Floating Logo Button (Top-Right) ===== */
    #adminChatIconBtn {
        position: fixed;
        top: 18px;
        right: 22px;
        z-index: 12000;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #141414;
        border: 2px solid #7c6300;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.6), 0 0 10px rgba(124, 99, 0, 0.25);
        transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        padding: 0;
        outline: none;
        user-select: none;
    }

    #adminChatIconBtn:hover {
        transform: translateY(-2px) scale(1.08);
        border-color: #ffd700;
        box-shadow: 0 6px 24px rgba(255, 215, 0, 0.4), 0 0 14px rgba(255, 215, 0, 0.25);
        background: #1c1c1c;
    }

    #adminChatIconBtn:active {
        transform: translateY(0) scale(0.95);
    }

    #adminChatIconBtn img {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        pointer-events: none;
        transition: transform 0.2s ease;
    }

    #adminChatIconBtn:hover img {
        transform: rotate(5deg);
    }

    /* Unread Badge */
    #adminChatTotalBadge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        border-radius: 999px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #ffffff;
        font-size: 10px;
        font-weight: 700;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #000000;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.65);
        pointer-events: none;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        animation: pulseChatBadge 2s infinite ease-in-out;
    }

    @keyframes pulseChatBadge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.12); }
    }

    /* Chat Indicator Hint Icon */
    .admin-chat-hint-dot {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 14px;
        height: 14px;
        background: #ffd700;
        border-radius: 50%;
        border: 2px solid #141414;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 7px;
        color: #000;
        box-shadow: 0 1px 4px rgba(0,0,0,0.5);
        pointer-events: none;
    }

    /* Mobile responsive adjustments */
    @media (max-width: 768px) {
        #adminChatIconBtn {
            width: 42px !important;
            height: 42px !important;
            top: 14px !important;
            right: 14px !important;
        }
        #adminChatIconBtn img {
            width: 32px !important;
            height: 32px !important;
        }
        #adminChatTotalBadge {
            min-width: 17px !important;
            height: 17px !important;
            font-size: 9px !important;
            top: -3px !important;
            right: -3px !important;
        }
        #adminChatPanel.mobile-convo-active #adminChatUserList {
            display: none !important;
        }
        #adminChatPanel.mobile-convo-active #adminChatBackBtn {
            display: inline-flex !important;
        }
        #adminChatPanel.mobile-convo-active #adminChatConvo {
            height: 100% !important;
        }
    }

    /* ===== Revenue Growth Timeframe & Custom Date Controls ===== */
    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
        position: relative;
        flex-wrap: wrap;
    }

    .chart-header-left {
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 0;
    }

    .chart-title-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chart-title-wrap h3 {
        margin: 0;
        font-size: 0.92rem;
        font-weight: 700;
        color: #ffffff;
        letter-spacing: 0.3px;
    }

    .chart-meta-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .chart-period {
        color: #a0a0a0;
        font-size: 0.72rem;
    }

    .chart-period-total {
        color: #ffd700;
        font-size: 0.72rem;
        font-weight: 600;
        background: rgba(255, 215, 0, 0.1);
        padding: 1px 7px;
        border-radius: 4px;
        border: 1px solid rgba(255, 215, 0, 0.25);
    }

    .chart-controls-wrap {
        position: relative;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .chart-timeframe-btns {
        display: inline-flex;
        align-items: center;
        background: rgba(0, 0, 0, 0.35);
        padding: 2px;
        border-radius: 8px;
        border: 1px solid rgba(255, 215, 0, 0.2);
        gap: 2px;
    }

    .timeframe-btn {
        background: transparent;
        border: none;
        color: #aaaaaa;
        padding: 4px 8px;
        font-size: 0.72rem;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        line-height: 1.2;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .timeframe-btn:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.08);
    }

    .timeframe-btn.active {
        color: #000000 !important;
        background: #ffd700 !important;
        font-weight: 700;
        box-shadow: 0 0 10px rgba(255, 215, 0, 0.4);
    }

    /* Custom Date Range Popover */
    .custom-date-popover {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        z-index: 100;
        background: #18191f;
        border: 1px solid rgba(255, 215, 0, 0.35);
        border-radius: 10px;
        padding: 10px 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
    }

    .custom-date-inner {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }

    .custom-date-field {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .custom-date-field label {
        font-size: 0.65rem;
        color: #ffd700;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .custom-date-field input[type="date"] {
        background: #0d0e12;
        border: 1px solid rgba(255, 215, 0, 0.25);
        color: #ffffff;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        outline: none;
        transition: border-color 0.2s;
        color-scheme: dark;
    }

    .custom-date-field input[type="date"]:focus {
        border-color: #ffd700;
        box-shadow: 0 0 6px rgba(255, 215, 0, 0.3);
    }

    .custom-date-apply-btn {
        background: #ffd700;
        color: #000000;
        border: none;
        border-radius: 6px;
        padding: 5px 10px;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.2s;
        height: 28px;
    }

    .custom-date-apply-btn:hover {
        background: #ffe24d;
        transform: translateY(-1px);
    }

    .custom-date-close-btn {
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: #aaaaaa;
        border-radius: 6px;
        padding: 5px 8px;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.2s;
        height: 28px;
    }

    .custom-date-close-btn:hover {
        color: #ffffff;
        border-color: #ffffff;
    }

    @media (max-width: 768px) {
        .chart-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        .chart-controls-wrap {
            width: 100%;
        }
        .chart-timeframe-btns {
            width: 100%;
            justify-content: space-between;
            overflow-x: auto;
        }
        .custom-date-popover {
            left: 0;
            right: auto;
            width: 100%;
            max-width: 320px;
        }
    }

    /* Stat items header badge: gold text instead of green */
    .stats-overview .stat-change,
    .stats-overview .stat-change.positive {
        color: #ffd700 !important;
    }
    </style>
    <script src="../js/dark-mode.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Preload audio file to eliminate delay
    const buttonClickAudio = new Audio('../ringtone/button_click.MP3');
    buttonClickAudio.volume = 0.3;
    buttonClickAudio.preload = 'auto';
    
    // Button click sound function
    function playButtonClickSound() {
        buttonClickAudio.currentTime = 0; // Reset to start
        buttonClickAudio.play().catch(e => console.log('Audio play failed:', e));
    }

    // Counting animation function
    function animateNumber(element, targetValue, duration = 2000, prefix = '', suffix = '', isDecimal = false) {
        const startValue = 0;
        const startTime = performance.now();
        
        function updateNumber(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function for smooth animation
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const currentValue = startValue + (targetValue - startValue) * easeOutQuart;
            
            if (isDecimal) {
                element.textContent = prefix + currentValue.toFixed(1) + suffix;
            } else {
                element.textContent = prefix + Math.floor(currentValue).toLocaleString() + suffix;
            }
            
            if (progress < 1) {
                requestAnimationFrame(updateNumber);
            }
        }
        
        requestAnimationFrame(updateNumber);
    }

    // Initialize counting animations
    function initCountAnimations() {
        const totalProductsElement = document.querySelector('.stat-item:nth-child(1) .stat-number');
        if (totalProductsElement) {
            animateNumber(totalProductsElement, <?php echo $totalProducts; ?>, 1500);
        }
        
        const reservationsElement = document.querySelector('.stat-item:nth-child(2) .stat-number');
        if (reservationsElement) {
            animateNumber(reservationsElement, <?php echo $totalReservations; ?>, 1800);
        }
        
        const sellingElement = document.querySelector('.stat-item:nth-child(3) .stat-number');
        if (sellingElement) {
            animateNumber(sellingElement, <?php echo $sellingCount; ?>, 1600);
        }

        const customersElement = document.querySelector('.stat-item:nth-child(4) .stat-number');
        if (customersElement) {
            animateNumber(customersElement, <?php echo $totalCustomers; ?>, 1700);
        }
        
        const satisfactionElement = document.querySelector('.stat-item:nth-child(5) .stat-number');
        if (satisfactionElement) {
            animateNumber(satisfactionElement, <?php echo $avgRating; ?>, 2000, '', '/5.00', true);
        }
        
        const revenueElement = document.querySelector('.stat-item:nth-child(6) .stat-number');
        if (revenueElement) {
            animateNumber(revenueElement, <?php echo $totalRevenue >= 1000 ? number_format($totalRevenue / 1000, 1) : number_format($totalRevenue, 0); ?>, 2200, '₱', '<?php echo $revenueSuffix; ?>', <?php echo $totalRevenue >= 1000 ? 'true' : 'false'; ?>);
        }
    }

    // Sync heights of Revenue Growth, Today's Schedule, and Order Status responsive to screen height
    function syncDashboardWidgetHeights() {
        const revEl = document.querySelector('.revenue-chart-container');
        const calEl = document.querySelector('.calendar-today-widget');
        const stEl = document.querySelector('.status-widget');
        const custWidget = document.querySelector('.customers-widget');

        if (window.innerWidth <= 1200) {
            [revEl, calEl, stEl, custWidget].forEach(el => {
                if (el) el.style.height = '';
            });
            return;
        }

        if (!custWidget) return;
        custWidget.style.height = '';

        // Responsive height based on available screen viewport height
        const overhead = 184;
        const availableHeight = window.innerHeight - overhead;
        const screenDependentHeight = Math.floor(availableHeight / 2);

        const targetHeight = Math.max(215, Math.max(custWidget.offsetHeight, screenDependentHeight));
        const heightStr = targetHeight + 'px';

        if (revEl) revEl.style.height = heightStr;
        if (calEl) calEl.style.height = heightStr;
        if (stEl) stEl.style.height = heightStr;
        custWidget.style.height = heightStr;
    }

    // Add click sound to all buttons and links
    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('button, .btn, .btn-submit, .btn-back, a[href]');
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                // Don't play sound for sidebar links (handled by sidebar.php)
                if (!this.closest('.sidebar')) {
                    playButtonClickSound();
                }
            });
        });
        
        // Start counting animations & sync heights
        setTimeout(initCountAnimations, 500);
        syncDashboardWidgetHeights();
        setTimeout(syncDashboardWidgetHeights, 600);
    });

    window.addEventListener('load', function() {
        syncDashboardWidgetHeights();
        setTimeout(syncDashboardWidgetHeights, 300);
    });
    window.addEventListener('resize', syncDashboardWidgetHeights);
    </script>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- Admin Chat Floating Logo Button (Top-Right) -->
    <button type="button" id="adminChatIconBtn" class="admin-chat-icon-btn" onclick="toggleAdminChatPanel()" title="Customer Chats (Click to open)" aria-label="Open Customer Chats">
        <img src="../images/icon/JMB.png" alt="ClosetGoo Logo" onerror="this.src='../images/icon/chat.png'">
        <span class="admin-chat-hint-dot"><i class="fas fa-comment"></i></span>
        <span id="adminChatTotalBadge" class="admin-chat-total-badge" style="display: none;">0</span>
    </button>

    <div class="shell dashboard-shell">
        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stat-item">
                <div class="stat-header">
                    <span class="stat-title">Total Products</span>
                    <span class="stat-change positive"><?php echo $totalProducts; ?> items</span>
                </div>
                <div class="stat-content">
                    <p class="stat-number">0</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-header">
                    <span class="stat-title">Reservations</span>
                    <span class="stat-change positive"><?php echo $totalReservations; ?> total</span>
                </div>
                <div class="stat-content">
                    <p class="stat-number">0</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-header">
                    <span class="stat-title">Selling</span>
                    <span class="stat-change positive"><?php echo $sellingCount; ?> sold</span>
                </div>
                <div class="stat-content">
                    <p class="stat-number">0</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-header">
                    <span class="stat-title">Customers</span>
                    <span class="stat-change positive"><?php echo $newCustomersToday > 0 ? "+{$newCustomersToday} today" : "0 today"; ?></span>
                </div>
                <div class="stat-content">
                    <p class="stat-number">0</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-header">
                    <span class="stat-title">Customer Satisfaction</span>
                    <span class="stat-change positive"><?php echo $totalReviews; ?> reviews</span>
                </div>
                <div class="stat-content">
                    <p class="stat-number">0.0/5.00</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-header">
                    <span class="stat-title">Revenue</span>
                    <span class="stat-change positive">Completed</span>
                </div>
                <div class="stat-content">
                    <p class="stat-number">₱0K</p>
                </div>
            </div>
        </div>

        <!-- Row 1: Revenue Growth Chart (Cols 1-4) -->
        <div class="revenue-chart-container">
            <div class="chart-header">
                <div class="chart-header-left">
                    <div class="chart-title-wrap">
                        <h3>Revenue Growth</h3>
                    </div>
                    <div class="chart-meta-row">
                        <span class="chart-period" id="revenueChartPeriod"><?php echo $monthlyLabels[0] . ' - ' . $monthlyLabels[5] . ' (6 months)'; ?></span>
                        <span class="chart-period-total" id="revenueChartTotalBadge">Total: ₱<?php echo number_format($totalRevenuePeriod, 2); ?></span>
                    </div>
                </div>
                <div class="chart-controls-wrap">
                    <div class="chart-timeframe-btns">
                        <button type="button" class="timeframe-btn" data-period="1day" onclick="changeRevenueTimeframe('1day')">1D</button>
                        <button type="button" class="timeframe-btn" data-period="1week" onclick="changeRevenueTimeframe('1week')">1W</button>
                        <button type="button" class="timeframe-btn" data-period="1month" onclick="changeRevenueTimeframe('1month')">1M</button>
                        <button type="button" class="timeframe-btn active" data-period="6months" onclick="changeRevenueTimeframe('6months')">6M</button>
                        <button type="button" class="timeframe-btn" data-period="1year" onclick="changeRevenueTimeframe('1year')">1Y</button>
                        <button type="button" class="timeframe-btn custom-btn" data-period="custom" onclick="toggleCustomDatePopover()" title="Custom Date Range">
                            <i class="fas fa-calendar-alt"></i> Custom
                        </button>
                    </div>

                    <!-- Custom Date Range Popover -->
                    <div class="custom-date-popover" id="customDatePopover" style="display: none;">
                        <div class="custom-date-inner">
                            <div class="custom-date-field">
                                <label>From</label>
                                <input type="date" id="revStartDate" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="custom-date-field">
                                <label>To</label>
                                <input type="date" id="revEndDate" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <button type="button" class="custom-date-apply-btn" onclick="applyCustomDateRange()">
                                <i class="fas fa-check"></i> Apply
                            </button>
                            <button type="button" class="custom-date-close-btn" onclick="toggleCustomDatePopover(false)" title="Close">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="chart-wrapper">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Row 1: Today's Schedule Calendar Widget (Cols 5-6, Same Height as Revenue Growth) -->
        <div class="dashboard-widget calendar-today-widget" onclick="window.location.href='calendar.php?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>'" title="Click to open Full Calendar for this month">
            <div class="widget-header">
                <h3><i class="fas fa-calendar-day"></i> Today's Schedule</h3>
            </div>
            
            <div class="cal-today-content">
                <div class="cal-today-info">
                    <div class="cal-today-items-list">
                        <?php if (empty($todayOrders)): ?>
                            <div class="cal-today-empty">
                                <i class="far fa-calendar-check" style="color: #ffd700;"></i>
                                <span>No reserves or buys today</span>
                            </div>
                        <?php else: ?>
                            <?php 
                            $visibleTodayItems = array_slice($todayOrders, 0, 7);
                            $hiddenTodayCount = max(0, count($todayOrders) - 7);
                            foreach ($visibleTodayItems as $ord): 
                                $isB = (($ord['product_status'] ?? '') === 'buy' || ($ord['payment_method'] ?? '') === 'buy');
                                $cName = trim(($ord['first_name'] ?? '') . ' ' . ($ord['last_name'] ?? ''));
                                $st = $ord['order_status'] ?? 'pending';
                            ?>
                                <div class="cal-today-row">
                                    <span class="cal-row-type <?php echo $isB ? 'type-buy' : 'type-res'; ?>">
                                        <?php echo $isB ? 'Buy' : 'Res'; ?>
                                    </span>
                                    <span class="cal-row-name" title="<?php echo htmlspecialchars($ord['product_name'] . ($cName ? ' (' . $cName . ')' : '')); ?>">
                                        <?php echo htmlspecialchars(mb_strimwidth($ord['product_name'], 0, 15, '...')); ?>
                                    </span>
                                    <span class="cal-row-status <?php echo htmlspecialchars($st); ?>">
                                        <?php echo ucfirst($st); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($todayOrders) && $hiddenTodayCount > 0): ?>
                        <div class="cal-today-more">
                            +<?php echo $hiddenTodayCount; ?> more &rarr;
                        </div>
                    <?php endif; ?>
                </div>

                <div class="cal-date-block">
                    <div class="cal-date-month"><?php echo strtoupper($todayMonthShort); ?></div>
                    <div class="cal-date-day"><?php echo $todayDay; ?></div>
                    <div class="cal-date-year"><?php echo $todayYear; ?></div>
                    <div class="cal-date-weekday"><?php echo $todayWeekday; ?></div>
                </div>
            </div>
        </div>

        <!-- Row 2: Customers Summary & Daily Stats (Cols 1-4, Under Revenue Growth) -->
        <div class="dashboard-widget customers-widget">
            <div class="widget-header cust-widget-header">
                <div class="cust-header-title">
                    <h3><i class="fas fa-users"></i> Customers Statistics</h3>
                    <span class="cust-header-subtitle">Daily registration analytics & customer tracking</span>
                </div>
                <div class="cust-header-badge-wrapper">
                    <span class="cust-pending-id-badge <?php echo $pendingIdCount > 0 ? 'has-pending' : ''; ?>" id="headerPendingIdBadge" onclick="openCustomerModal('pending_id', 'Pending ID Verification')" title="Accounts waiting for admin to verify their uploaded ID">
                        <i class="fas fa-user-clock"></i>
                        <strong id="headerPendingIdCount"><?php echo $pendingIdCount; ?></strong> Pending
                    </span>
                    <span class="cust-today-badge <?php echo $newCustomersToday > 0 ? 'has-new' : ''; ?>" id="headerTodayBadge" onclick="openCustomerModal('today', 'New Customers Registered Today')" style="cursor: pointer;" title="Click to view today's registered customers">
                        <i class="fas fa-calendar-day"></i> 
                        <strong>+<?php echo $newCustomersToday; ?></strong> New Today
                    </span>
                </div>
            </div>

            <!-- 4-Metric Grid -->
            <div class="cust-metrics-grid">
                <div class="cust-metric-card highlight-today" onclick="openCustomerModal('today', 'New Customers Registered Today')" title="Click to view today's registered customer accounts">
                    <div class="cust-metric-icon"><i class="fas fa-user-plus"></i></div>
                    <div class="cust-metric-details">
                        <span class="cust-metric-label">New Customers Today</span>
                        <div class="cust-metric-value-row">
                            <span class="cust-metric-value mobile-metric-value" data-value="<?php echo $newCustomersToday; ?>">+<?php echo $newCustomersToday; ?></span>
                            <span class="cust-metric-pill <?php echo $newCustomersToday > 0 ? 'pill-active' : ''; ?>">Today</span>
                        </div>
                        <span class="cust-metric-compare"><i class="fas fa-history"></i> <?php echo $newCustomersYesterday; ?> yesterday</span>
                    </div>
                </div>

                <div class="cust-metric-card" onclick="openCustomerModal('week', 'Customers Registered in the Past 7 Days')" title="Click to view past 7 days customer accounts">
                    <div class="cust-metric-icon"><i class="fas fa-calendar-week"></i></div>
                    <div class="cust-metric-details">
                        <span class="cust-metric-label">Past 7 Days</span>
                        <div class="cust-metric-value-row">
                            <span class="cust-metric-value"><?php echo $newCustomersWeek; ?></span>
                            <span class="cust-metric-pill">7 Days</span>
                        </div>
                        <span class="cust-metric-compare"><i class="fas fa-chart-line"></i> Recent 7-day signups</span>
                    </div>
                </div>

                <div class="cust-metric-card" onclick="openCustomerModal('month', 'Customers Registered This Month (<?php echo date('F Y'); ?>)')" title="Click to view this month's customer accounts">
                    <div class="cust-metric-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="cust-metric-details">
                        <span class="cust-metric-label">This Month</span>
                        <div class="cust-metric-value-row">
                            <span class="cust-metric-value"><?php echo $newCustomersMonth; ?></span>
                            <span class="cust-metric-pill">Month</span>
                        </div>
                        <span class="cust-metric-compare"><i class="fas fa-calendar"></i> <?php echo date('F Y'); ?></span>
                    </div>
                </div>

                <div class="cust-metric-card" onclick="openCustomerModal('all', 'All Registered Customer Accounts')" title="Click to view all registered customer accounts">
                    <div class="cust-metric-icon"><i class="fas fa-users"></i></div>
                    <div class="cust-metric-details">
                        <span class="cust-metric-label">Total Customers</span>
                        <div class="cust-metric-value-row">
                            <span class="cust-metric-value"><?php echo $totalCustomers; ?></span>
                            <span class="cust-metric-pill">Total</span>
                        </div>
                        <span class="cust-metric-compare"><i class="fas fa-id-badge"></i> All-time accounts</span>
                    </div>
                </div>
            </div>

            <!-- Pending ID Verification Strip -->
            <div class="cust-pending-id-strip <?php echo $pendingIdCount > 0 ? 'has-pending' : ''; ?>" id="custPendingIdStrip" onclick="openCustomerModal('pending_id', 'Pending ID Verification')" title="Click to review and verify uploaded IDs">
                <div class="cust-pending-id-icon"><i class="fas fa-user-shield"></i></div>
                <div class="cust-pending-id-text">
                    <span class="cust-pending-id-label">Pending ID Verification</span>
                    <span class="cust-pending-id-sub">New accounts can't reserve or buy until you verify their uploaded ID<?php if ($noIdCount > 0): ?> &middot; <?php echo $noIdCount; ?> account(s) have no ID uploaded yet<?php endif; ?></span>
                </div>
                <div class="cust-pending-id-count">
                    <span id="custPendingIdCount"><?php echo $pendingIdCount; ?></span>
                    <span class="cust-pending-id-cta">Review <i class="fas fa-chevron-right"></i></span>
                </div>
            </div>

            <!-- 7-Day Daily Registration Trend -->
            <div class="cust-trend-section">
                <div class="cust-sub-header">
                    <h4><i class="fas fa-chart-bar"></i> Daily Customer Registrations (Last 7 Days)</h4>
                    <span class="cust-sub-hint"><i class="fas fa-hand-pointer"></i> Click any bar or KPI box to view user accounts in modal</span>
                </div>
                <div class="cust-bars-container">
                    <?php 
                    $maxDaily = 1;
                    foreach ($dailyCustomerStats as $ds) {
                        if ($ds['count'] > $maxDaily) $maxDaily = $ds['count'];
                    }
                    foreach ($dailyCustomerStats as $ds): 
                        $heightPercent = max(12, round(($ds['count'] / $maxDaily) * 100));
                        $isToday = !empty($ds['is_today']);
                        $labelDate = date('M d, Y', strtotime($ds['date']));
                        $titleText = htmlspecialchars($ds['label']) . " ($labelDate): " . $ds['count'] . " customer(s) - Click to view accounts";
                    ?>
                    <div class="cust-bar-item <?php echo $isToday ? 'is-today' : ''; ?>" 
                         onclick="openCustomerModal('day', 'Customer Registrations for <?php echo htmlspecialchars($ds['label']); ?> (<?php echo $labelDate; ?>)', '<?php echo $ds['date']; ?>')"
                         data-date="<?php echo $ds['date']; ?>"
                         title="<?php echo $titleText; ?>">
                        <div class="cust-bar-count"><?php echo $ds['count']; ?></div>
                        <div class="cust-bar-track">
                            <div class="cust-bar-fill <?php echo $ds['count'] > 0 ? 'has-count' : ''; ?>" style="height: <?php echo $heightPercent; ?>%;"></div>
                        </div>
                        <div class="cust-bar-day"><?php echo $ds['day']; ?></div>
                        <div class="cust-bar-date"><?php echo date('M d', strtotime($ds['date'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Row 2: Order Status Donut Chart & Breakdown (Cols 5-6, Right of Customers Statistics, Same Height) -->
        <div class="dashboard-widget status-widget">
            <div class="widget-header">
                <h3><i class="fas fa-chart-pie"></i> Order Status</h3>
            </div>
            <div class="status-donut-container">
                <div class="status-chart-wrapper">
                    <canvas id="orderStatusChart"></canvas>
                    <div class="status-chart-center">
                        <span class="center-total-num"><?php echo $totalReservations; ?></span>
                        <span class="center-total-label">Orders</span>
                    </div>
                </div>
                <div class="status-breakdown">
                    <div class="status-row">
                        <span class="status-dot pending"></span>
                        <span class="status-label">Pending</span>
                        <span class="status-count"><?php echo $pendingCount; ?></span>
                    </div>
                    <div class="status-row">
                        <span class="status-dot confirmed"></span>
                        <span class="status-label">Confirmed</span>
                        <span class="status-count"><?php echo $confirmedCount; ?></span>
                    </div>
                    <div class="status-row">
                        <span class="status-dot completed"></span>
                        <span class="status-label">Completed</span>
                        <span class="status-count"><?php echo $completedCount; ?></span>
                    </div>
                    <div class="status-row">
                        <span class="status-dot cancelled"></span>
                        <span class="status-label">Cancelled</span>
                        <span class="status-count"><?php echo $cancelledCount; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Category Distribution (Cols 1-2) -->
        <div class="dashboard-widget category-widget">
            <div class="widget-header">
                <h3><i class="fas fa-tags"></i> Categories</h3>
            </div>
            <div class="category-list">
                <?php if (empty($categoryStats)): ?>
                    <div class="widget-empty">No categories yet</div>
                <?php else: ?>
                    <?php foreach ($categoryStats as $cat): ?>
                        <div class="category-item">
                            <span class="cat-name"><?php echo htmlspecialchars($cat['category']); ?></span>
                            <div class="cat-bar-bg">
                                <div class="cat-bar-fill" style="width: <?php echo min(100, (int)$cat['cnt'] * 20); ?>%"></div>
                            </div>
                            <span class="cat-count"><?php echo (int)$cat['cnt']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Row 3: Recent Reviews (Cols 3-4) -->
        <div class="dashboard-widget reviews-widget">
            <div class="widget-header">
                <h3><i class="fas fa-star"></i> Recent Reviews</h3>
            </div>
            <div class="reviews-list">
                <?php if (empty($recentReviews)): ?>
                    <div class="widget-empty">No reviews yet</div>
                <?php else: ?>
                    <?php foreach ($recentReviews as $rev): ?>
                        <div class="review-item-mini">
                            <div class="review-mini-header">
                                <span class="review-mini-product"><?php echo htmlspecialchars($rev['product_name'] ?? 'Unknown'); ?></span>
                                <span class="review-mini-stars"><?php
                                    for ($s = 0; $s < 5; $s++) {
                                        echo $s < (int)$rev['rating'] ? '&#9733;' : '<span style="color:#444">&#9733;</span>';
                                    }
                                ?></span>
                            </div>
                            <span class="review-mini-text"><?php echo htmlspecialchars(mb_strimwidth($rev['review_text'] ?? '', 0, 80, '...')); ?></span>
                            <span class="review-mini-author">— <?php echo htmlspecialchars(trim(($rev['first_name'] ?? '') . ' ' . ($rev['last_name'] ?? 'Anonymous'))); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Row 3: Top Reserved Products (Cols 5-6) -->
        <div class="dashboard-widget top-products-widget">
            <div class="widget-header">
                <h3><i class="fas fa-trophy"></i> Top Reserved Products</h3>
            </div>
            <div class="top-products-list">
                <?php if (empty($topProducts)): ?>
                    <div class="widget-empty">No reservations yet</div>
                <?php else: ?>
                    <?php foreach ($topProducts as $idx => $prod): ?>
                        <div class="top-product-item">
                            <span class="top-rank">#<?php echo $idx + 1; ?></span>
                            <span class="top-name"><?php echo htmlspecialchars($prod['product_name']); ?></span>
                            <span class="top-times"><?php echo (int)$prod['times_reserved']; ?>x</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script>
    // Revenue Growth Chart
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    // Start with all zeros
    const initialData = [0, 0, 0, 0, 0, 0];
    const targetData = <?php echo json_encode($monthlyRevenue); ?>;
    const chartLabels = <?php echo json_encode($monthlyLabels); ?>;
    const chartMaxVal = <?php echo $chartMax; ?>;
    
    let revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Revenue',
                data: initialData,
                borderColor: '#FFD700',
                backgroundColor: 'rgba(255, 215, 0, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#FFD700',
                pointBorderColor: '#FFD700',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#FFD700',
                    bodyColor: '#ffffff',
                    borderColor: '#FFD700',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            return '₱' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(255, 215, 0, 0.1)',
                        borderColor: 'rgba(255, 215, 0, 0.3)'
                    },
                    ticks: {
                        color: '#cccccc'
                    }
                },
                y: {
                    min: 0,
                    max: chartMaxVal,
                    ticks: {
                        stepSize: chartMaxVal / 5,
                        color: '#cccccc',
                        callback: function(value) {
                            return '₱' + value;
                        }
                    },
                    grid: {
                        color: 'rgba(255, 215, 0, 0.1)',
                        borderColor: 'rgba(255, 215, 0, 0.3)'
                    }
                }
            }
        }
    });
    
    // Animate to target values immediately
    revenueChart.data.datasets[0].data = targetData;
    revenueChart.update('active');

    // ===== Revenue Growth Dynamic & Realtime System =====
    let currentRevPeriod = '6months';
    let currentRevStartDate = '';
    let currentRevEndDate = '';
    let isRevFetching = false;

    function changeRevenueTimeframe(period) {
        if (typeof playButtonClickSound === 'function') playButtonClickSound();
        currentRevPeriod = period;
        
        toggleCustomDatePopover(false);
        
        document.querySelectorAll('.timeframe-btn').forEach(btn => {
            if (btn.getAttribute('data-period') === period) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        fetchRevenueGrowthData(false);
    }

    function toggleCustomDatePopover(force) {
        const popover = document.getElementById('customDatePopover');
        if (!popover) return;
        if (typeof force === 'boolean') {
            popover.style.display = force ? 'block' : 'none';
        } else {
            popover.style.display = (popover.style.display === 'none' || !popover.style.display) ? 'block' : 'none';
        }
    }

    function applyCustomDateRange() {
        if (typeof playButtonClickSound === 'function') playButtonClickSound();
        const startInput = document.getElementById('revStartDate');
        const endInput = document.getElementById('revEndDate');
        if (!startInput || !endInput) return;

        if (!startInput.value || !endInput.value) {
            alert('Please select both start and end dates.');
            return;
        }

        currentRevPeriod = 'custom';
        currentRevStartDate = startInput.value;
        currentRevEndDate = endInput.value;

        document.querySelectorAll('.timeframe-btn').forEach(btn => {
            if (btn.getAttribute('data-period') === 'custom') {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        toggleCustomDatePopover(false);
        fetchRevenueGrowthData(false);
    }

    function fetchRevenueGrowthData(isSilent = false) {
        if (isRevFetching) return;
        isRevFetching = true;

        let url = `dashboard.php?action=get_revenue_growth&period=${encodeURIComponent(currentRevPeriod)}`;
        if (currentRevPeriod === 'custom') {
            url += `&start_date=${encodeURIComponent(currentRevStartDate)}&end_date=${encodeURIComponent(currentRevEndDate)}`;
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                isRevFetching = false;
                if (data && data.status === 'success') {
                    updateRevenueChart(data, isSilent);
                }
            })
            .catch(err => {
                isRevFetching = false;
                console.error('Error fetching revenue growth:', err);
            });
    }

    function updateRevenueChart(data, isSilent) {
        if (!revenueChart) return;

        const periodEl = document.getElementById('revenueChartPeriod');
        const totalEl = document.getElementById('revenueChartTotalBadge');

        if (periodEl && data.period_label) {
            periodEl.textContent = data.period_label;
        }
        if (totalEl && data.formatted_total) {
            totalEl.textContent = 'Total: ' + data.formatted_total;
        }

        // Check if data changed
        const currentDataStr = JSON.stringify(revenueChart.data.datasets[0].data);
        const newDataStr = JSON.stringify(data.revenue);
        const currentLabelsStr = JSON.stringify(revenueChart.data.labels);
        const newLabelsStr = JSON.stringify(data.labels);

        if (currentDataStr === newDataStr && currentLabelsStr === newLabelsStr) {
            return;
        }

        revenueChart.data.labels = data.labels;
        revenueChart.data.datasets[0].data = data.revenue;

        const maxVal = data.max_val || 100;
        revenueChart.options.scales.y.max = maxVal;
        revenueChart.options.scales.y.ticks.stepSize = Math.max(20, Math.ceil(maxVal / 5));

        revenueChart.update(isSilent ? 'none' : 'active');
    }

    // Dismiss popover on outside click
    document.addEventListener('click', function(e) {
        const popover = document.getElementById('customDatePopover');
        const customBtn = document.querySelector('.timeframe-btn.custom-btn');
        if (popover && popover.style.display !== 'none') {
            if (!popover.contains(e.target) && (!customBtn || !customBtn.contains(e.target))) {
                popover.style.display = 'none';
            }
        }
    });

    // Realtime background polling for Revenue Growth every 5 seconds
    setInterval(function() {
        if (!document.hidden) {
            fetchRevenueGrowthData(true);
        }
    }, 5000);

    // Order Status Donut Chart
    const statusCtx = document.getElementById('orderStatusChart').getContext('2d');
    const statusDataValues = [
        <?php echo $pendingCount; ?>,
        <?php echo $confirmedCount; ?>,
        <?php echo $completedCount; ?>,
        <?php echo $cancelledCount; ?>
    ];
    const totalStatusCount = statusDataValues.reduce((a, b) => a + b, 0);

    const orderStatusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Confirmed', 'Completed', 'Cancelled'],
            datasets: [{
                data: totalStatusCount > 0 ? statusDataValues : [1],
                backgroundColor: totalStatusCount > 0 ? [
                    '#ff9800', // Pending (Orange)
                    '#2196f3', // Confirmed (Blue)
                    '#4CAF50', // Completed (Green)
                    '#f44336'  // Cancelled (Red)
                ] : ['#333333'],
                borderColor: '#1a1a1a',
                borderWidth: 2,
                hoverBorderColor: '#ffd700',
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1600,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: totalStatusCount > 0,
                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                    titleColor: '#FFD700',
                    bodyColor: '#ffffff',
                    borderColor: 'rgba(255, 215, 0, 0.4)',
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 4,
                    callbacks: {
                        label: function(context) {
                            const val = context.parsed;
                            const pct = totalStatusCount > 0 ? Math.round((val / totalStatusCount) * 100) : 0;
                            return ` ${context.label}: ${val} (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
    </script>



<!-- ===== ADMIN MESSENGER PANEL ===== -->
<div id="adminChatPanel" style="
    display: none;
    position: fixed;
    top: 0; right: 0; bottom: 0;
    width: 700px; max-width: 95vw;
    background: #111;
    border-left: 1px solid #7c6300;
    z-index: 14999;
    flex-direction: row;
    box-shadow: -8px 0 40px rgba(0,0,0,0.7);
    font-family: 'Segoe UI', sans-serif;
">
    <!-- LEFT: User List -->
    <div id="adminChatUserList" style="width:240px; flex-shrink:0; border-right:1px solid #2a2a2a; display:flex; flex-direction:column; background:#161616;">
        <!-- Header -->
        <div style="padding:16px 14px; border-bottom:1px solid #2a2a2a; display:flex; align-items:center; justify-content:space-between;">
            <div style="color:#ffd700; font-weight:700; font-size:15px; display:flex; align-items:center; gap:8px;">
                <img src="../images/icon/JMB.png" alt="Admin" style="width:24px; height:24px; border-radius:50%; object-fit:cover; border:1px solid #ffd700;" onerror="this.src='../images/icon/chat.png'">
                Messages
            </div>
            <div onclick="closeAdminChatPanel()" style="color:#888; cursor:pointer; font-size:18px; line-height:1; padding:2px 6px; border-radius:4px; transition:background 0.2s;" onmouseover="this.style.background='#2a2a2a'" onmouseout="this.style.background='transparent'">✕</div>
        </div>
        <!-- Search -->
        <div style="padding:10px 12px; border-bottom:1px solid #1e1e1e;">
            <input type="text" id="adminChatSearch" placeholder="Search conversations..." oninput="filterChatUsers()" style="width:100%; background:#222; border:1px solid #333; border-radius:20px; padding:8px 14px; color:#fff; font-size:12px; outline:none; box-sizing:border-box;">
        </div>
        <!-- User List Container -->
        <div id="adminChatUserListItems" style="flex:1; overflow-y:auto; scrollbar-width:thin; scrollbar-color:#333 transparent;">
            <div style="text-align:center; color:#555; font-size:13px; padding:30px 0;" id="adminNoChatsMsg">No messages yet</div>
        </div>
    </div>

    <!-- RIGHT: Conversation Area -->
    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden;">
        <!-- No conversation selected -->
        <div id="adminChatNoSelect" style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#444;">
            <img src="../images/icon/JMB.png" style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid #333; opacity:0.3; margin-bottom:16px;" onerror="this.src='../images/icon/chat.png'">
            <div style="font-size:14px;">Select a conversation to start replying</div>
        </div>

        <!-- Conversation view -->
        <div id="adminChatConvo" style="display:none; flex-direction:column; flex:1; overflow:hidden;">
            <!-- Convo Header -->
            <div style="padding:14px 18px; border-bottom:1px solid #2a2a2a; display:flex; align-items:center; gap:12px; background:#161616;">
                <button type="button" id="adminChatBackBtn" onclick="backToAdminUserList()" style="display:none; background:#222; border:1px solid #7c6300; border-radius:50%; width:32px; height:32px; color:#ffd700; font-size:12px; cursor:pointer; align-items:center; justify-content:center; flex-shrink:0;" title="Back to conversations">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div style="position:relative;">
                    <img id="adminConvoAvatar" src="../images/icon/profile_logo.png" style="width:38px; height:38px; border-radius:50%; object-fit:cover; border:2px solid #7c6300;" onerror="this.src='../images/icon/profile_logo.png'">
                    <span style="position:absolute; bottom:1px; right:1px; width:10px; height:10px; background:#44bb44; border-radius:50%; border:2px solid #161616;"></span>
                </div>
                <div>
                    <div id="adminConvoName" style="color:#fff; font-weight:700; font-size:14px;">—</div>
                    <div style="color:#888; font-size:11px;">Customer</div>
                </div>
            </div>
            <!-- Messages -->
            <div id="adminChatMessages" style="flex:1; overflow-y:auto; padding:16px 18px; display:flex; flex-direction:column; gap:10px; scrollbar-width:thin; scrollbar-color:#333 transparent;">
                <div style="text-align:center; color:#444; font-size:12px;" id="adminConvoPlaceholder">Loading messages…</div>
            </div>
            <!-- Reply Input -->
            <div style="padding:12px 16px; border-top:1px solid #2a2a2a; display:flex; gap:10px; align-items:flex-end; background:#161616;">
                <textarea id="adminReplyInput" placeholder="Type a reply..." rows="1" style="flex:1; background:#222; border:1px solid #333; border-radius:20px; padding:10px 16px; color:#fff; font-size:13px; resize:none; outline:none; max-height:80px; overflow-y:auto; line-height:1.4; font-family:inherit;" onkeydown="handleAdminReplyKey(event)" oninput="this.style.height='auto'; this.style.height=Math.min(this.scrollHeight,80)+'px'"></textarea>
                <button onclick="sendAdminReply()" style="background:linear-gradient(135deg,#7c6300,#b8960c); border:none; border-radius:50%; width:40px; height:40px; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/></svg>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Dark overlay backdrop -->
<div id="adminChatBackdrop" onclick="closeAdminChatPanel()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:14998;"></div>

<script>
// ===== ADMIN MESSENGER SYSTEM =====
let adminChatOpen = false;
let adminSelectedUserId = null;
let currentSelectedUserAvatar = '../images/icon/profile_logo.png';
let adminChatLastId = 0;
let adminChatPollInterval = null;
let adminUserPollInterval = null;
let allChatUsers = [];

function formatAdminAvatarPath(path) {
    if (!path) return '../images/icon/profile_logo.png';
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('../') || path.startsWith('/')) {
        return path;
    }
    return '../' + path;
}

// ===== ADMIN NOTIFICATION & TAB BADGE SYSTEM =====
let adminOriginalTitle = document.title;
let adminTitleFlashInterval = null;
let adminNotificationAudio = null;
let adminPrevTotalUnread = 0;

try {
    adminNotificationAudio = new Audio('../ringtone/notification.MP3');
    adminNotificationAudio.volume = 0.5;
} catch (e) {}

function requestAdminNotificationPermission() {
    if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
    }
}

function playAdminNotificationSound() {
    if (adminNotificationAudio) {
        adminNotificationAudio.currentTime = 0;
        adminNotificationAudio.play().catch(() => {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, ctx.currentTime);
                osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1);
                gain.gain.setValueAtTime(0.2, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.35);
            } catch (err) {}
        });
    }
}

function setAdminTabFaviconWithBadge(hasBadge) {
    const favicon = document.getElementById('dynamicFavicon');
    if (!favicon) return;
    if (!hasBadge) {
        favicon.href = '../images/icon/JMB.png';
        return;
    }
    const canvas = document.createElement('canvas');
    canvas.width = 32;
    canvas.height = 32;
    const ctx = canvas.getContext('2d');
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = '../images/icon/JMB.png';
    img.onload = function() {
        ctx.save();
        ctx.beginPath();
        ctx.arc(16, 16, 15, 0, Math.PI * 2);
        ctx.clip();
        ctx.drawImage(img, 0, 0, 32, 32);
        ctx.restore();
        
        ctx.beginPath();
        ctx.arc(24, 8, 6, 0, Math.PI * 2);
        ctx.fillStyle = '#ff0000';
        ctx.fill();
        ctx.lineWidth = 1.5;
        ctx.strokeStyle = '#ffffff';
        ctx.stroke();

        favicon.href = canvas.toDataURL('image/png');
    };
    img.onerror = function() {
        favicon.href = '../images/icon/JMB.png';
    };
}

function startAdminTabTitleFlash(unreadCount) {
    stopAdminTabTitleFlash();
    let toggle = false;
    adminTitleFlashInterval = setInterval(() => {
        document.title = toggle ? `(${unreadCount}) 🔴 New Message! - Admin Dashboard` : adminOriginalTitle;
        toggle = !toggle;
    }, 1000);
}

function stopAdminTabTitleFlash() {
    if (adminTitleFlashInterval) {
        clearInterval(adminTitleFlashInterval);
        adminTitleFlashInterval = null;
    }
    document.title = adminOriginalTitle;
    setAdminTabFaviconWithBadge(false);
}

function showAdminWebToast(senderName, messageText, avatarSrc, userObj) {
    let toast = document.getElementById('adminChatWebToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'adminChatWebToast';
        toast.style.cssText = 'position:fixed; top:20px; right:80px; z-index:30000; background:#181818; border:1px solid #ffd700; border-radius:12px; padding:12px 16px; display:flex; align-items:center; gap:12px; box-shadow:0 8px 32px rgba(0,0,0,0.8); cursor:pointer; max-width:340px; font-family:"Segoe UI",sans-serif;';
        document.body.appendChild(toast);
    }
    toast.innerHTML = `
        <img src="${avatarSrc || '../images/icon/profile_logo.png'}" style="width:38px; height:38px; border-radius:50%; object-fit:cover; border:1.5px solid #ffd700;" onerror="this.src='../images/icon/profile_logo.png'">
        <div style="flex:1; overflow:hidden;">
            <div style="color:#ffd700; font-weight:700; font-size:13px;">${escapeHtmlA(senderName)}</div>
            <div style="color:#ddd; font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtmlA(messageText)}</div>
        </div>
        <div onclick="event.stopPropagation(); this.parentElement.style.display='none';" style="color:#888; font-size:16px; padding:2px; cursor:pointer;">✕</div>
    `;
    toast.onclick = () => {
        toast.style.display = 'none';
        openAdminChatPanel();
        if (userObj) selectAdminConvo(userObj);
    };
    toast.style.display = 'flex';
    setTimeout(() => {
        if (toast) toast.style.display = 'none';
    }, 6000);
}

function triggerAdminNewMessageNotification(senderName, messageText, avatarSrc, totalUnread, userObj) {
    playAdminNotificationSound();
    setAdminTabFaviconWithBadge(true);
    startAdminTabTitleFlash(totalUnread || 1);
    showAdminWebToast(senderName, messageText, avatarSrc, userObj);

    if ("Notification" in window && Notification.permission === "granted") {
        try {
            const notif = new Notification(senderName, {
                body: messageText,
                icon: avatarSrc || "../images/icon/JMB.png",
                badge: "../images/icon/JMB.png",
                tag: "closetgo-admin-chat"
            });
            notif.onclick = function() {
                window.focus();
                openAdminChatPanel();
                if (userObj) selectAdminConvo(userObj);
                notif.close();
            };
        } catch (e) {}
    }
}

function toggleAdminChatPanel() {
    requestAdminNotificationPermission();
    if (adminChatOpen) {
        closeAdminChatPanel();
    } else {
        openAdminChatPanel();
    }
}

function openAdminChatPanel() {
    requestAdminNotificationPermission();
    adminChatOpen = true;
    const panel = document.getElementById('adminChatPanel');
    panel.style.display = 'flex';
    document.getElementById('adminChatBackdrop').style.display = 'block';
    loadAdminChatUsers();
    startAdminUserPolling();
}

function closeAdminChatPanel() {
    adminChatOpen = false;
    const panel = document.getElementById('adminChatPanel');
    if (panel) {
        panel.style.display = 'none';
        panel.classList.remove('mobile-convo-active');
    }
    const backdrop = document.getElementById('adminChatBackdrop');
    if (backdrop) backdrop.style.display = 'none';
    stopAdminConvoPoll();
    stopAdminUserPolling();
}

function backToAdminUserList() {
    const panel = document.getElementById('adminChatPanel');
    if (panel) panel.classList.remove('mobile-convo-active');
    stopAdminConvoPoll();
    adminSelectedUserId = null;
    const convo = document.getElementById('adminChatConvo');
    if (convo) convo.style.display = 'none';
    const noSelect = document.getElementById('adminChatNoSelect');
    if (noSelect) noSelect.style.display = 'flex';
    renderAdminUserList(allChatUsers);
}

function startAdminUserPolling() {
    if (adminUserPollInterval) clearInterval(adminUserPollInterval);
    const interval = document.hidden ? 8000 : 3000;
    adminUserPollInterval = setInterval(loadAdminChatUsers, interval);
}

function stopAdminUserPolling() {
    if (adminUserPollInterval) { clearInterval(adminUserPollInterval); adminUserPollInterval = null; }
}

function startAdminConvoPoll(userId) {
    stopAdminConvoPoll();
    const interval = document.hidden ? 8000 : 1500;
    adminChatPollInterval = setInterval(() => pollAdminConvo(userId), interval);
}

function stopAdminConvoPoll() {
    if (adminChatPollInterval) { clearInterval(adminChatPollInterval); adminChatPollInterval = null; }
}

function loadAdminChatUsers() {
    fetch('dashboard.php?chat_action=get_users', { credentials: 'same-origin' })
        .then(r => r.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.warn('chat_get_users non-JSON:', text);
                const cleanErr = text ? text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 100) : 'Server error';
                data = { success: false, error: cleanErr };
            }
            if (!data.success) {
                console.warn('Admin chat users error:', data.error);
                const container = document.getElementById('adminChatUserListItems');
                if (container && (!allChatUsers || allChatUsers.length === 0)) {
                    container.innerHTML = `<div style="text-align:center; color:#ff6b6b; font-size:12px; padding:30px 10px;">${escapeHtmlA(data.error || 'Failed to load conversations')}</div>`;
                }
                return;
            }
            allChatUsers = data.users || [];
            renderAdminUserList(allChatUsers);

            const total = data.total_unread || 0;
            const badge = document.getElementById('adminChatTotalBadge');

            if (total > adminPrevTotalUnread && total > 0) {
                // Find latest unread user
                const unreadUser = allChatUsers.find(u => u.unread_count > 0) || allChatUsers[0];
                if (unreadUser) {
                    triggerAdminNewMessageNotification(unreadUser.name, unreadUser.last_message || 'New customer message!', formatAdminAvatarPath(unreadUser.profile_image), total, unreadUser);
                }
            }
            adminPrevTotalUnread = total;

            if (total > 0) {
                badge.textContent = total > 99 ? '99+' : total;
                badge.style.display = 'flex';
                setAdminTabFaviconWithBadge(true);
            } else {
                badge.style.display = 'none';
                if (!adminTitleFlashInterval) {
                    setAdminTabFaviconWithBadge(false);
                }
            }
        }).catch(() => {});
}

function renderAdminUserList(users) {
    const container = document.getElementById('adminChatUserListItems');
    const search = document.getElementById('adminChatSearch').value.toLowerCase();

    const filtered = users.filter(u => u.name.toLowerCase().includes(search));

    if (filtered.length === 0) {
        container.innerHTML = '<div style="text-align:center; color:#555; font-size:13px; padding:30px 0;">No conversations yet</div>';
        return;
    }

    container.innerHTML = '';
    filtered.forEach(user => {
        const isActive = adminSelectedUserId === user.id;
        const item = document.createElement('div');
        item.style.cssText = `padding:12px 14px; display:flex; align-items:center; gap:10px; cursor:pointer; transition:background 0.15s; background:${isActive ? '#1e1e0a' : 'transparent'}; border-left:3px solid ${isActive ? '#ffd700' : 'transparent'};`;
        item.onmouseover = () => { if (!isActive) item.style.background = '#1a1a1a'; };
        item.onmouseout = () => { if (!isActive) item.style.background = 'transparent'; };
        item.onclick = () => selectAdminConvo(user);

        const lastMsgPreview = (user.last_sender === 'admin' ? 'You: ' : '') + (user.last_message || '').substring(0, 28) + (user.last_message && user.last_message.length > 28 ? '…' : '');
        const timeStr = formatAdminChatTime(user.last_time);
        const avatarSrc = formatAdminAvatarPath(user.profile_image);

        item.innerHTML = `
            <div style="position:relative; flex-shrink:0;">
                <img src="${escapeHtmlA(avatarSrc)}" style="width:42px; height:42px; border-radius:50%; object-fit:cover; border:2px solid ${isActive ? '#ffd700' : '#333'};" onerror="this.src='../images/icon/profile_logo.png'">
                ${user.unread_count > 0 ? `<span style="position:absolute; top:-2px; right:-2px; background:#ff4444; color:white; border-radius:50%; width:16px; height:16px; font-size:9px; font-weight:bold; display:flex; align-items:center; justify-content:center; border:2px solid #161616;">${user.unread_count}</span>` : ''}
            </div>
            <div style="flex:1; overflow:hidden;">
                <div style="display:flex; justify-content:space-between; align-items:baseline;">
                    <span style="color:${user.unread_count > 0 ? '#fff' : '#ccc'}; font-weight:${user.unread_count > 0 ? '700' : '500'}; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:120px;">${escapeHtmlA(user.name)}</span>
                    <span style="color:#555; font-size:10px; flex-shrink:0; margin-left:4px;">${timeStr}</span>
                </div>
                <div style="color:${user.unread_count > 0 ? '#bbb' : '#555'}; font-size:11px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtmlA(lastMsgPreview)}</div>
            </div>
        `;
        container.appendChild(item);
    });
}

function filterChatUsers() {
    renderAdminUserList(allChatUsers);
}

function selectAdminConvo(user) {
    adminSelectedUserId = user.id;
    currentSelectedUserAvatar = formatAdminAvatarPath(user.profile_image);
    adminChatLastId = 0;

    // Clear flashing title if this cleared the unreads
    stopAdminTabTitleFlash();

    // Update UI
    document.getElementById('adminChatNoSelect').style.display = 'none';
    const convo = document.getElementById('adminChatConvo');
    convo.style.display = 'flex';
    convo.style.flexDirection = 'column';
    document.getElementById('adminConvoName').textContent = user.name;
    document.getElementById('adminConvoAvatar').src = currentSelectedUserAvatar;
    document.getElementById('adminChatMessages').innerHTML = '<div style="text-align:center; color:#444; font-size:12px;">Loading…</div>';

    // On mobile, switch view to conversation
    const panel = document.getElementById('adminChatPanel');
    if (panel) panel.classList.add('mobile-convo-active');

    // Re-render user list to show active state
    renderAdminUserList(allChatUsers);

    // Mark as read
    fetch('dashboard.php?chat_action=mark_read', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ user_id: user.id })
    }).catch(() => {});

    // Load full history
    fetch(`dashboard.php?chat_action=get&user_id=${user.id}&last_id=0&caller=admin`, { credentials: 'same-origin' })
        .then(r => r.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                data = { success: false, error: text ? text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 100) : 'Server error' };
            }
            const container = document.getElementById('adminChatMessages');
            container.innerHTML = '';
            if (data.success && data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    appendAdminBubble(msg, user.name);
                    if (msg.id) adminChatLastId = Math.max(adminChatLastId, msg.id);
                });
            } else if (!data.success) {
                container.innerHTML = `<div style="text-align:center; color:#ff6b6b; font-size:12px; padding:20px;">${escapeHtmlA(data.error || 'Failed to load messages')}</div>`;
            } else {
                container.innerHTML = '<div style="text-align:center; color:#444; font-size:12px; padding:20px;">No messages yet</div>';
            }
            scrollAdminToBottom();
        }).catch(() => {});

    // Update user unread count to 0 in local data
    const u = allChatUsers.find(x => x.id === user.id);
    if (u) u.unread_count = 0;
    renderAdminUserList(allChatUsers);

    // Start polling this conversation
    startAdminConvoPoll(user.id);
    document.getElementById('adminReplyInput').focus();
}

function pollAdminConvo(userId) {
    if (!userId) return;
    fetch(`dashboard.php?chat_action=get&user_id=${userId}&last_id=${adminChatLastId}&caller=admin`, { credentials: 'same-origin' })
        .then(r => r.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                return;
            }
            if (!data.success) return;
            if (data.messages && data.messages.length > 0) {
                const userName = document.getElementById('adminConvoName').textContent;
                data.messages.forEach(msg => {
                    appendAdminBubble(msg, userName);
                    if (msg.id) adminChatLastId = Math.max(adminChatLastId, msg.id);
                    if (msg.sender === 'user') {
                        playAdminNotificationSound();
                    }
                });
                scrollAdminToBottom();
            }
        }).catch(() => {});
}

function formatAdminChatBubbleContent(rawMessage) {
    if (!rawMessage) return '';
    const match = rawMessage.match(/\[INQUIRY_ITEMS\]([\s\S]*?)\[\/INQUIRY_ITEMS\]/);
    if (!match) {
        return escapeHtmlA(rawMessage);
    }
    
    let items = [];
    try {
        items = JSON.parse(match[1]);
    } catch (e) {
        return escapeHtmlA(rawMessage);
    }
    
    const textPart = rawMessage.replace(/\[INQUIRY_ITEMS\][\s\S]*?\[\/INQUIRY_ITEMS\]/, '').trim();
    let html = '';
    
    if (textPart) {
        html += `<div style="margin-bottom:8px; word-break:break-word; font-size:13px; line-height:1.4;">${escapeHtmlA(textPart)}</div>`;
    }
    
    html += `<div style="display:flex; flex-direction:column; gap:6px; width:100%; min-width:210px;">`;
    items.forEach(item => {
        let imgSrc = item.image_path || 'images/icon/profile_logo.png';
        if (!imgSrc.startsWith('http') && !imgSrc.startsWith('../') && !imgSrc.startsWith('/')) {
            imgSrc = '../' + imgSrc;
        }
        html += `
            <div style="background:#141414; border:1px solid #7c6300; border-radius:10px; padding:8px 10px; display:flex; align-items:center; gap:10px; box-shadow:0 2px 8px rgba(0,0,0,0.5); text-align:left;">
                <img src="${escapeHtmlA(imgSrc)}" style="width:48px; height:48px; border-radius:8px; object-fit:cover; border:1px solid #ffd700; flex-shrink:0;" onerror="this.src='../images/icon/profile_logo.png'">
                <div style="flex:1; min-width:0; overflow:hidden;">
                    <div style="display:flex; align-items:center; gap:6px; margin-bottom:2px; flex-wrap:wrap;">
                        <span style="background:linear-gradient(135deg,#7c6300,#b8960c); color:#fff; font-size:9px; font-weight:700; padding:1px 6px; border-radius:6px; text-transform:uppercase;">${escapeHtmlA(item.source || 'Inquiry')}</span>
                        ${item.status ? `<span style="background:#1e381e; color:#55ee55; border:1px solid #2d5a2d; font-size:9px; font-weight:700; padding:1px 6px; border-radius:6px;">${escapeHtmlA(item.status)}</span>` : ''}
                    </div>
                    <div style="color:#fff; font-weight:700; font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtmlA(item.name || 'Product')}</div>
                    <div style="color:#ffd700; font-weight:700; font-size:12px; margin-top:1px;">₱${escapeHtmlA(String(item.price || '0.00'))}</div>
                    ${item.dates ? `<div style="color:#aaa; font-size:10px; margin-top:1px;">📅 ${escapeHtmlA(item.dates)}</div>` : ''}
                </div>
            </div>
        `;
    });
    html += `</div>`;
    return html;
}

function appendAdminBubble(msg, userName, tempId) {
    const container = document.getElementById('adminChatMessages');
    if (!container) return;

    // Deduplication check: if message with this ID already exists, do not duplicate!
    if (msg.id && msg.id > 0) {
        const existingWithId = container.querySelector(`[data-msg-id="${msg.id}"]`);
        if (existingWithId) return;

        // Check if there is an optimistic bubble waiting to be confirmed
        const pendingOptimistic = container.querySelector(`[data-msg-id="0"]`);
        if (pendingOptimistic && pendingOptimistic.getAttribute('data-sender') === msg.sender) {
            pendingOptimistic.setAttribute('data-msg-id', msg.id);
            pendingOptimistic.removeAttribute('data-temp-id');
            return;
        }
    }

    const isAdmin = msg.sender === 'admin';
    const bubbleWrapper = document.createElement('div');
    bubbleWrapper.setAttribute('data-msg-id', msg.id || 0);
    bubbleWrapper.setAttribute('data-sender', msg.sender);
    if (tempId) bubbleWrapper.setAttribute('data-temp-id', tempId);

    bubbleWrapper.style.cssText = `display:flex; flex-direction:row; align-items:flex-end; gap:8px; margin-bottom:12px; justify-content:${isAdmin ? 'flex-end' : 'flex-start'};`;

    const timeStr = formatAdminChatTime(msg.created_at);
    const adminImg = '../images/icon/JMB.png';
    const userImg = escapeHtmlA(currentSelectedUserAvatar || '../images/icon/profile_logo.png');
    const contentHtml = formatAdminChatBubbleContent(msg.message);

    if (isAdmin) {
        bubbleWrapper.innerHTML = `
            <div style="display:flex; flex-direction:column; align-items:flex-end; max-width:85%;">
                <div style="padding:10px 14px; border-radius:18px 18px 4px 18px; background:linear-gradient(135deg,#7c6300,#b8960c); color:#fff; font-size:13px; line-height:1.5; word-break:break-word; box-shadow:0 2px 8px rgba(0,0,0,0.3);">${contentHtml}</div>
                <div style="font-size:10px; color:#777; margin-top:2px; padding:0 4px;">You (Admin) · ${timeStr}</div>
            </div>
            <img src="${adminImg}" alt="Admin" style="width:30px; height:30px; border-radius:50%; object-fit:cover; border:1.5px solid #ffd700; flex-shrink:0; margin-bottom:16px;" onerror="this.src='../images/icon/profile_logo.png'">
        `;
    } else {
        bubbleWrapper.innerHTML = `
            <img src="${userImg}" alt="${escapeHtmlA(userName)}" style="width:30px; height:30px; border-radius:50%; object-fit:cover; border:1.5px solid #7c6300; flex-shrink:0; margin-bottom:16px;" onerror="this.src='../images/icon/profile_logo.png'">
            <div style="display:flex; flex-direction:column; align-items:flex-start; max-width:85%;">
                <div style="font-size:11px; color:#ffd700; font-weight:600; margin-bottom:2px; padding-left:4px;">${escapeHtmlA(userName)}</div>
                <div style="padding:10px 14px; border-radius:18px 18px 18px 4px; background:#2a2a2a; color:#fff; font-size:13px; line-height:1.5; word-break:break-word; border:1px solid #3a3a3a; box-shadow:0 2px 8px rgba(0,0,0,0.3);">${contentHtml}</div>
                <div style="font-size:10px; color:#777; margin-top:2px; padding:0 4px;">${timeStr}</div>
            </div>
        `;
    }
    container.appendChild(bubbleWrapper);
}

function sendAdminReply() {
    if (!adminSelectedUserId) return;
    const input = document.getElementById('adminReplyInput');
    const message = input.value.trim();
    if (!message) return;

    const tempId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
    const tempMsg = { id: 0, sender: 'admin', message: message, created_at: new Date().toISOString() };
    appendAdminBubble(tempMsg, 'You', tempId);
    scrollAdminToBottom();
    input.value = '';
    input.style.height = 'auto';

    const formData = new FormData();
    formData.append('user_id', adminSelectedUserId);
    formData.append('sender', 'admin');
    formData.append('message', message);

    fetch('dashboard.php?chat_action=send', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(r => r.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.warn('Admin chat send non-JSON response:', text);
            data = { success: false, error: text ? text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 100) : 'Server error' };
        }
        if (data.success && data.id) {
            adminChatLastId = Math.max(adminChatLastId, data.id);
            const container = document.getElementById('adminChatMessages');
            const el = container ? container.querySelector(`[data-temp-id="${tempId}"]`) : null;
            if (el) {
                el.setAttribute('data-msg-id', data.id);
                el.removeAttribute('data-temp-id');
            }
        } else {
            console.warn('Admin chat send error:', data);
            const container = document.getElementById('adminChatMessages');
            const el = container ? container.querySelector(`[data-temp-id="${tempId}"]`) : null;
            if (el) {
                el.style.opacity = '0.6';
                el.title = data.error || 'Failed to save message';
            }
        }
        // Refresh user list to update last message
        loadAdminChatUsers();
    }).catch(err => {
        console.error('Admin send failed:', err);
    });
}

function handleAdminReplyKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendAdminReply();
    }
}

function scrollAdminToBottom() {
    const el = document.getElementById('adminChatMessages');
    if (el) el.scrollTop = el.scrollHeight;
}

function escapeHtmlA(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(String(text)));
    return div.innerHTML;
}

function formatAdminChatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const now = new Date();
    const diffMs = now - d;
    const diffMins = Math.floor(diffMs / 60000);
    if (diffMins < 1) return 'just now';
    if (diffMins < 60) return `${diffMins}m`;
    if (diffMins < 1440) return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    return d.toLocaleDateString([], {month:'short', day:'numeric'});
}

window.addEventListener('focus', function() {
    if (adminChatOpen && adminSelectedUserId) {
        stopAdminTabTitleFlash();
    }
});

// ProFreeHost Optimization: Adjust polling dynamically based on page visibility
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        // Tab is active: immediately check and resume fast near-realtime polling
        if (adminChatOpen) {
            loadAdminChatUsers();
            if (adminSelectedUserId) {
                pollAdminConvo(adminSelectedUserId);
                startAdminConvoPoll(adminSelectedUserId);
            }
            startAdminUserPolling();
        } else {
            loadAdminChatUsers();
        }
    } else {
        // Tab hidden: slow down polling to save resources on free hosting
        if (adminSelectedUserId) {
            startAdminConvoPoll(adminSelectedUserId);
        }
        if (adminChatOpen) {
            startAdminUserPolling();
        }
    }
});

// Immediate badge and conversation check on page load
setTimeout(function() {
    loadAdminChatUsers();
}, 300);

// Periodic background check so admin sees new messages/badges even when panel is closed
setInterval(function() {
    if (!adminChatOpen && !document.hidden) {
        loadAdminChatUsers();
    }
}, 4000);


// ===== Customer Details Modal System =====
let currentModalCustomers = [];

function escapeHtmlA(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function openCustomerModal(filterType, title, dateVal) {
    if (typeof playButtonClickSound === 'function') {
        playButtonClickSound();
    }
    
    const modal = document.getElementById('customerDetailsModal');
    const modalTitle = document.getElementById('custModalTitle');
    const searchInput = document.getElementById('custModalSearch');
    const listContainer = document.getElementById('custModalList');
    const loadingEl = document.getElementById('custModalLoading');
    const emptyEl = document.getElementById('custModalEmpty');
    const countEl = document.getElementById('custModalCount');
    
    if (!modal) return;
    
    modal.classList.add('slide-panel-open');
    document.body.classList.add('slide-panel-open');
    document.body.style.overflow = 'hidden';
    if (modalTitle) modalTitle.textContent = title || 'Customer Accounts';
    if (searchInput) searchInput.value = '';
    if (listContainer) listContainer.innerHTML = '';
    if (loadingEl) loadingEl.style.display = 'block';
    if (emptyEl) emptyEl.style.display = 'none';
    if (countEl) countEl.textContent = '...';
    
    let url = `dashboard.php?action=get_customers_details&filter=${encodeURIComponent(filterType)}`;
    if (dateVal) {
        url += `&date=${encodeURIComponent(dateVal)}`;
    }
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (loadingEl) loadingEl.style.display = 'none';
            if (data.status === 'success') {
                currentModalCustomers = data.customers || [];
                if (modalTitle && data.title) modalTitle.textContent = data.title;
                if (countEl) countEl.textContent = currentModalCustomers.length;
                renderModalCustomerList(currentModalCustomers);
            } else {
                if (emptyEl) emptyEl.style.display = 'block';
                if (countEl) countEl.textContent = '0';
            }
        })
        .catch(err => {
            console.error('Error loading customer details modal:', err);
            if (loadingEl) loadingEl.style.display = 'none';
            if (emptyEl) {
                emptyEl.style.display = 'block';
                emptyEl.querySelector('p').textContent = 'Failed to load customer accounts. Please try again.';
            }
        });
}

function formatAdminCardPath(path) {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('../') || path.startsWith('/')) {
        return path;
    }
    return '../' + path;
}

function renderModalCustomerList(customers) {
    const listContainer = document.getElementById('custModalList');
    const emptyEl = document.getElementById('custModalEmpty');
    if (!listContainer) return;
    
    if (!customers || customers.length === 0) {
        listContainer.innerHTML = '';
        if (emptyEl) emptyEl.style.display = 'block';
        return;
    }
    
    if (emptyEl) emptyEl.style.display = 'none';
    let html = '';
    
    customers.forEach(cust => {
        const initial = (cust.display_name || 'C').charAt(0).toUpperCase();
        const phone = cust.phone ? escapeHtmlA(cust.phone) : '<span style="color:#9ca3af; font-style:italic;">No phone</span>';
        const location = cust.location ? escapeHtmlA(cust.location) : '<span style="color:#9ca3af; font-style:italic;">No location</span>';
        const avatarHtml = cust.profile_image 
            ? `<img src="../${escapeHtmlA(cust.profile_image)}" alt="Avatar" class="cust-card-avatar" onerror="this.outerHTML='<div class=\\'cust-card-avatar\\'>${initial}</div>'">`
            : `<div class="cust-card-avatar">${initial}</div>`;

        const cardImg = cust.id_front_image || cust.id_back_image || '';
        const hasCard = !!cardImg;
        const frontUrl = formatAdminCardPath(cardImg);
        const idType = cust.id_type || 'Government ID';
        const idStatus = parseInt(cust.id_verified || 0, 10);
        let statusBadge = '';
        if (!hasCard) {
            statusBadge = `<span class="cust-id-status no-id"><i class="fas fa-id-card-alt"></i> No ID</span>`;
        } else if (idStatus === 1) {
            statusBadge = `<span class="cust-id-status verified"><i class="fas fa-check-circle"></i> Verified</span>`;
        } else if (idStatus === 2) {
            statusBadge = `<span class="cust-id-status rejected"><i class="fas fa-times-circle"></i> Rejected</span>`;
        } else {
            statusBadge = `<span class="cust-id-status pending"><i class="fas fa-user-clock"></i> Pending</span>`;
        }

        html += `
            <div class="cust-card-row" data-cust-id="${parseInt(cust.id, 10)}">
                <div class="cust-row-identity">
                    <div class="cust-card-avatar-wrap">
                        ${avatarHtml}
                    </div>
                    <div class="cust-identity-text">
                        <div class="cust-name-line">
                            <span class="cust-card-name" style="color: #ffffff; font-weight: 700;" title="${escapeHtmlA(cust.display_name)}">${escapeHtmlA(cust.display_name)}</span>
                            ${statusBadge}
                        </div>
                        <div class="cust-joined-sub">
                            <i class="fas fa-calendar-alt"></i> Joined: <span>${escapeHtmlA(cust.joined_date || '')}</span> <span style="color:#9ca3af; margin-left:2px;">${escapeHtmlA(cust.time_str || '')}</span>
                        </div>
                    </div>
                </div>
                <div class="cust-row-contacts">
                    <div class="cust-row-info-item" title="${escapeHtmlA(cust.email)}">
                        <i class="fas fa-envelope"></i>
                        <span>${escapeHtmlA(cust.email)}</span>
                    </div>
                    <div class="cust-row-info-item">
                        <i class="fas fa-phone"></i>
                        <span>${phone}</span>
                    </div>
                </div>
                <div class="cust-row-location" title="${location}">
                    <div class="cust-row-info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>${location}</span>
                    </div>
                </div>
                <div class="cust-row-card-actions">
                    <button type="button" class="cust-btn-view-card ${hasCard ? 'has-card' : 'no-card'}" onclick="openAdminCustomerIdModal('${escapeHtmlA(cust.display_name)}', '${escapeHtmlA(idType)}', '${escapeHtmlA(frontUrl)}', ${parseInt(cust.id, 10)}, ${idStatus})" title="${hasCard ? 'View Customer Valid ID (Card Front with User)' : 'No Valid ID Uploaded'}">
                        <i class="fas fa-id-card"></i> View Card
                    </button>
                </div>
            </div>
        `;
    });
    
    listContainer.innerHTML = html;
}

function openAdminCustomerIdModal(name, idType, frontImg, custId, idStatus) {
    if (typeof playButtonClickSound === 'function') {
        playButtonClickSound();
    }
    const modal = document.getElementById('adminCustomerIdModal');
    const nameEl = document.getElementById('adminCustIdModalName');
    const typeEl = document.getElementById('adminCustIdModalType');
    const frontEl = document.getElementById('adminCustIdFrontImg');
    const frontCard = document.getElementById('adminCustIdFrontCard');
    const gridEl = document.getElementById('adminCustIdDualGrid');
    const emptyEl = document.getElementById('adminCustIdEmpty');
    
    if (!modal) return;

    modal.dataset.custId = custId ? String(custId) : '';
    renderAdminIdVerifyFooter(frontImg ? parseInt(idStatus || 0, 10) : -1);
    
    if (nameEl) nameEl.textContent = name ? `${name}'s Valid ID` : 'Customer Valid ID';
    if (typeEl) {
        typeEl.innerHTML = (idType ? `<i class="fas fa-id-card"></i> ${idType}` : '<i class="fas fa-id-card"></i> Government ID');
    }
    
    if (frontImg) {
        if (gridEl) gridEl.style.display = 'grid';
        if (emptyEl) emptyEl.style.display = 'none';
        if (frontCard) frontCard.style.display = 'flex';
        if (frontEl) {
            frontEl.src = frontImg;
            frontEl.alt = `${name || 'Customer'}'s Valid ID (Card Front with User)`;
        }
    } else {
        if (gridEl) gridEl.style.display = 'none';
        if (emptyEl) emptyEl.style.display = 'block';
    }
    
    modal.classList.add('is-active');
    modal.style.zIndex = '100005'; // Ensure it's on top of the customer slide panel
}

function closeAdminCustomerIdModal() {
    const modal = document.getElementById('adminCustomerIdModal');
    if (modal) {
        modal.classList.remove('is-active');
        modal.style.zIndex = '';
    }
    // Don't close the parent customer slide panel
}

// Render status + approve/reject buttons in the ID lightbox footer
// status: -1 = no ID, 0 = pending, 1 = verified, 2 = rejected
function renderAdminIdVerifyFooter(status) {
    const footer = document.getElementById('adminCustIdVerifyFooter');
    if (!footer) return;
    if (status < 0) {
        footer.style.display = 'none';
        footer.innerHTML = '';
        return;
    }
    footer.style.display = 'flex';
    const labels = {
        0: '<span class="cust-id-status pending"><i class="fas fa-user-clock"></i> Pending Verification</span>',
        1: '<span class="cust-id-status verified"><i class="fas fa-check-circle"></i> Verified &mdash; can reserve &amp; buy</span>',
        2: '<span class="cust-id-status rejected"><i class="fas fa-times-circle"></i> Rejected</span>'
    };
    let buttons = '';
    if (status !== 1) {
        buttons += `<button type="button" class="cust-btn-verify approve" onclick="verifyCustomerIdFromModal('approve', this)"><i class="fas fa-check"></i> Verify ID</button>`;
    }
    if (status !== 2) {
        buttons += `<button type="button" class="cust-btn-verify reject" onclick="verifyCustomerIdFromModal('reject', this)"><i class="fas fa-times"></i> Reject</button>`;
    }
    footer.innerHTML = `${labels[status] || labels[0]}<div class="cust-id-verify-btns">${buttons}</div>`;
}

function verifyCustomerIdFromModal(decision, btn) {
    const modal = document.getElementById('adminCustomerIdModal');
    const custId = modal ? parseInt(modal.dataset.custId || '0', 10) : 0;
    if (!custId) return;
    verifyCustomerId(custId, decision, btn, true);
}

let pendingRejectData = null;

function promptRejectCustomerId(custId, custName, btn, fromModal) {
    if (typeof playButtonClickSound === 'function') {
        playButtonClickSound();
    }
    pendingRejectData = { custId, btn, fromModal };
    const modal = document.getElementById('rejectCustomerIdModal');
    const nameEl = document.getElementById('rejectModalCustomerName');
    if (nameEl) {
        nameEl.textContent = custName ? custName : ('Customer Account #' + custId);
    }
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
    }
}

function closeRejectCustomerIdModal() {
    const modal = document.getElementById('rejectCustomerIdModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
    }
    pendingRejectData = null;
}

function executeRejectCustomerId() {
    if (!pendingRejectData) return;
    const { custId, btn, fromModal } = pendingRejectData;
    closeRejectCustomerIdModal();
    executeVerification(custId, 'reject', btn, fromModal);
}

function verifyCustomerId(custId, decision, btn, fromModal) {
    if (decision === 'reject') {
        let custName = '';
        if (Array.isArray(currentModalCustomers)) {
            const found = currentModalCustomers.find(c => parseInt(c.id, 10) === custId);
            if (found && found.display_name) custName = found.display_name;
        }
        if (!custName) {
            const nameEl = document.getElementById('adminCustIdModalName');
            if (nameEl && nameEl.textContent) {
                custName = nameEl.textContent.replace(/'s Valid ID$/i, '').trim();
            }
        }
        promptRejectCustomerId(custId, custName, btn, fromModal);
        return;
    }
    executeVerification(custId, decision, btn, fromModal);
}

function executeVerification(custId, decision, btn, fromModal) {
    if (btn) {
        btn.disabled = true;
        btn.dataset.origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
    }

    const fd = new FormData();
    fd.append('action', 'verify_customer_id');
    fd.append('user_id', custId);
    fd.append('decision', decision);

    fetch('dashboard.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.status !== 'success') {
                showNotification('Verification Error', data.message || 'Failed to update ID verification.', 'error');
                if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.origHtml || ''; }
                return;
            }

            // Update local data + re-render list
            currentModalCustomers.forEach(c => {
                if (parseInt(c.id, 10) === custId) c.id_verified = data.id_verified;
            });
            const modalTitle = document.getElementById('custModalTitle');
            const isPendingView = modalTitle && modalTitle.textContent === 'Pending ID Verification';
            if (isPendingView) {
                currentModalCustomers = currentModalCustomers.filter(c => parseInt(c.id, 10) !== custId);
            }
            const searchInput = document.getElementById('custModalSearch');
            filterModalCustomers(searchInput ? searchInput.value : '');

            updatePendingIdCounters(parseInt(data.pending_count || 0, 10));

            if (fromModal) {
                renderAdminIdVerifyFooter(parseInt(data.id_verified, 10));
            }

            if (decision === 'approve') {
                showNotification('ID Verified', data.message || 'Customer ID verified. Account can now reserve and buy.', 'success');
            } else {
                showNotification('ID Rejected', data.message || 'Customer ID rejected.', 'warning');
            }
        })
        .catch(err => {
            console.error('ID verification error:', err);
            showNotification('Error', 'Network error while updating ID verification.', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.origHtml || ''; }
        });
}

// Unified Notification Toast System (like calendar.php, categories.php, gowns.php)
let notificationTimeout = null;
let notificationCallback = null;
let notificationCancelCallback = null;

function showNotification(title, message, type = 'success', duration = 4000, onOk = null) {
    const toast = document.getElementById('customNotification');
    if (!toast) return;

    notificationCallback = onOk;
    notificationCancelCallback = null;

    const iconWrap = document.getElementById('customToastIcon');
    const titleEl = document.getElementById('customToastTitle');
    const msgEl = document.getElementById('customToastMessage');
    const cancelBtn = document.getElementById('customToastCancelBtn');
    const okBtn = document.getElementById('customToastOkBtn');

    if (cancelBtn) cancelBtn.style.display = 'none';
    if (okBtn) {
        okBtn.textContent = 'OK';
        okBtn.style.background = '#FFD700';
        okBtn.style.color = '#111';
    }

    titleEl.textContent = title;
    msgEl.innerHTML = message;

    iconWrap.className = 'custom-toast-icon-wrap ' + type;
    if (type === 'error') {
        iconWrap.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
    } else if (type === 'info') {
        iconWrap.innerHTML = '<i class="fas fa-info-circle"></i>';
    } else if (type === 'warning') {
        iconWrap.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
    } else {
        iconWrap.innerHTML = '<i class="fas fa-check-circle"></i>';
    }

    toast.classList.add('show');

    if (notificationTimeout) clearTimeout(notificationTimeout);
    if (duration > 0) {
        notificationTimeout = setTimeout(() => {
            hideNotification(true);
        }, duration);
    }
}

function showConfirmNotification(title, message, onConfirm, onCancel = null, okText = 'OK', type = 'warning') {
    const toast = document.getElementById('customNotification');
    if (!toast) return;

    notificationCallback = onConfirm;
    notificationCancelCallback = onCancel;

    const iconWrap = document.getElementById('customToastIcon');
    const titleEl = document.getElementById('customToastTitle');
    const msgEl = document.getElementById('customToastMessage');
    const cancelBtn = document.getElementById('customToastCancelBtn');
    const okBtn = document.getElementById('customToastOkBtn');

    if (cancelBtn) cancelBtn.style.display = 'inline-block';
    if (okBtn) {
        okBtn.textContent = okText;
        if (type === 'error' || type === 'danger') {
            okBtn.style.background = '#ef4444';
            okBtn.style.color = '#fff';
        } else {
            okBtn.style.background = '#FFD700';
            okBtn.style.color = '#111';
        }
    }

    titleEl.textContent = title;
    msgEl.innerHTML = message;

    iconWrap.className = 'custom-toast-icon-wrap ' + (type === 'danger' ? 'error' : type);
    iconWrap.innerHTML = (type === 'error' || type === 'danger')
        ? '<i class="fas fa-exclamation-circle"></i>'
        : '<i class="fas fa-exclamation-triangle"></i>';

    toast.classList.add('show');

    if (notificationTimeout) clearTimeout(notificationTimeout);
}

function hideNotification(isOk = true) {
    const toast = document.getElementById('customNotification');
    if (toast) toast.classList.remove('show');
    if (notificationTimeout) clearTimeout(notificationTimeout);

    if (isOk) {
        if (notificationCallback) {
            const cb = notificationCallback;
            notificationCallback = null;
            notificationCancelCallback = null;
            cb();
        }
    } else {
        if (notificationCancelCallback) {
            const cb = notificationCancelCallback;
            notificationCallback = null;
            notificationCancelCallback = null;
            cb();
        }
    }
}

function updatePendingIdCounters(count) {
    const headerCount = document.getElementById('headerPendingIdCount');
    const stripCount = document.getElementById('custPendingIdCount');
    const headerBadge = document.getElementById('headerPendingIdBadge');
    const strip = document.getElementById('custPendingIdStrip');
    if (headerCount) headerCount.textContent = count;
    if (stripCount) stripCount.textContent = count;
    if (headerBadge) headerBadge.classList.toggle('has-pending', count > 0);
    if (strip) strip.classList.toggle('has-pending', count > 0);
}

function filterModalCustomers(query) {
    const q = (query || '').toLowerCase().trim();
    if (!q) {
        renderModalCustomerList(currentModalCustomers);
        const countEl = document.getElementById('custModalCount');
        if (countEl) countEl.textContent = currentModalCustomers.length;
        return;
    }
    
    const filtered = currentModalCustomers.filter(c => {
        const name = (c.display_name || '').toLowerCase();
        const email = (c.email || '').toLowerCase();
        const phone = (c.phone || '').toLowerCase();
        const loc = (c.location || '').toLowerCase();
        const id = String(c.id || '');
        return name.includes(q) || email.includes(q) || phone.includes(q) || loc.includes(q) || id.includes(q);
    });
    
    const countEl = document.getElementById('custModalCount');
    if (countEl) countEl.textContent = filtered.length;
    renderModalCustomerList(filtered);
}

function closeCustomerModal() {
    const modal = document.getElementById('customerDetailsModal');
    if (modal) {
        modal.classList.remove('slide-panel-open');
    }
    document.body.classList.remove('slide-panel-open');
    document.body.style.overflow = 'auto';
}

// Close modal on escape key or clicking backdrop
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const rejectModal = document.getElementById('rejectCustomerIdModal');
        if (rejectModal && (rejectModal.classList.contains('active') || rejectModal.style.display === 'flex')) {
            closeRejectCustomerIdModal();
            return;
        }
        const idModal = document.getElementById('adminCustomerIdModal');
        if (idModal && idModal.classList.contains('is-active')) {
            closeAdminCustomerIdModal();
            return;
        }
        closeCustomerModal();
    }
});
window.addEventListener('click', function(e) {
    const rejectModal = document.getElementById('rejectCustomerIdModal');
    if (rejectModal && e.target === rejectModal) {
        closeRejectCustomerIdModal();
        e.stopPropagation();
        return;
    }
    const idModal = document.getElementById('adminCustomerIdModal');
    if (idModal && e.target === idModal) {
        closeAdminCustomerIdModal();
        e.stopPropagation(); // Prevent parent slide panel from closing
        return;
    }
    const modal = document.getElementById('customerDetailsModal');
    if (modal && e.target === modal) {
        closeCustomerModal();
    }
});

// Mobile: Handle customer metrics display
function handleMobileMetrics() {
    const mobileMetric = document.querySelector('.mobile-metric-value');
    if (mobileMetric) {
        const value = mobileMetric.getAttribute('data-value');
        if (window.innerWidth <= 768 && value) {
            mobileMetric.textContent = value; // Remove + on mobile
        } else if (value) {
            mobileMetric.textContent = '+' + value; // Add + on desktop
        }
    }
}

// Run on load and resize
window.addEventListener('load', handleMobileMetrics);
window.addEventListener('resize', handleMobileMetrics);


</script>

<!-- Customer Details Big Modal Backdrop -->
<div id="customerModalBackdrop" class="modal-backdrop" onclick="closeCustomerModal()"></div>

<!-- Customer Details Big Modal -->
<div id="customerDetailsModal" class="slide-panel">
    <div class="slide-panel-content">
        <div class="slide-panel-header">
            <h3><i class="fas fa-users-cog"></i> <span id="custModalTitle">Customer Accounts</span></h3>
            <button type="button" class="close-panel-btn" onclick="closeCustomerModal()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="cust-modal-search-wrap">
            <div class="cust-modal-search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="custModalSearch" placeholder="Search by name, email, phone, location, ID..." oninput="filterModalCustomers(this.value)">
            </div>
            <span class="cust-modal-count-badge" id="custModalCountBadge"><i class="fas fa-user-check"></i> <strong id="custModalCount">0</strong> Accounts</span>
        </div>
        <div class="slide-panel-body">
            <div id="custModalLoading" class="cust-modal-loading" style="display: none;">
                <i class="fas fa-circle-notch fa-spin"></i>
                <p>Loading customer account details...</p>
            </div>
            <div id="custModalEmpty" class="cust-modal-empty" style="display: none;">
                <i class="fas fa-user-slash"></i>
                <p>No customer accounts found for this selection.</p>
            </div>
            <div id="custModalList" class="cust-modal-list"></div>
        </div>
    </div>
</div>

<!-- Customer ID Card Lightbox Modal in Admin Dashboard (Always on top with z-index: 100005) -->
<div id="adminCustomerIdModal" class="modal cust-id-lightbox-modal" style="z-index: 100005;" onclick="closeAdminCustomerIdModal()">
    <div class="modal-content cust-id-lightbox-content" onclick="event.stopPropagation()">
        <div class="cust-id-lightbox-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-id-card" style="color: #ffd700; font-size: 1.3rem;"></i>
                <div>
                    <h3 id="adminCustIdModalName" style="margin: 0; color: #fff; font-size: 1.05rem; font-weight: 700;">Customer Valid ID</h3>
                    <span id="adminCustIdModalType" style="color: #ffd700; font-size: 0.78rem; display: flex; align-items: center; gap: 6px; margin-top: 2px;">Government ID</span>
                </div>
            </div>
            <button type="button" class="close-panel-btn" onclick="closeAdminCustomerIdModal()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="cust-id-lightbox-body">
            <div id="adminCustIdDualGrid" class="cust-id-dual-grid" style="grid-template-columns: 1fr;">
                <div class="cust-id-side-card" id="adminCustIdFrontCard">
                    <div class="cust-id-side-label"><i class="fas fa-id-badge"></i> CARD FRONT INCLUDING USER</div>
                    <div class="cust-id-img-box" style="max-height: 480px; min-height: 240px;">
                        <img id="adminCustIdFrontImg" src="" alt="Valid ID (Card Front Including User)" class="cust-id-preview-img" style="max-height: 460px;" onclick="window.open(this.src, '_blank')" title="Click to view full image in new tab">
                    </div>
                </div>
            </div>
            <div id="adminCustIdEmpty" class="cust-id-empty-state" style="display: none; text-align: center; padding: 36px 20px;">
                <i class="fas fa-id-card-alt" style="font-size: 3rem; color: #444; margin-bottom: 12px; display: block;"></i>
                <p style="color: #bbb; font-size: 0.9rem; margin: 0;">No Valid ID uploaded by this customer yet.</p>
            </div>
        </div>
        <div id="adminCustIdVerifyFooter" class="cust-id-verify-footer" style="display: none;"></div>
    </div>
</div>

<!-- REJECT CUSTOMER ID CONFIRMATION MODAL (Matching Settings/Gowns/Chat confirmation dialogs) -->
<div id="rejectCustomerIdModal" class="custom-modal-overlay" style="display: none;" onclick="closeRejectCustomerIdModal()">
    <div class="custom-modal-box reject-confirm-modal-box" onclick="event.stopPropagation()">
        <div class="custom-modal-header reject-modal-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Reject Customer ID</h3>
            <button type="button" class="modal-close-btn" onclick="closeRejectCustomerIdModal()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="custom-modal-body" style="text-align: center; padding: 24px 22px;">
            <div class="reject-modal-icon-wrap">
                <i class="fas fa-id-card"></i>
                <span class="reject-modal-icon-badge"><i class="fas fa-times"></i></span>
            </div>
            <p style="color: #ffffff; font-size: 1.05rem; font-weight: 700; margin: 0 0 6px 0;">Reject this customer's uploaded ID?</p>
            <div id="rejectModalCustomerName" style="color: #ffd700; font-weight: 700; font-size: 0.95rem; margin-bottom: 14px;">Customer Account</div>
            <div class="reject-modal-notice">
                <i class="fas fa-info-circle" style="color: #ef4444; margin-top: 2px;"></i>
                <span>They will need to upload a new valid ID in their Profile before they can reserve or buy.</span>
            </div>
        </div>
        <div class="custom-modal-footer">
            <button type="button" class="btn-modal-cancel" onclick="closeRejectCustomerIdModal()">Cancel</button>
            <button type="button" id="btnConfirmRejectCustomerId" class="btn-modal-reject" onclick="executeRejectCustomerId()">
                <i class="fas fa-times"></i> Yes, Reject ID
            </button>
        </div>
    </div>
</div>

<!-- Unified Top-Right Slide UI (Notification & Confirmation) -->
<div id="customNotification" class="custom-slide-toast">
    <div class="custom-toast-content">
        <div class="custom-toast-icon-wrap" id="customToastIcon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="custom-toast-body">
            <div class="custom-toast-title" id="customToastTitle">Notification</div>
            <div class="custom-toast-msg" id="customToastMessage">Message content here.</div>
        </div>
        <div class="custom-toast-btns" id="customToastBtns">
            <button type="button" class="custom-toast-cancel-btn" id="customToastCancelBtn" onclick="hideNotification(false)" style="display: none;">Cancel</button>
            <button type="button" class="custom-toast-ok-btn" id="customToastOkBtn" onclick="hideNotification(true)">OK</button>
        </div>
    </div>
</div>

</body>
</html>
