<?php
// dance_dashboard.php - Dance Admin Dashboard with Buttons & Tables
session_start();
require_once 'db.php';

// IMPORTANT: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user is dance_admin
if ($_SESSION['user_type'] !== 'dance_admin') {
    if ($_SESSION['user_type'] === 'athletics_admin') {
        header('Location: athletics_dashboard.php');
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
        header('Location: login.php');
        exit();
    }
}

// Get current user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

// Get dance statistics
$stats = [];

// Dancer counts
$stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE student_type = 'dancer' OR student_type = 'both'");
$stats['total_dancers'] = $stmt->fetchColumn();

// Dance coaches count
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'dance_coach'");
$stats['total_dance_coaches'] = $stmt->fetchColumn();

// Dance troupes count
$stmt = $pdo->query("SELECT COUNT(*) FROM dance_troupes WHERE is_active = 1");
$stats['total_troupes'] = $stmt->fetchColumn();

// Pending approvals for dancers
$stmt = $pdo->query("
    SELECT COUNT(*) FROM approvals a
    JOIN students s ON a.student_id = s.id
    WHERE a.status = 'pending' AND s.student_type IN ('dancer', 'both')
");
$stats['pending_approvals'] = $stmt->fetchColumn();

// Get dancers table data
$dancers = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.id_number, u.status,
               s.dance_troupe, s.dance_role,
               u.created_at
        FROM users u
        JOIN students s ON u.id = s.user_id
        WHERE s.student_type IN ('dancer', 'both')
        ORDER BY u.created_at DESC
        LIMIT 10
    ");
    $dancers = $stmt->fetchAll();
} catch (Exception $e) {
    $dancers = [];
}

// Get dance trainers table data
$trainers = [];
try {
    $stmt = $pdo->query("
        SELECT dc.*, u.first_name, u.last_name, u.email, u.id_number, u.status,
               (SELECT COUNT(*) FROM dance_troupes WHERE coach_id = dc.user_id) as troupe_count
        FROM dance_coaches dc
        JOIN users u ON dc.user_id = u.id
        WHERE u.status = 'active'
        ORDER BY u.first_name
        LIMIT 10
    ");
    $trainers = $stmt->fetchAll();
} catch (Exception $e) {
    $trainers = [];
}

// Get troupes table data
$troupes = [];
try {
    $stmt = $pdo->query("
        SELECT dt.*,
               CONCAT(u.first_name, ' ', u.last_name) as trainer_name,
               (SELECT COUNT(*) FROM dance_troupe_members WHERE troupe_id = dt.id AND status = 'active') as member_count
        FROM dance_troupes dt
        LEFT JOIN dance_coaches dc ON dt.coach_id = dc.user_id
        LEFT JOIN users u ON dc.user_id = u.id
        WHERE dt.is_active = 1
        ORDER BY dt.troupe_name
        LIMIT 10
    ");
    $troupes = $stmt->fetchAll();
} catch (Exception $e) {
    $troupes = [];
}

// Get pending approvals table data for dancers
$pending_approvals = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.id as approval_id,
            s.id as student_id,
            u.id_number,
            u.first_name,
            u.last_name,
            u.email,
            s.dance_troupe,
            s.dance_role,
            a.request_date,
            dt.troupe_name,
            CONCAT(cu.first_name, ' ', cu.last_name) as trainer_name
        FROM approvals a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        LEFT JOIN dance_troupes dt ON a.troupe_id = dt.id
        LEFT JOIN dance_coaches dc ON a.coach_id = dc.user_id
        LEFT JOIN users cu ON dc.user_id = cu.id
        WHERE a.status = 'pending' AND s.student_type IN ('dancer', 'both')
        ORDER BY a.request_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $pending_approvals = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_approvals = [];
}

// Get upcoming performances
$upcoming_performances = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM upcoming_events 
        WHERE event_date >= CURDATE() AND event_type IN ('dance', 'both')
        ORDER BY event_date ASC
        LIMIT 5
    ");
    $upcoming_performances = $stmt->fetchAll();
} catch (Exception $e) {
    $upcoming_performances = [];
}

// Get greeting based on time
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
    <title>TALENTRIX - Dance Admin Dashboard</title>
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

        /* Simple Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid #e2e8f0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .logo {
            font-size: 22px;
            font-weight: 700;
            color: #FFB347;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .logo i {
            color: #FFB347;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-avatar {
            width: 45px;
            height: 45px;
            background: #FFB347;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0a2540;
            font-weight: 600;
            font-size: 16px;
        }

        .admin-details h4 {
            font-size: 14px;
            color: #0a2540;
            margin-bottom: 3px;
        }

        .admin-details p {
            font-size: 12px;
            color: #64748b;
        }

        .admin-badge {
            background: #fef9e7;
            color: #FFB347;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            margin-top: 3px;
            display: inline-block;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #475569;
            text-decoration: none;
            margin: 2px 10px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .nav-item:hover {
            background: #fef9e7;
            color: #FFB347;
        }

        .nav-item.active {
            background: #FFB347;
            color: #0a2540;
        }

        .nav-item i {
            margin-right: 12px;
            font-size: 16px;
            width: 20px;
        }

        .nav-item span {
            font-size: 14px;
        }

        .nav-item .badge {
            margin-left: auto;
            background: #ef4444;
            color: white;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 25px;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        .page-title h1 {
            font-size: 24px;
            color: #0a2540;
        }

        .page-title p {
            color: #64748b;
            font-size: 14px;
            margin-top: 3px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 10px 15px 10px 40px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            width: 250px;
            font-size: 14px;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
        }

        .notification-icon i {
            font-size: 20px;
            color: #475569;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-info h3 {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .stat-info .value {
            font-size: 28px;
            font-weight: 700;
            color: #0a2540;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: #fef9e7;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFB347;
            font-size: 22px;
        }

        /* Action Buttons Bar */
        .action-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #FFB347;
            color: #0a2540;
        }

        .btn-primary:hover {
            background: #f39c12;
        }

        .btn-secondary {
            background: white;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #FFB347;
            color: #FFB347;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        /* Section Cards */
        .section-card {
            background: white;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
            overflow: hidden;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .section-header h2 {
            font-size: 16px;
            font-weight: 600;
            color: #0a2540;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-header h2 i {
            color: #FFB347;
        }

        .section-actions {
            display: flex;
            gap: 10px;
        }

        .section-actions .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #475569;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .section-actions .btn-small:hover {
            background: #FFB347;
            color: #0a2540;
            border-color: #FFB347;
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px 20px;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            color: #0a2540;
        }

        tr:hover {
            background: #f8fafc;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            background: #FFB347;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0a2540;
            font-weight: 600;
            font-size: 14px;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .table-actions {
            display: flex;
            gap: 8px;
        }

        .table-btn {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-edit {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-view {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn-approve {
            background: #10b981;
            color: white;
        }

        .btn-reject {
            background: #ef4444;
            color: white;
        }

        /* Events List */
        .events-list {
            padding: 15px;
        }

        .event-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .event-item:last-child {
            border-bottom: none;
        }

        .event-date {
            min-width: 60px;
            text-align: center;
            background: #fef9e7;
            padding: 8px 5px;
            border-radius: 8px;
        }

        .event-date .day {
            font-size: 18px;
            font-weight: 700;
            color: #FFB347;
            line-height: 1.2;
        }

        .event-date .month {
            font-size: 11px;
            color: #64748b;
        }

        .event-details {
            flex: 1;
        }

        .event-details h4 {
            font-size: 15px;
            color: #0a2540;
            margin-bottom: 4px;
        }

        .event-details p {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .event-badge {
            background: #FFB347;
            color: #0a2540;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .logo span,
            .admin-details,
            .nav-item span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .search-box input {
                width: 200px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-bar {
                flex-direction: column;
            }
            
            .table-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Simple Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-star"></i>
                    <span>DANCE HUB</span>
                </div>
                <div class="admin-info">
                    <div class="admin-avatar">
                        <?php echo strtoupper(substr($current_user['first_name'] ?? 'D', 0, 1)); ?>
                    </div>
                    <div class="admin-details">
                        <h4><?php echo htmlspecialchars($current_user['first_name'] ?? 'Dance') . ' ' . htmlspecialchars($current_user['last_name'] ?? 'Admin'); ?></h4>
                        <p><?php echo htmlspecialchars($current_user['email'] ?? 'admin@dancehub.edu'); ?></p>
                        <span class="admin-badge">Dance Admin</span>
                    </div>
                </div>
            </div>

            <div class="sidebar-nav">
                <a href="#" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-music"></i>
                    <span>Dancers</span>
                    <span class="badge"><?php echo $stats['total_dancers']; ?></span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-user-tie"></i>
                    <span>Trainers</span>
                    <span class="badge"><?php echo $stats['total_dance_coaches']; ?></span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Troupes</span>
                    <span class="badge"><?php echo $stats['total_troupes']; ?></span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-clock"></i>
                    <span>Approvals</span>
                    <span class="badge" style="background: #f59e0b;"><?php echo $stats['pending_approvals']; ?></span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-calendar"></i>
                    <span>Performances</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-trophy"></i>
                    <span>Achievements</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h1>Dashboard</h1>
                    <p><?php echo $greeting; ?>, <?php echo htmlspecialchars($current_user['first_name'] ?? 'Admin'); ?>! Here's your dance overview.</p>
                </div>
                <div class="top-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search dancers, troupes...">
                    </div>
                    <div class="notification-icon">
                        <i class="far fa-bell"></i>
                        <span class="notification-badge"><?php echo $stats['pending_approvals']; ?></span>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total Dancers</h3>
                        <div class="value"><?php echo number_format($stats['total_dancers']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-music"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Dance Trainers</h3>
                        <div class="value"><?php echo $stats['total_dance_coaches']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Dance Troupes</h3>
                        <div class="value"><?php echo $stats['total_troupes']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Pending Approvals</h3>
                        <div class="value"><?php echo $stats['pending_approvals']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <!-- Action Buttons Bar -->
            <div class="action-bar">
                <a href="#" class="action-btn btn-primary"><i class="fas fa-user-plus"></i> Add Dancer</a>
                <a href="#" class="action-btn btn-primary"><i class="fas fa-user-tie"></i> Add Trainer</a>
                <a href="#" class="action-btn btn-primary"><i class="fas fa-users"></i> Create Troupe</a>
                <a href="#" class="action-btn btn-secondary"><i class="fas fa-calendar-plus"></i> Schedule Show</a>
                <a href="#" class="action-btn btn-secondary"><i class="fas fa-trophy"></i> Add Achievement</a>
                <a href="#" class="action-btn btn-secondary"><i class="fas fa-file-export"></i> Export Report</a>
            </div>

            <!-- Pending Approvals Table -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Pending Approvals</h2>
                    <div class="section-actions">
                        <a href="#" class="btn-small"><i class="fas fa-eye"></i> View All</a>
                        <a href="#" class="btn-small"><i class="fas fa-check-double"></i> Bulk Approve</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Dancer</th>
                                <th>ID Number</th>
                                <th>Troupe</th>
                                <th>Role</th>
                                <th>Trainer</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($pending_approvals)): ?>
                                <?php foreach($pending_approvals as $approval): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($approval['first_name'], 0, 1) . substr($approval['last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']); ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($approval['id_number']); ?></td>
                                    <td><?php echo htmlspecialchars($approval['troupe_name'] ?? $approval['dance_troupe'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($approval['dance_role'] ?? 'Dancer'); ?></td>
                                    <td><?php echo htmlspecialchars($approval['trainer_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($approval['request_date'])); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="approve.php?id=<?php echo $approval['approval_id']; ?>" class="table-btn btn-approve">✓ Approve</a>
                                            <a href="reject.php?id=<?php echo $approval['approval_id']; ?>" class="table-btn btn-reject">✗ Reject</a>
                                            <a href="view.php?id=<?php echo $approval['student_id']; ?>" class="table-btn btn-view">👁️ View</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px; color: #64748b;">
                                        <i class="fas fa-check-circle" style="font-size: 24px; color: #10b981; margin-bottom: 10px;"></i>
                                        <p>No pending approvals</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Dancers Table -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-music"></i> Recent Dancers</h2>
                    <div class="section-actions">
                        <a href="#" class="btn-small"><i class="fas fa-plus"></i> Add New</a>
                        <a href="#" class="btn-small"><i class="fas fa-filter"></i> Filter</a>
                        <a href="#" class="btn-small"><i class="fas fa-download"></i> Export</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Dancer</th>
                                <th>ID Number</th>
                                <th>Troupe</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($dancers)): ?>
                                <?php foreach($dancers as $dancer): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($dancer['first_name'], 0, 1) . substr($dancer['last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($dancer['first_name'] . ' ' . $dancer['last_name']); ?></strong>
                                                <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($dancer['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($dancer['id_number']); ?></td>
                                    <td><?php echo htmlspecialchars($dancer['dance_troupe'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dancer['dance_role'] ?? 'Dancer'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $dancer['status'] ?? 'active'; ?>">
                                            <?php echo ucfirst($dancer['status'] ?? 'Active'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="#" class="table-btn btn-edit">Edit</a>
                                            <a href="#" class="table-btn btn-view">View</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 30px; color: #64748b;">
                                        <i class="fas fa-users-slash" style="font-size: 24px;"></i>
                                        <p>No dancers found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Two Column Section -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                <!-- Trainers Table -->
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-user-tie"></i> Dance Trainers</h2>
                        <div class="section-actions">
                            <a href="#" class="btn-small"><i class="fas fa-plus"></i> Add</a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Trainer</th>
                                    <th>Specialization</th>
                                    <th>Troupes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($trainers)): ?>
                                    <?php foreach(array_slice($trainers, 0, 5) as $trainer): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($trainer['first_name'], 0, 1) . substr($trainer['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($trainer['dance_specialization'] ?? 'N/A'); ?></td>
                                        <td><?php echo $trainer['troupe_count'] ?? 0; ?></td>
                                        <td>
                                            <a href="#" class="table-btn btn-edit">Edit</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 20px;">No trainers found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Troupes Table -->
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-users"></i> Dance Troupes</h2>
                        <div class="section-actions">
                            <a href="#" class="btn-small"><i class="fas fa-plus"></i> New</a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Troupe</th>
                                    <th>Style</th>
                                    <th>Trainer</th>
                                    <th>Members</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($troupes)): ?>
                                    <?php foreach(array_slice($troupes, 0, 5) as $troupe): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($troupe['troupe_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($troupe['dance_style'] ?? 'Various'); ?></td>
                                        <td><?php echo htmlspecialchars($troupe['trainer_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $troupe['member_count'] ?? 0; ?></td>
                                        <td>
                                            <a href="#" class="table-btn btn-edit">Manage</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 20px;">No troupes found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Upcoming Performances -->
            <div class="section-card" style="margin-top: 25px;">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-alt"></i> Upcoming Performances</h2>
                    <div class="section-actions">
                        <a href="#" class="btn-small"><i class="fas fa-plus"></i> Add Event</a>
                    </div>
                </div>
                <div class="events-list">
                    <?php if(!empty($upcoming_performances)): ?>
                        <?php foreach($upcoming_performances as $event): ?>
                        <div class="event-item">
                            <div class="event-date">
                                <div class="day"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                                <div class="month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                            </div>
                            <div class="event-details">
                                <h4><?php echo htmlspecialchars($event['event_title']); ?></h4>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location'] ?? 'TBA'); ?> • <?php echo date('g:i A', strtotime($event['event_time'] ?? '18:00:00')); ?></p>
                            </div>
                            <span class="event-badge"><?php echo ucfirst($event['event_type'] ?? 'Dance'); ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px; color: #64748b;">
                            <i class="fas fa-calendar-times" style="font-size: 24px;"></i>
                            <p>No upcoming performances</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>