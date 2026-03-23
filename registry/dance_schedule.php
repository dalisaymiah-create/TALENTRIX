<?php
require_once 'db.php';

// Get filter
$filter = $_GET['filter'] ?? 'upcoming';

if($filter == 'upcoming'){
    $stmt = $pdo->query("
        SELECT * FROM upcoming_events
        WHERE event_type='dance' AND event_date >= CURDATE()
        ORDER BY event_date ASC, event_time ASC
    ");
} elseif($filter == 'past'){
    $stmt = $pdo->query("
        SELECT * FROM upcoming_events
        WHERE event_type='dance' AND event_date < CURDATE()
        ORDER BY event_date DESC
    ");
} else {
    $stmt = $pdo->query("
        SELECT * FROM upcoming_events
        WHERE event_type='dance'
        ORDER BY event_date DESC
    ");
}

$schedules = $stmt->fetchAll();

// Get counts
$upcoming_count = $pdo->query("SELECT COUNT(*) FROM upcoming_events WHERE event_type='dance' AND event_date >= CURDATE()")->fetchColumn();
$past_count = $pdo->query("SELECT COUNT(*) FROM upcoming_events WHERE event_type='dance' AND event_date < CURDATE()")->fetchColumn();
?>

<style>
    .schedule-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .schedule-header h2 {
        color: #8B1E3F;
        font-size: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .schedule-header h2 i {
        color: #FFB347;
    }
    
    .filter-tabs {
        display: flex;
        gap: 10px;
        background: #f1f5f9;
        padding: 5px;
        border-radius: 10px;
    }
    
    .filter-tab {
        padding: 8px 20px;
        border-radius: 8px;
        text-decoration: none;
        color: #64748b;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .filter-tab.active {
        background: #8B1E3F;
        color: white;
    }
    
    .filter-tab:hover:not(.active) {
        background: #e2e8f0;
    }
    
    .filter-tab i {
        font-size: 12px;
    }
    
    .stats-cards {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-mini-card {
        background: white;
        border-radius: 12px;
        padding: 15px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        border: 1px solid #eef2f6;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .stat-mini-icon {
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
    
    .stat-mini-content h4 {
        font-size: 20px;
        font-weight: 700;
        color: #1a2639;
        margin-bottom: 2px;
    }
    
    .stat-mini-content p {
        font-size: 12px;
        color: #64748b;
    }
    
    .schedule-table-container {
        overflow-x: auto;
        border-radius: 15px;
        border: 1px solid #eef2f6;
    }
    
    .schedule-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }
    
    .schedule-table th {
        background: #f8fafc;
        padding: 15px 12px;
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #FFB347;
    }
    
    .schedule-table td {
        padding: 15px 12px;
        border-bottom: 1px solid #eef2f6;
        color: #1a2639;
        font-size: 14px;
    }
    
    .schedule-table tr:hover td {
        background-color: #fef3c7;
    }
    
    .event-title {
        font-weight: 600;
        color: #1a2639;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .event-title i {
        color: #FFB347;
    }
    
    .date-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #f1f5f9;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
    }
    
    .date-badge i {
        color: #8B1E3F;
    }
    
    .time-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #fef3c7;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
        color: #92400e;
    }
    
    .location-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .location-badge i {
        color: #FFB347;
    }
    
    .past-event {
        opacity: 0.7;
        background-color: #f8fafc;
    }
    
    .past-event .date-badge {
        background: #e2e8f0;
        color: #64748b;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }
    
    .empty-state i {
        font-size: 60px;
        margin-bottom: 20px;
        color: #e2e8f0;
    }
    
    .empty-state p {
        font-size: 16px;
        margin-bottom: 10px;
    }
    
    .schedule-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
    }
    
    .add-event-btn {
        background: #8B1E3F;
        color: white;
        padding: 12px 25px;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
        text-decoration: none;
    }
    
    .add-event-btn:hover {
        background: #6b152f;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(139, 30, 63, 0.3);
    }
</style>

<div class="card">
    <div class="schedule-header">
        <h2>
            <i class="fas fa-calendar-alt"></i> 
            Dance Schedule
        </h2>
        
        <div class="filter-tabs">
            <a href="?page=schedule&filter=upcoming" class="filter-tab <?php echo $filter == 'upcoming' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> Upcoming (<?php echo $upcoming_count; ?>)
            </a>
            <a href="?page=schedule&filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> All
            </a>
            <a href="?page=schedule&filter=past" class="filter-tab <?php echo $filter == 'past' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Past (<?php echo $past_count; ?>)
            </a>
        </div>
    </div>

    <div class="stats-cards">
        <div class="stat-mini-card">
            <div class="stat-mini-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-mini-content">
                <h4><?php echo $upcoming_count; ?></h4>
                <p>Upcoming Events</p>
            </div>
        </div>
        <div class="stat-mini-card">
            <div class="stat-mini-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-mini-content">
                <h4><?php echo count($schedules); ?></h4>
                <p>Total Events</p>
            </div>
        </div>
        <div class="stat-mini-card">
            <div class="stat-mini-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="stat-mini-content">
                <h4><?php 
                    $locations = array_unique(array_column($schedules, 'location'));
                    echo count($locations); 
                ?></h4>
                <p>Locations</p>
            </div>
        </div>
    </div>

    <div class="schedule-table-container">
        <table class="schedule-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Location</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if($schedules): ?>
                    <?php foreach($schedules as $s): 
                        $is_past = strtotime($s['event_date']) < strtotime(date('Y-m-d'));
                        $is_today = $s['event_date'] == date('Y-m-d');
                    ?>
                    <tr class="<?php echo $is_past ? 'past-event' : ''; ?>">
                        <td>
                            <div class="event-title">
                                <i class="fas <?php echo $is_today ? 'fa-star' : 'fa-calendar-day'; ?>"></i>
                                <?php echo htmlspecialchars($s['event_title']); ?>
                            </div>
                        </td>
                        <td>
                            <span class="date-badge">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M d, Y', strtotime($s['event_date'])); ?>
                                <?php if($is_today): ?>
                                <span style="background: #10b981; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: 5px;">TODAY</span>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <span class="time-badge">
                                <i class="fas fa-clock"></i>
                                <?php echo date('h:i A', strtotime($s['event_time'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="location-badge">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($s['location']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if($is_past): ?>
                                <span style="background: #e2e8f0; color: #64748b; padding: 4px 10px; border-radius: 20px; font-size: 12px;">
                                    <i class="fas fa-check-circle"></i> Completed
                                </span>
                            <?php elseif($is_today): ?>
                                <span style="background: #10b981; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px;">
                                    <i class="fas fa-hourglass-start"></i> Today
                                </span>
                            <?php else: ?>
                                <span style="background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 20px; font-size: 12px;">
                                    <i class="fas fa-clock"></i> Upcoming
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No scheduled events</p>
                                <small>Click "Add Event" to schedule your first performance</small>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="schedule-actions">
        <a href="#" onclick="openScheduleModal()" class="add-event-btn">
            <i class="fas fa-plus-circle"></i> Add New Event
        </a>
    </div>
</div>