<?php
/**
 * Fix OpenCart store settings for US launch.
 * Run on the server: php fix_settings.php
 */
$db = new PDO("mysql:host=localhost;dbname=geek_shop;charset=utf8mb4", "geek_shop", "jqqeO-C1kna^4Go0", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$prefix = "oc_";

// Settings to update - these go into oc_setting table
$settings = [
    // Store info
    ['config_name', 'Geeky Goody Goods', 0],
    ['config_owner', 'Tony Caston', 0],
    ['config_title', 'Geeky Goody Goods', 0],
    ['config_meta_description', 'Used electronics, cassettes, collectibles and more', 0],
    
    // Location (Houston, Texas, USA)
    ['config_country_id', 223, 0],  // United States
    ['config_zone_id', 3669, 0],    // Texas
    
    // Tax
    ['config_tax', 1, 0],           // Display prices with tax
    ['config_tax_default', 'shipping', 0],  // Default tax based on shipping address
    ['config_tax_customer', 'shipping', 0], // Tax based on customer's shipping address
    
    // Currency
    ['config_currency', 'USD', 0],
    
    // Length and weight
    ['config_length_class_id', 2, 0],  // Centimeter? Let's verify
    ['config_weight_class_id', 1, 0],  // Kilogram? Let's verify
    
    // Language
    ['config_language', 'en-gb', 0],  // Keep English
    
    // Order status defaults
    ['config_order_status_id', 1, 0],  // Pending
    ['config_complete_status_id', 5, 0], // Complete
    
    // Email
    ['config_email', 'tonyc@ageek4less.com', 0],
];

$stmt = $db->prepare("
    INSERT INTO {$prefix}setting (code, `key`, value, serialized, store_id)
    VALUES ('config', ?, ?, 0, 0)
    ON DUPLICATE KEY UPDATE value = VALUES(value)
");

foreach ($settings as [$key, $value, $storeId]) {
    try {
        $stmt->execute([$key, $value]);
        echo "OK: $key = $value\n";
    } catch (Exception $e) {
        echo "ERR: $key - " . $e->getMessage() . "\n";
    }
}

echo "\nDone. Clear the system cache to see changes.\n";
