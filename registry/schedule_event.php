<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'coach') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_title = $_POST['event_title'];
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $event_description = $_POST['event_description'];
    $location = $_POST['location'];
    $event_type = $_POST['event_type'];
    
    // Insert event
    $stmt = $pdo->prepare("INSERT INTO upcoming_events (event_title, event_date, event_time, event_description, location, event_type, created_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$event_title, $event_date, $event_time, $event_description, $location, $event_type, $_SESSION['user_id']])) {
        $_SESSION['success'] = "Event scheduled successfully!";
    } else {
        $_SESSION['error'] = "Failed to schedule event.";
    }
    
    header('Location: coach_dashboard.php');
    exit();
}
?>