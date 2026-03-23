<?php
// db.php - Database connection with automatic table creation

$host = '127.0.0.1';
$db   = 'talentrix';  // Changed to match your database name
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // First connect without database
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db");
    $pdo->exec("USE $db");
    
    // Check if tables exist, create if not
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        // Create all tables - NO DEFAULT USERS CREATED
        $schema = "
        -- Users table
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            id_number VARCHAR(50) UNIQUE NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            middle_name VARCHAR(50),
            last_name VARCHAR(50) NOT NULL,
            institution VARCHAR(100) DEFAULT 'Bohol Island State University',
            campus VARCHAR(100),
            user_type ENUM('admin', 'athletics_admin', 'dance_admin', 'sport_coach', 'dance_coach', 'student') NOT NULL,
            profile_picture VARCHAR(255),
            is_verified BOOLEAN DEFAULT FALSE,
            status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );

        -- Sports table
        CREATE TABLE IF NOT EXISTS sports (
            id INT PRIMARY KEY AUTO_INCREMENT,
            sport_name VARCHAR(100) NOT NULL,
            category VARCHAR(50),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        -- Sport positions table
        CREATE TABLE IF NOT EXISTS sport_positions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            sport_id INT,
            position_name VARCHAR(100),
            FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
        );

        -- Dance troupes table
        CREATE TABLE IF NOT EXISTS dance_troupes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            troupe_name VARCHAR(100) NOT NULL,
            dance_style VARCHAR(100),
            coach_id INT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        -- Students table
        CREATE TABLE IF NOT EXISTS students (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT UNIQUE NOT NULL,
            student_type ENUM('athlete', 'dancer', 'both') NOT NULL,
            college VARCHAR(100),
            course VARCHAR(100),
            year_level INT,
            section VARCHAR(10),
            primary_sport VARCHAR(100),
            secondary_sport VARCHAR(100),
            athlete_category VARCHAR(50),
            athlete_game VARCHAR(100),
            position VARCHAR(100),
            jersey_number INT,
            dance_troupe VARCHAR(100),
            dance_role VARCHAR(50),
            date_registered DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        -- Coaches table
        CREATE TABLE IF NOT EXISTS coaches (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT UNIQUE NOT NULL,
            employee_id VARCHAR(50),
            department VARCHAR(100),
            position VARCHAR(100),
            specialization VARCHAR(100),
            years_experience INT,
            bio TEXT,
            primary_sport VARCHAR(100),
            date_hired DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        -- Dance coaches table
        CREATE TABLE IF NOT EXISTS dance_coaches (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT UNIQUE NOT NULL,
            employee_id VARCHAR(50),
            dance_specialization VARCHAR(100),
            dance_troupe_name VARCHAR(100),
            years_experience INT,
            date_hired DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        -- Teams table
        CREATE TABLE IF NOT EXISTS teams (
            id INT PRIMARY KEY AUTO_INCREMENT,
            team_name VARCHAR(100) NOT NULL,
            sport_id INT,
            coach_id INT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE,
            FOREIGN KEY (coach_id) REFERENCES coaches(id) ON DELETE SET NULL
        );

        -- Team members table
        CREATE TABLE IF NOT EXISTS team_members (
            id INT PRIMARY KEY AUTO_INCREMENT,
            team_id INT NOT NULL,
            student_id INT NOT NULL,
            position_id INT,
            jersey_number INT,
            status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
            joined_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (position_id) REFERENCES sport_positions(id) ON DELETE SET NULL,
            UNIQUE KEY unique_team_student (team_id, student_id)
        );

        -- Dance troupe members table
        CREATE TABLE IF NOT EXISTS dance_troupe_members (
            id INT PRIMARY KEY AUTO_INCREMENT,
            troupe_id INT NOT NULL,
            student_id INT NOT NULL,
            role VARCHAR(50),
            status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
            joined_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (troupe_id) REFERENCES dance_troupes(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            UNIQUE KEY unique_troupe_student (troupe_id, student_id)
        );

        -- Achievements table
        CREATE TABLE IF NOT EXISTS achievements (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            achievement_title VARCHAR(255) NOT NULL,
            achievement_description TEXT,
            event_date DATE,
            medal_type ENUM('gold', 'silver', 'bronze', 'none') DEFAULT 'none',
            is_verified BOOLEAN DEFAULT FALSE,
            verified_by INT,
            verified_date DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (verified_by) REFERENCES coaches(id) ON DELETE SET NULL
        );

        -- Approvals table
        CREATE TABLE IF NOT EXISTS approvals (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            coach_id INT NOT NULL,
            team_id INT,
            troupe_id INT,
            approval_type ENUM('team', 'troupe') NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            rejection_reason TEXT,
            request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            response_date TIMESTAMP NULL,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (coach_id) REFERENCES coaches(id) ON DELETE CASCADE,
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
            FOREIGN KEY (troupe_id) REFERENCES dance_troupes(id) ON DELETE CASCADE
        );

        -- Attendance table
        CREATE TABLE IF NOT EXISTS attendance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            team_id INT,
            troupe_id INT,
            attendance_date DATE NOT NULL,
            status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
            marked_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
            FOREIGN KEY (troupe_id) REFERENCES dance_troupes(id) ON DELETE CASCADE,
            FOREIGN KEY (marked_by) REFERENCES coaches(id) ON DELETE SET NULL,
            UNIQUE KEY unique_attendance (student_id, attendance_date)
        );

        -- Homepage content table
        CREATE TABLE IF NOT EXISTS homepage_content (
            id INT PRIMARY KEY AUTO_INCREMENT,
            section_type ENUM('athletics', 'dance') NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT,
            badge VARCHAR(50),
            post_date DATE,
            status ENUM('draft', 'published') DEFAULT 'published',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        );

        -- Upcoming events table
        CREATE TABLE IF NOT EXISTS upcoming_events (
            id INT PRIMARY KEY AUTO_INCREMENT,
            event_title VARCHAR(255) NOT NULL,
            event_date DATE NOT NULL,
            event_time TIME,
            event_description TEXT,
            location VARCHAR(255),
            event_type ENUM('athletics', 'dance', 'both') DEFAULT 'both',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        );

        -- Insert sample sports (these are fine to keep)
        INSERT IGNORE INTO sports (sport_name, category) VALUES
        ('Basketball', 'Team Sport'),
        ('Volleyball', 'Team Sport'),
        ('Football', 'Team Sport'),
        ('Swimming', 'Individual Sport'),
        ('Track and Field', 'Individual Sport'),
        ('Badminton', 'Individual Sport'),
        ('Table Tennis', 'Individual Sport'),
        ('Chess', 'Individual Sport'),
        ('Taekwondo', 'Martial Arts'),
        ('Arnis', 'Martial Arts'),
        ('Dance Sport', 'Performing Arts');

        -- Insert sample dance troupes (these are fine to keep)
        INSERT IGNORE INTO dance_troupes (troupe_name, dance_style) VALUES
        ('BISU Street Dancers', 'Street Dance'),
        ('BISU Folkloric', 'Folk Dance'),
        ('BISU Hip Hop Crew', 'Hip Hop'),
        ('BISU Contemporary', 'Contemporary');
        ";
        
        // Execute the schema creation
        $pdo->exec($schema);
        
    } else {
        // If tables exist but user_type enum needs updating
        try {
            // Check if we need to update the user_type enum
            $result = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'user_type'")->fetch();
            if ($result) {
                $type = $result['Type'];
                if (strpos($type, 'athletics_admin') === false) {
                    // Update the enum to include new admin types
                    $pdo->exec("ALTER TABLE users MODIFY user_type ENUM('admin', 'athletics_admin', 'dance_admin', 'sport_coach', 'dance_coach', 'student') NOT NULL");
                }
            }
        } catch (Exception $e) {
            // Silently ignore errors
        }
        
    }
    
    // Ensure teams table has stats columns
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM teams")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('matches', $columns)) {
            $pdo->exec("ALTER TABLE teams ADD COLUMN matches INT DEFAULT 0");
        }

        if (!in_array('wins', $columns)) {
            $pdo->exec("ALTER TABLE teams ADD COLUMN wins INT DEFAULT 0");
        }

        if (!in_array('losses', $columns)) {
            $pdo->exec("ALTER TABLE teams ADD COLUMN losses INT DEFAULT 0");
        }

        if (!in_array('logo', $columns)) {
            $pdo->exec("ALTER TABLE teams ADD COLUMN logo VARCHAR(255)");
        }

    } catch (Exception $e) {
        // Silently ignore errors
    }
    
} catch (PDOException $e) {
    // Only show error if it's a critical database connection issue
    die("Database Connection Failed: " . $e->getMessage());
}

// For backward compatibility with code that expects mysqli connection
// You can use this function to get a mysqli connection if needed
function getMysqliConnection() {
    global $host, $user, $pass, $db;
    $conn = mysqli_connect($host, $user, $pass, $db);
    if (!$conn) {
        die("Connection Failed: " . mysqli_connect_error());
    }
    return $conn;
}

// You can also define a global mysqli connection for legacy code
$conn = getMysqliConnection();

// Make PDO connection available globally
global $pdo;
?>