<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$auth = new Auth($pdo);
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'email' => $_POST['email'],
        'password' => $_POST['password'],
        'usertype' => $_POST['usertype'],
        'first_name' => $_POST['first_name'],
        'last_name' => $_POST['last_name']
    ];
    
    if ($_POST['usertype'] === 'admin') {
        $data['admin_type'] = $_POST['admin_type'];
    }
    
    if ($_POST['usertype'] === 'student') {
        $data['course'] = $_POST['course'];
        $data['yr_level'] = $_POST['yr_level'];
        $data['college'] = $_POST['college'];
    } elseif ($_POST['usertype'] === 'coach') {
        $data['specialization'] = $_POST['specialization'];
    }
    
    $result = $auth->register($data);
    
    if ($result['success']) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Registration Successful - BISU Candijay</title>
            <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: "Poppins", sans-serif;
                    background: linear-gradient(135deg, #1a472a 0%, #0a2b3e 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.6);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1000;
                    animation: fadeIn 0.3s ease-out;
                }
                .modal-content {
                    background: white;
                    border-radius: 30px;
                    padding: 50px;
                    text-align: center;
                    max-width: 450px;
                    width: 90%;
                    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
                    animation: slideUp 0.3s ease-out;
                }
                .success-icon {
                    width: 100px;
                    height: 100px;
                    background: linear-gradient(135deg, #ffd700, #ff8c00);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 25px;
                    animation: scaleIn 0.5s ease-out;
                }
                .success-icon i { font-size: 50px; color: #1a472a; }
                .modal-content h3 { color: #1a472a; font-size: 1.8rem; margin-bottom: 15px; font-weight: 700; }
                .modal-content p { color: #666; margin-bottom: 25px; line-height: 1.6; }
                .redirect-text { color: #999; font-size: 0.9rem; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
                @keyframes scaleIn { from { transform: scale(0); } to { transform: scale(1); } }
            </style>
        </head>
        <body>
            <div class="modal-overlay" id="modalOverlay">
                <div class="modal-content">
                    <div class="success-icon"><i class="fas fa-check"></i></div>
                    <h3>Welcome Aboard!</h3>
                    <p>Your account has been created successfully. You can now login to access your dashboard.</p>
                    <div class="redirect-text"><i class="fas fa-spinner fa-spin"></i> Redirecting to login page...</div>
                </div>
            </div>
            <script>setTimeout(function() { window.location.href = "login.php"; }, 2000);</script>
        </body>
        </html>';
        exit();
    } else {
        $message = $result['message'];
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - BISU Candijay Achievement Registry</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #003366 0%, #003366 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        body::before {
            content: '🏃';
            position: absolute;
            font-size: 15rem;
            opacity: 0.03;
            bottom: -3rem;
            left: -3rem;
            pointer-events: none;
        }

        body::after {
            content: '🎭';
            position: absolute;
            font-size: 15rem;
            opacity: 0.03;
            top: -3rem;
            right: -3rem;
            pointer-events: none;
        }

        /* Navigation Bar */
        .top-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            z-index: 100;
            padding: 0.8rem 2rem;
        }

        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #1a472a;
        }

        .logo-text h2 {
            font-size: 1rem;
            font-weight: 700;
            color: #1a472a;
        }

        .logo-text p {
            font-size: 0.65rem;
            color: #003366;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
        }

        .nav-links a {
            text-decoration: none;
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #ff8c00;
        }

        .btn-login-nav {
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            color: #ffffff;
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
        }

        /* Auth Container */
        .auth-container {
            background: white;
            border-radius: 30px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 520px;
            width: 100%;
            padding: 40px;
            margin-top: 70px;
            position: relative;
            z-index: 10;
        }

        /* Header Section */
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .institution-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1a472a;
            margin-bottom: 5px;
        }

        .registry-title {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #003366, #003366);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 8px;
        }

        .auth-subtitle {
            color: #666;
            font-size: 0.85rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px 12px 42px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            background: white;
        }

        .form-group select {
            padding-left: 42px;
            cursor: pointer;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #ff8c00;
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #003366, #003366);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 140, 0, 0.3);
        }

        /* Footer Links */
        .auth-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .auth-footer p {
            color: #666;
            font-size: 0.85rem;
        }

        .auth-footer a {
            color: #110157;
            text-decoration: none;
            font-weight: 600;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .back-link {
            display: inline-block;
            margin-top: 12px;
            color: #999 !important;
            font-size: 0.8rem;
        }

        .back-link i {
            margin-right: 5px;
        }

        .message {
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .message.error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        small {
            color: #999;
            font-size: 0.7rem;
            display: block;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .auth-container {
                padding: 30px 25px;
                margin-top: 90px;
            }
            
            .registry-title {
                font-size: 1.5rem;
            }
            
            .nav-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .top-nav {
                padding: 0.8rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="nav-container">
            <a href="index.php" class="logo-area">
                <div class="logo-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="logo-text">
                    <h2>Bohol Island State University</h2>
                    <p>Candijay Campus</p>
                </div>
            </a>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="index.php#features">Features</a>
                <a href="index.php#champions">Achievers</a>
                <a href="login.php" class="btn-login-nav">Login</a>
            </div>
        </div>
    </div>

    <div class="auth-container">
        <div class="auth-header">
            <img src="assets/images/bisu-logo.png" alt="BiSU Logo" class="logo">
            <div class="institution-name">Bohol Island State University</div>
            <div class="registry-title">Create an Account</div>
            <div class="auth-subtitle">Join the Achievement Registry</div>
        </div>
        
        <?php if ($message && $messageType === 'error'): ?>
            <div class="message <?php echo $messageType; ?>">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <label><i class="fas fa-user-tag"></i> I am a</label>
                <div class="input-icon">
                    <i class="fas fa-user-circle"></i>
                    <select name="usertype" id="usertype" required onchange="toggleFields()">
                        <option value="student">Student (Athlete/Artist)</option>
                        <option value="coach">Coach / Mentor</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-user"></i> First Name</label>
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" name="first_name" placeholder="Enter your first name" required>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-user"></i> Last Name</label>
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" name="last_name" placeholder="Enter your last name" required>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <div class="input-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Enter your email address" required>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Create a password" required minlength="6">
                </div>
                <small>Minimum 6 characters</small>
            </div>
            
            <!-- Admin Fields -->
            <div id="adminFields" style="display: none;">
                <div class="form-group">
                    <label><i class="fas fa-shield-alt"></i> Admin Type</label>
                    <div class="input-icon">
                        <i class="fas fa-shield-alt"></i>
                        <select name="admin_type">
                            <option value="sports">Sports and Athletic Admin</option>
                            <option value="cultural_arts">Culture and Arts Admin</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Student Fields -->
            <div id="studentFields">
                <div class="form-group">
                    <label><i class="fas fa-graduation-cap"></i> Course</label>
                    <div class="input-icon">
                        <i class="fas fa-graduation-cap"></i>
                        <input type="text" name="course" placeholder="e.g., BS Computer Science, BS Sports Science">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Year Level</label>
                    <div class="input-icon">
                        <i class="fas fa-calendar-alt"></i>
                        <select name="yr_level">
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                            
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-university"></i> College</label>
                    <div class="input-icon">
                        <i class="fas fa-university"></i>
                        <input type="text" name="college" placeholder="e.g., College of Science, College of Teacher ">
                    </div>
                </div>
            </div>
            
            <!-- Coach Fields -->
            <div id="coachFields" style="display: none;">
                <div class="form-group">
                    <label><i class="fas fa-chalkboard-user"></i> Specialization</label>
                    <div class="input-icon">
                        <i class="fas fa-chalkboard-user"></i>
                        <input type="text" name="specialization" placeholder="e.g., Basketball Coach, Voice Mentor, Dance Instructor">
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn-submit">Create Account</button>
        </form>
        
        <div class="auth-footer">
            <p>Already have an account? <a href="login.php">Login here</a></p>
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
    </div>

    <script>
    function toggleFields() {
        const usertype = document.getElementById('usertype').value;
        const studentFields = document.getElementById('studentFields');
        const coachFields = document.getElementById('coachFields');
        const adminFields = document.getElementById('adminFields');
        
        studentFields.style.display = 'none';
        coachFields.style.display = 'none';
        adminFields.style.display = 'none';
        
        if (usertype === 'student') {
            studentFields.style.display = 'block';
            document.querySelectorAll('#studentFields input, #studentFields select').forEach(field => {
                field.required = true;
            });
            document.querySelectorAll('#coachFields input, #adminFields select').forEach(field => {
                field.required = false;
            });
        } else if (usertype === 'coach') {
            coachFields.style.display = 'block';
            document.querySelectorAll('#coachFields input').forEach(field => {
                field.required = true;
            });
            document.querySelectorAll('#studentFields input, #studentFields select, #adminFields select').forEach(field => {
                field.required = false;
            });
        } else if (usertype === 'admin') {
            adminFields.style.display = 'block';
            document.querySelectorAll('#adminFields select').forEach(field => {
                field.required = true;
            });
            document.querySelectorAll('#studentFields input, #studentFields select, #coachFields input').forEach(field => {
                field.required = false;
            });
        }
    }

    document.addEventListener('DOMContentLoaded', toggleFields);
    </script>
</body>
</html>