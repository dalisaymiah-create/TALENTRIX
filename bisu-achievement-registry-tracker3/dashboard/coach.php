<?php
require_once '../includes/session.php';
redirectIfNotLoggedIn();

if ($_SESSION['usertype'] !== 'coach') {
    header("Location: " . getDashboardPath($_SESSION['usertype'], $_SESSION['admin_type'] ?? null));
    exit();
}

require_once '../config/database.php';

// Function to check if student can join a team based on category_type
function canStudentJoinTeam($pdo, $student_id, $contest_id) {
    // Get category_type of the contest to join
    $stmt = $pdo->prepare("
        SELECT cat.category_type, cat.category_name, c.name as contest_name
        FROM contest c
        JOIN category cat ON c.category_id = cat.id
        WHERE c.id = ?
    ");
    $stmt->execute([$contest_id]);
    $newContest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$newContest) {
        return ['can_join' => false, 'reason' => 'Contest not found'];
    }
    
    // Check student's existing active participations and their category_types
    $stmt = $pdo->prepare("
        SELECT DISTINCT cat.category_type, cat.category_name, c.name as contest_name, p.team_id
        FROM participation p
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN category cat ON c.category_id = cat.id
        WHERE p.student_id = ? AND p.membership_status = 'active'
    ");
    $stmt->execute([$student_id]);
    $existingParticipations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $existingTypes = array_column($existingParticipations, 'category_type');
    
    if (in_array($newContest['category_type'], $existingTypes)) {
        return [
            'can_join' => false, 
            'reason' => "Student is already in a {$newContest['category_type']} category. Cannot join another {$newContest['category_type']} contest.",
            'existing' => $existingParticipations
        ];
    }
    
    return ['can_join' => true, 'reason' => 'OK'];
}

// Get coach info - JOIN with User to get names
$coach = null;
try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.user_id, u.first_name, u.last_name, u.email
        FROM coach c
        JOIN \"User\" u ON c.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $coach = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coach) {
        die("Error: No coach profile found for this user. Please contact administrator.");
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get coach's teams with member counts from participation table
$teams = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, t.college_filter, c.name as contest_name, a.activity_name, a.activity_type, a.school_year, a.competition_level,
               cat.category_name, cat.category_type,
               COUNT(DISTINCT p.student_id) as member_count
        FROM team t
        LEFT JOIN contest c ON t.contest_id = c.id
        LEFT JOIN activity a ON c.activity_id = a.id
        LEFT JOIN category cat ON c.category_id = cat.id
        LEFT JOIN participation p ON t.id = p.team_id AND p.membership_status = 'active'
        WHERE t.coach_id = ?
        GROUP BY t.id, t.team_name, t.college_filter, c.name, a.activity_name, a.activity_type, a.school_year, a.competition_level, cat.category_name, cat.category_type, c.id
        ORDER BY t.id DESC
    ");
    $stmt->execute([$coach['id']]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $teams = [];
}

// Get team members for each team from participation table
$teamMembers = [];
foreach ($teams as $team) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.course, s.college, p.created_at as joined_at, p.ranking,
                   u.first_name, u.last_name
            FROM participation p
            JOIN student s ON p.student_id = s.id
            JOIN \"User\" u ON s.user_id = u.id
            WHERE p.team_id = ? AND p.membership_status = 'active'
            ORDER BY u.last_name, u.first_name
        ");
        $stmt->execute([$team['id']]);
        $teamMembers[$team['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $teamMembers[$team['id']] = [];
    }
}

// Get total students count across all teams (unique students)
$totalStudents = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.student_id) as total
        FROM participation p
        JOIN team t ON p.team_id = t.id
        WHERE t.coach_id = ? AND p.membership_status = 'active'
    ");
    $stmt->execute([$coach['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalStudents = $result ? $result['total'] : 0;
} catch(PDOException $e) {
    $totalStudents = 0;
}

// Get active contests count
$activeContests = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id) as total
        FROM contest c
        JOIN team t ON c.id = t.contest_id
        WHERE t.coach_id = ?
    ");
    $stmt->execute([$coach['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $activeContests = $result ? $result['total'] : 0;
} catch(PDOException $e) {
    $activeContests = 0;
}

// Get all students with their team memberships (from participation)
$students = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.course, s.yr_level, s.college,
               u.first_name, u.last_name,
               STRING_AGG(DISTINCT t.team_name, ', ') as teams,
               STRING_AGG(DISTINCT cat.category_type, ', ') as category_types
        FROM student s
        JOIN \"User\" u ON s.user_id = u.id
        JOIN participation p ON s.id = p.student_id AND p.membership_status = 'active'
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN category cat ON c.category_id = cat.id
        WHERE t.coach_id = ?
        GROUP BY s.id, s.course, s.yr_level, s.college, u.first_name, u.last_name
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$coach['id']]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $students = [];
}

// Get all students (not just those in coach's teams) for adding to teams
$allStudents = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.course, s.yr_level, s.college, u.first_name, u.last_name, u.email
        FROM student s
        JOIN \"User\" u ON s.user_id = u.id
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute();
    $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $allStudents = [];
}

// Get available contests for team creation
$availableContests = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.*, a.activity_name, a.activity_type, a.school_year, a.competition_level, cat.category_name, cat.category_type
        FROM contest c
        JOIN activity a ON c.activity_id = a.id
        JOIN category cat ON c.category_id = cat.id
        WHERE c.contest_status = 'active'
        ORDER BY a.school_year DESC, c.name
    ");
    $stmt->execute();
    $availableContests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $availableContests = [];
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update profile
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        
        try {
            $stmt = $pdo->prepare("UPDATE \"User\" SET first_name = ?, last_name = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $_SESSION['user_id']]);
            $message = "Profile updated successfully!";
            
            // Refresh coach data
            $stmt = $pdo->prepare("
                SELECT c.id, c.user_id, u.first_name, u.last_name, u.email
                FROM coach c
                JOIN \"User\" u ON c.user_id = u.id
                WHERE u.id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $coach = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
    
    // Change password
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $stmt = $pdo->prepare("SELECT password FROM \"User\" WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($current_password, $user['password'])) {
            $error = "Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters.";
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE \"User\" SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                $message = "Password changed successfully!";
            } catch(PDOException $e) {
                $error = "Error changing password: " . $e->getMessage();
            }
        }
    }
    
    // Create team (college_filter only for Intramurals)
    if (isset($_POST['create_team'])) {
        $team_name = trim($_POST['team_name']);
        $contest_id = $_POST['contest_id'];
        $team_college = isset($_POST['team_college']) ? $_POST['team_college'] : null;
        
        if (!empty($team_name) && !empty($contest_id)) {
            // Get contest competition level and school_year
            $stmt = $pdo->prepare("
                SELECT a.competition_level, a.school_year
                FROM contest c 
                JOIN activity a ON c.activity_id = a.id 
                WHERE c.id = ?
            ");
            $stmt->execute([$contest_id]);
            $contest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // For Intramurals, college selection is required
            if ($contest['competition_level'] === 'Intramurals' && empty($team_college)) {
                $error = "Please select a college for this Intramurals team.";
            } else {
                // For non-Intramurals, college_filter should be NULL
                if ($contest['competition_level'] !== 'Intramurals') {
                    $team_college = null;
                }
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO team (team_name, coach_id, contest_id, college_filter) VALUES (?, ?, ?, ?) RETURNING id");
                    $stmt->execute([$team_name, $coach['id'], $contest_id, $team_college]);
                    $new_team_id = $stmt->fetchColumn();
                    
                    $message = "Team created successfully!";
                    header("Location: coach.php?success=Team created successfully");
                    exit();
                } catch(PDOException $e) {
                    $error = "Error creating team: " . $e->getMessage();
                }
            }
        } else {
            $error = "Please fill in all fields.";
        }
    }
    
    // Add student to team
    if (isset($_POST['add_student_to_team'])) {
        $student_id = $_POST['student_id'];
        $team_id = $_POST['team_id'];
        
        // Get team info including college_filter and competition level
        $stmt = $pdo->prepare("
            SELECT t.id as team_id, t.college_filter, c.id as contest_id, a.competition_level
            FROM team t
            JOIN contest c ON t.contest_id = c.id
            JOIN activity a ON c.activity_id = a.id
            WHERE t.id = ?
        ");
        $stmt->execute([$team_id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($team) {
            // Get student's college
            $stmt = $pdo->prepare("
                SELECT s.id, s.college, s.course, u.first_name, u.last_name 
                FROM student s
                JOIN \"User\" u ON s.user_id = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $collegeError = false;
            
            // For Intramurals only - check using college_filter from database
            if ($team['competition_level'] === 'Intramurals') {
                if (!empty($team['college_filter']) && $student['college'] !== $team['college_filter']) {
                    $collegeError = true;
                    $error = "Cannot add student from {$student['college']} college. This team is filtered for {$team['college_filter']} college only.";
                }
            }
            
            if (!$collegeError) {
                $canJoin = canStudentJoinTeam($pdo, $student_id, $team['contest_id']);
                
                if ($canJoin['can_join']) {
                    try {
                        // Check if already active in this team
                        $stmt = $pdo->prepare("SELECT id FROM participation WHERE team_id = ? AND student_id = ? AND membership_status = 'active'");
                        $stmt->execute([$team_id, $student_id]);
                        
                        if ($stmt->fetch()) {
                            $error = "Student is already an active member of this team.";
                        } else {
                            // Check if there's an inactive record
                            $stmt = $pdo->prepare("SELECT id FROM participation WHERE team_id = ? AND student_id = ? AND membership_status = 'inactive'");
                            $stmt->execute([$team_id, $student_id]);
                            $existing = $stmt->fetch();
                            
                            if ($existing) {
                                $stmt = $pdo->prepare("UPDATE participation SET membership_status = 'active', created_at = NOW() WHERE id = ?");
                                $stmt->execute([$existing['id']]);
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO participation (student_id, team_id, membership_status, created_at) VALUES (?, ?, 'active', NOW())");
                                $stmt->execute([$student_id, $team_id]);
                            }
                            $message = "Student added to team successfully!";
                            header("Location: coach.php?success=Student added to team");
                            exit();
                        }
                    } catch(PDOException $e) {
                        $error = "Error adding student to team: " . $e->getMessage();
                    }
                } else {
                    $error = $canJoin['reason'];
                    if (isset($canJoin['existing'])) {
                        $error .= "<br><small>Student is currently in: ";
                        foreach ($canJoin['existing'] as $existing) {
                            $error .= "{$existing['category_name']} ({$existing['contest_name']}), ";
                        }
                        $error = rtrim($error, ', ') . "</small>";
                    }
                }
            }
        } else {
            $error = "Team not found.";
        }
    }
    
    // Remove student from team (set status to inactive)
    if (isset($_POST['remove_student_from_team'])) {
        $student_id = $_POST['student_id'];
        $team_id = $_POST['team_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE participation SET membership_status = 'inactive' WHERE team_id = ? AND student_id = ? AND membership_status = 'active'");
            $stmt->execute([$team_id, $student_id]);
            $message = "Student removed from team successfully!";
            header("Location: coach.php?success=Student removed from team");
            exit();
        } catch(PDOException $e) {
            $error = "Error removing student from team: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Dashboard - BISU Athletes & Arts Performers Registry</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        /* Coach Dashboard Enhanced Styles */
        .welcome-card {
            background: linear-gradient(135deg, #003366 0%, #1a4d8c 50%, #2a6eb0 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-card::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -10%;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(255,215,0,0.15), transparent);
            border-radius: 50%;
            animation: floatCoach 10s ease-in-out infinite;
        }
        
        .welcome-card::after {
            content: '';
            position: absolute;
            bottom: -20%;
            left: -5%;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(255,215,0,0.1), transparent);
            border-radius: 50%;
            animation: floatCoach2 12s ease-in-out infinite;
        }
        
        @keyframes floatCoach {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-15px, 10px) rotate(5deg); }
        }
        
        @keyframes floatCoach2 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(10px, -15px) rotate(-5deg); }
        }
        
        .welcome-card h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }
        
        .welcome-card p {
            opacity: 0.95;
            position: relative;
            z-index: 2;
        }
        
        .welcome-card .coach-stats {
            display: flex;
            gap: 1.5rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }
        
        .welcome-card .coach-stat {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .welcome-card .coach-stat i {
            color: #ffd700;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 20px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.3);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #003366, #ffd700);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card i {
            font-size: 2rem;
            color: #003366;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #003366;
        }
        
        .stat-card .stat-label {
            color: #666;
            font-size: 0.85rem;
        }
        
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
        }
        
        .team-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .team-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        
        .team-header {
            background: linear-gradient(135deg, #003366, #1a4d8c);
            color: white;
            padding: 1rem 1.2rem;
            position: relative;
            overflow: hidden;
        }
        
        .team-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(255,215,0,0.15), transparent);
            border-radius: 50%;
        }
        
        .category-tag {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .category-sport { background: #d4edda; color: #155724; }
        .category-athletic { background: #d1ecf1; color: #0c5460; }
        .category-cultural { background: #e8d5f0; color: #6c3483; }
        
        .member-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem;
            background: var(--gray-100);
            margin-bottom: 0.5rem;
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .member-item:hover {
            background: var(--gray-200);
            transform: translateX(5px);
        }
        
        .form-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 1.8rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .filter-bar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
        }
        
        .content-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .content-section.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .mobile-menu-btn {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #003366, #1a4d8c);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            z-index: 1002;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        @media (max-width: 768px) {
            .teams-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-card .coach-stats {
                flex-direction: column;
            }
            
            .mobile-menu-btn {
                display: flex;
            }
            
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1001;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon">
                    <img src="../includes/uploads/images/bisu_logo.png" alt="BISU Logo" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #ffd700;">
                </div>
                <h2>Athletes & Arts Registry</h2>
                <div class="user-role">Coach / Team Trainer</div>
            </div>
            <ul class="nav-menu">
                <li class="nav-item active" data-section="dashboard"><a href="#" onclick="showSection('dashboard')"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="nav-item" data-section="myteams"><a href="#" onclick="showSection('myteams')"><i class="fas fa-users"></i> My Teams</a></li>
                <li class="nav-item" data-section="createteam"><a href="#" onclick="showSection('createteam')"><i class="fas fa-plus-circle"></i> Create Team</a></li>
                <li class="nav-item" data-section="addstudents"><a href="#" onclick="showSection('addstudents')"><i class="fas fa-user-plus"></i> Add Students</a></li>
                <li class="nav-item" data-section="students"><a href="#" onclick="showSection('students')"><i class="fas fa-user-graduate"></i> My Students</a></li>
                <li class="nav-item" data-section="profile"><a href="#" onclick="showSection('profile')"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li class="nav-item"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1 id="pageTitle">Coach Dashboard</h1>
                </div>
                <div class="user-info">
                    <span class="user-name"><i class="fas fa-user-check"></i> Coach <?php echo htmlspecialchars($coach['first_name'] ?? 'User'); ?></span>
                    <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            
            <?php if(isset($_GET['success'])): ?>
                <div class="alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>
            <?php if($message): ?>
                <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Dashboard Section -->
            <div id="dashboard-section" class="content-section active">
                <div class="welcome-card">
                    <h2><i class="fas fa-chalkboard-user"></i> Welcome, Coach <?php echo htmlspecialchars($coach['first_name']); ?>!</h2>
                    <p>Manage your teams, track athlete progress, and celebrate achievements in sports and cultural activities.</p>
                    <div class="coach-stats">
                        <div class="coach-stat"><i class="fas fa-users"></i> <?php echo count($teams); ?> Teams</div>
                        <div class="coach-stat"><i class="fas fa-trophy"></i> <?php echo $activeContests; ?> Active Tournaments</div>
                        <div class="coach-stat"><i class="fas fa-user-graduate"></i> <?php echo $totalStudents; ?> Athletes/Artists</div>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-users"></i>
                        <div class="stat-number"><?php echo count($teams); ?></div>
                        <div class="stat-label">Total Teams</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-trophy"></i>
                        <div class="stat-number"><?php echo $activeContests; ?></div>
                        <div class="stat-label">Active Tournaments</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-user-graduate"></i>
                        <div class="stat-number"><?php echo $totalStudents; ?></div>
                        <div class="stat-label">Total Athletes/Artists</div>
                    </div>
                </div>
                
                <?php if(count($teams) > 0): ?>
                <div class="data-table">
                    <div class="table-header">
                        <h2><i class="fas fa-users"></i> My Teams Overview</h2>
                        <button class="btn-add" onclick="showSection('createteam')"><i class="fas fa-plus"></i> Create Team</button>
                    </div>
                    <div class="teams-grid" style="padding: 1rem;">
                        <?php foreach(array_slice($teams, 0, 3) as $team): ?>
                            <div class="team-card">
                                <div class="team-header">
                                    <h3><?php echo htmlspecialchars($team['team_name']); ?></h3>
                                    <span class="category-tag <?php 
                                        echo strtolower($team['category_type']) == 'sport' ? 'category-sport' : 
                                            (strtolower($team['category_type']) == 'athletic' ? 'category-athletic' : 'category-cultural'); 
                                    ?>">
                                        <?php echo htmlspecialchars($team['category_type']); ?>
                                    </span>
                                </div>
                                <div class="team-info" style="padding: 1rem;">
                                    <p><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($team['contest_name'] ?? 'No Contest'); ?></p>
                                    <p><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($team['activity_name'] ?? 'No Activity'); ?> (<?php echo htmlspecialchars($team['school_year'] ?? 'N/A'); ?>)</p>
                                    <p><i class="fas fa-tag"></i> Level: <strong><?php echo htmlspecialchars($team['competition_level'] ?? 'N/A'); ?></strong></p>
                                    <p><i class="fas fa-users"></i> Members: <?php echo $team['member_count'] ?? 0; ?></p>
                                </div>
                                <div class="team-actions" style="padding: 1rem; border-top: 1px solid #e5e7eb;">
                                    <button class="btn-primary btn-sm" onclick="showSection('myteams')">View Team Details</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if(count($teams) > 3): ?>
                        <div style="text-align: center; padding: 1rem;">
                            <button class="btn-add" onclick="showSection('myteams')">View All Teams (<?php echo count($teams); ?>)</button>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- My Teams Section -->
            <div id="myteams-section" class="content-section">
                <div class="data-table">
                    <div class="table-header">
                        <h2><i class="fas fa-users"></i> My Teams</h2>
                        <button class="btn-add" onclick="showSection('createteam')"><i class="fas fa-plus"></i> Create Team</button>
                    </div>
                    
                    <?php if(count($teams) > 0): ?>
                        <div class="teams-grid" style="padding: 1rem;">
                            <?php foreach($teams as $team): ?>
                                <div class="team-card">
                                    <div class="team-header">
                                        <h3><?php echo htmlspecialchars($team['team_name']); ?></h3>
                                        <span class="category-tag <?php 
                                            echo strtolower($team['category_type']) == 'sport' ? 'category-sport' : 
                                                (strtolower($team['category_type']) == 'athletic' ? 'category-athletic' : 'category-cultural'); 
                                        ?>">
                                            <?php echo htmlspecialchars($team['category_type']); ?>
                                        </span>
                                    </div>
                                    <div class="team-info" style="padding: 1rem;">
                                        <p><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($team['contest_name'] ?? 'No Contest'); ?></p>
                                        <p><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($team['activity_name'] ?? 'No Activity'); ?> (<?php echo htmlspecialchars($team['school_year'] ?? 'N/A'); ?>)</p>
                                        <p><i class="fas fa-tag"></i> Competition Level: <strong><?php echo htmlspecialchars($team['competition_level'] ?? 'N/A'); ?></strong></p>
                                        
                                        <?php if(($team['competition_level'] ?? '') === 'Intramurals' && !empty($team['college_filter'])): ?>
                                            <p><i class="fas fa-building"></i> College Filter: <strong><?php echo htmlspecialchars($team['college_filter']); ?></strong></p>
                                            <div class="info-box" style="margin-top: 0.5rem;">
                                                <i class="fas fa-info-circle"></i> Only students from <?php echo htmlspecialchars($team['college_filter']); ?> college can join this team.
                                            </div>
                                        <?php elseif(($team['competition_level'] ?? '') !== 'Intramurals'): ?>
                                            <p><i class="fas fa-globe"></i> Open to all colleges - No college restriction</p>
                                        <?php endif; ?>
                                        
                                        <p><i class="fas fa-users"></i> Members: <?php echo $team['member_count'] ?? 0; ?></p>
                                        
                                        <?php if(isset($teamMembers[$team['id']]) && count($teamMembers[$team['id']]) > 0): ?>
                                            <div class="member-list" style="margin-top: 1rem;">
                                                <strong><i class="fas fa-list"></i> Team Members:</strong>
                                                <?php foreach($teamMembers[$team['id']] as $member): ?>
                                                    <div class="member-item">
                                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?> (<?php echo $member['course']; ?>) - <strong><?php echo $member['college']; ?></strong></span>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this student from team?')">
                                                            <input type="hidden" name="student_id" value="<?php echo $member['id']; ?>">
                                                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                            <button type="submit" name="remove_student_from_team" class="btn-delete btn-sm">Remove</button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="team-actions" style="padding: 1rem; border-top: 1px solid #e5e7eb;">
                                        <button class="btn-primary btn-sm" onclick="showSection('addstudents')">Add Members</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users-slash"></i>
                            <p>No teams created yet.</p>
                            <button class="btn-add" onclick="showSection('createteam')" style="margin-top: 1rem;"><i class="fas fa-plus"></i> Create Your First Team</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Create Team Section -->
            <div id="createteam-section" class="content-section">
                <div class="form-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h2 style="color: #003366;"><i class="fas fa-plus-circle"></i> Create New Team</h2>
                    </div>
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Team Name *</label>
                            <input type="text" name="team_name" required placeholder="Enter team name (e.g., COS Basketball Warriors)">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-trophy"></i> Select Contest/Tournament *</label>
                            <select name="contest_id" id="contest_select_create" required onchange="checkCompetitionLevel()">
                                <option value="">Select a contest</option>
                                <?php foreach($availableContests as $contest): ?>
                                    <option value="<?php echo $contest['id']; ?>" 
                                            data-level="<?php echo htmlspecialchars($contest['competition_level']); ?>">
                                        <?php echo htmlspecialchars($contest['name'] . ' - ' . $contest['activity_name'] . ' (' . $contest['competition_level'] . ' - ' . $contest['category_type'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- College Selection - ONLY for Intramurals -->
                        <div class="form-group" id="college_selection_div" style="display: none;">
                            <label><i class="fas fa-building"></i> Select College * (for Intramurals only)</label>
                            <select name="team_college" id="team_college_select">
                                <option value="">Select College</option>
                                <option value="COS">COS - College of Science (BSCS, BSES)</option>
                                <option value="CBM">CBM - College of Business and Management (BSHM, BSOAD)</option>
                                <option value="CFMS">CFMS - College of Fisheries and Marine Sciences (BSF, BSMB)</option>
                                <option value="CTE">CTE - College of Teacher Education (BEED, BSED-Math, BSED-English, BSED-Filipino, BSED-Science)</option>
                            </select>
                            <small class="info-text" style="display: block; margin-top: 5px; color: #666;">
                                <i class="fas fa-info-circle"></i> Only students from this college can join this team. For Provincial, Regional, National, International - no college restriction.
                            </small>
                        </div>
                        
                        <button type="submit" name="create_team" class="btn-submit"><i class="fas fa-check-circle"></i> Create Team</button>
                    </form>
                </div>
            </div>
            
            <!-- Add Students to Team Section -->
            <div id="addstudents-section" class="content-section">
                <div class="form-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h2 style="color: #003366;"><i class="fas fa-user-plus"></i> Add Students to Team</h2>
                    </div>
                    
                    <?php if(count($teams) == 0): ?>
                        <div class="empty-state">
                            <i class="fas fa-users-slash"></i>
                            <p>No teams available. Please create a team first.</p>
                            <button class="btn-add" onclick="showSection('createteam')" style="margin-top: 1rem;"><i class="fas fa-plus"></i> Create Team</button>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="form-group">
                                <label><i class="fas fa-users"></i> Select Team *</label>
                                <select name="team_id" id="team_select_add" required onchange="updateStudentOptions()">
                                    <option value="">Select a team</option>
                                    <?php foreach($teams as $team): ?>
                                        <option value="<?php echo $team['id']; ?>" 
                                                data-level="<?php echo htmlspecialchars($team['competition_level'] ?? ''); ?>"
                                                data-college="<?php echo htmlspecialchars($team['college_filter'] ?? ''); ?>">
                                            <?php 
                                                $collegeDisplay = '';
                                                if (($team['competition_level'] ?? '') === 'Intramurals' && !empty($team['college_filter'])) {
                                                    $collegeDisplay = ' [' . htmlspecialchars($team['college_filter']) . ' only]';
                                                }
                                                echo htmlspecialchars($team['team_name'] . ' - ' . ($team['contest_name'] ?? 'No Contest') . $collegeDisplay); 
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-user-graduate"></i> Select Student *</label>
                                <select name="student_id" id="student_select_add" required>
                                    <option value="">Select a student</option>
                                    <?php foreach($allStudents as $student): ?>
                                        <option value="<?php echo $student['id']; ?>" data-college="<?php echo htmlspecialchars($student['college']); ?>">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' - ' . $student['course'] . ' (' . $student['college'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div id="college_warning" class="alert-error" style="display: none; margin-bottom: 1rem;"></div>
                            
                            <button type="submit" name="add_student_to_team" class="btn-submit"><i class="fas fa-user-plus"></i> Add Student to Team</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- My Students Section -->
            <div id="students-section" class="content-section">
                <div class="filter-bar">
                    <div class="filter-group">
                        <i class="fas fa-search"></i>
                        <input type="text" id="studentSearch" placeholder="Search students..." onkeyup="filterStudents()">
                    </div>
                    <div class="filter-group">
                        <i class="fas fa-filter"></i>
                        <select id="categoryFilter" onchange="filterStudents()">
                            <option value="">All Categories</option>
                            <option value="Sport">Sport</option>
                            <option value="Athletic">Athletic</option>
                            <option value="Culture and Arts">Culture and Arts</option>
                        </select>
                    </div>
                </div>
                
                <?php if(count($students) > 0): ?>
                    <div class="data-table">
                        <div class="table-header">
                            <h2><i class="fas fa-user-graduate"></i> My Students</h2>
                        </div>
                        <table class="data-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Course</th>
                                    <th>College</th>
                                    <th>Teams Joined</th>
                                    <th>Categories</th>
                                </tr>
                            </thead>
                            <tbody id="studentsTableBody">
                                <?php foreach($students as $student): ?>
                                    <tr class="student-row" data-categories="<?php echo htmlspecialchars($student['category_types']); ?>">
                                        <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                        <td><?php echo htmlspecialchars($student['course']); ?> (<?php echo $student['yr_level']; ?>)
                                        <td><strong><?php echo htmlspecialchars($student['college']); ?></strong>
                                        <td><?php echo htmlspecialchars($student['teams']); ?>
                                        <td><?php echo htmlspecialchars($student['category_types']); ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                         </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-graduate"></i>
                        <p>No students assigned to your teams yet.</p>
                        <button class="btn-add" onclick="showSection('addstudents')" style="margin-top: 1rem;"><i class="fas fa-user-plus"></i> Add Students</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Profile Section -->
            <div id="profile-section" class="content-section">
                <div class="data-table">
                    <div class="table-header">
                        <h2><i class="fas fa-user-circle"></i> Profile Information</h2>
                    </div>
                    <form method="POST" style="padding: 1.5rem;">
                        <div class="form-group"><label><i class="fas fa-user"></i> First Name</label><input type="text" name="first_name" value="<?php echo htmlspecialchars($coach['first_name']); ?>" required></div>
                        <div class="form-group"><label><i class="fas fa-user"></i> Last Name</label><input type="text" name="last_name" value="<?php echo htmlspecialchars($coach['last_name']); ?>" required></div>
                        <div class="form-group"><label><i class="fas fa-envelope"></i> Email</label><input type="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" disabled style="background:#e9ecef"></div>
                        <button type="submit" name="update_profile" class="btn-submit"><i class="fas fa-save"></i> Update Profile</button>
                    </form>
                </div>
                
                <div class="data-table">
                    <div class="table-header">
                        <h2><i class="fas fa-key"></i> Change Password</h2>
                    </div>
                    <form method="POST" style="padding: 1.5rem;">
                        <div class="form-group"><label><i class="fas fa-lock"></i> Current Password</label><input type="password" name="current_password" required></div>
                        <div class="form-group"><label><i class="fas fa-key"></i> New Password</label><input type="password" name="new_password" required></div>
                        <div class="form-group"><label><i class="fas fa-check-circle"></i> Confirm New Password</label><input type="password" name="confirm_password" required></div>
                        <button type="submit" name="change_password" class="btn-submit"><i class="fas fa-sync-alt"></i> Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mobile-menu-btn" onclick="toggleMobileMenu()">
        <i class="fas fa-bars"></i>
    </div>
    
    <script>
        function showSection(section) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(el => el.classList.remove('active'));
            
            // Show selected section
            document.getElementById(`${section}-section`).classList.add('active');
            
            // Update active nav item
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            document.querySelector(`.nav-item[data-section="${section}"]`).classList.add('active');
            
            // Update page title
            const titles = {
                'dashboard': 'Coach Dashboard',
                'myteams': 'My Teams',
                'createteam': 'Create Team',
                'addstudents': 'Add Students to Team',
                'students': 'My Students',
                'profile': 'My Profile'
            };
            document.getElementById('pageTitle').innerText = titles[section] || 'Coach Dashboard';
            
            // Close mobile menu after selection
            if (window.innerWidth <= 768) {
                document.querySelector('.sidebar').classList.remove('show');
            }
        }
        
        function toggleMobileMenu() {
            document.querySelector('.sidebar').classList.toggle('show');
        }
        
        function checkCompetitionLevel() {
            const select = document.getElementById('contest_select_create');
            const selectedOption = select.options[select.selectedIndex];
            const competitionLevel = selectedOption ? selectedOption.getAttribute('data-level') : '';
            const collegeDiv = document.getElementById('college_selection_div');
            const collegeSelect = document.getElementById('team_college_select');
            
            if (competitionLevel === 'Intramurals') {
                collegeDiv.style.display = 'block';
                collegeSelect.required = true;
            } else {
                collegeDiv.style.display = 'none';
                collegeSelect.required = false;
                collegeSelect.value = '';
            }
        }
        
        function updateStudentOptions() {
            const teamSelect = document.getElementById('team_select_add');
            const selectedTeam = teamSelect.options[teamSelect.selectedIndex];
            const competitionLevel = selectedTeam ? selectedTeam.getAttribute('data-level') : '';
            const teamCollege = selectedTeam ? selectedTeam.getAttribute('data-college') : '';
            const studentSelect = document.getElementById('student_select_add');
            const warningDiv = document.getElementById('college_warning');
            
            warningDiv.style.display = 'none';
            warningDiv.innerHTML = '';
            
            const oldInfo = document.getElementById('team_filter_info');
            if (oldInfo) oldInfo.remove();
            
            for (let i = 0; i < studentSelect.options.length; i++) {
                studentSelect.options[i].style.display = '';
                studentSelect.options[i].disabled = false;
            }
            
            if (competitionLevel === 'Intramurals' && teamCollege) {
                let hasVisibleOptions = false;
                
                for (let i = 0; i < studentSelect.options.length; i++) {
                    const option = studentSelect.options[i];
                    const studentCollege = option.getAttribute('data-college');
                    
                    if (option.value !== '' && studentCollege !== teamCollege) {
                        option.style.display = 'none';
                        option.disabled = true;
                    } else if (option.value !== '') {
                        hasVisibleOptions = true;
                    }
                }
                
                if (!hasVisibleOptions) {
                    warningDiv.style.display = 'block';
                    warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> No students available from ' + teamCollege + ' college.';
                }
                
                const infoMsg = document.createElement('div');
                infoMsg.className = 'info-box';
                infoMsg.style.marginTop = '10px';
                infoMsg.id = 'team_filter_info';
                infoMsg.innerHTML = '<i class="fas fa-info-circle"></i> This team is filtered for <strong>' + teamCollege + '</strong> college students only.';
                studentSelect.parentNode.appendChild(infoMsg);
            }
        }
        
        function filterStudents() {
            const search = document.getElementById('studentSearch').value.toLowerCase();
            const category = document.getElementById('categoryFilter').value;
            const rows = document.querySelectorAll('.student-row');
            
            rows.forEach(row => {
                const name = row.cells[0].innerText.toLowerCase();
                const categories = row.getAttribute('data-categories') || '';
                let show = true;
                if (search && !name.includes(search)) show = false;
                if (category && !categories.includes(category)) show = false;
                row.style.display = show ? '' : 'none';
            });
        }
        
        // Handle URL parameters for initial section
        const urlParams = new URLSearchParams(window.location.search);
        const section = urlParams.get('section');
        if (section && ['dashboard', 'myteams', 'createteam', 'addstudents', 'students', 'profile'].includes(section)) {
            showSection(section);
        } else {
            // Set default active section
            showSection('dashboard');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            checkCompetitionLevel();
            
            const teamSelect = document.getElementById('team_select_add');
            if (teamSelect) {
                teamSelect.addEventListener('change', updateStudentOptions);
            }
        });
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.querySelector('.sidebar').classList.remove('show');
            }
        });
    </script>
</body>
</html>