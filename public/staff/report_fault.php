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
            $qr_scanned = ($preselected_asset_id == $asset_id) ? 1 : 0;
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

    /* ====== REPORT CARD ====== */
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

    /* ====== QR SCAN BUTTON ====== */
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

    /* ====== ASSET INFO BOX ====== */
    .asset-info-box {
        background: #f8f9fa;
        border-left: 4px solid #1a73e8;
        padding: 10px 15px;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    /* ====== NO ASSETS ====== */
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

    /* ====== QR SCANNER ====== */
    #qr-reader {
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        background: #000;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        min-height: 300px;
    }
    #qr-reader video {
        width: 100% !important;
        height: auto !important;
        display: block;
    }
    #qr-reader img {
        display: none !important;
    }
    
    /* QR Scanner Overlay */
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
        width: 200px;
        height: 200px;
        border: 3px solid #00ff00;
        border-radius: 12px;
        position: relative;
        box-shadow: 0 0 30px rgba(0, 255, 0, 0.2);
        animation: pulse-border 2s ease-in-out infinite;
    }
    .scanner-overlay .scan-frame::before {
        content: '';
        position: absolute;
        top: -2px;
        left: -2px;
        right: -2px;
        bottom: -2px;
        border: 3px solid transparent;
        border-top-color: #00ff00;
        border-left-color: #00ff00;
        border-radius: 12px;
        animation: scan-corner 2s ease-in-out infinite;
    }
    .scanner-overlay .scan-line {
        position: absolute;
        top: 0;
        left: 10%;
        right: 10%;
        height: 2px;
        background: linear-gradient(90deg, transparent, #00ff00, transparent);
        animation: scan-line 2s ease-in-out infinite;
        box-shadow: 0 0 10px rgba(0, 255, 0, 0.5);
    }
    
    @keyframes pulse-border {
        0%, 100% { box-shadow: 0 0 30px rgba(0, 255, 0, 0.2); }
        50% { box-shadow: 0 0 60px rgba(0, 255, 0, 0.5); }
    }
    @keyframes scan-corner {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    @keyframes scan-line {
        0% { top: 0; opacity: 1; }
        50% { top: 100%; opacity: 1; }
        51% { opacity: 0; }
        100% { opacity: 0; }
    }

    /* ====== SCANNER CONTROLS ====== */
    .scanner-controls {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 15px;
        flex-wrap: wrap;
    }
    .scanner-controls .btn {
        padding: 8px 20px;
        border-radius: 50px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        transition: all 0.3s;
        cursor: pointer;
    }
    .scanner-controls .btn:hover {
        transform: scale(1.05);
    }
    .scanner-controls .btn-torch {
        background: #ffc107;
        color: #212529;
    }
    .scanner-controls .btn-torch.active {
        background: #ffca2c;
        box-shadow: 0 0 30px rgba(255, 193, 7, 0.4);
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
    .scanner-controls .btn-start:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .scanner-status {
        padding: 10px;
        text-align: center;
        font-weight: 500;
        margin-top: 10px;
        border-radius: 8px;
    }
    .scanner-status.text-success { background: #d4edda; color: #155724; }
    .scanner-status.text-danger { background: #f8d7da; color: #721c24; }
    .scanner-status.text-warning { background: #fff3cd; color: #856404; }
    .scanner-status.text-info { background: #d1ecf1; color: #0c5460; }
    .scanner-status.text-muted { background: #e9ecef; color: #6c757d; }

    /* ====== RESPONSIVE ====== */
    @media (max-width: 576px) {
        .success-toast, .error-toast {
            top: 70px;
            right: 10px;
            left: 10px;
            min-width: auto;
            padding: 15px 20px;
        }
        .report-card .card-body {
            padding: 15px;
        }
        .scanner-controls .btn {
            padding: 6px 14px;
            font-size: 0.8rem;
        }
        .scanner-overlay .scan-frame {
            width: 150px;
            height: 150px;
        }
        #qr-reader {
            min-height: 250px;
        }
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-clipboard-list text-primary"></i> Report Fault</h4>
            <small class="text-muted">Report a new maintenance fault</small>
        </div>
        <a href="my_requests.php" class="btn btn-primary btn-sm">
            <i class="fas fa-list"></i> My Requests
        </a>
    </div>
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

<!-- ====== QR SCANNER MODAL ====== -->
<div class="modal fade" id="qrScannerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-qrcode"></i> Scan QR Code</h5>
                <button type="button" class="btn-close" id="closeScannerBtn" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- QR Reader -->
                <div id="qr-reader">
                    <div class="scanner-overlay" id="scannerOverlay">
                        <div class="scan-frame">
                            <div class="scan-line"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Scanner Status -->
                <div id="qr-reader-status" class="scanner-status text-muted">📷 Waiting for camera...</div>
                <div id="qr-reader-results" class="scanner-status" style="display:none;"></div>

                <!-- Scanner Controls -->
                <div class="scanner-controls">
                    <button class="btn btn-torch" id="torchBtn" title="Toggle Torch/Flash">
                        <i class="fas fa-lightbulb"></i> Torch
                    </button>
                    <button class="btn btn-switch" id="switchCameraBtn" title="Switch Camera">
                        <i class="fas fa-sync-alt"></i> Switch
                    </button>
                    <button class="btn btn-start" id="startScannerBtn" disabled>
                        <i class="fas fa-play"></i> Start
                    </button>
                    <button class="btn btn-stop" id="stopScannerBtn">
                        <i class="fas fa-stop"></i> Stop
                    </button>
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

<!-- ====== INCLUDE HTML5-QRCODE LIBRARY ====== -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
// ============================================================
// SECTION I: GLOBALS
// ============================================================
let html5QrCode = null;
let isScanning = false;
let isTorchOn = false;
let currentCameraId = null;
let cameraList = [];
let cameraIndex = 0;
let isStarting = false;
let torchSupported = false;
let scanAttempts = 0;
const MAX_SCAN_ATTEMPTS = 3;

const qrRegionId = "qr-reader";
const statusElement = document.getElementById('qr-reader-status');
const resultsElement = document.getElementById('qr-reader-results');
const scannerOverlay = document.getElementById('scannerOverlay');
const torchBtn = document.getElementById('torchBtn');
const switchBtn = document.getElementById('switchCameraBtn');
const startBtn = document.getElementById('startScannerBtn');
const stopBtn = document.getElementById('stopScannerBtn');

// ============================================================
// SECTION II: API URL
// ============================================================
const API_URL = '../../api/get_asset_by_qr.php';

// ============================================================
// SECTION III: CAMERA HELPERS - IMPROVED
// ============================================================

/**
 * Check if browser supports getUserMedia
 */
function isMediaDevicesSupported() {
    return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
}

/**
 * Get available cameras with improved error handling
 */
async function getCameras() {
    try {
        // Check if mediaDevices is supported
        if (!isMediaDevicesSupported()) {
            console.error('❌ MediaDevices API not supported in this browser');
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
            // Stop the stream immediately after getting permission
            stream.getTracks().forEach(track => track.stop());
            console.log('✅ Camera permission granted');
        } catch (permErr) {
            console.warn('⚠️ Permission request error:', permErr);
            // Try without constraints
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                stream.getTracks().forEach(track => track.stop());
                console.log('✅ Camera permission granted (basic)');
            } catch (permErr2) {
                console.error('❌ Permission denied:', permErr2);
                return [];
            }
        }

        // Now enumerate devices
        const devices = await navigator.mediaDevices.enumerateDevices();
        const cameras = devices.filter(device => device.kind === 'videoinput');
        
        console.log('📷 Cameras found:', cameras.length);
        if (cameras.length === 0) {
            console.warn('⚠️ No video input devices found');
            // Try alternative method: check if getUserMedia works
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                const tracks = stream.getVideoTracks();
                if (tracks.length > 0) {
                    console.log('✅ Found camera via getUserMedia:', tracks[0].label);
                    // Create a virtual camera entry
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
            console.log(`  Camera ${i}: ${cam.label || 'Unnamed'} (${cam.deviceId})`);
        });
        
        return cameras;
    } catch (err) {
        console.error('❌ Error enumerating cameras:', err);
        // Try fallback
        return getCamerasFallback();
    }
}

/**
 * Fallback method to get cameras
 */
async function getCamerasFallback() {
    try {
        console.log('🔄 Trying fallback camera detection...');
        const stream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'environment' 
            } 
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

/**
 * Check if torch is supported on current camera
 */
async function checkTorchSupport() {
    try {
        if (!html5QrCode || !html5QrCode._videoElement) {
            return false;
        }
        
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

/**
 * Start scanner with specific camera - IMPROVED
 */
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
            } catch (e) {
                // Ignore
            }
            html5QrCode = null;
        }

        // Get cameras - with retry
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
            // Check if mediaDevices is supported
            if (!isMediaDevicesSupported()) {
                throw new Error('Browser does not support camera access. Please use a modern browser (Chrome, Firefox, Edge) with HTTPS.');
            }
            throw new Error('No camera found. Please:\n1. Allow camera permissions in browser\n2. Connect a camera\n3. Use HTTPS or localhost\n4. Close other apps using camera');
        }

        // Select camera
        let selectedCameraId = cameraId;
        if (!selectedCameraId) {
            // Try to find back/environment camera first
            const envCam = cameraList.find(c => 
                c.label && (
                    c.label.toLowerCase().includes('back') || 
                    c.label.toLowerCase().includes('environment') ||
                    c.label.toLowerCase().includes('rear')
                )
            );
            
            // If no back camera, use first available
            selectedCameraId = envCam ? envCam.deviceId : cameraList[0].deviceId;
            
            // If using default, use camera index 0
            if (!selectedCameraId || selectedCameraId === 'default') {
                selectedCameraId = cameraList[0].deviceId;
            }
        }

        currentCameraId = selectedCameraId;
        console.log('📷 Using camera:', selectedCameraId);
        console.log('📷 Camera label:', cameraList.find(c => c.deviceId === selectedCameraId)?.label || 'Unknown');

        // Create scanner with selected camera
        html5QrCode = new Html5Qrcode(qrRegionId);

        // Show overlay
        if (scannerOverlay) {
            scannerOverlay.style.display = 'flex';
        }

        // Use simpler configuration for better compatibility
        const config = {
            fps: 15,
            qrbox: { width: 200, height: 200 },
            aspectRatio: 1.0
        };

        // Try with deviceId first
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
            // Try with facingMode
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
                // Try with default
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
        
        // Check torch support after camera is started
        setTimeout(async () => {
            torchSupported = await checkTorchSupport();
            if (torchSupported) {
                torchBtn.style.display = 'inline-flex';
                torchBtn.disabled = false;
                torchBtn.style.opacity = '1';
                torchBtn.title = 'Toggle Torch';
                console.log('💡 Torch is supported on this camera');
            } else {
                torchBtn.style.display = 'inline-flex';
                torchBtn.disabled = true;
                torchBtn.style.opacity = '0.5';
                torchBtn.title = 'Torch not supported on this camera';
                console.log('💡 Torch is NOT supported on this camera');
            }
        }, 500);
        
        statusElement.textContent = "✅ Scanner active. Point camera at QR code.";
        statusElement.className = "scanner-status text-success";
        resultsElement.style.display = 'none';
        
        // Reset torch state
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
        
        // Custom error messages
        if (errorMsg.includes('Permission denied') || errorMsg.includes('NotAllowedError')) {
            errorMsg = '⚠️ Camera permission denied.\n\nPlease:\n1. Click the camera icon in the address bar\n2. Select "Allow" for camera access\n3. Refresh the page';
        } else if (errorMsg.includes('NotFoundError')) {
            errorMsg = '❌ No camera found.\n\nPlease:\n1. Connect a camera\n2. Allow camera permissions\n3. Use HTTPS or localhost';
        } else if (errorMsg.includes('NotReadableError')) {
            errorMsg = '⚠️ Camera is in use by another application.\n\nPlease close other apps using the camera (Zoom, Teams, WhatsApp, etc.)';
        } else if (errorMsg.includes('OverconstrainedError')) {
            errorMsg = '⚠️ Camera settings not supported.\n\nTrying with default settings...';
            // Retry with default
            try {
                await startScanner(null);
                return;
            } catch (retryErr) {
                errorMsg = '❌ Failed to start camera. Please try again.';
            }
        } else if (errorMsg.includes('Browser does not support')) {
            errorMsg = '❌ Browser does not support camera.\n\nPlease use:\n- Google Chrome (latest)\n- Mozilla Firefox (latest)\n- Microsoft Edge (latest)';
        } else if (errorMsg.includes('localhost')) {
            errorMsg = '⚠️ Camera requires HTTPS or localhost.\n\nPlease use:\n- Localhost (http://localhost)\n- HTTPS connection\n- Or ngrok for external access';
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

/**
 * Stop scanner
 */
async function stopScanner() {
    if (html5QrCode && isScanning) {
        try {
            await html5QrCode.stop();
            isScanning = false;
            startBtn.disabled = false;
            stopBtn.disabled = true;
            
            // Turn off torch if on
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

/**
 * Toggle Torch/Flash - FIXED
 */
async function toggleTorch() {
    // Check if scanner is active
    if (!html5QrCode || !isScanning) {
        showNotification('⚠️ Please start the scanner first to use torch.', 'error');
        return;
    }

    // Check if torch is supported
    if (!torchSupported) {
        showNotification('⚠️ Torch is not supported on this camera.', 'error');
        return;
    }

    try {
        isTorchOn = !isTorchOn;
        
        // Try using the scanner's built-in torch method
        if (typeof html5QrCode.toggleTorch === 'function') {
            await html5QrCode.toggleTorch(isTorchOn);
        } else if (html5QrCode._videoElement) {
            // Manual torch using MediaStreamTrack
            const video = html5QrCode._videoElement;
            if (video.srcObject) {
                const tracks = video.srcObject.getVideoTracks();
                if (tracks.length > 0) {
                    const track = tracks[0];
                    // Check if torch is supported
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
        
        // Update button state
        torchBtn.classList.toggle('active', isTorchOn);
        torchBtn.innerHTML = isTorchOn ? 
            '<i class="fas fa-lightbulb"></i> Torch ON' : 
            '<i class="fas fa-lightbulb"></i> Torch';
            
        console.log(`💡 Torch ${isTorchOn ? 'ON' : 'OFF'}`);
        
    } catch (err) {
        console.warn('Torch error:', err);
        // Revert torch state on error
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

/**
 * Switch camera
 */
async function switchCamera() {
    if (cameraList.length < 2) {
        showNotification('⚠️ Only one camera available.', 'error');
        return;
    }

    try {
        await stopScanner();
        
        // Cycle to next camera
        cameraIndex = (cameraIndex + 1) % cameraList.length;
        const camera = cameraList[cameraIndex];
        
        statusElement.textContent = `🔄 Switching to: ${camera.label || 'Camera ' + (cameraIndex + 1)}...`;
        statusElement.className = "scanner-status text-info";
        resultsElement.style.display = 'none';
        
        setTimeout(() => {
            startScanner(camera.deviceId);
        }, 500);
        
        console.log(`🔄 Switching to camera ${cameraIndex + 1}: ${camera.label || 'Unnamed'}`);
        
    } catch (err) {
        console.error('Switch camera error:', err);
        showNotification('❌ Failed to switch camera.', 'error');
        startScanner(currentCameraId);
    }
}

// ============================================================
// SECTION IV: QR SCAN HANDLERS
// ============================================================

function onScanSuccess(decodedText, decodedResult) {
    console.log("📱 QR scanned:", decodedText);
    
    if (html5QrCode && isScanning) {
        // Pause scanner while processing
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

// ============================================================
// SECTION V: VERIFY QR WITH SERVER
// ============================================================

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
                    // Close modal after brief delay
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
                        if (modal) modal.hide();
                    }, 1500);
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

// ============================================================
// SECTION VI: SCANNER HELPERS
// ============================================================

function resetScanner() {
    statusElement.textContent = "⚠️ Scan failed. Use controls below to try again.";
    statusElement.className = "scanner-status text-warning";
    resultsElement.textContent = "Click 'Start' to try again or 'Switch' to change camera.";
    resultsElement.className = "scanner-status text-warning";
    resultsElement.style.display = 'block';
    resultsElement.style.whiteSpace = 'normal';
    isScanning = false;
    startBtn.disabled = false;
    stopBtn.disabled = true;
    torchBtn.disabled = true;
    torchBtn.style.opacity = '0.5';
    
    if (scannerOverlay) {
        scannerOverlay.style.display = 'none';
    }
}

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

// ============================================================
// SECTION VII: MODAL EVENTS
// ============================================================

const scannerModal = document.getElementById('qrScannerModal');

if (scannerModal) {
    scannerModal.addEventListener('shown.bs.modal', async function () {
        // Reset state
        isStarting = false;
        startBtn.disabled = false;
        stopBtn.disabled = true;
        torchBtn.disabled = true;
        torchBtn.style.opacity = '0.5';
        torchBtn.title = 'Start scanner first to use torch';
        
        statusElement.textContent = "📷 Detecting cameras...";
        statusElement.className = "scanner-status text-info";
        resultsElement.style.display = 'none';
        
        // Get cameras with retry
        cameraList = await getCameras();
        
        if (cameraList.length === 0) {
            // Try one more time after delay
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
            
            // Auto-start if cameras available
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
        torchBtn.title = 'Start scanner first to use torch';
        statusElement.textContent = "📷 Ready";
        statusElement.className = "scanner-status text-muted";
        statusElement.style.whiteSpace = 'normal';
        resultsElement.style.display = 'none';
        if (scannerOverlay) {
            scannerOverlay.style.display = 'none';
        }
    });
}

// ============================================================
// SECTION VIII: BUTTON EVENT LISTENERS
// ============================================================

// Start button
startBtn.addEventListener('click', function() {
    startScanner();
});

// Stop button
stopBtn.addEventListener('click', async function() {
    await stopScanner();
});

// Torch button
torchBtn.addEventListener('click', toggleTorch);

// Switch camera button
switchBtn.addEventListener('click', switchCamera);

// Manual select button
document.getElementById('manualSelectBtn').addEventListener('click', function() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
    if (modal) modal.hide();
});

// Close button
document.getElementById('closeScannerBtn').addEventListener('click', function() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
    if (modal) modal.hide();
});

// ============================================================
// SECTION IX: KEYBOARD SHORTCUTS
// ============================================================

document.addEventListener('keydown', function(e) {
    if (e.key === 't' || e.key === 'T') {
        if (document.getElementById('qrScannerModal').classList.contains('show')) {
            toggleTorch();
        }
    }
    if (e.key === 's' || e.key === 'S') {
        if (document.getElementById('qrScannerModal').classList.contains('show')) {
            switchCamera();
        }
    }
    if (e.key === 'Escape') {
        const modal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
        if (modal) modal.hide();
    }
});

// ============================================================
// SECTION X: TOAST AUTO-CLOSE
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    const toasts = document.querySelectorAll('.success-toast, .error-toast');
    toasts.forEach(function(toast) {
        setTimeout(function() {
            toast.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(function() { toast.style.display = 'none'; }, 300);
        }, 4000);
    });
    
    console.log('✅ Staff Fault Reporting with QR Scan Loaded.');
    console.log('💡 Keyboard shortcuts: [T] Torch | [S] Switch Camera | [ESC] Close');
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>