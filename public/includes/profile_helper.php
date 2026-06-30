<?php
// public/includes/profile_helper.php - Profile helper functions

require_once __DIR__ . '/../../config.php';

// Get user profile data
function getUserProfile($user_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Update user profile
function updateUserProfile($user_id, $data) {
    $db = getDB();
    
    $fields = [];
    $values = [];
    
    $allowed_fields = ['first_name', 'last_name', 'email', 'phone', 'department'];
    
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed_fields)) {
            $fields[] = "$key = ?";
            $values[] = trim($value);
        }
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $values[] = $user_id;
    $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
    $stmt = $db->prepare($sql);
    return $stmt->execute($values);
}

// Update profile picture
function updateProfilePicture($user_id, $filename) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
    return $stmt->execute([$filename, $user_id]);
}

// Get profile picture URL
function getProfilePicture($user_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    if ($result && $result['profile_picture']) {
        return BASE_URL . 'assets/uploads/profiles/' . $result['profile_picture'];
    }
    
    // Return default avatar based on user name
    return BASE_URL . 'assets/images/default-avatar.png';
}

// Get user initials for avatar
function getUserInitials($user_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $first = substr($user['first_name'] ?? 'U', 0, 1);
        $last = substr($user['last_name'] ?? 'N', 0, 1);
        return strtoupper($first . $last);
    }
    
    return 'UN';
}

// Change password
function changePassword($user_id, $old_password, $new_password) {
    $db = getDB();
    
    // Get current password
    $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($old_password, $user['password'])) {
        return 'incorrect';
    }
    
    // Update password
    $hashed = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    if ($stmt->execute([$hashed, $user_id])) {
        return 'success';
    }
    
    return 'error';
}

// Validate profile picture
function validateProfilePicture($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return 'Invalid file type. Please upload JPEG, PNG, GIF, or WEBP.';
    }
    
    if ($file['size'] > $max_size) {
        return 'File too large. Maximum size is 2MB.';
    }
    
    return true;
}

// Generate unique filename for profile picture
function generateProfileFilename($user_id, $extension) {
    return 'user_' . $user_id . '_' . time() . '.' . $extension;
}

// Delete old profile picture
function deleteOldProfilePicture($user_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    if ($result && $result['profile_picture']) {
        $file_path = __DIR__ . '/../../assets/uploads/profiles/' . $result['profile_picture'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
}
?>