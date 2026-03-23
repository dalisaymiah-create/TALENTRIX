<?php
// manage_coaches.php - Manage Coaches with Modern Sidebar
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user is athletics_admin
if ($_SESSION['user_type'] !== 'athletics_admin') {
    // Redirect to correct dashboard based on user type
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin_pages.php?page=dashboard');
        exit();
    } elseif ($_SESSION['user_type'] === 'dance_admin') {
        header('Location: dance_admin_dashboard.php');
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

// Get current user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

// Get user initials for avatar
$user_initials = strtoupper(substr($current_user['first_name'] ?? 'A', 0, 1) . substr($current_user['last_name'] ?? 'A', 0, 1));

// Get athletics statistics for sidebar badges
$total_athletes = $pdo->query("SELECT COUNT(*) FROM students WHERE student_type = 'athlete' OR student_type = 'both'")->fetchColumn();
$total_sport_coaches = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'sport_coach'")->fetchColumn();
$total_teams = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$total_achievements = $pdo->query("SELECT COUNT(*) FROM achievements")->fetchColumn();

// Handle coach deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    // Check if coach has any teams assigned
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE coach_id = ?");
    $stmt->execute([$delete_id]);
    $team_count = $stmt->fetchColumn();
    
    if ($team_count > 0) {
        $error_message = "Cannot delete coach because they are assigned to $team_count team(s). Please reassign the teams first.";
    } else {
        // Delete coach
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = 'sport_coach'");
        if ($stmt->execute([$delete_id])) {
            $success_message = "Coach deleted successfully!";
        } else {
            $error_message = "Error deleting coach.";
        }
    }
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get coaches count
if ($search) {
    $count_sql = "SELECT COUNT(*) FROM users WHERE user_type = 'sport_coach' 
                  AND (id_number LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute([$search_param, $search_param, $search_param, $search_param]);
    $total_coaches = $stmt->fetchColumn();
} else {
    $total_coaches = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'sport_coach'")->fetchColumn();
}

$total_pages = ceil($total_coaches / $limit);

// Get coaches with pagination
if ($search) {
    $sql = "SELECT u.*, 
                   (SELECT COUNT(*) FROM teams WHERE coach_id = u.id) as team_count,
                   (SELECT GROUP_CONCAT(team_name SEPARATOR ', ') FROM teams WHERE coach_id = u.id) as teams
            FROM users u 
            WHERE u.user_type = 'sport_coach' 
            AND (u.id_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$search_param, $search_param, $search_param, $search_param, $limit, $offset]);
    $coaches = $stmt->fetchAll();
} else {
    $sql = "SELECT u.*, 
                   (SELECT COUNT(*) FROM teams WHERE coach_id = u.id) as team_count,
                   (SELECT GROUP_CONCAT(team_name SEPARATOR ', ') FROM teams WHERE coach_id = u.id) as teams
            FROM users u 
            WHERE u.user_type = 'sport_coach' 
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit, $offset]);
    $coaches = $stmt->fetchAll();
}

// Get greeting
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good morning";
} elseif ($hour < 18) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATHLETIX - Manage Coaches</title>
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

        /* ===== MODERN SIDEBAR DESIGN FOR ATHLETICS ADMIN ===== */
        .sidebar {
            width: 300px;
            background: linear-gradient(180deg, #0a1a2f 0%, #1a2c42 100%);
            position: fixed;
            height: 100vh;
            padding: 25px 0;
            box-shadow: 10px 0 30px rgba(0,0,0,0.15);
            overflow-y: auto;
            z-index: 100;
        }

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

        .user-profile {
            padding: 20px 25px 30px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 15px;
        }

        .user-avatar-large {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #8B1E3F 0%, #6b152f 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            box-shadow: 0 10px 20px rgba(139, 30, 63, 0.3);
        }

        .user-avatar-large span {
            font-size: 28px;
            font-weight: 700;
            color: white;
        }

        .user-name {
            font-size: 18px;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
        }

        .user-role {
            display: inline-block;
            background: rgba(255,255,255,0.1);
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
            color: rgba(255,255,255,0.5);
            margin-top: 8px;
        }

        .nav-section {
            padding: 15px 25px 5px 25px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.3);
        }

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
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            border-radius: 12px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

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
            color: rgba(255,255,255,0.5);
            transition: all 0.3s ease;
        }

        .sidebar-nav a:hover {
            background: rgba(139, 30, 63, 0.15);
            color: #8B1E3F;
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(139, 30, 63, 0.2);
        }

        .sidebar-nav a:hover i {
            color: #8B1E3F;
            transform: scale(1.1);
        }

        .sidebar-nav li.active a {
            background: #8B1E3F;
            color: white;
            font-weight: 600;
            box-shadow: 0 10px 20px rgba(139, 30, 63, 0.3);
        }

        .sidebar-nav li.active a i {
            color: white;
        }

        .sidebar-nav li.active a::before {
            display: none;
        }

        .badge {
            margin-left: auto;
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.7);
            padding: 3px 8px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .sidebar-nav a:hover .badge {
            background: rgba(139, 30, 63, 0.3);
            color: #8B1E3F;
        }

        .sidebar-nav li.active .badge {
            background: rgba(255,255,255,0.2);
            color: white;
        }

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
            color: #8B1E3F;
        }

        .btn-add {
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

        .btn-add:hover {
            background: #6b152f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 30, 63, 0.3);
        }

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

        /* Search Bar */
        .search-bar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-input i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .search-input input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
        }

        .filter-select {
            padding: 12px 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
        }

        .btn-search {
            background: #8B1E3F;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-search:hover {
            background: #6b152f;
        }

        .btn-reset {
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #e9ecef;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-reset:hover {
            background: #e9ecef;
        }

        /* Coaches Table */
        .coaches-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e9ecef;
            overflow: hidden;
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
        }

        .card-header span {
            background: #f8f9fa;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            color: #6c757d;
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

        .badge-active {
            background: #d4edda;
            color: #155724;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-suspended {
            background: #f8d7da;
            color: #721c24;
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

        .team-badge {
            background: #e9ecef;
            color: #495057;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            margin: 2px;
            display: inline-block;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 20px;
            border-top: 1px solid #e9ecef;
        }

        .pagination a {
            padding: 8px 15px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            color: #495057;
            text-decoration: none;
            font-size: 14px;
        }

        .pagination a:hover {
            background: #f8f9fa;
        }

        .pagination a.active {
            background: #8B1E3F;
            color: white;
            border-color: #8B1E3F;
        }

        @media (max-width: 1200px) {
            table {
                display: block;
                overflow-x: auto;
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
            
            .search-bar {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- MODERN SIDEBAR (SAME AS DASHBOARD) -->
        <div class="sidebar">
            <div class="user-profile">
                <div class="user-avatar-large">
                    <span><?php echo $user_initials; ?></span>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($current_user['first_name'] ?? 'Athletics') . ' ' . htmlspecialchars($current_user['last_name'] ?? 'Admin'); ?></div>
                <div class="user-role">ATHLETICS ADMINISTRATOR</div>
                <div class="user-email"><?php echo htmlspecialchars($current_user['email'] ?? 'athletics@talentrix.edu'); ?></div>
            </div>

            <div class="nav-section">MAIN</div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="athletics_admin_dashboard.php">
                            <i class="fas fa-chart-pie"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="nav-section">MANAGEMENT</div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="manage_athletes.php">
                            <i class="fas fa-running"></i>
                            <span>Manage Athletes</span>
                            <span class="badge"><?php echo $total_athletes; ?></span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="manage_coaches.php">
                            <i class="fas fa-user-tie"></i>
                            <span>Manage Coaches</span>
                            <span class="badge"><?php echo $total_sport_coaches; ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="manage_teams.php">
                            <i class="fas fa-futbol"></i>
                            <span>Manage Teams</span>
                            <span class="badge"><?php echo $total_teams; ?></span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="nav-section">CONTENT</div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="events.php">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Events</span>
                        </a>
                    </li>
                    <li>
                        <a href="achievements.php">
                            <i class="fas fa-trophy"></i>
                            <span>Achievements</span>
                            <span class="badge"><?php echo $total_achievements; ?></span>
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

            <div class="nav-section">REPORTS</div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="reports.php">
                            <i class="fas fa-chart-line"></i>
                            <span>Analytics</span>
                        </a>
                    </li>
                </ul>
            </nav>

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
                    <h1>MANAGE COACHES</h1>
                    <p><?php echo $greeting; ?>, <?php echo htmlspecialchars($current_user['first_name'] ?? 'Admin'); ?>! Manage all sports coaches in the system.</p>
                </div>
                <div class="header-right">
                    <div class="date-badge"><i class="far fa-calendar-alt"></i> <?php echo date('F d, Y'); ?></div>
                    <a href="add_coach.php" class="btn-add"><i class="fas fa-plus"></i> Add Coach</a>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
            <div class="alert alert-success" id="successAlert">
                <span><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></span>
                <span class="close-alert" onclick="this.parentElement.style.display='none'">&times;</span>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-error" id="errorAlert">
                <span><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></span>
                <span class="close-alert" onclick="this.parentElement.style.display='none'">&times;</span>
            </div>
            <?php endif; ?>

            <!-- Search Bar -->
            <div class="search-bar">
                <form method="GET" style="display: flex; gap: 15px; width: 100%; flex-wrap: wrap;">
                    <div class="search-input">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by ID, name, or email..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <select name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <button type="submit" class="btn-search"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="manage_coaches.php" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </form>
            </div>

            <!-- Coaches Table -->
            <div class="coaches-card">
                <div class="card-header">
                    <h2>COACHES LIST</h2>
                    <span>Total: <?php echo $total_coaches; ?> coaches</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID NUMBER</th>
                            <th>FULL NAME</th>
                            <th>EMAIL</th>
                            <th>TEAMS</th>
                            <th>STATUS</th>
                            <th>DATE JOINED</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($coaches)): ?>
                            <?php foreach ($coaches as $coach): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($coach['id_number'] ?? 'N/A'); ?></strong></td>
                                <td><?php echo htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($coach['email']); ?></td>
                                <td>
                                    <?php if ($coach['team_count'] > 0): ?>
                                        <span class="team-badge"><?php echo $coach['team_count']; ?> team(s)</span>
                                    <?php else: ?>
                                        <span class="team-badge">No teams</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    switch ($coach['status']) {
                                        case 'active':
                                            $status_class = 'badge-active';
                                            break;
                                        case 'pending':
                                            $status_class = 'badge-pending';
                                            break;
                                        case 'suspended':
                                            $status_class = 'badge-suspended';
                                            break;
                                        default:
                                            $status_class = 'badge-pending';
                                    }
                                    ?>
                                    <span class="<?php echo $status_class; ?>"><?php echo ucfirst($coach['status'] ?? 'Pending'); ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($coach['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_coach.php?id=<?php echo $coach['id']; ?>" class="btn-view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_coach.php?id=<?php echo $coach['id']; ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                        <?php if ($coach['team_count'] == 0): ?>
                                            <a href="?delete=<?php echo $coach['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this coach?')"><i class="fas fa-trash"></i> Delete</a>
                                        <?php else: ?>
                                            <span class="btn-delete" style="opacity: 0.5; cursor: not-allowed;" title="Cannot delete coach with assigned teams"><i class="fas fa-trash"></i> Delete</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-user-tie" style="font-size: 48px; color: #6c757d; margin-bottom: 15px; display: block;"></i>
                                    <h3 style="color: #6c757d; margin-bottom: 10px;">No Coaches Found</h3>
                                    <p style="color: #6c757d;">Click the "Add Coach" button to add a new coach.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        setTimeout(function() {
            var successAlert = document.getElementById('successAlert');
            var errorAlert = document.getElementById('errorAlert');
            if (successAlert) successAlert.style.display = 'none';
            if (errorAlert) errorAlert.style.display = 'none';
        }, 5000);
    </script>
</body>
</html>