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

// Include header
include __DIR__ . '/../includes/header.php';
?>

<style>
    /* ====== SETTINGS PAGE ====== */
    .settings-page {
        width: 100%;
        margin: 0 auto;
    }
    
    .settings-section {
        background: white;
        border-radius: 16px;
        padding: 15px 30px;
        margin-bottom: 20px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.06);
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.04);
    }
    
    .settings-section:hover {
        box-shadow: 0 4px 25px rgba(0,0,0,0.08);
    }
    
    .settings-section .section-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 18px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #212529;
    }
    
    .settings-section .section-title i {
        color: #1a73e8;
        font-size: 1.1rem;
    }
    
    .settings-section .section-title .badge-auto {
        font-size: 0.6rem;
        background: #28a745;
        color: white;
        padding: 2px 10px;
        border-radius: 20px;
        font-weight: 500;
        margin-left: auto;
    }
    
    /* ====== COLOR OPTIONS ====== */
    .color-option {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 3px solid transparent;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-block;
        margin: 3px;
        position: relative;
    }
    
    .color-option:hover {
        transform: scale(1.12);
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    
    .color-option.selected {
        border-color: #1a73e8;
        box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.25);
        transform: scale(1.08);
    }
    
    .color-option.selected::after {
        content: '✓';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: white;
        font-size: 0.8rem;
        font-weight: 700;
        text-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }
    
    /* ====== FONT SIZE OPTIONS ====== */
    .font-size-option {
        padding: 8px 18px;
        border-radius: 10px;
        border: 2px solid #e9ecef;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-block;
        margin: 4px;
        background: white;
        color: #495057;
        font-weight: 500;
    }
    
    .font-size-option:hover {
        border-color: #1a73e8;
        background: #f8f9fa;
    }
    
    .font-size-option.selected {
        border-color: #1a73e8;
        background: #e8f0fe;
        color: #1a73e8;
        box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.15);
    }
    
    /* ====== PREVIEW BOX ====== */
    .preview-box {
        padding: 12px;
        border-radius: 12px;
        transition: all 0.4s ease;
        border: 2px solid #dee2e6;
        background: #f8f9fa;
        overflow: hidden;
    }
    
    .preview-header-box {
        background: #f8f9fa;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid #e9ecef;
    }
    
    .preview-header-box .preview-header-bar {
        padding: 10px 16px;
        color: white;
        font-weight: 500;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: background 0.4s ease;
        min-height: 48px;
    }
    
    .preview-header-box .preview-header-bar i {
        font-size: 1rem;
    }
    
    .preview-header-box .preview-header-bar .header-dots {
        margin-left: auto;
        display: flex;
        gap: 6px;
    }
    
    .preview-header-box .preview-header-bar .header-dots span {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: rgba(255,255,255,0.3);
    }
    
    .preview-layout {
        display: flex;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid #e9ecef;
        min-height: 80px;
        background: white;
    }
    
    .preview-layout .preview-sidebar-panel {
        width: 35%;
        padding: 12px 14px;
        color: white;
        display: flex;
        flex-direction: column;
        gap: 6px;
        transition: background 0.4s ease;
        min-height: 80px;
        flex-shrink: 0;
    }
    
    .preview-layout .preview-sidebar-panel .sidebar-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 4px 8px;
        border-radius: 4px;
        background: rgba(255,255,255,0.1);
        font-size: 0.7rem;
        font-weight: 400;
    }
    
    .preview-layout .preview-sidebar-panel .sidebar-item i {
        font-size: 0.7rem;
        width: 16px;
        text-align: center;
    }
    
    .preview-layout .preview-sidebar-panel .sidebar-item.active {
        background: rgba(255,255,255,0.2);
        font-weight: 500;
    }
    
    .preview-layout .preview-content-panel {
        flex: 1;
        padding: 12px 16px;
        background: #f8f9fa;
        display: flex;
        flex-direction: column;
        gap: 6px;
        transition: background 0.4s ease;
        min-height: 80px;
    }
    
    .preview-layout .preview-content-panel .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 6px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .preview-layout .preview-content-panel .content-header .content-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: #212529;
    }
    
    .preview-layout .preview-content-panel .content-header .content-dots {
        display: flex;
        gap: 4px;
    }
    
    .preview-layout .preview-content-panel .content-header .content-dots span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #dee2e6;
    }
    
    .preview-layout .preview-content-panel .content-card {
        background: white;
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid #e9ecef;
        font-size: 0.7rem;
        color: #495057;
    }
    
    .preview-layout .preview-content-panel .content-card .card-row {
        display: flex;
        justify-content: space-between;
        padding: 2px 0;
    }
    
    .preview-layout .preview-content-panel .content-card .card-row .label {
        color: #adb5bd;
    }
    
    .preview-label {
        font-size: 0.6rem;
        color: #adb5bd;
        text-align: center;
        margin-top: 6px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    
    /* ====== SIDEBAR BEHAVIOR PREVIEW ====== */
    .sidebar-behavior-preview {
        padding: 10px;
        background: #f8f9fa;
        border-radius: 10px;
        display: inline-block;
        transition: all 0.4s ease;
        border: 1px solid #e9ecef;
    }
    
    .sidebar-behavior-preview .sb-preview {
        padding: 10px 14px;
        background: <?php echo $sidebar_color; ?>;
        color: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.4s ease;
        min-width: 50px;
    }
    
    .sidebar-behavior-preview .sb-preview i {
        font-size: 1rem;
    }
    
    .sidebar-behavior-preview .sb-preview span {
        font-size: 0.75rem;
        transition: all 0.4s ease;
    }
    
    /* ====== TOAST ====== */
    .settings-toast {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 9999;
        background: white;
        border-radius: 16px;
        padding: 16px 25px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        display: none;
        align-items: center;
        gap: 14px;
        min-width: 280px;
        max-width: 420px;
        animation: slideInRight 0.4s ease;
        border-left: 5px solid #28a745;
    }
    
    .settings-toast.show {
        display: flex;
    }
    
    .settings-toast .toast-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #28a745;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: white;
        font-size: 1.2rem;
    }
    
    .settings-toast .toast-content {
        flex: 1;
    }
    
    .settings-toast .toast-content .toast-title {
        font-weight: 700;
        font-size: 0.95rem;
        color: #28a745;
        margin-bottom: 1px;
    }
    
    .settings-toast .toast-content .toast-message {
        font-size: 0.85rem;
        color: #495057;
        margin: 0;
    }
    
    .settings-toast .toast-close {
        background: transparent;
        border: none;
        color: #adb5bd;
        font-size: 1.1rem;
        cursor: pointer;
        padding: 4px;
        transition: all 0.3s;
    }
    
    .settings-toast .toast-close:hover {
        color: #495057;
        transform: scale(1.1);
    }
    
    @keyframes slideInRight {
        from { transform: translateX(100px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100px); opacity: 0; }
    }
    
    /* ====== AUTO-SAVE INDICATOR ====== */
    .save-indicator {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        color: #6c757d;
        padding: 4px 12px;
        border-radius: 20px;
        background: #f8f9fa;
        transition: all 0.3s;
    }
    
    .save-indicator.saving {
        color: #ffc107;
        background: #fff3cd;
    }
    
    .save-indicator.saved {
        color: #28a745;
        background: #d4edda;
    }
    
    .save-indicator i {
        font-size: 0.7rem;
    }
    
    /* ====== RESPONSIVE ====== */
    @media (max-width: 992px) {
        .settings-section {
            padding: 20px;
        }
        .preview-layout .preview-sidebar-panel {
            width: 40%;
        }
    }
    
    @media (max-width: 768px) {
        .settings-section {
            padding: 15px;
            border-radius: 12px;
        }
        .settings-section .section-title {
            font-size: 0.9rem;
        }
        .color-option {
            width: 34px;
            height: 34px;
            margin: 2px;
        }
        .font-size-option {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        .preview-layout {
            flex-direction: column;
            min-height: auto;
        }
        .preview-layout .preview-sidebar-panel {
            width: 100%;
            min-height: 60px;
            flex-direction: row;
            flex-wrap: wrap;
            padding: 10px 12px;
        }
        .preview-layout .preview-sidebar-panel .sidebar-item {
            font-size: 0.65rem;
            padding: 2px 6px;
        }
        .preview-layout .preview-content-panel {
            min-height: 60px;
            padding: 10px 12px;
        }
        .settings-toast {
            top: 70px;
            right: 10px;
            left: 10px;
            min-width: auto;
            padding: 14px 18px;
        }
        .settings-toast .toast-icon {
            width: 34px;
            height: 34px;
            font-size: 1rem;
        }
        .preview-header-box .preview-header-bar {
            font-size: 0.75rem;
            padding: 8px 12px;
            min-height: 40px;
        }
    }
    
    @media (max-width: 576px) {
        .settings-section {
            padding: 12px;
        }
        .color-option {
            width: 30px;
            height: 30px;
        }
        .font-size-option {
            padding: 4px 10px;
            font-size: 0.7rem;
        }
        .settings-toast {
            top: 60px;
            right: 5px;
            left: 5px;
            padding: 12px 14px;
            border-radius: 12px;
        }
        .settings-toast .toast-content .toast-title {
            font-size: 0.85rem;
        }
        .settings-toast .toast-content .toast-message {
            font-size: 0.75rem;
        }
        .save-indicator {
            font-size: 0.65rem;
            padding: 2px 8px;
        }
    }
</style>

<!-- ====== SUCCESS TOAST ====== -->
<div class="settings-toast" id="settingsToast">
    <div class="toast-icon">
        <i class="fas fa-check"></i>
    </div>
    <div class="toast-content">
        <div class="toast-title">✅ Settings Saved!</div>
        <p class="toast-message" id="toastMessage">Your changes have been applied.</p>
    </div>
    <button class="toast-close" onclick="closeToast()">
        <i class="fas fa-times"></i>
    </button>
</div>

<!-- ====== MAIN CONTENT ====== -->
<div class="main-content">
    <div class="container-fluid settings-page">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <h4 class="fw-bold mb-0"><i class="fas fa-cog text-primary"></i> Settings</h4>
                <small class="text-muted">Customize your dashboard appearance</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="save-indicator saved" id="saveIndicator">
                    <i class="fas fa-check-circle"></i>
                    <span id="saveText">Auto-save enabled</span>
                </span>
               
            </div>
        </div>
        
        <!-- Settings Form -->
        <form action="update_settings.php" method="POST" id="settingsForm">
            
            <!-- ====== HEADER COLOR ====== -->
            <div class="settings-section">
                <div class="section-title">
                    <i class="fas fa-palette"></i> Header Color
                    <span class="badge-auto">Auto-save</span>
                </div>
                <div class="row align-items-center">
                    <div class="col-lg-8 col-md-7">
                        <p class="text-muted small mb-2">Choose a color for the top header bar.</p>
                        <div class="d-flex flex-wrap gap-1" id="headerColorOptions">
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
                        <div class="mt-2 d-flex align-items-center gap-2">
                            <label class="form-label small mb-0">Custom:</label>
                            <input type="color" class="form-control form-control-color" style="width: 50px; height: 34px; padding: 2px; border-radius: 8px; cursor: pointer;" 
                                   value="<?php echo $header_color; ?>" 
                                   onchange="document.getElementById('header_color').value=this.value; updatePreview(); autoSave();">
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-5 mt-3 mt-md-0">
                        <div class="preview-box">
                            <div class="preview-header-box">
                                <div class="preview-header-bar" id="headerPreview" style="background: <?php echo $header_color; ?>;">
                                    <i class="fas fa-microchip"></i>
                                    <span>Header Preview</span>
                                    <div class="header-dots">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-label">Live Preview</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ====== SIDEBAR COLOR ====== -->
            <div class="settings-section">
                <div class="section-title">
                    <i class="fas fa-palette"></i> Sidebar Color
                    <span class="badge-auto">Auto-save</span>
                </div>
                <div class="row align-items-center">
                    <div class="col-lg-8 col-md-7">
                        <p class="text-muted small mb-2">Choose a color for the sidebar navigation.</p>
                        <div class="d-flex flex-wrap gap-1" id="sidebarColorOptions">
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
                        <div class="mt-2 d-flex align-items-center gap-2">
                            <label class="form-label small mb-0">Custom:</label>
                            <input type="color" class="form-control form-control-color" style="width: 50px; height: 34px; padding: 2px; border-radius: 8px; cursor: pointer;" 
                                   value="<?php echo $sidebar_color; ?>" 
                                   onchange="document.getElementById('sidebar_color').value=this.value; updatePreview(); autoSave();">
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-5 mt-3 mt-md-0">
                        <div class="preview-box">
                            <div class="preview-layout">
                                <div class="preview-sidebar-panel" id="sidebarPreview" style="background: <?php echo $sidebar_color; ?>;">
                                    <div class="sidebar-item active">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </div>
                                    <div class="sidebar-item">
                                        <i class="fas fa-laptop"></i> Assets
                                    </div>
                                    <div class="sidebar-item">
                                        <i class="fas fa-users"></i> Users
                                    </div>
                                    <div class="sidebar-item">
                                        <i class="fas fa-cog"></i> Settings
                                    </div>
                                </div>
                                <div class="preview-content-panel" id="contentPreview" style="background: <?php echo $background_color; ?>;">
                                    <div class="content-header">
                                        <span class="content-title">Dashboard</span>
                                        <div class="content-dots">
                                            <span></span>
                                            <span></span>
                                            <span></span>
                                        </div>
                                    </div>
                                    <div class="content-card">
                                        <div class="card-row">
                                            <span class="label">Total Users</span>
                                            <span><strong>24</strong></span>
                                        </div>
                                        <div class="card-row">
                                            <span class="label">Active Requests</span>
                                            <span><strong>5</strong></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-label">Live Preview</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ====== BACKGROUND COLOR ====== -->
            <div class="settings-section">
                <div class="section-title">
                    <i class="fas fa-palette"></i> Background Color
                    <span class="badge-auto">Auto-save</span>
                </div>
                <div class="row align-items-center">
                    <div class="col-lg-8 col-md-7">
                        <p class="text-muted small mb-2">Choose a background color for the main content area.</p>
                        <div class="d-flex flex-wrap gap-1" id="bgColorOptions">
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
                        <div class="mt-2 d-flex align-items-center gap-2">
                            <label class="form-label small mb-0">Custom:</label>
                            <input type="color" class="form-control form-control-color" style="width: 50px; height: 34px; padding: 2px; border-radius: 8px; cursor: pointer;" 
                                   value="<?php echo $background_color; ?>" 
                                   onchange="document.getElementById('background_color').value=this.value; updatePreview(); autoSave();">
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-5 mt-3 mt-md-0">
                        <div class="preview-box">
                            <div class="preview-layout" style="border: 1px solid #e9ecef; border-radius: 10px; overflow: hidden; min-height: 80px; background: white;">
                                <div class="preview-sidebar-panel" style="width: 35%; background: <?php echo $sidebar_color; ?>; padding: 10px 12px; min-height: 80px; display: flex; flex-direction: column; gap: 4px;">
                                    <div style="font-size: 0.6rem; opacity: 0.6; color: white;">Menu</div>
                                    <div style="display: flex; align-items: center; gap: 6px; padding: 3px 6px; border-radius: 4px; background: rgba(255,255,255,0.1); font-size: 0.65rem; color: white;">
                                        <i class="fas fa-home" style="font-size: 0.6rem;"></i> Home
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 6px; padding: 3px 6px; border-radius: 4px; font-size: 0.65rem; color: rgba(255,255,255,0.7);">
                                        <i class="fas fa-cog" style="font-size: 0.6rem;"></i> Settings
                                    </div>
                                </div>
                                <div class="preview-content-panel" id="bgContentPreview" style="flex: 1; padding: 10px 14px; background: <?php echo $background_color; ?>; min-height: 80px; display: flex; flex-direction: column; justify-content: center;">
                                    <div style="text-align: center; color: <?php echo (strpos($background_color, '#f') === 0 || $background_color === '#ffffff') ? '#333' : '#fff'; ?>;">
                                        <i class="fas fa-file-alt" style="font-size: 1.2rem; display: block; margin-bottom: 4px;"></i>
                                        <span style="font-size: 0.7rem;">Content Area</span>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-label">Live Preview</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ====== FONT SIZE ====== -->
            <div class="settings-section">
                <div class="section-title">
                    <i class="fas fa-font"></i> Font Size
                    <span class="badge-auto">Auto-save</span>
                </div>
                <div class="row align-items-center">
                    <div class="col-lg-8 col-md-7">
                        <p class="text-muted small mb-2">Select your preferred font size for the entire system.</p>
                        <div class="d-flex flex-wrap gap-2" id="fontSizeOptions">
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
                    <div class="col-lg-4 col-md-5 mt-3 mt-md-0">
                        <div class="preview-box" style="min-height: 60px; display: flex; flex-direction: column; justify-content: center;">
                            <div style="font-size: <?php echo $font_size; ?>; text-align: center; padding: 5px 0;">
                                <strong style="font-size: <?php echo $font_size; ?>;">Preview Text</strong>
                                <p style="font-size: <?php echo $font_size; ?>; margin: 2px 0 0 0; color: #6c757d;">This is how text will appear.</p>
                            </div>
                            <div class="preview-label">Live Preview</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ====== SIDEBAR BEHAVIOR ====== -->
            <div class="settings-section">
                <div class="section-title">
                    <i class="fas fa-sliders-h"></i> Sidebar Behavior
                    <span class="badge-auto">Auto-save</span>
                </div>
                <div class="row align-items-center">
                    <div class="col-lg-8 col-md-7">
                        <p class="text-muted small mb-2">Choose how the sidebar behaves on desktop.</p>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="sidebar_collapsed" id="sidebar_expanded" value="0" 
                                       <?php echo ($sidebar_collapsed == 0) ? 'checked' : ''; ?>
                                       onchange="updatePreview(); autoSave();">
                                <label class="form-check-label" for="sidebar_expanded">
                                    <i class="fas fa-expand text-success"></i> Expanded
                                    <small class="text-muted d-block">Show icons and labels</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="sidebar_collapsed" id="sidebar_collapsed_radio" value="1" 
                                       <?php echo ($sidebar_collapsed == 1) ? 'checked' : ''; ?>
                                       onchange="updatePreview(); autoSave();">
                                <label class="form-check-label" for="sidebar_collapsed_radio">
                                    <i class="fas fa-compress text-warning"></i> Collapsed
                                    <small class="text-muted d-block">Show icons only</small>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-5 mt-3 mt-md-0">
                        <div class="preview-box" style="min-height: 60px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <div class="sidebar-behavior-preview">
                                <div class="sb-preview" id="sidebarBehaviorPreview" 
                                     style="width: <?php echo ($sidebar_collapsed == 1) ? '50px' : '140px'; ?>; background: <?php echo $sidebar_color; ?>;">
                                    <i class="fas fa-home"></i>
                                    <span id="sbLabel" style="display: <?php echo ($sidebar_collapsed == 1) ? 'none' : 'inline'; ?>;">Sidebar</span>
                                </div>
                            </div>
                            <div class="preview-label">Live Preview</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ====== SAVE BUTTONS ====== -->
            <div class="settings-section" style="background: #f8f9fa; border: 2px dashed #dee2e6;">
                <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small">
                            <i class="fas fa-sync-alt fa-spin me-1" id="saveSpinner" style="display: none;"></i>
                            <span id="saveStatusText">Settings are saved automatically</span>
                        </span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="resetSettings()">
                            <i class="fas fa-undo"></i> Reset Defaults
                        </button>
                    </div>
                </div>
            </div>
            
        </form>
    </div>
</div>

<script>
// ====== SELECT COLOR ======
function selectColor(inputId, color, element) {
    document.getElementById(inputId).value = color;
    
    const parent = element.parentElement;
    parent.querySelectorAll('.color-option').forEach(el => {
        el.classList.remove('selected');
    });
    element.classList.add('selected');
    
    updatePreview();
    autoSave();
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
    autoSave();
}

// ====== UPDATE PREVIEW ======
function updatePreview() {
    const headerColor = document.getElementById('header_color').value;
    const sidebarColor = document.getElementById('sidebar_color').value;
    const bgColor = document.getElementById('background_color').value;
    const fontSize = document.getElementById('font_size').value;
    const collapsed = document.querySelector('input[name="sidebar_collapsed"]:checked')?.value || '0';
    
    // Header Preview
    const headerPreview = document.getElementById('headerPreview');
    if (headerPreview) {
        headerPreview.style.background = headerColor;
    }
    
    // Sidebar Preview
    const sidebarPreview = document.getElementById('sidebarPreview');
    if (sidebarPreview) {
        sidebarPreview.style.background = sidebarColor;
    }
    
    // Content Preview
    const contentPreview = document.getElementById('contentPreview');
    if (contentPreview) {
        contentPreview.style.background = bgColor;
    }
    
    // Background Content Preview
    const bgContentPreview = document.getElementById('bgContentPreview');
    if (bgContentPreview) {
        bgContentPreview.style.background = bgColor;
        const isLight = ['#f8f9fa', '#ffffff', '#e8f0fe', '#e8f5e9', '#fce4ec', '#fff3e0', '#f3e5f5', '#e0f7fa', '#f5f5f5'].includes(bgColor);
        const textEl = bgContentPreview.querySelector('div');
        if (textEl) {
            textEl.style.color = isLight ? '#333' : '#fff';
        }
    }
    
    // Sidebar Behavior Preview
    const sbPreview = document.getElementById('sidebarBehaviorPreview');
    const sbLabel = document.getElementById('sbLabel');
    if (sbPreview) {
        sbPreview.style.width = collapsed === '1' ? '50px' : '140px';
        sbPreview.style.background = sidebarColor;
        if (sbLabel) {
            sbLabel.style.display = collapsed === '1' ? 'none' : 'inline';
        }
    }
}

// ====== AUTO-SAVE ======
let saveTimeout = null;
let isSaving = false;

function autoSave() {
    if (saveTimeout) {
        clearTimeout(saveTimeout);
    }
    
    const indicator = document.getElementById('saveIndicator');
    const statusText = document.getElementById('saveStatusText');
    const spinner = document.getElementById('saveSpinner');
    
    indicator.className = 'save-indicator saving';
    document.getElementById('saveText').textContent = 'Saving...';
    spinner.style.display = 'inline-block';
    statusText.textContent = 'Saving changes...';
    
    saveTimeout = setTimeout(function() {
        performAutoSave();
    }, 600);
}

function performAutoSave() {
    if (isSaving) return;
    isSaving = true;
    
    const form = document.getElementById('settingsForm');
    const formData = new FormData(form);
    formData.append('auto_save', '1');
    
    fetch('update_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        isSaving = false;
        
        const indicator = document.getElementById('saveIndicator');
        const statusText = document.getElementById('saveStatusText');
        const spinner = document.getElementById('saveSpinner');
        const toast = document.getElementById('settingsToast');
        const toastMsg = document.getElementById('toastMessage');
        
        if (data.success) {
            indicator.className = 'save-indicator saved';
            document.getElementById('saveText').textContent = 'Saved';
            statusText.textContent = 'All changes saved successfully';
            spinner.style.display = 'none';
            
            toastMsg.textContent = 'Your changes have been applied successfully.';
            toast.classList.add('show');
            
            setTimeout(function() {
                closeToast();
            }, 2000);
        } else {
            indicator.className = 'save-indicator';
            document.getElementById('saveText').textContent = 'Auto-save enabled';
            statusText.textContent = 'Failed to save. Please try again.';
            spinner.style.display = 'none';
        }
    })
    .catch(error => {
        isSaving = false;
        const indicator = document.getElementById('saveIndicator');
        const spinner = document.getElementById('saveSpinner');
        indicator.className = 'save-indicator';
        document.getElementById('saveText').textContent = 'Auto-save enabled';
        spinner.style.display = 'none';
        console.error('Auto-save error:', error);
    });
}

// ====== CLOSE TOAST ======
function closeToast() {
    const toast = document.getElementById('settingsToast');
    if (toast) {
        toast.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(function() {
            toast.classList.remove('show');
            toast.style.animation = '';
        }, 300);
    }
}

// ====== RESET SETTINGS - WITH AUTO REFRESH ======
function resetSettings() {
    if (confirm('Reset all settings to default?')) {
        // Show saving indicator
        const indicator = document.getElementById('saveIndicator');
        const statusText = document.getElementById('saveStatusText');
        const spinner = document.getElementById('saveSpinner');
        
        indicator.className = 'save-indicator saving';
        document.getElementById('saveText').textContent = 'Resetting...';
        spinner.style.display = 'inline-block';
        statusText.textContent = 'Resetting to default settings...';
        
        // Prepare data
        const formData = new FormData();
        formData.append('header_color', '#0d47a1');
        formData.append('sidebar_color', '#0d47a1');
        formData.append('background_color', '#f8f9fa');
        formData.append('font_size', '14px');
        formData.append('sidebar_collapsed', '0');
        formData.append('auto_save', '1');
        formData.append('reset', '1');
        
        // Send reset request
        fetch('update_settings.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success and reload
                const toast = document.getElementById('settingsToast');
                const toastMsg = document.getElementById('toastMessage');
                toastMsg.textContent = 'Settings reset to default! Refreshing...';
                toast.classList.add('show');
                
                setTimeout(function() {
                    window.location.reload();
                }, 800);
            } else {
                alert('Failed to reset settings. Please try again.');
                indicator.className = 'save-indicator saved';
                document.getElementById('saveText').textContent = 'Auto-save enabled';
                spinner.style.display = 'none';
                statusText.textContent = 'Reset failed';
            }
        })
        .catch(error => {
            alert('Error resetting settings. Please try again.');
            indicator.className = 'save-indicator saved';
            document.getElementById('saveText').textContent = 'Auto-save enabled';
            spinner.style.display = 'none';
            statusText.textContent = 'Error occurred';
            console.error('Reset error:', error);
        });
    }
}

// ====== INITIAL PREVIEW ======
document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
});

console.log('✅ Settings Page Loaded!');
console.log('🎨 Auto-save enabled');
console.log('🔄 Reset will auto-refresh the page');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>