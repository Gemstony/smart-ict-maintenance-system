<?php
// includes/functions.php - Helper functions

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/session.php';

// Generate QR code identifier
function generateQRCode($asset_id) {
    return 'QR-' . str_pad($asset_id, 6, '0', STR_PAD_LEFT);
}

// Generate asset tag
function generateAssetTag() {
    $year = date('Y');
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as count FROM assets");
    $count = $stmt->fetch()['count'] + 1;
    return 'ASSET-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// Get user by ID
function getUser($user_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Get user by email
function getUserByEmail($email) {
    $db = getDB();
    $stmt = $db->prepare("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

// Get asset by ID
function getAsset($asset_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM assets WHERE asset_id = ?");
    $stmt->execute([$asset_id]);
    return $stmt->fetch();
}

// Create notification
function createNotification($user_id, $title, $message, $type = 'Info') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$user_id, $title, $message, $type]);
}

// Get unread notifications count
function getUnreadNotifications($user_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? $result['count'] : 0;
}

// Get notifications - FIXED
function getNotifications($user_id, $limit = 10) {
    $db = getDB();
    // Ensure limit is integer
    $limit = intval($limit);
    // Use direct concatenation for LIMIT (not a placeholder)
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT " . $limit);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Get status badge
function getStatusBadge($status) {
    $badges = [
        'Pending' => 'warning',
        'Assigned' => 'info',
        'In Progress' => 'primary',
        'Resolved' => 'success',
        'Closed' => 'secondary',
        'Available' => 'success',
        'In Use' => 'info',
        'Under Maintenance' => 'warning',
        'Retired' => 'danger'
    ];
    $class = $badges[$status] ?? 'secondary';
    return "<span class='badge bg-{$class}'>{$status}</span>";
}

// Get priority badge
function getPriorityBadge($priority) {
    $colors = [
        'Low' => 'secondary',
        'Medium' => 'info',
        'High' => 'warning',
        'Critical' => 'danger'
    ];
    $color = $colors[$priority] ?? 'secondary';
    return "<span class='badge bg-{$color}'>{$priority}</span>";
}

// Format date
function formatDate($date) {
    if (!$date) return 'N/A';
    return date('d M Y, H:i', strtotime($date));
}

// Time ago
function timeAgo($datetime) {
    if (!$datetime) return 'N/A';
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . ' mins ago';
    if ($diff < 86400) return floor($diff/3600) . ' hrs ago';
    if ($diff < 604800) return floor($diff/86400) . ' days ago';
    return date('d M Y', $time);
}

// Get dashboard counts
function getDashboardCounts($role, $user_id = null) {
    $db = getDB();
    $counts = [];
    
    if ($role === 'System Administrator') {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        $counts['users'] = $result ? $result['count'] : 0;
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM assets");
        $result = $stmt->fetch();
        $counts['assets'] = $result ? $result['count'] : 0;
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status IN ('Pending', 'Assigned', 'In Progress')");
        $result = $stmt->fetch();
        $counts['active_requests'] = $result ? $result['count'] : 0;
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status = 'Resolved' AND resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $result = $stmt->fetch();
        $counts['resolved_30days'] = $result ? $result['count'] : 0;
        
    } elseif ($role === 'ICT Technician') {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests WHERE assigned_to = ? AND status IN ('Pending', 'Assigned', 'In Progress')");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $counts['my_tasks'] = $result ? $result['count'] : 0;
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests WHERE assigned_to = ? AND status = 'Resolved' AND resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $counts['resolved_month'] = $result ? $result['count'] : 0;
        
    } elseif ($role === 'Staff') {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests WHERE reported_by = ? AND status IN ('Pending', 'Assigned', 'In Progress')");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $counts['my_requests'] = $result ? $result['count'] : 0;
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests WHERE reported_by = ? AND status = 'Resolved' AND resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $counts['resolved_my'] = $result ? $result['count'] : 0;
    }
    
    return $counts;
}

// Get technicians list
function getTechnicians() {
    $db = getDB();
    $stmt = $db->query("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE role = 'ICT Technician'");
    return $stmt->fetchAll();
}

// Get all staff
function getStaff() {
    $db = getDB();
    $stmt = $db->query("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE role = 'Staff'");
    return $stmt->fetchAll();
}

// Get all admins
function getAdmins() {
    $db = getDB();
    $stmt = $db->query("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE role = 'System Administrator'");
    return $stmt->fetchAll();
}

// Mark notification as read
function markNotificationAsRead($notification_id, $user_id) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE notification_id = ? AND user_id = ?");
    return $stmt->execute([$notification_id, $user_id]);
}

// Mark all notifications as read
function markAllNotificationsAsRead($user_id) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    return $stmt->execute([$user_id]);
}

// Delete notification
function deleteNotification($notification_id, $user_id) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
    return $stmt->execute([$notification_id, $user_id]);
}
?>