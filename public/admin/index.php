<?php
// public/admin/index.php - Admin Dashboard

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

// Check if user is logged in and is admin
requireRole('System Administrator');

$user_id = $_SESSION['user_id'];
$db = getDB();

// Get user data
$user = getUser($user_id);

// Get dashboard counts
$counts = getDashboardCounts('System Administrator');

// Get recent notifications (using notification_helper)
$unread = getUnreadNotificationsCount($user_id);
$notifications = getRecentNotifications($user_id, 5);

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

<style>
    /* ====== STAT CARDS ====== */
    .stat-card {
        border-radius: 16px;
        border: none;
        padding: 20px;
        transition: all 0.3s ease;
        cursor: default;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    .stat-card .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .stat-card .stat-number {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .stat-card .stat-label {
        font-size: 0.85rem;
        opacity: 0.8;
    }
    
    /* ====== QUICK ACTIONS ====== */
    .quick-action-card {
        border-radius: 12px;
        border: 2px solid #e9ecef;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        color: #212529;
        background: white;
    }
    .quick-action-card:hover {
        border-color: #1a73e8;
        transform: translateY(-3px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        color: #1a73e8;
    }
    .quick-action-card i {
        font-size: 2rem;
        margin-bottom: 10px;
        display: block;
    }
    .quick-action-card .action-label {
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    /* ====== REQUEST ITEM ====== */
    .request-item {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .request-item:hover {
        background: #f8f9fa;
    }
    .request-item .request-content {
        flex: 1;
        min-width: 0;
    }
    .request-item .request-content .request-title {
        font-weight: 600;
        font-size: 0.9rem;
        color: #212529;
    }
    .request-item .request-content .request-meta {
        font-size: 0.75rem;
        color: #6c757d;
    }
    .request-item .request-status {
        flex-shrink: 0;
    }
    
    /* ====== EMPTY STATE ====== */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
    }
    .empty-state i {
        font-size: 3rem;
        color: #dee2e6;
        margin-bottom: 15px;
    }
    .empty-state h6 {
        color: #495057;
    }
    .empty-state p {
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    /* ====== RESPONSIVE ====== */
    @media (max-width: 768px) {
        .stat-card .stat-number {
            font-size: 1.5rem;
        }
        .stat-card .stat-icon {
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
        }
        .quick-action-card {
            padding: 15px;
        }
        .quick-action-card i {
            font-size: 1.5rem;
        }
        .request-item {
            flex-wrap: wrap;
            padding: 10px 12px;
        }
        .request-item .request-status {
            width: 100%;
            text-align: right;
        }
    }
    
    @media (max-width: 576px) {
        .stat-card .stat-number {
            font-size: 1.2rem;
        }
        .stat-card {
            padding: 12px;
        }
        .quick-action-card {
            padding: 10px;
        }
        .quick-action-card i {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }
        .quick-action-card .action-label {
            font-size: 0.75rem;
        }
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-tachometer-alt text-primary"></i> Admin Dashboard</h4>
            <small class="text-muted">Welcome back, <?php echo getUserName(); ?>!</small>
        </div>
        <div>
            <a href="reports.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-chart-bar"></i> View Reports
            </a>
            <button class="btn btn-outline-secondary btn-sm ms-1" onclick="location.reload();">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ====== STATS CARDS ====== -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card stat-card bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo number_format($counts['users'] ?? 0); ?></div>
                        <div class="stat-label">Total Users</div>
                        <small class="text-white-50">
                            <i class="fas fa-user-shield"></i> <?php echo $admin_count; ?> Admins
                            <span class="mx-1">|</span>
                            <i class="fas fa-user-cog"></i> <?php echo $tech_count; ?> Techs
                            <span class="mx-1">|</span>
                            <i class="fas fa-user"></i> <?php echo $staff_count; ?> Staff
                        </small>
                    </div>
                    <div class="stat-icon bg-white bg-opacity-25">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card stat-card bg-success text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo number_format($counts['assets'] ?? 0); ?></div>
                        <div class="stat-label">Total Assets</div>
                        <small class="text-white-50">
                            <?php 
                            $available = 0;
                            foreach ($asset_status as $as) {
                                if ($as['status'] === 'Available') $available = $as['count'];
                            }
                            echo '<i class="fas fa-check-circle"></i> ' . $available . ' Available';
                            ?>
                        </small>
                    </div>
                    <div class="stat-icon bg-white bg-opacity-25">
                        <i class="fas fa-laptop"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card stat-card bg-warning text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo number_format($counts['active_requests'] ?? 0); ?></div>
                        <div class="stat-label">Active Requests</div>
                        <small class="text-white-50">
                            <i class="fas fa-clock"></i> Pending resolution
                        </small>
                    </div>
                    <div class="stat-icon bg-white bg-opacity-25">
                        <i class="fas fa-tools"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card stat-card bg-info text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo number_format($counts['resolved_30days'] ?? 0); ?></div>
                        <div class="stat-label">Resolved (30 days)</div>
                        <small class="text-white-50">
                            <i class="fas fa-check-double"></i> 
                            Avg: <?php echo $avg_resolution_hours; ?> hrs
                        </small>
                    </div>
                    <div class="stat-icon bg-white bg-opacity-25">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== CHARTS ROW ====== -->
    <div class="row g-3 mb-4">
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
    <div class="row g-3 mb-4">
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
            <?php if (count($recent_requests) > 0): ?>
                <?php foreach ($recent_requests as $req): ?>
                    <div class="request-item">
                        <div class="request-content">
                            <div class="request-title">
                                #<?php echo $req['request_id']; ?> - <?php echo htmlspecialchars($req['asset_name'] ?? 'N/A'); ?>
                                <span class="ms-2"><?php echo getPriorityBadge($req['priority']); ?></span>
                                <?php echo getStatusBadge($req['status']); ?>
                            </div>
                            <div class="request-meta">
                                <span>Reported by: <?php echo htmlspecialchars($req['reported_by_name'] ?? 'N/A'); ?></span>
                                <span class="mx-2">•</span>
                                <span><?php echo timeAgo($req['reported_at']); ?></span>
                            </div>
                        </div>
                        <div class="request-status">
                            <a href="#" class="btn btn-sm btn-outline-primary btn-action" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-success btn-action" title="Assign Technician">
                                <i class="fas fa-user-plus"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h6>No maintenance requests</h6>
                    <p>No maintenance requests have been submitted yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ====== QUICK ACTIONS ====== -->
    <div class="row g-3 mt-3">
        <div class="col-md-3 col-6">
            <a href="users.php" class="quick-action-card d-block">
                <i class="fas fa-user-plus text-primary"></i>
                <span class="action-label">Add User</span>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="assets.php" class="quick-action-card d-block">
                <i class="fas fa-laptop text-success"></i>
                <span class="action-label">Add Asset</span>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="reports.php" class="quick-action-card d-block">
                <i class="fas fa-chart-bar text-warning"></i>
                <span class="action-label">View Reports</span>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="../settings/" class="quick-action-card d-block">
                <i class="fas fa-cog text-info"></i>
                <span class="action-label">Settings</span>
            </a>
        </div>
    </div>
</div>

<script>
console.log('✅ Admin Dashboard Loaded!');
console.log('👥 Total Users:', '<?php echo $counts['users'] ?? 0; ?>');
console.log('📊 Total Assets:', '<?php echo $counts['assets'] ?? 0; ?>');
console.log('🔧 Active Requests:', '<?php echo $counts['active_requests'] ?? 0; ?>');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>