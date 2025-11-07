<?php
/**
 * Registration Page
 * Secure user registration with validation
 */

define('CYBERWEB_APP', true);
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        // Get and sanitize inputs
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $dateOfBirth = $_POST['date_of_birth'] ?? '';

        // Validate username
        $usernameError = validateUsername($username);
        if ($usernameError) {
            $errors[] = $usernameError;
        }

        // Check if username exists
        if (getUserByUsername($username)) {
            $errors[] = "Username already exists";
        }

        // Validate email
        if (!validateEmail($email)) {
            $errors[] = "Invalid email address";
        }

        // Check if email exists
        if (getUserByEmail($email)) {
            $errors[] = "Email already registered";
        }

        // Validate password
        $passwordErrors = validatePassword($password);
        if (!empty($passwordErrors)) {
            $errors = array_merge($errors, $passwordErrors);
        }

        // Check password confirmation
        if ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match";
        }

        // Validate age
        if (empty($dateOfBirth)) {
            $errors[] = "Date of birth is required";
        } elseif (!validateAge($dateOfBirth)) {
            $errors[] = "You must be at least 16 years old to register";
        }

        // Check rate limiting
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        if (!checkRateLimit($ipAddress, 'register', 3, 60)) {
            $errors[] = "Too many registration attempts. Please try again later.";
        }

        // If no errors, create user
        if (empty($errors)) {
            try {
                $db = getDB();
                $passwordHash = hashPassword($password);

                $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, date_of_birth)
                                      VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $passwordHash, $dateOfBirth]);

                $success = true;
                setFlashMessage('success', 'Registration successful! Please log in.');

                // Redirect to login after 2 seconds
                header("Refresh: 2; url=login.php");
            } catch (PDOException $e) {
                error_log("Registration error: " . $e->getMessage());
                $errors[] = "Registration failed. Please try again.";
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
    <title>Register - CyberWeb</title>
    <link rel="icon" type="image/png" href="assets/images/favicon-16x16.png">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>CyberWeb</h1>
                <p>Join the network</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>Registration successful! Redirecting to login...</p>
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

            <?php if (!$success): ?>
            <form method="POST" action="" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           required maxlength="50" pattern="[a-zA-Z0-9_]+"
                           title="Username can only contain letters, numbers, and underscores">
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth"
                           value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>"
                           required max="<?php echo date('Y-m-d', strtotime('-16 years')); ?>">
                    <small>You must be at least 16 years old</small>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                    <small>Min 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Register</button>
            </form>
            <?php endif; ?>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Log in</a></p>
            </div>
        </div>
    </div>
</body>
</html>