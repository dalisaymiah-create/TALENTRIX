<?php
// student_dashboard.php - COMPLETE WITH MODERN SIDEBAR BUTTONS
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
    } elseif ($_SESSION['user_type'] === 'sport_coach') {
        header('Location: coach_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'dance_coach') {
        header('Location: dance_trainer_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'athletics_admin') {
        header('Location: athletics_admin_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'dance_admin') {
        header('Location: dance_admin_dashboard.php');
        exit();
    } else {
        header('Location: login.php');
        exit();
    }
}

$user_id = $_SESSION['user_id'];
$page = $_GET['page'] ?? 'home';

// Initialize variables with default values
$student = [
    'id' => 0,
    'first_name' => 'Student',
    'last_name' => '',
    'email' => '',
    'id_number' => '',
    'status' => 'pending',
    'student_type' => 'athlete',
    'primary_sport' => '',
    'dance_troupe' => '',
    'created_at' => date('Y-m-d H:i:s')
];

$teams = [];
$troupes = [];
$pending_requests = [];
$available_teams = [];
$available_troupes = [];
$training_hours = 12.5;
$matches_played = 3;
$upcoming_events = [];
$team_players = [];
$coach_info = null;

try {
    // Get student details - CHECK IF EXISTS FIRST
    $stmt = $pdo->prepare("
        SELECT s.*, u.first_name, u.last_name, u.email, u.id_number, u.status, u.created_at
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $student_data = $stmt->fetch();
    
    // Only override defaults if we found a student record
    if ($student_data) {
        $student = $student_data;
        
        // Get student's teams (for athletes)
        if ($student['student_type'] == 'athlete' || $student['student_type'] == 'both') {
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
                error_log("Error fetching teams: " . $e->getMessage());
                $teams = [];
            }
        }

        // Get student's dance troupes (for dancers)
        if ($student['student_type'] == 'dancer' || $student['student_type'] == 'both') {
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
                error_log("Error fetching troupes: " . $e->getMessage());
                $troupes = [];
            }
        }

        // Get pending requests
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
            error_log("Error fetching pending requests: " . $e->getMessage());
            $pending_requests = [];
        }

        // Get training hours (last 30 days)
        try {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(TIMESTAMPDIFF(HOUR, created_at, NOW())), 0) as total_hours
                FROM attendance
                WHERE student_id = ? AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $stmt->execute([$student['id']]);
            $training_hours = $stmt->fetch()['total_hours'];
            if ($training_hours == 0) $training_hours = 12.5; // Default if no data
        } catch (Exception $e) {
            error_log("Error fetching training hours: " . $e->getMessage());
            $training_hours = 12.5;
        }

        // Get matches/performances count
        try {
            if ($student['student_type'] == 'athlete') {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as matches_played
                    FROM team_members tm
                    WHERE tm.student_id = ?
                ");
                $stmt->execute([$student['id']]);
                $result = $stmt->fetch();
                $matches_played = $result['matches_played'] ?? 3;
            } else {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as performances
                    FROM dance_troupe_members dtm
                    WHERE dtm.student_id = ?
                ");
                $stmt->execute([$student['id']]);
                $result = $stmt->fetch();
                $matches_played = $result['performances'] ?? 3;
            }
            if ($matches_played == 0) $matches_played = 3;
        } catch (Exception $e) {
            error_log("Error fetching activities: " . $e->getMessage());
            $matches_played = 3;
        }

        // Get coach/trainer info based on student's sport/troupe
        if ($student['student_type'] == 'athlete' && !empty($student['primary_sport'])) {
            try {
                $stmt = $pdo->prepare("
                    SELECT u.first_name, u.last_name, c.primary_sport, u.email
                    FROM coaches c
                    JOIN users u ON c.user_id = u.id
                    WHERE c.primary_sport = ? AND u.status = 'active'
                    LIMIT 1
                ");
                $stmt->execute([$student['primary_sport']]);
                $coach_info = $stmt->fetch();
            } catch (Exception $e) {
                error_log("Error fetching coach: " . $e->getMessage());
            }
        } else if ($student['student_type'] == 'dancer' && !empty($student['dance_troupe'])) {
            try {
                $stmt = $pdo->prepare("
                    SELECT u.first_name, u.last_name, dc.dance_specialization, u.email
                    FROM dance_coaches dc
                    JOIN users u ON dc.user_id = u.id
                    JOIN dance_troupes dt ON dc.id = dt.coach_id
                    WHERE dt.troupe_name = ? AND u.status = 'active'
                    LIMIT 1
                ");
                $stmt->execute([$student['dance_troupe']]);
                $coach_info = $stmt->fetch();
            } catch (Exception $e) {
                error_log("Error fetching trainer: " . $e->getMessage());
            }
        }

        // Get team players (teammates) - FOR ATHLETES ONLY
        if ($student['student_type'] == 'athlete' && !empty($teams)) {
            $team_ids = array_column($teams, 'id');
            if (!empty($team_ids)) {
                $placeholders = implode(',', array_fill(0, count($team_ids), '?'));
                
                try {
                    $stmt = $pdo->prepare("
                        SELECT u.first_name, u.last_name, s.primary_sport, tm.jersey_number
                        FROM team_members tm
                        JOIN students s ON tm.student_id = s.id
                        JOIN users u ON s.user_id = u.id
                        WHERE tm.team_id IN ($placeholders) 
                        AND tm.student_id != ? 
                        AND tm.status = 'active'
                        ORDER BY u.first_name
                        LIMIT 10
                    ");
                    $params = array_merge($team_ids, [$student['id']]);
                    $stmt->execute($params);
                    $team_players = $stmt->fetchAll();
                    
                    // Add sport info to each player
                    foreach($team_players as &$player) {
                        if (empty($player['primary_sport']) && !empty($teams[0]['sport_name'])) {
                            $player['primary_sport'] = $teams[0]['sport_name'];
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error fetching team players: " . $e->getMessage());
                    $team_players = [];
                }
            }
        }
        // Get troupe members for DANCERS ONLY
        else if ($student['student_type'] == 'dancer' && !empty($troupes)) {
            $troupe_ids = array_column($troupes, 'id');
            if (!empty($troupe_ids)) {
                $placeholders = implode(',', array_fill(0, count($troupe_ids), '?'));
                
                try {
                    $stmt = $pdo->prepare("
                        SELECT u.first_name, u.last_name, dt.troupe_name as primary_sport, dtm.role as position
                        FROM dance_troupe_members dtm
                        JOIN students s ON dtm.student_id = s.id
                        JOIN users u ON s.user_id = u.id
                        JOIN dance_troupes dt ON dtm.troupe_id = dt.id
                        WHERE dtm.troupe_id IN ($placeholders) 
                        AND dtm.student_id != ? 
                        AND dtm.status = 'active'
                        ORDER BY u.first_name
                        LIMIT 10
                    ");
                    $params = array_merge($troupe_ids, [$student['id']]);
                    $stmt->execute($params);
                    $team_players = $stmt->fetchAll();
                } catch (Exception $e) {
                    error_log("Error fetching troupe members: " . $e->getMessage());
                    $team_players = [];
                }
            }
        }

        // Get upcoming matches/events
        try {
            $event_type = ($student['student_type'] == 'athlete') ? 'athletics' : 
                          (($student['student_type'] == 'dancer') ? 'dance' : 'both');
            
            $stmt = $pdo->prepare("
                SELECT 'event' as type, event_date, event_title as title, location, event_time
                FROM upcoming_events 
                WHERE event_date >= CURDATE() AND (event_type = ? OR event_type = 'both')
                ORDER BY event_date ASC
                LIMIT 5
            ");
            $stmt->execute([$event_type]);
            $upcoming_events = $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error fetching events: " . $e->getMessage());
            $upcoming_events = [];
        }
    }
} catch (Exception $e) {
    // Log error but continue with defaults
    error_log("Student dashboard error: " . $e->getMessage());
}

// Get all available teams (for joining)
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
try {
    $available_troupes = $pdo->query("
        SELECT dt.*, CONCAT(u.first_name, ' ', u.last_name) as coach_name
        FROM dance_troupes dt
        LEFT JOIN dance_coaches dc ON dt.coach_id = dc.user_id
        LEFT JOIN users u ON dc.user_id = u.id
        WHERE dt.is_active = 1
        ORDER BY dt.troupe_name
    ")->fetchAll();
} catch (Exception $e) {
    $available_troupes = [];
}

// If no teammates, show appropriate mock data based on student type
if (empty($team_players)) {
    if ($student['student_type'] == 'athlete') {
        $team_players = [
            ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'primary_sport' => $student['primary_sport'] ?? 'Basketball', 'jersey_number' => 10],
            ['first_name' => 'Jose', 'last_name' => 'Rizal', 'primary_sport' => $student['primary_sport'] ?? 'Basketball', 'jersey_number' => 14],
            ['first_name' => 'Andres', 'last_name' => 'Bonifacio', 'primary_sport' => $student['primary_sport'] ?? 'Basketball', 'jersey_number' => 23]
        ];
    } else {
        $team_players = [
            ['first_name' => 'Maria', 'last_name' => 'Santos', 'primary_sport' => $student['dance_troupe'] ?? 'Street Dancers', 'position' => 'Lead Dancer'],
            ['first_name' => 'Ana', 'last_name' => 'Garcia', 'primary_sport' => $student['dance_troupe'] ?? 'Street Dancers', 'position' => 'Member'],
            ['first_name' => 'Sofia', 'last_name' => 'Reyes', 'primary_sport' => $student['dance_troupe'] ?? 'Street Dancers', 'position' => 'Choreographer']
        ];
    }
}

// If no events, show mock data
if (empty($upcoming_events)) {
    if ($student['student_type'] == 'athlete') {
        $upcoming_events = [
            ['type' => 'event', 'event_date' => date('Y-m-d', strtotime('+3 days')), 'title' => 'Team Practice', 'location' => 'Main Gym', 'event_time' => '15:00:00'],
            ['type' => 'event', 'event_date' => date('Y-m-d', strtotime('+7 days')), 'title' => 'Friendly Match', 'location' => 'Sports Complex', 'event_time' => '14:30:00'],
            ['type' => 'event', 'event_date' => date('Y-m-d', strtotime('+14 days')), 'title' => 'Training Camp', 'location' => 'Training Center', 'event_time' => '09:00:00']
        ];
    } else {
        $upcoming_events = [
            ['type' => 'event', 'event_date' => date('Y-m-d', strtotime('+2 days')), 'title' => 'Dance Practice', 'location' => 'Dance Studio', 'event_time' => '16:00:00'],
            ['type' => 'event', 'event_date' => date('Y-m-d', strtotime('+10 days')), 'title' => 'Performance Rehearsal', 'location' => 'Auditorium', 'event_time' => '13:00:00'],
            ['type' => 'event', 'event_date' => date('Y-m-d', strtotime('+21 days')), 'title' => 'Dance Competition', 'location' => 'Cultural Center', 'event_time' => '09:00:00']
        ];
    }
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
$is_pending = (isset($student['status']) && $student['status'] === 'pending');

// Get student initials for avatar
$student_initials = strtoupper(substr($student['first_name'] ?? 'S', 0, 1) . substr($student['last_name'] ?? 'T', 0, 1));
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

        /* ===== MODERN SIDEBAR DESIGN FOR STUDENT ===== */
        .sidebar {
            width: 300px;
            background: linear-gradient(180deg, #0a2540 0%, #1a365d 100%);
            position: fixed;
            height: 100vh;
            padding: 25px 0;
            box-shadow: 10px 0 30px rgba(10, 37, 64, 0.3);
            overflow-y: auto;
            z-index: 100;
        }

        /* Custom scrollbar */
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
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
            background: #10b981;
            color: white;
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
            color: #10b981;
        }

        .student-type-badge {
            display: inline-block;
            background: rgba(255,255,255,0.1);
            color: #10b981;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 8px;
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
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            transform: translateX(5px);
        }

        .sidebar-nav a:hover i {
            color: #10b981;
            transform: scale(1.1);
        }

        /* Active button state */
        .sidebar-nav li.active a {
            background: #10b981;
            color: white;
            font-weight: 600;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }

        .sidebar-nav li.active a i {
            color: white;
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
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .sidebar-nav li.active .badge {
            background: rgba(255,255,255,0.2);
            color: white;
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

        .join-team-btn-dance {
            background: #8B1E3F;
            box-shadow: 0 4px 15px rgba(139, 30, 63, 0.3);
        }

        .join-team-btn-dance:hover {
            background: #6b152f;
            box-shadow: 0 8px 25px rgba(139, 30, 63, 0.4);
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

        /* Teammates Grid */
        .teammates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .teammate-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }

        .teammate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .teammate-avatar {
            width: 80px;
            height: 80px;
            background: white;
            color: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 32px;
            margin: 0 auto 15px;
        }

        .teammate-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .teammate-role {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .teammate-jersey {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
        }

        /* Events Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .event-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #10b981;
            transition: all 0.3s;
        }

        .event-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .event-date {
            color: #10b981;
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .event-title {
            font-size: 18px;
            font-weight: 600;
            color: #0a2540;
            margin-bottom: 10px;
        }

        .event-location {
            color: #718096;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .event-time {
            color: #94a3b8;
            font-size: 14px;
        }

        /* Info Box */
        .info-box {
            background: #e0f2fe;
            border-left: 4px solid #0284c7;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            color: #0369a1;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-box i {
            font-size: 20px;
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

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .page-header h2 {
            font-size: 24px;
            color: #0a2540;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h2 i {
            color: #10b981;
        }

        .back-btn {
            padding: 8px 20px;
            background: #e2e8f0;
            color: #4a5568;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #cbd5e0;
            transform: translateX(-3px);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
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
            .student-type-badge,
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
            
            .teams-grid,
            .teammates-grid,
            .events-grid {
                grid-template-columns: 1fr;
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
        <!-- MODERN SIDEBAR FOR STUDENT -->
        <div class="sidebar">
            <!-- User Profile Section -->
            <div class="user-profile">
                <div class="user-avatar-large">
                    <span><?php echo $student_initials; ?></span>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($student['first_name'] ?? 'Student') . ' ' . htmlspecialchars($student['last_name'] ?? ''); ?></div>
                <div class="user-email">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email'] ?? 'student@talentrix.edu'); ?>
                </div>
                <div class="user-role">STUDENT</div>
                <div class="student-type-badge">
                    <i class="fas fa-<?php echo $student['student_type'] == 'athlete' ? 'running' : 'music'; ?>"></i>
                    <?php echo ucfirst($student['student_type']); ?>
                </div>
            </div>

            <!-- MAIN MENU SECTION -->
            <div class="nav-section">MAIN</div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php echo ($page == 'home') ? 'active' : ''; ?>">
                        <a href="?page=home">
                            <i class="fas fa-home"></i>
                            <span>Home</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- MY ACTIVITIES SECTION -->
            <div class="nav-section">MY ACTIVITIES</div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php echo ($page == 'teammates') ? 'active' : ''; ?>">
                        <a href="?page=teammates">
                            <i class="fas fa-users"></i>
                            <span><?php echo $student['student_type'] == 'athlete' ? 'Teammates' : 'Fellow Dancers'; ?></span>
                            <?php if(count($team_players) > 0): ?>
                            <span class="badge"><?php echo count($team_players); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="<?php echo ($page == 'events') ? 'active' : ''; ?>">
                        <a href="?page=events">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Events</span>
                            <?php if(count($upcoming_events) > 0): ?>
                            <span class="badge"><?php echo count($upcoming_events); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- MY TEAMS SECTION -->
            <div class="nav-section">MY TEAMS</div>
            <nav class="sidebar-nav">
                <ul>
                    <?php if($student['student_type'] == 'athlete' || $student['student_type'] == 'both'): ?>
                        <?php foreach(array_slice($teams, 0, 3) as $team): ?>
                        <li>
                            <a href="team_details.php?id=<?php echo $team['id']; ?>">
                                <i class="fas fa-trophy"></i>
                                <span><?php echo htmlspecialchars($team['team_name']); ?></span>
                                <span class="badge" style="background: #10b981;">#<?php echo $team['jersey_number'] ?? '00'; ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if($student['student_type'] == 'dancer' || $student['student_type'] == 'both'): ?>
                        <?php foreach(array_slice($troupes, 0, 3) as $troupe): ?>
                        <li>
                            <a href="troupe_details.php?id=<?php echo $troupe['id']; ?>">
                                <i class="fas fa-music"></i>
                                <span><?php echo htmlspecialchars($troupe['troupe_name']); ?></span>
                                <span class="badge" style="background: #8B1E3F;"><?php echo $troupe['role'] ?? 'Member'; ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if(empty($teams) && empty($troupes)): ?>
                    <li>
                        <a href="#">
                            <i class="fas fa-plus-circle"></i>
                            <span>Join a Team</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- ACCOUNT SECTION -->
            <div class="nav-section">ACCOUNT</div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="profile.php">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- WEBSITE SECTION -->
            <div class="nav-section">WEBSITE</div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="index.php">
                            <i class="fas fa-globe"></i>
                            <span>Homepage</span>
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
            <?php if($page == 'home'): ?>
            
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-title">
                    <h1><?php echo $student['student_type'] == 'athlete' ? 'Athlete Dashboard' : 'Dancer Dashboard'; ?></h1>
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
                    <p>Your application has been submitted and is waiting for <?php echo $student['student_type'] == 'athlete' ? 'coach' : 'trainer'; ?> approval. You'll get full access once approved.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Pending Requests Alert -->
            <?php if(!empty($pending_requests) && !$is_pending): ?>
            <div class="alert alert-warning">
                <i class="fas fa-clock" style="font-size: 20px;"></i>
                <div>
                    <strong>Pending Requests:</strong> You have <?php echo count($pending_requests); ?> request(s) waiting for approval.
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

            <!-- Show Coach/Trainer Info -->
            <?php if($coach_info && !$is_pending): ?>
            <div class="info-box" style="margin-top: 15px; background: #f0f9ff; border-left-color: #0ea5e9;">
                <i class="fas fa-user-tie"></i>
                <div>
                    <strong>Your <?php echo $student['student_type'] == 'athlete' ? 'Coach' : 'Trainer'; ?>:</strong>
                    <?php echo htmlspecialchars($coach_info['first_name'] . ' ' . $coach_info['last_name']); ?>
                    <?php if($student['student_type'] == 'athlete'): ?>
                        (<?php echo htmlspecialchars($coach_info['primary_sport']); ?>)
                    <?php else: ?>
                        (<?php echo htmlspecialchars($coach_info['dance_specialization']); ?>)
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Join Team Button (for students without teams) -->
            <?php if(empty($teams) && empty($troupes) && !$is_pending): ?>
            <div style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap;">
                <?php if($student['student_type'] == 'athlete' || $student['student_type'] == 'both'): ?>
                <button class="join-team-btn" onclick="openTeamModal()">
                    <i class="fas fa-plus-circle"></i> Join a Sports Team
                </button>
                <?php endif; ?>
                <?php if($student['student_type'] == 'dancer' || $student['student_type'] == 'both'): ?>
                <button class="join-team-btn join-team-btn-dance" onclick="openTroupeModal()">
                    <i class="fas fa-plus-circle"></i> Join a Dance Troupe
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3><?php echo $student['student_type'] == 'athlete' ? 'TRAINING HOURS' : 'PRACTICE HOURS'; ?></h3>
                        <i class="fas fa-clock" style="color: #10b981;"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($training_hours, 1); ?>h</div>
                    <div class="stat-change">Last 30 days</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <h3><?php echo $student['student_type'] == 'athlete' ? 'MATCHES' : 'PERFORMANCES'; ?></h3>
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
                        <a href="?page=events" class="view-all">View All →</a>
                    </div>
                    
                    <div class="schedule-list">
                        <?php foreach(array_slice($upcoming_events, 0, 3) as $event): ?>
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

                <!-- Right Column: Teammates/Fellow Dancers -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> 
                            <?php echo $student['student_type'] == 'athlete' ? 'Teammates' : 'Fellow Dancers'; ?>
                        </h3>
                        <a href="?page=teammates" class="view-all">View All →</a>
                    </div>
                    
                    <div class="team-players">
                        <?php if(!empty($team_players)): ?>
                            <?php foreach(array_slice($team_players, 0, 5) as $player): ?>
                            <div class="player-item">
                                <div class="player-avatar">
                                    <?php echo strtoupper(substr($player['first_name'], 0, 1) . substr($player['last_name'] ?? '', 0, 1)); ?>
                                </div>
                                <div class="player-info">
                                    <div class="player-name"><?php echo htmlspecialchars($player['first_name'] . ' ' . ($player['last_name'] ?? '')); ?></div>
                                    <div class="player-sport">
                                        <?php 
                                        if ($student['student_type'] == 'athlete') {
                                            echo htmlspecialchars($player['primary_sport'] ?? $student['primary_sport'] ?? 'Athlete');
                                        } else {
                                            echo htmlspecialchars($player['primary_sport'] ?? 'Dancer');
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php if(isset($player['jersey_number'])): ?>
                                <span class="player-jersey">#<?php echo $player['jersey_number']; ?></span>
                                <?php elseif(isset($player['position'])): ?>
                                <span class="player-jersey" style="background: #fef3c7; color: #92400e;"><?php echo $player['position']; ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-friends"></i>
                                <p>No <?php echo $student['student_type'] == 'athlete' ? 'teammates' : 'fellow dancers'; ?> yet</p>
                            </div>
                        <?php endif; ?>
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
                    <p style="color: #718096; margin-bottom: 20px;">You haven't joined any <?php echo $student['student_type'] == 'athlete' ? 'teams' : 'troupes'; ?> yet.</p>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <?php if($student['student_type'] == 'athlete' || $student['student_type'] == 'both'): ?>
                        <button class="join-team-btn" style="padding: 10px 20px; font-size: 14px;" onclick="openTeamModal()">
                            <i class="fas fa-plus-circle"></i> Join Sports Team
                        </button>
                        <?php endif; ?>
                        <?php if($student['student_type'] == 'dancer' || $student['student_type'] == 'both'): ?>
                        <button class="join-team-btn join-team-btn-dance" style="padding: 10px 20px; font-size: 14px;" onclick="openTroupeModal()">
                            <i class="fas fa-plus-circle"></i> Join Dance Troupe
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php elseif($page == 'teammates'): ?>

            <!-- Teammates Page -->
            <div class="page-header">
                <h2><i class="fas fa-users"></i> 
                    <?php echo $student['student_type'] == 'athlete' ? 'My Teammates' : 'My Fellow Dancers'; ?>
                </h2>
                <a href="?page=home" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <div class="dashboard-card">
                <div class="teammates-grid">
                    <?php if(!empty($team_players)): ?>
                        <?php foreach($team_players as $player): ?>
                        <div class="teammate-card">
                            <div class="teammate-avatar">
                                <?php echo strtoupper(substr($player['first_name'], 0, 1) . substr($player['last_name'] ?? '', 0, 1)); ?>
                            </div>
                            <div class="teammate-name"><?php echo htmlspecialchars($player['first_name'] . ' ' . ($player['last_name'] ?? '')); ?></div>
                            <div class="teammate-role">
                                <?php 
                                if ($student['student_type'] == 'athlete') {
                                    echo htmlspecialchars($player['primary_sport'] ?? $student['primary_sport'] ?? 'Athlete');
                                } else {
                                    echo htmlspecialchars($player['primary_sport'] ?? 'Dancer');
                                }
                                ?>
                            </div>
                            <?php if(isset($player['jersey_number'])): ?>
                            <div class="teammate-jersey">#<?php echo $player['jersey_number']; ?></div>
                            <?php elseif(isset($player['position'])): ?>
                            <div class="teammate-jersey"><?php echo $player['position']; ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="grid-column: 1/-1;">
                            <i class="fas fa-user-friends"></i>
                            <p>No <?php echo $student['student_type'] == 'athlete' ? 'teammates' : 'fellow dancers'; ?> found</p>
                            <small>
                                <?php if($student['student_type'] == 'athlete'): ?>
                                    Join a team to see your teammates here
                                <?php else: ?>
                                    Join a dance troupe to see your fellow dancers here
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif($page == 'events'): ?>

            <!-- Events Page -->
            <div class="page-header">
                <h2><i class="fas fa-calendar-alt"></i> Upcoming Events</h2>
                <a href="?page=home" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <div class="events-grid">
                <?php if(!empty($upcoming_events)): ?>
                    <?php foreach($upcoming_events as $event): ?>
                    <div class="event-card">
                        <div class="event-date">
                            <i class="fas fa-calendar-day"></i> <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                        </div>
                        <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                        <div class="event-location">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location'] ?? 'TBA'); ?>
                        </div>
                        <?php if(isset($event['event_time']) && !empty($event['event_time'])): ?>
                        <div class="event-time">
                            <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($event['event_time'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="grid-column: 1/-1; background: white; border-radius: 15px; padding: 60px;">
                        <i class="fas fa-calendar-times"></i>
                        <p>No upcoming events</p>
                        <small>Check back later for scheduled events</small>
                    </div>
                <?php endif; ?>
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
        console.log('Student dashboard loaded - Type: <?php echo $student['student_type']; ?>');
        
        // Add hover effects to stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    });
    </script>
</body>
</html>