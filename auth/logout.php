<?php
session_start();

// Record a faculty sign-out before the session is destroyed.
if (isset($_SESSION['email']) && trim((string)($_SESSION['role'] ?? '')) === 'faculty') {
    include_once __DIR__ . '/../connect.php';
    require_once __DIR__ . '/../activity_log.php';
    log_activity($conn, 'logout', 'Signed out of the system.');
}

session_destroy();
header("Location: login.php");
exit();
