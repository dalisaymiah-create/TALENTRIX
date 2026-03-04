<?php
// process_approval.php - Handle approval/rejection of student requests
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user is coach or trainer (NOT admin)
if ($_SESSION['user_type'] !== 'sport_coach' && $_SESSION['user_type'] !== 'dance_coach') {
    $_SESSION['error'] = 'Access denied. Only coaches can approve students.';
    header('Location: index.php');
    exit();
}

// Get parameters
$approval_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$reason = isset($_GET['reason']) ? $_GET['reason'] : '';

if (!$approval_id || !in_array($action, ['approve', 'reject'])) {
    $_SESSION['error'] = 'Invalid request parameters';
    redirect_back();
}

try {
    // Get approval details
    $stmt = $pdo->prepare("
        SELECT a.*, s.user_id as student_user_id, s.id as student_id,
               u.first_name, u.last_name, u.email,
               a.team_id, a.troupe_id, a.approval_type
        FROM approvals a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE a.id = ? AND a.coach_id = ?
    ");
    $stmt->execute([$approval_id, $_SESSION['user_id']]);
    $approval = $stmt->fetch();

    if (!$approval) {
        $_SESSION['error'] = 'Approval request not found';
        redirect_back();
    }

    if ($approval['status'] !== 'pending') {
        $_SESSION['error'] = 'This request has already been processed';
        redirect_back();
    }

    $pdo->beginTransaction();

    if ($action === 'approve') {
        // Update approval status
        $stmt = $pdo->prepare("
            UPDATE approvals 
            SET status = 'approved', response_date = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$approval_id]);

        // Update student's status to active
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$approval['student_user_id']]);

        // Add to appropriate team/troupe members table
        if ($_SESSION['user_type'] === 'sport_coach') {
            // Sports - add to team_members
            if ($approval['team_id']) {
                $check = $pdo->prepare("SELECT id FROM team_members WHERE student_id = ? AND team_id = ?");
                $check->execute([$approval['student_id'], $approval['team_id']]);
                
                if ($check->rowCount() == 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO team_members (student_id, team_id, status, joined_date) 
                        VALUES (?, ?, 'active', CURDATE())
                    ");
                    $stmt->execute([$approval['student_id'], $approval['team_id']]);
                }
            } else {
                // Get the coach's primary team
                $coach_team = $pdo->prepare("SELECT id FROM teams WHERE coach_id = (SELECT id FROM coaches WHERE user_id = ?) LIMIT 1");
                $coach_team->execute([$_SESSION['user_id']]);
                $team = $coach_team->fetch();
                
                if ($team) {
                    $stmt = $pdo->prepare("
                        INSERT INTO team_members (student_id, team_id, status, joined_date) 
                        VALUES (?, ?, 'active', CURDATE())
                    ");
                    $stmt->execute([$approval['student_id'], $team['id']]);
                }
            }
        } else {
            // Dance - add to dance_troupe_members
            if ($approval['troupe_id']) {
                $check = $pdo->prepare("SELECT id FROM dance_troupe_members WHERE student_id = ? AND troupe_id = ?");
                $check->execute([$approval['student_id'], $approval['troupe_id']]);
                
                if ($check->rowCount() == 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO dance_troupe_members (student_id, troupe_id, status, joined_date) 
                        VALUES (?, ?, 'active', CURDATE())
                    ");
                    $stmt->execute([$approval['student_id'], $approval['troupe_id']]);
                }
            } else {
                // Get the coach's primary troupe
                $coach_troupe = $pdo->prepare("SELECT id FROM dance_troupes WHERE coach_id = (SELECT id FROM dance_coaches WHERE user_id = ?) LIMIT 1");
                $coach_troupe->execute([$_SESSION['user_id']]);
                $troupe = $coach_troupe->fetch();
                
                if ($troupe) {
                    $stmt = $pdo->prepare("
                        INSERT INTO dance_troupe_members (student_id, troupe_id, status, joined_date) 
                        VALUES (?, ?, 'active', CURDATE())
                    ");
                    $stmt->execute([$approval['student_id'], $troupe['id']]);
                }
            }
        }

        $_SESSION['success'] = 'Student approved successfully! They can now login.';
    } else {
        // Reject with reason
        $stmt = $pdo->prepare("
            UPDATE approvals 
            SET status = 'rejected', response_date = NOW(), rejection_reason = ? 
            WHERE id = ?
        ");
        $stmt->execute([$reason, $approval_id]);

        // Update student's status to rejected
        $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$approval['student_user_id']]);

        $_SESSION['success'] = 'Student rejected';
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'Error processing request: ' . $e->getMessage();
    error_log("Approval error: " . $e->getMessage());
}

// Redirect back
function redirect_back() {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'sport_coach') {
        header('Location: coach_dashboard.php');
    } else {
        header('Location: dance_trainer_dashboard.php');
    }
    exit();
}

redirect_back();
?>