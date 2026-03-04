<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'coach') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendance_data = $_POST['attendance'] ?? [];
    $attendance_date = date('Y-m-d');
    
    // Get coach id
    $stmt = $pdo->prepare("SELECT id FROM coaches WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $coach = $stmt->fetch();
    
    if (!$coach) {
        $_SESSION['error'] = "Coach record not found.";
        header('Location: coach_dashboard.php');
        exit();
    }
    
    $pdo->beginTransaction();
    
    try {
        foreach ($attendance_data as $student_id => $status) {
            // Check if attendance already marked for today
            $stmt = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND attendance_date = ?");
            $stmt->execute([$student_id, $attendance_date]);
            
            if ($stmt->rowCount() == 0) {
                // Get team/troupe info for this student under this coach
                $stmt = $pdo->prepare("
                    SELECT tm.team_id, dtm.troupe_id 
                    FROM students s
                    LEFT JOIN team_members tm ON s.id = tm.student_id AND tm.status = 'active'
                    LEFT JOIN teams t ON tm.team_id = t.id AND t.coach_id = ?
                    LEFT JOIN dance_troupe_members dtm ON s.id = dtm.student_id AND dtm.status = 'active'
                    LEFT JOIN dance_troupes dt ON dtm.troupe_id = dt.id AND dt.coach_id = ?
                    WHERE s.id = ? AND (t.coach_id = ? OR dt.coach_id = ?)
                    LIMIT 1
                ");
                $stmt->execute([$coach['id'], $coach['id'], $student_id, $coach['id'], $coach['id']]);
                $info = $stmt->fetch();
                
                // Insert attendance record
                $stmt = $pdo->prepare("INSERT INTO attendance (student_id, team_id, troupe_id, attendance_date, status, marked_by) 
                                       VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $student_id,
                    $info['team_id'] ?? null,
                    $info['troupe_id'] ?? null,
                    $attendance_date,
                    $status,
                    $coach['id']
                ]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Attendance marked successfully for " . date('F d, Y');
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to mark attendance: " . $e->getMessage();
    }
    
    header('Location: coach_dashboard.php');
    exit();
}
?>