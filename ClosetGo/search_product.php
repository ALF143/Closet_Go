<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Search Products - ClosetGo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/homepage.css">
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.1.1/model-viewer.min.js"></script>
    <style>
        /* Placeholder transition effects */
        .placeholder-transition-out {
            animation: slideOut 0.3s ease-in-out;
        }
        
        .placeholder-transition-in {
            animation: slideIn 0.3s ease-in-out;
        }
        
        @keyframes slideOut {
            0% {
                opacity: 1;
                transform: translateY(0);
            }
            100% {
                opacity: 0;
                transform: translateY(-10px);
            }
        }
        
        @keyframes slideIn {
            0% {
                opacity: 0;
                transform: translateY(10px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        #searchInput {
            transition: all 0.3s ease;
        }
        
        .search-box a:hover {
            transform: scale(1.02);
            opacity: 0.9;
        }
        
        .search-box a:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body class="homepage">
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-bar-content">
            <img src="images/icon/back.png" alt="Back" class="back-button" onclick="goBack()">
            <div class="search-filter-container" style="flex: 1; margin-left: 20px;">
                <div class="search-box">
                    <img src="images/icon/search_bar.gif" alt="Search" style="width: 25px; height: 25px; margin-right: 8px;">
                    <input type="text" placeholder="Search for products..." id="searchInput" style="background: none; border: none; outline: none; flex: 1; font-size: 1rem; color: #ffffff;">
                </div>
            </div>
        </div>
    </div>

<div class="shell">
    <!-- Recent Search Box -->
    <div class="recent-search-box">
        <div id="recentSearches" class="recent-searches">
            <!-- Recent search items will be added here dynamically -->
        </div>
    </div>

    <!-- Loading Indicator -->
    <div id="loadingIndicator" class="loading">
        <div class="spinner"></div>
    </div>

    <!-- Products Container Box -->
    <div class="products-container">
        <!-- Products Grid -->
        <div id="productsGrid" class="product-grid" style="display: none;">
            <!-- Products will be loaded here dynamically -->
        </div>
    </div>
</div>

<script>
    function goBack() {
        window.location.href = 'index.php';
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Load products
        loadProducts();
        
        // Load recent searches
        loadRecentSearches();
        
        // Auto-focus search input for immediate typing
        setTimeout(() => {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
                // Remove pointer-events none to allow interaction
                searchInput.style.pointerEvents = 'auto';
            }
        }, 100);
    });

    // Load recent searches from localStorage
    function loadRecentSearches() {
        const recentSearches = JSON.parse(localStorage.getItem('recentSearches') || '[]');
        const recentSearchesContainer = document.getElementById('recentSearches');
        
        if (recentSearches.length === 0) {
            recentSearchesContainer.innerHTML = '';
            return;
        }
        
        recentSearchesContainer.innerHTML = '';
        recentSearches.slice(0, 5).forEach(search => {
            const searchChip = document.createElement('div');
            searchChip.className = 'recent-search-chip';
            searchChip.textContent = search;
            searchChip.onclick = () => {
                document.getElementById('searchInput').value = search;
                filterProducts();
            };
            recentSearchesContainer.appendChild(searchChip);
        });
    }

    // Save search to localStorage
    function saveSearch(searchTerm) {
        if (!searchTerm.trim()) return;
        
        let recentSearches = JSON.parse(localStorage.getItem('recentSearches') || '[]');
        
        // Remove if already exists
        recentSearches = recentSearches.filter(search => search !== searchTerm);
        
        // Add to beginning
        recentSearches.unshift(searchTerm);
        
        // Keep only last 10
        recentSearches = recentSearches.slice(0, 10);
        
        localStorage.setItem('recentSearches', JSON.stringify(recentSearches));
        loadRecentSearches();
    }

    // Load products from admin inventory
    function loadProducts() {
        const loadingIndicator = document.getElementById('loadingIndicator');
        const productsGrid = document.getElementById('productsGrid');

        fetch('admin/gowns.php?action=getAllGowns')
            .then(response => response.json())
            .then(data => {
                loadingIndicator.style.display = 'none';

                if (data && data.length > 0) {
                    productsGrid.style.display = 'grid';
                    displayProducts(data);
                    // Apply initial filter if search term exists
                    const urlParams = new URLSearchParams(window.location.search);
                    const searchTerm = urlParams.get('search');
                    if (searchTerm) {
                        document.getElementById('searchInput').placeholder = searchTerm;
                        filterProducts();
                    }
                }
            })
            .catch(error => {
                console.error('Error loading products:', error);
                loadingIndicator.style.display = 'none';
            });
    }

    // Display products in grid
    function displayProducts(products) {
        const productsGrid = document.getElementById('productsGrid');
        productsGrid.innerHTML = '';

        products.forEach(product => {
            const productCard = createProductCard(product);
            productsGrid.appendChild(productCard);
        });
    }

    // Create product card element
    function createProductCard(product) {
        const card = document.createElement('div');
        card.className = 'product-card';
        card.onclick = () => viewProduct(product.id);

        let imageContent = '';

        if (product.model_path) {
            // 3D Model viewer
            imageContent = `
                <div class="product-image">
                    <model-viewer
                        src="${product.model_path}"
                        alt="${product.name}"
                        auto-rotate
                        camera-orbit="auto 90deg auto"
                        camera-controls
                        disable-pan
                        disable-zoom
                        min-camera-orbit="auto 90deg auto"
                        max-camera-orbit="auto 90deg auto">
                    </model-viewer>
                    <button class="view-3d-btn" onclick="event.stopPropagation(); view3DModel('${product.model_path}')">
                        <i class="fas fa-cube"></i>
                    </button>
                </div>
            `;
        } else {
            // Placeholder
            imageContent = `
                <div class="product-image">
                    <div class="product-placeholder">
                        <i class="fas fa-tshirt"></i>
                        <div>${product.name}</div>
                        <div style="font-size: 0.9rem; opacity: 0.7;">No 3D Model</div>
                    </div>
                </div>
            `;
        }

        card.innerHTML = `
            ${imageContent}
            <div class="product-info">
                <div class="product-name" style="text-align: center;">${product.name}</div>
            </div>
        `;

        return card;
    }

    // View product details
    function viewProduct(productId) {
        window.location.href = `index.php?id=${productId}`;
    }

    // View 3D model in full screen
    function view3DModel(modelPath) {
        // Create modal for 3D view
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
        `;

        const viewer = document.createElement('model-viewer');
        viewer.setAttribute('src', modelPath);
        viewer.setAttribute('camera-controls', '');
        viewer.setAttribute('auto-rotate', '');
        viewer.setAttribute('disable-zoom', '');
        viewer.setAttribute('min-camera-orbit', 'auto 90deg auto');
        viewer.setAttribute('max-camera-orbit', 'auto 90deg auto');
        viewer.style.cssText = `
            width: 80%;
            height: 80%;
            max-width: 800px;
            max-height: 600px;
        `;

        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.style.cssText = `
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            font-size: 18px;
            z-index: 10001;
        `;

        closeBtn.onclick = () => document.body.removeChild(modal);
        modal.appendChild(viewer);
        modal.appendChild(closeBtn);
        document.body.appendChild(modal);
    }

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
        filterProducts();
        
        // Save search after user stops typing (debounce)
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(() => {
            const searchValue = e.target.value.trim();
            if (searchValue.length > 0) {
                saveSearch(searchValue);
            }
        }, 500);
    });

    // Category filter functionality
    document.getElementById('categoryFilter').addEventListener('change', function(e) {
        filterProducts();
    });

    // Size filter functionality
    document.getElementById('sizeFilter').addEventListener('change', function(e) {
        filterProducts();
    });

    // Combined filter function
    function filterProducts() {
        const searchInput = document.getElementById('searchInput');
        let searchTerm = searchInput.value.toLowerCase();
        
        // If no value, use placeholder for initial search
        if (!searchTerm && searchInput.placeholder) {
            searchTerm = searchInput.placeholder.toLowerCase();
        }
        
        const selectedCategory = document.getElementById('categoryFilter') ? document.getElementById('categoryFilter').value.toLowerCase() : '';
        const selectedSize = document.getElementById('sizeFilter') ? document.getElementById('sizeFilter').value.toUpperCase() : '';
        const productCards = document.querySelectorAll('.product-card');
        
        productCards.forEach(card => {
            const productName = card.querySelector('.product-name').textContent.toLowerCase();
            const productCategory = card.querySelector('.product-category').textContent.toLowerCase();
            const productSize = card.querySelector('.product-size').textContent.toUpperCase();
            
            let matchesSearch = productName.includes(searchTerm) || productCategory.includes(searchTerm);
            let matchesCategory = !selectedCategory || productCategory.includes(selectedCategory);
            let matchesSize = !selectedSize || productSize.includes(selectedSize);
            
            if (matchesSearch && matchesCategory && matchesSize) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    function showComingSoon(feature) {
        alert('coming soon, wait baby ko!');
    }
    
    // Dynamic search placeholder with product names from database
    let productNames = [];
    let currentProductIndex = 0;
    const searchInput = document.getElementById('searchInput');
    
    // Fetch product names from database
    async function fetchProductNames() {
        try {
            const response = await fetch('get_product_names.php');
            if (response.ok) {
                const names = await response.json();
                productNames = names.length > 0 ? names : ["Elegant Evening Gown", "Classic Wedding Dress"];
                updateSearchPlaceholder();
            }
        } catch (error) {
            console.error('Error fetching product names:', error);
            // Fallback to default names if fetch fails
            productNames = ["Elegant Evening Gown", "Classic Wedding Dress", "Summer Floral Dress"];
            updateSearchPlaceholder();
        }
    }
    
    function updateSearchPlaceholder() {
        if (searchInput && productNames.length > 0) {
            const productName = productNames[currentProductIndex];
            
            // Add transition classes
            searchInput.classList.add('placeholder-transition-out');
            
            // Change text after transition out starts
            setTimeout(() => {
                searchInput.placeholder = productName;
                searchInput.classList.remove('placeholder-transition-out');
                searchInput.classList.add('placeholder-transition-in');
                
                // Remove transition in class after animation
                setTimeout(() => {
                    searchInput.classList.remove('placeholder-transition-in');
                }, 300);
            }, 300);
            
            currentProductIndex = (currentProductIndex + 1) % productNames.length;
        }
    }
    
    // Start the placeholder rotation after fetching names
    fetchProductNames();
    setInterval(updateSearchPlaceholder, 3000);
</script>
</body>
</html>
