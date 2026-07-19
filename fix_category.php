<?php
require_once __DIR__ . '/src/OpenCartDb.php';
$cfg = require __DIR__ . '/config.php';
$pdo = new PDO("mysql:host=localhost;dbname=geek_shop;charset=utf8mb4", "geek_shop", "jqqeO-C1kna^4Go0", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$oc = new OpenCartDb($pdo, "oc_");

// Create Music category
$catId = $oc->ensureCategory("Music", 0);
if ($catId) {
    echo "Created Music category ID: $catId\n";
    
    // Move product 53 to Music
    $stmt = $pdo->prepare("DELETE FROM oc_product_to_category WHERE product_id = 53");
    $stmt->execute();
    $stmt = $pdo->prepare("INSERT INTO oc_product_to_category (product_id, category_id) VALUES (53, ?)");
    $stmt->execute([$catId]);
    echo "Product 53 moved to Music (ID: $catId)\n";
} else {
    echo "Failed to create Music category\n";
}
