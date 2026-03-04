<?php
// navbar.php - Navigation bar for non-dashboard pages
if (!isset($current_user) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
}
?>
<nav style="background: white; padding: 15px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center;">
    <div style="font-size: 24px; font-weight: 800; color: #0a2540;">
        <span style="color: #10b981;">TALENTRIX</span>
    </div>
    
    <div style="display: flex; gap: 20px; align-items: center;">
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="index.php" style="color: #4a5568; text-decoration: none;">Home</a>
            
            <?php if($_SESSION['user_type'] === 'admin'): ?>
                <a href="dashboard.php" style="color: #4a5568; text-decoration: none;">Dashboard</a>
                <a href="users.php" style="color: #4a5568; text-decoration: none;">Users</a>
            <?php elseif($_SESSION['user_type'] === 'coach'): ?>
                <a href="coach_dashboard.php" style="color: #4a5568; text-decoration: none;">Dashboard</a>
            <?php elseif($_SESSION['user_type'] === 'student'): ?>
                <a href="student_dashboard.php" style="color: #4a5568; text-decoration: none;">Dashboard</a>
            <?php endif; ?>
            
            <a href="profile.php" style="color: #4a5568; text-decoration: none;">Profile</a>
            <a href="logout.php" style="background: #ef4444; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none;">Logout</a>
        <?php else: ?>
            <a href="login.php" style="color: #4a5568; text-decoration: none;">Login</a>
            <a href="register.php" style="background: #10b981; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none;">Register</a>
        <?php endif; ?>
    </div>
</nav>