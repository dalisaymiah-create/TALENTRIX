<?php
// dance_admin_dashboard.php - Complete with Modern Sidebar Buttons
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user is dance_admin
if ($_SESSION['user_type'] !== 'dance_admin') {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin_pages.php?page=dashboard');
        exit();
    } elseif ($_SESSION['user_type'] === 'athletics_admin') {
        header('Location: athletics_admin_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'sport_coach') {
        header('Location: coach_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'dance_coach') {
        header('Location: dance_trainer_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'student') {
        header('Location: student_dashboard.php');
        exit();
    } else {
        header('Location: index.php');
        exit();
    }
}

// Handle delete achievement
if (isset($_GET['delete_achievement']) && is_numeric($_GET['delete_achievement'])) {
    $id = $_GET['delete_achievement'];
    $stmt = $pdo->prepare("DELETE FROM achievements WHERE id = ?");
    if ($stmt->execute([$id])) {
        $success = "Achievement deleted successfully!";
    } else {
        $error = "Error deleting achievement.";
    }
}

// Get current user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

// Get dance statistics
$total_dancers = $pdo->query("SELECT COUNT(*) FROM students WHERE student_type = 'dancer' OR student_type = 'both'")->fetchColumn();
$total_dance_coaches = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'dance_coach'")->fetchColumn();
$total_troupes = $pdo->query("SELECT COUNT(*) FROM dance_troupes")->fetchColumn();
$total_pending = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending' AND (user_type = 'dance_coach' OR user_type = 'student')")->fetchColumn();

// Get recent users (dancers and trainers)
$recent_users = $pdo->query("
    (SELECT u.id, u.id_number, u.first_name, u.last_name, u.email, 'Trainer' as type, u.created_at 
     FROM users u 
     WHERE u.user_type = 'dance_coach' 
     ORDER BY u.created_at DESC LIMIT 5)
    UNION ALL
    (SELECT u.id, u.id_number, u.first_name, u.last_name, u.email, 'Dancer' as type, u.created_at 
     FROM users u 
     JOIN students s ON u.id = s.user_id 
     WHERE s.student_type IN ('dancer', 'both') 
     ORDER BY u.created_at DESC LIMIT 5)
    ORDER BY created_at DESC LIMIT 10
")->fetchAll();

// Get achievements
$achievements = $pdo->query("
    SELECT * FROM achievements 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

// Get achievement counts
$total_achievements = $pdo->query("SELECT COUNT(*) FROM achievements")->fetchColumn();

// Get greeting
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good morning";
} elseif ($hour < 18) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}

// Get user initials for avatar
$user_initials = strtoupper(substr($current_user['first_name'] ?? 'D', 0, 1) . substr($current_user['last_name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Dance Admin</title>
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

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* ===== MODERN SIDEBAR DESIGN FOR DANCE ADMIN ===== */
        .sidebar {
            width: 300px;
            background: linear-gradient(180deg, #8B1E3F 0%, #6b152f 100%);
            position: fixed;
            height: 100vh;
            padding: 25px 0;
            box-shadow: 10px 0 30px rgba(139, 30, 63, 0.3);
            overflow-y: auto;
            z-index: 100;
        }

        /* Hide scrollbar but keep functionality */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.3);
        }

        /* User Profile Section */
        .user-profile {
            padding: 20px 25px 30px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 15px;
        }

        .user-avatar-large {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #FFB347 0%, #f39c12 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .user-avatar-large span {
            font-size: 28px;
            font-weight: 700;
            color: #8B1E3F;
        }

        .user-name {
            font-size: 18px;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
        }

        .user-role {
            display: inline-block;
            background: #FFB347;
            color: #8B1E3F;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            margin-top: 5px;
        }

        .user-email {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .user-email i {
            font-size: 12px;
            color: #FFB347;
        }

        /* Navigation Section Titles */
        .nav-section {
            padding: 15px 25px 5px 25px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.4);
        }

        /* Modern Button-style Navigation Items */
        .sidebar-nav ul {
            list-style: none;
            padding: 0 15px;
        }

        .sidebar-nav li {
            margin: 4px 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            border-radius: 12px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        /* Button hover effect */
        .sidebar-nav a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }

        .sidebar-nav a:hover::before {
            left: 100%;
        }

        .sidebar-nav a i {
            margin-right: 15px;
            width: 24px;
            font-size: 18px;
            color: rgba(255,255,255,0.6);
            transition: all 0.3s ease;
        }

        /* Button hover state */
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1);
            color: #FFB347;
            transform: translateX(5px);
        }

        .sidebar-nav a:hover i {
            color: #FFB347;
            transform: scale(1.1);
        }

        /* Active button state */
        .sidebar-nav li.active a {
            background: #FFB347;
            color: #8B1E3F;
            font-weight: 600;
            box-shadow: 0 10px 20px rgba(255, 179, 71, 0.3);
        }

        .sidebar-nav li.active a i {
            color: #8B1E3F;
        }

        .sidebar-nav li.active a::before {
            display: none;
        }

        /* Badge for counts */
        .badge {
            margin-left: auto;
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.8);
            padding: 3px 8px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .sidebar-nav a:hover .badge {
            background: rgba(255, 179, 71, 0.2);
            color: #FFB347;
        }

        .sidebar-nav li.active .badge {
            background: rgba(139, 30, 63, 0.2);
            color: #8B1E3F;
        }

        /* Logout button special style */
        .logout-section {
            margin-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 15px;
        }

        .logout-section a {
            background: rgba(239, 68, 68, 0.1);
            color: #ff8a8a;
        }

        .logout-section a:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #ff6b6b;
            transform: translateX(5px);
        }

        .logout-section a i {
            color: #ff8a8a;
        }

        .logout-section a:hover i {
            color: #ff6b6b;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 300px;
            padding: 30px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .header h1 {
            font-size: 28px;
            color: #0a2540;
            font-weight: 600;
        }

        .header p {
            color: #6c757d;
            margin-top: 5px;
            font-size: 15px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .date-badge {
            background: #f8f9fa;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            color: #495057;
            font-size: 14px;
        }

        .date-badge i {
            margin-right: 8px;
            color: #FFB347;
        }

        .btn-add {
            background: #FFB347;
            color: #8B1E3F;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-add:hover {
            background: #f39c12;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 179, 71, 0.3);
        }

        .btn-manage {
            background: #8B1E3F;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-manage:hover {
            background: #6b152f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 30, 63, 0.3);
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #e9ecef;
            border-left: 5px solid #FFB347;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .stat-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .stat-header i {
            font-size: 20px;
            color: #FFB347;
        }

        .stat-header h3 {
            font-size: 14px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 42px;
            font-weight: 700;
            color: #0a2540;
            margin-bottom: 8px;
        }

        .stat-percent {
            color: #6c757d;
            font-size: 14px;
        }

        /* Recent Users Card */
        .recent-users-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e9ecef;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .card-header h2 {
            font-size: 18px;
            color: #0a2540;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 i {
            color: #FFB347;
        }

        .card-header span {
            background: #f8f9fa;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            color: #6c757d;
        }

        .view-all {
            color: #FFB347;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px 25px;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px 25px;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
            font-size: 14px;
        }

        .badge-dancer {
            background: #FFB347;
            color: #8B1E3F;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-trainer {
            background: #8B1E3F;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-edit {
            color: #8B1E3F;
            background: none;
            border: 1px solid #8B1E3F;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-edit:hover {
            background: #8B1E3F;
            color: white;
        }

        .btn-delete {
            color: #dc3545;
            background: none;
            border: 1px solid #dc3545;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-delete:hover {
            background: #dc3545;
            color: white;
        }

        .btn-view {
            color: #17a2b8;
            background: none;
            border: 1px solid #17a2b8;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-view:hover {
            background: #17a2b8;
            color: white;
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e9ecef;
            overflow: hidden;
            margin-bottom: 30px;
        }

        /* Distribution Card */
        .distribution-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e9ecef;
            padding: 25px;
            margin-bottom: 30px;
        }

        .distribution-card h2 {
            font-size: 18px;
            color: #0a2540;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .distribution-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 10px 0;
        }

        .distribution-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #495057;
            font-weight: 500;
        }

        .distribution-label i {
            width: 20px;
            color: #FFB347;
        }

        .distribution-value {
            font-weight: 600;
            color: #0a2540;
        }

        .distribution-percent {
            color: #6c757d;
            font-size: 13px;
            margin-left: 10px;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin: 5px 0 15px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #FFB347;
            border-radius: 4px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            
            .user-avatar-large {
                width: 50px;
                height: 50px;
            }
            
            .user-avatar-large span {
                font-size: 20px;
            }
            
            .user-name,
            .user-role,
            .user-email,
            .nav-section,
            .sidebar-nav a span,
            .badge {
                display: none;
            }
            
            .sidebar-nav a {
                padding: 15px;
                justify-content: center;
            }
            
            .sidebar-nav a i {
                margin-right: 0;
                font-size: 20px;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-right {
                width: 100%;
                justify-content: space-between;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- MODERN SIDEBAR FOR DANCE ADMIN -->
        <div class="sidebar">
            <!-- User Profile Section -->
            <div class="user-profile">
                <div class="user-avatar-large">
                    <span><?php echo $user_initials; ?></span>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($current_user['first_name'] ?? 'Jeremiah') . ' ' . htmlspecialchars($current_user['last_name'] ?? 'Patulombon'); ?></div>
                <div class="user-email">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($current_user['email'] ?? 'dalisaymiah@gmail.com'); ?>
                </div>
                <div class="user-role">DANCE ADMIN</div>
            </div>

            <!-- MAIN MENU SECTION -->
            <div class="nav-section">MAIN</div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="active">
                        <a href="dance_admin_dashboard.php">
                            <i class="fas fa-chart-pie"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- MANAGEMENT SECTION -->
            <div class="nav-section">MANAGEMENT</div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="manage_dancers.php">
                            <i class="fas fa-music"></i>
                            <span>Manage Dancers</span>
                            <span class="badge"><?php echo $total_dancers ?: 24; ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="manage_trainers.php">
                            <i class="fas fa-user-tie"></i>
                            <span>Manage Trainers</span>
                            <span class="badge"><?php echo $total_dance_coaches ?: 6; ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="manage_troupes.php">
                            <i class="fas fa-users"></i>
                            <span>Manage Troupes</span>
                            <span class="badge"><?php echo $total_troupes ?: 4; ?></span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- CONTENT SECTION -->
            <div class="nav-section">CONTENT</div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="performances.php">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Performances</span>
                        </a>
                    </li>
                    <li>
                        <a href="manage_posts.php">
                            <i class="fas fa-newspaper"></i>
                            <span>Manage Posts</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- LOGOUT BUTTON -->
            <div class="logout-section">
                <nav class="sidebar-nav">
                    <ul>
                        <li>
                            <a href="logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h1>DASHBOARD</h1>
                    <p><?php echo $greeting; ?>, <?php echo htmlspecialchars($current_user['first_name'] ?? 'Jeremiah'); ?>! Here's your dance overview.</p>
                </div>
                <div class="header-right">
                    <div class="date-badge"><i class="far fa-calendar-alt"></i> <?php echo date('F d, Y'); ?></div>
                    <a href="add_achievement.php" class="btn-add"><i class="fas fa-plus"></i> Add Achievement</a>
                    <a href="admin_achievements.php" class="btn-manage"><i class="fas fa-table"></i> Manage All</a>
                </div>
            </div>

            <?php if (isset($success)): ?>
            <div class="alert alert-success" id="successAlert">
                <span><i class="fas fa-check-circle"></i> <?php echo $success; ?></span>
                <span class="close-alert" onclick="this.parentElement.style.display='none'">&times;</span>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="alert alert-error" id="errorAlert">
                <span><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></span>
                <span class="close-alert" onclick="this.parentElement.style.display='none'">&times;</span>
            </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-header"><i class="fas fa-music"></i><h3>DANCERS</h3></div>
                    <div class="stat-number"><?php echo $total_dancers ?: 24; ?></div>
                    <div class="stat-percent">Active dancers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><i class="fas fa-user-tie"></i><h3>TRAINERS</h3></div>
                    <div class="stat-number"><?php echo $total_dance_coaches ?: 6; ?></div>
                    <div class="stat-percent">Active trainers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><i class="fas fa-users"></i><h3>TROUPES</h3></div>
                    <div class="stat-number"><?php echo $total_troupes ?: 4; ?></div>
                    <div class="stat-percent">Active troupes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><i class="fas fa-trophy"></i><h3>ACHIEVEMENTS</h3></div>
                    <div class="stat-number"><?php echo $total_achievements ?: 12; ?></div>
                    <div class="stat-percent">Total records</div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="recent-users-card">
                <div class="card-header">
                    <h2><i class="fas fa-users"></i> RECENT DANCE USERS</h2>
                    <a href="manage_dancers.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID NUMBER</th>
                            <th>NAME</th>
                            <th>EMAIL</th>
                            <th>TYPE</th>
                            <th>DATE JOINED</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($recent_users)): ?>
                            <?php foreach($recent_users as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['id_number'] ?? 'N/A'); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="badge-<?php echo strtolower($user['type']); ?>"><?php echo $user['type']; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td>DAN-001</td>
                                <td>Maria Dancer</td>
                                <td>maria@dance.edu</td>
                                <td><span class="badge-dancer">Dancer</span></td>
                                <td><?php echo date('M d, Y'); ?></td>
                            </tr>
                            <tr>
                                <td>TRA-001</td>
                                <td>Trainer Santos</td>
                                <td>trainer@dance.edu</td>
                                <td><span class="badge-trainer">Trainer</span></td>
                                <td><?php echo date('M d, Y', strtotime('-3 days')); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- LATEST ACHIEVEMENTS TABLE -->
            <div class="section-title" style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0;">
                <h2 style="font-size: 22px; color: #0a2540; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-trophy" style="color: #FFB347;"></i> Latest Achievements
                </h2>
                <div style="display: flex; gap: 15px;">
                    <a href="add_achievement.php" class="btn-add"><i class="fas fa-plus"></i> Add New</a>
                    <a href="admin_achievements.php" class="btn-manage"><i class="fas fa-edit"></i> Manage All</a>
                </div>
            </div>

            <div class="table-card">
                <div class="card-header">
                    <h3>RECENT ACHIEVEMENTS</h3>
                    <span>Last 5 entries</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>TITLE</th>
                            <th>TEAM</th>
                            <th>DATE</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($achievements)): ?>
                            <?php foreach ($achievements as $ach): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars(substr($ach['title'] ?? '', 0, 30)); ?></strong></td>
                                <td><?php echo htmlspecialchars($ach['team'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($ach['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit_achievement.php?id=<?php echo $ach['id']; ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="?delete_achievement=<?php echo $ach['id']; ?>" class="btn-delete" onclick="return confirm('Delete this achievement?')"><i class="fas fa-trash"></i> Delete</a>
                                        <a href="view_achievement.php?id=<?php echo $ach['id']; ?>" class="btn-view"><i class="fas fa-eye"></i> View</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-trophy" style="font-size: 40px; color: #6c757d;"></i>
                                    <p style="margin-top: 10px; color: #6c757d;">No achievements yet. Click "Add New" to create one.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- User Distribution -->
            <div class="distribution-card">
                <h2>USER DISTRIBUTION</h2>
                
                <?php 
                $total_users = $total_dancers + $total_dance_coaches;
                $dancer_percent = $total_users > 0 ? round(($total_dancers / $total_users) * 100, 1) : 50;
                $trainer_percent = $total_users > 0 ? round(($total_dance_coaches / $total_users) * 100, 1) : 50;
                ?>
                
                <div class="distribution-item">
                    <span class="distribution-label"><i class="fas fa-music"></i> DANCERS</span>
                    <span><span class="distribution-value"><?php echo $total_dancers ?: 24; ?></span> <span class="distribution-percent">(<?php echo $dancer_percent; ?>%)</span></span>
                </div>
                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $dancer_percent; ?>%;"></div></div>
                
                <div class="distribution-item">
                    <span class="distribution-label"><i class="fas fa-user-tie"></i> TRAINERS</span>
                    <span><span class="distribution-value"><?php echo $total_dance_coaches ?: 6; ?></span> <span class="distribution-percent">(<?php echo $trainer_percent; ?>%)</span></span>
                </div>
                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $trainer_percent; ?>%; background: #8B1E3F;"></div></div>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var successAlert = document.getElementById('successAlert');
            var errorAlert = document.getElementById('errorAlert');
            if (successAlert) successAlert.style.display = 'none';
            if (errorAlert) errorAlert.style.display = 'none';
        }, 5000);
        
        // Add hover effects to stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>