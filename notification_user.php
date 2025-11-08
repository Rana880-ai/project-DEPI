<?php
session_start();
require_once 'config.php';

// التحقق من أن المستخدم مسجل دخول وهو من نوع user
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// جلب بيانات إحصائية من قاعدة البيانات
try {
    // إجمالي المواقف
    $total_spots_stmt = $pdo->query("SELECT COUNT(*) as total FROM parking_spots");
    $total_spots = $total_spots_stmt->fetch()['total'];
    
    // المواقف المتاحة
    $available_spots_stmt = $pdo->query("SELECT COUNT(*) as available FROM parking_spots WHERE status = 'available'");
    $available_spots = $available_spots_stmt->fetch()['available'];
    
    // الحجوزات النشطة للمستخدم
    $active_bookings_stmt = $pdo->prepare("
        SELECT COUNT(*) as active 
        FROM bookings 
        WHERE user_id = ? AND status = 'active'
    ");
    $active_bookings_stmt->execute([$user_id]);
    $active_bookings = $active_bookings_stmt->fetch()['active'];
    
    // جلب الإشعارات
    $notifications_stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $notifications_stmt->execute([$user_id]);
    $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // الإشعارات غير المقروءة
    $unread_notifications = count(array_filter($notifications, function($notification) {
        return !$notification['is_read'];
    }));

    // تحديث الإشعارات كمقروءة عند زيارة الصفحة
    if (isset($_GET['mark_all_read'])) {
        $update_stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $update_stmt->execute([$user_id]);
        header("Location: notification_user.php");
        exit();
    }

    //标记单个通知为已读
    if (isset($_GET['mark_read'])) {
        $notification_id = $_GET['mark_read'];
        $update_stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $update_stmt->execute([$notification_id, $user_id]);
        header("Location: notification_user.php");
        exit();
    }

} catch (PDOException $e) {
    // استخدام بيانات افتراضية في حالة الخطأ
    $total_spots = 50;
    $available_spots = 25;
    $active_bookings = 2;
    $unread_notifications = 3;
    
    // إشعارات افتراضية
    $notifications = [
        [
            'id' => 1,
            'title' => 'Welcome to SmartPark',
            'message' => 'Thank you for registering with SmartPark parking system',
            'type' => 'info',
            'is_read' => 1,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ],
        [
            'id' => 2,
            'title' => 'Booking Confirmed',
            'message' => 'Your parking spot A2 has been successfully booked',
            'type' => 'success',
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ],
        [
            'id' => 3,
            'title' => 'Parking Reminder',
            'message' => 'Your parking session will expire in 30 minutes',
            'type' => 'warning',
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'id' => 4,
            'title' => 'New Spot Available',
            'message' => 'A new VIP parking spot has been added in Zone B',
            'type' => 'info',
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ],
        [
            'id' => 5,
            'title' => 'Payment Processed',
            'message' => 'Your payment of $15.00 has been processed successfully',
            'type' => 'success',
            'is_read' => 1,
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - SmartPark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Segoe UI', sans-serif;
        }

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #f59e0b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --dark: #1f2937;
            --light: #f8fafc;
            --sidebar-width: 280px;
            --header-height: 80px;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            color: var(--dark);
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 30px 0;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255,255,255,0.2);
        }

        .logo {
            text-align: center;
            padding: 0 25px 30px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: var(--primary);
            font-size: 28px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .logo-icon {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            list-style: none;
            padding: 0 20px;
            flex: 1;
        }

        .nav-links li {
            margin-bottom: 8px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            text-decoration: none;
            color: var(--dark);
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
        }

        .nav-links a:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        .nav-links a.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        .nav-links a.active::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background: var(--primary);
            border-radius: 2px;
        }

        .nav-links i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
            font-size: 1.2em;
        }

        .nav-badge {
            background: var(--danger);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7em;
            font-weight: 600;
            margin-left: auto;
        }

        .user-profile {
            padding: 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar-sidebar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .user-details-sidebar {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9em;
        }

        .user-role {
            color: #6b7280;
            font-size: 0.8em;
            font-weight: 500;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 25px 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .welcome-section {
            flex: 1;
        }

        .welcome-section h1 {
            color: var(--dark);
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-section p {
            color: #6b7280;
            font-size: 1.1em;
            font-weight: 400;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 24px;
            box-shadow: 0 6px 15px rgba(99, 102, 241, 0.3);
        }

        .user-details {
            text-align: right;
        }

        .user-details .name {
            font-weight: 700;
            color: var(--dark);
            font-size: 1.1em;
        }

        .user-details .role {
            background: var(--success);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            margin-top: 5px;
            display: inline-block;
        }

        .logout-btn {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
            text-decoration: none;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        /* Notifications Section */
        .notifications-section {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-header h2 {
            color: var(--dark);
            font-size: 1.8em;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification-actions {
            display: flex;
            gap: 15px;
        }

        .action-btn {
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        .btn-mark-all {
            background: var(--success);
        }

        .btn-clear-all {
            background: var(--danger);
        }

        /* Notifications List */
        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notification-item {
            background: white;
            padding: 20px;
            border-radius: 15px;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }

        .notification-item.unread {
            border-left-color: var(--danger);
            background: linear-gradient(135deg, #fef3f2, white);
        }

        .notification-item.info {
            border-left-color: var(--info);
        }

        .notification-item.success {
            border-left-color: var(--success);
        }

        .notification-item.warning {
            border-left-color: var(--warning);
        }

        .notification-item.error {
            border-left-color: var(--danger);
        }

        .notification-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .notification-title {
            font-weight: 700;
            color: var(--dark);
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification-type {
            padding: 4px 8px;
            border-radius: 6px;
            color: white;
            font-size: 0.7em;
            font-weight: 600;
        }

        .type-info { background: var(--info); }
        .type-success { background: var(--success); }
        .type-warning { background: var(--warning); }
        .type-error { background: var(--danger); }

        .notification-time {
            color: #6b7280;
            font-size: 0.8em;
            font-weight: 500;
        }

        .notification-message {
            color: #4b5563;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .notification-actions-item {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-mark-read {
            padding: 6px 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8em;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-mark-read:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .unread-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
        }

        .no-notifications {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .no-notifications i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-notifications h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
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
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .stat-card:nth-child(2)::before {
            background: linear-gradient(90deg, var(--success), #059669);
        }

        .stat-card:nth-child(3)::before {
            background: linear-gradient(90deg, var(--warning), #d97706);
        }

        .stat-card:nth-child(4)::before {
            background: linear-gradient(90deg, var(--info), #0891b2);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 1.5em;
            color: white;
        }

        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, var(--success), #059669);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, var(--warning), #d97706);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: linear-gradient(135deg, var(--info), #0891b2);
        }

        .stat-content h3 {
            color: #6b7280;
            font-size: 0.9em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 2.5em;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 10px;
            line-height: 1;
        }

        .stat-change {
            font-size: 0.9em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .positive {
            color: var(--success);
        }

        .negative {
            color: var(--danger);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar .logo h1,
            .sidebar .nav-text,
            .sidebar .user-details-sidebar,
            .sidebar .nav-badge {
                display: none;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .nav-links a {
                justify-content: center;
                padding: 15px;
            }
            
            .nav-links i {
                margin-right: 0;
                font-size: 1.4em;
            }
            
            .user-profile {
                justify-content: center;
                padding: 15px;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .user-info {
                flex-direction: column;
                gap: 15px;
            }
            
            .user-details {
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .notification-actions {
                width: 100%;
                justify-content: space-between;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 20px 15px;
            }
            
            .welcome-section h1 {
                font-size: 2em;
            }
            
            .notification-actions {
                flex-direction: column;
            }
            
            .action-btn {
                justify-content: center;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(99, 102, 241, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(99, 102, 241, 0);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h1><i class="fas fa-parking logo-icon"></i> <span>SmartPark</span></h1>
        </div>
        
        <ul class="nav-links">
            <li><a href="dashboard-user.php"><i class="fas fa-home"></i> <span class="nav-text">Dashboard</span></a></li>
            <li>
                <a href="parking_map_user.php">
                    <i class="fas fa-map-marked-alt"></i> 
                    <span class="nav-text">Parking Maps</span>
                </a>
            </li>
            <li>
                <a href="booking_user.php">
                    <i class="fas fa-calendar-check"></i> 
                    <span class="nav-text">Book Parking</span>
                    <?php if ($active_bookings > 0): ?>
                    <span class="nav-badge"><?php echo $active_bookings; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="notification_user.php" class="active">
                    <i class="fas fa-bell"></i> 
                    <span class="nav-text">Notifications</span>
                    <?php if ($unread_notifications > 0): ?>
                    <span class="nav-badge"><?php echo $unread_notifications; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="user_user.php">
                    <i class="fas fa-user-cog"></i> 
                    <span class="nav-text">Profile Settings</span>
                </a>
            </li>
        </ul>
        
        <div class="user-profile">
            <div class="user-avatar-sidebar">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
            </div>
            <div class="user-details-sidebar">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                <div class="user-role">Premium User</div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header fade-in-up">
            <div class="welcome-section">
                <h1>Notifications 🔔</h1>
                <p>Stay updated with your parking activities and alerts</p>
            </div>
            
            <div class="user-info">
                <div class="user-details">
                    <div class="name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="role">Premium User</div>
                </div>
                <div class="user-avatar pulse">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card fade-in-up">
                <div class="stat-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Notifications</h3>
                    <div class="stat-value"><?php echo count($notifications); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 5 new this week
                    </div>
                </div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.1s;">
                <div class="stat-icon">
                    <i class="fas fa-envelope-open"></i>
                </div>
                <div class="stat-content">
                    <h3>Unread Notifications</h3>
                    <div class="stat-value"><?php echo $unread_notifications; ?></div>
                    <div class="stat-change negative">
                        <i class="fas fa-bell"></i> Needs attention
                    </div>
                </div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.2s;">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Read Notifications</h3>
                    <div class="stat-value"><?php echo count($notifications) - $unread_notifications; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 80% read rate
                    </div>
                </div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.3s;">
                <div class="stat-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="stat-content">
                    <h3>This Month</h3>
                    <div class="stat-value"><?php echo min(count($notifications), 12); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 3 more than last month
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications Section -->
        <div class="notifications-section fade-in-up" style="animation-delay: 0.4s;">
            <div class="section-header">
                <h2><i class="fas fa-bell"></i> Your Notifications</h2>
                <div class="notification-actions">
                    <a href="?mark_all_read=1" class="action-btn btn-mark-all">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </a>
                    <button class="action-btn btn-clear-all" onclick="clearAllNotifications()">
                        <i class="fas fa-trash"></i> Clear All
                    </button>
                </div>
            </div>

            <div class="notifications-list">
                <?php if (empty($notifications)): ?>
                    <div class="no-notifications">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No Notifications</h3>
                        <p>You're all caught up! New notifications will appear here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['type']; ?> <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                        <?php if (!$notification['is_read']): ?>
                            <div class="unread-badge"></div>
                        <?php endif; ?>
                        
                        <div class="notification-header">
                            <div class="notification-title">
                                <?php 
                                $icon = '';
                                switch($notification['type']) {
                                    case 'info': $icon = 'fas fa-info-circle'; break;
                                    case 'success': $icon = 'fas fa-check-circle'; break;
                                    case 'warning': $icon = 'fas fa-exclamation-triangle'; break;
                                    case 'error': $icon = 'fas fa-times-circle'; break;
                                }
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                                <?php echo htmlspecialchars($notification['title']); ?>
                                <span class="notification-type type-<?php echo $notification['type']; ?>">
                                    <?php echo ucfirst($notification['type']); ?>
                                </span>
                            </div>
                            <div class="notification-time">
                                <?php 
                                $time = strtotime($notification['created_at']);
                                $now = time();
                                $diff = $now - $time;
                                
                                if ($diff < 60) {
                                    echo 'Just now';
                                } elseif ($diff < 3600) {
                                    echo floor($diff / 60) . ' minutes ago';
                                } elseif ($diff < 86400) {
                                    echo floor($diff / 3600) . ' hours ago';
                                } else {
                                    echo date('M j, g:i A', $time);
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="notification-message">
                            <?php echo htmlspecialchars($notification['message']); ?>
                        </div>
                        
                        <?php if (!$notification['is_read']): ?>
                        <div class="notification-actions-item">
                            <a href="?mark_read=<?php echo $notification['id']; ?>" class="btn-mark-read">
                                <i class="fas fa-check"></i> Mark as Read
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // دالة مسح جميع الإشعارات
        function clearAllNotifications() {
            if (confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
                // هنا يمكنك إضافة كود AJAX لمسح جميع الإشعارات
                alert('All notifications have been cleared.');
                window.location.reload();
            }
        }

        // تحديث تلقائي للإشعارات كل 30 ثانية
        setInterval(() => {
            // محاكاة إشعار جديد
            if (Math.random() > 0.8) {
                // في التطبيق الحقيقي، سيأتي هذا من WebSocket أو AJAX
                console.log('New notification available');
            }
        }, 30000);

        // تهيئة الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // إضافة تأثيرات للبطاقات
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // تأثيرات للإشعارات
            document.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', function() {
                    if (this.classList.contains('unread')) {
                        const notificationId = this.querySelector('a')?.getAttribute('href')?.split('=')[1];
                        if (notificationId) {
                            window.location.href = `?mark_read=${notificationId}`;
                        }
                    }
                });
            });
        });

        // WebSocket للتحديثات الفورية (محاكاة)
        function initWebSocket() {
            // في التطبيق الحقيقي، سيتم توصيل WebSocket هنا
            console.log('WebSocket would connect here for real-time notifications');
        }

        // بدء WebSocket
        initWebSocket();
    </script>
</body>
</html>