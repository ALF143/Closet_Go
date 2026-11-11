<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../database/db_config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: adminlogin.php');
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Ensure order_status_logs table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS order_status_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reservation_id INT NULL,
        reservation_code VARCHAR(100) NULL,
        reservation_group_id VARCHAR(100) NULL,
        product_name VARCHAR(255) NULL,
        customer_name VARCHAR(255) NULL,
        order_type VARCHAR(50) DEFAULT 'reserve',
        old_status VARCHAR(50) NULL,
        new_status VARCHAR(50) NOT NULL,
        changed_by_type ENUM('staff', 'admin') DEFAULT 'staff',
        staff_id INT NULL,
        staff_name VARCHAR(150) NULL,
        staff_email VARCHAR(150) NULL,
        reason TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Auto-sanitize legacy status '0' records in order_status_logs if any exist
@$conn->query("UPDATE order_status_logs SET new_status = 'completed' WHERE new_status = '0' AND old_status = 'confirmed' AND (reason IS NULL OR reason = '')");
@$conn->query("UPDATE order_status_logs SET new_status = 'cancelled' WHERE new_status = '0' AND reason IS NOT NULL AND reason != ''");
@$conn->query("UPDATE order_status_logs SET new_status = 'confirmed' WHERE new_status = '0' AND old_status = 'pending' AND (reason IS NULL OR reason = '')");
@$conn->query("UPDATE order_status_logs SET new_status = 'confirmed' WHERE new_status = '0'");

// Human time ago helper function
if (!function_exists('humanTimeAgo')) {
    function humanTimeAgo($timestamp) {
        $diff = time() - $timestamp;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d, Y', $timestamp);
    }
}

// Fetch staff status summary statistics
$staffStats = [
    'total_changes' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'reserve_orders' => 0,
    'buy_orders' => 0
];
$staffSummaryRes = $conn->query("
    SELECT 
        COUNT(*) as total_changes,
        COUNT(CASE WHEN new_status = 'confirmed' THEN 1 END) as confirmed,
        COUNT(CASE WHEN new_status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN new_status = 'cancelled' THEN 1 END) as cancelled,
        COUNT(CASE WHEN order_type = 'reserve' THEN 1 END) as reserve_orders,
        COUNT(CASE WHEN order_type = 'buy' THEN 1 END) as buy_orders
    FROM order_status_logs
");
if ($staffSummaryRes && $sRow = $staffSummaryRes->fetch_assoc()) {
    $staffStats = $sRow;
}

// Fetch all staff status logs
$staffStatusLogs = [];
$staffLogResult = $conn->query("
    SELECT l.*, 
           COALESCE(g.image_path, g2.image_path) as image_path, 
           COALESCE(g.category, g2.category) as product_category,
           COALESCE(NULLIF(l.reason, ''), rp.cancellation_reason, '') as cancellation_reason,
           COALESCE(rp.cancellation_images, '') as cancellation_images
    FROM order_status_logs l
    LEFT JOIN reserve_products rp ON l.reservation_id = rp.id
    LEFT JOIN gowns g ON rp.product_id = g.id
    LEFT JOIN gowns g2 ON l.product_name = g2.name
    ORDER BY l.created_at DESC
");
if ($staffLogResult) {
    while ($row = $staffLogResult->fetch_assoc()) {
        $staffStatusLogs[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - ClosetGo Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-modern.css">
    <link rel="stylesheet" href="../css/admin_mobile_css.css">
    <link id="dynamicFavicon" rel="icon" href="../images/icon/JMB.jpg" type="image/jpeg">
    <script src="../js/dark-mode.js"></script>
    <style>
        /* Base Page Background matching calendar.php, gowns.php & dashboard.php */
        body {
            background: #000000 !important;
            color: #ffffff !important;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .shell {
            background: #000000 !important;
            min-height: 100vh;
            padding: 35px 35px 60px 35px;
        }

        /* Top Bar with Date Filters (Cols 1-4) & Search aligned on top of the last box (col 5) */
        .osa-top-bar {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 12px;
            align-items: center;
            position: relative;
        }

        .osa-date-filters {
            grid-column: 1 / 5;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
        }

        .chart-timeframe-btns {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 215, 0, 0.2);
            border-radius: 8px;
            padding: 3px;
            gap: 3px;
            position: relative;
        }

        .timeframe-btn {
            background: transparent;
            border: none;
            color: #aaaaaa;
            padding: 5px 12px;
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            line-height: 1.2;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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

        .osa-active-period-label {
            font-size: 0.78rem;
            color: #ffd700;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: rgba(255, 215, 0, 0.08);
            border: 1px solid rgba(255, 215, 0, 0.25);
            border-radius: 6px;
        }

        /* Custom Date Range Popover */
        .custom-date-popover {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            z-index: 100;
            background: #18191f;
            border: 1px solid rgba(255, 215, 0, 0.35);
            border-radius: 10px;
            padding: 12px 14px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.85);
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
            gap: 4px;
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
            padding: 5px 8px;
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
            padding: 5px 12px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
            height: 29px;
        }

        .custom-date-apply-btn:hover {
            background: #ffe24d;
            box-shadow: 0 0 8px rgba(255, 215, 0, 0.5);
        }

        .custom-date-close-btn {
            background: #27272a;
            color: #aaa;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 0.85rem;
            cursor: pointer;
            height: 29px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .custom-date-close-btn:hover {
            color: #fff;
            background: #3f3f46;
        }

        .osa-search-wrapper {
            grid-column: 5;
        }

        .osa-search-wrapper input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #7c6300;
            border-radius: 8px;
            background: #000000;
            color: #ffffff;
            font-size: 14px;
            box-shadow: inset 0 0 8px rgba(212, 175, 55, 0.4);
            transition: all 0.3s ease;
            outline: none;
            box-sizing: border-box;
        }

        .osa-search-wrapper input:hover,
        .osa-search-wrapper input:focus {
            background: #1a1a1a;
            border-color: #d4af37;
            box-shadow: inset 0 0 12px rgba(212, 175, 55, 0.6);
            color: #ffffff;
        }

        .osa-search-wrapper input::placeholder {
            color: #888888;
        }

        /* 5 Stats Boxes matching dashboard.php & calendar.php */
        .cal-stats-overview {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }

        .cal-stat-item {
            border-radius: 10px;
            padding: 6px 10px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 0 20px rgba(255, 215, 0, 0.22);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(26, 26, 26, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            user-select: none;
        }

        .cal-stat-item:hover {
            box-shadow: inset 0 0 22px rgba(255, 215, 0, 0.38);
            transform: translateY(-2px);
            border-color: rgba(255, 215, 0, 0.35);
        }

        .cal-stat-item.active {
            border-color: rgba(255, 215, 0, 0.75);
            background: linear-gradient(145deg, rgba(124, 99, 0, 0.3), rgba(26, 26, 26, 0.8));
            box-shadow: inset 0 0 25px rgba(255, 215, 0, 0.45), 0 2px 10px rgba(0, 0, 0, 0.5);
            transform: translateY(-2px);
        }

        .cal-stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 6px;
        }

        .cal-stat-title {
            color: #666666;
            font-size: 0.72rem;
            font-weight: 400;
            letter-spacing: 0.4px;
        }

        .cal-stat-item.active .cal-stat-title {
            color: #ffd700;
            font-weight: 600;
        }

        .cal-stat-content {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .cal-stat-number {
            color: #cccccc;
            font-size: 1.15rem;
            font-weight: 500;
            line-height: 1.15;
            margin: 0;
        }

        .cal-stat-item.active .cal-stat-number {
            color: #ffffff;
        }

        .cal-stat-change {
            font-size: 0.9rem;
            font-weight: 500;
            color: #ffd700 !important;
            align-self: flex-start;
        }
        /* Table Card & Content */
        .osa-table-card {
            background: #111111;
            border: 1px solid #7c6300;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: inset 0 0 20px rgba(255, 215, 0, 0.12), 0 8px 30px rgba(0, 0, 0, 0.6);
        }

        .osa-table-responsive {
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 215, 0, 0.3) transparent;
        }

        .osa-logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
            text-align: left;
        }

        .osa-logs-table th {
            padding: 11px 14px;
            color: #FFD700;
            font-weight: 600;
            font-size: 0.76rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border-bottom: 1px solid rgba(255, 215, 0, 0.25);
            background: #000000;
            white-space: nowrap;
        }

        .osa-logs-table td {
            padding: 10px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
            color: #cccccc;
            background: transparent;
        }

        .date-cell {
            color: #cccccc;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        .time-cell {
            color: #cccccc;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        .customer-cell-name {
            color: #cccccc;
            font-size: 0.82rem;
            max-width: 160px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .res-code-text {
            color: #cccccc;
            font-family: monospace;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        .changed-by-text {
            color: #cccccc;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        .order-type-text {
            font-size: 0.82rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .order-type-text.type-buy {
            color: #34d399;
        }

        .order-type-text.type-reserve {
            color: #ffd700;
        }

        .status-only-text {
            color: #cccccc;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        .btn-view-cancel {
            background: rgba(244, 67, 54, 0.12);
            border: 1px solid rgba(244, 67, 54, 0.35);
            color: #fca5a5;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 0.72rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
            outline: none;
        }

        .btn-view-cancel:hover {
            background: rgba(244, 67, 54, 0.28);
            border-color: #f87171;
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Cancellation Modal Styles */
        .cancel-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            box-sizing: border-box;
        }

        .cancel-modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.82);
            backdrop-filter: blur(5px);
        }

        .cancel-modal-dialog {
            position: relative;
            background: #111113;
            border: 1.5px solid #7c6300;
            border-radius: 12px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.9), inset 0 0 20px rgba(255, 215, 0, 0.12);
            z-index: 10;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: cancelModalFadeIn 0.2s ease-out;
        }

        @keyframes cancelModalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .cancel-modal-header {
            padding: 14px 18px;
            background: #18181c;
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .cancel-modal-close {
            background: transparent;
            border: none;
            color: #888;
            font-size: 1.4rem;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            transition: color 0.2s;
        }

        .cancel-modal-close:hover {
            color: #fff;
        }

        .cancel-modal-body {
            padding: 18px;
            max-height: 75vh;
            overflow-y: auto;
        }

        .cancel-modal-meta {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 14px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .cancel-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.82rem;
            gap: 8px;
        }

        .cancel-meta-label {
            color: #888;
            font-weight: 500;
        }

        .cancel-meta-value {
            color: #eee;
            font-weight: 600;
            text-align: right;
        }

        .cancel-meta-value.gold {
            color: #ffd700;
            font-family: monospace;
        }

        .cancel-reason-box {
            background: rgba(244, 67, 54, 0.08);
            border: 1.5px solid rgba(244, 67, 54, 0.35);
            border-radius: 8px;
            padding: 12px 14px;
        }

        .cancel-reason-title {
            color: #f87171;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .cancel-reason-content {
            color: #f3f4f6;
            font-size: 0.86rem;
            line-height: 1.45;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .cancel-modal-footer {
            padding: 12px 18px;
            background: #18181c;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: flex-end;
        }

        .btn-cancel-modal-close {
            background: #27272a;
            border: 1px solid #3f3f46;
            color: #e4e4e7;
            padding: 7px 16px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-cancel-modal-close:hover {
            background: #3f3f46;
            color: #fff;
        }

        .osa-logs-table tbody tr {
            transition: background 0.2s ease;
        }

        .osa-logs-table tbody tr:hover {
            background: rgba(255, 215, 0, 0.05);
        }

        .osa-table-footer {
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: #888;
            background: #080808;
            border-top: 1px solid rgba(255, 215, 0, 0.15);
        }

        .osa-empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #777;
        }

        .osa-empty-state i {
            font-size: 3rem;
            color: #444;
            margin-bottom: 14px;
            display: block;
        }

        .osa-empty-state h3 {
            color: #bbb;
            font-size: 1.1rem;
            margin-bottom: 6px;
        }

        .osa-empty-state p {
            color: #666;
            font-size: 0.85rem;
            margin: 0;
        }

        @media (max-width: 1024px) {
            .cal-stats-overview {
                grid-template-columns: repeat(3, 1fr);
            }
            .osa-top-bar {
                grid-template-columns: 1fr;
            }
            .osa-date-filters {
                grid-column: 1;
            }
            .osa-search-wrapper {
                grid-column: 1;
            }
        }

        @media (max-width: 768px) {
            .shell { padding: 20px 14px 50px 14px; }
            .cal-stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            .osa-top-bar {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .osa-date-filters {
                width: 100%;
                overflow-x: auto;
                padding-bottom: 4px;
            }
            .osa-search-wrapper {
                width: 100%;
                display: block;
            }
            .cal-stat-change {
                font-size: 0.78rem !important;
            }
        }

        @media (max-width: 480px) {
            .cal-stats-overview {
                grid-template-columns: 1fr;
            }
        }
        #mobilePageTitle {
            display: none !important;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="shell">
        <!-- Top Bar with Date Filters (Cols 1-4) & Search Bar (Col 5) -->
        <div class="osa-top-bar">
            <div class="osa-date-filters">
                <div class="chart-timeframe-btns">
                    <button type="button" class="timeframe-btn active" id="btnPeriodToday" onclick="changeDatePeriod('today')">Today</button>
                    <button type="button" class="timeframe-btn" id="btnPeriodWeek" onclick="changeDatePeriod('week')">1 Week</button>
                    <button type="button" class="timeframe-btn" id="btnPeriodMonth" onclick="changeDatePeriod('month')">1 Month</button>
                    <button type="button" class="timeframe-btn" id="btnPeriodYear" onclick="changeDatePeriod('year')">1 Year</button>
                    <button type="button" class="timeframe-btn" id="btnPeriodAll" onclick="changeDatePeriod('all')">All</button>
                    <button type="button" class="timeframe-btn" id="btnChooseDate" onclick="toggleCustomDatePopover()">
                        <i class="far fa-calendar-alt"></i> Choose Date
                    </button>
                </div>

                <!-- Custom Date Range Popover -->
                <div class="custom-date-popover" id="osaCustomDatePopover" style="display: none;">
                    <div class="custom-date-inner">
                        <div class="custom-date-field">
                            <label>From</label>
                            <input type="date" id="osaStartDate" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="custom-date-field">
                            <label>To</label>
                            <input type="date" id="osaEndDate" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <button type="button" class="custom-date-apply-btn" onclick="applyCustomDateRange()">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <button type="button" class="custom-date-close-btn" onclick="toggleCustomDatePopover(false)" title="Close">&times;</button>
                    </div>
                </div>

                <div class="osa-active-period-label" id="osaPeriodLabel">
                    <i class="fas fa-calendar-check"></i> <span id="osaPeriodLabelText">Today</span>
                </div>
            </div>

            <div class="osa-search-wrapper">
                <input type="text" id="searchName" placeholder="Search..." onkeyup="handleSearchInput()">
            </div>
        </div>

        <!-- 5 Stat Boxes: Confirmed, Completed, Cancelled, Reserve, Buy -->
        <div class="cal-stats-overview">
            <div class="cal-stat-item" onclick="applyStaffFilter('confirmed', this)">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Confirmed</span>
                    <span class="cal-stat-change" id="statBadgeConfirmed"><?php echo (int)($staffStats['confirmed'] ?? 0); ?> items</span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number" id="statNumConfirmed" style="color: #2196f3; text-shadow: 0 0 12px rgba(33, 150, 243, 0.4);"><?php echo (int)($staffStats['confirmed'] ?? 0); ?></p>
                </div>
            </div>

            <div class="cal-stat-item" onclick="applyStaffFilter('completed', this)">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Completed</span>
                    <span class="cal-stat-change" id="statBadgeCompleted"><?php echo (int)($staffStats['completed'] ?? 0); ?> items</span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number" id="statNumCompleted" style="color: #4CAF50; text-shadow: 0 0 12px rgba(76, 175, 80, 0.4);"><?php echo (int)($staffStats['completed'] ?? 0); ?></p>
                </div>
            </div>

            <div class="cal-stat-item" onclick="applyStaffFilter('cancelled', this)">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Cancelled</span>
                    <span class="cal-stat-change" id="statBadgeCancelled"><?php echo (int)($staffStats['cancelled'] ?? 0); ?> items</span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number" id="statNumCancelled" style="color: #f44336; text-shadow: 0 0 12px rgba(244, 67, 54, 0.4);"><?php echo (int)($staffStats['cancelled'] ?? 0); ?></p>
                </div>
            </div>

            <div class="cal-stat-item" onclick="applyStaffFilter('reserve', this)">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Reserve</span>
                    <span class="cal-stat-change" id="statBadgeReserve"><?php echo (int)($staffStats['reserve_orders'] ?? 0); ?> items</span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number" id="statNumReserve" style="color: #f59e0b; text-shadow: 0 0 12px rgba(245, 158, 11, 0.4);"><?php echo (int)($staffStats['reserve_orders'] ?? 0); ?></p>
                </div>
            </div>

            <div class="cal-stat-item" onclick="applyStaffFilter('buy', this)">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Buy</span>
                    <span class="cal-stat-change" id="statBadgeBuy"><?php echo (int)($staffStats['buy_orders'] ?? 0); ?> items</span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number" id="statNumBuy" style="color: #34d399; text-shadow: 0 0 12px rgba(52, 211, 153, 0.4);"><?php echo (int)($staffStats['buy_orders'] ?? 0); ?></p>
                </div>
            </div>
        </div>

        <!-- Staff Logs Table -->
        <div class="osa-table-card">
            <?php if (empty($staffStatusLogs)): ?>
                <div class="osa-empty-state">
                    <i class="fas fa-clipboard-check"></i>
                    <h3>No Audit Logs Recorded</h3>
                    <p>Status modifications performed by staff or administrators will appear here in chronological order.</p>
                </div>
            <?php else: ?>
                <div class="osa-table-responsive">
                    <table class="osa-logs-table">
                        <thead>
                            <tr>
                                <th>Product Details</th>
                                <th>Customer</th>
                                <th>Reservation Code</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Changed By</th>
                                <th>Order Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="osaTableBody">
                            <?php foreach ($staffStatusLogs as $log): 
                                $rawStatus = strtolower(trim($log['new_status'] ?? ''));
                                if ($rawStatus === '0' || empty($rawStatus) || !in_array($rawStatus, ['pending', 'confirmed', 'completed', 'cancelled'])) {
                                    if (!empty($log['cancellation_reason']) || !empty($log['reason'])) {
                                        $status = 'cancelled';
                                    } elseif (($log['old_status'] ?? '') === 'confirmed') {
                                        $status = 'completed';
                                    } elseif (($log['old_status'] ?? '') === 'pending') {
                                        $status = 'confirmed';
                                    } else {
                                        $status = 'confirmed';
                                    }
                                } else {
                                    $status = $rawStatus;
                                }
                                $orderType = strtolower($log['order_type'] ?? 'reserve');
                                $dateStr = date('M d, Y h:i A', strtotime($log['created_at']));
                                $timeStr = date('h:i A', strtotime($log['created_at']));
                                $timeAgo = humanTimeAgo(strtotime($log['created_at']));
                                $initials = strtoupper(substr($log['staff_name'] ?? 'S', 0, 1));
                                $isStaff = ($log['changed_by_type'] ?? 'staff') === 'staff';
                                $resCodeDisplay = $log['reservation_code'] ?: ($log['reservation_group_id'] ?: '#' . $log['reservation_id']);
                                $custName = $log['customer_name'] ?? 'Walk-In / Customer';
                                $prodName = $log['product_name'] ?? 'Product';
                                $staffName = $log['staff_name'] ?? 'Staff Member';
                                $searchPayload = strtolower($prodName . ' ' . $custName . ' ' . $resCodeDisplay . ' ' . $staffName . ' ' . $orderType . ' ' . $status . ' ' . $timeStr . ' ' . ($log['staff_email'] ?? '') . ' ' . ($log['reason'] ?? ''));
                            ?>
                            <tr class="osa-log-row" 
                                data-status="<?php echo htmlspecialchars($status); ?>" 
                                data-type="<?php echo htmlspecialchars($orderType); ?>"
                                data-date="<?php echo date('Y-m-d', strtotime($log['created_at'])); ?>"
                                data-search="<?php echo htmlspecialchars($searchPayload); ?>">
                                
                                <!-- 1. Product Details -->
                                <td>
                                    <div class="product-mini-cell">
                                        <?php if (!empty($log['image_path'])): ?>
                                            <img src="../<?php echo htmlspecialchars($log['image_path']); ?>" alt="Product" class="product-mini-thumb" onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <div class="product-mini-icon"><i class="fas fa-tshirt"></i></div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="product-cell-name" title="<?php echo htmlspecialchars($prodName); ?>">
                                                <?php echo htmlspecialchars($prodName); ?>
                                            </div>
                                            <?php if (!empty($log['product_category'])): ?>
                                                <div class="product-cell-cat"><?php echo htmlspecialchars($log['product_category']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- 2. Customer -->
                                <td>
                                    <div class="customer-cell-name" title="<?php echo htmlspecialchars($custName); ?>">
                                        <?php echo htmlspecialchars($custName); ?>
                                    </div>
                                </td>

                                <!-- 3. Reservation Code -->
                                <td>
                                    <span class="res-code-text"><?php echo htmlspecialchars($resCodeDisplay); ?></span>
                                </td>

                                <!-- 4. Date -->
                                <td>
                                    <div class="date-cell" title="<?php echo htmlspecialchars($dateStr); ?>">
                                        <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                    </div>
                                </td>

                                <!-- 5. Time -->
                                <td>
                                    <div class="time-cell" title="<?php echo htmlspecialchars($timeAgo); ?>">
                                        <?php echo $timeStr; ?>
                                    </div>
                                </td>

                                <!-- 6. Changed By -->
                                <td>
                                    <div class="changed-by-text" title="<?php echo htmlspecialchars($staffName); ?>">
                                        <?php echo htmlspecialchars($staffName); ?>
                                    </div>
                                </td>

                                <!-- 7. Order Type -->
                                <td>
                                    <span class="order-type-text type-<?php echo htmlspecialchars($orderType); ?>">
                                        <?php echo $orderType === 'buy' ? 'Buy' : 'Reserve'; ?>
                                    </span>
                                </td>

                                <!-- 8. Status -->
                                <td>
                                    <?php if ($status === 'cancelled'): 
                                        $cancelReason = !empty($log['cancellation_reason']) ? $log['cancellation_reason'] : (!empty($log['reason']) ? $log['reason'] : 'No explanation recorded.');
                                        $cancelPayload = [
                                            'code' => $resCodeDisplay,
                                            'customer' => $custName,
                                            'product' => $prodName,
                                            'cancelled_by' => $staffName . ' (' . ucfirst($log['changed_by_type'] ?? 'staff') . ')',
                                            'date' => $dateStr,
                                            'reason' => $cancelReason,
                                            'images' => $log['cancellation_images'] ?? ''
                                        ];
                                    ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span class="status-only-text">Cancelled</span>
                                            <button type="button" class="btn-view-cancel" onclick='openCancellationView(<?php echo htmlspecialchars(json_encode($cancelPayload), ENT_QUOTES, "UTF-8"); ?>)'>
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="status-only-text"><?php echo ucfirst($status); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="osa-empty-state" id="osaNoResultsState" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h3>No Matching Records Found</h3>
                    <p>No status changes match your current filter or search criteria.</p>
                </div>

                <div class="osa-table-footer">
                    <div>
                        Showing <span id="osaVisibleCount"><?php echo count($staffStatusLogs); ?></span> of <span id="osaTotalInPeriodCount"><?php echo count($staffStatusLogs); ?></span> entries
                    </div>
                    <div id="osaFilterSummary">Filter: All</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cancellation Message Modal -->
    <div id="cancelMessageModal" class="cancel-modal" style="display: none;">
        <div class="cancel-modal-backdrop" onclick="closeCancellationView()"></div>
        <div class="cancel-modal-dialog">
            <div class="cancel-modal-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-circle" style="color: #f87171; font-size: 18px;"></i>
                    <h3 style="margin: 0; color: #fff; font-size: 1.05rem; font-weight: 600;">Cancelled Message</h3>
                </div>
                <button type="button" class="cancel-modal-close" onclick="closeCancellationView()" aria-label="Close">&times;</button>
            </div>
            <div class="cancel-modal-body">
                <div class="cancel-modal-meta">
                    <div class="cancel-meta-row">
                        <span class="cancel-meta-label">Reservation Code:</span>
                        <span class="cancel-meta-value gold" id="cmResCode">-</span>
                    </div>
                    <div class="cancel-meta-row">
                        <span class="cancel-meta-label">Customer:</span>
                        <span class="cancel-meta-value" id="cmCustomer">-</span>
                    </div>
                    <div class="cancel-meta-row">
                        <span class="cancel-meta-label">Product:</span>
                        <span class="cancel-meta-value" id="cmProduct">-</span>
                    </div>
                    <div class="cancel-meta-row">
                        <span class="cancel-meta-label">Cancelled By:</span>
                        <span class="cancel-meta-value" id="cmCancelledBy">-</span>
                    </div>
                    <div class="cancel-meta-row">
                        <span class="cancel-meta-label">Date & Time:</span>
                        <span class="cancel-meta-value" id="cmDateTime">-</span>
                    </div>
                </div>

                <div class="cancel-reason-box">
                    <div class="cancel-reason-title">
                        <i class="fas fa-comment-dots"></i> Explanation & Reason
                    </div>
                    <div class="cancel-reason-content" id="cmReason">
                        No explanation recorded.
                    </div>
                </div>

                <div id="cmImagesContainer" style="display: none; margin-top: 14px;">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #aaa; margin-bottom: 6px;">
                        <i class="fas fa-images"></i> Attached Photos:
                    </div>
                    <div id="cmImagesList" style="display: flex; gap: 8px; flex-wrap: wrap;"></div>
                </div>
            </div>
            <div class="cancel-modal-footer">
                <button type="button" class="btn-cancel-modal-close" onclick="closeCancellationView()">Close</button>
            </div>
        </div>
    </div>

    <script>
    function openCancellationView(data) {
        document.getElementById('cmResCode').textContent = data.code || '-';
        document.getElementById('cmCustomer').textContent = data.customer || '-';
        document.getElementById('cmProduct').textContent = data.product || '-';
        document.getElementById('cmCancelledBy').textContent = data.cancelled_by || '-';
        document.getElementById('cmDateTime').textContent = data.date || '-';
        document.getElementById('cmReason').textContent = data.reason || 'No explanation recorded.';

        const imgContainer = document.getElementById('cmImagesContainer');
        const imgList = document.getElementById('cmImagesList');
        imgList.innerHTML = '';

        let images = [];
        if (data.images) {
            try {
                images = typeof data.images === 'string' ? JSON.parse(data.images) : data.images;
            } catch(e) {
                images = [];
            }
        }

        if (Array.isArray(images) && images.length > 0) {
            images.forEach(img => {
                const fullPath = (img.startsWith('http') || img.startsWith('/') || img.startsWith('../')) ? img : ('../' + img);
                const thumb = document.createElement('img');
                thumb.src = fullPath;
                thumb.alt = 'Cancellation attachment';
                thumb.style = 'width: 70px; height: 70px; object-fit: cover; border-radius: 6px; border: 1px solid #f44336; cursor: pointer;';
                thumb.title = 'Click to open full photo in new tab';
                thumb.onclick = () => window.open(fullPath, '_blank');
                imgList.appendChild(thumb);
            });
            imgContainer.style.display = 'block';
        } else {
            imgContainer.style.display = 'none';
        }

        document.getElementById('cancelMessageModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeCancellationView() {
        const modal = document.getElementById('cancelMessageModal');
        if (modal) modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCancellationView();
        }
    });
    // Preload button click sound
    const buttonClickAudio = new Audio('../ringtone/button_click.MP3');
    buttonClickAudio.volume = 0.3;
    buttonClickAudio.preload = 'auto';

    function playButtonClickSound() {
        buttonClickAudio.currentTime = 0;
        buttonClickAudio.play().catch(() => {});
    }

    document.addEventListener('DOMContentLoaded', function() {
        const interactiveEls = document.querySelectorAll('button, .cal-stat-item, a[href]');
        interactiveEls.forEach(el => {
            el.addEventListener('click', function() {
                if (!this.closest('.sidebar')) {
                    playButtonClickSound();
                }
            });
        });
    });

    const serverTodayStr = '<?php echo date('Y-m-d'); ?>';
    let currentDatePeriod = 'today'; // Today is default!
    let filterStartDate = serverTodayStr;
    let filterEndDate = serverTodayStr;
    let currentFilter = 'all';
    let searchQuery = '';

    function formatYMD(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function getDateMinusDays(days) {
        const parts = serverTodayStr.split('-').map(Number);
        const d = new Date(parts[0], parts[1] - 1, parts[2]);
        d.setDate(d.getDate() - days);
        return formatYMD(d);
    }

    function formatDateDisplay(ymdStr) {
        if (!ymdStr) return '';
        const parts = ymdStr.split('-').map(Number);
        const d = new Date(parts[0], parts[1] - 1, parts[2]);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function changeDatePeriod(period) {
        currentDatePeriod = period;
        toggleCustomDatePopover(false);

        // Update active button state
        document.querySelectorAll('.timeframe-btn').forEach(btn => btn.classList.remove('active'));

        const labelText = document.getElementById('osaPeriodLabelText');

        if (period === 'today') {
            filterStartDate = serverTodayStr;
            filterEndDate = serverTodayStr;
            const b = document.getElementById('btnPeriodToday');
            if (b) b.classList.add('active');
            if (labelText) labelText.textContent = 'Today (' + formatDateDisplay(serverTodayStr) + ')';
        } else if (period === 'week') {
            filterStartDate = getDateMinusDays(6);
            filterEndDate = serverTodayStr;
            const b = document.getElementById('btnPeriodWeek');
            if (b) b.classList.add('active');
            if (labelText) labelText.textContent = '1 Week (' + formatDateDisplay(filterStartDate) + ' - ' + formatDateDisplay(filterEndDate) + ')';
        } else if (period === 'month') {
            filterStartDate = getDateMinusDays(29);
            filterEndDate = serverTodayStr;
            const b = document.getElementById('btnPeriodMonth');
            if (b) b.classList.add('active');
            if (labelText) labelText.textContent = '1 Month (' + formatDateDisplay(filterStartDate) + ' - ' + formatDateDisplay(filterEndDate) + ')';
        } else if (period === 'year') {
            filterStartDate = getDateMinusDays(364);
            filterEndDate = serverTodayStr;
            const b = document.getElementById('btnPeriodYear');
            if (b) b.classList.add('active');
            if (labelText) labelText.textContent = '1 Year (' + formatDateDisplay(filterStartDate) + ' - ' + formatDateDisplay(filterEndDate) + ')';
        } else if (period === 'all') {
            filterStartDate = null;
            filterEndDate = null;
            const b = document.getElementById('btnPeriodAll');
            if (b) b.classList.add('active');
            if (labelText) labelText.textContent = 'All Time';
        }

        filterRows();
    }

    function toggleCustomDatePopover(forceState) {
        const popover = document.getElementById('osaCustomDatePopover');
        if (!popover) return;
        if (typeof forceState === 'boolean') {
            popover.style.display = forceState ? 'block' : 'none';
        } else {
            popover.style.display = (popover.style.display === 'none' || !popover.style.display) ? 'block' : 'none';
        }
    }

    function applyCustomDateRange() {
        const sInput = document.getElementById('osaStartDate');
        const eInput = document.getElementById('osaEndDate');
        let s = sInput ? sInput.value : '';
        let e = eInput ? eInput.value : '';

        if (!s && !e) {
            alert('Please choose a date.');
            return;
        }
        if (s && !e) e = s;
        if (!s && e) s = e;

        if (s > e) {
            const tmp = s;
            s = e;
            e = tmp;
        }

        filterStartDate = s;
        filterEndDate = e;
        currentDatePeriod = 'custom';

        document.querySelectorAll('.timeframe-btn').forEach(btn => btn.classList.remove('active'));
        const btnChoose = document.getElementById('btnChooseDate');
        if (btnChoose) btnChoose.classList.add('active');

        const labelText = document.getElementById('osaPeriodLabelText');
        if (labelText) {
            if (s === e) {
                labelText.textContent = formatDateDisplay(s);
            } else {
                labelText.textContent = formatDateDisplay(s) + ' - ' + formatDateDisplay(e);
            }
        }

        toggleCustomDatePopover(false);
        filterRows();
    }

    // Close custom date popover when clicking outside
    document.addEventListener('click', function(e) {
        const popover = document.getElementById('osaCustomDatePopover');
        const btnChoose = document.getElementById('btnChooseDate');
        if (popover && popover.style.display === 'block') {
            if (!popover.contains(e.target) && !btnChoose.contains(e.target)) {
                popover.style.display = 'none';
            }
        }
    });

    function applyStaffFilter(filter, el) {
        if (currentFilter === filter) {
            currentFilter = 'all';
            document.querySelectorAll('.cal-stat-item').forEach(b => b.classList.remove('active'));
        } else {
            currentFilter = filter;
            document.querySelectorAll('.cal-stat-item').forEach(b => b.classList.remove('active'));
            if (el) el.classList.add('active');
        }
        filterRows();
    }

    function handleSearchInput() {
        const input = document.getElementById('searchName');
        searchQuery = input ? input.value.trim().toLowerCase() : '';
        filterRows();
    }

    function isRowInDateRange(rowDateStr) {
        if (currentDatePeriod === 'all') return true;
        if (!rowDateStr) return false;
        if (filterStartDate && rowDateStr < filterStartDate) return false;
        if (filterEndDate && rowDateStr > filterEndDate) return false;
        return true;
    }

    function filterRows() {
        const rows = document.querySelectorAll('.osa-log-row');
        const noResults = document.getElementById('osaNoResultsState');
        const countDisplay = document.getElementById('osaVisibleCount');
        const totalInPeriodDisplay = document.getElementById('osaTotalInPeriodCount');
        const summaryDisplay = document.getElementById('osaFilterSummary');

        let visibleCount = 0;
        let periodTotal = 0;
        let counts = {
            confirmed: 0,
            completed: 0,
            cancelled: 0,
            reserve: 0,
            buy: 0
        };

        // First pass: compute breakdown for all rows inside the active date period
        rows.forEach(row => {
            const rowDate = row.getAttribute('data-date') || '';
            const inDate = isRowInDateRange(rowDate);

            if (inDate) {
                periodTotal++;
                const status = row.getAttribute('data-status');
                const type = row.getAttribute('data-type');
                if (counts[status] !== undefined) counts[status]++;
                if (counts[type] !== undefined) counts[type]++;
            }
        });

        // Update 5 stat card numbers and badge labels
        const statMap = [
            { numId: 'statNumConfirmed', badgeId: 'statBadgeConfirmed', val: counts.confirmed },
            { numId: 'statNumCompleted', badgeId: 'statBadgeCompleted', val: counts.completed },
            { numId: 'statNumCancelled', badgeId: 'statBadgeCancelled', val: counts.cancelled },
            { numId: 'statNumReserve', badgeId: 'statBadgeReserve', val: counts.reserve },
            { numId: 'statNumBuy', badgeId: 'statBadgeBuy', val: counts.buy }
        ];
        statMap.forEach(item => {
            const numEl = document.getElementById(item.numId);
            const badgeEl = document.getElementById(item.badgeId);
            if (numEl) numEl.textContent = item.val;
            if (badgeEl) badgeEl.textContent = item.val + ' items';
        });

        // Second pass: filter row visibility based on date range, status/type filter, and search text
        rows.forEach(row => {
            const rowDate = row.getAttribute('data-date') || '';
            const inDate = isRowInDateRange(rowDate);

            if (!inDate) {
                row.style.display = 'none';
                return;
            }

            const status = row.getAttribute('data-status');
            const type = row.getAttribute('data-type');
            const searchData = row.getAttribute('data-search') || '';

            let matchesFilter = false;
            if (currentFilter === 'all') {
                matchesFilter = true;
            } else if (currentFilter === 'buy' || currentFilter === 'reserve') {
                matchesFilter = (type === currentFilter);
            } else {
                matchesFilter = (status === currentFilter);
            }

            let matchesSearch = true;
            if (searchQuery.length > 0) {
                matchesSearch = searchData.includes(searchQuery);
            }

            if (matchesFilter && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (countDisplay) countDisplay.textContent = visibleCount;
        if (totalInPeriodDisplay) totalInPeriodDisplay.textContent = periodTotal;
        if (noResults) {
            noResults.style.display = (visibleCount === 0 && rows.length > 0) ? 'block' : 'none';
        }
        if (summaryDisplay) {
            let filterName = currentFilter.charAt(0).toUpperCase() + currentFilter.slice(1);
            if (currentFilter === 'all') filterName = 'All';
            if (currentFilter === 'buy') filterName = 'Buy';
            if (currentFilter === 'reserve') filterName = 'Reserve';
            if (searchQuery) filterName += ` (Search: "${searchQuery}")`;
            summaryDisplay.textContent = `Filter: ${filterName}`;
        }
    }

    // Apply default 'today' period on initial page load
    document.addEventListener('DOMContentLoaded', function() {
        changeDatePeriod('today');
    });
    </script>
</body>
</html>
