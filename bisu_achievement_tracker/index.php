<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$auth = new Auth($pdo);

// Redirect to dashboard if already logged in
if ($auth->isLoggedIn()) {
    $usertype = $_SESSION['usertype'];
    
    if ($usertype === 'admin') {
        $adminType = $_SESSION['admin_type'] ?? null;
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
}

// Fetch statistics for the landing page
$stats = [];

// Total students
$stmt = $pdo->query("SELECT COUNT(*) as total FROM Student");
$stats['total_students'] = $stmt->fetch()['total'];

// Total awards (top rankings)
$stmt = $pdo->query("
    SELECT COUNT(*) as total FROM Participation 
    WHERE ranking IN ('champion', '1st_place', '2nd_place', '3rd_place')
");
$stats['total_awards'] = $stmt->fetch()['total'];

// Total activities
$stmt = $pdo->query("SELECT COUNT(*) as total FROM Activity");
$stats['total_activities'] = $stmt->fetch()['total'];

// Total contests
$stmt = $pdo->query("SELECT COUNT(*) as total FROM Contest");
$stats['total_contests'] = $stmt->fetch()['total'];

// Get recent winners
$stmt = $pdo->query("
    SELECT 
        s.first_name AS student_first_name,
        s.last_name AS student_last_name,
        c.name AS contest_name,
        a.activity_name,
        a.activity_type,
        p.ranking,
        p.created_at
    FROM Participation p
    JOIN Student s ON p.student_id = s.id
    JOIN Contest c ON p.contest_id = c.id
    JOIN Activity a ON c.activity_id = a.id
    WHERE p.ranking IN ('champion', '1st_place')
    ORDER BY p.created_at DESC
    LIMIT 6
");
$recentWinners = $stmt->fetchAll();

// Get published announcements with images
$stmt = $pdo->query("
    SELECT a.*, u.email as author_email
    FROM Announcement a
    LEFT JOIN \"User\" u ON a.author_id = u.id
    WHERE a.is_published = true
    ORDER BY a.created_at DESC
    LIMIT 5
");
$announcements = $stmt->fetchAll();

// Parse image paths for each announcement
foreach ($announcements as $key => $announcement) {
    $images = [];
    if (!empty($announcement['image_path'])) {
        // Try to decode as JSON first
        $decoded = json_decode($announcement['image_path'], true);
        if (is_array($decoded)) {
            // It's a JSON array of multiple images
            $images = $decoded;
        } else {
            // It might be a single image path or comma-separated
            if (strpos($announcement['image_path'], ',') !== false) {
                $images = explode(',', $announcement['image_path']);
                $images = array_map('trim', $images);
            } else {
                $images = [$announcement['image_path']];
            }
        }
    }
    // Filter out empty values and ensure paths are valid
    $images = array_filter($images);
    $announcements[$key]['images'] = $images;
    // Store first image as thumbnail for card view
    $announcements[$key]['thumbnail'] = !empty($images) ? $images[0] : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BISU Candijay - Athlete & Art Performers Achievement Registry</title>
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
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background Elements */
        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.05"><path fill="white" d="M20,20 L30,20 L25,28 Z M70,70 L80,70 L75,78 Z M45,85 L55,85 L50,93 Z M85,15 L95,15 L90,23 Z"/><circle cx="50" cy="50" r="8"/><circle cx="15" cy="80" r="5"/><circle cx="85" cy="25" r="5"/></svg>');
            background-repeat: repeat;
            opacity: 0.1;
            pointer-events: none;
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #1a472a;
        }

        .logo h1 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #003366;
        }

        .logo p {
            font-size: 0.7rem;
            color: #003366;
            font-weight: 500;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #2c3e50;
            font-weight: 500;
            transition: all 0.3s;
        }

        .nav-links a:hover {
            color: #003366;
        }

        .btn-nav {
            padding: 0.6rem 1.8rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-login {
            background: transparent;
            color: #003366;
            border: 2px solid #003366;
        }

        .btn-login:hover {
            background: #1a472a;
            color: white;
            transform: translateY(-2px);
        }

        .btn-register {
            background: transparent;
            color: #003366;
            border: 2px solid #003366;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px #003366 (255, 140, 0, 0.4);
        }

        /* Hero Section */
        .hero {
            max-width: 1280px;
            margin: 0 auto;
            padding: 4rem 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .hero-content h1 {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1rem;
            color: white;
        }

        .hero-content .highlight {
            color: #003366;
            display: inline-block;
        }

        .hero-content p {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .hero-stats {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .stat-item {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 1rem 1.5rem;
            border-radius: 20px;
            min-width: 100px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: #ffd700;
        }

        .stat-label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-hero {
            padding: 0.8rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-hero-primary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-hero-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 140, 0, 0.4);
        }

        .btn-hero-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-hero-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .hero-image {
            text-align: center;
            position: relative;
        }

        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 3px solid rgba(255, 215, 0, 0.3);
        }

        /* Announcements Section */
        .announcements {
            background: linear-gradient(135deg, #fff8e7, #fff5e0);
            padding: 5rem 2rem;
        }

        .section-container {
            max-width: 1280px;
            margin: 0 auto;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #1a472a;
            margin-bottom: 1rem;
        }

        .section-title p {
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }

        .announcements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .announcement-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .announcement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
        }

        .announcement-header {
            background: linear-gradient(135deg, #1a472a, #0a2b3e);
            padding: 1.2rem 1.5rem;
            color: white;
        }

        .announcement-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .type-general { background: #6c757d; }
        .type-sports { background: #28a745; }
        .type-cultural_arts { background: #8e44ad; }
        .type-event { background: #fd7e14; }
        .type-achievement { background: #ffc107; color: #1a472a; }

        .announcement-header h3 {
            font-size: 1.2rem;
            margin: 0.5rem 0;
        }

        .announcement-date {
            font-size: 0.7rem;
            opacity: 0.8;
        }

        .announcement-image-container {
            width: 100%;
            height: 200px;
            overflow: hidden;
            position: relative;
            background: #f5f5f5;
        }

        .announcement-card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .image-count-badge {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .announcement-card:hover .announcement-card-image {
            transform: scale(1.05);
        }

        .announcement-content {
            padding: 1.5rem;
            color: #4a5568;
            line-height: 1.6;
        }

        .announcement-content p {
            margin-bottom: 1rem;
        }

        .read-more {
            color: #ff8c00;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .read-more:hover {
            gap: 10px;
        }

        /* Modal for full announcement */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 700px;
            max-height: 85vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }

        .modal-header {
            background: linear-gradient(135deg, #1a472a, #0a2b3e);
            padding: 1.5rem;
            color: white;
            border-radius: 20px 20px 0 0;
        }

        .modal-header h3 {
            font-size: 1.5rem;
            margin: 0.5rem 0;
        }

        .modal-body {
            padding: 1.5rem;
            line-height: 1.8;
            color: #4a5568;
        }

        .modal-images {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }

        .modal-images img {
            max-height: 300px;
            width: auto;
            object-fit: cover;
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .modal-images img:hover {
            transform: scale(1.02);
        }

        .modal-close {
            color: white;
            float: right;
            font-size: 1.8rem;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .modal-close:hover {
            color: #ffd700;
        }

        /* Features Section */
        .features {
            background: white;
            padding: 5rem 2rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: #f8fafc;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            border-color: #ffd700;
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(255, 140, 0, 0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .feature-icon i {
            font-size: 2rem;
            color: #ff8c00;
        }

        .feature-card h3 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: #1a472a;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
            font-size: 0.9rem;
        }

        /* Champions Section */
        .champions {
            padding: 5rem 2rem;
            background: linear-gradient(135deg, #f8fafc, #fff);
        }

        .champions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .champion-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            border-left: 4px solid #ffd700;
        }

        .champion-card:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .champion-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .champion-info h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: #1a472a;
        }

        .champion-info p {
            font-size: 0.8rem;
            color: #666;
        }

        .champion-badge {
            margin-left: auto;
            background: rgba(255, 215, 0, 0.2);
            padding: 0.3rem 0.8rem;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            color: #ff8c00;
        }

        /* CTA Section */
        .cta {
            background: linear-gradient(135deg, #1a472a, #0a2b3e);
            padding: 4rem 2rem;
            text-align: center;
            color: white;
        }

        .cta h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .cta p {
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .btn-cta {
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            color: #1a472a;
            padding: 0.8rem 2.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        /* Footer */
        .footer {
            background: #0a1a1f;
            color: white;
            padding: 3rem 2rem;
        }

        .footer-content {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .footer-section h4 {
            margin-bottom: 1rem;
            font-size: 1rem;
            color: #ffd700;
        }

        .footer-section p, .footer-section a {
            color: #a0b3c0;
            text-decoration: none;
            line-height: 1.8;
            font-size: 0.85rem;
        }

        .footer-section a:hover {
            color: #ffd700;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            margin-top: 2rem;
            border-top: 1px solid #2a3a4a;
            color: #a0b3c0;
            font-size: 0.8rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .hero {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 2rem;
                padding: 2rem;
            }
            
            .hero-content h1 {
                font-size: 1.8rem;
            }
            
            .hero-stats {
                justify-content: center;
            }
            
            .hero-buttons {
                justify-content: center;
            }
            
            .nav-container {
                flex-direction: column;
            }
            
            .section-title h2 {
                font-size: 1.5rem;
            }
            
            .champion-card {
                flex-wrap: wrap;
            }
            
            .champion-badge {
                margin-left: 0;
            }
            
            .announcements-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-images {
                flex-direction: column;
            }
            
            .modal-images img {
                max-height: 200px;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div>
                    <h1>Bohol Island State University</h1>
                    <p>Candijay Campus</p>
                </div>
            </a>
            <div class="nav-links">
                <a href="#home">Home</a>
                <a href="#announcements">Announcements</a>
                <a href="#features">Features</a>
                <a href="#champions">Achievers</a>
                <a href="login.php" class="btn-nav btn-login">Login</a>
                <a href="register.php" class="btn-nav btn-register">Register</a>
            </div>
        </div>
    </nav>

    <section id="home">
        <div class="hero">
            <div class="hero-content">
                <h1>
                    Athlete & Art Performers<br>
                    <span class="highlight">Achievement Registry</span>
                </h1>
                <p>Celebrating Excellence in Sports and Arts. Track and showcase the outstanding achievements of BISU Candijay's athletes and cultural performers.</p>
                <div class="hero-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_students']; ?>+</div>
                        <div class="stat-label">Active Students</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_awards']; ?>+</div>
                        <div class="stat-label">Awards Earned</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_activities']; ?></div>
                        <div class="stat-label">Activities</div>
                    </div>
                </div>
                <div class="hero-buttons">
                    <a href="login.php" class="btn-hero btn-hero-primary"><i class="fas fa-sign-in-alt"></i> Login to Registry</a>
                    <a href="register.php" class="btn-hero btn-hero-secondary"><i class="fas fa-user-plus"></i> Create Account</a>
                </div>
            </div>
            <div class="hero-image">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Crect width='400' height='300' fill='%232c3e50'/%3E%3Ccircle cx='200' cy='150' r='60' fill='%23ffd700' opacity='0.3'/%3E%3Ctext x='200' y='160' text-anchor='middle' fill='%23ffd700' font-size='40' font-weight='bold'%3E🏆%3C/text%3E%3Ctext x='200' y='220' text-anchor='middle' fill='white' font-size='14'%3ECelebrating Excellence%3C/text%3E%3C/svg%3E" alt="Achievement Registry">
            </div>
        </div>
    </section>

    <!-- Announcements Section -->
    <?php if (!empty($announcements)): ?>
    <div class="announcements" id="announcements">
        <div class="section-container">
            <div class="section-title">
                <h2><i class="fas fa-bullhorn"></i> Latest Announcements</h2>
                <p>Stay updated with the latest news and events</p>
            </div>
            <div class="announcements-grid">
                <?php foreach ($announcements as $announcement): ?>
                <div class="announcement-card" onclick="openModal(<?php echo $announcement['id']; ?>)">
                    <div class="announcement-header">
                        <span class="announcement-type type-<?php echo $announcement['announcement_type']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $announcement['announcement_type'])); ?>
                        </span>
                        <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                        <div class="announcement-date">
                            <i class="far fa-calendar-alt"></i> <?php echo date('F d, Y', strtotime($announcement['created_at'])); ?>
                        </div>
                    </div>
                    <?php if (!empty($announcement['thumbnail'])): ?>
                    <div class="announcement-image-container">
                        <img src="<?php echo $announcement['thumbnail']; ?>" class="announcement-card-image" alt="Announcement image" onerror="this.style.display='none'">
                        <?php if (count($announcement['images']) > 1): ?>
                        <div class="image-count-badge">
                            +<?php echo count($announcement['images']) - 1; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="announcement-content">
                        <p><?php echo htmlspecialchars(substr($announcement['content'], 0, 120)) . '...'; ?></p>
                        <span class="read-more">Read More <i class="fas fa-arrow-right"></i></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal for full announcement -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-close" onclick="closeModal()">&times;</span>
                <span id="modalType" class="announcement-type"></span>
                <h3 id="modalTitle"></h3>
                <div id="modalDate" class="announcement-date"></div>
            </div>
            <div id="modalBody" class="modal-body">
            </div>
        </div>
    </div>

    <div class="features" id="features">
        <div class="section-container">
            <div class="section-title">
                <h2>Registry Features</h2>
                <p>Comprehensive tracking system for student achievements</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3>Track Achievements</h3>
                    <p>Record and monitor student achievements in sports competitions and cultural arts performances.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Performance Analytics</h3>
                    <p>View detailed statistics and visualize progress over time with comprehensive analytics.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Role-Based Access</h3>
                    <p>Separate dashboards for students, coaches, and administrators with tailored features.</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($recentWinners)): ?>
    <div class="champions" id="champions">
        <div class="section-container">
            <div class="section-title">
                <h2>🏆 Recent Achievers</h2>
                <p>Celebrating our outstanding athletes and artists</p>
            </div>
            <div class="champions-grid">
                <?php foreach ($recentWinners as $winner): ?>
                <div class="champion-card">
                    <div class="champion-avatar">
                        <i class="fas fa-<?php echo $winner['activity_type'] == 'sports' ? 'futbol' : 'palette'; ?>"></i>
                    </div>
                    <div class="champion-info">
                        <h4><?php echo htmlspecialchars($winner['student_first_name'] . ' ' . $winner['student_last_name']); ?></h4>
                        <p><?php echo htmlspecialchars($winner['contest_name']); ?></p>
                    </div>
                    <div class="champion-badge">
                        <?php echo $winner['ranking'] === 'champion' ? '🏆 Champion' : '🥇 1st Place'; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="cta">
        <h2>Join the Achievement Registry</h2>
        <p>Be part of BISU Candijay's community of achievers in sports and cultural arts.</p>
        <a href="register.php" class="btn-cta">Create Account <i class="fas fa-arrow-right"></i></a>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h4>Bohol Island State University</h4>
                <p>Candijay Campus<br>Tracking excellence in Sports & Arts</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <p><a href="login.php">Login</a></p>
                <p><a href="register.php">Register</a></p>
            </div>
            <div class="footer-section">
                <h4>Contact</h4>
                <p><i class="fas fa-map-marker-alt"></i> Candijay, Bohol</p>
                <p><i class="fas fa-envelope"></i> bisu.candijay@edu.ph</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> BISU Candijay Achievement Registry. All rights reserved.</p>
        </div>
    </footer>

    <script>
    // Announcements data for modal
    const announcementsData = <?php echo json_encode($announcements); ?>;

    function openModal(id) {
        const announcement = announcementsData.find(a => a.id === id);
        if (announcement) {
            document.getElementById('modalType').innerHTML = announcement.announcement_type.replace('_', ' ').toUpperCase();
            document.getElementById('modalType').className = 'announcement-type type-' + announcement.announcement_type;
            document.getElementById('modalTitle').innerHTML = announcement.title;
            document.getElementById('modalDate').innerHTML = '<i class="far fa-calendar-alt"></i> ' + new Date(announcement.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            
            let modalBodyHtml = '';
            
            // Display all images if they exist
            if (announcement.images && announcement.images.length > 0) {
                modalBodyHtml += '<div class="modal-images">';
                announcement.images.forEach(img => {
                    modalBodyHtml += `<img src="${img}" alt="Announcement image" onclick="viewFullImage('${img}')">`;
                });
                modalBodyHtml += '</div>';
            }
            
            modalBodyHtml += '<p>' + announcement.content.replace(/\n/g, '<br>') + '</p>';
            document.getElementById('modalBody').innerHTML = modalBodyHtml;
            document.getElementById('announcementModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }
    
    function viewFullImage(imagePath) {
        window.open(imagePath, '_blank');
    }

    function closeModal() {
        document.getElementById('announcementModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('announcementModal');
        if (event.target === modal) {
            closeModal();
        }
    }
    </script>
</body>
</html>