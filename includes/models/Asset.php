<?php
// includes/models/Asset.php - Asset data model

class Asset
{
    private $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * Get all assets with optional filters
     * @param array $filters ['status' => ..., 'category' => ..., 'search' => ...]
     * @return array
     */
    public function getAll($filters = [])
    {
        $sql = "SELECT a.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name
                FROM assets a
                LEFT JOIN users u ON a.assigned_to = u.user_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $sql .= " AND a.category = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $sql .= " AND (a.name LIKE ? OR a.asset_tag LIKE ? OR a.model LIKE ? OR a.serial_number LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY a.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get a single asset by ID
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM assets WHERE asset_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Create a new asset
     * @param array $data
     * @return int|false last insert ID or false on error
     */
    /**
     * Create a new asset and generate its QR code.
     */
    public function create($data)
    {
        if (empty($data['asset_tag'])) {
            $data['asset_tag'] = $this->generateAssetTag();
        }
        if (empty($data['qr_code'])) {
            $data['qr_code'] = 'QR-' . $data['asset_tag'];
        }

        $stmt = $this->db->prepare("INSERT INTO assets 
            (asset_tag, qr_code, name, category, model, serial_number, location, 
             purchase_date, warranty_expiry, status, assigned_to, last_maintenance_date, next_maintenance_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            $data['asset_tag'],
            $data['qr_code'],
            $data['name'],
            $data['category'],
            $data['model'] ?? null,
            $data['serial_number'] ?? null,
            $data['location'] ?? null,
            $data['purchase_date'] ?? null,
            $data['warranty_expiry'] ?? null,
            $data['status'] ?? 'Available',
            !empty($data['assigned_to']) ? $data['assigned_to'] : null,
            $data['last_maintenance_date'] ?? null,
            $data['next_maintenance_date'] ?? null
        ]);

        if ($result) {
            $newId = $this->db->lastInsertId();
            // Generate QR code image
            $this->generateQRImage($newId, $data['asset_tag'], $data['qr_code']);
            return $newId;
        }
        return false;
    }

    /**
     * Generate QR code image for an asset.
     * @param int $asset_id
     * @param string $asset_tag
     * @param string $qr_code
     * @return string|false Path to image or false on failure
     */
    public function generateQRImage($asset_id, $asset_tag, $qr_code)
    {
        // Include QR library
        require_once __DIR__ . '/../../vendor/phpqrcode/qrlib.php';

        // Ensure directory exists
        $qr_dir = __DIR__ . '/../../assets/qr_codes/';
        if (!is_dir($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }

        $filename = $asset_tag . '.png';
        $filepath = $qr_dir . $filename;

        // QR code content: we can use asset ID or asset_tag
        $content = $qr_code; // or use a URL like: BASE_URL . '/asset.php?qr=' . $qr_code

        // Generate QR code
        QRcode::png($content, $filepath, QR_ECLEVEL_L, 6);

        // Update database with the image path
        $relative_path = 'assets/qr_codes/' . $filename;
        $stmt = $this->db->prepare("UPDATE assets SET qr_image = ? WHERE asset_id = ?");
        $stmt->execute([$relative_path, $asset_id]);

        return $relative_path;
    }

    /**
     * Regenerate QR code for an asset (overwrites existing)
     * @param int $asset_id
     * @return string|false Path to new image or false on failure
     */
    public function regenerateQR($asset_id)
    {
        $asset = $this->getById($asset_id);
        if (!$asset) {
            return false;
        }

        // Delete old QR image file if exists
        if (!empty($asset['qr_image'])) {
            $old_file = __DIR__ . '/../../' . $asset['qr_image'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        // Generate new QR (this will create a new file with the same name)
        return $this->generateQRImage($asset_id, $asset['asset_tag'], $asset['qr_code']);
    }
    /**
     * Generate QR for existing asset (used by manual generate button).
     */
    public function generateQRForExisting($asset_id)
    {
        $asset = $this->getById($asset_id);
        if (!$asset) {
            return false;
        }
        return $this->generateQRImage($asset_id, $asset['asset_tag'], $asset['qr_code']);
    }

    /**
     * Update an existing asset
     */
    public function update($id, $data)
    {
        $stmt = $this->db->prepare("UPDATE assets SET 
            asset_tag = ?, qr_code = ?, name = ?, category = ?, model = ?, 
            serial_number = ?, location = ?, purchase_date = ?, warranty_expiry = ?, 
            status = ?, assigned_to = ?, last_maintenance_date = ?, next_maintenance_date = ?
            WHERE asset_id = ?");
        return $stmt->execute([
            $data['asset_tag'],
            $data['qr_code'],
            $data['name'],
            $data['category'],
            $data['model'] ?? null,
            $data['serial_number'] ?? null,
            $data['location'] ?? null,
            $data['purchase_date'] ?? null,
            $data['warranty_expiry'] ?? null,
            $data['status'] ?? 'Available',
            !empty($data['assigned_to']) ? $data['assigned_to'] : null,
            $data['last_maintenance_date'] ?? null,
            $data['next_maintenance_date'] ?? null,
            $id
        ]);
    }

    /**
     * Delete an asset
     */
    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM assets WHERE asset_id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Toggle asset status (available/in use/maintenance/retired)
     * We'll allow changing to any status via update
     */

    /**
     * Generate a unique asset tag (e.g., ICT-0007)
     */
    /**
     * Generate a unique asset tag.
     */
    private function generateAssetTag()
    {
        $stmt = $this->db->query("SELECT MAX(asset_id) as max_id FROM assets");
        $row = $stmt->fetch();
        $next = ($row && $row['max_id']) ? $row['max_id'] + 1 : 1;
        return 'ICT-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get all distinct categories (from assets table or from asset_categories)
     */
    public function getCategories()
    {
        $stmt = $this->db->query("SELECT category_name FROM asset_categories ORDER BY category_name");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get all users for assignment dropdown
     */
    public function getUsers()
    {
        $stmt = $this->db->query("SELECT user_id, CONCAT(first_name, ' ', last_name) as full_name 
                                  FROM users WHERE status = 'active' ORDER BY full_name");
        return $stmt->fetchAll();
    }
}