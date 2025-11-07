<?php
/**
 * Utility Functions
 * Email, notifications, and helper functions
 */

if (!defined('CYBERWEB_APP')) {
    die('Direct access not permitted');
}

/**
 * Send email using PHPMailer or mail()
 * For production, use PHPMailer with SMTP
 */
function sendEmail($to, $subject, $body) {
    // Basic mail() function for demonstration
    // In production, use PHPMailer with proper SMTP configuration
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">" . "\r\n";

    return mail($to, $subject, $body, $headers);
}

/**
 * Send 2FA OTP email
 */
function send2FAOTP($email, $otp) {
    $subject = "CyberWeb - Your Login Code";
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background-color: #000; color: #fff; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #0066ff; padding: 20px; text-align: center; }
            .content { background-color: #1a1a1a; padding: 30px; }
            .otp-code { font-size: 32px; font-weight: bold; color: #0066ff; text-align: center; letter-spacing: 10px; padding: 20px; }
            .footer { text-align: center; padding: 20px; color: #888; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>CyberWeb</h1>
            </div>
            <div class='content'>
                <h2>Your Login Code</h2>
                <p>Use the following code to complete your login:</p>
                <div class='otp-code'>$otp</div>
                <p>This code will expire in 5 minutes.</p>
                <p>If you didn't request this code, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " CyberWeb. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail($email, $subject, $body);
}

/**
 * Get user by ID
 */
function getUserById($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Get user by username
 */
function getUserByUsername($username) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

/**
 * Get user by email
 */
function getUserByEmail($email) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

/**
 * Check if user is blocked
 */
function isUserBlocked($userId) {
    $user = getUserById($userId);
    if (!$user) return false;

    if ($user['is_blocked']) {
        if ($user['blocked_until'] && strtotime($user['blocked_until']) > time()) {
            return true;
        } elseif (!$user['blocked_until']) {
            return true;
        } else {
            // Unblock user if temporary block expired
            $db = getDB();
            $stmt = $db->prepare("UPDATE users SET is_blocked = FALSE, blocked_until = NULL WHERE user_id = ?");
            $stmt->execute([$userId]);
            return false;
        }
    }

    return false;
}

/**
 * Check if user A has blocked user B
 */
function hasBlocked($blockerUserId, $blockedUserId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->execute([$blockerUserId, $blockedUserId]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * Check if user is following another user
 */
function isFollowing($followerId, $followingId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$followerId, $followingId]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * Get post by ID with user information
 */
function getPostById($postId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, u.username, u.profile_picture,
               (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count,
               (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.post_id = ?
    ");
    $stmt->execute([$postId]);
    return $stmt->fetch();
}

/**
 * Check if user has liked a post
 */
function hasLikedPost($userId, $postId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM likes WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$userId, $postId]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * Get follower count
 */
function getFollowerCount($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM follows WHERE following_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * Get following count
 */
function getFollowingCount($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM follows WHERE follower_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * Get post count
 */
function getPostCount($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM posts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * Format timestamp for display
 */
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;

    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date("M j, Y", $time);
    }
}

/**
 * Format post date: "X hrs ago" if within 24 hours, otherwise "dd/mm/yyyy"
 */
function formatPostDate($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;

    // Within 24 hours: show hours ago
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        if ($hours == 0) {
            return "less than 1hr ago";
        }
        return $hours . "hrs ago";
    }

    // Over 24 hours: show dd/mm/yyyy
    return date("d/m/Y", $time);
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Set flash message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}
