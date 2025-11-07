<?php
/**
 * Login Page
 * Secure login with 2FA via email OTP
 */

define('CYBERWEB_APP', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

// Handle cancel/back to login
if (isset($_GET['cancel']) && $_GET['cancel'] == '1') {
    unset($_SESSION['login_user_id']);
    unset($_SESSION['login_username']);
    redirect('login.php');
}

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];
$showOTPForm = false;
$success = [];

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
            // Step 1: Verify credentials
            if (isset($_POST['username']) && isset($_POST['password'])) {
                $username = sanitizeInput($_POST['username']);
                $password = $_POST['password'];

                $user = getUserByUsername($username);

                if (!$user || !verifyPassword($password, $user['password_hash'])) {
                    $errors[] = "Invalid username or password";
                } elseif (isUserBlocked($user['user_id'])) {
                    $errors[] = "Your account has been blocked. Please contact support.";
                } else {
                    // Generate OTP
                    $otp = generateOTP();
                    $expiresAt = date('Y-m-d H:i:s', time() + OTP_EXPIRY);

                    // Store OTP in database
                    $db = getDB();
                    $stmt = $db->prepare("INSERT INTO two_factor_tokens (user_id, token, expires_at)
                                          VALUES (?, ?, ?)");
                    $stmt->execute([$user['user_id'], $otp, $expiresAt]);

                    // Send OTP via email
                    if (send2FAOTP($user['email'], $otp)) {
                        $_SESSION['login_user_id'] = $user['user_id'];
                        $_SESSION['login_username'] = $user['username'];
                        $showOTPForm = true;
                    } else {
                        $errors[] = "Failed to send verification code. Please try again.";
                    }
                }
            }
            // Step 2: Verify OTP
            elseif (isset($_POST['otp']) && isset($_SESSION['login_user_id'])) {
                $otp = sanitizeInput($_POST['otp']);
                $userId = $_SESSION['login_user_id'];

                $db = getDB();
                $stmt = $db->prepare("SELECT * FROM two_factor_tokens
                                      WHERE user_id = ? AND token = ? AND expires_at > NOW() AND used = FALSE
                                      ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$userId, $otp]);
                $tokenRecord = $stmt->fetch();

                if (!$tokenRecord) {
                    $errors[] = "Invalid or expired verification code";
                    $showOTPForm = true;
                } else {
                    // Mark token as used
                    $stmt = $db->prepare("UPDATE two_factor_tokens SET used = TRUE WHERE token_id = ?");
                    $stmt->execute([$tokenRecord['token_id']]);

                    // Get user details
                    $user = getUserById($userId);

                    // Create session
                    regenerateSession();
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['is_admin'] = (bool)$user['is_admin'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

                    // Update last login
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

                    // Clear login session variables
                    unset($_SESSION['login_user_id']);
                    unset($_SESSION['login_username']);

                    // Redirect to appropriate page
                    if ($user['is_admin']) {
                        redirect('admin/index.php');
                    } else {
                        redirect('index.php');
                    }
                }
            }
            // Step 3: Resend OTP
            elseif (isset($_POST['resend_otp']) && isset($_SESSION['login_user_id'])) {
                $userId = $_SESSION['login_user_id'];
                $user = getUserById($userId);

                // Generate new OTP
                $otp = generateOTP();
                $expiresAt = date('Y-m-d H:i:s', time() + OTP_EXPIRY);

                // Store new OTP in database
                $db = getDB();
                $stmt = $db->prepare("INSERT INTO two_factor_tokens (user_id, token, expires_at)
                                      VALUES (?, ?, ?)");
                $stmt->execute([$userId, $otp, $expiresAt]);

                // Send OTP via email
                if (send2FAOTP($user['email'], $otp)) {
                    $success[] = "A new verification code has been sent to your email.";
                    $showOTPForm = true;
                } else {
                    $errors[] = "Failed to send verification code. Please try again.";
                    $showOTPForm = true;
                }
            } else {
                $errors[] = "Invalid request";
            }
        }
    }
}

// Check if we should show OTP form
if (isset($_SESSION['login_user_id']) && !isset($_POST['otp'])) {
    $showOTPForm = true;
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CyberWeb</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>CyberWeb</h1>
                <p><?php echo $showOTPForm ? 'Enter verification code' : 'Welcome back'; ?></p>
            </div>

            <?php
            $flash = getFlashMessage();
            if ($flash):
            ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <p><?php echo htmlspecialchars($flash['message']); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <ul>
                        <?php foreach ($success as $msg): ?>
                            <li><?php echo htmlspecialchars($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
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

            <?php if ($showOTPForm): ?>
                <!-- OTP Verification Form -->
                <form method="POST" action="" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                    <p class="otp-info">We've sent a 6-digit code to your email address. Please enter it below.</p>

                    <div class="form-group">
                        <label for="otp">Verification Code</label>
                        <input type="text" id="otp" name="otp" required
                               pattern="[0-9]{6}" maxlength="6"
                               placeholder="000000" class="otp-input">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Verify</button>
                </form>

                <!-- Resend Code Form -->
                <form method="POST" action="" class="auth-form" style="margin-top: 10px;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="resend_otp" value="1">
                    <button type="submit" class="btn btn-secondary btn-block">Resend Code</button>
                </form>

                <div class="auth-footer">
                    <p><a href="login.php" onclick="return confirmCancel();">Back to login</a></p>
                </div>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-focus and format OTP input
        const otpInput = document.getElementById('otp');
        if (otpInput) {
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }

        // Confirm cancel action and clear session
        function confirmCancel() {
            if (confirm('Are you sure you want to cancel? You will need to enter your credentials again.')) {
                // Clear the login session by redirecting with a parameter
                window.location.href = 'login.php?cancel=1';
                return false;
            }
            return false;
        }
    </script>
</body>
</html>