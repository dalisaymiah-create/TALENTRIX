<?php
// sidebar.php - Sidebar for admin dashboard
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-crown"></i> TALENTRIX</h2>
        <p class="admin-info"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></p>
    </div>
    
    <nav class="sidebar-nav">
        <ul>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <a href="dashboard.php"><i class="fas fa-chart-bar"></i> <span>Dashboard</span></a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <a href="users.php"><i class="fas fa-users"></i> <span>Manage Users</span></a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_homepage.php' ? 'active' : ''; ?>">
                <a href="manage_homepage.php"><i class="fas fa-home"></i> <span>Manage Homepage</span></a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_images.php' ? 'active' : ''; ?>">
                <a href="manage_images.php"><i class="fas fa-images"></i> <span>Manage Images</span></a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a>
            </li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <a href="profile.php"><i class="fas fa-user"></i> <span>Profile</span></a>
            </li>
            <li class="divider"></li>
            <li><a href="index.php"><i class="fas fa-eye"></i> <span>View Homepage</span></a></li>
            <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </nav>
</div>