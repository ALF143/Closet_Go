<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: adminlogin.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-modern.css">
    <link rel="stylesheet" href="../css/admin_mobile_css.css">
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
    <style>
    /* Dark Mode Styles for Users Page */
    body.dark-mode .user-card {
        background: #16213e;
        border: 1px solid #0f3460;
    }
    
    body.dark-mode .user-avatar {
        background: #0f3460;
        color: #eee;
    }
    
    body.dark-mode .user-info h3 {
        color: #eee;
    }
    
    body.dark-mode .user-email {
        color: #bbb;
    }
    
    body.dark-mode .user-role {
        background: #0f3460;
        color: #eee;
    }
    
    body.dark-mode .user-status.active {
        background: #27ae60;
    }
    
    body.dark-mode .user-date {
        color: #bbb;
    }
    
    body.dark-mode .action-btn {
        background: #0f3460;
        border: 1px solid #16213e;
        color: #eee;
    }
    
    body.dark-mode .action-btn:hover {
        background: #16213e;
        color: #667eea;
    }
    
    body.dark-mode .action-btn-small {
        background: #0f3460;
        color: #eee;
    }
    
    body.dark-mode .action-btn-small:hover {
        background: #16213e;
        color: #667eea;
    }
    
    body.dark-mode .modal-content {
        background: #16213e;
        border: 1px solid #0f3460;
    }
    
    body.dark-mode .modal-content h2 {
        color: #eee;
    }
    
    body.dark-mode .modal-content label {
        color: #eee;
    }
    
    body.dark-mode .modal-content input,
    body.dark-mode .modal-content select {
        background: #0f3460;
        border: 1px solid #16213e;
        color: #eee;
    }
    
    body.dark-mode .content-section h2 {
        color: #eee;
    }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="shell">
        <section class="hero-copy">
            <div class="logo-mark"><span>US</span></div>
            <h1>Users <span class="h1-highlight">Management</span></h1>
            <p class="subtitle">Manage user accounts, view customer information, and handle user permissions in your admin panel.</p>
            <button class="cta-button" onclick="window.location.href='../index.php'">Back to Home</button>
        </section>

        <!-- Quick Actions -->
        <div class="content-section">
            <h2>Quick Actions</h2>
            <div class="quick-actions">
                <button class="action-btn" onclick="window.location.href='add_admin.php'">
                    <i class="fas fa-user-shield"></i> Add Admin
                </button>
                <button class="action-btn" onclick="openAddUserModal()">
                    <i class="fas fa-user-plus"></i> Add New User
                </button>
                <button class="action-btn">
                    <i class="fas fa-file-export"></i> Export Users
                </button>
                <button class="action-btn">
                    <i class="fas fa-envelope"></i> Send Notifications
                </button>
            </div>
        </div>

        <!-- Users Grid -->
        <div class="content-section">
            <h2>Registered Users</h2>
            <div class="users-grid">
                <!-- Sample User Cards -->
                <div class="user-card">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <h3>John Smith</h3>
                        <p class="user-email">john.smith@email.com</p>
                        <div class="user-meta">
                            <span class="user-role">Customer</span>
                            <span class="user-status active">Active</span>
                            <span class="user-date">Joined: Jan 15, 2024</span>
                        </div>
                    </div>
                    <div class="user-actions">
                        <button class="action-btn-small" onclick="editUser(1)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn-small danger" onclick="deleteUser(1)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                <div class="user-card">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <h3>Sarah Johnson</h3>
                        <p class="user-email">sarah.j@email.com</p>
                        <div class="user-meta">
                            <span class="user-role">Customer</span>
                            <span class="user-status active">Active</span>
                            <span class="user-date">Joined: Jan 20, 2024</span>
                        </div>
                    </div>
                    <div class="user-actions">
                        <button class="action-btn-small" onclick="editUser(2)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn-small danger" onclick="deleteUser(2)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                <div class="user-card">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <h3>Michael Davis</h3>
                        <p class="user-email">michael.d@email.com</p>
                        <div class="user-meta">
                            <span class="user-role premium">Premium</span>
                            <span class="user-status active">Active</span>
                            <span class="user-date">Joined: Feb 1, 2024</span>
                        </div>
                    </div>
                    <div class="user-actions">
                        <button class="action-btn-small" onclick="editUser(3)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn-small danger" onclick="deleteUser(3)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                <div class="user-card">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <h3>Emma Wilson</h3>
                        <p class="user-email">emma.w@email.com</p>
                        <div class="user-meta">
                            <span class="user-role">Customer</span>
                            <span class="user-status inactive">Inactive</span>
                            <span class="user-date">Joined: Feb 5, 2024</span>
                        </div>
                    </div>
                    <div class="user-actions">
                        <button class="action-btn-small" onclick="editUser(4)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn-small danger" onclick="deleteUser(4)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Statistics -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h3>Total Users</h3>
                    <p class="stat-number">1,234</p>
                    <span class="stat-change positive">+12% from last month</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <h3>Active Users</h3>
                    <p class="stat-number">892</p>
                    <span class="stat-change positive">+8% from last month</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">👑</div>
                <div class="stat-info">
                    <h3>New This Month</h3>
                    <p class="stat-number">156</p>
                    <span class="stat-change positive">+15% growth</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-info">
                    <h3>Premium Users</h3>
                    <p class="stat-number">342</p>
                    <span class="stat-change positive">+22% from last month</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New User</h2>
                <button type="button" class="close-panel-btn" onclick="closeAddUserModal()" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="userName">
                                <i class="fas fa-user"></i> Full Name
                            </label>
                            <input type="text" id="userName" name="userName" required placeholder="Enter full name">
                        </div>
                        <div class="form-group">
                            <label for="userEmail">
                                <i class="fas fa-envelope"></i> Email Address
                            </label>
                            <input type="email" id="userEmail" name="userEmail" required placeholder="user@email.com">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="userRole">
                                <i class="fas fa-user-tag"></i> Role
                            </label>
                            <select id="userRole" name="userRole" required>
                                <option value="">Select Role</option>
                                <option value="customer">Customer</option>
                                <option value="premium">Premium</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="userStatus">
                                <i class="fas fa-toggle-on"></i> Status
                            </label>
                            <select id="userStatus" name="userStatus" required>
                                <option value="">Select Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="userPassword">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <input type="password" id="userPassword" name="userPassword" placeholder="Enter password">
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('addUserForm').reset();
        }

        // User management functions
        function editUser(userId) {
            alert('Edit user functionality for user ID: ' + userId);
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                alert('Delete user functionality for user ID: ' + userId);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addUserModal');
            if (event.target === modal) {
                closeAddUserModal();
            }
        }
    </script>
</body>
</html>
