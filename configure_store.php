<?php
/**
 * Step 3-7: Configure US tax and shipping for OpenCart.
 * Run: php configure_store.php
 */
$db = new PDO("mysql:host=localhost;dbname=geek_shop;charset=utf8mb4", "geek_shop", "jqqeO-C1kna^4Go0", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

echo "=== Current Configuration ===\n\n";

// Check geo zones
$stmt = $db->query("SELECT geo_zone_id, name FROM oc_geo_zone");
echo "Geo Zones:\n";
foreach ($stmt as $r) echo "  [{$r['geo_zone_id']}] {$r['name']}\n";

// Check tax rates
$stmt = $db->query("SELECT tax_rate_id, name, rate, `type` FROM oc_tax_rate");
echo "\nTax Rates:\n";
foreach ($stmt as $r) echo "  [{$r['tax_rate_id']}] {$r['name']} - {$r['rate']}% ({$r['type']})\n";

// Check tax classes
$stmt = $db->query("SELECT tax_class_id, title FROM oc_tax_class");
echo "\nTax Classes:\n";
foreach ($stmt as $r) echo "  [{$r['tax_class_id']}] {$r['title']}\n";

// Check tax rate to geo zone mappings (OC4 stores geo_zone_id in tax_rate table)
$stmt = $db->query("SELECT tax_rate_id, geo_zone_id FROM oc_tax_rate");
echo "\nTax Rate → Geo Zone (from oc_tax_rate table):\n";
foreach ($stmt as $r) echo "  Tax rate {$r['tax_rate_id']} → Geo zone {$r['geo_zone_id']}\n";

// Check shipping methods
$stmt = $db->query("SELECT extension_id, `code`, `type` FROM oc_extension WHERE `type` = 'shipping'");
echo "\nShipping Extensions:\n";
foreach ($stmt as $r) echo "  [{$r['extension_id']}] {$r['code']} ({$r['type']})\n";

echo "\n=== Creating US Tax Configuration ===\n\n";

// Step 3: Create Texas geo zone
$geo = $db->prepare("INSERT INTO oc_geo_zone (name, description, date_added, date_modified) VALUES (?, ?, NOW(), NOW())");
$geo->execute(['Texas', 'Texas sales tax zone']);
$tx_geo_id = $db->lastInsertId();
echo "3. Created Texas geo zone ID: $tx_geo_id\n";

// Add zone to geo zone (Texas zone_id = 3669)
$z2g = $db->prepare("INSERT INTO oc_zone_to_geo_zone (country_id, zone_id, geo_zone_id, date_added, date_modified) VALUES (?, ?, ?, NOW(), NOW())");
$z2g->execute([223, 3669, $tx_geo_id]);
echo "   Added Texas zone to geo zone\n";

// Also create Continental US geo zone for shipping
$geo2 = $db->prepare("INSERT INTO oc_geo_zone (name, description, date_added, date_modified) VALUES (?, ?, NOW(), NOW())");
$geo2->execute(['Continental US', 'Continental United States for shipping']);
$us_geo_id = $db->lastInsertId();
echo "   Created Continental US geo zone ID: $us_geo_id\n";

// Add all US states to Continental US geo zone
$z2g2 = $db->prepare("INSERT INTO oc_zone_to_geo_zone (country_id, zone_id, geo_zone_id, date_added, date_modified) VALUES (?, ?, ?, NOW(), NOW())");
// Get all US zones
foreach ($db->query("SELECT zone_id FROM oc_zone WHERE country_id = 223") as $zone) {
    $z2g2->execute([223, $zone['zone_id'], $us_geo_id]);
}
echo "   Added all US states/zones to Continental US geo zone\n";

// Step 4: Create tax rates
// Texas state rate: 6.25%
$rate1 = $db->prepare("INSERT INTO oc_tax_rate (geo_zone_id, name, rate, `type`, date_added, date_modified) VALUES (?, ?, ?, ?, NOW(), NOW())");
$rate1->execute([$tx_geo_id, 'Texas State Sales Tax', 6.2500, 'P']);
$state_rate_id = $db->lastInsertId();
echo "4. Created Texas state tax rate ID: $state_rate_id (6.25%)\n";

// Texas local rate (Houston area): ~2%
$rate1->execute([$tx_geo_id, 'Texas Local Sales Tax', 2.0000, 'P']);
$local_rate_id = $db->lastInsertId();
echo "   Created Texas local tax rate ID: $local_rate_id (2.00%)\n";

// Step 5: Create Taxable Goods tax class
// Check if already exists
$existing = $db->query("SELECT tax_class_id FROM oc_tax_class WHERE title = 'Taxable Goods'")->fetch();
if (!$existing) {
    $tc = $db->prepare("INSERT INTO oc_tax_class (title, description, date_added, date_modified) VALUES (?, ?, NOW(), NOW())");
    $tc->execute(['Taxable Goods', 'Standard taxable goods for US sales']);
    $tax_class_id = $db->lastInsertId();
    echo "5. Created Taxable Goods tax class ID: $tax_class_id\n";
} else {
    $tax_class_id = $existing['tax_class_id'];
    echo "5. Taxable Goods tax class already exists (ID: $tax_class_id)\n";
}

// Assign both tax rates to the tax class
$tr = $db->prepare("INSERT INTO oc_tax_rule (tax_class_id, tax_rate_id, based, priority) VALUES (?, ?, 'shipping', 1)");
$tr->execute([$tax_class_id, $state_rate_id]);
echo "   Assigned state rate to Taxable Goods\n";
$tr->execute([$tax_class_id, $local_rate_id]);
echo "   Assigned local rate to Taxable Goods\n";

// Update all existing products to use Taxable Goods
$db->exec("UPDATE oc_product SET tax_class_id = $tax_class_id WHERE tax_class_id != $tax_class_id");
echo "   Updated all products to use Taxable Goods tax class\n";

// Step 6: Remove UK tax configuration
// UK VAT zone is geo_zone 3, UK Shipping is geo_zone 4
$db->exec("DELETE FROM oc_tax_rule WHERE tax_rule_id > 0");
echo "6. Removed existing tax rules\n";
// Mark old UK geo zones as unused (keep them for safety)
echo "   UK geo zones (3, 4) kept but unused\n";

// Step 7: Enable Flat Rate shipping for US
// Check if flat rate shipping exists
$fr = $db->query("SELECT extension_id FROM oc_extension WHERE `code` = 'flat' AND `type` = 'shipping'")->fetch();
if ($fr) {
    echo "7. Flat Rate shipping extension already installed (ID: {$fr['extension_id']})\n";
} else {
    $ext = $db->prepare("INSERT INTO oc_extension (extension_id, `code`, `type`) VALUES (?, 'flat', 'shipping')");
    $ext->execute([$db->query("SELECT COALESCE(MAX(extension_id), 0) + 1 FROM oc_extension")->fetchColumn()]);
    echo "7. Installed Flat Rate shipping\n";
}

// Configure flat rate shipping
$setting = $db->prepare("INSERT INTO oc_setting (code, `key`, value, serialized, store_id) VALUES ('shipping_flat', ?, ?, 0, 0) ON DUPLICATE KEY UPDATE value = VALUES(value)");
$setting->execute(['shipping_flat_status', '1']);
$setting->execute(['shipping_flat_geo_zone_id', (string)$us_geo_id]);
$setting->execute(['shipping_flat_tax_class_id', (string)$tax_class_id]);
$setting->execute(['shipping_flat_cost', '5.00']);
$setting->execute(['shipping_flat_sort_order', '1']);
echo "   Flat Rate configured: \$5.00 for Continental US\n";

echo "\n=== Done! ===\n";
echo "Texas tax: 6.25% state + 2.00% local = 8.25% combined\n";
echo "Shipping: Flat rate $5.00 to Continental US\n";
