<?php
/**
 * Security Functions
 * CSRF protection, input validation, XSS prevention
 */

if (!defined('CYBERWEB_APP')) {
    die('Direct access not permitted');
}

/**
 * Generate CSRF Token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) ||
        time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRE) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }

    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRE) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input to prevent XSS
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 * Requirements: min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
 */
function validatePassword($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }

    return $errors;
}

/**
 * Validate username
 */
function validateUsername($username) {
    if (strlen($username) < 3 || strlen($username) > 50) {
        return "Username must be between 3 and 50 characters";
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return "Username can only contain letters, numbers, and underscores";
    }
    return null;
}

/**
 * Validate age (must be 16+)
 */
function validateAge($dateOfBirth) {
    $dob = new DateTime($dateOfBirth);
    $today = new DateTime();
    $age = $today->diff($dob)->y;
    return $age >= 16;
}

/**
 * Hash password securely
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate secure random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Generate 6-digit OTP
 */
function generateOTP() {
    return sprintf("%06d", random_int(0, 999999));
}

/**
 * Check rate limiting
 */
function checkRateLimit($identifier, $action, $maxAttempts, $windowMinutes = 60) {
    $db = getDB();

    // Clean old entries
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->execute([$windowMinutes]);

    // Check current attempts
    $stmt = $db->prepare("SELECT attempts FROM rate_limits WHERE identifier = ? AND action = ?");
    $stmt->execute([$identifier, $action]);
    $result = $stmt->fetch();

    if ($result) {
        if ($result['attempts'] >= $maxAttempts) {
            return false;
        }

        // Increment attempts
        $stmt = $db->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE identifier = ? AND action = ?");
        $stmt->execute([$identifier, $action]);
    } else {
        // Create new entry
        $stmt = $db->prepare("INSERT INTO rate_limits (identifier, action, attempts) VALUES (?, ?, 1)");
        $stmt->execute([$identifier, $action]);
    }

    return true;
}

/**
 * Check authentication rate limits
 * Returns ['allowed' => bool, 'reason' => string]
 */
function checkAuthRateLimit($username, $ipAddress) {
    $db = getDB();

    // Check IP-based rate limit (10 attempts -> 1 hour timeout)
    $stmt = $db->prepare("SELECT attempts, window_start
                          FROM rate_limits
                          WHERE identifier = ? AND action = 'login_ip'
                          AND window_start > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->execute([$ipAddress, LOGIN_LOCKOUT_IP]);
    $ipLimit = $stmt->fetch();

    if ($ipLimit && $ipLimit['attempts'] >= MAX_LOGIN_ATTEMPTS_IP) {
        return ['allowed' => false, 'reason' => 'Too many login attempts from your IP address. Please try again in 1 hour.'];
    }

    // Check username-based rate limit (4 attempts within 5 minutes -> 10 minute timeout)
    $stmt = $db->prepare("SELECT attempts, window_start
                          FROM rate_limits
                          WHERE identifier = ? AND action = 'login_username'
                          AND window_start > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->execute([$username, LOGIN_LOCKOUT_USERNAME]);
    $usernameLimit = $stmt->fetch();

    if ($usernameLimit && $usernameLimit['attempts'] >= MAX_LOGIN_ATTEMPTS_USERNAME) {
        return ['allowed' => false, 'reason' => 'Too many failed login attempts for this username. Please try again in 10 minutes.'];
    }

    return ['allowed' => true, 'reason' => ''];
}

/**
 * Record failed login attempt
 */
function recordFailedLogin($username, $ipAddress) {
    $db = getDB();

    // Record IP-based attempt
    $stmt = $db->prepare("INSERT INTO rate_limits (identifier, action, attempts, window_start)
                          VALUES (?, 'login_ip', 1, NOW())
                          ON DUPLICATE KEY UPDATE attempts = attempts + 1");
    $stmt->execute([$ipAddress]);

    // Record username-based attempt (only if within the 5-minute window)
    $stmt = $db->prepare("SELECT attempts FROM rate_limits
                          WHERE identifier = ? AND action = 'login_username'
                          AND window_start > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->execute([$username, LOGIN_WINDOW_USERNAME]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Within window, increment
        $stmt = $db->prepare("UPDATE rate_limits
                             SET attempts = attempts + 1
                             WHERE identifier = ? AND action = 'login_username'");
        $stmt->execute([$username]);
    } else {
        // Outside window or new, reset with new window
        $stmt = $db->prepare("INSERT INTO rate_limits (identifier, action, attempts, window_start)
                              VALUES (?, 'login_username', 1, NOW())
                              ON DUPLICATE KEY UPDATE attempts = 1, window_start = NOW()");
        $stmt->execute([$username]);
    }
}

/**
 * Clear authentication rate limits after successful login
 */
function clearAuthRateLimit($username, $ipAddress) {
    $db = getDB();

    // Clear username-based limit
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE identifier = ? AND action = 'login_username'");
    $stmt->execute([$username]);

    // Note: We don't clear IP-based limits to prevent distributed attacks
}

/**
 * Validate file upload
 */
function validateImageUpload($file) {
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error";
        return $errors;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = "File size must not exceed 5MB";
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        $errors[] = "Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed";
    }

    return $errors;
}

/**
 * Secure file upload
 */
function secureFileUpload($file, $uploadPath) {
    $errors = validateImageUpload($file);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('img_', true) . '.' . $extension;
    $destination = $uploadPath . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'errors' => ['Failed to upload file']];
    }

    // Set proper permissions
    chmod($destination, 0644);

    return ['success' => true, 'filename' => $filename];
}

/**
 * Prevent session fixation
 */
function regenerateSession() {
    session_regenerate_id(true);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Require admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: index.php");
        exit;
    }
}

/**
 * Log admin action
 */
function logAdminAction($adminId, $action, $targetType = null, $targetId = null, $details = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO audit_log (admin_id, action, target_type, target_id, details, ip_address)
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $adminId,
        $action,
        $targetType,
        $targetId,
        $details,
        $_SERVER['REMOTE_ADDR']
    ]);
}
