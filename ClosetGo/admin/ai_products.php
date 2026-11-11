<?php
session_start();
require_once '../database/db_config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Handle POST requests for updating skin-based data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_skinbased') {
        $id = $_POST['id'] ?? '';
        $question = trim($_POST['question'] ?? '');
        $aiMessage = trim($_POST['ai_message'] ?? '');
        $skinToneColors = $_POST['skin_tone_colors'] ?? '{}';

        if (!empty($id) && !empty($question) && !empty($aiMessage)) {
            $stmt = $conn->prepare("UPDATE ai_skinbased SET question = ?, ai_message = ?, skin_tone_colors = ? WHERE id = ?");
            $stmt->bind_param("sssi", $question, $aiMessage, $skinToneColors, $id);
            $stmt->execute();
            header('Location: ai_products.php');
            exit();
        }
    }
}

// Fetch all skin-based data
$result = $conn->query("SELECT * FROM ai_skinbased ORDER BY id ASC");
$skinBasedData = [];
while ($row = $result->fetch_assoc()) {
    $skinBasedData[] = $row;
}

// Handle AJAX request to update skin tone colors
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_skin_tone_colors') {
    header('Content-Type: application/json');

    $id = $_POST['id'] ?? 0;
    $skinToneColors = $_POST['skin_tone_colors'] ?? '{}';

    $stmt = $conn->prepare("UPDATE ai_skinbased SET skin_tone_colors = ? WHERE id = ?");
    $stmt->bind_param("si", $skinToneColors, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }

    $stmt->close();
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Products - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-modern.css">
    <link rel="stylesheet" href="../css/admin_mobile_css.css">
    <style>
        /* ===== Page Layout (Aligned with buy.php) ===== */
        .shell {
            background: #000000 !important;
            min-height: 100vh;
            padding: 35px 35px 60px 35px !important;
            box-sizing: border-box;
        }
        .main-container {
            padding: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
            margin: 0 !important;
        }
        .content-section { display: flex; flex-direction: column; gap: 24px; }

        /* ===== Cards (Styled like buy.php .osa-table-card) ===== */
        .card {
            background: #111111;
            border: 1px solid #7c6300;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: inset 0 0 20px rgba(255, 215, 0, 0.12), 0 8px 30px rgba(0, 0, 0, 0.6);
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            border-color: #ffd700;
            box-shadow: inset 0 0 20px rgba(255, 215, 0, 0.18), 0 10px 35px rgba(0, 0, 0, 0.8);
        }
        .card-header {
            background: #161616;
            border-bottom: 1px solid rgba(255, 215, 0, 0.25);
            padding: 16px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h3 {
            color: #ffd700;
            font-size: 1.05rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header h3 i { color: #ffd700; }
        .badge {
            background: rgba(124,99,0,0.15);
            color: #ffd700;
            border: 1px solid rgba(124,99,0,0.3);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        /* ===== Card Body & Forms ===== */
        .card-body { padding: 24px; }
        .card-body label {
            color: #ccc;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .card-body label i { color: #ffd700; font-size: 12px; }
        .card-body input,
        .card-body textarea {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            color: #ffffff;
            padding: 10px 14px;
            font-size: 14px;
            width: 100%;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .card-body input:focus,
        .card-body textarea:focus {
            border-color: #7c6300;
            box-shadow: 0 0 0 3px rgba(124,99,0,0.15);
            outline: none;
        }
        .card-body input::placeholder,
        .card-body textarea::placeholder { color: #666; }
        .card-body textarea { resize: vertical; min-height: 80px; font-family: inherit; }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group { display: flex; flex-direction: column; }

        /* ===== Form Section (Skin Tone Config) ===== */
        .form-section {
            background: rgba(255,255,255,0.02);
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .form-section h4 {
            color: #ffd700;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-section h4 i { color: #ffd700; }
        .form-section input[readonly] {
            cursor: pointer;
            border: 1px solid rgba(124,99,0,0.4);
            background: rgba(124,99,0,0.05);
            color: #ffd700;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .form-section input[readonly]:hover {
            border-color: #ffd700;
            background: rgba(124,99,0,0.12);
            box-shadow: 0 0 0 3px rgba(124,99,0,0.1);
        }

        /* ===== Form Actions ===== */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            padding-top: 8px;
            border-top: 1px solid #2a2a2a;
        }
        .btn-primary {
            background: linear-gradient(135deg, #7c6300, #988726);
            color: #ffffff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.15s ease, box-shadow 0.2s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #988726, #b89f3d);
            box-shadow: 0 4px 16px rgba(124,99,0,0.3);
            transform: translateY(-1px);
        }
        .btn-primary:active { transform: scale(0.97); }

        /* ===== Alert ===== */
        .alert {
            background: rgba(255,152,0,0.08);
            border: 1px solid rgba(255,152,0,0.3);
            color: #ffb74d;
            padding: 16px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .alert i { font-size: 18px; }

        /* Big modal styling is maintained centrally in css/admin-modern.css */

        .slide-panel-header {
            background: linear-gradient(135deg, #1a1a1a, #111);
            padding: 20px 24px;
            border-bottom: 1px solid #2a2a2a;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        .slide-panel-header h3 {
            margin: 0;
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .slide-panel-header h3::before {
            content: '';
            width: 4px; height: 20px;
            background: linear-gradient(180deg, #ffd700, #7c6300);
            border-radius: 2px;
        }
        .slide-panel-close {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-size: 15px;
            cursor: pointer;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .slide-panel-close:hover {
            color: #ffffff;
            border-color: rgba(220, 53, 69, 1);
            background: rgba(220, 53, 69, 0.85);
            transform: rotate(90deg);
        }

        .slide-panel-body {
            padding: 20px;
            color: #ffffff;
            flex: 1;
            overflow-y: auto;
        }
        .slide-panel-body::-webkit-scrollbar { width: 6px; }
        .slide-panel-body::-webkit-scrollbar-track { background: #111; }
        .slide-panel-body::-webkit-scrollbar-thumb { background: #333; border-radius: 3px; }
        .slide-panel-body::-webkit-scrollbar-thumb:hover { background: #555; }

        /* ===== Color Items ===== */
        .color-item {
            background: linear-gradient(135deg, #1a1a1a, #161616);
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: 10px;
            border: 1px solid #2a2a2a;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: border-color 0.2s ease, transform 0.15s ease;
        }
        .color-item:hover {
            border-color: rgba(124,99,0,0.3);
            transform: translateX(-2px);
        }
        .color-item > div {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #e0e0e0;
            font-size: 14px;
        }
        .color-item i { color: #ffd700; font-size: 12px; }
        .color-item .remove-color {
            color: #ff4444;
            cursor: pointer;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .color-item .remove-color:hover {
            background: rgba(255,68,68,0.1);
            border-color: rgba(255,68,68,0.3);
        }

        /* ===== Slide Panel Overlay ===== */
        .slide-panel-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            display: none;
            z-index: 999;
        }
        .slide-panel-overlay.show { display: block; }

        /* ===== Slide Panel Footer ===== */
        .slide-panel-footer {
            padding: 16px 20px;
            border-top: 1px solid #2a2a2a;
            background: #111;
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }
        .slide-panel-footer input {
            flex: 1;
            background: #1a1a1a;
            border: 1px solid #333;
            color: #ffffff;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }
        .slide-panel-footer input:focus {
            outline: none;
            border-color: #7c6300;
            box-shadow: 0 0 0 3px rgba(124,99,0,0.15);
        }
        .slide-panel-footer button {
            background: linear-gradient(135deg, #7c6300, #988726);
            color: #ffffff;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .slide-panel-footer button:hover {
            background: linear-gradient(135deg, #988726, #b89f3d);
            box-shadow: 0 4px 12px rgba(124,99,0,0.25);
        }

        /* ===== Confirmation Modal ===== */
        .confirmation-modal {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            animation: fadeIn 0.25s ease;
        }
        .confirmation-modal.show { display: flex; }
        .confirmation-modal-content {
            background: linear-gradient(145deg, #1e1e1e, #161616);
            padding: 32px;
            border-radius: 16px;
            text-align: center;
            max-width: 380px;
            width: 90%;
            border: 1px solid rgba(124,99,0,0.3);
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            animation: slideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .confirmation-modal-content .modal-icon {
            width: 56px; height: 56px;
            background: rgba(255,68,68,0.1);
            border: 2px solid rgba(255,68,68,0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 24px;
            color: #ff4444;
        }
        .confirmation-modal-content h3 {
            color: #ffffff;
            margin: 0 0 8px 0;
            font-size: 1.1rem;
        }
        .confirmation-modal-content p {
            color: #888;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .confirmation-modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .confirmation-modal-buttons button {
            padding: 10px 24px;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .confirm-btn {
            background: linear-gradient(135deg, #ff4444, #d32f2f);
            color: #fff;
        }
        .confirm-btn:hover {
            box-shadow: 0 4px 16px rgba(255,68,68,0.3);
            transform: translateY(-1px);
        }
        .cancel-btn {
            background: #2a2a2a;
            color: #ccc;
            border: 1px solid #333;
        }
        .cancel-btn:hover {
            background: #333;
            border-color: #444;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: translateY(-20px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        /* ===== Responsive (Matching buy.php) ===== */
        @media (max-width: 768px) {
            .shell { padding: 20px 14px 50px 14px !important; }
            .form-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .shell { padding: 15px 10px 40px 10px !important; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="shell">
        <div class="main-container">
            <div class="content-section">
                <?php if (empty($skinBasedData)): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        No skin-based data found in the database. The table will be created automatically when the database is initialized.
                    </div>
                <?php else: ?>
                    <?php foreach ($skinBasedData as $data): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-palette"></i> Skin-Based Product Suggestions</h3>
                                <span class="badge">ID: <?php echo $data['id']; ?></span>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="ai_products.php">
                                    <input type="hidden" name="action" value="update_skinbased">
                                    <input type="hidden" name="id" value="<?php echo $data['id']; ?>">

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="question"><i class="fas fa-question-circle"></i> Question:</label>
                                            <input type="text" id="question" name="question" value="<?php echo htmlspecialchars($data['question']); ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="ai_message"><i class="fas fa-comment-dots"></i> AI Message:</label>
                                            <textarea id="ai_message" name="ai_message" required><?php echo htmlspecialchars($data['ai_message']); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="form-section">
                                        <h4><i class="fas fa-swatchbook"></i> Skin Tone Colors Configuration</h4>
                                        <div class="form-row">
                                            <?php
                                            $skinToneColors = json_decode($data['skin_tone_colors'], true);
                                            if ($skinToneColors):
                                                foreach ($skinToneColors as $tone => $colors):
                                            ?>
                                            <div class="form-group">
                                                <input type="text" id="tone_<?php echo $tone; ?>" name="skin_tone_colors[<?php echo $tone; ?>]" value="<?php echo ucfirst($tone); ?> Skin Tone" placeholder="Click to view colors" onclick="openSkinTonePanel('<?php echo $tone; ?>', '<?php echo htmlspecialchars(json_encode($colors)); ?>', <?php echo $data['id']; ?>)" readonly data-colors='<?php echo htmlspecialchars(json_encode($colors)); ?>'>
                                            </div>
                                            <?php endforeach; endif; ?>
                                        </div>
                                    </div>

                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Configuration
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Slide Panel Overlay -->
    <div class="slide-panel-overlay" id="slidePanelOverlay" onclick="closeSkinTonePanel()"></div>

    <!-- Slide Panel -->
    <div class="slide-panel" id="slidePanel">
        <div class="slide-panel-header">
            <h3 id="slidePanelTitle">Skin Tone Colors</h3>
            <button type="button" class="slide-panel-close" onclick="closeSkinTonePanel()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="slide-panel-body" id="slidePanelBody">
            <!-- Colors will be populated here -->
        </div>
        <div class="slide-panel-footer">
            <input type="text" id="addColorInput" placeholder="Enter new color" onkeypress="handleAddColorKeypress(event)">
            <button class="btn btn-primary" onclick="addNewColor()">Add Color</button>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="confirmation-modal-content">
            <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h3>Remove Color</h3>
            <p>Are you sure you want to remove this color?</p>
            <div class="confirmation-modal-buttons">
                <button class="cancel-btn" onclick="cancelRemove()">Cancel</button>
                <button class="confirm-btn" onclick="confirmRemove()">Remove</button>
            </div>
        </div>
    </div>

    <script>
        // Current tone being edited
        let currentTone = null;
        let currentColors = [];
        let colorToRemoveIndex = null;
        let currentRecordId = null;

        // Open slide panel with skin tone colors
        function openSkinTonePanel(tone, colorsJson, recordId) {
            const colors = JSON.parse(colorsJson);
            currentTone = tone;
            currentColors = colors;
            currentRecordId = recordId;

            const slidePanel = document.getElementById('slidePanel');
            const slidePanelOverlay = document.getElementById('slidePanelOverlay');
            const slidePanelTitle = document.getElementById('slidePanelTitle');
            const slidePanelBody = document.getElementById('slidePanelBody');
            const addColorInput = document.getElementById('addColorInput');

            // Set title
            slidePanelTitle.textContent = ucfirst(tone) + ' Skin Tone Colors';

            // Clear input
            addColorInput.value = '';

            // Populate colors
            renderColors();

            // Show slide panel
            slidePanel.classList.add('open');
            slidePanelOverlay.classList.add('show');

            // Focus on input
            addColorInput.focus();
        }

        // Render colors in the slide panel
        function renderColors() {
            const slidePanelBody = document.getElementById('slidePanelBody');
            slidePanelBody.innerHTML = '';

            currentColors.forEach(function(color, index) {
                const colorItem = document.createElement('div');
                colorItem.className = 'color-item';
                colorItem.innerHTML = '<div><i class="fas fa-circle"></i> ' + color + '</div><span class="remove-color" onclick="showRemoveConfirmation(' + index + ')">Remove</span>';
                slidePanelBody.appendChild(colorItem);
            });
        }

        // Show remove confirmation modal
        function showRemoveConfirmation(index) {
            colorToRemoveIndex = index;
            const confirmationModal = document.getElementById('confirmationModal');
            confirmationModal.classList.add('show');
        }

        // Confirm remove
        function confirmRemove() {
            if (colorToRemoveIndex !== null) {
                currentColors.splice(colorToRemoveIndex, 1);
                renderColors();
                updateDataColors();
                saveToDatabase();
                colorToRemoveIndex = null;
            }
            const confirmationModal = document.getElementById('confirmationModal');
            confirmationModal.classList.remove('show');
        }

        // Cancel remove
        function cancelRemove() {
            colorToRemoveIndex = null;
            const confirmationModal = document.getElementById('confirmationModal');
            confirmationModal.classList.remove('show');
        }

        // Add new color
        function addNewColor() {
            const addColorInput = document.getElementById('addColorInput');
            const newColor = addColorInput.value.trim();

            if (newColor && !currentColors.includes(newColor)) {
                currentColors.push(newColor);
                renderColors();
                updateDataColors();
                saveToDatabase();
                addColorInput.value = '';
                addColorInput.focus();
            }
        }

        // Handle Enter key in add color input
        function handleAddColorKeypress(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addNewColor();
            }
        }

        // Update data-colors attribute for the current tone
        function updateDataColors() {
            const inputField = document.getElementById('tone_' + currentTone);
            if (inputField) {
                inputField.setAttribute('data-colors', JSON.stringify(currentColors));
            }
        }

        // Save to database via AJAX
        function saveToDatabase() {
            // Get all skin tone colors from the form
            const skinToneInputs = document.querySelectorAll('input[name^="skin_tone_colors["]');
            const skinToneColors = {};

            skinToneInputs.forEach(function(input) {
                const tone = input.name.match(/\[(.*?)\]/)[1];
                const colors = JSON.parse(input.getAttribute('data-colors'));
                skinToneColors[tone] = colors;
            });

            // Send AJAX request to save to database
            fetch('ai_products.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'update_skin_tone_colors',
                    id: currentRecordId,
                    skin_tone_colors: JSON.stringify(skinToneColors)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Error saving to database:', data.error);
                    alert('Error saving to database: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving to database');
            });
        }

        // Close slide panel
        function closeSkinTonePanel() {
            const slidePanel = document.getElementById('slidePanel');
            const slidePanelOverlay = document.getElementById('slidePanelOverlay');

            if (slidePanel) slidePanel.classList.remove('open');
            if (slidePanelOverlay) slidePanelOverlay.classList.remove('show');

            // Reset current tone
            currentTone = null;
            currentColors = [];
            colorToRemoveIndex = null;
            currentRecordId = null;
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSkinTonePanel();
            }
        });

        // Helper function to capitalize first letter
        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        // Convert comma-separated colors to JSON format on submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const skinToneInputs = document.querySelectorAll('input[name^="skin_tone_colors["]');
            const skinToneColors = {};

            skinToneInputs.forEach(function(input) {
                const tone = input.name.match(/\[(.*?)\]/)[1];
                // Use data-colors attribute instead of input value
                const colors = JSON.parse(input.getAttribute('data-colors'));
                skinToneColors[tone] = colors;
            });

            // Create hidden input for JSON data
            const jsonInput = document.createElement('input');
            jsonInput.type = 'hidden';
            jsonInput.name = 'skin_tone_colors';
            jsonInput.value = JSON.stringify(skinToneColors);
            this.appendChild(jsonInput);

            // Remove individual color inputs from submission
            skinToneInputs.forEach(function(input) {
                input.name = '';
            });
        });
    </script>
</body>
</html>
