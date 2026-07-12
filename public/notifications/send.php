<?php
// public/notifications/send.php - Send notification page

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Only Admin can send notifications
if ($role !== 'System Administrator') {
    header('Location: index.php?error=unauthorized');
    exit();
}

$error = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $title = trim($_POST['title'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    $recipient_type = $_POST['recipient_type'] ?? 'all';
    $recipient_role = $_POST['recipient_role'] ?? '';
    $notification_type = $_POST['notification_type'] ?? 'info';
    
    if (empty($title) || empty($message_text)) {
        $error = 'Title and message are required!';
    } else {
        $success = false;
        $recipient_count = 0;
        
        if ($recipient_type === 'all') {
            $success = createNotificationForAll($title, $message_text, $notification_type);
            $recipient_count = count(getAllActiveUsers());
        } elseif ($recipient_type === 'role' && !empty($recipient_role)) {
            $success = createNotificationForRole($recipient_role, $title, $message_text, $notification_type);
            $recipient_count = count(getUsersByRole($recipient_role));
        } elseif ($recipient_type === 'specific') {
            $selected_users = $_POST['selected_users'] ?? [];
            if (!empty($selected_users)) {
                $success = createNotificationForUsers($selected_users, $title, $message_text, $notification_type);
                $recipient_count = count($selected_users);
            } else {
                $error = 'Please select at least one user!';
            }
        }
        
        if ($success) {
            // Redirect to index with success message
            header('Location: index.php?sent=1&count=' . $recipient_count);
            exit();
        } elseif (empty($error)) {
            $error = 'Failed to send notification!';
        }
    }
}

// Get all users for specific selection
$all_users = getAllActiveUsers();
$roles = ['System Administrator', 'ICT Technician', 'Staff'];

include __DIR__ . '/../includes/header.php';
?>

<!-- ====== SUCCESS TOAST (for redirect) ====== -->
<?php if (isset($_GET['sent'])): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon">
        <i class="fas fa-check"></i>
    </div>
    <div class="toast-content">
        <div class="toast-title">✅ Sent!</div>
        <p class="toast-message">Notification sent successfully to <?php echo isset($_GET['count']) ? intval($_GET['count']) : ''; ?> recipient(s)!</p>
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
        .success-toast .toast-content .toast-title {
            font-size: 0.9rem;
        }
        .success-toast .toast-content .toast-message {
            font-size: 0.8rem;
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
            // Remove sent param from URL
            const url = new URL(window.location.href);
            url.searchParams.delete('sent');
            url.searchParams.delete('count');
            window.history.replaceState({}, document.title, url.toString());
        }, 2000);
    });
</script>
<?php endif; ?>

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
    
    .recipient-card {
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.3s;
        text-align: center;
    }
    
    .recipient-card:hover {
        border-color: #1a73e8;
        background: #f8f9fa;
    }
    
    .recipient-card.active {
        border-color: #1a73e8;
        background: #e8f0fe;
    }
    
    .recipient-card i {
        font-size: 2rem;
        color: #1a73e8;
        display: block;
        margin-bottom: 8px;
    }
    
    .user-checkbox-list {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 10px;
    }
    
    .user-checkbox-list .form-check {
        padding: 5px 10px;
        border-radius: 6px;
        transition: all 0.2s;
    }
    
    .user-checkbox-list .form-check:hover {
        background: #f8f9fa;
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
    
    @media (max-width: 768px) {
        .form-section {
            padding: 15px;
        }
        .recipient-card {
            padding: 10px;
        }
        .recipient-card i {
            font-size: 1.5rem;
        }
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-paper-plane text-primary"></i> Send Notification</h4>
            <small class="text-muted">Send announcements to users</small>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Notifications
            </a>
        </div>
    </div>
    
    <!-- Error Alert -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Notification Form -->
    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-lg-8">
                <!-- Main Form -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-edit"></i> Notification Details
                    </div>
                    
                    <!-- Title -->
                    <div class="mb-3">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required 
                               placeholder="Enter notification title" maxlength="255"
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    </div>
                    
                    <!-- Message -->
                    <div class="mb-3">
                        <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="message" name="message" rows="5" required 
                                  placeholder="Enter notification message" maxlength="1000"
                                  oninput="updateCharCount(this)"><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        <div class="d-flex justify-content-end mt-1">
                            <span class="char-count" id="charCount">0 / 1000</span>
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
                                $checked = (isset($_POST['notification_type']) && $_POST['notification_type'] === $type) || ($type === 'info' && !isset($_POST['notification_type']));
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="notification_type" 
                                           id="type_<?php echo $type; ?>" value="<?php echo $type; ?>"
                                           <?php echo $checked ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="type_<?php echo $type; ?>">
                                        <span class="notification-type-badge bg-<?php echo $data['color']; ?> bg-opacity-10 text-<?php echo $data['color']; ?>">
                                            <i class="fas <?php echo $data['icon']; ?>"></i> <?php echo $data['label']; ?>
                                        </span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Recipients -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-users"></i> Recipients
                    </div>
                    
                    <!-- All Users -->
                    <div class="recipient-card <?php echo !isset($_POST['recipient_type']) || $_POST['recipient_type'] === 'all' ? 'active' : ''; ?>" 
                         onclick="selectRecipient('all')">
                        <i class="fas fa-globe"></i>
                        <h6>All Users</h6>
                        <small class="text-muted">Send to all active users</small>
                        <input type="radio" name="recipient_type" value="all" 
                               <?php echo !isset($_POST['recipient_type']) || $_POST['recipient_type'] === 'all' ? 'checked' : ''; ?> 
                               style="display:none;">
                    </div>
                    
                    <!-- By Role -->
                    <div class="recipient-card <?php echo isset($_POST['recipient_type']) && $_POST['recipient_type'] === 'role' ? 'active' : ''; ?>" 
                         onclick="selectRecipient('role')">
                        <i class="fas fa-user-tag"></i>
                        <h6>By Role</h6>
                        <small class="text-muted">Send to specific role</small>
                        <input type="radio" name="recipient_type" value="role" 
                               <?php echo isset($_POST['recipient_type']) && $_POST['recipient_type'] === 'role' ? 'checked' : ''; ?> 
                               style="display:none;">
                        <div class="mt-2" id="roleSelect" style="display: <?php echo isset($_POST['recipient_type']) && $_POST['recipient_type'] === 'role' ? 'block' : 'none'; ?>;">
                            <select class="form-select form-select-sm" name="recipient_role" onchange="this.form.querySelector('input[name=recipient_type][value=role]').checked = true;">
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?php echo $r; ?>" <?php echo (isset($_POST['recipient_role']) && $_POST['recipient_role'] === $r) ? 'selected' : ''; ?>>
                                        <?php echo $r; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Specific Users -->
                    <div class="recipient-card <?php echo isset($_POST['recipient_type']) && $_POST['recipient_type'] === 'specific' ? 'active' : ''; ?>" 
                         onclick="selectRecipient('specific')">
                        <i class="fas fa-user-plus"></i>
                        <h6>Specific Users</h6>
                        <small class="text-muted">Select individual users</small>
                        <input type="radio" name="recipient_type" value="specific" 
                               <?php echo isset($_POST['recipient_type']) && $_POST['recipient_type'] === 'specific' ? 'checked' : ''; ?> 
                               style="display:none;">
                        <div class="mt-2" id="userSelect" style="display: <?php echo isset($_POST['recipient_type']) && $_POST['recipient_type'] === 'specific' ? 'block' : 'none'; ?>;">
                            <div class="user-checkbox-list">
                                <?php foreach ($all_users as $user): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="selected_users[]" 
                                               value="<?php echo $user['user_id']; ?>"
                                               id="user_<?php echo $user['user_id']; ?>"
                                               <?php echo (isset($_POST['selected_users']) && in_array($user['user_id'], $_POST['selected_users'])) ? 'checked' : ''; ?>
                                               onchange="document.querySelector('input[name=recipient_type][value=specific]').checked = true;">
                                        <label class="form-check-label" for="user_<?php echo $user['user_id']; ?>">
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                            <span class="badge bg-secondary"><?php echo $user['role']; ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Submit -->
                <div class="form-section">
                    <button type="submit" name="send_notification" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-paper-plane"></i> Send Notification
                    </button>
                    <button type="reset" class="btn btn-outline-secondary w-100 mt-2" onclick="resetForm()">
                        <i class="fas fa-undo"></i> Reset Form
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// ====== SELECT RECIPIENT ======
function selectRecipient(type) {
    // Update radio button
    const radio = document.querySelector(`input[name="recipient_type"][value="${type}"]`);
    if (radio) radio.checked = true;
    
    // Update active state
    document.querySelectorAll('.recipient-card').forEach(card => {
        card.classList.remove('active');
    });
    const cards = document.querySelectorAll('.recipient-card');
    const index = type === 'all' ? 0 : type === 'role' ? 1 : 2;
    if (cards[index]) cards[index].classList.add('active');
    
    // Show/hide options
    document.getElementById('roleSelect').style.display = type === 'role' ? 'block' : 'none';
    document.getElementById('userSelect').style.display = type === 'specific' ? 'block' : 'none';
}

// ====== UPDATE CHARACTER COUNT ======
function updateCharCount(element) {
    const count = element.value.length;
    const max = 1000;
    const counter = document.getElementById('charCount');
    counter.textContent = `${count} / ${max}`;
    counter.classList.toggle('danger', count > max * 0.9);
}

// ====== RESET FORM ======
function resetForm() {
    document.querySelector('form').reset();
    document.getElementById('charCount').textContent = '0 / 1000';
    // Reset recipient selection
    selectRecipient('all');
}

// ====== INITIALIZE ======
document.addEventListener('DOMContentLoaded', function() {
    // Set initial character count
    const messageField = document.getElementById('message');
    if (messageField) {
        updateCharCount(messageField);
    }
    
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

console.log('✅ Send Notification Loaded!');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>