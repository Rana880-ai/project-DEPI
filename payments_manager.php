<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'manager') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// معالجة البحث والتصفية
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// بناء استعلام المدفوعات مع التصفية
$query = "
    SELECT 
        b.*,
        u.username,
        u.email,
        ps.spot_number,
        pz.zone_name,
        m.mall_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN parking_spots ps ON b.spot_id = ps.id
    LEFT JOIN parking_zones pz ON ps.zone_id = pz.id
    LEFT JOIN malls m ON pz.mall_id = m.id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR b.vehicle_plate LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter)) {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(b.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(b.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY b.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // إحصائيات المدفوعات
    $total_revenue_stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM bookings 
        WHERE status IN ('active', 'completed')
    ");
    $total_revenue = $total_revenue_stmt->fetch()['total'];

    $today_revenue_stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as revenue 
        FROM bookings 
        WHERE DATE(created_at) = CURDATE() AND status IN ('active', 'completed')
    ");
    $today_revenue = $today_revenue_stmt->fetch()['revenue'];

    $monthly_revenue_stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as revenue 
        FROM bookings 
        WHERE MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())
        AND status IN ('active', 'completed')
    ");
    $monthly_revenue = $monthly_revenue_stmt->fetch()['revenue'];

    // إحصائيات حسب الحالة
    $status_stats_stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count,
            COALESCE(SUM(amount), 0) as amount
        FROM bookings 
        GROUP BY status
    ");
    $status_stats = $status_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Management - SmartPark</title>
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

        .filters-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--secondary);
            color: var(--secondary);
        }

        .btn-outline:hover {
            background: var(--secondary);
            color: white;
        }

        .table-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            overflow-x: auto;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payments-table th,
        .payments-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .payments-table th {
            background: var(--light);
            color: var(--dark);
            font-weight: 600;
        }

        .payments-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .amount {
            font-weight: 700;
            color: var(--success);
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
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
            .filters-form {
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
            <li><a href="dashboard-manager.php"><i class="fas fa-home"></i> <span class="nav-text">Dashboard</span></a></li>
            <li><a href="parking_maps_manager.php"><i class="fas fa-map-marked-alt"></i> <span class="nav-text">Parking Maps</span></a></li>
            <li><a href="booking_manager.php"><i class="fas fa-calendar-check"></i> <span class="nav-text">Parking Management</span></a></li>
            <li><a href="users_manager.php"><i class="fas fa-users"></i> <span class="nav-text">Users</span></a></li>
            <li><a href="payments_manager.php" class="active"><i class="fas fa-credit-card"></i> <span class="nav-text">Payments</span></a></li>
            <li><a href="reports_manager.php"><i class="fas fa-chart-bar"></i> <span class="nav-text">Reports</span></a></li>
            <li><a href="notifications_manager.php"><i class="fas fa-bell"></i> <span class="nav-text">Notifications</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Payments Management</h1>
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
                <h3>Total Revenue</h3>
                <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                <div style="font-size: 14px; color: #666;">All time payments</div>
            </div>
            <div class="stat-card">
                <h3>Today's Revenue</h3>
                <div class="stat-value">$<?php echo number_format($today_revenue, 2); ?></div>
                <div style="font-size: 14px; color: #666;">Daily income</div>
            </div>
            <div class="stat-card">
                <h3>Monthly Revenue</h3>
                <div class="stat-value">$<?php echo number_format($monthly_revenue, 2); ?></div>
                <div style="font-size: 14px; color: #666;">Current month</div>
            </div>
            <div class="stat-card">
                <h3>Total Bookings</h3>
                <div class="stat-value"><?php echo count($bookings); ?></div>
                <div style="font-size: 14px; color: #666;">All reservations</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" class="form-control" 
                           placeholder="Username, Email, or Plate..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_from">From Date</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="form-group">
                    <label for="date_to">To Date</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="payments_manager.php" class="btn btn-outline" style="margin-top: 5px;">Reset</a>
                </div>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="table-container">
            <h2 style="margin-bottom: 20px; color: var(--dark);">
                <i class="fas fa-credit-card"></i> Payment Records
            </h2>
            
            <?php if (empty($bookings)): ?>
                <div class="no-data">
                    <i class="fas fa-receipt"></i>
                    <p>No payment records found</p>
                </div>
            <?php else: ?>
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>User</th>
                            <th>Vehicle</th>
                            <th>Parking Spot</th>
                            <th>Duration</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td>#<?php echo $booking['id']; ?></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($booking['username']); ?></div>
                                <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($booking['email']); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($booking['vehicle_plate']); ?></div>
                                <div style="font-size: 12px; color: #666; text-transform: capitalize;">
                                    <?php echo $booking['vehicle_type']; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600;">Spot <?php echo $booking['spot_number']; ?></div>
                                <div style="font-size: 12px; color: #666;">
                                    <?php echo $booking['zone_name'] . ' - ' . $booking['mall_name']; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600;">
                                    <?php echo date('M j, g:i A', strtotime($booking['start_time'])); ?>
                                </div>
                                <div style="font-size: 12px; color: #666;">
                                    to <?php echo date('M j, g:i A', strtotime($booking['end_time'])); ?>
                                </div>
                            </td>
                            <td class="amount">$<?php echo number_format($booking['amount'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $booking['status']; ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo date('M j, Y', strtotime($booking['created_at'])); ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-outline btn-sm" 
                                            onclick="viewBookingDetails(<?php echo $booking['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($booking['status'] == 'active'): ?>
                                    <button class="btn btn-primary btn-sm"
                                            onclick="completeBooking(<?php echo $booking['id']; ?>)">
                                        <i class="fas fa-check"></i>
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
    </div>

    <script>
        function viewBookingDetails(bookingId) {
            alert('View details for booking #' + bookingId);
            // يمكن استبدال هذا ب modal أو صفحة تفاصيل
        }

        function completeBooking(bookingId) {
            if (confirm('Are you sure you want to mark this booking as completed?')) {
                // إرسال طلب AJAX لتحديث حالة الحجز
                fetch('complete_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'booking_id=' + bookingId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Booking completed successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while completing the booking.');
                });
            }
        }

        // جعل الجدول أكثر تفاعلية
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.payments-table tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function(e) {
                    if (!e.target.closest('.action-buttons')) {
                        const bookingId = this.querySelector('td:first-child').textContent.replace('#', '');
                        viewBookingDetails(bookingId);
                    }
                });
            });
        });
    </script>
</body>
</html>