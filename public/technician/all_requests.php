<?php
// public/technician/all_requests.php - View all pending requests and claim them

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

requireRole('ICT Technician');

$db = getDB();
$user_id = $_SESSION['user_id'];

// ====== HANDLE CLAIM REQUEST ======
$success_msg = null;
$error_msg = null;
$claim_request_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_request'])) {
    $request_id = intval($_POST['request_id']);
    $claim_request_id = $request_id;
    
    // Check if request is still pending
    $check_stmt = $db->prepare("SELECT r.*, a.name AS asset_name, u.email AS reporter_email 
                                 FROM maintenance_requests r
                                 LEFT JOIN assets a ON r.asset_id = a.asset_id
                                 LEFT JOIN users u ON r.reported_by = u.user_id
                                 WHERE r.request_id = ? AND r.status = 'Pending'");
    $check_stmt->execute([$request_id]);
    $request = $check_stmt->fetch();
    
    if (!$request) {
        $error_msg = 'This request has already been claimed or is no longer available.';
    } else {
        // Assign technician and update status
        $stmt = $db->prepare("UPDATE maintenance_requests SET assigned_to = ?, status = 'Assigned', assigned_at = NOW() WHERE request_id = ?");
        if ($stmt->execute([$user_id, $request_id])) {
            $success_msg = 'Request #' . $request_id . ' claimed successfully!';
            
            // Send notification to reporter
            if ($request['reported_by']) {
                $title = "👤 Request Assigned";
                $message = "Your request #{$request_id} for asset '{$request['asset_name']}' has been claimed and assigned to a technician.";
                createNotificationForUsers([$request['reported_by']], $title, $message, 'info');
            }
            
            // Redirect to my tasks after 2 seconds
            echo "<meta http-equiv='refresh' content='2;url=my_tasks.php'>";
        } else {
            $error_msg = 'Failed to claim request. Please try again.';
        }
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
               CONCAT(tech.first_name, ' ', tech.last_name) AS assigned_tech_name
        FROM maintenance_requests r
        LEFT JOIN assets a ON r.asset_id = a.asset_id
        LEFT JOIN users reporter ON r.reported_by = reporter.user_id
        LEFT JOIN users tech ON r.assigned_to = tech.user_id
        WHERE 1=1";
$params = [];

// Show only pending requests by default, or all based on filter
if ($filter_status === 'pending') {
    $sql .= " AND r.status = 'Pending'";
} elseif ($filter_status === 'assigned') {
    $sql .= " AND r.status = 'Assigned'";
} elseif ($filter_status === 'in_progress') {
    $sql .= " AND r.status = 'In Progress'";
} elseif ($filter_status === 'resolved') {
    $sql .= " AND r.status = 'Resolved'";
} elseif ($filter_status === 'closed') {
    $sql .= " AND r.status = 'Closed'";
} elseif ($filter_status === 'my_tasks') {
    $sql .= " AND r.assigned_to = ?";
    $params[] = $user_id;
} else {
    // Default: show pending, assigned, and in progress (not completed)
    $sql .= " AND r.status IN ('Pending', 'Assigned', 'In Progress')";
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

$sql .= " ORDER BY 
          CASE r.priority 
              WHEN 'Critical' THEN 1 
              WHEN 'High' THEN 2 
              WHEN 'Medium' THEN 3 
              WHEN 'Low' THEN 4 
          END ASC,
          r.reported_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// ====== STATS ======
$total = count($requests);
$pending = 0; $assigned = 0; $in_progress = 0; $resolved = 0; $closed = 0; $my_tasks = 0;
foreach ($requests as $req) {
    switch ($req['status']) {
        case 'Pending': $pending++; break;
        case 'Assigned': $assigned++; break;
        case 'In Progress': $in_progress++; break;
        case 'Resolved': $resolved++; break;
        case 'Closed': $closed++; break;
    }
    if ($req['assigned_to'] == $user_id) {
        $my_tasks++;
    }
}

// Get total counts for filtering
$stmt_all = $db->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status = 'Pending'");
$pending_total = $stmt_all->fetch()['count'];

$stmt_assigned = $db->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status = 'Assigned'");
$assigned_total = $stmt_assigned->fetch()['count'];

$stmt_progress = $db->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status = 'In Progress'");
$in_progress_total = $stmt_progress->fetch()['count'];

$stmt_my = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests WHERE assigned_to = ? AND status NOT IN ('Resolved', 'Closed')");
$stmt_my->execute([$user_id]);
$my_tasks_total = $stmt_my->fetch()['count'];

// Include header
include __DIR__ . '/../includes/header.php';
?>

<!-- Toast Messages -->
<?php if (isset($success_msg)): ?>
    <div class="success-toast show" id="successToast">
        <div class="toast-icon"><i class="fas fa-check-circle"></i></div>
        <div class="toast-content">
            <div class="toast-title">✅ Success!</div>
            <p class="toast-message"><?php echo htmlspecialchars($success_msg); ?></p>
            <small class="text-muted">Redirecting to My Tasks...</small>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($error_msg)): ?>
    <div class="error-toast show" id="errorToast">
        <div class="toast-icon-error"><i class="fas fa-exclamation-circle"></i></div>
        <div class="toast-content">
            <div class="toast-title-error">❌ Error!</div>
            <p class="toast-message"><?php echo htmlspecialchars($error_msg); ?></p>
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
    .success-toast .toast-content small {
        font-size: 0.75rem;
        color: #6c757d;
    }
    @keyframes slideInRight {
        from { transform: translateX(100px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100px); opacity: 0; }
    }

    /* ====== CONFIRMATION OVERLAY ====== */
    .confirm-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 9998;
        display: none;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(6px);
    }
    .confirm-overlay.show {
        display: flex;
        animation: fadeInOverlay 0.3s ease;
    }

    .confirm-modal {
        background: white;
        border-radius: 24px;
        padding: 40px 50px;
        text-align: center;
        max-width: 480px;
        width: 90%;
        box-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
        animation: bounceIn 0.4s ease;
        position: relative;
    }

    .confirm-modal .confirm-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #28a745, #1e7e34);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        box-shadow: 0 8px 30px rgba(40, 167, 69, 0.3);
    }
    .confirm-modal .confirm-icon i {
        color: white;
        font-size: 2.5rem;
    }

    .confirm-modal .confirm-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #212529;
        margin-bottom: 8px;
    }

    .confirm-modal .confirm-message {
        color: #6c757d;
        font-size: 1rem;
        margin-bottom: 8px;
        line-height: 1.6;
    }
    
    .confirm-modal .confirm-details {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 15px 20px;
        margin: 15px 0 25px;
        text-align: left;
    }
    .confirm-modal .confirm-details p {
        margin: 5px 0;
        font-size: 0.9rem;
        color: #495057;
    }
    .confirm-modal .confirm-details .label {
        font-weight: 600;
        color: #212529;
    }

    .confirm-modal .confirm-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
    }

    .confirm-modal .confirm-actions .btn {
        padding: 12px 30px;
        border-radius: 50px;
        font-weight: 600;
        min-width: 120px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 0.95rem;
    }

    .confirm-modal .confirm-actions .btn:hover {
        transform: scale(1.05);
    }

    .confirm-modal .confirm-actions .btn-cancel {
        background: #e9ecef;
        color: #495057;
    }
    .confirm-modal .confirm-actions .btn-cancel:hover {
        background: #dee2e6;
    }

    .confirm-modal .confirm-actions .btn-claim {
        background: linear-gradient(135deg, #28a745, #1e7e34);
        color: white;
    }
    .confirm-modal .confirm-actions .btn-claim:hover {
        background: linear-gradient(135deg, #218838, #1a6e2a);
        box-shadow: 0 5px 20px rgba(40, 167, 69, 0.4);
    }

    .confirm-modal .close-modal-btn {
        position: absolute;
        top: 15px;
        right: 20px;
        background: transparent;
        border: none;
        font-size: 1.5rem;
        color: #adb5bd;
        cursor: pointer;
        transition: all 0.3s;
    }
    .confirm-modal .close-modal-btn:hover {
        color: #dc3545;
        transform: rotate(90deg);
    }

    @keyframes fadeInOverlay {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes bounceIn {
        0% { transform: scale(0.5); opacity: 0; }
        60% { transform: scale(1.03); }
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
    .filter-btn .badge-claimed { background: #17a2b8; color: white; }
    .filter-btn.active .badge-pending { background: rgba(255,255,255,0.4); color: #212529; }
    .filter-btn.active .badge-claimed { background: rgba(255,255,255,0.4); color: white; }

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

    .btn-claim {
        padding: 4px 14px;
        font-size: 12px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #28a745;
        color: white;
    }
    .btn-claim:hover { background: #218838; transform: scale(1.05); }
    .btn-claim:disabled { background: #6c757d; cursor: not-allowed; transform: none; }

    .modal-header {
        background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
        color: white;
    }
    .modal-header .btn-close { filter: brightness(0) invert(1); }

    .claim-badge {
        font-size: 0.7rem;
        padding: 2px 10px;
        border-radius: 12px;
        background: #cce5ff;
        color: #004085;
    }

    /* ====== RESPONSIVE ====== */
    @media (max-width: 768px) {
        .success-toast, .error-toast {
            top: 70px; right: 10px; left: 10px; min-width: auto; padding: 15px 20px;
        }
        .confirm-modal {
            padding: 30px 20px;
        }
        .confirm-modal .confirm-actions {
            flex-direction: column;
        }
        .confirm-modal .confirm-actions .btn {
            width: 100%;
            min-width: unset;
        }
        .filter-group { gap: 5px; }
        .filter-btn { padding: 3px 10px; font-size: 0.7rem; }
        .search-box { min-width: 150px; }
        .search-box input { font-size: 0.75rem; padding: 5px 10px 5px 28px; }
        .table-responsive { font-size: 0.8rem; }
        .btn-action { padding: 2px 6px; font-size: 10px; }
        .btn-action i { font-size: 10px; }
        .btn-claim { padding: 2px 10px; font-size: 10px; }
        .confirm-modal .confirm-details { padding: 10px 15px; }
    }
    
    @media (max-width: 576px) {
        .confirm-modal { padding: 20px 15px; }
        .confirm-modal .confirm-icon {
            width: 60px;
            height: 60px;
        }
        .confirm-modal .confirm-icon i { font-size: 2rem; }
        .confirm-modal .confirm-title { font-size: 1.2rem; }
        .filter-group .filter-label { display: none; }
    }
</style>

<!-- ====== CONFIRMATION OVERLAY ====== -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-modal">
        <button type="button" class="close-modal-btn" onclick="closeConfirm()">
            <i class="fas fa-times"></i>
        </button>
        <div class="confirm-icon">
            <i class="fas fa-hand-paper"></i>
        </div>
        <h5 class="confirm-title" id="confirmTitle">Claim This Request?</h5>
        <p class="confirm-message" id="confirmMessage">
            You are about to claim this maintenance request. Once claimed, it will be assigned to you.
        </p>
        <div class="confirm-details" id="confirmDetails">
            <p><span class="label">Request ID:</span> <span id="confirmRequestId">#---</span></p>
            <p><span class="label">Asset:</span> <span id="confirmAsset">---</span></p>
            <p><span class="label">Priority:</span> <span id="confirmPriority">---</span></p>
            <p><span class="label">Reported By:</span> <span id="confirmReportedBy">---</span></p>
        </div>
        <div class="confirm-actions">
            <button class="btn btn-cancel" onclick="closeConfirm()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="btn btn-claim" id="confirmClaimBtn" onclick="executeClaim()">
                <i class="fas fa-check"></i> Yes, Claim Request
            </button>
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-clipboard-list text-primary"></i> All Requests</h4>
            <small class="text-muted">View and claim pending maintenance requests</small>
        </div>
        <a href="my_tasks.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-tasks"></i> My Tasks
        </a>
    </div>

    <!-- Stats Row -->
    <div class="row g-2 mb-3">
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-warning mb-0"><?php echo $pending_total; ?></h5>
                <small class="text-muted">Available</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-info mb-0"><?php echo $assigned_total; ?></h5>
                <small class="text-muted">Assigned</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-primary mb-0"><?php echo $in_progress_total; ?></h5>
                <small class="text-muted">In Progress</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-success mb-0"><?php echo $my_tasks_total; ?></h5>
                <small class="text-muted">My Tasks</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-secondary mb-0"><?php echo $resolved + $closed; ?></h5>
                <small class="text-muted">Completed</small>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="filter-group">
                    <span class="filter-label">Filter:</span>
                    <a href="?status=all&priority=<?php echo $filter_priority; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> All
                    </a>
                    <a href="?status=pending&priority=<?php echo $filter_priority; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_status === 'pending' ? 'active' : ''; ?>">
                        <i class="fas fa-clock text-warning"></i> Available
                        <span class="badge badge-pending"><?php echo $pending_total; ?></span>
                    </a>
                    <a href="?status=assigned&priority=<?php echo $filter_priority; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_status === 'assigned' ? 'active' : ''; ?>">
                        <i class="fas fa-user-check text-info"></i> Assigned
                        <span class="badge badge-claimed"><?php echo $assigned_total; ?></span>
                    </a>
                    <a href="?status=in_progress&priority=<?php echo $filter_priority; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_status === 'in_progress' ? 'active' : ''; ?>">
                        <i class="fas fa-spinner text-primary"></i> In Progress
                    </a>
                    <a href="?status=my_tasks&priority=<?php echo $filter_priority; ?>&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_status === 'my_tasks' ? 'active' : ''; ?>">
                        <i class="fas fa-user text-success"></i> My Tasks
                        <span class="badge badge-claimed"><?php echo $my_tasks_total; ?></span>
                    </a>
                </div>

                <span class="text-muted">|</span>

                <div class="filter-group">
                    <span class="filter-label">Priority:</span>
                    <a href="?status=<?php echo $filter_status; ?>&priority=all&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_priority === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="?status=<?php echo $filter_status; ?>&priority=Low&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_priority === 'Low' ? 'active' : ''; ?>">Low</a>
                    <a href="?status=<?php echo $filter_status; ?>&priority=Medium&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_priority === 'Medium' ? 'active' : ''; ?>">Medium</a>
                    <a href="?status=<?php echo $filter_status; ?>&priority=High&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_priority === 'High' ? 'active' : ''; ?>">High</a>
                    <a href="?status=<?php echo $filter_status; ?>&priority=Critical&search=<?php echo urlencode($search); ?>" 
                       class="filter-btn <?php echo $filter_priority === 'Critical' ? 'active' : ''; ?>">Critical</a>
                </div>

                <span class="text-muted">|</span>

                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search by asset or description..." 
                           value="<?php echo htmlspecialchars($search); ?>" onkeyup="searchRequests(this.value)">
                    <i class="fas fa-times clear-search <?php echo !empty($search) ? 'show' : ''; ?>" onclick="clearSearch()"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Requests Table - Issue column removed -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list"></i> Requests</span>
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
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Reported</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($requests) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($requests as $req): ?>
                                <?php
                                $status_class = 'status-' . str_replace(' ', '-', $req['status']);
                                $priority_class = 'priority-' . $req['priority'];
                                $reported_date = date('d M Y, H:i', strtotime($req['reported_at']));
                                $is_my_task = ($req['assigned_to'] == $user_id);
                                $can_claim = ($req['status'] === 'Pending' && empty($req['assigned_to']));
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($req['asset_name'] ?? 'N/A'); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($req['asset_tag'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($req['reported_by_name'] ?? 'Unknown'); ?></td>
                                    <td><span class="priority-badge <?php echo $priority_class; ?>"><?php echo $req['priority']; ?></span></td>
                                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $req['status']; ?></span></td>
                                    <td><?php echo $reported_date; ?></td>
                                    <td class="text-center">
                                        <!-- View Details -->
                                        <button class="btn btn-sm btn-outline-primary btn-action" 
                                                data-bs-toggle="modal" data-bs-target="#viewModal"
                                                data-request-id="<?php echo $req['request_id']; ?>"
                                                data-asset="<?php echo htmlspecialchars($req['asset_name']); ?>"
                                                data-asset-tag="<?php echo htmlspecialchars($req['asset_tag']); ?>"
                                                data-reported-by="<?php echo htmlspecialchars($req['reported_by_name']); ?>"
                                                data-priority="<?php echo $req['priority']; ?>"
                                                data-status="<?php echo $req['status']; ?>"
                                                data-description="<?php echo htmlspecialchars($req['issue_description']); ?>"
                                                data-reported-at="<?php echo $reported_date; ?>"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <?php if ($can_claim): ?>
                                            <!-- Claim Button - triggers centered modal -->
                                            <button class="btn-claim" 
                                                    onclick="showClaimConfirm(
                                                        <?php echo $req['request_id']; ?>,
                                                        '<?php echo htmlspecialchars($req['asset_name']); ?>',
                                                        '<?php echo $req['priority']; ?>',
                                                        '<?php echo htmlspecialchars($req['reported_by_name']); ?>'
                                                    )"
                                                    title="Claim this request">
                                                <i class="fas fa-hand-paper"></i> Claim
                                            </button>
                                        <?php elseif ($is_my_task): ?>
                                            <a href="my_tasks.php" class="btn btn-sm btn-success btn-action" title="Go to My Tasks">
                                                <i class="fas fa-arrow-right"></i> My Task
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Already claimed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="no-results">
                                        <i class="fas fa-inbox"></i>
                                        <h6>No requests found</h6>
                                        <p class="text-muted small">Adjust filters or wait for new requests.</p>
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

<!-- ====== VIEW DETAILS MODAL ====== -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-clipboard-list"></i> Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row" id="viewModalContent">
                    <div class="col-md-6">
                        <p><strong>Request ID:</strong> <span id="viewRequestId"></span></p>
                        <p><strong>Asset:</strong> <span id="viewAsset"></span> (<span id="viewAssetTag"></span>)</p>
                        <p><strong>Reported By:</strong> <span id="viewReportedBy"></span></p>
                        <p><strong>Reported At:</strong> <span id="viewReportedAt"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Priority:</strong> <span id="viewPriority"></span></p>
                        <p><strong>Status:</strong> <span id="viewStatus"></span></p>
                    </div>
                    <div class="col-12">
                        <hr>
                        <p><strong>Issue Description:</strong></p>
                        <p id="viewDescription" class="bg-light p-3 rounded"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // ====== CLAIM CONFIRMATION (Centered Modal) ======
    let claimData = { requestId: 0, asset: '', priority: '', reportedBy: '' };

    function showClaimConfirm(requestId, asset, priority, reportedBy) {
        claimData = { requestId, asset, priority, reportedBy };
        
        const overlay = document.getElementById('confirmOverlay');
        document.getElementById('confirmRequestId').textContent = '#' + requestId;
        document.getElementById('confirmAsset').textContent = asset;
        document.getElementById('confirmReportedBy').textContent = reportedBy;
        
        // Priority with color
        const priorityMap = {
            'Low': '<span class="priority-badge priority-Low">Low</span>',
            'Medium': '<span class="priority-badge priority-Medium">Medium</span>',
            'High': '<span class="priority-badge priority-High">High</span>',
            'Critical': '<span class="priority-badge priority-Critical">Critical</span>'
        };
        document.getElementById('confirmPriority').innerHTML = priorityMap[priority] || priority;
        
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeConfirm() {
        document.getElementById('confirmOverlay').classList.remove('show');
        document.body.style.overflow = '';
        claimData = { requestId: 0, asset: '', priority: '', reportedBy: '' };
    }

    function executeClaim() {
        if (claimData.requestId > 0) {
            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'request_id';
            input.value = claimData.requestId;
            form.appendChild(input);
            const submitBtn = document.createElement('input');
            submitBtn.type = 'hidden';
            submitBtn.name = 'claim_request';
            submitBtn.value = '1';
            form.appendChild(submitBtn);
            document.body.appendChild(form);
            form.submit();
        }
        closeConfirm();
    }

    // ====== SEARCH ======
    let searchTimeout;
    function searchRequests(value) {
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

    // ====== VIEW MODAL DATA PASSING ======
    document.querySelectorAll('[data-bs-target="#viewModal"]').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('viewRequestId').textContent = '#' + this.dataset.requestId;
            document.getElementById('viewAsset').textContent = this.dataset.asset;
            document.getElementById('viewAssetTag').textContent = this.dataset.assetTag;
            document.getElementById('viewReportedBy').textContent = this.dataset.reportedBy;
            document.getElementById('viewReportedAt').textContent = this.dataset.reportedAt;
            
            // Priority with badge
            const priority = this.dataset.priority;
            const priorityMap = {
                'Low': '<span class="priority-badge priority-Low">Low</span>',
                'Medium': '<span class="priority-badge priority-Medium">Medium</span>',
                'High': '<span class="priority-badge priority-High">High</span>',
                'Critical': '<span class="priority-badge priority-Critical">Critical</span>'
            };
            document.getElementById('viewPriority').innerHTML = priorityMap[priority] || priority;
            
            // Status with badge
            const status = this.dataset.status;
            const statusMap = {
                'Pending': '<span class="status-badge status-Pending">Pending</span>',
                'Assigned': '<span class="status-badge status-Assigned">Assigned</span>',
                'In Progress': '<span class="status-badge status-In-Progress">In Progress</span>',
                'Resolved': '<span class="status-badge status-Resolved">Resolved</span>',
                'Closed': '<span class="status-badge status-Closed">Closed</span>'
            };
            document.getElementById('viewStatus').innerHTML = statusMap[status] || status;
            
            document.getElementById('viewDescription').textContent = this.dataset.description;
        });
    });

    // ====== KEYBOARD SHORTCUTS ======
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeConfirm();
            closeToast();
        }
        if (e.key === 'Enter' && document.getElementById('confirmOverlay').classList.contains('show')) {
            executeClaim();
        }
    });

    console.log('✅ All Requests - Technician View Loaded');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>