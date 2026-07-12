<?php
// public/profile/update_profile.php - Profile update handler

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../includes/profile_helper.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// ====== UPDATE PROFILE ======
if ($action === 'update_profile') {
    $data = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'department' => $_POST['department'] ?? ''
    ];
    
    // Validate
    if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']) || empty($data['phone'])) {
        header('Location: index.php?error=invalid');
        exit();
    }
    
    // Check if email exists for another user
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND user_id != ?");
    $stmt->execute([$data['email'], $user_id]);
    if ($stmt->fetch()) {
        header('Location: index.php?error=email_exists');
        exit();
    }
    
    $result = updateUserProfile($user_id, $data);
    
    if ($result) {
        // Update session
        $_SESSION['first_name'] = $data['first_name'];
        $_SESSION['last_name'] = $data['last_name'];
        $_SESSION['email'] = $data['email'];
        $_SESSION['phone'] = $data['phone'];
        $_SESSION['department'] = $data['department'];
        $_SESSION['full_name'] = trim($data['first_name'] . ' ' . $data['last_name']);
        
        header('Location: index.php?msg=updated&type=success');
    } else {
        header('Location: index.php?msg=error&type=danger');
    }
    exit();
}

// ====== CHANGE PASSWORD ======
if ($action === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        header('Location: index.php?error=invalid');
        exit();
    }
    
    if (strlen($new_password) < 8) {
        header('Location: index.php?error=weak');
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        header('Location: index.php?error=mismatch');
        exit();
    }
    
    $result = changePassword($user_id, $current_password, $new_password);
    
    if ($result === 'success') {
        header('Location: index.php?msg=password_updated&type=success');
    } elseif ($result === 'incorrect') {
        header('Location: index.php?error=incorrect');
    } else {
        header('Location: index.php?msg=error&type=danger');
    }
    exit();
}

// ====== UPLOAD PROFILE PICTURE ======
if ($action === 'upload_picture') {
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
        header('Location: index.php?error=file_error&msg=No file selected');
        exit();
    }
    
    $file = $_FILES['profile_picture'];
    
    // Validate
    $validation = validateProfilePicture($file);
    if ($validation !== true) {
        header('Location: index.php?error=file_error&msg=' . urlencode($validation));
        exit();
    }
    
    // Create directory if not exists
    $upload_dir = __DIR__ . '/../../assets/uploads/profiles/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = generateProfileFilename($user_id, $extension);
    $filepath = $upload_dir . $filename;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Delete old picture
        deleteOldProfilePicture($user_id);
        
        // Update database
        $result = updateProfilePicture($user_id, $filename);
        
        if ($result) {
            header('Location: index.php?msg=picture_updated&type=success');
        } else {
            // Delete uploaded file if database update fails
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            header('Location: index.php?error=file_error&msg=Database update failed');
        }
    } else {
        header('Location: index.php?error=file_error&msg=Failed to upload file');
    }
    exit();
}

// ====== INVALID ACTION ======
header('Location: index.php');
exit();
?>