<?php
// Note: session_start() should be called in the including file, not here
// This component assumes session is already started

// Fetch pending reservations count for calendar icon badge & buy orders
$sidebarPendingCount = 0;
$sidebarPendingBuyCount = 0;
try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        require_once __DIR__ . '/../database/db_config.php';
        $sbDb = new Database();
        $sbConn = $sbDb->getConnection();
    } else {
        $sbConn = $conn;
    }
    if ($sbConn) {
        $sbRes = $sbConn->query("SELECT COUNT(*) as cnt FROM reserve_products WHERE order_status = 'pending' AND (product_status != 'buy' OR product_status IS NULL)");
        if ($sbRes && $sbRow = $sbRes->fetch_assoc()) {
            $sidebarPendingCount = (int)$sbRow['cnt'];
        }
        $sbBuyRes = $sbConn->query("SELECT COUNT(*) as cnt FROM reserve_products WHERE order_status = 'pending' AND product_status = 'buy'");
        if ($sbBuyRes && $sbBuyRow = $sbBuyRes->fetch_assoc()) {
            $sidebarPendingBuyCount = (int)$sbBuyRow['cnt'];
        }
    }
} catch (Throwable $e) {
    $sidebarPendingCount = 0;
    $sidebarPendingBuyCount = 0;
}
?>

<style>
.sidebar-icon {
    overflow: visible !important;
}
.sidebar-icon-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}
.sidebar-icon-wrap i {
    font-size: 19px;
    min-width: 20px;
    color: #d4af37;
    transition: color 0.25s ease;
}
.sidebar-badge-count {
    position: absolute;
    top: -7px;
    right: -10px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #ffffff;
    font-size: 9.5px;
    font-weight: 700;
    line-height: 1;
    min-width: 17px;
    height: 17px;
    padding: 0 4px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid #000000;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.55);
    z-index: 10;
    pointer-events: none;
    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    letter-spacing: -0.2px;
}

/* ── Mobile Page Title Bar: hidden by default on laptop / desktop ── */
#mobilePageTitle {
    display: none !important;
}
@media (max-width: 768px) {
    #mobilePageTitle {
        display: flex !important;
    }
}
</style>

<script>
function toggleDropdown(event, dropdownId) {
    event.preventDefault();
    event.stopPropagation();
    
    const dropdown = document.getElementById(dropdownId);
    const allDropdowns = document.querySelectorAll('.dropdown-menu');
    
    // Close all other dropdowns
    allDropdowns.forEach(d => {
        if (d !== dropdown) {
            d.classList.remove('show');
        }
    });
    
    // Toggle current dropdown
    dropdown.classList.toggle('show');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.sidebar-dropdown')) {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(d => d.classList.remove('show'));
    }
});
// ── Mobile sidebar toggle ──
function initMobileSidebar() {
    // Inject mobile CSS if not already present
    if (!document.getElementById('adminMobileCSS')) {
        var link = document.createElement('link');
        link.id = 'adminMobileCSS';
        link.rel = 'stylesheet';
        link.href = (window.location.pathname.indexOf('/admin/') !== -1 ? '' : 'admin/') + '../css/admin_mobile_css.css';
        // Normalise path: sidebar.php is always inside /admin/
        link.href = '../css/admin_mobile_css.css';
        document.head.appendChild(link);
    }

    // Inject hamburger button if not already present
    if (!document.getElementById('mobileMenuToggle')) {
        var btn = document.createElement('button');
        btn.id = 'mobileMenuToggle';
        btn.setAttribute('aria-label', 'Toggle navigation menu');
        btn.innerHTML = '<i class="fas fa-bars"></i>';
        document.body.insertBefore(btn, document.body.firstChild);
    }

    // Inject overlay if not already present
    if (!document.getElementById('sidebarOverlay')) {
        var overlay = document.createElement('div');
        overlay.id = 'sidebarOverlay';
        document.body.insertBefore(overlay, document.body.firstChild);
    }

    var toggle  = document.getElementById('mobileMenuToggle');
    var sidebar = document.querySelector('.sidebar');
    var ol      = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar.classList.add('mobile-open');
        toggle.classList.add('active');
        toggle.querySelector('i').className = 'fas fa-times';
        ol.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('mobile-open');
        toggle.classList.remove('active');
        toggle.querySelector('i').className = 'fas fa-bars';
        ol.classList.remove('show');
        document.body.style.overflow = '';
    }

    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        if (sidebar.classList.contains('mobile-open')) { closeSidebar(); } else { openSidebar(); }
    });

    ol.addEventListener('click', closeSidebar);

    // Close when a sidebar link is clicked (navigation)
    document.querySelectorAll('.sidebar .sidebar-icon, .sidebar .dropdown-item').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) { closeSidebar(); }
        });
    });

    // ── Mobile Page Title Bar ──
    if (!document.getElementById('mobilePageTitle')) {
        var pageLabels = {
            'dashboard.php'           : 'Dashboard',
            'calendar.php'            : 'Calendar',
            'buy.php'                 : 'Buy Orders',
            'gowns.php'               : 'Products',
            'categories.php'          : 'Categories',
            'ai_products.php'         : 'AI Products',
            'add_admin.php'           : 'Add Admin',
            'settings.php'            : 'Settings',
        };

        var pageName = window.location.pathname.split('/').pop().split('?')[0];
        var label    = pageLabels[pageName] || '';

        if (label) {
            var titleBar = document.createElement('div');
            titleBar.id  = 'mobilePageTitle';
            titleBar.textContent = label;
            document.body.insertBefore(titleBar, document.body.firstChild);
        }
    }
}

document.addEventListener('DOMContentLoaded', initMobileSidebar);

</script>

<div class="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <a href="dashboard.php" class="logo-link">
            <div class="logo-icon">
                <img src="../images/icon/JMB.png" alt="JMB Logo" class="sidebar-logo-img">
            </div>
            <span class="logo-text">ClosetGo</span>
        </a>
    </div>
    
    <a href="dashboard.php" class="sidebar-icon">
        <i class="fas fa-tachometer-alt"></i>
        <span class="sidebar-text">Dashboard</span>
    </a>
    <a href="calendar.php" class="sidebar-icon" title="Calendar">
        <span class="sidebar-icon-wrap">
            <i class="fas fa-calendar-alt"></i>
            <?php if ($sidebarPendingCount > 0): ?>
                <span class="sidebar-badge-count" id="sidebarCalPendingBadge"><?php echo $sidebarPendingCount > 99 ? '99+' : $sidebarPendingCount; ?></span>
            <?php endif; ?>
        </span>
        <span class="sidebar-text">Calendar</span>
    </a>
    <a href="gowns.php" class="sidebar-icon">
        <i class="fas fa-tshirt"></i>
        <span class="sidebar-text">Products</span>
    </a>
   
    <a href="ai_products.php" class="sidebar-icon">
        <i class="fas fa-robot"></i>
        <span class="sidebar-text">Skin-Based</span>
    </a>

    <a href="buy.php" class="sidebar-icon" title="Buy Orders">
        <span class="sidebar-icon-wrap">
            <i class="fas fa-shopping-bag"></i>
            <?php if ($sidebarPendingBuyCount > 0): ?>
                <span class="sidebar-badge-count" id="sidebarBuyPendingBadge"><?php echo $sidebarPendingBuyCount > 99 ? '99+' : $sidebarPendingBuyCount; ?></span>
            <?php endif; ?>
        </span>
        <span class="sidebar-text">Buy</span>
    </a>

    <a href="order_status_actions.php" class="sidebar-icon" title="Staff Order Status Actions">
        <i class="fas fa-user-shield"></i>
        <span class="sidebar-text">Audit Logs</span>
    </a>

    <a href="settings.php" class="sidebar-icon">
        <i class="fas fa-cog"></i>
        <span class="sidebar-text">Settings</span>
    </a>
 
    <a href="logout.php" class="sidebar-icon">
        <i class="fas fa-sign-out-alt"></i>
        <span class="sidebar-text">Logout</span>
    </a>
</div>
