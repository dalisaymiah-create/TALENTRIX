<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Require coach role
requireCoach();

// Get all pending approvals
$pending_sql = "SELECT u.id, u.name, u.email, s.student_id, s.sport, s.position, s.bio, a.created_at
                FROM users u
                JOIN students s ON u.id = s.user_id
                JOIN approvals a ON u.id = a.user_id
                WHERE u.status = 'pending' AND a.status = 'pending'
                ORDER BY a.created_at DESC";
$pending = $conn->query($pending_sql);

// Get approval history
$history_sql = "SELECT u.name, a.status, a.approved_at, a.remarks, a.approved_by
                FROM approvals a
                JOIN users u ON a.user_id = u.id
                WHERE a.status != 'pending'
                ORDER BY a.approved_at DESC
                LIMIT 20";
$history = $conn->query($history_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approvals - Talentrix</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .approval-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #eee;
            padding-bottom: 1rem;
        }
        
        .filter-tab {
            padding: 0.5rem 1.5rem;
            cursor: pointer;
            border-radius: 20px;
            transition: all 0.3s;
        }
        
        .filter-tab.active {
            background: #667eea;
            color: white;
        }
        
        .student-preview {
            display: flex;
            align-items: center;
            gap: 2rem;
            padding: 1.5rem;
            background: #f9f9f9;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .student-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .student-info {
            flex: 1;
        }
        
        .student-info h3 {
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .student-info p {
            color: #666;
            margin: 0.2rem 0;
        }
        
        .student-info small {
            color: #999;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
        }
        
        .btn-approve-large {
            background: #4caf50;
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-reject-large {
            background: #ff4757;
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .badge-approved {
            background: #e8f5e9;
            color: #4caf50;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .badge-rejected {
            background: #ffebee;
            color: #ff4757;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/menu.php'; ?>
    
    <div class="dashboard">
        <div class="header">
            <h2>Manage Student Applications</h2>
            <h1>Approvals</h1>
        </div>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <div class="filter-tab active" onclick="showTab('pending')">Pending (<?php echo $pending->num_rows; ?>)</div>
            <div class="filter-tab" onclick="showTab('history')">History</div>
        </div>
        
        <!-- Pending Approvals -->
        <div id="pending-tab" class="approval-container">
            <h2 style="margin-bottom: 1.5rem;">⏳ Pending Approvals</h2>
            
            <?php if ($pending && $pending->num_rows > 0): ?>
                <?php while($student = $pending->fetch_assoc()): ?>
                    <div class="student-preview">
                        <div class="student-avatar">
                            <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                        </div>
                        <div class="student-info">
                            <h3><?php echo $student['name']; ?></h3>
                            <p>📧 <?php echo $student['email']; ?></p>
                            <p>🎯 <?php echo $student['sport']; ?> - <?php echo $student['position']; ?></p>
                            <p>🆔 <?php echo $student['student_id']; ?></p>
                            <?php if($student['bio']): ?>
                                <small>📝 <?php echo $student['bio']; ?></small>
                            <?php endif; ?>
                            <p><small>Applied: <?php echo date('F d, Y h:i A', strtotime($student['created_at'])); ?></small></p>
                        </div>
                        <div class="action-buttons">
                            <button class="btn-approve-large" onclick="approveStudent(<?php echo $student['id']; ?>)">
                                ✅ Approve
                            </button>
                            <button class="btn-reject-large" onclick="rejectStudent(<?php echo $student['id']; ?>)">
                                ❌ Reject
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem;">
                    <p style="font-size: 3rem; margin-bottom: 1rem;">🎉</p>
                    <h3>No pending approvals!</h3>
                    <p style="color: #666;">All caught up. Great job!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- History Tab (Hidden by default) -->
        <div id="history-tab" class="approval-container" style="display: none;">
            <h2 style="margin-bottom: 1.5rem;">📋 Approval History</h2>
            
            <?php if ($history && $history->num_rows > 0): ?>
                <?php while($item = $history->fetch_assoc()): ?>
                    <div class="history-item">
                        <div>
                            <strong><?php echo $item['name']; ?></strong>
                            <?php if($item['remarks']): ?>
                                <p style="color: #666; font-size: 0.9rem; margin-top: 0.2rem;">
                                    Reason: <?php echo $item['remarks']; ?>
                                </p>
                            <?php endif; ?>
                            <small style="color: #999;">
                                <?php echo date('F d, Y h:i A', strtotime($item['approved_at'])); ?>
                            </small>
                        </div>
                        <span class="<?php echo $item['status'] == 'approved' ? 'badge-approved' : 'badge-rejected'; ?>">
                            <?php echo ucfirst($item['status']); ?>
                        </span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: #999; text-align: center; padding: 2rem;">No approval history yet</p>
            <?php endif; ?>
        </div>
        
        <a href="dashboard.php" class="logout-btn" style="background: #667eea;">← Back to Dashboard</a>
    </div>
    
    <script>
    function showTab(tab) {
        const tabs = document.querySelectorAll('.filter-tab');
        const pending = document.getElementById('pending-tab');
        const history = document.getElementById('history-tab');
        
        tabs.forEach(t => t.classList.remove('active'));
        
        if (tab === 'pending') {
            tabs[0].classList.add('active');
            pending.style.display = 'block';
            history.style.display = 'none';
        } else {
            tabs[1].classList.add('active');
            pending.style.display = 'none';
            history.style.display = 'block';
        }
    }
    
    function approveStudent(userId) {
        if(confirm('Approve this student? They will get access immediately.')) {
            window.location.href = 'approve_student.php?id=' + userId + '&action=approve';
        }
    }
    
    function rejectStudent(userId) {
        let reason = prompt('Please enter reason for rejection:');
        if(reason !== null && reason.trim() !== '') {
            window.location.href = 'approve_student.php?id=' + userId + '&action=reject&reason=' + encodeURIComponent(reason);
        }
    }
    </script>
</body>
</html>