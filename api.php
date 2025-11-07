<?php
/**
 * API Endpoint
 * Handles AJAX requests for likes, comments, follows, etc.
 */

define('CYBERWEB_APP', true);
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Require login for all API endpoints
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get request data
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();

try {
    switch ($action) {
        case 'like':
            // Toggle like on a post
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid CSRF token');
            }

            $postId = intval($_POST['post_id'] ?? 0);
            $userId = $_SESSION['user_id'];

            if (!$postId) {
                throw new Exception('Invalid post ID');
            }

            // Check if already liked
            if (hasLikedPost($userId, $postId)) {
                // Unlike
                $stmt = $db->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
                $stmt->execute([$userId, $postId]);
                $liked = false;
            } else {
                // Like
                $stmt = $db->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
                $stmt->execute([$userId, $postId]);
                $liked = true;
            }

            // Get updated like count
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
            $stmt->execute([$postId]);
            $result = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'liked' => $liked,
                'count' => $result['count']
            ]);
            break;

        case 'comment':
            // Add a comment
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid CSRF token');
            }

            $postId = intval($_POST['post_id'] ?? 0);
            $content = sanitizeInput($_POST['content'] ?? '');
            $parentCommentId = !empty($_POST['parent_comment_id']) ? intval($_POST['parent_comment_id']) : null;
            $userId = $_SESSION['user_id'];

            if (!$postId || empty($content)) {
                throw new Exception('Invalid input');
            }

            // Rate limiting
            if (!checkRateLimit($userId, 'comment', RATE_LIMIT_COMMENT, 60)) {
                throw new Exception('Too many comments. Please slow down.');
            }

            $stmt = $db->prepare("INSERT INTO comments (post_id, user_id, parent_comment_id, content)
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute([$postId, $userId, $parentCommentId, $content]);

            $commentId = $db->lastInsertId();

            // Get comment details
            $stmt = $db->prepare("
                SELECT c.*, u.username, u.profile_picture
                FROM comments c
                JOIN users u ON c.user_id = u.user_id
                WHERE c.comment_id = ?
            ");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'comment' => $comment
            ]);
            break;

        case 'get_comments':
            // Get comments for a post
            $postId = intval($_GET['post_id'] ?? 0);

            if (!$postId) {
                throw new Exception('Invalid post ID');
            }

            $stmt = $db->prepare("
                SELECT c.*, u.username, u.profile_picture
                FROM comments c
                JOIN users u ON c.user_id = u.user_id
                WHERE c.post_id = ?
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$postId]);
            $comments = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'comments' => $comments
            ]);
            break;

        case 'follow':
            // Follow/unfollow a user
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid CSRF token');
            }

            $targetUserId = intval($_POST['user_id'] ?? 0);
            $currentUserId = $_SESSION['user_id'];

            if (!$targetUserId || $targetUserId == $currentUserId) {
                throw new Exception('Invalid user ID');
            }

            // Check if already following
            if (isFollowing($currentUserId, $targetUserId)) {
                // Unfollow
                $stmt = $db->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
                $stmt->execute([$currentUserId, $targetUserId]);
                $following = false;
            } else {
                // Follow
                $stmt = $db->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)");
                $stmt->execute([$currentUserId, $targetUserId]);
                $following = true;
            }

            echo json_encode([
                'success' => true,
                'following' => $following
            ]);
            break;

        case 'block':
            // Block a user
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid CSRF token');
            }

            $targetUserId = intval($_POST['user_id'] ?? 0);
            $currentUserId = $_SESSION['user_id'];

            if (!$targetUserId || $targetUserId == $currentUserId) {
                throw new Exception('Invalid user ID');
            }

            // Block user
            $stmt = $db->prepare("INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (?, ?)");
            $stmt->execute([$currentUserId, $targetUserId]);

            // Remove follow relationships
            $stmt = $db->prepare("DELETE FROM follows WHERE
                                  (follower_id = ? AND following_id = ?) OR
                                  (follower_id = ? AND following_id = ?)");
            $stmt->execute([$currentUserId, $targetUserId, $targetUserId, $currentUserId]);

            echo json_encode(['success' => true]);
            break;

        case 'unblock':
            // Unblock a user
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid CSRF token');
            }

            $targetUserId = intval($_POST['user_id'] ?? 0);
            $currentUserId = $_SESSION['user_id'];

            if (!$targetUserId) {
                throw new Exception('Invalid user ID');
            }

            $stmt = $db->prepare("DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
            $stmt->execute([$currentUserId, $targetUserId]);

            echo json_encode(['success' => true]);
            break;

        case 'report':
    $post_id = $_POST['post_id'] ?? null;
    $user_id = $_POST['user_id'] ?? null;
    $report_type = $_POST['report_type'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    if (empty($reason)) {
        echo json_encode(['success' => false, 'error' => 'Please provide a reason for reporting']);
        exit;
    }
    
    try {
        if ($report_type === 'post' && $post_id) {
            // Get the user who owns the post
            $stmt = $db->prepare("SELECT user_id FROM posts WHERE post_id = ?");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch();
            
            if (!$post) {
                echo json_encode(['success' => false, 'error' => 'Post not found']);
                exit;
            }
            
            // Insert report for post
            $stmt = $db->prepare("
                INSERT INTO reports (reporter_id, reported_user_id, post_id, reason, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $post['user_id'], $post_id, $reason]);
            
        } else if ($report_type === 'user' && $user_id) {
            // Insert report for user
            $stmt = $db->prepare("
                INSERT INTO reports (reporter_id, reported_user_id, reason, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $user_id, $reason]);
            
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid report type']);
            exit;
        }
        
        echo json_encode(['success' => true, 'message' => 'Report submitted successfully. Thank you for helping keep our community safe.']);
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to submit report']);
    }
    break;

        case 'delete_post':
            // Delete own post
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid CSRF token');
            }

            $postId = intval($_POST['post_id'] ?? 0);
            $userId = $_SESSION['user_id'];

            if (!$postId) {
                throw new Exception('Invalid post ID');
            }

            // Check ownership
            $stmt = $db->prepare("SELECT * FROM posts WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $userId]);
            $post = $stmt->fetch();

            if (!$post) {
                throw new Exception('Post not found or unauthorized');
            }

            // Delete post image if exists
            if ($post['image_path'] && file_exists(UPLOAD_PATH_POSTS . $post['image_path'])) {
                unlink(UPLOAD_PATH_POSTS . $post['image_path']);
            }

            // Delete post (cascades to likes and comments)
            $stmt = $db->prepare("DELETE FROM posts WHERE post_id = ?");
            $stmt->execute([$postId]);

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
