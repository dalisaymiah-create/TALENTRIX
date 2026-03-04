<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $troupe_id = $_POST['troupe_id'];
    $role = $_POST['role'] ?? '';
    
    // Get student id
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch();
    
    if (!$student) {
        $_SESSION['error'] = "Student record not found.";
        header('Location: student_dashboard.php');
        exit();
    }
    
    // Check if already a member
    $stmt = $pdo->prepare("SELECT id FROM dance_troupe_members WHERE student_id = ? AND troupe_id = ?");
    $stmt->execute([$student['id'], $troupe_id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['error'] = "You are already a member of this troupe or have a pending request.";
        header('Location: student_dashboard.php');
        exit();
    }
    
    // Get troupe details for coach
    $stmt = $pdo->prepare("SELECT coach_id FROM dance_troupes WHERE id = ?");
    $stmt->execute([$troupe_id]);
    $troupe = $stmt->fetch();
    
    if (!$troupe || !$troupe['coach_id']) {
        $_SESSION['error'] = "Invalid troupe or no coach assigned.";
        header('Location: student_dashboard.php');
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Add to troupe members with pending status
        $stmt = $pdo->prepare("INSERT INTO dance_troupe_members (troupe_id, student_id, role, status, joined_date) 
                               VALUES (?, ?, ?, 'pending', CURDATE())");
        $stmt->execute([$troupe_id, $student['id'], $role]);
        
        // Create approval request
        $stmt = $pdo->prepare("INSERT INTO approvals (student_id, coach_id, troupe_id, approval_type, status) 
                               VALUES (?, ?, ?, 'troupe', 'pending')");
        $stmt->execute([$student['id'], $troupe['coach_id'], $troupe_id]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "Your request to join the dance troupe has been sent to the coach for approval.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to send request: " . $e->getMessage();
    }
    
    header('Location: student_dashboard.php');
    exit();
}
?>