<?php
// public/includes/notification_helper.php - Notification helper functions

require_once __DIR__ . '/../../config.php';

// Get unread notifications count
function getUnreadNotificationsCount($user_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? (int)$result['count'] : 0;
}

// Get recent notifications - FIXED
function getRecentNotifications($user_id, $limit = 5) {
    $db = getDB();
    // Use intval() and concatenation for LIMIT
    $limit = intval($limit);
    $stmt = $db->prepare("SELECT n.*, 
                          CASE 
                              WHEN n.type = 'info' THEN 'bell'
                              WHEN n.type = 'success' THEN 'check-circle'
                              WHEN n.type = 'warning' THEN 'exclamation-triangle'
                              WHEN n.type = 'danger' THEN 'times-circle'
                              ELSE 'bell'
                          END as icon
                          FROM notifications n 
                          WHERE n.user_id = ? 
                          ORDER BY n.created_at DESC 
                          LIMIT " . $limit);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Get all notifications with pagination - FIXED
function getAllNotifications($user_id, $offset = 0, $limit = 20) {
    $db = getDB();
    $limit = intval($limit);
    $offset = intval($offset);
    $stmt = $db->prepare("SELECT n.*, 
                          CASE 
                              WHEN n.type = 'info' THEN 'bell'
                              WHEN n.type = 'success' THEN 'check-circle'
                              WHEN n.type = 'warning' THEN 'exclamation-triangle'
                              WHEN n.type = 'danger' THEN 'times-circle'
                              ELSE 'bell'
                          END as icon
                          FROM notifications n 
                          WHERE n.user_id = ? 
                          ORDER BY n.created_at DESC 
                          LIMIT " . $limit . " OFFSET " . $offset);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Get total notifications count
function getTotalNotificationsCount($user_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? (int)$result['count'] : 0;
}

// Create notification
function createNotification($user_id, $title, $message, $type = 'info', $link = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link, is_read) VALUES (?, ?, ?, ?, ?, 0)");
    return $stmt->execute([$user_id, $title, $message, $type, $link]);
}

// Create notification for multiple users
function createNotificationForUsers($user_ids, $title, $message, $type = 'info', $link = null) {
    $db = getDB();
    $success = true;
    
    foreach ($user_ids as $user_id) {
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link, is_read) VALUES (?, ?, ?, ?, ?, 0)");
        if (!$stmt->execute([$user_id, $title, $message, $type, $link])) {
            $success = false;
        }
    }
    
    return $success;
}

// Create notification for role
function createNotificationForRole($role, $title, $message, $type = 'info', $link = null) {
    $db = getDB();
    $stmt = $db->prepare("SELECT user_id FROM users WHERE role = ? AND (status = 'active' OR status IS NULL)");
    $stmt->execute([$role]);
    $users = $stmt->fetchAll();
    
    $success = true;
    foreach ($users as $user) {
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link, is_read) VALUES (?, ?, ?, ?, ?, 0)");
        if (!$stmt->execute([$user['user_id'], $title, $message, $type, $link])) {
            $success = false;
        }
    }
    
    return $success;
}

// Create notification for all users
function createNotificationForAll($title, $message, $type = 'info', $link = null) {
    $db = getDB();
    $stmt = $db->query("SELECT user_id FROM users WHERE status = 'active' OR status IS NULL");
    $users = $stmt->fetchAll();
    
    $success = true;
    foreach ($users as $user) {
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link, is_read) VALUES (?, ?, ?, ?, ?, 0)");
        if (!$stmt->execute([$user['user_id'], $title, $message, $type, $link])) {
            $success = false;
        }
    }
    
    return $success;
}

// Mark notification as read
function markNotificationAsRead($notification_id, $user_id) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    return $stmt->execute([$notification_id, $user_id]);
}

// Mark all notifications as read
function markAllNotificationsAsRead($user_id) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    return $stmt->execute([$user_id]);
}

// Delete notification
function deleteNotification($notification_id, $user_id) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
    return $stmt->execute([$notification_id, $user_id]);
}

// Get users by role
function getUsersByRole($role) {
    $db = getDB();
    $stmt = $db->prepare("SELECT user_id, first_name, last_name, email, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE role = ? AND (status = 'active' OR status IS NULL)");
    $stmt->execute([$role]);
    return $stmt->fetchAll();
}

// Get all active users
function getAllActiveUsers() {
    $db = getDB();
    $stmt = $db->query("SELECT user_id, first_name, last_name, email, role, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE status = 'active' OR status IS NULL");
    return $stmt->fetchAll();
}

// Get notification types
function getNotificationTypes() {
    return [
        'info' => 'Information',
        'success' => 'Success',
        'warning' => 'Warning',
        'danger' => 'Alert'
    ];
}

// Get notification type icon
function getNotificationTypeIcon($type) {
    $icons = [
        'info' => 'fa-info-circle',
        'success' => 'fa-check-circle',
        'warning' => 'fa-exclamation-triangle',
        'danger' => 'fa-times-circle'
    ];
    return $icons[$type] ?? 'fa-bell';
}

// Get notification type color
function getNotificationTypeColor($type) {
    $colors = [
        'info' => 'primary',
        'success' => 'success',
        'warning' => 'warning',
        'danger' => 'danger'
    ];
    return $colors[$type] ?? 'secondary';
}
?>