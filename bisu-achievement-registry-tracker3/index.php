<?php
require_once 'includes/session.php';
redirectIfLoggedIn();

// Get current school year function
function getCurrentSchoolYear() {
    $current_year = date('Y');
    $current_month = date('n');
    if ($current_month >= 6) {
        return $current_year . '-' . ($current_year + 1);
    } else {
        return ($current_year - 1) . '-' . $current_year;
    }
}

// Fetch announcements - JOIN with activity to get school_year
$announcements = [];
$availableSchoolYears = [];
$allAnnouncementImages = []; // Collect all images for the hero slider

try {
    require_once 'config/database.php';
    
    // Get all available school years from activity for filter dropdown
    $stmt = $pdo->prepare("
        SELECT DISTINCT school_year 
        FROM activity 
        WHERE school_year IS NOT NULL
        ORDER BY school_year DESC
    ");
    $stmt->execute();
    $availableSchoolYears = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($availableSchoolYears)) {
        $availableSchoolYears = [['school_year' => getCurrentSchoolYear()]];
    }
    
    // Get school year filter from URL
    $school_year_filter = isset($_GET['school_year']) ? $_GET['school_year'] : 'all';
    
    // FIXED: Get ALL published announcements - show all when "all" is selected
    if ($school_year_filter == 'all') {
        $stmt = $pdo->prepare("
            SELECT a.*, u.email as author_email, act.activity_name, act.school_year, act.competition_level
            FROM announcement a
            JOIN \"User\" u ON a.author_id = u.id
            LEFT JOIN activity act ON a.activity_id = act.id
            WHERE a.is_published = true
            ORDER BY a.created_at DESC
            LIMIT 50
        ");
        $stmt->execute();
    } else {
        // When filtering by specific school year, include announcements that have matching school year OR have no activity (general announcements)
        $stmt = $pdo->prepare("
            SELECT a.*, u.email as author_email, act.activity_name, act.school_year, act.competition_level
            FROM announcement a
            JOIN \"User\" u ON a.author_id = u.id
            LEFT JOIN activity act ON a.activity_id = act.id
            WHERE a.is_published = true 
            AND (act.school_year = ? OR a.activity_id IS NULL)
            ORDER BY a.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$school_year_filter]);
    }
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process images for each announcement AND collect all images for hero slider
    foreach ($announcements as &$announcement) {
        if (!empty($announcement['image_path'])) {
            $decoded = json_decode($announcement['image_path'], true);
            if (is_array($decoded)) {
                $announcement['images'] = $decoded;
                $announcement['image_count'] = count($decoded);
                
                // Add each image to the hero slider collection with metadata
                foreach ($decoded as $img_path) {
                    if (file_exists($img_path)) {
                        $allAnnouncementImages[] = [
                            'image' => $img_path,
                            'title' => $announcement['title'],
                            'admin_type' => $announcement['admin_type'],
                            'announcement_id' => $announcement['id']
                        ];
                    }
                }
            } else {
                $announcement['images'] = [$announcement['image_path']];
                $announcement['image_count'] = 1;
                if (file_exists($announcement['image_path'])) {
                    $allAnnouncementImages[] = [
                        'image' => $announcement['image_path'],
                        'title' => $announcement['title'],
                        'admin_type' => $announcement['admin_type'],
                        'announcement_id' => $announcement['id']
                    ];
                }
            }
        } else {
            $announcement['images'] = [];
            $announcement['image_count'] = 0;
        }
    }
    
} catch(PDOException $e) {
    $announcements = [];
    $availableSchoolYears = [];
    $allAnnouncementImages = [];
}

// Keep the school_year_filter for the select dropdown
$school_year_filter = isset($_GET['school_year']) ? $_GET['school_year'] : 'all';

// Get statistics
$stats = [
    'total_students' => 0,
    'total_awards' => 0,
    'total_activities' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM student");
    $stats['total_students'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM participation WHERE ranking IS NOT NULL AND ranking != ''");
    $stats['total_awards'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM activity");
    $stats['total_activities'] = $stmt->fetch()['count'];
} catch(PDOException $e) {
    // Keep default zeros
}

// If no images found, use placeholder images
if (empty($allAnnouncementImages)) {
    $allAnnouncementImages = [
        ['image' => 'includes/uploads/images/bisu_logo.png', 'title' => 'BISU Candijay', 'admin_type' => 'cultural'],
        ['image' => 'includes/uploads/images/bisu_logo.png', 'title' => 'BISU Sports', 'admin_type' => 'sports']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BISU Candijay - Athletes & Arts Performers Registry | Home</title>
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #003366;
            --primary-gold: #ffd700;
            --sports-green: #27ae60;
            --cultural-purple: #9b59b6;
            --dark-bg: #0a1a2f;
            --light-gray: #f8f9fa;
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
            overflow-x: hidden;
        }

        /* Hero Section with Split Layout */
        .hero {
            position: relative;
            min-height: 100vh;
            background: linear-gradient(135deg, #0a1a2f 0%, #1a3a5c 50%, #0a1a2f 100%);
            overflow: hidden;
        }
        
        /* Animated Background Elements */
        .hero-bg-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.08;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            background-repeat: repeat;
        }
        
        /* Floating Circles Animation */
        .floating-circle {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255,215,0,0.1), rgba(255,215,0,0.05));
            animation: float 20s infinite ease-in-out;
            pointer-events: none;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0) rotate(0deg); }
            25% { transform: translateY(-3%) translateX(2%) rotate(90deg); }
            50% { transform: translateY(-6%) translateX(-2%) rotate(180deg); }
            75% { transform: translateY(-3%) translateX(3%) rotate(270deg); }
        }
        
        .floating-circle:nth-child(1) { width: 300px; height: 300px; top: -100px; right: -100px; animation-duration: 25s; }
        .floating-circle:nth-child(2) { width: 200px; height: 200px; bottom: 20%; left: -80px; animation-duration: 20s; animation-delay: -5s; }
        .floating-circle:nth-child(3) { width: 150px; height: 150px; bottom: 40%; right: 10%; animation-duration: 18s; animation-delay: -10s; }
        .floating-circle:nth-child(4) { width: 100px; height: 100px; top: 30%; left: 15%; animation-duration: 15s; animation-delay: -3s; }

        /* Navigation */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5%;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            position: relative;
            z-index: 100;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--primary-blue) 0%, #003366 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            animation: pulse 2s infinite;
            overflow: hidden;
        }
        
        .logo-icon img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            50% { transform: scale(1.05); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
        }

        .logo-text h2 {
            color: var(--primary-blue);
            font-size: 1.3rem;
            line-height: 1.3;
        }

        .logo-text span {
            font-size: 0.85rem;
            font-weight: normal;
            color: #666;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-link {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary-blue);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-blue);
            transition: width 0.3s;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .btn-login, .btn-register {
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 600;
        }

        .btn-login {
            color: var(--primary-blue);
            border: 2px solid var(--primary-blue);
            background: transparent;
        }

        .btn-login:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,51,102,0.3);
        }

        .btn-register {
            background: linear-gradient(135deg, var(--primary-blue), #1a4d8c);
            color: white;
            border: none;
        }

        .btn-register:hover {
            background: linear-gradient(135deg, #002244, var(--primary-blue));
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,51,102,0.3);
        }

        /* Split Hero Section - Left Text + Right Slider */
        .hero-split-container {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            min-height: calc(100vh - 80px);
            padding: 2rem 5%;
            gap: 3rem;
            flex-wrap: wrap;
        }
        
        /* Left Side - Text Content */
        .hero-left {
            flex: 1;
            min-width: 300px;
            color: white;
            animation: fadeInUp 0.8s ease;
        }
        
        .hero-left h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 800;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .hero-left h1 .highlight {
            color: var(--primary-gold);
        }
        
        .hero-left p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
            opacity: 0.95;
        }
        
        .hero-stats {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .hero-stat {
            text-align: center;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 1rem 1.5rem;
            border-radius: 15px;
            transition: all 0.3s;
            min-width: 130px;
        }
        
        .hero-stat:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-5px);
        }
        
        .hero-stat .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.3rem;
        }
        
        .hero-stat .stat-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        /* Right Side - Image Slider */
        .hero-right {
            flex: 1;
            min-width: 400px;
            animation: fadeInUp 0.8s ease 0.2s backwards;
        }
        
        .hero-slider-container {
            position: relative;
            width: 100%;
            background: rgba(0,0,0,0.3);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            backdrop-filter: blur(5px);
        }
        
        .hero-slider {
            position: relative;
            width: 100%;
            padding-bottom: 75%; /* 4:3 Aspect Ratio */
            overflow: hidden;
        }
        
        .hero-slider-track {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .hero-slide {
            min-width: 100%;
            height: 100%;
            position: relative;
        }
        
        .hero-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .hero-slide-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            padding: 1.5rem;
            color: white;
        }
        
        .hero-slide-overlay h4 {
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
            opacity: 0.9;
        }
        
        .hero-slide-overlay p {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        .slide-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            color: white;
            z-index: 5;
        }
        
        .slide-badge.sports { background: linear-gradient(135deg, var(--sports-green), #1e8449); }
        .slide-badge.cultural { background: linear-gradient(135deg, var(--cultural-purple), #7d3c98); }
        
        /* Slider Navigation */
        .slider-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .slider-btn:hover {
            background: rgba(0,0,0,0.8);
            transform: translateY(-50%) scale(1.1);
        }
        
        .slider-btn.prev { left: 15px; }
        .slider-btn.next { right: 15px; }
        
        .slider-dots {
            position: absolute;
            bottom: 15px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 8px;
            z-index: 10;
        }
        
        .slider-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .slider-dot.active {
            background: var(--primary-gold);
            width: 20px;
            border-radius: 4px;
        }
        
        .slide-count {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(0,0,0,0.6);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            color: white;
            z-index: 10;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Announcements Section */
        .announcements-section {
            padding: 5rem 5%;
            background: linear-gradient(135deg, var(--light-gray) 0%, #e9ecef 100%);
        }

        .announcements-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .announcements-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .announcements-header h2 {
            color: var(--primary-blue);
            font-size: 2.2rem;
            position: relative;
            display: inline-block;
        }

        .announcements-header h2:after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 70px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-gold), #ffb347);
            border-radius: 2px;
        }

        .filter-bar {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--primary-blue);
        }

        .filter-group select {
            padding: 0.6rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-blue);
        }

        .current-school-year {
            background: linear-gradient(135deg, #e8f4f8, #d4eaf0);
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
            font-size: 0.85rem;
            color: var(--primary-blue);
            font-weight: 500;
            display: inline-block;
        }

        .announcements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 2rem;
        }

        .announcement-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .announcement-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }

        .announcement-gallery {
            position: relative;
            width: 100%;
            height: 240px;
            overflow: hidden;
            background: #f0f0f0;
        }

        .gallery-slider {
            display: flex;
            transition: transform 0.5s ease-in-out;
            height: 100%;
        }

        .gallery-slide {
            min-width: 100%;
            height: 100%;
            flex-shrink: 0;
        }

        .gallery-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .gallery-slide img:hover {
            transform: scale(1.05);
        }

        .gallery-nav {
            position: absolute;
            bottom: 15px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 8px;
            z-index: 10;
        }

        .gallery-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.6);
            cursor: pointer;
            transition: all 0.3s;
        }

        .gallery-dot.active {
            background: var(--primary-gold);
            width: 20px;
            border-radius: 4px;
        }

        .gallery-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .gallery-arrow:hover {
            background: rgba(0,0,0,0.8);
            transform: translateY(-50%) scale(1.1);
        }

        .gallery-arrow.prev { left: 10px; }
        .gallery-arrow.next { right: 10px; }
        
        .image-count-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            z-index: 10;
            backdrop-filter: blur(5px);
        }

        .announcement-image-placeholder {
            width: 100%;
            height: 240px;
            background: linear-gradient(135deg, var(--primary-blue) 0%, #1a4d8c 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .announcement-image-placeholder i {
            font-size: 4rem;
            opacity: 0.6;
        }

        .announcement-content {
            padding: 1.5rem;
        }

        .announcement-title {
            color: var(--primary-blue);
            font-size: 1.2rem;
            margin-bottom: 0.75rem;
            font-weight: 700;
        }

        .announcement-text {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .announcement-meta {
            color: #999;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            padding-top: 0.75rem;
            border-top: 1px solid #e5e7eb;
        }

        .school-year-badge {
            display: inline-block;
            background: #e9ecef;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            margin-bottom: 0.5rem;
        }

        .activity-badge {
            display: inline-block;
            background: #e8d5f0;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            margin-bottom: 0.5rem;
            margin-left: 0.5rem;
        }

        .admin-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            color: white;
        }

        .admin-badge.sports { background: linear-gradient(135deg, var(--sports-green), #1e8449); }
        .admin-badge.cultural { background: linear-gradient(135deg, var(--cultural-purple), #7d3c98); }

        .view-more {
            background: none;
            border: none;
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            padding: 0;
            margin-top: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .view-more:hover {
            color: var(--primary-gold);
            gap: 12px;
        }

        .excellence-section {
            padding: 5rem 5%;
            background: white;
            text-align: center;
        }

        .excellence-content {
            max-width: 800px;
            margin: 0 auto;
        }

        .excellence-content h2 {
            color: var(--primary-blue);
            font-size: 2.2rem;
            margin-bottom: 1rem;
            position: relative;
            display: inline-block;
        }

        .excellence-content h2:after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 70px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-gold), #ffb347);
            border-radius: 2px;
        }

        .excellence-content p {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.7;
            margin-top: 1.5rem;
        }

        .feature-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .feature-card {
            background: var(--light-gray);
            border-radius: 20px;
            padding: 2rem;
            transition: all 0.3s;
            text-align: center;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-blue), #1a4d8c);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
        }

        .feature-icon i {
            font-size: 30px;
            color: var(--primary-gold);
        }

        .feature-card h3 {
            color: var(--primary-blue);
            margin-bottom: 1rem;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
        }

        .feature-card.sports .feature-icon {
            background: linear-gradient(135deg, var(--sports-green), #1e8449);
        }

        .feature-card.cultural .feature-icon {
            background: linear-gradient(135deg, var(--cultural-purple), #7d3c98);
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            max-width: 850px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-header {
            padding: 1.5rem 1.5rem 0 1.5rem;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            border-radius: 20px 20px 0 0;
        }

        .modal-close {
            float: right;
            background: #dc3545;
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
        }

        .modal-close:hover {
            transform: scale(1.1);
        }

        .modal-body {
            padding: 0 1.5rem 1.5rem 1.5rem;
            clear: both;
        }

        .modal-gallery {
            position: relative;
            width: 100%;
            height: 350px;
            overflow: hidden;
            background: #f0f0f0;
            border-radius: 15px;
            margin-bottom: 1.5rem;
        }

        .modal-gallery-slider {
            display: flex;
            transition: transform 0.5s ease;
            height: 100%;
        }

        .modal-gallery-slide {
            min-width: 100%;
            height: 100%;
            flex-shrink: 0;
        }

        .modal-gallery-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
        }

        .no-announcements {
            text-align: center;
            padding: 4rem;
            background: white;
            border-radius: 20px;
        }

        .no-announcements i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 1rem;
        }

        .mobile-menu-btn {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--primary-blue);
        }

        @media (max-width: 968px) {
            .hero-split-container {
                flex-direction: column;
                text-align: center;
                gap: 2rem;
                padding: 2rem;
            }
            
            .hero-left {
                text-align: center;
            }
            
            .hero-stats {
                justify-content: center;
            }
            
            .hero-buttons {
                justify-content: center;
            }
            
            .hero-right {
                min-width: 280px;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                flex-direction: column;
                padding: 1rem;
                gap: 1rem;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .nav-links.show {
                display: flex;
            }
            
            .hero-left h1 {
                font-size: 2rem;
            }
            
            .hero-left p {
                font-size: 1rem;
            }
            
            .hero-stats {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }
            
            .hero-stat {
                width: 80%;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-primary, .btn-secondary {
                width: 100%;
                max-width: 250px;
                text-align: center;
            }
            
            .announcements-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                justify-content: space-between;
            }
            
            .modal-gallery {
                height: 250px;
            }
            
            .feature-cards {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .hero-left h1 {
                font-size: 1.5rem;
            }
            
            .hero-stat .stat-number {
                font-size: 1.5rem;
            }
            
            .excellence-content h2 {
                font-size: 1.5rem;
            }
            
            .logo-icon {
                width: 40px;
                height: 40px;
            }
            
            .logo-text h2 {
                font-size: 1rem;
            }
            
            .logo-text span {
                font-size: 0.7rem;
            }
            
            .hero-right {
                min-width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="hero">
        <!-- Animated Background Elements -->
        <div class="hero-bg-pattern"></div>
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
        
        <nav>
            <div class="logo">
                <div class="logo-icon">
                    <img src="includes/uploads/images/bisu_logo.png" alt="BISU Logo">
                </div>
                <div class="logo-text">
                    <h2>Bohol Island State University<br><span>Candijay Campus</span></h2>
                </div>
            </div>
            <div class="nav-links">
                <a href="index.php" class="nav-link active">Home</a>
                <a href="#announcements" class="nav-link">Announcements</a>
                <a href="#features" class="nav-link">Features</a>
                <a href="login.php" class="btn-login">Login</a>
                <a href="register.php" class="btn-register">Register</a>
            </div>
            <div class="mobile-menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i></div>
        </nav>

        <!-- Split Hero Section: Left Text + Right Slider -->
        <div class="hero-split-container">
            <!-- Left Side - Text Content -->
            <div class="hero-left">
                <h1>Athletes & <span class="highlight">Arts Performers</span></h1>
                <p>Celebrating Excellence in Sports and Arts. Track and showcase the outstanding achievements of BISU Candijay's athletes and cultural performers with our comprehensive registry system.</p>
                
                <div class="hero-stats">
                    <div class="hero-stat">
                        <div class="stat-number"><?php echo number_format($stats['total_students']); ?>+</div>
                        <div class="stat-label">Registered Athletes & Artists</div>
                    </div>
                    <div class="hero-stat">
                        <div class="stat-number"><?php echo number_format($stats['total_awards']); ?>+</div>
                        <div class="stat-label">Awards & Recognitions</div>
                    </div>
                    <div class="hero-stat">
                        <div class="stat-number"><?php echo number_format($stats['total_activities']); ?>+</div>
                        <div class="stat-label">Competitions & Events</div>
                    </div>
                </div>
                
                <div class="hero-buttons">
                    <a href="login.php" class="btn-primary"><i class="fas fa-sign-in-alt"></i> Login to Registry</a>
                    <a href="register.php" class="btn-secondary"><i class="fas fa-user-plus"></i> Create Account</a>
                </div>
            </div>
            
            <!-- Right Side - Image Slider (All Announcement Images) -->
            <div class="hero-right">
                <div class="hero-slider-container">
                    <div class="hero-slider" id="heroSlider">
                        <div class="hero-slider-track" id="heroSliderTrack">
                            <?php foreach ($allAnnouncementImages as $index => $imageData): ?>
                                <div class="hero-slide">
                                    <img src="<?php echo htmlspecialchars($imageData['image']); ?>" alt="Announcement Image">
                                    <div class="slide-badge <?php echo $imageData['admin_type']; ?>">
                                        <i class="fas <?php echo $imageData['admin_type'] == 'sports' ? 'fa-futbol' : 'fa-palette'; ?>"></i>
                                        <?php echo $imageData['admin_type'] == 'sports' ? 'Sports' : 'Cultural'; ?>
                                    </div>
                                    <div class="hero-slide-overlay">
                                        <h4><?php echo htmlspecialchars(substr($imageData['title'], 0, 50)); ?></h4>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($allAnnouncementImages) > 1): ?>
                            <button class="slider-btn prev" onclick="prevHeroSlide()">❮</button>
                            <button class="slider-btn next" onclick="nextHeroSlide()">❯</button>
                            <div class="slider-dots" id="heroSliderDots">
                                <?php for ($i = 0; $i < count($allAnnouncementImages); $i++): ?>
                                    <div class="slider-dot <?php echo $i == 0 ? 'active' : ''; ?>" onclick="goToHeroSlide(<?php echo $i; ?>)"></div>
                                <?php endfor; ?>
                            </div>
                            <div class="slide-count">
                                <span id="currentSlideNum">1</span> / <span id="totalSlidesNum"><?php echo count($allAnnouncementImages); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="announcements-section" id="announcements">
        <div class="announcements-container">
            <div class="announcements-header">
                <h2><i class="fas fa-bullhorn"></i> Latest Announcements</h2>
            </div>
            
            <div class="filter-bar">
                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt"></i> Filter by School Year:</label>
                    <select id="schoolYearFilter" onchange="filterBySchoolYear()">
                        <option value="all" <?php echo $school_year_filter == 'all' ? 'selected' : ''; ?>>All School Years</option>
                        <?php foreach($availableSchoolYears as $sy): ?>
                            <option value="<?php echo htmlspecialchars($sy['school_year']); ?>" <?php echo $school_year_filter == $sy['school_year'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sy['school_year']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="current-school-year">
                    <i class="fas fa-graduation-cap"></i> Current: <?php echo getCurrentSchoolYear(); ?>
                </div>
            </div>
            
            <?php if(count($announcements) > 0): ?>
                <div class="announcements-grid" id="announcementsGrid">
                    <?php foreach($announcements as $index => $announcement): ?>
                        <?php 
                        $images = $announcement['images'] ?? [];
                        $imageCount = $announcement['image_count'] ?? 0;
                        $galleryId = 'gallery_' . $announcement['id'] . '_' . $index;
                        $adminTypeClass = $announcement['admin_type'] == 'sports' ? 'sports' : 'cultural';
                        $adminTypeName = $announcement['admin_type'] == 'sports' ? 'Sports Admin' : 'Cultural Admin';
                        ?>
                        <div class="announcement-card" data-id="<?php echo $announcement['id']; ?>">
                            <?php if($imageCount > 0): ?>
                                <div class="announcement-gallery" id="<?php echo $galleryId; ?>">
                                    <div class="gallery-slider" id="<?php echo $galleryId; ?>_slider">
                                        <?php foreach($images as $img_index => $img_path): ?>
                                            <div class="gallery-slide">
                                                <img src="<?php echo htmlspecialchars($img_path); ?>" alt="Image <?php echo $img_index + 1; ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if($imageCount > 1): ?>
                                        <button class="gallery-arrow prev" onclick="event.stopPropagation(); prevSlide('<?php echo $galleryId; ?>')">❮</button>
                                        <button class="gallery-arrow next" onclick="event.stopPropagation(); nextSlide('<?php echo $galleryId; ?>')">❯</button>
                                        <div class="gallery-nav" id="<?php echo $galleryId; ?>_nav">
                                            <?php for($i = 0; $i < $imageCount; $i++): ?>
                                                <div class="gallery-dot <?php echo $i == 0 ? 'active' : ''; ?>" onclick="event.stopPropagation(); goToSlide('<?php echo $galleryId; ?>', <?php echo $i; ?>)"></div>
                                            <?php endfor; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="image-count-badge"><i class="fas fa-images"></i> <?php echo $imageCount; ?></div>
                                </div>
                            <?php else: ?>
                                <div class="announcement-image-placeholder"><i class="fas fa-newspaper"></i></div>
                            <?php endif; ?>
                            
                            <div class="announcement-content">
                                <h3 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                <div>
                                    <?php if(!empty($announcement['school_year'])): ?>
                                        <span class="school-year-badge"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($announcement['school_year']); ?></span>
                                    <?php endif; ?>
                                    <?php if(!empty($announcement['activity_name'])): ?>
                                        <span class="activity-badge"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($announcement['activity_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="announcement-text">
                                    <?php 
                                    $shortContent = strlen($announcement['content']) > 150 ? substr($announcement['content'], 0, 150) . '...' : $announcement['content'];
                                    echo nl2br(htmlspecialchars($shortContent)); 
                                    ?>
                                </div>
                                <div class="announcement-meta">
                                    <span>
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($announcement['author_email']); ?>
                                        <span class="admin-badge <?php echo $adminTypeClass; ?>"><?php echo $adminTypeName; ?></span>
                                    </span>
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></span>
                                </div>
                                <button class="view-more" onclick='showFullAnnouncement(<?php echo json_encode($announcement); ?>)'>
                                    Read More <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-announcements">
                    <i class="fas fa-bullhorn"></i>
                    <p>No announcements found.</p>
                    <?php if($school_year_filter != 'all'): ?>
                        <a href="?school_year=all#announcements" style="display: inline-block; margin-top: 1rem; color: var(--primary-blue); text-decoration: none;">
                            <i class="fas fa-eye"></i> View all announcements
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="excellence-section" id="features">
        <div class="excellence-content">
            <h2>Celebrating Excellence</h2>
            <p>BISU Candijay takes pride in nurturing talents and celebrating achievements in both sports and cultural arts. Our students excel in various competitions, bringing honor to the university.</p>
            
            <div class="feature-cards">
                <div class="feature-card sports">
                    <div class="feature-icon"><i class="fas fa-futbol"></i></div>
                    <h3>Sports Registry</h3>
                    <p>Track athletes, teams, tournaments, and achievements across various sports disciplines including basketball, volleyball, athletics, and more.</p>
                </div>
                <div class="feature-card cultural">
                    <div class="feature-icon"><i class="fas fa-palette"></i></div>
                    <h3>Cultural Arts Registry</h3>
                    <p>Showcase cultural performers, dance troupes, choirs, visual artists, and literary talents who bring pride to our university.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Achievement Analytics</h3>
                    <p>Comprehensive analytics and reports to monitor performance, track awards, and celebrate successes across all categories.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for full announcement -->
    <div id="announcementModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
                <div style="clear: both;"></div>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Dynamic content will be inserted here -->
            </div>
        </div>
    </div>

    <script>
        // Navigation
        function toggleMenu() {
            document.querySelector('.nav-links').classList.toggle('show');
        }
        
        function filterBySchoolYear() {
            const schoolYear = document.getElementById('schoolYearFilter').value;
            window.location.href = 'index.php?school_year=' + encodeURIComponent(schoolYear) + '#announcements';
        }
        
        // Hero Slider Variables
        let heroCurrentIndex = 0;
        let heroTotalSlides = <?php echo count($allAnnouncementImages); ?>;
        let heroAutoPlayInterval = null;
        
        function updateHeroSlider() {
            const track = document.getElementById('heroSliderTrack');
            if (track) {
                track.style.transform = `translateX(-${heroCurrentIndex * 100}%)`;
            }
            
            // Update dots
            const dots = document.querySelectorAll('#heroSliderDots .slider-dot');
            dots.forEach((dot, idx) => {
                if (idx === heroCurrentIndex) {
                    dot.classList.add('active');
                } else {
                    dot.classList.remove('active');
                }
            });
            
            // Update slide count
            const currentNumSpan = document.getElementById('currentSlideNum');
            if (currentNumSpan) {
                currentNumSpan.textContent = heroCurrentIndex + 1;
            }
        }
        
        function nextHeroSlide() {
            if (heroCurrentIndex < heroTotalSlides - 1) {
                heroCurrentIndex++;
                updateHeroSlider();
                resetHeroAutoPlay();
            } else {
                // Loop back to first slide
                heroCurrentIndex = 0;
                updateHeroSlider();
                resetHeroAutoPlay();
            }
        }
        
        function prevHeroSlide() {
            if (heroCurrentIndex > 0) {
                heroCurrentIndex--;
                updateHeroSlider();
                resetHeroAutoPlay();
            } else {
                // Loop to last slide
                heroCurrentIndex = heroTotalSlides - 1;
                updateHeroSlider();
                resetHeroAutoPlay();
            }
        }
        
        function goToHeroSlide(index) {
            if (index >= 0 && index < heroTotalSlides) {
                heroCurrentIndex = index;
                updateHeroSlider();
                resetHeroAutoPlay();
            }
        }
        
        function startHeroAutoPlay() {
            if (heroTotalSlides <= 1) return;
            if (heroAutoPlayInterval) clearInterval(heroAutoPlayInterval);
            heroAutoPlayInterval = setInterval(() => {
                nextHeroSlide();
            }, 5000);
        }
        
        function resetHeroAutoPlay() {
            if (heroTotalSlides <= 1) return;
            if (heroAutoPlayInterval) clearInterval(heroAutoPlayInterval);
            heroAutoPlayInterval = setInterval(() => {
                nextHeroSlide();
            }, 5000);
        }
        
        function stopHeroAutoPlay() {
            if (heroAutoPlayInterval) {
                clearInterval(heroAutoPlayInterval);
                heroAutoPlayInterval = null;
            }
        }
        
        // Gallery functions for announcement cards
        let galleryStates = {};
        
        function initGallery(galleryId) {
            const slider = document.getElementById(`${galleryId}_slider`);
            const slides = slider ? slider.querySelectorAll('.gallery-slide') : [];
            const totalSlides = slides.length;
            
            if (totalSlides === 0) return;
            
            galleryStates[galleryId] = {
                currentIndex: 0,
                totalSlides: totalSlides,
                slider: slider,
                dots: document.querySelectorAll(`#${galleryId}_nav .gallery-dot`),
                autoPlayInterval: null
            };
            
            startGalleryAutoPlay(galleryId);
            
            const gallery = document.getElementById(galleryId);
            if (gallery) {
                gallery.addEventListener('mouseenter', () => pauseGalleryAutoPlay(galleryId));
                gallery.addEventListener('mouseleave', () => startGalleryAutoPlay(galleryId));
            }
        }
        
        function startGalleryAutoPlay(galleryId) {
            const state = galleryStates[galleryId];
            if (!state || state.totalSlides <= 1) return;
            
            if (state.autoPlayInterval) clearInterval(state.autoPlayInterval);
            state.autoPlayInterval = setInterval(() => {
                const newIndex = (state.currentIndex + 1) % state.totalSlides;
                goToSlide(galleryId, newIndex);
            }, 5000);
        }
        
        function pauseGalleryAutoPlay(galleryId) {
            const state = galleryStates[galleryId];
            if (state && state.autoPlayInterval) {
                clearInterval(state.autoPlayInterval);
                state.autoPlayInterval = null;
            }
        }
        
        function updateSlider(galleryId) {
            const state = galleryStates[galleryId];
            if (!state || !state.slider) return;
            
            state.slider.style.transform = `translateX(-${state.currentIndex * 100}%)`;
            
            if (state.dots) {
                state.dots.forEach((dot, idx) => {
                    if (idx === state.currentIndex) {
                        dot.classList.add('active');
                    } else {
                        dot.classList.remove('active');
                    }
                });
            }
        }
        
        function nextSlide(galleryId) {
            const state = galleryStates[galleryId];
            if (!state) return;
            
            if (state.currentIndex < state.totalSlides - 1) {
                state.currentIndex++;
                updateSlider(galleryId);
                resetGalleryAutoPlay(galleryId);
            }
        }
        
        function prevSlide(galleryId) {
            const state = galleryStates[galleryId];
            if (!state) return;
            
            if (state.currentIndex > 0) {
                state.currentIndex--;
                updateSlider(galleryId);
                resetGalleryAutoPlay(galleryId);
            }
        }
        
        function goToSlide(galleryId, index) {
            const state = galleryStates[galleryId];
            if (!state) return;
            
            if (index >= 0 && index < state.totalSlides) {
                state.currentIndex = index;
                updateSlider(galleryId);
                resetGalleryAutoPlay(galleryId);
            }
        }
        
        function resetGalleryAutoPlay(galleryId) {
            const state = galleryStates[galleryId];
            if (!state || state.totalSlides <= 1) return;
            
            if (state.autoPlayInterval) clearInterval(state.autoPlayInterval);
            state.autoPlayInterval = setInterval(() => {
                const newIndex = (state.currentIndex + 1) % state.totalSlides;
                goToSlide(galleryId, newIndex);
            }, 5000);
        }
        
        // Initialize all card galleries
        function initAllGalleries() {
            const galleries = document.querySelectorAll('.announcement-gallery');
            galleries.forEach(gallery => {
                const slider = gallery.querySelector('.gallery-slider');
                const slides = slider ? slider.querySelectorAll('.gallery-slide') : [];
                if (slides.length > 0) {
                    initGallery(gallery.id);
                }
            });
        }
        
        // Hero slider event listeners for pause on hover
        const heroSliderContainer = document.querySelector('.hero-slider-container');
        if (heroSliderContainer) {
            heroSliderContainer.addEventListener('mouseenter', () => stopHeroAutoPlay());
            heroSliderContainer.addEventListener('mouseleave', () => startHeroAutoPlay());
        }
        
        // Full announcement modal functions
        let modalTotalSlides = 0;
        let modalCurrentIndex = 0;
        let modalSlider = null;
        let modalAutoPlayInterval = null;
        
        function showFullAnnouncement(announcement) {
            const modal = document.getElementById('announcementModal');
            const modalBody = document.getElementById('modalBody');
            
            const images = announcement.images || [];
            const adminTypeClass = announcement.admin_type == 'sports' ? 'sports' : 'cultural';
            const adminTypeName = announcement.admin_type == 'sports' ? 'Sports Admin' : 'Cultural Admin';
            
            let galleryHtml = '';
            const modalGalleryId = 'modalGallery_' + announcement.id;
            
            if (images.length > 0) {
                galleryHtml = `
                    <div class="modal-gallery" id="${modalGalleryId}">
                        <div class="modal-gallery-slider" id="${modalGalleryId}_slider">
                            ${images.map((img, idx) => `
                                <div class="modal-gallery-slide">
                                    <img src="${img}" alt="Image ${idx + 1}">
                                </div>
                            `).join('')}
                        </div>
                        ${images.length > 1 ? `
                            <button class="gallery-arrow prev" style="left: 10px;" onclick="event.stopPropagation(); prevModalSlide()">❮</button>
                            <button class="gallery-arrow next" style="right: 10px;" onclick="event.stopPropagation(); nextModalSlide()">❯</button>
                            <div class="gallery-nav" id="${modalGalleryId}_nav">
                                ${images.map((_, idx) => `<div class="gallery-dot ${idx === 0 ? 'active' : ''}" onclick="event.stopPropagation(); goToModalSlide(${idx})"></div>`).join('')}
                            </div>
                        ` : ''}
                        <div class="image-count-badge"><i class="fas fa-images"></i> ${images.length}</div>
                    </div>
                `;
            } else {
                galleryHtml = `<div style="background: linear-gradient(135deg, var(--primary-blue) 0%, #1a4d8c 100%); padding: 2rem; text-align: center; border-radius: 15px; margin-bottom: 1rem;">
                                    <i class="fas fa-newspaper" style="font-size: 3rem; color: white; opacity: 0.7;"></i>
                                </div>`;
            }
            
            const formattedDate = new Date(announcement.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            
            let schoolYearHtml = '';
            if (announcement.school_year && announcement.school_year !== 'null' && announcement.school_year !== '') {
                schoolYearHtml = `<div class="school-year-badge" style="display: inline-block; margin-right: 10px;"><i class="fas fa-calendar-alt"></i> ${announcement.school_year}</div>`;
            }
            
            let activityHtml = '';
            if (announcement.activity_name && announcement.activity_name !== '') {
                activityHtml = `<div class="activity-badge" style="display: inline-block;"><i class="fas fa-tag"></i> ${escapeHtml(announcement.activity_name)}</div>`;
            }
            
            modalBody.innerHTML = `
                ${galleryHtml}
                <div style="margin-bottom: 1rem;">
                    ${schoolYearHtml}
                    ${activityHtml}
                    <span class="admin-badge ${adminTypeClass}" style="margin-left: 10px;">${adminTypeName}</span>
                </div>
                <h2 style="color: var(--primary-blue); margin-bottom: 1rem;">${escapeHtml(announcement.title)}</h2>
                <div style="color: #999; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e5e7eb;">
                    <span><i class="fas fa-user"></i> ${escapeHtml(announcement.author_email)}</span>
                    <span style="margin-left: 1rem;"><i class="fas fa-calendar-alt"></i> ${formattedDate}</span>
                </div>
                <div style="color: #333; line-height: 1.8; white-space: pre-wrap; font-size: 1rem;">${escapeHtml(announcement.content).replace(/\n/g, '<br>')}</div>
            `;
            
            if (images.length > 1) {
                setTimeout(() => {
                    initModalGallery(images.length);
                    startModalAutoPlay();
                }, 100);
            } else if (images.length === 1) {
                setTimeout(() => {
                    initModalGallery(1);
                }, 100);
            }
            
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function startModalAutoPlay() {
            if (modalAutoPlayInterval) clearInterval(modalAutoPlayInterval);
            if (modalTotalSlides > 1) {
                modalAutoPlayInterval = setInterval(() => {
                    const newIndex = (modalCurrentIndex + 1) % modalTotalSlides;
                    goToModalSlide(newIndex);
                }, 5000);
            }
        }
        
        function stopModalAutoPlay() {
            if (modalAutoPlayInterval) {
                clearInterval(modalAutoPlayInterval);
                modalAutoPlayInterval = null;
            }
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function initModalGallery(totalSlides) {
            modalTotalSlides = totalSlides;
            modalCurrentIndex = 0;
            modalSlider = document.querySelector('#announcementModal .modal-gallery-slider');
            
            if (modalSlider) {
                updateModalGallery();
            }
            
            const modalGallery = document.querySelector('#announcementModal .modal-gallery');
            if (modalGallery && totalSlides > 1) {
                modalGallery.addEventListener('mouseenter', () => stopModalAutoPlay());
                modalGallery.addEventListener('mouseleave', () => startModalAutoPlay());
            }
        }
        
        function updateModalGallery() {
            if (modalSlider) {
                modalSlider.style.transform = `translateX(-${modalCurrentIndex * 100}%)`;
            }
            
            const dots = document.querySelectorAll('#announcementModal .modal-gallery-nav .gallery-dot');
            dots.forEach((dot, idx) => {
                if (idx === modalCurrentIndex) {
                    dot.classList.add('active');
                } else {
                    dot.classList.remove('active');
                }
            });
        }
        
        function prevModalSlide() {
            if (modalCurrentIndex > 0) {
                modalCurrentIndex--;
                updateModalGallery();
                stopModalAutoPlay();
                startModalAutoPlay();
            }
        }
        
        function nextModalSlide() {
            if (modalCurrentIndex < modalTotalSlides - 1) {
                modalCurrentIndex++;
                updateModalGallery();
                stopModalAutoPlay();
                startModalAutoPlay();
            }
        }
        
        function goToModalSlide(index) {
            modalCurrentIndex = index;
            updateModalGallery();
            stopModalAutoPlay();
            startModalAutoPlay();
        }
        
        function closeModal() {
            document.getElementById('announcementModal').style.display = 'none';
            document.body.style.overflow = '';
            modalTotalSlides = 0;
            modalCurrentIndex = 0;
            modalSlider = null;
            if (modalAutoPlayInterval) {
                clearInterval(modalAutoPlayInterval);
                modalAutoPlayInterval = null;
            }
        }
        
        // Close modal when clicking overlay
        document.getElementById('announcementModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        // Close with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
        
        // Smooth scroll for anchors
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) target.scrollIntoView({ behavior: 'smooth' });
            });
        });
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            initAllGalleries();
            startHeroAutoPlay();
            if (window.location.hash === '#announcements') {
                document.getElementById('announcements').scrollIntoView({ behavior: 'smooth' });
            }
        });
        
        window.addEventListener('load', function() {
            initAllGalleries();
        });
    </script>
</body>
</html>