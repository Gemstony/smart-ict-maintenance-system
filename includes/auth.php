<?php
// includes/auth.php - Authentication functions

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/session.php';

function authenticateUser($email, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // CHECK IF USER IS ACTIVE
        if (isset($user['status']) && $user['status'] === 'inactive') {
            return 'inactive'; // Return 'inactive' instead of true
        }
        
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['phone'] = $user['phone'];
        $_SESSION['department'] = $user['department'];
        $_SESSION['status'] = $user['status'] ?? 'active';
        return true;
    }
    return false;
}

function logoutUser() {
    session_destroy();
    header('Location: ../index.php');
    exit();
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Forgot Password - Step 1: Verify Email
function verifyEmail($email) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

// Forgot Password - Step 2: Verify Phone
function verifyPhone($email, $phone) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND phone = ?");
    $stmt->execute([$email, $phone]);
    return $stmt->fetch();
}

// Forgot Password - Step 3: Verify Last Name
function verifyLastName($email, $last_name) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND last_name = ?");
    $stmt->execute([$email, $last_name]);
    return $stmt->fetch();
}

// Forgot Password - Step 4: Update Password
function updatePassword($email, $new_password) {
    $db = getDB();
    $hashed = hashPassword($new_password);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
    return $stmt->execute([$hashed, $email]);
}
?>