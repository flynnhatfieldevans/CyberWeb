<?php
/**
 * Admin Reports Page
 * Manage and resolve reports
 */

define('CYBERWEB_APP', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

requireAdmin();

$db = getDB();
$errors = [];
$success = '';

// Handle report actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid request";
    } else {
        $action = $_POST['action'] ?? '';
        $reportId = intval($_POST['report_id'] ?? 0);

        switch ($action) {
            case 'resolve':
                $adminNotes = sanitizeInput($_POST['admin_notes'] ?? '');
                $stmt = $db->prepare("UPDATE reports SET status = 'resolved', admin_notes = ?,
                                      resolved_at = NOW(), resolved_by = ? WHERE report_id = ?");
                $stmt->execute([$adminNotes, $_SESSION['user_id'], $reportId]);
                logAdminAction($_SESSION['user_id'], 'resolve_report', 'report', $reportId, $adminNotes);
                $success = "Report resolved successfully";
                break;

            case 'ignore':
                $adminNotes = sanitizeInput($_POST['admin_notes'] ?? '');
                $stmt = $db->prepare("UPDATE reports SET status = 'ignored', admin_notes = ?,
                                      resolved_at = NOW(), resolved_by = ? WHERE report_id = ?");
                $stmt->execute([$adminNotes, $_SESSION['user_id'], $reportId]);
                logAdminAction($_SESSION['user_id'], 'ignore_report', 'report', $reportId, $adminNotes);
                $success = "Report ignored";
                break;

            case 'block_user':
                $userId = intval($_POST['user_id'] ?? 0);
                $blockDays = intval($_POST['block_days'] ?? 0);

                if ($blockDays > 0) {
                    $blockUntil = date('Y-m-d H:i:s', strtotime("+$blockDays days"));
                    $stmt = $db->prepare("UPDATE users SET is_blocked = TRUE, blocked_until = ? WHERE user_id = ?");
                    $stmt->execute([$blockUntil, $userId]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET is_blocked = TRUE, blocked_until = NULL WHERE user_id = ?");
                    $stmt->execute([$userId]);
                }

                logAdminAction($_SESSION['user_id'], 'block_user', 'user', $userId, "Blocked for $blockDays days");
                $success = "User blocked successfully";
                break;

            case 'delete_post':
                $postId = intval($_POST['post_id'] ?? 0);
                $stmt = $db->prepare("SELECT * FROM posts WHERE post_id = ?");
                $stmt->execute([$postId]);
                $post = $stmt->fetch();

                if ($post) {
                    // Delete image
                    if ($post['image_path'] && file_exists(UPLOAD_PATH_POSTS . $post['image_path'])) {
                        unlink(UPLOAD_PATH_POSTS . $post['image_path']);
                    }

                    // Delete post
                    $stmt = $db->prepare("DELETE FROM posts WHERE post_id = ?");
                    $stmt->execute([$postId]);

                    logAdminAction($_SESSION['user_id'], 'delete_post', 'post', $postId);
                    $success = "Post deleted successfully";
                }
                break;
        }
    }
}

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$type = $_GET['type'] ?? 'all';

// Build query
$where = ["1=1"];
$params = [];

if ($status !== 'all') {
    $where[] = "r.status = ?";
    $params[] = $status;
}

if ($type === 'post') {
    $where[] = "r.reported_post_id IS NOT NULL";
} elseif ($type === 'user') {
    $where[] = "r.reported_user_id IS NOT NULL";
}

$whereClause = implode(' AND ', $where);

// Get reports
$stmt = $db->prepare("
    SELECT r.*,
           reporter.username as reporter_username,
           reported_user.username as reported_username,
           reported_user.user_id as reported_user_id_data,
           p.content as post_content,
           p.image_path as post_image,
           resolver.username as resolver_username
    FROM reports r
    LEFT JOIN users reporter ON r.reporter_id = reporter.user_id
    LEFT JOIN users reported_user ON r.reported_user_id = reported_user.user_id
    LEFT JOIN posts p ON r.reported_post_id = p.post_id
    LEFT JOIN users resolver ON r.resolved_by = resolver.user_id
    WHERE $whereClause
    ORDER BY
        CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END,
        r.created_at DESC
");
$stmt->execute($params);
$reports = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin - CyberWeb</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <li><a href="reports.php" class="active"><i class="fas fa-flag"></i> Reports</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="posts.php"><i class="fas fa-images"></i> Posts</a></li>
                <li><a href="audit-log.php"><i class="fas fa-history"></i> Audit Log</a></li>
            </ul>
        </div>

        <div class="admin-content">
            <h2>Reports Management</h2>

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

            <!-- Filters -->
            <div class="filters">
                <div class="filter-group">
                    <label>Status:</label>
                    <select onchange="updateFilter('status', this.value)">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="ignored" <?php echo $status === 'ignored' ? 'selected' : ''; ?>>Ignored</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Type:</label>
                    <select onchange="updateFilter('type', this.value)">
                        <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="post" <?php echo $type === 'post' ? 'selected' : ''; ?>>Posts</option>
                        <option value="user" <?php echo $type === 'user' ? 'selected' : ''; ?>>Users</option>
                    </select>
                </div>
            </div>

            <!-- Reports List -->
            <?php if (empty($reports)): ?>
                <p class="empty-state-text">No reports found</p>
            <?php else: ?>
                <div class="reports-list">
                    <?php foreach ($reports as $report): ?>
                    <div class="report-card">
                        <div class="report-header">
                            <div class="report-meta">
                                <span class="badge badge-<?php echo $report['status'] === 'pending' ? 'warning' : ($report['status'] === 'resolved' ? 'success' : 'secondary'); ?>">
                                    <?php echo ucfirst($report['status']); ?>
                                </span>
                                <span class="report-id">Report #<?php echo $report['report_id']; ?></span>
                                <span class="report-date"><?php echo date('Y-m-d H:i', strtotime($report['created_at'])); ?></span>
                            </div>
                        </div>

                        <div class="report-body">
                            <div class="report-info">
                                <p><strong>Reported by:</strong> <?php echo htmlspecialchars($report['reporter_username']); ?></p>
                                <p><strong>Type:</strong>
                                    <?php if ($report['reported_post_id']): ?>
                                        Post #<?php echo $report['reported_post_id']; ?>
                                    <?php else: ?>
                                        User: <?php echo htmlspecialchars($report['reported_username']); ?>
                                    <?php endif; ?>
                                </p>
                                <p><strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($report['reason'])); ?></p>

                                <?php if ($report['reported_post_id'] && $report['post_content']): ?>
                                    <div class="reported-content">
                                        <strong>Post Content:</strong>
                                        <p><?php echo nl2br(htmlspecialchars(substr($report['post_content'], 0, 200))); ?></p>
                                        <?php if ($report['post_image']): ?>
                                            <img src="../uploads/posts/<?php echo htmlspecialchars($report['post_image']); ?>"
                                                 alt="Post image" style="max-width: 200px;">
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($report['admin_notes']): ?>
                                    <div class="admin-notes">
                                        <strong>Admin Notes:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($report['admin_notes'])); ?></p>
                                        <small>Resolved by: <?php echo htmlspecialchars($report['resolver_username']); ?> on
                                               <?php echo date('Y-m-d H:i', strtotime($report['resolved_at'])); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($report['status'] === 'pending'): ?>
                            <div class="report-actions">
                                <button class="btn btn-sm btn-primary" onclick="showReportActions(<?php echo $report['report_id']; ?>)">
                                    <i class="fas fa-cog"></i> Take Action
                                </button>
                            </div>

                            <div class="report-actions-form" id="actions-<?php echo $report['report_id']; ?>" style="display:none;">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">

                                    <div class="form-group">
                                        <label>Admin Notes:</label>
                                        <textarea name="admin_notes" rows="3" class="form-control"></textarea>
                                    </div>

                                    <div class="button-group">
                                        <button type="submit" name="action" value="resolve" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Resolve
                                        </button>
                                        <button type="submit" name="action" value="ignore" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-times"></i> Ignore
                                        </button>

                                        <?php if ($report['reported_user_id']): ?>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="showBlockForm(<?php echo $report['report_id']; ?>, <?php echo $report['reported_user_id_data']; ?>)">
                                            <i class="fas fa-ban"></i> Block User
                                        </button>
                                        <?php endif; ?>

                                        <?php if ($report['reported_post_id']): ?>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeletePost(<?php echo $report['report_id']; ?>, <?php echo $report['reported_post_id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete Post
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </form>

                                <!-- Block User Form -->
                                <form method="POST" action="" id="block-form-<?php echo $report['report_id']; ?>" style="display:none; margin-top:10px;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                    <input type="hidden" name="action" value="block_user">
                                    <input type="hidden" name="user_id" value="<?php echo $report['reported_user_id_data']; ?>">

                                    <div class="form-group">
                                        <label>Block Duration (days, 0 for permanent):</label>
                                        <input type="number" name="block_days" min="0" value="30" class="form-control">
                                    </div>

                                    <button type="submit" class="btn btn-sm btn-danger">Confirm Block</button>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="hideBlockForm(<?php echo $report['report_id']; ?>)">Cancel</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const csrfToken = '<?php echo $csrfToken; ?>';

        function updateFilter(param, value) {
            const url = new URL(window.location);
            url.searchParams.set(param, value);
            window.location = url;
        }

        function showReportActions(reportId) {
            document.getElementById('actions-' + reportId).style.display = 'block';
        }

        function showBlockForm(reportId, userId) {
            document.getElementById('block-form-' + reportId).style.display = 'block';
        }

        function hideBlockForm(reportId) {
            document.getElementById('block-form-' + reportId).style.display = 'none';
        }

        function confirmDeletePost(reportId, postId) {
            if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="report_id" value="${reportId}">
                    <input type="hidden" name="post_id" value="${postId}">
                    <input type="hidden" name="action" value="delete_post">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
