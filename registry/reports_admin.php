<?php
// reports.php - Analytics & Reports
require_once 'db.php';
requireLogin();

$page_title = 'Analytics & Reports';

// Get statistics
$total_athletes = $pdo->query("SELECT COUNT(*) FROM athletes")->fetchColumn();
$total_coaches = $pdo->query("SELECT COUNT(*) FROM coaches")->fetchColumn();
$total_teams = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$total_events = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$total_achievements = $pdo->query("SELECT COUNT(*) FROM achievements")->fetchColumn();
$total_posts = $pdo->query("SELECT COUNT(*) FROM posts WHERE status='published'")->fetchColumn();

// Events by month
$events_by_month = $pdo->query("
    SELECT 
        DATE_FORMAT(event_date, '%M') as month,
        COUNT(*) as count
    FROM events 
    WHERE YEAR(event_date) = YEAR(CURDATE())
    GROUP BY MONTH(event_date)
    ORDER BY MONTH(event_date)
")->fetchAll();

// Sports distribution
$sports_distribution = $pdo->query("
    SELECT sport, COUNT(*) as count 
    FROM athletes 
    GROUP BY sport
")->fetchAll();

// Recent activity
$recent_events = $pdo->query("SELECT * FROM events ORDER BY event_date DESC LIMIT 5")->fetchAll();
$recent_achievements = $pdo->query("SELECT * FROM achievements ORDER BY date_achieved DESC LIMIT 5")->fetchAll();

include 'header.php';
?>

<!-- Stats Row -->
<div class="stats-row">
    <div class="stat-card">
        <i class="fas fa-users"></i>
        <h3>Total Athletes</h3>
        <div class="number"><?php echo $total_athletes; ?></div>
    </div>
    
    <div class="stat-card">
        <i class="fas fa-chalkboard-user"></i>
        <h3>Total Coaches</h3>
        <div class="number"><?php echo $total_coaches; ?></div>
    </div>
    
    <div class="stat-card">
        <i class="fas fa-people-group"></i>
        <h3>Active Teams</h3>
        <div class="number"><?php echo $total_teams; ?></div>
    </div>
    
    <div class="stat-card">
        <i class="fas fa-calendar-alt"></i>
        <h3>Events</h3>
        <div class="number"><?php echo $total_events; ?></div>
    </div>
    
    <div class="stat-card">
        <i class="fas fa-trophy"></i>
        <h3>Achievements</h3>
        <div class="number"><?php echo $total_achievements; ?></div>
    </div>
    
    <div class="stat-card">
        <i class="fas fa-newspaper"></i>
        <h3>Published Posts</h3>
        <div class="number"><?php echo $total_posts; ?></div>
    </div>
</div>

<!-- Charts Row -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 24px;">
    <!-- Events Chart -->
    <div style="background: white; border-radius: 16px; padding: 24px;">
        <h3>📅 Events This Year</h3>
        <canvas id="eventsChart" style="max-height: 300px;"></canvas>
    </div>
    
    <!-- Sports Distribution Chart -->
    <div style="background: white; border-radius: 16px; padding: 24px;">
        <h3>🏅 Athletes by Sport</h3>
        <canvas id="sportsChart" style="max-height: 300px;"></canvas>
    </div>
</div>

<!-- Recent Activity -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
    <!-- Recent Events -->
    <div style="background: white; border-radius: 16px; padding: 24px;">
        <h3 style="margin-bottom: 20px;">📋 Recent Events</h3>
        <table class="data-table">
            <thead>
                <tr><th>Event</th><th>Date</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recent_events as $event): ?>
                <tr>
                    <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                    <td><?php echo date('M d', strtotime($event['event_date'])); ?></td>
                    <td><?php echo ucfirst($event['status']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent_events)): ?>
                <tr><td colspan="3" style="text-align: center;">No recent events</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Recent Achievements -->
    <div style="background: white; border-radius: 16px; padding: 24px;">
        <h3 style="margin-bottom: 20px;">🏆 Recent Achievements</h3>
        <table class="data-table">
            <thead>
                <tr><th>Achievement</th><th>Recipient</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recent_achievements as $achievement): ?>
                <tr>
                    <td><?php echo htmlspecialchars($achievement['title']); ?></td>
                    <td><?php echo htmlspecialchars($achievement['recipient']); ?></td>
                    <td><?php echo date('M d', strtotime($achievement['date_achieved'])); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent_achievements)): ?>
                <tr><td colspan="3" style="text-align: center;">No recent achievements</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Events Chart
const eventsCtx = document.getElementById('eventsChart').getContext('2d');
new Chart(eventsCtx, {
    type: 'line',
    data: {
        labels: [<?php 
            $months = [];
            foreach ($events_by_month as $event) {
                $months[] = "'" . $event['month'] . "'";
            }
            echo implode(',', $months);
        ?>],
        datasets: [{
            label: 'Number of Events',
            data: [<?php 
                $counts = [];
                foreach ($events_by_month as $event) {
                    $counts[] = $event['count'];
                }
                echo implode(',', $counts);
            ?>],
            borderColor: '#4361ee',
            backgroundColor: 'rgba(67, 97, 238, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'top',
            }
        }
    }
});

// Sports Distribution Chart
const sportsCtx = document.getElementById('sportsChart').getContext('2d');
new Chart(sportsCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php 
            $sports = [];
            foreach ($sports_distribution as $sport) {
                $sports[] = "'" . $sport['sport'] . "'";
            }
            echo implode(',', $sports);
        ?>],
        datasets: [{
            data: [<?php 
                $sport_counts = [];
                foreach ($sports_distribution as $sport) {
                    $sport_counts[] = $sport['count'];
                }
                echo implode(',', $sport_counts);
            ?>],
            backgroundColor: [
                '#4361ee',
                '#7209b7',
                '#ef476f',
                '#ffd166',
                '#06ffa5',
                '#4cc9f0'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});
</script>

<?php include 'footer.php'; ?>