<?php
// public/staff/printer_status.php - View all printers with real-time status

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole('Staff');

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get user department for filtering
$stmt = $db->prepare("SELECT department FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$department = $user['department'] ?? '';

// Get all printers with status
$sql = "SELECT 
            asset_id,
            name,
            asset_tag,
            ip_address,
            location,
            is_online,
            printer_status,
            ink_levels,
            paper_level,
            total_pages,
            error_message,
            last_scanned,
            created_at,
            CASE 
                WHEN is_online = 0 THEN 'Offline'
                WHEN printer_status = 'Paper Jam' THEN '❗ Paper Jam'
                WHEN printer_status = 'Out of Paper' THEN '📄 Out of Paper'
                WHEN printer_status = 'Low Toner' THEN '⚠️ Low Toner'
                WHEN printer_status = 'Error' THEN '❌ Error'
                WHEN printer_status = 'Online' THEN '✅ Online'
                ELSE '🔍 Unknown'
            END as display_status,
            CASE 
                WHEN is_online = 0 THEN 'danger'
                WHEN printer_status = 'Paper Jam' THEN 'danger'
                WHEN printer_status = 'Out of Paper' THEN 'warning'
                WHEN printer_status = 'Low Toner' THEN 'warning'
                WHEN printer_status = 'Error' THEN 'danger'
                WHEN printer_status = 'Online' THEN 'success'
                ELSE 'secondary'
            END as status_color
        FROM assets 
        WHERE category = 'Printer'";

// Filter by department if staff has department
if (!empty($department)) {
    $sql .= " AND location = ?";
    $params = [$department];
} else {
    $params = [];
}

$sql .= " ORDER BY is_online DESC, name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$printers = $stmt->fetchAll();

// Stats
$total = count($printers);
$online = 0;
$offline = 0;
$issues = 0;
$low_toner = 0;
$paper_jam = 0;
$out_of_paper = 0;

foreach ($printers as $p) {
    if ($p['is_online']) {
        $online++;
    } else {
        $offline++;
    }
    
    if ($p['printer_status'] === 'Low Toner') {
        $low_toner++;
        $issues++;
    } elseif ($p['printer_status'] === 'Paper Jam') {
        $paper_jam++;
        $issues++;
    } elseif ($p['printer_status'] === 'Out of Paper') {
        $out_of_paper++;
        $issues++;
    } elseif ($p['printer_status'] === 'Error') {
        $issues++;
    }
}

// Include header
include __DIR__ . '/../includes/header.php';
?>

<style>
    /* ====== Printer Card Styles ====== */
    .printer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .printer-card {
        background: white;
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        padding: 20px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .printer-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .printer-card .status-indicator {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        animation: pulse-dot 2s infinite;
    }
    
    .printer-card .status-indicator.online {
        background: #28a745;
        box-shadow: 0 0 20px rgba(40, 167, 69, 0.4);
    }
    
    .printer-card .status-indicator.offline {
        background: #dc3545;
        box-shadow: 0 0 20px rgba(220, 53, 69, 0.4);
    }
    
    .printer-card .status-indicator.warning {
        background: #ffc107;
        box-shadow: 0 0 20px rgba(255, 193, 7, 0.4);
        animation: pulse-warning 1.5s infinite;
    }
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    @keyframes pulse-warning {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.2); }
    }
    
    .printer-card .printer-icon {
        font-size: 3rem;
        color: #1a73e8;
        margin-bottom: 10px;
    }
    
    .printer-card .printer-name {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .printer-card .printer-location {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 10px;
    }
    
    .printer-card .printer-tag {
        font-size: 0.7rem;
        color: #6c757d;
        background: #f1f3f5;
        padding: 2px 10px;
        border-radius: 12px;
        display: inline-block;
    }
    
    /* Ink Level Bars */
    .ink-levels {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin: 12px 0;
    }
    
    .ink-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    
    .ink-item .ink-label {
        font-size: 0.7rem;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
    }
    
    .ink-item .ink-bar {
        height: 8px;
        background: #e9ecef;
        border-radius: 10px;
        overflow: hidden;
    }
    
    .ink-item .ink-bar .fill {
        height: 100%;
        border-radius: 10px;
        transition: width 0.5s ease;
    }
    
    .ink-item .ink-bar .fill.black { background: #212529; }
    .ink-item .ink-bar .fill.cyan { background: #0dcaf0; }
    .ink-item .ink-bar .fill.magenta { background: #dc3545; }
    .ink-item .ink-bar .fill.yellow { background: #ffc107; }
    
    .ink-item .ink-bar .fill.low {
        background: #dc3545 !important;
        animation: blink 1s infinite;
    }
    
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.3; }
    }
    
    /* Status Badges */
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .status-badge.success { background: #d4edda; color: #155724; }
    .status-badge.danger { background: #f8d7da; color: #721c24; }
    .status-badge.warning { background: #fff3cd; color: #856404; }
    .status-badge.secondary { background: #e2e3e5; color: #383d41; }
    
    .status-badge i {
        font-size: 0.7rem;
    }
    
    /* Quick Action Buttons */
    .printer-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
        flex-wrap: wrap;
    }
    
    .printer-actions .btn {
        padding: 6px 14px;
        font-size: 0.8rem;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
    }
    
    .printer-actions .btn:hover {
        transform: scale(1.03);
    }
    
    .printer-actions .btn-primary {
        background: #1a73e8;
        color: white;
    }
    
    .printer-actions .btn-danger {
        background: #dc3545;
        color: white;
    }
    
    .printer-actions .btn-warning {
        background: #ffc107;
        color: #212529;
    }
    
    /* Stats Cards */
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 15px 20px;
        border: 1px solid rgba(0,0,0,0.05);
        text-align: center;
        transition: all 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    }
    
    .stat-card .number {
        font-size: 2rem;
        font-weight: 700;
        margin: 0;
    }
    
    .stat-card .label {
        font-size: 0.85rem;
        color: #6c757d;
        margin: 0;
    }
    
    .stat-card .icon {
        font-size: 1.5rem;
        opacity: 0.3;
    }
    
    /* Auto-refresh indicator */
    .auto-refresh {
        font-size: 0.75rem;
        color: #6c757d;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .auto-refresh .spinner {
        width: 16px;
        height: 16px;
        border: 2px solid #e9ecef;
        border-top-color: #1a73e8;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        display: none;
    }
    
    .auto-refresh .spinner.active {
        display: block;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* Refresh Button */
    .btn-refresh {
        background: #1a73e8;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-refresh:hover {
        background: #0d47a1;
        transform: scale(1.03);
    }
    
    .btn-refresh:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
    }
    
    /* ====== RESPONSIVE ====== */
    @media (max-width: 768px) {
        .printer-grid {
            grid-template-columns: 1fr;
        }
        
        .stat-card .number {
            font-size: 1.5rem;
        }
        
        .printer-card .printer-icon {
            font-size: 2.5rem;
        }
        
        .ink-levels {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 576px) {
        .printer-card {
            padding: 15px;
        }
        
        .printer-actions .btn {
            padding: 4px 10px;
            font-size: 0.7rem;
        }
        
        .printer-card .printer-name {
            font-size: 1rem;
        }
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0">
                <i class="fas fa-print text-primary"></i> Printer Status
            </h4>
            <small class="text-muted">Real-time printer monitoring dashboard</small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="auto-refresh">
                <span>Auto-refresh: <span id="countdown">30</span>s</span>
                <div class="spinner" id="refreshSpinner"></div>
            </div>
            <button class="btn-refresh" id="manualRefresh" onclick="refreshPrinters()">
                <i class="fas fa-sync-alt"></i> Refresh Now
            </button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <i class="fas fa-print float-end icon"></i>
                <p class="number text-primary"><?php echo $total; ?></p>
                <p class="label">Total Printers</p>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <i class="fas fa-check-circle float-end icon" style="color:#28a745;"></i>
                <p class="number text-success"><?php echo $online; ?></p>
                <p class="label">Online</p>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <i class="fas fa-times-circle float-end icon" style="color:#dc3545;"></i>
                <p class="number text-danger"><?php echo $offline; ?></p>
                <p class="label">Offline</p>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <i class="fas fa-exclamation-triangle float-end icon" style="color:#ffc107;"></i>
                <p class="number text-warning"><?php echo $issues; ?></p>
                <p class="label">Issues</p>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <i class="fas fa-tint float-end icon" style="color:#dc3545;"></i>
                <p class="number text-danger"><?php echo $low_toner; ?></p>
                <p class="label">Low Toner</p>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <i class="fas fa-file float-end icon" style="color:#856404;"></i>
                <p class="number text-warning"><?php echo $out_of_paper; ?></p>
                <p class="label">Out of Paper</p>
            </div>
        </div>
    </div>

    <!-- Printer Grid -->
    <div class="printer-grid" id="printerGrid">
        <?php if (count($printers) > 0): ?>
            <?php foreach ($printers as $printer): 
                $ink = json_decode($printer['ink_levels'] ?? '{}', true);
                $statusClass = $printer['status_color'];
                $displayStatus = $printer['display_status'];
                
                // Determine status indicator class
                $indicatorClass = 'online';
                if (!$printer['is_online']) {
                    $indicatorClass = 'offline';
                } elseif ($printer['printer_status'] === 'Low Toner' || 
                          $printer['printer_status'] === 'Out of Paper') {
                    $indicatorClass = 'warning';
                }
            ?>
                <div class="printer-card" data-printer-id="<?php echo $printer['asset_id']; ?>">
                    <!-- Status Indicator -->
                    <div class="status-indicator <?php echo $indicatorClass; ?>"></div>
                    
                    <!-- Printer Icon & Name -->
                    <div class="d-flex align-items-center gap-3">
                        <div class="printer-icon">
                            <i class="fas fa-print"></i>
                        </div>
                        <div>
                            <div class="printer-name">
                                <?php echo htmlspecialchars($printer['name']); ?>
                            </div>
                            <div>
                                <span class="printer-tag">
                                    <?php echo htmlspecialchars($printer['asset_tag']); ?>
                                </span>
                                <span class="printer-tag ms-1">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($printer['location'] ?? 'Unknown'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Badge -->
                    <div class="mt-2">
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <i class="fas <?php 
                                echo $printer['is_online'] ? 'fa-circle' : 'fa-circle'; 
                            ?>"></i>
                            <?php echo $displayStatus; ?>
                        </span>
                        <?php if (!empty($printer['error_message'])): ?>
                            <span class="badge bg-danger ms-1" title="<?php echo htmlspecialchars($printer['error_message']); ?>">
                                <i class="fas fa-info-circle"></i>
                            </span>
                        <?php endif; ?>
                        <?php if ($printer['is_online'] && $printer['last_scanned']): ?>
                            <span class="badge bg-secondary ms-1">
                                <i class="far fa-clock"></i>
                                <?php echo timeAgo($printer['last_scanned']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Ink/Toner Levels -->
                    <?php if ($printer['is_online'] && !empty($ink)): ?>
                        <div class="ink-levels mt-2">
                            <?php 
                            $colors = [
                                'black' => ['label' => '🖤 Black', 'class' => 'black'],
                                'cyan' => ['label' => '💙 Cyan', 'class' => 'cyan'],
                                'magenta' => ['label' => '❤️ Magenta', 'class' => 'magenta'],
                                'yellow' => ['label' => '💛 Yellow', 'class' => 'yellow']
                            ];
                            foreach ($colors as $key => $color):
                                $level = intval($ink[$key] ?? 0);
                                $isLow = $level < 25;
                            ?>
                                <div class="ink-item">
                                    <div class="ink-label">
                                        <span><?php echo $color['label']; ?></span>
                                        <span class="<?php echo $isLow ? 'text-danger' : ''; ?>">
                                            <?php echo $level; ?>%
                                        </span>
                                    </div>
                                    <div class="ink-bar">
                                        <div class="fill <?php echo $color['class']; ?> <?php echo $isLow ? 'low' : ''; ?>" 
                                             style="width: <?php echo $level; ?>%;">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="printer-actions">
                        <a href="report_fault.php?asset_id=<?php echo $printer['asset_id']; ?>" 
                           class="btn btn-danger">
                            <i class="fas fa-exclamation-triangle"></i> Report Fault
                        </a>
                        <?php if ($printer['is_online']): ?>
                            <button class="btn btn-warning" onclick="scanPrinter(<?php echo $printer['asset_id']; ?>)">
                                <i class="fas fa-sync-alt"></i> Scan
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-print fa-4x text-muted mb-3"></i>
                    <h5>No Printers Found</h5>
                    <p class="text-muted">No printers are registered in the system.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ====== SCRIPTS ====== -->
<script>
    // ====== AUTO-REFRESH ======
    let refreshInterval = 30; // seconds
    let countdown = refreshInterval;
    let isRefreshing = false;
    
    function startAutoRefresh() {
        const countdownEl = document.getElementById('countdown');
        const spinner = document.getElementById('refreshSpinner');
        
        setInterval(() => {
            countdown--;
            if (countdownEl) {
                countdownEl.textContent = countdown;
            }
            
            if (countdown <= 0) {
                countdown = refreshInterval;
                refreshPrinters();
            }
        }, 1000);
    }
    
    // ====== REFRESH PRINTERS ======
    function refreshPrinters() {
        if (isRefreshing) return;
        isRefreshing = true;
        
        const btn = document.getElementById('manualRefresh');
        const spinner = document.getElementById('refreshSpinner');
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        spinner.classList.add('active');
        
        fetch('api/get_printer_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cards without full page reload
                    updatePrinterCards(data.printers);
                    showNotification('✅ Printers updated successfully!', 'success');
                } else {
                    showNotification('❌ Failed to update printers', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ Network error. Please try again.', 'error');
            })
            .finally(() => {
                isRefreshing = false;
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Now';
                spinner.classList.remove('active');
                countdown = refreshInterval;
            });
    }
    
    // ====== UPDATE PRINTER CARDS ======
    function updatePrinterCards(printers) {
        const grid = document.getElementById('printerGrid');
        const currentCards = grid.querySelectorAll('.printer-card');
        
        // Update each card or add new ones
        printers.forEach(printer => {
            const existingCard = grid.querySelector(`[data-printer-id="${printer.asset_id}"]`);
            
            if (existingCard) {
                // Update existing card
                updateCard(existingCard, printer);
            } else {
                // Add new card (you might want to reload page for simplicity)
                // For now, we'll reload
                location.reload();
            }
        });
    }
    
    // ====== UPDATE INDIVIDUAL CARD ======
    function updateCard(card, data) {
        // Update status indicator
        const indicator = card.querySelector('.status-indicator');
        let indicatorClass = 'online';
        if (!data.is_online) {
            indicatorClass = 'offline';
        } else if (data.printer_status === 'Low Toner' || data.printer_status === 'Out of Paper') {
            indicatorClass = 'warning';
        }
        indicator.className = `status-indicator ${indicatorClass}`;
        
        // Update status badge
        const statusBadge = card.querySelector('.status-badge');
        const statusMap = {
            'Online': ['success', 'fa-circle'],
            'Offline': ['danger', 'fa-circle'],
            'Paper Jam': ['danger', 'fa-exclamation-triangle'],
            'Out of Paper': ['warning', 'fa-file'],
            'Low Toner': ['warning', 'fa-tint'],
            'Error': ['danger', 'fa-times-circle']
        };
        const [color, icon] = statusMap[data.printer_status] || ['secondary', 'fa-circle'];
        statusBadge.className = `status-badge ${color}`;
        statusBadge.innerHTML = `<i class="fas ${icon}"></i> ${data.display_status}`;
        
        // Update ink levels if available
        if (data.is_online && data.ink_levels) {
            const inkContainer = card.querySelector('.ink-levels');
            if (inkContainer) {
                const inkData = JSON.parse(data.ink_levels || '{}');
                const fills = inkContainer.querySelectorAll('.fill');
                const labels = inkContainer.querySelectorAll('.ink-label span:last-child');
                
                ['black', 'cyan', 'magenta', 'yellow'].forEach((color, index) => {
                    const level = parseInt(inkData[color] || 0);
                    if (fills[index]) {
                        fills[index].style.width = level + '%';
                        fills[index].classList.toggle('low', level < 25);
                    }
                    if (labels[index]) {
                        labels[index].textContent = level + '%';
                        labels[index].className = level < 25 ? 'text-danger' : '';
                    }
                });
            }
        }
        
        // Update timestamp
        const timeBadge = card.querySelector('.badge.bg-secondary');
        if (timeBadge && data.last_scanned) {
            timeBadge.innerHTML = `<i class="far fa-clock"></i> ${timeAgo(data.last_scanned)}`;
        }
    }
    
    // ====== TIME AGO FUNCTION ======
    function timeAgo(date) {
        const diff = Math.floor((new Date() - new Date(date)) / 1000);
        if (diff < 60) return diff + 's ago';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }
    
    // ====== SCAN PRINTER ======
    function scanPrinter(assetId) {
        const btn = event.target.closest('button');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scanning...';
        
        fetch('api/scan_printer.php?id=' + assetId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ Printer scanned successfully!', 'success');
                    setTimeout(() => refreshPrinters(), 1000);
                } else {
                    showNotification('❌ ' + data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('❌ Network error. Please try again.', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Scan';
            });
    }
    
    // ====== SHOW NOTIFICATION ======
    function showNotification(message, type) {
        const toast = document.createElement('div');
        toast.className = (type === 'success') ? 'success-toast show' : 'error-toast show';
        toast.style.position = 'fixed';
        toast.style.top = '80px';
        toast.style.right = '20px';
        toast.style.zIndex = '99999';
        
        const iconClass = (type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle';
        const title = (type === 'success') ? '✅ Success' : '❌ Error';
        const titleClass = (type === 'success') ? 'toast-title' : 'toast-title-error';
        const iconWrap = (type === 'success') ? 'toast-icon' : 'toast-icon-error';
        
        toast.innerHTML = `
            <div class="${iconWrap}"><i class="fas ${iconClass}"></i></div>
            <div class="toast-content">
                <div class="${titleClass}">${title}</div>
                <p class="toast-message">${message}</p>
            </div>
        `;
        
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => toast.remove(), 400);
        }, 4000);
    }
    
    // ====== KEYBOARD SHORTCUTS ======
    document.addEventListener('keydown', function(e) {
        if (e.key === 'r' || e.key === 'R') {
            refreshPrinters();
        }
    });
    
    // ====== START AUTO-REFRESH ======
    document.addEventListener('DOMContentLoaded', function() {
        startAutoRefresh();
        console.log('🖨️ Printer Status Dashboard Loaded');
        console.log('⌨️ Press R to refresh manually');
    });
</script>

<style>
    /* Toast styles (reused from other pages) */
    .success-toast, .error-toast {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 99999;
        background: white;
        border-radius: 16px;
        padding: 20px 30px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 15px;
        min-width: 300px;
        max-width: 450px;
        animation: slideInRight 0.4s ease;
        border-left: 5px solid #28a745;
    }
    .success-toast { border-left-color: #28a745; }
    .error-toast { border-left-color: #dc3545; }
    .success-toast .toast-icon, .error-toast .toast-icon-error {
        width: 45px; height: 45px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .success-toast .toast-icon { background: #28a745; color: white; font-size: 1.5rem; }
    .error-toast .toast-icon-error { background: #dc3545; color: white; font-size: 1.5rem; }
    .success-toast .toast-content, .error-toast .toast-content { flex: 1; }
    .success-toast .toast-content .toast-title { font-weight: 700; font-size: 1rem; color: #28a745; margin-bottom: 2px; }
    .error-toast .toast-content .toast-title-error { font-weight: 700; font-size: 1rem; color: #dc3545; margin-bottom: 2px; }
    .success-toast .toast-content .toast-message, .error-toast .toast-content .toast-message {
        font-size: 0.9rem; color: #495057; margin: 0;
    }
    @keyframes slideInRight {
        from { transform: translateX(100px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100px); opacity: 0; }
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>