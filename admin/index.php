<?php
/**
 * Admin Dashboard
 * View reports, manage users, and monitor activity
 */

define('CYBERWEB_APP', true);
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

requireAdmin();

$db = getDB();

// Get statistics
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_admin = FALSE");
$totalUsers = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM posts");
$totalPosts = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'");
$pendingReports = $stmt->fetch()['count'];

// Get recent reports
$stmt = $db->prepare("
    SELECT r.*,
           reporter.username as reporter_username,
           reported_user.username as reported_username,
           p.content as post_content,
           p.image_path as post_image
    FROM reports r
    LEFT JOIN users reporter ON r.reporter_id = reporter.user_id
    LEFT JOIN users reported_user ON r.reported_user_id = reported_user.user_id
    LEFT JOIN posts p ON r.reported_post_id = p.post_id
    ORDER BY r.created_at DESC
    LIMIT 20
");
$stmt->execute();
$recentReports = $stmt->fetchAll();

// Get recent users
$stmt = $db->prepare("
    SELECT user_id, username, email, created_at, last_login, is_blocked
    FROM users
    WHERE is_admin = FALSE
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentUsers = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CyberWeb</title>
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
                <li><a href="index.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="reports.php"><i class="fas fa-flag"></i> Reports (<?php echo $pendingReports; ?>)</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="audit-log.php"><i class="fas fa-history"></i> Audit Log</a></li>
            </ul>
        </div>

        <div class="admin-content">
            <h2>Dashboard</h2>

            <?php
            $flash = getFlashMessage();
            if ($flash):
            ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <p><?php echo htmlspecialchars($flash['message']); ?></p>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($totalUsers); ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-images"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($totalPosts); ?></h3>
                        <p>Total Posts</p>
                    </div>
                </div>

                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <i class="fas fa-flag"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($pendingReports); ?></h3>
                        <p>Pending Reports</p>
                    </div>
                </div>
            </div>

            <!-- Recent Reports -->
            <div class="admin-section">
                <div class="section-header">
                    <h3>Recent Reports</h3>
                    <a href="reports.php" class="btn btn-primary btn-sm">View All</a>
                </div>

                <?php if (empty($recentReports)): ?>
                    <p class="empty-state-text">No reports yet</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Reporter</th>
                                    <th>Type</th>
                                    <th>Reported Content</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentReports as $report): ?>
                                <tr>
                                    <td><?php echo $report['report_id']; ?></td>
                                    <td><?php echo htmlspecialchars($report['reporter_username']); ?></td>
                                    <td>
                                        <?php if ($report['reported_post_id']): ?>
                                            <span class="badge badge-primary">Post</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($report['reported_post_id']): ?>
                                            Post #<?php echo $report['reported_post_id']; ?>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($report['reported_username']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="reason-cell"><?php echo htmlspecialchars(substr($report['reason'], 0, 50)); ?>...</td>
                                    <td>
                                        <span class="badge badge-<?php echo $report['status'] === 'pending' ? 'warning' : ($report['status'] === 'resolved' ? 'success' : 'secondary'); ?>">
                                            <?php echo ucfirst($report['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($report['created_at'])); ?></td>
                                    <td>
                                        <a href="reports.php?id=<?php echo $report['report_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Users -->
            <div class="admin-section">
                <div class="section-header">
                    <h3>Recent Users</h3>
                    <a href="users.php" class="btn btn-primary btn-sm">View All</a>
                </div>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Joined</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td><?php echo $user['last_login'] ? timeAgo($user['last_login']) : 'Never'; ?></td>
                                <td>
                                    <?php if ($user['is_blocked']): ?>
                                        <span class="badge badge-danger">Blocked</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="users.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-primary">Manage</a>
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
    </script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
