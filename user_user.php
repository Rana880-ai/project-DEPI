<?php
session_start();
require_once 'config.php';

// التحقق من أن المستخدم مسجل دخول وهو من نوع user
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// جلب بيانات المستخدم
try {
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: logout.php");
        exit();
    }

    // جلب بيانات إحصائية
    $total_spots_stmt = $pdo->query("SELECT COUNT(*) as total FROM parking_spots");
    $total_spots = $total_spots_stmt->fetch()['total'];
    
    $available_spots_stmt = $pdo->query("SELECT COUNT(*) as available FROM parking_spots WHERE status = 'available'");
    $available_spots = $available_spots_stmt->fetch()['available'];
    
    $active_bookings_stmt = $pdo->prepare("SELECT COUNT(*) as active FROM bookings WHERE user_id = ? AND status = 'active'");
    $active_bookings_stmt->execute([$user_id]);
    $active_bookings = $active_bookings_stmt->fetch()['active'];
    
    $notifications_stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
    $notifications_stmt->execute([$user_id]);
    $unread_notifications = $notifications_stmt->fetch()['unread'];

    // جلب إحصائيات الحجوزات
    $total_bookings_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ?");
    $total_bookings_stmt->execute([$user_id]);
    $total_bookings = $total_bookings_stmt->fetch()['total'];

    $completed_bookings_stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM bookings WHERE user_id = ? AND status = 'completed'");
    $completed_bookings_stmt->execute([$user_id]);
    $completed_bookings = $completed_bookings_stmt->fetch()['completed'];

    $total_spent_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_spent FROM bookings WHERE user_id = ? AND status IN ('completed', 'active')");
    $total_spent_stmt->execute([$user_id]);
    $total_spent = $total_spent_stmt->fetch()['total_spent'];

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// تحديث بيانات الملف الشخصي
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $vehicle_type = trim($_POST['vehicle_type'] ?? '');
        $license_plate = trim($_POST['license_plate'] ?? '');

        try {
            // التحقق من أن اسم المستخدم والبريد الإلكتروني غير مستخدمين من قبل مستخدمين آخرين
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $check_stmt->execute([$username, $email, $user_id]);
            $existing_user = $check_stmt->fetch();

            if ($existing_user) {
                $error_message = "Username or email already exists!";
            } else {
                $update_stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ?, vehicle_type = ?, license_plate = ? WHERE id = ?");
                $update_stmt->execute([$username, $email, $phone, $vehicle_type, $license_plate, $user_id]);
                
                $_SESSION['username'] = $username;
                $success_message = "Profile updated successfully!";
                
                // إعادة جلب بيانات المستخدم
                $user_stmt->execute([$user_id]);
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    }

    // تغيير كلمة المرور
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (!password_verify($current_password, $user['password'])) {
            $error_message = "Current password is incorrect!";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long!";
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->execute([$hashed_password, $user_id]);
                $success_message = "Password changed successfully!";
            } catch (PDOException $e) {
                $error_message = "Error changing password: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - SmartPark</title>
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
            text-decoration: none;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        /* Profile Content */
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        @media (max-width: 1024px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
        }

        /* Profile Section */
        .profile-section {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
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

        /* Profile Info */
        .profile-info {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 2px solid #e5e7eb;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 36px;
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
            position: relative;
        }

        .profile-avatar-edit {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8em;
        }

        .profile-details h3 {
            font-size: 1.5em;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .profile-details p {
            color: #6b7280;
            margin-bottom: 8px;
        }

        .member-since {
            background: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            display: inline-block;
        }

        /* Forms */
        .profile-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9em;
        }

        .form-group input, .form-group select {
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.3em;
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .stat-value {
            font-size: 2em;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6b7280;
            font-weight: 600;
            font-size: 0.9em;
        }

        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: linear-gradient(135deg, #d1fae5, #ecfdf5);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .message.error {
            background: linear-gradient(135deg, #fee2e2, #fef2f2);
            color: var(--danger);
            border-left: 4px solid var(--danger);
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
            
            .profile-info {
                flex-direction: column;
                text-align: center;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 20px 15px;
            }
            
            .welcome-section h1 {
                font-size: 2em;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
                <a href="notification_user.php">
                    <i class="fas fa-bell"></i> 
                    <span class="nav-text">Notifications</span>
                    <?php if ($unread_notifications > 0): ?>
                    <span class="nav-badge"><?php echo $unread_notifications; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="user_user.php" class="active">
                    <i class="fas fa-user-cog"></i> 
                    <span class="nav-text">Profile Settings</span>
                </a>
            </li>
        </ul>
        
        <div class="user-profile">
            <div class="user-avatar-sidebar">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <div class="user-details-sidebar">
                <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="user-role"><?php echo ucfirst($user['user_type']); ?></div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header fade-in-up">
            <div class="welcome-section">
                <h1>Profile Settings 👤</h1>
                <p>Manage your account information and preferences</p>
            </div>
            
            <div class="user-info">
                <div class="user-details">
                    <div class="name"><?php echo htmlspecialchars($user['username']); ?></div>
                    <div class="role"><?php echo ucfirst($user['user_type']); ?> Account</div>
                </div>
                <div class="user-avatar pulse">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
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
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-value"><?php echo $total_bookings; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.1s;">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $completed_bookings; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.2s;">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-value">$<?php echo number_format($total_spent, 2); ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.3s;">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-value"><?php echo $total_bookings > 0 ? round(($completed_bookings / $total_bookings) * 100) : 0; ?>%</div>
                <div class="stat-label">Success Rate</div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="message success fade-in-up">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error fade-in-up">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Profile Information -->
            <div class="profile-section fade-in-up" style="animation-delay: 0.4s;">
                <div class="section-header">
                    <h2><i class="fas fa-user"></i> Profile Information</h2>
                </div>

                <div class="profile-info">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        <button class="profile-avatar-edit" title="Change Avatar">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                    <div class="profile-details">
                        <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                        <p><?php echo ucfirst($user['user_type']); ?> Account</p>
                        <span class="member-since">
                            Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                        </span>
                    </div>
                </div>

                <form method="POST" class="profile-form">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Enter your phone number">
                    </div>

                    <div class="form-group">
                        <label for="vehicle_type">Preferred Vehicle Type</label>
                        <select id="vehicle_type" name="vehicle_type">
                            <option value="">Select Vehicle Type</option>
                            <option value="car" <?php echo ($user['vehicle_type'] ?? '') === 'car' ? 'selected' : ''; ?>>Car</option>
                            <option value="suv" <?php echo ($user['vehicle_type'] ?? '') === 'suv' ? 'selected' : ''; ?>>SUV</option>
                            <option value="motorcycle" <?php echo ($user['vehicle_type'] ?? '') === 'motorcycle' ? 'selected' : ''; ?>>Motorcycle</option>
                            <option value="truck" <?php echo ($user['vehicle_type'] ?? '') === 'truck' ? 'selected' : ''; ?>>Truck</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="license_plate">License Plate</label>
                        <input type="text" id="license_plate" name="license_plate" value="<?php echo htmlspecialchars($user['license_plate'] ?? ''); ?>" placeholder="Enter your license plate">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Security Settings -->
            <div class="profile-section fade-in-up" style="animation-delay: 0.5s;">
                <div class="section-header">
                    <h2><i class="fas fa-shield-alt"></i> Security Settings</h2>
                </div>

                <form method="POST" class="profile-form">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required placeholder="Enter your current password">
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required placeholder="Enter new password (min. 6 characters)">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your new password">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>

                <!-- Account Preferences -->
                <div style="margin-top: 30px; padding-top: 25px; border-top: 2px solid #e5e7eb;">
                    <div class="section-header">
                        <h3><i class="fas fa-cog"></i> Preferences</h3>
                    </div>

                    <div class="form-group">
                        <label>Email Notifications</label>
                        <div style="display: flex; gap: 20px; margin-top: 10px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" checked> Booking Confirmations
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" checked> Parking Reminders
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox"> Promotional Offers
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div style="margin-top: 30px; padding-top: 25px; border-top: 2px solid #fee2e2;">
                    <div class="section-header">
                        <h3 style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                    </div>

                    <p style="color: #6b7280; margin-bottom: 15px; font-size: 0.9em;">
                        Once you delete your account, there is no going back. Please be certain.
                    </p>

                    <button class="btn" style="background: var(--danger); color: white;" onclick="confirmDelete()">
                        <i class="fas fa-trash"></i> Delete Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // تأكيد حذف الحساب
        function confirmDelete() {
            if (confirm('Are you sure you want to delete your account? This action cannot be undone and all your data will be permanently lost.')) {
                alert('Account deletion feature would be implemented here.');
                // في التطبيق الحقيقي، سيتم إرسال طلب AJAX لحذف الحساب
            }
        }

        // التحقق من تطابق كلمات المرور
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePasswords() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.style.borderColor = 'var(--danger)';
                } else {
                    confirmPassword.style.borderColor = 'var(--success)';
                }
            }
            
            newPassword.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);

            // إضافة تأثيرات للبطاقات
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });

        // محاكاة تغيير الصورة الشخصية
        document.querySelector('.profile-avatar-edit').addEventListener('click', function(e) {
            e.preventDefault();
            alert('Avatar change feature would be implemented here. You can upload a new profile picture.');
        });
    </script>
</body>
</html>