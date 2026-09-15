<?php
/**
 * E-Pilketos v2.0 - Administrator Logout
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

if (is_admin()) {
    try {
        $pdo = getDb();
        log_activity($pdo, 'LOGOUT', 'Keluar dari sesi panel admin');
    } catch (Throwable $e) {
        // Ignore error on logout
    }
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header('Location: login.php');
exit;
