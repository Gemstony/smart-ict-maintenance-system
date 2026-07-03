<?php
// public/admin/reports.php - Admin Intensive Reports Dashboard

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/settings_helper.php';

requireRole('System Administrator');

$db = getDB();

// ====== DATE FILTERS ======
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

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
$stmt = $db->query("SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END) as assigned,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed,
    AVG(TIMESTAMPDIFF(HOUR, assigned_at, resolved_at)) as avg_resolution_time
FROM maintenance_requests r
WHERE 1=1 $date_condition");
$overall = $stmt->fetch();

$total_requests = $overall['total_requests'] ?? 0;
$completed = $overall['completed'] ?? 0;
$pending = $overall['pending'] ?? 0;
$assigned = $overall['assigned'] ?? 0;
$in_progress = $overall['in_progress'] ?? 0;
$resolved = $overall['resolved'] ?? 0;
$closed = $overall['closed'] ?? 0;
$avg_resolution = $overall['avg_resolution_time'] ?? null;
$avg_resolution_display = $avg_resolution ? round($avg_resolution, 1) . ' hrs' : 'N/A';
$resolution_rate = ($total_requests > 0) ? round(($completed / $total_requests) * 100, 1) : 0;

// ====== TECHNICIAN PERFORMANCE ======
$stmt = $db->query("SELECT 
    u.user_id,
    CONCAT(u.first_name, ' ', u.last_name) as tech_name,
    COUNT(r.request_id) as total_tasks,
    SUM(CASE WHEN r.status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN r.status NOT IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as pending_tasks,
    AVG(TIMESTAMPDIFF(HOUR, r.assigned_at, r.resolved_at)) as avg_time,
    MAX(r.resolved_at) as last_completed
FROM users u
LEFT JOIN maintenance_requests r ON u.user_id = r.assigned_to
WHERE u.role = 'ICT Technician' AND u.status = 'active'
GROUP BY u.user_id
ORDER BY completed_tasks DESC");
$technicians = $stmt->fetchAll();

// ====== REQUESTS BY STATUS (for pie) ======
$stmt = $db->query("SELECT status, COUNT(*) as count 
                     FROM maintenance_requests 
                     WHERE 1=1 $date_condition
                     GROUP BY status");
$status_data = $stmt->fetchAll();
$status_labels = [];
$status_counts = [];
foreach ($status_data as $row) {
    $status_labels[] = $row['status'];
    $status_counts[] = $row['count'];
}

// ====== REQUESTS BY PRIORITY ======
$stmt = $db->query("SELECT priority, COUNT(*) as count 
                     FROM maintenance_requests 
                     WHERE 1=1 $date_condition
                     GROUP BY priority");
$priority_data = $stmt->fetchAll();
$priority_labels = ['Low', 'Medium', 'High', 'Critical'];
$priority_counts = [0,0,0,0];
foreach ($priority_data as $row) {
    $idx = array_search($row['priority'], $priority_labels);
    if ($idx !== false) $priority_counts[$idx] = $row['count'];
}

// ====== REQUESTS BY DEPARTMENT (location) ======
$stmt = $db->query("SELECT a.location, COUNT(*) as count 
                     FROM maintenance_requests r
                     LEFT JOIN assets a ON r.asset_id = a.asset_id
                     WHERE a.location IS NOT NULL
                     GROUP BY a.location
                     ORDER BY count DESC
                     LIMIT 10");
$dept_data = $stmt->fetchAll();
$dept_labels = [];
$dept_counts = [];
foreach ($dept_data as $row) {
    $dept_labels[] = $row['location'];
    $dept_counts[] = $row['count'];
}

// ====== TOP 10 ASSETS WITH MOST REQUESTS ======
$stmt = $db->query("SELECT a.name, a.asset_tag, COUNT(r.request_id) as request_count
                     FROM maintenance_requests r
                     LEFT JOIN assets a ON r.asset_id = a.asset_id
                     WHERE a.asset_id IS NOT NULL
                     GROUP BY a.asset_id
                     ORDER BY request_count DESC
                     LIMIT 10");
$top_assets = $stmt->fetchAll();

// ====== MONTHLY TREND (last 12 months) ======
$stmt = $db->query("SELECT DATE_FORMAT(reported_at, '%Y-%m') as month, 
                            COUNT(*) as total,
                            SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as completed
                     FROM maintenance_requests 
                     WHERE reported_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
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

// ====== AVERAGE RESOLUTION TIME BY PRIORITY ======
$stmt = $db->query("SELECT priority, AVG(TIMESTAMPDIFF(HOUR, assigned_at, resolved_at)) as avg_time
                     FROM maintenance_requests
                     WHERE assigned_at IS NOT NULL AND resolved_at IS NOT NULL
                     GROUP BY priority");
$priority_time_data = $stmt->fetchAll();
$priority_times = [];
foreach ($priority_time_data as $row) {
    $priority_times[$row['priority']] = round($row['avg_time'], 1);
}

// ====== DAILY ACTIVITY (last 30 days) ======
$stmt = $db->query("SELECT DATE(reported_at) as day, COUNT(*) as count
                     FROM maintenance_requests 
                     WHERE reported_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                     GROUP BY DATE(reported_at)
                     ORDER BY day ASC");
$daily_data = $stmt->fetchAll();
$daily_days = [];
$daily_counts = [];
foreach ($daily_data as $row) {
    $daily_days[] = date('d M', strtotime($row['day']));
    $daily_counts[] = $row['count'];
}

// ====== QR SCAN STATISTICS ======
$stmt = $db->query("SELECT COUNT(*) as total_qr_scans FROM qr_scan_logs");
$total_qr_scans = $stmt->fetch()['total_qr_scans'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as qr_reported FROM maintenance_requests WHERE qr_scanned = 1");
$qr_reported = $stmt->fetch()['qr_reported'] ?? 0;

$qr_percentage = ($total_requests > 0) ? round(($qr_reported / $total_requests) * 100, 1) : 0;

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
    .stat-card .stat-detail {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 4px;
    }

    .bg-primary-soft { background: #e8f0fe; color: #1a73e8; }
    .bg-success-soft { background: #d4edda; color: #28a745; }
    .bg-warning-soft { background: #fff3cd; color: #856404; }
    .bg-info-soft { background: #d1ecf1; color: #0c5460; }
    .bg-danger-soft { background: #f8d7da; color: #dc3545; }
    .bg-secondary-soft { background: #e2e3e5; color: #383d41; }
    .bg-purple-soft { background: #e8d5f5; color: #6f42c1; }

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

    /* ====== TECHNICIAN TABLE ====== */
    .tech-table .badge {
        font-size: 0.7rem;
        padding: 3px 8px;
    }
    .tech-table .progress {
        height: 8px;
        border-radius: 10px;
        background-color: #e9ecef;
        min-width: 80px;
    }
    .tech-table .progress-bar {
        border-radius: 10px;
    }
    .rank-badge {
        display: inline-block;
        width: 28px;
        height: 28px;
        line-height: 28px;
        text-align: center;
        border-radius: 50%;
        font-weight: 700;
        font-size: 0.75rem;
        background: #f1f3f5;
        color: #495057;
    }
    .rank-badge.gold { background: #ffd700; color: #856404; }
    .rank-badge.silver { background: #c0c0c0; color: #495057; }
    .rank-badge.bronze { background: #cd7f32; color: white; }

    /* ====== RESPONSIVE ====== */
    @media (max-width: 768px) {
        .stat-card .stat-number { font-size: 1.5rem; }
        .chart-container { height: 200px; }
        .filter-btn { padding: 3px 10px; font-size: 0.7rem; }
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-chart-pie text-primary"></i> System Reports</h4>
            <small class="text-muted">Comprehensive analytics and performance metrics</small>
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

    <!-- ====== OVERALL STATS ====== -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-primary-soft"><i class="fas fa-tasks text-primary"></i></div>
                <p class="stat-number"><?php echo $total_requests; ?></p>
                <p class="stat-label">Total Requests</p>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-success-soft"><i class="fas fa-check-circle text-success"></i></div>
                <p class="stat-number"><?php echo $resolution_rate; ?>%</p>
                <p class="stat-label">Resolution Rate</p>
                <div class="stat-detail"><?php echo $completed; ?> of <?php echo $total_requests; ?> completed</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-info-soft"><i class="fas fa-clock text-info"></i></div>
                <p class="stat-number"><?php echo $avg_resolution_display; ?></p>
                <p class="stat-label">Avg Resolution Time</p>
                <div class="stat-detail">from assignment to resolution</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-warning-soft"><i class="fas fa-qrcode text-warning"></i></div>
                <p class="stat-number"><?php echo $qr_percentage; ?>%</p>
                <p class="stat-label">QR Reported</p>
                <div class="stat-detail"><?php echo $qr_reported; ?> requests via QR</div>
            </div>
        </div>
    </div>

    <!-- ====== STATUS DISTRIBUTION & TREND ====== -->
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-chart-pie text-primary me-1"></i> Status Distribution</div>
                <div class="chart-container chart-container-sm">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-flag me-1"></i> Priority Breakdown</div>
                <div class="chart-container chart-container-sm">
                    <canvas id="priorityChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-building me-1"></i> By Department</div>
                <div class="chart-container chart-container-sm">
                    <canvas id="departmentChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== MONTHLY TREND & DAILY ACTIVITY ====== -->
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-chart-line text-primary me-1"></i> Monthly Trend (Last 12 Months)</div>
                <div class="chart-container" style="height:300px;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-calendar-day me-1"></i> Daily Activity (Last 30 Days)</div>
                <div class="chart-container" style="height:300px;">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== TECHNICIAN PERFORMANCE TABLE ====== -->
    <div class="chart-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="chart-title mb-0"><i class="fas fa-users me-1"></i> Technician Performance</div>
            <span class="badge bg-secondary"><?php echo count($technicians); ?> active technicians</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover tech-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Technician</th>
                        <th>Total Tasks</th>
                        <th>Completed</th>
                        <th>Pending</th>
                        <th>Resolution Rate</th>
                        <th>Avg Time</th>
                        <th>Last Completion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($technicians) > 0): ?>
                        <?php $rank = 1; ?>
                        <?php foreach ($technicians as $tech): 
                            $tech_total = $tech['total_tasks'] ?? 0;
                            $tech_completed = $tech['completed_tasks'] ?? 0;
                            $tech_pending = $tech['pending_tasks'] ?? 0;
                            $tech_rate = ($tech_total > 0) ? round(($tech_completed / $tech_total) * 100, 1) : 0;
                            $tech_avg = $tech['avg_time'] ? round($tech['avg_time'], 1) . ' hrs' : 'N/A';
                            $rank_class = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
                        ?>
                            <tr>
                                <td><span class="rank-badge <?php echo $rank_class; ?>"><?php echo $rank++; ?></span></td>
                                <td><?php echo htmlspecialchars($tech['tech_name']); ?></td>
                                <td><?php echo $tech_total; ?></td>
                                <td><?php echo $tech_completed; ?></td>
                                <td><?php echo $tech_pending; ?></td>
                                <td>
                                    <?php if ($tech_total > 0): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <span><?php echo $tech_rate; ?>%</span>
                                            <div class="progress">
                                                <div class="progress-bar bg-<?php echo $tech_rate >= 70 ? 'success' : ($tech_rate >= 50 ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo $tech_rate; ?>%;"></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $tech_avg; ?></td>
                                <td>
                                    <?php if ($tech['last_completed']): ?>
                                        <?php echo date('d M Y', strtotime($tech['last_completed'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted">No active technicians found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ====== TOP ASSETS & PRIORITY TIMES ====== -->
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-server me-1"></i> Top 10 Assets with Most Requests</div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Asset</th>
                                <th>Tag</th>
                                <th>Requests</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($top_assets) > 0): ?>
                                <?php $i = 1; foreach ($top_assets as $asset): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($asset['name'] ?? 'Unknown'); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($asset['asset_tag']); ?></span></td>
                                        <td><span class="badge bg-primary"><?php echo $asset['request_count']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted">No asset data available.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-clock me-1"></i> Average Resolution Time by Priority</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Priority</th>
                                <th>Avg Resolution Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($priority_labels as $p): ?>
                                <tr>
                                    <td><span class="priority-badge priority-<?php echo $p; ?>"><?php echo $p; ?></span></td>
                                    <td>
                                        <?php if (isset($priority_times[$p])): ?>
                                            <?php echo $priority_times[$p]; ?> hrs
                                        <?php else: ?>
                                            <span class="text-muted">No data</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <small class="text-muted">Average time from assignment to resolution.</small>
            </div>
        </div>
    </div>
</div>

<!-- ====== CHART.JS ====== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    const colors = ['#1a73e8', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6f42c1', '#fd7e14', '#20c997', '#e83e8c'];

    // Status Chart (Doughnut)
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($status_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($status_counts); ?>,
                backgroundColor: ['#fff3cd', '#cce5ff', '#d1ecf1', '#d4edda', '#e2e3e5'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    // Priority Chart (Bar)
    new Chart(document.getElementById('priorityChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($priority_labels); ?>,
            datasets: [{
                label: 'Requests',
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
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Department Chart (Horizontal Bar)
    new Chart(document.getElementById('departmentChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($dept_labels); ?>,
            datasets: [{
                label: 'Requests',
                data: <?php echo json_encode($dept_counts); ?>,
                backgroundColor: colors.slice(0, <?php echo count($dept_labels); ?>),
                borderColor: '#fff',
                borderWidth: 2,
                borderRadius: 6,
                barPercentage: 0.6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Trend Chart (Line)
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_months); ?>,
            datasets: [
                { label: 'Total Requests', data: <?php echo json_encode($trend_total); ?>, borderColor: '#1a73e8', backgroundColor: 'rgba(26,115,232,0.1)', fill: true, tension: 0.4, pointRadius: 4 },
                { label: 'Completed', data: <?php echo json_encode($trend_completed); ?>, borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,0.1)', fill: true, tension: 0.4, pointRadius: 4 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Daily Activity Chart (Bar)
    new Chart(document.getElementById('dailyChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($daily_days); ?>,
            datasets: [{
                label: 'Requests',
                data: <?php echo json_encode($daily_counts); ?>,
                backgroundColor: 'rgba(26,115,232,0.6)',
                borderColor: '#1a73e8',
                borderWidth: 1,
                borderRadius: 4,
                barPercentage: 0.8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>