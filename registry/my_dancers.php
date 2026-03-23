<?php
require_once 'db.php';

$search = $_GET['search'] ?? '';

if($search){
    $stmt = $pdo->prepare("
        SELECT s.id, u.first_name, u.last_name, u.email, u.id_number, s.dance_role
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE u.id_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?
        ORDER BY u.first_name
    ");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
}else{
    $stmt = $pdo->query("
        SELECT s.id, u.first_name, u.last_name, u.email, u.id_number, s.dance_role
        FROM students s
        JOIN users u ON s.user_id = u.id
        ORDER BY u.first_name
    ");
}

$dancers = $stmt->fetchAll();
?>

<style>
    .dancers-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .dancers-header h2 {
        color: #8B1E3F;
        font-size: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .dancers-header h2 i {
        color: #FFB347;
    }
    
    .search-form {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .search-form input {
        padding: 12px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        width: 280px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .search-form input:focus {
        border-color: #FFB347;
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 179, 71, 0.1);
    }
    
    .search-form button {
        padding: 12px 25px;
        background: linear-gradient(135deg, #8B1E3F 0%, #6b152f 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }
    
    .search-form button:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(139, 30, 63, 0.3);
    }
    
    .reset-btn {
        padding: 12px 25px;
        background: #e2e8f0;
        color: #475569;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .reset-btn:hover {
        background: #cbd5e0;
    }
    
    .dancers-table-container {
        overflow-x: auto;
        border-radius: 15px;
        border: 1px solid #eef2f6;
    }
    
    .dancers-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }
    
    .dancers-table th {
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
    
    .dancers-table td {
        padding: 15px 12px;
        border-bottom: 1px solid #eef2f6;
        color: #1a2639;
        font-size: 14px;
    }
    
    .dancers-table tr:hover td {
        background-color: #fef3c7;
    }
    
    .dancer-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .dancer-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #8B1E3F 0%, #6b152f 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 14px;
    }
    
    .dancer-name {
        font-weight: 600;
        color: #1a2639;
    }
    
    .role-badge {
        background: #fef3c7;
        color: #92400e;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }
    
    .remove-btn {
        background: #ef4444;
        color: white;
        padding: 8px 15px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s;
    }
    
    .remove-btn:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
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
    
    .stats-badge {
        background: #8B1E3F;
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        margin-left: 10px;
    }
</style>

<div class="card">
    <div class="dancers-header">
        <h2>
            <i class="fas fa-users"></i> 
            My Dancers
            <span class="stats-badge"><?php echo count($dancers); ?> total</span>
        </h2>
        
        <div class="search-form">
            <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="hidden" name="page" value="dancers">
                <input type="text" name="search" 
                       placeholder="Search by name or ID number..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if($search): ?>
                <a href="?page=dancers" class="reset-btn">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="dancers-table-container">
        <table class="dancers-table">
            <thead>
                <tr>
                    <th>Dancer</th>
                    <th>ID Number</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($dancers): ?>
                    <?php foreach($dancers as $d): ?>
                    <tr>
                        <td>
                            <div class="dancer-info">
                                <div class="dancer-avatar">
                                    <?php echo strtoupper(substr($d['first_name'], 0, 1) . substr($d['last_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="dancer-name"><?php echo htmlspecialchars($d['first_name'] . " " . $d['last_name']); ?></div>
                                    <small style="color: #94a3b8;">Joined: <?php echo date('M Y'); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><span style="font-family: monospace;"><?php echo htmlspecialchars($d['id_number']); ?></span></td>
                        <td><?php echo htmlspecialchars($d['email']); ?></td>
                        <td>
                            <span class="role-badge">
                                <i class="fas fa-star" style="font-size: 10px;"></i>
                                <?php echo htmlspecialchars($d['dance_role'] ?? 'Dancer'); ?>
                            </span>
                        </td>
                        <td>
                            <a href="remove_dancer.php?id=<?php echo $d['id']; ?>" 
                               class="remove-btn"
                               onclick="return confirm('Are you sure you want to remove this dancer?')">
                                <i class="fas fa-user-minus"></i> Remove
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="fas fa-user-slash"></i>
                                <p>No dancers found</p>
                                <?php if($search): ?>
                                <small>Try a different search term</small>
                                <?php else: ?>
                                <small>Click "Add Dancer" to add your first dancer</small>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>