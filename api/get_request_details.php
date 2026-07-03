<?php
// api/get_request_details.php - Return HTML for request details modal

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo '<div class="alert alert-danger">Unauthorized</div>';
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$db = getDB();

$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($request_id <= 0) {
    echo '<div class="alert alert-warning">Invalid request ID.</div>';
    exit();
}

// Fetch request details with joins
$sql = "SELECT r.*, 
               a.name AS asset_name, a.asset_tag, a.category,
               CONCAT(reporter.first_name, ' ', reporter.last_name) AS reported_by_name,
               CONCAT(tech.first_name, ' ', tech.last_name) AS assigned_tech_name
        FROM maintenance_requests r
        LEFT JOIN assets a ON r.asset_id = a.asset_id
        LEFT JOIN users reporter ON r.reported_by = reporter.user_id
        LEFT JOIN users tech ON r.assigned_to = tech.user_id
        WHERE r.request_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$request_id]);
$req = $stmt->fetch();

if (!$req) {
    echo '<div class="alert alert-warning">Request not found.</div>';
    exit();
}

// Permission check: staff can only view their own, admin/tech can view all
if ($role === 'Staff' && $req['reported_by'] != $user_id) {
    http_response_code(403);
    echo '<div class="alert alert-danger">You do not have permission to view this request.</div>';
    exit();
}

// Fetch tasks if any (optional)
$tasks_sql = "SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) AS tech_name
              FROM maintenance_tasks t
              LEFT JOIN users u ON t.technician_id = u.user_id
              WHERE t.request_id = ? ORDER BY t.task_id";
$stmt_tasks = $db->prepare($tasks_sql);
$stmt_tasks->execute([$request_id]);
$tasks = $stmt_tasks->fetchAll();

// Now output HTML
?>
<div class="request-detail">
    <div class="row">
        <div class="col-md-6">
            <h6><i class="fas fa-box"></i> Asset</h6>
            <p><strong><?php echo htmlspecialchars($req['asset_name'] ?? 'N/A'); ?></strong><br>
            <span class="text-muted">Tag: <?php echo htmlspecialchars($req['asset_tag'] ?? 'N/A'); ?></span><br>
            Category: <?php echo htmlspecialchars($req['category'] ?? 'N/A'); ?></p>
        </div>
        <div class="col-md-6">
            <h6><i class="fas fa-user"></i> Reporter</h6>
            <p><?php echo htmlspecialchars($req['reported_by_name'] ?? 'Unknown'); ?></p>
            <h6><i class="fas fa-user-cog"></i> Assigned To</h6>
            <p><?php echo $req['assigned_to'] ? htmlspecialchars($req['assigned_tech_name'] ?? 'Unknown') : '<span class="text-muted">Not assigned</span>'; ?></p>
        </div>
    </div>

    <hr>

    <div class="row">
        <div class="col-12">
            <h6><i class="fas fa-exclamation-triangle"></i> Issue Description</h6>
            <p class="bg-light p-2 rounded"><?php echo nl2br(htmlspecialchars($req['issue_description'])); ?></p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <h6><i class="fas fa-flag"></i> Priority</h6>
            <span class="priority-badge priority-<?php echo $req['priority']; ?>"><?php echo $req['priority']; ?></span>
        </div>
        <div class="col-md-4">
            <h6><i class="fas fa-circle"></i> Status</h6>
            <span class="status-badge status-<?php echo str_replace(' ', '-', $req['status']); ?>"><?php echo $req['status']; ?></span>
        </div>
        <div class="col-md-4">
            <h6><i class="fas fa-qrcode"></i> QR Scanned</h6>
            <span class="badge bg-<?php echo $req['qr_scanned'] ? 'success' : 'secondary'; ?>">
                <?php echo $req['qr_scanned'] ? 'Yes' : 'No'; ?>
            </span>
        </div>
    </div>

    <hr>

    <div class="row">
        <div class="col-md-6">
            <h6><i class="fas fa-calendar-plus"></i> Reported At</h6>
            <p><?php echo date('d M Y, H:i', strtotime($req['reported_at'])); ?></p>
        </div>
        <?php if ($req['assigned_at']): ?>
        <div class="col-md-6">
            <h6><i class="fas fa-calendar-check"></i> Assigned At</h6>
            <p><?php echo date('d M Y, H:i', strtotime($req['assigned_at'])); ?></p>
        </div>
        <?php endif; ?>
        <?php if ($req['resolved_at']): ?>
        <div class="col-md-6">
            <h6><i class="fas fa-check-circle"></i> Resolved At</h6>
            <p><?php echo date('d M Y, H:i', strtotime($req['resolved_at'])); ?></p>
        </div>
        <?php endif; ?>
        <?php if ($req['closed_at']): ?>
        <div class="col-md-6">
            <h6><i class="fas fa-times-circle"></i> Closed At</h6>
            <p><?php echo date('d M Y, H:i', strtotime($req['closed_at'])); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($req['resolution_notes']): ?>
    <hr>
    <div class="row">
        <div class="col-12">
            <h6><i class="fas fa-sticky-note"></i> Resolution Notes</h6>
            <p class="bg-light p-2 rounded"><?php echo nl2br(htmlspecialchars($req['resolution_notes'])); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tasks): ?>
    <hr>
    <h6><i class="fas fa-tasks"></i> Tasks</h6>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Technician</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Completed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><?php echo $task['task_id']; ?></td>
                    <td><?php echo htmlspecialchars($task['task_description']); ?></td>
                    <td><?php echo htmlspecialchars($task['tech_name'] ?? 'Unknown'); ?></td>
                    <td><span class="badge bg-<?php echo $task['status'] == 'Completed' ? 'success' : ($task['status'] == 'In Progress' ? 'warning' : 'secondary'); ?>"><?php echo $task['status']; ?></span></td>
                    <td><?php echo $task['started_at'] ? date('d M Y', strtotime($task['started_at'])) : '-'; ?></td>
                    <td><?php echo $task['completed_at'] ? date('d M Y', strtotime($task['completed_at'])) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
/* Inline styles for the modal content - can be placed in main CSS later */
.priority-badge {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}
.priority-Low { background: #d4edda; color: #155724; }
.priority-Medium { background: #fff3cd; color: #856404; }
.priority-High { background: #f8d7da; color: #721c24; }
.priority-Critical { background: #dc3545; color: white; }

.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-Pending { background: #fff3cd; color: #856404; }
.status-Assigned { background: #cce5ff; color: #004085; }
.status-In-Progress { background: #d1ecf1; color: #0c5460; }
.status-Resolved { background: #d4edda; color: #155724; }
.status-Closed { background: #e2e3e5; color: #383d41; }
</style>