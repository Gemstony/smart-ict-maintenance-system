<?php
// public/login_process.php - Login processing

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        header('Location: ../index.php?error=invalid');
        exit();
    }
    
    $result = authenticateUser($email, $password);
    
    // Check if user is inactive
    if ($result === 'inactive') {
        header('Location: ../index.php?error=inactive');
        exit();
    }
    
    if ($result === true) {
        // Redirect based on role
        $role = $_SESSION['role'];
        switch ($role) {
            case 'System Administrator':
                header('Location: admin/index.php');
                break;
            case 'ICT Technician':
                header('Location: technician/index.php');
                break;
            case 'Staff':
                header('Location: staff/index.php');
                break;
            default:
                header('Location: ../index.php?error=unauthorized');
        }
        exit();
    } else {
        header('Location: ../index.php?error=invalid');
        exit();
    }
} else {
    header('Location: ../index.php');
    exit();
}
?>