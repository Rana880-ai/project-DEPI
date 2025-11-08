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

// Process form requests
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if(isset($_POST['action'])) {
            switch($_POST['action']) {
                case 'add_zone':
                    if(!empty($_POST['zone_name'])) {
                        $stmt = $pdo->prepare("INSERT INTO parking_zones (zone_name, floor, description) VALUES (?, ?, ?)");
                        $stmt->execute([$_POST['zone_name'], $_POST['floor'], $_POST['description']]);
                        $message = "✅ Zone added successfully";
                    }
                    break;
                    
                case 'add_spot':
                    if(!empty($_POST['spot_number'])) {
                        // Check for duplicate spot number
                        $check_stmt = $pdo->prepare("SELECT id FROM parking_spots WHERE spot_number = ?");
                        $check_stmt->execute([$_POST['spot_number']]);
                        
                        if($check_stmt->fetch()) {
                            $error = "❌ Spot number already exists";
                        } else {
                            // Get zone_id from selected zone
                            $zone_stmt = $pdo->prepare("SELECT id FROM parking_zones WHERE zone_name = ? AND floor = ?");
                            $zone_stmt->execute([$_POST['zone'], $_POST['floor']]);
                            $zone = $zone_stmt->fetch();
                            
                            if($zone) {
                                $zone_id = $zone['id'];
                                $stmt = $pdo->prepare("INSERT INTO parking_spots (zone_id, spot_number, spot_type, status, hourly_rate) VALUES (?, ?, ?, 'available', ?)");
                                $stmt->execute([
                                    $zone_id,
                                    $_POST['spot_number'],
                                    $_POST['spot_type'],
                                    $_POST['hourly_rate']
                                ]);
                                $message = "✅ Parking spot added successfully";
                            } else {
                                $error = "❌ Zone not found";
                            }
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
                    
                case 'update_settings':
                    foreach($_POST['settings'] as $key => $value) {
                        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                                              ON DUPLICATE KEY UPDATE setting_value = ?");
                        $stmt->execute([$key, $value, $value]);
                    }
                    $message = "✅ Settings updated successfully";
                    break;
                    
                case 'bulk_update':
                    if(isset($_POST['spot_ids']) && is_array($_POST['spot_ids'])) {
                        foreach($_POST['spot_ids'] as $spot_id) {
                            $stmt = $pdo->prepare("UPDATE parking_spots SET status = ? WHERE id = ?");
                            $stmt->execute([$_POST['bulk_status'], $spot_id]);
                        }
                        $message = "✅ Updated " . count($_POST['spot_ids']) . " spots";
                    }
                    break;
            }
        }
    } catch (PDOException $e) {
        $error = "❌ Database error: " . $e->getMessage();
    }
}

// Fetch data
try {
    // Fetch spots with zone information
    $spots_stmt = $pdo->query("
        SELECT 
            ps.*, 
            pz.zone_name,
            pz.floor,
            COUNT(b.id) as active_bookings
        FROM parking_spots ps 
        LEFT JOIN parking_zones pz ON ps.zone_id = pz.id
        LEFT JOIN bookings b ON ps.id = b.spot_id AND b.status = 'active'
        GROUP BY ps.id
        ORDER BY pz.floor, pz.zone_name, ps.spot_number
    ");
    $parking_spots = $spots_stmt->fetchAll();
    
    // Fetch available zones
    $zones_stmt = $pdo->query("SELECT * FROM parking_zones ORDER BY floor, zone_name");
    $zones = $zones_stmt->fetchAll();
    
    // Fetch statistics
    $total_spots_stmt = $pdo->query("SELECT COUNT(*) as total FROM parking_spots");
    $total_spots = $total_spots_stmt->fetch()['total'];
    
    $available_spots_stmt = $pdo->query("SELECT COUNT(*) as available FROM parking_spots WHERE status = 'available'");
    $available_spots = $available_spots_stmt->fetch()['available'];
    
    $occupied_spots_stmt = $pdo->query("SELECT COUNT(*) as occupied FROM parking_spots WHERE status = 'occupied'");
    $occupied_spots = $occupied_spots_stmt->fetch()['occupied'];
    
    $maintenance_spots_stmt = $pdo->query("SELECT COUNT(*) as maintenance FROM parking_spots WHERE status = 'maintenance'");
    $maintenance_spots = $maintenance_spots_stmt->fetch()['maintenance'];
    
    // Fetch settings
    $settings_stmt = $pdo->query("SELECT * FROM system_settings");
    $settings_data = $settings_stmt->fetchAll();
    $settings = [];
    foreach($settings_data as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Fetch active bookings
    $active_bookings_stmt = $pdo->query("
        SELECT COUNT(*) as active 
        FROM bookings 
        WHERE status = 'active'
    ");
    $active_bookings = $active_bookings_stmt->fetch()['active'];
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Management - SmartPark</title>
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

        .bulk-actions {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            gap: 10px;
            align-items: center;
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
            <li><a href="booking_manager.php" class="active"><i class="fas fa-calendar-check"></i> <span class="nav-text">Parking Management</span></a></li>
            <li><a href="users_manager.php"><i class="fas fa-users"></i> <span class="nav-text">Users</span></a></li>
            <li><a href="payments_manager.php"><i class="fas fa-credit-card"></i> <span class="nav-text">Payments</span></a></li>
            <li><a href="reports_manager.php"><i class="fas fa-chart-bar"></i> <span class="nav-text">Reports</span></a></li>
            <li><a href="notifications_manager.php"><i class="fas fa-bell"></i> <span class="nav-text">Notifications</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Parking Management System</h1>
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

        <!-- Stats -->
        <div class="stats-grid">
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
            <div class="stat-card">
                <h3>Occupied</h3>
                <div class="stat-value"><?php echo $occupied_spots; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-ban"></i> Currently in use
                </div>
            </div>
            <div class="stat-card">
                <h3>Under Maintenance</h3>
                <div class="stat-value"><?php echo $maintenance_spots; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-tools"></i> Being serviced
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="openTab('spots-tab')">View & Manage</button>
            <button class="tab-btn" onclick="openTab('add-zone-tab')">Add Zone</button>
            <button class="tab-btn" onclick="openTab('add-spot-tab')">Add Spot</button>
            <button class="tab-btn" onclick="openTab('settings-tab')">Settings</button>
            <button class="tab-btn" onclick="openTab('bulk-tab')">Bulk Update</button>
        </div>

        <!-- Spots Management Tab -->
        <div id="spots-tab" class="tab-content active">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Spot Number</th>
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

        <!-- Add Zone Tab -->
        <div id="add-zone-tab" class="tab-content">
            <div class="form-container">
                <h2 style="color: var(--dark); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-map-marker-alt"></i> Add New Zone
                </h2>
                <form method="POST" id="addZoneForm">
                    <input type="hidden" name="action" value="add_zone">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Zone Name *</label>
                            <input type="text" name="zone_name" class="form-control" required 
                                   placeholder="Example: Zone A, Main Area">
                        </div>
                        <div class="form-group">
                            <label>Floor *</label>
                            <select name="floor" class="form-control" required>
                                <option value="1">Floor 1</option>
                                <option value="2">Floor 2</option>
                                <option value="3">Floor 3</option>
                                <option value="4">Floor 4</option>
                            </select>
                        </div>
                        <div class="form-group">
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
                    <i class="fas fa-plus-circle"></i> Add New Parking Spot
                </h2>
                <form method="POST" id="addSpotForm">
                    <input type="hidden" name="action" value="add_spot">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Spot Number *</label>
                            <input type="text" name="spot_number" class="form-control" required 
                                   placeholder="Example: A1, B2, C3">
                        </div>
                        <div class="form-group">
                            <label>Zone *</label>
                            <select name="zone" class="form-control" required>
                                <?php foreach($zones as $zone): ?>
                                <option value="<?php echo $zone['zone_name']; ?>">
                                    <?php echo $zone['zone_name']; ?> (Floor <?php echo $zone['floor']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Floor *</label>
                            <select name="floor" class="form-control" required>
                                <option value="1">Floor 1</option>
                                <option value="2">Floor 2</option>
                                <option value="3">Floor 3</option>
                                <option value="4">Floor 4</option>
                            </select>
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
                            <input type="number" name="hourly_rate" class="form-control" 
                                   value="<?php echo $settings['parking_hourly_rate'] ?? '5'; ?>" min="1" max="100" step="0.5" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add Spot
                    </button>
                </form>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings-tab" class="tab-content">
            <div class="form-container">
                <h2 style="color: var(--dark); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-cog"></i> System Settings
                </h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Base Hourly Rate ($)</label>
                            <input type="number" name="settings[parking_hourly_rate]" 
                                   class="form-control" value="<?php echo $settings['parking_hourly_rate'] ?? '5'; ?>"
                                   min="1" max="100" step="0.5">
                        </div>
                        <div class="form-group">
                            <label>Maximum Booking Duration (hours)</label>
                            <input type="number" name="settings[parking_max_hours]" 
                                   class="form-control" value="<?php echo $settings['parking_max_hours'] ?? '24'; ?>"
                                   min="1" max="168">
                        </div>
                        <div class="form-group">
                            <label>Business Hours Start</label>
                            <input type="time" name="settings[business_hours_start]" 
                                   class="form-control" value="<?php echo $settings['business_hours_start'] ?? '08:00'; ?>">
                        </div>
                        <div class="form-group">
                            <label>Business Hours End</label>
                            <input type="time" name="settings[business_hours_end]" 
                                   class="form-control" value="<?php echo $settings['business_hours_end'] ?? '22:00'; ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- Bulk Actions Tab -->
        <div id="bulk-tab" class="tab-content">
            <div class="form-container">
                <h2 style="color: var(--dark); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-sync-alt"></i> Bulk Update
                </h2>
                <form method="POST" id="bulkForm">
                    <input type="hidden" name="action" value="bulk_update">
                    <div class="form-group">
                        <label>Change Spot Status to:</label>
                        <select name="bulk_status" class="form-control" required>
                            <option value="available">Available</option>
                            <option value="maintenance">Under Maintenance</option>
                            <option value="occupied">Occupied</option>
                        </select>
                    </div>
                    
                    <div class="table-container">
                        <div class="bulk-actions">
                            <button type="button" class="btn btn-warning btn-sm" onclick="selectAllSpots()">
                                <i class="fas fa-check-square"></i> Select All
                            </button>
                            <button type="button" class="btn btn-warning btn-sm" onclick="deselectAllSpots()">
                                <i class="fas fa-times-circle"></i> Deselect All
                            </button>
                            <span id="selectedCount">0 spots selected</span>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th width="50px">Select</th>
                                    <th>Spot Number</th>
                                    <th>Zone</th>
                                    <th>Floor</th>
                                    <th>Type</th>
                                    <th>Current Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($parking_spots as $spot): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="spot_ids[]" value="<?php echo $spot['id']; ?>" 
                                               class="spot-checkbox" onchange="updateSelectedCount()">
                                    </td>
                                    <td><?php echo $spot['spot_number']; ?></td>
                                    <td><?php echo $spot['zone_name']; ?></td>
                                    <td>Floor <?php echo $spot['floor']; ?></td>
                                    <td>
                                        <span class="type-badge type-<?php echo $spot['spot_type']; ?>">
                                            <?php echo $spot['spot_type']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $spot['status']; ?>">
                                            <?php echo $spot['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="bulkSubmit" disabled>
                        <i class="fas fa-sync-alt"></i> Apply Changes to Selected Spots
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Spot Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%;">
            <h3 style="margin-bottom: 20px;">✏️ Edit Parking Spot</h3>
            <form method="POST" id="editForm">
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
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab Management
        function openTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Deactivate all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show the selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Activate the clicked tab button
            event.currentTarget.classList.add('active');
        }

        // Edit Spot
        function editSpot(spotId) {
            // Here you can fetch spot data via AJAX
            // For simplicity, we'll just show the form
            document.getElementById('edit_spot_id').value = spotId;
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Delete Spot
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

        // Bulk Actions
        function selectAllSpots() {
            document.querySelectorAll('.spot-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectedCount();
        }

        function deselectAllSpots() {
            document.querySelectorAll('.spot-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const selected = document.querySelectorAll('.spot-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = selected + ' spots selected';
            document.getElementById('bulkSubmit').disabled = selected === 0;
        }

        // Form Validation
        document.getElementById('addSpotForm').addEventListener('submit', function(e) {
            const spotNumber = this.spot_number.value.trim();
            if(!spotNumber) {
                e.preventDefault();
                alert('⚠️ Please enter spot number');
                return;
            }
        });

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if(e.target === this) {
                closeEditModal();
            }
        });

        // Auto-refresh every 30 seconds
        setInterval(() => {
            // Can add automatic data refresh here
        }, 30000);
    </script>
</body>
</html>