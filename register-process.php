<?php
session_start();
require_once 'config.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // تنظيف البيانات المدخلة
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = $_POST['user_type'];
    
    // مصفوفة لتخزين الأخطاء
    $errors = [];
    
    // التحقق من البيانات
    if(empty($username)) {
        $errors[] = "Username is required";
    } elseif(strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long";
    } elseif(!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers and underscores";
    }
    
    if(empty($email)) {
        $errors[] = "Email is required";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if(empty($password)) {
        $errors[] = "Password is required";
    } elseif(strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if(empty($confirm_password)) {
        $errors[] = "Please confirm your password";
    } elseif($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if(empty($user_type) || !in_array($user_type, ['user', 'manager'])) {
        $errors[] = "Please select a valid user type";
    }
    
    // إذا كانت هناك أخطاء، إعادة التوجيه مع رسائل الخطأ
    if(!empty($errors)) {
        $error_message = implode("\\n", $errors);
        header("Location: signup.php?error=" . urlencode($error_message));
        exit();
    }
    
    try {
        // التحقق من عدم وجود اسم المستخدم أو البريد الإلكتروني مسبقاً
        $sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $email]);
        
        if($stmt->rowCount() > 0) {
            // التحقق من أي منهما موجود
            $sql = "SELECT username, email FROM users WHERE username = ? OR email = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $email]);
            $existing_user = $stmt->fetch();
            
            if($existing_user['username'] === $username) {
                $errors[] = "Username already exists";
            }
            if($existing_user['email'] === $email) {
                $errors[] = "Email already exists";
            }
            
            $error_message = implode("\\n", $errors);
            header("Location: signup.php?error=" . urlencode($error_message));
            exit();
        }
        
        // إنشاء الحساب الجديد
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if($stmt->execute([$username, $email, $hashed_password, $user_type])) {
            // الحصول على بيانات المستخدم الجديد
            $sql = "SELECT * FROM users WHERE username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if($user) {
                // تسجيل الدخول تلقائياً
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['created_at'] = $user['created_at'];
                
                // رسالة نجاح
                $_SESSION['success'] = "Account created successfully! Welcome to SmartPark!";
                
                // التوجيه للصفحة المناسبة
                if($user_type == 'user') {
                    header("Location: dashboard-user.php");
                } else {
                    header("Location: dashboard-manager.php");
                }
                exit();
            } else {
                throw new Exception("Failed to retrieve user data after registration");
            }
        } else {
            throw new Exception("Failed to create account");
        }
        
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        header("Location: signup.php?error=Database error: Unable to create account");
        exit();
    } catch(Exception $e) {
        error_log("General error: " . $e->getMessage());
        header("Location: signup.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: signup.php");
    exit();
}
?>