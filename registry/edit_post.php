<?php
// edit_post.php - Edit Post with Image Upload
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'athletics_admin' && $_SESSION['user_type'] !== 'dance_admin')) {
    header('Location: login.php');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get post data
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    header('Location: manage_posts.php');
    exit();
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$error = '';

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
    $remove_current_image = isset($_POST['remove_current_image']) ? true : false;
    
    // Handle image upload
    $image_path = $post['image_path']; // Keep existing image by default
    
    // Remove image if requested
    if ($remove_current_image && $post['image_path'] && file_exists($post['image_path'])) {
        unlink($post['image_path']);
        $image_path = null;
    }
    
    // Upload new image if provided
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['post_image']['type'], $allowed_types)) {
            $error = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
        } elseif ($_FILES['post_image']['size'] > $max_size) {
            $error = 'Image size must be less than 5MB.';
        } else {
            // Delete old image
            if ($post['image_path'] && file_exists($post['image_path'])) {
                unlink($post['image_path']);
            }
            
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
            // Update category counts if category changed
            if ($category != $post['category']) {
                $stmt = $pdo->prepare("UPDATE categories SET post_count = post_count - 1 WHERE name = ?");
                $stmt->execute([$post['category']]);
                
                $stmt = $pdo->prepare("UPDATE categories SET post_count = post_count + 1 WHERE name = ?");
                $stmt->execute([$category]);
            }
            
            $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, category = ?, image_path = ?, post_date = ? WHERE id = ?");
            $stmt->execute([$title, $content, $category, $image_path, $post_date, $id]);
            
            $_SESSION['success'] = 'Post updated successfully!';
            header('Location: manage_posts.php');
            exit();
        } catch (Exception $e) {
            $error = 'Error updating post: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALENTRIX - Edit Post</title>
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

        .current-image {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .current-image h4 {
            color: #495057;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .current-image img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            margin-bottom: 10px;
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
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
            <h1><i class="fas fa-edit"></i>EDIT POST</h1>
            <p>Update post information and images</p>
        </div>

        <div class="form-content">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" id="postForm">
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Title <span class="required">*</span></label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Category <span class="required">*</span></label>
                        <select name="category" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['name']; ?>" <?php echo $post['category'] == $cat['name'] ? 'selected' : ''; ?>>
                                    <?php echo $cat['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Post Date <span class="required">*</span></label>
                        <input type="date" name="post_date" value="<?php echo $post['post_date']; ?>" required>
                    </div>
                </div>

                <!-- Current Image Display -->
                <?php if ($post['image_path'] && file_exists($post['image_path'])): ?>
                <div class="current-image">
                    <h4><i class="fas fa-image"></i> Current Image:</h4>
                    <img src="<?php echo $post['image_path']; ?>" alt="Current post image">
                    <div class="checkbox-group">
                        <input type="checkbox" name="remove_current_image" id="remove_image">
                        <label for="remove_image">Remove current image</label>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Image Upload -->
                <div class="form-group">
                    <label><i class="fas fa-image"></i> New Image (Optional)</label>
                    <div class="image-upload-container" onclick="document.getElementById('post_image').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload a new image or drag and drop</p>
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
                    <textarea name="content" rows="12" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Update Post
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