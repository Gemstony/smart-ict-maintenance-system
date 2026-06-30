<?php
// public/notifications/edit.php - Edit notification

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$is_admin = ($role === 'System Administrator');

// Only admin can edit
if (!$is_admin) {
    header('Location: index.php?error=unauthorized');
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Location: index.php?error=invalid');
    exit();
}

// Get notification details
$db = getDB();
$stmt = $db->prepare("SELECT * FROM notifications WHERE notification_id = ?");
$stmt->execute([$id]);
$notification = $stmt->fetch();

if (!$notification) {
    header('Location: index.php?error=notfound');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notification'])) {
    $title = trim($_POST['title'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    $type = $_POST['notification_type'] ?? 'info';
    
    if (empty($title) || empty($message_text)) {
        $error = 'Title and message are required!';
    } else {
        $stmt = $db->prepare("UPDATE notifications SET title = ?, message = ?, type = ?, updated_at = CURRENT_TIMESTAMP WHERE notification_id = ?");
        if ($stmt->execute([$title, $message_text, $type, $id])) {
            // Redirect to index with success message
            header('Location: index.php?updated=1');
            exit();
        } else {
            $error = 'Failed to update notification!';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
    .form-section {
        background: white;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.06);
    }
    
    .form-section .section-title {
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-section .section-title i {
        color: #1a73e8;
    }
    
    .notification-type-badge {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.8rem;
    }
    
    .char-count {
        font-size: 0.75rem;
        color: #6c757d;
    }
    
    .char-count.danger {
        color: #dc3545;
    }
    
    .preview-box {
        border: 2px dashed #dee2e6;
        border-radius: 12px;
        padding: 20px;
        background: #f8f9fa;
    }
    
    .preview-box .preview-title {
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    .preview-box .preview-message {
        color: #495057;
    }
    
    .preview-box .preview-meta {
        font-size: 0.75rem;
        color: #adb5bd;
    }
    
    .preview-box .preview-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .info-box {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-top: 15px;
    }
    
    .info-box .info-item {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        border-bottom: 1px solid #e9ecef;
        font-size: 0.85rem;
    }
    
    .info-box .info-item:last-child {
        border-bottom: none;
    }
    
    .info-box .info-item .label {
        color: #6c757d;
    }
    
    .info-box .info-item .value {
        font-weight: 500;
        color: #212529;
    }
    
    @media (max-width: 768px) {
        .form-section {
            padding: 15px;
        }
        .preview-box {
            padding: 15px;
        }
    }
</style>

<!-- ====== SUCCESS TOAST (for redirect) ====== -->
<?php if (isset($_GET['updated'])): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon">
        <i class="fas fa-check"></i>
    </div>
    <div class="toast-content">
        <div class="toast-title">✅ Updated!</div>
        <p class="toast-message">Notification updated successfully!</p>
    </div>
</div>

<style>
    .success-toast {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 9999;
        background: white;
        border-radius: 16px;
        padding: 20px 30px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        gap: 15px;
        min-width: 300px;
        max-width: 450px;
        animation: slideInRight 0.4s ease;
        border-left: 5px solid #28a745;
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
    
    @media (max-width: 768px) {
        .success-toast {
            top: 70px;
            right: 10px;
            left: 10px;
            min-width: auto;
            padding: 15px 20px;
        }
        .success-toast .toast-icon {
            width: 35px;
            height: 35px;
        }
        .success-toast .toast-icon i {
            font-size: 1.2rem;
        }
    }
</style>

<script>
    // Auto close toast after 2 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const toast = document.getElementById('successToast');
            if (toast) {
                toast.style.animation = 'slideOutRight 0.3s ease forwards';
                setTimeout(function() {
                    toast.style.display = 'none';
                }, 300);
            }
            // Remove updated param from URL
            const url = new URL(window.location.href);
            url.searchParams.delete('updated');
            window.history.replaceState({}, document.title, url.toString());
        }, 2000);
    });
</script>
<?php endif; ?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-edit text-warning"></i> Edit Notification</h4>
            <small class="text-muted">Update notification details</small>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Notifications
            </a>
        </div>
    </div>
    
    <!-- Error Alert -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Edit Form -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-edit"></i> Edit Notification
                </div>
                
                <form method="POST">
                    <!-- Title -->
                    <div class="mb-3">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required 
                               value="<?php echo htmlspecialchars($notification['title']); ?>" maxlength="255">
                    </div>
                    
                    <!-- Message -->
                    <div class="mb-3">
                        <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="message" name="message" rows="6" required 
                                  maxlength="1000" oninput="updateCharCount(this)"><?php echo htmlspecialchars($notification['message']); ?></textarea>
                        <div class="d-flex justify-content-end mt-1">
                            <span class="char-count" id="charCount"><?php echo strlen($notification['message']); ?> / 1000</span>
                        </div>
                    </div>
                    
                    <!-- Notification Type -->
                    <div class="mb-3">
                        <label class="form-label">Notification Type</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php 
                            $types = [
                                'info' => ['label' => 'Information', 'color' => 'primary', 'icon' => 'fa-info-circle'],
                                'success' => ['label' => 'Success', 'color' => 'success', 'icon' => 'fa-check-circle'],
                                'warning' => ['label' => 'Warning', 'color' => 'warning', 'icon' => 'fa-exclamation-triangle'],
                                'danger' => ['label' => 'Alert', 'color' => 'danger', 'icon' => 'fa-times-circle']
                            ];
                            foreach ($types as $type => $data): 
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="notification_type" 
                                           id="type_<?php echo $type; ?>" value="<?php echo $type; ?>"
                                           <?php echo $notification['type'] === $type ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="type_<?php echo $type; ?>">
                                        <span class="notification-type-badge bg-<?php echo $data['color']; ?> bg-opacity-10 text-<?php echo $data['color']; ?>">
                                            <i class="fas <?php echo $data['icon']; ?>"></i> <?php echo $data['label']; ?>
                                        </span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Recipient Info (Readonly) -->
                    <div class="mb-3">
                        <label class="form-label">Recipient</label>
                        <?php
                        $stmt = $db->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
                        $stmt->execute([$notification['user_id']]);
                        $recipient = $stmt->fetch();
                        ?>
                        <div class="form-control bg-light">
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']); ?> 
                            (<?php echo htmlspecialchars($recipient['email']); ?>)
                        </div>
                        <small class="text-muted">Recipient cannot be changed</small>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" name="update_notification" class="btn btn-warning">
                            <i class="fas fa-save"></i> Update Notification
                        </button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Preview -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-eye"></i> Preview
                </div>
                <div class="preview-box">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="preview-badge bg-<?php echo getNotificationTypeColor($notification['type']); ?> text-white">
                            <?php echo ucfirst($notification['type']); ?>
                        </span>
                        <span class="preview-badge <?php echo $notification['is_read'] ? 'bg-secondary' : 'bg-primary'; ?> text-white">
                            <?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?>
                        </span>
                    </div>
                    <div class="preview-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                    <div class="preview-message"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></div>
                    <div class="preview-meta mt-2">
                        <i class="far fa-clock"></i> <?php echo timeAgo($notification['created_at']); ?>
                    </div>
                </div>
            </div>
            
            <!-- Information -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i> Information
                </div>
                <div class="info-box">
                    <div class="info-item">
                        <span class="label">Created</span>
                        <span class="value"><?php echo formatDate($notification['created_at']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Last Updated</span>
                        <span class="value"><?php echo formatDate($notification['updated_at']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Status</span>
                        <span class="value">
                            <span class="badge <?php echo $notification['is_read'] ? 'bg-secondary' : 'bg-primary'; ?>">
                                <?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="label">Recipient</span>
                        <span class="value"><?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ====== UPDATE CHARACTER COUNT ======
function updateCharCount(element) {
    const count = element.value.length;
    const max = 1000;
    const counter = document.getElementById('charCount');
    counter.textContent = `${count} / ${max}`;
    counter.classList.toggle('danger', count > max * 0.9);
}

// ====== LIVE PREVIEW ======
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('title');
    const messageInput = document.getElementById('message');
    const typeRadios = document.querySelectorAll('input[name="notification_type"]');
    
    function updatePreview() {
        const title = titleInput.value || 'Title';
        const message = messageInput.value || 'Message';
        let type = 'info';
        typeRadios.forEach(radio => {
            if (radio.checked) type = radio.value;
        });
        
        const previewBox = document.querySelector('.preview-box');
        const badges = previewBox.querySelectorAll('.preview-badge');
        if (badges.length > 0) {
            badges[0].textContent = type.charAt(0).toUpperCase() + type.slice(1);
            badges[0].className = `preview-badge bg-${getTypeColor(type)} text-white`;
        }
        previewBox.querySelector('.preview-title').textContent = title;
        previewBox.querySelector('.preview-message').textContent = message;
    }
    
    function getTypeColor(type) {
        const colors = {
            'info': 'primary',
            'success': 'success',
            'warning': 'warning',
            'danger': 'danger'
        };
        return colors[type] || 'secondary';
    }
    
    titleInput.addEventListener('input', updatePreview);
    messageInput.addEventListener('input', updatePreview);
    typeRadios.forEach(radio => {
        radio.addEventListener('change', updatePreview);
    });
    
    // Auto-dismiss alerts
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

console.log('✅ Edit Notification Loaded!');
console.log('📝 Editing notification:', '<?php echo $id; ?>');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>