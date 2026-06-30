<?php
// public/staff/index.php - Staff Dashboard

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole('Staff');

$counts = getDashboardCounts('Staff', $_SESSION['user_id']);
$unread = getUnreadNotifications($_SESSION['user_id']);
$notifications = getNotifications($_SESSION['user_id']);

$db = getDB();
$stmt = $db->prepare("SELECT r.*, a.name as asset_name, 
                      CONCAT(t.first_name, ' ', t.last_name) as technician_name 
                      FROM maintenance_requests r 
                      LEFT JOIN assets a ON r.asset_id = a.asset_id 
                      LEFT JOIN users t ON r.assigned_to = t.user_id 
                      WHERE r.reported_by = ? 
                      ORDER BY r.reported_at DESC LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$my_requests = $stmt->fetchAll();

// Get available assets for reporting
$stmt_assets = $db->query("SELECT * FROM assets WHERE status = 'Available' OR status = 'In Use' ORDER BY name");
$assets = $stmt_assets->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #004d40;
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
                <a href="report_fault.php" class="nav-link">
                    <i class="fas fa-exclamation-triangle"></i> Report Fault
                </a>
                <a href="my_requests.php" class="nav-link">
                    <i class="fas fa-list"></i> My Requests
                </a>
                <a href="view_assets.php" class="nav-link">
                    <i class="fas fa-laptop"></i> View Assets
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
                        <small class="text-muted">Staff - <?php echo $_SESSION['department'] ?? 'N/A'; ?></small>
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
                                    <h6 class="text-white-50">My Active Requests</h6>
                                    <h2><?php echo $counts['my_requests'] ?? 0; ?></h2>
                                </div>
                                <div class="icon"><i class="fas fa-clock"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card stat-card bg-success text-white">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Resolved (30 days)</h6>
                                    <h2><?php echo $counts['resolved_my'] ?? 0; ?></h2>
                                </div>
                                <div class="icon"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                <h5>Report a Fault</h5>
                                <p class="text-muted">Submit a maintenance request for ICT equipment</p>
                                <a href="report_fault.php" class="btn btn-warning">Report Now</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-search fa-3x text-info mb-3"></i>
                                <h5>View Assets</h5>
                                <p class="text-muted">Check available ICT assets and their status</p>
                                <a href="view_assets.php" class="btn btn-info text-white">View Assets</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- My Requests -->
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-list"></i> My Recent Requests
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Request</th>
                                        <th>Asset</th>
                                        <th>Technician</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($my_requests) > 0): ?>
                                        <?php foreach ($my_requests as $req): ?>
                                            <tr>
                                                <td>#<?php echo $req['request_id']; ?></td>
                                                <td><?php echo $req['asset_name'] ?? 'N/A'; ?></td>
                                                <td><?php echo $req['technician_name'] ?? 'Not Assigned'; ?></td>
                                                <td><?php echo getPriorityBadge($req['priority']); ?></td>
                                                <td><?php echo getStatusBadge($req['status']); ?></td>
                                                <td><?php echo timeAgo($req['reported_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">You have not reported any faults yet.</td>
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