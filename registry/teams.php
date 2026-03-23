<?php
// teams.php - Team Management Page
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

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Team Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Teams Grid */
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .team-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid #eef2f6;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .team-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        .team-logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #f59e0b;
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.2);
        }

        .team-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .team-logo-placeholder {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f59e0b20, #d9770620);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: #f59e0b;
            border: 3px dashed #f59e0b;
        }

        .team-name {
            font-size: 20px;
            font-weight: 700;
            color: #1a2639;
            text-align: center;
            margin-bottom: 5px;
        }

        .team-sport {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .team-sport i {
            color: #f59e0b;
            font-size: 12px;
        }

        .team-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            padding: 15px 0;
            border-top: 1px solid #eef2f6;
            border-bottom: 1px solid #eef2f6;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1a2639;
        }

        .stat-label {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }

        .stat-value.wins {
            color: #10b981;
        }

        .stat-value.losses {
            color: #ef4444;
        }

        .team-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }

        .team-players {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #64748b;
            font-size: 13px;
        }

        .team-players i {
            color: #f59e0b;
        }

        .team-actions {
            display: flex;
            gap: 10px;
        }

        .team-action-btn {
            color: #94a3b8;
            transition: color 0.2s;
            text-decoration: none;
        }

        .team-action-btn:hover {
            color: #f59e0b;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            color: #e2e8f0;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 10px;
        }

        .empty-state small {
            font-size: 13px;
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
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
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
            .teams-grid {
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
            
            .teams-grid {
                grid-template-columns: 1fr;
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
                    <a href="teams.php" class="nav-item active">
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
                    <h1>Team Management</h1>
                    <p>Manage your teams and track their performance</p>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search teams..." id="searchInput">
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

            <!-- Teams Section -->
            <div class="page-header">
                <h2><i class="fas fa-trophy"></i> My Teams (<?php echo count($teams); ?>)</h2>
                <button class="add-btn" onclick="openAddTeamModal()">
                    <i class="fas fa-plus-circle"></i> Add New Team
                </button>
            </div>

            <div class="teams-grid" id="teamsGrid">
                <?php if(!empty($teams)): ?>
                    <?php foreach($teams as $team): ?>
                    <div class="team-card">
                        <div class="team-logo-placeholder">
                            <?php 
                            $initial = strtoupper(substr($team['team_name'] ?? 'T', 0, 1));
                            echo $initial;
                            ?>
                        </div>
                        <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                        <div class="team-sport">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($team['sport_name']); ?>
                        </div>
                        
                        <div class="team-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $team['player_count'] ?? 0; ?></div>
                                <div class="stat-label">Players</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value wins"><?php echo rand(5, 15); ?></div>
                                <div class="stat-label">Wins</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value losses"><?php echo rand(2, 8); ?></div>
                                <div class="stat-label">Losses</div>
                            </div>
                        </div>

                        <div class="team-footer">
                            <div class="team-players">
                                <i class="fas fa-users"></i>
                                <span><?php echo $team['player_count'] ?? 0; ?> active players</span>
                            </div>
                            <div class="team-actions">
                                <a href="#" class="team-action-btn" onclick="viewTeam(<?php echo $team['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="#" class="team-action-btn" onclick="editTeam(<?php echo $team['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" class="team-action-btn" onclick="deleteTeam(<?php echo $team['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-trophy"></i>
                        <p>No teams found</p>
                        <small>Click "Add New Team" to create your first team</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Team Modal -->
    <div id="addTeamModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-plus-circle"></i> Add New Team</h2>
            <form method="POST" action="add_team.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Team Name</label>
                    <input type="text" name="team_name" required placeholder="e.g., Varsity Basketball">
                </div>
                <div class="form-group">
                    <label>Sport</label>
                    <select name="sport_id" required>
                        <option value="">-- Select Sport --</option>
                        <?php 
                        try {
                            $sports = $pdo->query("SELECT id, sport_name FROM sports")->fetchAll();
                            foreach($sports as $sport): 
                        ?>
                        <option value="<?php echo $sport['id']; ?>"><?php echo htmlspecialchars($sport['sport_name']); ?></option>
                        <?php 
                            endforeach;
                        } catch (Exception $e) {
                            // Default options if sports table doesn't exist
                        ?>
                        <option value="1">Basketball</option>
                        <option value="2">Volleyball</option>
                        <option value="3">Football</option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Team Logo</label>
                    <input type="file" name="logo" accept="image/*">
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-submit">Create Team</button>
                    <button type="button" class="btn-cancel" onclick="closeAddTeamModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Modal functions
    function openAddTeamModal() {
        document.getElementById('addTeamModal').classList.add('active');
    }

    function closeAddTeamModal() {
        document.getElementById('addTeamModal').classList.remove('active');
    }

    // Search functionality
    document.getElementById('searchInput')?.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const cards = document.querySelectorAll('.team-card');
        
        cards.forEach(card => {
            const teamName = card.querySelector('.team-name')?.textContent.toLowerCase() || '';
            const teamSport = card.querySelector('.team-sport')?.textContent.toLowerCase() || '';
            
            if(teamName.includes(searchTerm) || teamSport.includes(searchTerm)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // Team action functions
    function viewTeam(id) {
        alert('View team details: ' + id);
        // Implement view functionality
    }

    function editTeam(id) {
        alert('Edit team: ' + id);
        // Implement edit functionality
    }

    function deleteTeam(id) {
        if(confirm('Are you sure you want to delete this team?')) {
            alert('Delete team: ' + id);
            // Implement delete functionality
        }
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('addTeamModal');
        if (event.target == modal) {
            modal.classList.remove('active');
        }
    }

    // Keyboard shortcut for search (press '/' to focus search)
    document.addEventListener('keydown', function(e) {
        if(e.key === '/' && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            document.getElementById('searchInput')?.focus();
        }
        
        if (e.key === 'Escape') {
            document.getElementById('addTeamModal')?.classList.remove('active');
        }
    });
    </script>
</body>
</html>