<?php
/**
 * Profile Page
 * View user profiles and their posts
 */

define('CYBERWEB_APP', true);
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

requireLogin();

$username = $_GET['username'] ?? $_SESSION['username'];
$profileUser = getUserByUsername($username);

if (!$profileUser) {
    setFlashMessage('error', 'User not found');
    redirect('index.php');
}

$isOwnProfile = $profileUser['user_id'] == $_SESSION['user_id'];
$currentUserId = $_SESSION['user_id'];
$db = getDB();

// Check if current user has blocked this profile
$hasBlockedProfile = hasBlocked($currentUserId, $profileUser['user_id']);
$isBlockedByProfile = hasBlocked($profileUser['user_id'], $currentUserId);

// Get user statistics
$followerCount = getFollowerCount($profileUser['user_id']);
$followingCount = getFollowingCount($profileUser['user_id']);
$postCount = getPostCount($profileUser['user_id']);
$isFollowingUser = isFollowing($currentUserId, $profileUser['user_id']);

// Get user's posts
$stmt = $db->prepare("
    SELECT p.*, u.username, u.profile_picture,
           (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
           (SELECT COUNT(*) > 0 FROM likes WHERE post_id = p.post_id AND user_id = ?) as user_liked
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$currentUserId, $profileUser['user_id']]);
$posts = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
$currentUser = getUserById($currentUserId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profileUser['username']); ?> - CyberWeb</title>
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
            <div class="nav-right">
                <div class="profile-menu">
                    <?php
                    $currentUserPic = !empty($currentUser['profile_picture']) ?
                                     htmlspecialchars($currentUser['profile_picture']) :
                                     'default-avatar.svg';
                    ?>
                    <img src="uploads/profiles/<?php echo $currentUserPic; ?>"
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
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-pic-large-wrapper">
                    <?php
                    $profileUserPic = !empty($profileUser['profile_picture']) ?
                                     htmlspecialchars($profileUser['profile_picture']) :
                                     'default-avatar.svg';
                    ?>
                    <img src="uploads/profiles/<?php echo $profileUserPic; ?>"
                         alt="<?php echo htmlspecialchars($profileUser['username']); ?>"
                         class="profile-pic-large"
                         onerror="this.src='uploads/profiles/default-avatar.svg';">
                </div>

                <div class="profile-info">
                    <div class="profile-username-row">
                        <h2><?php echo htmlspecialchars($profileUser['username']); ?></h2>

                        <?php if ($isOwnProfile): ?>
                            <a href="settings.php" class="btn btn-secondary">
                                <i class="fas fa-cog"></i> Edit Profile
                            </a>
                        <?php elseif (!$hasBlockedProfile && !$isBlockedByProfile): ?>
                            <button class="btn <?php echo $isFollowingUser ? 'btn-secondary' : 'btn-primary'; ?>"
                                    id="followBtn"
                                    onclick="toggleFollow(<?php echo $profileUser['user_id']; ?>)">
                                <?php echo $isFollowingUser ? 'Unfollow' : 'Follow'; ?>
                            </button>
                        <?php endif; ?>

                        <?php if (!$isOwnProfile): ?>
                            <div class="profile-menu-btn">
                                <button class="btn btn-icon" onclick="toggleProfileMenu()">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu" id="profileActionMenu">
                                    <a href="#" onclick="reportUser(<?php echo $profileUser['user_id']; ?>); return false;">
                                        <i class="fas fa-flag"></i> Report User
                                    </a>
                                    <a href="#" onclick="blockUser('<?php echo htmlspecialchars($profileUser['user_id']); ?>'); return false;">
                                        <i class="fas fa-ban"></i> Block
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="profile-stats">
                        <div class="stat">
                            <span class="stat-value"><?php echo $postCount; ?></span>
                            <span class="stat-label">Posts</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?php echo $followerCount; ?></span>
                            <span class="stat-label">Followers</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?php echo $followingCount; ?></span>
                            <span class="stat-label">Following</span>
                        </div>
                    </div>

                    <?php if ($profileUser['bio']): ?>
                        <div class="profile-bio">
                            <p><?php echo nl2br(htmlspecialchars($profileUser['bio'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Posts Grid -->
            <div class="profile-posts">
                <h3>Posts</h3>

                <?php if (empty($posts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-images fa-3x"></i>
                        <p>No posts yet</p>
                    </div>
                <?php else: ?>
                    <div class="posts-grid">
                        <?php foreach ($posts as $post): ?>
                        <div class="post-card" data-post-id="<?php echo $post['post_id']; ?>">
                            <?php if ($post['image_path']): ?>
                            <div class="post-image">
                                <img src="uploads/posts/<?php echo htmlspecialchars($post['image_path']); ?>"
                                     alt="Post image">
                            </div>
                            <?php endif; ?>

                            <?php if ($post['content']): ?>
                            <div class="post-content">
                                <p><?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 150))); ?>
                                   <?php if (strlen($post['content']) > 150) echo '...'; ?>
                                </p>
                            </div>
                            <?php endif; ?>

                            <div class="post-footer">
                                <div class="post-actions">
                                    <button class="btn-action like-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>"
                                            onclick="toggleLike(<?php echo $post['post_id']; ?>)">
                                        <i class="<?php echo $post['user_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                        <span class="like-count"><?php echo $post['like_count']; ?></span>
                                    </button>
                                    <button class="btn-action" onclick="showComments(<?php echo $post['post_id']; ?>)">
                                        <i class="far fa-comment"></i>
                                        <span class="comment-count"><?php echo $post['comment_count']; ?></span>
                                    </button>
                                </div>
                                <div class="post-time">
                                    <?php echo formatPostDate($post['created_at']); ?>
                                </div>
                            </div>

                            <?php if ($isOwnProfile): ?>
                            <div class="post-actions-menu">
                                <button class="btn-icon" onclick="deletePost(<?php echo $post['post_id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
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

    <script>
        const csrfToken = '<?php echo $csrfToken; ?>';
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>