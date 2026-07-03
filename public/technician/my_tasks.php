<?php
// public/technician/my_tasks.php - Technician Dashboard

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

requireRole('ICT Technician');

$db = getDB();
$user_id = $_SESSION['user_id'];

// ====== HANDLE STATUS UPDATE ======
$success_msg = null;
$error_msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task_status'])) {
    $request_id = intval($_POST['request_id']);
    $status = $_POST['status'] ?? 'Pending';
    $notes = trim($_POST['notes'] ?? '');

    // Verify the request is assigned to this technician
    $check_stmt = $db->prepare("SELECT r.*, a.name AS asset_name, u.email AS reporter_email 
                                 FROM maintenance_requests r
                                 LEFT JOIN assets a ON r.asset_id = a.asset_id
                                 LEFT JOIN users u ON r.reported_by = u.user_id
                                 WHERE r.request_id = ? AND r.assigned_to = ?");
    $check_stmt->execute([$request_id, $user_id]);
    $request = $check_stmt->fetch();

    if (!$request) {
        $error_msg = 'You are not authorized to update this request.';
    } else {
        // Build update query
        $fields = [];
        $params = [];
        if ($status === 'Resolved') {
            $fields[] = "resolved_at = NOW()";
        }
        if ($status === 'Closed') {
            $fields[] = "closed_at = NOW()";
        }
        if (!empty($notes) && ($status === 'Resolved' || $status === 'Closed')) {
            $fields[] = "resolution_notes = ?";
            $params[] = $notes;
        } else if (!empty($notes)) {
            // For other statuses, store notes in a separate field? We'll use resolution_notes for all updates.
            $fields[] = "resolution_notes = ?";
            $params[] = $notes;
        }
        $fields[] = "status = ?";
        $params[] = $status;
        $params[] = $request_id;

        $sql = "UPDATE maintenance_requests SET " . implode(', ', $fields) . " WHERE request_id = ?";
        $stmt = $db->prepare($sql);
        if ($stmt->execute($params)) {
            $success_msg = 'Task status updated successfully.';

            // ----- SEND NOTIFICATIONS -----
            $title = '';
            $message = '';
            $type = 'info';
            if ($status === 'In Progress') {
                $title = "⚙️ Work Started on Your Request";
                $message = "Technician has started working on request #{$request_id} for asset '{$request['asset_name']}'.";
                $type = 'warning';
            } elseif ($status === 'Resolved') {
                $title = "✅ Request Resolved";
                $message = "Your request #{$request_id} for asset '{$request['asset_name']}' has been resolved. ";
                if (!empty($notes)) {
                    $message .= "Notes: " . substr($notes, 0, 150);
                }
                $type = 'success';
            } elseif ($status === 'Closed') {
                $title = "📋 Request Closed";
                $message = "Your request #{$request_id} for asset '{$request['asset_name']}' has been closed.";
                if (!empty($notes)) {
                    $message .= " Notes: " . substr($notes, 0, 150);
                }
                $type = 'info';
            }

            if (!empty($title) && $request['reported_by']) {
                createNotificationForUsers([$request['reported_by']], $title, $message, $type);
            }

            header('Location: my_tasks.php?success=' . urlencode($success_msg));
            exit();
        } else {
            $error_msg = 'Failed to update status.';
        }
    }
    if ($error_msg) {
        header('Location: my_tasks.php?error=' . urlencode($error_msg));
        exit();
    }
}

// ====== FILTERS ======
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ====== BUILD QUERY ======
$sql = "SELECT r.*, 
               a.name AS asset_name, a.asset_tag,
               CONCAT(reporter.first_name, ' ', reporter.last_name) AS reported_by_name,
               reporter.email AS reporter_email
        FROM maintenance_requests r
        LEFT JOIN assets a ON r.asset_id = a.asset_id
        LEFT JOIN users reporter ON r.reported_by = reporter.user_id
        WHERE r.assigned_to = ?";
$params = [$user_id];

if ($filter_status !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $filter_status;
}
if ($filter_priority !== 'all') {
    $sql .= " AND r.priority = ?";
    $params[] = $filter_priority;
}
if (!empty($search)) {
    $sql .= " AND (a.name LIKE ? OR r.issue_description LIKE ? OR r.request_id LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = '%' . $search . '%';
}

$sql .= " ORDER BY r.reported_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// ====== STATS ======
$total = count($tasks);
$pending = 0; $assigned = 0; $in_progress = 0; $resolved = 0; $closed = 0;
foreach ($tasks as $t) {
    switch ($t['status']) {
        case 'Pending': $pending++; break;
        case 'Assigned': $assigned++; break;
        case 'In Progress': $in_progress++; break;
        case 'Resolved': $resolved++; break;
        case 'Closed': $closed++; break;
    }
}

// ====== GET REQUEST DETAILS FOR VIEW MODAL ======
$detail_request = null;
if (isset($_GET['view']) && intval($_GET['view']) > 0) {
    $view_id = intval($_GET['view']);
    $detail_stmt = $db->prepare("SELECT r.*, 
        a.name AS asset_name, a.asset_tag, a.location,
        CONCAT(reporter.first_name, ' ', reporter.last_name) AS reported_by_name,
        CONCAT(tech.first_name, ' ', tech.last_name) AS assigned_tech_name
        FROM maintenance_requests r
        LEFT JOIN assets a ON r.asset_id = a.asset_id
        LEFT JOIN users reporter ON r.reported_by = reporter.user_id
        LEFT JOIN users tech ON r.assigned_to = tech.user_id
        WHERE r.request_id = ? AND r.assigned_to = ?");
    $detail_stmt->execute([$view_id, $user_id]);
    $detail_request = $detail_stmt->fetch();
}

// Include header
include __DIR__ . '/../includes/header.php';
?>

<!-- Toast Messages -->
<?php if (isset($_GET['success'])): ?>
    <div class="success-toast show" id="successToast">
        <div class="toast-icon"><i class="fas fa-check-circle"></i></div>
        <div class="toast-content">
            <div class="toast-title">✅ Success!</div>
            <p class="toast-message"><?php echo htmlspecialchars($_GET['success']); ?></p>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="error-toast show" id="errorToast">
        <div class="toast-icon-error"><i class="fas fa-exclamation-circle"></i></div>
        <div class="toast-content">
            <div class="toast-title-error">❌ Error!</div>
            <p class="toast-message"><?php echo htmlspecialchars($_GET['error']); ?></p>
        </div>
    </div>
<?php endif; ?>

<style>
    /* ====== TOASTS (reused) ====== */
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
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-Pending { background: #fff3cd; color: #856404; }
    .status-Assigned { background: #cce5ff; color: #004085; }
    .status-In\ Progress { background: #d1ecf1; color: #0c5460; }
    .status-Resolved { background: #d4edda; color: #155724; }
    .status-Closed { background: #e2e3e5; color: #383d41; }

    .priority-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .priority-Low { background: #d4edda; color: #155724; }
    .priority-Medium { background: #fff3cd; color: #856404; }
    .priority-High { background: #f8d7da; color: #721c24; }
    .priority-Critical { background: #dc3545; color: white; }

    .card { border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
    .card-header { background: white; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 15px 20px; font-weight: 600; }
    .table th { background: #f8f9fa; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #dee2e6; }
    .table td { vertical-align: middle; }
    .no-results { text-align: center; padding: 40px 20px; color: #6c757d; }
    .no-results i { font-size: 3rem; color: #dee2e6; margin-bottom: 15px; }

    .btn-action {
        padding: 4px 10px;
        font-size: 12px;
        margin: 2px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-action:hover { transform: scale(1.05); }
    .btn-action i { font-size: 13px; }
    .modal-header {
        background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
        color: white;
    }
    .modal-header .btn-close { filter: brightness(0) invert(1); }

    /* ====== RESPONSIVE ====== */
    @media (max-width: 768px) {
        .success-toast, .error-toast {
            top: 70px; right: 10px; left: 10px; min-width: auto; padding: 15px 20px;
        }
        .filter-group { gap: 5px; }
        .filter-btn { padding: 3px 10px; font-size: 0.7rem; }
        .search-box { min-width: 150px; }
        .search-box input { font-size: 0.75rem; padding: 5px 10px 5px 28px; }
        .table-responsive { font-size: 0.8rem; }
        .btn-action { padding: 2px 6px; font-size: 10px; }
        .btn-action i { font-size: 10px; }
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-tasks text-primary"></i> My Tasks</h4>
            <small class="text-muted">Manage your assigned maintenance requests</small>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-2 mb-3">
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-primary mb-0"><?php echo $total; ?></h5>
                <small class="text-muted">Total</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-warning mb-0"><?php echo $pending + $assigned; ?></h5>
                <small class="text-muted">Pending</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-info mb-0"><?php echo $in_progress; ?></h5>
                <small class="text-muted">In Progress</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-success mb-0"><?php echo $resolved; ?></h5>
                <small class="text-muted">Resolved</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-secondary mb-0"><?php echo $closed; ?></h5>
                <small class="text-muted">Closed</small>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="filter-group">
                    <span class="filter-label">Status:</span>
                    <a href="?status=all&priority=<?php echo $filter_priority; ?>&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                        All <span class="badge"><?php echo $total; ?></span>
                    </a>
                    <a href="?status=Pending&priority=<?php echo $filter_priority; ?>&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_status === 'Pending' ? 'active' : ''; ?>">
                        Pending <span class="badge"><?php echo $pending; ?></span>
                    </a>
                    <a href="?status=Assigned&priority=<?php echo $filter_priority; ?>&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_status === 'Assigned' ? 'active' : ''; ?>">
                        Assigned <span class="badge"><?php echo $assigned; ?></span>
                    </a>
                    <a href="?status=In%20Progress&priority=<?php echo $filter_priority; ?>&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_status === 'In Progress' ? 'active' : ''; ?>">
                        In Progress <span class="badge"><?php echo $in_progress; ?></span>
                    </a>
                    <a href="?status=Resolved&priority=<?php echo $filter_priority; ?>&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_status === 'Resolved' ? 'active' : ''; ?>">
                        Resolved <span class="badge"><?php echo $resolved; ?></span>
                    </a>
                    <a href="?status=Closed&priority=<?php echo $filter_priority; ?>&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_status === 'Closed' ? 'active' : ''; ?>">
                        Closed <span class="badge"><?php echo $closed; ?></span>
                    </a>
                </div>

                <span class="text-muted">|</span>

                <div class="filter-group">
                    <span class="filter-label">Priority:</span>
                    <a href="?status=<?php echo $filter_status; ?>&priority=all&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_priority === 'all' ? 'active' : ''; ?>">
                        All
                    </a>
                    <a href="?status=<?php echo $filter_status; ?>&priority=Low&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_priority === 'Low' ? 'active' : ''; ?>">
                        Low
                    </a>
                    <a href="?status=<?php echo $filter_status; ?>&priority=Medium&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_priority === 'Medium' ? 'active' : ''; ?>">
                        Medium
                    </a>
                    <a href="?status=<?php echo $filter_status; ?>&priority=High&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_priority === 'High' ? 'active' : ''; ?>">
                        High
                    </a>
                    <a href="?status=<?php echo $filter_status; ?>&priority=Critical&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $filter_priority === 'Critical' ? 'active' : ''; ?>">
                        Critical
                    </a>
                </div>

                <span class="text-muted">|</span>

                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search by asset or description..." 
                           value="<?php echo htmlspecialchars($search); ?>" onkeyup="searchTasks(this.value)">
                    <i class="fas fa-times clear-search <?php echo !empty($search) ? 'show' : ''; ?>" onclick="clearSearch()"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tasks Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list"></i> Tasks</span>
            <span class="badge bg-primary"><?php echo $total; ?> found</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Asset</th>
                            <th>Reported By</th>
                            <th>Issue</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Reported</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tasks) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($tasks as $task): ?>
                                <?php
                                $status_class = 'status-' . str_replace(' ', '-', $task['status']);
                                $priority_class = 'priority-' . $task['priority'];
                                $reported_date = date('d M Y, H:i', strtotime($task['reported_at']));
                                $can_update = ($task['status'] !== 'Closed');
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($task['asset_name'] ?? 'N/A'); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($task['asset_tag'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($task['reported_by_name'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars(substr($task['issue_description'], 0, 40)); ?>
                                        <?php if (strlen($task['issue_description']) > 40): ?>...<?php endif; ?>
                                    </td>
                                    <td><span class="priority-badge <?php echo $priority_class; ?>"><?php echo $task['priority']; ?></span></td>
                                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $task['status']; ?></span></td>
                                    <td><?php echo $reported_date; ?></td>
                                    <td class="text-center">
                                        <!-- View -->
                                        <a href="?view=<?php echo $task['request_id']; ?>" class="btn btn-sm btn-outline-primary btn-action" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <!-- Update Status (only if not closed) -->
                                        <?php if ($can_update): ?>
                                            <button class="btn btn-sm btn-info btn-action" data-bs-toggle="modal" data-bs-target="#updateStatusModal"
                                                    data-request-id="<?php echo $task['request_id']; ?>"
                                                    data-current-status="<?php echo $task['status']; ?>"
                                                    data-asset="<?php echo htmlspecialchars($task['asset_name']); ?>"
                                                    title="Update Status">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="no-results">
                                        <i class="fas fa-inbox"></i>
                                        <h6>No tasks assigned</h6>
                                        <p class="text-muted small">You have no maintenance requests assigned to you.</p>
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

<!-- ====== UPDATE STATUS MODAL ====== -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sync-alt"></i> Update Task Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="request_id" id="update_request_id">
                <div class="modal-body">
                    <p>Update status for <strong id="update_asset_name"></strong></p>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="update_status" name="status">
                            <option value="Assigned">Assigned</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (optional)</label>
                        <textarea class="form-control" id="update_notes" name="notes" rows="2" placeholder="Add notes about the work done..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_task_status" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== VIEW DETAILS MODAL ====== -->
<?php if ($detail_request): ?>
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-clipboard-list"></i> Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Request ID:</strong> #<?php echo $detail_request['request_id']; ?></p>
                        <p><strong>Asset:</strong> <?php echo htmlspecialchars($detail_request['asset_name'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($detail_request['asset_tag'] ?? ''); ?>)</p>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($detail_request['location'] ?? 'N/A'); ?></p>
                        <p><strong>Reported By:</strong> <?php echo htmlspecialchars($detail_request['reported_by_name'] ?? 'Unknown'); ?></p>
                        <p><strong>Reported At:</strong> <?php echo date('d M Y, H:i', strtotime($detail_request['reported_at'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Priority:</strong> <span class="priority-badge priority-<?php echo $detail_request['priority']; ?>"><?php echo $detail_request['priority']; ?></span></p>
                        <p><strong>Status:</strong> <span class="status-badge status-<?php echo str_replace(' ', '-', $detail_request['status']); ?>"><?php echo $detail_request['status']; ?></span></p>
                        <p><strong>Assigned To:</strong> <?php echo htmlspecialchars($detail_request['assigned_tech_name'] ?? 'Not assigned'); ?></p>
                        <?php if ($detail_request['assigned_at']): ?>
                            <p><strong>Assigned At:</strong> <?php echo date('d M Y, H:i', strtotime($detail_request['assigned_at'])); ?></p>
                        <?php endif; ?>
                        <?php if ($detail_request['resolved_at']): ?>
                            <p><strong>Resolved At:</strong> <?php echo date('d M Y, H:i', strtotime($detail_request['resolved_at'])); ?></p>
                        <?php endif; ?>
                        <?php if ($detail_request['closed_at']): ?>
                            <p><strong>Closed At:</strong> <?php echo date('d M Y, H:i', strtotime($detail_request['closed_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-12">
                        <p><strong>Issue Description:</strong></p>
                        <p><?php echo nl2br(htmlspecialchars($detail_request['issue_description'])); ?></p>
                    </div>
                </div>
                <?php if ($detail_request['resolution_notes']): ?>
                <div class="row">
                    <div class="col-12">
                        <p><strong>Resolution Notes:</strong></p>
                        <p><?php echo nl2br(htmlspecialchars($detail_request['resolution_notes'])); ?></p>
                    </div>
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

<script>
    // ====== SEARCH ======
    let searchTimeout;
    function searchTasks(value) {
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
        url.searchParams.delete('success');
        url.searchParams.delete('error');
        window.history.replaceState({}, document.title, url.toString());
    }

    document.addEventListener('DOMContentLoaded', function() {
        const toasts = document.querySelectorAll('.success-toast, .error-toast');
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
    });

    // ====== UPDATE MODAL DATA PASSING ======
    document.querySelectorAll('[data-bs-target="#updateStatusModal"]').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('update_request_id').value = this.dataset.requestId;
            document.getElementById('update_asset_name').textContent = this.dataset.asset;
            document.getElementById('update_status').value = this.dataset.currentStatus;
            document.getElementById('update_notes').value = '';
        });
    });

    console.log('✅ Technician Dashboard Loaded');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>