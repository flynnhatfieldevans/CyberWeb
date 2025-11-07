<?php
/**
 * Login Page
 * Secure login (2FA temporarily disabled for testing)
 */

define('CYBERWEB_APP', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        // Check rate limiting
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        if (!checkRateLimit($ipAddress, 'login', MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_TIME / 60)) {
            $errors[] = "Too many login attempts. Please try again later.";
        } else {
            // Verify credentials
            if (isset($_POST['username']) && isset($_POST['password'])) {
                $username = sanitizeInput($_POST['username']);
                $password = $_POST['password'];

                $user = getUserByUsername($username);

                if (!$user || !verifyPassword($password, $user['password_hash'])) {
                    $errors[] = "Invalid username or password";
                } elseif (isUserBlocked($user['user_id'])) {
                    $errors[] = "Your account has been blocked. Please contact support.";
                } else {
                    // Login successful - create session directly
                    regenerateSession();
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['is_admin'] = (bool)$user['is_admin'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

                    // Update last login
                    $db = getDB();
                    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $stmt->execute([$user['user_id']]);

                    // Store session in database
                    $sessionId = session_id();
                    $stmt = $db->prepare("INSERT INTO sessions (session_id, user_id, ip_address, user_agent)
                                          VALUES (?, ?, ?, ?)
                                          ON DUPLICATE KEY UPDATE last_activity = NOW()");
                    $stmt->execute([
                        $sessionId,
                        $user['user_id'],
                        $_SERVER['REMOTE_ADDR'],
                        $_SERVER['HTTP_USER_AGENT']
                    ]);

                    // Redirect to appropriate page
                    if ($user['is_admin']) {
                        redirect('admin/index.php');
                    } else {
                        redirect('index.php');
                    }
                }
            } else {
                $errors[] = "Invalid request";
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CyberWeb</title>
    <link rel="icon" type="image/png" href="assets/images/favicon-16x16.png">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>CyberWeb</h1>
                <p>Welcome back</p>
            </div>

            <?php
            $flash = getFlashMessage();
            if ($flash):
            ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <p><?php echo htmlspecialchars($flash['message']); ?></p>
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

            <!-- Login Form -->
            <form method="POST" action="" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Register</a></p>
            </div>
        </div>
    </div>
</body>
</html>