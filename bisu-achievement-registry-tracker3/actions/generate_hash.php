<?php
/**
 * Password Hash Generator for Admin Accounts
 * 
 * This script generates password hashes for creating admin accounts.
 * Run this file once to get the hash, then use it in your SQL INSERT statements.
 * 
 * HOW TO USE:
 * 1. Access this file via browser: http://yourdomain.com/actions/generate_hash.php
 * 2. Copy the generated hash
 * 3. Use it in your SQL INSERT statements
 * 
 * SECURITY NOTE: Delete this file after use to prevent unauthorized access!
 */

// Set the password you want to hash
$password = 'admin123';

// Generate the hash
$hash = password_hash($password, PASSWORD_DEFAULT);

// Also generate for other common passwords if needed
$password2 = 'password123';
$hash2 = password_hash($password2, PASSWORD_DEFAULT);

$password3 = 'admin456';
$hash3 = password_hash($password3, PASSWORD_DEFAULT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator - BISU Achievement Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #003366 0%, #002244 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 900px;
            width: 100%;
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
        h1 {
            color: #003366;
            border-bottom: 3px solid #ffd700;
            padding-bottom: 0.8rem;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
        }
        h2 {
            color: #003366;
            margin-top: 1.8rem;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }
        .hash-box {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-left: 4px solid #003366;
            padding: 1rem;
            margin: 1rem 0;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            border-radius: 10px;
            font-size: 0.85rem;
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .warning i {
            color: #f59e0b;
            font-size: 1.5rem;
        }
        .success {
            background: #dcfce7;
            border-left: 4px solid #22c55e;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .success i {
            color: #22c55e;
            font-size: 1.5rem;
        }
        code {
            background: #e9ecef;
            padding: 0.2rem 0.5rem;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #1e1e2e;
            color: #f8f8f2;
            padding: 1rem;
            border-radius: 12px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            margin: 1rem 0;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #003366, #1a4d8c);
            color: white;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            margin-top: 1rem;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,51,102,0.3);
        }
        .footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
            font-size: 0.85rem;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #003366;
            color: white;
            font-weight: 600;
        }
        td {
            background: #f8f9fa;
        }
        .fa, .fas {
            margin-right: 5px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <h1>
            <i class="fas fa-key" style="color: #ffd700;"></i> 
            Password Hash Generator
        </h1>
        
        <div class="warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>SECURITY WARNING:</strong> 
                Delete this file (<code>actions/generate_hash.php</code>) immediately after use to prevent unauthorized access!
            </div>
        </div>
        
        <h2><i class="fas fa-code"></i> Generated Hashes</h2>
        
        <div class="success">
            <i class="fas fa-check-circle"></i>
            <div style="flex: 1;">
                <strong>Password: admin123</strong>
                <div class="hash-box">
                    <?php echo $hash; ?>
                </div>
            </div>
        </div>
        
        <div class="hash-box">
            <strong><i class="fas fa-lock"></i> Password: password123</strong><br>
            Hash: <?php echo $hash2; ?>
        </div>
        
        <div class="hash-box">
            <strong><i class="fas fa-lock"></i> Password: admin456</strong><br>
            Hash: <?php echo $hash3; ?>
        </div>
        
        <h2><i class="fas fa-database"></i> SQL INSERT Statements</h2>
        <p>Copy and run these SQL commands in your PostgreSQL database:</p>
        
        <pre><?php echo "-- Cultural Admin
INSERT INTO \"User\" (email, password, usertype, admin_type, created_at) 
VALUES ('cultural@bisu.edu.ph', '{$hash}', 'admin', 'cultural', NOW());

-- Sports Admin
INSERT INTO \"User\" (email, password, usertype, admin_type, created_at) 
VALUES ('sports@bisu.edu.ph', '{$hash}', 'admin', 'sports', NOW());

-- Verify the inserts
SELECT id, email, usertype, admin_type, created_at FROM \"User\" 
WHERE email IN ('cultural@bisu.edu.ph', 'sports@bisu.edu.ph');"; ?>
        </pre>
        
        <h2><i class="fas fa-clipboard-list"></i> Complete SQL Script (with DELETE if exists)</h2>
        <pre><?php echo "-- Delete existing entries if they exist
DELETE FROM \"User\" WHERE email IN ('cultural@bisu.edu.ph', 'sports@bisu.edu.ph');

-- Insert admin users
INSERT INTO \"User\" (email, password, usertype, admin_type, created_at) 
VALUES 
('cultural@bisu.edu.ph', '{$hash}', 'admin', 'cultural', NOW()),
('sports@bisu.edu.ph', '{$hash}', 'admin', 'sports', NOW());

-- Verify the inserts
SELECT * FROM \"User\" WHERE usertype = 'admin';"; ?>
        </pre>
        
        <h2><i class="fas fa-users"></i> Login Credentials</h2>
        <table>
            <thead>
                <tr>
                    <th>User Type</th>
                    <th>Email</th>
                    <th>Password</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><i class="fas fa-palette"></i> Cultural Admin</td>
                    <td>cultural@bisu.edu.ph</td>
                    <td>admin123</td>
                </tr>
                <tr>
                    <td><i class="fas fa-futbol"></i> Sports Admin</td>
                    <td>sports@bisu.edu.ph</td>
                    <td>admin123</td>
                </tr>
            </tbody>
        </table>
        
        <h2><i class="fas fa-info-circle"></i> How to Run the SQL</h2>
        <ol style="margin-left: 1.5rem; margin-bottom: 1rem;">
            <li>Copy the SQL statements above</li>
            <li>Open your PostgreSQL database tool (pgAdmin, psql, or any database client)</li>
            <li>Paste and execute the SQL statements</li>
            <li>Verify the users were inserted successfully</li>
            <li>Go to <code>login.php</code> and try logging in</li>
        </ol>
        
        <div class="warning">
            <i class="fas fa-skull-crossbones"></i>
            <div>
                <strong>IMPORTANT:</strong> 
                After you've successfully created the admin accounts, <strong>DELETE THIS FILE</strong> 
                (<code>actions/generate_hash.php</code>) for security reasons!
            </div>
        </div>
        
        <div class="footer">
            <p><i class="fas fa-edit"></i> If you need to generate a hash for a different password, modify the <code>$password</code> variable at the top of this file.</p>
            <button class="btn" onclick="window.location.href='../login.php'">
                <i class="fas fa-sign-in-alt"></i> Go to Login Page
            </button>
        </div>
    </div>
</body>
</html>