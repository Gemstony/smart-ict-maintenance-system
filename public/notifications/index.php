<?php
// public/notifications/index.php - Manage all notifications

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$is_admin = ($role === 'System Administrator');

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, unread, read

// Handle actions
$message = '';
$message_type = '';
$action_type = '';

// ====== DELETE NOTIFICATION ======
if (isset($_GET['delete']) && intval($_GET['delete']) > 0) {
    $notif_id = intval($_GET['delete']);
    
    $db = getDB();
    $stmt = $db->prepare("SELECT user_id FROM notifications WHERE notification_id = ?");
    $stmt->execute([$notif_id]);
    $notif = $stmt->fetch();
    
    if ($notif && ($notif['user_id'] == $user_id || $is_admin)) {
        if (deleteNotification($notif_id, $user_id)) {
            $message = 'Notification deleted successfully!';
            $message_type = 'success';
            $action_type = 'delete_success';
        } else {
            $message = 'Failed to delete notification!';
            $message_type = 'danger';
            $action_type = 'delete';
        }
    } else {
        $message = 'You do not have permission to delete this notification!';
        $message_type = 'danger';
        $action_type = 'delete';
    }
}

// ====== MARK AS READ ======
if (isset($_GET['read']) && intval($_GET['read']) > 0) {
    $notif_id = intval($_GET['read']);
    if (markNotificationAsRead($notif_id, $user_id)) {
        $message = 'Notification marked as read!';
        $message_type = 'success';
        $action_type = 'read_success';
    }
}

// ====== MARK AS UNREAD ======
if (isset($_GET['unread']) && intval($_GET['unread']) > 0) {
    $notif_id = intval($_GET['unread']);
    $db = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 0 WHERE notification_id = ? AND user_id = ?");
    if ($stmt->execute([$notif_id, $user_id])) {
        $message = 'Notification marked as unread!';
        $message_type = 'success';
        $action_type = 'unread_success';
    }
}

// ====== MARK ALL AS READ ======
if (isset($_GET['mark_all'])) {
    if (markAllNotificationsAsRead($user_id)) {
        $message = 'All notifications marked as read!';
        $message_type = 'success';
        $action_type = 'markall_success';
    }
}

// ====== GET NOTIFICATIONS ======
$notifications = [];
$total = 0;

if ($filter === 'all') {
    $notifications = getAllNotifications($user_id, $offset, $limit);
    $total = getTotalNotificationsCount($user_id);
} elseif ($filter === 'unread') {
    $db = getDB();
    $stmt = $db->prepare("SELECT n.*, 
                          CASE 
                              WHEN n.type = 'info' THEN 'bell'
                              WHEN n.type = 'success' THEN 'check-circle'
                              WHEN n.type = 'warning' THEN 'exclamation-triangle'
                              WHEN n.type = 'danger' THEN 'times-circle'
                              ELSE 'bell'
                          END as icon
                          FROM notifications n 
                          WHERE n.user_id = ? AND n.is_read = 0
                          ORDER BY n.created_at DESC 
                          LIMIT " . intval($limit) . " OFFSET " . intval($offset));
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $total = $stmt->fetch()['count'] ?? 0;
} elseif ($filter === 'read') {
    $db = getDB();
    $stmt = $db->prepare("SELECT n.*, 
                          CASE 
                              WHEN n.type = 'info' THEN 'bell'
                              WHEN n.type = 'success' THEN 'check-circle'
                              WHEN n.type = 'warning' THEN 'exclamation-triangle'
                              WHEN n.type = 'danger' THEN 'times-circle'
                              ELSE 'bell'
                          END as icon
                          FROM notifications n 
                          WHERE n.user_id = ? AND n.is_read = 1
                          ORDER BY n.created_at DESC 
                          LIMIT " . intval($limit) . " OFFSET " . intval($offset));
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 1");
    $stmt->execute([$user_id]);
    $total = $stmt->fetch()['count'] ?? 0;
}

$total_pages = ceil($total / $limit);
$unread = getUnreadNotificationsCount($user_id);

// Include header
include __DIR__ . '/../includes/header.php';
?>

<style>
    /* ====== SUCCESS TOAST ====== */
    .success-toast {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 9999;
        background: white;
        border-radius: 16px;
        padding: 20px 30px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        display: none;
        align-items: center;
        gap: 15px;
        min-width: 300px;
        max-width: 450px;
        animation: slideInRight 0.4s ease;
        border-left: 5px solid #28a745;
    }
    
    .success-toast.show {
        display: flex;
    }
    
    .success-toast .toast-icon {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: #28a745;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .success-toast .toast-icon i {
        color: white;
        font-size: 1.5rem;
    }
    
    .success-toast .toast-content {
        flex: 1;
    }
    
    .success-toast .toast-content .toast-title {
        font-weight: 700;
        font-size: 1rem;
        color: #28a745;
        margin-bottom: 2px;
    }
    
    .success-toast .toast-content .toast-message {
        font-size: 0.9rem;
        color: #495057;
        margin: 0;
    }
    
    .success-toast .toast-close {
        background: transparent;
        border: none;
        color: #adb5bd;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 5px;
        transition: all 0.3s;
    }
    
    .success-toast .toast-close:hover {
        color: #495057;
        transform: scale(1.1);
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100px);
            opacity: 0;
        }
    }
    
    /* ====== CONFIRMATION OVERLAY ====== */
    .confirm-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9998;
        display: none;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
    }
    
    .confirm-overlay.show {
        display: flex;
        animation: fadeInOverlay 0.3s ease;
    }
    
    .confirm-modal {
        background: white;
        border-radius: 20px;
        padding: 40px 50px;
        text-align: center;
        max-width: 420px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: bounceIn 0.4s ease;
    }
    
    .confirm-modal .confirm-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: #dc3545;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
    }
    
    .confirm-modal .confirm-icon i {
        color: white;
        font-size: 2rem;
    }
    
    .confirm-modal .confirm-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 8px;
    }
    
    .confirm-modal .confirm-message {
        color: #6c757d;
        font-size: 0.95rem;
        margin-bottom: 20px;
    }
    
    .confirm-modal .confirm-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
    }
    
    .confirm-modal .confirm-actions .btn {
        padding: 10px 25px;
        border-radius: 50px;
        font-weight: 600;
        min-width: 100px;
    }
    
    .confirm-modal .confirm-actions .btn-cancel {
        background: #e9ecef;
        color: #495057;
        border: none;
    }
    
    .confirm-modal .confirm-actions .btn-cancel:hover {
        background: #dee2e6;
    }
    
    .confirm-modal .confirm-actions .btn-danger {
        background: #dc3545;
        color: white;
        border: none;
    }
    
    .confirm-modal .confirm-actions .btn-danger:hover {
        background: #c82333;
    }
    
    @keyframes fadeInOverlay {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes bounceIn {
        0% { transform: scale(0.5); opacity: 0; }
        60% { transform: scale(1.05); }
        100% { transform: scale(1); opacity: 1; }
    }
    
    /* ====== NOTIFICATION ITEMS ====== */
    .notification-item-full {
        padding: 15px 20px;
        border-bottom: 1px solid #f0f0f0;
        transition: all 0.2s;
        display: flex;
        gap: 15px;
        align-items: flex-start;
    }
    
    .notification-item-full:hover {
        background: #f8f9fa;
    }
    
    .notification-item-full.unread {
        background: #e8f0fe;
        border-left: 4px solid #1a73e8;
    }
    
    .notification-item-full.unread:hover {
        background: #dce6f5;
    }
    
    .notification-item-full .notif-icon-lg {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.2rem;
    }
    
    .notification-item-full .notif-icon-lg.info {
        background: #dbeafe;
        color: #1a73e8;
    }
    
    .notification-item-full .notif-icon-lg.success {
        background: #d4edda;
        color: #28a745;
    }
    
    .notification-item-full .notif-icon-lg.warning {
        background: #fff3cd;
        color: #ffc107;
    }
    
    .notification-item-full .notif-icon-lg.danger {
        background: #f8d7da;
        color: #dc3545;
    }
    
    .notification-item-full .notif-content-full {
        flex: 1;
        min-width: 0;
    }
    
    .notification-item-full .notif-content-full .notif-title-full {
        font-weight: 600;
        font-size: 1rem;
        color: #212529;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .notification-item-full .notif-content-full .notif-message-full {
        font-size: 0.9rem;
        color: #495057;
        margin-bottom: 4px;
        word-wrap: break-word;
    }
    
    .notification-item-full .notif-content-full .notif-time-full {
        font-size: 0.75rem;
        color: #adb5bd;
    }
    
    .notification-item-full .notif-actions {
        flex-shrink: 0;
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .notification-item-full .notif-actions .btn {
        padding: 4px 10px;
        font-size: 0.75rem;
        border-radius: 6px;
    }
    
    .notification-item-full .notif-actions .btn i {
        font-size: 0.75rem;
    }
    
    /* ====== FILTERS ====== */
    .filter-btn {
        padding: 6px 16px;
        border-radius: 20px;
        border: 2px solid #e9ecef;
        background: white;
        color: #495057;
        font-size: 0.85rem;
        transition: all 0.3s;
        text-decoration: none;
        cursor: pointer;
    }
    
    .filter-btn:hover {
        border-color: #1a73e8;
        color: #1a73e8;
    }
    
    .filter-btn.active {
        background: #1a73e8;
        border-color: #1a73e8;
        color: white;
    }
    
    .filter-btn .badge {
        font-size: 0.7rem;
        margin-left: 5px;
    }
    
    /* ====== PAGINATION ====== */
    .pagination .page-link {
        border-radius: 8px;
        margin: 0 3px;
        color: #1a73e8;
    }
    
    .pagination .page-item.active .page-link {
        background: #1a73e8;
        border-color: #1a73e8;
        color: white;
    }
    
    /* ====== EMPTY STATE ====== */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #dee2e6;
        margin-bottom: 20px;
    }
    
    .empty-state h5 {
        color: #495057;
    }
    
    .empty-state p {
        color: #6c757d;
    }
    
    /* ====== RESPONSIVE ====== */
    @media (max-width: 768px) {
        .notification-item-full {
            flex-direction: column;
            padding: 12px 15px;
        }
        .notification-item-full .notif-actions {
            width: 100%;
            justify-content: flex-end;
        }
        .confirm-modal {
            padding: 30px 20px;
        }
        .confirm-modal .confirm-actions {
            flex-direction: column;
        }
        .confirm-modal .confirm-actions .btn {
            width: 100%;
        }
        .filter-btn {
            padding: 4px 12px;
            font-size: 0.75rem;
        }
        .notification-item-full .notif-content-full .notif-title-full {
            font-size: 0.9rem;
        }
        .notification-item-full .notif-content-full .notif-message-full {
            font-size: 0.8rem;
        }
        .success-toast {
            top: 70px;
            right: 10px;
            left: 10px;
            min-width: auto;
            padding: 15px 20px;
        }
    }
    
    @media (max-width: 576px) {
        .filters-wrap {
            flex-wrap: wrap;
            gap: 5px;
        }
        .header-actions-wrap {
            flex-wrap: wrap;
            gap: 8px;
        }
        .success-toast {
            top: 65px;
            right: 8px;
            left: 8px;
            padding: 12px 15px;
        }
        .success-toast .toast-icon {
            width: 35px;
            height: 35px;
        }
        .success-toast .toast-icon i {
            font-size: 1.2rem;
        }
        .success-toast .toast-content .toast-title {
            font-size: 0.9rem;
        }
        .success-toast .toast-content .toast-message {
            font-size: 0.8rem;
        }
    }
</style>

<!-- ====== SUCCESS TOAST ====== -->
<?php if ($message_type === 'success'): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon">
        <i class="fas fa-check"></i>
    </div>
    <div class="toast-content">
        <div class="toast-title">
            <?php 
            if ($action_type === 'delete_success') echo '🗑️ Deleted!';
            elseif ($action_type === 'read_success') echo '✅ Marked as Read!';
            elseif ($action_type === 'unread_success') echo '📬 Marked as Unread!';
            elseif ($action_type === 'markall_success') echo '✅ All Marked as Read!';
            else echo '✅ Success!';
            ?>
        </div>
        <p class="toast-message"><?php echo $message; ?></p>
    </div>
    <button class="toast-close" onclick="closeToast()">
        <i class="fas fa-times"></i>
    </button>
</div>
<?php endif; ?>

<!-- ====== CONFIRMATION OVERLAY ====== -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-modal">
        <div class="confirm-icon">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h5 class="confirm-title" id="confirmTitle">Delete Notification?</h5>
        <p class="confirm-message" id="confirmMessage">Are you sure you want to delete this notification? This action cannot be undone.</p>
        <div class="confirm-actions">
            <button class="btn btn-cancel" onclick="closeConfirm()">Cancel</button>
            <button class="btn btn-danger" id="confirmBtn" onclick="executeDelete()">Yes, Delete</button>
        </div>
    </div>
</div>

<!-- ====== MAIN CONTENT ====== -->
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-bell text-primary"></i> Notifications</h4>
            <small class="text-muted"><?php echo $total; ?> total notifications</small>
        </div>
        <div class="d-flex gap-2 header-actions-wrap">
            <?php if ($unread > 0): ?>
                <a href="?mark_all=1" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-check-double"></i> Mark All Read
                </a>
            <?php endif; ?>
            <?php if ($is_admin): ?>
                <a href="send.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-paper-plane"></i> Send Notification
                </a>
            <?php endif; ?>
            <button class="btn btn-outline-secondary btn-sm" onclick="location.reload();">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>
    
    <!-- Message Alert (for errors only) -->
    <?php if ($message && $message_type !== 'success'): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="d-flex flex-wrap gap-2 mb-3 filters-wrap">
        <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
            <i class="fas fa-list"></i> All
            <span class="badge bg-secondary text-white"><?php echo getTotalNotificationsCount($user_id); ?></span>
        </a>
        <a href="?filter=unread" class="filter-btn <?php echo $filter === 'unread' ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i> Unread
            <span class="badge bg-danger text-white"><?php echo $unread; ?></span>
        </a>
        <a href="?filter=read" class="filter-btn <?php echo $filter === 'read' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i> Read
        </a>
    </div>
     <!-- Stats -->
    <div class="row g-3 mt-2">
        <div class="col-md-4 col-6">
            <div class="card p-2 text-center">
                <small class="text-muted">Total</small>
                <h5 class="mb-0"><?php echo getTotalNotificationsCount($user_id); ?></h5>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="card p-2 text-center">
                <small class="text-muted">Unread</small>
                <h5 class="mb-0 text-danger"><?php echo $unread; ?></h5>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="card p-2 text-center">
                <small class="text-muted">Read</small>
                <h5 class="mb-0 text-success"><?php echo getTotalNotificationsCount($user_id) - $unread; ?></h5>
            </div>
        </div>
    </div>
    
    <!-- Notifications List -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item-full <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                        <div class="notif-icon-lg <?php echo $notif['type']; ?>">
                            <i class="fas <?php echo getNotificationTypeIcon($notif['type']); ?>"></i>
                        </div>
                        <div class="notif-content-full">
                            <div class="notif-title-full">
                                <?php echo htmlspecialchars($notif['title']); ?>
                                <?php if (!$notif['is_read']): ?>
                                    <span class="badge bg-primary">New</span>
                                <?php endif; ?>
                                <?php if ($notif['type']): ?>
                                    <span class="badge bg-<?php echo getNotificationTypeColor($notif['type']); ?>">
                                        <?php echo ucfirst($notif['type']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="notif-message-full"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></div>
                            <div class="notif-time-full">
                                <i class="far fa-clock"></i> <?php echo timeAgo($notif['created_at']); ?>
                            </div>
                        </div>
                        <div class="notif-actions">
                            <?php if (!$notif['is_read']): ?>
                                <a href="?read=<?php echo $notif['notification_id']; ?>" class="btn btn-sm btn-outline-primary" title="Mark as read">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php else: ?>
                                <a href="?unread=<?php echo $notif['notification_id']; ?>" class="btn btn-sm btn-outline-secondary" title="Mark as unread">
                                    <i class="fas fa-undo"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($is_admin): ?>
                                <a href="edit.php?id=<?php echo $notif['notification_id']; ?>" class="btn btn-sm btn-outline-warning" title="Edit notification">
                                    <i class="fas fa-edit"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($is_admin || $notif['user_id'] == $user_id): ?>
                                <button class="btn btn-sm btn-outline-danger" title="Delete notification" 
                                        onclick="showConfirm(<?php echo $notif['notification_id']; ?>, '<?php echo addslashes($notif['title']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="far fa-bell-slash"></i>
                    <h5>No Notifications</h5>
                    <p><?php echo $filter === 'unread' ? 'You have no unread notifications.' : 'You don\'t have any notifications yet.'; ?></p>
                    <?php if ($is_admin): ?>
                        <a href="send.php" class="btn btn-primary mt-3">
                            <i class="fas fa-paper-plane"></i> Send First Notification
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++): 
                ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
    
   
</div>

<script>
// ====== SUCCESS TOAST ======
function showToast(title, message) {
    const toast = document.getElementById('successToast');
    if (!toast) {
        // Create toast if doesn't exist
        const newToast = document.createElement('div');
        newToast.className = 'success-toast show';
        newToast.id = 'successToast';
        newToast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-check"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <p class="toast-message">${message}</p>
            </div>
            <button class="toast-close" onclick="closeToast()">
                <i class="fas fa-times"></i>
            </button>
        `;
        document.body.appendChild(newToast);
    } else {
        toast.querySelector('.toast-title').textContent = title;
        toast.querySelector('.toast-message').textContent = message;
        toast.classList.add('show');
    }
    
    // Auto close after 2 seconds
    setTimeout(function() {
        closeToast();
    }, 2000);
}

function closeToast() {
    const toast = document.getElementById('successToast');
    if (toast) {
        toast.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(function() {
            toast.classList.remove('show');
            toast.style.animation = '';
            // Remove from URL params
            const url = new URL(window.location.href);
            url.searchParams.delete('delete');
            url.searchParams.delete('read');
            url.searchParams.delete('unread');
            url.searchParams.delete('mark_all');
            window.history.replaceState({}, document.title, url.toString());
        }, 300);
    }
}

// ====== CONFIRMATION MODAL ======
let deleteId = 0;

function showConfirm(id, title) {
    const overlay = document.getElementById('confirmOverlay');
    document.getElementById('confirmTitle').textContent = 'Delete Notification?';
    document.getElementById('confirmMessage').textContent = `Are you sure you want to delete "${title}"? This action cannot be undone.`;
    deleteId = id;
    overlay.classList.add('show');
}

function closeConfirm() {
    document.getElementById('confirmOverlay').classList.remove('show');
    deleteId = 0;
}

function executeDelete() {
    if (deleteId > 0) {
        window.location.href = '?delete=' + deleteId;
    }
    closeConfirm();
}

// ====== KEYBOARD SHORTCUTS ======
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfirm();
        closeToast();
    }
    if (e.key === 'Enter' && document.getElementById('confirmOverlay').classList.contains('show')) {
        executeDelete();
    }
});

// ====== AUTO-CLOSE SUCCESS TOAST ======
document.addEventListener('DOMContentLoaded', function() {
    const toast = document.getElementById('successToast');
    if (toast && toast.classList.contains('show')) {
        // Auto close after 2 seconds
        setTimeout(function() {
            closeToast();
        }, 2000);
    }
    
    // Auto-dismiss error alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                setTimeout(function() {
                    closeBtn.click();
                }, 5000);
            }
        });
    }, 1000);
});

console.log('✅ Notifications Management Loaded!');
console.log('📊 Total:', '<?php echo getTotalNotificationsCount($user_id); ?>');
console.log('📬 Unread:', '<?php echo $unread; ?>');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>