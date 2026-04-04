<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth($pdo);
$auth->requireAuth();
$auth->requireRole('student');

$studentId = $_SESSION['student_id'];

// Get student profile
$stmt = $pdo->prepare("
    SELECT s.*, u.email 
    FROM Student s
    JOIN \"User\" u ON s.user_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

// Get student achievements
$stmt = $pdo->prepare("
    SELECT * FROM get_student_performance(?)
");
$stmt->execute([$studentId]);
$achievements = $stmt->fetchAll();

// Get all achievements with full details
$stmt = $pdo->prepare("
    SELECT p.*, c.name as contest_name, a.activity_name, a.activity_type, a.year,
           co.first_name as coach_first, co.last_name as coach_last
    FROM Participation p
    JOIN Contest c ON p.contest_id = c.id
    JOIN Activity a ON c.activity_id = a.id
    LEFT JOIN Coach co ON p.coach_id = co.id
    WHERE p.student_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$studentId]);
$allAchievements = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_participations,
        COUNT(CASE WHEN ranking IN ('champion', '1st_place', '2nd_place', '3rd_place') THEN 1 END) as total_awards,
        COUNT(CASE WHEN ranking = 'champion' THEN 1 END) as championships,
        COUNT(CASE WHEN ranking = '1st_place' THEN 1 END) as gold_medals,
        COUNT(CASE WHEN ranking = '2nd_place' THEN 1 END) as silver_medals,
        COUNT(CASE WHEN ranking = '3rd_place' THEN 1 END) as bronze_medals
    FROM Participation 
    WHERE student_id = ?
");
$stmt->execute([$studentId]);
$stats = $stmt->fetch();

// Get all available activities (for My Teams and Available Activities)
$stmt = $pdo->query("
    SELECT a.*, COUNT(c.id) as contest_count
    FROM Activity a
    LEFT JOIN Contest c ON a.id = c.activity_id
    GROUP BY a.id
    ORDER BY a.year DESC, a.id DESC
");
$activities = $stmt->fetchAll();

// Get all contests for available activities
$stmt = $pdo->query("
    SELECT c.*, a.activity_name, a.activity_type, a.year as activity_year,
           cat.category_name
    FROM Contest c
    JOIN Activity a ON c.activity_id = a.id
    LEFT JOIN Category cat ON c.category_id = cat.id
    ORDER BY a.year DESC, c.name
");
$allContests = $stmt->fetchAll();

// Get other students in same course (for teams/group)
$stmt = $pdo->prepare("
    SELECT s.*, 
           COUNT(p.id) as total_participations,
           COUNT(CASE WHEN p.ranking IN ('champion', '1st_place', '2nd_place', '3rd_place') THEN 1 END) as awards
    FROM Student s
    LEFT JOIN Participation p ON s.id = p.student_id
    WHERE s.course = ? AND s.id != ?
    GROUP BY s.id
    ORDER BY s.last_name
");
$stmt->execute([$student['course'], $studentId]);
$courseMates = $stmt->fetchAll();

// Get top performers in same course
$stmt = $pdo->prepare("
    SELECT s.first_name, s.last_name, s.course,
           COUNT(p.id) as total_participations,
           COUNT(CASE WHEN p.ranking IN ('champion', '1st_place', '2nd_place', '3rd_place') THEN 1 END) as awards
    FROM Student s
    LEFT JOIN Participation p ON s.id = p.student_id
    WHERE s.course = ?
    GROUP BY s.id, s.first_name, s.last_name, s.course
    ORDER BY awards DESC, total_participations DESC
    LIMIT 5
");
$stmt->execute([$student['course']]);
$topPerformers = $stmt->fetchAll();

// Get all available teams created by coaches
$stmt = $pdo->query("
    SELECT t.*, 
           c.first_name as coach_first, 
           c.last_name as coach_last,
           COUNT(s.id) as member_count
    FROM Team t
    LEFT JOIN Coach c ON t.coach_id = c.id
    LEFT JOIN Student s ON t.id = s.team_id
    GROUP BY t.id, c.id, c.first_name, c.last_name
    ORDER BY t.team_name
");
$availableTeams = $stmt->fetchAll();

// Get current student's team (if any)
$stmt = $pdo->prepare("
    SELECT t.*, c.first_name as coach_first, c.last_name as coach_last
    FROM Team t
    LEFT JOIN Coach c ON t.coach_id = c.id
    WHERE t.id = ?
");
$stmt->execute([$student['team_id']]);
$currentTeam = $stmt->fetch();

// Get team members if student is in a team
$currentTeamMembers = [];
if ($student['team_id']) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COUNT(p.id) as total_participations,
               COUNT(CASE WHEN p.ranking IN ('champion', '1st_place', '2nd_place', '3rd_place') THEN 1 END) as awards
        FROM Student s
        LEFT JOIN Participation p ON s.id = p.student_id
        WHERE s.team_id = ?
        GROUP BY s.id
        ORDER BY s.last_name
    ");
    $stmt->execute([$student['team_id']]);
    $currentTeamMembers = $stmt->fetchAll();
}

// Get team achievements
$teamAchievements = [];
if ($student['team_id']) {
    $stmt = $pdo->prepare("
        SELECT p.*, s.first_name, s.last_name, c.name as contest_name, a.activity_name,
               a.activity_type, p.ranking, p.role
        FROM Participation p
        JOIN Student s ON p.student_id = s.id
        JOIN Contest c ON p.contest_id = c.id
        JOIN Activity a ON c.activity_id = a.id
        WHERE p.team_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$student['team_id']]);
    $teamAchievements = $stmt->fetchAll();
}

// Get team specialization for filtering activities
$teamSpecialization = null;
if ($currentTeam) {
    $teamSpecialization = $currentTeam['specialization'];
}

// Get activities filtered by team specialization (if student has a team)
$filteredActivities = [];
if ($teamSpecialization) {
    // Get activities that have contests matching the team specialization
    $stmt = $pdo->prepare("
        SELECT DISTINCT a.*, COUNT(DISTINCT c.id) as contest_count
        FROM Activity a
        JOIN Contest c ON a.id = c.activity_id
        WHERE c.name ILIKE ? OR a.activity_name ILIKE ? OR a.activity_type ILIKE ?
        GROUP BY a.id
        ORDER BY a.year DESC, a.id DESC
    ");
    $searchTerm = '%' . $teamSpecialization . '%';
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $filteredActivities = $stmt->fetchAll();
} else {
    // If no team, show general activities or none
    $stmt = $pdo->prepare("
        SELECT a.*, COUNT(c.id) as contest_count
        FROM Activity a
        LEFT JOIN Contest c ON a.id = c.activity_id
        WHERE a.activity_type_filter = 'general'
        GROUP BY a.id
        ORDER BY a.year DESC, a.id DESC
        LIMIT 5
    ");
    $stmt->execute();
    $filteredActivities = $stmt->fetchAll();
}

// Get contests filtered by team specialization
$filteredContests = [];
if ($teamSpecialization) {
    $stmt = $pdo->prepare("
        SELECT c.*, a.activity_name, a.activity_type, a.year as activity_year,
               cat.category_name
        FROM Contest c
        JOIN Activity a ON c.activity_id = a.id
        LEFT JOIN Category cat ON c.category_id = cat.id
        WHERE c.name ILIKE ? OR a.activity_name ILIKE ? OR a.activity_type ILIKE ?
        ORDER BY a.year DESC, c.name
    ");
    $searchTerm = '%' . $teamSpecialization . '%';
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $filteredContests = $stmt->fetchAll();
}

// Group achievements by year for chart
$yearlyData = [];
foreach ($achievements as $ach) {
    $year = $ach['year'];
    if (!isset($yearlyData[$year])) {
        $yearlyData[$year] = 0;
    }
    $yearlyData[$year]++;
}

// Handle profile update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_profile':
                    $newEmail = trim($_POST['email']);
                    if (!empty($newEmail) && filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                        // Check if email exists for other users
                        $stmt = $pdo->prepare("SELECT id FROM \"User\" WHERE email = ? AND id != (SELECT user_id FROM Student WHERE id = ?)");
                        $stmt->execute([$newEmail, $studentId]);
                        if (!$stmt->fetch()) {
                            $stmt = $pdo->prepare("UPDATE \"User\" SET email = ? WHERE id = (SELECT user_id FROM Student WHERE id = ?)");
                            $stmt->execute([$newEmail, $studentId]);
                            $_SESSION['email'] = $newEmail;
                            $message = "Profile updated successfully!";
                            $messageType = "success";
                            
                            header("Location: student.php?updated=1");
                            exit();
                        } else {
                            $message = "Email already exists!";
                            $messageType = "error";
                        }
                    } else {
                        $message = "Invalid email address!";
                        $messageType = "error";
                    }
                    break;
                    
                case 'update_password':
                    $currentPassword = $_POST['current_password'];
                    $newPassword = $_POST['new_password'];
                    $confirmPassword = $_POST['confirm_password'];
                    
                    // Get current user
                    $stmt = $pdo->prepare("SELECT password FROM \"User\" WHERE id = (SELECT user_id FROM Student WHERE id = ?)");
                    $stmt->execute([$studentId]);
                    $user = $stmt->fetch();
                    
                    if (password_verify($currentPassword, $user['password'])) {
                        if ($newPassword === $confirmPassword && strlen($newPassword) >= 6) {
                            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE \"User\" SET password = ? WHERE id = (SELECT user_id FROM Student WHERE id = ?)");
                            $stmt->execute([$hashedPassword, $studentId]);
                            $message = "Password updated successfully!";
                            $messageType = "success";
                        } else {
                            $message = "New password doesn't match or is too short (min 6 characters)!";
                            $messageType = "error";
                        }
                    } else {
                        $message = "Current password is incorrect!";
                        $messageType = "error";
                    }
                    break;
                    // In student.php, find the join_team case and update it:

case 'join_team':
    $teamId = $_POST['team_id'];
    
    // Check if student is already in a team
    if ($student['team_id']) {
        // Leave current team first
        $oldTeamId = $student['team_id'];
        
        // Update participations from old team
        $stmt = $pdo->prepare("UPDATE Participation SET team_id = NULL WHERE student_id = ? AND team_id = ?");
        $stmt->execute([$studentId, $oldTeamId]);
        
        $message = "You have left your previous team and ";
    }
    
    // Check if team exists
    $stmt = $pdo->prepare("SELECT id FROM Team WHERE id = ?");
    $stmt->execute([$teamId]);
    if ($stmt->fetch()) {
        // Update student's team
        $stmt = $pdo->prepare("UPDATE Student SET team_id = ? WHERE id = ?");
        $stmt->execute([$teamId, $studentId]);
        
        if (isset($message)) {
            $message .= "joined the new team successfully!";
        } else {
            $message = "You have successfully joined the team!";
        }
        $messageType = "success";
        
        header("Location: student.php?joined=1");
        exit();
    } else {
        $message = "Team not found!";
        $messageType = "error";
    }
    break;                    


case 'leave_team':
    $currentTeamId = $student['team_id'];
    
    if ($currentTeamId) {
        // Update all participations that were associated with this team for this student
        $stmt = $pdo->prepare("UPDATE Participation SET team_id = NULL WHERE student_id = ? AND team_id = ?");
        $stmt->execute([$studentId, $currentTeamId]);
        
        // Remove student from team
        $stmt = $pdo->prepare("UPDATE Student SET team_id = NULL WHERE id = ?");
        $stmt->execute([$studentId]);
        
        $message = "You have left the team successfully!";
        $messageType = "success";
        
        header("Location: student.php?left=1");
        exit();
    } else {
        $message = "You are not part of any team!";
        $messageType = "error";
    }
    break;
            }
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

if (isset($_GET['updated'])) {
    $message = "Profile updated successfully!";
    $messageType = "success";
}

if (isset($_GET['joined'])) {
    $message = "You have successfully joined the team!";
    $messageType = "success";
}

if (isset($_GET['left'])) {
    $message = "You have left the team successfully!";
    $messageType = "success";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - BISU Candijay Achievement Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #eef2f7 100%);
            min-height: 100vh;
        }

        /* Dashboard Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0a2b3e 0%, #1e6f5c 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 35px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.1);
        }

        .avatar {
            width: 85px;
            height: 85px;
            background: linear-gradient(135deg, #ffd89b, #c7e9fb);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2.5rem;
            color: #0a2b3e;
            font-weight: bold;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .sidebar-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .sidebar-header p {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 5px;
        }

        .sidebar-nav {
            padding: 25px 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            margin: 5px 15px;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: all 0.3s ease;
            gap: 12px;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
        }

        .sidebar-nav a i {
            width: 24px;
            font-size: 1.2rem;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 35px;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-title h2 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #0a2b3e, #1e6f5c);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .page-title p {
            color: #6c757d;
            margin-top: 5px;
            font-size: 0.9rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info span {
            background: #f8f9fa;
            padding: 8px 18px;
            border-radius: 40px;
            font-size: 0.85rem;
            color: #495057;
        }

        .logout-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 8px 22px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.85rem;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.03);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-info h3 {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: #1e6f5c;
        }

        .stat-icon i {
            font-size: 3rem;
            color: #0a2b3e;
            opacity: 0.2;
        }

        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 35px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .chart-container h3 {
            margin-bottom: 20px;
            color: #0a2b3e;
            font-weight: 700;
        }

        canvas {
            max-height: 300px;
        }

        /* Data Table */
        .data-table {
            background: white;
            border-radius: 24px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
            margin-bottom: 25px;
        }

        .data-table h3 {
            margin-bottom: 20px;
            color: #0a2b3e;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.85rem;
        }

        tr:hover {
            background: #f8f9fa;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-champion {
            background: #ffd700;
            color: #7d5d1a;
        }

        .badge-1st_place {
            background: #c0c0c0;
            color: #4a4a4a;
        }

        .badge-2nd_place {
            background: #cd7f32;
            color: white;
        }

        .badge-3rd_place {
            background: #b87333;
            color: white;
        }

        .badge-finalist {
            background: #28a745;
            color: white;
        }

        .badge-participant {
            background: #6c757d;
            color: white;
        }

        /* Form Cards */
        .form-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            margin-bottom: 35px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .form-card h3 {
            margin-bottom: 20px;
            color: #0a2b3e;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            transition: 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #1e6f5c;
            outline: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0a2b3e, #1e6f5c);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 111, 92, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 5px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message.info {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        /* Team Cards */
        .team-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #1e6f5c;
        }

        .team-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .team-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0a2b3e;
        }

        .team-spec {
            font-size: 0.8rem;
            color: #1e6f5c;
        }

        .team-members {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .member {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .member-name {
            font-weight: 500;
        }

        .member-stats {
            font-size: 0.75rem;
            color: #1e6f5c;
        }

        /* Activity Cards */
        .activity-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: 0.3s;
            border: 1px solid #e9ecef;
        }

        .activity-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .activity-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0a2b3e;
            margin-bottom: 10px;
        }

        .activity-details {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .contest-list {
            margin-top: 15px;
            padding-left: 20px;
        }

        .contest-item {
            padding: 8px;
            border-left: 3px solid #1e6f5c;
            margin-bottom: 8px;
            background: #f8f9fa;
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            max-width: 300px;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }

        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1002;
            background: #1e6f5c;
            color: white;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1001;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .menu-toggle {
                display: block;
            }
        }

        .section {
            animation: fadeInUp 0.4s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 3000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            justify-content: center;
            align-items: center;
        }
        
        .image-modal-content {
            position: relative;
            max-width: 90%;
            max-height: 90%;
        }
        
        .image-modal-content img {
            width: 100%;
            height: auto;
            max-height: 80vh;
            object-fit: contain;
        }
        
        .image-modal-close {
            position: absolute;
            top: 20px;
            right: 40px;
            color: white;
            font-size: 40px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</div>
<div class="dashboard-container">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="avatar">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h3><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
            <p><?php echo htmlspecialchars($student['course']); ?> • Year <?php echo $student['yr_level']; ?></p>
        </div>
        <div class="sidebar-nav">
            <a onclick="showSection('overview')" class="active"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a onclick="showSection('teams')"><i class="fas fa-users"></i> My Teams</a>
            <a onclick="showSection('jointeams')"><i class="fas fa-user-plus"></i> Join Teams/Group</a>
            <a onclick="showSection('achievements')"><i class="fas fa-trophy"></i> My Achievements</a>
            <a onclick="showSection('activities')"><i class="fas fa-calendar-alt"></i> Available Activities</a>
            <a onclick="showSection('profile')"><i class="fas fa-user-circle"></i> Profile</a>
            <a onclick="showSection('settings')"><i class="fas fa-cog"></i> Settings</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Log Out</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h2>Student Dashboard</h2>
                <p>Welcome back, <?php echo htmlspecialchars($student['first_name']); ?>! ✨</p>
            </div>
            <div class="user-info">
                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard Overview Section -->
        <div id="overview-section" class="section">
            <div class="stats-grid">
                <div class="stat-card" onclick="showSection('achievements')">
                    <div class="stat-info">
                        <h3>Total Participations</h3>
                        <div class="stat-number"><?php echo $stats['total_participations']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
                <div class="stat-card" onclick="showSection('achievements')">
                    <div class="stat-info">
                        <h3>🏆 Championships</h3>
                        <div class="stat-number"><?php echo $stats['championships'] ?? 0; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                </div>
                <div class="stat-card" onclick="showSection('achievements')">
                    <div class="stat-info">
                        <h3>🥇 Gold Medals</h3>
                        <div class="stat-number"><?php echo $stats['gold_medals'] ?? 0; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-medal"></i>
                    </div>
                </div>
                <div class="stat-card" onclick="showSection('achievements')">
                    <div class="stat-info">
                        <h3>Total Awards</h3>
                        <div class="stat-number"><?php echo $stats['total_awards']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>

            <?php if (!empty($yearlyData)): ?>
            <div class="chart-container">
                <h3><i class="fas fa-chart-bar"></i> Performance Trend</h3>
                <canvas id="achievementChart"></canvas>
            </div>
            <?php endif; ?>

            <div class="data-table">
                <h3><i class="fas fa-clock"></i> Recent Achievements</h3>
                <table>
                    <thead>
                        <tr><th>Year</th><th>Contest</th><th>Activity</th><th>Ranking</th><th>Role</th><th>Coach</th></tr>
                    </thead>
                    <tbody>
                        <?php $recent = array_slice($allAchievements, 0, 5); ?>
                        <?php foreach ($recent as $achievement): ?>
                        <tr>
                            <td><?php echo $achievement['year']; ?></td>
                            <td><?php echo htmlspecialchars($achievement['contest_name']); ?></td>
                            <td><?php echo htmlspecialchars($achievement['activity_name']); ?></td>
                            <td><span class="badge badge-<?php echo str_replace('_', '-', $achievement['ranking']); ?>"><?php echo str_replace('_', ' ', $achievement['ranking']); ?></span></td>
                            <td><?php echo htmlspecialchars($achievement['role']); ?></td>
                            <td><?php echo htmlspecialchars($achievement['coach_first'] . ' ' . $achievement['coach_last']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent)): ?>
                        <tr><td colspan="6" style="text-align:center;">✨ No achievements recorded yet. Start competing!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-chart-line"></i> Top Performers in <?php echo htmlspecialchars($student['course']); ?></h3>
                <table>
                    <thead>
                        <tr><th>Name</th><th>Participations</th><th>Awards</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topPerformers as $performer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($performer['first_name'] . ' ' . $performer['last_name']); ?></td>
                            <td><?php echo $performer['total_participations']; ?></td>
                            <td><i class="fas fa-trophy"></i> <?php echo $performer['awards']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- My Teams Section -->
        <div id="teams-section" class="section" style="display: none;">
            <div class="data-table">
                <h3><i class="fas fa-users"></i> My Team</h3>
                <?php if ($currentTeam): ?>
                <div class="team-card">
                    <div class="team-header">
                        <div>
                            <span class="team-name"><i class="fas fa-users"></i> <?php echo htmlspecialchars($currentTeam['team_name']); ?></span>
                            <div class="team-spec">Specialization: <?php echo htmlspecialchars($currentTeam['specialization']); ?></div>
                            <?php if ($currentTeam['coach_first']): ?>
                            <div class="team-spec">Coach: <?php echo htmlspecialchars($currentTeam['coach_first'] . ' ' . $currentTeam['coach_last']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="badge badge-participant"><?php echo count($currentTeamMembers); ?> members</span>
                            <form method="POST" style="display: inline-block; margin-left: 10px;" onsubmit="return confirm('Are you sure you want to leave this team?')">
                                <input type="hidden" name="action" value="leave_team">
                                <button type="submit" class="btn-danger" style="padding: 5px 15px;">Leave Team</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="team-members">
                        <?php if (!empty($currentTeamMembers)): ?>
                            <?php foreach ($currentTeamMembers as $member): ?>
                            <div class="member">
                                <div class="member-name">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                    <?php if ($member['id'] == $studentId): ?>
                                        <span class="badge badge-champion" style="font-size: 0.7rem; background: #1e6f5c;">You</span>
                                    <?php endif; ?>
                                    <br><small><?php echo htmlspecialchars($member['course']); ?> - Year <?php echo $member['yr_level']; ?></small>
                                </div>
                                <div class="member-stats">
                                    <?php echo $member['total_participations']; ?> participations<br>
                                    <?php echo $member['awards']; ?> awards
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #999; text-align: center; padding: 20px;">No team members found.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <h3 style="margin-top: 30px;">Team Achievements</h3>
                <table>
                    <thead>
                        <tr><th>Member</th><th>Contest</th><th>Activity</th><th>Ranking</th><th>Role</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teamAchievements as $ta): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ta['first_name'] . ' ' . $ta['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($ta['contest_name']); ?></td>
                            <td><?php echo htmlspecialchars($ta['activity_name']); ?></td>
                            <td><span class="badge badge-<?php echo str_replace('_', '-', $ta['ranking']); ?>"><?php echo str_replace('_', ' ', $ta['ranking']); ?></span></td>
                            <td><?php echo htmlspecialchars($ta['role']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($teamAchievements)): ?>
                        <tr><td colspan="5" style="text-align:center;">No team achievements recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="text-align: center; padding: 40px;">
                    <i class="fas fa-users-slash" style="font-size: 3rem; color: #ccc;"></i><br>
                    You are not part of any team yet.<br>
                    Visit "Join Teams/Group" to join a team!
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Join Teams/Group Section -->
        <div id="jointeams-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-users"></i> Available Teams to Join</h3>
                <p style="margin-bottom: 20px; color: #6c757d;">Browse teams created by coaches and join one that matches your interest!</p>
                
                <?php if ($student['team_id']): ?>
                <div class="message info" style="margin-bottom: 20px;">
                    <i class="fas fa-info-circle"></i> You are already a member of <strong><?php echo htmlspecialchars($currentTeam['team_name']); ?></strong>. 
                    Leave your current team first to join another team.
                </div>
                <?php endif; ?>
                
                <?php if (empty($availableTeams)): ?>
                    <p style="text-align: center; padding: 40px;">
                        <i class="fas fa-folder-open" style="font-size: 3rem; color: #ccc;"></i><br>
                        No teams available at the moment.<br>
                        Check back later or contact your coach to create a team!
                    </p>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                        <?php foreach ($availableTeams as $team): ?>
                        <div class="team-card" style="border-left: 4px solid #1e6f5c;">
                            <div class="team-header">
                                <div>
                                    <span class="team-name"><i class="fas fa-users"></i> <?php echo htmlspecialchars($team['team_name']); ?></span>
                                    <div class="team-spec">Specialization: <?php echo htmlspecialchars($team['specialization']); ?></div>
                                    <?php if ($team['coach_first']): ?>
                                    <div class="team-spec">Coach: <?php echo htmlspecialchars($team['coach_first'] . ' ' . $team['coach_last']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="badge badge-participant"><?php echo $team['member_count']; ?> members</span>
                            </div>
                            
                            <?php if (!$student['team_id']): ?>
                            <form method="POST" style="margin-top: 15px;" onsubmit="return confirm('Join team: <?php echo htmlspecialchars($team['team_name']); ?>?')">
                                <input type="hidden" name="action" value="join_team">
                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                <button type="submit" class="btn-primary" style="width: 100%;">
                                    <i class="fas fa-sign-in-alt"></i> Join This Team
                                </button>
                            </form>
                            <?php elseif ($student['team_id'] == $team['id']): ?>
                            <div style="margin-top: 15px; padding: 10px; background: #d4edda; border-radius: 8px; text-align: center; color: #155724;">
                                <i class="fas fa-check-circle"></i> This is your current team
                            </div>
                            <?php else: ?>
                            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px; text-align: center; color: #6c757d;">
                                <i class="fas fa-lock"></i> Leave your current team first to join
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-card">
                <h3><i class="fas fa-info-circle"></i> How Teams Work</h3>
                <ul style="margin-left: 20px; color: #666; line-height: 1.8;">
                    <li><i class="fas fa-check-circle" style="color: #1e6f5c;"></i> Teams are created by coaches for specific sports or activities</li>
                    <li><i class="fas fa-check-circle" style="color: #1e6f5c;"></i> Join a team that matches your interest and specialization</li>
                    <li><i class="fas fa-check-circle" style="color: #1e6f5c;"></i> Being in a team allows you to participate in team-based competitions</li>
                    <li><i class="fas fa-check-circle" style="color: #1e6f5c;"></i> You can only be in one team at a time</li>
                    <li><i class="fas fa-check-circle" style="color: #1e6f5c;"></i> Team achievements will be displayed in your profile</li>
                </ul>
                <p style="margin-top: 15px; padding: 10px; background: #e3f2fd; border-radius: 8px;">
                    <i class="fas fa-envelope"></i> <strong>Need help?</strong> Contact your coach if you don't see the team you're looking for.
                </p>
            </div>
        </div>

        <!-- My Achievements Section -->
        <div id="achievements-section" class="section" style="display: none;">
            <div class="data-table">
                <h3><i class="fas fa-trophy"></i> All Your Achievements & Medals</h3>
                <div class="search-box">
                    <input type="text" id="achievementSearch" placeholder="Search achievements..." onkeyup="filterAchievements()">
                </div>
                <table id="achievementsTable">
                    <thead>
                        <tr><th>Year</th><th>Contest</th><th>Activity</th><th>Ranking</th><th>Role</th><th>Coach</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allAchievements as $achievement): ?>
                        <tr class="achievement-row">
                            <td><?php echo $achievement['year']; ?></td>
                            <td><?php echo htmlspecialchars($achievement['contest_name']); ?></td>
                            <td><?php echo htmlspecialchars($achievement['activity_name']); ?></td>
                            <td><span class="badge badge-<?php echo str_replace('_', '-', $achievement['ranking']); ?>"><?php echo str_replace('_', ' ', $achievement['ranking']); ?></span></td>
                            <td><?php echo htmlspecialchars($achievement['role']); ?></td>
                            <td><?php echo htmlspecialchars($achievement['coach_first'] . ' ' . $achievement['coach_last']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($achievement['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($allAchievements)): ?>
                        <tr><td colspan="7" style="text-align:center;">No achievements recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="stats-grid" style="margin-top: 20px;">
                <div class="stat-card">
                    <div class="stat-info"><h3>🏆 Championships</h3><div class="stat-number"><?php echo $stats['championships'] ?? 0; ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>🥇 Gold Medals</h3><div class="stat-number"><?php echo $stats['gold_medals'] ?? 0; ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>🥈 Silver Medals</h3><div class="stat-number"><?php echo $stats['silver_medals'] ?? 0; ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><h3>🥉 Bronze Medals</h3><div class="stat-number"><?php echo $stats['bronze_medals'] ?? 0; ?></div></div>
                </div>
            </div>
        </div>

        <!-- Available Activities Section -->
        <div id="activities-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-calendar-alt"></i> Available Activities</h3>
                
                <?php if (!$currentTeam): ?>
                    <div class="message info" style="margin-bottom: 20px;">
                        <i class="fas fa-info-circle"></i> You are not part of any team yet. 
                        <a href="#" onclick="showSection('jointeams'); return false;" style="color: #1e6f5c; text-decoration: underline;">Join a team</a> to see activities related to your specialization.
                    </div>
                <?php elseif (empty($filteredActivities)): ?>
                    <div class="message info" style="margin-bottom: 20px;">
                        <i class="fas fa-info-circle"></i> No activities available for your team specialization: <strong><?php echo htmlspecialchars($teamSpecialization); ?></strong>.<br>
                        Check back later for new activities or contact your coach for more opportunities.
                    </div>
                <?php else: ?>
                    <div class="message success" style="margin-bottom: 20px; background: #e8f5e9;">
                        <i class="fas fa-check-circle"></i> Showing activities related to your team: <strong><?php echo htmlspecialchars($currentTeam['team_name']); ?></strong> (Specialization: <?php echo htmlspecialchars($teamSpecialization); ?>)
                    </div>
                <?php endif; ?>
                
                <div class="search-box">
                    <input type="text" id="activitySearch" placeholder="Search activities..." onkeyup="filterActivities()">
                </div>
                
                <?php if (!empty($filteredActivities)): ?>
                    <?php foreach ($filteredActivities as $activity): ?>
                    <div class="activity-card activity-item">
                        <div class="activity-title">
                            <i class="fas fa-<?php echo $activity['activity_type'] == 'sports' ? 'futbol' : ($activity['activity_type'] == 'arts' ? 'palette' : 'music'); ?>"></i>
                            <?php echo htmlspecialchars($activity['activity_name']); ?>
                        </div>
                        <div class="activity-details">
                            <span><i class="fas fa-tag"></i> Type: <?php echo ucfirst($activity['activity_type']); ?></span>
                            <span><i class="fas fa-calendar"></i> Year: <?php echo $activity['year']; ?></span>
                            <span><i class="fas fa-trophy"></i> Contests: <?php echo $activity['contest_count']; ?></span>
                        </div>
                        <?php if ($activity['event_name']): ?>
                        <p style="margin-top: 10px; color: #6c757d;"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($activity['event_name']); ?></p>
                        <?php endif; ?>
                        
                        <div class="contest-list">
                            <strong>Available Contests:</strong>
                            <?php 
                            $activityContests = array_filter($filteredContests, function($c) use ($activity) {
                                return $c['activity_id'] == $activity['id'];
                            });
                            ?>
                            <?php if (!empty($activityContests)): ?>
                                <?php foreach ($activityContests as $contest): ?>
                                <div class="contest-item">
                                    <i class="fas fa-chevron-right"></i> <?php echo htmlspecialchars($contest['name']); ?>
                                    <?php if ($contest['category_name']): ?>
                                        <span class="badge badge-participant"><?php echo htmlspecialchars($contest['category_name']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($contest['tournament_manager']): ?>
                                        <br><small>Manager: <?php echo htmlspecialchars($contest['tournament_manager']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #6c757d; margin-top: 10px;">No contests available for this activity.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php if ($currentTeam): ?>
                        <p style="text-align: center; padding: 40px;">
                            <i class="fas fa-folder-open" style="font-size: 3rem; color: #ccc;"></i><br>
                            No activities found for your team specialization: <strong><?php echo htmlspecialchars($teamSpecialization); ?></strong><br>
                            Check back later or contact your coach for updates.
                        </p>
                    <?php else: ?>
                        <p style="text-align: center; padding: 40px;">
                            <i class="fas fa-users-slash" style="font-size: 3rem; color: #ccc;"></i><br>
                            You need to join a team first to see available activities.<br>
                            <a href="#" onclick="showSection('jointeams'); return false;" style="color: #1e6f5c; text-decoration: underline;">Click here to join a team</a>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($currentTeam): ?>
            <div class="form-card">
                <h3><i class="fas fa-info-circle"></i> About Your Team</h3>
                <div style="padding: 15px; background: #f8f9fa; border-radius: 12px;">
                    <p><strong>Team Name:</strong> <?php echo htmlspecialchars($currentTeam['team_name']); ?></p>
                    <p><strong>Specialization:</strong> <?php echo htmlspecialchars($currentTeam['specialization']); ?></p>
                    <?php if ($currentTeam['coach_first']): ?>
                    <p><strong>Coach:</strong> <?php echo htmlspecialchars($currentTeam['coach_first'] . ' ' . $currentTeam['coach_last']); ?></p>
                    <?php endif; ?>
                    <p><strong>Members:</strong> <?php echo count($currentTeamMembers); ?> members</p>
                </div>
                <p style="margin-top: 15px; color: #6c757d;">
                    <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> The activities shown above are filtered based on your team's specialization. 
                    Participating in these activities can help your team earn achievements and awards!
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Profile Section -->
        <div id="profile-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-user-circle"></i> My Profile</h3>
                <div class="form-row">
                    <div class="form-group"><label>First Name</label><input type="text" value="<?php echo htmlspecialchars($student['first_name']); ?>" readonly></div>
                    <div class="form-group"><label>Last Name</label><input type="text" value="<?php echo htmlspecialchars($student['last_name']); ?>" readonly></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Course</label><input type="text" value="<?php echo htmlspecialchars($student['course']); ?>" readonly></div>
                    <div class="form-group"><label>Year Level</label><input type="text" value="<?php echo $student['yr_level']; ?> Year" readonly></div>
                </div>
                <div class="form-group"><label>College</label><input type="text" value="<?php echo htmlspecialchars($student['college']); ?>" readonly></div>
                <div class="form-group"><label>Email</label><input type="email" value="<?php echo htmlspecialchars($student['email']); ?>" readonly></div>
                <div class="form-group"><label>Student ID</label><input type="text" value="<?php echo $student['id']; ?>" readonly></div>
                <div class="form-group"><label>Team</label><input type="text" value="<?php echo $currentTeam ? htmlspecialchars($currentTeam['team_name']) : 'Not in a team'; ?>" readonly></div>
                <div class="form-group"><label>Total Participations</label><input type="text" value="<?php echo $stats['total_participations']; ?>" readonly></div>
                <div class="form-group"><label>Total Awards</label><input type="text" value="<?php echo $stats['total_awards']; ?>" readonly></div>
            </div>
        </div>

        <!-- Settings Section -->
        <div id="settings-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-envelope"></i> Update Email</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label>New Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                    </div>
                    <button type="submit" class="btn-primary">Update Email</button>
                </form>
            </div>
            
            <div class="form-card">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_password">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn-primary">Change Password</button>
                </form>
            </div>
            
            <div class="form-card">
                <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
                <div class="form-group">
                    <label>
                        <input type="checkbox" checked disabled> Email me when new achievements are recorded
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" checked disabled> Receive updates about new activities
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" disabled> Weekly performance summary
                    </label>
                </div>
                <p style="color: #6c757d; margin-top: 10px;"><i class="fas fa-info-circle"></i> Notification settings will be available soon.</p>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="image-modal">
    <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
    <div class="image-modal-content">
        <img id="modalImage" src="" alt="Full size image">
    </div>
</div>

<script>
// Section navigation
function showSection(section) {
    const sections = ['overview', 'teams', 'jointeams', 'achievements', 'activities', 'profile', 'settings'];
    sections.forEach(sec => {
        const el = document.getElementById(sec + '-section');
        if(el) el.style.display = 'none';
    });
    const activeSection = document.getElementById(section + '-section');
    if(activeSection) activeSection.style.display = 'block';
    
    const links = document.querySelectorAll('.sidebar-nav a');
    links.forEach(link => link.classList.remove('active'));
    if(event && event.target) {
        const clickedLink = event.target.closest('a');
        if(clickedLink) clickedLink.classList.add('active');
    }
    
    // Close sidebar on mobile after click
    if(window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('active');
    }
}

// Filter achievements
function filterAchievements() {
    const input = document.getElementById('achievementSearch');
    if (!input) return;
    const filter = input.value.toLowerCase();
    const table = document.getElementById('achievementsTable');
    if (!table) return;
    const rows = table.getElementsByClassName('achievement-row');
    
    for(let i = 0; i < rows.length; i++) {
        const text = rows[i].textContent.toLowerCase();
        if(text.indexOf(filter) > -1) {
            rows[i].style.display = '';
        } else {
            rows[i].style.display = 'none';
        }
    }
}

// Filter activities
function filterActivities() {
    const input = document.getElementById('activitySearch');
    if (!input) return;
    const filter = input.value.toLowerCase();
    const activities = document.getElementsByClassName('activity-item');
    
    for(let i = 0; i < activities.length; i++) {
        const text = activities[i].textContent.toLowerCase();
        if(text.indexOf(filter) > -1) {
            activities[i].style.display = 'block';
        } else {
            activities[i].style.display = 'none';
        }
    }
}

// View full image
function viewFullImage(imagePath) {
    document.getElementById('modalImage').src = '../' + imagePath;
    document.getElementById('imageModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Toggle sidebar for mobile
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
}

// Chart for achievements
<?php if (!empty($yearlyData)): ?>
const ctx = document.getElementById('achievementChart');
if(ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_keys($yearlyData)); ?>,
            datasets: [{
                label: 'Participations per Year',
                data: <?php echo json_encode(array_values($yearlyData)); ?>,
                borderColor: '#1e6f5c',
                backgroundColor: 'rgba(30, 111, 92, 0.1)',
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#0a2b3e',
                pointBorderColor: '#fff',
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top' },
                tooltip: { backgroundColor: '#0a2b3e' }
            }
        }
    });
}
<?php endif; ?>

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    if(window.innerWidth <= 768 && sidebar.classList.contains('active')) {
        if(!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    }
    
    // Close modal when clicking outside
    const modal = document.getElementById('imageModal');
    if(modal && modal.style.display === 'flex') {
        if(event.target === modal) {
            closeImageModal();
        }
    }
});

// Close modal on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeImageModal();
    }
});
</script>
</body>
</html>