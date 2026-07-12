<?php
// public/notifications/check_new.php - Check for new notifications (AJAX)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../includes/notification_helper.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$last_check = isset($_GET['last_check']) ? intval($_GET['last_check']) : 0;

// Get unread count
$unread = getUnreadNotificationsCount($user_id);

// Check if there are new notifications since last check
$db = getDB();
$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0 AND notification_id > ?");
$stmt->execute([$user_id, $last_check]);
$new_count = $stmt->fetch()['count'] ?? 0;

echo json_encode([
    'unread' => $unread,
    'has_new' => $new_count > 0,
    'new_count' => $new_count
]);
?>