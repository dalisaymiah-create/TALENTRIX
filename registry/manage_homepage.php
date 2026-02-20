<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // ADD ACHIEVEMENT
        if ($_POST['action'] === 'add_achievement') {
            $stmt = $pdo->prepare("INSERT INTO homepage_content (section_type, title, content, badge, post_date, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['section_type'],
                $_POST['title'],
                $_POST['content'],
                $_POST['badge'],
                $_POST['post_date'],
                $_SESSION['user_id']
            ]);
            $success = "Achievement added successfully!";
        }
        
        // DELETE ACHIEVEMENT
        if ($_POST['action'] === 'delete_item') {
            $stmt = $pdo->prepare("DELETE FROM homepage_content WHERE id = ?");
            $stmt->execute([$_POST['item_id']]);
            $success = "Item deleted successfully!";
        }
        
        // ADD EVENT
        if ($_POST['action'] === 'add_event') {
            $stmt = $pdo->prepare("INSERT INTO upcoming_events (event_title, event_date, event_description, event_type, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['event_title'],
                $_POST['event_date'],
                $_POST['event_description'],
                $_POST['event_type'],
                $_SESSION['user_id']
            ]);
            $success = "Event added successfully!";
        }
        
        // DELETE EVENT
        if ($_POST['action'] === 'delete_event') {
            $stmt = $pdo->prepare("DELETE FROM upcoming_events WHERE id = ?");
            $stmt->execute([$_POST['event_id']]);
            $success = "Event deleted successfully!";
        }
    }
}

// Get all content
$achievements = $pdo->query("SELECT * FROM homepage_content ORDER BY post_date DESC")->fetchAll();
$events = $pdo->query("SELECT * FROM upcoming_events ORDER BY event_date ASC")->fetchAll();

// Get current user
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Manage Homepage</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Admin Manage Page Styles */
        .manage-container {
            padding: 30px;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .manage-header {
            background: linear-gradient(135deg, #0a2540 0%, #1a365d 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        
        .tab {
            padding: 12px 24px;
            background: white;
            border: none;
            border-radius: 8px 8px 0 0;
            font-weight: 600;
            color: #4a5568;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .tab.active {
            background: #10b981;
            color: white;
        }
        
        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            display: none;
        }
        
        .section.active {
            display: block;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .btn-add {
            background: #10b981;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .content-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            position: relative;
        }
        
        .card-badge {
            position: absolute;
            top: -10px;
            right: 20px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-athletics { background: #8B1E3F; color: white; }
        .badge-dance { background: #FFB347; color: #1e3e5c; }
        
        .card-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            animation: slideIn 0.3s;
            z-index: 2000;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-crown"></i> TALENTRIX Admin</h2>
                <p>Welcome, <?php echo htmlspecialchars($current_user['first_name']); ?></p>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-chart-bar"></i> Dashboard</a></li>
                    <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                    <li class="active"><a href="manage_homepage.php"><i class="fas fa-home"></i> Manage Homepage</a></li>
                    <li><a href="../index.php"><i class="fas fa-eye"></i> View Homepage</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="manage-header">
                <h1>📝 MANAGE HOMEPAGE CONTENT</h1>
                <p>Add, edit, or remove achievements and events for athletes and dancers</p>
            </div>

            <?php if(isset($success)): ?>
                <div class="notification">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="showTab('achievements')">🏆 Achievements</button>
                <button class="tab" onclick="showTab('events')">📅 Events</button>
            </div>

            <!-- Achievements Section -->
            <div id="achievements-section" class="section active">
                <div class="section-header">
                    <h2>Athlete & Dancer Achievements</h2>
                    <button class="btn-add" onclick="openModal('add-achievement-modal')">
                        <i class="fas fa-plus"></i> Add Achievement
                    </button>
                </div>
                
                <div class="content-grid">
                    <?php foreach($achievements as $item): ?>
                    <div class="content-card">
                        <span class="card-badge <?php echo $item['section_type'] == 'athletics' ? 'badge-athletics' : 'badge-dance'; ?>">
                            <?php echo htmlspecialchars($item['badge']); ?>
                        </span>
                        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                        <p style="color: #718096; margin: 10px 0;">
                            <i class="far fa-calendar"></i> <?php echo date('F d, Y', strtotime($item['post_date'])); ?>
                        </p>
                        <p><?php echo htmlspecialchars(substr($item['content'], 0, 100)) . '...'; ?></p>
                        <div class="card-actions">
                            <form method="POST" onsubmit="return confirm('Delete this achievement?')">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="btn-delete">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Events Section -->
            <div id="events-section" class="section">
                <div class="section-header">
                    <h2>Upcoming Events</h2>
                    <button class="btn-add" onclick="openModal('add-event-modal')">
                        <i class="fas fa-plus"></i> Add Event
                    </button>
                </div>
                
                <div class="content-grid">
                    <?php foreach($events as $event): ?>
                    <div class="content-card">
                        <h3><?php echo htmlspecialchars($event['event_title']); ?></h3>
                        <p style="color: #10b981; font-weight: 600;">
                            📅 <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                        </p>
                        <p><?php echo htmlspecialchars($event['event_description']); ?></p>
                        <p><strong>Type:</strong> <?php echo ucfirst($event['event_type']); ?></p>
                        <div class="card-actions">
                            <form method="POST" onsubmit="return confirm('Delete this event?')">
                                <input type="hidden" name="action" value="delete_event">
                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                <button type="submit" class="btn-delete">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Achievement Modal -->
    <div id="add-achievement-modal" class="modal">
        <div class="modal-content">
            <h2>Add New Achievement</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_achievement">
                
                <div class="form-group">
                    <label>Type:</label>
                    <select name="section_type" required>
                        <option value="athletics">Athletics Achievement</option>
                        <option value="dance">Dance Achievement</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Title:</label>
                    <input type="text" name="title" required placeholder="e.g., BISU Candijay Athletes Win Championship">
                </div>
                
                <div class="form-group">
                    <label>Badge:</label>
                    <select name="badge" required>
                        <option value="ATHLETICS">ATHLETICS</option>
                        <option value="DANCE">DANCE</option>
                        <option value="CHAMPION">CHAMPION</option>
                        <option value="RECORD">RECORD BREAKER</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Date:</label>
                    <input type="date" name="post_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="content" required rows="5" placeholder="Write the achievement details..."></textarea>
                </div>
                
                <button type="submit" class="btn-add" style="width: 100%;">Publish Achievement</button>
                <button type="button" onclick="closeModal('add-achievement-modal')" style="margin-top: 10px; width: 100%; padding: 12px; background: #e2e8f0; border: none; border-radius: 8px;">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Add Event Modal -->
    <div id="add-event-modal" class="modal">
        <div class="modal-content">
            <h2>Add Upcoming Event</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_event">
                
                <div class="form-group">
                    <label>Event Type:</label>
                    <select name="event_type" required>
                        <option value="athletics">Athletics Event</option>
                        <option value="dance">Dance Event</option>
                        <option value="both">Both</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Event Title:</label>
                    <input type="text" name="event_title" required placeholder="e.g., Regional Athletics Meet">
                </div>
                
                <div class="form-group">
                    <label>Event Date:</label>
                    <input type="date" name="event_date" required>
                </div>
                
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="event_description" required rows="3" placeholder="Event details..."></textarea>
                </div>
                
                <button type="submit" class="btn-add" style="width: 100%;">Add Event</button>
                <button type="button" onclick="closeModal('add-event-modal')" style="margin-top: 10px; width: 100%; padding: 12px; background: #e2e8f0; border: none; border-radius: 8px;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(tabName + '-section').classList.add('active');
            event.target.classList.add('active');
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
        
        // Auto-hide notification
        setTimeout(() => {
            const notification = document.querySelector('.notification');
            if (notification) {
                notification.style.display = 'none';
            }
        }, 3000);
    </script>
</body>
</html>