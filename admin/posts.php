<?php
/**
 * Admin Posts Management Page
 * View and manage all posts on the platform
 */

define('CYBERWEB_APP', true);
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

requireAdmin();

$db = getDB();
$success = '';
$errors = [];

// Handle post deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $postId = intval($_POST['post_id'] ?? 0);

    if ($action === 'delete_post' && $postId > 0) {
        // Get post details
        $stmt = $db->prepare("SELECT * FROM posts WHERE post_id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();

        if ($post) {
            // Delete post image if exists
            if ($post['image_path'] && file_exists(UPLOAD_PATH_POSTS . $post['image_path'])) {
                unlink(UPLOAD_PATH_POSTS . $post['image_path']);
            }

            // Delete post (cascades to likes and comments)
            $stmt = $db->prepare("DELETE FROM posts WHERE post_id = ?");
            $stmt->execute([$postId]);

            // Log admin action
            logAdminAction($_SESSION['user_id'], 'delete_post', 'post', $postId, "Deleted post by user ID: {$post['user_id']}");

            $success = "Post deleted successfully";
        } else {
            $errors[] = "Post not found";
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$userId = $_GET['user_id'] ?? '';
$hasImage = $_GET['has_image'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'newest';

// Build query
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.content LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($userId)) {
    $where[] = "p.user_id = ?";
    $params[] = intval($userId);
}

if ($hasImage === 'yes') {
    $where[] = "p.image_path IS NOT NULL";
} elseif ($hasImage === 'no') {
    $where[] = "p.image_path IS NULL";
}

$whereClause = implode(' AND ', $where);

// Determine sort order
$orderBy = match($sortBy) {
    'oldest' => 'p.created_at ASC',
    'most_liked' => 'like_count DESC',
    'most_commented' => 'comment_count DESC',
    default => 'p.created_at DESC', // newest
};

// Get posts with statistics
$stmt = $db->prepare("
    SELECT p.*,
           u.username,
           u.profile_picture,
           (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
           (SELECT COUNT(*) FROM reports WHERE reported_post_id = p.post_id) as report_count
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    WHERE $whereClause
    ORDER BY $orderBy
    LIMIT 50
");
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Get total post count
$stmt = $db->query("SELECT COUNT(*) as count FROM posts");
$totalPosts = $stmt->fetch()['count'];

// Get posts with images count
$stmt = $db->query("SELECT COUNT(*) as count FROM posts WHERE image_path IS NOT NULL");
$postsWithImages = $stmt->fetch()['count'];

// Get all users for filter dropdown
$stmt = $db->query("SELECT user_id, username FROM users WHERE is_admin = FALSE ORDER BY username");
$users = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posts Management - Admin - CyberWeb</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .post-card {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .post-header {
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        .post-user {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .post-user img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }
        .post-user-info h4 {
            margin: 0;
            font-size: 0.9rem;
        }
        .post-user-info small {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        .post-content {
            padding: 1rem;
        }
        .post-content p {
            margin: 0 0 0.5rem 0;
            word-wrap: break-word;
        }
        .post-image {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
        }
        .post-stats {
            padding: 0.75rem 1rem;
            background: var(--background);
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .post-stats span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .post-actions {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.5rem;
        }
        .stats-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-box {
            flex: 1;
            background: var(--secondary-bg);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        .stat-box h3 {
            margin: 0;
            color: var(--primary-blue);
            font-size: 1.8rem;
        }
        .stat-box p {
            margin: 0.5rem 0 0 0;
            color: var(--text-secondary);
        }
        .filter-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .filter-item {
            flex: 1;
            min-width: 150px;
        }
        .filter-item label {
            display: block;
            margin-bottom: 0.25rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .filter-item select,
        .filter-item input {
            width: 100%;
            padding: 0.5rem;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 4px;
        }
        .report-badge {
            background: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-left">
                <h1 class="logo">CyberWeb Admin</h1>
            </div>
            <div class="nav-right">
                <a href="../index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Main Site
                </a>
                <a href="../logout.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Admin Container -->
    <div class="admin-container">
        <div class="admin-sidebar">
            <ul class="admin-menu">
                <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="reports.php"><i class="fas fa-flag"></i> Reports</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="posts.php" class="active"><i class="fas fa-images"></i> Posts</a></li>
                <li><a href="audit-log.php"><i class="fas fa-history"></i> Audit Log</a></li>
            </ul>
        </div>

        <div class="admin-content">
            <h2><i class="fas fa-images"></i> Posts Management</h2>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-box">
                    <h3><?php echo number_format($totalPosts); ?></h3>
                    <p>Total Posts</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo number_format($postsWithImages); ?></h3>
                    <p>Posts with Images</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo count($posts); ?></h3>
                    <p>Showing</p>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-item">
                        <label>Search:</label>
                        <input type="text" name="search" placeholder="Search content or username..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-item">
                        <label>User:</label>
                        <select name="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>"
                                        <?php echo $userId == $user['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label>Has Image:</label>
                        <select name="has_image">
                            <option value="all" <?php echo $hasImage === 'all' ? 'selected' : ''; ?>>All Posts</option>
                            <option value="yes" <?php echo $hasImage === 'yes' ? 'selected' : ''; ?>>With Images</option>
                            <option value="no" <?php echo $hasImage === 'no' ? 'selected' : ''; ?>>Text Only</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label>Sort By:</label>
                        <select name="sort">
                            <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="most_liked" <?php echo $sortBy === 'most_liked' ? 'selected' : ''; ?>>Most Liked</option>
                            <option value="most_commented" <?php echo $sortBy === 'most_commented' ? 'selected' : ''; ?>>Most Commented</option>
                        </select>
                    </div>

                    <div class="filter-item" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>

            <?php if (!empty($search) || !empty($userId) || $hasImage !== 'all' || $sortBy !== 'newest'): ?>
                <div style="margin-bottom: 1rem;">
                    <a href="posts.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            <?php endif; ?>

            <!-- Posts Grid -->
            <?php if (empty($posts)): ?>
                <p class="empty-state-text">No posts found</p>
            <?php else: ?>
                <div class="posts-grid">
                    <?php foreach ($posts as $post): ?>
                        <div class="post-card">
                            <div class="post-header">
                                <div class="post-user">
                                    <img src="../uploads/profiles/<?php echo htmlspecialchars($post['profile_picture']); ?>"
                                         alt="<?php echo htmlspecialchars($post['username']); ?>">
                                    <div class="post-user-info">
                                        <h4><?php echo htmlspecialchars($post['username']); ?></h4>
                                        <small><?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></small>
                                    </div>
                                </div>
                                <?php if ($post['report_count'] > 0): ?>
                                    <span class="report-badge">
                                        <i class="fas fa-flag"></i> <?php echo $post['report_count']; ?> Report<?php echo $post['report_count'] > 1 ? 's' : ''; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($post['content']): ?>
                                <div class="post-content">
                                    <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($post['image_path']): ?>
                                <img src="../uploads/posts/<?php echo htmlspecialchars($post['image_path']); ?>"
                                     alt="Post image" class="post-image">
                            <?php endif; ?>

                            <?php if ($post['location_name']): ?>
                                <div class="post-content" style="padding-top: 0;">
                                    <small style="color: var(--text-secondary);">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($post['location_name']); ?>
                                    </small>
                                </div>
                            <?php endif; ?>

                            <div class="post-stats">
                                <span>
                                    <i class="fas fa-heart"></i>
                                    <?php echo number_format($post['like_count']); ?>
                                </span>
                                <span>
                                    <i class="fas fa-comment"></i>
                                    <?php echo number_format($post['comment_count']); ?>
                                </span>
                                <span>
                                    <i class="fas fa-hashtag"></i>
                                    Post #<?php echo $post['post_id']; ?>
                                </span>
                            </div>

                            <div class="post-actions">
                                <a href="../index.php#post-<?php echo $post['post_id']; ?>"
                                   class="btn btn-sm btn-secondary" target="_blank">
                                    <i class="fas fa-external-link-alt"></i> View
                                </a>
                                <button class="btn btn-sm btn-danger"
                                        onclick="confirmDeletePost(<?php echo $post['post_id']; ?>, '<?php echo htmlspecialchars($post['username']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($posts) >= 50): ?>
                    <div style="margin-top: 1.5rem; padding: 1rem; background: var(--secondary-bg); border-radius: 8px; text-align: center;">
                        <p style="color: var(--text-secondary); margin: 0;">
                            Showing first 50 posts. Use filters to narrow down results.
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const csrfToken = '<?php echo $csrfToken; ?>';

        function confirmDeletePost(postId, username) {
            if (confirm(`Are you sure you want to delete post #${postId} by ${username}?\n\nThis will delete:\n- The post content\n- Associated image (if any)\n- All likes\n- All comments\n\nThis action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="action" value="delete_post">
                    <input type="hidden" name="post_id" value="${postId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
