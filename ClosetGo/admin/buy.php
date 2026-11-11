<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: adminlogin.php");
    exit();
}

require_once __DIR__ . '/../database/db_config.php';
$database = new Database();
$conn = $database->getConnection();

// Fetch summary stats for Buy Orders
$buyStats = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0
];
$statsRes = $conn->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN order_status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN order_status = 'confirmed' THEN 1 END) as confirmed,
        COUNT(CASE WHEN order_status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled
    FROM reserve_products 
    WHERE product_status = 'buy' OR payment_method = 'buy'
");
if ($statsRes && $row = $statsRes->fetch_assoc()) {
    $buyStats = $row;
}

// Fetch all Buy Orders with product and customer details
$buyOrders = [];
$ordersRes = $conn->query("
    SELECT rp.*,
           COALESCE(g.image_path, '') as product_image,
           COALESCE(g.category, rp.category) as resolved_category,
           COALESCE(g.purchase_price, rp.price) as gown_purchase_price,
           u.phone as customer_phone,
           u.email as customer_email
    FROM reserve_products rp
    LEFT JOIN gowns g ON rp.product_id = g.id
    LEFT JOIN system_users u ON rp.user_id = u.id
    WHERE rp.product_status = 'buy' OR rp.payment_method = 'buy'
    ORDER BY rp.id DESC
");
if ($ordersRes) {
    while ($row = $ordersRes->fetch_assoc()) {
        $buyOrders[] = $row;
    }
}

// Helper function for human relative time
function humanTimeAgo($time) {
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $time);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Orders - ClosetGo Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-modern.css">
    <link rel="stylesheet" href="../css/admin_mobile_css.css">
    <link id="dynamicFavicon" rel="icon" href="../images/icon/JMB.jpg" type="image/jpeg">
    <script src="../js/dark-mode.js"></script>
    <style>
        /* Base Page Background matching order_status_actions.php, calendar.php & dashboard.php */
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

        /* Top Bar with Date Filters (Cols 1-4) & Search Bar (Col 5) */
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

        /* 5 Stats Boxes matching order_status_actions.php */
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

        .osa-logs-table tbody tr {
            transition: background 0.2s ease;
            cursor: default;
        }

        .osa-logs-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        /* Desktop Column Widths (Action hidden on laptop, shown on mobile only) */
        .osa-logs-table th.col-prod-details,
        .osa-logs-table td.col-prod-details {
            width: 35%;
        }

        .osa-logs-table th.col-customer,
        .osa-logs-table td.col-customer {
            width: 25%;
        }

        .osa-logs-table th.col-order-code,
        .osa-logs-table td.col-order-code {
            width: 16%;
        }

        .osa-logs-table th.col-date,
        .osa-logs-table td.col-date {
            width: 13%;
        }

        .osa-logs-table th.col-status,
        .osa-logs-table td.col-status {
            width: 11%;
        }

        .osa-logs-table th.col-action,
        .osa-logs-table td.col-action {
            display: none !important;
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

        /* Status badges: text only, no box/background, status color preserved */
        .mini-status-chip {
            font-size: 0.82rem;
            font-weight: 600;
            display: inline-block;
            text-transform: capitalize;
            white-space: nowrap;
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
            border-radius: 0 !important;
        }

        .mini-status-chip.chip-pending {
            color: #ffb74d !important;
        }

        .mini-status-chip.chip-confirmed {
            color: #64b5f6 !important;
        }

        .mini-status-chip.chip-completed {
            color: #81c784 !important;
        }

        .mini-status-chip.chip-cancelled {
            color: #e57373 !important;
        }

        /* Order Type Badge */
        .order-type-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.72rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        /* Customer Cell: no icon/box, light gray text */
        .customer-cell-name {
            color: #cccccc;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .customer-cell-phone {
            font-size: 0.72rem;
            color: #999999;
            margin-top: 2px;
        }

        /* Code Pill: text only, no box, light gray */
        .code-pill {
            font-family: monospace;
            font-size: 0.82rem;
            color: #cccccc;
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
            white-space: nowrap;
        }

        .date-cell {
            color: #cccccc;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        .price-text-gold {
            color: #ffd700;
            font-weight: 600;
            font-size: 0.88rem;
        }

        .price-sub {
            font-size: 0.72rem;
            color: #888;
        }

        .payment-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.72rem;
            padding: 2px 7px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.05);
            color: #bbb;
            text-transform: capitalize;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        /* Action: text only, light gray, no box */
        .btn-action-view {
            padding: 0;
            border: none;
            background: transparent;
            color: #cccccc;
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            transition: color 0.2s ease;
            display: inline-block;
            outline: none;
            text-decoration: none;
        }

        .btn-action-view:hover {
            background: transparent;
            border: none;
            color: #ffffff;
            transform: none;
            box-shadow: none;
            text-decoration: underline;
        }

        /* Slide Panel Styling (matching calendar.php) */
        .slide-panel {
            position: fixed;
            top: 0;
            right: -100%;
            min-width: 460px;
            max-width: 620px;
            width: 45%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: #0a0a0a;
            border-left: 2px solid rgba(212, 175, 55, 0.4);
            box-shadow: -10px 0 40px rgba(0, 0, 0, 0.8), -2px 0 15px rgba(201, 170, 46, 0.2);
            transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 100001;
        }

        .slide-panel.slide-panel-open {
            right: 0;
        }

        .slide-panel-content {
            height: 100%;
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .slide-panel-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0a0a0a;
        }

        .slide-panel-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #ffd700;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .close-panel-btn {
            background: transparent;
            border: none;
            color: #888;
            font-size: 1.25rem;
            cursor: pointer;
            transition: color 0.2s;
            padding: 4px;
            line-height: 1;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-panel-btn:hover {
            color: #ffd700;
            background: rgba(255, 215, 0, 0.1);
        }

        .slide-panel-body {
            flex: 1;
            height: 100%;
            overflow-y: auto;
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-section-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 215, 0, 0.12);
            border-radius: 10px;
            padding: 14px 16px;
        }

        .modal-section-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: #ffd700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .modal-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        /* Ensure code and status stay in one row on all devices */
        .modal-grid-2.single-row {
            grid-template-columns: 1fr 1fr !important;
            gap: 12px !important;
        }

        .modal-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .modal-info-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .modal-info-label {
            font-size: 0.72rem;
            color: #888;
            text-transform: uppercase;
        }

        .modal-info-val {
            font-size: 0.88rem;
            color: #e5e5e5;
            font-weight: 500;
        }

        .modal-prod-row {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .modal-prod-img {
            width: 64px;
            height: 64px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #7c6300;
            background: #000;
        }

        .modal-sizes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 8px;
        }

        .size-measurement-pill {
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid rgba(255, 215, 0, 0.15);
            border-radius: 6px;
            padding: 6px 10px;
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .size-measurement-pill .m-label {
            font-size: 0.68rem;
            color: #999;
        }

        .size-measurement-pill .m-val {
            font-size: 0.82rem;
            color: #ffd700;
            font-weight: 600;
        }

        .proof-img-thumb {
            max-width: 140px;
            max-height: 90px;
            border-radius: 6px;
            border: 1px solid #7c6300;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .proof-img-thumb:hover {
            transform: scale(1.05);
            border-color: #ffd700;
        }

        /* Responsive Breakpoints */
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
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 8px !important;
            }
            .cal-stat-item:last-child {
                grid-column: span 2;
            }
            .osa-top-bar {
                display: flex !important;
                flex-direction: column !important;
                gap: 10px !important;
                margin-bottom: 12px;
            }
            .osa-date-filters {
                width: 100% !important;
                overflow-x: auto !important;
                padding-bottom: 4px !important;
            }
            .osa-search-wrapper {
                grid-column: auto !important;
                width: 100% !important;
                display: block !important;
            }
            .modal-grid-3 {
                grid-template-columns: 1fr;
            }
            /* Keep modal-grid-2 as 2 columns on mobile for code and status */
            .modal-grid-2 {
                grid-template-columns: 1fr 1fr !important;
                gap: 8px !important;
            }
            
            /* Slide panel mobile responsive */
            .slide-panel {
                width: 100% !important;
                min-width: 100% !important;
                max-width: 100% !important;
                right: -120% !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                border-left: none !important;
            }
            .slide-panel.slide-panel-open {
                right: 0 !important;
                box-shadow: -10px 0 35px rgba(0, 0, 0, 0.75), -2px 0 15px rgba(201, 170, 46, 0.2) !important;
                border-left: 2px solid rgba(201, 170, 46, 0.6) !important;
            }
            .slide-panel-header {
                padding: 10px 14px !important;
            }
            .slide-panel-header h2 {
                font-size: 0.90rem !important;
                font-weight: 600 !important;
                line-height: 1.3 !important;
                color: #ffd700 !important;
                word-break: break-word !important;
            }
            .slide-panel-header .close-panel-btn {
                width: 28px !important;
                height: 28px !important;
                font-size: 13px !important;
            }
            .slide-panel-body {
                padding: 12px 10px !important;
            }

            /* Responsive Table: Display ONLY Product Details, Customer, and Action on mobile */
            table.osa-logs-table {
                display: table !important;
                width: 100% !important;
                table-layout: fixed;
            }
            table.osa-logs-table thead {
                display: table-header-group !important;
            }
            table.osa-logs-table tbody {
                display: table-row-group !important;
            }
            table.osa-logs-table tr {
                display: table-row !important;
            }
            table.osa-logs-table th,
            table.osa-logs-table td {
                display: table-cell !important;
                padding: 10px 8px !important;
            }

            /* Hide non-essential columns on mobile */
            .osa-logs-table .col-order-code,
            .osa-logs-table .col-date,
            .osa-logs-table .col-status {
                display: none !important;
            }

            /* Visible column widths */
            .osa-logs-table th.col-prod-details,
            .osa-logs-table td.col-prod-details {
                width: 45% !important;
            }
            .osa-logs-table th.col-customer,
            .osa-logs-table td.col-customer {
                width: 33% !important;
            }
            .osa-logs-table th.col-action,
            .osa-logs-table td.col-action {
                width: 22% !important;
                text-align: right !important;
            }

            .product-mini-cell {
                gap: 8px;
            }
            .product-mini-thumb,
            .product-mini-icon {
                width: 32px !important;
                height: 32px !important;
                min-width: 32px !important;
            }
            .product-cell-name {
                max-width: 100% !important;
                font-size: 0.76rem !important;
                white-space: normal !important;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                line-height: 1.25;
            }
            .product-cell-cat {
                font-size: 0.62rem !important;
            }
            .customer-cell-name {
                font-size: 0.75rem !important;
                line-height: 1.25;
                word-break: break-word;
            }
            .btn-action-view {
                padding: 5px 8px !important;
                font-size: 0.72rem !important;
                gap: 4px;
                white-space: nowrap;
            }
        }

        @media (max-width: 480px) {
            .shell { padding: 15px 10px 40px 10px; }
            .cal-stats-overview {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 6px !important;
            }
            .cal-stat-item {
                padding: 6px 8px !important;
            }
            .cal-stat-title {
                font-size: 0.66rem !important;
            }
            .cal-stat-number {
                font-size: 1.05rem !important;
            }
            .cal-stat-change {
                font-size: 0.78rem !important;
                color: #ffd700 !important;
            }
            .osa-logs-table th,
            .osa-logs-table td {
                padding: 8px 6px !important;
            }
            .osa-logs-table th.col-prod-details,
            .osa-logs-table td.col-prod-details {
                width: 44% !important;
            }
            .osa-logs-table th.col-customer,
            .osa-logs-table td.col-customer {
                width: 32% !important;
            }
            .osa-logs-table th.col-action,
            .osa-logs-table td.col-action {
                width: 24% !important;
            }
            .btn-action-view {
                padding: 4px 6px !important;
                font-size: 0.68rem !important;
            }

            /* Ensure code and status stay in one row on mobile */
            .modal-grid-2.single-row {
                grid-template-columns: 1fr 1fr !important;
                gap: 8px !important;
            }
            .modal-grid-2.single-row .modal-info-item {
                gap: 2px !important;
            }
            .modal-grid-2.single-row .modal-info-label {
                font-size: 0.65rem !important;
            }
            .modal-grid-2.single-row .modal-info-val {
                font-size: 0.8rem !important;
            }
            .modal-grid-2.single-row .mini-status-chip {
                font-size: 0.65rem !important;
                padding: 2px 6px !important;
            }

            /* Slide panel smaller mobile */
            .slide-panel-header {
                padding: 8px 12px !important;
            }
            .slide-panel-header h2 {
                font-size: 0.82rem !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="shell buy-page">
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
                <div class="custom-date-popover" id="buyCustomDatePopover" style="display: none;">
                    <div class="custom-date-inner">
                        <div class="custom-date-field">
                            <label>From</label>
                            <input type="date" id="buyStartDate" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="custom-date-field">
                            <label>To</label>
                            <input type="date" id="buyEndDate" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <button type="button" class="custom-date-apply-btn" onclick="applyCustomDateRange()">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <button type="button" class="custom-date-close-btn" onclick="toggleCustomDatePopover(false)" title="Close">&times;</button>
                    </div>
                </div>

                <div class="osa-active-period-label" id="buyPeriodLabel">
                    <i class="fas fa-calendar-check"></i> <span id="buyPeriodLabelText">Today</span>
                </div>
            </div>

            <div class="osa-search-wrapper">
                <input type="text" id="searchName" placeholder="Search buy orders..." onkeyup="handleSearchInput()">
            </div>
        </div>

        <!-- 5 Stat Boxes: Confirmed, Completed, Cancelled, Pending, Total Orders -->
        <div class="cal-stats-overview">
            <div class="cal-stat-item" onclick="applyBuyFilter('confirmed', this)">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Confirmed</span>
                    <span class="cal-stat-change" id="statBadgeConfirmed"><?php echo (int)($buyStats['confirmed'] ?? 0); ?> items</span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number" id="statNumConfirmed" style="color: #2196f3; text-shadow: 0 0 12px rgba(33, 150, 243, 0.4);"><?php echo (int)($buyStats['confirmed'] ?? 0); ?></p>
                </div>
            </div>

            <div class="cal-stat-item" onclick="applyBuyFilter('completed', this)">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Completed</span>
                    <span class="cal-stat-change" id="statBadgeCompleted"><?php echo (int)($buyStats['completed'] ?? 0); ?> items</span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number" id="statNumCompleted" style="color: #4CAF50; text-shadow: 0 0 12px rgba(76, 175, 80, 0.4);"><?php echo (int)($buyStats['completed'] ?? 0); ?></p>
                </div>
            </div>

            <div class="cal-stat-item" onclick="applyBuyFilter('cancelled', this)">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Cancelled</span>
                    <span class="cal-stat-change" id="statBadgeCancelled"><?php echo (int)($buyStats['cancelled'] ?? 0); ?> items</span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number" id="statNumCancelled" style="color: #f44336; text-shadow: 0 0 12px rgba(244, 67, 54, 0.4);"><?php echo (int)($buyStats['cancelled'] ?? 0); ?></p>
                </div>
            </div>

            <div class="cal-stat-item" onclick="applyBuyFilter('pending', this)">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Pending</span>
                    <span class="cal-stat-change" id="statBadgePending"><?php echo (int)($buyStats['pending'] ?? 0); ?> items</span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number" id="statNumPending" style="color: #ff9800; text-shadow: 0 0 12px rgba(255, 152, 0, 0.4);"><?php echo (int)($buyStats['pending'] ?? 0); ?></p>
                </div>
            </div>

            <div class="cal-stat-item" onclick="applyBuyFilter('all', this)">
                <div class="cal-stat-header">
                    <span class="cal-stat-title">Total Buy Orders</span>
                    <span class="cal-stat-change" id="statBadgeTotal"><?php echo (int)($buyStats['total'] ?? 0); ?> total</span>
                </div>
                <div class="cal-stat-content">
                    <p class="cal-stat-number" id="statNumTotal" style="color: #34d399; text-shadow: 0 0 12px rgba(52, 211, 153, 0.4);"><?php echo (int)($buyStats['total'] ?? 0); ?></p>
                </div>
            </div>
        </div>

        <!-- Buy Orders Table Card -->
        <div class="osa-table-card">
            <?php if (empty($buyOrders)): ?>
                <div class="osa-empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>No Buy Orders Recorded</h3>
                    <p>When customers purchase products to buy, their complete order details will appear here.</p>
                </div>
            <?php else: ?>
                <div class="osa-table-responsive">
                    <table class="osa-logs-table">
                        <thead>
                            <tr>
                                <th class="col-prod-details">Product Details</th>
                                <th class="col-customer">Customer</th>
                                <th class="col-order-code">Order Code</th>
                                <th class="col-date">Date</th>
                                <th class="col-status">Status</th>
                                <th class="col-action" style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="buyTableBody">
                            <?php foreach ($buyOrders as $order): 
                                $status = strtolower($order['order_status'] ?? 'pending');
                                $orderCode = $order['reservation_code'] ?: ($order['reservation_group_id'] ?: '#' . $order['id']);
                                $custName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
                                if (empty($custName)) $custName = 'Customer #' . $order['user_id'];
                                $prodName = $order['product_name'] ?? 'Product';
                                $qty = max(1, (int)($order['quantity'] ?? 1));
                                $unitPrice = (float)($order['price'] ?? 0);
                                $totalPrice = (float)($order['total_price'] ?? ($unitPrice * $qty));
                                $downpayment = (float)($order['downpayment_amount'] ?? 0);
                                $balance = (float)($order['balance_amount'] ?? 0);
                                $dateVal = !empty($order['reserve_date']) ? date('M d, Y', strtotime($order['reserve_date'])) : date('M d, Y', strtotime($order['reserved_at']));
                                $rawDate = !empty($order['reserved_at']) ? $order['reserved_at'] : (!empty($order['reserve_date']) ? $order['reserve_date'] : ($order['created_at'] ?? ''));
                                $rowDateYmd = !empty($rawDate) ? date('Y-m-d', strtotime($rawDate)) : '';
                                $paymentMethod = $order['payment_method'] ?? 'cash';
                                $paymentOption = $order['payment_option'] ?? 'full';
                                
                                $searchPayload = strtolower($prodName . ' ' . $custName . ' ' . $orderCode . ' ' . $status . ' ' . $paymentMethod . ' ' . $paymentOption . ' ' . ($order['category'] ?? '') . ' ' . ($order['customer_phone'] ?? '') . ' ' . ($order['customer_email'] ?? ''));
                            ?>
                            <tr class="buy-order-row"
                                data-id="<?php echo (int)$order['id']; ?>"
                                data-status="<?php echo htmlspecialchars($status); ?>"
                                data-date="<?php echo htmlspecialchars($rowDateYmd); ?>"
                                data-search="<?php echo htmlspecialchars($searchPayload); ?>">
                                
                                <!-- 1. Product Details -->
                                <td class="col-prod-details">
                                    <div class="product-mini-cell">
                                        <?php if (!empty($order['product_image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($order['product_image']); ?>" alt="Product" class="product-mini-thumb" onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <div class="product-mini-icon"><i class="fas fa-tshirt"></i></div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="product-cell-name" title="<?php echo htmlspecialchars($prodName); ?>">
                                                <?php echo htmlspecialchars($prodName); ?>
                                            </div>
                                            <?php if (!empty($order['resolved_category'])): ?>
                                                <div class="product-cell-cat"><?php echo htmlspecialchars($order['resolved_category']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- 2. Customer -->
                                <td class="col-customer">
                                    <div class="customer-cell-name" title="<?php echo htmlspecialchars($custName); ?>">
                                        <?php echo htmlspecialchars($custName); ?>
                                    </div>
                                    <?php if (!empty($order['customer_phone'])): ?>
                                        <div class="customer-cell-phone">
                                            <?php echo htmlspecialchars($order['customer_phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- 3. Order Code -->
                                <td class="col-order-code">
                                    <span class="code-pill"><?php echo htmlspecialchars($orderCode); ?></span>
                                </td>

                                <!-- 4. Date -->
                                <td class="col-date">
                                    <div class="date-cell" title="Ordered: <?php echo date('M d, Y h:i A', strtotime($order['reserved_at'])); ?>">
                                        <?php echo $dateVal; ?>
                                    </div>
                                </td>

                                <!-- 5. Status -->
                                <td class="col-status">
                                    <span class="mini-status-chip chip-<?php echo htmlspecialchars($status); ?>" id="statusChip-<?php echo (int)$order['id']; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>

                                <!-- 6. Action -->
                                <td class="col-action" style="text-align: right;">
                                    <button type="button" class="btn-action-view" onclick="event.stopPropagation(); openBuyDetailsModal(<?php echo (int)$order['id']; ?>)">
                                        Details
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="osa-table-footer">
                    <div>
                        Showing <strong id="buyVisibleCount" style="color: #ffd700;"><?php echo count($buyOrders); ?></strong> buy orders
                    </div>
                    <div id="buyFilterSummary" style="color: #777;">
                        Filter: All Statuses
                    </div>
                </div>

                <div id="buyNoResultsState" class="osa-empty-state" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h3>No Matching Buy Orders Found</h3>
                    <p>Try refining your search keyword or clearing active status filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Buy Order Details Slide Panel -->
    <div id="buyDetailsModal" class="slide-panel">
        <div class="slide-panel-content">
            <div class="slide-panel-header">
                <h2>
                    <i class="fas fa-shopping-bag"></i>
                    <span>Buy Order Details</span>
                </h2>
                <button type="button" class="close-panel-btn" onclick="closeBuyModal()" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="slide-panel-body" id="buyModalBody">
                <div style="text-align: center; padding: 40px; color: #888;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #ffd700; margin-bottom: 10px;"></i>
                    <p>Loading buy order details...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    function playButtonClickSound() {
        try {
            const audio = new Audio('../ringtone/sound.mp3');
            audio.volume = 0.2;
            audio.play().catch(e => {});
        } catch(e) {}
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
    let filterStartDate = serverTodayStr;
    let filterEndDate = serverTodayStr;
    let currentDatePeriod = 'today';
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

        const labelText = document.getElementById('buyPeriodLabelText');

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
        const popover = document.getElementById('buyCustomDatePopover');
        if (!popover) return;
        if (typeof forceState === 'boolean') {
            popover.style.display = forceState ? 'block' : 'none';
        } else {
            popover.style.display = (popover.style.display === 'none' || !popover.style.display) ? 'block' : 'none';
        }
    }

    function applyCustomDateRange() {
        const sInput = document.getElementById('buyStartDate');
        const eInput = document.getElementById('buyEndDate');
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

        const labelText = document.getElementById('buyPeriodLabelText');
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
        const popover = document.getElementById('buyCustomDatePopover');
        const btnChoose = document.getElementById('btnChooseDate');
        if (popover && popover.style.display === 'block') {
            if (!popover.contains(e.target) && !btnChoose.contains(e.target)) {
                popover.style.display = 'none';
            }
        }
    });

    function isRowInDateRange(rowDateStr) {
        if (currentDatePeriod === 'all') return true;
        if (!rowDateStr) return false;
        if (filterStartDate && rowDateStr < filterStartDate) return false;
        if (filterEndDate && rowDateStr > filterEndDate) return false;
        return true;
    }

    function applyBuyFilter(filter, el) {
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

    function filterRows() {
        const rows = document.querySelectorAll('.buy-order-row');
        const noResults = document.getElementById('buyNoResultsState');
        const countDisplay = document.getElementById('buyVisibleCount');
        const summaryDisplay = document.getElementById('buyFilterSummary');

        let visibleCount = 0;
        let counts = {
            confirmed: 0,
            completed: 0,
            cancelled: 0,
            pending: 0,
            total: 0
        };

        // First pass: compute stats for all rows within the active date period
        rows.forEach(row => {
            const rowDate = row.getAttribute('data-date') || '';
            const inDate = isRowInDateRange(rowDate);

            if (inDate) {
                counts.total++;
                const status = (row.getAttribute('data-status') || '').toLowerCase();
                if (counts[status] !== undefined) {
                    counts[status]++;
                }
            }
        });

        // Update 5 stat boxes numbers and badges
        const statMap = [
            { numId: 'statNumConfirmed', badgeId: 'statBadgeConfirmed', val: counts.confirmed, suffix: ' items' },
            { numId: 'statNumCompleted', badgeId: 'statBadgeCompleted', val: counts.completed, suffix: ' items' },
            { numId: 'statNumCancelled', badgeId: 'statBadgeCancelled', val: counts.cancelled, suffix: ' items' },
            { numId: 'statNumPending', badgeId: 'statBadgePending', val: counts.pending, suffix: ' items' },
            { numId: 'statNumTotal', badgeId: 'statBadgeTotal', val: counts.total, suffix: ' total' }
        ];
        statMap.forEach(item => {
            const numEl = document.getElementById(item.numId);
            const badgeEl = document.getElementById(item.badgeId);
            if (numEl) numEl.textContent = item.val;
            if (badgeEl) badgeEl.textContent = item.val + item.suffix;
        });

        // Second pass: filter row visibility based on date range, status filter, and search text
        rows.forEach(row => {
            const rowDate = row.getAttribute('data-date') || '';
            const inDate = isRowInDateRange(rowDate);

            if (!inDate) {
                row.style.display = 'none';
                return;
            }

            const status = (row.getAttribute('data-status') || '').toLowerCase();
            const searchData = row.getAttribute('data-search') || '';

            let matchesFilter = (currentFilter === 'all' || status === currentFilter);
            let matchesSearch = (!searchQuery || searchData.includes(searchQuery));

            if (matchesFilter && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (countDisplay) countDisplay.textContent = visibleCount;
        if (noResults) {
            noResults.style.display = (visibleCount === 0 && rows.length > 0) ? 'block' : 'none';
        }
        if (summaryDisplay) {
            let filterName = currentFilter.charAt(0).toUpperCase() + currentFilter.slice(1);
            if (currentFilter === 'all') filterName = 'All Statuses';
            if (searchQuery) filterName += ` (Search: "${searchQuery}")`;
            summaryDisplay.textContent = `Filter: ${filterName}`;
        }
    }

    // Apply default 'today' period on initial page load (like in order_status_actions.php)
    document.addEventListener('DOMContentLoaded', function() {
        changeDatePeriod('today');
    });

    // Slide Panel Details Logic
    let activeModalReservationId = null;

    function openBuyDetailsModal(reservationId) {
        activeModalReservationId = reservationId;
        const modal = document.getElementById('buyDetailsModal');
        const body = document.getElementById('buyModalBody');

        modal.classList.add('slide-panel-open');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('slide-panel-open');
        
        body.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #888;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #ffd700; margin-bottom: 10px;"></i>
                <p>Loading buy order details...</p>
            </div>
        `;

        fetch(`get_reservation_details.php?id=${reservationId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.reservation) {
                    body.innerHTML = `<div style="color: #ff6b6b; padding: 20px; text-align: center;">Failed to load order details: ${data.message || 'Unknown error'}</div>`;
                    return;
                }

                const r = data.reservation;
                const products = data.products || [r];
                const status = (r.order_status || 'pending').toLowerCase();
                const orderCode = r.reservation_code || (r.reservation_group_id || '#' + r.id);

                let productsHtml = '';
                products.forEach((prod, pIdx) => {
                    const prodName = prod.product_name || 'Gown / Attire';
                    const prodCat = prod.category || 'Product';
                    const prodImg = prod.gown_image ? `../${prod.gown_image}` : '';
                    const unitPrice = parseFloat(prod.price || 0);
                    const qty = parseInt(prod.quantity || 1);
                    const lineTotal = parseFloat(prod.total_price || (unitPrice * qty));

                    // Build size/measurements list
                    let sizeItemsHtml = '';
                    const sizeLabels = (Array.isArray(prod.size_labels) && prod.size_labels.length > 0)
                        ? prod.size_labels
                        : [
                            { index: 1, label: 'Bust / Chest' },
                            { index: 2, label: 'Waist' },
                            { index: 3, label: 'Hips' },
                            { index: 4, label: 'Shoulder' },
                            { index: 5, label: 'Length' },
                            { index: 6, label: 'Arm Hole' },
                            { index: 7, label: 'Sleeve' }
                        ];

                    const allItems = [];
                    allItems.push({ num: 1, data: prod });
                    if (Array.isArray(prod.items) && prod.items.length > 0) {
                        prod.items.forEach(it => {
                            allItems.push({ num: it.item_number || (allItems.length + 1), data: it });
                        });
                    }

                    allItems.forEach(itemObj => {
                        let mListHtml = '';
                        let hasAnyMeasurement = false;
                        sizeLabels.forEach(lbl => {
                            const val = itemObj.data[`size_${lbl.index}`];
                            if (val && val.trim() !== '') {
                                hasAnyMeasurement = true;
                                mListHtml += `
                                    <div class="size-measurement-pill">
                                        <span class="m-label">${lbl.label}</span>
                                        <span class="m-val">${escapeHtml(val)} in</span>
                                    </div>
                                `;
                            }
                        });

                        if (hasAnyMeasurement) {
                            sizeItemsHtml += `
                                <div style="margin-top: 8px;">
                                    ${allItems.length > 1 ? `<div style="font-size: 0.72rem; color: #ffd700; margin-bottom: 4px; font-weight: 600;">Item #${itemObj.num} Measurements:</div>` : ''}
                                    <div class="modal-sizes-grid">${mListHtml}</div>
                                </div>
                            `;
                        }
                    });

                    productsHtml += `
                        <div class="modal-section-card">
                            <div class="modal-prod-row">
                                ${prodImg 
                                    ? `<img src="${prodImg}" alt="${escapeHtml(prodName)}" class="modal-prod-img" onerror="this.style.display='none'">` 
                                    : `<div class="product-mini-icon" style="width: 64px; height: 64px; border-radius: 8px;"><i class="fas fa-tshirt" style="font-size: 1.5rem;"></i></div>`
                                }
                                <div style="flex: 1;">
                                    <div style="font-size: 1.05rem; font-weight: 600; color: #fff;">${escapeHtml(prodName)}</div>
                                    <div style="font-size: 0.75rem; color: #888; text-transform: uppercase; margin-top: 2px;">${escapeHtml(prodCat)}</div>
                                    <div style="font-size: 0.85rem; color: #ffd700; margin-top: 6px; font-weight: 600;">
                                        ₱${unitPrice.toLocaleString('en-US', {minimumFractionDigits: 2})} &times; ${qty} = ₱${lineTotal.toLocaleString('en-US', {minimumFractionDigits: 2})}
                                    </div>
                                </div>
                            </div>
                            ${sizeItemsHtml ? `
                                <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(255, 215, 0, 0.1);">
                                    <div class="modal-info-label" style="margin-bottom: 6px;"><i class="fas fa-ruler-combined"></i> Custom Size Measurements:</div>
                                    ${sizeItemsHtml}
                                </div>
                            ` : ''}
                        </div>
                    `;
                });

                // Customer & Contact Info
                const custName = `${r.first_name || ''} ${r.last_name || ''}`.trim() || `Customer #${r.user_id}`;
                const custPhone = r.customer_phone || (r.last_name && r.last_name.includes('Walk-In') ? r.last_name : 'Not specified');
                const custEmail = r.customer_email || 'Not provided';
                const orderDate = r.reserved_at ? formatDate(r.reserved_at) : 'N/A';
                const claimDate = r.reserve_date ? formatDate(r.reserve_date) : 'N/A';
                const claimTime = r.reserve_time || 'Regular Store Hours';

                // Financial Breakdown
                const totalPrice = parseFloat(r.total_price || 0);
                const downpayment = parseFloat(r.downpayment_amount || 0);
                const balance = parseFloat(r.balance_amount || 0);
                const paymentMethod = (r.payment_method || 'Cash').toUpperCase();
                const paymentOption = r.payment_option === 'first_payment' ? 'Downpayment' : 'Full Payment';
                const paymentProof = r.payment_proof ? `../${r.payment_proof}` : '';

                body.innerHTML = `
                    <!-- Order Code & Status Section -->
                    <div class="modal-section-card" style="margin-bottom: 12px;">
                        <div class="modal-grid-2 single-row">
                            <div class="modal-info-item">
                                <span class="modal-info-label">Order Code</span>
                                <span class="modal-info-val" style="color: #ffd700; font-weight: 700; font-size: 1rem;">${escapeHtml(orderCode)}</span>
                            </div>
                            <div class="modal-info-item">
                                <span class="modal-info-label">Status</span>
                                <span class="mini-status-chip chip-${status}" style="margin-top: 2px;">
                                    <i class="fas ${getStatusIcon(status)}"></i> ${status.toUpperCase()}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Products in Purchase -->
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        ${productsHtml}
                    </div>

                    <!-- Customer & Claim Information -->
                    <div class="modal-section-card">
                        <div class="modal-section-title"><i class="fas fa-user-circle"></i> Customer & Claim Schedule</div>
                        <div class="modal-grid-3">
                            <div class="modal-info-item">
                                <span class="modal-info-label">Customer Name</span>
                                <span class="modal-info-val">${escapeHtml(custName)}</span>
                            </div>
                            <div class="modal-info-item">
                                <span class="modal-info-label">Contact Phone</span>
                                <span class="modal-info-val">${escapeHtml(custPhone)}</span>
                            </div>
                            <div class="modal-info-item">
                                <span class="modal-info-label">Email Address</span>
                                <span class="modal-info-val">${escapeHtml(custEmail)}</span>
                            </div>
                            <div class="modal-info-item">
                                <span class="modal-info-label">Order Placed On</span>
                                <span class="modal-info-val">${orderDate}</span>
                            </div>
                            <div class="modal-info-item">
                                <span class="modal-info-label">Claim / Purchase Date</span>
                                <span class="modal-info-val" style="color: #ffd700;">${claimDate}</span>
                            </div>
                            <div class="modal-info-item">
                                <span class="modal-info-label">Claim Time</span>
                                <span class="modal-info-val">${escapeHtml(claimTime)}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <div class="modal-section-card">
                        <div class="modal-section-title"><i class="fas fa-receipt"></i> Payment & Billing Summary</div>
                        <div class="modal-grid-3">
                            <div class="modal-info-item">
                                <span class="modal-info-label">Total Amount</span>
                                <span class="modal-info-val" style="color: #ffd700; font-size: 1rem; font-weight: 700;">₱${totalPrice.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                            </div>
                            <div class="modal-info-item">
                                <span class="modal-info-label">Downpayment Paid</span>
                                <span class="modal-info-val" style="color: #4CAF50;">₱${downpayment.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                            </div>
                            <div class="modal-info-item">
                                <span class="modal-info-label">Remaining Balance</span>
                                <span class="modal-info-val" style="${balance > 0 ? 'color: #ff8888;' : 'color: #888;'} font-weight: 700;">₱${balance.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                            </div>
                            <div class="modal-info-item">
                                <span class="modal-info-label">Payment Option</span>
                                <span class="modal-info-val">${escapeHtml(paymentOption)}</span>
                            </div>
                            <div class="modal-info-item">
                                <span class="modal-info-label">Payment Method</span>
                                <span class="modal-info-val">${escapeHtml(paymentMethod)}</span>
                            </div>
                            ${paymentProof ? `
                                <div class="modal-info-item">
                                    <span class="modal-info-label">Payment Receipt</span>
                                    <img src="${paymentProof}" alt="Receipt" class="proof-img-thumb" onclick="window.open('${paymentProof}', '_blank')" title="Click to open full image">
                                </div>
                            ` : ''}
                        </div>

                        ${r.cancellation_reason ? `
                            <div style="margin-top: 12px; padding: 10px; background: rgba(244, 67, 54, 0.1); border: 1px solid rgba(244, 67, 54, 0.3); border-radius: 6px;">
                                <div class="modal-info-label" style="color: #ff8888;"><i class="fas fa-exclamation-triangle"></i> Cancellation Reason:</div>
                                <div style="color: #ffcdd2; font-size: 0.85rem; margin-top: 4px;">${escapeHtml(r.cancellation_reason)}</div>
                            </div>
                        ` : ''}
                    </div>
                `;
            })
            .catch(err => {
                body.innerHTML = `<div style="color: #ff6b6b; padding: 20px; text-align: center;">Network error while loading order details: ${err.message}</div>`;
            });
    }

    function closeBuyModal() {
        const modal = document.getElementById('buyDetailsModal');
        modal.classList.remove('slide-panel-open');
        document.body.classList.remove('slide-panel-open');
        document.body.style.overflow = 'auto';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeBuyModal();
        }
    });

    function getStatusIcon(status) {
        switch(status) {
            case 'completed': return 'fa-check-circle';
            case 'confirmed': return 'fa-clipboard-check';
            case 'cancelled': return 'fa-times-circle';
            default: return 'fa-clock';
        }
    }

    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const d = new Date(dateStr.replace(/-/g, '/'));
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    </script>
</body>
</html>
