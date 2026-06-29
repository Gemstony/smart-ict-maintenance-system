<?php
// admin/authenticate.php
session_start();
require_once __DIR__ . '/../../includes/Database.php'; // adjust path to your Database class

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = trim($_POST['username']);
$password = $_POST['password'];

if (empty($username) || empty($password)) {
    header('Location: login.php?error=1');
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT admin_id, username, password_hash, role FROM admin_users WHERE username = :username");
$stmt->execute(['username' => $username]);
$admin = $stmt->fetch();

if ($admin && password_verify($password, $admin['password_hash'])) {
    // Update last login time
    $updateStmt = $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE admin_id = :id");
    $updateStmt->execute(['id' => $admin['admin_id']]);
    
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin['admin_id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];
    
    header('Location: index.php');
    exit;
} else {
    header('Location: login.php?error=1');
    exit;
}
?>