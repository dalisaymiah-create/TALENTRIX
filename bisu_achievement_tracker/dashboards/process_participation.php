<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth($pdo);
$auth->requireAuth();
$auth->requireRole('coach');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Participation (student_id, contest_id, coach_id, ranking, role) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['student_id'],
            $_POST['contest_id'],
            $_POST['coach_id'],
            $_POST['ranking'],
            $_POST['role']
        ]);
        
        $_SESSION['message'] = "Participation recorded successfully!";
        $_SESSION['message_type'] = "success";
        
    } catch (PDOException $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
}

header("Location: coach.php");
exit();
?>