<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// التحقق من وجود بيانات الحجز
if(!isset($_SESSION['pending_booking'])) {
    header("Location: booking_user.php");
    exit();
}

$booking_data = $_SESSION['pending_booking'];
$error_message = '';

// معالجة الدفع (فقط إذا تم إرسال النموذج)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'process_payment') {
    $card_number = str_replace(' ', '', $_POST['card_number']);
    $card_holder = $_POST['card_holder'];
    $expiry_month = $_POST['expiry_month'];
    $expiry_year = $_POST['expiry_year'];
    $cvv = $_POST['cvv'];
    
    try {
        // التحقق من صحة بيانات البطاقة الأساسية
        if(empty($card_holder) || empty($card_number) || empty($expiry_month) || empty($expiry_year) || empty($cvv)) {
            $error_message = "Please fill in all payment details.";
        } elseif(strlen($card_number) < 16) {
            $error_message = "Please enter a valid card number.";
        } elseif(strlen($cvv) < 3) {
            $error_message = "Please enter a valid CVV.";
        } else {
            // محاكاة عملية الدفع (في نظام حقيقي، هنا يتم الاتصال ببوابة الدفع)
            $payment_success = true; // محاكاة نجاح الدفع
            
            if($payment_success) {
                // إنشاء الحجز في قاعدة البيانات
                $start_time = date('Y-m-d H:i:s');
                $end_time = date('Y-m-d H:i:s', strtotime("+{$booking_data['duration']} hours"));
                
                $booking_stmt = $pdo->prepare("
                    INSERT INTO bookings (user_id, spot_id, vehicle_type, vehicle_plate, start_time, end_time, amount, status, payment_status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 'paid')
                ");
                $booking_stmt->execute([
                    $user_id, 
                    $booking_data['spot_id'], 
                    $booking_data['vehicle_type'], 
                    $booking_data['license_plate'], 
                    $start_time, 
                    $end_time, 
                    $booking_data['amount']
                ]);
                
                $booking_id = $pdo->lastInsertId();
                
                // تحديث حالة الموقف
                $update_spot_stmt = $pdo->prepare("UPDATE parking_spots SET status = 'occupied' WHERE id = ?");
                $update_spot_stmt->execute([$booking_data['spot_id']]);
                
                // تسجيل المعاملة
                $transaction_stmt = $pdo->prepare("
                    INSERT INTO transactions (user_id, booking_id, amount, transaction_type, payment_method, status, transaction_reference)
                    VALUES (?, ?, ?, 'payment', 'credit_card', 'completed', ?)
                ");
                $transaction_reference = 'TXN' . time() . rand(1000, 9999);
                $transaction_stmt->execute([
                    $user_id,
                    $booking_id,
                    $booking_data['amount'],
                    $transaction_reference
                ]);
                
                // إضافة إشعار
                $notification_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type) 
                    VALUES (?, 'Booking Confirmed', ?, 'success')
                ");
                $notification_stmt->execute([
                    $user_id, 
                    "Your booking for spot {$booking_data['spot_number']} has been confirmed. Amount: \${$booking_data['amount']}"
                ]);
                
                // مسح بيانات الحجز المؤقتة
                unset($_SESSION['pending_booking']);
                
                // إعادة التوجيه إلى صفحة التأكيد
                $_SESSION['booking_success'] = true;
                $_SESSION['last_booking_id'] = $booking_id;
                header("Location: booking_success.php");
                exit();
            } else {
                $error_message = "Payment failed. Please check your card details and try again.";
            }
        }
        
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - SmartPark</title>
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
            --success: #10b981;
            --danger: #ef4444;
            --dark: #1f2937;
            --light: #f8fafc;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .payment-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 800px;
            overflow: hidden;
        }

        .payment-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 30px;
            text-align: center;
        }

        .payment-header h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .payment-header p {
            opacity: 0.9;
            font-size: 1.1em;
        }

        .payment-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        @media (max-width: 768px) {
            .payment-content {
                grid-template-columns: 1fr;
            }
        }

        .booking-summary {
            background: #f8f9fa;
            padding: 30px;
            border-right: 1px solid #e9ecef;
        }

        .summary-title {
            color: var(--dark);
            font-size: 1.5em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-label {
            color: #6b7280;
            font-weight: 500;
        }

        .summary-value {
            color: var(--dark);
            font-weight: 600;
        }

        .total-amount {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .total-label {
            color: #6b7280;
            font-size: 1.1em;
            margin-bottom: 10px;
        }

        .total-value {
            color: var(--primary);
            font-size: 2.5em;
            font-weight: 800;
        }

        .payment-form {
            padding: 30px;
        }

        .form-title {
            color: var(--dark);
            font-size: 1.5em;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .payment-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .error-message {
            background: #fee2e2;
            color: var(--danger);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--danger);
        }

        .card-icons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .card-icon {
            width: 40px;
            height: 25px;
            background: #e5e7eb;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            color: #6b7280;
        }

        .success-message {
            background: #d1fae5;
            color: var(--success);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--success);
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h1><i class="fas fa-credit-card"></i> Secure Payment</h1>
            <p>Complete your parking booking with secure payment</p>
        </div>
        
        <div class="payment-content">
            <div class="booking-summary">
                <h2 class="summary-title"><i class="fas fa-receipt"></i> Booking Summary</h2>
                
                <div class="summary-item">
                    <span class="summary-label">Parking Spot:</span>
                    <span class="summary-value"><?php echo htmlspecialchars($booking_data['spot_number']); ?></span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">Vehicle Type:</span>
                    <span class="summary-value"><?php echo ucfirst(htmlspecialchars($booking_data['vehicle_type'])); ?></span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">License Plate:</span>
                    <span class="summary-value"><?php echo htmlspecialchars($booking_data['license_plate']); ?></span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">Duration:</span>
                    <span class="summary-value"><?php echo htmlspecialchars($booking_data['duration']); ?> hours</span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">Hourly Rate:</span>
                    <span class="summary-value">$<?php echo htmlspecialchars($booking_data['hourly_rate']); ?>/hour</span>
                </div>
                
                <div class="total-amount">
                    <div class="total-label">Total Amount</div>
                    <div class="total-value">$<?php echo htmlspecialchars($booking_data['amount']); ?></div>
                </div>
            </div>
            
            <div class="payment-form">
                <h2 class="form-title"><i class="fas fa-lock"></i> Payment Details</h2>
                
                <?php if(isset($_GET['success']) && $_GET['success'] == 'true'): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> Payment processed successfully! Redirecting...
                    </div>
                    <script>
                        setTimeout(() => {
                            window.location.href = 'booking_success.php';
                        }, 2000);
                    </script>
                <?php endif; ?>
                
                <?php if(!empty($error_message)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="card_holder">Card Holder Name</label>
                        <input type="text" id="card_holder" name="card_holder" class="form-control" placeholder="John Doe" required 
                               value="<?php echo isset($_POST['card_holder']) ? htmlspecialchars($_POST['card_holder']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="card_number">Card Number</label>
                        <input type="text" id="card_number" name="card_number" class="form-control" placeholder="1234 5678 9012 3456" maxlength="19" required
                               value="<?php echo isset($_POST['card_number']) ? htmlspecialchars($_POST['card_number']) : ''; ?>">
                        <div class="card-icons">
                            <div class="card-icon">VISA</div>
                            <div class="card-icon">MC</div>
                            <div class="card-icon">AMEX</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="expiry_month">Expiry Month</label>
                            <select id="expiry_month" name="expiry_month" class="form-control" required>
                                <option value="">Month</option>
                                <?php for($i = 1; $i <= 12; $i++): 
                                    $selected = (isset($_POST['expiry_month']) && $_POST['expiry_month'] == sprintf('%02d', $i)) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo sprintf('%02d', $i); ?>" <?php echo $selected; ?>>
                                        <?php echo sprintf('%02d', $i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="expiry_year">Expiry Year</label>
                            <select id="expiry_year" name="expiry_year" class="form-control" required>
                                <option value="">Year</option>
                                <?php for($i = date('Y'); $i <= date('Y') + 10; $i++): 
                                    $selected = (isset($_POST['expiry_year']) && $_POST['expiry_year'] == $i) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $i; ?>" <?php echo $selected; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cvv">CVV</label>
                        <input type="text" id="cvv" name="cvv" class="form-control" placeholder="123" maxlength="4" required
                               value="<?php echo isset($_POST['cvv']) ? htmlspecialchars($_POST['cvv']) : ''; ?>">
                    </div>
                    
                    <div class="payment-actions">
                        <a href="booking_user.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Booking
                        </a>
                        <button type="submit" name="action" value="process_payment" class="btn btn-primary">
                            <i class="fas fa-lock"></i> Pay $<?php echo htmlspecialchars($booking_data['amount']); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // تنسيق رقم البطاقة تلقائيًا
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ');
            if (formattedValue) {
                e.target.value = formattedValue;
            }
        });

        // التحقق من صحة تاريخ انتهاء الصلاحية
        document.querySelector('form').addEventListener('submit', function(e) {
            const expiryMonth = document.getElementById('expiry_month').value;
            const expiryYear = document.getElementById('expiry_year').value;
            const currentYear = new Date().getFullYear();
            const currentMonth = new Date().getMonth() + 1;
            
            if (expiryYear && expiryMonth) {
                if (expiryYear < currentYear || (expiryYear == currentYear && expiryMonth < currentMonth)) {
                    e.preventDefault();
                    alert('Card has expired. Please check the expiry date.');
                    return false;
                }
            }
            
            // التحقق من صحة CVV
            const cvv = document.getElementById('cvv').value;
            if (cvv.length < 3 || !/^\d+$/.test(cvv)) {
                e.preventDefault();
                alert('Please enter a valid CVV (3 or 4 digits).');
                return false;
            }
            
            // التحقق من صحة رقم البطاقة
            const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
            if (cardNumber.length < 16 || !/^\d+$/.test(cardNumber)) {
                e.preventDefault();
                alert('Please enter a valid 16-digit card number.');
                return false;
            }
        });

        // منع إدخال أحرف في CVV ورقم البطاقة
        document.getElementById('cvv').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^\d]/g, '');
        });

        document.getElementById('card_number').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^\d\s]/g, '');
        });
    </script>
</body>
</html>