<?php
$db = new PDO("mysql:host=localhost;dbname=geek_shop;charset=utf8mb4", "geek_shop", "jqqeO-C1kna^4Go0");

// Update the meta title
$db->exec("UPDATE oc_setting SET value='Geeky Goody Goods' WHERE `key`='config_meta_title' AND store_id=0");

// Remove duplicate config_name entries (keep the latest)
$db->exec("DELETE FROM oc_setting WHERE `key`='config_name' AND store_id=0 AND setting_id NOT IN (
    SELECT setting_id FROM (SELECT MAX(setting_id) as setting_id FROM oc_setting WHERE `key`='config_name' AND store_id=0) AS t
)");

echo "Fixed.\n";
foreach ($db->query("SELECT `key`, value FROM oc_setting WHERE `key` IN ('config_name','config_title','config_meta_title')") as $r) {
    echo $r['key'] . " = " . $r['value'] . "\n";
}
