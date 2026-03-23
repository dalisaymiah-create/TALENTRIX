<?php
// login.php - COMPLETE FIXED VERSION
session_start();
require_once 'db.php';

$error = '';
$success = '';

// Check for registration success message
if(isset($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
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

// IMPORTANT: Only redirect if user is already logged in
if(isset($_SESSION['user_id'])) {
    // User is already logged in - redirect to appropriate dashboard
    if($_SESSION['user_type'] === 'admin') {
        header('Location: admin_pages.php?page=dashboard');
        exit();
    } elseif($_SESSION['user_type'] === 'athletics_admin') {
        header('Location: athletics_admin_dashboard.php');
        exit();
    } elseif($_SESSION['user_type'] === 'dance_admin') {
        header('Location: dance_admin_dashboard.php');
        exit();
    } elseif($_SESSION['user_type'] === 'sport_coach') {
        header('Location: coach_dashboard.php');
        exit();
    } elseif($_SESSION['user_type'] === 'dance_coach') {
        header('Location: dance_trainer_dashboard.php');
        exit();
    } elseif($_SESSION['user_type'] === 'student') {
        header('Location: student_dashboard.php');
        exit();
    } else {
        // Fallback for unknown user types
        header('Location: index.php');
        exit();
    }
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
            // Check if account is active
            if ($user['status'] === 'inactive') {
                $error = "Your account has been deactivated. Please contact administrator.";
            }
            // Check if email verified (for students/coaches)
            else if ($user['user_type'] !== 'admin' && !$user['is_verified'] && $user['status'] === 'pending') {
                $error = "Your account is pending verification. Please wait for admin approval.";
            }
            // Password verification
            else if (password_verify($password, $user['password'])) {
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Set session variables
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
                    exit();
                } elseif ($user['user_type'] === 'athletics_admin') {
                    header('Location: athletics_admin_dashboard.php');
                    exit();
                } elseif ($user['user_type'] === 'dance_admin') {
                    header('Location: dance_admin_dashboard.php');
                    exit();
                } elseif ($user['user_type'] === 'sport_coach') {
                    header('Location: coach_dashboard.php');
                    exit();
                } elseif ($user['user_type'] === 'dance_coach') {
                    header('Location: dance_trainer_dashboard.php');
                    exit();
                } elseif ($user['user_type'] === 'student') {
                    header('Location: student_dashboard.php');
                    exit();
                } else {
                    // Fallback for any other type
                    header('Location: index.php');
                    exit();
                }
            } else {
                $error = "Invalid username or password!";
            }
        } else {
            $error = "User not found!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #0a2540 0%, #1a365d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
            background: white;
            border-radius: 20px;
            padding: 50px 45px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.35);
            text-align: center;
        }
        
        .header {
            margin-bottom: 45px;
        }
        
        .logo {
            font-size: 42px;
            font-weight: 900;
            background: linear-gradient(135deg, #0a2540 0%, #10b981 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2.5px;
            margin-bottom: 8px;
        }
        
        .tagline {
            font-size: 15px;
            color: #4a5568;
            font-weight: 500;
            letter-spacing: 1.2px;
            margin-top: 8px;
        }
        
        .subtitle {
            font-size: 18px;
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 28px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group label i {
            color: #10b981;
            font-size: 16px;
        }
        
        .form-control {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8fafc;
        }
        
        .form-control:focus {
            border-color: #10b981;
            background: white;
            outline: none;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
            transform: translateY(-2px);
        }
        
        .btn-login {
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
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.35);
        }
        
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .links {
            text-align: center;
            margin-top: 40px;
            font-size: 14px;
            color: #4a5568;
        }
        
        .links p {
            margin: 18px 0;
        }
        
        .links a {
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .links a:hover {
            color: #059669;
            transform: translateX(3px);
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-size: 14px;
            border-left: 5px solid;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border-left-color: #c53030;
            animation: shake 0.5s ease-in-out;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left-color: #38a169;
            animation: slideIn 0.5s ease-out;
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e2e8f0, transparent);
            margin: 35px 0;
            position: relative;
        }
        
        .divider::before {
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
        
        @media (max-width: 576px) {
            .login-container {
                padding: 35px 25px;
                margin: 20px;
            }
            
            .logo {
                font-size: 36px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header">
            <div class="logo">TALENTRIX</div>
            <div class="tagline">TALENT MANAGEMENT SYSTEM</div>
            <div class="subtitle">Login to Your Account</div>
        </div>
        
        <?php if($success): ?>
            <div class="alert alert-success" id="success-alert">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error" id="error-alert">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i>
                    Username or Email
                </label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       class="form-control" 
                       placeholder="Enter your username or email" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       required
                       autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i>
                    Password
                </label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-control" 
                       placeholder="Enter your password" 
                       required>
            </div>
            
            <button type="submit" class="btn-login" id="loginBtn">
                LOGIN
            </button>
        </form>
        
        
        
        <div class="links">
            <p>New to TALENTRIX? <a href="register.php">
                <i class="fas fa-user-plus"></i>
                Create an account
            </a></p>
            
            <div class="divider"></div>
            
            <div class="back-home">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
    
    <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        
        if (!username || !password) {
            e.preventDefault();
            alert('Please fill in all fields');
            return false;
        }
        
        const loginBtn = document.getElementById('loginBtn');
        loginBtn.disabled = true;
        loginBtn.textContent = 'LOGGING IN...';
        
        return true;
    });
    
    setTimeout(function() {
        const successAlert = document.getElementById('success-alert');
        const errorAlert = document.getElementById('error-alert');
        
        if (successAlert) {
            successAlert.style.transition = 'opacity 0.5s';
            successAlert.style.opacity = '0';
            setTimeout(() => successAlert.style.display = 'none', 500);
        }
        
        if (errorAlert) {
            errorAlert.style.transition = 'opacity 0.5s';
            errorAlert.style.opacity = '0';
            setTimeout(() => errorAlert.style.display = 'none', 500);
        }
    }, 5000);
    
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
</body>
</html>