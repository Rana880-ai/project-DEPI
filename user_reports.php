<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'manager') {
    header("Location: index.php");
    exit();
}

// Get user statistics
try {
    // Basic statistics
    $total_users_stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $total_users_stmt->fetch()['total'];
    
    $regular_users_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'user'");
    $regular_users = $regular_users_stmt->fetch()['count'];
    
    $manager_users_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'manager'");
    $manager_users = $manager_users_stmt->fetch()['count'];
    
    // Registration statistics
    $new_today_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()");
    $new_today = $new_today_stmt->fetch()['count'];
    
    $new_week_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $new_week = $new_week_stmt->fetch()['count'];
    
    $new_month_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $new_month = $new_month_stmt->fetch()['count'];
    
    // Active users (have bookings)
    $active_users_stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as count FROM bookings WHERE start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $active_users = $active_users_stmt->fetch()['count'];
    
    // User distribution by month
    $monthly_growth_stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $monthly_growth = $monthly_growth_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Users by type
    $users_by_type_stmt = $pdo->query("
        SELECT user_type, COUNT(*) as count 
        FROM users 
        GROUP BY user_type
    ");
    $users_by_type = $users_by_type_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent users
    $recent_users_stmt = $pdo->query("
        SELECT username, email, user_type, created_at 
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recent_users = $recent_users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Prepare data for charts
$monthly_labels = [];
$monthly_data = [];
foreach($monthly_growth as $month) {
    $monthly_labels[] = date('M Y', strtotime($month['month']));
    $monthly_data[] = $month['count'];
}

$type_labels = [];
$type_data = [];
$type_colors = ['#3498db', '#f39c12'];
foreach($users_by_type as $type) {
    $type_labels[] = ucfirst($type['user_type']);
    $type_data[] = $type['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Reports - SmartPark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .stat-card:nth-child(2)::before { background: var(--success); }
        .stat-card:nth-child(3)::before { background: var(--warning); }
        .stat-card:nth-child(4)::before { background: var(--info); }
        .stat-card:nth-child(5)::before { background: #9b59b6; }
        .stat-card:nth-child(6)::before { background: #1abc9c; }

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

        .stat-change {
            font-size: 14px;
            color: #666;
        }

        .charts-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .chart-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h3 {
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .recent-users {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
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

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        @media (max-width: 1024px) {
            .charts-container {
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
            .stats-grid {
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
            <li><a href="user_reports.php" class="active"><i class="fas fa-chart-pie"></i> <span class="nav-text">User Reports</span></a></li>
            <li><a href="payments_manager.php"><i class="fas fa-credit-card"></i> <span class="nav-text">Payments</span></a></li>
            <li><a href="reports_manager.php"><i class="fas fa-chart-bar"></i> <span class="nav-text">Reports</span></a></li>
            <li><a href="notifications_manager.php"><i class="fas fa-bell"></i> <span class="nav-text">Notifications</span></a></li>
            <li><a href="settings_manager.php"><i class="fas fa-cog"></i> <span class="nav-text">Settings</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>User Reports & Statistics</h1>
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

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-change">All registered users</div>
            </div>
            <div class="stat-card">
                <h3>Regular Users</h3>
                <div class="stat-value"><?php echo $regular_users; ?></div>
                <div class="stat-change"><?php echo round(($regular_users/$total_users)*100, 1); ?>% of total</div>
            </div>
            <div class="stat-card">
                <h3>Managers</h3>
                <div class="stat-value"><?php echo $manager_users; ?></div>
                <div class="stat-change"><?php echo round(($manager_users/$total_users)*100, 1); ?>% of total</div>
            </div>
            <div class="stat-card">
                <h3>New Today</h3>
                <div class="stat-value"><?php echo $new_today; ?></div>
                <div class="stat-change">Registered today</div>
            </div>
            <div class="stat-card">
                <h3>New This Week</h3>
                <div class="stat-value"><?php echo $new_week; ?></div>
                <div class="stat-change">Registered in last 7 days</div>
            </div>
            <div class="stat-card">
                <h3>Active Users</h3>
                <div class="stat-value"><?php echo $active_users; ?></div>
                <div class="stat-change">Had bookings in last 30 days</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-container">
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line"></i> User Growth Over 12 Months</h3>
                </div>
                <div class="chart-container">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie"></i> User Distribution by Type</h3>
                </div>
                <div class="chart-container">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="recent-users">
            <div class="chart-header">
                <h3><i class="fas fa-users"></i> Recently Registered Users</h3>
            </div>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Registration Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
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
        </div>

        <div class="action-buttons">
            <a href="export_users.php" class="btn btn-primary">
                <i class="fas fa-file-export"></i> Export Reports
            </a>
            <a href="users_manager.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to User Management
            </a>
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>

    <script>
        // Monthly user growth
        const growthCtx = document.getElementById('growthChart').getContext('2d');
        const growthChart = new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: 'Number of Users',
                    data: <?php echo json_encode($monthly_data); ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // User distribution by type
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        const typeChart = new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($type_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($type_data); ?>,
                    backgroundColor: ['#3498db', '#f39c12'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Update charts on window resize
        window.addEventListener('resize', function() {
            growthChart.resize();
            typeChart.resize();
        });
    </script>
</body>
</html>