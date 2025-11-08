<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'user') {
    header("Location: login.php");
    exit();
}

if(!isset($_SESSION['booking_success'])) {
    header("Location: booking_user.php");
    exit();
}

// مسح علامة النجاح بعد العرض
unset($_SESSION['booking_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Success - SmartPark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Segoe UI', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            padding: 50px;
            text-align: center;
            max-width: 600px;
            width: 100%;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 3em;
            color: white;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .success-title {
            color: #1f2937;
            font-size: 2.5em;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .success-message {
            color: #6b7280;
            font-size: 1.2em;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .booking-details {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: left;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            color: #6b7280;
            font-weight: 500;
        }

        .detail-value {
            color: #1f2937;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1 class="success-title">Booking Confirmed! 🎉</h1>
        
        <p class="success-message">
            Your parking spot has been successfully booked. You will receive a confirmation notification shortly.
        </p>
        
        <div class="booking-details">
            <div class="detail-item">
                <span class="detail-label">Booking Reference:</span>
                <span class="detail-value">#<?php echo time(); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Status:</span>
                <span class="detail-value" style="color: #10b981;">Active</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Payment Status:</span>
                <span class="detail-value" style="color: #10b981;">Paid</span>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="booking_user.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Booking
            </a>
            <a href="dashboard-user.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Go to Dashboard
            </a>
        </div>
    </div>
</body>
</html>