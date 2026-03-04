<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'coach') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $achievement_title = $_POST['achievement_title'];
    $achievement_description = $_POST['achievement_description'];
    $event_date = $_POST['event_date'];
    $medal_type = $_POST['medal_type'] ?? 'none';
    
    // Get coach id
    $stmt = $pdo->prepare("SELECT id FROM coaches WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $coach = $stmt->fetch();
    
    if (!$coach) {
        $_SESSION['error'] = "Coach record not found.";
        header('Location: coach_dashboard.php');
        exit();
    }
    
    // Insert achievement
    $stmt = $pdo->prepare("INSERT INTO achievements (student_id, achievement_title, achievement_description, event_date, medal_type, is_verified, verified_by, verified_date) 
                           VALUES (?, ?, ?, ?, ?, 1, ?, NOW())");
    
    if ($stmt->execute([$student_id, $achievement_title, $achievement_description, $event_date, $medal_type, $coach['id']])) {
        $_SESSION['success'] = "Achievement added and verified successfully!";
    } else {
        $_SESSION['error'] = "Failed to add achievement.";
    }
    
    header('Location: coach_dashboard.php');
    exit();
}
?>