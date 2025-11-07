<?php
/**
 * Logout Page
 * Securely destroy session and logout user
 */

define('CYBERWEB_APP', true);
require_once 'config.php';
require_once 'includes/db.php';

// Delete session from database
if (isset($_SESSION['user_id'])) {
    $db = Database::getInstance()->getConnection();
    $sessionId = session_id();
    $stmt = $db->prepare("DELETE FROM sessions WHERE session_id = ?");
    $stmt->execute([$sessionId]);
}

// Destroy session
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect to login
header("Location: login.php");
exit;
