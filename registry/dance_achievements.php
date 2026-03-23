<?php
require_once 'db.php';

// Get achievements
$stmt = $pdo->query("
    SELECT * FROM dance_achievements 
    ORDER BY achievement_date DESC
");
$achievements = $stmt->fetchAll();

// Get dancers for dropdown
$dancers = $pdo->query("
    SELECT s.id, u.first_name, u.last_name 
    FROM students s
    JOIN users u ON s.user_id = u.id
    ORDER BY u.first_name
")->fetchAll();
?>

<style>
    .achievements-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .achievements-header h2 {
        color: #8B1E3F;
        font-size: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .achievements-header h2 i {
        color: #FFB347;
    }
    
    .stats-badge {
        background: #8B1E3F;
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        margin-left: 10px;
    }
    
    .add-achievement-form {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 30px;
        border-left: 5px solid #FFB347;
    }
    
    .add-achievement-form h3 {
        color: #8B1E3F;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 18px;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
        color: #475569;
        font-size: 13px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: #FFB347;
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 179, 71, 0.1);
    }
    
    .add-btn {
        background: #8B1E3F;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
        margin-top: 10px;
    }
    
    .add-btn:hover {
        background: #6b152f;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(139, 30, 63, 0.3);
    }
    
    .achievements-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .achievement-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        border: 1px solid #eef2f6;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }
    
    .achievement-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    }
    
    .achievement-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #8B1E3F, #FFB347);
    }
    
    .achievement-icon {
        font-size: 40px;
        margin-bottom: 15px;
        text-align: center;
    }
    
    .achievement-title {
        font-size: 18px;
        font-weight: 700;
        color: #1a2639;
        margin-bottom: 10px;
        text-align: center;
    }
    
    .achievement-description {
        color: #64748b;
        font-size: 14px;
        margin-bottom: 15px;
        line-height: 1.5;
        text-align: center;
    }
    
    .achievement-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #eef2f6;
    }
    
    .achievement-date {
        display: flex;
        align-items: center;
        gap: 5px;
        color: #94a3b8;
        font-size: 12px;
    }
    
    .achievement-date i {
        color: #FFB347;
    }
    
    .achievement-medal {
        background: #fef3c7;
        color: #92400e;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
        background: #f8fafc;
        border-radius: 15px;
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
    
    .recent-badge {
        background: #10b981;
        color: white;
        padding: 3px 8px;
        border-radius: 15px;
        font-size: 10px;
        font-weight: 600;
        margin-left: 10px;
    }
</style>

<div class="card">
    <div class="achievements-header">
        <h2>
            <i class="fas fa-trophy"></i> 
            Dance Achievements
            <span class="stats-badge"><?php echo count($achievements); ?> total</span>
        </h2>
    </div>

    <!-- Add Achievement Form -->
    <div class="add-achievement-form">
        <h3><i class="fas fa-plus-circle"></i> Add New Achievement</h3>
        <form method="POST" action="add_achievement.php">
            <div class="form-grid">
                <div class="form-group">
                    <label>Achievement Title</label>
                    <input type="text" name="title" placeholder="e.g., Champion - Dance Competition" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" placeholder="Brief description" required>
                </div>
                <div class="form-group">
                    <label>Achievement Date</label>
                    <input type="date" name="date" required>
                </div>
            </div>
            <button type="submit" class="add-btn">
                <i class="fas fa-medal"></i> Add Achievement
            </button>
        </form>
    </div>

    <!-- Achievements Grid -->
    <?php if($achievements): ?>
    <div class="achievements-grid">
        <?php foreach($achievements as $index => $a): ?>
        <div class="achievement-card">
            <div class="achievement-icon">
                <?php 
                $icons = ['🏆', '🥇', '🥈', '🥉', '🎭', '💃', '🕺', '🌟', '✨', '⭐'];
                echo $icons[$index % count($icons)];
                ?>
            </div>
            <div class="achievement-title">
                <?php echo htmlspecialchars($a['title']); ?>
                <?php if($index < 3): ?>
                <span class="recent-badge">RECENT</span>
                <?php endif; ?>
            </div>
            <div class="achievement-description">
                <?php echo htmlspecialchars($a['description']); ?>
            </div>
            <div class="achievement-footer">
                <div class="achievement-date">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('M d, Y', strtotime($a['achievement_date'])); ?>
                </div>
                <div class="achievement-medal">
                    <i class="fas fa-medal"></i> Achievement
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-medal"></i>
        <p>No achievements yet</p>
        <small>Add your first achievement using the form above</small>
    </div>
    <?php endif; ?>
</div>