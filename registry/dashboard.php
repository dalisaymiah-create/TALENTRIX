<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get current user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

// Set session variables if not set
if (!isset($_SESSION['full_name']) && $current_user) {
    $_SESSION['full_name'] = $current_user['first_name'] . ' ' . $current_user['last_name'];
    $_SESSION['email'] = $current_user['email'];
}

// Get statistics
$total_students = $pdo->query("SELECT COUNT(*) as students FROM users WHERE user_type = 'student'")->fetch()['students'];
$total_faculty = $pdo->query("SELECT COUNT(*) as faculty FROM users WHERE user_type = 'faculty'")->fetch()['faculty'];
$total_admins = $pdo->query("SELECT COUNT(*) as admins FROM users WHERE user_type = 'admin'")->fetch()['admins'];
$total_users = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'];

// Get homepage stats
$total_achievements = $pdo->query("SELECT COUNT(*) as count FROM homepage_content")->fetch()['count'];
$total_events = $pdo->query("SELECT COUNT(*) as count FROM upcoming_events")->fetch()['count'];
$total_images = $pdo->query("SELECT COUNT(*) as count FROM homepage_images")->fetch()['count'];

// Calculate percentages
$student_percent = $total_users > 0 ? round(($total_students / $total_users) * 100, 1) : 0;
$faculty_percent = $total_users > 0 ? round(($total_faculty / $total_users) * 100, 1) : 0;

$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll();
$user_types = $pdo->query("SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type")->fetchAll();

// Get recent homepage activity
$recent_achievements = $pdo->query("SELECT * FROM homepage_content ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recent_events = $pdo->query("SELECT * FROM upcoming_events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5")->fetchAll();

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
    <title>Admin Dashboard - Registry System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Additional styles for homepage management */
        .stat-card.homepage {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .stat-card.images {
            background: linear-gradient(135deg, #8B1E3F 0%, #6b152f 100%);
        }
        
        .stat-card.events {
            background: linear-gradient(135deg, #FFB347 0%, #f39c12 100%);
        }
        
        .admin-pages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .admin-page-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px 10px;
            background: #f8fafc;
            border-radius: 10px;
            text-decoration: none;
            color: #4a5568;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        
        .admin-page-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            background: white;
            border-color: #10b981;
        }
        
        .admin-page-link.homepage {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .admin-page-link.images {
            background: linear-gradient(135deg, #8B1E3F 0%, #6b152f 100%);
            color: white;
        }
        
        .page-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .admin-page-link span:last-child {
            font-size: 12px;
            font-weight: 600;
        }
        
        .homepage-activity-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .activity-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .activity-card h4 {
            color: #0a2540;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-badge {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .badge-athletics { background: #8B1E3F; color: white; }
        .badge-dance { background: #FFB347; color: #1e3e5c; }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: #0a2540;
            font-size: 0.9rem;
        }
        
        .activity-meta {
            font-size: 0.8rem;
            color: #718096;
        }
        
        .view-all-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            padding: 8px;
            background: #f8fafc;
            border-radius: 8px;
            color: #10b981;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .view-all-link:hover {
            background: #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-crown"></i> Admin Panel</h2>
                <p class="admin-info">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $current_user['first_name']); ?></p>
                <p class="admin-email"><?php echo htmlspecialchars($_SESSION['email'] ?? $current_user['email']); ?></p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="active"><a href="dashboard.php"><i class="fas fa-chart-bar"></i> <span>Dashboard</span></a></li>
                    <li><a href="manage_users.php"><i class="fas fa-users"></i> <span>Manage Users</span></a></li>
                    
                    <!-- Homepage Management Section -->
                    <li><a href="manage_homepage.php"><i class="fas fa-home"></i> <span>Manage Homepage</span></a></li>
                    <li><a href="manage_images.php"><i class="fas fa-images"></i> <span>Homepage Images</span></a></li>
                    
                    <li><a href="tables.php"><i class="fas fa-table"></i> <span>Tables</span></a></li>
                    <li><a href="charts.php"><i class="fas fa-chart-pie"></i> <span>Charts</span></a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
                    <li><a href="projects.php"><i class="fas fa-project-diagram"></i> <span>Projects</span></a></li>
                    <li><a href="tasks.php"><i class="fas fa-tasks"></i> <span>Tasks</span></a></li>
                    <li><a href="forms.php"><i class="fas fa-file-alt"></i> <span>Forms</span></a></li>
                    <li class="divider"></li>
                    <li><a href="index.php"><i class="fas fa-eye"></i> <span>View Homepage</span></a></li>
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
                        <?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $current_user['first_name']); ?>! Here's your overview.
                    </p>
                </div>
                <div class="header-actions">
                    <span class="last-login"><i class="fas fa-clock"></i> Last login: Today at <?php echo date('h:i A'); ?></span>
                    <a href="register.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New User</a>
                </div>
            </header>

            <!-- Stats Grid - 4 columns -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; margin-bottom: 30px;">
                <div class="stat-card stat-students">
                    <div class="stat-icon">🎓</div>
                    <div class="stat-info">
                        <h3>STUDENTS</h3>
                        <p class="stat-number"><?php echo $total_students; ?></p>
                        <p class="stat-change"><?php echo $student_percent; ?>% of total</p>
                    </div>
                </div>
                
                <div class="stat-card stat-faculty">
                    <div class="stat-icon">👨‍🏫</div>
                    <div class="stat-info">
                        <h3>FACULTY</h3>
                        <p class="stat-number"><?php echo $total_faculty; ?></p>
                        <p class="stat-change"><?php echo $faculty_percent; ?>% of total</p>
                    </div>
                </div>
                
                <!-- Homepage Stats -->
                <div class="stat-card homepage">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-info">
                        <h3>ACHIEVEMENTS</h3>
                        <p class="stat-number"><?php echo $total_achievements; ?></p>
                        <p class="stat-change">On homepage</p>
                    </div>
                </div>
                
                <div class="stat-card events">
                    <div class="stat-icon">📅</div>
                    <div class="stat-info">
                        <h3>EVENTS</h3>
                        <p class="stat-number"><?php echo $total_events; ?></p>
                        <p class="stat-change">Upcoming</p>
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
                            <a href="manage_users.php" class="btn btn-outline" style="font-size: 14px;">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Type</th>
                                        <th>Date Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar-small"><?php echo strtoupper(substr($user['first_name'], 0, 1)); ?></div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong><br>
                                                    <small style="color: #718096;">ID: <?php echo htmlspecialchars($user['id_number'] ?? $user['id']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><span class="badge badge-<?php echo $user['user_type']; ?>"><?php echo htmlspecialchars($user['user_type']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
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
                                    <div class="chart-fill" style="width: <?php echo ($total_users > 0) ? ($type['count'] / $total_users) * 100 : 0; ?>%; 
                                        background-color: <?php echo $colors[$type['user_type']] ?? '#cccccc'; ?>"></div>
                                </div>
                                <div class="chart-value">
                                    <?php echo $type['count']; ?> 
                                    (<?php echo ($total_users > 0) ? round(($type['count'] / $total_users) * 100, 1) : 0; ?>%)
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
                                <div class="stat-item-label">Total Users</div>
                                <div class="stat-item-value"><?php echo $total_users; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-item-label">Students + Faculty</div>
                                <div class="stat-item-value"><?php echo $total_students + $total_faculty; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-item-label">Admins</div>
                                <div class="stat-item-value"><?php echo $total_admins; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-item-label">Total Images</div>
                                <div class="stat-item-value"><?php echo $total_images; ?></div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 25px; padding-top: 25px; border-top: 2px solid #f1f5f9;">
                            <h4 style="color: #718096; margin-bottom: 15px;">Quick Actions</h4>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <a href="register.php" class="btn btn-primary" style="justify-content: center;">
                                    <i class="fas fa-user-plus"></i> Add User
                                </a>
                                <a href="manage_homepage.php" class="btn" style="background: #10b981; color: white; justify-content: center;">
                                    <i class="fas fa-home"></i> Manage Homepage
                                </a>
                                <a href="manage_images.php" class="btn" style="background: #8B1E3F; color: white; justify-content: center;">
                                    <i class="fas fa-images"></i> Upload Images
                                </a>
                                <a href="reports.php" class="btn" style="background: #f8fafc; color: #4a5568; justify-content: center;">
                                    <i class="fas fa-file-export"></i> Generate Report
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

            <!-- Homepage Activity Section -->
            <div class="dashboard-card" style="margin-top: 30px;">
                <div class="card-header">
                    <h3><i class="fas fa-home"></i> HOMEPAGE ACTIVITY</h3>
                    <div>
                        <a href="manage_homepage.php" class="btn btn-outline" style="margin-right: 10px;">Manage</a>
                        <a href="index.php" class="btn btn-primary">View Homepage</a>
                    </div>
                </div>
                
                <div class="homepage-activity-grid">
                    <!-- Recent Achievements -->
                    <div class="activity-card">
                        <h4><i class="fas fa-trophy" style="color: #FFB347;"></i> Recent Achievements</h4>
                        <?php if(empty($recent_achievements)): ?>
                            <p style="color: #718096; text-align: center; padding: 20px;">No achievements yet</p>
                        <?php else: ?>
                            <?php foreach($recent_achievements as $ach): ?>
                            <div class="activity-item">
                                <div class="activity-badge <?php echo $ach['section_type'] == 'athletics' ? 'badge-athletics' : 'badge-dance'; ?>">
                                    <?php echo $ach['section_type'] == 'athletics' ? '🏀' : '💃'; ?>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title"><?php echo htmlspecialchars($ach['title']); ?></div>
                                    <div class="activity-meta">
                                        <?php echo date('M d, Y', strtotime($ach['post_date'])); ?> • 
                                        <?php echo $ach['badge']; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <a href="manage_homepage.php" class="view-all-link">View All Achievements →</a>
                    </div>
                    
                    <!-- Upcoming Events -->
                    <div class="activity-card">
                        <h4><i class="fas fa-calendar-alt" style="color: #10b981;"></i> Upcoming Events</h4>
                        <?php if(empty($recent_events)): ?>
                            <p style="color: #718096; text-align: center; padding: 20px;">No upcoming events</p>
                        <?php else: ?>
                            <?php foreach($recent_events as $event): ?>
                            <div class="activity-item">
                                <div class="activity-badge" style="background: <?php echo $event['event_type'] == 'athletics' ? '#8B1E3F' : '#FFB347'; ?>; color: white;">
                                    <?php echo $event['event_type'] == 'athletics' ? '🏃' : '💃'; ?>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                                    <div class="activity-meta">
                                        <?php echo date('M d, Y', strtotime($event['event_date'])); ?> • 
                                        <?php echo ucfirst($event['event_type']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <a href="manage_homepage.php#events" class="view-all-link">View All Events →</a>
                    </div>
                </div>
            </div>

            <!-- Quick Access to Admin Pages -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> QUICK ACCESS</h3>
                </div>
                <div class="admin-pages-grid">
                    <!-- Homepage Management Quick Links -->
                    <a href="manage_homepage.php" class="admin-page-link homepage">
                        <span class="page-icon">🏠</span>
                        <span>Manage Homepage</span>
                    </a>
                    <a href="manage_images.php" class="admin-page-link images">
                        <span class="page-icon">📸</span>
                        <span>Homepage Images</span>
                    </a>
                    
                    <a href="tables.php" class="admin-page-link">
                        <span class="page-icon">📋</span>
                        <span>Tables</span>
                    </a>
                    <a href="charts.php" class="admin-page-link">
                        <span class="page-icon">📊</span>
                        <span>Charts</span>
                    </a>
                    <a href="buttons.php" class="admin-page-link">
                        <span class="page-icon">🔄</span>
                        <span>Buttons</span>
                    </a>
                    <a href="cards.php" class="admin-page-link">
                        <span class="page-icon">🃏</span>
                        <span>Cards</span>
                    </a>
                    <a href="utilities-animation.php" class="admin-page-link">
                        <span class="page-icon">🎬</span>
                        <span>Animation</span>
                    </a>
                    <a href="utilities-border.php" class="admin-page-link">
                        <span class="page-icon">🖼️</span>
                        <span>Borders</span>
                    </a>
                    <a href="utilities-color.php" class="admin-page-link">
                        <span class="page-icon">🎨</span>
                        <span>Colors</span>
                    </a>
                    <a href="utilities-other.php" class="admin-page-link">
                        <span class="page-icon">🔧</span>
                        <span>Other</span>
                    </a>
                    <a href="profile.php" class="admin-page-link">
                        <span class="page-icon">👤</span>
                        <span>Profile</span>
                    </a>
                    <a href="settings.php" class="admin-page-link">
                        <span class="page-icon">⚙️</span>
                        <span>Settings</span>
                    </a>
                </div>
            </div>

            <!-- Footer -->
            <footer style="margin-top: 40px; padding: 25px; text-align: center; color: #718096; border-top: 2px solid #e2e8f0;">
                <p>© <?php echo date('Y'); ?> Registry System Admin Dashboard. All rights reserved.</p>
                <p style="font-size: 14px; margin-top: 8px;">Version 2.1.0 | Last updated: <?php echo date('F d, Y'); ?></p>
            </footer>
        </div>
    </div>

    <script>
        // Simple animation for chart bars
        document.addEventListener('DOMContentLoaded', function() {
            const chartFills = document.querySelectorAll('.chart-fill');
            chartFills.forEach(fill => {
                const width = fill.style.width;
                fill.style.width = '0';
                setTimeout(() => {
                    fill.style.width = width;
                }, 300);
            });
            
            // Add hover effects to cards
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