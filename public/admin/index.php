<?php
// public/admin/index.php - Admin Dashboard

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/settings_helper.php';

// Check if user is logged in and is admin
requireRole('System Administrator');

// Get user data
$user = getUser($_SESSION['user_id']);
$counts = getDashboardCounts('System Administrator');
$notifications = getNotifications($_SESSION['user_id'], 5);
$unread = getUnreadNotifications($_SESSION['user_id']);

// Get database connection
$db = getDB();

// Get recent requests with full names
$stmt = $db->query("SELECT r.*, a.name as asset_name, 
                    CONCAT(u.first_name, ' ', u.last_name) as reported_by_name 
                    FROM maintenance_requests r 
                    LEFT JOIN assets a ON r.asset_id = a.asset_id 
                    LEFT JOIN users u ON r.reported_by = u.user_id 
                    ORDER BY r.reported_at DESC LIMIT 10");
$recent_requests = $stmt->fetchAll();

// Get users count by role
$stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$role_counts = $stmt->fetchAll();

// Get assets by status
$stmt = $db->query("SELECT status, COUNT(*) as count FROM assets GROUP BY status");
$asset_status = $stmt->fetchAll();

// Get requests by status
$stmt = $db->query("SELECT status, COUNT(*) as count FROM maintenance_requests GROUP BY status");
$request_status = $stmt->fetchAll();

// Get total technicians
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'ICT Technician'");
$tech_count = $stmt->fetch()['count'] ?? 0;

// Get total staff
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'Staff'");
$staff_count = $stmt->fetch()['count'] ?? 0;

// Get total admins
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'System Administrator'");
$admin_count = $stmt->fetch()['count'] ?? 0;

// Get maintenance trends (last 6 months)
$stmt = $db->query("SELECT 
                    DATE_FORMAT(reported_at, '%M') as month,
                    COUNT(*) as count,
                    SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved
                    FROM maintenance_requests 
                    WHERE reported_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY MONTH(reported_at)
                    ORDER BY reported_at ASC");
$trend_data = $stmt->fetchAll();

// Get top faulty assets
$stmt = $db->query("SELECT a.name, COUNT(r.request_id) as fault_count 
                    FROM assets a 
                    LEFT JOIN maintenance_requests r ON a.asset_id = r.asset_id 
                    GROUP BY a.asset_id 
                    ORDER BY fault_count DESC 
                    LIMIT 5");
$top_faulty_assets = $stmt->fetchAll();

// Get average resolution time
$stmt = $db->query("SELECT AVG(TIMESTAMPDIFF(HOUR, reported_at, resolved_at)) as avg_hours 
                    FROM maintenance_requests 
                    WHERE status = 'Resolved' AND resolved_at IS NOT NULL");
$avg_time = $stmt->fetch();
$avg_resolution_hours = round($avg_time['avg_hours'] ?? 0, 1);

// Include header (this includes sidebar)
include __DIR__ . '/../includes/header.php';
?>

<!-- ====== DASHBOARD CONTENT ====== -->
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-tachometer-alt text-primary"></i> Dashboard</h4>
            <small class="text-muted">Welcome back, <?php echo getUserName(); ?>!</small>
        </div>
        <div class="d-flex gap-2">
            <a href="reports.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-chart-bar"></i> View Reports
            </a>
            <button class="btn btn-primary btn-sm" onclick="window.location.reload();">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ====== STATS CARDS ====== -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card stat-card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Total Users</h6>
                            <h2 class="text-white mb-0"><?php echo number_format($counts['users'] ?? 0); ?></h2>
                            <small class="text-white-50">
                                <i class="fas fa-user-cog"></i> <?php echo $admin_count; ?> Admins
                                <span class="mx-1">|</span>
                                <i class="fas fa-user-tie"></i> <?php echo $tech_count; ?> Technicians
                                <span class="mx-1">|</span>
                                <i class="fas fa-user"></i> <?php echo $staff_count; ?> Staff
                            </small>
                        </div>
                        <div class="icon-circle bg-white bg-opacity-25">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card stat-card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Total Assets</h6>
                            <h2 class="text-white mb-0"><?php echo number_format($counts['assets'] ?? 0); ?></h2>
                            <small class="text-white-50">
                                <i class="fas fa-check-circle"></i> <?php 
                                $available = 0;
                                foreach ($asset_status as $as) {
                                    if ($as['status'] === 'Available') $available = $as['count'];
                                }
                                echo $available; ?> Available
                            </small>
                        </div>
                        <div class="icon-circle bg-white bg-opacity-25">
                            <i class="fas fa-laptop fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card stat-card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Active Requests</h6>
                            <h2 class="text-white mb-0"><?php echo number_format($counts['active_requests'] ?? 0); ?></h2>
                            <small class="text-white-50">
                                <i class="fas fa-clock"></i> Pending resolution
                            </small>
                        </div>
                        <div class="icon-circle bg-white bg-opacity-25">
                            <i class="fas fa-tools fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card stat-card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 mb-1">Resolved (30 days)</h6>
                            <h2 class="text-white mb-0"><?php echo number_format($counts['resolved_30days'] ?? 0); ?></h2>
                            <small class="text-white-50">
                                <i class="fas fa-check-double"></i> 
                                Avg: <?php echo $avg_resolution_hours; ?> hrs
                            </small>
                        </div>
                        <div class="icon-circle bg-white bg-opacity-25">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== CHARTS ROW ====== -->
    <div class="row g-4 mb-4">
        <!-- Users by Role -->
        <div class="col-xl-4 col-lg-6 col-md-12">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-users text-primary"></i> Users by Role</span>
                    <span class="badge bg-primary"><?php echo $counts['users'] ?? 0; ?> Total</span>
                </div>
                <div class="card-body">
                    <?php if (count($role_counts) > 0): ?>
                        <?php foreach ($role_counts as $rc): 
                            $percentage = ($counts['users'] > 0) ? round($rc['count'] / $counts['users'] * 100, 1) : 0;
                            $colors = [
                                'System Administrator' => 'danger',
                                'ICT Technician' => 'warning',
                                'Staff' => 'info'
                            ];
                            $color = $colors[$rc['role']] ?? 'secondary';
                        ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo $rc['role']; ?></span>
                                    <span><strong><?php echo $rc['count']; ?></strong> (<?php echo $percentage; ?>%)</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $percentage; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No users found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Assets by Status -->
        <div class="col-xl-4 col-lg-6 col-md-12">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-laptop text-success"></i> Assets by Status</span>
                    <span class="badge bg-success"><?php echo $counts['assets'] ?? 0; ?> Total</span>
                </div>
                <div class="card-body">
                    <?php if (count($asset_status) > 0): ?>
                        <?php 
                        $status_colors = [
                            'Available' => 'success',
                            'In Use' => 'primary',
                            'Under Maintenance' => 'warning',
                            'Retired' => 'danger'
                        ];
                        foreach ($asset_status as $as): 
                            $percentage = ($counts['assets'] > 0) ? round($as['count'] / $counts['assets'] * 100, 1) : 0;
                            $color = $status_colors[$as['status']] ?? 'secondary';
                        ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo $as['status']; ?></span>
                                    <span><strong><?php echo $as['count']; ?></strong> (<?php echo $percentage; ?>%)</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $percentage; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No assets found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Requests by Status -->
        <div class="col-xl-4 col-lg-6 col-md-12">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-clipboard-list text-warning"></i> Requests by Status</span>
                    <span class="badge bg-warning"><?php echo array_sum(array_column($request_status, 'count')); ?> Total</span>
                </div>
                <div class="card-body">
                    <?php if (count($request_status) > 0): 
                        $total_requests = array_sum(array_column($request_status, 'count'));
                        $status_colors = [
                            'Pending' => 'warning',
                            'Assigned' => 'info',
                            'In Progress' => 'primary',
                            'Resolved' => 'success',
                            'Closed' => 'secondary'
                        ];
                        foreach ($request_status as $rs):
                            $percentage = ($total_requests > 0) ? round($rs['count'] / $total_requests * 100, 1) : 0;
                            $color = $status_colors[$rs['status']] ?? 'secondary';
                    ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo $rs['status']; ?></span>
                                    <span><strong><?php echo $rs['count']; ?></strong> (<?php echo $percentage; ?>%)</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $percentage; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No requests found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== SECOND ROW - Trends & Top Faulty ====== -->
    <div class="row g-4 mb-4">
        <!-- Monthly Trends -->
        <div class="col-xl-8 col-lg-12">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-chart-line text-primary"></i> Maintenance Trends (Last 6 Months)
                </div>
                <div class="card-body">
                    <?php if (count($trend_data) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Total Requests</th>
                                        <th>Resolved</th>
                                        <th>Resolution Rate</th>
                                        <th>Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trend_data as $td): 
                                        $rate = ($td['count'] > 0) ? round($td['resolved'] / $td['count'] * 100, 1) : 0;
                                        $trend_icon = ($rate >= 80) ? 'fa-arrow-up text-success' : (($rate >= 50) ? 'fa-arrow-right text-warning' : 'fa-arrow-down text-danger');
                                    ?>
                                        <tr>
                                            <td><strong><?php echo $td['month']; ?></strong></td>
                                            <td><?php echo $td['count']; ?></td>
                                            <td><?php echo $td['resolved']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo ($rate >= 80) ? 'success' : (($rate >= 50) ? 'warning' : 'danger'); ?>">
                                                    <?php echo $rate; ?>%
                                                </span>
                                            </td>
                                            <td><i class="fas <?php echo $trend_icon; ?>"></i></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No data available for trends</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Faulty Assets -->
        <div class="col-xl-4 col-lg-12">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-exclamation-triangle text-danger"></i> Top Faulty Assets
                </div>
                <div class="card-body">
                    <?php if (count($top_faulty_assets) > 0): ?>
                        <?php foreach ($top_faulty_assets as $index => $asset): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <span class="badge bg-<?php echo $index === 0 ? 'danger' : ($index === 1 ? 'warning' : 'secondary'); ?>" 
                                          style="font-size: 1rem; padding: 8px 12px;">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <span><strong><?php echo htmlspecialchars($asset['name']); ?></strong></span>
                                        <span class="badge bg-danger"><?php echo $asset['fault_count']; ?> faults</span>
                                    </div>
                                    <div class="progress" style="height: 4px;">
                                        <?php 
                                        $max_faults = $top_faulty_assets[0]['fault_count'] ?? 1;
                                        $width = ($asset['fault_count'] / $max_faults) * 100;
                                        ?>
                                        <div class="progress-bar bg-<?php echo $index === 0 ? 'danger' : ($index === 1 ? 'warning' : 'secondary'); ?>" 
                                             style="width: <?php echo $width; ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No fault data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== RECENT REQUESTS ====== -->
    <div class="card">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list"></i> Recent Maintenance Requests</span>
            <a href="reports.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Asset</th>
                            <th>Reported By</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Reported</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_requests) > 0): ?>
                            <?php foreach ($recent_requests as $req): ?>
                                <tr>
                                    <td><span class="badge bg-secondary">#<?php echo $req['request_id']; ?></span></td>
                                    <td><?php echo htmlspecialchars($req['asset_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($req['reported_by_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo getPriorityBadge($req['priority']); ?></td>
                                    <td><?php echo getStatusBadge($req['status']); ?></td>
                                    <td>
                                        <small><?php echo timeAgo($req['reported_at']); ?></small>
                                    </td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-outline-primary btn-action" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="#" class="btn btn-sm btn-outline-success btn-action" title="Assign Technician">
                                            <i class="fas fa-user-plus"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                    No maintenance requests found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ====== QUICK ACTIONS ====== -->
    <div class="row g-3 mt-3">
        <div class="col-md-3 col-6">
            <a href="users.php" class="text-decoration-none">
                <div class="card text-center p-3 hover-card">
                    <i class="fas fa-user-plus fa-2x text-primary"></i>
                    <small class="mt-2">Add User</small>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="assets.php" class="text-decoration-none">
                <div class="card text-center p-3 hover-card">
                    <i class="fas fa-laptop fa-2x text-success"></i>
                    <small class="mt-2">Add Asset</small>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="reports.php" class="text-decoration-none">
                <div class="card text-center p-3 hover-card">
                    <i class="fas fa-chart-bar fa-2x text-warning"></i>
                    <small class="mt-2">View Reports</small>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="../settings/" class="text-decoration-none">
                <div class="card text-center p-3 hover-card">
                    <i class="fas fa-cog fa-2x text-info"></i>
                    <small class="mt-2">Settings</small>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- ====== STYLES ====== -->
<style>
    /* Stat Cards */
    .stat-card {
        border-radius: 16px;
        border: none;
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    .stat-card .icon-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .stat-card .icon-circle i {
        font-size: 1.8rem;
    }
    
    /* Hover Cards */
    .hover-card {
        transition: all 0.3s ease;
        border-radius: 12px;
        border: 1px solid #e9ecef;
    }
    .hover-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        border-color: #1a73e8;
    }
    
    /* Action Buttons */
    .btn-action {
        padding: 4px 8px;
        font-size: 12px;
        margin: 2px;
        border-radius: 6px;
    }
    .btn-action i {
        font-size: 14px;
    }
    
    /* Progress Bar */
    .progress {
        border-radius: 10px;
        background-color: #e9ecef;
    }
    .progress-bar {
        border-radius: 10px;
        transition: width 1s ease;
    }
    
    /* Cards */
    .card {
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        transition: all 0.3s ease;
    }
    .card:hover {
        box-shadow: 0 5px 20px rgba(0,0,0,0.06);
    }
    .card-header {
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 15px 20px;
    }
    
    /* Table */
    .table th {
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
        border-bottom: 2px solid #e9ecef;
    }
    .table td {
        vertical-align: middle;
        padding: 12px 15px;
    }
    
    /* Badges */
    .badge {
        padding: 5px 12px;
        font-weight: 500;
        border-radius: 20px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .stat-card .icon-circle {
            width: 45px;
            height: 45px;
        }
        .stat-card .icon-circle i {
            font-size: 1.3rem;
        }
        .stat-card h2 {
            font-size: 1.5rem;
        }
        .stat-card h6 {
            font-size: 0.75rem;
        }
        .stat-card small {
            font-size: 0.65rem;
        }
    }
    
    @media (max-width: 576px) {
        .card-body {
            padding: 15px;
        }
        .table-responsive {
            font-size: 0.8rem;
        }
        .btn-action {
            padding: 2px 5px;
            font-size: 10px;
        }
        .btn-action i {
            font-size: 11px;
        }
        .hover-card {
            padding: 10px !important;
        }
        .hover-card i {
            font-size: 1.5rem !important;
        }
        .hover-card small {
            font-size: 0.7rem;
        }
    }
</style>

<!-- ====== SCRIPTS ====== -->
<script>
// ====== AUTO-REFRESH STATS (Optional) ======
// Uncomment to refresh stats every 60 seconds
/*
setInterval(function() {
    location.reload();
}, 60000);
*/

// ====== TOOLTIP INITIALIZATION ======
document.addEventListener('DOMContentLoaded', function() {
    // Add any tooltip initialization here
});

// ====== CONFIRM DELETE ======
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item? This action cannot be undone.');
}

// ====== PRINT REPORT ======
function printReport() {
    window.print();
}

// ====== EXPORT TO CSV ======
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let row of rows) {
        const cells = row.querySelectorAll('th, td');
        const rowData = [];
        for (let cell of cells) {
            rowData.push(cell.innerText.trim());
        }
        csv.push(rowData.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'report.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<!-- ====== FOOTER ====== -->
<?php include __DIR__ . '/../includes/footer.php'; ?>