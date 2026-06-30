<?php
// public/admin/users.php - User Management for System Administrator

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole('System Administrator');

$db = getDB();
$message = '';
$message_type = '';

// ====== HANDLE ACTIONS ======
$action = $_GET['action'] ?? '';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ====== ADD USER ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'Staff';
    $department = trim($_POST['department'] ?? '');
    $password = '12345678'; // Default password
    
    // Validate
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
        $message = 'All fields are required!';
        $message_type = 'danger';
    } else {
        // Check if email exists
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $message = 'Email already exists!';
            $message_type = 'danger';
        } else {
            $hashed_password = hashPassword($password);
            $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, department) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password, $role, $department])) {
                $message = 'User added successfully! Default password: 12345678';
                $message_type = 'success';
            } else {
                $message = 'Failed to add user!';
                $message_type = 'danger';
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
        $message = 'All fields are required!';
        $message_type = 'danger';
    } else {
        // Check if email exists for other users
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $message = 'Email already exists for another user!';
            $message_type = 'danger';
        } else {
            $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, department = ?, status = ? WHERE user_id = ?");
            if ($stmt->execute([$first_name, $last_name, $email, $phone, $role, $department, $status, $user_id])) {
                $message = 'User updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to update user!';
                $message_type = 'danger';
            }
        }
    }
}

// ====== DELETE USER ======
if ($action === 'delete' && $user_id > 0) {
    // Prevent deleting self
    if ($user_id == $_SESSION['user_id']) {
        $message = 'You cannot delete your own account!';
        $message_type = 'danger';
    } else {
        // Check if user has any maintenance requests
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests WHERE reported_by = ? OR assigned_to = ?");
        $stmt->execute([$user_id, $user_id]);
        $result = $stmt->fetch();
        if ($result && $result['count'] > 0) {
            $message = 'Cannot delete user with maintenance requests!';
            $message_type = 'danger';
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
            if ($stmt->execute([$user_id])) {
                $message = 'User deleted successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to delete user!';
                $message_type = 'danger';
            }
        }
    }
}

// ====== DEACTIVATE/ACTIVATE USER ======
if ($action === 'toggle_status' && $user_id > 0) {
    if ($user_id == $_SESSION['user_id']) {
        $message = 'You cannot change your own status!';
        $message_type = 'danger';
    } else {
        // Get current status
        $stmt = $db->prepare("SELECT status FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user) {
            $new_status = ($user['status'] === 'active') ? 'inactive' : 'active';
            $stmt = $db->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            if ($stmt->execute([$new_status, $user_id])) {
                $message = 'User ' . ($new_status === 'active' ? 'activated' : 'deactivated') . ' successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to update user status!';
                $message_type = 'danger';
            }
        }
    }
}

// ====== RESET PASSWORD ======
if ($action === 'reset_password' && $user_id > 0) {
    if ($user_id == $_SESSION['user_id']) {
        $message = 'You cannot reset your own password!';
        $message_type = 'danger';
    } else {
        $new_password = '12345678';
        $hashed = hashPassword($new_password);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        if ($stmt->execute([$hashed, $user_id])) {
            $message = 'Password reset to 12345678 successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to reset password!';
            $message_type = 'danger';
        }
    }
}

// ====== GET ALL USERS ======
$stmt = $db->query("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Get user for edit modal
$edit_user = null;
if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #0d47a1;
            padding: 20px 0;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        .sidebar .nav-link i {
            width: 25px;
        }
        .sidebar .brand {
            color: white;
            font-size: 1.3rem;
            font-weight: bold;
            padding: 10px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .content-area {
            background: #f8f9fa;
            min-height: calc(100vh - 70px);
            padding: 30px;
        }
        .top-nav {
            background: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .badge-notif {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.6rem;
        }
        .btn-action {
            padding: 4px 8px;
            font-size: 14px;
            margin: 2px;
            border-radius: 4px;
        }
        .btn-action i {
            margin: 0;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #1a73e8;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
        }
        .table th {
            background: #f1f3f5;
            font-weight: 600;
        }
        .modal-header {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            color: white;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 6px 12px;
        }
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 6px 12px;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 p-0 sidebar">
            <div class="brand">
                <i class="fas fa-microchip"></i> ICT-AMS
            </div>
            <nav class="nav flex-column">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="assets.php" class="nav-link">
                    <i class="fas fa-laptop"></i> Assets
                </a>
                <a href="technicians.php" class="nav-link">
                    <i class="fas fa-user-cog"></i> Technicians
                </a>
                <a href="users.php" class="nav-link active">
                    <i class="fas fa-users"></i> Users
                </a>
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <hr style="border-color: rgba(255,255,255,0.1);">
                <a href="../../logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 p-0">
            <!-- Top Nav -->
            <div class="top-nav px-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0"><i class="fas fa-users text-primary"></i> User Management</h5>
                        <small class="text-muted">Manage system users</small>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus-circle"></i> Add User
                        </button>
                        <a href="../../logout.php" class="btn btn-danger ms-2">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="content-area">
                <!-- Message Alert -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Users Table -->
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-list"></i> All Users
                        <span class="badge bg-primary ms-2"><?php echo count($users); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="usersTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($users as $user): ?>
                                        <?php 
                                        $is_self = ($user['user_id'] == $_SESSION['user_id']);
                                        $status_class = ($user['status'] ?? 'active') === 'active' ? 'status-active' : 'status-inactive';
                                        $status_text = ($user['status'] ?? 'active') === 'active' ? 'Active' : 'Inactive';
                                        $initials = substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'N', 0, 1);
                                        ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-2"><?php echo strtoupper($initials); ?></div>
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
                                            <td>
                                                <?php 
                                                $role_badge = [
                                                    'System Administrator' => 'danger',
                                                    'ICT Technician' => 'warning',
                                                    'Staff' => 'info'
                                                ];
                                                $badge_color = $role_badge[$user['role']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $badge_color; ?>"><?php echo $user['role']; ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <i class="fas fa-<?php echo ($user['status'] ?? 'active') === 'active' ? 'check-circle' : 'times-circle'; ?>"></i>
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <!-- Edit Button -->
                                                <button class="btn btn-sm btn-primary btn-action" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editUserModal"
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
                                                
                                                <!-- Toggle Status Button -->
                                                <?php if (!$is_self): ?>
                                                    <a href="?action=toggle_status&id=<?php echo $user['user_id']; ?>" 
                                                       class="btn btn-sm btn-<?php echo ($user['status'] ?? 'active') === 'active' ? 'warning' : 'success'; ?> btn-action"
                                                       onclick="return confirm('Are you sure you want to <?php echo ($user['status'] ?? 'active') === 'active' ? 'deactivate' : 'activate'; ?> this user?')"
                                                       title="<?php echo ($user['status'] ?? 'active') === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="fas fa-<?php echo ($user['status'] ?? 'active') === 'active' ? 'pause' : 'play'; ?>"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <!-- Reset Password Button -->
                                                <?php if (!$is_self): ?>
                                                    <a href="?action=reset_password&id=<?php echo $user['user_id']; ?>" 
                                                       class="btn btn-sm btn-warning btn-action"
                                                       onclick="return confirm('Reset password for <?php echo htmlspecialchars($user['first_name']); ?> to 12345678?')"
                                                       title="Reset Password to 12345678">
                                                        <i class="fas fa-key"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <!-- Delete Button -->
                                                <?php if (!$is_self): ?>
                                                    <a href="?action=delete&id=<?php echo $user['user_id']; ?>" 
                                                       class="btn btn-sm btn-danger btn-action"
                                                       onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($user['first_name']); ?>? This action cannot be undone!')"
                                                       title="Delete User">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
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
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Default password will be: <strong>12345678</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add User
                    </button>
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
                    <button type="submit" name="edit_user" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
    // ====== DATATABLE ======
    $(document).ready(function() {
        $('#usersTable').DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            order: [[0, 'asc']],
            columnDefs: [
                { orderable: false, targets: [7] } // Disable sorting on Actions column
            ],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ users",
                infoEmpty: "No users found",
                infoFiltered: "(filtered from _MAX_ total users)"
            }
        });
    });

    // ====== EDIT USER MODAL - Populate data ======
    document.addEventListener('DOMContentLoaded', function() {
        const editModal = document.getElementById('editUserModal');
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            // Get data from button attributes
            document.getElementById('edit_user_id').value = button.dataset.userId;
            document.getElementById('edit_first_name').value = button.dataset.firstName;
            document.getElementById('edit_last_name').value = button.dataset.lastName;
            document.getElementById('edit_email').value = button.dataset.email;
            document.getElementById('edit_phone').value = button.dataset.phone;
            document.getElementById('edit_role').value = button.dataset.role;
            document.getElementById('edit_department').value = button.dataset.department || '';
            document.getElementById('edit_status').value = button.dataset.status || 'active';
        });
    });

    // ====== AUTO-DISMISS ALERT ======
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                setTimeout(function() {
                    alert.classList.remove('show');
                    alert.style.display = 'none';
                }, 5000);
            }
        });
    }, 100);
</script>
</body>
</html>