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
    /* Reuse toast styles from assets.php */
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

    .status-Pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-Assigned {
        background: #cce5ff;
        color: #004085;
    }

    .status-In\ Progress {
        background: #d1ecf1;
        color: #0c5460;
    }

    .status-Resolved {
        background: #d4edda;
        color: #155724;
    }

    .status-Closed {
        background: #e2e3e5;
        color: #383d41;
    }

    .priority-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .priority-Low {
        background: #d4edda;
        color: #155724;
    }

    .priority-Medium {
        background: #fff3cd;
        color: #856404;
    }

    .priority-High {
        background: #f8d7da;
        color: #721c24;
    }

    .priority-Critical {
        background: #dc3545;
        color: white;
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
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-clipboard-list text-primary"></i> My Requests</h4>
            <small class="text-muted">Track all your maintenance requests</small>
        </div>
        <a href="report_fault.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus-circle"></i> Report New Fault
        </a>
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
                            <th>#</th>
                            <th>Asset</th>
                            <th>Issue</th>
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
                                    <td>
                                        <?php echo htmlspecialchars(substr($req['issue_description'], 0, 50)); ?>
                                        <?php if (strlen($req['issue_description']) > 50): ?>...<?php endif; ?>
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
                                        <!-- View Details (future) -->
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
                                        <a href="report_fault.php" class="btn btn-primary btn-sm mt-2">
                                            <i class="fas fa-plus-circle"></i> Report a Fault
                                        </a>
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

<!-- View Request Modal (placeholder) -->
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

<script>
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

        // Show clear button if search has value
        const searchInput = document.getElementById('searchInput');
        const clearBtn = document.querySelector('.clear-search');
        if (searchInput && clearBtn) {
            searchInput.addEventListener('input', function () {
                clearBtn.classList.toggle('show', this.value.length > 0);
            });
        }
    });

    // ====== VIEW REQUEST (AJAX) ======
    function viewRequest(requestId) {
        const modal = new bootstrap.Modal(document.getElementById('viewRequestModal'));
        const body = document.getElementById('requestDetailsBody');
        body.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading request details...</p></div>';
        modal.show();

        fetch('<?php echo BASE_URL; ?>api/get_request_details.php?id=' + requestId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
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

    console.log('✅ My Requests Loaded');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>