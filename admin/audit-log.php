<?php
/**
 * Admin Audit Log Page
 * View all administrative actions and historical activity
 */

define('CYBERWEB_APP', true);
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

requireAdmin();

$db = getDB();

// Get filter parameters
$adminFilter = $_GET['admin'] ?? 'all';
$actionFilter = $_GET['action'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$limit = intval($_GET['limit'] ?? 50);

// Build query
$where = ["1=1"];
$params = [];

if ($adminFilter !== 'all') {
    $where[] = "al.admin_id = ?";
    $params[] = intval($adminFilter);
}

if ($actionFilter !== 'all') {
    $where[] = "al.action = ?";
    $params[] = $actionFilter;
}

if (!empty($dateFrom)) {
    $where[] = "al.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $where[] = "al.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereClause = implode(' AND ', $where);

// Get audit logs
$stmt = $db->prepare("
    SELECT al.*,
           u.username as admin_username
    FROM audit_log al
    LEFT JOIN users u ON al.admin_id = u.user_id
    WHERE $whereClause
    ORDER BY al.created_at DESC
    LIMIT ?
");
$params[] = $limit;
$stmt->execute($params);
$auditLogs = $stmt->fetchAll();

// Get all admins for filter dropdown
$stmt = $db->query("SELECT user_id, username FROM users WHERE is_admin = TRUE ORDER BY username");
$admins = $stmt->fetchAll();

// Get unique action types for filter dropdown
$stmt = $db->query("SELECT DISTINCT action FROM audit_log ORDER BY action");
$actionTypes = $stmt->fetchAll();

// Get statistics
$stmt = $db->query("SELECT COUNT(*) as count FROM audit_log");
$totalLogs = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM audit_log WHERE DATE(created_at) = CURDATE()");
$todayLogs = $stmt->fetch()['count'];

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log - Admin - CyberWeb</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .audit-log-entry {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .audit-log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .audit-log-action {
            font-weight: 600;
            color: var(--primary-blue);
        }
        .audit-log-timestamp {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .audit-log-details {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: var(--background);
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .audit-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .badge-block { background: #dc3545; color: white; }
        .badge-unblock { background: #28a745; color: white; }
        .badge-delete { background: #dc3545; color: white; }
        .badge-resolve { background: #28a745; color: white; }
        .badge-ignore { background: #6c757d; color: white; }
        .badge-default { background: var(--secondary-bg); color: var(--text-primary); }
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
                <li><a href="audit-log.php" class="active"><i class="fas fa-history"></i> Audit Log</a></li>
            </ul>
        </div>

        <div class="admin-content">
            <h2><i class="fas fa-history"></i> Audit Log</h2>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-box">
                    <h3><?php echo number_format($totalLogs); ?></h3>
                    <p>Total Actions</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo number_format($todayLogs); ?></h3>
                    <p>Actions Today</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo count($admins); ?></h3>
                    <p>Active Admins</p>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-item">
                        <label>Admin:</label>
                        <select name="admin" onchange="this.form.submit()">
                            <option value="all" <?php echo $adminFilter === 'all' ? 'selected' : ''; ?>>All Admins</option>
                            <?php foreach ($admins as $admin): ?>
                                <option value="<?php echo $admin['user_id']; ?>"
                                        <?php echo $adminFilter == $admin['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($admin['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label>Action Type:</label>
                        <select name="action" onchange="this.form.submit()">
                            <option value="all" <?php echo $actionFilter === 'all' ? 'selected' : ''; ?>>All Actions</option>
                            <?php foreach ($actionTypes as $actionType): ?>
                                <option value="<?php echo htmlspecialchars($actionType['action']); ?>"
                                        <?php echo $actionFilter === $actionType['action'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $actionType['action']))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label>Date From:</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>

                    <div class="filter-item">
                        <label>Date To:</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>

                    <div class="filter-item">
                        <label>Show:</label>
                        <select name="limit" onchange="this.form.submit()">
                            <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25 entries</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 entries</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 entries</option>
                            <option value="250" <?php echo $limit == 250 ? 'selected' : ''; ?>>250 entries</option>
                        </select>
                    </div>

                    <div class="filter-item" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>

            <?php if (!empty($dateFrom) || !empty($dateTo) || $adminFilter !== 'all' || $actionFilter !== 'all'): ?>
                <div style="margin-bottom: 1rem;">
                    <a href="audit-log.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            <?php endif; ?>

            <!-- Audit Log Entries -->
            <?php if (empty($auditLogs)): ?>
                <p class="empty-state-text">No audit log entries found</p>
            <?php else: ?>
                <div class="audit-logs-list">
                    <?php foreach ($auditLogs as $log): ?>
                        <div class="audit-log-entry">
                            <div class="audit-log-header">
                                <div>
                                    <?php
                                    $badgeClass = 'badge-default';
                                    switch ($log['action']) {
                                        case 'block_user':
                                            $badgeClass = 'badge-block';
                                            break;
                                        case 'unblock_user':
                                            $badgeClass = 'badge-unblock';
                                            break;
                                        case 'delete_user':
                                        case 'delete_post':
                                            $badgeClass = 'badge-delete';
                                            break;
                                        case 'resolve_report':
                                            $badgeClass = 'badge-resolve';
                                            break;
                                        case 'ignore_report':
                                            $badgeClass = 'badge-ignore';
                                            break;
                                    }
                                    ?>
                                    <span class="audit-badge <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))); ?>
                                    </span>
                                    <span class="audit-log-action">
                                        by <?php echo htmlspecialchars($log['admin_username']); ?>
                                    </span>
                                </div>
                                <div class="audit-log-timestamp">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                                </div>
                            </div>

                            <div class="audit-log-details">
                                <?php if ($log['target_type']): ?>
                                    <p>
                                        <strong>Target:</strong>
                                        <?php echo htmlspecialchars(ucfirst($log['target_type'])); ?>
                                        #<?php echo $log['target_id']; ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($log['details']): ?>
                                    <p>
                                        <strong>Details:</strong>
                                        <?php echo nl2br(htmlspecialchars($log['details'])); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($log['ip_address']): ?>
                                    <p>
                                        <strong>IP Address:</strong>
                                        <code><?php echo htmlspecialchars($log['ip_address']); ?></code>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 1.5rem; padding: 1rem; background: var(--secondary-bg); border-radius: 8px; text-align: center;">
                    <p style="color: var(--text-secondary); margin: 0;">
                        Showing <?php echo count($auditLogs); ?> of <?php echo number_format($totalLogs); ?> total entries
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const csrfToken = '<?php echo $csrfToken; ?>';
    </script>
</body>
</html>
