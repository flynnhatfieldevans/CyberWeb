<?php
/**
 * Admin Users Management Page
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

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $userId = intval($_POST['user_id'] ?? 0);

    switch ($action) {
        case 'delete_user':
            $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user && !$user['is_admin']) {
                // Delete user's posts images
                $stmt = $db->prepare("SELECT image_path FROM posts WHERE user_id = ? AND image_path IS NOT NULL");
                $stmt->execute([$userId]);
                $posts = $stmt->fetchAll();
                foreach ($posts as $post) {
                    $imagePath = UPLOAD_PATH_POSTS . $post['image_path'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }

                // Delete user's profile picture
                if ($user['profile_picture'] !== 'default-avatar.svg') {
                    $profilePath = UPLOAD_PATH_PROFILES . $user['profile_picture'];
                    if (file_exists($profilePath)) {
                        unlink($profilePath);
                    }
                }

                // Delete user (cascades to all related data)
                $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);

                logAdminAction($_SESSION['user_id'], 'delete_user', 'user', $userId, "Deleted user: {$user['username']}");
                $success = "User deleted successfully";
            }
            break;

        case 'block_user':
            $blockDays = intval($_POST['block_days'] ?? 0);
            if ($blockDays > 0) {
                $blockUntil = date('Y-m-d H:i:s', strtotime("+$blockDays days"));
                $stmt = $db->prepare("UPDATE users SET is_blocked = TRUE, blocked_until = ? WHERE user_id = ?");
                $stmt->execute([$blockUntil, $userId]);
                logAdminAction($_SESSION['user_id'], 'block_user', 'user', $userId, "Blocked for $blockDays days");
            } else {
                $stmt = $db->prepare("UPDATE users SET is_blocked = TRUE, blocked_until = NULL WHERE user_id = ?");
                $stmt->execute([$userId]);
                logAdminAction($_SESSION['user_id'], 'block_user', 'user', $userId, "Blocked permanently");
            }
            $success = "User blocked successfully";
            break;

        case 'unblock_user':
            $stmt = $db->prepare("UPDATE users SET is_blocked = FALSE, blocked_until = NULL WHERE user_id = ?");
            $stmt->execute([$userId]);
            logAdminAction($_SESSION['user_id'], 'unblock_user', 'user', $userId);
            $success = "User unblocked successfully";
            break;
    }
}

// Get all users
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';

$where = ["is_admin = FALSE"];
$params = [];

if (!empty($search)) {
    $where[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status === 'blocked') {
    $where[] = "is_blocked = TRUE";
} elseif ($status === 'active') {
    $where[] = "is_blocked = FALSE";
}

$whereClause = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM posts WHERE user_id = u.user_id) as post_count,
           (SELECT COUNT(*) FROM follows WHERE follower_id = u.user_id) as following_count,
           (SELECT COUNT(*) FROM follows WHERE following_id = u.user_id) as follower_count
    FROM users u
    WHERE $whereClause
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Admin - CyberWeb</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
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

    <div class="admin-container">
        <div class="admin-sidebar">
            <ul class="admin-menu">
                <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="reports.php"><i class="fas fa-flag"></i> Reports</a></li>
                <li><a href="users.php" class="active"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="audit-log.php"><i class="fas fa-history"></i> Audit Log</a></li>
            </ul>
        </div>

        <div class="admin-content">
            <h2>Users Management</h2>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>

            <!-- Search and Filter -->
            <div class="filters">
                <form method="GET" action="" style="display: flex; gap: 1rem; flex: 1;">
                    <div class="filter-group" style="flex: 1;">
                        <input type="text" name="search" placeholder="Search users..."
                               value="<?php echo htmlspecialchars($search); ?>"
                               style="width: 100%; padding: 0.5rem; background-color: var(--secondary-bg);
                                      border: 1px solid var(--border-color); color: var(--text-primary);
                                      border-radius: 4px;">
                    </div>
                    <div class="filter-group">
                        <select name="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Users</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="blocked" <?php echo $status === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>

            <!-- Users Table -->
            <div class="admin-section">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Posts</th>
                                <th>Followers</th>
                                <th>Joined</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td>
                                    <a href="../profile.php?username=<?php echo urlencode($user['username']); ?>"
                                       target="_blank" style="color: var(--primary-blue);">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo $user['post_count']; ?></td>
                                <td><?php echo $user['follower_count']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td><?php echo $user['last_login'] ? timeAgo($user['last_login']) : 'Never'; ?></td>
                                <td>
                                    <?php if ($user['is_blocked']): ?>
                                        <span class="badge badge-danger">Blocked</span>
                                        <?php if ($user['blocked_until']): ?>
                                            <br><small style="color: var(--text-secondary);">
                                                Until: <?php echo date('Y-m-d', strtotime($user['blocked_until'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="button-group">
                                        <?php if ($user['is_blocked']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="action" value="unblock_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check"></i> Unblock
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-warning" onclick="showBlockForm(<?php echo $user['user_id']; ?>)">
                                                <i class="fas fa-ban"></i> Block
                                            </button>
                                        <?php endif; ?>

                                        <button class="btn btn-sm btn-danger" onclick="confirmDeleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>

                                    <!-- Block Form (hidden) -->
                                    <form method="POST" id="block-form-<?php echo $user['user_id']; ?>" style="display: none; margin-top: 10px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="block_user">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                                            <input type="number" name="block_days" placeholder="Days (0=permanent)"
                                                   min="0" value="30" style="width: 150px; padding: 0.25rem;
                                                   background-color: var(--secondary-bg); border: 1px solid var(--border-color);
                                                   color: var(--text-primary); border-radius: 4px;">
                                            <button type="submit" class="btn btn-sm btn-danger">Confirm</button>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="hideBlockForm(<?php echo $user['user_id']; ?>)">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = '<?php echo $csrfToken; ?>';

        function showBlockForm(userId) {
            document.getElementById('block-form-' + userId).style.display = 'block';
        }

        function hideBlockForm(userId) {
            document.getElementById('block-form-' + userId).style.display = 'none';
        }

        function confirmDeleteUser(userId, username) {
            if (confirm(`Are you sure you want to delete user "${username}"? This will delete all their posts, comments, and data. This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
