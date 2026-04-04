<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth($pdo);
$auth->requireAuth();
$auth->requireRole('admin');
$auth->requireAdminType('cultural_arts');

// Get statistics
$stats = [];

// Total cultural arts students
$stmt = $pdo->query("
    SELECT COUNT(*) as total FROM Student s
    JOIN \"User\" u ON s.user_id = u.id
    WHERE EXISTS (
        SELECT 1 FROM Participation p 
        JOIN Contest c ON p.contest_id = c.id 
        JOIN Activity a ON c.activity_id = a.id 
        WHERE p.student_id = s.id AND a.activity_type_filter = 'cultural_arts'
    ) OR s.course LIKE '%Arts%' OR s.course LIKE '%Music%' OR s.course LIKE '%Theater%'
");
$stats['total_students'] = $stmt->fetch()['total'];

// Total cultural arts coaches
$stmt = $pdo->query("
    SELECT COUNT(*) as total FROM Coach c
    WHERE c.specialization ILIKE '%arts%' OR c.specialization ILIKE '%music%' 
       OR c.specialization ILIKE '%theater%' OR c.specialization ILIKE '%dance%'
");
$stats['total_coaches'] = $stmt->fetch()['total'];

// Total cultural arts participations
$stmt = $pdo->query("
    SELECT COUNT(*) as total FROM Participation p
    JOIN Contest c ON p.contest_id = c.id
    JOIN Activity a ON c.activity_id = a.id
    WHERE a.activity_type_filter = 'cultural_arts'
");
$stats['total_participations'] = $stmt->fetch()['total'];

// Total cultural arts awards
$stmt = $pdo->query("
    SELECT COUNT(*) as total FROM Participation p
    JOIN Contest c ON p.contest_id = c.id
    JOIN Activity a ON c.activity_id = a.id
    WHERE a.activity_type_filter = 'cultural_arts' 
    AND p.ranking IN ('champion', '1st_place', '2nd_place', '3rd_place')
");
$stats['total_awards'] = $stmt->fetch()['total'];

// Get all cultural arts users
$stmt = $pdo->query("
    SELECT u.*, 
           CASE 
               WHEN u.usertype = 'student' THEN s.first_name || ' ' || s.last_name
               WHEN u.usertype = 'coach' THEN c.first_name || ' ' || c.last_name
               ELSE u.email
           END as fullname
    FROM \"User\" u
    LEFT JOIN Student s ON u.id = s.user_id
    LEFT JOIN Coach c ON u.id = c.user_id
    WHERE u.usertype IN ('student', 'coach')
    AND (
        (u.usertype = 'student' AND (
            s.course LIKE '%Arts%' OR s.course LIKE '%Music%' OR s.course LIKE '%Theater%' OR
            EXISTS (SELECT 1 FROM Participation p JOIN Contest ct ON p.contest_id = ct.id JOIN Activity a ON ct.activity_id = a.id WHERE p.student_id = s.id AND a.activity_type_filter = 'cultural_arts')
        ))
        OR
        (u.usertype = 'coach' AND (c.specialization ILIKE '%arts%' OR c.specialization ILIKE '%music%' OR c.specialization ILIKE '%theater%'))
    )
    ORDER BY u.id DESC
");
$users = $stmt->fetchAll();

// Get all cultural arts activities
$stmt = $pdo->query("
    SELECT a.*, COUNT(c.id) as contest_count
    FROM Activity a
    LEFT JOIN Contest c ON a.id = c.activity_id
    WHERE a.activity_type_filter = 'cultural_arts'
    GROUP BY a.id
    ORDER BY a.year DESC
");
$activities = $stmt->fetchAll();

// Get all cultural arts contests
$stmt = $pdo->query("
    SELECT c.*, a.activity_name, cat.category_name
    FROM Contest c
    JOIN Activity a ON c.activity_id = a.id
    LEFT JOIN Category cat ON c.category_id = cat.id
    WHERE a.activity_type_filter = 'cultural_arts'
    ORDER BY c.year DESC, c.id DESC
");
$contests = $stmt->fetchAll();

// Get cultural arts categories
$stmt = $pdo->query("SELECT * FROM Category WHERE category_type IN ('cultural_arts', 'general') ORDER BY category_name");
$categories = $stmt->fetchAll();

// Get recent cultural arts participations
$stmt = $pdo->query("
    SELECT p.*, s.first_name, s.last_name, s.course, c.name as contest_name
    FROM Participation p
    JOIN Student s ON p.student_id = s.id
    JOIN Contest c ON p.contest_id = c.id
    JOIN Activity a ON c.activity_id = a.id
    WHERE a.activity_type_filter = 'cultural_arts'
    ORDER BY p.created_at DESC
    LIMIT 20
");
$recentParticipations = $stmt->fetchAll();

// Get announcements for cultural arts
$stmt = $pdo->query("
    SELECT a.*, u.email as author_email
    FROM Announcement a
    LEFT JOIN \"User\" u ON a.author_id = u.id
    WHERE a.announcement_type IN ('cultural_arts', 'general')
    ORDER BY a.created_at DESC
");
$announcements = $stmt->fetchAll();

// Parse image paths for each announcement
foreach ($announcements as $key => $announcement) {
    $images = [];
    if (!empty($announcement['image_path'])) {
        $decoded = json_decode($announcement['image_path'], true);
        if (is_array($decoded)) {
            $images = $decoded;
        } else {
            $images = explode(',', $announcement['image_path']);
            $images = array_map('trim', $images);
        }
    }
    $announcements[$key]['images'] = array_filter($images);
}

// Create upload directory if not exists
$uploadDir = '../uploads/announcements/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_activity':
                    $stmt = $pdo->prepare("
                        INSERT INTO Activity (activity_name, activity_type, event_name, year, activity_type_filter) 
                        VALUES (?, ?, ?, ?, 'cultural_arts')
                    ");
                    $stmt->execute([
                        $_POST['activity_name'],
                        $_POST['activity_type'],
                        $_POST['event_name'],
                        $_POST['year']
                    ]);
                    $message = "Activity added successfully!";
                    $messageType = "success";
                    break;
                    
                case 'add_contest':
                    $stmt = $pdo->prepare("
                        INSERT INTO Contest (activity_id, name, tournament_manager, category_id, year) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['activity_id'],
                        $_POST['name'],
                        $_POST['tournament_manager'],
                        $_POST['category_id'] ?: null,
                        $_POST['year']
                    ]);
                    $message = "Contest added successfully!";
                    $messageType = "success";
                    break;
                    
                case 'add_category':
                    $stmt = $pdo->prepare("
                        INSERT INTO Category (category_name, category_type) VALUES (?, 'cultural_arts')
                    ");
                    $stmt->execute([$_POST['category_name']]);
                    $message = "Category added successfully!";
                    $messageType = "success";
                    break;

                    $stmt = $pdo->prepare("DELETE FROM \"User\" WHERE id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    $message = "User deleted successfully!";
                    $messageType = "success";
                    break;
                    
                case 'add_announcement':
                    $imagePaths = [];
                    if (isset($_FILES['announcement_images']) && !empty($_FILES['announcement_images']['name'][0])) {
                        $files = $_FILES['announcement_images'];
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        $maxSize = 5 * 1024 * 1024;
                        
                        for ($i = 0; $i < count($files['name']); $i++) {
                            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                $fileType = $files['type'][$i];
                                $fileSize = $files['size'][$i];
                                
                                if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
                                    $extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                                    $filename = uniqid() . '_' . time() . '_' . $i . '.' . $extension;
                                    $uploadPath = $uploadDir . $filename;
                                    
                                    if (move_uploaded_file($files['tmp_name'][$i], $uploadPath)) {
                                        $imagePaths[] = 'uploads/announcements/' . $filename;
                                    }
                                } else {
                                    $message = "Invalid image file: " . $files['name'][$i] . ". Only JPG, PNG, GIF, WEBP up to 5MB are allowed.";
                                    $messageType = "error";
                                    break 2;
                                }
                            }
                        }
                    }
                    
                    $imagePathJson = !empty($imagePaths) ? json_encode($imagePaths) : null;
                    $announcementType = isset($_POST['announcement_type']) ? $_POST['announcement_type'] : 'cultural_arts';
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO Announcement (title, content, announcement_type, author_id, is_published, image_path) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['content'],
                        $announcementType,
                        $_SESSION['user_id'],
                        isset($_POST['is_published']) ? 1 : 0,
                        $imagePathJson
                    ]);
                    $message = "Announcement added with " . count($imagePaths) . " images successfully!";
                    $messageType = "success";
                    break;

case 'delete_user':
    try {
        $pdo->beginTransaction();
        
        $userId = $_POST['user_id'];
        
        $stmt = $pdo->prepare("SELECT usertype FROM \"User\" WHERE id = ?");
        $stmt->execute([$userId]);
        $userType = $stmt->fetchColumn();
        
        // Delete the user - constraints will handle the rest
        $stmt = $pdo->prepare("DELETE FROM \"User\" WHERE id = ?");
        $stmt->execute([$userId]);
        
        $pdo->commit();
        
        $message = "User deleted successfully!";
        $messageType = "success";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Error deleting user: " . $e->getMessage();
        $messageType = "error";
    }
    break;
    
    // Delete the user
    $stmt = $pdo->prepare("DELETE FROM \"User\" WHERE id = ?");
    $stmt->execute([$_POST['user_id']]);
    $message = "User deleted successfully!";
    $messageType = "success";
    break;
                    
                case 'delete_announcement':
                    $stmt = $pdo->prepare("SELECT image_path FROM Announcement WHERE id = ?");
                    $stmt->execute([$_POST['announcement_id']]);
                    $announcement = $stmt->fetch();
                    
                    if ($announcement && $announcement['image_path']) {
                        $images = json_decode($announcement['image_path'], true);
                        if (is_array($images)) {
                            foreach ($images as $imagePath) {
                                $imageFile = '../' . $imagePath;
                                if (file_exists($imageFile)) {
                                    unlink($imageFile);
                                }
                            }
                        } else {
                            $imageFile = '../' . $announcement['image_path'];
                            if (file_exists($imageFile)) {
                                unlink($imageFile);
                            }
                        }
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM Announcement WHERE id = ?");
                    $stmt->execute([$_POST['announcement_id']]);
                    $message = "Announcement deleted successfully!";
                    $messageType = "success";
                    break;
                    
                case 'update_announcement_status':
                    $stmt = $pdo->prepare("UPDATE Announcement SET is_published = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$_POST['is_published'], $_POST['announcement_id']]);
                    $message = "Announcement status updated!";
                    $messageType = "success";
                    break;
                    
                case 'delete_announcement_image':
                    $stmt = $pdo->prepare("SELECT image_path FROM Announcement WHERE id = ?");
                    $stmt->execute([$_POST['announcement_id']]);
                    $announcement = $stmt->fetch();
                    
                    if ($announcement && $announcement['image_path']) {
                        $images = json_decode($announcement['image_path'], true);
                        $imageToDelete = $_POST['image_path'];
                        
                        if (is_array($images) && in_array($imageToDelete, $images)) {
                            $images = array_diff($images, [$imageToDelete]);
                            $newImagePath = !empty($images) ? json_encode(array_values($images)) : null;
                            
                            $imageFile = '../' . $imageToDelete;
                            if (file_exists($imageFile)) {
                                unlink($imageFile);
                            }
                            
                            $stmt = $pdo->prepare("UPDATE Announcement SET image_path = ? WHERE id = ?");
                            $stmt->execute([$newImagePath, $_POST['announcement_id']]);
                            $message = "Image deleted successfully!";
                            $messageType = "success";
                        }
                    }
                    break;
            }
            
            header("Location: admin_cultural.php");
            exit();
            
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cultural Arts Admin - BISU Candijay</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #eef2f7 100%);
            min-height: 100vh;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #8B4513 0%, #CD853F 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 35px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.1);
        }

        .avatar {
            width: 85px;
            height: 85px;
            background: linear-gradient(135deg, #ffd89b, #c7e9fb);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2.5rem;
            color: #8B4513;
            font-weight: bold;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .sidebar-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .sidebar-header p {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 5px;
        }

        .sidebar-nav {
            padding: 25px 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            margin: 5px 15px;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: all 0.3s ease;
            gap: 12px;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
        }

        .sidebar-nav a i {
            width: 24px;
            font-size: 1.2rem;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 35px;
        }

        .top-bar {
            background: white;
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-title h2 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #8B4513, #CD853F);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .page-title p {
            color: #6c757d;
            margin-top: 5px;
            font-size: 0.9rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info span {
            background: #f8f9fa;
            padding: 8px 18px;
            border-radius: 40px;
            font-size: 0.85rem;
            color: #495057;
        }

        .logout-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 8px 22px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.85rem;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.03);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-info h3 {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: #CD853F;
        }

        .stat-icon i {
            font-size: 3rem;
            color: #8B4513;
            opacity: 0.2;
        }

        .data-table {
            background: white;
            border-radius: 24px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
            margin-bottom: 25px;
        }

        .data-table h3 {
            margin-bottom: 20px;
            color: #8B4513;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.85rem;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-cultural {
            background: #CD853F;
            color: white;
        }
        
        .badge-published {
            background: #28a745;
            color: white;
        }
        
        .badge-draft {
            background: #dc3545;
            color: white;
        }

        .form-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            margin-bottom: 35px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .form-card h3 {
            margin-bottom: 20px;
            color: #8B4513;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            transition: 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #CD853F;
            outline: none;
        }

        .form-group input[type="file"] {
            padding: 8px;
        }

        .image-preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .image-preview-item {
            position: relative;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .image-preview-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }

        .remove-preview {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-size: 12px;
        }

        .remove-preview:hover {
            background: #dc3545;
        }

        .btn-primary {
            background: linear-gradient(135deg, #8B4513, #CD853F);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(205, 133, 63, 0.4);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 5px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
            padding: 5px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
            padding: 5px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .announcement-preview {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 12px;
            border-left: 4px solid #CD853F;
            transition: transform 0.2s;
            display: flex;
            gap: 15px;
        }
        
        .announcement-preview:hover {
            transform: translateX(5px);
        }
        
        .announcement-preview-images {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .announcement-preview-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            background: #e9ecef;
            cursor: pointer;
        }
        
        .announcement-preview-content {
            flex: 1;
        }
        
        .announcement-images-gallery {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .gallery-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid #e0e0e0;
            transition: transform 0.2s;
        }
        
        .gallery-image:hover {
            transform: scale(1.05);
            border-color: #CD853F;
        }

        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1002;
            background: #8B4513;
            color: white;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1001;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .menu-toggle {
                display: block;
            }
            .announcement-preview {
                flex-direction: column;
            }
            .announcement-preview-image {
                width: 100%;
                height: 150px;
            }
        }

        .section {
            animation: fadeInUp 0.4s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .image-modal {
            display: none;
            position: fixed;
            z-index: 3000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            justify-content: center;
            align-items: center;
        }
        
        .image-modal-content {
            position: relative;
            max-width: 90%;
            max-height: 90%;
        }
        
        .image-modal-content img {
            width: 100%;
            height: auto;
            max-height: 80vh;
            object-fit: contain;
        }
        
        .image-modal-close {
            position: absolute;
            top: 20px;
            right: 40px;
            color: white;
            font-size: 40px;
            cursor: pointer;
        }
        
        .image-modal-prev,
        .image-modal-next {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            font-size: 30px;
            padding: 15px;
            cursor: pointer;
            border-radius: 50%;
        }
        
        .image-modal-prev {
            left: 20px;
        }
        
        .image-modal-next {
            right: 20px;
        }
        
        .image-modal-prev:hover,
        .image-modal-next:hover {
            background: rgba(0, 0, 0, 0.8);
        }

        /* Report Styles */
        .report-options {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 16px;
            margin-top: 15px;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .report-table th,
        .report-table td {
            padding: 10px;
            border: 1px solid #dee2e6;
            text-align: left;
        }
        
        .report-table th {
            background: #e9ecef;
            font-weight: 600;
            color: #495057;
        }
        
        .report-table tr:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
<div class="menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</div>
<div class="dashboard-wrapper">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="avatar">
                <i class="fas fa-palette"></i>
            </div>
            <h3>Cultural Arts Admin</h3>
            <p>Culture & Arts Division</p>
        </div>
        <div class="sidebar-nav">
            <a onclick="showSection('overview')" class="active"><i class="fas fa-chart-line"></i> Overview</a>
            <a onclick="showSection('announcements')"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a onclick="showSection('users')"><i class="fas fa-users"></i> Artists & Mentors</a>
            <a onclick="showSection('activities')"><i class="fas fa-calendar-alt"></i> Cultural Activities</a>
            <a onclick="showSection('contests')"><i class="fas fa-trophy"></i> Arts Contests</a>
            <a onclick="showSection('categories')"><i class="fas fa-tags"></i> Art Categories</a>
            <a onclick="showSection('participations')"><i class="fas fa-list"></i> Participations</a>
            <a onclick="showSection('reports')"><i class="fas fa-chart-line"></i> Reports</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h2><i class="fas fa-palette"></i> Cultural Arts Admin Dashboard</h2>
                <p>Managing Culture and Arts Achievements</p>
            </div>
            <div class="user-info">
                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($_SESSION['email']); ?></span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Overview Section -->
        <div id="overview-section" class="section">
            <div class="stats-grid">
                <div class="stat-card" onclick="showSection('users')">
                    <div class="stat-info">
                        <h3>Total Artists</h3>
                        <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
                <div class="stat-card" onclick="showSection('users')">
                    <div class="stat-info">
                        <h3>Arts Mentors</h3>
                        <div class="stat-number"><?php echo $stats['total_coaches']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-user"></i>
                    </div>
                </div>
                <div class="stat-card" onclick="showSection('participations')">
                    <div class="stat-info">
                        <h3>Participations</h3>
                        <div class="stat-number"><?php echo $stats['total_participations']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
                <div class="stat-card" onclick="showSection('participations')">
                    <div class="stat-info">
                        <h3>Arts Awards</h3>
                        <div class="stat-number"><?php echo $stats['total_awards']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-medal"></i>
                    </div>
                </div>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-bullhorn"></i> Recent Announcements</h3>
                <?php $recentAnnouncements = array_slice($announcements, 0, 3); ?>
                <?php if (!empty($recentAnnouncements)): ?>
                    <?php foreach ($recentAnnouncements as $announcement): ?>
                        <div class="announcement-preview">
                            <?php if (!empty($announcement['images'])): ?>
                                <div class="announcement-preview-images">
                                    <img src="../<?php echo $announcement['images'][0]; ?>" class="announcement-preview-image" alt="Announcement image" onclick="openImageModal(<?php echo $announcement['id']; ?>, 0)">
                                    <?php if (count($announcement['images']) > 1): ?>
                                        <span style="position: relative; display: inline-block;">
                                            <img src="../<?php echo $announcement['images'][1]; ?>" class="announcement-preview-image" alt="Announcement image" onclick="openImageModal(<?php echo $announcement['id']; ?>, 1)">
                                            <?php if (count($announcement['images']) > 2): ?>
                                                <span style="position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.7); color: white; padding: 2px 5px; border-radius: 10px; font-size: 10px;">+<?php echo count($announcement['images']) - 2; ?></span>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="announcement-preview-content">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                                    <h4 style="color: #333; margin: 0;"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                    <span class="badge <?php echo $announcement['is_published'] ? 'badge-published' : 'badge-draft'; ?>">
                                        <?php echo $announcement['is_published'] ? 'Published' : 'Draft'; ?>
                                    </span>
                                </div>
                                <p style="color: #666; margin-bottom: 10px;"><?php echo htmlspecialchars(substr($announcement['content'], 0, 120)) . '...'; ?></p>
                                <small style="color: #999;">Posted: <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?> | Images: <?php echo count($announcement['images']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No announcements yet. Click "Announcements" to create one.</p>
                <?php endif; ?>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-clock"></i> Recent Cultural Arts Participations</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Contest</th>
                            <th>Ranking</th>
                            <th>Role</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentParticipations as $participation): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($participation['first_name'] . ' ' . $participation['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($participation['course']); ?></td>
                            <td><?php echo htmlspecialchars($participation['contest_name']); ?></td>
                            <td><?php echo str_replace('_', ' ', $participation['ranking']); ?></td>
                            <td><?php echo htmlspecialchars($participation['role']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($participation['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentParticipations)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;">No cultural arts participations recorded yet.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Announcements Section -->
        <div id="announcements-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-plus-circle"></i> Create New Announcement</h3>
                <form method="POST" enctype="multipart/form-data" id="announcementForm">
                    <input type="hidden" name="action" value="add_announcement">
                    <input type="hidden" name="announcement_type" value="cultural_arts">
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" placeholder="Announcement Title" required>
                    </div>
                    <div class="form-group">
                        <label>Content *</label>
                        <textarea name="content" rows="5" placeholder="Write your announcement here..." required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 12px;"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Images (Multiple - Select up to 10 images)</label>
                        <input type="file" name="announcement_images[]" id="announcement_images" accept="image/jpeg,image/png,image/gif,image/webp" multiple onchange="previewMultipleImages(this)">
                        <small style="color: #6c757d;">Accepted formats: JPG, PNG, GIF, WEBP. Max size per image: 5MB. You can select multiple images.</small>
                        <div id="multipleImagePreview" class="image-preview-container"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="is_published">
                                <option value="1">Published</option>
                                <option value="0">Draft</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Post Announcement</button>
                </form>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-bullhorn"></i> All Announcements</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Images</th>
                            <th>Title</th>
                            <th>Content</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($announcements as $announcement): ?>
                        <tr>
                            <td><?php echo $announcement['id']; ?></td>
                            <td>
                                <?php if (!empty($announcement['images'])): ?>
                                    <div class="announcement-images-gallery">
                                        <?php foreach (array_slice($announcement['images'], 0, 3) as $idx => $img): ?>
                                            <img src="../<?php echo $img; ?>" class="gallery-image" alt="Image" onclick="viewFullImage('<?php echo $img; ?>')">
                                        <?php endforeach; ?>
                                        <?php if (count($announcement['images']) > 3): ?>
                                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; background: #e9ecef; border-radius: 8px;">+<?php echo count($announcement['images']) - 3; ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #999;">No images</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                            <td style="max-width: 300px;"><?php echo htmlspecialchars(substr($announcement['content'], 0, 80)) . '...'; ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_announcement_status">
                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                    <input type="hidden" name="is_published" value="<?php echo $announcement['is_published'] ? 0 : 1; ?>">
                                    <button type="submit" class="<?php echo $announcement['is_published'] ? 'btn-warning' : 'btn-success'; ?>" style="padding: 5px 10px;">
                                        <?php echo $announcement['is_published'] ? 'Unpublish' : 'Publish'; ?>
                                    </button>
                                </form>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this announcement and all its images?')">
                                    <input type="hidden" name="action" value="delete_announcement">
                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Users Section -->
        <div id="users-section" class="section" style="display: none;">
            <div class="data-table">
                <h3><i class="fas fa-users"></i> Artists & Mentors (Cultural Arts Division)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Name</th>
                            <th>User Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                            <td><?php echo ucfirst($user['usertype']); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Activities Section -->
        <div id="activities-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-plus-circle"></i> Add New Cultural Activity</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_activity">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Activity Name</label>
                            <input type="text" name="activity_name" placeholder="e.g., Cultural Fest, Art Exhibition" required>
                        </div>
                        <div class="form-group">
                            <label>Activity Type</label>
                            <select name="activity_type" required>
                                <option value="cultural">Cultural</option>
                                <option value="arts">Arts</option>
                                <option value="music">Music</option>
                                <option value="dance">Dance</option>
                                <option value="theater">Theater</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Event Name</label>
                            <input type="text" name="event_name" placeholder="e.g., Singing Competition">
                        </div>
                        <div class="form-group">
                            <label>Year</label>
                            <input type="number" name="year" value="<?php echo date('Y'); ?>" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Add Activity</button>
                </form>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-calendar-alt"></i> Existing Cultural Activities</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Activity Name</th>
                            <th>Type</th>
                            <th>Event</th>
                            <th>Year</th>
                            <th>Contests</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $activity): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($activity['activity_name']); ?></td>
                            <td><span class="badge badge-cultural"><?php echo ucfirst($activity['activity_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($activity['event_name']); ?></td>
                            <td><?php echo $activity['year']; ?></td>
                            <td><?php echo $activity['contest_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Contests Section -->
        <div id="contests-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-plus-circle"></i> Add New Arts Contest</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_contest">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Activity</label>
                            <select name="activity_id" required>
                                <option value="">Select Activity</option>
                                <?php foreach ($activities as $activity): ?>
                                <option value="<?php echo $activity['id']; ?>">
                                    <?php echo htmlspecialchars($activity['activity_name'] . ' (' . $activity['year'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Contest Name</label>
                            <input type="text" name="name" placeholder="e.g., Singing Competition, Painting Contest" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tournament Manager</label>
                            <input type="text" name="tournament_manager">
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" name="year" value="<?php echo date('Y'); ?>" required>
                    </div>
                    <button type="submit" class="btn-primary">Add Contest</button>
                </form>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-trophy"></i> Existing Arts Contests</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Contest Name</th>
                            <th>Activity</th>
                            <th>Category</th>
                            <th>Manager</th>
                            <th>Year</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contests as $contest): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($contest['name']); ?></td>
                            <td><?php echo htmlspecialchars($contest['activity_name']); ?></td>
                            <td><?php echo htmlspecialchars($contest['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($contest['tournament_manager']); ?></td>
                            <td><?php echo $contest['year']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Categories Section -->
        <div id="categories-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-plus-circle"></i> Add New Art Category</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_category">
                    <div class="form-group">
                        <label>Category Name</label>
                        <input type="text" name="category_name" placeholder="e.g., Vocal Solo, Modern Dance, Oil Painting" required>
                    </div>
                    <button type="submit" class="btn-primary">Add Category</button>
                </form>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-tags"></i> Existing Art Categories</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Category Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><span class="badge badge-cultural"><?php echo htmlspecialchars($category['category_name']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Participations Section -->
        <div id="participations-section" class="section" style="display: none;">
            <div class="data-table">
                <h3><i class="fas fa-list"></i> Cultural Arts Participations</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Contest</th>
                            <th>Ranking</th>
                            <th>Role</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentParticipations as $participation): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($participation['first_name'] . ' ' . $participation['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($participation['course']); ?></td>
                            <td><?php echo htmlspecialchars($participation['contest_name']); ?></td>
                            <td><?php echo str_replace('_', ' ', $participation['ranking']); ?></td>
                            <td><?php echo htmlspecialchars($participation['role']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($participation['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Reports Section -->
        <div id="reports-section" class="section" style="display: none;">
            <div class="form-card">
                <h3><i class="fas fa-chart-line"></i> Generate Reports</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Report Type</label>
                        <select id="reportType" onchange="toggleReportOptions()">
                            <option value="coaches">Coaches Report</option>
                            <option value="events">Events/Activities Report</option>
                            <option value="teams">Teams Report</option>
                        </select>
                    </div>
                </div>
                
                <!-- Coaches Report Options -->
                <div id="coachesOptions" class="report-options">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Select Coach</label>
                            <select id="coachSelect">
                                <option value="">All Coaches</option>
                                <?php
                                $coachStmt = $pdo->query("
                                    SELECT c.id, c.first_name, c.last_name, c.specialization
                                    FROM Coach c
                                    WHERE c.specialization ILIKE '%arts%' OR c.specialization ILIKE '%music%' 
                                       OR c.specialization ILIKE '%theater%' OR c.specialization ILIKE '%dance%'
                                    ORDER BY c.last_name
                                ");
                                $culturalCoaches = $coachStmt->fetchAll();
                                foreach ($culturalCoaches as $coach): ?>
                                    <option value="<?php echo $coach['id']; ?>">
                                        <?php echo htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name'] . ' (' . ($coach['specialization'] ?: 'Cultural Arts') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Year (Optional)</label>
                            <select id="coachYear">
                                <option value="">All Years</option>
                                <?php for($y = 2020; $y <= date('Y'); $y++): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <button onclick="generateCoachReport()" class="btn-primary"><i class="fas fa-download"></i> Generate Coach Report</button>
                </div>
                
                <!-- Events Report Options -->
                <div id="eventsOptions" class="report-options" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Select Activity/Event</label>
                            <select id="eventSelect">
                                <option value="">All Activities</option>
                                <?php
                                $eventStmt = $pdo->query("
                                    SELECT a.id, a.activity_name, a.activity_type, a.year
                                    FROM Activity a
                                    WHERE a.activity_type_filter = 'cultural_arts'
                                    ORDER BY a.year DESC, a.activity_name
                                ");
                                $culturalEvents = $eventStmt->fetchAll();
                                foreach ($culturalEvents as $event): ?>
                                    <option value="<?php echo $event['id']; ?>">
                                        <?php echo htmlspecialchars($event['activity_name'] . ' (' . $event['year'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Year (Optional)</label>
                            <select id="eventYear">
                                <option value="">All Years</option>
                                <?php for($y = 2020; $y <= date('Y'); $y++): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <button onclick="generateEventReport()" class="btn-primary"><i class="fas fa-download"></i> Generate Event Report</button>
                </div>
                
                <!-- Teams Report Options -->
                <div id="teamsOptions" class="report-options" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Select Team</label>
                            <select id="teamSelect">
                                <option value="">All Teams</option>
                                <?php
                                $teamStmt = $pdo->query("
                                    SELECT t.id, t.team_name, t.specialization, c.first_name, c.last_name
                                    FROM Team t
                                    LEFT JOIN Coach c ON t.coach_id = c.id
                                    WHERE t.specialization ILIKE '%arts%' OR t.specialization ILIKE '%music%' 
                                       OR t.specialization ILIKE '%theater%' OR t.specialization ILIKE '%dance%'
                                    ORDER BY t.team_name
                                ");
                                $culturalTeams = $teamStmt->fetchAll();
                                foreach ($culturalTeams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>">
                                        <?php echo htmlspecialchars($team['team_name'] . ' (' . ($team['specialization'] ?: 'Cultural Arts') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Year (Optional)</label>
                            <select id="teamYear">
                                <option value="">All Years</option>
                                <?php for($y = 2020; $y <= date('Y'); $y++): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <button onclick="generateTeamReport()" class="btn-primary"><i class="fas fa-download"></i> Generate Team Report</button>
                </div>
            </div>
            
            <!-- Report Preview Section -->
            <div class="data-table" id="reportPreview" style="display: none;">
                <h3><i class="fas fa-file-alt"></i> Report Preview</h3>
                <div id="reportPreviewContent"></div>
                <button id="downloadReportBtn" class="btn-primary" style="margin-top: 15px;"><i class="fas fa-download"></i> Download CSV</button>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal for Full Screen View -->
<div id="imageModal" class="image-modal">
    <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
    <span class="image-modal-prev" onclick="prevImage()">&#10094;</span>
    <span class="image-modal-next" onclick="nextImage()">&#10095;</span>
    <div class="image-modal-content">
        <img id="modalImage" src="" alt="Full size image">
    </div>
</div>

<script>
let currentImages = [];
let currentIndex = 0;

function showSection(section) {
    const sections = ['overview', 'announcements', 'users', 'activities', 'contests', 'categories', 'participations', 'reports'];
    sections.forEach(sec => {
        const el = document.getElementById(sec + '-section');
        if(el) el.style.display = 'none';
    });
    const activeSection = document.getElementById(section + '-section');
    if(activeSection) activeSection.style.display = 'block';
    
    const links = document.querySelectorAll('.sidebar-nav a');
    links.forEach(link => link.classList.remove('active'));
    if(event && event.target) {
        const clickedLink = event.target.closest('a');
        if(clickedLink) clickedLink.classList.add('active');
    }
    
    if(window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('active');
    }
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
}

let selectedFiles = [];

function previewMultipleImages(input) {
    const previewContainer = document.getElementById('multipleImagePreview');
    previewContainer.innerHTML = '';
    selectedFiles = Array.from(input.files);
    
    selectedFiles.forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewItem = document.createElement('div');
                previewItem.className = 'image-preview-item';
                previewItem.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-preview" onclick="removeImage(${index})">×</button>
                `;
                previewContainer.appendChild(previewItem);
            }
            reader.readAsDataURL(file);
        }
    });
}

function removeImage(index) {
    selectedFiles.splice(index, 1);
    const fileInput = document.getElementById('announcement_images');
    const dt = new DataTransfer();
    selectedFiles.forEach(file => dt.items.add(file));
    fileInput.files = dt.files;
    previewMultipleImages(fileInput);
}

function openImageModal(announcementId, imageIndex) {
    const announcement = announcementsData.find(a => a.id === announcementId);
    if (announcement && announcement.images && announcement.images.length > 0) {
        currentImages = announcement.images;
        currentIndex = imageIndex;
        document.getElementById('modalImage').src = '../' + currentImages[currentIndex];
        document.getElementById('imageModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function viewFullImage(imagePath) {
    currentImages = [imagePath];
    currentIndex = 0;
    document.getElementById('modalImage').src = '../' + imagePath;
    document.getElementById('imageModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function prevImage() {
    if (currentImages.length > 0) {
        currentIndex = (currentIndex - 1 + currentImages.length) % currentImages.length;
        document.getElementById('modalImage').src = '../' + currentImages[currentIndex];
    }
}

function nextImage() {
    if (currentImages.length > 0) {
        currentIndex = (currentIndex + 1) % currentImages.length;
        document.getElementById('modalImage').src = '../' + currentImages[currentIndex];
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeImageModal();
    }
    if (event.key === 'ArrowLeft') {
        prevImage();
    }
    if (event.key === 'ArrowRight') {
        nextImage();
    }
});

window.onclick = function(event) {
    const modal = document.getElementById('imageModal');
    if (event.target === modal) {
        closeImageModal();
    }
}

const announcementsData = <?php echo json_encode($announcements); ?>;

// Report generation functions
let currentReportData = [];
let currentReportType = '';

function toggleReportOptions() {
    const reportType = document.getElementById('reportType').value;
    document.getElementById('coachesOptions').style.display = 'none';
    document.getElementById('eventsOptions').style.display = 'none';
    document.getElementById('teamsOptions').style.display = 'none';
    
    if (reportType === 'coaches') {
        document.getElementById('coachesOptions').style.display = 'block';
    } else if (reportType === 'events') {
        document.getElementById('eventsOptions').style.display = 'block';
    } else if (reportType === 'teams') {
        document.getElementById('teamsOptions').style.display = 'block';
    }
    
    document.getElementById('reportPreview').style.display = 'none';
}

async function generateCoachReport() {
    const coachId = document.getElementById('coachSelect').value;
    const year = document.getElementById('coachYear').value;
    
    let url = 'generate_report.php?type=coach&sports=1';
    if (coachId) url += '&coach_id=' + coachId;
    if (year) url += '&year=' + year;
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        if (data.success) {
            currentReportData = data.data;
            currentReportType = 'coach';
            displayReportPreview(data);
            document.getElementById('reportPreview').style.display = 'block';
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        alert('Error generating report: ' + error);
    }
}

async function generateEventReport() {
    const eventId = document.getElementById('eventSelect').value;
    const year = document.getElementById('eventYear').value;
    
    let url = 'generate_report.php?type=event&sports=1';
    if (eventId) url += '&event_id=' + eventId;
    if (year) url += '&year=' + year;
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        if (data.success) {
            currentReportData = data.data;
            currentReportType = 'event';
            displayReportPreview(data);
            document.getElementById('reportPreview').style.display = 'block';
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        alert('Error generating report: ' + error);
    }
}

async function generateTeamReport() {
    const teamId = document.getElementById('teamSelect').value;
    const year = document.getElementById('teamYear').value;
    
    let url = 'generate_report.php?type=team&sports=1';
    if (teamId) url += '&team_id=' + teamId;
    if (year) url += '&year=' + year;
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        if (data.success) {
            currentReportData = data.data;
            currentReportType = 'team';
            displayReportPreview(data);
            document.getElementById('reportPreview').style.display = 'block';
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        alert('Error generating report: ' + error);
    }
}

function displayReportPreview(data) {
    const container = document.getElementById('reportPreviewContent');
    let html = '<table class="report-table"><thead><tr>';
    
    if (data.headers) {
        data.headers.forEach(header => {
            html += '<th>' + header + '</th>';
        });
    }
    html += '</tr></thead><tbody>';
    
    if (data.data && data.data.length > 0) {
        data.data.forEach(row => {
            html += '<tr>';
            Object.values(row).forEach(cell => {
                html += '<td>' + (cell || '-') + '</td>';
            });
            html += '</tr>';
        });
    } else {
        html += '<tr><td colspan="' + (data.headers ? data.headers.length : 5) + '" style="text-align: center;">No data found</td></tr>';
    }
    
    html += '</tbody></table>';
    container.innerHTML = html;
    
    document.getElementById('downloadReportBtn').onclick = () => downloadReportCSV();
}

function downloadReportCSV() {
    if (!currentReportData || currentReportData.length === 0) {
        alert('No data to download');
        return;
    }
    
    const headers = Object.keys(currentReportData[0]);
    let csvContent = headers.join(',') + '\n';
    
    currentReportData.forEach(row => {
        const rowValues = headers.map(header => {
            let value = row[header] || '';
            if (value.toString().includes(',') || value.toString().includes('"')) {
                value = '"' + value.toString().replace(/"/g, '""') + '"';
            }
            return value;
        });
        csvContent += rowValues.join(',') + '\n';
    });
    
    const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.href = url;
    link.setAttribute('download', 'cultural_report_' + currentReportType + '_' + new Date().toISOString().slice(0,19) + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
</script>
</body>
</html>