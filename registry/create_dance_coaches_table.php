<?php
// create_dance_coaches_table.php - Run this first!
require_once 'db.php';

// Create the table
try {
    $sql = "CREATE TABLE IF NOT EXISTS dance_coaches (
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
    )";
    
    $pdo->exec($sql);
    echo "✅ dance_coaches table created successfully!";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>