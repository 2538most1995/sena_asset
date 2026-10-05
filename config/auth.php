<?php
// config/auth.php
// Authentication & Session Management

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user']);
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    if (!is_logged_in()) {
        $current_uri = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header("Location: login.php?redirect=" . urlencode($current_uri));
        exit;
    }
}

function login_user($user) {
    $_SESSION['user_id'] = $user['id'] ?? 1;
    $_SESSION['username'] = $user['username'] ?? 'admin';
    $fullname = $user['fullname'] ?? 'ผู้ใช้งาน';
    $_SESSION['user'] = [
        'id' => $user['id'] ?? 1,
        'username' => $user['username'] ?? 'admin',
        'fullname' => $fullname,
        'role' => $user['role'] ?? 'ผู้ดูแลระบบ',
        'avatar' => !empty($user['avatar']) ? $user['avatar'] : mb_substr($fullname, 0, 1, 'UTF-8')
    ];
}

function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies") && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
