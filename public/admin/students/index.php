<?php
// admin/students/index.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../../includes/Database.php';

$db = Database::getInstance()->getConnection();

$limit = 20;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$message = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $message = "Student updated successfully!";
}
if (isset($_GET['msg']) && $_GET['msg'] === 'added') {
    $message = "Student added successfully!";
}
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Student deleted successfully!";
}

if (!empty($search)) {
    // Count with search
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM students WHERE reg_no LIKE ? OR full_name LIKE ?");
    $countStmt->execute(["%$search%", "%$search%"]);
    $total = $countStmt->fetch()['total'];

    // Fetch with search
    $stmt = $db->prepare("SELECT student_id, reg_no, full_name, phone_number, created_at FROM students 
                          WHERE reg_no LIKE ? OR full_name LIKE ? 
                          ORDER BY reg_no LIMIT ? OFFSET ?");
    $stmt->execute(["%$search%", "%$search%", $limit, $offset]);
} else {
    $countStmt = $db->query("SELECT COUNT(*) as total FROM students");
    $total = $countStmt->fetch()['total'];

    $stmt = $db->prepare("SELECT student_id, reg_no, full_name, phone_number, created_at FROM students ORDER BY reg_no LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
}
$students = $stmt->fetchAll();

$totalPages = ceil($total / $limit);

include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        ✅ <strong>Success!</strong> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card p-4">
    <h3>Student Management</h3>
    <div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
        <a href="add.php" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Add New Student</a>
        <form method="GET" class="d-flex">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by reg_no or name"
                value="<?php echo htmlspecialchars($search); ?>" style="width: 250px;">
            <button type="submit" class="btn btn-primary btn-sm ms-2">Search</button>
            <?php if ($search): ?>
                <a href="index.php" class="btn btn-secondary btn-sm ms-2">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Reg No</th>
                    <th>Full Name</th>
                    <th>Phone</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['reg_no']); ?></td>
                        <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($s['phone_number']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($s['created_at'])); ?></td>
                        <td>
                            <a href="view.php?id=<?php echo $s['student_id']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-eye"></i></a>
                            <a href="edit.php?id=<?php echo $s['student_id']; ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                            <a href="delete.php?id=<?php echo $s['student_id']; ?>" class="btn btn-sm btn-danger"
                                onclick="return confirm('Are you sure? This will also delete all linked fees, results, registrations.');"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">No students found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link"
                            href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>