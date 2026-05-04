<?php
require_once 'includes/session.php';
redirectIfLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - BISU Athletes & Arts Registry</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #003366;
            --primary-gold: #ffd700;
            --sports-green: #27ae60;
            --cultural-purple: #9b59b6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-blue) 0%, #0a2a4a 100%);
            min-height: 100vh;
        }

        /* Registration Page with Card Design */
        .register-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .register-container {
            max-width: 650px;
            width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .register-header {
            background: linear-gradient(135deg, var(--primary-blue), #1a4d8c);
            padding: 2rem;
            text-align: center;
            color: white;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem auto;
        }
        
        .logo-icon img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-blue);
            padding: 2px;
            background: white;
        }
        
        .register-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .register-header p {
            opacity: 0.9;
        }
        
        .register-body {
            padding: 2rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group .input-wrapper {
            position: relative;
        }
        
        .form-group .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 2.8rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
            background: white;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0,51,102,0.1);
        }
        
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-blue), #1a4d8c);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,51,102,0.3);
        }
        
        .auth-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .auth-footer a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .auth-footer a:hover {
            color: var(--primary-gold);
        }
        
        .info-text {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .field-group {
            animation: fadeIn 0.3s ease;
            border-left: 3px solid var(--primary-blue);
            padding-left: 1rem;
            margin-top: 1rem;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .usertype-card {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .usertype-option {
            flex: 1;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .usertype-option:hover {
            border-color: var(--primary-blue);
            background: #f8f9fa;
        }
        
        .usertype-option.selected {
            border-color: var(--primary-blue);
            background: rgba(0,51,102,0.05);
        }
        
        .usertype-option i {
            font-size: 24px;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .usertype-option .student-icon { color: var(--primary-blue); }
        .usertype-option .coach-icon { color: var(--sports-green); }
        .usertype-option .admin-icon { color: var(--cultural-purple); }
        
        .home-link {
            position: fixed;
            top: 20px;
            right: 30px;
            z-index: 100;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: var(--primary-blue);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .home-link:hover {
            background: var(--primary-blue);
            color: white;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .register-wrapper {
                padding: 1rem;
            }
            
            .home-link {
                top: 10px;
                right: 15px;
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .usertype-card {
                flex-direction: column;
            }
            
            .register-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <a href="index.php" class="home-link">
        <i class="fas fa-home"></i> Back to Home
    </a>
    
    <div class="register-wrapper">
        <div class="register-container">
            <div class="register-header">
                <div class="logo-icon">
                    <img src="includes/uploads/images/bisu_logo.png" alt="BISU Logo">
                </div>
                <h2>Create Account ✨</h2>
                <p>Join the BISU Athletes & Arts Performers Registry</p>
            </div>
            
            <div class="register-body">
                <?php if(isset($_GET['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                
                <form action="actions/process_register.php" method="POST">
                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" required placeholder="Enter your email">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" required placeholder="Create a password (min. 6 characters)">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-check-circle"></i>
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Register as</label>
                        <select id="usertype" name="usertype" required style="display: none;">
                            <option value="student">Student</option>
                            <option value="coach">Coach</option>
                            <option value="admin">Admin</option>
                        </select>
                        
                        <div class="usertype-card">
                            <div class="usertype-option" data-value="student" onclick="selectUsertype('student')">
                                <i class="fas fa-user-graduate student-icon"></i>
                                <strong>Student</strong>
                                <small style="display: block; font-size: 0.7rem; color: #666;">Athlete / Artist</small>
                            </div>
                            <div class="usertype-option" data-value="coach" onclick="selectUsertype('coach')">
                                <i class="fas fa-chalkboard-user coach-icon"></i>
                                <strong>Coach</strong>
                                <small style="display: block; font-size: 0.7rem; color: #666;">Team Trainer / Mentor</small>
                            </div>
                            <div class="usertype-option" data-value="admin" onclick="selectUsertype('admin')">
                                <i class="fas fa-user-shield admin-icon"></i>
                                <strong>Admin</strong>
                                <small style="display: block; font-size: 0.7rem; color: #666;">Sports / Cultural Admin</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Student Fields -->
                    <div id="student_fields" style="display:none;" class="field-group">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> First Name</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" id="first_name" name="first_name" placeholder="Enter your first name">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Last Name</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" id="last_name" name="last_name" placeholder="Enter your last name">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-graduation-cap"></i> Course</label>
                            <div class="input-wrapper">
                                <i class="fas fa-graduation-cap"></i>
                                <select id="course" name="course">
                                    <option value="">Select course</option>
                                    <optgroup label="Teacher Education">
                                        <option value="BEED">BEED - Bachelor of Elementary Education</option>
                                        <option value="BSED-Math">BSED - Mathematics</option>
                                        <option value="BSED-English">BSED - English</option>
                                        <option value="BSED-Filipino">BSED - Filipino</option>
                                        <option value="BSED-Science">BSED - Science</option>
                                    </optgroup>
                                    <optgroup label="Science & Technology">
                                        <option value="BSCS">BSCS - Computer Science</option>
                                        <option value="BSES">BSES - Environmental Science</option>
                                    </optgroup>
                                    <optgroup label="Business & Management">
                                        <option value="BSHM">BSHM - Hospitality Management</option>
                                        <option value="BSOAD">BSOAD - Office Administration</option>
                                    </optgroup>
                                    <optgroup label="Fisheries & Marine">
                                        <option value="BSF">BSF - Fisheries</option>
                                        <option value="BSMB">BSMB - Marine Biology</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-layer-group"></i> Year Level</label>
                            <div class="input-wrapper">
                                <i class="fas fa-layer-group"></i>
                                <input type="number" id="yr_level" name="yr_level" min="1" max="5" placeholder="1-5">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Coach Fields -->
                    <div id="coach_fields" style="display:none;" class="field-group">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> First Name</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" id="coach_first_name" name="coach_first_name" placeholder="Enter your first name">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Last Name</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" id="coach_last_name" name="coach_last_name" placeholder="Enter your last name">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Admin Fields -->
                    <div id="admin_fields" style="display:none;" class="field-group">
                        <div class="form-group">
                            <label><i class="fas fa-user-shield"></i> Admin Type</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user-shield"></i>
                                <select id="admin_type" name="admin_type">
                                    <option value="">Select admin type</option>
                                    <option value="sports">🏆 Sports Admin</option>
                                    <option value="cultural">🎭 Cultural Admin</option>
                                </select>
                            </div>
                            <div class="info-text">
                                <i class="fas fa-info-circle"></i> Sports Admin manages sports events, Cultural Admin manages cultural activities
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> First Name</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" id="admin_first_name" name="admin_first_name" placeholder="Enter your first name">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Last Name</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" id="admin_last_name" name="admin_last_name" placeholder="Enter your last name">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-user-plus"></i> Register
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let selectedUsertype = '';
        
        function selectUsertype(type) {
            selectedUsertype = type;
            
            // Update visual selection
            document.querySelectorAll('.usertype-option').forEach(opt => {
                opt.classList.remove('selected');
                if (opt.getAttribute('data-value') === type) {
                    opt.classList.add('selected');
                }
            });
            
            // Update hidden select
            document.getElementById('usertype').value = type;
            
            // Show/hide fields
            const studentFields = document.getElementById('student_fields');
            const coachFields = document.getElementById('coach_fields');
            const adminFields = document.getElementById('admin_fields');
            
            studentFields.style.display = 'none';
            coachFields.style.display = 'none';
            adminFields.style.display = 'none';
            
            // Reset required attributes
            document.querySelectorAll('#student_fields input, #student_fields select').forEach(input => input.required = false);
            document.querySelectorAll('#coach_fields input, #coach_fields select').forEach(input => input.required = false);
            document.querySelectorAll('#admin_fields input, #admin_fields select').forEach(input => input.required = false);
            
            if (type === 'student') {
                studentFields.style.display = 'block';
                document.querySelectorAll('#student_fields input, #student_fields select').forEach(input => input.required = true);
            } else if (type === 'coach') {
                coachFields.style.display = 'block';
                document.querySelectorAll('#coach_fields input, #coach_fields select').forEach(input => input.required = true);
            } else if (type === 'admin') {
                adminFields.style.display = 'block';
                document.querySelectorAll('#admin_fields input, #admin_fields select').forEach(input => input.required = true);
            }
        }
        
        // Check for pre-selected value from URL or default
        const urlParams = new URLSearchParams(window.location.search);
        const typeParam = urlParams.get('type');
        if (typeParam && ['student', 'coach', 'admin'].includes(typeParam)) {
            selectUsertype(typeParam);
        }
    </script>
</body>
</html>