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
    <title>Sign Up - SmartPark</title>
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
            background: linear-gradient(-45deg, #1a1a2e, #16213e, #0f3460, #533483);
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
            width: 120px;
            height: 120px;
            top: 12%;
            left: 12%;
            animation-delay: 0s;
            background: rgba(255, 215, 0, 0.1);
        }

        .shape:nth-child(2) {
            width: 180px;
            height: 180px;
            top: 68%;
            left: 78%;
            animation-delay: 2s;
            background: rgba(3, 233, 244, 0.1);
        }

        .shape:nth-child(3) {
            width: 90px;
            height: 90px;
            top: 82%;
            left: 22%;
            animation-delay: 4s;
            background: rgba(255, 107, 107, 0.1);
        }

        .shape:nth-child(4) {
            width: 140px;
            height: 140px;
            top: 20%;
            left: 72%;
            animation-delay: 6s;
            background: rgba(81, 207, 102, 0.1);
        }

        @keyframes float {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg) scale(1);
            }
            33% { 
                transform: translateY(-25px) rotate(120deg) scale(1.05);
            }
            66% { 
                transform: translateY(15px) rotate(240deg) scale(0.95);
            }
        }

        .container {
            position: relative;
            width: 100%;
            max-width: 480px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 24px;
            padding: 45px 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            color: #fff;
            z-index: 2;
            transition: all 0.4s ease;
        }

        .container:hover {
            transform: translateY(-8px);
            box-shadow: 0 35px 70px rgba(0, 0, 0, 0.4);
        }

        .logo {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo h1 {
            font-size: 2.8em;
            background: linear-gradient(45deg, #ffd700, #03e9f4, #ff6b6b);
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
            border-bottom-color: #ffd700;
            background: rgba(255, 215, 0, 0.05);
            padding-left: 15px;
            border-radius: 8px 8px 0 0;
        }

        .form-group input:valid {
            border-bottom-color: #51cf66;
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
            color: #ffd700;
            font-size: 13px;
            font-weight: 600;
        }

        .form-group label i {
            width: 16px;
            text-align: center;
        }

        .user-type {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 35px;
        }

        .user-option {
            position: relative;
            cursor: pointer;
        }

        .user-option input {
            display: none;
        }

        .user-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 25px 20px;
            background: rgba(255, 255, 255, 0.08);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            text-align: center;
            transition: all 0.4s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .user-label::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: 0.5s;
        }

        .user-label:hover::before {
            left: 100%;
        }

        .user-option input:checked + .user-label {
            background: rgba(255, 215, 0, 0.15);
            border-color: #ffd700;
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(255, 215, 0, 0.25);
        }

        .user-label:hover {
            transform: translateY(-5px);
            border-color: rgba(255, 215, 0, 0.5);
        }

        .user-label i {
            font-size: 28px;
            margin-bottom: 12px;
            color: #ffd700;
            transition: all 0.3s ease;
        }

        .user-option input:checked + .user-label i {
            transform: scale(1.2);
        }

        .user-label span {
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 5px;
        }

        .user-label .desc {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.4;
        }

        .btn {
            width: 100%;
            padding: 18px 0;
            background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%);
            border: none;
            border-radius: 14px;
            color: #fff;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s ease;
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.4);
            position: relative;
            overflow: hidden;
        }

        .btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(255, 215, 0, 0.5);
        }

        .btn:active {
            transform: translateY(-2px);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: 0.6s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .links {
            text-align: center;
            margin-top: 30px;
        }

        .links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 15px;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.06);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .links a:hover {
            color: #ffd700;
            background: rgba(255, 215, 0, 0.1);
            transform: translateY(-3px);
        }

        .error-messages {
            background: rgba(255, 107, 107, 0.15);
            border: 1px solid rgba(255, 107, 107, 0.4);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 25px;
            animation: slideDown 0.5s ease-out;
        }

        .error-message {
            color: #ff6b6b;
            font-size: 14px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .error-message:last-child {
            margin-bottom: 0;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            font-size: 16px;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            color: #ffd700;
            background: rgba(255, 255, 255, 0.1);
        }

        .input-hint {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 8px;
            font-weight: 400;
        }

        .password-strength, .password-match {
            font-size: 13px;
            margin-top: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(255, 215, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @media (max-width: 520px) {
            .container {
                padding: 35px 25px;
                margin: 10px;
            }
            
            .logo h1 {
                font-size: 2.3em;
            }
            
            .user-type {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .user-label {
                padding: 20px 15px;
            }
        }

        /* تأثيرات إضافية للقوة */
        .strength-weak { color: #ff6b6b; }
        .strength-medium { color: #ffd700; }
        .strength-strong { color: #51cf66; }

        /* تحسينات للشاشات الصغيرة */
        @media (max-width: 380px) {
            .container {
                padding: 30px 20px;
            }
            
            .logo h1 {
                font-size: 2em;
            }
            
            .btn {
                padding: 16px 0;
                font-size: 16px;
            }
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
            <p>Join Our Smart Parking Community</p>
        </div>
        
        <h2>Create Your Account</h2>
        
        <?php if(isset($_GET['error'])): ?>
            <div class="error-messages">
                <?php
                $errors = explode("\\n", $_GET['error']);
                foreach($errors as $error):
                ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="register-process.php" method="POST" id="signupForm">
            <div class="form-group">
                <input type="text" name="username" id="username" required 
                       pattern="[a-zA-Z0-9_]+" title="Only letters, numbers and underscores allowed"
                       autocomplete="username">
                <label>
                    <i class="fas fa-user"></i>
                    <span>Username</span>
                </label>
                <div class="input-hint">3+ characters, letters, numbers, underscores only</div>
            </div>
            
            <div class="form-group">
                <input type="email" name="email" id="email" required autocomplete="email">
                <label>
                    <i class="fas fa-envelope"></i>
                    <span>Email Address</span>
                </label>
            </div>
            
            <div class="form-group">
                <input type="password" name="password" id="password" required 
                       minlength="6" oninput="checkPasswordStrength()" autocomplete="new-password">
                <label>
                    <i class="fas fa-lock"></i>
                    <span>Password</span>
                </label>
                <button type="button" class="password-toggle" onclick="togglePassword('password')" aria-label="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </button>
                <div class="password-strength" id="passwordStrength"></div>
            </div>
            
            <div class="form-group">
                <input type="password" name="confirm_password" id="confirm_password" required 
                       oninput="checkPasswordMatch()" autocomplete="new-password">
                <label>
                    <i class="fas fa-lock"></i>
                    <span>Confirm Password</span>
                </label>
                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')" aria-label="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </button>
                <div class="password-match" id="passwordMatch"></div>
            </div>
            
            <div class="user-type">
                <div class="user-option">
                    <input type="radio" name="user_type" value="user" id="user" checked>
                    <label class="user-label" for="user">
                        <i class="fas fa-user"></i>
                        <span>Regular User</span>
                        <div class="desc">Find & book parking spots easily</div>
                    </label>
                </div>
                
                <div class="user-option">
                    <input type="radio" name="user_type" value="manager" id="manager">
                    <label class="user-label" for="manager">
                        <i class="fas fa-user-tie"></i>
                        <span>Parking Manager</span>
                        <div class="desc">Manage parking facilities & operations</div>
                    </label>
                </div>
            </div>
            
            <button type="submit" class="btn pulse" id="signupBtn">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
            
            <div class="links">
                <a href="login.php">
                    <i class="fas fa-sign-in-alt"></i> Already have an account?
                </a>
            </div>
        </form>
    </div>

    <script>
        // دالة للتحقق من تطابق كلمات المرور
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchText.textContent = '';
                matchText.className = 'password-match';
                return;
            }
            
            if (password === confirmPassword) {
                matchText.textContent = '✓ Passwords match';
                matchText.className = 'password-match strength-strong';
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.className = 'password-match strength-weak';
            }
        }
        
        // دالة للتحقق من قوة كلمة المرور
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthText = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthText.textContent = '';
                strengthText.className = 'password-strength';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            
            if (password.match(/[a-z]/)) strength++;
            else feedback.push('lowercase letter');
            
            if (password.match(/[A-Z]/)) strength++;
            else feedback.push('uppercase letter');
            
            if (password.match(/\d/)) strength++;
            else feedback.push('number');
            
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            else feedback.push('special character');
            
            const strengthLabels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
            const strengthClasses = ['strength-weak', 'strength-weak', 'strength-medium', 'strength-medium', 'strength-strong', 'strength-strong'];
            
            strengthText.textContent = `Password Strength: ${strengthLabels[strength]}`;
            strengthText.className = `password-strength ${strengthClasses[strength]}`;
            
            checkPasswordMatch();
        }
        
        // دالة للتحقق من صحة النموذج قبل الإرسال
        function validateForm() {
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // التحقق من اسم المستخدم
            if (username.length < 3) {
                showAlert('Username must be at least 3 characters long');
                return false;
            }
            
            if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                showAlert('Username can only contain letters, numbers and underscores');
                return false;
            }
            
            // التحقق من كلمة المرور
            if (password.length < 6) {
                showAlert('Password must be at least 6 characters long');
                return false;
            }
            
            if (password !== confirmPassword) {
                showAlert('Passwords do not match');
                return false;
            }
            
            return true;
        }
        
        function showAlert(message) {
            alert('⚠️ ' + message);
        }
        
        // دالة لإظهار/إخفاء كلمة المرور
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = passwordInput.parentElement.querySelector('.password-toggle i');
            
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

        // Form submission animation
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            if (validateForm()) {
                const btn = document.getElementById('signupBtn');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
                btn.disabled = true;
                btn.classList.remove('pulse');
            }
        });

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

        // Add hover effects to user type options
        document.querySelectorAll('.user-option').forEach(option => {
            option.addEventListener('mouseenter', function() {
                if (!this.querySelector('input').checked) {
                    this.querySelector('.user-label').style.transform = 'translateY(-5px)';
                }
            });
            
            option.addEventListener('mouseleave', function() {
                if (!this.querySelector('input').checked) {
                    this.querySelector('.user-label').style.transform = 'translateY(0)';
                }
            });
        });
    </script>
</body>
</html>