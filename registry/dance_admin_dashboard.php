<?php
// dance_admin_dashboard.php - Simplified Dance Admin Dashboard
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user is dance_admin
if ($_SESSION['user_type'] !== 'dance_admin') {
    // Redirect to correct dashboard based on user type
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin_pages.php?page=dashboard');
        exit();
    } elseif ($_SESSION['user_type'] === 'athletics_admin') {
        header('Location: athletics_admin_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'sport_coach') {
        header('Location: coach_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'dance_coach') {
        header('Location: dance_trainer_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'student') {
        header('Location: student_dashboard.php');
        exit();
    } else {
        header('Location: index.php');
        exit();
    }
}

// Get current user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

// Get dance statistics
$total_dancers = $pdo->query("SELECT COUNT(*) FROM students WHERE student_type = 'dancer' OR student_type = 'both'")->fetchColumn();
$total_dance_coaches = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'dance_coach'")->fetchColumn();
$total_troupes = $pdo->query("SELECT COUNT(*) FROM dance_troupes")->fetchColumn();
$total_pending = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending' AND (user_type = 'dance_coach' OR user_type = 'student')")->fetchColumn();

// Get recent users (dancers and trainers)
$recent_users = $pdo->query("
    (SELECT u.id, u.id_number, u.first_name, u.last_name, u.email, 'Trainer' as type, u.created_at 
     FROM users u 
     WHERE u.user_type = 'dance_coach' 
     ORDER BY u.created_at DESC LIMIT 5)
    UNION ALL
    (SELECT u.id, u.id_number, u.first_name, u.last_name, u.email, 'Dancer' as type, u.created_at 
     FROM users u 
     JOIN students s ON u.id = s.user_id 
     WHERE s.student_type IN ('dancer', 'both') 
     ORDER BY u.created_at DESC LIMIT 5)
    ORDER BY created_at DESC LIMIT 10
")->fetchAll();

// Get greeting
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good morning";
} elseif ($hour < 18) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Dance Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: #f5f7fb;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #e9ecef;
            position: fixed;
            height: 100vh;
            padding: 30px 0;
        }

        .sidebar-header {
            padding: 0 25px 30px 25px;
            border-bottom: 1px solid #e9ecef;
        }

        .sidebar-header h2 {
            font-size: 24px;
            color: #FFB347;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .sidebar-header h2 i {
            color: #FFB347;
            margin-right: 10px;
        }

        .admin-info {
            font-size: 14px;
            color: #495057;
            margin-bottom: 5px;
        }

        .admin-email {
            font-size: 12px;
            color: #6c757d;
        }

        .admin-badge {
            background: #FFB347;
            color: #0a2540;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            margin-top: 5px;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .sidebar-nav ul {
            list-style: none;
        }

        .sidebar-nav li {
            margin: 5px 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #495057;
            text-decoration: none;
            font-size: 15px;
            transition: all 0.2s;
        }

        .sidebar-nav a i {
            margin-right: 15px;
            width: 20px;
            color: #6c757d;
            font-size: 16px;
        }

        .sidebar-nav a:hover {
            background: #f8f9fa;
            color: #FFB347;
        }

        .sidebar-nav a:hover i {
            color: #FFB347;
        }

        .sidebar-nav li.active a {
            background: #f8f9fa;
            color: #FFB347;
            border-right: 3px solid #FFB347;
        }

        .sidebar-nav li.active a i {
            color: #FFB347;
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
            color: #0a2540;
            font-weight: 600;
        }

        .header p {
            color: #6c757d;
            margin-top: 5px;
            font-size: 15px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .date-badge {
            background: white;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            color: #495057;
            font-size: 14px;
        }

        .date-badge i {
            margin-right: 8px;
            color: #FFB347;
        }

        .btn-add {
            background: #FFB347;
            color: #0a2540;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-add:hover {
            background: #f39c12;
        }

        /* Stats Row - Two Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #e9ecef;
            border-left: 5px solid #FFB347;
        }

        .stat-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .stat-header i {
            font-size: 20px;
            color: #FFB347;
        }

        .stat-header h3 {
            font-size: 14px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 42px;
            font-weight: 700;
            color: #0a2540;
            margin-bottom: 8px;
        }

        .stat-percent {
            color: #6c757d;
            font-size: 14px;
        }

        /* Recent Users Card */
        .recent-users-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
        }

        .card-header h2 {
            font-size: 18px;
            color: #0a2540;
            font-weight: 600;
        }

        .view-all {
            color: #FFB347;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px 25px;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px 25px;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
            font-size: 14px;
        }

        .badge-dancer {
            background: #FFB347;
            color: #0a2540;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-trainer {
            background: #8B1E3F;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Distribution Card */
        .distribution-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            padding: 25px;
            margin-bottom: 30px;
        }

        .distribution-card h2 {
            font-size: 18px;
            color: #0a2540;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .distribution-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 10px 0;
        }

        .distribution-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #495057;
            font-weight: 500;
        }

        .distribution-label i {
            width: 20px;
            color: #FFB347;
        }

        .distribution-value {
            font-weight: 600;
            color: #0a2540;
        }

        .distribution-percent {
            color: #6c757d;
            font-size: 13px;
            margin-left: 10px;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin: 5px 0 15px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #FFB347;
            border-radius: 4px;
        }

        /* Bottom Stats */
        .stats-bottom {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-top: 20px;
        }

        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-left: 3px solid #FFB347;
        }

        .stat-box-label {
            color: #6c757d;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-box-value {
            font-size: 28px;
            font-weight: 700;
            color: #0a2540;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-bottom {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h2 span,
            .admin-info,
            .admin-email,
            .admin-badge,
            .sidebar-nav a span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .stats-bottom {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-star"></i>DANCE HUB</h2>
                <div class="admin-info">Welcome, <?php echo htmlspecialchars($current_user['first_name'] ?? 'System'); ?></div>
                <div class="admin-email"><?php echo htmlspecialchars($current_user['email'] ?? 'dance@talentrix.edu'); ?></div>
                <span class="admin-badge">Dance Admin</span>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="active"><a href="dance_admin_dashboard.php"><i class="fas fa-chart-bar"></i><span>Dashboard</span></a></li>
                    <li><a href="#"><i class="fas fa-music"></i><span>Manage Dancers</span></a></li>
                    <li><a href="#"><i class="fas fa-user-tie"></i><span>Manage Trainers</span></a></li>
                    <li><a href="#"><i class="fas fa-users"></i><span>Manage Troupes</span></a></li>
                    <li><a href="performances.php"><i class="fas fa-calendar"></i><span>Performances</span></a></li>
                    <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h1>DASHBOARD</h1>
                    <p><?php echo $greeting; ?>, <?php echo htmlspecialchars($current_user['first_name'] ?? 'System'); ?>! Here's your dance overview.</p>
                </div>
                <div class="header-right">
                    <div class="date-badge"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y'); ?></div>
                    <a href="#" class="btn-add"><i class="fas fa-plus"></i> Add new</a>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-header"><i class="fas fa-music"></i><h3>DANCERS</h3></div>
                    <div class="stat-number"><?php echo $total_dancers; ?></div>
                    <div class="stat-percent"><?php echo $total_dancers + $total_dance_coaches > 0 ? round(($total_dancers / ($total_dancers + $total_dance_coaches)) * 100, 1) : 0; ?>% OF TOTAL</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><i class="fas fa-user-tie"></i><h3>TRAINERS</h3></div>
                    <div class="stat-number"><?php echo $total_dance_coaches; ?></div>
                    <div class="stat-percent"><?php echo $total_dancers + $total_dance_coaches > 0 ? round(($total_dance_coaches / ($total_dancers + $total_dance_coaches)) * 100, 1) : 0; ?>% OF TOTAL</div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="recent-users-card">
                <div class="card-header">
                    <h2>RECENT DANCE USERS</h2>
                    <a href="#" class="view-all">View All</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID NUMBER</th>
                            <th>NAME</th>
                            <th>EMAIL</th>
                            <th>TYPE</th>
                            <th>DATE JOINED</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($recent_users)): ?>
                            <?php foreach($recent_users as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['id_number'] ?? 'N/A'); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="badge-<?php echo strtolower($user['type']); ?>"><?php echo $user['type']; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td>DAN-001</td>
                                <td>Maria Dancer</td>
                                <td>maria@dance.edu</td>
                                <td><span class="badge-dancer">Dancer</span></td>
                                <td><?php echo date('M d, Y'); ?></td>
                            </tr>
                            <tr>
                                <td>TRA-001</td>
                                <td>Trainer Santos</td>
                                <td>trainer@dance.edu</td>
                                <td><span class="badge-trainer">Trainer</span></td>
                                <td><?php echo date('M d, Y', strtotime('-3 days')); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- User Distribution -->
            <div class="distribution-card">
                <h2>USER DISTRIBUTION</h2>
                
                <div class="distribution-item">
                    <span class="distribution-label"><i class="fas fa-music"></i> DANCERS</span>
                    <span><span class="distribution-value"><?php echo $total_dancers; ?></span> <span class="distribution-percent">(<?php echo $total_dancers + $total_dance_coaches > 0 ? round(($total_dancers / ($total_dancers + $total_dance_coaches)) * 100, 1) : 0; ?>%)</span></span>
                </div>
                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $total_dancers + $total_dance_coaches > 0 ? round(($total_dancers / ($total_dancers + $total_dance_coaches)) * 100, 1) : 0; ?>%;"></div></div>
                
                <div class="distribution-item">
                    <span class="distribution-label"><i class="fas fa-user-tie"></i> TRAINERS</span>
                    <span><span class="distribution-value"><?php echo $total_dance_coaches; ?></span> <span class="distribution-percent">(<?php echo $total_dancers + $total_dance_coaches > 0 ? round(($total_dance_coaches / ($total_dancers + $total_dance_coaches)) * 100, 1) : 0; ?>%)</span></span>
                </div>
                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $total_dancers + $total_dance_coaches > 0 ? round(($total_dance_coaches / ($total_dancers + $total_dance_coaches)) * 100, 1) : 0; ?>%; background: #8B1E3F;"></div></div>
            </div>
</body>
</html>