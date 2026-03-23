<?php
// view_achievement.php - View Single Achievement
session_start();
require_once 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, u.email 
    FROM achievements a 
    LEFT JOIN users u ON a.created_by = u.id 
    WHERE a.id = ?
");
$stmt->execute([$id]);
$achievement = $stmt->fetch();

if (!$achievement) {
    header('Location: admin_achievements.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - View Achievement</title>
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
            padding: 40px;
        }

        .container {
            max-width: 800px;
            margin: auto;
        }

        .achievement-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .header {
            padding: 40px;
            background: <?php echo $achievement['category'] == 'athlete' ? '#8B1E3F' : '#f59e0b'; ?>;
            color: white;
            position: relative;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header .category-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
        }

        .content {
            padding: 40px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e9ecef;
        }

        .info-item {
            text-align: center;
        }

        .info-item i {
            font-size: 24px;
            color: #8B1E3F;
            margin-bottom: 10px;
        }

        .info-item .label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .info-item .value {
            font-size: 16px;
            font-weight: 600;
            color: #0a2540;
        }

        .description {
            margin-bottom: 30px;
        }

        .description h3 {
            font-size: 18px;
            color: #0a2540;
            margin-bottom: 15px;
        }

        .description p {
            color: #495057;
            line-height: 1.8;
            font-size: 16px;
        }

        .footer {
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            color: #6c757d;
            font-size: 14px;
        }

        .btn-back {
            display: inline-block;
            padding: 12px 25px;
            background: #8B1E3F;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .btn-back:hover {
            background: #6b152f;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="javascript:history.back()" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back
        </a>

        <div class="achievement-card">
            <div class="header">
                <h1><?php echo htmlspecialchars($achievement['title']); ?></h1>
                <p><?php echo htmlspecialchars($achievement['team'] ?? 'TALENTRIX'); ?></p>
                <span class="category-badge">
                    <i class="fas fa-<?php echo $achievement['category'] == 'athlete' ? 'running' : 'music'; ?>"></i>
                    <?php echo strtoupper($achievement['category']); ?>
                </span>
            </div>

            <div class="content">
                <div class="info-grid">
                    <div class="info-item">
                        <i class="fas fa-calendar"></i>
                        <div class="label">Date Achieved</div>
                        <div class="value"><?php echo date('F d, Y', strtotime($achievement['achievement_date'])); ?></div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-users"></i>
                        <div class="label">Team/Troupe</div>
                        <div class="value"><?php echo htmlspecialchars($achievement['team'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-user"></i>
                        <div class="label">Added By</div>
                        <div class="value"><?php echo htmlspecialchars($achievement['first_name'] . ' ' . $achievement['last_name']); ?></div>
                    </div>
                </div>

                <div class="description">
                    <h3>Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($achievement['description'] ?? 'No description provided.')); ?></p>
                </div>

                <div class="footer">
                    <span><i class="fas fa-clock"></i> Added: <?php echo date('M d, Y', strtotime($achievement['created_at'])); ?></span>
                    <span><i class="fas fa-tag"></i> ID: #<?php echo $achievement['id']; ?></span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>