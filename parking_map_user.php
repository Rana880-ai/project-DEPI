<?php
session_start();
require_once 'config.php';

// التحقق من أن المستخدم مسجل دخول وهو من نوع user
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// جلب بيانات المواقف من قاعدة البيانات مع معلومات المولات والمناطق
try {
    $spots_stmt = $pdo->prepare("
        SELECT 
            ps.id,
            ps.spot_number,
            ps.spot_type,
            ps.status,
            ps.hourly_rate,
            pz.zone_name,
            pz.floor,
            m.mall_name,
            m.lat,
            m.lng,
            m.location
        FROM parking_spots ps
        JOIN parking_zones pz ON ps.zone_id = pz.id
        JOIN malls m ON pz.mall_id = m.id
        WHERE ps.is_active = 1
        ORDER BY m.mall_name, pz.zone_name, ps.spot_number
    ");
    $spots_stmt->execute();
    $parking_spots = $spots_stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب المولات المتاحة
    $malls_stmt = $pdo->query("
        SELECT * FROM malls 
        ORDER BY mall_name
    ");
    $malls = $malls_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// إحصائيات المواقف
$total_spots = count($parking_spots);
$available_spots = count(array_filter($parking_spots, function($spot) {
    return $spot['status'] === 'available';
}));
$occupied_spots = $total_spots - $available_spots;

// حساب الإيرادات من المواقف المتاحة
$total_revenue = array_sum(array_map(function($spot) {
    return $spot['status'] === 'available' ? $spot['hourly_rate'] : 0;
}, $parking_spots));

// جلب عدد الإشعارات غير المقروءة
try {
    $notifications_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $notifications_stmt->execute([$user_id]);
    $unread_notifications = $notifications_stmt->fetch()['unread'];
} catch (PDOException $e) {
    $unread_notifications = 0;
}

// جلب عدد الحجوزات النشطة
try {
    $active_bookings_stmt = $pdo->prepare("
        SELECT COUNT(*) as active 
        FROM bookings 
        WHERE user_id = ? AND status = 'active'
    ");
    $active_bookings_stmt->execute([$user_id]);
    $active_bookings = $active_bookings_stmt->fetch()['active'];
} catch (PDOException $e) {
    $active_bookings = 0;
}

// معالجة البحث والتصفية
$selected_mall = isset($_GET['mall']) ? $_GET['mall'] : '';
$selected_zone = isset($_GET['zone']) ? $_GET['zone'] : '';
$spot_type = isset($_GET['spot_type']) ? $_GET['spot_type'] : '';
$max_price = isset($_GET['max_price']) ? $_GET['max_price'] : '';

// تصفية المواقف بناءً على البحث
$filtered_spots = $parking_spots;

if ($selected_mall) {
    $filtered_spots = array_filter($filtered_spots, function($spot) use ($selected_mall) {
        return $spot['mall_name'] == $selected_mall;
    });
}

if ($selected_zone) {
    $filtered_spots = array_filter($filtered_spots, function($spot) use ($selected_zone) {
        return $spot['zone_name'] == $selected_zone;
    });
}

if ($spot_type && $spot_type != 'all') {
    $filtered_spots = array_filter($filtered_spots, function($spot) use ($spot_type) {
        return $spot['spot_type'] == $spot_type;
    });
}

if ($max_price) {
    $filtered_spots = array_filter($filtered_spots, function($spot) use ($max_price) {
        return $spot['hourly_rate'] <= floatval($max_price);
    });
}

// جلب المناطق بناءً على المول المختار
$zones = [];
if ($selected_mall) {
    try {
        $zones_stmt = $pdo->prepare("
            SELECT DISTINCT zone_name 
            FROM parking_zones 
            JOIN malls ON parking_zones.mall_id = malls.id 
            WHERE malls.mall_name = ?
        ");
        $zones_stmt->execute([$selected_mall]);
        $zones = $zones_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $zones = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive Parking Map - SmartPark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
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

        .nav-links a:hover, .nav-links a.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
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

        /* Filters Section */
        .filters-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
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
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-control {
            padding: 12px 15px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        /* Map Container */
        .map-container {
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
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h2 {
            color: var(--dark);
            font-size: 1.8em;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        #leafletMap {
            height: 500px;
            border-radius: 15px;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        /* Legend */
        .legend {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 0.9em;
            background: white;
            padding: 8px 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }

        .legend-color {
            width: 18px;
            height: 18px;
            border-radius: 4px;
        }

        .legend-available { background: var(--success); }
        .legend-occupied { background: var(--danger); }
        .legend-reserved { background: var(--warning); }
        .legend-vip { background: var(--primary); }
        .legend-electric { background: var(--info); }
        .legend-disabled { background: #8b5cf6; }
        .legend-family { background: #f97316; }

        /* Spot Info */
        .spot-info-window {
            padding: 15px;
            min-width: 220px;
        }

        .spot-info-header {
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--dark);
            font-size: 1.1em;
        }

        .spot-status {
            padding: 6px 12px;
            border-radius: 6px;
            color: white;
            font-size: 0.8em;
            font-weight: 600;
            margin-bottom: 10px;
            display: inline-block;
        }

        .status-available { background: var(--success); }
        .status-occupied { background: var(--danger); }
        .status-reserved { background: var(--warning); }

        .spot-type {
            padding: 4px 8px;
            border-radius: 4px;
            background: #f3f4f6;
            color: #6b7280;
            font-size: 0.7em;
            font-weight: 600;
            margin-left: 5px;
        }

        .book-btn {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .book-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
        }

        .book-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Results Summary */
        .results-summary {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .results-summary h3 {
            color: var(--dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-form {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            #leafletMap {
                height: 400px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 20px 15px;
            }
            
            #leafletMap {
                height: 300px;
            }
            
            .welcome-section h1 {
                font-size: 2em;
            }
            
            .legend {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h1><i class="fas fa-parking"></i> <span>SmartPark</span></h1>
        </div>
        
        <ul class="nav-links">
            <li><a href="dashboard-user.php"><i class="fas fa-home"></i> <span class="nav-text">Dashboard</span></a></li>
            <li>
                <a href="parking_map_user.php" class="active">
                    <i class="fas fa-map-marked-alt"></i> 
                    <span class="nav-text">Parking Maps</span>
                </a>
            </li>
            <li>
                <a href="booking_user.php">
                    <i class="fas fa-calendar-check"></i> 
                    <span class="nav-text">My Bookings</span>
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
                <h1>Interactive Parking Maps 🗺️</h1>
                <p>Find and book available parking spots in real-time</p>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
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
                    <div style="font-size: 14px; color: #666;">Across all malls</div>
                </div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.1s;">
                <div class="stat-icon">
                    <i class="fas fa-car"></i>
                </div>
                <div class="stat-content">
                    <h3>Available Now</h3>
                    <div class="stat-value"><?php echo $available_spots; ?></div>
                    <div style="font-size: 14px; color: #666;">Ready for booking</div>
                </div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.2s;">
                <div class="stat-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="stat-content">
                    <h3>Occupied Spots</h3>
                    <div class="stat-value"><?php echo $occupied_spots; ?></div>
                    <div style="font-size: 14px; color: #666;">Currently in use</div>
                </div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.3s;">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <h3>Availability Rate</h3>
                    <div class="stat-value"><?php echo $total_spots > 0 ? round(($available_spots / $total_spots) * 100) : 0; ?>%</div>
                    <div style="font-size: 14px; color: #666;">Current capacity</div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-card fade-in-up" style="animation-delay: 0.4s;">
            <form method="GET" class="filters-form">
                <div class="form-group">
                    <label for="mall"><i class="fas fa-building"></i> Select Mall</label>
                    <select id="mall" name="mall" class="form-control" onchange="this.form.submit()">
                        <option value="">All Malls</option>
                        <?php foreach ($malls as $mall): ?>
                        <option value="<?php echo htmlspecialchars($mall['mall_name']); ?>" 
                            <?php echo $selected_mall == $mall['mall_name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mall['mall_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="zone"><i class="fas fa-layer-group"></i> Zone</label>
                    <select id="zone" name="zone" class="form-control" onchange="this.form.submit()">
                        <option value="">All Zones</option>
                        <?php foreach ($zones as $zone): ?>
                        <option value="<?php echo htmlspecialchars($zone); ?>" 
                            <?php echo $selected_zone == $zone ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($zone); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="spot_type"><i class="fas fa-tag"></i> Spot Type</label>
                    <select id="spot_type" name="spot_type" class="form-control" onchange="this.form.submit()">
                        <option value="all">All Types</option>
                        <option value="regular" <?php echo $spot_type == 'regular' ? 'selected' : ''; ?>>Regular</option>
                        <option value="vip" <?php echo $spot_type == 'vip' ? 'selected' : ''; ?>>VIP</option>
                        <option value="electric" <?php echo $spot_type == 'electric' ? 'selected' : ''; ?>>Electric</option>
                        <option value="disabled" <?php echo $spot_type == 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                        <option value="family" <?php echo $spot_type == 'family' ? 'selected' : ''; ?>>Family</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="max_price"><i class="fas fa-dollar-sign"></i> Max Price/Hour</label>
                    <input type="number" id="max_price" name="max_price" class="form-control" 
                           placeholder="Enter max price" value="<?php echo htmlspecialchars($max_price); ?>"
                           min="0" step="0.5">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="parking_map_user.php" class="btn btn-outline" style="margin-top: 10px;">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Results Summary -->
        <?php if ($selected_mall || $selected_zone || $spot_type || $max_price): ?>
        <div class="results-summary fade-in-up">
            <h3><i class="fas fa-filter"></i> Filter Results</h3>
            <p>
                Showing <?php echo count($filtered_spots); ?> spots
                <?php if ($selected_mall): ?> in <strong><?php echo htmlspecialchars($selected_mall); ?></strong><?php endif; ?>
                <?php if ($selected_zone): ?> - Zone <strong><?php echo htmlspecialchars($selected_zone); ?></strong><?php endif; ?>
                <?php if ($spot_type && $spot_type != 'all'): ?> - Type: <strong><?php echo ucfirst($spot_type); ?></strong><?php endif; ?>
                <?php if ($max_price): ?> - Max price: <strong>$<?php echo htmlspecialchars($max_price); ?>/hour</strong><?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Map Container -->
        <div class="map-container fade-in-up" style="animation-delay: 0.5s;">
            <div class="section-header">
                <h2><i class="fas fa-map-marked-alt"></i> Parking Map</h2>
                <div style="color: #6b7280; font-size: 0.9em;">
                    <?php echo count($filtered_spots); ?> spots found
                </div>
            </div>

            <div id="leafletMap">
                <!-- سيتم تحميل الخريطة هنا -->
            </div>

            <!-- Legend -->
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color legend-available"></div>
                    <span>Available</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-occupied"></div>
                    <span>Occupied</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-vip"></div>
                    <span>VIP Spot</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-electric"></div>
                    <span>Electric</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-disabled"></div>
                    <span>Disabled</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-family"></div>
                    <span>Family</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        // بيانات المواقف من PHP
        const parkingSpots = <?php echo json_encode($filtered_spots); ?>;
        let leafletMap;

        // دالة الحصول على لون الموقف حسب النوع والحالة
        function getSpotColor(spot) {
            if (spot.status !== 'available') {
                return '#ef4444'; // أحمر للمواقف المشغولة
            }
            
            switch(spot.spot_type) {
                case 'vip': return '#6366f1'; // أزرق لـ VIP
                case 'electric': return '#06b6d4'; // أزرق فاتح للكهربائية
                case 'disabled': return '#8b5cf6'; // بنفسجي لذوي الاحتياجات
                case 'family': return '#f97316'; // برتقالي للعائلات
                default: return '#10b981'; // أخضر للمواقف العادية
            }
        }

        // دالة الحصول على أيقونة الموقف
        function getSpotIcon(spot) {
            const color = getSpotColor(spot);
            return L.divIcon({
                className: 'custom-parking-icon',
                html: `
                    <div style="
                        background: ${color};
                        width: 25px;
                        height: 25px;
                        border-radius: 50%;
                        border: 3px solid white;
                        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        font-weight: bold;
                        font-size: 10px;
                    ">P</div>
                `,
                iconSize: [25, 25],
                iconAnchor: [12, 12]
            });
        }

        // تهيئة خريطة Leaflet
        function initLeafletMap() {
            // تحديد المركز الافتراضي بناءً على المواقف المصفاة
            let centerLat = 30.0444;
            let centerLng = 31.2357;
            
            if (parkingSpots.length > 0) {
                centerLat = parseFloat(parkingSpots[0].lat);
                centerLng = parseFloat(parkingSpots[0].lng);
            }

            // إنشاء الخريطة
            leafletMap = L.map('leafletMap').setView([centerLat, centerLng], 16);

            // إضافة طبقة الخريطة
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(leafletMap);

            // إضافة markers للمواقف
            parkingSpots.forEach(spot => {
                const icon = getSpotIcon(spot);
                const marker = L.marker([parseFloat(spot.lat), parseFloat(spot.lng)], { 
                    icon: icon 
                }).addTo(leafletMap);
                
                // إنشاء محتوى النافذة المنبثقة
                const popupContent = `
                    <div class="spot-info-window">
                        <div class="spot-info-header">
                            Spot ${spot.spot_number}
                            <span class="spot-type">${spot.spot_type.toUpperCase()}</span>
                        </div>
                        <div class="spot-status status-${spot.status}">
                            ${spot.status === 'available' ? 'Available' : 'Occupied'}
                        </div>
                        <div><strong>Mall:</strong> ${spot.mall_name}</div>
                        <div><strong>Zone:</strong> ${spot.zone_name}</div>
                        <div><strong>Floor:</strong> ${spot.floor}</div>
                        <div><strong>Price:</strong> $${spot.hourly_rate}/hour</div>
                        ${spot.status === 'available' ? 
                            `<button class="book-btn" onclick="bookSpot(${spot.id})">
                                <i class="fas fa-calendar-plus"></i> Book Now
                            </button>` : 
                            '<button class="book-btn" disabled>Currently Occupied</button>'
                        }
                    </div>
                `;

                marker.bindPopup(popupContent);
            });

            // إضافة تحكم التكبير
            L.control.zoom({ position: 'topright' }).addTo(leafletMap);
        }

        // دالة حجز الموقف
        function bookSpot(spotId) {
            const spot = parkingSpots.find(s => s.id == spotId);
            if (spot && spot.status === 'available') {
                if (confirm(`Do you want to book parking spot ${spot.spot_number} at ${spot.mall_name} for $${spot.hourly_rate}/hour?`)) {
                    // استخدام AJAX لإرسال طلب الحجز
                    fetch('book_spot.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `spot_id=${spotId}&user_id=<?php echo $user_id; ?>`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`✅ Spot ${spot.spot_number} booked successfully!\nYou will receive a confirmation notification.`);
                            // إعادة تحميل الصفحة لتحديث البيانات
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            alert(`❌ Error: ${data.message}`);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('❌ An error occurred while booking the spot.');
                    });
                }
            }
        }

        // تحديث قائمة المناطق عند تغيير المول
        document.getElementById('mall').addEventListener('change', function() {
            const mallName = this.value;
            if (mallName) {
                fetch(`get_zones.php?mall=${encodeURIComponent(mallName)}`)
                    .then(response => response.json())
                    .then(zones => {
                        const zoneSelect = document.getElementById('zone');
                        zoneSelect.innerHTML = '<option value="">All Zones</option>';
                        zones.forEach(zone => {
                            zoneSelect.innerHTML += `<option value="${zone}">${zone}</option>`;
                        });
                    })
                    .catch(error => console.error('Error fetching zones:', error));
            }
        });

        // تهيئة الخريطة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            initLeafletMap();
        });

        // تحديث تلقائي للخريطة كل 30 ثانية
        setInterval(() => {
            if (leafletMap) {
                leafletMap.remove();
                initLeafletMap();
            }
        }, 30000);
    </script>
</body>
</html>