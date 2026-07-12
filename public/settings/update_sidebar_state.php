<?php
// public/settings/update_sidebar_state.php - Update sidebar state via AJAX

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../includes/settings_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $collapsed = isset($_POST['collapsed']) ? intval($_POST['collapsed']) : 0;
    
    $data = [
        'sidebar_collapsed' => $collapsed
    ];
    
    $result = updateUserSettings($user_id, $data);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => $result]);
    exit();
}
?>