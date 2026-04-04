<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth($pdo);
$auth->requireAuth();
$auth->requireRole('coach');

$coachId = $_SESSION['coach_id'];

// Get coach profile
$stmt = $pdo->prepare("SELECT * FROM Coach WHERE id = ?");
$stmt->execute([$coachId]);
$coach = $stmt->fetch();

// Get coach's specializations as array
$specializations = !empty($coach['specialization']) ? explode(',', $coach['specialization']) : [];
$specializations = array_map('trim', $specializations);
$specializations = array_filter($specializations);

// Predefined specialization options
$specializationOptions = [
    'Sports' => [
        'Basketball',
        'Volleyball',
        'Football/Soccer',
        'Baseball/Softball',
        'Swimming',
        'Track and Field - Running',
        'Track and Field - Jumping',
        'Track and Field - Throwing',
        'Badminton',
        'Table Tennis',
        'Tennis',
        'Chess',
        'Taekwondo',
        'Karate',
        'Arnis',
        'Weightlifting',
        'Gymnastics',
        'Dance Sports'
    ],
    'Athletics' => [
        'Sprint Events (100m, 200m, 400m)',
        'Middle Distance (800m, 1500m)',
        'Long Distance (3000m, 5000m)',
        'Hurdles',
        'Relay Races',
        'High Jump',
        'Long Jump',
        'Triple Jump',
        'Shot Put',
        'Discus Throw',
        'Javelin Throw',
        'Hammer Throw',
        'Marathon',
        'Cross Country',
        'Race Walking'
    ],
    'Culture and Arts' => [
        'Dance Troupe - Folk Dance',
        'Dance Troupe - Modern Dance',
        'Dance Troupe - Hip Hop',
        'Dance Troupe - Ballroom',
        'Dance Troupe - Contemporary',
        'Choral Group - Classical',
        'Choral Group - Pop',
        'Choral Group - Gospel',
        'Band - Marching Band',
        'Band - Rock Band',
        'Band - Jazz Band',
        'Rondalla',
        'Guitar Ensemble',
        'Vocal Solo - Pop',
        'Vocal Solo - Classical',
        'Vocal Solo - Kundiman',
        'Visual Arts - Painting',
        'Visual Arts - Drawing',
        'Visual Arts - Sculpture',
        'Visual Arts - Digital Art',
        'Theater Arts - Acting',
        'Theater Arts - Play Production',
        'Theater Arts - Musical Theater',
        'Literary Arts - Poetry',
        'Literary Arts - Short Story',
        'Literary Arts - Essay',
        'Photography',
        'Film Making',
        'Costume Design',
        'Set Design',
        'Cultural Presentations',
        'Pageantry'
    ]
];

// Get all students based on coach's specialization
$students = [];
if (!empty($specializations)) {
    // Build the condition for contest name matching
    $conditions = [];
    $params = [];
    
    foreach ($specializations as $spec) {
        $conditions[] = "c.name ILIKE ?";
        $params[] = '%' . $spec . '%';
    }
    $specializationCondition = implode(' OR ', $conditions);
    
    // Query to get students who participated in contests matching coach's specialization
    $query = "
        SELECT DISTINCT s.*, 
               COALESCE((
                   SELECT COUNT(p2.id) 
                   FROM Participation p2 
                   WHERE p2.student_id = s.id AND p2.coach_id = ?
               ), 0) as total_participations,
               COALESCE((
                   SELECT COUNT(p2.id) 
                   FROM Participation p2 
                   WHERE p2.student_id = s.id 
                   AND p2.coach_id = ? 
                   AND p2.ranking IN ('champion', '1st_place', '2nd_place', '3rd_place')
               ), 0) as awards,
               COALESCE((
                   SELECT COUNT(p2.id) 
                   FROM Participation p2 
                   WHERE p2.student_id = s.id 
                   AND p2.coach_id = ? 
                   AND p2.ranking = 'champion'
               ), 0) as championships
        FROM Student s
        WHERE EXISTS (
            SELECT 1 
            FROM Participation p 
            JOIN Contest c ON p.contest_id = c.id 
            WHERE p.student_id = s.id 
            AND ($specializationCondition)
        )
        ORDER BY s.last_name
    ";
    
    // Add coach_id parameter three times for the subqueries
    $allParams = array_merge([$coachId, $coachId, $coachId], $params);
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($allParams);
    $students = $stmt->fetchAll();
}

// Get teams created by this coach
$teams = [];
$teamMembers = [];
if (!empty($specializations)) {
    // Get all teams for this coach using the Team table
    $stmt = $pdo->prepare("
        SELECT * FROM Team 
        WHERE coach_id = ? 
        ORDER BY id DESC
    ");
    $stmt->execute([$coachId]);
    $teams = $stmt->fetchAll();
    
    // Get team members for each team from Student table
    foreach ($teams as $index => $team) {
        $stmt = $pdo->prepare("
            SELECT s.id as student_id, s.first_name, s.last_name, s.course, s.yr_level
            FROM Student s
            WHERE s.team_id = ?
            ORDER BY s.last_name
        ");
        $stmt->execute([$team['id']]);
        $teamMembers[$team['id']] = $stmt->fetchAll();
    }
}

// Get recent participations (only those matching specialization)
$recentParticipations = [];
if (!empty($specializations)) {
    $conditions = [];
    $params = [$coachId];
    
    foreach ($specializations as $spec) {
        $conditions[] = "c.name ILIKE ?";
        $params[] = '%' . $spec . '%';
    }
    $specializationCondition = implode(' OR ', $conditions);
    
    $query = "
        SELECT p.*, s.first_name, s.last_name, s.course, c.name as contest_name, a.activity_name, a.activity_type
        FROM Participation p
        JOIN Student s ON p.student_id = s.id
        JOIN Contest c ON p.contest_id = c.id
        JOIN Activity a ON c.activity_id = a.id
        WHERE p.coach_id = ? AND ($specializationCondition)
        ORDER BY p.created_at DESC
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $recentParticipations = $stmt->fetchAll();
}

// Get all participations (for achievements)
$allParticipations = [];
if (!empty($specializations)) {
    $conditions = [];
    $params = [$coachId];
    
    foreach ($specializations as $spec) {
        $conditions[] = "c.name ILIKE ?";
        $params[] = '%' . $spec . '%';
    }
    $specializationCondition = implode(' OR ', $conditions);
    
    $query = "
        SELECT p.*, s.first_name, s.last_name, s.course, c.name as contest_name, a.activity_name, a.activity_type
        FROM Participation p
        JOIN Student s ON p.student_id = s.id
        JOIN Contest c ON p.contest_id = c.id
        JOIN Activity a ON c.activity_id = a.id
        WHERE p.coach_id = ? AND ($specializationCondition)
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $allParticipations = $stmt->fetchAll();
}

// Get contest winners summary
$winners = [];
if (!empty($specializations)) {
    $conditions = [];
    $params = [$coachId];
    
    foreach ($specializations as $spec) {
        $conditions[] = "c.name ILIKE ?";
        $params[] = '%' . $spec . '%';
    }
    $specializationCondition = implode(' OR ', $conditions);
    
    $query = "
        SELECT 
            cat.category_name,
            c.name AS contest_name,
            act.activity_name,
            act.activity_type,
            c.year,
            s.first_name AS student_first_name,
            s.last_name AS student_last_name,
            p.ranking,
            p.role,
            p.created_at
        FROM Contest c
        LEFT JOIN Category cat ON c.category_id = cat.id
        JOIN Activity act ON c.activity_id = act.id
        JOIN Participation p ON c.id = p.contest_id
        JOIN Student s ON p.student_id = s.id
        WHERE p.coach_id = ? 
        AND p.ranking IN ('champion', '1st_place', '2nd_place', '3rd_place')
        AND ($specializationCondition)
        ORDER BY c.year DESC, p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $winners = $stmt->fetchAll();
}

// Get all contests for dropdown
$allContests = [];
if (!empty($specializations)) {
    $conditions = [];
    $params = [];
    
    foreach ($specializations as $spec) {
        $conditions[] = "c.name ILIKE ?";
        $params[] = '%' . $spec . '%';
    }
    $specializationCondition = implode(' OR ', $conditions);
    
    $query = "
        SELECT c.*, a.activity_name, a.activity_type
        FROM Contest c 
        JOIN Activity a ON c.activity_id = a.id 
        WHERE $specializationCondition
        ORDER BY c.year DESC, c.id DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $allContests = $stmt->fetchAll();
}

// Get all activities
$activities = [];
if (!empty($specializations)) {
    $conditions = [];
    $params = [];
    
    foreach ($specializations as $spec) {
        $conditions[] = "c.name ILIKE ?";
        $params[] = '%' . $spec . '%';
    }
    $specializationCondition = implode(' OR ', $conditions);
    
    $query = "
        SELECT DISTINCT a.* 
        FROM Activity a
        JOIN Contest c ON a.id = c.activity_id
        WHERE $specializationCondition
        ORDER BY a.year DESC, a.id DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $activities = $stmt->fetchAll();
}

// Get statistics for dashboard
$stats = [];

// Total students under this coach
$stats['total_students'] = count($students);

// Total participations
$stats['total_participations'] = count($allParticipations);

// Total awards
$stats['total_awards'] = count($winners);

// Total championships
$stats['total_championships'] = 0;
foreach ($winners as $w) {
    if ($w['ranking'] == 'champion') {
        $stats['total_championships']++;
    }
}

// Medal distribution
$medalCounts = ['champion' => 0, '1st_place' => 0, '2nd_place' => 0, '3rd_place' => 0];
foreach ($winners as $w) {
    if (isset($medalCounts[$w['ranking']])) {
        $medalCounts[$w['ranking']]++;
    }
}

// Group students by course for "My Teams"
$courseGroups = [];
foreach ($students as $s) {
    $course = $s['course'];
    if (!isset($courseGroups[$course])) {
        $courseGroups[$course] = [];
    }
    $courseGroups[$course][] = $s;
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_specialization':
                    $newSpecialization = trim($_POST['specialization']);
                    if (!empty($newSpecialization)) {
                        // Get current specialization
                        $stmt = $pdo->prepare("SELECT specialization FROM Coach WHERE id = ?");
                        $stmt->execute([$coachId]);
                        $currentSpec = $stmt->fetchColumn();
                        
                        // Append new specialization if not already present
                        $currentSpecArray = !empty($currentSpec) ? explode(',', $currentSpec) : [];
                        $currentSpecArray = array_map('trim', $currentSpecArray);
                        
                        if (!in_array($newSpecialization, $currentSpecArray)) {
                            $currentSpecArray[] = $newSpecialization;
                            $updatedSpec = implode(', ', $currentSpecArray);
                            
                            $stmt = $pdo->prepare("UPDATE Coach SET specialization = ? WHERE id = ?");
                            $stmt->execute([$updatedSpec, $coachId]);
                            $message = "Specialization added successfully!";
                            $messageType = "success";
                            
                            // Refresh page to show updated data
                            header("Location: coach.php?updated=1");
                            exit();
                        } else {
                            $message = "Specialization already exists!";
                            $messageType = "error";
                        }
                    } else {
                        $message = "Please select a specialization.";
                        $messageType = "error";
                    }
                    break;
                    
                case 'remove_specialization':
                    $specToRemove = trim($_POST['spec_to_remove']);
                    if (!empty($specToRemove)) {
                        // Get current specialization
                        $stmt = $pdo->prepare("SELECT specialization FROM Coach WHERE id = ?");
                        $stmt->execute([$coachId]);
                        $currentSpec = $stmt->fetchColumn();
                        
                        $currentSpecArray = !empty($currentSpec) ? explode(',', $currentSpec) : [];
                        $currentSpecArray = array_map('trim', $currentSpecArray);
                        
                        // Remove the specialization
                        $key = array_search($specToRemove, $currentSpecArray);
                        if ($key !== false) {
                            unset($currentSpecArray[$key]);
                            $updatedSpec = !empty($currentSpecArray) ? implode(', ', $currentSpecArray) : null;
                            
                            $stmt = $pdo->prepare("UPDATE Coach SET specialization = ? WHERE id = ?");
                            $stmt->execute([$updatedSpec, $coachId]);
                            $message = "Specialization removed successfully!";
                            $messageType = "success";
                            
                            header("Location: coach.php?updated=1");
                            exit();
                        }
                    }
                    break;
                    
                case 'create_team':
                    $teamName = trim($_POST['team_name']);
                    $teamSpecialization = trim($_POST['team_specialization']);
                    
                    if (!empty($teamName) && !empty($teamSpecialization)) {
                        // Check if team name already exists for this coach
                        $stmt = $pdo->prepare("SELECT id FROM Team WHERE coach_id = ? AND team_name = ?");
                        $stmt->execute([$coachId, $teamName]);
                        if (!$stmt->fetch()) {
                            $stmt = $pdo->prepare("
                                INSERT INTO Team (team_name, coach_id, specialization) 
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$teamName, $coachId, $teamSpecialization]);
                            $message = "Team created successfully!";
                            $messageType = "success";
                            
                            header("Location: coach.php?team_created=1");
                            exit();
                        } else {
                            $message = "Team name already exists!";
                            $messageType = "error";
                        }
                    } else {
                        $message = "Please fill in all required fields!";
                        $messageType = "error";
                    }
                    break;
                    // In coach.php, find the add_team_member case and update it:

case 'add_team_member':
    $teamId = $_POST['team_id'];
    $studentId = $_POST['student_id'];
    
    // Check if student is already in a team
    $stmt = $pdo->prepare("SELECT team_id FROM Student WHERE id = ?");
    $stmt->execute([$studentId]);
    $currentTeamId = $stmt->fetchColumn();
    
    if ($currentTeamId && $currentTeamId != $teamId) {
        // Student is in a different team, remove them from that team first
        // Update participations for the old team
        $stmt = $pdo->prepare("UPDATE Participation SET team_id = NULL WHERE student_id = ? AND team_id = ?");
        $stmt->execute([$studentId, $currentTeamId]);
    }
    
    // Add student to new team
    $stmt = $pdo->prepare("UPDATE Student SET team_id = ? WHERE id = ?");
    $stmt->execute([$teamId, $studentId]);
    
    $message = "Student added to team successfully!";
    $messageType = "success";
    break;
                    // In coach.php, find the remove_team_member case and update it:

case 'remove_team_member':
    $teamId = $_POST['team_id'];
    $studentId = $_POST['student_id'];
    
    // First, verify the student is in this team
    $stmt = $pdo->prepare("SELECT team_id FROM Student WHERE id = ?");
    $stmt->execute([$studentId]);
    $currentTeam = $stmt->fetchColumn();
    
    if ($currentTeam == $teamId) {
        // Update participations that were associated with this team for this student
        // Set team_id to NULL for those participations
        $stmt = $pdo->prepare("UPDATE Participation SET team_id = NULL WHERE student_id = ? AND team_id = ?");
        $stmt->execute([$studentId, $teamId]);
        
        // Remove student from team
        $stmt = $pdo->prepare("UPDATE Student SET team_id = NULL WHERE id = ? AND team_id = ?");
        $stmt->execute([$studentId, $teamId]);
        
        $message = "Student removed from team successfully!";
    } else {
        $message = "Student is not in this team!";
        $messageType = "error";
    }
    break;
 // In coach.php, update the delete_team case:

case 'delete_team':
    try {
        $pdo->beginTransaction();
        
        $teamId = $_POST['team_id'];
        
        // First, verify the team belongs to this coach
        $stmt = $pdo->prepare("SELECT id FROM Team WHERE id = ? AND coach_id = ?");
        $stmt->execute([$teamId, $coachId]);
        
        if ($stmt->fetch()) {
            // Update students to remove team_id (will be set to NULL by constraint, but let's do it explicitly)
            $stmt = $pdo->prepare("UPDATE Student SET team_id = NULL WHERE team_id = ?");
            $stmt->execute([$teamId]);
            
            // Delete the team - participations will have team_id set to NULL by constraint
            $stmt = $pdo->prepare("DELETE FROM Team WHERE id = ?");
            $stmt->execute([$teamId]);
            
            $pdo->commit();
            $message = "Team deleted successfully!";
            $messageType = "success";
        } else {
            $pdo->rollBack();
            $message = "Team not found or you don't have permission to delete it!";
            $messageType = "error";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Error deleting team: " . $e->getMessage();
        $messageType = "error";
    }
    break;
                    
                case 'add_achievement':
                    // Check if the contest matches coach's specialization
                    $stmt = $pdo->prepare("
                        SELECT c.name FROM Contest c WHERE c.id = ?
                    ");
                    $stmt->execute([$_POST['contest_id']]);
                    $contest = $stmt->fetch();
                    
                    $contestMatches = false;
                    foreach ($specializations as $spec) {
                        if (stripos($contest['name'], $spec) !== false) {
                            $contestMatches = true;
                            break;
                        }
                    }
                    
                    if ($contestMatches || empty($specializations)) {
                        // Get student's team_id if any
                        $stmt = $pdo->prepare("SELECT team_id FROM Student WHERE id = ?");
                        $stmt->execute([$_POST['student_id']]);
                        $studentTeam = $stmt->fetch();
                        $teamId = $studentTeam['team_id'] ?: null;
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO Participation (student_id, contest_id, coach_id, team_id, ranking, role) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $_POST['student_id'],
                            $_POST['contest_id'],
                            $coachId,
                            $teamId,
                            $_POST['ranking'],
                            $_POST['role']
                        ]);
                        $message = "Achievement recorded successfully!";
                        $messageType = "success";
                        
                        header("Location: coach.php?success=1");
                        exit();
                    } else {
                        $message = "This contest does not match your specialization!";
                        $messageType = "error";
                    }
                    break;
            }
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $message = "Achievement recorded successfully!";
    $messageType = "success";
}

if (isset($_GET['updated'])) {
    $message = "Specialization updated successfully!";
    $messageType = "success";
}

if (isset($_GET['team_created'])) {
    $message = "Team created successfully!";
    $messageType = "success";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Dashboard - BISU Candijay Achievement Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #eef2f7 100%);
            min-height: 100vh;
        }

        /* Dashboard Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #003366;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 35px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.1);
        }

        .avatar {
            width: 85px;
            height: 85px;
            background: linear-gradient(135deg, #ffd89b, #c7e9fb);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2.5rem;
            color: #134b5e;
            font-weight: bold;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .sidebar-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .sidebar-header p {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 5px;
        }

        .sidebar-nav {
            padding: 25px 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            margin: 5px 15px;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: all 0.3s ease;
            gap: 12px;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
        }

        .sidebar-nav a i {
            width: 24px;
            font-size: 1.2rem;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 35px;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-title h2 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #003366, #003366);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .page-title p {
            color: #6c757d;
            margin-top: 5px;
            font-size: 0.9rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info span {
            background: #f8f9fa;
            padding: 8px 18px;
            border-radius: 40px;
            font-size: 0.85rem;
            color: #495057;
        }

        .logout-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 8px 22px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.85rem;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.03);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-info h3 {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: #003366;
        }

        .stat-icon i {
            font-size: 3rem;
            color: #134b5e;
            opacity: 0.2;
        }

        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 35px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .chart-container h3 {
            margin-bottom: 20px;
            color: #134b5e;
            font-weight: 700;
        }

        canvas {
            max-height: 300px;
        }

        /* Data Table */
        .data-table {
            background: white;
            border-radius: 24px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
            margin-bottom: 25px;
        }

        .data-table h3 {
            margin-bottom: 20px;
            color: #134b5e;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.85rem;
        }

        tr:hover {
            background: #f8f9fa;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-champion {
            background: #ffd700;
            color: #7d5d1a;
        }

        .badge-1st_place {
            background: #c0c0c0;
            color: #4a4a4a;
        }

        .badge-2nd_place {
            background: #cd7f32;
            color: white;
        }

        .badge-3rd_place {
            background: #b87333;
            color: white;
        }

        .badge-finalist {
            background: #28a745;
            color: white;
        }

        .badge-participant {
            background: #6c757d;
            color: white;
        }

        /* Team Cards */
        .team-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #e67e22;
        }

        .team-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .team-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #134b5e;
        }

        .team-spec {
            font-size: 0.8rem;
            color: #003366;
        }

        .team-members {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .member {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .member-name {
            font-weight: 500;
        }

        .member-stats {
            font-size: 0.75rem;
            color: #e67e22;
        }

        .member-actions {
            display: flex;
            gap: 5px;
        }

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .btn-icon:hover {
            background: rgba(0, 0, 0, 0.1);
        }

        .btn-icon.remove {
            color: #dc3545;
        }

        .team-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }

        /* Form Cards */
        .form-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            margin-bottom: 35px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .form-card h3 {
            margin-bottom: 20px;
            color: #134b5e;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            transition: 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #e67e22;
            outline: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #003366, #003366);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(230, 126, 34, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 5px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .btn-sm {
            padding: 5px 12px;
            font-size: 0.8rem;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Activity Cards */
        .activity-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: 0.3s;
            border: 1px solid #e9ecef;
        }

        .activity-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .activity-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #134b5e;
            margin-bottom: 10px;
        }

        .activity-details {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .contest-list {
            margin-top: 15px;
            padding-left: 20px;
        }

        .contest-item {
            padding: 8px;
            border-left: 3px solid #e67e22;
            margin-bottom: 8px;
            background: #f8f9fa;
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            max-width: 300px;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }

        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1002;
            background: #134b5e;
            color: white;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1001;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .menu-toggle {
                display: block;
            }
        }

        .section {
            animation: fadeInUp 0.4s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .announcement-card {
            background: #fef9e6;
            border-left: 4px solid #e67e22;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            transition: 0.3s;
        }

        .announcement-card:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .announcement-title {
            font-weight: 700;
            color: #134b5e;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .announcement-date {
            font-size: 0.75rem;
            color: #e67e22;
            margin-bottom: 10px;
        }

        .specialization-badge {
            display: inline-block;
            background: #e67e22;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin: 3px;
        }

        .specialization-group {
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
        }

        .specialization-group-header {
            background: #f8f9fa;
            padding: 12px 15px;
            font-weight: 700;
            color: #134b5e;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
        }

        .specialization-group-header i {
            margin-right: 8px;
        }

        .specialization-group-options {
            padding: 15px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .specialization-option {
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
        }

        .specialization-option:hover {
            background: #e67e22;
            color: white;
            transform: translateX(5px);
        }

        .specialization-option.selected {
            background: #e67e22;
            color: white;
        }

        .remove-spec-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.7rem;
            margin-left: 8px;
            cursor: pointer;
        }

        .remove-spec-btn:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
<div class="menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</div>
<div class="dashboard-container">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="avatar">
                <i class="fas fa-chalkboard-user"></i>
            </div>
            <h3>Coach <?php echo htmlspecialchars($coach['first_name']); ?></h3>
            <p>
                <?php 
                if (!empty($specializations)) {
                    foreach ($specializations as $spec): 
                ?>
                <span class="specialization-badge"><?php echo htmlspecialchars(trim($spec)); ?></span>
                <?php 
                    endforeach;
                } else {
                    echo "No specialization set";
                }
                ?>
            </p>
        </div>
        <div class="sidebar-nav">
            <a onclick="showSection('overview')" class="active"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a onclick="showSection('teams')"><i class="fas fa-users"></i> My Teams</a>
            <a onclick="showSection('createteam')"><i class="fas fa-plus-circle"></i> Create Team</a>
            <a onclick="showSection('students')"><i class="fas fa-user-graduate"></i> My Students</a>
            <a onclick="showSection('activities')"><i class="fas fa-calendar-alt"></i> Activities & Announcement</a>
            <a onclick="showSection('achievements')"><i class="fas fa-trophy"></i> Achievements</a>
            <a onclick="showSection('specialization')"><i class="fas fa-plus-circle"></i> Add Specialization</a>
            <a onclick="showSection('reports')"><i class="fas fa-chart-line"></i> Reports</a>
            <a onclick="showSection('profile')"><i class="fas fa-id-card"></i> Profile</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h2>Coach Command Center</h2>
                <p>Empowering champions • <?php echo htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']); ?></p>
            </div>
            <div class="user-info">
                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($_SESSION['email']); ?></span>
                <a href="../logout.php" class="logout-btn">Exit</a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard Section -->
        <div id="overview-section" class="section">
            <div class="stats-grid">
                <div class="stat-card" onclick="showSection('students')">
                    <div class="stat-info">
                        <h3>🏅 Athletes</h3>
                        <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-card" onclick="showSection('teams')">
                    <div class="stat-info">
                        <h3>👥 Teams</h3>
                        <div class="stat-number"><?php echo count($teams); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-people-group"></i>
                    </div>
                </div>
                <div class="stat-card" onclick="showSection('achievements')">
                    <div class="stat-info">
                        <h3>📋 Participations</h3>
                        <div class="stat-number"><?php echo $stats['total_participations']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-flag-checkered"></i>
                    </div>
                </div>
                <div class="stat-card" onclick="showSection('achievements')">
                    <div class="stat-info">
                        <h3>🏆 Awards Earned</h3>
                        <div class="stat-number"><?php echo $stats['total_awards']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-medal"></i>
                    </div>
                </div>
                <div class="stat-card" onclick="showSection('achievements')">
                    <div class="stat-info">
                        <h3>👑 Championships</h3>
                        <div class="stat-number"><?php echo $stats['total_championships']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($winners)): ?>
            <div class="chart-container">
                <h3><i class="fas fa-chart-bar"></i> Medal Distribution</h3>
                <canvas id="medalChart"></canvas>
            </div>
            <?php endif; ?>
            
            <div class="data-table">
                <h3><i class="fas fa-clock"></i> Recent Activities</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Contest</th>
                            <th>Ranking</th>
                            <th>Role</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach(array_slice($recentParticipations, 0, 8) as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['contest_name']); ?></td>
                            <td><span class="badge badge-<?php echo str_replace('_', '-', $p['ranking']); ?>"><?php echo str_replace('_', ' ', $p['ranking']); ?></span></td>
                            <td><?php echo htmlspecialchars($p['role']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentParticipations)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;">No participations recorded yet.<?php if (empty($specializations)): ?> Please add your specialization first.<?php endif; ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- My Teams Section -->
        <div id="teams-section" class="section" style="display: none;">
            <div class="data-table">
                <h3><i class="fas fa-people-group"></i> My Teams</h3>
                <?php if (empty($teams)): ?>
                    <p><?php if (empty($specializations)): ?>Please add your specialization first to create teams.<?php else: ?>No teams created yet. Click "Create Team" to get started.<?php endif; ?></p>
                <?php else: ?>
                    <?php foreach ($teams as $team): ?>
                    <div class="team-card">
                        <div class="team-header">
                            <div>
                                <span class="team-name"><i class="fas fa-users"></i> <?php echo htmlspecialchars($team['team_name']); ?></span>
                                <div class="team-spec">Specialization: <?php echo htmlspecialchars($team['specialization']); ?></div>
                            </div>
                            <span class="badge badge-participant"><?php echo isset($teamMembers[$team['id']]) ? count($teamMembers[$team['id']]) : 0; ?> members</span>
                        </div>
                        
                        <div class="team-members">
                            <?php if (isset($teamMembers[$team['id']]) && !empty($teamMembers[$team['id']])): ?>
                                <?php foreach ($teamMembers[$team['id']] as $member): ?>
                                <div class="member">
                                    <div class="member-name">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                        <br><small><?php echo htmlspecialchars($member['course']); ?> - Year <?php echo $member['yr_level']; ?></small>
                                    </div>
                                    <div class="member-actions">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this member from the team?')">
                                            <input type="hidden" name="action" value="remove_team_member">
                                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                            <input type="hidden" name="student_id" value="<?php echo $member['student_id']; ?>">
                                            <button type="submit" class="btn-icon remove" title="Remove from team">
                                                <i class="fas fa-user-minus"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #999; text-align: center; padding: 20px;">No members yet. Add students from "My Students" section.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="team-actions">
                            <button class="btn-secondary btn-sm" onclick="showAddMemberForm(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars($team['team_name']); ?>')">
                                <i class="fas fa-user-plus"></i> Add Member
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this team? All members will be removed.')">
                                <input type="hidden" name="action" value="delete_team">
                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                <button type="submit" class="btn-danger btn-sm">
                                    <i class="fas fa-trash"></i> Delete Team
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add Member Modal -->
        <div id="addMemberModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 24px; padding: 30px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
                <h3 style="margin-bottom: 20px;">Add Member to <span id="modalTeamName"></span></h3>
                <form method="POST" id="addMemberForm">
                    <input type="hidden" name="action" value="add_team_member">
                    <input type="hidden" name="team_id" id="modalTeamId">
                    <div class="form-group">
                        <label>Select Student</label>
                        <select name="student_id" id="modalStudentSelect" required>
                            <option value="">Choose a student</option>
                            <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['course'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <button type="submit" class="btn-primary">Add Member</button>
                        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Create Team Section -->
        <div id="createteam-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-plus-circle"></i> Create New Team</h3>
                <?php if (empty($specializations)): ?>
                    <div class="message error" style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i> Please add your specialization first before creating a team.
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="create_team">
                    <div class="form-group">
                        <label>Team Name *</label>
                        <input type="text" name="team_name" placeholder="e.g., BISU Eagles Basketball Team" required <?php echo empty($specializations) ? 'disabled' : ''; ?>>
                    </div>
                    <div class="form-group">
                        <label>Specialization *</label>
                        <select name="team_specialization" required <?php echo empty($specializations) ? 'disabled' : ''; ?>>
                            <option value="">Select specialization for this team</option>
                            <?php foreach ($specializations as $spec): ?>
                            <option value="<?php echo htmlspecialchars(trim($spec)); ?>"><?php echo htmlspecialchars(trim($spec)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #6c757d;">This team will be associated with this specialization</small>
                    </div>
                    <button type="submit" class="btn-primary" <?php echo empty($specializations) ? 'disabled' : ''; ?>><i class="fas fa-save"></i> Create Team</button>
                </form>
            </div>
            
        </div>

        <!-- My Students Section -->
        <div id="students-section" class="section" style="display: none;">
            <div class="data-table">
                <h3><i class="fas fa-user-graduate"></i> Athlete Roster (<?php echo !empty($specializations) ? implode(', ', $specializations) : 'No specialization'; ?>)</h3>
                <?php if (empty($specializations)): ?>
                    <div class="message error" style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i> Please add your specialization first to see students under your expertise.
                    </div>
                <?php endif; ?>
                <?php if (!empty($specializations) && empty($students)): ?>
                    <div class="message" style="margin-bottom: 20px; background: #e3f2fd; color: #0c5460;">
                        <i class="fas fa-info-circle"></i> No students found under your specialization(s): <?php echo implode(', ', $specializations); ?>. Students will appear once they participate in contests matching your specialization.
                    </div>
                <?php endif; ?>
                <?php if (!empty($students)): ?>
                <div class="search-box">
                    <input type="text" id="studentSearch" placeholder="Search students..." onkeyup="filterStudents()">
                </div>
                <table id="studentsTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Year</th>
                            <th>Participations</th>
                            <th>Awards</th>
                            <th>Championships</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $s): ?>
                        <tr class="student-row">
                            <td><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($s['course']); ?></td>
                            <td><?php echo $s['yr_level']; ?> Year</td>
                            <td><?php echo $s['total_participations']; ?></td>
                            <td><i class="fas fa-trophy"></i> <?php echo $s['awards']; ?></td>
                            <td><i class="fas fa-crown"></i> <?php echo $s['championships']; ?></td>
                            <td>
                                <button class="btn-secondary btn-sm" onclick="showAddToTeam(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>')">
                                    <i class="fas fa-user-plus"></i> Add to Team
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add to Team Modal -->
        <div id="addToTeamModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 24px; padding: 30px; max-width: 500px; width: 90%;">
                <h3 style="margin-bottom: 20px;">Add <span id="studentNameSpan"></span> to Team</h3>
                <form method="POST" id="addToTeamForm">
                    <input type="hidden" name="action" value="add_team_member">
                    <input type="hidden" name="student_id" id="addToTeamStudentId">
                    <div class="form-group">
                        <label>Select Team</label>
                        <select name="team_id" id="teamSelect" required>
                            <option value="">Choose a team</option>
                            <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['team_name'] . ' (' . $team['specialization'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <button type="submit" class="btn-primary">Add to Team</button>
                        <button type="button" class="btn-secondary" onclick="closeAddToTeamModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Activities & Announcement Section -->
        <div id="activities-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-bullhorn"></i> Upcoming Activities (<?php echo !empty($specializations) ? implode(', ', $specializations) : 'No specialization'; ?>)</h3>
                <?php if (empty($specializations)): ?>
                    <div class="message error" style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i> Please add your specialization first to see relevant activities.
                    </div>
                <?php endif; ?>
                <?php if (empty($activities) && !empty($specializations)): ?>
                    <p>No activities available for your specialization.</p>
                <?php elseif (!empty($activities)): ?>
                    <?php foreach(array_slice($activities, 0, 5) as $activity): ?>
                    <div class="announcement-card">
                        <div class="announcement-title">
                            <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($activity['activity_name']); ?>
                        </div>
                        <div class="announcement-date">
                            <?php echo ucfirst($activity['activity_type']); ?> • Year: <?php echo $activity['year']; ?>
                        </div>
                        <p><?php echo htmlspecialchars($activity['event_name'] ?: 'No event details'); ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="form-card">
                <h3><i class="fas fa-info-circle"></i> Announcements</h3>
                <div class="announcement-card">
                    <div class="announcement-title">🏆 Upcoming Tournament: Regional Sportsfest 2025</div>
                    <div class="announcement-date">March 15, 2025</div>
                    <p>All athletes are advised to submit their medical certificates by March 20. Practice schedule: Monday & Wednesday, 3:00 PM - 6:00 PM.</p>
                </div>
                <div class="announcement-card">
                    <div class="announcement-title">📝 Registration for BISU Olympics 2025</div>
                    <div class="announcement-date">March 10, 2025</div>
                    <p>Registration is now open for the annual BISU Olympics. Deadline: April 15, 2025.</p>
                </div>
                <div class="announcement-card">
                    <div class="announcement-title">✨ Recognition Day 2025</div>
                    <div class="announcement-date">March 5, 2025</div>
                    <p>Save the date: April 30, 2025 for the annual recognition ceremony. All awardees must confirm attendance.</p>
                </div>
            </div>
        </div>

        <!-- Achievements Section -->
        <div id="achievements-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-plus-circle"></i> Record New Achievement</h3>
                <?php if (empty($specializations)): ?>
                    <div class="message error" style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i> Please add your specialization first to record achievements.
                    </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_achievement">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Athlete *</label>
                            <select name="student_id" required <?php echo empty($students) ? 'disabled' : ''; ?>>
                                <option value="">Select student</option>
                                <?php foreach($students as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?> (<?php echo $s['course']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Contest / Event *</label>
                            <select name="contest_id" required <?php echo empty($allContests) ? 'disabled' : ''; ?>>
                                <option value="">Choose contest</option>
                                <?php foreach($allContests as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name'] . ' (' . $c['activity_name'] . ' - ' . $c['year'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Ranking *</label>
                            <select name="ranking" required>
                                <option value="champion">🏆 Champion</option>
                                <option value="1st_place">🥇 1st Place</option>
                                <option value="2nd_place">🥈 2nd Place</option>
                                <option value="3rd_place">🥉 3rd Place</option>
                                <option value="finalist">⭐ Finalist</option>
                                <option value="participant">📌 Participant</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Role *</label>
                            <input type="text" name="role" placeholder="e.g., Team Captain, Solo Player, Lead Vocalist" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary" <?php echo empty($specializations) ? 'disabled' : ''; ?>><i class="fas fa-save"></i> Record Achievement</button>
                </form>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-trophy"></i> All Achievements & Winners (<?php echo !empty($specializations) ? implode(', ', $specializations) : 'No specialization'; ?>)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Contest</th>
                            <th>Activity</th>
                            <th>Ranking</th>
                            <th>Role</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($allParticipations as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['first_name'].' '.$p['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['contest_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['activity_name']); ?></td>
                            <td><span class="badge badge-<?php echo str_replace('_', '-', $p['ranking']); ?>"><?php echo str_replace('_', ' ', $p['ranking']); ?></span></td>
                            <td><?php echo htmlspecialchars($p['role']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($allParticipations)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;">No achievements recorded yet.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Specialization Section with Categorized Options -->
        <div id="specialization-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-plus-circle"></i> Add New Specialization</h3>
                
                <?php if (!empty($specializations)): ?>
                <div style="margin-bottom: 25px;">
                    <h4>Your Current Specializations:</h4>
                    <div style="padding: 15px; background: #f8f9fa; border-radius: 12px; margin-top: 10px;">
                        <?php foreach ($specializations as $spec): ?>
                            <span class="specialization-badge">
                                <?php echo htmlspecialchars(trim($spec)); ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this specialization?')">
                                    <input type="hidden" name="action" value="remove_specialization">
                                    <input type="hidden" name="spec_to_remove" value="<?php echo htmlspecialchars(trim($spec)); ?>">
                                    <button type="submit" class="remove-spec-btn" style="margin-left: 8px;">✕</button>
                                </form>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="specializationForm">
                    <input type="hidden" name="action" value="update_specialization">
                    <input type="hidden" name="specialization" id="selectedSpecialization" value="">
                    
                    <div class="form-group">
                        <label>Select Specialization Category:</label>
                        
                        <!-- Sports Category -->
                        <div class="specialization-group">
                            <div class="specialization-group-header" onclick="toggleGroup('sports-group')">
                                <i class="fas fa-futbol"></i> Sports & Athletic
                                <i class="fas fa-chevron-down" style="float: right;"></i>
                            </div>
                            <div id="sports-group" class="specialization-group-options">
                                <?php foreach ($specializationOptions['Sports'] as $option): ?>
                                <div class="specialization-option" data-value="<?php echo htmlspecialchars($option); ?>" onclick="selectSpecialization(this)">
                                    <?php echo htmlspecialchars($option); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Athletics Category -->
                        <div class="specialization-group">
                            <div class="specialization-group-header" onclick="toggleGroup('athletics-group')">
                                <i class="fas fa-running"></i> Athletics (Track & Field)
                                <i class="fas fa-chevron-down" style="float: right;"></i>
                            </div>
                            <div id="athletics-group" class="specialization-group-options">
                                <?php foreach ($specializationOptions['Athletics'] as $option): ?>
                                <div class="specialization-option" data-value="<?php echo htmlspecialchars($option); ?>" onclick="selectSpecialization(this)">
                                    <?php echo htmlspecialchars($option); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Culture and Arts Category -->
                        <div class="specialization-group">
                            <div class="specialization-group-header" onclick="toggleGroup('culture-group')">
                                <i class="fas fa-palette"></i> Culture and Arts
                                <i class="fas fa-chevron-down" style="float: right;"></i>
                            </div>
                            <div id="culture-group" class="specialization-group-options">
                                <?php foreach ($specializationOptions['Culture and Arts'] as $option): ?>
                                <div class="specialization-option" data-value="<?php echo htmlspecialchars($option); ?>" onclick="selectSpecialization(this)">
                                    <?php echo htmlspecialchars($option); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" id="selectedDisplay" style="display: none;">
                        <label>Selected Specialization:</label>
                        <div style="padding: 12px; background: #e3f2fd; border-radius: 8px; color: #0c5460;">
                            <i class="fas fa-check-circle"></i> <span id="selectedSpecName"></span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary" id="addSpecBtn" disabled><i class="fas fa-plus-circle"></i> Add Specialization</button>
                </form>
            </div>
            

        <!-- Reports Section -->
        <div id="reports-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-chart-line"></i> Performance Reports</h3>
                <div class="stats-grid" style="margin-bottom: 20px;">
                    <div class="stat-card">
                        <div class="stat-info"><h3>🏆 Total Medals</h3><div class="stat-number"><?php echo $stats['total_awards']; ?></div></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info"><h3>👑 Championships</h3><div class="stat-number"><?php echo $stats['total_championships']; ?></div></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info"><h3>⭐ Students Trained</h3><div class="stat-number"><?php echo $stats['total_students']; ?></div></div>
                    </div>
                </div>
                
                <h4>Medal Distribution</h4>
                <div class="form-row">
                    <div class="form-group"><label>🏆 Champions</label><input type="text" value="<?php echo $medalCounts['champion']; ?>" readonly style="font-size: 1.5rem; font-weight: bold; color: #ffd700;"></div>
                    <div class="form-group"><label>🥇 Gold Medals (1st Place)</label><input type="text" value="<?php echo $medalCounts['1st_place']; ?>" readonly style="font-size: 1.5rem; font-weight: bold;"></div>
                    <div class="form-group"><label>🥈 Silver Medals (2nd Place)</label><input type="text" value="<?php echo $medalCounts['2nd_place']; ?>" readonly style="font-size: 1.5rem; font-weight: bold;"></div>
                    <div class="form-group"><label>🥉 Bronze Medals (3rd Place)</label><input type="text" value="<?php echo $medalCounts['3rd_place']; ?>" readonly style="font-size: 1.5rem; font-weight: bold;"></div>
                </div>
                
                <button onclick="generateReport()" class="btn-primary" style="margin-top: 20px;"><i class="fas fa-download"></i> Generate & Download Report (CSV)</button>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-trophy"></i> Detailed Winners List</h3>
                表格
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Contest</th>
                            <th>Student</th>
                            <th>Ranking</th>
                            <th>Year</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($winners as $w): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($w['category_name'] ?? 'General'); ?></td>
                            <td><?php echo htmlspecialchars($w['contest_name']); ?></td>
                            <td><?php echo htmlspecialchars($w['student_first_name'].' '.$w['student_last_name']); ?></td>
                            <td><span class="badge badge-<?php echo str_replace('_', '-', $w['ranking']); ?>"><?php echo str_replace('_', ' ', $w['ranking']); ?></span></td>
                            <td><?php echo $w['year']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($winners)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;">No winners recorded yet.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profile-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-id-card"></i> Coach Profile</h3>
                <div class="form-row">
                    <div class="form-group"><label>First Name</label><input type="text" value="<?php echo htmlspecialchars($coach['first_name']); ?>" readonly></div>
                    <div class="form-group"><label>Last Name</label><input type="text" value="<?php echo htmlspecialchars($coach['last_name']); ?>" readonly></div>
                </div>
                <div class="form-group">
                    <label>Specializations</label>
                    <div style="padding: 12px; background: #f8f9fa; border-radius: 12px;">
                        <?php 
                        if (!empty($specializations)): 
                            foreach ($specializations as $spec): 
                        ?>
                            <span class="specialization-badge" style="background: #134b5e;"><?php echo htmlspecialchars(trim($spec)); ?></span>
                        <?php 
                            endforeach;
                        else: 
                        ?>
                            <span style="color: #999;">No specializations added yet.</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group"><label>Email</label><input type="email" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" readonly></div>
                <div class="form-group"><label>Coach ID</label><input type="text" value="<?php echo $coach['id']; ?>" readonly></div>
                <div class="form-group"><label>Total Students</label><input type="text" value="<?php echo $stats['total_students']; ?>" readonly></div>
                <div class="form-group"><label>Total Awards</label><input type="text" value="<?php echo $stats['total_awards']; ?>" readonly></div>
                <div class="form-group"><label>Total Championships</label><input type="text" value="<?php echo $stats['total_championships']; ?>" readonly></div>
                <div class="form-group"><label>Teams Created</label><input type="text" value="<?php echo count($teams); ?>" readonly></div>
            </div>
        </div>
    </div>
</div>

<script>
// Section navigation
function showSection(section) {
    const sections = ['overview', 'teams', 'createteam', 'students', 'activities', 'achievements', 'specialization', 'reports', 'profile'];
    sections.forEach(sec => {
        const el = document.getElementById(sec + '-section');
        if(el) el.style.display = 'none';
    });
    const activeSection = document.getElementById(section + '-section');
    if(activeSection) activeSection.style.display = 'block';
    
    const links = document.querySelectorAll('.sidebar-nav a');
    links.forEach(link => link.classList.remove('active'));
    if(event && event.target) {
        const clickedLink = event.target.closest('a');
        if(clickedLink) clickedLink.classList.add('active');
    }
    
    // Close sidebar on mobile after click
    if(window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('active');
    }
}

// Toggle specialization group visibility
function toggleGroup(groupId) {
    const group = document.getElementById(groupId);
    if (group.style.display === 'none' || !group.style.display) {
        group.style.display = 'grid';
    } else {
        group.style.display = 'none';
    }
}

// Initialize all groups to be visible
document.addEventListener('DOMContentLoaded', function() {
    const groups = ['sports-group', 'athletics-group', 'culture-group'];
    groups.forEach(groupId => {
        const group = document.getElementById(groupId);
        if (group) {
            group.style.display = 'grid';
        }
    });
});

// Select specialization
let selectedSpec = '';
function selectSpecialization(element) {
    // Remove selected class from all options
    document.querySelectorAll('.specialization-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    
    // Add selected class to clicked option
    element.classList.add('selected');
    
    // Get the value
    selectedSpec = element.getAttribute('data-value');
    
    // Update hidden input and display
    document.getElementById('selectedSpecialization').value = selectedSpec;
    document.getElementById('selectedSpecName').innerHTML = selectedSpec;
    document.getElementById('selectedDisplay').style.display = 'block';
    document.getElementById('addSpecBtn').disabled = false;
}

// Student search filter
function filterStudents() {
    const input = document.getElementById('studentSearch');
    if (!input) return;
    const filter = input.value.toLowerCase();
    const table = document.getElementById('studentsTable');
    if (!table) return;
    const rows = table.getElementsByClassName('student-row');
    
    for(let i = 0; i < rows.length; i++) {
        const name = rows[i].getElementsByTagName('td')[0];
        const course = rows[i].getElementsByTagName('td')[1];
        if(name && course) {
            const textValue = name.textContent.toLowerCase() + ' ' + course.textContent.toLowerCase();
            if(textValue.indexOf(filter) > -1) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
        }
    }
}

// Show Add Member Modal
function showAddMemberForm(teamId, teamName) {
    document.getElementById('modalTeamId').value = teamId;
    document.getElementById('modalTeamName').innerHTML = teamName;
    document.getElementById('addMemberModal').style.display = 'flex';
}

// Show Add to Team Modal
function showAddToTeam(studentId, studentName) {
    document.getElementById('addToTeamStudentId').value = studentId;
    document.getElementById('studentNameSpan').innerHTML = studentName;
    document.getElementById('addToTeamModal').style.display = 'flex';
}

// Close modals
function closeModal() {
    document.getElementById('addMemberModal').style.display = 'none';
}

function closeAddToTeamModal() {
    document.getElementById('addToTeamModal').style.display = 'none';
}

// Generate report as CSV
function generateReport() {
    let csvContent = "Student,Contest,Ranking,Role,Date\n";
    <?php foreach($allParticipations as $p): ?>
    csvContent += "<?php echo addslashes($p['first_name'] . ' ' . $p['last_name']); ?>,<?php echo addslashes($p['contest_name']); ?>,<?php echo addslashes(str_replace('_', ' ', $p['ranking'])); ?>,<?php echo addslashes($p['role']); ?>,<?php echo date('Y-m-d', strtotime($p['created_at'])); ?>\n";
    <?php endforeach; ?>
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'coach_report_<?php echo date('Y-m-d'); ?>.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Toggle sidebar for mobile
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
}

// Chart for medal distribution
<?php if (!empty($winners)): ?>
const medalCtx = document.getElementById('medalChart');
if(medalCtx) {
    new Chart(medalCtx, {
        type: 'doughnut',
        data: {
            labels: ['Champions', '1st Place', '2nd Place', '3rd Place'],
            datasets: [{
                data: [<?php echo $medalCounts['champion']; ?>, <?php echo $medalCounts['1st_place']; ?>, <?php echo $medalCounts['2nd_place']; ?>, <?php echo $medalCounts['3rd_place']; ?>],
                backgroundColor: ['#ffd700', '#c0c0c0', '#cd7f32', '#b87333'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}
<?php endif; ?>

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    if(window.innerWidth <= 768 && sidebar.classList.contains('active')) {
        if(!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    }
    
    // Close modals when clicking outside
    const modal = document.getElementById('addMemberModal');
    if(modal && modal.style.display === 'flex') {
        if(event.target === modal) {
            closeModal();
        }
    }
    
    const addToTeamModal = document.getElementById('addToTeamModal');
    if(addToTeamModal && addToTeamModal.style.display === 'flex') {
        if(event.target === addToTeamModal) {
            closeAddToTeamModal();
        }
    }
});
</script>
</body>
</html>