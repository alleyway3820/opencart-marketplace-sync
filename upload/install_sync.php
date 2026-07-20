<?php
$db = new PDO("mysql:host=localhost;dbname=geek_shop;charset=utf8mb4", "geek_shop", "jqqeO-C1kna^4Go0", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Register extension and paths for the sync controller
$db->exec("INSERT IGNORE INTO oc_extension (extension_id, extension, `type`, `code`) VALUES (52, 'opencart', 'shipping', 'sync')");

// Add extension_path entries
$paths = [
    'opencart/admin/controller/shipping/sync.php',
    'opencart/admin/language/en-gb/shipping/sync.php',
    'opencart/admin/view/template/shipping/sync.twig',
    'opencart/admin/view/template/shipping/sync_preview.twig',
];

$nextId = (int)$db->query("SELECT COALESCE(MAX(extension_path_id), 0) + 1 FROM oc_extension_path")->fetchColumn();
$stmt = $db->prepare("INSERT IGNORE INTO oc_extension_path (extension_path_id, extension_install_id, path) VALUES (?, 0, ?)");
foreach ($paths as $i => $path) {
    $stmt->execute([$nextId + $i, $path]);
    echo "Added: $path\n";
}

// Add permissions
$r = $db->query("SELECT permission FROM oc_user_group WHERE user_group_id = 1");
$perm = json_decode($r->fetchColumn(), true);
foreach (['access', 'modify'] as $type) {
    $has = false;
    foreach ($perm[$type] ?? [] as $p) {
        if (strpos($p, 'extension/opencart/shipping/sync') !== false) $has = true;
    }
    if (!$has) {
        $perm[$type][] = 'extension/opencart/shipping/sync';
    }
}
$db->exec("UPDATE oc_user_group SET permission = " . $db->quote(json_encode($perm)) . " WHERE user_group_id = 1");
echo "Permissions added\n";

echo "\nDone. Visit Extensions > Shipping to see Marketplace Sync.\n";
