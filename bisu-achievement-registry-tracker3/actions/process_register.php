<?php
require_once '../config/database.php';
require_once '../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../register.php");
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$usertype = $_POST['usertype'] ?? '';

// Validation
if (empty($email) || empty($password) || empty($usertype)) {
    header("Location: ../register.php?error=" . urlencode("Please fill in all required fields"));
    exit();
}

if ($password !== $confirm_password) {
    header("Location: ../register.php?error=" . urlencode("Passwords do not match"));
    exit();
}

if (strlen($password) < 6) {
    header("Location: ../register.php?error=" . urlencode("Password must be at least 6 characters"));
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../register.php?error=" . urlencode("Invalid email format"));
    exit();
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM \"User\" WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        header("Location: ../register.php?error=" . urlencode("Email already registered"));
        exit();
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Insert user - handle admin_type for admin users
    $admin_type = null;
    $first_name = '';
    $last_name = '';
    
    if ($usertype === 'admin') {
        $admin_type = $_POST['admin_type'] ?? null;
        $first_name = trim($_POST['admin_first_name'] ?? '');
        $last_name = trim($_POST['admin_last_name'] ?? '');
        
        // Validate admin_type
        if (empty($admin_type) || !in_array($admin_type, ['sports', 'cultural'])) {
            $pdo->rollBack();
            header("Location: ../register.php?error=" . urlencode("Please select a valid admin type"));
            exit();
        }
        
        if (empty($first_name) || empty($last_name)) {
            $pdo->rollBack();
            header("Location: ../register.php?error=" . urlencode("Please fill in all admin information"));
            exit();
        }
    } 
    elseif ($usertype === 'student') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        
        if (empty($first_name) || empty($last_name)) {
            $pdo->rollBack();
            header("Location: ../register.php?error=" . urlencode("Please fill in your first name and last name"));
            exit();
        }
    }
    elseif ($usertype === 'coach') {
        $first_name = trim($_POST['coach_first_name'] ?? '');
        $last_name = trim($_POST['coach_last_name'] ?? '');
        
        if (empty($first_name) || empty($last_name)) {
            $pdo->rollBack();
            header("Location: ../register.php?error=" . urlencode("Please fill in your first name and last name"));
            exit();
        }
    }
    
    // Insert into User table with first_name and last_name
    $stmt = $pdo->prepare("INSERT INTO \"User\" (email, password, usertype, admin_type, first_name, last_name, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW()) RETURNING id");
    $stmt->execute([$email, $hashed_password, $usertype, $admin_type, $first_name, $last_name]);
    $user_id = $stmt->fetchColumn();
    
    // Handle specific user type data (only additional fields, no first_name/last_name)
    if ($usertype === 'student') {
        $course = $_POST['course'] ?? '';
        $yr_level = $_POST['yr_level'] ?? '';
        
        // Validate student fields
        if (empty($course) || empty($yr_level)) {
            $pdo->rollBack();
            header("Location: ../register.php?error=" . urlencode("Please fill in all student information"));
            exit();
        }
        
        // Determine college based on course
        $college = '';
        $bsed_courses = ['BSED-Math', 'BSED-English', 'BSED-Filipino', 'BSED-Science'];
        
        if ($course === 'BEED' || in_array($course, $bsed_courses)) {
            $college = 'CTE';
        } elseif ($course === 'BSCS' || $course === 'BSES') {
            $college = 'COS';
        } elseif ($course === 'BSHM' || $course === 'BSOAD') {
            $college = 'CBM';
        } elseif ($course === 'BSF' || $course === 'BSMB') {
            $college = 'CFMS';
        } else {
            $college = 'COS'; // Default fallback
        }
        
        $stmt = $pdo->prepare("INSERT INTO student (course, yr_level, college, user_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$course, $yr_level, $college, $user_id]);
        
    } elseif ($usertype === 'coach') {
        // Coach table only needs user_id
        $stmt = $pdo->prepare("INSERT INTO coach (user_id) VALUES (?)");
        $stmt->execute([$user_id]);
    }
    
    $pdo->commit();
    
    // Show appropriate success message based on user type
    if ($usertype === 'admin') {
        $admin_type_name = ($admin_type === 'sports') ? 'Sports Admin' : 'Cultural Admin';
        header("Location: ../login.php?success=" . urlencode("{$admin_type_name} account created successfully! Please login with your credentials."));
    } else {
        header("Location: ../login.php?success=" . urlencode("Registration successful! Please login."));
    }
    exit();
    
} catch(PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Registration error: " . $e->getMessage());
    header("Location: ../register.php?error=" . urlencode("Registration failed. Please try again."));
    exit();
}
?>