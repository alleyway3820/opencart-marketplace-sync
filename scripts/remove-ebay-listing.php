<?php
/**
 * eBay Sync - Standalone script to end an eBay listing when an OC product sells.
 *
 * Usage:
 *   php scripts/remove-ebay-listing.php <product_id> [order_id]
 *
 * The script:
 *   1. Looks up the OC product by product_id
 *   2. Checks if ebay_item_id is set
 *   3. If yes, calls eBay Trading API EndItem
 *   4. Logs the result to oc_ebay_sold_events and PHP error log
 *
 * Returns exit code 0 on success, 1 on failure.
 */

declare(strict_types=1);

// Determine base directory
$baseDir = dirname(__DIR__);

// Load config
$configFile = $baseDir . '/config.php';
if (!file_exists($configFile)) {
    fwrite(STDERR, "ERROR: config.php not found\n");
    exit(1);
}
$config = require $configFile;

// Autoload
require_once $baseDir . '/src/EbayInventoryWriter.php';

// ── Parse args ──────────────────────────────────────────────────────────────
if ($argc < 2) {
    fwrite(STDERR, "Usage: php remove-ebay-listing.php <product_id> [order_id]\n");
    exit(1);
}

$productId = (int)$argv[1];
$orderId   = $argv[2] ?? '';

// ── DB connection ────────────────────────────────────────────────────────────
$oc = $config['opencart'];
$pdo = new PDO(
    "mysql:host={$oc['host']};port={$oc['port']};dbname={$oc['dbname']};charset=utf8mb4",
    $oc['username'],
    $oc['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$prefix = $oc['prefix'] ?? 'oc_';

// ── Find product ─────────────────────────────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT product_id, ebay_item_id, quantity, name FROM {$prefix}product p
     LEFT JOIN {$prefix}product_description pd ON p.product_id = pd.product_id AND pd.language_id = 1
     WHERE p.product_id = ?"
);
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    fwrite(STDERR, "ERROR: Product {$productId} not found\n");
    exit(1);
}

$ebayItemId = $product['ebay_item_id'] ?? '';
if (empty($ebayItemId)) {
    echo "SKIP: Product {$productId} has no ebay_item_id — not an eBay-synced listing.\n";
    exit(0);
}

echo "Product: {$product['name']}\n";
echo "eBay Item ID: {$ebayItemId}\n";
echo "Current OC Qty: {$product['quantity']}\n";

// ── Call eBay API ────────────────────────────────────────────────────────────
$writer = new EbayInventoryWriter($config);
$result = $writer->endItem($ebayItemId, 'NotAvailable');

if ($result['success']) {
    echo "OK: eBay listing {$ebayItemId} ended. End time: {$result['end_time']}\n";

    // Record in oc_ebay_sold_events (idempotency ledger)
    $stmt = $pdo->prepare(
        "INSERT INTO oc_ebay_sold_events
            (email_message_id, ebay_order_id, ebay_item_id, product_id, qty_sold, new_quantity, processed_at)
         VALUES (?, ?, ?, ?, ?, 0, NOW())"
    );
    $stmt->execute([
        'remove:' . $productId . ':' . time(),
        $orderId ?: 'order:' . $orderId,
        preg_replace('/[^0-9]/', '', $ebayItemId),
        $productId,
        1,
    ]);

    exit(0);
} else {
    fwrite(STDERR, "ERROR: Failed to end eBay listing {$ebayItemId}: {$result['error']}\n");
    exit(1);
}
