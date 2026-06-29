<?php
// admin/students/add.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../../includes/Database.php';

$db = Database::getInstance()->getConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reg_no = trim($_POST['reg_no']);
    $full_name = trim($_POST['full_name']);
    $phone_number = trim($_POST['phone_number']);
    $pin = $_POST['pin'];
    
    // Validation
    if (empty($reg_no) || empty($full_name) || empty($phone_number) || empty($pin)) {
        $error = 'All fields are required.';
    } elseif (strlen($pin) < 4 || strlen($pin) > 6) {
        $error = 'PIN must be 4-6 digits.';
    } else {
        // Check if reg_no already exists
        $stmt = $db->prepare("SELECT reg_no FROM students WHERE reg_no = :reg_no");
        $stmt->execute(['reg_no' => $reg_no]);
        if ($stmt->fetch()) {
            $error = 'Registration number already exists.';
        } else {
            $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO students (reg_no, full_name, phone_number, pin_hash) VALUES (:reg_no, :full_name, :phone_number, :pin_hash)");
            if ($stmt->execute(['reg_no' => $reg_no, 'full_name' => $full_name, 'phone_number' => $phone_number, 'pin_hash' => $pin_hash])) {
                $success = 'Student added successfully.';
                // Clear form
                $reg_no = $full_name = $phone_number = '';
            } else {
                $error = 'Database error.';
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="card p-4 mx-auto" style="max-width: 600px;">
    <h3>Add New Student</h3>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Registration Number:</label>
            <input type="text" name="reg_no" value="<?php echo htmlspecialchars($reg_no ?? ''); ?>" required class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">Full Name:</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name ?? ''); ?>" required class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">Phone Number:</label>
            <input type="text" name="phone_number" value="<?php echo htmlspecialchars($phone_number ?? ''); ?>" required placeholder="e.g., 255712345678" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">PIN (4-6 digits):</label>
            <input type="password" name="pin" required class="form-control">
        </div>
        <button type="submit" class="btn btn-success">Save Student</button>
        <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>