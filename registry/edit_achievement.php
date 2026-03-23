<?php
// edit_achievement.php - Edit Achievement
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get achievement data
$stmt = $pdo->prepare("SELECT * FROM achievements WHERE id = ?");
$stmt->execute([$id]);
$achievement = $stmt->fetch();

if (!$achievement) {
    header('Location: admin_achievements.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $team = trim($_POST['team'] ?? '');
    $category = $_POST['category'] ?? 'athlete';
    $achievement_date = $_POST['achievement_date'] ?? date('Y-m-d');
    
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE achievements 
                SET title = ?, description = ?, team = ?, category = ?, achievement_date = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$title, $description, $team, $category, $achievement_date, $id]);
            
            $_SESSION['success_message'] = 'Achievement updated successfully!';
            header('Location: admin_achievements.php');
            exit();
        } catch (Exception $e) {
            $error = 'Error updating achievement: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Edit Achievement</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .form-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 600px;
            overflow: hidden;
        }

        .header {
            background: #8B1E3F;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header h1 i {
            margin-right: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .form-content {
            padding: 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: #495057;
        }

        .form-group label i {
            margin-right: 8px;
            color: #8B1E3F;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #8B1E3F;
            box-shadow: 0 0 0 3px rgba(139, 30, 63, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn-submit {
            background: #8B1E3F;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            background: #6b152f;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .required::after {
            content: " *";
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="header">
            <h1><i class="fas fa-edit"></i>EDIT ACHIEVEMENT</h1>
            <p>Update achievement details</p>
        </div>

        <div class="form-content">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="required"><i class="fas fa-tag"></i> Achievement Title</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($achievement['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" rows="4"><?php echo htmlspecialchars($achievement['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-users"></i> Team/Troupe Name</label>
                    <input type="text" name="team" value="<?php echo htmlspecialchars($achievement['team']); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="required"><i class="fas fa-calendar"></i> Achievement Date</label>
                        <input type="date" name="achievement_date" value="<?php echo $achievement['achievement_date']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="required"><i class="fas fa-filter"></i> Category</label>
                        <select name="category" required>
                            <option value="athlete" <?php echo $achievement['category'] == 'athlete' ? 'selected' : ''; ?>>🏃 Athlete Achievement</option>
                            <option value="dance" <?php echo $achievement['category'] == 'dance' ? 'selected' : ''; ?>>💃 Dance Achievement</option>
                        </select>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Update Achievement
                    </button>
                    <a href="admin_achievements.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>