<?php
// index.php - TALENTRIX Homepage with clickable buttons
session_start();
require_once 'db.php';

// Get latest achievements with student names
$achievements = $pdo->query("
    SELECT a.*, s.id as student_id, s.student_type, u.first_name, u.last_name
    FROM achievements a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE a.is_verified = 1
    ORDER BY a.created_at DESC 
    LIMIT 4
")->fetchAll();

// Get counts for stats
$total_athletes = $pdo->query("SELECT COUNT(*) FROM students WHERE student_type IN ('athlete','both')")->fetchColumn();
$total_dancers = $pdo->query("SELECT COUNT(*) FROM students WHERE student_type IN ('dancer','both')")->fetchColumn();
$total_coaches = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type IN ('sport_coach','dance_coach')")->fetchColumn();
$total_achievements = $pdo->query("SELECT COUNT(*) FROM achievements WHERE is_verified = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: #f5f7fb;
        }

        /* Navbar */
        .navbar {
            background: white;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .logo h1 {
            font-size: 24px;
            color: #8B1E3F;
            font-weight: 700;
        }

        .logo h1 i {
            margin-right: 10px;
        }

        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #495057;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: #8B1E3F;
        }

        .btn-login {
            background: #8B1E3F;
            color: white !important;
            padding: 10px 25px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .btn-login:hover {
            background: #6b152f;
        }

        .btn-register {
            background: #10b981;
            color: white !important;
            padding: 10px 25px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .btn-register:hover {
            background: #059669;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #0a2540 0%, #1a365d 100%);
            color: white;
            padding: 120px 40px 80px;
            text-align: center;
        }

        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
        }

        .hero p {
            font-size: 18px;
            max-width: 700px;
            margin: 0 auto 30px;
            opacity: 0.9;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .btn-primary {
            background: #8B1E3F;
            color: white;
            padding: 15px 35px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            background: #6b152f;
        }

        .btn-secondary {
            background: transparent;
            color: white;
            padding: 15px 35px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            border: 2px solid white;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: white;
            color: #0a2540;
        }

        /* Stats Section - CLICKABLE CARDS */
        .stats-section {
            padding: 60px 40px;
            max-width: 1200px;
            margin: auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            border: 1px solid #e9ecef;
            border-left: 5px solid #8B1E3F;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .stat-icon i {
            font-size: 20px;
            color: #8B1E3F;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #0a2540;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Achievements Section - CLICKABLE CARDS */
        .achievements-section {
            padding: 60px 40px;
            background: white;
        }

        .section-header {
            max-width: 1200px;
            margin: 0 auto 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h2 {
            font-size: 32px;
            color: #0a2540;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 i {
            color: #8B1E3F;
        }

        .view-all {
            color: #8B1E3F;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .view-all:hover {
            color: #6b152f;
        }

        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            max-width: 1200px;
            margin: auto;
        }

        .achievement-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #e9ecef;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .achievement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .card-header {
            height: 8px;
            background: #8B1E3F;
        }

        .card-content {
            padding: 20px;
        }

        .student-name {
            color: #8B1E3F;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .achievement-title {
            font-size: 18px;
            color: #0a2540;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .achievement-desc {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .achievement-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
            font-size: 13px;
        }

        .achievement-date {
            color: #6c757d;
        }

        .achievement-date i {
            margin-right: 5px;
        }

        .medal-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .medal-gold {
            background: #FFD700;
            color: #000;
        }

        .medal-silver {
            background: #C0C0C0;
            color: #000;
        }

        .medal-bronze {
            background: #CD7F32;
            color: #fff;
        }

        /* Features Section - CLICKABLE CARDS */
        .features-section {
            padding: 60px 40px;
            background: #f8f9fa;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            max-width: 1200px;
            margin: auto;
        }

        .feature-item {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .feature-icon i {
            font-size: 28px;
            color: #8B1E3F;
        }

        .feature-item h3 {
            font-size: 18px;
            color: #0a2540;
            margin-bottom: 10px;
        }

        .feature-item p {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.6;
        }

        /* Footer */
        .footer {
            background: #0a2540;
            color: white;
            padding: 40px;
            text-align: center;
        }

        .footer p {
            opacity: 0.8;
            font-size: 14px;
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .achievements-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
                padding: 15px 20px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .hero h1 {
                font-size: 32px;
            }
            
            .stats-grid,
            .achievements-grid,
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo">
            <h1><i class="fas fa-running"></i>TALENTRIX</h1>
        </div>
        <div class="nav-links">
            <a href="#home">Home</a>
            <a href="#achievements">Achievements</a>
            <a href="#features">Features</a>
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php 
                // Check user type for dashboard redirect
                $dashboard_link = 'dashboard.php';
                if(isset($_SESSION['user_type'])) {
                    if($_SESSION['user_type'] == 'athletics_admin') {
                        $dashboard_link = 'athletics_admin_dashboard.php';
                    } elseif($_SESSION['user_type'] == 'dance_admin') {
                        $dashboard_link = 'dance_admin_dashboard.php';
                    } elseif($_SESSION['user_type'] == 'sport_coach') {
                        $dashboard_link = 'coach_dashboard.php';
                    } elseif($_SESSION['user_type'] == 'dance_coach') {
                        $dashboard_link = 'dance_trainer_dashboard.php';
                    } elseif($_SESSION['user_type'] == 'student') {
                        $dashboard_link = 'student_dashboard.php';
                    } elseif($_SESSION['user_type'] == 'admin') {
                        $dashboard_link = 'admin_pages.php?page=dashboard';
                    }
                }
                ?>
                <a href="<?php echo $dashboard_link; ?>" class="btn-login">Dashboard</a>
                <a href="logout.php" class="btn-register">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn-login">Login</a>
                <a href="register.php" class="btn-register">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <h1>Welcome to TALENTRIX</h1>
        <p>Your comprehensive sports and dance management system. Track achievements, manage teams, and celebrate excellence.</p>
        <div class="hero-buttons">
            <a href="register.php" class="btn-primary">Get Started</a>
            <a href="#achievements" class="btn-secondary">View Achievements</a>
        </div>
    </section>

    <!-- Stats Section - All Cards Clickable -->
    <section class="stats-section">
        <div class="stats-grid">
            <a href="athletes_list.php" class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-running"></i>
                </div>
                <div class="stat-number"><?php echo $total_athletes ?: 0; ?></div>
                <div class="stat-label">Active Athletes</div>
            </a>
            <a href="dancers_list.php" class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-music"></i>
                </div>
                <div class="stat-number"><?php echo $total_dancers ?: 0; ?></div>
                <div class="stat-label">Active Dancers</div>
            </a>
            <a href="coaches_list.php" class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-number"><?php echo $total_coaches ?: 0; ?></div>
                <div class="stat-label">Certified Coaches</div>
            </a>
            <a href="achievements.php" class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-number"><?php echo $total_achievements ?: 0; ?></div>
                <div class="stat-label">Total Achievements</div>
            </a>
        </div>
    </section>

    <!-- Achievements Section - Cards Clickable -->
    <section class="achievements-section" id="achievements">
        <div class="section-header">
            <h2><i class="fas fa-trophy"></i> Latest Achievements</h2>
            <a href="achievements.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
        </div>

        <div class="achievements-grid">
            <?php if (!empty($achievements)): ?>
                <?php foreach ($achievements as $ach): ?>
                <a href="view_achievement.php?id=<?php echo $ach['id']; ?>" class="achievement-card">
                    <div class="card-header"></div>
                    <div class="card-content">
                        <div class="student-name">
                            <i class="fas fa-user-graduate"></i> 
                            <?php echo htmlspecialchars($ach['first_name'] . ' ' . $ach['last_name']); ?>
                        </div>
                        <h3 class="achievement-title"><?php echo htmlspecialchars($ach['achievement_title']); ?></h3>
                        <p class="achievement-desc">
                            <?php echo htmlspecialchars(substr($ach['achievement_description'] ?? 'No description', 0, 80)) . '...'; ?>
                        </p>
                        <div class="achievement-meta">
                            <span class="achievement-date">
                                <i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($ach['event_date'] ?? $ach['created_at'])); ?>
                            </span>
                            <?php if ($ach['medal_type'] != 'none'): ?>
                                <span class="medal-badge medal-<?php echo $ach['medal_type']; ?>">
                                    <?php 
                                    if($ach['medal_type'] == 'gold') echo '🥇 Gold';
                                    elseif($ach['medal_type'] == 'silver') echo '🥈 Silver';
                                    elseif($ach['medal_type'] == 'bronze') echo '🥉 Bronze';
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 60px;">
                    <i class="fas fa-trophy" style="font-size: 64px; color: #6c757d;"></i>
                    <h3 style="color: #6c757d; margin-top: 20px;">No achievements yet</h3>
                    <p style="color: #6c757d; margin-top: 10px;">Be the first to add an achievement!</p>
                    <?php if(isset($_SESSION['user_id']) && ($_SESSION['user_type'] == 'admin' || $_SESSION['user_type'] == 'athletics_admin' || $_SESSION['user_type'] == 'dance_admin')): ?>
                    <a href="add_achievement.php" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #8B1E3F; color: white; text-decoration: none; border-radius: 5px;">
                        <i class="fas fa-plus"></i> Add Achievement
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Features Section - Cards Clickable -->
    <section class="features-section" id="features">
        <div class="section-header" style="text-align: center; display: block;">
            <h2><i class="fas fa-star"></i> Why Choose TALENTRIX?</h2>
        </div>
        
        <div class="features-grid">
            <a href="feature_achievement_tracking.php" class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3>Achievement Tracking</h3>
                <p>Record and showcase all athletic and dance achievements in one place.</p>
            </a>
            <a href="feature_team_management.php" class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Team Management</h3>
                <p>Efficiently manage athletes, dancers, coaches, and team assignments.</p>
            </a>
            <a href="feature_event_scheduling.php" class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>Event Scheduling</h3>
                <p>Plan and coordinate events, competitions, and practice schedules.</p>
            </a>
            <a href="feature_performance_analytics.php" class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Performance Analytics</h3>
                <p>Track progress and generate performance reports for teams and individuals.</p>
            </a>
            <a href="feature_approval_system.php" class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>Approval System</h3>
                <p>Streamlined approval process for student athletes and dancers.</p>
            </a>
            <a href="feature_security.php" class="feature-item">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Secure Platform</h3>
                <p>Role-based access control ensuring data security and privacy.</p>
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2026 TALENTRIX. All rights reserved. Bohol Island State University.</p>
    </footer>
</body>
</html>