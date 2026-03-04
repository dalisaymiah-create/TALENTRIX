<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Require coach role
requireCoach();

if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header('Location: approvals.php');
    exit();
}

$user_id = $_GET['id'];
$action = $_GET['action'];
$coach_id = $_SESSION['user_id'];
$reason = isset($_GET['reason']) ? $_GET['reason'] : '';

// Start transaction
$conn->begin_transaction();

try {
    if ($action == 'approve') {
        // Update user status
        $sql1 = "UPDATE users SET status = 'approved' WHERE id = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("i", $user_id);
        
        if (!$stmt1->execute()) {
            throw new Exception("Failed to update user status");
        }
        
        // Update approval record
        $sql2 = "UPDATE approvals SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE user_id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("ii", $coach_id, $user_id);
        
        if (!$stmt2->execute()) {
            throw new Exception("Failed to update approval record");
        }
        
        // Get student's team/sport
        $sql3 = "SELECT sport, team_id FROM students WHERE user_id = ?";
        $stmt3 = $conn->prepare($sql3);
        $stmt3->bind_param("i", $user_id);
        $stmt3->execute();
        $student = $stmt3->get_result()->fetch_assoc();
        
        // Add to team members
        $sql4 = "INSERT INTO team_members (user_id, team_id, status, joined_at) 
                 VALUES (?, ?, 'active', NOW())";
        $stmt4 = $conn->prepare($sql4);
        $team_id = $student['team_id'] ?? 1; // Default team if none
        $stmt4->bind_param("ii", $user_id, $team_id);
        
        if (!$stmt4->execute()) {
            throw new Exception("Failed to add to team");
        }
        
        $message = "Student approved successfully!";
        
    } elseif ($action == 'reject') {
        if (empty($reason)) {
            throw new Exception("Rejection reason is required");
        }
        
        // Update user status
        $sql1 = "UPDATE users SET status = 'rejected' WHERE id = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("i", $user_id);
        
        if (!$stmt1->execute()) {
            throw new Exception("Failed to update user status");
        }
        
        // Update approval with reason
        $sql2 = "UPDATE approvals SET status = 'rejected', approved_by = ?, approved_at = NOW(), remarks = ? WHERE user_id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("isi", $coach_id, $reason, $user_id);
        
        if (!$stmt2->execute()) {
            throw new Exception("Failed to update approval record");
        }
        
        $message = "Student rejected successfully.";
    }
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success'] = $message;
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

// Redirect back
header('Location: approvals.php');
exit();
?>