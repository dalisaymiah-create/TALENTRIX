<?php
// achievements.php - View All Achievements
session_start();
require_once 'db.php';

// Get all achievements
$achievements = $pdo->query("
    SELECT a.*, s.id as student_id, s.student_type, u.first_name, u.last_name
    FROM achievements a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE a.is_verified = 1
    ORDER BY a.created_at DESC
")->fetchAll();

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Apply filter
if ($filter == 'athlete') {
    $achievements = $pdo->query("
        SELECT a.*, s.id as student_id, s.student_type, u.first_name, u.last_name
        FROM achievements a
        LEFT JOIN students s ON a.student_id = s.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE a.is_verified = 1 AND s.student_type IN ('athlete', 'both')
        ORDER BY a.created_at DESC
    ")->fetchAll();
} elseif ($filter == 'dance') {
    $achievements = $pdo->query("
        SELECT a.*, s.id as student_id, s.student_type, u.first_name, u.last_name
        FROM achievements a
        LEFT JOIN students s ON a.student_id = s.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE a.is_verified = 1 AND s.student_type IN ('dancer', 'both')
        ORDER BY a.created_at DESC
    ")->fetchAll();
}

// Get counts
$total_athlete = $pdo->query("
    SELECT COUNT(*) FROM achievements a
    JOIN students s ON a.student_id = s.id
    WHERE s.student_type IN ('athlete', 'both')
")->fetchColumn();

$total_dance = $pdo->query("
    SELECT COUNT(*) FROM achievements a
    JOIN students s ON a.student_id = s.id
    WHERE s.student_type IN ('dancer', 'both')
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - All Achievements</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: #f5f7fb;
        }

        .navbar {
            background: white;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .logo h1 {
            font-size: 24px;
            color: #8B1E3F;
            font-weight: 700;
        }

        .logo h1 i {
            margin-right: 10px;
        }

        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #495057;
            font-weight: 500;
        }

        .nav-links a:hover {
            color: #8B1E3F;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #0a2540;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-section {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background: white;
            color: #495057;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid #e9ecef;
            transition: all 0.3s;
        }

        .filter-btn:hover {
            background: #8B1E3F;
            color: white;
        }

        .filter-btn.active {
            background: #8B1E3F;
            color: white;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-left: 5px solid #8B1E3F;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #0a2540;
        }

        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .achievement-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            border: 1px solid #e9ecef;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .achievement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .card-header {
            height: 8px;
            background: <?php echo $filter == 'athlete' ? '#8B1E3F' : ($filter == 'dance' ? '#FFB347' : 'linear-gradient(90deg, #8B1E3F 0%, #FFB347 100%)'); ?>;
        }

        .card-content {
            padding: 20px;
        }

        .student-name {
            color: #8B1E3F;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .achievement-title {
            font-size: 18px;
            color: #0a2540;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .achievement-desc {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .achievement-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
            font-size: 13px;
        }

        .achievement-date {
            color: #6c757d;
        }

        .medal-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .medal-gold {
            background: #FFD700;
            color: #000;
        }

        .medal-silver {
            background: #C0C0C0;
            color: #000;
        }

        .medal-bronze {
            background: #CD7F32;
            color: #fff;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            color: #8B1E3F;
            text-decoration: none;
            font-weight: 600;
        }

        .footer {
            background: #0a2540;
            color: white;
            padding: 40px;
            text-align: center;
            margin-top: 60px;
        }

        @media (max-width: 1024px) {
            .achievements-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .achievements-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <h1><i class="fas fa-running"></i>TALENTRIX</h1>
        </div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="achievements.php">Achievements</a>
            <a href="index.php#features">Features</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>

        <div class="page-header">
            <h1><i class="fas fa-trophy"></i> All Achievements</h1>
        </div>

        <div class="filter-section">
            <a href="?filter=all" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">All Achievements</a>
            <a href="?filter=athlete" class="filter-btn <?php echo $filter == 'athlete' ? 'active' : ''; ?>">Athlete Achievements</a>
            <a href="?filter=dance" class="filter-btn <?php echo $filter == 'dance' ? 'active' : ''; ?>">Dance Achievements</a>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($achievements); ?></div>
                <div class="stat-label">Total Achievements</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_athlete; ?></div>
                <div class="stat-label">Athlete Achievements</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_dance; ?></div>
                <div class="stat-label">Dance Achievements</div>
            </div>
        </div>

        <div class="achievements-grid">
            <?php if (!empty($achievements)): ?>
                <?php foreach ($achievements as $ach): ?>
                <a href="view_achievement.php?id=<?php echo $ach['id']; ?>" class="achievement-card">
                    <div class="card-header"></div>
                    <div class="card-content">
                        <div class="student-name">
                            <i class="fas fa-user-graduate"></i> 
                            <?php echo htmlspecialchars($ach['first_name'] . ' ' . $ach['last_name']); ?>
                        </div>
                        <h3 class="achievement-title"><?php echo htmlspecialchars($ach['achievement_title']); ?></h3>
                        <p class="achievement-desc">
                            <?php echo htmlspecialchars(substr($ach['achievement_description'] ?? 'No description', 0, 100)) . '...'; ?>
                        </p>
                        <div class="achievement-meta">
                            <span class="achievement-date">
                                <i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($ach['event_date'] ?? $ach['created_at'])); ?>
                            </span>
                            <?php if ($ach['medal_type'] != 'none'): ?>
                                <span class="medal-badge medal-<?php echo $ach['medal_type']; ?>">
                                    <?php 
                                    if($ach['medal_type'] == 'gold') echo '🥇 Gold';
                                    elseif($ach['medal_type'] == 'silver') echo '🥈 Silver';
                                    elseif($ach['medal_type'] == 'bronze') echo '🥉 Bronze';
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 60px;">
                    <i class="fas fa-trophy" style="font-size: 64px; color: #6c757d;"></i>
                    <h3 style="color: #6c757d; margin-top: 20px;">No achievements found</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; 2026 TALENTRIX. All rights reserved. Bohol Island State University.</p>
    </footer>
</body>
</html>