<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'manager') {
    header("Location: index.php");
    exit();
}

$message = '';
$message_type = '';

// Process bulk actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['user_ids']) && isset($_POST['bulk_action'])) {
        $user_ids = $_POST['user_ids'];
        $bulk_action = $_POST['bulk_action'];
        
        if(empty($user_ids)) {
            $message = "No users selected!";
            $message_type = 'error';
        } else {
            try {
                $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
                
                switch($bulk_action) {
                    case 'delete':
                        // Prevent deleting current user
                        if(in_array($_SESSION['user_id'], $user_ids)) {
                            $message = "You cannot delete your own account!";
                            $message_type = 'error';
                        } else {
                            $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
                            $delete_stmt->execute($user_ids);
                            $affected = $delete_stmt->rowCount();
                            $message = "Successfully deleted $affected users!";
                            $message_type = 'success';
                        }
                        break;
                        
                    case 'change_type_user':
                        $update_stmt = $pdo->prepare("UPDATE users SET user_type = 'user' WHERE id IN ($placeholders)");
                        $update_stmt->execute($user_ids);
                        $affected = $update_stmt->rowCount();
                        $message = "Successfully converted $affected users to regular users!";
                        $message_type = 'success';
                        break;
                        
                    case 'change_type_manager':
                        $update_stmt = $pdo->prepare("UPDATE users SET user_type = 'manager' WHERE id IN ($placeholders)");
                        $update_stmt->execute($user_ids);
                        $affected = $update_stmt->rowCount();
                        $message = "Successfully converted $affected users to managers!";
                        $message_type = 'success';
                        break;
                        
                    case 'export_selected':
                        // Will handle export later
                        $message = "Selected users will be exported...";
                        $message_type = 'info';
                        break;
                        
                    default:
                        $message = "Unknown action!";
                        $message_type = 'error';
                }
                
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Get all users
try {
    $users_stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_users_stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $total_users_stmt->fetch()['total'];
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Actions - SmartPark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #17a2b8;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --sidebar-width: 250px;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
        }

        .logo {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }

        .logo h1 {
            color: var(--primary);
            font-size: 24px;
            font-weight: 700;
        }

        .nav-links {
            list-style: none;
            padding: 0 20px;
        }

        .nav-links li {
            margin-bottom: 10px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            text-decoration: none;
            color: var(--dark);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover, .nav-links a.active {
            background: var(--secondary);
            color: white;
        }

        .nav-links i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--warning);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
        }

        .manager-badge {
            background: var(--warning);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .bulk-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        .bulk-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .bulk-header h2 {
            color: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .bulk-actions {
            background: var(--light);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .action-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-card:hover {
            border-color: var(--secondary);
            transform: translateY(-2px);
        }

        .action-card.selected {
            border-color: var(--secondary);
            background: rgba(52, 152, 219, 0.1);
        }

        .action-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .action-delete { color: var(--danger); }
        .action-type { color: var(--warning); }
        .action-export { color: var(--success); }

        .form-group {
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .users-table-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .table-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            background: var(--light);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid #ddd;
        }

        .users-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--info);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }

        .user-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-user {
            background: var(--info);
            color: white;
        }

        .badge-manager {
            background: var(--warning);
            color: white;
        }

        .select-all {
            margin-bottom: 15px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            .sidebar .logo h1, .sidebar .nav-text {
                display: none;
            }
            .main-content {
                margin-left: 70px;
            }
            .nav-links a {
                justify-content: center;
            }
            .nav-links i {
                margin-right: 0;
                font-size: 20px;
            }
            .users-table {
                display: block;
                overflow-x: auto;
            }
            .action-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h1>SmartPark</h1>
        </div>
        <ul class="nav-links">
            <li><a href="manager_dashboard.php"><i class="fas fa-home"></i> <span class="nav-text">Dashboard</span></a></li>
            <li><a href="parking_maps_manager.php"><i class="fas fa-map-marked-alt"></i> <span class="nav-text">Parking Maps</span></a></li>
            <li><a href="booking_manager.php"><i class="fas fa-calendar-check"></i> <span class="nav-text">Booking Management</span></a></li>
            <li><a href="users_manager.php"><i class="fas fa-users"></i> <span class="nav-text">Users</span></a></li>
            <li><a href="add_user.php"><i class="fas fa-user-plus"></i> <span class="nav-text">Add User</span></a></li>
            <li><a href="export_users.php"><i class="fas fa-file-export"></i> <span class="nav-text">Export Data</span></a></li>
            <li><a href="user_reports.php"><i class="fas fa-chart-pie"></i> <span class="nav-text">User Reports</span></a></li>
            <li><a href="bulk_actions.php" class="active"><i class="fas fa-cogs"></i> <span class="nav-text">Bulk Actions</span></a></li>
            <li><a href="payments_manager.php"><i class="fas fa-credit-card"></i> <span class="nav-text">Payments</span></a></li>
            <li><a href="reports_manager.php"><i class="fas fa-chart-bar"></i> <span class="nav-text">Reports</span></a></li>
            <li><a href="notifications_manager.php"><i class="fas fa-bell"></i> <span class="nav-text">Notifications</span></a></li>
            <li><a href="settings_manager.php"><i class="fas fa-cog"></i> <span class="nav-text">Settings</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Bulk User Actions</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="manager-badge">Manager</div>
                </div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <!-- Message Alert -->
        <?php if(!empty($message)): ?>
            <div class="alert <?php 
                if($message_type == 'success') echo 'alert-success';
                elseif($message_type == 'error') echo 'alert-error';
                else echo 'alert-info';
            ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="bulk-container">
            <div class="bulk-header">
                <h2><i class="fas fa-cogs"></i> Bulk Actions</h2>
                <p>Choose the appropriate action and select the users you want to apply it to</p>
            </div>

            <form method="POST" id="bulkForm">
                <div class="bulk-actions">
                    <h3><i class="fas fa-tasks"></i> Choose Action Type:</h3>
                    <div class="action-grid" id="actionGrid">
                        <div class="action-card" data-action="delete">
                            <div class="action-icon action-delete">
                                <i class="fas fa-trash"></i>
                            </div>
                            <div class="action-title">Delete Users</div>
                            <div class="action-desc">Permanently delete selected users</div>
                        </div>
                        <div class="action-card" data-action="change_type_user">
                            <div class="action-icon action-type">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="action-title">Convert to Regular User</div>
                            <div class="action-desc">Convert managers to regular users</div>
                        </div>
                        <div class="action-card" data-action="change_type_manager">
                            <div class="action-icon action-type">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="action-title">Convert to Manager</div>
                            <div class="action-desc">Grant manager privileges to users</div>
                        </div>
                        <div class="action-card" data-action="export_selected">
                            <div class="action-icon action-export">
                                <i class="fas fa-file-export"></i>
                            </div>
                            <div class="action-title">Export Selected</div>
                            <div class="action-desc">Export data of selected users</div>
                        </div>
                    </div>

                    <input type="hidden" name="bulk_action" id="bulkAction" value="">

                    <div class="form-group">
                        <label for="action_confirm">Action Confirmation:</label>
                        <input type="text" id="action_confirm" class="form-control" 
                               placeholder="Type 'delete' to proceed..." style="display: none;">
                    </div>

                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" id="executeBtn" disabled>
                            <i class="fas fa-play"></i> Execute Action
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>

                <div class="users-table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-users"></i> Users List</h3>
                        <div class="select-all">
                            <label>
                                <input type="checkbox" id="selectAll"> Select All
                            </label>
                            <span id="selectedCount">0 users selected</span>
                        </div>
                    </div>

                    <?php if(empty($users)): ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-users-slash" style="font-size: 48px; margin-bottom: 15px;"></i>
                            <h3>No Users Found</h3>
                            <p>There are no user accounts in the system</p>
                        </div>
                    <?php else: ?>
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">Select</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Registration Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $user): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" 
                                               class="user-checkbox" 
                                               <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="user-avatar-small">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></div>
                                                <?php if($user['id'] == $_SESSION['user_id']): ?>
                                                <div style="font-size: 12px; color: var(--warning);">(You)</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="user-badge <?php echo $user['user_type'] == 'manager' ? 'badge-manager' : 'badge-user'; ?>">
                                            <?php echo ucfirst($user['user_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="action-buttons" style="justify-content: center;">
            <a href="users_manager.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to User Management
            </a>
            <a href="manager_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const actionCards = document.querySelectorAll('.action-card');
            const bulkActionInput = document.getElementById('bulkAction');
            const executeBtn = document.getElementById('executeBtn');
            const actionConfirm = document.getElementById('action_confirm');
            const selectAll = document.getElementById('selectAll');
            const userCheckboxes = document.querySelectorAll('.user-checkbox');
            const selectedCount = document.getElementById('selectedCount');

            // Select action
            actionCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selection from all cards
                    actionCards.forEach(c => c.classList.remove('selected'));
                    // Select clicked card
                    this.classList.add('selected');
                    
                    const action = this.dataset.action;
                    bulkActionInput.value = action;
                    
                    // Show confirmation field for dangerous actions
                    if(action === 'delete') {
                        actionConfirm.style.display = 'block';
                        actionConfirm.placeholder = "Type 'delete' to confirm...";
                    } else {
                        actionConfirm.style.display = 'none';
                    }
                    
                    updateExecuteButton();
                });
            });

            // Update execute button state
            function updateExecuteButton() {
                const hasAction = bulkActionInput.value !== '';
                const hasSelectedUsers = document.querySelectorAll('.user-checkbox:checked').length > 0;
                let confirmValid = true;
                
                if(bulkActionInput.value === 'delete') {
                    confirmValid = actionConfirm.value === 'delete';
                }
                
                executeBtn.disabled = !(hasAction && hasSelectedUsers && confirmValid);
            }

            // Select/Deselect all
            selectAll.addEventListener('change', function() {
                userCheckboxes.forEach(checkbox => {
                    if(!checkbox.disabled) {
                        checkbox.checked = this.checked;
                    }
                });
                updateSelectedCount();
                updateExecuteButton();
            });

            // Update selected users count
            function updateSelectedCount() {
                const selected = document.querySelectorAll('.user-checkbox:checked').length;
                selectedCount.textContent = selected + ' users selected';
            }

            // Update when user selection changes
            userCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectedCount();
                    updateExecuteButton();
                    
                    // Update "Select All" state
                    const allChecked = document.querySelectorAll('.user-checkbox:not(:disabled)').length === 
                                      document.querySelectorAll('.user-checkbox:not(:disabled):checked').length;
                    selectAll.checked = allChecked;
                });
            });

            // Validate confirmation field
            actionConfirm.addEventListener('input', function() {
                updateExecuteButton();
            });

            // Confirm before execution
            document.getElementById('bulkForm').addEventListener('submit', function(e) {
                const action = bulkActionInput.value;
                const selectedCount = document.querySelectorAll('.user-checkbox:checked').length;
                
                let message = '';
                if(action === 'delete') {
                    message = `Are you sure you want to delete ${selectedCount} users? This action cannot be undone!`;
                } else if(action === 'change_type_user') {
                    message = `Do you want to convert ${selectedCount} users to regular users?`;
                } else if(action === 'change_type_manager') {
                    message = `Do you want to grant manager privileges to ${selectedCount} users?`;
                } else if(action === 'export_selected') {
                    message = `Do you want to export data for ${selectedCount} users?`;
                }
                
                if(!confirm(message)) {
                    e.preventDefault();
                }
            });

            // Reset form
            window.resetForm = function() {
                actionCards.forEach(c => c.classList.remove('selected'));
                bulkActionInput.value = '';
                actionConfirm.style.display = 'none';
                actionConfirm.value = '';
                userCheckboxes.forEach(c => c.checked = false);
                selectAll.checked = false;
                updateSelectedCount();
                updateExecuteButton();
            };

            // Initialize counter
            updateSelectedCount();
        });

        // Auto-hide messages
        const alert = document.querySelector('.alert');
        if(alert) {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        }
    </script>
</body>
</html>