<?php
// admin_sports_analytics.php - Analytics Dashboard for Sports Admin
require_once '../includes/session.php';
redirectIfNotLoggedIn();

if ($_SESSION['usertype'] !== 'admin' || $_SESSION['admin_type'] !== 'sports') {
    header("Location: " . getDashboardPath($_SESSION['usertype'], $_SESSION['admin_type'] ?? null));
    exit();
}

require_once '../config/database.php';

// Get admin info
$admin_name = $_SESSION['email'] ?? 'Sports Admin';
$current_admin_type = 'sports';

// Get current school year
function getCurrentSchoolYear() {
    $current_year = date('Y');
    $current_month = date('n');
    if ($current_month >= 6) {
        return $current_year . '-' . ($current_year + 1);
    } else {
        return ($current_year - 1) . '-' . $current_year;
    }
}

// Get available school years for filtering
$availableSchoolYears = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT a.school_year 
        FROM activity a 
        WHERE a.activity_type = 'sports' AND a.school_year IS NOT NULL
        ORDER BY a.school_year DESC
    ");
    $stmt->execute();
    $availableSchoolYears = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($availableSchoolYears)) {
        $availableSchoolYears = [['school_year' => getCurrentSchoolYear()]];
    }
} catch(PDOException $e) {
    $availableSchoolYears = [['school_year' => getCurrentSchoolYear()]];
}

$selected_school_year = isset($_GET['school_year']) ? $_GET['school_year'] : 'all';
$selected_competition_level = isset($_GET['competition_level']) ? $_GET['competition_level'] : 'all';

// Build WHERE clause for filtering
$where_conditions = ["a.activity_type = 'sports'"];
$params = [];

if ($selected_school_year !== 'all') {
    $where_conditions[] = "a.school_year = ?";
    $params[] = $selected_school_year;
}

if ($selected_competition_level !== 'all') {
    $where_conditions[] = "a.competition_level = ?";
    $params[] = $selected_competition_level;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// =====================================================
// DATA FOR CHARTS
// =====================================================

// 1. PARTICIPATION BY CATEGORY TYPE (Sports vs Athletic)
try {
    $stmt = $pdo->prepare("
        SELECT 
            cat.category_type,
            COUNT(DISTINCT p.id) as total_participations,
            COUNT(DISTINCT CASE WHEN p.ranking IS NOT NULL THEN p.id END) as with_rankings,
            COUNT(DISTINCT s.id) as unique_students
        FROM participation p
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        JOIN category cat ON c.category_id = cat.id
        JOIN student s ON p.student_id = s.id
        {$where_clause}
        AND p.membership_status = 'active'
        GROUP BY cat.category_type
        ORDER BY total_participations DESC
    ");
    $stmt->execute($params);
    $categoryParticipation = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $categoryParticipation = [];
}

// 2. PARTICIPATION BY COMPETITION LEVEL
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.competition_level,
            COUNT(DISTINCT p.id) as total_participations,
            COUNT(DISTINCT CASE WHEN p.ranking IS NOT NULL THEN p.id END) as with_rankings,
            COUNT(DISTINCT t.id) as total_teams,
            COUNT(DISTINCT c.id) as total_contests
        FROM participation p
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        {$where_clause}
        AND p.membership_status = 'active'
        GROUP BY a.competition_level
        ORDER BY 
            CASE a.competition_level
                WHEN 'Intramurals' THEN 1
                WHEN 'Provincial' THEN 2
                WHEN 'Regional' THEN 3
                WHEN 'National' THEN 4
                WHEN 'International' THEN 5
                ELSE 6
            END
    ");
    $stmt->execute($params);
    $levelParticipation = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $levelParticipation = [];
}

// 3. TOP PERFORMING COLLEGES (by medals)
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.college,
            COUNT(CASE WHEN p.ranking IN ('1st Place', 'Champion') THEN 1 END) as gold_medals,
            COUNT(CASE WHEN p.ranking IN ('2nd Place', '1st Runner Up') THEN 1 END) as silver_medals,
            COUNT(CASE WHEN p.ranking IN ('3rd Place', '2nd Runner Up') THEN 1 END) as bronze_medals,
            COUNT(DISTINCT p.id) as total_participations,
            COUNT(DISTINCT CASE WHEN p.ranking IS NOT NULL THEN p.id END) as medals_won
        FROM participation p
        JOIN student s ON p.student_id = s.id
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        {$where_clause}
        AND p.membership_status = 'active'
        GROUP BY s.college
        ORDER BY gold_medals DESC, silver_medals DESC, bronze_medals DESC
    ");
    $stmt->execute($params);
    $collegePerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $collegePerformance = [];
}

// 4. TREND OVER SCHOOL YEARS
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.school_year,
            COUNT(DISTINCT p.id) as total_participations,
            COUNT(DISTINCT CASE WHEN p.ranking IS NOT NULL THEN p.id END) as with_rankings,
            COUNT(DISTINCT t.id) as total_teams,
            COUNT(DISTINCT c.id) as total_contests,
            COUNT(DISTINCT s.id) as unique_students
        FROM participation p
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        JOIN student s ON p.student_id = s.id
        WHERE a.activity_type = 'sports' AND p.membership_status = 'active'
        GROUP BY a.school_year
        ORDER BY a.school_year DESC
    ");
    $stmt->execute();
    $yearlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $yearlyTrend = [];
}

// 5. PARTICIPATION BY COLLEGE (for current filter)
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.college,
            COUNT(DISTINCT p.id) as total_participations,
            COUNT(DISTINCT CASE WHEN p.ranking IS NOT NULL THEN p.id END) as with_rankings,
            COUNT(DISTINCT s.id) as unique_students
        FROM participation p
        JOIN student s ON p.student_id = s.id
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        {$where_clause}
        AND p.membership_status = 'active'
        GROUP BY s.college
        ORDER BY total_participations DESC
    ");
    $stmt->execute($params);
    $collegeParticipation = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $collegeParticipation = [];
}

// 6. RANKING DISTRIBUTION
try {
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN p.ranking IN ('1st Place', 'Champion') THEN 'Gold (1st/Champion)'
                WHEN p.ranking IN ('2nd Place', '1st Runner Up') THEN 'Silver (2nd/1st RU)'
                WHEN p.ranking IN ('3rd Place', '2nd Runner Up') THEN 'Bronze (3rd/2nd RU)'
                WHEN p.ranking IN ('Finalist', 'Semi-finalist', 'Quarter-finalist') THEN 'Finalist/Participant'
                ELSE 'Other'
            END as medal_category,
            COUNT(*) as count
        FROM participation p
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        {$where_clause}
        AND p.membership_status = 'active'
        AND p.ranking IS NOT NULL
        GROUP BY medal_category
        ORDER BY 
            CASE 
                WHEN medal_category = 'Gold (1st/Champion)' THEN 1
                WHEN medal_category = 'Silver (2nd/1st RU)' THEN 2
                WHEN medal_category = 'Bronze (3rd/2nd RU)' THEN 3
                ELSE 4
            END
    ");
    $stmt->execute($params);
    $rankingDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $rankingDistribution = [];
}

// 7. TOP SPORTS/ACTIVITIES
try {
    $stmt = $pdo->prepare("
        SELECT 
            cat.category_name,
            COUNT(DISTINCT p.id) as total_participations,
            COUNT(DISTINCT CASE WHEN p.ranking IS NOT NULL THEN p.id END) as medals_won,
            COUNT(DISTINCT t.id) as teams_count
        FROM participation p
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        JOIN category cat ON c.category_id = cat.id
        {$where_clause}
        AND p.membership_status = 'active'
        GROUP BY cat.category_name
        ORDER BY total_participations DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $topCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $topCategories = [];
}

// 8. OVERALL STATISTICS
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT p.id) as total_participations,
            COUNT(DISTINCT CASE WHEN p.ranking IS NOT NULL THEN p.id END) as with_rankings,
            COUNT(DISTINCT s.id) as unique_students,
            COUNT(DISTINCT t.id) as total_teams,
            COUNT(DISTINCT c.id) as total_contests,
            COUNT(DISTINCT a.id) as total_activities,
            COUNT(DISTINCT CASE WHEN p.ranking IN ('1st Place', 'Champion') THEN p.id END) as gold_medals,
            COUNT(DISTINCT CASE WHEN p.ranking IN ('2nd Place', '1st Runner Up') THEN p.id END) as silver_medals,
            COUNT(DISTINCT CASE WHEN p.ranking IN ('3rd Place', '2nd Runner Up') THEN p.id END) as bronze_medals
        FROM participation p
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        JOIN student s ON p.student_id = s.id
        {$where_clause}
        AND p.membership_status = 'active'
    ");
    $stmt->execute($params);
    $overallStats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $overallStats = [
        'total_participations' => 0,
        'with_rankings' => 0,
        'unique_students' => 0,
        'total_teams' => 0,
        'total_contests' => 0,
        'total_activities' => 0,
        'gold_medals' => 0,
        'silver_medals' => 0,
        'bronze_medals' => 0
    ];
}

// Get coaches count
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id) as total_coaches
        FROM coach c
        JOIN team t ON c.id = t.coach_id
        JOIN contest con ON t.contest_id = con.id
        JOIN activity a ON con.activity_id = a.id
        {$where_clause}
    ");
    $stmt->execute($params);
    $coachStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $overallStats['total_coaches'] = $coachStats['total_coaches'] ?? 0;
} catch(PDOException $e) {
    $overallStats['total_coaches'] = 0;
}

$achievement_rate = $overallStats['total_participations'] > 0 
    ? round(($overallStats['with_rankings'] / $overallStats['total_participations']) * 100, 1)
    : 0;

// Prepare JSON data for charts
$chartData = [
    'categoryParticipation' => $categoryParticipation,
    'levelParticipation' => $levelParticipation,
    'collegePerformance' => $collegePerformance,
    'yearlyTrend' => $yearlyTrend,
    'collegeParticipation' => $collegeParticipation,
    'rankingDistribution' => $rankingDistribution,
    'topCategories' => $topCategories
];
$chartDataJson = json_encode($chartData);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sports Analytics Dashboard - BISU Achievement Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
        .dashboard-container { display: flex; min-height: 100vh; }
        
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: #003366;
            color: white;
            padding: 2rem 1rem;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 100;
        }
        .sidebar-header {
            text-align: center;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 2rem;
        }
        .sidebar-header h2 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: white;
        }
        .user-role {
            background: rgba(255,255,255,0.2);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            display: inline-block;
            font-size: 0.8rem;
        }
        .nav-menu {
            list-style: none;
        }
        .nav-item {
            margin-bottom: 0.5rem;
        }
        .nav-item a {
            color: white;
            text-decoration: none;
            padding: 0.8rem 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .nav-item a:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 1.5rem;
        }
        .nav-item.active a {
            background: rgba(255,255,255,0.2);
            border-left: 3px solid #ffd700;
        }
        /* Main Content */
        .main-content { flex: 1; margin-left: 280px; padding: 2rem; }
        .top-bar { background: white; padding: 1rem 2rem; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .page-title h1 { font-size: 1.5rem; color: #003366; }
        .user-info { display: flex; align-items: center; gap: 1rem; }
        .admin-badge { background: #27ae60; color: white; padding: 0.3rem 0.8rem; border-radius: 20px; font-weight: bold; }
        
        /* Filter Bar */
        .filter-bar { background: white; padding: 1rem 1.5rem; border-radius: 10px; margin-bottom: 2rem; display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-weight: 600; color: #333; font-size: 0.85rem; }
        .filter-group select { padding: 0.5rem 1rem; border: 1px solid #ddd; border-radius: 5px; font-size: 0.9rem; min-width: 180px; }
        .filter-group button { background: #003366; color: white; border: none; padding: 0.5rem 1.5rem; border-radius: 5px; cursor: pointer; font-weight: 600; }
        .filter-group button:hover { background: #002244; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1rem; border-radius: 10px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stat-card i { font-size: 2rem; color: #003366; margin-bottom: 0.5rem; }
        .stat-card .number { font-size: 1.8rem; font-weight: bold; color: #003366; }
        .stat-card .label { color: #666; font-size: 0.8rem; }
        .achievement-rate { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
        .achievement-rate .number, .achievement-rate .label { color: white; }
        
        /* Chart Grid */
        .chart-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .chart-card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .chart-header { background: #f8f9fa; padding: 1rem; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .chart-header h3 { color: #003366; font-size: 1.1rem; }
        .chart-header i { color: #27ae60; }
        .chart-body { padding: 1.5rem; min-height: 400px; }
        canvas { max-height: 350px; width: 100%; }
        
        /* Full width chart */
        .chart-full { grid-column: 1 / -1; }
        
        /* Medal Colors */
        .medal-gold { color: #ffd700; text-shadow: 0 0 2px #856404; }
        .medal-silver { color: #c0c0c0; text-shadow: 0 0 2px #495057; }
        .medal-bronze { color: #cd7f32; text-shadow: 0 0 2px #5c3a1a; }
        
        /* Table Styles */
        .data-table { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .table-header { padding: 1rem; background: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .table-header h3 { color: #003366; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #f8f9fa; font-weight: 600; color: #555; }
        
        .btn-print { background: #28a745; color: white; padding: 0.5rem 1rem; border: none; border-radius: 5px; cursor: pointer; }
        .btn-print:hover { background: #218838; }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; height: auto; }
            .main-content { margin-left: 0; }
            .chart-grid { grid-template-columns: 1fr; }
        }
        
        @media print {
            .sidebar, .top-bar, .filter-bar, .btn-print, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .chart-card { break-inside: avoid; }
        }
        
        /* Sidebar Logo Styles */
        .sidebar-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
            margin-bottom: 1rem;
            border-radius: 10px;
            background: white;
            padding: 5px;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 1rem;
        }
        .logo-icon img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffd700;
            padding: 2px;
            background: white;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon">
                <img src="../includes/uploads/images/bisu_logo.png" alt="BISU Logo" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
            </div>
            <h2>BISU Tracker</h2>
            <div class="user-role">Sports Admin</div>
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="admin_sports.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item active"><a href="admin_sports_analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a></li>
            <li class="nav-item"><a href="admin_sports.php?section=events"><i class="fas fa-calendar-alt"></i> Sports Events</a></li>
            <li class="nav-item"><a href="admin_sports.php?section=tournaments"><i class="fas fa-trophy"></i> Manage Tournaments</a></li>
            <li class="nav-item"><a href="admin_sports.php?section=participants"><i class="fas fa-users"></i> Teams</a></li>
            <li class="nav-item"><a href="admin_sports.php?section=participations"><i class="fas fa-list-alt"></i> Participants List</a></li>
            <li class="nav-item"><a href="admin_sports.php?section=announcements"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="admin_sports_report.php"><i class="fas fa-print"></i> Reports</a></li>
            <li class="nav-item"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-chart-bar"></i> Sports Analytics Dashboard</h1>
            </div>
            <div class="user-info">
                <span class="admin-badge"><i class="fas fa-futbol"></i> SPORTS ADMIN</span>
                <span><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($admin_name); ?></span>
                <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 1.5rem; flex-wrap: wrap; width: 100%;">
                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt"></i> School Year</label>
                    <select name="school_year" onchange="this.form.submit()">
                        <option value="all" <?php echo $selected_school_year == 'all' ? 'selected' : ''; ?>>All School Years</option>
                        <?php foreach($availableSchoolYears as $sy): ?>
                            <option value="<?php echo htmlspecialchars($sy['school_year']); ?>" <?php echo $selected_school_year == $sy['school_year'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sy['school_year']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-tag"></i> Competition Level</label>
                    <select name="competition_level" onchange="this.form.submit()">
                        <option value="all" <?php echo $selected_competition_level == 'all' ? 'selected' : ''; ?>>All Levels</option>
                        <option value="Intramurals" <?php echo $selected_competition_level == 'Intramurals' ? 'selected' : ''; ?>>Intramurals</option>
                        <option value="Provincial" <?php echo $selected_competition_level == 'Provincial' ? 'selected' : ''; ?>>Provincial</option>
                        <option value="Regional" <?php echo $selected_competition_level == 'Regional' ? 'selected' : ''; ?>>Regional</option>
                        <option value="National" <?php echo $selected_competition_level == 'National' ? 'selected' : ''; ?>>National</option>
                        <option value="International" <?php echo $selected_competition_level == 'International' ? 'selected' : ''; ?>>International</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit"><i class="fas fa-filter"></i> Apply Filter</button>
                    <a href="admin_sports_analytics.php" style="background: #6c757d; color: white; padding: 0.5rem 1rem; border-radius: 5px; text-decoration: none;">Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-user-graduate"></i>
                <div class="number"><?php echo number_format($overallStats['unique_students'] ?? 0); ?></div>
                <div class="label">Student Athletes</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="number"><?php echo number_format($overallStats['total_teams'] ?? 0); ?></div>
                <div class="label">Teams</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-chalkboard-user"></i>
                <div class="number"><?php echo number_format($overallStats['total_coaches'] ?? 0); ?></div>
                <div class="label">Coaches</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-trophy"></i>
                <div class="number"><?php echo number_format($overallStats['total_contests'] ?? 0); ?></div>
                <div class="label">Tournaments</div>
            </div>
            <div class="stat-card achievement-rate">
                <i class="fas fa-medal"></i>
                <div class="number"><?php echo $achievement_rate; ?>%</div>
                <div class="label">Achievement Rate</div>
            </div>
        </div>
        
        <!-- Medal Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-medal medal-gold"></i>
                <div class="number" style="color: #ffd700;"><?php echo number_format($overallStats['gold_medals'] ?? 0); ?></div>
                <div class="label">Gold Medals (1st/Champion)</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-medal medal-silver"></i>
                <div class="number" style="color: #c0c0c0;"><?php echo number_format($overallStats['silver_medals'] ?? 0); ?></div>
                <div class="label">Silver Medals (2nd/1st RU)</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-medal medal-bronze"></i>
                <div class="number" style="color: #cd7f32;"><?php echo number_format($overallStats['bronze_medals'] ?? 0); ?></div>
                <div class="label">Bronze Medals (3rd/2nd RU)</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-chart-line"></i>
                <div class="number"><?php echo number_format($overallStats['total_participations'] ?? 0); ?></div>
                <div class="label">Total Participations</div>
            </div>
        </div>
        
        <div class="chart-grid">
            <!-- Chart 1: Participation by Competition Level (Bar Chart) -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-bar"></i> Participation by Competition Level</h3>
                    <i class="fas fa-chart-simple"></i>
                </div>
                <div class="chart-body">
                    <canvas id="levelChart"></canvas>
                </div>
            </div>
            
            <!-- Chart 2: Medal Distribution (Pie Chart) -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie"></i> Medal Distribution</h3>
                    <i class="fas fa-medal"></i>
                </div>
                <div class="chart-body">
                    <canvas id="medalChart"></canvas>
                </div>
            </div>
            
            <!-- Chart 3: Participation by Category Type (Bar Chart) -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-bar"></i> Participation by Category Type</h3>
                    <i class="fas fa-category"></i>
                </div>
                <div class="chart-body">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            
            <!-- Chart 4: College Performance (Horizontal Bar Chart) -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-bar"></i> College Performance (Medals)</h3>
                    <i class="fas fa-building"></i>
                </div>
                <div class="chart-body">
                    <canvas id="collegePerformanceChart"></canvas>
                </div>
            </div>
            
            <!-- Chart 5: Yearly Trend (Line Chart) -->
            <div class="chart-card chart-full">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line"></i> Yearly Trend - Participation Over School Years</h3>
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="chart-body">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            
            <!-- Chart 6: Top Sports/Activities (Bar Chart) -->
            <div class="chart-card chart-full">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-bar"></i> Top Sports/Activities by Participation</h3>
                    <i class="fas fa-futbol"></i>
                </div>
                <div class="chart-body">
                    <canvas id="topSportsChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- College Performance Table -->
        <div class="data-table">
            <div class="table-header">
                <h3><i class="fas fa-building"></i> College Medal Tally</h3>
            </div>
            <table>
                <thead>
                    <tr><th>College</th><th>Gold</th><th>Silver</th><th>Bronze</th><th>Total Medals</th><th>Participations</th></tr>
                </thead>
                <tbody>
                    <?php if(count($collegePerformance) > 0): ?>
                        <?php foreach($collegePerformance as $college): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($college['college']); ?></strong></td>
                                <td><span class="medal-gold">🏆 <?php echo $college['gold_medals'] ?? 0; ?></span></td>
                                <td><span class="medal-silver">🥈 <?php echo $college['silver_medals'] ?? 0; ?></span></td>
                                <td><span class="medal-bronze">🥉 <?php echo $college['bronze_medals'] ?? 0; ?></span></td>
                                <td><?php echo ($college['gold_medals'] ?? 0) + ($college['silver_medals'] ?? 0) + ($college['bronze_medals'] ?? 0); ?></td>
                                <td><?php echo $college['total_participations'] ?? 0; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; color: #999;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Data from PHP
        const chartData = <?php echo $chartDataJson; ?>;
        
        // Helper function to get color arrays
        function getColors(count, startHue = 200) {
            const colors = [];
            for (let i = 0; i < count; i++) {
                const hue = (startHue + i * 30) % 360;
                colors.push(`hsla(${hue}, 70%, 50%, 0.8)`);
            }
            return colors;
        }
        
        // 1. Competition Level Bar Chart
        if (chartData.levelParticipation && chartData.levelParticipation.length > 0) {
            const levelCtx = document.getElementById('levelChart').getContext('2d');
            const levelLabels = chartData.levelParticipation.map(d => d.competition_level);
            const levelData = chartData.levelParticipation.map(d => d.total_participations);
            
            new Chart(levelCtx, {
                type: 'bar',
                data: {
                    labels: levelLabels,
                    datasets: [{
                        label: 'Number of Participations',
                        data: levelData,
                        backgroundColor: 'rgba(0, 51, 102, 0.7)',
                        borderColor: '#003366',
                        borderWidth: 1,
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.raw.toLocaleString()} participations` } }
                    },
                    scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Participations' } } }
                }
            });
        }
        
        // 2. Medal Distribution Pie Chart
        if (chartData.rankingDistribution && chartData.rankingDistribution.length > 0) {
            const medalCtx = document.getElementById('medalChart').getContext('2d');
            const medalLabels = chartData.rankingDistribution.map(d => d.medal_category);
            const medalData = chartData.rankingDistribution.map(d => d.count);
            const medalColors = ['#ffd700', '#c0c0c0', '#cd7f32', '#6c757d', '#17a2b8'];
            
            new Chart(medalCtx, {
                type: 'pie',
                data: {
                    labels: medalLabels,
                    datasets: [{ data: medalData, backgroundColor: medalColors.slice(0, medalLabels.length), borderWidth: 0 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'right' }, tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw} awards (${((ctx.raw / medalData.reduce((a,b)=>a+b,0))*100).toFixed(1)}%)` } } }
                }
            });
        }
        
        // 3. Category Type Bar Chart
        if (chartData.categoryParticipation && chartData.categoryParticipation.length > 0) {
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            const categoryLabels = chartData.categoryParticipation.map(d => d.category_type);
            const categoryData = chartData.categoryParticipation.map(d => d.total_participations);
            const categoryWithRankings = chartData.categoryParticipation.map(d => d.with_rankings);
            
            new Chart(categoryCtx, {
                type: 'bar',
                data: {
                    labels: categoryLabels,
                    datasets: [
                        { label: 'Total Participations', data: categoryData, backgroundColor: 'rgba(0, 51, 102, 0.7)', borderRadius: 5 },
                        { label: 'With Rankings/Awards', data: categoryWithRankings, backgroundColor: 'rgba(39, 174, 96, 0.7)', borderRadius: 5 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true, title: { display: true, text: 'Count' } } }
                }
            });
        }
        
        // 4. College Performance Horizontal Bar Chart
        if (chartData.collegePerformance && chartData.collegePerformance.length > 0) {
            const collegeCtx = document.getElementById('collegePerformanceChart').getContext('2d');
            const collegeLabels = chartData.collegePerformance.map(d => d.college);
            const goldData = chartData.collegePerformance.map(d => d.gold_medals || 0);
            const silverData = chartData.collegePerformance.map(d => d.silver_medals || 0);
            const bronzeData = chartData.collegePerformance.map(d => d.bronze_medals || 0);
            
            new Chart(collegeCtx, {
                type: 'bar',
                data: {
                    labels: collegeLabels,
                    datasets: [
                        { label: 'Gold (1st/Champion)', data: goldData, backgroundColor: '#ffd700', borderRadius: 5 },
                        { label: 'Silver (2nd/1st RU)', data: silverData, backgroundColor: '#c0c0c0', borderRadius: 5 },
                        { label: 'Bronze (3rd/2nd RU)', data: bronzeData, backgroundColor: '#cd7f32', borderRadius: 5 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Medals' } } }
                }
            });
        }
        
        // 5. Yearly Trend Line Chart
        if (chartData.yearlyTrend && chartData.yearlyTrend.length > 0) {
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            const sortedTrend = [...chartData.yearlyTrend].sort((a,b) => a.school_year.localeCompare(b.school_year));
            const trendLabels = sortedTrend.map(d => d.school_year);
            const participationsData = sortedTrend.map(d => d.total_participations);
            const rankingsData = sortedTrend.map(d => d.with_rankings);
            const studentsData = sortedTrend.map(d => d.unique_students);
            const contestsData = sortedTrend.map(d => d.total_contests);
            
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [
                        { label: 'Total Participations', data: participationsData, borderColor: '#003366', backgroundColor: 'rgba(0, 51, 102, 0.1)', tension: 0.4, fill: true },
                        { label: 'With Rankings/Awards', data: rankingsData, borderColor: '#27ae60', backgroundColor: 'rgba(39, 174, 96, 0.1)', tension: 0.4, fill: true },
                        { label: 'Unique Students', data: studentsData, borderColor: '#ffd700', backgroundColor: 'rgba(255, 215, 0, 0.1)', tension: 0.4, fill: true }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true, title: { display: true, text: 'Count' } } }
                }
            });
        }
        
        // 6. Top Sports/Activities Bar Chart
        if (chartData.topCategories && chartData.topCategories.length > 0) {
            const topSportsCtx = document.getElementById('topSportsChart').getContext('2d');
            const topLabels = chartData.topCategories.map(d => d.category_name);
            const topData = chartData.topCategories.map(d => d.total_participations);
            const topMedals = chartData.topCategories.map(d => d.medals_won);
            
            new Chart(topSportsCtx, {
                type: 'bar',
                data: {
                    labels: topLabels,
                    datasets: [
                        { label: 'Total Participations', data: topData, backgroundColor: 'rgba(0, 51, 102, 0.7)', borderRadius: 5 },
                        { label: 'Awards/Rankings Won', data: topMedals, backgroundColor: 'rgba(39, 174, 96, 0.7)', borderRadius: 5 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    indexAxis: 'y',
                    plugins: { legend: { position: 'top' } },
                    scales: { x: { beginAtZero: true, title: { display: true, text: 'Count' } } }
                }
            });
        }
    </script>
</body>
</html>