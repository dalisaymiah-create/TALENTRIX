<?php
session_start();
require_once 'db.php';

// If user is already logged in, redirect
if(isset($_SESSION['user_id'])) {
    echo '<script>window.location.href = "dashboard.php";</script>';
    exit();
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = isset($_POST['user_type']) ? $_POST['user_type'] : 'student';
    $id_number = trim($_POST['id_number']);
    
    // Common fields
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $institution = isset($_POST['institution']) ? trim($_POST['institution']) : '';
    $campus = isset($_POST['campus']) ? trim($_POST['campus']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    
    // Faculty-specific fields
    $category = $user_type;
    $position = '';
    $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';
    $years_experience = isset($_POST['years_experience']) ? (int)$_POST['years_experience'] : 0;
    
    // Other fields (default values)
    $college = '';
    $course = '';
    $program_sport = '';
    $admin_level = '';
    $is_verified = 0;
    $verification_token = bin2hex(random_bytes(16));
    $status = 'pending';
    

    $errors = [];
    
    if(empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password) || empty($id_number)) {
        $errors[] = 'All required fields must be filled!';
    }
    
    if(strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters!';
    }
    
    if($password !== $confirm_password) {
        $errors[] = 'Passwords do not match!';
    }
    
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format!';
    }
    
    if($user_type == 'student') {
        if(!preg_match('/^\d{4}-\d{5}$/', $id_number)) {
            $errors[] = 'Invalid Student ID format. Must be: YYYY-##### (Example: 2023-00123)';
        }
        // Check if contains letters
        if(preg_match('/[A-Za-z]/', $id_number)) {
            $errors[] = 'Student ID should contain numbers and dash only (no letters).';
        }
    } elseif($user_type == 'faculty') {
        if(!preg_match('/^[A-Za-z]{2,4}-\d{4}\d{0,3}$/', $id_number)) {
            $errors[] = 'Invalid Faculty ID format. Must be: ABC-YYYY### (Example: FAC-2024001)';
        }
        $id_number = strtoupper($id_number);
    } elseif($user_type == 'admin') {
        if(!preg_match('/^ADMIN-\d{3}$/i', $id_number)) {
            $errors[] = 'Invalid Admin ID format. Must be: ADMIN-### (Example: ADMIN-001)';
        }
        $id_number = strtoupper($id_number);
    }
    
    // Faculty-specific validation
    if($user_type == 'faculty') {
        if(empty($institution) || $institution == '-- Select Institution --') {
            $errors[] = 'Please select an institution!';
        }
        if(empty($campus) || $campus == '-- Select Campus --') {
            $errors[] = 'Please select a campus!';
        }
        if(empty($department) || $department == '-- Select Department --') {
            $errors[] = 'Please select a department!';
        }
        if(empty($specialization) || $specialization == '-- Select Specialization --') {
            $errors[] = 'Please select a specialization!';
        }
        $position = $specialization;
    }
    
    // Check if email already exists
    if(empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if($stmt->rowCount() > 0) {
            $errors[] = 'Email already registered!';
        }
    }
    
    // Check if username already exists
    if(empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if($stmt->rowCount() > 0) {
            $errors[] = 'Username already taken!';
        }
    }
    
    // Check if ID number already exists
    if(empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $stmt->execute([$id_number]);
        
        if($stmt->rowCount() > 0) {
            $errors[] = 'ID Number already registered!';
        }
    }
    
    if(empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user with available fields
        $stmt = $pdo->prepare("INSERT INTO users 
            (username, email, password, id_number, first_name, middle_name, last_name, 
             category, institution, campus, department, user_type, position,
             college, course, program_sport, admin_level, is_verified, verification_token, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        try {
            if($stmt->execute([
                $username, $email, $hashed_password, $id_number, $first_name, $middle_name, $last_name,
                $category, $institution, $campus, $department, $user_type, $position,
                $college, $course, $program_sport, $admin_level, $is_verified, $verification_token, $status
            ])) {
                $user_id = $pdo->lastInsertId();
                
                // Insert into coaches table if faculty
                if($user_type == 'faculty') {
                    $stmt2 = $pdo->prepare("INSERT INTO coaches 
                        (user_id, specialization, years_experience, created_at) 
                        VALUES (?, ?, ?, NOW())");
                    $stmt2->execute([$user_id, $specialization, $years_experience]);
                }
                
                $_SESSION['registration_success'] = 'Registration successful! Your account is pending verification.';
                
                echo '<!DOCTYPE html>
                <html>
                <head>
                    <title>Registration Successful</title>
                    <script>
                        alert("✅ Registration successful! Your account is pending verification.");
                        window.location.href = "login.php";
                    </script>
                </head>
                <body>
                    <p style="text-align: center; padding: 50px; font-family: Arial;">
                        Registration successful! Redirecting to login page...
                        <br><br>
                        <a href="login.php">Click here if not redirected</a>
                    </p>
                </body>
                </html>';
                exit();
                
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    
    if(!empty($errors)) {
        $error = implode('<br>', $errors);
    }
}

function getInstitutions($pdo) {
    // Check if database has records
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM institutions");
    $result = $stmt->fetch();
    
    if($result['count'] > 0) {
        // Get from database
        $stmt = $pdo->query("SELECT id, name FROM institutions ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        return [
            ['id' => 1, 'name' => 'Bohol Island State University'],
            ['id' => 2, 'name' => 'Other Institution']
        ];
    }
}

function getCampuses($pdo, $institution_id = null) {
    // Check if database has records
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM campuses");
    $result = $stmt->fetch();
    
    if($result['count'] > 0) {
        // Get from database
        if($institution_id) {
            $stmt = $pdo->prepare("SELECT id, name FROM campuses WHERE institution_id = ? ORDER BY name");
            $stmt->execute([$institution_id]);
        } else {
            $stmt = $pdo->query("SELECT id, name FROM campuses ORDER BY name");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Hardcoded options for BISU
        $bisuCampuses = [
            ['id' => 1, 'name' => 'Tagbilaran Campus'],
            ['id' => 2, 'name' => 'Candijay Campus'],
            ['id' => 3, 'name' => 'Balilihan Campus'],
            ['id' => 4, 'name' => 'Bilar Campus'],
            ['id' => 5, 'name' => 'Calape Campus'],
            ['id' => 6, 'name' => 'Clarin Campus']
        ];
        
        if($institution_id == 1) { // BISU
            return $bisuCampuses;
        } else {
            return [['id' => 7, 'name' => 'Main Campus']];
        }
    }
}

function getDepartments($pdo, $campus_id = null) {
    // Check if database has records
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments");
    $result = $stmt->fetch();
    
    if($result['count'] > 0) {
        // Get from database
        if($campus_id) {
            $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE campus_id = ? ORDER BY name");
            $stmt->execute([$campus_id]);
        } else {
            $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Hardcoded departments
        return [
            ['id' => 1, 'name' => 'College of Business Management'],
            ['id' => 2, 'name' => 'College of Teacher Education'],
            ['id' => 3, 'name' => 'College of Fisheries and Marine Sciences'],
            ['id' => 4, 'name' => 'College of Sciences'],
            ['id' => 5, 'name' => 'Other Department']
        ];
    }
}

function getSpecializations($pdo) {
    // Check if database has records
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM specializations");
    $result = $stmt->fetch();
    
    if($result['count'] > 0) {
        // Get from database
        $stmt = $pdo->query("SELECT id, name FROM specializations ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Hardcoded specializations
        return [
            ['id' => 1, 'name' => 'Sports Coaching'],
            ['id' => 2, 'name' => 'Career Counseling'],
            ['id' => 3, 'name' => 'Leadership Training'],
            ['id' => 4, 'name' => 'Skill Development'],
            ['id' => 5, 'name' => 'Other Specialization']
        ];
    }
}

$institutions = getInstitutions($pdo);
$campuses = getCampuses($pdo);
$departments = getDepartments($pdo);
$specializations = getSpecializations($pdo);

$years_experience = range(0, 50);
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
            max-width: 1000px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            display: flex;
            min-height: auto;
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
        }
        
        .type-option {
            flex: 1;
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
        .type-faculty .type-icon { color: #3b82f6; }
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
            margin-bottom: 20px;
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
        
        /* Faculty-specific fields */
        .faculty-fields {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 4px solid #3b82f6;
        }
        
        .faculty-fields h4 {
            color: #3b82f6;
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        
        /* Button */
        .btn-signup {
            width: 100%;
            padding: 14px;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
        }
        
        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 20px;
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
        }
        
        @media (max-width: 480px) {
            .user-type-selector {
                flex-direction: column;
            }
            
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
                    <i class="fas fa-graduation-cap"></i>
                    <div class="feature-text">
                        <h4>For Students</h4>
                        <p>Access learning materials and track progress</p>
                    </div>
                </div>
                
                <div class="feature">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <div class="feature-text">
                        <h4>For Faculty</h4>
                        <p>Manage courses and evaluate students</p>
                    </div>
                </div>
                
                <div class="feature">
                    <i class="fas fa-user-shield"></i>
                    <div class="feature-text">
                        <h4>For Administrators</h4>
                        <p>Oversee system operations and analytics</p>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: auto; text-align: center;">
                <p>Already have an account?</p>
                <a href="login.php" style="color: white; text-decoration: underline; font-weight: 600;">Sign In Here</a>
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
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" onsubmit="return validateForm()">
                <!-- User Type Selection -->
                <div class="user-type-selector">
                    <div class="type-option type-student <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'student') ? 'selected' : 'selected'; ?>" onclick="selectUserType('student')">
                        <div class="type-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="type-label">Student</div>
                    </div>
                    
                    <div class="type-option type-faculty <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'faculty') ? 'selected' : ''; ?>" onclick="selectUserType('faculty')">
                        <div class="type-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="type-label">Faculty</div>
                    </div>
                    
                    <div class="type-option type-admin <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'admin') ? 'selected' : ''; ?>" onclick="selectUserType('admin')">
                        <div class="type-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="type-label">Administrator</div>
                    </div>
                </div>
                
                <input type="hidden" name="user_type" id="user_type" value="<?php echo isset($_POST['user_type']) ? $_POST['user_type'] : 'student'; ?>">
                
                <!-- Basic Information -->
                <div class="form-grid">
                    <!-- Username -->
                    <div class="form-group">
                        <label for="username">Username <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="username" name="username" 
                                   placeholder="Choose a username" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                   required>
                        </div>
                        <div class="input-hint">Your unique username</div>
                    </div>
                    
                    <!-- ID Number -->
                    <div class="form-group">
                        <label for="id_number">ID Number <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-id-card"></i>
                            <input type="text" id="id_number" name="id_number" 
                                   placeholder="e.g., 2023-00123" 
                                   value="<?php echo isset($_POST['id_number']) ? htmlspecialchars($_POST['id_number']) : ''; ?>" 
                                   required
                                   oninput="validateIDNumber(this.value, document.getElementById('user_type').value)">
                        </div>
                        <div class="input-hint" id="id-number-hint">Your official ID number</div>
                        <div id="id-number-error" class="input-hint" style="color: #ef4444; margin-top: 5px;"></div>
                    </div>
                    
                    <!-- Email -->
                    <div class="form-group">
                        <label for="email">Email <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" 
                                   placeholder="your.email@institution.edu" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   required>
                        </div>
                        <div class="input-hint" id="email-hint">Use your official email</div>
                    </div>
                    
                    <!-- First Name -->
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
                    
                    <!-- Last Name -->
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
                    
                    <!-- Password -->
                    <div class="form-group">
                        <label for="password">Password <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" 
                                   placeholder="At least 8 characters" 
                                   required>
                        </div>
                        <div class="input-hint">Use a strong password</div>
                        <div id="password-strength-indicator" class="input-hint"></div>
                    </div>
                    
                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span>*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm your password" 
                                   required>
                        </div>
                        <div class="input-hint">Re-enter your password</div>
                        <div id="password-match-indicator" class="input-hint"></div>
                    </div>
                </div>
                
                <!-- Faculty-Specific Fields (Hidden by default) -->
                <div class="faculty-fields" id="faculty-fields">
                    <h4><i class="fas fa-chalkboard-teacher"></i> Faculty Information</h4>
                    
                    <div class="form-grid">
                        <!-- Middle Name -->
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <div class="input-with-icon">
                                <i class="fas fa-user"></i>
                                <input type="text" id="middle_name" name="middle_name" 
                                       placeholder="Enter your middle name (optional)" 
                                       value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                            </div>
                            <div class="input-hint">Optional</div>
                        </div>
                        
                        <!-- Institution -->
                        <div class="form-group">
                            <label for="institution">Institution <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-university"></i>
                                <select id="institution" name="institution" onchange="updateCampuses()">
                                    <option value="">-- Select Institution --</option>
                                    <?php foreach($institutions as $inst): ?>
                                        <option value="<?php echo $inst['name']; ?>" 
                                            <?php echo (isset($_POST['institution']) && $_POST['institution'] == $inst['name']) ? 'selected' : ''; ?>
                                            data-id="<?php echo $inst['id']; ?>">
                                            <?php echo htmlspecialchars($inst['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Campus -->
                        <div class="form-group">
                            <label for="campus">Campus <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-map-marker-alt"></i>
                                <select id="campus" name="campus" onchange="updateDepartments()">
                                    <option value="">-- Select Campus --</option>
                                    <!-- Campuses will be loaded dynamically based on institution -->
                                    <?php foreach($campuses as $camp): ?>
                                        <option value="<?php echo $camp['name']; ?>" 
                                            <?php echo (isset($_POST['campus']) && $_POST['campus'] == $camp['name']) ? 'selected' : ''; ?>
                                            data-id="<?php echo $camp['id']; ?>">
                                            <?php echo htmlspecialchars($camp['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Department -->
                        <div class="form-group">
                            <label for="department">Department <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-building"></i>
                                <select id="department" name="department">
                                    <option value="">-- Select Department --</option>
                                    <?php foreach($departments as $dept): ?>
                                        <option value="<?php echo $dept['name']; ?>" 
                                            <?php echo (isset($_POST['department']) && $_POST['department'] == $dept['name']) ? 'selected' : ''; ?>
                                            data-id="<?php echo $dept['id']; ?>">
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Specialization -->
                        <div class="form-group">
                            <label for="specialization">Specialization/Expertise <span>*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-star"></i>
                                <select id="specialization" name="specialization">
                                    <option value="">-- Select Specialization --</option>
                                    <?php foreach($specializations as $spec): ?>
                                        <option value="<?php echo $spec['name']; ?>" 
                                            <?php echo (isset($_POST['specialization']) && $_POST['specialization'] == $spec['name']) ? 'selected' : ''; ?>
                                            data-id="<?php echo $spec['id']; ?>">
                                            <?php echo htmlspecialchars($spec['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="input-hint">Will be stored as Position</div>
                        </div>
                        
                        <!-- Years of Experience -->
                        <div class="form-group">
                            <label for="years_experience">Years of Experience</label>
                            <div class="input-with-icon">
                                <i class="fas fa-calendar-alt"></i>
                                <select id="years_experience" name="years_experience">
                                    <option value="">-- Select Years --</option>
                                    <?php foreach($years_experience as $year): ?>
                                        <option value="<?php echo $year; ?>" 
                                            <?php echo (isset($_POST['years_experience']) && $_POST['years_experience'] == $year) ? 'selected' : ''; ?>>
                                            <?php echo $year; ?> year<?php echo $year != 1 ? 's' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-signup" id="submit-btn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </div>
    </div>
    
    <script>
        // Function to restrict input to numbers and dash only (for student)
        function restrictToNumbers(e) {
            const key = e.key;
            // Allow: numbers (0-9), dash (-), backspace, delete, tab, arrow keys
            const allowedKeys = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '-', 
                                'Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 
                                'ArrowUp', 'ArrowDown'];
            
            if (!allowedKeys.includes(key)) {
                e.preventDefault();
            }
        }
        
        // Remove restriction function
        function removeNumberRestriction(e) {
            const idNumberField = document.getElementById('id_number');
            idNumberField.removeEventListener('keypress', restrictToNumbers);
        }
        
        function selectUserType(type) {
            document.getElementById('user_type').value = type;
            
            // Remove selected class from all options
            document.querySelectorAll('.type-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            document.querySelector(`.type-${type}`).classList.add('selected');
            
            // Show/hide faculty fields
            const facultyFields = document.getElementById('faculty-fields');
            const idNumberField = document.getElementById('id_number');
            const idHint = document.getElementById('id-number-hint');
            
            if(type === 'faculty') {
                facultyFields.style.display = 'block';
                updateFormHints(type);
                idNumberField.placeholder = 'e.g., FAC-2024001';
                if(idHint) idHint.textContent = 'Format: ABC-YYYY### (Example: FAC-2024001)';
                
                // Remove number restriction for faculty
                idNumberField.removeEventListener('keypress', restrictToNumbers);
                
                // Initialize campuses and departments based on selected institution
                updateCampuses();
            } else if(type === 'student') {
                facultyFields.style.display = 'none';
                updateFormHints(type);
                idNumberField.placeholder = 'e.g., 2023-00123 (Numbers and dash only)';
                if(idHint) idHint.textContent = 'Format: YYYY-##### (Example: 2023-00123)';
                
                // Add number restriction for student
                idNumberField.removeEventListener('keypress', restrictToNumbers); // Remove first to avoid duplicates
                idNumberField.addEventListener('keypress', restrictToNumbers);
                
            } else {
                facultyFields.style.display = 'none';
                updateFormHints(type);
                idNumberField.placeholder = 'e.g., ADMIN-001';
                if(idHint) idHint.textContent = 'Format: ADMIN-### (Example: ADMIN-001)';
                
                // Remove number restriction for admin
                idNumberField.removeEventListener('keypress', restrictToNumbers);
            }
            
            // Update submit button text
            const submitBtn = document.getElementById('submit-btn');
            if(type === 'faculty') {
                submitBtn.innerHTML = '<i class="fas fa-chalkboard-teacher"></i> Register as Faculty';
            } else if(type === 'admin') {
                submitBtn.innerHTML = '<i class="fas fa-user-shield"></i> Register as Administrator';
            } else {
                submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
            }
            
            // Clear and re-validate ID number
            validateIDNumber(idNumberField.value, type);
        }
        
        function updateFormHints(userType) {
            const emailInput = document.getElementById('email');
            const emailHint = document.getElementById('email-hint');
            
            if(userType === 'student') {
                emailInput.placeholder = 'your.student@school.edu';
                emailHint.textContent = 'Use your official student email';
            } else if(userType === 'faculty') {
                emailInput.placeholder = 'faculty.name@school.edu';
                emailHint.textContent = 'Use your official faculty email';
            } else if(userType === 'admin') {
                emailInput.placeholder = 'admin@institution.edu';
                emailHint.textContent = 'Use your administrative email';
            }
        }
        
        // Real-time ID number validation
        function validateIDNumber(idNumber, userType) {
            const errorElement = document.getElementById('id-number-error');
            const idNumberField = document.getElementById('id_number');
            
            if (!errorElement) {
                const errorDiv = document.createElement('div');
                errorDiv.id = 'id-number-error';
                errorDiv.className = 'input-hint';
                errorDiv.style.color = '#ef4444';
                errorDiv.style.marginTop = '5px';
                idNumberField.parentNode.appendChild(errorDiv);
            }
            
            let isValid = false;
            let errorMessage = '';
            
            if (idNumber === '') {
                errorMessage = '';
            } else if (userType === 'student') {
                // Student: YYYY-##### (e.g., 2023-00123)
                const studentRegex = /^\d{4}-\d{5}$/;
                isValid = studentRegex.test(idNumber);
                if (!isValid) {
                    errorMessage = '❌ Invalid format. Use: YYYY-##### (Example: 2023-00123)';
                    errorElement.style.color = '#ef4444';
                } else {
                    errorMessage = '✓ Valid student ID format';
                    errorElement.style.color = '#10b981';
                }
            } else if (userType === 'faculty') {
                // Faculty: ABC-YYYY### (e.g., FAC-2024001)
                const facultyRegex = /^[A-Za-z]{2,4}-\d{4}\d{0,3}$/;
                isValid = facultyRegex.test(idNumber.toUpperCase());
                if (!isValid) {
                    errorMessage = '❌ Invalid format. Use: ABC-YYYY### (Example: FAC-2024001)';
                    errorElement.style.color = '#ef4444';
                } else {
                    errorMessage = '✓ Valid faculty ID format';
                    errorElement.style.color = '#10b981';
                }
            } else if (userType === 'admin') {
                // Admin: ADMIN-### (e.g., ADMIN-001)
                const adminRegex = /^ADMIN-\d{3}$/i;
                isValid = adminRegex.test(idNumber.toUpperCase());
                if (!isValid) {
                    errorMessage = '❌ Invalid format. Use: ADMIN-### (Example: ADMIN-001)';
                    errorElement.style.color = '#ef4444';
                } else {
                    errorMessage = '✓ Valid admin ID format';
                    errorElement.style.color = '#10b981';
                }
            }
            
            if (errorElement) {
                errorElement.textContent = errorMessage;
            }
            
            return isValid;
        }
        
        // Form validation before submission
        function validateForm() {
            const idNumber = document.getElementById('id_number').value;
            const userType = document.getElementById('user_type').value;
            const isValid = validateIDNumber(idNumber, userType);
            
            if (!isValid && idNumber !== '') {
                alert('Please enter a valid ID number format for your user type.');
                document.getElementById('id_number').focus();
                return false;
            }
            
            // Check if passwords match
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                document.getElementById('confirm_password').focus();
                return false;
            }
            
            return true;
        }
        
        // Campuses data (hardcoded for BISU)
        const campusesData = {
            'Bohol Island State University': [
                'Tagbilaran Campus',
                'Candijay Campus',
                'Balilihan Campus',
                'Bilar Campus',
                'Calape Campus',
                'Clarin Campus'
            ],
            'Other Institution': [
                'Main Campus'
            ]
        };
        
        // Departments data (same for all campuses for now)
        const departmentsData = [
            'College of Business Management',
            'College of Teacher Education',
            'College of Fisheries and Marine Sciences',
            'College of Sciences',
            'Other Department'
        ];
        
        function updateCampuses() {
            const institutionSelect = document.getElementById('institution');
            const campusSelect = document.getElementById('campus');
            const selectedInstitution = institutionSelect.value;
            
            // Clear existing options except the first one
            while(campusSelect.options.length > 1) {
                campusSelect.remove(1);
            }
            
            // Reset department dropdown
            updateDepartments(true);
            
            if(selectedInstitution && campusesData[selectedInstitution]) {
                // Add campuses for selected institution
                campusesData[selectedInstitution].forEach(campus => {
                    const option = document.createElement('option');
                    option.value = campus;
                    option.textContent = campus;
                    campusSelect.appendChild(option);
                });
                
                campusSelect.disabled = false;
                
                // If there's a previously selected campus in the same institution, try to select it
                if(<?php echo isset($_POST['campus']) ? "'" . $_POST['campus'] . "'" : 'null'; ?>) {
                    const previousCampus = <?php echo isset($_POST['campus']) ? "'" . $_POST['campus'] . "'" : 'null'; ?>;
                    for(let i = 0; i < campusSelect.options.length; i++) {
                        if(campusSelect.options[i].value === previousCampus) {
                            campusSelect.selectedIndex = i;
                            break;
                        }
                    }
                }
            } else {
                campusSelect.disabled = true;
            }
        }
        
        function updateDepartments(resetOnly = false) {
            const departmentSelect = document.getElementById('department');
            const campusSelect = document.getElementById('campus');
            
            // Clear existing options except the first one
            while(departmentSelect.options.length > 1) {
                departmentSelect.remove(1);
            }
            
            if(!resetOnly) {
                const selectedCampus = campusSelect.value;
                
                if(selectedCampus) {
                    // Add all departments (for now, same for all campuses)
                    departmentsData.forEach(department => {
                        const option = document.createElement('option');
                        option.value = department;
                        option.textContent = department;
                        departmentSelect.appendChild(option);
                    });
                    
                    departmentSelect.disabled = false;
                    
                    // If there's a previously selected department, try to select it
                    if(<?php echo isset($_POST['department']) ? "'" . $_POST['department'] . "'" : 'null'; ?>) {
                        const previousDept = <?php echo isset($_POST['department']) ? "'" . $_POST['department'] . "'" : 'null'; ?>;
                        for(let i = 0; i < departmentSelect.options.length; i++) {
                            if(departmentSelect.options[i].value === previousDept) {
                                departmentSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                } else {
                    departmentSelect.disabled = true;
                }
            } else {
                departmentSelect.disabled = true;
            }
        }
        
        // Initialize based on current user type
        document.addEventListener('DOMContentLoaded', function() {
            const userType = document.getElementById('user_type').value;
            selectUserType(userType);
            
            // Real-time ID number validation
            const idNumberField = document.getElementById('id_number');
            idNumberField.addEventListener('input', function() {
                validateIDNumber(this.value, document.getElementById('user_type').value);
            });
            
            // Password strength indicator
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            
            if(passwordInput) {
                passwordInput.addEventListener('input', checkPasswordStrength);
            }
            
            if(confirmInput) {
                confirmInput.addEventListener('input', checkPasswordMatch);
            }
            
            // If institution is already selected (from form submission), update campuses
            const institutionSelect = document.getElementById('institution');
            if(institutionSelect.value) {
                updateCampuses();
            }
            
            // If campus is already selected (from form submission), update departments
            const campusSelect = document.getElementById('campus');
            if(campusSelect.value) {
                updateDepartments();
            }
        });
        
        function checkPasswordStrength() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            let message = '';
            let color = '';
            
            if(password.length === 0) {
                return;
            }
            
            if(strength === 'weak') {
                message = 'Password strength: Weak';
                color = '#ef4444';
            } else if(strength === 'medium') {
                message = 'Password strength: Medium';
                color = '#f59e0b';
            } else if(strength === 'strong') {
                message = 'Password strength: Strong';
                color = '#10b981';
            }
            
            let indicator = document.getElementById('password-strength-indicator');
            if(!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'password-strength-indicator';
                indicator.className = 'input-hint';
                this.parentNode.insertAdjacentElement('afterend', indicator);
            }
            
            indicator.textContent = message;
            indicator.style.color = color;
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            
            if(confirm.length === 0) {
                return;
            }
            
            let indicator = document.getElementById('password-match-indicator');
            if(!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'password-match-indicator';
                indicator.className = 'input-hint';
                this.parentNode.insertAdjacentElement('afterend', indicator);
            }
            
            if(password === confirm) {
                indicator.textContent = '✓ Passwords match';
                indicator.style.color = '#10b981';
            } else {
                indicator.textContent = '❌ Passwords do not match';
                indicator.style.color = '#ef4444';
            }
        }
        
        function calculatePasswordStrength(password) {
            let strength = 0;
            
            if(password.length >= 8) strength++;
            if(/[A-Z]/.test(password)) strength++;
            if(/[0-9]/.test(password)) strength++;
            if(/[^A-Za-z0-9]/.test(password)) strength++;
            
            if(strength < 2) return 'weak';
            if(strength < 4) return 'medium';
            return 'strong';
        }
    </script>
</body>
</html>