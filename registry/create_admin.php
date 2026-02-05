<?php
// create_admin.php
require_once 'db.php';

// Check if there are already users
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$count = $stmt->fetch()['count'];

// Check if admin already exists
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'admin'");
$stmt->execute();
$admin_count = $stmt->fetch()['count'];

if ($admin_count == 0) {
    // Create default admin with all required fields
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    
    // Prepare all fields for insertion
    $stmt = $pdo->prepare("INSERT INTO users 
        (id_number, username, email, password, first_name, last_name, user_type, 
         institution, campus, college, department, course, is_verified, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    // Execute with all required fields
    if ($stmt->execute([
        'ADMIN-001', 
        'admin', 
        'admin@talentrix.edu', 
        $hashed_password, 
        'System', 
        'Administrator', 
        'admin',
        'Bohol Island State University',
        'Tagbilaran Campus',
        'College of Sciences',
        'COS',
        'Bachelor of Science in Computer Science',
        1 // is_verified
    ])) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>TALENTRIX - Admin Created</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: linear-gradient(135deg, #0a2540 0%, #1a365d 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 16px;
                    max-width: 500px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                }
                .success-box {
                    background: #c6f6d5;
                    border-left: 5px solid #38a169;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                }
                .warning-box {
                    background: #fed7d7;
                    border-left: 5px solid #c53030;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                }
                .credentials {
                    background: #e0f2fe;
                    padding: 15px;
                    border-radius: 8px;
                    margin: 15px 0;
                }
                .btn {
                    display: inline-block;
                    background: #10b981;
                    color: white;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: bold;
                    margin: 10px 5px;
                }
                .btn:hover {
                    background: #059669;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1 style='color: #0a2540;'>TALENTRIX</h1>
                <h2>✅ Default Admin Created Successfully!</h2>
                
                <div class='success-box'>
                    <h3><i class='fas fa-user-shield'></i> Administrator Account Created</h3>
                    <p>The system administrator account has been created with the following credentials:</p>
                </div>
                
                <div class='credentials'>
                    <h4>Login Credentials:</h4>
                    <p><strong>Username:</strong> admin</p>
                    <p><strong>Password:</strong> admin123</p>
                    <p><strong>Email:</strong> admin@talentrix.edu</p>
                    <p><strong>User Type:</strong> Administrator</p>
                </div>
                
                <div class='warning-box'>
                    <h4>⚠️ IMPORTANT SECURITY NOTICE:</h4>
                    <p>1. <strong>Change the default password immediately</strong> after first login</p>
                    <p>2. <strong>Delete this file</strong> (create_admin.php) from the server</p>
                    <p>3. Consider creating additional admin accounts for backup</p>
                </div>
                
                <div style='margin-top: 30px; text-align: center;'>
                    <a href='login.php' class='btn'>
                        <i class='fas fa-sign-in-alt'></i> Go to Login Page
                    </a>
                    <a href='admin_pages.php?page=dashboard' class='btn' style='background: #3b82f6;'>
                        <i class='fas fa-tachometer-alt'></i> Go to Admin Dashboard
                    </a>
                </div>
                
                <p style='margin-top: 30px; font-size: 12px; color: #718096; text-align: center;'>
                    <i class='fas fa-info-circle'></i> Total users in system: " . $count . "
                </p>
            </div>
        </body>
        </html>";
    } else {
        echo "<div style='padding: 20px; background: #fed7d7; border-radius: 8px;'>
                <h2>❌ Error Creating Admin</h2>
                <p>Failed to create admin user. Please check:</p>
                <ul>
                    <li>Database connection</li>
                    <li>Table structure (all required columns exist)</li>
                    <li>Database permissions</li>
                </ul>
                <p><a href='login.php'>Go to Login</a></p>
              </div>";
    }
} else {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>TALENTRIX - Admin Exists</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #0a2540 0%, #1a365d 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container {
                background: white;
                padding: 40px;
                border-radius: 16px;
                max-width: 500px;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }
            .info-box {
                background: #e0f2fe;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .btn {
                display: inline-block;
                background: #10b981;
                color: white;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
                margin: 10px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1 style='color: #0a2540;'>TALENTRIX</h1>
            <h2>Admin User Already Exists</h2>
            
            <div class='info-box'>
                <h3><i class='fas fa-info-circle'></i> System Status</h3>
                <p>An administrator account already exists in the system.</p>
                <p><strong>Total Users:</strong> " . $count . "</p>
                <p><strong>Admin Users:</strong> " . $admin_count . "</p>
            </div>
            
            <p>You cannot create another admin using this method. To create additional administrators:</p>
            <ul style='text-align: left;'>
                <li>Login as existing admin</li>
                <li>Use the admin dashboard to create new users</li>
                <li>Or use the admin registration page with secret key</li>
            </ul>
            
            <div style='margin-top: 30px;'>
                <a href='login.php' class='btn'>
                    <i class='fas fa-sign-in-alt'></i> Go to Login Page
                </a>
                <a href='admin_register.php?key=TALENTRIX2024' class='btn' style='background: #8b5cf6;'>
                    <i class='fas fa-user-shield'></i> Admin Registration (with key)
                </a>
                <a href='index.php' class='btn' style='background: #6b7280;'>
                    <i class='fas fa-home'></i> Back to Home
                </a>
            </div>
        </div>
    </body>
    </html>";
}
?>