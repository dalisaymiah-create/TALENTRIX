<?php
// admin_achievements.php - Complete Achievements Manager
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get current user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM achievements WHERE id = ?");
    if ($stmt->execute([$id])) {
        $success = "Achievement deleted successfully!";
    } else {
        $error = "Error deleting achievement.";
    }
}

// Get all achievements
$achievements = $pdo->query("
    SELECT a.*, u.first_name, u.last_name 
    FROM achievements a 
    LEFT JOIN users u ON a.created_by = u.id 
    ORDER BY a.achievement_date DESC
")->fetchAll();

// Get statistics
$total_athlete = $pdo->query("SELECT COUNT(*) FROM achievements WHERE category = 'athlete'")->fetchColumn();
$total_dance = $pdo->query("SELECT COUNT(*) FROM achievements WHERE category = 'dance'")->fetchColumn();
$total_achievements = $total_athlete + $total_dance;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Achievements Manager</title>
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
            color: #8B1E3F;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .sidebar-header h2 i {
            color: #8B1E3F;
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
            background: #8B1E3F;
            color: white;
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
            color: #8B1E3F;
        }

        .sidebar-nav a:hover i {
            color: #8B1E3F;
        }

        .sidebar-nav li.active a {
            background: #f8f9fa;
            color: #8B1E3F;
            border-right: 3px solid #8B1E3F;
        }

        .sidebar-nav li.active a i {
            color: #8B1E3F;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

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
            color: #8B1E3F;
        }

        .btn-add {
            background: #8B1E3F;
            color: white;
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
            background: #6b152f;
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #e9ecef;
            border-left: 5px solid #8B1E3F;
        }

        .stat-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .stat-header i {
            font-size: 20px;
            color: #8B1E3F;
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

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            margin-right: 10px;
        }

        .close-alert {
            cursor: pointer;
            font-size: 18px;
        }

        /* Achievements Table */
        .achievements-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            overflow: hidden;
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

        .card-header span {
            background: #f8f9fa;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            color: #6c757d;
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

        .badge-athlete {
            background: #8B1E3F;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-dance {
            background: #f59e0b;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-edit {
            color: #8B1E3F;
            background: none;
            border: 1px solid #8B1E3F;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-edit:hover {
            background: #8B1E3F;
            color: white;
        }

        .btn-delete {
            color: #dc3545;
            background: none;
            border: 1px solid #dc3545;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-delete:hover {
            background: #dc3545;
            color: white;
        }

        .btn-view {
            color: #17a2b8;
            background: none;
            border: 1px solid #17a2b8;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-view:hover {
            background: #17a2b8;
            color: white;
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
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-trophy"></i>TALENTRIX</h2>
                <div class="admin-info">Welcome, <?php echo htmlspecialchars($current_user['first_name'] ?? 'Admin'); ?></div>
                <div class="admin-email"><?php echo htmlspecialchars($current_user['email'] ?? 'admin@talentrix.edu'); ?></div>
                <span class="admin-badge">Achievements Admin</span>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="athletics_admin_dashboard.php"><i class="fas fa-chart-bar"></i><span>Dashboard</span></a></li>
                    <li><a href="manage_athletes.php"><i class="fas fa-running"></i><span>Manage Athletes</span></a></li>
                    <li><a href="manage_coaches.php"><i class="fas fa-user-tie"></i><span>Manage Coaches</span></a></li>
                    <li class="active"><a href="admin_achievements.php"><i class="fas fa-trophy"></i><span>Achievements</span></a></li>
                    <li><a href="events.php"><i class="fas fa-calendar"></i><span>Events</span></a></li>
                    <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h1>ACHIEVEMENTS MANAGER</h1>
                    <p>Manage all athlete and dance achievements</p>
                </div>
                <div class="header-right">
                    <div class="date-badge"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y'); ?></div>
                    <a href="add_achievement.php" class="btn-add"><i class="fas fa-plus"></i> Add Achievement</a>
                </div>
            </div>

            <?php if (isset($success)): ?>
            <div class="alert alert-success" id="successAlert">
                <span><i class="fas fa-check-circle"></i> <?php echo $success; ?></span>
                <span class="close-alert" onclick="this.parentElement.style.display='none'">&times;</span>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="alert alert-error" id="errorAlert">
                <span><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></span>
                <span class="close-alert" onclick="this.parentElement.style.display='none'">&times;</span>
            </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-header"><i class="fas fa-trophy"></i><h3>TOTAL ACHIEVEMENTS</h3></div>
                    <div class="stat-number"><?php echo $total_achievements; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><i class="fas fa-running"></i><h3>ATHLETE ACHIEVEMENTS</h3></div>
                    <div class="stat-number"><?php echo $total_athlete; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><i class="fas fa-music"></i><h3>DANCE ACHIEVEMENTS</h3></div>
                    <div class="stat-number"><?php echo $total_dance; ?></div>
                </div>
            </div>

            <!-- Achievements Table -->
            <div class="achievements-card">
                <div class="card-header">
                    <h2>ALL ACHIEVEMENTS</h2>
                    <span>Total: <?php echo $total_achievements; ?> records</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>TITLE</th>
                            <th>TEAM</th>
                            <th>CATEGORY</th>
                            <th>DATE</th>
                            <th>ADDED BY</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($achievements)): ?>
                            <?php foreach ($achievements as $ach): ?>
                            <tr>
                                <td><strong><?php echo $ach['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($ach['title']); ?></td>
                                <td><?php echo htmlspecialchars($ach['team'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge-<?php echo $ach['category']; ?>">
                                        <?php echo ucfirst($ach['category']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($ach['achievement_date'])); ?></td>
                                <td><?php echo htmlspecialchars($ach['first_name'] . ' ' . $ach['last_name']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_achievement.php?id=<?php echo $ach['id']; ?>" class="btn-view"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit_achievement.php?id=<?php echo $ach['id']; ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="?delete=<?php echo $ach['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this achievement?')"><i class="fas fa-trash"></i> Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-trophy" style="font-size: 48px; color: #6c757d; margin-bottom: 15px; display: block;"></i>
                                    <h3 style="color: #6c757d; margin-bottom: 10px;">No Achievements Found</h3>
                                    <p style="color: #6c757d;">Click the "Add Achievement" button to add your first achievement.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var successAlert = document.getElementById('successAlert');
            var errorAlert = document.getElementById('errorAlert');
            if (successAlert) successAlert.style.display = 'none';
            if (errorAlert) errorAlert.style.display = 'none';
        }, 5000);
    </script>
</body>
</html>