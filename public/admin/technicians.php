<?php
// public/admin/technicians.php - Manage ICT Technicians with Performance View

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

requireRole('System Administrator');

$db = getDB();

// ====== GET FILTERS ======
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ====== HANDLE ACTIONS ======
$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ====== TOGGLE STATUS (same as users.php) ======
if ($action === 'toggle_status' && $user_id > 0) {
    $stmt = $db->prepare("SELECT first_name, last_name, status FROM users WHERE user_id = ? AND role = 'ICT Technician'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if ($user) {
        $new_status = ($user['status'] === 'active') ? 'inactive' : 'active';
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        if ($stmt->execute([$new_status, $user_id])) {
            header('Location: technicians.php?toggled=1&status=' . $new_status . '&name=' . urlencode($user['first_name'] . ' ' . $user['last_name']));
            exit();
        }
    }
    header('Location: technicians.php?error=user_not_found');
    exit();
}

// ====== DELETE TECHNICIAN ======
if ($action === 'delete' && $user_id > 0) {
    $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE user_id = ? AND role = 'ICT Technician'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if ($user) {
        $name = $user['first_name'] . ' ' . $user['last_name'];
        // Check if has requests
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests WHERE assigned_to = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        if ($result && $result['count'] > 0) {
            header('Location: technicians.php?error=has_requests&name=' . urlencode($name));
            exit();
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
            if ($stmt->execute([$user_id])) {
                header('Location: technicians.php?deleted=1&name=' . urlencode($name));
                exit();
            }
        }
    }
    header('Location: technicians.php?error=delete_failed');
    exit();
}

// ====== FETCH TECHNICIANS WITH PERFORMANCE DATA ======
$sql = "SELECT u.*, 
               COUNT(r.request_id) as total_tasks,
               SUM(CASE WHEN r.status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as completed_tasks,
               SUM(CASE WHEN r.status NOT IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as pending_tasks,
               AVG(TIMESTAMPDIFF(HOUR, r.assigned_at, r.resolved_at)) as avg_resolution_hours,
               MAX(r.resolved_at) as last_completed
        FROM users u
        LEFT JOIN maintenance_requests r ON u.user_id = r.assigned_to
        WHERE u.role = 'ICT Technician'";

$params = [];
if ($filter_status === 'active') {
    $sql .= " AND u.status = 'active'";
} elseif ($filter_status === 'inactive') {
    $sql .= " AND u.status = 'inactive'";
}
if (!empty($search)) {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}
$sql .= " GROUP BY u.user_id ORDER BY completed_tasks DESC, total_tasks DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$technicians = $stmt->fetchAll();

// ====== STATS ======
$total = count($technicians);
$active_count = count(array_filter($technicians, fn($t) => $t['status'] === 'active'));
$inactive_count = $total - $active_count;
$total_tasks = array_sum(array_column($technicians, 'total_tasks'));
$total_completed = array_sum(array_column($technicians, 'completed_tasks'));
$overall_resolution = ($total_tasks > 0) ? round(($total_completed / $total_tasks) * 100, 1) : 0;

// ====== GET DETAILED PERFORMANCE FOR VIEW MODAL ======
$view_tech = null;
if (isset($_GET['view']) && intval($_GET['view']) > 0) {
    $view_id = intval($_GET['view']);
    $stmt = $db->prepare("SELECT u.*, 
                                  COUNT(r.request_id) as total_tasks,
                                  SUM(CASE WHEN r.status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as completed_tasks,
                                  SUM(CASE WHEN r.status NOT IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as pending_tasks,
                                  AVG(TIMESTAMPDIFF(HOUR, r.assigned_at, r.resolved_at)) as avg_resolution_hours,
                                  MAX(r.resolved_at) as last_completed,
                                  (SELECT AVG(TIMESTAMPDIFF(HOUR, assigned_at, resolved_at)) 
                                   FROM maintenance_requests 
                                   WHERE assigned_at IS NOT NULL AND resolved_at IS NOT NULL) as overall_avg
                           FROM users u
                           LEFT JOIN maintenance_requests r ON u.user_id = r.assigned_to
                           WHERE u.user_id = ? AND u.role = 'ICT Technician'
                           GROUP BY u.user_id");
    $stmt->execute([$view_id]);
    $view_tech = $stmt->fetch();
    // Also get task breakdown by status for this technician
    if ($view_tech) {
        $stmt = $db->prepare("SELECT status, COUNT(*) as count 
                               FROM maintenance_requests 
                               WHERE assigned_to = ? 
                               GROUP BY status");
        $stmt->execute([$view_id]);
        $view_tech['status_breakdown'] = $stmt->fetchAll();
        // Get recent activity
        $stmt = $db->prepare("SELECT r.request_id, a.name as asset_name, r.status
                               FROM maintenance_requests r
                               LEFT JOIN assets a ON r.asset_id = a.asset_id
                               WHERE r.assigned_to = ?
                               LIMIT 10");
        $stmt->execute([$view_id]);
        $view_tech['recent_activity'] = $stmt->fetchAll();
    }
}

// Include header
include __DIR__ . '/../includes/header.php';
?>

<!-- ====== TOASTS ====== -->
<?php if (isset($_GET['toggled'])): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon"><i class="fas fa-<?php echo $_GET['status'] === 'active' ? 'play' : 'pause'; ?>"></i></div>
    <div class="toast-content">
        <div class="toast-title"><?php echo $_GET['status'] === 'active' ? '✅ Activated!' : '⏸️ Deactivated!'; ?></div>
        <p class="toast-message"><?php echo htmlspecialchars($_GET['name'] ?? 'Technician'); ?> has been <?php echo $_GET['status'] === 'active' ? 'activated' : 'deactivated'; ?>.</p>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
<div class="success-toast show" id="successToast">
    <div class="toast-icon"><i class="fas fa-trash-alt"></i></div>
    <div class="toast-content">
        <div class="toast-title">🗑️ Deleted!</div>
        <p class="toast-message"><?php echo htmlspecialchars($_GET['name'] ?? 'Technician'); ?> has been deleted.</p>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="error-toast show" id="errorToast">
    <div class="toast-icon-error"><i class="fas fa-exclamation-circle"></i></div>
    <div class="toast-content">
        <div class="toast-title-error">❌ Error</div>
        <p class="toast-message">
            <?php 
            $err = $_GET['error'];
            if ($err === 'has_requests') echo htmlspecialchars($_GET['name'] ?? 'Technician') . ' has maintenance requests and cannot be deleted.';
            elseif ($err === 'user_not_found') echo 'Technician not found.';
            else echo 'An error occurred.';
            ?>
        </p>
    </div>
</div>
<?php endif; ?>

<style>
    /* Reuse styles from users.php and reports */
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
    @keyframes slideInRight {
        from { transform: translateX(100px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100px); opacity: 0; }
    }

    /* Confirmation overlay (reuse) */
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

    /* Filters & Table (reuse from users.php) */
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

    .card { border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
    .card-header { background: white; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 15px 20px; font-weight: 600; }

    .table th {
        background: #f8f9fa;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #dee2e6;
    }
    .table td { vertical-align: middle; }

    .no-results {
        text-align: center; padding: 40px 20px; color: #6c757d;
    }
    .no-results i { font-size: 3rem; color: #dee2e6; margin-bottom: 15px; }

    .modal-header {
        background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
        color: white;
    }
    .modal-header .btn-close { filter: brightness(0) invert(1); }

    /* Performance-specific styles */
    .stat-mini {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 10px 12px;
        text-align: center;
        height: 100%;
    }
    .stat-mini .number {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
    }
    .stat-mini .label {
        font-size: 0.75rem;
        color: #6c757d;
    }
    .stat-mini .trend {
        font-size: 0.7rem;
        font-weight: 600;
    }
    .stat-mini .trend.up { color: #28a745; }
    .stat-mini .trend.down { color: #dc3545; }

    .activity-list {
        max-height: 200px;
        overflow-y: auto;
    }
    .activity-list .list-group-item {
        border-left: 3px solid #1a73e8;
        margin-bottom: 5px;
        border-radius: 6px;
    }
    .progress {
        height: 8px;
        border-radius: 10px;
        background-color: #e9ecef;
        min-width: 80px;
    }
    .progress-bar {
        border-radius: 10px;
    }

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
            <h4 class="fw-bold mb-0"><i class="fas fa-user-cog text-primary"></i> Technicians</h4>
            <small class="text-muted">Manage ICT Technicians and view performance</small>
        </div>
        <a href="users.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-users"></i> All Users
        </a>
    </div>

    <!-- ====== STATS ROW ====== -->
    <div class="row g-2 mb-3">
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
                <h5 class="text-info mb-0"><?php echo $overall_resolution; ?>%</h5>
                <small class="text-muted">Overall Resolution</small>
            </div>
        </div>
    </div>

    <!-- ====== FILTERS ====== -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="filter-group">
                    <span class="filter-label">Status:</span>
                    <a href="?status=all&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                        All <span class="badge"><?php echo $total; ?></span>
                    </a>
                    <a href="?status=active&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_status === 'active' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle text-success"></i> Active <span class="badge"><?php echo $active_count; ?></span>
                    </a>
                    <a href="?status=inactive&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_status === 'inactive' ? 'active' : ''; ?>">
                        <i class="fas fa-times-circle text-danger"></i> Inactive <span class="badge"><?php echo $inactive_count; ?></span>
                    </a>
                </div>
                <span class="text-muted">|</span>
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search technicians..." 
                           value="<?php echo htmlspecialchars($search); ?>" onkeyup="searchTechs(this.value)">
                    <i class="fas fa-times clear-search <?php echo !empty($search) ? 'show' : ''; ?>" onclick="clearSearch()"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== TECHNICIANS TABLE ====== -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list"></i> Technicians</span>
            <span class="badge bg-primary"><?php echo $total; ?> found</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Technician</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Tasks</th>
                            <th>Completed</th>
                            <th>Resolution Rate</th>
                            <th>Avg Time</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($technicians) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($technicians as $tech): 
                                $total_tasks = $tech['total_tasks'] ?? 0;
                                $completed_tasks = $tech['completed_tasks'] ?? 0;
                                $resolution_rate = ($total_tasks > 0) ? round(($completed_tasks / $total_tasks) * 100, 1) : 0;
                                $avg_time = $tech['avg_resolution_hours'] ? round($tech['avg_resolution_hours'], 1) . ' hrs' : 'N/A';
                                $initials = substr($tech['first_name'] ?? 'U', 0, 1) . substr($tech['last_name'] ?? 'N', 0, 1);
                                $status_class = $tech['status'] === 'active' ? 'status-active' : 'status-inactive';
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="user-avatar-mini"><?php echo strtoupper($initials); ?></div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($tech['email']); ?></td>
                                    <td><?php echo htmlspecialchars($tech['department'] ?? 'N/A'); ?></td>
                                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($tech['status']); ?></span></td>
                                    <td><?php echo $total_tasks; ?></td>
                                    <td><?php echo $completed_tasks; ?></td>
                                    <td>
                                        <?php if ($total_tasks > 0): ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <span><?php echo $resolution_rate; ?>%</span>
                                                <div class="progress">
                                                    <div class="progress-bar bg-<?php echo $resolution_rate >= 70 ? 'success' : ($resolution_rate >= 50 ? 'warning' : 'danger'); ?>" 
                                                         style="width: <?php echo $resolution_rate; ?>%;"></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $avg_time; ?></td>
                                    <td class="text-center">
                                        <!-- View Performance -->
                                        <a href="?view=<?php echo $tech['user_id']; ?>" class="btn btn-sm btn-info btn-action" title="View Performance">
                                            <i class="fas fa-chart-bar"></i>
                                        </a>
                                        <!-- Toggle Status -->
                                        <button class="btn btn-sm btn-<?php echo $tech['status'] === 'active' ? 'warning' : 'success'; ?> btn-action"
                                                onclick="showConfirm('<?php echo $tech['status'] === 'active' ? 'deactivate' : 'activate'; ?>', '<?php echo htmlspecialchars($tech['first_name']); ?>', <?php echo $tech['user_id']; ?>)"
                                                title="<?php echo $tech['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas fa-<?php echo $tech['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                        </button>
                                        <!-- Edit (redirect to users.php with edit param) -->
                                        <a href="users.php?edit=<?php echo $tech['user_id']; ?>" class="btn btn-sm btn-primary btn-action" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <!-- Delete -->
                                        <button class="btn btn-sm btn-danger btn-action"
                                                onclick="showConfirm('delete', '<?php echo htmlspecialchars($tech['first_name']); ?>', <?php echo $tech['user_id']; ?>)"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">
                                    <div class="no-results">
                                        <i class="fas fa-user-slash"></i>
                                        <h6>No technicians found</h6>
                                        <p class="text-muted small">Try adjusting filters or add a new technician via Users page.</p>
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

<!-- ====== VIEW PERFORMANCE MODAL ====== -->
<?php if ($view_tech): ?>
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-chart-bar"></i> Performance: <?php echo htmlspecialchars($view_tech['first_name'] . ' ' . $view_tech['last_name']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Quick Stats -->
                <div class="row g-2 mb-3">
                    <div class="col-md-3 col-6">
                        <div class="stat-mini">
                            <p class="number text-primary"><?php echo $view_tech['total_tasks'] ?? 0; ?></p>
                            <p class="label">Total Tasks</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-mini">
                            <p class="number text-success"><?php echo $view_tech['completed_tasks'] ?? 0; ?></p>
                            <p class="label">Completed</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-mini">
                            <p class="number text-warning"><?php echo $view_tech['pending_tasks'] ?? 0; ?></p>
                            <p class="label">Pending</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-mini">
                            <p class="number text-info"><?php 
                                $rate = ($view_tech['total_tasks'] > 0) ? round(($view_tech['completed_tasks'] / $view_tech['total_tasks']) * 100, 1) : 0;
                                echo $rate . '%';
                            ?></p>
                            <p class="label">Resolution Rate</p>
                        </div>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <div class="stat-mini">
                            <p class="number"><?php echo $view_tech['avg_resolution_hours'] ? round($view_tech['avg_resolution_hours'], 1) . ' hrs' : 'N/A'; ?></p>
                            <p class="label">Avg Resolution Time</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-mini">
                            <p class="number"><?php 
                                $overall = $view_tech['overall_avg'] ?? 0;
                                if ($view_tech['avg_resolution_hours'] && $overall > 0) {
                                    $efficiency = round((1 - (($view_tech['avg_resolution_hours'] - $overall) / $overall)) * 100, 1);
                                    $efficiency = max(0, min(100, $efficiency));
                                    echo $efficiency . '%';
                                } else {
                                    echo 'N/A';
                                }
                            ?></p>
                            <p class="label">Efficiency Score</p>
                        </div>
                    </div>
                </div>

                <hr>
                <h6 class="fw-bold"><i class="fas fa-chart-pie me-1"></i> Status Breakdown</h6>
                <div class="row g-2 mb-3">
                    <?php 
                    $status_map = ['Pending' => 'warning', 'Assigned' => 'primary', 'In Progress' => 'info', 'Resolved' => 'success', 'Closed' => 'secondary'];
                    $breakdown = $view_tech['status_breakdown'] ?? [];
                    if (count($breakdown) > 0):
                        foreach ($breakdown as $item):
                    ?>
                        <div class="col-md-2 col-4">
                            <div class="stat-mini">
                                <p class="number text-<?php echo $status_map[$item['status']] ?? 'secondary'; ?>"><?php echo $item['count']; ?></p>
                                <p class="label"><?php echo $item['status']; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-muted">No tasks yet.</div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($view_tech['recent_activity'])): ?>
                <hr>
                <h6 class="fw-bold"><i class="fas fa-history me-1"></i> Recent Activity</h6>
                <div class="activity-list">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($view_tech['recent_activity'] as $activity): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <span class="badge bg-secondary me-1">#<?php echo $activity['request_id']; ?></span>
                                    <?php echo htmlspecialchars($activity['asset_name'] ?? 'Asset'); ?>
                                </span>
                                <span>
                                    <span class="status-badge status-<?php echo str_replace(' ', '-', $activity['status']); ?>"><?php echo $activity['status']; ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
        viewModal.show();
    });
</script>
<?php endif; ?>

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
        title.textContent = '🗑️ Delete Technician?';
        message.textContent = `Are you sure you want to delete "${name}"? This cannot be undone.`;
        icon.className = 'confirm-icon danger';
        icon.innerHTML = '<i class="fas fa-trash-alt"></i>';
        btn.className = 'btn btn-danger';
        btn.textContent = 'Yes, Delete';
    } else if (action === 'deactivate') {
        title.textContent = '⏸️ Deactivate?';
        message.textContent = `Deactivate "${name}"? They will not be able to login.`;
        icon.className = 'confirm-icon warning';
        icon.innerHTML = '<i class="fas fa-pause-circle"></i>';
        btn.className = 'btn btn-warning';
        btn.textContent = 'Yes, Deactivate';
    } else if (action === 'activate') {
        title.textContent = '▶️ Activate?';
        message.textContent = `Activate "${name}"? They will be able to login again.`;
        icon.className = 'confirm-icon success';
        icon.innerHTML = '<i class="fas fa-play-circle"></i>';
        btn.className = 'btn btn-success';
        btn.textContent = 'Yes, Activate';
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
        window.location.href = 'technicians.php?action=' + confirmData.action + '&id=' + confirmData.userId;
    }
    closeConfirm();
}

// ====== SEARCH ======
let searchTimeout;
function searchTechs(value) {
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
    ['toggled', 'deleted', 'error', 'name', 'status'].forEach(function(p) {
        url.searchParams.delete(p);
    });
    window.history.replaceState({}, document.title, url.toString());
}

document.addEventListener('DOMContentLoaded', function() {
    const toasts = document.querySelectorAll('.success-toast, .error-toast');
    if (toasts.length > 0) {
        setTimeout(closeToast, 2500);
    }
    const searchInput = document.getElementById('searchInput');
    const clearBtn = document.querySelector('.clear-search');
    if (searchInput && clearBtn) {
        searchInput.addEventListener('input', function() {
            clearBtn.classList.toggle('show', this.value.length > 0);
        });
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeConfirm(); closeToast(); }
    if (e.key === 'Enter' && document.getElementById('confirmOverlay').classList.contains('show')) {
        executeConfirm();
    }
});

console.log('✅ Technicians Management Loaded');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>