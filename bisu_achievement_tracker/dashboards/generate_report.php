<?php
// Set JSON header first
header('Content-Type: application/json');

// Disable error display in output
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config/database.php';
require_once '../includes/auth.php';

try {
    $auth = new Auth($pdo);
    $auth->requireAuth();
    $auth->requireRole('admin');
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Authentication failed: ' . $e->getMessage()
    ]);
    exit;
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$isSports = isset($_GET['sports']);
$isCultural = isset($_GET['cultural']);
$coachId = isset($_GET['coach_id']) && $_GET['coach_id'] !== '' ? $_GET['coach_id'] : null;
$eventId = isset($_GET['event_id']) && $_GET['event_id'] !== '' ? $_GET['event_id'] : null;
$teamId = isset($_GET['team_id']) && $_GET['team_id'] !== '' ? $_GET['team_id'] : null;
$year = isset($_GET['year']) && $_GET['year'] !== '' ? $_GET['year'] : null;

$result = [];
$headers = [];

try {
    if ($type === 'coach') {
        // Build coach report query with LEFT JOIN to show coaches even without participations
        $query = "
            SELECT 
                c.first_name || ' ' || c.last_name as coach_name,
                c.specialization as coach_specialization,
                COALESCE(s.first_name || ' ' || s.last_name, 'No participations yet') as student_name,
                COALESCE(s.course, '-') as student_course,
                COALESCE(s.yr_level::text, '-') as year_level,
                COALESCE(a.activity_name, '-') as activity_name,
                COALESCE(a.activity_type, '-') as activity_type,
                COALESCE(c2.name, '-') as contest_name,
                COALESCE(p.ranking, '-') as ranking,
                COALESCE(p.role, '-') as role,
                COALESCE(p.created_at::text, '-') as participation_date,
                COALESCE(t.team_name, '-') as team_name
            FROM Coach c
            LEFT JOIN Participation p ON c.id = p.coach_id
            LEFT JOIN Student s ON p.student_id = s.id
            LEFT JOIN Contest c2 ON p.contest_id = c2.id
            LEFT JOIN Activity a ON c2.activity_id = a.id
            LEFT JOIN Team t ON p.team_id = t.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($isSports) {
            // Sports admin filtering
            $query .= " AND (
                c.specialization ILIKE '%sports%' 
                OR c.specialization ILIKE '%basketball%' 
                OR c.specialization ILIKE '%volleyball%'
                OR c.specialization ILIKE '%swimming%'
                OR c.specialization ILIKE '%athletics%'
                OR a.activity_type_filter = 'sports'
            )";
        }
        if ($isCultural) {
            // Cultural arts admin filtering
            $query .= " AND (
                c.specialization ILIKE '%arts%' 
                OR c.specialization ILIKE '%music%' 
                OR c.specialization ILIKE '%theater%' 
                OR c.specialization ILIKE '%dance%'
                OR c.specialization ILIKE '%cultural%'
                OR a.activity_type_filter = 'cultural_arts'
            )";
        }
        if ($coachId && $coachId !== '') {
            $query .= " AND c.id = ?";
            $params[] = (int)$coachId;
        }
        if ($year && $year !== '') {
            $query .= " AND (EXTRACT(YEAR FROM p.created_at) = ? OR p.created_at IS NULL)";
            $params[] = (int)$year;
        }
        
        $query .= " ORDER BY c.last_name, p.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $headers = ['Coach Name', 'Specialization', 'Student Name', 'Course', 'Year Level', 'Activity', 'Type', 'Contest', 'Ranking', 'Role', 'Date', 'Team'];
        
    } elseif ($type === 'event') {
        // Build event report query with LEFT JOIN to show all events
        $query = "
            SELECT 
                a.activity_name,
                a.activity_type,
                a.year as activity_year,
                COALESCE(c2.name, '-') as contest_name,
                COALESCE(c2.tournament_manager, '-') as tournament_manager,
                COALESCE(cat.category_name, '-') as category_name,
                COALESCE(s.first_name || ' ' || s.last_name, 'No participants') as student_name,
                COALESCE(s.course, '-') as student_course,
                COALESCE(co.first_name || ' ' || co.last_name, '-') as coach_name,
                COALESCE(p.ranking, '-') as ranking,
                COALESCE(p.role, '-') as role,
                COALESCE(p.created_at::text, '-') as participation_date,
                COALESCE(t.team_name, '-') as team_name
            FROM Activity a
            LEFT JOIN Contest c2 ON a.id = c2.activity_id
            LEFT JOIN Category cat ON c2.category_id = cat.id
            LEFT JOIN Participation p ON c2.id = p.contest_id
            LEFT JOIN Student s ON p.student_id = s.id
            LEFT JOIN Coach co ON p.coach_id = co.id
            LEFT JOIN Team t ON p.team_id = t.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($isSports) {
            // Sports admin filtering
            $query .= " AND a.activity_type_filter = 'sports'";
        }
        if ($isCultural) {
            // Cultural arts admin filtering
            $query .= " AND a.activity_type_filter = 'cultural_arts'";
        }
        if ($eventId && $eventId !== '') {
            $query .= " AND a.id = ?";
            $params[] = (int)$eventId;
        }
        if ($year && $year !== '') {
            $query .= " AND a.year = ?";
            $params[] = (int)$year;
        }
        
        $query .= " ORDER BY a.year DESC, a.activity_name, p.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $headers = ['Activity Name', 'Type', 'Year', 'Contest', 'Tournament Manager', 'Category', 'Student Name', 'Course', 'Coach', 'Ranking', 'Role', 'Date', 'Team'];
        
    } elseif ($type === 'team') {
        // Build team report query with LEFT JOIN to show all teams
        $query = "
            SELECT 
                t.team_name,
                t.specialization as team_specialization,
                COALESCE(co.first_name || ' ' || co.last_name, '-') as coach_name,
                COALESCE(s.first_name || ' ' || s.last_name, 'No members') as student_name,
                COALESCE(s.course, '-') as student_course,
                COALESCE(s.yr_level::text, '-') as year_level,
                COALESCE(a.activity_name, '-') as activity_name,
                COALESCE(a.activity_type, '-') as activity_type,
                COALESCE(c2.name, '-') as contest_name,
                COALESCE(p.ranking, '-') as ranking,
                COALESCE(p.role, '-') as role,
                COALESCE(p.created_at::text, '-') as participation_date
            FROM Team t
            LEFT JOIN Coach co ON t.coach_id = co.id
            LEFT JOIN Student s ON t.id = s.team_id
            LEFT JOIN Participation p ON s.id = p.student_id
            LEFT JOIN Contest c2 ON p.contest_id = c2.id
            LEFT JOIN Activity a ON c2.activity_id = a.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($isSports) {
            // Sports admin filtering
            $query .= " AND (
                t.specialization ILIKE '%sports%' 
                OR t.specialization ILIKE '%basketball%' 
                OR t.specialization ILIKE '%volleyball%'
                OR t.specialization ILIKE '%swimming%'
                OR t.specialization ILIKE '%athletics%'
                OR co.specialization ILIKE '%sports%'
            )";
        }
        if ($isCultural) {
            // Cultural arts admin filtering
            $query .= " AND (
                t.specialization ILIKE '%arts%' 
                OR t.specialization ILIKE '%music%' 
                OR t.specialization ILIKE '%dance%' 
                OR t.specialization ILIKE '%theater%'
                OR t.specialization ILIKE '%cultural%'
                OR co.specialization ILIKE '%arts%'
                OR co.specialization ILIKE '%music%'
            )";
        }
        if ($teamId && $teamId !== '') {
            $query .= " AND t.id = ?";
            $params[] = (int)$teamId;
        }
        if ($year && $year !== '') {
            $query .= " AND (EXTRACT(YEAR FROM p.created_at) = ? OR p.created_at IS NULL)";
            $params[] = (int)$year;
        }
        
        $query .= " ORDER BY t.team_name, s.last_name, p.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $headers = ['Team Name', 'Team Specialization', 'Coach Name', 'Student Name', 'Course', 'Year Level', 'Activity', 'Type', 'Contest', 'Ranking', 'Role', 'Date'];
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid report type'
        ]);
        exit;
    }
    
    // Format result for JSON response - remove any nulls
    $formattedResult = [];
    foreach ($result as $row) {
        $cleanRow = [];
        foreach ($row as $key => $value) {
            // Convert null to empty string for cleaner output
            $cleanRow[$key] = $value !== null ? $value : '';
        }
        $formattedResult[] = $cleanRow;
    }
    
    echo json_encode([
        'success' => true,
        'headers' => $headers,
        'data' => $formattedResult,
        'count' => count($formattedResult)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>