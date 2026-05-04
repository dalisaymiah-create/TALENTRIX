<?php
require_once '../includes/session.php';
redirectIfNotLoggedIn();

if ($_SESSION['usertype'] !== 'admin' || $_SESSION['admin_type'] !== 'cultural') {
    header("Location: " . getDashboardPath($_SESSION['usertype'], $_SESSION['admin_type'] ?? null));
    exit();
}

require_once '../config/database.php';

// Helper function to safely get membership status
function getMembershipStatus($part) {
    if (isset($part['membership_status'])) {
        return $part['membership_status'];
    }
    if (isset($part['participation_status'])) {
        return $part['participation_status'];
    }
    return 'active';
}

// Get admin info
$admin_name = $_SESSION['email'] ?? 'Cultural Admin';
$current_admin_type = 'cultural';

// Get statistics
$stats = [];

// Get total cultural events
$stmt = $pdo->query("SELECT COUNT(*) as count FROM activity WHERE activity_type IN ('cultural', 'arts')");
$stats['total_events'] = $stmt->fetch()['count'];

// Get total contests
$stmt = $pdo->query("SELECT COUNT(*) as count FROM contest");
$stats['active_contests'] = $stmt->fetch()['count'];

// Get total participants
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT s.id) as count 
    FROM student s
    JOIN participation p ON s.id = p.student_id AND p.membership_status = 'active'
    JOIN team t ON p.team_id = t.id
    JOIN contest c ON t.contest_id = c.id
    JOIN activity a ON c.activity_id = a.id
    WHERE a.activity_type IN ('cultural', 'arts')
");
$stats['total_participants'] = $stmt->fetch()['count'];

$participations = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id as participation_id,
            p.ranking,
            p.membership_status,
            p.created_at as joined_date,
            s.id as student_id,
            u.first_name,
            u.last_name,
            s.course,
            s.yr_level,
            s.college as student_college,
            t.id as team_id,
            t.team_name,
            c.id as contest_id,
            c.name as contest_name,
            a.id as activity_id,
            a.activity_name,
            a.school_year,
            a.competition_level,
            cat.category_name,
            cat.category_type,
            uc.first_name as coach_first,
            uc.last_name as coach_last
        FROM participation p
        JOIN student s ON p.student_id = s.id
        JOIN \"User\" u ON s.user_id = u.id
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        JOIN category cat ON c.category_id = cat.id
        LEFT JOIN coach co ON t.coach_id = co.id
        LEFT JOIN \"User\" uc ON co.user_id = uc.id
        WHERE a.activity_type IN ('cultural', 'arts')
        ORDER BY a.school_year DESC, c.name, t.team_name
    ");
    $stmt->execute();
    $participations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $participations = [];
}

// Get all unique contests for filter
$allContests = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name, a.school_year
        FROM contest c
        JOIN activity a ON c.activity_id = a.id
        WHERE a.activity_type IN ('cultural', 'arts')
        ORDER BY a.school_year DESC, c.name
    ");
    $stmt->execute();
    $allContests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $allContests = [];
}

$allTeams = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.id, t.team_name
        FROM team t
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        WHERE a.activity_type IN ('cultural', 'arts')
        ORDER BY t.team_name
    ");
    $stmt->execute();
    $allTeams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $allTeams = [];
}

$allSchoolYears = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT a.school_year
        FROM activity a
        WHERE a.activity_type IN ('cultural', 'arts') AND a.school_year IS NOT NULL
        ORDER BY a.school_year DESC
    ");
    $stmt->execute();
    $allSchoolYears = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $allSchoolYears = [];
}

$allRankings = [
    '1st Place', '2nd Place', '3rd Place', 'Champion', 
    '1st Runner Up', '2nd Runner Up', '3rd Runner Up', 
    'Finalist', 'Best Performer', 'Best in Talent',
    'People\'s Choice', 'Special Award', 'Participant'
];

// Get cultural events
$cultural_events = [];
$stmt = $pdo->query("
    SELECT a.*, COALESCE(c.contest_count, 0) as contest_count
    FROM activity a
    LEFT JOIN (SELECT activity_id, COUNT(DISTINCT id) as contest_count FROM contest GROUP BY activity_id) c ON a.id = c.activity_id
    WHERE a.activity_type IN ('cultural', 'arts')
    ORDER BY a.school_year DESC
");
$cultural_events = $stmt->fetchAll();

// Get announcements
$announcements = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.email as author_email, act.activity_name, act.school_year, act.competition_level
        FROM announcement a
        JOIN \"User\" u ON a.author_id = u.id
        LEFT JOIN activity act ON a.activity_id = act.id
        WHERE a.is_published = true 
        AND a.admin_type = 'cultural'
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll();
    
    foreach ($announcements as &$announcement) {
        if (!empty($announcement['image_path'])) {
            $decoded = json_decode($announcement['image_path'], true);
            if (is_array($decoded)) {
                $announcement['images'] = $decoded;
                $announcement['image_count'] = count($decoded);
            } else {
                $announcement['images'] = [$announcement['image_path']];
                $announcement['image_count'] = 1;
            }
        } else {
            $announcement['images'] = [];
            $announcement['image_count'] = 0;
        }
    }
} catch(PDOException $e) {
    $announcements = [];
}

// Get categories for Culture and Arts
$categories = [];
$stmt = $pdo->query("SELECT id, category_name, category_type FROM category WHERE category_type = 'Culture and Arts' ORDER BY category_name");
$categories = $stmt->fetchAll();

// Get activities for dropdown
$activities = [];
$stmt = $pdo->query("SELECT id, activity_name, school_year, competition_level FROM activity WHERE activity_type IN ('cultural', 'arts') ORDER BY school_year DESC");
$activities = $stmt->fetchAll();

// Get teams by contest
$allTeamsByContest = [];
$stmt = $pdo->query("
    SELECT 
        t.id as team_id,
        t.team_name,
        c.id as contest_id,
        c.name as contest_name,
        a.activity_name,
        a.school_year,
        a.competition_level,
        cat.category_name,
        uc.first_name as coach_first,
        uc.last_name as coach_last,
        COUNT(DISTINCT p.student_id) as member_count,
        STRING_AGG(DISTINCT CONCAT(u.first_name, ' ', u.last_name, ' (', s.course, ')'), ', ') as members
    FROM team t
    JOIN contest c ON t.contest_id = c.id
    JOIN activity a ON c.activity_id = a.id
    JOIN category cat ON c.category_id = cat.id
    LEFT JOIN coach co ON t.coach_id = co.id
    LEFT JOIN \"User\" uc ON co.user_id = uc.id
    LEFT JOIN participation p ON t.id = p.team_id AND p.membership_status = 'active'
    LEFT JOIN student s ON p.student_id = s.id
    LEFT JOIN \"User\" u ON s.user_id = u.id
    WHERE a.activity_type IN ('cultural', 'arts')
    GROUP BY t.id, t.team_name, c.id, c.name, a.activity_name, a.school_year, a.competition_level, cat.category_name, uc.first_name, uc.last_name
    ORDER BY a.school_year DESC, c.name
");
$allTeamsByContest = $stmt->fetchAll();

$teamsByContest = [];
foreach ($allTeamsByContest as $team) {
    $contestKey = $team['contest_id'];
    if (!isset($teamsByContest[$contestKey])) {
        $teamsByContest[$contestKey] = [
            'contest_name' => $team['contest_name'],
            'activity_name' => $team['activity_name'],
            'school_year' => $team['school_year'],
            'competition_level' => $team['competition_level'],
            'teams' => []
        ];
    }
    $teamsByContest[$contestKey]['teams'][] = $team;
}

// Get cultural contests
$cultural_contests = [];
$stmt = $pdo->query("
    SELECT DISTINCT
        c.id,
        c.name,
        c.contest_status,
        a.activity_name,
        a.school_year,
        a.competition_level,
        cat.category_name,
        COALESCE(t.team_count, 0) as team_count,
        COALESCE(s.participant_count, 0) as participant_count
    FROM contest c
    JOIN activity a ON c.activity_id = a.id
    JOIN category cat ON c.category_id = cat.id
    LEFT JOIN (SELECT contest_id, COUNT(DISTINCT id) as team_count FROM team GROUP BY contest_id) t ON c.id = t.contest_id
    LEFT JOIN (SELECT t.contest_id, COUNT(DISTINCT p.student_id) as participant_count FROM team t JOIN participation p ON t.id = p.team_id AND p.membership_status = 'active' GROUP BY t.contest_id) s ON c.id = s.contest_id
    WHERE a.activity_type IN ('cultural', 'arts')
    ORDER BY a.school_year DESC, c.name
");
$cultural_contests = $stmt->fetchAll();

// Get available school years
$availableSchoolYears = [];
$stmt = $pdo->query("
    SELECT DISTINCT school_year 
    FROM activity 
    WHERE activity_type IN ('cultural', 'arts') AND school_year IS NOT NULL
    ORDER BY school_year DESC
");
$availableSchoolYears = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getCurrentSchoolYear() {
    $current_year = date('Y');
    $current_month = date('n');
    if ($current_month >= 6) {
        return $current_year . '-' . ($current_year + 1);
    } else {
        return ($current_year - 1) . '-' . $current_year;
    }
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update Ranking
    if (isset($_POST['update_ranking'])) {
        $participation_id = $_POST['participation_id'];
        $ranking = trim($_POST['ranking']);
        if (!empty($ranking)) {
            try {
                $stmt = $pdo->prepare("UPDATE participation SET ranking = ? WHERE id = ?");
                $stmt->execute([$ranking, $participation_id]);
                $message = "Ranking updated successfully!";
                // Refresh participations
                $stmt = $pdo->prepare("
                    SELECT p.*, s.course, s.yr_level, s.college, u.first_name, u.last_name, t.team_name, c.name as contest_name, a.activity_name, a.school_year, a.competition_level, cat.category_name
                    FROM participation p
                    JOIN student s ON p.student_id = s.id
                    JOIN \"User\" u ON s.user_id = u.id
                    JOIN team t ON p.team_id = t.id
                    JOIN contest c ON t.contest_id = c.id
                    JOIN activity a ON c.activity_id = a.id
                    JOIN category cat ON c.category_id = cat.id
                    WHERE a.activity_type IN ('cultural', 'arts')
                ");
                $stmt->execute();
                $participations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch(PDOException $e) {
                $error = "Error updating ranking: " . $e->getMessage();
            }
        } else {
            $error = "Please enter a ranking value.";
        }
    }
    
    // Update Status
    if (isset($_POST['update_status'])) {
        $participation_id = $_POST['participation_id'];
        $status = $_POST['status'];
        if (in_array($status, ['active', 'inactive'])) {
            try {
                $stmt = $pdo->prepare("UPDATE participation SET membership_status = ? WHERE id = ?");
                $stmt->execute([$status, $participation_id]);
                $message = "Status updated successfully!";
                // Refresh participations
                $stmt = $pdo->prepare("
                    SELECT p.*, s.course, s.yr_level, s.college, u.first_name, u.last_name, t.team_name, c.name as contest_name, a.activity_name, a.school_year, a.competition_level, cat.category_name
                    FROM participation p
                    JOIN student s ON p.student_id = s.id
                    JOIN \"User\" u ON s.user_id = u.id
                    JOIN team t ON p.team_id = t.id
                    JOIN contest c ON t.contest_id = c.id
                    JOIN activity a ON c.activity_id = a.id
                    JOIN category cat ON c.category_id = cat.id
                    WHERE a.activity_type IN ('cultural', 'arts')
                ");
                $stmt->execute();
                $participations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch(PDOException $e) {
                $error = "Error updating status: " . $e->getMessage();
            }
        } else {
            $error = "Invalid status value.";
        }
    }
    
    // Create Activity
    if (isset($_POST['create_activity'])) {
        $activity_name = trim($_POST['activity_name']);
        $school_year = $_POST['school_year'];
        $competition_level = $_POST['competition_level'];
        
        if (!empty($activity_name) && !empty($school_year) && !empty($competition_level)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO activity (activity_name, activity_type, school_year, competition_level) VALUES (?, 'cultural', ?, ?)");
                $stmt->execute([$activity_name, $school_year, $competition_level]);
                $message = "Cultural activity created successfully!";
                
                // Refresh events
                $stmt = $pdo->query("
                    SELECT a.*, COALESCE(c.contest_count, 0) as contest_count
                    FROM activity a
                    LEFT JOIN (SELECT activity_id, COUNT(DISTINCT id) as contest_count FROM contest GROUP BY activity_id) c ON a.id = c.activity_id
                    WHERE a.activity_type IN ('cultural', 'arts')
                    ORDER BY a.school_year DESC
                ");
                $cultural_events = $stmt->fetchAll();
                
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM activity WHERE activity_type IN ('cultural', 'arts')");
                $stats['total_events'] = $stmt->fetch()['count'];
                
                // Refresh available school years
                $stmt = $pdo->query("
                    SELECT DISTINCT school_year 
                    FROM activity 
                    WHERE activity_type IN ('cultural', 'arts') AND school_year IS NOT NULL
                    ORDER BY school_year DESC
                ");
                $availableSchoolYears = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Refresh activities for dropdown
                $stmt = $pdo->query("SELECT id, activity_name, school_year, competition_level FROM activity WHERE activity_type IN ('cultural', 'arts') ORDER BY school_year DESC");
                $activities = $stmt->fetchAll();
                
            } catch(PDOException $e) {
                $error = "Error creating activity: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all required fields.";
        }
    }
    
    // Create Contest
    if (isset($_POST['create_contest'])) {
        $contest_name = trim($_POST['contest_name']);
        $activity_id = $_POST['activity_id'];
        $category_id = $_POST['category_id'];
        
        if (!empty($contest_name) && !empty($activity_id) && !empty($category_id)) {
            try {
                $stmt = $pdo->prepare("SELECT category_type FROM category WHERE id = ?");
                $stmt->execute([$category_id]);
                $category = $stmt->fetch();
                
                if (!$category || $category['category_type'] !== 'Culture and Arts') {
                    $error = "Invalid category selected. Cultural admin can only create contests for Culture and Arts categories.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO contest (name, activity_id, category_id, contest_status) VALUES (?, ?, ?, 'active')");
                    $stmt->execute([$contest_name, $activity_id, $category_id]);
                    $message = "Cultural contest created successfully!";
                    
                    // Refresh contests
                    $stmt = $pdo->query("
                        SELECT DISTINCT c.id, c.name, a.activity_name, a.school_year, a.competition_level, cat.category_name, COALESCE(t.team_count,0) as team_count, COALESCE(s.participant_count,0) as participant_count
                        FROM contest c
                        JOIN activity a ON c.activity_id = a.id
                        JOIN category cat ON c.category_id = cat.id
                        LEFT JOIN (SELECT contest_id, COUNT(DISTINCT id) as team_count FROM team GROUP BY contest_id) t ON c.id = t.contest_id
                        LEFT JOIN (SELECT t.contest_id, COUNT(DISTINCT p.student_id) as participant_count FROM team t JOIN participation p ON t.id = p.team_id AND p.membership_status = 'active' GROUP BY t.contest_id) s ON c.id = s.contest_id
                        WHERE a.activity_type IN ('cultural', 'arts')
                        ORDER BY a.school_year DESC
                    ");
                    $cultural_contests = $stmt->fetchAll();
                }
            } catch(PDOException $e) {
                $error = "Error creating contest: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all fields.";
        }
    }
    
    // Handle Create Announcement
    if (isset($_POST['create_announcement'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $activity_id = !empty($_POST['activity_id']) ? $_POST['activity_id'] : null;
        
        $image_paths = [];
        
        if (isset($_FILES['announcement_images']) && !empty($_FILES['announcement_images']['name'][0])) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $upload_dir = '../includes/uploads/announcements/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            foreach ($_FILES['announcement_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['announcement_images']['error'][$key] == 0) {
                    $filename = $_FILES['announcement_images']['name'][$key];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, $allowed)) {
                        $new_filename = uniqid() . '_' . time() . '_' . $key . '.' . $ext;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($tmp_name, $upload_path)) {
                            $image_paths[] = 'includes/uploads/announcements/' . $new_filename;
                        }
                    }
                }
            }
        }
        
        $image_path_json = !empty($image_paths) ? json_encode($image_paths) : null;
        
        if (!empty($title) && !empty($content)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO announcement (title, content, author_id, is_published, announcement_type, image_path, admin_type, activity_id, created_at) VALUES (?, ?, ?, true, 'general', ?, 'cultural', ?, NOW())");
                $stmt->execute([$title, $content, $_SESSION['user_id'], $image_path_json, $activity_id]);
                $message = "Announcement posted successfully with " . count($image_paths) . " image(s)!";
                
                // Refresh announcements
                $stmt = $pdo->prepare("
                    SELECT a.*, u.email as author_email, act.activity_name, act.school_year, act.competition_level
                    FROM announcement a
                    JOIN \"User\" u ON a.author_id = u.id
                    LEFT JOIN activity act ON a.activity_id = act.id
                    WHERE a.is_published = true AND a.admin_type = 'cultural'
                    ORDER BY a.created_at DESC
                    LIMIT 10
                ");
                $stmt->execute();
                $announcements = $stmt->fetchAll();
                
                foreach ($announcements as &$announcement) {
                    if (!empty($announcement['image_path'])) {
                        $decoded = json_decode($announcement['image_path'], true);
                        if (is_array($decoded)) {
                            $announcement['images'] = $decoded;
                            $announcement['image_count'] = count($decoded);
                        } else {
                            $announcement['images'] = [$announcement['image_path']];
                            $announcement['image_count'] = 1;
                        }
                    } else {
                        $announcement['images'] = [];
                        $announcement['image_count'] = 0;
                    }
                }
            } catch(PDOException $e) {
                $error = "Error creating announcement: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all fields.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cultural Admin Dashboard - BISU Achievement Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', 'Segoe UI', sans-serif; background: #f5f5f5; }
        .admin-info { display: flex; align-items: center; gap: 1rem; }
        .admin-badge { background: #ffd700; color: #003366; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.85rem; font-weight: bold; }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .alert-success { background: #d4edda; color: #155724; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; border-left: 4px solid #dc3545; }
        .events-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 1rem; }
        .event-card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .event-card:hover { transform: translateY(-5px); }
        .event-header { background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white; padding: 1rem; }
        .event-body { padding: 1rem; }
        .event-stats { display: flex; justify-content: space-between; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #dee2e6; }
        .btn-sm { padding: 0.3rem 0.8rem; font-size: 0.85rem; border: none; border-radius: 5px; cursor: pointer; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #003366; }
        .btn-submit { background: #003366; color: white; padding: 0.5rem 1rem; border: none; border-radius: 5px; cursor: pointer; width: 100%; font-size: 1rem; }
        .btn-submit:hover { background: #002244; }
        .data-table { background: white; border-radius: 10px; overflow-x: auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .table-header { padding: 1rem; background: #f8f9fa; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .table-header h2 { font-size: 1.2rem; color: #003366; }
        .btn-add { background: #003366; color: white; padding: 0.5rem 1rem; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-add:hover { background: #002244; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.3s; border-top: 4px solid #003366; text-align: center; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card h3 { color: #666; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #003366; }
        .top-bar { background: white; padding: 1rem 2rem; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .page-title h1 { font-size: 1.5rem; color: #003366; margin: 0; }
        .user-info { display: flex; align-items: center; gap: 1rem; }
        .user-name { font-weight: 600; color: #333; }
        .logout-btn { background: #dc3545; color: white; padding: 0.5rem 1rem; border-radius: 5px; text-decoration: none; }
        .logout-btn:hover { background: #c82333; }
        .sidebar { width: 280px; background: #003366; color: white; padding: 2rem 1rem; position: fixed; height: 100vh; overflow-y: auto; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 100; }
        .sidebar-header { text-align: center; padding-bottom: 2rem; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 2rem; }
        .sidebar-header h2 { font-size: 1.3rem; margin-bottom: 0.5rem; color: white; }
        .user-role { background: rgba(255,255,255,0.2); padding: 0.3rem 0.8rem; border-radius: 20px; display: inline-block; font-size: 0.8rem; }
        .nav-menu { list-style: none; }
        .nav-item { margin-bottom: 0.5rem; }
        .nav-item a { color: white; text-decoration: none; padding: 0.8rem 1rem; display: flex; align-items: center; gap: 12px; border-radius: 8px; transition: all 0.3s; cursor: pointer; }
        .nav-item a:hover { background: rgba(255,255,255,0.1); padding-left: 1.5rem; }
        .nav-item.active a { background: rgba(255,255,255,0.2); border-left: 3px solid #ffd700; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: 280px; padding: 2rem; }
        .category-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: bold; background: #e8d5f0; color: #6c3483; }
        .ranking-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .ranking-1st { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #856404; }
        .ranking-2nd { background: linear-gradient(135deg, #c0c0c0, #e0e0e0); color: #495057; }
        .ranking-3rd { background: linear-gradient(135deg, #cd7f32, #e8a87c); color: #5c3a1a; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .filter-bar { background: #f8f9fa; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; border: 1px solid #dee2e6; }
        .filter-bar input, .filter-bar select { padding: 0.5rem; border: 1px solid #ddd; border-radius: 5px; font-size: 0.9rem; }
        .filter-group { display: flex; align-items: center; gap: 0.5rem; }
        .inline-form { display: inline; }
        .clear-filters { background: #6c757d; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; }
        .contest-group { background: white; border-radius: 10px; margin-bottom: 2rem; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .contest-group-header { background: linear-gradient(135deg, #6c3483 0%, #8e44ad 100%); color: white; padding: 1rem 1.5rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .contest-group-header h3 { margin: 0; font-size: 1.1rem; }
        .contest-group-header .badge { background: rgba(255,255,255,0.2); padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.85rem; }
        .contest-teams { padding: 1rem; display: none; }
        .contest-teams.show { display: block; }
        .team-card-small { background: #f8f9fa; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; border-left: 4px solid #6c3483; }
        .team-name { font-weight: bold; color: #6c3483; margin-bottom: 0.5rem; font-size: 1.05rem; }
        .member-list { margin-top: 0.5rem; padding-left: 1rem; color: #666; font-size: 0.9rem; }
        .member-list span { display: inline-block; background: #e9ecef; padding: 0.2rem 0.5rem; border-radius: 15px; margin: 0.2rem; font-size: 0.8rem; }
        .toggle-icon { transition: transform 0.3s; }
        .toggle-icon.rotated { transform: rotate(90deg); }
        .no-teams { text-align: center; padding: 2rem; color: #999; }
        
        /* Announcement Styles */
        .announcement-item { padding: 1rem; border-bottom: 1px solid #dee2e6; display: flex; gap: 1rem; flex-wrap: wrap; }
        .announcement-gallery { display: flex; gap: 10px; overflow-x: auto; flex-shrink: 0; max-width: 100%; }
        .announcement-thumb { width: 100px; height: 100px; object-fit: cover; border-radius: 8px; cursor: pointer; transition: transform 0.2s; }
        .announcement-thumb:hover { transform: scale(1.05); }
        .announcement-image-placeholder { flex-shrink: 0; width: 120px; height: 120px; background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; }
        .school-year-badge { display: inline-block; background: #e9ecef; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; margin-bottom: 0.5rem; color: #495057; }
        .activity-badge { display: inline-block; background: #e8d5f0; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; margin-left: 0.5rem; color: #6c3483; }
        .view-more { color: #9b59b6; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px; margin-top: 0.5rem; background: none; border: none; cursor: pointer; }
        .view-more:hover { color: #8e44ad; }
        .admin-tag { display: inline-block; background: #ffd700; color: #003366; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; margin-left: 0.5rem; font-weight: bold; }
        .info-box { background: #e8f4f8; border-left: 4px solid #17a2b8; padding: 1rem; margin-bottom: 1rem; border-radius: 5px; }
        .image-preview { position: relative; display: inline-block; width: 80px; height: 80px; border: 2px solid #ddd; border-radius: 8px; overflow: hidden; margin: 5px; }
        .image-preview img { width: 100%; height: 100%; object-fit: cover; }
        .remove-image { position: absolute; top: 2px; right: 2px; background: rgba(220,53,69,0.8); color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; }
        .remove-image:hover { background: #dc3545; }
        
        /* Modal Styles */
        .image-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 3000; justify-content: center; align-items: center; cursor: pointer; }
        .image-modal img { max-width: 90%; max-height: 90%; object-fit: contain; }
        .image-modal-close { position: absolute; top: 20px; right: 40px; color: white; font-size: 40px; cursor: pointer; }
        .announcement-full-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000; justify-content: center; align-items: center; overflow-y: auto; }
        .announcement-full-modal-content { background: white; border-radius: 12px; max-width: 800px; width: 90%; margin: 2rem auto; position: relative; max-height: 90vh; overflow-y: auto; }
        .modal-close-btn { position: sticky; top: 10px; right: 10px; float: right; background: #dc3545; color: white; border: none; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; margin: 10px; z-index: 10; }
        .modal-gallery { position: relative; width: 100%; height: 300px; overflow: hidden; background: #f0f0f0; }
        .modal-gallery-slider { display: flex; transition: transform 0.5s ease; height: 100%; }
        .modal-gallery-slide { min-width: 100%; height: 100%; }
        .modal-gallery-slide img { width: 100%; height: 100%; object-fit: cover; cursor: pointer; }
        .modal-gallery-arrow { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; z-index: 10; }
        .modal-gallery-arrow.prev { left: 10px; }
        .modal-gallery-arrow.next { right: 10px; }
        .modal-gallery-nav { position: absolute; bottom: 10px; left: 0; right: 0; display: flex; justify-content: center; gap: 8px; z-index: 10; }
        .modal-gallery-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.5); cursor: pointer; }
        .modal-gallery-dot.active { background: #ffd700; width: 20px; border-radius: 4px; }
        .logo-icon img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #ffd700; padding: 2px; background: white; }
        .mobile-menu-btn { display: none; }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; height: auto; transform: translateX(-100%); position: fixed; z-index: 1001; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-group { flex-direction: column; align-items: stretch; }
            .announcement-item { flex-direction: column; }
            .mobile-menu-btn { display: flex; position: fixed; bottom: 20px; right: 20px; background: #003366; color: white; width: 50px; height: 50px; border-radius: 50%; align-items: center; justify-content: center; cursor: pointer; z-index: 1002; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
            .modal-gallery { height: 200px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon"><img src="../includes/uploads/images/bisu_logo.png" alt="BISU Logo"></div>
                <h2>BISU Tracker</h2>
                <div class="user-role">Cultural Admin</div>
            </div>
            <ul class="nav-menu">
                <li class="nav-item active" data-tab="dashboard"><a href="#" onclick="showTab('dashboard')"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="nav-item" data-tab="analytics"><a href="admin_cultural_analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                <li class="nav-item" data-tab="events"><a href="#" onclick="showTab('events')"><i class="fas fa-calendar-alt"></i> Cultural Events</a></li>
                <li class="nav-item" data-tab="contests"><a href="#" onclick="showTab('contests')"><i class="fas fa-trophy"></i> Manage Contests</a></li>
                <li class="nav-item" data-tab="participants"><a href="#" onclick="showTab('participants')"><i class="fas fa-users"></i> Teams</a></li>
                <li class="nav-item" data-tab="participations"><a href="#" onclick="showTab('participations')"><i class="fas fa-list-alt"></i> Participants List</a></li>
                <li class="nav-item" data-tab="announcements"><a href="#" onclick="showTab('announcements')"><i class="fas fa-bullhorn"></i> Announcements</a></li>
                <li class="nav-item" data-tab="reports"><a href="admin_cultural_report.php"><i class="fas fa-print"></i> Reports</a></li>
                <li class="nav-item"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="page-title"><h1 id="pageTitle">Cultural Affairs Dashboard</h1></div>
                <div class="user-info">
                    <div class="admin-info">
                        <span class="admin-badge"><i class="fas fa-palette"></i> Cultural Admin</span>
                        <span class="user-name"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($admin_name); ?></span>
                    </div>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php if($message): ?>
                <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Dashboard Tab -->
            <div id="dashboard-tab" class="tab-content active">
                <div class="stats-grid">
                    <div class="stat-card"><h3><i class="fas fa-calendar-alt"></i> Cultural Events</h3><div class="stat-number"><?php echo $stats['total_events']; ?></div></div>
                    <div class="stat-card"><h3><i class="fas fa-trophy"></i> Total Contests</h3><div class="stat-number"><?php echo $stats['active_contests']; ?></div></div>
                    <div class="stat-card"><h3><i class="fas fa-users"></i> Participants</h3><div class="stat-number"><?php echo $stats['total_participants']; ?></div></div>
                </div>
                
                <div class="data-table">
                    <div class="table-header"><h2><i class="fas fa-calendar-alt"></i> Recent Cultural Events</h2><button class="btn-add" onclick="showTab('events')">+ Create Activity</button></div>
                    <div class="events-grid">
                        <?php $recentEvents = array_slice($cultural_events, 0, 5); ?>
                        <?php foreach($recentEvents as $event): ?>
                            <div class="event-card"><div class="event-header"><h3><?php echo htmlspecialchars($event['activity_name']); ?></h3><small><?php echo htmlspecialchars($event['school_year'] ?? 'N/A'); ?></small></div><div class="event-body"><p><strong>Level:</strong> <?php echo htmlspecialchars($event['competition_level'] ?? 'N/A'); ?></p><div class="event-stats"><span><i class="fas fa-trophy"></i> <?php echo $event['contest_count']; ?> Contests</span></div></div></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Manage Contests Tab -->
            <div id="contests-tab" class="tab-content">
                <div class="data-table">
                    <div class="table-header"><h2><i class="fas fa-trophy"></i> Cultural Contests</h2><button class="btn-add" onclick="openCreateContestModal()">+ Create Contest</button></div>
                    
                    <div id="createContestModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center;">
                        <div style="background: white; border-radius: 10px; max-width: 500px; width: 90%; padding: 2rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;"><h3 style="color: #003366;">Create New Contest</h3><button onclick="closeCreateContestModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button></div>
                            <form action="" method="POST">
                                <div class="form-group"><label>Contest Name *</label><input type="text" name="contest_name" required placeholder="e.g., Singing Competition"></div>
                                <div class="form-group"><label>Select Cultural Activity *</label><select name="activity_id" required><option value="">Select activity</option><?php foreach($activities as $activity): ?><option value="<?php echo $activity['id']; ?>"><?php echo htmlspecialchars($activity['activity_name'] . ' (' . ($activity['school_year'] ?? 'N/A') . ')'); ?></option><?php endforeach; ?></select></div>
                                <div class="form-group"><label>Category *</label><select name="category_id" required><option value="">Select category</option><?php foreach($categories as $category): ?><option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['category_name']); ?></option><?php endforeach; ?></select></div>
                                <button type="submit" name="create_contest" class="btn-submit">Create Contest</button>
                            </form>
                        </div>
                    </div>
                    
                    <?php if(count($cultural_contests) > 0): ?>
                        <table><thead><tr><th>Contest Name</th><th>Activity</th><th>School Year</th><th>Category</th><th>Teams</th><th>Participants</th><th>Status</th></tr></thead><tbody>
                        <?php foreach($cultural_contests as $contest): ?><tr><td><strong><?php echo htmlspecialchars($contest['name']); ?></strong></td><td><?php echo htmlspecialchars($contest['activity_name']); ?></td><td><?php echo htmlspecialchars($contest['school_year'] ?? 'N/A'); ?></td><td><span class="category-badge"><?php echo htmlspecialchars($contest['category_name']); ?></span></td><td><?php echo $contest['team_count']; ?></td><td><?php echo $contest['participant_count']; ?></td><td><span class="status-active">Active</span></td></tr><?php endforeach; ?>
                        </tbody></table>
                    <?php else: ?>
                        <div style="text-align:center;padding:3rem;color:#999;">No cultural contests created yet.</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Cultural Events Tab -->
            <div id="events-tab" class="tab-content">
                <div class="data-table">
                    <div class="table-header"><h2><i class="fas fa-calendar-alt"></i> Cultural Events</h2><button class="btn-add" onclick="openCreateActivityModal()">+ Create Activity</button></div>
                    
                    <div id="createActivityModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center;">
                        <div style="background: white; border-radius: 10px; max-width: 500px; width: 90%; padding: 2rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;"><h3 style="color: #003366;">Create Cultural Activity</h3><button onclick="closeCreateActivityModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button></div>
                            <form action="" method="POST">
                                <div class="form-group"><label>Activity Name *</label><input type="text" name="activity_name" required placeholder="e.g., BISU Cultural Night 2024"></div>
                                <div class="form-group"><label>School Year *</label><select name="school_year" required><option value="">Select School Year</option><?php $current_year=date('Y'); for($i=-3;$i<=2;$i++){$start=$current_year+$i;$end=$start+1;echo "<option value='{$start}-{$end}'>{$start}-{$end}</option>";}?></select></div>
                                <div class="form-group"><label>Competition Level *</label><select name="competition_level" required><option value="Intramurals">Intramurals</option><option value="Provincial">Provincial</option><option value="Regional">Regional</option><option value="National">National</option><option value="International">International</option></select></div>
                                <button type="submit" name="create_activity" class="btn-submit">Create Activity</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="events-grid">
                        <?php foreach($cultural_events as $event): ?>
                            <div class="event-card"><div class="event-header"><h3><?php echo htmlspecialchars($event['activity_name']); ?></h3><small><?php echo htmlspecialchars($event['school_year'] ?? 'N/A'); ?></small></div><div class="event-body"><p><strong>Level:</strong> <?php echo htmlspecialchars($event['competition_level'] ?? 'N/A'); ?></p><div class="event-stats"><span><i class="fas fa-trophy"></i> <?php echo $event['contest_count']; ?> Contests</span></div></div></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Teams Tab -->
            <div id="participants-tab" class="tab-content">
                <div class="data-table">
                    <div class="table-header"><h2><i class="fas fa-users"></i> Teams & Participants by Contest</h2></div>
                    <?php if(count($teamsByContest) > 0): ?>
                        <?php foreach($teamsByContest as $contest): ?>
                            <div class="contest-group"><div class="contest-group-header" onclick="toggleContest(this)"><h3><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($contest['contest_name']); ?> <small>(<?php echo htmlspecialchars($contest['activity_name']); ?> - <?php echo $contest['school_year'] ?? 'N/A'; ?>)</small></h3><div><span class="badge"><i class="fas fa-users"></i> <?php echo count($contest['teams']); ?> Teams</span><i class="fas fa-chevron-right toggle-icon"></i></div></div><div class="contest-teams"><?php foreach($contest['teams'] as $team): ?><div class="team-card-small"><div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?> <span style="float:right;"><?php echo $team['member_count']; ?> Members</span></div><div class="coach-name">Coach: <?php echo htmlspecialchars(($team['coach_first']??'').' '.($team['coach_last']??'')); ?></div><div class="member-list"><strong>Members:</strong><br><?php if($team['members']){$members=explode(', ',$team['members']); foreach($members as $member){echo '<span>'.htmlspecialchars($member).'</span>';}}else{echo 'No members assigned yet.';} ?></div></div><?php endforeach; ?></div></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-teams">No teams have been created yet.</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Participants List Tab -->
            <div id="participations-tab" class="tab-content">
                <div class="data-table">
                    <div class="table-header"><h2><i class="fas fa-list-alt"></i> All Participants</h2></div>
                    
                    <div class="filter-bar">
                        <div class="filter-group"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search..." onkeyup="applyFilters()"></div>
                        <div class="filter-group"><i class="fas fa-calendar-alt"></i><select id="schoolYearFilter" onchange="applyFilters()"><option value="">All School Years</option><?php foreach($allSchoolYears as $sy){echo '<option value="'.htmlspecialchars($sy['school_year']).'">'.htmlspecialchars($sy['school_year']).'</option>';}?></select></div>
                        <div class="filter-group"><i class="fas fa-trophy"></i><select id="contestFilter" onchange="applyFilters()"><option value="">All Contests</option><?php foreach($allContests as $contest){echo '<option value="'.htmlspecialchars($contest['name']).'">'.htmlspecialchars($contest['name']).'</option>';}?></select></div>
                        <div class="filter-group"><i class="fas fa-users"></i><select id="teamFilter" onchange="applyFilters()"><option value="">All Teams</option><?php foreach($allTeams as $team){echo '<option value="'.htmlspecialchars($team['team_name']).'">'.htmlspecialchars($team['team_name']).'</option>';}?></select></div>
                        <div class="filter-group"><i class="fas fa-medal"></i><select id="rankingFilter" onchange="applyFilters()"><option value="">All Rankings</option><?php foreach($allRankings as $ranking){echo '<option value="'.htmlspecialchars($ranking).'">'.htmlspecialchars($ranking).'</option>';}?></select></div>
                        <div class="filter-group"><i class="fas fa-circle"></i><select id="statusFilter" onchange="applyFilters()"><option value="">All Status</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                        <button class="clear-filters" onclick="clearFilters()">Clear All</button>
                    </div>
                    
                    <?php if(count($participations) > 0): ?>
                        <table id="participationsTable"><thead><tr><th>Student Name</th><th>Course/Year</th><th>College</th><th>Team</th><th>Coach</th><th>Contest</th><th>School Year</th><th>Ranking</th><th>Status</th><th>Actions</th></tr></thead><tbody id="participationsTableBody">
                        <?php foreach($participations as $part): ?><?php $status = getMembershipStatus($part); ?>
                            <tr data-student="<?php echo strtolower($part['first_name'].' '.$part['last_name']); ?>" data-team="<?php echo strtolower($part['team_name']); ?>" data-contest="<?php echo strtolower($part['contest_name']); ?>" data-schoolyear="<?php echo strtolower($part['school_year']??''); ?>" data-ranking="<?php echo strtolower($part['ranking']??''); ?>" data-status="<?php echo strtolower($status); ?>">
                                <td><strong><?php echo htmlspecialchars($part['first_name'].' '.$part['last_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($part['course']); ?> (<?php echo $part['yr_level']; ?>th)</td>
                                <td><strong><?php echo htmlspecialchars($part['student_college']); ?></strong></td>
                                <td><?php echo htmlspecialchars($part['team_name']); ?></td>
                                <td><?php if(!empty($part['coach_first'])): ?><?php echo htmlspecialchars($part['coach_first'].' '.$part['coach_last']); ?><?php else: ?>N/A<?php endif; ?></td>
                                <td><?php echo htmlspecialchars($part['contest_name']); ?></td>
                                <td><?php echo htmlspecialchars($part['school_year'] ?? 'N/A'); ?></td>
                                <td><?php if(!empty($part['ranking'])): ?><span class="ranking-badge <?php echo strpos(strtolower($part['ranking']),'1st')!==false?'ranking-1st':(strpos(strtolower($part['ranking']),'2nd')!==false?'ranking-2nd':(strpos(strtolower($part['ranking']),'3rd')!==false?'ranking-3rd':'')); ?>"><?php echo htmlspecialchars($part['ranking']); ?></span><?php else: ?>Not set<?php endif; ?></td>
                                <td><span class="status-active"><?php echo ucfirst($status); ?></span></td>
                                <td><button class="btn-sm" style="background:#ffc107;" onclick="openRankingModal(<?php echo $part['participation_id']; ?>, '<?php echo addslashes($part['first_name'].' '.$part['last_name']); ?>', '<?php echo addslashes($part['team_name']); ?>', '<?php echo addslashes($part['ranking']??''); ?>')">Set Rank</button>
                                <form method="POST" class="inline-form"><input type="hidden" name="participation_id" value="<?php echo $part['participation_id']; ?>"><select name="status" onchange="this.form.submit()"><option value="active" <?php echo $status=='active'?'selected':''; ?>>Active</option><option value="inactive" <?php echo $status=='inactive'?'selected':''; ?>>Inactive</option></select><input type="hidden" name="update_status" value="1"></form>
                            </td>
                        </tr>
                        <?php endforeach; ?></tbody></table>
                    <?php else: ?>
                        <div class="no-teams">No participants found for cultural activities.</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Announcements Tab -->
            <div id="announcements-tab" class="tab-content">
                <div class="data-table">
                    <div class="table-header">
                        <h2><i class="fas fa-bullhorn"></i> Announcements <span class="admin-tag">Cultural Admin Only</span></h2>
                        <button class="btn-add" onclick="openCreateAnnouncementModal()"><i class="fas fa-plus"></i> Post Announcement</button>
                    </div>
                    
                    <!-- Create Announcement Modal -->
                    <div id="createAnnouncementModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center;">
                        <div style="background: white; border-radius: 10px; max-width: 600px; width: 90%; padding: 2rem; max-height: 90vh; overflow-y: auto;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <h3 style="color: #9b59b6;"><i class="fas fa-plus-circle"></i> Post New Announcement</h3>
                                <button onclick="closeCreateAnnouncementModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                            </div>
                            <div class="info-box">
                                <i class="fas fa-info-circle"></i> This announcement will only be visible to Cultural Admin dashboard.
                            </div>
                            <form action="" method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label><i class="fas fa-calendar-alt"></i> Select Activity/Event (Optional)</label>
                                    <select name="activity_id">
                                        <option value="">-- No specific activity (General Announcement) --</option>
                                        <?php foreach($activities as $activity): ?>
                                            <option value="<?php echo $activity['id']; ?>">
                                                <?php echo htmlspecialchars($activity['activity_name'] . ' (' . ($activity['school_year'] ?? 'N/A') . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small style="display: block; margin-top: 5px; color: #666;">
                                        <i class="fas fa-info-circle"></i> Select an activity to link this announcement to a specific school year.
                                    </small>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-heading"></i> Title *</label>
                                    <input type="text" name="title" required placeholder="Announcement title">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-file-alt"></i> Content *</label>
                                    <textarea name="content" rows="5" required placeholder="Write your announcement here..."></textarea>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-images"></i> Upload Images (Select multiple)</label>
                                    <input type="file" name="announcement_images[]" id="announcementImages" accept="image/jpeg,image/png,image/gif,image/webp" multiple onchange="previewImages()">
                                    <small style="display: block; margin-top: 5px; color: #666;">
                                        <i class="fas fa-info-circle"></i> You can select multiple images. Supported formats: JPG, PNG, GIF, WEBP
                                    </small>
                                </div>
                                <div id="imagePreviewContainer" style="display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0;"></div>
                                <button type="submit" name="create_announcement" class="btn-submit" style="background: #9b59b6; margin-top: 10px;">
                                    <i class="fas fa-paper-plane"></i> Publish Announcement
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- School Year Filter -->
                    <div class="filter-bar" style="margin-bottom: 1rem;">
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt"></i> Filter by School Year:</label>
                            <select id="announcementSchoolYearFilter" onchange="filterAnnouncementsBySchoolYear()">
                                <option value="all">All School Years</option>
                                <?php foreach($availableSchoolYears as $sy): ?>
                                    <option value="<?php echo htmlspecialchars($sy['school_year']); ?>"><?php echo htmlspecialchars($sy['school_year']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <h3 style="margin: 1rem 0 0.5rem 1rem;">Recent Announcements</h3>
                    
                    <?php if(count($announcements) > 0): ?>
                        <?php foreach($announcements as $announcement): ?>
                            <?php 
                            $images = $announcement['images'] ?? [];
                            $imageCount = $announcement['image_count'] ?? 0;
                            $imagesJson = htmlspecialchars(json_encode($images), ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="announcement-item" data-school-year="<?php echo htmlspecialchars($announcement['school_year'] ?? ''); ?>">
                                <?php if($imageCount > 0): ?>
                                    <div class="announcement-gallery">
                                        <?php foreach($images as $img_path): ?>
                                            <img src="../<?php echo $img_path; ?>" class="announcement-thumb" onclick="openImageModal('../<?php echo $img_path; ?>')">
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="announcement-image-placeholder"><i class="fas fa-newspaper" style="font-size: 3rem;"></i></div>
                                <?php endif; ?>
                                <div style="flex: 1;">
                                    <h3 style="color: #9b59b6;"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                    <?php if(!empty($announcement['school_year'])): ?>
                                        <span class="school-year-badge"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($announcement['school_year']); ?></span>
                                    <?php endif; ?>
                                    <?php if(!empty($announcement['activity_name'])): ?>
                                        <span class="activity-badge"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($announcement['activity_name']); ?></span>
                                    <?php endif; ?>
                                    <p><?php echo nl2br(htmlspecialchars(substr($announcement['content'], 0, 200) . (strlen($announcement['content']) > 200 ? '...' : ''))); ?></p>
                                    <div class="announcement-meta">
                                        <small><i class="fas fa-user"></i> <?php echo htmlspecialchars($announcement['author_email']); ?> | <i class="fas fa-clock"></i> <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></small>
                                        <button class="view-more" onclick='viewFullAnnouncement(<?php echo $announcement['id']; ?>, <?php echo json_encode(htmlspecialchars($announcement['title'])); ?>, <?php echo json_encode(htmlspecialchars($announcement['content'])); ?>, "<?php echo htmlspecialchars($announcement['created_at']); ?>", "<?php echo htmlspecialchars($announcement['author_email']); ?>", <?php echo $imagesJson; ?>, "cultural", "<?php echo htmlspecialchars($announcement['school_year'] ?? ''); ?>", "<?php echo htmlspecialchars($announcement['activity_name'] ?? ''); ?>")'>Read More <i class="fas fa-arrow-right"></i></button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem; color: #999;">
                            <i class="fas fa-bullhorn" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <p>No announcements yet.</p>
                            <button class="btn-add" onclick="openCreateAnnouncementModal()" style="margin-top: 1rem;">Post Your First Announcement</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modals -->
    <div id="announcementFullModal" class="announcement-full-modal">
        <div class="announcement-full-modal-content">
            <button class="modal-close-btn" onclick="closeFullAnnouncementModal()"><i class="fas fa-times"></i></button>
            <div style="clear: both;"></div>
            <div id="fullModalContent" style="padding: 2rem;"></div>
        </div>
    </div>
    
    <div id="imageModal" class="image-modal" onclick="closeImageModal()">
        <span class="image-modal-close">&times;</span>
        <img id="modalImage" src="">
    </div>
    
    <div id="rankingModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 10px; padding: 2rem; max-width: 400px; width: 90%;">
            <h3 style="color: #003366;">Set Ranking / Award</h3>
            <p id="modalStudentInfo" style="margin-bottom: 1rem; color: #666;"></p>
            <form method="POST" id="rankingForm">
                <input type="hidden" name="participation_id" id="modalParticipationId">
                <div class="form-group">
                    <label>Ranking / Award</label>
                    <select name="ranking" required style="width: 100%; padding: 0.5rem;">
                        <option value="">Select ranking</option>
                        <option value="1st Place">1st Place</option>
                        <option value="2nd Place">2nd Place</option>
                        <option value="3rd Place">3rd Place</option>
                        <option value="Champion">Champion</option>
                        <option value="1st Runner Up">1st Runner Up</option>
                        <option value="2nd Runner Up">2nd Runner Up</option>
                        <option value="Best Performer">Best Performer</option>
                        <option value="Best in Talent">Best in Talent</option>
                        <option value="People's Choice">People's Choice</option>
                        <option value="Special Award">Special Award</option>
                        <option value="Finalist">Finalist</option>
                        <option value="Participant">Participant</option>
                    </select>
                </div>
                <button type="submit" name="update_ranking" class="btn-submit">Save Ranking</button>
                <button type="button" onclick="closeRankingModal()" style="margin-top: 0.5rem; width: 100%; padding: 0.5rem; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">Cancel</button>
            </form>
        </div>
    </div>
    
    <div class="mobile-menu-btn" onclick="document.querySelector('.sidebar').classList.toggle('show')"><i class="fas fa-bars"></i></div>
    
    <script>
        // Image preview function
        function previewImages() {
            const input = document.getElementById('announcementImages');
            const preview = document.getElementById('imagePreviewContainer');
            preview.innerHTML = '';
            
            if (input.files) {
                for (let i = 0; i < input.files.length; i++) {
                    const file = input.files[i];
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'image-preview';
                            div.innerHTML = '<img src="' + e.target.result + '"><button type="button" class="remove-image" onclick="this.parentElement.remove()">&times;</button>';
                            preview.appendChild(div);
                        }
                        reader.readAsDataURL(file);
                    }
                }
            }
        }
        
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            document.querySelector('.nav-item[data-tab="' + tabName + '"]').classList.add('active');
            var titles = { 'dashboard': 'Cultural Affairs Dashboard', 'events': 'Cultural Events', 'contests': 'Manage Contests', 'participants': 'Teams', 'participations': 'Participants List', 'announcements': 'Announcements' };
            document.getElementById('pageTitle').innerText = titles[tabName] || 'Cultural Dashboard';
            if(window.innerWidth <= 768) document.querySelector('.sidebar').classList.remove('show');
        }
        
        function toggleContest(element) {
            var teamsDiv = element.nextElementSibling;
            teamsDiv.classList.toggle('show');
            var icon = element.querySelector('.toggle-icon');
            if(icon) icon.classList.toggle('rotated');
        }
        
        function openCreateContestModal() { document.getElementById('createContestModal').style.display = 'flex'; }
        function closeCreateContestModal() { document.getElementById('createContestModal').style.display = 'none'; }
        function openCreateActivityModal() { document.getElementById('createActivityModal').style.display = 'flex'; }
        function closeCreateActivityModal() { document.getElementById('createActivityModal').style.display = 'none'; }
        function openCreateAnnouncementModal() { 
            document.getElementById('createAnnouncementModal').style.display = 'flex';
            document.getElementById('imagePreviewContainer').innerHTML = '';
            var fileInput = document.getElementById('announcementImages');
            if (fileInput) fileInput.value = '';
        }
        function closeCreateAnnouncementModal() { document.getElementById('createAnnouncementModal').style.display = 'none'; }
        
        function filterAnnouncementsBySchoolYear() {
            var schoolYear = document.getElementById('announcementSchoolYearFilter').value;
            var items = document.querySelectorAll('.announcement-item');
            for(var i = 0; i < items.length; i++) {
                items[i].style.display = (schoolYear === 'all' || items[i].getAttribute('data-school-year') === schoolYear) ? 'flex' : 'none';
            }
        }
        
        function applyFilters() {
            var search = document.getElementById('searchInput').value.toLowerCase();
            var schoolYear = document.getElementById('schoolYearFilter').value.toLowerCase();
            var contest = document.getElementById('contestFilter').value.toLowerCase();
            var team = document.getElementById('teamFilter').value.toLowerCase();
            var ranking = document.getElementById('rankingFilter').value.toLowerCase();
            var status = document.getElementById('statusFilter').value.toLowerCase();
            var rows = document.querySelectorAll('#participationsTableBody tr');
            var visible = 0;
            for(var i = 0; i < rows.length; i++) {
                var row = rows[i];
                var show = true;
                if(search && !row.getAttribute('data-student').includes(search) && !row.getAttribute('data-team').includes(search) && !row.getAttribute('data-contest').includes(search)) show = false;
                if(schoolYear && !row.getAttribute('data-schoolyear').includes(schoolYear)) show = false;
                if(contest && !row.getAttribute('data-contest').includes(contest)) show = false;
                if(team && !row.getAttribute('data-team').includes(team)) show = false;
                if(ranking && !row.getAttribute('data-ranking').includes(ranking)) show = false;
                if(status && !row.getAttribute('data-status').includes(status)) show = false;
                row.style.display = show ? '' : 'none';
                if(show) visible++;
            }
            var noMsg = document.getElementById('noResultsMessage');
            if(visible === 0 && rows.length > 0) {
                if(!noMsg) {
                    noMsg = document.createElement('tr');
                    noMsg.id = 'noResultsMessage';
                    noMsg.innerHTML = '<td colspan="10" style="text-align:center;padding:2rem;">No records match your filters.将</td>';
                    document.querySelector('#participationsTable tbody').appendChild(noMsg);
                }
                noMsg.style.display = '';
            } else if(noMsg) {
                noMsg.style.display = 'none';
            }
        }
        
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('schoolYearFilter').value = '';
            document.getElementById('contestFilter').value = '';
            document.getElementById('teamFilter').value = '';
            document.getElementById('rankingFilter').value = '';
            document.getElementById('statusFilter').value = '';
            applyFilters();
        }
        
        // Modal Gallery Variables
        var modalTotalSlides = 0;
        var modalCurrentIndex = 0;
        var modalSlider = null;
        var modalAutoPlayInterval = null;
        
        function startModalAutoPlay() {
            if (modalAutoPlayInterval) clearInterval(modalAutoPlayInterval);
            if (modalTotalSlides > 1) {
                modalAutoPlayInterval = setInterval(function() {
                    var newIndex = (modalCurrentIndex + 1) % modalTotalSlides;
                    goToModalSlide(newIndex);
                }, 5000);
            }
        }
        
        function stopModalAutoPlay() {
            if (modalAutoPlayInterval) {
                clearInterval(modalAutoPlayInterval);
                modalAutoPlayInterval = null;
            }
        }
        
        function initModalGallery(totalSlides) {
            modalTotalSlides = totalSlides;
            modalCurrentIndex = 0;
            modalSlider = document.querySelector('#announcementFullModal .modal-gallery-slider');
            if (modalSlider) updateModalGallery();
            var modalGallery = document.querySelector('#announcementFullModal .modal-gallery');
            if (modalGallery && totalSlides > 1) {
                modalGallery.addEventListener('mouseenter', function() { stopModalAutoPlay(); });
                modalGallery.addEventListener('mouseleave', function() { startModalAutoPlay(); });
            }
        }
        
        function updateModalGallery() {
            if (modalSlider) modalSlider.style.transform = 'translateX(-' + (modalCurrentIndex * 100) + '%)';
            var dots = document.querySelectorAll('#announcementFullModal .modal-gallery-dot');
            for(var i = 0; i < dots.length; i++) {
                if (i === modalCurrentIndex) dots[i].classList.add('active');
                else dots[i].classList.remove('active');
            }
        }
        
        function prevModalSlide() {
            if (modalCurrentIndex > 0) {
                modalCurrentIndex--;
                updateModalGallery();
                stopModalAutoPlay();
                startModalAutoPlay();
            }
        }
        
        function nextModalSlide() {
            if (modalCurrentIndex < modalTotalSlides - 1) {
                modalCurrentIndex++;
                updateModalGallery();
                stopModalAutoPlay();
                startModalAutoPlay();
            }
        }
        
        function goToModalSlide(index) {
            modalCurrentIndex = index;
            updateModalGallery();
            stopModalAutoPlay();
            startModalAutoPlay();
        }
        
        function viewFullAnnouncement(id, title, content, date, author, images, adminType, schoolYear, activityName) {
            var modal = document.getElementById('announcementFullModal');
            var modalContent = document.getElementById('fullModalContent');
            
            var galleryHtml = '';
            if(images && images.length > 0) {
                var slidesHtml = '';
                for(var i = 0; i < images.length; i++) {
                    slidesHtml += '<div class="modal-gallery-slide"><img src="../' + images[i] + '" onclick="openImageModal(\'../' + images[i] + '\')"></div>';
                }
                var dotsHtml = '';
                for(var i = 0; i < images.length; i++) {
                    dotsHtml += '<div class="modal-gallery-dot' + (i === 0 ? ' active' : '') + '" onclick="goToModalSlide(' + i + ')"></div>';
                }
                galleryHtml = '<div class="modal-gallery"><div class="modal-gallery-slider">' + slidesHtml + '</div><button class="modal-gallery-arrow prev" onclick="prevModalSlide()">❮</button><button class="modal-gallery-arrow next" onclick="nextModalSlide()">❯</button><div class="modal-gallery-nav">' + dotsHtml + '</div></div>';
            }
            
            var schoolYearHtml = '';
            if(schoolYear && schoolYear !== 'null' && schoolYear !== '') {
                schoolYearHtml = '<div class="school-year-badge" style="display: inline-block; margin-right: 10px;"><i class="fas fa-calendar-alt"></i> ' + schoolYear + '</div>';
            }
            
            var activityHtml = '';
            if(activityName && activityName !== '') {
                activityHtml = '<div class="activity-badge" style="display: inline-block;"><i class="fas fa-tag"></i> ' + activityName + '</div>';
            }
            
            modalContent.innerHTML = galleryHtml + '<div style="margin-bottom: 1rem;">' + schoolYearHtml + activityHtml + '<span class="admin-badge" style="background:#9b59b6; color:white; padding:2px 8px; border-radius:15px; font-size:12px;"><i class="fas fa-palette"></i> Cultural Admin</span></div><h2 style="color:#9b59b6;">' + title + '</h2><div style="color:#999;margin-bottom:1rem;"><i class="fas fa-user"></i> ' + author + ' | <i class="fas fa-calendar-alt"></i> ' + new Date(date).toLocaleDateString() + '</div><div style="line-height:1.8; white-space: pre-wrap;">' + content.replace(/\n/g, '<br>') + '</div>';
            
            modal.style.display = 'flex';
            
            if(images && images.length > 1) {
                setTimeout(function() { initModalGallery(images.length); startModalAutoPlay(); }, 100);
            } else if(images && images.length === 1) {
                setTimeout(function() { initModalGallery(1); }, 100);
            }
        }
        
        function closeFullAnnouncementModal() {
            document.getElementById('announcementFullModal').style.display = 'none';
            if (modalAutoPlayInterval) {
                clearInterval(modalAutoPlayInterval);
                modalAutoPlayInterval = null;
            }
            modalTotalSlides = 0;
            modalCurrentIndex = 0;
            modalSlider = null;
        }
        
        function openImageModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').style.display = 'flex';
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }
        
        function openRankingModal(id, name, team, currentRanking) {
            document.getElementById('modalParticipationId').value = id;
            document.getElementById('modalStudentInfo').innerHTML = '<strong>Participant:</strong> ' + name + '<br><strong>Team:</strong> ' + team;
            var select = document.querySelector('#rankingForm select');
            if(currentRanking) {
                for(var i = 0; i < select.options.length; i++) {
                    if(select.options[i].value === currentRanking) {
                        select.selectedIndex = i;
                        break;
                    }
                }
            }
            document.getElementById('rankingModal').style.display = 'flex';
        }
        
        function closeRankingModal() {
            document.getElementById('rankingModal').style.display = 'none';
        }
        
        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            var firstContest = document.querySelector('.contest-teams');
            if(firstContest) {
                firstContest.classList.add('show');
                var icon = document.querySelector('.contest-group-header .toggle-icon');
                if(icon) icon.classList.add('rotated');
            }
            document.getElementById('rankingModal').addEventListener('click', function(e) { if(e.target === this) closeRankingModal(); });
            document.getElementById('announcementFullModal').addEventListener('click', function(e) { if(e.target === this) closeFullAnnouncementModal(); });
            
            var modals = ['createContestModal', 'createActivityModal', 'createAnnouncementModal'];
            for(var i = 0; i < modals.length; i++) {
                var modal = document.getElementById(modals[i]);
                if(modal) {
                    modal.addEventListener('click', function(e) { if(e.target === this) this.style.display = 'none'; });
                }
            }
            applyFilters();
        });
        
        var urlParams = new URLSearchParams(window.location.search);
        var section = urlParams.get('section');
        if(section && ['dashboard', 'events', 'contests', 'participants', 'participations', 'announcements'].includes(section)) showTab(section);
    </script>
</body>
</html>