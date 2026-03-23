<?php
// schedule.php - Schedule Management Page
session_start();
require_once 'db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user is sport_coach
if ($_SESSION['user_type'] !== 'sport_coach') {
    if ($_SESSION['user_type'] === 'athletics_admin') {
        header('Location: athletics_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'dance_admin') {
        header('Location: dance_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'dance_coach') {
        header('Location: dance_trainer_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'student') {
        header('Location: student_dashboard.php');
        exit();
    } else {
        header('Location: login.php');
        exit();
    }
}

$user_id = $_SESSION['user_id'];

// Get coach details
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.first_name, u.last_name, u.email, u.id_number
        FROM coaches c
        JOIN users u ON c.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $coach = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching coach: " . $e->getMessage());
    $coach = null;
}

// If coach not found, create basic coach record
if (!$coach) {
    try {
        // First check if user exists
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, id_number FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Insert into coaches table
            $stmt = $pdo->prepare("INSERT INTO coaches (user_id, employee_id, date_hired, primary_sport) VALUES (?, ?, CURDATE(), 'Basketball')");
            $employee_id = 'COACH-' . $user_id . '-' . date('Y');
            $stmt->execute([$user_id, $employee_id]);
            
            // Fetch again
            $stmt = $pdo->prepare("
                SELECT c.*, u.first_name, u.last_name, u.email, u.id_number
                FROM coaches c
                JOIN users u ON c.user_id = u.id
                WHERE u.id = ?
            ");
            $stmt->execute([$user_id]);
            $coach = $stmt->fetch();
        }
    } catch (Exception $e) {
        error_log("Error creating coach: " . $e->getMessage());
        $_SESSION['error'] = "Error setting up coach profile. Please contact administrator.";
    }
}

// Get coach initials for avatar
$coach_initials = 'CT';
if ($coach && isset($coach['first_name']) && isset($coach['last_name']) && 
    !empty($coach['first_name']) && !empty($coach['last_name'])) {
    $first_initial = trim(substr($coach['first_name'], 0, 1));
    $last_initial = trim(substr($coach['last_name'], 0, 1));
    $coach_initials = strtoupper($first_initial . $last_initial);
}

// Get all teams under this coach
$teams = [];
if (isset($coach['id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, COALESCE(s.sport_name, 'Basketball') as sport_name, 
                   (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as player_count
            FROM teams t
            LEFT JOIN sports s ON t.sport_id = s.id
            WHERE t.coach_id = ?
            ORDER BY t.team_name
        ");
        $stmt->execute([$coach['id']]);
        $teams = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching teams: " . $e->getMessage());
        $teams = [];
    }
}

// Get upcoming matches
$upcoming_matches = [];
if (!empty($teams)) {
    try {
        $team_ids = array_column($teams, 'id');
        if (!empty($team_ids)) {
            $placeholders = implode(',', array_fill(0, count($team_ids), '?'));
            $stmt = $pdo->prepare("
                SELECT m.*, t.team_name
                FROM matches m
                JOIN teams t ON m.team_id = t.id
                WHERE m.team_id IN ($placeholders)
                ORDER BY m.match_date ASC, m.match_time ASC
            ");
            $stmt->execute($team_ids);
            $upcoming_matches = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error fetching matches: " . $e->getMessage());
        $upcoming_matches = [];
    }
}

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Schedule Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            background: #f8fafc;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 15px rgba(0,0,0,0.05);
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 30px 25px;
            border-bottom: 1px solid #eef2f6;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #1a2639;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .logo span {
            color: #f59e0b;
            font-size: 28px;
        }

        .coach-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .coach-avatar {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
        }

        .coach-details h4 {
            font-size: 16px;
            color: #1a2639;
            margin-bottom: 4px;
        }

        .coach-details p {
            font-size: 13px;
            color: #64748b;
        }

        .coach-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 6px;
            display: inline-block;
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 0;
        }

        .nav-section {
            margin-bottom: 20px;
        }

        .nav-section-title {
            padding: 10px 25px;
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s;
            margin: 2px 10px;
            border-radius: 10px;
        }

        .nav-item:hover {
            background: #fef3c7;
            color: #f59e0b;
        }

        .nav-item.active {
            background: #f59e0b;
            color: white;
        }

        .nav-item i {
            margin-right: 15px;
            font-size: 18px;
            width: 22px;
        }

        .nav-item span {
            font-size: 14px;
            font-weight: 500;
        }

        .nav-item .badge {
            margin-left: auto;
            background: #ef4444;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
        }

        .sidebar-footer {
            padding: 25px;
            border-top: 1px solid #eef2f6;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ef4444;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            padding: 10px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .logout-btn:hover {
            background: #fee2e2;
        }

        .logout-btn i {
            font-size: 18px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            color: #1a2639;
            font-weight: 600;
        }

        .header p {
            color: #64748b;
            margin-top: 5px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input {
            padding: 12px 20px 12px 45px;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            width: 280px;
            font-size: 14px;
            background: white;
        }

        .search-box input:focus {
            outline: none;
            border-color: #f59e0b;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
        }

        .notification-icon i {
            font-size: 22px;
            color: #475569;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .header-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h2 {
            font-size: 24px;
            color: #1a2639;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h2 i {
            color: #f59e0b;
        }

        .add-btn {
            background: #f59e0b;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .add-btn:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.3);
        }

        /* Schedule Grid */
        .schedule-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 25px;
            margin-top: 20px;
        }

        /* Calendar Card */
        .calendar-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid #eef2f6;
        }

        #calendar {
            margin-top: 10px;
        }

        /* Upcoming Matches Card */
        .matches-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid #eef2f6;
        }

        .matches-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eef2f6;
        }

        .matches-header h3 {
            font-size: 16px;
            color: #1a2639;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .matches-header h3 i {
            color: #f59e0b;
        }

        .matches-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-height: 500px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .matches-list::-webkit-scrollbar {
            width: 5px;
        }

        .matches-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .matches-list::-webkit-scrollbar-thumb {
            background: #f59e0b;
            border-radius: 10px;
        }

        .match-item {
            background: #f8fafc;
            border-radius: 15px;
            padding: 15px;
            border-left: 4px solid #f59e0b;
            transition: all 0.3s;
        }

        .match-item:hover {
            transform: translateX(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .match-date {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .match-date .day {
            font-size: 20px;
            font-weight: 700;
            color: #f59e0b;
        }

        .match-date .month {
            font-size: 14px;
            color: #64748b;
        }

        .match-date .time {
            margin-left: auto;
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .match-details h4 {
            font-size: 15px;
            color: #1a2639;
            margin-bottom: 5px;
        }

        .match-details p {
            font-size: 13px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 10px;
        }

        .match-details p i {
            font-size: 12px;
            color: #f59e0b;
        }

        .match-team {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .match-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            justify-content: flex-end;
        }

        .match-action-btn {
            color: #94a3b8;
            transition: color 0.2s;
            text-decoration: none;
            padding: 5px;
        }

        .match-action-btn:hover {
            color: #f59e0b;
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
            color: #e2e8f0;
        }

        .empty-state p {
            font-size: 14px;
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
            color: #1a2639;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-content h2 i {
            color: #f59e0b;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #475569;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #f59e0b;
            outline: none;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-submit {
            flex: 1;
            padding: 14px;
            background: #f59e0b;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            background: #d97706;
        }

        .btn-cancel {
            flex: 1;
            padding: 14px;
            background: #e2e8f0;
            color: #475569;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #cbd5e0;
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .schedule-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .logo span:last-child,
            .coach-details,
            .nav-item span,
            .nav-section-title,
            .sidebar-footer span {
                display: none;
            }
            
            .coach-avatar {
                margin: 0 auto;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .header-actions {
                gap: 10px;
            }
            
            .search-box input {
                width: 200px;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: start;
            }
            
            .add-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* FullCalendar Customization */
        .fc .fc-button-primary {
            background-color: #f59e0b;
            border-color: #f59e0b;
        }

        .fc .fc-button-primary:hover {
            background-color: #d97706;
            border-color: #d97706;
        }

        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background-color: #d97706;
            border-color: #d97706;
        }

        .fc .fc-day-today {
            background-color: #fef3c7 !important;
        }

        .fc .fc-event {
            background-color: #f59e0b;
            border-color: #f59e0b;
            padding: 2px 5px;
            cursor: pointer;
        }

        .fc .fc-event:hover {
            background-color: #d97706;
            border-color: #d97706;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span>🏀</span> TALENTRIX
                </div>
                <div class="coach-info">
                    <div class="coach-avatar">
                        <?php echo $coach_initials; ?>
                    </div>
                    <div class="coach-details">
                        <h4>Coach <?php echo htmlspecialchars($coach['first_name'] ?? 'Coach'); ?></h4>
                        <p><?php echo htmlspecialchars($coach['primary_sport'] ?? 'Head Coach'); ?></p>
                        <span class="coach-badge">Active</span>
                    </div>
                </div>
            </div>

            <div class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">MAIN</div>
                    <a href="coach_dashboard.php" class="nav-item">
                        <i>🏠</i>
                        <span>Dashboard</span>
                    </a>
                    <a href="schedule.php" class="nav-item active">
                        <i>📅</i>
                        <span>Schedule</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">MANAGEMENT</div>
                    <a href="players.php" class="nav-item">
                        <i>👥</i>
                        <span>Players</span>
                    </a>
                    <a href="teams.php" class="nav-item">
                        <i>🏀</i>
                        <span>Teams</span>
                    </a>
                    <a href="reports.php" class="nav-item">
                        <i>📊</i>
                        <span>Reports</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Teams</div>
                    <?php foreach(array_slice($teams, 0, 3) as $team): ?>
                    <a href="#" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span><?php echo htmlspecialchars($team['team_name'] ?? 'Team'); ?></span>
                        <span class="badge" style="background: #f59e0b;"><?php echo $team['player_count'] ?? 0; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Account</div>
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                    <a href="#" class="nav-item">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
            </div>

            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Log Out</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1>Schedule Management</h1>
                    <p>Manage your team matches and events</p>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search events..." id="searchInput">
                    </div>
                    <div class="notification-icon">
                        <i class="far fa-bell"></i>
                    </div>
                    <div class="header-profile">
                        <div class="header-avatar">
                            <?php echo $coach_initials; ?>
                        </div>
                    </div>
                </div>
            </div>

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

            <!-- Schedule Section -->
            <div class="page-header">
                <h2><i class="fas fa-calendar-alt"></i> Team Schedule</h2>
                <button class="add-btn" onclick="openAddEventModal()">
                    <i class="fas fa-plus-circle"></i> Schedule Match
                </button>
            </div>

            <div class="schedule-grid">
                <!-- Calendar -->
                <div class="calendar-card">
                    <div id="calendar"></div>
                </div>

                <!-- Upcoming Matches -->
                <div class="matches-card">
                    <div class="matches-header">
                        <h3><i class="fas fa-clock"></i> Upcoming Matches</h3>
                        <span class="badge" style="background: #f59e0b; padding: 5px 10px; border-radius: 20px; color: white;"><?php echo count($upcoming_matches); ?> events</span>
                    </div>

                    <div class="matches-list" id="matchesList">
                        <?php if(!empty($upcoming_matches)): ?>
                            <?php foreach($upcoming_matches as $match): ?>
                            <div class="match-item">
                                <div class="match-date">
                                    <span class="day"><?php echo date('d', strtotime($match['match_date'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($match['match_date'])); ?></span>
                                    <span class="time"><i class="far fa-clock"></i> <?php echo isset($match['match_time']) ? date('h:i A', strtotime($match['match_time'])) : 'TBA'; ?></span>
                                </div>
                                <div class="match-details">
                                    <h4><?php echo htmlspecialchars($match['opponent'] ?? 'TBD'); ?></h4>
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($match['location'] ?? 'TBA'); ?></p>
                                    <span class="match-team"><?php echo htmlspecialchars($match['team_name']); ?></span>
                                </div>
                                <div class="match-actions">
                                    <a href="#" class="match-action-btn" onclick="editEvent(<?php echo $match['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" class="match-action-btn" onclick="deleteEvent(<?php echo $match['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No upcoming matches</p>
                                <small>Click "Schedule Match" to add your first event</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Event Modal -->
    <div id="addEventModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-calendar-plus"></i> Schedule New Match</h2>
            <form method="POST" action="add_event.php" onsubmit="return validateEventForm()">
                <div class="form-group">
                    <label>Team</label>
                    <select name="team_id" required>
                        <option value="">-- Select Team --</option>
                        <?php foreach($teams as $team): ?>
                        <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Opponent</label>
                    <input type="text" name="opponent" required placeholder="e.g., University Team">
                </div>
                <div class="form-group">
                    <label>Match Date</label>
                    <input type="date" name="match_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Match Time</label>
                    <input type="time" name="match_time" required>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" required placeholder="e.g., Main Gym">
                </div>
                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea name="notes" rows="3" placeholder="Additional information..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-submit">Schedule Match</button>
                    <button type="button" class="btn-cancel" onclick="closeAddEventModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div id="eventDetailsModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-calendar-alt"></i> Event Details</h2>
            <div id="eventDetails"></div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeEventDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script>
    let calendar;

    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        
        // Prepare events data from PHP
        const events = [
            <?php foreach($upcoming_matches as $match): ?>
            {
                id: <?php echo $match['id']; ?>,
                title: 'vs <?php echo addslashes($match['opponent']); ?>',
                start: '<?php echo $match['match_date'] . 'T' . ($match['match_time'] ?? '00:00:00'); ?>',
                extendedProps: {
                    team: '<?php echo addslashes($match['team_name']); ?>',
                    location: '<?php echo addslashes($match['location'] ?? 'TBA'); ?>',
                    opponent: '<?php echo addslashes($match['opponent']); ?>'
                }
            },
            <?php endforeach; ?>
        ];

        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 600,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'Today',
                month: 'Month',
                week: 'Week',
                day: 'Day'
            },
            events: events,
            eventClick: function(info) {
                showEventDetails(info.event);
            },
            dateClick: function(info) {
                // Optional: Pre-fill date in add event modal
                document.querySelector('input[name="match_date"]').value = info.dateStr;
                openAddEventModal();
            }
        });

        calendar.render();
    });

    // Modal functions
    function openAddEventModal() {
        document.getElementById('addEventModal').classList.add('active');
    }

    function closeAddEventModal() {
        document.getElementById('addEventModal').classList.remove('active');
    }

    function showEventDetails(event) {
        const details = document.getElementById('eventDetails');
        details.innerHTML = `
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="font-size: 48px; color: #f59e0b; margin-bottom: 10px;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3 style="margin-bottom: 5px;">${event.title}</h3>
                <p style="color: #64748b;">${event.extendedProps.team}</p>
            </div>
            <div style="margin-bottom: 15px;">
                <p><i class="fas fa-calendar" style="color: #f59e0b; width: 20px;"></i> ${event.start.toLocaleDateString()}</p>
                <p><i class="fas fa-clock" style="color: #f59e0b; width: 20px;"></i> ${event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                <p><i class="fas fa-map-marker-alt" style="color: #f59e0b; width: 20px;"></i> ${event.extendedProps.location}</p>
                <p><i class="fas fa-users" style="color: #f59e0b; width: 20px;"></i> vs ${event.extendedProps.opponent}</p>
            </div>
        `;
        document.getElementById('eventDetailsModal').classList.add('active');
    }

    function closeEventDetailsModal() {
        document.getElementById('eventDetailsModal').classList.remove('active');
    }

    // Form validation
    function validateEventForm() {
        const team = document.querySelector('select[name="team_id"]').value;
        const opponent = document.querySelector('input[name="opponent"]').value;
        const date = document.querySelector('input[name="match_date"]').value;
        const time = document.querySelector('input[name="match_time"]').value;
        const location = document.querySelector('input[name="location"]').value;
        
        if(!team || !opponent || !date || !time || !location) {
            alert('Please fill in all required fields');
            return false;
        }
        
        return true;
    }

    // Search functionality
    document.getElementById('searchInput')?.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const matches = document.querySelectorAll('.match-item');
        
        matches.forEach(match => {
            const text = match.textContent.toLowerCase();
            if(text.includes(searchTerm)) {
                match.style.display = '';
            } else {
                match.style.display = 'none';
            }
        });
    });

    // Event action functions
    function editEvent(id) {
        alert('Edit event: ' + id);
        // Implement edit functionality
    }

    function deleteEvent(id) {
        if(confirm('Are you sure you want to delete this event?')) {
            alert('Delete event: ' + id);
            // Implement delete functionality
        }
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target == modal) {
                modal.classList.remove('active');
            }
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if(e.key === '/' && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            document.getElementById('searchInput')?.focus();
        }
        
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal.active');
            modals.forEach(modal => {
                modal.classList.remove('active');
            });
        }
    });
    </script>
</body>
</html>