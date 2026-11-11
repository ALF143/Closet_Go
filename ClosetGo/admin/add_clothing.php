<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: adminlogin.php');
    exit();
}

// Include database connection
require_once '../database/db_config.php';

class ClothingManager {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Add new clothing item
    public function addClothing($name, $category, $price, $purchase_price, $color, $description, $imagePath = null, $imagePath2 = null, $imagePath3 = null, $modelPath = null) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO gowns (name, category, price, purchase_price, color, description, image_path, image_path2, image_path3, model_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("ssdddsssss", $name, $category, $price, $purchase_price, $color, $description, $imagePath, $imagePath2, $imagePath3, $modelPath);
        
        return $stmt->execute();
    }

    // Get all clothing items
    public function getAllClothing() {
        $conn = $this->db->getConnection();
        
        $result = $conn->query("SELECT * FROM gowns ORDER BY id DESC");
        $clothing = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $clothing[] = $row;
            }
        }
        
        return $clothing;
    }

    // Get single clothing item
    public function getClothing($id) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM gowns WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }

    // Update clothing item
    public function updateClothing($id, $name, $category, $price, $purchase_price, $color, $description, $imagePath = null, $modelPath = null) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("
            UPDATE gowns SET name = ?, category = ?, price = ?, purchase_price = ?, color = ?, description = ?, image_path = ?, model_path = ? 
            WHERE id = ?
        ");
        
        $stmt->bind_param("ssdddsssi", $name, $category, $price, $purchase_price, $color, $description, $imagePath, $modelPath, $id);
        
        return $stmt->execute();
    }

    // Delete clothing item
    public function deleteClothing($id) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("DELETE FROM gowns WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        return $stmt->execute();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clothingManager = new ClothingManager();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_clothing':
                $name = $_POST['name'];
                $category = $_POST['category'];
                $price = $_POST['price'];
                $purchase_price = $_POST['purchase_price'];
                $color = $_POST['color'];
                $description = $_POST['description'];
                
                // Handle file uploads
                $imagePath = null;
                $imagePath2 = null;
                $imagePath3 = null;
                $modelPath = null;
                
                // Handle multiple image uploads
                if (isset($_FILES['images']) && $_FILES['images']['error'][0] !== UPLOAD_ERR_NO_FILE) {
                    $imageFiles = $_FILES['images'];
                    $fileCount = count($imageFiles['name']);
                    
                    // Process up to 3 images
                    for ($i = 0; $i < min($fileCount, 3); $i++) {
                        if ($imageFiles['error'][$i] === UPLOAD_ERR_OK) {
                            $imageName = time() . '_' . ($i + 1) . '_' . basename($imageFiles['name'][$i]);
                            $currentImagePath = 'uploads/' . $imageName;
                            move_uploaded_file($imageFiles['tmp_name'][$i], '../' . $currentImagePath);
                            
                            // Store in appropriate variable
                            if ($i === 0) $imagePath = $currentImagePath;
                            elseif ($i === 1) $imagePath2 = $currentImagePath;
                            elseif ($i === 2) $imagePath3 = $currentImagePath;
                        }
                    }
                }
                
                // Handle 3D model
                if (isset($_FILES['model']) && $_FILES['model']['error'] === UPLOAD_ERR_OK) {
                    $modelPath = 'models/' . time() . '_' . basename($_FILES['model']['name']);
                    move_uploaded_file($_FILES['model']['tmp_name'], '../' . $modelPath);
                }
                
                if ($clothingManager->addClothing($name, $category, $price, $purchase_price, $color, $description, $imagePath, $imagePath2, $imagePath3, $modelPath)) {
                    $success_message = "Clothing item added successfully!";
                } else {
                    $error_message = "Failed to add clothing item.";
                }
                break;
                
            case 'update_clothing':
                $id = $_POST['id'];
                $name = $_POST['name'];
                $category = $_POST['category'];
                $price = $_POST['price'];
                $purchase_price = $_POST['purchase_price'];
                $color = $_POST['color'];
                $description = $_POST['description'];
                
                // Handle file uploads
                $imagePath = null;
                $modelPath = null;
                
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $imagePath = 'uploads/' . time() . '_' . basename($_FILES['image']['name']);
                    move_uploaded_file($_FILES['image']['tmp_name'], '../' . $imagePath);
                }
                
                if (isset($_FILES['model']) && $_FILES['model']['error'] === UPLOAD_ERR_OK) {
                    $modelPath = 'models/' . time() . '_' . basename($_FILES['model']['name']);
                    move_uploaded_file($_FILES['model']['tmp_name'], '../' . $modelPath);
                }
                
                if ($clothingManager->updateClothing($id, $name, $category, $price, $purchase_price, $color, $description, $imagePath, $modelPath)) {
                    $success_message = "Clothing item updated successfully!";
                } else {
                    $error_message = "Failed to update clothing item.";
                }
                break;
                
            case 'delete_clothing':
                $id = $_POST['id'];
                if ($clothingManager->deleteClothing($id)) {
                    $success_message = "Clothing item deleted successfully!";
                } else {
                    $error_message = "Failed to delete clothing item.";
                }
                break;
        }
    }
}

// Handle API requests
if (isset($_GET['action']) && $_GET['action'] == 'getAllClothing') {
    header('Content-Type: application/json');
    
    $clothingManager = new ClothingManager();
    $clothing = $clothingManager->getAllClothing();
    
    echo json_encode($clothing);
    exit();
}

if (isset($_GET['action']) && $_GET['action'] == 'getClothing') {
    header('Content-Type: application/json');
    
    $id = $_GET['id'];
    $clothingManager = new ClothingManager();
    $clothing = $clothingManager->getClothing($id);
    
    if ($clothing) {
        echo json_encode($clothing);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Clothing item not found']);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Clothing - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-modern.css">
    <link rel="stylesheet" href="../css/admin_mobile_css.css">
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.1.1/model-viewer.min.js"></script>
    <script src="../js/dark-mode.js"></script>
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
    });
    </script>
</head>
<body class="no-sidebar">
    <div class="shell">
        <!-- Clothing Form -->
        <div class="content-section">
          
            
            <?php if (isset($success_message)): ?>
                <div class="success-message" style="background: rgba(78, 205, 196, 0.2); color: #4ecdc4; border: 1px solid rgba(78, 205, 196, 0.3); padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <p><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message" style="background: rgba(255, 107, 107, 0.2); color: #ff6b6b; border: 1px solid rgba(255, 107, 107, 0.3); padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="clothing-form">
                <input type="hidden" name="action" value="add_clothing">
                
                <div class="form-row-three">
                    <div class="form-group">
                        <label for="name">
                            <i class="fas fa-tag"></i> Product
                        </label>
                        <input type="text" id="name" name="name" required placeholder="Enter clothing name">
                    </div>
                    <div class="form-group">
                        <label for="category">
                            <i class="fas fa-folder"></i> Category
                        </label>
                        <select id="category" name="category" required>
                            <option value="">Select category</option>
                            <option value="Men">Men</option>
                            <option value="Upper Wear (Men)">Upper Wear (Men)</option>
                            <option value="Lower Wear (Men)">Lower Wear (Men)</option>
                            <option value="Women">Women</option>
                            <option value="Children">Children</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="stock">
                            <i class="fas fa-boxes"></i> Stock
                        </label>
                        <input type="number" id="stock" name="stock" required min="0" placeholder="0">
                    </div>
                </div>
                
                <div class="form-row-three">
                    <div class="form-group">
                        <label for="price">
                            <i class="fas fa-dollar-sign"></i> Price
                        </label>
                        <input type="number" id="price" name="price" required step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="purchase_price">
                            <i class="fas fa-tag"></i> Purchase Price
                        </label>
                        <input type="number" id="purchase_price" name="purchase_price" required step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="color">
                            <i class="fas fa-palette"></i> Color
                        </label>
                        <input type="text" id="color" name="color" required placeholder="Enter color">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">
                        <i class="fas fa-align-left"></i> Description
                    </label>
                    <textarea id="description" name="description" rows="4" required placeholder="Enter clothing description"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="images">
                        <i class="fas fa-images"></i> Product Images (Select up to 3 images)
                    </label>
                    <input type="file" id="images" name="images[]" accept="image/*" multiple onchange="previewImages(this)">
                    <small>Hold Ctrl/Cmd to select multiple images (max 3)</small>
                    <div id="imagePreview" class="image-preview-container" style="margin-top: 10px; display: none;">
                        <!-- Image previews will be shown here -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="model">
                        <i class="fas fa-cube"></i> 3D Model 
                    </label>
                    <input type="file" id="model" name="model" accept=".glb,.gltf" onchange="previewModel(this)">
                    <small>Select .glb or .gltf file</small>
                    <div id="modelPreview" class="model-preview-container" style="margin-top: 10px; display: none;">
                        <!-- 3D model preview will be shown here -->
                    </div>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-plus"></i> Add Clothing Item
                </button>
                <button type="reset" class="action-btn">
                    <i class="fas fa-undo"></i> Reset Form
                </button>
            </form>
        </div>

            </div>

    <style>
    /* Light Mode Styles for Add Clothing */
    .form-group label {
        color: #000000;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(0, 0, 0, 0.3);
        color: #000000;
        font-size: 14px;
    }
    
    .form-group input::placeholder,
    .form-group textarea::placeholder {
        color: #666666;
    }
    
    .clothing-card {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.3s ease;
    }
    
    .clothing-card:hover {
        transform: translateY(-5px);
    }
    
    .clothing-image {
        height: 200px;
        background: rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    
    .clothing-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .no-image {
        font-size: 48px;
        color: rgba(0, 0, 0, 0.3);
    }
    
    .clothing-info {
        padding: 15px;
    }
    
    .clothing-info h4 {
        color: #000000;
        margin-bottom: 8px;
    }
    
    .clothing-info .category {
        color: rgba(0, 0, 0, 0.7);
        font-size: 14px;
        margin-bottom: 8px;
    }
    
    .clothing-info .price {
        color: #4ecdc4;
        font-weight: bold;
        font-size: 18px;
        margin-bottom: 10px;
    }
    
    .clothing-actions {
        display: flex;
        gap: 10px;
    }
    
    .clothing-actions .action-btn {
        padding: 8px 12px;
        font-size: 12px;
    }
    
    .clothing-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
    }
    
    .clothing-form {
        display: grid;
        gap: 20px;
    }

    /* Dark Mode Styles for Add Clothing */
    body.dark-mode .form-group label {
        color: #eee;
    }
    
    body.dark-mode .form-group input,
    body.dark-mode .form-group select,
    body.dark-mode .form-group textarea {
        background: #0f3460;
        border: 1px solid #16213e;
        color: #eee;
    }
    
    body.dark-mode .form-group input::placeholder,
    body.dark-mode .form-group textarea::placeholder {
        color: #bbb;
    }
    
    body.dark-mode .clothing-card {
        background: #16213e;
        border: 1px solid #0f3460;
    }
    
    body.dark-mode .clothing-image {
        background: #0f3460;
    }
    
    body.dark-mode .no-image {
        color: #bbb;
    }
    
    body.dark-mode .clothing-info h4 {
        color: #eee;
    }
    
    body.dark-mode .clothing-info .category {
        color: #bbb;
    }
    
    body.dark-mode .clothing-info .price {
        color: #667eea;
    }
    
    /* Image Preview Styles */
    .image-preview-container {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 10px;
    }
    
    .image-preview-item {
        position: relative;
        width: 80px;
        height: 80px;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid #667eea;
        background: #f8f9fa;
    }
    
    .image-preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .image-preview-item .remove-btn {
        position: absolute;
        top: 2px;
        right: 2px;
        background: rgba(255, 255, 255, 0.9);
        border: none;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        cursor: pointer;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .image-preview-item:hover .remove-btn {
        opacity: 1;
    }
    
    body.dark-mode .image-preview-item {
        border-color: #5a67d8;
        background: #0f3460;
    }
    
    body.dark-mode .image-preview-item .remove-btn {
        background: rgba(0, 0, 0, 0.7);
        color: white;
    }
    
    /* 3D Model Preview Styles */
    .model-preview-container {
        width: 100%;
        height: 200px;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid #667eea;
        background: #f8f9fa;
        position: relative;
    }
    
    .model-preview-container model-viewer {
        width: 100%;
        height: 100%;
    }
    
    .model-preview-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #666;
        font-size: 14px;
    }
    
    .model-preview-placeholder i {
        font-size: 48px;
        margin-bottom: 10px;
        color: #999;
    }
    
    .model-preview-container .remove-btn {
        position: absolute;
        top: 5px;
        right: 5px;
        background: rgba(255, 255, 255, 0.9);
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 10;
    }
    
    .model-preview-container:hover .remove-btn {
        opacity: 1;
    }
    
    body.dark-mode .model-preview-container {
        border-color: #5a67d8;
        background: #0f3460;
    }
    
    body.dark-mode .model-preview-container .remove-btn {
        background: rgba(0, 0, 0, 0.7);
        color: white;
    }
    </style>

    <script>
    // Include model-viewer script
    const script = document.createElement('script');
    script.type = 'module';
    script.src = 'https://ajax.googleapis.com/ajax/libs/model-viewer/3.1.1/model-viewer.min.js';
    document.head.appendChild(script);
    
    // Image Preview Function
    let selectedImages = [];
    let selectedModel = null;
    
    function previewImages(input) {
        const previewContainer = document.getElementById('imagePreview');
        
        if (input.files && input.files.length > 0) {
            // Add new images to the selected array (max 3 total)
            const maxImages = 3;
            const currentCount = selectedImages.length;
            const newImages = Array.from(input.files).slice(0, maxImages - currentCount);
            
            // Process new images
            newImages.forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const imageData = {
                            url: e.target.result,
                            name: file.name,
                            id: Date.now() + '_' + index
                        };
                        selectedImages.push(imageData);
                        renderPreviews();
                    };
                    
                    reader.readAsDataURL(file);
                }
            });
        }
    }
    
    function renderPreviews() {
        const previewContainer = document.getElementById('imagePreview');
        previewContainer.innerHTML = '';
        
        if (selectedImages.length > 0) {
            previewContainer.style.display = 'flex';
            
            selectedImages.forEach((image, index) => {
                const previewItem = document.createElement('div');
                previewItem.className = 'image-preview-item';
                previewItem.innerHTML = `
                    <img src="${image.url}" alt="Preview ${index + 1}">
                    <button class="remove-btn" onclick="removeImage('${image.id}')" title="Remove image">×</button>
                `;
                previewContainer.appendChild(previewItem);
            });
        } else {
            previewContainer.style.display = 'none';
        }
    }
    
    function removeImage(imageId) {
        selectedImages = selectedImages.filter(img => img.id !== imageId);
        renderPreviews();
    }
    
    // 3D Model Preview Function
    function previewModel(input) {
        const previewContainer = document.getElementById('modelPreview');
        
        if (input.files && input.files.length > 0) {
            const file = input.files[0];
            const fileName = file.name.toLowerCase();
            
            // Check if it's a valid 3D model file
            if (fileName.endsWith('.glb') || fileName.endsWith('.gltf')) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    selectedModel = {
                        url: e.target.result,
                        name: file.name,
                        id: Date.now()
                    };
                    renderModelPreview();
                };
                
                reader.readAsDataURL(file);
            } else {
                alert('Please select a valid 3D model file (.glb or .gltf)');
                input.value = '';
            }
        }
    }
    
    function renderModelPreview() {
        const previewContainer = document.getElementById('modelPreview');
        
        if (selectedModel) {
            previewContainer.style.display = 'block';
            previewContainer.innerHTML = `
                <model-viewer 
                    src="${selectedModel.url}"
                    alt="${selectedModel.name}"
                    auto-rotate
                    camera-orbit="auto 90deg auto"
                    camera-controls
                    disable-pan
                    disable-zoom
                    min-camera-orbit="auto 90deg auto"
                    max-camera-orbit="auto 90deg auto"
                    style="width: 100%; height: 100%;">
                </model-viewer>
                <button class="remove-btn" onclick="removeModel()" title="Remove model">×</button>
            `;
        } else {
            previewContainer.style.display = 'none';
        }
    }
    
    function removeModel() {
        selectedModel = null;
        const previewContainer = document.getElementById('modelPreview');
        previewContainer.style.display = 'none';
        document.getElementById('model').value = '';
    }
    
    // Edit and Delete Functions
    function editClothing(id) {
        // Load clothing data into form for editing
        fetch(`add_clothing.php?action=getClothing&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data) {
                    document.getElementById('name').value = data.name;
                    document.getElementById('category').value = data.category;
                    document.getElementById('price').value = data.price;
                    document.getElementById('purchase_price').value = data.purchase_price || '';
                    document.getElementById('color').value = data.color || '';
                    document.getElementById('description').value = data.description;
                    
                    // Change form action to update
                    document.querySelector('input[name="action"]').value = 'update_clothing';
                    
                    // Add hidden ID field
                    let idField = document.createElement('input');
                    idField.type = 'hidden';
                    idField.name = 'id';
                    idField.value = id;
                    document.querySelector('.clothing-form').appendChild(idField);
                    
                    // Scroll to form
                    document.querySelector('.clothing-form').scrollIntoView({ behavior: 'smooth' });
                }
            })
            .catch(error => console.error('Error:', error));
    }
    
    function deleteClothing(id) {
        if (confirm('Are you sure you want to delete this clothing item?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_clothing">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>
