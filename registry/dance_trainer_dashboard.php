<?php
// dance_trainer_dashboard.php - UPDATED WITH FIXED APPROVAL QUERIES
session_start();
require_once 'db.php';

// allow only dance trainer
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'dance_trainer'){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$page = $_GET['page'] ?? 'dashboard';

// Get dance trainer details
$stmt = $pdo->prepare("
    SELECT dt.*, u.first_name, u.last_name, u.email, u.id_number
    FROM dance_trainers dt
    JOIN users u ON dt.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$trainer = $stmt->fetch();

// If dance trainer not found, create basic record
if (!$trainer) {
    $stmt = $pdo->prepare("INSERT INTO dance_trainers (user_id, employee_id, date_hired) VALUES (?, ?, CURDATE())");
    $stmt->execute([$user_id, 'DANCE-' . $user_id]);
    
    $stmt = $pdo->prepare("
        SELECT dt.*, u.first_name, u.last_name, u.email, u.id_number
        FROM dance_trainers dt
        JOIN users u ON dt.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $trainer = $stmt->fetch();
}

// Get trainer initials
$trainer_initials = strtoupper(substr($trainer['first_name'] ?? 'D', 0, 1) . substr($trainer['last_name'] ?? 'T', 0, 1));

// ============ PENDING APPROVALS - DANCERS WAITING FOR TRAINER APPROVAL ============
// FIXED: Now using dance_trainer_id instead of coach_id
$pending_approvals = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.id as approval_id,
            s.id as student_id,
            u.id_number,
            u.first_name,
            u.last_name,
            u.email,
            'dancer' as student_type,
            s.dance_troupe as troupe_name,
            s.dance_role as role,
            a.request_date,
            a.status,
            dt.troupe_name,
            dt.id as troupe_id,
            a.approval_type
        FROM approvals a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        LEFT JOIN dance_troupes dt ON a.troupe_id = dt.id
        WHERE a.dance_trainer_id = ? AND a.status = 'pending'
        ORDER BY a.request_date DESC
    ");
    $stmt->execute([$trainer['id']]); // Using dance_trainer.id, not user_id
    $pending_approvals = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching pending approvals: " . $e->getMessage());
    $pending_approvals = [];
}

// ============ APPROVED DANCERS (ACTIVE TROUPE MEMBERS) ============
// FIXED: Now using dance_trainer_id to filter
$troupe_members = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            u.first_name,
            u.last_name,
            u.email,
            s.dance_troupe,
            s.dance_role as role,
            dtm.joined_date,
            dt.troupe_name,
            dt.id as troupe_id,
            a.status as approval_status
        FROM dance_troupe_members dtm
        JOIN students s ON dtm.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN dance_troupes dt ON dtm.troupe_id = dt.id
        LEFT JOIN approvals a ON a.student_id = s.id AND a.dance_trainer_id = ? AND a.status = 'approved'
        WHERE dt.coach_id = ? AND dtm.status = 'active'
        ORDER BY u.first_name ASC
    ");
    $stmt->execute([$trainer['id'], $trainer['id']]);
    $troupe_members = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching troupe members: " . $e->getMessage());
    $troupe_members = [];
}

// Get all troupes under this trainer
$troupes = [];
try {
    $stmt = $pdo->prepare("
        SELECT dt.*, 
               (SELECT COUNT(*) FROM dance_troupe_members WHERE troupe_id = dt.id AND status = 'active') as member_count
        FROM dance_troupes dt
        WHERE dt.coach_id = ?
        ORDER BY dt.troupe_name
    ");
    $stmt->execute([$trainer['id']]);
    $troupes = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching troupes: " . $e->getMessage());
    $troupes = [];
}

// Get upcoming performances
$upcoming_performances = [];
try {
    $troupe_ids = array_column($troupes, 'id');
    if (!empty($troupe_ids)) {
        $placeholders = implode(',', array_fill(0, count($troupe_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT e.*, 'performance' as type
            FROM upcoming_events e
            WHERE e.troupe_id IN ($placeholders) AND e.event_date >= CURDATE()
            ORDER BY e.event_date ASC
            LIMIT 5
        ");
        $stmt->execute($troupe_ids);
        $upcoming_performances = $stmt->fetchAll();
    } else {
        // Get general dance events if no specific troupes
        $stmt = $pdo->prepare("
            SELECT e.*, 'performance' as type
            FROM upcoming_events e
            WHERE e.event_type IN ('dance', 'both') AND e.event_date >= CURDATE()
            ORDER BY e.event_date ASC
            LIMIT 5
        ");
        $stmt->execute();
        $upcoming_performances = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching upcoming performances: " . $e->getMessage());
    $upcoming_performances = [];
}

// Get stats
$total_dancers = count($troupe_members);
$pending_count = count($pending_approvals);
$total_troupes = count($troupes);

// Get greeting
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dance Trainer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f5f6fa;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* ===== MODERN SIDEBAR DESIGN FOR DANCE TRAINER ===== */
        .sidebar {
            width: 300px;
            background: linear-gradient(180deg, #8B1E3F 0%, #6b152f 100%);
            position: fixed;
            height: 100vh;
            padding: 25px 0;
            box-shadow: 10px 0 30px rgba(139, 30, 63, 0.3);
            overflow-y: auto;
            z-index: 100;
        }

        /* Custom scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.3);
        }

        /* User Profile Section */
        .user-profile {
            padding: 20px 25px 30px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 15px;
        }

        .user-avatar-large {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #FFB347 0%, #f39c12 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .user-avatar-large span {
            font-size: 28px;
            font-weight: 700;
            color: #8B1E3F;
        }

        .user-name {
            font-size: 18px;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
        }

        .user-role {
            display: inline-block;
            background: #FFB347;
            color: #8B1E3F;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            margin-top: 5px;
        }

        .user-email {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .user-email i {
            font-size: 12px;
            color: #FFB347;
        }

        /* Navigation Section Titles */
        .nav-section {
            padding: 15px 25px 5px 25px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.4);
        }

        /* Modern Button-style Navigation Items */
        .sidebar-nav ul {
            list-style: none;
            padding: 0 15px;
        }

        .sidebar-nav li {
            margin: 4px 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            border-radius: 12px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        /* Button hover effect */
        .sidebar-nav a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }

        .sidebar-nav a:hover::before {
            left: 100%;
        }

        .sidebar-nav a i {
            margin-right: 15px;
            width: 24px;
            font-size: 18px;
            color: rgba(255,255,255,0.6);
            transition: all 0.3s ease;
        }

        /* Button hover state */
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1);
            color: #FFB347;
            transform: translateX(5px);
        }

        .sidebar-nav a:hover i {
            color: #FFB347;
            transform: scale(1.1);
        }

        /* Active button state */
        .sidebar-nav li.active a {
            background: #FFB347;
            color: #8B1E3F;
            font-weight: 600;
            box-shadow: 0 10px 20px rgba(255, 179, 71, 0.3);
        }

        .sidebar-nav li.active a i {
            color: #8B1E3F;
        }

        .sidebar-nav li.active a::before {
            display: none;
        }

        /* Badge for counts */
        .badge {
            margin-left: auto;
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.8);
            padding: 3px 8px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .sidebar-nav a:hover .badge {
            background: rgba(255, 179, 71, 0.2);
            color: #FFB347;
        }

        .sidebar-nav li.active .badge {
            background: rgba(139, 30, 63, 0.2);
            color: #8B1E3F;
        }

        /* Logout button special style */
        .logout-section {
            margin-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 15px;
        }

        .logout-section a {
            background: rgba(239, 68, 68, 0.1);
            color: #ff8a8a;
        }

        .logout-section a:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #ff6b6b;
            transform: translateX(5px);
        }

        .logout-section a i {
            color: #ff8a8a;
        }

        .logout-section a:hover i {
            color: #ff6b6b;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 300px;
            padding: 30px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            color: #1a2639;
        }

        .header p {
            color: #666;
            margin-top: 5px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .search-box input {
            padding: 10px 10px 10px 35px;
            border: 1px solid #ddd;
            border-radius: 20px;
            width: 250px;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
        }

        .notification-icon i {
            font-size: 20px;
            color: #666;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-avatar {
            width: 45px;
            height: 45px;
            background: #FFB347;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8B1E3F;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #8B1E3F 0%, #6b152f 100%);
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 30px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-text h2 {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .welcome-text p {
            opacity: 0.9;
            font-size: 14px;
        }

        .date {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #FFB347;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .stat-header h3 {
            font-size: 13px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            background: #fef3c7;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8B1E3F;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1a2639;
        }

        .stat-label {
            font-size: 12px;
            color: #999;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .card-header h3 {
            font-size: 16px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-header h3 i {
            color: #FFB347;
        }

        .view-all {
            color: #FFB347;
            text-decoration: none;
            font-size: 13px;
        }

        /* Alert */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* Pending Approvals Table */
        .approvals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .approvals-table th {
            text-align: left;
            padding: 12px 10px;
            background: #f8fafc;
            color: #666;
            font-size: 12px;
            border-bottom: 2px solid #eee;
        }

        .approvals-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-avatar {
            width: 35px;
            height: 35px;
            background: #8B1E3F;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 12px;
        }

        .btn-approve {
            padding: 5px 12px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
        }

        .btn-reject {
            padding: 5px 12px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
        }

        /* Member List */
        .member-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .member-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .member-avatar {
            width: 40px;
            height: 40px;
            background: #8B1E3F;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 600;
            color: #333;
        }

        .member-role {
            font-size: 12px;
            color: #999;
        }

        .member-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
        }

        /* Schedule List */
        .schedule-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .schedule-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #FFB347;
        }

        .schedule-date {
            min-width: 50px;
            text-align: center;
        }

        .schedule-date .day {
            font-size: 18px;
            font-weight: 700;
            color: #FFB347;
        }

        .schedule-date .month {
            font-size: 10px;
            color: #999;
        }

        .schedule-details {
            flex: 1;
        }

        .schedule-details h4 {
            font-size: 14px;
            color: #333;
        }

        .schedule-details p {
            font-size: 11px;
            color: #999;
        }

        .schedule-type {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 10px;
        }

        /* Troupes Grid */
        .troupes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .troupe-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 15px;
            border-left: 3px solid #8B1E3F;
            text-decoration: none;
            color: inherit;
            transition: transform 0.3s;
            display: block;
        }

        .troupe-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .troupe-card h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .troupe-card p {
            color: #666;
            font-size: 12px;
            margin-bottom: 10px;
        }

        .troupe-stats {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #8B1E3F;
            font-size: 12px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 40px;
            margin-bottom: 10px;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .action-btn {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            text-decoration: none;
            color: #333;
            border: 1px solid #eee;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            border-color: #FFB347;
            box-shadow: 0 5px 15px rgba(255, 179, 71, 0.2);
        }

        .action-btn i {
            font-size: 20px;
            color: #FFB347;
        }

        .action-btn span {
            font-size: 12px;
            font-weight: 500;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
        }

        .modal-content h2 {
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #666;
            font-size: 13px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-submit {
            flex: 1;
            padding: 12px;
            background: #FFB347;
            color: #8B1E3F;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-cancel {
            flex: 1;
            padding: 12px;
            background: #e2e8f0;
            color: #666;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            
            .user-avatar-large {
                width: 50px;
                height: 50px;
            }
            
            .user-avatar-large span {
                font-size: 20px;
            }
            
            .user-name,
            .user-role,
            .user-email,
            .nav-section,
            .sidebar-nav a span,
            .badge {
                display: none;
            }
            
            .sidebar-nav a {
                padding: 15px;
                justify-content: center;
            }
            
            .sidebar-nav a i {
                margin-right: 0;
                font-size: 20px;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .header-actions {
                gap: 10px;
            }
            
            .search-box input {
                width: 180px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .troupes-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="dashboard">
    <!-- MODERN SIDEBAR FOR DANCE TRAINER -->
    <div class="sidebar">
        <!-- User Profile Section -->
        <div class="user-profile">
            <div class="user-avatar-large">
                <span><?php echo $trainer_initials; ?></span>
            </div>
            <div class="user-name">Trainer <?php echo htmlspecialchars($trainer['first_name'] ?? 'Trainer'); ?> <?php echo htmlspecialchars($trainer['last_name'] ?? ''); ?></div>
            <div class="user-email">
                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($trainer['email'] ?? 'trainer@dance.edu'); ?>
            </div>
            <div class="user-role">DANCE TRAINER</div>
        </div>

        <!-- MAIN MENU SECTION -->
        <div class="nav-section">MAIN</div>
        <nav class="sidebar-nav">
            <ul>
                <li class="<?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
                    <a href="?page=dashboard">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- MANAGEMENT SECTION -->
        <div class="nav-section">MANAGEMENT</div>
        <nav class="sidebar-nav">
            <ul>
                <li class="<?php echo ($page == 'dancers') ? 'active' : ''; ?>">
                    <a href="?page=dancers">
                        <i class="fas fa-users"></i>
                        <span>My Dancers</span>
                        <?php if($total_dancers > 0): ?>
                        <span class="badge"><?php echo $total_dancers; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="<?php echo ($page == 'schedule') ? 'active' : ''; ?>">
                    <a href="?page=schedule">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Schedule</span>
                    </a>
                </li>
                <li class="<?php echo ($page == 'achievements') ? 'active' : ''; ?>">
                    <a href="?page=achievements">
                        <i class="fas fa-trophy"></i>
                        <span>Achievements</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- TROUPES SECTION -->
        <div class="nav-section">MY TROUPES</div>
        <nav class="sidebar-nav">
            <ul>
                <?php foreach(array_slice($troupes, 0, 3) as $troupe): ?>
                <li>
                    <a href="troupe_details.php?id=<?php echo $troupe['id']; ?>">
                        <i class="fas fa-music"></i>
                        <span><?php echo htmlspecialchars($troupe['troupe_name']); ?></span>
                        <span class="badge" style="background: #FFB347;"><?php echo $troupe['member_count'] ?? 0; ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
                <?php if(empty($troupes)): ?>
                <li>
                    <a href="#" onclick="openCreateTroupeModal()">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create Troupe</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- ACCOUNT SECTION -->
        <div class="nav-section">ACCOUNT</div>
        <nav class="sidebar-nav">
            <ul>
                <li>
                    <a href="profile.php">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- HOMEPAGE LINK -->
        <div class="nav-section">WEBSITE</div>
        <nav class="sidebar-nav">
            <ul>
                <li>
                    <a href="index.php">
                        <i class="fas fa-globe"></i>
                        <span>View Homepage</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- LOGOUT BUTTON -->
        <div class="logout-section">
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <div class="main-content">
        <?php if($page == 'dashboard'): ?>

            <!-- Header -->
            <div class="header">
                <div>
                    <h1>Troupe Management</h1>
                    <p><?php echo $greeting; ?>, Trainer <?php echo htmlspecialchars($trainer['first_name'] ?? 'Trainer'); ?>!</p>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search dancer...">
                    </div>
                    <div class="notification-icon">
                        <i class="far fa-bell"></i>
                        <?php if($pending_count > 0): ?>
                        <span class="notification-badge"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="header-avatar">
                        <?php echo $trainer_initials; ?>
                    </div>
                </div>
            </div>

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h2><i class="fas fa-music"></i> Welcome to Your Dashboard</h2>
                    <p>Manage your troupe, approve new dancers, and schedule performances.</p>
                </div>
                <div class="date">
                    <i class="far fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Dancers</h3>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_dancers; ?></div>
                    <div class="stat-label">Active troupe members</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Pending</h3>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Awaiting approval</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Troupes</h3>
                        <div class="stat-icon">
                            <i class="fas fa-music"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_troupes; ?></div>
                    <div class="stat-label">Under your supervision</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Approved</h3>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $total_dancers; ?></div>
                    <div class="stat-label">Ready to perform</div>
                </div>
            </div>

            <!-- PENDING APPROVALS SECTION -->
            <?php if(!empty($pending_approvals)): ?>
            <div class="card" style="border-left: 4px solid #FFB347;">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Pending Approvals</h3>
                    <span style="background: #FFB347; color: #8B1E3F; padding: 3px 10px; border-radius: 15px; font-size: 12px;"><?php echo count($pending_approvals); ?> requests</span>
                </div>
                
                <table class="approvals-table">
                    <thead>
                        <tr>
                            <th>Dancer</th>
                            <th>ID Number</th>
                            <th>Troupe</th>
                            <th>Role</th>
                            <th>Request Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pending_approvals as $approval): ?>
                        <tr>
                            <td>
                                <div class="student-info">
                                    <div class="student-avatar">
                                        <?php echo strtoupper(substr($approval['first_name'], 0, 1) . substr($approval['last_name'], 0, 1)); ?>
                                    </div>
                                    <span><?php echo htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($approval['id_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($approval['troupe_name'] ?? $approval['troupe'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($approval['role'] ?? 'Dancer'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($approval['request_date'])); ?></td>
                            <td>
                                <button class="btn-approve" onclick="approveRequest(<?php echo $approval['approval_id']; ?>)">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn-reject" onclick="showRejectModal(<?php echo $approval['approval_id']; ?>)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card" style="border-left: 4px solid #10b981;">
                <div class="card-header">
                    <h3><i class="fas fa-check-circle" style="color: #10b981;"></i> No Pending Approvals</h3>
                </div>
                <p style="color: #999; text-align: center; padding: 20px;">All caught up! No dancers waiting for approval.</p>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="#" class="action-btn" onclick="openAddDancerModal()">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Dancer</span>
                </a>
                <a href="#" class="action-btn" onclick="openScheduleModal()">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Schedule Event</span>
                </a>
                <a href="?page=achievements" class="action-btn">
                    <i class="fas fa-medal"></i>
                    <span>Add Achievement</span>
                </a>
                <a href="#" class="action-btn" onclick="openCreateTroupeModal()">
                    <i class="fas fa-plus-circle"></i>
                    <span>Create Troupe</span>
                </a>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Troupe Members -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Troupe Members</h3>
                        <a href="?page=dancers" class="view-all">View All →</a>
                    </div>
                    
                    <div class="member-list" id="memberList">
                        <?php if(!empty($troupe_members)): ?>
                            <?php foreach(array_slice($troupe_members, 0, 5) as $member): ?>
                            <div class="member-item">
                                <div class="member-avatar">
                                    <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'] ?? '', 0, 1)); ?>
                                </div>
                                <div class="member-info">
                                    <div class="member-name"><?php echo htmlspecialchars($member['first_name'] . ' ' . ($member['last_name'] ?? '')); ?></div>
                                    <div class="member-role"><?php echo htmlspecialchars($member['role'] ?? 'Dancer'); ?></div>
                                </div>
                                <span class="member-badge"><?php echo htmlspecialchars($member['troupe_name'] ?? 'Member'); ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-slash"></i>
                                <p>No dancers added yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Schedule -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Upcoming Events</h3>
                        <a href="?page=schedule" class="view-all">View All →</a>
                    </div>
                    
                    <div class="schedule-list">
                        <?php if(!empty($upcoming_performances)): ?>
                            <?php foreach($upcoming_performances as $event): ?>
                            <div class="schedule-item">
                                <div class="schedule-date">
                                    <div class="day"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                                    <div class="month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                                </div>
                                <div class="schedule-details">
                                    <h4><?php echo htmlspecialchars($event['event_title']); ?></h4>
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location'] ?? 'Dance Studio'); ?></p>
                                </div>
                                <span class="schedule-type">Performance</span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No upcoming events</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- My Troupes -->
            <?php if(!empty($troupes)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-music"></i> My Dance Troupes</h3>
                    <a href="#" class="view-all" onclick="openCreateTroupeModal()">Create New →</a>
                </div>
                
                <div class="troupes-grid">
                    <?php foreach($troupes as $troupe): ?>
                    <a href="troupe_details.php?id=<?php echo $troupe['id']; ?>" class="troupe-card">
                        <h4><?php echo htmlspecialchars($troupe['troupe_name']); ?></h4>
                        <p><?php echo htmlspecialchars($troupe['dance_style'] ?? 'Various Styles'); ?></p>
                        <div class="troupe-stats">
                            <i class="fas fa-users"></i>
                            <span><?php echo $troupe['member_count'] ?? 0; ?> members</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        <?php elseif($page == 'dancers'): ?>
            <?php include 'my_dancers.php'; ?>
        <?php elseif($page == 'schedule'): ?>
            <?php include 'dance_schedule.php'; ?>
        <?php elseif($page == 'achievements'): ?>
            <?php include 'dance_achievements.php'; ?>
        <?php else: ?>
            <div class="card">
                <h1>Page Not Found</h1>
                <p>The requested page does not exist.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <h2><i class="fas fa-times-circle" style="color: #ef4444;"></i> Reject Request</h2>
        <p style="margin-bottom: 15px;">Please provide a reason for rejection:</p>
        <div class="form-group">
            <textarea id="rejectReason" rows="3" placeholder="Enter reason..."></textarea>
        </div>
        <div class="modal-actions">
            <button class="btn-submit" style="background: #ef4444;" onclick="confirmReject()">Confirm Reject</button>
            <button class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Add Dancer Modal -->
<div id="addDancerModal" class="modal">
    <div class="modal-content">
        <h2><i class="fas fa-user-plus"></i> Add New Dancer</h2>
        <form method="POST" action="add_dancer.php">
            <div class="form-group">
                <label>Student ID Number *</label>
                <input type="text" name="id_number" placeholder="e.g., 2024-0001" required>
            </div>
            <div class="form-group">
                <label>First Name *</label>
                <input type="text" name="first_name" required>
            </div>
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Dance Troupe *</label>
                <select name="dance_troupe" required>
                    <option value="">-- Select Troupe --</option>
                    <?php foreach($troupes as $troupe): ?>
                    <option value="<?php echo $troupe['troupe_name']; ?>"><?php echo htmlspecialchars($troupe['troupe_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="dance_role">
                    <option value="Member">Member</option>
                    <option value="Lead Dancer">Lead Dancer</option>
                    <option value="Choreographer">Choreographer</option>
                </select>
            </div>
            <input type="hidden" name="trainer_id" value="<?php echo $trainer['id']; ?>">
            <div class="modal-actions">
                <button type="submit" class="btn-submit">Add Dancer</button>
                <button type="button" class="btn-cancel" onclick="closeAddDancerModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Troupe Modal -->
<div id="createTroupeModal" class="modal">
    <div class="modal-content">
        <h2><i class="fas fa-plus-circle"></i> Create Dance Troupe</h2>
        <form method="POST" action="create_troupe.php">
            <div class="form-group">
                <label>Troupe Name *</label>
                <input type="text" name="troupe_name" required placeholder="e.g., BISU Street Dancers">
            </div>
            <div class="form-group">
                <label>Dance Style</label>
                <select name="dance_style">
                    <option value="">-- Select Style --</option>
                    <option value="Street Dance">Street Dance</option>
                    <option value="Hip Hop">Hip Hop</option>
                    <option value="Contemporary">Contemporary</option>
                    <option value="Ballet">Ballet</option>
                    <option value="Jazz">Jazz</option>
                    <option value="Folk Dance">Folk Dance</option>
                    <option value="Ballroom">Ballroom</option>
                    <option value="Cheerdance">Cheerdance</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="Describe your troupe..."></textarea>
            </div>
            <input type="hidden" name="coach_id" value="<?php echo $trainer['id']; ?>">
            <div class="modal-actions">
                <button type="submit" class="btn-submit">Create Troupe</button>
                <button type="button" class="btn-cancel" onclick="closeCreateTroupeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Schedule Event Modal -->
<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <h2><i class="fas fa-calendar-plus"></i> Schedule Event</h2>
        <form method="POST" action="schedule_event.php">
            <div class="form-group">
                <label>Event Title *</label>
                <input type="text" name="event_title" required placeholder="e.g., Dance Practice, Recital">
            </div>
            <div class="form-group">
                <label>Event Date *</label>
                <input type="date" name="event_date" required>
            </div>
            <div class="form-group">
                <label>Event Time *</label>
                <input type="time" name="event_time" required>
            </div>
            <div class="form-group">
                <label>Location *</label>
                <input type="text" name="location" required placeholder="e.g., Dance Studio">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="event_description" rows="3" placeholder="Event details..."></textarea>
            </div>
            <div class="form-group">
                <label>Troupe (Optional)</label>
                <select name="troupe_id">
                    <option value="">-- All Troupes --</option>
                    <?php foreach($troupes as $troupe): ?>
                    <option value="<?php echo $troupe['id']; ?>"><?php echo htmlspecialchars($troupe['troupe_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn-submit">Schedule</button>
                <button type="button" class="btn-cancel" onclick="closeScheduleModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentApprovalId = null;

// Modal functions
function openAddDancerModal() {
    document.getElementById('addDancerModal').classList.add('active');
}

function closeAddDancerModal() {
    document.getElementById('addDancerModal').classList.remove('active');
}

function openCreateTroupeModal() {
    document.getElementById('createTroupeModal').classList.add('active');
}

function closeCreateTroupeModal() {
    document.getElementById('createTroupeModal').classList.remove('active');
}

function openScheduleModal() {
    document.getElementById('scheduleModal').classList.add('active');
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.remove('active');
}

// Approval functions
function approveRequest(approvalId) {
    if(confirm('Approve this request?')) {
        window.location.href = 'process_approval.php?id=' + approvalId + '&action=approve&type=dance';
    }
}

function showRejectModal(approvalId) {
    currentApprovalId = approvalId;
    document.getElementById('rejectModal').classList.add('active');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('active');
    document.getElementById('rejectReason').value = '';
    currentApprovalId = null;
}

function confirmReject() {
    const reason = document.getElementById('rejectReason').value;
    if(!reason.trim()) {
        alert('Please provide a reason for rejection');
        return;
    }
    window.location.href = 'process_approval.php?id=' + currentApprovalId + '&action=reject&reason=' + encodeURIComponent(reason) + '&type=dance';
}

// Search functionality
document.getElementById('searchInput')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const members = document.querySelectorAll('.member-item');
    
    members.forEach(member => {
        const name = member.querySelector('.member-name')?.textContent.toLowerCase() || '';
        const role = member.querySelector('.member-role')?.textContent.toLowerCase() || '';
        
        if(name.includes(searchTerm) || role.includes(searchTerm)) {
            member.style.display = 'flex';
        } else {
            member.style.display = 'none';
        }
    });
});

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target == modal) {
            modal.classList.remove('active');
        }
    });
}
</script>

</body>
</html>