<?php
$cfg = require '/home/geekygoodygoods.com/public_html/ebay-sync/config.php';
require '/home/geekygoodygoods.com/public_html/ebay-sync/src/EbayApi.php';
$a = new EbayApi($cfg['ebay_api']);
$r = $a->searchItems("cassette", 3);
echo "Total: " . ($r['total'] ?? 'N/A') . "\n";
echo "Keys: " . implode(", ", array_keys($r)) . "\n";
$items = $r['itemSummaries'] ?? $r['items'] ?? [];
echo "Item count: " . count($items) . "\n";
if (!empty($items)) {
    $first = $items[0];
    echo "First item keys: " . implode(", ", array_keys($first)) . "\n";
    echo "Has itemId: " . (isset($first['itemId']) ? 'YES' : 'NO') . "\n";
    echo "Has title: " . (isset($first['title']) ? 'YES' : 'NO') . "\n";
    echo "Has image: " . (isset($first['image']) ? 'YES' : 'NO') . "\n";
    echo "Image keys: " . (isset($first['image']) ? implode(", ", array_keys($first['image'])) : 'N/A') . "\n";
    echo "Price: " . json_encode($first['price'] ?? 'N/A') . "\n";
}
