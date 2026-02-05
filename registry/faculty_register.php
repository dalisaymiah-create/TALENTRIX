<?php
// faculty_register.php
session_start();
require_once 'db.php';

$error = '';
$success = '';

// Static data for faculty
$institutions = ['Bohol Island State University'];
$campuses = ['-- Select Campus --', 'Tagbilaran Campus', 'Candijay Campus', 'Balilihan Campus', 'Bilar Campus', 'Calape Campus', 'Clarin Campus'];
$departments = ['CBM', 'CTE', 'CFMS', 'COS'];
$specializations = [
    'Sports Coaching',
    'Academic Advising',
    'Career Counseling',
    'Research Mentoring',
    'Leadership Training',
    'Skill Development',
    'Project Supervision',
    'Thesis Advising'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = trim($_POST['email']);
    $employee_id = trim($_POST['employee_id'] ?? 'FAC-' . date('YmdHis'));
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $institution = trim($_POST['institution'] ?? '');
    $campus = trim($_POST['campus'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $years_experience = intval($_POST['years_experience'] ?? 0);
    $bio = trim($_POST['bio'] ?? '');
    $user_type = 'faculty';
    $is_verified = 0; // Faculty needs admin approval

    // Validation
    if(empty($username) || empty($password) || empty($email) || empty($first_name) || empty($last_name)) {
        $error = "Please fill in all required fields";
    } elseif($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif(strlen($password) < 8) {
        $error = "Password must be at least 8 characters";
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $error = "Username or email already exists!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate verification token
            $verification_token = bin2hex(random_bytes(32));
            
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Insert into users table
                $stmt = $pdo->prepare("INSERT INTO users 
                    (username, password, email, id_number, first_name, middle_name, last_name, 
                     institution, campus, department, user_type, is_verified, verification_token) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([$username, $hashed_password, $email, $employee_id, 
                              $first_name, $middle_name, $last_name, 
                              $institution, $campus, $department, 
                              $user_type, $is_verified, $verification_token]);
                
                $user_id = $pdo->lastInsertId();
                
                // Insert into coaches table
                $stmt = $pdo->prepare("INSERT INTO coaches 
                    (user_id, specialization, years_experience, bio) 
                    VALUES (?, ?, ?, ?)");
                
                $stmt->execute([$user_id, $specialization, $years_experience, $bio]);
                
                // Commit transaction
                $pdo->commit();
                
                // SUCCESS - Redirect to login page
                header('Location: login.php?registered=success&type=faculty');
                exit();
                
            } catch (Exception $e) {
                // Rollback on error
                $pdo->rollBack();
                $error = "Registration failed. Please try again. Error: " . $e->getMessage();
            }
        }
    }
}

// Check if already logged in
if(isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// For form repopulation
$selected_specialization = $_POST['specialization'] ?? '';
$selected_years = $_POST['years_experience'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Faculty/Coach Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* TALENTRIX Registration Styles */
        .talentrix-register-page {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .talentrix-register-container {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .talentrix-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .talentrix-logo {
            font-size: 32px;
            font-weight: 800;
            color: #0a2540;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .talentrix-subtitle {
            font-size: 18px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 20px;
        }
        
        .user-type-badge {
            display: inline-block;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .talentrix-form {
            margin-bottom: 20px;
        }
        
        .talentrix-input-group {
            margin-bottom: 15px;
        }
        
        .talentrix-input-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }
        
        .required-star {
            color: #e53e3e;
            margin-left: 2px;
        }
        
        .talentrix-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f8fafc;
        }
        
        .talentrix-input:focus {
            border-color: #8b5cf6;
            background: white;
            outline: none;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .talentrix-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            background: #f8fafc;
            color: #4a5568;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .talentrix-select:focus {
            border-color: #8b5cf6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .talentrix-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            min-height: 100px;
            resize: vertical;
            background: #f8fafc;
        }
        
        .talentrix-textarea:focus {
            border-color: #8b5cf6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .talentrix-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 20px;
        }
        
        .talentrix-btn:hover {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
        }
        
        .talentrix-links {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: #4a5568;
        }
        
        .talentrix-links a {
            color: #8b5cf6;
            text-decoration: none;
            font-weight: 600;
        }
        
        .talentrix-links a:hover {
            text-decoration: underline;
        }
        
        .talentrix-error {
            background: #fed7d7;
            color: #c53030;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c53030;
        }
        
        .talentrix-success {
            background: #c6f6d5;
            color: #22543d;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #38a169;
        }
        
        .talentrix-hint {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
            font-style: italic;
        }
        
        .account-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
        }
        
        .account-type-btn {
            flex: 1;
            padding: 12px;
            text-align: center;
            border-radius: 8px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            color: #4a5568;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
        }
        
        .account-type-btn.active {
            background: #8b5cf6;
            color: white;
            border-color: #8b5cf6;
        }
        
        .account-type-btn:hover:not(.active) {
            background: #e2e8f0;
        }
        
        .faculty-info {
            background: #f5f3ff;
            border-left: 4px solid #8b5cf6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .faculty-info h4 {
            color: #7c3aed;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Scrollbar styling */
        .talentrix-register-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .talentrix-register-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .talentrix-register-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        .talentrix-register-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body class="talentrix-register-page">
    <div class="talentrix-register-container">
        <div class="talentrix-header">
            <div class="talentrix-logo">TALENTRIX</div>
            <div class="talentrix-subtitle">FACULTY/COACH REGISTRATION</div>
            <div class="user-type-badge">Faculty/Coach Account</div>
        </div>
        
        <?php if($error): ?>
            <div class="talentrix-error">
                ❌ <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Account Type Selector -->
        <div class="account-type-selector">
            <a href="register.php" class="account-type-btn">
                <i class="fas fa-user-graduate"></i> Student
            </a>
            <a href="faculty_register.php" class="account-type-btn active">
                <i class="fas fa-chalkboard-teacher"></i> Faculty/Coach
            </a>
            <a href="admin_register.php" class="account-type-btn">
                <i class="fas fa-user-shield"></i> Admin
            </a>
        </div>
        
        <div class="faculty-info">
            <h4><i class="fas fa-info-circle"></i> Faculty Registration</h4>
            <p>Register as a faculty member or coach. Your account will require admin approval before you can access the system.</p>
        </div>
        
        <form method="POST" action="" class="talentrix-form" id="registrationForm">
            <!-- Username -->
            <div class="talentrix-input-group">
                <label for="username">Username<span class="required-star">*</span></label>
                <input type="text" id="username" name="username" 
                       class="talentrix-input" 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       placeholder="Choose a username" required>
            </div>
            
            <!-- Email -->
            <div class="talentrix-input-group">
                <label for="email">Institution Email<span class="required-star">*</span></label>
                <input type="email" id="email" name="email" 
                       class="talentrix-input" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       placeholder="faculty.name@school.edu" required>
                <div class="talentrix-hint">Use your official faculty email</div>
            </div>
            
            <!-- Password -->
            <div class="talentrix-input-group">
                <label for="password">Password<span class="required-star">*</span></label>
                <input type="password" id="password" name="password" 
                       class="talentrix-input" placeholder="At least 8 characters" required>
            </div>
            
            <!-- Confirm Password -->
            <div class="talentrix-input-group">
                <label for="confirm_password">Confirm Password<span class="required-star">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       class="talentrix-input" placeholder="Confirm your password" required>
            </div>
            
            <!-- Employee ID -->
            <div class="talentrix-input-group">
                <label for="employee_id">Employee ID</label>
                <input type="text" id="employee_id" name="employee_id" 
                       class="talentrix-input" 
                       value="<?php echo htmlspecialchars($_POST['employee_id'] ?? ''); ?>"
                       placeholder="e.g., FAC-2024001">
                <div class="talentrix-hint">Leave blank to auto-generate</div>
            </div>
            
            <!-- Name Fields -->
            <div class="talentrix-input-group">
                <label for="first_name">First Name<span class="required-star">*</span></label>
                <input type="text" id="first_name" name="first_name" 
                       class="talentrix-input" 
                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                       placeholder="Enter your first name" required>
            </div>
            
            <div class="talentrix-input-group">
                <label for="middle_name">Middle Name</label>
                <input type="text" id="middle_name" name="middle_name" 
                       class="talentrix-input" 
                       value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>"
                       placeholder="Enter your middle name (optional)">
            </div>
            
            <div class="talentrix-input-group">
                <label for="last_name">Last Name<span class="required-star">*</span></label>
                <input type="text" id="last_name" name="last_name" 
                       class="talentrix-input" 
                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                       placeholder="Enter your last name" required>
            </div>
            
            <!-- Institutional Dropdowns -->
            <div class="talentrix-input-group">
                <label for="institution">Institution<span class="required-star">*</span></label>
                <select id="institution" name="institution" class="talentrix-select" required>
                    <option value="">-- Select Institution --</option>
                    <?php foreach($institutions as $inst): ?>
                        <option value="<?php echo $inst; ?>" 
                            <?php echo (($_POST['institution'] ?? '') == $inst) ? 'selected' : ''; ?>>
                            <?php echo $inst; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="talentrix-input-group">
                <label for="campus">Campus<span class="required-star">*</span></label>
                <select id="campus" name="campus" class="talentrix-select" required>
                    <?php foreach($campuses as $camp): ?>
                        <option value="<?php echo $camp; ?>" 
                            <?php echo (($_POST['campus'] ?? '') == $camp) ? 'selected' : ''; ?>>
                            <?php echo $camp; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Department -->
            <div class="talentrix-input-group">
                <label for="department">Department<span class="required-star">*</span></label>
                <select id="department" name="department" class="talentrix-select" required>
                    <option value="">-- Select Department --</option>
                    <?php foreach($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>" 
                            <?php echo (($_POST['department'] ?? '') == $dept) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Specialization -->
            <div class="talentrix-input-group">
                <label for="specialization">Specialization/Expertise<span class="required-star">*</span></label>
                <select id="specialization" name="specialization" class="talentrix-select" required>
                    <option value="">-- Select Specialization --</option>
                    <?php foreach($specializations as $spec): ?>
                        <option value="<?php echo htmlspecialchars($spec); ?>" 
                            <?php echo ($selected_specialization == $spec) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($spec); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Years of Experience -->
            <div class="talentrix-input-group">
                <label for="years_experience">Years of Experience</label>
                <select id="years_experience" name="years_experience" class="talentrix-select">
                    <option value="">-- Select Years --</option>
                    <?php for($i = 0; $i <= 30; $i++): ?>
                        <option value="<?php echo $i; ?>" 
                            <?php echo ($selected_years == $i) ? 'selected' : ''; ?>>
                            <?php echo $i; ?> year<?php echo $i != 1 ? 's' : ''; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <!-- Bio -->
            <div class="talentrix-input-group">
                <label for="bio">Brief Biography/Introduction</label>
                <textarea id="bio" name="bio" class="talentrix-textarea" 
                    placeholder="Tell us about your background, expertise, and coaching philosophy..."><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
                <div class="talentrix-hint">Optional: Share your professional background</div>
            </div>
            
            <button type="submit" class="talentrix-btn">
                <i class="fas fa-user-tie"></i> REGISTER AS FACULTY/COACH
            </button>
        </form>
        
        <div class="talentrix-links">
            <p>Already have an account? <a href="login.php">Login</a></p>
            <p><a href="index.php">← Back to Home</a></p>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const registrationForm = document.getElementById('registrationForm');
        
        // Form validation
        registrationForm.addEventListener('submit', function(e) {
            let isValid = true;
            let errorMessage = '';
            
            // Basic required field validation
            const requiredFields = registrationForm.querySelectorAll('[required]');
            requiredFields.forEach(function(field) {
                if(!field.value.trim()) {
                    field.style.borderColor = '#e53e3e';
                    isValid = false;
                } else {
                    field.style.borderColor = '#e2e8f0';
                }
            });
            
            // Validate password length
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password.length < 8) {
                errorMessage = 'Password must be at least 8 characters long';
                isValid = false;
            }
            
            if (password !== confirmPassword) {
                errorMessage = 'Passwords do not match';
                isValid = false;
            }
            
            if(!isValid) {
                e.preventDefault();
                if(errorMessage) {
                    alert(errorMessage);
                } else {
                    alert('Please fill in all required fields correctly.');
                }
                return false;
            }
            
            // Confirm institutional email
            const email = document.getElementById('email').value;
            if (!email.includes('@') || (!email.includes('.edu') && !email.includes('.school'))) {
                if (!confirm('Are you sure this is your official faculty email?')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
    });
    </script>
</body>
</html>