<?php
// api/get_asset_by_qr.php
// Returns asset details for a given QR code (qr_code or asset_tag)

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Allow any logged-in user
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$db = getDB();

try {
    // Get user role and department
    $stmt = $db->prepare("SELECT role, department FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit();
    }

    $qr = isset($_GET['qr']) ? trim($_GET['qr']) : '';
    if (empty($qr)) {
        http_response_code(400);
        echo json_encode(['error' => 'QR code parameter missing']);
        exit();
    }

    // Search by qr_code OR asset_tag (fixed: both conditions)
    $stmt = $db->prepare("SELECT asset_id, name, asset_tag, category, location, status 
                           FROM assets 
                           WHERE qr_code = ? OR asset_tag = ?");
    $stmt->execute([$qr, $qr]);
    $asset = $stmt->fetch();

    if (!$asset) {
        http_response_code(404);
        echo json_encode(['error' => 'Asset not found']);
        exit();
    }

    // If user is staff, check department
    if ($user['role'] === 'Staff') {
        if ($asset['location'] !== $user['department']) {
            http_response_code(403);
            echo json_encode([
                'error' => 'This asset does not belong to your department',
                'asset' => $asset
            ]);
            exit();
        }
        if ($asset['status'] === 'Retired') {
            http_response_code(403);
            echo json_encode([
                'error' => 'This asset is retired and cannot be reported',
                'asset' => $asset
            ]);
            exit();
        }
    }

    // Success
    echo json_encode([
        'success' => true,
        'asset' => $asset
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}