<?php
require_once 'db.php';

echo "<h2>🔧 Reset Admin Password</h2>";

// Check if admin exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
$stmt->execute();
$admin = $stmt->fetch();

if (!$admin) {
    echo "<p style='color: red;'>❌ No admin user found!</p>";
    echo "<p>Creating admin user...</p>";
    
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users 
        (id_number, username, email, password, first_name, last_name, user_type) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute(['ADMIN-001', 'admin', 'admin@system.com', $hashed_password, 'System', 'Administrator', 'admin'])) {
        echo "<p style='color: green;'>✅ Admin user created!</p>";
    }
} else {
    echo "<p>✅ Admin user found: " . htmlspecialchars($admin['username']) . "</p>";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_password = $_POST['password'] ?? 'admin123';
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        if ($stmt->execute([$hashed_password])) {
            echo "<div style='background: #d4edda; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
            echo "<h3>✅ Password updated!</h3>";
            echo "<p><strong>Username:</strong> admin</p>";
            echo "<p><strong>New Password:</strong> $new_password</p>";
            echo "</div>";
        }
    }
    
    echo '<form method="POST" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 10px;">';
    echo '<label>New Password: </label>';
    echo '<input type="text" name="password" value="admin123">';
    echo '<button type="submit" style="margin-left: 10px; padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 5px;">Reset Password</button>';
    echo '</form>';
}

echo '<p><a href="login.php">Go to Login</a></p>';
?>