<?php
session_start();
require_once 'db.php';

// Get homepage content from database
$athlete_achievements = $pdo->query("SELECT * FROM homepage_content WHERE section_type = 'athletics' AND status = 'published' ORDER BY post_date DESC LIMIT 3")->fetchAll();
$dancer_achievements = $pdo->query("SELECT * FROM homepage_content WHERE section_type = 'dance' AND status = 'published' ORDER BY post_date DESC LIMIT 3")->fetchAll();
$upcoming_events = $pdo->query("SELECT * FROM upcoming_events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 4")->fetchAll();

// Get stats
$total_users = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'];
$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 3")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Talent Management System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Original TALENTRIX Styles - Keep exactly as you had */
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
            border-bottom: 3px solid #10b981;
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
            background: #f0fdf4;
            color: #065f46;
        }
        
        .talentrix-nav-links a.login-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
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
            color: #34d399;
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
            font-size: 14px;
        }
        
        .talentrix-btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .talentrix-btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
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
        
        /* NEW: Achievements Grid */
        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .achievement-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 5px solid #8B1E3F;
            position: relative;
        }
        
        .achievement-card.dance {
            border-left-color: #FFB347;
        }
        
        .achievement-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        }
        
        .achievement-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-athletics {
            background: #8B1E3F;
            color: white;
        }
        
        .badge-dance {
            background: #FFB347;
            color: #1e3e5c;
        }
        
        .achievement-date {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .achievement-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #0a2540;
            margin-bottom: 15px;
            padding-right: 80px;
        }
        
        /* NEW: Events Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            background: linear-gradient(135deg, #0a2540 0%, #1a365d 100%);
            padding: 60px;
            border-radius: 20px;
            color: white;
        }
        
        .event-card {
            background: rgba(255,255,255,0.1);
            padding: 25px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        
        .event-date {
            font-size: 1.8rem;
            font-weight: 700;
            color: #FFB347;
        }
        
        /* NEW: Quick Links */
        .quick-links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .quick-link {
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }
        
        .quick-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        /* Original User Cards */
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
            text-align: center;
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
            margin: 0 auto 20px;
        }
        
        /* Original Features */
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
        
        .activation-warning {
            text-align: center;
            padding: 10px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 0.9rem;
            border-top: 1px solid #cbd5e1;
        }
        
        @media (max-width: 768px) {
            .talentrix-hero h1 { font-size: 2.5rem; }
            .talentrix-navbar { flex-direction: column; }
            .events-grid { padding: 30px; }
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
                    <a href="manage_homepage.php">📝 Manage Homepage</a>
                <?php endif; ?>
                <a href="logout.php">🚪 Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            <?php else: ?>
                <a href="login.php" class="login-btn">🔑 Login</a>
                <a href="register.php">📝 Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="talentrix-hero">
        <h1>Talent Management System</h1>
        <p>Designed to manage, track, and organize records of athletes, performers, and graduates. Provides efficient data management, reporting, and monitoring for schools and organizations.</p>
        
        <div class="talentrix-hero-stats">
            <span>📈 Total Registered:</span>
            <strong><?php echo $total_users; ?></strong>
            <span>users</span>
        </div>
        
        <?php if(!isset($_SESSION['user_id'])): ?>
            <div class="talentrix-cta-buttons">
                <a href="register.php" class="talentrix-btn talentrix-btn-primary">GET STARTED FREE</a>
                <a href="login.php" class="talentrix-btn talentrix-btn-secondary">ADMIN LOGIN</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ACHIEVEMENTS SECTION (Dynamic from Admin) -->
<div class="talentrix-section">
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 40px; align-items: start;">
        <!-- Left Column: Achievements -->
        <div>
            <h2 style="text-align: left; margin-bottom: 30px;">🏆 LATEST ACHIEVEMENTS</h2>
            <?php 
            $all_achievements = array_merge($athlete_achievements, $dancer_achievements);
            usort($all_achievements, function($a, $b) {
                return strtotime($b['post_date']) - strtotime($a['post_date']);
            });
            $all_achievements = array_slice($all_achievements, 0, 3);
            
            if(!empty($all_achievements)): 
            ?>
            <div class="achievements-grid" style="grid-template-columns: 1fr;">
                <?php foreach($all_achievements as $achievement): ?>
                <div class="achievement-card <?php echo $achievement['section_type'] == 'dance' ? 'dance' : ''; ?>" style="margin-bottom: 20px;">
                    <span class="achievement-badge <?php echo $achievement['section_type'] == 'athletics' ? 'badge-athletics' : 'badge-dance'; ?>">
                        <?php echo htmlspecialchars($achievement['badge']); ?>
                    </span>
                    <div class="achievement-date">
                        📅 <?php echo date('F d, Y', strtotime($achievement['post_date'])); ?>
                    </div>
                    <h3 class="achievement-title"><?php echo htmlspecialchars($achievement['title']); ?></h3>
                    <p><?php echo htmlspecialchars(substr($achievement['content'], 0, 150)) . '...'; ?></p>
                    <a href="#" style="color: #10b981; font-weight: 600; text-decoration: none; display: inline-block; margin-top: 15px;">
                        Continue reading →
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Column: Image Sidebar -->
        <div class="image-sidebar">
            <div style="background: linear-gradient(135deg, #8B1E3F 0%, #6b152f 100%); padding: 30px; border-radius: 20px; color: white; margin-bottom: 30px;">
                <h3 style="font-size: 1.8rem; margin-bottom: 15px;">📸 FEATURED</h3>
                <p>Latest moments from our athletes and dancers</p>
            </div>
            
            <!-- Image Gallery -->
            <div class="image-gallery" style="display: grid; gap: 20px;">
                <!-- Image 1 - Athletics -->
                <div class="gallery-item" style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <div style="height: 200px; background: linear-gradient(135deg, #0a2540 20%, #8B1E3F 100%); display: flex; align-items: center; justify-content: center; position: relative;">
                        <i class="fas fa-basketball-ball" style="font-size: 80px; color: rgba(255,255,255,0.3);"></i>
                        <span style="position: absolute; bottom: 15px; left: 15px; background: rgba(0,0,0,0.5); color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem;">🏀 Athletics</span>
                    </div>
                    <div style="padding: 20px;">
                        <h4 style="color: #0a2540;">Basketball Team Training</h4>
                        <p style="color: #718096; font-size: 0.9rem;">February 2025</p>
                    </div>
                </div>
                
                <!-- Image 2 - Dance -->
                <div class="gallery-item" style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <div style="height: 200px; background: linear-gradient(135deg, #FFB347 20%, #f39c12 100%); display: flex; align-items: center; justify-content: center; position: relative;">
                        <i class="fas fa-music" style="font-size: 80px; color: rgba(255,255,255,0.3);"></i>
                        <span style="position: absolute; bottom: 15px; left: 15px; background: rgba(0,0,0,0.5); color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem;">💃 Dance Troupe</span>
                    </div>
                    <div style="padding: 20px;">
                        <h4 style="color: #0a2540;">Street Dance Competition</h4>
                        <p style="color: #718096; font-size: 0.9rem;">January 2025</p>
                    </div>
                </div>
                
                <!-- Image 3 - Volleyball -->
                <div class="gallery-item" style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <div style="height: 200px; background: linear-gradient(135deg, #10b981 20%, #059669 100%); display: flex; align-items: center; justify-content: center; position: relative;">
                        <i class="fas fa-volleyball-ball" style="font-size: 80px; color: rgba(255,255,255,0.3);"></i>
                        <span style="position: absolute; bottom: 15px; left: 15px; background: rgba(0,0,0,0.5); color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem;">🏐 Volleyball</span>
                    </div>
                    <div style="padding: 20px;">
                        <h4 style="color: #0a2540;">Women's Volleyball Champions</h4>
                        <p style="color: #718096; font-size: 0.9rem;">December 2024</p>
                    </div>
                </div>
            </div>
            
            <!-- Upload Button (for admin) -->
            <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
            <div style="margin-top: 30px; text-align: center;">
                <a href="manage_images.php" style="display: inline-block; padding: 12px 30px; background: #0a2540; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    <i class="fas fa-upload"></i> Upload New Image
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

    <!-- EVENTS SECTION (Dynamic from Admin) -->
<div class="talentrix-section">
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 40px; align-items: start;">
        <!-- Left Column: Events -->
        <div>
            <h2 style="text-align: left; margin-bottom: 30px;">📅 UPCOMING EVENTS</h2>
            <?php if(!empty($upcoming_events)): ?>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px;">
                <?php foreach($upcoming_events as $event): ?>
                <div style="background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border-left: 5px solid <?php echo $event['event_type'] == 'athletics' ? '#8B1E3F' : '#FFB347'; ?>;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: <?php echo $event['event_type'] == 'athletics' ? '#8B1E3F' : '#FFB347'; ?>;">
                        <?php echo date('M d', strtotime($event['event_date'])); ?>
                    </div>
                    <h3 style="margin: 10px 0; color: #0a2540;"><?php echo htmlspecialchars($event['event_title']); ?></h3>
                    <p style="color: #718096; font-size: 0.9rem;"><?php echo htmlspecialchars($event['event_description']); ?></p>
                    <span style="display: inline-block; margin-top: 10px; padding: 3px 12px; background: <?php echo $event['event_type'] == 'athletics' ? '#8B1E3F' : '#FFB347'; ?>; color: white; border-radius: 15px; font-size: 0.7rem;">
                        <?php echo ucfirst($event['event_type']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Column: Event Image -->
        <div class="event-image-sidebar">
            <div style="background: linear-gradient(135deg, #0a2540 0%, #1a365d 100%); border-radius: 20px; padding: 30px; color: white; text-align: center;">
                <i class="fas fa-calendar-check" style="font-size: 60px; margin-bottom: 20px; color: #FFB347;"></i>
                <h3 style="font-size: 1.8rem; margin-bottom: 10px;">Don't Miss Out!</h3>
                <p style="opacity: 0.9;">Register now for upcoming tryouts and competitions</p>
                
                <!-- Calendar Preview -->
                <div style="margin-top: 30px; background: rgba(255,255,255,0.1); border-radius: 15px; padding: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span style="font-weight: 600;">February 2025</span>
                        <span><i class="fas fa-chevron-right"></i></span>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; text-align: center; font-size: 0.8rem;">
                        <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                        <?php for($i=1; $i<=28; $i++): ?>
                        <span style="background: <?php echo $i == 15 ? '#FFB347' : 'rgba(255,255,255,0.1)'; ?>; color: <?php echo $i == 15 ? '#0a2540' : 'white'; ?>; padding: 5px; border-radius: 5px;"><?php echo $i; ?></span>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- QUICK LINKS -->
    <div class="talentrix-section">
        <h2>⚡ QUICK ACCESS</h2>
        <div class="quick-links-grid">
            <a href="#" class="quick-link" style="background: #8B1E3F; color: white;">
                <i class="fas fa-basketball-ball" style="font-size: 40px;"></i>
                <h3>Varsity Teams</h3>
            </a>
            <a href="#" class="quick-link" style="background: #FFB347; color: #0a2540;">
                <i class="fas fa-music" style="font-size: 40px;"></i>
                <h3>Dance Troupes</h3>
            </a>
            <a href="#" class="quick-link" style="background: #10b981; color: white;">
                <i class="fas fa-calendar-check" style="font-size: 40px;"></i>
                <h3>Training Schedules</h3>
            </a>
            <a href="#" class="quick-link" style="background: #0a2540; color: white;">
                <i class="fas fa-trophy" style="font-size: 40px;"></i>
                <h3>Tryouts</h3>
            </a>
        </div>
    </div>

    <!-- RECENT USERS (Original) -->
    <div class="talentrix-section">
        <h2>👥 RECENT MEMBERS</h2>
        <div class="talentrix-user-cards">
            <?php foreach($recent_users as $user): ?>
            <div class="talentrix-user-card">
                <div class="talentrix-user-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                <p style="color: #718096;"><?php echo htmlspecialchars($user['email']); ?></p>
                <span style="background: #e2e8f0; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem;">
                    <?php echo ucfirst($user['user_type']); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- FEATURES (Original) -->
    <div class="talentrix-section">
        <h2>✨ FEATURES</h2>
        <div class="talentrix-features-grid">
            <div class="talentrix-feature">
                <div class="talentrix-feature-icon">🎯</div>
                <h3>Athlete Management</h3>
                <p style="color: #718096;">Track athletic performance, competition records, and training progress.</p>
            </div>
            <div class="talentrix-feature">
                <div class="talentrix-feature-icon">💃</div>
                <h3>Dancer Management</h3>
                <p style="color: #718096;">Manage dance troupe members, performances, and choreography schedules.</p>
            </div>
            <div class="talentrix-feature">
                <div class="talentrix-feature-icon">📊</div>
                <h3>Performance Analytics</h3>
                <p style="color: #718096;">Generate reports and track achievements over time.</p>
            </div>
        </div>
    </div>

    <div class="talentrix-footer">
        <p>© <?php echo date('Y'); ?> TALENTRIX. All rights reserved.</p>
        <p style="font-size: 14px; margin-top: 8px;">Version 2.1.0 | Athletes & Dancers Management System</p>
    </div>
    
    <div class="activation-warning">
        <i class="fab fa-windows"></i> Activate Windows · Go to Settings to activate Windows
    </div>
</body>
</html>