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
    $total_spots_stmt = $pdo->query("SELECT COUNT(*) as total FROM parking_spots WHERE status != 'maintenance'");
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
    
    // الإشعارات غير المقروءة
    $notifications_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $notifications_stmt->execute([$user_id]);
    $unread_notifications = $notifications_stmt->fetch()['unread'];

    // جلب بيانات المواقف من قاعدة البيانات مع الأسعار المحدثة
    $spots_stmt = $pdo->query("
        SELECT ps.*, 
               pz.zone_name,
               pz.floor,
               b.id as booking_id, 
               b.vehicle_plate, 
               b.vehicle_type,
               b.start_time,
               b.end_time,
               u.username
        FROM parking_spots ps
        LEFT JOIN parking_zones pz ON ps.zone_id = pz.id
        LEFT JOIN bookings b ON ps.id = b.spot_id AND b.status = 'active'
        LEFT JOIN users u ON b.user_id = u.id
        WHERE ps.status != 'maintenance'
        ORDER BY pz.floor, pz.zone_name, ps.spot_number
    ");
    $parking_spots = $spots_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب حجوزات المستخدم الحالي
    $bookings_stmt = $pdo->prepare("
        SELECT b.*, ps.spot_number, ps.zone_id, pz.zone_name, pz.floor, ps.hourly_rate
        FROM bookings b
        JOIN parking_spots ps ON b.spot_id = ps.id
        LEFT JOIN parking_zones pz ON ps.zone_id = pz.id
        WHERE b.user_id = ? AND b.status = 'active'
        ORDER BY b.created_at DESC
    ");
    $bookings_stmt->execute([$user_id]);
    $user_bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب إعدادات الأسعار من system_settings
    $settings_stmt = $pdo->query("SELECT * FROM system_settings WHERE setting_key IN ('parking_hourly_rate', 'parking_max_hours')");
    $settings_data = $settings_stmt->fetchAll(PDO::FETCH_ASSOC);
    $settings = [];
    foreach($settings_data as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    $default_hourly_rate = $settings['parking_hourly_rate'] ?? 5;
    $max_hours = $settings['parking_max_hours'] ?? 24;
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// إحصائيات المواقف
$total_spots = count($parking_spots);
$available_spots = count(array_filter($parking_spots, function($spot) {
    return $spot['status'] === 'available' && empty($spot['booking_id']);
}));
$occupied_spots = $total_spots - $available_spots;
$maintenance_spots = count(array_filter($parking_spots, function($spot) {
    return $spot['status'] === 'maintenance';
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Parking - SmartPark</title>
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
            --vip: #8b5cf6;
            --disabled: #9ca3af;
            --electric: #10b981;
            --family: #ec4899;
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

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Parking Section */
        .parking-section {
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

        .floor-selector {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .floor-btn {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            color: var(--dark);
        }

        .floor-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        .floor-btn:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: translateY(-2px);
        }

        /* Parking Grid */
        .parking-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .parking-zone {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .parking-zone:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .zone-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid #dee2e6;
        }

        .zone-header h3 {
            color: var(--dark);
            font-size: 1.3em;
            margin-bottom: 5px;
        }

        .zone-stats {
            font-size: 0.9em;
            color: #6c757d;
        }

        .spots-container {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
        }

        .parking-spot {
            aspect-ratio: 1;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85em;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .spot-available {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .spot-occupied {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
        }

        .spot-reserved {
            background: linear-gradient(135deg, var(--warning), #d97706);
            color: white;
        }

        .spot-vip {
            border-color: var(--vip);
            background: linear-gradient(135deg, var(--vip), #7c3aed);
            color: white;
        }

        .spot-disabled {
            background: linear-gradient(135deg, var(--disabled), #6b7280);
            color: white;
        }

        .spot-electric {
            border-color: var(--electric);
            background: linear-gradient(135deg, var(--electric), #059669);
            color: white;
        }

        .spot-family {
            border-color: var(--family);
            background: linear-gradient(135deg, var(--family), #be185d);
            color: white;
        }

        .parking-spot:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }

        .spot-info {
            position: absolute;
            bottom: -100%;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 8px;
            font-size: 0.7em;
            text-align: center;
            transition: bottom 0.3s ease;
        }

        .parking-spot:hover .spot-info {
            bottom: 0;
        }

        /* Bookings Section */
        .bookings-section {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            max-height: 600px;
            overflow-y: auto;
        }

        .booking-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }

        .booking-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .booking-spot {
            font-weight: 700;
            color: var(--dark);
            font-size: 1.2em;
        }

        .booking-status {
            padding: 6px 12px;
            border-radius: 6px;
            color: white;
            font-size: 0.8em;
            font-weight: 600;
        }

        .status-active { 
            background: linear-gradient(135deg, var(--success), #059669); 
        }
        .status-completed { 
            background: linear-gradient(135deg, var(--info), #0891b2); 
        }
        .status-cancelled { 
            background: linear-gradient(135deg, var(--danger), #dc2626); 
        }

        .booking-details {
            color: #6c757d;
            font-size: 0.9em;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .booking-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8em;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-extend {
            background: linear-gradient(135deg, var(--warning), #d97706);
            color: white;
        }

        .btn-cancel {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
        }

        .btn-navigate {
            background: linear-gradient(135deg, var(--info), #0891b2);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Legend */
        .legend {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 0.8em;
            background: white;
            padding: 6px 12px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }

        .legend-available { background: var(--success); }
        .legend-occupied { background: var(--danger); }
        .legend-reserved { background: var(--warning); }
        .legend-vip { background: var(--vip); }
        .legend-disabled { background: var(--disabled); }
        .legend-electric { background: var(--electric); }
        .legend-family { background: var(--family); }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            color: var(--dark);
            font-size: 1.5em;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #7f8c8d;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: var(--danger);
        }

        .booking-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-weight: 600;
            color: var(--dark);
        }

        .form-group input, .form-group select {
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .booking-actions-modal {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Navigation Panel */
        .navigation-panel {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
            display: none;
        }

        .navigation-active .navigation-panel {
            display: block;
        }

        .route-info {
            background: #e8f4fd;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid var(--info);
        }

        .route-steps {
            list-style: none;
            padding-left: 0;
        }

        .route-step {
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .route-step:hover {
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        /* WebSocket Status */
        .websocket-status {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            z-index: 1001;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .status-connected {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .status-disconnected {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
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
            
            .spots-container {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .legend {
                gap: 10px;
            }
            
            .booking-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 20px 15px;
            }
            
            .welcome-section h1 {
                font-size: 2em;
            }
            
            .legend {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .parking-grid {
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

        .no-bookings {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .no-bookings i {
            font-size: 3em;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- WebSocket Status Indicator -->
    <div class="websocket-status status-disconnected" id="websocketStatus">
        <i class="fas fa-plug"></i>
        <span>Connecting...</span>
    </div>

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
                <a href="booking_user.php" class="active">
                    <i class="fas fa-calendar-check"></i> 
                    <span class="nav-text">Book Parking</span>
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
                <h1>Book Your Parking Spot 🚗</h1>
                <p>Real-time parking availability with instant booking</p>
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
                        <i class="fas fa-arrow-up"></i> Managed in real-time
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
                        <i class="fas fa-sync-alt"></i> Live updates
                    </div>
                </div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.2s;">
                <div class="stat-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="stat-content">
                    <h3>Occupied Spots</h3>
                    <div class="stat-value"><?php echo $occupied_spots; ?></div>
                    <div class="stat-change">
                        <i class="fas fa-chart-line"></i> Real-time tracking
                    </div>
                </div>
            </div>
            
            <div class="stat-card fade-in-up" style="animation-delay: 0.3s;">
                <div class="stat-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-content">
                    <h3>Under Maintenance</h3>
                    <div class="stat-value"><?php echo $maintenance_spots; ?></div>
                    <div class="stat-change">
                        <i class="fas fa-exclamation-triangle"></i> Manager controlled
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-grid">
            <!-- Parking Map Section -->
            <div class="parking-section fade-in-up" style="animation-delay: 0.4s;">
                <div class="section-header">
                    <h2><i class="fas fa-parking"></i> Available Parking Spots</h2>
                    <div class="floor-selector">
                        <button class="floor-btn active" data-floor="all">All Floors</button>
                        <button class="floor-btn" data-floor="1">Floor 1</button>
                        <button class="floor-btn" data-floor="2">Floor 2</button>
                        <button class="floor-btn" data-floor="3">Floor 3</button>
                    </div>
                </div>

                <div class="parking-grid">
                    <?php
                    // تجميع المواقف حسب المنطقة والطابق
                    $zones = [];
                    foreach ($parking_spots as $spot) {
                        $floor = $spot['floor'] ?? 1;
                        $zone_key = "Floor {$floor} - " . ($spot['zone_name'] ?? 'Zone ' . $spot['zone_id']);
                        $zones[$zone_key][] = $spot;
                    }
                    
                    foreach ($zones as $zone_name => $spots): 
                        $zone_available = count(array_filter($spots, function($s) {
                            return $s['status'] === 'available' && empty($s['booking_id']);
                        }));
                        $floor = explode(' - ', $zone_name)[0];
                    ?>
                    <div class="parking-zone" data-floor="<?php echo explode(' ', $floor)[1]; ?>">
                        <div class="zone-header">
                            <h3><?php echo $zone_name; ?></h3>
                            <div class="zone-stats">
                                <?php echo count($spots); ?> spots • 
                                <?php echo $zone_available; ?> available •
                                $<?php echo $spots[0]['hourly_rate'] ?? $default_hourly_rate; ?>/hour
                            </div>
                        </div>
                        <div class="spots-container">
                            <?php foreach ($spots as $spot): 
                                $spot_class = 'spot-' . $spot['status'];
                                if ($spot['spot_type'] === 'vip') $spot_class .= ' spot-vip';
                                if ($spot['spot_type'] === 'disabled') $spot_class .= ' spot-disabled';
                                if ($spot['spot_type'] === 'electric') $spot_class .= ' spot-electric';
                                if ($spot['spot_type'] === 'family') $spot_class .= ' spot-family';
                                
                                $is_available = $spot['status'] === 'available' && empty($spot['booking_id']);
                                $hourly_rate = $spot['hourly_rate'] ?? $default_hourly_rate;
                            ?>
                            <div class="parking-spot <?php echo $spot_class; ?>" 
                                 onclick="openBookingModal(
                                     <?php echo $spot['id']; ?>, 
                                     '<?php echo $spot['spot_number']; ?>', 
                                     <?php echo $is_available ? 'true' : 'false'; ?>,
                                     <?php echo $hourly_rate; ?>,
                                     '<?php echo $spot['spot_type']; ?>'
                                 )">
                                <?php echo $spot['spot_number']; ?>
                                <div class="spot-info">
                                    <strong>Spot <?php echo $spot['spot_number']; ?></strong><br>
                                    <?php 
                                    if ($is_available) echo '✅ Available';
                                    elseif (!empty($spot['booking_id'])) echo '🔒 Booked by ' . $spot['username'];
                                    else echo '🚫 ' . ucfirst($spot['status']);
                                    ?>
                                    <br>
                                    Type: <?php echo ucfirst($spot['spot_type']); ?>
                                    <br>
                                    Rate: $<?php echo $hourly_rate; ?>/hour
                                    <?php if ($spot['spot_type'] === 'vip'): ?>
                                    <br>⭐ VIP Spot
                                    <?php elseif ($spot['spot_type'] === 'disabled'): ?>
                                    <br>♿ Accessible
                                    <?php elseif ($spot['spot_type'] === 'electric'): ?>
                                    <br>🔌 EV Charging
                                    <?php elseif ($spot['spot_type'] === 'family'): ?>
                                    <br>👨‍👩‍👧‍👦 Family
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
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
                        <div class="legend-color legend-reserved"></div>
                        <span>Reserved</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-vip"></div>
                        <span>VIP Spot</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-disabled"></div>
                        <span>Disabled</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-electric"></div>
                        <span>Electric</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-family"></div>
                        <span>Family</span>
                    </div>
                </div>
            </div>

            <!-- Bookings Section -->
            <div class="bookings-section fade-in-up" style="animation-delay: 0.5s;">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> My Active Bookings</h2>
                </div>

                <?php if (empty($user_bookings)): ?>
                    <div class="no-bookings">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Active Bookings</h3>
                        <p>Book a parking spot to see it here</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($user_bookings as $booking): 
                        $current_rate = $booking['hourly_rate'] ?? $default_hourly_rate;
                    ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <div class="booking-spot">
                                Spot <?php echo $booking['spot_number']; ?> 
                                (<?php echo $booking['zone_name'] ?? 'Zone ' . $booking['zone_id']; ?>)
                            </div>
                            <div class="booking-status status-active">
                                Active • $<?php echo $current_rate; ?>/hour
                            </div>
                        </div>
                        <div class="booking-details">
                            <div><strong>Vehicle:</strong> <?php echo strtoupper($booking['vehicle_type']); ?> - <?php echo $booking['vehicle_plate']; ?></div>
                            <div><strong>Start:</strong> <?php echo date('M j, g:i A', strtotime($booking['start_time'])); ?></div>
                            <div><strong>End:</strong> <?php echo date('M j, g:i A', strtotime($booking['end_time'])); ?></div>
                            <div><strong>Current Rate:</strong> $<?php echo $current_rate; ?>/hour</div>
                            <div><strong>Total Paid:</strong> $<?php echo $booking['amount']; ?></div>
                        </div>
                        <div class="booking-actions">
                            <button class="action-btn btn-extend" onclick="extendBooking(<?php echo $booking['id']; ?>, <?php echo $current_rate; ?>)">
                                <i class="fas fa-clock"></i> Extend
                            </button>
                            <button class="action-btn btn-cancel" onclick="cancelBooking(<?php echo $booking['id']; ?>)">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button class="action-btn btn-navigate" onclick="showNavigation(<?php echo $booking['spot_id']; ?>, '<?php echo $booking['spot_number']; ?>')">
                                <i class="fas fa-route"></i> Navigate
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation Panel -->
        <div class="navigation-panel" id="navigationPanel">
            <div class="section-header">
                <h2><i class="fas fa-route"></i> Indoor Navigation</h2>
                <button class="close-modal" onclick="hideNavigation()">&times;</button>
            </div>
            <div class="route-info" id="routeInfo">
                <i class="fas fa-info-circle"></i> 
                <span>Select a parking spot to see navigation instructions</span>
            </div>
            <ol class="route-steps" id="routeSteps">
                <!-- Route steps will be populated by JavaScript -->
            </ol>
        </div>
    </div>

    <!-- Booking Modal -->
    <div class="modal" id="bookingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Book Parking Spot</h3>
                <button class="close-modal" onclick="closeBookingModal()">&times;</button>
            </div>
            <div class="booking-form">
                <div class="form-group">
                    <label>Parking Spot</label>
                    <input type="text" id="selectedSpot" readonly>
                </div>
                <div class="form-group">
                    <label>Vehicle Type</label>
                    <select id="vehicleType">
                        <option value="car">Car</option>
                        <option value="suv">SUV</option>
                        <option value="motorcycle">Motorcycle</option>
                        <option value="truck">Truck</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>License Plate</label>
                    <input type="text" id="licensePlate" placeholder="Enter license plate" maxlength="20">
                </div>
                <div class="form-group">
                    <label>Duration</label>
                    <select id="bookingDuration">
                        <!-- Options will be populated dynamically -->
                    </select>
                </div>
                <div class="form-group">
                    <label>Total Amount</label>
                    <input type="text" id="totalAmount" readonly>
                </div>
                <div class="booking-actions-modal">
                    <button class="btn btn-secondary" onclick="closeBookingModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn btn-primary" onclick="confirmBooking()">
                        <i class="fas fa-check"></i> Confirm Booking
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // بيانات WebSocket
        let websocket = null;
        let reconnectInterval = null;
        let selectedSpotId = null;
        let selectedSpotNumber = null;
        let selectedHourlyRate = null;
        let selectedSpotType = null;

        // تهيئة WebSocket
        function initWebSocket() {
            const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            const wsUrl = `${protocol}//${window.location.host}/ws/parking`;
            
            try {
                websocket = new WebSocket(wsUrl);
                
                websocket.onopen = function() {
                    console.log('WebSocket connected');
                    updateWebSocketStatus(true);
                    clearInterval(reconnectInterval);
                };
                
                websocket.onmessage = function(event) {
                    const data = JSON.parse(event.data);
                    handleWebSocketMessage(data);
                };
                
                websocket.onclose = function() {
                    console.log('WebSocket disconnected');
                    updateWebSocketStatus(false);
                    // محاولة إعادة الاتصال كل 5 ثواني
                    reconnectInterval = setInterval(initWebSocket, 5000);
                };
                
                websocket.onerror = function(error) {
                    console.error('WebSocket error:', error);
                    updateWebSocketStatus(false);
                };
                
            } catch (error) {
                console.error('WebSocket initialization failed:', error);
                updateWebSocketStatus(false);
            }
        }

        // تحديث حالة اتصال WebSocket
        function updateWebSocketStatus(connected) {
            const statusElement = document.getElementById('websocketStatus');
            if (connected) {
                statusElement.className = 'websocket-status status-connected';
                statusElement.innerHTML = '<i class="fas fa-plug"></i><span>Live Updates Connected</span>';
            } else {
                statusElement.className = 'websocket-status status-disconnected';
                statusElement.innerHTML = '<i class="fas fa-plug"></i><span>Reconnecting...</span>';
            }
        }

        // معالجة رسائل WebSocket
        function handleWebSocketMessage(data) {
            switch (data.type) {
                case 'spot_status_update':
                    updateSpotStatus(data.spotId, data.status, data.bookingInfo);
                    break;
                case 'booking_confirmed':
                    showBookingConfirmation(data.booking);
                    break;
                case 'spot_released':
                    releaseSpot(data.spotId);
                    break;
                case 'price_updated':
                    updateSpotPrices(data.spotId, data.hourlyRate);
                    break;
            }
        }

        // تحديث حالة الموقف
        function updateSpotStatus(spotId, status, bookingInfo) {
            const spotElement = document.querySelector(`.parking-spot[onclick*="${spotId}"]`);
            if (spotElement) {
                // تحديث class بناءً على الحالة الجديدة
                spotElement.className = `parking-spot spot-${status}`;
                
                // تحديث معلومات الموقف
                const spotInfo = spotElement.querySelector('.spot-info');
                if (spotInfo) {
                    let infoText = `Spot ${spotId} - ${status.charAt(0).toUpperCase() + status.slice(1)}`;
                    if (bookingInfo && status === 'occupied') {
                        infoText += ` by ${bookingInfo.username}`;
                    }
                    spotInfo.innerHTML = infoText;
                }
            }
        }

        // تحديث أسعار المواقف
        function updateSpotPrices(spotId, hourlyRate) {
            const spotElement = document.querySelector(`.parking-spot[onclick*="${spotId}"]`);
            if (spotElement) {
                const spotInfo = spotElement.querySelector('.spot-info');
                if (spotInfo) {
                    const infoText = spotInfo.innerHTML.replace(/\$[\d.]+(?=\/hour)/, `$${hourlyRate}`);
                    spotInfo.innerHTML = infoText;
                }
            }
        }

        // فتح نافذة الحجز
        function openBookingModal(spotId, spotNumber, isAvailable, hourlyRate, spotType) {
            if (!isAvailable) {
                alert('This parking spot is not available for booking.');
                return;
            }

            selectedSpotId = spotId;
            selectedSpotNumber = spotNumber;
            selectedHourlyRate = parseFloat(hourlyRate);
            selectedSpotType = spotType;
            
            document.getElementById('selectedSpot').value = `Spot ${spotNumber} (${spotType})`;
            document.getElementById('modalTitle').textContent = `Book Spot ${spotNumber}`;
            
            // تحديث خيارات المدة بناءً على السعر
            updateDurationOptions(selectedHourlyRate);
            
            document.getElementById('bookingModal').style.display = 'flex';
        }

        // دالة لتحديث خيارات المدة بناءً على السعر
        function updateDurationOptions(hourlyRate) {
            const durationSelect = document.getElementById('bookingDuration');
            const prices = calculatePrices(hourlyRate);
            
            durationSelect.innerHTML = '';
            
            prices.forEach((price, index) => {
                const hours = index + 1;
                const option = document.createElement('option');
                option.value = hours;
                option.textContent = `${hours} hour${hours > 1 ? 's' : ''} - $${price}`;
                option.setAttribute('data-amount', price);
                durationSelect.appendChild(option);
            });

            // تحديث المبلغ الإجمالي عند تغيير المدة
            updateTotalAmount();
        }

        // دالة لحساب الأسعار بناءً على السعر لكل ساعة
        function calculatePrices(hourlyRate) {
            const prices = [];
            const maxHours = <?php echo $max_hours; ?>;
            
            for (let i = 1; i <= Math.min(6, maxHours); i++) {
                let price = i * hourlyRate;
                // تطبيق خصم للفترات الطويلة
                if (i >= 3) price *= 0.9; // 10% discount for 3+ hours
                if (i >= 6) price *= 0.85; // 15% discount for 6+ hours
                
                prices.push(price.toFixed(2));
            }
            
            // إضافة خيارات للفترات الطويلة
            if (maxHours >= 12) {
                prices.push((12 * hourlyRate * 0.8).toFixed(2)); // 20% discount for 12 hours
            }
            if (maxHours >= 24) {
                prices.push((24 * hourlyRate * 0.7).toFixed(2)); // 30% discount for 24 hours
            }
            
            return prices;
        }

        // تحديث المبلغ الإجمالي
        function updateTotalAmount() {
            const durationSelect = document.getElementById('bookingDuration');
            const selectedOption = durationSelect.options[durationSelect.selectedIndex];
            const amount = selectedOption.getAttribute('data-amount');
            document.getElementById('totalAmount').value = `$${amount}`;
        }

        // إغلاق نافذة الحجز
        function closeBookingModal() {
            document.getElementById('bookingModal').style.display = 'none';
            selectedSpotId = null;
            selectedSpotNumber = null;
            selectedHourlyRate = null;
            selectedSpotType = null;
        }

        // تأكيد الحجز
        function confirmBooking() {
            const vehicleType = document.getElementById('vehicleType').value;
            const licensePlate = document.getElementById('licensePlate').value;
            const duration = document.getElementById('bookingDuration').value;

            if (!licensePlate.trim()) {
                alert('Please enter your license plate number.');
                return;
            }

            // حساب المبلغ بناءً على السعر الحالي
            const prices = calculatePrices(selectedHourlyRate);
            const amount = prices[duration - 1];

            // إرسال طلب الحجز عبر AJAX
            const formData = new FormData();
            formData.append('spot_id', selectedSpotId);
            formData.append('vehicle_type', vehicleType);
            formData.append('license_plate', licensePlate);
            formData.append('duration', duration);
            formData.append('amount', amount);
            formData.append('hourly_rate', selectedHourlyRate);
            formData.append('action', 'book_spot');

            fetch('booking_processor.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.redirect) {
                        // الانتقال إلى صفحة الدفع
                        window.location.href = data.redirect;
                    } else {
                        showBookingConfirmation(data.booking);
                        closeBookingModal();
                    }
                } else {
                    alert('Booking failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing your booking.');
            });
        }

        // إظهار تأكيد الحجز
        function showBookingConfirmation(booking) {
            alert(`Booking confirmed! 🎉\n\nSpot: ${booking.spot_number}\nVehicle: ${booking.vehicle_type}\nLicense: ${booking.vehicle_plate}\nDuration: ${booking.duration} hours\nAmount: $${booking.amount}`);
            
            // تحديث الصفحة
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }

        // تمديد الحجز
        function extendBooking(bookingId, currentRate) {
            const newDuration = prompt('Enter additional hours to extend:');
            if (newDuration && !isNaN(newDuration) && newDuration > 0) {
                const additionalAmount = (newDuration * currentRate).toFixed(2);
                
                if (confirm(`Extend booking by ${newDuration} hours for $${additionalAmount}?`)) {
                    // إرسال طلب التمديد
                    const formData = new FormData();
                    formData.append('booking_id', bookingId);
                    formData.append('additional_hours', newDuration);
                    formData.append('additional_amount', additionalAmount);
                    formData.append('action', 'extend_booking');

                    fetch('booking_processor.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Booking extended successfully!');
                            window.location.reload();
                        } else {
                            alert('Extension failed: ' + data.message);
                        }
                    });
                }
            }
        }

        // إلغاء الحجز
        function cancelBooking(bookingId) {
            if (confirm('Are you sure you want to cancel this booking?')) {
                const formData = new FormData();
                formData.append('booking_id', bookingId);
                formData.append('action', 'cancel_booking');

                fetch('booking_processor.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Booking cancelled successfully!');
                        
                        // إرسال تحديث عبر WebSocket
                        if (websocket && websocket.readyState === WebSocket.OPEN) {
                            websocket.send(JSON.stringify({
                                type: 'spot_released',
                                spotId: data.spot_id
                            }));
                        }
                        
                        window.location.reload();
                    } else {
                        alert('Cancellation failed: ' + data.message);
                    }
                });
            }
        }

        // إظهار التنقل
        function showNavigation(spotId, spotNumber) {
            document.getElementById('navigationPanel').style.display = 'block';
            document.body.classList.add('navigation-active');
            
            // محاكاة بيانات التنقل
            const routeInfo = document.getElementById('routeInfo');
            const routeSteps = document.getElementById('routeSteps');
            
            routeInfo.innerHTML = `<i class="fas fa-info-circle"></i> Navigation to Spot ${spotNumber}`;
            
            // خطوات التنقل المحاكاة
            const steps = [
                'Enter through the main entrance',
                'Take the first right towards Zone ' + spotNumber.charAt(0),
                'Follow the signs to Floor 1',
                'Look for spot ' + spotNumber + ' on your left',
                'Park your vehicle in the designated spot'
            ];
            
            routeSteps.innerHTML = '';
            steps.forEach((step, index) => {
                const li = document.createElement('li');
                li.className = 'route-step';
                li.textContent = `${index + 1}. ${step}`;
                routeSteps.appendChild(li);
            });
        }

        // إخفاء التنقل
        function hideNavigation() {
            document.getElementById('navigationPanel').style.display = 'none';
            document.body.classList.remove('navigation-active');
        }

        // تحرير الموقف
        function releaseSpot(spotId) {
            const spotElement = document.querySelector(`.parking-spot[onclick*="${spotId}"]`);
            if (spotElement) {
                spotElement.className = 'parking-spot spot-available';
                const spotInfo = spotElement.querySelector('.spot-info');
                if (spotInfo) {
                    spotInfo.innerHTML = `Spot ${spotId} - Available`;
                }
            }
        }

        // إغلاق المودال عند النقر خارجها
        window.onclick = function(event) {
            const modal = document.getElementById('bookingModal');
            if (event.target === modal) {
                closeBookingModal();
            }
        }

        // تهيئة الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            initWebSocket();
            
            // إضافة تأثيرات للبطاقات
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // فلترة الطوابق
            document.querySelectorAll('.floor-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.floor-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const selectedFloor = this.getAttribute('data-floor');
                    document.querySelectorAll('.parking-zone').forEach(zone => {
                        if (selectedFloor === 'all' || zone.getAttribute('data-floor') === selectedFloor) {
                            zone.style.display = 'block';
                        } else {
                            zone.style.display = 'none';
                        }
                    });
                });
            });

            // تحديث المبلغ الإجمالي عند تغيير المدة
            document.getElementById('bookingDuration').addEventListener('change', updateTotalAmount);

            // تحديث تلقائي كل 15 ثانية للحصول على أحدث البيانات
            setInterval(() => {
                if (!websocket || websocket.readyState !== WebSocket.OPEN) {
                    // إعادة تحميل البيانات من الخادم
                    fetch(window.location.href + '?refresh=true')
                        .then(() => window.location.reload());
                }
            }, 15000);
        });

        // تحديث تلقائي كل 30 ثانية (للحالات التي لا يعمل فيها WebSocket)
        setInterval(() => {
            if (!websocket || websocket.readyState !== WebSocket.OPEN) {
                window.location.reload();
            }
        }, 30000);
    </script>
</body>
</html>