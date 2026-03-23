<?php
// athletes_list.php - View all athletes
session_start();
require_once 'db.php';

// Get all athletes
$athletes = $pdo->query("
    SELECT u.*, s.student_type, s.primary_sport, s.primary_position, s.jersey_number,
           s.athlete_category, s.year_level, s.college, s.course
    FROM users u
    JOIN students s ON u.id = s.user_id
    WHERE s.student_type IN ('athlete', 'both')
    ORDER BY u.first_name, u.last_name
")->fetchAll();

// Get stats
$total_athletes = count($athletes);
$sports = $pdo->query("SELECT DISTINCT primary_sport FROM students WHERE primary_sport IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - All Athletes</title>
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
            color: #8B1E3F;
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

        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #8B1E3F 0%, #6b152f 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .search-section {
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-input:focus {
            border-color: #8B1E3F;
            outline: none;
        }

        .filter-select {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            border-bottom: 2px solid #8B1E3F;
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
            background: #8B1E3F;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .badge-category {
            background: #10b981;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
        }

        .jersey-badge {
            background: #0a2540;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-view {
            color: #8B1E3F;
            background: none;
            border: 1px solid #8B1E3F;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-view:hover {
            background: #8B1E3F;
            color: white;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a {
            padding: 8px 15px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            color: #495057;
            text-decoration: none;
        }

        .pagination a:hover {
            background: #8B1E3F;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #e9ecef;
        }

        @media (max-width: 1024px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
            
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
            <h1><i class="fas fa-running"></i> All Athletes</h1>
            <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_athletes; ?></div>
                <div class="stat-label">Total Athletes</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="stat-number"><?php echo count($sports); ?></div>
                <div class="stat-label">Sports</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #FFB347 0%, #f39c12 100%);">
                <div class="stat-number"><?php echo $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn(); ?></div>
                <div class="stat-label">Teams</div>
            </div>
        </div>

        <div class="search-section">
            <input type="text" id="searchInput" class="search-input" placeholder="Search by name, ID, or sport...">
            <select id="sportFilter" class="filter-select">
                <option value="">All Sports</option>
                <?php foreach($sports as $sport): ?>
                    <option value="<?php echo htmlspecialchars($sport); ?>"><?php echo htmlspecialchars($sport); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!empty($athletes)): ?>
        <table id="athletesTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>ID Number</th>
                    <th>Sport</th>
                    <th>Position</th>
                    <th>Category</th>
                    <th>Jersey</th>
                    <th>Year/Course</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($athletes as $athlete): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']); ?></strong>
                    </td>
                    <td><?php echo htmlspecialchars($athlete['id_number']); ?></td>
                    <td>
                        <span class="badge-sport"><?php echo htmlspecialchars($athlete['primary_sport'] ?? 'N/A'); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($athlete['primary_position'] ?? 'N/A'); ?></td>
                    <td>
                        <?php if ($athlete['athlete_category']): ?>
                            <span class="badge-category"><?php echo ucfirst($athlete['athlete_category']); ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($athlete['jersey_number']): ?>
                            <span class="jersey-badge">#<?php echo $athlete['jersey_number']; ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $year = $athlete['year_level'] ? $athlete['year_level'] . ' Year' : '';
                        $course = $athlete['course'] ?? '';
                        echo htmlspecialchars($year . ($year && $course ? ' - ' : '') . $course);
                        ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="view_athlete.php?id=<?php echo $athlete['id']; ?>" class="btn-view">View</a>
                            <?php if(isset($_SESSION['user_id']) && ($_SESSION['user_type'] == 'admin' || $_SESSION['user_type'] == 'athletics_admin')): ?>
                            <a href="edit_athlete.php?id=<?php echo $athlete['id']; ?>" class="btn-view">Edit</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-running"></i>
            <h3>No Athletes Found</h3>
            <p>There are no registered athletes in the system yet.</p>
        </div>
        <?php endif; ?>

        <div class="pagination">
            <a href="#">1</a>
            <a href="#">2</a>
            <a href="#">3</a>
            <a href="#">Next →</a>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#athletesTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Filter by sport
        document.getElementById('sportFilter').addEventListener('change', function() {
            const sport = this.value.toLowerCase();
            const rows = document.querySelectorAll('#athletesTable tbody tr');
            
            rows.forEach(row => {
                if (!sport) {
                    row.style.display = '';
                    return;
                }
                
                const sportCell = row.cells[2].textContent.toLowerCase();
                row.style.display = sportCell.includes(sport) ? '' : 'none';
            });
        });
    </script>
</body>
</html>