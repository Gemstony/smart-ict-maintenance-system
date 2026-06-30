<?php
// public/technician/index.php - Technician Dashboard

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

requireRole('ICT Technician');

$user_id = $_SESSION['user_id'];
$db = getDB();

// Get counts for technician
$counts = getDashboardCounts('ICT Technician', $user_id);

// Get my assigned tasks
$stmt = $db->prepare("SELECT r.*, a.name as asset_name, 
                      CONCAT(u.first_name, ' ', u.last_name) as reported_by_name 
                      FROM maintenance_requests r 
                      LEFT JOIN assets a ON r.asset_id = a.asset_id 
                      LEFT JOIN users u ON r.reported_by = u.user_id 
                      WHERE r.assigned_to = ? 
                      ORDER BY r.reported_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$my_tasks = $stmt->fetchAll();

// Get tasks by status for charts
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM maintenance_requests 
                      WHERE assigned_to = ? GROUP BY status");
$stmt->execute([$user_id]);
$task_status = $stmt->fetchAll();

// Get total tasks count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM maintenance_requests WHERE assigned_to = ?");
$stmt->execute([$user_id]);
$total_tasks = $stmt->fetch()['total'] ?? 0;

// Get resolved this month
$stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests 
                      WHERE assigned_to = ? AND status = 'Resolved' 
                      AND resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute([$user_id]);
$resolved_month = $stmt->fetch()['count'] ?? 0;

// Get pending count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests 
                      WHERE assigned_to = ? AND status IN ('Pending', 'Assigned', 'In Progress')");
$stmt->execute([$user_id]);
$pending_count = $stmt->fetch()['count'] ?? 0;

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
    
    /* ====== TASK ITEMS ====== */
    .task-item {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .task-item:hover {
        background: #f8f9fa;
    }
    .task-item .task-priority {
        width: 6px;
        height: 40px;
        border-radius: 3px;
        flex-shrink: 0;
    }
    .task-item .task-priority.critical { background: #dc3545; }
    .task-item .task-priority.high { background: #ffc107; }
    .task-item .task-priority.medium { background: #17a2b8; }
    .task-item .task-priority.low { background: #28a745; }
    
    .task-item .task-content {
        flex: 1;
        min-width: 0;
    }
    .task-item .task-content .task-title {
        font-weight: 600;
        font-size: 0.9rem;
        color: #212529;
    }
    .task-item .task-content .task-meta {
        font-size: 0.75rem;
        color: #6c757d;
    }
    .task-item .task-actions {
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
        .task-item {
            flex-wrap: wrap;
            padding: 10px 12px;
        }
        .task-item .task-actions {
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
            <h4 class="fw-bold mb-0"><i class="fas fa-tachometer-alt text-warning"></i> Technician Dashboard</h4>
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
                        <div class="stat-number"><?php echo $total_tasks; ?></div>
                        <div class="stat-label">Total Tasks</div>
                    </div>
                    <div class="stat-icon bg-white bg-opacity-25">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card stat-card bg-warning text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo $pending_count; ?></div>
                        <div class="stat-label">Pending Tasks</div>
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
                        <div class="stat-number"><?php echo $resolved_month; ?></div>
                        <div class="stat-label">Resolved (30 days)</div>
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
                            $stmt = $db->prepare("SELECT COUNT(DISTINCT asset_id) as count FROM maintenance_requests WHERE assigned_to = ?");
                            $stmt->execute([$user_id]);
                            echo $stmt->fetch()['count'] ?? 0;
                        ?></div>
                        <div class="stat-label">Assets Serviced</div>
                    </div>
                    <div class="stat-icon bg-white bg-opacity-25">
                        <i class="fas fa-laptop"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== TASK STATUS CHART & QUICK ACTIONS ====== -->
    <div class="row g-3 mb-4">
        <!-- Task Status -->
        <div class="col-xl-6 col-lg-6 col-md-12">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-chart-pie text-warning"></i> Task Status
                </div>
                <div class="card-body">
                    <?php if (count($task_status) > 0): ?>
                        <?php foreach ($task_status as $ts): 
                            $total = array_sum(array_column($task_status, 'count'));
                            $width = ($total > 0) ? round($ts['count'] / $total * 100, 1) : 0;
                            $colors = [
                                'Pending' => 'warning',
                                'Assigned' => 'info',
                                'In Progress' => 'primary',
                                'Resolved' => 'success',
                                'Closed' => 'secondary'
                            ];
                            $color = $colors[$ts['status']] ?? 'secondary';
                        ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo $ts['status']; ?></span>
                                    <span><strong><?php echo $ts['count']; ?></strong> (<?php echo $width; ?>%)</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $width; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No tasks assigned yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="col-xl-6 col-lg-6 col-md-12">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-bolt text-warning"></i> Quick Actions
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="my_tasks.php" class="quick-action-card d-block">
                                <i class="fas fa-list text-primary"></i>
                                <span class="action-label">My Tasks</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="update_task.php" class="quick-action-card d-block">
                                <i class="fas fa-edit text-warning"></i>
                                <span class="action-label">Update Task</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="reports.php" class="quick-action-card d-block">
                                <i class="fas fa-chart-bar text-success"></i>
                                <span class="action-label">Reports</span>
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

    <!-- ====== MY TASKS ====== -->
    <div class="card">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-clipboard-list text-warning"></i> My Assigned Tasks</span>
            <a href="my_tasks.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="card-body p-0">
            <?php if (count($my_tasks) > 0): ?>
                <?php foreach ($my_tasks as $task): 
                    $priority_class = strtolower($task['priority'] ?? 'medium');
                ?>
                    <div class="task-item">
                        <div class="task-priority <?php echo $priority_class; ?>"></div>
                        <div class="task-content">
                            <div class="task-title">#<?php echo $task['request_id']; ?> - <?php echo htmlspecialchars($task['asset_name'] ?? 'N/A'); ?></div>
                            <div class="task-meta">
                                <span>Reported by: <?php echo htmlspecialchars($task['reported_by_name'] ?? 'N/A'); ?></span>
                                <span class="mx-2">•</span>
                                <span><?php echo timeAgo($task['reported_at']); ?></span>
                                <span class="mx-2">•</span>
                                <?php echo getPriorityBadge($task['priority']); ?>
                                <?php echo getStatusBadge($task['status']); ?>
                            </div>
                        </div>
                        <div class="task-actions">
                            <a href="update_task.php?id=<?php echo $task['request_id']; ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i> Update
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h6>No tasks assigned</h6>
                    <p>You don't have any maintenance tasks assigned yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
console.log('✅ Technician Dashboard Loaded!');
console.log('📊 Total Tasks:', '<?php echo $total_tasks; ?>');
console.log('⏳ Pending:', '<?php echo $pending_count; ?>');
console.log('✅ Resolved (30 days):', '<?php echo $resolved_month; ?>');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>