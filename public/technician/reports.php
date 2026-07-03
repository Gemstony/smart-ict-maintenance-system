<?php
// public/technician/reports.php - Technician Performance Reports

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/settings_helper.php';

requireRole('ICT Technician');

$db = getDB();
$user_id = $_SESSION['user_id'];

// ====== GET DATE FILTERS ======
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Build date condition
$date_condition = "";
if ($period === 'today') {
    $date_condition = "AND DATE(r.reported_at) = CURDATE()";
} elseif ($period === 'week') {
    $date_condition = "AND DATE(r.reported_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($period === 'month') {
    $date_condition = "AND DATE(r.reported_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
} elseif ($period === 'custom') {
    $date_condition = "AND DATE(r.reported_at) BETWEEN '" . $db->quote($date_from) . "' AND '" . $db->quote($date_to) . "'";
}

// ====== OVERALL STATS ======
// Total tasks assigned
$stmt = $db->query("SELECT COUNT(*) as total FROM maintenance_requests WHERE assigned_to = $user_id");
$total_tasks = $stmt->fetch()['total'];

// Completed tasks (Resolved + Closed)
$stmt = $db->query("SELECT COUNT(*) as completed FROM maintenance_requests WHERE assigned_to = $user_id AND status IN ('Resolved', 'Closed')");
$completed_tasks = $stmt->fetch()['completed'];

// Pending tasks (Pending + Assigned + In Progress)
$stmt = $db->query("SELECT COUNT(*) as pending FROM maintenance_requests WHERE assigned_to = $user_id AND status IN ('Pending', 'Assigned', 'In Progress')");
$pending_tasks = $stmt->fetch()['pending'];

// Resolution rate
$resolution_rate = ($total_tasks > 0) ? round(($completed_tasks / $total_tasks) * 100, 1) : 0;

// Average resolution time (in hours)
$stmt = $db->query("SELECT AVG(TIMESTAMPDIFF(HOUR, assigned_at, resolved_at)) as avg_time 
                     FROM maintenance_requests 
                     WHERE assigned_to = $user_id AND assigned_at IS NOT NULL AND resolved_at IS NOT NULL");
$avg_time = $stmt->fetch()['avg_time'];
$avg_time_display = $avg_time ? round($avg_time, 1) . ' hrs' : 'N/A';

// ====== TASKS BY STATUS ======
$stmt = $db->query("SELECT status, COUNT(*) as count 
                     FROM maintenance_requests 
                     WHERE assigned_to = $user_id 
                     GROUP BY status");
$status_data = $stmt->fetchAll();
$status_labels = [];
$status_counts = [];
foreach ($status_data as $row) {
    $status_labels[] = $row['status'];
    $status_counts[] = $row['count'];
}

// ====== TASKS BY PRIORITY ======
$stmt = $db->query("SELECT priority, COUNT(*) as count 
                     FROM maintenance_requests 
                     WHERE assigned_to = $user_id 
                     GROUP BY priority");
$priority_data = $stmt->fetchAll();
$priority_labels = ['Low', 'Medium', 'High', 'Critical'];
$priority_counts = [0, 0, 0, 0];
foreach ($priority_data as $row) {
    $index = array_search($row['priority'], $priority_labels);
    if ($index !== false) {
        $priority_counts[$index] = $row['count'];
    }
}

// ====== TASKS BY ASSET CATEGORY ======
$stmt = $db->query("SELECT a.category, COUNT(*) as count 
                     FROM maintenance_requests r
                     LEFT JOIN assets a ON r.asset_id = a.asset_id
                     WHERE r.assigned_to = $user_id AND a.category IS NOT NULL
                     GROUP BY a.category
                     ORDER BY count DESC
                     LIMIT 10");
$category_data = $stmt->fetchAll();
$category_labels = [];
$category_counts = [];
foreach ($category_data as $row) {
    $category_labels[] = $row['category'];
    $category_counts[] = $row['count'];
}

// ====== RECENT ACTIVITY ======
$stmt = $db->prepare("SELECT r.*, a.name AS asset_name, 
                              CONCAT(reporter.first_name, ' ', reporter.last_name) AS reported_by_name
                       FROM maintenance_requests r
                       LEFT JOIN assets a ON r.asset_id = a.asset_id
                       LEFT JOIN users reporter ON r.reported_by = reporter.user_id
                       WHERE r.assigned_to = ?
                       
                       LIMIT 20");
$stmt->execute([$user_id]);
$recent_activity = $stmt->fetchAll();

// ====== MONTHLY TREND (last 6 months) ======
$stmt = $db->query("SELECT DATE_FORMAT(reported_at, '%Y-%m') as month, 
                            COUNT(*) as total,
                            SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as completed
                     FROM maintenance_requests 
                     WHERE assigned_to = $user_id 
                       AND reported_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                     GROUP BY DATE_FORMAT(reported_at, '%Y-%m')
                     ORDER BY month ASC");
$trend_data = $stmt->fetchAll();
$trend_months = [];
$trend_total = [];
$trend_completed = [];
foreach ($trend_data as $row) {
    $trend_months[] = date('M Y', strtotime($row['month'] . '-01'));
    $trend_total[] = $row['total'];
    $trend_completed[] = $row['completed'];
}

// ====== EFFICIENCY SCORE (based on resolution time vs average) ======
$stmt = $db->query("SELECT AVG(TIMESTAMPDIFF(HOUR, assigned_at, resolved_at)) as overall_avg 
                     FROM maintenance_requests 
                     WHERE assigned_at IS NOT NULL AND resolved_at IS NOT NULL");
$overall_avg = $stmt->fetch()['overall_avg'];
$tech_avg = $avg_time;

if ($tech_avg && $overall_avg && $overall_avg > 0) {
    $efficiency_score = round((1 - (($tech_avg - $overall_avg) / $overall_avg)) * 100, 1);
    $efficiency_score = max(0, min(100, $efficiency_score));
} else {
    $efficiency_score = 0;
}

// Include header
include __DIR__ . '/../includes/header.php';
?>

<style>
    /* ====== STAT CARDS ====== */
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid rgba(0,0,0,0.05);
        transition: all 0.3s;
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    .stat-card .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 10px;
    }
    .stat-card .stat-number {
        font-size: 2rem;
        font-weight: 700;
        margin: 0;
    }
    .stat-card .stat-label {
        color: #6c757d;
        font-size: 0.9rem;
        margin: 0;
    }
    .stat-card .stat-change {
        font-size: 0.8rem;
        font-weight: 600;
    }
    .stat-card .stat-change.positive { color: #28a745; }
    .stat-card .stat-change.negative { color: #dc3545; }

    .bg-primary-soft { background: #e8f0fe; color: #1a73e8; }
    .bg-success-soft { background: #d4edda; color: #28a745; }
    .bg-warning-soft { background: #fff3cd; color: #856404; }
    .bg-info-soft { background: #d1ecf1; color: #0c5460; }

    /* ====== CHART CONTAINERS ====== */
    .chart-container {
        position: relative;
        height: 280px;
        width: 100%;
        margin: 0 auto;
    }
    .chart-container-sm {
        height: 220px;
    }
    .chart-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    .chart-card .chart-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 15px;
        color: #333;
    }

    /* ====== FILTERS ====== */
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
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

    /* ====== RECENT ACTIVITY TABLE ====== */
    .activity-table {
        font-size: 0.9rem;
    }
    .activity-table .status-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .status-Pending { background: #fff3cd; color: #856404; }
    .status-Assigned { background: #cce5ff; color: #004085; }
    .status-In\ Progress { background: #d1ecf1; color: #0c5460; }
    .status-Resolved { background: #d4edda; color: #155724; }
    .status-Closed { background: #e2e3e5; color: #383d41; }

    /* ====== RESPONSIVE ====== */
    @media (max-width: 768px) {
        .stat-card .stat-number { font-size: 1.5rem; }
        .chart-container { height: 200px; }
        .filter-btn { padding: 3px 10px; font-size: 0.7rem; }
        .activity-table { font-size: 0.75rem; }
    }
</style>

<!-- ====== MAIN CONTENT ====== -->
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-chart-bar text-primary"></i> Performance Reports</h4>
            <small class="text-muted">Track your performance and productivity metrics</small>
        </div>
    </div>

    <!-- ====== STATS ROW ====== -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-primary-soft">
                    <i class="fas fa-tasks text-primary"></i>
                </div>
                <p class="stat-number"><?php echo $total_tasks; ?></p>
                <p class="stat-label">Total Tasks</p>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-success-soft">
                    <i class="fas fa-check-circle text-success"></i>
                </div>
                <p class="stat-number"><?php echo $completed_tasks; ?></p>
                <p class="stat-label">Completed</p>
                <small class="text-muted"><?php echo $resolution_rate; ?>% resolution rate</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-warning-soft">
                    <i class="fas fa-clock text-warning"></i>
                </div>
                <p class="stat-number"><?php echo $pending_tasks; ?></p>
                <p class="stat-label">Pending</p>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-info-soft">
                    <i class="fas fa-rocket text-info"></i>
                </div>
                <p class="stat-number"><?php echo $avg_time_display; ?></p>
                <p class="stat-label">Avg Resolution Time</p>
                <?php if ($efficiency_score > 0): ?>
                    <small class="stat-change <?php echo $efficiency_score >= 70 ? 'positive' : 'negative'; ?>">
                        <?php echo $efficiency_score; ?>% efficiency
                    </small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ====== FILTERS ====== -->
    <div class="chart-card">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="fw-bold small">Period:</span>
            <div class="filter-group">
                <a href="?period=all" class="filter-btn <?php echo $period === 'all' ? 'active' : ''; ?>">All Time</a>
                <a href="?period=today" class="filter-btn <?php echo $period === 'today' ? 'active' : ''; ?>">Today</a>
                <a href="?period=week" class="filter-btn <?php echo $period === 'week' ? 'active' : ''; ?>">This Week</a>
                <a href="?period=month" class="filter-btn <?php echo $period === 'month' ? 'active' : ''; ?>">This Month</a>
                <a href="?period=custom" class="filter-btn <?php echo $period === 'custom' ? 'active' : ''; ?>">Custom</a>
            </div>
            <?php if ($period === 'custom'): ?>
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="period" value="custom">
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="form-control form-control-sm" style="width:150px;">
                    <span>to</span>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="form-control form-control-sm" style="width:150px;">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- ====== CHARTS ROW ====== -->
    <div class="row g-3">
        <!-- Status Distribution -->
        <div class="col-md-4">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-circle text-primary me-1"></i> Status Distribution</div>
                <div class="chart-container chart-container-sm">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Priority Distribution -->
        <div class="col-md-4">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-flag me-1"></i> Priority Breakdown</div>
                <div class="chart-container chart-container-sm">
                    <canvas id="priorityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Asset Categories -->
        <div class="col-md-4">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-boxes me-1"></i> Asset Categories</div>
                <div class="chart-container chart-container-sm">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== TREND CHART ====== -->
    <div class="chart-card">
        <div class="chart-title"><i class="fas fa-chart-line text-primary me-1"></i> Monthly Trend (Last 6 Months)</div>
        <div class="chart-container" style="height:300px;">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- ====== RECENT ACTIVITY ====== -->
    <div class="chart-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="chart-title mb-0"><i class="fas fa-history me-1"></i> Recent Activity</div>
            <span class="badge bg-secondary">Last 20</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover activity-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Asset</th>
                        <th>Reported By</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Reported</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_activity) > 0): ?>
                        <?php foreach ($recent_activity as $activity): ?>
                            <tr>
                                <td>#<?php echo $activity['request_id']; ?></td>
                                <td><?php echo htmlspecialchars($activity['asset_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($activity['reported_by_name'] ?? 'Unknown'); ?></td>
                                <td><span class="priority-badge priority-<?php echo $activity['priority']; ?>"><?php echo $activity['priority']; ?></span></td>
                                <td><span class="status-badge status-<?php echo str_replace(' ', '-', $activity['status']); ?>"><?php echo $activity['status']; ?></span></td>
                                <td><?php echo date('d M Y', strtotime($activity['reported_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No activity found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ====== PERFORMANCE TIPS ====== -->
    <div class="row g-3">
        <div class="col-md-6">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-lightbulb text-warning me-1"></i> Performance Insights</div>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <strong>Resolution Rate:</strong> <?php echo $resolution_rate; ?>% 
                        (<?php echo $completed_tasks; ?> of <?php echo $total_tasks; ?> tasks completed)
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-clock text-info me-2"></i>
                        <strong>Average Resolution Time:</strong> <?php echo $avg_time_display; ?>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-tasks text-primary me-2"></i>
                        <strong>Active Tasks:</strong> <?php echo $pending_tasks; ?> tasks in progress
                    </li>
                    <?php if ($efficiency_score > 0): ?>
                        <li>
                            <i class="fas fa-rocket text-warning me-2"></i>
                            <strong>Efficiency Score:</strong> <?php echo $efficiency_score; ?>% 
                            <?php if ($efficiency_score >= 70): ?>
                                <span class="badge bg-success">Excellent</span>
                            <?php elseif ($efficiency_score >= 50): ?>
                                <span class="badge bg-warning">Good</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Needs Improvement</span>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-gem text-primary me-1"></i> Quick Stats</div>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted">Total Tasks</small>
                            <h5 class="mb-0"><?php echo $total_tasks; ?></h5>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted">Completion Rate</small>
                            <h5 class="mb-0"><?php echo $resolution_rate; ?>%</h5>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted">Avg Resolution</small>
                            <h5 class="mb-0"><?php echo $avg_time_display; ?></h5>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted">Pending Tasks</small>
                            <h5 class="mb-0"><?php echo $pending_tasks; ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====== CHART.JS ====== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    // ====== COLOR PALETTE ======
    const colors = {
        primary: '#1a73e8',
        success: '#28a745',
        warning: '#ffc107',
        danger: '#dc3545',
        info: '#17a2b8',
        secondary: '#6c757d',
        purple: '#6f42c1',
        orange: '#fd7e14',
        teal: '#20c997',
        pink: '#e83e8c'
    };

    const chartColors = [
        colors.primary, colors.success, colors.warning, colors.danger, 
        colors.info, colors.purple, colors.orange, colors.teal, colors.pink
    ];

    // ====== STATUS CHART ======
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($status_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($status_counts); ?>,
                backgroundColor: [
                    '#fff3cd', '#cce5ff', '#d1ecf1', '#d4edda', '#e2e3e5'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            }
        }
    });

    // ====== PRIORITY CHART ======
    const priorityCtx = document.getElementById('priorityChart').getContext('2d');
    new Chart(priorityCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($priority_labels); ?>,
            datasets: [{
                label: 'Tasks by Priority',
                data: <?php echo json_encode($priority_counts); ?>,
                backgroundColor: ['#d4edda', '#fff3cd', '#f8d7da', '#dc3545'],
                borderColor: ['#28a745', '#ffc107', '#dc3545', '#dc3545'],
                borderWidth: 2,
                borderRadius: 6,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // ====== CATEGORY CHART ======
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(categoryCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($category_labels); ?>,
            datasets: [{
                label: 'Tasks by Category',
                data: <?php echo json_encode($category_counts); ?>,
                backgroundColor: chartColors.slice(0, <?php echo count($category_labels); ?>),
                borderColor: '#fff',
                borderWidth: 2,
                borderRadius: 6,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // ====== TREND CHART ======
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_months); ?>,
            datasets: [
                {
                    label: 'Total Tasks',
                    data: <?php echo json_encode($trend_total); ?>,
                    borderColor: colors.primary,
                    backgroundColor: 'rgba(26, 115, 232, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: colors.primary,
                    pointRadius: 5
                },
                {
                    label: 'Completed Tasks',
                    data: <?php echo json_encode($trend_completed); ?>,
                    borderColor: colors.success,
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: colors.success,
                    pointRadius: 5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    console.log('✅ Technician Reports Loaded');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>