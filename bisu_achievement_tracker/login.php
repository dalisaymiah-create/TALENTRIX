<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$auth = new Auth($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    if ($auth->login($email, $password)) {
        $usertype = $_SESSION['usertype'];
        
        if ($usertype === 'admin') {
            $adminType = $_SESSION['admin_type'];
            if ($adminType === 'cultural_arts') {
                header("Location: dashboards/admin_cultural.php");
            } elseif ($adminType === 'sports') {
                header("Location: dashboards/admin_sports.php");
            } else {
                header("Location: dashboards/admin.php");
            }
        } else {
            header("Location: dashboards/$usertype.php");
        }
        exit();
    } else {
        $error = 'Invalid email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BISU Candijay Achievement Registry</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #003366 0%, #003366 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        /* Decorative elements */
        body::before {
            content: '🏆';
            position: absolute;
            font-size: 15rem;
            opacity: 0.03;
            bottom: -3rem;
            left: -3rem;
            pointer-events: none;
        }

        body::after {
            content: '🎭';
            position: absolute;
            font-size: 15rem;
            opacity: 0.03;
            top: -3rem;
            right: -3rem;
            pointer-events: none;
        }

        /* Navigation Bar */
        .top-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            z-index: 100;
            padding: 0.8rem 2rem;
        }

        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #1a472a;
        }

        .logo-text h2 {
            font-size: 1rem;
            font-weight: 700;
            color: #1a472a;
        }

        .logo-text p {
            font-size: 0.65rem;
            color: #ff8c00;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
        }

        .nav-links a {
            text-decoration: none;
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #ff8c00;
        }

        .btn-home {
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            color: #1a472a;
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
        }

        /* Auth Container */
        .auth-container {
            background: white;
            border-radius: 30px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 480px;
            width: 100%;
            padding: 40px;
            margin-top: 70px;
            position: relative;
            z-index: 10;
        }

        /* Header Section */
        .auth-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .institution-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1a472a;
            margin-bottom: 5px;
        }

        .registry-title {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #003366, #003366);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 8px;
        }

        .auth-subtitle {
            color: #666;
            font-size: 0.85rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1rem;
        }

        .form-group input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: #280f7b;
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #003366, #003366);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 140, 0, 0.3);
        }

        /* Footer Links */
        .auth-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .auth-footer p {
            color: #666;
            font-size: 0.85rem;
        }

        .auth-footer a {
            color: #05057a;
            text-decoration: none;
            font-weight: 600;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .back-link {
            display: inline-block;
            margin-top: 12px;
            color: #999 !important;
            font-size: 0.8rem;
        }

        .back-link i {
            margin-right: 5px;
        }

        .message {
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .message.error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        @media (max-width: 768px) {
            .auth-container {
                padding: 30px 25px;
                margin-top: 90px;
            }
            
            .registry-title {
                font-size: 1.5rem;
            }
            
            .nav-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .top-nav {
                padding: 0.8rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="nav-container">
            <a href="index.php" class="logo-area">
                <div class="logo-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="logo-text">
                    <h2>Bohol Island State University</h2>
                    <p>Candijay Campus</p>
                </div>
            </a>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="index.php#features">Features</a>
                <a href="index.php#champions">Achievers</a>
                <a href="register.php" class="btn-home">Register</a>
            </div>
        </div>
    </div>

    <div class="auth-container">
        <div class="auth-header">
            <img src="assets/images/bisu-logo.png" alt="BiSU Logo" class="logo">
            <div class="institution-name">Bohol Island State University</div>
            <div class="registry-title">Login to Achievement<br>Registry</div>
        </div>
        
        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Username or Email</label>
                <div class="input-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Enter your username or email" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>
            
            <button type="submit" class="btn-submit">Login</button>
        </form>
        
        <div class="auth-footer">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
    </div>
</body>
</html>