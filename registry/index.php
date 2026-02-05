<?php
session_start();
require_once 'db.php';

// Get stats
$total_users = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'];
$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 3")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Talent & Graduate Management System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* TALENTRIX Homepage Styles */
        .talentrix-homepage {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #2d3748;
        }
        
        .talentrix-navbar {
            background: white;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-bottom: 3px solid #10b981; /* Emerald green for talent/growth */
        }
        
        .talentrix-logo {
            font-size: 28px;
            font-weight: 800;
            color: #0a2540;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .talentrix-logo-icon {
            color: #10b981;
            font-size: 32px;
        }
        
        .talentrix-nav-links {
            display: flex;
            gap: 25px;
            align-items: center;
        }
        
        .talentrix-nav-links a {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            color: #4a5568;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .talentrix-nav-links a:hover {
            background: #f0fdf4; /* Light green background */
            color: #065f46;
        }
        
        .talentrix-nav-links a.login-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .talentrix-nav-links a.login-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }
        
        .talentrix-hero {
            text-align: center;
            padding: 100px 20px;
            background: linear-gradient(135deg, #0a2540 0%, #1a365d 100%);
            color: white;
            margin-bottom: 60px;
        }
        
        .talentrix-hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
        }
        
        .talentrix-hero p {
            font-size: 1.3rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto 40px;
            line-height: 1.6;
        }
        
        .talentrix-hero-stats {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 30px;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            margin-top: 20px;
        }
        
        .talentrix-hero-stats strong {
            font-size: 1.5rem;
            color: #34d399; /* Emerald light */
        }
        
        .talentrix-cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 40px;
        }
        
        .talentrix-btn {
            padding: 16px 32px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 14px;
        }
        
        .talentrix-btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .talentrix-btn-primary:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }
        
        .talentrix-btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .talentrix-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-3px);
        }
        
        .talentrix-section {
            max-width: 1200px;
            margin: 0 auto 80px;
            padding: 0 20px;
        }
        
        .talentrix-section h2 {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: #0a2540;
            margin-bottom: 50px;
            position: relative;
        }
        
        .talentrix-section h2:after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: #10b981;
            margin: 15px auto;
            border-radius: 2px;
        }
        
        .talentrix-user-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .talentrix-user-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        
        .talentrix-user-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
            border-color: #10b981;
        }
        
        .talentrix-user-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        
        .talentrix-features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 40px;
            margin-top: 50px;
        }
        
        .talentrix-feature {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }
        
        .talentrix-feature:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: #10b981;
        }
        
        .talentrix-feature-icon {
            font-size: 48px;
            margin-bottom: 25px;
            color: #10b981;
        }
        
        .talentrix-footer {
            text-align: center;
            padding: 40px;
            background: #0a2540;
            color: #a0aec0;
            margin-top: 80px;
        }
        
        .welcome-user {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px 30px;
            border-radius: 12px;
            margin-top: 30px;
            display: inline-block;
        }
        
        .welcome-user strong {
            color: #34d399;
            font-size: 1.2rem;
        }
        
        @media (max-width: 768px) {
            .talentrix-hero h1 {
                font-size: 2.5rem;
            }
            
            .talentrix-navbar {
                flex-direction: column;
                gap: 20px;
                padding: 20px;
            }
            
            .talentrix-nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .talentrix-cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .talentrix-btn {
                width: 100%;
                max-width: 300px;
                text-align: center;
            }
        }
    </style>
</head>
<body class="talentrix-homepage">
    <nav class="talentrix-navbar">
        <div class="talentrix-logo">
            <span class="talentrix-logo-icon">🏆</span>
            TALENTRIX
        </div>
        <div class="talentrix-nav-links">
            <a href="index.php">🏠 Home</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php if($_SESSION['user_type'] === 'admin'): ?>
                    <a href="dashboard.php">📊 Dashboard</a>
                <?php endif; ?>
                <a href="logout.php">🚪 Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            <?php else: ?>
                <a href="login.php" class="login-btn">🔑 Login</a>
                <a href="register.php">📝 Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="talentrix-hero">
        <h1>Talent & Graduate Management System</h1>
        <p>Designed to manage, track, and organize records of athletes, performers, and graduates. Provides efficient data management, reporting, and monitoring for schools and organizations.</p>
        
        <div class="talentrix-hero-stats">
            <span>📈 Total Registered:</span>
            <strong><?php echo $total_users; ?></strong>
            <span>users</span>
        </div>
        
        <?php if(!isset($_SESSION['user_id'])): ?>
            <div class="talentrix-cta-buttons">
                <a href="register.php" class="talentrix-btn talentrix-btn-primary">Get Started Free</a>
                <a href="login.php" class="talentrix-btn talentrix-btn-secondary">Admin Login</a>
            </div>
        <?php else: ?>
            <div class="welcome-user">
                <p>Hello, <strong><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong>! 👋</p>
                <p>Welcome back to TALENTRIX</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if($recent_users): ?>
    <div class="talentrix-section">
        <h2>Recent Registrations</h2>
        <div class="talentrix-user-cards">
            <?php foreach($recent_users as $user): ?>
            <div class="talentrix-user-card">
                <div class="talentrix-user-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                <p><strong>ID:</strong> <?php echo htmlspecialchars($user['id_number']); ?></p>
                <?php if($user['institution']): ?>
                    <p><strong>Institution:</strong> <?php echo htmlspecialchars($user['institution']); ?></p>
                <?php endif; ?>
                <p><strong>Type:</strong> 
                    <span class="badge badge-<?php echo $user['user_type']; ?>">
                        <?php echo htmlspecialchars($user['user_type']); ?>
                    </span>
                </p>
                <p><small>Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?></small></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="talentrix-section">
        <h2>Core Features</h2>
        <div class="talentrix-features-grid">
            <div class="talentrix-feature">
                <div class="talentrix-feature-icon">📋</div>
                <h3>Talent Profile Management</h3>
                <p>Comprehensive tracking of athletes, performers, and graduate records in one centralized platform</p>
            </div>
            <div class="talentrix-feature">
                <div class="talentrix-feature-icon">📊</div>
                <h3>Performance Analytics</h3>
                <p>AI-driven insights into talent development, achievement tracking, and performance metrics</p>
            </div>
            <div class="talentrix-feature">
                <div class="talentrix-feature-icon">🔒</div>
                <h3>Secure Data Management</h3>
                <p>Role-based access control with institutional verification for sensitive talent data</p>
            </div>
        </div>
    </div>

    <footer class="talentrix-footer">
        <p>© 2024 TALENTRIX - Talent & Graduate Management System. All rights reserved.</p>
        <p style="margin-top: 10px; font-size: 14px;">Bohol Island State University | Academic Technology Division</p>
    </footer>
</body>
</html>