<?php
// fix_database.php - Complete database repair tool
require_once 'db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>TALENTRIX Database Repair</title>
    <style>
        body { font-family: Arial; background: #f0f2f5; padding: 30px; }
        .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        h1 { color: #0a2540; border-bottom: 2px solid #10b981; padding-bottom: 10px; }
        h2 { color: #1a365d; margin-top: 25px; }
        .success { background: #c6f6d5; color: #22543d; padding: 12px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #38a169; }
        .error { background: #fed7d7; color: #c53030; padding: 12px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #c53030; }
        .info { background: #e0f2fe; color: #0369a1; padding: 12px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #0284c7; }
        .warning { background: #fef3c7; color: #92400e; padding: 12px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #f59e0b; }
        .btn { display: inline-block; background: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; margin: 10px 5px; font-weight: 600; }
        .btn:hover { background: #059669; }
        .btn-secondary { background: #3b82f6; }
        .btn-secondary:hover { background: #2563eb; }
        .btn-warning { background: #f59e0b; }
        .btn-warning:hover { background: #d97706; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #f8fafc; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 TALENTRIX Database Repair Tool</h1>";

// Function to log messages
function logMessage($type, $message) {
    $class = $type === 'success' ? 'success' : ($type === 'error' ? 'error' : ($type === 'warning' ? 'warning' : 'info'));
    echo "<div class='$class'>" . ($type === 'success' ? '✅ ' : ($type === 'error' ? '❌ ' : ($type === 'warning' ? '⚠️ ' : 'ℹ️ '))) . $message . "</div>";
}

// Check database connection
try {
    $pdo->query("SELECT 1");
    logMessage('success', "Database connection successful");
} catch (Exception $e) {
    logMessage('error', "Database connection failed: " . $e->getMessage());
    exit();
}

// Check students table structure
logMessage('info', "Checking students table structure...");

$columns = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_ASSOC);
$column_names = array_column($columns, 'Field');

echo "<table>";
echo "<tr><th>Column Name</th><th>Type</th><th>Status</th></tr>";
foreach ($columns as $col) {
    $status = "<span style='color: #10b981;'>✓ Exists</span>";
    echo "<tr><td><code>" . $col['Field'] . "</code></td><td>" . $col['Type'] . "</td><td>$status</td></tr>";
}
echo "</table>";

// Check for required columns
$required_columns = [
    'position' => "Position column (used by registration form)",
    'athlete_game' => "Athlete game/event column",
    'primary_position' => "Primary position column"
];

$missing_columns = [];
foreach ($required_columns as $col => $desc) {
    if (!in_array($col, $column_names)) {
        $missing_columns[] = $col;
        logMessage('warning', "Missing column: <code>$col</code> - $desc");
    }
}

// Fix missing columns
if (!empty($missing_columns)) {
    logMessage('info', "Adding missing columns...");
    
    foreach ($missing_columns as $col) {
        try {
            if ($col == 'position') {
                $pdo->exec("ALTER TABLE students ADD COLUMN position VARCHAR(100) NULL AFTER athlete_category");
                logMessage('success', "Added column: <code>position</code>");
            } elseif ($col == 'athlete_game') {
                $pdo->exec("ALTER TABLE students ADD COLUMN athlete_game VARCHAR(100) NULL AFTER athlete_category");
                logMessage('success', "Added column: <code>athlete_game</code>");
            } elseif ($col == 'primary_position') {
                $pdo->exec("ALTER TABLE students ADD COLUMN primary_position VARCHAR(100) NULL AFTER athlete_category");
                logMessage('success', "Added column: <code>primary_position</code>");
            }
        } catch (Exception $e) {
            logMessage('error', "Failed to add $col: " . $e->getMessage());
        }
    }
} else {
    logMessage('success', "All required columns exist!");
}

// Check ENUM values for athlete_category
logMessage('info', "Checking ENUM values for athlete_category...");

try {
    $result = $pdo->query("SHOW COLUMNS FROM students WHERE Field = 'athlete_category'")->fetch();
    if ($result) {
        preg_match("/^enum\(\'(.*)\'\)$/", $result['Type'], $matches);
        if (isset($matches[1])) {
            $enum_values = explode("','", $matches[1]);
            logMessage('info', "Current athlete_category values: " . implode(', ', $enum_values));
            
            // Check if 'none' is included
            if (!in_array('none', $enum_values)) {
                $pdo->exec("ALTER TABLE students MODIFY athlete_category ENUM('varsity', 'club', 'intramural', 'none') DEFAULT 'none'");
                logMessage('success', "Updated athlete_category to include 'none' value");
            }
        }
    }
} catch (Exception $e) {
    logMessage('error', "Error checking athlete_category: " . $e->getMessage());
}

// Check coaches table
logMessage('info', "Checking coaches table...");

try {
    $coach_columns = $pdo->query("SHOW COLUMNS FROM coaches")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('primary_sport', $coach_columns)) {
        $pdo->exec("ALTER TABLE coaches ADD COLUMN primary_sport VARCHAR(100) NULL AFTER specialization");
        logMessage('success', "Added primary_sport to coaches table");
    }
    
    if (!in_array('employee_id', $coach_columns)) {
        $pdo->exec("ALTER TABLE coaches ADD COLUMN employee_id VARCHAR(50) NULL AFTER user_id");
        logMessage('success', "Added employee_id to coaches table");
    }
} catch (Exception $e) {
    logMessage('error', "Error checking coaches table: " . $e->getMessage());
}

// Check if admin user exists
$admin_count = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'admin'")->fetchColumn();
if ($admin_count == 0) {
    logMessage('warning', "No admin user found. Creating default admin...");
    
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, id_number, first_name, last_name, user_type, is_verified, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    try {
        $stmt->execute(['admin', 'admin@talentrix.edu', $hashed_password, 'ADMIN-001', 'System', 'Administrator', 'admin', 1, 'active']);
        logMessage('success', "Default admin created! Username: admin, Password: admin123");
    } catch (Exception $e) {
        logMessage('error', "Failed to create admin: " . $e->getMessage());
    }
} else {
    logMessage('success', "Admin user exists in system");
}

// Create uploads directory
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
    logMessage('success', "Created uploads directory");
} else {
    if (is_writable('uploads')) {
        logMessage('success', "Uploads directory is writable");
    } else {
        logMessage('warning', "Uploads directory exists but is not writable. Please set permissions to 777.");
    }
}

// Fix any NULL constraints that might cause issues
logMessage('info', "Fixing NULL constraints...");

try {
    $pdo->exec("ALTER TABLE students MODIFY college VARCHAR(100) NULL");
    $pdo->exec("ALTER TABLE students MODIFY course VARCHAR(100) NULL");
    $pdo->exec("ALTER TABLE students MODIFY year_level INT NULL");
    logMessage('success', "Updated NULL constraints for student fields");
} catch (Exception $e) {
    logMessage('error', "Error fixing constraints: " . $e->getMessage());
}

// Display final instructions
echo "<div style='margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 10px;'>";
echo "<h2>📋 Next Steps</h2>";
echo "<ol style='margin-left: 20px; line-height: 1.8;'>";
echo "<li><strong>Run the test script:</strong> <a href='test_system.php' class='btn btn-secondary' style='padding: 5px 15px; font-size: 14px;'>Run System Test</a></li>";
echo "<li><strong>Try registration again:</strong> <a href='register.php' class='btn' style='padding: 5px 15px; font-size: 14px;'>Go to Registration</a></li>";
echo "<li><strong>Login as admin:</strong> <a href='login.php' class='btn btn-warning' style='padding: 5px 15px; font-size: 14px;'>Go to Login</a> (admin/admin123)</li>";
echo "</ol>";
echo "</div>";

echo "</div></body></html>";
?>