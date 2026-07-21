<?php
/**
 * eBay Sync Event Listener
 *
 * OpenCart 4.x event controller.
 * Hooks into order status changes and triggers eBay listing removal
 * when a product with ebay_item_id is sold.
 *
 * Place at: <oc_root>/catalog/controller/event/ebay_sync.php
 *
 * Register event:
 *   INSERT INTO oc_event (code, trigger, action, status, sort_order)
 *   VALUES ('ebay_sync_order_history',
 *           'catalog/model/checkout/order/addHistory/after',
 *           'event/ebay_sync/orderHistory',
 *           1, 0);
 */

namespace Opencart\Catalog\Controller\Event;

class EbaySync extends \Opencart\System\Engine\Controller
{
    // Statuses that trigger eBay listing removal
    private const TRIGGER_STATUSES = [2, 5];  // Processing, Complete

    /**
     * Fires after addOrderHistory completes.
     * $args[0] = order_id, $args[1] = order_status_id
     */
    public function orderHistory(string $route, array $args, mixed $output = null): void
    {
        $orderId       = (int)($args[0] ?? 0);
        $orderStatusId = (int)($args[1] ?? 0);

        if ($orderId === 0 || !in_array($orderStatusId, self::TRIGGER_STATUSES, true)) {
            return;
        }

        // Get order products from DB directly (avoids framework model issues)
        $products = $this->getOrderProducts($orderId);
        if (empty($products)) {
            return;
        }

        // Find our sync config (outside web root at /root/opencart-marketplace-sync/)
        $configFile = $this->findConfigFile();
        if (!$configFile) {
            return;
        }

        // Process each eBay-synced product in the order
        foreach ($products as $product) {
            $ebayItemId = $product['ebay_item_id'] ?? '';
            if (empty($ebayItemId)) {
                continue;
            }

            $this->removeEbayListing($configFile, $product, $orderId);
        }
    }

    /**
     * Query order products with ebay_item_id directly from the database.
     */
    private function getOrderProducts(int $orderId): array
    {
        $sql = "SELECT op.product_id, op.name, op.quantity, op.price, p.ebay_item_id
                FROM " . DB_PREFIX . "order_product op
                JOIN " . DB_PREFIX . "product p ON op.product_id = p.product_id
                WHERE op.order_id = " . (int)$orderId . "
                  AND p.ebay_item_id IS NOT NULL
                  AND p.ebay_item_id != ''";

        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * Find the sync-app config.php in known locations.
     */
    private function findConfigFile(): ?string
    {
        $candidates = [
            // Development/root location
            '/root/opencart-marketplace-sync/config.php',
            // Alternative location
            dirname(DIR_APPLICATION) . '/../sync-app/config.php',
            // From OC root
            DIR_OPENCART . '../sync-app/config.php',
        ];

        foreach ($candidates as $path) {
            $resolved = realpath($path);
            if ($resolved && file_exists($resolved)) {
                return $resolved;
            }
        }

        error_log('EbaySync: config.php not found at any known location.');
        return null;
    }

    /**
     * Remove an eBay listing via the Trading API.
     */
    private function removeEbayListing(string $configFile, array $product, int $orderId): void
    {
        $config = require $configFile;

        // Autoload EbayInventoryWriter
        $writerPath = dirname($configFile) . '/src/EbayInventoryWriter.php';
        if (!file_exists($writerPath)) {
            error_log("EbaySync: EbayInventoryWriter.php not found at {$writerPath}");
            return;
        }
        require_once $writerPath;

        try {
            $writer = new \EbayInventoryWriter($config);
            $result = $writer->endItem($product['ebay_item_id'], 'NotAvailable');

            if ($result['success']) {
                // Record in oc_ebay_sold_events
                $this->db->query(
                    "INSERT INTO oc_ebay_sold_events SET
                        email_message_id = 'remove:" . (int)$orderId . ":" . (int)$product['product_id'] . ":" . time() . "',
                        ebay_order_id    = '',
                        ebay_item_id     = '" . $this->db->escape(preg_replace('/[^0-9]/', '', $product['ebay_item_id'])) . "',
                        product_id       = " . (int)$product['product_id'] . ",
                        qty_sold         = " . (int)$product['quantity'] . ",
                        new_quantity     = 0,
                        processed_at     = NOW()"
                );

                error_log("EbaySync: Ended eBay listing {$product['ebay_item_id']} (order {$orderId}, product {$product['product_id']}). End time: {$result['end_time']}");
            } else {
                error_log("EbaySync: FAILED to end eBay listing {$product['ebay_item_id']} (order {$orderId}): {$result['error']}");
            }
        } catch (\Throwable $e) {
            error_log("EbaySync: Exception removing listing for product {$product['product_id']}: " . $e->getMessage());
        }
    }
}
