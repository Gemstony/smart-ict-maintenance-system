<?php
// public/admin/users.php - User Management for System Administrator

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/profile_helper.php';

// ====== SMS CONFIGURATION ======
// Include SMS sender functions
require_once __DIR__ . '/../includes/sms_helper.php';

requireRole('System Administrator');

$db = getDB();

// ====== GET FILTERS ======
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_role = isset($_GET['role']) ? $_GET['role'] : 'all';
$filter_approved = isset($_GET['approved']) ? $_GET['approved'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ====== HANDLE ACTIONS ======
$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ====== APPROVE USER WITH SMS NOTIFICATION ======
if ($action === 'approve' && $user_id > 0) {
    $stmt = $db->prepare("UPDATE users SET is_approved = 1, approved_by = ?, approved_at = NOW(), status = 'active' WHERE user_id = ?");
    if ($stmt->execute([$_SESSION['user_id'], $user_id])) {
        // Get user details for SMS
        $stmt = $db->prepare("SELECT first_name, last_name, email, phone FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        // Send notification to user
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, '✅ Account Approved', 'Your account has been approved by the administrator. You can now login.', 'success')
        ");
        $stmt->execute([$user_id]);
        
        // ====== SEND SMS TO USER ======
        $smsMessage = "IFM ICT: Your account has been approved by the administrator. You can now login.";
        sendSMS($user['phone'], $smsMessage);
        
       
        header('Location: users.php?approved=1&name=' . urlencode($user['first_name'] . ' ' . $user['last_name']));
        exit();
    } else {
        header('Location: users.php?error=approve_failed');
        exit();
    }
}

// ====== REJECT USER WITH SMS NOTIFICATION ======
if ($action === 'reject' && $user_id > 0) {
    $stmt = $db->prepare("SELECT first_name, last_name, phone FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Send rejection notification
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, '❌ Account Rejected', 'Your account registration has been rejected. Please contact the administrator for more information.', 'danger')
        ");
        $stmt->execute([$user_id]);
        
        // ====== SEND SMS TO USER ======
        $smsMessage = "IFM ICT: Sorry, your account registration has been rejected. Please contact the administrator for more information.";
        sendSMS($user['phone'], $smsMessage);
        
        $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        header('Location: users.php?rejected=1&name=' . urlencode($user['first_name'] . ' ' . $user['last_name']));
        exit();
    } else {
        header('Location: users.php?error=user_not_found');
        exit();
    }
}

// ====== GENERATE REGISTRATION LINK ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_link'])) {
    $role = 'Staff'; // DEFAULT ROLE - STAFF
    $department = trim($_POST['department'] ?? '');
    $max_uses = intval($_POST['max_uses'] ?? 1);
    $expires_days = intval($_POST['expires_days'] ?? 7);
    
    if (empty($department)) {
        header('Location: users.php?error=link_failed');
        exit();
    }
    
    $token = bin2hex(random_bytes(32));
    $expires_at = ($expires_days > 0) ? date('Y-m-d H:i:s', strtotime("+$expires_days days")) : null;
    
    $stmt = $db->prepare("
        INSERT INTO registration_links (token, created_by, role, department, max_uses, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$token, $_SESSION['user_id'], $role, $department, $max_uses, $expires_at])) {
        $link_url = BASE_URL . 'public/register.php?token=' . $token;
        $_SESSION['generated_link'] = $link_url;
        $_SESSION['link_expires'] = $expires_at;
        $_SESSION['link_max_uses'] = $max_uses;
        $_SESSION['link_department'] = $department;
        
        header('Location: users.php?link_generated=1');
        exit();
    } else {
        header('Location: users.php?error=link_failed');
        exit();
    }
}

// ====== ADD USER (Admin Direct) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'Staff';
    $department = trim($_POST['department'] ?? '');
    $block = trim($_POST['block'] ?? '');
    $room = trim($_POST['room'] ?? '');
    $password = '12345678';
    
    // Clean phone number - ensure it has 255 prefix
    $phone = cleanPhoneNumber($phone);
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
        header('Location: users.php?error=add_failed');
        exit();
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            header('Location: users.php?error=email_exists');
            exit();
        } else {
            $hashed_password = hashPassword($password);
            $stmt = $db->prepare("
                INSERT INTO users (first_name, last_name, email, phone, password, role, department, block, room, status, is_approved) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 1)
            ");
            if ($stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password, $role, $department, $block, $room])) {
                header('Location: users.php?added=1&name=' . urlencode($first_name . ' ' . $last_name));
                exit();
            } else {
                header('Location: users.php?error=add_failed');
                exit();
            }
        }
    }
}

// ====== EDIT USER ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'Staff';
    $department = trim($_POST['department'] ?? '');
    $block = trim($_POST['block'] ?? '');
    $room = trim($_POST['room'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $is_approved = isset($_POST['is_approved']) ? 1 : 0;
    
    // Clean phone number
    $phone = cleanPhoneNumber($phone);
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
        header('Location: users.php?error=edit_failed');
        exit();
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            header('Location: users.php?error=email_exists');
            exit();
        } else {
            $stmt = $db->prepare("
                UPDATE users SET 
                    first_name = ?, last_name = ?, email = ?, phone = ?, 
                    role = ?, department = ?, block = ?, room = ?, 
                    status = ?, is_approved = ?
                WHERE user_id = ?
            ");
            if ($stmt->execute([$first_name, $last_name, $email, $phone, $role, $department, $block, $room, $status, $is_approved, $user_id])) {
                header('Location: users.php?updated=1&name=' . urlencode($first_name . ' ' . $last_name));
                exit();
            } else {
                header('Location: users.php?error=edit_failed');
                exit();
            }
        }
    }
}

// ====== TOGGLE STATUS (ACTIVATE/DEACTIVATE) - FIXED ======
if ($action === 'toggle_status' && $user_id > 0) {
    if ($user_id == $_SESSION['user_id']) {
        header('Location: users.php?error=self_toggle');
        exit();
    } else {
        // Get current user data
        $stmt = $db->prepare("SELECT first_name, last_name, status, is_approved, phone FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $name = $user['first_name'] . ' ' . $user['last_name'];
            $current_status = $user['status'];
            
            // Toggle status
            $new_status = ($current_status === 'active') ? 'inactive' : 'active';
            
            // If activating, also ensure is_approved is 1
            $is_approved = ($new_status === 'active') ? 1 : $user['is_approved'];
            
            $stmt = $db->prepare("UPDATE users SET status = ?, is_approved = ? WHERE user_id = ?");
            if ($stmt->execute([$new_status, $is_approved, $user_id])) {
                // Send notification
                $title = ($new_status === 'active') ? '✅ Account Activated' : '⏸️ Account Deactivated';
                $message = ($new_status === 'active') 
                    ? 'Your account has been activated. You can now login.' 
                    : 'Your account has been deactivated. Please contact the administrator.';
                
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $title, $message, ($new_status === 'active') ? 'success' : 'warning']);
                
                // ====== SEND SMS TO USER ======
                if ($new_status === 'active') {
                    $smsMessage = "IFM ICT: Your account has been activated by the administrator. You can now login.";
                } else {
                    $smsMessage = "IFM ICT: Your account has been deactivated. Please contact the administrator for more information.";
                }
                sendSMS($user['phone'], $smsMessage);
                
                header('Location: users.php?toggled=1&status=' . $new_status . '&name=' . urlencode($name));
                exit();
            } else {
                header('Location: users.php?error=toggle_failed');
                exit();
            }
        } else {
            header('Location: users.php?error=user_not_found');
            exit();
        }
    }
}

// ====== DELETE USER ======
if ($action === 'delete' && $user_id > 0) {
    if ($user_id == $_SESSION['user_id']) {
        header('Location: users.php?error=self_delete');
        exit();
    } else {
        $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $name = $user ? $user['first_name'] . ' ' . $user['last_name'] : 'User';
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests WHERE reported_by = ? OR assigned_to = ?");
        $stmt->execute([$user_id, $user_id]);
        $result = $stmt->fetch();
        if ($result && $result['count'] > 0) {
            header('Location: users.php?error=has_requests&name=' . urlencode($name));
            exit();
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
            if ($stmt->execute([$user_id])) {
                deleteOldProfilePicture($user_id);
                header('Location: users.php?deleted=1&name=' . urlencode($name));
                exit();
            } else {
                header('Location: users.php?error=delete_failed');
                exit();
            }
        }
    }
}

// ====== RESET PASSWORD ======
if ($action === 'reset_password' && $user_id > 0) {
    if ($user_id == $_SESSION['user_id']) {
        header('Location: users.php?error=self_reset');
        exit();
    } else {
        $stmt = $db->prepare("SELECT first_name, last_name, phone FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $name = $user ? $user['first_name'] . ' ' . $user['last_name'] : 'User';
        
        $new_password = '12345678';
        $hashed = hashPassword($new_password);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        if ($stmt->execute([$hashed, $user_id])) {
            // Send SMS with new password
            $smsMessage = "IFM  ICT: Your new password is 12345678. Please change it after logging in.";
            sendSMS($user['phone'], $smsMessage);
            
            header('Location: users.php?reset=1&name=' . urlencode($name));
            exit();
        } else {
            header('Location: users.php?error=reset_failed');
            exit();
        }
    }
}

// ====== CLEAR LINK SESSION ======
if (isset($_GET['action']) && $_GET['action'] === 'clear_link') {
    unset($_SESSION['generated_link']);
    unset($_SESSION['link_expires']);
    unset($_SESSION['link_max_uses']);
    unset($_SESSION['link_department']);
    exit();
}

// ====== BUILD QUERY WITH FILTERS ======
$sql = "SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE 1=1";
$params = [];

if ($filter_status === 'active') {
    $sql .= " AND status = 'active'";
} elseif ($filter_status === 'inactive') {
    $sql .= " AND status = 'inactive'";
}

if ($filter_role !== 'all' && !empty($filter_role)) {
    $sql .= " AND role = ?";
    $params[] = $filter_role;
}

if ($filter_approved === 'pending') {
    $sql .= " AND is_approved = 0";
} elseif ($filter_approved === 'approved') {
    $sql .= " AND is_approved = 1";
}

if (!empty($search)) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY is_approved ASC, created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get pending users count
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_approved = 0");
$pending_count = $stmt->fetch()['count'];

// Get user for edit modal
$edit_user = null;
if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_user = $stmt->fetch();
}

// Include header
include __DIR__ . '/../includes/header.php';

// Get generated link from session
$generated_link = isset($_SESSION['generated_link']) ? $_SESSION['generated_link'] : null;
$link_expires = isset($_SESSION['link_expires']) ? $_SESSION['link_expires'] : null;
$link_max_uses = isset($_SESSION['link_max_uses']) ? $_SESSION['link_max_uses'] : null;
$link_department = isset($_SESSION['link_department']) ? $_SESSION['link_department'] : null;
?>

<!-- ====== SUCCESS TOASTS ====== -->
<?php if (isset($_GET['added'])): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon"><i class="fas fa-user-plus"></i></div>
    <div class="toast-content">
        <div class="toast-title">✅ User Added!</div>
        <p class="toast-message"><?php echo htmlspecialchars($_GET['name'] ?? 'User'); ?> has been added successfully!</p>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['approved'])): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon"><i class="fas fa-check-circle"></i></div>
    <div class="toast-content">
        <div class="toast-title">✅ User Approved!</div>
        <p class="toast-message"><?php echo htmlspecialchars($_GET['name'] ?? 'User'); ?> has been approved and can now login. <span class="badge bg-info">SMS sent</span></p>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['rejected'])): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon"><i class="fas fa-times-circle"></i></div>
    <div class="toast-content">
        <div class="toast-title">❌ User Rejected!</div>
        <p class="toast-message"><?php echo htmlspecialchars($_GET['name'] ?? 'User'); ?> has been rejected and removed. <span class="badge bg-info">SMS sent</span></p>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['toggled'])): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon"><i class="fas fa-<?php echo $_GET['status'] === 'active' ? 'play' : 'pause'; ?>"></i></div>
    <div class="toast-content">
        <div class="toast-title"><?php echo $_GET['status'] === 'active' ? '✅ User Activated!' : '⏸️ User Deactivated!'; ?></div>
        <p class="toast-message"><?php echo htmlspecialchars($_GET['name'] ?? 'User'); ?> has been <?php echo $_GET['status'] === 'active' ? 'activated' : 'deactivated'; ?> successfully. <span class="badge bg-info">SMS sent</span></p>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['reset'])): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon"><i class="fas fa-key"></i></div>
    <div class="toast-content">
        <div class="toast-title">🔑 Password Reset!</div>
        <p class="toast-message">Password for <?php echo htmlspecialchars($_GET['name'] ?? 'User'); ?> has been reset to <strong>12345678</strong>. <span class="badge bg-info">SMS sent</span></p>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['link_generated']) && $generated_link): ?>
<div class="success-toast show" id="linkToast" style="min-width: 450px; border-left-color: #1a73e8;">
    <div class="toast-icon" style="background: #1a73e8;"><i class="fas fa-link"></i></div>
    <div class="toast-content">
        <div class="toast-title" style="color: #1a73e8;">✅ Registration Link Generated!</div>
        <div class="toast-message">
            <div class="link-info mb-2">
                <small class="text-muted">
                    <i class="fas fa-building"></i> Department: <strong><?php echo htmlspecialchars($link_department); ?></strong> |
                    <i class="fas fa-users"></i> Max Uses: <strong><?php echo $link_max_uses; ?></strong> |
                    <i class="fas fa-clock"></i> Expires: <strong><?php echo $link_expires ? date('d M Y, H:i', strtotime($link_expires)) : 'Never'; ?></strong>
                </small>
            </div>
            <div class="input-group">
                <input type="text" class="form-control form-control-sm" id="registrationLink" value="<?php echo htmlspecialchars($generated_link); ?>" readonly>
                <button class="btn btn-sm btn-primary copy-btn" onclick="copyLink()">
                    <i class="fas fa-copy"></i> Copy
                </button>
                <button class="btn btn-sm btn-secondary dismiss-link-btn" onclick="dismissLink()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <small class="text-muted mt-1 d-block">Click copy to share the link with users. This link will disappear when you close or refresh.</small>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ====== ERROR TOASTS ====== -->
<?php if (isset($_GET['error'])): ?>
<div class="error-toast show" id="errorToast">
    <div class="toast-icon-error"><i class="fas fa-exclamation-circle"></i></div>
    <div class="toast-content">
        <div class="toast-title-error">❌ Error!</div>
        <p class="toast-message">
            <?php 
            $error = $_GET['error'];
            if ($error === 'self_delete') echo 'You cannot delete your own account!';
            elseif ($error === 'self_toggle') echo 'You cannot change your own status!';
            elseif ($error === 'self_reset') echo 'You cannot reset your own password!';
            elseif ($error === 'has_requests') echo htmlspecialchars($_GET['name'] ?? 'User') . ' has maintenance requests and cannot be deleted!';
            elseif ($error === 'email_exists') echo 'Email already exists in the system!';
            elseif ($error === 'add_failed') echo 'Failed to add user! Please try again.';
            elseif ($error === 'edit_failed') echo 'Failed to update user! Please try again.';
            elseif ($error === 'delete_failed') echo 'Failed to delete user!';
            elseif ($error === 'toggle_failed') echo 'Failed to change user status!';
            elseif ($error === 'reset_failed') echo 'Failed to reset password!';
            elseif ($error === 'user_not_found') echo 'User not found!';
            elseif ($error === 'link_failed') echo 'Failed to generate registration link!';
            else echo 'An error occurred!';
            ?>
        </p>
    </div>
</div>
<?php endif; ?>

<style>
    /* ====== TOASTS ====== */
    .success-toast, .error-toast {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 99999;
        background: white;
        border-radius: 16px;
        padding: 20px 30px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 15px;
        min-width: 300px;
        max-width: 550px;
        animation: slideInRight 0.4s ease;
    }
    .success-toast { border-left: 5px solid #28a745; }
    .error-toast { border-left: 5px solid #dc3545; }
    .success-toast .toast-icon, .error-toast .toast-icon-error {
        width: 45px; height: 45px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .success-toast .toast-icon { background: #28a745; color: white; font-size: 1.5rem; }
    .error-toast .toast-icon-error { background: #dc3545; color: white; font-size: 1.5rem; }
    .success-toast .toast-content, .error-toast .toast-content { flex: 1; }
    .success-toast .toast-content .toast-title { font-weight: 700; font-size: 1rem; color: #28a745; margin-bottom: 2px; }
    .error-toast .toast-content .toast-title-error { font-weight: 700; font-size: 1rem; color: #dc3545; margin-bottom: 2px; }
    .success-toast .toast-content .toast-message, .error-toast .toast-content .toast-message {
        font-size: 0.9rem; color: #495057; margin: 0;
    }
    .success-toast .toast-content .toast-message strong { color: #1a73e8; }
    .success-toast .toast-content .toast-message .badge.bg-info { 
        font-size: 0.6rem; 
        padding: 2px 8px;
        vertical-align: middle;
    }
    .success-toast .toast-content .toast-message .input-group {
        display: flex;
        gap: 5px;
        margin-top: 5px;
    }
    .success-toast .toast-content .toast-message .input-group input {
        flex: 1;
        border: 1px solid #ced4da;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 0.85rem;
        background: #f8f9fa;
    }
    .success-toast .toast-content .toast-message .copy-btn {
        padding: 4px 12px;
        border-radius: 4px;
        border: none;
        background: #1a73e8;
        color: white;
        cursor: pointer;
        transition: all 0.3s;
        white-space: nowrap;
        font-size: 0.85rem;
    }
    .success-toast .toast-content .toast-message .copy-btn:hover {
        background: #0d47a1;
        transform: scale(1.05);
    }
    .success-toast .toast-content .toast-message .copy-btn.copied {
        background: #28a745;
    }
    .success-toast .toast-content .toast-message .dismiss-link-btn {
        padding: 4px 10px;
        border-radius: 4px;
        border: none;
        background: #dc3545;
        color: white;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 0.85rem;
    }
    .success-toast .toast-content .toast-message .dismiss-link-btn:hover {
        background: #c82333;
        transform: scale(1.05);
    }
    .success-toast .toast-content .link-info {
        background: #f8f9fa;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 0.8rem;
    }
    
    @keyframes slideInRight {
        from { transform: translateX(100px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100px); opacity: 0; }
    }
    
    /* ====== CONFIRMATION ====== */
    .confirm-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5); z-index: 9998;
        display: none; align-items: center; justify-content: center;
        backdrop-filter: blur(5px);
    }
    .confirm-overlay.show { display: flex; animation: fadeIn 0.3s ease; }
    .confirm-modal {
        background: white; border-radius: 20px; padding: 40px 50px;
        text-align: center; max-width: 420px; width: 90%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: bounceIn 0.4s ease;
    }
    .confirm-modal .confirm-icon {
        width: 70px; height: 70px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 15px;
    }
    .confirm-modal .confirm-icon.danger { background: #dc3545; color: white; }
    .confirm-modal .confirm-icon.warning { background: #ffc107; color: #212529; }
    .confirm-modal .confirm-icon.success { background: #28a745; color: white; }
    .confirm-modal .confirm-icon i { font-size: 2rem; }
    .confirm-modal .confirm-title { font-size: 1.3rem; font-weight: 700; color: #333; margin-bottom: 8px; }
    .confirm-modal .confirm-message { color: #6c757d; font-size: 0.95rem; margin-bottom: 20px; }
    .confirm-modal .confirm-actions { display: flex; gap: 10px; justify-content: center; }
    .confirm-modal .confirm-actions .btn {
        padding: 10px 25px; border-radius: 50px; font-weight: 600;
        min-width: 100px; border: none; cursor: pointer; transition: all 0.3s;
    }
    .confirm-modal .confirm-actions .btn:hover { transform: scale(1.05); }
    .confirm-modal .confirm-actions .btn-cancel { background: #e9ecef; color: #495057; }
    .confirm-modal .confirm-actions .btn-cancel:hover { background: #dee2e6; }
    .confirm-modal .confirm-actions .btn-danger { background: #dc3545; color: white; }
    .confirm-modal .confirm-actions .btn-danger:hover { background: #c82333; }
    .confirm-modal .confirm-actions .btn-warning { background: #ffc107; color: #212529; }
    .confirm-modal .confirm-actions .btn-warning:hover { background: #e0a800; }
    .confirm-modal .confirm-actions .btn-success { background: #28a745; color: white; }
    .confirm-modal .confirm-actions .btn-success:hover { background: #218838; }
    
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes bounceIn {
        0% { transform: scale(0.5); opacity: 0; }
        60% { transform: scale(1.05); }
        100% { transform: scale(1); opacity: 1; }
    }
    
    /* ====== FILTERS ====== */
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }
    .filter-group .filter-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #6c757d;
        margin-right: 4px;
    }
    .filter-btn {
        padding: 4px 14px;
        border-radius: 20px;
        border: 2px solid #e9ecef;
        background: white;
        color: #495057;
        font-size: 0.75rem;
        transition: all 0.3s;
        text-decoration: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .filter-btn:hover { border-color: #1a73e8; color: #1a73e8; }
    .filter-btn.active { background: #1a73e8; border-color: #1a73e8; color: white; }
    .filter-btn .badge { font-size: 0.6rem; margin-left: 4px; background: rgba(0,0,0,0.1); }
    .filter-btn.active .badge { background: rgba(255,255,255,0.3); color: white; }
    .filter-btn .badge-pending { background: #ffc107; color: #212529; }
    .filter-btn.active .badge-pending { background: rgba(255,255,255,0.4); color: #212529; }
    
    .search-box {
        position: relative;
        min-width: 200px;
    }
    .search-box input {
        padding: 6px 12px 6px 32px;
        border-radius: 20px;
        border: 2px solid #e9ecef;
        font-size: 0.8rem;
        width: 100%;
        transition: all 0.3s;
        background: white;
    }
    .search-box input:focus {
        border-color: #1a73e8;
        outline: none;
        box-shadow: 0 0 0 3px rgba(26,115,232,0.1);
    }
    .search-box .search-icon {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
        font-size: 0.8rem;
    }
    .search-box .clear-search {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
        font-size: 0.8rem;
        cursor: pointer;
        display: none;
    }
    .search-box .clear-search.show { display: block; }
    .search-box .clear-search:hover { color: #dc3545; }
    
    /* ====== TABLE ====== */
    .status-badge {
        padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;
    }
    .status-active { background: #d4edda; color: #155724; }
    .status-inactive { background: #f8d7da; color: #721c24; }
    .status-pending { background: #fff3cd; color: #856404; }
    
    /* ====== USER AVATAR ====== */
    .user-avatar-mini {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        color: white;
        flex-shrink: 0;
        overflow: hidden;
        background: linear-gradient(135deg, #1a73e8, #0d47a1);
        text-transform: uppercase;
        border: 2px solid #e9ecef;
        transition: all 0.2s;
    }
    .user-avatar-mini img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .user-avatar-mini.has-image { border-color: #1a73e8; }
    .user-avatar-mini:hover { transform: scale(1.05); border-color: #1a73e8; }
    
    .btn-action {
        padding: 4px 10px; font-size: 12px; margin: 2px;
        border-radius: 6px; border: none; cursor: pointer;
        transition: all 0.3s; display: inline-flex; align-items: center; gap: 4px;
    }
    .btn-action:hover { transform: scale(1.05); }
    .btn-action i { font-size: 13px; }
    
    .modal-header {
        background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
        color: white;
    }
    .modal-header .btn-close { filter: brightness(0) invert(1); }
    
    .table th {
        background: #f8f9fa; font-weight: 600; font-size: 0.75rem;
        text-transform: uppercase; letter-spacing: 0.5px;
        border-bottom: 2px solid #dee2e6;
    }
    .table td { vertical-align: middle; }
    .card { border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
    .card-header { background: white; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 15px 20px; font-weight: 600; }
    
    .no-results {
        text-align: center; padding: 40px 20px; color: #6c757d;
    }
    .no-results i { font-size: 3rem; color: #dee2e6; margin-bottom: 15px; }
    
    .block-badge {
        background: #e8f0fe;
        color: #1a73e8;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .room-badge {
        background: #f1f3f5;
        color: #495057;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    /* Phone number display */
    .phone-display {
        font-family: monospace;
        font-size: 0.85rem;
    }
    
    /* ====== RESPONSIVE ====== */
    @media (max-width: 768px) {
        .success-toast, .error-toast {
            top: 70px; right: 10px; left: 10px; min-width: auto; padding: 15px 20px;
        }
        .confirm-modal { padding: 30px 20px; }
        .confirm-modal .confirm-actions { flex-direction: column; }
        .confirm-modal .confirm-actions .btn { width: 100%; }
        .filter-group { gap: 5px; }
        .filter-btn { padding: 3px 10px; font-size: 0.7rem; }
        .search-box { min-width: 150px; }
        .search-box input { font-size: 0.75rem; padding: 5px 10px 5px 28px; }
        .table-responsive { font-size: 0.8rem; }
        .btn-action { padding: 2px 6px; font-size: 10px; }
        .btn-action i { font-size: 10px; }
        .stat-cards .card { padding: 10px; }
        .stat-cards h5 { font-size: 1.1rem; }
        .user-avatar-mini {
            width: 32px;
            height: 32px;
            font-size: 0.7rem;
        }
    }
    
    @media (max-width: 576px) {
        .success-toast, .error-toast {
            top: 60px; right: 5px; left: 5px; padding: 12px 15px; border-radius: 12px;
        }
        .search-box { min-width: 120px; }
        .filter-group .filter-label { display: none; }
        .user-avatar-mini {
            width: 28px;
            height: 28px;
            font-size: 0.6rem;
        }
    }
</style>

<!-- ====== CONFIRMATION OVERLAY ====== -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-modal">
        <div class="confirm-icon" id="confirmIcon"><i class="fas fa-exclamation-triangle"></i></div>
        <h5 class="confirm-title" id="confirmTitle">Are you sure?</h5>
        <p class="confirm-message" id="confirmMessage">This action cannot be undone.</p>
        <div class="confirm-actions">
            <button class="btn btn-cancel" onclick="closeConfirm()">Cancel</button>
            <button class="btn btn-danger" id="confirmBtn" onclick="executeConfirm()">Yes, Proceed</button>
        </div>
    </div>
</div>

<!-- ====== MAIN CONTENT ====== -->
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-users text-primary"></i> User Management</h4>
            <small class="text-muted">Manage system users, approve registrations, and generate registration links</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#generateLinkModal">
                <i class="fas fa-link"></i> Generate Link
            </button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus-circle"></i> Add User
            </button>
        </div>
    </div>

    <!-- ====== STATS ROW ====== -->
    <?php 
    $total = count($users);
    $active_count = count(array_filter($users, function($u) { return ($u['status'] ?? 'active') === 'active' && $u['is_approved']; }));
    $inactive_count = $total - $active_count - $pending_count;
    $admin_count = count(array_filter($users, function($u) { return $u['role'] === 'System Administrator' && $u['is_approved']; }));
    ?>
    <div class="row g-2 mb-3 stat-cards">
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-primary mb-0"><?php echo $total; ?></h5>
                <small class="text-muted">Total</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-success mb-0"><?php echo $active_count; ?></h5>
                <small class="text-muted">Active</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-warning mb-0"><?php echo $pending_count; ?></h5>
                <small class="text-muted">Pending</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-danger mb-0"><?php echo $inactive_count; ?></h5>
                <small class="text-muted">Inactive</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-warning mb-0"><?php echo $admin_count; ?></h5>
                <small class="text-muted">Admins</small>
            </div>
        </div>
    </div>

    <!-- ====== FILTERS ====== -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Status Filters -->
                <div class="filter-group">
                    <span class="filter-label">Status:</span>
                    <a href="?status=all&role=<?php echo $filter_role; ?>&approved=<?php echo $filter_approved; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                        All <span class="badge"><?php echo $total; ?></span>
                    </a>
                    <a href="?status=active&role=<?php echo $filter_role; ?>&approved=<?php echo $filter_approved; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_status === 'active' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle text-success"></i> Active <span class="badge"><?php echo $active_count; ?></span>
                    </a>
                    <a href="?status=inactive&role=<?php echo $filter_role; ?>&approved=<?php echo $filter_approved; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_status === 'inactive' ? 'active' : ''; ?>">
                        <i class="fas fa-times-circle text-danger"></i> Inactive <span class="badge"><?php echo $inactive_count; ?></span>
                    </a>
                </div>
                
                <span class="text-muted">|</span>
                
                <!-- Approval Filters -->
                <div class="filter-group">
                    <span class="filter-label">Approval:</span>
                    <a href="?status=<?php echo $filter_status; ?>&role=<?php echo $filter_role; ?>&approved=all&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_approved === 'all' ? 'active' : ''; ?>">
                        All
                    </a>
                    <a href="?status=<?php echo $filter_status; ?>&role=<?php echo $filter_role; ?>&approved=pending&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_approved === 'pending' ? 'active' : ''; ?>">
                        <i class="fas fa-clock text-warning"></i> Pending
                        <span class="badge badge-pending"><?php echo $pending_count; ?></span>
                    </a>
                    <a href="?status=<?php echo $filter_status; ?>&role=<?php echo $filter_role; ?>&approved=approved&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_approved === 'approved' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle text-success"></i> Approved
                    </a>
                </div>
                
                <span class="text-muted">|</span>
                
                <!-- Role Filters -->
                <div class="filter-group">
                    <span class="filter-label">Role:</span>
                    <a href="?status=<?php echo $filter_status; ?>&role=all&approved=<?php echo $filter_approved; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_role === 'all' ? 'active' : ''; ?>">
                        All Roles
                    </a>
                    <a href="?status=<?php echo $filter_status; ?>&role=System%20Administrator&approved=<?php echo $filter_approved; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_role === 'System Administrator' ? 'active' : ''; ?>">
                        <i class="fas fa-user-shield text-danger"></i> Admin
                    </a>
                    <a href="?status=<?php echo $filter_status; ?>&role=ICT%20Technician&approved=<?php echo $filter_approved; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_role === 'ICT Technician' ? 'active' : ''; ?>">
                        <i class="fas fa-user-cog text-warning"></i> Technician
                    </a>
                    <a href="?status=<?php echo $filter_status; ?>&role=Staff&approved=<?php echo $filter_approved; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_role === 'Staff' ? 'active' : ''; ?>">
                        <i class="fas fa-user text-info"></i> Staff
                    </a>
                </div>
                
                <span class="text-muted">|</span>
                
                <!-- Search Box -->
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search users..." 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           onkeyup="searchUsers(this.value)">
                    <i class="fas fa-times clear-search <?php echo !empty($search) ? 'show' : ''; ?>" 
                       onclick="clearSearch()"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list"></i> Users</span>
            <span class="badge bg-primary"><?php echo $total; ?> found</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="usersTable" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Block</th>
                            <th>Room</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Approval</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php if (count($users) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($users as $user): ?>
                                <?php 
                                $is_self = ($user['user_id'] == $_SESSION['user_id']);
                                $is_pending = !$user['is_approved'];
                                $status_class = $is_pending ? 'status-pending' : (($user['status'] ?? 'active') === 'active' ? 'status-active' : 'status-inactive');
                                $status_text = $is_pending ? 'Pending' : (($user['status'] ?? 'active') === 'active' ? 'Active' : 'Inactive');
                                $initials = substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'N', 0, 1);
                                
                                $profile_pic = getProfilePicture($user['user_id']);
                                $has_picture = !empty($user['profile_picture']) && file_exists(__DIR__ . '/../../assets/uploads/profiles/' . $user['profile_picture']);
                                
                                $role_colors = [
                                    'System Administrator' => 'danger',
                                    'ICT Technician' => 'warning',
                                    'Staff' => 'info'
                                ];
                                $role_color = $role_colors[$user['role']] ?? 'secondary';
                                
                                // Format phone number for display (add +)
                                $display_phone = $user['phone'] ? '+'.$user['phone'] : 'N/A';
                                ?>
                                <tr class="<?php echo $is_pending ? 'table-warning' : ''; ?>">
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="user-avatar-mini <?php echo $has_picture ? 'has-image' : ''; ?>">
                                                <?php if ($has_picture): ?>
                                                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                                                         alt="<?php echo htmlspecialchars($user['full_name'] ?? $user['first_name'] . ' ' . $user['last_name']); ?>">
                                                <?php else: ?>
                                                    <?php echo strtoupper($initials); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['full_name'] ?? $user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                <?php if ($is_self): ?>
                                                    <span class="badge bg-info ms-1">You</span>
                                                <?php endif; ?>
                                                <?php if ($is_pending): ?>
                                                    <span class="badge bg-warning ms-1">Pending Approval</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td class="phone-display"><?php echo $display_phone; ?></td>
                                    <td>
                                        <?php if ($user['block']): ?>
                                            <span class="block-badge"><?php echo htmlspecialchars($user['block']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['room']): ?>
                                            <span class="room-badge"><?php echo htmlspecialchars($user['room']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-<?php echo $role_color; ?>"><?php echo $user['role']; ?></span></td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <i class="fas fa-<?php echo $is_pending ? 'clock' : (($user['status'] ?? 'active') === 'active' ? 'check-circle' : 'times-circle'); ?>"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['is_approved']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning"><i class="fas fa-clock"></i> Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($is_pending): ?>
                                            <!-- Approve Button -->
                                            <button class="btn btn-sm btn-success btn-action"
                                                    onclick="showConfirm('approve', '<?php echo htmlspecialchars($user['first_name']); ?>', <?php echo $user['user_id']; ?>)"
                                                    title="Approve User">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <!-- Reject Button -->
                                            <button class="btn btn-sm btn-danger btn-action"
                                                    onclick="showConfirm('reject', '<?php echo htmlspecialchars($user['first_name']); ?>', <?php echo $user['user_id']; ?>)"
                                                    title="Reject User">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php else: ?>
                                            <!-- Edit -->
                                            <button class="btn btn-sm btn-primary btn-action" 
                                                    data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                    data-user-id="<?php echo $user['user_id']; ?>"
                                                    data-first-name="<?php echo htmlspecialchars($user['first_name']); ?>"
                                                    data-last-name="<?php echo htmlspecialchars($user['last_name']); ?>"
                                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($user['phone']); ?>"
                                                    data-role="<?php echo $user['role']; ?>"
                                                    data-department="<?php echo htmlspecialchars($user['department'] ?? ''); ?>"
                                                    data-block="<?php echo htmlspecialchars($user['block'] ?? ''); ?>"
                                                    data-room="<?php echo htmlspecialchars($user['room'] ?? ''); ?>"
                                                    data-status="<?php echo $user['status'] ?? 'active'; ?>"
                                                    data-approved="<?php echo $user['is_approved'] ? '1' : '0'; ?>"
                                                    <?php echo $is_self ? 'disabled' : ''; ?>
                                                    title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <!-- Toggle Status (Activate/Deactivate) -->
                                            <?php if (!$is_self): ?>
                                                <?php if (($user['status'] ?? 'active') === 'active'): ?>
                                                    <button class="btn btn-sm btn-warning btn-action"
                                                            onclick="showConfirm('deactivate', '<?php echo htmlspecialchars($user['first_name']); ?>', <?php echo $user['user_id']; ?>)"
                                                            title="Deactivate User">
                                                        <i class="fas fa-pause"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-success btn-action"
                                                            onclick="showConfirm('activate', '<?php echo htmlspecialchars($user['first_name']); ?>', <?php echo $user['user_id']; ?>)"
                                                            title="Activate User">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <!-- Reset Password -->
                                            <?php if (!$is_self): ?>
                                                <button class="btn btn-sm btn-warning btn-action"
                                                        onclick="showConfirm('reset_password', '<?php echo htmlspecialchars($user['first_name']); ?>', <?php echo $user['user_id']; ?>)"
                                                        title="Reset Password to 12345678">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Delete -->
                                            <?php if (!$is_self): ?>
                                                <button class="btn btn-sm btn-danger btn-action"
                                                        onclick="showConfirm('delete', '<?php echo htmlspecialchars($user['first_name']); ?>', <?php echo $user['user_id']; ?>)"
                                                        title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">
                                    <div class="no-results">
                                        <i class="fas fa-users-slash"></i>
                                        <h6>No users found</h6>
                                        <p class="text-muted small">Try adjusting your filters or search terms</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ====== GENERATE REGISTRATION LINK MODAL ====== -->
<div class="modal fade" id="generateLinkModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-link"></i> Generate Registration Link</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p class="text-muted">Generate a unique link that users can use to register themselves.</p>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Note:</strong> Role is set to <strong>Staff</strong> by default. Admin will assign the role and block/room during approval.
                    </div>
                    
                    <div class="mb-3">
                        <label for="link_department" class="form-label">Department <span class="text-danger">*</span></label>
                        <select class="form-select" id="link_department" name="department" required>
                            <option value="ICT Department">ICT Department</option>
                            <option value="Finance Department">Finance Department</option>
                            <option value="Social Science Department">Social Science Department</option>
                            <option value="Mathematics Department">Mathematics Department</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="link_max_uses" class="form-label">Max Uses</label>
                            <select class="form-select" id="link_max_uses" name="max_uses">
                                <option value="1">1 (Single Use)</option>
                                <option value="5">5 Uses</option>
                                <option value="10">10 Uses</option>
                                <option value="25">25 Uses</option>
                                <option value="50">50 Uses</option>
                                <option value="0">Unlimited</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="link_expires" class="form-label">Expires After (Days)</label>
                            <select class="form-select" id="link_expires" name="expires_days">
                                <option value="1">1 Day</option>
                                <option value="3">3 Days</option>
                                <option value="7" selected>7 Days</option>
                                <option value="14">14 Days</option>
                                <option value="30">30 Days</option>
                                <option value="0">Never</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-clock"></i> 
                        <strong>Pending Approval:</strong> Users who register using this link will need admin approval before they can login.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="generate_link" class="btn btn-success">
                        <i class="fas fa-link"></i> Generate Link
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== ADD USER MODAL ====== -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">+255</span>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   placeholder="712345678" required maxlength="9"
                                   oninput="formatPhone(this)">
                        </div>
                        <small class="text-muted">Enter 9 digits after +255 (e.g., 712345678)</small>
                        <span id="phoneCount" class="badge bg-secondary ms-2">0/9</span>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="Staff">Staff</option>
                            <option value="ICT Technician">ICT Technician</option>
                            <option value="System Administrator">System Administrator</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="department" class="form-label">Department</label>
                        <select class="form-select" id="department" name="department" required>
                            <option value="ICT Department">ICT Department</option>
                            <option value="Finance Department">Finance Department</option>
                            <option value="Social Science Department">Social Science Department</option>
                            <option value="Mathematics Department">Mathematics Department</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="block" class="form-label">Block</label>
                            <select class="form-select" id="block" name="block">
                                <option value="">Select Block</option>
                                <option value="Block A">Block A</option>
                                <option value="Block B">Block B</option>
                                <option value="Block C">Block C</option>
                                <option value="Block D">Block D</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="room" class="form-label">Room</label>
                            <select class="form-select" id="room" name="room">
                                <option value="">Select Room</option>
                                <?php for ($i = 1; $i <= 20; $i++): ?>
                                    <option value="Room <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>">
                                        Room <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> Default password: <strong>12345678</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary"><i class="fas fa-save"></i> Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== EDIT USER MODAL ====== -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">+255</span>
                            <input type="tel" class="form-control" id="edit_phone" name="phone" 
                                   placeholder="712345678" required maxlength="9"
                                   oninput="formatPhoneEdit(this)">
                        </div>
                        <small class="text-muted">Enter 9 digits after +255</small>
                        <span id="editPhoneCount" class="badge bg-secondary ms-2">0/9</span>
                    </div>
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="Staff">Staff</option>
                            <option value="ICT Technician">ICT Technician</option>
                            <option value="System Administrator">System Administrator</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_department" class="form-label">Department</label>
                        <select class="form-select" id="edit_department" name="department" required>
                            <option value="ICT Department">ICT Department</option>
                            <option value="Finance Department">Finance Department</option>
                            <option value="Social Science Department">Social Science Department</option>
                            <option value="Mathematics Department">Mathematics Department</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_block" class="form-label">Block</label>
                            <select class="form-select" id="edit_block" name="block">
                                <option value="">Select Block</option>
                                <option value="Block A">Block A</option>
                                <option value="Block B">Block B</option>
                                <option value="Block C">Block C</option>
                                <option value="Block D">Block D</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_room" class="form-label">Room</label>
                            <select class="form-select" id="edit_room" name="room">
                                <option value="">Select Room</option>
                                <?php for ($i = 1; $i <= 20; $i++): ?>
                                    <option value="Room <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>">
                                        Room <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_approved" name="is_approved" value="1">
                            <label class="form-check-label" for="edit_approved">
                                Approved (User can login)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_user" class="btn btn-primary"><i class="fas fa-save"></i> Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== SCRIPTS ====== -->
<script>
// ====== PHONE FORMAT FUNCTIONS ======
function formatPhone(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 9) {
        value = value.slice(0, 9);
    }
    input.value = value;
    updatePhoneCount('phoneCount', value.length);
}

function formatPhoneEdit(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length > 9) {
        value = value.slice(0, 9);
    }
    input.value = value;
    updatePhoneCount('editPhoneCount', value.length);
}

function updatePhoneCount(elementId, length) {
    const countEl = document.getElementById(elementId);
    if (countEl) {
        countEl.textContent = length + '/9';
        if (length === 9) {
            countEl.className = 'badge bg-success ms-2';
        } else if (length >= 7) {
            countEl.className = 'badge bg-warning text-dark ms-2';
        } else {
            countEl.className = 'badge bg-secondary ms-2';
        }
    }
}

// ====== CONFIRMATION ======
let confirmData = { action: '', name: '', userId: 0 };

function showConfirm(action, name, userId) {
    const overlay = document.getElementById('confirmOverlay');
    const title = document.getElementById('confirmTitle');
    const message = document.getElementById('confirmMessage');
    const icon = document.getElementById('confirmIcon');
    const btn = document.getElementById('confirmBtn');
    
    confirmData = { action, name, userId };
    
    if (action === 'delete') {
        title.textContent = '🗑️ Delete User?';
        message.textContent = `Are you sure you want to delete "${name}"? This action cannot be undone.`;
        icon.className = 'confirm-icon danger';
        icon.innerHTML = '<i class="fas fa-trash-alt"></i>';
        btn.className = 'btn btn-danger';
        btn.textContent = 'Yes, Delete User';
    } else if (action === 'deactivate') {
        title.textContent = '⏸️ Deactivate User?';
        message.textContent = `Are you sure you want to deactivate "${name}"? They will not be able to login.`;
        icon.className = 'confirm-icon warning';
        icon.innerHTML = '<i class="fas fa-pause-circle"></i>';
        btn.className = 'btn btn-warning';
        btn.textContent = 'Yes, Deactivate';
    } else if (action === 'activate') {
        title.textContent = '▶️ Activate User?';
        message.textContent = `Are you sure you want to activate "${name}"? They will be able to login again.`;
        icon.className = 'confirm-icon success';
        icon.innerHTML = '<i class="fas fa-play-circle"></i>';
        btn.className = 'btn btn-success';
        btn.textContent = 'Yes, Activate';
    } else if (action === 'reset_password') {
        title.textContent = '🔑 Reset Password?';
        message.textContent = `Are you sure you want to reset "${name}"'s password to 12345678?`;
        icon.className = 'confirm-icon warning';
        icon.innerHTML = '<i class="fas fa-key"></i>';
        btn.className = 'btn btn-warning';
        btn.textContent = 'Yes, Reset Password';
    } else if (action === 'approve') {
        title.textContent = '✅ Approve User?';
        message.textContent = `Approve "${name}"? They will be able to login immediately.`;
        icon.className = 'confirm-icon success';
        icon.innerHTML = '<i class="fas fa-check-circle"></i>';
        btn.className = 'btn btn-success';
        btn.textContent = 'Yes, Approve';
    } else if (action === 'reject') {
        title.textContent = '❌ Reject User?';
        message.textContent = `Reject "${name}"? The user account will be deleted.`;
        icon.className = 'confirm-icon danger';
        icon.innerHTML = '<i class="fas fa-times-circle"></i>';
        btn.className = 'btn btn-danger';
        btn.textContent = 'Yes, Reject';
    }
    
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeConfirm() {
    document.getElementById('confirmOverlay').classList.remove('show');
    document.body.style.overflow = '';
    confirmData = { action: '', name: '', userId: 0 };
}

function executeConfirm() {
    if (confirmData.userId > 0 && confirmData.action) {
        window.location.href = 'users.php?action=' + confirmData.action + '&id=' + confirmData.userId;
    }
    closeConfirm();
}

// ====== COPY LINK FUNCTION ======
function copyLink() {
    const input = document.getElementById('registrationLink');
    const btn = document.querySelector('.copy-btn');
    
    if (input) {
        input.select();
        input.setSelectionRange(0, 99999);
        
        try {
            document.execCommand('copy');
            btn.innerHTML = '✅ Copied!';
            btn.classList.add('copied');
            
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-copy"></i> Copy';
                btn.classList.remove('copied');
            }, 3000);
        } catch (err) {
            navigator.clipboard.writeText(input.value).then(() => {
                btn.innerHTML = '✅ Copied!';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-copy"></i> Copy';
                    btn.classList.remove('copied');
                }, 3000);
            }).catch(() => {
                alert('Failed to copy! Please select and copy manually.');
            });
        }
    }
}

// ====== DISMISS LINK ======
function dismissLink() {
    const toast = document.getElementById('linkToast');
    if (toast) {
        toast.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(() => {
            toast.style.display = 'none';
            fetch('users.php?action=clear_link', { method: 'POST' });
        }, 300);
    }
}

// ====== SEARCH ======
let searchTimeout;

function searchUsers(value) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        const url = new URL(window.location.href);
        if (value.trim()) {
            url.searchParams.set('search', value.trim());
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }, 300);
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    const url = new URL(window.location.href);
    url.searchParams.delete('search');
    window.location.href = url.toString();
}

// ====== TOAST AUTO-CLOSE ======
function closeToast() {
    const toasts = document.querySelectorAll('.success-toast:not(#linkToast), .error-toast');
    toasts.forEach(function(toast) {
        toast.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(function() { toast.style.display = 'none'; }, 300);
    });
    const url = new URL(window.location.href);
    ['added', 'updated', 'deleted', 'toggled', 'reset', 'approved', 'rejected', 'link_generated', 'error', 'name', 'status'].forEach(function(p) {
        url.searchParams.delete(p);
    });
    window.history.replaceState({}, document.title, url.toString());
}

// ====== EDIT MODAL ======
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (button) {
                document.getElementById('edit_user_id').value = button.dataset.userId || '';
                document.getElementById('edit_first_name').value = button.dataset.firstName || '';
                document.getElementById('edit_last_name').value = button.dataset.lastName || '';
                document.getElementById('edit_email').value = button.dataset.email || '';
                
                // Set phone without +255 prefix for editing
                let phone = button.dataset.phone || '';
                if (phone.startsWith('255')) {
                    phone = phone.substring(3);
                }
                document.getElementById('edit_phone').value = phone;
                updatePhoneCount('editPhoneCount', phone.length);
                
                document.getElementById('edit_role').value = button.dataset.role || 'Staff';
                document.getElementById('edit_department').value = button.dataset.department || '';
                document.getElementById('edit_block').value = button.dataset.block || '';
                document.getElementById('edit_room').value = button.dataset.room || '';
                document.getElementById('edit_status').value = button.dataset.status || 'active';
                document.getElementById('edit_approved').checked = parseInt(button.dataset.approved || '0') === 1;
            }
        });
    }
    
    // Auto close toasts
    const toasts = document.querySelectorAll('.success-toast:not(#linkToast), .error-toast');
    if (toasts.length > 0) {
        setTimeout(closeToast, 4000);
    }
    
    // Show clear button if search has value
    const searchInput = document.getElementById('searchInput');
    const clearBtn = document.querySelector('.clear-search');
    if (searchInput && clearBtn) {
        searchInput.addEventListener('input', function() {
            clearBtn.classList.toggle('show', this.value.length > 0);
        });
    }
    
    // Initialize phone count for add user
    const addPhone = document.getElementById('phone');
    if (addPhone && addPhone.value) {
        formatPhone(addPhone);
    }
});

// ====== KEYBOARD SHORTCUTS ======
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeConfirm(); closeToast(); }
    if (e.key === 'Enter' && document.getElementById('confirmOverlay').classList.contains('show')) {
        executeConfirm();
    }
});

console.log('✅ Users Management Loaded!');
console.log('👥 Total:', '<?php echo $total; ?>');
console.log('⏳ Pending:', '<?php echo $pending_count; ?>');
console.log('✅ Active:', '<?php echo $active_count; ?>');
console.log('📱 SMS Notifications: Enabled');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>