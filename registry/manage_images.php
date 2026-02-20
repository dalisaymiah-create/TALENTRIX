<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Create table for images if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS homepage_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    image_title VARCHAR(255),
    image_category VARCHAR(50),
    image_date DATE,
    image_url VARCHAR(500),
    display_order INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_image') {
        $target_dir = "uploads/";
        
        // Create uploads directory if not exists
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES["image_file"]["name"]);
        $target_file = $target_dir . $file_name;
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Check if image file is actual image
        $check = getimagesize($_FILES["image_file"]["tmp_name"]);
        if($check !== false) {
            $uploadOk = 1;
        } else {
            $error = "File is not an image.";
            $uploadOk = 0;
        }
        
        // Allow certain file formats
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }
        
        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["image_file"]["tmp_name"], $target_file)) {
                // Save to database
                $stmt = $pdo->prepare("INSERT INTO homepage_images (image_title, image_category, image_date, image_url, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['image_title'],
                    $_POST['image_category'],
                    $_POST['image_date'],
                    $target_file,
                    $_SESSION['user_id']
                ]);
                $success = "Image uploaded successfully!";
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        }
    }
    
    // Delete image
    if ($_POST['action'] === 'delete_image') {
        // Get image URL first
        $stmt = $pdo->prepare("SELECT image_url FROM homepage_images WHERE id = ?");
        $stmt->execute([$_POST['image_id']]);
        $image = $stmt->fetch();
        
        // Delete file from server
        if ($image && file_exists($image['image_url'])) {
            unlink($image['image_url']);
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM homepage_images WHERE id = ?");
        $stmt->execute([$_POST['image_id']]);
        $success = "Image deleted successfully!";
    }
}

// Get all images
$images = $pdo->query("SELECT * FROM homepage_images ORDER BY created_at DESC")->fetchAll();

// Get current user
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Manage Images</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .image-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .image-preview {
            height: 200px;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .image-category {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .category-athletics { background: #8B1E3F; color: white; }
        .category-dance { background: #FFB347; color: #1e3e5c; }
        
        .image-info {
            padding: 20px;
        }
        
        .upload-form {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 40px;
        }
        
        .file-input {
            border: 2px dashed #cbd5e0;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .file-input:hover {
            border-color: #10b981;
            background: #f0fdf4;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-crown"></i> TALENTRIX Admin</h2>
                <p>Welcome, <?php echo htmlspecialchars($current_user['first_name']); ?></p>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-chart-bar"></i> Dashboard</a></li>
                    <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                    <li><a href="manage_homepage.php"><i class="fas fa-home"></i> Manage Homepage</a></li>
                    <li class="active"><a href="manage_images.php"><i class="fas fa-images"></i> Manage Images</a></li>
                    <li><a href="../index.php"><i class="fas fa-eye"></i> View Homepage</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="manage-header">
                <h1>📸 MANAGE HOMEPAGE IMAGES</h1>
                <p>Upload and manage images for athletes and dancers gallery</p>
            </div>

            <?php if(isset($success)): ?>
                <div class="notification"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="notification" style="background: #dc2626;"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Upload Form -->
            <div class="upload-form">
                <h2 style="margin-bottom: 25px;">Upload New Image</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_image">
                    
                    <div class="form-group">
                        <label>Image Title:</label>
                        <input type="text" name="image_title" required placeholder="e.g., Basketball Team Training">
                    </div>
                    
                    <div class="form-group">
                        <label>Category:</label>
                        <select name="image_category" required>
                            <option value="athletics">Athletics</option>
                            <option value="dance">Dance</option>
                            <option value="event">Event</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Date:</label>
                        <input type="date" name="image_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Choose Image:</label>
                        <div class="file-input" onclick="document.getElementById('image_file').click()">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #10b981; margin-bottom: 15px;"></i>
                            <p>Click to select image or drag and drop</p>
                            <p style="font-size: 0.8rem; color: #718096;">PNG, JPG, JPEG, GIF up to 5MB</p>
                            <input type="file" id="image_file" name="image_file" accept="image/*" required style="display: none;" onchange="updateFileName(this)">
                        </div>
                        <p id="file-name" style="margin-top: 10px; font-size: 0.9rem;"></p>
                    </div>
                    
                    <button type="submit" class="btn-add" style="width: 100%;">
                        <i class="fas fa-upload"></i> Upload Image
                    </button>
                </form>
            </div>

            <!-- Image Gallery -->
            <h2 style="margin: 40px 0 20px;">Uploaded Images</h2>
            <div class="image-grid">
                <?php if(empty($images)): ?>
                <p style="grid-column: 1/-1; text-align: center; padding: 50px; background: white; border-radius: 15px;">
                    No images uploaded yet. Click "Upload New Image" to add some.
                </p>
                <?php endif; ?>
                
                <?php foreach($images as $image): ?>
                <div class="image-card">
                    <div class="image-preview" style="background-image: url('<?php echo $image['image_url']; ?>');">
                        <span class="image-category category-<?php echo $image['image_category']; ?>">
                            <?php echo ucfirst($image['image_category']); ?>
                        </span>
                    </div>
                    <div class="image-info">
                        <h3><?php echo htmlspecialchars($image['image_title']); ?></h3>
                        <p style="color: #718096; margin: 5px 0;">
                            <i class="far fa-calendar"></i> <?php echo date('F d, Y', strtotime($image['image_date'])); ?>
                        </p>
                        <form method="POST" onsubmit="return confirm('Delete this image?')" style="margin-top: 15px;">
                            <input type="hidden" name="action" value="delete_image">
                            <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                            <button type="submit" class="btn-delete" style="width: 100%;">
                                <i class="fas fa-trash"></i> Delete Image
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            const fileName = input.files[0]?.name;
            document.getElementById('file-name').innerHTML = fileName ? 'Selected: ' + fileName : '';
        }
        
        // Auto-hide notification
        setTimeout(() => {
            const notification = document.querySelector('.notification');
            if (notification) notification.style.display = 'none';
        }, 3000);
    </script>
</body>
</html>