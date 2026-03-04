<?php
// register.php - WITH AUTOMATIC TABLE CREATION
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

// Create dance_coaches table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dance_coaches (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT UNIQUE NOT NULL,
            employee_id VARCHAR(50),
            dance_specialization VARCHAR(100) NOT NULL,
            dance_troupe_name VARCHAR(100),
            years_experience INT,
            achievements TEXT,
            certifications VARCHAR(255),
            campus VARCHAR(100),
            bio TEXT,
            date_hired DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    // Table might already exist or other error - continue
}

// If user is already logged in, redirect
if(isset($_SESSION['user_id'])) {
    if($_SESSION['user_type'] === 'admin') {
        header('Location: admin_pages.php?page=dashboard');
    } elseif($_SESSION['user_type'] === 'sport_coach') {
        header('Location: coach_dashboard.php');
    } elseif($_SESSION['user_type'] === 'dance_coach') {
        header('Location: dance_coach_dashboard.php');
    } else {
        header('Location: student_dashboard.php');
    }
    exit();
}

$error = '';

// Get sports from database
$sports = $pdo->query("SELECT id, sport_name FROM sports WHERE is_active = 1 ORDER BY sport_name")->fetchAll();

// Get dance troupes
$dance_troupes = $pdo->query("SELECT id, troupe_name FROM dance_troupes WHERE is_active = 1 ORDER BY troupe_name")->fetchAll();

// Get sports coaches (for athlete approval) - with error handling
$sports_coaches = [];
try {
    $sports_coaches = $pdo->query("
        SELECT c.id, c.user_id, u.first_name, u.last_name, c.primary_sport 
        FROM coaches c 
        JOIN users u ON c.user_id = u.id 
        WHERE u.status = 'active'
        ORDER BY c.primary_sport, u.first_name
    ")->fetchAll();
} catch (Exception $e) {
    $sports_coaches = [];
}

// Get dance coaches (for dancer approval) - with error handling
$dance_coaches = [];
try {
    $dance_coaches = $pdo->query("
        SELECT dc.id, dc.user_id, u.first_name, u.last_name, dc.dance_specialization 
        FROM dance_coaches dc 
        JOIN users u ON dc.user_id = u.id 
        WHERE u.status = 'active'
        ORDER BY u.first_name
    ")->fetchAll();
} catch (Exception $e) {
    $dance_coaches = [];
}

// Group sports coaches by sport
$coaches_by_sport = [];
foreach($sports_coaches as $coach) {
    $sport = $coach['primary_sport'] ?? 'Unassigned';
    if(!isset($coaches_by_sport[$sport])) {
        $coaches_by_sport[$sport] = [];
    }
    $coaches_by_sport[$sport][] = $coach;
}

// Student data
$year_levels = [1, 2, 3, 4];
$athlete_categories = ['varsity', 'club', 'intramural'];
$dance_roles = ['Member', 'Lead Dancer', 'Choreographer'];

// Sport positions mapping
$sportPositions = [
    'Basketball' => [
        'label' => 'Position',
        'options' => ['Point Guard', 'Shooting Guard', 'Small Forward', 'Power Forward', 'Center']
    ],
    'Volleyball' => [
        'label' => 'Position',
        'options' => ['Setter', 'Outside Hitter', 'Opposite Hitter', 'Middle Blocker', 'Libero']
    ],
    'Football' => [
        'label' => 'Position',
        'options' => ['Goalkeeper', 'Defender', 'Midfielder', 'Forward', 'Striker']
    ],
    'Swimming' => [
        'label' => 'Event',
        'options' => ['Freestyle', 'Backstroke', 'Breaststroke', 'Butterfly', 'Individual Medley']
    ],
    'Arnis' => [
        'label' => 'Event / Anyo',
        'options' => ['Anyo (Forms)', 'Labanan (Full Contact)', 'Labanan (Light Contact)', 'Espada y Daga', 'Solo Baston', 'Double Baston']
    ],
    'Chess' => [
        'label' => 'Category',
        'options' => ['Opening Specialist', 'Tactician', 'Endgame Specialist', 'Blitz', 'Standard', 'Rapid']
    ],
    'Track and Field' => [
        'label' => 'Event',
        'options' => ['Sprinter', 'Long Distance', 'Jumper', 'Thrower', 'Hurdler', 'Race Walker']
    ],
    'Taekwondo' => [
        'label' => 'Category',
        'options' => ['Poomsae', 'Kyorugi (Sparring)', 'Breaking', 'Self-Defense']
    ],
    'Badminton' => [
        'label' => 'Category',
        'options' => ['Singles', 'Doubles', 'Mixed Doubles']
    ],
    'Table Tennis' => [
        'label' => 'Category',
        'options' => ['Singles', 'Doubles', 'Mixed Doubles']
    ]
];

// Colleges with courses
$colleges = [
    'CBM' => [
        'name' => 'College of Business and Management',
        'courses' => [
            'Bachelor of Science in Hospitality Management',
            'Bachelor of Science in Office Administration'
        ]
    ],
    'CTE' => [
        'name' => 'College of Teacher Education',
        'courses' => [
            'Bachelor of Elementary Education',
            'Bachelor of Secondary Education'
        ]
    ],
    'COS' => [
        'name' => 'College of Sciences',
        'courses' => [
            'Bachelor of Science in Computer Science',
            'Bachelor of Science in Environmental Science'
        ]
    ],
    'CFMS' => [
        'name' => 'College of Fisheries and Marine Sciences',
        'courses' => [
            'Bachelor of Science in Fisheries',
            'Bachelor of Science in Marine Biology'
        ]
    ]
];

// Campuses
$campuses = [
    'Tagbilaran Campus',
    'Candijay Campus',
    'Balilihan Campus',
    'Bilar Campus',
    'Calape Campus',
    'Clarin Campus'
];

// Specializations for coaches
$specializations = [
    'Basketball Coaching',
    'Volleyball Coaching',
    'Football Coaching',
    'Swimming Coaching',
    'Track and Field Coaching',
    'Sports Science',
    'Physical Education'
];

$years_experience = range(0, 30);

// Dance specializations
$dance_specializations = [
    'Street Dance',
    'Hip Hop',
    'Contemporary',
    'Ballet',
    'Jazz',
    'Folk Dance',
    'Ballroom',
    'Cheerdance',
    'Modern Dance',
    'Traditional Dance',
    'Breakdance',
    'Latin Dance'
];

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'student';
    $id_number = trim($_POST['id_number'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    
    $errors = [];
    
    // Basic validation
    if(empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password) || empty($id_number)) {
        $errors[] = 'All required fields must be filled!';
    }
    
    if($password !== $confirm_password) {
        $errors[] = 'Passwords do not match!';
    }
    
    if(strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters!';
    }
    
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format!';
    }
    
    // Student validation
    if($user_type == 'student') {
        if(empty($_POST['student_type'])) {
            $errors[] = 'Please select student type (Athlete or Dancer)!';
        }
        
        if(!preg_match('/^\d{4}-\d{5}$/', $id_number)) {
            $errors[] = 'Student ID must be: YYYY-##### (e.g., 2023-00123)';
        }
        
        // For athletes, check if they selected a sport and coach
        if($_POST['student_type'] == 'athlete') {
            if(empty($_POST['primary_sport'])) {
                $errors[] = 'Please select your primary sport!';
            }
            if(empty($_POST['approving_coach_id'])) {
                $errors[] = 'Please select the coach who will approve your application!';
            }
        }
        
        // For dancers, check if they selected a troupe and coach
        if($_POST['student_type'] == 'dancer') {
            if(empty($_POST['dance_troupe'])) {
                $errors[] = 'Please select your dance troupe!';
            }
            if(empty($_POST['approving_dance_coach_id'])) {
                $errors[] = 'Please select the trainor who will approve your application!';
            }
        }
    }
    
    // Sports Coach validation
    if($user_type == 'sport_coach') {
        if(!preg_match('/^[A-Za-z]{2,4}-\d{4}\d{0,3}$/', $id_number)) {
            $errors[] = 'Coach ID must be: ABC-YYYY### (e.g., COA-2024001)';
        }
        $id_number = strtoupper($id_number);
        
        if(empty($_POST['primary_sport_coach'])) {
            $errors[] = 'Please select the sport you want to coach!';
        }
    }
    
    // Dance Coach validation
    if($user_type == 'dance_coach') {
        if(!preg_match('/^[A-Za-z]{2,4}-\d{4}\d{0,3}$/', $id_number)) {
            $errors[] = 'Dance Trainor ID must be: ABC-YYYY### (e.g., DAN-2024001)';
        }
        $id_number = strtoupper($id_number);
        
        if(empty($_POST['dance_specialization'])) {
            $errors[] = 'Please select your dance specialization!';
        }
        
        if(empty($_POST['dance_experience'])) {
            $errors[] = 'Please select your years of experience!';
        }
    }
    
    // Admin validation
    if($user_type == 'admin') {
        if(!preg_match('/^ADMIN-\d{3}$/i', $id_number)) {
            $errors[] = 'Admin ID must be: ADMIN-### (e.g., ADMIN-001)';
        }
        $id_number = strtoupper($id_number);
    }
    
    // Check if email exists
    if(empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? OR id_number = ?");
        $stmt->execute([$email, $username, $id_number]);
        if($stmt->rowCount() > 0) {
            $errors[] = 'Email, username, or ID number already exists!';
        }
    }
    
    if(empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Set status based on user type
            // Coaches and Admins are automatically active
            // Students need approval
            if($user_type == 'student') {
                $status = 'pending';
                $is_verified = 0;
            } else {
                // Sport Coach, Dance Coach, Admin - automatically active
                $status = 'active';
                $is_verified = 1;
            }
            
            // Insert into users
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, id_number, first_name, middle_name, last_name, 
                                  institution, user_type, is_verified, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Bohol Island State University', ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $username, $email, $hashed_password, $id_number,
                $first_name, $middle_name, $last_name,
                $user_type, $is_verified, $status
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Insert into specific tables based on user type
            if($user_type == 'student') {
                $student_type = $_POST['student_type'] ?? 'athlete';
                
                $stmt2 = $pdo->prepare("
                    INSERT INTO students (
                        user_id, student_type, college, course, year_level, section,
                        primary_sport, secondary_sport, athlete_category, primary_position, jersey_number,
                        dance_troupe, dance_role, date_registered, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())
                ");
                
                $stmt2->execute([
                    $user_id,
                    $student_type,
                    $_POST['college'] ?? null,
                    $_POST['course'] ?? null,
                    !empty($_POST['year_level']) ? (int)$_POST['year_level'] : null,
                    $_POST['section'] ?? null,
                    $_POST['primary_sport'] ?? null,
                    $_POST['secondary_sport'] ?? null,
                    $_POST['athlete_category'] ?? null,
                    $_POST['primary_position'] ?? null,
                    !empty($_POST['jersey_number']) ? (int)$_POST['jersey_number'] : null,
                    $_POST['dance_troupe'] ?? null,
                    $_POST['dance_role'] ?? null
                ]);
                
                $student_id = $pdo->lastInsertId();
                
                // CREATE APPROVAL REQUEST based on student type
                if($student_type == 'athlete' && !empty($_POST['approving_coach_id'])) {
                    // Get coach's user_id from coaches table
                    $coach_stmt = $pdo->prepare("SELECT user_id FROM coaches WHERE id = ?");
                    $coach_stmt->execute([$_POST['approving_coach_id']]);
                    $coach = $coach_stmt->fetch();
                    
                    if($coach) {
                        // Create approval record
                        $approval_stmt = $pdo->prepare("
                            INSERT INTO approvals (
                                student_id, coach_id, team_id, approval_type, status, request_date
                            ) VALUES (?, ?, NULL, 'team', 'pending', NOW())
                        ");
                        $approval_stmt->execute([$student_id, $coach['user_id']]);
                    }
                }
                
                if($student_type == 'dancer' && !empty($_POST['approving_dance_coach_id'])) {
                    // Get dance coach's user_id from dance_coaches table
                    $dance_coach_stmt = $pdo->prepare("SELECT user_id FROM dance_coaches WHERE id = ?");
                    $dance_coach_stmt->execute([$_POST['approving_dance_coach_id']]);
                    $dance_coach = $dance_coach_stmt->fetch();
                    
                    if($dance_coach) {
                        // Create approval record
                        $approval_stmt = $pdo->prepare("
                            INSERT INTO approvals (
                                student_id, coach_id, troupe_id, approval_type, status, request_date
                            ) VALUES (?, ?, NULL, 'troupe', 'pending', NOW())
                        ");
                        $approval_stmt->execute([$student_id, $dance_coach['user_id']]);
                    }
                }
            }
            
            if($user_type == 'sport_coach') {
                $stmt2 = $pdo->prepare("
                    INSERT INTO coaches (
                        user_id, employee_id, department, position, specialization, years_experience, 
                        bio, primary_sport, date_hired, created_at
                    ) VALUES (?, ?, 'College of Sports', 'Sports Coach', ?, ?, ?, ?, CURDATE(), NOW())
                ");
                
                $stmt2->execute([
                    $user_id,
                    $id_number,
                    $_POST['specialization'] ?? '',
                    !empty($_POST['years_experience']) ? (int)$_POST['years_experience'] : 0,
                    $_POST['bio'] ?? '',
                    $_POST['primary_sport_coach'] ?? null
                ]);
            }
            
            if($user_type == 'dance_coach') {
                // Make sure dance_coaches table exists
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS dance_coaches (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT UNIQUE NOT NULL,
                        employee_id VARCHAR(50),
                        dance_specialization VARCHAR(100) NOT NULL,
                        dance_troupe_name VARCHAR(100),
                        years_experience INT,
                        achievements TEXT,
                        certifications VARCHAR(255),
                        campus VARCHAR(100),
                        bio TEXT,
                        date_hired DATE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )
                ");
                
                // Insert dance coach
                $stmt2 = $pdo->prepare("
                    INSERT INTO dance_coaches (
                        user_id, employee_id, dance_specialization, dance_troupe_name, 
                        years_experience, achievements, certifications, campus, bio, date_hired, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())
                ");
                
                $stmt2->execute([
                    $user_id,
                    $id_number,
                    $_POST['dance_specialization'],
                    $_POST['dance_troupe_name'] ?? null,
                    (int)$_POST['dance_experience'],
                    $_POST['dance_achievements'] ?? null,
                    $_POST['dance_certifications'] ?? null,
                    $_POST['dance_campus'] ?? null,
                    $_POST['dance_bio'] ?? null
                ]);
            }
            
            $pdo->commit();
            
            // Different success messages based on user type
            if($user_type == 'student') {
                $student_type = $_POST['student_type'] ?? '';
                if($student_type == 'athlete') {
                    $_SESSION['registration_success'] = 'Registration successful! Your application has been sent to the sports coach for approval. You will be notified once approved.';
                } else {
                    $_SESSION['registration_success'] = 'Registration successful! Your application has been sent to the dance trainor for approval. You will be notified once approved.';
                }
            } else {
                $_SESSION['registration_success'] = 'Registration successful! You can now login to your account.';
            }
            
            header('Location: login.php?registered=success');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
    
    if(!empty($errors)) {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Sign Up</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #0a2540 0%, #1a365d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .signup-container {
            width: 100%;
            max-width: 1200px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            display: flex;
        }
        
        /* Left Panel */
        .signup-left {
            flex: 1;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 40px 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            font-size: 40px;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }
        
        .logo h2 {
            font-size: 20px;
            font-weight: 600;
            opacity: 0.9;
        }
        
        .features {
            margin-top: 40px;
        }
        
        .feature {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .feature i {
            font-size: 20px;
            background: rgba(255, 255, 255, 0.2);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .feature-text h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .feature-text p {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .login-prompt {
            margin-top: auto;
            text-align: center;
            padding-top: 30px;
        }
        
        .login-prompt a {
            color: white;
            text-decoration: underline;
            font-weight: 600;
        }
        
        /* Right Panel */
        .signup-right {
            flex: 1.5;
            padding: 40px 30px;
            overflow-y: auto;
            max-height: 800px;
        }
        
        .signup-header {
            margin-bottom: 25px;
        }
        
        .signup-header h3 {
            font-size: 24px;
            color: #0a2540;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .signup-header p {
            color: #718096;
            font-size: 15px;
        }
        
        /* User Type Selector */
        .user-type-selector {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .type-option {
            flex: 1;
            min-width: 120px;
            text-align: center;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .type-option:hover {
            border-color: #10b981;
            transform: translateY(-2px);
        }
        
        .type-option.selected {
            border-color: #10b981;
            background: #f0fff4;
        }
        
        .type-icon {
            font-size: 22px;
            margin-bottom: 8px;
        }
        
        .type-student .type-icon { color: #10b981; }
        .type-sport-coach .type-icon { color: #f59e0b; }
        .type-dance-coach .type-icon { color: #8B1E3F; }
        .type-admin .type-icon { color: #ef4444; }
        
        .type-label {
            font-weight: 600;
            color: #0a2540;
            font-size: 13px;
        }
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        label {
            display: block;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 6px;
            font-size: 13px;
        }
        
        label span {
            color: #ef4444;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
            padding-right: 35px;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #10b981;
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .input-hint {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
            margin-left: 4px;
        }
        
        /* Section Headers */
        .section-header {
            background: #f0fdf4;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 20px 0 15px 0;
            font-weight: 700;
            color: #0a2540;
            border-left: 4px solid #10b981;
        }
        
        /* Student-specific fields */
        .student-fields, .sport-coach-fields, .dance-coach-fields {
            margin-top: 20px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 4px solid #10b981;
        }
        
        .sport-coach-fields {
            border-left-color: #f59e0b;
        }
        
        .dance-coach-fields {
            border-left-color: #8B1E3F;
        }
        
        .student-fields h4, .sport-coach-fields h4, .dance-coach-fields h4 {
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .student-fields h4 { color: #10b981; }
        .sport-coach-fields h4 { color: #f59e0b; }
        .dance-coach-fields h4 { color: #8B1E3F; }
        
        /* Student Type Selector */
        .student-type-selector {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .student-type-option {
            flex: 1;
            text-align: center;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .student-type-option:hover {
            border-color: #10b981;
            transform: translateY(-2px);
        }
        
        .student-type-option.selected {
            border-color: #10b981;
            background: #f0fff4;
        }
        
        .student-type-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .student-type-athlete .student-type-icon { color: #10b981; }
        .student-type-dancer .student-type-icon { color: #f59e0b; }
        
        .student-type-label {
            font-weight: 600;
            color: #0a2540;
            font-size: 13px;
        }
        
        /* Athlete-specific fields */
        .athlete-fields {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f0fdf4;
            border-radius: 12px;
            border-left: 4px solid #10b981;
        }
        
        .athlete-fields h5 {
            color: #10b981;
            margin-bottom: 15px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Dancer-specific fields */
        .dancer-fields {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #fef3c7;
            border-radius: 12px;
            border-left: 4px solid #f59e0b;
        }
        
        .dancer-fields h5 {
            color: #f59e0b;
            margin-bottom: 15px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        
        /* Button */
        .btn-signup {
            width: 100%;
            padding: 16px;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
        }
        
        .btn-signup i {
            font-size: 18px;
        }
        
        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: #718096;
            font-size: 14px;
        }
        
        .login-link a {
            color: #10b981;
            font-weight: 600;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        /* Position datalist styling */
        datalist {
            display: none;
        }
        
        /* Responsive */
        @media (max-width: 900px) {
            .signup-container {
                flex-direction: column;
                max-width: 600px;
            }
            
            .signup-left {
                padding: 25px 20px;
            }
            
            .signup-right {
                padding: 25px 20px;
                max-height: none;
            }
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .student-type-selector {
                flex-direction: column;
            }
            
            .user-type-selector {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .logo h1 {
                font-size: 32px;
            }
            
            .logo h2 {
                font-size: 16px;
            }
            
            body {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <!-- Left Panel -->
        <div class="signup-left">
            <div class="logo">
                <h1>TALENTRIX</h1>
                <h2>SIGN UP</h2>
            </div>
            
            <div class="features">
                <div class="feature">
                    <i class="fas fa-running"></i>
                    <div class="feature-text">
                        <h4>For Athletes</h4>
                        <p>Choose your sport and coach who will approve your application</p>
                    </div>
                </div>
                
                <div class="feature">
                    <i class="fas fa-music"></i>
                    <div class="feature-text">
                        <h4>For Dancers</h4>
                        <p>Choose your troupe and trainor who will approve your application</p>
                    </div>
                </div>
                
                <div class="feature">
                    <i class="fas fa-futbol"></i>
                    <div class="feature-text">
                        <h4>For Sports Coaches</h4>
                        <p>Manage teams and approve athletes (Instant access)</p>
                    </div>
                </div>
                
                <div class="feature">
                    <i class="fas fa-music"></i>
                    <div class="feature-text">
                        <h4>For Dance Trainors</h4>
                        <p>Train dancers and manage performances (Instant access)</p>
                    </div>
                </div>
                
                <div class="feature">
                    <i class="fas fa-user-shield"></i>
                    <div class="feature-text">
                        <h4>For Administrators</h4>
                        <p>Manage homepage content and announcements (Instant access)</p>
                    </div>
                </div>
            </div>
            
            <div class="login-prompt">
                <p>Already have an account?</p>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Sign In Here</a>
            </div>
        </div>
        
        <!-- Right Panel -->
        <div class="signup-right">
            <div class="signup-header">
                <h3>Create Your Account</h3>
                <p>Choose your role and fill in your details</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registrationForm">
                <!-- User Type Selection -->
                <input type="hidden" name="user_type" id="user_type" value="<?php echo isset($_POST['user_type']) ? $_POST['user_type'] : 'student'; ?>">
                
                <div class="user-type-selector">
                    <div class="type-option type-student <?php echo (!isset($_POST['user_type']) || $_POST['user_type'] == 'student') ? 'selected' : ''; ?>" onclick="selectUserType('student')">
                        <div class="type-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="type-label">Student</div>
                    </div>
                    
                    <div class="type-option type-sport-coach <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'sport_coach') ? 'selected' : ''; ?>" onclick="selectUserType('sport_coach')">
                        <div class="type-icon">
                            <i class="fas fa-futbol"></i>
                        </div>
                        <div class="type-label">Sports Coach</div>
                    </div>
                    
                    <div class="type-option type-dance-coach <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'dance_coach') ? 'selected' : ''; ?>" onclick="selectUserType('dance_coach')">
                        <div class="type-icon">
                            <i class="fas fa-music"></i>
                        </div>
                        <div class="type-label">Dance Trainor</div>
                    </div>
                    
                    <div class="type-option type-admin <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'admin') ? 'selected' : ''; ?>" onclick="selectUserType('admin')">
                        <div class="type-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="type-label">Administrator</div>
                    </div>
                </div>
                
                <!-- Basic Information -->
                <div class="section-header">
                    <i class="fas fa-user"></i> Personal Information
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name">First Name <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-user-circle"></i>
                            <input type="text" id="first_name" name="first_name" 
                                   placeholder="Enter your first name" 
                                   value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-user-circle"></i>
                            <input type="text" id="last_name" name="last_name" 
                                   placeholder="Enter your last name" 
                                   value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="middle_name" name="middle_name" 
                                   placeholder="Enter your middle name (optional)" 
                                   value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" 
                                   placeholder="your.email@institution.edu" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-user-tag"></i>
                            <input type="text" id="username" name="username" 
                                   placeholder="Choose a username" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_number">ID Number <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-id-card"></i>
                            <input type="text" id="id_number" name="id_number" 
                                   placeholder="e.g., 2023-00123" 
                                   value="<?php echo isset($_POST['id_number']) ? htmlspecialchars($_POST['id_number']) : ''; ?>" 
                                   required>
                        </div>
                        <div class="input-hint" id="id-number-hint">Your official ID number</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" 
                                   placeholder="At least 8 characters" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm your password" 
                                   required>
                        </div>
                    </div>
                </div>
                
                <!-- STUDENT-SPECIFIC FIELDS -->
                <div class="student-fields" id="student-fields" style="display: <?php echo (!isset($_POST['user_type']) || $_POST['user_type'] == 'student') ? 'block' : 'none'; ?>;">
                    <h4><i class="fas fa-graduation-cap"></i> Student Information</h4>
                    
                    <!-- Student Type Selector -->
                    <input type="hidden" name="student_type" id="student_type" value="<?php echo isset($_POST['student_type']) ? $_POST['student_type'] : 'athlete'; ?>">
                    
                    <div class="student-type-selector">
                        <div class="student-type-option student-type-athlete <?php echo (!isset($_POST['student_type']) || $_POST['student_type'] == 'athlete') ? 'selected' : ''; ?>" onclick="selectStudentType('athlete')">
                            <div class="student-type-icon">
                                <i class="fas fa-running"></i>
                            </div>
                            <div class="student-type-label">Athlete</div>
                        </div>
                        
                        <div class="student-type-option student-type-dancer <?php echo (isset($_POST['student_type']) && $_POST['student_type'] == 'dancer') ? 'selected' : ''; ?>" onclick="selectStudentType('dancer')">
                            <div class="student-type-icon">
                                <i class="fas fa-music"></i>
                            </div>
                            <div class="student-type-label">Dancer</div>
                        </div>
                    </div>
                    
                    <!-- Academic Information -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="college">College</label>
                            <div class="input-with-icon">
                                <i class="fas fa-university"></i>
                                <select id="college" name="college" onchange="updateCourses()">
                                    <option value="">-- Select College (Optional) --</option>
                                    <?php foreach($colleges as $code => $college): ?>
                                        <option value="<?php echo $code; ?>" <?php echo (isset($_POST['college']) && $_POST['college'] == $code) ? 'selected' : ''; ?>>
                                            <?php echo $college['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="course">Course/Program</label>
                            <div class="input-with-icon">
                                <i class="fas fa-book"></i>
                                <select id="course" name="course">
                                    <option value="">-- Select College First --</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="year_level">Year Level</label>
                            <div class="input-with-icon">
                                <i class="fas fa-calendar"></i>
                                <select id="year_level" name="year_level">
                                    <option value="">-- Select Year (Optional) --</option>
                                    <?php foreach($year_levels as $year): ?>
                                        <option value="<?php echo $year; ?>" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == $year) ? 'selected' : ''; ?>>
                                            <?php echo $year; ?><?php echo $year == 1 ? 'st' : ($year == 2 ? 'nd' : ($year == 3 ? 'rd' : 'th')); ?> Year
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="section">Section</label>
                            <div class="input-with-icon">
                                <i class="fas fa-layer-group"></i>
                                <input type="text" id="section" name="section" placeholder="e.g., A, B, C (Optional)"
                                       value="<?php echo isset($_POST['section']) ? htmlspecialchars($_POST['section']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- ATHLETE-SPECIFIC FIELDS -->
                    <div class="athlete-fields" id="athlete-fields" style="display: <?php echo (!isset($_POST['student_type']) || $_POST['student_type'] == 'athlete') ? 'block' : 'none'; ?>;">
                        <h5><i class="fas fa-running"></i> Athlete Information</h5>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="primary_sport">Primary Sport <span>*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-futbol"></i>
                                    <select id="primary_sport" name="primary_sport" onchange="updatePositionField(); updateCoachList()" required>
                                        <option value="">-- Select Sport --</option>
                                        <?php foreach($sports as $sport): ?>
                                            <option value="<?php echo $sport['sport_name']; ?>" <?php echo (isset($_POST['primary_sport']) && $_POST['primary_sport'] == $sport['sport_name']) ? 'selected' : ''; ?>>
                                                <?php echo $sport['sport_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="secondary_sport">Secondary Sport</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-futbol"></i>
                                    <select id="secondary_sport" name="secondary_sport">
                                        <option value="">-- Select Sport (Optional) --</option>
                                        <?php foreach($sports as $sport): ?>
                                            <option value="<?php echo $sport['sport_name']; ?>" <?php echo (isset($_POST['secondary_sport']) && $_POST['secondary_sport'] == $sport['sport_name']) ? 'selected' : ''; ?>>
                                                <?php echo $sport['sport_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="athlete_category">Athlete Category</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-medal"></i>
                                    <select id="athlete_category" name="athlete_category">
                                        <option value="">-- Select Category (Optional) --</option>
                                        <?php foreach($athlete_categories as $cat): ?>
                                            <option value="<?php echo $cat; ?>" <?php echo (isset($_POST['athlete_category']) && $_POST['athlete_category'] == $cat) ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($cat); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- DYNAMIC POSITION FIELD -->
                            <div class="form-group" id="position_field" style="display: <?php echo (isset($_POST['primary_sport']) && isset($sportPositions[$_POST['primary_sport']])) ? 'block' : 'none'; ?>;">
                                <label id="position_label">
                                    <?php 
                                    $selectedSport = isset($_POST['primary_sport']) ? $_POST['primary_sport'] : '';
                                    echo isset($sportPositions[$selectedSport]) ? $sportPositions[$selectedSport]['label'] : 'Position'; 
                                    ?>
                                </label>
                                <div class="input-with-icon">
                                    <i class="fas fa-user-tag"></i>
                                    <input type="text" id="primary_position" name="primary_position" 
                                           placeholder="Type or select from suggestions"
                                           value="<?php echo isset($_POST['primary_position']) ? htmlspecialchars($_POST['primary_position']) : ''; ?>"
                                           list="position-suggestions">
                                    <datalist id="position-suggestions">
                                        <?php if(isset($_POST['primary_sport']) && isset($sportPositions[$_POST['primary_sport']])): ?>
                                            <?php foreach($sportPositions[$_POST['primary_sport']]['options'] as $pos): ?>
                                                <option value="<?php echo $pos; ?>">
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </datalist>
                                </div>
                                <div class="input-hint">Pwede mag-type o pumili sa suggestions</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="jersey_number">Jersey Number</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-tshirt"></i>
                                    <input type="number" id="jersey_number" name="jersey_number" placeholder="e.g., 18 (Optional)" min="0" max="99"
                                           value="<?php echo isset($_POST['jersey_number']) ? htmlspecialchars($_POST['jersey_number']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Coach Selection for Athletes -->
                        <div class="form-group full-width" style="margin-top: 15px;">
                            <label for="approving_coach">Select Your Coach <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-user-tie"></i>
                                <select id="approving_coach" name="approving_coach_id" required>
                                    <option value="">-- Select a coach --</option>
                                    <?php if(empty($sports_coaches)): ?>
                                        <option value="" disabled>No coaches available yet</option>
                                    <?php else: ?>
                                        <?php 
                                        // Group coaches by sport
                                        $current_sport = '';
                                        foreach($sports_coaches as $coach): 
                                            if($coach['primary_sport'] != $current_sport) {
                                                if($current_sport != '') echo '</optgroup>';
                                                $current_sport = $coach['primary_sport'];
                                                echo '<optgroup label="' . htmlspecialchars($current_sport) . '">';
                                            }
                                        ?>
                                            <option value="<?php echo $coach['id']; ?>" data-sport="<?php echo $coach['primary_sport']; ?>" <?php echo (isset($_POST['approving_coach_id']) && $_POST['approving_coach_id'] == $coach['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if($current_sport != '') echo '</optgroup>'; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="input-hint">This coach will receive and approve your application</div>
                        </div>
                    </div>
                    
                    <!-- DANCER-SPECIFIC FIELDS -->
                    <div class="dancer-fields" id="dancer-fields" style="display: <?php echo (isset($_POST['student_type']) && $_POST['student_type'] == 'dancer') ? 'block' : 'none'; ?>;">
                        <h5><i class="fas fa-music"></i> Dancer Information</h5>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="dance_troupe">Dance Troupe <span>*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-users"></i>
                                    <select id="dance_troupe" name="dance_troupe" required>
                                        <option value="">-- Select Troupe --</option>
                                        <?php foreach($dance_troupes as $troupe): ?>
                                            <option value="<?php echo $troupe['troupe_name']; ?>" <?php echo (isset($_POST['dance_troupe']) && $_POST['dance_troupe'] == $troupe['troupe_name']) ? 'selected' : ''; ?>>
                                                <?php echo $troupe['troupe_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="dance_role">Dance Role</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-star"></i>
                                    <select id="dance_role" name="dance_role">
                                        <option value="">-- Select Role (Optional) --</option>
                                        <?php foreach($dance_roles as $role): ?>
                                            <option value="<?php echo $role; ?>" <?php echo (isset($_POST['dance_role']) && $_POST['dance_role'] == $role) ? 'selected' : ''; ?>>
                                                <?php echo $role; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Coach Selection for Dancers -->
                        <div class="form-group full-width" style="margin-top: 15px;">
                            <label for="approving_dance_coach">Select Your Trainor <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-user-tie"></i>
                                <select id="approving_dance_coach" name="approving_dance_coach_id" required>
                                    <option value="">-- Select a dance trainor --</option>
                                    <?php if(empty($dance_coaches)): ?>
                                        <option value="" disabled>No dance trainors available yet</option>
                                    <?php else: ?>
                                        <?php foreach($dance_coaches as $coach): ?>
                                            <option value="<?php echo $coach['id']; ?>" <?php echo (isset($_POST['approving_dance_coach_id']) && $_POST['approving_dance_coach_id'] == $coach['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name'] . ' (' . $coach['dance_specialization'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="input-hint">This trainor will receive and approve your application</div>
                        </div>
                    </div>
                    
                    <!-- Student Approval Notice -->
                    <div style="background: #e0f2fe; padding: 12px; border-radius: 8px; margin-top: 15px;">
                        <p style="color: #0369a1; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> Your application will be sent to your selected coach/trainor for approval. You will be able to login once approved.
                        </p>
                    </div>
                </div>
                
                <!-- SPORTS COACH-SPECIFIC FIELDS -->
                <div class="sport-coach-fields" id="sport-coach-fields" style="display: <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'sport_coach') ? 'block' : 'none'; ?>;">
                    <h4><i class="fas fa-futbol"></i> Sports Coach Information</h4>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="primary_sport_coach">Sport to Coach <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-futbol"></i>
                                <select id="primary_sport_coach" name="primary_sport_coach">
                                    <option value="">-- Select Sport --</option>
                                    <?php foreach($sports as $sport): ?>
                                        <option value="<?php echo $sport['sport_name']; ?>" <?php echo (isset($_POST['primary_sport_coach']) && $_POST['primary_sport_coach'] == $sport['sport_name']) ? 'selected' : ''; ?>>
                                            <?php echo $sport['sport_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="specialization">Specialization</label>
                            <div class="input-with-icon">
                                <i class="fas fa-star"></i>
                                <select id="specialization" name="specialization">
                                    <option value="">-- Select Specialization (Optional) --</option>
                                    <?php foreach($specializations as $spec): ?>
                                        <option value="<?php echo $spec; ?>" <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == $spec) ? 'selected' : ''; ?>>
                                            <?php echo $spec; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="years_experience">Years of Experience <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-calendar-alt"></i>
                                <select id="years_experience" name="years_experience">
                                    <option value="">-- Select Years --</option>
                                    <?php foreach($years_experience as $year): ?>
                                        <option value="<?php echo $year; ?>" <?php echo (isset($_POST['years_experience']) && $_POST['years_experience'] == $year) ? 'selected' : ''; ?>>
                                            <?php echo $year; ?> year<?php echo $year != 1 ? 's' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="campus">Campus</label>
                            <div class="input-with-icon">
                                <i class="fas fa-map-marker-alt"></i>
                                <select id="campus" name="campus">
                                    <option value="">-- Select Campus (Optional) --</option>
                                    <?php foreach($campuses as $camp): ?>
                                        <option value="<?php echo $camp; ?>" <?php echo (isset($_POST['campus']) && $_POST['campus'] == $camp) ? 'selected' : ''; ?>>
                                            <?php echo $camp; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="bio">Bio / Introduction</label>
                            <div class="input-with-icon">
                                <i class="fas fa-align-left"></i>
                                <textarea id="bio" name="bio" rows="3" placeholder="Tell us about your coaching experience (Optional)"><?php echo isset($_POST['bio']) ? htmlspecialchars($_POST['bio']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Coach Approval Notice -->
                    <div style="background: #c6f6d5; padding: 12px; border-radius: 8px; margin-top: 15px;">
                        <p style="color: #22543d; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-check-circle"></i>
                            <strong>Note:</strong> Your account will be activated immediately. You can login right away and start approving athlete applications.
                        </p>
                    </div>
                </div>
                
                <!-- DANCE COACH-SPECIFIC FIELDS -->
                <div class="dance-coach-fields" id="dance-coach-fields" style="display: <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'dance_coach') ? 'block' : 'none'; ?>;">
                    <h4><i class="fas fa-music"></i> Dance Trainor Information</h4>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="dance_specialization">Dance Style Specialization <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-star"></i>
                                <select id="dance_specialization" name="dance_specialization">
                                    <option value="">-- Select Dance Style --</option>
                                    <?php foreach($dance_specializations as $spec): ?>
                                        <option value="<?php echo $spec; ?>" <?php echo (isset($_POST['dance_specialization']) && $_POST['dance_specialization'] == $spec) ? 'selected' : ''; ?>>
                                            <?php echo $spec; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dance_troupe_name">Dance Troupe Name</label>
                            <div class="input-with-icon">
                                <i class="fas fa-users"></i>
                                <input type="text" id="dance_troupe_name" name="dance_troupe_name" 
                                       placeholder="e.g., BISU Street Dancers"
                                       value="<?php echo isset($_POST['dance_troupe_name']) ? htmlspecialchars($_POST['dance_troupe_name']) : ''; ?>">
                            </div>
                            <div class="input-hint">Optional - Name of your dance group</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dance_experience">Years of Dance Experience <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-calendar-alt"></i>
                                <select id="dance_experience" name="dance_experience">
                                    <option value="">-- Select Years --</option>
                                    <?php for($year = 0; $year <= 30; $year++): ?>
                                        <option value="<?php echo $year; ?>" <?php echo (isset($_POST['dance_experience']) && $_POST['dance_experience'] == $year) ? 'selected' : ''; ?>>
                                            <?php echo $year; ?> year<?php echo $year != 1 ? 's' : ''; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dance_achievements">Notable Achievements</label>
                            <div class="input-with-icon">
                                <i class="fas fa-trophy"></i>
                                <textarea id="dance_achievements" name="dance_achievements" rows="3" 
                                          placeholder="e.g., Champion - Regional Dance Competition 2023, etc."><?php echo isset($_POST['dance_achievements']) ? htmlspecialchars($_POST['dance_achievements']) : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dance_certifications">Certifications</label>
                            <div class="input-with-icon">
                                <i class="fas fa-certificate"></i>
                                <input type="text" id="dance_certifications" name="dance_certifications" 
                                       placeholder="e.g., Dance Instructor License, etc."
                                       value="<?php echo isset($_POST['dance_certifications']) ? htmlspecialchars($_POST['dance_certifications']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dance_campus">Campus</label>
                            <div class="input-with-icon">
                                <i class="fas fa-map-marker-alt"></i>
                                <select id="dance_campus" name="dance_campus">
                                    <option value="">-- Select Campus (Optional) --</option>
                                    <?php foreach($campuses as $camp): ?>
                                        <option value="<?php echo $camp; ?>" <?php echo (isset($_POST['dance_campus']) && $_POST['dance_campus'] == $camp) ? 'selected' : ''; ?>>
                                            <?php echo $camp; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="dance_bio">Bio / Introduction</label>
                            <div class="input-with-icon">
                                <i class="fas fa-align-left"></i>
                                <textarea id="dance_bio" name="dance_bio" rows="3" 
                                          placeholder="Tell us about your dance journey and teaching experience"><?php echo isset($_POST['dance_bio']) ? htmlspecialchars($_POST['dance_bio']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dance Coach Approval Notice -->
                    <div style="background: #c6f6d5; padding: 12px; border-radius: 8px; margin-top: 15px;">
                        <p style="color: #22543d; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-check-circle"></i>
                            <strong>Note:</strong> Your account will be activated immediately. You can login right away and start approving dancer applications.
                        </p>
                    </div>
                </div>
                
                <!-- Admin Notice -->
                <?php if(isset($_POST['user_type']) && $_POST['user_type'] == 'admin'): ?>
                <div style="background: #c6f6d5; padding: 12px; border-radius: 8px; margin-top: 15px;">
                    <p style="color: #22543d; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-check-circle"></i>
                        <strong>Note:</strong> Admin accounts are activated immediately.
                    </p>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-signup" id="submit-btn">
                    <i class="fas fa-user-plus"></i>
                    <span class="btn-text">Create Account</span>
                </button>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Sign in here</a>
            </div>
        </div>
    </div>
    
    <script>
    // Sport positions data from PHP
    const sportPositions = <?php echo json_encode($sportPositions); ?>;
    
    // College courses data
    const courseData = {
        'CBM': [
            'Bachelor of Science in Hospitality Management',
            'Bachelor of Science in Office Administration'
        ],
        'CTE': [
            'Bachelor of Elementary Education',
            'Bachelor of Secondary Education'
        ],
        'COS': [
            'Bachelor of Science in Computer Science',
            'Bachelor of Science in Environmental Science'
        ],
        'CFMS': [
            'Bachelor of Science in Fisheries',
            'Bachelor of Science in Marine Biology'
        ]
    };

    // User Type Selection
    function selectUserType(type) {
        document.getElementById('user_type').value = type;
        
        document.querySelectorAll('.type-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        if (type === 'sport_coach') {
            document.querySelector('.type-sport-coach').classList.add('selected');
        } else if (type === 'dance_coach') {
            document.querySelector('.type-dance-coach').classList.add('selected');
        } else {
            document.querySelector('.type-' + type).classList.add('selected');
        }
        
        // Show/hide appropriate fields
        document.getElementById('student-fields').style.display = type === 'student' ? 'block' : 'none';
        document.getElementById('sport-coach-fields').style.display = type === 'sport_coach' ? 'block' : 'none';
        document.getElementById('dance-coach-fields').style.display = type === 'dance_coach' ? 'block' : 'none';
        
        // Update ID format hint
        const idHint = document.getElementById('id-number-hint');
        if (type === 'student') {
            idHint.textContent = 'Format: YYYY-##### (Example: 2023-00123)';
        } else if (type === 'sport_coach') {
            idHint.textContent = 'Format: ABC-YYYY### (Example: COA-2024001)';
        } else if (type === 'dance_coach') {
            idHint.textContent = 'Format: ABC-YYYY### (Example: DAN-2024001)';
        } else if (type === 'admin') {
            idHint.textContent = 'Format: ADMIN-### (Example: ADMIN-001)';
        }
    }

    // Student Type Selection
    function selectStudentType(type) {
        document.getElementById('student_type').value = type;
        
        document.querySelectorAll('.student-type-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        document.querySelector('.student-type-' + type).classList.add('selected');
        
        // Show/hide athlete and dancer fields
        document.getElementById('athlete-fields').style.display = type === 'athlete' ? 'block' : 'none';
        document.getElementById('dancer-fields').style.display = type === 'dancer' ? 'block' : 'none';
    }

    // Update courses based on selected college
    function updateCourses() {
        const collegeSelect = document.getElementById('college');
        const courseSelect = document.getElementById('course');
        const selectedCollege = collegeSelect.value;
        
        courseSelect.innerHTML = '<option value="">-- Select Course --</option>';
        
        if (selectedCollege && courseData[selectedCollege]) {
            courseData[selectedCollege].forEach(course => {
                const option = document.createElement('option');
                option.value = course;
                option.textContent = course;
                courseSelect.appendChild(option);
            });
            courseSelect.disabled = false;
        } else {
            courseSelect.disabled = true;
            courseSelect.innerHTML = '<option value="">-- Select College First --</option>';
        }
    }

    // DYNAMIC POSITION FIELD - Updates based on selected sport
    function updatePositionField() {
        const sportSelect = document.getElementById('primary_sport');
        const positionField = document.getElementById('position_field');
        const positionInput = document.getElementById('primary_position');
        const positionLabel = document.getElementById('position_label');
        const positionSuggestions = document.getElementById('position-suggestions');
        
        if (!sportSelect || !positionField) return;
        
        const selectedSport = sportSelect.value;
        
        if (selectedSport && sportPositions[selectedSport]) {
            positionField.style.display = 'block';
            if (positionLabel) positionLabel.textContent = sportPositions[selectedSport].label;
            
            if (positionSuggestions) {
                let optionsHtml = '';
                sportPositions[selectedSport].options.forEach(pos => {
                    optionsHtml += `<option value="${pos}">`;
                });
                positionSuggestions.innerHTML = optionsHtml;
            }
            
            if (positionInput) positionInput.value = '';
        } else {
            positionField.style.display = 'none';
            if (positionInput) positionInput.value = '';
        }
    }

    // Update coach list based on selected sport (for athletes)
    function updateCoachList() {
        const sportSelect = document.getElementById('primary_sport');
        const coachSelect = document.getElementById('approving_coach');
        const selectedSport = sportSelect.value;
        
        if (!coachSelect) return;
        
        // Show all options and highlight based on sport
        const options = coachSelect.querySelectorAll('option');
        options.forEach(option => {
            if (option.value === '') return; // Skip the first option
            
            const sport = option.getAttribute('data-sport');
            if (selectedSport && sport !== selectedSport) {
                option.style.display = 'none';
            } else {
                option.style.display = 'block';
            }
        });
        
        // Reset selection
        coachSelect.value = '';
    }

    // SIMPLE FORM SUBMISSION - This ensures the form submits properly
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        console.log('Form submitting for user type: ' + document.getElementById('user_type').value);
        
        // Check if passwords match
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('❌ Passwords do not match!');
            return false;
        }
        
        if (password.length < 8) {
            e.preventDefault();
            alert('❌ Password must be at least 8 characters!');
            return false;
        }
        
        // Let the form submit normally
        return true;
    });

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded - setting up form for all user types');
        
        // Initialize fields
        updatePositionField();
        
        // Initialize student type display
        const studentType = document.getElementById('student_type');
        if (studentType) {
            if (studentType.value === 'athlete') {
                document.getElementById('athlete-fields').style.display = 'block';
                document.getElementById('dancer-fields').style.display = 'none';
            } else if (studentType.value === 'dancer') {
                document.getElementById('athlete-fields').style.display = 'none';
                document.getElementById('dancer-fields').style.display = 'block';
            }
        }
        
        // Initialize courses if college is selected
        const college = document.getElementById('college');
        if (college && college.value) {
            updateCourses();
        }
        
        // Initialize coach list filtering
        if (document.getElementById('primary_sport')) {
            updateCoachList();
        }
        
        // Set ID hint based on default user type
        const idHint = document.getElementById('id-number-hint');
        idHint.textContent = 'Format: YYYY-##### (Example: 2023-00123)';
    });
    </script>
</body>
</html>