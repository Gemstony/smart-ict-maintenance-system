<?php
// public/forgot_password_process.php - Forgot password processing

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

$step = $_POST['step'] ?? '1';
$email = trim($_POST['email'] ?? '');

switch ($step) {
    case '1':
        // Step 1: Verify Email
        if (empty($email)) {
            header('Location: ../index.php?step=forgot1&error=email_not_found');
            exit();
        }
        
        $user = verifyEmail($email);
        if (!$user) {
            header('Location: ../index.php?step=forgot1&error=email_not_found');
            exit();
        }
        
        header('Location: ../index.php?step=forgot2&email=' . urlencode($email));
        exit();
        
    case '2':
        // Step 2: Verify Phone
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($email) || empty($phone)) {
            header('Location: ../index.php?step=forgot2&email=' . urlencode($email) . '&error=phone_mismatch');
            exit();
        }
        
        $user = verifyPhone($email, $phone);
        if (!$user) {
            header('Location: ../index.php?step=forgot2&email=' . urlencode($email) . '&error=phone_mismatch');
            exit();
        }
        
        header('Location: ../index.php?step=forgot3&email=' . urlencode($email));
        exit();
        
    case '3':
        // Step 3: Verify Last Name
        $last_name = trim($_POST['last_name'] ?? '');
        
        if (empty($email) || empty($last_name)) {
            header('Location: ../index.php?step=forgot3&email=' . urlencode($email) . '&error=lastname_mismatch');
            exit();
        }
        
        $user = verifyLastName($email, $last_name);
        if (!$user) {
            header('Location: ../index.php?step=forgot3&email=' . urlencode($email) . '&error=lastname_mismatch');
            exit();
        }
        
        header('Location: ../index.php?step=forgot4&email=' . urlencode($email));
        exit();
        
    case '4':
        // Step 4: Update Password
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($email) || empty($new_password) || empty($confirm_password)) {
            header('Location: ../index.php?step=forgot4&email=' . urlencode($email) . '&error=password_mismatch');
            exit();
        }
        
        if (strlen($new_password) < 8) {
            header('Location: ../index.php?step=forgot4&email=' . urlencode($email) . '&error=password_weak');
            exit();
        }
        
        if ($new_password !== $confirm_password) {
            header('Location: ../index.php?step=forgot4&email=' . urlencode($email) . '&error=password_mismatch');
            exit();
        }
        
        $result = updatePassword($email, $new_password);
        if ($result) {
            header('Location: ../index.php?step=login&msg=password_updated');
        } else {
            header('Location: ../index.php?step=forgot4&email=' . urlencode($email) . '&error=update_failed');
        }
        exit();
        
    default:
        header('Location: ../index.php');
        exit();
}
?>