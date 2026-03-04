<?php
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if already approved
if ($_SESSION['status'] == 'approved') {
    if ($_SESSION['role'] == 'student') {
        header('Location: student/dashboard.php');
    } elseif ($_SESSION['role'] == 'coach') {
        header('Location: coach/dashboard.php');
    }
    exit();
}

// Get user details
$user_id = $_SESSION['user_id'];
$sql = "SELECT u.*, s.sport, s.position 
        FROM users u 
        LEFT JOIN students s ON u.id = s.user_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approval - Talentrix</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="pending-container">
        <div class="pending-card">
            <div class="loader"></div>
            
            <h1 style="color: #ff9800; margin: 1rem 0;">⏳ Pending Approval</h1>
            
            <p style="color: #666; line-height: 1.8; margin-bottom: 2rem;">
                Hello <strong><?php echo $user['name']; ?></strong>,<br>
                Your account is currently waiting for coach approval.<br>
                You'll receive access to your dashboard once approved.
            </p>
            
            <div style="background: #f5f5f5; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; text-align: left;">
                <h3 style="margin-bottom: 1rem; color: #333;">Application Details:</h3>
                <p><strong>Sport:</strong> <?php echo $user['sport'] ?? 'Not specified'; ?></p>
                <p><strong>Position:</strong> <?php echo $user['position'] ?? 'Not specified'; ?></p>
                <p><strong>Applied:</strong> <?php echo date('F d, Y h:i A'); ?></p>
                <p><strong>Status:</strong> <span style="color: #ff9800; font-weight: bold;">Pending Review</span></p>
            </div>
            
            <p style="color: #999; font-size: 0.9rem;">
                If this takes too long, please contact your coach or administrator.
            </p>
            
            <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
                <a href="logout.php" style="padding: 0.8rem 2rem; background: #ff4757; color: white; text-decoration: none; border-radius: 5px;">Log Out</a>
                <button onclick="location.reload()" style="padding: 0.8rem 2rem; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;">Refresh</button>
            </div>
        </div>
    </div>
</body>
</html>