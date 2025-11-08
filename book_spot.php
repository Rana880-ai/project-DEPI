<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'user') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $spot_id = $_POST['spot_id'];
    
    try {
        $pdo->beginTransaction();
        
        // التحقق من أن الموقف متاح
        $spot_stmt = $pdo->prepare("SELECT * FROM parking_spots WHERE id = ? AND status = 'available'");
        $spot_stmt->execute([$spot_id]);
        $spot = $spot_stmt->fetch();
        
        if (!$spot) {
            throw new Exception('Spot is not available');
        }
        
        // إنشاء الحجز
        $booking_stmt = $pdo->prepare("
            INSERT INTO bookings (user_id, spot_id, vehicle_plate, vehicle_type, start_time, end_time, amount, status) 
            VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR), ?, 'active')
        ");
        
        // افتراض بيانات المركبة (في التطبيق الحقيقي تأتي من نموذج المستخدم)
        $vehicle_plate = 'ABC123';
        $vehicle_type = 'car';
        $amount = $spot['hourly_rate'];
        
        $booking_stmt->execute([$user_id, $spot_id, $vehicle_plate, $vehicle_type, $amount]);
        $booking_id = $pdo->lastInsertId();
        
        // تحديث حالة الموقف
        $update_spot_stmt = $pdo->prepare("UPDATE parking_spots SET status = 'occupied' WHERE id = ?");
        $update_spot_stmt->execute([$spot_id]);
        
        // إرسال إشعار للمستخدم
        $notification_stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type) 
            VALUES (?, 'Booking Confirmed', 'Your parking spot has been booked successfully!', 'success')
        ");
        $notification_stmt->execute([$user_id]);
        
        $pdo->commit();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Spot booked successfully', 'booking_id' => $booking_id]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>