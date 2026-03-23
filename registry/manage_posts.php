<?php
// manage_posts.php - Manage Latest Posts
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'athletics_admin' && $_SESSION['user_type'] !== 'dance_admin')) {
    header('Location: login.php');
    exit();
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    if ($stmt->execute([$id])) {
        $success = "Post deleted successfully!";
    } else {
        $error = "Error deleting post.";
    }
}

// Get all posts
$posts = $pdo->query("SELECT * FROM posts ORDER BY post_date DESC")->fetchAll();

// Get categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Manage Posts</title>
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

        .btn-manage {
            background: #0a2540;
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

        .btn-manage:hover {
            background: #1a365d;
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

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-left: 5px solid #8B1E3F;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #0a2540;
        }

        .stat-label {
            color: #6c757d;
            font-size: 13px;
            text-transform: uppercase;
        }

        /* Table */
        .table-card {
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
        }

        td {
            padding: 15px 25px;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
            font-size: 14px;
        }

        .badge-category {
            background: #e9ecef;
            color: #495057;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
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
                <h2><i class="fas fa-newspaper"></i>TALENTRIX</h2>
                <div class="admin-info">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></div>
                <div class="admin-email"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'admin@talentrix.edu'); ?></div>
                <span class="admin-badge">Content Manager</span>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin_dashboard.php"><i class="fas fa-chart-bar"></i><span>Dashboard</span></a></li>
                    <li class="active"><a href="manage_posts.php"><i class="fas fa-newspaper"></i><span>Manage Posts</span></a></li>
                    <li><a href="manage_categories.php"><i class="fas fa-tags"></i><span>Categories</span></a></li>
                    <li><a href="admin_achievements.php"><i class="fas fa-trophy"></i><span>Achievements</span></a></li>
                    <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h1>MANAGE POSTS</h1>
                    <p>Create and edit latest news and announcements</p>
                </div>
                <div class="header-right">
                    <div class="date-badge"><i class="far fa-calendar-alt"></i> <?php echo date('F d, Y'); ?></div>
                    <a href="add_post.php" class="btn-add"><i class="fas fa-plus"></i> Add New Post</a>
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
                    <div class="stat-number"><?php echo count($posts); ?></div>
                    <div class="stat-label">Total Posts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php 
                        $announcements = array_filter($posts, function($p) { return $p['category'] == 'Announcements'; });
                        echo count($announcements);
                    ?></div>
                    <div class="stat-label">Announcements</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php 
                        $news = array_filter($posts, function($p) { return $p['category'] == 'News'; });
                        echo count($news);
                    ?></div>
                    <div class="stat-label">News</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php 
                        $hiring = array_filter($posts, function($p) { return $p['category'] == 'Hiring'; });
                        echo count($hiring);
                    ?></div>
                    <div class="stat-label">Hiring</div>
                </div>
            </div>

            <!-- Posts Table -->
            <div class="table-card">
                <div class="card-header">
                    <h2>LATEST POSTS</h2>
                    <span>Last 5 entries</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>TITLE</th>
                            <th>CATEGORY</th>
                            <th>DATE</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($posts)): ?>
                            <?php foreach (array_slice($posts, 0, 5) as $post): ?>
                            <tr>
                                <td><strong>#<?php echo $post['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars(substr($post['title'], 0, 50)) . '...'; ?></td>
                                <td><span class="badge-category"><?php echo htmlspecialchars($post['category']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($post['post_date'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="?delete=<?php echo $post['id']; ?>" class="btn-delete" onclick="return confirm('Delete this post?')"><i class="fas fa-trash"></i> Delete</a>
                                        <a href="preview_post.php?id=<?php echo $post['id']; ?>" class="btn-view"><i class="fas fa-eye"></i> Preview</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-newspaper" style="font-size: 48px; color: #6c757d;"></i>
                                    <h3 style="color: #6c757d; margin-top: 10px;">No posts yet</h3>
                                    <p>Click "Add New Post" to create your first post.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        setTimeout(function() {
            var successAlert = document.getElementById('successAlert');
            var errorAlert = document.getElementById('errorAlert');
            if (successAlert) successAlert.style.display = 'none';
            if (errorAlert) errorAlert.style.display = 'none';
        }, 5000);
    </script>
</body>
</html>