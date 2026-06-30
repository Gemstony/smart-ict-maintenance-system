<?php
// public/settings/index.php - User Settings

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../includes/settings_helper.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$settings = getUserSettings($user_id);
$font_sizes = getFontSizes();
$color_themes = getColorThemes();

// Get current values
$header_color = $settings['header_color'] ?? '#0d47a1';
$sidebar_color = $settings['sidebar_color'] ?? '#0d47a1';
$background_color = $settings['background_color'] ?? '#f8f9fa';
$font_size = $settings['font_size'] ?? '14px';
$sidebar_collapsed = $settings['sidebar_collapsed'] ?? 0;

// Handle messages
$message = $_GET['msg'] ?? '';
$message_type = $_GET['type'] ?? 'success';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Include header (which includes all CSS) -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <style>
        .settings-section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.06);
            transition: all 0.3s;
        }
        
        .settings-section:hover {
            box-shadow: 0 4px 25px rgba(0,0,0,0.1);
        }
        
        .settings-section .section-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .settings-section .section-title i {
            color: #1a73e8;
        }
        
        .color-option {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 3px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            margin: 4px;
        }
        
        .color-option:hover {
            transform: scale(1.1);
        }
        
        .color-option.selected {
            border-color: #1a73e8;
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.3);
        }
        
        .font-size-option {
            padding: 8px 16px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            margin: 4px;
            background: white;
        }
        
        .font-size-option:hover {
            border-color: #1a73e8;
        }
        
        .font-size-option.selected {
            border-color: #1a73e8;
            background: #e8f0fe;
        }
        
        .preview-box {
            padding: 20px;
            border-radius: 12px;
            margin-top: 15px;
            transition: all 0.3s;
            border: 2px dashed #dee2e6;
        }
        
        .preview-box .preview-header {
            padding: 10px 15px;
            border-radius: 8px 8px 0 0;
            color: white;
            font-weight: 500;
        }
        
        .preview-box .preview-sidebar {
            width: 200px;
            padding: 15px;
            border-radius: 0 0 0 8px;
            color: white;
            min-height: 120px;
            float: left;
        }
        
        .preview-box .preview-content {
            padding: 15px;
            min-height: 120px;
            border-radius: 0 0 8px 0;
            background: #f8f9fa;
            overflow: hidden;
        }
        
        .preview-clear {
            clear: both;
        }
        
        @media (max-width: 768px) {
            .settings-section {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-0"><i class="fas fa-cog text-primary"></i> Settings</h4>
                <small class="text-muted">Customize your dashboard appearance</small>
            </div>
        </div>
        
        <!-- Message Alert -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php 
                if ($message === 'updated') {
                    echo 'Settings updated successfully!';
                } elseif ($message === 'error') {
                    echo 'Failed to update settings. Please try again.';
                } else {
                    echo $message;
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Settings Form -->
        <form action="update_settings.php" method="POST" id="settingsForm">
            
            <!-- ====== HEADER COLOR ====== -->
            <div class="settings-section">
                <div class="section-title">
                    <i class="fas fa-palette"></i> Header Color
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <p class="text-muted small">Choose a color for the top header bar.</p>
                        <div class="d-flex flex-wrap gap-2" id="headerColorOptions">
                            <?php foreach ($color_themes as $color => $name): ?>
                                <div class="color-option <?php echo ($header_color === $color) ? 'selected' : ''; ?>" 
                                     style="background: <?php echo $color; ?>;"
                                     data-color="<?php echo $color; ?>"
                                     onclick="selectColor('header_color', '<?php echo $color; ?>', this)"
                                     title="<?php echo $name; ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="header_color" id="header_color" value="<?php echo $header_color; ?>">
                        <div class="mt-2">
                            <label class="form-label small">Custom Color</label>
                            <input type="color" class="form-control form-control-color" style="width: 60px; height: 40px; padding: 2px;" 
                                   value="<?php echo $header_color; ?>" 
                                   onchange="document.getElementById('header_color').value=this.value; updatePreview();">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="preview-box" style="background: #f8f9fa;">
                            <div class="preview-header" style="background: <?php echo $header_color; ?>;">
                                <i class="fas fa-microchip"></i> Header Preview
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ====== SIDEBAR COLOR ====== -->
            <div class="settings-section">
                <div class="section-title">
                    <i class="fas fa-palette"></i> Sidebar Color
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <p class="text-muted small">Choose a color for the sidebar navigation.</p>
                        <div class="d-flex flex-wrap gap-2" id="sidebarColorOptions">
                            <?php foreach ($color_themes as $color => $name): ?>
                                <div class="color-option <?php echo ($sidebar_color === $color) ? 'selected' : ''; ?>" 
                                     style="background: <?php echo $color; ?>;"
                                     data-color="<?php echo $color; ?>"
                                     onclick="selectColor('sidebar_color', '<?php echo $color; ?>', this)"
                                     title="<?php echo $name; ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="sidebar_color" id="sidebar_color" value="<?php echo $sidebar_color; ?>">
                        <div class="mt-2">
                            <label class="form-label small">Custom Color</label>
                            <input type="color" class="form-control form-control-color" style="width: 60px; height: 40px; padding: 2px;" 
                                   value="<?php echo $sidebar_color; ?>" 
                                   onchange="document.getElementById('sidebar_color').value=this.value; updatePreview();">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="preview-box" style="background: #f8f9fa;">
                            <div class="preview-sidebar" style="background: <?php echo $sidebar_color; ?>;">
                                <i class="fas fa-home"></i> Sidebar
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ====== BACKGROUND COLOR ====== -->
            <div class="settings-section">
                <div class="section-title">
                    <i class="fas fa-palette"></i> Background Color
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <p class="text-muted small">Choose a background color for the main content area.</p>
                        <div class="d-flex flex-wrap gap-2" id="bgColorOptions">
                            <?php 
                            $bg_colors = [
                                '#f8f9fa' => 'Light Gray',
                                '#ffffff' => 'White',
                                '#e8f0fe' => 'Light Blue',
                                '#e8f5e9' => 'Light Green',
                                '#fce4ec' => 'Light Pink',
                                '#fff3e0' => 'Light Orange',
                                '#f3e5f5' => 'Light Purple',
                                '#e0f7fa' => 'Light Cyan',
                                '#f5f5f5' => 'Gray',
                            ];
                            foreach ($bg_colors as $color => $name): 
                            ?>
                                <div class="color-option <?php echo ($background_color === $color) ? 'selected' : ''; ?>" 
                                     style="background: <?php echo $color; ?>; border-color: <?php echo ($background_color === $color) ? '#1a73e8' : '#ddd'; ?>;"
                                     data-color="<?php echo $color; ?>"
                                     onclick="selectColor('background_color', '<?php echo $color; ?>', this)"
                                     title="<?php echo $name; ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="background_color" id="background_color" value="<?php echo $background_color; ?>">
                        <div class="mt-2">
                            <label class="form-label small">Custom Color</label>
                            <input type="color" class="form-control form-control-color" style="width: 60px; height: 40px; padding: 2px;" 
                                   value="<?php echo $background_color; ?>" 
                                   onchange="document.getElementById('background_color').value=this.value; updatePreview();">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="preview-box" style="background: <?php echo $background_color; ?>;">
                            <div style="padding: 20px; text-align: center; color: <?php echo (strpos($background_color, '#f') === 0) ? '#333' : '#fff'; ?>;">
                                <i class="fas fa-file-alt fa-2x"></i>
                                <p class="mt-2">Content Area</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ====== FONT SIZE ====== -->
            <div class="settings-section">
                <div class="section-title">
                    <i class="fas fa-font"></i> Font Size
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <p class="text-muted small">Select your preferred font size for the entire system.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($font_sizes as $size => $label): ?>
                                <div class="font-size-option <?php echo ($font_size === $size) ? 'selected' : ''; ?>" 
                                     style="font-size: <?php echo $size; ?>;"
                                     onclick="selectFontSize('<?php echo $size; ?>', this)">
                                    <?php echo $label; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="font_size" id="font_size" value="<?php echo $font_size; ?>">
                    </div>
                    <div class="col-md-4">
                        <div class="preview-box" style="background: #f8f9fa;">
                            <div style="font-size: <?php echo $font_size; ?>;">
                                <strong>Preview Text</strong>
                                <p style="font-size: <?php echo $font_size; ?>;">This is how text will appear.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ====== SIDEBAR BEHAVIOR ====== -->
            <div class="settings-section">
                <div class="section-title">
                    <i class="fas fa-sliders-h"></i> Sidebar Behavior
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <p class="text-muted small">Choose how the sidebar behaves on desktop.</p>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sidebar_collapsed" id="sidebar_expanded" value="0" <?php echo ($sidebar_collapsed == 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="sidebar_expanded">
                                <i class="fas fa-expand"></i> Expanded - Show icons and labels
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sidebar_collapsed" id="sidebar_collapsed_radio" value="1" <?php echo ($sidebar_collapsed == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="sidebar_collapsed_radio">
                                <i class="fas fa-compress"></i> Collapsed - Show icons only
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="preview-box" style="background: #f8f9fa; text-align: center;">
                            <div style="padding: 10px; background: <?php echo $sidebar_color; ?>; color: white; border-radius: 8px; display: inline-block; width: <?php echo ($sidebar_collapsed == 1) ? '50px' : '150px'; ?>;">
                                <i class="fas fa-home"></i>
                                <?php if ($sidebar_collapsed == 0): ?>
                                    <span>Sidebar</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ====== SUBMIT BUTTONS ====== -->
            <div class="settings-section">
                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <button type="reset" class="btn btn-secondary px-4 py-2" onclick="resetSettings()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <a href="<?php echo BASE_URL; ?>public/<?php echo strtolower($_SESSION['role']); ?>/" class="btn btn-outline-secondary px-4 py-2">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            
        </form>
    </div>
</div>

<!-- Include footer scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ====== SELECT COLOR ======
function selectColor(inputId, color, element) {
    // Update hidden input
    document.getElementById(inputId).value = color;
    
    // Update UI
    const parent = element.parentElement;
    parent.querySelectorAll('.color-option').forEach(el => {
        el.classList.remove('selected');
    });
    element.classList.add('selected');
    
    // Update preview
    updatePreview();
}

// ====== SELECT FONT SIZE ======
function selectFontSize(size, element) {
    document.getElementById('font_size').value = size;
    
    const parent = element.parentElement;
    parent.querySelectorAll('.font-size-option').forEach(el => {
        el.classList.remove('selected');
    });
    element.classList.add('selected');
    
    updatePreview();
}

// ====== UPDATE PREVIEW ======
function updatePreview() {
    const headerColor = document.getElementById('header_color').value;
    const sidebarColor = document.getElementById('sidebar_color').value;
    const bgColor = document.getElementById('background_color').value;
    const fontSize = document.getElementById('font_size').value;
    
    // Update header preview
    const headerPreview = document.querySelector('.preview-header');
    if (headerPreview) {
        headerPreview.style.background = headerColor;
    }
    
    // Update sidebar preview
    const sidebarPreview = document.querySelector('.preview-sidebar');
    if (sidebarPreview) {
        sidebarPreview.style.background = sidebarColor;
    }
    
    // Update background preview
    const bgPreview = document.querySelector('.preview-box[style*="background: #f8f9fa;"]');
    if (bgPreview) {
        bgPreview.style.background = bgColor;
        const content = bgPreview.querySelector('div');
        if (content) {
            const isLight = ['#f8f9fa', '#ffffff', '#e8f0fe', '#e8f5e9', '#fce4ec', '#fff3e0', '#f3e5f5', '#e0f7fa', '#f5f5f5'].includes(bgColor);
            content.style.color = isLight ? '#333' : '#fff';
        }
    }
    
    // Update font size preview
    const fontPreview = document.querySelector('.preview-box .preview-text');
    if (fontPreview) {
        fontPreview.style.fontSize = fontSize;
    }
}

// ====== RESET SETTINGS ======
function resetSettings() {
    if (confirm('Reset all settings to default?')) {
        document.getElementById('header_color').value = '#0d47a1';
        document.getElementById('sidebar_color').value = '#0d47a1';
        document.getElementById('background_color').value = '#f8f9fa';
        document.getElementById('font_size').value = '14px';
        document.querySelector('input[name="sidebar_collapsed"][value="0"]').checked = true;
        
        // Update UI
        document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
        document.querySelectorAll('.font-size-option').forEach(el => el.classList.remove('selected'));
        
        // Update preview
        updatePreview();
    }
}

// ====== FORM SUBMISSION ======
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    // No need to prevent default, form submits normally
});

// ====== INITIAL PREVIEW ======
document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
});
</script>

</body>
</html>