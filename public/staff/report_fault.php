<?php
// public/staff/report_fault.php - Report a fault with QR scan support

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/settings_helper.php';

requireRole('Staff');

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get staff department
$stmt = $db->prepare("SELECT department FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user || empty($user['department'])) {
    $_SESSION['error'] = 'Your account is not assigned to any department. Contact admin.';
    header('Location: index.php');
    exit();
}
$department = $user['department'];

// QR pre‑selection from URL parameter (optional)
$preselected_asset_id = null;
$qr_error = null;
$scanned_code = null;

if (isset($_GET['qr']) && !empty($_GET['qr'])) {
    $qr_code = trim($_GET['qr']);
    $scanned_code = $qr_code;
    $stmt = $db->prepare("SELECT asset_id, name, location, status FROM assets WHERE qr_code = ? OR asset_tag = ?");
    $stmt->execute([$qr_code, $qr_code]);
    $asset = $stmt->fetch();
    if ($asset) {
        if ($asset['location'] === $department && $asset['status'] !== 'Retired') {
            $preselected_asset_id = $asset['asset_id'];
        } else {
            $qr_error = 'This asset is not in your department or is retired.';
        }
    } else {
        $qr_error = 'Asset not found.';
    }
}

// Fetch assets for dropdown
$stmt = $db->prepare("SELECT asset_id, name, asset_tag, status 
                       FROM assets 
                       WHERE location = ? AND status != 'Retired'
                       ORDER BY name ASC");
$stmt->execute([$department]);
$assets = $stmt->fetchAll();
$no_assets = (count($assets) === 0);

// Handle form submission
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_fault'])) {
    $asset_id = intval($_POST['asset_id'] ?? 0);
    $issue_description = trim($_POST['issue_description'] ?? '');
    $priority = $_POST['priority'] ?? 'Medium';

    if ($asset_id <= 0) {
        $error = 'Please select an asset.';
    } elseif (empty($issue_description)) {
        $error = 'Please describe the issue.';
    } else {
        // Verify asset belongs to department and is not retired
        $stmt = $db->prepare("SELECT asset_id FROM assets WHERE asset_id = ? AND location = ? AND status != 'Retired'");
        $stmt->execute([$asset_id, $department]);
        if (!$stmt->fetch()) {
            $error = 'Invalid asset selected.';
        } else {
            // Check if QR was scanned for this asset (if preselected matches)
            $qr_scanned = ($preselected_asset_id == $asset_id && !empty($scanned_code)) ? 1 : 0;
            $stmt = $db->prepare("INSERT INTO maintenance_requests 
                (asset_id, reported_by, issue_description, priority, status, reported_at, qr_scanned)
                VALUES (?, ?, ?, ?, 'Pending', NOW(), ?)");
            if ($stmt->execute([$asset_id, $user_id, $issue_description, $priority, $qr_scanned])) {
                $_SESSION['toast'] = [
                    'type' => 'success',
                    'title' => '✅ Fault Reported!',
                    'message' => 'Your request has been submitted successfully.'
                ];
                header('Location: report_fault.php?success=1');
                exit();
            } else {
                $error = 'Failed to submit request. Please try again.';
            }
        }
    }
}

// Include header
include __DIR__ . '/../includes/header.php';

// Show toasts
if (isset($_GET['success']) && isset($_SESSION['toast'])) {
    $toast = $_SESSION['toast'];
    unset($_SESSION['toast']);
    echo '<div class="success-toast show" id="successToast">
            <div class="toast-icon"><i class="fas fa-check-circle"></i></div>
            <div class="toast-content">
                <div class="toast-title">' . htmlspecialchars($toast['title']) . '</div>
                <p class="toast-message">' . htmlspecialchars($toast['message']) . '</p>
            </div>
          </div>';
}
if ($error) {
    echo '<div class="error-toast show" id="errorToast">
            <div class="toast-icon-error"><i class="fas fa-exclamation-circle"></i></div>
            <div class="toast-content">
                <div class="toast-title-error">❌ Error!</div>
                <p class="toast-message">' . htmlspecialchars($error) . '</p>
            </div>
          </div>';
}
if ($qr_error) {
    echo '<div class="error-toast show" id="errorToast">
            <div class="toast-icon-error"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="toast-content">
                <div class="toast-title-error">⚠️ QR Scan Issue</div>
                <p class="toast-message">' . htmlspecialchars($qr_error) . '</p>
            </div>
          </div>';
}
?>

<style>
    /* Reuse toast styles */
    .success-toast, .error-toast {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 9999;
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

    .report-card {
        max-width: 700px;
        margin: 2rem auto;
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    .report-card .card-header {
        background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
        color: white;
        border-radius: 16px 16px 0 0;
        padding: 15px 20px;
        font-weight: 600;
    }
    .report-card .card-body {
        padding: 25px;
    }
    .form-label {
        font-weight: 600;
        font-size: 0.9rem;
    }
    .required::after {
        content: " *";
        color: #dc3545;
    }
    .qr-scan-btn {
        background: #f1f3f5;
        border: 2px dashed #adb5bd;
        border-radius: 12px;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        margin-bottom: 15px;
    }
    .qr-scan-btn:hover {
        background: #e9ecef;
        border-color: #1a73e8;
    }
    .qr-scan-btn i {
        font-size: 2rem;
        color: #1a73e8;
    }
    .asset-info-box {
        background: #f8f9fa;
        border-left: 4px solid #1a73e8;
        padding: 10px 15px;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .no-assets {
        text-align: center;
        padding: 30px 20px;
        color: #6c757d;
    }
    .no-assets i {
        font-size: 3rem;
        color: #dee2e6;
        margin-bottom: 15px;
    }
    #qr-reader {
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
    }
    #qr-reader video {
        border-radius: 8px;
    }
    .scanner-status {
        padding: 10px;
        text-align: center;
        font-weight: 500;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="report-card card">
                <div class="card-header">
                    <i class="fas fa-exclamation-triangle"></i> Report a Fault
                    <small class="d-block text-white-50">Department: <?php echo htmlspecialchars($department); ?></small>
                </div>
                <div class="card-body">

                    <?php if ($no_assets): ?>
                        <div class="no-assets">
                            <i class="fas fa-box-open"></i>
                            <h5>No assets available</h5>
                            <p class="text-muted">There are no assets assigned to your department that can be reported.</p>
                            <a href="index.php" class="btn btn-secondary btn-sm mt-2">Go to Dashboard</a>
                        </div>
                    <?php else: ?>

                        <!-- QR Scan Button -->
                        <div class="qr-scan-btn" data-bs-toggle="modal" data-bs-target="#qrScannerModal">
                            <i class="fas fa-qrcode"></i>
                            <div class="mt-1">Tap to scan QR code</div>
                            <small class="text-muted">(Auto‑select the asset)</small>
                        </div>

                        <form method="POST" id="reportForm">
                            <!-- Asset Dropdown -->
                            <div class="mb-3">
                                <label for="asset_id" class="form-label required">Asset</label>
                                <select class="form-select" id="asset_id" name="asset_id" required>
                                    <option value="">-- Select an asset --</option>
                                    <?php foreach ($assets as $asset): ?>
                                        <option value="<?php echo $asset['asset_id']; ?>" 
                                            <?php echo ($preselected_asset_id == $asset['asset_id']) ? 'selected' : ''; ?>
                                            data-asset-tag="<?php echo htmlspecialchars($asset['asset_tag']); ?>"
                                            data-asset-name="<?php echo htmlspecialchars($asset['name']); ?>">
                                            <?php echo htmlspecialchars($asset['name'] . ' (' . $asset['asset_tag'] . ') - ' . $asset['status']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Only assets in your department are shown.</small>
                            </div>

                            <!-- Issue Description -->
                            <div class="mb-3">
                                <label for="issue_description" class="form-label required">Issue Description</label>
                                <textarea class="form-control" id="issue_description" name="issue_description" 
                                    rows="4" placeholder="Describe the fault in detail..." required><?php echo htmlspecialchars($_POST['issue_description'] ?? ''); ?></textarea>
                            </div>

                            <!-- Priority -->
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                    <option value="Critical">Critical</option>
                                </select>
                                <small class="text-muted">Choose the urgency of this fault.</small>
                            </div>

                            <?php if ($preselected_asset_id): ?>
                                <?php 
                                    $stmt = $db->prepare("SELECT name, asset_tag, location, status FROM assets WHERE asset_id = ?");
                                    $stmt->execute([$preselected_asset_id]);
                                    $pre_asset = $stmt->fetch();
                                ?>
                                <div class="asset-info-box" id="assetInfoBox">
                                    <i class="fas fa-info-circle text-primary"></i> 
                                    <strong>QR Asset:</strong> <?php echo htmlspecialchars($pre_asset['name'] . ' (' . $pre_asset['asset_tag'] . ')'); ?>
                                    <span class="badge bg-secondary ms-2"><?php echo $pre_asset['status']; ?></span>
                                </div>
                            <?php else: ?>
                                <div class="asset-info-box" id="assetInfoBox" style="display:none;"></div>
                            <?php endif; ?>

                            <button type="submit" name="report_fault" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane"></i> Submit Fault Report
                            </button>
                        </form>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Scanner Modal -->
<div class="modal fade" id="qrScannerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-qrcode"></i> Scan QR Code</h5>
                <button type="button" class="btn-close" id="closeScannerBtn" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="qr-reader"></div>
                <div id="qr-reader-status" class="scanner-status text-muted">Waiting for camera...</div>
                <div id="qr-reader-results" class="scanner-status" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="stopScannerBtn">Stop Scanner</button>
            </div>
        </div>
    </div>
</div>

<!-- Include html5-qrcode library from CDN -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
    // ====== GLOBALS ======
    let html5QrCode = null;
    let isScanning = false;
    const qrRegionId = "qr-reader";
    const statusElement = document.getElementById('qr-reader-status');
    const resultsElement = document.getElementById('qr-reader-results');

    // ====== API URL – use relative path from current page ======
    // This file is in public/staff/, so go up two levels to reach api/
    const API_URL = '../../api/get_asset_by_qr.php';
    console.log('API URL (relative):', API_URL);

    // ====== START SCANNER ======
    function startScanner() {
        if (html5QrCode) {
            html5QrCode.resume().catch(() => {
                html5QrCode = null;
                startScanner();
            });
            return;
        }

        html5QrCode = new Html5Qrcode(qrRegionId);

        const config = {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        };

        html5QrCode.start(
            { facingMode: "environment" },
            config,
            onScanSuccess,
            onScanError
        ).then(() => {
            isScanning = true;
            statusElement.textContent = "✅ Scanner active. Point camera at QR code.";
            statusElement.className = "scanner-status text-success";
            resultsElement.style.display = 'none';
        }).catch(err => {
            statusElement.textContent = "❌ Could not start camera: " + err;
            statusElement.className = "scanner-status text-danger";
            console.error(err);
        });
    }

    // ====== SCAN SUCCESS ======
    function onScanSuccess(decodedText, decodedResult) {
        console.log("QR scanned:", decodedText);
        if (html5QrCode && isScanning) {
            html5QrCode.stop().then(() => {
                isScanning = false;
                statusElement.textContent = "⏳ Verifying asset...";
                statusElement.className = "scanner-status text-info";
                resultsElement.style.display = 'block';
                resultsElement.textContent = "⏳ Verifying with server...";
                resultsElement.className = "scanner-status text-info";
                verifyQRCode(decodedText);
            }).catch(err => {
                console.warn("Stop error:", err);
                verifyQRCode(decodedText);
            });
        } else {
            verifyQRCode(decodedText);
        }
    }

    function onScanError(errorMessage) {
        // Ignore
    }

    // ====== VERIFY QR WITH SERVER – using relative URL ======
    function verifyQRCode(qrCode) {
        const url = API_URL + '?qr=' + encodeURIComponent(qrCode);
        console.log('Fetching:', url);

        fetch(url)
            .then(response => {
                console.log('Response status:', response.status);
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        const data = JSON.parse(text);
                        return { ok: response.ok, data };
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error('Invalid JSON response. Check server logs.');
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
                    const select = document.getElementById('asset_id');
                    const options = select.options;
                    let found = false;
                    for (let i = 0; i < options.length; i++) {
                        if (options[i].value == asset.asset_id) {
                            select.value = asset.asset_id;
                            found = true;
                            break;
                        }
                    }
                    if (found) {
                        updateAssetInfo(asset.name, asset.asset_tag, asset.status);
                        showNotification("✅ Asset auto-selected: " + asset.name, "success");
                        const modal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
                        if (modal) modal.hide();
                    } else {
                        showNotification("⚠️ Asset not in your department. Please select manually.", "error");
                        resetScanner();
                    }
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

    // ====== RESET SCANNER (adds retry button) ======
    function resetScanner() {
        statusElement.textContent = "⚠️ Scan failed. Tap 'Start Scanner' to retry.";
        statusElement.className = "scanner-status text-warning";
        resultsElement.textContent = "Click 'Start Scanner' to try again.";
        resultsElement.className = "scanner-status text-warning";
        resultsElement.style.display = 'block';
        isScanning = false;
        if (html5QrCode) {
            html5QrCode = null;
        }
        let retryBtn = document.getElementById('retryScannerBtn');
        if (!retryBtn) {
            retryBtn = document.createElement('button');
            retryBtn.id = 'retryScannerBtn';
            retryBtn.className = 'btn btn-primary mt-2';
            retryBtn.textContent = '🔄 Start Scanner';
            retryBtn.addEventListener('click', function() {
                statusElement.textContent = "Waiting for camera...";
                statusElement.className = "scanner-status text-muted";
                resultsElement.style.display = 'none';
                startScanner();
                this.remove();
            });
            statusElement.parentNode.insertBefore(retryBtn, statusElement.nextSibling);
        }
    }

    // ====== UPDATE ASSET INFO BOX ======
    function updateAssetInfo(name, tag, status) {
        const box = document.getElementById('assetInfoBox');
        if (box) {
            box.style.display = 'block';
            box.innerHTML = `
                <i class="fas fa-info-circle text-primary"></i> 
                <strong>QR Asset:</strong> ${name} (${tag})
                <span class="badge bg-secondary ms-2">${status}</span>
            `;
        }
    }

    // ====== SHOW TOAST ======
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
        scannerModal.addEventListener('shown.bs.modal', function () {
            const oldBtn = document.getElementById('retryScannerBtn');
            if (oldBtn) oldBtn.remove();
            startScanner();
        });
        scannerModal.addEventListener('hidden.bs.modal', function () {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    isScanning = false;
                }).catch(err => console.warn("Stop error:", err));
            }
            statusElement.textContent = "Waiting for camera...";
            statusElement.className = "scanner-status text-muted";
            resultsElement.style.display = 'none';
            const oldBtn = document.getElementById('retryScannerBtn');
            if (oldBtn) oldBtn.remove();
        });
    }

    document.getElementById('stopScannerBtn')?.addEventListener('click', function() {
        if (html5QrCode && isScanning) {
            html5QrCode.stop().then(() => {
                isScanning = false;
                statusElement.textContent = "⏸️ Scanner stopped by user.";
                statusElement.className = "scanner-status text-warning";
            }).catch(err => console.warn("Stop error:", err));
        }
    });

    // ====== TOAST AUTO-CLOSE ======
    document.addEventListener('DOMContentLoaded', function() {
        const toasts = document.querySelectorAll('.success-toast, .error-toast');
        toasts.forEach(function(toast) {
            setTimeout(function() {
                toast.style.animation = 'slideOutRight 0.3s ease forwards';
                setTimeout(function() { toast.style.display = 'none'; }, 300);
            }, 4000);
        });
    });

    console.log('✅ Staff Fault Reporting with QR Scan Loaded.');
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>