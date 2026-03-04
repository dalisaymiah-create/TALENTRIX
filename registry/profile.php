<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    
    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
    if ($stmt->execute([$first_name, $last_name, $email, $user_id])) {
        $_SESSION['success'] = "Profile updated successfully!";
        $_SESSION['full_name'] = $first_name . ' ' . $last_name;
        header('Location: profile.php');
        exit();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 8) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);
                $_SESSION['success'] = "Password changed successfully!";
            } else {
                $_SESSION['error'] = "Password must be at least 8 characters.";
            }
        } else {
            $_SESSION['error'] = "New passwords do not match.";
        }
    } else {
        $_SESSION['error'] = "Current password is incorrect.";
    }
    
    header('Location: profile.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - TALENTRIX</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
        }
        
        .profile-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .profile-tab {
            padding: 15px 25px;
            background: none;
            border: none;
            font-weight: 600;
            color: #718096;
            cursor: pointer;
            position: relative;
        }
        
        .profile-tab.active {
            color: #10b981;
        }
        
        .profile-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #10b981;
        }
        
        .profile-section {
            display: none;
        }
        
        .profile-section.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #4a5568;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .form-group input:focus {
            border-color: #10b981;
            outline: none;
        }
        
        .btn-save {
            background: #10b981;
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        
        .info-row {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-label {
            width: 150px;
            font-weight: 600;
            color: #4a5568;
        }
        
        .info-value {
            flex: 1;
            color: #2d3748;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #c53030;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="profile-container">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
            </div>
            <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
            <p><?php echo ucfirst($user['user_type']); ?> • <?php echo htmlspecialchars($user['id_number']); ?></p>
        </div>
        
        <div class="profile-tabs">
            <button class="profile-tab active" onclick="showTab('info')">Profile Info</button>
            <button class="profile-tab" onclick="showTab('edit')">Edit Profile</button>
            <button class="profile-tab" onclick="showTab('password')">Change Password</button>
        </div>
        
        <!-- Profile Info Section -->
        <div id="info-section" class="profile-section active">
            <div class="info-row">
                <div class="info-label">First Name</div>
                <div class="info-value"><?php echo htmlspecialchars($user['first_name']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Last Name</div>
                <div class="info-value"><?php echo htmlspecialchars($user['last_name']); ?></div>
            </div>
            <?php if(!empty($user['middle_name'])): ?>
            <div class="info-row">
                <div class="info-label">Middle Name</div>
                <div class="info-value"><?php echo htmlspecialchars($user['middle_name']); ?></div>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <div class="info-label">Email</div>
                <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">ID Number</div>
                <div class="info-value"><?php echo htmlspecialchars($user['id_number']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Institution</div>
                <div class="info-value"><?php echo htmlspecialchars($user['institution'] ?? 'Bohol Island State University'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Campus</div>
                <div class="info-value"><?php echo htmlspecialchars($user['campus'] ?? 'Not specified'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Account Status</div>
                <div class="info-value">
                    <?php if($user['is_verified']): ?>
                        <span style="color: #10b981;">✓ Verified</span>
                    <?php else: ?>
                        <span style="color: #f59e0b;">⏳ Pending Verification</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Member Since</div>
                <div class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div>
            </div>
        </div>
        
        <!-- Edit Profile Section -->
        <div id="edit-section" class="profile-section">
            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                
                <button type="submit" class="btn-save">Update Profile</button>
            </form>
        </div>
        
        <!-- Change Password Section -->
        <div id="password-section" class="profile-section">
            <form method="POST">
                <input type="hidden" name="change_password" value="1">
                
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label>New Password (min. 8 characters)</label>
                    <input type="password" name="new_password" required>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn-save">Change Password</button>
            </form>
        </div>
    </div>
    
    <script>
        function showTab(tab) {
            document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.profile-section').forEach(s => s.classList.remove('active'));
            
            document.querySelector(`.profile-tab[onclick="showTab('${tab}')"]`).classList.add('active');
            document.getElementById(tab + '-section').classList.add('active');
        }
    </script>
</body>
</html>