<?php
// public/technician/index.php - Technician Dashboard

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole('ICT Technician');

$counts = getDashboardCounts('ICT Technician', $_SESSION['user_id']);
$unread = getUnreadNotifications($_SESSION['user_id']);
$notifications = getNotifications($_SESSION['user_id']);

$db = getDB();
$stmt = $db->prepare("SELECT r.*, a.name as asset_name, 
                      CONCAT(u.first_name, ' ', u.last_name) as reported_by_name 
                      FROM maintenance_requests r 
                      LEFT JOIN assets a ON r.asset_id = a.asset_id 
                      LEFT JOIN users u ON r.reported_by = u.user_id 
                      WHERE r.assigned_to = ? 
                      ORDER BY r.reported_at DESC LIMIT 20");
$stmt->execute([$_SESSION['user_id']]);
$my_tasks = $stmt->fetchAll();

// Get tasks by status for charts
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM maintenance_requests WHERE assigned_to = ? GROUP BY status");
$stmt->execute([$_SESSION['user_id']]);
$task_status = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #1a237e;
            padding: 20px 0;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        .sidebar .nav-link i {
            width: 25px;
        }
        .sidebar .brand {
            color: white;
            font-size: 1.3rem;
            font-weight: bold;
            padding: 10px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .stat-card {
            border-radius: 15px;
            padding: 20px;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .content-area {
            background: #f8f9fa;
            min-height: calc(100vh - 70px);
            padding: 30px;
        }
        .top-nav {
            background: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .badge-notif {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.6rem;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 p-0 sidebar">
            <div class="brand">
                <i class="fas fa-microchip"></i> ICT-AMS
            </div>
            <nav class="nav flex-column">
                <a href="index.php" class="nav-link active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="my_tasks.php" class="nav-link">
                    <i class="fas fa-tasks"></i> My Tasks
                </a>
                <a href="update_task.php" class="nav-link">
                    <i class="fas fa-edit"></i> Update Task
                </a>
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <hr style="border-color: rgba(255,255,255,0.1);">
                <a href="../../logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </div>
        
        <!-- Main -->
        <div class="col-md-9 col-lg-10 p-0">
            <!-- Top Nav -->
            <div class="top-nav px-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0">Welcome, <?php echo getUserName(); ?>!</h5>
                        <small class="text-muted">ICT Technician</small>
                    </div>
                    <div class="col-auto">
                        <a href="#" class="btn btn-outline-primary position-relative me-2" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread > 0): ?>
                                <span class="badge bg-danger badge-notif"><?php echo $unread; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 300px;">
                            <div class="p-2 bg-light fw-bold">Notifications</div>
                            <?php if (count($notifications) > 0): ?>
                                <?php foreach ($notifications as $n): ?>
                                    <div class="dropdown-item small <?php echo $n['is_read'] ? '' : 'bg-light'; ?>">
                                        <strong><?php echo $n['title']; ?></strong>
                                        <p class="mb-0 text-muted"><?php echo $n['message']; ?></p>
                                        <small><?php echo timeAgo($n['created_at']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="dropdown-item text-muted">No notifications</div>
                            <?php endif; ?>
                        </div>
                        <a href="../../logout.php" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="content-area">
                <!-- Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card stat-card bg-primary text-white">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">My Active Tasks</h6>
                                    <h2><?php echo $counts['my_tasks'] ?? 0; ?></h2>
                                </div>
                                <div class="icon"><i class="fas fa-tasks"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card stat-card bg-success text-white">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Resolved This Month</h6>
                                    <h2><?php echo $counts['resolved_month'] ?? 0; ?></h2>
                                </div>
                                <div class="icon"><i class="fas fa-check-double"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Task Status Chart -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-white fw-bold">My Tasks by Status</div>
                            <div class="card-body">
                                <?php if (count($task_status) > 0): ?>
                                    <?php foreach ($task_status as $ts): ?>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span><?php echo $ts['status']; ?></span>
                                            <span class="badge bg-secondary"><?php echo $ts['count']; ?></span>
                                        </div>
                                        <div class="progress mb-3">
                                            <?php 
                                            $total = array_sum(array_column($task_status, 'count'));
                                            $width = ($total > 0) ? ($ts['count'] / $total * 100) : 0;
                                            ?>
                                            <div class="progress-bar bg-info" style="width: <?php echo $width; ?>%"></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center">No tasks assigned yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-white fw-bold">Quick Actions</div>
                            <div class="card-body text-center">
                                <a href="update_task.php" class="btn btn-warning btn-lg m-2">
                                    <i class="fas fa-edit"></i> Update Task
                                </a>
                                <a href="my_tasks.php" class="btn btn-primary btn-lg m-2">
                                    <i class="fas fa-list"></i> My Tasks
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- My Tasks -->
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-list"></i> My Assigned Tasks
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Request</th>
                                        <th>Asset</th>
                                        <th>Reported By</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($my_tasks) > 0): ?>
                                        <?php foreach ($my_tasks as $task): ?>
                                            <tr>
                                                <td>#<?php echo $task['request_id']; ?></td>
                                                <td><?php echo $task['asset_name'] ?? 'N/A'; ?></td>
                                                <td><?php echo $task['reported_by_name'] ?? 'N/A'; ?></td>
                                                <td><?php echo getPriorityBadge($task['priority']); ?></td>
                                                <td><?php echo getStatusBadge($task['status']); ?></td>
                                                <td>
                                                    <a href="update_task.php?id=<?php echo $task['request_id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i> Update
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No tasks assigned yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>