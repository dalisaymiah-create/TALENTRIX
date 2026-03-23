<?php
// achievements.php - Manage Achievements
require_once 'db.php';
requireLogin();

$page_title = 'Achievements';

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO achievements (title, recipient, event_name, date_achieved, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['recipient'],
                    $_POST['event_name'],
                    $_POST['date_achieved'],
                    $_POST['description']
                ]);
                $success = "Achievement added successfully!";
                break;
                
            case 'edit':
                $stmt = $pdo->prepare("UPDATE achievements SET title=?, recipient=?, event_name=?, date_achieved=?, description=? WHERE id=?");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['recipient'],
                    $_POST['event_name'],
                    $_POST['date_achieved'],
                    $_POST['description'],
                    $_POST['id']
                ]);
                $success = "Achievement updated successfully!";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM achievements WHERE id=?");
                $stmt->execute([$_POST['id']]);
                $success = "Achievement deleted successfully!";
                break;
        }
    }
}

// Get all achievements
$achievements = $pdo->query("SELECT * FROM achievements ORDER BY date_achieved DESC")->fetchAll();

include 'header.php';
?>

<?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 20px;">
    <button class="btn btn-primary" onclick="openModal('addAchievementModal')">
        <i class="fas fa-plus"></i> Add Achievement
    </button>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Achievement</th>
            <th>Recipient</th>
            <th>Event</th>
            <th>Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($achievements as $achievement): ?>
        <tr>
            <td><?php echo $achievement['id']; ?></td>
            <td><strong><?php echo htmlspecialchars($achievement['title']); ?></strong></td>
            <td><?php echo htmlspecialchars($achievement['recipient']); ?></td>
            <td><?php echo htmlspecialchars($achievement['event_name']); ?></td>
            <td><?php echo date('F d, Y', strtotime($achievement['date_achieved'])); ?></td>
            <td>
                <button class="btn btn-primary btn-sm" onclick="editAchievement(<?php echo htmlspecialchars(json_encode($achievement)); ?>)">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="deleteAchievement(<?php echo $achievement['id']; ?>)">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        
        <?php if (empty($achievements)): ?>
        <tr>
            <td colspan="6" style="text-align: center;">No achievements recorded</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Add Achievement Modal -->
<div id="addAchievementModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Achievement</h3>
            <button class="close-modal" onclick="closeModal('addAchievementModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Achievement Title</label>
                    <input type="text" name="title" placeholder="e.g., Gold Medal - 100m Sprint" required>
                </div>
                <div class="form-group">
                    <label>Recipient (Athlete/Team)</label>
                    <input type="text" name="recipient" required>
                </div>
                <div class="form-group">
                    <label>Event Name</label>
                    <input type="text" name="event_name" required>
                </div>
                <div class="form-group">
                    <label>Date Achieved</label>
                    <input type="date" name="date_achieved" required>
                </div>
                <div class="form-group">
                    <label>Description (Optional)</label>
                    <textarea name="description" rows="3" placeholder="Additional details about this achievement..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('addAchievementModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Achievement</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Achievement Modal -->
<div id="editAchievementModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Achievement</h3>
            <button class="close-modal" onclick="closeModal('editAchievementModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Achievement Title</label>
                    <input type="text" name="title" id="edit_title" required>
                </div>
                <div class="form-group">
                    <label>Recipient (Athlete/Team)</label>
                    <input type="text" name="recipient" id="edit_recipient" required>
                </div>
                <div class="form-group">
                    <label>Event Name</label>
                    <input type="text" name="event_name" id="edit_event" required>
                </div>
                <div class="form-group">
                    <label>Date Achieved</label>
                    <input type="date" name="date_achieved" id="edit_date" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('editAchievementModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Achievement</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" action="">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function editAchievement(achievement) {
    document.getElementById('edit_id').value = achievement.id;
    document.getElementById('edit_title').value = achievement.title;
    document.getElementById('edit_recipient').value = achievement.recipient;
    document.getElementById('edit_event').value = achievement.event_name;
    document.getElementById('edit_date').value = achievement.date_achieved;
    document.getElementById('edit_description').value = achievement.description;
    openModal('editAchievementModal');
}

function deleteAchievement(id) {
    if (confirm('Are you sure you want to delete this achievement?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php include 'footer.php'; ?>