<?php
// schedule_event.php - Add new schedule event
session_start();
require_once 'db.php';

// Check if user is logged in and is coach or trainer
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'sport_coach' && $_SESSION['user_type'] !== 'dance_coach')) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: schedule.php');
    exit();
}

$event_title = $_POST['event_title'] ?? $_POST['title'] ?? '';
$event_date = $_POST['event_date'] ?? '';
$event_time = $_POST['event_time'] ?? '';
$event_description = $_POST['event_description'] ?? $_POST['description'] ?? '';
$location = $_POST['location'] ?? '';
$event_type = $_POST['event_type'] ?? 'general';
$team_id = $_POST['team_id'] ?? null;
$troupe_id = $_POST['troupe_id'] ?? null;

// Validate required fields
if (empty($event_title) || empty($event_date) || empty($event_time)) {
    $_SESSION['error'] = "Please fill in all required fields.";
    header('Location: schedule.php?month=' . date('m', strtotime($event_date)) . '&year=' . date('Y', strtotime($event_date)));
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Check if schedule table exists, create if not
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_id INT NULL,
            troupe_id INT NULL,
            event_type VARCHAR(50),
            title VARCHAR(255) NOT NULL,
            event_date DATE NOT NULL,
            event_time TIME NOT NULL,
            location VARCHAR(255),
            description TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (event_date),
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
            FOREIGN KEY (troupe_id) REFERENCES dance_troupes(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    
    // Insert into schedule table
    $stmt = $pdo->prepare("
        INSERT INTO schedule (team_id, troupe_id, event_type, title, event_date, event_time, location, description, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $team_id,
        $troupe_id,
        $event_type,
        $event_title,
        $event_date,
        $event_time,
        $location,
        $event_description,
        $_SESSION['user_id']
    ]);
    
    // Also insert into upcoming_events for backward compatibility
    try {
        $stmt2 = $pdo->prepare("
            INSERT INTO upcoming_events (event_title, event_date, event_time, event_description, location, event_type, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt2->execute([$event_title, $event_date, $event_time, $event_description, $location, $event_type, $_SESSION['user_id']]);
    } catch (Exception $e) {
        // Upcoming_events table might not exist or have different structure - ignore
        error_log("Could not insert into upcoming_events: " . $e->getMessage());
    }
    
    $pdo->commit();
    
    $_SESSION['success'] = "Event scheduled successfully!";
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error scheduling event: " . $e->getMessage();
    error_log("Schedule event error: " . $e->getMessage());
}

// Redirect back to schedule page with the correct month
$redirect_month = date('m', strtotime($event_date));
$redirect_year = date('Y', strtotime($event_date));
header('Location: schedule.php?month=' . $redirect_month . '&year=' . $redirect_year);
exit();
?>