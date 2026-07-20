<?php
$db = new PDO("mysql:host=localhost;dbname=geek_shop;charset=utf8mb4", "geek_shop", "jqqeO-C1kna^4Go0", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Remove from shipping category
$db->exec("DELETE FROM oc_extension WHERE `code`='sync' AND `type`='shipping'");

// Register as a standalone tool (no type category)
$db->exec("INSERT IGNORE INTO oc_extension (extension_id, extension, `type`, `code`) VALUES (53, 'opencart', '', 'sync')");

// Add permissions if not already there
$r = $db->query("SELECT permission FROM oc_user_group WHERE user_group_id = 1");
$perm = json_decode($r->fetchColumn(), true);
$found = false;
foreach (['access', 'modify'] as $type) {
    foreach ($perm[$type] ?? [] as $p) {
        if (strpos($p, 'extension/opencart/shipping/sync') !== false) $found = true;
    }
}
if (!$found) {
    foreach (['access', 'modify'] as $type) {
        $perm[$type][] = 'extension/opencart/shipping/sync';
    }
    $db->exec("UPDATE oc_user_group SET permission = " . $db->quote(json_encode($perm)) . " WHERE user_group_id = 1");
    echo "Permissions updated\n";
}

echo "Done. Sync tool removed from Shipping category.\n";
echo "Access directly at:\n";
echo "  /shopadmin/index.php?route=extension/opencart/shipping/sync\n";
echo "  /shopadmin/index.php?route=extension/opencart/shipping/sync.preview\n";
