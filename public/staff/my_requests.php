<?php
// public/staff/my_requests.php - View my submitted maintenance requests

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/settings_helper.php';

requireRole('Staff');

$db = getDB();
$user_id = $_SESSION['user_id'];

// ====== FILTERS ======
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ====== BUILD QUERY ======
$sql = "SELECT r.*, 
               a.name AS asset_name, a.asset_tag,
               CONCAT(u.first_name, ' ', u.last_name) AS assigned_technician
        FROM maintenance_requests r
        LEFT JOIN assets a ON r.asset_id = a.asset_id
        LEFT JOIN users u ON r.assigned_to = u.user_id
        WHERE r.reported_by = ?";
$params = [$user_id];

if ($filter_status !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $sql .= " AND (a.name LIKE ? OR r.issue_description LIKE ? OR r.request_id LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = '%' . $search . '%';
}

$sql .= " ORDER BY r.reported_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// ====== STATS ======
$total = count($requests);
$pending = 0;
$assigned = 0;
$in_progress = 0;
$resolved = 0;
$closed = 0;
foreach ($requests as $req) {
    switch ($req['status']) {
        case 'Pending':
            $pending++;
            break;
        case 'Assigned':
            $assigned++;
            break;
        case 'In Progress':
            $in_progress++;
            break;
        case 'Resolved':
            $resolved++;
            break;
        case 'Closed':
            $closed++;
            break;
    }
}

// Include header
include __DIR__ . '/../includes/header.php';
?>

<!-- Toast messages (if any) -->
<?php if (isset($_GET['success'])): ?>
    <div class="success-toast show" id="successToast">
        <div class="toast-icon"><i class="fas fa-check-circle"></i></div>
        <div class="toast-content">
            <div class="toast-title">✅ Success!</div>
            <p class="toast-message"><?php echo htmlspecialchars($_GET['message'] ?? 'Action completed.'); ?></p>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="error-toast show" id="errorToast">
        <div class="toast-icon-error"><i class="fas fa-exclamation-circle"></i></div>
        <div class="toast-content">
            <div class="toast-title-error">❌ Error!</div>
            <p class="toast-message"><?php echo htmlspecialchars($_GET['message'] ?? 'An error occurred.'); ?></p>
        </div>
    </div>
<?php endif; ?>

<style>
    /* ====== TOAST STYLES ====== */
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
    }
    .success-toast { border-left: 5px solid #28a745; }
    .error-toast { border-left: 5px solid #dc3545; }
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

    /* ====== FILTERS ====== */
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }
    .filter-group .filter-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #6c757d;
        margin-right: 4px;
    }
    .filter-btn {
        padding: 4px 14px;
        border-radius: 20px;
        border: 2px solid #e9ecef;
        background: white;
        color: #495057;
        font-size: 0.75rem;
        transition: all 0.3s;
        text-decoration: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .filter-btn:hover { border-color: #1a73e8; color: #1a73e8; }
    .filter-btn.active { background: #1a73e8; border-color: #1a73e8; color: white; }
    .filter-btn .badge { font-size: 0.6rem; margin-left: 4px; background: rgba(0,0,0,0.1); }
    .filter-btn.active .badge { background: rgba(255,255,255,0.3); color: white; }

    .search-box {
        position: relative;
        min-width: 200px;
    }
    .search-box input {
        padding: 6px 12px 6px 32px;
        border-radius: 20px;
        border: 2px solid #e9ecef;
        font-size: 0.8rem;
        width: 100%;
        transition: all 0.3s;
        background: white;
    }
    .search-box input:focus {
        border-color: #1a73e8;
        outline: none;
        box-shadow: 0 0 0 3px rgba(26,115,232,0.1);
    }
    .search-box .search-icon {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
        font-size: 0.8rem;
    }
    .search-box .clear-search {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
        font-size: 0.8rem;
        cursor: pointer;
        display: none;
    }
    .search-box .clear-search.show { display: block; }
    .search-box .clear-search:hover { color: #dc3545; }

    /* ====== TABLE ====== */
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-Pending { background: #fff3cd; color: #856404; }
    .status-Assigned { background: #cce5ff; color: #004085; }
    .status-In\ Progress { background: #d1ecf1; color: #0c5460; }
    .status-Resolved { background: #d4edda; color: #155724; }
    .status-Closed { background: #e2e3e5; color: #383d41; }

    .priority-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .priority-Low { background: #d4edda; color: #155724; }
    .priority-Medium { background: #fff3cd; color: #856404; }
    .priority-High { background: #f8d7da; color: #721c24; }
    .priority-Critical { background: #dc3545; color: white; }

    .card { border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
    .card-header { background: white; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 15px 20px; font-weight: 600; }
    .table th { background: #f8f9fa; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #dee2e6; }
    .table td { vertical-align: middle; }
    .no-results { text-align: center; padding: 40px 20px; color: #6c757d; }
    .no-results i { font-size: 3rem; color: #dee2e6; margin-bottom: 15px; }

    /* ====== QR SCANNER MODAL ====== */
    #qr-reader {
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        background: #000;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        min-height: 350px;
    }
    #qr-reader video {
        width: 100% !important;
        height: auto !important;
        display: block;
    }
    #qr-reader img {
        display: none !important;
    }

    /* Scanner Overlay */
    .scanner-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
        z-index: 10;
    }
    .scanner-overlay .scan-frame {
        width: 220px;
        height: 220px;
        border: 3px solid #00ff00;
        border-radius: 16px;
        position: relative;
        box-shadow: 0 0 40px rgba(0, 255, 0, 0.3);
        animation: pulse-border 2s ease-in-out infinite;
    }
    .scanner-overlay .scan-frame::before {
        content: '';
        position: absolute;
        top: -3px;
        left: -3px;
        right: -3px;
        bottom: -3px;
        border: 3px solid transparent;
        border-top-color: #00ff00;
        border-left-color: #00ff00;
        border-radius: 16px;
        animation: scan-corner 3s linear infinite;
    }
    .scanner-overlay .scan-line {
        position: absolute;
        top: 0;
        left: 10%;
        right: 10%;
        height: 3px;
        background: linear-gradient(90deg, transparent, #00ff00, transparent);
        animation: scan-line 2.5s ease-in-out infinite;
        box-shadow: 0 0 20px rgba(0, 255, 0, 0.6);
        border-radius: 2px;
    }
    .scanner-overlay .scan-text {
        position: absolute;
        bottom: -35px;
        color: #00ff00;
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 2px;
        text-shadow: 0 0 20px rgba(0, 255, 0, 0.5);
        animation: pulse-text 2s ease-in-out infinite;
    }

    @keyframes pulse-border {
        0%, 100% { box-shadow: 0 0 40px rgba(0, 255, 0, 0.3); }
        50% { box-shadow: 0 0 80px rgba(0, 255, 0, 0.6); }
    }
    @keyframes scan-corner {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    @keyframes scan-line {
        0% { top: 5%; opacity: 1; }
        50% { top: 85%; opacity: 1; }
        51% { opacity: 0; }
        100% { opacity: 0; }
    }
    @keyframes pulse-text {
        0%, 100% { opacity: 0.7; }
        50% { opacity: 1; }
    }

    /* Scanner Controls */
    .scanner-controls {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 15px;
        flex-wrap: wrap;
    }
    .scanner-controls .btn {
        padding: 10px 22px;
        border-radius: 50px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        transition: all 0.3s;
        cursor: pointer;
        font-size: 0.9rem;
    }
    .scanner-controls .btn:hover {
        transform: scale(1.05);
    }
    .scanner-controls .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
    }
    .scanner-controls .btn-torch {
        background: #ffc107;
        color: #212529;
    }
    .scanner-controls .btn-torch.active {
        background: #ffca2c;
        box-shadow: 0 0 40px rgba(255, 193, 7, 0.5);
    }
    .scanner-controls .btn-torch.active i {
        animation: torch-glow 1s ease-in-out infinite;
    }
    @keyframes torch-glow {
        0%, 100% { text-shadow: 0 0 10px rgba(255, 193, 7, 0.3); }
        50% { text-shadow: 0 0 30px rgba(255, 193, 7, 0.8); }
    }
    .scanner-controls .btn-switch {
        background: #17a2b8;
        color: white;
    }
    .scanner-controls .btn-stop {
        background: #dc3545;
        color: white;
    }
    .scanner-controls .btn-start {
        background: #28a745;
        color: white;
    }

    .scanner-status {
        padding: 12px 16px;
        text-align: center;
        font-weight: 500;
        margin-top: 12px;
        border-radius: 10px;
        font-size: 0.95rem;
    }
    .scanner-status.text-success { background: #d4edda; color: #155724; }
    .scanner-status.text-danger { background: #f8d7da; color: #721c24; }
    .scanner-status.text-warning { background: #fff3cd; color: #856404; }
    .scanner-status.text-info { background: #d1ecf1; color: #0c5460; }
    .scanner-status.text-muted { background: #e9ecef; color: #6c757d; }

    .modal-header {
        background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
        color: white;
    }
    .modal-header .btn-close { filter: brightness(0) invert(1); }

    /* ====== RESPONSIVE ====== */
    @media (max-width: 768px) {
        .success-toast, .error-toast {
            top: 70px; right: 10px; left: 10px; min-width: auto; padding: 15px 20px;
        }
        .filter-group { gap: 5px; }
        .filter-btn { padding: 3px 10px; font-size: 0.7rem; }
        .search-box { min-width: 150px; }
        .search-box input { font-size: 0.75rem; padding: 5px 10px 5px 28px; }
        .table-responsive { font-size: 0.8rem; }
        .scanner-controls .btn { padding: 8px 16px; font-size: 0.8rem; }
        .scanner-overlay .scan-frame { width: 160px; height: 160px; }
        #qr-reader { min-height: 280px; }
        .scanner-overlay .scan-text { bottom: -30px; font-size: 0.7rem; }
    }
    @media (max-width: 576px) {
        .success-toast, .error-toast {
            top: 60px; right: 5px; left: 5px; padding: 12px 15px;
        }
        .scanner-controls .btn { padding: 6px 12px; font-size: 0.7rem; gap: 4px; }
        .scanner-overlay .scan-frame { width: 130px; height: 130px; }
        #qr-reader { min-height: 220px; }
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-clipboard-list text-primary"></i> My Requests</h4>
            <small class="text-muted">Track all your maintenance requests</small>
        </div>
        <!-- Report New Fault Button - Opens QR Scanner Modal -->
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#qrScannerModal">
            <i class="fas fa-qrcode"></i> Report New Fault
        </button>
    </div>

    <!-- Stats Row -->
    <div class="row g-2 mb-3">
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-primary mb-0"><?php echo $total; ?></h5>
                <small class="text-muted">Total</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-warning mb-0"><?php echo $pending; ?></h5>
                <small class="text-muted">Pending</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-info mb-0"><?php echo $assigned + $in_progress; ?></h5>
                <small class="text-muted">In Progress</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-success mb-0"><?php echo $resolved; ?></h5>
                <small class="text-muted">Resolved</small>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-secondary mb-0"><?php echo $closed; ?></h5>
                <small class="text-muted">Closed</small>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="filter-group">
                    <span class="filter-label">Status:</span>
                    <a href="?status=all&search=<?php echo urlencode($search); ?>"
                        class="filter-btn <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                        All <span class="badge"><?php echo $total; ?></span>
                    </a>
                    <a href="?status=Pending&search=<?php echo urlencode($search); ?>"
                        class="filter-btn <?php echo $filter_status === 'Pending' ? 'active' : ''; ?>">
                        Pending <span class="badge"><?php echo $pending; ?></span>
                    </a>
                    <a href="?status=Assigned&search=<?php echo urlencode($search); ?>"
                        class="filter-btn <?php echo $filter_status === 'Assigned' ? 'active' : ''; ?>">
                        Assigned <span class="badge"><?php echo $assigned; ?></span>
                    </a>
                    <a href="?status=In%20Progress&search=<?php echo urlencode($search); ?>"
                        class="filter-btn <?php echo $filter_status === 'In Progress' ? 'active' : ''; ?>">
                        In Progress <span class="badge"><?php echo $in_progress; ?></span>
                    </a>
                    <a href="?status=Resolved&search=<?php echo urlencode($search); ?>"
                        class="filter-btn <?php echo $filter_status === 'Resolved' ? 'active' : ''; ?>">
                        Resolved <span class="badge"><?php echo $resolved; ?></span>
                    </a>
                    <a href="?status=Closed&search=<?php echo urlencode($search); ?>"
                        class="filter-btn <?php echo $filter_status === 'Closed' ? 'active' : ''; ?>">
                        Closed <span class="badge"><?php echo $closed; ?></span>
                    </a>
                </div>

                <span class="text-muted">|</span>

                <!-- Search -->
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search by asset or description..."
                        value="<?php echo htmlspecialchars($search); ?>" onkeyup="searchRequests(this.value)">
                    <i class="fas fa-times clear-search <?php echo !empty($search) ? 'show' : ''; ?>"
                        onclick="clearSearch()"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list"></i> Requests</span>
            <span class="badge bg-primary"><?php echo $total; ?> found</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Asset</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Reported</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($requests) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($requests as $req): ?>
                                <?php
                                $status_class = 'status-' . str_replace(' ', '-', $req['status']);
                                $priority_class = 'priority-' . $req['priority'];
                                $reported_date = date('d M Y, H:i', strtotime($req['reported_at']));
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($req['asset_name'] ?? 'N/A'); ?></strong>
                                        <br><small
                                            class="text-muted"><?php echo htmlspecialchars($req['asset_tag'] ?? ''); ?></small>
                                    </td>
                                    
                                    <td><span
                                            class="priority-badge <?php echo $priority_class; ?>"><?php echo $req['priority']; ?></span>
                                    </td>
                                    <td><span
                                            class="status-badge <?php echo $status_class; ?>"><?php echo $req['status']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($req['assigned_to']): ?>
                                            <?php echo htmlspecialchars($req['assigned_technician'] ?? 'Unknown'); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $reported_date; ?></td>
                                    <td class="text-center">
                                        <!-- View Details -->
                                        <button class="btn btn-sm btn-outline-primary btn-action"
                                            onclick="viewRequest(<?php echo $req['request_id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="no-results">
                                        <i class="fas fa-inbox"></i>
                                        <h6>No requests found</h6>
                                        <p class="text-muted small">You haven't submitted any requests yet, or try adjusting
                                            filters.</p>
                                        <button class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#qrScannerModal">
                                            <i class="fas fa-qrcode"></i> Scan & Report Fault
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ====== QR SCANNER MODAL (Advanced with Torch Support) ====== -->
<div class="modal fade" id="qrScannerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-qrcode"></i> Scan QR Code to Report Fault</h5>
                <button type="button" class="btn-close" id="closeScannerBtn" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- QR Reader -->
                <div id="qr-reader">
                    <div class="scanner-overlay" id="scannerOverlay">
                        <div class="scan-frame">
                            <div class="scan-line"></div>
                            <div class="scan-text">SCAN QR CODE</div>
                        </div>
                    </div>
                </div>
                
                <!-- Scanner Status -->
                <div id="qr-reader-status" class="scanner-status text-muted">📷 Waiting for camera...</div>
                <div id="qr-reader-results" class="scanner-status" style="display:none;"></div>

                <!-- Scanner Controls - Advanced with Torch -->
                <div class="scanner-controls">
                    <button class="btn btn-torch" id="torchBtn" title="Toggle Torch/Flash (Keyboard: T)" disabled>
                        <i class="fas fa-lightbulb"></i> Torch
                    </button>
                    <button class="btn btn-switch" id="switchCameraBtn" title="Switch Camera (Keyboard: S)">
                        <i class="fas fa-sync-alt"></i> Switch
                    </button>
                    <button class="btn btn-start" id="startScannerBtn" disabled>
                        <i class="fas fa-play"></i> Start
                    </button>
                    <button class="btn btn-stop" id="stopScannerBtn" disabled>
                        <i class="fas fa-stop"></i> Stop
                    </button>
                </div>
                
                <!-- Keyboard Shortcuts Hint -->
                <div class="text-center mt-2">
                    <small class="text-muted">
                        <i class="fas fa-keyboard"></i> Shortcuts: 
                        <kbd>T</kbd> Torch · 
                        <kbd>S</kbd> Switch Camera · 
                        <kbd>ESC</kbd> Close
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="manualSelectBtn">
                    <i class="fas fa-hand-pointer"></i> Select Manually
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Request Modal -->
<div class="modal fade" id="viewRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-clipboard-list"></i> Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="requestDetailsBody">
                <p class="text-muted">Loading...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ====== QR SCANNER SCRIPT ====== -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
// ============================================================
// QR SCANNER - Advanced Implementation with Torch Support
// ============================================================

// ====== GLOBALS ======
let html5QrCode = null;
let isScanning = false;
let isTorchOn = false;
let currentCameraId = null;
let cameraList = [];
let cameraIndex = 0;
let isStarting = false;
let torchSupported = false;
const MAX_SCAN_ATTEMPTS = 3;

const qrRegionId = "qr-reader";
const statusElement = document.getElementById('qr-reader-status');
const resultsElement = document.getElementById('qr-reader-results');
const scannerOverlay = document.getElementById('scannerOverlay');
const torchBtn = document.getElementById('torchBtn');
const switchBtn = document.getElementById('switchCameraBtn');
const startBtn = document.getElementById('startScannerBtn');
const stopBtn = document.getElementById('stopScannerBtn');

const API_URL = '../../api/get_asset_by_qr.php';

// ====== CAMERA HELPERS ======

function isMediaDevicesSupported() {
    return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
}

async function getCameras() {
    try {
        if (!isMediaDevicesSupported()) {
            console.error('❌ MediaDevices API not supported');
            return [];
        }

        // Request permission first
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    facingMode: 'environment',
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                } 
            });
            stream.getTracks().forEach(track => track.stop());
            console.log('✅ Camera permission granted');
        } catch (permErr) {
            console.warn('⚠️ Permission request error:', permErr);
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                stream.getTracks().forEach(track => track.stop());
                console.log('✅ Camera permission granted (basic)');
            } catch (permErr2) {
                console.error('❌ Permission denied:', permErr2);
                return [];
            }
        }

        const devices = await navigator.mediaDevices.enumerateDevices();
        const cameras = devices.filter(device => device.kind === 'videoinput');
        
        console.log('📷 Cameras found:', cameras.length);
        if (cameras.length === 0) {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                const tracks = stream.getVideoTracks();
                if (tracks.length > 0) {
                    console.log('✅ Found camera via getUserMedia:', tracks[0].label);
                    return [{
                        deviceId: 'default',
                        label: tracks[0].label || 'Default Camera',
                        kind: 'videoinput'
                    }];
                }
                stream.getTracks().forEach(track => track.stop());
            } catch (e) {
                console.error('❌ getUserMedia test failed:', e);
            }
            return [];
        }
        
        cameras.forEach((cam, i) => {
            console.log(`  Camera ${i}: ${cam.label || 'Unnamed'}`);
        });
        
        return cameras;
    } catch (err) {
        console.error('❌ Error enumerating cameras:', err);
        return getCamerasFallback();
    }
}

async function getCamerasFallback() {
    try {
        console.log('🔄 Trying fallback camera detection...');
        const stream = await navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: 'environment' } 
        });
        const tracks = stream.getVideoTracks();
        const cameras = [];
        if (tracks.length > 0) {
            cameras.push({
                deviceId: 'default',
                label: tracks[0].label || 'Default Camera',
                kind: 'videoinput'
            });
            console.log('✅ Fallback found camera:', tracks[0].label);
        }
        stream.getTracks().forEach(track => track.stop());
        return cameras;
    } catch (err) {
        console.error('❌ Fallback failed:', err);
        return [];
    }
}

async function checkTorchSupport() {
    try {
        if (!html5QrCode || !html5QrCode._videoElement) return false;
        const video = html5QrCode._videoElement;
        if (video.srcObject) {
            const tracks = video.srcObject.getVideoTracks();
            if (tracks.length > 0) {
                const track = tracks[0];
                const capabilities = track.getCapabilities();
                return capabilities && capabilities.torch === true;
            }
        }
        return false;
    } catch (err) {
        console.warn('Torch support check failed:', err);
        return false;
    }
}

// ====== START / STOP SCANNER ======

async function startScanner(cameraId = null) {
    if (isStarting) return;
    isStarting = true;
    startBtn.disabled = true;
    statusElement.textContent = "⏳ Starting camera...";
    statusElement.className = "scanner-status text-info";

    try {
        // Cleanup old scanner
        if (html5QrCode) {
            try {
                await html5QrCode.stop();
                await html5QrCode.clear();
            } catch (e) {}
            html5QrCode = null;
        }

        // Get cameras with retry
        let attempts = 0;
        while (cameraList.length === 0 && attempts < MAX_SCAN_ATTEMPTS) {
            attempts++;
            console.log(`🔄 Attempt ${attempts} to get cameras...`);
            cameraList = await getCameras();
            if (cameraList.length === 0 && attempts < MAX_SCAN_ATTEMPTS) {
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
        }

        if (cameraList.length === 0) {
            if (!isMediaDevicesSupported()) {
                throw new Error('Browser does not support camera access. Please use a modern browser (Chrome, Firefox, Edge) with HTTPS.');
            }
            throw new Error('No camera found. Please:\n1. Allow camera permissions\n2. Connect a camera\n3. Use HTTPS or localhost\n4. Close other apps using camera');
        }

        // Select camera - prefer back/environment camera
        let selectedCameraId = cameraId;
        if (!selectedCameraId) {
            const envCam = cameraList.find(c => 
                c.label && (
                    c.label.toLowerCase().includes('back') || 
                    c.label.toLowerCase().includes('environment') ||
                    c.label.toLowerCase().includes('rear')
                )
            );
            selectedCameraId = envCam ? envCam.deviceId : cameraList[0].deviceId;
            if (!selectedCameraId || selectedCameraId === 'default') {
                selectedCameraId = cameraList[0].deviceId;
            }
        }

        currentCameraId = selectedCameraId;
        console.log('📷 Using camera:', selectedCameraId);

        html5QrCode = new Html5Qrcode(qrRegionId);

        if (scannerOverlay) {
            scannerOverlay.style.display = 'flex';
        }

        const config = {
            fps: 20,
            qrbox: { width: 220, height: 220 },
            aspectRatio: 1.0
        };

        let startSuccess = false;
        try {
            await html5QrCode.start(
                { deviceId: { exact: selectedCameraId } },
                config,
                onScanSuccess,
                onScanError
            );
            startSuccess = true;
        } catch (err) {
            console.warn('Start with exact deviceId failed:', err);
            try {
                await html5QrCode.start(
                    { facingMode: 'environment' },
                    config,
                    onScanSuccess,
                    onScanError
                );
                startSuccess = true;
            } catch (err2) {
                console.warn('Start with facingMode failed:', err2);
                try {
                    await html5QrCode.start(
                        {},
                        config,
                        onScanSuccess,
                        onScanError
                    );
                    startSuccess = true;
                } catch (err3) {
                    console.warn('Start with default failed:', err3);
                    throw new Error('Could not start camera. Please check permissions and try again.');
                }
            }
        }

        if (!startSuccess) {
            throw new Error('Failed to start camera');
        }

        isScanning = true;
        isStarting = false;
        startBtn.disabled = true;
        stopBtn.disabled = false;
        
        // Check torch support
        setTimeout(async () => {
            torchSupported = await checkTorchSupport();
            if (torchSupported) {
                torchBtn.disabled = false;
                torchBtn.style.opacity = '1';
                torchBtn.title = 'Toggle Torch (T)';
                console.log('💡 Torch is supported on this camera');
            } else {
                torchBtn.disabled = true;
                torchBtn.style.opacity = '0.5';
                torchBtn.title = 'Torch not supported on this camera';
                console.log('💡 Torch is NOT supported on this camera');
            }
        }, 500);
        
        statusElement.textContent = "✅ Scanner active. Point camera at QR code.";
        statusElement.className = "scanner-status text-success";
        resultsElement.style.display = 'none';
        
        isTorchOn = false;
        torchBtn.classList.remove('active');
        torchBtn.innerHTML = '<i class="fas fa-lightbulb"></i> Torch';

        console.log('✅ Scanner started successfully');

    } catch (err) {
        isStarting = false;
        startBtn.disabled = false;
        stopBtn.disabled = true;
        torchBtn.disabled = true;
        torchBtn.style.opacity = '0.5';
        
        console.error('❌ Start scanner error:', err);
        
        let errorMsg = err.message || 'Could not start camera.';
        
        if (errorMsg.includes('Permission denied') || errorMsg.includes('NotAllowedError')) {
            errorMsg = '⚠️ Camera permission denied.\n\nPlease:\n1. Click the camera icon in the address bar\n2. Select "Allow" for camera access\n3. Refresh the page';
        } else if (errorMsg.includes('NotFoundError')) {
            errorMsg = '❌ No camera found.\n\nPlease:\n1. Connect a camera\n2. Allow camera permissions\n3. Use HTTPS or localhost';
        } else if (errorMsg.includes('NotReadableError')) {
            errorMsg = '⚠️ Camera is in use by another application.\n\nPlease close other apps using the camera (Zoom, Teams, WhatsApp, etc.)';
        } else if (errorMsg.includes('OverconstrainedError')) {
            errorMsg = '⚠️ Camera settings not supported.\n\nTrying with default settings...';
            try {
                await startScanner(null);
                return;
            } catch (retryErr) {
                errorMsg = '❌ Failed to start camera. Please try again.';
            }
        }
        
        statusElement.textContent = errorMsg;
        statusElement.className = "scanner-status text-danger";
        statusElement.style.whiteSpace = 'pre-line';
        
        resultsElement.style.display = 'block';
        resultsElement.innerHTML = `
            <div style="text-align:left; padding:10px;">
                <strong>💡 Troubleshooting Tips:</strong><br>
                1. ✅ Ensure camera is connected<br>
                2. 🔒 Use HTTPS or localhost<br>
                3. 🔑 Allow camera permissions<br>
                4. 📱 Close other camera apps<br>
                5. 🔄 Refresh the page<br>
                6. 🌐 Try a different browser
            </div>
        `;
        resultsElement.className = "scanner-status text-warning";
        
        if (scannerOverlay) {
            scannerOverlay.style.display = 'none';
        }
        
        showNotification("⚠️ " + errorMsg.replace(/\n/g, ' '), "error");
    }
}

async function stopScanner() {
    if (html5QrCode && isScanning) {
        try {
            await html5QrCode.stop();
            isScanning = false;
            startBtn.disabled = false;
            stopBtn.disabled = true;
            
            if (isTorchOn) {
                try {
                    await toggleTorch();
                } catch (e) {}
            }
            
            statusElement.textContent = "⏸️ Scanner stopped.";
            statusElement.className = "scanner-status text-warning";
            
            if (scannerOverlay) {
                scannerOverlay.style.display = 'none';
            }
            
            torchBtn.classList.remove('active');
            torchBtn.innerHTML = '<i class="fas fa-lightbulb"></i> Torch';
            torchBtn.disabled = true;
            torchBtn.style.opacity = '0.5';
            isTorchOn = false;
            
            console.log('⏸️ Scanner stopped');
        } catch (err) {
            console.warn('Stop error:', err);
        }
    }
}

// ====== TORCH CONTROL ======

async function toggleTorch() {
    if (!html5QrCode || !isScanning) {
        showNotification('⚠️ Please start the scanner first to use torch.', 'error');
        return;
    }

    if (!torchSupported) {
        showNotification('⚠️ Torch is not supported on this camera.', 'error');
        return;
    }

    try {
        isTorchOn = !isTorchOn;
        
        if (typeof html5QrCode.toggleTorch === 'function') {
            await html5QrCode.toggleTorch(isTorchOn);
        } else if (html5QrCode._videoElement) {
            const video = html5QrCode._videoElement;
            if (video.srcObject) {
                const tracks = video.srcObject.getVideoTracks();
                if (tracks.length > 0) {
                    const track = tracks[0];
                    const capabilities = track.getCapabilities();
                    if (capabilities && capabilities.torch) {
                        await track.applyConstraints({
                            advanced: [{ torch: isTorchOn }]
                        });
                    } else {
                        throw new Error('Torch not supported on this device');
                    }
                } else {
                    throw new Error('No video tracks available');
                }
            } else {
                throw new Error('No video stream available');
            }
        } else {
            throw new Error('Scanner not properly initialized');
        }
        
        torchBtn.classList.toggle('active', isTorchOn);
        torchBtn.innerHTML = isTorchOn ? 
            '<i class="fas fa-lightbulb"></i> Torch ON' : 
            '<i class="fas fa-lightbulb"></i> Torch';
            
        console.log(`💡 Torch ${isTorchOn ? 'ON' : 'OFF'}`);
        
    } catch (err) {
        console.warn('Torch error:', err);
        isTorchOn = !isTorchOn;
        torchBtn.classList.remove('active');
        torchBtn.innerHTML = '<i class="fas fa-lightbulb"></i> Torch';
        torchSupported = false;
        torchBtn.disabled = true;
        torchBtn.style.opacity = '0.5';
        torchBtn.title = 'Torch not supported on this camera';
        showNotification('⚠️ Torch not supported on this device or camera.', 'error');
    }
}

// ====== SWITCH CAMERA ======

async function switchCamera() {
    if (cameraList.length < 2) {
        showNotification('⚠️ Only one camera available.', 'error');
        return;
    }

    try {
        await stopScanner();
        
        cameraIndex = (cameraIndex + 1) % cameraList.length;
        const camera = cameraList[cameraIndex];
        
        statusElement.textContent = `🔄 Switching to: ${camera.label || 'Camera ' + (cameraIndex + 1)}...`;
        statusElement.className = "scanner-status text-info";
        resultsElement.style.display = 'none';
        
        setTimeout(() => {
            startScanner(camera.deviceId);
        }, 500);
        
        console.log(`🔄 Switching to camera ${cameraIndex + 1}`);
        
    } catch (err) {
        console.error('Switch camera error:', err);
        showNotification('❌ Failed to switch camera.', 'error');
        startScanner(currentCameraId);
    }
}

// ====== QR SCAN HANDLERS ======

function onScanSuccess(decodedText, decodedResult) {
    console.log("📱 QR scanned:", decodedText);
    
    if (html5QrCode && isScanning) {
        html5QrCode.pause();
        isScanning = false;
        
        statusElement.textContent = "⏳ Verifying asset...";
        statusElement.className = "scanner-status text-info";
        resultsElement.style.display = 'block';
        resultsElement.textContent = "⏳ Verifying with server...";
        resultsElement.className = "scanner-status text-info";
        
        verifyQRCode(decodedText);
    } else {
        verifyQRCode(decodedText);
    }
}

function onScanError(errorMessage) {
    // Ignore - these are frequent and not critical
}

// ====== VERIFY QR WITH SERVER ======

function verifyQRCode(qrCode) {
    const url = API_URL + '?qr=' + encodeURIComponent(qrCode);
    console.log('🌐 Fetching:', url);

    fetch(url)
        .then(response => {
            console.log('📡 Response status:', response.status);
            return response.text().then(text => {
                console.log('📄 Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    return { ok: response.ok, data };
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response.');
                }
            });
        })
        .then(({ ok, data }) => {
            if (!ok) {
                if (data && data.error) {
                    throw data;
                } else {
                    throw { error: 'Server error (status ' + ok + ')' };
                }
            }
            if (data.success) {
                const asset = data.asset;
                // Redirect to report_fault.php with QR code parameter
                window.location.href = 'report_fault.php?qr=' + encodeURIComponent(qrCode);
            } else {
                let msg = data.error || 'Invalid QR code';
                showNotification("❌ " + msg, "error");
                resetScanner();
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            let msg = 'Network error. Please try again.';
            if (err.error) {
                msg = err.error;
                if (err.asset) {
                    showNotification("⚠️ " + msg + " (Asset: " + err.asset.name + ")", "error");
                } else {
                    showNotification("❌ " + msg, "error");
                }
            } else if (err.message) {
                showNotification("❌ " + err.message, "error");
            } else {
                showNotification("❌ Network error. Please try again.", "error");
            }
            resetScanner();
        });
}

function resetScanner() {
    statusElement.textContent = "⚠️ Scan failed. Use controls below to try again.";
    statusElement.className = "scanner-status text-warning";
    resultsElement.textContent = "Click 'Start' to try again or 'Switch' to change camera.";
    resultsElement.className = "scanner-status text-warning";
    resultsElement.style.display = 'block';
    isScanning = false;
    startBtn.disabled = false;
    stopBtn.disabled = true;
    torchBtn.disabled = true;
    torchBtn.style.opacity = '0.5';
    
    if (scannerOverlay) {
        scannerOverlay.style.display = 'none';
    }
}

// ====== NOTIFICATION ======

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

// ====== MODAL EVENTS ======

const scannerModal = document.getElementById('qrScannerModal');

if (scannerModal) {
    scannerModal.addEventListener('shown.bs.modal', async function () {
        isStarting = false;
        startBtn.disabled = false;
        stopBtn.disabled = true;
        torchBtn.disabled = true;
        torchBtn.style.opacity = '0.5';
        torchBtn.title = 'Start scanner first to use torch';
        
        statusElement.textContent = "📷 Detecting cameras...";
        statusElement.className = "scanner-status text-info";
        resultsElement.style.display = 'none';
        
        cameraList = await getCameras();
        
        if (cameraList.length === 0) {
            await new Promise(resolve => setTimeout(resolve, 1000));
            cameraList = await getCameras();
        }
        
        if (cameraList.length === 0) {
            statusElement.textContent = "❌ No camera found. Please check:\n1. Camera permissions\n2. Camera connection\n3. Use HTTPS/localhost";
            statusElement.className = "scanner-status text-danger";
            statusElement.style.whiteSpace = 'pre-line';
            startBtn.disabled = true;
            
            resultsElement.style.display = 'block';
            resultsElement.innerHTML = `
                <div style="text-align:left; padding:10px;">
                    <strong>💡 Quick Fixes:</strong><br>
                    1. 🔒 Use HTTPS or localhost<br>
                    2. 🔑 Allow camera permissions<br>
                    3. 📱 Close other camera apps<br>
                    4. 🔄 Refresh the page
                </div>
            `;
            resultsElement.className = "scanner-status text-warning";
        } else {
            statusElement.textContent = `📷 ${cameraList.length} camera(s) found. Click "Start" to begin.`;
            statusElement.className = "scanner-status text-info";
            startBtn.disabled = false;
            resultsElement.style.display = 'none';
            
            setTimeout(() => {
                startScanner();
            }, 800);
        }
    });

    scannerModal.addEventListener('hidden.bs.modal', async function () {
        await stopScanner();
        if (html5QrCode) {
            try {
                await html5QrCode.clear();
            } catch (e) {}
            html5QrCode = null;
        }
        isScanning = false;
        startBtn.disabled = false;
        stopBtn.disabled = true;
        torchBtn.disabled = true;
        torchBtn.style.opacity = '0.5';
        statusElement.textContent = "📷 Ready";
        statusElement.className = "scanner-status text-muted";
        statusElement.style.whiteSpace = 'normal';
        resultsElement.style.display = 'none';
        if (scannerOverlay) {
            scannerOverlay.style.display = 'none';
        }
    });
}

// ====== BUTTON EVENT LISTENERS ======

startBtn.addEventListener('click', function() {
    startScanner();
});

stopBtn.addEventListener('click', async function() {
    await stopScanner();
});

torchBtn.addEventListener('click', toggleTorch);

switchBtn.addEventListener('click', switchCamera);

document.getElementById('manualSelectBtn').addEventListener('click', function() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
    if (modal) modal.hide();
    window.location.href = 'report_fault.php';
});

document.getElementById('closeScannerBtn').addEventListener('click', function() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
    if (modal) modal.hide();
});

// ====== KEYBOARD SHORTCUTS ======

document.addEventListener('keydown', function(e) {
    if (e.key === 't' || e.key === 'T') {
        if (document.getElementById('qrScannerModal').classList.contains('show')) {
            e.preventDefault();
            toggleTorch();
        }
    }
    if (e.key === 's' || e.key === 'S') {
        if (document.getElementById('qrScannerModal').classList.contains('show')) {
            e.preventDefault();
            switchCamera();
        }
    }
    if (e.key === 'Escape') {
        const modal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
        if (modal) modal.hide();
    }
});

// ====== SEARCH ======
let searchTimeout;
function searchRequests(value) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function () {
        const url = new URL(window.location.href);
        if (value.trim()) {
            url.searchParams.set('search', value.trim());
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }, 300);
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    const url = new URL(window.location.href);
    url.searchParams.delete('search');
    window.location.href = url.toString();
}

// ====== TOAST AUTO-CLOSE ======
function closeToast() {
    const toasts = document.querySelectorAll('.success-toast, .error-toast');
    toasts.forEach(function (toast) {
        toast.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(function () { toast.style.display = 'none'; }, 300);
    });
    const url = new URL(window.location.href);
    url.searchParams.delete('success');
    url.searchParams.delete('error');
    url.searchParams.delete('message');
    window.history.replaceState({}, document.title, url.toString());
}

document.addEventListener('DOMContentLoaded', function () {
    const toasts = document.querySelectorAll('.success-toast, .error-toast');
    if (toasts.length > 0) {
        setTimeout(closeToast, 4000);
    }

    const searchInput = document.getElementById('searchInput');
    const clearBtn = document.querySelector('.clear-search');
    if (searchInput && clearBtn) {
        searchInput.addEventListener('input', function () {
            clearBtn.classList.toggle('show', this.value.length > 0);
        });
    }
});

// ====== VIEW REQUEST ======
function viewRequest(requestId) {
    const modal = new bootstrap.Modal(document.getElementById('viewRequestModal'));
    const body = document.getElementById('requestDetailsBody');
    body.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading request details...</p></div>';
    modal.show();

    fetch('<?php echo BASE_URL; ?>api/get_request_details.php?id=' + requestId)
        .then(response => response.text())
        .then(html => {
            body.innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            body.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Failed to load request details. Please try again.
                </div>
            `;
        });
}

console.log('✅ My Requests Loaded with Advanced QR Scanner');
console.log('💡 Keyboard shortcuts: [T] Torch | [S] Switch Camera | [ESC] Close');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>