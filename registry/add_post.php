<?php
// add_post.php - Add New Post with Image Upload
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'athletics_admin' && $_SESSION['user_type'] !== 'dance_admin')) {
    header('Location: login.php');
    exit();
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$error = '';
$success = '';

// Create upload directory if it doesn't exist
$upload_dir = 'uploads/posts/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category = $_POST['category'] ?? '';
    $post_date = $_POST['post_date'] ?? date('Y-m-d');
    
    // Handle image upload
    $image_path = null;
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['post_image']['type'], $allowed_types)) {
            $error = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
        } elseif ($_FILES['post_image']['size'] > $max_size) {
            $error = 'Image size must be less than 5MB.';
        } else {
            // Generate unique filename
            $extension = pathinfo($_FILES['post_image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['post_image']['tmp_name'], $target_path)) {
                $image_path = $target_path;
            } else {
                $error = 'Failed to upload image.';
            }
        }
    }
    
    if (empty($title)) {
        $error = 'Title is required';
    } elseif (empty($content)) {
        $error = 'Content is required';
    } elseif (empty($category)) {
        $error = 'Please select a category';
    } elseif (empty($error)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO posts (title, content, category, image_path, post_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $category, $image_path, $post_date]);
            
            // Update category post count
            $stmt = $pdo->prepare("UPDATE categories SET post_count = post_count + 1 WHERE name = ?");
            $stmt->execute([$category]);
            
            $_SESSION['success'] = 'Post added successfully!';
            header('Location: manage_posts.php');
            exit();
        } catch (Exception $e) {
            $error = 'Error adding post: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Add Post</title>
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
            max-width: 800px;
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        .form-group label i {
            margin-right: 8px;
            color: #8B1E3F;
        }

        .form-group label .required {
            color: #dc3545;
            margin-left: 4px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #8B1E3F;
            outline: none;
            box-shadow: 0 0 0 3px rgba(139, 30, 63, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* Image Upload Styles */
        .image-upload-container {
            border: 2px dashed #e9ecef;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .image-upload-container:hover {
            border-color: #8B1E3F;
            background: #fff5f5;
        }

        .image-upload-container i {
            font-size: 48px;
            color: #8B1E3F;
            margin-bottom: 10px;
        }

        .image-upload-container p {
            color: #495057;
            font-size: 14px;
        }

        .image-upload-container .file-info {
            font-size: 12px;
            color: #6c757d;
            margin-top: 10px;
        }

        .image-preview {
            display: none;
            margin-top: 20px;
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .image-preview img {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
        }

        .remove-image {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: background 0.2s;
        }

        .remove-image:hover {
            background: #bb2d3b;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: #6b152f;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
            transition: background 0.2s;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
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
            <h1><i class="fas fa-plus-circle"></i>ADD NEW POST</h1>
            <p>Create a new announcement or news post with images</p>
        </div>

        <div class="form-content">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" id="postForm">
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Title <span class="required">*</span></label>
                    <input type="text" name="title" placeholder="Enter post title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Category <span class="required">*</span></label>
                        <select name="category" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['name']; ?>" <?php echo (isset($_POST['category']) && $_POST['category'] == $cat['name']) ? 'selected' : ''; ?>>
                                    <?php echo $cat['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Post Date <span class="required">*</span></label>
                        <input type="date" name="post_date" value="<?php echo isset($_POST['post_date']) ? $_POST['post_date'] : date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <!-- Image Upload -->
                <div class="form-group">
                    <label><i class="fas fa-image"></i> Featured Image</label>
                    <div class="image-upload-container" onclick="document.getElementById('post_image').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload an image or drag and drop</p>
                        <p class="file-info">Supported formats: JPG, PNG, GIF, WEBP (Max 5MB)</p>
                    </div>
                    <input type="file" id="post_image" name="post_image" accept="image/*" style="display: none;" onchange="previewImage(this)">
                    
                    <!-- Image Preview -->
                    <div class="image-preview" id="imagePreview">
                        <img id="previewImg" src="" alt="Preview">
                        <button type="button" class="remove-image" onclick="removeImage()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Content <span class="required">*</span></label>
                    <textarea name="content" rows="12" placeholder="Write your post content here..." required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Publish Post
                    </button>
                    <a href="manage_posts.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage() {
            const preview = document.getElementById('imagePreview');
            const fileInput = document.getElementById('post_image');
            
            preview.style.display = 'none';
            previewImg.src = '';
            fileInput.value = '';
        }

        // Drag and drop functionality
        const dropZone = document.querySelector('.image-upload-container');
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#8B1E3F';
            dropZone.style.background = '#fff5f5';
        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#e9ecef';
            dropZone.style.background = '#f8f9fa';
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#e9ecef';
            dropZone.style.background = '#f8f9fa';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('post_image').files = files;
                previewImage(document.getElementById('post_image'));
            }
        });
    </script>
</body>
</html>