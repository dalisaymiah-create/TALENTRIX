<?php
// join_team.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: student_dashboard.php');
    exit();
}

$team_id = $_POST['team_id'] ?? 0;
$position = $_POST['position'] ?? '';
$jersey_number = !empty($_POST['jersey_number']) ? (int)$_POST['jersey_number'] : null;

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
$stmt = $pdo->prepare("SELECT id FROM team_members WHERE student_id = ? AND team_id = ?");
$stmt->execute([$student['id'], $team_id]);
if ($stmt->rowCount() > 0) {
    $_SESSION['error'] = "You already have a request for this team.";
    header('Location: student_dashboard.php');
    exit();
}

// Get team details for coach
$stmt = $pdo->prepare("SELECT coach_id FROM teams WHERE id = ?");
$stmt->execute([$team_id]);
$team = $stmt->fetch();

if (!$team || !$team['coach_id']) {
    $_SESSION['error'] = "Invalid team or no coach assigned.";
    header('Location: student_dashboard.php');
    exit();
}

// Start transaction
$pdo->beginTransaction();

try {
    // Add to team_members with pending status
    $stmt = $pdo->prepare("
        INSERT INTO team_members (team_id, student_id, position, jersey_number, status, joined_date) 
        VALUES (?, ?, ?, ?, 'pending', CURDATE())
    ");
    $stmt->execute([$team_id, $student['id'], $position, $jersey_number]);
    
    // Create approval request
    $stmt = $pdo->prepare("
        INSERT INTO approvals (student_id, coach_id, team_id, approval_type, status) 
        VALUES (?, ?, ?, 'team', 'pending')
    ");
    $stmt->execute([$student['id'], $team['coach_id'], $team_id]);
    
    $pdo->commit();
    
    $_SESSION['success'] = "Your request to join the team has been sent to the coach for approval.";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Failed to send request: " . $e->getMessage();
}

header('Location: student_dashboard.php');
exit();
?>