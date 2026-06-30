<?php
// public/admin/users.php - User Management for System Administrator

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/settings_helper.php';

requireRole('System Administrator');

$db = getDB();

// ====== GET FILTERS ======
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_role = isset($_GET['role']) ? $_GET['role'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ====== HANDLE ACTIONS ======
$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ====== ADD USER ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'Staff';
    $department = trim($_POST['department'] ?? '');
    $password = '12345678';
    
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
            $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, department, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            if ($stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password, $role, $department])) {
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
    $status = $_POST['status'] ?? 'active';
    
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
            $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, department = ?, status = ? WHERE user_id = ?");
            if ($stmt->execute([$first_name, $last_name, $email, $phone, $role, $department, $status, $user_id])) {
                header('Location: users.php?updated=1&name=' . urlencode($first_name . ' ' . $last_name));
                exit();
            } else {
                header('Location: users.php?error=edit_failed');
                exit();
            }
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
                header('Location: users.php?deleted=1&name=' . urlencode($name));
                exit();
            } else {
                header('Location: users.php?error=delete_failed');
                exit();
            }
        }
    }
}

// ====== TOGGLE STATUS (DEACTIVATE/ACTIVATE) - FIXED ======
if ($action === 'toggle_status' && $user_id > 0) {
    if ($user_id == $_SESSION['user_id']) {
        header('Location: users.php?error=self_toggle');
        exit();
    } else {
        // Get current user data
        $stmt = $db->prepare("SELECT first_name, last_name, status FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $name = $user['first_name'] . ' ' . $user['last_name'];
            // Toggle status
            $new_status = ($user['status'] === 'active') ? 'inactive' : 'active';
            
            $stmt = $db->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            if ($stmt->execute([$new_status, $user_id])) {
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

// ====== RESET PASSWORD ======
if ($action === 'reset_password' && $user_id > 0) {
    if ($user_id == $_SESSION['user_id']) {
        header('Location: users.php?error=self_reset');
        exit();
    } else {
        $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $name = $user ? $user['first_name'] . ' ' . $user['last_name'] : 'User';
        
        $new_password = '12345678';
        $hashed = hashPassword($new_password);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        if ($stmt->execute([$hashed, $user_id])) {
            header('Location: users.php?reset=1&name=' . urlencode($name));
            exit();
        } else {
            header('Location: users.php?error=reset_failed');
            exit();
        }
    }
}

// ====== BUILD QUERY WITH FILTERS ======
$sql = "SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE 1=1";
$params = [];

// Status filter
if ($filter_status === 'active') {
    $sql .= " AND status = 'active'";
} elseif ($filter_status === 'inactive') {
    $sql .= " AND status = 'inactive'";
}

// Role filter
if ($filter_role !== 'all' && !empty($filter_role)) {
    $sql .= " AND role = ?";
    $params[] = $filter_role;
}

// Search filter
if (!empty($search)) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY created_at DESC";

// Execute query
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get user for edit modal
$edit_user = null;
if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_user = $stmt->fetch();
}

// Include header
include __DIR__ . '/../includes/header.php';
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

<?php if (isset($_GET['updated'])): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon"><i class="fas fa-edit"></i></div>
    <div class="toast-content">
        <div class="toast-title">✅ User Updated!</div>
        <p class="toast-message"><?php echo htmlspecialchars($_GET['name'] ?? 'User'); ?> has been updated successfully!</p>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon"><i class="fas fa-trash-alt"></i></div>
    <div class="toast-content">
        <div class="toast-title">🗑️ User Deleted!</div>
        <p class="toast-message"><?php echo htmlspecialchars($_GET['name'] ?? 'User'); ?> has been deleted successfully!</p>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['toggled'])): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon"><i class="fas fa-<?php echo $_GET['status'] === 'active' ? 'play' : 'pause'; ?>"></i></div>
    <div class="toast-content">
        <div class="toast-title"><?php echo $_GET['status'] === 'active' ? '✅ User Activated!' : '⏸️ User Deactivated!'; ?></div>
        <p class="toast-message"><?php echo htmlspecialchars($_GET['name'] ?? 'User'); ?> has been <?php echo $_GET['status'] === 'active' ? 'activated' : 'deactivated'; ?> successfully!</p>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['reset'])): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon"><i class="fas fa-key"></i></div>
    <div class="toast-content">
        <div class="toast-title">🔑 Password Reset!</div>
        <p class="toast-message">Password for <?php echo htmlspecialchars($_GET['name'] ?? 'User'); ?> has been reset to <strong>12345678</strong>!</p>
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
        z-index: 9999;
        background: white;
        border-radius: 16px;
        padding: 20px 30px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 15px;
        min-width: 300px;
        max-width: 450px;
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
    
    .user-avatar-mini {
        width: 35px; height: 35px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 0.8rem; color: white;
        background: linear-gradient(135deg, #1a73e8, #0d47a1);
        flex-shrink: 0; text-transform: uppercase;
    }
    
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
    }
    
    @media (max-width: 576px) {
        .success-toast, .error-toast {
            top: 60px; right: 5px; left: 5px; padding: 12px 15px; border-radius: 12px;
        }
        .search-box { min-width: 120px; }
        .filter-group .filter-label { display: none; }
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
            <small class="text-muted">Manage system users</small>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus-circle"></i> Add User
        </button>
    </div>

    <!-- ====== STATS ROW ====== -->
    <div class="row g-2 mb-3 stat-cards">
        <?php 
        $total = count($users);
        $active_count = count(array_filter($users, function($u) { return ($u['status'] ?? 'active') === 'active'; }));
        $inactive_count = count(array_filter($users, function($u) { return ($u['status'] ?? 'active') === 'inactive'; }));
        $admin_count = count(array_filter($users, function($u) { return $u['role'] === 'System Administrator'; }));
        ?>
        <div class="col-md-3 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-primary mb-0"><?php echo $total; ?></h5>
                <small class="text-muted">Total</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-success mb-0"><?php echo $active_count; ?></h5>
                <small class="text-muted">Active</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-danger mb-0"><?php echo $inactive_count; ?></h5>
                <small class="text-muted">Inactive</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
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
                    <a href="?status=all&role=<?php echo $filter_role; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                        All <span class="badge"><?php echo $total; ?></span>
                    </a>
                    <a href="?status=active&role=<?php echo $filter_role; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_status === 'active' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle text-success"></i> Active <span class="badge"><?php echo $active_count; ?></span>
                    </a>
                    <a href="?status=inactive&role=<?php echo $filter_role; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_status === 'inactive' ? 'active' : ''; ?>">
                        <i class="fas fa-times-circle text-danger"></i> Inactive <span class="badge"><?php echo $inactive_count; ?></span>
                    </a>
                </div>
                
                <span class="text-muted">|</span>
                
                <!-- Role Filters -->
                <div class="filter-group">
                    <span class="filter-label">Role:</span>
                    <a href="?status=<?php echo $filter_status; ?>&role=all&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_role === 'all' ? 'active' : ''; ?>">
                        All Roles
                    </a>
                    <a href="?status=<?php echo $filter_status; ?>&role=System%20Administrator&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_role === 'System Administrator' ? 'active' : ''; ?>">
                        <i class="fas fa-user-shield text-danger"></i> Admin
                    </a>
                    <a href="?status=<?php echo $filter_status; ?>&role=ICT%20Technician&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_role === 'ICT Technician' ? 'active' : ''; ?>">
                        <i class="fas fa-user-cog text-warning"></i> Technician
                    </a>
                    <a href="?status=<?php echo $filter_status; ?>&role=Staff&search=<?php echo urlencode($search); ?>" 
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
                            <th>Role</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php if (count($users) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($users as $user): ?>
                                <?php 
                                $is_self = ($user['user_id'] == $_SESSION['user_id']);
                                $status_class = ($user['status'] ?? 'active') === 'active' ? 'status-active' : 'status-inactive';
                                $status_text = ($user['status'] ?? 'active') === 'active' ? 'Active' : 'Inactive';
                                $initials = substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'N', 0, 1);
                                
                                $role_colors = [
                                    'System Administrator' => 'danger',
                                    'ICT Technician' => 'warning',
                                    'Staff' => 'info'
                                ];
                                $role_color = $role_colors[$user['role']] ?? 'secondary';
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="user-avatar-mini"><?php echo strtoupper($initials); ?></div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['full_name'] ?? $user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                <?php if ($is_self): ?>
                                                    <span class="badge bg-info ms-1">You</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td><span class="badge bg-<?php echo $role_color; ?>"><?php echo $user['role']; ?></span></td>
                                    <td><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <i class="fas fa-<?php echo ($user['status'] ?? 'active') === 'active' ? 'check-circle' : 'times-circle'; ?>"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
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
                                                data-status="<?php echo $user['status'] ?? 'active'; ?>"
                                                <?php echo $is_self ? 'disabled' : ''; ?>
                                                title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <!-- Toggle Status -->
                                        <?php if (!$is_self): ?>
                                            <button class="btn btn-sm btn-<?php echo ($user['status'] ?? 'active') === 'active' ? 'warning' : 'success'; ?> btn-action"
                                                    onclick="showConfirm('<?php echo ($user['status'] ?? 'active') === 'active' ? 'deactivate' : 'activate'; ?>', '<?php echo htmlspecialchars($user['first_name']); ?>', <?php echo $user['user_id']; ?>)"
                                                    title="<?php echo ($user['status'] ?? 'active') === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-<?php echo ($user['status'] ?? 'active') === 'active' ? 'pause' : 'play'; ?>"></i>
                                            </button>
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
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
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
                        <input type="tel" class="form-control" id="phone" name="phone" required>
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
                        <input type="text" class="form-control" id="department" name="department" placeholder="e.g., ICT Department">
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
                        <input type="tel" class="form-control" id="edit_phone" name="phone" required>
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
                        <input type="text" class="form-control" id="edit_department" name="department" placeholder="e.g., ICT Department">
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
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
    const toasts = document.querySelectorAll('.success-toast, .error-toast');
    toasts.forEach(function(toast) {
        toast.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(function() { toast.style.display = 'none'; }, 300);
    });
    const url = new URL(window.location.href);
    ['added', 'updated', 'deleted', 'toggled', 'reset', 'error', 'name', 'status'].forEach(function(p) {
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
                document.getElementById('edit_phone').value = button.dataset.phone || '';
                document.getElementById('edit_role').value = button.dataset.role || 'Staff';
                document.getElementById('edit_department').value = button.dataset.department || '';
                document.getElementById('edit_status').value = button.dataset.status || 'active';
            }
        });
    }
    
    // Auto close toasts
    const toasts = document.querySelectorAll('.success-toast, .error-toast');
    if (toasts.length > 0) {
        setTimeout(closeToast, 2500);
    }
    
    // Show clear button if search has value
    const searchInput = document.getElementById('searchInput');
    const clearBtn = document.querySelector('.clear-search');
    if (searchInput && clearBtn) {
        searchInput.addEventListener('input', function() {
            clearBtn.classList.toggle('show', this.value.length > 0);
        });
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
console.log('✅ Active:', '<?php echo $active_count; ?>');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>