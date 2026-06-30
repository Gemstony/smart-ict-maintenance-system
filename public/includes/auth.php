<?php
// admin/includes/auth.php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Optional: store admin info in session
// $_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role']
?>