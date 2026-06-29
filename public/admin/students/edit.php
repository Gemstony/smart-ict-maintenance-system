<?php
// admin/students/edit.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../../includes/Database.php';

$db = Database::getInstance()->getConnection();
$error = '';
$success = '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Fetch student data
$stmt = $db->prepare("SELECT * FROM students WHERE student_id = :id");
$stmt->execute(['id' => $id]);
$student = $stmt->fetch();
if (!$student) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone_number = trim($_POST['phone_number']);
    $new_pin = $_POST['new_pin'] ?? '';
    
    if (empty($full_name) || empty($phone_number)) {
        $error = 'Full name and phone number are required.';
    } else {
        if (!empty($new_pin)) {
            if (strlen($new_pin) < 4 || strlen($new_pin) > 6) {
                $error = 'PIN must be 4-6 digits.';
            } else {
                $pin_hash = password_hash($new_pin, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE students SET full_name = :full_name, phone_number = :phone_number, pin_hash = :pin_hash WHERE student_id = :id");
                $success = $stmt->execute(['full_name' => $full_name, 'phone_number' => $phone_number, 'pin_hash' => $pin_hash, 'id' => $id]);
            }
        } else {
            $stmt = $db->prepare("UPDATE students SET full_name = :full_name, phone_number = :phone_number WHERE student_id = :id");
            $success = $stmt->execute(['full_name' => $full_name, 'phone_number' => $phone_number, 'id' => $id]);
        }
        
        if ($success) {
            header('Location: index.php?msg=updated');
            exit;
        } else {
            $error = 'Update failed.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="card p-4 mx-auto" style="max-width: 600px;">
    <h3>Edit Student: <?php echo htmlspecialchars($student['reg_no']); ?></h3>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Registration Number:</label>
            <input type="text" value="<?php echo htmlspecialchars($student['reg_no']); ?>" disabled class="form-control bg-light">
        </div>
        <div class="mb-3">
            <label class="form-label">Full Name:</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" required class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">Phone Number:</label>
            <input type="text" name="phone_number" value="<?php echo htmlspecialchars($student['phone_number']); ?>" required class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">Change PIN (leave blank to keep current):</label>
            <input type="password" name="new_pin" placeholder="New PIN (4-6 digits)" class="form-control">
        </div>
        <button type="submit" class="btn btn-warning">Update Student</button>
        <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>