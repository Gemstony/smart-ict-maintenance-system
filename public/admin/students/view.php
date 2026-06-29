<?php
// admin/students/view.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../../includes/Database.php';

$db = Database::getInstance()->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Fetch student details
$stmt = $db->prepare("SELECT * FROM students WHERE student_id = :id");
$stmt->execute(['id' => $id]);
$student = $stmt->fetch();
if (!$student) {
    header('Location: index.php');
    exit;
}

// Fetch fee balances (all semesters)
$feeStmt = $db->prepare("SELECT * FROM fee_balances WHERE reg_no = :reg_no ORDER BY semester DESC");
$feeStmt->execute(['reg_no' => $student['reg_no']]);
$fees = $feeStmt->fetchAll();

// Fetch results (all semesters)
$resultStmt = $db->prepare("SELECT * FROM results WHERE reg_no = :reg_no ORDER BY semester DESC, course_code");
$resultStmt->execute(['reg_no' => $student['reg_no']]);
$results = $resultStmt->fetchAll();

// Fetch course registrations (all semesters)
$regStmt = $db->prepare("SELECT * FROM course_registrations WHERE reg_no = :reg_no ORDER BY semester DESC, course_code");
$regStmt->execute(['reg_no' => $student['reg_no']]);
$registrations = $regStmt->fetchAll();

// Fetch recent USSD sessions (last 10)
$sessionStmt = $db->prepare("SELECT session_id, current_state, created_at FROM ussd_sessions WHERE phone_number = :phone ORDER BY created_at DESC LIMIT 10");
$sessionStmt->execute(['phone' => $student['phone_number']]);
$sessions = $sessionStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-person-badge me-2"></i>Student Details</h4>
            <div>
                <a href="edit.php?id=<?php echo $student['student_id']; ?>" class="btn btn-warning btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                <a href="index.php" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
        <div class="card-body">
            <!-- Personal Information -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr><th>Registration Number</th><td><?php echo htmlspecialchars($student['reg_no']); ?></td></tr>
                        <tr><th>Full Name</th><td><?php echo htmlspecialchars($student['full_name']); ?></td></tr>
                        <tr><th>Phone Number</th><td><?php echo htmlspecialchars($student['phone_number']); ?></td></tr>
                        <tr><th>Registered On</th><td><?php echo date('d/m/Y H:i', strtotime($student['created_at'])); ?></td></tr>
                        <tr><th>Last Updated</th><td><?php echo date('d/m/Y H:i', strtotime($student['updated_at'])); ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5><i class="bi bi-bar-chart"></i> Summary</h5>
                            <ul class="list-unstyled">
                                <li><strong>Total Fees (All Semesters):</strong> TZS <?php echo number_format(array_sum(array_column($fees, 'total_fees')), 2); ?></li>
                                <li><strong>Total Paid:</strong> TZS <?php echo number_format(array_sum(array_column($fees, 'paid_amount')), 2); ?></li>
                                <li><strong>Outstanding Balance:</strong> <span class="text-danger">TZS <?php echo number_format(array_sum(array_column($fees, 'balance')), 2); ?></span></li>
                                <li><strong>Results Entered:</strong> <?php echo count($results); ?></li>
                                <li><strong>Course Registrations:</strong> <?php echo count($registrations); ?></li>
                                <li><strong>USSD Sessions:</strong> <?php echo count($sessions); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs for detailed data -->
            <ul class="nav nav-tabs" id="studentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="fees-tab" data-bs-toggle="tab" data-bs-target="#fees" type="button" role="tab">
                        <i class="bi bi-currency-dollar me-1"></i>Fees
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="results-tab" data-bs-toggle="tab" data-bs-target="#results" type="button" role="tab">
                        <i class="bi bi-graph-up me-1"></i>Results
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="registrations-tab" data-bs-toggle="tab" data-bs-target="#registrations" type="button" role="tab">
                        <i class="bi bi-file-earmark-text me-1"></i>Registrations
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessions" type="button" role="tab">
                        <i class="bi bi-telephone me-1"></i>USSD Sessions
                    </button>
                </li>
            </ul>
            <div class="tab-content p-3 border border-top-0 rounded-bottom">
                <!-- Fees Tab -->
                <div class="tab-pane fade show active" id="fees" role="tabpanel">
                    <?php if (empty($fees)): ?>
                        <p class="text-muted">No fee records found for this student.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="table-dark">
                                    <tr><th>Semester</th><th>Total Fees</th><th>Paid</th><th>Balance</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fees as $f): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($f['semester']); ?></td>
                                            <td>TZS <?php echo number_format($f['total_fees'], 2); ?></td>
                                            <td>TZS <?php echo number_format($f['paid_amount'], 2); ?></td>
                                            <td class="<?php echo $f['balance'] > 0 ? 'text-danger fw-bold' : 'text-success'; ?>">
                                                TZS <?php echo number_format($f['balance'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="/nit-ussd-system/public/admin/fees/index.php?reg_no=<?php echo urlencode($student['reg_no']); ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrow-right"></i> View all fees
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Results Tab -->
                <div class="tab-pane fade" id="results" role="tabpanel">
                    <?php if (empty($results)): ?>
                        <p class="text-muted">No results recorded for this student.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="table-dark">
                                    <tr><th>Semester</th><th>Course Code</th><th>Course Name</th><th>Grade</th><th>Marks</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['semester']); ?></td>
                                            <td><?php echo htmlspecialchars($r['course_code']); ?></td>
                                            <td><?php echo htmlspecialchars($r['course_name']); ?></td>
                                            <td><?php echo htmlspecialchars($r['grade']); ?></td>
                                            <td><?php echo htmlspecialchars($r['marks']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="/nit-ussd-system/public/admin/results/index.php?reg_no=<?php echo urlencode($student['reg_no']); ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrow-right"></i> View all results
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Registrations Tab -->
                <div class="tab-pane fade" id="registrations" role="tabpanel">
                    <?php if (empty($registrations)): ?>
                        <p class="text-muted">No course registrations found for this student.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="table-dark">
                                    <tr><th>Semester</th><th>Course Code</th><th>Registration Date</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrations as $reg): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($reg['semester']); ?></td>
                                            <td><?php echo htmlspecialchars($reg['course_code']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($reg['registration_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $reg['status'] === 'registered' ? 'success' : ($reg['status'] === 'dropped' ? 'danger' : 'info'); 
                                                ?>">
                                                    <?php echo ucfirst($reg['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="/nit-ussd-system/public/admin/registrations/by_student.php?reg_no=<?php echo urlencode($student['reg_no']); ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrow-right"></i> View all registrations
                        </a>
                    <?php endif; ?>
                </div>

                <!-- USSD Sessions Tab -->
                <div class="tab-pane fade" id="sessions" role="tabpanel">
                    <?php if (empty($sessions)): ?>
                        <p class="text-muted">No USSD sessions found for this student's phone number.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="table-dark">
                                    <tr><th>Session ID</th><th>State</th><th>Date & Time</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessions as $s): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars(substr($s['session_id'], 0, 20)); ?>…</code></td>
                                            <td><?php echo htmlspecialchars($s['current_state']); ?></td>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($s['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="/nit-ussd-system/public/admin/logs/index.php?phone=<?php echo urlencode($student['phone_number']); ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrow-right"></i> View all sessions
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>