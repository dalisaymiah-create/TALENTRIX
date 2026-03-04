<?php
// student_dashboard.php - FIXED AUTHENTICATION
session_start();
require_once 'db.php';

// IMPORTANT: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user is student - if not, redirect to correct dashboard
if ($_SESSION['user_type'] !== 'student') {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin_pages.php?page=dashboard');
        exit();
    } elseif ($_SESSION['user_type'] === 'coach') {
        header('Location: coach_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'dance_coach') {
        header('Location: dance_trainer_dashboard.php');
        exit();
    } else {
        header('Location: login.php');
        exit();
    }
}

// Rest of your student dashboard code continues here...
$user_id = $_SESSION['user_id'];

// Get student details
$stmt = $pdo->prepare("
    SELECT s.*, u.first_name, u.last_name, u.email, u.id_number, u.status, u.created_at
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);


$student = $stmt->fetch();
// Get student's teams
$teams = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, s.sport_name, tm.jersey_number, tm.position, tm.status as team_status
        FROM team_members tm
        JOIN teams t ON tm.team_id = t.id
        JOIN sports s ON t.sport_id = s.id
        WHERE tm.student_id = ? AND tm.status = 'active'
    ");
    $stmt->execute([$student['id']]);
    $teams = $stmt->fetchAll();
} catch (Exception $e) {
    $teams = [];
}

// Get student's dance troupes
$troupes = [];
try {
    $stmt = $pdo->prepare("
        SELECT dt.*, dtm.role, dtm.status as member_status
        FROM dance_troupe_members dtm
        JOIN dance_troupes dt ON dtm.troupe_id = dt.id
        WHERE dtm.student_id = ? AND dtm.status = 'active'
    ");
    $stmt->execute([$student['id']]);
    $troupes = $stmt->fetchAll();
} catch (Exception $e) {
    $troupes = [];
}

// Get pending requests
$pending_requests = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, t.team_name, dt.troupe_name
        FROM approvals a
        LEFT JOIN teams t ON a.team_id = t.id
        LEFT JOIN dance_troupes dt ON a.troupe_id = dt.id
        WHERE a.student_id = ? AND a.status = 'pending'
    ");
    $stmt->execute([$student['id']]);
    $pending_requests = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_requests = [];
}

// Get all available teams (for joining)
$available_teams = [];
try {
    $available_teams = $pdo->query("
        SELECT t.*, s.sport_name, CONCAT(u.first_name, ' ', u.last_name) as coach_name
        FROM teams t
        JOIN sports s ON t.sport_id = s.id
        LEFT JOIN coaches c ON t.coach_id = c.id
        LEFT JOIN users u ON c.user_id = u.id
        WHERE t.is_active = 1
        ORDER BY s.sport_name, t.team_name
    ")->fetchAll();
} catch (Exception $e) {
    $available_teams = [];
}

// Get all available troupes
$available_troupes = [];
try {
    $available_troupes = $pdo->query("
        SELECT dt.*, CONCAT(u.first_name, ' ', u.last_name) as coach_name
        FROM dance_troupes dt
        LEFT JOIN dance_coaches dc ON dt.coach_id = dc.id
        LEFT JOIN users u ON dc.user_id = u.id
        WHERE dt.is_active = 1
        ORDER BY dt.troupe_name
    ")->fetchAll();
} catch (Exception $e) {
    $available_troupes = [];
}

// Get training hours (last 30 days)
$training_hours = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(TIMESTAMPDIFF(HOUR, created_at, NOW())), 0) as total_hours
        FROM attendance
        WHERE student_id = ? AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$student['id']]);
    $training_hours = $stmt->fetch()['total_hours'];
} catch (Exception $e) {
    $training_hours = 12.5; // Default mock data
}

// Get matches played
$matches_played = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as matches_played
        FROM team_members tm
        WHERE tm.student_id = ?
    ");
    $stmt->execute([$student['id']]);
    $matches_played = $stmt->fetch()['matches_played'] ?? 3; // Default mock data
} catch (Exception $e) {
    $matches_played = 3;
}

// Get upcoming matches/events
$upcoming_events = [];
try {
    $event_type = ($student['student_type'] == 'athlete') ? 'athletics' : 
                  (($student['student_type'] == 'dancer') ? 'dance' : 'both');
    
    $stmt = $pdo->prepare("
        SELECT 'event' as type, event_date, event_title as title, location, event_time
        FROM upcoming_events 
        WHERE event_date >= CURDATE() AND (event_type = ? OR event_type = 'both')
        ORDER BY event_date ASC
        LIMIT 3
    ");
    $stmt->execute([$event_type]);
    $upcoming_events = $stmt->fetchAll();
} catch (Exception $e) {
    // Mock data if no events
    $upcoming_events = [
        ['type' => 'event', 'event_date' => date('Y-m-d', strtotime('+3 days')), 'title' => 'Team Practice', 'location' => 'Main Gym', 'event_time' => '15:00:00'],
        ['type' => 'event', 'event_date' => date('Y-m-d', strtotime('+7 days')), 'title' => 'Friendly Match', 'location' => 'Sports Complex', 'event_time' => '14:30:00'],
        ['type' => 'event', 'event_date' => date('Y-m-d', strtotime('+14 days')), 'title' => 'Training Camp', 'location' => 'Training Center', 'event_time' => '09:00:00']
    ];
}

// Get team players (teammates)
$team_players = [];
if (!empty($teams)) {
    $team_ids = array_column($teams, 'id');
    $placeholders = implode(',', array_fill(0, count($team_ids), '?'));
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.first_name, u.last_name, s.primary_sport, tm.jersey_number
            FROM team_members tm
            JOIN students s ON tm.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE tm.team_id IN ($placeholders) AND tm.student_id != ? AND tm.status = 'active'
            LIMIT 5
        ");
        $params = array_merge($team_ids, [$student['id']]);
        $stmt->execute($params);
        $team_players = $stmt->fetchAll();
    } catch (Exception $e) {
        $team_players = [];
    }
}

// If no teammates, show mock data
if (empty($team_players)) {
    $team_players = [
        ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'primary_sport' => 'Basketball', 'jersey_number' => 10],
        ['first_name' => 'Maria', 'last_name' => 'Santos', 'primary_sport' => 'Volleyball', 'jersey_number' => 7],
        ['first_name' => 'Jose', 'last_name' => 'Rizal', 'primary_sport' => 'Football', 'jersey_number' => 14]
    ];
}

// Calculate performance (mock data)
$performance = rand(85, 98);

// Get greeting based on time
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

// Check if user is active/pending
$is_pending = ($student['status'] ?? 'pending') === 'pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f0f2f5;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0a2540 0%, #1a365d 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }

        .sidebar-header {
            padding: 30px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-header h2 {
            font-size: 24px;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .sidebar-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .sidebar-nav ul {
            list-style: none;
        }

        .sidebar-nav li {
            margin: 5px 15px;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .sidebar-nav li.active {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 15px;
            transition: all 0.3s;
        }

        .sidebar-nav a i {
            margin-right: 15px;
            width: 20px;
            font-size: 18px;
        }

        .sidebar-nav a:hover {
            color: white;
            transform: translateX(5px);
        }

        .sidebar-nav li.active a {
            color: white;
        }

        .divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 20px 25px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            background: #f5f7fb;
            min-height: 100vh;
        }

        /* Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .header-title h1 {
            font-size: 24px;
            color: #0a2540;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .header-title p {
            color: #718096;
            font-size: 14px;
        }

        .logout-btn {
            padding: 8px 20px;
            background: #ef4444;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        /* Pending Approval Banner */
        .pending-banner {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }

        .pending-banner i {
            font-size: 40px;
        }

        .pending-banner h3 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .pending-banner p {
            opacity: 0.9;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(180deg, #10b981 0%, #059669 100%);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-header h3 {
            color: #718096;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #0a2540;
            margin-bottom: 5px;
        }

        .stat-change {
            color: #10b981;
            font-size: 14px;
            font-weight: 600;
        }

        .performance-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
            background: #f0fdf4;
            padding: 8px 12px;
            border-radius: 8px;
        }

        .performance-indicator span {
            color: #10b981;
            font-size: 20px;
        }

        /* Join Team Button */
        .join-team-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 25px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 16px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .join-team-btn:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 35px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-content h2 {
            margin-bottom: 25px;
            color: #0a2540;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-content h2 i {
            color: #10b981;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #4a5568;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group select:focus,
        .form-group input:focus {
            border-color: #10b981;
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-submit {
            flex: 1;
            padding: 14px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            background: #059669;
        }

        .btn-cancel {
            flex: 1;
            padding: 14px;
            background: #e2e8f0;
            color: #4a5568;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #cbd5e0;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        /* Cards */
        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .dashboard-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f2f5;
        }

        .card-header h3 {
            font-size: 18px;
            color: #0a2540;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 i {
            color: #10b981;
        }

        .view-all {
            color: #10b981;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .view-all:hover {
            color: #059669;
            transform: translateX(3px);
        }

        /* Schedule List */
        .schedule-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .schedule-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 4px solid #10b981;
            transition: all 0.3s;
        }

        .schedule-item:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }

        .schedule-date {
            min-width: 70px;
            font-weight: 700;
            color: #10b981;
            font-size: 16px;
        }

        .schedule-details {
            flex: 1;
        }

        .schedule-title {
            font-weight: 600;
            color: #0a2540;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .schedule-location {
            font-size: 13px;
            color: #718096;
        }

        .schedule-time {
            font-size: 13px;
            color: #94a3b8;
        }

        /* Team Players */
        .team-players {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .player-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .player-item:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }

        .player-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        .player-info {
            flex: 1;
        }

        .player-name {
            font-weight: 600;
            color: #0a2540;
            margin-bottom: 3px;
            font-size: 15px;
        }

        .player-sport {
            font-size: 12px;
            color: #718096;
        }

        .player-jersey {
            font-size: 16px;
            font-weight: 700;
            color: #10b981;
            background: #f0fdf4;
            padding: 5px 12px;
            border-radius: 20px;
        }

        /* My Teams Grid */
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .team-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }

        .team-card:hover {
            border-color: #10b981;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .team-card h4 {
            color: #0a2540;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .team-sport {
            color: #10b981;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 15px;
            display: inline-block;
            padding: 3px 12px;
            background: #e0f2fe;
            border-radius: 20px;
        }

        .team-detail {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
            color: #4a5568;
            font-size: 14px;
        }

        .team-detail i {
            color: #10b981;
            width: 20px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 50px;
            margin-bottom: 15px;
            color: #cbd5e0;
        }

        .empty-state p {
            font-size: 16px;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #38a169;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border-left: 4px solid #e53e3e;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h2,
            .sidebar-header p,
            .sidebar-nav a span {
                display: none;
            }
            
            .sidebar-nav a {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar-nav a i {
                margin-right: 0;
                font-size: 20px;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .pending-banner {
                flex-direction: column;
                text-align: center;
            }
            
            .teams-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .stat-value {
                font-size: 24px;
            }
            
            .join-team-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>TALENTRIX</h2>
                <p><?php echo $greeting; ?>,<br><?php echo htmlspecialchars($student['first_name'] ?? 'Student'); ?></p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="active"><a href="student_dashboard.php"><i class="fas fa-home"></i> <span>Home</span></a></li>
                    <li><a href="#"><i class="fas fa-running"></i> <span>My Sports</span></a></li>
                    <li><a href="#"><i class="fas fa-users"></i> <span>Teammates</span></a></li>
                    <li><a href="#"><i class="fas fa-calendar"></i> <span>Events</span></a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
                    <li><a href="#"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
                    <li class="divider"></li>
                    <li><a href="index.php"><i class="fas fa-globe"></i> <span>Homepage</span></a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-title">
                    <h1>Student Dashboard</h1>
                    <p><?php echo $greeting; ?>, <?php echo htmlspecialchars($student['first_name'] ?? 'Student') . ' ' . htmlspecialchars($student['last_name'] ?? ''); ?>!</p>
                </div>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </header>

            <!-- Pending Approval Banner (if pending) -->
            <?php if($is_pending): ?>
            <div class="pending-banner">
                <i class="fas fa-clock"></i>
                <div>
                    <h3>Your Account is Pending Approval</h3>
                    <p>Your application has been submitted and is waiting for coach approval. You'll get full access once approved.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Pending Requests Alert -->
            <?php if(!empty($pending_requests) && !$is_pending): ?>
            <div class="alert alert-warning">
                <i class="fas fa-clock" style="font-size: 20px;"></i>
                <div>
                    <strong>Pending Requests:</strong> You have <?php echo count($pending_requests); ?> request(s) waiting for coach approval.
                </div>
            </div>
            <?php endif; ?>

            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>

            <!-- Join Team Button (for students without teams) -->
            <?php if(empty($teams) && empty($troupes) && !$is_pending): ?>
            <div style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap;">
                <button class="join-team-btn" onclick="openTeamModal()">
                    <i class="fas fa-plus-circle"></i> Join a Sports Team
                </button>
                <button class="join-team-btn" style="background: #8B1E3F;" onclick="openTroupeModal()">
                    <i class="fas fa-plus-circle"></i> Join a Dance Troupe
                </button>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>TRAINING HOURS</h3>
                        <i class="fas fa-clock" style="color: #10b981;"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($training_hours, 1); ?>h</div>
                    <div class="stat-change">Last 30 days</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>ACTIVITIES</h3>
                        <i class="fas fa-trophy" style="color: #10b981;"></i>
                    </div>
                    <div class="stat-value"><?php echo $matches_played; ?></div>
                    <div class="stat-change">Events participated</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>PERFORMANCE</h3>
                        <i class="fas fa-chart-line" style="color: #10b981;"></i>
                    </div>
                    <div class="stat-value"><?php echo $performance; ?>%</div>
                    <div class="performance-indicator">
                        <span>📈</span> Above Average
                    </div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Left Column: Upcoming Events -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Upcoming Events</h3>
                        <a href="#" class="view-all">View All →</a>
                    </div>
                    
                    <div class="schedule-list">
                        <?php foreach($upcoming_events as $event): ?>
                        <div class="schedule-item">
                            <div class="schedule-date">
                                <?php echo date('M d', strtotime($event['event_date'])); ?>
                            </div>
                            <div class="schedule-details">
                                <div class="schedule-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                <div class="schedule-location">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location'] ?? 'TBA'); ?>
                                </div>
                                <?php if(isset($event['event_time']) && !empty($event['event_time'])): ?>
                                <div class="schedule-time">
                                    <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($event['event_time'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if(empty($upcoming_events)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>No upcoming events</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column: Teammates -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Teammates</h3>
                        <a href="#" class="view-all">View All →</a>
                    </div>
                    
                    <div class="team-players">
                        <?php foreach($team_players as $player): ?>
                        <div class="player-item">
                            <div class="player-avatar">
                                <?php echo strtoupper(substr($player['first_name'], 0, 1) . substr($player['last_name'] ?? '', 0, 1)); ?>
                            </div>
                            <div class="player-info">
                                <div class="player-name"><?php echo htmlspecialchars($player['first_name'] . ' ' . ($player['last_name'] ?? '')); ?></div>
                                <div class="player-sport"><?php echo htmlspecialchars($player['primary_sport'] ?? 'Athlete'); ?></div>
                            </div>
                            <span class="player-jersey">#<?php echo $player['jersey_number'] ?? rand(1, 99); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- My Teams Section -->
            <?php if(!empty($teams) || !empty($troupes)): ?>
            <div class="dashboard-card" style="margin-top: 30px;">
                <div class="card-header">
                    <h3><i class="fas fa-medal"></i> My Teams & Troupes</h3>
                    <span class="view-all">Manage</span>
                </div>
                
                <div class="teams-grid">
                    <?php foreach($teams as $team): ?>
                    <div class="team-card">
                        <h4><?php echo htmlspecialchars($team['team_name']); ?></h4>
                        <span class="team-sport"><?php echo htmlspecialchars($team['sport_name']); ?></span>
                        <div class="team-detail">
                            <i class="fas fa-tshirt"></i> Jersey #<?php echo $team['jersey_number'] ?? 'TBA'; ?>
                        </div>
                        <div class="team-detail">
                            <i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($team['position'] ?? 'Player'); ?>
                        </div>
                        <div class="team-detail">
                            <i class="fas fa-check-circle" style="color: #10b981;"></i> Active Member
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php foreach($troupes as $troupe): ?>
                    <div class="team-card" style="border-left: 4px solid #8B1E3F;">
                        <h4><?php echo htmlspecialchars($troupe['troupe_name']); ?></h4>
                        <span class="team-sport" style="background: #8B1E3F; color: white;">Dance Troupe</span>
                        <div class="team-detail">
                            <i class="fas fa-star"></i> <?php echo htmlspecialchars($troupe['role'] ?? 'Dancer'); ?>
                        </div>
                        <div class="team-detail">
                            <i class="fas fa-check-circle" style="color: #10b981;"></i> Active Member
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- If no teams at all -->
            <?php if(empty($teams) && empty($troupes) && !$is_pending): ?>
            <div class="dashboard-card" style="margin-top: 30px;">
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3 style="margin-bottom: 10px;">No Teams Yet</h3>
                    <p style="color: #718096; margin-bottom: 20px;">You haven't joined any teams or troupes yet.</p>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <button class="join-team-btn" style="padding: 10px 20px; font-size: 14px;" onclick="openTeamModal()">
                            <i class="fas fa-plus-circle"></i> Join Sports Team
                        </button>
                        <button class="join-team-btn" style="background: #8B1E3F; padding: 10px 20px; font-size: 14px;" onclick="openTroupeModal()">
                            <i class="fas fa-plus-circle"></i> Join Dance Troupe
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Join Team Modal -->
    <div id="joinTeamModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-users"></i> Join a Sports Team</h2>
            <form method="POST" action="join_team.php">
                <div class="form-group">
                    <label>Select Team <span style="color: #ef4444;">*</span></label>
                    <select name="team_id" required>
                        <option value="">-- Choose a team --</option>
                        <?php foreach($available_teams as $team): ?>
                        <option value="<?php echo $team['id']; ?>">
                            <?php echo htmlspecialchars($team['team_name'] . ' (' . $team['sport_name'] . ')'); ?>
                            <?php if(!empty($team['coach_name'])): ?> - Coach: <?php echo $team['coach_name']; ?><?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                        <?php if(empty($available_teams)): ?>
                        <option value="" disabled>No teams available</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Position (Optional)</label>
                    <input type="text" name="position" placeholder="e.g., Point Guard, Setter, Forward">
                </div>
                <div class="form-group">
                    <label>Preferred Jersey Number</label>
                    <input type="number" name="jersey_number" min="0" max="99" placeholder="e.g., 23">
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-submit">Send Request</button>
                    <button type="button" class="btn-cancel" onclick="closeTeamModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Join Troupe Modal -->
    <div id="joinTroupeModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-music"></i> Join a Dance Troupe</h2>
            <form method="POST" action="join_troupe.php">
                <div class="form-group">
                    <label>Select Troupe <span style="color: #ef4444;">*</span></label>
                    <select name="troupe_id" required>
                        <option value="">-- Choose a troupe --</option>
                        <?php foreach($available_troupes as $troupe): ?>
                        <option value="<?php echo $troupe['id']; ?>">
                            <?php echo htmlspecialchars($troupe['troupe_name']); ?>
                            <?php if(!empty($troupe['coach_name'])): ?> - Trainor: <?php echo $troupe['coach_name']; ?><?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                        <?php if(empty($available_troupes)): ?>
                        <option value="" disabled>No troupes available</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Role (Optional)</label>
                    <select name="role">
                        <option value="">-- Select Role --</option>
                        <option value="Member">Member</option>
                        <option value="Lead Dancer">Lead Dancer</option>
                        <option value="Choreographer">Choreographer</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-submit">Send Request</button>
                    <button type="button" class="btn-cancel" onclick="closeTroupeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Modal functions for teams
    function openTeamModal() {
        document.getElementById('joinTeamModal').classList.add('active');
    }

    function closeTeamModal() {
        document.getElementById('joinTeamModal').classList.remove('active');
    }

    // Modal functions for troupes
    function openTroupeModal() {
        document.getElementById('joinTroupeModal').classList.add('active');
    }

    function closeTroupeModal() {
        document.getElementById('joinTroupeModal').classList.remove('active');
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const teamModal = document.getElementById('joinTeamModal');
        const troupeModal = document.getElementById('joinTroupeModal');
        
        if (event.target == teamModal) {
            teamModal.classList.remove('active');
        }
        if (event.target == troupeModal) {
            troupeModal.classList.remove('active');
        }
    }

    // Check for session messages (fallback if not shown in PHP)
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Student dashboard loaded');
    });
    </script>
</body>
</html>