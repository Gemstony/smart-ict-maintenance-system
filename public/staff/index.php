<?php
// public/staff/index.php - Staff Dashboard

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

requireRole('Staff');

$user_id = $_SESSION['user_id'];
$db = getDB();

// Get counts for staff
$counts = getDashboardCounts('Staff', $user_id);

// Get my requests
$stmt = $db->prepare("SELECT r.*, a.name as asset_name, 
                      CONCAT(t.first_name, ' ', t.last_name) as technician_name 
                      FROM maintenance_requests r 
                      LEFT JOIN assets a ON r.asset_id = a.asset_id 
                      LEFT JOIN users t ON r.assigned_to = t.user_id 
                      WHERE r.reported_by = ? 
                      ORDER BY r.reported_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$my_requests = $stmt->fetchAll();

// Get available assets for reporting
$stmt_assets = $db->query("SELECT * FROM assets WHERE status = 'Available' OR status = 'In Use' ORDER BY name");
$assets = $stmt_assets->fetchAll();

// Get total requests count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM maintenance_requests WHERE reported_by = ?");
$stmt->execute([$user_id]);
$total_requests = $stmt->fetch()['total'] ?? 0;

// Get resolved count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests 
                      WHERE reported_by = ? AND status = 'Resolved'");
$stmt->execute([$user_id]);
$resolved_count = $stmt->fetch()['count'] ?? 0;

// Get pending count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests 
                      WHERE reported_by = ? AND status IN ('Pending', 'Assigned', 'In Progress')");
$stmt->execute([$user_id]);
$pending_count = $stmt->fetch()['count'] ?? 0;

// Get requests by status for chart
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM maintenance_requests 
                      WHERE reported_by = ? GROUP BY status");
$stmt->execute([$user_id]);
$request_status = $stmt->fetchAll();

// Get recent notifications
$unread = getUnreadNotificationsCount($user_id);
$notifications = getRecentNotifications($user_id, 5);

// Include header
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
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
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
    
    /* ====== REQUEST ITEMS ====== */
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
    .request-item .request-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.2rem;
    }
    .request-item .request-icon.pending { background: #fff3cd; color: #856404; }
    .request-item .request-icon.resolved { background: #d4edda; color: #155724; }
    .request-item .request-icon.inprogress { background: #cce5ff; color: #004085; }
    
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
    
    /* ====== ASSET CARD ====== */
    .asset-mini-card {
        padding: 12px;
        border-radius: 10px;
        border: 1px solid #e9ecef;
        transition: all 0.3s;
        text-align: center;
    }
    .asset-mini-card:hover {
        border-color: #1a73e8;
        background: #f8f9fa;
    }
    .asset-mini-card .asset-name {
        font-weight: 600;
        font-size: 0.85rem;
    }
    .asset-mini-card .asset-status {
        font-size: 0.7rem;
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
        .asset-mini-card .asset-name {
            font-size: 0.75rem;
        }
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-tachometer-alt text-info"></i> Staff Dashboard</h4>
            <small class="text-muted">Welcome back, <?php echo getUserName(); ?>!</small>
        </div>
        <div>
            <button class="btn btn-outline-secondary btn-sm" onclick="location.reload();">
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
                        <div class="stat-number"><?php echo $total_requests; ?></div>
                        <div class="stat-label">Total Requests</div>
                    </div>
                    <div class="stat-icon bg-white bg-opacity-25">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card stat-card bg-warning text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo $pending_count; ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                    <div class="stat-icon bg-white bg-opacity-25">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card stat-card bg-success text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo $resolved_count; ?></div>
                        <div class="stat-label">Resolved</div>
                    </div>
                    <div class="stat-icon bg-white bg-opacity-25">
                        <i class="fas fa-check-double"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card stat-card bg-info text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php 
                            $stmt = $db->prepare("SELECT COUNT(DISTINCT asset_id) as count FROM maintenance_requests WHERE reported_by = ?");
                            $stmt->execute([$user_id]);
                            echo $stmt->fetch()['count'] ?? 0;
                        ?></div>
                        <div class="stat-label">Assets Reported</div>
                    </div>
                    <div class="stat-icon bg-white bg-opacity-25">
                        <i class="fas fa-laptop"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== REQUEST STATUS & QUICK ACTIONS ====== -->
    <div class="row g-3 mb-4">
        <!-- Request Status -->
        <div class="col-xl-6 col-lg-6 col-md-12">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-chart-pie text-info"></i> Request Status
                </div>
                <div class="card-body">
                    <?php if (count($request_status) > 0): ?>
                        <?php foreach ($request_status as $rs): 
                            $total = array_sum(array_column($request_status, 'count'));
                            $width = ($total > 0) ? round($rs['count'] / $total * 100, 1) : 0;
                            $colors = [
                                'Pending' => 'warning',
                                'Assigned' => 'info',
                                'In Progress' => 'primary',
                                'Resolved' => 'success',
                                'Closed' => 'secondary'
                            ];
                            $color = $colors[$rs['status']] ?? 'secondary';
                        ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo $rs['status']; ?></span>
                                    <span><strong><?php echo $rs['count']; ?></strong> (<?php echo $width; ?>%)</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $width; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No requests submitted yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="col-xl-6 col-lg-6 col-md-12">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-bolt text-info"></i> Quick Actions
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="report_fault.php" class="quick-action-card d-block">
                                <i class="fas fa-exclamation-triangle text-danger"></i>
                                <span class="action-label">Report Fault</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="my_requests.php" class="quick-action-card d-block">
                                <i class="fas fa-list text-primary"></i>
                                <span class="action-label">My Requests</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="view_assets.php" class="quick-action-card d-block">
                                <i class="fas fa-laptop text-success"></i>
                                <span class="action-label">View Assets</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="../settings/" class="quick-action-card d-block">
                                <i class="fas fa-cog text-info"></i>
                                <span class="action-label">Settings</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== MY REQUESTS ====== -->
    <div class="card mb-4">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-clipboard-list text-info"></i> My Recent Requests</span>
            <a href="my_requests.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="card-body p-0">
            <?php if (count($my_requests) > 0): ?>
                <?php foreach ($my_requests as $req): 
                    $status_class = strtolower($req['status'] ?? 'pending');
                    $icon = $status_class === 'resolved' ? 'fa-check-circle' : ($status_class === 'pending' ? 'fa-clock' : 'fa-spinner');
                ?>
                    <div class="request-item">
                        <div class="request-icon <?php echo $status_class; ?>">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="request-content">
                            <div class="request-title">#<?php echo $req['request_id']; ?> - <?php echo htmlspecialchars($req['asset_name'] ?? 'N/A'); ?></div>
                            <div class="request-meta">
                                <span>Technician: <?php echo htmlspecialchars($req['technician_name'] ?? 'Not Assigned'); ?></span>
                                <span class="mx-2">•</span>
                                <span><?php echo timeAgo($req['reported_at']); ?></span>
                            </div>
                        </div>
                        <div class="request-status">
                            <?php echo getPriorityBadge($req['priority']); ?>
                            <?php echo getStatusBadge($req['status']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h6>No requests submitted</h6>
                    <p>You haven't reported any faults yet. Click "Report Fault" to get started.</p>
                    <a href="report_fault.php" class="btn btn-primary btn-sm mt-2">
                        <i class="fas fa-exclamation-triangle"></i> Report Fault
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ====== AVAILABLE ASSETS ====== -->
    <?php if (count($assets) > 0): ?>
    <div class="card">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-laptop text-success"></i> Available Assets</span>
            <a href="view_assets.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <?php 
                $display_assets = array_slice($assets, 0, 6);
                foreach ($display_assets as $asset): 
                ?>
                    <div class="col-xl-2 col-lg-3 col-md-4 col-6">
                        <div class="asset-mini-card">
                            <i class="fas fa-<?php echo $asset['category'] === 'Laptop' ? 'laptop' : ($asset['category'] === 'Printer' ? 'print' : ($asset['category'] === 'Network' ? 'network-wired' : 'desktop')); ?> fa-2x text-primary d-block mb-1"></i>
                            <div class="asset-name"><?php echo htmlspecialchars($asset['name']); ?></div>
                            <div class="asset-status">
                                <span class="badge bg-<?php echo $asset['status'] === 'Available' ? 'success' : 'info'; ?>">
                                    <?php echo $asset['status']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
console.log('✅ Staff Dashboard Loaded!');
console.log('📊 Total Requests:', '<?php echo $total_requests; ?>');
console.log('⏳ Pending:', '<?php echo $pending_count; ?>');
console.log('✅ Resolved:', '<?php echo $resolved_count; ?>');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>