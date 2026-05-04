<?php
// admin_cultural_report.php - Report for Cultural Admin only with filters
require_once '../includes/session.php';
redirectIfNotLoggedIn();

if ($_SESSION['usertype'] !== 'admin' || $_SESSION['admin_type'] !== 'cultural') {
    header("Location: " . getDashboardPath($_SESSION['usertype'], $_SESSION['admin_type'] ?? null));
    exit();
}

require_once '../config/database.php';

// Get admin info
$admin_name = $_SESSION['email'] ?? 'Cultural Admin';
$current_admin_type = 'cultural';

// Cultural Admin specific - only Culture and Arts
$allowed_category_types = ['Culture and Arts'];
$report_title = 'Culture and Arts Report';
$icon_class = 'fas fa-palette';
$header_bg = '#8e44ad';

function getCurrentSchoolYear() {
    $current_year = date('Y');
    $current_month = date('n');
    if ($current_month >= 6) {
        return $current_year . '-' . ($current_year + 1);
    } else {
        return ($current_year - 1) . '-' . $current_year;
    }
}

function getRankingClass($ranking) {
    if (empty($ranking)) return '';
    $rank_lower = strtolower($ranking);
    if (strpos($rank_lower, '1st') !== false || strpos($rank_lower, 'champion') !== false) return 'ranking-1st';
    if (strpos($rank_lower, '2nd') !== false) return 'ranking-2nd';
    if (strpos($rank_lower, '3rd') !== false) return 'ranking-3rd';
    return 'ranking-default';
}

// =====================================================
// FILTER PARAMETERS
// =====================================================
$selected_school_year = isset($_GET['school_year']) ? $_GET['school_year'] : 'all';
$selected_category = isset($_GET['category']) ? $_GET['category'] : 'all';
$selected_competition_level = isset($_GET['competition_level']) ? $_GET['competition_level'] : 'all';

// =====================================================
// GET FILTER OPTIONS FROM DATABASE
// =====================================================

// Get available school years
$availableSchoolYears = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT a.school_year 
        FROM activity a 
        WHERE a.activity_type IN ('cultural', 'arts') AND a.school_year IS NOT NULL
        ORDER BY a.school_year DESC
    ");
    $stmt->execute();
    $availableSchoolYears = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($availableSchoolYears)) {
        $availableSchoolYears = [['school_year' => getCurrentSchoolYear()]];
    }
} catch(PDOException $e) {
    $availableSchoolYears = [['school_year' => getCurrentSchoolYear()]];
}

// Get available categories (cultural events) for filter
$availableCategories = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT cat.id, cat.category_name, cat.category_type
        FROM category cat
        WHERE cat.category_type = 'Culture and Arts'
        ORDER BY cat.category_name
    ");
    $stmt->execute();
    $availableCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $availableCategories = [];
}

// Get available competition levels
$competitionLevels = ['Intramurals', 'Provincial', 'Regional', 'National', 'International'];

// =====================================================
// BUILD WHERE CLAUSE FOR FILTERS
// =====================================================
$where_conditions = ["p.membership_status = 'active'", "cat.category_type = 'Culture and Arts'"];
$params = [];

if ($selected_school_year !== 'all') {
    $where_conditions[] = "a.school_year = ?";
    $params[] = $selected_school_year;
}

if ($selected_category !== 'all') {
    $where_conditions[] = "cat.id = ?";
    $params[] = $selected_category;
}

if ($selected_competition_level !== 'all') {
    $where_conditions[] = "a.competition_level = ?";
    $params[] = $selected_competition_level;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// =====================================================
// FETCH COACHES WITH FILTERS
// =====================================================
$coaches = [];
try {
    $coach_sql = "
        SELECT DISTINCT
            c.id as coach_id,
            u.id as user_id,
            u.first_name,
            u.last_name,
            u.email,
            COUNT(DISTINCT t.id) as total_teams,
            COUNT(DISTINCT p.student_id) as total_members,
            STRING_AGG(DISTINCT cat.category_type, ', ') as category_types
        FROM coach c
        JOIN \"User\" u ON c.user_id = u.id
        LEFT JOIN team t ON c.id = t.coach_id
        LEFT JOIN contest con ON t.contest_id = con.id
        LEFT JOIN category cat ON con.category_id = cat.id
        LEFT JOIN participation p ON t.id = p.team_id AND p.membership_status = 'active'
        LEFT JOIN activity a ON con.activity_id = a.id
        WHERE cat.category_type = 'Culture and Arts'
    ";
    
    if ($selected_school_year !== 'all') {
        $coach_sql .= " AND a.school_year = '" . addslashes($selected_school_year) . "'";
    }
    if ($selected_category !== 'all') {
        $coach_sql .= " AND cat.id = " . intval($selected_category);
    }
    if ($selected_competition_level !== 'all') {
        $coach_sql .= " AND a.competition_level = '" . addslashes($selected_competition_level) . "'";
    }
    
    $coach_sql .= " GROUP BY c.id, u.id, u.first_name, u.last_name, u.email
                    ORDER BY u.last_name, u.first_name";
    
    $stmt = $pdo->prepare($coach_sql);
    $stmt->execute();
    $coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $coaches = [];
}

// =====================================================
// FETCH TEAMS WITH FILTERS
// =====================================================
$teams = [];
try {
    $teams_sql = "
        SELECT 
            t.id as team_id,
            t.team_name,
            t.college_filter,
            u.first_name as coach_first,
            u.last_name as coach_last,
            c.name as contest_name,
            a.activity_name,
            a.activity_type,
            a.competition_level,
            a.school_year,
            cat.category_name,
            cat.category_type,
            COUNT(DISTINCT p.student_id) as member_count,
            STRING_AGG(DISTINCT CONCAT(su.first_name, ' ', su.last_name, ' (', s.course, ')'), '; ') as members_list
        FROM team t
        JOIN coach co ON t.coach_id = co.id
        JOIN \"User\" u ON co.user_id = u.id
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        JOIN category cat ON c.category_id = cat.id
        LEFT JOIN participation p ON t.id = p.team_id AND p.membership_status = 'active'
        LEFT JOIN student s ON p.student_id = s.id
        LEFT JOIN \"User\" su ON s.user_id = su.id
        WHERE cat.category_type = 'Culture and Arts'
    ";
    
    if ($selected_school_year !== 'all') {
        $teams_sql .= " AND a.school_year = '" . addslashes($selected_school_year) . "'";
    }
    if ($selected_category !== 'all') {
        $teams_sql .= " AND cat.id = " . intval($selected_category);
    }
    if ($selected_competition_level !== 'all') {
        $teams_sql .= " AND a.competition_level = '" . addslashes($selected_competition_level) . "'";
    }
    
    $teams_sql .= " GROUP BY t.id, t.team_name, t.college_filter, u.first_name, u.last_name, 
                     c.name, a.activity_name, a.activity_type, a.competition_level, 
                     a.school_year, cat.category_name, cat.category_type
                    ORDER BY a.school_year DESC, cat.category_type, t.team_name";
    
    $stmt = $pdo->prepare($teams_sql);
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $teams = [];
}

// Group teams by activity/school_year
$teams_by_activity = [];
foreach ($teams as $team) {
    $activity_key = $team['activity_name'] . '_' . ($team['school_year'] ?? 'N/A');
    if (!isset($teams_by_activity[$activity_key])) {
        $teams_by_activity[$activity_key] = [
            'activity_name' => $team['activity_name'],
            'school_year' => $team['school_year'],
            'competition_level' => $team['competition_level'],
            'teams' => []
        ];
    }
    $teams_by_activity[$activity_key]['teams'][] = $team;
}

// =====================================================
// FETCH PARTICIPANTS WITH FILTERS
// =====================================================
$participants = [];
try {
    $participants_sql = "
        SELECT 
            s.id as student_id,
            su.first_name,
            su.last_name,
            s.course,
            s.yr_level,
            s.college,
            t.team_name,
            c.name as contest_name,
            a.activity_name,
            a.activity_type,
            a.competition_level,
            a.school_year,
            cat.category_name,
            cat.category_type,
            p.ranking,
            p.created_at as participation_date,
            u.first_name as coach_first,
            u.last_name as coach_last
        FROM participation p
        JOIN student s ON p.student_id = s.id
        JOIN \"User\" su ON s.user_id = su.id
        JOIN team t ON p.team_id = t.id
        JOIN contest c ON t.contest_id = c.id
        JOIN activity a ON c.activity_id = a.id
        JOIN category cat ON c.category_id = cat.id
        JOIN coach co ON t.coach_id = co.id
        JOIN \"User\" u ON co.user_id = u.id
        WHERE p.membership_status = 'active'
        AND cat.category_type = 'Culture and Arts'
    ";
    
    if ($selected_school_year !== 'all') {
        $participants_sql .= " AND a.school_year = '" . addslashes($selected_school_year) . "'";
    }
    if ($selected_category !== 'all') {
        $participants_sql .= " AND cat.id = " . intval($selected_category);
    }
    if ($selected_competition_level !== 'all') {
        $participants_sql .= " AND a.competition_level = '" . addslashes($selected_competition_level) . "'";
    }
    
    $participants_sql .= " ORDER BY a.school_year DESC, cat.category_type, su.last_name, su.first_name";
    
    $stmt = $pdo->prepare($participants_sql);
    $stmt->execute();
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $participants = [];
}

// Group participants by activity
$participants_by_activity = [];
foreach ($participants as $participant) {
    $activity_key = $participant['activity_name'] . '_' . ($participant['school_year'] ?? 'N/A');
    if (!isset($participants_by_activity[$activity_key])) {
        $participants_by_activity[$activity_key] = [
            'activity_name' => $participant['activity_name'],
            'school_year' => $participant['school_year'],
            'competition_level' => $participant['competition_level'],
            'participants' => []
        ];
    }
    $participants_by_activity[$activity_key]['participants'][] = $participant;
}

// Get statistics with filters
$stats = [
    'total_coaches' => count($coaches),
    'total_teams' => count($teams),
    'total_participants' => count($participants),
    'participants_with_rankings' => 0
];

foreach ($participants as $participant) {
    if (!empty($participant['ranking'])) $stats['participants_with_rankings']++;
}

$report_date = date('F d, Y');
$report_time = date('h:i A');

// Get category name for display
$selected_category_name = 'All Culture and Arts';
if ($selected_category !== 'all') {
    foreach ($availableCategories as $cat) {
        if ($cat['id'] == $selected_category) {
            $selected_category_name = $cat['category_name'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cultural Admin Report - BISU Candijay Achievement Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background: #f5f5f5; min-height: 100vh; }
        .dashboard-container { display: flex; min-height: 100vh; }
        
        /* Sidebar Styles - Same as admin_cultural.php */
        .sidebar {
            width: 280px;
            background: #003366;
            color: white;
            padding: 2rem 1rem;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 100;
        }
        .sidebar-header {
            text-align: center;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 2rem;
        }
        .sidebar-header h2 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: white;
        }
        .user-role {
            background: rgba(255,255,255,0.2);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            display: inline-block;
            font-size: 0.8rem;
        }
        .nav-menu {
            list-style: none;
        }
        .nav-item {
            margin-bottom: 0.5rem;
        }
        .nav-item a {
            color: white;
            text-decoration: none;
            padding: 0.8rem 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .nav-item a:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 1.5rem;
        }
        .nav-item.active a {
            background: rgba(255,255,255,0.2);
            border-left: 3px solid #ffd700;
        }
        .logo-icon img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffd700;
            padding: 2px;
            background: white;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            background: #f5f5f5;
        }
        .top-bar {
            background: white;
            padding: 1rem 2rem;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .page-title h1 {
            font-size: 1.5rem;
            color: #003366;
        }
        .admin-badge {
            background: #9b59b6;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: bold;
            margin-right: 1rem;
            display: inline-block;
        }
        .print-btn {
            background: #28a745;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }
        .print-btn:hover {
            background: #218838;
        }
        
        /* Filter Bar */
        .filter-bar {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            display: flex;
            gap: 1.5rem;
            align-items: flex-end;
            flex-wrap: wrap;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .filter-group label {
            font-weight: 600;
            color: #333;
            font-size: 0.85rem;
        }
        .filter-group select {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            min-width: 180px;
        }
        .filter-group button {
            background: #9b59b6;
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        .filter-group button:hover {
            background: #8e44ad;
        }
        .filter-group a {
            background: #6c757d;
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        .filter-group a:hover {
            background: #5a6268;
        }
        
        /* Report Container - NO SCROLL, FULL WIDTH */
        .report-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
            overflow-y: visible;
        }
        
        /* Report Content - full width, no hidden scroll */
        .report-content {
            width: 100%;
            min-width: 1000px;
        }
        
        /* Report Header Styles */
        .report-header {
            padding: 2rem;
            text-align: center;
            border-bottom: 2px solid #e0e0e0;
            background: white;
        }
        .logo-container {
            display: flex;
            justify-content: left;
            align-items: left;
            gap: 2rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .logo {
            text-align: center;
        }
        .logo-img {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        .logo-img i {
            font-size: 40px;
            color: #003366;
        }
        .logo-text p {
            color: #666;
            font-size: 0.8rem;
        }
        .report-title {
            margin-top: 1rem;
        }
        .report-title h1 {
            color: #8e44ad;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        .report-title h3 {
            color: #666;
            font-weight: normal;
        }
        .rep-header {
            background: #8e44ad;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            display: inline-block;
            margin-top: 0.5rem;
        }
        
        /* Report Meta */
        .report-meta {
            display: flex;
            justify-content: space-between;
            background: #f8f9fa;
            padding: 1rem 2rem;
            border-bottom: 1px solid #dee2e6;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .filter-info {
            background: #e9ecef;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        .filter-info i {
            margin-right: 5px;
        }
        .school-year-box {
            background: #8e44ad;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
        }
        
        /* Stats Summary */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .stat-box {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-box .number {
            font-size: 2rem;
            font-weight: bold;
            color: #8e44ad;
        }
        .stat-box .label {
            color: #666;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        /* Report Sections */
        .report-section {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #dee2e6;
        }
        .section-title {
            color: #8e44ad;
            font-size: 1.3rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #8e44ad;
            display: inline-block;
        }
        .section-subtitle {
            color: #666;
            font-size: 1rem;
            margin: 1rem 0 0.5rem 0;
            font-weight: 600;
        }
        
        /* Tables - full width no horizontal scroll inside */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            table-layout: auto;
        }
        .data-table th, 
        .data-table td {
            padding: 0.6rem 0.8rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            vertical-align: top;
            font-size: 0.8rem;
            word-wrap: break-word;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            white-space: nowrap;
        }
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        /* Member list column */
        .data-table td.members-list {
            white-space: normal;
            min-width: 200px;
            max-width: 250px;
        }
        .data-table td.members-list span {
            display: inline-block;
            background: #e9ecef;
            padding: 2px 6px;
            margin: 2px;
            border-radius: 12px;
            font-size: 0.7rem;
        }
        
        /* Coach Cards */
        .coach-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #8e44ad;
        }
        .coach-name {
            font-size: 1.1rem;
            font-weight: bold;
            color: #8e44ad;
            margin-bottom: 0.5rem;
        }
        .coach-details {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        /* Badges */
        .category-badge-cultural,
        .competition-badge,
        .ranking-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            white-space: nowrap;
        }
        .category-badge-cultural { background: #e8d5f0; color: #6c3483; }
        .competition-badge { background: #e9ecef; color: #495057; }
        .ranking-badge { background: #e9ecef; color: #495057; }
        .ranking-1st { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #856404; }
        .ranking-2nd { background: linear-gradient(135deg, #c0c0c0, #e0e0e0); color: #495057; }
        .ranking-3rd { background: linear-gradient(135deg, #cd7f32, #e8a87c); color: #5c3a1a; }
        
        /* Footer */
        .footer {
            padding: 1.5rem 2rem;
            text-align: center;
            border-top: 1px solid #dee2e6;
            background: #f8f9fa;
            font-size: 0.85rem;
            color: #666;
        }
        
        /* ===================================================== */
        /* PRINT STYLES - ALL CONTENT VISIBLE IN PRINT */
        /* ===================================================== */
        @media print {
            /* Hide sidebar, top-bar, filter bar, buttons when printing */
            .sidebar, .top-bar, .filter-bar, .print-btn, .no-print, 
            .nav-menu, .admin-badge, button, .btn-primary, .btn-add {
                display: none !important;
            }
            
            /* Remove margins and padding for print */
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
                background: white !important;
            }
            
            .dashboard-container {
                display: block !important;
            }
            
            /* Report container takes full width */
            .report-container {
                box-shadow: none !important;
                border-radius: 0 !important;
                overflow: visible !important;
                background: white !important;
                width: 100% !important;
            }
            
            /* Report content - full width */
            .report-content {
                min-width: 100% !important;
                width: 100% !important;
            }
            
            /* Force all tables to be fully visible */
            .data-table, table {
                width: 100% !important;
                min-width: 100% !important;
                table-layout: auto !important;
                page-break-inside: avoid;
            }
            
            /* Ensure all rows are visible */
            .report-section {
                page-break-inside: avoid;
                page-break-after: avoid;
            }
            
            /* Force tables to break properly across pages */
            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            
            thead {
                display: table-header-group;
            }
            
            tfoot {
                display: table-footer-group;
            }
            
            /* Keep badges visible */
            .ranking-badge, .competition-badge, .category-badge-cultural {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            
            /* Card styles */
            .coach-card {
                break-inside: avoid;
                border: 1px solid #ccc;
                box-shadow: none;
            }
            
            /* Stats boxes */
            .stat-box {
                border: 1px solid #ccc;
                box-shadow: none;
                break-inside: avoid;
            }
            
            /* Ensure all text is black for better print */
            * {
                color: black !important;
            }
            
            /* Keep link colors */
            a {
                text-decoration: none;
            }
            
            /* Section titles */
            .section-title {
                color: #8e44ad !important;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }
            .main-content {
                margin-left: 0;
            }
            .stats-summary {
                grid-template-columns: repeat(2, 1fr);
            }
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
   <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon">
                <img src="../includes/uploads/images/bisu_logo.png" alt="BISU Logo" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
            </div>
            <h2>BISU Tracker</h2>
            <div class="user-role">Cultural Admin</div>
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="admin_cultural.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item active"><a href="admin_cultural_analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a></li>
            <li class="nav-item"><a href="admin_cultural.php?section=events"><i class="fas fa-calendar-alt"></i> Cultural Events</a></li>
            <li class="nav-item"><a href="admin_cultural.php?section=contests"><i class="fas fa-trophy"></i> Manage Contests</a></li>
            <li class="nav-item"><a href="admin_cultural.php?section=participants"><i class="fas fa-users"></i> Teams</a></li>
            <li class="nav-item"><a href="admin_cultural.php?section=participations"><i class="fas fa-list-alt"></i> Participant List</a></li>
            <li class="nav-item"><a href="admin_cultural.php?section=announcements"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li class="nav-item"><a href="admin_cultural_report.php"><i class="fas fa-print"></i> Reports</a></li>
            <li class="nav-item"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1><i class="fas fa-print"></i> Reports | <span class="admin-badge"><i class="fas fa-palette"></i> CULTURAL ADMIN</span></h1>
                </div>
                <div>
                    <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
                    <a href="admin_cultural.php" style="background: #6c757d; color: white; padding: 0.5rem 1rem; border-radius: 5px; text-decoration: none;"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
            </div>
            
            <!-- FILTER BAR -->
            <div class="filter-bar no-print">
                <form method="GET" style="display: flex; gap: 1.5rem; flex-wrap: wrap; width: 100%; align-items: flex-end;">
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-alt"></i> School Year</label>
                        <select name="school_year">
                            <option value="all" <?php echo $selected_school_year == 'all' ? 'selected' : ''; ?>>All School Years</option>
                            <?php foreach($availableSchoolYears as $sy): ?>
                                <option value="<?php echo htmlspecialchars($sy['school_year']); ?>" <?php echo $selected_school_year == $sy['school_year'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sy['school_year']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Cultural Category/Event</label>
                        <select name="category">
                            <option value="all" <?php echo $selected_category == 'all' ? 'selected' : ''; ?>>All Culture and Arts</option>
                            <?php foreach($availableCategories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $selected_category == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-tag"></i> Competition Level</label>
                        <select name="competition_level">
                            <option value="all" <?php echo $selected_competition_level == 'all' ? 'selected' : ''; ?>>All Levels</option>
                            <?php foreach($competitionLevels as $level): ?>
                                <option value="<?php echo $level; ?>" <?php echo $selected_competition_level == $level ? 'selected' : ''; ?>>
                                    <?php echo $level; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit"><i class="fas fa-filter"></i> Apply Filters</button>
                        <a href="admin_cultural_report.php"><i class="fas fa-eraser"></i> Clear Filters</a>
                    </div>
                </form>
            </div>
            
            <!-- REPORT CONTAINER -->
            <div class="report-container" id="reportContainer">
                <!-- Report Content - Full width, no hidden scrolling -->
                <div class="report-content">
                    
                    <!-- HEADER with 3 Logos -->
                    <div class="report-header">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 2rem; flex-wrap: wrap;">
                            <!-- Left side - BISU Logo and text -->
                            <div style="display: flex; align-items: center; gap: 1.5rem;">
                                <img src="../includes/uploads/images/bisu_logo.png" alt="BISU Logo" style="width: 100px; height: 100px; border-radius: 100%; object-fit: cover;">
                                <p>Republic of the Philippines<br><strong>BOHOL ISLAND STATE UNIVERSITY</strong><br>Culture and Arts Office<br>Cogtong, Candijay, 6312 Bohol, Philippines<br>Balance | Integrity | Stewardship | Uprightness</p>
                            </div>
                            
                            <!-- Right side - Two Logos Side by Side (Bagong Pilipinas and ISO) -->
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <img src="../includes/uploads/images/bagong_pilipinas_logo.png" alt="Bagong Pilipinas Logo" style="width: 100px; height: 100px; object-fit: cover;">
                                <img src="../includes/uploads/images/ISO_logo.jpg" alt="ISO Logo" style="width: 200px; height: 100px; object-fit: cover;">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Report Meta -->
                    <div class="report-meta">
                        <div><strong><i class="fas fa-user"></i> Prepared by:</strong> <?php echo htmlspecialchars($admin_name); ?></div>
                        <div><strong><i class="fas fa-id-card"></i> Admin Type:</strong> Cultural Admin</div>
                        <div><strong><i class="fas fa-filter"></i> Report Type:</strong> Culture & Arts Only</div>
                        <div class="school-year-box"><i class="fas fa-calendar"></i> Current SY: <?php echo getCurrentSchoolYear(); ?></div>
                    </div>
                    
                    <!-- Active Filters -->
                    <div class="report-meta" style="background: #e9ecef; border-bottom: 1px solid #dee2e6;">
                        <div class="filter-info"><i class="fas fa-chart-line"></i> <strong>Applied Filters:</strong></div>
                        <div class="filter-info"><i class="fas fa-calendar-alt"></i> School Year: <strong><?php echo $selected_school_year == 'all' ? 'All' : htmlspecialchars($selected_school_year); ?></strong></div>
                        <div class="filter-info"><i class="fas fa-filter"></i> Cultural Event: <strong><?php echo htmlspecialchars($selected_category_name); ?></strong></div>
                        <div class="filter-info"><i class="fas fa-tag"></i> Competition Level: <strong><?php echo $selected_competition_level == 'all' ? 'All' : htmlspecialchars($selected_competition_level); ?></strong></div>
                    </div>
                    
                    <!-- Statistics Summary -->
                    <div class="stats-summary">
                        <div class="stat-box"><div class="number"><?php echo $stats['total_coaches']; ?></div><div class="label"><i class="fas fa-chalkboard-user"></i> Cultural Coaches</div></div>
                        <div class="stat-box"><div class="number"><?php echo $stats['total_teams']; ?></div><div class="label"><i class="fas fa-users"></i> Cultural Teams</div></div>
                        <div class="stat-box"><div class="number"><?php echo $stats['total_participants']; ?></div><div class="label"><i class="fas fa-user-graduate"></i> Total Participants</div></div>
                        <div class="stat-box"><div class="number"><?php echo $stats['participants_with_rankings']; ?></div><div class="label"><i class="fas fa-medal"></i> With Awards/Rankings</div></div>
                    </div>
                    
                    <!-- COACHES SECTION -->
                    <div class="report-section">
                        <h2 class="section-title"><i class="fas fa-chalkboard-user"></i> Cultural Coaches</h2>
                        <?php if(count($coaches) > 0): ?>
                            <?php foreach($coaches as $coach): ?>
                                <div class="coach-card">
                                    <div class="coach-name">
                                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']); ?>
                                    </div>
                                    <div class="coach-details">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($coach['email']); ?> |
                                        <i class="fas fa-users"></i> Teams: <?php echo $coach['total_teams']; ?> |
                                        <i class="fas fa-user-graduate"></i> Total Members: <?php echo $coach['total_members']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #999; text-align: center; padding: 1rem;">No cultural coaches found with the selected filters.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- TEAMS SECTION -->
                    <div class="report-section">
                        <h2 class="section-title"><i class="fas fa-users"></i> Cultural Teams by Activity/Event</h2>
                        <?php if(count($teams_by_activity) > 0): ?>
                            <?php foreach($teams_by_activity as $activity): ?>
                                <h3 class="section-subtitle">
                                    <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($activity['activity_name']); ?>
                                    <span class="competition-badge" style="margin-left: 10px;"><?php echo htmlspecialchars($activity['competition_level']); ?></span>
                                    <span class="category-badge-cultural" style="margin-left: 5px;"><?php echo htmlspecialchars($activity['school_year'] ?? 'N/A'); ?></span>
                                </h3>
                                
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Team Name</th>
                                            <th>Coach</th>
                                            <th>Contest</th>
                                            <th>Category</th>
                                            <th>Competition Level</th>
                                            <th>School Year</th>
                                            <th>Members Count</th>
                                            <th>Members List</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($activity['teams'] as $team): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($team['coach_first'] . ' ' . $team['coach_last']); ?></td>
                                                <td><?php echo htmlspecialchars($team['contest_name']); ?></td>
                                                <td><span class="category-badge-cultural"><?php echo htmlspecialchars($team['category_name']); ?></span></td>
                                                <td><span class="competition-badge"><?php echo htmlspecialchars($team['competition_level']); ?></span></td>
                                                <td><?php echo htmlspecialchars($team['school_year'] ?? 'N/A'); ?></td>
                                                <td><?php echo $team['member_count']; ?></td>
                                                <td class="members-list">
                                                    <?php 
                                                    if(!empty($team['members_list'])) {
                                                        $members = explode('; ', $team['members_list']);
                                                        foreach($members as $member) {
                                                            echo '<span>' . htmlspecialchars($member) . '</span>';
                                                        }
                                                    } else {
                                                        echo '<span>No members assigned</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #999; text-align: center; padding: 1rem;">No cultural teams found with the selected filters.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- PARTICIPANTS SECTION -->
                    <div class="report-section">
                        <h2 class="section-title"><i class="fas fa-user-graduate"></i> Culture & Arts Participants</h2>
                        <?php if(count($participants_by_activity) > 0): ?>
                            <?php foreach($participants_by_activity as $activity): ?>
                                <h3 class="section-subtitle">
                                    <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($activity['activity_name']); ?>
                                    <span class="competition-badge" style="margin-left: 10px;"><?php echo htmlspecialchars($activity['competition_level']); ?></span>
                                    <span style="margin-left: 5px; font-size: 0.8rem;"><?php echo htmlspecialchars($activity['school_year'] ?? 'N/A'); ?></span>
                                </h3>
                                
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Course/Year</th>
                                            <th>College</th>
                                            <th>Team</th>
                                            <th>Contest</th>
                                            <th>Art Category</th>
                                            <th>Competition Level</th>
                                            <th>School Year</th>
                                            <th>Ranking/Award</th>
                                            <th>Coach</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($activity['participants'] as $participant): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($participant['course']); ?> (Y<?php echo $participant['yr_level']; ?>)</td>
                                                <td><?php echo htmlspecialchars($participant['college']); ?></td>
                                                <td><?php echo htmlspecialchars($participant['team_name']); ?></td>
                                                <td><?php echo htmlspecialchars($participant['contest_name']); ?></td>
                                                <td><span class="category-badge-cultural"><?php echo htmlspecialchars($participant['category_name']); ?></span></td>
                                                <td><span class="competition-badge"><?php echo htmlspecialchars($participant['competition_level']); ?></span></td>
                                                <td><?php echo htmlspecialchars($participant['school_year'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if(!empty($participant['ranking'])): ?>
                                                        <span class="ranking-badge <?php echo getRankingClass($participant['ranking']); ?>"><?php echo htmlspecialchars($participant['ranking']); ?></span>
                                                    <?php else: ?>
                                                        <span style="color: #999;">—</span>
                                                    <?php endif; ?>
                                                  </td>
                                                <td><?php echo htmlspecialchars($participant['coach_first'] . ' ' . $participant['coach_last']); ?></td>
                                              </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #999; text-align: center; padding: 1rem;">No cultural participants found with the selected filters.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Footer -->
                    <div class="footer">
                        <p><i class="fas fa-print"></i> Generated by BISU Candijay Achievement Tracker System | Culture and Arts Division</p>
                        <p>Balance / Integrity / Stewardship / Uprightness</p>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</body>
</html>