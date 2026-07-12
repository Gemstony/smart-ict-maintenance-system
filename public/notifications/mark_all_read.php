<?php
// public/notifications/mark_all_read.php - Mark all notifications as read (AJAX)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../includes/notification_helper.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$result = markAllNotificationsAsRead($_SESSION['user_id']);
echo json_encode(['success' => $result]);
?>