<?php
// Database configuration
$host = 'localhost';
$port = '5432';
$dbname = 'bisu_candijay_achievement_tracker';
$user = 'postgres';
$password = '12345678';

// Create connection string
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";

try {
    // Create PDO instance
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Set timezone to Philippines
    $pdo->exec("SET timezone = 'Asia/Manila'");
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>