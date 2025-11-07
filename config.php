<?php
//prevent direct access
if (!defined('CYBERWEB_APP')) {
    die('Direct access not permitted');
}

//DB config
define('DB_HOST', 'localhost:3306');
define('DB_NAME', 'cyberweb');
define('DB_USER', 'cyberwebDBadmin');
define('DB_PASS', 'sdEH1pW8S_G4z%=n'); 
define('DB_CHARSET', 'utf8mb4');

//application settings
define('APP_NAME', 'CyberWeb');
define('APP_URL', 'https://s4542906-ctxxxx.uogs.co.uk');

//security settings
define('SESSION_LIFETIME', 3600); //1 hour
define('CSRF_TOKEN_EXPIRE', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); //15 minutes
define('OTP_EXPIRY', 300); //5 minutes for 2FA tokens

//file upload settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_PATH_PROFILES', __DIR__ . '/uploads/profiles/');
define('UPLOAD_PATH_POSTS', __DIR__ . '/uploads/posts/');

//email config (for 2FA and notifications)
define('USE_SMTP', true); //set to true to use SMTP, false to use mail()

define('SMTP_HOST', 'uogs.co.uk'); // Change to your SMTP server
define('SMTP_PORT', 465);
define('SMTP_USER', 'cyberweb@s4542906-ctxxxx.uogs.co.uk'); // Change in production
define('SMTP_PASS', 'tH26z4^6o'); // Change in production
define('SMTP_FROM', 'cyberweb@s4542906-ctxxxx.uogs.co.uk'); // Change to your email
define('SMTP_FROM_NAME', 'CyberWeb');

//rate limiting settings
define('RATE_LIMIT_POST', 10); // posts per hour
define('RATE_LIMIT_COMMENT', 30); // comments per hour
define('RATE_LIMIT_UPLOAD', 20); // uploads per hour

// ============================================================================
// GEOLOCATION SERVICE (100% FREE - NO API KEY REQUIRED!)
// ============================================================================
//
// OpenStreetMap Nominatim - Completely FREE geocoding service
// - No registration required
// - No API key required
// - No payment required
// - No usage limits for normal use (1 req/sec max)
// - Privacy-friendly
// - Attribution required (automatically included)
//
// Documentation: https://nominatim.org/release-docs/latest/api/Reverse/
// Usage Policy: https://operations.osmfoundation.org/policies/nominatim/
//
define('USE_NOMINATIM', true); // Free geocoding service
define('NOMINATIM_URL', 'https://nominatim.openstreetmap.org/reverse');
define('NOMINATIM_USER_AGENT', 'CyberWeb/1.0'); // Required by Nominatim ToS

//timezone
date_default_timezone_set('UTC');

// Error Reporting (DISABLE IN PRODUCTION!)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// For production, use these settings instead:
// error_reporting(0);
// ini_set('display_errors', 0);

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters BEFORE starting session
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => true,      // HTTPS only
        'httponly' => true,    // Prevent JavaScript access
        'samesite' => 'Strict' // CSRF protection
    ]);
    
    ini_set('session.use_strict_mode', 1);
    
    // Now start the session
    session_start();
}

// ============================================================================
// NOTES
// ============================================================================
//
// Email Setup:
//  - Start with USE_SMTP = false (uses built-in mail())
//  - If mail() doesn't work, try Gmail with App Password
//  - See INSTALLATION.md for detailed email setup
//
// Geolocation:
//  - Already configured! No changes needed.
//  - OpenStreetMap Nominatim is 100% free
//  - No API key required
//
// Security:
//  - Change DB_USER and DB_PASS for production
//  - Change APP_URL to your domain
//  - Generate unique SITE_KEY in production
//  - Disable error display in production
//