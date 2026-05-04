<?php
// Start session with secure settings
session_start();

// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // Regenerate every 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['usertype']);
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit();
    }
}

function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        $dashboard = getDashboardPath($_SESSION['usertype'], $_SESSION['admin_type'] ?? null);
        header("Location: $dashboard");
        exit();
    }
}

function getDashboardPath($usertype, $admin_type = null) {
    switch($usertype) {
        case 'student':
            return 'dashboard/student.php';
        case 'coach':
            return 'dashboard/coach.php';
        case 'admin':
            if ($admin_type === 'cultural') {
                return 'dashboard/admin_cultural.php';
            } elseif ($admin_type === 'sports') {
                return 'dashboard/admin_sports.php';
            }
            return '../login.php';
        default:
            return '../login.php';
    }
}

// CSRF token functions
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>