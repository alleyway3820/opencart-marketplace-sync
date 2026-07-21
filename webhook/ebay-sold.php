<?php
/**
 * eBay Sold Webhook
 *
 * Called by n8n when it detects an eBay "Your item sold!" email.
 * Decrements OpenCart stock for the matching product.
 *
 * POST /webhook/ebay-sold.php
 * Headers:
 *   Content-Type: application/json
 *   X-Webhook-Secret: <shared_secret>
 *
 * Body:
 *   {
 *     "email_message_id": "<abc123@mail.ebay.com>",   // MIME Message-ID (idempotency key)
 *     "ebay_item_id":     "335678901234",              // eBay item ID (numeric)
 *     "ebay_order_id":    "25-12345-67890",            // eBay order number (optional)
 *     "qty_sold":         1                             // Quantity sold (default: 1)
 *   }
 */

declare(strict_types=1);

header('Content-Type: application/json');

// ── Config ──────────────────────────────────────────────────────────────────
// Webhook secret is in config.php under 'webhook_secret'
// Generate:  php -r "echo bin2hex(random_bytes(32));"

// ── Auth ─────────────────────────────────────────────────────────────────────
$cfg = require __DIR__ . '/../config.php';
$provided = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
if (!hash_equals($cfg['webhook_secret'] ?? '', $provided)) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

// ── Method check ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

// ── Parse body ───────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid JSON']));
}

$emailMsgId  = trim($data['email_message_id'] ?? '');
$ebayItemId  = trim($data['ebay_item_id'] ?? '');
$ebayOrderId = trim($data['ebay_order_id'] ?? '');
$qtySold     = max(1, (int)($data['qty_sold'] ?? 1));

if ($emailMsgId === '' || $ebayItemId === '') {
    http_response_code(400);
    exit(json_encode(['error' => 'email_message_id and ebay_item_id are required']));
}

// ── DB connection ────────────────────────────────────────────────────────────
$oc = $cfg['opencart'];

try {
    $pdo = new PDO(
        "mysql:host={$oc['host']};port={$oc['port']};dbname={$oc['dbname']};charset=utf8mb4",
        $oc['username'],
        $oc['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'Database connection failed']));
}

// ── Normalize eBay item ID ───────────────────────────────────────────────────
$numericId = preg_replace('/[^0-9]/', '', $ebayItemId);
$v1Id      = "v1|{$numericId}|0";

// ── Idempotency check ────────────────────────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT id, product_id, new_quantity FROM oc_ebay_sold_events
     WHERE email_message_id = ? LIMIT 1"
);
$stmt->execute([$emailMsgId]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    http_response_code(200);
    exit(json_encode([
        'status'       => 'already_processed',
        'product_id'   => (int)$existing['product_id'],
        'new_quantity' => (int)$existing['new_quantity'],
    ]));
}

// ── Find product ─────────────────────────────────────────────────────────────
$productId = null;
$matchedSku = null;

// Try numeric ID first (bare), then v1 format, then sku column as fallback
$prefix = $oc['prefix'] ?? 'oc_';

foreach ([$numericId, $v1Id] as $candidate) {
    // Check ebay_item_id column (primary)
    $s = $pdo->prepare(
        "SELECT product_id, ebay_item_id FROM {$prefix}product WHERE ebay_item_id = ? LIMIT 1"
    );
    $s->execute([$candidate]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $productId = (int)$row['product_id'];
        $matchedSku = $candidate;
        break;
    }

    // Fallback: check sku column (legacy items not yet migrated)
    $s = $pdo->prepare(
        "SELECT product_id FROM {$prefix}product WHERE sku = ? LIMIT 1"
    );
    $s->execute([$candidate]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $productId = (int)$row['product_id'];
        $matchedSku = $candidate;
        // Auto-migrate to ebay_item_id column
        $pdo->prepare(
            "UPDATE {$prefix}product SET ebay_item_id = ?, sku = '' WHERE product_id = ?"
        )->execute([$candidate, $productId]);
        break;
    }
}

if ($productId === null) {
    http_response_code(404);
    exit(json_encode([
        'error'        => 'Product not found',
        'ebay_item_id' => $ebayItemId,
        'tried'        => [$numericId, $v1Id],
    ]));
}

// ── Decrement stock ──────────────────────────────────────────────────────────
$pdo->beginTransaction();

$stmt = $pdo->prepare(
    "SELECT quantity FROM {$prefix}product WHERE product_id = ? FOR UPDATE"
);
$stmt->execute([$productId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    $pdo->rollBack();
    http_response_code(500);
    exit(json_encode(['error' => 'Product vanished during read']));
}

$oldQty = (int)$row['quantity'];
$newQty = max(0, $oldQty - $qtySold);

$pdo->prepare(
    "UPDATE {$prefix}product SET quantity = ?, date_modified = NOW() WHERE product_id = ?"
)->execute([$newQty, $productId]);

// ── Record event ─────────────────────────────────────────────────────────────
$pdo->prepare(
    "INSERT INTO oc_ebay_sold_events
        (email_message_id, ebay_order_id, ebay_item_id, product_id, qty_sold, new_quantity, processed_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())"
)->execute([$emailMsgId, $ebayOrderId, $numericId, $productId, $qtySold, $newQty]);

$pdo->commit();

http_response_code(200);
echo json_encode([
    'status'           => 'ok',
    'product_id'       => $productId,
    'ebay_item_id'     => $numericId,
    'qty_sold'         => $qtySold,
    'old_quantity'     => $oldQty,
    'new_quantity'     => $newQty,
]);
