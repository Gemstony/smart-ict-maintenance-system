<?php
// includes/session.php - Session management

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'System Administrator';
}

function isTechnician() {
    return isLoggedIn() && $_SESSION['role'] === 'ICT Technician';
}

function isStaff() {
    return isLoggedIn() && $_SESSION['role'] === 'Staff';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: ../index.php?error=unauthorized');
        exit();
    }
}

function getUserName() {
    $first = $_SESSION['first_name'] ?? '';
    $last = $_SESSION['last_name'] ?? '';
    return trim($first . ' ' . $last);
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getFullName($first_name, $last_name) {
    return trim($first_name . ' ' . $last_name);
}
?>