<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'user') {
    header("Location: index.php");
    exit();
}

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
    $active_bookings_stmt->execute([$_SESSION['user_id']]);
    $active_bookings = $active_bookings_stmt->fetch()['active'];
    
    // الإشعارات غير المقروءة
    $notifications_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $notifications_stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $notifications_stmt->fetch()['unread'];
    
} catch (PDOException $e) {
    // استخدام قيم افتراضية في حالة الخطأ
    $total_spots = 300;
    $available_spots = 142;
    $active_bookings = 3;
    $unread_notifications = 2;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - SmartPark</title>
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
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
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

        /* Quick Actions */
        .quick-actions {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }

        .section-header {
            display: flex;
            justify-content: between;
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

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .action-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            cursor: pointer;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }

        .action-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.8em;
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .action-card:nth-child(2) .action-icon {
            background: linear-gradient(135deg, var(--success), #059669);
        }

        .action-card:nth-child(3) .action-icon {
            background: linear-gradient(135deg, var(--warning), #d97706);
        }

        .action-card:nth-child(4) .action-icon {
            background: linear-gradient(135deg, var(--info), #0891b2);
        }

        .action-card h3 {
            color: var(--dark);
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .action-card p {
            color: #6b7280;
            font-size: 0.9em;
            line-height: 1.4;
        }

        /* Recent Activity */
        .recent-activity {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 12px;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .activity-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            background: var(--primary);
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .activity-time {
            color: #6b7280;
            font-size: 0.8em;
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
            
            .actions-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 20px 15px;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-section h1 {
                font-size: 2em;
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
            <li><a href="user.php" class="active"><i class="fas fa-home"></i> <span class="nav-text">Dashboard</span></a></li>
            <li>
                <a href="parking_map_user.php">
                    <i class="fas fa-map-marked-alt"></i> 
                    <span class="nav-text">Parking Maps</span>
                </a>
            </li>
            <li>
                <a href="booking_user.php">
                    <i class="fas fa-calendar-check"></i> 
                    <span class="nav-text">My Bookings</span>
                    <span class="nav-badge"><?php echo $active_bookings; ?></span>
                </a>
            </li>
            <li>
                <a href="notification_user.php">
                    <i class="fas fa-bell"></i> 
                    <span class="nav-text">Notifications</span>
                    <span class="nav-badge"><?php echo $unread_notifications; ?></span>
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
                <h1>Welcome Back, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋</h1>
                <p>Here's what's happening with your parking today</p>
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
                    <i class="fas fa-parking"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Parking Spots</h3>
                    <div class="stat-value"><?php echo $total_spots; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 15 new spots added
                    </div>
                </div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.1s;">
                <div class="stat-icon">
                    <i class="fas fa-car"></i>
                </div>
                <div class="stat-content">
                    <h3>Available Now</h3>
                    <div class="stat-value"><?php echo $available_spots; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 12% recently freed
                    </div>
                </div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.2s;">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3>Active Bookings</h3>
                    <div class="stat-value"><?php echo $active_bookings; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 5% from yesterday
                    </div>
                </div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.3s;">
                <div class="stat-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-content">
                    <h3>Notifications</h3>
                    <div class="stat-value"><?php echo $unread_notifications; ?></div>
                    <div class="stat-change negative">
                        <i class="fas fa-arrow-down"></i> 2 unread
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions fade-in-up" style="animation-delay: 0.4s;">
            <div class="section-header">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            </div>
            <div class="actions-grid">
                <div class="action-card" onclick="window.location.href='parking_map_user.php'">
                    <div class="action-icon">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <h3>Find Parking</h3>
                    <p>Explore available parking spots in real-time</p>
                </div>
                
                <div class="action-card" onclick="window.location.href='booking_user.php'">
                    <div class="action-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <h3>New Booking</h3>
                    <p>Reserve your parking spot instantly</p>
                </div>
                
                <div class="action-card" onclick="window.location.href='notification_user.php'">
                    <div class="action-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3>Notifications</h3>
                    <p>Check your alerts and updates</p>
                </div>
                
                <div class="action-card" onclick="window.location.href='user_user.php'">
                    <div class="action-icon">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <h3>Profile</h3>
                    <p>Manage your account settings</p>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity fade-in-up" style="animation-delay: 0.5s;">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Recent Activity</h2>
            </div>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Parking Spot Booked</div>
                        <div class="activity-time">Spot A15 - 2 hours ago</div>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Navigation Used</div>
                        <div class="activity-time">To Spot B22 - 5 hours ago</div>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Payment Processed</div>
                        <div class="activity-time">$15.00 - Yesterday</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // جعل السايدبار تفاعلي
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-links a');
            const currentPage = window.location.pathname.split('/').pop();
            
            // تحديد الصفحة النشطة بناءً على الرابط الحالي
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href === currentPage) {
                    link.classList.add('active');
                }
                
                link.addEventListener('click', function(e) {
                    // إذا كان الرابط يشير إلى صفحة حقيقية، اسمح بالتنقل الطبيعي
                    if (href && href !== '#') {
                        return;
                    }
                    
                    e.preventDefault();
                    
                    // إزالة النشاط من جميع الروابط
                    navLinks.forEach(l => l.classList.remove('active'));
                    
                    // إضافة النشاط للرابط المختار
                    this.classList.add('active');
                });
            });

            // إضافة تأثيرات hover للبطاقات
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });

            // محاكاة تحديث البيانات الحية
            setInterval(() => {
                const availableSpots = Math.floor(Math.random() * 50) + 120;
                const activeBookings = Math.floor(Math.random() * 5) + 1;
                
                // تحديث الإحصائيات
                document.querySelector('.stat-card:nth-child(2) .stat-value').textContent = availableSpots;
                document.querySelector('.stat-card:nth-child(3) .stat-value').textContent = activeBookings;
                
                // تحديث badges في السايدبار
                document.querySelector('.nav-links li:nth-child(3) .nav-badge').textContent = activeBookings;
                
            }, 10000); // تحديث كل 10 ثواني

            // إضافة تأثيرات النقر على بطاقات الإجراءات السريعة
            const actionCards = document.querySelectorAll('.action-card');
            actionCards.forEach(card => {
                card.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        });

        // تأثيرات scroll
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const parallax = document.querySelector('.header');
            if (parallax) {
                parallax.style.transform = `translateY(${scrolled * 0.1}px)`;
            }
        });
    </script>
</body>
</html>