<?php
require_once '../config/database.php';
require_once '../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header("Location: ../login.php?error=" . urlencode("Please fill in all fields"));
    exit();
}

try {
    // Get user from database
    $stmt = $pdo->prepare("SELECT * FROM \"User\" WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['usertype'] = $user['usertype'];
        $_SESSION['admin_type'] = $user['admin_type'];
        $_SESSION['first_name'] = $user['first_name'] ?? '';
        $_SESSION['last_name'] = $user['last_name'] ?? '';
        
        // Redirect based on user type
        $dashboard = getDashboardPath($user['usertype'], $user['admin_type']);
        header("Location: ../$dashboard");
        exit();
    } else {
        header("Location: ../login.php?error=" . urlencode("Invalid email or password"));
        exit();
    }
} catch(PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    header("Location: ../login.php?error=" . urlencode("Login failed. Please try again."));
    exit();
}
?>