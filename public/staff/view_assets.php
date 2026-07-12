<?php
// public/admin/assets.php - Asset Management

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/settings_helper.php';

// Allow only System Administrator (or add ICT Technician if needed)
requireRole('Staff');

// Load the Asset model
require_once __DIR__ . '/../../includes/models/StaffAsset.php';
$assetModel = new Asset();
$db = getDB();

// ====== GET FILTERS ======
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_category = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ====== HANDLE ACTIONS ======
$action = isset($_GET['action']) ? $_GET['action'] : '';
$asset_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ====== GENERATE / REGENERATE QR CODE ======
if ($action === 'generate_qr' && $asset_id > 0) {
    $asset = $assetModel->getById($asset_id);
    if ($asset) {
        $result = $assetModel->regenerateQR($asset_id);
        if ($result) {
            header('Location: assets.php?qr_generated=1&name=' . urlencode($asset['name']));
            exit();
        } else {
            header('Location: assets.php?error=qr_failed');
            exit();
        }
    } else {
        header('Location: assets.php?error=asset_not_found');
        exit();
    }
}

// ====== ADD ASSET ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_asset'])) {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'category' => trim($_POST['category'] ?? ''),
        'model' => trim($_POST['model'] ?? ''),
        'serial_number' => trim($_POST['serial_number'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'purchase_date' => !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null,
        'warranty_expiry' => !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : null,
        'status' => $_POST['status'] ?? 'Available',
        'assigned_to' => !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null,
        'last_maintenance_date' => !empty($_POST['last_maintenance_date']) ? $_POST['last_maintenance_date'] : null,
        'next_maintenance_date' => !empty($_POST['next_maintenance_date']) ? $_POST['next_maintenance_date'] : null,
    ];

    if (empty($data['name']) || empty($data['category'])) {
        header('Location: assets.php?error=add_failed');
        exit();
    }

    $new_id = $assetModel->create($data);
    if ($new_id) {
        header('Location: assets.php?added=1&name=' . urlencode($data['name']));
        exit();
    } else {
        header('Location: assets.php?error=add_failed');
        exit();
    }
}

// ====== EDIT ASSET ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_asset'])) {
    $asset_id = intval($_POST['asset_id']);
    $data = [
        'asset_tag' => trim($_POST['asset_tag'] ?? ''),
        'qr_code' => trim($_POST['qr_code'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'category' => trim($_POST['category'] ?? ''),
        'model' => trim($_POST['model'] ?? ''),
        'serial_number' => trim($_POST['serial_number'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'purchase_date' => !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null,
        'warranty_expiry' => !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : null,
        'status' => $_POST['status'] ?? 'Available',
        'assigned_to' => !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null,
        'last_maintenance_date' => !empty($_POST['last_maintenance_date']) ? $_POST['last_maintenance_date'] : null,
        'next_maintenance_date' => !empty($_POST['next_maintenance_date']) ? $_POST['next_maintenance_date'] : null,
    ];

    if (empty($data['name']) || empty($data['category']) || empty($data['asset_tag']) || empty($data['qr_code'])) {
        header('Location: assets.php?error=edit_failed');
        exit();
    }

    if ($assetModel->update($asset_id, $data)) {
        header('Location: assets.php?updated=1&name=' . urlencode($data['name']));
        exit();
    } else {
        header('Location: assets.php?error=edit_failed');
        exit();
    }
}

// ====== DELETE ASSET ======
if ($action === 'delete' && $asset_id > 0) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_requests WHERE asset_id = ?");
    $stmt->execute([$asset_id]);
    $result = $stmt->fetch();
    if ($result && $result['count'] > 0) {
        $asset = $assetModel->getById($asset_id);
        $name = $asset ? $asset['name'] : 'Asset';
        header('Location: assets.php?error=has_requests&name=' . urlencode($name));
        exit();
    } else {
        $asset = $assetModel->getById($asset_id);
        $name = $asset ? $asset['name'] : 'Asset';
        if ($assetModel->delete($asset_id)) {
            header('Location: assets.php?deleted=1&name=' . urlencode($name));
            exit();
        } else {
            header('Location: assets.php?error=delete_failed');
            exit();
        }
    }
}

// ====== TOGGLE STATUS ======
if ($action === 'toggle_status' && $asset_id > 0) {
    $asset = $assetModel->getById($asset_id);
    if ($asset) {
        $statuses = ['Available', 'In Use', 'Under Maintenance', 'Retired'];
        $current = $asset['status'];
        $index = array_search($current, $statuses);
        $next_index = ($index + 1) % count($statuses);
        $new_status = $statuses[$next_index];
        $data = $asset;
        $data['status'] = $new_status;
        if ($assetModel->update($asset_id, $data)) {
            header('Location: assets.php?toggled=1&status=' . urlencode($new_status) . '&name=' . urlencode($asset['name']));
            exit();
        } else {
            header('Location: assets.php?error=toggle_failed');
            exit();
        }
    } else {
        header('Location: assets.php?error=asset_not_found');
        exit();
    }
}

// ====== FETCH ASSETS ======
$filters = [
    'status' => $filter_status,
    'category' => $filter_category,
    'search' => $search
];
$assets = $assetModel->getAll($filters);
$categories = $assetModel->getCategories();
$users = $assetModel->getUsers();

$edit_asset = null;
if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $edit_asset = $assetModel->getById(intval($_GET['edit']));
}

// ====== INCLUDE HEADER (from public/includes/) ======
include __DIR__ . '/../includes/header.php';
?>


<!-- ====== SUCCESS TOASTS ====== -->
<?php if (isset($_GET['added'])): ?>
    <div class="success-toast show" id="successToast">
        <div class="toast-icon"><i class="fas fa-plus-circle"></i></div>
        <div class="toast-content">
            <div class="toast-title">✅ Asset Added!</div>
            <p class="toast-message"><?php echo htmlspecialchars($_GET['name'] ?? 'Asset'); ?> has been added successfully!
            </p>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="success-toast show" id="successToast">
        <div class="toast-icon"><i class="fas fa-edit"></i></div>
        <div class="toast-content">
            <div class="toast-title">✅ Asset Updated!</div>
            <p class="toast-message"><?php echo htmlspecialchars($_GET['name'] ?? 'Asset'); ?> has been updated
                successfully!</p>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="success-toast show" id="successToast">
        <div class="toast-icon"><i class="fas fa-trash-alt"></i></div>
        <div class="toast-content">
            <div class="toast-title">🗑️ Asset Deleted!</div>
            <p class="toast-message"><?php echo htmlspecialchars($_GET['name'] ?? 'Asset'); ?> has been deleted
                successfully!</p>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['toggled'])): ?>
    <div class="success-toast show" id="successToast">
        <div class="toast-icon"><i class="fas fa-sync-alt"></i></div>
        <div class="toast-content">
            <div class="toast-title">🔄 Status Changed!</div>
            <p class="toast-message"><?php echo htmlspecialchars($_GET['name'] ?? 'Asset'); ?> status updated to
                <strong><?php echo htmlspecialchars($_GET['status']); ?></strong>!
            </p>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['qr_generated'])): ?>
    <div class="success-toast show" id="successToast">
        <div class="toast-icon"><i class="fas fa-qrcode"></i></div>
        <div class="toast-content">
            <div class="toast-title">✅ QR Code Generated!</div>
            <p class="toast-message">QR Code for <?php echo htmlspecialchars($_GET['name'] ?? 'Asset'); ?> has been
                generated!</p>
        </div>
    </div>
<?php endif; ?>

<!-- ====== ERROR TOASTS ====== -->
<?php if (isset($_GET['error'])): ?>
    <div class="error-toast show" id="errorToast">
        <div class="toast-icon-error"><i class="fas fa-exclamation-circle"></i></div>
        <div class="toast-content">
            <div class="toast-title-error">❌ Error!</div>
            <p class="toast-message">
                <?php
                $error = $_GET['error'];
                if ($error === 'has_requests')
                    echo htmlspecialchars($_GET['name'] ?? 'Asset') . ' has maintenance requests and cannot be deleted!';
                elseif ($error === 'asset_not_found')
                    echo 'Asset not found!';
                elseif ($error === 'email_exists')
                    echo 'Duplicate entry!';
                elseif ($error === 'add_failed')
                    echo 'Failed to add asset! Please try again.';
                elseif ($error === 'edit_failed')
                    echo 'Failed to update asset! Please try again.';
                elseif ($error === 'delete_failed')
                    echo 'Failed to delete asset!';
                elseif ($error === 'toggle_failed')
                    echo 'Failed to change asset status!';
                else
                    echo 'An error occurred!';
                ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<style>
    /* ====== TOASTS (reuse from users.php) ====== */
    .success-toast,
    .error-toast {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 9999;
        background: white;
        border-radius: 16px;
        padding: 20px 30px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        gap: 15px;
        min-width: 300px;
        max-width: 450px;
        animation: slideInRight 0.4s ease;
    }

    .success-toast {
        border-left: 5px solid #28a745;
    }

    .error-toast {
        border-left: 5px solid #dc3545;
    }

    .success-toast .toast-icon,
    .error-toast .toast-icon-error {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .success-toast .toast-icon {
        background: #28a745;
        color: white;
        font-size: 1.5rem;
    }

    .error-toast .toast-icon-error {
        background: #dc3545;
        color: white;
        font-size: 1.5rem;
    }

    .success-toast .toast-content,
    .error-toast .toast-content {
        flex: 1;
    }

    .success-toast .toast-content .toast-title {
        font-weight: 700;
        font-size: 1rem;
        color: #28a745;
        margin-bottom: 2px;
    }

    .error-toast .toast-content .toast-title-error {
        font-weight: 700;
        font-size: 1rem;
        color: #dc3545;
        margin-bottom: 2px;
    }

    .success-toast .toast-content .toast-message,
    .error-toast .toast-content .toast-message {
        font-size: 0.9rem;
        color: #495057;
        margin: 0;
    }

    .success-toast .toast-content .toast-message strong {
        color: #1a73e8;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100px);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }

        to {
            transform: translateX(100px);
            opacity: 0;
        }
    }

    /* ====== CONFIRMATION ====== */
    .confirm-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9998;
        display: none;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
    }

    .confirm-overlay.show {
        display: flex;
        animation: fadeIn 0.3s ease;
    }

    .confirm-modal {
        background: white;
        border-radius: 20px;
        padding: 40px 50px;
        text-align: center;
        max-width: 420px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: bounceIn 0.4s ease;
    }

    .confirm-modal .confirm-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
    }

    .confirm-modal .confirm-icon.danger {
        background: #dc3545;
        color: white;
    }

    .confirm-modal .confirm-icon.warning {
        background: #ffc107;
        color: #212529;
    }

    .confirm-modal .confirm-icon.success {
        background: #28a745;
        color: white;
    }

    .confirm-modal .confirm-icon i {
        font-size: 2rem;
    }

    .confirm-modal .confirm-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 8px;
    }

    .confirm-modal .confirm-message {
        color: #6c757d;
        font-size: 0.95rem;
        margin-bottom: 20px;
    }

    .confirm-modal .confirm-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    .confirm-modal .confirm-actions .btn {
        padding: 10px 25px;
        border-radius: 50px;
        font-weight: 600;
        min-width: 100px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
    }

    .confirm-modal .confirm-actions .btn:hover {
        transform: scale(1.05);
    }

    .confirm-modal .confirm-actions .btn-cancel {
        background: #e9ecef;
        color: #495057;
    }

    .confirm-modal .confirm-actions .btn-cancel:hover {
        background: #dee2e6;
    }

    .confirm-modal .confirm-actions .btn-danger {
        background: #dc3545;
        color: white;
    }

    .confirm-modal .confirm-actions .btn-danger:hover {
        background: #c82333;
    }

    .confirm-modal .confirm-actions .btn-warning {
        background: #ffc107;
        color: #212529;
    }

    .confirm-modal .confirm-actions .btn-warning:hover {
        background: #e0a800;
    }

    .confirm-modal .confirm-actions .btn-success {
        background: #28a745;
        color: white;
    }

    .confirm-modal .confirm-actions .btn-success:hover {
        background: #218838;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @keyframes bounceIn {
        0% {
            transform: scale(0.5);
            opacity: 0;
        }

        60% {
            transform: scale(1.05);
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
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

    .filter-btn:hover {
        border-color: #1a73e8;
        color: #1a73e8;
    }

    .filter-btn.active {
        background: #1a73e8;
        border-color: #1a73e8;
        color: white;
    }

    .filter-btn .badge {
        font-size: 0.6rem;
        margin-left: 4px;
        background: rgba(0, 0, 0, 0.1);
    }

    .filter-btn.active .badge {
        background: rgba(255, 255, 255, 0.3);
        color: white;
    }

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
        box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
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

    .search-box .clear-search.show {
        display: block;
    }

    .search-box .clear-search:hover {
        color: #dc3545;
    }

    /* ====== TABLE ====== */
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-Available {
        background: #d4edda;
        color: #155724;
    }

    .status-In\ Use {
        background: #cce5ff;
        color: #004085;
    }

    .status-Under\ Maintenance {
        background: #fff3cd;
        color: #856404;
    }

    .status-Retired {
        background: #f8d7da;
        color: #721c24;
    }

    .asset-icon {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #1a73e8, #0d47a1);
        color: white;
        font-size: 0.9rem;
        flex-shrink: 0;
    }

    .btn-action {
        padding: 4px 10px;
        font-size: 12px;
        margin: 2px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-action:hover {
        transform: scale(1.05);
    }

    .btn-action i {
        font-size: 13px;
    }

    .modal-header {
        background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
        color: white;
    }

    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }

    .table th {
        background: #f8f9fa;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #dee2e6;
    }

    .table td {
        vertical-align: middle;
    }

    .card {
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, 0.05);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    }

    .card-header {
        background: white;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        padding: 15px 20px;
        font-weight: 600;
    }

    .no-results {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }

    .no-results i {
        font-size: 3rem;
        color: #dee2e6;
        margin-bottom: 15px;
    }

    /* ====== RESPONSIVE ====== */
    @media (max-width: 768px) {

        .success-toast,
        .error-toast {
            top: 70px;
            right: 10px;
            left: 10px;
            min-width: auto;
            padding: 15px 20px;
        }

        .confirm-modal {
            padding: 30px 20px;
        }

        .confirm-modal .confirm-actions {
            flex-direction: column;
        }

        .confirm-modal .confirm-actions .btn {
            width: 100%;
        }

        .filter-group {
            gap: 5px;
        }

        .filter-btn {
            padding: 3px 10px;
            font-size: 0.7rem;
        }

        .search-box {
            min-width: 150px;
        }

        .search-box input {
            font-size: 0.75rem;
            padding: 5px 10px 5px 28px;
        }

        .table-responsive {
            font-size: 0.8rem;
        }

        .btn-action {
            padding: 2px 6px;
            font-size: 10px;
        }

        .btn-action i {
            font-size: 10px;
        }

        .stat-cards .card {
            padding: 10px;
        }

        .stat-cards h5 {
            font-size: 1.1rem;
        }
    }

    @media (max-width: 576px) {

        .success-toast,
        .error-toast {
            top: 60px;
            right: 5px;
            left: 5px;
            padding: 12px 15px;
            border-radius: 12px;
        }

        .search-box {
            min-width: 120px;
        }

        .filter-group .filter-label {
            display: none;
        }
    }
</style>

<!-- ====== CONFIRMATION OVERLAY ====== -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-modal">
        <div class="confirm-icon" id="confirmIcon"><i class="fas fa-exclamation-triangle"></i></div>
        <h5 class="confirm-title" id="confirmTitle">Are you sure?</h5>
        <p class="confirm-message" id="confirmMessage">This action cannot be undone.</p>
        <div class="confirm-actions">
            <button class="btn btn-cancel" onclick="closeConfirm()">Cancel</button>
            <button class="btn btn-danger" id="confirmBtn" onclick="executeConfirm()">Yes, Proceed</button>
        </div>
    </div>
</div>

<!-- ====== MAIN CONTENT ====== -->
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-boxes text-primary"></i> Asset Management</h4>
            <small class="text-muted">Manage ICT assets</small>
        </div>

    </div>

    <!-- ====== STATS ROW ====== -->
    <?php
    $total = count($assets);
    $available = count(array_filter($assets, fn($a) => $a['status'] === 'Available'));
    $inuse = count(array_filter($assets, fn($a) => $a['status'] === 'In Use'));
    $maintenance = count(array_filter($assets, fn($a) => $a['status'] === 'Under Maintenance'));
    $retired = count(array_filter($assets, fn($a) => $a['status'] === 'Retired'));
    ?>
    <div class="row g-2 mb-3 stat-cards">
        <div class="col-md-3 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-primary mb-0"><?php echo $total; ?></h5>
                <small class="text-muted">Total</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-success mb-0"><?php echo $available; ?></h5>
                <small class="text-muted">Available</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-info mb-0"><?php echo $inuse; ?></h5>
                <small class="text-muted">In Use</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card p-2 text-center">
                <h5 class="text-warning mb-0"><?php echo $maintenance; ?></h5>
                <small class="text-muted">Maintenance</small>
            </div>
        </div>
    </div>

    <!-- ====== FILTERS ====== -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Status Filters -->
                <div class="filter-group">
                    <span class="filter-label">Status:</span>
                    <a href="?status=all&category=<?php echo $filter_category; ?>&search=<?php echo urlencode($search); ?>"
                        class="filter-btn <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                        All <span class="badge"><?php echo $total; ?></span>
                    </a>
                    <a href="?status=Available&category=<?php echo $filter_category; ?>&search=<?php echo urlencode($search); ?>"
                        class="filter-btn <?php echo $filter_status === 'Available' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle text-success"></i> Available <span
                            class="badge"><?php echo $available; ?></span>
                    </a>
                    <a href="?status=In%20Use&category=<?php echo $filter_category; ?>&search=<?php echo urlencode($search); ?>"
                        class="filter-btn <?php echo $filter_status === 'In Use' ? 'active' : ''; ?>">
                        <i class="fas fa-user text-info"></i> In Use <span class="badge"><?php echo $inuse; ?></span>
                    </a>
                    <a href="?status=Under%20Maintenance&category=<?php echo $filter_category; ?>&search=<?php echo urlencode($search); ?>"
                        class="filter-btn <?php echo $filter_status === 'Under Maintenance' ? 'active' : ''; ?>">
                        <i class="fas fa-tools text-warning"></i> Maintenance <span
                            class="badge"><?php echo $maintenance; ?></span>
                    </a>
                    <a href="?status=Retired&category=<?php echo $filter_category; ?>&search=<?php echo urlencode($search); ?>"
                        class="filter-btn <?php echo $filter_status === 'Retired' ? 'active' : ''; ?>">
                        <i class="fas fa-times-circle text-danger"></i> Retired <span
                            class="badge"><?php echo $retired; ?></span>
                    </a>
                </div>

                <span class="text-muted">|</span>

                <!-- Category Filters -->
                <div class="filter-group">
                    <span class="filter-label">Category:</span>
                    <a href="?status=<?php echo $filter_status; ?>&category=all&search=<?php echo urlencode($search); ?>"
                        class="filter-btn <?php echo $filter_category === 'all' ? 'active' : ''; ?>">
                        All
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="?status=<?php echo $filter_status; ?>&category=<?php echo urlencode($cat); ?>&search=<?php echo urlencode($search); ?>"
                            class="filter-btn <?php echo $filter_category === $cat ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($cat); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <span class="text-muted">|</span>

                <!-- Search Box -->
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search assets..."
                        value="<?php echo htmlspecialchars($search); ?>" onkeyup="searchAssets(this.value)">
                    <i class="fas fa-times clear-search <?php echo !empty($search) ? 'show' : ''; ?>"
                        onclick="clearSearch()"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Assets Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list"></i> Assets</span>
            <span class="badge bg-primary"><?php echo $total; ?> found</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="assetsTable" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Asset</th>
                            <th>Tag / QR</th>
                            <th>Category</th>
                            <th>Model</th>
                            <th>Location</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="assetsTableBody">
                        <?php if (count($assets) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($assets as $asset): ?>
                                <?php
                                $status_class = 'status-' . str_replace(' ', '-', $asset['status']);
                                $initials = substr($asset['name'] ?? 'A', 0, 2);
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="asset-icon"><?php echo strtoupper($initials); ?></div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($asset['name']); ?></strong>
                                                <?php if ($asset['serial_number']): ?>
                                                    <br><small class="text-muted">SN:
                                                        <?php echo htmlspecialchars($asset['serial_number']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span
                                            class="badge bg-secondary"><?php echo htmlspecialchars($asset['asset_tag']); ?></span>
                                        <?php if ($asset['qr_image']): ?>
                                            <img src="<?php echo BASE_URL . htmlspecialchars($asset['qr_image']); ?>" alt="QR"
                                                style="width:30px; height:30px; cursor:pointer; border-radius:4px;"
                                                data-bs-toggle="modal" data-bs-target="#qrModal"
                                                data-qr-image="<?php echo BASE_URL . htmlspecialchars($asset['qr_image']); ?>"
                                                data-asset-name="<?php echo htmlspecialchars($asset['name']); ?>"
                                                data-asset-tag="<?php echo htmlspecialchars($asset['asset_tag']); ?>">
                                        <?php else: ?>
                                            <span class="text-muted">No QR</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($asset['category']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['model'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($asset['location'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($asset['assigned_to']): ?>
                                            <span
                                                class="badge bg-light text-dark"><?php echo htmlspecialchars($asset['assigned_to_name'] ?? 'User'); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $asset['status']; ?>
                                        </span>
                                    </td>
                                    
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="no-results">
                                        <i class="fas fa-box-open"></i>
                                        <h6>No assets found</h6>
                                        <p class="text-muted small">Try adjusting your filters or search terms</p>
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

<!-- ====== ADD ASSET MODAL ====== -->
<div class="modal fade" id="addAssetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add New Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_name" class="form-label">Asset Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="add_name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_category" class="form-label">Category <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" id="add_category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_model" class="form-label">Model</label>
                            <input type="text" class="form-control" id="add_model" name="model">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_serial" class="form-label">Serial Number</label>
                            <input type="text" class="form-control" id="add_serial" name="serial_number">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="add_location" class="form-label">Location <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_location" name="location" required>
                            <option value="ICT Department" selected>ICT Department</option>
                            <option value="Finance Department">Finance Department</option>
                            <option value="Social Science Department">Social Science Department</option>
                            <option value="Mathematics Department">Mathematics Department</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_purchase" class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" id="add_purchase" name="purchase_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_warranty" class="form-label">Warranty Expiry</label>
                            <input type="date" class="form-control" id="add_warranty" name="warranty_expiry">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_status" class="form-label">Status</label>
                            <select class="form-select" id="add_status" name="status">
                                <option value="Available">Available</option>
                                <option value="In Use">In Use</option>
                                <option value="Under Maintenance">Under Maintenance</option>
                                <option value="Retired">Retired</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_assigned" class="form-label">Assigned To</label>
                            <select class="form-select" id="add_assigned" name="assigned_to">
                                <option value="">Unassigned</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_last_maintenance" class="form-label">Last Maintenance</label>
                            <input type="date" class="form-control" id="add_last_maintenance"
                                name="last_maintenance_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_next_maintenance" class="form-label">Next Maintenance</label>
                            <input type="date" class="form-control" id="add_next_maintenance"
                                name="next_maintenance_date">
                        </div>
                    </div>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> Asset Tag and QR Code will be auto-generated.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_asset" class="btn btn-primary"><i class="fas fa-save"></i> Add
                        Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== EDIT ASSET MODAL ====== -->
<div class="modal fade" id="editAssetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="asset_id" id="edit_asset_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_name" class="form-label">Asset Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_category" class="form-label">Category <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" id="edit_category" name="category" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_asset_tag" class="form-label">Asset Tag <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_asset_tag" name="asset_tag" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_qr_code" class="form-label">QR Code <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_qr_code" name="qr_code" readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_model" class="form-label">Model</label>
                            <input type="text" class="form-control" id="edit_model" name="model">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_serial" class="form-label">Serial Number</label>
                            <input type="text" class="form-control" id="edit_serial" name="serial_number">
                        </div>
                    </div>
                    <div class="mb-3">

                        <label for="edit_location" class="form-label">Location <span
                                class="text-danger">*</span></label>
                        <select class="form-select" id="edit_location" name="location" required>
                            <option value="ICT Department" selected>ICT Department</option>
                            <option value="Finance Department">Finance Department</option>
                            <option value="Social Science Department">Social Science Department</option>
                            <option value="Mathematics Department">Mathematics Department</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_purchase" class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" id="edit_purchase" name="purchase_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_warranty" class="form-label">Warranty Expiry</label>
                            <input type="date" class="form-control" id="edit_warranty" name="warranty_expiry">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="Available">Available</option>
                                <option value="In Use">In Use</option>
                                <option value="Under Maintenance">Under Maintenance</option>
                                <option value="Retired">Retired</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_assigned" class="form-label">Assigned To</label>
                            <select class="form-select" id="edit_assigned" name="assigned_to">
                                <option value="">Unassigned</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_last_maintenance" class="form-label">Last Maintenance</label>
                            <input type="date" class="form-control" id="edit_last_maintenance"
                                name="last_maintenance_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_next_maintenance" class="form-label">Next Maintenance</label>
                            <input type="date" class="form-control" id="edit_next_maintenance"
                                name="next_maintenance_date">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_asset" class="btn btn-primary"><i class="fas fa-save"></i> Update
                        Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrModalContent">
                    <img id="qrModalImage" src="" alt="QR Code" class="img-fluid mb-2" style="max-height:300px;">
                    <p><strong id="qrModalName"></strong></p>
                    <p><span class="badge bg-secondary" id="qrModalTag"></span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printQR()"><i class="fas fa-print"></i>
                    Print</button>
            </div>
        </div>
    </div>
</div>
<!-- ====== SCRIPTS ====== -->
<script>
    // ====== CONFIRMATION ======
    let confirmData = { action: '', name: '', id: 0 };

    function showConfirm(action, name, id) {
        const overlay = document.getElementById('confirmOverlay');
        const title = document.getElementById('confirmTitle');
        const message = document.getElementById('confirmMessage');
        const icon = document.getElementById('confirmIcon');
        const btn = document.getElementById('confirmBtn');

        confirmData = { action, name, id };

        if (action === 'delete') {
            title.textContent = '🗑️ Delete Asset?';
            message.textContent = `Are you sure you want to delete "${name}"? This action cannot be undone.`;
            icon.className = 'confirm-icon danger';
            icon.innerHTML = '<i class="fas fa-trash-alt"></i>';
            btn.className = 'btn btn-danger';
            btn.textContent = 'Yes, Delete Asset';
        } else if (action === 'toggle_status') {
            title.textContent = '🔄 Change Status?';
            message.textContent = `Are you sure you want to change the status of "${name}"?`;
            icon.className = 'confirm-icon warning';
            icon.innerHTML = '<i class="fas fa-sync-alt"></i>';
            btn.className = 'btn btn-warning';
            btn.textContent = 'Yes, Change Status';
        } else if (action === 'generate_qr') {
            title.textContent = '📱 Generate QR Code?';
            message.textContent = `Generate QR code for "${name}"?`;
            icon.className = 'confirm-icon success';
            icon.innerHTML = '<i class="fas fa-qrcode"></i>';
            btn.className = 'btn btn-success';
            btn.textContent = 'Yes, Generate';
        }

        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeConfirm() {
        document.getElementById('confirmOverlay').classList.remove('show');
        document.body.style.overflow = '';
        confirmData = { action: '', name: '', id: 0 };
    }

    function executeConfirm() {
        if (confirmData.id > 0 && confirmData.action) {
            window.location.href = 'assets.php?action=' + confirmData.action + '&id=' + confirmData.id;
        }
        closeConfirm();
    }

    // ====== SEARCH ======
    let searchTimeout;

    function searchAssets(value) {
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
        ['added', 'updated', 'deleted', 'toggled', 'qr_generated', 'error', 'name', 'status'].forEach(function (p) {
            url.searchParams.delete(p);
        });
        window.history.replaceState({}, document.title, url.toString());
    }

    // ====== EDIT MODAL ======
    document.addEventListener('DOMContentLoaded', function () {
        const editModal = document.getElementById('editAssetModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (button) {
                    document.getElementById('edit_asset_id').value = button.dataset.assetId || '';
                    document.getElementById('edit_asset_tag').value = button.dataset.assetTag || '';
                    document.getElementById('edit_qr_code').value = button.dataset.qrCode || '';
                    document.getElementById('edit_name').value = button.dataset.name || '';
                    document.getElementById('edit_category').value = button.dataset.category || '';
                    document.getElementById('edit_model').value = button.dataset.model || '';
                    document.getElementById('edit_serial').value = button.dataset.serial || '';
                    document.getElementById('edit_location').value = button.dataset.location || '';
                    document.getElementById('edit_purchase').value = button.dataset.purchase || '';
                    document.getElementById('edit_warranty').value = button.dataset.warranty || '';
                    document.getElementById('edit_status').value = button.dataset.status || 'Available';
                    document.getElementById('edit_assigned').value = button.dataset.assigned || '';
                    document.getElementById('edit_last_maintenance').value = button.dataset.lastMaintenance || '';
                    document.getElementById('edit_next_maintenance').value = button.dataset.nextMaintenance || '';
                }
            });
        }

        // Auto close toasts
        const toasts = document.querySelectorAll('.success-toast, .error-toast');
        if (toasts.length > 0) {
            setTimeout(closeToast, 2500);
        }

        // Show clear button if search has value
        const searchInput = document.getElementById('searchInput');
        const clearBtn = document.querySelector('.clear-search');
        if (searchInput && clearBtn) {
            searchInput.addEventListener('input', function () {
                clearBtn.classList.toggle('show', this.value.length > 0);
            });
        }
    });

    // ====== KEYBOARD SHORTCUTS ======
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeConfirm(); closeToast(); }
        if (e.key === 'Enter' && document.getElementById('confirmOverlay').classList.contains('show')) {
            executeConfirm();
        }
    });

    console.log('✅ Asset Management Loaded!');
    console.log('📦 Total assets:', '<?php echo $total; ?>');

    // Populate QR modal
    document.addEventListener('DOMContentLoaded', function () {
        const qrModal = document.getElementById('qrModal');
        if (qrModal) {
            qrModal.addEventListener('show.bs.modal', function (event) {
                const img = event.relatedTarget;
                const qrImage = img.getAttribute('data-qr-image');
                const name = img.getAttribute('data-asset-name');
                const tag = img.getAttribute('data-asset-tag');

                document.getElementById('qrModalImage').src = qrImage;
                document.getElementById('qrModalName').textContent = name;
                document.getElementById('qrModalTag').textContent = tag;
            });
        }
    });

    function printQR() {
        const content = document.getElementById('qrModalContent').innerHTML;
        const win = window.open('', '_blank');
        win.document.write(`
        <html><head><title>Print QR</title>
        <style>body{text-align:center; padding:20px;} img{max-width:300px;}</style>
        </head><body>${content}</body></html>
    `);
        win.document.close();
        win.print();
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>