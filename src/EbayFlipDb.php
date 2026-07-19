<?php
/**
 * eBay Flip Database Reader
 *
 * Reads items from the geek_ebayflip database (the existing eBay tracking database).
 */
class EbayFlipDb
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Get all active eBay items that should be synced to OpenCart.
     *
     * @return array Array of item data arrays
     */
    public function getItemsToSync(): array
    {
        try {
            // Try ebay_items table first
            $stmt = $this->db->prepare(
                "SELECT ei.*, inv.quantity as inv_quantity
                 FROM ebay_items ei
                 LEFT JOIN inventory inv ON ei.item_id = inv.item_id
                 WHERE ei.listing_status = 'active'
                    OR ei.listing_status IS NULL
                 ORDER BY ei.date_added DESC"
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                // Fallback: try the sales table or just list all ebay_items
                $stmt = $this->db->prepare(
                    "SELECT * FROM ebay_items ORDER BY date_added DESC LIMIT 500"
                );
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $rows;
        } catch (PDOException $e) {
            error_log("EbayFlipDb::getItemsToSync error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a specific item by its eBay item ID.
     */
    public function getItem(string $itemId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT ei.*, inv.quantity as inv_quantity
                 FROM ebay_items ei
                 LEFT JOIN inventory inv ON ei.item_id = inv.item_id
                 WHERE ei.item_id = ?
                 LIMIT 1"
            );
            $stmt->execute([$itemId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log("EbayFlipDb::getItem error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the database table structure for display.
     */
    public function getTableInfo(): array
    {
        try {
            $stmt = $this->db->prepare("SHOW TABLES");
            $stmt->execute();
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $info = [];
            foreach ($tables as $table) {
                $countStmt = $this->db->prepare("SELECT COUNT(*) FROM `$table`");
                $countStmt->execute();
                $info[$table] = (int)$countStmt->fetchColumn();
            }
            return $info;
        } catch (PDOException $e) {
            error_log("EbayFlipDb::getTableInfo error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a sample row from a table to understand its structure.
     */
    public function getSample(string $table): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM `$table` LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: [];
        } catch (PDOException $e) {
            error_log("EbayFlipDb::getSample error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark an item as synced (update the listing_status or add a sync flag).
     */
    public function markSynced(string $itemId, int $productId, string $status = 'synced'): bool
    {
        try {
            // Check if a sync_log table exists
            $stmt = $this->db->prepare("SHOW TABLES LIKE 'sync_log'");
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $stmt = $this->db->prepare(
                    "INSERT INTO sync_log (item_id, product_id, status, synced_at)
                     VALUES (?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE status = ?, synced_at = NOW()"
                );
                $stmt->execute([$itemId, $productId, $status, $status]);
                return true;
            }

            // Otherwise update the ebay_items listing_status
            $stmt = $this->db->prepare(
                "UPDATE ebay_items SET listing_status = ? WHERE item_id = ?"
            );
            $stmt->execute([$status, $itemId]);
            return true;
        } catch (PDOException $e) {
            error_log("EbayFlipDb::markSynced error: " . $e->getMessage());
            return false;
        }
    }
}
