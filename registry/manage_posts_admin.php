<?php
// manage_posts.php - Manage News/Posts
require_once 'db.php';
requireLogin();

$page_title = 'Manage Posts';

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO posts (title, content, author, status, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['content'],
                    $_SESSION['user_id'],
                    $_POST['status']
                ]);
                $success = "Post created successfully!";
                break;
                
            case 'edit':
                $stmt = $pdo->prepare("UPDATE posts SET title=?, content=?, status=? WHERE id=?");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['content'],
                    $_POST['status'],
                    $_POST['id']
                ]);
                $success = "Post updated successfully!";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM posts WHERE id=?");
                $stmt->execute([$_POST['id']]);
                $success = "Post deleted successfully!";
                break;
                
            case 'publish':
                $stmt = $pdo->prepare("UPDATE posts SET status='published', published_at=NOW() WHERE id=?");
                $stmt->execute([$_POST['id']]);
                $success = "Post published successfully!";
                break;
        }
    }
}

// Get all posts
$posts = $pdo->query("SELECT p.*, u.username as author_name FROM posts p LEFT JOIN users u ON p.author = u.id ORDER BY p.created_at DESC")->fetchAll();

include 'header.php';
?>

<?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 20px;">
    <button class="btn btn-primary" onclick="openModal('addPostModal')">
        <i class="fas fa-plus"></i> Create New Post
    </button>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Author</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($posts as $post): ?>
        <tr>
            <td><?php echo $post['id']; ?></td>
            <td><strong><?php echo htmlspecialchars($post['title']); ?></strong></td>
            <td><?php echo htmlspecialchars($post['author_name'] ?? 'Unknown'); ?></td>
            <td>
                <span class="badge badge-<?php echo $post['status'] == 'published' ? 'success' : ($post['status'] == 'draft' ? 'warning' : 'secondary'); ?>">
                    <?php echo ucfirst($post['status']); ?>
                </span>
            </td>
            <td><?php echo date('M d, Y', strtotime($post['created_at'])); ?></td>
            <td>
                <button class="btn btn-primary btn-sm" onclick="editPost(<?php echo htmlspecialchars(json_encode($post)); ?>)">
                    <i class="fas fa-edit"></i>
                </button>
                <?php if ($post['status'] != 'published'): ?>
                <button class="btn btn-success btn-sm" onclick="publishPost(<?php echo $post['id']; ?>)">
                    <i class="fas fa-check"></i>
                </button>
                <?php endif; ?>
                <button class="btn btn-danger btn-sm" onclick="deletePost(<?php echo $post['id']; ?>)">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        
        <?php if (empty($posts)): ?>
        <tr>
            <td colspan="6" style="text-align: center;">No posts found</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Add Post Modal -->
<div id="addPostModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Create New Post</h3>
            <button class="close-modal" onclick="closeModal('addPostModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" rows="8" required></textarea>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="draft">Draft</option>
                        <option value="published">Publish Now</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('addPostModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Post</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Post Modal -->
<div id="editPostModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Edit Post</h3>
            <button class="close-modal" onclick="closeModal('editPostModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" id="edit_title" required>
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" id="edit_content" rows="8" required></textarea>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" required>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('editPostModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Post</button>
            </div>
        </form>
    </div>
</div>

<!-- Publish Form -->
<form id="publishForm" method="POST" action="">
    <input type="hidden" name="action" value="publish">
    <input type="hidden" name="id" id="publish_id">
</form>

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

function editPost(post) {
    document.getElementById('edit_id').value = post.id;
    document.getElementById('edit_title').value = post.title;
    document.getElementById('edit_content').value = post.content;
    document.getElementById('edit_status').value = post.status;
    openModal('editPostModal');
}

function publishPost(id) {
    if (confirm('Publish this post? It will be visible to the public.')) {
        document.getElementById('publish_id').value = id;
        document.getElementById('publishForm').submit();
    }
}

function deletePost(id) {
    if (confirm('Are you sure you want to delete this post?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php include 'footer.php'; ?>