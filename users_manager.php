<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'manager') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$search_query = '';
$user_type_filter = '';

// Handle user actions (delete, edit)
if(isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $target_id = $_GET['id'];
    
    try {
        if($action == 'delete' && $target_id != $user_id) {
            $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $delete_stmt->execute([$target_id]);
            $message = "User deleted successfully!";
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle search and filter
if(isset($_GET['search'])) {
    $search_query = $_GET['search'];
}

if(isset($_GET['user_type'])) {
    $user_type_filter = $_GET['user_type'];
}

// Fetch users with filters
try {
    $sql = "SELECT * FROM users WHERE 1=1";
    $params = [];
    
    if(!empty($search_query)) {
        $sql .= " AND (username LIKE ? OR email LIKE ?)";
        $search_param = "%$search_query%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if(!empty($user_type_filter)) {
        $sql .= " AND user_type = ?";
        $params[] = $user_type_filter;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $users_stmt = $pdo->prepare($sql);
    $users_stmt->execute($params);
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user counts for statistics
    $total_users_stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $total_users_stmt->fetch()['total'];
    
    $regular_users_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'user'");
    $regular_users = $regular_users_stmt->fetch()['count'];
    
    $manager_users_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'manager'");
    $manager_users = $manager_users_stmt->fetch()['count'];
    
    $new_today_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()");
    $new_today = $new_today_stmt->fetch()['count'];
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - SmartPark</title>
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--secondary);
        }

        .stat-card:nth-child(2)::before {
            background: var(--success);
        }

        .stat-card:nth-child(3)::before {
            background: var(--warning);
        }

        .stat-card:nth-child(4)::before {
            background: var(--info);
        }

        .stat-card h3 {
            color: var(--dark);
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        /* Controls Section */
        .controls {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        .controls h2 {
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-control {
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

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
            transform: translateY(-2px);
        }

        /* Users Table */
        .users-table-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        .table-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h2 {
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

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 14px;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        /* Message Alert */
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

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
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
            <li><a href="dashboard-manager.php"><i class="fas fa-home"></i> <span class="nav-text">Dashboard</span></a></li>
            <li><a href="parking_maps_manager.php"><i class="fas fa-map-marked-alt"></i> <span class="nav-text">Parking Maps</span></a></li>
            <li><a href="booking_manager.php"><i class="fas fa-calendar-check"></i> <span class="nav-text">Parking Management</span></a></li>
            <li><a href="users_manager.php" class="active"><i class="fas fa-users"></i> <span class="nav-text">Users</span></a></li>
            <li><a href="payments_manager.php"><i class="fas fa-credit-card"></i> <span class="nav-text">Payments</span></a></li>
            <li><a href="reports_manager.php"><i class="fas fa-chart-bar"></i> <span class="nav-text">Reports</span></a></li>
            <li><a href="notifications_manager.php"><i class="fas fa-bell"></i> <span class="nav-text">Notifications</span></a></li>
            <li><a href="settings_manager.php"><i class="fas fa-cog"></i> <span class="nav-text">Settings</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>User Management</h1>
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
            <div class="alert <?php echo strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-change">
                    All registered users in system
                </div>
            </div>
            <div class="stat-card">
                <h3>Regular Users</h3>
                <div class="stat-value"><?php echo $regular_users; ?></div>
                <div class="stat-change">
                    Users with parking access
                </div>
            </div>
            <div class="stat-card">
                <h3>Manager Accounts</h3>
                <div class="stat-value"><?php echo $manager_users; ?></div>
                <div class="stat-change">
                    Administrative accounts
                </div>
            </div>
            <div class="stat-card">
                <h3>New Today</h3>
                <div class="stat-value"><?php echo $new_today; ?></div>
                <div class="stat-change">
                    Users registered today
                </div>
            </div>
        </div>

        <!-- Search and Filter Controls -->
        <div class="controls">
            <h2><i class="fas fa-filter"></i> Filter Users</h2>
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search">Search Users</label>
                    <input type="text" id="search" name="search" class="form-control" 
                           placeholder="Search by username or email..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="form-group">
                    <label for="user_type">User Type</label>
                    <select id="user_type" name="user_type" class="form-control">
                        <option value="">All Types</option>
                        <option value="user" <?php echo $user_type_filter == 'user' ? 'selected' : ''; ?>>Regular User</option>
                        <option value="manager" <?php echo $user_type_filter == 'manager' ? 'selected' : ''; ?>>Manager</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="users-table-container">
            <div class="table-header">
                <h2><i class="fas fa-users"></i> All Users</h2>
                <a href="add_user.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add New User
                </a>
            </div>

            <?php if(empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No Users Found</h3>
                    <p>No users match your current filters. Try adjusting your search criteria.</p>
                </div>
            <?php else: ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="user-avatar-small">
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></div>
                                        <div style="font-size: 12px; color: #666;">ID: <?php echo $user['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="user-badge <?php echo $user['user_type'] == 'manager' ? 'badge-manager' : 'badge-user'; ?>">
                                    <?php echo ucfirst($user['user_type']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php if($user['id'] != $user_id): ?>
                                    <a href="?action=delete&id=<?php echo $user['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-danger btn-sm" disabled title="Cannot delete your own account">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="stats-grid">
            <a href="add_user.php" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer;">
                <h3>Add New User</h3>
                <div class="stat-value" style="color: var(--success);">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-change">
                    Create a new user account
                </div>
            </a>
            <a href="export_users.php" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer;">
                <h3>Export Users</h3>
                <div class="stat-value" style="color: var(--info);">
                    <i class="fas fa-file-export"></i>
                </div>
                <div class="stat-change">
                    Export user data to CSV
                </div>
            </a>
            <a href="user_reports.php" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer;">
                <h3>User Reports</h3>
                <div class="stat-value" style="color: var(--warning);">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="stat-change">
                    View user statistics
                </div>
            </a>
            <a href="bulk_actions.php" class="stat-card" style="text-decoration: none; color: inherit; cursor: pointer;">
                <h3>Bulk Actions</h3>
                <div class="stat-value" style="color: var(--secondary);">
                    <i class="fas fa-cogs"></i>
                </div>
                <div class="stat-change">
                    Manage multiple users
                </div>
            </a>
        </div>
    </div>

    <script>
        // Make sidebar interactive
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-links a');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Remove active class from all links
                    navLinks.forEach(l => l.classList.remove('active'));
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                });
            });

            // Auto-hide success message after 5 seconds
            const alert = document.querySelector('.alert-success');
            if(alert) {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            }

            // Add hover effects to stat cards that are links
            const statCards = document.querySelectorAll('.stat-card[style*="cursor: pointer"]');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
                });
            });

            // Confirm before delete
            const deleteButtons = document.querySelectorAll('a[href*="action=delete"]');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if(!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>