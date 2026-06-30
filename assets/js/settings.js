// assets/js/settings.js - Enhanced settings functionality

document.addEventListener('DOMContentLoaded', function() {
    // Update sidebar state from settings
    const isCollapsed = document.documentElement.style.getPropertyValue('--sidebar-collapsed').trim();
    const sidebar = document.getElementById('sidebarWrapper');
    const mainContent = document.getElementById('mainContent');
    
    if (isCollapsed === '1' && window.innerWidth > 992) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }
});

// ====== SIDEBAR TOGGLE (Enhanced) ======
function toggleSidebar() {
    const sidebar = document.getElementById('sidebarWrapper');
    const mainContent = document.getElementById('mainContent');
    const overlay = document.getElementById('sidebarOverlay');
    const isMobile = window.innerWidth <= 992;
    
    if (isMobile) {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
    } else {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        
        const isCollapsed = sidebar.classList.contains('collapsed') ? 1 : 0;
        
        // Save preference via AJAX
        fetch('<?php echo BASE_URL; ?>public/settings/update_sidebar_state.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'collapsed=' + isCollapsed
        });
    }
}

// ====== RESPONSIVE HANDLING (Enhanced) ======
function handleResponsive() {
    const sidebar = document.getElementById('sidebarWrapper');
    const mainContent = document.getElementById('mainContent');
    const overlay = document.getElementById('sidebarOverlay');
    const isMobile = window.innerWidth <= 992;
    const isCollapsed = sidebar.classList.contains('collapsed');
    
    if (isMobile) {
        sidebar.classList.remove('collapsed');
        mainContent.classList.remove('expanded');
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// ====== NOTIFICATION DROPDOWN ======
function toggleNotifications() {
    // TODO: Implement dropdown
    // For now, redirect to notifications page
    window.location.href = '<?php echo BASE_URL; ?>public/notifications/';
}

// ====== KEYBOARD SHORTCUTS ======
document.addEventListener('keydown', function(e) {
    // Ctrl + B to toggle sidebar
    if (e.ctrlKey && e.key === 'b') {
        e.preventDefault();
        toggleSidebar();
    }
    
    // Escape to close sidebar on mobile
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('sidebarWrapper');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
});