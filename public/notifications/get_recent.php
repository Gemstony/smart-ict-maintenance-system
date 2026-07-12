<?php
// public/notifications/get_recent.php - AJAX get recent notifications

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../includes/notification_helper.php';

requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$notifications = getRecentNotifications($user_id, 5);
$unread_count = getUnreadNotificationsCount($user_id);

// Build HTML
ob_start();
if (count($notifications) > 0): ?>
    <?php foreach ($notifications as $notif): 
        $icon_class = 'info';
        $icon_icon = 'fa-info-circle';
        if ($notif['type'] === 'Success') { $icon_class = 'success'; $icon_icon = 'fa-check-circle'; }
        elseif ($notif['type'] === 'Warning') { $icon_class = 'warning'; $icon_icon = 'fa-exclamation-triangle'; }
        elseif ($notif['type'] === 'Error') { $icon_class = 'error'; $icon_icon = 'fa-times-circle'; }
    ?>
        <div class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" 
             onclick="markAsRead(<?php echo $notif['notification_id']; ?>)"
             data-id="<?php echo $notif['notification_id']; ?>">
            <div class="notif-icon <?php echo $icon_class; ?>">
                <i class="fas <?php echo $icon_icon; ?>"></i>
            </div>
            <div class="notif-content">
                <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                <div class="notif-time">
                    <i class="far fa-clock"></i> <?php echo timeAgo($notif['created_at']); ?>
                </div>
            </div>
            <?php if (!$notif['is_read']): ?>
                <div class="notif-badge-unread"></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="notif-empty">
        <i class="fas fa-bell-slash"></i>
        <p>No notifications yet</p>
    </div>
<?php endif;
$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html,
    'unread_count' => $unread_count
]);
?>