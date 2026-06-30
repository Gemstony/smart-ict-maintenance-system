<?php
// public/profile/index.php - User Profile

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/profile_helper.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user = getUserProfile($user_id);
$settings = getUserSettings($user_id);
$profile_picture = getProfilePicture($user_id);
$initials = getUserInitials($user_id);

// Handle messages
$message = $_GET['msg'] ?? '';
$message_type = $_GET['type'] ?? 'success';
$error = $_GET['error'] ?? '';

// Include header
include __DIR__ . '/../includes/header.php';
?>

<!-- ====== PROFILE CONTENT ====== -->
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-user-circle text-primary"></i> My Profile</h4>
            <small class="text-muted">Manage your personal information</small>
        </div>
        <div>
            <a href="<?php echo BASE_URL; ?>public/<?php echo strtolower($_SESSION['role']); ?>/" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Message Alert -->
    <?php if ($message === 'updated'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> Profile updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($message === 'picture_updated'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> Profile picture updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($message === 'password_updated'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> Password changed successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($error === 'incorrect'): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> Current password is incorrect!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($error === 'mismatch'): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> New passwords do not match!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($error === 'weak'): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> Password must be at least 8 characters!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($error === 'file_error'): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_GET['msg'] ?? 'Error uploading file!'; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ====== PROFILE CARD ====== -->
        <div class="col-xl-4 col-lg-5 col-md-12">
            <div class="card">
                <div class="card-body text-center p-4">
                    <!-- Profile Picture -->
                    <div class="position-relative d-inline-block mb-3">
                        <?php if (file_exists(__DIR__ . '/../../assets/uploads/profiles/' . $user['profile_picture']) && $user['profile_picture']): ?>
                            <img src="<?php echo $profile_picture; ?>" 
                                 alt="Profile Picture" 
                                 class="rounded-circle img-fluid" 
                                 style="width: 150px; height: 150px; object-fit: cover; border: 4px solid #1a73e8;">
                        <?php else: ?>
                            <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto" 
                                 style="width: 150px; height: 150px; background: linear-gradient(135deg, #1a73e8, #0d47a1); color: white; font-size: 3rem; font-weight: bold; border: 4px solid #1a73e8;">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Upload Button -->
                        <button class="btn btn-primary btn-sm rounded-circle position-absolute bottom-0 end-0" 
                                style="width: 40px; height: 40px;" 
                                data-bs-toggle="modal" 
                                data-bs-target="#uploadPictureModal"
                                title="Change Profile Picture">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                    
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                    <p class="text-muted mb-2">
                        <span class="badge bg-<?php echo $user['role'] === 'System Administrator' ? 'danger' : ($user['role'] === 'ICT Technician' ? 'warning' : 'info'); ?>">
                            <?php echo $user['role']; ?>
                        </span>
                    </p>
                    <p class="text-muted small mb-3">
                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?>
                    </p>
                    
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Email</small>
                                <small><?php echo htmlspecialchars($user['email']); ?></small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Phone</small>
                                <small><?php echo htmlspecialchars($user['phone']); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                        <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ====== PROFILE DETAILS ====== -->
        <div class="col-xl-8 col-lg-7 col-md-12">
            <!-- Account Information -->
            <div class="card mb-4">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-info-circle text-primary"></i> Account Information
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Full Name</label>
                            <p class="fw-bold mb-0"><?php echo htmlspecialchars($user['full_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Role</label>
                            <p class="fw-bold mb-0"><?php echo $user['role']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Email Address</label>
                            <p class="fw-bold mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Phone Number</label>
                            <p class="fw-bold mb-0"><?php echo htmlspecialchars($user['phone']); ?></p>
                        </div>
                        <div class="col-md-12">
                            <label class="text-muted small">Department</label>
                            <p class="fw-bold mb-0"><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Member Since</label>
                            <p class="fw-bold mb-0"><?php echo formatDate($user['created_at']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Last Updated</label>
                            <p class="fw-bold mb-0"><?php echo formatDate($user['updated_at']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Summary -->
            <div class="card">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-chart-simple text-success"></i> Activity Summary
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center">
                                <h3 class="text-primary mb-0">
                                    <?php 
                                    $db = getDB();
                                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests WHERE reported_by = ?");
                                    $stmt->execute([$user_id]);
                                    echo $stmt->fetch()['count'] ?? 0;
                                    ?>
                                </h3>
                                <small class="text-muted">Total Requests</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center">
                                <h3 class="text-success mb-0">
                                    <?php 
                                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests WHERE reported_by = ? AND status = 'Resolved'");
                                    $stmt->execute([$user_id]);
                                    echo $stmt->fetch()['count'] ?? 0;
                                    ?>
                                </h3>
                                <small class="text-muted">Resolved</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center">
                                <h3 class="text-warning mb-0">
                                    <?php 
                                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests WHERE reported_by = ? AND status IN ('Pending', 'Assigned', 'In Progress')");
                                    $stmt->execute([$user_id]);
                                    echo $stmt->fetch()['count'] ?? 0;
                                    ?>
                                </h3>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====== EDIT PROFILE MODAL ====== -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="update_profile.php" method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="department" class="form-label">Department</label>
                        <input type="text" class="form-control" id="department" name="department" 
                               value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>" 
                               placeholder="e.g., ICT Department">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== CHANGE PASSWORD MODAL ====== -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key"></i> Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="update_profile.php" method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="current_password" name="current_password" required placeholder="Enter current password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" class="form-control" id="new_password" name="new_password" required placeholder="Enter new password (min 8 chars)">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Password must be at least 8 characters long.</small>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-check"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Confirm new password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== UPLOAD PICTURE MODAL ====== -->
<div class="modal fade" id="uploadPictureModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-camera"></i> Upload Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="update_profile.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_picture">
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <div class="drop-zone p-4 border rounded" style="border: 2px dashed #ced4da; cursor: pointer;" 
                             onclick="document.getElementById('profile_picture').click()">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                            <p class="text-muted">Click or drag to upload profile picture</p>
                            <small class="text-muted">Supported formats: JPEG, PNG, GIF, WEBP (Max 2MB)</small>
                            <input type="file" class="d-none" id="profile_picture" name="profile_picture" 
                                   accept="image/*" onchange="previewImage(this)">
                        </div>
                        <div id="imagePreview" class="mt-3 d-none">
                            <img id="previewImg" src="#" alt="Preview" class="img-fluid rounded" style="max-height: 200px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== STYLES ====== -->
<style>
    .drop-zone {
        transition: all 0.3s;
    }
    .drop-zone:hover {
        border-color: #1a73e8 !important;
        background: #f8f9fa;
    }
    .drop-zone.dragover {
        border-color: #1a73e8 !important;
        background: #e8f0fe;
    }
</style>

<!-- ====== SCRIPTS ====== -->
<script>
// Toggle password visibility
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Preview image before upload
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const img = document.getElementById('previewImg');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.classList.remove('d-none');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Drag and drop functionality
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.querySelector('.drop-zone');
    
    if (dropZone) {
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('profile_picture').files = files;
                previewImage(document.getElementById('profile_picture'));
            }
        });
    }
});

// Auto-dismiss alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.click();
            }
        }, 5000);
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>