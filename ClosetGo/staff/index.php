<?php
/**
 * staff/index.php
 * ClosetGo Staff Portal - Reservation Scanner & Management Dashboard
 */
session_start();

// ── Session guard: staff must be logged in ──
if (empty($_SESSION['staff_logged_in'])) {
    header('Location: ../login_user/login.php');
    exit;
}

require_once '../database/db_config.php';

$database = new Database();
$conn = $database->getConnection();

// Handle AJAX fetch of all gowns & categories for real-time product picker
if (isset($_GET['action']) && $_GET['action'] === 'get_all_gowns_picker') {
    header('Content-Type: application/json');
    try {
        $list = [];
        $categories = [];
        if ($conn) {
            $catRes = $conn->query("SELECT category_name FROM categories ORDER BY id ASC");
            if ($catRes) {
                while ($crow = $catRes->fetch_assoc()) {
                    if (!empty($crow['category_name'])) {
                        $categories[] = $crow['category_name'];
                    }
                }
            }
            $gownsRes = $conn->query("SELECT id, name, category, price, discount, image_path, image_path2, image_path3, model_path, stock, color, description FROM gowns ORDER BY name ASC");
            if ($gownsRes) {
                while ($r = $gownsRes->fetch_assoc()) {
                    $list[] = $r;
                }
            }
        }
        echo json_encode(['success' => true, 'gowns' => $list, 'categories' => $categories]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

$allGowns = [];
$categorySizeLabelsMap = [];
$allCategoryNames = [];
$rentalDurationDays = 10;

$staffId = intval($_SESSION['staff_id'] ?? 1);
$staffName = $_SESSION['staff_name'] ?? 'Staff Member';
$staffEmail = $_SESSION['staff_email'] ?? 'staff@closetgo.local';
$staffCreatedAt = '';
$staffJoinedDate = 'Recently Joined';

if ($conn) {
    // Fetch latest staff account info
    if ($staffId > 0) {
        $staffStmt = $conn->prepare("SELECT id, name, firstname, middlename, lastname, suffix, email, created_at FROM system_staffs WHERE id = ? LIMIT 1");
        if ($staffStmt) {
            $staffStmt->bind_param("i", $staffId);
            $staffStmt->execute();
            $sRes = $staffStmt->get_result();
            if ($sRes && $sRow = $sRes->fetch_assoc()) {
                if (!empty($sRow['name'])) {
                    $staffName = $sRow['name'];
                } elseif (!empty($sRow['firstname'])) {
                    $staffName = trim($sRow['firstname'] . ' ' . (!empty($sRow['middlename']) ? $sRow['middlename'] . ' ' : '') . ($sRow['lastname'] ?? '') . (!empty($sRow['suffix']) ? ' ' . $sRow['suffix'] : ''));
                }
                if (!empty($sRow['email'])) {
                    $staffEmail = $sRow['email'];
                }
                if (!empty($sRow['created_at'])) {
                    $staffCreatedAt = $sRow['created_at'];
                    $staffJoinedDate = date('M d, Y', strtotime($sRow['created_at']));
                }
            }
            $staffStmt->close();
        }
    }

    // Auto-migrate columns
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reservation_code VARCHAR(50) DEFAULT NULL AFTER reservation_group_id");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS is_rescheduled TINYINT(1) NOT NULL DEFAULT 0");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS rescheduled_at DATETIME DEFAULT NULL");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS original_reserve_date DATE DEFAULT NULL");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS original_return_date DATE DEFAULT NULL");
    @$conn->query("ALTER TABLE reserve_products ADD COLUMN IF NOT EXISTS reschedule_reason TEXT DEFAULT NULL");

    // Fetch rental duration days
    $durRes = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'rental_duration_days' LIMIT 1");
    if ($durRes && $row = $durRes->fetch_assoc()) {
        $rentalDurationDays = max(1, intval($row['setting_value']));
    }

    // Categories
    $catsResult = $conn->query("SELECT * FROM categories ORDER BY id ASC");
    if ($catsResult) {
        while ($crow = $catsResult->fetch_assoc()) {
            $catNameRaw = trim($crow['category_name'] ?? '');
            if (!empty($catNameRaw)) {
                $allCategoryNames[] = $catNameRaw;
            }
            $catName = strtolower($catNameRaw);
            $labels = [];
            for ($i = 1; $i <= 10; $i++) {
                $lbl = trim($crow['size_label_' . $i] ?? '');
                if (!empty($lbl)) {
                    $labels[] = ['index' => $i, 'label' => $lbl];
                }
            }
            if (!empty($catName)) {
                $categorySizeLabelsMap[$catName] = $labels;
            }
        }
    }

    // Gowns for walk-in selection
    $gownsResult = $conn->query("SELECT id, name, category, price, discount, image_path, image_path2, image_path3, model_path, stock, color, description FROM gowns ORDER BY name ASC");
    if ($gownsResult) {
        while ($g = $gownsResult->fetch_assoc()) {
            $allGowns[] = $g;
            if (!empty($g['category']) && !in_array($g['category'], $allCategoryNames)) {
                $allCategoryNames[] = $g['category'];
            }
        }
    }
}

// Compute initials
$nameParts = preg_split('/\s+/', trim($staffName));
$initials = '';
if (count($nameParts) >= 2) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts) - 1], 0, 1));
} else {
    $initials = strtoupper(substr($staffName, 0, 2));
}
if (empty($initials)) $initials = 'ST';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>ClosetGo Staff Portal - QR Scanner & Reservation Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="../images/icon/JMB.png" type="image/png">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        :root {
            --bg-primary: #121212;
            --bg-secondary: #1a1a1a;
            --bg-card: #202020;
            --bg-input: #292929;
            --accent-gold: #ffd700;
            --accent-gold-dark: #b8960c;
            --accent-gold-gradient: linear-gradient(135deg, #7c6300, #b8960c);
            --border-color: #333333;
            --border-gold: #7c6300;
            --text-primary: #ffffff;
            --text-secondary: #aaaaaa;
            --text-muted: #777777;
            --status-pending: #e6a100;
            --status-confirmed: #0099ff;
            --status-completed: #22c55e;
            --status-cancelled: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Header */
        .staff-header {
            background: linear-gradient(180deg, #1f1f1f, #161616);
            border-bottom: 1px solid var(--border-gold);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0,0,0,0.6);
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }

        .brand-logo {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-gold);
        }

        .brand-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--accent-gold);
            letter-spacing: 0.5px;
        }

        .brand-badge {
            background: var(--accent-gold-gradient);
            color: #000;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 12px;
            text-transform: uppercase;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-btn {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            text-decoration: none;
            white-space: nowrap;
        }

        .nav-btn:hover {
            border-color: var(--accent-gold);
            color: var(--accent-gold);
            background: #282828;
        }

        .nav-btn.active {
            background: var(--accent-gold-gradient);
            color: #fff;
            border-color: transparent;
        }

        /* Header Staff Profile Pill Button */
        /* Unified Equal Top Navigation Action Buttons (QR Scanner, Walk-In, Alf Staff) */
        .btn-header-qr-scanner,
        .btn-walk-in,
        .staff-header-profile-btn {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.16) 0%, rgba(255, 215, 0, 0.08) 100%) !important;
            border: 1.5px solid rgba(255, 215, 0, 0.4) !important;
            color: #ffd700 !important;
            height: 38px !important;
            min-height: 38px !important;
            max-height: 38px !important;
            min-width: 110px !important;
            padding: 0 16px !important;
            border-radius: 10px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            font-family: inherit !important;
            line-height: 1 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2) !important;
            text-transform: none !important;
            letter-spacing: normal !important;
            text-decoration: none !important;
            white-space: nowrap !important;
            box-sizing: border-box !important;
        }

        .btn-header-qr-scanner:hover,
        .btn-walk-in:hover,
        .staff-header-profile-btn:hover {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.3) 0%, rgba(255, 215, 0, 0.18) 100%) !important;
            border-color: #ffd700 !important;
            color: #fff !important;
            box-shadow: 0 0 16px rgba(255, 215, 0, 0.35) !important;
            transform: translateY(-1px) !important;
        }

        .btn-header-qr-scanner .btn-label,
        .btn-walk-in .btn-label,
        .staff-header-profile-btn .btn-label {
            color: inherit !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            line-height: 1 !important;
        }

        .staff-header-profile-btn:hover .staff-header-name {
            color: #fff !important;
        }

        .staff-header-avatar {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(255, 215, 0, 0.22);
            border: 1px solid rgba(255, 215, 0, 0.5);
            color: #ffd700;
            font-size: 10px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            line-height: 1;
            transition: all 0.2s ease;
        }

        .staff-header-profile-btn:hover .staff-header-avatar {
            background: rgba(255, 215, 0, 0.4);
            border-color: #ffd700;
            color: #fff;
        }

        .staff-header-name {
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12px !important;
            font-weight: 700 !important;
            color: inherit !important;
            line-height: 1 !important;
            transition: color 0.2s ease;
        }

        .staff-online-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 8px #22c55e;
            animation: pulseLed 2s infinite ease-in-out;
            flex-shrink: 0;
        }

        @keyframes pulseLed {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.55; transform: scale(0.85); }
        }

        /* Mobile Header Adjustments */
        @media (max-width: 600px) {
            .nav-btn .btn-label { display: inline-block !important; font-size: 11px !important; }
            .nav-btn { padding: 6px 12px !important; border-radius: 8px !important; }
            .staff-header-profile-btn { padding: 6px 10px !important; border-radius: 8px !important; }
            .staff-online-dot { display: none; }
            .brand-badge { display: none; }
            .staff-header { padding: 8px 12px; }
        }

        /* Container Layout */
        .staff-container {
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* KPI Chips Bar */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }

        @media (max-width: 1100px) {
            .kpi-grid {
                grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            }
        }

        @media (max-width: 480px) {
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            #kpiCard-all {
                grid-column: 1 / -1; /* Total Orders spans full width */
            }
        }

        .kpi-card {
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
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            min-height: unset;
        }

        .kpi-card:hover {
            box-shadow: inset 0 0 22px rgba(255, 215, 0, 0.38);
            transform: translateY(-2px);
        }

        .kpi-card.active {
            border-color: #ffd700 !important;
            box-shadow: inset 0 0 25px rgba(255, 215, 0, 0.45), 0 2px 10px rgba(0, 0, 0, 0.5) !important;
            transform: translateY(-2px);
        }

        .kpi-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
            width: 100%;
        }

        .kpi-info h4 {
            color: #666666;
            font-size: 0.72rem;
            font-weight: 400;
            letter-spacing: 0.4px;
            margin: 0;
            white-space: nowrap;
        }

        .kpi-info .kpi-num {
            font-size: 1.15rem;
            font-weight: 500;
            line-height: 1.15;
            color: #ffd700 !important;
            text-shadow: 0 0 12px rgba(255, 215, 0, 0.4);
            margin: 0;
            text-align: left;
            white-space: nowrap;
        }

        .kpi-icon {
            display: none;
        }

        /* Main Workspace Grid (Scanner / Lookup on left, List on right) */
        .workspace-grid {
            display: grid;
            grid-template-columns: 1fr 1.35fr;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 900px) {
            .workspace-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ── Full Screen Mobile Detail Drawer / View ── */
        @media (max-width: 900px) {
            #detailPanel {
                position: fixed !important;
                top: 0 !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100vw !important;
                max-width: 100vw !important;
                height: 100% !important;
                max-height: 100dvh !important;
                z-index: 1000005 !important;
                border-radius: 0 !important;
                border: none !important;
                overflow: hidden !important;
                transform: translateY(100%) !important;
                transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
                background: #14151a !important;
                box-shadow: none !important;
                display: flex !important;
                flex-direction: column !important;
                margin: 0 !important;
                visibility: hidden;
                pointer-events: none;
            }
            #detailPanel.drawer-open {
                transform: translateY(0) !important;
                visibility: visible !important;
                pointer-events: auto !important;
            }
            #detailPanel .card-panel-header {
                position: sticky !important;
                top: 0 !important;
                z-index: 10 !important;
                background: #1c1e25 !important;
                border-bottom: 1px solid rgba(255, 215, 0, 0.25) !important;
                padding: 12px 14px !important;
                flex-shrink: 0 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
            }
            #detailPanel .card-panel-body {
                flex: 1 !important;
                overflow-y: auto !important;
                padding: 16px 14px calc(70px + env(safe-area-inset-bottom, 20px)) 14px !important;
                -webkit-overflow-scrolling: touch !important;
                min-height: 0 !important;
            }
            .drawer-backdrop {
                display: none;
                position: fixed !important;
                inset: 0 !important;
                background: rgba(0,0,0,0.72) !important;
                backdrop-filter: blur(4px) !important;
                -webkit-backdrop-filter: blur(4px) !important;
                z-index: 1000004 !important;
            }
            .drawer-backdrop.visible { display: block !important; }
        }

        /* Scanner Box */
        .card-panel {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .card-panel-header {
            padding: 14px 18px;
            background: #1e1e1e;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-panel-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--accent-gold);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-panel-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            flex: 1;
        }

        /* QR Scanner Camera Area */
        .scanner-wrapper {
            position: relative;
            background: #0d0d0d;
            border: 2px dashed #444;
            border-radius: 14px;
            overflow: hidden;
            min-height: 260px;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* QR Aim Corner Overlay */
        .qr-aim-overlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 20;
            display: none;
        }
        .qr-aim-overlay.active { display: block; }
        .qr-aim-frame {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            box-shadow: 0 0 0 9999px rgba(0,0,0,0.45);
            border-radius: 4px;
        }
        .qr-corner {
            position: absolute;
            width: 28px;
            height: 28px;
            border-color: #ff2a6d;
            border-style: solid;
        }
        .qr-corner.tl { top: -2px; left: -2px; border-width: 4px 0 0 4px; border-radius: 6px 0 0 0; }
        .qr-corner.tr { top: -2px; right: -2px; border-width: 4px 4px 0 0; border-radius: 0 6px 0 0; }
        .qr-corner.bl { bottom: -2px; left: -2px; border-width: 0 0 4px 4px; border-radius: 0 0 0 6px; }
        .qr-corner.br { bottom: -2px; right: -2px; border-width: 0 4px 4px 0; border-radius: 0 0 6px 0; }
        .qr-scan-line {
            position: absolute;
            left: 4px;
            right: 4px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #ff2a6d 40%, #ff2a6d 60%, transparent);
            border-radius: 2px;
            animation: scanLineMove 1.8s linear infinite;
            box-shadow: 0 0 8px rgba(255,42,109,0.7);
        }
        @keyframes scanLineMove {
            0% { top: 10%; }
            50% { top: 85%; }
            100% { top: 10%; }
        }
        /* Upload preview bar */
        .qr-upload-preview {
            display: none;
            background: rgba(255,42,109,0.12);
            border: 1.5px solid rgba(255,42,109,0.5);
            border-radius: 10px;
            padding: 10px 14px;
            gap: 10px;
            align-items: center;
            flex-shrink: 0;
            font-size: 12px;
            color: #fff;
        }
        .qr-upload-preview.visible { display: flex; }
        .qr-upload-preview-img {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: 7px;
            border: 1.5px solid rgba(255,42,109,0.6);
            flex-shrink: 0;
        }
        .qr-upload-preview-info { flex: 1; min-width: 0; }
        .qr-upload-preview-title {
            font-weight: 700;
            color: #ff2a6d;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .qr-upload-preview-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #ccc;
            font-size: 11px;
        }
        .qr-upload-preview-status {
            font-size: 11px;
            color: #ffd700;
            margin-top: 3px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        #qrReader {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            border: none !important;
            background: transparent !important;
            overflow: hidden !important;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        #qrReader > div,
        #qrReader #qrReader__scan_region,
        #qrReader #qrReader__camera_selection,
        #qrReader #qrReader__dashboard_section,
        #qrReader #qrReader__dashboard_section_csr {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            background: transparent !important;
        }

        #qrReader #qrReader__scan_region {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 200px !important;
            overflow: hidden !important;
        }

        #qrReader video {
            width: 100% !important;
            max-width: 100% !important;
            height: 100% !important;
            max-height: 60vh !important;
            object-fit: cover !important;
            border-radius: 12px !important;
            display: block !important;
            margin: 0 auto !important;
        }

        #qrReader canvas {
            max-width: 100% !important;
            height: auto !important;
        }

        #qrReader img {
            max-width: 100% !important;
            height: auto !important;
        }

        #qrReader #qrReader__header_message,
        #qrReader #qrReader__status_span {
            font-size: 11px !important;
            color: #ffd700 !important;
        }

        .scanner-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 30px 20px;
            text-align: center;
        }

        .scanner-placeholder i {
            font-size: 48px;
            color: var(--accent-gold);
            animation: pulseScan 2s infinite ease-in-out;
        }

        @keyframes pulseScan {
            0%, 100% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 1; }
        }

        .scanner-controls {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-scanner-action {
            background: var(--bg-card);
            border: 1px solid var(--border-gold);
            color: #fff;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-scanner-action:hover {
            background: var(--accent-gold-gradient);
            border-color: transparent;
        }

        /* Search Input Box */
        .search-box-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .search-input-wrapper {
            position: relative;
            flex: 1;
            display: flex;
            align-items: center;
        }

        .search-input-wrapper .search-icon {
            position: absolute;
            left: 14px;
            color: #888;
            pointer-events: none;
            font-size: 13px;
            transition: color 0.2s;
        }

        .search-input-wrapper:focus-within .search-icon {
            color: var(--accent-gold);
        }

        .search-input {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 10px 36px 10px 38px;
            color: #fff;
            font-size: 13px;
            outline: none;
            transition: all 0.2s;
        }

        .search-input:focus {
            border-color: var(--accent-gold);
            background: #252525;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.12);
        }

        .btn-clear-search {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #777;
            cursor: pointer;
            display: none;
            font-size: 13px;
            padding: 4px;
            transition: color 0.2s;
        }

        .btn-clear-search:hover {
            color: #ff5555;
        }

        .btn-search {
            background: var(--accent-gold-gradient);
            border: none;
            color: #fff;
            padding: 0 20px;
            border-radius: 24px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            height: 40px;
            transition: opacity 0.2s;
            white-space: nowrap;
        }

        .btn-search:hover {
            opacity: 0.9;
        }

        @media (max-width: 600px) {
            .btn-search {
                display: none;
            }
            .search-box-row {
                width: 100%;
            }
            .search-input {
                font-size: 13.5px;
                padding: 11px 36px 11px 38px;
            }
        }

        /* Active Selected Reservation Details Panel */
        .res-detail-card {
            background: #181818;
            border: 1.5px solid var(--border-gold);
            border-radius: 14px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.5);
        }

        .res-detail-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            border-bottom: 1px solid #282828;
            padding-bottom: 12px;
        }

        .res-code-badge {
            font-size: 16px;
            font-weight: 800;
            color: var(--accent-gold);
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .res-status-pill {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .pill-pending { background: rgba(230,161,0,0.15); color: var(--status-pending); border: 1px solid var(--status-pending); }
        .pill-confirmed { background: rgba(0,153,255,0.15); color: var(--status-confirmed); border: 1px solid var(--status-confirmed); }
        .pill-completed { background: rgba(34,197,94,0.15); color: var(--status-completed); border: 1px solid var(--status-completed); }
        .pill-cancelled { background: rgba(239,68,68,0.15); color: var(--status-cancelled); border: 1px solid var(--status-cancelled); }

        .detail-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            background: #1f1f1f;
            padding: 12px;
            border-radius: 10px;
            font-size: 12px;
        }

        @media (max-width: 420px) {
            .detail-meta-grid { grid-template-columns: 1fr; }
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .meta-label {
            color: var(--text-muted);
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .meta-value {
            color: #fff;
            font-weight: 600;
        }

        .meta-value.highlight {
            color: var(--accent-gold);
            font-size: 14px;
        }

        /* Reservation Details Panel Styles */
        .res-detail-section {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid #333;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 14px;
        }

        .res-detail-qr-btn:hover {
            border-color: #ffd700 !important;
            background: #2a2a30 !important;
            transform: translateY(-1px);
        }
        .res-detail-qr-btn:active {
            transform: scale(0.99);
        }

        .res-detail-section-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #FFD700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .res-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .res-detail-field {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .res-detail-label {
            font-size: 0.7rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .res-detail-value {
            font-size: 0.9rem;
            color: #e0e0e0;
            font-weight: 500;
        }

        .res-detail-value.gold {
            color: #FFD700;
        }

        .res-detail-full {
            grid-column: 1 / -1;
        }

        .res-detail-status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .res-detail-status-badge.pending {
            background: rgba(255, 152, 0, 0.2);
            color: #ff9800;
            border: 1px solid rgba(255, 152, 0, 0.3);
        }

        .res-detail-status-badge.confirmed {
            background: rgba(33, 150, 243, 0.2);
            color: #2196f3;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }

        .res-detail-status-badge.completed {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .res-detail-status-badge.cancelled {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        .card-type-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-type-badge.reserve {
            background: rgba(255, 193, 7, 0.18);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .card-type-badge.buy {
            background: rgba(76, 175, 80, 0.18);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .size-item-card {
            background: rgba(255, 215, 0, 0.03);
            border: 1px solid rgba(255, 215, 0, 0.15);
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 8px;
        }

        .size-item-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .size-item-number {
            background: #7c6300;
            color: #FFD700;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .size-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .size-badge.empty {
            background: rgba(255, 255, 255, 0.05);
            color: #666;
            border: 1px solid #333;
        }

        /* Products list inside detail card */
        .res-products-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 420px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .res-prod-card {
            background: #242424;
            border: 1px solid #383838;
            border-radius: 10px;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: border-color 0.2s;
        }

        .res-prod-card:hover {
            border-color: #555;
        }

        .res-prod-top {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .res-prod-img {
            width: 52px;
            height: 52px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #444;
            flex-shrink: 0;
            background: #1a1a1a;
        }

        .res-prod-info {
            flex: 1;
            min-width: 0;
        }

        .res-prod-name {
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .res-prod-meta {
            font-size: 11.5px;
            color: var(--accent-gold);
            margin-top: 3px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .res-prod-type-badge {
            font-size: 9.5px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .type-buy { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.4); }
        .type-reserve { background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.4); }

        /* Size breakdown per item styles */
        .res-sizes-container {
            background: #1a1a1a;
            border: 1px solid #303030;
            border-radius: 8px;
            padding: 9px 12px;
        }

        .res-sizes-header {
            font-size: 10px;
            font-weight: 700;
            color: #ffd700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 7px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .res-item-size-row {
            padding: 6px 0;
            border-top: 1px dashed #2c2c2c;
        }
        .res-item-size-row:first-of-type {
            border-top: none;
            padding-top: 2px;
        }

        .res-item-badge {
            font-size: 10.5px;
            font-weight: 700;
            color: #ffd700;
            margin-bottom: 5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .res-sizes-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .res-size-chip {
            background: #282828;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            line-height: 1.2;
        }

        .size-chip-lbl {
            color: #aaa;
            font-weight: 500;
            font-size: 10.5px;
        }

        .size-chip-val {
            color: #fff;
            font-weight: 700;
            font-size: 11px;
        }

        .res-no-sizes {
            font-size: 11px;
            color: #777;
            font-style: italic;
            padding: 2px 0;
        }

        .res-item-size-preview {
            font-size: 10.5px;
            color: #ffd700;
            margin-top: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Action Buttons Row */
        .res-action-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 4px;
        }

        @media (max-width: 380px) {
            .res-action-buttons { grid-template-columns: 1fr; }
        }

        .action-btn {
            border: none;
            padding: 12px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            /* Larger touch target */
            min-height: 44px;
        }

        .btn-confirm { background: #007acc; color: #fff; }
        .btn-confirm:hover { background: #0099ff; }
        .btn-complete { background: #16a34a; color: #fff; }
        .btn-complete:hover { background: #22c55e; }
        .btn-cancel { background: #dc2626; color: #fff; }
        .btn-cancel:hover { background: #ef4444; }

        /* Custom Status Confirmation Modal (Matching index.php Clear Chat Modal) */
        .clear-chat-confirm-modal {
            position: fixed;
            top: 20px;
            right: 20px;
            left: auto;
            z-index: 10000005;
            pointer-events: none;
            opacity: 0;
            transform: translate(50px, -50px) scale(0.9);
            transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;
        }

        .clear-chat-confirm-modal.show {
            opacity: 1;
            pointer-events: auto;
            transform: translate(0, 0) scale(1);
        }

        /* Mobile: Center confirmation modal */
        @media (max-width: 768px) {
            .clear-chat-confirm-modal {
                top: 25px;
                left: 50%;
                right: auto;
                transform: translate(-50%, -50px) scale(0.9);
                width: calc(100% - 32px);
                max-width: 340px;
                display: flex;
                justify-content: center;
            }

            .clear-chat-confirm-modal.show {
                transform: translate(-50%, 0) scale(1);
            }
        }

        .clear-chat-confirm-card {
            background: linear-gradient(145deg, #242424, #161616);
            border: 1px solid rgba(255, 215, 0, 0.45);
            border-radius: 14px;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.8), 0 0 20px rgba(255, 215, 0, 0.12);
            padding: 14px 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            max-width: 340px;
            width: 100%;
            backdrop-filter: blur(14px);
        }

        .clear-chat-confirm-icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 215, 0, 0.15);
            border: 1px solid rgba(255, 215, 0, 0.4);
            color: #ffd700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .clear-chat-confirm-icon.icon-confirm {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.4);
            color: #22c55e;
        }

        .clear-chat-confirm-icon.icon-complete {
            background: rgba(0, 153, 255, 0.15);
            border: 1px solid rgba(0, 153, 255, 0.4);
            color: #0099ff;
        }

        .clear-chat-confirm-icon.icon-cancel {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #ff5252;
        }

        .clear-chat-confirm-icon.icon-pending {
            background: rgba(230, 161, 0, 0.15);
            border: 1px solid rgba(230, 161, 0, 0.4);
            color: #e6a100;
        }

        .clear-chat-confirm-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .clear-chat-confirm-title {
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.3;
        }

        .clear-chat-confirm-desc {
            color: #aaaaaa;
            font-size: 11px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.35;
            margin-bottom: 8px;
        }

        .clear-chat-confirm-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .clear-chat-btn-cancel {
            background: #2f2f2f;
            border: 1px solid #444;
            color: #ccc;
            font-size: 11px;
            font-weight: 600;
            font-family: inherit;
            padding: 5px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
        }

        .clear-chat-btn-cancel:hover {
            background: #3c3c3c;
            color: #fff;
        }

        .clear-chat-btn-delete {
            background: linear-gradient(135deg, #d32f2f, #b71c1c);
            border: none;
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            font-family: inherit;
            padding: 5px 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
            box-shadow: 0 2px 8px rgba(211, 47, 47, 0.4);
        }

        .clear-chat-btn-delete:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(211, 47, 47, 0.6);
        }

        .clear-chat-btn-delete.btn-status-confirm {
            background: linear-gradient(135deg, #16a34a, #15803d);
            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.4);
        }
        .clear-chat-btn-delete.btn-status-confirm:hover {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.6);
        }

        .clear-chat-btn-delete.btn-status-complete {
            background: linear-gradient(135deg, #0284c7, #0369a1);
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.4);
        }
        .clear-chat-btn-delete.btn-status-complete:hover {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.6);
        }

        .clear-chat-btn-delete.btn-status-cancel {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.4);
        }
        .clear-chat-btn-delete.btn-status-cancel:hover {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.6);
        }

        .clear-chat-btn-delete.btn-status-pending {
            background: linear-gradient(135deg, #d97706, #b45309);
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.4);
        }
        .clear-chat-btn-delete.btn-status-pending:hover {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.6);
        }

        /* Reservation List Table / Items */
        .res-list-container {
            flex: 1;
            overflow-y: auto;
            max-height: 620px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-right: 4px;
            /* Smooth scroll on mobile */
            -webkit-overflow-scrolling: touch;
        }

        @media (max-width: 900px) {
            .res-list-container { max-height: none; }
        }

        .res-card-item {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px 16px;
            min-height: 68px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .res-card-item:hover {
            border-color: var(--accent-gold);
            background: #252525;
            transform: translateX(2px);
        }

        .res-card-item.selected {
            border-color: var(--accent-gold);
            background: #221d0a;
        }

        .res-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex: 1;
        }

        .res-item-thumb {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #444;
            flex-shrink: 0;
        }

        .res-item-details {
            min-width: 0;
            overflow: hidden;
        }

        .res-item-code {
            color: var(--accent-gold);
            font-size: 13px;
            font-weight: 700;
        }

        .res-item-name {
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-top: 1px;
        }

        .res-item-dates {
            color: var(--text-muted);
            font-size: 11px;
            margin-top: 2px;
        }

        .res-item-right {
            text-align: right;
            flex-shrink: 0;
        }

        .res-item-price {
            color: var(--accent-gold);
            font-size: 13px;
            font-weight: 700;
        }

        /* Filter Tabs */
        .filter-tabs-row {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            padding-bottom: 4px;
            /* Hide scrollbar but keep scroll */
            scrollbar-width: none;
        }
        .filter-tabs-row::-webkit-scrollbar { display: none; }

        .tab-chip {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 7px 14px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
            /* Larger tap target */
            min-height: 34px;
            display: flex;
            align-items: center;
            user-select: none;
        }

        .tab-chip:hover:not(.active) {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 215, 0, 0.4);
            color: #fff;
            transform: translateY(-1px);
        }

        .tab-chip.active {
            background: var(--accent-gold-gradient);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 2px 10px rgba(255, 215, 0, 0.25);
        }

        /* QR Code Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 20000000 !important;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        #qrModal {
            z-index: 20000000 !important;
        }

        .modal-card {
            background: #1c1c1c;
            border: 2px solid var(--accent-gold);
            border-radius: 16px;
            padding: 24px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.9), 0 0 25px rgba(255, 215, 0, 0.2);
            position: relative;
            z-index: 20000001 !important;
            animation: qrModalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes qrModalPop {
            from { opacity: 0; transform: scale(0.92); }
            to { opacity: 1; transform: scale(1); }
        }

        /* Toast Notifications */
        .staff-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1e1e1e;
            border: 1px solid var(--accent-gold);
            border-radius: 10px;
            padding: 12px 18px;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 8px 30px rgba(0,0,0,0.8);
            z-index: 25000000 !important;
            display: none;
            align-items: center;
            gap: 10px;
            animation: slideDownToast 0.3s ease;
            max-width: calc(100vw - 32px);
        }

        /* Centre toast on mobile so it doesn't overflow */
        @media (max-width: 600px) {
            .staff-toast {
                right: 50%;
                transform: translateX(50%);
                bottom: 16px;
                font-size: 12px;
            }
        }

        /* Walk-In Specific Styling */
        .res-card-item.walkin-item {
            border-left: 3.5px solid var(--accent-gold) !important;
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.12);
        }

        .res-walkin-badge {
            background: rgba(255, 215, 0, 0.18);
            color: #ffd700;
            border: 1px solid rgba(255, 215, 0, 0.5);
            font-weight: 700;
            border-radius: 4px;
            padding: 2px 7px;
            font-size: 10px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .btn-walk-in {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.16) 0%, rgba(255, 215, 0, 0.08) 100%) !important;
            border: 1.5px solid rgba(255, 215, 0, 0.4) !important;
            color: #ffd700 !important;
            height: 38px !important;
            min-height: 38px !important;
            max-height: 38px !important;
            min-width: 110px !important;
            padding: 0 16px !important;
            border-radius: 10px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            line-height: 1 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2) !important;
            text-transform: none !important;
            letter-spacing: normal !important;
            box-sizing: border-box !important;
        }

        .btn-walk-in:hover {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.3) 0%, rgba(255, 215, 0, 0.18) 100%) !important;
            border-color: #ffd700 !important;
            color: #fff !important;
            box-shadow: 0 0 16px rgba(255, 215, 0, 0.35) !important;
            transform: translateY(-1px) !important;
        }

        /* Walk-in Calendar Picker Modal */
        .calendar-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 100000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }

        .calendar-modal-overlay.active {
            display: flex;
        }

        .calendar-modal {
            background: #1a1a1a;
            border-radius: 12px;
            padding: 20px;
            width: 90%;
            max-width: 440px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.7);
            border: 2px solid #333333;
        }

        .calendar-modal-header {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 12px;
            padding: 10px;
            background: #2a2a2a;
            border-radius: 8px;
            color: #ffffff;
        }

        .calendar-modal-nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .calendar-modal-nav button {
            background: rgba(0, 0, 0, 0.3);
            border: none;
            color: #ffd700;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .calendar-modal-nav button:hover {
            background: rgba(255, 215, 0, 0.2);
        }

        .calendar-modal-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: #ffffff;
            min-width: 140px;
            text-align: center;
        }

        .calendar-modal .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            background: #262626;
            border: 1px solid #333333;
            border-radius: 8px;
            padding: 4px;
        }

        .calendar-modal .calendar-day-header {
            background: #2a2a2a;
            color: #ffd700;
            font-weight: 600;
            font-size: 0.75rem;
            text-align: center;
            padding: 8px 2px;
        }

        .calendar-modal .calendar-day {
            background: #1e1e1e;
            color: #ffffff;
            min-height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s ease;
            user-select: none;
        }

        .calendar-modal .calendar-day:hover:not(.disabled):not(.other-month) {
            background: #ffd700;
            color: #111;
            font-weight: bold;
        }

        .calendar-modal .calendar-day.today {
            border: 1px solid #ffd700;
        }

        .calendar-modal .calendar-day.selected {
            background: #ffd700 !important;
            color: #111 !important;
            font-weight: bold;
        }

        .calendar-modal .calendar-day.disabled {
            color: #555555;
            background: #151515;
            cursor: not-allowed;
            text-decoration: line-through;
            opacity: 0.45;
        }

        .calendar-modal .calendar-day.other-month {
            opacity: 0.25;
            cursor: default;
        }

        .calendar-modal .calendar-day.booked-date {
            background: rgba(244, 67, 54, 0.25) !important;
            color: #ff8888 !important;
            border: 1px solid rgba(244, 67, 54, 0.5) !important;
            text-decoration: line-through;
            cursor: not-allowed !important;
            opacity: 0.75;
        }

        .calendar-modal-legend {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 12px;
            font-size: 0.75rem;
            flex-wrap: wrap;
        }

        .calendar-modal-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #aaaaaa;
        }

        .calendar-modal-legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        /* Slide Panel Styling for Walk-In Modal */
        .slide-panel {
            position: fixed;
            top: 0;
            right: -650px;
            width: 600px;
            max-width: 95vw;
            height: 100vh;
            background: #18181d;
            box-shadow: -5px 0 35px rgba(0,0,0,0.8);
            z-index: 10000;
            transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            border-left: 1px solid #333;
        }

        .slide-panel.slide-panel-open {
            right: 0;
        }

        .slide-panel-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.65);
            z-index: 9999;
            display: none;
            backdrop-filter: blur(3px);
        }

        .slide-panel-backdrop.active {
            display: block;
        }

        .slide-panel-content {
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }

        .slide-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid #333;
            background: #1e1e24;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .close-panel-btn {
            background: none;
            border: none;
            color: #aaa;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .close-panel-btn:hover {
            color: #ffd700;
            background: rgba(255,215,0,0.1);
        }

        /* Cancellation Slide Panel Put in Front of Reservation Details (Matches admin/calendar.php) */
        .cancellation-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.72);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            z-index: 10000040 !important;
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        .cancellation-backdrop.active {
            display: block;
            opacity: 1;
        }

        #cancellationModal {
            z-index: 10000050 !important;
            border-left: 2px solid rgba(244, 67, 54, 0.8) !important;
            box-shadow: -15px 0 45px rgba(0, 0, 0, 0.95), -2px 0 20px rgba(244, 67, 54, 0.3) !important;
        }

        .cancel-reason-select {
            width: 100%;
            box-sizing: border-box;
            background: #18191f;
            border: 1px solid #383b45;
            border-radius: 8px;
            color: #fff;
            padding: 12px 36px 12px 14px;
            font-size: 0.9rem;
            outline: none;
            cursor: pointer;
            transition: border 0.2s, box-shadow 0.2s;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23FFD700'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 18px;
        }
        .cancel-reason-select:focus {
            border-color: #f44336;
            box-shadow: 0 0 0 2px rgba(244, 67, 54, 0.2);
        }
        .cancel-reason-select option {
            background: #121316;
            color: #fff;
            padding: 8px;
        }

        .cancel-quick-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
            margin-bottom: 4px;
        }
        .cancel-quick-chip {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #bbb;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.74rem;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }
        .cancel-quick-chip:hover {
            background: rgba(244, 67, 54, 0.15);
            border-color: rgba(244, 67, 54, 0.4);
            color: #fff;
        }
        .cancel-quick-chip.active {
            background: rgba(244, 67, 54, 0.25);
            border-color: #f44336;
            color: #ff8888;
            font-weight: 600;
        }

        /* =======================================================
           100% RESPONSIVE MOBILE BOTTOM NAV WITH 4PX NOTCH GAP
           ======================================================= */
        .mobile-bottom-nav {
            display: none;
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100vw !important;
            max-width: 100% !important;
            height: 62px !important;
            background: transparent !important;
            z-index: 999999 !important;
            padding: 0 6px;
            padding-bottom: env(safe-area-inset-bottom, 0px);
            align-items: flex-end;
            justify-content: space-around;
            box-sizing: border-box !important;
            margin: 0 !important;
            pointer-events: auto;
            touch-action: manipulation;
        }

        /* Modular Responsive SVG Background Bar with Centered 4px Gap Notch */
        .mobile-nav-bg-container {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: flex-end;
            pointer-events: none;
            z-index: 1;
            filter: drop-shadow(0 -4px 14px rgba(0, 0, 0, 0.75));
        }

        .mobile-nav-wing {
            flex: 1;
            height: calc(100% - 10px);
            background: #212328;
        }

        .mobile-nav-notch-center {
            width: 108px;
            height: 100%;
            flex-shrink: 0;
            display: block;
        }

        /* Responsive display: only show on mobile/tablet screens */
        @media (max-width: 900px) {
            .mobile-bottom-nav {
                display: flex !important;
            }
            .staff-container {
                padding-bottom: 86px !important;
            }
            .staff-toast {
                bottom: 76px !important;
            }
        }

        /* Nav Action Buttons */
        .nav-action-item {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 4px 4px;
            height: 48px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            text-decoration: none;
            position: relative;
            z-index: 2;
        }

        .nav-action-item .nav-icon {
            font-size: 18px;
            transition: transform 0.2s ease, color 0.2s ease;
        }

        .nav-action-item .nav-icon-img {
            width: 22px;
            height: 22px;
            object-fit: contain;
            transition: transform 0.2s ease, filter 0.2s ease, opacity 0.2s ease;
            opacity: 0.65;
            filter: brightness(0.85);
            display: block;
            margin-bottom: 2px;
        }

        .nav-action-item .nav-icon-img.nav-icon-walkin {
            opacity: 0.75;
        }

        .nav-action-item:hover .nav-icon-img {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        .nav-action-item .nav-label {
            font-size: 10px;
            font-weight: 600;
            margin-top: 1px;
            letter-spacing: 0.2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #9ca3af;
            transition: color 0.2s ease;
        }

        /* Active State: Signature Gold matching index.php */
        .nav-action-item.active {
            color: #ffd700 !important;
        }

        .nav-action-item.active .nav-icon {
            color: #ffd700 !important;
            transform: translateY(-1px);
        }

        .nav-action-item.active .nav-icon-img {
            opacity: 1;
            transform: translateY(-1px) scale(1.08);
            filter: brightness(0) saturate(100%) invert(79%) sepia(74%) saturate(840%) hue-rotate(359deg) brightness(103%) contrast(106%) drop-shadow(0 2px 6px rgba(255, 215, 0, 0.45));
        }

        .nav-action-item.active .nav-label {
            color: #ffd700 !important;
            font-weight: 700;
        }

        .nav-action-item:active {
            transform: scale(0.92);
        }

        /* Center cradle air gap */
        .nav-center-gap {
            width: 82px;
            flex-shrink: 0;
            pointer-events: none;
            z-index: 2;
        }

        /* Center Floating Action Button (Pink Circle with Deep Curved Gap - Matches index.php) */
        .nav-fab-wrapper {
            position: absolute;
            left: 50%;
            top: -16px;
            transform: translateX(-50%);
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: auto;
        }

        .nav-fab-scan-btn {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #ff2a6d; /* Vibrant hot pink matching image */
            border: none;
            color: #121212;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.75), 0 3px 10px rgba(255, 42, 109, 0.45);
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease;
            -webkit-tap-highlight-color: transparent;
            position: relative;
            outline: none;
        }

        .nav-fab-scan-btn:hover {
            transform: scale(1.06);
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.85), 0 5px 16px rgba(255, 42, 109, 0.6);
        }

        .nav-fab-scan-btn:active {
            transform: scale(0.92);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.6);
        }

        .nav-fab-scan-btn i {
            font-size: 20px;
            font-weight: 900;
            color: #121212 !important; /* Dark/black icon */
        }

        .nav-fab-scan-btn img,
        .nav-fab-scan-btn .nav-fab-icon {
            width: 26px;
            height: 26px;
            object-fit: contain;
            display: block;
            pointer-events: none;
            transition: transform 0.2s ease;
        }

        /* Micro screen optimization (< 360px) */
        @media (max-width: 360px) {
            .mobile-bottom-nav {
                padding: 0 2px;
            }
            .nav-action-item .nav-icon {
                font-size: 16px;
            }
            .nav-action-item .nav-label {
                font-size: 9px;
            }
        }

        /* =======================================================
           LAPTOP / DESKTOP MODALS & FULL-WIDTH WORKSPACE
           (Scanner and Reservation Details open as modals on laptop)
           ======================================================= */
        @media (min-width: 901px) {
            /* Wider container layout on laptop to maximize space */
            .staff-container {
                max-width: 1480px !important;
                width: 96% !important;
            }

            /* Full-width workspace grid on laptop when modals are closed */
            .workspace-grid {
                display: block !important;
            }
            .workspace-grid > div:first-child {
                display: contents !important;
            }

            /* Scanner Modal on Laptop */
            #qrScannerPanel:not(.desktop-modal-open) {
                display: none !important;
            }

            #qrScannerPanel.desktop-modal-open {
                display: flex !important;
                position: fixed !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                width: 480px !important;
                max-width: 92vw !important;
                max-height: 90vh !important;
                z-index: 1000030 !important;
                background: #18191f !important;
                border: 1.5px solid rgba(255, 215, 0, 0.45) !important;
                border-radius: 20px !important;
                box-shadow: 0 25px 70px rgba(0, 0, 0, 0.85), 0 0 35px rgba(255, 215, 0, 0.2) !important;
                overflow: hidden !important;
                animation: desktopModalZoomIn 0.22s cubic-bezier(0.16, 1, 0.3, 1) !important;
            }

            #desktopScannerBackdrop {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.78);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                z-index: 1000025;
                display: none;
                animation: fadeInScannerBackdrop 0.2s ease-out;
            }

            #desktopScannerBackdrop.active {
                display: block !important;
            }

            .scanner-modal-close-btn {
                background: rgba(255, 255, 255, 0.08);
                border: 1px solid rgba(255, 255, 255, 0.16);
                color: #e0e0e0;
                font-size: 22px;
                line-height: 1;
                width: 32px;
                height: 32px;
                border-radius: 8px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.18s ease;
                padding: 0;
            }

            .scanner-modal-close-btn:hover {
                background: rgba(239, 68, 68, 0.25);
                border-color: #ef4444;
                color: #ff6b6b;
                transform: scale(1.08);
            }

            /* Header QR button on laptop (Opens Scanner Modal) */
            .btn-header-qr-scanner {
                background: linear-gradient(135deg, rgba(255, 215, 0, 0.16) 0%, rgba(255, 215, 0, 0.08) 100%) !important;
                border: 1.5px solid rgba(255, 215, 0, 0.4) !important;
                color: #ffd700 !important;
                height: 38px !important;
                min-height: 38px !important;
                max-height: 38px !important;
                min-width: 110px !important;
                padding: 0 16px !important;
                border-radius: 10px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 8px !important;
                font-size: 12px !important;
                font-weight: 700 !important;
                line-height: 1 !important;
                cursor: pointer !important;
                transition: all 0.2s ease !important;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2) !important;
                box-sizing: border-box !important;
            }

            .btn-header-qr-scanner:hover {
                background: linear-gradient(135deg, rgba(255, 215, 0, 0.3) 0%, rgba(255, 215, 0, 0.18) 100%) !important;
                border-color: #ffd700 !important;
                color: #fff !important;
                box-shadow: 0 0 16px rgba(255, 215, 0, 0.35) !important;
                transform: translateY(-1px) !important;
            }

            /* Reservation Details Modal on Laptop (Wider & more spacious) */
            #detailPanel:not(.drawer-open) {
                display: none !important;
            }

            #detailPanel.drawer-open {
                display: flex !important;
                position: fixed !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                width: 1080px !important;
                max-width: 95vw !important;
                height: auto !important;
                max-height: 90vh !important;
                z-index: 1000030 !important;
                background: #18191f !important;
                border: 1.5px solid rgba(255, 215, 0, 0.45) !important;
                border-radius: 20px !important;
                box-shadow: 0 25px 80px rgba(0, 0, 0, 0.85), 0 0 35px rgba(255, 215, 0, 0.18) !important;
                overflow: hidden !important;
                visibility: visible !important;
                pointer-events: auto !important;
                animation: detailModalZoomIn 0.22s cubic-bezier(0.16, 1, 0.3, 1) !important;
            }

            #drawerBackdrop {
                position: fixed !important;
                inset: 0 !important;
                background: rgba(0, 0, 0, 0.78) !important;
                backdrop-filter: blur(8px) !important;
                -webkit-backdrop-filter: blur(8px) !important;
                z-index: 1000025 !important;
                display: none;
                animation: fadeInDetailBackdrop 0.2s ease-out;
            }

            #drawerBackdrop.visible {
                display: block !important;
            }

            #detailPanel .card-panel-header {
                padding: 14px 22px !important;
                background: #1c1e26 !important;
                border-bottom: 1px solid rgba(255, 215, 0, 0.25) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                flex-shrink: 0 !important;
            }

            #detailPanel .card-panel-body {
                flex: 1 !important;
                overflow-y: auto !important;
                padding: 24px 30px !important;
                max-height: calc(90vh - 75px) !important;
            }

            .drawer-close-btn {
                display: none !important;
            }

            .detail-modal-close-btn {
                background: rgba(255, 255, 255, 0.08);
                border: 1px solid rgba(255, 255, 255, 0.16);
                color: #e0e0e0;
                font-size: 22px;
                line-height: 1;
                width: 32px;
                height: 32px;
                border-radius: 8px;
                cursor: pointer;
                display: flex !important;
                align-items: center;
                justify-content: center;
                transition: all 0.18s ease;
                padding: 0;
            }

            .detail-modal-close-btn:hover {
                background: rgba(239, 68, 68, 0.25);
                border-color: #ef4444;
                color: #ff6b6b;
                transform: scale(1.08);
            }

            /* Reservation Records Modal on Laptop (Hidden by default, opens as modal on clicking KPI boxes) */
            #recordsListCardPanel:not(.desktop-records-modal-open) {
                display: none !important;
            }

            #recordsListCardPanel.desktop-records-modal-open {
                display: flex !important;
                position: fixed !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                width: 980px !important;
                max-width: 95vw !important;
                height: 84vh !important;
                max-height: 88vh !important;
                z-index: 1000020 !important;
                background: #18191f !important;
                border: 1.5px solid rgba(255, 215, 0, 0.45) !important;
                border-radius: 20px !important;
                box-shadow: 0 25px 80px rgba(0, 0, 0, 0.85), 0 0 35px rgba(255, 215, 0, 0.18) !important;
                overflow: hidden !important;
                visibility: visible !important;
                pointer-events: auto !important;
                animation: detailModalZoomIn 0.22s cubic-bezier(0.16, 1, 0.3, 1) !important;
            }

            #recordsModalBackdrop {
                position: fixed !important;
                inset: 0 !important;
                background: rgba(0, 0, 0, 0.78) !important;
                backdrop-filter: blur(8px) !important;
                -webkit-backdrop-filter: blur(8px) !important;
                z-index: 1000015 !important;
                display: none;
                animation: fadeInDetailBackdrop 0.2s ease-out;
            }

            #recordsModalBackdrop.active {
                display: block !important;
            }

            #recordsListCardPanel.desktop-records-modal-open .card-panel-header {
                padding: 14px 22px !important;
                background: #1c1e26 !important;
                border-bottom: 1px solid rgba(255, 215, 0, 0.25) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                flex-shrink: 0 !important;
            }

            #recordsListCardPanel.desktop-records-modal-open .card-panel-body {
                flex: 1 !important;
                overflow-y: auto !important;
                padding: 20px 24px !important;
                min-height: 0 !important;
            }

            #recordsListCardPanel.desktop-records-modal-open #reservationsList {
                flex: 1 !important;
                overflow-y: auto !important;
                max-height: none !important;
            }

            .records-modal-close-btn {
                background: rgba(255, 255, 255, 0.08);
                border: 1px solid rgba(255, 255, 255, 0.16);
                color: #e0e0e0;
                font-size: 22px;
                line-height: 1;
                width: 32px;
                height: 32px;
                border-radius: 8px;
                cursor: pointer;
                display: flex !important;
                align-items: center;
                justify-content: center;
                transition: all 0.18s ease;
                padding: 0;
            }

            .records-modal-close-btn:hover {
                background: rgba(239, 68, 68, 0.25);
                border-color: #ef4444;
                color: #ff6b6b;
                transform: scale(1.08);
            }

            /* Cancel Reservation Modal on Laptop (Big Centered Modal instead of slide panel) */
            #cancellationModal:not(.slide-panel-open) {
                display: none !important;
            }

            #cancellationModal.slide-panel-open {
                display: flex !important;
                flex-direction: column !important;
                position: fixed !important;
                top: 50% !important;
                left: 50% !important;
                right: auto !important;
                bottom: auto !important;
                transform: translate(-50%, -50%) !important;
                width: 760px !important;
                max-width: 94vw !important;
                height: auto !important;
                max-height: 90vh !important;
                z-index: 10000050 !important;
                background: #18191f !important;
                border: 1.5px solid rgba(244, 67, 54, 0.6) !important;
                border-radius: 20px !important;
                box-shadow: 0 25px 85px rgba(0, 0, 0, 0.92), 0 0 45px rgba(244, 67, 54, 0.25) !important;
                overflow: hidden !important;
                transition: none !important;
                animation: cancelModalZoomIn 0.22s cubic-bezier(0.16, 1, 0.3, 1) !important;
            }

            #cancellationModal .slide-panel-content {
                display: flex !important;
                flex-direction: column !important;
                height: 100% !important;
                max-height: 90vh !important;
                overflow: hidden !important;
            }

            #cancellationModal .slide-panel-header {
                flex-shrink: 0 !important;
                padding: 18px 24px !important;
                background: linear-gradient(180deg, #22232b, #191a21) !important;
                border-bottom: 1px solid rgba(244, 67, 54, 0.35) !important;
            }

            #cancellationModal .slide-panel-body {
                flex: 1 1 auto !important;
                overflow-y: auto !important;
                padding: 24px 28px !important;
                max-height: calc(90vh - 72px) !important;
            }

            #cancellationModal .close-panel-btn {
                background: rgba(255, 255, 255, 0.08);
                border: 1px solid rgba(255, 255, 255, 0.16);
                color: #e0e0e0;
                font-size: 18px;
                width: 34px;
                height: 34px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                transition: all 0.2s ease;
                cursor: pointer;
            }

            #cancellationModal .close-panel-btn:hover {
                background: rgba(239, 68, 68, 0.25);
                border-color: #ef4444;
                color: #ff6b6b;
                transform: scale(1.08);
            }

            #cancellationBackdrop {
                position: fixed !important;
                inset: 0 !important;
                background: rgba(0, 0, 0, 0.8) !important;
                backdrop-filter: blur(8px) !important;
                -webkit-backdrop-filter: blur(8px) !important;
                z-index: 10000045 !important;
            }

            #cancellationBackdrop.active {
                display: block !important;
                opacity: 1 !important;
                animation: fadeInCancelBackdrop 0.2s ease-out !important;
            }

            /* Walk-In Modal on Laptop (Centered Modal instead of slide panel) */
            #walkInModal:not(.slide-panel-open) {
                display: none !important;
            }

            #walkInModal.slide-panel-open {
                display: flex !important;
                flex-direction: column !important;
                position: fixed !important;
                top: 50% !important;
                left: 50% !important;
                right: auto !important;
                bottom: auto !important;
                transform: translate(-50%, -50%) !important;
                width: 960px !important;
                max-width: 94vw !important;
                height: auto !important;
                max-height: 88vh !important;
                z-index: 100000 !important;
                background: #14151a !important;
                border: 1.5px solid rgba(255, 215, 0, 0.45) !important;
                border-radius: 20px !important;
                box-shadow: 0 25px 85px rgba(0, 0, 0, 0.92), 0 0 45px rgba(255, 215, 0, 0.18) !important;
                overflow: hidden !important;
                transition: none !important;
                animation: staffModalZoomIn 0.22s cubic-bezier(0.16, 1, 0.3, 1) !important;
            }

            #walkInModal .slide-panel-content {
                display: flex !important;
                flex-direction: column !important;
                height: 100% !important;
                max-height: 88vh !important;
                overflow: hidden !important;
            }

            #walkInModal .slide-panel-header {
                flex-shrink: 0 !important;
                padding: 18px 24px !important;
                background: linear-gradient(180deg, #1f2027, #16171d) !important;
                border-bottom: 1px solid rgba(255, 215, 0, 0.25) !important;
                border-top-left-radius: 20px !important;
                border-top-right-radius: 20px !important;
            }

            #walkInModal .slide-panel-body {
                flex: 1 1 auto !important;
                overflow-y: auto !important;
                padding: 24px 28px !important;
                max-height: calc(88vh - 72px) !important;
            }

            #walkInModal .close-panel-btn {
                background: rgba(255, 255, 255, 0.08) !important;
                border: 1px solid rgba(255, 255, 255, 0.16) !important;
                color: #e0e0e0 !important;
                font-size: 16px !important;
                width: 34px !important;
                height: 34px !important;
                border-radius: 8px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                cursor: pointer !important;
                transition: all 0.2s ease !important;
                padding: 0 !important;
            }

            #walkInModal .close-panel-btn:hover {
                background: rgba(239, 68, 68, 0.25) !important;
                border-color: #ef4444 !important;
                color: #ff6b6b !important;
                transform: scale(1.08) !important;
            }

            #walkInModalBackdrop {
                position: fixed !important;
                inset: 0 !important;
                background: rgba(0, 0, 0, 0.8) !important;
                backdrop-filter: blur(8px) !important;
                -webkit-backdrop-filter: blur(8px) !important;
                z-index: 99998 !important;
            }

            #walkInModalBackdrop.active {
                display: block !important;
                animation: fadeInStaffBackdrop 0.2s ease-out !important;
            }

            /* Staff Profile & Account Modal on Laptop (Centered Modal instead of slide panel) */
            #staffProfileModalOverlay:not(.slide-panel-open) {
                display: none !important;
            }

            #staffProfileModalOverlay.slide-panel-open {
                display: flex !important;
                flex-direction: column !important;
                position: fixed !important;
                top: 50% !important;
                left: 50% !important;
                right: auto !important;
                bottom: auto !important;
                transform: translate(-50%, -50%) !important;
                width: 620px !important;
                max-width: 92vw !important;
                height: auto !important;
                max-height: 88vh !important;
                z-index: 100001 !important;
                background: linear-gradient(165deg, #1c1d25 0%, #15161c 60%, #101115 100%) !important;
                border: 1.5px solid rgba(255, 215, 0, 0.45) !important;
                border-radius: 20px !important;
                box-shadow: 0 25px 85px rgba(0, 0, 0, 0.92), 0 0 45px rgba(255, 215, 0, 0.18) !important;
                overflow: hidden !important;
                transition: none !important;
                animation: staffModalZoomIn 0.22s cubic-bezier(0.16, 1, 0.3, 1) !important;
            }

            #staffProfileModalOverlay .slide-panel-content {
                display: flex !important;
                flex-direction: column !important;
                height: 100% !important;
                max-height: 88vh !important;
                overflow: hidden !important;
            }

            #staffProfileModalOverlay .slide-panel-header,
            #staffProfileModalOverlay.profile-slide-panel .staff-profile-top-bar {
                flex-shrink: 0 !important;
                padding: 18px 24px !important;
                background: linear-gradient(180deg, #1f2027, #16171d) !important;
                border-bottom: 1px solid rgba(255, 215, 0, 0.25) !important;
                border-top-left-radius: 20px !important;
                border-top-right-radius: 20px !important;
            }

            #staffProfileModalOverlay .slide-panel-body,
            #staffProfileModalOverlay .staff-profile-content {
                flex: 1 1 auto !important;
                overflow-y: auto !important;
                padding: 24px 28px !important;
                max-height: calc(88vh - 72px) !important;
            }

            #staffProfileModalOverlay .close-panel-btn {
                background: rgba(255, 255, 255, 0.08) !important;
                border: 1px solid rgba(255, 255, 255, 0.16) !important;
                color: #e0e0e0 !important;
                font-size: 16px !important;
                width: 34px !important;
                height: 34px !important;
                border-radius: 8px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                cursor: pointer !important;
                transition: all 0.2s ease !important;
                padding: 0 !important;
            }

            #staffProfileModalOverlay .close-panel-btn:hover {
                background: rgba(239, 68, 68, 0.25) !important;
                border-color: #ef4444 !important;
                color: #ff6b6b !important;
                transform: scale(1.08) !important;
            }

            #staffProfileModalBackdrop {
                position: fixed !important;
                inset: 0 !important;
                background: rgba(0, 0, 0, 0.8) !important;
                backdrop-filter: blur(8px) !important;
                -webkit-backdrop-filter: blur(8px) !important;
                z-index: 100000 !important;
            }

            #staffProfileModalBackdrop.active {
                display: block !important;
                animation: fadeInStaffBackdrop 0.2s ease-out !important;
            }
        }

        @keyframes staffModalZoomIn {
            from {
                opacity: 0;
                transform: translate(-50%, -46%) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        @keyframes fadeInStaffBackdrop {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes cancelModalZoomIn {
            from {
                opacity: 0;
                transform: translate(-50%, -46%) scale(0.94);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        @keyframes fadeInCancelBackdrop {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes desktopModalZoomIn {
            from {
                opacity: 0;
                transform: translate(-50%, -46%) scale(0.94);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        @keyframes detailModalZoomIn {
            from {
                opacity: 0;
                transform: translate(-50%, -46%) scale(0.94);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        @keyframes fadeInScannerBackdrop {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInDetailBackdrop {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* =======================================================
           RESERVATION CALENDAR STYLES (Matches admin/calendar.php)
           ======================================================= */
        .calendar-card-panel {
            background: rgba(255, 255, 255, 0.05) !important;
            color: #ffffff;
            border-radius: 12px !important;
            padding: 10px 14px;
            border: 1px solid #333 !important;
            box-shadow: inset 0 0 25px rgba(255, 215, 0, 0.10) !important;
            margin-bottom: 20px;
        }

        .cal-main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding: 6px 12px !important;
            background: rgba(255, 255, 255, 0.03) !important;
            border-radius: 8px;
            border: 1px solid #222 !important;
            gap: 12px;
            flex-wrap: wrap;
        }

        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .calendar-card-panel .card-panel-title {
            color: #ffd700;
            font-size: 1.05rem;
            font-weight: 500;
        }

        .cal-nav-btn {
            background: rgba(0, 0, 0, 0.3);
            border: none;
            color: #ffd700;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .cal-nav-btn.cal-today-btn {
            color: #ffd700 !important;
            font-weight: 500;
            font-size: 12px;
        }

        .cal-nav-btn:hover {
            background: rgba(124, 99, 0, 0.3);
            transform: translateY(-1px);
        }

        .calendar-title {
            font-size: 1.1rem;
            font-weight: 500;
            color: #ffd700;
            letter-spacing: 0.3px;
        }

        .cal-product-filter-wrap {
            position: relative;
        }

        .cal-product-filter-select {
            background: #111217;
            color: #ffd700;
            border: 1px solid rgba(255, 215, 0, 0.35);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            outline: none;
            transition: border-color 0.2s;
            max-width: 220px;
        }

        .cal-product-filter-select:focus {
            border-color: #ffd700;
            box-shadow: 0 0 8px rgba(255, 215, 0, 0.2);
        }

        .cal-card-body {
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: all 0.25s ease;
        }

        .legend {
            display: flex;
            gap: 15px;
            margin: 2px 0 8px 0;
            flex-wrap: wrap;
            align-items: center;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: #aaa;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            flex-shrink: 0;
        }

        .cal-date-filter-banner {
            background: rgba(255, 215, 0, 0.08);
            border: 1px solid rgba(255, 215, 0, 0.22);
            border-radius: 8px;
            padding: 8px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 12px;
            color: #ddd;
        }

        .cal-clear-filter-btn {
            background: rgba(244, 67, 54, 0.15);
            border: 1px solid rgba(244, 67, 54, 0.4);
            color: #ff8888;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.18s;
        }

        .cal-clear-filter-btn:hover {
            background: rgba(244, 67, 54, 0.3);
            border-color: #f44336;
            color: #fff;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #222;
            border: 1px solid #222;
            border-radius: 8px;
            overflow: hidden;
        }

        .calendar-day-header {
            background: rgba(255, 255, 255, 0.03);
            padding: 10px 5px;
            text-align: center;
            font-weight: 500;
            color: #7c6300;
            font-size: 0.8rem;
        }

        .day-short { display: none; }

        .calendar-day {
            background: #0d0d0d;
            color: #ffffff;
            min-height: 84px;
            padding: 6px;
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
            display: flex;
            flex-direction: column;
            gap: 2px;
            overflow: hidden;
            box-sizing: border-box;
        }

        .calendar-day:hover {
            background: #1a1a1a;
            border-color: rgba(124, 99, 0, 0.5);
        }

        .calendar-day.other-month {
            background: #080808;
            color: #333;
            opacity: 1;
        }

        .calendar-day.today {
            background: rgba(124, 99, 0, 0.3);
            color: #FFD700;
            font-weight: 600;
            border-color: #7c6300;
        }

        .calendar-day.today .calendar-day-number {
            color: #ffd700;
            font-weight: 700;
        }

        .calendar-day.selected-date {
            background: rgba(124, 99, 0, 0.25) !important;
            border-color: #ffd700 !important;
            box-shadow: inset 0 0 10px rgba(255, 215, 0, 0.2) !important;
        }

        .calendar-day.selected-date .calendar-day-number {
            color: #ffd700 !important;
            font-weight: 700;
        }

        .calendar-day.fully-booked {
            background: rgba(244, 67, 54, 0.15);
            border-color: rgba(244, 67, 54, 0.4);
        }

        .calendar-day.fully-booked .calendar-day-number {
            color: #f44336;
        }

        .calendar-day-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3px;
            line-height: 1;
        }

        .calendar-day-number {
            font-weight: 500;
            margin-bottom: 0;
            font-size: 0.8rem;
            color: #bbb;
        }

        .mobile-res-count {
            display: none;
            background: #ffd700;
            color: #000;
            font-size: 9px;
            font-weight: 800;
            padding: 1px 5px;
            border-radius: 10px;
        }

        .cal-more-text {
            font-size: 0.65rem;
            font-weight: 700;
            color: #ffd700;
            cursor: pointer;
            line-height: 1;
            user-select: none;
            transition: color 0.2s ease, opacity 0.2s ease;
            white-space: nowrap;
            margin-left: auto;
        }

        .cal-more-text:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        .reservation-item {
            background: #28a745;
            color: #ffffff;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 10px;
            margin: 1px 0;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2px;
            box-sizing: border-box;
            font-weight: 500;
        }

        .reservation-item:hover {
            transform: scale(1.03);
            filter: brightness(1.15);
        }

        .reservation-item.pending {
            background: rgba(255, 152, 0, 0.3) !important;
            color: #ff9800 !important;
            border: 1px solid rgba(255, 152, 0, 0.35);
        }

        .reservation-item.confirmed {
            background: rgba(33, 150, 243, 0.3) !important;
            color: #2196f3 !important;
            border: 1px solid rgba(33, 150, 243, 0.35);
        }

        .reservation-item.completed {
            background: rgba(76, 175, 80, 0.3) !important;
            color: #4CAF50 !important;
            border: 1px solid rgba(76, 175, 80, 0.35);
        }

        .reservation-item.cancelled {
            background: rgba(244, 67, 54, 0.3) !important;
            color: #f44336 !important;
            border: 1px solid rgba(244, 67, 54, 0.35);
        }

        .reservation-item.rescheduled {
            background: rgba(245, 158, 11, 0.3) !important;
            color: #f59e0b !important;
            border: 1px solid rgba(245, 158, 11, 0.35);
        }

        .reservation-item.walkin-item {
            background: rgba(255, 215, 0, 0.2) !important;
            color: #ffd700 !important;
            border: 1px solid rgba(255, 215, 0, 0.4);
            border-left: 3px solid #ffd700 !important;
            box-shadow: 0 1px 6px rgba(255, 215, 0, 0.25);
            font-weight: 600;
        }

        .reservation-item .res-item-name {
            color: inherit !important;
            font-size: 10px;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
            min-width: 0;
            margin-top: 0;
        }

        .reservation-item.pending .res-item-name {
            color: #ff9800 !important;
        }

        .reservation-item.confirmed .res-item-name {
            color: #2196f3 !important;
        }

        .reservation-item.completed .res-item-name {
            color: #4CAF50 !important;
        }

        .reservation-item.cancelled .res-item-name {
            color: #f44336 !important;
        }

        .reservation-item.rescheduled .res-item-name {
            color: #f59e0b !important;
        }

        .reservation-item.walkin-item .res-item-name {
            color: #ffd700 !important;
        }

        /* =======================================================
           MOBILE VIEW MODES (Home View vs Search/Records View vs Scanner View)
           ======================================================= */
        @media (max-width: 900px) {
            .btn-header-qr-scanner,
            .detail-modal-close-btn,
            #desktopScannerBackdrop,
            .scanner-modal-close-btn,
            .records-modal-close-btn,
            #recordsModalBackdrop {
                display: none !important;
            }

            #qrScannerPanel {
                display: none !important;
            }

            /* ── Default Mobile View (Home Mode): Hide Reservation Records, Show 6 Responsive KPI Cards ── */
            body:not(.mobile-search-view-mode):not(.mobile-scanner-view-mode) #recordsListCardPanel {
                display: none !important;
            }

            /* ── Reservation Calendar is visible on LAPTOP ONLY (completely hidden on mobile) ── */
            #staffCalendarPanel {
                display: none !important;
            }

            body:not(.mobile-search-view-mode):not(.mobile-scanner-view-mode) .kpi-grid {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px !important;
                margin-top: 4px !important;
                width: 100% !important;
            }

            .kpi-card:first-child {
                grid-column: 1 / -1 !important;
            }

            .kpi-card {
                padding: 14px 12px !important;
                border-radius: 12px !important;
                min-height: unset !important;
                background: rgba(26, 26, 26, 0.6) !important;
                backdrop-filter: blur(10px) !important;
                -webkit-backdrop-filter: blur(10px) !important;
                border: 1px solid rgba(255, 255, 255, 0.05) !important;
                display: flex !important;
                align-items: stretch !important;
                justify-content: space-between !important;
                box-shadow: inset 0 0 20px rgba(255, 215, 0, 0.22) !important;
                transition: transform 0.15s, border-color 0.15s, box-shadow 0.15s !important;
                -webkit-tap-highlight-color: transparent !important;
                cursor: pointer !important;
            }

            .kpi-card:hover, .kpi-card.active, .kpi-card:active {
                border-color: #ffd700 !important;
                transform: scale(0.98) !important;
                box-shadow: inset 0 0 25px rgba(255, 215, 0, 0.45), 0 2px 10px rgba(0, 0, 0, 0.5) !important;
            }

            .kpi-info {
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
                width: 100% !important;
                min-height: unset !important;
                gap: 6px !important;
            }

            .kpi-info h4 {
                font-size: 11px !important;
                font-weight: 500 !important;
                letter-spacing: 0.4px !important;
                color: #888888 !important;
                margin: 0 !important;
                white-space: nowrap !important;
            }

            .kpi-info .kpi-num {
                font-size: 22px !important;
                font-weight: 600 !important;
                color: #ffd700 !important;
                text-shadow: 0 0 12px rgba(255, 215, 0, 0.4) !important;
                margin: 0 !important;
                line-height: 1.15 !important;
                text-align: right !important;
                align-self: flex-end !important;
                width: 100% !important;
                white-space: nowrap !important;
            }

            .kpi-icon {
                display: none !important;
            }

            /* ── Mobile Search / Reservation Records View Mode (Full Screen Edge-to-Edge Stretched, No Margin, Above Bottom Bar) ── */
            body.mobile-search-view-mode {
                overflow: hidden !important;
            }

            body.mobile-search-view-mode .staff-header {
                display: none !important;
            }

            body.mobile-search-view-mode .staff-container {
                height: calc(100dvh - 62px) !important;
                max-height: calc(100dvh - 62px) !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100vw !important;
                width: 100vw !important;
                display: flex !important;
                flex-direction: column !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
                gap: 0 !important;
            }

            body.mobile-search-view-mode .workspace-grid {
                flex: 1 !important;
                height: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                width: 100% !important;
                max-width: 100vw !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
            }

            body.mobile-search-view-mode .workspace-grid > div:first-child {
                display: contents !important;
            }

            body.mobile-search-view-mode #recordsListCardPanel {
                display: flex !important;
                flex-direction: column !important;
                flex: 1 !important;
                height: 100% !important;
                width: 100% !important;
                max-width: 100vw !important;
                min-height: 0 !important;
                border-radius: 0 !important;
                border-left: none !important;
                border-right: none !important;
                border-top: none !important;
                border-bottom: 1px solid rgba(255, 215, 0, 0.25) !important;
                box-shadow: none !important;
                animation: fadeInSearchPanel 0.22s ease-out;
                margin: 0 !important;
                padding: 0 !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            @keyframes fadeInSearchPanel {
                from { opacity: 0; transform: translateY(8px); }
                to { opacity: 1; transform: translateY(0); }
            }

            body.mobile-search-view-mode #recordsListCardPanel .card-panel-header {
                padding: 12px 14px !important;
                background: #181a20 !important;
                border-bottom: 1px solid rgba(255, 215, 0, 0.2) !important;
                flex-shrink: 0 !important;
                border-radius: 0 !important;
            }

            body.mobile-search-view-mode #recordsListCardPanel .card-panel-body {
                flex: 1 !important;
                display: flex !important;
                flex-direction: column !important;
                padding: 12px 12px 6px 12px !important;
                gap: 8px !important;
                overflow: hidden !important;
                min-height: 0 !important;
            }

            body.mobile-search-view-mode .search-box-row {
                width: 100% !important;
                margin-bottom: 2px !important;
                flex-shrink: 0 !important;
            }

            body.mobile-search-view-mode .filter-tabs-row {
                display: flex !important;
                overflow-x: auto !important;
                white-space: nowrap !important;
                padding-bottom: 6px !important;
                gap: 6px !important;
                -webkit-overflow-scrolling: touch !important;
                scrollbar-width: none !important;
                flex-shrink: 0 !important;
            }
            body.mobile-search-view-mode .filter-tabs-row::-webkit-scrollbar {
                display: none !important;
            }

            body.mobile-search-view-mode .res-list-container {
                flex: 1 !important;
                height: 100% !important;
                max-height: none !important;
                min-height: 0 !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch !important;
                padding-bottom: 20px !important;
            }

            body.mobile-search-view-mode .kpi-grid,
            body.mobile-search-view-mode #qrScannerPanel {
                display: none !important;
            }

            /* ── Mobile Scanner-Only View Mode (Full Screen Camera down to Bottom Nav Bar) ── */
            body.mobile-scanner-view-mode {
                overflow: hidden !important;
            }

            body.mobile-scanner-view-mode .staff-container {
                height: calc(100dvh - 65px) !important;
                max-height: calc(100dvh - 65px) !important;
                padding: 6px 10px 72px 10px !important;
                display: flex !important;
                flex-direction: column !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            body.mobile-scanner-view-mode .workspace-grid {
                flex: 1 !important;
                height: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            body.mobile-scanner-view-mode .workspace-grid > div:first-child {
                flex: 1 !important;
                height: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            body.mobile-scanner-view-mode #qrScannerPanel {
                display: flex !important;
                flex: 1 !important;
                height: 100% !important;
                width: 100% !important;
                max-width: 100% !important;
                border-color: #ff2a6d !important;
                box-shadow: 0 0 25px rgba(255, 42, 109, 0.3) !important;
                animation: fadeInScanner 0.25s ease;
                margin-bottom: 0 !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            body.mobile-scanner-view-mode #qrScannerPanel .card-panel-body {
                flex: 1 !important;
                height: 100% !important;
                width: 100% !important;
                max-width: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                padding: 8px !important;
                gap: 8px !important;
                overflow: hidden !important;
                box-sizing: border-box !important;
            }

            body.mobile-scanner-view-mode #qrScannerPanel .scanner-wrapper {
                flex: 1 !important;
                height: 100% !important;
                width: 100% !important;
                max-width: 100% !important;
                min-height: 0 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 12px !important;
                border-color: #ff2a6d !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            body.mobile-scanner-view-mode #qrReader {
                width: 100% !important;
                height: 100% !important;
                max-width: 100% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                overflow: hidden !important;
                box-sizing: border-box !important;
            }

            body.mobile-scanner-view-mode #qrReader video {
                width: 100% !important;
                height: 100% !important;
                max-width: 100% !important;
                max-height: 100% !important;
                object-fit: cover !important;
                border-radius: 10px !important;
            }

            body.mobile-scanner-view-mode .kpi-grid,
            body.mobile-scanner-view-mode #mobileHomeDashboard,
            body.mobile-scanner-view-mode #recordsListCardPanel,
            body.mobile-scanner-view-mode #detailPanel:not(.drawer-open) {
                display: none !important;
            }
        }

        /* =======================================================
           COMPREHENSIVE SMALL DEVICES & MOBILE RESPONSIVENESS
           ======================================================= */
        .drawer-close-btn {
            display: none;
            background: none;
            border: none;
            color: #aaa;
            cursor: pointer;
        }

        @media (max-width: 900px) {
            .header-actions .btn-walk-in {
                display: none !important;
            }

            .drawer-close-btn {
                display: flex !important;
                align-items: center;
                justify-content: center;
                background: rgba(255, 255, 255, 0.08);
                border: 1px solid #444;
                color: #fff;
                width: 30px;
                height: 30px;
                border-radius: 50%;
                font-size: 13px;
                cursor: pointer;
                transition: all 0.2s;
                padding: 0;
            }
            .drawer-close-btn:hover {
                background: rgba(255, 255, 255, 0.18);
                border-color: #ffd700;
            }
            .drawer-close-btn .back-icon-img,
            .picker-back-btn .back-icon-img {
                width: 14px;
                height: 14px;
                object-fit: contain;
                display: block;
                pointer-events: none;
                transition: transform 0.2s ease;
            }
            .drawer-close-btn:hover .back-icon-img,
            .picker-back-btn:hover .back-icon-img {
                transform: translateX(-2px);
            }

            /* Compact Reservation Record boxes on mobile */
            .res-card-item {
                padding: 11px 13px !important;
                border-radius: 12px !important;
                min-height: 60px !important;
                gap: 9px !important;
                align-items: center !important;
            }

            .res-item-left {
                gap: 8px !important;
                align-items: center !important;
            }

            .res-item-thumb {
                width: 36px !important;
                height: 36px !important;
                border-radius: 6px !important;
                flex-shrink: 0 !important;
            }

            .res-item-details {
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                gap: 1px !important;
            }

            .res-item-code {
                font-size: 11.5px !important;
                line-height: 1.2 !important;
                display: flex !important;
                align-items: center !important;
                gap: 4px !important;
            }

            .res-walkin-badge {
                font-size: 8.5px !important;
                padding: 1px 5px !important;
                border-radius: 3px !important;
                line-height: 1 !important;
            }

            .res-item-name {
                font-size: 11px !important;
                color: #e0e0e0 !important;
                line-height: 1.2 !important;
                margin-top: 0 !important;
            }

            .res-item-dates {
                font-size: 10px !important;
                color: #888 !important;
                line-height: 1.2 !important;
                margin-top: 0 !important;
            }

            /* Hide verbose size preview on mobile card list so the box remains small & slim */
            .res-item-size-preview {
                display: none !important;
            }

            .res-item-right {
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-end !important;
                justify-content: center !important;
                gap: 2px !important;
                flex-shrink: 0 !important;
            }

            .res-item-price {
                font-size: 12px !important;
                line-height: 1.2 !important;
                font-weight: 700 !important;
            }

            .res-status-pill {
                font-size: 8.5px !important;
                padding: 2px 6px !important;
                border-radius: 12px !important;
                margin-top: 0 !important;
                line-height: 1.1 !important;
            }
        }

        @media (max-width: 600px) {
            html, body {
                overflow-x: hidden;
            }

            .staff-header {
                padding: 8px 12px;
            }

            .brand-logo {
                width: 32px;
                height: 32px;
            }

            .brand-title {
                font-size: 15px;
            }

            .header-actions {
                gap: 6px;
            }

            .header-actions .btn-walk-in {
                display: none !important;
            }

            .staff-container {
                padding: 10px 10px 85px 10px !important;
            }

            /* Responsive Slide Panel for Walk-In and Profile on Mobile */
            .slide-panel,
            #walkInModal,
            #staffProfileModalOverlay {
                display: flex !important;
                flex-direction: column !important;
                position: fixed !important;
                top: 0 !important;
                bottom: 0 !important;
                left: auto !important;
                right: -100vw !important;
                width: 100vw !important;
                max-width: 100vw !important;
                height: 100% !important;
                max-height: 100dvh !important;
                transform: none !important;
                border-radius: 0 !important;
                border-left: none !important;
                transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1) !important;
                animation: none !important;
                box-shadow: -5px 0 35px rgba(0, 0, 0, 0.8) !important;
            }

            .slide-panel.slide-panel-open,
            #walkInModal.slide-panel-open,
            #staffProfileModalOverlay.slide-panel-open {
                display: flex !important;
                right: 0 !important;
                left: auto !important;
                top: 0 !important;
                transform: none !important;
            }

            .slide-panel-header {
                padding: 12px 14px;
            }

            .slide-panel-body {
                padding: 14px 14px calc(110px + env(safe-area-inset-bottom, 20px)) 14px !important;
            }

            .walkin-submit-row {
                margin-top: 16px !important;
                padding-bottom: 36px !important;
                display: flex !important;
                gap: 10px !important;
                width: 100% !important;
            }

            .walkin-submit-row button {
                flex: 1 !important;
                padding: 12px 10px !important;
                justify-content: center !important;
                text-align: center !important;
            }

            /* Small device adjustments for drawer */
            #detailPanel {
                max-height: 85vh;
                padding-bottom: max(24px, env(safe-area-inset-bottom, 24px));
            }

            .card-panel-header {
                padding: 10px 14px;
            }

            .card-panel-body {
                padding: 12px;
                gap: 10px;
            }

            /* Calendar Modal Responsive */
            .calendar-modal {
                width: 95%;
                max-width: 340px;
                padding: 12px;
            }

            .calendar-modal .calendar-day {
                min-height: 32px;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 420px) {
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 6px !important;
            }

            .kpi-card {
                padding: 14px 12px !important;
                min-height: 82px !important;
            }

            .kpi-info {
                min-height: 52px !important;
            }

            .kpi-info h4 {
                font-size: 10.5px !important;
            }

            .kpi-info .kpi-num {
                font-size: 22px !important;
            }

            .tab-chip {
                padding: 5px 10px;
                font-size: 10px;
                min-height: 30px;
            }

            .res-action-buttons {
                grid-template-columns: 1fr;
            }

            .res-prod-img {
                width: 44px;
                height: 44px;
            }

            .walkin-responsive-grid {
                grid-template-columns: 1fr !important;
            }
        }

        @media (max-width: 340px) {
            .mobile-bottom-nav {
                padding: 0 2px;
            }

            .nav-action-item .nav-icon {
                font-size: 15px;
            }

            .nav-action-item .nav-label {
                font-size: 8.5px;
            }

            .kpi-card {
                min-height: 78px !important;
                padding: 12px 10px !important;
            }

            .kpi-info .kpi-num {
                font-size: 18px !important;
            }
        }

        /* ── Staff Profile Slide-In Panel (Matching Walk-In Slide Panel Structure) ── */
        .profile-slide-panel {
            position: fixed;
            top: 0;
            right: -650px;
            width: 520px;
            max-width: 95vw;
            height: 100vh;
            background: linear-gradient(165deg, #1d1e26 0%, #15161c 60%, #101115 100%);
            box-shadow: -8px 0 45px rgba(0, 0, 0, 0.85);
            z-index: 100000;
            transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            border-left: 1.5px solid rgba(255, 215, 0, 0.35);
            color: #fff;
            display: flex;
            flex-direction: column;
            -webkit-overflow-scrolling: touch;
        }

        .profile-slide-panel.slide-panel-open {
            right: 0 !important;
        }

        .profile-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            z-index: 99999;
            display: none;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .profile-backdrop.active {
            display: block !important;
        }

        /* Modal Header Bar */
        .staff-profile-top-bar {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255, 215, 0, 0.25);
            background: #1e1e24;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .staff-profile-modal-title {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--accent-gold);
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        /* Modal Scrollable Body */
        .staff-profile-content {
            padding: 18px 20px calc(80px + env(safe-area-inset-bottom, 20px)) 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
            -webkit-overflow-scrolling: touch;
        }

        /* Hero Banner & Avatar Section (Spacious & Highly Visible) */
        .staff-hero-card {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.15) 0%, rgba(32, 34, 44, 0.9) 100%);
            border: 1.5px solid rgba(255, 215, 0, 0.35);
            border-radius: 18px;
            padding: 22px 20px;
            min-height: 125px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .staff-hero-card::after {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 140px;
            height: 140px;
            background: radial-gradient(circle, rgba(255, 215, 0, 0.22) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .staff-avatar-wrapper {
            position: relative;
            flex-shrink: 0;
        }

        .staff-avatar-ring {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffd700, #b8960c);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #111;
            font-size: 26px;
            font-weight: 900;
            box-shadow: 0 8px 22px rgba(255, 215, 0, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.4);
            border: 3px solid #1a1b22;
        }

        .staff-avatar-badge {
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: #10b981;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 2.5px solid #1a1b22;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 10px;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.8);
        }

        .staff-hero-info {
            flex: 1;
            min-width: 0;
        }

        .staff-hero-name {
            font-size: 18px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.25;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .staff-hero-email {
            font-size: 12.5px;
            color: #ccc;
            margin-top: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .staff-hero-chips {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .staff-role-badge {
            background: linear-gradient(135deg, #ffd700, #e5a900);
            color: #111;
            font-weight: 800;
            font-size: 10.5px;
            padding: 3.5px 9px;
            border-radius: 12px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .staff-status-badge {
            background: rgba(16, 185, 129, 0.18);
            border: 1px solid rgba(16, 185, 129, 0.45);
            color: #34d399;
            font-size: 10.5px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* 3-Column Metrics Stats Grid */
        .staff-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .staff-stat-box {
            background: rgba(28, 30, 38, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 12px 10px;
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
        }

        .staff-stat-lbl {
            font-size: 10px;
            color: #888;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .staff-stat-val {
            font-size: 13.5px;
            font-weight: 800;
            color: #ffd700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Detailed Account Information List */
        .staff-info-card {
            background: rgba(22, 23, 30, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 11px;
        }

        .staff-info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding-bottom: 9px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 12.5px;
        }

        .staff-info-row:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .staff-info-key {
            color: #999;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            flex-shrink: 0;
        }

        .staff-info-key i {
            width: 14px;
            color: var(--accent-gold);
            font-size: 12px;
        }

        .staff-info-val {
            color: #eee;
            font-weight: 600;
            text-align: right;
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 60%;
        }

        /* Quick Action Buttons Grid */
        .staff-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .staff-act-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
            border: none;
            min-height: 44px;
        }

        .staff-act-btn-gold {
            background: linear-gradient(135deg, #ffd700, #e5a900);
            color: #111;
            box-shadow: 0 4px 14px rgba(255, 215, 0, 0.35);
        }

        .staff-act-btn-gold:hover {
            background: linear-gradient(135deg, #ffe033, #f5b700);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(255, 215, 0, 0.45);
        }

        .staff-act-btn-dark {
            background: #242632;
            border: 1px solid #3d4052;
            color: #fff;
        }

        .staff-act-btn-dark:hover {
            background: #2e3040;
            border-color: #ffd700;
            transform: translateY(-1px);
        }

        .staff-logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px;
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1.5px solid rgba(239, 68, 68, 0.35);
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            min-height: 44px;
        }

        .staff-logout-btn:hover {
            background: rgba(239, 68, 68, 0.22);
            border-color: #ef4444;
            color: #ff6b6b;
            box-shadow: 0 0 16px rgba(239, 68, 68, 0.35);
            transform: translateY(-1px);
        }

        /* ── Comprehensive Mobile Optimization for Profile Slide Panel ── */
        @media (max-width: 900px) {
            .profile-slide-panel {
                width: 100vw !important;
                max-width: 100vw !important;
                right: -100vw;
                height: 100% !important;
                max-height: 100dvh !important;
                border-radius: 0 !important;
                border-left: none !important;
            }

            .staff-profile-content {
                padding: 16px 16px calc(110px + env(safe-area-inset-bottom, 20px)) 16px !important;
                gap: 16px !important;
            }

            /* Extra Height and Visibility for Hero Card on Mobile */
            .staff-hero-card {
                padding: 22px 18px !important;
                min-height: 135px !important;
                gap: 16px !important;
                border-radius: 18px !important;
            }

            .staff-avatar-ring {
                width: 68px !important;
                height: 68px !important;
                font-size: 26px !important;
            }

            .staff-avatar-badge {
                width: 22px !important;
                height: 22px !important;
                font-size: 10px !important;
            }

            .staff-hero-name {
                font-size: 18px !important;
                line-height: 1.3 !important;
            }

            .staff-hero-email {
                font-size: 12.5px !important;
                margin-top: 4px !important;
            }

            .staff-hero-chips {
                margin-top: 10px !important;
                gap: 8px !important;
            }

            .staff-role-badge {
                font-size: 11px !important;
                padding: 4px 10px !important;
            }

            .staff-status-badge {
                font-size: 11px !important;
                padding: 3.5px 9px !important;
            }
        }

        @media (max-width: 480px) {
            .staff-hero-card {
                padding: 20px 16px !important;
                min-height: 130px !important;
                gap: 14px !important;
            }

            .staff-avatar-ring {
                width: 64px !important;
                height: 64px !important;
                font-size: 24px !important;
            }

            .staff-hero-name {
                font-size: 17px !important;
            }

            .staff-hero-email {
                font-size: 12px !important;
            }

            .staff-stat-box {
                padding: 10px 8px;
            }

            .staff-stat-val {
                font-size: 12.5px;
            }

            .staff-info-card {
                padding: 12px 14px;
            }

            .staff-info-row {
                font-size: 11.5px;
            }

            .staff-actions-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }

        /* Walk-In Visual Product Picker Modal */
        .product-picker-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.88);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 100005;
            align-items: center;
            justify-content: center;
            padding: 16px;
            box-sizing: border-box;
        }

        .product-picker-modal-overlay.active {
            display: flex;
        }

        .product-picker-modal {
            background: #14161b;
            border: 1.5px solid #7c6300;
            border-radius: 14px;
            width: 100%;
            max-width: 960px;
            max-height: 88vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.9), 0 0 30px rgba(255, 215, 0, 0.15);
            overflow: hidden;
            animation: pickerZoomIn 0.22s ease-out;
        }

        @keyframes pickerZoomIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }

        .product-picker-header {
            padding: 16px 20px 12px 20px;
            border-bottom: 1px solid #282a32;
            background: rgba(255, 255, 255, 0.02);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .picker-back-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 215, 0, 0.35);
            color: #ffd700;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
            padding: 0;
        }

        .picker-back-btn:hover, .picker-back-btn:active {
            background: rgba(255, 215, 0, 0.22);
            border-color: #ffd700;
            color: #fff;
            transform: scale(1.05);
        }

        .close-picker-btn {
            background: none;
            border: none;
            color: #aaa;
            font-size: 28px;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            transition: color 0.2s;
        }

        .close-picker-btn:hover {
            color: #ffd700;
        }

        .picker-search-bar {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .picker-search-input-wrap {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
        }

        .picker-search-input-wrap i {
            position: absolute;
            left: 12px;
            color: #ffd700;
            font-size: 0.85rem;
            pointer-events: none;
        }

        .picker-search-input-wrap input {
            width: 100%;
            box-sizing: border-box;
            background: #1c1e25;
            border: 1px solid #383b45;
            border-radius: 8px;
            color: #fff;
            padding: 9px 34px 9px 34px;
            font-size: 0.85rem;
            transition: border-color 0.2s;
        }

        .picker-search-input-wrap input:focus {
            border-color: #ffd700;
            outline: none;
        }

        .picker-search-input-wrap button {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #888;
            font-size: 16px;
            cursor: pointer;
        }

        .picker-category-filter select {
            background: #1c1e25;
            border: 1px solid #383b45;
            border-radius: 8px;
            color: #ffd700;
            font-weight: 600;
            padding: 9px 12px;
            font-size: 0.85rem;
            cursor: pointer;
            outline: none;
        }

        .picker-category-pills {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge */
            padding: 2px 2px 4px 2px;
        }

        .picker-category-pills::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
            width: 0;
            height: 0;
        }

        .picker-pill {
            background: #1c1e25;
            border: 1px solid #383b45;
            color: #bbb;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .picker-pill:hover {
            border-color: #ffd700;
            color: #fff;
        }

        .picker-pill.active {
            background: #7c6300;
            color: #ffd700;
            border-color: #ffd700;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.2);
        }

        .product-picker-body {
            flex: 1;
            overflow-y: auto;
            padding: 14px 20px 20px 20px;
            min-height: 280px;
        }

        .picker-category-header {
            display: flex;
            align-items: center;
            margin: 18px 0 12px 0;
            width: 100%;
        }

        .picker-category-header:first-child {
            margin-top: 4px;
        }

        .picker-category-title {
            color: #d4af37;
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0;
            margin-right: 14px;
            white-space: nowrap;
            text-shadow: 0 0 8px rgba(212, 175, 55, 0.3);
        }

        .picker-category-line {
            flex: 1;
            height: 1.5px;
            background: linear-gradient(90deg, #7c6300 0%, rgba(124, 99, 0, 0.3) 50%, transparent 100%);
            position: relative;
        }

        .picker-category-line::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 7px;
            height: 7px;
            background: #d4af37;
            border-radius: 50%;
        }

        .picker-gowns-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 14px;
            margin-bottom: 16px;
        }

        .picker-gown-card {
            background: #181920;
            border: 1px solid #2e313d;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .picker-gown-card:hover {
            border-color: #ffd700;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5), 0 0 15px rgba(255, 215, 0, 0.15);
        }

        .picker-gown-card.selected {
            border-color: #ffd700;
            box-shadow: 0 0 0 2px #ffd700, 0 0 20px rgba(255, 215, 0, 0.3);
            background: rgba(255, 215, 0, 0.05);
        }

        .picker-gown-card.out-of-stock {
            opacity: 0.5;
            cursor: not-allowed;
            filter: grayscale(0.7);
        }

        .picker-gown-card.out-of-stock:hover {
            transform: none;
            border-color: #f44336;
            box-shadow: none;
        }

        .picker-gown-img-wrap {
            width: 100%;
            height: 155px;
            background: #0d0e12;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .picker-gown-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .picker-gown-card:hover .picker-gown-img-wrap img {
            transform: scale(1.05);
        }

        .picker-discount-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: #f44336;
            color: #fff;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            z-index: 2;
        }

        .picker-stock-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            backdrop-filter: blur(4px);
            z-index: 2;
        }

        .picker-stock-badge.instock {
            background: rgba(40, 167, 69, 0.9);
            color: #fff;
        }

        .picker-stock-badge.lowstock {
            background: rgba(255, 152, 0, 0.9);
            color: #fff;
        }

        .picker-stock-badge.outofstock {
            background: rgba(244, 67, 54, 0.9);
            color: #fff;
        }

        .picker-gown-info {
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }

        .picker-gown-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .picker-gown-category-tag {
            font-size: 0.7rem;
            color: #888;
        }

        .picker-gown-price-row {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-top: 4px;
        }

        .picker-gown-price {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffd700;
        }

        .picker-gown-price-orig {
            font-size: 0.75rem;
            color: #777;
            text-decoration: line-through;
        }

        .picker-gown-select-btn {
            margin-top: 8px;
            width: 100%;
            padding: 6px;
            border-radius: 6px;
            background: #252833;
            border: 1px solid #3c404e;
            color: #ffd700;
            font-size: 0.76rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .picker-gown-card:hover .picker-gown-select-btn {
            background: #ffd700;
            color: #111;
            border-color: #ffd700;
        }

        .picker-gown-card.selected .picker-gown-select-btn {
            background: #28a745;
            color: #fff;
            border-color: #28a745;
        }

        .product-picker-footer {
            padding: 12px 20px;
            border-top: 1px solid #282a32;
            background: rgba(255, 255, 255, 0.02);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .picker-cancel-btn {
            padding: 8px 18px;
            border-radius: 6px;
            background: #262830;
            border: 1px solid #383b45;
            color: #ccc;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .picker-cancel-btn:hover {
            background: #333;
            color: #fff;
        }

        @media (max-width: 900px) {
            .product-picker-modal-overlay {
                padding: 0 !important;
                inset: 0 !important;
                width: 100vw !important;
                max-width: 100vw !important;
                height: 100% !important;
                max-height: 100dvh !important;
                z-index: 10000005 !important;
            }

            .product-picker-modal {
                width: 100vw !important;
                max-width: 100vw !important;
                height: 100% !important;
                max-height: 100dvh !important;
                min-height: 100dvh !important;
                border: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                animation: none !important;
            }

            .product-picker-header {
                padding: calc(12px + env(safe-area-inset-top, 0px)) 14px 10px 14px !important;
                border-radius: 0 !important;
                background: #181a20 !important;
                border-bottom: 1px solid #282a32 !important;
            }

            .product-picker-body {
                padding: 10px 12px calc(24px + env(safe-area-inset-bottom, 0px)) 12px !important;
                scrollbar-width: none !important;
                -ms-overflow-style: none !important;
            }

            .product-picker-body::-webkit-scrollbar {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
            }

            /* No bar: hide footer bar and hide bottom nav bar on mobile */
            .product-picker-footer {
                display: none !important;
            }

            body:has(#walkInProductPickerModal.active) #mobileBottomNav,
            #walkInProductPickerModal.active ~ #mobileBottomNav {
                display: none !important;
            }

            .picker-gowns-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 10px;
            }
            .picker-gown-img-wrap {
                height: 120px;
            }
        }
    </style>
</head>
<body>

    <!-- Staff Header -->
    <header class="staff-header">
        <a href="index.php" class="brand-section">
            <img src="../images/icon/JMB.png" alt="ClosetGo" class="brand-logo" onerror="this.src='../images/icon/chat.png'">
            <div>
                <div class="brand-title">ClosetGo</div>
                <div style="font-size: 11px; color: #888;">Staff Reservation Portal</div>
            </div>
            <span class="brand-badge">STAFF</span>
        </a>
        <div class="header-actions">
            <button type="button" class="nav-btn btn-header-qr-scanner" onclick="openDesktopScannerModal()" title="Open QR Code Scanner">
                <span class="btn-label">QR Scanner</span>
            </button>
            <button type="button" class="nav-btn btn-walk-in" onclick="openWalkInModal()" title="Walk-In Reservation">
                <span class="btn-label">Walk-In</span>
            </button>
            <button type="button" class="nav-btn staff-header-profile-btn" onclick="openStaffProfileModal()" title="View Staff Profile">
                <div class="staff-header-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <span class="btn-label staff-header-name"><?php echo htmlspecialchars($staffName); ?></span>
                <span class="staff-online-dot"></span>
            </button>
        </div>
    </header>

    <div class="staff-container">
        <!-- KPI Summary Cards (One per box, responsive) -->
        <div class="kpi-grid">
            <div class="kpi-card" id="kpiCard-pending" onclick="filterByStatus('pending', this)">
                <div class="kpi-info">
                    <h4>Pending</h4>
                    <div class="kpi-num" id="kpiPending">0</div>
                </div>
            </div>
            <div class="kpi-card" id="kpiCard-confirmed" onclick="filterByStatus('confirmed', this)">
                <div class="kpi-info">
                    <h4>Confirmed</h4>
                    <div class="kpi-num" id="kpiConfirmed">0</div>
                </div>
            </div>
            <div class="kpi-card" id="kpiCard-completed" onclick="filterByStatus('completed', this)">
                <div class="kpi-info">
                    <h4>Completed</h4>
                    <div class="kpi-num" id="kpiCompleted">0</div>
                </div>
            </div>
            <div class="kpi-card" id="kpiCard-rescheduled" onclick="filterByStatus('rescheduled', this)">
                <div class="kpi-info">
                    <h4>Rescheduled</h4>
                    <div class="kpi-num" id="kpiRescheduled">0</div>
                </div>
            </div>
            <div class="kpi-card" id="kpiCard-walkin" onclick="filterByStatus('walkin', this)">
                <div class="kpi-info">
                    <h4>Walk-In</h4>
                    <div class="kpi-num" id="kpiWalkIn">0</div>
                </div>
            </div>
            <div class="kpi-card" id="kpiCard-cancelled" onclick="filterByStatus('cancelled', this)">
                <div class="kpi-info">
                    <h4>Cancelled</h4>
                    <div class="kpi-num" id="kpiCancelled">0</div>
                </div>
            </div>
            <div class="kpi-card active" id="kpiCard-all" onclick="filterByStatus('all', this)">
                <div class="kpi-info">
                    <h4>Total Orders</h4>
                    <div class="kpi-num" id="kpiTotal">0</div>
                </div>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="workspace-grid">
            <!-- Left Column: Active Reservation Detail Management -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <!-- Desktop Scanner Backdrop Overlay -->
                <div class="scanner-modal-backdrop" id="desktopScannerBackdrop"></div>

                <!-- QR Code Scanner Panel (Modal on Laptop / Fullscreen View on Mobile) -->
                <div class="card-panel" id="qrScannerPanel">
                    <div class="card-panel-header">
                        <div class="card-panel-title">
                            <i class="fas fa-qrcode"></i>
                            QR Code Scanner
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="font-size: 11px; color: #888;" id="scannerStatusLabel">Live Camera</div>
                            <button type="button" class="scanner-modal-close-btn" onclick="closeDesktopScannerModal()" title="Close Scanner">&times;</button>
                        </div>
                    </div>
                    <div class="card-panel-body">
                        <!-- Camera Viewport -->
                        <div class="scanner-wrapper" id="scannerViewport">
                            <div class="scanner-placeholder" id="scannerPlaceholder">
                                <i class="fas fa-camera"></i>
                                <div style="font-size: 13px; color: #ccc; font-weight: 600;">Camera is currently idle</div>
                                <div style="font-size: 11px; color: #888; max-width: 220px;">Tap "Start Scanner" to scan or use "Upload QR" to scan from a photo.</div>
                            </div>
                            <div id="qrReader" style="display:none; width:100%; position:relative;"></div>
                            <!-- QR Aim Overlay (shown while camera scanning) -->
                            <div class="qr-aim-overlay" id="qrAimOverlay">
                                <div class="qr-aim-frame">
                                    <div class="qr-corner tl"></div>
                                    <div class="qr-corner tr"></div>
                                    <div class="qr-corner bl"></div>
                                    <div class="qr-corner br"></div>
                                    <div class="qr-scan-line"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Upload QR Preview Strip -->
                        <div class="qr-upload-preview" id="qrUploadPreview">
                            <img id="qrUploadPreviewImg" src="" alt="QR" class="qr-upload-preview-img">
                            <div class="qr-upload-preview-info">
                                <div class="qr-upload-preview-title"><i class="fas fa-image" style="margin-right:4px;"></i>QR Upload</div>
                                <div class="qr-upload-preview-name" id="qrUploadPreviewName">—</div>
                                <div class="qr-upload-preview-status" id="qrUploadPreviewStatus">
                                    <i class="fas fa-spinner fa-spin"></i> Processing...
                                </div>
                            </div>
                        </div>

                        <!-- Scanner Control Buttons -->
                        <div class="scanner-controls">
                            <button class="btn-scanner-action" id="startScanBtn" onclick="startQRScanner()">
                                <i class="fas fa-play"></i>
                                <span>Start Scanner</span>
                            </button>
                            <button class="btn-scanner-action" id="stopScanBtn" onclick="stopQRScanner()" style="display:none; border-color:#ff4444;">
                                <i class="fas fa-stop"></i>
                                <span>Stop</span>
                            </button>
                            <label class="btn-scanner-action" style="cursor: pointer;" id="uploadQrLabel">
                                <i class="fas fa-image"></i>
                                <span>Upload QR</span>
                                <input type="file" id="qrFileInput" accept="image/*" capture="environment" style="display:none;" onchange="handleQRFileUpload(event)">
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Active Reservation Detail & Management Card -->
                <div class="card-panel" id="detailPanel">
                    <div class="card-panel-header">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button type="button" class="drawer-close-btn" onclick="closeDrawer()" title="Back to list">
                                <img src="../images/icon/back.png" alt="Back" class="back-icon-img" onerror="this.src='images/icon/back.png'">
                            </button>
                            <div class="card-panel-title">
                                <i class="fas fa-info-circle"></i>
                                Reservation Details
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div id="detailActionTag" style="font-size: 11px; color: #ffd700;">Select an order</div>
                            <button type="button" class="detail-modal-close-btn" onclick="closeDrawer()" title="Close Details Modal">&times;</button>
                        </div>
                    </div>
                    <div class="card-panel-body" id="detailPanelBody">
                        <div style="text-align: center; color: #666; padding: 40px 20px; font-size: 13px;">
                            <i class="fas fa-search" style="font-size: 32px; color: #444; margin-bottom: 10px; display: block;"></i>
                            Scan a QR code or type an ID above to view and manage reservation details.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Calendar & Manual Lookup Search & All Reservations List -->
            <div style="display: flex; flex-direction: column; gap: 20px; flex: 1; min-width: 0;">
                <!-- Reservation Calendar (Matches admin/calendar.php) -->
                <div class="card-panel calendar-card-panel" id="staffCalendarPanel">
                    <div class="card-panel-header cal-main-header">
                        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                            <div class="card-panel-title">
                                Reservation Calendar
                            </div>
                            <div class="calendar-nav">
                                <button type="button" class="cal-nav-btn" onclick="calPrevMonth()" title="Previous Month">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button type="button" class="cal-nav-btn cal-today-btn" onclick="calToday()" title="Current Month / Today">
                                    Today
                                </button>
                                <button type="button" class="cal-nav-btn" onclick="calNextMonth()" title="Next Month">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                            <div class="calendar-title" id="calMonthTitle">
                                October 2026
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        </div>
                    </div>

                    <div class="card-panel-body cal-card-body" id="calCardBody">
                        <!-- Legend (Matches admin/calendar.php) -->
                        <div class="legend">
                            <div class="legend-item">
                                <div class="legend-color" style="background: rgba(255, 152, 0, 0.45); border: 1px solid #ff9800;"></div>
                                <span>Pending</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: rgba(33, 150, 243, 0.45); border: 1px solid #2196f3;"></div>
                                <span>Confirmed</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: rgba(255, 215, 0, 0.25); border: 1px solid #ffd700;"></div>
                                <span><i class="fas fa-walking" style="color: #ffd700;"></i> Walk-In</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: rgba(245, 158, 11, 0.25); border: 1px solid #f59e0b;"></div>
                                <span><i class="fas fa-history" style="color: #f59e0b;"></i> Rescheduled</span>
                            </div>
                        </div>

                        <!-- Main Calendar Grid -->
                        <div class="calendar-grid" id="staffCalendarGrid">
                            <!-- Populated by renderStaffCalendar() -->
                        </div>
                    </div>
                </div>

                <!-- Desktop Records Modal Backdrop Overlay -->
                <div class="records-modal-backdrop" id="recordsModalBackdrop"></div>

                <!-- Right Column: Manual Lookup Search & All Reservations List (Modal on Laptop / Embedded in Mobile) -->
                <div class="card-panel" id="recordsListCardPanel" style="flex: 1;">
                    <div class="card-panel-header">
                        <div class="card-panel-title">
                            <i class="fas fa-list-alt"></i>
                            <span id="recordsPanelTitleText">Reservation Records</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="font-size: 11px; color: #888;" id="recordCountLabel">0 records</div>
                            <button type="button" class="records-modal-close-btn" onclick="closeRecordsModal()" title="Close Records Modal">&times;</button>
                        </div>
                    </div>
                    <div class="card-panel-body">
                        <!-- Search Bar -->
                        <div class="search-box-row">
                            <div class="search-input-wrapper">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="searchInput" class="search-input" placeholder="Search code, customer, gown, or walk-in..." oninput="handleRealtimeSearch(this.value)" onkeydown="if(event.key==='Enter') executeSearch()">
                                <button type="button" id="clearSearchBtn" class="btn-clear-search" onclick="clearSearch()" title="Clear search">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            </div>
                            <button class="btn-search" onclick="executeSearch()">
                                <i class="fas fa-search"></i>
                                Search
                            </button>
                        </div>

                        <!-- Filter Tabs -->
                        <div class="filter-tabs-row">
                            <div class="tab-chip" id="tabChip-pending" onclick="filterByStatus('pending', this)">Pending</div>
                            <div class="tab-chip" id="tabChip-confirmed" onclick="filterByStatus('confirmed', this)">Confirmed</div>
                            <div class="tab-chip" id="tabChip-completed" onclick="filterByStatus('completed', this)">Completed</div>
                            <div class="tab-chip" id="tabChip-rescheduled" onclick="filterByStatus('rescheduled', this)">Rescheduled</div>
                            <div class="tab-chip" id="tabChip-walkin" onclick="filterByStatus('walkin', this)"><i class="fas fa-walking" style="margin-right:4px;"></i> Walk-In</div>
                            <div class="tab-chip" id="tabChip-cancelled" onclick="filterByStatus('cancelled', this)">Cancelled</div>
                            <div class="tab-chip active" id="tabChip-all" onclick="filterByStatus('all', this)">All</div>
                        </div>

                        <!-- Date Filter Active Banner -->
                        <div id="calDateFilterBanner" class="cal-date-filter-banner" style="display: none; margin-bottom: 12px;">
                            <span><i class="fas fa-calendar-day" style="color:#ffd700; margin-right:6px;"></i> Filtered by date: <strong id="calDateFilterText" style="color:#ffd700;"></strong></span>
                            <button type="button" class="cal-clear-filter-btn" onclick="clearDateFilter()"><i class="fas fa-times"></i> Clear Date</button>
                        </div>

                        <!-- Scrollable Reservations List -->
                        <div class="res-list-container" id="reservationsList">
                            <div style="text-align: center; color: #666; padding: 40px 0; font-size: 13px;">Loading reservations...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Viewer Modal -->
    <div class="modal-overlay" id="qrModal">
        <div class="modal-card" style="position: relative;" onclick="event.stopPropagation()">
            <button type="button" class="close-panel-btn" onclick="closeQRModal()" aria-label="Close" title="Close" style="position: absolute; top: 12px; right: 12px;"><i class="fas fa-times"></i></button>
            <div style="color: #ffd700; font-size: 16px; font-weight: 700; padding-right: 24px;">Reservation QR Code</div>
            <div id="modalQRImageContainer" style="background:#fff; padding:12px; border-radius:12px; display:inline-block;"></div>
            <div id="modalQRText" style="font-size: 16px; font-weight: 800; color: #ffd700; letter-spacing: 1px;"></div>
            <div style="font-size: 12px; color: #aaa;">Scan this code with the staff scanner to confirm or manage order.</div>
            <button class="btn-scanner-action" style="margin-top:8px; width:100%; justify-content:center;" onclick="closeQRModal()">Close</button>
        </div>
    </div>

    <!-- Walk-In Modal Backdrop -->
    <div class="slide-panel-backdrop" id="walkInModalBackdrop"></div>

    <!-- Walk-In Reservation Slide Panel (Shop Customer) -->
    <div id="walkInModal" class="slide-panel">
        <div class="slide-panel-content">
            <div class="slide-panel-header" style="border-bottom: 1px solid rgba(255, 215, 0, 0.3); display: flex; align-items: center; justify-content: space-between;">
                <h2 style="color: #ffd700; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; margin: 0;">
                    <i class="fas fa-walking"></i> Walk-In
                </h2>
                <button type="button" class="close-panel-btn" onclick="closeWalkInModal()" aria-label="Close" title="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="slide-panel-body" style="padding: 20px; color: #fff;">
                <form id="walkInForm" onsubmit="submitWalkInReservation(event)">
                    <!-- Customer Information -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid #333; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                        <div style="font-size: 0.78rem; text-transform: uppercase; color: #ffd700; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-user"></i> Customer Information
                        </div>
                        <div class="walkin-responsive-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">First Name</label>
                                <input type="text" id="walkInFirstName" name="first_name" required placeholder="e.g. Alfred" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 10px; font-size: 0.85rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">Last Name</label>
                                <input type="text" id="walkInLastName" name="last_name" required placeholder="e.g. Vergara" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 10px; font-size: 0.85rem;">
                            </div>
                        </div>
                        <div class="walkin-responsive-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">Phone Number</label>
                                <input type="tel" id="walkInPhone" name="phone" required maxlength="11" pattern="09[0-9]{9}" inputmode="numeric" placeholder="09xxxxxxxxx" value="09" onfocus="if(!this.value) this.value='09';" oninput="formatWalkInPhone(this)" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 10px; font-size: 0.85rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">Email (Optional)</label>
                                <input type="email" id="walkInEmail" name="email" placeholder="vergara@email.com" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 10px; font-size: 0.85rem;">
                            </div>
                        </div>
                    </div>

                    <!-- Product & Booking Details -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid #333; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                        <div style="font-size: 0.78rem; text-transform: uppercase; color: #ffd700; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-tshirt"></i> Product Selection & Rental Details
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 5px;">
                                Product <span style="color: #ffd700;">*</span>
                            </label>
                            
                            <!-- Synced select for form validation & submission -->
                            <select id="walkInProductId" name="product_id" required onchange="handleWalkInProductChange(this)" style="display: none;">
                                <option value="">-- Choose Gown / Product --</option>
                                <?php foreach ($allGowns as $g): 
                                    $stock = intval($g['stock'] ?? 0);
                                    $disc = floatval($g['discount'] ?? 0);
                                    $origPrice = floatval($g['price'] ?? 0);
                                    $finalPrice = $disc > 0 ? $origPrice * (1 - $disc / 100) : $origPrice;
                                ?>
                                    <option value="<?php echo $g['id']; ?>" 
                                            data-price="<?php echo $finalPrice; ?>" 
                                            data-orig-price="<?php echo $origPrice; ?>"
                                            data-discount="<?php echo $disc; ?>"
                                            data-category="<?php echo htmlspecialchars($g['category'] ?? ''); ?>" 
                                            data-stock="<?php echo $stock; ?>"
                                            data-color="<?php echo htmlspecialchars($g['color'] ?? ''); ?>"
                                            data-image="<?php echo htmlspecialchars($g['image_path'] ?? $g['image_path2'] ?? $g['image_path3'] ?? ''); ?>"
                                            data-model="<?php echo htmlspecialchars($g['model_path'] ?? ''); ?>"
                                            data-name="<?php echo htmlspecialchars($g['name'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($g['name']); ?> — ₱<?php echo number_format($finalPrice, 2); ?> (Stock: <?php echo $stock; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Custom Visual Trigger (Initial / Empty State) -->
                            <div id="walkInProductSelectTrigger" onclick="openWalkInProductPicker()" style="display: flex; align-items: center; justify-content: space-between; background: #16181d; border: 1.5px dashed #ffd700; border-radius: 8px; padding: 12px 14px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#fff'; this.style.background='#1a1d24';" onmouseout="this.style.borderColor='#ffd700'; this.style.background='#16181d';">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(255, 215, 0, 0.12); border: 1px solid rgba(255, 215, 0, 0.35); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: #ffd700;">
                                        <i class="fas fa-tshirt"></i>
                                    </div>
                                </div>
                                <div style="color: #ffd700; font-size: 0.8rem; font-weight: 700; background: rgba(255,215,0,0.15); border: 1px solid rgba(255,215,0,0.4); border-radius: 6px; padding: 6px 12px; display: flex; align-items: center; gap: 5px;">
                                    <span>Browse</span> <i class="fas fa-chevron-right" style="font-size: 0.75rem;"></i>
                                </div>
                            </div>

                            <!-- Rich Selected Product Card Preview (Selected State) -->
                            <div id="walkInSelectedProductCard" style="display: none; background: rgba(255,215,0,0.06); border: 1.5px solid #7c6300; border-radius: 10px; padding: 10px 12px; transition: all 0.2s;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div id="walkInSelectedProductImgWrap" style="width: 58px; height: 58px; border-radius: 8px; overflow: hidden; background: #0d0e12; border: 1px solid #444; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                                        <span style="font-size: 1.5rem;">👗</span>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                            <strong id="walkInSelectedProductName" style="font-size: 0.95rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">--</strong>
                                            <span id="walkInSelectedProductCategory" style="font-size: 0.7rem; background: rgba(255,215,0,0.15); border: 1px solid rgba(255,215,0,0.3); color: #ffd700; border-radius: 4px; padding: 1px 6px;">--</span>
                                            <span id="walkInSelectedProductColor" style="font-size: 0.7rem; color: #bbb;"></span>
                                        </div>
                                        <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 3px;">
                                            <span id="walkInSelectedProductPrice" style="font-size: 1rem; font-weight: 700; color: #ffd700;">₱0</span>
                                            <span id="walkInSelectedProductStock" style="font-size: 0.75rem; color: #6cd488; font-weight: 600;">(Stock: 0)</span>
                                        </div>
                                    </div>
                                    <button type="button" onclick="openWalkInProductPicker()" style="padding: 7px 12px; border-radius: 6px; background: #262830; border: 1px solid #444; color: #ffd700; font-size: 0.8rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 5px; flex-shrink: 0; transition: all 0.2s;" onmouseover="this.style.background='#333'; this.style.borderColor='#ffd700';" onmouseout="this.style.background='#262830'; this.style.borderColor='#444';">
                                        <i class="fas fa-exchange-alt"></i> Change
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="walkin-responsive-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">Type</label>
                                <select id="walkInProductStatus" name="product_status" onchange="handleWalkInTypeChange(this)" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 10px; font-size: 0.85rem;">
                                    <option value="reserve">Rental (Reserve)</option>
                                    <option value="buy">Purchase (Buy)</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">
                                    Quantity <span id="walkInStockLabel" style="font-size: 0.7rem; color: #ffd700; font-weight: normal;">(Max: 1)</span>
                                </label>
                                <div style="display: flex; align-items: center; background: #16181d; border: 1px solid #383b45; border-radius: 6px; overflow: hidden; height: 38px;">
                                    <button type="button" id="walkInQtyMinusBtn" onclick="stepWalkInQuantity(-1)" style="width: 38px; height: 100%; background: #262830; border: none; color: #fff; font-size: 1rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; user-select: none;">
                                        <i class="fas fa-minus" style="font-size: 0.7rem;"></i>
                                    </button>
                                    <input type="number" id="walkInQuantity" name="quantity" min="1" max="1" value="1" readonly onchange="recalcWalkInTotals()" style="width: 100%; height: 100%; box-sizing: border-box; background: transparent; border: none; color: #fff; text-align: center; font-size: 0.95rem; font-weight: 700; outline: none; -moz-appearance: textfield;">
                                    <button type="button" id="walkInQtyPlusBtn" onclick="stepWalkInQuantity(1)" style="width: 38px; height: 100%; background: #262830; border: none; color: #fff; font-size: 1rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; user-select: none;">
                                        <i class="fas fa-plus" style="font-size: 0.7rem;"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Active Rental Notice for Purchase -->
                        <div id="walkInBuyScheduleNotice" style="display: none; background: rgba(255, 170, 0, 0.12); border: 1px solid rgba(255, 170, 0, 0.45); border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; color: #fff; font-size: 0.8rem; line-height: 1.4;">
                            <div style="display: flex; align-items: flex-start; gap: 8px;">
                                <i class="fas fa-info-circle" style="color: #ffaa00; font-size: 1.1rem; margin-top: 2px; flex-shrink: 0;"></i>
                                <div>
                                    <strong style="color: #ffaa00; display: block; margin-bottom: 2px;">Upcoming Rental Active</strong>
                                    <span id="walkInBuyScheduleText" style="color: #ddd;">This product has active rental reservations until <strong id="walkInRentalEndText" style="color: #ff8888;">--</strong>. Purchase pickup is available on or after <strong id="walkInEarliestBuyDateText" style="color: #ffd700;">--</strong>.</span>
                                </div>
                            </div>
                        </div>

                        <div class="walkin-responsive-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;" id="walkInDatesRow">
                            <div>
                                <label id="walkInReserveDateLabel" style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">Reserve Date</label>
                                <div style="position: relative;">
                                    <input type="text" id="walkInReserveDate" name="reserve_date" readonly placeholder="Select date" onclick="openWalkInCalendarModal('walkInReserveDate')" required style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 30px 8px 10px; font-size: 0.85rem; cursor: pointer;">
                                    <i class="far fa-calendar-alt" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #ffd700; pointer-events: none; font-size: 0.85rem;"></i>
                                </div>
                            </div>
                            <div id="walkInReturnDateWrap">
                                <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 4px;">Return Date <span style="font-size: 0.7rem; color: #6cd488;">(+<?php echo $rentalDurationDays; ?> days)</span></label>
                                <div style="position: relative;">
                                    <input type="text" id="walkInReturnDate" name="return_date" readonly placeholder="Select date" onclick="openWalkInCalendarModal('walkInReturnDate')" required style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 30px 8px 10px; font-size: 0.85rem; cursor: pointer;">
                                    <i class="far fa-calendar-check" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #6cd488; pointer-events: none; font-size: 0.85rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sizes / Measurements -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid #333; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                        <div style="font-size: 0.78rem; text-transform: uppercase; color: #ffd700; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
                            <span style="display: flex; align-items: center; gap: 6px;"><i class="fas fa-ruler-combined"></i> Size</span>
                            <span id="walkInSizeCategoryBadge" style="font-size: 0.72rem; color: #a5d8b5; background: rgba(108, 212, 136, 0.15); border: 1px solid rgba(108, 212, 136, 0.3); border-radius: 4px; padding: 2px 7px; text-transform: none;">Category: Standard</span>
                        </div>
                        <div id="walkInSizesFieldsContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px;">
                            <div>
                                <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Bust</label>
                                <input type="text" name="size_1" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Waist</label>
                                <input type="text" name="size_2" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Hips</label>
                                <input type="text" name="size_3" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Height/Other</label>
                                <input type="text" name="size_4" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                            </div>
                        </div>
                    </div>

                    <!-- Payment Information (Based on product_reserve.php) -->
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid #333; border-radius: 10px; padding: 14px; margin-bottom: 20px;">
                        <div style="font-size: 0.78rem; text-transform: uppercase; color: #ffd700; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
                            <span style="display: flex; align-items: center; gap: 6px;"><i class="fas fa-money-bill-wave"></i> Payment Information</span>
                            <span style="font-size: 0.7rem; color: #ffd700; background: rgba(255, 215, 0, 0.12); padding: 2px 8px; border-radius: 12px; border: 1px solid rgba(255, 215, 0, 0.3); font-weight: 600;">Select payment terms</span>
                        </div>

                        <!-- Payment Method -->
                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 5px;">Payment Method</label>
                            <select id="walkInPaymentMethod" name="payment_method" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 8px 10px; font-size: 0.85rem;">
                                <option value="cash">💵 Cash (In Shop)</option>
                                <option value="rcbc_qr">📱 RCBC Bank Scan QR</option>
                            </select>
                        </div>

                        <!-- Payment Option (Full Payment vs First Payment ₱500 Deposit) -->
                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 0.75rem; color: #aaa; margin-bottom: 6px;">Payment Option</label>
                            <input type="hidden" id="walkInPaymentOption" name="payment_option" value="full">

                            <div class="walkin-payment-options-grid" style="display: flex; flex-direction: column; gap: 8px;">
                                <!-- Option 1: Full Payment (100%) -->
                                <div class="walkin-payment-option-item active" id="walkInFullPaymentOption" onclick="selectWalkInPaymentOption('full')" style="display: flex; justify-content: space-between; align-items: center; background: rgba(255, 215, 0, 0.08); border: 1.5px solid #ffd700; border-radius: 10px; padding: 10px 14px; cursor: pointer; transition: all 0.2s ease; user-select: none;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="walkin-custom-radio active" id="walkInRadioFull" style="width: 18px; height: 18px; border-radius: 50%; border: 2px solid #ffd700; background: #ffd700; color: #000; display: flex; align-items: center; justify-content: center; font-size: 0.62rem; flex-shrink: 0;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div>
                                            <strong class="walkin-option-title" id="walkInFullTitle" style="display: block; font-size: 0.84rem; color: #ffd700; font-weight: 700;">Full Payment (100%)</strong>
                                            <p style="margin: 2px 0 0; font-size: 0.72rem; color: #888;">Pay the complete amount today</p>
                                        </div>
                                    </div>
                                    <div class="walkin-option-price" id="walkInFullPriceTag" style="font-size: 0.95rem; font-weight: 700; color: #ffd700;">₱0</div>
                                </div>

                                <!-- Option 2: First Payment (₱500 Deposit) -->
                                <div class="walkin-payment-option-item" id="walkInFirstPaymentOption" onclick="selectWalkInPaymentOption('first_payment')" style="display: flex; justify-content: space-between; align-items: center; background: #222224; border: 1.5px solid #38383f; border-radius: 10px; padding: 10px 14px; cursor: pointer; transition: all 0.2s ease; user-select: none;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="walkin-custom-radio" id="walkInRadioFirst" style="width: 18px; height: 18px; border-radius: 50%; border: 2px solid #555; background: transparent; color: transparent; display: flex; align-items: center; justify-content: center; font-size: 0.62rem; flex-shrink: 0;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div>
                                            <strong class="walkin-option-title" id="walkInFirstTitle" style="display: block; font-size: 0.84rem; color: #fff; font-weight: 600;">First Payment (₱500 Deposit)</strong>
                                            <p id="walkInFirstPaymentDesc" style="margin: 2px 0 0; font-size: 0.72rem; color: #888;">Pay ₱500 now to secure booking, balance due on pickup</p>
                                        </div>
                                    </div>
                                    <div class="walkin-option-price" id="walkInFirstPriceTag" style="font-size: 0.95rem; font-weight: 700; color: #ffd700;">₱500</div>
                                </div>
                            </div>
                        </div>

                        <!-- Price Summary -->
                        <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 0.75rem; color: #888;">Total Amount</div>
                                <div id="walkInTotalPriceDisplay" style="font-size: 1.1rem; font-weight: 700; color: #ffd700;">₱0</div>
                                <input type="hidden" id="walkInTotalPrice" name="total_price" value="0">
                            </div>
                            <div style="text-align: right;">
                                <div id="walkInDueLabel" style="font-size: 0.75rem; color: #888;">Full Payment / Balance</div>
                                <div style="font-size: 0.9rem; font-weight: 600;">
                                    <span id="walkInDownpaymentDisplay" style="color: #6cd488;">₱0</span> / <span id="walkInBalanceDisplay" style="color: #ff9800;">₱0</span>
                                </div>
                                <input type="hidden" id="walkInDownpayment" name="downpayment_amount" value="0">
                                <input type="hidden" id="walkInBalance" name="balance_amount" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="walkin-submit-row" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px;">
                        <button type="button" onclick="closeWalkInModal()" style="padding: 10px 20px; border-radius: 8px; background: #262830; color: #bbb; border: 1px solid #383b45; cursor: pointer; font-size: 0.85rem;">Cancel</button>
                        <button type="submit" id="saveWalkInBtn" style="padding: 10px 24px; border-radius: 8px; background: linear-gradient(135deg, #ffd700, #e5a900); color: #111; font-weight: 800; border: none; cursor: pointer; font-size: 0.88rem; box-shadow: 0 4px 15px rgba(255, 215, 0, 0.35); display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                            <i></i> Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Walk-in Calendar Date Picker Modal -->
    <div id="walkInCalendarModalOverlay" class="calendar-modal-overlay">
        <div class="calendar-modal" onclick="event.stopPropagation()">
            <div class="calendar-modal-header" style="display: flex; align-items: center; justify-content: space-between;">
                <div class="calendar-modal-nav">
                    <button type="button" onclick="changeWalkInCalendarMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                    <div class="calendar-modal-title" id="walkInCalendarMonthYear">Loading...</div>
                    <button type="button" onclick="changeWalkInCalendarMonth(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
                <button type="button" class="close-panel-btn" onclick="closeWalkInCalendarModal()" aria-label="Close" title="Close"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="calendar-grid" id="walkInCalendarGrid">
                <!-- Day headers & days dynamically rendered by JS -->
            </div>
            
            <div class="calendar-modal-legend">
                <div class="calendar-modal-legend-item">
                    <div class="calendar-modal-legend-color" style="background: #ffd700;"></div>
                    <span>Selected</span>
                </div>
                <div class="calendar-modal-legend-item">
                    <div class="calendar-modal-legend-color" style="background: rgba(244, 67, 54, 0.5);"></div>
                    <span>Booked (Unavailable)</span>
                </div>
                <div class="calendar-modal-legend-item">
                    <div class="calendar-modal-legend-color" style="background: #1e1e1e; border: 1px solid #444;"></div>
                    <span>Available</span>
                </div>
            </div>
            
            <div style="margin-top: 14px; text-align: center;">
                <button type="button" onclick="closeWalkInCalendarModal()" style="padding: 8px 24px; background: #262830; color: #fff; border: 1px solid #383b45; border-radius: 6px; cursor: pointer; font-size: 0.85rem;">Close</button>
            </div>
        </div>
    </div>

    <!-- Walk-In Visual Product Picker Modal -->
    <div id="walkInProductPickerModal" class="product-picker-modal-overlay">
        <div class="product-picker-modal" onclick="event.stopPropagation()">
            <!-- Header -->
            <div class="product-picker-header">
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <button type="button" class="picker-back-btn" onclick="closeWalkInProductPicker()" title="Back" aria-label="Back">
                            <img src="../images/icon/back.png" alt="Back" class="back-icon-img" onerror="this.src='images/icon/back.png'">
                        </button>
                        <div>
                            <h3 style="margin: 0; font-size: 1.1rem; color: #ffd700; font-weight: 700;">Product</h3>
                        </div>
                    </div>
                    <button type="button" class="close-panel-btn" onclick="closeWalkInProductPicker()" aria-label="Close" title="Close"><i class="fas fa-times"></i></button>
                </div>
                
                <!-- Search & Category Filters Bar -->
                <div class="picker-search-bar">
                    <div class="picker-search-input-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" id="pickerSearchInput" placeholder="Search product by name or color..." oninput="filterPickerProducts()">
                        <button type="button" id="pickerClearSearchBtn" onclick="clearPickerSearch()" style="display:none;">&times;</button>
                    </div>
                    <div class="picker-category-filter">
                        <select id="pickerCategoryFilter" onchange="setPickerCategory(this.value)">
                            <option value="">All Categories</option>
                            <?php foreach ($allCategoryNames as $cname): ?>
                                <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Category Filter Pills -->
                <div class="picker-category-pills" id="pickerCategoryPills">
                    <button type="button" class="picker-pill active" data-category="" onclick="setPickerCategory('', this)">All</button>
                    <?php foreach ($allCategoryNames as $cname): ?>
                        <button type="button" class="picker-pill" data-category="<?php echo htmlspecialchars($cname); ?>" onclick="setPickerCategory('<?php echo htmlspecialchars(addslashes($cname)); ?>', this)">
                            <?php echo htmlspecialchars($cname); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Body Grid with Categories & Product Cards -->
            <div class="product-picker-body" id="pickerProductGrid">
                <!-- Dynamically rendered via JavaScript -->
            </div>

            <!-- Footer -->
            <div class="product-picker-footer">
                <div style="font-size: 0.8rem; color: #888;">
                    <span id="pickerCountDisplay">Showing all products</span>
                </div>
                <span style="font-size: 0.75rem; color: #666; font-style: italic;">Tip: Click outside to close</span>
            </div>
        </div>
    </div>

    <!-- Modern Mobile Bottom Navigation Bar (Home, Search, Center Pink FAB, Profile, Settings) -->
    <nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="Mobile Navigation">
        <!-- SVG Curved Scoop Notch Background Bar (Matches index.php) -->
        <div class="mobile-nav-bg-container">
            <div class="mobile-nav-wing"></div>
            <svg class="mobile-nav-notch-center" viewBox="0 0 108 62" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M 0,10 
                         L 12,10 
                         C 22,10 26,18 28,26 
                         A 32 32 0 0 0 80,26 
                         C 82,18 86,10 96,10 
                         L 108,10 
                         L 108,62 
                         L 0,62 Z" 
                      fill="#212328" />
            </svg>
            <div class="mobile-nav-wing"></div>
        </div>

        <!-- Home (Left) -->
        <button type="button" class="nav-action-item active" id="navHomeBtn" onclick="navToHome()" title="Home">
            <img src="../images/icon/home.png" alt="Home" class="nav-icon-img">
            <span class="nav-label">Home</span>
        </button>

        <!-- Search (Left-Center) -->
        <button type="button" class="nav-action-item" id="navSearchBtn" onclick="navFocusSearch(true)" title="Search">
            <img src="../images/icon/search.png" alt="Search" class="nav-icon-img">
            <span class="nav-label">Search</span>
        </button>

        <!-- Center Cradle Gap -->
        <div class="nav-center-gap"></div>

        <!-- Center Floating Action Button (Pink Camera Scanner Button) -->
        <div class="nav-fab-wrapper">
            <button type="button" class="nav-fab-scan-btn" id="navScanBtn" onclick="navTriggerScan()" title="Scan Products / QR Scanner">
                <img src="../images/icon/camera.png" alt="Camera Scan" class="nav-fab-icon">
            </button>
        </div>

        <!-- Walk-In (Right-Center) -->
        <button type="button" class="nav-action-item" id="navWalkInBtn" onclick="navToggleWalkIn()" title="Walk-In Reservation">
            <img src="../images/icon/walkin.png" alt="Walk-In" class="nav-icon-img nav-icon-walkin">
            <span class="nav-label">Walk-In</span>
        </button>

        <!-- Profile (Right) -->
        <button type="button" class="nav-action-item" id="navProfileBtn" onclick="openStaffProfileModal()" title="Profile">
            <img src="../images/icon/profile.png" alt="Profile" class="nav-icon-img">
            <span class="nav-label">Profile</span>
        </button>
    </nav>

    <!-- Staff Profile Slide-In Panel (Matching Walk-In Slide Panel Structure) -->
    <div id="staffProfileModalBackdrop" class="slide-panel-backdrop profile-backdrop"></div>
    <div id="staffProfileModalOverlay" class="slide-panel profile-slide-panel">
        <div class="slide-panel-content">
            <!-- Top Title Bar -->
            <div class="slide-panel-header staff-profile-top-bar" style="display: flex; align-items: center; justify-content: space-between;">
                <div class="staff-profile-modal-title">
                    <i class="fas fa-id-badge"></i>
                    <span>Staff Profile & Account</span>
                </div>
                <button type="button" class="close-panel-btn" onclick="closeStaffProfileModal()" aria-label="Close" title="Close"><i class="fas fa-times"></i></button>
            </div>

            <!-- Scrollable Content -->
            <div class="slide-panel-body staff-profile-content">
                <!-- Hero Avatar & Name Card -->
                <div class="staff-hero-card">
                    <div class="staff-avatar-wrapper">
                        <div class="staff-avatar-ring">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                        <div class="staff-avatar-badge" title="Active Staff">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                    <div class="staff-hero-info">
                        <div class="staff-hero-name">
                            <?php echo htmlspecialchars($staffName); ?>
                        </div>
                        <div class="staff-hero-email">
                            <i class="fas fa-envelope" style="color:#888; font-size:11px;"></i>
                            <span><?php echo htmlspecialchars($staffEmail); ?></span>
                        </div>
                        <div class="staff-hero-chips">
                            <span class="staff-role-badge">
                                <i class="fas fa-user-shield"></i> AUTHORIZED STAFF
                            </span>
                            <span class="staff-status-badge">
                                <span class="staff-online-dot" style="width:6px; height:6px;"></span> Active Session
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 3-Column Metrics / Quick Info -->
                <div class="staff-stats-grid">
                    <div class="staff-stat-box">
                        <span class="staff-stat-lbl">Staff ID</span>
                        <span class="staff-stat-val">#<?php echo str_pad($staffId, 3, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="staff-stat-box">
                        <span class="staff-stat-lbl">Portal Access</span>
                        <span class="staff-stat-val" style="color:#38bdf8;">Full Ops</span>
                    </div>
                    <div class="staff-stat-box">
                        <span class="staff-stat-lbl">Security</span>
                        <span class="staff-stat-val" style="color:#4ade80;">Verified</span>
                    </div>
                </div>

                <!-- Detailed Account & System Information -->
                <div class="staff-info-card">
                    <div class="staff-info-row">
                        <div class="staff-info-key"><i class="fas fa-user-tie"></i> Full Name</div>
                        <div class="staff-info-val"><?php echo htmlspecialchars($staffName); ?></div>
                    </div>
                    <div class="staff-info-row">
                        <div class="staff-info-key"><i class="fas fa-envelope-open-text"></i> Work Email</div>
                        <div class="staff-info-val"><?php echo htmlspecialchars($staffEmail); ?></div>
                    </div>
                    <div class="staff-info-row">
                        <div class="staff-info-key"><i class="fas fa-store-alt"></i> Assigned Branch</div>
                        <div class="staff-info-val">ClosetGo Main Boutique</div>
                    </div>
                    <div class="staff-info-row">
                        <div class="staff-info-key"><i class="fas fa-shield-alt"></i> Authentication</div>
                        <div class="staff-info-val" style="color:#4ade80;"><i class="fas fa-lock"></i> 2FA Verified</div>
                    </div>
                    <div class="staff-info-row">
                        <div class="staff-info-key"><i class="fas fa-calendar-alt"></i> Member Since</div>
                        <div class="staff-info-val"><?php echo htmlspecialchars($staffJoinedDate); ?></div>
                    </div>
                </div>

                <!-- Quick Action Shortcuts -->
                <div class="staff-actions-grid">
                    <button type="button" class="staff-act-btn staff-act-btn-gold" onclick="closeStaffProfileModal(); openWalkInModal();">
                        <i class="fas fa-walking"></i> + New Walk-In
                    </button>
                    <button type="button" class="staff-act-btn staff-act-btn-dark" onclick="closeStaffProfileModal(); navTriggerScan();">
                        <i class="fas fa-camera" style="color:#ec4899;"></i> Open Scanner
                    </button>
                </div>

                <!-- Logout Button -->
                <a href="staff_logout.php" class="staff-logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout from Staff Portal
                </a>
            </div>
        </div>
    </div>

    <!-- Cancellation Backdrop & Big Slide Panel (Matches admin/calendar.php) -->
    <div id="cancellationBackdrop" class="cancellation-backdrop"></div>
    <div id="cancellationModal" class="slide-panel">
        <div class="slide-panel-content">
            <div class="slide-panel-header" style="border-bottom: 1px solid rgba(244, 67, 54, 0.3);">
                <h2 style="color: #f44336; display: flex; align-items: center; gap: 8px; margin: 0; font-size: 1.15rem;">
                    <i class="fas fa-times-circle"></i> Cancel Reservation
                </h2>
                <button type="button" class="close-panel-btn" onclick="closeCancellationModal()" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="slide-panel-body" style="padding: 24px; color: #fff;">
                <form id="cancellationForm" onsubmit="submitStaffCancellation(event)">
                    <input type="hidden" id="cancelReservationCode" name="code" value="">
                    <input type="hidden" id="cancelReservationId" name="id" value="">
                    <input type="hidden" name="status" value="cancelled">
                    
                    <div id="cancelReservationSummary" style="background: rgba(255,255,255,0.03); border: 1px solid #333; border-radius: 8px; padding: 12px; margin-bottom: 18px; font-size: 0.85rem;">
                    </div>

                    <!-- Series of Cancellation Reason Choices -->
                    <div style="margin-bottom: 16px;">
                        <label style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; text-transform: uppercase; color: #FFD700; font-weight: 600; margin-bottom: 8px; letter-spacing: 0.5px;">
                            <span><i class="fas fa-list-ul"></i> Cancellation Reason <span style="color: #f44336;">*</span></span>
                            <span style="font-size: 0.7rem; color: #aaa; text-transform: none; font-weight: normal;">(Staff choices)</span>
                        </label>
                        <select id="cancellationReasonSelect" required onchange="handleCancellationReasonChange(this)" class="cancel-reason-select">
                            <option value="">-- Select a reason for cancellation --</option>
                            <option value="Item Damaged or Stained (Requires Repair / Dry Cleaning)">Item Damaged or Stained (Requires Repair / Dry Cleaning)</option>
                            <option value="Item Not Returned on Time by Previous Borrower">Item Not Returned on Time by Previous Borrower</option>
                            <option value="Scheduling Conflict / Double Booking on Selected Dates">Scheduling Conflict / Double Booking on Selected Dates</option>
                            <option value="Payment Unverified / Deposit Deadline Expired">Payment Unverified / Deposit Deadline Expired</option>
                            <option value="Customer Requested Cancellation / Change of Plans">Customer Requested Cancellation / Change of Plans</option>
                            <option value="Measurement Discrepancy or Size Fit Issue">Measurement Discrepancy or Size Fit Issue</option>
                            <option value="Failed Required ID Verification / Contract Incomplete">Failed Required ID Verification / Contract Incomplete</option>
                            <option value="Store Operational Maintenance / Emergency Closure">Store Operational Maintenance / Emergency Closure</option>
                            <option value="Other Reason">Other Reason (Specify detailed explanation below)</option>
                        </select>

                        <!-- Quick Selection Chips for Easy Click -->
                        <div class="cancel-quick-chips">
                            <span class="cancel-quick-chip" data-reason="Item Damaged or Stained (Requires Repair / Dry Cleaning)" onclick="selectCancelChip(this, 'Item Damaged or Stained (Requires Repair / Dry Cleaning)')">Fabric Damage</span>
                            <span class="cancel-quick-chip" data-reason="Item Not Returned on Time by Previous Borrower" onclick="selectCancelChip(this, 'Item Not Returned on Time by Previous Borrower')">Not Returned</span>
                            <span class="cancel-quick-chip" data-reason="Scheduling Conflict / Double Booking on Selected Dates" onclick="selectCancelChip(this, 'Scheduling Conflict / Double Booking on Selected Dates')">Date Conflict</span>
                            <span class="cancel-quick-chip" data-reason="Payment Unverified / Deposit Deadline Expired" onclick="selectCancelChip(this, 'Payment Unverified / Deposit Deadline Expired')">Payment Issue</span>
                            <span class="cancel-quick-chip" data-reason="Customer Requested Cancellation / Change of Plans" onclick="selectCancelChip(this, 'Customer Requested Cancellation / Change of Plans')">Customer Request</span>
                            <span class="cancel-quick-chip" data-reason="Measurement Discrepancy or Size Fit Issue" onclick="selectCancelChip(this, 'Measurement Discrepancy or Size Fit Issue')">Sizing Fit</span>
                            <span class="cancel-quick-chip" data-reason="Failed Required ID Verification / Contract Incomplete" onclick="selectCancelChip(this, 'Failed Required ID Verification / Contract Incomplete')">ID / Policy</span>
                            <span class="cancel-quick-chip" data-reason="Other Reason" onclick="selectCancelChip(this, 'Other Reason')">Other</span>
                        </div>
                    </div>

                    <!-- Additional Details & Explanation for the Customer -->
                    <div style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 0.8rem; text-transform: uppercase; color: #FFD700; font-weight: 600; margin-bottom: 8px; letter-spacing: 0.5px;">
                            <i class="fas fa-comment-alt"></i> Additional Explanation for Customer <span id="notesRequiredStar" style="color: #f44336; display: none;">*</span>
                        </label>
                        <textarea id="cancellationReasonNotes" rows="3" placeholder="Provide extra details or instructions for the customer (optional if standard reason selected)..." style="width: 100%; box-sizing: border-box; background: #18191f; border: 1px solid #383b45; border-radius: 8px; color: #fff; padding: 12px; font-size: 0.88rem; resize: vertical; outline: none; transition: border 0.2s;" onfocus="this.style.borderColor='#FFD700'" onblur="this.style.borderColor='#383b45'"></textarea>
                        <div style="font-size: 0.74rem; color: #888; margin-top: 5px; display: flex; align-items: center; gap: 5px;">
                            <i class="fas fa-info-circle" style="color: #FFD700;"></i>
                            <span>The selected reason and note will be clearly shown to the user on their order status page.</span>
                        </div>
                    </div>

                    <div style="margin-bottom: 24px;">
                        <label style="display: block; font-size: 0.8rem; text-transform: uppercase; color: #FFD700; font-weight: 600; margin-bottom: 8px; letter-spacing: 0.5px;">
                            <i class="fas fa-camera"></i> Attach Images of Problem (Optional)
                        </label>
                        <div style="border: 2px dashed #383b45; border-radius: 8px; padding: 18px; text-align: center; background: #14151a; cursor: pointer; transition: all 0.2s;" onclick="document.getElementById('cancelImagesInput').click()" ondragover="event.preventDefault(); this.style.borderColor='#FFD700';" ondragleave="this.style.borderColor='#383b45';" ondrop="handleCancelImagesDrop(event)">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #888; margin-bottom: 6px; display: block;"></i>
                            <div style="font-size: 0.85rem; color: #bbb; font-weight: 500;">Click or Drag & Drop images here</div>
                            <div style="font-size: 0.75rem; color: #666; margin-top: 4px;">Supports JPG, PNG, WEBP (multiple files allowed)</div>
                            <input type="file" id="cancelImagesInput" multiple accept="image/*" style="display: none;" onchange="handleCancelImagesSelect(this)">
                        </div>
                        <div id="cancelImagesPreview" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px;"></div>
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end; padding-top: 10px; border-top: 1px solid #333;">
                        <button type="button" onclick="closeCancellationModal()" style="padding: 10px 18px; border-radius: 8px; background: #262830; color: #bbb; border: 1px solid #383b45; cursor: pointer; font-size: 0.85rem;">Keep Reservation</button>
                        <button type="submit" id="confirmCancelBtn" style="padding: 10px 20px; border-radius: 8px; background: #f44336; color: #fff; border: none; font-weight: 600; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-times-circle"></i> Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Custom Confirmation Modal for Manage Reservation Status (styled like index.php Clear Chat Modal) -->
    <div id="statusConfirmModal" class="clear-chat-confirm-modal">
        <div class="clear-chat-confirm-card" id="statusConfirmCard" style="position: relative;">
            <button type="button" class="close-panel-btn" onclick="closeStatusConfirmModal()" aria-label="Close" title="Close" style="position: absolute; top: 10px; right: 12px; font-size: 1rem;"><i class="fas fa-times"></i></button>
            <div class="clear-chat-confirm-icon" id="statusConfirmIcon">
                <i class="fas fa-check-circle" id="statusConfirmIconEl"></i>
            </div>
            <div class="clear-chat-confirm-body">
                <div class="clear-chat-confirm-title" id="statusConfirmTitle">Confirm Reservation?</div>
                <div class="clear-chat-confirm-desc" id="statusConfirmDesc">Are you sure you want to mark this reservation as confirmed?</div>
                
                <div id="statusCancelReasonGroup" style="display: none; margin-bottom: 8px;">
                    <input type="text" id="statusCancelReasonInput" placeholder="Cancellation reason (e.g. Customer cancelled)" 
                           style="width: 100%; background: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 6px 10px; color: #fff; font-size: 11px; outline: none; font-family: inherit; box-sizing: border-box;">
                </div>

                <div class="clear-chat-confirm-actions">
                    <button type="button" class="clear-chat-btn-cancel" onclick="closeStatusConfirmModal()">Cancel</button>
                    <button type="button" class="clear-chat-btn-delete" id="statusConfirmActionBtn" onclick="executePendingStatusChange()">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="staff-toast" id="staffToast"></div>

    <script>
        let allReservations = [];
        let currentStatusFilter = 'all';
        let currentSelectedCode = null;
        let html5QrScanner = null;
        let isScannerRunning = false;

        // Calendar State (Matches admin/calendar.php)
        let currentCalYear = new Date().getFullYear();
        let currentCalMonth = new Date().getMonth(); // 0-indexed
        let calProductFilter = 'all';
        let calSelectedDate = null;
        const calMonthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        // Sound Chime on scan
        function playBeep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, ctx.currentTime);
                osc.frequency.setValueAtTime(1320, ctx.currentTime + 0.1);
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.3);
            } catch(e) {}
        }

        function showToast(msg, duration = 3000) {
            const toast = document.getElementById('staffToast');
            toast.innerHTML = `<i class="fas fa-check-circle" style="color:#ffd700;"></i> ${escapeHtml(msg)}`;
            toast.style.display = 'flex';
            setTimeout(() => { toast.style.display = 'none'; }, duration);
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(String(text)));
            return div.innerHTML;
        }

        /* ── Calendar Helper Functions ── */
        function isResOnDate(r, dateStr) {
            if (!r || !r.reserve_date) return false;
            const orderStatus = (r.order_status || 'pending').toLowerCase();
            if (orderStatus === 'cancelled' || orderStatus === 'completed') return false;
            const pStatus = (r.product_status || '').toLowerCase();
            const pMethod = (r.payment_method || '').toLowerCase();
            if (pStatus === 'buy' || pMethod === 'buy') return false;

            const start = String(r.reserve_date).slice(0, 10);
            if (!start || start === '0000-00-00') return false;
            let end = String(r.return_date || r.reserve_date).slice(0, 10);
            if (!end || end === '0000-00-00' || end < start) {
                end = start;
            }
            return dateStr >= start && dateStr <= end;
        }

        function calPrevMonth() {
            currentCalMonth--;
            if (currentCalMonth < 0) {
                currentCalMonth = 11;
                currentCalYear--;
            }
            renderStaffCalendar();
        }

        function calNextMonth() {
            currentCalMonth++;
            if (currentCalMonth > 11) {
                currentCalMonth = 0;
                currentCalYear++;
            }
            renderStaffCalendar();
        }

        function calToday() {
            const now = new Date();
            currentCalYear = now.getFullYear();
            currentCalMonth = now.getMonth();
            renderStaffCalendar();
        }

        function filterCalendarByProduct(val) {
            calProductFilter = val;
            renderStaffCalendar();
        }

        function filterCalendarByDate(dateStr) {
            if (calSelectedDate === dateStr) {
                clearDateFilter();
                return;
            }
            calSelectedDate = dateStr;

            // Highlight cell in grid
            document.querySelectorAll('#staffCalendarGrid .calendar-day').forEach(el => {
                if (el.getAttribute('data-date') === dateStr) {
                    el.classList.add('selected-date');
                } else {
                    el.classList.remove('selected-date');
                }
            });

            // Format date for records panel title
            let formattedDate = dateStr;
            const parts = dateStr.split('-');
            if (parts.length === 3) {
                const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                formattedDate = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            }

            // Show date filter banner if present
            const dateBanner = document.getElementById('calDateFilterBanner');
            const dateText = document.getElementById('calDateFilterText');
            if (dateBanner && dateText) {
                dateText.textContent = formattedDate;
                dateBanner.style.display = 'flex';
            }

            // Filter reservation records list
            const filtered = allReservations.filter(r => isResOnDate(r, dateStr));
            renderReservationList(filtered);

            // On laptop: open records modal to show the reservations for this date
            if (window.innerWidth > 900) {
                openRecordsModal();
                const titleEl = document.getElementById('recordsPanelTitleText');
                if (titleEl) {
                    titleEl.textContent = 'Reservations on ' + formattedDate;
                }
            } else {
                const recPanel = document.getElementById('recordsListCardPanel');
                if (recPanel) {
                    recPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        }

        function clearDateFilter() {
            calSelectedDate = null;
            document.querySelectorAll('#staffCalendarGrid .calendar-day').forEach(el => {
                el.classList.remove('selected-date');
            });

            const dateBanner = document.getElementById('calDateFilterBanner');
            if (dateBanner) dateBanner.style.display = 'none';

            const titleEl = document.getElementById('recordsPanelTitleText');
            if (titleEl) {
                const map = {
                    'all': 'Total Orders',
                    'walkin': 'Walk-In Reservations',
                    'pending': 'Pending Reservations',
                    'confirmed': 'Confirmed Reservations',
                    'completed': 'Completed Reservations',
                    'rescheduled': 'Rescheduled Reservations',
                    'cancelled': 'Cancelled Reservations'
                };
                titleEl.textContent = map[currentStatusFilter] || 'Reservation Records';
            }

            const searchInput = document.getElementById('searchInput');
            const query = searchInput ? searchInput.value.trim() : '';
            if (query) {
                handleRealtimeSearch(query);
            } else {
                renderReservationList(allReservations);
            }
        }

        function openReservationFromCal(codeOrId) {
            const found = allReservations.find(r => 
                r.reservation_code === codeOrId || 
                r.reservation_group_id === codeOrId || 
                String(r.id) === String(codeOrId)
            );
            if (found) {
                selectReservation(found);
            } else {
                fetch(`api_get_reservation.php?code=${encodeURIComponent(codeOrId)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.reservations && data.reservations.length > 0) {
                            selectReservation(data.reservations[0]);
                        }
                    }).catch(e => console.error(e));
            }
        }

        function renderStaffCalendar() {
            const titleEl = document.getElementById('calMonthTitle');
            if (titleEl) {
                titleEl.textContent = `${calMonthNames[currentCalMonth]} ${currentCalYear}`;
            }

            const grid = document.getElementById('staffCalendarGrid');
            if (!grid) return;

            const firstDay = new Date(currentCalYear, currentCalMonth, 1);
            const daysInMonth = new Date(currentCalYear, currentCalMonth + 1, 0).getDate();
            const firstDayOfWeek = firstDay.getDay(); // 0 = Sunday, 6 = Saturday

            const now = new Date();
            const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

            let html = `
                <div class="calendar-day-header"><span class="day-full">Sunday</span><span class="day-short">Sun</span></div>
                <div class="calendar-day-header"><span class="day-full">Monday</span><span class="day-short">Mon</span></div>
                <div class="calendar-day-header"><span class="day-full">Tuesday</span><span class="day-short">Tue</span></div>
                <div class="calendar-day-header"><span class="day-full">Wednesday</span><span class="day-short">Wed</span></div>
                <div class="calendar-day-header"><span class="day-full">Thursday</span><span class="day-short">Thu</span></div>
                <div class="calendar-day-header"><span class="day-full">Friday</span><span class="day-short">Fri</span></div>
                <div class="calendar-day-header"><span class="day-full">Saturday</span><span class="day-short">Sat</span></div>
            `;

            // Empty cells before first day
            for (let i = 0; i < firstDayOfWeek; i++) {
                html += '<div class="calendar-day other-month"></div>';
            }

            // Days of month
            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${currentCalYear}-${String(currentCalMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const isToday = (dateStr === todayStr);
                const isSelected = (calSelectedDate === dateStr);

                let dayRes = allReservations.filter(r => isResOnDate(r, dateStr));

                // Filter by gown/product if selected
                if (calProductFilter !== 'all') {
                    dayRes = dayRes.filter(r => {
                        const prods = r.products || [];
                        return prods.some(p => String(p.id) === String(calProductFilter) || String(p.product_id) === String(calProductFilter));
                    });
                }

                const totalCount = dayRes.length;
                const visibleLimit = 3;
                const hiddenCount = Math.max(0, totalCount - visibleLimit);

                let cellClasses = ['calendar-day'];
                if (isToday) cellClasses.push('today');
                if (isSelected) cellClasses.push('selected-date');

                html += `<div class="${cellClasses.join(' ')}" data-date="${dateStr}" onclick="filterCalendarByDate('${dateStr}')">`;
                html += `  <div class="calendar-day-top">`;
                html += `    <span class="calendar-day-number">${day}</span>`;
                if (totalCount > 0) {
                    html += `    <span class="mobile-res-count">${totalCount}</span>`;
                }
                if (hiddenCount > 0) {
                    html += `    <span class="cal-more-text" onclick="event.stopPropagation(); filterCalendarByDate('${dateStr}')" title="${hiddenCount} more. Click to view all.">+${hiddenCount} more</span>`;
                }
                html += `  </div>`;

                // Render visible pills
                dayRes.forEach((r, idx) => {
                    if (idx >= visibleLimit) return;

                    const st = (r.order_status || 'pending').toLowerCase();
                    const isWalkIn = !!r.is_walkin;
                    const resCode = r.reservation_code || r.reservation_group_id || '#' + r.id;
                    const custName = r.customer_name || 'Customer';

                    let prodName = 'Product';
                    if (r.products && r.products.length > 0) {
                        if (calProductFilter !== 'all') {
                            const match = r.products.find(p => String(p.id) === String(calProductFilter) || String(p.product_id) === String(calProductFilter));
                            prodName = match ? (match.product_name || match.name || 'Product') : (r.products[0].product_name || r.products[0].name || 'Product');
                        } else {
                            prodName = r.products[0].product_name || r.products[0].name || 'Product';
                        }
                    }

                    let pillClass = `reservation-item ${st} ${isWalkIn ? 'walkin-item' : ''}`;
                    let walkInIcon = isWalkIn ? '<i class="fas fa-walking" style="color: #ffd700; font-size: 0.65rem; margin-right: 2px; flex-shrink: 0;" title="Walk-In Customer"></i>' : '';
                    let isRescheduled = (r.is_rescheduled == 1 || (r.products && r.products.some(p => p.is_rescheduled == 1)));
                    let rescheduleIcon = isRescheduled ? '<i class="fas fa-history" style="color: #f59e0b; font-size: 0.65rem; margin-right: 2px; flex-shrink: 0;" title="Rescheduled Reservation"></i>' : '';

                    html += `<div class="${pillClass}" title="${escapeHtml(custName + ' - ' + prodName + ' [' + resCode + ']')}" onclick="event.stopPropagation(); openReservationFromCal('${escapeHtml(resCode)}')">`;
                    html += `  ${walkInIcon}${rescheduleIcon}`;
                    html += `  <span class="res-item-name" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0;">${escapeHtml(prodName)}</span>`;
                    html += `</div>`;
                });

                html += `</div>`;
            }

            // Trailing empty cells
            const totalCells = firstDayOfWeek + daysInMonth;
            const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
            for (let i = 0; i < remainingCells; i++) {
                html += '<div class="calendar-day other-month"></div>';
            }

            grid.innerHTML = html;
        }

        // Fetch reservations list
        function loadReservations(searchQuery = '') {
            const container = document.getElementById('reservationsList');
            if (container) {
                container.innerHTML = '<div style="text-align: center; color: #888; padding: 40px 0; font-size: 13px;"><i class="fas fa-spinner fa-spin" style="margin-right: 6px; color: var(--accent-gold);"></i>Loading reservations...</div>';
            }

            let url = `api_get_reservation.php?status=${encodeURIComponent(currentStatusFilter)}`;
            if (searchQuery) {
                url += `&search=${encodeURIComponent(searchQuery)}`;
            }

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        allReservations = data.reservations || [];
                        allReservations.sort((a, b) => {
                            const dateA = new Date(a.reserved_at || 0).getTime();
                            const dateB = new Date(b.reserved_at || 0).getTime();
                            if (dateB !== dateA) return dateB - dateA;
                            return (b.id || 0) - (a.id || 0);
                        });
                        renderKPIs(data.kpis);

                        // Render Calendar
                        renderStaffCalendar();

                        // Filter list if a specific calendar date is selected
                        let listToRender = allReservations;
                        if (calSelectedDate) {
                            listToRender = listToRender.filter(r => isResOnDate(r, calSelectedDate));
                        }
                        renderReservationList(listToRender);

                        // If we have an active selected code, refresh its detail card
                        if (currentSelectedCode) {
                            const found = allReservations.find(r => r.reservation_code === currentSelectedCode || r.reservation_group_id === currentSelectedCode || String(r.id) === currentSelectedCode);
                            if (found) {
                                renderDetailCard(found); // re-render detail in place without re-opening drawer
                            }
                        }
                    }
                }).catch(err => {
                    console.error(err);
                });
        }

        function renderKPIs(kpis) {
            if (!kpis) return;
            document.getElementById('kpiTotal').textContent = kpis.total || 0;
            const walkinEl = document.getElementById('kpiWalkIn');
            if (walkinEl) walkinEl.textContent = kpis.walkin || 0;
            document.getElementById('kpiPending').textContent = kpis.pending || 0;
            document.getElementById('kpiConfirmed').textContent = kpis.confirmed || 0;
            document.getElementById('kpiCompleted').textContent = kpis.completed || 0;
            const reschedEl = document.getElementById('kpiRescheduled');
            if (reschedEl) reschedEl.textContent = kpis.rescheduled || 0;
            document.getElementById('kpiCancelled').textContent = kpis.cancelled || 0;
        }

        function renderReservationList(list) {
            const container = document.getElementById('reservationsList');
            document.getElementById('recordCountLabel').textContent = `${list.length} records`;

            if (!list || list.length === 0) {
                container.innerHTML = '<div style="text-align:center; color:#666; padding:40px 0; font-size:13px;">No matching reservations found.</div>';
                return;
            }

            container.innerHTML = '';
            list.forEach(res => {
                const isSelected = (currentSelectedCode && (res.reservation_code === currentSelectedCode || res.reservation_group_id === currentSelectedCode || String(res.id) === currentSelectedCode));
                const firstProd = res.products[0] || {};
                let imgSrc = firstProd.image_path || '../images/icon/profile_logo.png';
                if (!imgSrc.startsWith('http') && !imgSrc.startsWith('../') && !imgSrc.startsWith('/')) {
                    imgSrc = '../' + imgSrc;
                }
                const isWalkIn = res.is_walkin;

                const card = document.createElement('div');
                card.className = `res-card-item ${isSelected ? 'selected' : ''} ${isWalkIn ? 'walkin-item' : ''}`;
                card.onclick = () => selectReservation(res);

                const dueAmount = parseFloat(res.balance_amount || 0);
                let duePaidHtml = '';
                if (dueAmount > 0) {
                    duePaidHtml = `<div class="res-item-price" style="color:#f87171; font-weight:700;">Due: ₱${dueAmount.toLocaleString('en-US', {minimumFractionDigits: 2})}</div>`;
                } else {
                    duePaidHtml = `<div class="res-item-price" style="color:#4ade80; font-weight:700;"><i class="fas fa-check-circle" style="font-size:11px; margin-right:3px;"></i>Paid</div>`;
                }

                card.innerHTML = `
                    <div class="res-item-left">
                        <img src="${escapeHtml(imgSrc)}" class="res-item-thumb" onerror="this.src='../images/icon/profile_logo.png'">
                        <div class="res-item-details">
                            <div class="res-item-code" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                ${isWalkIn ? '<span class="res-walkin-badge"><i class="fas fa-walking"></i> Walk-In</span>' : ''}
                                <span>${escapeHtml(res.reservation_code || res.reservation_group_id || '#' + res.id)}</span>
                            </div>
                            <div class="res-item-name">${escapeHtml(res.customer_name || 'Customer')}</div>
                        </div>
                    </div>
                    <div class="res-item-right">
                        ${duePaidHtml}
                    </div>
                `;
                container.appendChild(card);
            });
        }

        function selectReservation(res) {
            currentSelectedCode = res.reservation_code || res.reservation_group_id || String(res.id);

            // Highlight in list
            document.querySelectorAll('.res-card-item').forEach(el => el.classList.remove('selected'));
            const selectedEl = Array.from(document.querySelectorAll('.res-card-item')).find(el => el.textContent.includes(currentSelectedCode));
            if (selectedEl) selectedEl.classList.add('selected');

            renderDetailCard(res);

            // Open modal on laptop / slide drawer up on mobile
            openDrawer();
        }

        function openDrawer() {
            const panel = document.getElementById('detailPanel');
            const backdrop = document.getElementById('drawerBackdrop');
            if (panel) {
                panel.classList.add('drawer-open');
                panel.style.visibility = 'visible';
                panel.style.pointerEvents = 'auto';
            }
            if (backdrop) {
                backdrop.classList.add('visible');
            }
            document.body.style.overflow = 'hidden';
        }

        function closeDrawer() {
            const panel = document.getElementById('detailPanel');
            const backdrop = document.getElementById('drawerBackdrop');
            if (panel) {
                panel.classList.remove('drawer-open');
                panel.style.visibility = '';
                panel.style.pointerEvents = '';
            }
            if (backdrop) {
                backdrop.classList.remove('visible');
            }
            document.body.style.overflow = '';
        }

        // Helper to render measurements per item/product
        function renderProductSizes(p) {
            const qty = Math.max(1, parseInt(p.quantity) || 1);
            const sizeLabels = Array.isArray(p.size_labels) && p.size_labels.length ? p.size_labels : [];

            function getLabel(idx) {
                const found = sizeLabels.find(l => Number(l.index) === Number(idx));
                return found ? found.label : ('Size ' + idx);
            }

            // Build Item 1 sizes
            const items = [];
            const item1Sizes = {};
            for (let i = 1; i <= 10; i++) {
                const val = p['size_' + i];
                if (val !== undefined && val !== null && String(val).trim() !== '') {
                    item1Sizes[i] = String(val).trim();
                }
            }
            items.push({ item_number: 1, sizes: item1Sizes });

            // Build Item 2+ sizes
            const subItems = Array.isArray(p.items) ? p.items : [];
            for (let num = 2; num <= qty; num++) {
                const sub = subItems.find(it => Number(it.item_number) === num);
                const subSizes = {};
                for (let i = 1; i <= 10; i++) {
                    const val = sub ? sub['size_' + i] : item1Sizes[i];
                    if (val !== undefined && val !== null && String(val).trim() !== '') {
                        subSizes[i] = String(val).trim();
                    } else if (item1Sizes[i]) {
                        subSizes[i] = item1Sizes[i];
                    }
                }
                items.push({ item_number: num, sizes: subSizes });
            }

            const hasAnySize = items.some(it => Object.keys(it.sizes).length > 0);
            if (!hasAnySize) {
                return `<div class="res-no-sizes"><i class="fas fa-ruler-slash" style="margin-right:4px;"></i>No measurements specified</div>`;
            }

            if (qty === 1) {
                const chipHtml = Object.entries(items[0].sizes).map(([idx, val]) => `
                    <div class="res-size-chip">
                        <span class="size-chip-lbl">${escapeHtml(getLabel(idx))}:</span>
                        <span class="size-chip-val">${escapeHtml(val)}</span>
                    </div>
                `).join('');
                return `
                    <div class="res-sizes-container">
                        <div class="res-sizes-header">
                            <i class="fas fa-ruler-combined"></i> Measurements
                        </div>
                        <div class="res-sizes-chips">${chipHtml}</div>
                    </div>
                `;
            }

            // Multi-item breakdown
            const itemsHtml = items.map(it => {
                const chips = Object.entries(it.sizes).map(([idx, val]) => `
                    <div class="res-size-chip">
                        <span class="size-chip-lbl">${escapeHtml(getLabel(idx))}:</span>
                        <span class="size-chip-val">${escapeHtml(val)}</span>
                    </div>
                `).join('');

                return `
                    <div class="res-item-size-row">
                        <div class="res-item-badge"><i class="fas fa-tshirt"></i> Item ${it.item_number}</div>
                        <div class="res-sizes-chips">${chips || '<span style="color:#777; font-size:11px;">No measurements</span>'}</div>
                    </div>
                `;
            }).join('');

            return `
                <div class="res-sizes-container">
                    <div class="res-sizes-header">
                        <i class="fas fa-ruler-combined"></i> Measurements per Item (${qty} items)
                    </div>
                    ${itemsHtml}
                </div>
            `;
        }

        function renderDetailCard(res) {
            const body = document.getElementById('detailPanelBody');
            const code = res.reservation_code || res.reservation_group_id || '#' + res.id;
            const status = res.order_status || 'pending';
            const statusClass = status;
            const isWalkIn = res.is_walkin;
            const isAllBuy = (res.products && res.products.length > 0)
                ? res.products.every(p => (p.product_status === 'buy' || p.payment_method === 'buy'))
                : (res.product_status === 'buy' || res.payment_method === 'buy');

            let totalOrderPrice = 0;
            let totalOrderDownpayment = 0;
            let totalOrderBalance = 0;
            (res.products || []).forEach(p => {
                totalOrderPrice += parseFloat(p.total_price || 0);
                totalOrderDownpayment += parseFloat(p.downpayment_amount || 0);
                totalOrderBalance += parseFloat(p.balance_amount || 0);
            });

            // Build products section HTML
            let productsHtml = '';
            (res.products || []).forEach((prod, pIdx) => {
                const prodStatus = prod.order_status || status;
                const prodType = (prod.product_status === 'buy' || prod.payment_method === 'buy') ? 'buy' : 'reserve';
                const qty = Math.max(1, parseInt(prod.quantity || 1));
                const unitPrice = parseFloat(prod.price || 0);
                const lineTotal = parseFloat(prod.total_price || (unitPrice * qty));

                // Build size items for this product with size definition labels
                let itemsHtml = '';
                const prodItems = prod.items || [];
                const totalItems = prodItems.length > 0 ? (prodItems.length + 1) : 1;

                const sizeLabels = (Array.isArray(prod.size_labels) && prod.size_labels.length > 0)
                    ? prod.size_labels
                    : [
                        { index: 1, label: 'Chest/Bust' },
                        { index: 2, label: 'Waist' },
                        { index: 3, label: 'Hips' },
                        { index: 4, label: 'Shoulder' }
                    ];

                const allItemsList = [];
                allItemsList.push({ item_number: 1, sizes: prod });
                if (Array.isArray(prod.items)) {
                    prod.items.forEach(it => {
                        allItemsList.push({ item_number: parseInt(it.item_number), sizes: it });
                    });
                }

                allItemsList.forEach(item => {
                    let fieldsHtml = '';
                    sizeLabels.forEach(sl => {
                        const val = item.sizes['size_' + sl.index];
                        if (val !== undefined && val !== null && val !== '') {
                            fieldsHtml += `
                                <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid #444; border-radius: 6px; padding: 5px 10px; display: inline-flex; align-items: center; gap: 6px;">
                                    <span style="color: #aaa; font-size: 11.5px; font-weight: 500;">${escapeHtml(sl.label)}:</span>
                                    <span style="color: #FFD700; font-size: 13px; font-weight: 700;">${escapeHtml(val)}"</span>
                                </div>
                            `;
                        }
                    });

                    if (!fieldsHtml) {
                        fieldsHtml = '<span class="size-badge empty" style="font-size: 12px;">No measurements specified</span>';
                    }

                    itemsHtml += `
                        <div class="size-item-card">
                            <div class="size-item-header">
                                <span class="size-item-number">Item ${item.item_number}</span>
                            </div>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                ${fieldsHtml}
                            </div>
                        </div>
                    `;
                });

                const prodReserveDate = prod.reserve_date || res.reserve_date || '';
                const prodReturnDate = prod.return_date || res.return_date || '';

                productsHtml += `
                    <div style="background: #252528; border: 1px solid #3a3a40; border-radius: 8px; padding: 14px; margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 8px;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <strong style="color: #fff; font-size: 15px;">${escapeHtml(prod.product_name)}</strong>
                                    <span class="card-type-badge ${prodType}">${prodType === 'buy' ? 'Buy' : 'Reserve'}</span>
                                    <span class="res-detail-status-badge ${prodStatus}" style="font-size: 0.7rem; padding: 2px 8px;">${prodStatus}</span>
                                    ${(parseInt(prod.is_rescheduled || 0) === 1 || parseInt(res.is_rescheduled || 0) === 1) ? `
                                        <span style="background: rgba(245, 158, 11, 0.2); border: 1px solid #f59e0b; color: #fbbf24; font-size: 0.7rem; padding: 2px 8px; border-radius: 4px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fas fa-history"></i> Rescheduled
                                        </span>
                                    ` : ''}
                                </div>
                                <div style="color: #888; font-size: 12px; margin-top: 3px;">${escapeHtml(prod.category || '')} &bull; ₱${unitPrice.toLocaleString()} ea</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="color: #ffd700; font-weight: 700; font-size: 15px;">₱${lineTotal.toLocaleString()}</div>
                            </div>
                        </div>
                        <div style="font-size: 12px; font-weight: 600; color: #aaa; margin: 8px 0 4px 0;">Item Measurements (${totalItems}):</div>
                        ${itemsHtml}

                        <!-- Schedule under size items (Date only) -->
                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #333;">
                            ${prodType === 'buy' ? `
                                <div style="font-size: 12px; font-weight: 600; color: #ffd700; margin-bottom: 6px;"><i class="fas fa-shopping-bag"></i> Product Purchase:</div>
                                <div style="background: rgba(0,0,0,0.25); padding: 8px 10px; border-radius: 6px;">
                                    <span style="display: block; font-size: 11px; color: #888;">Date</span>
                                    <span style="font-size: 12.5px; color: #fff; font-weight: 600;">${prodReserveDate || '-'}</span>
                                </div>
                            ` : `
                                <div style="font-size: 12px; font-weight: 600; color: #ffd700; margin-bottom: 6px;"><i class="fas fa-calendar-alt"></i> Product Rental Period:</div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: rgba(0,0,0,0.25); padding: 8px 10px; border-radius: 6px;">
                                    <div>
                                        <span style="display: block; font-size: 11px; color: #888;">Reserve</span>
                                        <span style="font-size: 12.5px; color: #fff; font-weight: 600;">${prodReserveDate || '-'}</span>
                                        ${(prod.original_reserve_date || res.original_reserve_date) ? `
                                            <span style="display: block; font-size: 10.5px; color: #f87171; text-decoration: line-through;">was ${escapeHtml(prod.original_reserve_date || res.original_reserve_date)}</span>
                                        ` : ''}
                                    </div>
                                    <div>
                                        <span style="display: block; font-size: 11px; color: #888;">Return</span>
                                        <span style="font-size: 12.5px; color: #fff; font-weight: 600;">${prodReturnDate || '-'}</span>
                                        ${(prod.original_return_date || res.original_return_date) ? `
                                            <span style="display: block; font-size: 10.5px; color: #f87171; text-decoration: line-through;">was ${escapeHtml(prod.original_return_date || res.original_return_date)}</span>
                                        ` : ''}
                                    </div>
                                </div>
                            `}
                        </div>
                    </div>
                `;
            });

            const isOrderRescheduled = parseInt(res.is_rescheduled || 0) === 1 || (res.products || []).some(p => parseInt(p.is_rescheduled || 0) === 1);
            const rescheduledDateFormatted = res.rescheduled_at ? new Date(res.rescheduled_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

            body.innerHTML = `
                <div style="display: flex; flex-direction: column; gap: 0;">

                    ${isOrderRescheduled ? `
                        <!-- Rescheduled Notification Banner for Staff -->
                        <div class="res-detail-section" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.18), rgba(180, 83, 9, 0.1)); border: 1.5px solid #f59e0b; border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; box-shadow: 0 4px 16px rgba(245, 158, 11, 0.15);">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; margin-bottom: 10px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="background: #f59e0b; color: #000; font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-history"></i> Rescheduled
                                    </span>
                                    <span style="color: #fbbf24; font-weight: 700; font-size: 13.5px;">Customer Rescheduled This Reservation</span>
                                </div>
                                ${rescheduledDateFormatted ? `<span style="font-size: 11px; color: #d1d5db; background: rgba(0,0,0,0.3); padding: 2px 8px; border-radius: 4px;"><i class="far fa-clock"></i> ${rescheduledDateFormatted}</span>` : ''}
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: rgba(0,0,0,0.35); padding: 8px 12px; border-radius: 8px; font-size: 12px; margin-bottom: 8px;">
                                <div>
                                    <span style="display: block; color: #9ca3af; font-size: 10.5px; text-transform: uppercase; font-weight: 600;">Original (1st) Dates</span>
                                    <span style="color: #f87171; font-weight: 600; text-decoration: line-through;">
                                        ${escapeHtml(res.original_reserve_date || '-')} &rarr; ${escapeHtml(res.original_return_date || '-')}
                                    </span>
                                </div>
                                <div>
                                    <span style="display: block; color: #9ca3af; font-size: 10.5px; text-transform: uppercase; font-weight: 600;">Current (Rescheduled) Dates</span>
                                    <span style="color: #4ade80; font-weight: 700;">
                                        ${escapeHtml(res.reserve_date || '-')} &rarr; ${escapeHtml(res.return_date || '-')}
                                    </span>
                                </div>
                            </div>
                            ${res.reschedule_reason ? `
                                <div style="font-size: 12px; color: #f3f4f6; background: rgba(0,0,0,0.25); padding: 7px 10px; border-radius: 6px; border-left: 3px solid #f59e0b;">
                                    <strong style="color: #fbbf24;">Reschedule Reason:</strong> ${escapeHtml(res.reschedule_reason)}
                                </div>
                            ` : ''}
                        </div>
                    ` : ''}

                    <!-- Reserved Products & Details (At the top) -->
                    <div class="res-detail-section">
                        <div class="res-detail-section-title"><i class="fas fa-tag"></i> Reserved Products & Details (${(res.products || []).length})</div>
                        ${productsHtml}
                    </div>

                    <!-- Reservation Code & QR Code + Customer Valid ID (Compact Action Buttons Side-by-Side to Maximize Space) -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 14px;">
                        <!-- Reservation Code & QR Code Button -->
                        <button type="button" class="res-detail-qr-btn" onclick="showStaffReservationQR(decodeURIComponent('${encodeURIComponent(code)}'))" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; background: #222226; border: 1px solid rgba(255,215,0,0.35); border-radius: 8px; color: #fff; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(0,0,0,0.25); text-align: left;">
                            <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                                <div style="width: 34px; height: 34px; border-radius: 8px; background: rgba(255,215,0,0.15); border: 1px solid rgba(255,215,0,0.4); display: flex; align-items: center; justify-content: center; color: #ffd700; font-size: 15px; flex-shrink: 0;">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <div style="font-size: 9.5px; color: #888; line-height: 1.2; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Reservation Code</div>
                                    <div style="font-size: 13px; font-weight: 700; color: #ffd700; font-family: monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(code)}</div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 700; color: #ffd700; background: rgba(255,215,0,0.12); padding: 6px 10px; border-radius: 6px; border: 1px solid rgba(255,215,0,0.25); flex-shrink: 0; margin-left: 6px;">
                                <i class="fas fa-expand"></i> QR
                            </div>
                        </button>

                        <!-- Customer Valid ID Button -->
                        <button type="button" id="btnViewCustomerValidId" class="res-detail-qr-btn" onclick="showStaffValidIdCard(decodeURIComponent('${encodeURIComponent(res.id_front_image || '')}'), decodeURIComponent('${encodeURIComponent(res.id_type || '')}'), decodeURIComponent('${encodeURIComponent(res.customer_name || '')}'))" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; background: #222226; border: 1px solid rgba(255,215,0,0.35); border-radius: 8px; color: #fff; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(0,0,0,0.25); text-align: left;">
                            <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                                <div style="width: 34px; height: 34px; border-radius: 8px; background: rgba(255,215,0,0.15); border: 1px solid rgba(255,215,0,0.4); display: flex; align-items: center; justify-content: center; color: #ffd700; font-size: 15px; flex-shrink: 0;">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <div style="min-width: 0;">
                                    <div style="font-size: 9.5px; color: #888; line-height: 1.2; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Customer Valid ID</div>
                                    <div style="font-size: 12.5px; font-weight: 700; color: ${res.id_front_image ? '#ffd700' : '#888'}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(res.id_type || (res.id_front_image ? 'Uploaded' : 'No ID uploaded'))}</div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 700; color: #ffd700; background: rgba(255,215,0,0.12); padding: 6px 10px; border-radius: 6px; border: 1px solid rgba(255,215,0,0.25); flex-shrink: 0; margin-left: 6px;">
                                <i class="fas fa-expand"></i> ID
                            </div>
                        </button>
                    </div>

                    <!-- Customer Info & Payment -->
                    <div class="res-detail-section">
                        <div class="res-detail-section-title"><i class="fas fa-user"></i> Customer & Payment</div>
                        <div class="res-detail-grid">
                            <div class="res-detail-field">
                                <span class="res-detail-label">Customer Name</span>
                                <span class="res-detail-value">${escapeHtml(res.customer_name || 'N/A')}</span>
                            </div>
                            <div class="res-detail-field">
                                <span class="res-detail-label">Customer Email</span>
                                <span class="res-detail-value">${escapeHtml(res.customer_email || 'N/A')}</span>
                            </div>
                            <div class="res-detail-field">
                                <span class="res-detail-label">Payment Option</span>
                                <span class="res-detail-value">${res.payment_option === 'first_payment' ? (totalOrderDownpayment > 0 ? `First Payment (₱${totalOrderDownpayment.toLocaleString()} Deposit)` : 'First Payment (₱500 Deposit)') : '<span style="color: #4ade80; font-weight: 700;">Full Payment (Paid)</span>'}</span>
                            </div>
                            ${res.payment_option === 'first_payment' ? `
                                <div class="res-detail-field">
                                    <span class="res-detail-label">Deposit Paid</span>
                                    <span class="res-detail-value" style="color: #6cd488;">₱${totalOrderDownpayment.toLocaleString()}</span>
                                </div>
                                <div class="res-detail-field">
                                    <span class="res-detail-label">Due on Pickup</span>
                                    <span class="res-detail-value" style="color: #f87171; font-weight: 700;">₱${totalOrderBalance.toLocaleString()}</span>
                                </div>
                            ` : ''}
                            <div class="res-detail-field res-detail-full">
                                <span class="res-detail-label">Total Amount</span>
                                <span class="res-detail-value gold" style="font-size: 1.1rem;">₱${totalOrderPrice.toLocaleString()}</span>
                            </div>

                            <!-- User Uploaded Payment Proof Section -->
                            <div class="res-detail-field res-detail-full" style="margin-top: 10px; border-top: 1px solid #383b45; padding-top: 10px;">
                                <span class="res-detail-label" style="display: flex; align-items: center; gap: 6px; font-weight: 700; color: #ffd700; margin-bottom: 6px;">
                                    Uploaded Payment
                                </span>
                                ${(() => {
                                    let proofPath = res.payment_proof || '';
                                    if (proofPath) {
                                        let fullImgPath = proofPath.startsWith('http') || proofPath.startsWith('/') || proofPath.startsWith('../') ? proofPath : ('../' + proofPath);
                                        return `
                                            <div>
                                                <a href="${fullImgPath}" target="_blank" title="Click to view full size receipt">
                                                    <img src="${fullImgPath}" alt="Uploaded Payment" style="width: 85px; height: 85px; object-fit: cover; border-radius: 6px; border: 2px solid #ffd700; cursor: pointer; display: block; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                                                </a>
                                            </div>
                                        `;
                                    } else {
                                        return `
                                            <div style="font-size: 12px; color: #888; font-style: italic;">
                                                No payment proof uploaded (In-Store Cash / Walk-in)
                                            </div>
                                        `;
                                    }
                                })()}
                            </div>
                        </div>
                    </div>

                    ${res.cancellation_reason ? `
                        <div class="res-detail-section" style="background: rgba(244, 67, 54, 0.08); border: 1px solid rgba(244, 67, 54, 0.4);">
                            <div class="res-detail-section-title" style="color: #f44336; margin-bottom: 6px;"><i class="fas fa-ban"></i> Cancellation Details</div>
                            <div style="font-size: 12px; color: #ff8888; margin-bottom: 4px;">
                                <strong>Reason:</strong> ${escapeHtml(res.cancellation_reason)}
                            </div>
                            ${(() => {
                                try {
                                    const imgs = typeof res.cancellation_images === 'string' ? JSON.parse(res.cancellation_images || '[]') : (res.cancellation_images || []);
                                    if (Array.isArray(imgs) && imgs.length > 0) {
                                        return '<div style="margin-top: 8px;"><div style="font-size: 11px; color: #aaa; margin-bottom: 4px;">Problem Attachments:</div><div style="display: flex; gap: 8px; flex-wrap: wrap;">' +
                                            imgs.map(img => `<img src="../${img}" alt="Problem" onclick="window.open('../${img}', '_blank')" style="width: 70px; height: 70px; object-fit: cover; border-radius: 6px; border: 1px solid #f44336; cursor: pointer;" title="Click to view full image">`).join('') +
                                            '</div></div>';
                                    }
                                } catch(e) {}
                                return '';
                            })()}
                        </div>
                    ` : ''}

                    <!-- Management Actions -->
                    <div class="res-detail-section">
                        <div class="res-detail-section-title"><i class="fas fa-cog"></i> Manage Reservation Status</div>
                        <div class="res-action-buttons">
                            ${status === 'pending' ? `
                                <button class="action-btn btn-confirm" onclick="promptStatusChange('${escapeHtml(code)}', 'confirmed')">
                                    <i class="fas fa-check"></i>
                                    Confirm
                                </button>
                                <button class="action-btn btn-cancel" onclick="openStaffCancellationModal('${escapeHtml(code)}')">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </button>
                            ` : ''}
                            ${status === 'confirmed' ? `
                                <button class="action-btn btn-complete" onclick="promptStatusChange('${escapeHtml(code)}', 'completed')">
                                    <i class="fas fa-check-double"></i>
                                    Complete
                                </button>
                            ` : ''}
                            ${status === 'completed' ? `
                                <button class="action-btn btn-complete" style="opacity:0.6; cursor:default;" disabled>
                                    <i class="fas fa-box-check"></i>
                                    Completed
                                </button>
                            ` : ''}
                            ${status === 'cancelled' ? `
                                <button class="action-btn" style="background:#d97706; color:#fff;" onclick="promptStatusChange('${escapeHtml(code)}', 'pending')">
                                    <i class="fas fa-undo"></i>
                                    Reopen (Pending)
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        // ===== CUSTOMER VALID ID MODAL (single card: front incl. user) =====
        function showStaffValidIdCard(frontImg, idType, customerName) {
            let modal = document.getElementById('staffValidIdModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'staffValidIdModal';
                modal.style.cssText = 'position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:10000050; display:flex; align-items:center; justify-content:center; padding:20px;';
                modal.onclick = () => { modal.style.display = 'none'; };
                document.body.appendChild(modal);
            }

            let imgHtml;
            if (frontImg) {
                const path = (frontImg.startsWith('http') || frontImg.startsWith('/') || frontImg.startsWith('../')) ? frontImg : ('../' + frontImg);
                imgHtml = `
                    <a href="${escapeHtml(path)}" target="_blank" title="Open full size">
                        <img src="${escapeHtml(path)}" alt="Card Front Including User" style="width:100%; max-height:60vh; object-fit:contain; border-radius:10px; border:2px solid #ffd700; background:#111; display:block;">
                    </a>`;
            } else {
                imgHtml = '<div style="padding:40px 10px; color:#888; font-size:13px; font-style:italic; border:1px dashed #444; border-radius:10px;">No ID image uploaded</div>';
            }

            modal.innerHTML = `
                <div style="background:#1a1a1a; border:2px solid #ffd700; border-radius:18px; padding:22px; max-width:480px; width:100%; text-align:center; box-shadow:0 12px 48px rgba(0,0,0,0.9);" onclick="event.stopPropagation()">
                    <div style="color:#ffd700; font-size:17px; font-weight:800; margin-bottom:4px;"><i class="fas fa-id-card"></i> Customer Valid ID</div>
                    <div style="color:#aaa; font-size:12px; margin-bottom:14px;">${escapeHtml(customerName || 'Customer')} &middot; ${escapeHtml(idType || 'ID type not specified')}</div>
                    <div style="font-size:11px; color:#ffd700; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:8px; text-align:left;">Card Front (Including User)</div>
                    ${imgHtml}
                    <button type="button" onclick="document.getElementById('staffValidIdModal').style.display='none'" style="margin-top:16px; background:linear-gradient(135deg,#7c6300,#b8960c); color:#fff; border:none; border-radius:24px; padding:9px 28px; font-size:12px; font-weight:700; cursor:pointer;">Close</button>
                </div>
            `;
            modal.style.display = 'flex';
        }

        // ===== QR CODE MODAL FOR STAFF RESERVATION DETAILS =====
        function showStaffReservationQR(code) {
            if (!code) return;
            let modal = document.getElementById('staffReservationQRModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'staffReservationQRModal';
                modal.style.cssText = 'position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.85); z-index:10000050; display:flex; align-items:center; justify-content:center; padding:20px;';
                modal.onclick = () => { modal.style.display = 'none'; };
                document.body.appendChild(modal);
            } else {
                modal.style.zIndex = '10000050';
            }

            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(code)}`;
            modal.innerHTML = `
                <div style="background:#1a1a1a; border:2px solid #ffd700; border-radius:18px; padding:24px; max-width:360px; width:100%; text-align:center; box-shadow:0 12px 48px rgba(0,0,0,0.9);" onclick="event.stopPropagation()">
                    <div style="color:#ffd700; font-size:17px; font-weight:800; margin-bottom:4px;"><i class="fas fa-qrcode"></i> Reservation QR Code</div>
                    <div style="color:#aaa; font-size:12px; margin-bottom:16px;">Scan to verify reservation</div>
                    <div style="background:#ffffff; padding:14px; border-radius:14px; display:inline-block; margin-bottom:14px; box-shadow:0 4px 16px rgba(0,0,0,0.4);">
                        <img src="${qrUrl}" alt="Reservation QR" style="width:200px; height:200px; display:block;">
                    </div>
                    <div style="color:#ffd700; font-size:16px; font-weight:800; letter-spacing:1px; margin-bottom:3px; font-family: monospace;">${escapeHtml(code)}</div>
                    <div style="color:#777; font-size:11px; margin-bottom:16px;">Reservation Code</div>
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button type="button" onclick="navigator.clipboard.writeText('${escapeHtml(code)}'); this.innerHTML='<i class=\\'fas fa-check\\'></i> Copied!'; setTimeout(()=>{this.innerHTML='<i class=\\'far fa-copy\\'></i> Copy Code';}, 2000);" style="background:#2b2d35; border:1px solid #444; color:#ddd; border-radius:24px; padding:9px 18px; font-size:12px; font-weight:600; cursor:pointer;">
                            <i class="far fa-copy"></i> Copy Code
                        </button>
                        <button type="button" onclick="document.getElementById('staffReservationQRModal').style.display='none'" style="background:linear-gradient(135deg,#7c6300,#b8960c); color:#fff; border:none; border-radius:24px; padding:9px 24px; font-size:12px; font-weight:700; cursor:pointer;">Close</button>
                    </div>
                </div>
            `;
            modal.style.display = 'flex';
        }

        // =======================================================
        // BIG CANCELLATION MODAL LOGIC (Matches admin/calendar.php)
        // =======================================================
        let cancelSelectedFiles = [];

        function handleCancellationReasonChange(selectEl) {
            const val = selectEl.value;
            const notesEl = document.getElementById('cancellationReasonNotes');
            const starEl = document.getElementById('notesRequiredStar');

            document.querySelectorAll('.cancel-quick-chip').forEach(chip => {
                if (chip.getAttribute('data-reason') === val) {
                    chip.classList.add('active');
                } else {
                    chip.classList.remove('active');
                }
            });

            if (val === 'Other Reason') {
                if (notesEl) {
                    notesEl.required = true;
                    notesEl.placeholder = 'Please provide detailed reason for cancellation (required)...';
                }
                if (starEl) starEl.style.display = 'inline';
            } else {
                if (notesEl) {
                    notesEl.required = false;
                    notesEl.placeholder = 'Provide extra details or instructions for the customer (optional if standard reason selected)...';
                }
                if (starEl) starEl.style.display = 'none';
            }
        }

        function selectCancelChip(chipEl, reasonText) {
            const selectEl = document.getElementById('cancellationReasonSelect');
            if (!selectEl) return;
            selectEl.value = reasonText;
            handleCancellationReasonChange(selectEl);
        }

        function openStaffCancellationModal(code) {
            cancelSelectedFiles = [];
            const codeInput = document.getElementById('cancelReservationCode');
            const idInput = document.getElementById('cancelReservationId');
            if (codeInput) codeInput.value = code;

            const res = (Array.isArray(allReservations) ? allReservations.find(r => r.reservation_code === code || r.reservation_group_id === code || String(r.id) === code) : null) || {};
            if (idInput) idInput.value = res.id || '';

            // Reset reason select and notes
            const selectEl = document.getElementById('cancellationReasonSelect');
            if (selectEl) selectEl.value = '';
            const notesEl = document.getElementById('cancellationReasonNotes');
            if (notesEl) {
                notesEl.value = '';
                notesEl.required = false;
                notesEl.placeholder = 'Provide extra details or instructions for the customer (optional if standard reason selected)...';
            }
            const starEl = document.getElementById('notesRequiredStar');
            if (starEl) starEl.style.display = 'none';

            // Reset chips
            document.querySelectorAll('.cancel-quick-chip').forEach(c => c.classList.remove('active'));

            const previewEl = document.getElementById('cancelImagesPreview');
            if (previewEl) previewEl.innerHTML = '';
            const inputEl = document.getElementById('cancelImagesInput');
            if (inputEl) inputEl.value = '';

            const summaryEl = document.getElementById('cancelReservationSummary');
            if (summaryEl) {
                let prodTitle = 'Reservation #' + (res.reservation_code || res.reservation_group_id || res.id || code);
                if (res.products && res.products.length > 1) {
                    prodTitle = res.products.length + ' Products &bull; ' + (res.reservation_code || code);
                } else if (res.products && res.products[0] && res.products[0].product_name) {
                    prodTitle = res.products[0].product_name;
                }
                summaryEl.innerHTML = `
                    <div style="font-weight: 700; color: #fff; font-size: 13.5px; margin-bottom: 4px;">${escapeHtml(prodTitle)}</div>
                    <div style="color: #aaa; font-size: 0.8rem;">
                        Customer: <span style="color: #FFD700; font-weight: 600;">${escapeHtml(res.customer_name || 'N/A')}</span> &bull; 
                        Date: <span style="color: #fff;">${escapeHtml(res.reserve_date || 'N/A')}</span> &bull;
                        Code: <span style="color: #ffd700; font-family: monospace;">${escapeHtml(code)}</span>
                    </div>
                `;
            }

            const cancelModal = document.getElementById('cancellationModal');
            if (cancelModal) {
                cancelModal.classList.add('slide-panel-open');
            }

            const backdrop = document.getElementById('cancellationBackdrop');
            if (backdrop) backdrop.classList.add('active');

            document.body.style.overflow = 'hidden';
        }

        function closeCancellationModal() {
            const cancelModal = document.getElementById('cancellationModal');
            if (cancelModal) {
                cancelModal.classList.remove('slide-panel-open');
            }

            const backdrop = document.getElementById('cancellationBackdrop');
            if (backdrop) backdrop.classList.remove('active');

            document.body.style.overflow = '';
        }

        function handleCancelImagesSelect(input) {
            if (!input.files) return;
            const newFiles = Array.from(input.files);
            cancelSelectedFiles = cancelSelectedFiles.concat(newFiles);
            renderCancelImagesPreview();
            input.value = '';
        }

        function handleCancelImagesDrop(event) {
            event.preventDefault();
            event.currentTarget.style.borderColor = '#383b45';
            if (event.dataTransfer && event.dataTransfer.files) {
                const newFiles = Array.from(event.dataTransfer.files).filter(f => f.type.startsWith('image/'));
                cancelSelectedFiles = cancelSelectedFiles.concat(newFiles);
                renderCancelImagesPreview();
            }
        }

        function removeCancelImage(index) {
            cancelSelectedFiles.splice(index, 1);
            renderCancelImagesPreview();
        }

        function renderCancelImagesPreview() {
            const container = document.getElementById('cancelImagesPreview');
            if (!container) return;
            container.innerHTML = '';
            cancelSelectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const wrap = document.createElement('div');
                    wrap.style.cssText = 'position: relative; width: 70px; height: 70px; border-radius: 6px; overflow: hidden; border: 1px solid #444;';
                    wrap.innerHTML = `
                        <img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">
                        <button type="button" onclick="removeCancelImage(${index})" style="position: absolute; top: 2px; right: 2px; background: rgba(0,0,0,0.7); border: none; color: #ff5252; border-radius: 50%; width: 18px; height: 18px; cursor: pointer; font-size: 10px; display: flex; align-items: center; justify-content: center;">&times;</button>
                    `;
                    container.appendChild(wrap);
                };
                reader.readAsDataURL(file);
            });
        }

        function submitStaffCancellation(event) {
            event.preventDefault();
            const code = document.getElementById('cancelReservationCode').value;
            const resId = document.getElementById('cancelReservationId').value;
            const selectEl = document.getElementById('cancellationReasonSelect');
            const selectedReason = selectEl ? selectEl.value.trim() : '';
            const notesEl = document.getElementById('cancellationReasonNotes');
            const notes = notesEl ? notesEl.value.trim() : '';

            if (!selectedReason) {
                showToast('Please select a cancellation reason.', 3500);
                if (selectEl) selectEl.focus();
                return;
            }

            if (selectedReason === 'Other Reason' && !notes) {
                showToast('Please specify the detailed explanation for the cancellation.', 3500);
                if (notesEl) notesEl.focus();
                return;
            }

            // Build final reason string
            let finalReason = selectedReason;
            if (selectedReason === 'Other Reason') {
                finalReason = notes;
            } else if (notes) {
                finalReason = selectedReason + ' — Note: ' + notes;
            }

            const btn = document.getElementById('confirmCancelBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';

            const formData = new FormData();
            formData.append('code', code);
            if (resId) formData.append('id', resId);
            formData.append('status', 'cancelled');
            formData.append('cancellation_reason', finalReason);
            cancelSelectedFiles.forEach((file) => {
                formData.append('cancellation_images[]', file);
            });

            fetch('api_update_status.php', {
                method: 'POST',
                body: formData
            })
            .then(async res => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(text || 'Invalid server response');
                }
            })
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-times-circle"></i> Confirm Cancellation';
                if (data.success) {
                    closeCancellationModal();
                    
                    // Optimistic update local memory & DOM
                    updateStatusRealtime(code, 'cancelled', finalReason);
                    showToast('Reservation successfully cancelled');
                    
                    // Refresh data
                    const searchInput = document.getElementById('searchInput');
                    const query = searchInput ? searchInput.value.trim() : '';
                    loadReservations(query);
                } else {
                    showToast(data.error || data.message || 'Failed to cancel reservation', 4000);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-times-circle"></i> Confirm Cancellation';
                console.error(err);
                showToast(err.message || 'Error cancelling reservation', 4000);
            });
        }

        let pendingStatusUpdate = { code: null, status: null, reason: '' };

        function promptStatusChange(code, newStatus) {
            if (!code || !newStatus) return;
            if (newStatus === 'cancelled') {
                openStaffCancellationModal(code);
                return;
            }
            pendingStatusUpdate = { code: code, status: newStatus, reason: '' };

            const modal = document.getElementById('statusConfirmModal');
            const iconWrap = document.getElementById('statusConfirmIcon');
            const iconEl = document.getElementById('statusConfirmIconEl');
            const titleEl = document.getElementById('statusConfirmTitle');
            const descEl = document.getElementById('statusConfirmDesc');
            const reasonGroup = document.getElementById('statusCancelReasonGroup');
            const reasonInput = document.getElementById('statusCancelReasonInput');
            const actionBtn = document.getElementById('statusConfirmActionBtn');

            if (!modal) return;

            // Reset modal styles
            iconWrap.className = 'clear-chat-confirm-icon';
            actionBtn.className = 'clear-chat-btn-delete';
            if (reasonInput) reasonInput.value = '';

            const displayCode = escapeHtml(code);

            if (newStatus === 'confirmed') {
                iconWrap.classList.add('icon-confirm');
                iconEl.className = 'fas fa-check-circle';
                titleEl.textContent = 'Confirm Reservation?';
                descEl.innerHTML = `Are you sure you want to mark reservation <strong style="color:#ffd700;">${displayCode}</strong> as confirmed?`;
                actionBtn.textContent = 'Confirm';
                actionBtn.classList.add('btn-status-confirm');
                if (reasonGroup) reasonGroup.style.display = 'none';
            } else if (newStatus === 'completed') {
                iconWrap.classList.add('icon-complete');
                iconEl.className = 'fas fa-check-double';
                titleEl.textContent = 'Complete Reservation?';
                descEl.innerHTML = `Mark reservation <strong style="color:#ffd700;">${displayCode}</strong> as completed and fulfilled?`;
                actionBtn.textContent = 'Complete';
                actionBtn.classList.add('btn-status-complete');
                if (reasonGroup) reasonGroup.style.display = 'none';
            } else if (newStatus === 'cancelled') {
                iconWrap.classList.add('icon-cancel');
                iconEl.className = 'fas fa-times-circle';
                titleEl.textContent = 'Cancel Reservation?';
                descEl.innerHTML = `Are you sure you want to cancel reservation <strong style="color:#ffd700;">${displayCode}</strong>?`;
                actionBtn.textContent = 'Cancel Order';
                actionBtn.classList.add('btn-status-cancel');
                if (reasonGroup) {
                    reasonGroup.style.display = 'block';
                    setTimeout(() => { if (reasonInput) reasonInput.focus(); }, 120);
                }
            } else if (newStatus === 'pending') {
                iconWrap.classList.add('icon-pending');
                iconEl.className = 'fas fa-clock';
                titleEl.textContent = 'Set to Pending?';
                descEl.innerHTML = `Revert reservation <strong style="color:#ffd700;">${displayCode}</strong> back to pending status?`;
                actionBtn.textContent = 'Set Pending';
                actionBtn.classList.add('btn-status-pending');
                if (reasonGroup) reasonGroup.style.display = 'none';
            }

            modal.classList.add('show');
        }

        function closeStatusConfirmModal() {
            const modal = document.getElementById('statusConfirmModal');
            if (modal) modal.classList.remove('show');
            pendingStatusUpdate = { code: null, status: null, reason: '' };
        }

        function executePendingStatusChange() {
            if (!pendingStatusUpdate.code || !pendingStatusUpdate.status) {
                closeStatusConfirmModal();
                return;
            }

            const code = pendingStatusUpdate.code;
            const newStatus = pendingStatusUpdate.status;
            let reason = '';
            if (newStatus === 'cancelled') {
                const reasonInput = document.getElementById('statusCancelReasonInput');
                reason = reasonInput ? reasonInput.value.trim() : '';
                if (!reason) reason = 'Cancelled by staff';
            }

            closeStatusConfirmModal();
            updateStatusRealtime(code, newStatus, reason);
        }

        function updateStatusRealtime(code, newStatus, reason = '') {
            if (!code || !newStatus) return;

            // 1. Instant Realtime Optimistic DOM & Memory State Update (Zero lag)
            let targetRes = null;
            if (Array.isArray(allReservations)) {
                allReservations.forEach(r => {
                    const rCode = r.reservation_code || r.reservation_group_id || String(r.id);
                    if (rCode === code || r.reservation_code === code || r.reservation_group_id === code || String(r.id) === code) {
                        r.order_status = newStatus;
                        if (newStatus === 'cancelled') {
                            r.cancellation_reason = reason;
                        }
                        targetRes = r;
                    }
                });
            }

            // Immediately update KPI numbers locally
            recalcKPIsFromMemory();

            // Immediately update list item in DOM if rendered
            updateListItemStatusInDOM(code, newStatus);

            // Immediately update calendar pills
            renderStaffCalendar();

            // Immediately re-render active detail card if this code is currently viewed
            if (currentSelectedCode && (currentSelectedCode === code || (targetRes && (targetRes.reservation_code === currentSelectedCode || targetRes.reservation_group_id === currentSelectedCode || String(targetRes.id) === currentSelectedCode)))) {
                if (targetRes) {
                    renderDetailCard(targetRes);
                }
            }

            showToast(`Reservation marked as ${newStatus.toUpperCase()}`);

            // 2. Perform backend API update
            fetch('api_update_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code: code, status: newStatus, reason: reason })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Silently sync latest data from server to keep everything in sync
                    const searchInput = document.getElementById('searchInput');
                    const query = searchInput ? searchInput.value.trim() : '';
                    loadReservations(query);
                } else {
                    showToast(data.error || 'Failed to update status on server', 4000);
                    loadReservations();
                }
            }).catch(err => {
                console.error(err);
                showToast('Error syncing status update to server', 4000);
            });
        }

        function updateListItemStatusInDOM(code, newStatus) {
            const container = document.getElementById('reservationsList');
            if (!container) return;
            const items = container.querySelectorAll('.res-card-item');
            items.forEach(card => {
                if (card.textContent.includes(code)) {
                    const pill = card.querySelector('.res-status-pill');
                    if (pill) {
                        pill.className = `res-status-pill pill-${newStatus}`;
                        pill.textContent = newStatus;
                    }
                }
            });
        }

        function recalcKPIsFromMemory() {
            if (!Array.isArray(allReservations)) return;
            const uniqueCodes = new Set();
            let total = 0, pending = 0, confirmed = 0, completed = 0, rescheduled = 0, cancelled = 0, walkin = 0;
            allReservations.forEach(r => {
                const k = r.reservation_code || r.reservation_group_id || r.id;
                if (!uniqueCodes.has(k)) {
                    uniqueCodes.add(k);
                    total++;
                    const st = (r.order_status || 'pending').toLowerCase();
                    if (st === 'pending') pending++;
                    else if (st === 'confirmed') confirmed++;
                    else if (st === 'completed') completed++;
                    else if (st === 'cancelled') cancelled++;
                    if (r.is_rescheduled == 1 || (r.products && r.products.some(p => p.is_rescheduled == 1))) rescheduled++;
                    if (r.is_walkin) walkin++;
                }
            });
            renderKPIs({ total, pending, confirmed, completed, rescheduled, cancelled, walkin });
        }

        // Backward-compatibility wrapper
        function updateStatus(code, newStatus, reason = '') {
            promptStatusChange(code, newStatus);
        }

        function promptCancel(code) {
            promptStatusChange(code, 'cancelled');
        }

        let searchDebounceTimer = null;

        function handleRealtimeSearch(value) {
            const clearBtn = document.getElementById('clearSearchBtn');
            if (clearBtn) {
                clearBtn.style.display = value.trim() ? 'block' : 'none';
            }

            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                const query = value.trim().toLowerCase();
                if (!query) {
                    renderReservationList(allReservations);
                    return;
                }

                // Instant local filter in memory
                const filtered = allReservations.filter(res => {
                    const code = (res.reservation_code || res.reservation_group_id || '#' + res.id).toLowerCase();
                    const name = (res.customer_name || '').toLowerCase();
                    const email = (res.customer_email || '').toLowerCase();
                    const products = (res.products || []).map(p => (p.product_name || p.name || '') + ' ' + (p.category || '')).join(' ').toLowerCase();
                    return code.includes(query) || name.includes(query) || email.includes(query) || products.includes(query);
                });

                if (filtered.length > 0) {
                    renderReservationList(filtered);
                } else {
                    // Fallback to server search if no matching records in current memory list
                    loadReservations(query);
                }
            }, 120);
        }

        function clearSearch() {
            const input = document.getElementById('searchInput');
            input.value = '';
            const clearBtn = document.getElementById('clearSearchBtn');
            if (clearBtn) clearBtn.style.display = 'none';
            input.focus();
            renderReservationList(allReservations);
        }

        function filterByStatus(status, el) {
            currentStatusFilter = status;

            // Clear any calendar date filter when user clicks a status filter / top box
            calSelectedDate = null;
            document.querySelectorAll('#staffCalendarGrid .calendar-day').forEach(d => {
                d.classList.remove('selected-date');
            });
            const dateBanner = document.getElementById('calDateFilterBanner');
            if (dateBanner) dateBanner.style.display = 'none';

            document.querySelectorAll('.tab-chip').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.kpi-card').forEach(k => k.classList.remove('active'));

            if (el && el.classList) {
                el.classList.add('active');
            }

            // Synchronize matching tab chip
            const targetChip = document.getElementById('tabChip-' + status);
            if (targetChip) {
                targetChip.classList.add('active');
            } else {
                const matchingTab = Array.from(document.querySelectorAll('.tab-chip')).find(t => {
                    const txt = t.textContent.trim().toLowerCase();
                    return txt.includes(status) || (status === 'all' && txt === 'all') || (status === 'walkin' && txt.includes('walk-in'));
                });
                if (matchingTab) matchingTab.classList.add('active');
            }

            const targetKpi = document.getElementById('kpiCard-' + status);
            if (targetKpi) targetKpi.classList.add('active');

            // If on mobile: smoothly switch to Search / Reservation Records view and scroll matching tab into view
            if (window.innerWidth <= 900) {
                navFocusSearch(false);
                setTimeout(() => {
                    const activeTab = document.querySelector('.tab-chip.active');
                    if (activeTab) {
                        activeTab.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                    }
                }, 120);
            } else {
                // On laptop: open the Reservation Records modal for this status
                openRecordsModal(status);
            }

            const searchInput = document.getElementById('searchInput');
            const query = searchInput ? searchInput.value.trim() : '';
            loadReservations(query);
        }

        function openRecordsModal(status = null) {
            if (status) {
                currentStatusFilter = status;
            }
            const modal = document.getElementById('recordsListCardPanel');
            const backdrop = document.getElementById('recordsModalBackdrop');
            if (backdrop) backdrop.classList.add('active');
            if (modal) modal.classList.add('desktop-records-modal-open');

            // Update modal title to reflect the clicked box
            const titleEl = document.getElementById('recordsPanelTitleText');
            if (titleEl) {
                const map = {
                    'all': 'Total Orders',
                    'walkin': 'Walk-In Reservations',
                    'pending': 'Pending Reservations',
                    'confirmed': 'Confirmed Reservations',
                    'completed': 'Completed Reservations',
                    'rescheduled': 'Rescheduled Reservations',
                    'cancelled': 'Cancelled Reservations'
                };
                titleEl.textContent = map[currentStatusFilter] || 'Reservation Records';
            }
        }

        function closeRecordsModal() {
            const modal = document.getElementById('recordsListCardPanel');
            const backdrop = document.getElementById('recordsModalBackdrop');
            if (modal) modal.classList.remove('desktop-records-modal-open');
            if (backdrop) backdrop.classList.remove('active');

            // Also clear calendar date selection when closing the records modal
            if (calSelectedDate) {
                clearDateFilter();
            }
        }

        function executeSearch() {
            const query = document.getElementById('searchInput').value.trim();
            loadReservations(query);
        }

        // ===== QR CODE SCANNER FUNCTIONS =====
        let isProcessingScan = false;

        function openDesktopScannerModal() {
            closeWalkInModal();
            closeStaffProfileModal();
            closeStatusConfirmModal();

            const modal = document.getElementById('qrScannerPanel');
            const backdrop = document.getElementById('desktopScannerBackdrop');
            if (!modal) return;

            if (backdrop) backdrop.classList.add('active');
            modal.classList.add('desktop-modal-open');
            document.body.classList.add('desktop-scanner-active');

            // Automatically turn on camera scanner when opened
            startQRScanner();
        }

        function closeDesktopScannerModal() {
            const modal = document.getElementById('qrScannerPanel');
            const backdrop = document.getElementById('desktopScannerBackdrop');

            if (modal) modal.classList.remove('desktop-modal-open');
            if (backdrop) backdrop.classList.remove('active');
            document.body.classList.remove('desktop-scanner-active');

            if (isScannerRunning) {
                stopQRScanner();
            }
        }

        function toggleCameraScanner() {
            if (isScannerRunning) {
                stopQRScanner();
            } else {
                startQRScanner();
            }
        }

        function startQRScanner() {
            const placeholder = document.getElementById('scannerPlaceholder');
            const readerDiv = document.getElementById('qrReader');
            const startBtn = document.getElementById('startScanBtn');
            const stopBtn = document.getElementById('stopScanBtn');
            const aimOverlay = document.getElementById('qrAimOverlay');
            const statusLabel = document.getElementById('scannerStatusLabel');

            hideUploadPreview();
            if (placeholder) placeholder.style.display = 'none';
            if (readerDiv) readerDiv.style.display = 'block';
            if (startBtn) startBtn.style.display = 'none';
            if (stopBtn) stopBtn.style.display = 'inline-flex';
            if (aimOverlay) aimOverlay.classList.add('active');
            if (statusLabel) statusLabel.textContent = '🟢 Camera Active';

            // Destroy any previous scanner instance to avoid reuse errors
            if (html5QrScanner) {
                try { html5QrScanner.stop().catch(() => {}); } catch(e) {}
                html5QrScanner = null;
            }
            html5QrScanner = new Html5Qrcode("qrReader");

            isProcessingScan = false;

            html5QrScanner.start(
                { facingMode: "environment" },
                {
                    fps: 15,
                    qrbox: (viewfinderWidth, viewfinderHeight) => {
                        const minDim = Math.min(viewfinderWidth, viewfinderHeight);
                        const boxSize = Math.floor(minDim * 0.75);
                        const finalDim = Math.max(160, Math.min(280, boxSize));
                        return { width: finalDim, height: finalDim };
                    },
                    aspectRatio: 1.0,
                    disableFlip: false
                },
                (decodedText) => {
                    handleQRScanSuccess(decodedText);
                },
                (errorMessage) => {
                    // per-frame error (no match yet) — ignore
                }
            ).then(() => {
                isScannerRunning = true;
            }).catch(err => {
                console.error("Camera error:", err);
                // Reset UI back to idle state
                if (placeholder) placeholder.style.display = 'flex';
                if (readerDiv) readerDiv.style.display = 'none';
                if (startBtn) startBtn.style.display = 'inline-flex';
                if (stopBtn) stopBtn.style.display = 'none';
                if (aimOverlay) aimOverlay.classList.remove('active');
                if (statusLabel) statusLabel.textContent = 'Live Camera';
                showToast('⚠️ Camera unavailable. Use "Upload QR" instead.', 4000);
            });
        }

        function stopQRScanner() {
            isScannerRunning = false;
            const placeholder = document.getElementById('scannerPlaceholder');
            const reader = document.getElementById('qrReader');
            const startBtn = document.getElementById('startScanBtn');
            const stopBtn = document.getElementById('stopScanBtn');
            const aimOverlay = document.getElementById('qrAimOverlay');
            const statusLabel = document.getElementById('scannerStatusLabel');

            function _resetUI() {
                if (placeholder) placeholder.style.display = 'flex';
                if (reader) reader.style.display = 'none';
                if (startBtn) startBtn.style.display = 'inline-flex';
                if (stopBtn) stopBtn.style.display = 'none';
                if (aimOverlay) aimOverlay.classList.remove('active');
                if (statusLabel) statusLabel.textContent = 'Live Camera';
            }

            if (html5QrScanner) {
                try {
                    html5QrScanner.stop().then(_resetUI).catch(_resetUI);
                } catch(e) { _resetUI(); }
            } else {
                _resetUI();
            }
        }

        function showUploadPreview(file) {
            const bar = document.getElementById('qrUploadPreview');
            const img = document.getElementById('qrUploadPreviewImg');
            const name = document.getElementById('qrUploadPreviewName');
            const status = document.getElementById('qrUploadPreviewStatus');
            if (!bar) return;
            if (img && file) {
                const reader = new FileReader();
                reader.onload = e => { img.src = e.target.result; };
                reader.readAsDataURL(file);
            }
            if (name) name.textContent = file ? file.name : '—';
            if (status) status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            bar.classList.add('visible');
        }

        function hideUploadPreview() {
            const bar = document.getElementById('qrUploadPreview');
            if (bar) bar.classList.remove('visible');
        }

        function setUploadPreviewStatus(html, isError) {
            const status = document.getElementById('qrUploadPreviewStatus');
            if (status) {
                status.innerHTML = html;
                status.style.color = isError ? '#ff5555' : '#ffd700';
            }
        }

        function findBestReservationMatch(reservations, searchCode) {
            if (!reservations || !reservations.length) return null;
            if (reservations.length === 1) return reservations[0];

            const target = String(searchCode).trim().toLowerCase();
            const cleanTarget = target.replace(/^(cgw|cg)-?/i, '').replace(/^#+/, '');
            
            // Extract trailing ID if legacy format "CG-260730-0015"
            let legacyId = '';
            const legacyMatch = target.match(/^(?:cgw|cg)-?\d{6}-(\d+)$/i);
            if (legacyMatch) {
                legacyId = String(parseInt(legacyMatch[1], 10));
            }

            // 1. Exact reservation_code match
            let found = reservations.find(r => (r.reservation_code || '').toLowerCase() === target);
            if (found) return found;

            // 2. Exact reservation_group_id match
            found = reservations.find(r => (r.reservation_group_id || '').toLowerCase() === target);
            if (found) return found;

            // 3. Exact ID / prefixed ID match
            found = reservations.find(r => {
                const rId = String(r.id || '');
                return rId === target ||
                       rId === cleanTarget ||
                       (legacyId && rId === legacyId) ||
                       ('cg-' + rId) === target ||
                       ('cgw-' + rId) === target ||
                       ('#' + rId) === target;
            });
            if (found) return found;

            // 4. Case-insensitive substring fallback
            found = reservations.find(r => {
                const rCode = (r.reservation_code || '').toLowerCase();
                const rGrp = (r.reservation_group_id || '').toLowerCase();
                return rCode.includes(target) || rGrp.includes(target);
            });
            if (found) return found;

            return reservations[0];
        }

        function handleQRScanSuccess(decodedText) {
            if (!decodedText || isProcessingScan) return;
            isProcessingScan = true;

            let code = String(decodedText).trim();

            // Extract from URL query params or path if scanned as a URL
            if (code.startsWith('http://') || code.startsWith('https://') || code.includes('://')) {
                try {
                    const parsedUrl = new URL(code);
                    const qCode = parsedUrl.searchParams.get('code') || 
                                  parsedUrl.searchParams.get('reservation_code') || 
                                  parsedUrl.searchParams.get('group_id') || 
                                  parsedUrl.searchParams.get('ref') || 
                                  parsedUrl.searchParams.get('id') || 
                                  parsedUrl.searchParams.get('order_id') || 
                                  parsedUrl.searchParams.get('res') || 
                                  parsedUrl.searchParams.get('order');
                    if (qCode) {
                        code = qCode.trim();
                    } else {
                        const segs = parsedUrl.pathname.split('/').filter(Boolean);
                        const lastSeg = segs.length ? segs[segs.length - 1] : '';
                        if (lastSeg && !lastSeg.includes('.php')) {
                            code = lastSeg.trim();
                        }
                    }
                } catch(e) {}
            } else if (code.includes('code=')) {
                try {
                    const match = code.match(/code=([^&]+)/);
                    if (match && match[1]) code = decodeURIComponent(match[1]).trim();
                } catch(e) {}
            } else if (code.includes('id=')) {
                try {
                    const match = code.match(/id=([^&]+)/);
                    if (match && match[1]) code = decodeURIComponent(match[1]).trim();
                } catch(e) {}
            }

            // Also check if JSON format was decoded
            if (code.startsWith('{') && code.endsWith('}')) {
                try {
                    const parsed = JSON.parse(code);
                    if (parsed.code || parsed.reservation_code || parsed.reservation_group_id || parsed.id) {
                        code = String(parsed.code || parsed.reservation_code || parsed.reservation_group_id || parsed.id).trim();
                    }
                } catch(e) {}
            }

            playBeep();
            if (navigator.vibrate) {
                try { navigator.vibrate([60, 40, 60]); } catch(e) {}
            }
            showToast(`Scanned: ${code}`, 3000);

            // Automatically turn off camera scanner when code is captured
            stopQRScanner();

            // Automatically close desktop scanner modal if open
            closeDesktopScannerModal();

            // Switch view modes on mobile so user sees the scanned results
            document.body.classList.remove('mobile-scanner-view-mode');
            if (window.innerWidth <= 900) {
                document.body.classList.add('mobile-search-view-mode');
                document.querySelectorAll('.nav-action-item').forEach(el => el.classList.remove('active'));
                const searchBtn = document.getElementById('navSearchBtn');
                if (searchBtn) searchBtn.classList.add('active');
            }

            // Put the scanned/uploaded code into the search input and show clear button
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.value = code;
                const clearBtn = document.getElementById('clearSearchBtn');
                if (clearBtn) clearBtn.style.display = 'block';
            }

            // Function to automatically open the reservation drawer on mobile
            function openReservationAutomatically(res) {
                if (!res) return;
                currentSelectedCode = res.reservation_code || res.reservation_group_id || String(res.id);
                
                // Cache into allReservations so all tabs and details keep it
                const existingIdx = allReservations.findIndex(r => 
                    (r.reservation_code && r.reservation_code === res.reservation_code) ||
                    (r.reservation_group_id && r.reservation_group_id === res.reservation_group_id) ||
                    String(r.id) === String(res.id)
                );
                if (existingIdx >= 0) {
                    allReservations[existingIdx] = res;
                } else {
                    allReservations.unshift(res);
                }

                renderReservationList([res]);
                selectReservation(res);
                if (window.innerWidth <= 900) {
                    openDrawer();
                    setTimeout(() => { openDrawer(); }, 60);
                    setTimeout(() => { openDrawer(); }, 180);
                }
            }

            // Automatically search for this code and immediately display detail card / drawer
            fetch(`api_get_reservation.php?code=${encodeURIComponent(code)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.reservations && data.reservations.length > 0) {
                        const targetRes = findBestReservationMatch(data.reservations, code);
                        openReservationAutomatically(targetRes);
                    } else {
                        // Secondary attempt via search parameter
                        fetch(`api_get_reservation.php?search=${encodeURIComponent(code)}&status=all`)
                            .then(r2 => r2.json())
                            .then(data2 => {
                                if (data2.success && data2.reservations && data2.reservations.length > 0) {
                                    const targetRes = findBestReservationMatch(data2.reservations, code);
                                    openReservationAutomatically(targetRes);
                                } else {
                                    // Fallback search in all records currently in memory
                                    const clean = code.replace(/^(CGW|CG)-?/i, '').replace(/^#+/, '').toLowerCase();
                                    const foundLocal = (allReservations || []).find(r => {
                                        const rCode = (r.reservation_code || '').toLowerCase();
                                        const rGrp = (r.reservation_group_id || '').toLowerCase();
                                        const rId = String(r.id || '');
                                        return rCode === code.toLowerCase() || 
                                               rGrp === code.toLowerCase() || 
                                               rId === code || 
                                               rId === clean ||
                                               ('cg-' + rId) === code.toLowerCase() ||
                                               ('#' + rId) === code.toLowerCase();
                                    });
                                    if (foundLocal) {
                                        openReservationAutomatically(foundLocal);
                                    } else {
                                        handleRealtimeSearch(code);
                                        showToast(`No matching reservation found for "${code}"`, 3500);
                                    }
                                }
                                setTimeout(() => { isProcessingScan = false; }, 1200);
                            })
                            .catch(() => {
                                handleRealtimeSearch(code);
                                setTimeout(() => { isProcessingScan = false; }, 1200);
                            });
                        return;
                    }
                    setTimeout(() => { isProcessingScan = false; }, 1200);
                }).catch(err => {
                    console.error("QR lookup error:", err);
                    handleRealtimeSearch(code);
                    setTimeout(() => { isProcessingScan = false; }, 1200);
                });
        }

        function handleQRFileUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            e.target.value = ''; // reset so same file can be re-selected

            // Show upload preview immediately
            showUploadPreview(file);

            // Stop any running camera scanner before file scan
            if (isScannerRunning) {
                stopQRScanner();
            }

            // Switch to scanner view mode on mobile so the upload preview is visible
            if (window.innerWidth <= 900) {
                document.body.classList.remove('mobile-search-view-mode');
                document.body.classList.add('mobile-scanner-view-mode');
                document.querySelectorAll('.nav-action-item').forEach(el => el.classList.remove('active'));
            }

            // Use a dedicated offscreen element so scanFile always works (independent of camera state)
            const tempId = 'qrScanFileHelper';
            let tempDiv = document.getElementById(tempId);
            if (!tempDiv) {
                tempDiv = document.createElement('div');
                tempDiv.id = tempId;
                tempDiv.style.cssText = 'position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;visibility:hidden;';
                document.body.appendChild(tempDiv);
            }

            // Always create a fresh instance for file scanning
            const fileScanner = new Html5Qrcode(tempId);

            fileScanner.scanFile(file, true)
                .then(decodedText => {
                    fileScanner.clear().catch(() => {});
                    setUploadPreviewStatus('<i class="fas fa-check-circle"></i> QR Detected!', false);
                    setTimeout(() => {
                        hideUploadPreview();
                        handleQRScanSuccess(decodedText);
                    }, 700);
                })
                .catch(err => {
                    console.warn('QR file scan error:', err);
                    fileScanner.clear().catch(() => {});
                    setUploadPreviewStatus('<i class="fas fa-times-circle"></i> No QR found in image', true);
                    setTimeout(() => {
                        hideUploadPreview();
                        // Reset scanner view to idle on mobile
                        if (window.innerWidth <= 900) {
                            document.body.classList.remove('mobile-scanner-view-mode');
                            document.querySelectorAll('.nav-action-item').forEach(el => el.classList.remove('active'));
                            const homeBtn = document.getElementById('navHomeBtn');
                            if (homeBtn) homeBtn.classList.add('active');
                        }
                    }, 2000);
                    showToast('⚠️ No QR code found. Try a clearer image.', 4000);
                });
        }

        // ===== QR MODAL =====
        function openQRModal(code) {
            const modal = document.getElementById('qrModal');
            const container = document.getElementById('modalQRImageContainer');
            const textEl = document.getElementById('modalQRText');

            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(code)}`;
            container.innerHTML = `<img src="${qrUrl}" alt="QR Code" style="width:200px; height:200px; display:block;">`;
            textEl.textContent = code;
            modal.style.display = 'flex';
        }

        function closeQRModal() {
            document.getElementById('qrModal').style.display = 'none';
        }

        // ===== WALK-IN MANAGEMENT & CALENDAR DATE PICKER =====
        const allGownsData = <?php echo json_encode($allGowns); ?>;
        const categorySizeLabelsMap = <?php echo json_encode($categorySizeLabelsMap); ?>;
        let currentRentalDurationDays = <?php echo intval($rentalDurationDays); ?>;
        let pickerCurrentCategory = '';
        let pickerCategoriesList = <?php echo json_encode($allCategoryNames); ?>;
        window.latestGownsList = <?php echo json_encode($allGowns); ?>;

        let walkInUnavailableDates = [];
        let walkInCalendarCurrentDate = new Date();
        let walkInActiveDateFieldId = null;
        let walkInEarliestBuyDate = null;
        let currentWalkInProductId = 0;
        let currentWalkInStockInfo = null;

        function formatLocalDateStr(d) {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function parseLocalDate(dateStr) {
            if (!dateStr) return null;
            const parts = dateStr.split('-');
            if (parts.length === 3) {
                return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            }
            return new Date(dateStr);
        }

        function formatWalkInPhone(input) {
            let val = input.value.replace(/[^0-9]/g, '');
            if (!val.startsWith('09')) {
                if (val.startsWith('9')) {
                    val = '0' + val;
                } else if (val === '0' || val === '') {
                    val = '09';
                } else {
                    val = '09' + val.replace(/^0+/, '');
                }
            }
            input.value = val.slice(0, 11);
        }

        function stepWalkInQuantity(delta) {
            const qtyInput = document.getElementById('walkInQuantity');
            if (!qtyInput) return;
            const currentVal = parseInt(qtyInput.value) || 1;
            const minVal = parseInt(qtyInput.min) || 1;
            const maxVal = parseInt(qtyInput.max) || 1;
            let newVal = currentVal + delta;
            if (newVal < minVal) newVal = minVal;
            if (newVal > maxVal) newVal = maxVal;
            qtyInput.value = newVal;
            updateWalkInQtyButtonStates();
            recalcWalkInTotals();
        }

        function updateWalkInQtyButtonStates() {
            const qtyInput = document.getElementById('walkInQuantity');
            const minusBtn = document.getElementById('walkInQtyMinusBtn');
            const plusBtn = document.getElementById('walkInQtyPlusBtn');
            if (!qtyInput || !minusBtn || !plusBtn) return;
            const currentVal = parseInt(qtyInput.value) || 1;
            const minVal = parseInt(qtyInput.min) || 1;
            const maxVal = parseInt(qtyInput.max) || 1;

            if (currentVal <= minVal) {
                minusBtn.disabled = true;
                minusBtn.style.opacity = '0.4';
                minusBtn.style.cursor = 'not-allowed';
            } else {
                minusBtn.disabled = false;
                minusBtn.style.opacity = '1';
                minusBtn.style.cursor = 'pointer';
            }

            if (currentVal >= maxVal) {
                plusBtn.disabled = true;
                plusBtn.style.opacity = '0.4';
                plusBtn.style.cursor = 'not-allowed';
            } else {
                plusBtn.disabled = false;
                plusBtn.style.opacity = '1';
                plusBtn.style.cursor = 'pointer';
            }
        }

        function navToggleWalkIn() {
            const modal = document.getElementById('walkInModal');
            const isOpen = modal && modal.classList.contains('slide-panel-open');
            if (isOpen) {
                closeWalkInModal();
            } else {
                openWalkInModal();
            }
        }

        function openWalkInModal() {
            // Stop camera if running on mobile
            if (isScannerRunning) {
                stopQRScanner();
            }

            // Close other modals and drawer
            closeDesktopScannerModal();
            closeStaffProfileModal();
            closeDrawer();

            // Set active state on bottom nav Walk-In
            document.querySelectorAll('.nav-action-item').forEach(el => el.classList.remove('active'));
            const walkInBtn = document.getElementById('navWalkInBtn');
            if (walkInBtn) walkInBtn.classList.add('active');

            const form = document.getElementById('walkInForm');
            if (form) form.reset();

            const phoneInput = document.getElementById('walkInPhone');
            if (phoneInput) phoneInput.value = '09';

            const qtyInput = document.getElementById('walkInQuantity');
            if (qtyInput) qtyInput.value = '1';
            updateWalkInQtyButtonStates();

            const reserveDateInput = document.getElementById('walkInReserveDate');
            const returnDateInput = document.getElementById('walkInReturnDate');
            const todayStr = formatLocalDateStr(new Date());

            if (reserveDateInput) {
                reserveDateInput.value = todayStr;
                const autoReturnObj = new Date();
                autoReturnObj.setDate(autoReturnObj.getDate() + (currentRentalDurationDays || 10));
                if (returnDateInput) returnDateInput.value = formatLocalDateStr(autoReturnObj);
            }

            const prodSelect = document.getElementById('walkInProductId');
            if (prodSelect) {
                prodSelect.value = '';
                handleWalkInProductChange(prodSelect);
            } else {
                recalcWalkInTotals();
            }

            document.getElementById('walkInModal').classList.add('slide-panel-open');
            document.getElementById('walkInModalBackdrop').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeWalkInModal() {
            const modal = document.getElementById('walkInModal');
            const backdrop = document.getElementById('walkInModalBackdrop');
            if (modal) modal.classList.remove('slide-panel-open');
            if (backdrop) backdrop.classList.remove('active');
            document.body.style.overflow = '';

            const walkInBtn = document.getElementById('navWalkInBtn');
            if (walkInBtn) walkInBtn.classList.remove('active');

            // Restore previous active tab state (Search vs Home)
            if (document.body.classList.contains('mobile-search-view-mode')) {
                const searchBtn = document.getElementById('navSearchBtn');
                if (searchBtn) searchBtn.classList.add('active');
            } else if (!document.body.classList.contains('mobile-scanner-view-mode')) {
                const homeBtn = document.getElementById('navHomeBtn');
                if (homeBtn) homeBtn.classList.add('active');
            }
        }

        // Product Picker & Walk-In Selection Handlers
        function openWalkInProductPicker() {
            const modal = document.getElementById('walkInProductPickerModal');
            if (!modal) return;
            modal.classList.add('active');
            const bNav = document.getElementById('mobileBottomNav');
            if (bNav) bNav.style.display = 'none';

            // Fetch fresh gowns from server to ensure real-time sync with database
            fetch('index.php?action=get_all_gowns_picker')
                .then(r => r.json())
                .then(data => {
                    if (data && data.success && Array.isArray(data.gowns)) {
                        window.latestGownsList = data.gowns;
                        if (Array.isArray(data.categories) && data.categories.length > 0) {
                            pickerCategoriesList = data.categories;
                        }
                        syncGownsSelectOptions(data.gowns);
                    }
                    renderPickerUI();
                })
                .catch(err => {
                    console.error('Error loading gowns picker:', err);
                    renderPickerUI();
                });
        }

        function closeWalkInProductPicker(e) {
            if (e && e.target && e.target !== document.getElementById('walkInProductPickerModal') && !e.target.closest('.picker-back-btn')) return;
            const modal = document.getElementById('walkInProductPickerModal');
            if (modal) modal.classList.remove('active');
            const bNav = document.getElementById('mobileBottomNav');
            if (bNav && window.innerWidth <= 900) bNav.style.display = '';
        }

        function syncGownsSelectOptions(gowns) {
            const select = document.getElementById('walkInProductId');
            if (!select) return;
            const currentVal = select.value;
            
            let html = '<option value="">-- Choose Gown / Product --</option>';
            gowns.forEach(g => {
                const stock = parseInt(g.stock) || 0;
                const disc = parseFloat(g.discount) || 0;
                const origPrice = parseFloat(g.price) || 0;
                const finalPrice = disc > 0 ? origPrice * (1 - disc / 100) : origPrice;
                
                html += `
                    <option value="${g.id}"
                        data-price="${finalPrice}"
                        data-orig-price="${origPrice}"
                        data-discount="${disc}"
                        data-category="${escapeHtml(g.category || '')}"
                        data-stock="${stock}"
                        data-color="${escapeHtml(g.color || '')}"
                        data-image="${escapeHtml(g.image_path || g.image_path2 || g.image_path3 || '')}"
                        data-model="${escapeHtml(g.model_path || '')}"
                        data-name="${escapeHtml(g.name || '')}">
                        ${escapeHtml(g.name)} — ₱${finalPrice.toFixed(2)} (Stock: ${stock})
                    </option>
                `;
            });
            select.innerHTML = html;
            if (currentVal) {
                select.value = currentVal;
            }
        }

        function setPickerCategory(catName, clickedEl) {
            pickerCurrentCategory = catName || '';
            const catSelect = document.getElementById('pickerCategoryFilter');
            if (catSelect) catSelect.value = pickerCurrentCategory;
            
            let targetPill = clickedEl || null;
            document.querySelectorAll('.picker-pill').forEach(pill => {
                const pCat = pill.getAttribute('data-category') || '';
                if (pCat.toLowerCase() === pickerCurrentCategory.toLowerCase()) {
                    pill.classList.add('active');
                    if (!targetPill) targetPill = pill;
                } else {
                    pill.classList.remove('active');
                }
            });

            // Auto-slide active pill to the center of the pills bar
            if (targetPill) {
                targetPill.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
            
            filterPickerProducts();
        }

        function clearPickerSearch() {
            const input = document.getElementById('pickerSearchInput');
            const clearBtn = document.getElementById('pickerClearSearchBtn');
            if (input) input.value = '';
            if (clearBtn) clearBtn.style.display = 'none';
            filterPickerProducts();
        }

        function renderPickerUI() {
            const gowns = window.latestGownsList || allGownsData || [];
            const catSelect = document.getElementById('pickerCategoryFilter');
            const pillsContainer = document.getElementById('pickerCategoryPills');

            const distinctCats = [];
            pickerCategoriesList.forEach(c => {
                if (!distinctCats.includes(c)) distinctCats.push(c);
            });
            gowns.forEach(g => {
                if (g.category && !distinctCats.includes(g.category)) {
                    distinctCats.push(g.category);
                }
            });

            if (catSelect) {
                let catOptions = '<option value="">All Categories</option>';
                distinctCats.forEach(c => {
                    catOptions += `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`;
                });
                catSelect.innerHTML = catOptions;
                catSelect.value = pickerCurrentCategory;
            }

            if (pillsContainer) {
                let pillsHtml = `<button type="button" class="picker-pill ${pickerCurrentCategory === '' ? 'active' : ''}" data-category="" onclick="setPickerCategory('', this)">All (${gowns.length})</button>`;
                distinctCats.forEach(c => {
                    const count = gowns.filter(g => (g.category || '').toLowerCase() === c.toLowerCase()).length;
                    pillsHtml += `<button type="button" class="picker-pill ${pickerCurrentCategory.toLowerCase() === c.toLowerCase() ? 'active' : ''}" data-category="${escapeHtml(c)}" onclick="setPickerCategory('${escapeHtml(c)}', this)">${escapeHtml(c)} (${count})</button>`;
                });
                pillsContainer.innerHTML = pillsHtml;
            }

            filterPickerProducts();
        }

        function filterPickerProducts() {
            const gowns = window.latestGownsList || allGownsData || [];
            const searchInput = document.getElementById('pickerSearchInput');
            const searchTerm = (searchInput ? searchInput.value : '').toLowerCase().trim();
            const clearBtn = document.getElementById('pickerClearSearchBtn');
            if (clearBtn) clearBtn.style.display = searchTerm ? 'block' : 'none';

            const catSelect = document.getElementById('pickerCategoryFilter');
            const selectedCategory = catSelect ? catSelect.value.trim() : pickerCurrentCategory;
            pickerCurrentCategory = selectedCategory;

            document.querySelectorAll('.picker-pill').forEach(pill => {
                const pCat = pill.getAttribute('data-category');
                if (pCat === selectedCategory) {
                    pill.classList.add('active');
                } else {
                    pill.classList.remove('active');
                }
            });

            const select = document.getElementById('walkInProductId');
            const selectedPid = select ? parseInt(select.value) || 0 : 0;

            const grouped = {};
            const categoriesOrder = [];
            pickerCategoriesList.forEach(c => {
                if (!categoriesOrder.includes(c)) categoriesOrder.push(c);
            });
            gowns.forEach(g => {
                const c = g.category || 'General';
                if (!categoriesOrder.includes(c)) {
                    categoriesOrder.push(c);
                }
                if (!grouped[c]) {
                    grouped[c] = [];
                }
                grouped[c].push(g);
            });

            const grid = document.getElementById('pickerProductGrid');
            if (!grid) return;

            let totalVisibleGowns = 0;
            let html = '';

            categoriesOrder.forEach(category => {
                if (selectedCategory && category.toLowerCase() !== selectedCategory.toLowerCase()) {
                    return;
                }

                const categoryGowns = grouped[category] || [];
                const matchingGowns = categoryGowns.filter(g => {
                    if (!searchTerm) return true;
                    const nameMatch = (g.name || '').toLowerCase().includes(searchTerm);
                    const colorMatch = (g.color || '').toLowerCase().includes(searchTerm);
                    const catMatch = (g.category || '').toLowerCase().includes(searchTerm);
                    return nameMatch || colorMatch || catMatch;
                });

                if (matchingGowns.length === 0) return;

                totalVisibleGowns += matchingGowns.length;

                html += `
                    <div class="picker-category-header" data-category="${escapeHtml(category)}">
                        <h4 class="picker-category-title">${escapeHtml(category)}</h4>
                        <div class="picker-category-line"></div>
                    </div>
                    <div class="picker-gowns-grid">
                `;

                matchingGowns.forEach(g => {
                    const pid = parseInt(g.id);
                    const stock = parseInt(g.stock) || 0;
                    const isOutOfStock = stock <= 0;
                    const isSelected = pid === selectedPid;
                    const disc = parseFloat(g.discount) || 0;
                    const origPrice = parseFloat(g.price) || 0;
                    const finalPrice = disc > 0 ? origPrice * (1 - disc / 100) : origPrice;

                    let imgPath = g.image_path || g.image_path2 || g.image_path3 || '';
                    if (imgPath && !imgPath.startsWith('http') && !imgPath.startsWith('/') && !imgPath.startsWith('../')) {
                        imgPath = '../' + imgPath;
                    }

                    let stockBadgeClass = 'instock';
                    let stockBadgeText = `Stock: ${stock}`;
                    if (isOutOfStock) {
                        stockBadgeClass = 'outofstock';
                        stockBadgeText = 'Out of Stock';
                    } else if (stock <= 2) {
                        stockBadgeClass = 'lowstock';
                        stockBadgeText = `Low Stock: ${stock}`;
                    }

                    html += `
                        <div class="picker-gown-card ${isSelected ? 'selected' : ''} ${isOutOfStock ? 'out-of-stock' : ''}" 
                             onclick="${isOutOfStock ? `alert('${escapeHtml(g.name)} is currently out of stock.')` : `selectProductFromPicker(${pid})`}"
                             title="${isOutOfStock ? 'Out of Stock - Cannot be selected' : 'Click to select ' + escapeHtml(g.name)}">
                            
                            <div class="picker-gown-img-wrap">
                                ${imgPath ? `<img src="${imgPath}" alt="${escapeHtml(g.name)}" onerror="this.onerror=null; this.parentElement.innerHTML='<span style=\\'font-size:3rem;\\'>👗</span>';">` : `<span style="font-size: 3rem;">👗</span>`}
                                ${disc > 0 ? `<span class="picker-discount-badge">-${Math.round(disc)}%</span>` : ''}
                                <span class="picker-stock-badge ${stockBadgeClass}">${stockBadgeText}</span>
                            </div>

                            <div class="picker-gown-info">
                                <h5 class="picker-gown-name" title="${escapeHtml(g.name)}">${escapeHtml(g.name)}</h5>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="picker-gown-category-tag">${escapeHtml(g.category || 'Standard')}</span>
                                    ${g.color ? `<span style="font-size: 0.7rem; color: #aaa;">${escapeHtml(g.color)}</span>` : ''}
                                </div>
                                <div class="picker-gown-price-row">
                                    <span class="picker-gown-price">₱${finalPrice.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                    ${disc > 0 ? `<span class="picker-gown-price-orig">₱${origPrice.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>` : ''}
                                </div>
                                <button type="button" class="picker-gown-select-btn" ${isOutOfStock ? 'disabled' : ''}>
                                    ${isSelected ? '<i class="fas fa-check-circle"></i> Selected' : (isOutOfStock ? '<i class="fas fa-ban"></i> Out of Stock' : '<i class="fas fa-hand-pointer"></i> Choose')}
                                </button>
                            </div>
                        </div>
                    `;
                });

                html += `</div>`;
            });

            if (totalVisibleGowns === 0) {
                html = `
                    <div style="text-align: center; padding: 40px 20px; color: #aaa;">
                        <i class="fas fa-tshirt" style="font-size: 40px; margin-bottom: 12px; color: #7c6300;"></i>
                        <h4 style="color: #eee; margin: 0 0 6px 0;">No Products Found</h4>
                        <p style="font-size: 0.82rem; margin: 0;">Try adjusting your search query or selecting a different category.</p>
                    </div>
                `;
            }

            grid.innerHTML = html;

            const countDisplay = document.getElementById('pickerCountDisplay');
            if (countDisplay) {
                countDisplay.textContent = `Showing ${totalVisibleGowns} product${totalVisibleGowns === 1 ? '' : 's'}${selectedCategory ? ' in ' + selectedCategory : ''}`;
            }
        }

        function selectProductFromPicker(productId) {
            const select = document.getElementById('walkInProductId');
            if (!select) return;
            select.value = String(productId);
            handleWalkInProductChange(select);
            closeWalkInProductPicker();
        }

        function handleWalkInProductChange(selectEl) {
            const selectedOption = selectEl ? selectEl.options[selectEl.selectedIndex] : null;
            const selectedPid = selectEl ? parseInt(selectEl.value) || 0 : 0;
            
            const triggerBox = document.getElementById('walkInProductSelectTrigger');
            const selectedBox = document.getElementById('walkInSelectedProductCard');

            if (!selectedPid || !selectedOption || !selectedOption.value) {
                if (triggerBox) triggerBox.style.display = 'flex';
                if (selectedBox) selectedBox.style.display = 'none';
                recalcWalkInTotals();
                return;
            }

            if (triggerBox) triggerBox.style.display = 'none';
            if (selectedBox) selectedBox.style.display = 'block';

            const prodName = selectedOption.getAttribute('data-name') || selectedOption.textContent.split('—')[0].trim();
            const catName = (selectedOption.getAttribute('data-category') || '').trim();
            const colorName = (selectedOption.getAttribute('data-color') || '').trim();
            const unitPrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;
            const prodStock = parseInt(selectedOption.getAttribute('data-stock')) || 1;
            const imgPath = selectedOption.getAttribute('data-image') || '';

            const nameEl = document.getElementById('walkInSelectedProductName');
            const catEl = document.getElementById('walkInSelectedProductCategory');
            const colorEl = document.getElementById('walkInSelectedProductColor');
            const priceEl = document.getElementById('walkInSelectedProductPrice');
            const stockEl = document.getElementById('walkInSelectedProductStock');
            const imgWrap = document.getElementById('walkInSelectedProductImgWrap');

            if (nameEl) nameEl.textContent = prodName;
            if (catEl) catEl.textContent = catName || 'General';
            if (colorEl) colorEl.textContent = colorName ? `• ${colorName}` : '';
            if (priceEl) priceEl.textContent = `₱${unitPrice.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            if (stockEl) {
                stockEl.textContent = `(Stock: ${prodStock})`;
                stockEl.style.color = prodStock <= 0 ? '#f44336' : (prodStock <= 2 ? '#ff9800' : '#6cd488');
            }
            if (imgWrap) {
                if (imgPath) {
                    const cleanPath = imgPath.startsWith('http') || imgPath.startsWith('/') || imgPath.startsWith('../') ? imgPath : ('../' + imgPath);
                    imgWrap.innerHTML = `<img src="${cleanPath}" alt="${escapeHtml(prodName)}" style="width:100%; height:100%; object-fit:contain;" onerror="this.onerror=null; this.parentElement.innerHTML='<span style=\\'font-size:1.5rem;\\'>👗</span>';">`;
                } else {
                    imgWrap.innerHTML = `<span style="font-size: 1.5rem;">👗</span>`;
                }
            }

            // Update quantity input max stock & stock label
            const qtyInput = document.getElementById('walkInQuantity');
            const stockLabel = document.getElementById('walkInStockLabel');
            if (qtyInput) {
                qtyInput.max = prodStock;
                if (parseInt(qtyInput.value) > prodStock) {
                    qtyInput.value = prodStock;
                }
                if (parseInt(qtyInput.value) < 1) {
                    qtyInput.value = 1;
                }
            }
            if (stockLabel) {
                stockLabel.textContent = `(Stock: ${prodStock})`;
            }
            updateWalkInQtyButtonStates();
            
            const catBadge = document.getElementById('walkInSizeCategoryBadge');
            if (catBadge) {
                catBadge.textContent = catName ? `Category: ${catName}` : 'Category: Standard';
            }

            // Fetch unavailable dates and check buy availability for the newly selected gown
            if (selectedPid > 0) {
                currentWalkInProductId = selectedPid;
                fetchWalkInUnavailableDates(selectedPid, function() {
                    checkWalkInBuyAvailability(function() {
                        if (document.getElementById('walkInCalendarModalOverlay').classList.contains('active')) {
                            renderWalkInCalendarModalGrid();
                        }
                    });
                });
            }

            const sizesContainer = document.getElementById('walkInSizesFieldsContainer');
            if (sizesContainer) {
                const catKey = catName.toLowerCase();
                const labels = categorySizeLabelsMap[catKey] || [];
                if (labels.length > 0) {
                    let html = '';
                    labels.forEach(lblObj => {
                        html += `
                            <div>
                                <label style="display: block; font-size: 0.72rem; color: #ffd700; margin-bottom: 3px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(lblObj.label)}">${escapeHtml(lblObj.label)}</label>
                                <input type="text" name="size_${lblObj.index}" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #ffd700; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                            </div>
                        `;
                    });
                    sizesContainer.innerHTML = html;
                } else {
                    sizesContainer.innerHTML = `
                        <div>
                            <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Bust</label>
                            <input type="text" name="size_1" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Waist</label>
                            <input type="text" name="size_2" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Hips</label>
                            <input type="text" name="size_3" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.72rem; color: #aaa; margin-bottom: 3px;">Height/Other</label>
                            <input type="text" name="size_4" placeholder="0" oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="width: 100%; box-sizing: border-box; background: #16181d; border: 1px solid #383b45; border-radius: 6px; color: #fff; padding: 6px 8px; font-size: 0.82rem;">
                        </div>
                    `;
                }
            }

            recalcWalkInTotals();
        }

        function handleWalkInTypeChange(selectEl) {
            const isBuy = selectEl.value === 'buy';
            const returnDateWrap = document.getElementById('walkInReturnDateWrap');
            const returnDateInput = document.getElementById('walkInReturnDate');
            const reserveDateLabel = document.getElementById('walkInReserveDateLabel');

            if (returnDateWrap) {
                returnDateWrap.style.display = isBuy ? 'none' : 'block';
            }
            if (returnDateInput) {
                returnDateInput.required = !isBuy;
            }
            if (reserveDateLabel) {
                reserveDateLabel.textContent = isBuy ? 'Purchase / Claim Date' : 'Reserve Date';
            }

            checkWalkInBuyAvailability();
            recalcWalkInTotals();
        }

        function selectWalkInPaymentOption(plan) {
            const hiddenInput = document.getElementById('walkInPaymentOption');
            if (hiddenInput) hiddenInput.value = plan;

            const fullItem = document.getElementById('walkInFullPaymentOption');
            const firstItem = document.getElementById('walkInFirstPaymentOption');
            const radioFull = document.getElementById('walkInRadioFull');
            const radioFirst = document.getElementById('walkInRadioFirst');
            const fullTitle = document.getElementById('walkInFullTitle');
            const firstTitle = document.getElementById('walkInFirstTitle');

            if (plan === 'first_payment') {
                if (fullItem) {
                    fullItem.classList.remove('active');
                    fullItem.style.borderColor = '#38383f';
                    fullItem.style.background = '#222224';
                }
                if (radioFull) {
                    radioFull.style.borderColor = '#555';
                    radioFull.style.background = 'transparent';
                    radioFull.style.color = 'transparent';
                }
                if (fullTitle) fullTitle.style.color = '#fff';

                if (firstItem) {
                    firstItem.classList.add('active');
                    firstItem.style.borderColor = '#ffd700';
                    firstItem.style.background = 'rgba(255, 215, 0, 0.08)';
                }
                if (radioFirst) {
                    radioFirst.style.borderColor = '#ffd700';
                    radioFirst.style.background = '#ffd700';
                    radioFirst.style.color = '#000';
                }
                if (firstTitle) firstTitle.style.color = '#ffd700';
            } else {
                if (firstItem) {
                    firstItem.classList.remove('active');
                    firstItem.style.borderColor = '#38383f';
                    firstItem.style.background = '#222224';
                }
                if (radioFirst) {
                    radioFirst.style.borderColor = '#555';
                    radioFirst.style.background = 'transparent';
                    radioFirst.style.color = 'transparent';
                }
                if (firstTitle) firstTitle.style.color = '#fff';

                if (fullItem) {
                    fullItem.classList.add('active');
                    fullItem.style.borderColor = '#ffd700';
                    fullItem.style.background = 'rgba(255, 215, 0, 0.08)';
                }
                if (radioFull) {
                    radioFull.style.borderColor = '#ffd700';
                    radioFull.style.background = '#ffd700';
                    radioFull.style.color = '#000';
                }
                if (fullTitle) fullTitle.style.color = '#ffd700';
            }

            recalcWalkInTotals();
        }

        function handleWalkInPaymentOptionChange(selectEl) {
            if (selectEl) selectWalkInPaymentOption(selectEl.value);
            else recalcWalkInTotals();
        }

        function recalcWalkInTotals() {
            const prodSelect = document.getElementById('walkInProductId');
            const qtyInput = document.getElementById('walkInQuantity');
            const paymentOptionSelect = document.getElementById('walkInPaymentOption');

            if (!prodSelect) return;
            const selectedOption = prodSelect.options[prodSelect.selectedIndex];
            const price = parseFloat(selectedOption ? selectedOption.getAttribute('data-price') : 0) || 0;
            const qty = Math.max(1, parseInt(qtyInput ? qtyInput.value : 1) || 1);
            const total = price * qty;

            const plan = paymentOptionSelect ? paymentOptionSelect.value : 'full';
            const isDownpayment = plan === 'first_payment';

            // Base deposit rule on product_reserve.php: First payment is ₱500 (or total if < 500)
            const firstPaymentAmount = Math.min(500, total);
            const downpayment = isDownpayment ? firstPaymentAmount : total;
            const balance = isDownpayment ? Math.max(0, total - firstPaymentAmount) : 0;

            const totalDisplay = document.getElementById('walkInTotalPriceDisplay');
            const totalInput = document.getElementById('walkInTotalPrice');
            const downDisplay = document.getElementById('walkInDownpaymentDisplay');
            const downInput = document.getElementById('walkInDownpayment');
            const balDisplay = document.getElementById('walkInBalanceDisplay');
            const balInput = document.getElementById('walkInBalance');
            const dueLabel = document.getElementById('walkInDueLabel');
            const fullPriceTag = document.getElementById('walkInFullPriceTag');
            const firstPriceTag = document.getElementById('walkInFirstPriceTag');
            const firstDesc = document.getElementById('walkInFirstPaymentDesc');

            if (totalDisplay) totalDisplay.textContent = `₱${total.toLocaleString('en-US')}`;
            if (totalInput) totalInput.value = total;
            if (downDisplay) downDisplay.textContent = `₱${downpayment.toLocaleString('en-US')}`;
            if (downInput) downInput.value = downpayment;
            if (balDisplay) balDisplay.textContent = `₱${balance.toLocaleString('en-US')}`;
            if (balInput) balInput.value = balance;
            if (dueLabel) dueLabel.textContent = isDownpayment ? 'Deposit Due Now / Balance' : 'Full Payment / Balance';

            if (fullPriceTag) fullPriceTag.textContent = `₱${total.toLocaleString('en-US')}`;
            if (firstPriceTag) firstPriceTag.textContent = `₱${firstPaymentAmount.toLocaleString('en-US')}`;
            if (firstDesc) {
                firstDesc.textContent = `Pay ₱${firstPaymentAmount.toLocaleString('en-US')} now to secure booking, balance of ₱${balance.toLocaleString('en-US')} due on pickup`;
            }
        }

        function fetchWalkInUnavailableDates(productId, callback) {
            if (!productId || productId <= 0) {
                walkInUnavailableDates = [];
                if (callback) callback();
                return;
            }
            fetch(`../get_unavailable_dates.php?product_id=${productId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        walkInUnavailableDates = data.dates || [];
                    } else {
                        walkInUnavailableDates = [];
                    }
                    if (callback) callback();
                })
                .catch(() => {
                    walkInUnavailableDates = [];
                    if (callback) callback();
                });
        }

        function checkWalkInBuyAvailability(callback) {
            const prodSelect = document.getElementById('walkInProductId');
            const statusSelect = document.getElementById('walkInProductStatus');
            const noticeEl = document.getElementById('walkInBuyScheduleNotice');
            const productId = prodSelect ? parseInt(prodSelect.value) || 0 : 0;
            const isBuy = statusSelect && statusSelect.value === 'buy';

            if (!isBuy || productId <= 0) {
                if (noticeEl) noticeEl.style.display = 'none';
                walkInEarliestBuyDate = null;
                currentWalkInStockInfo = null;
                if (callback) callback();
                return;
            }

            fetch(`../get_available_stock.php?product_id=${productId}&type=buy_check`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.success && data.earliest_buy_date) {
                        walkInEarliestBuyDate = data.earliest_buy_date;
                        currentWalkInStockInfo = data;
                        const rentalEndStr = data.rental_end_date || '--';
                        if (noticeEl) {
                            noticeEl.style.display = 'block';
                            const rentalEndEl = document.getElementById('walkInRentalEndText');
                            const buyDateEl = document.getElementById('walkInEarliestBuyDateText');
                            if (rentalEndEl) rentalEndEl.textContent = rentalEndStr;
                            if (buyDateEl) buyDateEl.textContent = walkInEarliestBuyDate;
                        }
                    } else {
                        walkInEarliestBuyDate = null;
                        currentWalkInStockInfo = null;
                        if (noticeEl) noticeEl.style.display = 'none';
                    }
                    if (callback) callback();
                })
                .catch(() => {
                    walkInEarliestBuyDate = null;
                    currentWalkInStockInfo = null;
                    if (noticeEl) noticeEl.style.display = 'none';
                    if (callback) callback();
                });
        }

        // ===== CALENDAR DATE PICKER MODAL =====
        function openWalkInCalendarModal(targetFieldId) {
            walkInActiveDateFieldId = targetFieldId;
            const currentVal = document.getElementById(targetFieldId) ? document.getElementById(targetFieldId).value : '';
            if (currentVal) {
                walkInCalendarCurrentDate = parseLocalDate(currentVal) || new Date();
            } else {
                walkInCalendarCurrentDate = new Date();
            }

            const prodSelect = document.getElementById('walkInProductId');
            const selectedPid = prodSelect ? parseInt(prodSelect.value) || 0 : 0;

            const overlay = document.getElementById('walkInCalendarModalOverlay');
            if (overlay) overlay.classList.add('active');

            if (selectedPid > 0) {
                currentWalkInProductId = selectedPid;
                fetchWalkInUnavailableDates(selectedPid, function() {
                    checkWalkInBuyAvailability(function() {
                        renderWalkInCalendarModalGrid();
                    });
                });
            } else {
                renderWalkInCalendarModalGrid();
            }
        }

        function closeWalkInCalendarModal() {
            document.getElementById('walkInCalendarModalOverlay').classList.remove('active');
            walkInActiveDateFieldId = null;
        }

        function changeWalkInCalendarMonth(delta) {
            walkInCalendarCurrentDate.setMonth(walkInCalendarCurrentDate.getMonth() + delta);
            renderWalkInCalendarModalGrid();
        }

        function renderWalkInCalendarModalGrid() {
            const grid = document.getElementById('walkInCalendarGrid');
            const title = document.getElementById('walkInCalendarMonthYear');
            if (!grid || !title) return;

            const year = walkInCalendarCurrentDate.getFullYear();
            const month = walkInCalendarCurrentDate.getMonth();
            const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            title.textContent = `${monthNames[month]} ${year}`;

            grid.innerHTML = `
                <div class="calendar-day-header">Sun</div>
                <div class="calendar-day-header">Mon</div>
                <div class="calendar-day-header">Tue</div>
                <div class="calendar-day-header">Wed</div>
                <div class="calendar-day-header">Thu</div>
                <div class="calendar-day-header">Fri</div>
                <div class="calendar-day-header">Sat</div>
            `;

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const prevMonthDays = new Date(year, month, 0).getDate();

            const today = formatLocalDateStr(new Date());
            const currentInput = walkInActiveDateFieldId ? document.getElementById(walkInActiveDateFieldId) : null;
            const selectedDate = currentInput ? currentInput.value : '';

            const typeSelect = document.getElementById('walkInProductStatus');
            const isBuy = typeSelect && typeSelect.value === 'buy';
            const earliestBuyDate = (isBuy && currentWalkInStockInfo && !currentWalkInStockInfo.can_buy_now)
                ? currentWalkInStockInfo.earliest_buy_date
                : null;

            let minDate = today;
            if (isBuy && earliestBuyDate && earliestBuyDate > minDate) {
                minDate = earliestBuyDate;
            } else if (walkInActiveDateFieldId === 'walkInReturnDate') {
                const resVal = document.getElementById('walkInReserveDate')?.value;
                if (resVal) {
                    minDate = resVal;
                }
            }

            // Leading empty cells
            for (let i = 0; i < firstDay; i++) {
                const prevDayNum = prevMonthDays - firstDay + 1 + i;
                const cell = document.createElement('div');
                cell.className = 'calendar-day other-month';
                cell.textContent = prevDayNum;
                grid.appendChild(cell);
            }

            // Days of current month
            for (let day = 1; day <= daysInMonth; day++) {
                const dateObj = new Date(year, month, day);
                const dateStr = formatLocalDateStr(dateObj);
                const dayElem = document.createElement('div');
                dayElem.className = 'calendar-day';
                dayElem.textContent = day;

                if (dateStr === today) {
                    dayElem.classList.add('today');
                }
                if (dateStr === selectedDate) {
                    dayElem.classList.add('selected');
                }

                // For buy: unavailable before earliestBuyDate or past dates
                // For rental: unavailable if in walkInUnavailableDates or past
                let isUnavailable = false;
                let reasonText = '';

                if (isBuy) {
                    if (earliestBuyDate && dateStr < earliestBuyDate) {
                        isUnavailable = true;
                        reasonText = `Active rental until ${currentWalkInStockInfo.latest_reservation_end_formatted || currentWalkInStockInfo.latest_return_date}. Available for purchase pickup on or after ${currentWalkInStockInfo.earliest_buy_date_formatted || earliestBuyDate}`;
                    } else if (dateStr < today) {
                        isUnavailable = true;
                        reasonText = 'Past date';
                    }
                } else {
                    if (walkInUnavailableDates.includes(dateStr)) {
                        isUnavailable = true;
                        reasonText = 'Fully booked for this product';
                    } else if (dateStr < minDate) {
                        isUnavailable = true;
                        reasonText = 'Past / earlier than reserve date';
                    }
                }

                if (isUnavailable) {
                    dayElem.classList.add('disabled');
                    dayElem.title = reasonText;
                } else {
                    dayElem.onclick = function() {
                        if (isBuy) {
                            const resInput = document.getElementById('walkInReserveDate');
                            const retInput = document.getElementById('walkInReturnDate');
                            if (resInput) resInput.value = dateStr;
                            if (retInput) retInput.value = dateStr;
                            closeWalkInCalendarModal();
                        } else {
                            if (walkInActiveDateFieldId === 'walkInReserveDate') {
                                const resInput = document.getElementById('walkInReserveDate');
                                if (resInput) resInput.value = dateStr;

                                // Calculate auto return date (+ rental duration days)
                                const autoReturnObj = new Date(dateStr + 'T00:00:00');
                                autoReturnObj.setDate(autoReturnObj.getDate() + (currentRentalDurationDays || 10));
                                const autoReturnStr = formatLocalDateStr(autoReturnObj);

                                const retInput = document.getElementById('walkInReturnDate');
                                if (retInput) retInput.value = autoReturnStr;

                                closeWalkInCalendarModal();
                            } else if (walkInActiveDateFieldId === 'walkInReturnDate') {
                                const retInput = document.getElementById('walkInReturnDate');
                                if (retInput) retInput.value = dateStr;
                                closeWalkInCalendarModal();
                            }
                        }
                    };
                }
                grid.appendChild(dayElem);
            }

            // Trailing cells
            const totalCells = firstDay + daysInMonth;
            const remaining = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
            for (let i = 1; i <= remaining; i++) {
                const cell = document.createElement('div');
                cell.className = 'calendar-day other-month';
                cell.textContent = i;
                grid.appendChild(cell);
            }
        }

        function closeWalkInCalendarModal(e) {
            if (e && e.target !== document.getElementById('walkInCalendarModalOverlay')) return;
            const overlay = document.getElementById('walkInCalendarModalOverlay');
            if (overlay) overlay.classList.remove('active');
            walkInActiveDateFieldId = null;
        }

        function submitWalkInReservation(event) {
            event.preventDefault();
            const form = document.getElementById('walkInForm');
            const submitBtn = document.getElementById('saveWalkInBtn');
            const originalBtnHtml = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            const formData = new FormData(form);

            fetch('save_walkin_reservation.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;

                if (data.success) {
                    closeWalkInModal();
                    showToast(`Walk-In order ${data.reservation_code} confirmed!`, 4000);
                    playBeep();
                    currentSelectedCode = data.reservation_code;
                    loadReservations();
                } else {
                    alert('Failed to save walk-in: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
                alert('Connection error saving walk-in reservation.');
                console.error(err);
            });
        }

        // ===== MOBILE BOTTOM NAVIGATION HANDLERS =====
        function navToHome() {
            // Automatically turn OFF camera when leaving scanner mode
            if (isScannerRunning) {
                stopQRScanner();
            }

            // Close walk-in modal if open
            closeWalkInModal();

            // Remove mobile search and scanner view modes
            document.body.classList.remove('mobile-search-view-mode');
            document.body.classList.remove('mobile-scanner-view-mode');

            // Set active state on bottom nav
            document.querySelectorAll('.nav-action-item').forEach(el => el.classList.remove('active'));
            const homeBtn = document.getElementById('navHomeBtn');
            if (homeBtn) homeBtn.classList.add('active');

            // Close profile modal and drawer if open
            closeStaffProfileModal();
            closeDrawer();

            // Scroll smoothly to top of workspace
            window.scrollTo({ top: 0, behavior: 'smooth' });
            loadReservations();
        }

        function navFocusSearch(focusInput = true) {
            // Automatically turn OFF camera when switching to search
            if (isScannerRunning) {
                stopQRScanner();
            }

            // Close walk-in modal if open
            closeWalkInModal();

            // Exit mobile scanner-only view mode
            document.body.classList.remove('mobile-scanner-view-mode');

            // Activate mobile search & records stretched view mode
            document.body.classList.add('mobile-search-view-mode');

            // Update bottom nav active state
            document.querySelectorAll('.nav-action-item').forEach(el => el.classList.remove('active'));
            const searchBtn = document.getElementById('navSearchBtn');
            if (searchBtn) searchBtn.classList.add('active');

            closeStaffProfileModal();
            if (focusInput) {
                closeDrawer();
            }

            const recordsPanel = document.getElementById('recordsListCardPanel');
            if (recordsPanel) {
                recordsPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            if (focusInput) {
                const input = document.getElementById('searchInput');
                if (input) {
                    setTimeout(() => {
                        input.focus();
                    }, 150);
                }
            }
        }

        function navTriggerScan() {
            // On laptop/desktop, open the scanner modal directly
            if (window.innerWidth > 900) {
                closeWalkInModal();
                closeStaffProfileModal();
                closeDrawer();
                openDesktopScannerModal();
                return;
            }

            // Provide haptic feedback if supported on mobile
            if (navigator.vibrate) {
                try { navigator.vibrate(40); } catch(e) {}
            }
            playBeep();

            // Reset scan processing state so new scan can be captured
            isProcessingScan = false;

            // Close walk-in modal, profile modal, drawer
            closeWalkInModal();
            closeStaffProfileModal();
            closeDrawer();

            // Remove search mode and activate scanner view mode
            document.body.classList.remove('mobile-search-view-mode');
            document.body.classList.add('mobile-scanner-view-mode');

            // Set scanner active state on bottom nav
            document.querySelectorAll('.nav-action-item').forEach(el => el.classList.remove('active'));

            // Open or scroll the live camera scanner into view
            const scannerPanel = document.getElementById('qrScannerPanel');
            if (scannerPanel) {
                scannerPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            // Automatically turn on camera scanner on tap
            startQRScanner();
        }

        function openStaffProfileModal() {
            // Close desktop scanner modal if open
            closeDesktopScannerModal();

            // Turn off camera if running
            if (isScannerRunning) {
                stopQRScanner();
            }

            // Close walk-in modal and details drawer
            closeWalkInModal();
            closeDrawer();
            closeStatusConfirmModal();

            document.querySelectorAll('.nav-action-item').forEach(el => el.classList.remove('active'));
            const profileBtn = document.getElementById('navProfileBtn');
            if (profileBtn) profileBtn.classList.add('active');

            const panel = document.getElementById('staffProfileModalOverlay');
            const backdrop = document.getElementById('staffProfileModalBackdrop');
            if (panel) panel.classList.add('slide-panel-open');
            if (backdrop) backdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeStaffProfileModal() {
            const panel = document.getElementById('staffProfileModalOverlay');
            const backdrop = document.getElementById('staffProfileModalBackdrop');
            if (panel) panel.classList.remove('slide-panel-open');
            if (backdrop) backdrop.classList.remove('active');
            document.body.style.overflow = '';

            // Restore previous active tab state (Search vs Home)
            if (document.body.classList.contains('mobile-search-view-mode')) {
                const searchBtn = document.getElementById('navSearchBtn');
                if (searchBtn) searchBtn.classList.add('active');
            } else if (!document.body.classList.contains('mobile-scanner-view-mode')) {
                const homeBtn = document.getElementById('navHomeBtn');
                if (homeBtn) homeBtn.classList.add('active');
            }
            const profileBtn = document.getElementById('navProfileBtn');
            if (profileBtn) profileBtn.classList.remove('active');
        }

        // Modals now only close when clicking the X button
        // document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { ... } });

        // Initialize on load
        window.addEventListener('DOMContentLoaded', () => {
            loadReservations();

            // Efficient polling: pause when tab is hidden, resume when visible
            let pollInterval = null;

            function startPolling() {
                if (pollInterval) return;
                pollInterval = setInterval(() => {
                    if (!document.getElementById('searchInput').value.trim()) {
                        loadReservations();
                    }
                }, 6000);
            }

            function stopPolling() {
                clearInterval(pollInterval);
                pollInterval = null;
            }

            startPolling();

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopPolling();
                } else {
                    loadReservations(); // immediate refresh on tab focus
                    startPolling();
                }
            });
        });
    </script>
</body>
</html>
