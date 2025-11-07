<?php
/**
 * Explore Page
 * Discover and follow other users
 */

define('CYBERWEB_APP', true);
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

requireLogin();

$currentUser = getUserById($_SESSION['user_id']);
$db = getDB();

// Get users to follow (not blocked, not following, not self)
$search = $_GET['search'] ?? '';

$query = "
    SELECT u.user_id, u.username, u.profile_picture, u.bio,
           (SELECT COUNT(*) FROM follows WHERE following_id = u.user_id) as follower_count,
           (SELECT COUNT(*) FROM posts WHERE user_id = u.user_id) as post_count
    FROM users u
    WHERE u.user_id != ?
    AND u.is_blocked = FALSE
    AND u.user_id NOT IN (SELECT blocked_id FROM blocks WHERE blocker_id = ?)
    AND u.user_id NOT IN (SELECT blocker_id FROM blocks WHERE blocked_id = ?)
";

$params = [$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']];

if (!empty($search)) {
    $query .= " AND u.username LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY follower_count DESC, u.created_at DESC LIMIT 50";

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore - CyberWeb</title>
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
        <div class="explore-container">
            <h2>Explore Users</h2>

            <!-- Search Form -->
            <form method="GET" action="" class="search-form" style="margin-bottom: 2rem;">
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search users..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           style="width: 100%; padding: 0.75rem; background-color: var(--card-bg);
                                  border: 1px solid var(--border-color); color: var(--text-primary);
                                  border-radius: 8px; font-size: 1rem;">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>

            <!-- Users Grid -->
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-friends fa-3x"></i>
                    <h3>No users found</h3>
                    <p>Try adjusting your search</p>
                </div>
            <?php else: ?>
                <div class="users-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($users as $user): ?>
                    <div class="user-card" style="background-color: var(--card-bg); border: 1px solid var(--border-color);
                                                   border-radius: 12px; padding: 1.5rem; text-align: center;">
                        <a href="profile.php?username=<?php echo urlencode($user['username']); ?>">
                            <?php
                            $userPic = !empty($user['profile_picture']) ?
                                      htmlspecialchars($user['profile_picture']) :
                                      'default-avatar.svg';
                            ?>
                            <img src="uploads/profiles/<?php echo $userPic; ?>"
                                 alt="<?php echo htmlspecialchars($user['username']); ?>"
                                 style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;
                                        border: 3px solid var(--primary-blue); margin-bottom: 1rem;"
                                 onerror="this.src='uploads/profiles/default-avatar.svg';">
                        </a>

                        <h3 style="margin: 0 0 0.5rem 0;">
                            <a href="profile.php?username=<?php echo urlencode($user['username']); ?>"
                               style="color: var(--text-primary); text-decoration: none;">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </a>
                        </h3>

                        <?php if ($user['bio']): ?>
                            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 1rem;">
                                <?php echo htmlspecialchars(substr($user['bio'], 0, 100)); ?>
                                <?php if (strlen($user['bio']) > 100) echo '...'; ?>
                            </p>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: center; gap: 2rem; margin-bottom: 1rem;">
                            <div>
                                <strong style="color: var(--primary-blue);"><?php echo $user['post_count']; ?></strong>
                                <span style="color: var(--text-secondary); font-size: 0.875rem;"> posts</span>
                            </div>
                            <div>
                                <strong style="color: var(--primary-blue);"><?php echo $user['follower_count']; ?></strong>
                                <span style="color: var(--text-secondary); font-size: 0.875rem;"> followers</span>
                            </div>
                        </div>

                        <?php
                        $isFollowing = isFollowing($_SESSION['user_id'], $user['user_id']);
                        ?>
                        <button class="btn <?php echo $isFollowing ? 'btn-secondary' : 'btn-primary'; ?> btn-block"
                                onclick="toggleFollow(<?php echo $user['user_id']; ?>)">
                            <?php echo $isFollowing ? 'Unfollow' : 'Follow'; ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const csrfToken = '<?php echo $csrfToken; ?>';
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>