<?php
// public/admin/print_labels.php
// Print QR labels for filtered assets

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/models/Asset.php';

requireRole('ICT Technician');

$assetModel = new Asset();

$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_category = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$filters = [
    'status' => $filter_status,
    'category' => $filter_category,
    'search' => $search
];
$assets = $assetModel->getAll($filters);

// If no assets, show message
if (empty($assets)) {
    die('No assets found to print.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Labels</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #fff;
        }
        .label-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        .label-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            background: #fafafa;
            page-break-inside: avoid;
        }
        .label-item img {
            max-width: 120px;
            height: auto;
            display: block;
            margin: 0 auto 10px;
        }
        .label-item .asset-name {
            font-weight: bold;
            font-size: 14px;
        }
        .label-item .asset-tag {
            font-size: 12px;
            color: #555;
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .label-grid { gap: 15px; }
            .label-item { border: 1px solid #aaa; background: white; }
        }
        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }
        .no-print button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border: none;
            background: #0d6efd;
            color: white;
            border-radius: 5px;
        }
        .no-print button:hover {
            background: #0b5ed7;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <h2>QR Labels Preview</h2>
        <p><?php echo count($assets); ?> asset(s) found.</p>
        <button onclick="window.print()"><i class="fas fa-print"></i> Print Labels</button>
        <button onclick="window.close()">Close</button>
        <hr>
    </div>

    <div class="label-grid">
        <?php foreach ($assets as $asset): ?>
            <?php if (!empty($asset['qr_image'])): ?>
                <div class="label-item">
                    <img src="<?php echo BASE_URL . htmlspecialchars($asset['qr_image']); ?>" alt="QR Code">
                    <div class="asset-name"><?php echo htmlspecialchars($asset['name']); ?></div>
                    <span class="asset-tag"><?php echo htmlspecialchars($asset['asset_tag']); ?></span>
                </div>
            <?php else: ?>
                <div class="label-item" style="background:#f8d7da; color:#721c24;">
                    <p>No QR Code for<br><strong><?php echo htmlspecialchars($asset['name']); ?></strong></p>
                    <span class="asset-tag"><?php echo htmlspecialchars($asset['asset_tag']); ?></span>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <script>
        // Auto-print if URL contains ?print=1
        if (window.location.search.includes('print=1')) {
            window.onload = function() {
                window.print();
            };
        }
    </script>
</body>
</html>