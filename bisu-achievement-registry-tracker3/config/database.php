<?php
$host = 'localhost';
$dbname = 'Bisu_Registry_Achievement_Tracker3';
$username = 'postgres';
$password = '12345678';

// Connection options for better performance
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => true // Enable persistent connections for better performance
];

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password, $options);
} catch(PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database connection failed: " . $e->getMessage());
    die("Unable to connect to the database. Please try again later or contact administrator.");
}
?>