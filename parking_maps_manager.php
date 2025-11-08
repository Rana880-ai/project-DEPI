<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is manager
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'manager') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Create missing tables if they don't exist
try {
    // Create malls table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS malls (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mall_name VARCHAR(100) NOT NULL,
            location VARCHAR(255) NOT NULL,
            total_floors INT DEFAULT 1,
            total_spots INT DEFAULT 0,
            contact_email VARCHAR(100),
            contact_phone VARCHAR(20),
            description TEXT,
            lat DECIMAL(10, 8),
            lng DECIMAL(11, 8),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Add columns to parking_zones if they don't exist
    $pdo->exec("ALTER TABLE parking_zones ADD COLUMN IF NOT EXISTS mall_id INT");
    $pdo->exec("ALTER TABLE parking_zones ADD COLUMN IF NOT EXISTS total_spots INT DEFAULT 0");
    $pdo->exec("ALTER TABLE parking_zones ADD COLUMN IF NOT EXISTS available_spots INT DEFAULT 0");
    
    // Add foreign key constraint
    $pdo->exec("ALTER TABLE parking_zones ADD CONSTRAINT fk_parking_zones_mall FOREIGN KEY (mall_id) REFERENCES malls(id) ON DELETE CASCADE");
    
} catch (PDOException $e) {
    // Ignore errors if tables/columns already exist
}

// Process form requests
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if(isset($_POST['action'])) {
            switch($_POST['action']) {
                case 'add_mall':
                    if(!empty($_POST['mall_name']) && !empty($_POST['mall_location'])) {
                        $stmt = $pdo->prepare("INSERT INTO malls (mall_name, location, total_floors, total_spots, contact_email, contact_phone, description, lat, lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $_POST['mall_name'],
                            $_POST['mall_location'],
                            $_POST['total_floors'] ?? 1,
                            $_POST['total_spots'] ?? 0,
                            $_POST['contact_email'] ?? '',
                            $_POST['contact_phone'] ?? '',
                            $_POST['description'] ?? '',
                            $_POST['lat'] ?? 0,
                            $_POST['lng'] ?? 0
                        ]);
                        $message = "✅ Mall added successfully";
                    }
                    break;
                    
                case 'update_mall':
                    $stmt = $pdo->prepare("UPDATE malls SET mall_name = ?, location = ?, total_floors = ?, total_spots = ?, contact_email = ?, contact_phone = ?, description = ?, lat = ?, lng = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['mall_name'],
                        $_POST['mall_location'],
                        $_POST['total_floors'],
                        $_POST['total_spots'],
                        $_POST['contact_email'],
                        $_POST['contact_phone'],
                        $_POST['description'],
                        $_POST['lat'],
                        $_POST['lng'],
                        $_POST['mall_id']
                    ]);
                    $message = "✅ Mall updated successfully";
                    break;
                    
                case 'delete_mall':
                    // Check if mall has zones
                    $check_zones = $pdo->prepare("SELECT COUNT(*) as zones_count FROM parking_zones WHERE mall_id = ?");
                    $check_zones->execute([$_POST['mall_id']]);
                    $zones_count = $check_zones->fetch()['zones_count'];
                    
                    if($zones_count > 0) {
                        $error = "❌ Cannot delete mall with existing zones. Delete zones first.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM malls WHERE id = ?");
                        $stmt->execute([$_POST['mall_id']]);
                        $message = "✅ Mall deleted successfully";
                    }
                    break;
                    
                case 'add_zone':
                    if(!empty($_POST['zone_name']) && !empty($_POST['mall_id'])) {
                        $stmt = $pdo->prepare("INSERT INTO parking_zones (mall_id, zone_name, floor, total_spots, available_spots, description) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $_POST['mall_id'],
                            $_POST['zone_name'],
                            $_POST['floor'] ?? 1,
                            $_POST['total_spots'] ?? 0,
                            $_POST['available_spots'] ?? 0,
                            $_POST['description'] ?? ''
                        ]);
                        $message = "✅ Zone added successfully";
                    }
                    break;
                    
                case 'update_zone':
                    $stmt = $pdo->prepare("UPDATE parking_zones SET zone_name = ?, floor = ?, total_spots = ?, available_spots = ?, description = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['zone_name'],
                        $_POST['floor'],
                        $_POST['total_spots'],
                        $_POST['available_spots'],
                        $_POST['description'],
                        $_POST['zone_id']
                    ]);
                    $message = "✅ Zone updated successfully";
                    break;
                    
                case 'delete_zone':
                    // Check if zone has spots
                    $check_spots = $pdo->prepare("SELECT COUNT(*) as spots_count FROM parking_spots WHERE zone_id = ?");
                    $check_spots->execute([$_POST['zone_id']]);
                    $spots_count = $check_spots->fetch()['spots_count'];
                    
                    if($spots_count > 0) {
                        $error = "❌ Cannot delete zone with existing spots. Delete spots first.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM parking_zones WHERE id = ?");
                        $stmt->execute([$_POST['zone_id']]);
                        $message = "✅ Zone deleted successfully";
                    }
                    break;
                    
                case 'add_spot':
                    if(!empty($_POST['spot_number']) && !empty($_POST['zone_id'])) {
                        // Check for duplicate spot number in the same zone
                        $check_stmt = $pdo->prepare("SELECT id FROM parking_spots WHERE spot_number = ? AND zone_id = ?");
                        $check_stmt->execute([$_POST['spot_number'], $_POST['zone_id']]);
                        
                        if($check_stmt->fetch()) {
                            $error = "❌ Spot number already exists in this zone";
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO parking_spots (zone_id, spot_number, spot_type, status, hourly_rate) VALUES (?, ?, ?, 'available', ?)");
                            $stmt->execute([
                                $_POST['zone_id'],
                                $_POST['spot_number'],
                                $_POST['spot_type'] ?? 'regular',
                                $_POST['hourly_rate'] ?? 5.00
                            ]);
                            $message = "✅ Parking spot added successfully";
                        }
                    }
                    break;
                    
                case 'update_spot':
                    $stmt = $pdo->prepare("UPDATE parking_spots SET spot_type = ?, status = ?, hourly_rate = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['spot_type'],
                        $_POST['status'],
                        $_POST['hourly_rate'],
                        $_POST['spot_id']
                    ]);
                    $message = "✅ Spot updated successfully";
                    break;
                    
                case 'delete_spot':
                    // Check for active bookings
                    $check_booking = $pdo->prepare("SELECT id FROM bookings WHERE spot_id = ? AND status = 'active'");
                    $check_booking->execute([$_POST['spot_id']]);
                    
                    if($check_booking->fetch()) {
                        $error = "❌ Cannot delete spot with active bookings";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM parking_spots WHERE id = ?");
                        $stmt->execute([$_POST['spot_id']]);
                        $message = "✅ Spot deleted successfully";
                    }
                    break;
            }
        }
    } catch (PDOException $e) {
        $error = "❌ Database error: " . $e->getMessage();
    }
}

// Fetch data with error handling
try {
    // Fetch malls
    $malls_stmt = $pdo->query("SELECT * FROM malls ORDER BY mall_name");
    $malls = $malls_stmt->fetchAll();
    
    // Fetch zones with mall information
    $zones_stmt = $pdo->query("
        SELECT pz.*, m.mall_name, m.lat as mall_lat, m.lng as mall_lng
        FROM parking_zones pz 
        LEFT JOIN malls m ON pz.mall_id = m.id 
        ORDER BY m.mall_name, pz.floor, pz.zone_name
    ");
    $zones = $zones_stmt->fetchAll();
    
    // Fetch spots with zone and mall information
    $spots_stmt = $pdo->query("
        SELECT 
            ps.*, 
            pz.zone_name,
            pz.floor,
            m.mall_name,
            m.location as mall_location,
            m.lat as mall_lat,
            m.lng as mall_lng,
            COUNT(b.id) as active_bookings
        FROM parking_spots ps 
        LEFT JOIN parking_zones pz ON ps.zone_id = pz.id
        LEFT JOIN malls m ON pz.mall_id = m.id
        LEFT JOIN bookings b ON ps.id = b.spot_id AND b.status = 'active'
        GROUP BY ps.id
        ORDER BY m.mall_name, pz.floor, pz.zone_name, ps.spot_number
    ");
    $parking_spots = $spots_stmt->fetchAll();
    
    // Get statistics
    $total_malls_stmt = $pdo->query("SELECT COUNT(*) as total FROM malls");
    $total_malls = $total_malls_stmt->fetch()['total'];
    
    $total_zones_stmt = $pdo->query("SELECT COUNT(*) as total FROM parking_zones");
    $total_zones = $total_zones_stmt->fetch()['total'];
    
    $total_spots_stmt = $pdo->query("SELECT COUNT(*) as total FROM parking_spots");
    $total_spots = $total_spots_stmt->fetch()['total'];
    
    $available_spots_stmt = $pdo->query("SELECT COUNT(*) as available FROM parking_spots WHERE status = 'available'");
    $available_spots = $available_spots_stmt->fetch()['available'];
    
    $occupied_spots_stmt = $pdo->query("SELECT COUNT(*) as occupied FROM parking_spots WHERE status = 'occupied'");
    $occupied_spots = $occupied_spots_stmt->fetch()['occupied'];
    
    $maintenance_spots_stmt = $pdo->query("SELECT COUNT(*) as maintenance FROM parking_spots WHERE status = 'maintenance'");
    $maintenance_spots = $maintenance_spots_stmt->fetch()['maintenance'];
    
} catch (PDOException $e) {
    // Initialize empty arrays if there's an error
    $malls = [];
    $zones = [];
    $parking_spots = [];
    $total_malls = 0;
    $total_zones = 0;
    $total_spots = 0;
    $available_spots = 0;
    $occupied_spots = 0;
    $maintenance_spots = 0;
    
    $error = "Database initialization needed. Some tables are being created.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Malls & Parking Management - SmartPark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Mapbox CSS -->
    <link href='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css' rel='stylesheet' />
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

        /* Tabs */
        .tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 10px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 15px 30px;
            border: none;
            background: none;
            cursor: pointer;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 120px;
        }

        .tab-btn.active {
            background: var(--secondary);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Map Container */
        .map-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        .map-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
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

        .map-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .control-btn {
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--dark);
        }

        .control-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        .control-btn:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }

        /* Maps */
        #interactiveMap {
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

        .legend-mall { background: var(--primary); }
        .legend-available { background: var(--success); }
        .legend-occupied { background: var(--danger); }
        .legend-maintenance { background: var(--warning); }

        /* Forms */
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--secondary);
            outline: none;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.9em;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Tables */
        .table-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            overflow: hidden;
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .status-available { background: #d1fae5; color: #065f46; }
        .status-occupied { background: #fee2e2; color: #991b1b; }
        .status-maintenance { background: #fef3c7; color: #92400e; }
        .status-reserved { background: #e0e7ff; color: #3730a3; }

        .type-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .type-regular { background: #e0e7ff; color: #3730a3; }
        .type-vip { background: #f3e8ff; color: #6b21a8; }
        .type-disabled { background: #fce7f3; color: #9d174d; }
        .type-electric { background: #dcfce7; color: #166534; }
        .type-family { background: #fce7f3; color: #9d174d; }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
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

        /* Map Popup */
        .map-popup {
            max-width: 300px;
        }

        .popup-header {
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--dark);
            font-size: 1.1em;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 5px;
        }

        .popup-info {
            margin-bottom: 10px;
        }

        .popup-stats {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
        }

        .popup-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
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
            
            .tabs {
                flex-direction: column;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
            
            #interactiveMap {
                height: 400px;
            }
        }

        @media (max-width: 480px) {
            #interactiveMap {
                height: 300px;
            }
            
            .map-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .map-controls {
                width: 100%;
                justify-content: center;
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
            <li><a href="parking_maps_manager.php" class="active"><i class="fas fa-map-marked-alt"></i> <span class="nav-text">parking maps</span></a></li>
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
            <h1>Malls & Parking Maps Management</h1>
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

        <!-- Messages -->
        <?php if($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Malls</h3>
                <div class="stat-value"><?php echo $total_malls; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-building"></i> Shopping centers
                </div>
            </div>
            <div class="stat-card">
                <h3>Parking Zones</h3>
                <div class="stat-value"><?php echo $total_zones; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-layer-group"></i> Managed zones
                </div>
            </div>
            <div class="stat-card">
                <h3>Total Spots</h3>
                <div class="stat-value"><?php echo $total_spots; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-parking"></i> All parking spots
                </div>
            </div>
            <div class="stat-card">
                <h3>Available Now</h3>
                <div class="stat-value"><?php echo $available_spots; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-car"></i> Ready for booking
                </div>
            </div>
        </div>

        <!-- Interactive Map Section -->
        <div class="map-container">
            <div class="map-header">
                <div class="section-header">
                    <h2><i class="fas fa-map-marked-alt"></i> Interactive Parking Map</h2>
                </div>
                <div class="map-controls">
                    <button class="control-btn active" onclick="showAllMalls()">
                        <i class="fas fa-building"></i> All Malls
                    </button>
                    <button class="control-btn" onclick="showAvailableSpots()">
                        <i class="fas fa-car"></i> Available Spots
                    </button>
                    <button class="control-btn" onclick="showOccupiedSpots()">
                        <i class="fas fa-ban"></i> Occupied Spots
                    </button>
                </div>
            </div>

            <div id="interactiveMap">
                <div class="loading" style="display: flex; justify-content: center; align-items: center; height: 100%;">
                    <i class="fas fa-spinner fa-spin"></i> Loading Interactive Map...
                </div>
            </div>

            <!-- Legend -->
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color legend-mall"></div>
                    <span>Shopping Mall</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-available"></div>
                    <span>Available Spot</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-occupied"></div>
                    <span>Occupied Spot</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-maintenance"></div>
                    <span>Maintenance</span>
                </div>
            </div>
        </div>

        <!-- Management Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="openTab('malls-tab')">Manage Malls</button>
            <button class="tab-btn" onclick="openTab('zones-tab')">Manage Zones</button>
            <button class="tab-btn" onclick="openTab('spots-tab')">Manage Spots</button>
            <button class="tab-btn" onclick="openTab('add-mall-tab')">Add New Mall</button>
            <button class="tab-btn" onclick="openTab('add-zone-tab')">Add New Zone</button>
            <button class="tab-btn" onclick="openTab('add-spot-tab')">Add New Spot</button>
        </div>

        <!-- Malls Management Tab -->
        <div id="malls-tab" class="tab-content active">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Mall Name</th>
                            <th>Location</th>
                            <th>Floors</th>
                            <th>Total Spots</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($malls)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                                    <i class="fas fa-building" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                    <p>No malls found. Add your first mall to get started.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($malls as $mall): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($mall['mall_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($mall['location']); ?></td>
                                <td><?php echo $mall['total_floors']; ?> floors</td>
                                <td><?php echo $mall['total_spots']; ?> spots</td>
                                <td>
                                    <div><?php echo htmlspecialchars($mall['contact_email']); ?></div>
                                    <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($mall['contact_phone']); ?></div>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-primary btn-sm" onclick="editMall(<?php echo $mall['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteMall(<?php echo $mall['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                        <button class="btn btn-info btn-sm" onclick="focusOnMall(<?php echo $mall['id']; ?>, <?php echo $mall['lat'] ?? 30.0444; ?>, <?php echo $mall['lng'] ?? 31.2357; ?>)">
                                            <i class="fas fa-map-marker-alt"></i> View on Map
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- باقي التبويبات تبقى كما هي بدون تغيير -->
        <!-- Zones Management Tab -->
        <div id="zones-tab" class="tab-content">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Zone Name</th>
                            <th>Mall</th>
                            <th>Floor</th>
                            <th>Total Spots</th>
                            <th>Available</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($zones)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                                    <i class="fas fa-layer-group" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                    <p>No zones found. Add your first zone to get started.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($zones as $zone): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($zone['zone_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($zone['mall_name']); ?></td>
                                <td>Floor <?php echo $zone['floor']; ?></td>
                                <td><?php echo $zone['total_spots']; ?></td>
                                <td><?php echo $zone['available_spots']; ?></td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-primary btn-sm" onclick="editZone(<?php echo $zone['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteZone(<?php echo $zone['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Spots Management Tab -->
        <div id="spots-tab" class="tab-content">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Spot Number</th>
                            <th>Mall</th>
                            <th>Zone</th>
                            <th>Floor</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Price/Hour</th>
                            <th>Active Bookings</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($parking_spots as $spot): ?>
                        <tr>
                            <td><strong><?php echo $spot['spot_number']; ?></strong></td>
                            <td><?php echo $spot['mall_name']; ?></td>
                            <td><?php echo $spot['zone_name']; ?></td>
                            <td>Floor <?php echo $spot['floor']; ?></td>
                            <td>
                                <span class="type-badge type-<?php echo $spot['spot_type']; ?>">
                                    <?php 
                                    $type_names = [
                                        'regular' => 'Regular',
                                        'vip' => 'VIP',
                                        'disabled' => 'Disabled',
                                        'electric' => 'Electric',
                                        'family' => 'Family'
                                    ];
                                    echo $type_names[$spot['spot_type']] ?? $spot['spot_type']; 
                                    ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $spot['status']; ?>">
                                    <?php 
                                    $status_names = [
                                        'available' => 'Available',
                                        'occupied' => 'Occupied',
                                        'maintenance' => 'Maintenance',
                                        'reserved' => 'Reserved'
                                    ];
                                    echo $status_names[$spot['status']] ?? $spot['status']; 
                                    ?>
                                </span>
                            </td>
                            <td>$<?php echo $spot['hourly_rate']; ?></td>
                            <td><?php echo $spot['active_bookings']; ?></td>
                            <td>
                                <div class="actions">
                                    <button class="btn btn-primary btn-sm" onclick="editSpot(<?php echo $spot['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteSpot(<?php echo $spot['id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- باقي النماذج تبقى كما هي -->
        <!-- Add Mall Tab -->
        <div id="add-mall-tab" class="tab-content">
            <div class="form-container">
                <h2 style="color: var(--dark); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-building"></i> Add New Shopping Mall
                </h2>
                <form method="POST" id="addMallForm">
                    <input type="hidden" name="action" value="add_mall">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Mall Name *</label>
                            <input type="text" name="mall_name" class="form-control" required 
                                   placeholder="Example: City Center Mall, Mega Mall">
                        </div>
                        <div class="form-group">
                            <label>Location *</label>
                            <input type="text" name="mall_location" class="form-control" required 
                                   placeholder="Full address of the mall">
                        </div>
                        <div class="form-group">
                            <label>Total Floors</label>
                            <input type="number" name="total_floors" class="form-control" value="3" min="1" max="10">
                        </div>
                        <div class="form-group">
                            <label>Total Parking Spots</label>
                            <input type="number" name="total_spots" class="form-control" value="100" min="1" max="10000">
                        </div>
                        <div class="form-group">
                            <label>Contact Email</label>
                            <input type="email" name="contact_email" class="form-control" 
                                   placeholder="mall@example.com">
                        </div>
                        <div class="form-group">
                            <label>Contact Phone</label>
                            <input type="tel" name="contact_phone" class="form-control" 
                                   placeholder="+1234567890">
                        </div>
                        <div class="form-group">
                            <label>Latitude *</label>
                            <input type="number" name="lat" class="form-control" required 
                                   placeholder="30.0444" step="any" value="30.0444">
                        </div>
                        <div class="form-group">
                            <label>Longitude *</label>
                            <input type="number" name="lng" class="form-control" required 
                                   placeholder="31.2357" step="any" value="31.2357">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="4" 
                                      placeholder="Brief description of the mall..."></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Mall
                    </button>
                </form>
            </div>
        </div>

        <!-- Add Zone Tab -->
        <div id="add-zone-tab" class="tab-content">
            <div class="form-container">
                <h2 style="color: var(--dark); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-layer-group"></i> Add New Parking Zone
                </h2>
                <form method="POST" id="addZoneForm">
                    <input type="hidden" name="action" value="add_zone">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Mall *</label>
                            <select name="mall_id" class="form-control" required>
                                <option value="">Select Mall</option>
                                <?php foreach($malls as $mall): ?>
                                <option value="<?php echo $mall['id']; ?>">
                                    <?php echo htmlspecialchars($mall['mall_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Zone Name *</label>
                            <input type="text" name="zone_name" class="form-control" required 
                                   placeholder="Example: Zone A, Main Parking">
                        </div>
                        <div class="form-group">
                            <label>Floor *</label>
                            <select name="floor" class="form-control" required>
                                <option value="1">Floor 1</option>
                                <option value="2">Floor 2</option>
                                <option value="3">Floor 3</option>
                                <option value="4">Floor 4</option>
                                <option value="5">Floor 5</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Total Spots</label>
                            <input type="number" name="total_spots" class="form-control" value="50" min="1" max="1000">
                        </div>
                        <div class="form-group">
                            <label>Available Spots</label>
                            <input type="number" name="available_spots" class="form-control" value="50" min="0" max="1000">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Zone Description</label>
                            <textarea name="description" class="form-control" rows="3" 
                                      placeholder="Brief description of the zone..."></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Zone
                    </button>
                </form>
            </div>
        </div>

        <!-- Add Spot Tab -->
        <div id="add-spot-tab" class="tab-content">
            <div class="form-container">
                <h2 style="color: var(--dark); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-parking"></i> Add New Parking Spot
                </h2>
                <form method="POST" id="addSpotForm">
                    <input type="hidden" name="action" value="add_spot">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Zone *</label>
                            <select name="zone_id" class="form-control" required>
                                <option value="">Select Zone</option>
                                <?php foreach($zones as $zone): ?>
                                <option value="<?php echo $zone['id']; ?>">
                                    <?php echo htmlspecialchars($zone['mall_name']); ?> - <?php echo htmlspecialchars($zone['zone_name']); ?> (Floor <?php echo $zone['floor']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Spot Number *</label>
                            <input type="text" name="spot_number" class="form-control" required 
                                   placeholder="Example: A1, B2, C3">
                        </div>
                        <div class="form-group">
                            <label>Spot Type *</label>
                            <select name="spot_type" class="form-control" required>
                                <option value="regular">Regular</option>
                                <option value="vip">VIP</option>
                                <option value="disabled">Disabled</option>
                                <option value="electric">Electric</option>
                                <option value="family">Family</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price per Hour ($) *</label>
                            <input type="number" name="hourly_rate" class="form-control" value="5" min="1" max="100" step="0.5" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Spot
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Mapbox GL JS -->
    <script src='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js'></script>
    
    <script>
        // بيانات المولات والمواقف من PHP
        const malls = <?php echo json_encode($malls); ?>;
        const parkingSpots = <?php echo json_encode($parking_spots); ?>;
        let map;
        let currentFilter = 'all';

        // Mapbox Access Token (استبدل بالمفتاح الخاص بك)
        mapboxgl.accessToken = 'pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw';

        // Initialize Map
        function initMap() {
            try {
                map = new mapboxgl.Map({
                    container: 'interactiveMap',
                    style: 'mapbox://styles/mapbox/streets-v12',
                    center: [31.2357, 30.0444], // القاهرة
                    zoom: 12,
                    attributionControl: false
                });

                // Add zoom and rotation controls
                map.addControl(new mapboxgl.NavigationControl());

                map.on('load', () => {
                    // إضافة markers للمولات
                    addMallMarkers();
                    
                    // إضافة markers للمواقف
                    addParkingSpotMarkers();
                    
                    // إخفاء رسالة التحميل
                    document.querySelector('#interactiveMap .loading').style.display = 'none';
                });

            } catch (error) {
                console.error('Map initialization error:', error);
                document.querySelector('#interactiveMap .loading').innerHTML = `
                    <div style="text-align: center; color: #666;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <p>Error loading map</p>
                        <small>${error.message}</small>
                    </div>
                `;
            }
        }

        // إضافة markers للمولات
        function addMallMarkers() {
            malls.forEach(mall => {
                if (mall.lat && mall.lng) {
                    const el = document.createElement('div');
                    el.className = 'mall-marker';
                    el.innerHTML = '<i class="fas fa-building" style="color: #2c3e50; font-size: 24px;"></i>';
                    el.style.cursor = 'pointer';

                    const popup = new mapboxgl.Popup({ offset: 25 })
                        .setHTML(`
                            <div class="map-popup">
                                <div class="popup-header">${mall.mall_name}</div>
                                <div class="popup-info">
                                    <strong>Location:</strong> ${mall.location}<br>
                                    <strong>Floors:</strong> ${mall.total_floors}<br>
                                    <strong>Total Spots:</strong> ${mall.total_spots}
                                </div>
                                <div class="popup-stats">
                                    <small>Contact: ${mall.contact_email || 'N/A'}</small>
                                </div>
                                <div class="popup-actions">
                                    <button class="btn btn-primary btn-sm" onclick="viewMallDetails(${mall.id})">
                                        <i class="fas fa-info-circle"></i> Details
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="editMall(${mall.id})">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </div>
                            </div>
                        `);

                    new mapboxgl.Marker(el)
                        .setLngLat([parseFloat(mall.lng), parseFloat(mall.lat)])
                        .setPopup(popup)
                        .addTo(map);
                }
            });
        }

        // إضافة markers للمواقف
        function addParkingSpotMarkers() {
            // تجميع المواقف حسب المول لتجنب التكدس
            const spotsByMall = {};
            
            parkingSpots.forEach(spot => {
                if (!spot.mall_lat || !spot.mall_lng) return;
                
                const mallKey = spot.mall_id;
                if (!spotsByMall[mallKey]) {
                    spotsByMall[mallKey] = [];
                }
                spotsByMall[mallKey].push(spot);
            });

            // إضافة markers للمواقف مع توزيع عشوائي حول المول
            Object.values(spotsByMall).forEach(spots => {
                if (spots.length === 0) return;
                
                const baseLat = parseFloat(spots[0].mall_lat);
                const baseLng = parseFloat(spots[0].mall_lng);
                
                spots.forEach((spot, index) => {
                    // توزيع عشوائي حول المول
                    const offsetLat = (Math.random() - 0.5) * 0.002;
                    const offsetLng = (Math.random() - 0.5) * 0.002;
                    
                    const lat = baseLat + offsetLat;
                    const lng = baseLng + offsetLng;

                    const el = document.createElement('div');
                    el.className = 'spot-marker';
                    el.innerHTML = getSpotIcon(spot.status);
                    el.style.cursor = 'pointer';
                    el.style.width = '20px';
                    el.style.height = '20px';
                    el.style.borderRadius = '50%';
                    el.style.border = '2px solid white';
                    el.style.boxShadow = '0 2px 5px rgba(0,0,0,0.3)';

                    const popup = new mapboxgl.Popup({ offset: 25 })
                        .setHTML(`
                            <div class="map-popup">
                                <div class="popup-header">Spot ${spot.spot_number}</div>
                                <div class="popup-info">
                                    <strong>Mall:</strong> ${spot.mall_name}<br>
                                    <strong>Zone:</strong> ${spot.zone_name}<br>
                                    <strong>Floor:</strong> ${spot.floor}<br>
                                    <strong>Type:</strong> ${spot.spot_type}<br>
                                    <strong>Status:</strong> <span class="status-badge status-${spot.status}">${spot.status}</span>
                                </div>
                                <div class="popup-stats">
                                    <strong>Price:</strong> $${spot.hourly_rate}/hour<br>
                                    <strong>Active Bookings:</strong> ${spot.active_bookings}
                                </div>
                                <div class="popup-actions">
                                    <button class="btn btn-primary btn-sm" onclick="editSpot(${spot.id})">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    ${spot.status === 'available' ? 
                                        `<button class="btn btn-success btn-sm" onclick="simulateBooking(${spot.id})">
                                            <i class="fas fa-calendar-plus"></i> Simulate Booking
                                        </button>` : 
                                        ''
                                    }
                                </div>
                            </div>
                        `);

                    new mapboxgl.Marker(el)
                        .setLngLat([lng, lat])
                        .setPopup(popup)
                        .addTo(map);
                });
            });
        }

        // الحصول على الأيقونة المناسبة حسب حالة الموقف
        function getSpotIcon(status) {
            const colors = {
                'available': '#27ae60',
                'occupied': '#e74c3c', 
                'maintenance': '#f39c12',
                'reserved': '#3498db'
            };
            
            const color = colors[status] || '#95a5a6';
            return `<div style="background: ${color}; width: 100%; height: 100%; border-radius: 50%;"></div>`;
        }

        // تصفية الخريطة
        function showAllMalls() {
            currentFilter = 'all';
            updateMapControls('all');
            // إعادة تحميل الخريطة لعرض كل البيانات
            location.reload();
        }

        function showAvailableSpots() {
            currentFilter = 'available';
            updateMapControls('available');
            // يمكن إضافة منطق التصفية هنا
            alert('Filtering available spots...');
        }

        function showOccupiedSpots() {
            currentFilter = 'occupied';
            updateMapControls('occupied');
            // يمكن إضافة منطق التصفية هنا
            alert('Filtering occupied spots...');
        }

        function updateMapControls(activeFilter) {
            document.querySelectorAll('.control-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        // التركيز على مول معين في الخريطة
        function focusOnMall(mallId, lat, lng) {
            if (map) {
                map.flyTo({
                    center: [parseFloat(lng), parseFloat(lat)],
                    zoom: 16,
                    essential: true
                });
                
                // فتح popup المول
                setTimeout(() => {
                    const mallMarker = document.querySelector(`[data-mall-id="${mallId}"]`);
                    if (mallMarker) {
                        mallMarker.click();
                    }
                }, 1000);
            }
        }

        // محاكاة الحجز (للتجربة)
        function simulateBooking(spotId) {
            if (confirm('Simulate booking for this spot?')) {
                alert(`Booking simulated for spot ${spotId}`);
                // هنا يمكن إضافة منطق محاكاة الحجز
            }
        }

        // عرض تفاصيل المول
        function viewMallDetails(mallId) {
            openTab('malls-tab');
            // يمكن إضافة منطق لإظهار تفاصيل المول
            alert(`Viewing details for mall ${mallId}`);
        }

        // Tab Management
        function openTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // باقي الدوال تبقى كما هي
        function editMall(mallId) {
            document.getElementById('edit_mall_id').value = mallId;
            document.getElementById('editMallModal').style.display = 'flex';
        }

        function closeEditMallModal() {
            document.getElementById('editMallModal').style.display = 'none';
        }

        function editZone(zoneId) {
            document.getElementById('edit_zone_id').value = zoneId;
            document.getElementById('editZoneModal').style.display = 'flex';
        }

        function closeEditZoneModal() {
            document.getElementById('editZoneModal').style.display = 'none';
        }

        function editSpot(spotId) {
            document.getElementById('edit_spot_id').value = spotId;
            document.getElementById('editSpotModal').style.display = 'flex';
        }

        function closeEditSpotModal() {
            document.getElementById('editSpotModal').style.display = 'none';
        }

        function deleteMall(mallId) {
            if(confirm('⚠️ Are you sure you want to delete this mall? This will also delete all associated zones and spots.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_mall">
                    <input type="hidden" name="mall_id" value="${mallId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteZone(zoneId) {
            if(confirm('⚠️ Are you sure you want to delete this zone? This will also delete all associated spots.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_zone">
                    <input type="hidden" name="zone_id" value="${zoneId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteSpot(spotId) {
            if(confirm('⚠️ Are you sure you want to delete this spot?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_spot">
                    <input type="hidden" name="spot_id" value="${spotId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // تهيئة الخريطة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
        });

        // تحديث تلقائي كل 30 ثانية
        setInterval(() => {
            // يمكن إضافة تحديث للبيانات هنا
        }, 30000);
    </script>

    <!-- Modals تبقى كما هي -->
    <!-- Edit Mall Modal -->
    <div id="editMallModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <h3 style="margin-bottom: 20px;">🏢 Edit Mall</h3>
            <form method="POST" id="editMallForm">
                <input type="hidden" name="action" value="update_mall">
                <input type="hidden" name="mall_id" id="edit_mall_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Mall Name</label>
                        <input type="text" name="mall_name" class="form-control" id="edit_mall_name" required>
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="mall_location" class="form-control" id="edit_mall_location" required>
                    </div>
                    <div class="form-group">
                        <label>Total Floors</label>
                        <input type="number" name="total_floors" class="form-control" id="edit_total_floors" min="1" max="10">
                    </div>
                    <div class="form-group">
                        <label>Total Spots</label>
                        <input type="number" name="total_spots" class="form-control" id="edit_total_spots" min="1" max="10000">
                    </div>
                    <div class="form-group">
                        <label>Contact Email</label>
                        <input type="email" name="contact_email" class="form-control" id="edit_contact_email">
                    </div>
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="tel" name="contact_phone" class="form-control" id="edit_contact_phone">
                    </div>
                    <div class="form-group">
                        <label>Latitude</label>
                        <input type="text" name="lat" class="form-control" id="edit_lat" step="any">
                    </div>
                    <div class="form-group">
                        <label>Longitude</label>
                        <input type="text" name="lng" class="form-control" id="edit_lng" step="any">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Description</label>
                        <textarea name="description" class="form-control" id="edit_mall_description" rows="4"></textarea>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditMallModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Zone Modal -->
    <div id="editZoneModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%;">
            <h3 style="margin-bottom: 20px;">🏗️ Edit Zone</h3>
            <form method="POST" id="editZoneForm">
                <input type="hidden" name="action" value="update_zone">
                <input type="hidden" name="zone_id" id="edit_zone_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Zone Name</label>
                        <input type="text" name="zone_name" class="form-control" id="edit_zone_name" required>
                    </div>
                    <div class="form-group">
                        <label>Floor</label>
                        <select name="floor" class="form-control" id="edit_zone_floor" required>
                            <option value="1">Floor 1</option>
                            <option value="2">Floor 2</option>
                            <option value="3">Floor 3</option>
                            <option value="4">Floor 4</option>
                            <option value="5">Floor 5</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Total Spots</label>
                        <input type="number" name="total_spots" class="form-control" id="edit_zone_total_spots" min="1" max="1000">
                    </div>
                    <div class="form-group">
                        <label>Available Spots</label>
                        <input type="number" name="available_spots" class="form-control" id="edit_zone_available_spots" min="0" max="1000">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Description</label>
                        <textarea name="description" class="form-control" id="edit_zone_description" rows="3"></textarea>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditZoneModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Spot Modal -->
    <div id="editSpotModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%;">
            <h3 style="margin-bottom: 20px;">✏️ Edit Parking Spot</h3>
            <form method="POST" id="editSpotForm">
                <input type="hidden" name="action" value="update_spot">
                <input type="hidden" name="spot_id" id="edit_spot_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Spot Type</label>
                        <select name="spot_type" class="form-control" id="edit_spot_type" required>
                            <option value="regular">Regular</option>
                            <option value="vip">VIP</option>
                            <option value="disabled">Disabled</option>
                            <option value="electric">Electric</option>
                            <option value="family">Family</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Spot Status</label>
                        <select name="status" class="form-control" id="edit_status" required>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Under Maintenance</option>
                            <option value="reserved">Reserved</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Price per Hour ($)</label>
                        <input type="number" name="hourly_rate" class="form-control" 
                               id="edit_hourly_rate" min="1" max="100" step="0.5" required>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditSpotModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>