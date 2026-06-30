<?php
// public/includes/header.php - Shared header for all dashboards

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/profile_helper.php';
require_once __DIR__ . '/notification_helper.php';

// Get user settings
$settings = getUserSettings($_SESSION['user_id']);

// Get unread notifications count
$unread = getUnreadNotificationsCount($_SESSION['user_id']);

// Get recent notifications (for dropdown)
$recent_notifications = getRecentNotifications($_SESSION['user_id'], 5);

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Get user data for avatar
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? 'User';
$last_name = $_SESSION['last_name'] ?? '';
$role = $_SESSION['role'] ?? 'Staff';
$profile_picture = getProfilePicture($user_id);
$initials = getUserInitials($user_id);
$full_name = trim($first_name . ' ' . $last_name);

// Get sidebar collapsed state from settings
$sidebar_collapsed = isset($settings['sidebar_collapsed']) ? (int)$settings['sidebar_collapsed'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ====== CSS VARIABLES ====== */
        :root {
            --header-color: <?php echo $settings['header_color'] ?? '#0d47a1'; ?>;
            --sidebar-color: <?php echo $settings['sidebar_color'] ?? '#0d47a1'; ?>;
            --bg-color: <?php echo $settings['background_color'] ?? '#f8f9fa'; ?>;
            --font-size: <?php echo $settings['font_size'] ?? '14px'; ?>;
            --sidebar-collapsed: <?php echo $sidebar_collapsed; ?>;
        }
        
        * {
            font-size: var(--font-size);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        /* ====== HEADER ====== */
        .top-header {
            background: var(--header-color);
            padding: 8px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            height: 65px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 15px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        }
        
        .top-header .brand {
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .top-header .brand i {
            font-size: 1.5rem;
        }
        
        .top-header .brand .brand-text {
            display: inline-block;
        }
        
        /* ====== LEFT SECTION ====== */
        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        /* ====== HAMBURGER BUTTON ====== */
        .hamburger-btn {
            background: transparent;
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 5px 10px;
            cursor: pointer;
            transition: all 0.3s;
            display: block;
            border-radius: 8px;
        }
        
        .hamburger-btn:hover {
            background: rgba(255,255,255,0.15);
            transform: scale(1.05);
        }
        
        /* ====== RIGHT SECTION ====== */
        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* ====== NOTIFICATION ICON ====== */
        .btn-notification {
            color: rgba(255,255,255,0.85);
            background: transparent;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.3s;
            position: relative;
            font-size: 1.1rem;
        }
        
        .btn-notification:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        .btn-notification .badge-notif {
            position: absolute;
            top: 2px;
            right: 2px;
            font-size: 0.55rem;
            padding: 2px 6px;
            border-radius: 50%;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dc3545;
            color: white;
            font-weight: 600;
            animation: pulse 1.5s infinite;
        }
        
        /* ====== NOTIFICATION DROPDOWN ====== */
        .notification-dropdown {
            min-width: 380px;
            max-width: 420px;
            padding: 0;
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin-top: 10px;
            max-height: 500px;
            overflow: hidden;
        }
        
        .notification-dropdown .dropdown-header {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
        }
        
        .notification-dropdown .dropdown-header h6 {
            margin: 0;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .notification-dropdown .dropdown-header .mark-all {
            font-size: 0.75rem;
            color: #1a73e8;
            text-decoration: none;
            cursor: pointer;
        }
        
        .notification-dropdown .dropdown-header .mark-all:hover {
            text-decoration: underline;
        }
        
        .notification-dropdown .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }
        
        .notification-dropdown .notification-item {
            padding: 12px 20px;
            border-bottom: 1px solid #f5f5f5;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        
        .notification-dropdown .notification-item:hover {
            background: #f8f9fa;
        }
        
        .notification-dropdown .notification-item.unread {
            background: #e8f0fe;
        }
        
        .notification-dropdown .notification-item.unread:hover {
            background: #dce6f5;
        }
        
        .notification-dropdown .notification-item .notif-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.9rem;
        }
        
        .notification-dropdown .notification-item .notif-icon.info {
            background: #dbeafe;
            color: #1a73e8;
        }
        
        .notification-dropdown .notification-item .notif-icon.success {
            background: #d4edda;
            color: #28a745;
        }
        
        .notification-dropdown .notification-item .notif-icon.warning {
            background: #fff3cd;
            color: #ffc107;
        }
        
        .notification-dropdown .notification-item .notif-icon.danger {
            background: #f8d7da;
            color: #dc3545;
        }
        
        .notification-dropdown .notification-item .notif-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-dropdown .notification-item .notif-content .notif-title {
            font-weight: 600;
            font-size: 0.85rem;
            color: #212529;
            margin-bottom: 2px;
        }
        
        .notification-dropdown .notification-item .notif-content .notif-message {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 2px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .notification-dropdown .notification-item .notif-content .notif-time {
            font-size: 0.65rem;
            color: #adb5bd;
        }
        
        .notification-dropdown .notification-item .notif-badge {
            flex-shrink: 0;
            margin-top: 5px;
        }
        
        .notification-dropdown .notification-item .notif-badge .badge {
            font-size: 0.6rem;
            padding: 2px 8px;
        }
        
        .notification-dropdown .dropdown-footer {
            padding: 10px 20px;
            border-top: 1px solid #f0f0f0;
            text-align: center;
            background: #f8f9fa;
        }
        
        .notification-dropdown .dropdown-footer a {
            font-size: 0.85rem;
            color: #1a73e8;
            text-decoration: none;
        }
        
        .notification-dropdown .dropdown-footer a:hover {
            text-decoration: underline;
        }
        
        .notification-dropdown .empty-notifications {
            padding: 30px 20px;
            text-align: center;
        }
        
        .notification-dropdown .empty-notifications i {
            font-size: 2.5rem;
            color: #dee2e6;
            margin-bottom: 10px;
        }
        
        .notification-dropdown .empty-notifications p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        /* ====== USER AVATAR ====== */
        .user-avatar-wrapper {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 12px 4px 4px;
            border-radius: 50px;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .user-avatar-wrapper:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
        }
        
        .user-avatar-wrapper .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            flex-shrink: 0;
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            border: 2px solid rgba(255,255,255,0.3);
            overflow: hidden;
            transition: all 0.3s;
            text-transform: uppercase;
        }
        
        .user-avatar-wrapper .avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-avatar-wrapper .avatar-circle.has-image {
            border-color: rgba(255,255,255,0.5);
        }
        
        .user-avatar-wrapper .user-info-text {
            color: white;
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        
        .user-avatar-wrapper .user-info-text .user-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .user-avatar-wrapper .user-info-text .user-role {
            font-size: 0.65rem;
            opacity: 0.8;
            font-weight: 400;
        }
        
        .user-avatar-wrapper .dropdown-arrow {
            color: rgba(255,255,255,0.6);
            font-size: 0.7rem;
            transition: all 0.3s;
            margin-left: 2px;
        }
        
        .user-avatar-wrapper:hover .dropdown-arrow {
            color: white;
        }
        
        /* ====== DROPDOWN MENU ====== */
        .user-dropdown-menu {
            min-width: 240px;
            padding: 8px 0;
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin-top: 10px;
        }
        
        .user-dropdown-menu .dropdown-header {
            padding: 12px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-dropdown-menu .dropdown-header .mini-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            color: white;
            flex-shrink: 0;
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            overflow: hidden;
            text-transform: uppercase;
        }
        
        .user-dropdown-menu .dropdown-header .mini-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-dropdown-menu .dropdown-header .mini-info .mini-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #212529;
        }
        
        .user-dropdown-menu .dropdown-header .mini-info .mini-email {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .user-dropdown-menu .dropdown-item {
            padding: 10px 20px;
            font-size: 0.85rem;
            color: #212529;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-dropdown-menu .dropdown-item:hover {
            background: #f8f9fa;
        }
        
        .user-dropdown-menu .dropdown-item i {
            width: 20px;
            text-align: center;
            font-size: 1rem;
            color: #6c757d;
        }
        
        .user-dropdown-menu .dropdown-item:hover i {
            color: #1a73e8;
        }
        
        .user-dropdown-menu .dropdown-item.text-danger i {
            color: #dc3545;
        }
        
        .user-dropdown-menu .dropdown-divider {
            margin: 6px 0;
        }
        
        /* ====== SIDEBAR ====== */
        .sidebar-wrapper {
            position: fixed;
            top: 65px;
            left: 0;
            bottom: 0;
            width: 250px;
            background: var(--sidebar-color);
            z-index: 1040;
            transition: all 0.3s ease;
            overflow-y: auto;
            overflow-x: hidden;
            padding-top: 10px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-wrapper.collapsed {
            width: 70px;
        }
        
        .sidebar-wrapper .sidebar-nav {
            display: flex;
            flex-direction: column;
            padding: 0 10px;
        }
        
        .sidebar-wrapper .sidebar-nav .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            margin-bottom: 2px;
            white-space: nowrap;
            position: relative;
        }
        
        .sidebar-wrapper .sidebar-nav .nav-item:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        .sidebar-wrapper .sidebar-nav .nav-item.active {
            background: rgba(255,255,255,0.2);
            color: white;
            font-weight: 600;
        }
        
        .sidebar-wrapper .sidebar-nav .nav-item i {
            font-size: 1.2rem;
            min-width: 24px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .sidebar-wrapper .sidebar-nav .nav-item .nav-label {
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .sidebar-wrapper.collapsed .sidebar-nav .nav-item .nav-label {
            display: none;
        }
        
        .sidebar-wrapper.collapsed .sidebar-nav .nav-item {
            justify-content: center;
            padding: 12px;
        }
        
        .sidebar-wrapper.collapsed .sidebar-nav .nav-item i {
            font-size: 1.4rem;
        }
        
        /* Tooltip for collapsed sidebar */
        .sidebar-wrapper.collapsed .sidebar-nav .nav-item:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 75px;
            top: 50%;
            transform: translateY(-50%);
            background: #333;
            color: white;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            white-space: nowrap;
            z-index: 1060;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        /* ====== MAIN CONTENT ====== */
        .main-content {
            margin-top: 65px;
            margin-left: 250px;
            padding: 25px 30px;
            min-height: calc(100vh - 65px);
            transition: all 0.3s ease;
            background-color: var(--bg-color);
        }
        
        .main-content.expanded {
            margin-left: 70px;
        }
        
        /* ====== SIDEBAR OVERLAY ====== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 65px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1039;
        }
        
        .sidebar-overlay.show {
            display: block;
        }
        
        /* ====== ANIMATIONS ====== */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .user-dropdown-menu, .notification-dropdown {
            animation: fadeInDown 0.2s ease;
        }
        
        /* ====== RESPONSIVE ====== */
        @media (max-width: 992px) {
            .sidebar-wrapper {
                left: -280px;
                width: 280px;
                box-shadow: 2px 0 20px rgba(0,0,0,0.3);
            }
            
            .sidebar-wrapper.show {
                left: 0;
            }
            
            .sidebar-wrapper .sidebar-nav .nav-item .nav-label {
                display: inline !important;
            }
            
            .sidebar-wrapper .sidebar-nav .nav-item {
                justify-content: flex-start !important;
                padding: 12px 16px !important;
            }
            
            .sidebar-wrapper .sidebar-nav .nav-item i {
                font-size: 1.2rem !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 15px;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
            
            .top-header .brand .brand-text {
                font-size: 0.95rem;
            }
            
            .user-avatar-wrapper .user-info-text {
                display: none;
            }
            
            .notification-dropdown {
                min-width: 320px;
                right: -60px;
            }
        }
        
        @media (max-width: 768px) {
            .top-header {
                padding: 6px 12px;
                height: 60px;
            }
            
            .top-header .brand {
                font-size: 1rem;
            }
            
            .top-header .brand i {
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-top: 60px;
                padding: 12px;
            }
            
            .sidebar-wrapper {
                top: 60px;
                width: 270px;
            }
            
            .sidebar-overlay {
                top: 60px;
            }
            
            .hamburger-btn {
                font-size: 1.3rem;
                padding: 4px 8px;
            }
            
            .user-avatar-wrapper .avatar-circle {
                width: 35px;
                height: 35px;
                font-size: 0.8rem;
            }
            
            .user-avatar-wrapper .dropdown-arrow {
                display: none;
            }
            
            .btn-notification {
                padding: 6px 8px;
                font-size: 1rem;
            }
            
            .btn-notification .badge-notif {
                font-size: 0.5rem;
                min-width: 16px;
                height: 16px;
                top: 0;
                right: 0;
            }
            
            .notification-dropdown {
                min-width: 280px;
                right: -80px;
                max-width: 340px;
            }
        }
        
        @media (max-width: 480px) {
            .top-header {
                padding: 4px 10px;
                height: 55px;
            }
            
            .top-header .brand {
                font-size: 0.85rem;
            }
            
            .top-header .brand i {
                font-size: 1rem;
            }
            
            .main-content {
                margin-top: 55px;
                padding: 10px;
            }
            
            .sidebar-wrapper {
                top: 55px;
            }
            
            .sidebar-overlay {
                top: 55px;
            }
            
            .user-avatar-wrapper .avatar-circle {
                width: 32px;
                height: 32px;
                font-size: 0.7rem;
            }
            
            .user-avatar-wrapper {
                padding: 2px 6px 2px 2px;
            }
            
            .notification-dropdown {
                min-width: 260px;
                right: -100px;
                max-width: 300px;
            }
        }
        
        /* ====== SCROLLBAR ====== */
        .sidebar-wrapper::-webkit-scrollbar,
        .notification-list::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar-wrapper::-webkit-scrollbar-track,
        .notification-list::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
        }
        
        .sidebar-wrapper::-webkit-scrollbar-thumb,
        .notification-list::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 10px;
        }
        
        .sidebar-wrapper::-webkit-scrollbar-thumb:hover,
        .notification-list::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
    </style>
</head>
<body>

<!-- ====== SIDEBAR OVERLAY (Mobile) ====== -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- ====== HEADER ====== -->
<header class="top-header">
    <!-- Left Section -->
    <div class="header-left">
        <!-- Hamburger Button -->
        <button class="hamburger-btn" onclick="toggleSidebar()" id="hamburgerBtn" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Brand -->
        <a href="<?php echo BASE_URL; ?>public/<?php echo strtolower($role); ?>/" class="brand">
            <i class="fas fa-microchip"></i>
            <span class="brand-text"><?php echo APP_SHORT; ?></span>
        </a>
    </div>
    
    <!-- Right Section -->
    <div class="header-right">
        <!-- Notifications -->
        <div class="dropdown">
            <button class="btn-notification" id="notificationBell" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($unread > 0): ?>
                    <span class="badge-notif" id="notifBadge"><?php echo $unread; ?></span>
                <?php endif; ?>
            </button>
            
            <!-- Notification Dropdown -->
            <div class="dropdown-menu dropdown-menu-end notification-dropdown" id="notificationDropdown">
                <div class="dropdown-header">
                    <h6><i class="fas fa-bell text-primary"></i> Notifications</h6>
                    <?php if ($unread > 0): ?>
                        <a class="mark-all" onclick="markAllRead()">Mark all as read</a>
                    <?php endif; ?>
                </div>
                
                <div class="notification-list" id="notificationList">
                    <?php if (count($recent_notifications) > 0): ?>
                        <?php foreach ($recent_notifications as $notif): ?>
                            <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" 
                                 data-id="<?php echo $notif['notification_id']; ?>"
                                 onclick="viewNotification(<?php echo $notif['notification_id']; ?>)">
                                <div class="notif-icon <?php echo $notif['type']; ?>">
                                    <i class="fas fa-<?php echo $notif['icon'] ?? 'bell'; ?>"></i>
                                </div>
                                <div class="notif-content">
                                    <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                    <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                    <div class="notif-time"><?php echo timeAgo($notif['created_at']); ?></div>
                                </div>
                                <?php if (!$notif['is_read']): ?>
                                    <div class="notif-badge">
                                        <span class="badge bg-primary">New</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-notifications">
                            <i class="far fa-bell-slash"></i>
                            <p>No notifications yet</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="dropdown-footer">
                    <a href="<?php echo BASE_URL; ?>public/notifications/">View all notifications</a>
                </div>
            </div>
        </div>
        
        <!-- User Avatar & Dropdown -->
        <div class="user-avatar-wrapper dropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <!-- Avatar Circle -->
            <div class="avatar-circle <?php echo !empty($profile_picture) ? 'has-image' : ''; ?>">
                <?php if (!empty($profile_picture) && file_exists(__DIR__ . '/../../assets/uploads/profiles/' . basename($profile_picture))): ?>
                    <img src="<?php echo $profile_picture; ?>" alt="<?php echo $full_name; ?>">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            
            <!-- User Info -->
            <div class="user-info-text">
                <span class="user-name"><?php echo $first_name; ?></span>
                <span class="user-role"><?php echo $role; ?></span>
            </div>
            
            <!-- Dropdown Arrow -->
            <i class="fas fa-chevron-down dropdown-arrow"></i>
        </div>
        
        <!-- ====== USER DROPDOWN MENU ====== -->
        <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu">
            <li class="dropdown-header">
                <div class="mini-avatar">
                    <?php if (!empty($profile_picture) && file_exists(__DIR__ . '/../../assets/uploads/profiles/' . basename($profile_picture))): ?>
                        <img src="<?php echo $profile_picture; ?>" alt="<?php echo $full_name; ?>">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div class="mini-info">
                    <div class="mini-name"><?php echo $full_name; ?></div>
                    <div class="mini-email"><?php echo $_SESSION['email'] ?? ''; ?></div>
                </div>
            </li>
            
            <li><hr class="dropdown-divider"></li>
            
            <!-- Settings -->
            <li>
                <a class="dropdown-item" href="<?php echo BASE_URL; ?>public/settings/">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
            
            <!-- Change Password -->
            <li>
                <a class="dropdown-item" href="<?php echo BASE_URL; ?>public/profile/#change-password">
                    <i class="fas fa-key"></i> Change Password
                </a>
            </li>
          
            
            <li><hr class="dropdown-divider"></li>
            
            <!-- Logout -->
            <li>
                <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</header>

<!-- ====== SIDEBAR ====== -->
<nav class="sidebar-wrapper <?php echo $sidebar_collapsed ? 'collapsed' : ''; ?>" id="sidebarWrapper">
    <div class="sidebar-nav">
        <?php
        // Define navigation based on role
        $nav_items = [];
        
        if ($role === 'System Administrator') {
            $nav_items = [
                ['url' => '../admin/index.php', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
                ['url' => '../admin/assets.php', 'icon' => 'fa-laptop', 'label' => 'Assets'],
                ['url' => '../admin/technicians.php', 'icon' => 'fa-user-cog', 'label' => 'Technicians'],
                ['url' => '../admin/users.php', 'icon' => 'fa-users', 'label' => 'Users'],
                ['url' => '../admin/reports.php', 'icon' => 'fa-chart-bar', 'label' => 'Reports'],
                ];
        } elseif ($role === 'ICT Technician') {
            $nav_items = [
                ['url' => '../technician/index.php', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
                ['url' => '../technician/my_tasks.php', 'icon' => 'fa-tasks', 'label' => 'My Tasks'],
                ['url' => '../technician/update_task.php', 'icon' => 'fa-edit', 'label' => 'Update Task'],
                ['url' => '../technician/reports.php', 'icon' => 'fa-chart-bar', 'label' => 'Reports']
            ];
        } elseif ($role === 'Staff') {
            $nav_items = [
                ['url' => '../staff/index.php', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
                ['url' => '../staff/report_fault.php', 'icon' => 'fa-exclamation-triangle', 'label' => 'Report Fault'],
                ['url' => '../staff/my_requests.php', 'icon' => 'fa-list', 'label' => 'My Requests'],
                ['url' => '../staff/view_assets.php', 'icon' => 'fa-laptop', 'label' => 'View Assets']
            ];
        }
        
        // Add common items
        $nav_items[] = ['divider' => true];
        $nav_items[] = ['url' => '../notifications/', 'icon' => 'fa-bell', 'label' => 'Notifications'];
        $nav_items[] = ['url' => '../settings/', 'icon' => 'fa-cog', 'label' => 'Settings'];
        $nav_items[] = ['url' => '../profile/', 'icon' => 'fa-user-circle', 'label' => 'Profile'];
        $nav_items[] = ['divider' => true];
        $nav_items[] = ['url' => '../../logout.php', 'icon' => 'fa-sign-out-alt', 'label' => 'Logout', 'class' => 'text-danger'];
        
        // Determine base path
        $base_path = '';
        if ($role === 'System Administrator') $base_path = 'admin/';
        elseif ($role === 'ICT Technician') $base_path = 'technician/';
        elseif ($role === 'Staff') $base_path = 'staff/';
        
        foreach ($nav_items as $item) {
            if (isset($item['divider'])) {
                echo '<hr style="border-color: rgba(255,255,255,0.1); margin: 8px 0;">';
                continue;
            }
            
            $url = $item['url'];
            $icon = $item['icon'];
            $label = $item['label'];
            $class = $item['class'] ?? '';
            
            // Determine if active
            $is_active = false;
            if (strpos($url, $base_path) !== false) {
                $page = basename($url);
                if ($page === $current_page) {
                    $is_active = true;
                }
            }
            
            $active_class = $is_active ? 'active' : '';
            $tooltip = $label;
            
            echo "<a href='{$url}' class='nav-item {$active_class} {$class}' data-tooltip='{$tooltip}'>
                    <i class='fas {$icon}'></i>
                    <span class='nav-label'>{$label}</span>
                  </a>";
        }
        ?>
    </div>
</nav>

<!-- ====== MAIN CONTENT START ====== -->
<div class="main-content <?php echo $sidebar_collapsed ? 'expanded' : ''; ?>" id="mainContent">

<!-- ====== NOTIFICATION SOUND (Hidden) ====== -->
<audio id="notificationSound" style="display:none;">
    <source src="<?php echo BASE_URL; ?>assets/sounds/notification.mp3" type="audio/mpeg">
</audio>

<!-- ====== SUCCESS POPUP OVERLAY ====== -->
<div class="success-overlay" id="successOverlay" style="display:none;">
    <div class="success-modal">
        <div class="check-circle">
            <i class="fas fa-check"></i>
        </div>
        <h3 class="success-title" id="popupTitle">Success!</h3>
        <p class="success-message" id="popupMessage">Action completed successfully.</p>
        <button class="btn-success-close" onclick="closeSuccessPopup()">
            <i class="fas fa-check"></i> Continue
        </button>
    </div>
</div>

<style>
    /* ====== SUCCESS POPUP ====== */
    .success-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
    }
    
    .success-overlay.show {
        display: flex;
        animation: fadeInOverlay 0.3s ease;
    }
    
    .success-modal {
        background: white;
        border-radius: 20px;
        padding: 50px 60px;
        text-align: center;
        max-width: 450px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: bounceIn 0.5s ease;
    }
    
    .success-modal .check-circle {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: #28a745;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        animation: scaleCheck 0.6s ease 0.2s both;
    }
    
    .success-modal .check-circle i {
        color: white;
        font-size: 2.5rem;
        animation: checkmark 0.4s ease 0.4s both;
    }
    
    .success-modal .success-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #28a745;
        margin-bottom: 10px;
    }
    
    .success-modal .success-message {
        color: #6c757d;
        font-size: 0.95rem;
        margin-bottom: 20px;
    }
    
    .success-modal .btn-success-close {
        background: #28a745;
        color: white;
        border: none;
        padding: 10px 30px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .success-modal .btn-success-close:hover {
        background: #218838;
        transform: scale(1.05);
    }
    
    @keyframes fadeInOverlay {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes bounceIn {
        0% { transform: scale(0.5); opacity: 0; }
        60% { transform: scale(1.05); }
        100% { transform: scale(1); opacity: 1; }
    }
    
    @keyframes scaleCheck {
        0% { transform: scale(0); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }
    
    @keyframes checkmark {
        0% { transform: scale(0) rotate(-30deg); opacity: 0; }
        100% { transform: scale(1) rotate(0deg); opacity: 1; }
    }
    
    @media (max-width: 768px) {
        .success-modal {
            padding: 30px 20px;
        }
        .success-modal .check-circle {
            width: 60px;
            height: 60px;
        }
        .success-modal .check-circle i {
            font-size: 2rem;
        }
        .success-modal .success-title {
            font-size: 1.2rem;
        }
        .success-modal .success-message {
            font-size: 0.85rem;
        }
    }
</style>

<script>
// ====== SIDEBAR TOGGLE - FIXED ======
function toggleSidebar() {
    const sidebar = document.getElementById('sidebarWrapper');
    const mainContent = document.getElementById('mainContent');
    const overlay = document.getElementById('sidebarOverlay');
    const isMobile = window.innerWidth <= 992;
    
    if (isMobile) {
        // Mobile: toggle show/hide
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
    } else {
        // Desktop: toggle collapsed/expanded and save
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        
        const isCollapsed = sidebar.classList.contains('collapsed') ? 1 : 0;
        
        // Save state via AJAX
        saveSidebarState(isCollapsed);
    }
}

// ====== SAVE SIDEBAR STATE - FIXED ======
function saveSidebarState(collapsed) {
    // Update the class on main content immediately for smooth UX
    const mainContent = document.getElementById('mainContent');
    
    fetch('<?php echo BASE_URL; ?>public/settings/update_sidebar_state.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'collapsed=' + collapsed
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ Sidebar state saved:', collapsed ? 'Collapsed' : 'Expanded');
        } else {
            console.log('⚠️ Failed to save sidebar state');
            // Revert if failed
            const sidebar = document.getElementById('sidebarWrapper');
            const mainContent = document.getElementById('mainContent');
            if (collapsed) {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
            } else {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        }
    })
    .catch(err => {
        console.log('❌ Sidebar state save error:', err);
        // Revert on error
        const sidebar = document.getElementById('sidebarWrapper');
        const mainContent = document.getElementById('mainContent');
        if (collapsed) {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('expanded');
        } else {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }
    });
}

// ====== RESPONSIVE HANDLING ======
function handleResponsive() {
    const sidebar = document.getElementById('sidebarWrapper');
    const mainContent = document.getElementById('mainContent');
    const overlay = document.getElementById('sidebarOverlay');
    const isMobile = window.innerWidth <= 992;
    
    if (isMobile) {
        // On mobile, remove collapsed state and show overlay if needed
        sidebar.classList.remove('collapsed');
        mainContent.classList.remove('expanded');
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    } else {
        // On desktop, restore collapsed state from CSS variable
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        
        // Restore collapsed state from CSS variable
        const isCollapsed = parseInt(document.documentElement.style.getPropertyValue('--sidebar-collapsed').trim());
        if (isCollapsed === 1) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        } else {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('expanded');
        }
    }
}

// ====== SUCCESS POPUP ======
function showSuccessPopup(title, message) {
    const overlay = document.getElementById('successOverlay');
    document.getElementById('popupTitle').textContent = title;
    document.getElementById('popupMessage').textContent = message;
    overlay.style.display = 'flex';
    overlay.classList.add('show');
    
    setTimeout(function() {
        closeSuccessPopup();
    }, 4000);
}

function closeSuccessPopup() {
    const overlay = document.getElementById('successOverlay');
    overlay.classList.remove('show');
    setTimeout(function() {
        overlay.style.display = 'none';
    }, 300);
}

// ====== NOTIFICATION FUNCTIONS ======
function viewNotification(id) {
    fetch('<?php echo BASE_URL; ?>public/notifications/mark_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + id
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              window.location.href = '<?php echo BASE_URL; ?>public/notifications/';
          }
      });
}

function markAllRead() {
    fetch('<?php echo BASE_URL; ?>public/notifications/mark_all_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              location.reload();
          }
      });
}

// ====== CHECK FOR NEW NOTIFICATIONS ======
function checkNewNotifications() {
    fetch('<?php echo BASE_URL; ?>public/notifications/check_new.php')
        .then(response => response.json())
        .then(data => {
            if (data.unread > 0) {
                const badge = document.getElementById('notifBadge');
                if (badge) {
                    badge.textContent = data.unread;
                    badge.style.display = 'flex';
                } else {
                    const bell = document.getElementById('notificationBell');
                    const newBadge = document.createElement('span');
                    newBadge.className = 'badge-notif';
                    newBadge.id = 'notifBadge';
                    newBadge.textContent = data.unread;
                    bell.appendChild(newBadge);
                }
                
                if (data.has_new) {
                    playNotificationSound();
                    showSuccessPopup('📬 New Notification', 'You have a new notification!');
                }
            }
        })
        .catch(err => console.log('Check notifications error:', err));
}

// ====== PLAY NOTIFICATION SOUND ======
function playNotificationSound() {
    const audio = document.getElementById('notificationSound');
    if (audio) {
        audio.play().catch(err => console.log('Sound play error:', err));
    }
}

// ====== EVENT LISTENERS ======
window.addEventListener('resize', handleResponsive);

document.addEventListener('DOMContentLoaded', function() {
    handleResponsive();
    
    // Check for new notifications every 30 seconds
    setInterval(checkNewNotifications, 30000);
});

// ====== CLOSE SIDEBAR ON ESC ======
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('sidebarWrapper');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
        closeSuccessPopup();
    }
});

// ====== CLOSE DROPDOWN ON CLICK OUTSIDE ======
document.addEventListener('click', function(e) {
    const wrapper = document.querySelector('.user-avatar-wrapper');
    const dropdown = document.querySelector('.user-dropdown-menu');
    if (wrapper && dropdown) {
        if (!wrapper.contains(e.target) && !dropdown.contains(e.target)) {
            const dropdownInstance = bootstrap.Dropdown.getInstance(wrapper);
            if (dropdownInstance) {
                dropdownInstance.hide();
            }
        }
    }
});

console.log('✅ Header loaded successfully!');
console.log('👤 User:', '<?php echo $full_name; ?>');
console.log('🎯 Role:', '<?php echo $role; ?>');
console.log('📐 Sidebar:', '<?php echo $sidebar_collapsed ? 'Collapsed' : 'Expanded'; ?>');
</script>