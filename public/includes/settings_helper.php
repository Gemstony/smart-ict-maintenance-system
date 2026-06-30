<?php
// public/includes/settings_helper.php - Settings helper functions

require_once __DIR__ . '/../../config.php';

// Get user settings
function getUserSettings($user_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // Create default settings
        $stmt = $db->prepare("INSERT INTO user_settings (user_id, header_color, sidebar_color, background_color, font_size, sidebar_collapsed) 
                              VALUES (?, '#0d47a1', '#0d47a1', '#f8f9fa', '14px', 0)");
        $stmt->execute([$user_id]);
        
        // Fetch again
        $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch();
    }
    
    return $settings;
}

// Update user settings
function updateUserSettings($user_id, $data) {
    $db = getDB();
    
    $fields = [];
    $values = [];
    
    foreach ($data as $key => $value) {
        if (in_array($key, ['header_color', 'sidebar_color', 'background_color', 'font_size', 'sidebar_collapsed'])) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
    }
    
    if (empty($fields)) {
        return false;
    }
    
    $values[] = $user_id;
    $sql = "UPDATE user_settings SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
    $stmt = $db->prepare($sql);
    return $stmt->execute($values);
}

// Get available font sizes
function getFontSizes() {
    return [
        '10px' => 'Small (10px)',
        '12px' => 'Normal (12px)',
        '14px' => 'Medium (14px)',
        '16px' => 'Large (16px)',
        '18px' => 'Extra Large (18px)'
    ];
}

// Get available colors (predefined themes)
function getColorThemes() {
    return [
        '#0d47a1' => 'Deep Blue',
        '#1a73e8' => 'Google Blue',
        '#004d40' => 'Teal Green',
        '#1a237e' => 'Indigo',
        '#4a148c' => 'Purple',
        '#880e4f' => 'Deep Pink',
        '#bf360c' => 'Deep Orange',
        '#1e1e1e' => 'Dark',
        '#2d3436' => 'Dark Gray',
        '#e17055' => 'Warm Red',
        '#00b894' => 'Mint Green',
        '#fdcb6e' => 'Golden',
    ];
}
?>