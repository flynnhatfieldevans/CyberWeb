<?php
/**
 * Main Feed Page
 * Display posts from followed users
 */

define('CYBERWEB_APP', true);
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

requireLogin();

$currentUser = getUserById($_SESSION['user_id']);
$db = getDB();

// Get posts from followed users and own posts
// Fixed: ORDER BY p.created_at DESC for newest first
$stmt = $db->prepare("
    SELECT p.*, u.username, u.profile_picture,
           (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
           (SELECT COUNT(*) > 0 FROM likes WHERE post_id = p.post_id AND user_id = ?) as user_liked
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.user_id IN (
        SELECT following_id FROM follows WHERE follower_id = ?
    ) OR p.user_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$posts = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed - CyberWeb</title>
    <link rel="icon" type="image/png" href="assets/images/favicon-16x16.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-left">
                <a href="index.php" class="logo">CyberWeb</a>
            </div>
            <div class="nav-center">
                <a href="explore.php" class="nav-link">
                    <i class="fas fa-compass"></i> Explore
                </a>
            </div>
            <div class="nav-right">
                <div class="profile-menu">
                    <?php
                    // Fixed: Handle empty profile picture
                    $profilePic = !empty($currentUser['profile_picture']) ?
                                  htmlspecialchars($currentUser['profile_picture']) :
                                  'default-avatar.svg';
                    ?>
                    <img src="uploads/profiles/<?php echo $profilePic; ?>"
                         alt="Profile" class="profile-pic-small" id="profileMenuBtn"
                         onerror="this.src='uploads/profiles/default-avatar.svg';">
                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="profile.php?username=<?php echo urlencode($currentUser['username']); ?>">
                            <i class="fas fa-user"></i> View Profile
                        </a>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <?php if ($currentUser['is_admin']): ?>
                        <a href="admin/index.php">
                            <i class="fas fa-shield-alt"></i> Admin Panel
                        </a>
                        <?php endif; ?>
                        <hr>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="main-container">
        <div class="feed-container">
            <div class="feed-header">
                <h2>Your Feed</h2>
            </div>

            <?php
            $flash = getFlashMessage();
            if ($flash):
            ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <p><?php echo htmlspecialchars($flash['message']); ?></p>
                </div>
            <?php endif; ?>

            <!-- Posts Feed -->
            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <i class="fas fa-rss fa-3x"></i>
                    <h3>Your feed is empty</h3>
                    <p>Start following users to see their posts here!</p>
                    <a href="explore.php" class="btn btn-primary">
                        <i class="fas fa-compass"></i> Explore Users
                    </a>
                </div>
            <?php else: ?>
                <div class="posts-feed">
                    <?php foreach ($posts as $post): ?>
                    <div class="post-card" data-post-id="<?php echo $post['post_id']; ?>">
                        <!-- Post Header -->
                        <div class="post-header">
                            <div class="post-user-info">
                                <?php
                                // Fixed: Handle empty profile picture
                                $postUserPic = !empty($post['profile_picture']) ?
                                              htmlspecialchars($post['profile_picture']) :
                                              'default-avatar.svg';
                                ?>
                                <img src="uploads/profiles/<?php echo $postUserPic; ?>"
                                     alt="<?php echo htmlspecialchars($post['username']); ?>"
                                     class="post-profile-pic"
                                     onerror="this.src='uploads/profiles/default-avatar.svg';">
                                <div>
                                    <a href="profile.php?username=<?php echo urlencode($post['username']); ?>"
                                       class="post-username">
                                        <?php echo htmlspecialchars($post['username']); ?>
                                    </a>
                                    <div class="post-time">
                                        <?php
                                        // Display: "X hrs ago" if within 24 hours, otherwise "dd/mm/yyyy"
                                        echo formatPostDate($post['created_at']);
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="post-menu">
                                <button class="btn-icon" onclick="togglePostMenu(<?php echo $post['post_id']; ?>)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="post-dropdown" id="postMenu<?php echo $post['post_id']; ?>">
                                    <?php if ($post['user_id'] == $_SESSION['user_id']): ?>
                                        <!-- Fixed: Delete option for own posts -->
                                        <a href="#" onclick="deletePost(<?php echo $post['post_id']; ?>); return false;">
                                            <i class="fas fa-trash"></i> Delete Post
                                        </a>
                                    <?php else: ?>
                                        <a href="#" onclick="reportPost(<?php echo $post['post_id']; ?>); return false;">
                                            <i class="fas fa-flag"></i> Report Post
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Post Content -->
                        <?php if ($post['content']): ?>
                        <div class="post-content">
                            <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Post Image -->
                        <?php if ($post['image_path']): ?>
                        <div class="post-image">
                            <img src="uploads/posts/<?php echo htmlspecialchars($post['image_path']); ?>"
                                 alt="Post image">
                        </div>
                        <?php endif; ?>

                        <!-- Post Location -->
                        <?php if ($post['location_name']): ?>
                        <div class="post-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($post['location_name']); ?>
                        </div>
                        <?php endif; ?>

                        <!-- Post Actions -->
                        <div class="post-actions">
                            <!--Like button functionality -->
                            <button class="btn-action like-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>"
                                    onclick="toggleLike(<?php echo $post['post_id']; ?>)">
                                <i class="<?php echo $post['user_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                <span class="like-count"><?php echo $post['like_count']; ?></span>
                            </button>
                            <!--Comment button functionality -->
                            <button class="btn-action comment-btn" onclick="showComments(<?php echo $post['post_id']; ?>)">
                                <i class="far fa-comment"></i>
                                <span class="comment-count"><?php echo $post['comment_count']; ?></span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Report Modal (handles both posts and users) -->
<div class="modal" id="reportModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Report</h3>
            <button class="btn-close" onclick="closeReportModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form onsubmit="submitReport(event)">
            <input type="hidden" name="post_id" id="reportPostId">
            <input type="hidden" name="user_id" id="reportUserId">
            <input type="hidden" name="report_type" id="reportType">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <div class="form-group">
                <label for="reportReason">Reason for reporting:</label>
                <textarea name="reason" id="reportReason" rows="5" required
                          placeholder="Please describe why you're reporting this..."></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeReportModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Report</button>
            </div>
        </form>
    </div>
</div>

    <!-- Floating Action Button -->
    <a href="create-post.php" class="fab" title="Create Post">
        <i class="fas fa-plus"></i>
    </a>

    <script>
        const csrfToken = '<?php echo $csrfToken; ?>';
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>