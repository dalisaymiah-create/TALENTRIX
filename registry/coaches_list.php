<?php
// coaches_list.php - View all coaches
session_start();
require_once 'db.php';

// Get sports coaches
$sport_coaches = $pdo->query("
    SELECT u.*, c.primary_sport, c.specialization, c.years_experience, c.date_hired,
           (SELECT COUNT(*) FROM teams WHERE coach_id = c.id) as team_count
    FROM users u
    JOIN coaches c ON u.id = c.user_id
    WHERE u.user_type = 'sport_coach'
    ORDER BY u.first_name, u.last_name
")->fetchAll();

// Get dance coaches
$dance_coaches = $pdo->query("
    SELECT u.*, dc.dance_specialization, dc.years_experience, dc.dance_troupe_name, dc.date_hired,
           (SELECT COUNT(*) FROM dance_troupes WHERE coach_id = dc.user_id) as troupe_count
    FROM users u
    JOIN dance_coaches dc ON u.id = dc.user_id
    WHERE u.user_type = 'dance_coach'
    ORDER BY u.first_name, u.last_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - All Coaches</title>
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            font-size: 32px;
            color: #0a2540;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header h1 i {
            color: #10b981;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #0a2540;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s;
        }

        .back-btn:hover {
            background: #1a365d;
        }

        .search-section {
            margin-bottom: 30px;
        }

        .search-input {
            width: 100%;
            max-width: 400px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-input:focus {
            border-color: #10b981;
            outline: none;
        }

        .coach-type {
            margin: 40px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #10b981;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .coach-type h2 {
            font-size: 24px;
            color: #0a2540;
        }

        .coach-type .badge {
            background: #10b981;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }

        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            border-bottom: 2px solid #10b981;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge-sport {
            background: #10b981;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-dance {
            background: #8B1E3F;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-team {
            background: #0a2540;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }

        .coach-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-view {
            color: #10b981;
            background: none;
            border: 1px solid #10b981;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-view:hover {
            background: #10b981;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #6c757d;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #e9ecef;
        }

        @media (max-width: 1024px) {
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-user-tie"></i> All Coaches & Trainors</h1>
            <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>

        <div class="search-section">
            <input type="text" id="searchInput" class="search-input" placeholder="Search coaches by name or specialization...">
        </div>

        <!-- Sports Coaches Section -->
        <div class="coach-type">
            <h2>Sports Coaches</h2>
            <span class="badge"><?php echo count($sport_coaches); ?> total</span>
        </div>

        <?php if (!empty($sport_coaches)): ?>
        <table id="sportCoachesTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>ID Number</th>
                    <th>Sport</th>
                    <th>Specialization</th>
                    <th>Experience</th>
                    <th>Teams</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sport_coaches as $coach): ?>
                <tr>
                    <td>
                        <span class="coach-avatar"><?php echo strtoupper(substr($coach['first_name'], 0, 1) . substr($coach['last_name'], 0, 1)); ?></span>
                        <?php echo htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($coach['id_number']); ?></td>
                    <td>
                        <span class="badge-sport"><?php echo htmlspecialchars($coach['primary_sport']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($coach['specialization'] ?? 'N/A'); ?></td>
                    <td><?php echo $coach['years_experience']; ?> years</td>
                    <td>
                        <span class="badge-team"><?php echo $coach['team_count']; ?> team(s)</span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="view_coach.php?id=<?php echo $coach['id']; ?>" class="btn-view">View</a>
                            <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'admin'): ?>
                            <a href="edit_coach.php?id=<?php echo $coach['id']; ?>" class="btn-view">Edit</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-user-tie"></i>
            <h3>No Sports Coaches Found</h3>
        </div>
        <?php endif; ?>

        <!-- Dance Trainors Section -->
        <div class="coach-type" style="border-bottom-color: #8B1E3F;">
            <h2>Dance Trainors</h2>
            <span class="badge" style="background: #8B1E3F;"><?php echo count($dance_coaches); ?> total</span>
        </div>

        <?php if (!empty($dance_coaches)): ?>
        <table id="danceCoachesTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>ID Number</th>
                    <th>Specialization</th>
                    <th>Troupe</th>
                    <th>Experience</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dance_coaches as $coach): ?>
                <tr>
                    <td>
                        <span class="coach-avatar" style="background: #8B1E3F;"><?php echo strtoupper(substr($coach['first_name'], 0, 1) . substr($coach['last_name'], 0, 1)); ?></span>
                        <?php echo htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($coach['id_number']); ?></td>
                    <td>
                        <span class="badge-dance"><?php echo htmlspecialchars($coach['dance_specialization']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($coach['dance_troupe_name'] ?? 'N/A'); ?></td>
                    <td><?php echo $coach['years_experience']; ?> years</td>
                    <td>
                        <div class="action-buttons">
                            <a href="view_trainor.php?id=<?php echo $coach['id']; ?>" class="btn-view">View</a>
                            <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'admin'): ?>
                            <a href="edit_trainor.php?id=<?php echo $coach['id']; ?>" class="btn-view">Edit</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-music"></i>
            <h3>No Dance Trainors Found</h3>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            
            // Search in sports coaches table
            const sportRows = document.querySelectorAll('#sportCoachesTable tbody tr');
            sportRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
            
            // Search in dance coaches table
            const danceRows = document.querySelectorAll('#danceCoachesTable tbody tr');
            danceRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>