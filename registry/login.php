<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';

// ✅✅✅ ADDED: Check for registration success message from registration.php
if(isset($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']); // Clear after displaying
}

// Check if redirected from successful registration via URL parameter
if(isset($_GET['registered']) && $_GET['registered'] === 'success') {
    $success = "🎉 Registration successful! You can now login with your credentials.";
}

// Check if redirected from logout
if(isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = "👋 Successfully logged out!";
}

// Check if redirected from admin access attempt
if(isset($_GET['access']) && $_GET['access'] === 'denied') {
    $error = "⛔ Admin access required! Please login as administrator.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if(empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user) {
            // Manual admin override (for testing only - remove in production)
            if (($username === 'admin' || $user['user_type'] === 'admin') && $password === 'admin123') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Redirect to admin dashboard
                header('Location: admin_pages.php?page=dashboard');
                exit();
            }
            // Normal password verification
            else if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Redirect based on user type
                if ($user['user_type'] === 'admin') {
                    header('Location: admin_pages.php?page=dashboard');
                } else {
                    header('Location: index.php');
                }
                exit();
            } else {
                $error = "Invalid username or password!";
            }
        } else {
            $error = "User not found!";
        }
    }
}

// Check if already logged in
if(isset($_SESSION['user_id'])) {
    if($_SESSION['user_type'] === 'admin') {
        header('Location: admin_pages.php?page=dashboard');
    } else {
        header('Location: index.php');
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Login</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* TALENTRIX Login Styles */
        .talentrix-login-page {
            background: linear-gradient(135deg, #0a2540 0%, #1a365d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            position: relative;
        }
        
        .talentrix-login-container {
            width: 100%;
            max-width: 450px;
            background: white;
            border-radius: 20px;
            padding: 50px 45px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.35);
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .talentrix-header {
            margin-bottom: 45px;
        }
        
        .talentrix-logo {
            font-size: 42px;
            font-weight: 900;
            background: linear-gradient(135deg, #0a2540 0%, #10b981 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2.5px;
            margin-bottom: 8px;
        }
        
        .talentrix-tagline {
            font-size: 15px;
            color: #4a5568;
            font-weight: 500;
            letter-spacing: 1.2px;
            margin-top: 8px;
        }
        
        .login-subtitle {
            font-size: 18px;
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 30px;
        }
        
        .talentrix-form {
            margin-bottom: 35px;
        }
        
        .talentrix-input-group {
            margin-bottom: 28px;
            text-align: left;
        }
        
        .talentrix-input-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .talentrix-input-group label i {
            color: #10b981;
            font-size: 16px;
        }
        
        .talentrix-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8fafc;
            color: #2d3748;
        }
        
        .talentrix-input:focus {
            border-color: #10b981;
            background: white;
            outline: none;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
            transform: translateY(-2px);
        }
        
        .talentrix-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 15px;
            position: relative;
            overflow: hidden;
        }
        
        .talentrix-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.35);
        }
        
        .talentrix-btn:active {
            transform: translateY(-1px);
        }
        
        .talentrix-links {
            text-align: center;
            margin-top: 40px;
            font-size: 14px;
            color: #4a5568;
        }
        
        .talentrix-links p {
            margin: 18px 0;
        }
        
        .talentrix-links a {
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .talentrix-links a:hover {
            color: #059669;
            transform: translateX(3px);
        }
        
        .talentrix-error {
            background: #fed7d7;
            color: #c53030;
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-size: 14px;
            border-left: 5px solid #c53030;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: shake 0.5s ease-in-out;
        }
        
        .talentrix-success {
            background: #c6f6d5;
            color: #22543d;
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-size: 14px;
            border-left: 5px solid #38a169;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.5s ease-out;
        }
        
        .talentrix-divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e2e8f0, transparent);
            margin: 35px 0;
            position: relative;
        }
        
        .talentrix-divider::before {
            content: "or";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 0 15px;
            color: #a0aec0;
            font-size: 12px;
            font-weight: 600;
        }
        
        .back-home {
            margin-top: 30px;
        }
        
        .back-home a {
            color: #718096;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .back-home a:hover {
            color: #10b981;
            transform: translateX(-5px);
        }
        
        /* Decorative background elements */
        .bg-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
            opacity: 0.1;
        }
        
        .bg-circle {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #3b82f6);
            animation: float 20s infinite linear;
        }
        
        .circle-1 {
            width: 300px;
            height: 300px;
            top: -150px;
            right: -150px;
            animation-delay: 0s;
        }
        
        .circle-2 {
            width: 200px;
            height: 200px;
            bottom: -100px;
            left: -100px;
            animation-delay: 5s;
            background: linear-gradient(135deg, #8b5cf6, #ec4899);
        }
        
        /* Animations */
        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(30px, -30px) rotate(90deg); }
            50% { transform: translate(0, -60px) rotate(180deg); }
            75% { transform: translate(-30px, -30px) rotate(270deg); }
            100% { transform: translate(0, 0) rotate(360deg); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Demo credentials note */
        .demo-credentials {
            background: #e0f2fe;
            border-left: 4px solid #0ea5e9;
            padding: 12px 16px;
            border-radius: 8px;
            margin-top: 25px;
            text-align: left;
            font-size: 13px;
            color: #0369a1;
        }
        
        .demo-credentials h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .demo-credentials ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .demo-credentials li {
            margin: 5px 0;
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .talentrix-login-container {
                padding: 35px 25px;
                margin: 20px;
            }
            
            .talentrix-logo {
                font-size: 36px;
            }
            
            .talentrix-tagline {
                font-size: 14px;
            }
        }
        
        /* Loading effect */
        .loading {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .btn-loading .loading {
            display: block;
        }
        
        .btn-loading .btn-text {
            opacity: 0.5;
        }
        
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
</head>
<body class="talentrix-login-page">
    <!-- Decorative background -->
    <div class="bg-pattern">
        <div class="bg-circle circle-1"></div>
        <div class="bg-circle circle-2"></div>
    </div>
    
    <div class="talentrix-login-container">
        <div class="talentrix-header">
            <div class="talentrix-logo">TALENTRIX</div>
            <div class="talentrix-tagline">TALENT & GRADUATE MANAGEMENT SYSTEM</div>
            <div class="login-subtitle">Login to Your Account</div>
        </div>
        
        <?php if($success): ?>
            <div class="talentrix-success">
                <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="talentrix-error">
                <i class="fas fa-exclamation-triangle" style="font-size: 18px;"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="talentrix-form" id="loginForm">
            <div class="talentrix-input-group">
                <label for="username">
                    <i class="fas fa-user"></i>
                    Username or Email
                </label>
                <input type="text" id="username" name="username" 
                       class="talentrix-input" 
                       placeholder="Enter your username or email" 
                       required
                       autofocus>
            </div>
            
            <div class="talentrix-input-group">
                <label for="password">
                    <i class="fas fa-lock"></i>
                    Password
                </label>
                <input type="password" id="password" name="password" 
                       class="talentrix-input" 
                       placeholder="Enter your password" 
                       required>
            </div>
            
            <button type="submit" class="talentrix-btn" id="loginBtn">
                <span class="btn-text">LOGIN</span>
                <div class="loading"></div>
            </button>
        </form>
        
        <!-- Demo credentials for testing -->
        <div class="demo-credentials">
            <h4><i class="fas fa-info-circle"></i> Demo Credentials:</h4>
            <ul>
                <li><strong>Admin:</strong> admin / admin123</li>
                <li><strong>Student:</strong> Use your registered credentials</li>
            </ul>
        </div>
        
        <div class="talentrix-links">
            <p>New student? <a href="register.php">
                <i class="fas fa-user-plus"></i>
                Create an account
            </a></p>
            
            <div class="talentrix-divider"></div>
            
            <p>Forgot password? <a href="forgot_password.php">
                <i class="fas fa-key"></i>
                Reset password
            </a></p>
            
            <div class="back-home">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            
            // Form validation
            loginForm.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                
                if(!username || !password) {
                    e.preventDefault();
                    alert('Please fill in all fields');
                    return false;
                }
                
                // Show loading state
                loginBtn.classList.add('btn-loading');
                loginBtn.disabled = true;
                
                return true;
            });
            
            // Auto-fill demo credentials for testing
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.get('demo') === 'admin') {
                document.getElementById('username').value = 'admin';
                document.getElementById('password').value = 'admin123';
            }
            
            // Enter key to submit
            document.getElementById('password').addEventListener('keypress', function(e) {
                if(e.key === 'Enter') {
                    loginForm.requestSubmit();
                }
            });
        });
    </script>
</body>
</html>