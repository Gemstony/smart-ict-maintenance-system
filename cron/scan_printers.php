<?php
// cron/scan_printers.php - Run every 5 minutes to scan printers

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../functions/device_detection.php';

$db = getDB();
$detector = new DeviceDetector();

// Get all printers
$stmt = $db->query("SELECT asset_id, name, ip_address, snmp_community FROM assets WHERE category = 'Printer'");
$printers = $stmt->fetchAll();

foreach ($printers as $printer) {
    $ip = $printer['ip_address'];
    $assetId = $printer['asset_id'];
    $community = $printer['snmp_community'] ?? 'public';
    
    // Check if printer is online (ping)
    $isOnline = $detector->pingDevice($ip);
    
    if ($isOnline) {
        // Get printer status via SNMP
        $status = $detector->getPrinterStatus($ip, $community);
        $inkLevels = $detector->getInkLevels($ip, $community);
        $paperLevel = $detector->getPaperLevel($ip, $community);
        $totalPages = $detector->getTotalPages($ip, $community);
        
        // Update database
        $stmt = $db->prepare("
            UPDATE assets 
            SET 
                is_online = 1,
                printer_status = ?,
                ink_levels = ?,
                paper_level = ?,
                total_pages = ?,
                status = ?,
                last_scanned = NOW()
            WHERE asset_id = ?
        ");
        
        $displayStatus = $status;
        $statusColor = 'Online';
        
        if ($status === 'Online') {
            $statusColor = 'Available';
        } else {
            $statusColor = $status; // Paper Jam, Out of Paper, etc.
        }
        
        $stmt->execute([
            $status,
            json_encode($inkLevels),
            $paperLevel,
            $totalPages,
            $statusColor,
            $assetId
        ]);
        
        // Check for issues and create notifications
        if ($status === 'Low Toner') {
            createTonerAlert($assetId, $inkLevels);
        } elseif ($status === 'Paper Jam') {
            createPaperJamAlert($assetId);
        } elseif ($status === 'Out of Paper') {
            createOutOfPaperAlert($assetId);
        } elseif ($status === 'Error') {
            createErrorAlert($assetId);
        }
        
    } else {
        // Printer is offline
        $stmt = $db->prepare("
            UPDATE assets 
            SET 
                is_online = 0,
                printer_status = 'Offline',
                status = 'Offline',
                last_scanned = NOW()
            WHERE asset_id = ?
        ");
        $stmt->execute([$assetId]);
        
        // Create offline alert if it was online before
        $stmt = $db->prepare("SELECT is_online FROM assets WHERE asset_id = ?");
        $stmt->execute([$assetId]);
        $prev = $stmt->fetch();
        if ($prev && $prev['is_online'] == 1) {
            createOfflineAlert($assetId);
        }
    }
}

// ====== ALERT FUNCTIONS ======

function createTonerAlert($assetId, $inkLevels) {
    $db = getDB();
    $stmt = $db->prepare("SELECT name FROM assets WHERE asset_id = ?");
    $stmt->execute([$assetId]);
    $asset = $stmt->fetch();
    
    $lowColors = [];
    foreach ($inkLevels as $color => $level) {
        if ($level < 25) {
            $lowColors[] = ucfirst($color) . " ($level%)";
        }
    }
    
    if (empty($lowColors)) return;
    
    $message = "⚠️ Low Toner Alert\n";
    $message .= "Printer: {$asset['name']}\n";
    $message .= "Low Colors: " . implode(', ', $lowColors) . "\n";
    $message .= "Please order replacement toner.";
    
    // Send to ICT Technicians
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        SELECT user_id, '⚠️ Low Toner Alert', ?, 'warning'
        FROM users WHERE role = 'ICT Technician' AND status = 'active'
    ");
    $stmt->execute([$message]);
}

function createPaperJamAlert($assetId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT name FROM assets WHERE asset_id = ?");
    $stmt->execute([$assetId]);
    $asset = $stmt->fetch();
    
    $message = "❗ Paper Jam Detected\n";
    $message .= "Printer: {$asset['name']}\n";
    $message .= "Please clear the paper jam.";
    
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        SELECT user_id, '❗ Paper Jam', ?, 'danger'
        FROM users WHERE role = 'ICT Technician' AND status = 'active'
    ");
    $stmt->execute([$message]);
}

function createOutOfPaperAlert($assetId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT name FROM assets WHERE asset_id = ?");
    $stmt->execute([$assetId]);
    $asset = $stmt->fetch();
    
    $message = "📄 Out of Paper\n";
    $message .= "Printer: {$asset['name']}\n";
    $message .= "Please add paper.";
    
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        SELECT user_id, '📄 Out of Paper', ?, 'warning'
        FROM users WHERE role = 'ICT Technician' AND status = 'active'
    ");
    $stmt->execute([$message]);
}

function createOfflineAlert($assetId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT name FROM assets WHERE asset_id = ?");
    $stmt->execute([$assetId]);
    $asset = $stmt->fetch();
    
    $message = "🔴 Printer Offline\n";
    $message .= "Printer: {$asset['name']}\n";
    $message .= "Please check the network connection.";
    
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        SELECT user_id, '🔴 Printer Offline', ?, 'danger'
        FROM users WHERE role = 'ICT Technician' AND status = 'active'
    ");
    $stmt->execute([$message]);
}