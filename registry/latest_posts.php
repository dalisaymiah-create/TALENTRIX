<?php
// latest_posts.php - Include this in your homepage
require_once 'db.php';

// Get latest posts
$latest_posts = $pdo->query("
    SELECT * FROM posts 
    WHERE is_published = 1 
    ORDER BY post_date DESC 
    LIMIT 2
")->fetchAll();

// Get categories with counts
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>

<!-- LATEST POSTS SECTION -->
<div style="padding: 40px; background: white;">
    <div style="max-width: 1200px; margin: auto;">
        <h2 style="font-size: 28px; color: #0a2540; margin-bottom: 30px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-newspaper" style="color: #8B1E3F;"></i> LATEST POSTS
        </h2>
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            <!-- Main Posts -->
            <div>
                <?php if (!empty($latest_posts)): ?>
                    <?php foreach ($latest_posts as $post): ?>
                    <div style="background: #f8f9fa; border-radius: 12px; padding: 25px; margin-bottom: 20px; border-left: 5px solid #8B1E3F;">
                        <h3 style="font-size: 20px; color: #0a2540; margin-bottom: 10px;">
                            <?php echo htmlspecialchars($post['title']); ?>
                        </h3>
                        <div style="color: #6c757d; font-size: 13px; margin-bottom: 15px;">
                            <i class="far fa-calendar-alt"></i> <?php echo date('F j, Y', strtotime($post['post_date'])); ?>
                        </div>
                        <p style="color: #495057; line-height: 1.6; margin-bottom: 15px;">
                            <?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 300))) . '...'; ?>
                        </p>
                        <a href="post.php?id=<?php echo $post['id']; ?>" style="color: #8B1E3F; text-decoration: none; font-weight: 600;">
                            continue reading <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 12px;">
                        <i class="fas fa-newspaper" style="font-size: 48px; color: #6c757d;"></i>
                        <p style="color: #6c757d; margin-top: 10px;">No posts yet</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Categories -->
                <div style="background: #f8f9fa; border-radius: 12px; padding: 25px; margin-bottom: 20px;">
                    <h3 style="font-size: 18px; color: #0a2540; margin-bottom: 15px;">
                        <i class="fas fa-tags" style="color: #8B1E3F;"></i> CATEGORIES
                    </h3>
                    <ul style="list-style: none;">
                        <?php foreach ($categories as $cat): ?>
                        <li style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <a href="category.php?name=<?php echo urlencode($cat['name']); ?>" 
                               style="color: #495057; text-decoration: none; transition: color 0.2s;"
                               onmouseover="this.style.color='#8B1E3F'" 
                               onmouseout="this.style.color='#495057'">
                                <?php echo $cat['name']; ?>
                            </a>
                            <span style="background: #e9ecef; padding: 2px 8px; border-radius: 20px; font-size: 12px; color: #495057;">
                                <?php echo $cat['post_count']; ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Portals -->
                <div style="background: #f8f9fa; border-radius: 12px; padding: 25px;">
                    <h3 style="font-size: 18px; color: #0a2540; margin-bottom: 15px;">
                        <i class="fas fa-external-link-alt" style="color: #8B1E3F;"></i> PORTALS
                    </h3>
                    <ul style="list-style: none;">
                        <li style="margin-bottom: 10px;">
                            <a href="admission.php" style="color: #495057; text-decoration: none; display: block; padding: 8px; border-radius: 6px; transition: all 0.2s;"
                               onmouseover="this.style.background='#8B1E3F'; this.style.color='white'" 
                               onmouseout="this.style.background='transparent'; this.style.color='#495057'">
                                <i class="fas fa-graduation-cap"></i> Admission
                            </a>
                        </li>
                        <li style="margin-bottom: 10px;">
                            <a href="student_login.php" style="color: #495057; text-decoration: none; display: block; padding: 8px; border-radius: 6px; transition: all 0.2s;"
                               onmouseover="this.style.background='#8B1E3F'; this.style.color='white'" 
                               onmouseout="this.style.background='transparent'; this.style.color='#495057'">
                                <i class="fas fa-user-graduate"></i> Student Login
                            </a>
                        </li>
                        <li style="margin-bottom: 10px;">
                            <a href="faculty_login.php" style="color: #495057; text-decoration: none; display: block; padding: 8px; border-radius: 6px; transition: all 0.2s;"
                               onmouseover="this.style.background='#8B1E3F'; this.style.color='white'" 
                               onmouseout="this.style.background='transparent'; this.style.color='#495057'">
                                <i class="fas fa-chalkboard-teacher"></i> Faculty Login
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>