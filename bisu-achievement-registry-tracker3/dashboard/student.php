<?php
require_once '../includes/session.php';
redirectIfNotLoggedIn();

if ($_SESSION['usertype'] !== 'student') {
    header("Location: " . getDashboardPath($_SESSION['usertype'], $_SESSION['admin_type'] ?? null));
    exit();
}

require_once '../config/database.php';

// Get student info
$student = null;
try {
    $stmt = $pdo->prepare("
        SELECT s.*, u.first_name, u.last_name, u.email 
        FROM student s
        JOIN \"User\" u ON s.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        die("Error: No student profile found. Please contact administrator.");
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get student's multiple teams
$studentTeams = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, t.college_filter, c.name as contest_name, a.activity_name, a.school_year, a.competition_level,
               cat.category_name, cat.category_type, 
               uc.first_name as coach_first, uc.last_name as coach_last,
               p.created_at as joined_at, p.ranking, p.membership_status
        FROM participation p
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        JOIN category cat ON c.category_id = cat.id
        LEFT JOIN coach co ON t.coach_id = co.id
        LEFT JOIN \"User\" uc ON co.user_id = uc.id
        WHERE p.student_id = ? AND p.membership_status = 'active'
        ORDER BY a.school_year DESC, cat.category_type
    ");
    $stmt->execute([$student['id']]);
    $studentTeams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $studentTeams = [];
}

$achievements = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, t.team_name, c.name as contest_name, a.activity_name, a.school_year, cat.category_name, cat.category_type
        FROM participation p
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        JOIN category cat ON c.category_id = cat.id
        WHERE p.student_id = ? AND p.ranking IS NOT NULL AND p.ranking != ''
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$student['id']]);
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $achievements = [];
}

// Get available teams for joining
$availableTeams = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, t.college_filter, c.name as contest_name, a.activity_name, a.school_year, a.competition_level,
               cat.category_name, cat.category_type,
               uc.first_name as coach_first, uc.last_name as coach_last,
               COUNT(DISTINCT p.student_id) as current_members
        FROM team t
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        JOIN category cat ON c.category_id = cat.id
        LEFT JOIN coach co ON t.coach_id = co.id
        LEFT JOIN \"User\" uc ON co.user_id = uc.id
        LEFT JOIN participation p ON t.id = p.team_id AND p.membership_status = 'active'
        WHERE c.contest_status = 'active'
        GROUP BY t.id, t.team_name, t.college_filter, c.name, a.activity_name, a.school_year, a.competition_level, 
                 cat.category_name, cat.category_type, uc.first_name, uc.last_name, c.id
        ORDER BY a.school_year DESC, t.team_name
    ");
    $stmt->execute();
    $allTeams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $studentCollege = $student['college'];
    $existingCategoryTypes = array_column($studentTeams, 'category_type');
    
    foreach ($allTeams as $team) {
        if (in_array($team['category_type'], $existingCategoryTypes)) {
            continue;
        }
        
        $canJoin = true;
        if ($team['competition_level'] === 'Intramurals') {
            if (!empty($team['college_filter']) && $team['college_filter'] !== $studentCollege) {
                $canJoin = false;
            }
        }
        
        if ($canJoin) {
            $availableTeams[] = $team;
        }
    }
} catch(PDOException $e) {
    $availableTeams = [];
}

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_team'])) {
    $team_id = $_POST['team_id'];
    
    $stmt = $pdo->prepare("
        SELECT t.id as team_id, t.college_filter, c.id as contest_id, a.competition_level
        FROM team t
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        WHERE t.id = ?
    ");
    $stmt->execute([$team_id]);
    $teamInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($teamInfo) {
        $errorMsg = null;
        
        if ($teamInfo['competition_level'] === 'Intramurals') {
            if (!empty($teamInfo['college_filter']) && $teamInfo['college_filter'] !== $student['college']) {
                $errorMsg = "You cannot join this team because it is for {$teamInfo['college_filter']} college students only.";
            }
        }
        
        if (empty($errorMsg)) {
            $stmtCat = $pdo->prepare("
                SELECT cat.category_type
                FROM contest c
                JOIN category cat ON c.category_id = cat.id
                WHERE c.id = ?
            ");
            $stmtCat->execute([$teamInfo['contest_id']]);
            $categoryType = $stmtCat->fetchColumn();
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM participation p
                JOIN team t ON p.team_id = t.id
                JOIN contest c ON t.contest_id = c.id
                JOIN category cat ON c.category_id = cat.id
                WHERE p.student_id = ? AND p.membership_status = 'active' AND cat.category_type = ?
            ");
            $stmt->execute([$student['id'], $categoryType]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing['count'] > 0) {
                $errorMsg = "You are already in a {$categoryType} team. Cannot join another team in the same category.";
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM participation WHERE team_id = ? AND student_id = ?");
                    $stmt->execute([$team_id, $student['id']]);
                    $existingRecord = $stmt->fetch();
                    
                    if ($existingRecord) {
                        $stmt = $pdo->prepare("UPDATE participation SET membership_status = 'active', created_at = NOW() WHERE id = ?");
                        $stmt->execute([$existingRecord['id']]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO participation (student_id, team_id, membership_status, created_at) VALUES (?, ?, 'active', NOW())");
                        $stmt->execute([$student['id'], $team_id]);
                    }
                    $message = "Successfully joined the team!";
                    header("Location: student.php?success=" . urlencode("Successfully joined the team!"));
                    exit();
                } catch(PDOException $e) {
                    $errorMsg = "Error joining team: " . $e->getMessage();
                }
            }
        }
        $error = $errorMsg;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_team'])) {
    $team_id = $_POST['team_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE participation SET membership_status = 'inactive' WHERE team_id = ? AND student_id = ? AND membership_status = 'active'");
        $stmt->execute([$team_id, $student['id']]);
        $message = "You have left the team.";
        header("Location: student.php?success=" . urlencode("You have left the team."));
        exit();
    } catch(PDOException $e) {
        $error = "Error leaving team: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $course = trim($_POST['course']);
    $yr_level = $_POST['yr_level'];
    $college = $_POST['college'];
    
    try {
        $stmt = $pdo->prepare("UPDATE \"User\" SET first_name = ?, last_name = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $_SESSION['user_id']]);
        
        $stmt = $pdo->prepare("UPDATE student SET course = ?, yr_level = ?, college = ? WHERE id = ?");
        $stmt->execute([$course, $yr_level, $college, $student['id']]);
        $message = "Profile updated successfully!";
        
        $stmt = $pdo->prepare("
            SELECT s.*, u.first_name, u.last_name, u.email 
            FROM student s
            JOIN \"User\" u ON s.user_id = u.id
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
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

function getTeamCollege($pdo, $team_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.college 
        FROM participation p 
        JOIN student s ON p.student_id = s.id 
        WHERE p.team_id = ? AND p.membership_status = 'active' 
        LIMIT 1
    ");
    $stmt->execute([$team_id]);
    return $stmt->fetchColumn();
}

$achievementStats = [
    'total' => count($achievements),
    'by_ranking' => []
];

foreach($achievements as $ach) {
    $ranking = $ach['ranking'] ?? 'No Ranking';
    if(!isset($achievementStats['by_ranking'][$ranking])) {
        $achievementStats['by_ranking'][$ranking] = 0;
    }
    $achievementStats['by_ranking'][$ranking]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - BISU Athletes & Arts Performers Registry</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        /* Student Dashboard Enhanced Styles */
        .welcome-banner {
            background: linear-gradient(135deg, #003366 0%, #1a4d8c 50%, #2a6eb0 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,215,0,0.15), transparent);
            border-radius: 50%;
            animation: floatBanner 8s ease-in-out infinite;
        }
        
        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,215,0,0.1), transparent);
            border-radius: 50%;
            animation: floatBanner2 10s ease-in-out infinite;
        }
        
        @keyframes floatBanner {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-20px, 10px) rotate(5deg); }
        }
        
        @keyframes floatBanner2 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(15px, -10px) rotate(-5deg); }
        }
        
        .welcome-banner h2 {
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            position: relative;
            z-index: 2;
        }
        
        .welcome-banner p {
            opacity: 0.95;
            position: relative;
            z-index: 2;
        }
        
        .welcome-banner .banner-stats {
            display: flex;
            gap: 1.5rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }
        
        .welcome-banner .banner-stat {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .welcome-banner .banner-stat i {
            color: #ffd700;
        }
        
        .achievement-badge {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #856404;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .college-badge {
            background: linear-gradient(135deg, #e8f4f8, #d4eaf0);
            color: #0c5460;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
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
        
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem;
        }
        
        .team-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
        
        .ranking-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .ranking-1st { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #856404; }
        .ranking-2nd { background: linear-gradient(135deg, #c0c0c0, #e0e0e0); color: #495057; }
        .ranking-3rd { background: linear-gradient(135deg, #cd7f32, #e8a87c); color: #5c3a1a; }
        .ranking-default { background: linear-gradient(135deg, #e9ecef, #dee2e6); color: #495057; }
        
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
        
        @media (max-width: 768px) {
            .teams-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-banner .banner-stats {
                flex-direction: column;
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
                <div class="user-role">Student Athlete/Artist</div>
            </div>
            <ul class="nav-menu">
                <li class="nav-item active" data-section="dashboard"><a href="#" onclick="showSection('dashboard')"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="nav-item" data-section="myteams"><a href="#" onclick="showSection('myteams')"><i class="fas fa-users"></i> My Teams</a></li>
                <li class="nav-item" data-section="achievements"><a href="#" onclick="showSection('achievements')"><i class="fas fa-medal"></i> My Achievements</a></li>
                <li class="nav-item" data-section="profile"><a href="#" onclick="showSection('profile')"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li class="nav-item"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1 id="pageTitle">Student Dashboard</h1>
                </div>
                <div class="user-info">
                    <span class="user-name"><i class="fas fa-user-check"></i> Welcome, <?php echo htmlspecialchars($student['first_name'] ?? 'User'); ?>!</span>
                    <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            
            <?php if(isset($_GET['success'])): ?>
                <div class="alert-success" style="padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>
            <?php if($message): ?>
                <div class="alert-success" style="padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert-error" style="padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Dashboard Section -->
            <div id="dashboard-section" class="content-section active">
                <div class="welcome-banner">
                    <h2><i class="fas fa-graduation-cap"></i> Welcome back, <?php echo htmlspecialchars($student['first_name']); ?>!</h2>
                    <p>Track your achievements, join teams, and showcase your talents in sports and cultural activities.</p>
                    <div class="banner-stats">
                        <div class="banner-stat"><i class="fas fa-building"></i> <?php echo htmlspecialchars($student['college']); ?></div>
                        <div class="banner-stat"><i class="fas fa-medal"></i> <?php echo count($achievements); ?> Achievements</div>
                        <div class="banner-stat"><i class="fas fa-users"></i> <?php echo count($studentTeams); ?> Teams Joined</div>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-medal"></i>
                        <div class="stat-number"><?php echo count($achievements); ?></div>
                        <div class="stat-label">Total Achievements</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-users"></i>
                        <div class="stat-number"><?php echo count($studentTeams); ?></div>
                        <div class="stat-label">Teams Joined</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-building"></i>
                        <div class="stat-number" style="font-size: 1.3rem;"><?php echo htmlspecialchars($student['college']); ?></div>
                        <div class="stat-label">Your College</div>
                    </div>
                </div>
                
                <?php if(count($studentTeams) > 0): ?>
                <div class="data-table">
                    <div class="table-header">
                        <h2><i class="fas fa-users"></i> My Teams Overview</h2>
                        <button class="btn-add" onclick="showSection('jointeams')"><i class="fas fa-plus"></i> Join More Teams</button>
                    </div>
                    <div class="teams-grid" style="padding: 1rem;">
                        <?php foreach(array_slice($studentTeams, 0, 3) as $team): ?>
                            <div class="team-card">
                                <div class="team-header">
                                    <h3><?php echo htmlspecialchars($team['team_name']); ?></h3>
                                    <span class="category-tag <?php echo strtolower($team['category_type']) == 'sport' ? 'category-sport' : (strtolower($team['category_type']) == 'athletic' ? 'category-athletic' : 'category-cultural'); ?>">
                                        <?php echo htmlspecialchars($team['category_type']); ?>
                                    </span>
                                </div>
                                <div class="team-info" style="padding: 1rem;">
                                    <p><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($team['contest_name']); ?></p>
                                    <p><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($team['activity_name']); ?> (<?php echo htmlspecialchars($team['school_year'] ?? 'N/A'); ?>)</p>
                                    <p><i class="fas fa-chalkboard-user"></i> Coach: <?php echo htmlspecialchars($team['coach_first'] . ' ' . $team['coach_last']); ?></p>
                                    <?php if($team['ranking']): ?>
                                        <p><i class="fas fa-medal"></i> Ranking: <span class="ranking-badge <?php 
                                            echo strpos(strtolower($team['ranking']), '1st') !== false ? 'ranking-1st' : 
                                                (strpos(strtolower($team['ranking']), '2nd') !== false ? 'ranking-2nd' : 
                                                (strpos(strtolower($team['ranking']), '3rd') !== false ? 'ranking-3rd' : 'ranking-default')); 
                                        ?>"><?php echo htmlspecialchars($team['ranking']); ?></span></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if(count($studentTeams) > 3): ?>
                        <div style="text-align: center; padding: 1rem;">
                            <button class="btn-add" onclick="showSection('myteams')">View All Teams (<?php echo count($studentTeams); ?>)</button>
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
                        <button class="btn-add" onclick="showSection('jointeams')"><i class="fas fa-plus"></i> Join More Teams</button>
                    </div>
                    <?php if(count($studentTeams) > 0): ?>
                        <div class="teams-grid" style="padding: 1rem;">
                            <?php foreach($studentTeams as $team): ?>
                                <div class="team-card">
                                    <div class="team-header">
                                        <h3><?php echo htmlspecialchars($team['team_name']); ?></h3>
                                        <span class="category-tag <?php echo strtolower($team['category_type']) == 'sport' ? 'category-sport' : (strtolower($team['category_type']) == 'athletic' ? 'category-athletic' : 'category-cultural'); ?>">
                                            <?php echo htmlspecialchars($team['category_type']); ?>
                                        </span>
                                    </div>
                                    <div class="team-info" style="padding: 1rem;">
                                        <p><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($team['contest_name']); ?></p>
                                        <p><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($team['activity_name']); ?> (<?php echo htmlspecialchars($team['school_year'] ?? 'N/A'); ?>)</p>
                                        <p><i class="fas fa-tag"></i> Level: <strong><?php echo htmlspecialchars($team['competition_level']); ?></strong></p>
                                        <p><i class="fas fa-chalkboard-user"></i> Coach: <?php echo htmlspecialchars($team['coach_first'] . ' ' . $team['coach_last']); ?></p>
                                        <?php if($team['competition_level'] === 'Intramurals'): ?>
                                            <?php $teamCollege = getTeamCollege($pdo, $team['id']); ?>
                                            <p><i class="fas fa-building"></i> Team College: <strong><?php echo $teamCollege ? htmlspecialchars($teamCollege) : 'No members yet'; ?></strong></p>
                                        <?php endif; ?>
                                        <p><i class="fas fa-clock"></i> Joined: <?php echo date('M d, Y', strtotime($team['joined_at'])); ?></p>
                                        <?php if($team['ranking']): ?>
                                            <p><i class="fas fa-medal"></i> Ranking: <span class="ranking-badge <?php 
                                                echo strpos(strtolower($team['ranking']), '1st') !== false ? 'ranking-1st' : 
                                                    (strpos(strtolower($team['ranking']), '2nd') !== false ? 'ranking-2nd' : 
                                                    (strpos(strtolower($team['ranking']), '3rd') !== false ? 'ranking-3rd' : 'ranking-default')); 
                                            ?>"><?php echo htmlspecialchars($team['ranking']); ?></span></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="team-actions" style="padding: 1rem; border-top: 1px solid #e5e7eb;">
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to leave this team?')">
                                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                            <button type="submit" name="leave_team" class="btn-delete"><i class="fas fa-sign-out-alt"></i> Leave Team</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users-slash"></i>
                            <p>You are not in any team yet.</p>
                            <button class="btn-add" onclick="showSection('jointeams')" style="margin-top: 1rem;"><i class="fas fa-plus"></i> Join a Team</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Join Teams Section -->
            <div id="jointeams-section" class="content-section">
                <div class="data-table">
                    <div class="table-header">
                        <h2><i class="fas fa-user-plus"></i> Available Teams to Join</h2>
                    </div>
                    <?php if(count($availableTeams) > 0): ?>
                        <div class="teams-grid" style="padding: 1rem;">
                            <?php foreach($availableTeams as $team): ?>
                                <div class="team-card">
                                    <div class="team-header" style="background: linear-gradient(135deg, #27ae60, #1e8449);">
                                        <h3><?php echo htmlspecialchars($team['team_name']); ?></h3>
                                        <span class="category-tag <?php echo strtolower($team['category_type']) == 'sport' ? 'category-sport' : (strtolower($team['category_type']) == 'athletic' ? 'category-athletic' : 'category-cultural'); ?>">
                                            <?php echo htmlspecialchars($team['category_type']); ?>
                                        </span>
                                    </div>
                                    <div class="team-info" style="padding: 1rem;">
                                        <p><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($team['contest_name']); ?></p>
                                        <p><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($team['activity_name']); ?> (<?php echo htmlspecialchars($team['school_year'] ?? 'N/A'); ?>)</p>
                                        <p><i class="fas fa-tag"></i> Level: <strong><?php echo htmlspecialchars($team['competition_level']); ?></strong></p>
                                        <p><i class="fas fa-chalkboard-user"></i> Coach: <?php echo htmlspecialchars($team['coach_first'] . ' ' . $team['coach_last']); ?></p>
                                        <?php if($team['competition_level'] === 'Intramurals' && !empty($team['college_filter'])): ?>
                                            <p><i class="fas fa-building"></i> Team College: <strong><?php echo htmlspecialchars($team['college_filter']); ?></strong></p>
                                        <?php elseif($team['competition_level'] === 'Intramurals'): ?>
                                            <p><i class="fas fa-building"></i> Team College: <strong>Open (First member sets the college)</strong></p>
                                        <?php else: ?>
                                            <p><i class="fas fa-globe"></i> Open to all colleges</p>
                                        <?php endif; ?>
                                        <p><i class="fas fa-users"></i> Members: <?php echo $team['current_members']; ?></p>
                                    </div>
                                    <div class="team-actions" style="padding: 1rem; border-top: 1px solid #e5e7eb;">
                                        <form method="POST" onsubmit="return confirm('Join this team?')">
                                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                            <button type="submit" name="join_team" class="btn-add"><i class="fas fa-sign-in-alt"></i> Join Team</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users-slash"></i>
                            <p>No available teams to join at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Achievements Section -->
            <div id="achievements-section" class="content-section">
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="stat-card">
                        <i class="fas fa-medal"></i>
                        <div class="stat-number"><?php echo $achievementStats['total']; ?></div>
                        <div class="stat-label">Total Achievements</div>
                    </div>
                    <?php foreach($achievementStats['by_ranking'] as $ranking => $count): ?>
                        <div class="stat-card">
                            <i class="fas fa-award"></i>
                            <div class="stat-number"><?php echo $count; ?></div>
                            <div class="stat-label"><?php echo htmlspecialchars($ranking); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if(count($achievements) > 0): ?>
                    <div class="data-table">
                        <div class="table-header">
                            <h2><i class="fas fa-list"></i> All Achievements</h2>
                        </div>
                        <table class="data-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Contest</th>
                                    <th>Activity</th>
                                    <th>Category</th>
                                    <th>Team</th>
                                    <th>Ranking</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($achievements as $achievement): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($achievement['contest_name']); ?>
                                        <td><?php echo htmlspecialchars($achievement['activity_name']); ?> (<?php echo htmlspecialchars($achievement['school_year'] ?? 'N/A'); ?>)
                                        <td><span class="category-tag category-cultural"><?php echo htmlspecialchars($achievement['category_name']); ?></span>
                                        <td><?php echo htmlspecialchars($achievement['team_name']); ?>
                                        <td><?php 
                                            $rankClass = 'ranking-default';
                                            if(strpos(strtolower($achievement['ranking']), '1st') !== false) $rankClass = 'ranking-1st';
                                            elseif(strpos(strtolower($achievement['ranking']), '2nd') !== false) $rankClass = 'ranking-2nd';
                                            elseif(strpos(strtolower($achievement['ranking']), '3rd') !== false) $rankClass = 'ranking-3rd';
                                        ?><span class="ranking-badge <?php echo $rankClass; ?>"><?php echo htmlspecialchars($achievement['ranking']); ?></span>
                                        <td><?php echo date('M d, Y', strtotime($achievement['created_at'])); ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-medal"></i>
                        <p>No achievements yet. Join teams and compete!</p>
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
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> First Name</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Last Name</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-graduation-cap"></i> Course</label>
                            <input type="text" name="course" value="<?php echo htmlspecialchars($student['course']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-layer-group"></i> Year Level</label>
                            <select name="yr_level" required>
                                <option value="1" <?php echo $student['yr_level'] == 1 ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2" <?php echo $student['yr_level'] == 2 ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3" <?php echo $student['yr_level'] == 3 ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4" <?php echo $student['yr_level'] == 4 ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> College</label>
                            <select name="college" required>
                                <option value="COS" <?php echo $student['college'] == 'COS' ? 'selected' : ''; ?>>COS - College of Science</option>
                                <option value="CTE" <?php echo $student['college'] == 'CTE' ? 'selected' : ''; ?>>CTE - College of Teacher Education</option>
                                <option value="CBM" <?php echo $student['college'] == 'CBM' ? 'selected' : ''; ?>>CBM - College of Business and Management</option>
                                <option value="CFMS" <?php echo $student['college'] == 'CFMS' ? 'selected' : ''; ?>>CFMS - College of Fisheries and Marine Sciences</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" disabled style="background:#e9ecef">
                        </div>
                        <button type="submit" name="update_profile" class="btn-submit"><i class="fas fa-save"></i> Update Profile</button>
                    </form>
                </div>
                
                <div class="data-table">
                    <div class="table-header">
                        <h2><i class="fas fa-key"></i> Change Password</h2>
                    </div>
                    <form method="POST" style="padding: 1.5rem;">
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> New Password</label>
                            <input type="password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-check-circle"></i> Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
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
                'dashboard': 'Student Dashboard',
                'myteams': 'My Teams',
                'jointeams': 'Join Teams',
                'achievements': 'My Achievements',
                'profile': 'My Profile'
            };
            document.getElementById('pageTitle').innerText = titles[section] || 'Student Dashboard';
            
            // Close mobile menu after selection
            if (window.innerWidth <= 768) {
                document.querySelector('.sidebar').classList.remove('show');
            }
        }
        
        function toggleMobileMenu() {
            document.querySelector('.sidebar').classList.toggle('show');
        }
        
        // Handle URL parameters for initial section
        const urlParams = new URLSearchParams(window.location.search);
        const section = urlParams.get('section');
        if (section && ['dashboard', 'myteams', 'jointeams', 'achievements', 'profile'].includes(section)) {
            showSection(section);
        } else {
            showSection('dashboard');
        }
        
        // Handle responsive sidebar
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.querySelector('.sidebar').classList.remove('show');
            }
        });
    </script>
</body>
</html>