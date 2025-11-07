<?php
/**
 * Settings Page
 * User account settings and preferences
 */

define('CYBERWEB_APP', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

requireLogin();

$currentUser = getUserById($_SESSION['user_id']);
$db = getDB();
$errors = [];
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'update_profile':
                $newUsername = sanitizeInput($_POST['username'] ?? '');
                $bio = sanitizeInput($_POST['bio'] ?? '');

                // Validate username
                if ($newUsername !== $currentUser['username']) {
                    $usernameError = validateUsername($newUsername);
                    if ($usernameError) {
                        $errors[] = $usernameError;
                    } elseif (getUserByUsername($newUsername)) {
                        $errors[] = "Username already exists";
                    }
                }

                if (empty($errors)) {
                    $stmt = $db->prepare("UPDATE users SET username = ?, bio = ? WHERE user_id = ?");
                    $stmt->execute([$newUsername, $bio, $_SESSION['user_id']]);
                    $_SESSION['username'] = $newUsername;
                    $success = "Profile updated successfully";
                    $currentUser = getUserById($_SESSION['user_id']);
                }
                break;

            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                // Verify current password
                if (!verifyPassword($currentPassword, $currentUser['password_hash'])) {
                    $errors[] = "Current password is incorrect";
                } else {
                    // Validate new password
                    $passwordErrors = validatePassword($newPassword);
                    if (!empty($passwordErrors)) {
                        $errors = array_merge($errors, $passwordErrors);
                    }

                    if ($newPassword !== $confirmPassword) {
                        $errors[] = "New passwords do not match";
                    }

                    if (empty($errors)) {
                        $newPasswordHash = hashPassword($newPassword);
                        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                        $stmt->execute([$newPasswordHash, $_SESSION['user_id']]);
                        $success = "Password changed successfully";
                    }
                }
                break;

            case 'update_profile_picture':
                if (!empty($_FILES['profile_picture']['name'])) {
                    $uploadResult = secureFileUpload($_FILES['profile_picture'], UPLOAD_PATH_PROFILES);
                    if ($uploadResult['success']) {
                        // Delete old profile picture if not default
                        if ($currentUser['profile_picture'] !== 'default-avatar.svg') {
                            $oldPath = UPLOAD_PATH_PROFILES . $currentUser['profile_picture'];
                            if (file_exists($oldPath)) {
                                unlink($oldPath);
                            }
                        }

                        $stmt = $db->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                        $stmt->execute([$uploadResult['filename'], $_SESSION['user_id']]);
                        $success = "Profile picture updated successfully";
                        $currentUser = getUserById($_SESSION['user_id']);
                    } else {
                        $errors = $uploadResult['errors'];
                    }
                } else {
                    $errors[] = "Please select an image";
                }
                break;

            case 'update_privacy':
                $privacySetting = $_POST['privacy_setting'] ?? 'public';
                if (!in_array($privacySetting, ['public', 'friends-only'])) {
                    $errors[] = "Invalid privacy setting";
                } else {
                    $stmt = $db->prepare("UPDATE users SET privacy_setting = ? WHERE user_id = ?");
                    $stmt->execute([$privacySetting, $_SESSION['user_id']]);
                    $success = "Privacy settings updated";
                    $currentUser = getUserById($_SESSION['user_id']);
                }
                break;
        }
    }
}

// Get blocked users
$stmt = $db->prepare("
    SELECT u.user_id, u.username, u.profile_picture
    FROM blocks b
    JOIN users u ON b.blocked_id = u.user_id
    WHERE b.blocker_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$blockedUsers = $stmt->fetchAll();

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CyberWeb</title>
    <link rel="icon" type="image/png" href="assets/images/favicon-16x16.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-left">
                <a href="index.php" class="logo">CyberWeb</a>
            </div>
            <div class="nav-right">
                <a href="profile.php?username=<?php echo urlencode($currentUser['username']); ?>" class="btn btn-secondary">
                    Back to Profile
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="main-container">
        <div class="settings-container">
            <h2>Account Settings</h2>

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

            <!-- Profile Settings -->
            <div class="settings-section">
                <h3>Profile Information</h3>
                <form method="POST" action="" class="settings-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username"
                               value="<?php echo htmlspecialchars($currentUser['username']); ?>"
                               required maxlength="50" pattern="[a-zA-Z0-9_]+">
                    </div>

                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="4" maxlength="500"><?php echo htmlspecialchars($currentUser['bio'] ?? ''); ?></textarea>
                        <small>Max 500 characters</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
            </div>

            <!-- Profile Picture -->
            <div class="settings-section">
                <h3>Profile Picture</h3>
                <div class="profile-picture-settings">
                    <?php
                    $currentUserPic = !empty($currentUser['profile_picture']) ?
                                     htmlspecialchars($currentUser['profile_picture']) :
                                     'default-avatar.svg';
                    ?>
                    <img src="uploads/profiles/<?php echo $currentUserPic; ?>"
                         alt="Profile Picture" class="profile-pic-large"
                         onerror="this.src='uploads/profiles/default-avatar.svg';">
                    <form method="POST" action="" enctype="multipart/form-data" class="settings-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="update_profile_picture">

                        <div class="form-group">
                            <label for="profile_picture" class="file-upload-label">
                                <i class="fas fa-upload"></i> Choose New Picture
                                <input type="file" name="profile_picture" id="profile_picture" accept="image/*" required>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary">Upload</button>
                    </form>
                </div>
            </div>

            <!-- Password Change -->
            <div class="settings-section">
                <h3>Change Password</h3>
                <form method="POST" action="" class="settings-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8">
                        <small>Min 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>

            <!-- Privacy Settings -->
            <div class="settings-section">
                <h3>Privacy Settings</h3>
                <form method="POST" action="" class="settings-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="update_privacy">

                    <div class="form-group">
                        <label>Post Visibility</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="privacy_setting" value="public"
                                       <?php echo $currentUser['privacy_setting'] === 'public' ? 'checked' : ''; ?>>
                                Public - Anyone can see your posts
                            </label>
                            <label>
                                <input type="radio" name="privacy_setting" value="friends-only"
                                       <?php echo $currentUser['privacy_setting'] === 'friends-only' ? 'checked' : ''; ?>>
                                Friends Only - Only people you follow can see your posts
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Privacy</button>
                </form>
            </div>

            <!-- Blocked Users -->
            <div class="settings-section">
                <h3>Blocked Users</h3>
                <?php if (empty($blockedUsers)): ?>
                    <p class="empty-state-text">You haven't blocked anyone</p>
                <?php else: ?>
                    <div class="blocked-users-list">
                        <?php foreach ($blockedUsers as $user): ?>
                        <div class="blocked-user-item">
                            <div class="user-info">
                                <?php
                                $blockedUserPic = !empty($user['profile_picture']) ?
                                                 htmlspecialchars($user['profile_picture']) :
                                                 'default-avatar.svg';
                                ?>
                                <img src="uploads/profiles/<?php echo $blockedUserPic; ?>"
                                     alt="<?php echo htmlspecialchars($user['username']); ?>"
                                     class="profile-pic-small"
                                     onerror="this.src='uploads/profiles/default-avatar.svg';">
                                <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                            </div>
                            <button class="btn btn-secondary btn-sm"
                                    onclick="unblockUser(<?php echo $user['user_id']; ?>)">
                                Unblock
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = '<?php echo $csrfToken; ?>';
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>