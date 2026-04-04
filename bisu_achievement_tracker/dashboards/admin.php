<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth($pdo);
$auth->requireAuth();
$auth->requireRole('admin');

// Check if admin has specific type, redirect to appropriate dashboard
$adminType = $auth->getAdminType();
if ($adminType === 'sports') {
    header("Location: admin_sports.php");
    exit();
} elseif ($adminType === 'cultural_arts') {
    header("Location: admin_cultural.php");
    exit();
}

// If no specific type or general admin, show all data
// Get statistics
$stats = [];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM \"User\"");
$stats['total_users'] = $stmt->fetch()['total'];

// Total students
$stmt = $pdo->query("SELECT COUNT(*) as total FROM Student");
$stats['total_students'] = $stmt->fetch()['total'];

// Total coaches
$stmt = $pdo->query("SELECT COUNT(*) as total FROM Coach");
$stats['total_coaches'] = $stmt->fetch()['total'];

// Total participations
$stmt = $pdo->query("SELECT COUNT(*) as total FROM Participation");
$stats['total_participations'] = $stmt->fetch()['total'];

// Total awards
$stmt = $pdo->query("
    SELECT COUNT(*) as total FROM Participation 
    WHERE ranking IN ('champion', '1st_place', '2nd_place', '3rd_place')
");
$stats['total_awards'] = $stmt->fetch()['total'];

// Get all users
$stmt = $pdo->query("
    SELECT u.*, 
           CASE 
               WHEN u.usertype = 'student' THEN s.first_name || ' ' || s.last_name
               WHEN u.usertype = 'coach' THEN c.first_name || ' ' || c.last_name
               ELSE u.email
           END as fullname
    FROM \"User\" u
    LEFT JOIN Student s ON u.id = s.user_id
    LEFT JOIN Coach c ON u.id = c.user_id
    ORDER BY u.id DESC
");
$users = $stmt->fetchAll();

// Get all activities
$stmt = $pdo->query("
    SELECT a.*, COUNT(c.id) as contest_count
    FROM Activity a
    LEFT JOIN Contest c ON a.id = c.activity_id
    GROUP BY a.id
    ORDER BY a.year DESC
");
$activities = $stmt->fetchAll();

// Get all contests
$stmt = $pdo->query("
    SELECT c.*, a.activity_name, cat.category_name
    FROM Contest c
    JOIN Activity a ON c.activity_id = a.id
    LEFT JOIN Category cat ON c.category_id = cat.id
    ORDER BY c.year DESC, c.id DESC
");
$contests = $stmt->fetchAll();

// Get all categories
$stmt = $pdo->query("SELECT * FROM Category ORDER BY category_name");
$categories = $stmt->fetchAll();

// Get recent participations
$stmt = $pdo->query("
    SELECT p.*, s.first_name, s.last_name, s.course, c.name as contest_name
    FROM Participation p
    JOIN Student s ON p.student_id = s.id
    JOIN Contest c ON p.contest_id = c.id
    ORDER BY p.created_at DESC
    LIMIT 20
");
$recentParticipations = $stmt->fetchAll();

// Get announcements
$stmt = $pdo->query("
    SELECT a.*, u.email as author_email
    FROM Announcement a
    LEFT JOIN \"User\" u ON a.author_id = u.id
    ORDER BY a.created_at DESC
");
$announcements = $stmt->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_activity':
                    $stmt = $pdo->prepare("
                        INSERT INTO Activity (activity_name, activity_type, event_name, year, activity_type_filter) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['activity_name'],
                        $_POST['activity_type'],
                        $_POST['event_name'],
                        $_POST['year'],
                        $_POST['activity_type_filter'] ?? 'general'
                    ]);
                    $message = "Activity added successfully!";
                    $messageType = "success";
                    break;
                    
                case 'add_contest':
                    $stmt = $pdo->prepare("
                        INSERT INTO Contest (activity_id, name, tournament_manager, category_id, year) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['activity_id'],
                        $_POST['name'],
                        $_POST['tournament_manager'],
                        $_POST['category_id'] ?: null,
                        $_POST['year']
                    ]);
                    $message = "Contest added successfully!";
                    $messageType = "success";
                    break;
                    
                case 'add_category':
                    $stmt = $pdo->prepare("
                        INSERT INTO Category (category_name, category_type) VALUES (?, ?)
                    ");
                    $stmt->execute([$_POST['category_name'], $_POST['category_type'] ?? 'general']);
                    $message = "Category added successfully!";
                    $messageType = "success";
                    break;
                    
                case 'delete_user':
                    $stmt = $pdo->prepare("DELETE FROM \"User\" WHERE id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    $message = "User deleted successfully!";
                    $messageType = "success";
                    break;
                    
                case 'add_announcement':
                    $stmt = $pdo->prepare("
                        INSERT INTO Announcement (title, content, announcement_type, author_id, is_published) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['content'],
                        $_POST['announcement_type'],
                        $_SESSION['user_id'],
                        isset($_POST['is_published']) ? 1 : 0
                    ]);
                    $message = "Announcement added successfully!";
                    $messageType = "success";
                    break;
                    
                case 'delete_announcement':
                    $stmt = $pdo->prepare("DELETE FROM Announcement WHERE id = ?");
                    $stmt->execute([$_POST['announcement_id']]);
                    $message = "Announcement deleted successfully!";
                    $messageType = "success";
                    break;
                    
                case 'update_announcement_status':
                    $stmt = $pdo->prepare("UPDATE Announcement SET is_published = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$_POST['is_published'], $_POST['announcement_id']]);
                    $message = "Announcement status updated!";
                    $messageType = "success";
                    break;
            }
            
            // Refresh data after modifications
            header("Location: admin.php");
            exit();
            
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}
?>
<?php include '../includes/header.php'; ?>
<div class="dashboard-wrapper">
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-user-shield" style="font-size: 2rem;"></i>
            <h3>Admin Portal</h3>
            <p>System Administrator</p>
        </div>
        <div class="sidebar-nav">
            <a href="#" class="active" onclick="showSection('overview')">
                <i class="fas fa-home"></i> Overview
            </a>
            <a href="#" onclick="showSection('announcements')">
                <i class="fas fa-bullhorn"></i> Announcements
            </a>
            <a href="#" onclick="showSection('users')">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="#" onclick="showSection('activities')">
                <i class="fas fa-calendar-alt"></i> Activities
            </a>
            <a href="#" onclick="showSection('contests')">
                <i class="fas fa-trophy"></i> Contests
            </a>
            <a href="#" onclick="showSection('categories')">
                <i class="fas fa-tags"></i> Categories
            </a>
            <a href="#" onclick="showSection('participations')">
                <i class="fas fa-list"></i> Participations
            </a>
            <a href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h2>Admin Dashboard</h2>
                <p>System Management and Monitoring</p>
            </div>
            <div class="user-info">
                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($_SESSION['email']); ?></span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Overview Section -->
        <div id="overview-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total Users</h3>
                        <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Students</h3>
                        <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Coaches</h3>
                        <div class="stat-number"><?php echo $stats['total_coaches']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-user"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Participations</h3>
                        <div class="stat-number"><?php echo $stats['total_participations']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Awards Given</h3>
                        <div class="stat-number"><?php echo $stats['total_awards']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-medal"></i>
                    </div>
                </div>
            </div>
            
            <!-- Recent Announcements Preview -->
            <div class="data-table">
                <h3><i class="fas fa-bullhorn"></i> Recent Announcements</h3>
                <?php $recentAnnouncements = array_slice($announcements, 0, 3); ?>
                <?php if (!empty($recentAnnouncements)): ?>
                    <?php foreach ($recentAnnouncements as $announcement): ?>
                        <div class="announcement-preview" style="background: #f8f9fa; padding: 15px; margin-bottom: 15px; border-radius: 12px; border-left: 4px solid #667eea;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <h4 style="color: #333; margin: 0;"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                <span class="badge" style="background: <?php echo $announcement['is_published'] ? '#28a745' : '#dc3545'; ?>; color: white;">
                                    <?php echo $announcement['is_published'] ? 'Published' : 'Draft'; ?>
                                </span>
                            </div>
                            <p style="color: #666; margin-bottom: 10px;"><?php echo htmlspecialchars(substr($announcement['content'], 0, 150)) . '...'; ?></p>
                            <small style="color: #999;">Posted: <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No announcements yet. Click "Announcements" to create one.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Announcements Section -->
        <div id="announcements-section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-plus-circle"></i> Create New Announcement</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_announcement">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" placeholder="Announcement Title" required>
                    </div>
                    <div class="form-group">
                        <label>Content</label>
                        <textarea name="content" rows="5" placeholder="Write your announcement here..." required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px;"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Announcement Type</label>
                            <select name="announcement_type" required>
                                <option value="general">General</option>
                                <option value="sports">Sports & Athletic</option>
                                <option value="cultural_arts">Culture & Arts</option>
                                <option value="event">Event</option>
                                <option value="achievement">Achievement</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="is_published">
                                <option value="1">Published</option>
                                <option value="0">Draft</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Post Announcement</button>
                </form>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-bullhorn"></i> All Announcements</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Content</th>
                            <th>Status</th>
                            <th>Author</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($announcements as $announcement): ?>
                        <tr>
                            <td><?php echo $announcement['id']; ?></td>
                            <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                            <td><span class="badge" style="background: #667eea; color: white;"><?php echo ucfirst(str_replace('_', ' ', $announcement['announcement_type'])); ?></span></td>
                            <td style="max-width: 300px;"><?php echo htmlspecialchars(substr($announcement['content'], 0, 80)) . '...'; ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_announcement_status">
                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                    <input type="hidden" name="is_published" value="<?php echo $announcement['is_published'] ? 0 : 1; ?>">
                                    <button type="submit" class="btn-<?php echo $announcement['is_published'] ? 'warning' : 'success'; ?>" style="padding: 5px 10px; font-size: 0.75rem; background: <?php echo $announcement['is_published'] ? '#ffc107' : '#28a745'; ?>; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                        <?php echo $announcement['is_published'] ? 'Unpublish' : 'Publish'; ?>
                                    </button>
                                </form>
                            </td>
                            <td><?php echo htmlspecialchars($announcement['author_email']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this announcement?')">
                                    <input type="hidden" name="action" value="delete_announcement">
                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Users Section -->
        <div id="users-section" style="display: none;">
            <div class="data-table">
                <h3>System Users</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Name</th>
                            <th>User Type</th>
                            <th>Admin Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                            <td><?php echo ucfirst($user['usertype']); ?></td>
                            <td><?php echo $user['admin_type'] ? ucfirst(str_replace('_', ' ', $user['admin_type'])) : '-'; ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Activities Section -->
        <div id="activities-section" style="display: none;">
            <div class="form-card">
                <h3>Add New Activity</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_activity">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Activity Name</label>
                            <input type="text" name="activity_name" required>
                        </div>
                        <div class="form-group">
                            <label>Activity Type</label>
                            <select name="activity_type" required>
                                <option value="sports">Sports</option>
                                <option value="arts">Arts</option>
                                <option value="cultural">Cultural</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Event Name</label>
                            <input type="text" name="event_name">
                        </div>
                        <div class="form-group">
                            <label>Year</label>
                            <input type="number" name="year" value="<?php echo date('Y'); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Activity Filter (Assign to)</label>
                        <select name="activity_type_filter" required>
                            <option value="general">General (All Admins)</option>
                            <option value="sports">Sports & Athletic Admin Only</option>
                            <option value="cultural_arts">Culture & Arts Admin Only</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">Add Activity</button>
                </form>
            </div>
            
            <div class="data-table">
                <h3>Existing Activities</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Activity Name</th>
                            <th>Type</th>
                            <th>Event</th>
                            <th>Year</th>
                            <th>Assigned To</th>
                            <th>Contests</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $activity): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($activity['activity_name']); ?></td>
                            <td><?php echo ucfirst($activity['activity_type']); ?></td>
                            <td><?php echo htmlspecialchars($activity['event_name']); ?></td>
                            <td><?php echo $activity['year']; ?></td>
                            <td>
                                <?php 
                                if ($activity['activity_type_filter'] == 'sports') echo 'Sports & Athletic';
                                elseif ($activity['activity_type_filter'] == 'cultural_arts') echo 'Culture & Arts';
                                else echo 'General';
                                ?>
                            </td>
                            <td><?php echo $activity['contest_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Contests Section -->
        <div id="contests-section" style="display: none;">
            <div class="form-card">
                <h3>Add New Contest</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_contest">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Activity</label>
                            <select name="activity_id" required>
                                <option value="">Select Activity</option>
                                <?php foreach ($activities as $activity): ?>
                                <option value="<?php echo $activity['id']; ?>">
                                    <?php echo htmlspecialchars($activity['activity_name'] . ' (' . $activity['year'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Contest Name</label>
                            <input type="text" name="name" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tournament Manager</label>
                            <input type="text" name="tournament_manager">
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" name="year" value="<?php echo date('Y'); ?>" required>
                    </div>
                    <button type="submit" class="btn-primary">Add Contest</button>
                </form>
            </div>
            
            <div class="data-table">
                <h3>Existing Contests</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Contest Name</th>
                            <th>Activity</th>
                            <th>Category</th>
                            <th>Manager</th>
                            <th>Year</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contests as $contest): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($contest['name']); ?></td>
                            <td><?php echo htmlspecialchars($contest['activity_name']); ?></td>
                            <td><?php echo htmlspecialchars($contest['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($contest['tournament_manager']); ?></td>
                            <td><?php echo $contest['year']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Categories Section -->
        <div id="categories-section" style="display: none;">
            <div class="form-card">
                <h3>Add New Category</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_category">
                    <div class="form-group">
                        <label>Category Name</label>
                        <input type="text" name="category_name" required>
                    </div>
                    <div class="form-group">
                        <label>Category Type</label>
                        <select name="category_type" required>
                            <option value="general">General</option>
                            <option value="sports">Sports & Athletic</option>
                            <option value="cultural_arts">Culture & Arts</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">Add Category</button>
                </form>
            </div>
            
            <div class="data-table">
                <h3>Existing Categories</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                            <td>
                                <?php 
                                if ($category['category_type'] == 'sports') echo 'Sports & Athletic';
                                elseif ($category['category_type'] == 'cultural_arts') echo 'Culture & Arts';
                                else echo 'General';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Participations Section -->
        <div id="participations-section" style="display: none;">
            <div class="data-table">
                <h3>Recent Participations</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Contest</th>
                            <th>Ranking</th>
                            <th>Role</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentParticipations as $participation): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($participation['first_name'] . ' ' . $participation['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($participation['course']); ?></td>
                            <td><?php echo htmlspecialchars($participation['contest_name']); ?></td>
                            <td><?php echo str_replace('_', ' ', $participation['ranking']); ?></td>
                            <td><?php echo htmlspecialchars($participation['role']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($participation['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.btn-warning {
    background: #ffc107;
    color: #212529;
    padding: 5px 10px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}
.btn-success {
    background: #28a745;
    color: white;
    padding: 5px 10px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}
.announcement-preview {
    transition: transform 0.2s;
}
.announcement-preview:hover {
    transform: translateX(5px);
}
</style>

<script>
function showSection(section) {
    // Hide all sections
    document.getElementById('overview-section').style.display = 'none';
    document.getElementById('announcements-section').style.display = 'none';
    document.getElementById('users-section').style.display = 'none';
    document.getElementById('activities-section').style.display = 'none';
    document.getElementById('contests-section').style.display = 'none';
    document.getElementById('categories-section').style.display = 'none';
    document.getElementById('participations-section').style.display = 'none';
    
    // Show selected section
    document.getElementById(section + '-section').style.display = 'block';
    
    // Update active class on nav links
    const links = document.querySelectorAll('.sidebar-nav a');
    links.forEach(link => link.classList.remove('active'));
    if (event && event.target) {
        const clickedLink = event.target.closest('a');
        if (clickedLink) clickedLink.classList.add('active');
    }
}
</script>
</body>
</html>