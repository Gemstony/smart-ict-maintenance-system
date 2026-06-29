<?php
// admin/students/delete.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../../includes/Database.php';

$db = Database::getInstance()->getConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    // Due to foreign key CASCADE, deleting a student will delete related records.
    $stmt = $db->prepare("DELETE FROM students WHERE student_id = :id");
    $stmt->execute(['id' => $id]);
}
header('Location: index.php');
exit;