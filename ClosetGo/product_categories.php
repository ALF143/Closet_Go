<?php
session_start();
require_once 'database/db_config.php';
$db = new Database();
$conn = $db->getConnection();

// Check if user is logged in
$isLoggedIn = false;
$currentUser = null;
$displayName = '';
$userProfileImage = 'images/icon/profile_logo.png';

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
$userEmail = isset($_SESSION['email']) ? $_SESSION['email'] : (isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '');

if ($userId > 0 || !empty($userEmail)) {
    if ($userId > 0) {
        $userStmt = $conn->prepare("SELECT id, email, first_name, middle_name, last_name, profile_image FROM system_users WHERE id = ?");
        $userStmt->bind_param("i", $userId);
    } else {
        $userStmt = $conn->prepare("SELECT id, email, first_name, middle_name, last_name, profile_image FROM system_users WHERE email = ?");
        $userStmt->bind_param("s", $userEmail);
    }
    
    if ($userStmt) {
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        if ($userResult && $userResult->num_rows > 0) {
            $currentUser = $userResult->fetch_assoc();
            $isLoggedIn = true;
            $_SESSION['user_id'] = $currentUser['id'];
            $_SESSION['email'] = $currentUser['email'];
            
            $fullName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
            if (!empty($fullName)) {
                $displayName = $fullName;
            } elseif (!empty($currentUser['first_name'])) {
                $displayName = $currentUser['first_name'];
            } elseif (!empty($currentUser['email'])) {
                $displayName = $currentUser['email'];
            } else {
                $displayName = '';
            }
            
            if (!empty($currentUser['profile_image'])) {
                $userProfileImage = $currentUser['profile_image'];
            }
        }
        $userStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Category Products - ClosetGo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/homepage.css?v=<?php echo @filemtime('css/homepage.css'); ?>">
    <link rel="stylesheet" href="css/skeleton.css?v=<?php echo @filemtime('css/skeleton.css'); ?>">
    <link id="dynamicFavicon" rel="icon" href="images/icon/JMB.jpg" type="image/jpeg">
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.1.1/model-viewer.min.js"></script>

    <style>
        @media (min-width: 768px) {
            .products-container {
                margin-top: 50px !important;
            }
        }
    </style>

</head>
<body class="homepage">
<!-- Desktop Sidebar -->
<div class="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <a href="index.php" class="logo-link">
            <img src="img/ClosetGo-logo.png" alt="ClosetGo Logo" class="logo-image">
        </a>
    </div>
    
    <a href="index.php" class="sidebar-icon active">
        <i class="fas fa-home"></i>
        <span class="sidebar-text">Home</span>
    </a>
</div>

<!-- Mobile Bottom Navigation -->
<nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="Mobile Navigation">
    <div class="mobile-nav-bg-container">
        <div class="mobile-nav-wing"></div>
        <svg class="mobile-nav-notch-center" viewBox="0 0 108 62" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path class="notch-edge" d="M 0,0 C 14,0 20,4 28,14 C 36,24 44,48 54,48 C 64,48 72,24 80,14 C 88,4 94,0 108,0 Z"></path>
        </svg>
        <div class="mobile-nav-wing"></div>
    </div>
    <a href="index.php" class="nav-item active">
        <img src="images/icon/home.png" alt="Home" class="nav-icon">
        <span class="nav-text">Home</span>
    </a>
    <a href="save.php" class="nav-item">
        <div class="nav-icon-wrapper" style="position: relative; display: inline-block;">
            <img src="images/icon/cart.png" alt="Cart" class="nav-icon">
            <span id="cartCount" class="purchase-nav-badge cart-badge" style="display: none;">0</span>
        </div>
        <span class="nav-text">Cart</span>
    </a>
    <div class="nav-center-gap"></div>
    <div class="nav-fab-wrapper">
        <a href="product_camscan.php" class="nav-fab-scan-btn" title="Camera Scan">
            <img src="images/icon/camera.png" alt="Camera Scan" class="nav-fab-icon">
        </a>
    </div>
    <a href="purchase.php" class="nav-item">
        <div class="nav-icon-wrapper" style="position: relative; display: inline-block;">
            <img src="images/icon/purchase.png" alt="Purchases" class="nav-icon">
            <span id="purchaseBadge" class="purchase-nav-badge" style="display: none;">0</span>
        </div>
        <span class="nav-text">Purchases</span>
    </a>
    <a href="profile.php" class="nav-item">
        <img src="images/icon/profile.png" alt="Profile" class="nav-icon">
        <span class="nav-text">Profile</span>
    </a>
</nav>

<div class="shell">
    <!-- Top Navigation Bar -->
    <div class="top-nav">
        <!-- Logo Section (Desktop Only) -->
        <div class="logo-section">
            <div class="logo-text-container">
                <div class="top-texts">
                    <a href="#" class="download-link">
                        <div class="download-text">Download</div>
                    </a>
                    <div class="text-divider"></div>
                    <div class="follow-text">Follow us on <a href="https://www.facebook.com/ACV.alf?mibextid=ZbWKwL" target="_blank" class="social-link"><img src="images/icon/facebook.gif" alt="Facebook" class="facebook-icon"></a> & <a href="https://www.instagram.com/iam_alff?igsh=YWdjcmQ2MmJ0OXkw" target="_blank" class="social-link"><img src="images/icon/instagram.gif" alt="Instagram" class="instagram-icon"></a></div>
                </div>
                <a href="index.php" class="closetgo-link">
                    <img src="images/icon/JMB.png" alt="ClosetGo" class="brand-logo" onerror="this.src='images/icon/chat.png'">
                    <span>ClosetGo</span>
                </a>
            </div>
        </div>
        
        <!-- Mobile ClosetGo Logo -->
        <a href="index.php" class="mobile-closetgo-link">
            <img src="images/icon/JMB.png" alt="ClosetGo" class="brand-logo" onerror="this.src='images/icon/chat.png'">
            <span>ClosetGo</span>
        </a>
        
        <!-- Right Side Navigation -->
        <div class="search-filter-container">
            <div class="search-box">
                <div style="display: flex; align-items: center; width: 100%;">
                    <img src="images/icon/search.png" alt="Search" class="search-left-icon" onclick="document.getElementById('searchInput')?.focus();">
                    <div style="color: inherit; display: flex; align-items: center; flex: 1; min-width: 0; overflow: hidden;">
                        <input type="text" placeholder="Search in this category..." id="searchInput" 
                               style="background: none; border: none; outline: none; width: 100%; min-width: 0; font-size: 1rem; color: #ffffff; pointer-events: auto;">
                    </div>
                    <div class="ai-search-prompt" onclick="window.location.href='index.php'" title="Use ClosetGo AiGo">
                        <img src="images/icon/search_bar.gif" alt="Search" class="search-right-icon">
                        <span class="ai-search-prompt-text">Use AiGo</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="auth-links" id="authLinksContainer">
            <?php if ($isLoggedIn): ?>
                <div class="auth-user-profile">
                    <span class="auth-user-name" id="topbarUserName"><?php echo htmlspecialchars($displayName); ?></span>
                    <a href="profile.php" class="auth-user-avatar-wrap" title="View Profile">
                        <img src="<?php echo htmlspecialchars($userProfileImage); ?>" alt="Profile" class="auth-user-avatar" id="topbarUserAvatar" onerror="this.src='images/icon/profile_logo.png'">
                    </a>
                </div>
            <?php else: ?>
                <a href="login_user/sign_up.php" class="auth-link">Sign Up</a>
                <div class="auth-divider"></div>
                <a href="login_user/login.php" class="auth-link">Login</a>
            <?php endif; ?>
        </div>
        <div class="mobile-top-icons">
            <div class="mobile-top-search" onclick="document.getElementById('searchInput')?.focus();" title="Search">
                <img src="images/icon/search.png" alt="Search">
            </div>
            <div class="purchase-top-container" style="position: relative; display: inline-block;" title="Purchases">
                <img src="images/icon/purchase.png" alt="Purchases" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'"
                     onclick="window.location.href='purchase.php'">
                <span id="topbarPurchaseBadge" class="purchase-nav-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
            </div>
            <div class="notification-container" id="topbarNotificationContainer" style="position: relative; display: inline-block;" title="Notifications" onclick="toggleNotificationDropdown(event)">
                <img src="images/icon/notification.png" alt="Notification" class="topbar-notif-icon" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'">
                <span id="topbarNotificationBadge" class="notification-nav-badge" style="display: none;">0</span>

                <!-- Notification Dropdown Panel -->
                <div id="notificationDropdown" class="notification-dropdown" onclick="event.stopPropagation()">
                    <div class="notif-dropdown-header">
                        <div class="notif-header-title">
                            <i class="fas fa-bell"></i> Notifications
                            <span id="notifUnreadPill" class="notif-unread-pill" style="display: none;">0 new</span>
                        </div>
                        <button type="button" class="notif-mark-read-btn" onclick="markAllNotificationsAsRead(event)">Mark all read</button>
                    </div>
                    <div class="notif-filter-tabs">
                        <div class="notif-tab active" onclick="filterNotifTab('all', this)">
                            <span>All</span>
                            <span id="notifTabBadgeAll" class="notif-tab-badge" style="display: none;">0</span>
                        </div>
                        <div class="notif-tab" onclick="filterNotifTab('order', this)">
                            <span>Orders</span>
                            <span id="notifTabBadgeOrder" class="notif-tab-badge" style="display: none;">0</span>
                        </div>
                        <div class="notif-tab" onclick="filterNotifTab('chat', this)">
                            <span>Messages</span>
                            <span id="notifTabBadgeChat" class="notif-tab-badge" style="display: none;">0</span>
                        </div>
                    </div>
                    <div id="notifListContainer" class="notif-list-container">
                        <div class="notif-loading" style="padding: 24px; text-align: center; color: #888; font-size: 12px;"><i class="fas fa-spinner fa-spin"></i> Loading notifications...</div>
                    </div>
                </div>
            </div>
            <div class="cart-container" style="position: relative; display: inline-block; margin-right: 120px;" title="Cart">
                <img src="images/icon/cart.png" alt="Cart" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'"
                     onclick="window.location.href='save.php'">
                <span id="topbarCartCount" class="cart-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
            </div>
            <div class="chat-top-container" style="position: relative; display: none;" title="Chat with Admin" onclick="window.location.href='index.php'">
                <img src="images/icon/message.png" alt="Chat" style="width: 23px; height: 23px; cursor: pointer; transition: transform 0.2s ease;"
                     onmouseover="this.style.transform='scale(1.1)'"
                     onmouseout="this.style.transform='scale(1)'">
                <span id="userChatBadge" class="purchase-nav-badge cart-badge" style="display: none; position: absolute; top: -5px; right: 1px; background: #ff4444; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 9px; font-weight: bold; align-items: center; justify-content: center;">0</span>
            </div>
        </div>
    </div>


    <!-- Products Container Box -->
    <div class="products-container" style="background: transparent; border: none; padding: 0;">
        <div class="products-box">
            <!-- Products Grid -->
            <div id="productsGrid" class="product-grid" style="display: grid;">
                <!-- Products will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- No Products Message -->
    <div id="noProductsMessage" style="display: none; text-align: center; padding: 70px 20px;">
        <i class="fas fa-box-open" style="font-size: 3rem; color: #555; margin-bottom: 16px;"></i>
        <h3 style="color: #bbb; font-size: 1.2rem; font-weight: 600;">No products found in this category</h3>
        <p style="color: #777; margin-top: 8px; font-size: 0.95rem;">Check back later or browse other collections on the homepage!</p>
        <button type="button" onclick="goBack()" style="margin-top: 18px; padding: 10px 24px; background: #ffd700; color: #111; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;">Return to Home</button>
    </div>
</div>

<script>
    const USER_CHAT_USER_ID = <?php echo intval($userId); ?>;
    let currentCategory = '';
    let allCategoryProducts = [];
    let cachedNotifications = [];
    let activeNotifFilter = 'all';

    function goBack() {
        window.location.href = 'index.php';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        currentCategory = urlParams.get('category') || '';
        
        const categoryTitle = document.getElementById('categoryTitle');
        if (categoryTitle && currentCategory) {
            categoryTitle.textContent = currentCategory.toUpperCase();
        }

        renderProductsSkeleton();
        loadProducts();
        updateCartCount();
        fetchNotifications();
        
        // Auto-focus search input
        setTimeout(() => {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', handleCategorySearch);
            }
        }, 100);

        // Click outside notification dropdown to close
        document.addEventListener('click', function(e) {
            const notifDropdown = document.getElementById('notificationDropdown');
            const notifBtn = document.getElementById('topbarNotificationContainer');
            if (notifDropdown && notifDropdown.classList.contains('show')) {
                if (!notifDropdown.contains(e.target) && (!notifBtn || !notifBtn.contains(e.target))) {
                    notifDropdown.classList.remove('show');
                }
            }
        });
    });

    // Skeleton generation
    function renderProductsSkeleton() {
        const grid = document.getElementById('productsGrid');
        if (!grid) return;
        grid.innerHTML = '';
        for (let i = 0; i < 6; i++) {
            const card = document.createElement('div');
            card.className = 'skeleton-product-card skeleton-box';
            card.style.height = '220px';
            grid.appendChild(card);
        }
    }

    // Load products from admin inventory
    function loadProducts() {
        const productsGrid = document.getElementById('productsGrid');
        const noProductsMessage = document.getElementById('noProductsMessage');
        const countDisplay = document.getElementById('categoryProductCount');

        fetch('admin/gowns.php?action=getAllGowns&hide_out_of_stock=1')
            .then(response => response.json())
            .then(data => {
                if (Array.isArray(data)) {
                    // Additional client-side filter: exclude out-of-stock products as safety measure
                    const inStockData = data.filter(product => {
                        const stock = parseInt(product.stock || 0);
                        return stock > 0;
                    });
                    
                    // Filter matching category
                    if (currentCategory && currentCategory !== 'all') {
                        allCategoryProducts = inStockData.filter(product => {
                            const cat = (product.category || '').toLowerCase().trim();
                            return cat === currentCategory.toLowerCase().trim() || cat.includes(currentCategory.toLowerCase().trim());
                        });
                    } else {
                        allCategoryProducts = inStockData;
                    }

                    if (allCategoryProducts.length > 0) {
                        productsGrid.style.display = 'grid';
                        if (noProductsMessage) noProductsMessage.style.display = 'none';
                        if (countDisplay) countDisplay.textContent = `${allCategoryProducts.length} ${allCategoryProducts.length === 1 ? 'Product' : 'Products'}`;
                        displayProducts(allCategoryProducts);
                    } else {
                        productsGrid.innerHTML = '';
                        productsGrid.style.display = 'none';
                        if (countDisplay) countDisplay.textContent = '0 Products';
                        if (noProductsMessage) noProductsMessage.style.display = 'block';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading products:', error);
                if (noProductsMessage) noProductsMessage.style.display = 'block';
            });
    }

    // Display products in grid
    function displayProducts(products) {
        const productsGrid = document.getElementById('productsGrid');
        const noProductsMessage = document.getElementById('noProductsMessage');
        productsGrid.innerHTML = '';

        if (!products || products.length === 0) {
            productsGrid.style.display = 'none';
            if (noProductsMessage) noProductsMessage.style.display = 'block';
            return;
        }

        // Additional client-side filter: exclude out-of-stock products as safety measure
        const inStockProducts = products.filter(product => {
            const stock = parseInt(product.stock || 0);
            return stock > 0;
        });

        if (inStockProducts.length === 0) {
            productsGrid.style.display = 'none';
            if (noProductsMessage) noProductsMessage.style.display = 'block';
            return;
        }

        productsGrid.style.display = 'grid';
        if (noProductsMessage) noProductsMessage.style.display = 'none';

        inStockProducts.forEach(product => {
            const productCard = createProductCard(product);
            productsGrid.appendChild(productCard);
        });
    }

    // Create product card element (matching index.php)
    function createProductCard(product) {
        const card = document.createElement('div');
        card.className = 'product-card skeleton-fade-in';
        card.onclick = () => viewProduct(product.id);

        let imageContent = '';

        if (product.image_path) {
            imageContent = `
                <div class="product-image">
                    <img src="${product.image_path}" alt="${product.name}" style="width: 100%; height: 100%; object-fit: cover; padding: 0;">
                </div>
            `;
        } else if (product.model_path) {
            imageContent = `
                <div class="product-image">
                    <model-viewer
                        src="${product.model_path}"
                        alt="${product.name}"
                        auto-rotate
                        camera-orbit="auto 90deg auto"
                        disable-pan
                        disable-zoom
                        min-camera-orbit="auto 90deg auto"
                        max-camera-orbit="auto 90deg auto">
                        <div slot="poster" class="model-skeleton-poster">
                            <div class="model-skeleton-shimmer"></div>
                        </div>
                    </model-viewer>
                    <button class="view-3d-btn" onclick="event.stopPropagation(); view3DModel('${product.model_path}')">
                        <i class="fas fa-cube"></i>
                    </button>
                </div>
            `;
        } else {
            imageContent = `
                <div class="product-image">
                    <div class="product-placeholder">
                        <i class="fas fa-tshirt"></i>
                        <div>${product.name}</div>
                        <div style="font-size: 0.9rem; opacity: 0.7;">No Image</div>
                    </div>
                </div>
            `;
        }

        const categoryUpper = (product.category || '').toUpperCase();
        const colorText = product.color ? ((product.color.includes(',') ? 'Colors: ' : 'Color: ') + product.color) : '';

        card.innerHTML = `
            ${imageContent}
            <div class="product-info">
                <div class="product-name">${product.name}</div>
                ${categoryUpper ? `<div class="product-category">${categoryUpper}</div>` : ''}
                ${colorText ? `<div class="product-color">${colorText}</div>` : ''}
                <div class="product-size" style="display: none;">${product.size || ''}</div>
            </div>
            ${product.discount && product.discount > 0 ? `<div class="discount-badge">-${Math.round(product.discount)}%</div>` : ''}
        `;

        return card;
    }

    function viewProduct(productId) {
        window.location.href = `index.php?id=${productId}`;
    }

    // View 3D model modal
    function view3DModel(modelPath) {
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.9); z-index: 10000; display: flex;
            justify-content: center; align-items: center;
        `;

        const viewer = document.createElement('model-viewer');
        viewer.setAttribute('src', modelPath);
        viewer.setAttribute('camera-controls', '');
        viewer.setAttribute('auto-rotate', '');
        viewer.setAttribute('disable-zoom', '');
        viewer.setAttribute('min-camera-orbit', 'auto 90deg auto');
        viewer.setAttribute('max-camera-orbit', 'auto 90deg auto');
        viewer.style.cssText = `width: 80%; height: 80%; max-width: 800px; max-height: 600px;`;

        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.style.cssText = `
            position: absolute; top: 20px; right: 20px; background: white;
            border: none; border-radius: 50%; width: 40px; height: 40px;
            cursor: pointer; font-size: 18px; z-index: 10001;
        `;

        closeBtn.onclick = () => document.body.removeChild(modal);
        modal.appendChild(viewer);
        modal.appendChild(closeBtn);
        document.body.appendChild(modal);
    }

    // Real-time search inside current category
    function handleCategorySearch(e) {
        const term = e.target.value.toLowerCase().trim();
        if (!term) {
            displayProducts(allCategoryProducts);
            const countDisplay = document.getElementById('categoryProductCount');
            if (countDisplay) countDisplay.textContent = `${allCategoryProducts.length} ${allCategoryProducts.length === 1 ? 'Product' : 'Products'}`;
            return;
        }

        const filtered = allCategoryProducts.filter(p => {
            const name = (p.name || '').toLowerCase();
            const desc = (p.description || '').toLowerCase();
            return name.includes(term) || desc.includes(term);
        });

        displayProducts(filtered);
        const countDisplay = document.getElementById('categoryProductCount');
        if (countDisplay) countDisplay.textContent = `${filtered.length} ${filtered.length === 1 ? 'Product' : 'Products'}`;
    }

    // Update Cart Count
    function updateCartCount() {
        const cartBadges = [
            document.getElementById('cartCount'),
            document.getElementById('topbarCartCount')
        ].filter(Boolean);
        if (cartBadges.length === 0) return;

        fetch('save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_cart_count'
        })
        .then(r => r.json())
        .then(data => {
            const count = data.success ? data.count : 0;
            cartBadges.forEach(b => {
                if (count > 0) {
                    b.textContent = count;
                    b.style.display = 'flex';
                } else {
                    b.style.display = 'none';
                }
            });
        })
        .catch(e => console.error('Error fetching cart count:', e));
    }

    // Notification dropdown & badges
    function toggleNotificationDropdown(e) {
        if (e) e.stopPropagation();
        const dropdown = document.getElementById('notificationDropdown');
        if (!dropdown) return;

        const isShowing = dropdown.classList.contains('show');
        if (isShowing) {
            dropdown.classList.remove('show');
        } else {
            dropdown.classList.add('show');
            fetchNotifications(true);
        }
    }

    function filterNotifTab(type, tabEl) {
        activeNotifFilter = type;
        const tabs = document.querySelectorAll('.notif-tab');
        tabs.forEach(t => t.classList.remove('active'));
        if (tabEl) tabEl.classList.add('active');
        renderNotificationsList();
    }

    function fetchNotifications(renderAfter = false) {
        const userId = USER_CHAT_USER_ID || 0;
        fetch(`notifications_get.php?user_id=${userId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && Array.isArray(data.notifications)) {
                    cachedNotifications = data.notifications;
                    updateNotificationBadge();
                    if (renderAfter || (document.getElementById('notificationDropdown') && document.getElementById('notificationDropdown').classList.contains('show'))) {
                        renderNotificationsList();
                    }
                }
            })
            .catch(err => console.error('Error fetching notifications:', err));
    }

    function getReadNotificationIds() {
        try {
            const key = `read_notifs_${USER_CHAT_USER_ID || 0}`;
            return JSON.parse(localStorage.getItem(key) || '[]');
        } catch(e) {
            return [];
        }
    }

    function updateNotificationBadge() {
        const badge = document.getElementById('topbarNotificationBadge');
        const pill = document.getElementById('notifUnreadPill');
        const tabBadgeAll = document.getElementById('notifTabBadgeAll');
        const tabBadgeOrder = document.getElementById('notifTabBadgeOrder');
        const tabBadgeChat = document.getElementById('notifTabBadgeChat');
        const readIds = getReadNotificationIds();

        const unreadList = cachedNotifications.filter(n => !readIds.includes(n.id));
        const unreadCount = unreadList.length;

        const unreadOrders = cachedNotifications.filter(n => (n.type === 'order' || n.type === 'reminder') && !readIds.includes(n.id)).length;
        const unreadChat = cachedNotifications.filter(n => n.type === 'chat' && !readIds.includes(n.id)).length;

        if (badge) {
            if (unreadCount > 0) {
                badge.style.display = 'inline-flex';
                badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            } else {
                badge.style.display = 'none';
            }
        }

        if (pill) {
            if (unreadCount > 0) {
                pill.style.display = 'inline-block';
                pill.textContent = unreadCount + ' new';
            } else {
                pill.style.display = 'none';
            }
        }

        if (tabBadgeAll) {
            if (unreadCount > 0) {
                tabBadgeAll.style.display = 'inline-block';
                tabBadgeAll.textContent = unreadCount > 99 ? '99+' : unreadCount;
            } else {
                tabBadgeAll.style.display = 'none';
            }
        }

        if (tabBadgeOrder) {
            if (unreadOrders > 0) {
                tabBadgeOrder.style.display = 'inline-block';
                tabBadgeOrder.textContent = unreadOrders > 99 ? '99+' : unreadOrders;
            } else {
                tabBadgeOrder.style.display = 'none';
            }
        }

        if (tabBadgeChat) {
            if (unreadChat > 0) {
                tabBadgeChat.style.display = 'inline-block';
                tabBadgeChat.textContent = unreadChat > 99 ? '99+' : unreadChat;
            } else {
                tabBadgeChat.style.display = 'none';
            }
        }
    }

    function renderNotificationsList() {
        const container = document.getElementById('notifListContainer');
        if (!container) return;

        const readIds = getReadNotificationIds();
        let list = cachedNotifications;

        if (activeNotifFilter === 'order') {
            list = list.filter(n => n.type === 'order' || n.type === 'reminder');
        } else if (activeNotifFilter === 'chat') {
            list = list.filter(n => n.type === 'chat');
        }

        if (list.length === 0) {
            container.innerHTML = `
                <div class="notif-empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <div>No notifications</div>
                </div>
            `;
            return;
        }

        container.innerHTML = list.map(n => {
            const isRead = readIds.includes(n.id);
            const iconClass = n.type === 'order' ? 'fa-shopping-bag' : (n.type === 'chat' ? 'fa-comment' : 'fa-bell');
            const iconBg = n.type === 'order' ? 'rgba(74, 144, 226, 0.15)' : (n.type === 'chat' ? 'rgba(80, 227, 194, 0.15)' : 'rgba(255, 215, 0, 0.15)');
            const iconColor = n.type === 'order' ? '#4a90e2' : (n.type === 'chat' ? '#50e3c2' : '#ffd700');

            return `
                <div class="notif-item ${isRead ? '' : 'unread'}" onclick="handleNotificationClick(${n.id}, '${n.link || ''}')">
                    <div class="notif-item-icon" style="background: ${iconBg}; color: ${iconColor};">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="notif-item-content">
                        <div class="notif-item-title">${n.title || 'Notification'}</div>
                        <div class="notif-item-desc">${n.message || ''}</div>
                        <div class="notif-item-time">${n.time_ago || ''}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function markAllNotificationsAsRead(e) {
        if (e) e.stopPropagation();
        const allIds = cachedNotifications.map(n => n.id);
        const key = `read_notifs_${USER_CHAT_USER_ID || 0}`;
        localStorage.setItem(key, JSON.stringify(allIds));
        updateNotificationBadge();
        renderNotificationsList();
    }

    function handleNotificationClick(notifId, link) {
        const key = `read_notifs_${USER_CHAT_USER_ID || 0}`;
        let readIds = getReadNotificationIds();
        if (!readIds.includes(notifId)) {
            readIds.push(notifId);
            localStorage.setItem(key, JSON.stringify(readIds));
            updateNotificationBadge();
        }
        if (link) window.location.href = link;
    }

    // Auto-refresh badges
    setInterval(updateCartCount, 10000);
    setInterval(fetchNotifications, 10000);
</script>
</body>
</html>
