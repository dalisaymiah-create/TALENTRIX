<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get the page parameter
$page = $_GET['page'] ?? 'dashboard';

// List of allowed pages
$allowed_pages = [
    'dashboard' => 'dashboard',
    'tables' => 'tables.html',
    'charts' => 'charts.html',
    'buttons' => 'buttons.html',
    'cards' => 'cards.html',
    'animation' => 'utilities-animation.html',
    'border' => 'utilities-border.html',
    'color' => 'utilities-color.html',
    'other' => 'utilities-other.html'
];

// Get current user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

// Get dashboard statistics
$stats = [
    'total_users' => 0,
    'total_students' => 0,
    'total_admins' => 0,
    'total_faculty' => 0,
    'pending_verifications' => 0,
    'recent_registrations' => 0
];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'student'");
$stats['total_students'] = $stmt->fetch()['count'];


$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'faculty'");
$stats['total_faculty'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'pending' OR status IS NULL");
$stats['pending_verifications'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['recent_registrations'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 10");
$recent_users = $stmt->fetchAll();

$user_types = $pdo->query("SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type")->fetchAll();

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
    <title>TALENTRIX Admin - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-crown"></i> TALENTRIX Admin</h2>
                <p class="admin-info">Welcome, <?php echo htmlspecialchars($current_user['first_name']); ?></p>
                <p class="admin-email"><?php echo htmlspecialchars($current_user['email']); ?></p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php echo $page == 'dashboard' ? 'active' : ''; ?>">
                        <a href="?page=dashboard"><i class="fas fa-chart-bar"></i> <span>Dashboard</span></a>
                    </li>
                    <li class="<?php echo $page == 'users' ? 'active' : ''; ?>">
                        <a href="users.php"><i class="fas fa-users"></i> <span>Manage Users</span></a>
                    </li>
                    <li class="<?php echo $page == 'tables' ? 'active' : ''; ?>">
                        <a href="?page=tables"><i class="fas fa-table"></i> <span>Tables</span></a>
                    </li>
                    <li class="<?php echo $page == 'charts' ? 'active' : ''; ?>">
                        <a href="?page=charts"><i class="fas fa-chart-pie"></i> <span>Charts</span></a>
                    </li>
                    <li class="<?php echo $page == 'buttons' ? 'active' : ''; ?>">
                        <a href="?page=buttons"><i class="fas fa-square"></i> <span>Buttons</span></a>
                    </li>
                    <li class="<?php echo $page == 'cards' ? 'active' : ''; ?>">
                        <a href="?page=cards"><i class="fas fa-id-card"></i> <span>Cards</span></a>
                    </li>
                    <li class="<?php echo $page == 'animation' ? 'active' : ''; ?>">
                        <a href="?page=animation"><i class="fas fa-spinner"></i> <span>Animation</span></a>
                    </li>
                    <li class="<?php echo $page == 'border' ? 'active' : ''; ?>">
                        <a href="?page=border"><i class="fas fa-border-all"></i> <span>Border</span></a>
                    </li>
                    <li class="<?php echo $page == 'color' ? 'active' : ''; ?>">
                        <a href="?page=color"><i class="fas fa-palette"></i> <span>Color</span></a>
                    </li>
                    <li class="<?php echo $page == 'other' ? 'active' : ''; ?>">
                        <a href="?page=other"><i class="fas fa-tools"></i> <span>Other</span></a>
                    </li>
                    <li class="divider"></li>
                    <li><a href="../index.php"><i class="fas fa-home"></i> <span>Homepage</span></a></li>
                    <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div>
                    <h1>DASHBOARD</h1>
                    <p style="color: #718096; margin-top: 5px; font-size: 16px;">
                        <?php echo $greeting; ?>, <?php echo htmlspecialchars($current_user['first_name']); ?>! Here's your overview.
                    </p>
                </div>
                <div class="header-actions">
                    <span class="last-login"><i class="fas fa-clock"></i> Last login: Today at <?php echo date('h:i A'); ?></span>
                    <a href="register.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New User</a>
                </div>
            </header>
            
                
           <!-- Stats Grid -->
<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 30px;">
    <div class="stat-card stat-students">
        <div class="stat-icon">🎓</div>
        <div class="stat-info">
            <h3>STUDENTS</h3>
            <p class="stat-number"><?php echo number_format($stats['total_students']); ?></p>
            <p class="stat-change"><?php echo $stats['total_users'] > 0 ? round(($stats['total_students'] / $stats['total_users']) * 100, 1) : 0; ?>% of total</p>
        </div>
    </div>
    
    <div class="stat-card stat-faculty">
        <div class="stat-icon">👨‍🏫</div>
        <div class="stat-info">
            <h3>FACULTY</h3>
            <p class="stat-number"><?php echo number_format($stats['total_faculty']); ?></p>
            <p class="stat-change"><?php echo $stats['total_users'] > 0 ? round(($stats['total_faculty'] / $stats['total_users']) * 100, 1) : 0; ?>% of total</p>
        </div>
    </div>
</div>
                

            <!-- Dashboard Sections -->
            <div class="dashboard-sections">
                <div class="section-card">
                    <h3>PROFILE</h3>
                    <p>View and edit your personal information, change password, and update preferences.</p>
                    <a href="profile.php" class="new-badge">NEW</a>
                </div>
                
                <div class="section-card">
                    <h3>SETTINGS</h3>
                    <p>Configure system settings, manage permissions, and customize dashboard appearance.</p>
                    <a href="settings.php" class="new-badge">NEW</a>
                </div>
                
                <div class="section-card">
                    <h3>PROJECTS</h3>
                    <p>Manage ongoing projects, track progress, and assign tasks to team members.</p>
                    <a href="projects.php" class="new-badge">NEW</a>
                </div>
                
                <div class="section-card">
                    <h3>TASKS</h3>
                    <p>Create, assign, and track tasks. Set deadlines and monitor completion status.</p>
                    <a href="tasks.php" class="new-badge">NEW</a>
                </div>
                
                <div class="section-card">
                    <h3>FORMS</h3>
                    <p>Create and manage forms for data collection, surveys, and user submissions.</p>
                    <a href="forms.php" class="new-badge">NEW</a>
                </div>
            </div>

            <!-- Two Columns Layout -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 30px;">
                <!-- Left Column: Recent Users & Distribution -->
                <div>
                    <!-- Recent Users Table -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-users"></i> RECENT USERS</h3>
                            <a href="users.php" class="btn btn-primary" style="font-size: 14px;">View All Users</a>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID Number</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Type</th>
                                        <th>Date Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(!empty($recent_users)): ?>
                                        <?php foreach($recent_users as $user): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($user['id_number'] ?? $user['id']); ?></strong></td>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-avatar-small"><?php echo strtoupper(substr($user['first_name'], 0, 1)); ?></div>
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><span class="badge badge-<?php echo $user['user_type']; ?>"><?php echo htmlspecialchars($user['user_type']); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 30px; color: #718096;">
                                                <i class="fas fa-users-slash" style="font-size: 24px; margin-bottom: 10px;"></i>
                                                <p>No users found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- User Distribution -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> USER DISTRIBUTION</h3>
                        </div>
                        <div class="chart-container">
                            <?php foreach($user_types as $type): ?>
                            <div class="chart-item">
                                <div class="chart-label">
                                    <span class="chart-color" style="background-color: <?php 
                                        $colors = ['admin' => '#ff6b6b', 'student' => '#4ecdc4', 'faculty' => '#fdcb6e'];
                                        echo $colors[$type['user_type']] ?? '#cccccc';
                                    ?>"></span>
                                    <?php echo ucfirst($type['user_type']); ?>
                                </div>
                                <div class="chart-bar">
                                    <div class="chart-fill" style="width: <?php echo ($stats['total_users'] > 0) ? ($type['count'] / $stats['total_users']) * 100 : 0; ?>%; 
                                        background-color: <?php echo $colors[$type['user_type']] ?? '#cccccc'; ?>"></div>
                                </div>
                                <div class="chart-value">
                                    <?php echo $type['count']; ?> 
                                    (<?php echo ($stats['total_users'] > 0) ? round(($type['count'] / $stats['total_users']) * 100, 1) : 0; ?>%)
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Quick Stats & Actions -->
                <div>
                    <!-- Connected Stats -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-link"></i> CONNECTED</h3>
                        </div>
                        <div class="connected-stats">
                            <div class="stat-item">
                                <div class="stat-item-label">Total</div>
                                <div class="stat-item-value"><?php echo number_format($stats['total_users']); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-item-label">Connected</div>
                                <div class="stat-item-value"><?php echo number_format($stats['total_students'] + $stats['total_faculty']); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-item-label">Active</div>
                                <div class="stat-item-value"><?php echo number_format($stats['total_users'] - $stats['total_admins']); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-item-label">Pending</div>
                                <div class="stat-item-value"><?php echo number_format($stats['pending_verifications']); ?></div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 25px; padding-top: 25px; border-top: 2px solid #f1f5f9;">
                            <h4 style="color: #718096; margin-bottom: 15px;">Quick Actions</h4>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <a href="register.php" class="btn btn-primary" style="justify-content: center;">
                                    <i class="fas fa-user-plus"></i> Add User
                                </a>
                                <a href="reports.php" class="btn" style="background: #f8fafc; color: #4a5568; justify-content: center;">
                                    <i class="fas fa-file-export"></i> Generate Report
                                </a>
                                <a href="backup.php" class="btn" style="background: #f8fafc; color: #4a5568; justify-content: center;">
                                    <i class="fas fa-database"></i> Backup Data
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- System Status -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-server"></i> SYSTEM STATUS</h3>
                        </div>
                        <div style="margin-top: 15px;">
                            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e2e8f0;">
                                <span style="color: #718096;">Server Load</span>
                                <span style="color: #10b981; font-weight: 600;">Normal</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e2e8f0;">
                                <span style="color: #718096;">Database</span>
                                <span style="color: #10b981; font-weight: 600;">Online</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 12px 0;">
                                <span style="color: #718096;">Uptime</span>
                                <span style="font-weight: 700; color: #0a2540;">99.9%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Access to Admin Pages -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> QUICK ACCESS TO ADMIN PAGES</h3>
                </div>
                <div class="admin-pages-grid">
                    <a href="?page=tables" class="admin-page-link">
                        <span class="page-icon">📋</span>
                        <span>Tables</span>
                    </a>
                    <a href="?page=charts" class="admin-page-link">
                        <span class="page-icon">📊</span>
                        <span>Charts</span>
                    </a>
                    <a href="?page=buttons" class="admin-page-link">
                        <span class="page-icon">🔄</span>
                        <span>Buttons</span>
                    </a>
                    <a href="?page=cards" class="admin-page-link">
                        <span class="page-icon">🃏</span>
                        <span>Cards</span>
                    </a>
                    <a href="?page=animation" class="admin-page-link">
                        <span class="page-icon">🎬</span>
                        <span>Animation</span>
                    </a>
                    <a href="?page=border" class="admin-page-link">
                        <span class="page-icon">🖼️</span>
                        <span>Borders</span>
                    </a>
                    <a href="?page=color" class="admin-page-link">
                        <span class="page-icon">🎨</span>
                        <span>Colors</span>
                    </a>
                    <a href="?page=other" class="admin-page-link">
                        <span class="page-icon">🔧</span>
                        <span>Other</span>
                    </a>
                </div>
            </div>

            <!-- Footer -->
            <footer style="margin-top: 40px; padding: 25px; text-align: center; color: #718096; border-top: 2px solid #e2e8f0;">
                <p>© <?php echo date('Y'); ?> TALENTRIX Admin Dashboard. All rights reserved.</p>
                <p style="font-size: 14px; margin-top: 8px;">Version 2.1.0 | Last updated: <?php echo date('F d, Y'); ?></p>
            </footer>
        </div>
    </div>

    <script>
        // Animation for chart bars
        document.addEventListener('DOMContentLoaded', function() {
            const chartFills = document.querySelectorAll('.chart-fill');
            chartFills.forEach(fill => {
                const width = fill.style.width;
                fill.style.width = '0';
                setTimeout(() => {
                    fill.style.width = width;
                }, 300);
            });
            
            // Add hover effects
            const cards = document.querySelectorAll('.stat-card, .section-card, .dashboard-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-8px)';
                });
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>