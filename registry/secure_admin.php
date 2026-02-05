<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get requested file
$file = $_GET['file'] ?? '';
$allowed_files = [
    'tables.html', 'charts.html', 'buttons.html', 'cards.html',
    'utilities-animation.html', 'utilities-border.html', 
    'utilities-color.html', 'utilities-other.html'
];

if (in_array($file, $allowed_files) && file_exists($file)) {
    // Add admin header
    echo '<div style="background: #667eea; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center;">';
    echo '<h3>Admin Panel - ' . htmlspecialchars($file) . '</h3>';
    echo '<div>';
    echo '<a href="dashboard.php" style="color: white; margin-right: 15px;">🏠 Dashboard</a>';
    echo '<a href="logout.php" style="color: white;">🚪 Logout</a>';
    echo '</div></div>';
    
    // Display the file
    readfile($file);
} else {
    header('Location: dashboard.php');
}
?>