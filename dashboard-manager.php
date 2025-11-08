<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'manager') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch statistical data from database
try {
    // Total spots
    $total_spots_stmt = $pdo->query("SELECT COUNT(*) as total FROM parking_spots");
    $total_spots = $total_spots_stmt->fetch()['total'];
    
    // Available spots
    $available_spots_stmt = $pdo->query("SELECT COUNT(*) as available FROM parking_spots WHERE status = 'available'");
    $available_spots = $available_spots_stmt->fetch()['available'];
    
    // Occupied spots
    $occupied_spots_stmt = $pdo->query("SELECT COUNT(*) as occupied FROM parking_spots WHERE status = 'occupied'");
    $occupied_spots = $occupied_spots_stmt->fetch()['occupied'];
    
    // Maintenance spots
    $maintenance_spots_stmt = $pdo->query("SELECT COUNT(*) as maintenance FROM parking_spots WHERE status = 'maintenance'");
    $maintenance_spots = $maintenance_spots_stmt->fetch()['maintenance'];
    
    // Total users
    $total_users_stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'user'");
    $total_users = $total_users_stmt->fetch()['total'];
    
    // Active bookings
    $active_bookings_stmt = $pdo->query("SELECT COUNT(*) as active FROM bookings WHERE status = 'active'");
    $active_bookings = $active_bookings_stmt->fetch()['active'];
    
    // Today's revenue
    $today_revenue_stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as revenue 
        FROM bookings 
        WHERE DATE(created_at) = CURDATE() AND status IN ('active', 'completed')
    ");
    $today_revenue = $today_revenue_stmt->fetch()['revenue'];
    
    // Monthly revenue
    $monthly_revenue_stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as revenue 
        FROM bookings 
        WHERE MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())
        AND status IN ('active', 'completed')
    ");
    $monthly_revenue = $monthly_revenue_stmt->fetch()['revenue'];
    
    // Today's active bookings
    $today_bookings_stmt = $pdo->query("
        SELECT COUNT(*) as today 
        FROM bookings 
        WHERE DATE(created_at) = CURDATE() AND status = 'active'
    ");
    $today_bookings = $today_bookings_stmt->fetch()['today'];
    
    // Occupancy rate - Check for division by zero
    $occupancy_rate = 0;
    if ($total_spots > 0) {
        $occupancy_rate = round(($occupied_spots / $total_spots) * 100);
    }
    
    // Revenue chart data (last 7 days)
    $revenue_chart_stmt = $pdo->query("
        SELECT 
            DATE(created_at) as date,
            COALESCE(SUM(amount), 0) as daily_revenue
        FROM bookings 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND status IN ('active', 'completed')
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $revenue_data = $revenue_chart_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no data, create default data
    if (empty($revenue_data)) {
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $revenue_data[] = [
                'date' => $date,
                'daily_revenue' => 0
            ];
        }
    }
    
    // Peak hours
    $peak_hours_stmt = $pdo->query("
        SELECT 
            HOUR(start_time) as hour,
            COUNT(*) as bookings_count
        FROM bookings 
        WHERE DATE(created_at) = CURDATE()
        GROUP BY HOUR(start_time)
        ORDER BY bookings_count DESC
        LIMIT 1
    ");
    $peak_hour_data = $peak_hours_stmt->fetch();
    $peak_hour = $peak_hour_data ? $peak_hour_data['hour'] . ':00' : 'N/A';
    
    // Recent bookings
    $recent_bookings_stmt = $pdo->query("
        SELECT 
            b.*,
            u.username,
            ps.spot_number,
            pz.zone_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN parking_spots ps ON b.spot_id = ps.id
        LEFT JOIN parking_zones pz ON ps.zone_id = pz.id
        WHERE b.status = 'active'
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $recent_bookings = $recent_bookings_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Calculate percentages with zero division check
$available_percentage = $total_spots > 0 ? round(($available_spots / $total_spots) * 100) : 0;
$maintenance_percentage = $total_spots > 0 ? round(($maintenance_spots / $total_spots) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - SmartPark</title>
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
            background: var(--danger);
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

        .stat-change {
            font-size: 14px;
            font-weight: 600;
        }

        .positive {
            color: var(--success);
        }

        .negative {
            color: var(--danger);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-container, .recent-bookings {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .chart-container h2, .recent-bookings h2 {
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-wrapper {
            height: 300px;
            position: relative;
        }

        .booking-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
        }

        .booking-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .booking-item:last-child {
            border-bottom: none;
        }

        .booking-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--info);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
        }

        .booking-details {
            flex: 1;
        }

        .booking-spot {
            font-weight: 600;
            color: var(--dark);
        }

        .booking-user {
            color: #666;
            font-size: 14px;
        }

        .booking-time {
            color: #999;
            font-size: 12px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .info-card h3 {
            color: var(--dark);
            margin-bottom: 10px;
            font-size: 16px;
        }

        .info-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .manager-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .action-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            background: rgba(52, 152, 219, 0.1);
        }

        .action-card i {
            font-size: 32px;
            color: var(--secondary);
            margin-bottom: 10px;
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
        }

        .logout-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .occupancy-meter {
            margin-top: 10px;
        }

        .meter-bar {
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
        }

        .meter-fill {
            height: 100%;
            background: var(--success);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .meter-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        @media (max-width: 1024px) {
            .content-grid {
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
            <li><a href="#" class="active"><i class="fas fa-home"></i> <span class="nav-text">Dashboard</span></a></li>
            <li><a href="parking_maps_manager.php"><i class="fas fa-map-marked-alt"></i> <span class="nav-text">Parking Maps</span></a></li>
            <li><a href="booking_manager.php"><i class="fas fa-calendar-check"></i> <span class="nav-text">Parking Management</span></a></li>
            <li><a href="users_manager.php"><i class="fas fa-users"></i> <span class="nav-text">Users</span></a></li>
            <li><a href="payments_manager.php"><i class="fas fa-credit-card"></i> <span class="nav-text">Payments</span></a></li>
            <li><a href="reports_manager.php"><i class="fas fa-chart-bar"></i> <span class="nav-text">Reports</span></a></li>
            <li><a href="notifications_manager.php"><i class="fas fa-bell"></i> <span class="nav-text">Notifications</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Manager Dashboard</h1>
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
                <h3>Total Available Spots</h3>
                <div class="stat-value"><?php echo $available_spots; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> 
                    <?php echo $available_percentage; ?>% of capacity
                </div>
            </div>
            <div class="stat-card">
                <h3>Active Reservations</h3>
                <div class="stat-value"><?php echo $active_bookings; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-calendar-check"></i> 
                    <?php echo $today_bookings; ?> new today
                </div>
            </div>
            <div class="stat-card">
                <h3>Today's Revenue</h3>
                <div class="stat-value">$<?php echo number_format($today_revenue, 2); ?></div>
                <div class="stat-change <?php echo $today_revenue > 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-<?php echo $today_revenue > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i> 
                    Real-time tracking
                </div>
            </div>
            <div class="stat-card">
                <h3>System Occupancy</h3>
                <div class="stat-value"><?php echo $occupancy_rate; ?>%</div>
                <div class="occupancy-meter">
                    <div class="meter-bar">
                        <div class="meter-fill" style="width: <?php echo $occupancy_rate; ?>%"></div>
                    </div>
                    <div class="meter-text"><?php echo $occupied_spots; ?> of <?php echo $total_spots; ?> spots occupied</div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Revenue Chart -->
            <div class="chart-container">
                <h2><i class="fas fa-chart-line"></i> Revenue Overview - Last 7 Days</h2>
                <div class="chart-wrapper">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="recent-bookings">
                <h2><i class="fas fa-clock"></i> Recent Active Bookings</h2>
                <?php if (empty($recent_bookings)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>No active bookings</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_bookings as $booking): ?>
                    <div class="booking-item">
                        <div class="booking-icon">
                            <i class="fas fa-car"></i>
                        </div>
                        <div class="booking-details">
                            <div class="booking-spot">
                                Spot <?php echo $booking['spot_number']; ?> 
                                (<?php echo $booking['zone_name'] ?? 'Unknown Zone'; ?>)
                            </div>
                            <div class="booking-user"><?php echo htmlspecialchars($booking['username']); ?></div>
                            <div class="booking-time">
                                <?php echo date('M j, g:i A', strtotime($booking['created_at'])); ?>
                            </div>
                        </div>
                        <div style="font-weight: 600; color: var(--success);">
                            $<?php echo number_format($booking['amount'], 2); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Parking Information -->
        <div class="info-grid">
            <div class="info-card">
                <h3>Total Capacity</h3>
                <div class="info-value"><?php echo $total_spots; ?> spots</div>
            </div>
            <div class="info-card">
                <h3>Currently Available</h3>
                <div class="info-value">
                    <?php echo $available_spots; ?> spots (<?php echo $available_percentage; ?>%)
                </div>
            </div>
            <div class="info-card">
                <h3>Monthly Revenue</h3>
                <div class="info-value">$<?php echo number_format($monthly_revenue, 2); ?></div>
            </div>
            <div class="info-card">
                <h3>Active Users</h3>
                <div class="info-value"><?php echo $total_users; ?> users</div>
            </div>
        </div>

        <!-- Peak Hours & Maintenance -->
        <div class="info-grid">
            <div class="info-card">
                <h3>Peak Hours Today</h3>
                <div class="info-value"><?php echo $peak_hour; ?></div>
                <div style="font-size: 14px; color: #666; margin-top: 5px;">
                    Most bookings at this hour
                </div>
            </div>
            <div class="info-card">
                <h3>Under Maintenance</h3>
                <div class="info-value"><?php echo $maintenance_spots; ?> spots</div>
                <div style="font-size: 14px; color: #666; margin-top: 5px;">
                    <?php echo $maintenance_percentage; ?>% of total
                </div>
            </div>
            <div class="info-card">
                <h3>Occupied Spots</h3>
                <div class="info-value"><?php echo $occupied_spots; ?></div>
                <div style="font-size: 14px; color: #666; margin-top: 5px;">
                    <?php echo $occupancy_rate; ?>% occupancy rate
                </div>
            </div>
            <div class="info-card">
                <h3>Today's Bookings</h3>
                <div class="info-value"><?php echo $today_bookings; ?></div>
                <div style="font-size: 14px; color: #666; margin-top: 5px;">
                    New reservations today
                </div>
            </div>
        </div>

        <!-- Manager Actions -->
        <div class="manager-actions">
            <a href="booking_manager.php" class="action-card">
                <i class="fas fa-parking"></i>
                <h3>Manage Parking</h3>
                <p>Add, edit, and manage parking spots</p>
            </a>
            <a href="users_manager.php" class="action-card">
                <i class="fas fa-users-cog"></i>
                <h3>Manage Users</h3>
                <p>View and manage user accounts</p>
            </a>
            <a href="reports_manager.php" class="action-card">
                <i class="fas fa-file-alt"></i>
                <h3>Generate Reports</h3>
                <p>Create financial and usage reports</p>
            </a>
            <a href="settings_manager.php" class="action-card">
                <i class="fas fa-sliders-h"></i>
                <h3>System Settings</h3>
                <p>Configure parking system</p>
            </a>
        </div>
    </div>

    <script>
        // Revenue chart data
        const revenueData = {
            labels: [<?php
                $labels = [];
                foreach ($revenue_data as $data) {
                    $labels[] = "'" . date('M j', strtotime($data['date'])) . "'";
                }
                echo implode(', ', $labels);
            ?>],
            datasets: [{
                label: 'Daily Revenue ($)',
                data: [<?php
                    $values = [];
                    foreach ($revenue_data as $data) {
                        $values[] = $data['daily_revenue'] ?? 0;
                    }
                    echo implode(', ', $values);
                ?>],
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        };

        // Initialize chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'line',
            data: revenueData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

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

            // Add hover effects to action cards
            const actionCards = document.querySelectorAll('.action-card');
            actionCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Live data update simulation
            setInterval(() => {
                // Simple random update for display (can be replaced with real AJAX requests)
                const availableElement = document.querySelector('.stat-card:nth-child(1) .stat-value');
                const currentAvailable = parseInt(availableElement.textContent);
                const newAvailable = Math.max(0, currentAvailable + Math.floor(Math.random() * 3) - 1);
                availableElement.textContent = newAvailable;
                
                // Update occupancy rate
                const totalSpots = <?php echo $total_spots ?: 1; ?>;
                const newOccupancyRate = Math.round((totalSpots - newAvailable) / totalSpots * 100);
                document.querySelector('.stat-card:nth-child(4) .stat-value').textContent = newOccupancyRate + '%';
                document.querySelector('.meter-fill').style.width = newOccupancyRate + '%';
                document.querySelector('.meter-text').textContent = 
                    (totalSpots - newAvailable) + ' of ' + totalSpots + ' spots occupied';
                    
            }, 10000);
        });
    </script>
</body>
</html>