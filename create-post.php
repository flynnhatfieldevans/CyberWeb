<?php
/**
 * Create Post Page
 * Create new posts with text, images, and geotags
 */

define('CYBERWEB_APP', true);
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

requireLogin();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        $content = sanitizeInput($_POST['content'] ?? '');
        $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $locationName = sanitizeInput($_POST['location_name'] ?? '');
        $userId = $_SESSION['user_id'];

        // Check rate limiting
        if (!checkRateLimit($userId, 'post', RATE_LIMIT_POST, 60)) {
            $errors[] = "You've reached the post limit. Please try again later.";
        }

        // Validate that post has either content or image
        $hasImage = !empty($_FILES['image']['name']);
        if (empty($content) && !$hasImage) {
            $errors[] = "Post must have either text content or an image";
        }

        $imagePath = null;

        // Handle image upload
        if ($hasImage && empty($errors)) {
            $uploadResult = secureFileUpload($_FILES['image'], UPLOAD_PATH_POSTS);
            if ($uploadResult['success']) {
                $imagePath = $uploadResult['filename'];
            } else {
                $errors = array_merge($errors, $uploadResult['errors']);
            }
        }

        // Create post if no errors
        if (empty($errors)) {
            try {
                $db = getDB();
                $stmt = $db->prepare("INSERT INTO posts (user_id, content, image_path, latitude, longitude, location_name, created_at)
                                      VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $userId,
                    $content ?: null,
                    $imagePath,
                    $latitude,
                    $longitude,
                    $locationName ?: null
                ]);

                $success = true;
                setFlashMessage('success', 'Post created successfully!');
                redirect('index.php');
            } catch (PDOException $e) {
                error_log("Post creation error: " . $e->getMessage());
                $errors[] = "Failed to create post. Please try again.";

                // Delete uploaded image if post creation failed
                if ($imagePath && file_exists(UPLOAD_PATH_POSTS . $imagePath)) {
                    unlink(UPLOAD_PATH_POSTS . $imagePath);
                }
            }
        }
    }
}

$csrfToken = generateCSRFToken();
$currentUser = getUserById($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post - CyberWeb</title>
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
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="main-container">
        <div class="create-post-container">
            <h2>Create New Post</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" class="create-post-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="latitude" id="latitude">
                <input type="hidden" name="longitude" id="longitude">

                <div class="form-group">
                    <textarea name="content" id="content" rows="6"
                              placeholder="What's on your mind, <?php echo htmlspecialchars($currentUser['username']); ?>?"
                              maxlength="5000"><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                    <small class="char-count"><span id="charCount">0</span>/5000</small>
                </div>

                <div class="form-group">
                    <label for="image" class="file-upload-label">
                        <i class="fas fa-image"></i> Add Photo
                        <input type="file" name="image" id="image" accept="image/*">
                    </label>
                    <div id="imagePreview" class="image-preview"></div>
                </div>

                <div class="form-group">
                    <label for="location_name">
                        <i class="fas fa-map-marker-alt"></i> Add Location
                    </label>
                    <div class="location-input-group">
                        <input type="text" name="location_name" id="location_name"
                               placeholder="Enter location name"
                               value="<?php echo htmlspecialchars($_POST['location_name'] ?? ''); ?>">
                        <button type="button" class="btn btn-secondary" onclick="getCurrentLocation()">
                            <i class="fas fa-crosshairs"></i> Use Current Location
                        </button>
                    </div>
                    <small id="locationStatus" class="location-status"></small>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-paper-plane"></i> Post
                </button>
            </form>
        </div>
    </div>

    <script>
        // Character counter
        const contentTextarea = document.getElementById('content');
        const charCount = document.getElementById('charCount');

        contentTextarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });

        // Image preview
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');

        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        <button type="button" class="remove-image" onclick="removeImage()">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });

        function removeImage() {
            imageInput.value = '';
            imagePreview.innerHTML = '';
        }

        // Geolocation
        function getCurrentLocation() {
            const locationStatus = document.getElementById('locationStatus');

            if (!navigator.geolocation) {
                locationStatus.textContent = 'Geolocation is not supported by your browser';
                return;
            }

            locationStatus.textContent = 'Getting your location...';

            navigator.geolocation.getCurrentPosition(
                async function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    document.getElementById('latitude').value = lat;
                    document.getElementById('longitude').value = lng;

                    // Reverse geocoding using FREE OpenStreetMap Nominatim API
                    // No API key required!
                    try {
                        const response = await fetch(
                            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,
                            {
                                headers: {
                                    'User-Agent': '<?php echo NOMINATIM_USER_AGENT; ?>'
                                }
                            }
                        );
                        const data = await response.json();

                        if (data.display_name) {
                            document.getElementById('location_name').value = data.display_name;
                            locationStatus.textContent = 'Location set successfully • Powered by OpenStreetMap';
                        } else {
                            document.getElementById('location_name').value = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                            locationStatus.textContent = 'Location set (coordinates only)';
                        }
                    } catch (error) {
                        document.getElementById('location_name').value = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                        locationStatus.textContent = 'Location set (coordinates only)';
                    }
                },
                function(error) {
                    locationStatus.textContent = 'Unable to retrieve your location: ' + error.message;
                }
            );
        }
    </script>
</body>
</html>