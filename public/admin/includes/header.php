<?php
// admin/includes/header.php
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - NIT USSD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #F7F9F6;
            min-height: 100vh;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
        }
        /* Sidebar - modern glass effect */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            backdrop-filter: blur(10px);
            color: white;
            position: fixed;
            height: 100vh;
            padding-top: 1.5rem;
            transition: transform 0.3s ease-in-out;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 2px 0 12px rgba(0,0,0,0.1);
        }
        /* Hidden by default on mobile */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-280px);
            }
            .sidebar.show {
                transform: translateX(0);
            }
        }
        /* On desktop, always visible */
        @media (min-width: 992px) {
            .sidebar {
                transform: translateX(0) !important;
            }
        }
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.85);
            padding: 12px 24px;
            text-decoration: none;
            border-left: 4px solid transparent;
            transition: all 0.2s;
            font-weight: 500;
        }
        .sidebar a i {
            font-size: 1.2rem;
            width: 24px;
        }
        .sidebar a:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: #ffc107;
            color: white;
        }
        /* Main content area */
        .content {
            margin-left: 280px;
            padding: 20px;
            transition: margin-left 0.3s ease-in-out;
        }
        @media (max-width: 991.98px) {
            .content {
                margin-left: 0;
            }
        }
        .top-bar {
            background: white;
            border-radius: 16px;
            padding: 12px 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .sidebar-toggle {
            background: #2c3e50;
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .sidebar-toggle:hover {
            background: #1a252f;
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        .overlay.show {
            display: block;
        }
        @media (min-width: 992px) {
            .overlay {
                display: none !important;
            }
        }
        .card {
            border: none;
            border-radius: 20px;
            background: rgba(255,255,255,0.98);
            transition: all 0.2s;
        }
        .btn {
            border-radius: 40px;
            padding: 8px 20px;
            font-weight: 500;
        }
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
    </style>
</head>
<body>
<div class="overlay" id="sidebarOverlay"></div>
<div class="sidebar" id="sidebar">
    <div class="text-center mb-4">
        <i class="bi bi-phone-fill fs-1"></i>
        <h5 class="mt-2 fw-bold">NIT USSD Admin</h5>
    </div>
    <a href="/nit-ussd-system/public/admin/index.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a href="/nit-ussd-system/public/admin/students/index.php"><i class="bi bi-people"></i> Students</a>
    <hr class="mx-3 my-3" style="border-color: rgba(255,255,255,0.2);">
    <a href="/nit-ussd-system/public/admin/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>  
<div class="content" id="content">  
    <div class="top-bar">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle d-lg-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <h4 class="mb-0 fw-semibold">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></h4>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-light text-dark py-2 px-3"><i class="bi bi-calendar"></i> <?php echo date('l, d M Y'); ?></span>
        </div>
    </div>
    <!-- JavaScript for toggle functionality (placed at end of header so DOM is ready) -->
    <script>
        (function() {
            const toggleBtn = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (!toggleBtn || !sidebar || !overlay) return;
            
            function openSidebar() {
                sidebar.classList.add('show');
                overlay.classList.add('show');
            }
            function closeSidebar() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
            
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (sidebar.classList.contains('show')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
            
            overlay.addEventListener('click', closeSidebar);
            
            // On window resize, if screen becomes large, ensure sidebar is visible and remove overlay
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    // On desktop, hide overlay and remove 'show' class (we use CSS to keep it visible)
                    overlay.classList.remove('show');
                    sidebar.classList.remove('show'); // class not needed because media query makes it visible
                }
            });
        })();
    </script>