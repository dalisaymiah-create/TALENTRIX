<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_system'])) {
        // Update system settings
        $_SESSION['success'] = "System settings updated successfully!";
    }
    
    if (isset($_POST['add_sport'])) {
        $sport_name = $_POST['sport_name'];
        $category = $_POST['category'];
        
        $stmt = $pdo->prepare("INSERT INTO sports (sport_name, category) VALUES (?, ?)");
        if ($stmt->execute([$sport_name, $category])) {
            $_SESSION['success'] = "Sport added successfully!";
        }
    }
    
    if (isset($_POST['add_troupe'])) {
        $troupe_name = $_POST['troupe_name'];
        $dance_style = $_POST['dance_style'];
        
        $stmt = $pdo->prepare("INSERT INTO dance_troupes (troupe_name, dance_style) VALUES (?, ?)");
        if ($stmt->execute([$troupe_name, $dance_style])) {
            $_SESSION['success'] = "Dance troupe added successfully!";
        }
    }
    
    header('Location: settings.php');
    exit();
}

// Get all sports
$sports = $pdo->query("SELECT * FROM sports ORDER BY sport_name")->fetchAll();

// Get all dance troupes
$troupes = $pdo->query("SELECT * FROM dance_troupes ORDER BY troupe_name")->fetchAll();

// Get system stats
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_students' => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'total_coaches' => $pdo->query("SELECT COUNT(*) FROM coaches")->fetchColumn(),
    'total_teams' => $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn(),
    'total_troupes' => $pdo->query("SELECT COUNT(*) FROM dance_troupes")->fetchColumn(),
    'total_sports' => $pdo->query("SELECT COUNT(*) FROM sports")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - TALENTRIX</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .settings-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .settings-card h3 {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f2f5;
            color: #0a2540;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .stat-label {
            color: #718096;
        }
        
        .stat-value {
            font-weight: 700;
            color: #10b981;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
        }
        
        .btn-submit {
            background: #10b981;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        
        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .alert {
            padding: 15px;
            background: #c6f6d5;
            color: #22543d;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="settings-container">
                <h1>System Settings</h1>
                
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <div class="settings-grid">
                    <!-- System Stats -->
                    <div class="settings-card">
                        <h3><i class="fas fa-chart-pie"></i> System Statistics</h3>
                        <div class="stat-item">
                            <span class="stat-label">Total Users</span>
                            <span class="stat-value"><?php echo $stats['total_users']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Students</span>
                            <span class="stat-value"><?php echo $stats['total_students']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Coaches</span>
                            <span class="stat-value"><?php echo $stats['total_coaches']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Teams</span>
                            <span class="stat-value"><?php echo $stats['total_teams']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Dance Troupes</span>
                            <span class="stat-value"><?php echo $stats['total_troupes']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Sports</span>
                            <span class="stat-value"><?php echo $stats['total_sports']; ?></span>
                        </div>
                    </div>
                    
                    <!-- Add Sport -->
                    <div class="settings-card">
                        <h3><i class="fas fa-futbol"></i> Add New Sport</h3>
                        <form method="POST">
                            <input type="hidden" name="add_sport" value="1">
                            
                            <div class="form-group">
                                <label>Sport Name</label>
                                <input type="text" name="sport_name" required placeholder="e.g., Basketball">
                            </div>
                            
                            <div class="form-group">
                                <label>Category</label>
                                <select name="category" required>
                                    <option value="Team Sport">Team Sport</option>
                                    <option value="Individual Sport">Individual Sport</option>
                                    <option value="Martial Arts">Martial Arts</option>
                                    <option value="Performing Arts">Performing Arts</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn-submit">Add Sport</button>
                        </form>
                        
                        <h4 style="margin: 20px 0 10px;">Existing Sports</h4>
                        <?php foreach($sports as $sport): ?>
                            <div class="list-item">
                                <span><?php echo htmlspecialchars($sport['sport_name']); ?></span>
                                <span style="color: #718096;"><?php echo $sport['category']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Add Dance Troupe -->
                    <div class="settings-card">
                        <h3><i class="fas fa-music"></i> Add Dance Troupe</h3>
                        <form method="POST">
                            <input type="hidden" name="add_troupe" value="1">
                            
                            <div class="form-group">
                                <label>Troupe Name</label>
                                <input type="text" name="troupe_name" required placeholder="e.g., BISU Street Dancers">
                            </div>
                            
                            <div class="form-group">
                                <label>Dance Style</label>
                                <input type="text" name="dance_style" required placeholder="e.g., Street Dance, Folk">
                            </div>
                            
                            <button type="submit" class="btn-submit">Add Troupe</button>
                        </form>
                        
                        <h4 style="margin: 20px 0 10px;">Existing Troupes</h4>
                        <?php foreach($troupes as $troupe): ?>
                            <div class="list-item">
                                <span><?php echo htmlspecialchars($troupe['troupe_name']); ?></span>
                                <span style="color: #718096;"><?php echo $troupe['dance_style']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- System Configuration -->
                    <div class="settings-card">
                        <h3><i class="fas fa-cog"></i> System Configuration</h3>
                        <form method="POST">
                            <input type="hidden" name="update_system" value="1">
                            
                            <div class="form-group">
                                <label>System Name</label>
                                <input type="text" value="TALENTRIX" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label>Institution</label>
                                <input type="text" value="Bohol Island State University" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label>Default Campus</label>
                                <select>
                                    <option>Tagbilaran Campus</option>
                                    <option>Candijay Campus</option>
                                    <option>Balilihan Campus</option>
                                    <option>Bilar Campus</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Registration</label>
                                <select>
                                    <option>Open (Anyone can register)</option>
                                    <option>Admin Approval Required</option>
                                    <option>Closed</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn-submit">Save Configuration</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>