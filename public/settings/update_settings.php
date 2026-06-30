<?php
// public/settings/update_settings.php - Save settings

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../includes/settings_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    $data = [
        'header_color' => $_POST['header_color'] ?? '#0d47a1',
        'sidebar_color' => $_POST['sidebar_color'] ?? '#0d47a1',
        'background_color' => $_POST['background_color'] ?? '#f8f9fa',
        'font_size' => $_POST['font_size'] ?? '14px',
        'sidebar_collapsed' => isset($_POST['sidebar_collapsed']) ? intval($_POST['sidebar_collapsed']) : 0
    ];
    
    // Validate hex colors
    foreach (['header_color', 'sidebar_color', 'background_color'] as $key) {
        if (!preg_match('/^#[a-f0-9]{6}$/i', $data[$key])) {
            $data[$key] = '#0d47a1';
        }
    }
    
    // Validate font size
    $valid_sizes = ['10px', '12px', '14px', '16px', '18px'];
    if (!in_array($data['font_size'], $valid_sizes)) {
        $data['font_size'] = '14px';
    }
    
    $result = updateUserSettings($user_id, $data);
    
    if ($result) {
        header('Location: index.php?msg=updated&type=success');
    } else {
        header('Location: index.php?msg=error&type=danger');
    }
    exit();
} else {
    header('Location: index.php');
    exit();
}
?>