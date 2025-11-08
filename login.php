<?php
session_start();
if(isset($_SESSION['user_id'])) {
    if($_SESSION['user_type'] == 'user') {
        header("Location: dashboard-user.php");
    } else {
        header("Location: dashboard-manager.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SmartPark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(-45deg, #1a1a2e, #16213e, #001e43ff, #2d0a60ff);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
            overflow: hidden;
            padding: 20px;
        }

        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .floating-shapes {
            position: fixed;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
            pointer-events: none;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
            backdrop-filter: blur(5px);
        }

        .shape:nth-child(1) {
            width: 100px;
            height: 100px;
            top: 15%;
            left: 10%;
            animation-delay: 0s;
            background: rgba(3, 233, 244, 0.1);
        }

        .shape:nth-child(2) {
            width: 150px;
            height: 150px;
            top: 65%;
            left: 80%;
            animation-delay: 2s;
            background: rgba(255, 107, 107, 0.1);
        }

        .shape:nth-child(3) {
            width: 80px;
            height: 80px;
            top: 80%;
            left: 20%;
            animation-delay: 4s;
            background: rgba(81, 207, 102, 0.1);
        }

        .shape:nth-child(4) {
            width: 120px;
            height: 120px;
            top: 25%;
            left: 75%;
            animation-delay: 6s;
            background: rgba(255, 215, 0, 0.1);
        }

        @keyframes float {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg) scale(1);
            }
            33% { 
                transform: translateY(-20px) rotate(120deg) scale(1.05);
            }
            66% { 
                transform: translateY(10px) rotate(240deg) scale(0.95);
            }
        }

        .container {
            position: relative;
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 24px;
            padding: 45px 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            color: #fff;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .container:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.35);
        }

        .logo {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo h1 {
            font-size: 2.8em;
            background: linear-gradient(45deg, #03e9f4, #ffd700, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 8px;
        }

        .logo p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 15px;
            font-weight: 300;
            letter-spacing: 0.5px;
        }

        .container h2 {
            text-align: center;
            margin-bottom: 35px;
            font-weight: 600;
            font-size: 1.9em;
            color: rgba(255, 255, 255, 0.95);
        }

        .form-group {
            position: relative;
            margin-bottom: 32px;
        }

        .form-group input {
            width: 100%;
            padding: 16px 0;
            font-size: 16px;
            color: #fff;
            border: none;
            border-bottom: 2px solid rgba(255, 255, 255, 0.25);
            outline: none;
            background: transparent;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            border-bottom-color: #03e9f4;
            background: rgba(3, 233, 244, 0.05);
            padding-left: 15px;
            border-radius: 8px 8px 0 0;
        }

        .form-group label {
            position: absolute;
            top: 16px;
            left: 0;
            padding: 10px 0;
            font-size: 16px;
            color: rgba(255, 255, 255, 0.6);
            pointer-events: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group input:focus ~ label,
        .form-group input:valid ~ label {
            top: -22px;
            left: 0;
            color: #03e9f4;
            font-size: 13px;
            font-weight: 600;
        }

        .form-group label i {
            width: 16px;
            text-align: center;
        }

        .btn {
            width: 100%;
            padding: 16px 0;
            background: linear-gradient(135deg, #03e9f4 0%, #ff6b6b 100%);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(3, 233, 244, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(3, 233, 244, 0.4);
        }

        .btn:active {
            transform: translateY(-1px);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .links {
            display: flex;
            justify-content: space-between;
            margin-top: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .links a:hover {
            color: #03e9f4;
            background: rgba(3, 233, 244, 0.1);
            transform: translateY(-2px);
        }

        .error {
            color: #ff6b6b;
            text-align: center;
            margin-bottom: 25px;
            padding: 15px;
            background: rgba(255, 107, 107, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(255, 107, 107, 0.3);
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            animation: shake 0.5s ease-in-out;
        }

        .success {
            color: #51cf66;
            text-align: center;
            margin-bottom: 25px;
            padding: 15px;
            background: rgba(81, 207, 102, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(81, 207, 102, 0.3);
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            animation: bounceIn 0.6s ease-out;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            font-size: 16px;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            color: #03e9f4;
            background: rgba(255, 255, 255, 0.1);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        @keyframes bounceIn {
            0% { 
                opacity: 0;
                transform: scale(0.8);
            }
            50% { 
                opacity: 1;
                transform: scale(1.05);
            }
            100% { 
                opacity: 1;
                transform: scale(1);
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 35px 25px;
                margin: 10px;
            }
            
            .logo h1 {
                font-size: 2.3em;
            }
            
            .links {
                flex-direction: column;
                text-align: center;
            }
            
            .links a {
                justify-content: center;
            }
        }

        /* تأثيرات إضافية */
        .form-group input:valid {
            border-bottom-color: #51cf66;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(3, 233, 244, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(3, 233, 244, 0); }
            100% { box-shadow: 0 0 0 0 rgba(3, 233, 244, 0); }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="container">
        <div class="logo">
            <h1><i class="fas fa-parking"></i> SmartPark</h1>
            <p>Intelligent Parking Solutions</p>
        </div>
        
        <h2>Welcome Back!</h2>
        
             <?php if(isset($_GET['error'])): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> 
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_GET['success'])): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i> 
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <form action="login-process.php" method="POST" id="loginForm">
            <div class="form-group">
                <input type="text" name="username" required autocomplete="username">
                <label>
                    <i class="fas fa-user"></i>
                    <span>Username</span>
                </label>
            </div>
            
            <div class="form-group">
                <input type="password" name="password" id="password" required autocomplete="current-password">
                <label>
                    <i class="fas fa-lock"></i>
                    <span>Password</span>
                </label>
                <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            
            <button type="submit" class="btn pulse" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
            
            <div class="links">
                <a href="signup.php">
                    <i class="fas fa-user-plus"></i> Create New Account
                </a>
                <a href="#" onclick="showForgotPassword()">
                    <i class="fas fa-key"></i> Forgot Password?
                </a>
            </div>
        </form>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
                toggleIcon.parentElement.setAttribute('aria-label', 'Hide password');
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
                toggleIcon.parentElement.setAttribute('aria-label', 'Show password');
            }
        }

        // Add floating animation to form inputs on focus
        document.querySelectorAll('.form-group input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-5px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Form submission animation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
            btn.disabled = true;
            btn.classList.remove('pulse');
        });

        function showForgotPassword() {
            alert('Please contact system administrator to reset your password.\n\nEmail: admin@smartpark.com\nPhone: +1-555-0123');
        }

        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="username"]').focus();
        });

        // Add input validation feedback
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    this.classList.add('has-value');
                } else {
                    this.classList.remove('has-value');
                }
            });
        });
    </script>
</body>
</html>