<?php
require_once 'includes/session.php';
redirectIfLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BISU Athletes & Arts Registry</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #003366;
            --primary-gold: #ffd700;
            --sports-green: #27ae60;
            --cultural-purple: #9b59b6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }

        /* Login Page with Split Design */
        .login-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Left Side - Branding/Image */
        .login-branding {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-blue) 0%, #0a2a4a 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }
        
        .branding-bg-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.08;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            background-repeat: repeat;
        }
        
        .branding-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
            max-width: 400px;
        }
        
        .branding-logo {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary-gold), #ffb347);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem auto;
        }
        
        .branding-logo i {
            font-size: 50px;
            color: var(--primary-blue);
        }
        
        .branding-content h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .branding-content p {
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .features-list {
            margin-top: 3rem;
            text-align: left;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .feature-item i {
            width: 30px;
            color: var(--primary-gold);
        }
        
        /* Right Side - Login Form */
        .login-form-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background: white;
            padding: 2rem;
        }
        
        .auth-card {
            background: white;
            width: 100%;
            max-width: 450px;
            padding: 2rem;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .auth-header h2 {
            color: var(--primary-blue);
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .auth-header p {
            color: #666;
            margin-top: 0.5rem;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem auto;
        }
        
        .logo-icon img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-blue);
            padding: 2px;
            background: white;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group .input-wrapper {
            position: relative;
        }
        
        .form-group .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 2.8rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0,51,102,0.1);
        }
        
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-blue), #1a4d8c);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,51,102,0.3);
        }
        
        .auth-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .auth-footer a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .auth-footer a:hover {
            color: var(--primary-gold);
        }
        
        .home-link {
            position: fixed;
            top: 20px;
            right: 30px;
            z-index: 100;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: var(--primary-blue);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .home-link:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
            }
            
            .login-branding {
                padding: 2rem;
                min-height: 300px;
            }
            
            .branding-content h1 {
                font-size: 1.5rem;
            }
            
            .features-list {
                display: none;
            }
            
            .home-link {
                top: 10px;
                right: 15px;
                padding: 6px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <a href="index.php" class="home-link">
        <i class="fas fa-home"></i> Back to Home
    </a>
    
    <div class="login-wrapper">
        <!-- Left Branding Side -->
        <div class="login-branding">
            <div class="branding-bg-pattern"></div>
            <div class="branding-content">
                <div class="branding-logo">
                    <i class="fas fa-trophy"></i>
                </div>
                <h1>Athletes & Arts Performers Registry</h1>
                <p>Track, celebrate, and showcase the outstanding achievements of BISU Candijay's finest talents.</p>
                
                <div class="features-list">
                    <div class="feature-item">
                        <i class="fas fa-futbol"></i>
                        <span>Track sports achievements & tournaments</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-palette"></i>
                        <span>Showcase cultural & arts performances</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Generate comprehensive analytics reports</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Login Form Side -->
        <div class="login-form-container">
            <div class="auth-card">
                <div class="auth-header">
                    <div class="logo-icon">
                        <img src="includes/uploads/images/bisu_logo.png" alt="BISU Logo">
                    </div>
                    <h2>Welcome Back! 👋</h2>
                    <p>Login to access your dashboard</p>
                </div>
                
                <?php if(isset($_GET['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($_GET['success']); ?>
                    </div>
                <?php endif; ?>
                
                <form action="actions/process_login.php" method="POST">
                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" required placeholder="Enter your email">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" required placeholder="Enter your password">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>