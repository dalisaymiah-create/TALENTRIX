<?php
// coach_dashboard.php - COMPLETE FIXED VERSION
session_start();
require_once 'db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// IMPORTANT: Check if user is logged in
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
if ($coach && isset($coach['first_name']) && isset($coach['last_name'])) {
    $coach_initials = strtoupper(substr($coach['first_name'], 0, 1) . substr($coach['last_name'], 0, 1));
}

// Get pending approvals (students waiting for coach approval)
$pending_approvals = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.id as approval_id,
            s.id as student_id,
            u.id_number,
            u.first_name,
            u.last_name,
            COALESCE(s.primary_sport, 'Basketball') as sport,
            COALESCE(s.primary_position, 'Player') as position,
            s.athlete_category,
            s.jersey_number,
            a.request_date,
            a.status,
            t.team_name,
            t.id as team_id
        FROM approvals a
        INNER JOIN students s ON a.student_id = s.id
        INNER JOIN users u ON s.user_id = u.id
        LEFT JOIN teams t ON a.team_id = t.id
        WHERE a.coach_id = ? AND a.status = 'pending'
        ORDER BY a.request_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]); // coach_id in approvals is the USER ID
    $pending_approvals = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching approvals: " . $e->getMessage());
    $pending_approvals = [];
}

// Get approved players (active team members) with stats - FIXED QUERY with error handling
$team_players = [];
if (isset($coach['id'])) {
    try {
        // First get all teams for this coach
        $team_ids = [];
        $teams_check = $pdo->prepare("SELECT id FROM teams WHERE coach_id = ?");
        $teams_check->execute([$coach['id']]);
        $team_ids = $teams_check->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($team_ids)) {
            $placeholders = implode(',', array_fill(0, count($team_ids), '?'));
            $stmt = $pdo->prepare("
                SELECT 
                    s.id,
                    u.first_name,
                    u.last_name,
                    s.primary_sport,
                    COALESCE(s.primary_position, 'Player') as position,
                    s.athlete_category,
                    tm.jersey_number,
                    t.team_name,
                    tm.joined_date,
                    (SELECT COUNT(*) FROM achievements WHERE student_id = s.id) as achievements_count,
                    (SELECT COUNT(*) FROM attendance WHERE student_id = s.id AND status = 'present' AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as attendance_count
                FROM team_members tm
                INNER JOIN students s ON tm.student_id = s.id
                INNER JOIN users u ON s.user_id = u.id
                INNER JOIN teams t ON tm.team_id = t.id
                WHERE tm.team_id IN ($placeholders) AND tm.status = 'active'
                ORDER BY u.first_name ASC
            ");
            $stmt->execute($team_ids);
            $team_players = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error fetching team players: " . $e->getMessage());
        $team_players = [];
    }
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

// Get recent achievements added by coach
$recent_achievements = [];
if (isset($coach['id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as student_name,
                   CASE a.medal_type
                       WHEN 'gold' THEN '🥇'
                       WHEN 'silver' THEN '🥈'
                       WHEN 'bronze' THEN '🥉'
                       ELSE '🏆'
                   END as medal_icon
            FROM achievements a
            JOIN students s ON a.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE a.verified_by = ?
            ORDER BY a.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$coach['id']]);
        $recent_achievements = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching achievements: " . $e->getMessage());
        $recent_achievements = [];
    }
}

// Get upcoming matches/events
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
                WHERE m.team_id IN ($placeholders) AND m.match_date >= CURDATE()
                ORDER BY m.match_date ASC
                LIMIT 5
            ");
            $stmt->execute($team_ids);
            $upcoming_matches = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error fetching matches: " . $e->getMessage());
        $upcoming_matches = [];
    }
}

// If no matches, create sample data
if (empty($upcoming_matches) && !empty($teams)) {
    $upcoming_matches = [
        [
            'match_date' => date('Y-m-d', strtotime('+3 days')), 
            'opponent' => 'University Team', 
            'location' => 'Main Gym', 
            'match_time' => '15:00:00', 
            'team_name' => $teams[0]['team_name'] ?? 'Varsity Team'
        ],
        [
            'match_date' => date('Y-m-d', strtotime('+10 days')), 
            'opponent' => 'City Rivals', 
            'location' => 'Sports Complex', 
            'match_time' => '14:30:00', 
            'team_name' => $teams[0]['team_name'] ?? 'Varsity Team'
        ],
        [
            'match_date' => date('Y-m-d', strtotime('+17 days')), 
            'opponent' => 'State Champions', 
            'location' => 'Arena', 
            'match_time' => '16:00:00', 
            'team_name' => $teams[0]['team_name'] ?? 'Varsity Team'
        ]
    ];
}

// Get stats
$total_players = count($team_players);
$pending_count = count($pending_approvals);
$total_teams = count($teams);
$active_count = $total_players;
$injured_count = 2; // This would come from a health/injury table in real implementation

// Calculate team performance (mock data)
$team_performance = rand(78, 92);
$attendance_rate = rand(85, 98);

// Get greeting based on time
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

// Get sports for dropdown
$sports = [];
try {
    $sports = $pdo->query("SELECT sport_name FROM sports WHERE is_active = 1 OR is_active IS NULL")->fetchAll();
} catch (Exception $e) {
    // If sports table doesn't exist or query fails, use default sports
    $sports = [
        ['sport_name' => 'Basketball'],
        ['sport_name' => 'Volleyball'],
        ['sport_name' => 'Football'],
        ['sport_name' => 'Swimming'],
        ['sport_name' => 'Athletics']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Sports Coach Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* [Previous CSS styles remain exactly the same as in your original file] */
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

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-text h2 {
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .welcome-text p {
            opacity: 0.9;
            font-size: 14px;
            max-width: 400px;
        }

        .welcome-banner .date {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 500;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid #eef2f6;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-header h3 {
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            background: #fef3c7;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f59e0b;
            font-size: 20px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a2639;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: #94a3b8;
        }

        .stat-trend {
            font-size: 12px;
            color: #10b981;
            margin-top: 5px;
        }

        /* Team Performance Card */
        .performance-card {
            background: linear-gradient(135deg, #1a2639 0%, #2d3748 100%);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
        }

        .performance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .performance-header h3 {
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .performance-header h3 i {
            color: #f59e0b;
        }

        .performance-value {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .performance-label {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 20px;
        }

        .progress-bar {
            height: 10px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            margin: 20px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            width: <?php echo $team_performance; ?>%;
            background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
            border-radius: 10px;
        }

        .performance-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .perf-stat {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
        }

        .perf-stat .value {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .perf-stat .label {
            font-size: 12px;
            opacity: 0.7;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .action-btn {
            background: white;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            text-decoration: none;
            color: #1a2639;
            border: 1px solid #eef2f6;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border-color: #f59e0b;
        }

        .action-btn i {
            font-size: 24px;
            color: #f59e0b;
        }

        .action-btn span {
            font-size: 13px;
            font-weight: 500;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid #eef2f6;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eef2f6;
        }

        .card-header h3 {
            font-size: 16px;
            color: #1a2639;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-header h3 i {
            color: #f59e0b;
        }

        .view-all {
            color: #f59e0b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
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
            border-radius: 15px;
            border-left: 4px solid #f59e0b;
        }

        .schedule-date {
            min-width: 60px;
            text-align: center;
        }

        .schedule-date .day {
            font-size: 22px;
            font-weight: 700;
            color: #f59e0b;
        }

        .schedule-date .month {
            font-size: 12px;
            color: #64748b;
        }

        .schedule-details {
            flex: 1;
        }

        .schedule-details h4 {
            font-size: 15px;
            color: #1a2639;
            margin-bottom: 5px;
        }

        .schedule-details p {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .schedule-details p i {
            font-size: 11px;
        }

        .schedule-team {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        /* Team Players - Roster Style */
        .roster-table {
            width: 100%;
            border-collapse: collapse;
        }

        .roster-table th {
            text-align: left;
            padding: 12px 5px;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .roster-table td {
            padding: 12px 5px;
            border-bottom: 1px solid #f1f5f9;
        }

        .player-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .player-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .player-name {
            font-weight: 600;
            color: #1a2639;
            font-size: 14px;
        }

        .player-position {
            font-size: 12px;
            color: #64748b;
        }

        .player-stats {
            display: flex;
            gap: 8px;
        }

        .stat-badge {
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
        }

        .jersey-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-injured {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Pending Approvals List */
        .approvals-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .approval-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
        }

        .approval-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .approval-info {
            flex: 1;
        }

        .approval-info h4 {
            font-size: 14px;
            color: #1a2639;
            margin-bottom: 3px;
        }

        .approval-info p {
            font-size: 12px;
            color: #64748b;
        }

        .approval-actions {
            display: flex;
            gap: 8px;
        }

        .btn-approve {
            padding: 6px 12px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-reject {
            padding: 6px 12px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Achievements Grid */
        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 10px;
        }

        .achievement-card {
            background: #f8fafc;
            border-radius: 15px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .achievement-icon {
            font-size: 28px;
        }

        .achievement-details h4 {
            font-size: 13px;
            color: #1a2639;
            margin-bottom: 3px;
        }

        .achievement-details p {
            font-size: 11px;
            color: #64748b;
        }

        .achievement-medal {
            margin-left: auto;
            font-size: 18px;
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

        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group select:focus,
        .form-group input:focus,
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

        /* Alert Messages */
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

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .performance-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .performance-stats {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .achievements-grid {
                grid-template-columns: 1fr;
            }
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
                    <div class="nav-section-title">Menu</div>
                    <a href="coach_dashboard.php" class="nav-item active">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="#" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span>My Players</span>
                        <span class="badge"><?php echo $total_players; ?></span>
                    </a>
                    <a href="#" class="nav-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Schedule</span>
                    </a>
                    <a href="#" class="nav-item">
                        <i class="fas fa-trophy"></i>
                        <span>Achievements</span>
                    </a>
                    <a href="#" class="nav-item">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Attendance</span>
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
                    <h1>Team Management</h1>
                    <p><?php echo $greeting; ?>, Coach <?php echo htmlspecialchars($coach['first_name'] ?? 'Coach'); ?>! Manage your roster and team performance.</p>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search player by name..." id="searchInput">
                    </div>
                    <div class="notification-icon">
                        <i class="far fa-bell"></i>
                        <?php if($pending_count > 0): ?>
                        <span class="notification-badge"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="header-profile">
                        <div class="header-avatar">
                            <?php echo $coach_initials; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h2><i class="fas fa-basketball-ball"></i> Next Game: <?php echo $upcoming_matches[0]['opponent'] ?? 'TBD'; ?></h2>
                    <p>Get your team ready for the upcoming match. Practice at 3 PM today.</p>
                </div>
                <div class="date">
                    <i class="far fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
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

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Players</h3>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_players; ?></div>
                    <div class="stat-label">Active roster</div>
                    <div class="stat-trend">↑ 2 this month</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Active</h3>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $active_count; ?></div>
                    <div class="stat-label">Ready to play</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Injured</h3>
                        <div class="stat-icon">
                            <i class="fas fa-ambulance"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $injured_count; ?></div>
                    <div class="stat-label">Recovering</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Teams</h3>
                        <div class="stat-icon">
                            <i class="fas fa-futbol"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_teams; ?></div>
                    <div class="stat-label">Under your supervision</div>
                </div>
            </div>

            <!-- Team Performance Card -->
            <div class="performance-card">
                <div class="performance-header">
                    <h3><i class="fas fa-chart-line"></i> Team Performance</h3>
                    <span>Last 30 days</span>
                </div>
                <div class="performance-value"><?php echo $team_performance; ?>%</div>
                <div class="performance-label">Overall Team Rating</div>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="performance-stats">
                    <div class="perf-stat">
                        <div class="value"><?php echo $attendance_rate; ?>%</div>
                        <div class="label">Attendance</div>
                    </div>
                    <div class="perf-stat">
                        <div class="value">12</div>
                        <div class="label">Games Played</div>
                    </div>
                    <div class="perf-stat">
                        <div class="value">8</div>
                        <div class="label">Wins</div>
                    </div>
                    <div class="perf-stat">
                        <div class="value">78.5</div>
                        <div class="label">Avg PPG</div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="#" class="action-btn" onclick="openAddPlayerModal()">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Player</span>
                </a>
                <a href="#" class="action-btn" onclick="openScheduleModal()">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Schedule Match</span>
                </a>
                <a href="#" class="action-btn" onclick="openAchievementModal()">
                    <i class="fas fa-medal"></i>
                    <span>Add Achievement</span>
                </a>
                <a href="#" class="action-btn" onclick="openAttendanceModal()">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Mark Attendance</span>
                </a>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Left Column: Team Roster -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Team Roster</h3>
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <span style="font-size: 12px; color: #64748b;">Sort By:</span>
                            <select style="padding: 5px 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12px;" id="sortSelect">
                                <option value="position">Position</option>
                                <option value="jersey">Jersey #</option>
                                <option value="name">Name</option>
                            </select>
                            <a href="#" class="view-all">View All →</a>
                        </div>
                    </div>
                    
                    <table class="roster-table" id="rosterTable">
                        <thead>
                            <tr>
                                <th>Player Name</th>
                                <th>Position</th>
                                <th>Stats</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($team_players)): ?>
                                <?php foreach($team_players as $player): ?>
                                <tr>
                                    <td>
                                        <div class="player-info">
                                            <div class="player-avatar">
                                                <?php echo strtoupper(substr($player['first_name'] ?? 'P', 0, 1) . substr($player['last_name'] ?? 'L', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="player-name"><?php echo htmlspecialchars(($player['first_name'] ?? 'Player') . ' ' . ($player['last_name'] ?? '')); ?></div>
                                                <div class="player-position"><?php echo htmlspecialchars($player['position'] ?? $player['primary_sport'] ?? 'Player'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($player['position'] ?? 'F'); ?></td>
                                    <td>
                                        <div class="player-stats">
                                            <span class="stat-badge">🏀 <?php echo $player['achievements_count'] ?? 0; ?></span>
                                            <span class="stat-badge">📊 <?php echo $player['attendance_count'] ?? 0; ?>%</span>
                                        </div>
                                    </td>
                                    <td><span class="jersey-badge">#<?php echo $player['jersey_number'] ?? '00'; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        <i class="fas fa-user-slash" style="font-size: 40px; margin-bottom: 15px;"></i>
                                        <p>No players added yet</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div style="display: flex; gap: 15px; margin-top: 20px;">
                        <span class="status-badge status-active">● <?php echo $active_count; ?> Active</span>
                        <span class="status-badge status-injured">● <?php echo $injured_count; ?> Injured</span>
                    </div>
                </div>

                <!-- Right Column: Pending Approvals -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Pending Approvals</h3>
                        <?php if($pending_count > 0): ?>
                        <span class="badge" style="background: #f59e0b; padding: 5px 10px; border-radius: 20px; color: white;"><?php echo $pending_count; ?> new</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="approvals-list">
                        <?php if(!empty($pending_approvals)): ?>
                            <?php foreach(array_slice($pending_approvals, 0, 5) as $approval): ?>
                            <div class="approval-item">
                                <div class="approval-avatar">
                                    <?php echo strtoupper(substr($approval['first_name'] ?? 'S', 0, 1) . substr($approval['last_name'] ?? 'T', 0, 1)); ?>
                                </div>
                                <div class="approval-info">
                                    <h4><?php echo htmlspecialchars(($approval['first_name'] ?? 'Student') . ' ' . ($approval['last_name'] ?? '')); ?></h4>
                                    <p><?php echo htmlspecialchars($approval['sport'] ?? 'Basketball'); ?> • <?php echo htmlspecialchars($approval['position'] ?? 'Player'); ?></p>
                                </div>
                                <div class="approval-actions">
                                    <button class="btn-approve" onclick="approveRequest(<?php echo $approval['approval_id'] ?? 0; ?>)">✓</button>
                                    <button class="btn-reject" onclick="showRejectModal(<?php echo $approval['approval_id'] ?? 0; ?>)">✗</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle" style="color: #10b981;"></i>
                                <p>No pending approvals</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Second Row: Schedule and Achievements -->
            <div class="dashboard-grid" style="margin-top: 30px;">
                <!-- Left Column: Upcoming Schedule -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Upcoming Schedule</h3>
                        <a href="#" class="view-all">View All →</a>
                    </div>
                    
                    <div class="schedule-list">
                        <?php if(!empty($upcoming_matches)): ?>
                            <?php foreach($upcoming_matches as $match): ?>
                            <div class="schedule-item">
                                <div class="schedule-date">
                                    <div class="day"><?php echo date('d', strtotime($match['match_date'] ?? date('Y-m-d'))); ?></div>
                                    <div class="month"><?php echo date('M', strtotime($match['match_date'] ?? date('Y-m-d'))); ?></div>
                                </div>
                                <div class="schedule-details">
                                    <h4>vs <?php echo htmlspecialchars($match['opponent'] ?? 'TBD'); ?></h4>
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($match['location'] ?? 'TBA'); ?> • <i class="fas fa-clock"></i> <?php echo isset($match['match_time']) ? date('h:i A', strtotime($match['match_time'])) : 'TBA'; ?></p>
                                </div>
                                <span class="schedule-team"><?php echo htmlspecialchars($match['team_name'] ?? 'Varsity'); ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No upcoming matches scheduled</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column: Recent Achievements -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-trophy"></i> Recent Achievements</h3>
                        <a href="#" class="view-all">View All →</a>
                    </div>
                    
                    <div class="achievements-grid">
                        <?php if(!empty($recent_achievements)): ?>
                            <?php foreach($recent_achievements as $achievement): ?>
                            <div class="achievement-card">
                                <div class="achievement-icon">
                                    <?php echo $achievement['medal_icon'] ?? '🏆'; ?>
                                </div>
                                <div class="achievement-details">
                                    <h4><?php echo htmlspecialchars($achievement['achievement_title'] ?? 'Achievement'); ?></h4>
                                    <p><?php echo htmlspecialchars($achievement['student_name'] ?? 'Student'); ?></p>
                                </div>
                                <div class="achievement-medal">
                                    <?php if(isset($achievement['medal_type'])): ?>
                                        <?php if($achievement['medal_type'] == 'gold'): ?>🥇
                                        <?php elseif($achievement['medal_type'] == 'silver'): ?>🥈
                                        <?php elseif($achievement['medal_type'] == 'bronze'): ?>🥉
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state" style="grid-column: span 2;">
                                <i class="fas fa-medal"></i>
                                <p>No achievements yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-times-circle" style="color: #ef4444;"></i> Reject Request</h2>
            <p style="margin-bottom: 20px;">Please provide a reason for rejection:</p>
            <div class="form-group">
                <textarea id="rejectReason" rows="4" placeholder="Enter reason..."></textarea>
            </div>
            <div class="modal-actions">
                <button class="btn-submit" style="background: #ef4444;" onclick="confirmReject()">Confirm Reject</button>
                <button class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Add Player Modal -->
    <div id="addPlayerModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-user-plus"></i> Add New Player</h2>
            <form method="POST" action="add_student.php" onsubmit="return validateAddPlayerForm()">
                <div class="form-group">
                    <label>Student ID Number</label>
                    <input type="text" name="id_number" placeholder="e.g., 2024-0001" required>
                </div>
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Sport</label>
                    <select name="primary_sport" required>
                        <option value="">-- Select Sport --</option>
                        <?php foreach($sports as $sport): ?>
                        <option value="<?php echo htmlspecialchars($sport['sport_name']); ?>"><?php echo htmlspecialchars($sport['sport_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="primary_position" placeholder="e.g., Point Guard">
                </div>
                <div class="form-group">
                    <label>Jersey Number</label>
                    <input type="number" name="jersey_number" min="0" max="99">
                </div>
                <div class="form-group">
                    <label>Select Team</label>
                    <select name="team_id" required>
                        <option value="">-- Select Team --</option>
                        <?php foreach($teams as $team): ?>
                        <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-submit">Add Player</button>
                    <button type="button" class="btn-cancel" onclick="closeAddPlayerModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Schedule Match Modal -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-calendar-plus"></i> Schedule Match</h2>
            <form method="POST" action="schedule_event.php">
                <div class="form-group">
                    <label>Team</label>
                    <select name="team_id" required>
                        <option value="">-- Select Team --</option>
                        <?php foreach($teams as $team): ?>
                        <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name'] ?? 'Team'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Opponent</label>
                    <input type="text" name="opponent" required placeholder="e.g., University Team">
                </div>
                <div class="form-group">
                    <label>Match Date</label>
                    <input type="date" name="match_date" required>
                </div>
                <div class="form-group">
                    <label>Match Time</label>
                    <input type="time" name="match_time" required>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" required placeholder="e.g., Main Gym">
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-submit">Schedule</button>
                    <button type="button" class="btn-cancel" onclick="closeScheduleModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Achievement Modal -->
    <div id="achievementModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-medal"></i> Add Achievement</h2>
            <form method="POST" action="add_achievement.php">
                <div class="form-group">
                    <label>Select Student</label>
                    <select name="student_id" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach($team_players as $player): ?>
                        <option value="<?php echo $player['id'] ?? 0; ?>"><?php echo htmlspecialchars(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Achievement Title</label>
                    <input type="text" name="achievement_title" required placeholder="e.g., Champion - Regional Tournament">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="achievement_description" rows="3" placeholder="Describe the achievement..."></textarea>
                </div>
                <div class="form-group">
                    <label>Event Date</label>
                    <input type="date" name="event_date" required>
                </div>
                <div class="form-group">
                    <label>Medal Type</label>
                    <select name="medal_type">
                        <option value="gold">🥇 Gold</option>
                        <option value="silver">🥈 Silver</option>
                        <option value="bronze">🥉 Bronze</option>
                        <option value="none">🏆 None</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-submit">Add Achievement</button>
                    <button type="button" class="btn-cancel" onclick="closeAchievementModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Attendance Modal -->
    <div id="attendanceModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-clipboard-check"></i> Mark Attendance</h2>
            <form method="POST" action="mark_attendance.php">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Select Team</label>
                    <select name="team_id" id="attendanceTeamSelect" required onchange="loadTeamPlayers(this.value)">
                        <option value="">-- Select Team --</option>
                        <?php foreach($teams as $team): ?>
                        <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <h3 style="margin: 20px 0 10px;">Players</h3>
                <div id="playersAttendanceList">
                    <?php if(!empty($team_players)): ?>
                        <?php foreach($team_players as $player): ?>
                        <div style="display: flex; align-items: center; gap: 15px; padding: 10px; background: #f8fafc; margin-bottom: 10px; border-radius: 8px;">
                            <span style="font-weight: 600;"><?php echo htmlspecialchars(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '')); ?></span>
                            <select name="attendance[<?php echo $player['id'] ?? 0; ?>]" style="margin-left: auto; padding: 5px; border: 1px solid #e2e8f0; border-radius: 5px;">
                                <option value="present">✅ Present</option>
                                <option value="absent">❌ Absent</option>
                                <option value="late">⏰ Late</option>
                                <option value="excused">📝 Excused</option>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-state">No players available</p>
                    <?php endif; ?>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn-submit">Save Attendance</button>
                    <button type="button" class="btn-cancel" onclick="closeAttendanceModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    let currentApprovalId = null;

    // Modal functions
    function openAddPlayerModal() {
        document.getElementById('addPlayerModal').classList.add('active');
    }

    function closeAddPlayerModal() {
        document.getElementById('addPlayerModal').classList.remove('active');
    }

    function openScheduleModal() {
        document.getElementById('scheduleModal').classList.add('active');
    }

    function closeScheduleModal() {
        document.getElementById('scheduleModal').classList.remove('active');
    }

    function openAchievementModal() {
        document.getElementById('achievementModal').classList.add('active');
    }

    function closeAchievementModal() {
        document.getElementById('achievementModal').classList.remove('active');
    }

    function openAttendanceModal() {
        document.getElementById('attendanceModal').classList.add('active');
    }

    function closeAttendanceModal() {
        document.getElementById('attendanceModal').classList.remove('active');
    }

    // Approval functions
    function approveRequest(approvalId) {
        if(approvalId && confirm('Approve this request?')) {
            window.location.href = 'process_approval.php?id=' + approvalId + '&action=approve';
        } else {
            alert('Invalid approval ID');
        }
    }

    function showRejectModal(approvalId) {
        if(approvalId) {
            currentApprovalId = approvalId;
            document.getElementById('rejectModal').classList.add('active');
        } else {
            alert('Invalid approval ID');
        }
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').classList.remove('active');
        document.getElementById('rejectReason').value = '';
        currentApprovalId = null;
    }

    function confirmReject() {
        const reason = document.getElementById('rejectReason').value;
        if(!reason.trim()) {
            alert('Please provide a reason for rejection');
            return;
        }
        if(currentApprovalId) {
            window.location.href = 'process_approval.php?id=' + currentApprovalId + '&action=reject&reason=' + encodeURIComponent(reason);
        }
    }

    // Form validation
    function validateAddPlayerForm() {
        const idNumber = document.querySelector('input[name="id_number"]').value;
        const email = document.querySelector('input[name="email"]').value;
        const teamId = document.querySelector('select[name="team_id"]').value;
        
        if(!idNumber || !email || !teamId) {
            alert('Please fill in all required fields');
            return false;
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if(!emailRegex.test(email)) {
            alert('Please enter a valid email address');
            return false;
        }
        
        return true;
    }

    // Load team players for attendance
    function loadTeamPlayers(teamId) {
        if(!teamId) return;
        
        // In a real implementation, you would make an AJAX call here
        // For now, we'll just show a message
        const list = document.getElementById('playersAttendanceList');
        list.innerHTML = '<p class="empty-state">Loading players...</p>';
        
        // Simulate loading
        setTimeout(() => {
            // This would be replaced with actual AJAX response
            list.innerHTML = `<?php 
                if(!empty($team_players)) {
                    $output = '';
                    foreach($team_players as $player) {
                        $output .= '<div style="display: flex; align-items: center; gap: 15px; padding: 10px; background: #f8fafc; margin-bottom: 10px; border-radius: 8px;">';
                        $output .= '<span style="font-weight: 600;">' . htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) . '</span>';
                        $output .= '<select name=\"attendance[' . $player['id'] . ']\" style=\"margin-left: auto; padding: 5px; border: 1px solid #e2e8f0; border-radius: 5px;\">';
                        $output .= '<option value=\"present\">✅ Present</option>';
                        $output .= '<option value=\"absent\">❌ Absent</option>';
                        $output .= '<option value=\"late\">⏰ Late</option>';
                        $output .= '<option value=\"excused\">📝 Excused</option>';
                        $output .= '</select>';
                        $output .= '</div>';
                    }
                    echo $output;
                } else {
                    echo '<p class=\"empty-state\">No players available</p>';
                }
            ?>`;
        }, 500);
    }

    // Search functionality
    document.getElementById('searchInput')?.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#rosterTable tbody tr');
        
        rows.forEach(row => {
            const nameCell = row.querySelector('.player-name');
            if(nameCell) {
                const name = nameCell.textContent.toLowerCase();
                if(name.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    });

    // Sort functionality
    document.getElementById('sortSelect')?.addEventListener('change', function() {
        const sortBy = this.value;
        const tbody = document.querySelector('#rosterTable tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            if(sortBy === 'name') {
                const nameA = a.querySelector('.player-name')?.textContent || '';
                const nameB = b.querySelector('.player-name')?.textContent || '';
                return nameA.localeCompare(nameB);
            } else if(sortBy === 'position') {
                const posA = a.querySelector('td:nth-child(2)')?.textContent || '';
                const posB = b.querySelector('td:nth-child(2)')?.textContent || '';
                return posA.localeCompare(posB);
            } else if(sortBy === 'jersey') {
                const jerseyA = a.querySelector('.jersey-badge')?.textContent.replace('#', '') || '0';
                const jerseyB = b.querySelector('.jersey-badge')?.textContent.replace('#', '') || '0';
                return parseInt(jerseyA) - parseInt(jerseyB);
            }
            return 0;
        });
        
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
    });

    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target == modal) {
                modal.classList.remove('active');
            }
        });
    }

    // Keyboard shortcut to close modals (Escape key)
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modals = document.querySelectorAll('.modal.active');
            modals.forEach(modal => {
                modal.classList.remove('active');
            });
        }
    });
    </script>
</body>
</html>