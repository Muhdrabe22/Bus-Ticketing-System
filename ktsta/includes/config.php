<?php
// KTSTA Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ktsta_db');
define('BASE_URL', 'http://localhost/ktsta');
define('SITE_NAME', 'KTSTA');
define('SITE_FULL', 'Katsina State Transport Authority');
define('VERSION', '2.0');

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DB Connection
function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// Auth helpers
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin($role = null) {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/pages/login.php');
        exit;
    }
    if ($role && $_SESSION['user_role'] !== $role) {
        // Check if admin can access all
        if ($_SESSION['user_role'] === 'admin') return;
        header('Location: ' . BASE_URL . '/pages/unauthorized.php');
        exit;
    }
}

function currentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $id = (int)$_SESSION['user_id'];
    $res = $db->query("SELECT * FROM users WHERE id = $id");
    return $res ? $res->fetch_assoc() : null;
}

function getUserById($id) {
    $db = getDB();
    $id = (int)$id;
    $res = $db->query("SELECT id, full_name, email, phone, role, wallet_balance, profile_photo, status FROM users WHERE id = $id");
    return $res ? $res->fetch_assoc() : null;
}

// Sanitize
function clean($data) {
    $db = getDB();
    return $db->real_escape_string(htmlspecialchars(strip_tags(trim($data))));
}

function generateRef($prefix = 'BKG') {
    return $prefix . '-KTS-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

function formatMoney($amount) {
    return '₦' . number_format($amount, 2);
}

function formatDate($date) {
    return date('D, d M Y', strtotime($date));
}

function formatDateTime($dt) {
    return date('D, d M Y \a\t H:i', strtotime($dt));
}

function getSetting($key) {
    $db = getDB();
    $key = clean($key);
    $res = $db->query("SELECT setting_value FROM settings WHERE setting_key='$key'");
    if ($res && $row = $res->fetch_assoc()) return $row['setting_value'];
    return '';
}

function addNotification($userId, $title, $message, $type = 'system') {
    $db = getDB();
    $userId = (int)$userId;
    $title = clean($title);
    $message = clean($message);
    $type = clean($type);
    $db->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($userId, '$title', '$message', '$type')");
}

function getUnreadNotifications($userId) {
    $db = getDB();
    $userId = (int)$userId;
    $res = $db->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id=$userId AND is_read=0");
    $row = $res->fetch_assoc();
    return $row['cnt'];
}

function generateQR($data) {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($data);
}

// CSRF
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
