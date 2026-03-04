<?php
// dance_trainer_dashboard.php - COMPLETE WITH FIXED APPROVALS
session_start();
require_once 'db.php';

// IMPORTANT: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user is dance_coach
if ($_SESSION['user_type'] !== 'dance_coach') {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin_pages.php?page=dashboard');
        exit();
    } elseif ($_SESSION['user_type'] === 'sport_coach') {
        header('Location: coach_dashboard.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'student') {
        header('Location: student_dashboard.php');
        exit();
    } else {
        header('Location: login.php');
        exit();
    }
}

$user_id = $_SESSION['user_id'];

// Get dance coach details
$stmt = $pdo->prepare("
    SELECT dc.*, u.first_name, u.last_name, u.email, u.id_number
    FROM dance_coaches dc
    JOIN users u ON dc.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$coach = $stmt->fetch();

// If dance coach not found, create basic record
if (!$coach) {
    $stmt = $pdo->prepare("INSERT INTO dance_coaches (user_id, employee_id, date_hired) VALUES (?, ?, CURDATE())");
    $stmt->execute([$user_id, 'DANCE-' . $user_id]);
    
    $stmt = $pdo->prepare("
        SELECT dc.*, u.first_name, u.last_name, u.email, u.id_number
        FROM dance_coaches dc
        JOIN users u ON dc.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $coach = $stmt->fetch();
}

// Get trainer initials
$trainer_initials = strtoupper(substr($coach['first_name'] ?? 'D', 0, 1) . substr($coach['last_name'] ?? 'T', 0, 1));

// ============ PENDING APPROVALS - DANCERS WAITING FOR TRAINER APPROVAL ============
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
            dt.id as troupe_id
        FROM approvals a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        LEFT JOIN dance_troupes dt ON a.troupe_id = dt.id
        WHERE a.coach_id = ? AND a.status = 'pending'
        ORDER BY a.request_date DESC
    ");
    $stmt->execute([$user_id]); // coach_id in approvals is the USER ID
    $pending_approvals = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_approvals = [];
}

// ============ APPROVED DANCERS (ACTIVE TROUPE MEMBERS) ============
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
            dt.id as troupe_id
        FROM dance_troupe_members dtm
        JOIN students s ON dtm.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN dance_troupes dt ON dtm.troupe_id = dt.id
        WHERE dt.coach_id = ? AND dtm.status = 'active'
        ORDER BY u.first_name ASC
    ");
    $stmt->execute([$coach['id']]);
    $troupe_members = $stmt->fetchAll();
} catch (Exception $e) {
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
    $stmt->execute([$coach['id']]);
    $troupes = $stmt->fetchAll();
} catch (Exception $e) {
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
            WHERE e.event_type IN ('dance', 'both') AND e.event_date >= CURDATE()
            ORDER BY e.event_date ASC
            LIMIT 5
        ");
        $stmt->execute();
        $upcoming_performances = $stmt->fetchAll();
    }
} catch (Exception $e) {
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Dance Trainer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            background: #f8fafc;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - Dance Theme (Maroon/Gold) */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #8B1E3F 0%, #6b152f 100%);
            color: white;
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 30px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .logo span {
            color: #FFB347;
            font-size: 28px;
        }

        .trainer-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .trainer-avatar {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #FFB347 0%, #f39c12 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0a2540;
            font-weight: 700;
            font-size: 20px;
        }

        .trainer-details h4 {
            font-size: 16px;
            color: white;
            margin-bottom: 4px;
        }

        .trainer-details p {
            font-size: 13px;
            color: rgba(255,255,255,0.7);
        }

        .trainer-badge {
            background: #FFB347;
            color: #0a2540;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 6px;
            display: inline-block;
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: 20px;
        }

        .nav-section-title {
            padding: 10px 25px;
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.2s;
            margin: 2px 10px;
            border-radius: 10px;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .nav-item.active {
            background: #FFB347;
            color: #0a2540;
        }

        .nav-item i {
            margin-right: 15px;
            font-size: 18px;
            width: 22px;
        }

        .nav-item span {
            font-size: 14px;
            font-weight: 500;
        }

        .nav-item .badge {
            margin-left: auto;
            background: #ef4444;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
        }

        .divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 20px 25px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
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
            font-weight: 600;
        }

        .header p {
            color: #64748b;
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
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input {
            padding: 12px 20px 12px 45px;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            width: 280px;
            font-size: 14px;
            background: white;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
        }

        .notification-icon i {
            font-size: 22px;
            color: #475569;
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
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .header-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #FFB347 0%, #f39c12 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0a2540;
            font-weight: 600;
            font-size: 16px;
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #8B1E3F 0%, #6b152f 100%);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-text h2 {
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .welcome-text p {
            opacity: 0.9;
            font-size: 14px;
            max-width: 400px;
        }

        .welcome-banner .date {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 500;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid #eef2f6;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-header h3 {
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            background: #fef3c7;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8B1E3F;
            font-size: 20px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a2639;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: #94a3b8;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
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

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* ============ PENDING APPROVALS SECTION ============ */
        .approvals-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 5px solid #FFB347;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        .approvals-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .approvals-header h2 {
            font-size: 18px;
            color: #1a2639;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .approvals-header h2 i {
            color: #FFB347;
        }

        .pending-badge {
            background: #FFB347;
            color: #0a2540;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .approvals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .approvals-table th {
            text-align: left;
            padding: 15px 10px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #e2e8f0;
        }

        .approvals-table td {
            padding: 15px 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-avatar-small {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #8B1E3F 0%, #6b152f 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .approval-actions {
            display: flex;
            gap: 10px;
        }

        .btn-approve {
            padding: 8px 16px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-approve:hover {
            background: #059669;
        }

        .btn-reject {
            padding: 8px 16px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-reject:hover {
            background: #dc2626;
        }

        /* Troupe Members List */
        .member-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .member-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .member-item:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }

        .member-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #8B1E3F 0%, #6b152f 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 600;
            color: #1a2639;
            margin-bottom: 3px;
            font-size: 15px;
        }

        .member-role {
            font-size: 12px;
            color: #64748b;
        }

        .member-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Schedule List */
        .schedule-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .schedule-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 15px;
            border-left: 4px solid #FFB347;
        }

        .schedule-date {
            min-width: 60px;
            text-align: center;
        }

        .schedule-date .day {
            font-size: 22px;
            font-weight: 700;
            color: #FFB347;
        }

        .schedule-date .month {
            font-size: 12px;
            color: #64748b;
        }

        .schedule-details {
            flex: 1;
        }

        .schedule-details h4 {
            font-size: 15px;
            color: #1a2639;
            margin-bottom: 5px;
        }

        .schedule-details p {
            font-size: 12px;
            color: #64748b;
        }

        .schedule-team {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 50px;
            margin-bottom: 15px;
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
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            text-decoration: none;
            color: #1a2639;
            border: 1px solid #eef2f6;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border-color: #FFB347;
        }

        .action-btn i {
            font-size: 24px;
            color: #FFB347;
        }

        .action-btn span {
            font-size: 13px;
            font-weight: 500;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid #eef2f6;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eef2f6;
        }

        .card-header h3 {
            font-size: 16px;
            color: #1a2639;
            font-weight: 600;
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
            font-weight: 500;
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
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 35px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-content h2 {
            margin-bottom: 25px;
            color: #1a2639;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-content h2 i {
            color: #FFB347;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #475569;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-submit {
            flex: 1;
            padding: 14px;
            background: #FFB347;
            color: #0a2540;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-cancel {
            flex: 1;
            padding: 14px;
            background: #e2e8f0;
            color: #475569;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            .sidebar .logo span:last-child,
            .trainer-details,
            .nav-item span,
            .nav-section-title {
                display: none;
            }
            .main-content {
                margin-left: 70px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span>💃</span> TALENTRIX
                </div>
                <div class="trainer-info">
                    <div class="trainer-avatar">
                        <?php echo $trainer_initials; ?>
                    </div>
                    <div class="trainer-details">
                        <h4>Trainer <?php echo htmlspecialchars($coach['first_name'] ?? 'Trainer'); ?></h4>
                        <p><?php echo htmlspecialchars($coach['dance_specialization'] ?? 'Dance Trainer'); ?></p>
                        <span class="trainer-badge">Active</span>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <!-- MENU Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Menu</div>
                    <a href="dance_trainer_dashboard.php" class="nav-item active">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="#" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span>My Dancers</span>
                        <?php if($total_dancers > 0): ?>
                        <span class="badge"><?php echo $total_dancers; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="#" class="nav-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Schedule</span>
                    </a>
                    <a href="#" class="nav-item">
                        <i class="fas fa-trophy"></i>
                        <span>Achievements</span>
                    </a>
                </div>

                <!-- TROUPES Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Troupes</div>
                    <?php foreach(array_slice($troupes, 0, 3) as $troupe): ?>
                    <a href="#" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span><?php echo htmlspecialchars($troupe['troupe_name']); ?></span>
                        <span class="badge" style="background: #FFB347;"><?php echo $troupe['member_count']; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- ACCOUNT Section with LOGOUT under Profile -->
                <div class="nav-section">
                    <div class="nav-section-title">Account</div>
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                    <a href="#" class="nav-item">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <!-- LOGOUT added here under Profile and Settings -->
                    <a href="logout.php" class="nav-item" style="color: #ef4444;">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>

                <div class="divider"></div>
                
                <!-- Homepage Link -->
                <a href="index.php" class="nav-item">
                    <i class="fas fa-globe"></i>
                    <span>Homepage</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1>Troupe Management</h1>
                    <p><?php echo $greeting; ?>, Trainer <?php echo htmlspecialchars($coach['first_name'] ?? 'Trainer'); ?>!</p>
                </div>
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search dancer...">
                    </div>
                    <div class="notification-icon">
                        <i class="far fa-bell"></i>
                        <?php if($pending_count > 0): ?>
                        <span class="notification-badge"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="header-profile">
                        <div class="header-avatar">
                            <?php echo $trainer_initials; ?>
                        </div>
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

            <!-- ============ PENDING APPROVALS SECTION ============ -->
            <?php if(!empty($pending_approvals)): ?>
            <div class="approvals-section">
                <div class="approvals-header">
                    <h2><i class="fas fa-clock"></i> Pending Approvals</h2>
                    <span class="pending-badge"><?php echo count($pending_approvals); ?> requests</span>
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
                                    <div class="student-avatar-small">
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
                                <div class="approval-actions">
                                    <button class="btn-approve" onclick="approveRequest(<?php echo $approval['approval_id']; ?>)">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn-reject" onclick="showRejectModal(<?php echo $approval['approval_id']; ?>)">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="approvals-section" style="border-left-color: #10b981;">
                <div class="approvals-header">
                    <h2><i class="fas fa-check-circle" style="color: #10b981;"></i> No Pending Approvals</h2>
                </div>
                <p style="color: #64748b; text-align: center; padding: 20px;">All caught up! No dancers waiting for approval.</p>
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
                <a href="#" class="action-btn" onclick="alert('Achievement feature coming soon!')">
                    <i class="fas fa-medal"></i>
                    <span>Add Achievement</span>
                </a>
                <a href="#" class="action-btn" onclick="alert('Attendance feature coming soon!')">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Mark Attendance</span>
                </a>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Troupe Members -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Troupe Members</h3>
                        <a href="#" class="view-all">View All →</a>
                    </div>
                    
                    <div class="member-list">
                        <?php if(!empty($troupe_members)): ?>
                            <?php foreach($troupe_members as $member): ?>
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
                        <a href="#" class="view-all">View All →</a>
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
                                <span class="schedule-team">Performance</span>
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
            <div class="card" style="margin-top: 30px;">
                <div class="card-header">
                    <h3><i class="fas fa-music"></i> My Dance Troupes</h3>
                    <a href="#" class="view-all">Manage →</a>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
                    <?php foreach($troupes as $troupe): ?>
                    <div style="background: #f8fafc; border-radius: 12px; padding: 20px; border-left: 4px solid #8B1E3F;">
                        <h4 style="color: #1a2639; margin-bottom: 10px;"><?php echo htmlspecialchars($troupe['troupe_name']); ?></h4>
                        <p style="color: #64748b; margin-bottom: 10px;"><?php echo htmlspecialchars($troupe['dance_style'] ?? 'Various Styles'); ?></p>
                        <div style="display: flex; align-items: center; gap: 10px; color: #8B1E3F;">
                            <i class="fas fa-users"></i>
                            <span><?php echo $troupe['member_count'] ?? 0; ?> members</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h2><i class="fas fa-times-circle" style="color: #ef4444;"></i> Reject Request</h2>
            <p style="margin-bottom: 20px;">Please provide a reason for rejection:</p>
            <div class="form-group">
                <textarea id="rejectReason" rows="4" placeholder="Enter reason..."></textarea>
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
            <form method="POST" action="add_student.php">
                <div class="form-group">
                    <label>Student ID Number</label>
                    <input type="text" name="id_number" placeholder="e.g., 2024-0001" required>
                </div>
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Dance Troupe</label>
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
                <div class="modal-actions">
                    <button type="submit" class="btn-submit">Add Dancer</button>
                    <button type="button" class="btn-cancel" onclick="closeAddDancerModal()">Cancel</button>
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
                    <label>Event Title</label>
                    <input type="text" name="event_title" required placeholder="e.g., Dance Practice, Recital">
                </div>
                <div class="form-group">
                    <label>Event Date</label>
                    <input type="date" name="event_date" required>
                </div>
                <div class="form-group">
                    <label>Event Time</label>
                    <input type="time" name="event_time" required>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" required placeholder="e.g., Dance Studio">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="event_description" rows="3" placeholder="Event details..."></textarea>
                </div>
                <div class="form-group">
                    <label>Event Type</label>
                    <select name="event_type">
                        <option value="dance">Dance Event</option>
                        <option value="both">Both (Dance & Athletics)</option>
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

    function openScheduleModal() {
        document.getElementById('scheduleModal').classList.add('active');
    }

    function closeScheduleModal() {
        document.getElementById('scheduleModal').classList.remove('active');
    }

    // Approval functions
    function approveRequest(approvalId) {
        if(confirm('Approve this request?')) {
            window.location.href = 'process_approval.php?id=' + approvalId + '&action=approve';
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
        window.location.href = 'process_approval.php?id=' + currentApprovalId + '&action=reject&reason=' + encodeURIComponent(reason);
    }

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