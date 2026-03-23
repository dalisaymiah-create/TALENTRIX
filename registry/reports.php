<?php
// reports.php - Reports Dashboard
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

// Get all players
$players = [];
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM students s
        JOIN users u ON s.user_id = u.id
    ");
    $playerTotal = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $playerTotal = 0;
}

// Calculate totals
$teamTotal = count($teams);
$matchTotal = 0;
$winTotal = 0;

foreach($teams as $team) {
    $matchTotal += $team['matches'] ?? 0;
    $winTotal += $team['wins'] ?? 0;
}

$lossTotal = $matchTotal - $winTotal;

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Reports Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .date-range {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 8px 15px;
            border-radius: 30px;
            border: 1px solid #e2e8f0;
        }

        .date-range i {
            color: #f59e0b;
        }

        .date-range span {
            font-size: 14px;
            color: #475569;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
        }

        .notification-icon i {
            font-size: 22px;
            color: #475569;
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
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f59e0b, #d97706);
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
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-trend.down {
            color: #ef4444;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid #eef2f6;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eef2f6;
        }

        .chart-header h3 {
            font-size: 16px;
            color: #1a2639;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-header h3 i {
            color: #f59e0b;
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid #eef2f6;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eef2f6;
        }

        .table-header h3 {
            font-size: 16px;
            color: #1a2639;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-header h3 i {
            color: #f59e0b;
        }

        .export-btn {
            background: #f8fafc;
            color: #475569;
            padding: 8px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .export-btn:hover {
            background: #f59e0b;
            color: white;
            border-color: #f59e0b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px 10px;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #eef2f6;
        }

        td {
            padding: 15px 10px;
            border-bottom: 1px solid #f1f5f9;
            color: #1a2639;
            font-size: 14px;
        }

        .team-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .team-avatar {
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

        .team-name {
            font-weight: 600;
            color: #1a2639;
        }

        .team-sport {
            font-size: 12px;
            color: #64748b;
        }

        .win-badge {
            background: #dcfce7;
            color: #166534;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .loss-badge {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .winrate-bar {
            width: 100px;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
        }

        .winrate-fill {
            height: 100%;
            background: #f59e0b;
            border-radius: 3px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .charts-grid {
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
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
                    <div class="nav-section-title">MAIN</div>
                    <a href="coach_dashboard.php" class="nav-item">
                        <i>🏠</i>
                        <span>Dashboard</span>
                    </a>
                    <a href="schedule.php" class="nav-item">
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
                    <a href="reports.php" class="nav-item active">
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
                    <h1>Reports Dashboard</h1>
                    <p>Analytics and performance metrics</p>
                </div>
                <div class="header-actions">
                    <div class="date-range">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?php echo date('F j, Y'); ?></span>
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

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Players</h3>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $playerTotal; ?></div>
                    <div class="stat-label">Active roster</div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i> 12% from last month
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Teams</h3>
                        <div class="stat-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $teamTotal; ?></div>
                    <div class="stat-label">Active teams</div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i> 2 new this season
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Matches</h3>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $matchTotal; ?></div>
                    <div class="stat-label">Games played</div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i> 8 upcoming
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Win Rate</h3>
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $matchTotal > 0 ? round(($winTotal / $matchTotal) * 100) : 0; ?>%</div>
                    <div class="stat-label">Overall performance</div>
                    <div class="stat-trend <?php echo ($winTotal / max($matchTotal, 1)) > 0.5 ? '' : 'down'; ?>">
                        <i class="fas fa-arrow-<?php echo ($winTotal / max($matchTotal, 1)) > 0.5 ? 'up' : 'down'; ?>"></i> 
                        <?php echo $winTotal; ?> wins / <?php echo $lossTotal; ?> losses
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <!-- Performance Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-bar"></i> Team Performance</h3>
                        <select style="padding: 5px 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12px;">
                            <option>Last 30 days</option>
                            <option>Last 3 months</option>
                            <option>Last 6 months</option>
                            <option>This season</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>

                <!-- Win/Loss Ratio -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-pie-chart"></i> Win/Loss Ratio</h3>
                        <span class="badge" style="background: #f59e0b; padding: 5px 10px; border-radius: 20px; color: white;"><?php echo $teamTotal; ?> teams</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="winLossChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Team Statistics Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-table"></i> Team Statistics</h3>
                    <button class="export-btn" onclick="exportToCSV()">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="teamStatsTable">
                        <thead>
                            <tr>
                                <th>Team</th>
                                <th>Sport</th>
                                <th>Players</th>
                                <th>Matches</th>
                                <th>Wins</th>
                                <th>Losses</th>
                                <th>Win Rate</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($teams)): ?>
                                <?php foreach($teams as $team): 
                                    $matches = $team['matches'] ?? 0;
                                    $wins = $team['wins'] ?? 0;
                                    $losses = $matches - $wins;
                                    $winRate = $matches > 0 ? round(($wins / $matches) * 100) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <div class="team-info">
                                            <div class="team-avatar">
                                                <?php echo strtoupper(substr($team['team_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($team['sport_name']); ?></td>
                                    <td><?php echo $team['player_count'] ?? 0; ?></td>
                                    <td><?php echo $matches; ?></td>
                                    <td><span class="win-badge"><?php echo $wins; ?></span></td>
                                    <td><span class="loss-badge"><?php echo $losses; ?></span></td>
                                    <td><?php echo $winRate; ?>%</td>
                                    <td>
                                        <div class="winrate-bar">
                                            <div class="winrate-fill" style="width: <?php echo $winRate; ?>%;"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-chart-line" style="font-size: 40px; color: #e2e8f0; margin-bottom: 10px;"></i>
                                        <p>No team statistics available</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Performance Chart
    const performanceCtx = document.getElementById('performanceChart').getContext('2d');
    new Chart(performanceCtx, {
        type: 'line',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6'],
            datasets: [{
                label: 'Performance Score',
                data: [65, 72, 78, 75, 85, 82],
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });

    // Win/Loss Chart
    const winLossCtx = document.getElementById('winLossChart').getContext('2d');
    new Chart(winLossCtx, {
        type: 'doughnut',
        data: {
            labels: ['Wins', 'Losses'],
            datasets: [{
                data: [<?php echo $winTotal; ?>, <?php echo $lossTotal; ?>],
                backgroundColor: ['#10b981', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            cutout: '70%'
        }
    });

    // Export to CSV function
    function exportToCSV() {
        const table = document.getElementById('teamStatsTable');
        const rows = table.querySelectorAll('tr');
        let csv = [];
        
        rows.forEach(row => {
            const cols = row.querySelectorAll('th, td');
            const rowData = [];
            cols.forEach(col => {
                // Clean up the data (remove HTML, get text only)
                let text = col.textContent.trim();
                // Handle the performance bar specially
                if (col.querySelector('.winrate-bar')) {
                    const winRate = col.querySelector('.winrate-fill')?.style.width || '0%';
                    text = winRate;
                }
                rowData.push('"' + text.replace(/"/g, '""') + '"');
            });
            csv.push(rowData.join(','));
        });
        
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'team_statistics_<?php echo date('Y-m-d'); ?>.csv';
        a.click();
        window.URL.revokeObjectURL(url);
    }

    // Keyboard shortcut for export (Ctrl+E)
    document.addEventListener('keydown', function(e) {
        if(e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            exportToCSV();
        }
    });
    </script>
</body>
</html>