<?php
// admin/index.php
require_once 'includes/auth.php';
require_once __DIR__ . '/../../includes/Database.php';

$db = Database::getInstance()->getConnection();

// --- Metrics Queries ---
$stmt = $db->query("SELECT COUNT(*) as total FROM students");
$totalStudents = $stmt->fetch()['total'];

$stmt = $db->query("SELECT SUM(paid_amount) as total FROM fee_balances");
$totalFees = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM results");
$totalResults = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM course_registrations WHERE status = 'registered'");
$activeRegistrations = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM announcements WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
$activeAnnouncements = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM ussd_sessions WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$sessionsToday = $stmt->fetch()['total'];

// --- Recent Sessions (last 5) ---
$recentSessions = $db->query("SELECT phone_number, current_state, created_at FROM ussd_sessions ORDER BY created_at DESC LIMIT 5")->fetchAll();

// --- Outstanding Fee Balance (students with balance > 0) ---
$stmt = $db->query("SELECT COUNT(DISTINCT reg_no) as total FROM fee_balances WHERE balance > 0");
$outstandingCount = $stmt->fetch()['total'];

include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Metrics Cards Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm bg-white">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                        <i class="bi bi-people-fill fs-2 text-primary"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Total Students</h6>
                        <h3 class="mb-0 fw-bold"><?php echo number_format($totalStudents); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm bg-white">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                        <i class="bi bi-cash-stack fs-2 text-success"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Fees Collected</h6>
                        <h3 class="mb-0 fw-bold fs-5">TZS <?php echo number_format($totalFees, 0); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm bg-white">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
                        <i class="bi bi-bar-chart-line fs-2 text-info"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Results Entered</h6>
                        <h3 class="mb-0 fw-bold"><?php echo number_format($totalResults); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm bg-white">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                        <i class="bi bi-telephone-forward fs-2 text-warning"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Today's Sessions</h6>
                        <h3 class="mb-0 fw-bold"><?php echo number_format($sessionsToday); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row Metrics -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm bg-white">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                        <i class="bi bi-file-text fs-2 text-danger"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Active Registrations</h6>
                        <h3 class="mb-0 fw-bold"><?php echo number_format($activeRegistrations); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm bg-white">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-secondary bg-opacity-10 p-3 me-3">
                        <i class="bi bi-megaphone fs-2 text-secondary"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Active Announcements</h6>
                        <h3 class="mb-0 fw-bold"><?php echo number_format($activeAnnouncements); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm bg-white">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                        <i class="bi bi-credit-card-2-front fs-2 text-danger"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Outstanding Balances</h6>
                        <h3 class="mb-0 fw-bold "><?php echo number_format($outstandingCount); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm bg-white">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-purple bg-opacity-10 p-3 me-3">
                        <i class="bi bi-calendar-week fs-2 text-purple"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Last 7 Days Sessions</h6>
                        <h3 class="mb-0 fw-bold">
                            <?php
                                $stmt = $db->query("SELECT COUNT(*) as total FROM ussd_sessions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                                echo number_format($stmt->fetch()['total']);
                            ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions and Recent Activity -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-lightning me-2"></i> Quick Actions
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="students/add.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Student</a>
                        <a href="results/upload.php" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload Results (CSV)</a>
                        <a href="fees/record_payment.php" class="btn btn-success"><i class="bi bi-cash me-1"></i>Record Payment</a>
                        <a href="announcements/add.php" class="btn btn-info text-white"><i class="bi bi-megaphone me-1"></i>New Announcement</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">
                    <i class="bi bi-clock-history me-2"></i> Recent USSD Sessions
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Phone</th><th>State</th><th>Time</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentSessions)): ?>
                                    <tr><td colspan="3" class="text-center py-3">No sessions yet.</a>
                                        <tr>
                                <?php else: ?>
                                    <?php foreach ($recentSessions as $s): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($s['phone_number']); ?></td>
                                            <td><?php echo htmlspecialchars($s['current_state']); ?></td>
                                            <td><?php echo date('d/m H:i', strtotime($s['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white text-end">
                    <a href="logs/index.php" class="text-decoration-none">View all logs <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-purple { background-color: #6f42c1; }
    .text-purple { color: #6f42c1; }
    .card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
</style>

<?php include 'includes/footer.php'; ?>