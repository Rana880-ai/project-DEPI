<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'manager') {
    header("Location: index.php");
    exit();
}

// Export data to CSV
if(isset($_GET['export'])) {
    try {
        $sql = "SELECT id, username, email, user_type, created_at FROM users ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Set file headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=users_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // File header
        fputcsv($output, ['ID', 'Username', 'Email', 'User Type', 'Registration Date']);
        
        // Data
        foreach($users as $user) {
            fputcsv($output, [
                $user['id'],
                $user['username'],
                $user['email'],
                $user['user_type'],
                $user['created_at']
            ]);
        }
        
        fclose($output);
        exit;
        
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

// Get user statistics
try {
    $total_users_stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $total_users_stmt->fetch()['total'];
    
    $regular_users_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'user'");
    $regular_users = $regular_users_stmt->fetch()['count'];
    
    $manager_users_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'manager'");
    $manager_users = $manager_users_stmt->fetch()['count'];
    
    $new_week_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $new_week = $new_week_stmt->fetch()['count'];
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Users - SmartPark</title>
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

        .export-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            max-width: 800px;
            margin: 0 auto;
        }

        .export-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .export-header h2 {
            color: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--light);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
        }

        .export-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .export-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .export-card:hover {
            border-color: var(--secondary);
            transform: translateY(-5px);
        }

        .export-icon {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--secondary);
        }

        .export-card h3 {
            color: var(--dark);
            margin-bottom: 10px;
        }

        .export-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
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
            margin-top: 30px;
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
            .export-container {
                padding: 20px;
            }
            .action-buttons {
                flex-direction: column;
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
            <li><a href="export_users.php" class="active"><i class="fas fa-file-export"></i> <span class="nav-text">Export Data</span></a></li>
            <li><a href="payments_manager.php"><i class="fas fa-credit-card"></i> <span class="nav-text">Payments</span></a></li>
            <li><a href="reports_manager.php"><i class="fas fa-chart-bar"></i> <span class="nav-text">Reports</span></a></li>
            <li><a href="notifications_manager.php"><i class="fas fa-bell"></i> <span class="nav-text">Notifications</span></a></li>
            <li><a href="settings_manager.php"><i class="fas fa-cog"></i> <span class="nav-text">Settings</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Export User Data</h1>
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

        <div class="export-container">
            <div class="export-header">
                <h2><i class="fas fa-file-export"></i> Export User Data</h2>
                <p>Choose the appropriate export method to download user data</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_users; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $regular_users; ?></div>
                    <div class="stat-label">Regular Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $manager_users; ?></div>
                    <div class="stat-label">Managers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $new_week; ?></div>
                    <div class="stat-label">New This Week</div>
                </div>
            </div>

            <div class="export-options">
                <div class="export-card">
                    <div class="export-icon">
                        <i class="fas fa-file-csv"></i>
                    </div>
                    <h3>Export to CSV</h3>
                    <p>CSV file that can be opened in Excel or any spreadsheet software</p>
                    <a href="?export=csv" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download CSV
                    </a>
                </div>

                <div class="export-card">
                    <div class="export-icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <h3>Export to Excel</h3>
                    <p>Excel file with advanced formatting and enhanced tables</p>
                    <button class="btn btn-success" onclick="exportToExcel()">
                        <i class="fas fa-download"></i> Download Excel
                    </button>
                </div>

                <div class="export-card">
                    <div class="export-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <h3>Export to PDF</h3>
                    <p>Organized PDF report with statistics and charts</p>
                    <button class="btn btn-danger" onclick="exportToPDF()">
                        <i class="fas fa-download"></i> Download PDF
                    </button>
                </div>
            </div>

            <div class="export-options">
                <div class="export-card">
                    <div class="export-icon">
                        <i class="fas fa-print"></i>
                    </div>
                    <h3>Print Report</h3>
                    <p>Print user report in print-friendly format</p>
                    <button class="btn btn-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>

                <div class="export-card">
                    <div class="export-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Statistical Report</h3>
                    <p>Detailed report with statistics and charts</p>
                    <a href="user_reports.php" class="btn btn-primary">
                        <i class="fas fa-chart-pie"></i> View Reports
                    </a>
                </div>
            </div>

            <div class="action-buttons">
                <a href="users_manager.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to User Management
                </a>
                <a href="manager_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <script>
        function exportToExcel() {
            alert('This feature will be developed soon. Currently, you can use CSV export which works excellently with Excel.');
            // You can open a new window for Excel export
            window.open('export_users.php?export=excel', '_blank');
        }

        function exportToPDF() {
            alert('PDF export feature is under development. Currently, you can use print or CSV export.');
            // You can open a new window for PDF export
            window.open('export_users.php?export=pdf', '_blank');
        }

        // Show confirmation before export
        document.addEventListener('DOMContentLoaded', function() {
            const exportLinks = document.querySelectorAll('a[href*="export="]');
            exportLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if(!confirm('Do you want to download the user data export file?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>