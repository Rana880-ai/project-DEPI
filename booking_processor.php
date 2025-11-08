<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if($_POST['action'] == 'book_spot') {
    $spot_id = $_POST['spot_id'];
    $vehicle_type = $_POST['vehicle_type'];
    $license_plate = strtoupper($_POST['license_plate']);
    $duration = $_POST['duration'];
    $amount = $_POST['amount'];
    $hourly_rate = $_POST['hourly_rate'];
    
    try {
        // التحقق من أن الموقف متاح
        $spot_stmt = $pdo->prepare("SELECT * FROM parking_spots WHERE id = ? AND status = 'available'");
        $spot_stmt->execute([$spot_id]);
        $spot = $spot_stmt->fetch();
        
        if(!$spot) {
            echo json_encode(['success' => false, 'message' => 'Spot is no longer available']);
            exit;
        }
        
        // حفظ بيانات الحجز مؤقتًا في الجلسة للانتقال إلى صفحة الدفع
        $_SESSION['pending_booking'] = [
            'spot_id' => $spot_id,
            'spot_number' => $spot['spot_number'],
            'vehicle_type' => $vehicle_type,
            'license_plate' => $license_plate,
            'duration' => $duration,
            'amount' => $amount,
            'hourly_rate' => $hourly_rate
        ];
        
        echo json_encode([
            'success' => true,
            'redirect' => 'payment.php'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// إلغاء الحجز
if($_POST['action'] == 'cancel_booking') {
    $booking_id = $_POST['booking_id'];
    
    try {
        // جلب بيانات الحجز
        $booking_stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
        $booking_stmt->execute([$booking_id, $user_id]);
        $booking = $booking_stmt->fetch();
        
        if(!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
            exit;
        }
        
        // تحديث حالة الحجز
        $update_booking_stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
        $update_booking_stmt->execute([$booking_id]);
        
        // تحديث حالة الموقف
        $update_spot_stmt = $pdo->prepare("UPDATE parking_spots SET status = 'available' WHERE id = ?");
        $update_spot_stmt->execute([$booking['spot_id']]);
        
        // إضافة إشعار
        $notification_stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type) 
            VALUES (?, 'Booking Cancelled', ?, 'info')
        ");
        $notification_stmt->execute([
            $user_id, 
            "Your booking for spot has been cancelled."
        ]);
        
        echo json_encode(['success' => true, 'spot_id' => $booking['spot_id']]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>