<?php
// events.php - Manage Events
require_once 'db.php';
requireLogin();

$page_title = 'Events Management';

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO events (event_name, event_date, location, description, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['event_name'],
                    $_POST['event_date'],
                    $_POST['location'],
                    $_POST['description'],
                    $_POST['status']
                ]);
                $success = "Event added successfully!";
                break;
                
            case 'edit':
                $stmt = $pdo->prepare("UPDATE events SET event_name=?, event_date=?, location=?, description=?, status=? WHERE id=?");
                $stmt->execute([
                    $_POST['event_name'],
                    $_POST['event_date'],
                    $_POST['location'],
                    $_POST['description'],
                    $_POST['status'],
                    $_POST['id']
                ]);
                $success = "Event updated successfully!";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM events WHERE id=?");
                $stmt->execute([$_POST['id']]);
                $success = "Event deleted successfully!";
                break;
        }
    }
}

// Get all events
$events = $pdo->query("SELECT * FROM events ORDER BY event_date DESC")->fetchAll();

include 'header.php';
?>

<?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 20px;">
    <button class="btn btn-primary" onclick="openModal('addEventModal')">
        <i class="fas fa-plus"></i> Create New Event
    </button>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Event Name</th>
            <th>Date</th>
            <th>Location</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($events as $event): ?>
        <tr>
            <td><?php echo $event['id']; ?></td>
            <td><?php echo htmlspecialchars($event['event_name']); ?></td>
            <td><?php echo date('F d, Y', strtotime($event['event_date'])); ?></td>
            <td><?php echo htmlspecialchars($event['location']); ?></td>
            <td>
                <span class="badge badge-<?php echo $event['status'] == 'upcoming' ? 'primary' : ($event['status'] == 'ongoing' ? 'warning' : 'success'); ?>">
                    <?php echo ucfirst($event['status']); ?>
                </span>
            </td>
            <td>
                <button class="btn btn-primary btn-sm" onclick="editEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="deleteEvent(<?php echo $event['id']; ?>)">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        
        <?php if (empty($events)): ?>
        <tr>
            <td colspan="6" style="text-align: center;">No events found</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Add Event Modal -->
<div id="addEventModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create New Event</h3>
            <button class="close-modal" onclick="closeModal('addEventModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Event Name</label>
                    <input type="text" name="event_name" required>
                </div>
                <div class="form-group">
                    <label>Event Date</label>
                    <input type="datetime-local" name="event_date" required>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="upcoming">Upcoming</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('addEventModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Event</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Event Modal -->
<div id="editEventModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Event</h3>
            <button class="close-modal" onclick="closeModal('editEventModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Event Name</label>
                    <input type="text" name="event_name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Event Date</label>
                    <input type="datetime-local" name="event_date" id="edit_date" required>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" id="edit_location" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" required>
                        <option value="upcoming">Upcoming</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('editEventModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Event</button>
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

function editEvent(event) {
    document.getElementById('edit_id').value = event.id;
    document.getElementById('edit_name').value = event.event_name;
    document.getElementById('edit_date').value = event.event_date.replace(' ', 'T');
    document.getElementById('edit_location').value = event.location;
    document.getElementById('edit_description').value = event.description;
    document.getElementById('edit_status').value = event.status;
    openModal('editEventModal');
}

function deleteEvent(id) {
    if (confirm('Are you sure you want to delete this event?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php include 'footer.php'; ?>