<?php
// register.php - COMPLETE UPDATED VERSION WITH FIXED FOREIGN KEY ISSUE
// User types: student, sport_coach, dance_trainer, athletics_admin, dance_admin
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

// If user is already logged in, redirect
if(isset($_SESSION['user_id'])) {
    if($_SESSION['user_type'] === 'athletics_admin') {
        header('Location: athletics_dashboard.php');
        exit();
    } elseif($_SESSION['user_type'] === 'dance_admin') {
        header('Location: dance_dashboard.php');
        exit();
    } elseif($_SESSION['user_type'] === 'sport_coach') {
        header('Location: coach_dashboard.php');
        exit();
    } elseif($_SESSION['user_type'] === 'dance_trainer') {
        header('Location: dance_trainer_dashboard.php');
        exit();
    } elseif($_SESSION['user_type'] === 'student') {
        header('Location: student_dashboard.php');
        exit();
    }
}

$error = '';

// Get sports from database
$sports = $pdo->query("SELECT id, sport_name FROM sports WHERE is_active = 1 ORDER BY sport_name")->fetchAll();

// Get dance troupes
$dance_troupes = $pdo->query("SELECT id, troupe_name FROM dance_troupes WHERE is_active = 1 ORDER BY troupe_name")->fetchAll();

// Get sports coaches (for athlete approval) - now getting from coaches table with correct ID
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

// Get dance trainers (for dancer approval) - we need their coach ID if they exist in coaches table
$dance_trainers = [];
try {
    // First, get dance trainers from dance_trainers table
    $dance_trainers = $pdo->query("
        SELECT dt.id, dt.user_id, u.first_name, u.last_name, dt.dance_specialization 
        FROM dance_trainers dt 
        JOIN users u ON dt.user_id = u.id 
        WHERE u.status = 'active'
        ORDER BY u.first_name
    ")->fetchAll();
} catch (Exception $e) {
    $dance_trainers = [];
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

// Admin departments
$admin_departments = [
    'Athletics Department',
    'Sports Development Office',
    'Physical Education Department',
    'Student Affairs',
    'Academic Affairs'
];

// Admin positions
$admin_positions = [
    'Athletics Director',
    'Sports Coordinator',
    'Facilities Manager',
    'Events Coordinator',
    'Program Head'
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
        $errors[] = 'Please fill in all required fields marked with *';
    }
    
    if($password !== $confirm_password) {
        $errors[] = 'Passwords do not match!';
    }
    
    if(strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters!';
    }
    
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address!';
    }
    
    // Student validation
    if($user_type == 'student') {
        if(empty($_POST['student_type'])) {
            $errors[] = 'Please select student type (Athlete or Dancer)!';
        } else {
            if($_POST['student_type'] == 'athlete') {
                if(empty($_POST['primary_sport'])) {
                    $errors[] = 'Please select your primary sport!';
                }
                if(empty($_POST['approving_coach_id'])) {
                    $errors[] = 'Please select the coach who will approve your application!';
                }
            } else if($_POST['student_type'] == 'dancer') {
                if(empty($_POST['dance_troupe'])) {
                    $errors[] = 'Please select your dance troupe!';
                }
                if(empty($_POST['approving_dance_trainer_id'])) {
                    $errors[] = 'Please select the trainor who will approve your application!';
                }
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
    
    // Dance Trainer validation
    if($user_type == 'dance_trainer') {
        if(!preg_match('/^[A-Za-z]{2,4}-\d{4}\d{0,3}$/', $id_number)) {
            $errors[] = 'Dance Trainor ID must be: ABC-YYYY### (e.g., DAN-2024001)';
        }
        $id_number = strtoupper($id_number);
        
        if(empty($_POST['dance_specialization'])) {
            $errors[] = 'Please select your dance specialization!';
        }
    }
    
    // Athletics Admin validation
    if($user_type == 'athletics_admin') {
        if(!preg_match('/^ATH-\d{3}$/i', $id_number)) {
            $errors[] = 'Athletics Admin ID must be: ATH-### (e.g., ATH-001)';
        }
        $id_number = strtoupper($id_number);
    }
    
    // Dance Admin validation
    if($user_type == 'dance_admin') {
        if(!preg_match('/^DAN-\d{3}$/i', $id_number)) {
            $errors[] = 'Dance Admin ID must be: DAN-### (e.g., DAN-001)';
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
    
    // Only proceed if NO errors
    if(empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Set status based on user type
            if($user_type == 'student') {
                $status = 'pending';
                $is_verified = 0;
            } else {
                // All non-student users are automatically active
                $status = 'active';
                $is_verified = 1;
            }
            
            // Insert into users
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    username, 
                    email, 
                    password, 
                    id_number, 
                    first_name, 
                    middle_name, 
                    last_name, 
                    institution, 
                    user_type, 
                    is_verified, 
                    status, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $username,
                $email,
                $hashed_password,
                $id_number,
                $first_name,
                $middle_name,
                $last_name,
                'Bohol Island State University',
                $user_type,
                $is_verified,
                $status
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Insert into specific tables based on user type
            if($user_type == 'student') {
                $student_type = $_POST['student_type'] ?? 'athlete';
                
                // Handle empty values properly
                $athlete_category = !empty($_POST['athlete_category']) ? $_POST['athlete_category'] : null;
                $primary_position = !empty($_POST['primary_position']) ? $_POST['primary_position'] : null;
                $jersey_number = !empty($_POST['jersey_number']) ? (int)$_POST['jersey_number'] : null;
                $dance_troupe = !empty($_POST['dance_troupe']) ? $_POST['dance_troupe'] : null;
                $dance_role = !empty($_POST['dance_role']) ? $_POST['dance_role'] : null;
                $section = !empty($_POST['section']) ? $_POST['section'] : null;
                $college = !empty($_POST['college']) ? $_POST['college'] : null;
                $course = !empty($_POST['course']) ? $_POST['course'] : null;
                $year_level = !empty($_POST['year_level']) ? (int)$_POST['year_level'] : null;
                
                $stmt2 = $pdo->prepare("
                    INSERT INTO students (
                        user_id, student_type, college, course, year_level, section,
                        primary_sport, athlete_category, primary_position, jersey_number,
                        dance_troupe, dance_role, date_registered, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())
                ");
                
                $stmt2->execute([
                    $user_id,
                    $student_type,
                    $college,
                    $course,
                    $year_level,
                    $section,
                    $_POST['primary_sport'] ?? null,
                    $athlete_category,
                    $primary_position,
                    $jersey_number,
                    $dance_troupe,
                    $dance_role
                ]);
                
                $student_id = $pdo->lastInsertId();
                
                // CREATE APPROVAL REQUEST - FIXED FOREIGN KEY ISSUE
                if($student_type == 'athlete' && !empty($_POST['approving_coach_id'])) {
                    // Get coach's details from coaches table
                    $coach_stmt = $pdo->prepare("SELECT id, user_id FROM coaches WHERE id = ?");
                    $coach_stmt->execute([$_POST['approving_coach_id']]);
                    $coach = $coach_stmt->fetch();
                    
                    if($coach) {
                        // Create approval record - use coach_id from coaches table (this is the primary key of coaches table)
                        $approval_stmt = $pdo->prepare("
                            INSERT INTO approvals (
                                student_id, coach_id, approval_type, status, request_date
                            ) VALUES (?, ?, 'team', 'pending', NOW())
                        ");
                        $approval_stmt->execute([$student_id, $coach['id']]);
                    }
                }
                
                if($student_type == 'dancer' && !empty($_POST['approving_dance_trainer_id'])) {
                    // IMPORTANT FIX: Dance trainers don't have records in coaches table
                    // So we need to either:
                    // 1. Set coach_id to NULL (since dance trainers aren't in coaches table)
                    // 2. OR create a record for dance trainers in coaches table
                    
                    // Option 1: Set coach_id to NULL (dance trainers will check approvals via dance_trainer_dashboard)
                    // This is the simpler solution since dance trainers have their own table
                    
                    $approval_stmt = $pdo->prepare("
                        INSERT INTO approvals (
                            student_id, coach_id, dance_trainer_id, approval_type, status, request_date
                        ) VALUES (?, NULL, ?, 'troupe', 'pending', NOW())
                    ");
                    $approval_stmt->execute([$student_id, $_POST['approving_dance_trainer_id']]);
                }
            }
            
            if($user_type == 'sport_coach') {
                $stmt2 = $pdo->prepare("
                    INSERT INTO coaches (
                        user_id, employee_id, department, position, specialization, years_experience, 
                        bio, primary_sport, date_hired, created_at
                    ) VALUES (?, ?, 'College of Sports', 'Sports Coach', ?, ?, ?, ?, CURDATE(), NOW())
                ");
                
                $specialization = !empty($_POST['specialization']) ? $_POST['specialization'] : '';
                $bio = !empty($_POST['bio']) ? $_POST['bio'] : '';
                $years = !empty($_POST['years_experience']) ? (int)$_POST['years_experience'] : 0;
                
                $stmt2->execute([
                    $user_id,
                    $id_number,
                    $specialization,
                    $years,
                    $bio,
                    $_POST['primary_sport_coach'] ?? null
                ]);
            }
            
            if($user_type == 'dance_trainer') {
                $stmt2 = $pdo->prepare("
                    INSERT INTO dance_trainers (
                        user_id, employee_id, dance_specialization, dance_troupe_name, 
                        years_experience, achievements, certifications, campus, bio, date_hired, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())
                ");
                
                $dance_specialization = !empty($_POST['dance_specialization']) ? $_POST['dance_specialization'] : '';
                $dance_troupe_name = !empty($_POST['dance_troupe_name']) ? $_POST['dance_troupe_name'] : null;
                $dance_experience = !empty($_POST['dance_experience']) ? (int)$_POST['dance_experience'] : 0;
                $dance_achievements = !empty($_POST['dance_achievements']) ? $_POST['dance_achievements'] : null;
                $dance_certifications = !empty($_POST['dance_certifications']) ? $_POST['dance_certifications'] : null;
                $dance_campus = !empty($_POST['dance_campus']) ? $_POST['dance_campus'] : null;
                $dance_bio = !empty($_POST['dance_bio']) ? $_POST['dance_bio'] : null;
                
                $stmt2->execute([
                    $user_id,
                    $id_number,
                    $dance_specialization,
                    $dance_troupe_name,
                    $dance_experience,
                    $dance_achievements,
                    $dance_certifications,
                    $dance_campus,
                    $dance_bio
                ]);
            }
            
            if($user_type == 'athletics_admin') {
                // Insert into athletics_admins table if it exists
                // For now, just users table is enough
            }
            
            if($user_type == 'dance_admin') {
                // Insert into dance_admins table if it exists
                // For now, just users table is enough
            }
            
            $pdo->commit();
            
            if($user_type == 'student') {
                $_SESSION['registration_success'] = 'Registration successful! Your application has been sent to your selected coach/trainor for approval.';
            } else {
                $_SESSION['registration_success'] = 'Registration successful! You can now login to your account.';
            }
            
            header('Location: login.php?registered=success');
            exit();
            
        } catch (Exception $e) {
            try {
                $pdo->rollBack();
            } catch (Exception $rollbackError) {
                error_log("Rollback failed: " . $rollbackError->getMessage());
            }
            
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
        
        .user-type-selector {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .type-option {
            flex: 1;
            min-width: 110px;
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
        .type-dance-trainer .type-icon { color: #8B1E3F; }
        .type-athletics-admin .type-icon { color: #3b82f6; }
        .type-dance-admin .type-icon { color: #FFB347; }
        
        .type-label {
            font-weight: 600;
            color: #0a2540;
            font-size: 12px;
        }
        
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
        
        .section-header {
            background: #f0fdf4;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 20px 0 15px 0;
            font-weight: 700;
            color: #0a2540;
            border-left: 4px solid #10b981;
        }
        
        .student-fields, .sport-coach-fields, .dance-trainer-fields, 
        .athletics-admin-fields, .dance-admin-fields {
            margin-top: 20px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 4px solid #10b981;
        }
        
        .sport-coach-fields {
            border-left-color: #f59e0b;
        }
        
        .dance-trainer-fields {
            border-left-color: #8B1E3F;
        }
        
        .athletics-admin-fields {
            border-left-color: #3b82f6;
        }
        
        .dance-admin-fields {
            border-left-color: #FFB347;
        }
        
        .student-fields h4, .sport-coach-fields h4, .dance-trainer-fields h4,
        .athletics-admin-fields h4, .dance-admin-fields h4 {
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .student-fields h4 { color: #10b981; }
        .sport-coach-fields h4 { color: #f59e0b; }
        .dance-trainer-fields h4 { color: #8B1E3F; }
        .athletics-admin-fields h4 { color: #3b82f6; }
        .dance-admin-fields h4 { color: #FFB347; }
        
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
            position: relative;
            z-index: 10;
        }
        
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
        }
        
        .btn-signup:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .btn-signup i {
            font-size: 18px;
        }
        
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
        
        datalist {
            display: none;
        }
        
        .info-box {
            background: #e0f2fe;
            border-left: 4px solid #0284c7;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            color: #0369a1;
        }
        
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
                        <p>Manage athletes and teams (Instant access)</p>
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
                    <i class="fas fa-trophy"></i>
                    <div class="feature-text">
                        <h4>For Athletics Admins</h4>
                        <p>Manage all athletic activities (Instant access)</p>
                    </div>
                </div>
                
                <div class="feature">
                    <i class="fas fa-star" style="color: #FFB347;"></i>
                    <div class="feature-text">
                        <h4>For Dance Admins</h4>
                        <p>Manage all dance activities (Instant access)</p>
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
                <input type="hidden" name="user_type" id="user_type" value="<?php echo isset($_POST['user_type']) ? htmlspecialchars($_POST['user_type']) : 'student'; ?>">
                
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
                    
                    <div class="type-option type-dance-trainer <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'dance_trainer') ? 'selected' : ''; ?>" onclick="selectUserType('dance_trainer')">
                        <div class="type-icon">
                            <i class="fas fa-music"></i>
                        </div>
                        <div class="type-label">Dance Trainor</div>
                    </div>
                    
                    <div class="type-option type-athletics-admin <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'athletics_admin') ? 'selected' : ''; ?>" onclick="selectUserType('athletics_admin')">
                        <div class="type-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="type-label">Athletics Admin</div>
                    </div>
                    
                    <div class="type-option type-dance-admin <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'dance_admin') ? 'selected' : ''; ?>" onclick="selectUserType('dance_admin')">
                        <div class="type-icon">
                            <i class="fas fa-star" style="color: #FFB347;"></i>
                        </div>
                        <div class="type-label">Dance Admin</div>
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
                                   placeholder="Format depends on your role" 
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
                    
                    <input type="hidden" name="student_type" id="student_type" value="<?php echo isset($_POST['student_type']) ? htmlspecialchars($_POST['student_type']) : 'athlete'; ?>">
                    
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
                                    <select id="primary_sport" name="primary_sport" onchange="updatePositionField()">
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
                                <div class="input-hint">Type or select from suggestions</div>
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
                                <select id="approving_coach" name="approving_coach_id">
                                    <option value="">-- Select a coach --</option>
                                    <?php if(empty($sports_coaches)): ?>
                                        <option value="" disabled>No coaches available yet</option>
                                    <?php else: ?>
                                        <?php 
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
                                    <select id="dance_troupe" name="dance_troupe">
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
                        
                        <!-- Trainer Selection for Dancers -->
                        <div class="form-group full-width" style="margin-top: 15px;">
                            <label for="approving_dance_trainer">Select Your Trainor <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-user-tie"></i>
                                <select id="approving_dance_trainer" name="approving_dance_trainer_id">
                                    <option value="">-- Select a dance trainor --</option>
                                    <?php if(empty($dance_trainers)): ?>
                                        <option value="" disabled>No dance trainors available yet</option>
                                    <?php else: ?>
                                        <?php foreach($dance_trainers as $trainer): ?>
                                            <option value="<?php echo $trainer['id']; ?>" <?php echo (isset($_POST['approving_dance_trainer_id']) && $_POST['approving_dance_trainer_id'] == $trainer['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name'] . ' (' . $trainer['dance_specialization'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="input-hint">This trainor will receive and approve your application</div>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Your application will be sent to your selected coach/trainor for approval. You will be able to login once approved.
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
                                <select id="primary_sport_coach" name="primary_sport_coach" required>
                                    <option value="">-- Select Sport --</option>
                                    <?php foreach($sports as $sport): ?>
                                        <option value="<?php echo $sport['sport_name']; ?>" <?php echo (isset($_POST['primary_sport_coach']) && $_POST['primary_sport_coach'] == $sport['sport_name']) ? 'selected' : ''; ?>>
                                            <?php echo $sport['sport_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="input-hint">You will receive approval requests for this sport</div>
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
                                <select id="years_experience" name="years_experience" required>
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
                            <label for="campus_coach">Campus</label>
                            <div class="input-with-icon">
                                <i class="fas fa-map-marker-alt"></i>
                                <select id="campus_coach" name="campus">
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
                    
                    <div class="info-box" style="background: #c6f6d5; border-left-color: #38a169; color: #22543d;">
                        <i class="fas fa-check-circle"></i>
                        <strong>Note:</strong> Your account will be activated immediately. You will see pending approvals for your sport in your dashboard.
                    </div>
                </div>
                
                <!-- DANCE TRAINER-SPECIFIC FIELDS -->
                <div class="dance-trainer-fields" id="dance-trainer-fields" style="display: <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'dance_trainer') ? 'block' : 'none'; ?>;">
                    <h4><i class="fas fa-music"></i> Dance Trainor Information</h4>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="dance_specialization">Dance Style Specialization <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-star"></i>
                                <select id="dance_specialization" name="dance_specialization" required>
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
                            <div class="input-hint">You will receive approval requests for this troupe</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dance_experience">Years of Dance Experience <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-calendar-alt"></i>
                                <select id="dance_experience" name="dance_experience" required>
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
                                          placeholder="e.g., Champion - Regional Dance Competition 2023"><?php echo isset($_POST['dance_achievements']) ? htmlspecialchars($_POST['dance_achievements']) : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dance_certifications">Certifications</label>
                            <div class="input-with-icon">
                                <i class="fas fa-certificate"></i>
                                <input type="text" id="dance_certifications" name="dance_certifications" 
                                       placeholder="e.g., Dance Instructor License"
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
                                          placeholder="Tell us about your dance journey"><?php echo isset($_POST['dance_bio']) ? htmlspecialchars($_POST['dance_bio']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-box" style="background: #c6f6d5; border-left-color: #38a169; color: #22543d;">
                        <i class="fas fa-check-circle"></i>
                        <strong>Note:</strong> Your account will be activated immediately. You will see pending approvals for your troupe in your dashboard.
                    </div>
                </div>
                
                <!-- ATHLETICS ADMIN-SPECIFIC FIELDS -->
                <div class="athletics-admin-fields" id="athletics-admin-fields" style="display: <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'athletics_admin') ? 'block' : 'none'; ?>;">
                    <h4><i class="fas fa-trophy"></i> Athletics Administrator Information</h4>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="admin_department">Department <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-building"></i>
                                <select id="admin_department" name="admin_department" required>
                                    <option value="">-- Select Department --</option>
                                    <?php foreach($admin_departments as $dept): ?>
                                        <option value="<?php echo $dept; ?>" <?php echo (isset($_POST['admin_department']) && $_POST['admin_department'] == $dept) ? 'selected' : ''; ?>>
                                            <?php echo $dept; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_position">Position <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-user-tie"></i>
                                <select id="admin_position" name="admin_position" required>
                                    <option value="">-- Select Position --</option>
                                    <?php foreach($admin_positions as $pos): ?>
                                        <option value="<?php echo $pos; ?>" <?php echo (isset($_POST['admin_position']) && $_POST['admin_position'] == $pos) ? 'selected' : ''; ?>>
                                            <?php echo $pos; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_campus">Campus</label>
                            <div class="input-with-icon">
                                <i class="fas fa-map-marker-alt"></i>
                                <select id="admin_campus" name="admin_campus">
                                    <option value="">-- Select Campus (Optional) --</option>
                                    <?php foreach($campuses as $camp): ?>
                                        <option value="<?php echo $camp; ?>" <?php echo (isset($_POST['admin_campus']) && $_POST['admin_campus'] == $camp) ? 'selected' : ''; ?>>
                                            <?php echo $camp; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="admin_bio">Bio / Introduction</label>
                            <div class="input-with-icon">
                                <i class="fas fa-align-left"></i>
                                <textarea id="admin_bio" name="admin_bio" rows="3" 
                                          placeholder="Tell us about your administrative experience"><?php echo isset($_POST['admin_bio']) ? htmlspecialchars($_POST['admin_bio']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-box" style="background: #c6f6d5; border-left-color: #38a169; color: #22543d;">
                        <i class="fas fa-check-circle"></i>
                        <strong>Note:</strong> Your account will be activated immediately. You will have full access to manage all athletic activities.
                    </div>
                </div>
                
                <!-- DANCE ADMIN-SPECIFIC FIELDS -->
                <div class="dance-admin-fields" id="dance-admin-fields" style="display: <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'dance_admin') ? 'block' : 'none'; ?>;">
                    <h4><i class="fas fa-star" style="color: #FFB347;"></i> Dance Administrator Information</h4>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="dance_admin_department">Department <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-building"></i>
                                <select id="dance_admin_department" name="dance_admin_department" required>
                                    <option value="">-- Select Department --</option>
                                    <option value="Dance Department">Dance Department</option>
                                    <option value="Cultural Affairs">Cultural Affairs</option>
                                    <option value="Performing Arts">Performing Arts</option>
                                    <option value="Student Affairs">Student Affairs</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dance_admin_position">Position <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-user-tie"></i>
                                <select id="dance_admin_position" name="dance_admin_position" required>
                                    <option value="">-- Select Position --</option>
                                    <option value="Dance Director">Dance Director</option>
                                    <option value="Cultural Affairs Coordinator">Cultural Affairs Coordinator</option>
                                    <option value="Performing Arts Manager">Performing Arts Manager</option>
                                    <option value="Dance Program Head">Dance Program Head</option>
                                    <option value="Events Coordinator">Events Coordinator</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dance_admin_campus">Campus</label>
                            <div class="input-with-icon">
                                <i class="fas fa-map-marker-alt"></i>
                                <select id="dance_admin_campus" name="dance_admin_campus">
                                    <option value="">-- Select Campus (Optional) --</option>
                                    <?php foreach($campuses as $camp): ?>
                                        <option value="<?php echo $camp; ?>" <?php echo (isset($_POST['dance_admin_campus']) && $_POST['dance_admin_campus'] == $camp) ? 'selected' : ''; ?>>
                                            <?php echo $camp; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="dance_admin_bio">Bio / Introduction</label>
                            <div class="input-with-icon">
                                <i class="fas fa-align-left"></i>
                                <textarea id="dance_admin_bio" name="dance_admin_bio" rows="3" 
                                          placeholder="Tell us about your experience in dance administration"><?php echo isset($_POST['dance_admin_bio']) ? htmlspecialchars($_POST['dance_admin_bio']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-box" style="background: #c6f6d5; border-left-color: #38a169; color: #22543d;">
                        <i class="fas fa-check-circle"></i>
                        <strong>Note:</strong> Your account will be activated immediately. You will have full access to manage all dance activities.
                    </div>
                </div>
                
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
    const courseData = <?php echo json_encode($colleges); ?>;

    // User Type Selection
    function selectUserType(type) {
        document.getElementById('user_type').value = type;
        
        document.querySelectorAll('.type-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        if (type === 'sport_coach') {
            document.querySelector('.type-sport-coach').classList.add('selected');
        } else if (type === 'dance_trainer') {
            document.querySelector('.type-dance-trainer').classList.add('selected');
        } else if (type === 'athletics_admin') {
            document.querySelector('.type-athletics-admin').classList.add('selected');
        } else if (type === 'dance_admin') {
            document.querySelector('.type-dance-admin').classList.add('selected');
        } else {
            document.querySelector('.type-' + type).classList.add('selected');
        }
        
        // Show/hide appropriate fields
        document.getElementById('student-fields').style.display = type === 'student' ? 'block' : 'none';
        document.getElementById('sport-coach-fields').style.display = type === 'sport_coach' ? 'block' : 'none';
        document.getElementById('dance-trainer-fields').style.display = type === 'dance_trainer' ? 'block' : 'none';
        document.getElementById('athletics-admin-fields').style.display = type === 'athletics_admin' ? 'block' : 'none';
        document.getElementById('dance-admin-fields').style.display = type === 'dance_admin' ? 'block' : 'none';
        
        // Update ID format hint
        const idHint = document.getElementById('id-number-hint');
        if (type === 'student') {
            idHint.textContent = 'Format: YYYY-##### (Example: 2023-00123)';
        } else if (type === 'sport_coach') {
            idHint.textContent = 'Format: ABC-YYYY### (Example: COA-2024001)';
        } else if (type === 'dance_trainer') {
            idHint.textContent = 'Format: ABC-YYYY### (Example: DAN-2024001)';
        } else if (type === 'athletics_admin') {
            idHint.textContent = 'Format: ATH-### (Example: ATH-001)';
        } else if (type === 'dance_admin') {
            idHint.textContent = 'Format: DAN-### (Example: DAN-001)';
        }
        
        // Update required fields after a short delay
        setTimeout(updateRequiredFields, 50);
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
        
        // Update required fields after a short delay
        setTimeout(updateRequiredFields, 50);
    }

    // Update required attributes based on visibility
    function updateRequiredFields() {
        // First, REMOVE required from ALL conditional fields
        const primarySport = document.getElementById('primary_sport');
        const approvingCoach = document.getElementById('approving_coach');
        const danceTroupe = document.getElementById('dance_troupe');
        const approvingDanceTrainer = document.getElementById('approving_dance_trainer');
        const sportCoachSport = document.getElementById('primary_sport_coach');
        const sportCoachYears = document.getElementById('years_experience');
        const danceSpecialization = document.getElementById('dance_specialization');
        const danceExperience = document.getElementById('dance_experience');
        const adminDepartment = document.getElementById('admin_department');
        const adminPosition = document.getElementById('admin_position');
        const danceAdminDepartment = document.getElementById('dance_admin_department');
        const danceAdminPosition = document.getElementById('dance_admin_position');
        
        if (primarySport) primarySport.removeAttribute('required');
        if (approvingCoach) approvingCoach.removeAttribute('required');
        if (danceTroupe) danceTroupe.removeAttribute('required');
        if (approvingDanceTrainer) approvingDanceTrainer.removeAttribute('required');
        if (sportCoachSport) sportCoachSport.removeAttribute('required');
        if (sportCoachYears) sportCoachYears.removeAttribute('required');
        if (danceSpecialization) danceSpecialization.removeAttribute('required');
        if (danceExperience) danceExperience.removeAttribute('required');
        if (adminDepartment) adminDepartment.removeAttribute('required');
        if (adminPosition) adminPosition.removeAttribute('required');
        if (danceAdminDepartment) danceAdminDepartment.removeAttribute('required');
        if (danceAdminPosition) danceAdminPosition.removeAttribute('required');
        
        // Get current state
        const userType = document.getElementById('user_type').value;
        const studentType = document.getElementById('student_type').value;
        
        // Apply required based on visible fields
        if (userType === 'student') {
            if (studentType === 'athlete') {
                const athleteFields = document.getElementById('athlete-fields');
                if (athleteFields && athleteFields.style.display === 'block') {
                    if (primarySport) primarySport.setAttribute('required', 'required');
                    if (approvingCoach) approvingCoach.setAttribute('required', 'required');
                }
            } else if (studentType === 'dancer') {
                const dancerFields = document.getElementById('dancer-fields');
                if (dancerFields && dancerFields.style.display === 'block') {
                    if (danceTroupe) danceTroupe.setAttribute('required', 'required');
                    if (approvingDanceTrainer) approvingDanceTrainer.setAttribute('required', 'required');
                }
            }
        } else if (userType === 'sport_coach') {
            if (sportCoachSport) sportCoachSport.setAttribute('required', 'required');
            if (sportCoachYears) sportCoachYears.setAttribute('required', 'required');
        } else if (userType === 'dance_trainer') {
            if (danceSpecialization) danceSpecialization.setAttribute('required', 'required');
            if (danceExperience) danceExperience.setAttribute('required', 'required');
        } else if (userType === 'athletics_admin') {
            if (adminDepartment) adminDepartment.setAttribute('required', 'required');
            if (adminPosition) adminPosition.setAttribute('required', 'required');
        } else if (userType === 'dance_admin') {
            if (danceAdminDepartment) danceAdminDepartment.setAttribute('required', 'required');
            if (danceAdminPosition) danceAdminPosition.setAttribute('required', 'required');
        }
    }

    // Update courses based on selected college
    function updateCourses() {
        const collegeSelect = document.getElementById('college');
        const courseSelect = document.getElementById('course');
        const selectedCollege = collegeSelect.value;
        
        courseSelect.innerHTML = '<option value="">-- Select Course --</option>';
        
        if (selectedCollege && courseData[selectedCollege] && courseData[selectedCollege].courses) {
            courseData[selectedCollege].courses.forEach(course => {
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

    // Form submit handler to clean up empty values
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        // For all optional fields, if they're empty, ensure they're properly handled
        const optionalFields = ['athlete_category', 'jersey_number', 
                               'dance_role', 'section', 'middle_name', 'primary_position'];
        
        optionalFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && field.value.trim() === '') {
                // Empty value is fine - PHP will handle it with the !empty() check
            }
        });
    });

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize position field if sport is selected
        if (document.getElementById('primary_sport') && document.getElementById('primary_sport').value) {
            updatePositionField();
        }
        
        // Initialize courses if college is selected
        const college = document.getElementById('college');
        if (college && college.value) {
            updateCourses();
        }
        
        // Set ID hint based on default user type
        const idHint = document.getElementById('id-number-hint');
        const userType = document.getElementById('user_type').value;
        if (userType === 'athletics_admin') {
            idHint.textContent = 'Format: ATH-### (Example: ATH-001)';
        } else if (userType === 'dance_admin') {
            idHint.textContent = 'Format: DAN-### (Example: DAN-001)';
        } else if (userType === 'sport_coach') {
            idHint.textContent = 'Format: ABC-YYYY### (Example: COA-2024001)';
        } else if (userType === 'dance_trainer') {
            idHint.textContent = 'Format: ABC-YYYY### (Example: DAN-2024001)';
        } else {
            idHint.textContent = 'Format: YYYY-##### (Example: 2023-00123)';
        }
        
        // Initialize required fields with slight delay
        setTimeout(updateRequiredFields, 100);
        
        // Add change event to primary sport to update position field
        const primarySport = document.getElementById('primary_sport');
        if (primarySport) {
            primarySport.addEventListener('change', updatePositionField);
        }
        
        // Add change event to college dropdown
        const collegeSelect = document.getElementById('college');
        if (collegeSelect) {
            collegeSelect.addEventListener('change', updateCourses);
        }
    });
    </script>
</body>
</html>