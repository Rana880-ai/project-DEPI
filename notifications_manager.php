<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'manager') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = $error_message = '';

// معالجة تحديث حالة الحجز
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking'])) {
    $booking_id = $_POST['booking_id'];
    $new_status = $_POST['status'];
    
    try {
        $pdo->beginTransaction();
        
        // تحديث حالة الحجز
        $update_stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $update_stmt->execute([$new_status, $booking_id]);
        
        // إنشاء إشعار للمستخدم
        $booking_stmt = $pdo->prepare("
            SELECT b.*, u.username, ps.spot_number 
            FROM bookings b 
            JOIN users u ON b.user_id = u.id 
            JOIN parking_spots ps ON b.spot_id = ps.id 
            WHERE b.id = ?
        ");
        $booking_stmt->execute([$booking_id]);
        $booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            $notification_title = "Booking " . ucfirst($new_status);
            $notification_message = "Your booking for spot " . $booking['spot_number'] . " has been " . $new_status;
            
            $notification_stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at) 
                VALUES (?, ?, ?, 'info', NOW())
            ");
            $notification_stmt->execute([$booking['user_id'], $notification_title, $notification_message]);
        }
        
        $pdo->commit();
        $success_message = "Booking status updated successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error updating booking: " . $e->getMessage();
    }
}

// جلب البيانات
try {
    // الحجوزات الجديدة (آخر 24 ساعة)
    $new_bookings_stmt = $pdo->query("
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
        WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY b.created_at DESC
    ");
    $new_bookings = $new_bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

    // جميع الإشعارات للمستخدمين
    $all_notifications_stmt = $pdo->query("
        SELECT 
            n.*,
            u.username,
            u.user_type
        FROM notifications n
        JOIN users u ON n.user_id = u.id
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    $all_notifications = $all_notifications_stmt->fetchAll(PDO::FETCH_ASSOC);

    // إحصائيات
    $total_notifications_stmt = $pdo->query("SELECT COUNT(*) as total FROM notifications");
    $total_notifications = $total_notifications_stmt->fetch()['total'];

    $unread_notifications_stmt = $pdo->query("SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0");
    $unread_notifications = $unread_notifications_stmt->fetch()['unread'];

    $today_notifications_stmt = $pdo->query("SELECT COUNT(*) as today FROM notifications WHERE DATE(created_at) = CURDATE()");
    $today_notifications = $today_notifications_stmt->fetch()['today'];

    $new_users_today_stmt = $pdo->query("SELECT COUNT(*) as new_users FROM users WHERE DATE(created_at) = CURDATE() AND user_type = 'user'");
    $new_users_today = $new_users_today_stmt->fetch()['new_users'];

    // المستخدمين الجدد
    $new_users_stmt = $pdo->query("
        SELECT * FROM users 
        WHERE user_type = 'user' 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $new_users = $new_users_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications Management - SmartPark</title>
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

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .card h2 {
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .data-table th {
            background: var(--light);
            color: var(--dark);
            font-weight: 600;
        }

        .data-table tr:hover {
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

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 12px;
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .notification-title {
            font-weight: 600;
            color: var(--dark);
        }

        .notification-user {
            color: #666;
            font-size: 14px;
        }

        .notification-time {
            color: #999;
            font-size: 12px;
        }

        .notification-message {
            color: #555;
            line-height: 1.4;
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

        .alert {
            padding: 15px;
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

        .form-group {
            margin-bottom: 15px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
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
            <li><a href="dashboard-manager.php"><i class="fas fa-home"></i> <span class="nav-text">Dashboard</span></a></li>
            <li><a href="parking_maps_manager.php"><i class="fas fa-map-marked-alt"></i> <span class="nav-text">Parking Maps</span></a></li>
            <li><a href="booking_manager.php"><i class="fas fa-calendar-check"></i> <span class="nav-text">Parking Management</span></a></li>
            <li><a href="users_manager.php"><i class="fas fa-users"></i> <span class="nav-text">Users</span></a></li>
            <li><a href="payments_manager.php"><i class="fas fa-credit-card"></i> <span class="nav-text">Payments</span></a></li>
            <li><a href="reports_manager.php"><i class="fas fa-chart-bar"></i> <span class="nav-text">Reports</span></a></li>
            <li><a href="notifications_manager.php" class="active"><i class="fas fa-bell"></i> <span class="nav-text">Notifications</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Notifications Management</h1>
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

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Notifications</h3>
                <div class="stat-value"><?php echo $total_notifications; ?></div>
                <div style="font-size: 14px; color: #666;">All system notifications</div>
            </div>
            <div class="stat-card">
                <h3>Unread Notifications</h3>
                <div class="stat-value"><?php echo $unread_notifications; ?></div>
                <div style="font-size: 14px; color: #666;">Require attention</div>
            </div>
            <div class="stat-card">
                <h3>Today's Notifications</h3>
                <div class="stat-value"><?php echo $today_notifications; ?></div>
                <div style="font-size: 14px; color: #666;">New today</div>
            </div>
            <div class="stat-card">
                <h3>New Users Today</h3>
                <div class="stat-value"><?php echo $new_users_today; ?></div>
                <div style="font-size: 14px; color: #666;">Registered today</div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- New Bookings Section -->
            <div class="card">
                <h2><i class="fas fa-calendar-plus"></i> New Bookings (Last 24h)</h2>
                <div class="table-container">
                    <?php if (empty($new_bookings)): ?>
                        <div class="no-data">
                            <i class="fas fa-calendar-times"></i>
                            <p>No new bookings in the last 24 hours</p>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Spot</th>
                                    <th>Vehicle</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($new_bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($booking['username']); ?></div>
                                        <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($booking['email']); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo $booking['spot_number']; ?></div>
                                        <div style="font-size: 12px; color: #666;"><?php echo $booking['zone_name']; ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo $booking['vehicle_plate']; ?></div>
                                        <div style="font-size: 12px; color: #666; text-transform: capitalize;"><?php echo $booking['vehicle_type']; ?></div>
                                    </td>
                                    <td style="font-weight: 700; color: var(--success);">
                                        $<?php echo number_format($booking['amount'], 2); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: flex; gap: 5px;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <select name="status" class="form-control" style="width: 100px;">
                                                <option value="active" <?php echo $booking['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="completed" <?php echo $booking['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $booking['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" name="update_booking" class="btn btn-primary">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- All Notifications Section -->
            <div class="card">
                <h2><i class="fas fa-bell"></i> All User Notifications</h2>
                <div style="max-height: 500px; overflow-y: auto;">
                    <?php if (empty($all_notifications)): ?>
                        <div class="no-data">
                            <i class="fas fa-bell-slash"></i>
                            <p>No notifications found</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($all_notifications as $notification): ?>
                        <div class="notification-item">
                            <div class="notification-header">
                                <div class="notification-title">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </div>
                                <div class="notification-time">
                                    <?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?>
                                </div>
                            </div>
                            <div class="notification-user">
                                Sent to: <?php echo htmlspecialchars($notification['username']); ?> 
                                (<?php echo ucfirst($notification['user_type']); ?>)
                            </div>
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <div style="margin-top: 8px;">
                                <span class="status-badge <?php echo $notification['is_read'] ? 'status-completed' : 'status-active'; ?>">
                                    <?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- New Users Section -->
        <div class="card">
            <h2><i class="fas fa-user-plus"></i> Recently Registered Users</h2>
            <div class="table-container">
                <?php if (empty($new_users)): ?>
                    <div class="no-data">
                        <i class="fas fa-users-slash"></i>
                        <p>No new users registered</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Registered</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($new_users as $user): ?>
                            <tr>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge status-active">
                                        Active
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // تحديث تلقائي للصفحة كل دقيقة
        setInterval(() => {
            // محاكاة تحديث البيانات
            console.log('Auto-refreshing notifications...');
        }, 60000);

        // تأثيرات تفاعلية
        document.addEventListener('DOMContentLoaded', function() {
            // إضافة تأثير hover للصفوف
            const tableRows = document.querySelectorAll('.data-table tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });

            // إشعار عند تحديث حالة الحجز
            const updateForms = document.querySelectorAll('form[method="POST"]');
            updateForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const select = this.querySelector('select');
                    const newStatus = select.value;
                    console.log('Updating booking status to:', newStatus);
                });
            });

            // عدّاد الإشعارات غير المقروءة
            function updateUnreadCount() {
                const unreadElements = document.querySelectorAll('.status-badge.status-active');
                console.log('Unread notifications:', unreadElements.length);
            }

            updateUnreadCount();
        });

        // WebSocket للتحديثات الفورية (محاكاة)
        function initRealTimeUpdates() {
            // في التطبيق الحقيقي، سيتم توصيل WebSocket هنا
            console.log('Initializing real-time updates for notifications...');
            
            setInterval(() => {
                // محاكاة إشعار جديد
                if (Math.random() > 0.7) {
                    console.log('New notification received!');
                    // يمكن إضافة كود لعرض إشعار جديد
                }
            }, 10000);
        }

        initRealTimeUpdates();
    </script>
</body>
</html>